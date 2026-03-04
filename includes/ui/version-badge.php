<?php
/**
 * Renders the Slate Ops version badge.
 *
 * Reads version via slate_ops_get_version() which prefers VERSION.txt over the
 * plugin header constant, so a file-based deploy can update it without editing PHP.
 *
 * Usage: include this partial inside any ops shell template; the badge will be
 * output inline at the current position in the document.
 */
if (!defined('ABSPATH')) exit;
?>
<div class="ops-version-badge" aria-label="Slate Ops version">
  <strong>Slate Ops</strong> v<?php echo esc_html(slate_ops_get_version()); ?>
</div>
