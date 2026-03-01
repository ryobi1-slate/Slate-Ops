<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Fetches job data from the Slate Ops shop-floor endpoint and normalises it
 * to match the Job interface the React bundle expects:
 *
 *   id, so_number, vin, dealer, customer, sales_person,
 *   assigned_tech, start_date, due_date, time_estimate (string),
 *   notes, status, status_detail (optional)
 */
class Slate_Ops_Monitor_Service {

    public function get_jobs() {
        global $wpdb;
        $table = $wpdb->prefix . 'slate_ops_jobs';

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT j.job_id, j.so_number, j.customer_name, j.dealer_name,
                    j.vin, j.status, j.delay_reason, j.sales_person,
                    j.estimated_minutes, j.scheduled_start, j.scheduled_finish,
                    j.notes, u.display_name AS assigned_user_name
             FROM {$table} j
             LEFT JOIN {$wpdb->users} u ON u.ID = j.assigned_user_id
             WHERE j.archived_at IS NULL
               AND ( j.status != %s OR j.status_updated_at >= %s )
             ORDER BY j.priority ASC, j.scheduled_start ASC",
            'COMPLETE',
            gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
        ), ARRAY_A );

        if ( ! $rows ) {
            return array();
        }

        return array_values( array_map( array( $this, 'normalize' ), $rows ) );
    }

    // -------------------------------------------------------------------------

    private function s( $v ) {
        return trim( (string) $v );
    }

    private function format_tech( $display_name ) {
        $name  = $this->s( $display_name );
        if ( $name === '' ) return 'Unassigned';
        $parts = array_values( array_filter( preg_split( '/\s+/', $name ) ) );
        if ( count( $parts ) >= 2 ) {
            return $parts[0] . ' ' . strtoupper( $parts[ count( $parts ) - 1 ][0] ) . '.';
        }
        return $parts[0] ?? $name;
    }

    /**
     * Returns the primary display status string.
     * The frontend's normalizeStatus() will uppercase and convert " - " → " – ",
     * so plain internal codes like 'IN_PROGRESS' are also accepted by the app,
     * but we return the fully-formatted string for clarity.
     *
     * STATUS_MAPPING columns (from types.ts):
     *   DELAYED:     DELAYED | DELAY – PARTS | DELAY – UPDATE REQUIRED | DELAY – ON HOLD
     *   SCHEDULED:   SCHEDULED | SCHEDULED – NOT ARRIVED | SCHEDULED – READY TO START
     *   IN_PROGRESS: IN PROGRESS | IN_PROGRESS | QUALITY CONTROL
     *   COMPLETED:   COMPLETED | COMPLETE – AWAITING PICKUP
     *
     * Note: UNSCHEDULED has no column — those jobs won't appear on the board.
     */
    private function map_status( $status, $delay_reason ) {
        switch ( strtoupper( $this->s( $status ) ) ) {
            case 'UNSCHEDULED':          return 'UNSCHEDULED';
            case 'READY_FOR_SCHEDULING': return 'SCHEDULED – READY TO START';
            case 'SCHEDULED':            return 'SCHEDULED – NOT ARRIVED';
            case 'DELAYED':
                $r = strtolower( $this->s( $delay_reason ) );
                if ( strpos( $r, 'parts' ) !== false ) return 'DELAY – PARTS';
                if ( strpos( $r, 'hold'  ) !== false ) return 'DELAY – ON HOLD';
                return 'DELAY – UPDATE REQUIRED';
            case 'BLOCKED':     return 'DELAY – ON HOLD';
            case 'IN_PROGRESS': return 'IN PROGRESS';
            case 'PENDING_QC':  return 'QUALITY CONTROL';
            case 'COMPLETE':    return 'COMPLETE – AWAITING PICKUP';
            default:            return strtoupper( $this->s( $status ) );
        }
    }

    private function normalize( $job ) {
        $mins  = (int) ( $job['estimated_minutes'] ?? 0 );
        $hours = $mins > 0 ? round( $mins / 60, 1 ) : 0;

        return array(
            'id'            => (string) ( $job['job_id']      ?? '' ),
            'so_number'     => $this->s( $job['so_number']    ?? '' ),
            'vin'           => $this->s( $job['vin']          ?? '' ),
            'dealer'        => $this->s( $job['dealer_name']  ?? '' ),
            'customer'      => $this->s( $job['customer_name'] ?? '' ),
            'sales_person'  => $this->s( $job['sales_person'] ?? '' ),
            'assigned_tech' => $this->format_tech( $job['assigned_user_name'] ?? '' ),
            'start_date'    => $job['scheduled_start']  ? substr( $job['scheduled_start'],  0, 10 ) : '',
            'due_date'      => $job['scheduled_finish'] ? substr( $job['scheduled_finish'], 0, 10 ) : '',
            'time_estimate' => $hours > 0 ? $hours . ' hrs' : '',
            'notes'         => $this->s( $job['notes'] ?? '' ),
            'status'        => $this->map_status( $job['status'] ?? '', $job['delay_reason'] ?? '' ),
        );
    }
}
