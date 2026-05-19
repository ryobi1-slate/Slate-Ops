# Power Automate Sync Items callback

This document describes the Purchasing item sync flow between Slate Ops, Power Automate, and Business Central.

The callback endpoint is:

```text
POST /wp-json/slate-ops/v1/purchasing/integration/callback
```

## Security contract

Required headers:

```text
Content-Type: application/json
X-Slate-Signature: sha256=<lowercase_hex_hmac>
X-Slate-Timestamp: <unix_seconds>
X-Slate-Event-Type: bc.item.synced
X-Slate-Flow-Id: <power_automate_run_or_event_id>
```

`X-Slate-Signature` may also be sent as:

```text
X-Slate-Signature: sha256-base64=<base64_hmac>
```

Power Automate cloud flows that cannot compute HMAC-SHA256 may instead send:

```text
X-Slate-Callback-Secret: <shared_hmac_secret>
```

The callback secret fallback still requires `X-Slate-Timestamp` and the timestamp must be within the same 300-second window.

Both signature formats use the same signed string:

```text
<X-Slate-Timestamp> + "." + <raw_request_body>
```

Algorithm:

```text
HMAC-SHA256
```

The timestamp must be within 300 seconds of the WordPress server clock.

The body must be signed exactly as posted. Do not sign parsed JSON, formatted JSON, or a different Compose output than the body used by the final HTTP callback action.

## Sync Items flow

Recommended flow name:

```text
Slate Ops - Sync Items
```

Required Power Automate actions:

1. Trigger: When an HTTP request is received
2. Parse JSON: Parse the Slate Ops request
3. Business Central: Find records (V3) for Items
4. Data operation: Select normalized item fields
5. Compose: Create callback event id
6. Compose: Create callback timestamp in Unix seconds
7. Compose: Create callback body
8. Compose or signing action: Create signed string
9. HMAC action: Compute HMAC-SHA256 over the signed string with the shared secret
10. HTTP: POST the callback to Slate Ops
11. Response: Return `202 Accepted` to the original Slate Ops sync request

Pagination control:

- If the BC item action is limited to 1,000 records, confirm that limit is an intentional BC filter, not the connector page size.
- If it is connector pagination, enable pagination or loop through all pages before composing the callback body.
- The callback should represent the full intended item population for purchasing visibility.

Failure path:

1. Compose a `bc.sync.failed` callback body
2. Sign it with the same HMAC contract
3. HTTP POST it to the same callback endpoint

## Incoming Slate Ops request

Slate Ops sends the item sync request to the configured Item Sync Flow URL.

Expected event type:

```text
purchase.item.sync.requested
```

The request body shape is:

```json
{
  "eventId": "2e57b39d-7f50-45b1-ae9c-ff4d54777629",
  "eventType": "purchase.item.sync.requested",
  "eventVersion": "1.0",
  "occurredAt": "2026-05-14T18:00:00Z",
  "sourceSystem": "slate_ops",
  "payload": {
    "feed": "item"
  }
}
```

If a Purchasing HMAC secret is configured in Slate Ops, the outbound request includes `X-Slate-Signature` and `X-Slate-Timestamp`. In sandbox setup, read-only sync requests may be unsigned while the HMAC secret is blank.

## Business Central item mapping

The callback payload should normalize Business Central item records into the fields Slate Ops accepts:

```json
{
  "itemNo": "1000",
  "description": "Front seat cover",
  "type": "Inventory",
  "blocked": false,
  "itemCategoryCode": "STOCKED",
  "vendorNo": "VEND-001",
  "vendorItemNo": "FS-COVER-001",
  "baseUnitOfMeasure": "EA",
  "onHand": 12,
  "qtyOnPurchaseOrder": 4,
  "qtyOnSalesOrder": 0,
  "qtyOnComponentLines": 3,
  "reorderingPolicy": "Fixed Reorder Qty.",
  "reorderPoint": 4,
  "reorderQuantity": 8,
  "maximumInventory": 0,
  "safetyStockQuantity": 2,
  "leadTimeCalculation": "2W",
  "minimumOrderQuantity": 1,
  "orderMultiple": 1,
  "unitCost": 42.75
}
```

`itemNo` is preferred for each item. Slate Ops also accepts the Business Central v2 `number` field as the item number, and `displayName` as a fallback description, so the item sync flow can send the raw BC field names.

`reorderingPolicy` is required for Phase 1 demand logic. Slate Ops branches as follows:

- `Lot-for-Lot`: projected available = `onHand + qtyOnPurchaseOrder - qtyOnSalesOrder - qtyOnComponentLines - safetyStockQuantity`; flagged when projected available is below zero.
- `Fixed Reorder Qty.` or `Maximum Qty.`: available = `onHand + qtyOnPurchaseOrder`; flagged when available is less than or equal to `reorderPoint`.

Do not send `forecastedNeed` or `suggestedOrder` for Phase 1. Suggested order quantity belongs to the Phase 2 Requisition Worksheet feed.

## Exact callback body

For item sync, `X-Slate-Event-Type` must be:

```text
bc.item.synced
```

Callback body:

```json
{
  "eventId": "PA-RUN-ID-OR-GUID",
  "eventType": "bc.item.synced",
  "eventVersion": "1.0",
  "occurredAt": "2026-05-14T18:01:00Z",
  "sourceSystem": "business_central",
  "payload": {
    "items": [
      {
        "itemNo": "1000",
        "description": "Front seat cover",
        "type": "Inventory",
        "blocked": false,
        "itemCategoryCode": "STOCKED",
        "vendorNo": "VEND-001",
        "vendorItemNo": "FS-COVER-001",
        "baseUnitOfMeasure": "EA",
        "onHand": 12,
        "qtyOnPurchaseOrder": 4,
        "qtyOnSalesOrder": 0,
        "qtyOnComponentLines": 3,
        "reorderingPolicy": "Fixed Reorder Qty.",
        "reorderPoint": 4,
        "reorderQuantity": 8,
        "maximumInventory": 0,
        "safetyStockQuantity": 2,
        "leadTimeCalculation": "2W",
        "minimumOrderQuantity": 1,
        "orderMultiple": 1,
        "unitCost": 42.75
      }
    ]
  }
}
```

The callback handler reads the raw request body for signature verification, then decodes JSON. `eventId` is required for idempotency. Duplicate `eventId` callbacks return success without reprocessing.

## HMAC signing steps

Use one Compose output as the final callback body and send that exact output in the HTTP action body.

1. Compose `timestamp` as Unix seconds.
2. Compose `callbackBody` as the final JSON string.
3. Compose `signedString`:

```text
<timestamp>.<callbackBody>
```

4. Compute HMAC-SHA256 of `signedString` using the shared Purchasing HMAC secret.
5. If the HMAC tool returns lowercase hex, set:

```text
X-Slate-Signature: sha256=<hex>
```

6. If the HMAC tool returns base64 bytes, set:

```text
X-Slate-Signature: sha256-base64=<base64>
```

7. POST the same `callbackBody` Compose output to the callback URL.

Do not include the secret in run history notes, logs, response bodies, or Teams/email notifications.

If the cloud flow cannot compute HMAC-SHA256, skip `X-Slate-Signature` and send the shared secret in `X-Slate-Callback-Secret` instead. Keep `X-Slate-Timestamp` fresh and use HTTPS only.

## HTTP callback action

Method:

```text
POST
```

URI:

```text
https://<site-host>/wp-json/slate-ops/v1/purchasing/integration/callback
```

Headers:

```text
Content-Type: application/json
X-Slate-Signature: sha256-base64=<base64_hmac>
X-Slate-Timestamp: <timestamp>
X-Slate-Event-Type: bc.item.synced
X-Slate-Flow-Id: <flow_run_id_or_event_id>
```

Power Automate callback-secret fallback:

```text
Content-Type: application/json
X-Slate-Callback-Secret: <shared_hmac_secret>
X-Slate-Timestamp: <timestamp>
X-Slate-Event-Type: bc.item.synced
X-Slate-Flow-Id: <flow_run_id_or_event_id>
```

Body:

```text
<callbackBody Compose output>
```

## HMAC compatibility

Slate Ops accepts both HMAC signature formats:

```text
sha256=<lowercase_hex_hmac>
sha256-base64=<base64_hmac>
```

It also accepts `X-Slate-Callback-Secret` for Power Automate cloud flows that cannot compute HMAC-SHA256. This keeps the callback protected by the configured shared secret while avoiding unsupported expression functions. The unsigned bypass is only for read-only sync callbacks when no HMAC secret is configured. Purchase writeback events are not in the unsigned allowlist and must remain protected.

## Troubleshooting

| HTTP status | Likely cause | Fix |
| --- | --- | --- |
| 401 | Missing signature or callback secret, missing timestamp, timestamp older than 300 seconds, wrong secret, or signed body does not exactly match posted body. | Sign `<timestamp>.<raw callback body>` and post that exact same body. Confirm the WordPress HMAC secret matches Power Automate. Use `sha256-base64=` if the HMAC action returns base64, or `X-Slate-Callback-Secret` if Power Automate cannot compute HMAC. |
| 400 | Invalid JSON body, missing `eventId`, or malformed request. | Send valid JSON with a non-empty `eventId`, `eventType`, `eventVersion`, `occurredAt`, `sourceSystem`, and `payload.items`. |
| 422 | `X-Slate-Event-Type` is not accepted. | For Sync Items, set `X-Slate-Event-Type` to `bc.item.synced`. Do not use purchase writeback event names for read-only item sync. |
