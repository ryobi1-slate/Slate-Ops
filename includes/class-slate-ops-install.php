<?php
if (!defined('ABSPATH')) exit;

class Slate_Ops_Install {
  public static function activate() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset = $wpdb->get_charset_collate();
    $jobs = Slate_Ops_Utils::jobs_table();

    $sql = "CREATE TABLE $jobs (
      job_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      so_number VARCHAR(40) NULL,
      vin VARCHAR(40) NULL,
      customer_name VARCHAR(190) NULL,
      dealer_name VARCHAR(190) NULL,

      status VARCHAR(40) NOT NULL DEFAULT 'UNSCHEDULED',
      dealer_status VARCHAR(40) NOT NULL DEFAULT 'waiting',

      work_center VARCHAR(60) NULL,
      estimated_minutes INT UNSIGNED NULL,
      parts_status VARCHAR(40) NULL,
      requested_date DATE NULL,

      scheduled_start DATETIME NULL,
      scheduled_finish DATETIME NULL,
      assigned_user_id BIGINT UNSIGNED NULL,

      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,

      PRIMARY KEY (job_id),
      KEY so_number (so_number),
      KEY status (status),
      KEY dealer_status (dealer_status),
      KEY assigned_user_id (assigned_user_id)
    ) $charset;";

    dbDelta($sql);

    self::add_caps();

    Slate_Ops_UI::register_routes();
    flush_rewrite_rules();
  }

  private static function add_caps() {
    $roles = ['administrator'];
    foreach ($roles as $role_name) {
      $role = get_role($role_name);
      if ($role) {
        $role->add_cap(Slate_Ops_Utils::CAP_ADMIN);
        $role->add_cap(Slate_Ops_Utils::CAP_SUPERVISOR);
        $role->add_cap(Slate_Ops_Utils::CAP_TECH);
        $role->add_cap(Slate_Ops_Utils::CAP_CS);
      }
    }
  }
}
