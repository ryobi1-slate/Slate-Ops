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
  <div class="ops-brand">
    <img class="ops-logo" src="<?php echo esc_url(SLATE_OPS_URL . 'assets/logos/slate-logo-white.svg'); ?>" alt="Slate" width="116" height="26">
  </div>

  <nav class="ops-nav">
    <?php
    $is_admin = current_user_can(Slate_Ops_Utils::CAP_ADMIN);
    $is_exec  = current_user_can(Slate_Ops_Utils::CAP_SUPERVISOR);
    $is_cs    = current_user_can(Slate_Ops_Utils::CAP_CS);
    $is_tech  = current_user_can(Slate_Ops_Utils::CAP_TECH);
    ?>

    <?php if ($is_admin || $is_exec) : ?>
      <?php ops_nav_link('/ops/exec',     '/exec',     'dashboard',      'Executive'); ?>
      <?php ops_nav_link('/ops/cs',       '/cs',       'person',         'CS'); ?>
      <?php ops_nav_link('/ops/tech',     '/tech',     'build',          'Tech'); ?>
      <?php ops_nav_link('/ops/schedule', '/schedule', 'calendar_month', 'Schedule'); ?>
      <?php ops_nav_link('/ops/admin',    '/admin',    'shield',         'Admin'); ?>
      <?php ops_nav_link('/ops/settings', '/settings', 'settings',       'Settings'); ?>
    <?php elseif ($is_cs) : ?>
      <?php ops_nav_link('/ops/cs',       '/cs',       'person',         'CS'); ?>
      <?php ops_nav_link('/ops/schedule', '/schedule', 'calendar_month', 'Schedule'); ?>
    <?php elseif ($is_tech) : ?>
      <?php ops_nav_link('/ops/tech',     '/tech',     'build',          'Tech'); ?>
      <?php ops_nav_link('/ops/schedule', '/schedule', 'calendar_month', 'Schedule'); ?>
    <?php else : ?>
      <?php ops_nav_link('/ops/exec', '/exec', 'dashboard', 'Executive'); ?>
    <?php endif; ?>
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
