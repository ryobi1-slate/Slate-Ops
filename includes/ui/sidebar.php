<?php
/**
 * Shared sidebar partial.
 *
 * Expected context (variables set by the including template):
 *   $user        WP_User  — current user
 *   $role_label  string   — human-readable role label
 *   $caps        array    — output of Slate_Ops_Utils::current_user_caps_summary()
 *
 * Helper used below:
 *   ops_nav_link($href, $route, $icon, $label)  — defined in ops-app.php (or re-declared here if absent)
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('ops_nav_link')) {
  function ops_nav_link($href, $route, $icon, $label) {
    $current_path = class_exists('Slate_Ops_Routes') ? Slate_Ops_Routes::current_path() : '';
    $current_route = $current_path === '' ? '/' : '/' . strtok($current_path, '/');
    $current_full_route = $current_path === '' ? '/' : '/' . trim($current_path, '/');
    $is_active = ($current_route === $route || $current_full_route === $route);
    $classes = 'ops-nav-link' . ($is_active ? ' active' : '');
    echo '<a href="' . esc_url($href) . '" data-route="' . esc_attr($route) . '" class="' . esc_attr($classes) . '">'
      . '<span class="material-symbols-outlined ops-nav-icon">' . esc_html($icon) . '</span>'
      . '<span class="ops-nav-label">' . esc_html($label) . '</span>'
      . '</a>';
  }
}
?>
<aside class="ops-sidebar">

  <div class="ops-sidebar-header">
    <div class="ops-sidebar-collapse-row">
      <button class="ops-sidebar-collapse-btn" id="ops-sidebar-toggle" type="button" aria-label="Collapse sidebar">
        <span class="material-symbols-outlined">chevron_left</span>
      </button>
    </div>
  </div>

  <nav class="ops-nav" aria-label="Slate Ops navigation">
    <?php
    $allowed = Slate_Ops_Utils::user_allowed_pages();
    $show_ops_home = !($caps['tech'] && !$caps['admin'] && !$caps['supervisor'] && !$caps['cs']);
    $has_admin_nav = in_array('admin', $allowed, true) || in_array('settings', $allowed, true);
    ?>

    <div class="ops-nav-section">
      <div class="ops-nav-section-label">Workspaces</div>
      <?php

      if ($show_ops_home) :
        ops_nav_link('/ops/', '/', 'home', 'Ops Home');
      endif;
      if (in_array('cs-dashboard', $allowed, true)) :
        ops_nav_link('/ops/cs-dashboard', '/cs-dashboard', 'support_agent', 'CS Workspace');
      endif;
      if (in_array('tech', $allowed, true)) :
        ops_nav_link('/ops/tech', '/tech', 'build', 'Tech Screen');
      endif;
      if (in_array('executive', $allowed, true)) :
        ops_nav_link('/ops/exec', '/exec', 'monitoring', 'Executive');
      endif;
      if (in_array('schedule', $allowed, true)) :
        ops_nav_link('/ops/schedule', '/schedule', 'calendar_month', 'Schedule');
      endif;
      if (in_array('purchasing', $allowed, true)) :
        ops_nav_link('/ops/purchasing', '/purchasing', 'shopping_cart', 'Purchasing');
      endif;
      if (in_array('monitor', $allowed, true)) :
        ops_nav_link(home_url('/slate-ops-monitor/'), '/monitor', 'desktop_windows', 'Monitor');
      endif;
      if (in_array('resource-hub', $allowed, true)) :
        ops_nav_link('/ops/resource-hub', '/resource-hub', 'menu_book', 'Resource Hub');
      endif;
      ?>
    </div>

    <?php if ($has_admin_nav) : ?>
      <div class="ops-nav-divider" aria-hidden="true"></div>
      <div class="ops-nav-section">
        <div class="ops-nav-section-label">Administration</div>
        <?php
        if (in_array('settings', $allowed, true)) :
          ops_nav_link('/ops/settings', '/settings', 'tune', 'Settings / Admin');
        endif;
        if (in_array('admin', $allowed, true)) :
          ops_nav_link('/ops/admin/users', '/admin/users', 'manage_accounts', 'Users & Roles');
          ops_nav_link('/ops/admin/audit', '/admin/audit', 'history', 'Audit Log');
        endif;
        ?>
      </div>
    <?php endif; ?>
  </nav>

  <div class="ops-sidebar-footer">
    <?php include __DIR__ . '/version-badge.php'; ?>
  </div>

</aside>
<script>
(function () {
  var btn = document.getElementById('ops-sidebar-toggle');
  if (!btn) return;

  var toggle = function () {
    var isCollapsed = document.documentElement.classList.toggle('ops-sidebar-collapsed');
    btn.setAttribute('aria-label', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
    try { localStorage.setItem('slate_ops_sidebar_collapsed', isCollapsed ? '1' : '0'); } catch (e) {}
  };

  btn.addEventListener('click', toggle);

  // Sync initial aria-label if sidebar was restored collapsed by FOUC script
  if (document.documentElement.classList.contains('ops-sidebar-collapsed')) {
    btn.setAttribute('aria-label', 'Expand sidebar');
  }
})();
</script>
