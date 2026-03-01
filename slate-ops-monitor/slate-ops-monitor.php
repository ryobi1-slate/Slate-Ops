<?php
/**
 * Plugin Name: Slate Ops Shop Monitor
 * Description: Display-only shop floor monitor (Kanban) powered by a bundled React UI. Pulls live job data from Slate Ops — no ClickUp dependency.
 * Version: 0.1.0
 * Author: Slate
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Slate_Ops_Monitor {

    const SHORTCODE     = 'slate_ops_monitor';
    const SCRIPT_HANDLE = 'slate-ops-monitor-app';
    const TW_HANDLE     = 'slate-ops-monitor-tailwindcdn';
    const CACHE_KEY     = 'slate_ops_monitor_jobs';
    const CACHE_TTL     = 30; // seconds — faster refresh than ClickUp version since data is local

    public static function init() {
        add_shortcode( self::SHORTCODE, [ __CLASS__, 'shortcode' ] );
        add_action( 'wp_enqueue_scripts',  [ __CLASS__, 'register_assets' ] );
        add_filter( 'script_loader_tag',   [ __CLASS__, 'add_module_attribute' ], 10, 3 );
        add_action( 'rest_api_init',       [ __CLASS__, 'register_api' ] );
    }

    // ──────────────────────────────────────────────────────────────
    // REST API
    // ──────────────────────────────────────────────────────────────

    public static function register_api() {
        register_rest_route( 'slate-monitor/v1', '/jobs', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_jobs' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'slate-monitor/v1', '/health', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'health' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public static function health() {
        return rest_ensure_response( [
            'ok'                => true,
            'source'            => 'slate-ops',
            'cache_ttl_seconds' => self::CACHE_TTL,
        ] );
    }

    /**
     * Serve job data to the React frontend.
     * Fetches raw rows from the Slate Ops shop-floor endpoint and normalises
     * them into the same shape the monitor bundle already understands.
     */
    public static function get_jobs() {
        $cached = get_transient( self::CACHE_KEY );
        if ( $cached !== false ) {
            return rest_ensure_response( $cached );
        }

        $response = wp_remote_get( rest_url( 'slate/v2/shop-floor' ), [
            'timeout' => 10,
        ] );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'slate_monitor_upstream',
                'Slate Ops endpoint unreachable: ' . $response->get_error_message(),
                [ 'status' => 502 ]
            );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return new WP_Error(
                'slate_monitor_upstream',
                "Slate Ops endpoint returned HTTP {$code}",
                [ 'status' => 502 ]
            );
        }

        $raw = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $raw ) ) {
            return new WP_Error(
                'slate_monitor_data',
                'Invalid response from Slate Ops endpoint.',
                [ 'status' => 502 ]
            );
        }

        $jobs = array_values( array_map( [ __CLASS__, 'normalize_job' ], $raw ) );
        set_transient( self::CACHE_KEY, $jobs, self::CACHE_TTL );

        return rest_ensure_response( $jobs );
    }

    // ──────────────────────────────────────────────────────────────
    // Normalisation  (Slate Ops DB row → monitor display shape)
    // ──────────────────────────────────────────────────────────────

    private static function s( $v ) {
        return trim( (string) $v );
    }

    private static function is_numeric_only( $v ) {
        $v = self::s( $v );
        return $v !== '' && preg_match( '/^\d+$/', $v ) === 1;
    }

    private static function clean_dealer( $dealer ) {
        $dealer = self::s( $dealer );
        return ( $dealer === '' || self::is_numeric_only( $dealer ) ) ? '' : $dealer;
    }

    /**
     * Identical logic to the original UpfitOps monitor so the display cards
     * look exactly the same.
     */
    private static function compute_party_display( $dealer, $customer, $so_number ) {
        $dealer_raw = self::s( $dealer );
        $dealer     = self::clean_dealer( $dealer_raw );
        $customer   = self::s( $customer );
        $so_number  = self::s( $so_number );

        if ( $customer !== '' && $dealer !== '' ) {
            return [ 'primary_name' => $customer, 'secondary_name' => 'via ' . $dealer, 'party_scenario' => 'retail' ];
        }
        if ( $customer !== '' && $dealer === '' ) {
            return [ 'primary_name' => $customer, 'secondary_name' => 'Direct', 'party_scenario' => 'direct' ];
        }
        if ( $customer === '' && $dealer_raw !== '' ) {
            $primary = $dealer !== '' ? $dealer : ( $so_number !== '' ? $so_number : 'Unknown' );
            return [ 'primary_name' => $primary, 'secondary_name' => 'Stock unit', 'party_scenario' => 'stock' ];
        }
        return [ 'primary_name' => ( $so_number !== '' ? $so_number : 'Unknown' ), 'secondary_name' => '', 'party_scenario' => 'fallback' ];
    }

    /**
     * Format a WordPress display_name into "First L." style, matching
     * how the original monitor formatted ClickUp assignee names.
     */
    private static function format_tech( $display_name ) {
        $name  = self::s( $display_name );
        if ( $name === '' ) return 'Unassigned';
        $parts = array_values( array_filter( preg_split( '/\s+/', $name ) ) );
        if ( count( $parts ) >= 2 ) {
            return $parts[0] . ' ' . strtoupper( $parts[ count( $parts ) - 1 ][0] ) . '.';
        }
        return $parts[0] ?? $name;
    }

    /**
     * Map Slate Ops internal statuses to the display strings the React bundle
     * already knows how to render (same vocabulary as the ClickUp version).
     */
    private static function map_status( $ops_status, $delay_reason = '' ) {
        $s = strtoupper( self::s( $ops_status ) );
        $r = strtolower( self::s( $delay_reason ) );

        switch ( $s ) {
            case 'UNSCHEDULED':
                return 'UNSCHEDULED';
            case 'READY_FOR_SCHEDULING':
                return 'SCHEDULED – READY TO START';
            case 'SCHEDULED':
                return 'SCHEDULED – NOT ARRIVED';
            case 'DELAYED':
                if ( strpos( $r, 'parts' ) !== false ) return 'DELAY – PARTS';
                if ( strpos( $r, 'hold'  ) !== false ) return 'DELAY – ON HOLD';
                return 'DELAY – UPDATE REQUIRED';
            case 'BLOCKED':
                return 'DELAY – ON HOLD';
            case 'IN_PROGRESS':
                return 'IN PROGRESS';
            case 'PENDING_QC':
                return 'QUALITY CONTROL';
            case 'COMPLETE':
                return 'COMPLETE – AWAITING PICKUP';
            default:
                return $s;
        }
    }

    private static function normalize_job( $job ) {
        $dealer   = self::s( $job['dealer_name']   ?? '' );
        $customer = self::s( $job['customer_name'] ?? '' );
        $so       = self::s( $job['so_number']     ?? '' );
        $vin      = self::s( $job['vin']           ?? '' );
        $party    = self::compute_party_display( $dealer, $customer, $so );

        return [
            'id'                  => (string) ( $job['job_id'] ?? '' ),
            'so_number'           => $so,
            'dealer'              => $dealer,
            'customer'            => $customer,
            'vin'                 => $vin,
            'primary_name'        => (string) $party['primary_name'],
            'secondary_name'      => (string) $party['secondary_name'],
            'party_scenario'      => (string) $party['party_scenario'],
            'vin_last8'           => self::s( $job['vin_last8'] ?? '' ),
            'status'              => self::map_status( $job['status'] ?? '', $job['delay_reason'] ?? '' ),
            'assigned_tech'       => self::format_tech( $job['assigned_user_name'] ?? '' ),
            'start_date'          => $job['scheduled_start']  ?: null,
            'due_date'            => $job['scheduled_finish'] ?: null,
            'time_estimate_hours' => round( (int) ( $job['estimated_minutes'] ?? 0 ) / 60, 1 ),
            'tags'                => [],
            'latest_comment'      => self::s( $job['notes'] ?? '' ),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // Frontend — identical markup, CSS, and JS to the original monitor.
    // Only window.UPFITOPS_API is changed to point at slate-monitor/v1/jobs.
    // ──────────────────────────────────────────────────────────────

    public static function register_assets() {}

    public static function shortcode( $atts = [] ) {
        wp_enqueue_script( self::TW_HANDLE, 'https://cdn.tailwindcss.com', [], null, false );
        wp_add_inline_script(
            self::TW_HANDLE,
            "tailwind.config={theme:{extend:{colors:{gray:{850:'#1f2937',900:'#111827',950:'#030712'}}}}};",
            'after'
        );

        $inline_css = <<<CSS
html, body { background:#030712 !important; color:#fff !important; overflow:hidden !important; margin:0 !important; padding:0 !important; }
header, footer, nav, .site-header, .site-footer, #masthead, #colophon, .navigation, .sidebar, .wp-block-post-title, .entry-title, .page-title { display:none !important; }
#page, #content, #primary, #main, .site, .site-content, .entry-content, article { all:unset !important; display:block !important; }
:root { --upfitops-scale: 1; }
*, *::before, *::after { box-sizing: border-box; }
.upfitops-monitor-wrap { position:fixed !important; top:0; left:0; width:100vw !important; height:100vh !important; z-index:99999; background:#030712 !important; overflow:hidden !important; }
.upfitops-monitor-scale { transform:scale(var(--upfitops-scale)); transform-origin:top left; width:calc(100% / var(--upfitops-scale)); height:calc(100% / var(--upfitops-scale)); }
#upfitops-root { width:100%; height:100%; }
/* Lock to 4 fixed columns on TV displays */
#upfitops-root .flex.h-full.gap-2 > div { width:25% !important; flex:0 0 25% !important; min-width:0 !important; flex-shrink:0 !important; }
#upfitops-root .flex.h-full.gap-2 { overflow-x: hidden !important; align-items: stretch; }
@media (min-width: 1600px) { :root { --upfitops-scale: 1.45; } }
@media (min-width: 2000px) { :root { --upfitops-scale: 1.85; } }
@media (min-width: 3000px) { :root { --upfitops-scale: 2.10; } }
CSS;

        wp_register_style( 'slate-ops-monitor-inline', false );
        wp_enqueue_style( 'slate-ops-monitor-inline' );
        wp_add_inline_style( 'slate-ops-monitor-inline', $inline_css );

        // Prefer wp-content/uploads/slate-ops-monitor/ so a large bundle can be
        // deployed via SFTP without going through git.  Falls back to the plugin's
        // own assets/ directory (or a Git LFS-tracked file placed there).
        $upload     = wp_upload_dir();
        $upload_dir = trailingslashit( $upload['basedir'] ) . 'slate-ops-monitor/';
        $upload_url = trailingslashit( $upload['baseurl'] ) . 'slate-ops-monitor/';

        $bundle = self::find_bundle( $upload_dir );
        if ( $bundle ) {
            $asset_dir = $upload_dir;
            $asset_url = $upload_url;
        } else {
            $asset_dir = plugin_dir_path( __FILE__ ) . 'assets/';
            $asset_url = plugin_dir_url( __FILE__ ) . 'assets/';
            $bundle    = self::find_bundle( $asset_dir );
        }

        if ( ! $bundle ) {
            return '<div style="color:#fff;background:#b91c1c;padding:12px;">'
                . 'JS bundle not found. Place an <code>index-*.js</code> file in '
                . '<code>wp-content/uploads/slate-ops-monitor/</code> or the plugin\'s <code>assets/</code> folder.'
                . '</div>';
        }

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            $asset_url . $bundle,
            [],
            filemtime( $asset_dir . $bundle ),
            true
        );

        // Point the React bundle at OUR endpoint instead of the ClickUp-backed one.
        wp_add_inline_script(
            self::SCRIPT_HANDLE,
            'window.UPFITOPS_API=' . wp_json_encode( rest_url( 'slate-monitor/v1/jobs' ) ) . ';',
            'before'
        );

        add_action( 'wp_footer', function () {
            ?>
            <script>
              (function () {
                var s = new URLSearchParams(window.location.search).get('scale');
                if (s && !isNaN(s)) document.documentElement.style.setProperty('--upfitops-scale', s);
              })();
            </script>
            <?php
        }, 99 );

        return '<div class="upfitops-monitor-wrap"><div class="upfitops-monitor-scale"><div id="upfitops-root"></div></div></div>';
    }

    public static function add_module_attribute( $tag, $handle, $src ) {
        return ( $handle === self::SCRIPT_HANDLE )
            ? sprintf( '<script type="module" src="%s"></script>', esc_url( $src ) )
            : $tag;
    }

    private static function find_bundle( $dir ) {
        if ( ! is_dir( $dir ) ) return null;
        $found = [];
        foreach ( scandir( $dir ) as $f ) {
            if ( preg_match( '/^index-.*\.js$/', $f ) ) {
                $found[] = [ 'f' => $f, 'm' => filemtime( $dir . $f ) ];
            }
        }
        usort( $found, fn( $a, $b ) => $b['m'] - $a['m'] );
        return $found[0]['f'] ?? null;
    }
}

Slate_Ops_Monitor::init();
