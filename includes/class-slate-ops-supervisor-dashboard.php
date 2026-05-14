<?php
/**
 * Slate Ops — Supervisor Dashboard data layer.
 *
 * Phase 2A: live, read-only Supervisor payload derived from Ops jobs.
 * Write actions remain intentionally disabled in the UI until safe endpoints
 * are wired for each supervisor workflow.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('Slate_Ops_Statuses')) {
  require_once SLATE_OPS_PATH . 'includes/class-slate-ops-statuses.php';
}

class Slate_Ops_Supervisor_Dashboard {

  private static $live_jobs_cache = null;
  private static $qc_cache = null;
  private static $time_cache = null;

  public static function get_tabs() {
    return [
      ['id' => 'overview', 'label' => 'Overview'],
      ['id' => 'blocked', 'label' => 'Blocked Jobs', 'count_key' => 'blocked'],
      ['id' => 'schedule', 'label' => 'Schedule'],
      ['id' => 'techs', 'label' => 'Techs'],
      ['id' => 'qc', 'label' => 'QC / Rework', 'count_key' => 'qc'],
      ['id' => 'ready', 'label' => 'Ready Queue', 'count_key' => 'ready'],
    ];
  }

  public static function get_blocked_categories() {
    return [
      'Parts',
      'Scope / Engineering',
      'Customer / Dealer',
      'QC Issue',
      'Tooling / Bay',
      'Tech Needs Help',
      'Admin / Paperwork',
    ];
  }

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

  private static function table_exists(string $table): bool {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
  }

  private static function live_rows(): array {
    if (self::$live_jobs_cache !== null) return self::$live_jobs_cache;

    global $wpdb;
    $table = $wpdb->prefix . 'slate_ops_jobs';
    if (!self::table_exists($table)) {
      self::$live_jobs_cache = [];
      return self::$live_jobs_cache;
    }

    $statuses = self::active_statuses();
    $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
    $sql = "SELECT j.*, u.display_name AS assigned_name
              FROM $table j
              LEFT JOIN {$wpdb->users} u ON u.ID = j.assigned_user_id
             WHERE j.archived_at IS NULL
               AND j.status IN ($placeholders)
             ORDER BY
               CASE
                 WHEN j.status IN ('BLOCKED','DELAYED','ON_HOLD') THEN 1
                 WHEN j.status IN ('QC','PENDING_QC') THEN 2
                 WHEN j.status = 'IN_PROGRESS' THEN 3
                 WHEN j.status IN ('SCHEDULED','QUEUED') THEN 4
                 WHEN j.status = 'READY_FOR_BUILD' THEN 5
                 ELSE 9
               END,
               j.priority ASC,
               COALESCE(j.promised_date, j.target_ship_date, DATE(j.scheduled_start), j.requested_date) ASC,
               j.updated_at DESC
             LIMIT %d";

    $args = array_merge($statuses, [500]);
    $rows = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) ?: [];
    foreach ($rows as &$row) {
      $row['status'] = Slate_Ops_Statuses::normalize((string) ($row['status'] ?? ''));
      $row['parts_status'] = strtoupper((string) ($row['parts_status'] ?? ''));
      $row['assigned_user_id'] = isset($row['assigned_user_id']) ? (int) $row['assigned_user_id'] : 0;
    }
    unset($row);

    self::$live_jobs_cache = $rows;
    return self::$live_jobs_cache;
  }

  private static function qc_rows_by_job(): array {
    if (self::$qc_cache !== null) return self::$qc_cache;

    global $wpdb;
    $table = $wpdb->prefix . 'slate_ops_qc_records';
    if (!self::table_exists($table)) {
      self::$qc_cache = [];
      return self::$qc_cache;
    }

    $rows = $wpdb->get_results(
      "SELECT q.*
         FROM $table q
         INNER JOIN (
           SELECT job_id, MAX(created_at) AS latest_at
             FROM $table
            GROUP BY job_id
         ) latest ON latest.job_id = q.job_id AND latest.latest_at = q.created_at",
      ARRAY_A
    ) ?: [];

    $by_job = [];
    foreach ($rows as $row) {
      $by_job[(int) $row['job_id']] = $row;
    }

    self::$qc_cache = $by_job;
    return self::$qc_cache;
  }

  private static function today_minutes_by_user(): array {
    if (self::$time_cache !== null) return self::$time_cache;

    global $wpdb;
    $table = $wpdb->prefix . 'slate_ops_time_segments';
    if (!self::table_exists($table)) {
      self::$time_cache = [];
      return self::$time_cache;
    }

    $start = gmdate('Y-m-d 00:00:00');
    $end = gmdate('Y-m-d 23:59:59');
    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT user_id,
                SUM(TIMESTAMPDIFF(MINUTE, start_ts, COALESCE(end_ts, UTC_TIMESTAMP()))) AS minutes
           FROM $table
          WHERE start_ts >= %s
            AND start_ts <= %s
            AND approval_status != 'voided'
          GROUP BY user_id",
        $start,
        $end
      ),
      ARRAY_A
    ) ?: [];

    $minutes = [];
    foreach ($rows as $row) {
      $minutes[(int) $row['user_id']] = max(0, (int) round((float) ($row['minutes'] ?? 0)));
    }

    self::$time_cache = $minutes;
    return self::$time_cache;
  }

  private static function count_where(array $items, callable $callback): int {
    return count(array_filter($items, $callback));
  }

  private static function initials(string $name): string {
    $name = trim($name);
    if ($name === '') return 'UN';
    $parts = preg_split('/\s+/', $name);
    $first = strtoupper(substr((string) ($parts[0] ?? 'U'), 0, 1));
    $last = count($parts) > 1 ? strtoupper(substr((string) end($parts), 0, 1)) : '';
    return $first . ($last ?: 'N');
  }

  private static function humanize(string $value): string {
    $value = trim($value);
    if ($value === '') return '—';
    return ucwords(strtolower(str_replace(['_', '-'], ' ', $value)));
  }

  private static function job_id_label(array $row): string {
    $so = trim((string) ($row['so_number'] ?? ''));
    if ($so !== '') return $so;
    return 'Job ' . (int) ($row['job_id'] ?? 0);
  }

  private static function job_secondary_label(array $row): string {
    $quote = trim((string) ($row['quote_number'] ?? ''));
    if ($quote !== '') return $quote;
    return 'Job #' . (int) ($row['job_id'] ?? 0);
  }

  private static function date_source(array $row): string {
    foreach (['promised_date', 'target_ship_date', 'scheduled_finish', 'scheduled_start', 'requested_date'] as $key) {
      $value = trim((string) ($row[$key] ?? ''));
      if ($value !== '') return $value;
    }
    return '';
  }

  private static function due_label(array $row): string {
    $date = self::date_source($row);
    if ($date === '') return '—';
    $ts = strtotime($date);
    if (!$ts) return '—';
    return wp_date('M j', $ts);
  }

  private static function due_delta(array $row): int {
    $date = self::date_source($row);
    if ($date === '') return 999;
    $ts = strtotime(substr($date, 0, 10));
    if (!$ts) return 999;
    $today = strtotime(current_time('Y-m-d'));
    return (int) floor(($ts - $today) / DAY_IN_SECONDS);
  }

  private static function duration_since(?string $date): string {
    $date = trim((string) $date);
    if ($date === '') return '—';
    $ts = strtotime($date);
    if (!$ts) return '—';
    $seconds = max(0, time() - $ts);
    $days = (int) floor($seconds / DAY_IN_SECONDS);
    $hours = (int) floor(($seconds % DAY_IN_SECONDS) / HOUR_IN_SECONDS);
    if ($days > 0) return $days . 'd ' . $hours . 'h';
    if ($hours > 0) return $hours . 'h';
    return max(1, (int) floor($seconds / MINUTE_IN_SECONDS)) . 'm';
  }

  private static function minutes_label(int $minutes): string {
    $minutes = max(0, $minutes);
    $hours = (int) floor($minutes / 60);
    $mins = $minutes % 60;
    return $hours . 'h ' . str_pad((string) $mins, 2, '0', STR_PAD_LEFT) . 'm';
  }

  private static function status_slug(string $status, array $row): string {
    if (self::is_rework($row)) return 'rework';
    switch (Slate_Ops_Statuses::normalize($status)) {
      case Slate_Ops_Statuses::BLOCKED:
        return 'blocked';
      case Slate_Ops_Statuses::ON_HOLD:
        return 'hold';
      case Slate_Ops_Statuses::IN_PROGRESS:
        return 'in-progress';
      case Slate_Ops_Statuses::QC:
        return 'qc';
      case Slate_Ops_Statuses::AWAITING_PICKUP:
        return 'complete';
      case Slate_Ops_Statuses::READY_FOR_BUILD:
      case Slate_Ops_Statuses::SCHEDULED:
        return 'ready';
      default:
        return 'pending';
    }
  }

  private static function parts_label(string $parts_status): string {
    switch (strtoupper($parts_status)) {
      case 'READY':
        return 'Complete';
      case 'PARTIAL':
        return 'Partial but schedulable';
      case 'HOLD':
        return 'Held';
      case 'NOT_READY':
        return 'Not ready';
      default:
        return self::humanize($parts_status);
    }
  }

  private static function parts_risk(string $parts_status): string {
    switch (strtoupper($parts_status)) {
      case 'HOLD':
      case 'NOT_READY':
        return 'high';
      case 'PARTIAL':
        return 'med';
      default:
        return 'low';
    }
  }

  private static function qc_state(array $row): string {
    $status = (string) ($row['status'] ?? '');
    $qc = self::qc_rows_by_job()[(int) ($row['job_id'] ?? 0)] ?? null;
    $result = strtoupper((string) ($qc['result'] ?? ''));

    if ($result === 'FAIL') return 'Failed QC';
    if ($status === Slate_Ops_Statuses::QC || $result === 'SUBMITTED') return 'Pending QC';
    if ($result === 'PASS' || $status === Slate_Ops_Statuses::AWAITING_PICKUP) return 'Signed off';
    return 'Not started';
  }

  private static function is_rework(array $row): bool {
    $qc = self::qc_rows_by_job()[(int) ($row['job_id'] ?? 0)] ?? null;
    return (string) ($row['status'] ?? '') === Slate_Ops_Statuses::IN_PROGRESS
      && strtoupper((string) ($qc['result'] ?? '')) === 'FAIL';
  }

  private static function is_blocked(array $job): bool {
    return !empty($job['blocked_category']);
  }

  private static function blocked_category(array $row): ?string {
    $status = (string) ($row['status'] ?? '');
    $parts = strtoupper((string) ($row['parts_status'] ?? ''));
    $reason = strtolower(trim(implode(' ', [
      (string) ($row['block_reason'] ?? ''),
      (string) ($row['block_note'] ?? ''),
      (string) ($row['hold_reason'] ?? ''),
      (string) ($row['hold_note'] ?? ''),
      (string) ($row['queue_note'] ?? ''),
    ])));

    if (self::is_rework($row) || $status === Slate_Ops_Statuses::QC && strpos($reason, 'fail') !== false) return 'QC Issue';
    if ($status === Slate_Ops_Statuses::BLOCKED || $status === Slate_Ops_Statuses::ON_HOLD) {
      $block_reason = strtoupper((string) ($row['block_reason'] ?? ''));
      $mapped = [
        'WAITING_ON_PARTS' => 'Parts',
        'TECH_SUPPORT_NEEDED' => 'Tech Needs Help',
        'VEHICLE_ISSUE' => 'Customer / Dealer',
        'CUSTOMER_DEALER_QUESTION' => 'Customer / Dealer',
        'SCOPE_OR_JOB_ISSUE' => 'Scope / Engineering',
        'QUALITY_REWORK_ISSUE' => 'QC Issue',
        'OTHER_ISSUE' => 'Admin / Paperwork',
      ];
      if (isset($mapped[$block_reason])) return $mapped[$block_reason];
      if ($parts === 'HOLD' || $parts === 'NOT_READY' || str_contains($reason, 'part') || str_contains($reason, 'vendor')) return 'Parts';
      if (str_contains($reason, 'scope') || str_contains($reason, 'engineering') || str_contains($reason, 'build sheet')) return 'Scope / Engineering';
      if (str_contains($reason, 'customer') || str_contains($reason, 'dealer') || str_contains($reason, 'sign-off')) return 'Customer / Dealer';
      if (str_contains($reason, 'qc') || str_contains($reason, 'rework') || str_contains($reason, 'fail')) return 'QC Issue';
      if (str_contains($reason, 'bay') || str_contains($reason, 'tool') || str_contains($reason, 'lift')) return 'Tooling / Bay';
      if (str_contains($reason, 'tech') || str_contains($reason, 'help') || !empty($row['awaiting_direction'])) return 'Tech Needs Help';
      return 'Admin / Paperwork';
    }

    if ($parts === 'HOLD' || ($parts === 'NOT_READY' && in_array($status, [Slate_Ops_Statuses::SCHEDULED, Slate_Ops_Statuses::IN_PROGRESS], true))) {
      return 'Parts';
    }

    return null;
  }

  private static function blocked_reason(array $row, ?string $category): ?string {
    if (!$category) return null;
    foreach (['block_note', 'hold_note', 'queue_note', 'schedule_notes', 'notes'] as $key) {
      $value = trim((string) ($row[$key] ?? ''));
      if ($value !== '') return $value;
    }
    $reason = trim((string) (($row['block_reason'] ?? '') ?: ($row['hold_reason'] ?? '')));
    if ($reason !== '') return self::humanize($reason);
    if ($category === 'Parts') return 'Parts readiness needs review before the next constrained stage.';
    return $category . ' needs supervisor review.';
  }

  private static function step_label(array $row): string {
    $task = trim((string) ($row['current_task_summary'] ?? ''));
    if ($task !== '') return $task;
    $work_center = trim((string) ($row['work_center'] ?? ''));
    if ($work_center !== '') return self::humanize($work_center);
    return Slate_Ops_Statuses::label((string) ($row['status'] ?? ''));
  }

  private static function issue_and_action(array $row, ?string $category): array {
    $status = (string) ($row['status'] ?? '');
    $parts = strtoupper((string) ($row['parts_status'] ?? ''));
    $note = trim((string) (($row['queue_note'] ?? '') ?: ($row['schedule_notes'] ?? '')));

    if ($category) {
      return [
        $note !== '' ? $note : $category . ' needs supervisor review.',
        $category,
        $category === 'Parts' ? 'Review parts ETA' : 'Review blocker',
      ];
    }
    if (self::is_rework($row)) return ['QC failed, rework required.', 'QC rework', 'Send back to tech'];
    if ($status === Slate_Ops_Statuses::QC) return [$note !== '' ? $note : 'Pending supervisor QC sign-off.', 'Review QC', 'Review QC'];
    if ($status === Slate_Ops_Statuses::AWAITING_PICKUP) return [$note !== '' ? $note : 'Ready for pickup closeout.', 'Ready closeout', 'Approve closeout'];
    if ((int) ($row['assigned_user_id'] ?? 0) <= 0) return [$note !== '' ? $note : 'Needs tech ownership.', 'Assign tech', 'Assign tech'];
    if ($parts === 'PARTIAL') return [$note !== '' ? $note : 'Partial parts, next stage can still start.', 'Partial parts', 'Review schedule'];
    if ($status === Slate_Ops_Statuses::READY_FOR_BUILD) return [$note !== '' ? $note : 'Ready for bay planning.', 'Schedule into bay', 'Schedule'];
    if ($status === Slate_Ops_Statuses::SCHEDULED) return [$note !== '' ? $note : 'Scheduled work is ready to start.', 'Scheduled', 'Review schedule'];
    return [$note !== '' ? $note : 'On track.', 'On track', 'Review job'];
  }

  private static function last_note(array $row): string {
    foreach (['queue_note', 'block_note', 'hold_note', 'schedule_notes', 'notes'] as $key) {
      $value = trim((string) ($row[$key] ?? ''));
      if ($value !== '') return $value;
    }
    $updated = trim((string) ($row['updated_at'] ?? ''));
    if ($updated !== '') return 'Last updated ' . self::duration_since($updated) . ' ago';
    return 'No note recorded.';
  }

  private static function job_from_row(array $row): array {
    $category = self::blocked_category($row);
    $blocked_reason = self::blocked_reason($row, $category);
    [$issue, $issue_short, $action] = self::issue_and_action($row, $category);
    $assigned_name = trim((string) ($row['assigned_name'] ?? ''));
    $status = (string) ($row['status'] ?? '');
    $status_slug = self::status_slug($status, $row);
    $due_delta = self::due_delta($row);
    $priority = (int) ($row['priority'] ?? 3);

    return [
      'id' => self::job_id_label($row),
      'so' => self::job_secondary_label($row),
      'customer' => trim((string) ($row['customer_name'] ?? '')) ?: 'Unknown customer',
      'dealer' => trim((string) ($row['dealer_name'] ?? '')) ?: '—',
      'tech' => $assigned_name !== '' ? ['name' => $assigned_name, 'initials' => self::initials($assigned_name)] : null,
      'status' => $status_slug,
      'status_label' => $status_slug === 'rework' ? 'Rework' : Slate_Ops_Statuses::label($status),
      'step' => self::step_label($row),
      'due' => self::due_label($row),
      'due_delta' => $due_delta,
      'issue' => $issue,
      'issue_short' => $issue_short,
      'blocked_category' => $category,
      'blocked_reason' => $blocked_reason,
      'time_blocked' => $category ? self::duration_since((string) (($row['status_updated_at'] ?? '') ?: ($row['updated_at'] ?? ''))) : null,
      'last_note' => self::last_note($row),
      'action' => $action,
      'parts' => self::parts_label((string) ($row['parts_status'] ?? '')),
      'parts_risk' => self::parts_risk((string) ($row['parts_status'] ?? '')),
      'qc' => self::qc_state($row),
      'bay' => trim((string) ($row['work_center'] ?? '')) !== '' ? self::humanize((string) $row['work_center']) : '—',
      'work_center' => trim((string) ($row['work_center'] ?? '')) !== '' ? self::humanize((string) $row['work_center']) : '—',
      'priority' => $category || $due_delta <= 0 || $priority <= 1 ? 'high' : ($priority <= 2 || $due_delta <= 3 ? 'med' : 'low'),
      'notes' => [
        ['who' => 'Slate Ops', 'role' => 'System', 'when' => self::duration_since((string) (($row['updated_at'] ?? '') ?: ($row['created_at'] ?? ''))) . ' ago', 'text' => self::last_note($row)],
      ],
      '_raw' => [
        'job_id' => (int) ($row['job_id'] ?? 0),
        'status' => $status,
        'assigned_user_id' => (int) ($row['assigned_user_id'] ?? 0),
        'scheduled_start' => (string) ($row['scheduled_start'] ?? ''),
        'parts_status' => (string) ($row['parts_status'] ?? ''),
      ],
    ];
  }

  private static function jobs(): array {
    return array_map([__CLASS__, 'job_from_row'], self::live_rows());
  }

  private static function tech_status_for_jobs(array $jobs): string {
    if (empty($jobs)) return 'No Active Job';
    foreach ($jobs as $job) {
      if (!empty($job['blocked_category'])) return 'Paused / Blocked';
    }
    foreach ($jobs as $job) {
      if ($job['status'] === 'qc') return 'In QC';
    }
    foreach ($jobs as $job) {
      if ($job['status'] === 'ready') return 'Waiting';
    }
    return 'Working';
  }

  private static function techs(array $jobs): array {
    $by_user = [];
    foreach ($jobs as $job) {
      $uid = (int) ($job['_raw']['assigned_user_id'] ?? 0);
      if ($uid <= 0 || empty($job['tech']['name'])) continue;
      if (!isset($by_user[$uid])) {
        $by_user[$uid] = [
          'name' => $job['tech']['name'],
          'initials' => $job['tech']['initials'],
          'jobs' => [],
        ];
      }
      $by_user[$uid]['jobs'][] = $job;
    }

    $today_minutes = self::today_minutes_by_user();
    $techs = [];
    foreach ($by_user as $uid => $group) {
      $group_jobs = $group['jobs'];
      usort($group_jobs, function ($a, $b) {
        return (int) $a['due_delta'] <=> (int) $b['due_delta'];
      });
      $current = $group_jobs[0] ?? null;
      $next = $group_jobs[1]['id'] ?? '—';
      $blocker = null;
      foreach ($group_jobs as $job) {
        if (!empty($job['blocked_category'])) {
          $blocker = $job['blocked_category'];
          break;
        }
      }

      $techs[] = [
        'initials' => $group['initials'],
        'name' => $group['name'],
        'role' => 'Tech',
        'current' => $current ? ['id' => $current['id'], 'label' => $current['step']] : null,
        'on_job_time' => $current && !empty($current['blocked_category']) ? (string) $current['time_blocked'] : '—',
        'next' => $next,
        'status' => self::tech_status_for_jobs($group_jobs),
        'needs_help' => (bool) $blocker,
        'blocker' => $blocker,
        'hours' => self::minutes_label((int) ($today_minutes[(int) $uid] ?? 0)),
      ];
    }

    return array_values($techs);
  }

  private static function attention(array $jobs, array $blocked, array $qc_jobs): array {
    $unassigned = self::filter_jobs($jobs, function($job) { return empty($job['tech']); });
    $parts_risk = self::filter_jobs($jobs, function($job) { return $job['parts_risk'] !== 'low'; });
    $due_soon = self::filter_jobs($jobs, function($job) { return (int) $job['due_delta'] <= 1; });

    usort($blocked, function ($a, $b) {
      return (int) $a['due_delta'] <=> (int) $b['due_delta'];
    });
    usort($due_soon, function ($a, $b) {
      return (int) $a['due_delta'] <=> (int) $b['due_delta'];
    });

    $items = [];
    $oldest = $blocked[0] ?? null;
    $items[] = [
      'label' => 'Oldest blocked job',
      'value' => $oldest['id'] ?? 'Clear',
      'detail' => $oldest ? (($oldest['blocked_category'] ?: 'Blocker') . ' open ' . ($oldest['time_blocked'] ?: '—')) : 'No active blockers',
    ];

    $help = null;
    foreach ($blocked as $job) {
      if (!empty($job['tech']['name'])) {
        $help = $job;
        break;
      }
    }
    $items[] = [
      'label' => 'Tech needing help',
      'value' => $help['tech']['name'] ?? 'Clear',
      'detail' => $help ? $help['issue_short'] : 'No tech blockers',
    ];

    $soon = $due_soon[0] ?? null;
    $items[] = [
      'label' => 'Job due soon',
      'value' => $soon['id'] ?? 'Clear',
      'detail' => $soon ? (($soon['due'] ?: 'Due date') . ' - ' . $soon['issue_short']) : 'No near-term due risk',
    ];

    $qc = $qc_jobs[0] ?? null;
    $items[] = [
      'label' => 'QC item waiting',
      'value' => $qc['id'] ?? 'Clear',
      'detail' => $qc ? $qc['qc'] : 'No QC waiting',
    ];

    $parts = $parts_risk[0] ?? null;
    $items[] = [
      'label' => 'Parts risk item',
      'value' => $parts['id'] ?? 'Clear',
      'detail' => $parts ? $parts['parts'] : 'No parts risk',
    ];

    if ($unassigned && count($items) < 5) {
      $items[] = ['label' => 'Assignment needed', 'value' => $unassigned[0]['id'], 'detail' => 'Unassigned shop work'];
    }

    return array_slice($items, 0, 5);
  }

  private static function filter_jobs($jobs, $callback) {
    return array_values(array_filter($jobs, $callback));
  }

  public static function get_payload() {
    $jobs = self::jobs();
    $techs = self::techs($jobs);
    $blocked = self::filter_jobs($jobs, function($job) { return self::is_blocked($job); });
    $qc_jobs = self::filter_jobs($jobs, function($job) { return in_array($job['status'], ['qc', 'rework'], true); });
    $rework = self::filter_jobs($jobs, function($job) { return $job['status'] === 'rework'; });
    $failed_qc = self::filter_jobs($jobs, function($job) { return $job['qc'] === 'Failed QC'; });
    $unassigned = self::filter_jobs($jobs, function($job) { return empty($job['tech']); });
    $at_risk = self::filter_jobs($jobs, function($job) {
      return (int) $job['due_delta'] <= 1 || $job['parts_risk'] === 'high' || !empty($job['blocked_category']);
    });
    $ready = self::filter_jobs($jobs, function($job) { return $job['status'] === 'ready'; });
    $today = self::filter_jobs($jobs, function($job) {
      $scheduled = (string) ($job['_raw']['scheduled_start'] ?? '');
      return $scheduled !== '' && substr($scheduled, 0, 10) === current_time('Y-m-d');
    });
    $partial_schedulable = self::filter_jobs($jobs, function($job) {
      return $job['parts_risk'] === 'med' && !in_array($job['status'], ['blocked', 'hold'], true);
    });
    $admin_hold = self::filter_jobs($jobs, function($job) {
      return $job['status'] === 'hold' || $job['issue_short'] === 'Assign tech' || stripos((string) $job['blocked_category'], 'Admin') !== false;
    });

    $payload = [
      'source' => 'live',
      'generated_at' => current_time('mysql'),
      'kpis' => [
        ['key' => 'active', 'label' => 'Active Jobs', 'value' => count($jobs), 'sub' => 'Jobs currently open', 'tone' => ''],
        ['key' => 'progress', 'label' => 'In Progress', 'value' => self::count_where($jobs, function($job) { return $job['status'] === 'in-progress'; }), 'sub' => 'Currently being worked', 'tone' => 'sage'],
        ['key' => 'blocked', 'label' => 'Blocked Jobs', 'value' => count($blocked), 'sub' => 'Needs action today', 'tone' => 'blocked'],
        ['key' => 'parts', 'label' => 'Waiting on Parts', 'value' => self::count_where($jobs, function($job) { return $job['parts_risk'] === 'high'; }), 'sub' => 'Parts blocking progress', 'tone' => 'arches'],
        ['key' => 'qc', 'label' => 'Pending QC', 'value' => self::count_where($jobs, function($job) { return $job['status'] === 'qc'; }), 'sub' => 'Needs supervisor sign-off', 'tone' => 'sage'],
        ['key' => 'rework', 'label' => 'Rework Required', 'value' => count($rework), 'sub' => 'Returned from QC', 'tone' => 'arches'],
        ['key' => 'due', 'label' => 'Jobs Due This Week', 'value' => self::count_where($jobs, function($job) { return (int) $job['due_delta'] >= 0 && (int) $job['due_delta'] <= 7; }), 'sub' => 'Inside the 7-day window', 'tone' => ''],
        ['key' => 'risk', 'label' => 'Schedule Risk', 'value' => count($at_risk), 'sub' => 'Behind plan or at risk', 'tone' => 'blocked'],
      ],
      'jobs' => $jobs,
      'techs' => $techs,
      'blocked_categories' => self::get_blocked_categories(),
      'schedule' => [
        'cards' => [
          ['label' => 'Scheduled Today', 'value' => count($today), 'sub' => 'Jobs with shop time today'],
          ['label' => 'Ready to Schedule', 'value' => count($ready), 'sub' => 'Cleared for bay planning'],
          ['label' => 'At Risk', 'value' => count($at_risk), 'sub' => 'Due soon, past due, blocked, or parts risk'],
          ['label' => 'Unassigned', 'value' => count($unassigned), 'sub' => 'Needs tech ownership'],
        ],
        'today' => $today,
        'at_risk' => $at_risk,
        'ready_to_schedule' => $ready,
        'unassigned' => $unassigned,
        'future' => [
          ['label' => 'Planned Capacity', 'value' => '— hrs / wk', 'sub' => 'Tech hours available next 7 days'],
          ['label' => 'Buffer Usage', 'value' => '— %', 'sub' => 'Slack consumed vs. planned'],
          ['label' => 'Overload Warning', 'value' => '—', 'sub' => 'Flags when scheduled work exceeds capacity'],
          ['label' => 'Bay Load', 'value' => 'Planned', 'sub' => 'Bay-level utilization preview'],
          ['label' => 'Work Center Load', 'value' => 'Planned', 'sub' => 'Electrical, interior, cabinetry, QC'],
        ],
      ],
      'qc' => [
        'pending' => self::filter_jobs($jobs, function($job) { return $job['status'] === 'qc'; }),
        'failed' => $failed_qc,
        'rework' => $rework,
        'signoff' => self::filter_jobs($jobs, function($job) { return $job['status'] === 'complete'; }),
      ],
      'ready_queue' => [
        'assign' => self::filter_jobs($ready, function($job) { return empty($job['tech']); }),
        'schedule' => self::filter_jobs($ready, function($job) { return !empty($job['tech']); }),
        'partial_schedulable' => $partial_schedulable,
        'admin_hold' => $admin_hold,
      ],
      'attention' => self::attention($jobs, $blocked, $qc_jobs),
      'tabs' => self::get_tabs(),
    ];

    return apply_filters('slate_ops_supervisor_refresh', $payload);
  }
}
