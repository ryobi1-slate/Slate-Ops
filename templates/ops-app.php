<?php
/**
 * Slate Ops — main shell template.
 *
 * Served for every /ops/* request via the template_include filter.
 * Hybrid shell: standalone PHP templates render selected routes, and the
 * legacy React app mounts into #ops-view for the remaining routes.
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
  : ($caps['tech']                ? 'ops-role-tech'
  : ($caps['viewer']              ? 'ops-role-viewer' : ''))));

$role_label = $caps['admin']      ? 'Admin'
  : ($caps['supervisor']          ? 'Supervisor'
  : ($caps['cs']                  ? 'Customer Service'
  : ($caps['tech']                ? 'Technician'
  : ($caps['viewer']              ? 'Viewer' : ''))));

$user = wp_get_current_user();

// Route-specific body class — used for layout overrides (e.g. tech full-screen panel).
$_ops_path  = Slate_Ops_Routes::current_path(); // e.g. "tech", "cs", "schedule/…"
$_ops_route = strtok($_ops_path, '/');           // first segment only
$page_class = $_ops_route ? 'ops-page-' . sanitize_html_class($_ops_route) : '';
$route_map = [
  ''             => 'executive',
  'exec'         => 'executive',
  'cs'           => 'cs',
  'cs-dashboard' => 'cs-dashboard',
  'tech'         => 'tech',
  'schedule'     => 'schedule',
  'purchasing'   => 'purchasing',
  'resource-hub' => 'resource-hub',
  'admin'        => 'admin',
  'settings'     => 'settings',
  'monitor'      => 'monitor',
];
$page_slug = $route_map[$_ops_route] ?? null;
$is_blocked = $page_slug && !slate_ops_current_user_can_access_ops_page($page_slug);

// Embed mode: ?embed=1 strips topbar + sidebar so legacy React routes can
// render inside an iframe when needed. Presentation flag only — access checks
// are unaffected.
$is_embed = isset($_GET['embed']) && (string) $_GET['embed'] === '1';

// `exec` is the canonical Executive route; only that path renders the
// server-side Executive Dashboard V2 template. The empty `/ops/` path
// keeps its legacy React-app behavior.
$is_executive_page = ($_ops_route === 'exec');
$is_resource_hub_page = ($_ops_route === 'resource-hub');

// Open layout shell (outputs <html> … <section class="ops-content"><div id="ops-view">)
$shell_part = 'open';
include SLATE_OPS_PATH . 'includes/ui/layout-shell.php';

if ($is_blocked) : ?>
  <section style="display:grid;place-items:center;height:100%;padding:32px;">
    <div style="max-width:520px;text-align:center;background:#fff;border:1px solid #D9D5C7;border-radius:10px;padding:28px;">
      <h1 style="margin:0 0 8px;font-size:24px;">Access Denied</h1>
      <p style="margin:0;color:#5E646B;">You do not have permission to view this Slate Ops page.</p>
    </div>
  </section>
<?php elseif ($page_slug === 'cs-dashboard') :
  // Server-rendered CS Dashboard with the CS Workspace tab. React app is not
  // enqueued for this route in slate-ops.php; the empty #ops-view div above
  // is a harmless sibling.
  include SLATE_OPS_PATH . 'templates/pages/cs-dashboard.php';
elseif ($is_executive_page) :
  // Server-rendered Executive Dashboard V2 (Purchasing pattern).
  // React app is not enqueued for this route in slate-ops.php.
  include SLATE_OPS_PATH . 'templates/pages/executive-dashboard.php';
elseif ($is_resource_hub_page) :
  // Server-rendered Resource Hub. React app is not enqueued for this route.
  include SLATE_OPS_PATH . 'templates/pages/resource-hub.php';
endif; ?>

<?php // For all other routes #ops-view is empty — React app (app.js) mounts and renders content at runtime.

// Close layout shell (outputs </section></div></body></html>)
$shell_part = 'close';
include SLATE_OPS_PATH . 'includes/ui/layout-shell.php';
