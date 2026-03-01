<?php
/**
 * Plugin Name: Slate Ops Shop Monitor
 * Description: Shop floor monitor connected to Slate Ops (no ClickUp dependency).
 * Version: 1.0.0
 * Author: Slate
 * Text Domain: slate-ops-monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SLATE_OPS_MONITOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'SLATE_OPS_MONITOR_URL',  plugin_dir_url( __FILE__ ) );

require_once SLATE_OPS_MONITOR_PATH . 'includes/admin/class-slate-ops-monitor-admin.php';
require_once SLATE_OPS_MONITOR_PATH . 'includes/service/class-slate-ops-monitor-service.php';
require_once SLATE_OPS_MONITOR_PATH . 'includes/api/class-slate-ops-monitor-rest.php';

class Slate_Ops_Shop_Monitor {

    public function run() {
        $admin = new Slate_Ops_Monitor_Admin();
        add_action( 'admin_menu', array( $admin, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $admin, 'page_init' ) );

        $api = new Slate_Ops_Monitor_Rest();
        add_action( 'rest_api_init', array( $api, 'register_routes' ) );

        add_shortcode( 'slate_ops_monitor', array( $this, 'render_shortcode' ) );
    }

    public function render_shortcode() {
        // Prefer wp-content/uploads/slate-ops-monitor/ for large bundles deployed via SFTP.
        // Falls back to the plugin's own dist/assets/ directory.
        $upload     = wp_upload_dir();
        $upload_dir = trailingslashit( $upload['basedir'] ) . 'slate-ops-monitor/';
        $upload_url = trailingslashit( $upload['baseurl'] ) . 'slate-ops-monitor/';

        if ( file_exists( $upload_dir . 'index.js' ) ) {
            $asset_abs = $upload_dir . 'index.js';
            $asset_url = $upload_url . 'index.js';
        } else {
            $asset_abs = SLATE_OPS_MONITOR_PATH . 'dist/assets/index.js';
            $asset_url = SLATE_OPS_MONITOR_URL  . 'dist/assets/index.js';
        }

        $asset_ver = file_exists( $asset_abs ) ? (string) filemtime( $asset_abs ) : '1.0.0';

        wp_enqueue_script( 'slate-ops-monitor-app', $asset_url, array(), $asset_ver, true );

        static $filter_added = false;
        if ( ! $filter_added ) {
            $filter_added = true;
            add_filter( 'script_loader_tag', function ( $tag, $handle ) {
                if ( 'slate-ops-monitor-app' !== $handle ) {
                    return $tag;
                }
                return '<script type="module" src="' . esc_url( preg_replace( '/.*src=["\']([^"\']+)["\'].*/', '$1', $tag ) ) . '"></script>';
            }, 10, 2 );
        }

        wp_enqueue_script( 'slate-ops-monitor-tailwind', 'https://cdn.tailwindcss.com', array(), '3.0', false );

        $options  = get_option( 'slate_ops_monitor_settings', array() );
        $interval = ! empty( $options['refresh_interval'] ) ? $options['refresh_interval'] : '60000';
        $scale    = ! empty( $options['ui_scale'] )         ? $options['ui_scale']         : '1';

        wp_localize_script( 'slate-ops-monitor-app', 'slateMonitorSettings', array(
            'root'      => esc_url_raw( rest_url( 'slate-ops-monitor/v1/jobs' ) ),
            'nonce'     => wp_create_nonce( 'wp_rest' ),
            'interval'  => $interval,
            'scale'     => $scale,
            'pluginUrl' => esc_url_raw( SLATE_OPS_MONITOR_URL ),
        ) );

        $css = "html, body { height:100%; margin:0; padding:0; background:#020617; overflow:hidden; }\n"
             . "body { position:relative; }\n"
             . "#page, #content, .site, .site-content, .content-area, main, .entry-content, .wp-site-blocks {\n"
             . "  margin:0 !important; padding:0 !important; max-width:none !important; width:100% !important;\n"
             . "}\n";

        return '<style>' . $css . '</style>'
             . '<div id="root" style="position:fixed;inset:0;width:100vw;height:100vh;z-index:9999;"></div>';
    }
}

$slate_ops_shop_monitor = new Slate_Ops_Shop_Monitor();
$slate_ops_shop_monitor->run();
