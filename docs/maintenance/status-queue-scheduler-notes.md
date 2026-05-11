# Status, queue, and scheduler notes

Last reviewed: 2026-05-11

This note documents known differences between active job status sets, the CS Workspace queue, scheduler writes, and Tech ordering. It is maintenance context only and does not change behavior.

## Canonical status source

`includes/class-slate-ops-statuses.php` is the canonical status definition file. It defines current statuses, legacy aliases, labels, and allowed transitions.

Current active statuses include:

- `INTAKE`
- `NEEDS_SO`
- `READY_FOR_BUILD`
- `SCHEDULED`
- `IN_PROGRESS`
- `BLOCKED`
- `QC`
- `ON_HOLD`
- Legacy aliases: `QUEUED`, `PENDING_QC`, `DELAYED`

`COMPLETE` and `CANCELLED` are not active statuses.

## CS Workspace queue status scope

`/cs/queue` intentionally has its own active queue scope in `Slate_Ops_REST::cs_queue_active_statuses()`.

The CS queue currently includes:

- `INTAKE`
- `NEEDS_SO`
- `READY_FOR_BUILD`
- `SCHEDULED`
- `IN_PROGRESS`
- `BLOCKED`
- `QC`
- `PENDING_QC`

Known difference: `ON_HOLD` is active globally but is not currently included in `/cs/queue`.

## Queue fields and write paths

The CS Workspace tab keeps the visible work order in these job fields:

- `queue_order`
- `queue_visible`
- `queue_note`
- `queue_updated_at`
- `queue_updated_by`
- `assigned_user_id` when reassignment is saved through the queue endpoint

Queue-specific saves use `POST /cs/queue`. General job detail edits use existing `/jobs` and `/jobs/{id}` endpoints. This means `assigned_user_id` has more than one valid write path.

## Scheduler writes

Scheduler handlers can write scheduling fields and status fields independently:

- `schedule_job()` builds scheduler updates and currently writes `status = QUEUED`, which is a legacy alias normalized/displayed as Scheduled elsewhere.
- `release_job()` writes `scheduling_status = READY_FOR_BUILD` and `ready_queue_entered_at`, but does not necessarily change primary `status`.
- `hold_job()` writes primary `status = ON_HOLD`.
- `unhold_job()` returns the job to `SCHEDULED` when it has `scheduled_start`, otherwise `READY_FOR_BUILD`.

Known difference: `status` and `scheduling_status` are related but not identical. Some list filters accept either field for readiness checks.

## Tech ordering risk

The CS Workspace queue reads `/cs/queue`, grouped by assigned tech and sorted by queue fields. The legacy React Tech route reads the general `/jobs` endpoint.

Known difference: `/cs/queue` ordering prioritizes assigned user and `queue_order`; `/jobs` orders by `queue_priority` and `updated_at` unless the React layer applies additional ordering. Future Tech queue changes should confirm the final sort at the UI layer before changing REST behavior.

## Cleanup guidance

- Keep `/cs/queue` endpoint behavior stable unless a separate behavior change is approved.
- Treat `.cs-beta` naming as internal implementation detail for now.
- When changing status labels or queue inclusion rules, compare `Slate_Ops_Statuses::active()`, `cs_queue_active_statuses()`, scheduler handlers, and Tech route ordering together.
