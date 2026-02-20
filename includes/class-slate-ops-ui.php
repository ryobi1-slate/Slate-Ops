<?php
if (!defined('ABSPATH')) exit;

class Slate_Ops_UI {

  public static function register_routes() {
    add_rewrite_rule('^ops/?$', 'index.php?slate_ops=1', 'top');
    add_rewrite_tag('%slate_ops%', '1');
    add_filter('template_include', [__CLASS__, 'template'], 99);
  }

  public static function is_ops_request() {
    return (get_query_var('slate_ops') == '1');
  }

  public static function template($template) {
    if (!self::is_ops_request()) return $template;

    Slate_Ops_Utils::require_ops_access();

    return SLATE_OPS_PATH . 'templates/ops-app.php';
  }
}
