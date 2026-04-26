# Slate OPS Purchasing - Source of Truth

## Purpose

The Slate OPS Purchasing page is the internal purchasing workspace for reviewing demand, creating purchase requests, tracking vendors, viewing open purchase orders, and preparing for future Business Central integration.

This page is not a live Business Central purchasing system yet.

The first goal is to make the existing Purchasing page functional inside WordPress using local Slate Ops data. Business Central stays unplugged until the workflow is stable.

## Current State

Repo:

```text
ryobi1-slate/Slate-Ops
```

Current route:

```text
/ops/purchasing
```

Existing frontend owner:

```text
assets/js/purchasing.js
```

Current behavior:

- Page already exists.
- Uses vanilla JavaScript.
- Owns `#ops-view`.
- Loaded only on the Purchasing route.
- Uses hardcoded mock arrays:
  - `DEMAND`
  - `REQUESTS`
  - `VENDORS`
  - `OPEN_POS`
  - `ACTIVITY`
- Has no REST API calls.
- Has no database persistence.
- Has no purchase request creation.
- Has no Business Central connection.
- Has disabled action buttons.
- Shows Preview / Manual Trigger Mode.

## Locked Design Direction

Do not rebuild the page.

Keep:

- Existing Slate Ops shell.
- Existing Purchasing tabs.
- Existing card/table layout.
- Existing vanilla JS approach.
- Existing Business Central unplugged posture.
- Existing clean Slate visual direction.

Tabs stay:

1. Overview
2. Demand
3. Purchase Requests
4. Vendors
5. Open POs
6. API Status

## Non-Goals

Do not:

- Convert the page to React.
- Rebuild the Ops shell.
- Redesign the page.
- Connect to Business Central yet.
- Create real Business Central POs.
- Change Dealer Portal.
- Change Tech, CS, Executive, Schedule, Admin, or Settings.
- Change importers.
- Add checkout, cart, tax, shipping, or retail language.
- Add complicated purchasing automation before the manual workflow works.

## Core Workflow

### MVP Workflow

1. User opens `/ops/purchasing`.
2. Page loads purchasing data from WordPress tables.
3. User reviews demand.
4. User selects eligible demand lines.
5. User creates purchase requests grouped by vendor.
6. Purchase requests are saved in WordPress.
7. User reviews PRs.
8. User updates PR status.
9. User can mark PR as ordered.
10. API Status continues to show Business Central as not connected.

## Data Ownership

Phase 1 data source:

```text
WordPress database tables inside Slate Ops
```

Future data source:

```text
Business Central API
```

Business Central is not the source of truth until the connection and sync rules are defined.

For now, WordPress is the working local purchasing layer.

## Status Rules

Purchase request statuses:

```text
draft
review
approved
held
ordered
cancelled
```

Status movement:

- `draft` can move to `review`, `held`, or `cancelled`.
- `review` can move to `approved`, `held`, or `cancelled`.
- `approved` can move to `ordered`, `held`, or `cancelled`.
- `held` can move back to `review` or to `cancelled`.
- `ordered` is final for MVP.
- `cancelled` is final for MVP.

No Business Central PO is created from status changes in MVP.

## Security Rules

Backend work must follow:

- Logged-in users only.
- Use existing Slate Ops permission pattern.
- If no purchasing permission exists, use `manage_options` temporarily.
- REST nonce required.
- Sanitize all request input.
- Use `$wpdb->prepare()` for variable SQL.
- Return `WP_Error` for invalid requests.
- Do not expose raw database errors to users.
- Keep frontend errors clear and useful.

## Frontend Rules

File:

```text
assets/js/purchasing.js
```

Keep:

- Vanilla JS.
- Existing tab structure.
- Existing render pattern where practical.
- Existing Slate visual style.

Add:

- `apiFetch` helper.
- Loading states.
- Empty states.
- Error banner.
- Selected demand state.
- Filters.
- Action handlers.
- Success messages.
- Persistent data refresh after writes.

Suggested state shape:

```js
var state = {
  tab: 'overview',
  loading: false,
  error: null,
  notice: null,
  summary: null,
  demand: [],
  requests: [],
  vendors: [],
  openPos: [],
  activity: [],
  status: null,
  selectedDemandIds: [],
  filters: {
    search: '',
    urgency: 'all',
    vendor: 'all'
  },
  activeRequestId: null
};
```

## Acceptance Criteria

MVP is complete when:

- `/ops/purchasing` still visually matches the current page.
- Data loads from WordPress tables.
- Mock arrays are no longer the primary data source.
- Demand lines can be selected.
- Selected demand lines can create purchase requests.
- PRs persist after refresh.
- PR status changes persist after refresh.
- Vendors load from WordPress.
- Open POs load from WordPress.
- API Status shows BC is not connected.
- No console errors.
- No PHP warnings.
- No jQuery.
- Unauthorized REST calls fail cleanly.
- Existing Ops pages are untouched.
