<?php
/**
 * Shared topbar / header partial.
 *
 * Three-zone layout (mirrors Dealer Portal shell):
 *   Left   — Slate logo
 *   Center — page title placeholder (populated by JS via setPageTitle())
 *   Right  — version badge
 *
 */
if (!defined('ABSPATH')) exit;
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
  </div>

</header>
