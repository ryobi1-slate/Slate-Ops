<?php
/**
 * Plugin Name: Slate Ops
 * Description: Internal Ops layer for Slate (jobs, status, time-ready contract for scheduler).
 * Version: 0.2.0
 * Author: Slate
 */

if (!defined('ABSPATH')) exit;

define('SLATE_OPS_VERSION', '0.2.0');
define('SLATE_OPS_PATH', plugin_dir_path(__FILE__));
define('SLATE_OPS_URL', plugin_dir_url(__FILE__));
require_once SLATE_OPS_PATH . 'includes/class-slate-ops-assets.php';

require_once SLATE_OPS_PATH . 'includes/class-slate-ops-utils.php';
require_once SLATE_OPS_PATH . 'includes/class-slate-ops-install.php';
require_once SLATE_OPS_PATH . 'includes/class-slate-ops-contract.php';
require_once SLATE_OPS_PATH . 'includes/class-slate-ops-rest.php';
require_once SLATE_OPS_PATH . 'includes/class-slate-ops-ui.php';

register_activation_hook(__FILE__, ['Slate_Ops_Install', 'activate']);

add_action('init', ['Slate_Ops_UI', 'register_routes']);
add_action('rest_api_init', ['Slate_Ops_REST', 'register_routes']);

add_action('wp_enqueue_scripts', function() {
  if (!Slate_Ops_UI::is_ops_request()) return;

  wp_enqueue_style('slate-ops', SLATE_OPS_URL . 'assets/css/ops.css', [], SLATE_OPS_VERSION);
  wp_enqueue_script('slate-ops', SLATE_OPS_URL . 'assets/js/ops.js', [], SLATE_OPS_VERSION, true);

  wp_localize_script('slate-ops', 'slateOpsSettings', [
    'restRoot' => esc_url_raw(rest_url('slate-ops/v1')),
    'nonce' => wp_create_nonce('wp_rest'),
  ]);
});
