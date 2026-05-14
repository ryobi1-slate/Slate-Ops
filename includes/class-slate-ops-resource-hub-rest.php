<?php
if (!defined('ABSPATH')) exit;

class Slate_Ops_Resource_Hub_REST {

  public static function register_routes() {
    foreach (['slate-ops/v1', 'upfitops/v1'] as $ns) {
      register_rest_route($ns, '/resource-hub', [
        [
          'methods' => 'GET',
          'permission_callback' => [__CLASS__, 'perm_read'],
          'callback' => [__CLASS__, 'list_resources'],
        ],
        [
          'methods' => 'POST',
          'permission_callback' => [__CLASS__, 'perm_submit_resource'],
          'callback' => [__CLASS__, 'create_resource'],
        ],
      ]);

      register_rest_route($ns, '/resource-hub/(?P<id>\d+)', [
        [
          'methods' => 'PATCH',
          'permission_callback' => [__CLASS__, 'perm_review'],
          'callback' => [__CLASS__, 'update_resource'],
        ],
      ]);

      register_rest_route($ns, '/resource-hub/context', [
        [
          'methods' => 'GET',
          'permission_callback' => [__CLASS__, 'perm_read'],
          'callback' => [__CLASS__, 'context_resources'],
        ],
      ]);

      register_rest_route($ns, '/resource-hub/media', [
        [
          'methods' => 'POST',
          'permission_callback' => [__CLASS__, 'perm_submit_resource'],
          'callback' => [__CLASS__, 'upload_media'],
        ],
      ]);

      register_rest_route($ns, '/resource-hub/field-notes', [
        [
          'methods' => 'GET',
          'permission_callback' => [__CLASS__, 'perm_review'],
          'callback' => [__CLASS__, 'list_field_notes'],
        ],
        [
          'methods' => 'POST',
          'permission_callback' => [__CLASS__, 'perm_submit_field_note'],
          'callback' => [__CLASS__, 'create_field_note'],
        ],
      ]);

      register_rest_route($ns, '/resource-hub/field-notes/(?P<id>\d+)', [
        [
          'methods' => 'PATCH',
          'permission_callback' => [__CLASS__, 'perm_review'],
          'callback' => [__CLASS__, 'update_field_note'],
        ],
      ]);

      register_rest_route($ns, '/resource-hub/field-notes/(?P<id>\d+)/promote', [
        [
          'methods' => 'POST',
          'permission_callback' => [__CLASS__, 'perm_review'],
          'callback' => [__CLASS__, 'promote_field_note'],
        ],
      ]);
    }
  }

  public static function perm_read() {
    return Slate_Ops_Utils::can_access() && slate_ops_current_user_can_access_ops_page('resource-hub');
  }

  public static function perm_review() {
    return self::perm_read() && Slate_Ops_Utils::can_cs_or_above();
  }

  public static function perm_submit_resource() {
    return self::perm_read();
  }

  public static function perm_submit_field_note() {
    return self::perm_read() && (Slate_Ops_Utils::is_tech() || Slate_Ops_Utils::can_cs_or_above());
  }

  public static function list_resources($req) {
    return rest_ensure_response([
      'ok' => true,
      'resources' => Slate_Ops_Resource_Hub::list_for_current_user(),
      'canReview' => Slate_Ops_Utils::can_cs_or_above(),
      'canAddResource' => self::perm_submit_resource(),
      'canSubmitFieldNote' => self::perm_submit_field_note(),
      'fieldNotes' => Slate_Ops_Utils::can_cs_or_above() ? Slate_Ops_Resource_Hub::list_field_notes('submitted') : [],
      'audiences' => Slate_Ops_Resource_Hub::AUDIENCES,
      'linkTypes' => Slate_Ops_Resource_Hub::LINK_TYPES,
      'noteTypes' => Slate_Ops_Resource_Hub::NOTE_TYPES,
    ]);
  }

  public static function context_resources($req) {
    $type = sanitize_key((string) $req->get_param('type'));
    $key = sanitize_text_field((string) $req->get_param('key'));
    return rest_ensure_response([
      'ok' => true,
      'resources' => Slate_Ops_Resource_Hub::resources_for_context($type, $key),
    ]);
  }

  public static function create_resource($req) {
    $body = $req->get_json_params();
    if (!is_array($body)) {
      $body = [];
    }

    if (!self::perm_review()) {
      $body['status_key'] = 'needs_review';
      $body['source_type'] = 'vendor';
    }

    $resource = Slate_Ops_Resource_Hub::create($body);
    if (is_wp_error($resource)) {
      return $resource;
    }

    return rest_ensure_response([
      'ok' => true,
      'resource' => $resource,
    ]);
  }

  public static function update_resource($req) {
    $body = $req->get_json_params();
    if (!is_array($body)) {
      $body = [];
    }

    $resource = Slate_Ops_Resource_Hub::update((int) $req['id'], $body);
    if (is_wp_error($resource)) {
      return $resource;
    }

    return rest_ensure_response([
      'ok' => true,
      'resource' => $resource,
    ]);
  }

  public static function upload_media($req) {
    if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
      return new WP_Error('missing_upload', 'Choose a file to upload.', ['status' => 400]);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    $file = $_FILES['file'];
    $uploaded = wp_handle_upload($file, [
      'test_form' => false,
      'mimes' => [
        'pdf' => 'application/pdf',
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'mp4' => 'video/mp4',
        'mov' => 'video/quicktime',
      ],
    ]);

    if (!empty($uploaded['error'])) {
      return new WP_Error('upload_failed', sanitize_text_field($uploaded['error']), ['status' => 500]);
    }

    return rest_ensure_response([
      'ok' => true,
      'media' => [
        'name' => sanitize_file_name($file['name'] ?? ''),
        'url' => esc_url_raw($uploaded['url'] ?? ''),
        'type' => sanitize_text_field($uploaded['type'] ?? ''),
        'meta' => self::media_meta_label($file),
      ],
    ]);
  }

  public static function list_field_notes($req) {
    $status = sanitize_key((string) $req->get_param('status'));
    return rest_ensure_response([
      'ok' => true,
      'fieldNotes' => Slate_Ops_Resource_Hub::list_field_notes($status ?: 'submitted'),
    ]);
  }

  public static function create_field_note($req) {
    $body = $req->get_json_params();
    if (!is_array($body)) {
      $body = [];
    }

    $note = Slate_Ops_Resource_Hub::create_field_note($body);
    if (is_wp_error($note)) {
      return $note;
    }

    return rest_ensure_response([
      'ok' => true,
      'fieldNote' => $note,
    ]);
  }

  public static function update_field_note($req) {
    $body = $req->get_json_params();
    if (!is_array($body)) {
      $body = [];
    }

    $note = Slate_Ops_Resource_Hub::update_field_note((int) $req['id'], $body);
    if (is_wp_error($note)) {
      return $note;
    }

    return rest_ensure_response([
      'ok' => true,
      'fieldNote' => $note,
    ]);
  }

  public static function promote_field_note($req) {
    $body = $req->get_json_params();
    if (!is_array($body)) {
      $body = [];
    }

    $result = Slate_Ops_Resource_Hub::promote_field_note((int) $req['id'], $body);
    if (is_wp_error($result)) {
      return $result;
    }

    return rest_ensure_response([
      'ok' => true,
      'fieldNote' => $result['note'],
      'resource' => $result['resource'],
    ]);
  }

  private static function media_meta_label($file) {
    $size = isset($file['size']) ? (int) $file['size'] : 0;
    $type = sanitize_text_field($file['type'] ?? 'file');
    if ($size >= 1048576) {
      $label = round($size / 1048576, 1) . ' MB';
    } elseif ($size >= 1024) {
      $label = round($size / 1024, 1) . ' KB';
    } else {
      $label = $size . ' bytes';
    }
    return $type . ' - ' . $label;
  }
}
