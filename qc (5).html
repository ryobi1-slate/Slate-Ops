<?php
if (!defined('ABSPATH')) exit;
Slate_Ops_Routes::require_access_or_redirect();

$caps      = Slate_Ops_Utils::current_user_caps_summary();
$role_class = $caps['admin'] ? 'ops-role-admin'
  : ($caps['supervisor'] ? 'ops-role-supervisor'
  : ($caps['cs']         ? 'ops-role-cs'
  : ($caps['tech']       ? 'ops-role-tech' : '')));

$role_label = $caps['admin'] ? 'Admin'
  : ($caps['supervisor'] ? 'Supervisor'
  : ($caps['cs']         ? 'Customer Service'
  : ($caps['tech']       ? 'Technician' : '')));

$user = wp_get_current_user();

function ops_nav_link( $href, $route, $icon, $label ) {
  echo '<a href="' . esc_url( $href ) . '" data-route="' . esc_attr( $route ) . '" class="ops-nav-link">'
    . '<span class="material-symbols-outlined">' . esc_html( $icon ) . '</span>'
    . '<span class="ops-nav-label">' . esc_html( $label ) . '</span>'
    . '</a>';
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Slate Ops</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto+Flex:opsz,wght@8..144,300..900&display=swap" rel="stylesheet">
  <?php wp_head(); ?>
</head>
<body class="slate-ops <?php echo esc_attr( $role_class ); ?>">
<div id="slate-ops-app" class="ops-shell">

  <aside class="ops-sidebar">
    <div class="ops-brand">
      <?php echo Slate_Ops_Assets::logo_img(36); ?>
    </div>

    <nav class="ops-nav">
      <?php if ( current_user_can( Slate_Ops_Utils::CAP_TECH ) && ! current_user_can( Slate_Ops_Utils::CAP_ADMIN ) && ! current_user_can( Slate_Ops_Utils::CAP_SUPERVISOR ) && ! current_user_can( Slate_Ops_Utils::CAP_CS ) ) : ?>
        <?php ops_nav_link( '/ops/tech',  '/tech',  'build',        'Tech' ); ?>
        <?php ops_nav_link( '/ops/jobs',  '/jobs',  'construction', 'Jobs' ); ?>
      <?php endif; ?>

      <?php if ( current_user_can( Slate_Ops_Utils::CAP_CS ) && ! current_user_can( Slate_Ops_Utils::CAP_ADMIN ) && ! current_user_can( Slate_Ops_Utils::CAP_SUPERVISOR ) ) : ?>
        <?php ops_nav_link( '/ops/cs',       '/cs',       'headset_mic',    'CS' ); ?>
        <?php ops_nav_link( '/ops/schedule', '/schedule', 'calendar_today', 'Schedule' ); ?>
        <?php ops_nav_link( '/ops/jobs',     '/jobs',     'construction',   'Jobs' ); ?>
        <?php ops_nav_link( '/ops/new',      '/new',      'add_circle',     'Create Job' ); ?>
      <?php endif; ?>

      <?php if ( current_user_can( Slate_Ops_Utils::CAP_SUPERVISOR ) && ! current_user_can( Slate_Ops_Utils::CAP_ADMIN ) ) : ?>
        <?php ops_nav_link( '/ops/supervisor', '/supervisor', 'engineering',    'Supervisor' ); ?>
        <?php ops_nav_link( '/ops/exec',       '/exec',       'dashboard',      'Dashboard' ); ?>
        <?php ops_nav_link( '/ops/jobs',       '/jobs',       'construction',   'Jobs' ); ?>
        <?php ops_nav_link( '/ops/qc',         '/qc',         'fact_check',     'QC' ); ?>
        <?php ops_nav_link( '/ops/schedule',   '/schedule',   'calendar_today', 'Schedule' ); ?>
        <?php ops_nav_link( '/ops/settings',   '/settings',   'settings',       'Settings' ); ?>
      <?php endif; ?>

      <?php if ( current_user_can( Slate_Ops_Utils::CAP_ADMIN ) ) : ?>
        <?php ops_nav_link( '/ops/admin',      '/admin',      'shield_person',  'Admin' ); ?>
        <?php ops_nav_link( '/ops/exec',       '/exec',       'dashboard',      'Dashboard' ); ?>
        <?php ops_nav_link( '/ops/cs',         '/cs',         'headset_mic',    'CS' ); ?>
        <?php ops_nav_link( '/ops/supervisor', '/supervisor', 'engineering',    'Supervisor' ); ?>
        <?php ops_nav_link( '/ops/jobs',       '/jobs',       'construction',   'Jobs' ); ?>
        <?php ops_nav_link( '/ops/qc',         '/qc',         'fact_check',     'QC' ); ?>
        <?php ops_nav_link( '/ops/schedule',   '/schedule',   'calendar_today', 'Schedule' ); ?>
      <?php endif; ?>

      <?php if ( ! current_user_can( Slate_Ops_Utils::CAP_TECH ) && ! current_user_can( Slate_Ops_Utils::CAP_CS ) && ! current_user_can( Slate_Ops_Utils::CAP_SUPERVISOR ) && ! current_user_can( Slate_Ops_Utils::CAP_ADMIN ) ) : ?>
        <?php ops_nav_link( '/ops/exec', '/exec', 'dashboard',    'Dashboard' ); ?>
        <?php ops_nav_link( '/ops/jobs', '/jobs', 'construction', 'Jobs' ); ?>
      <?php endif; ?>
    </nav>

    <div class="ops-sidebar-footer">
      <div class="ops-sidebar-user">
        <div class="ops-sidebar-user-name"><?php echo esc_html( $user->display_name ); ?></div>
        <?php if ( $role_label ) : ?>
          <div class="ops-sidebar-user-role"><?php echo esc_html( $role_label ); ?></div>
        <?php endif; ?>
      </div>
      <a class="ops-sidebar-logout" href="<?php echo esc_url( wp_logout_url( home_url('/') ) ); ?>" title="Log out">
        <span class="material-symbols-outlined">logout</span>
      </a>
    </div>
  </aside>

  <div class="ops-body">
    <header class="ops-header">
      <span class="ops-page-title" id="ops-page-title"></span>
    </header>
    <section class="ops-content">
      <div id="ops-view"></div>
    </section>
  </div>

</div>
<?php wp_footer(); ?>
</body>
</html>
