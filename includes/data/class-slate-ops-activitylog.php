<?php
/**
 * Slate_Ops_Activity_Log — append-only activity log helpers.
 *
 * All writes go to wp_slate_ops_audit_log (already created by
 * class-slate-ops-install.php).  This class provides a clean API so
 * REST handlers, data helpers, and hooks don't build raw inserts.
 *
 * Schema reference (audit_log):
 *   audit_id     BIGINT PK AUTO_INCREMENT
 *   entity_type  VARCHAR(30)  — 'job', 'segment', 'setting'
 *   entity_id    BIGINT       — e.g. job_id
 *   action       VARCHAR(30)  — e.g. 'status_change', 'schedule_update', 'assign'
 *   field_name   VARCHAR(80)  — specific field changed, or null for multi-field actions
 *   old_value    LONGTEXT
 *   new_value    LONGTEXT
 *   note         TEXT
 *   user_id      BIGINT
 *   ip_address   VARCHAR(64)
 *   user_agent   VARCHAR(255)
 *   created_at   DATETIME
 */
if (!defined('ABSPATH')) exit;

class Slate_Ops_Activity_Log {

  public static function table() {
    global $wpdb;
    return $wpdb->prefix . 'slate_ops_audit_log';
  }

  // ── Write ────────────────────────────────────────────────────────

  /**
   * Append one activity entry.
   *
   * @param string     $entity_type  'job' | 'segment' | 'setting' | …
   * @param int        $entity_id
   * @param string     $action       e.g. 'status_change', 'schedule_update', 'assign', 'note_added'
   * @param mixed      $new_value    Scalar or array (will be JSON-encoded).
   * @param mixed|null $old_value    Previous value, optional.
   * @param string     $field_name   Specific field, optional.
   * @param string     $note         Human-readable note, optional.
   * @return int|false  Inserted audit_id, or false on failure.
   */
  public static function append(
    $entity_type,
    $entity_id,
    $action,
    $new_value   = null,
    $old_value   = null,
    $field_name  = null,
    $note        = ''
  ) {
    global $wpdb;

    $encode = function($v) {
      if ($v === null) return null;
      return is_scalar($v) ? (string)$v : wp_json_encode($v);
    };

    $row = [
      'entity_type' => sanitize_text_field($entity_type),
      'entity_id'   => (int)$entity_id,
      'action'      => sanitize_text_field($action),
      'field_name'  => $field_name ? sanitize_text_field($field_name) : null,
      'old_value'   => $encode($old_value),
      'new_value'   => $encode($new_value),
      'note'        => $note ? sanitize_textarea_field($note) : null,
      'user_id'     => get_current_user_id() ?: null,
      'ip_address'  => self::client_ip(),
      'user_agent'  => isset($_SERVER['HTTP_USER_AGENT'])
                         ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 255)
                         : null,
      'created_at'  => Slate_Ops_Utils::now_gmt(),
    ];

    $result = $wpdb->insert(self::table(), $row);
    return $result ? (int)$wpdb->insert_id : false;
  }

  /**
   * Convenience: record a schedule change for a job.
   *
   * @param int   $job_id
   * @param array $changes  Assoc array of fields changed (new values).
   */
  public static function log_schedule_change( $job_id, array $changes ) {
    return self::append('job', $job_id, 'schedule_update', $changes, null, null, 'Schedule updated');
  }

  /**
   * Convenience: record a status change.
   *
   * @param int    $job_id
   * @param string $old_status
   * @param string $new_status
   * @param string $note
   */
  public static function log_status_change( $job_id, $old_status, $new_status, $note = '' ) {
    return self::append('job', $job_id, 'status_change', $new_status, $old_status, 'status', $note);
  }

  /**
   * Convenience: record an assignment change.
   *
   * @param int      $job_id
   * @param int|null $old_user_id
   * @param int|null $new_user_id
   */
  public static function log_assign( $job_id, $old_user_id, $new_user_id ) {
    $old_name = $old_user_id ? Slate_Ops_Utils::user_display($old_user_id) : 'Unassigned';
    $new_name = $new_user_id ? Slate_Ops_Utils::user_display($new_user_id) : 'Unassigned';
    return self::append('job', $job_id, 'assign', $new_name, $old_name, 'assigned_user_id');
  }

  /**
   * Convenience: record a time segment event (start/stop/correction).
   *
   * @param int    $job_id
   * @param int    $segment_id
   * @param string $event       'start' | 'stop' | 'correction' | 'void'
   * @param array  $details
   */
  public static function log_time_event( $job_id, $segment_id, $event, array $details = [] ) {
    return self::append(
      'job',
      $job_id,
      'time_' . $event,
      array_merge(['segment_id' => $segment_id], $details),
      null,
      'time_segment'
    );
  }

  // ── Read ─────────────────────────────────────────────────────────

  /**
   * Fetch activity for a job, newest first.
   *
   * @param int $job_id
   * @param int $limit  Default 200.
   * @return array  Rows as assoc arrays with user_name joined.
   */
  public static function for_job( $job_id, $limit = 200 ) {
    global $wpdb;
    $tbl = self::table();
    return $wpdb->get_results(
      $wpdb->prepare(
        "SELECT a.*, COALESCE(u.display_name,'System') AS user_name
         FROM $tbl a
         LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
         WHERE a.entity_type = 'job' AND a.entity_id = %d
         ORDER BY a.created_at DESC
         LIMIT %d",
        (int)$job_id,
        (int)$limit
      ),
      ARRAY_A
    ) ?: [];
  }

  // ── Helpers ──────────────────────────────────────────────────────

  private static function client_ip() {
    foreach (['HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
      if (!empty($_SERVER[$key])) {
        $ip = trim(explode(',', wp_unslash($_SERVER[$key]))[0]);
        return substr(sanitize_text_field($ip), 0, 64);
      }
    }
    return null;
  }
}
