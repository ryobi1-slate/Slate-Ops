<?php
if (!defined('ABSPATH')) exit;

class Slate_Ops_REST {

  public static function register_routes() {
    register_rest_route('slate-ops/v1', '/me', [
      'methods' => 'GET',
      'permission_callback' => [__CLASS__, 'perm_ops'],
      'callback' => [__CLASS__, 'me'],
    ]);

    register_rest_route('slate-ops/v1', '/settings', [
      [
        'methods' => 'GET',
        'permission_callback' => [__CLASS__, 'perm_ops'],
        'callback' => [__CLASS__, 'get_settings'],
      ],
      [
        'methods' => 'POST',
        'permission_callback' => [__CLASS__, 'perm_admin_or_supervisor'],
        'callback' => [__CLASS__, 'update_settings'],
      ],
    ]);

    register_rest_route('slate-ops/v1', '/users', [
      'methods' => 'GET',
      'permission_callback' => [__CLASS__, 'perm_admin_or_supervisor'],
      'callback' => [__CLASS__, 'users'],
    ]);

    register_rest_route('slate-ops/v1', '/jobs', [
      [
        'methods' => 'GET',
        'permission_callback' => [__CLASS__, 'perm_ops'],
        'callback' => [__CLASS__, 'list_jobs'],
      ],
      [
        'methods' => 'POST',
        'permission_callback' => [__CLASS__, 'perm_cs_or_admin'],
        'callback' => [__CLASS__, 'create_job_manual'],
      ],
    ]);

    register_rest_route('slate-ops/v1', '/jobs/(?P<id>\d+)', [
      'methods' => 'GET',
      'permission_callback' => [__CLASS__, 'perm_ops'],
      'callback' => [__CLASS__, 'get_job'],
    ]);

    register_rest_route('slate-ops/v1', '/jobs/(?P<id>\d+)/so', [
      'methods' => 'POST',
      'permission_callback' => [__CLASS__, 'perm_cs_or_admin'],
      'callback' => [__CLASS__, 'set_so'],
    ]);

    register_rest_route('slate-ops/v1', '/jobs/(?P<id>\d+)/assign', [
      'methods' => 'POST',
      'permission_callback' => [__CLASS__, 'perm_supervisor_or_admin'],
      'callback' => [__CLASS__, 'assign_job'],
    ]);

    register_rest_route('slate-ops/v1', '/jobs/(?P<id>\d+)/schedule', [
      'methods' => 'POST',
      'permission_callback' => [__CLASS__, 'perm_supervisor_or_admin'],
      'callback' => [__CLASS__, 'schedule_job'],
    ]);

    register_rest_route('slate-ops/v1', '/jobs/(?P<id>\d+)/status', [
      'methods' => 'POST',
      'permission_callback' => [__CLASS__, 'perm_ops'],
      'callback' => [__CLASS__, 'set_status'],
    ]);

    register_rest_route('slate-ops/v1', '/time/active', [
      'methods' => 'GET',
      'permission_callback' => [__CLASS__, 'perm_ops'],
      'callback' => [__CLASS__, 'time_active'],
    ]);

    register_rest_route('slate-ops/v1', '/time/start', [
      'methods' => 'POST',
      'permission_callback' => [__CLASS__, 'perm_tech_or_supervisor_or_admin'],
      'callback' => [__CLASS__, 'time_start'],
    ]);

    register_rest_route('slate-ops/v1', '/time/stop', [
      'methods' => 'POST',
      'permission_callback' => [__CLASS__, 'perm_tech_or_supervisor_or_admin'],
      'callback' => [__CLASS__, 'time_stop'],
    ]);

    register_rest_route('slate-ops/v1', '/time/correction', [
      'methods' => 'POST',
      'permission_callback' => [__CLASS__, 'perm_tech_or_supervisor_or_admin'],
      'callback' => [__CLASS__, 'time_correction_request'],
    ]);

    register_rest_route('slate-ops/v1', '/supervisor/queues', [
      'methods' => 'GET',
      'permission_callback' => [__CLASS__, 'perm_supervisor_or_admin'],
      'callback' => [__CLASS__, 'supervisor_queues'],
    ]);
  }

  // Permissions
  public static function perm_ops() {
    return Slate_Ops_Utils::require_ops_access();
  }
  public static function perm_tech_or_supervisor_or_admin() {
    return is_user_logged_in() && (current_user_can(Slate_Ops_Utils::CAP_TECH) || current_user_can(Slate_Ops_Utils::CAP_SUPERVISOR) || current_user_can(Slate_Ops_Utils::CAP_ADMIN));
  }
  public static function perm_supervisor_or_admin() {
    return is_user_logged_in() && (current_user_can(Slate_Ops_Utils::CAP_SUPERVISOR) || current_user_can(Slate_Ops_Utils::CAP_ADMIN));
  }
  public static function perm_admin_or_supervisor() {
    return self::perm_supervisor_or_admin();
  }
  public static function perm_cs_or_admin() {
    return is_user_logged_in() && (current_user_can(Slate_Ops_Utils::CAP_CS) || current_user_can(Slate_Ops_Utils::CAP_ADMIN));
  }

  // Handlers
  public static function me($req) {
    return [
      'user' => [
        'id' => get_current_user_id(),
        'name' => wp_get_current_user()->display_name,
        'caps' => Slate_Ops_Utils::current_user_caps_summary(),
      ],
    ];
  }

  public static function get_settings($req) {
    global $wpdb;
    $t = $wpdb->prefix . 'slate_ops_settings';
    $row = $wpdb->get_row("SELECT * FROM $t WHERE id=1", ARRAY_A);
    return $row ?: [];
  }

  public static function update_settings($req) {
    global $wpdb;
    $t = $wpdb->prefix . 'slate_ops_settings';
    $body = $req->get_json_params();

    $shift_start = isset($body['shift_start']) ? sanitize_text_field($body['shift_start']) : '07:00:00';
    $shift_end = isset($body['shift_end']) ? sanitize_text_field($body['shift_end']) : '15:30:00';
    $lunch = isset($body['lunch_minutes']) ? max(0, intval($body['lunch_minutes'])) : 30;
    $breaks = isset($body['break_minutes']) ? max(0, intval($body['break_minutes'])) : 20;

    $wpdb->update($t, [
      'shift_start' => $shift_start,
      'shift_end' => $shift_end,
      'lunch_minutes' => $lunch,
      'break_minutes' => $breaks,
      'updated_by' => get_current_user_id(),
      'updated_at' => Slate_Ops_Utils::now_gmt(),
    ], ['id' => 1]);

    self::audit('settings', 1, 'update', null, null, wp_json_encode(['shift_start'=>$shift_start,'shift_end'=>$shift_end,'lunch_minutes'=>$lunch,'break_minutes'=>$breaks]), 'Settings updated');
    return self::get_settings($req);
  }

  public static function list_jobs($req) {
global $wpdb;
$t = $wpdb->prefix . 'slate_ops_jobs';

$status = $req->get_param('status');
$q = $req->get_param('q');
$so_missing = (int)$req->get_param('so_missing');

$limit = (int)$req->get_param('limit');
if ($limit <= 0) $limit = 100;
if ($limit > 500) $limit = 500;

$where = "archived_at IS NULL";
$params = [];

if ($status) {
  $where .= " AND status = %s";
  $params[] = strtoupper(sanitize_text_field($status));
}

if ($so_missing === 1) {
  $where .= " AND (so_number IS NULL OR so_number = '')";
}

if ($q) {
  $q = sanitize_text_field($q);
  $where .= " AND (so_number LIKE %s OR vin LIKE %s OR customer_name LIKE %s OR dealer_name LIKE %s)";
  $like = '%' . $wpdb->esc_like($q) . '%';
  array_push($params, $like, $like, $like, $like);
}

$sql = "SELECT job_id, source, created_from, portal_quote_id, quote_number, so_number, customer_name, vin, dealer_name,
               job_type, parts_status, status, status_detail, status_updated_at, delay_reason, priority,
               assigned_user_id, work_center, estimated_minutes, scheduled_start, scheduled_finish, requested_date,
               clickup_task_id, clickup_estimate_ms, dealer_status, created_at, updated_at
        FROM $t WHERE $where ORDER BY updated_at DESC LIMIT $limit";

$rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

// Actual minutes (sum of closed segments) in one query
$ids = array_map(function($r){ return (int)$r['job_id']; }, $rows);
$actual_map = [];
if (!empty($ids)) {
  $seg = $wpdb->prefix . 'slate_ops_time_segments';
  $in = implode(',', array_fill(0, count($ids), '%d'));
  $qsql = "SELECT job_id, SUM(TIMESTAMPDIFF(MINUTE, start_ts, end_ts)) AS actual_minutes
           FROM $seg
           WHERE end_ts IS NOT NULL AND job_id IN ($in)
           GROUP BY job_id";
  $prep = $wpdb->prepare($qsql, $ids);
  $totals = $wpdb->get_results($prep, ARRAY_A);
  foreach ($totals as $trow) {
    $actual_map[(int)$trow['job_id']] = (int)($trow['actual_minutes'] ?? 0);
  }
}

foreach ($rows as &$r) {
  $r['assigned_name'] = $r['assigned_user_id'] ? Slate_Ops_Utils::user_display($r['assigned_user_id']) : '';

  // Back-compat fallbacks
  if (empty($r['created_from'])) $r['created_from'] = $r['source'] ?: 'manual';
  if (empty($r['status_updated_at'])) $r['status_updated_at'] = $r['updated_at'] ?: $r['created_at'];

  $r['actual_minutes'] = $actual_map[(int)$r['job_id']] ?? 0;
}

return ['jobs' => $rows];
  }

    if ($q) {
      $q = sanitize_text_field($q);
      $where .= " AND (so_number LIKE %s OR vin LIKE %s OR customer_name LIKE %s OR dealer_name LIKE %s)";
      $like = '%' . $wpdb->esc_like($q) . '%';
      array_push($params, $like, $like, $like, $like);
    }

    $sql = "SELECT job_id, source, portal_quote_id, quote_number, so_number, customer_name, vin, dealer_name, job_type, parts_status, status, assigned_user_id, scheduled_start, scheduled_finish, requested_date, clickup_task_id, clickup_estimate_ms, dealer_status, updated_at
            FROM $t WHERE $where ORDER BY updated_at DESC LIMIT $limit";

    $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

    // Add assigned display
    foreach ($rows as &$r) {
      $r['assigned_name'] = $r['assigned_user_id'] ? Slate_Ops_Utils::user_display($r['assigned_user_id']) : '';
    }

    return ['jobs' => $rows];
  }

  public static function get_job($req) {
    $job_id = intval($req['id']);
    $job = self::job_by_id($job_id);
    if (!$job) return new WP_Error('not_found', 'Job not found', ['status' => 404]);

    $breakdown = self::time_breakdown($job_id);
    $job['time'] = $breakdown;

    return $job;
  }

  public static function create_job_manual($req) {
global $wpdb;
$body = $req->get_json_params();
$t = $wpdb->prefix . 'slate_ops_jobs';

$customer = sanitize_text_field($body['customer_name'] ?? '');
$dealer = sanitize_text_field($body['dealer_name'] ?? 'Internal');
$vin = sanitize_text_field($body['vin'] ?? '');
$job_type = sanitize_key($body['job_type'] ?? 'upfit');
$parts_status = sanitize_key($body['parts_status'] ?? '');
$priority = (int)($body['priority'] ?? 3);
if ($priority < 1 || $priority > 5) $priority = 3;

$now = Slate_Ops_Utils::now_gmt();

$wpdb->insert($t, [
  'source' => 'manual',
  'created_from' => 'manual',
  'customer_name' => $customer,
  'dealer_name' => $dealer,
  'vin' => $vin,
  'job_type' => $job_type ?: 'upfit',
  'parts_status' => $parts_status ?: null,
  'status' => 'UNSCHEDULED',
  'status_updated_at' => $now,
  'delay_reason' => null,
  'priority' => $priority,
  'dealer_status' => 'waiting',
  'created_by' => get_current_user_id(),
  'created_at' => $now,
  'updated_at' => $now,
]);

$job_id = (int)$wpdb->insert_id;
self::audit('job', $job_id, 'create', null, null, wp_json_encode(['created_from'=>'manual']), 'Manual job created');

$job = self::job_by_id($job_id);
self::maybe_create_clickup_task($job);

return self::get_job(['id' => $job_id]);
  }

  public static function set_so($req) {
    global $wpdb;
    $job_id = intval($req['id']);
    $body = $req->get_json_params();
    $so = strtoupper(trim(sanitize_text_field($body['so_number'] ?? '')));

    if (!Slate_Ops_Utils::so_is_valid($so)) {
      return new WP_Error('invalid_so', 'SO# format must be S-ORD######', ['status' => 400]);
    }

    $t = $wpdb->prefix . 'slate_ops_jobs';
    $existing = $wpdb->get_var($wpdb->prepare("SELECT job_id FROM $t WHERE so_number=%s AND job_id<>%d", $so, $job_id));
    if ($existing) {
      return new WP_Error('so_exists', 'SO# already linked to another job', ['status' => 409, 'job_id' => (int)$existing]);
    }

    $job = self::job_by_id($job_id);
    if (!$job) return new WP_Error('not_found', 'Job not found', ['status' => 404]);

    $old = $job['so_number'];

    $wpdb->update($t, [
      'so_number' => $so,
      'status' => 'READY_FOR_SCHEDULING',
      'dealer_status' => 'waiting',
      'updated_at' => Slate_Ops_Utils::now_gmt(),
    ], ['job_id' => $job_id]);

    self::audit('job', $job_id, 'update', 'so_number', $old, $so, 'SO# set');
    self::audit('job', $job_id, 'update', 'status', $job['status'], 'READY_FOR_SCHEDULING', 'Moved to Ready for Scheduling');

    // Update ClickUp name if linked.
    $job2 = self::job_by_id($job_id);
    self::maybe_update_clickup_name($job2);

    // Push dealer portal status back (integration hook)
    self::maybe_push_dealer_portal_status($job2);

    return self::get_job(['id' => $job_id]);
  }

  public static function assign_job($req) {
    global $wpdb;
    $job_id = intval($req['id']);
    $body = $req->get_json_params();
    $user_id = intval($body['assigned_user_id'] ?? 0);

    $job = self::job_by_id($job_id);
    if (!$job) return new WP_Error('not_found', 'Job not found', ['status' => 404]);

    $t = $wpdb->prefix . 'slate_ops_jobs';
    $old = $job['assigned_user_id'];

    $wpdb->update($t, [
      'assigned_user_id' => $user_id ?: null,
      'updated_at' => Slate_Ops_Utils::now_gmt(),
    ], ['job_id' => $job_id]);

    self::audit('job', $job_id, 'update', 'assigned_user_id', (string)$old, (string)$user_id, 'Assignment updated');

    return self::get_job(['id' => $job_id]);
  }

  public static function schedule_job($req) {
global $wpdb;
$body = $req->get_json_params();
$t = $wpdb->prefix . 'slate_ops_jobs';

$job_id = (int)($body['job_id'] ?? 0);
if (!$job_id) return new WP_Error('bad_request', 'Missing job_id', ['status'=>400]);

$work_center = sanitize_text_field($body['work_center'] ?? '');
$est = (int)($body['estimated_minutes'] ?? 0);
if ($est < 0) $est = 0;
$start = sanitize_text_field($body['scheduled_start'] ?? '');
$finish = sanitize_text_field($body['scheduled_finish'] ?? '');
$assigned = (int)($body['assigned_user_id'] ?? 0);

$now = Slate_Ops_Utils::now_gmt();

$update = [
  'work_center' => $work_center ?: null,
  'estimated_minutes' => $est ?: null,
  'scheduled_start' => $start ?: null,
  'scheduled_finish' => $finish ?: null,
  'assigned_user_id' => $assigned ?: null,
  'status' => 'SCHEDULED',
  'status_updated_at' => $now,
  'updated_at' => $now,
];

$wpdb->update($t, $update, ['job_id' => $job_id]);

self::audit('job', $job_id, 'update', 'schedule', null, wp_json_encode($update), 'Schedule updated');

// Keep ClickUp name fresh once SO# exists
$job = self::job_by_id($job_id);
self::maybe_update_clickup_name($job);

// Push simplified dealer status based on shop status
self::maybe_push_dealer_portal_status($job);

return self::get_job(['id' => $job_id]);
  }

    $start = sanitize_text_field($body['scheduled_start'] ?? '');
    $finish = sanitize_text_field($body['scheduled_finish'] ?? '');

    $t = $wpdb->prefix . 'slate_ops_jobs';
    $wpdb->update($t, [
      'scheduled_start' => $start ?: null,
      'scheduled_finish' => $finish ?: null,
      'status' => 'SCHEDULED',
      'dealer_status' => 'waiting',
      'updated_at' => Slate_Ops_Utils::now_gmt(),
    ], ['job_id' => $job_id]);

    self::audit('job', $job_id, 'update', 'scheduled_start', $job['scheduled_start'], $start, 'Scheduled start updated');
    self::audit('job', $job_id, 'update', 'scheduled_finish', $job['scheduled_finish'], $finish, 'Scheduled finish updated');
    self::audit('job', $job_id, 'update', 'status', $job['status'], 'SCHEDULED', 'Job scheduled');

    $job2 = self::job_by_id($job_id);
    self::maybe_push_dealer_portal_status($job2);
    return self::get_job(['id' => $job_id]);
  }

  public static function set_status($req) {
global $wpdb;
$body = $req->get_json_params();
$t = $wpdb->prefix . 'slate_ops_jobs';

$job_id = (int)($body['job_id'] ?? 0);
if (!$job_id) return new WP_Error('bad_request', 'Missing job_id', ['status'=>400]);

$new_status = strtoupper(sanitize_text_field($body['status'] ?? ''));
if (!$new_status) return new WP_Error('bad_request', 'Missing status', ['status'=>400]);

$detail = sanitize_text_field($body['status_detail'] ?? '');
$delay_reason = sanitize_key($body['delay_reason'] ?? '');
$priority = (int)($body['priority'] ?? 0);

$now = Slate_Ops_Utils::now_gmt();

$update = [
  'status' => $new_status,
  'status_detail' => $detail ?: null,
  'status_updated_at' => $now,
  'updated_at' => $now,
];

// Only set delay_reason when provided (and typically for DELAYED)
if (!empty($delay_reason)) {
  $update['delay_reason'] = $delay_reason;
}

// Priority 1-5
if ($priority >= 1 && $priority <= 5) {
  $update['priority'] = $priority;
}

$wpdb->update($t, $update, ['job_id' => $job_id]);

self::audit('job', $job_id, 'update', 'status', null, wp_json_encode($update), 'Status updated');

$job = self::job_by_id($job_id);
self::maybe_push_dealer_portal_status($job);

return self::get_job(['id' => $job_id]);
  }

    $job = self::job_by_id($job_id);
    if (!$job) return new WP_Error('not_found', 'Job not found', ['status' => 404]);

    // QC approve: only supervisor/admin can set COMPLETE from PENDING_QC.
    if ($new === 'COMPLETE' && $job['status'] === 'PENDING_QC' && !self::perm_supervisor_or_admin()) {
      return new WP_Error('forbidden', 'Supervisor required for QC approve', ['status' => 403]);
    }

    $t = $wpdb->prefix . 'slate_ops_jobs';
    $dealer_status = Slate_Ops_Utils::dealer_status_from_internal($new);

    $wpdb->update($t, [
      'status' => $new,
      'dealer_status' => $dealer_status,
      'updated_at' => Slate_Ops_Utils::now_gmt(),
    ], ['job_id' => $job_id]);

    self::audit('job', $job_id, 'update', 'status', $job['status'], $new, $note ?: 'Status updated');

    $job2 = self::job_by_id($job_id);
    self::maybe_push_dealer_portal_status($job2);

    return self::get_job(['id' => $job_id]);
  }

  public static function time_start($req) {
    global $wpdb;
    $body = $req->get_json_params();
    $job_id = intval($body['job_id'] ?? 0);
    $reason = sanitize_key($body['reason'] ?? '');
    $note = sanitize_text_field($body['note'] ?? '');

    if (!$job_id) return new WP_Error('missing_job', 'job_id required', ['status' => 400]);
    $job = self::job_by_id($job_id);
    if (!$job) return new WP_Error('not_found', 'Job not found', ['status' => 404]);

    $user_id = get_current_user_id();

    // Auto-stop any open segment for this tech.
    $segments = $wpdb->prefix . 'slate_ops_time_segments';
    $open = $wpdb->get_row($wpdb->prepare("SELECT * FROM $segments WHERE user_id=%d AND end_ts IS NULL AND state='active' ORDER BY start_ts DESC LIMIT 1", $user_id), ARRAY_A);
    if ($open) {
      $wpdb->update($segments, [
        'end_ts' => Slate_Ops_Utils::now_gmt(),
        'updated_at' => Slate_Ops_Utils::now_gmt(),
      ], ['segment_id' => (int)$open['segment_id']]);

      self::audit('segment', (int)$open['segment_id'], 'update', 'end_ts', null, Slate_Ops_Utils::now_gmt(), 'Auto-stopped due to starting another job');
    }

    // Reason required when not assigned (and assignment exists).
    $assigned = (int)($job['assigned_user_id'] ?? 0);
    if ($assigned && $assigned !== $user_id) {
      $reason = Slate_Ops_Utils::sanitize_reason($reason);
      if ($reason === 'other' && empty($note)) {
        return new WP_Error('reason_required', 'Reason note required for "Other"', ['status' => 400]);
      }
    } else {
      $reason = null;
    }

    $now = Slate_Ops_Utils::now_gmt();
    $wpdb->insert($segments, [
      'job_id' => $job_id,
      'user_id' => $user_id,
      'start_ts' => $now,
      'end_ts' => null,
      'reason' => $reason,
      'note' => $note ?: null,
      'source' => 'timer',
      'state' => 'active',
      'approval_status' => 'approved',
      'created_by' => $user_id,
      'created_at' => $now,
      'updated_at' => $now,
    ]);

    $segment_id = (int)$wpdb->insert_id;
    self::audit('segment', $segment_id, 'create', null, null, wp_json_encode(['job_id'=>$job_id,'user_id'=>$user_id]), 'Timer started');

    // Set job to IN_PROGRESS if needed.
    if (!in_array($job['status'], ['IN_PROGRESS','PENDING_QC','COMPLETE'], true)) {
      self::set_status(['id'=>$job_id, 'status'=>'IN_PROGRESS', 'note'=>'Auto set by timer start']);
    }

    return [
      'segment_id' => $segment_id,
      'job_id' => $job_id,
      'started_at' => $now,
    ];
  }

  public static function time_stop($req) {
    global $wpdb;
    $user_id = get_current_user_id();
    $segments = $wpdb->prefix . 'slate_ops_time_segments';
    $open = $wpdb->get_row($wpdb->prepare("SELECT * FROM $segments WHERE user_id=%d AND end_ts IS NULL AND state='active' ORDER BY start_ts DESC LIMIT 1", $user_id), ARRAY_A);
    if (!$open) {
      return new WP_Error('no_active_timer', 'No active timer', ['status' => 400]);
    }

    $now = Slate_Ops_Utils::now_gmt();
    $wpdb->update($segments, [
      'end_ts' => $now,
      'updated_at' => $now,
    ], ['segment_id' => (int)$open['segment_id']]);

    self::audit('segment', (int)$open['segment_id'], 'update', 'end_ts', null, $now, 'Timer stopped');

    return [
      'segment_id' => (int)$open['segment_id'],
      'stopped_at' => $now,
    ];
  }

  public static function time_correction_request($req) {
    // V1: tech submits a correction as a new pending segment (no auto-void logic yet).
    // Supervisor can later void/replace segments; UI will show pending minutes separately.
    global $wpdb;
    $body = $req->get_json_params();
    $job_id = intval($body['job_id'] ?? 0);
    $start = sanitize_text_field($body['start_ts'] ?? '');
    $end = sanitize_text_field($body['end_ts'] ?? '');
    $note = sanitize_text_field($body['note'] ?? '');

    if (!$job_id || !$start || !$end) return new WP_Error('missing_fields', 'job_id, start_ts, end_ts required', ['status' => 400]);
    if (empty($note)) return new WP_Error('note_required', 'Reason note required', ['status' => 400]);

    $job = self::job_by_id($job_id);
    if (!$job) return new WP_Error('not_found', 'Job not found', ['status' => 404]);

    $segments = $wpdb->prefix . 'slate_ops_time_segments';
    $now = Slate_Ops_Utils::now_gmt();

    $wpdb->insert($segments, [
      'job_id' => $job_id,
      'user_id' => get_current_user_id(),
      'start_ts' => $start,
      'end_ts' => $end,
      'reason' => null,
      'note' => $note,
      'source' => 'manual_fix',
      'state' => 'active',
      'approval_status' => 'pending',
      'created_by' => get_current_user_id(),
      'created_at' => $now,
      'updated_at' => $now,
    ]);

    $segment_id = (int)$wpdb->insert_id;
    self::audit('segment', $segment_id, 'create', null, null, wp_json_encode(['job_id'=>$job_id,'start'=>$start,'end'=>$end]), 'Time correction submitted (pending)');

    return ['segment_id' => $segment_id, 'approval_status' => 'pending'];
  }

  public static function time_active($req) {
    global $wpdb;
    $t = $wpdb->prefix . 'slate_ops_time';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE user_id=%d AND end_at IS NULL ORDER BY start_at DESC LIMIT 1", get_current_user_id()), ARRAY_A);
    return ['active' => $row ?: null];
  }

  public static function users($req) {
    $users = get_users([
      'fields' => ['ID','display_name','user_email'],
      'orderby' => 'display_name',
      'order' => 'ASC',
      'number' => 500,
    ]);
    $out = [];
    foreach ($users as $u){
      $out[] = ['id'=>(int)$u->ID,'name'=>$u->display_name,'email'=>$u->user_email];
    }
    return ['users'=>$out];
  }

  public static function supervisor_queues($req) {
    global $wpdb;
    $segments = $wpdb->prefix . 'slate_ops_time_segments';
    $jobs = $wpdb->prefix . 'slate_ops_jobs';

    // Pending corrections
    $pending = $wpdb->get_results("SELECT s.segment_id, s.job_id, s.user_id, s.start_ts, s.end_ts, s.note, s.source, s.approval_status
      FROM $segments s WHERE s.approval_status='pending' AND s.state='active' ORDER BY s.created_at DESC LIMIT 100", ARRAY_A);

    // Unassigned segments: tech != assigned_user_id and assigned_user_id not null
    $unassigned = $wpdb->get_results("SELECT s.segment_id, s.job_id, s.user_id, s.start_ts, s.end_ts, s.reason, s.note
      FROM $segments s
      JOIN $jobs j ON j.job_id = s.job_id
      WHERE j.assigned_user_id IS NOT NULL AND j.assigned_user_id <> s.user_id
        AND s.state='active' AND s.end_ts IS NOT NULL
      ORDER BY s.end_ts DESC LIMIT 200", ARRAY_A);

    return [
      'pending_corrections' => array_map([__CLASS__, 'decorate_segment'], $pending),
      'unassigned_segments' => array_map([__CLASS__, 'decorate_segment'], $unassigned),
    ];
  }

  // Helpers
  private static function decorate_segment($s) {
    $s['user_name'] = Slate_Ops_Utils::user_display($s['user_id']);
    return $s;
  }

  private static function job_by_id($job_id) {
global $wpdb;
$t = $wpdb->prefix . 'slate_ops_jobs';
$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE job_id=%d", $job_id), ARRAY_A);
if ($row) {
  $row['assigned_name'] = $row['assigned_user_id'] ? Slate_Ops_Utils::user_display($row['assigned_user_id']) : '';

  // Back-compat fallbacks
  if (empty($row['created_from'])) $row['created_from'] = $row['source'] ?: 'manual';
  if (empty($row['status_updated_at'])) $row['status_updated_at'] = $row['updated_at'] ?: $row['created_at'];

  // Computed actual minutes
  $seg = $wpdb->prefix . 'slate_ops_time_segments';
  $mins = $wpdb->get_var($wpdb->prepare("SELECT SUM(TIMESTAMPDIFF(MINUTE, start_ts, end_ts)) FROM $seg WHERE job_id=%d AND end_ts IS NOT NULL", (int)$job_id));
  $row['actual_minutes'] = (int)($mins ?: 0);
}
return $row;
  }
    return $row;
  }

  private static function time_breakdown($job_id) {
    global $wpdb;
    $segments = $wpdb->prefix . 'slate_ops_time_segments';

    $rows = $wpdb->get_results($wpdb->prepare("
      SELECT user_id,
        SUM(CASE WHEN approval_status='approved' AND state='active' AND end_ts IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, start_ts, end_ts) ELSE 0 END) as approved_minutes,
        SUM(CASE WHEN approval_status='pending' AND state='active' AND end_ts IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, start_ts, end_ts) ELSE 0 END) as pending_minutes,
        COUNT(*) as segment_count,
        MAX(COALESCE(end_ts, start_ts)) as last_activity
      FROM $segments
      WHERE job_id=%d AND state='active'
      GROUP BY user_id
      ORDER BY approved_minutes DESC, pending_minutes DESC
    ", $job_id), ARRAY_A);

    $by_tech = [];
    $approved_total = 0;
    $pending_total = 0;

    foreach ($rows as $r) {
      $approved_total += (int)$r['approved_minutes'];
      $pending_total += (int)$r['pending_minutes'];
      $by_tech[] = [
        'user_id' => (int)$r['user_id'],
        'user_name' => Slate_Ops_Utils::user_display($r['user_id']),
        'approved_minutes' => (int)$r['approved_minutes'],
        'pending_minutes' => (int)$r['pending_minutes'],
        'segment_count' => (int)$r['segment_count'],
        'last_activity' => $r['last_activity'],
      ];
    }

    return [
      'approved_minutes_total' => $approved_total,
      'pending_minutes_total' => $pending_total,
      'by_tech' => $by_tech,
    ];
  }

  private static function audit($entity_type, $entity_id, $action, $field, $old, $new, $note = '') {
    global $wpdb;
    $t = $wpdb->prefix . 'slate_ops_audit_log';
    $wpdb->insert($t, [
      'entity_type' => sanitize_key($entity_type),
      'entity_id' => (int)$entity_id,
      'action' => sanitize_key($action),
      'field_name' => $field ? sanitize_key($field) : null,
      'old_value' => $old !== null ? maybe_serialize($old) : null,
      'new_value' => $new !== null ? maybe_serialize($new) : null,
      'note' => $note ? sanitize_text_field($note) : null,
      'user_id' => get_current_user_id(),
      'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : null,
      'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : null,
      'created_at' => Slate_Ops_Utils::now_gmt(),
    ]);
  }

  private static function maybe_create_clickup_task($job) {
    if (!$job || !empty($job['clickup_task_id'])) return;

    $name = 'UNSCHEDULED - ' . ($job['customer_name'] ?: 'Job ' . $job['job_id']);
    $desc = "Slate Ops Job\n\n"
      . "Job ID: {$job['job_id']}\n"
      . ($job['portal_quote_id'] ? "Portal Quote ID: {$job['portal_quote_id']}\n" : "")
      . ($job['quote_number'] ? "Quote #: {$job['quote_number']}\n" : "")
      . ($job['vin'] ? "VIN: {$job['vin']}\n" : "")
      . ($job['dealer_name'] ? "Dealer: {$job['dealer_name']}\n" : "")
      . "Status: UNSCHEDULED\n";

    $resp = Slate_Ops_ClickUp::create_unscheduled_task($name, $desc);
    if (is_wp_error($resp)) return;

    if (!empty($resp['id'])) {
      global $wpdb;
      $t = $wpdb->prefix . 'slate_ops_jobs';
      $wpdb->update($t, [
        'clickup_task_id' => sanitize_text_field($resp['id']),
        'updated_at' => Slate_Ops_Utils::now_gmt(),
      ], ['job_id' => (int)$job['job_id']]);
      self::audit('job', (int)$job['job_id'], 'update', 'clickup_task_id', null, (string)$resp['id'], 'ClickUp task created');
    }
  }

  private static function maybe_update_clickup_name($job) {
    if (!$job || empty($job['clickup_task_id']) || empty($job['so_number'])) return;
    $vin_last6 = $job['vin'] ? substr(preg_replace('/\s+/', '', $job['vin']), -6) : '';
    $name = $job['so_number'] . ' - ' . ($job['customer_name'] ?: 'Customer') . ($vin_last6 ? ' - ' . $vin_last6 : '');
    Slate_Ops_ClickUp::update_task_name($job['clickup_task_id'], $name);
  }

  private static function maybe_push_dealer_portal_status($job) {
    if (!$job) return;
    // Integration point: your Dealer Portal can hook this action and persist dealer status however it wants.
    do_action('slate_ops_dealer_status_changed', (int)$job['job_id'], sanitize_text_field($job['dealer_status']), $job);
  }

  public static function handle_quote_approved($quote_id) {
global $wpdb;
$t = $wpdb->prefix . 'slate_ops_jobs';
$now = Slate_Ops_Utils::now_gmt();

$quote_id = (int)$quote_id;
if (!$quote_id) return;

// Avoid duplicates: one job per portal_quote_id
$existing = $wpdb->get_var($wpdb->prepare("SELECT job_id FROM $t WHERE portal_quote_id=%d AND archived_at IS NULL", $quote_id));
if ($existing) return;

$wpdb->insert($t, [
  'source' => 'portal',
  'created_from' => 'portal',
  'portal_quote_id' => $quote_id,
  'status' => 'UNSCHEDULED',
  'status_updated_at' => $now,
  'delay_reason' => null,
  'priority' => 3,
  'dealer_status' => 'waiting',
  'created_by' => get_current_user_id() ?: null,
  'created_at' => $now,
  'updated_at' => $now,
]);

$job_id = (int)$wpdb->insert_id;
self::audit('job', $job_id, 'create', null, null, wp_json_encode(['created_from'=>'portal','portal_quote_id'=>$quote_id]), 'Job created from portal approval');

$job = self::job_by_id($job_id);
self::maybe_create_clickup_task($job);
self::maybe_push_dealer_portal_status($job);
  }
}
