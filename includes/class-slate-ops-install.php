<?php
if (!defined('ABSPATH')) exit;

class Slate_Ops_Install {

  public static function activate() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();

    $jobs = $wpdb->prefix . 'slate_ops_jobs';
    $segments = $wpdb->prefix . 'slate_ops_time_segments';
    $audit = $wpdb->prefix . 'slate_ops_audit_log';
    $settings = $wpdb->prefix . 'slate_ops_settings';

    $sql_jobs = "CREATE TABLE $jobs (
job_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

-- Source tracking
source VARCHAR(20) NOT NULL DEFAULT 'manual',
created_from VARCHAR(20) NOT NULL DEFAULT 'manual',

-- Portal linkage
portal_quote_id BIGINT UNSIGNED NULL,
quote_number VARCHAR(64) NULL,

-- Identifiers
so_number VARCHAR(32) NULL,
customer_name VARCHAR(255) NULL,
vin VARCHAR(32) NULL,
dealer_name VARCHAR(255) NULL,

-- Classification
job_type VARCHAR(30) NOT NULL DEFAULT 'upfit',
parts_status VARCHAR(20) NULL,

-- Status + scheduling
status VARCHAR(30) NOT NULL DEFAULT 'UNSCHEDULED',
status_detail VARCHAR(100) NULL,
status_updated_at DATETIME NULL,

delay_reason VARCHAR(30) NULL,
priority TINYINT UNSIGNED NOT NULL DEFAULT 3,

assigned_user_id BIGINT UNSIGNED NULL,
work_center VARCHAR(60) NULL,
estimated_minutes INT UNSIGNED NULL,
scheduled_start DATETIME NULL,
scheduled_finish DATETIME NULL,
requested_date DATE NULL,

-- ClickUp
clickup_task_id VARCHAR(64) NULL,
clickup_estimate_ms BIGINT UNSIGNED NULL,

-- Dealer-facing simplified status
dealer_status VARCHAR(20) NOT NULL DEFAULT 'waiting',

-- Audit fields
created_by BIGINT UNSIGNED NULL,
created_at DATETIME NOT NULL,
updated_at DATETIME NOT NULL,

archived_at DATETIME NULL,
archived_by BIGINT UNSIGNED NULL,
archive_reason VARCHAR(255) NULL,

PRIMARY KEY  (job_id),
UNIQUE KEY so_unique (so_number),
KEY status_idx (status),
KEY status_updated_idx (status_updated_at),
KEY assigned_idx (assigned_user_id),
KEY quote_idx (portal_quote_id),
KEY clickup_idx (clickup_task_id),
KEY priority_idx (priority),
KEY created_from_idx (created_from)

    ) $charset_collate;";

    $sql_segments = "CREATE TABLE $segments (
      segment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      job_id BIGINT UNSIGNED NOT NULL,
      user_id BIGINT UNSIGNED NOT NULL,
      start_ts DATETIME NOT NULL,
      end_ts DATETIME NULL,
      reason VARCHAR(30) NULL,
      note TEXT NULL,
      source VARCHAR(20) NOT NULL DEFAULT 'timer',
      state VARCHAR(10) NOT NULL DEFAULT 'active',
      approval_status VARCHAR(12) NOT NULL DEFAULT 'approved',
      approved_by BIGINT UNSIGNED NULL,
      approved_at DATETIME NULL,
      voided_by BIGINT UNSIGNED NULL,
      voided_at DATETIME NULL,
      void_reason VARCHAR(255) NULL,
      created_by BIGINT UNSIGNED NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (segment_id),
      KEY job_idx (job_id),
      KEY user_idx (user_id),
      KEY open_idx (user_id, end_ts),
      KEY state_idx (state),
      KEY approval_idx (approval_status)
    ) $charset_collate;";

    $sql_audit = "CREATE TABLE $audit (
      audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      entity_type VARCHAR(30) NOT NULL,
      entity_id BIGINT UNSIGNED NOT NULL,
      action VARCHAR(30) NOT NULL,
      field_name VARCHAR(80) NULL,
      old_value LONGTEXT NULL,
      new_value LONGTEXT NULL,
      note TEXT NULL,
      user_id BIGINT UNSIGNED NULL,
      ip_address VARCHAR(64) NULL,
      user_agent VARCHAR(255) NULL,
      created_at DATETIME NOT NULL,
      PRIMARY KEY (audit_id),
      KEY ent_idx (entity_type, entity_id),
      KEY user_idx (user_id)
    ) $charset_collate;";

    $sql_settings = "CREATE TABLE $settings (
      id TINYINT UNSIGNED NOT NULL DEFAULT 1,
      shift_start TIME NOT NULL DEFAULT '07:00:00',
      shift_end TIME NOT NULL DEFAULT '15:30:00',
      lunch_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
      break_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 20,
      timezone VARCHAR(64) NOT NULL DEFAULT 'America/Los_Angeles',
      updated_by BIGINT UNSIGNED NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id)
    ) $charset_collate;";

    dbDelta($sql_jobs);
    dbDelta($sql_segments);
    dbDelta($sql_audit);
    dbDelta($sql_settings);

    // Seed settings row if missing.
    $exists = $wpdb->get_var("SELECT COUNT(*) FROM $settings WHERE id=1");
    if (!$exists) {
      $wpdb->insert($settings, [
        'id' => 1,
        'shift_start' => '07:00:00',
        'shift_end' => '15:30:00',
        'lunch_minutes' => 30,
        'break_minutes' => 20,
        'timezone' => 'America/Los_Angeles',
        'updated_at' => gmdate('Y-m-d H:i:s'),
      ]);
    }

    // Flush rewrites once.
    Slate_Ops_Roles::register_roles_caps();
    Slate_Ops_Routes::register_routes();
    flush_rewrite_rules();
  }

  public static function deactivate() {
    flush_rewrite_rules();
  }
}
