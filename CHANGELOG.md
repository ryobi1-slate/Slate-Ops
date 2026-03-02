# Slate Ops Changelog

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
