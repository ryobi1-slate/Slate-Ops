# Slate OPS Purchasing - Phase Plan

## Phase 0 - Baseline

Goal:

Lock current page behavior.

Tasks:

- Confirm `/ops/purchasing` loads.
- Confirm `assets/js/purchasing.js` owns the page.
- Capture current UI state.
- Do not redesign.

Output:

- Stable baseline for all future work.

---

## Phase 1 - UX Enhancement + Data Foundation

Goal:

Introduce WordPress-backed data while improving usability.

Tasks:

- Add DB tables.
- Add REST endpoints (read-only).
- Seed data if empty.
- Replace mock arrays with REST calls.
- Add loading, empty, and error states.
- Add demand filters (search, urgency, vendor).

Output:

- Page loads real data from WP.
- UI feels closer to Claude design.

---

## Phase 2 - Demand to Purchase Request

Goal:

Enable core purchasing action.

Tasks:

- Add selectable demand rows.
- Disable invalid rows.
- Add Create Purchase Request action.
- Group lines by vendor.
- Save PRs.
- Log activity.

Output:

- Demand can generate purchase requests.

---

## Phase 3 - Purchase Request Workflow

Goal:

Manage PR lifecycle.

Tasks:

- Add PR detail drawer.
- Add status actions.
- Persist status changes.
- Add notes.
- Add activity logging.

Output:

- Full internal PR workflow.

---

## Phase 4 - Vendor + PO Readiness

Goal:

Improve support tabs.

Tasks:

- Expand vendor data.
- Add vendor warnings.
- Improve Open POs (read-only).
- Add detail views.

Output:

- Supporting data is useful and accurate.

---

## Phase 5 - Business Central Preparation

Goal:

Prepare system for integration.

Tasks:

- Add sync log.
- Add mapping fields.
- Add API status data.
- Keep BC disconnected.

Output:

- System ready for BC integration.

---

## Phase 6 - Business Central Integration

Goal:

Connect purchasing workflow to BC.

Tasks:

- Pull demand.
- Pull vendors.
- Pull open POs.
- Push approved PRs.

Output:

- Fully connected purchasing workflow.
