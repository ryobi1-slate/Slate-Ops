<?php
if (!defined('ABSPATH')) exit;

/**
 * Phase 2: read-only + purchase request creation endpoints.
 * Status transition and vendor/PO write endpoints are Phase 3/4.
 */
class Slate_Ops_Purchasing_REST {

  // ── Permission ─────────────────────────────────────────────────────────────

  public static function check_access() {
    return is_user_logged_in() && (
      current_user_can(Slate_Ops_Utils::CAP_SUPERVISOR) ||
      current_user_can(Slate_Ops_Utils::CAP_ADMIN)
    );
  }

  public static function check_admin() {
    return is_user_logged_in() && current_user_can(Slate_Ops_Utils::CAP_ADMIN);
  }

  // ── Route registration ─────────────────────────────────────────────────────

  public static function register_routes() {
    $ns   = 'slate-ops/v1';
    $perm = [__CLASS__, 'check_access'];

    register_rest_route($ns, '/purchasing/overview', [
      'methods'             => 'GET',
      'permission_callback' => $perm,
      'callback'            => [__CLASS__, 'h_overview'],
    ]);

    register_rest_route($ns, '/purchasing/vendors', [
      'methods'             => 'GET',
      'permission_callback' => $perm,
      'callback'            => [__CLASS__, 'h_list_vendors'],
    ]);

    register_rest_route($ns, '/purchasing/items', [
      'methods'             => 'GET',
      'permission_callback' => $perm,
      'callback'            => [__CLASS__, 'h_list_items'],
    ]);

    register_rest_route($ns, '/purchasing/requests', [
      [
        'methods'             => 'GET',
        'permission_callback' => $perm,
        'callback'            => [__CLASS__, 'h_list_requests'],
      ],
      [
        'methods'             => 'POST',
        'permission_callback' => $perm,
        'callback'            => [__CLASS__, 'h_create_requests'],
      ],
    ]);

    register_rest_route($ns, '/purchasing/requests/(?P<id>\d+)', [
      [
        'methods'             => 'GET',
        'permission_callback' => $perm,
        'callback'            => [__CLASS__, 'h_get_request'],
      ],
      [
        'methods'             => 'PATCH',
        'permission_callback' => $perm,
        'callback'            => [__CLASS__, 'h_update_request'],
      ],
    ]);

    register_rest_route($ns, '/purchasing/orders', [
      'methods'             => 'GET',
      'permission_callback' => $perm,
      'callback'            => [__CLASS__, 'h_list_orders'],
    ]);

    register_rest_route($ns, '/purchasing/orders/(?P<id>\d+)/lines', [
      'methods'             => 'GET',
      'permission_callback' => $perm,
      'callback'            => [__CLASS__, 'h_list_order_lines'],
    ]);

    // Integration / PA readiness
    $admin = [__CLASS__, 'check_admin'];

    register_rest_route($ns, '/purchasing/integration/status', [
      'methods'             => 'GET',
      'permission_callback' => $perm,
      'callback'            => [__CLASS__, 'h_integration_status'],
    ]);

    register_rest_route($ns, '/purchasing/integration/settings', [
      'methods'             => 'POST',
      'permission_callback' => $admin,
      'callback'            => [__CLASS__, 'h_save_integration_settings'],
    ]);

    register_rest_route($ns, '/purchasing/integration/test-event', [
      'methods'             => 'POST',
      'permission_callback' => $admin,
      'callback'            => [__CLASS__, 'h_send_test_event'],
    ]);

    register_rest_route($ns, '/purchasing/integration/callback', [
      'methods'             => 'POST',
      'permission_callback' => '__return_true',
      'callback'            => [__CLASS__, 'h_integration_callback'],
    ]);

    register_rest_route($ns, '/purchasing/integration/sync/(?P<feed>[a-z]+)', [
      'methods'             => 'POST',
      'permission_callback' => $admin,
      'callback'            => [__CLASS__, 'h_sync_request'],
    ]);
  }

  // ── Handlers ───────────────────────────────────────────────────────────────

  public static function h_overview($req) {
    return rest_ensure_response(Slate_Ops_Purchasing::get_overview());
  }

  public static function h_list_vendors($req) {
    return rest_ensure_response(Slate_Ops_Purchasing::list_vendors());
  }

  public static function h_list_items($req) {
    return rest_ensure_response(Slate_Ops_Purchasing::list_items());
  }

  public static function h_list_requests($req) {
    return rest_ensure_response(Slate_Ops_Purchasing::list_requests());
  }

  public static function h_list_orders($req) {
    return rest_ensure_response(Slate_Ops_Purchasing::list_orders());
  }

  public static function h_list_order_lines($req) {
    return rest_ensure_response(Slate_Ops_Purchasing::list_order_lines((int) $req['id']));
  }

  public static function h_get_request($req) {
    $row = Slate_Ops_Purchasing::get_request_resolved((int) $req['id']);
    if (!$row) {
      return new WP_Error('not_found', 'Purchase request not found.', ['status' => 404]);
    }
    return rest_ensure_response($row);
  }

  public static function h_update_request($req) {
    $id  = (int) $req['id'];
    $raw = $req->get_json_params() ?: [];

    $row = Slate_Ops_Purchasing::get_request($id);
    if (!$row) {
      return new WP_Error('not_found', 'Purchase request not found.', ['status' => 404]);
    }

    if (array_key_exists('status', $raw)) {
      $new_status = sanitize_text_field($raw['status']);
      if ($new_status !== $row['status']) {
        $allowed = Slate_Ops_Purchasing::allowed_transitions($row['status']);
        if (!in_array($new_status, $allowed, true)) {
          return new WP_Error(
            'invalid_transition',
            'Cannot move "' . $row['status'] . '" to "' . $new_status . '".',
            ['status' => 422]
          );
        }
        Slate_Ops_Purchasing::update_request_status($id, $new_status);
      }
    }

    if (array_key_exists('notes', $raw)) {
      $new_notes = sanitize_textarea_field($raw['notes']);
      if ($new_notes !== ($row['notes'] ?? '')) {
        Slate_Ops_Purchasing::update_request_notes($id, $new_notes);
      }
    }

    return rest_ensure_response(Slate_Ops_Purchasing::get_request_resolved($id));
  }

  // ── Integration / PA readiness handlers ───────────────────────────────────

  public static function h_integration_status($req) {
    $status = Slate_Ops_PA_Events::get_status();
    if (!self::check_admin()) {
      unset($status['flow_urls']);
    }
    return rest_ensure_response($status);
  }

  public static function h_save_integration_settings($req) {
    $raw = $req->get_json_params() ?: [];
    Slate_Ops_PA_Events::save_settings($raw);
    return rest_ensure_response(Slate_Ops_PA_Events::get_status());
  }

  public static function h_send_test_event($req) {
    $result = Slate_Ops_PA_Events::send_test_event();
    if (is_wp_error($result)) return $result;
    return rest_ensure_response($result);
  }

  // Read-only BC sync event types — inbound from Power Automate, no WP writes outside purchasing tables.
  // Purchase writeback events (future Phase) are NOT in this list and will always require HMAC.
  const READONLY_SYNC_EVENTS = [
    'bc.vendor.synced',
    'bc.item.synced',
    'bc.openPo.synced',
    'bc.demand.synced',
    'bc.sync.failed',
  ];

  public static function h_integration_callback($req) {
    $body       = $req->get_body();
    $signature  = $req->get_header('x-slate-signature') ?? '';
    $timestamp  = $req->get_header('x-slate-timestamp') ?? '';
    $event_type = $req->get_header('x-slate-event-type') ?? '';
    $flow_id    = $req->get_header('x-slate-flow-id') ?? '';

    $hmac_configured = !empty(get_option(Slate_Ops_PA_Events::OPT_SECRET, ''));
    $is_readonly_sync = in_array($event_type, self::READONLY_SYNC_EVENTS, true);

    // Temporary sandbox bypass: when no HMAC secret is configured, allow
    // read-only BC sync callbacks without signature verification. This lets
    // developers test the sync pipeline before wiring up production secrets.
    // HMAC is always enforced once a secret is set, and purchase writeback
    // events are never eligible for this bypass.
    if ($hmac_configured || !$is_readonly_sync) {
      if (!Slate_Ops_PA_Events::verify_inbound($body, $signature, $timestamp)) {
        return new WP_Error('invalid_signature', 'Signature invalid or expired.', ['status' => 401]);
      }
    }

    $allowed_events = array_merge(['purchase.integration.test'], self::READONLY_SYNC_EVENTS);
    if (!in_array($event_type, $allowed_events, true)) {
      return new WP_Error('unsupported_event', 'Event type not accepted.', ['status' => 422]);
    }

    $json = json_decode($body, true);
    if (!is_array($json)) {
      return new WP_Error('invalid_json', 'Request body is not valid JSON.', ['status' => 400]);
    }

    if ($event_type !== 'purchase.integration.test' && empty($json['eventId'])) {
      return new WP_Error('missing_event_id', 'eventId is required for idempotency.', ['status' => 400]);
    }

    $payload      = (isset($json['payload']) && is_array($json['payload'])) ? $json['payload'] : [];
    $event_id     = isset($json['eventId']) ? sanitize_text_field($json['eventId']) : '';
    $payload_hash = hash('sha256', $body);
    $now          = Slate_Ops_Utils::now_gmt();

    if ($event_type !== 'purchase.integration.test' &&
        Slate_Ops_PA_Events::is_duplicate_event($event_id)) {
      return rest_ensure_response(['received' => true, 'event_type' => $event_type, 'duplicate' => true]);
    }

    switch ($event_type) {
      case 'purchase.integration.test':
        update_option(Slate_Ops_PA_Events::OPT_LAST_CB, $now);
        break;

      case 'bc.vendor.synced':
        $vendor_count = Slate_Ops_PA_Events::process_vendor_sync($payload);
        Slate_Ops_PA_Events::log_callback($event_id, $event_type, $flow_id, 'success',
          'Processed ' . $vendor_count . ' vendor(s)', $payload_hash);
        Slate_Ops_PA_Events::record_feed_sync('vendor', $now, 'success',
          'Synced ' . $vendor_count . ' vendor(s)');
        break;

      case 'bc.item.synced':
        Slate_Ops_PA_Events::process_item_sync($payload);
        Slate_Ops_PA_Events::log_callback($event_id, $event_type, $flow_id, 'success', 'Processed', $payload_hash);
        Slate_Ops_PA_Events::record_feed_sync('item', $now, 'success', 'Synced');
        break;

      case 'bc.openPo.synced':
        Slate_Ops_PA_Events::process_po_sync($payload);
        Slate_Ops_PA_Events::log_callback($event_id, $event_type, $flow_id, 'success', 'Processed', $payload_hash);
        Slate_Ops_PA_Events::record_feed_sync('po', $now, 'success', 'Synced');
        break;

      case 'bc.demand.synced':
        Slate_Ops_PA_Events::process_item_sync($payload);
        Slate_Ops_PA_Events::log_callback($event_id, $event_type, $flow_id, 'success', 'Processed', $payload_hash);
        Slate_Ops_PA_Events::record_feed_sync('demand', $now, 'success', 'Synced');
        break;

      case 'bc.sync.failed':
        Slate_Ops_PA_Events::log_callback($event_id, $event_type, $flow_id, 'error',
          sanitize_text_field($payload['error'] ?? 'Sync failed'), $payload_hash);
        Slate_Ops_PA_Events::process_sync_failed($payload);
        break;
    }

    return rest_ensure_response(['received' => true, 'event_type' => $event_type]);
  }

  public static function h_sync_request($req) {
    $feed    = sanitize_key($req['feed']);
    $allowed = ['vendor', 'item', 'po', 'demand'];
    if (!in_array($feed, $allowed, true)) {
      return new WP_Error('invalid_feed', 'Unknown feed. Allowed: vendor, item, po, demand.', ['status' => 400]);
    }
    $result = Slate_Ops_PA_Events::send_sync_request($feed);
    if (is_wp_error($result)) return $result;
    return rest_ensure_response($result);
  }

  // ── Purchase request handlers ──────────────────────────────────────────────

  public static function h_create_requests($req) {
    $raw   = $req->get_json_params();
    $items = isset($raw['items']) && is_array($raw['items']) ? $raw['items'] : [];

    if (empty($items)) {
      return new WP_Error('invalid_items', 'No items provided.', ['status' => 400]);
    }
    if (count($items) > 50) {
      return new WP_Error('too_many_items', 'Maximum 50 items per request.', ['status' => 400]);
    }

    $created = [];
    foreach ($items as $item) {
      $description = sanitize_text_field($item['item_description'] ?? '');
      if (!$description) {
        return new WP_Error('invalid_item', 'Item description is required.', ['status' => 400]);
      }
      $data = [
        'item_id'          => !empty($item['item_id'])  ? (int) $item['item_id']  : null,
        'item_description' => $description,
        'vendor_id'        => !empty($item['vendor_id']) ? (int) $item['vendor_id'] : null,
        'qty'              => max(1, (int) ($item['qty'] ?? 1)),
        'unit_cost'        => max(0.0, (float) ($item['unit_cost'] ?? 0)),
        'requested_by'     => get_current_user_id(),
      ];
      $id = Slate_Ops_Purchasing::create_request($data);
      if (!$id) {
        return new WP_Error('create_failed', 'Failed to save purchase request.', ['status' => 500]);
      }
      $created[] = Slate_Ops_Purchasing::get_request($id);
    }

    return rest_ensure_response(['created' => $created]);
  }
}

