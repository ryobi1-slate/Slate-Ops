<?php
/**
 * Shared topbar / header partial.
 *
 * Three-zone layout (mirrors Dealer Portal shell):
 *   Left   — Slate logo
 *   Center — page title placeholder (populated by JS via setPageTitle())
 *   Right  — version badge + user avatar dropdown
 *
 * Context variables available from ops-app.php: $user, $role_label
 */
if (!defined('ABSPATH')) exit;

$_tb_user = $user ?? wp_get_current_user();
$_fn      = trim($_tb_user->first_name);
$_ln      = trim($_tb_user->last_name);
$_initials = ($_fn && $_ln)
  ? strtoupper(mb_substr($_fn, 0, 1) . mb_substr($_ln, 0, 1))
  : strtoupper(mb_substr($_tb_user->display_name, 0, 2));
?>
<header class="ops-header">

  <div class="ops-header__left">
    <a class="ops-topbar__logo-link" href="/ops/" aria-label="Slate Ops — go to home">
      <img class="ops-topbar__logo"
           src="<?php echo esc_url(SLATE_OPS_URL . 'assets/logos/slate-logo-sage.png'); ?>"
           alt="Slate"
           width="auto"
           height="24"
           style="object-fit:contain;height:24px;width:auto;">
    </a>
  </div>

  <div class="ops-header__center">
    <span class="ops-page-title" id="ops-page-title"></span>
  </div>

  <div class="ops-header__right">
    <?php include __DIR__ . '/version-badge.php'; ?>
    <details class="ops-topbar__account">
      <summary class="ops-topbar__avatar" aria-label="Account menu"><?php echo esc_html($_initials); ?></summary>
      <div class="ops-topbar__dropdown">
        <div class="ops-topbar__dropdown-name"><?php echo esc_html($_tb_user->display_name); ?></div>
        <?php if (!empty($role_label)) : ?>
          <div class="ops-topbar__dropdown-role"><?php echo esc_html($role_label); ?></div>
        <?php endif; ?>
        <hr class="ops-topbar__dropdown-divider">
        <a class="ops-topbar__dropdown-link"
           href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Log out</a>
      </div>
    </details>
  </div>

</header>
