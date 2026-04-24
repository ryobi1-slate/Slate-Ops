<?php
/**
 * Slate_Capacity_Service — compute work center capacity, allocations, and overloads.
 *
 * All methods are static. Does not write to jobs — only reads jobs and writes
 * to the capacity_snapshots table for caching.
 *
 * Capacity model (Phase 0 — simple):
 *   - Each work center has a daily_capacity_minutes and weekly_capacity_minutes.
 *   - Allocated minutes = sum of estimated_minutes of SCHEDULED jobs assigned
 *     to that work center whose scheduled_start falls within the period.
 *   - Overload = allocated > capacity.
 *   - Jobs with scheduling_flag = OVERLOADED are flagged when their work center
 *     is overloaded for the day they are scheduled.
 */
if (!defined('ABSPATH')) exit;

class Slate_Capacity_Service {

  // ── Capacity reads ───────────────────────────────────────────────

  /**
   * Get capacity summary for all work centers for a date range.
   *
   * Returns an array keyed by wc_id:
   * [
   *   wc_id => [
   *     'wc_id'              => int,
   *     'wc_code'            => string,
   *     'display_name'       => string,
   *     'is_constraint'      => bool,
   *     'color'              => string,
   *     'capacity_minutes'   => int,   // weekly capacity
   *     'allocated_minutes'  => int,   // sum of estimated_minutes for scheduled jobs
   *     'available_minutes'  => int,   // capacity - allocated (may be negative)
   *     'overload_minutes'   => int,   // max(0, allocated - capacity)
   *     'utilization_pct'    => float, // 0–100+
   *     'is_overloaded'      => bool,
   *   ]
   * ]
   *
   * @param string $date_from  Y-m-d
   * @param string $date_to    Y-m-d
   */
  public static function get_summary( $date_from, $date_to ) {
    global $wpdb;

    $work_centers = Slate_Ops_Work_Centers::query(true);
    if (empty($work_centers)) return [];

    $jobs_table = $wpdb->prefix . 'slate_ops_jobs';

    // Get allocated minutes per work_center code for jobs scheduled in range.
    $allocated_by_code = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT work_center, SUM(COALESCE(estimated_minutes, 0)) AS allocated
         FROM $jobs_table
         WHERE archived_at IS NULL
           AND status IN ('QUEUED','IN_PROGRESS')
           AND work_center IS NOT NULL
           AND scheduled_start IS NOT NULL
           AND DATE(scheduled_start) <= %s
           AND DATE(COALESCE(scheduled_finish, scheduled_start)) >= %s
         GROUP BY work_center",
        $date_to,
        $date_from
      ),
      ARRAY_A
    ) ?: [];

    $alloc_map = [];
    foreach ($allocated_by_code as $row) {
      $alloc_map[ $row['work_center'] ] = (int) $row['allocated'];
    }

    $result = [];
    $threshold = (int) $wpdb->get_var("SELECT capacity_threshold_pct FROM {$wpdb->prefix}slate_ops_settings WHERE id=1") ?: 70;

    foreach ($work_centers as $wc) {
      $capacity  = (int) $wc['weekly_capacity_minutes'];
      $effective_capacity = round($capacity * ($threshold / 100));
      $allocated = (int) ($alloc_map[ $wc['wc_code'] ] ?? 0);
      $available = $effective_capacity - $allocated;
      $overload  = max(0, $allocated - $effective_capacity);
      $util_pct  = $capacity > 0 ? round(($allocated / $capacity) * 100, 1) : 0.0;

      $result[ (int) $wc['wc_id'] ] = [
        'wc_id'            => (int) $wc['wc_id'],
        'wc_code'          => $wc['wc_code'],
        'display_name'     => $wc['display_name'],
        'is_constraint'    => (bool) $wc['is_constraint'],
        'color'            => $wc['color'],
        'capacity_minutes' => $capacity,
        'effective_capacity_minutes' => $effective_capacity,
        'allocated_minutes'=> $allocated,
        'available_minutes'=> $available,
        'overload_minutes' => $overload,
        'utilization_pct'  => $util_pct,
        'is_overloaded'    => $overload > 0,
      ];
    }

    return $result;
  }

  /**
   * Return only work centers that are overloaded in the given range.
   */
  public static function get_overloads( $date_from, $date_to ) {
    $summary = self::get_summary($date_from, $date_to);
    return array_values(array_filter($summary, fn($s) => $s['is_overloaded']));
  }

  /**
   * Get constraint work center utilization (0–100+ pct) for a week.
   */
  public static function get_constraint_utilization( $date_from, $date_to ) {
    $summary    = self::get_summary($date_from, $date_to);
    $constraint = array_filter($summary, fn($s) => $s['is_constraint']);
    if (empty($constraint)) return null;
    return reset($constraint);
  }

  // ── Scheduling flag refresh ───────────────────────────────────────

  /**
   * Recompute scheduling_flag (ON_TIME | AT_RISK | LATE | OVERLOADED) for
   * all scheduled jobs, then write results back to the jobs table.
   *
   * Rules:
   *   LATE        — promised_date < today and job is not complete.
   *   OVERLOADED  — job's work center is overloaded in the scheduled week.
   *   AT_RISK     — promised_date within 3 days (but not yet late).
   *   ON_TIME     — everything else.
   *
   * @param string|null $date_from  Limit to jobs starting >= this date. Default: today.
   * @param string|null $date_to    Limit to jobs starting <= this date. Default: +60 days.
   * @return int  Number of jobs updated.
   */
  public static function refresh_flags( $date_from = null, $date_to = null ) {
    global $wpdb;

    $today = wp_date('Y-m-d');
    $from  = $date_from ?: $today;
    $to    = $date_to   ?: date('Y-m-d', strtotime($today . ' +60 days'));

    $jobs_table = $wpdb->prefix . 'slate_ops_jobs';

    // Fetch all non-archived scheduled jobs in the range.
    $jobs = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT job_id, work_center, scheduled_start, scheduled_finish,
                promised_date, target_ship_date, status, scheduling_flag
         FROM $jobs_table
         WHERE archived_at IS NULL
           AND status IN ('QUEUED','IN_PROGRESS','READY_FOR_BUILD')
           AND (scheduled_start IS NULL OR DATE(scheduled_start) <= %s)
         LIMIT 1000",
        $to
      ),
      ARRAY_A
    ) ?: [];

    if (empty($jobs)) return 0;

    // Build overloaded work_center set for the range.
    $overloads   = self::get_overloads($from, $to);
    $overloaded_codes = array_column($overloads, 'wc_code');

    $updated = 0;
    $now     = Slate_Ops_Utils::now_gmt();

    foreach ($jobs as $job) {
      $flag = self::compute_flag($job, $today, $overloaded_codes);

      if ($flag === $job['scheduling_flag']) continue; // no change needed

      $wpdb->update(
        $jobs_table,
        ['scheduling_flag' => $flag, 'updated_at' => $now],
        ['job_id' => (int) $job['job_id']]
      );
      $updated++;
    }

    return $updated;
  }

  /**
   * Compute the scheduling flag for a single job row.
   *
   * @param array    $job               Assoc row from jobs table.
   * @param string   $today             Y-m-d for today.
   * @param string[] $overloaded_codes  Array of work_center codes that are overloaded.
   * @return string  ON_TIME | AT_RISK | LATE | OVERLOADED
   */
  public static function compute_flag( array $job, $today, array $overloaded_codes ) {
    $due = $job['promised_date'] ?: $job['target_ship_date'];

    if ($due) {
      if ($due < $today) {
        return 'LATE';
      }
      $days_until = (int) ceil((strtotime($due) - strtotime($today)) / 86400);
      if ($days_until <= 3) {
        return 'AT_RISK';
      }
    }

    if (!empty($job['work_center']) && in_array($job['work_center'], $overloaded_codes, true)) {
      return 'OVERLOADED';
    }

    return 'ON_TIME';
  }

  // ── Snapshot cache ────────────────────────────────────────────────

  /**
   * Persist capacity snapshots for each work center for each day in range.
   * Used by the dashboard so it doesn't recalculate on every load.
   *
   * @param string $date_from  Y-m-d
   * @param string $date_to    Y-m-d
   */
  public static function refresh_snapshots( $date_from, $date_to ) {
    global $wpdb;

    $snap_table   = $wpdb->prefix . 'slate_ops_capacity_snapshots';
    $work_centers = Slate_Ops_Work_Centers::query(true);
    if (empty($work_centers)) return;

    $jobs_table = $wpdb->prefix . 'slate_ops_jobs';
    $now        = Slate_Ops_Utils::now_gmt();

    // Walk day by day.
    $cursor = strtotime($date_from);
    $end    = strtotime($date_to);

    while ($cursor <= $end) {
      $day = date('Y-m-d', $cursor);

      // Get allocated minutes per work_center code for this day.
      $alloc_rows = $wpdb->get_results(
        $wpdb->prepare(
          "SELECT work_center, SUM(COALESCE(estimated_minutes, 0)) AS allocated
           FROM $jobs_table
           WHERE archived_at IS NULL
             AND status IN ('QUEUED','IN_PROGRESS')
             AND work_center IS NOT NULL
             AND DATE(scheduled_start) <= %s
             AND DATE(COALESCE(scheduled_finish, scheduled_start)) >= %s
           GROUP BY work_center",
          $day, $day
        ),
        ARRAY_A
      ) ?: [];

      $alloc_map = [];
      foreach ($alloc_rows as $row) {
        $alloc_map[ $row['work_center'] ] = (int) $row['allocated'];
      }

      $threshold = (int) $wpdb->get_var("SELECT capacity_threshold_pct FROM {$wpdb->prefix}slate_ops_settings WHERE id=1") ?: 70;

      foreach ($work_centers as $wc) {
        $capacity  = (int) $wc['daily_capacity_minutes'];
        $effective_capacity = round($capacity * ($threshold / 100));
        $allocated = (int) ($alloc_map[ $wc['wc_code'] ] ?? 0);
        $available = $effective_capacity - $allocated;
        $overload  = max(0, $allocated - $effective_capacity);

        // Upsert snapshot row.
        $wpdb->query(
          $wpdb->prepare(
            "INSERT INTO $snap_table
               (snapshot_date, work_center_id, capacity_minutes, allocated_minutes, available_minutes, overload_minutes, created_at)
             VALUES (%s, %d, %d, %d, %d, %d, %s)
             ON DUPLICATE KEY UPDATE
               capacity_minutes   = VALUES(capacity_minutes),
               allocated_minutes  = VALUES(allocated_minutes),
               available_minutes  = VALUES(available_minutes),
               overload_minutes   = VALUES(overload_minutes),
               created_at         = VALUES(created_at)",
            $day,
            (int) $wc['wc_id'],
            $capacity,
            $allocated,
            $available,
            $overload,
            $now
          )
        );
      }

      $cursor = strtotime('+1 day', $cursor);
    }
  }
}
