# BC item load sheet

Purpose: classify purchase-relevant Business Central items and prepare an import-ready parameter sheet for Phase 1 purchasing visibility.

This file is the working spec for the import sheet. The actual Sheet1 source data was not present in the repo when this was created, so the CSV template is intentionally header-only until the 48 source parts are provided.

Template:

```text
docs/purchasing/bc-item-load-sheet-template.csv
```

## Required load columns

| Column | Use |
| --- | --- |
| Item No. | Business Central item number. Required. |
| Description | Human-readable item name. |
| Type | Must be `Inventory` for purchasable stock/job parts. |
| Blocked | Excluded or inactive items should be blocked or non-inventory. |
| Item Category Code | Bucket/filter control. Use category to distinguish job-driven, stocked, and excluded items. |
| Proposed bucket | Working classification: `A - Job-driven`, `B - Stocked`, or `Exclude`. |
| Reordering Policy | BC policy. Use `Lot-for-Lot` for Bucket A, `Fixed Reorder Qty.` or `Maximum Qty.` for Bucket B. |
| Vendor No. | Preferred vendor for routing purchase requests. |
| Vendor Item No. | Vendor part number where available. |
| Base Unit of Measure | BC base unit for quantities. |
| Lead Time Calculation | Required for planning timing. |
| Minimum Order Quantity | Required where vendor pack/min rules apply. |
| Order Multiple | Required where vendor pack/multiple rules apply. |
| Safety Stock Quantity | Optional for Bucket A, expected for Bucket B. |
| Reorder Point | Bucket B trigger threshold. |
| Reorder Quantity | Bucket B fixed reorder quantity. |
| Maximum Inventory | Bucket B max target when using `Maximum Qty.`. |
| Unit Cost | Reference only for review and PO visibility. |
| Avg Monthly Usage | Sheet1 input/calculated usage. |
| Daily Usage | Sheet1-derived usage for auditability. |
| Review Status | `Needs source data`, `Ready for BC load`, or `Hold`. |
| Review Notes | Free-text exception notes. |

## Bucket rules

Bucket A, job-driven:

- `Type` must be `Inventory`.
- `Reordering Policy` must be `Lot-for-Lot`.
- Populate `Lead Time Calculation`, `Vendor No.`, `Vendor Item No.`, `Minimum Order Quantity`, and `Order Multiple`.
- `Safety Stock Quantity` may be populated only when a deliberate buffer is needed.
- Leave Bucket B consumption fields blank unless BC requires a value.

Bucket B, stocked:

- `Type` must be `Inventory`.
- `Reordering Policy` should normally be `Fixed Reorder Qty.`.
- Use `Maximum Qty.` only for lumpy usage where a max target is more useful than a fixed reorder quantity.
- Populate `Reorder Point`, `Reorder Quantity` or `Maximum Inventory`, `Safety Stock Quantity`, `Lead Time Calculation`, and `Vendor No.`.
- Sheet1 consumption math maps to `Avg Monthly Usage`, `Daily Usage`, `Safety Stock Quantity`, `Reorder Point`, `Reorder Quantity`, and `Maximum Inventory`.

Exclude:

- Use for labor, sublet, VINs, shop supplies, and other non-purchase inventory records.
- Prefer `Type` other than `Inventory` or blocked-from-purchasing behavior in BC.
- Do not assign a purchasing reorder policy unless BC requires one for the record type.

## Controls before import

- No inventory item should be active with blank `Reordering Policy`.
- No purchase-relevant inventory item should have blank `Vendor No.`.
- Bucket B rows must have a `Reorder Point` and either `Reorder Quantity` or `Maximum Inventory`.
- Bucket A rows must not use a stocked policy.
- Do not import portal-only fields into BC. `Proposed bucket`, `Avg Monthly Usage`, `Daily Usage`, `Review Status`, and `Review Notes` are review/audit columns unless a BC import package explicitly maps them.

## Missing input

To populate the 48 real rows, provide the Sheet1 export with at least:

- Item number
- Description
- Average monthly usage or source consumption values
- Existing reorder point/reorder quantity/max/safety stock values, if already calculated
- Vendor number or vendor name
- Lead time, minimum order quantity, and order multiple where available
