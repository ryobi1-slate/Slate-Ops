<?php
/**
 * Shared topbar / header partial.
 *
 * Three-zone layout (mirrors Dealer Portal shell):
 *   Left   — Slate logo
 *   Center — page title placeholder (populated by JS via setPageTitle())
 *   Right  — shop/status + signed-in user
 *
 */
if (!defined('ABSPATH')) exit;

$topbar_user = isset($user) && $user instanceof WP_User ? $user : wp_get_current_user();
$topbar_name = $topbar_user && !empty($topbar_user->display_name) ? $topbar_user->display_name : 'User';
$topbar_role = isset($role_label) && $role_label ? $role_label : '';
$name_parts = preg_split('/\s+/', trim($topbar_name));
$initials = '';
if (is_array($name_parts) && count($name_parts) > 0) {
  $initials .= strtoupper(substr($name_parts[0], 0, 1));
  if (count($name_parts) > 1) {
    $initials .= strtoupper(substr($name_parts[count($name_parts) - 1], 0, 1));
  }
}
$initials = $initials ?: 'U';
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
    <div class="ops-topbar-user" title="<?php echo esc_attr($topbar_name . ($topbar_role ? ' - ' . $topbar_role : '')); ?>">
      <span class="ops-topbar-user__avatar"><?php echo esc_html($initials); ?></span>
      <span class="ops-topbar-user__name">
        <?php echo esc_html($topbar_name); ?>
        <?php if ($topbar_role) : ?>
          <span><?php echo esc_html($topbar_role); ?></span>
        <?php endif; ?>
      </span>
    </div>
    <a class="ops-topbar-logout" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" title="Log out" aria-label="Log out">
      <span class="material-symbols-outlined">logout</span>
    </a>
  </div>

</header>
