<?php
if (!defined('ABSPATH')) exit;

/**
 * Phase 0 WP Admin menu and page shells.
 *
 * All dynamic content is rendered by slate-ops-admin.js which calls the
 * /wp-json/slate-ops/v1/scheduler/* REST endpoints.
 */
class Slate_Ops_Admin {

  public static function init() {
    add_action('admin_menu',            [__CLASS__, 'register_menu']);
    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
  }

  // ── Menu ──────────────────────────────────────────────────────────────

  public static function register_menu() {
    // Top-level "Slate Ops" menu — accessible to CS staff and up.
    add_menu_page(
      'Slate Ops',
      'Slate Ops',
      'slate_ops_customer_service',
      'slate-ops',
      [__CLASS__, 'render_cs_page'],   // default page = CS
      'dashicons-calendar-alt',
      56
    );

    // CS sub-page (mirrors the top-level default).
    add_submenu_page(
      'slate-ops',
      'Customer Service — Slate Ops',
      'CS',
      'slate_ops_customer_service',
      'slate-ops-cs',
      [__CLASS__, 'render_cs_page']
    );

    // Schedule sub-page.
    add_submenu_page(
      'slate-ops',
      'Schedule — Slate Ops',
      'Schedule',
      'slate_ops_customer_service',
      'slate-ops-schedule',
      [__CLASS__, 'render_schedule_page']
    );

    // Settings — admin only.
    add_submenu_page(
      'slate-ops',
      'Settings — Slate Ops',
      'Settings',
      'manage_options',
      'slate-ops-settings',
      [__CLASS__, 'render_settings_page']
    );

    // Remove the duplicate auto-generated first sub-item ("Slate Ops").
    remove_submenu_page('slate-ops', 'slate-ops');
  }

  // ── Page shells ───────────────────────────────────────────────────────

  public static function render_cs_page() {
    if (!current_user_can('slate_ops_customer_service') && !current_user_can('manage_options')) {
      wp_die('You do not have permission to access this page.');
    }
    echo '<div class="wrap"><div id="slate-cs-app"><p class="slate-loading">Loading…</p></div></div>';
  }

  public static function render_schedule_page() {
    if (!current_user_can('slate_ops_customer_service') && !current_user_can('manage_options')) {
      wp_die('You do not have permission to access this page.');
    }
    echo '<div class="wrap"><div id="slate-schedule-app"><p class="slate-loading">Loading…</p></div></div>';
  }

  public static function render_settings_page() {
    if (!current_user_can('manage_options')) {
      wp_die('You do not have permission to access this page.');
    }
    echo '<div class="wrap"><div id="slate-settings-app"><p class="slate-loading">Loading…</p></div></div>';
  }

  // ── Assets ────────────────────────────────────────────────────────────

  public static function enqueue_assets($hook) {
    $our_pages = [
      'toplevel_page_slate-ops',
      'slate-ops_page_slate-ops-cs',
      'slate-ops_page_slate-ops-schedule',
      'slate-ops_page_slate-ops-settings',
    ];

    if (!in_array($hook, $our_pages, true)) {
      return;
    }

    wp_enqueue_style(
      'slate-ops-admin',
      SLATE_OPS_URL . 'assets/css/slate-ops-admin.css',
      [],
      SLATE_OPS_VERSION
    );

    wp_enqueue_script(
      'slate-ops-admin',
      SLATE_OPS_URL . 'assets/js/slate-ops-admin.js',
      [],
      SLATE_OPS_VERSION,
      true
    );

    // Determine current page slug for the JS router.
    $page_map = [
      'toplevel_page_slate-ops'          => 'slate-ops-cs',
      'slate-ops_page_slate-ops-cs'      => 'slate-ops-cs',
      'slate-ops_page_slate-ops-schedule' => 'slate-ops-schedule',
      'slate-ops_page_slate-ops-settings' => 'slate-ops-settings',
    ];

    wp_localize_script('slate-ops-admin', 'slateAdminSettings', [
      'restRoot' => esc_url_raw(rest_url('slate-ops/v1')),
      'nonce'    => wp_create_nonce('wp_rest'),
      'page'     => $page_map[$hook] ?? 'slate-ops-cs',
      'version'  => SLATE_OPS_VERSION,
      'user'     => [
        'id'   => get_current_user_id(),
        'name' => wp_get_current_user()->display_name,
      ],
    ]);
  }
}
