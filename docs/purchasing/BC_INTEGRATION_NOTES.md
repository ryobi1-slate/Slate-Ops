# Slate OPS Purchasing - Business Central Integration Notes

## Purpose

This document captures the Business Central integration plan for the Slate OPS Purchasing page.

Business Central integration is planned, but it is not part of the first local workflow build.

The Purchasing page should first work as a WordPress-backed internal purchasing workflow. Business Central should be added after the local data model, purchase request flow, status flow, and activity logging are stable.

## Current Integration Position

Business Central status for MVP:

```text
Not Connected
```

API Status tab should show:

- Business Central: Not Connected
- Credentials: Not Configured
- Environment: Not Configured
- Company: Not Configured
- Last Sync: Never, or last local placeholder sync log
- WP Purchasing Tables: Available

Sync buttons should remain disabled until the BC integration phase.

## Integration Guardrails

Do not connect to Business Central during local MVP phases.

Do not:

- Pull live demand yet.
- Pull live item availability yet.
- Pull live vendor records yet.
- Push purchase orders yet.
- Push purchase requests yet.
- Store API secrets in the repo.
- Hardcode tenant IDs, client IDs, secrets, company IDs, or environment names.

All secrets must be stored in WordPress options, server environment variables, or another secure storage method. They must never be committed.

## Likely BC Data Needed

To tie Purchasing into Business Central, gather these items:

### Connection Details

- Tenant ID
- Environment name, such as Sandbox or Production
- Company ID
- Base API URL
- API version
- Authentication method
- Client ID
- Client secret handling plan
- Token URL
- Required scopes

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

- Planning Worksheet
- Requisition Worksheet
- Item Availability by Event
- Sales Orders
- Production Orders
- Assembly Orders
- Custom API page
- Existing report or query

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

Before coding BC integration, answer these:

1. What is the source of demand?
2. Should Slate Ops create BC purchase orders or only draft internal PRs?
3. Who approves a PR before BC writeback?
4. What BC document type should be created?
5. Should purchase requests map to purchase orders, purchase quotes, or requisition lines?
6. How should rejected BC writes be handled?
7. How should duplicate PRs be prevented?
8. How should item/vendor mismatches be flagged?
9. Should BC remain the final source of truth after writeback?
10. How should local records reconcile after BC updates?

## Suggested Integration Phases

### BC Phase A - Readiness

- Add API settings screen or settings section.
- Add secure credential storage.
- Add connection test endpoint.
- Show connection result on API Status tab.
- Do not sync operational data yet.

### BC Phase B - Read-Only Sync

- Pull vendors.
- Pull items.
- Pull open purchase orders.
- Pull inventory availability.
- Write sync results to local WP tables.
- Show last sync time and errors.

### BC Phase C - Demand Sync

- Pull demand from confirmed BC source.
- Normalize into `slate_purchase_demand`.
- Flag invalid rows.
- Keep local PR creation unchanged.

### BC Phase D - Controlled Writeback

- Allow approved PRs to create BC purchasing documents.
- Store BC document number on the local PR.
- Log writeback response.
- Prevent repeat writeback.
- Keep manual override path.

### BC Phase E - Reconciliation

- Pull BC PO status updates.
- Update local Open POs.
- Update local PRs linked to BC documents.
- Display sync conflicts.

## Error Handling Requirements

BC integration must handle:

- Authentication failure
- Expired token
- Missing company ID
- Missing environment
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

## Data Ownership After BC Connection

Before writeback:

```text
Slate Ops local database = working layer
Business Central = reference layer
```

After writeback:

```text
Business Central = purchasing document source of truth
Slate Ops = workflow and visibility layer
```

## Required Fields to Add Later

Purchase request header should eventually support:

- `bc_document_type`
- `bc_document_number`
- `bc_company_id`
- `bc_sync_status`
- `bc_last_synced_at`
- `bc_last_error`

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

## Information Needed From Business Central

Use this checklist when collecting API info:

- BC tenant ID
- BC environment name
- BC company ID
- OAuth app registration details
- API permissions/scopes
- Test user/service account plan
- Sandbox URL
- Production URL
- Item API endpoint
- Vendor API endpoint
- Purchase order API endpoint
- Demand/planning source endpoint
- Sample item response
- Sample vendor response
- Sample purchase order response
- Sample demand/planning response
- Rules for creating draft purchase orders
- Required fields for purchase order creation
- Error response examples

## Build Rule

Business Central work must not begin until there is a separate approved task that references this document and clearly states which BC phase is being built.
