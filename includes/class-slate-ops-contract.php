<?php
if (!defined('ABSPATH')) exit;

class Slate_Ops_Contract {

  public static function schedulable_job_fields() {
    $fields = [
      'work_center' => 'text',
      'estimated_minutes' => 'int',
      'scheduled_start' => 'datetime',
      'scheduled_finish' => 'datetime',
      'assigned_user_id' => 'int',
      'parts_status' => 'text',
      'requested_date' => 'date',
    ];

    return apply_filters('slate_ops_schedulable_job_fields', $fields);
  }

  public static function fire_job_created($job_id) {
    do_action('slate_ops_job_created', (int)$job_id);
  }

  public static function fire_job_updated($job_id, $changed_fields = []) {
    do_action('slate_ops_job_updated', (int)$job_id, (array)$changed_fields);
  }

  public static function fire_schedule_updated($job_id, $changed_fields = []) {
    do_action('slate_ops_schedule_updated', (int)$job_id, (array)$changed_fields);
  }
}
