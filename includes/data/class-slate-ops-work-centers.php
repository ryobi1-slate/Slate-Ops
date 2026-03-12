<?php
/**
 * Slate_Ops_Work_Centers — data-access helpers for the work centers table.
 *
 * Work centers are the production areas that jobs are scheduled into.
 * One work center is designated as_constraint = 1 (the bottleneck).
 * Capacity is stored in minutes per day and per week.
 */
if (!defined('ABSPATH')) exit;

class Slate_Ops_Work_Centers {

  public static function table() {
    global $wpdb;
    return $wpdb->prefix . 'slate_ops_work_centers';
  }

  // ── Fetch helpers ────────────────────────────────────────────────

  /**
   * Get a single work center by wc_id. Returns assoc array or null.
   */
  public static function get( $wc_id ) {
    global $wpdb;
    $t = self::table();
    return $wpdb->get_row(
      $wpdb->prepare( "SELECT * FROM $t WHERE wc_id = %d", (int) $wc_id ),
      ARRAY_A
    ) ?: null;
  }

  /**
   * Get a single work center by wc_code.
   */
  public static function get_by_code( $code ) {
    global $wpdb;
    $t = self::table();
    return $wpdb->get_row(
      $wpdb->prepare( "SELECT * FROM $t WHERE wc_code = %s", sanitize_key($code) ),
      ARRAY_A
    ) ?: null;
  }

  /**
   * Return all work centers, ordered by sequence_order.
   *
   * @param bool $active_only  Default true — only return active centers.
   */
  public static function query( $active_only = true ) {
    global $wpdb;
    $t = self::table();
    $where = $active_only ? 'WHERE active = 1' : '';
    return $wpdb->get_results(
      "SELECT * FROM $t $where ORDER BY sequence_order ASC, wc_id ASC",
      ARRAY_A
    ) ?: [];
  }

  /**
   * Return the single work center flagged as is_constraint = 1.
   * Returns null if none is set yet.
   */
  public static function get_constraint() {
    global $wpdb;
    $t = self::table();
    return $wpdb->get_row(
      "SELECT * FROM $t WHERE is_constraint = 1 AND active = 1 LIMIT 1",
      ARRAY_A
    ) ?: null;
  }

  // ── Write helpers ────────────────────────────────────────────────

  /**
   * Insert a new work center row.
   *
   * @param array $data {
   *   string $wc_code              Required. Short slug (e.g. "electrical").
   *   string $display_name         Required.
   *   int    $daily_capacity_minutes   Default 840 (7h × 60).
   *   int    $weekly_capacity_minutes  Default 4200.
   *   bool   $is_constraint        Default false.
   *   int    $sequence_order       Default 0.
   *   string $color                Default '#5A6B65'.
   * }
   * @return int|false  Inserted wc_id on success, false on failure.
   */
  public static function create( array $data ) {
    global $wpdb;
    $t   = self::table();
    $now = Slate_Ops_Utils::now_gmt();

    $row = [
      'wc_code'                => sanitize_key( $data['wc_code'] ?? '' ),
      'display_name'           => sanitize_text_field( $data['display_name'] ?? '' ),
      'daily_capacity_minutes' => max(0, (int) ($data['daily_capacity_minutes'] ?? 840)),
      'weekly_capacity_minutes'=> max(0, (int) ($data['weekly_capacity_minutes'] ?? 4200)),
      'is_constraint'          => empty($data['is_constraint']) ? 0 : 1,
      'sequence_order'         => max(0, (int) ($data['sequence_order'] ?? 0)),
      'color'                  => sanitize_hex_color( $data['color'] ?? '#5A6B65' ) ?: '#5A6B65',
      'active'                 => 1,
      'created_at'             => $now,
      'updated_at'             => $now,
    ];

    if (empty($row['wc_code']) || empty($row['display_name'])) {
      return false;
    }

    // Only one constraint allowed.
    if ($row['is_constraint']) {
      $wpdb->update( $t, ['is_constraint' => 0], ['is_constraint' => 1] );
    }

    $result = $wpdb->insert( $t, $row );
    return $result ? (int) $wpdb->insert_id : false;
  }

  /**
   * Update an existing work center.
   *
   * @param int   $wc_id
   * @param array $data  Any subset of create() fields (plus 'active').
   * @return bool
   */
  public static function update( $wc_id, array $data ) {
    global $wpdb;
    $t   = self::table();
    $now = Slate_Ops_Utils::now_gmt();

    $allowed = [
      'display_name', 'daily_capacity_minutes', 'weekly_capacity_minutes',
      'is_constraint', 'sequence_order', 'color', 'active',
    ];

    $update = ['updated_at' => $now];

    foreach ($allowed as $col) {
      if (!array_key_exists($col, $data)) continue;
      switch ($col) {
        case 'display_name':
          $update[$col] = sanitize_text_field($data[$col]);
          break;
        case 'daily_capacity_minutes':
        case 'weekly_capacity_minutes':
        case 'sequence_order':
          $update[$col] = max(0, (int) $data[$col]);
          break;
        case 'is_constraint':
        case 'active':
          $update[$col] = empty($data[$col]) ? 0 : 1;
          break;
        case 'color':
          $update[$col] = sanitize_hex_color($data[$col]) ?: '#5A6B65';
          break;
      }
    }

    // If this work center is being set as constraint, clear any existing constraint.
    if (!empty($update['is_constraint'])) {
      $wpdb->update( $t, ['is_constraint' => 0], ['is_constraint' => 1] );
    }

    return $wpdb->update( $t, $update, ['wc_id' => (int) $wc_id] ) !== false;
  }
}
