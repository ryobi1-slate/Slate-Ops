<?php
/**
 * Plugin Name: Slate Ops
 * Description: Internal Ops UI (/ops/) for Customer Service, Shop Supervisor, and Techs. Integrates with Slate Dealer Portal + ClickUp.
 * Version: 0.3.5
 * Author: Slate
 */

if (!defined('ABSPATH')) exit;

define('SLATE_OPS_VERSION', '0.3.5');
define('SLATE_OPS_PATH', plugin_dir_path(__FILE__));
define('SLATE_OPS_URL', plugin_dir_url(__FILE__));
require_once SLATE_OPS_PATH . 'includes/class-slate-ops-assets.php';
require_once SLATE_OPS_PATH . 'includes/class-slate-ops-contract.php';

require_once SLATE_OPS_PATH . 'includes/class-slate-ops-install.php';
require_once SLATE_OPS_PATH . 'includes/class-slate-ops-roles.php';
require_once SLATE_OPS_PATH . 'includes/class-slate-ops-routes.php';
require_once SLATE_OPS_PATH . 'includes/class-slate-ops-rest.php';
require_once SLATE_OPS_PATH . 'includes/class-slate-ops-clickup.php';
require_once SLATE_OPS_PATH . 'includes/class-slate-ops-utils.php';

register_activation_hook(__FILE__, ['Slate_Ops_Install', 'activate']);
register_deactivation_hook(__FILE__, ['Slate_Ops_Install', 'deactivate']);

add_action('init', ['Slate_Ops_Roles', 'register_roles_caps']);
add_action('init', ['Slate_Ops_Install', 'maybe_upgrade']);
add_action('init', ['Slate_Ops_Routes', 'register_routes']);
add_action('rest_api_init', ['Slate_Ops_REST', 'register_routes']);

add_action('wp_enqueue_scripts', function() {
  if (!Slate_Ops_Routes::is_ops_request()) return;

  wp_enqueue_style('material-symbols', 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200', [], null);
  wp_enqueue_style('slate-ops', SLATE_OPS_URL . 'assets/css/ops.css', ['material-symbols'], SLATE_OPS_VERSION);
  wp_enqueue_script('slate-ops', SLATE_OPS_URL . 'assets/js/ops.js', [], SLATE_OPS_VERSION, true);

  wp_localize_script('slate-ops', 'slateOpsSettings', [
    'restRoot' => esc_url_raw(rest_url('slate-ops/v1')),
    'nonce' => wp_create_nonce('wp_rest'),
    'user' => [
      'id' => get_current_user_id(),
      'name' => wp_get_current_user()->display_name,
      'caps' => Slate_Ops_Utils::current_user_caps_summary(),
    ],
    'colors' => [
      'sage' => '#404f4b',
      'sand' => '#e1dfc8',
      'arches' => '#d86b19',
      'redwood' => '#0f342a',
      'black' => '#000000',
      'white' => '#ffffff',
    ],
    'dealers' => array_values(Slate_Ops_Utils::dealer_list()),
  ]);
});

add_filter('template_include', function($template) {
  if (!Slate_Ops_Routes::is_ops_request()) return $template;
  return SLATE_OPS_PATH . 'templates/ops-app.php';
}, 99);

/**
 * Integration point: Dealer Portal quote approved.
 * Expected: do_action('slate_quote_approved', $quote_id);
 * slate-ops will create a job + ClickUp unscheduled task.
 */
add_action('slate_quote_approved', function($quote_id) {
  // Avoid fatal if REST class not loaded for some reason.
  if (!class_exists('Slate_Ops_REST')) return;
  Slate_Ops_REST::handle_quote_approved((int)$quote_id);
}, 10, 1);
