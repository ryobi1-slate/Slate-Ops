<?php
if (!defined('ABSPATH')) exit;

class Slate_Ops_Purchasing {

  // SOT status values for purchase requests
  const PR_STATUSES = ['draft', 'review', 'approved', 'held', 'ordered', 'cancelled'];

  // Allowed forward transitions per SOT
  const PR_TRANSITIONS = [
    'draft'     => ['review', 'held', 'cancelled'],
    'review'    => ['approved', 'held', 'cancelled'],
    'approved'  => ['ordered', 'held', 'cancelled'],
    'held'      => ['review', 'cancelled'],
    'ordered'   => [],
    'cancelled' => [],
  ];

  public static function allowed_transitions($status) {
    return self::PR_TRANSITIONS[$status] ?? [];
  }

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

  // ── Items ──────────────────────────────────────────────────────────────────

  public static function list_items() {
    global $wpdb;
    $ti = self::t('items');
    $tv = self::t('vendors');
    return $wpdb->get_results(
      "SELECT i.*, v.name AS preferred_vendor_name
       FROM $ti i
       LEFT JOIN $tv v ON i.preferred_vendor_id = v.id
       WHERE i.part_number NOT LIKE 'BOM%'
       ORDER BY i.part_number ASC",
      ARRAY_A
    ) ?: [];
  }

  public static function get_item($id) {
    global $wpdb;
    $t = self::t('items');
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", (int) $id), ARRAY_A);
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

  public static function get_request_resolved($id) {
    global $wpdb;
    $tr = self::t('requests');
    $tv = self::t('vendors');
    $row = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT r.*, v.name AS vendor_name_resolved
         FROM $tr r
         LEFT JOIN $tv v ON r.vendor_id = v.id
         WHERE r.id = %d",
        (int) $id
      ),
      ARRAY_A
    );
    if ($row) {
      $row['requested_by_name'] = self::resolve_user_name($row['requested_by'] ?? null);
    }
    return $row;
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
    if (!in_array($status, self::PR_STATUSES, true)) {
      return false;
    }
    return $wpdb->update(
      self::t('requests'),
      ['status' => $status, 'updated_at' => Slate_Ops_Utils::now_gmt()],
      ['id' => (int) $id]
    );
  }

  public static function update_request_notes($id, $notes) {
    global $wpdb;
    return $wpdb->update(
      self::t('requests'),
      ['notes' => $notes, 'updated_at' => Slate_Ops_Utils::now_gmt()],
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

    $open_requests        = (int) $wpdb->get_var("SELECT COUNT(*) FROM $tr WHERE status NOT IN ('cancelled')");
    $pending_review       = (int) $wpdb->get_var("SELECT COUNT(*) FROM $tr WHERE status = 'review'");
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

    $icon_map  = [
      'approved'  => 'check_circle',
      'cancelled' => 'cancel',
      'review'    => 'edit_note',
      'ordered'   => 'local_shipping',
      'held'      => 'pause_circle',
      'draft'     => 'draft',
    ];
    $label_map = [
      'draft'     => 'created as draft',
      'review'    => 'submitted for review',
      'approved'  => 'approved',
      'held'      => 'placed on hold',
      'ordered'   => 'marked as ordered',
      'cancelled' => 'cancelled',
    ];

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
      'pending_review'      => $pending_review,
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

  // ── Seed sample data ───────────────────────────────────────────────────────

  public static function maybe_seed() {
    if (get_option('slate_ops_pur_seeded')) return;

    global $wpdb;
    $tv = self::t('vendors');
    $ti = self::t('items');

    // Only seed when both tables are empty.
    if ((int) $wpdb->get_var("SELECT COUNT(*) FROM $tv") > 0 ||
        (int) $wpdb->get_var("SELECT COUNT(*) FROM $ti") > 0) {
      update_option('slate_ops_pur_seeded', true);
      return;
    }

    $now = Slate_Ops_Utils::now_gmt();

    $vendors = [
      ['name' => 'SlateAuto Supply',      'contact_email' => 'orders@slateauto.example',       'lead_time_days' => 2, 'payment_terms' => 'Net 30', 'status' => 'active'],
      ['name' => 'Parts Direct',           'contact_email' => 'supply@partsdirect.example',     'lead_time_days' => 3, 'payment_terms' => 'Net 15', 'status' => 'active'],
      ['name' => 'TireHub',                'contact_email' => 'wholesale@tirehub.example',      'lead_time_days' => 5, 'payment_terms' => 'Net 30', 'status' => 'active'],
      ['name' => 'AutoParts Plus',         'contact_email' => 'accounts@autopartsplus.example', 'lead_time_days' => 4, 'payment_terms' => 'Net 30', 'status' => 'active'],
      ['name' => 'MotorPro Distribution', 'contact_email' => 'orders@motorpro.example',        'lead_time_days' => 7, 'payment_terms' => 'Net 45', 'status' => 'inactive'],
    ];

    $vendor_ids = [];
    foreach ($vendors as $v) {
      $wpdb->insert($tv, array_merge($v, ['created_at' => $now, 'updated_at' => $now]));
      $vendor_ids[$v['name']] = (int) $wpdb->insert_id;
    }

    $items = [
      ['part_number' => 'B-1042', 'description' => 'Brake Pad Set (Front)', 'preferred_vendor' => 'SlateAuto Supply', 'on_hand' => 8,  'reorder_point' => 10, 'unit_cost' => 28.50,  'demand_level' => 'high',   'forecasted_need' => 24, 'suggested_order' => 20],
      ['part_number' => 'L-0318', 'description' => 'Oil Filter',             'preferred_vendor' => 'SlateAuto Supply', 'on_hand' => 14, 'reorder_point' => 20, 'unit_cost' => 4.25,   'demand_level' => 'high',   'forecasted_need' => 60, 'suggested_order' => 50],
      ['part_number' => 'T-2201', 'description' => 'Tire (P225/60R18)',      'preferred_vendor' => 'TireHub',          'on_hand' => 2,  'reorder_point' => 4,  'unit_cost' => 145.00, 'demand_level' => 'high',   'forecasted_need' => 12, 'suggested_order' => 10],
      ['part_number' => 'F-0071', 'description' => 'Air Filter',             'preferred_vendor' => 'AutoParts Plus',   'on_hand' => 6,  'reorder_point' => 8,  'unit_cost' => 12.00,  'demand_level' => 'medium', 'forecasted_need' => 18, 'suggested_order' => 15],
      ['part_number' => 'S-0885', 'description' => 'Spark Plug Set',         'preferred_vendor' => 'AutoParts Plus',   'on_hand' => 3,  'reorder_point' => 6,  'unit_cost' => 22.00,  'demand_level' => 'high',   'forecasted_need' => 20, 'suggested_order' => 20],
      ['part_number' => 'W-1124', 'description' => 'Wiper Blade',            'preferred_vendor' => 'Parts Direct',     'on_hand' => 12, 'reorder_point' => 15, 'unit_cost' => 8.50,   'demand_level' => 'medium', 'forecasted_need' => 30, 'suggested_order' => 20],
      ['part_number' => 'C-4410', 'description' => 'Cabin Air Filter',       'preferred_vendor' => 'Parts Direct',     'on_hand' => 4,  'reorder_point' => 5,  'unit_cost' => 14.00,  'demand_level' => 'medium', 'forecasted_need' => 16, 'suggested_order' => 15],
      ['part_number' => 'B-0093', 'description' => 'Battery (Group 35)',     'preferred_vendor' => 'Parts Direct',     'on_hand' => 1,  'reorder_point' => 3,  'unit_cost' => 94.00,  'demand_level' => 'high',   'forecasted_need' => 8,  'suggested_order' => 10],
    ];

    foreach ($items as $item) {
      $vendor_name = $item['preferred_vendor'];
      unset($item['preferred_vendor']);
      $item['preferred_vendor_id'] = $vendor_ids[$vendor_name] ?? null;
      $wpdb->insert($ti, array_merge($item, ['created_at' => $now, 'updated_at' => $now]));
    }

    update_option('slate_ops_pur_seeded', true);
  }

  // ── Helpers ────────────────────────────────────────────────────────────────

  private static function resolve_user_name($user_id) {
    if (!$user_id) return '';
    $u = get_userdata((int) $user_id);
    return $u ? ($u->display_name ?: $u->user_login) : '';
  }
}
