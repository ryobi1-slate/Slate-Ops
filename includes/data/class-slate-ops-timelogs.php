<?php
/**
 * Slate_Ops_Time_Logs — time-segment data helpers.
 *
 * Wraps wp_slate_ops_time_segments (created by class-slate-ops-install.php).
 * The REST endpoints in class-slate-ops-rest.php continue to own write paths;
 * this class provides shared read helpers and a clean open/close API so future
 * callers don't duplicate query logic.
 *
 * Schema reference (time_segments):
 *   segment_id       BIGINT PK
 *   job_id           BIGINT
 *   user_id          BIGINT
 *   start_ts         DATETIME
 *   end_ts           DATETIME NULL (null = open/active)
 *   reason           VARCHAR(30) NULL
 *   note             TEXT NULL
 *   source           VARCHAR(20)  'timer'
 *   state            VARCHAR(10)  'active' | 'closed'
 *   approval_status  VARCHAR(12)  'approved' | 'pending' | 'voided'
 *   …audit fields…
 */
if (!defined('ABSPATH')) exit;

class Slate_Ops_Time_Logs {

  public static function table() {
    global $wpdb;
    return $wpdb->prefix . 'slate_ops_time_segments';
  }

  // ── Open segment ─────────────────────────────────────────────────

  /**
   * Start a new time segment for a user on a job.
   * Closes any previously open segment for that user first.
   *
   * @param int    $job_id
   * @param int    $user_id
   * @param string $reason  Allowed reason key.
   * @param string $note
   * @return int|false  segment_id or false.
   */
  public static function open( $job_id, $user_id, $reason = 'other', $note = '' ) {
    global $wpdb;

    // Close any open segments for this user.
    self::close_open_for_user($user_id);

    $now  = Slate_Ops_Utils::now_gmt();
    $data = [
      'job_id'          => (int)$job_id,
      'user_id'         => (int)$user_id,
      'start_ts'        => $now,
      'end_ts'          => null,
      'reason'          => Slate_Ops_Utils::sanitize_reason($reason),
      'note'            => $note ? sanitize_textarea_field($note) : null,
      'source'          => 'timer',
      'state'           => 'active',
      'approval_status' => 'approved',
      'created_by'      => $user_id,
      'created_at'      => $now,
      'updated_at'      => $now,
    ];

    $result = $wpdb->insert(self::table(), $data);
    if (!$result) return false;

    $segment_id = (int)$wpdb->insert_id;
    Slate_Ops_Activity_Log::log_time_event($job_id, $segment_id, 'start', ['reason' => $data['reason']]);
    return $segment_id;
  }

  /**
   * Close the active segment for a user.
   *
   * @param int $user_id
   * @return int|false  segment_id that was closed, or false.
   */
  public static function close_open_for_user( $user_id ) {
    global $wpdb;
    $t   = self::table();
    $now = Slate_Ops_Utils::now_gmt();

    $open = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT segment_id, job_id FROM $t WHERE user_id=%d AND end_ts IS NULL AND state='active' LIMIT 1",
        (int)$user_id
      ),
      ARRAY_A
    );
    if (!$open) return false;

    $wpdb->update(
      $t,
      ['end_ts' => $now, 'state' => 'closed', 'updated_at' => $now],
      ['segment_id' => $open['segment_id']]
    );

    Slate_Ops_Activity_Log::log_time_event($open['job_id'], $open['segment_id'], 'stop');
    return (int)$open['segment_id'];
  }

  // ── Read ─────────────────────────────────────────────────────────

  /**
   * Return the currently open segment for a user, or null.
   */
  public static function get_active_for_user( $user_id ) {
    global $wpdb;
    $t = self::table();
    return $wpdb->get_row(
      $wpdb->prepare(
        "SELECT s.*, j.so_number, j.customer_name
         FROM $t s
         LEFT JOIN {$wpdb->prefix}slate_ops_jobs j ON j.job_id = s.job_id
         WHERE s.user_id = %d AND s.end_ts IS NULL AND s.state = 'active'
         LIMIT 1",
        (int)$user_id
      ),
      ARRAY_A
    ) ?: null;
  }

  /**
   * Return all segments for a job.
   *
   * @param int  $job_id
   * @param bool $approved_only  Include only approved segments.
   * @return array
   */
  public static function for_job( $job_id, $approved_only = false ) {
    global $wpdb;
    $t     = self::table();
    $extra = $approved_only ? "AND s.approval_status = 'approved'" : '';
    return $wpdb->get_results(
      $wpdb->prepare(
        "SELECT s.*, COALESCE(u.display_name,'Unknown') AS user_name
         FROM $t s
         LEFT JOIN {$wpdb->users} u ON u.ID = s.user_id
         WHERE s.job_id = %d $extra
         ORDER BY s.start_ts ASC",
        (int)$job_id
      ),
      ARRAY_A
    ) ?: [];
  }

  /**
   * Summarise time for a job: total approved minutes, pending minutes, by-tech breakdown.
   *
   * @param int $job_id
   * @return array{approved_minutes_total: int, pending_minutes_total: int, by_tech: array}
   */
  public static function summary_for_job( $job_id ) {
    $segs = self::for_job($job_id, false);

    $approved_total  = 0;
    $pending_total   = 0;
    $by_tech         = [];

    foreach ($segs as $s) {
      if ($s['approval_status'] === 'voided') continue;

      $start   = strtotime($s['start_ts']);
      $end     = $s['end_ts'] ? strtotime($s['end_ts']) : time();
      $minutes = (int)round(($end - $start) / 60);

      $uid  = (int)$s['user_id'];
      $name = $s['user_name'] ?? 'Unknown';

      if (!isset($by_tech[$uid])) {
        $by_tech[$uid] = [
          'user_id'          => $uid,
          'user_name'        => $name,
          'approved_minutes' => 0,
          'pending_minutes'  => 0,
          'segment_count'    => 0,
          'last_activity'    => null,
        ];
      }

      $by_tech[$uid]['segment_count']++;
      $by_tech[$uid]['last_activity'] = $s['end_ts'] ?: $s['start_ts'];

      if ($s['approval_status'] === 'approved') {
        $approved_total          += $minutes;
        $by_tech[$uid]['approved_minutes'] += $minutes;
      } else {
        $pending_total           += $minutes;
        $by_tech[$uid]['pending_minutes']  += $minutes;
      }
    }

    return [
      'approved_minutes_total' => $approved_total,
      'pending_minutes_total'  => $pending_total,
      'by_tech'                => array_values($by_tech),
    ];
  }
}
