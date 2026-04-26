<?php
if (!defined('ABSPATH')) exit;

class Slate_Ops_Install {

  public static function activate() {
    self::run_install(true);
  }

  public static function maybe_upgrade() {
    $installed = get_option('slate_ops_version', '0.0.0');
    if (version_compare((string) $installed, (string) SLATE_OPS_VERSION, '>=')) {
      return;
    }

    self::run_install(false);
  }

  private static function run_install($flush_rewrites = false) {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();

    $jobs             = $wpdb->prefix . 'slate_ops_jobs';
    $segments         = $wpdb->prefix . 'slate_ops_time_segments';
    $audit            = $wpdb->prefix . 'slate_ops_audit_log';
    $settings         = $wpdb->prefix . 'slate_ops_settings';
    $work_centers     = $wpdb->prefix . 'slate_ops_work_centers';
    $sched_events     = $wpdb->prefix . 'slate_ops_schedule_events';
    $cap_snaps        = $wpdb->prefix . 'slate_ops_capacity_snapshots';
    $job_reviews      = $wpdb->prefix . 'slate_ops_job_reviews';
    $job_assignments  = $wpdb->prefix . 'slate_ops_job_assignments';
    $blockers         = $wpdb->prefix . 'slate_ops_blockers';
    $schedule_slots   = $wpdb->prefix . 'slate_ops_schedule_slots';
    $eod_reports      = $wpdb->prefix . 'slate_ops_eod_reports';
    $eod_report_lines = $wpdb->prefix . 'slate_ops_eod_report_lines';
    $qc_records       = $wpdb->prefix . 'slate_ops_qc_records';

    $boms             = $wpdb->prefix . 'slate_boms';
    $bom_lines       = $wpdb->prefix . 'slate_bom_lines';
    $products         = $wpdb->prefix . 'slate_products';
    $dealers_ext      = $wpdb->prefix . 'slate_dealers';
    $quotes           = $wpdb->prefix . 'slate_quotes';
    $quote_lines      = $wpdb->prefix . 'slate_quote_lines';

    // ── Purchasing tables ─────────────────────────────
    $pur_vendors     = $wpdb->prefix . 'slate_ops_pur_vendors';
    $pur_items       = $wpdb->prefix . 'slate_ops_pur_items';
    $pur_requests    = $wpdb->prefix . 'slate_ops_pur_requests';
    $pur_orders      = $wpdb->prefix . 'slate_ops_pur_orders';
    $pur_order_lines = $wpdb->prefix . 'slate_ops_pur_order_lines';

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
vin_last8 VARCHAR(8) NULL,
dealer_name VARCHAR(255) NULL,

-- Classification
job_type VARCHAR(30) NOT NULL DEFAULT 'UPFIT',
parts_status VARCHAR(20) NOT NULL DEFAULT 'NOT_READY',

-- Status + scheduling
status VARCHAR(30) NOT NULL DEFAULT 'INTAKE',
status_detail VARCHAR(100) NULL,
status_updated_at DATETIME NULL,

delay_reason VARCHAR(30) NULL,
priority TINYINT UNSIGNED NOT NULL DEFAULT 3,
priority_score SMALLINT UNSIGNED NOT NULL DEFAULT 0,

assigned_user_id BIGINT UNSIGNED NULL,
primary_owner_id BIGINT UNSIGNED NULL,
work_center VARCHAR(60) NULL,
estimated_minutes INT UNSIGNED NULL,
constraint_minutes_required INT UNSIGNED NULL,
scope_status VARCHAR(30) NOT NULL DEFAULT 'ESTIMATING',
scheduling_status VARCHAR(30) NOT NULL DEFAULT 'PENDING_RELEASE',
target_week_id VARCHAR(50) NULL,
ready_queue_entered_at DATETIME NULL,
override_flag TINYINT(1) NOT NULL DEFAULT 0,
override_reason VARCHAR(255) NULL,
override_notes TEXT NULL,

-- Scheduler control
scheduler_locked TINYINT(1) NOT NULL DEFAULT 0,
hold_reason VARCHAR(255) NULL,
schedule_notes TEXT NULL,
scheduling_flag VARCHAR(20) NULL,

scheduled_start DATETIME NULL,
scheduled_finish DATETIME NULL,
requested_date DATE NULL,
promised_date DATE NULL,
target_ship_date DATE NULL,

-- Intake fields
customer_expectations TEXT NULL,
scope_summary TEXT NULL,
documents_complete TINYINT(1) NOT NULL DEFAULT 0,

-- Execution flags
awaiting_direction TINYINT(1) NOT NULL DEFAULT 0,
percent_complete TINYINT UNSIGNED NOT NULL DEFAULT 0,

-- Cached rollups (updated by server-side hooks)
actual_minutes_approved INT UNSIGNED NOT NULL DEFAULT 0,
actual_minutes_pending INT UNSIGNED NOT NULL DEFAULT 0,
current_task_summary VARCHAR(500) NULL,

sales_person VARCHAR(255) NULL,
stock_number VARCHAR(64) NULL,
notes TEXT NULL,

-- Manual queue ordering for Tech Up Next
queue_order INT UNSIGNED NULL,

-- ClickUp
clickup_task_id VARCHAR(64) NULL,
clickup_estimate_ms BIGINT UNSIGNED NULL,

-- Dealer-facing simplified status
dealer_status VARCHAR(20) NOT NULL DEFAULT 'waiting',

-- Multi-location
location_id BIGINT UNSIGNED NOT NULL DEFAULT 1,

-- Audit fields
created_by BIGINT UNSIGNED NULL,
created_at DATETIME NOT NULL,
updated_at DATETIME NOT NULL,

archived_at DATETIME NULL,
archived_by BIGINT UNSIGNED NULL,
archive_reason VARCHAR(255) NULL,

PRIMARY KEY  (job_id),
KEY so_idx (so_number),
KEY status_idx (status),
KEY status_updated_idx (status_updated_at),
KEY assigned_idx (assigned_user_id),
KEY primary_owner_idx (primary_owner_id),
KEY quote_idx (portal_quote_id),
KEY clickup_idx (clickup_task_id),
KEY priority_idx (priority),
KEY created_from_idx (created_from),
KEY scheduling_flag_idx (scheduling_flag),
KEY scheduler_locked_idx (scheduler_locked),
KEY location_idx (location_id),
KEY awaiting_idx (awaiting_direction)

    ) $charset_collate;";

    $sql_segments = "CREATE TABLE $segments (
      segment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      job_id BIGINT UNSIGNED NOT NULL,
      user_id BIGINT UNSIGNED NOT NULL,
      start_ts DATETIME NOT NULL,
      end_ts DATETIME NULL,
      task VARCHAR(100) NULL,
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
      location_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
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
      note_type VARCHAR(30) NULL,
      user_id BIGINT UNSIGNED NULL,
      ip_address VARCHAR(64) NULL,
      user_agent VARCHAR(255) NULL,
      created_at DATETIME NOT NULL,
      PRIMARY KEY (audit_id),
      KEY ent_idx (entity_type, entity_id),
      KEY user_idx (user_id),
      KEY action_idx (action)
    ) $charset_collate;";

    $sql_settings = "CREATE TABLE $settings (
      id TINYINT UNSIGNED NOT NULL DEFAULT 1,
      shift_start TIME NOT NULL DEFAULT '07:00:00',
      shift_end TIME NOT NULL DEFAULT '15:30:00',
      lunch_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
      break_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 10,
      break_count TINYINT UNSIGNED NOT NULL DEFAULT 2,
      capacity_threshold_pct TINYINT UNSIGNED NOT NULL DEFAULT 70,
      ot_threshold_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 480,
      timezone VARCHAR(64) NOT NULL DEFAULT 'America/Los_Angeles',
      updated_by BIGINT UNSIGNED NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id)
    ) $charset_collate;";

    $sql_work_centers = "CREATE TABLE $work_centers (
      wc_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      wc_code VARCHAR(30) NOT NULL,
      display_name VARCHAR(100) NOT NULL,
      daily_capacity_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 840,
      weekly_capacity_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 4200,
      is_constraint TINYINT(1) NOT NULL DEFAULT 0,
      sequence_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
      color VARCHAR(7) NOT NULL DEFAULT '#5A6B65',
      active TINYINT(1) NOT NULL DEFAULT 1,
      location_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (wc_id),
      UNIQUE KEY wc_code_idx (wc_code)
    ) $charset_collate;";

    $sql_schedule_events = "CREATE TABLE $sched_events (
      event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      job_id BIGINT UNSIGNED NOT NULL,
      work_center_id BIGINT UNSIGNED NOT NULL,
      start_datetime DATETIME NOT NULL,
      end_datetime DATETIME NOT NULL,
      allocated_minutes INT UNSIGNED NOT NULL DEFAULT 0,
      event_type VARCHAR(20) NOT NULL DEFAULT 'planned',
      created_by BIGINT UNSIGNED NULL,
      updated_by BIGINT UNSIGNED NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (event_id),
      KEY job_idx (job_id),
      KEY wc_idx (work_center_id),
      KEY range_idx (start_datetime, end_datetime)
    ) $charset_collate;";

    $sql_capacity_snapshots = "CREATE TABLE $cap_snaps (
      snapshot_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      snapshot_date DATE NOT NULL,
      work_center_id BIGINT UNSIGNED NOT NULL,
      capacity_minutes INT NOT NULL DEFAULT 0,
      allocated_minutes INT NOT NULL DEFAULT 0,
      available_minutes INT NOT NULL DEFAULT 0,
      overload_minutes INT NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL,
      PRIMARY KEY (snapshot_id),
      UNIQUE KEY date_wc_idx (snapshot_date, work_center_id)
    ) $charset_collate;";

    // ── New tables (Phase 0 backbone) ─────────────────

    $sql_job_reviews = "CREATE TABLE $job_reviews (
      review_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      job_id BIGINT UNSIGNED NOT NULL,
      reviewer_id BIGINT UNSIGNED NOT NULL,
      decision VARCHAR(20) NOT NULL,
      review_notes TEXT NULL,
      hold_reason VARCHAR(100) NULL,
      return_reason TEXT NULL,
      location_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL,
      PRIMARY KEY (review_id),
      KEY job_idx (job_id),
      KEY reviewer_idx (reviewer_id),
      KEY decision_idx (decision)
    ) $charset_collate;";

    $sql_job_assignments = "CREATE TABLE $job_assignments (
      assignment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      job_id BIGINT UNSIGNED NOT NULL,
      user_id BIGINT UNSIGNED NOT NULL,
      role VARCHAR(20) NOT NULL,
      assigned_by BIGINT UNSIGNED NOT NULL,
      assigned_task TEXT NULL,
      planned_start_point VARCHAR(255) NULL,
      planned_stop_point VARCHAR(255) NULL,
      effective_date DATE NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
      location_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL,
      ended_at DATETIME NULL,
      PRIMARY KEY (assignment_id),
      KEY job_idx (job_id),
      KEY user_idx (user_id),
      KEY job_user_idx (job_id, user_id, status),
      KEY effective_idx (effective_date, status),
      KEY role_idx (role)
    ) $charset_collate;";

    $sql_blockers = "CREATE TABLE $blockers (
      blocker_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      job_id BIGINT UNSIGNED NOT NULL,
      reported_by BIGINT UNSIGNED NOT NULL,
      blocker_type VARCHAR(30) NOT NULL,
      description TEXT NOT NULL,
      severity VARCHAR(10) NOT NULL DEFAULT 'BLOCKING',
      status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
      acknowledged_by BIGINT UNSIGNED NULL,
      acknowledged_at DATETIME NULL,
      resolved_by BIGINT UNSIGNED NULL,
      resolution_notes TEXT NULL,
      resolved_at DATETIME NULL,
      location_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL,
      PRIMARY KEY (blocker_id),
      KEY job_idx (job_id),
      KEY status_idx (status),
      KEY severity_status_idx (severity, status),
      KEY reporter_idx (reported_by)
    ) $charset_collate;";

    $sql_schedule_slots = "CREATE TABLE $schedule_slots (
      slot_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      job_id BIGINT UNSIGNED NOT NULL,
      work_center_id BIGINT UNSIGNED NULL,
      assigned_user_id BIGINT UNSIGNED NULL,
      slot_date DATE NOT NULL,
      start_time TIME NULL,
      end_time TIME NULL,
      allocated_minutes INT UNSIGNED NOT NULL DEFAULT 0,
      slot_type VARCHAR(20) NOT NULL DEFAULT 'PLANNED',
      status VARCHAR(20) NOT NULL DEFAULT 'SCHEDULED',
      created_by BIGINT UNSIGNED NULL,
      location_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (slot_id),
      KEY job_idx (job_id),
      KEY date_idx (slot_date),
      KEY wc_date_idx (work_center_id, slot_date),
      KEY user_date_idx (assigned_user_id, slot_date),
      KEY status_idx (status)
    ) $charset_collate;";

    $sql_eod_reports = "CREATE TABLE $eod_reports (
      eod_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      user_id BIGINT UNSIGNED NOT NULL,
      report_date DATE NOT NULL,
      general_notes TEXT NULL,
      submitted_at DATETIME NOT NULL,
      reviewed_by BIGINT UNSIGNED NULL,
      reviewed_at DATETIME NULL,
      review_notes TEXT NULL,
      location_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL,
      PRIMARY KEY (eod_id),
      UNIQUE KEY user_date_idx (user_id, report_date),
      KEY date_idx (report_date),
      KEY reviewed_idx (reviewed_by)
    ) $charset_collate;";

    $sql_eod_report_lines = "CREATE TABLE $eod_report_lines (
      line_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      eod_id BIGINT UNSIGNED NOT NULL,
      job_id BIGINT UNSIGNED NOT NULL,
      status_note TEXT NULL,
      work_completed VARCHAR(500) NULL,
      work_remaining VARCHAR(500) NULL,
      issues TEXT NULL,
      percent_complete TINYINT UNSIGNED NULL,
      PRIMARY KEY (line_id),
      KEY eod_idx (eod_id),
      KEY job_idx (job_id)
    ) $charset_collate;";

    $sql_qc_records = "CREATE TABLE $qc_records (
      qc_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      job_id BIGINT UNSIGNED NOT NULL,
      checkpoint VARCHAR(50) NOT NULL,
      result VARCHAR(20) NOT NULL,
      checked_by BIGINT UNSIGNED NOT NULL,
      notes TEXT NULL,
      location_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL,
      PRIMARY KEY (qc_id),
      KEY job_idx (job_id),
      KEY checkpoint_idx (checkpoint)
    ) $charset_collate;";

    // ── Pricing Core tables ───────────────────────────

    $sql_products = "CREATE TABLE $products (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      sku VARCHAR(64) NOT NULL,
      product_name VARCHAR(255) NOT NULL,
      product_type VARCHAR(30) NOT NULL DEFAULT 'PART',
      dealer_price_published DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
      retail_price_published DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY sku_idx (sku)
    ) $charset_collate;";

    $sql_boms = "CREATE TABLE $boms (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      bom_no VARCHAR(64) NOT NULL,
      name VARCHAR(255) NOT NULL,
      fitment VARCHAR(100) NULL,
      market VARCHAR(50) NULL,
      category VARCHAR(50) NULL,
      install_hours DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      shop_supply_units DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      status VARCHAR(20) NOT NULL DEFAULT 'active',
      revision VARCHAR(20) NULL,
      notes TEXT NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY bom_no_idx (bom_no)
    ) $charset_collate;";

    $sql_bom_lines = "CREATE TABLE $bom_lines (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      bom_id BIGINT UNSIGNED NOT NULL,
      product_id BIGINT UNSIGNED NULL,
      sku VARCHAR(64) NULL,
      qty DECIMAL(10,2) NOT NULL DEFAULT 1.00,
      line_type VARCHAR(20) NOT NULL DEFAULT 'PART',
      line_notes TEXT NULL,
      sort_order INT UNSIGNED NOT NULL DEFAULT 0,
      PRIMARY KEY (id),
      KEY bom_idx (bom_id)
    ) $charset_collate;";

    $sql_dealers_ext = "CREATE TABLE $dealers_ext (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      dealer_code VARCHAR(32) NOT NULL,
      dealer_name VARCHAR(255) NOT NULL,
      labor_rate_retail_published DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
      labor_rate_wholesale_published DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
      shop_supply_base_retail_published DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
      shop_supply_base_wholesale_published DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      effective_date DATE NULL,
      PRIMARY KEY (id),
      UNIQUE KEY code_idx (dealer_code)
    ) $charset_collate;";

    $sql_quotes = "CREATE TABLE $quotes (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      quote_no VARCHAR(64) NOT NULL,
      dealer_id BIGINT UNSIGNED NOT NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
      subtotal_retail DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
      subtotal_wholesale DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
      labor_hours DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      shop_supply_units DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      notes TEXT NULL,
      created_by BIGINT UNSIGNED NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY quote_no_idx (quote_no)
    ) $charset_collate;";

    $sql_quote_lines = "CREATE TABLE $quote_lines (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      quote_id BIGINT UNSIGNED NOT NULL,
      product_id BIGINT UNSIGNED NULL,
      sku_snapshot VARCHAR(64) NULL,
      name_snapshot VARCHAR(255) NULL,
      qty DECIMAL(10,2) NOT NULL DEFAULT 1.00,
      unit_retail DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
      unit_wholesale DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
      line_retail DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
      line_wholesale DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
      line_type VARCHAR(20) NOT NULL DEFAULT 'PART',
      created_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY quote_idx (quote_id)
    ) $charset_collate;";

    $sql_pur_vendors = "CREATE TABLE $pur_vendors (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      bc_vendor_id VARCHAR(64) NULL,
      name VARCHAR(255) NOT NULL,
      contact_email VARCHAR(255) NULL,
      contact_phone VARCHAR(50) NULL,
      lead_time_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
      payment_terms VARCHAR(50) NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'active',
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY status_idx (status),
      KEY bc_idx (bc_vendor_id)
    ) $charset_collate;";

    $sql_pur_items = "CREATE TABLE $pur_items (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      bc_item_id VARCHAR(64) NULL,
      part_number VARCHAR(64) NOT NULL,
      description VARCHAR(255) NOT NULL,
      on_hand INT NOT NULL DEFAULT 0,
      reorder_point INT NOT NULL DEFAULT 0,
      unit_cost DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
      demand_level VARCHAR(10) NOT NULL DEFAULT 'low',
      forecasted_need INT NOT NULL DEFAULT 0,
      suggested_order INT NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY part_number_idx (part_number),
      KEY bc_idx (bc_item_id)
    ) $charset_collate;";

    $sql_pur_requests = "CREATE TABLE $pur_requests (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      request_number VARCHAR(20) NULL,
      item_id BIGINT UNSIGNED NULL,
      item_description VARCHAR(255) NOT NULL,
      vendor_id BIGINT UNSIGNED NULL,
      qty INT NOT NULL DEFAULT 1,
      unit_cost DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
      status VARCHAR(20) NOT NULL DEFAULT 'draft',
      requested_by BIGINT UNSIGNED NULL,
      notes TEXT NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY request_number_idx (request_number),
      KEY status_idx (status),
      KEY vendor_idx (vendor_id),
      KEY item_idx (item_id),
      KEY requestor_idx (requested_by)
    ) $charset_collate;";

    $sql_pur_orders = "CREATE TABLE $pur_orders (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      bc_po_id VARCHAR(64) NULL,
      po_number VARCHAR(20) NULL,
      vendor_id BIGINT UNSIGNED NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'submitted',
      ordered_at DATETIME NULL,
      expected_date DATE NULL,
      notes TEXT NULL,
      created_by BIGINT UNSIGNED NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY po_number_idx (po_number),
      KEY status_idx (status),
      KEY vendor_idx (vendor_id),
      KEY bc_idx (bc_po_id)
    ) $charset_collate;";

    $sql_pur_order_lines = "CREATE TABLE $pur_order_lines (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      po_id BIGINT UNSIGNED NOT NULL,
      item_id BIGINT UNSIGNED NULL,
      item_description VARCHAR(255) NOT NULL,
      qty_ordered INT NOT NULL DEFAULT 0,
      qty_received INT NOT NULL DEFAULT 0,
      unit_cost DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY po_idx (po_id),
      KEY item_idx (item_id)
    ) $charset_collate;";

    // ── Run all dbDelta ─────────────────────────────────

    dbDelta($sql_jobs);
    dbDelta($sql_segments);
    dbDelta($sql_audit);
    dbDelta($sql_settings);
    dbDelta($sql_work_centers);
    dbDelta($sql_schedule_events);
    dbDelta($sql_capacity_snapshots);
    dbDelta($sql_job_reviews);
    dbDelta($sql_job_assignments);
    dbDelta($sql_blockers);
    dbDelta($sql_schedule_slots);
    dbDelta($sql_eod_reports);
    dbDelta($sql_eod_report_lines);
    dbDelta($sql_qc_records);

    dbDelta($sql_products);
    dbDelta($sql_boms);
    dbDelta($sql_bom_lines);
    dbDelta($sql_dealers_ext);
    dbDelta($sql_quotes);
    dbDelta($sql_quote_lines);

    dbDelta($sql_pur_vendors);
    dbDelta($sql_pur_items);
    dbDelta($sql_pur_requests);
    dbDelta($sql_pur_orders);
    dbDelta($sql_pur_order_lines);

    // ── Data migrations ─────────────────────────────────

    // Backfill primary_owner_id from assigned_user_id for existing jobs.
    $wpdb->query("UPDATE $jobs SET primary_owner_id = assigned_user_id WHERE primary_owner_id IS NULL AND assigned_user_id IS NOT NULL");

    // Migrate legacy status values to the canonical set.
    // Earliest legacy values go first so later passes pick them up.
    $wpdb->query("UPDATE $jobs SET status = 'INTAKE'           WHERE status IN ('UNSCHEDULED','PENDING_INTAKE','NEEDS_SO','RETURNED_TO_CS')");
    $wpdb->query("UPDATE $jobs SET status = 'READY_FOR_BUILD'  WHERE status IN ('READY_FOR_SCHEDULING','APPROVED_FOR_SCHEDULING','READY_TO_SCHEDULE','READY_FOR_SUPERVISOR_REVIEW')");
    $wpdb->query("UPDATE $jobs SET status = 'QUEUED'           WHERE status = 'SCHEDULED'");
    $wpdb->query("UPDATE $jobs SET status = 'READY_FOR_PICKUP' WHERE status IN ('COMPLETE_AWAITING_PICKUP','COMPLETED_AWAITING_PICKUP')");
    $wpdb->query("UPDATE $jobs SET status = 'COMPLETE'         WHERE status = 'COMPLETED'");
    // Migrate scheduling_status column to match canonical job status values.
    $wpdb->query("UPDATE $jobs SET scheduling_status = 'READY_FOR_BUILD' WHERE scheduling_status = 'APPROVED_FOR_SCHEDULING'");

    $exists = $wpdb->get_var("SELECT COUNT(*) FROM $settings WHERE id=1");
    if (!$exists) {
      $wpdb->insert($settings, [
        'id' => 1,
        'shift_start' => '07:00:00',
        'shift_end' => '15:30:00',
        'lunch_minutes' => 30,
        'break_minutes' => 20,
        'capacity_threshold_pct' => 70,
        'timezone' => 'America/Los_Angeles',
        'updated_at' => gmdate('Y-m-d H:i:s'),
      ]);
    }

    update_option('slate_ops_version', SLATE_OPS_VERSION);

    if ($flush_rewrites) {
      Slate_Ops_Roles::register_roles_caps();
      Slate_Ops_Routes::register_routes();
      flush_rewrite_rules();
    }
  }

  public static function deactivate() {
    flush_rewrite_rules();
  }
}
