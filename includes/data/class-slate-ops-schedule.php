<?php
/**
 * Slate_Ops_Schedule — schedule-board data helpers.
 *
 * Sits on top of Slate_Ops_Jobs for schedule-specific queries (week boards,
 * bay occupancy, capacity checks).  Does not duplicate the jobs table — all
 * schedule fields live on wp_slate_ops_jobs; no extra table is needed.
 */
if (!defined('ABSPATH')) exit;

class Slate_Ops_Schedule {

  /**
   * Return the jobs + schedule fields for a given ISO week.
   *
   * @param int $year   e.g. 2026
   * @param int $week   1-53 (ISO)
   * @return array  Keyed by job_id.
   */
  public static function get_week( $year, $week ) {
    $range = self::week_range($year, $week);
    return Slate_Ops_Jobs::get_scheduled_in_range($range['from'], $range['to']);
  }

  /**
   * Return start (Mon) and end (Sun) dates for an ISO week.
   *
   * @return array{from: string, to: string}  Y-m-d strings.
   */
  public static function week_range( $year, $week ) {
    $dto = new DateTime();
    $dto->setISODate((int)$year, (int)$week, 1); // Monday
    $from = $dto->format('Y-m-d');
    $dto->modify('+6 days');
    $to = $dto->format('Y-m-d');
    return compact('from', 'to');
  }

  /**
   * Count scheduled jobs per bay for a given date range.
   *
   * Returns array<bay_name, int>.
   */
  public static function bay_load( $from, $to ) {
    global $wpdb;
    $t = $wpdb->prefix . 'slate_ops_jobs';
    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT work_center, COUNT(*) AS cnt
         FROM $t
         WHERE archived_at IS NULL
           AND scheduled_start IS NOT NULL
           AND DATE(scheduled_start) <= %s
           AND DATE(COALESCE(scheduled_finish, scheduled_start)) >= %s
           AND work_center IS NOT NULL
         GROUP BY work_center",
        $to,
        $from
      ),
      ARRAY_A
    ) ?: [];

    $out = [];
    foreach ($rows as $row) {
      $out[$row['work_center']] = (int)$row['cnt'];
    }
    return $out;
  }

  /**
   * Apply a bulk schedule update (used by the REST endpoint and any CLI tools).
   *
   * @param array $updates  [{job_id, scheduled_start?, scheduled_finish?, assigned_user_id?, work_center?, estimated_minutes?}, …]
   * @return array{saved: int[], errors: array[]}
   */
  public static function apply_bulk( array $updates ) {
    $saved  = [];
    $errors = [];

    foreach ($updates as $item) {
      $job_id = isset($item['job_id']) ? (int)$item['job_id'] : 0;
      if ($job_id <= 0) {
        $errors[] = ['job_id' => 0, 'message' => 'Missing job_id'];
        continue;
      }

      $fields = array_intersect_key($item, array_flip([
        'scheduled_start', 'scheduled_finish', 'assigned_user_id',
        'work_center', 'estimated_minutes',
      ]));

      if (empty($fields)) {
        $errors[] = ['job_id' => $job_id, 'message' => 'No schedulable fields provided'];
        continue;
      }

      $ok = Slate_Ops_Jobs::update_schedule($job_id, $fields, true);
      if ($ok) {
        $saved[] = $job_id;
      } else {
        $errors[] = ['job_id' => $job_id, 'message' => 'DB update failed'];
      }
    }

    return compact('saved', 'errors');
  }
}
