# CS / Tech launch checklist - 2026-05-11

## Launch scope

This launch is limited to the Slate Ops CS and Tech workflows.

Included:

- CS Dashboard at `/ops/cs-dashboard`
- Job Queue control
- Manual CS job creation
- CS job detail edits
- CS status movement
- CS tech assignment through the Job Queue
- Tech workspace at `/ops/tech`
- Monitor route if needed by shop leadership

Deferred:

- Scheduler page and capacity workflow
- Purchasing workflow
- Trail Run
- Business Central purchasing automation
- Any checkout, payment, shipping, or retail flow

## Required plugins on staging and production

- Slate Dealer Portal
- Slate Ops
- Slate Ops Shop Monitor, if the monitor route is part of launch
- Slate Pricing Core, if CS job context depends on shared pricing data

Do not install Slate Trail Run for this launch.

## Route smoke test

Verify these routes after activating Slate Ops and saving permalinks:

- `/ops/cs-dashboard`
- `/ops/cs-dashboard#queue`
- `/ops/tech`
- `/slate-ops-monitor/`, if monitor is enabled

Expected result:

- CS users can access `/ops/cs-dashboard`.
- Tech users can access `/ops/tech`.
- CS and tech users do not see `Schedule` in the sidebar.
- Direct non-admin access to `/ops/schedule` is blocked while Scheduler is deferred.

## CS acceptance test

- Create a manual job from Job Queue.
- Confirm the new job appears in the unassigned CS queue.
- Assign the job to a tech.
- Set queue order.
- Save queue changes.
- Reload the page and confirm assignment and queue order persisted.
- Edit customer, dealer, VIN, notes, parts status, estimated hours, and requested date.
- Move status through the launch-supported statuses.
- Confirm `IN_PROGRESS` is rejected when no tech is assigned.
- Confirm blocked, hold, and cancelled statuses require the appropriate reason and note.

## Tech acceptance test

- Log in as a tech.
- Confirm assigned work is visible on `/ops/tech`.
- Confirm queue order affects the current / next / up-next work shown to the tech.
- Start work on an assigned job.
- Stop work and confirm time is recorded.
- Submit QC when the job is ready.
- Confirm tech users cannot manage the full CS queue.

## Production promotion notes

- Promote from a verified staging commit only.
- Do not merge the old `production` branch blindly into `main`.
- Back up the production database before activation.
- Activate Slate Ops on production, then save permalinks.
- Assign production users to the canonical Slate Ops roles.
- Keep Scheduler disabled until the scheduling workflow is separately tested.
