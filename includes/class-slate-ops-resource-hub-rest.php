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
          'permission_callback' => [__CLASS__, 'perm_review'],
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
    }
  }

  public static function perm_read() {
    return Slate_Ops_Utils::can_access() && slate_ops_current_user_can_access_ops_page('resource-hub');
  }

  public static function perm_review() {
    return self::perm_read() && Slate_Ops_Utils::can_supervisor_or_admin();
  }

  public static function list_resources($req) {
    return rest_ensure_response([
      'ok' => true,
      'resources' => Slate_Ops_Resource_Hub::list_for_current_user(),
      'canReview' => Slate_Ops_Utils::can_supervisor_or_admin(),
      'audiences' => Slate_Ops_Resource_Hub::AUDIENCES,
    ]);
  }

  public static function create_resource($req) {
    $body = $req->get_json_params();
    if (!is_array($body)) {
      $body = [];
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
}
