<?php
if (!defined('ABSPATH')) exit;

/**
 * Phase 0 scheduler REST endpoints.
 *
 * All routes live under slate-ops/v1/scheduler/ to avoid collisions with
 * the existing slate-ops/v1/jobs routes (which target wp_slate_ops_jobs).
 *
 * The WP Admin JS calls these endpoints.  Phase 1 can rename/extend them
 * without touching the existing ops workflow.
 */
class Slate_Ops_Scheduler_REST {

  public static function register_routes() {
    // ── Jobs ───────────────────────────────────────────────────────────
    register_rest_route('slate-ops/v1', '/scheduler/jobs', [
      [
        'methods'             => 'GET',
        'permission_callback' => [__CLASS__, 'perm_cs_or_admin'],
        'callback'            => [__CLASS__, 'get_jobs'],
      ],
      [
        'methods'             => 'POST',
        'permission_callback' => [__CLASS__, 'perm_cs_or_admin'],
        'callback'            => [__CLASS__, 'create_job'],
      ],
    ]);

    register_rest_route('slate-ops/v1', '/scheduler/jobs/(?P<job_uid>[0-9a-f\-]{36})', [
      [
        'methods'             => 'PUT',
        'permission_callback' => [__CLASS__, 'perm_cs_or_admin'],
        'callback'            => [__CLASS__, 'update_job'],
      ],
      [
        'methods'             => 'DELETE',
        'permission_callback' => [__CLASS__, 'perm_cs_or_admin'],
        'callback'            => [__CLASS__, 'delete_job'],
      ],
    ]);

    register_rest_route('slate-ops/v1', '/scheduler/jobs/(?P<job_uid>[0-9a-f\-]{36})/assign-week', [
      'methods'             => 'POST',
      'permission_callback' => [__CLASS__, 'perm_cs_or_admin'],
      'callback'            => [__CLASS__, 'assign_week'],
    ]);

    register_rest_route('slate-ops/v1', '/scheduler/jobs/(?P<job_uid>[0-9a-f\-]{36})/unassign-week', [
      'methods'             => 'POST',
      'permission_callback' => [__CLASS__, 'perm_cs_or_admin'],
      'callback'            => [__CLASS__, 'unassign_week'],
    ]);

    // ── Weeks ──────────────────────────────────────────────────────────
    register_rest_route('slate-ops/v1', '/scheduler/weeks', [
      'methods'             => 'GET',
      'permission_callback' => [__CLASS__, 'perm_cs_or_admin'],
      'callback'            => [__CLASS__, 'get_weeks'],
    ]);

    register_rest_route('slate-ops/v1', '/scheduler/weeks/regenerate', [
      'methods'             => 'POST',
      'permission_callback' => [__CLASS__, 'perm_admin'],
      'callback'            => [__CLASS__, 'regenerate_weeks'],
    ]);

    // ── Settings ───────────────────────────────────────────────────────
    register_rest_route('slate-ops/v1', '/scheduler/settings', [
      [
        'methods'             => 'GET',
        'permission_callback' => [__CLASS__, 'perm_cs_or_admin'],
        'callback'            => [__CLASS__, 'get_settings'],
      ],
      [
        'methods'             => 'POST',
        'permission_callback' => [__CLASS__, 'perm_admin'],
        'callback'            => [__CLASS__, 'update_settings'],
      ],
    ]);
  }

  // ── Permission callbacks ─────────────────────────────────────────────

  public static function perm_cs_or_admin() {
    return current_user_can('slate_ops_customer_service')
        || current_user_can('slate_ops_admin')
        || current_user_can('manage_options');
  }

  public static function perm_admin() {
    return current_user_can('slate_ops_admin')
        || current_user_can('manage_options');
  }

  // ── Jobs ─────────────────────────────────────────────────────────────

  public static function get_jobs(WP_REST_Request $req) {
    global $wpdb;
    $table = $wpdb->prefix . 'slate_jobs';

    $where  = ['1=1'];
    $params = [];

    $status = sanitize_key($req->get_param('status'));
    if ($status) {
      $where[]  = 'scheduling_status = %s';
      $params[] = strtoupper($status);
    }

    $search = sanitize_text_field($req->get_param('search'));
    if ($search) {
      $like     = '%' . $wpdb->esc_like($search) . '%';
      $where[]  = '(customer_name LIKE %s OR so_number LIKE %s)';
      $params[] = $like;
      $params[] = $like;
    }

    if ($req->get_param('missing_so')) {
      $where[] = "(so_number IS NULL OR so_number = '')";
    }

    $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where) . ' ORDER BY updated_at DESC';

    if ($params) {
      $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params));
    } else {
      $rows = $wpdb->get_results($sql);
    }

    return rest_ensure_response(array_map([__CLASS__, 'format_job'], $rows ?: []));
  }

  public static function create_job(WP_REST_Request $req) {
    global $wpdb;
    $table = $wpdb->prefix . 'slate_jobs';

    $customer_name = sanitize_text_field($req->get_param('customer_name'));
    $job_type      = sanitize_key($req->get_param('job_type'));
    $planned_hours = (float) $req->get_param('planned_hours');
    $so_number     = sanitize_text_field($req->get_param('so_number'));
    $notes         = sanitize_textarea_field($req->get_param('notes'));

    if (!$customer_name) {
      return new WP_Error('missing_customer', 'customer_name is required', ['status' => 422]);
    }
    $allowed_types = ['RV', 'UPFIT', 'COMMERCIAL'];
    if (!in_array(strtoupper($job_type), $allowed_types, true)) {
      return new WP_Error('invalid_type', 'job_type must be RV, UPFIT, or COMMERCIAL', ['status' => 422]);
    }
    if ($planned_hours <= 0) {
      return new WP_Error('invalid_hours', 'planned_hours must be greater than 0', ['status' => 422]);
    }

    $job_uid = self::generate_uuid();
    $now     = gmdate('Y-m-d H:i:s');

    $wpdb->insert($table, [
      'job_uid'           => $job_uid,
      'customer_name'     => $customer_name,
      'job_type'          => strtoupper($job_type),
      'planned_minutes'   => (int) round($planned_hours * 60),
      'so_number'         => $so_number ?: null,
      'scheduling_status' => 'INTAKE',
      'target_week_id'    => null,
      'notes'             => $notes ?: null,
      'created_at'        => $now,
      'updated_at'        => $now,
    ]);

    if ($wpdb->last_error) {
      return new WP_Error('db_error', $wpdb->last_error, ['status' => 500]);
    }

    $row = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM $table WHERE job_uid = %s",
      $job_uid
    ));

    return new WP_REST_Response(self::format_job($row), 201);
  }

  public static function update_job(WP_REST_Request $req) {
    global $wpdb;
    $table   = $wpdb->prefix . 'slate_jobs';
    $job_uid = sanitize_text_field($req->get_param('job_uid'));

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE job_uid = %s", $job_uid));
    if (!$row) {
      return new WP_Error('not_found', 'Job not found', ['status' => 404]);
    }

    $updates = [];

    if (null !== $req->get_param('customer_name')) {
      $val = sanitize_text_field($req->get_param('customer_name'));
      if (!$val) return new WP_Error('missing_customer', 'customer_name cannot be empty', ['status' => 422]);
      $updates['customer_name'] = $val;
    }
    if (null !== $req->get_param('job_type')) {
      $val = strtoupper(sanitize_key($req->get_param('job_type')));
      if (!in_array($val, ['RV', 'UPFIT', 'COMMERCIAL'], true)) {
        return new WP_Error('invalid_type', 'job_type must be RV, UPFIT, or COMMERCIAL', ['status' => 422]);
      }
      $updates['job_type'] = $val;
    }
    if (null !== $req->get_param('planned_hours')) {
      $hours = (float) $req->get_param('planned_hours');
      if ($hours <= 0) return new WP_Error('invalid_hours', 'planned_hours must be > 0', ['status' => 422]);
      $updates['planned_minutes'] = (int) round($hours * 60);

      // If hours changed and job is scheduled, recalc week allocation.
      if ($row->target_week_id && $row->scheduling_status === 'SCHEDULED') {
        $updates['updated_at'] = gmdate('Y-m-d H:i:s');
        $wpdb->update($table, $updates, ['job_uid' => $job_uid]);
        Slate_Ops_Scheduler_DB::recalc_week_allocation($row->target_week_id);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE job_uid = %s", $job_uid));
        return rest_ensure_response(self::format_job($row));
      }
    }
    if (array_key_exists('so_number', $req->get_json_params() ?? [])) {
      $updates['so_number'] = sanitize_text_field($req->get_param('so_number')) ?: null;
    }
    if (array_key_exists('notes', $req->get_json_params() ?? [])) {
      $updates['notes'] = sanitize_textarea_field($req->get_param('notes')) ?: null;
    }

    if (empty($updates)) {
      return rest_ensure_response(self::format_job($row));
    }

    $updates['updated_at'] = gmdate('Y-m-d H:i:s');
    $wpdb->update($table, $updates, ['job_uid' => $job_uid]);

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE job_uid = %s", $job_uid));
    return rest_ensure_response(self::format_job($row));
  }

  public static function delete_job(WP_REST_Request $req) {
    global $wpdb;
    $table   = $wpdb->prefix . 'slate_jobs';
    $job_uid = sanitize_text_field($req->get_param('job_uid'));

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE job_uid = %s", $job_uid));
    if (!$row) {
      return new WP_Error('not_found', 'Job not found', ['status' => 404]);
    }

    // Decrement week allocation before deleting.
    if ($row->target_week_id && $row->scheduling_status === 'SCHEDULED') {
      $wpdb->delete($table, ['job_uid' => $job_uid]);
      Slate_Ops_Scheduler_DB::recalc_week_allocation($row->target_week_id);
    } else {
      $wpdb->delete($table, ['job_uid' => $job_uid]);
    }

    return rest_ensure_response(['deleted' => true, 'job_uid' => $job_uid]);
  }

  // ── Week assignment ───────────────────────────────────────────────────

  public static function assign_week(WP_REST_Request $req) {
    global $wpdb;
    $jobs_tbl  = $wpdb->prefix . 'slate_jobs';
    $weeks_tbl = $wpdb->prefix . 'slate_weeks';
    $job_uid   = sanitize_text_field($req->get_param('job_uid'));

    $job = $wpdb->get_row($wpdb->prepare("SELECT * FROM $jobs_tbl WHERE job_uid = %s", $job_uid));
    if (!$job) {
      return new WP_Error('not_found', 'Job not found', ['status' => 404]);
    }

    $target_week_id = sanitize_text_field($req->get_param('target_week_id'));
    if (!$target_week_id) {
      return new WP_Error('missing_week', 'target_week_id is required', ['status' => 422]);
    }

    $week = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM $weeks_tbl WHERE week_id = %s",
      $target_week_id
    ));
    if (!$week) {
      return new WP_Error('week_not_found', 'Week not found', ['status' => 404]);
    }

    $old_week_id = $job->target_week_id;

    $wpdb->update($jobs_tbl, [
      'target_week_id'    => $target_week_id,
      'scheduling_status' => 'SCHEDULED',
      'updated_at'        => gmdate('Y-m-d H:i:s'),
    ], ['job_uid' => $job_uid]);

    // Recalc allocation for old week (if changing weeks) and new week.
    if ($old_week_id && $old_week_id !== $target_week_id) {
      Slate_Ops_Scheduler_DB::recalc_week_allocation($old_week_id);
    }
    Slate_Ops_Scheduler_DB::recalc_week_allocation($target_week_id);

    $job  = $wpdb->get_row($wpdb->prepare("SELECT * FROM $jobs_tbl WHERE job_uid = %s", $job_uid));
    $week = $wpdb->get_row($wpdb->prepare("SELECT * FROM $weeks_tbl WHERE week_id = %s", $target_week_id));

    return rest_ensure_response([
      'job'  => self::format_job($job),
      'week' => self::format_week($week),
    ]);
  }

  public static function unassign_week(WP_REST_Request $req) {
    global $wpdb;
    $jobs_tbl = $wpdb->prefix . 'slate_jobs';
    $job_uid  = sanitize_text_field($req->get_param('job_uid'));

    $job = $wpdb->get_row($wpdb->prepare("SELECT * FROM $jobs_tbl WHERE job_uid = %s", $job_uid));
    if (!$job) {
      return new WP_Error('not_found', 'Job not found', ['status' => 404]);
    }

    $old_week_id = $job->target_week_id;

    $wpdb->update($jobs_tbl, [
      'target_week_id'    => null,
      'scheduling_status' => 'INTAKE',
      'updated_at'        => gmdate('Y-m-d H:i:s'),
    ], ['job_uid' => $job_uid]);

    if ($old_week_id) {
      Slate_Ops_Scheduler_DB::recalc_week_allocation($old_week_id);
    }

    $job = $wpdb->get_row($wpdb->prepare("SELECT * FROM $jobs_tbl WHERE job_uid = %s", $job_uid));
    return rest_ensure_response(['job' => self::format_job($job)]);
  }

  // ── Weeks ────────────────────────────────────────────────────────────

  public static function get_weeks(WP_REST_Request $req) {
    global $wpdb;
    $weeks_tbl = $wpdb->prefix . 'slate_weeks';
    $jobs_tbl  = $wpdb->prefix . 'slate_jobs';

    $weeks = $wpdb->get_results(
      "SELECT * FROM $weeks_tbl ORDER BY start_date ASC"
    );

    $result = [];
    foreach ($weeks as $week) {
      $jobs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $jobs_tbl WHERE target_week_id = %s AND scheduling_status = 'SCHEDULED' ORDER BY updated_at DESC",
        $week->week_id
      ));
      $formatted       = self::format_week($week);
      $formatted['jobs'] = array_map([__CLASS__, 'format_job'], $jobs ?: []);
      $result[]        = $formatted;
    }

    return rest_ensure_response($result);
  }

  public static function regenerate_weeks(WP_REST_Request $req) {
    $weeks_ahead = (int) get_option('slate_scheduler_weeks_ahead', 12);
    Slate_Ops_Scheduler_DB::seed_weeks($weeks_ahead);
    return rest_ensure_response(['regenerated' => true]);
  }

  // ── Settings ─────────────────────────────────────────────────────────

  public static function get_settings() {
    return rest_ensure_response([
      'enabled'           => (bool) get_option('slate_scheduler_enabled', true),
      'weeks_ahead'       => (int)  get_option('slate_scheduler_weeks_ahead', 12),
      'capacity_minutes'  => (int)  get_option('slate_scheduler_capacity_minutes', 0),
      'version'           => SLATE_OPS_VERSION,
      'tables'            => Slate_Ops_Scheduler_DB::tables_exist(),
    ]);
  }

  public static function update_settings(WP_REST_Request $req) {
    $body = $req->get_json_params();

    if (isset($body['enabled'])) {
      update_option('slate_scheduler_enabled', (bool) $body['enabled']);
    }
    if (isset($body['weeks_ahead'])) {
      $val = max(1, min(52, (int) $body['weeks_ahead']));
      update_option('slate_scheduler_weeks_ahead', $val);
    }
    if (isset($body['capacity_minutes'])) {
      update_option('slate_scheduler_capacity_minutes', max(0, (int) $body['capacity_minutes']));
    }

    return self::get_settings();
  }

  // ── Formatters ───────────────────────────────────────────────────────

  private static function format_job($row) {
    if (!$row) return null;
    return [
      'id'                 => (int) $row->id,
      'job_uid'            => $row->job_uid,
      'customer_name'      => $row->customer_name,
      'job_type'           => $row->job_type,
      'planned_minutes'    => (int) $row->planned_minutes,
      'planned_hours'      => round($row->planned_minutes / 60, 1),
      'so_number'          => $row->so_number,
      'scheduling_status'  => $row->scheduling_status,
      'target_week_id'     => $row->target_week_id,
      'notes'              => $row->notes,
      'created_at'         => $row->created_at,
      'updated_at'         => $row->updated_at,
    ];
  }

  private static function format_week($row) {
    if (!$row) return null;
    return [
      'week_id'                   => $row->week_id,
      'label'                     => $row->label,
      'start_date'                => $row->start_date,
      'end_date'                  => $row->end_date,
      'total_capacity_minutes'    => (int) $row->total_capacity_minutes,
      'planned_limit_minutes'     => (int) $row->planned_limit_minutes,
      'current_allocated_minutes' => (int) $row->current_allocated_minutes,
    ];
  }

  // ── Helpers ──────────────────────────────────────────────────────────

  private static function generate_uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
      mt_rand(0, 0xffff), mt_rand(0, 0xffff),
      mt_rand(0, 0xffff),
      mt_rand(0, 0x0fff) | 0x4000,
      mt_rand(0, 0x3fff) | 0x8000,
      mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
  }
}
