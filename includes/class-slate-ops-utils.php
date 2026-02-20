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

  public static function dealer_status_from_internal($status) {
    $status = strtoupper((string)$status);
    $waiting = ['UNSCHEDULED','READY_FOR_SCHEDULING','SCHEDULED','DELAYED','BLOCKED'];
    if (in_array($status, $waiting, true)) return 'waiting';
    if (in_array($status, ['IN_PROGRESS','PENDING_QC'], true)) return 'in_process';
    if ($status === 'COMPLETE') return 'complete';
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
