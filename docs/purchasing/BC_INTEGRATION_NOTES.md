# Slate OPS Purchasing - Business Central Integration Notes

## Purpose

This document captures the Business Central integration plan for the Slate OPS Purchasing page.

The locked project direction is that Slate WordPress systems do not talk directly to Business Central in V1. Business Central access is handled through Power Automate flows.

The Purchasing page should first work as a WordPress-backed internal purchasing workflow. Power Automate and Business Central should be added after the local data model, purchase request flow, status flow, and activity logging are stable.

## Current Integration Position

Business Central status for MVP:

```text
Not Connected
```

API Status tab should show:

- Business Central: Not Connected
- Power Automate: Not Configured
- Flow URLs: Not Configured
- Flow Secrets: Not Configured
- Last Sync: Never, or last local placeholder sync log
- WP Purchasing Tables: Available

Sync buttons should remain disabled until the Power Automate integration phase.

## Locked Architecture Direction

The integration layer is Power Automate.

```text
Slate Ops / Dealer Portal -> signed event -> Power Automate -> Business Central
Business Central / Power Automate -> signed callback -> WordPress inbox
```

The WordPress portal stores:

- Power Automate trigger URLs
- HMAC shared secrets
- Outbox events
- Inbox events
- Local workflow state

Power Automate stores or owns:

- Business Central connector/auth
- BC tenant/environment/company selection
- BC API actions
- BC retry behavior after PA accepts the event

Business Central stores:

- Commercial purchasing/order records after writeback
- Items
- Vendors
- Purchase documents
- Availability/planning data

## Important Guardrail

Do not put these in Slate Ops code or repo:

- Tenant ID
- Client ID
- Client secret
- BC company ID
- BC environment name as a hardcoded value
- BC base API URL as executable config
- OAuth token logic
- Direct BC API client

For V1, the WordPress side should not need direct BC connection details. It needs only Power Automate flow URLs and HMAC secrets.

## Known Project Context

From prior Slate BC planning:

- Slate uses Microsoft Dynamics 365 Business Central.
- Simcrest is supporting BC implementation.
- Current operational process still uses manual BC review and Excel connector workflows.
- Purchasing planning has centered around Planning Worksheet, Requisition Worksheet, Prod. Order - Shortage List, and Item Availability by Event.
- Power Automate is the preferred integration layer for V1.
- Portal/Slate Ops emits signed events to Power Automate.
- Power Automate talks to Business Central.
- Power Automate posts signed callbacks back to WordPress.
- Standard BC connector/actions are preferred for V1.
- Custom BC API pages or AL work are deferred until needed.

## Event Model To Reuse

The Dealer Portal integration work already locked an event-driven model. Purchasing should reuse the same pattern where practical.

Required event envelope:

```json
{
  "eventId": "uuid-v4",
  "eventType": "purchase.request.approved",
  "eventVersion": "1.0",
  "occurredAt": "2026-04-21T18:30:00Z",
  "sourceSystem": "slate_ops",
  "payload": {}
}
```

Required event rules:

- `eventId` is the sole idempotency key.
- `eventVersion` is required.
- `occurredAt` is UTC ISO-8601 with Z suffix.
- Payload is immutable after enqueue.
- Retries resend the original stored body.
- Duplicate inbound `eventId` is a no-op.
- Outbound events are stored before first send attempt.

## Security Model To Reuse

Use the locked HMAC model:

Required headers:

```text
X-Slate-Signature: sha256=<hex>
X-Slate-Timestamp: <unix_seconds>
X-Slate-Event-Type: <event.type>
X-Slate-Flow-Id: <flow_id>
Content-Type: application/json
```

Rules:

- Signed string = `timestamp + "." + raw_body`
- Algorithm = HMAC-SHA256
- Compare with `hash_equals`
- Replay window = 300 seconds
- Reject missing timestamp, signature, event type, flow ID, or raw body
- Reject flow/event mismatch
- Never log secrets, signatures, or raw HMAC input

## Purchasing Event Candidates

These are not approved for build yet. They are candidate events for the purchasing integration phase.

Outbound from Slate Ops to Power Automate:

```text
purchase.request.approved
purchase.request.cancelled
purchase.order.requested
purchase.vendor.sync.requested
purchase.demand.sync.requested
```

Inbound from Power Automate to Slate Ops:

```text
bc.purchaseOrder.created
bc.purchaseOrder.failed
bc.purchaseOrder.updated
bc.vendor.synced
bc.item.synced
bc.demand.synced
bc.sync.failed
```

## Correlation Model

Purchasing should lock correlation before coding.

Recommended:

```text
correlation_id = purchaseRequestId for purchase request events
correlation_id = bcPurchaseOrderNo for BC PO callbacks after PO creation
correlation_id = TEST-{timestamp}-{random} for synthetic test events
```

Every event should carry at least one of:

- purchaseRequestId
- portalQuoteId, if generated from a quote/order flow
- bcPurchaseOrderNo
- salesOrderNo, if tied to a job/order

## Retry Ownership

Recommended split:

- WordPress retries only delivery to Power Automate.
- Power Automate owns downstream BC retries.
- If PA returns 2xx, WordPress treats the event as delivered.
- Terminal BC failures must come back through signed callbacks.

Failure classification:

- Missing flow URL or secret = terminal `flow_not_configured`
- WP HTTP timeout / DNS / TLS / connection failure = transient
- HTTP 2xx = sent
- HTTP 429 = transient, respect Retry-After
- HTTP 4xx except 429 = terminal
- HTTP 5xx = transient

## Likely Purchasing Data Needed From BC

Power Automate will need access to these BC areas. Slate Ops should not call them directly in V1.

### Item Data

- Item number
- Description
- Vendor item number
- Preferred vendor
- Inventory quantity
- Quantity on purchase order
- Quantity on sales order
- Quantity on production order
- Reorder point
- Reorder quantity
- Safety stock quantity
- Lead time calculation
- Unit cost
- Base unit of measure
- Blocked status

### Vendor Data

- Vendor number
- Vendor name
- Contact name
- Email
- Phone
- Payment terms
- Lead time
- Minimum order amount
- Freight terms
- Blocked status

### Demand Data

Confirm which BC source drives demand:

- Requisition Worksheet, preferred first candidate for purchase suggestions
- Planning Worksheet, secondary candidate for broader MRP/production planning
- Prod. Order - Shortage List, useful for immediate production shortages
- Item Availability by Event, useful for visibility but not a purchasing engine by itself
- Custom API page, likely later if standard connector cannot expose the right shape

### Purchase Order Data

- PO number
- Vendor number
- Status
- Order date
- Expected receipt date
- Lines
- Quantities
- Unit costs
- Line totals
- Received quantities
- Outstanding quantities

## Key Mapping Questions

Before coding BC/PA purchasing integration, answer these:

1. What is the source of purchasing demand?
2. Can Power Automate access that source through standard BC connector actions?
3. Do we need a custom BC API page for worksheet/demand lines?
4. Should Slate Ops create BC purchase orders, purchase quotes, or requisition lines?
5. Who approves a PR before writeback?
6. What exact event should trigger writeback?
7. How should duplicate PR writeback be prevented?
8. How should item/vendor mismatches be flagged?
9. Should BC become the final purchase document source of truth after writeback?
10. How should local records reconcile after BC updates?

## Suggested Integration Phases

### PA/BC Phase A - Readiness

- Add Power Automate settings section.
- Store flow URLs and write-only HMAC secrets.
- Add connection/test event buttons.
- Add signed inbound receiver if not already available.
- Do not sync operational data yet.

### PA/BC Phase B - Read-Only Sync

- Pull vendors through PA.
- Pull items through PA.
- Pull open purchase orders through PA.
- Pull inventory availability through PA.
- Write sync results to local WP tables.
- Show last sync time and errors.

### PA/BC Phase C - Demand Sync

- Pull demand from confirmed BC source through PA.
- Normalize into `slate_purchase_demand`.
- Flag invalid rows.
- Keep local PR creation unchanged.

### PA/BC Phase D - Controlled Writeback

- Allow approved PRs to emit event to PA.
- PA creates BC purchasing document.
- Store BC document number on local PR.
- Log callback response.
- Prevent repeat writeback with event ID and local sync status.

### PA/BC Phase E - Reconciliation

- Pull BC PO status updates through PA callbacks or sync flow.
- Update local Open POs.
- Update local PRs linked to BC documents.
- Display sync conflicts.

## Error Handling Requirements

Integration must handle:

- Missing PA flow config
- Bad HMAC signature
- Replay attempt
- Flow/event mismatch
- Power Automate failure
- BC connector authentication failure inside PA
- Missing BC company/environment inside PA
- Missing item
- Missing vendor
- Blocked item
- Blocked vendor
- Invalid unit of measure
- Duplicate PO attempt
- Permission failure
- Rate limit
- Timeout
- Partial sync failure

Errors should be logged to `slate_purchasing_sync_log` and shown in the API Status tab.

## Data Ownership After Connection

Before writeback:

```text
Slate Ops local database = working layer
Power Automate = integration layer
Business Central = reference layer
```

After writeback:

```text
Business Central = purchasing document source of truth
Slate Ops = workflow and visibility layer
Power Automate = integration layer
```

## Required Fields To Add Later

Purchase request header should eventually support:

- `bc_document_type`
- `bc_document_number`
- `bc_sync_status`
- `bc_last_synced_at`
- `bc_last_error`
- `pa_flow_id`
- `pa_last_event_id`

Purchase request lines should eventually support:

- `bc_line_number`
- `bc_item_id`
- `bc_vendor_item_no`
- `bc_unit_of_measure`

Demand rows should eventually support:

- `bc_source_type`
- `bc_source_id`
- `bc_source_line_no`
- `bc_item_id`
- `bc_vendor_id`

## Information Needed From Simcrest / BC Team

Ask for these, but do not store secrets in the repo:

- Which BC source should drive purchasing demand
- Whether PA can access that source with standard BC connector actions
- Sandbox company name
- Dedicated service account decision for Power Automate connector
- Item fields available through PA
- Vendor fields available through PA
- Purchase order fields available through PA
- Required fields for purchase order creation
- Error response examples
- Any required custom API page or extension work

## Build Rule

Business Central/Power Automate work must not begin until there is a separate approved task that references this document and clearly states which PA/BC phase is being built.
