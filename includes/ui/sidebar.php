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
    <?php echo Slate_Ops_Assets::logo_img(36); ?>
  </div>

  <nav class="ops-nav">
    <?php
    // Tech-only (no supervisor / CS / admin)
    if (current_user_can(Slate_Ops_Utils::CAP_TECH)
      && !current_user_can(Slate_Ops_Utils::CAP_ADMIN)
      && !current_user_can(Slate_Ops_Utils::CAP_SUPERVISOR)
      && !current_user_can(Slate_Ops_Utils::CAP_CS)) :
    ?>
      <?php ops_nav_link('/ops/tech',  '/tech',  'build',        'Tech'); ?>
      <?php ops_nav_link('/ops/jobs',  '/jobs',  'construction', 'Jobs'); ?>
    <?php endif; ?>

    <?php
    // CS (no admin / supervisor)
    if (current_user_can(Slate_Ops_Utils::CAP_CS)
      && !current_user_can(Slate_Ops_Utils::CAP_ADMIN)
      && !current_user_can(Slate_Ops_Utils::CAP_SUPERVISOR)) :
    ?>
      <?php ops_nav_link('/ops/cs',       '/cs',       'headset_mic',    'CS'); ?>
      <?php ops_nav_link('/ops/schedule', '/schedule', 'calendar_today', 'Schedule'); ?>
      <?php ops_nav_link('/ops/jobs',     '/jobs',     'construction',   'Jobs'); ?>
      <?php ops_nav_link('/ops/new',      '/new',      'add_circle',     'Create Job'); ?>
    <?php endif; ?>

    <?php
    // Supervisor (no admin)
    if (current_user_can(Slate_Ops_Utils::CAP_SUPERVISOR)
      && !current_user_can(Slate_Ops_Utils::CAP_ADMIN)) :
    ?>
      <?php ops_nav_link('/ops/supervisor', '/supervisor', 'engineering',    'Supervisor'); ?>
      <?php ops_nav_link('/ops/exec',       '/exec',       'dashboard',      'Dashboard'); ?>
      <?php ops_nav_link('/ops/jobs',       '/jobs',       'construction',   'Jobs'); ?>
      <?php ops_nav_link('/ops/qc',         '/qc',         'fact_check',     'QC'); ?>
      <?php ops_nav_link('/ops/schedule',   '/schedule',   'calendar_today', 'Schedule'); ?>
      <?php ops_nav_link('/ops/settings',   '/settings',   'settings',       'Settings'); ?>
    <?php endif; ?>

    <?php
    // Admin
    if (current_user_can(Slate_Ops_Utils::CAP_ADMIN)) :
    ?>
      <?php ops_nav_link('/ops/admin',      '/admin',      'shield_person',  'Admin'); ?>
      <?php ops_nav_link('/ops/exec',       '/exec',       'dashboard',      'Dashboard'); ?>
      <?php ops_nav_link('/ops/cs',         '/cs',         'headset_mic',    'CS'); ?>
      <?php ops_nav_link('/ops/supervisor', '/supervisor', 'engineering',    'Supervisor'); ?>
      <?php ops_nav_link('/ops/jobs',       '/jobs',       'construction',   'Jobs'); ?>
      <?php ops_nav_link('/ops/qc',         '/qc',         'fact_check',     'QC'); ?>
      <?php ops_nav_link('/ops/schedule',   '/schedule',   'calendar_today', 'Schedule'); ?>
    <?php endif; ?>

    <?php
    // Fallback: no recognized role
    if (!current_user_can(Slate_Ops_Utils::CAP_TECH)
      && !current_user_can(Slate_Ops_Utils::CAP_CS)
      && !current_user_can(Slate_Ops_Utils::CAP_SUPERVISOR)
      && !current_user_can(Slate_Ops_Utils::CAP_ADMIN)) :
    ?>
      <?php ops_nav_link('/ops/exec', '/exec', 'dashboard',    'Dashboard'); ?>
      <?php ops_nav_link('/ops/jobs', '/jobs', 'construction', 'Jobs'); ?>
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
