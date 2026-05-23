<?php
/**
 * Slate_Ops_Quality_REST — REST endpoints for the Quality module.
 *
 * Routes are mounted on the existing slate-ops/v1 namespace alongside the
 * other Ops endpoints. Capability gates use the existing CAP_SUBMIT_QC and
 * CAP_REVIEW_QC capabilities (techs submit, supervisors review).
 */
if (!defined('ABSPATH')) exit;

class Slate_Ops_Quality_REST {

  const NS = 'slate-ops/v1';

  // ── Permission gates ────────────────────────────────────────────────────

  public static function can_read() {
    return Slate_Ops_Utils::can_access();
  }

  public static function can_submit() {
    return is_user_logged_in() && (
      current_user_can(Slate_Ops_Utils::CAP_SUBMIT_QC) ||
      current_user_can(Slate_Ops_Utils::CAP_TECH) ||
      current_user_can(Slate_Ops_Utils::CAP_SUPERVISOR) ||
      current_user_can(Slate_Ops_Utils::CAP_ADMIN)
    );
  }

  public static function can_review() {
    return is_user_logged_in() && (
      current_user_can(Slate_Ops_Utils::CAP_REVIEW_QC) ||
      current_user_can(Slate_Ops_Utils::CAP_SUPERVISOR) ||
      current_user_can(Slate_Ops_Utils::CAP_ADMIN)
    );
  }

  /**
   * Validates that a (job, form_code) tuple is well-formed and that the
   * form_code is required for the job's type. Returns either an
   * [$job_row, $template] pair or a WP_Error.
   *
   * This is the single chokepoint every mutating endpoint goes through so
   * the job-type → form-set contract cannot be bypassed by hand-crafted
   * REST calls.
   */
  private static function resolve_job_form($job_id, $form_code) {
    $job_id    = (int) $job_id;
    $form_code = strtoupper(sanitize_text_field((string) $form_code));

    if ($job_id <= 0) {
      return new WP_Error('quality_invalid_job', 'Invalid job id.', ['status' => 400]);
    }
    if (!class_exists('Slate_Ops_Jobs')) {
      return new WP_Error('quality_jobs_unavailable', 'Jobs data layer unavailable.', ['status' => 500]);
    }
    $job = Slate_Ops_Jobs::get($job_id);
    if (!$job) {
      return new WP_Error('quality_job_not_found', 'Job not found.', ['status' => 404]);
    }
    $template = Slate_Ops_Quality::get_form_template($form_code);
    if (!$template) {
      return new WP_Error('quality_unknown_form', 'Unknown form code.', ['status' => 404]);
    }
    $required = Slate_Ops_Quality::required_forms_for_job_type($job['job_type'] ?? '');
    if (!in_array($form_code, $required, true)) {
      return new WP_Error('quality_form_not_required', 'This form is not part of the required set for this job type.', ['status' => 409]);
    }
    return [$job, $template];
  }

  /**
   * Submit/draft/photo gate. Techs may only touch forms for jobs assigned
   * to them (assigned_user_id or primary_owner_id). Supervisors and admins
   * may always touch any job's form.
   */
  private static function user_can_edit_job($job) {
    $uid = get_current_user_id();
    if (!$uid) return false;
    if (current_user_can(Slate_Ops_Utils::CAP_ADMIN))      return true;
    if (current_user_can(Slate_Ops_Utils::CAP_SUPERVISOR)) return true;
    if (current_user_can(Slate_Ops_Utils::CAP_TECH)) {
      $assigned = (int) ($job['assigned_user_id'] ?? 0);
      $owner    = (int) ($job['primary_owner_id'] ?? 0);
      return ($uid === $assigned || $uid === $owner);
    }
    return false;
  }

  // ── Route registration ─────────────────────────────────────────────────

  public static function register_routes() {
    register_rest_route(self::NS, '/quality/registry', [
      'methods'             => 'GET',
      'permission_callback' => [__CLASS__, 'can_read'],
      'callback'            => [__CLASS__, 'h_registry'],
    ]);

    register_rest_route(self::NS, '/quality/dashboard', [
      'methods'             => 'GET',
      'permission_callback' => [__CLASS__, 'can_read'],
      'callback'            => [__CLASS__, 'h_dashboard'],
    ]);

    register_rest_route(self::NS, '/quality/jobs/(?P<job_id>\d+)', [
      'methods'             => 'GET',
      'permission_callback' => [__CLASS__, 'can_read'],
      'callback'            => [__CLASS__, 'h_job'],
    ]);

    register_rest_route(self::NS, '/quality/jobs/(?P<job_id>\d+)/forms/(?P<form_code>[A-Z0-9\-]+)', [
      'methods'             => 'GET',
      'permission_callback' => [__CLASS__, 'can_read'],
      'callback'            => [__CLASS__, 'h_form'],
    ]);

    register_rest_route(self::NS, '/quality/jobs/(?P<job_id>\d+)/forms/(?P<form_code>[A-Z0-9\-]+)/draft', [
      'methods'             => 'POST',
      'permission_callback' => [__CLASS__, 'can_submit'],
      'callback'            => [__CLASS__, 'h_save_draft'],
    ]);

    register_rest_route(self::NS, '/quality/jobs/(?P<job_id>\d+)/forms/(?P<form_code>[A-Z0-9\-]+)/submit', [
      'methods'             => 'POST',
      'permission_callback' => [__CLASS__, 'can_submit'],
      'callback'            => [__CLASS__, 'h_submit'],
    ]);

    register_rest_route(self::NS, '/quality/jobs/(?P<job_id>\d+)/forms/(?P<form_code>[A-Z0-9\-]+)/review', [
      'methods'             => 'POST',
      'permission_callback' => [__CLASS__, 'can_review'],
      'callback'            => [__CLASS__, 'h_review'],
    ]);

    register_rest_route(self::NS, '/quality/jobs/(?P<job_id>\d+)/forms/(?P<form_code>[A-Z0-9\-]+)/unlock', [
      'methods'             => 'POST',
      'permission_callback' => [__CLASS__, 'can_review'],
      'callback'            => [__CLASS__, 'h_unlock'],
    ]);

    register_rest_route(self::NS, '/quality/jobs/(?P<job_id>\d+)/forms/(?P<form_code>[A-Z0-9\-]+)/photos', [
      'methods'             => 'POST',
      'permission_callback' => [__CLASS__, 'can_submit'],
      'callback'            => [__CLASS__, 'h_upload_photo'],
    ]);
  }

  // ── Handlers ───────────────────────────────────────────────────────────

  public static function h_registry() {
    return rest_ensure_response([
      'forms'       => array_values(Slate_Ops_Quality::form_registry()),
      'statuses'    => Slate_Ops_Quality::allowed_statuses(),
      'fingerprint' => Slate_Ops_Quality::registry_fingerprint(),
      'version'     => defined('SLATE_OPS_VERSION') ? SLATE_OPS_VERSION : null,
    ]);
  }

  public static function h_dashboard(WP_REST_Request $r) {
    $status = $r->get_param('status') ? sanitize_key((string) $r->get_param('status')) : null;
    if ($status && !in_array($status, Slate_Ops_Quality::allowed_statuses(), true)) {
      $status = null;
    }
    $jobs = Slate_Ops_Quality::list_jobs([
      'status' => $status,
      'limit'  => 200,
    ]);

    // Tech-only viewers see their own assignments only. CS / supervisor /
    // admin see everything.
    $is_tech_only = current_user_can(Slate_Ops_Utils::CAP_TECH)
      && !current_user_can(Slate_Ops_Utils::CAP_SUPERVISOR)
      && !current_user_can(Slate_Ops_Utils::CAP_ADMIN)
      && !current_user_can(Slate_Ops_Utils::CAP_CS)
      && !current_user_can(Slate_Ops_Utils::CAP_CS_LEGACY);
    if ($is_tech_only) {
      $uid = get_current_user_id();
      $jobs = array_values(array_filter($jobs, function ($j) use ($uid) {
        return $uid && ((int) ($j['assigned_user_id'] ?? 0) === $uid);
      }));
    }

    return rest_ensure_response([
      'buckets' => Slate_Ops_Quality::bucket_counts(),
      'jobs'    => $jobs,
    ]);
  }

  public static function h_job(WP_REST_Request $r) {
    $job_id = (int) $r['job_id'];
    $desc   = Slate_Ops_Quality::describe_job($job_id);
    if (!$desc) {
      return new WP_Error('quality_job_not_found', 'Job not found.', ['status' => 404]);
    }
    // Tech-only viewers may only read jobs assigned to them. CS / supervisor
    // / admin see every job in the queue.
    $is_tech_only = current_user_can(Slate_Ops_Utils::CAP_TECH)
      && !current_user_can(Slate_Ops_Utils::CAP_SUPERVISOR)
      && !current_user_can(Slate_Ops_Utils::CAP_ADMIN)
      && !current_user_can(Slate_Ops_Utils::CAP_CS)
      && !current_user_can(Slate_Ops_Utils::CAP_CS_LEGACY);
    if ($is_tech_only) {
      $uid = (int) get_current_user_id();
      if ($uid !== (int) ($desc['assigned_user_id'] ?? 0)) {
        return new WP_Error('quality_forbidden', 'This job is not assigned to you.', ['status' => 403]);
      }
    }
    return rest_ensure_response($desc);
  }

  public static function h_form(WP_REST_Request $r) {
    $resolved = self::resolve_job_form($r['job_id'], $r['form_code']);
    if (is_wp_error($resolved)) return $resolved;
    list($job, $template) = $resolved;

    $row = Slate_Ops_Quality::ensure_form_row((int) $job['job_id'], $template['code']);
    return rest_ensure_response([
      'template'    => $template,
      'row'         => $row,
      'photos'      => self::hydrate_photos($row['photos'] ?? []),
      'fingerprint' => Slate_Ops_Quality::registry_fingerprint(),
      'version'     => defined('SLATE_OPS_VERSION') ? SLATE_OPS_VERSION : null,
    ]);
  }

  public static function h_save_draft(WP_REST_Request $r) {
    $resolved = self::resolve_job_form($r['job_id'], $r['form_code']);
    if (is_wp_error($resolved)) return $resolved;
    list($job, $template) = $resolved;

    if (!self::user_can_edit_job($job)) {
      return new WP_Error('quality_forbidden', 'You can only edit Quality forms for jobs assigned to you.', ['status' => 403]);
    }
    $row = Slate_Ops_Quality::ensure_form_row((int) $job['job_id'], $template['code']);
    if (self::row_is_locked_for_edits($row)) {
      return new WP_Error('quality_locked', 'This form is locked. A supervisor must unlock it before changes.', ['status' => 423]);
    }

    $payload = $r->get_json_params();
    if (!is_array($payload)) $payload = [];
    $payload = self::sanitize_payload($payload);
    $result  = Slate_Ops_Quality::save_draft((int) $job['job_id'], $template['code'], $payload);
    if (is_wp_error($result)) return $result;
    return rest_ensure_response($result);
  }

  public static function h_submit(WP_REST_Request $r) {
    $resolved = self::resolve_job_form($r['job_id'], $r['form_code']);
    if (is_wp_error($resolved)) return $resolved;
    list($job, $template) = $resolved;

    if (!self::user_can_edit_job($job)) {
      return new WP_Error('quality_forbidden', 'You can only submit Quality forms for jobs assigned to you.', ['status' => 403]);
    }

    $body = $r->get_json_params();
    if (!is_array($body)) $body = [];

    $row_before = Slate_Ops_Quality::ensure_form_row((int) $job['job_id'], $template['code']);
    if (self::row_is_locked_for_edits($row_before)) {
      return new WP_Error('quality_locked', 'This form is locked.', ['status' => 423]);
    }

    if (!empty($body['payload']) && is_array($body['payload'])) {
      $payload = self::sanitize_payload($body['payload']);
      Slate_Ops_Quality::save_draft((int) $job['job_id'], $template['code'], $payload);
    }

    $row    = Slate_Ops_Quality::get_form((int) $job['job_id'], $template['code']);
    $errors = self::validate_for_submit($template, $row);
    if (!empty($errors)) {
      return new WP_Error('quality_incomplete', 'Some items still need to be completed.', [
        'status' => 422, 'errors' => $errors,
      ]);
    }

    $signature = [
      'typed_name' => sanitize_text_field($body['signature']['typed_name'] ?? ''),
    ];
    $result = Slate_Ops_Quality::submit_form((int) $job['job_id'], $template['code'], $signature);
    if (is_wp_error($result)) return $result;
    return rest_ensure_response($result);
  }

  public static function h_review(WP_REST_Request $r) {
    $resolved = self::resolve_job_form($r['job_id'], $r['form_code']);
    if (is_wp_error($resolved)) return $resolved;
    list($job, $template) = $resolved;

    $body = $r->get_json_params();
    if (!is_array($body)) $body = [];

    $decision = sanitize_key($body['decision'] ?? '');
    $note     = (string) ($body['note'] ?? '');

    if (!in_array($decision, [Slate_Ops_Quality::STATUS_PASSED, Slate_Ops_Quality::STATUS_NEEDS_CORRECTION], true)) {
      return new WP_Error('quality_invalid_decision', 'Decision must be passed or needs_correction.', ['status' => 400]);
    }

    $result = Slate_Ops_Quality::review_form((int) $job['job_id'], $template['code'], $decision, $note);
    if (is_wp_error($result)) return $result;
    return rest_ensure_response($result);
  }

  public static function h_unlock(WP_REST_Request $r) {
    $resolved = self::resolve_job_form($r['job_id'], $r['form_code']);
    if (is_wp_error($resolved)) return $resolved;
    list($job, $template) = $resolved;

    $body   = $r->get_json_params();
    $reason = (string) ($body['reason'] ?? '');
    $result = Slate_Ops_Quality::unlock_form((int) $job['job_id'], $template['code'], $reason);
    if (is_wp_error($result)) return $result;
    return rest_ensure_response($result);
  }

  public static function h_upload_photo(WP_REST_Request $r) {
    $resolved = self::resolve_job_form($r['job_id'], $r['form_code']);
    if (is_wp_error($resolved)) return $resolved;
    list($job, $template) = $resolved;

    if (!self::user_can_edit_job($job)) {
      return new WP_Error('quality_forbidden', 'You can only attach photos for jobs assigned to you.', ['status' => 403]);
    }

    $slot_key = sanitize_key((string) ($_POST['slot'] ?? ''));
    if ($slot_key === '') {
      return new WP_Error('quality_slot_required', 'Photo slot is required.', ['status' => 400]);
    }
    // Slot must belong to the form's defined slot catalogue.
    $valid_slot = false;
    foreach (($template['photo_slots'] ?? []) as $s) {
      if ($s['key'] === $slot_key) { $valid_slot = true; break; }
    }
    if (!$valid_slot) {
      return new WP_Error('quality_unknown_slot', 'Unknown photo slot for this form.', ['status' => 400]);
    }

    $row_before = Slate_Ops_Quality::ensure_form_row((int) $job['job_id'], $template['code']);
    if (self::row_is_locked_for_edits($row_before)) {
      return new WP_Error('quality_locked', 'This form is locked.', ['status' => 423]);
    }

    if (empty($_FILES['file'])) {
      return new WP_Error('quality_no_file', 'No file uploaded.', ['status' => 400]);
    }
    if (!function_exists('media_handle_upload')) {
      require_once ABSPATH . 'wp-admin/includes/file.php';
      require_once ABSPATH . 'wp-admin/includes/media.php';
      require_once ABSPATH . 'wp-admin/includes/image.php';
    }
    // Restrict to image MIME types — Quality slots are intake/sign-off photos.
    $overrides = [
      'test_form' => false,
      'mimes'     => [
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png'          => 'image/png',
        'gif'          => 'image/gif',
        'webp'         => 'image/webp',
        'heic'         => 'image/heic',
      ],
    ];
    $attachment_id = media_handle_upload('file', 0, [], $overrides);
    if (is_wp_error($attachment_id)) return $attachment_id;

    update_post_meta($attachment_id, '_slate_ops_quality_job_id',   (int) $job['job_id']);
    update_post_meta($attachment_id, '_slate_ops_quality_form_code', $template['code']);
    update_post_meta($attachment_id, '_slate_ops_quality_slot',     $slot_key);

    $row = Slate_Ops_Quality::attach_photo((int) $job['job_id'], $template['code'], $slot_key, (int) $attachment_id);
    if (is_wp_error($row)) return $row;

    return rest_ensure_response([
      'attachment_id' => (int) $attachment_id,
      'url'           => wp_get_attachment_image_url($attachment_id, 'medium'),
      'thumb_url'     => wp_get_attachment_image_url($attachment_id, 'thumbnail'),
      'row'           => $row,
      'photos'        => self::hydrate_photos($row['photos'] ?? []),
    ]);
  }

  /**
   * A form is locked for tech edits when it has a non-null locked_at, OR
   * when it's already in a terminal review state (passed or submitted).
   * Supervisor Unlock clears locked_at and resets status to in_progress.
   */
  private static function row_is_locked_for_edits($row) {
    if (!$row) return false;
    if (!empty($row['locked_at'])) return true;
    if (in_array($row['status'], [Slate_Ops_Quality::STATUS_SUBMITTED, Slate_Ops_Quality::STATUS_PASSED], true)) return true;
    return false;
  }

  // ── Helpers ────────────────────────────────────────────────────────────

  private static function sanitize_payload(array $payload) {
    $out = [];
    // checklist: { section_key: { item_key: { result, note, initials, ts } } }
    if (!empty($payload['checklist']) && is_array($payload['checklist'])) {
      $checklist = [];
      foreach ($payload['checklist'] as $sec_key => $items) {
        $sec = sanitize_key((string) $sec_key);
        if (!is_array($items)) continue;
        $checklist[$sec] = [];
        foreach ($items as $item_key => $resp) {
          $ik = sanitize_key((string) $item_key);
          if (!is_array($resp)) continue;
          $result = sanitize_key($resp['result'] ?? '');
          if (!in_array($result, ['pass', 'fail', ''], true)) $result = '';
          $checklist[$sec][$ik] = [
            'result'    => $result,
            'note'      => sanitize_textarea_field((string) ($resp['note'] ?? '')),
            'initials'  => sanitize_text_field((string) ($resp['initials'] ?? '')),
            'user_id'   => (int) get_current_user_id(),
            'timestamp' => current_time('mysql'),
          ];
        }
      }
      $out['checklist'] = $checklist;
    }

    if (!empty($payload['vehicle']) && is_array($payload['vehicle'])) {
      $v = $payload['vehicle'];
      $out['vehicle'] = [
        'vin'        => sanitize_text_field((string) ($v['vin'] ?? '')),
        'odometer'   => sanitize_text_field((string) ($v['odometer'] ?? '')),
        'key_count'  => sanitize_text_field((string) ($v['key_count'] ?? '')),
        'rvia_no'    => sanitize_text_field((string) ($v['rvia_no'] ?? '')),
        'notes'      => sanitize_textarea_field((string) ($v['notes'] ?? '')),
      ];
    }

    if (!empty($payload['notes'])) {
      $out['notes'] = sanitize_textarea_field((string) $payload['notes']);
    }
    return $out;
  }

  private static function validate_for_submit($template, $row) {
    $errors = [];
    if (!$template) return ['form' => 'Unknown form.'];
    $checklist = is_array($row['payload']['checklist'] ?? null) ? $row['payload']['checklist'] : [];
    foreach ($template['sections'] as $section) {
      $sk = $section['key'];
      foreach ($section['items'] as $item) {
        $ik   = $item['key'];
        $resp = $checklist[$sk][$ik] ?? null;
        $result = $resp['result'] ?? '';
        if ($result !== 'pass' && $result !== 'fail') {
          $errors[] = ['section' => $sk, 'item' => $ik, 'reason' => 'missing_response'];
        } elseif ($result === 'fail' && trim((string) ($resp['note'] ?? '')) === '') {
          $errors[] = ['section' => $sk, 'item' => $ik, 'reason' => 'fail_note_required'];
        }
      }
    }
    $photos = is_array($row['photos'] ?? null) ? $row['photos'] : [];
    foreach (($template['photo_slots'] ?? []) as $slot) {
      if (!empty($slot['required']) && empty($photos[$slot['key']])) {
        $errors[] = ['slot' => $slot['key'], 'reason' => 'photo_required'];
      }
    }
    return $errors;
  }

  private static function hydrate_photos($photos) {
    $out = [];
    if (!is_array($photos)) return $out;
    foreach ($photos as $slot => $ids) {
      $out[$slot] = [];
      if (!is_array($ids)) continue;
      foreach ($ids as $aid) {
        $aid = (int) $aid;
        if (!$aid) continue;
        $out[$slot][] = [
          'attachment_id' => $aid,
          'url'           => wp_get_attachment_image_url($aid, 'medium'),
          'thumb_url'     => wp_get_attachment_image_url($aid, 'thumbnail'),
        ];
      }
    }
    return $out;
  }
}
