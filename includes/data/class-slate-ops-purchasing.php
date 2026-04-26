<?php
if (!defined('ABSPATH')) exit;

class Slate_Ops_Purchasing {

  // ── Table helpers ──────────────────────────────────────────────────────────

  private static function t($name) {
    global $wpdb;
    return $wpdb->prefix . 'slate_ops_pur_' . $name;
  }

  // ── Vendors ────────────────────────────────────────────────────────────────

  public static function list_vendors($args = []) {
    global $wpdb;
    $t   = self::t('vendors');
    $sql = "SELECT * FROM $t";
    if (!empty($args['status'])) {
      $sql .= $wpdb->prepare(' WHERE status = %s', $args['status']);
    }
    $sql .= ' ORDER BY name ASC';
    return $wpdb->get_results($sql, ARRAY_A) ?: [];
  }

  public static function get_vendor($id) {
    global $wpdb;
    $t = self::t('vendors');
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", (int) $id), ARRAY_A);
  }

  public static function create_vendor($data) {
    global $wpdb;
    $now = Slate_Ops_Utils::now_gmt();
    $wpdb->insert(self::t('vendors'), array_merge($data, [
      'created_at' => $now,
      'updated_at' => $now,
    ]));
    return $wpdb->insert_id;
  }

  public static function update_vendor($id, $data) {
    global $wpdb;
    $data['updated_at'] = Slate_Ops_Utils::now_gmt();
    return $wpdb->update(self::t('vendors'), $data, ['id' => (int) $id]);
  }

  // ── Items ──────────────────────────────────────────────────────────────────

  public static function list_items() {
    global $wpdb;
    $t = self::t('items');
    return $wpdb->get_results("SELECT * FROM $t ORDER BY part_number ASC", ARRAY_A) ?: [];
  }

  public static function get_item($id) {
    global $wpdb;
    $t = self::t('items');
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", (int) $id), ARRAY_A);
  }

  public static function create_item($data) {
    global $wpdb;
    $now = Slate_Ops_Utils::now_gmt();
    $wpdb->insert(self::t('items'), array_merge($data, [
      'created_at' => $now,
      'updated_at' => $now,
    ]));
    return $wpdb->insert_id;
  }

  public static function update_item($id, $data) {
    global $wpdb;
    $data['updated_at'] = Slate_Ops_Utils::now_gmt();
    return $wpdb->update(self::t('items'), $data, ['id' => (int) $id]);
  }

  // ── Purchase Requests ──────────────────────────────────────────────────────

  public static function list_requests() {
    global $wpdb;
    $tr = self::t('requests');
    $tv = self::t('vendors');
    $rows = $wpdb->get_results(
      "SELECT r.*, v.name AS vendor_name_resolved
       FROM $tr r
       LEFT JOIN $tv v ON r.vendor_id = v.id
       ORDER BY r.created_at DESC",
      ARRAY_A
    ) ?: [];

    foreach ($rows as &$row) {
      $row['requested_by_name'] = self::resolve_user_name($row['requested_by'] ?? null);
    }
    unset($row);

    return $rows;
  }

  public static function get_request($id) {
    global $wpdb;
    $t = self::t('requests');
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", (int) $id), ARRAY_A);
  }

  public static function create_request($data) {
    global $wpdb;
    $now = Slate_Ops_Utils::now_gmt();
    if (!isset($data['status'])) {
      $data['status'] = 'draft';
    }
    $wpdb->insert(self::t('requests'), array_merge($data, [
      'created_at' => $now,
      'updated_at' => $now,
    ]));
    $id = (int) $wpdb->insert_id;
    if ($id) {
      $wpdb->update(
        self::t('requests'),
        ['request_number' => 'PR-' . str_pad($id, 4, '0', STR_PAD_LEFT)],
        ['id' => $id]
      );
    }
    return $id;
  }

  public static function update_request_status($id, $status) {
    global $wpdb;
    $allowed = ['draft', 'pending', 'approved', 'rejected'];
    if (!in_array($status, $allowed, true)) {
      return false;
    }
    return $wpdb->update(
      self::t('requests'),
      ['status' => $status, 'updated_at' => Slate_Ops_Utils::now_gmt()],
      ['id' => (int) $id]
    );
  }

  // ── Purchase Orders ────────────────────────────────────────────────────────

  public static function list_orders() {
    global $wpdb;
    $to = self::t('orders');
    $tv = self::t('vendors');
    $tl = self::t('order_lines');
    return $wpdb->get_results(
      "SELECT o.*,
              v.name AS vendor_name,
              (SELECT COUNT(*) FROM $tl l WHERE l.po_id = o.id) AS line_count,
              (SELECT COALESCE(SUM(l.qty_ordered * l.unit_cost), 0) FROM $tl l WHERE l.po_id = o.id) AS total_value
       FROM $to o
       LEFT JOIN $tv v ON o.vendor_id = v.id
       ORDER BY o.ordered_at DESC",
      ARRAY_A
    ) ?: [];
  }

  public static function get_order($id) {
    global $wpdb;
    $t = self::t('orders');
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", (int) $id), ARRAY_A);
  }

  public static function list_order_lines($po_id) {
    global $wpdb;
    $t = self::t('order_lines');
    return $wpdb->get_results(
      $wpdb->prepare("SELECT * FROM $t WHERE po_id = %d ORDER BY id ASC", (int) $po_id),
      ARRAY_A
    ) ?: [];
  }

  // ── Overview ───────────────────────────────────────────────────────────────

  public static function get_overview() {
    global $wpdb;
    $tr = self::t('requests');
    $to = self::t('orders');
    $ti = self::t('items');
    $tv = self::t('vendors');
    $tl = self::t('order_lines');

    $open_requests        = (int) $wpdb->get_var("SELECT COUNT(*) FROM $tr WHERE status NOT IN ('rejected')");
    $pending_approval     = (int) $wpdb->get_var("SELECT COUNT(*) FROM $tr WHERE status = 'pending'");
    $items_tracked        = (int) $wpdb->get_var("SELECT COUNT(*) FROM $ti");
    $items_below_reorder  = (int) $wpdb->get_var("SELECT COUNT(*) FROM $ti WHERE on_hand <= reorder_point AND reorder_point > 0");
    $active_vendors       = (int) $wpdb->get_var("SELECT COUNT(*) FROM $tv WHERE status = 'active'");
    $total_vendors        = (int) $wpdb->get_var("SELECT COUNT(*) FROM $tv");
    $open_orders          = (int) $wpdb->get_var("SELECT COUNT(*) FROM $to WHERE status NOT IN ('received', 'closed')");
    $items_on_order       = (int) $wpdb->get_var(
      "SELECT COALESCE(SUM(l.qty_ordered - l.qty_received), 0)
       FROM $tl l
       INNER JOIN $to o ON l.po_id = o.id
       WHERE o.status NOT IN ('received', 'closed')
         AND l.qty_ordered > l.qty_received"
    );

    $recent_rows = $wpdb->get_results(
      "SELECT request_number, item_description, status, qty, updated_at
       FROM $tr
       ORDER BY updated_at DESC
       LIMIT 5",
      ARRAY_A
    ) ?: [];

    $icon_map  = ['approved' => 'check_circle', 'rejected' => 'cancel', 'pending' => 'edit_note', 'draft' => 'draft'];
    $label_map = ['draft' => 'created as draft', 'pending' => 'submitted for approval', 'approved' => 'approved', 'rejected' => 'rejected'];

    $recent_activity = array_map(function ($r) use ($icon_map, $label_map) {
      return [
        'icon' => $icon_map[$r['status']] ?? 'history',
        'text' => $r['request_number'] . ' ' . ($label_map[$r['status']] ?? $r['status'])
                . ' — ' . $r['item_description'] . ' × ' . $r['qty'],
        'time' => date('M j · g:i A', strtotime($r['updated_at'])),
      ];
    }, $recent_rows);

    $open_po_summary = $wpdb->get_results(
      "SELECT o.po_number, o.status, o.expected_date, v.name AS vendor_name,
              (SELECT COUNT(*) FROM $tl l WHERE l.po_id = o.id) AS line_count
       FROM $to o
       LEFT JOIN $tv v ON o.vendor_id = v.id
       WHERE o.status NOT IN ('received', 'closed')
       ORDER BY o.ordered_at DESC
       LIMIT 5",
      ARRAY_A
    ) ?: [];

    return [
      'open_requests'       => $open_requests,
      'pending_approval'    => $pending_approval,
      'items_below_reorder' => $items_below_reorder,
      'items_tracked'       => $items_tracked,
      'items_on_order'      => $items_on_order,
      'open_orders'         => $open_orders,
      'active_vendors'      => $active_vendors,
      'total_vendors'       => $total_vendors,
      'recent_activity'     => $recent_activity,
      'open_po_summary'     => $open_po_summary,
    ];
  }

  // ── Helpers ────────────────────────────────────────────────────────────────

  private static function resolve_user_name($user_id) {
    if (!$user_id) return '';
    $u = get_userdata((int) $user_id);
    return $u ? ($u->display_name ?: $u->user_login) : '';
  }
}
