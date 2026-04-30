<?php
if (!defined('ABSPATH')) exit;

class Slate_Ops_Utils {

  // ── Role-level capabilities ──────────────────────────────────────────────
  const CAP_ACCESS     = 'slate_ops_access';      // base gate — every ops role has this
  const CAP_ADMIN      = 'slate_ops_admin';
  const CAP_SUPERVISOR = 'slate_ops_supervisor';
  const CAP_CS         = 'slate_ops_cs';
  const CAP_TECH       = 'slate_ops_tech';
  const CAP_VIEWER     = 'slate_ops_viewer';

  // Legacy cap name kept for backward-compat during migration
  const CAP_CS_LEGACY  = 'slate_ops_customer_service';

  // ── Fine-grained operation capabilities ─────────────────────────────────
  const CAP_MANAGE_SETTINGS = 'slate_ops_manage_settings';
  const CAP_MANAGE_USERS    = 'slate_ops_manage_users';
  const CAP_CREATE_JOBS     = 'slate_ops_create_jobs';
  const CAP_EDIT_JOBS       = 'slate_ops_edit_jobs';
  const CAP_DELETE_JOBS     = 'slate_ops_delete_jobs';
  const CAP_SCHEDULE_JOBS   = 'slate_ops_schedule_jobs';
  const CAP_ASSIGN_JOBS     = 'slate_ops_assign_jobs';
  const CAP_UPDATE_STATUS   = 'slate_ops_update_status';
  const CAP_TIME_TRACKING   = 'slate_ops_time_tracking';
  const CAP_SUBMIT_QC       = 'slate_ops_submit_qc';
  const CAP_REVIEW_QC       = 'slate_ops_review_qc';
  const CAP_VIEW_EXECUTIVE  = 'slate_ops_view_executive';
  const CAP_VIEW_MONITOR    = 'slate_ops_view_monitor';

  // ── Canonical capability name list ──────────────────────────────────────

  /**
   * Returns every Slate Ops capability name as a plain array of strings.
   * Used by the role installer (to avoid duplication) and by the debug
   * endpoint (to avoid hardcoded lists in business logic).
   */
  public static function all_cap_names() {
    return [
      self::CAP_ACCESS,
      self::CAP_ADMIN,
      self::CAP_SUPERVISOR,
      self::CAP_CS,
      self::CAP_TECH,
      self::CAP_VIEWER,
      self::CAP_MANAGE_SETTINGS,
      self::CAP_MANAGE_USERS,
      self::CAP_CREATE_JOBS,
      self::CAP_EDIT_JOBS,
      self::CAP_DELETE_JOBS,
      self::CAP_SCHEDULE_JOBS,
      self::CAP_ASSIGN_JOBS,
      self::CAP_UPDATE_STATUS,
      self::CAP_TIME_TRACKING,
      self::CAP_SUBMIT_QC,
      self::CAP_REVIEW_QC,
      self::CAP_VIEW_EXECUTIVE,
      self::CAP_VIEW_MONITOR,
    ];
  }

  // ── Centralized permission helpers ───────────────────────────────────────

  /** Any authenticated ops user (includes viewer). */
  public static function can_access() {
    return is_user_logged_in() && (
      current_user_can(self::CAP_ACCESS) ||
      // Fallback for users whose role predates the access cap
      current_user_can(self::CAP_TECH) ||
      current_user_can(self::CAP_SUPERVISOR) ||
      current_user_can(self::CAP_CS) ||
      current_user_can(self::CAP_CS_LEGACY) ||
      current_user_can(self::CAP_ADMIN)
    );
  }

  /** Alias kept for backward compatibility with routes that call this. */
  public static function require_ops_access() {
    return self::can_access();
  }

  public static function can_manage_settings() {
    return is_user_logged_in() && current_user_can(self::CAP_MANAGE_SETTINGS);
  }

  public static function can_manage_users() {
    return is_user_logged_in() && current_user_can(self::CAP_MANAGE_USERS);
  }

  public static function can_create_jobs() {
    return is_user_logged_in() && current_user_can(self::CAP_CREATE_JOBS);
  }

  public static function can_edit_jobs() {
    return is_user_logged_in() && current_user_can(self::CAP_EDIT_JOBS);
  }

  public static function can_delete_jobs() {
    return is_user_logged_in() && current_user_can(self::CAP_DELETE_JOBS);
  }

  public static function can_schedule_jobs() {
    return is_user_logged_in() && current_user_can(self::CAP_SCHEDULE_JOBS);
  }

  public static function can_assign_jobs() {
    return is_user_logged_in() && current_user_can(self::CAP_ASSIGN_JOBS);
  }

  public static function can_update_status() {
    return is_user_logged_in() && current_user_can(self::CAP_UPDATE_STATUS);
  }

  public static function can_time_tracking() {
    return is_user_logged_in() && current_user_can(self::CAP_TIME_TRACKING);
  }

  public static function can_submit_qc() {
    return is_user_logged_in() && current_user_can(self::CAP_SUBMIT_QC);
  }

  public static function can_review_qc() {
    return is_user_logged_in() && current_user_can(self::CAP_REVIEW_QC);
  }

  public static function can_view_executive() {
    return is_user_logged_in() && current_user_can(self::CAP_VIEW_EXECUTIVE);
  }

  public static function can_view_monitor() {
    return is_user_logged_in() && current_user_can(self::CAP_VIEW_MONITOR);
  }

  public static function is_admin() {
    return is_user_logged_in() && current_user_can(self::CAP_ADMIN);
  }

  public static function is_supervisor() {
    return is_user_logged_in() && current_user_can(self::CAP_SUPERVISOR);
  }

  public static function is_cs() {
    return is_user_logged_in() && (
      current_user_can(self::CAP_CS) || current_user_can(self::CAP_CS_LEGACY)
    );
  }

  public static function is_tech() {
    return is_user_logged_in() && current_user_can(self::CAP_TECH);
  }

  public static function can_supervisor_or_admin() {
    return is_user_logged_in() && (
      current_user_can(self::CAP_SUPERVISOR) ||
      current_user_can(self::CAP_ADMIN)
    );
  }

  public static function can_cs_or_above() {
    return is_user_logged_in() && (
      current_user_can(self::CAP_CS) ||
      current_user_can(self::CAP_CS_LEGACY) ||
      current_user_can(self::CAP_SUPERVISOR) ||
      current_user_can(self::CAP_ADMIN)
    );
  }

  // ── User capability summary ──────────────────────────────────────────────

  /**
   * Returns an array of the current user's ops capabilities for
   * frontend localization. Keys are kept stable for backward compat.
   */
  public static function current_user_caps_summary() {
    return [
      'access'           => current_user_can(self::CAP_ACCESS),
      'admin'            => current_user_can(self::CAP_ADMIN),
      'supervisor'       => current_user_can(self::CAP_SUPERVISOR),
      'cs'               => current_user_can(self::CAP_CS) || current_user_can(self::CAP_CS_LEGACY),
      'tech'             => current_user_can(self::CAP_TECH),
      'viewer'           => current_user_can(self::CAP_VIEWER),
      'manage_settings'  => current_user_can(self::CAP_MANAGE_SETTINGS),
      'manage_users'     => current_user_can(self::CAP_MANAGE_USERS),
      'create_jobs'      => current_user_can(self::CAP_CREATE_JOBS),
      'edit_jobs'        => current_user_can(self::CAP_EDIT_JOBS),
      'delete_jobs'      => current_user_can(self::CAP_DELETE_JOBS),
      'schedule_jobs'    => current_user_can(self::CAP_SCHEDULE_JOBS),
      'assign_jobs'      => current_user_can(self::CAP_ASSIGN_JOBS),
      'update_status'    => current_user_can(self::CAP_UPDATE_STATUS),
      'time_tracking'    => current_user_can(self::CAP_TIME_TRACKING),
      'submit_qc'        => current_user_can(self::CAP_SUBMIT_QC),
      'review_qc'        => current_user_can(self::CAP_REVIEW_QC),
      'view_executive'   => current_user_can(self::CAP_VIEW_EXECUTIVE),
      'view_monitor'     => current_user_can(self::CAP_VIEW_MONITOR),
    ];
  }

  /**
   * Returns the list of app pages this user is allowed to visit.
   * Used by /me endpoint and frontend guard.
   */
  public static function user_allowed_pages() {
    $pages = [];

    if (current_user_can(self::CAP_VIEW_EXECUTIVE)) {
      $pages[] = 'executive';
    }
    if (current_user_can(self::CAP_CS) || current_user_can(self::CAP_CS_LEGACY)) {
      $pages[] = 'cs';
    }
    if (current_user_can(self::CAP_TECH)) {
      $pages[] = 'tech';
    }
    if (current_user_can(self::CAP_SCHEDULE_JOBS)) {
      $pages[] = 'schedule';
    }
    if (current_user_can(self::CAP_ADMIN)) {
      $pages[] = 'admin';
    }
    if (current_user_can(self::CAP_MANAGE_SETTINGS)) {
      $pages[] = 'settings';
    }
    if (current_user_can(self::CAP_VIEW_MONITOR)) {
      $pages[] = 'monitor';
    }

    return $pages;
  }

  // ── Validators ──────────────────────────────────────────────────────────

  public static function so_is_valid($so) {
    $so = strtoupper(trim((string)$so));
    return (bool) preg_match('/^S-ORD\d{6}$/', $so);
  }

  public static function vin_last8_is_valid($vin_last8) {
    return (bool) preg_match('/^[A-HJ-NPR-Z0-9]{7,8}$/', strtoupper((string) $vin_last8));
  }

  public static function cs_job_types() {
    return [
      'UPFIT',
      'COMMERCIAL_UPFIT',
      'COMMERCIAL_BUILD',
      'RV_BUILD',
      'RV_UPFIT',
      'PARTS_ONLY',
      'SERVICE',
      'WARRANTY',
    ];
  }

  public static function cs_created_from_values() {
    return ['portal', 'manual'];
  }

  public static function cs_parts_statuses() {
    return ['NOT_READY', 'PARTIAL', 'READY', 'HOLD'];
  }

  public static function cs_block_reasons() {
    return ['PARTS', 'ENGINEERING', 'CUSTOMER', 'LABOR', 'OTHER'];
  }

  public static function cs_hold_reasons() {
    return ['CUSTOMER_CHANGE', 'BILLING', 'ESCALATION', 'SCOPE_REVIEW', 'VENDOR_DISPUTE', 'OTHER'];
  }

  public static function cs_cancel_reasons() {
    return ['CUSTOMER_CANCELED', 'DEAL_FELL_THROUGH', 'DUPLICATE', 'SCOPE_ABSORBED', 'NO_SHOW', 'PRICING', 'OTHER'];
  }

  public static function dealer_list() {
    $default_dealers = [
      'Northwest Fleet',
      'Pacific Utility Vehicles',
      'Summit Commercial',
      'Canyon RV Center',
      'Metro Work Trucks',
    ];

    $saved = get_option('slate_ops_dealers', null);
    $dealers = is_array($saved) ? $saved : $default_dealers;

    $dealers = apply_filters('slate_ops_dealer_list', $dealers);

    if (!is_array($dealers)) {
      return [];
    }

    return array_values(array_filter(array_map('sanitize_text_field', $dealers)));
  }

  public static function sales_person_list() {
    $saved = get_option('slate_ops_sales_people', []);
    if (!is_array($saved)) {
      $saved = [];
    }

    $people = apply_filters('slate_ops_sales_person_list', $saved);
    if (!is_array($people)) {
      return [];
    }

    return array_values(array_filter(array_map('sanitize_text_field', $people)));
  }

  public static function dealer_status_from_internal($status) {
    $canonical = Slate_Ops_Statuses::normalize((string)$status);
    $waiting = [
      Slate_Ops_Statuses::INTAKE,
      Slate_Ops_Statuses::READY_FOR_BUILD,
      Slate_Ops_Statuses::QUEUED,
      Slate_Ops_Statuses::ON_HOLD,
      Slate_Ops_Statuses::DELAYED,
      Slate_Ops_Statuses::READY_FOR_SUPERVISOR_REVIEW,
      Slate_Ops_Statuses::RETURNED_TO_CS,
    ];
    if (in_array($canonical, $waiting, true)) return 'waiting';
    if (in_array($canonical, [Slate_Ops_Statuses::IN_PROGRESS, Slate_Ops_Statuses::PENDING_QC], true)) return 'in_process';
    if (in_array($canonical, [Slate_Ops_Statuses::COMPLETE, Slate_Ops_Statuses::READY_FOR_PICKUP], true)) return 'complete';
    return 'waiting';
  }

  public static function now_gmt() {
    return gmdate('Y-m-d H:i:s');
  }

  public static function sanitize_reason($reason) {
    $allowed = [
      'helping_assigned_tech',
      'parts_kit_support',
      'qc_support',
      'diagnosis',
      'rework',
      'other',
    ];
    $reason = sanitize_key($reason);
    if (!in_array($reason, $allowed, true)) $reason = 'other';
    return $reason;
  }

  public static function pad_sj($job_id) {
    return 'SJ-' . str_pad((string)intval($job_id), 6, '0', STR_PAD_LEFT);
  }

  public static function user_display($user_id) {
    $u = get_user_by('id', (int)$user_id);
    if (!$u) return 'Unknown';
    return $u->display_name ?: $u->user_login;
  }
}
