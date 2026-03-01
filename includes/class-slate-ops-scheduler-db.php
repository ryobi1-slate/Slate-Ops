<?php
if (!defined('ABSPATH')) exit;

/**
 * Manages the Phase 0 scheduler tables: wp_slate_jobs and wp_slate_weeks.
 * These are intentionally separate from wp_slate_ops_jobs so Phase 1 can
 * enforce gates without restructuring the existing ops workflow.
 */
class Slate_Ops_Scheduler_DB {

  public static function create_tables() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();

    $jobs  = $wpdb->prefix . 'slate_jobs';
    $weeks = $wpdb->prefix . 'slate_weeks';

    // Phase 0 jobs table — keep this schema stable; Phase 1 adds to it.
    $sql_jobs = "CREATE TABLE $jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_uid VARCHAR(36) NOT NULL,
  customer_name VARCHAR(255) NOT NULL DEFAULT '',
  job_type VARCHAR(20) NOT NULL DEFAULT 'UPFIT',
  planned_minutes INT NOT NULL DEFAULT 0,
  so_number VARCHAR(50) NULL,
  scheduling_status VARCHAR(30) NOT NULL DEFAULT 'INTAKE',
  target_week_id VARCHAR(20) NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY job_uid_idx (job_uid),
  KEY scheduling_status_idx (scheduling_status),
  KEY target_week_id_idx (target_week_id),
  KEY so_number_idx (so_number)
) $charset_collate;";

    // Phase 0 weeks table — capacity fields stored now, enforced in Phase 1.
    $sql_weeks = "CREATE TABLE $weeks (
  week_id VARCHAR(20) NOT NULL,
  label VARCHAR(100) NOT NULL DEFAULT '',
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  total_capacity_minutes INT NOT NULL DEFAULT 0,
  planned_limit_minutes INT NOT NULL DEFAULT 0,
  current_allocated_minutes INT NOT NULL DEFAULT 0,
  PRIMARY KEY  (week_id),
  KEY start_date_idx (start_date),
  KEY end_date_idx (end_date)
) $charset_collate;";

    dbDelta($sql_jobs);
    dbDelta($sql_weeks);
  }

  /**
   * Seed rolling weeks from current ISO week through +$weeks_ahead.
   * Skips weeks that already exist so re-running is safe.
   */
  public static function seed_weeks($weeks_ahead = 12) {
    global $wpdb;
    $table = $wpdb->prefix . 'slate_weeks';

    $tz    = new DateTimeZone('America/Los_Angeles');
    $today = new DateTime('now', $tz);

    // Rewind to Monday of current ISO week.
    $dow = (int) $today->format('N'); // 1 = Mon … 7 = Sun
    if ($dow > 1) {
      $today->modify('-' . ($dow - 1) . ' days');
    }
    $monday = $today; // $today is now Monday of current week

    for ($i = 0; $i <= $weeks_ahead; $i++) {
      $week_start = clone $monday;
      if ($i > 0) {
        $week_start->modify("+{$i} weeks");
      }
      $week_end = clone $week_start;
      $week_end->modify('+6 days');

      // ISO year and week number (format 'o' gives ISO year, 'W' gives ISO week).
      $iso_year = $week_start->format('o');
      $iso_week = $week_start->format('W');
      $week_id  = 'WK-' . $iso_year . '-' . str_pad($iso_week, 2, '0', STR_PAD_LEFT);
      $label    = 'Week of ' . $week_start->format('M j, Y');

      $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT week_id FROM $table WHERE week_id = %s",
        $week_id
      ));

      if (!$exists) {
        $wpdb->insert($table, [
          'week_id'                  => $week_id,
          'label'                    => $label,
          'start_date'               => $week_start->format('Y-m-d'),
          'end_date'                 => $week_end->format('Y-m-d'),
          'total_capacity_minutes'   => 0,
          'planned_limit_minutes'    => 0,
          'current_allocated_minutes' => 0,
        ]);
      }
    }
  }

  /**
   * Recalculate current_allocated_minutes for a week from the jobs table.
   * Safer than increment/decrement because it can't drift out of sync.
   */
  public static function recalc_week_allocation($week_id) {
    global $wpdb;
    $weeks_tbl = $wpdb->prefix . 'slate_weeks';
    $jobs_tbl  = $wpdb->prefix . 'slate_jobs';

    $sum = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COALESCE(SUM(planned_minutes), 0)
         FROM $jobs_tbl
        WHERE target_week_id = %s
          AND scheduling_status = 'SCHEDULED'",
      $week_id
    ));

    $wpdb->update(
      $weeks_tbl,
      ['current_allocated_minutes' => $sum],
      ['week_id' => $week_id]
    );
  }

  /** Returns true if both Phase 0 tables exist. */
  public static function tables_exist() {
    global $wpdb;
    $jobs  = $wpdb->prefix . 'slate_jobs';
    $weeks = $wpdb->prefix . 'slate_weeks';
    return [
      'slate_jobs'  => ($wpdb->get_var("SHOW TABLES LIKE '$jobs'")  === $jobs),
      'slate_weeks' => ($wpdb->get_var("SHOW TABLES LIKE '$weeks'") === $weeks),
    ];
  }
}
