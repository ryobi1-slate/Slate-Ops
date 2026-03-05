<?php
if (!defined('ABSPATH')) exit;

/**
 * Returns the running plugin version.
 *
 * Reads VERSION.txt from the plugin root when present so a deploy script can
 * bump the file without editing PHP headers. Falls back to the constant that
 * slate-ops.php defines from the plugin header.
 */
if (!function_exists('slate_ops_get_version')) {
  function slate_ops_get_version() {
    // Prefer the code-defined version so the UI always matches the plugin header.
    if (defined('SLATE_OPS_VERSION') && SLATE_OPS_VERSION) return SLATE_OPS_VERSION;

    // Fallback: legacy deploy flow writes VERSION.txt.
    $file = defined('SLATE_OPS_PATH') ? SLATE_OPS_PATH . 'VERSION.txt' : '';
    if ($file && file_exists($file)) {
      $v = trim((string) file_get_contents($file));
      if ($v !== '') return $v;
    }
    return '0.0.0';
  }
}

/**
 * Register the Sovereign ERP API
 * This enables the staging path for the Revised Monitor
 */
add_action('rest_api_init', function () {
    register_rest_route('slate/v2', '/shop-floor', [
        'methods'             => 'GET',
        'callback'            => 'get_slate_internal_jobs',
        'permission_callback' => '__return_true', // Public display logic
    ]);
});

if (!function_exists('get_slate_internal_jobs')) {
  function get_slate_internal_jobs() {
      global $wpdb;
      $table = $wpdb->prefix . 'slate_ops_jobs';

      // Active jobs: not archived, and either not yet complete or completed within the last 7 days.
      $rows = $wpdb->get_results( $wpdb->prepare(
          "SELECT j.job_id, j.so_number, j.customer_name, j.dealer_name,
                  j.vin, j.status, j.delay_reason, j.sales_person,
                  j.work_center, j.estimated_minutes,
                  j.scheduled_start, j.scheduled_finish, j.notes,
                  u.display_name AS assigned_user_name
           FROM {$table} j
           LEFT JOIN {$wpdb->users} u ON u.ID = j.assigned_user_id
           WHERE j.archived_at IS NULL
             AND ( j.status != %s OR j.status_updated_at >= %s )
           ORDER BY j.priority ASC, j.scheduled_start ASC",
          'COMPLETE',
          gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
      ), ARRAY_A );

      if ( ! $rows ) {
          return rest_ensure_response( [] );
      }

      $out = [];
      foreach ( $rows as $job ) {
          $out[] = [
              'job_id'             => (int)    $job['job_id'],
              'so_number'          => (string) ( $job['so_number']     ?? '' ),
              'customer_name'      => (string) ( $job['customer_name'] ?? '' ),
              'dealer_name'        => (string) ( $job['dealer_name']   ?? '' ),
              'vin'                => (string) ( $job['vin']           ?? '' ),
              'sales_person'       => (string) ( $job['sales_person']  ?? '' ),
              'status'             => (string) ( $job['status']        ?? '' ),
              'delay_reason'       => (string) ( $job['delay_reason']  ?? '' ),
              'estimated_minutes'  => (int)    ( $job['estimated_minutes'] ?? 0 ),
              'scheduled_start'    => $job['scheduled_start']  ? substr( $job['scheduled_start'],  0, 10 ) : null,
              'scheduled_finish'   => $job['scheduled_finish'] ? substr( $job['scheduled_finish'], 0, 10 ) : null,
              'notes'              => (string) ( $job['notes']              ?? '' ),
              'assigned_user_name' => (string) ( $job['assigned_user_name'] ?? '' ),
          ];
      }

      return rest_ensure_response( $out );
  }
}
