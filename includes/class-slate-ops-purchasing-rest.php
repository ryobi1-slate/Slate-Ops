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

