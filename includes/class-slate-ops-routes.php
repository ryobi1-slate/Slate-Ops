<?php
if (!defined('ABSPATH')) exit;

class Slate_Ops_Routes {

  public static function register_routes() {
    add_rewrite_rule('^ops/?$', 'index.php?slate_ops=1', 'top');
    add_rewrite_rule('^ops/(.*)$', 'index.php?slate_ops=1&slate_ops_path=$matches[1]', 'top');

    add_rewrite_tag('%slate_ops%', '1');
    add_rewrite_tag('%slate_ops_path%', '([^&]+)');

    // If permalinks are flushed on activation, no need to flush here.
  }

  public static function is_ops_request() {
    return (get_query_var('slate_ops') == '1');
  }

  public static function require_access_or_redirect() {
    if (Slate_Ops_Utils::require_ops_access()) return;
    auth_redirect();
  }

  public static function current_path() {
    $p = get_query_var('slate_ops_path');
    return $p ? sanitize_text_field($p) : '';
  }
}
