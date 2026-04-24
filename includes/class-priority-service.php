<?php
/**
 * Slate_Priority_Service — compute job priority scores.
 *
 * Priority score is a transparent integer used to sort jobs in the scheduler queue.
 * Higher score = higher priority.
 *
 * Score inputs (additive):
 *   +40  due within 3 days
 *   +25  due within 7 days
 *   +30  constraint buffer under 20% (promised_date - today < 20% of total buffer)
 *   +20  manual priority = 1 (expedite)
 *   +10  manual priority = 2
 *    +0  manual priority = 3 (default)
 *    -5  manual priority = 4
 *   -10  manual priority = 5 (low)
 *   +5   job age > 14 days (sitting too long)
 *   -9999 on hold (delay_reason set) — sorts to bottom
 */
if (!defined('ABSPATH')) exit;

class Slate_Priority_Service {

  /**
   * Compute a priority score for one job row.
   *
   * @param array $job  Assoc row from jobs table.
   * @return int  Score (higher = more urgent).
   */
  public static function compute_score( array $job ) {
    $score = 0;
    $today = strtotime(wp_date('Y-m-d'));

    // Hard disqualifiers: on hold sorts to bottom.
    if (!empty($job['delay_reason'])) {
      return -9999;
    }

    // Due-date urgency — use promised_date then target_ship_date.
    $due_date_str = $job['promised_date'] ?: $job['target_ship_date'] ?: null;
    if ($due_date_str) {
      $due = strtotime($due_date_str);
      $days_until = (int) ceil(($due - $today) / 86400);

      if ($days_until <= 3) {
        $score += 40;
      } elseif ($days_until <= 7) {
        $score += 25;
      }

      // Constraint buffer penetration: if estimated_minutes are set and
      // time remaining is tight relative to work needed.
      $wc_minutes = (int) ($job['constraint_minutes_required'] ?: $job['estimated_minutes'] ?: 0);
      if ($wc_minutes > 0 && $days_until > 0) {
        // Available shop minutes = days_until × 480 (rough 8h day).
        $available_shop_minutes = $days_until * 480;
        $buffer_pct = ($available_shop_minutes - $wc_minutes) / max(1, $available_shop_minutes) * 100;
        if ($buffer_pct < 20) {
          $score += 30;
        }
      }
    }

    // Manual priority tier.
    $manual = (int) ($job['priority'] ?? 3);
    $priority_bonus = [1 => 20, 2 => 10, 3 => 0, 4 => -5, 5 => -10];
    $score += $priority_bonus[$manual] ?? 0;

    // Age bonus: if job has been waiting over 14 days.
    if (!empty($job['ready_queue_entered_at'])) {
      $entered = strtotime($job['ready_queue_entered_at']);
      $days_waiting = (int) floor(($today - $entered) / 86400);
      if ($days_waiting > 14) {
        $score += 5;
      }
    }

    return $score;
  }

  /**
   * Recompute and persist priority_score for all schedulable jobs.
   *
   * @return int  Number of jobs updated.
   */
  public static function refresh_scores() {
    global $wpdb;

    $t = $wpdb->prefix . 'slate_ops_jobs';
    $now = Slate_Ops_Utils::now_gmt();

    $jobs = $wpdb->get_results(
      "SELECT job_id, priority, promised_date, target_ship_date,
              constraint_minutes_required, estimated_minutes,
              ready_queue_entered_at, delay_reason, priority_score
       FROM $t
       WHERE archived_at IS NULL
         AND status IN ('INTAKE','READY_FOR_BUILD','QUEUED')
       LIMIT 1000",
      ARRAY_A
    ) ?: [];

    $updated = 0;
    foreach ($jobs as $job) {
      $score = self::compute_score($job);
      if ($score === (int) $job['priority_score']) continue;

      $wpdb->update(
        $t,
        ['priority_score' => $score, 'updated_at' => $now],
        ['job_id' => (int) $job['job_id']]
      );
      $updated++;
    }

    return $updated;
  }
}
