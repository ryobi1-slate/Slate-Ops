<?php
if (!defined('ABSPATH')) exit;
Slate_Ops_Routes::require_access_or_redirect();
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Slate Ops</title>
  <?php wp_head(); ?>
</head>
<body class="slate-ops">
  <div id="slate-ops-app">
    <header class="ops-header">
      <div class="ops-brand">
        <div class="ops-mark"></div>
        <div class="ops-title">SLATE OPS</div>
      </div>
      <div class="ops-user">
        <span class="ops-user-name"><?php echo esc_html(wp_get_current_user()->display_name); ?></span>
        <a class="ops-link" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Logout</a>
      </div>
    </header>

    <main class="ops-main">
      <div class="ops-nav">
        <a href="/ops/" data-route="/" class="ops-nav-link">Dashboard</a>
        <a href="/ops/jobs" data-route="/jobs" class="ops-nav-link">Jobs</a>
        <?php if (current_user_can(Slate_Ops_Utils::CAP_CS) || current_user_can(Slate_Ops_Utils::CAP_ADMIN)) : ?>
          <a href="/ops/new" data-route="/new" class="ops-nav-link">Create Job</a>
        <?php endif; ?>
        <?php if (current_user_can(Slate_Ops_Utils::CAP_SUPERVISOR) || current_user_can(Slate_Ops_Utils::CAP_ADMIN)) : ?>
          <a href="/ops/supervisor" data-route="/supervisor" class="ops-nav-link">Supervisor</a>
          <a href="/ops/settings" data-route="/settings" class="ops-nav-link">Settings</a>
        <?php endif; ?>
      </div>

      <section class="ops-content">
        <div id="ops-view"></div>
      </section>
    </main>

    <footer class="ops-footer">
      <span>Slate Ops v<?php echo esc_html(SLATE_OPS_VERSION); ?></span>
    </footer>
  </div>
  <?php wp_footer(); ?>
</body>
</html>
