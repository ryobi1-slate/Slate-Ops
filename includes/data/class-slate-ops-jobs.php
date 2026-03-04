<?php
/**
 * Slate_Ops_Jobs — thin data-access helpers for the jobs table.
 *
 * All methods are static; callers should use them directly rather than
 * going through $wpdb every time.  Do NOT instantiate this class.
 *
 * Existing REST endpoints (class-slate-ops-rest.php) continue to write
 * directly to the DB; this class provides shared read helpers so views
 * and future features don't duplicate query logic.
 */
if (!defined('ABSPATH')) exit;

class Slate_Ops_Jobs {

  // ── Table names ──────────────────────────────────────────────────

  public static function table() {
    global $wpdb;
    return $wpdb->prefix . 'slate_ops_jobs';
  }

  // ── Fetch helpers ────────────────────────────────────────────────

  /**
   * Fetch a single job row by job_id.  Returns assoc array or null.
   */
  public static function get( $job_id ) {
    global $wpdb;
    $t = self::table();
    return $wpdb->get_row(
      $wpdb->prepare( "SELECT * FROM $t WHERE job_id = %d", (int) $job_id ),
      ARRAY_A
    ) ?: null;
  }

  /**
   * Fetch multiple jobs with optional filters.
   *
   * @param array $args {
   *   int    $limit          Max rows. Default 500.
   *   int    $offset         Row offset. Default 0.
   *   string $status         Filter by status (exact). Optional.
   *   string $statuses       Comma-separated status list. Optional.
   *   bool   $exclude_archived  Exclude archived rows. Default true.
   *   string $order_by       Column name. Default 'created_at'.
   *   string $order          ASC|DESC. Default DESC.
   * }
   * @return array  Array of assoc arrays.
   */
  public static function query( array $args = [] ) {
    global $wpdb;
    $t = self::table();

    $limit           = max(1, (int) ($args['limit']   ?? 500));
    $offset          = max(0, (int) ($args['offset']  ?? 0));
    $exclude_archived = (bool) ($args['exclude_archived'] ?? true);
    $order_col       = in_array($args['order_by'] ?? '', ['created_at','status','priority','scheduled_start'], true)
                         ? $args['order_by']
                         : 'created_at';
    $order_dir       = strtoupper($args['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

    $where = ['1=1'];

    if ($exclude_archived) {
      $where[] = 'archived_at IS NULL';
    }
    if (!empty($args['status'])) {
      $where[] = $wpdb->prepare('status = %s', $args['status']);
    }
    if (!empty($args['statuses'])) {
      $vals = array_map('sanitize_text_field', explode(',', $args['statuses']));
      $placeholders = implode(',', array_fill(0, count($vals), '%s'));
      // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
      $where[] = $wpdb->prepare("status IN ($placeholders)", ...$vals);
    }

    $sql = "SELECT * FROM $t WHERE " . implode(' AND ', $where)
         . " ORDER BY $order_col $order_dir"
         . " LIMIT %d OFFSET %d";

    return $wpdb->get_results(
      $wpdb->prepare($sql, $limit, $offset),
      ARRAY_A
    ) ?: [];
  }

  /**
   * Return jobs that are unscheduled or ready-for-scheduling.
   */
  public static function get_schedulable( $limit = 200 ) {
    return self::query([
      'statuses'         => 'UNSCHEDULED,READY_FOR_SCHEDULING',
      'limit'            => $limit,
      'order_by'         => 'priority',
      'order'            => 'ASC',
      'exclude_archived' => true,
    ]);
  }

  /**
   * Return jobs that have a scheduled_start within a date range.
   *
   * @param string $from  Y-m-d
   * @param string $to    Y-m-d
   */
  public static function get_scheduled_in_range( $from, $to, $limit = 500 ) {
    global $wpdb;
    $t = self::table();
    return $wpdb->get_results(
      $wpdb->prepare(
        "SELECT j.*, u.display_name AS assigned_name
         FROM $t j
         LEFT JOIN {$wpdb->users} u ON u.ID = j.assigned_user_id
         WHERE j.archived_at IS NULL
           AND j.scheduled_start IS NOT NULL
           AND DATE(j.scheduled_start) <= %s
           AND DATE(COALESCE(j.scheduled_finish, j.scheduled_start)) >= %s
         ORDER BY j.priority ASC, j.scheduled_start ASC
         LIMIT %d",
        $to,
        $from,
        $limit
      ),
      ARRAY_A
    ) ?: [];
  }

  // ── Write helpers ────────────────────────────────────────────────

  /**
   * Update scheduling fields on one job.
   * Writes an activity log entry if $log is true (default).
   *
   * @param int   $job_id
   * @param array $fields  Any subset of: scheduled_start, scheduled_finish,
   *                       assigned_user_id, work_center, estimated_minutes.
   * @param bool  $log     Write audit_log entry.
   * @return bool
   */
  public static function update_schedule( $job_id, array $fields, $log = true ) {
    global $wpdb;
    $t = self::table();

    $allowed = ['scheduled_start','scheduled_finish','assigned_user_id','work_center','estimated_minutes'];
    $update  = ['updated_at' => Slate_Ops_Utils::now_gmt()];

    foreach ($allowed as $col) {
      if (array_key_exists($col, $fields)) {
        $update[$col] = $fields[$col];
      }
    }

    if (count($update) === 1) return true; // only updated_at, nothing to do

    $result = $wpdb->update($t, $update, ['job_id' => (int) $job_id]);

    if ($log && $result !== false) {
      Slate_Ops_Activity_Log::append('job', $job_id, 'schedule_update', $fields);
    }

    return $result !== false;
  }
}
