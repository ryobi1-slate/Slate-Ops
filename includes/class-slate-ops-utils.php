<?php
if (!defined('ABSPATH')) exit;

class Slate_Ops_Utils {

  const CAP_TECH = 'slate_ops_tech';
  const CAP_SUPERVISOR = 'slate_ops_supervisor';
  const CAP_CS = 'slate_ops_customer_service';
  const CAP_ADMIN = 'slate_ops_admin';

  public static function current_user_caps_summary() {
    return [
      'tech' => current_user_can(self::CAP_TECH),
      'supervisor' => current_user_can(self::CAP_SUPERVISOR),
      'cs' => current_user_can(self::CAP_CS),
      'admin' => current_user_can(self::CAP_ADMIN),
    ];
  }

  public static function require_ops_access() {
    if (is_user_logged_in() && (current_user_can(self::CAP_TECH) || current_user_can(self::CAP_SUPERVISOR) || current_user_can(self::CAP_CS) || current_user_can(self::CAP_ADMIN))) {
      return true;
    }
    return false;
  }

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
