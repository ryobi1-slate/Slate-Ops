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
    echo '<a href="' . esc_url($href) . '" data-route="' . esc_attr($route) . '" class="ops-nav-link">'
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
    <div class="ops-sidebar-nav-section">
      <span class="ops-sidebar-nav-label">Navigate</span>
    </div>
  </div>

  <nav class="ops-nav">
    <?php
    $allowed = Slate_Ops_Utils::user_allowed_pages();

    if (in_array('executive', $allowed, true)) :
      ops_nav_link('/ops/exec',         '/exec',          'dashboard',      'Executive');
    endif;
    if (in_array('cs-dashboard', $allowed, true)) :
      ops_nav_link('/ops/cs-dashboard', '/cs-dashboard',  'person',         'CS');
    endif;
    // Legacy /ops/cs route — only rendered for users explicitly granted the
    // `cs` slug in the access matrix (default-hidden as of v0.52.0).
    if (in_array('cs', $allowed, true)) :
      ops_nav_link('/ops/cs',           '/cs',            'support_agent',  'CS (legacy)');
    endif;
    if (in_array('tech', $allowed, true)) :
      ops_nav_link('/ops/tech',     '/tech',     'build',          'Tech');
    endif;
    if (in_array('schedule', $allowed, true)) :
      ops_nav_link('/ops/schedule', '/schedule', 'calendar_month', 'Schedule');
    endif;
    if (in_array('purchasing', $allowed, true)) :
      ops_nav_link('/ops/purchasing', '/purchasing', 'shopping_cart', 'Purchasing');
    endif;
    if (in_array('resource-hub', $allowed, true)) :
      ops_nav_link('/ops/resource-hub', '/resource-hub', 'folder_open', 'Resource hub');
    endif;
    if (in_array('admin', $allowed, true)) :
      ops_nav_link('/ops/admin',    '/admin',    'shield',         'Admin');
    endif;
    if (in_array('settings', $allowed, true)) :
      ops_nav_link('/ops/settings', '/settings', 'settings',       'Settings');
    endif;
    if (in_array('monitor', $allowed, true)) :
      ops_nav_link(home_url('/slate-ops-monitor/'), '/monitor', 'monitor', 'Monitor');
    endif;
    ?>
  </nav>

  <div class="ops-sidebar-footer">
    <div class="ops-sidebar-user">
      <div class="ops-sidebar-user-name"><?php echo esc_html($user->display_name); ?></div>
      <?php if ($role_label) : ?>
        <div class="ops-sidebar-user-role"><?php echo esc_html($role_label); ?></div>
      <?php endif; ?>
    </div>
    <a class="ops-sidebar-logout" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" title="Log out">
      <span class="material-symbols-outlined">logout</span>
    </a>
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
