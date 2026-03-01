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
        $response = wp_remote_get( rest_url( 'slate/v2/shop-floor' ), array(
            'timeout' => 10,
        ) );

        if ( is_wp_error( $response ) ) {
            return array();
        }

        if ( (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return array();
        }

        $raw = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $raw ) ) {
            return array();
        }

        return array_values( array_map( array( $this, 'normalize' ), $raw ) );
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
            'start_date'    => $this->s( $job['scheduled_start']  ?? '' ),
            'due_date'      => $this->s( $job['scheduled_finish'] ?? '' ),
            'time_estimate' => $hours > 0 ? $hours . ' hrs' : '',
            'notes'         => $this->s( $job['notes'] ?? '' ),
            'status'        => $this->map_status( $job['status'] ?? '', $job['delay_reason'] ?? '' ),
        );
    }
}
