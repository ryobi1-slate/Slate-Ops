# Slate Ops — Project Instructions

## Versioning

When committing changes for a pull request, bump the plugin version in `slate-ops.php` (both the `Version` header comment and the `SLATE_OPS_VERSION` constant). Use semantic versioning — patch for fixes, minor for features.

## Pages

### CS / Supervisor Operations Dashboard (`/ops/cs-dashboard`)

Server-rendered first, vanilla-JS-enhanced page following the **Purchasing pattern** (PHP template + standalone CSS/JS, React skipped on the route via the `$is_cs_dashboard` branch in `slate-ops.php`). Markup is at `templates/pages/cs-dashboard.php`, data layer at `includes/class-slate-ops-cs.php`, assets at `assets/css/ops-cs-dashboard.css` + `assets/js/ops-cs-dashboard.js`. Phase 1 ships **stub data** — every value comes from `Slate_Ops_CS::get_*()` static methods, and the refresh path runs through `apply_filters('slate_ops_cs_refresh', $payload)` so wiring real ops data later is a single filter swap. Access uses the existing role→page matrix; default grants are admin / supervisor / cs. The legacy `/ops/cs` React page is kept reachable but hidden from default sidebars; an admin can re-enable it per role via the Page Access matrix.

### Workspace tab (Phase 2 — embed bridge to legacy /ops/cs)

The CS Dashboard's second sub-tab ("Workspace") embeds the legacy React `/ops/cs` page in an iframe pointed at `/ops/cs?embed=1`. The `?embed=1` flag is detected in `templates/ops-app.php`, passed into `includes/ui/layout-shell.php`, and **suppresses the topbar + sidebar + sidebar-collapse FOUC script** (chrome PHP-skipped, not CSS-hidden), then appends `ops-embed` to the body class. The React app itself is untouched. The embedded body's background is forced to `--slate-surface-page` in `ops-shell.css` so the cream cascade matches the parent dashboard's `.ops-content` and there is no visible seam across the iframe boundary. The iframe is **lazy-loaded on first tab click** (skeleton with `progress_activity` spinner while it resolves), and stays in the DOM with `src` intact afterward so subsequent tab switches are instant. When Workspace is active, `html.cs-workspace-active` + `body.cs-workspace-active` lock parent scrolling and the dashboard switches to a viewport-fill flex layout so the iframe owns the only scroll region. Access control is unchanged — `?embed=1` is presentation only and the existing CAP_CS / CAP_CS_LEGACY / CAP_SUPERVISOR / CAP_ADMIN gate on `/ops/cs` still runs (denied users see the access-denied panel without chrome).

This is a **transitional bridge** until pieces of the legacy React app are ported into native sub-tabs. **Known limitation deferred to Phase 3+:** the browser back button inside the iframe walks the iframe's own history first, not the parent's. No `postMessage` protocol or URL bridging is implemented for the iframe's internal navigation.
