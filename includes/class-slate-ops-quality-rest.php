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
      'forms'    => array_values(Slate_Ops_Quality::form_registry()),
      'statuses' => Slate_Ops_Quality::allowed_statuses(),
    ]);
  }

  public static function h_dashboard(WP_REST_Request $r) {
    $status = $r->get_param('status') ? sanitize_key((string) $r->get_param('status')) : null;
    if ($status && !in_array($status, Slate_Ops_Quality::allowed_statuses(), true)) {
      $status = null;
    }
    return rest_ensure_response([
      'buckets' => Slate_Ops_Quality::bucket_counts(),
      'jobs'    => Slate_Ops_Quality::list_jobs([
        'status' => $status,
        'limit'  => 200,
      ]),
    ]);
  }

  public static function h_job(WP_REST_Request $r) {
    $job_id = (int) $r['job_id'];
    $desc   = Slate_Ops_Quality::describe_job($job_id);
    if (!$desc) {
      return new WP_Error('quality_job_not_found', 'Job not found.', ['status' => 404]);
    }
    return rest_ensure_response($desc);
  }

  public static function h_form(WP_REST_Request $r) {
    $job_id    = (int) $r['job_id'];
    $form_code = strtoupper(sanitize_text_field((string) $r['form_code']));
    $template  = Slate_Ops_Quality::get_form_template($form_code);
    if (!$template) {
      return new WP_Error('quality_unknown_form', 'Unknown form code.', ['status' => 404]);
    }
    $row = Slate_Ops_Quality::ensure_form_row($job_id, $form_code);
    return rest_ensure_response([
      'template' => $template,
      'row'      => $row,
      'photos'   => self::hydrate_photos($row['photos'] ?? []),
    ]);
  }

  public static function h_save_draft(WP_REST_Request $r) {
    $job_id    = (int) $r['job_id'];
    $form_code = strtoupper(sanitize_text_field((string) $r['form_code']));
    $payload   = $r->get_json_params();
    if (!is_array($payload)) $payload = [];

    $payload = self::sanitize_payload($payload);
    $result  = Slate_Ops_Quality::save_draft($job_id, $form_code, $payload);
    if (is_wp_error($result)) return $result;
    return rest_ensure_response($result);
  }

  public static function h_submit(WP_REST_Request $r) {
    $job_id    = (int) $r['job_id'];
    $form_code = strtoupper(sanitize_text_field((string) $r['form_code']));
    $body      = $r->get_json_params();
    if (!is_array($body)) $body = [];

    if (!empty($body['payload']) && is_array($body['payload'])) {
      $payload = self::sanitize_payload($body['payload']);
      Slate_Ops_Quality::save_draft($job_id, $form_code, $payload);
    }

    $template = Slate_Ops_Quality::get_form_template($form_code);
    $row      = Slate_Ops_Quality::get_form($job_id, $form_code);
    $errors   = self::validate_for_submit($template, $row);
    if (!empty($errors)) {
      return new WP_Error('quality_incomplete', 'Some items still need to be completed.', [
        'status' => 422, 'errors' => $errors,
      ]);
    }

    $signature = [
      'typed_name' => sanitize_text_field($body['signature']['typed_name'] ?? ''),
    ];
    $result = Slate_Ops_Quality::submit_form($job_id, $form_code, $signature);
    if (is_wp_error($result)) return $result;
    return rest_ensure_response($result);
  }

  public static function h_review(WP_REST_Request $r) {
    $job_id    = (int) $r['job_id'];
    $form_code = strtoupper(sanitize_text_field((string) $r['form_code']));
    $body      = $r->get_json_params();
    if (!is_array($body)) $body = [];

    $decision = sanitize_key($body['decision'] ?? '');
    $note     = (string) ($body['note'] ?? '');

    if (!in_array($decision, [Slate_Ops_Quality::STATUS_PASSED, Slate_Ops_Quality::STATUS_NEEDS_CORRECTION], true)) {
      return new WP_Error('quality_invalid_decision', 'Decision must be passed or needs_correction.', ['status' => 400]);
    }

    $result = Slate_Ops_Quality::review_form($job_id, $form_code, $decision, $note);
    if (is_wp_error($result)) return $result;
    return rest_ensure_response($result);
  }

  public static function h_unlock(WP_REST_Request $r) {
    $job_id    = (int) $r['job_id'];
    $form_code = strtoupper(sanitize_text_field((string) $r['form_code']));
    $body      = $r->get_json_params();
    $reason    = (string) ($body['reason'] ?? '');
    $result    = Slate_Ops_Quality::unlock_form($job_id, $form_code, $reason);
    if (is_wp_error($result)) return $result;
    return rest_ensure_response($result);
  }

  public static function h_upload_photo(WP_REST_Request $r) {
    $job_id    = (int) $r['job_id'];
    $form_code = strtoupper(sanitize_text_field((string) $r['form_code']));
    $slot_key  = sanitize_key((string) ($_POST['slot'] ?? ''));
    if ($slot_key === '') {
      return new WP_Error('quality_slot_required', 'Photo slot is required.', ['status' => 400]);
    }

    if (empty($_FILES['file'])) {
      return new WP_Error('quality_no_file', 'No file uploaded.', ['status' => 400]);
    }
    if (!function_exists('media_handle_upload')) {
      require_once ABSPATH . 'wp-admin/includes/file.php';
      require_once ABSPATH . 'wp-admin/includes/media.php';
      require_once ABSPATH . 'wp-admin/includes/image.php';
    }
    $attachment_id = media_handle_upload('file', 0);
    if (is_wp_error($attachment_id)) return $attachment_id;

    update_post_meta($attachment_id, '_slate_ops_quality_job_id', $job_id);
    update_post_meta($attachment_id, '_slate_ops_quality_form_code', $form_code);
    update_post_meta($attachment_id, '_slate_ops_quality_slot', $slot_key);

    $row = Slate_Ops_Quality::attach_photo($job_id, $form_code, $slot_key, (int) $attachment_id);
    if (is_wp_error($row)) return $row;

    return rest_ensure_response([
      'attachment_id' => (int) $attachment_id,
      'url'           => wp_get_attachment_image_url($attachment_id, 'medium'),
      'thumb_url'     => wp_get_attachment_image_url($attachment_id, 'thumbnail'),
      'row'           => $row,
      'photos'        => self::hydrate_photos($row['photos'] ?? []),
    ]);
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
