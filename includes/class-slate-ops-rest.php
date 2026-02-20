<?php
if (!defined('ABSPATH')) exit;

class Slate_Ops_REST {

  public static function register_routes() {

    register_rest_route('slate-ops/v1', '/jobs', [
      'methods' => 'GET',
      'permission_callback' => [__CLASS__, 'perm_ops'],
      'callback' => [__CLASS__, 'get_jobs'],
      'args' => [
        'status' => ['required' => false],
        'limit' => ['required' => false],
      ],
    ]);

    register_rest_route('slate-ops/v1', '/jobs', [
      'methods' => 'POST',
      'permission_callback' => [__CLASS__, 'perm_cs_or_admin'],
      'callback' => [__CLASS__, 'create_job'],
    ]);

    register_rest_route('slate-ops/v1', '/jobs/(?P<id>\d+)', [
      'methods' => 'POST',
      'permission_callback' => [__CLASS__, 'perm_ops'],
      'callback' => [__CLASS__, 'update_job'],
    ]);
  }

  public static function perm_ops() {
    return Slate_Ops_Utils::can_access_ops();
  }

  public static function perm_cs_or_admin() {
    return is_user_logged_in() && (
      current_user_can(Slate_Ops_Utils::CAP_ADMIN) ||
      current_user_can(Slate_Ops_Utils::CAP_CS) ||
      current_user_can('administrator')
    );
  }

  public static function get_jobs($req) {
    global $wpdb;
    $t = Slate_Ops_Utils::jobs_table();
    $status = sanitize_text_field($req->get_param('status'));
    $limit = (int)($req->get_param('limit') ?: 100);
    $limit = max(1, min(500, $limit));

    $where = "1=1";
    $params = [];

    if ($status) {
      $where .= " AND status=%s";
      $params[] = $status;
    }

    $sql = "SELECT job_id, so_number, vin, customer_name, dealer_name, status, dealer_status, work_center, estimated_minutes, scheduled_start, scheduled_finish, assigned_user_id, updated_at
            FROM $t WHERE $where ORDER BY updated_at DESC LIMIT $limit";

    if ($params) {
      $sql = $wpdb->prepare($sql, ...$params);
    }

    $rows = $wpdb->get_results($sql, ARRAY_A);

    return ['jobs' => $rows];
  }

  public static function create_job($req) {
    global $wpdb;
    $t = Slate_Ops_Utils::jobs_table();
    $body = $req->get_json_params();

    $data = [
      'so_number' => sanitize_text_field($body['so_number'] ?? ''),
      'vin' => sanitize_text_field($body['vin'] ?? ''),
      'customer_name' => sanitize_text_field($body['customer_name'] ?? ''),
      'dealer_name' => sanitize_text_field($body['dealer_name'] ?? ''),
      'status' => sanitize_text_field($body['status'] ?? 'UNSCHEDULED'),
      'dealer_status' => sanitize_text_field($body['dealer_status'] ?? 'waiting'),
      'created_at' => Slate_Ops_Utils::now_gmt(),
      'updated_at' => Slate_Ops_Utils::now_gmt(),
    ];

    if (!$data['status']) $data['status'] = 'UNSCHEDULED';
    if (!$data['dealer_status']) $data['dealer_status'] = 'waiting';

    $wpdb->insert($t, $data);
    $job_id = (int)$wpdb->insert_id;

    Slate_Ops_Contract::fire_job_created($job_id);

    return ['ok' => true, 'job_id' => $job_id];
  }

  public static function update_job($req) {
    global $wpdb;
    $t = Slate_Ops_Utils::jobs_table();
    $job_id = (int)$req['id'];

    $body = $req->get_json_params();
    $job = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE job_id=%d", $job_id), ARRAY_A);
    if (!$job) return new WP_Error('not_found', 'Job not found', ['status' => 404]);

    $allowed = array_keys(Slate_Ops_Contract::schedulable_job_fields());
    $allowed = array_merge($allowed, ['so_number','vin','customer_name','dealer_name','status','dealer_status']);

    $updates = [];
    $changed = [];

    foreach ($allowed as $k) {
      if (!array_key_exists($k, $body)) continue;

      if (in_array($k, ['estimated_minutes','assigned_user_id'], true)) {
        $val = (int)$body[$k];
        $updates[$k] = $val ?: null;
      } elseif ($k === 'requested_date') {
        $val = sanitize_text_field($body[$k]);
        $updates[$k] = $val ?: null;
      } else {
        $val = sanitize_text_field($body[$k]);
        $updates[$k] = $val !== '' ? $val : null;
      }

      $changed[] = $k;
    }

    if (!$updates) return ['ok' => true, 'job_id' => $job_id, 'changed' => []];

    $updates['updated_at'] = Slate_Ops_Utils::now_gmt();

    $wpdb->update($t, $updates, ['job_id' => $job_id]);

    Slate_Ops_Contract::fire_job_updated($job_id, $changed);

    // If schedule-related fields changed, fire schedule hook too
    $sched_fields = ['scheduled_start','scheduled_finish','work_center','estimated_minutes','assigned_user_id'];
    if (count(array_intersect($sched_fields, $changed)) > 0) {
      Slate_Ops_Contract::fire_schedule_updated($job_id, array_values(array_intersect($sched_fields, $changed)));
    }

    return ['ok' => true, 'job_id' => $job_id, 'changed' => $changed];
  }
}
