# Phase 1 Architecture: Status, Parts, and Tech Queue Logic

**Plugin version at time of writing:** 0.19.4  
**Scope:** Canonical job lifecycle, parts readiness, CS release gate, tech visibility, and queue ordering.  
This document is descriptive — it reflects the system as built. No code changes are implied.

---

## 1. Purpose

This document specifies the job-lifecycle model for Slate Ops Phase 1. It defines:

- Every valid job status value and what it means
- How jobs move between statuses and who triggers each transition
- The parts-readiness model and how it gates release to the scheduler
- What technicians can see and act on at each stage
- How the scheduler queue is ordered
- How statuses from the legacy ClickUp system map to current values

The intended audience is engineers building new features, QA verifying workflows, and anyone troubleshooting a job stuck in an unexpected state.

---

## 2. Canonical Job Statuses

All status values are stored as bare uppercase strings in the `status VARCHAR(30)` column of the `slate_ops_jobs` table. There are no PHP constants — the strings themselves are the canonical form.

| Status | DB Value | Who Creates It |
|---|---|---|
| Pending Intake | `PENDING_INTAKE` | Job creation without an SO number |
| Approved for Scheduling | `APPROVED_FOR_SCHEDULING` | CS adds SO number, or CS releases job, or portal intake |
| Scheduled | `SCHEDULED` | Supervisor assigns job to a technician |
| In Progress | `IN_PROGRESS` | Technician starts the work timer |
| Pending QC | `PENDING_QC` | Technician submits job for supervisor review |
| Complete | `COMPLETE` | Supervisor passes QC |
| On Hold | `ON_HOLD` | Manual hold by CS or Supervisor |
| Delayed | `DELAYED` | ClickUp importer only (legacy) |
| Complete – Awaiting Pickup | `COMPLETE_AWAITING_PICKUP` | ClickUp importer only (legacy) |

The `status_detail` column (`VARCHAR(100) NULL`) carries supplementary text when a status alone is ambiguous. The `status_updated_at` column records when the status last changed.

---

## 3. UI Labels

The internal DB value and the label shown in the Ops UI are not always identical. The mapping below covers both the internal admin views and the read-only dealer portal.

### Ops UI Labels

| DB Value | Displayed Label |
|---|---|
| `PENDING_INTAKE` | Pending Intake |
| `APPROVED_FOR_SCHEDULING` | Approved for Scheduling |
| `SCHEDULED` | Scheduled |
| `IN_PROGRESS` | In Progress |
| `PENDING_QC` | Pending QC |
| `COMPLETE` | Complete |
| `ON_HOLD` | On Hold |
| `DELAYED` | Delayed |
| `COMPLETE_AWAITING_PICKUP` | Complete – Awaiting Pickup |

### Dealer Portal Labels

The dealer-facing portal collapses internal statuses into three coarse buckets via `Slate_Ops_Utils::dealer_status_from_internal()`:

| Portal Label | Internal Statuses Mapped |
|---|---|
| `waiting` | `PENDING_INTAKE`, `APPROVED_FOR_SCHEDULING`, `SCHEDULED`, `ON_HOLD` |
| `in_process` | `IN_PROGRESS`, `PENDING_QC` |
| `complete` | `COMPLETE` |

---

## 4. Status Flow

### Primary (Happy-Path) Flow

```
PENDING_INTAKE
    │  CS adds SO number  →  set_so()
    ▼
APPROVED_FOR_SCHEDULING
    │  Supervisor assigns tech + scheduled_start  →  scheduler update
    ▼
SCHEDULED
    │  Tech starts timer  →  time_start()
    ▼
IN_PROGRESS
    │  Tech submits for review  →  submit_qc()
    ▼
PENDING_QC
    ├─ Supervisor PASS  →  review_qc()  →  COMPLETE
    └─ Supervisor FAIL  →  review_qc()  →  IN_PROGRESS  (rework loop)
```

### Transition Details

**Job creation → PENDING_INTAKE or APPROVED_FOR_SCHEDULING**  
`create_job_manual()` (`class-slate-ops-rest.php`)  
- If SO number is supplied at creation time → starts at `APPROVED_FOR_SCHEDULING`.  
- If no SO number → starts at `PENDING_INTAKE`.  
- Portal-quoted jobs always start at `PENDING_INTAKE`; the SO number is added later.

**PENDING_INTAKE → APPROVED_FOR_SCHEDULING**  
`set_so()` (`class-slate-ops-rest.php`)  
- Triggered when CS adds the SO number to an intake-stage job.  
- Audit note written: "Moved to Approved for Scheduling".

**APPROVED_FOR_SCHEDULING → SCHEDULED**  
`build_scheduler_update_fields()` (`class-slate-ops-rest.php`)  
- Supervisor assigns `assigned_user_id`, `scheduled_start`, `scheduled_finish`, `work_center`.  
- Status is set to `SCHEDULED`.

**SCHEDULED → IN_PROGRESS**  
`time_start()` (`class-slate-ops-rest.php`)  
- If the job is not already `IN_PROGRESS`, `PENDING_QC`, or `COMPLETE`, starting the timer auto-advances the status to `IN_PROGRESS`.

**IN_PROGRESS → PENDING_QC**  
`submit_qc()` (`class-slate-ops-rest.php`)  
- Requires current status to be `IN_PROGRESS`.  
- Active timer is stopped automatically.  
- A `qc_records` row is created with `checkpoint = 'TECH_QC'`, `result = 'SUBMITTED'`.

**PENDING_QC → COMPLETE or IN_PROGRESS**  
`review_qc()` (`class-slate-ops-rest.php`)  
- Requires current status to be `PENDING_QC`.  
- `PASS` → `COMPLETE`. QC record updated with `result = 'PASS'`.  
- `FAIL` → `IN_PROGRESS` (job returns to the technician for rework). QC record updated with `result = 'FAIL'`.

---

## 5. Exception Statuses

These statuses are not part of the main progression. They can occur at various points in the lifecycle.

### ON_HOLD

- Triggered by `hold_job()` for any active job.
- The `delay_reason` field is set to explain the hold (e.g., `'parts'`, `'waiting_customer'`).
- Sets `priority_score = -9999`, which forces the job to the bottom of the scheduler queue.
- `unhold_job()` restores status:
  - To `SCHEDULED` if `scheduled_start` is present.
  - To `APPROVED_FOR_SCHEDULING` otherwise.
- Triggers a priority score refresh after restore.

### DELAYED (Legacy Only)

- Created only by the ClickUp importer (`class-clickup-importer.php`).
- Automatically sets `delay_reason = 'parts'`.
- Not created by any current UI flow.
- Treated as a hold-equivalent for priority scoring.

### COMPLETE_AWAITING_PICKUP (Legacy Only)

- Created only by the ClickUp importer.
- Represents jobs that were marked complete in ClickUp but had not yet been picked up by the customer.
- Not created by any current UI flow. No special runtime handling beyond display.

---

## 6. Parts Status Model

Parts readiness is tracked independently of job status in the `parts_status VARCHAR(20)` column (default: `NOT_READY`). It is managed by CS and gates the release-to-scheduler action.

### Parts Status Values

| DB Value | Meaning |
|---|---|
| `NOT_READY` | Parts not yet ordered or not arrived (default) |
| `PARTIAL` | Some parts have arrived; waiting on remainder |
| `READY` | All required parts received and available |
| `HOLD` | Parts are backordered or otherwise blocked |

### Lifecycle

- CS sets `parts_status` when creating or editing a job.
- The value is updated manually by CS as parts arrive.
- `READY` is required by the release gate before a job can be moved to `APPROVED_FOR_SCHEDULING` (see §7).

### Legacy Parts Status Mapping (ClickUp Import)

| ClickUp Value | Mapped To |
|---|---|
| `Ready` | `READY` |
| `Received` | `READY` |
| `Arrived` | `READY` |
| `Partial` | `PARTIAL` |
| `Not Ready` | `NOT_READY` |
| `Hold` | `NOT_READY` |

---

## 7. CS Readiness Gate

Before a job can enter the scheduler queue it must pass the release gate enforced by `release_job()` (`class-slate-ops-rest.php`).

### Required Conditions

All four conditions must be true unless the CS user supplies an `override_flag`:

| Condition | Failure Code |
|---|---|
| `so_number` is not empty | `missing_so_number` |
| `estimated_minutes > 0` | `missing_estimated_minutes` |
| `scope_status == 'LOCKED'` | `scope_not_locked` |
| `parts_status == 'READY'` | `parts_not_ready` |

### On Success

1. `scheduling_status` is set to `APPROVED_FOR_SCHEDULING`.
2. `ready_queue_entered_at` timestamp is recorded (used for age-based priority scoring).
3. If an override was used, the override reason and notes are stored.
4. Audit note written: "Released to scheduler".

### Scope Status

`scope_status VARCHAR(30)` defaults to `'ESTIMATING'`. The only other value used as a gate is `'LOCKED'`. Scope-lock transitions are not yet managed through the UI — this is a release gate placeholder for a future scope-lock workflow.

### Override

When `override_flag = true` is passed, all gate checks are bypassed. The override reason is recorded. This path exists for exceptional cases (e.g., a tech needs to start before parts arrive).

---

## 8. Tech Visibility Rules

### Role Capabilities

| Capability | Constant (`class-slate-ops-utils.php`) |
|---|---|
| Technician | `slate_ops_tech` |
| Supervisor | `slate_ops_supervisor` |
| Customer Service | `slate_ops_customer_service` |
| Admin | `slate_ops_admin` |

### What Techs Can Do

| Action | Allowed? |
|---|---|
| View active timer | Yes |
| Start timer on an assigned job | Yes |
| Stop timer | Yes |
| Submit job for QC | Yes |
| View supervisor correction queue | Yes (read-only) |
| Create jobs | No — CS only |
| Add or update SO number | No — CS only |
| Release job to scheduler | No — CS / Supervisor |
| Schedule / assign jobs | No — Supervisor only |
| Pass or fail a QC review | No — Supervisor only |
| Update job details (scope, estimate, parts) | No — CS / Supervisor |

### Status-Gated Actions for Techs

| Action | Requires Status |
|---|---|
| `time_start` (start timer) | Not `COMPLETE` |
| `submit_qc` | `IN_PROGRESS` |

Techs can only meaningfully act on jobs that are in `SCHEDULED` or `IN_PROGRESS`. Jobs in `PENDING_INTAKE`, `APPROVED_FOR_SCHEDULING`, `PENDING_QC`, `COMPLETE`, or `ON_HOLD` have no tech-accessible actions.

### Job List Filters Available to Techs

The job list endpoint (`GET /jobs`) accepts these filters relevant to tech workflows:

- `assigned_me` — jobs assigned to the requesting user
- `status` / `status_in` — filter by one or more statuses
- `scheduled_from` / `scheduled_to` — date-range filter
- `q` — full-text search

---

## 9. Queue Order Logic

The scheduler queue shows jobs with `status = 'APPROVED_FOR_SCHEDULING'` ordered by `priority_score DESC` (highest score appears first). Score is computed by `Slate_Priority_Service` (`class-priority-service.php`) and stored in the `priority_score` column.

### Scoring Factors

Each factor contributes additively to the final score:

| Condition | Score Delta |
|---|---|
| Job is on hold (`delay_reason` is set) | **−9999** (overrides all — sorts to bottom) |
| Due date within 3 days | +40 |
| Due date within 7 days | +25 |
| Constraint buffer < 20% of available shop hours before due date | +30 |
| Manual priority = 1 (expedite) | +20 |
| Manual priority = 2 | +10 |
| Manual priority = 3 (default) | +0 |
| Manual priority = 4 | −5 |
| Manual priority = 5 (lowest) | −10 |
| Age in ready queue > 14 days | +5 |

Due-date urgency checks both `promised_date` and `target_ship_date`; whichever is nearest is used. Only one urgency tier applies per job (the higher one).

### Score Refresh

`Slate_Priority_Service::refresh_scores()` recalculates scores for all jobs with `status IN ('PENDING_INTAKE', 'APPROVED_FOR_SCHEDULING', 'SCHEDULED')`. It is triggered automatically on hold and unhold operations. Additional trigger points (e.g., nightly cron) may be added in later phases.

### Schedulable Job Query

`Slate_Ops_Jobs::get_schedulable()` (`data/class-slate-ops-jobs.php`) returns up to 200 jobs with:

- `status = 'APPROVED_FOR_SCHEDULING'`
- Not archived
- Ordered by `priority ASC` (the integer `priority` field, 1–5, separate from `priority_score`)

---

## 10. Legacy Status Mapping

### ClickUp Importer

Jobs imported from ClickUp (`class-clickup-importer.php`) arrive with human-readable status strings. The importer translates them to internal values:

| ClickUp Status | Internal Status |
|---|---|
| `Scheduled` | `APPROVED_FOR_SCHEDULING` |
| `In Progress` | `IN_PROGRESS` |
| `Complete` | `COMPLETE` |
| `Complete - Awaiting Pickup` | `COMPLETE_AWAITING_PICKUP` |
| `On Hold` | `ON_HOLD` |
| `Delayed` | `DELAYED` (sets `delay_reason = 'parts'`) |

Imported jobs are tagged with `source = 'clickup'`. Lead tech names are stored in `schedule_notes` because ClickUp data does not include WordPress user IDs.

### Database Migrations

Two one-time migrations were applied during upgrades:

| Old Value | New Value | Context |
|---|---|---|
| `UNSCHEDULED` | `PENDING_INTAKE` | Renamed during early schema revision |
| `READY_FOR_SCHEDULING` | `APPROVED_FOR_SCHEDULING` | Renamed for clarity |

These old values no longer appear in the codebase. Any job row carrying them was updated in place by the installer migration.

---

## 11. Implementation Phases

### Phase 0 — Backbone (Shipped)

- Core DB schema: `slate_ops_jobs`, `job_reviews`, `job_assignments`, `blockers`, `schedule_slots`, `eod_reports`, `qc_records`.
- Priority scoring service (`class-priority-service.php`).
- Capacity and buffer services (`class-capacity-service.php`, `class-buffer-service.php`).
- ClickUp import CLI tool.
- Basic job CRUD, time tracking, QC submit/review.
- Front-end scheduler bootstrap (`scheduler-phase0.js`).

### Phase 1 — Status, Parts, and Queue (This Document)

- Canonical status model documented and stable.
- Parts status (`NOT_READY` / `PARTIAL` / `READY` / `HOLD`) as a first-class field.
- CS release gate enforcing SO number, estimate, scope lock, and parts readiness.
- Priority score algorithm with urgency, constraint-buffer, manual-priority, and age factors.
- Tech role visibility and action restrictions enforced at the REST layer.
- Dealer portal status rollup (`waiting` / `in_process` / `complete`).

### Phase 2 — Scope Lock Workflow (Planned)

- UI for CS to transition `scope_status` from `ESTIMATING` to `LOCKED`.
- Scope-lock is currently a release-gate condition but has no active transition endpoint; CS cannot lock scope from the UI today.
- Phase 2 will close this gap.

### Phase 3 — Scheduler Configuration (Planned)

- Comment in `class-slate-ops-rest.php` (near `release_job`) notes:  
  *"Phase 1+: scheduler can be configured to only show jobs with scheduling_status = APPROVED_FOR_SCHEDULING"*  
- Will introduce configurable scheduler modes and multi-work-center views built on the existing `schedule_slots` table.

### Phase 4 — Time Segment Approval (Partially Implemented)

- `approval_status` column on time segments (values: `approved`, `pending`, `voided`).
- Supervisor correction queue already visible to techs (read-only).
- Full approval workflow (supervisor accept / reject individual segments) is in progress.

---

*End of document.*
