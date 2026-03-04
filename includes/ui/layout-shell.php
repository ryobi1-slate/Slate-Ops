<?php
/**
 * Shared layout shell wrapper.
 *
 * This file is NOT meant to be included directly. It documents the open/close
 * pattern used by templates/ops-app.php and any future per-page templates.
 *
 * Usage pattern in a page template:
 *
 *   // --- OPEN shell ---
 *   $shell_part = 'open';
 *   include SLATE_OPS_PATH . 'includes/ui/layout-shell.php';
 *
 *   // --- page-specific content here ---
 *
 *   // --- CLOSE shell ---
 *   $shell_part = 'close';
 *   include SLATE_OPS_PATH . 'includes/ui/layout-shell.php';
 *
 * Expected context variables (set before the 'open' include):
 *   $caps        array    — current_user_caps_summary()
 *   $role_class  string   — CSS class derived from role
 *   $role_label  string   — human-readable role
 *   $user        WP_User  — current user object
 */
if (!defined('ABSPATH')) exit;

$shell_part = $shell_part ?? 'open';

if ($shell_part === 'open') : ?>
<!doctype html>
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
<body class="slate-ops <?php echo esc_attr($role_class); ?>">
<div id="slate-ops-app" class="ops-shell">
  <?php include SLATE_OPS_PATH . 'includes/ui/sidebar.php'; ?>
  <div class="ops-body">
    <?php include SLATE_OPS_PATH . 'includes/ui/topbar.php'; ?>
    <section class="ops-content">
      <div id="ops-view"></div>
<?php elseif ($shell_part === 'close') : ?>
    </section>
  </div>
</div>
<?php wp_footer(); ?>
</body>
</html>
<?php endif;
