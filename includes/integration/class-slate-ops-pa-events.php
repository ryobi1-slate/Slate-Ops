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
   * Verify an inbound signature. Rejects replays older than 300 s.
   */
  public static function verify_inbound($body, $signature, $timestamp) {
    $secret = get_option(self::OPT_SECRET, '');
    if (!$secret || !$signature || !$timestamp) return false;
    if (abs(time() - (int) $timestamp) > 300)    return false;
    return hash_equals(self::sign($body, (string) $timestamp, $secret), $signature);
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

    if (array_key_exists('hmac_secret', $data)) {
      $secret = sanitize_text_field(trim((string) $data['hmac_secret']));
      if ($secret !== '') {
        update_option(self::OPT_SECRET, $secret);
      } else {
        delete_option(self::OPT_SECRET);
      }
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

  // ── Private helpers ────────────────────────────────────────────────────────

  private static function uuid4() {
    return sprintf(
      '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
      mt_rand(0, 0xffff), mt_rand(0, 0xffff),
      mt_rand(0, 0xffff),
      mt_rand(0, 0x0fff) | 0x4000,
      mt_rand(0, 0x3fff) | 0x8000,
      mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
  }
}
