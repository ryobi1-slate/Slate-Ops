# Slate Ops Changelog

## 0.61.0 — Supervisor actions

- Added safe Supervisor Dashboard action modals for notes, helper assignment, blocker clearing, hold moves, and QC review.
- Added Supervisor-scoped REST endpoints with nonce-protected requests, permission checks, required notes for state changes, and audit logging.
- Kept schedule handoff as navigation only; no drag/drop or schedule writes were added in this phase.

## 0.60.0 — Live Supervisor data

- Replaced the Supervisor Dashboard prototype stub payload with live read-only Ops job data from `slate_ops_jobs`.
- Added live Supervisor rollups for blockers, parts risk, schedule readiness, tech status, QC/rework, ready queue, and attention items.
- Kept Supervisor Dashboard actions read-only while preserving the `slate_ops_supervisor_refresh` filter for future real-data overrides.

## 0.59.0 — Supervisor Dashboard

- Added server-rendered Supervisor Dashboard at `/ops/supervisor-dashboard` with blocked jobs, schedule readiness, tech status, QC/rework, and ready queue views.
- Added scoped Supervisor Dashboard CSS, vanilla JS tab/filter/drawer enhancements, supervisor role access, and sidebar navigation.
- Added `Slate_Ops_Supervisor_Dashboard` stub payload with `slate_ops_supervisor_refresh` for future real-data wiring.
- Preserved the Claude design reference under `design/` and marked design/prototype assets as export-ignored for production plugin ZIP builds.

## 0.58.1 — Current CS Dashboard / CS Workspace final state

**No behavior change in this cleanup entry.** Current main now documents the state reached across the 0.55.0 through 0.58.1 CS Dashboard work:

- `/ops/cs-dashboard` remains the server-rendered CS Dashboard route with standalone vanilla JS enhancement.
- The visible working sub-tab is labeled `CS Workspace`, while the internal tab key remains `queue` and the REST endpoint remains `/cs/queue`.
- `/ops/cs` remains the legacy React CS route and is still reachable only for users granted the legacy `cs` page.
- Queue fields continue to save through `GET/POST /cs/queue`; general job detail edits continue through existing `/jobs` and `/jobs/{id}` endpoints.
- Internal `.cs-beta` selectors and IDs remain unchanged for compatibility.

## 0.58.0 — CS Workspace stabilization

- Finalized the CS Workspace surface inside `/ops/cs-dashboard` with queue ordering, grouped tech lists, detail editing, drag/drop reassignment, and new job intake using existing REST paths.
- Kept `/ops/cs-dashboard` and `/cs/queue` stable; no checkout, payment, shipping, or production branch behavior changed.

## 0.57.0 — CS Workspace promotion

- Promoted the combined CS queue/detail surface as the primary CS workspace concept on the CS Dashboard while preserving legacy hash aliases for saved links.
- Legacy `/ops/cs` stayed available as a separate React route for explicitly granted users.

## 0.56.0 — CS Workspace beta phases

- Added CS Workspace beta queue/detail behavior, then expanded it with drag/drop ordering, cross-tech reassignment, editable job detail fields, and UI polish.
- Continued to use the existing `/cs/queue`, `/jobs`, and `/jobs/{id}` REST endpoints.

## 0.55.0 — CS Dashboard: CS Workspace queue foundation

**`templates/pages/cs-dashboard.php`, `assets/js/ops-cs-dashboard.js`, `assets/css/ops-cs-dashboard.css`:**
- Added the CS-owned queue foundation on CS Dashboard, now surfaced as the `CS Workspace` sub-tab. Lazy-loads on first activation and renders one card per assigned tech.
- Filter chips: All / Scheduled / Blocked / Ready for Closeout / Unassigned.
- Inline edits per row: queue # (number input), queue note (text input), visibility toggle. Save Changes button (disabled until edits) bulk-posts to `/cs/queue`. Normalize Order button re-numbers visible jobs in each tech group to 1, 2, 3 in their current sort.
- Warnings bar: duplicate queue # within a tech group (alert) and blocked or parts-hold job at queue #1 (warn).
- Localized REST root + nonce on the dashboard (`window.slateOpsCsDashboard.api`).

**`includes/class-slate-ops-rest.php`:**
- New `GET /cs/queue` and `POST /cs/queue` endpoints (CS / Supervisor / Admin) covering open jobs. Save handler accepts `queue_order`, `queue_visible`, `queue_note`, optional `assigned_user_id`; sanitizes input, audits via `Slate_Ops_Activity_Log::append`.

**`includes/class-slate-ops-install.php`:**
- Added job columns `queue_visible TINYINT(1)`, `queue_note TEXT`, `queue_updated_at DATETIME`, `queue_updated_by BIGINT UNSIGNED` (CREATE TABLE + idempotent ALTER in `add_missing_columns`).

## 0.47.0 — By Tech view on CS page; CS table enhancements (Phase 0)

**`assets/react/app.js`:**
- CS filter bar: added All Jobs / By Tech view toggle.
- By Tech view: groups SCHEDULED / IN_PROGRESS / BLOCKED / QC jobs by assigned tech (alpha, Unassigned last). Sort within group: IN_PROGRESS → BLOCKED → SCHEDULED (queue_priority asc) → QC; due date then created_at as tie-breakers. Columns: PRI / Status / SO# / Customer / Est.Hrs / Due / Notes.
- Due column: compact M/D format (e.g. 4/30) — year omitted for table density.
- Notes column: single-line ellipsis preview; native title shows full text.
- Status badge hover: context-aware tooltip per status (BLOCKED/ON_HOLD include reason+note).
- Parts badge hover: plain-language description per parts state.

**`assets/css/ops-shell.css`:**
- Added `.ops-cs-notes-cell` (max-width 220px, single-line ellipsis) applied to inner div wrapper for reliable truncation across all table layouts.

**`includes/class-slate-ops-rest.php` / `includes/class-slate-ops-install.php` / `includes/class-slate-ops-utils.php`:**
- `queue_priority` field (1=Next … 4=Low, default 3): DB migration, REST GET/PATCH/CREATE, PHP 7.4-compatible return type, SQL ORDER BY queue_priority ASC.
- Fixed `cs_hold_reasons()` missing `WAITING_ON_VAN` (caused false "Hold reason required" error).
- PHP 8.0 union type `int|WP_Error` replaced with docblock for WordPress 7.4 compat.

_Note: changelog entries for 0.29.0–0.46.1 were not recorded in this file; see git log for details._

## 0.28.2 — Fix: blank CS page (split-body bracket imbalance)

**`assets/react/app.js`:**
- Removed one extra `})` in the CS component return expression that closed the `ops-cs-split-body` div before the right panel was added as a child — leaving its `children:[` array unclosed and producing a `SyntaxError: Unexpected token '}'` that blanked the entire CS page.
- After the fix the right panel (`ops-cs-right`) is correctly nested inside `ops-cs-split-body` as intended by the split-view layout.

## 0.28.1 — CS page: split-view layout (jobs list + detail panel)

**Layout refactor — CS only. No REST, logic, or permission changes.**

**`assets/react/app.js`:**
- CS root wrapper: changed from single-column scroll to `ops-cs-split-page` (flex column, 100% height).
- Added `newMode` state (`useState(false)`) — drives "New Job" panel in the right column.
- Header: retains eyebrow/title/desc + KPI stats; adds `+ New Job` button that opens the create form in the right panel without a modal.
- Split body: `ops-cs-split-body` row flex below the header.
  - Left (`ops-cs-left`): filters row + jobs table. Columns trimmed to Customer / SO# / VIN# / Status / Parts / Est. Hrs / Due for density. Row click selects job into right panel and clears newMode.
  - Right (`ops-cs-right`): conditional panel — shows New Job create form, Job Detail edit form, or "Select a job to review details." empty state. No dashed boxes.
- Create form moved from always-visible top block into right panel (triggered by `+ New Job`); closes on submit or `×` button.
- Edit form moved from below-table block into right panel; shows when a row is selected; closes via `×` or after save.
- Notes field in edit form changed to `<textarea>` (preserves newlines).
- All existing create/edit/save/delete/filter/status logic preserved unchanged.

**`assets/css/ops-shell.css`** — Section 22 (new):
- `.ops-cs-split-page` — flex-column, 100% height, overflow hidden, `--slate-surface-page` background.
- `.ops-cs-split-body` — row flex with gap/padding; fills remaining height.
- `.ops-cs-left` — flex-1, scrollable.
- `.ops-cs-right` — 360px fixed width, scrollable.
- `.ops-cs-empty-panel` — centered empty state card.
- `.ops-cs-border-b-top` — top divider for delete/save action row.
- Split-page overrides: cards no longer forced `margin-bottom`; table rows use `--slate-space-sm` vertical padding in right panel.

## 0.27.2 — Phase 1 purchasing: live REST data, demand filters, correct status model

**`includes/data/class-slate-ops-purchasing.php`:**
- Rewritten with correct SOT status vocabulary: `draft → review → approved → held → ordered → cancelled`
- `maybe_seed()`: seeds 5 vendors + 8 items on first install via `run_install()` — guarded by option flag + empty-table check (vendors AND items). Runs only on activation or version upgrade; not on every `init`.

**`includes/class-slate-ops-purchasing-rest.php`:**
- Stripped to 6 read-only GET endpoints (Phase 1 only): `/purchasing/overview`, `/purchasing/requests`, `/purchasing/items`, `/purchasing/vendors`, `/purchasing/orders`, `/purchasing/api-status`. No POST/PATCH/DELETE endpoints.

**`includes/class-slate-ops-install.php`:**
- Added `preferred_vendor VARCHAR(255)` column to `pur_items` table for demand vendor filter.
- `run_install()` calls `maybe_seed()` — seed runs during install/upgrade only.

**`assets/js/purchasing.js`:**
- Full rewrite: flat SOT state, `loadAll()` with `Promise.allSettled` (partial failures don't blank all tabs), demand filters (search, urgency, vendor), correct status badges, focus-preserving filter re-render, UTC-aware date parsing.

**`assets/css/purchasing.css`:**
- Added filter bar, error/notice banner, and API status variant styles.
- Token cleanup: replaced hardcoded `10px/12px/16px/14px` values with `--slate-space-md/lg` and `--slate-text-base`; `line-height: 1` → `var(--slate-leading-tight)`.
- Error banner: changed from `--slate-redwood-wash/redwood` → `--slate-flag-wash/flag/flag-ink`. Redwood reserved for healthy/on-track states.

## 0.27.1 — Executive page: override React root sage tint via `.slate-portal`

**Root cause:** The React app root wrapper applies `bg-background-light` (`#e1dfc8`, sage/warm-green tint) to itself. This wrapper sits between `#ops-view` and the Executive component's `.slate-portal` div, overriding the shell's `--slate-surface-page` background set on `.ops-content`. Purchasing pages bypass this (they use a separate vanilla JS bundle that doesn't go through the React root), which is why Purchasing shows the correct neutral background.

**`assets/css/ops-shell.css`:**
- `.slate-portal`: added `background: var(--slate-surface-page)` — directly overrides the React root's `bg-background-light` sage tint for the Executive component. Token-based, targeted to `.slate-portal` only.

## 0.26.8 — Executive page: match Purchasing page background

**`assets/css/ops-shell.css`:**
- `.ops-body` and `.ops-content`: changed `background: var(--sand)` → `background: var(--slate-surface-page)` — aligns the content area background with `--slate-surface-page` (#F5F3ED), matching the Purchasing page and Dealer Portal rhythm. CS page's `.ops-cs-page` already overrides with the same token; Tech screen unaffected.

**`assets/react/app.js`:**
- Executive wrapper: removed `flex-1 overflow-y-auto` from className (was `"flex-1 overflow-y-auto slate-portal"`, now `"slate-portal"`). These were redundant — `#ops-view`'s parent is not flex so `flex-1` did nothing; scrolling is handled by `ops-content`'s `overflow-y: auto`. Removing them eliminates the nested scroll container that contributed to the tinted-panel appearance.

## 0.26.7 — Executive page: match Purchasing visual pattern

**Root cause:** `pur-subnav`/`pur-tab` CSS lived only in `purchasing.css` (page-specific). Executive page doesn't load `purchasing.css`, so WP theme button defaults rendered tabs as boxed/outlined buttons instead of underline tabs.

**`assets/css/ops-shell.css`:**
- Section 21 (new): Added `pur-subnav` and `pur-tab` tab styles to global shell CSS so they are available on all pages. Added `border: none !important`, `background: none !important`, `box-shadow: none !important`, `border-radius: 0` to defeat WP theme button defaults.
- Section 16: `.slate-kpi-card` — added `!important` to `border` to prevent WP theme from overriding with a dark top border.
- Section 16: Added `.slate-kpi-grid--exec { grid-template-columns: repeat(4, 1fr) }` — fixed 4-column layout for Executive's 8-card grids (avoids 7+1 awkward wrap from `auto-fill`).

**`assets/react/app.js`:**
- Executive component overview/capture/bottlenecks KPI grids: changed `className:"slate-kpi-grid"` → `"slate-kpi-grid slate-kpi-grid--exec"` (4-col layout). No data/logic changes.

**Before:** boxed red/orange tab outlines (WP theme button defaults), dark top KPI card borders (WP theme override), 7+1 awkward grid wrap  
**After:** clean underline tabs matching Purchasing, uniform card borders, 4×2 KPI grid layout

## 0.26.6 — Sidebar: center collapse chevron matching Dealer Portal

**`assets/css/ops-shell.css`:**
- `.ops-sidebar-collapse-row`: added `justify-content: center`; changed padding from `8px 8px 2px 12px` (left-pinned) to `8px 0 4px` (centered, no horizontal padding)
- `.ops-sidebar-nav-section`: changed padding from `0 12px 8px` (no top — tight) to `8px 12px` (8px breathing room above NAVIGATE, left-aligned at 12px matching nav item rhythm)

**Before:** chevron left-pinned at 12px, NAVIGATE had no top gap  
**After:** chevron centered in full sidebar width (expanded and collapsed), NAVIGATE 8px below chevron, left-aligned

Collapsed state: chevron centers naturally in 44px sidebar; NAVIGATE collapses to zero height/padding via existing rule.

## 0.26.5 — Shell: full-width topbar matching Dealer Portal structure

**`includes/ui/layout-shell.php`:**
- Moved `topbar.php` include above the sidebar — topbar is now the first child of `.ops-shell`
- Added `.ops-shell-body` div wrapping sidebar + `.ops-body` (row flex container below topbar)
- Close sequence updated to close the extra nesting level

**`assets/css/ops-shell.css`:**
- `.ops-shell`: added `flex-direction: column` — topbar sits on top, body row fills remaining height
- Added `.ops-shell-body { display: flex; flex: 1; min-height: 0; overflow: hidden }` — row wrapper for sidebar + content
- Removed redundant `@media (max-width: 900px) { .ops-shell { flex-direction: column } }` (desktop is already column)

**Before:** sidebar started at top-left; topbar was inside `.ops-body`, offset to the right of the sidebar  
**After:** topbar spans full viewport width; sidebar and content begin below the topbar — matches Dealer Portal structure

Sidebar collapse behavior (localStorage, 172px/44px, chevron animation) unchanged.  
Tech screen override (`.ops-page-tech .ops-header { display: none }`) still hides topbar on full-screen tech view.  
Mobile bottom-tab layout unaffected (sidebar is `position: fixed`; `.ops-body` padding-bottom unchanged).

## 0.26.4 — Shell: fix main content left gutter

**`assets/css/ops-shell.css`:**
- `.ops-content`: removed `padding: 24px 28px`; set to `padding: 0`
- Each page wrapper (`p-6`, `p-8`, `.slate-portal`, `.ops-cs-page`) already provides its own internal padding — the shell padding was stacking on top and creating a 52–60px double-gutter
- Removed now-redundant `@media (max-width: 700px) { .ops-content { padding: 0 } }` override (same as base now)
- Result: content starts ~24–32px from sidebar edge, matching Dealer Portal rhythm; Tech screen unaffected (its override sets `padding: 0` separately)

## 0.26.3 — Shell: sidebar visual polish + sage logo

**`assets/logos/slate-logo-sage.png`** — Slate Sage PNG logo asset added; dark sage parallelogram shape with white "|SLATE" wordmark (actual brand asset)

**`includes/ui/topbar.php`:** logo source updated from `slate-logo.svg` (orange bar variant) to `slate-logo-sage.png`; `object-fit: contain`, height 24px, width auto

**`includes/ui/sidebar.php`:**
- Split sidebar header into two stacked rows: `.ops-sidebar-collapse-row` (chevron alone) + `.ops-sidebar-nav-section` (NAVIGATE label alone)

**`assets/css/ops-shell.css`:**
- `.ops-sidebar-header`: changed from single flex row to `flex-direction: column`; removed fixed height
- `.ops-sidebar-collapse-row`: new compact row; left padding `var(--slate-space-md)` to align chevron icon center with nav item icons
- `.ops-sidebar-nav-section`: new label area; collapsed state adds `height: 0; padding: 0` to remove the gap left by opacity-only hiding
- `.ops-sidebar-collapse-btn`: full button reset (`border: none !important`, `box-shadow: none`, `appearance: none`, `background: transparent !important`); hover removes redundant `background` override (already set on base)
- `.ops-nav-link.active`: removed redundant `border-left-color: transparent` (base class already sets `border-left: 2px solid transparent`); background stays `--slate-sage-wash`

## 0.26.2 — Shell: sidebar collapse + Dealer Portal nav alignment

**`includes/ui/sidebar.php`:**
- Added sidebar header: collapse toggle button (chevron_left), "Navigate" label, ⌘K keyboard hint
- Collapse button toggles `html.ops-sidebar-collapsed` class; state persisted to `localStorage` key `slate_ops_sidebar_collapsed`
- Inline script runs immediately after sidebar renders (no FOUC dependency)

**`includes/ui/layout-shell.php`:**
- Added single-line FOUC-prevention script in `<head>` — restores collapsed class from localStorage before first paint

**`assets/css/ops-shell.css`:**
- Section 3: `.ops-sidebar` gains `overflow: hidden` + `transition: width` for smooth collapse animation
- Section 3a: New `.ops-sidebar-header` with collapse button, nav label, and keyboard hint styles
- Section 3b: Collapsed state — `html.ops-sidebar-collapsed .ops-sidebar` → 44px width; labels fade out; chevron rotates 180°; nav links center their icons
- Nav hover: `--slate-surface-tint` → `--slate-sage-wash` (matches Dealer Portal warm hover)
- Mobile breakpoint: `.ops-sidebar-header` hidden in bottom-tab layout; collapsed overrides reset so mobile is unaffected

## 0.26.1 — Dealer Portal component alignment

**`assets/css/ops-shell.css` — new component system (sections 14–20):**
- Added `--slate-weight-bold: 600` and `--slate-flag-ink: #8B1A1A` tokens to `:root`
- Section 14: `.slate-portal` — page container with token-driven padding and max-width
- Section 15: `.slate-card`, `.slate-card__header`, `.slate-card__title` — white card surface
- Section 16: `.slate-kpi-grid`, `.slate-kpi-card`, `__label`, `__value`, `__sub` — KPI metric cards
- Section 17: `.slate-table` with thead/tbody/tr/td/th token styles, `__lead`, `__num`, `__empty` modifiers
- Section 18: `.slate-btn` with `--primary`, `--secondary`, `--outline` variants — no blue/purple
- Section 19: `.slate-status` with `--neutral`, `--attention`, `--healthy`, `--critical` — no blue/purple/pink
- Section 20: `.ops-banner-error` — inline error banner using `--slate-flag-wash` + `--slate-flag`
- Fixed `.ops-btn-danger`: hardcoded `#c0392b / #922b21` → `var(--slate-flag) / var(--slate-flag-ink)`
- Fixed `.ops-cs-empty`: `1.5px dashed` border → `1px solid var(--slate-divider-soft)`
- Fixed `.ops-page-eyebrow`: `font-weight: 700` → `var(--slate-weight-medium)`

**`assets/react/app.js` — Executive dashboard restyled to use component classes:**
- Outer wrapper: `flex-1 overflow-y-auto slate-portal` (removed `p-8 font-sans` utility soup)
- Loading state: `"text-center py-16 text-slate-400"` → `ops-cs-empty`
- Error banner: Tailwind `bg-red-50 border border-red-200 text-red-700` soup → `ops-banner-error`
- Overview/Capture/Bottleneck KPI grids: `grid grid-cols-* gap-4` → `slate-kpi-grid`
- KPI cards: `ops-cs-card p-4 rounded-xl flex flex-col justify-between h-28` → `slate-kpi-card`
- KPI labels: `text-[10px] font-bold text-slate-400 uppercase tracking-wider` → `slate-kpi-card__label`
- KPI values: `text-3xl font-bold text-slate-900` → `slate-kpi-card__value`
- Table section wrappers: `ops-cs-card rounded-xl overflow-hidden` → `slate-card`
- Table headers: `px-6 py-4 ops-cs-border-b` → `slate-card__header`
- Table titles: `font-bold text-lg text-slate-800` → `slate-card__title`
- Tables: `w-full text-sm text-left` → `slate-table`; all th/td/tr utility soup removed — CSS handles it
- Table lead cells: `px-6 py-3 font-bold text-slate-900` → `slate-table__lead`
- Table empty cells: `px-6 py-8 text-center text-slate-400 italic` → `slate-table__empty`

## 0.26.0 — Shared design system tokens + visual drift cleanup

**`assets/css/ops-shell.css` — token additions/normalization:**
- Radius scale corrected: `--slate-radius-none: 0`, `--slate-radius-md: 5px` (was 8px), `--slate-radius-lg: 8px` (was 12px)
- Added line height tokens: `--slate-leading-tight`, `--slate-leading-normal`, `--slate-leading-relaxed`
- Added letter spacing tokens: `--slate-tracking-normal`, `--slate-tracking-label`, `--slate-tracking-wide`
- Added border shorthand tokens: `--slate-border-width`, `--slate-border-thick`, `--slate-border`, `--slate-border-strong`, `--slate-border-soft`
- Added `--slate-shadow-none: none` and `--slate-shell-topbar-height: 56px`

**`assets/css/purchasing.css` — badge drift fix:**
- `pur-badge--submitted`: replaced hardcoded `#e8f0fe / #1a56db` (blue) → `--slate-surface-tint / --slate-ink-muted`
- `pur-badge--acknowledged`: replaced hardcoded `#e8f0fe / #1a56db` (blue) → `--slate-sage-wash / --slate-sage-ink`
- `pur-badge--partial`: replaced hardcoded `#fef9c3 / #854d0e` (yellow) → `--slate-arches-wash / --slate-arches-ink`

**`assets/react/app.js` — drift fixes (7 targeted replacements):**
- `IN_PROGRESS` job card header: `bg-blue-700` → `bg-slate-600` (sage-neutral)
- `PENDING_QC` job card header: `bg-purple-700` → `bg-slate-500`
- QUEUED status badge pill: `bg-blue-100 text-blue-700` → `bg-slate-100 text-slate-600`
- QUEUED status dot: `bg-blue-400` → `bg-slate-400`
- Delete button hover: `hover:text-rose-500` → `hover:text-red-700`
- Sidebar: removed `shadow-xl` (sidebar already has CSS border-right)
- Modal card: `shadow-2xl` → `shadow-sm`

**Deferred (not safe to change in bulk):**
- `font-bold` (233 instances) — pervasive, needs Tailwind config rebuild
- `rounded-full` (13 instances) — acceptable for pill badges
- `orange`/`amber`/`green` Tailwind classes — semantic and acceptable
- Hardcoded `#d86b19` arches hex in buttons — requires CSS class + JS co-migration
- No PHP changes

## 0.25.9 — Executive dashboard: labor/job performance KPI tabs

**New REST endpoint — `GET /slate-ops/v1/executive/labor-summary`** (admin/supervisor only):
- Returns five aggregates: `overview`, `tech_performance`, `job_performance`, `labor_capture`, `bottlenecks`
- Overview: active job counts (total, in-progress, pending QC, ready for pickup), total estimated minutes, total logged minutes, variance, labor capture %
- Tech Performance: per-tech assigned/active job counts, est. minutes, logged minutes, variance, capture %
- Job Performance: per-job SO#, customer, status, lead tech, est. minutes, logged minutes, variance, % used
- Labor Capture: jobs with/missing estimate, jobs with/without logged time, total raw logged hours, estimate coverage %
- Bottlenecks: count per status (READY_FOR_BUILD, QUEUED, IN_PROGRESS, PENDING_QC, READY_FOR_PICKUP, ON_HOLD, DELAYED)

**React — Executive `t0` component rebuilt with 5 tabs** (Purchasing page tab style):
- Overview, Tech Performance, Job Performance, Labor Capture, Bottlenecks
- Uses `pur-subnav` / `pur-tab` CSS classes (active underline, Slate colors)
- Fetches `/executive/labor-summary` on mount; shows loading/error states
- KPI cards use existing `ops-cs-card` class; tables use `ops-cs-thead` / `ops-cs-divide`
- No payroll logic; all metrics are job-costing/performance signals

- `includes/class-slate-ops-rest.php` + `assets/react/app.js` changes; no CSS changes

## 0.25.8 — Tech screen Today's Time: job-costing deduction formula

**PHP — `time_daily_summary()`:**
- Replaced tiered Phase 1 rule with the correct job-costing/performance formula:
  - `configured_deduction = (break_count × break_minutes) + lunch_minutes` (from saved settings)
  - `applied_deduction = min(configured_deduction, raw_minutes)` — never deducts more than was worked
  - `net_minutes = raw_minutes - applied_deduction` (floor 0)
- Example: 5 min raw, 80 min configured → applied = 5 min, net = 0 min (correct for costing)
- Example: 240 min raw, 80 min configured → applied = 80 min, net = 160 min
- Settings remain connected to `slate_ops_settings` table (not hardcoded)
- Added code comment: "This is a job-costing/performance deduction, not payroll calculation."
- No React changes — frontend already renders `ee.deduction_minutes` / `ee.net_minutes` directly from backend

## 0.25.7 — Fix Tech screen Today's Time deduction (tiered rule)

**PHP — `time_daily_summary()`:**
- Replaced binary threshold (`>= 180 min → apply all deductions`) with Phase 1 tiered rule:
  - `< 2 h (< 120 min)` → 0 deductions (a 5-minute log no longer shows a negative net)
  - `2–4 h (120–239 min)` → 1 break
  - `4–6 h (240–359 min)` → 2 breaks
  - `≥ 6 h (≥ 360 min)` → 2 breaks + lunch
- Break count capped at configured `break_count` from `slate_ops_settings` (settings remain connected, not hardcoded)
- Return payload now always includes `lunch_minutes`, `break_minutes`, `break_count` for transparency
- No React changes — frontend already renders `ee.deduction_minutes` / `ee.net_minutes` directly from backend

## 0.25.6 — Supervisor QC and Pickup flow

State flow: IN_PROGRESS → PENDING_QC → READY_FOR_PICKUP → COMPLETE (fail path: PENDING_QC → IN_PROGRESS)

**PHP:**
- `review_qc()`: QC PASS now transitions PENDING_QC → READY_FOR_PICKUP (previously went straight to COMPLETE, skipping pickup step)
- `set_status()`: added COMPLETE guard — job must be READY_FOR_PICKUP to mark complete (422 otherwise)

**React — Supervisor dashboard (`n0` component):**
- **Pending QC panel**: lists all PENDING_QC jobs; supervisor can Pass QC (→ READY_FOR_PICKUP) or Fail / Send Back (→ IN_PROGRESS via `window.prompt` for required note); error banner on failure; busy-lock prevents double-submit; page reloads on success
- **Ready for Pickup panel**: lists all READY_FOR_PICKUP jobs; CS/Supervisor can Mark Complete (→ COMPLETE); same error/busy/reload pattern

- `class-slate-ops-rest.php` + `app.js` changes; no CSS changes

## 0.25.5 — Tech timer endpoint hardening

- **`set_status()` PENDING_QC guard**: `POST /jobs/{id}/status` now returns 422 if the job is not IN_PROGRESS when transitioning to PENDING_QC; matches the guard already present in `submit_qc`; prevents backend from accepting Complete from wrong states
- **Complete handler response check**: React `I=` handler now inspects `res.ok` on the `/jobs/{id}/status` fetch and throws with the backend error message if not ok — previously a 422 was silently swallowed
- **Complete handler status pre-flight**: React `I=` now checks `r.status !== "IN_PROGRESS"` before showing the confirm dialog and sets the error banner immediately without a network call
- `class-slate-ops-rest.php` + `app.js` changes; no CSS changes

## 0.25.4 — Tech timer workflow hardening

- **`time_start()` job-status guard**: returns 422 if the job is not in QUEUED or IN_PROGRESS status; prevents starting timers on completed/held jobs
- **`time_start()` duplicate-timer guard**: if tech has an active timer on the same job, returns 200 idempotently; if on a different job, returns 409 "You already have an active timer on another job. Pause it before starting a new one."
- **`time_stop()` response**: now includes `job_id` alongside `segment_id` and `stopped_at`
- **React Play/Start pre-flight guard**: `Ot` handler checks `r` (active job) before calling the API — if a different job is already active, sets the error banner immediately without a network round-trip
- **Notes button**: replaces `alert("Notes coming soon")` stub with a real `add_note` API call (`POST /jobs/{id}/notes`); prompts for text via `window.prompt`, refreshes job data on success
- `class-slate-ops-rest.php` + `app.js` changes; no CSS changes

## 0.25.3 — Tech screen: fix action button palette

- **Complete**: secondary outline — `ops-tech-action-secondary` (white bg → `--surface-card`, sage text → `--sage`, sage border → `--border-md`)
- **Pause**: primary filled — `ops-tech-action-primary` (dark sage bg → `--sage`, white text); most-used in-session action promoted to primary
- **Notes**: secondary outline — `ops-tech-action-secondary`
- **Play** (Up Next + Help cards): `ops-tech-play-btn` (dark sage bg, white text; `[disabled]` attribute triggers grey/muted CSS state — no extra JS class needed)
- Root cause: Tailwind `@layer utilities` loses to unlayered WP theme `button {}` selectors; Section 13 in `ops-shell.css` uses `.ops-page-tech #ops-view` ID scope (specificity 1,2,0) to enforce palette
- `app.js` + `ops-shell.css` changes; no logic or PHP changes

## 0.25.2 — Tech screen UI cleanup Part 2

- **Desktop layout**: removed `max-w-[480px] mx-auto shadow-2xl` phone-mockup constraint from the Tech panel; panel now fills the content area on all viewports
- **Help button / job cards**: added `ops-tech-help-btn` and `ops-tech-card` CSS classes backed by unlayered `#ops-view`-scoped rules in `ops-shell.css` (Section 12); these rules carry specificity 1,2,0 and beat any unlayered WordPress theme `button {}` selectors that were overriding Tailwind's `@layer utilities` color classes with pink/magenta
- **Color**: Help button renders white background (`--surface-card`), sage text (`--sage`), neutral border (`--border`); hover transitions to sand (`--sand`)
- `app.js` + `ops-shell.css` changes; no PHP or logic changes

## 0.25.1 — Tech screen: guarantee ops-page-tech body class via React

- **Body class fix**: `g0` component now adds `ops-page-tech` to `document.body` on mount and removes it on unmount; ensures Section 11 CSS (sidebar/topbar hiding, full-height layout) applies regardless of whether the PHP route system set the class
- `app.js` change and version bump; no CSS or functional PHP changes

## 0.25.0 — Tech screen UI cleanup

- **Sidebar**: hidden on mobile/tablet (≤900px) for Tech route — no bottom nav bar squeezing the phone UI
- **Topbar**: hidden on Tech route — phone panel owns its own header
- **Layout**: `ops-content` padding removed for Tech; `#ops-view` participates in flex height chain so panel fills viewport
- **Bottom nav**: removed single-button "MY JOBS" bar (redundant with sidebar nav)
- **Help on Another Job**: label corrected to title case
- **Section spacing**: `space-y-6` → `space-y-4`, `py-5` → `py-4` in main content area
- CSS changes in `ops-shell.css` (section 11); no logic/data changes

## 0.24.0 — Tech screen: visibility rules and Up Next sorting

- **Up Next** now fetches only `QUEUED`, `IN_PROGRESS`, and `PENDING_QC` jobs assigned to the current tech; `READY_FOR_BUILD`, `UNSCHEDULED`, and `ON_HOLD` jobs no longer appear
- **Up Next sort order**: IN_PROGRESS first → QUEUED → PENDING_QC, then `scheduled_start` ASC (nulls last) → `queue_order` ASC (nulls last) → `created_at` ASC
- **Help on Another Job** now fetches only `IN_PROGRESS` jobs and excludes jobs where the current user is the lead tech (`assigned_user_id`), sorted by `scheduled_start` ASC then `created_at` ASC
- **Play button** disabled for `PENDING_QC` status; also fixed a duplicate `COMPLETE` check in the `b0` guard
- `app.js` logic changes only; no PHP or CSS changes

## 0.17.9 — Hotfix: restore section div bracket after timer panel; remove redundant rounded-full

- **Blank page fix**: v0.17.7 timer panel replacement was missing one closing `]})` that shut the parent section div; all Ops pages rendered blank as a result. Inserted the missing bracket sequence to restore correct JSX tree structure.
- **`rounded-full` removed** from the Exec Recent Job Movement badge className — redundant because `.ops-badge-status-*` CSS already enforces `border-radius: 999px !important` via the softness pass
- `app.js` bracket + class change only; no CSS or logic changes

## 0.17.8 — Exec page: fix status labels and badge colors

- **Exec Recent Job Movement table**: replaced raw enum values (`M.status`) with human-readable labels via inline map; added `rounded-full` pill class; fallback changed from `bg-slate-200` to `ops-badge-status-intake`
- **PENDING_QC label**: corrected from `"In Progress"` → `"Pending QC"` in the CS component `SL` map
- **In Progress badge**: `--slate-sage-ink` bg → `--slate-sage` bg (lighter, token-aligned)
- **Complete badge**: `--slate-redwood` fill → `--slate-redwood-wash` bg / `--slate-redwood` text (softer)
- **On Hold / Delayed badge**: `--slate-flag` fill → `--slate-flag-wash` bg / `--slate-flag` text (softer)
- CSS + mapping only; no logic changes

## 0.17.7 — Tech mobile: dark timer panel

- **Timer panel**: Replaced 3 separate HRS/MIN/SEC dark boxes with a single unified dark panel (`bg-[#1f2d29]`, `rounded-2xl`, increased padding)
- **Label**: "LABOR TIME ELAPSED" eyebrow — 10px uppercase, `text-white/50`, tracked
- **Display**: Elapsed time rendered as `HH:MM:SS` in a single `text-5xl` mono display
- **Target row**: Optional row (below a `border-white/10` divider) renders only when `r.estimated_minutes` is present on the active job; formatted with existing `yu()` zero-pad helper
- No efficiency calculation (no source data); no fake data; no behavior changes

## 0.17.6 — Page header hierarchy: eyebrow + title + description + divider

- **CSS classes added**: `ops-page-header`, `ops-page-eyebrow`, `ops-page-title`, `ops-page-desc`
- **`ops-page-header`**: `padding-bottom: 20px; margin-bottom: 24px; border-bottom: 1px solid var(--slate-divider)`
- **`ops-page-eyebrow`**: 10px uppercase, `letter-spacing: 0.12em`, `color: var(--slate-arches)`
- **`ops-page-title`**: `color: var(--slate-ink)`, `font-weight: 500`, no forced caps
- **`ops-page-desc`**: `color: var(--slate-ink-muted)`
- **CS page**: eyebrow "CS Queue" added above h1; header wrapper gets `ops-page-header` divider; stats summary card wrapper removed (plain flex row)
- **Exec page**: existing eyebrow div, h1, and description converted to new semantic classes; header wrapper uses `ops-page-header`
- **Schedule page**: same treatment as CS (eyebrow "Schedule" added; card wrapper on stats removed)
- CSS + markup changes only; no logic or routing changes

## 0.17.5 — CS softness pass: radius, borders, text hierarchy, empty state

- **Radius tokens**: `--slate-radius-lg: 12px` and `--slate-radius-md: 8px` added to `:root`
- **Cards**: `border-radius: var(--slate-radius-lg)` applied via `.ops-cs-card`
- **Inputs/selects**: `border-radius: var(--slate-radius-md)` applied via `.ops-cs-input`
- **Buttons**: `border-radius: var(--slate-radius-md)` applied via `.ops-btn-arches-solid` and `.ops-btn-arches`
- **Badge pills**: all `.ops-badge-*` classes set to `border-radius: 999px` — fully rounded pills with slightly wider horizontal padding
- **Table row dividers**: `border-bottom: 1px solid var(--slate-divider-soft)` on each row; cell padding increased to 14px (from 12px)
- **Table header**: padding increased slightly for breathing room
- **Text hierarchy**: `.ops-cs-card label` uses `--slate-ink-subtle` and `font-weight: 500`; `h1/h2` use `--slate-ink`
- **Empty state**: dashed `--slate-divider-soft` border, `--slate-radius-lg` corners, `--slate-ink-muted` text; class `ops-cs-empty` replaces raw Tailwind utilities
- **Section spacing**: card margin-bottom increased to 20px
- CSS-only visual changes; no behavior or routing changes

## 0.17.4 — Visual polish: buttons, status badges, sidebar, logo

- **Buttons**: Primary buttons (Create Job, Save & Close) now use `--slate-sage` fill → `--slate-sage-ink` hover; orange/arches removed as primary color
- **Status badges**: Scheduled → neutral sage-wash/sage-ink; Pending QC → amber arches-wash/arches-ink (separated from In Progress); In Progress → dark sage-ink; Complete → redwood; Blocked/Hold → flag red
- **Sidebar**: Background → `--slate-surface`; border → `--slate-divider`; nav links → `--slate-ink-muted`; hover → `--slate-sage-wash`; active → `--slate-surface-tint` with arches left accent (`box-shadow: inset 3px 0 0 var(--slate-arches)`); user/footer colors all via `--slate-ink-*` tokens
- **Logo**: Replaced inline SVG with `assets/logos/slate-logo.svg` local asset; CSS filter renders it dark on the light sidebar; no external URLs
- CSS-only visual changes; no behavior or routing changes

## 0.17.3 — CS visual tokens: Dealer Portal design system

- Mirrored full `--slate-*` token set from Dealer Portal into `ops-shell.css` `:root`
- Added 23 semantic CSS classes (`ops-cs-*`, `ops-badge-*`, `ops-btn-arches-solid`) using only `var(--slate-*)` tokens — no invented colors, no hardcoded hex in class definitions
- **Page background**: `--slate-surface-page` (#F5F3ED)
- **Cards**: `--slate-surface` + `--slate-divider` border; `shadow-sm` replaced with token border
- **Table headers**: `--slate-sage-wash` bg, `--slate-ink-subtle` text, `--slate-divider` bottom border
- **Table rows**: selected → `--slate-arches-wash`; hover → `--slate-surface-tint`; dividers → `--slate-divider-soft`
- **Inputs**: `--slate-divider` border; focus ring/border → `--slate-arches`
- **Buttons**: `--slate-arches` fill → `--slate-arches-ink` hover (via updated `ops-btn-arches-solid`)
- **Parts badges**: Ready → redwood-wash/redwood; Partial → arches-wash/arches-ink; Not Ready → flag-wash/flag
- **Job status badges**: Intake → sage-wash/sage-ink; Scheduled → arches; In Progress → sage-ink; Complete → redwood; On Hold/Delayed → flag
- **Edit panel**: orange left border removed; neutral `--slate-divider` card border
- No behavior changes; CS scope only

## 0.17.2 — Role-based /ops landing route

- Visiting bare `/ops` now redirects to the user's primary page based on role:
  admin/supervisor → `/ops/exec`, cs → `/ops/cs`, tech → `/ops/tech`
- Direct routes (`/ops/cs`, `/ops/exec`, `/ops/tech`, etc.) are unaffected
- Logic reads `slateOpsSettings.user.caps`; no PHP or routing changes

## 0.17.1 — CS: Start Date field

- Added Start Date field (`scheduled_start`) to the CS job list, create form, and edit panel
- Wires the existing backend field — no schema or API changes
- Field order: Customer Name → VIN# → SO# → Lead Tech → Est. Hours → Start Date → Due Date → Notes → Job Status → Parts Status
- Both create and save payloads include `scheduled_start`

## 0.17.0 — CS cleanup: field order, labels, colors, Save & Close

- **Field order** applied to job list table, create form, and edit panel:
  Customer Name → VIN# → SO# → Lead Tech → Est. Hours → Due Date → Notes → Job Status → Parts Status
- "Description" label renamed to "Notes" throughout CS UI (backend field unchanged)
- **Save & Close**: X button removed from edit panel; Save renamed to "Save & Close"; panel closes automatically on successful save
- **Job Status labels**: `APPROVED_FOR_SCHEDULING` → "Scheduled", `PENDING_QC` → "In Progress"; added `COMPLETE_AWAITING_PICKUP` → "Complete - Awaiting Pickup", `DELAYED` → "Delayed"
- **Job Status colors**: Red = On Hold/Delayed, Amber = Scheduled/Complete-Awaiting-Pickup, Blue = In Progress, Green = Complete
- **Parts Status colors**: Red = Not Ready, Amber = Partial, Green = Ready/Received/Arrived
- Parts labels normalized: `RECEIVED`/`ARRIVED` → "Ready", `HOLD` → "Not Ready"
- Notes cell styled bold when `parts_status = PARTIAL` for visibility

## 0.16.4 — Cache-bust / deploy refresh (no code changes)

- Plugin version bumped so WordPress asset URLs re-version and browsers / CDNs drop the previous bundle. No CSS, JS, or behavior changes.

## 0.16.3 — Technician visual refinement (no layout change)

### Technician (`/ops/tech`)
- Stop button repainted in Slate Redwood (`--redwood`) instead of bright orange — operational tone, not promotional.
- Hero title reduced (28px → 22px) so the elapsed timer is the strongest visual signal in the active state.
- Active-state left accent now painted as an inset `box-shadow`, removing the active-vs-idle padding shift; both states share identical padding.
- Up Next rail CTAs quieted to outlined sage so the rail clearly reads as secondary to the active hero.
- Tightened phone (≤760) and tablet (≤1080) breakpoints; added a sub-480 breakpoint that stacks the timer cells vertically only on the smallest screens.

## 0.16.2 — Technician screen execution-surface pass

### Technician (`/ops/tech`)
- Collapsed the card-stack feel into a single dominant active hero. Blocker and work notes are now inline divider-separated sections of the hero, not standalone competing cards.
- Timer is now a flat structural strip directly under the job title (Elapsed | Target), top-and-bottom hairline rules, no panel background or gradient.
- Stop / Submit-for-QC / + Note grouped into a single action cluster with a clear primary action.
- Up Next rail rebuilt as one bordered panel of dense divider rows with edge-to-edge tap targets — no more floating card-per-job. Blocked and focused rows use inset accent rules instead of borders.
- Removed `box-shadow` from hero and Up Next surfaces to reduce the "app card" feel.
- Stronger Slate page header: condensed display title, sage-led eyebrow, page-level border-bottom that ties to the rest of Ops.
- Removed stale CSS for `.tech-block-card`, `.tech-notes-card`, `.tech-next-card`, the old `.tech-timer*` panel, and the `.tech-stop-dot` glyph.

## 0.16.1 — Technician screen integration pass

### Technician (`/ops/tech`)
- Composition reworked to feel like a Slate Ops page, not a phone mockup dropped into the desktop shell.
- Added a Slate-style page header (eyebrow + title + sub) so the surface reads like the rest of Ops; the phone-view toggle moved into the header.
- Switched from a centered narrow column to a true two-column layout on desktop (main hero + sticky Up Next sidebar). Stacks to a single column under 1080px and on phone view.
- Removed the generic mobile-app status pill ("Running" / "Idle" green/grey dot) in favor of a Slate eyebrow with a sage status dot.
- Active hero now uses a sage left rule (matching existing Slate card treatments) instead of a heavy elevated shadow.
- Timer panel flattened into a single bordered "measurement strip" with elapsed | target side-by-side, swapping the gradient panel for a calmer Slate token surface.
- Up Next sidebar restyled as a single bordered card with internal divider rows, replacing the heavier free-floating mobile-app cards.
- Stop / Start buttons retuned to standard Slate radii and weight while preserving touch-friendly sizing.

## 0.16.0 — Technician surface refresh (phone-first execution screen)

### Technician (`/ops/tech`)
- Rewrote the landing screen as a focused, phone-first execution surface.
- Active state: unified hero card with status pill, job label, customer/unit meta, large mono timer with optional target, dominant "Stop Work & Log Labor" action, secondary "Submit for QC" and "+ Note" actions, inline QC/note panels.
- Idle state is no longer a dead end — the next assigned job is promoted inside the hero as a clear "Start this job" CTA.
- Empty state rendered when nothing is assigned.
- Blocker card (amber, left-striped) surfaces `hold_reason` / `delay_reason` / `status_detail` for the active job.
- Work notes card surfaces `schedule_notes` for the active job, kept visually secondary to the timer.
- Up Next: simple vertical card list with one primary Start/Resume/Open action per job; cards tap-open into job detail.
- Removed the legacy "All Jobs" collapsible table from the Tech screen to cut admin clutter.
- Shell/color tokens, cards, and buttons reuse existing Ops design tokens — no shell redesign.

## 0.8.0 — Phase 0 UI Map (Dashboard + Scheduler Inspector + Job Detail Tabs)

### Dashboard (`/ops/exec`)
- Replaced minimal KPI row with full Phase 0 dashboard layout
- **Today's Plan** section: scheduled today count, hours loaded vs. capacity bar (shift hours × tech count), late/blocked count
- **Flow Health** grid: WIP count + oldest job age for each workflow stage (Unscheduled → Ready → Scheduled → In Progress → Pending QC)
- **Alerts** row: Parts Hold, Approval Hold, Over Hours — shown only when non-zero
- **Quick Actions** toolbar: Create Job, Create Schedule Block, Add Note to Job (inline modal, no page nav required)

### Scheduler (`/ops/schedule`)
- New 3-column layout: Unscheduled Queue (left) + Calendar Grid (center) + Job Inspector (right)
- **Left panel**: filterable queue with search input, blocker badges (Parts Hold, RUSH), promised date
- **Right panel Inspector**: populates on card click (replaces modal); shows job header, schedule, assigned tech, est/actual hours, blocker alert, Start/Stop timer buttons, Open Full Detail link
- Calendar job cards: blocked jobs highlighted amber; selected card outlined in orange accent
- Drag and drop behavior unchanged; bulk save (scheduler-phase0.js) unchanged

### Job Detail (`/ops/job/:id`)
- Added tab navigation: **Summary** | **Time** | **Blockers** | **Activity**
- Summary tab: job fields, schedule, notes, quick actions (Set SO#, QC Approve, Edit Job)
- Time tab: Start/Stop/Fix buttons + tech time breakdown table
- Blockers tab: Parts Hold / Approval Hold toggles + hold note — auto-activated if job is currently blocked
- Activity tab: append-only audit log table (newest first)
- Hold badge visible in job header when blocked

### No schema changes
All Phase 0 fields already existed. Zero destructive changes.

---

## 0.7.0 — Phase 0 Manual Scheduling

### UI
- Applied B2B Operations Dashboard design system: dark navy sidebar (#1e2132), orange accent (#e07444), neutral canvas (#f0f2f5)
- Version badge moved from bottom-right overlay to top-right of header bar on all Ops pages
- Supervisor role now shows Settings nav link
- Monitor display shows version string in header (`slateMonitorSettings.version`)

### Job Detail
- Blockers panel: one-click Parts Hold and Approval Hold with note capture; persists via existing `status`/`delay_reason` fields using `PATCH /jobs/{id}`
- Activity log card: reads from `wp_slate_ops_audit_log` table (status, schedule, assignment, time changes); displayed append-only below the job detail card

### Scheduler
- Confirmed: drag/drop persists `scheduled_start`, `scheduled_finish`, `work_center` via `POST /jobs/{id}/schedule`
- New Entry modal assigns installer (`assigned_user_id`) alongside bay and dates

### API
- New `GET /jobs/{id}/activity` endpoint reads `wp_slate_ops_audit_log` for a job (up to 200 entries, newest first); requires `ops` capability

### Monitor
- `slateMonitorSettings` now includes a `version` field (passed from PHP via `wp_localize_script`)
- Version chip rendered in monitor header below Last Sync timestamp

---

## 0.6.11
- Internal updates (version bump)

## 0.6.10
- Initial tracked release
