<?php
/**
 * Plugin Name: Slate Shop Monitor
 * Description: Industrial Shop Floor Monitor for ClickUp
 * Version: 1.0.2
 * Author: Slate
 * Text Domain: slate-shop-monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Constants
define( 'SLATE_MONITOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'SLATE_MONITOR_URL', plugin_dir_url( __FILE__ ) );

// Include Classes
require_once SLATE_MONITOR_PATH . 'includes/admin/class-slate-monitor-admin.php';
require_once SLATE_MONITOR_PATH . 'includes/service/class-slate-monitor-clickup.php';
require_once SLATE_MONITOR_PATH . 'includes/api/class-slate-monitor-rest.php';

class Slate_Shop_Monitor {

	public function run() {
		// Initialize Admin Settings
		$admin = new Slate_Monitor_Admin();
		add_action( 'admin_menu', array( $admin, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $admin, 'page_init' ) );

		// Initialize REST API
		$api = new Slate_Monitor_Rest();
		add_action( 'rest_api_init', array( $api, 'register_routes' ) );

		// Initialize Shortcode
		add_shortcode( 'slate_shop_monitor', array( $this, 'render_shortcode' ) );
	}

	public function render_shortcode() {
        $asset_rel = 'dist/assets/index.js';
        $asset_abs = SLATE_MONITOR_PATH . $asset_rel;
        $asset_ver = file_exists( $asset_abs ) ? (string) filemtime( $asset_abs ) : '1.0.1';

        // Enqueue React bundle (cache-busted by filemtime)
        wp_enqueue_script(
            'slate-monitor-app',
            SLATE_MONITOR_URL . $asset_rel,
            array(),
            $asset_ver,
            true
        );

        // Add type="module" to the script tag for Vite compatibility (only once)
        static $module_tag_filter_added = false;
        if ( ! $module_tag_filter_added ) {
            $module_tag_filter_added = true;
            add_filter( 'script_loader_tag', function( $tag, $handle, $src ) {
                if ( 'slate-monitor-app' !== $handle ) {
                    return $tag;
                }
                return '<script type="module" src="' . esc_url( $src ) . '"></script>';
            }, 10, 3 );
        }
        // Retrieve Settings
        $options = get_option( 'slate_monitor_settings' );
        $interval = isset($options['refresh_interval']) && !empty($options['refresh_interval']) ? $options['refresh_interval'] : '60000';
        $scale = isset($options['ui_scale']) && !empty($options['ui_scale']) ? $options['ui_scale'] : '1';

        // Pass Settings to JS
        wp_localize_script( 'slate-monitor-app', 'slateMonitorSettings', array(
            'root' => esc_url_raw( rest_url( 'slate-monitor/v1/jobs' ) ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'interval' => $interval,
			'scale' => $scale,
			'pluginUrl' => esc_url_raw( SLATE_MONITOR_URL )
        ));

		// Full-bleed layout reset (only on pages where shortcode is rendered)
		$css = "\n" .
			"html, body { height: 100%; margin: 0; padding: 0; background: #020617; overflow: hidden; }\n" .
			"body { position: relative; }\n" .
			"#page, #content, .site, .site-content, .content-area, main, .entry-content, .wp-site-blocks {\n" .
			"  margin: 0 !important; padding: 0 !important; max-width: none !important; width: 100% !important;\n" .
			"}\n";

		return '<style>' . $css . '</style>' .
			'<div id="root" style="position:fixed; inset:0; width:100vw; height:100vh; z-index:9999;"></div>';
	}
}

$slate_monitor = new Slate_Shop_Monitor();
$slate_monitor->run();