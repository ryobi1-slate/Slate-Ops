<?php
if (!defined('ABSPATH')) exit;

class Slate_Ops_Purchasing_REST {

  // ── Permission ─────────────────────────────────────────────────────────────

  public static function check_access() {
    return is_user_logged_in() && (
      current_user_can(Slate_Ops_Utils::CAP_SUPERVISOR) ||
      current_user_can(Slate_Ops_Utils::CAP_ADMIN)
    );
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
      [
        'methods'             => 'GET',
        'permission_callback' => $perm,
        'callback'            => [__CLASS__, 'h_list_vendors'],
      ],
      [
        'methods'             => 'POST',
        'permission_callback' => $perm,
        'callback'            => [__CLASS__, 'h_create_vendor'],
      ],
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
        'callback'            => [__CLASS__, 'h_create_request'],
      ],
    ]);

    register_rest_route($ns, '/purchasing/requests/(?P<id>\d+)/status', [
      'methods'             => 'POST',
      'permission_callback' => $perm,
      'callback'            => [__CLASS__, 'h_update_request_status'],
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
  }

  // ── Handlers ───────────────────────────────────────────────────────────────

  public static function h_overview($req) {
    return rest_ensure_response(Slate_Ops_Purchasing::get_overview());
  }

  public static function h_list_vendors($req) {
    return rest_ensure_response(Slate_Ops_Purchasing::list_vendors());
  }

  public static function h_create_vendor($req) {
    $name = sanitize_text_field($req['name'] ?? '');
    if (!$name) {
      return new WP_Error('bad_request', 'name is required', ['status' => 400]);
    }

    $id = Slate_Ops_Purchasing::create_vendor([
      'name'          => $name,
      'contact_email' => sanitize_email($req['contact_email'] ?? ''),
      'contact_phone' => sanitize_text_field($req['contact_phone'] ?? ''),
      'lead_time_days'=> max(0, (int) ($req['lead_time_days'] ?? 0)),
      'payment_terms' => sanitize_text_field($req['payment_terms'] ?? ''),
      'status'        => in_array($req['status'] ?? '', ['active', 'inactive'], true) ? $req['status'] : 'active',
    ]);

    if (!$id) {
      return new WP_Error('db_error', 'Failed to create vendor', ['status' => 500]);
    }

    return rest_ensure_response(['id' => $id]);
  }

  public static function h_list_items($req) {
    return rest_ensure_response(Slate_Ops_Purchasing::list_items());
  }

  public static function h_list_requests($req) {
    return rest_ensure_response(Slate_Ops_Purchasing::list_requests());
  }

  public static function h_create_request($req) {
    $item_description = sanitize_text_field($req['item_description'] ?? '');
    if (!$item_description) {
      return new WP_Error('bad_request', 'item_description is required', ['status' => 400]);
    }

    $id = Slate_Ops_Purchasing::create_request([
      'item_id'          => !empty($req['item_id']) ? (int) $req['item_id'] : null,
      'item_description' => $item_description,
      'vendor_id'        => !empty($req['vendor_id']) ? (int) $req['vendor_id'] : null,
      'qty'              => max(1, (int) ($req['qty'] ?? 1)),
      'unit_cost'        => max(0, (float) ($req['unit_cost'] ?? 0)),
      'notes'            => sanitize_textarea_field($req['notes'] ?? ''),
      'requested_by'     => get_current_user_id(),
    ]);

    if (!$id) {
      return new WP_Error('db_error', 'Failed to create request', ['status' => 500]);
    }

    return rest_ensure_response(['id' => $id]);
  }

  public static function h_update_request_status($req) {
    $id     = (int) $req['id'];
    $status = sanitize_key($req['status'] ?? '');
    $result = Slate_Ops_Purchasing::update_request_status($id, $status);

    if ($result === false) {
      return new WP_Error('bad_request', 'Invalid status or request not found', ['status' => 400]);
    }

    return rest_ensure_response(['success' => true]);
  }

  public static function h_list_orders($req) {
    return rest_ensure_response(Slate_Ops_Purchasing::list_orders());
  }

  public static function h_list_order_lines($req) {
    return rest_ensure_response(Slate_Ops_Purchasing::list_order_lines((int) $req['id']));
  }
}
