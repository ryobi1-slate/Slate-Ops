<?php
/**
 * Plugin Name: Slate Ops
 * Description: Internal Ops UI (/ops/) for Customer Service, Shop Supervisor, and Techs. Integrates with Slate Dealer Portal + ClickUp.
 * Version: 0.56.2
 * Author: Slate
 */

if (!defined('ABSPATH')) exit;

define('SLATE_OPS_VERSION', '0.56.2');
define('SLATE_OPS_PATH', plugin_dir_path(__FILE__));
define('SLATE_OPS_URL', plugin_dir_url(__FILE__));
require_once SLATE_OPS_PATH . 'includes/class-slate-ops-assets.php';

// Page-access matrix helper wrappers (requested public API names).
function slate_ops_get_default_role_page_access() {
  return Slate_Ops_Utils::get_default_role_page_access();
}
function slate_ops_get_role_page_access() {
  return Slate_Ops_Utils::get_role_page_access();
}
function slate_ops_current_user_can_access_ops_page($page_slug) {
  return Slate_Ops_Utils::current_user_can_access_ops_page($page_slug);
}
function slate_ops_get_allowed_pages_for_current_user() {
  return Slate_Ops_Utils::user_allowed_pages();
}
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

// CS / Supervisor Operations Dashboard data layer (Phase 1: stub data)
require_once SLATE_OPS_PATH . 'includes/class-slate-ops-cs.php';

// Executive Dashboard data layer (server-rendered, stub data for now)
require_once SLATE_OPS_PATH . 'includes/class-slate-ops-executive.php';

// Scheduler services (Phase 0)
require_once SLATE_OPS_PATH . 'includes/class-capacity-service.php';
require_once SLATE_OPS_PATH . 'includes/class-priority-service.php';
require_once SLATE_OPS_PATH . 'includes/class-buffer-service.php';

require_once SLATE_OPS_PATH . 'includes/class-slate-ops-rest.php';
require_once SLATE_OPS_PATH . 'includes/class-slate-ops-clickup.php';
require_once SLATE_OPS_PATH . 'includes/data/class-slate-ops-purchasing.php';
require_once SLATE_OPS_PATH . 'includes/integration/class-slate-ops-pa-events.php';
require_once SLATE_OPS_PATH . 'includes/class-slate-ops-purchasing-rest.php';

if ( is_admin() ) {
    require_once SLATE_OPS_PATH . 'includes/admin/class-clickup-import-admin.php';
    add_action( 'admin_menu', [ 'Slate_Ops_ClickUp_Import_Admin', 'register_menu' ] );
}

register_activation_hook(__FILE__, ['Slate_Ops_Install', 'activate']);
register_activation_hook(__FILE__, ['Slate_Ops_Roles', 'install']);
register_deactivation_hook(__FILE__, ['Slate_Ops_Install', 'deactivate']);

// Version-gated: runs full repair only when SLATE_OPS_VERSION changes.
add_action('init',       ['Slate_Ops_Roles', 'maybe_install']);
// Self-healing: always runs on admin_init so manual role edits are corrected.
add_action('admin_init', ['Slate_Ops_Roles', 'install']);
add_action('init', ['Slate_Ops_Install', 'maybe_upgrade']);
add_action('init', ['Slate_Ops_Routes', 'register_routes']);
add_action('rest_api_init', ['Slate_Ops_REST', 'register_routes']);
add_action('rest_api_init', ['Slate_Ops_Purchasing_REST', 'register_routes']);

add_action('wp_enqueue_scripts', function() {
  if (!Slate_Ops_Routes::is_ops_request()) return;

  wp_enqueue_style('material-symbols', 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200', [], null);

  $ver_shell = file_exists(SLATE_OPS_PATH . 'assets/css/ops-shell.css') ? filemtime(SLATE_OPS_PATH . 'assets/css/ops-shell.css') : SLATE_OPS_VERSION;

  // ops-shell.css: structural shell tokens (sidebar, topbar, version badge). Always loaded.
  wp_enqueue_style('slate-ops-shell', SLATE_OPS_URL . 'assets/css/ops-shell.css', ['material-symbols'], $ver_shell);

  // Narrow check: only the purchasing sub-tree gets the purchasing bundle.
  // Exact match 'purchasing' or sub-paths 'purchasing/…' — nothing else.
  $current_path = Slate_Ops_Routes::current_path();
  $is_purchasing   = ($current_path === 'purchasing' || strncmp($current_path, 'purchasing/', 11) === 0);
  $is_cs_dashboard = ($current_path === 'cs-dashboard' || strncmp($current_path, 'cs-dashboard/', 13) === 0);
  $is_executive    = ($current_path === 'exec' || strncmp($current_path, 'exec/', 5) === 0);

  if ($is_purchasing) {
    // Purchasing workspace — standalone vanilla JS; React app is not loaded here.
    $ver_pur_css = file_exists(SLATE_OPS_PATH . 'assets/css/purchasing.css') ? filemtime(SLATE_OPS_PATH . 'assets/css/purchasing.css') : SLATE_OPS_VERSION;
    $ver_pur_js  = file_exists(SLATE_OPS_PATH . 'assets/js/purchasing.js')   ? filemtime(SLATE_OPS_PATH . 'assets/js/purchasing.js')   : SLATE_OPS_VERSION;
    wp_enqueue_style('slate-ops-purchasing',  SLATE_OPS_URL . 'assets/css/purchasing.css', ['slate-ops-shell'], $ver_pur_css);
    wp_enqueue_script('slate-ops-purchasing', SLATE_OPS_URL . 'assets/js/purchasing.js',   [],                  $ver_pur_js,  true);
    wp_localize_script('slate-ops-purchasing', 'slateOpsPurchasing', [
      'api' => [
        'root'  => esc_url_raw(rest_url('slate-ops/v1')),
        'nonce' => wp_create_nonce('wp_rest'),
      ],
      'user' => [
        'is_admin' => current_user_can(Slate_Ops_Utils::CAP_ADMIN),
      ],
    ]);
  } elseif ($is_cs_dashboard) {
    // CS / Supervisor Operations Dashboard — server-rendered template +
    // standalone vanilla JS. React app is not loaded here.
    $ver_cs_css = file_exists(SLATE_OPS_PATH . 'assets/css/ops-cs-dashboard.css') ? filemtime(SLATE_OPS_PATH . 'assets/css/ops-cs-dashboard.css') : SLATE_OPS_VERSION;
    $ver_cs_js  = file_exists(SLATE_OPS_PATH . 'assets/js/ops-cs-dashboard.js')   ? filemtime(SLATE_OPS_PATH . 'assets/js/ops-cs-dashboard.js')   : SLATE_OPS_VERSION;
    wp_enqueue_style('slate-ops-cs-dashboard',  SLATE_OPS_URL . 'assets/css/ops-cs-dashboard.css', ['slate-ops-shell'], $ver_cs_css);
    wp_enqueue_script('slate-ops-cs-dashboard', SLATE_OPS_URL . 'assets/js/ops-cs-dashboard.js',   [],                  $ver_cs_js,  true);
    wp_localize_script('slate-ops-cs-dashboard', 'slateOpsCsDashboard', [
      'api' => [
        'root'  => esc_url_raw(rest_url('slate-ops/v1')),
        'nonce' => wp_create_nonce('wp_rest'),
      ],
      'user' => [
        'id'   => get_current_user_id(),
        'caps' => Slate_Ops_Utils::current_user_caps_summary(),
      ],
    ]);
  } elseif ($is_executive) {
    // Executive Dashboard V2 — server-rendered template + standalone
    // vanilla JS (Purchasing pattern). React app is not loaded here.
    $route_blocked_exec = !slate_ops_current_user_can_access_ops_page('executive');
    if ($route_blocked_exec) {
      // Render only the shell + access-denied panel; no page assets needed.
      return;
    }
    $ver_exec_css = file_exists(SLATE_OPS_PATH . 'assets/css/executive-dashboard.css') ? filemtime(SLATE_OPS_PATH . 'assets/css/executive-dashboard.css') : SLATE_OPS_VERSION;
    $ver_exec_js  = file_exists(SLATE_OPS_PATH . 'assets/js/executive-dashboard.js')   ? filemtime(SLATE_OPS_PATH . 'assets/js/executive-dashboard.js')   : SLATE_OPS_VERSION;
    wp_enqueue_style('slate-ops-executive',  SLATE_OPS_URL . 'assets/css/executive-dashboard.css', ['slate-ops-shell'], $ver_exec_css);
    wp_enqueue_script('slate-ops-executive', SLATE_OPS_URL . 'assets/js/executive-dashboard.js',   [],                  $ver_exec_js,  true);
  } else {
    $route_map = [
      '' => 'executive', 'exec' => 'executive', 'cs' => 'cs', 'tech' => 'tech',
      'schedule' => 'schedule', 'purchasing' => 'purchasing', 'admin' => 'admin',
      'settings' => 'settings', 'monitor' => 'monitor',
    ];
    $route_slug = $route_map[$current_path] ?? null;
    $route_blocked = $route_slug ? !slate_ops_current_user_can_access_ops_page($route_slug) : false;

    // All other /ops/* routes — React app.
    $ver_app_css  = file_exists(SLATE_OPS_PATH . 'assets/react/app.css')       ? filemtime(SLATE_OPS_PATH . 'assets/react/app.css')       : SLATE_OPS_VERSION;
    $ver_app_js   = file_exists(SLATE_OPS_PATH . 'assets/react/app.js')        ? filemtime(SLATE_OPS_PATH . 'assets/react/app.js')        : SLATE_OPS_VERSION;
    $ver_guard_js = file_exists(SLATE_OPS_PATH . 'assets/js/ops-access-guard.js') ? filemtime(SLATE_OPS_PATH . 'assets/js/ops-access-guard.js') : SLATE_OPS_VERSION;

    wp_enqueue_style('slate-ops-react',  SLATE_OPS_URL . 'assets/react/app.css', ['slate-ops-shell'], $ver_app_css);

    if (!$route_blocked && ($current_path === 'tech' || strncmp($current_path, 'tech/', 5) === 0)) {
      $ver_tech_css = file_exists(SLATE_OPS_PATH . 'assets/css/tech-mobile-fix.css') ? filemtime(SLATE_OPS_PATH . 'assets/css/tech-mobile-fix.css') : SLATE_OPS_VERSION;
      wp_enqueue_style('slate-ops-tech-mobile-fix', SLATE_OPS_URL . 'assets/css/tech-mobile-fix.css', ['slate-ops-react'], $ver_tech_css);
    }

    // Guard script loads first (before React) so it can intercept disallowed routes.
    wp_enqueue_script('slate-ops-guard', SLATE_OPS_URL . 'assets/js/ops-access-guard.js', [], $ver_guard_js, false);

    // Pause Work modal + success toast helper (footer, before React).
    $ver_pw_js = file_exists(SLATE_OPS_PATH . 'assets/js/ops-pause-work.js') ? filemtime(SLATE_OPS_PATH . 'assets/js/ops-pause-work.js') : SLATE_OPS_VERSION;
    wp_enqueue_script('slate-ops-pause-work', SLATE_OPS_URL . 'assets/js/ops-pause-work.js', [], $ver_pw_js, true);

    if (!$route_blocked) {
      wp_enqueue_script('slate-ops-react', SLATE_OPS_URL . 'assets/react/app.js', ['wp-element', 'slate-ops-guard', 'slate-ops-pause-work'], $ver_app_js, true);
    }

    $current_user = wp_get_current_user();

    wp_localize_script('slate-ops-guard', 'slateOpsSettings', [
      'api' => [
        'root'  => esc_url_raw(rest_url('slate-ops/v1')),
        'nonce' => wp_create_nonce('wp_rest'),
      ],
      'user' => [
        'id'            => get_current_user_id(),
        'name'          => $current_user->display_name,
        'caps'          => Slate_Ops_Utils::current_user_caps_summary(),
        'roles'         => array_values((array) $current_user->roles),
        'allowed_pages' => Slate_Ops_Utils::user_allowed_pages(),
      ],
      'colors' => [
        'sage'    => '#404f4b',
        'sand'    => '#e1dfc8',
        'arches'  => '#d86b19',
        'redwood' => '#0f342a',
        'black'   => '#000000',
        'white'   => '#ffffff',
      ],
      'logos' => [
        'white' => esc_url_raw(SLATE_OPS_URL . 'assets/logos/Slate-Logo-White-CMYK.png'),
      ],
      'dealers' => array_values(Slate_Ops_Utils::dealer_list()),
      'page_access' => [
        'roles' => slate_ops_get_role_page_access(),
        'defaults' => slate_ops_get_default_role_page_access(),
      ],
    ]);

    if (!$route_blocked) {
      $ver_access_admin = file_exists(SLATE_OPS_PATH . 'assets/js/ops-page-access-admin.js') ? filemtime(SLATE_OPS_PATH . 'assets/js/ops-page-access-admin.js') : SLATE_OPS_VERSION;
      wp_enqueue_script('slate-ops-page-access-admin', SLATE_OPS_URL . 'assets/js/ops-page-access-admin.js', ['slate-ops-react'], $ver_access_admin, true);
    }
  }
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
 * Phase 0 login routing.
 *
 * Sends Slate Ops users to their workspace landing page after login. Dealer
 * and other non-ops users fall through to whatever WordPress would have
 * returned (typically /dashboard/ from the dealer portal).
 *
 * Admins explicitly requesting wp-admin are allowed through so they can
 * reach the WP admin when they intend to.
 */
add_filter('login_redirect', function($redirect_to, $requested_redirect_to, $user) {
  if (is_wp_error($user) || !($user instanceof WP_User)) {
    return $redirect_to;
  }

  $is_admin = user_can($user, Slate_Ops_Utils::CAP_ADMIN);

  $requested = is_string($requested_redirect_to) ? $requested_redirect_to : '';
  if ($requested !== '' && stripos($requested, 'wp-admin') !== false && $is_admin) {
    return $redirect_to;
  }

  $is_supervisor = user_can($user, Slate_Ops_Utils::CAP_SUPERVISOR);
  $is_cs         = user_can($user, Slate_Ops_Utils::CAP_CS)
                || user_can($user, Slate_Ops_Utils::CAP_CS_LEGACY);
  $is_tech       = user_can($user, Slate_Ops_Utils::CAP_TECH);
  $is_viewer     = user_can($user, Slate_Ops_Utils::CAP_VIEWER)
                || user_can($user, Slate_Ops_Utils::CAP_VIEW_MONITOR);

  if ($is_admin || $is_supervisor || $is_cs) {
    return esc_url_raw(home_url('/ops/cs-dashboard'));
  }
  if ($is_tech) {
    return esc_url_raw(home_url('/ops/tech'));
  }
  if ($is_viewer) {
    return esc_url_raw(home_url('/slate-ops-monitor/'));
  }

  return $redirect_to;
}, 10, 3);

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
