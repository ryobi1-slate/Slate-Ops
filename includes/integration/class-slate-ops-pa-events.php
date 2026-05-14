<?php
if (!defined('ABSPATH')) exit;

/**
 * Power Automate / BC readiness helper.
 *
 * Handles HMAC signing, option storage, test event dispatch, and inbound
 * signature verification. No BC calls, no operational sync.
 */
class Slate_Ops_PA_Events {

  // ── Option keys ────────────────────────────────────────────────────────────

  const OPT_ENABLED   = 'slate_ops_pa_purchasing_enabled';
  const OPT_FLOW_PR   = 'slate_ops_pa_flow_purchase_request_approved_url';
  const OPT_FLOW_VEN  = 'slate_ops_pa_flow_vendor_sync_url';
  const OPT_FLOW_ITEM = 'slate_ops_pa_flow_item_sync_url';
  const OPT_FLOW_PO   = 'slate_ops_pa_flow_open_po_sync_url';
  const OPT_FLOW_DEM  = 'slate_ops_pa_flow_demand_sync_url';
  const OPT_SECRET    = 'slate_ops_pa_hmac_secret';
  const OPT_LAST_AT   = 'slate_ops_pa_last_test_event_at';
  const OPT_LAST_ST   = 'slate_ops_pa_last_test_event_status';
  const OPT_LAST_MSG  = 'slate_ops_pa_last_test_event_message';
  const OPT_LAST_CB   = 'slate_ops_pa_last_callback_at';

  // ── HMAC ───────────────────────────────────────────────────────────────────

  /**
   * Sign a request body.
   * signed_string = timestamp + "." + raw_body
   * Returns "sha256=<hex>"
   */
  public static function sign($body, $timestamp, $secret) {
    return 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
  }

  /**
   * Sign a request body using base64 output for tools that cannot easily emit
   * a lowercase hex HMAC. Uses the same signed_string as sign().
   */
  public static function sign_base64($body, $timestamp, $secret) {
    return 'sha256-base64=' . base64_encode(hash_hmac('sha256', $timestamp . '.' . $body, $secret, true));
  }

  /**
   * Verify an inbound callback. Rejects replays older than 300 s.
   */
  public static function verify_inbound($body, $signature, $timestamp, $callback_secret = '') {
    $secret = get_option(self::OPT_SECRET, '');
    if (!$secret || !$timestamp) return false;
    if (abs(time() - (int) $timestamp) > 300)    return false;

    $signature = trim((string) $signature);
    if ($signature &&
        (hash_equals(self::sign($body, (string) $timestamp, $secret), $signature) ||
         hash_equals(self::sign_base64($body, (string) $timestamp, $secret), $signature))) {
      return true;
    }

    $callback_secret = trim((string) $callback_secret);
    return $callback_secret !== '' && hash_equals($secret, $callback_secret);
  }

  // ── Status (safe for UI — no secret values) ────────────────────────────────

  public static function get_status() {
    return [
      'enabled'          => (bool) get_option(self::OPT_ENABLED, false),
      'hmac_configured'  => !empty(get_option(self::OPT_SECRET, '')),
      'flow_urls'        => [
        'pr'     => get_option(self::OPT_FLOW_PR,   ''),
        'vendor' => get_option(self::OPT_FLOW_VEN,  ''),
        'item'   => get_option(self::OPT_FLOW_ITEM, ''),
        'po'     => get_option(self::OPT_FLOW_PO,   ''),
        'demand' => get_option(self::OPT_FLOW_DEM,  ''),
      ],
      'flows_configured' => [
        'pr'     => !empty(get_option(self::OPT_FLOW_PR,   '')),
        'vendor' => !empty(get_option(self::OPT_FLOW_VEN,  '')),
        'item'   => !empty(get_option(self::OPT_FLOW_ITEM, '')),
        'po'     => !empty(get_option(self::OPT_FLOW_PO,   '')),
        'demand' => !empty(get_option(self::OPT_FLOW_DEM,  '')),
      ],
      'last_test' => [
        'at'      => get_option(self::OPT_LAST_AT,  null) ?: null,
        'status'  => get_option(self::OPT_LAST_ST,  null) ?: null,
        'message' => get_option(self::OPT_LAST_MSG, null) ?: null,
      ],
      'callback_available' => true,
      'last_callback_at'   => get_option(self::OPT_LAST_CB, null) ?: null,
      'sync_status'        => self::get_sync_status(),
    ];
  }

  // ── Settings write ─────────────────────────────────────────────────────────

  public static function save_settings(array $data) {
    if (array_key_exists('enabled', $data)) {
      update_option(self::OPT_ENABLED, (bool) $data['enabled']);
    }

    $url_map = [
      'flow_pr_url'     => self::OPT_FLOW_PR,
      'flow_vendor_url' => self::OPT_FLOW_VEN,
      'flow_item_url'   => self::OPT_FLOW_ITEM,
      'flow_po_url'     => self::OPT_FLOW_PO,
      'flow_demand_url' => self::OPT_FLOW_DEM,
    ];
    foreach ($url_map as $key => $opt) {
      if (array_key_exists($key, $data)) {
        update_option($opt, esc_url_raw(trim((string) $data[$key])));
      }
    }

    if (!empty($data['clear_hmac_secret'])) {
      delete_option(self::OPT_SECRET);
    } elseif (array_key_exists('hmac_secret', $data)) {
      $secret = trim((string) $data['hmac_secret']);
      if ($secret !== '') {
        update_option(self::OPT_SECRET, $secret);
      }
      // blank input = keep existing secret; use clear_hmac_secret to explicitly remove it
    }
  }

  // ── Test event ─────────────────────────────────────────────────────────────

  public static function send_test_event() {
    // Use first configured flow URL.
    $flow_url = '';
    foreach ([self::OPT_FLOW_PR, self::OPT_FLOW_VEN, self::OPT_FLOW_ITEM, self::OPT_FLOW_PO, self::OPT_FLOW_DEM] as $opt) {
      $url = get_option($opt, '');
      if ($url) { $flow_url = $url; break; }
    }

    if (!$flow_url) {
      return new WP_Error('no_flow_url', 'No Power Automate flow URL is configured.', ['status' => 400]);
    }

    $secret = get_option(self::OPT_SECRET, '');
    if (!$secret) {
      return new WP_Error('no_secret', 'HMAC secret is not configured.', ['status' => 400]);
    }

    $event_id  = self::uuid4();
    $timestamp = (string) time();
    $body = wp_json_encode([
      'eventId'      => $event_id,
      'eventType'    => 'purchase.integration.test',
      'eventVersion' => '1.0',
      'occurredAt'   => gmdate('Y-m-d\TH:i:s\Z'),
      'sourceSystem' => 'slate_ops',
      'payload'      => ['message' => 'Slate Ops purchasing integration test'],
    ]);

    $response = wp_remote_post($flow_url, [
      'headers' => [
        'Content-Type'       => 'application/json',
        'X-Slate-Signature'  => self::sign($body, $timestamp, $secret),
        'X-Slate-Timestamp'  => $timestamp,
        'X-Slate-Event-Type' => 'purchase.integration.test',
        'X-Slate-Flow-Id'    => $event_id,
      ],
      'body'    => $body,
      'timeout' => 15,
    ]);

    $now = Slate_Ops_Utils::now_gmt();

    if (is_wp_error($response)) {
      $msg = $response->get_error_message();
      update_option(self::OPT_LAST_AT,  $now);
      update_option(self::OPT_LAST_ST,  'error');
      update_option(self::OPT_LAST_MSG, $msg);
      return ['status' => 'error', 'message' => $msg, 'at' => $now];
    }

    $code   = (int) wp_remote_retrieve_response_code($response);
    $status = ($code >= 200 && $code < 300) ? 'success' : 'error';
    $msg    = 'HTTP ' . $code;
    update_option(self::OPT_LAST_AT,  $now);
    update_option(self::OPT_LAST_ST,  $status);
    update_option(self::OPT_LAST_MSG, $msg);

    return [
      'status'      => $status,
      'message'     => $msg,
      'at'          => $now,
      'http_status' => $code,
    ];
  }

  // ── Sync request (outbound) ────────────────────────────────────────────────

  public static function send_sync_request($feed) {
    $cfg = self::feed_config($feed);
    if (!$cfg) {
      return new WP_Error('invalid_feed', 'Unknown feed: ' . $feed, ['status' => 400]);
    }

    $flow_url = get_option($cfg['opt'], '');
    if (!$flow_url) {
      return new WP_Error('no_flow_url', 'No flow URL configured for feed: ' . $feed, ['status' => 400]);
    }

    $secret    = get_option(self::OPT_SECRET, '');
    $event_id  = self::uuid4();
    $timestamp = (string) time();
    $body = wp_json_encode([
      'eventId'      => $event_id,
      'eventType'    => $cfg['event'],
      'eventVersion' => '1.0',
      'occurredAt'   => gmdate('Y-m-d\TH:i:s\Z'),
      'sourceSystem' => 'slate_ops',
      'payload'      => ['feed' => $feed],
    ]);

    // Sign when a secret is configured; omit signature headers for sandbox
    // testing where no secret is set (read-only sync only).
    $headers = ['Content-Type' => 'application/json'];
    if ($secret) {
      $headers['X-Slate-Signature'] = self::sign($body, $timestamp, $secret);
      $headers['X-Slate-Timestamp'] = $timestamp;
    }
    $headers['X-Slate-Event-Type'] = $cfg['event'];
    $headers['X-Slate-Flow-Id']    = $event_id;

    $response = wp_remote_post($flow_url, [
      'headers' => $headers,
      'body'    => $body,
      'timeout' => 60,
    ]);

    $now = Slate_Ops_Utils::now_gmt();
    update_option(self::feed_sync_opt($feed, 'requested_at'), $now);

    if (is_wp_error($response)) {
      $msg = $response->get_error_message();
      update_option(self::feed_sync_opt($feed, 'status'), 'error');
      update_option(self::feed_sync_opt($feed, 'msg'),    $msg);
      return ['status' => 'error', 'message' => $msg, 'at' => $now];
    }

    $code   = (int) wp_remote_retrieve_response_code($response);
    $status = ($code >= 200 && $code < 300) ? 'pending' : 'error';
    // 202 Accepted is the expected PA response for async flows — the actual
    // data arrives later via the callback endpoint, so status stays 'pending'
    // until the bc.*.synced callback is received.
    if ($code === 202) {
      $msg = 'Accepted — awaiting callback';
    } elseif ($code >= 200 && $code < 300) {
      $msg = 'HTTP ' . $code . ' — awaiting callback';
    } else {
      $msg = 'HTTP ' . $code;
    }
    update_option(self::feed_sync_opt($feed, 'status'), $status);
    update_option(self::feed_sync_opt($feed, 'msg'),    $msg);

    return [
      'status'      => $status,
      'message'     => $msg,
      'at'          => $now,
      'http_status' => $code,
    ];
  }

  // ── Sync status ────────────────────────────────────────────────────────────

  public static function get_sync_status() {
    $feeds = ['vendor', 'item', 'po', 'demand'];
    $out   = [];
    foreach ($feeds as $feed) {
      $out[$feed] = [
        'requested_at' => get_option(self::feed_sync_opt($feed, 'requested_at'), null) ?: null,
        'at'           => get_option(self::feed_sync_opt($feed, 'at'),           null) ?: null,
        'status'       => get_option(self::feed_sync_opt($feed, 'status'),       null) ?: null,
        'message'      => get_option(self::feed_sync_opt($feed, 'msg'),          null) ?: null,
      ];
    }
    return $out;
  }

  public static function record_feed_sync($feed, $at, $status, $message) {
    update_option(self::feed_sync_opt($feed, 'at'),     $at);
    update_option(self::feed_sync_opt($feed, 'status'), $status);
    update_option(self::feed_sync_opt($feed, 'msg'),    $message);
  }

  // ── Idempotency + logging ──────────────────────────────────────────────────

  public static function is_duplicate_event($event_id) {
    global $wpdb;
    $t = $wpdb->prefix . 'slate_ops_pur_sync_log';
    return (bool) $wpdb->get_var($wpdb->prepare(
      "SELECT id FROM $t WHERE integration_event_id = %s LIMIT 1",
      $event_id
    ));
  }

  public static function log_callback($event_id, $event_type, $flow_id, $status, $message, $payload_hash = null) {
    global $wpdb;
    $t   = $wpdb->prefix . 'slate_ops_pur_sync_log';
    $now = Slate_Ops_Utils::now_gmt();
    $wpdb->insert($t, [
      'integration_event_id' => $event_id,
      'event_type'           => $event_type,
      'flow_id'              => $flow_id ?: null,
      'status'               => $status,
      'message'              => $message,
      'received_at'          => $now,
      'processed_at'         => $now,
      'payload_hash'         => $payload_hash,
      'created_at'           => $now,
    ]);
  }

  // ── Inbound sync processors ────────────────────────────────────────────────

  public static function process_vendor_sync($payload) {
    global $wpdb;
    $t   = $wpdb->prefix . 'slate_ops_pur_vendors';
    $now = Slate_Ops_Utils::now_gmt();

    // Support both a single-vendor payload {vendorNo, name, ...} and a batch
    // payload {vendors: [{vendorNo, ...}, ...]} — PA typically sends a batch.
    if (isset($payload['vendors']) && is_array($payload['vendors'])) {
      $records = $payload['vendors'];
    } elseif (!empty($payload['vendorNo'])) {
      $records = [$payload];
    } else {
      return 0;
    }

    // Collect all valid vendor numbers up front so we can pre-fetch existing
    // rows in a single IN query instead of one SELECT per record (N+1).
    $vendor_nos = [];
    foreach ($records as $v) {
      $vno = sanitize_text_field($v['vendorNo'] ?? '');
      if ($vno !== '') $vendor_nos[] = $vno;
    }
    if (empty($vendor_nos)) return 0;

    $placeholders = implode(',', array_fill(0, count($vendor_nos), '%s'));
    $existing_rows = $wpdb->get_results(
      $wpdb->prepare("SELECT id, bc_vendor_id FROM $t WHERE bc_vendor_id IN ($placeholders)", $vendor_nos),
      ARRAY_A
    );
    $existing_map = [];
    foreach ($existing_rows as $row) {
      $existing_map[$row['bc_vendor_id']] = (int) $row['id'];
    }

    $count = 0;
    foreach ($records as $v) {
      $vendor_no = sanitize_text_field($v['vendorNo'] ?? '');
      if ($vendor_no === '') continue;

      // Accept both key names PA may send for minimum order amount.
      $min_order_raw = $v['minimumOrderAmount'] ?? $v['minOrderAmount'] ?? null;

      $data = [
        'bc_vendor_id'     => $vendor_no,
        'name'             => sanitize_text_field($v['name']         ?? ''),
        'contact_email'    => sanitize_email($v['email']             ?? ''),
        'contact_phone'    => sanitize_text_field($v['phone']        ?? ''),
        'payment_terms'    => sanitize_text_field($v['paymentTerms'] ?? ''),
        'freight_terms'    => sanitize_text_field($v['freightTerms'] ?? ''),
        'lead_time_days'   => max(0, (int) ($v['leadTimeDays']       ?? 0)),
        'min_order_amount' => isset($min_order_raw) ? max(0.0, (float) $min_order_raw) : null,
        'status'           => sanitize_text_field($v['status']       ?? 'active'),
        'updated_at'       => $now,
      ];

      if (isset($existing_map[$vendor_no])) {
        $result = $wpdb->update($t, $data, ['id' => $existing_map[$vendor_no]]);
      } else {
        $result = $wpdb->insert($t, array_merge($data, ['created_at' => $now]));
      }

      if ($result !== false) $count++;
    }

    return $count;
  }

  public static function process_item_sync($payload) {
    global $wpdb;
    $ti  = $wpdb->prefix . 'slate_ops_pur_items';
    $tv  = $wpdb->prefix . 'slate_ops_pur_vendors';
    $now = Slate_Ops_Utils::now_gmt();

    if (isset($payload['items']) && is_string($payload['items'])) {
      $decoded_items = json_decode($payload['items'], true);
      if (is_array($decoded_items)) {
        $payload['items'] = $decoded_items;
      }
    }

    // Support both Slate-native itemNo payloads and BC v2 item payloads that
    // use number/displayName. PA typically sends a batch.
    if (isset($payload['items']) && is_array($payload['items'])) {
      $records = $payload['items'];
    } elseif (!empty($payload['itemNo']) || !empty($payload['number'])) {
      $records = [$payload];
    } else {
      return 0;
    }

    // Collect all valid item numbers up front so we can pre-fetch existing
    // rows in a single IN query instead of one SELECT per record (N+1).
    $item_nos = [];
    foreach ($records as $i) {
      $ino = sanitize_text_field($i['itemNo'] ?? $i['number'] ?? '');
      if (self::is_ignored_item_no($ino)) continue;
      if ($ino !== '') $item_nos[] = $ino;
    }
    if (empty($item_nos)) return 0;

    $placeholders = implode(',', array_fill(0, count($item_nos), '%s'));
    $existing_rows = $wpdb->get_results(
      $wpdb->prepare("SELECT id, bc_item_id FROM $ti WHERE bc_item_id IN ($placeholders)", $item_nos),
      ARRAY_A
    );
    $existing_map = [];
    foreach ($existing_rows as $row) {
      $existing_map[$row['bc_item_id']] = (int) $row['id'];
    }

    // Pre-fetch vendor id mappings in a single IN query.
    $vendor_nos = [];
    foreach ($records as $i) {
      $vno = sanitize_text_field($i['vendorNo'] ?? '');
      if ($vno !== '') $vendor_nos[] = $vno;
    }
    $vendor_map = [];
    if (!empty($vendor_nos)) {
      $vendor_nos = array_values(array_unique($vendor_nos));
      $v_placeholders = implode(',', array_fill(0, count($vendor_nos), '%s'));
      $vendor_rows = $wpdb->get_results(
        $wpdb->prepare("SELECT id, bc_vendor_id FROM $tv WHERE bc_vendor_id IN ($v_placeholders)", $vendor_nos),
        ARRAY_A
      );
      foreach ($vendor_rows as $row) {
        $vendor_map[$row['bc_vendor_id']] = (int) $row['id'];
      }
    }

    $count = 0;
    foreach ($records as $i) {
      $item_no = sanitize_text_field($i['itemNo'] ?? $i['number'] ?? '');
      if (self::is_ignored_item_no($item_no)) continue;
      if ($item_no === '') continue;

      $vendor_no           = sanitize_text_field($i['vendorNo'] ?? '');
      $preferred_vendor_id = ($vendor_no !== '' && isset($vendor_map[$vendor_no])) ? $vendor_map[$vendor_no] : null;

      $data = [
        'bc_item_id'          => $item_no,
        'part_number'         => sanitize_text_field($i['itemNo']          ?? $i['number'] ?? $item_no),
        'description'         => sanitize_text_field($i['description']     ?? $i['displayName'] ?? ''),
        'preferred_vendor_id' => $preferred_vendor_id,
        'on_hand'             => (int) ($i['onHand']                       ?? 0),
        'reorder_point'       => max(0, (int) ($i['reorderPoint']          ?? 0)),
        'unit_cost'           => max(0.0, (float) ($i['unitCost']          ?? 0)),
        'demand_level'        => sanitize_text_field($i['demandLevel']     ?? 'low'),
        'forecasted_need'     => max(0, (int) ($i['forecastedNeed']        ?? 0)),
        'suggested_order'     => max(0, (int) ($i['suggestedOrder']        ?? 0)),
        'updated_at'          => $now,
      ];

      if (isset($existing_map[$item_no])) {
        $result = $wpdb->update($ti, $data, ['id' => $existing_map[$item_no]]);
      } else {
        $result = $wpdb->insert($ti, array_merge($data, ['created_at' => $now]));
      }

      if ($result !== false) $count++;
    }

    return $count;
  }

  public static function process_po_sync($payload) {
    global $wpdb;
    $to  = $wpdb->prefix . 'slate_ops_pur_orders';
    $tl  = $wpdb->prefix . 'slate_ops_pur_order_lines';
    $tv  = $wpdb->prefix . 'slate_ops_pur_vendors';
    $now = Slate_Ops_Utils::now_gmt();

    $po_no = sanitize_text_field($payload['poNo'] ?? '');
    if (!$po_no) return;

    $vendor_id = null;
    $vendor_no = sanitize_text_field($payload['vendorNo'] ?? '');
    if ($vendor_no) {
      $vid = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $tv WHERE bc_vendor_id = %s LIMIT 1",
        $vendor_no
      ));
      if ($vid) $vendor_id = (int) $vid;
    }

    $ordered_at    = null;
    $expected_date = null;
    if (!empty($payload['orderedAt'])) {
      $ts = strtotime($payload['orderedAt']);
      if ($ts) $ordered_at = gmdate('Y-m-d H:i:s', $ts);
    }
    if (!empty($payload['expectedDate'])) {
      $ts_exp = strtotime($payload['expectedDate']);
      if ($ts_exp) $expected_date = gmdate('Y-m-d', $ts_exp);
    }

    $existing = $wpdb->get_row(
      $wpdb->prepare("SELECT id FROM $to WHERE bc_po_id = %s LIMIT 1", $po_no),
      ARRAY_A
    );

    $header = [
      'bc_po_id'      => $po_no,
      'po_number'     => sanitize_text_field($payload['poNo']     ?? $po_no),
      'vendor_id'     => $vendor_id,
      'status'        => sanitize_text_field($payload['status']   ?? 'submitted'),
      'ordered_at'    => $ordered_at,
      'expected_date' => $expected_date,
      'notes'         => sanitize_textarea_field($payload['notes'] ?? ''),
      'updated_at'    => $now,
    ];

    $po_id = null;
    if ($existing) {
      $po_id = (int) $existing['id'];
      $wpdb->update($to, $header, ['id' => $po_id]);
    } else {
      $wpdb->insert($to, array_merge($header, ['created_at' => $now]));
      $po_id = (int) $wpdb->insert_id;
    }

    if (!$po_id) return;

    $lines = isset($payload['lines']) && is_array($payload['lines']) ? $payload['lines'] : [];
    $wpdb->query('START TRANSACTION');
    $wpdb->delete($tl, ['po_id' => $po_id]);
    $ok = true;
    foreach ($lines as $line) {
      $result = $wpdb->insert($tl, [
        'po_id'            => $po_id,
        'item_description' => sanitize_text_field($line['itemDescription'] ?? ''),
        'qty_ordered'      => max(0, (int) ($line['qtyOrdered']            ?? 0)),
        'qty_received'     => max(0, (int) ($line['qtyReceived']           ?? 0)),
        'unit_cost'        => max(0.0, (float) ($line['unitCost']          ?? 0)),
        'created_at'       => $now,
        'updated_at'       => $now,
      ]);
      if ($result === false) { $ok = false; break; }
    }
    if ($ok) {
      $wpdb->query('COMMIT');
    } else {
      $wpdb->query('ROLLBACK');
    }
  }

  public static function process_po_received($payload) {
    global $wpdb;
    $to  = $wpdb->prefix . 'slate_ops_pur_orders';
    $now = Slate_Ops_Utils::now_gmt();

    $po_no = sanitize_text_field($payload['poNo'] ?? '');
    if (!$po_no) return false;

    $updated = $wpdb->update(
      $to,
      ['status' => 'received', 'updated_at' => $now],
      ['bc_po_id' => $po_no]
    );

    return $updated !== false && $updated > 0;
  }

  public static function process_sync_failed($payload) {
    $feed = sanitize_text_field($payload['feed'] ?? '');
    if (!$feed || !self::feed_config($feed)) return;
    $now = Slate_Ops_Utils::now_gmt();
    update_option(self::feed_sync_opt($feed, 'at'),     $now);
    update_option(self::feed_sync_opt($feed, 'status'), 'error');
    update_option(self::feed_sync_opt($feed, 'msg'),    sanitize_text_field($payload['error'] ?? 'Sync failed'));
  }

  // ── Private helpers ────────────────────────────────────────────────────────

  private static function feed_config($feed) {
    $map = [
      'vendor' => ['opt' => self::OPT_FLOW_VEN,  'event' => 'purchase.vendor.sync.requested'],
      'item'   => ['opt' => self::OPT_FLOW_ITEM, 'event' => 'purchase.item.sync.requested'],
      'po'     => ['opt' => self::OPT_FLOW_PO,   'event' => 'purchase.openPo.sync.requested'],
      'demand' => ['opt' => self::OPT_FLOW_DEM,  'event' => 'purchase.demand.sync.requested'],
    ];
    return $map[$feed] ?? null;
  }

  private static function feed_sync_opt($feed, $key) {
    return 'slate_ops_pa_sync_' . $feed . '_' . $key;
  }

  private static function is_ignored_item_no($item_no) {
    return stripos((string) $item_no, 'BOM') === 0;
  }

  private static function uuid4() {
    return wp_generate_uuid4();
  }
}
