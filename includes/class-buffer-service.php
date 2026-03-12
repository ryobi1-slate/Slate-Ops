<?php
/**
 * Slate_Buffer_Service — TOC-style buffer logic for Phase 0.
 *
 * Computes effective due dates by subtracting shipping and QC buffers
 * from the promised ship date.  Simple backward scheduling anchor.
 *
 * Buffer defaults (configurable via wp_options):
 *   slate_ops_shipping_buffer_days  = 1
 *   slate_ops_qc_buffer_days        = 1
 */
if (!defined('ABSPATH')) exit;

class Slate_Buffer_Service {

  // ── Buffer defaults ──────────────────────────────────────────────

  public static function get_shipping_buffer_days() {
    return (int) get_option('slate_ops_shipping_buffer_days', 1);
  }

  public static function get_qc_buffer_days() {
    return (int) get_option('slate_ops_qc_buffer_days', 1);
  }

  public static function set_buffer_defaults( $shipping_days, $qc_days ) {
    update_option('slate_ops_shipping_buffer_days', max(0, (int) $shipping_days));
    update_option('slate_ops_qc_buffer_days', max(0, (int) $qc_days));
  }

  // ── Buffer calculations ──────────────────────────────────────────

  /**
   * Return the total buffer days (shipping + QC).
   */
  public static function get_total_buffer_days() {
    return self::get_shipping_buffer_days() + self::get_qc_buffer_days();
  }

  /**
   * Given a promised ship date, compute the latest date production must finish.
   *
   * Example:
   *   promised_date = Friday
   *   shipping_buffer = 1 day
   *   qc_buffer = 1 day
   *   → production must finish by Wednesday (Friday - 2)
   *
   * @param string $promised_date  Y-m-d
   * @return string  Y-m-d — latest production finish date.
   */
  public static function get_production_deadline( $promised_date ) {
    $total_buffer = self::get_total_buffer_days();
    $ts = strtotime($promised_date);
    return date('Y-m-d', strtotime("-{$total_buffer} days", $ts));
  }

  /**
   * Return buffer info for a job.
   *
   * @param array $job  Job row (must have promised_date or target_ship_date).
   * @return array {
   *   promised_date          string|null
   *   production_deadline    string|null
   *   shipping_buffer_days   int
   *   qc_buffer_days         int
   *   total_buffer_days      int
   *   days_until_deadline    int|null
   *   buffer_consumed_pct    float|null  (0–100+, 100+ = past deadline)
   * }
   */
  public static function get_job_buffer( array $job ) {
    $shipping = self::get_shipping_buffer_days();
    $qc       = self::get_qc_buffer_days();
    $total    = $shipping + $qc;

    $promised = $job['promised_date'] ?: $job['target_ship_date'] ?: null;

    if (!$promised) {
      return [
        'promised_date'       => null,
        'production_deadline' => null,
        'shipping_buffer_days'=> $shipping,
        'qc_buffer_days'      => $qc,
        'total_buffer_days'   => $total,
        'days_until_deadline' => null,
        'buffer_consumed_pct' => null,
      ];
    }

    $deadline      = self::get_production_deadline($promised);
    $today         = strtotime(wp_date('Y-m-d'));
    $deadline_ts   = strtotime($deadline);
    $days_remaining = (int) ceil(($deadline_ts - $today) / 86400);

    // Estimate when production should have started based on estimated_minutes.
    $est_mins = (int) ($job['estimated_minutes'] ?? 0);
    $buffer_consumed_pct = null;
    if ($est_mins > 0 && $total > 0) {
      // Rough: production window = days_remaining × 480min/day.
      $avail = $days_remaining * 480;
      $buffer_consumed_pct = $avail > 0
        ? round(max(0, 100 - ($avail / $est_mins) * 100), 1)
        : 100.0;
    }

    return [
      'promised_date'       => $promised,
      'production_deadline' => $deadline,
      'shipping_buffer_days'=> $shipping,
      'qc_buffer_days'      => $qc,
      'total_buffer_days'   => $total,
      'days_until_deadline' => $days_remaining,
      'buffer_consumed_pct' => $buffer_consumed_pct,
    ];
  }
}
