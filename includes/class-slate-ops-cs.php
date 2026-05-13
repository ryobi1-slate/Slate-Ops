<?php
/**
 * Slate Ops — CS / Supervisor Operations Dashboard data layer.
 *
 * The overview dashboard summarizes the same active Ops jobs that power the
 * Job Queue tab. The queue remains the source of truth for blocker review.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('Slate_Ops_Statuses')) {
  require_once SLATE_OPS_PATH . 'includes/class-slate-ops-statuses.php';
}
class Slate_Ops_CS {

  private static $jobs_cache = null;

  private static function active_statuses(): array {
    return [
      Slate_Ops_Statuses::INTAKE,
      Slate_Ops_Statuses::NEEDS_SO,
      Slate_Ops_Statuses::READY_FOR_BUILD,
      Slate_Ops_Statuses::SCHEDULED,
      Slate_Ops_Statuses::IN_PROGRESS,
      Slate_Ops_Statuses::BLOCKED,
      Slate_Ops_Statuses::QC,
      Slate_Ops_Statuses::AWAITING_PICKUP,
      Slate_Ops_Statuses::ON_HOLD,
      Slate_Ops_Statuses::QUEUED,
      Slate_Ops_Statuses::PENDING_QC,
      Slate_Ops_Statuses::READY_FOR_PICKUP,
      Slate_Ops_Statuses::DELAYED,
    ];
  }

  private static function active_jobs(): array {
    if (self::$jobs_cache !== null) return self::$jobs_cache;

    global $wpdb;
    $t = $wpdb->prefix . 'slate_ops_jobs';
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t));
    if ($exists !== $t) {
      self::$jobs_cache = [];
      return self::$jobs_cache;
    }

    $statuses = self::active_statuses();
    $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
    $sql = "SELECT job_id, so_number, customer_name, dealer_name, status, parts_status,
                   assigned_user_id, estimated_minutes, requested_date, promised_date,
                   target_ship_date, updated_at, created_at, block_reason, block_note,
                   queue_note
              FROM $t
             WHERE archived_at IS NULL
               AND status IN ($placeholders)
             ORDER BY updated_at DESC, job_id DESC";

    $rows = $wpdb->get_results($wpdb->prepare($sql, $statuses), ARRAY_A) ?: [];
    foreach ($rows as &$row) {
      $row['status'] = Slate_Ops_Statuses::normalize((string) ($row['status'] ?? ''));
      $row['parts_status'] = strtoupper((string) ($row['parts_status'] ?? ''));
    }
    unset($row);

    self::$jobs_cache = $rows;
    return self::$jobs_cache;
  }

  private static function count_where(callable $fn): int {
    return count(array_filter(self::active_jobs(), $fn));
  }

  private static function percent(int $count, int $total): int {
    if ($total <= 0) return 0;
    return max(0, min(100, (int) round(($count / $total) * 100)));
  }

  private static function is_parts_risk(array $job): bool {
    return in_array((string) ($job['parts_status'] ?? ''), ['NOT_READY', 'HOLD'], true);
  }

  private static function is_blocked(array $job): bool {
    return in_array((string) ($job['status'] ?? ''), [Slate_Ops_Statuses::BLOCKED, Slate_Ops_Statuses::ON_HOLD], true);
  }

  private static function is_missing_update(array $job): bool {
    return trim((string) ($job['queue_note'] ?? '')) === '';
  }

  private static function job_customer(array $job): string {
    $customer = trim((string) ($job['customer_name'] ?? ''));
    $dealer = trim((string) ($job['dealer_name'] ?? ''));
    if ($customer !== '' && $dealer !== '') return $customer . ' / ' . $dealer;
    return $customer !== '' ? $customer : ($dealer !== '' ? $dealer : 'Unknown customer');
  }

  private static function priority_row(array $job): array {
    $status = (string) ($job['status'] ?? '');
    $parts = (string) ($job['parts_status'] ?? '');
    $pill = 'neutral';
    $action = 'Review job';
    $owner = 'CS';
    $detail = trim((string) ($job['queue_note'] ?? ''));

    if (self::is_blocked($job)) {
      $pill = 'blocked';
      $owner = 'Supervisor';
      $action = 'Review blocker';
      $reason = trim((string) ($job['block_reason'] ?? ''));
      $note = trim((string) ($job['block_note'] ?? ''));
      $detail = $note !== '' ? $note : ($reason !== '' ? ucwords(str_replace('_', ' ', $reason)) : 'Blocked job needs review.');
    } elseif (in_array($parts, ['NOT_READY', 'HOLD'], true)) {
      $pill = 'parts';
      $action = $parts === 'HOLD' ? 'Resolve parts hold' : 'Confirm parts ETA';
      $detail = $detail !== '' ? $detail : 'Parts status is blocking work from starting.';
    } elseif ($status === Slate_Ops_Statuses::QC) {
      $pill = 'qc';
      $owner = 'Supervisor';
      $action = 'Closeout review';
      $detail = $detail !== '' ? $detail : 'Ready for closeout review.';
    } elseif ($status === Slate_Ops_Statuses::AWAITING_PICKUP) {
      $pill = 'pickup';
      $action = 'Notify customer';
      $detail = $detail !== '' ? $detail : 'Complete and awaiting pickup or delivery coordination.';
    } elseif ($status === Slate_Ops_Statuses::READY_FOR_BUILD) {
      $pill = 'ready';
      $action = 'Confirm queue';
      $detail = $detail !== '' ? $detail : 'Ready to build and visible for shop planning.';
    }

    return [
      'id'     => (string) (($job['so_number'] ?? '') ?: ('JOB-' . (int) ($job['job_id'] ?? 0))),
      'cust'   => self::job_customer($job),
      'status' => Slate_Ops_Statuses::label($status),
      'pill'   => $pill,
      'owner'  => $owner,
      'action' => $action,
      'detail' => $detail,
    ];
  }

  public static function get_kpis() {
    $jobs = self::active_jobs();
    return [
      'active'     => count($jobs),
      'parts'      => self::count_where(function ($j) { return self::is_parts_risk($j); }),
      'ready'      => self::count_where(function ($j) { return ($j['status'] ?? '') === Slate_Ops_Statuses::READY_FOR_BUILD; }),
      'inprogress' => self::count_where(function ($j) { return ($j['status'] ?? '') === Slate_Ops_Statuses::IN_PROGRESS; }),
      'qc'         => self::count_where(function ($j) { return ($j['status'] ?? '') === Slate_Ops_Statuses::QC; }),
      'pickup'     => self::count_where(function ($j) { return ($j['status'] ?? '') === Slate_Ops_Statuses::AWAITING_PICKUP; }),
      'blocked'    => self::count_where(function ($j) { return self::is_blocked($j); }),
      'updates'    => self::count_where(function ($j) { return self::is_missing_update($j); }),
    ];
  }

  public static function get_priorities() {
    $jobs = self::active_jobs();
    usort($jobs, function ($a, $b) {
      $rank = function ($j) {
        if (self::is_blocked($j)) return 1;
        if (self::is_parts_risk($j)) return 2;
        if (($j['status'] ?? '') === Slate_Ops_Statuses::QC) return 3;
        if (($j['status'] ?? '') === Slate_Ops_Statuses::AWAITING_PICKUP) return 4;
        if (($j['status'] ?? '') === Slate_Ops_Statuses::READY_FOR_BUILD) return 5;
        return 9;
      };
      $ra = $rank($a);
      $rb = $rank($b);
      if ($ra !== $rb) return $ra <=> $rb;
      return strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''));
    });

    return array_map(function ($job) { return self::priority_row($job); }, array_slice($jobs, 0, 4));
  }

  public static function get_health() {
    $k = self::get_kpis();
    $active = max(1, (int) $k['active']);
    return [
      ['label' => 'Blocker Review',     'value' => (int) $k['blocked'] . ' need review',      'pct' => self::percent((int) $k['blocked'], $active), 'tone' => $k['blocked'] ? 'alert' : 'good'],
      ['label' => 'Parts Risk',         'value' => (int) $k['parts'] . ' jobs affected',      'pct' => self::percent((int) $k['parts'], $active), 'tone' => $k['parts'] ? 'warn' : 'good'],
      ['label' => 'QC Load',            'value' => (int) $k['qc'] . ' pending closeout',      'pct' => self::percent((int) $k['qc'], $active), 'tone' => $k['qc'] ? 'warn' : 'good'],
      ['label' => 'Pickup Queue',       'value' => (int) $k['pickup'] . ' ready',             'pct' => self::percent((int) $k['pickup'], $active), 'tone' => 'good'],
      ['label' => 'Schedule Readiness', 'value' => (int) $k['ready'] . ' jobs cleared',       'pct' => self::percent((int) $k['ready'], $active), 'tone' => 'good'],
      ['label' => 'Update Discipline',  'value' => (int) $k['updates'] . ' jobs need notes',  'pct' => self::percent((int) $k['updates'], $active), 'tone' => $k['updates'] ? 'alert' : 'good'],
    ];
  }

  public static function get_parts() {
    $hold = self::count_where(function ($j) { return ($j['parts_status'] ?? '') === 'HOLD'; });
    $not_ready = self::count_where(function ($j) { return ($j['parts_status'] ?? '') === 'NOT_READY'; });
    $partial = self::count_where(function ($j) { return ($j['parts_status'] ?? '') === 'PARTIAL'; });

    return [
      ['name' => 'Parts on hold',     'sub' => 'Blocks tech start',         'count' => $hold,      'tone' => $hold ? 'alert' : 'zero'],
      ['name' => 'Parts not ready',   'sub' => 'Needs ETA or update',       'count' => $not_ready, 'tone' => $not_ready ? 'alert' : 'zero'],
      ['name' => 'Partial parts',     'sub' => 'Can start with caution',    'count' => $partial,   'tone' => $partial ? '' : 'zero'],
    ];
  }

  public static function get_qc() {
    $qc = self::count_where(function ($j) { return ($j['status'] ?? '') === Slate_Ops_Statuses::QC; });
    $pickup = self::count_where(function ($j) { return ($j['status'] ?? '') === Slate_Ops_Statuses::AWAITING_PICKUP; });

    return [
      ['name' => 'Ready to close',    'sub' => 'Awaiting CS closeout',     'count' => $qc,     'tone' => $qc ? 'alert' : 'zero'],
      ['name' => 'Ready to release',  'sub' => 'Cleared for pickup prep',  'count' => $pickup, 'tone' => $pickup ? 'good' : 'zero'],
    ];
  }

  public static function get_pickup() {
    $pickup = self::count_where(function ($j) { return ($j['status'] ?? '') === Slate_Ops_Statuses::AWAITING_PICKUP; });
    return [
      ['name' => 'Complete - Awaiting Pickup', 'sub' => 'Customer can be contacted', 'count' => $pickup, 'tone' => $pickup ? 'good' : 'zero'],
    ];
  }

  public static function get_subtab_counts() {
    $k = self::get_kpis();
    return [
      'intake'     => self::count_where(function ($j) { return in_array(($j['status'] ?? ''), [Slate_Ops_Statuses::INTAKE, Slate_Ops_Statuses::NEEDS_SO], true); }),
      'parts'      => (int) $k['parts'],
      'qc'         => (int) $k['qc'],
      'pickup'     => (int) $k['pickup'],
      'exceptions' => (int) $k['blocked'],
    ];
  }

  public static function get_payload() {
    $payload = [
      'kpis'          => self::get_kpis(),
      'priorities'    => self::get_priorities(),
      'health'        => self::get_health(),
      'parts'         => self::get_parts(),
      'qc'            => self::get_qc(),
      'pickup'        => self::get_pickup(),
      'subtab_counts' => self::get_subtab_counts(),
    ];
    return apply_filters('slate_ops_cs_refresh', $payload);
  }
}
