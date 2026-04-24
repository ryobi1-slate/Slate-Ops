<?php
if (!defined('ABSPATH')) exit;

class Slate_Ops_ClickUp {

  private static function token() {
    return defined('UPFITOPS_CLICKUP_KEY') ? UPFITOPS_CLICKUP_KEY : '';
  }

  private static function list_id() {
    return defined('UPFITOPS_CLICKUP_LIST') ? UPFITOPS_CLICKUP_LIST : '';
  }

  public static function enabled() {
    return (bool) self::token() && (bool) self::list_id();
  }

  private static function request($method, $url, $body = null) {
    $headers = [
      'Authorization' => self::token(),
      'Content-Type' => 'application/json',
    ];

    $args = [
      'method' => $method,
      'headers' => $headers,
      'timeout' => 20,
    ];

    if ($body !== null) {
      $args['body'] = wp_json_encode($body);
    }

    $resp = wp_remote_request($url, $args);
    if (is_wp_error($resp)) {
      return $resp;
    }
    $code = wp_remote_retrieve_response_code($resp);
    $json = json_decode(wp_remote_retrieve_body($resp), true);

    if ($code < 200 || $code >= 300) {
      return new WP_Error('clickup_error', 'ClickUp API error', ['code' => $code, 'body' => $json]);
    }
    return $json;
  }

  public static function create_unscheduled_task($name, $description, $custom_fields = []) {
    if (!self::enabled()) return new WP_Error('clickup_not_enabled', 'ClickUp not configured');

    $url = 'https://api.clickup.com/api/v2/list/' . rawurlencode(self::list_id()) . '/task';

    // ClickUp expects custom_fields as an array of {id,value}. We don't know IDs yet.
    // We'll store metadata in the description until you provide custom field IDs.
    $body = [
      'name' => $name,
      'description' => $description,
      'status' => 'INTAKE',
    ];

    return self::request('POST', $url, $body);
  }

  public static function update_task_name($task_id, $name) {
    if (!self::enabled()) return new WP_Error('clickup_not_enabled', 'ClickUp not configured');
    $url = 'https://api.clickup.com/api/v2/task/' . rawurlencode($task_id);
    return self::request('PUT', $url, ['name' => $name]);
  }

  public static function add_comment($task_id, $comment_text) {
    if (!self::enabled()) return new WP_Error('clickup_not_enabled', 'ClickUp not configured');
    $url = 'https://api.clickup.com/api/v2/task/' . rawurlencode($task_id) . '/comment';
    return self::request('POST', $url, [
      'comment_text' => $comment_text,
      'notify_all' => false,
    ]);
  }

  public static function get_task($task_id) {
    if (!self::enabled()) return new WP_Error('clickup_not_enabled', 'ClickUp not configured');
    $url = 'https://api.clickup.com/api/v2/task/' . rawurlencode($task_id);
    return self::request('GET', $url);
  }
}
