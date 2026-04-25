<?php
/**
 * Plugin Name: Slate Ops
 * Description: Internal Ops UI (/ops/) for Customer Service, Shop Supervisor, and Techs. Integrates with Slate Dealer Portal + ClickUp.
 * Version: 0.21.1
 * Author: Slate
 */

if (!defined('ABSPATH')) exit;

define('SLATE_OPS_VERSION', '0.21.1');
define('SLATE_OPS_PATH', plugin_dir_path(__FILE__));
define('SLATE_OPS_URL', plugin_dir_url(__FILE__));
require_once SLATE_OPS_PATH . 'includes/class-slate-ops-assets.php';
require_once SLATE_OPS_PATH . 'includes/class-slate-ops-contract.php';

require_once SLATE_OPS_PATH . 'includes/class-slate-ops-install.php';
require_once SLATE_OPS_PATH . 'includes/class-slate-ops-roles.php';
require_once SLATE_OPS_PATH . 'includes/class-slate-ops-routes.php';
require_once SLATE_OPS_PATH . 'includes/class-slate-ops-statuses.php';
require_once SLATE_OPS_PATH . 'includes/class-slate-ops-utils.php';
require_once SLATE_OPS_PATH . 'includes/functions.php';

// Data helpers (must load after Utils)
require_once SLATE_OPS_PATH . 'includes/data/class-slate-ops-activitylog.php';
require_once SLATE_OPS_PATH . 'includes/data/class-slate-ops-timelogs.php';
require_once SLATE_OPS_PATH . 'includes/data/class-slate-ops-jobs.php';
require_once SLATE_OPS_PATH . 'includes/data/class-slate-ops-schedule.php';
require_once SLATE_OPS_PATH . 'includes/data/class-slate-ops-work-centers.php';

// Scheduler services (Phase 0)
require_once SLATE_OPS_PATH . 'includes/class-capacity-service.php';
require_once SLATE_OPS_PATH . 'includes/class-priority-service.php';
require_once SLATE_OPS_PATH . 'includes/class-buffer-service.php';

require_once SLATE_OPS_PATH . 'includes/class-slate-ops-rest.php';
require_once SLATE_OPS_PATH . 'includes/class-slate-ops-clickup.php';

if ( is_admin() ) {
    require_once SLATE_OPS_PATH . 'includes/admin/class-clickup-import-admin.php';
    add_action( 'admin_menu', [ 'Slate_Ops_ClickUp_Import_Admin', 'register_menu' ] );
}

register_activation_hook(__FILE__, ['Slate_Ops_Install', 'activate']);
register_deactivation_hook(__FILE__, ['Slate_Ops_Install', 'deactivate']);

add_action('init', ['Slate_Ops_Roles', 'register_roles_caps']);
add_action('init', ['Slate_Ops_Install', 'maybe_upgrade']);
add_action('init', ['Slate_Ops_Routes', 'register_routes']);
add_action('rest_api_init', ['Slate_Ops_REST', 'register_routes']);

add_action('wp_enqueue_scripts', function() {
  if (!Slate_Ops_Routes::is_ops_request()) return;

    wp_enqueue_style('material-symbols', 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200', [], null);

  $ver_shell = file_exists(SLATE_OPS_PATH . 'assets/css/ops-shell.css') ? filemtime(SLATE_OPS_PATH . 'assets/css/ops-shell.css') : SLATE_OPS_VERSION;
  $ver_app_css = file_exists(SLATE_OPS_PATH . 'assets/react/app.css') ? filemtime(SLATE_OPS_PATH . 'assets/react/app.css') : SLATE_OPS_VERSION;
  $ver_app_js  = file_exists(SLATE_OPS_PATH . 'assets/react/app.js') ? filemtime(SLATE_OPS_PATH . 'assets/react/app.js') : SLATE_OPS_VERSION;

  // ops-shell.css: structural shell tokens (sidebar, topbar, version badge).
  wp_enqueue_style('slate-ops-shell', SLATE_OPS_URL . 'assets/css/ops-shell.css', ['material-symbols'], $ver_shell);
  wp_enqueue_style('slate-ops-react', SLATE_OPS_URL . 'assets/react/app.css', ['slate-ops-shell'], $ver_app_css);
  wp_enqueue_script('slate-ops-react', SLATE_OPS_URL . 'assets/react/app.js', ['wp-element'], $ver_app_js, true);

  $current_user = wp_get_current_user();

  wp_localize_script('slate-ops-react', 'slateOpsSettings', [
    'api' => [
      'root' => esc_url_raw(rest_url('slate-ops/v1')),
      'nonce' => wp_create_nonce('wp_rest'),
    ],
    'user' => [
      'id' => get_current_user_id(),
      'name' => $current_user->display_name,
      'caps' => Slate_Ops_Utils::current_user_caps_summary(),
      'roles' => array_values((array) $current_user->roles),
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

// Hide the WP admin bar on all /ops/ pages — it breaks the full-screen layout.
add_filter('show_admin_bar', function($show) {
  if (Slate_Ops_Routes::is_ops_request()) return false;
  return $show;
});

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
