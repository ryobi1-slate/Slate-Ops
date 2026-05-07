# Slate Ops — Project Instructions

## Versioning

When committing changes for a pull request, bump the plugin version in `slate-ops.php` (both the `Version` header comment and the `SLATE_OPS_VERSION` constant). Use semantic versioning — patch for fixes, minor for features.

## Pages

### CS / Supervisor Operations Dashboard (`/ops/cs-dashboard`)

Server-rendered first, vanilla-JS-enhanced page following the **Purchasing pattern** (PHP template + standalone CSS/JS, React skipped on the route via the `$is_cs_dashboard` branch in `slate-ops.php`). Markup is at `templates/pages/cs-dashboard.php`, data layer at `includes/class-slate-ops-cs.php`, assets at `assets/css/ops-cs-dashboard.css` + `assets/js/ops-cs-dashboard.js`. Phase 1 ships **stub data** — every value comes from `Slate_Ops_CS::get_*()` static methods, and the refresh path runs through `apply_filters('slate_ops_cs_refresh', $payload)` so wiring real ops data later is a single filter swap. Access uses the existing role→page matrix; default grants are admin / supervisor / cs. The legacy `/ops/cs` React page is kept reachable but hidden from default sidebars; an admin can re-enable it per role via the Page Access matrix.
