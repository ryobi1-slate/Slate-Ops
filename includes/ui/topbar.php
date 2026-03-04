<?php
/**
 * Shared topbar / header partial.
 *
 * Renders the horizontal header bar containing the page title placeholder
 * (populated by JS via setPageTitle()) and the version badge.
 *
 * No variables required; version is read from slate_ops_get_version().
 */
if (!defined('ABSPATH')) exit;
?>
<header class="ops-header">
  <span class="ops-page-title" id="ops-page-title"></span>
  <?php include __DIR__ . '/version-badge.php'; ?>
</header>
