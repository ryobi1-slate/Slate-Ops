# Slate Ops Changelog

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
