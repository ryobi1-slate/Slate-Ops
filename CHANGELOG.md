# Slate Ops Changelog

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
