<?php
/**
 * Slate Ops — main shell template.
 *
 * Served for every /ops/* request via the template_include filter.
 * All page content is rendered by assets/react/app.js (React) into #ops-view.
 *
 * Layout is assembled from shared partials in includes/ui/:
 *   layout-shell.php  — <html><head><body> wrapper
 *   sidebar.php       — left navigation
 *   topbar.php        — header bar (page title + version badge)
 *   version-badge.php — badge rendered by topbar.php
 */
if (!defined('ABSPATH')) exit;

// Require authentication before rendering anything.
Slate_Ops_Routes::require_access_or_redirect();

// Build role context used by sidebar and body class.
$caps = Slate_Ops_Utils::current_user_caps_summary();

$role_class = $caps['admin']      ? 'ops-role-admin'
  : ($caps['supervisor']          ? 'ops-role-supervisor'
  : ($caps['cs']                  ? 'ops-role-cs'
  : ($caps['tech']                ? 'ops-role-tech' : '')));

$role_label = $caps['admin']      ? 'Admin'
  : ($caps['supervisor']          ? 'Supervisor'
  : ($caps['cs']                  ? 'Customer Service'
  : ($caps['tech']                ? 'Technician' : '')));

$user = wp_get_current_user();

// Route-specific body class — used for layout overrides (e.g. tech full-screen panel).
$_ops_path  = Slate_Ops_Routes::current_path(); // e.g. "tech", "cs", "schedule/…"
$_ops_route = strtok($_ops_path, '/');           // first segment only
$page_class = $_ops_route ? 'ops-page-' . sanitize_html_class($_ops_route) : '';

// Open layout shell (outputs <html> … <section class="ops-content"><div id="ops-view">)
$shell_part = 'open';
include SLATE_OPS_PATH . 'includes/ui/layout-shell.php';

// #ops-view is empty — React app (app.js) mounts and renders all content at runtime.

// Close layout shell (outputs </section></div></body></html>)
$shell_part = 'close';
include SLATE_OPS_PATH . 'includes/ui/layout-shell.php';
