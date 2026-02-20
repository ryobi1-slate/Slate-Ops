<?php
if (!defined('ABSPATH')) exit;

class Slate_Ops_Utils {
  const CAP_ADMIN = 'slate_ops_admin';
  const CAP_SUPERVISOR = 'slate_ops_supervisor';
  const CAP_TECH = 'slate_ops_tech';
  const CAP_CS = 'slate_ops_cs';

  public static function can_access_ops() {
    return is_user_logged_in() && (
      current_user_can('administrator') ||
      current_user_can(self::CAP_ADMIN) ||
      current_user_can(self::CAP_SUPERVISOR) ||
      current_user_can(self::CAP_TECH) ||
      current_user_can(self::CAP_CS)
    );
  }

  public static function require_ops_access() {
    if (!is_user_logged_in()) {
      auth_redirect();
      exit;
    }
    if (!self::can_access_ops()) {
      wp_die('Forbidden', 'Forbidden', ['response' => 403]);
      exit;
    }
  }

  public static function jobs_table() {
    global $wpdb;
    return $wpdb->prefix . 'slate_ops_jobs';
  }

  public static function now_gmt() {
    return gmdate('Y-m-d H:i:s');
  }
}
