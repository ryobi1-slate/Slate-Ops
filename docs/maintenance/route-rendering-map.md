# Slate Ops route rendering map

Last reviewed: 2026-05-14

This map documents the current `/ops` rendering split. It is maintenance context only and does not change route behavior.

## Routing overview

All `/ops` URLs are captured by the rewrite rules in `includes/class-slate-ops-routes.php`. The shared shell is `templates/ops-app.php`. Asset loading and React-vs-template selection happen in `slate-ops.php` and `templates/ops-app.php`.

## Route map

| Route | Current renderer | Notes |
|---|---|---|
| `/ops/` | Server-rendered PHP + vanilla JS | Root renders the Executive dashboard. |
| `/ops/exec` | Server-rendered PHP + vanilla JS | Uses `templates/pages/executive-dashboard.php`, `assets/css/executive-dashboard.css`, and `assets/js/executive-dashboard.js`. |
| `/ops/cs-dashboard` | Server-rendered PHP + vanilla JS | Uses `templates/pages/cs-dashboard.php`, `assets/css/ops-cs-dashboard.css`, and `assets/js/ops-cs-dashboard.js`. Visible CS work tab is `Job Queue`; internal tab key remains `queue`. |
| `/ops/supervisor-dashboard` | Server-rendered PHP + vanilla JS | Uses `templates/pages/supervisor-dashboard.php`, `assets/css/ops-supervisor-dashboard.css`, `assets/js/ops-supervisor-dashboard.js`, and the `Slate_Ops_Supervisor_Dashboard` stub data layer. |
| `/ops/cs` | React bundle | Legacy CS route. Sidebar labels it `CS (legacy)` when the user has access to the legacy `cs` page. |
| `/ops/tech` | React bundle | Loads the React app plus the tech mobile CSS override. |
| `/ops/schedule` | React bundle | Scheduler UI is still in the React bundle. |
| `/ops/purchasing` | Server shell + standalone vanilla JS | Uses `assets/js/purchasing.js` and `assets/css/purchasing.css`; Business Central remains unplugged per purchasing SOT. |
| `/ops/resource-hub` | Server-rendered PHP + vanilla JS | Uses `templates/pages/resource-hub.php`, `assets/css/resource-hub.css`, and `assets/js/resource-hub.js`. |
| `/ops/admin` | React bundle | Admin page remains in the React bundle. |
| `/ops/settings` | React bundle | Settings page remains in the React bundle. |
| `/ops/monitor` | React route map entry only | Sidebar currently links Monitor users to `/slate-ops-monitor/`, not `/ops/monitor`. |

## Maintenance notes

- Do not edit `assets/react/app.js` for CS Dashboard label cleanup. `/ops/cs-dashboard` does not load the React app.
- Keep `/ops/cs-dashboard` as the canonical CS Dashboard route.
- Keep `/ops/supervisor-dashboard` server-rendered; do not copy prototype React shell files into production.
- Keep `/cs/queue` as the Job Queue endpoint.
- Internal `.cs-beta` selectors are retained for compatibility until a separate CSS/JS refactor is approved.
