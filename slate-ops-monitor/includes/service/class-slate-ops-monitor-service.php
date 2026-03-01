<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Fetches job data from the Slate Ops shop-floor endpoint and normalises it
 * into the same shape Slate_Monitor_Clickup_Service::get_jobs() produced,
 * so the React bundle works without any changes.
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
    // Normalisation — Slate Ops DB row → monitor display shape
    // -------------------------------------------------------------------------

    private function s( $v ) {
        return trim( (string) $v );
    }

    private function clean_dealer( $dealer ) {
        $d = $this->s( $dealer );
        // Purely-numeric dealer values are internal IDs, not display names.
        return ( $d === '' || ctype_digit( $d ) ) ? '' : $d;
    }

    private function party_display( $dealer_raw, $customer, $so ) {
        $dealer   = $this->clean_dealer( $dealer_raw );
        $customer = $this->s( $customer );
        $so       = $this->s( $so );

        if ( $customer !== '' && $dealer !== '' ) {
            return array( 'primary' => $customer, 'secondary' => 'via ' . $dealer, 'scenario' => 'retail' );
        }
        if ( $customer !== '' ) {
            return array( 'primary' => $customer, 'secondary' => 'Direct', 'scenario' => 'direct' );
        }
        if ( $dealer_raw !== '' ) {
            $primary = $dealer !== '' ? $dealer : ( $so !== '' ? $so : 'Unknown' );
            return array( 'primary' => $primary, 'secondary' => 'Stock unit', 'scenario' => 'stock' );
        }
        return array( 'primary' => ( $so !== '' ? $so : 'Unknown' ), 'secondary' => '', 'scenario' => 'fallback' );
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
     * Map Slate Ops internal status codes to the display strings the React
     * bundle already renders (same vocabulary the ClickUp version used).
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
            case 'BLOCKED':    return 'DELAY – ON HOLD';
            case 'IN_PROGRESS': return 'IN PROGRESS';
            case 'PENDING_QC':  return 'QUALITY CONTROL';
            case 'COMPLETE':    return 'COMPLETE – AWAITING PICKUP';
            default:            return strtoupper( $this->s( $status ) );
        }
    }

    private function normalize( $job ) {
        $dealer   = $this->s( $job['dealer_name']   ?? '' );
        $customer = $this->s( $job['customer_name'] ?? '' );
        $so       = $this->s( $job['so_number']     ?? '' );
        $party    = $this->party_display( $dealer, $customer, $so );

        return array(
            'id'                  => (string) ( $job['job_id'] ?? '' ),
            'so_number'           => $so,
            'dealer'              => $dealer,
            'customer'            => $customer,
            'vin'                 => $this->s( $job['vin']      ?? '' ),
            'primary_name'        => $party['primary'],
            'secondary_name'      => $party['secondary'],
            'party_scenario'      => $party['scenario'],
            'vin_last8'           => $this->s( $job['vin_last8'] ?? '' ),
            'status'              => $this->map_status( $job['status'] ?? '', $job['delay_reason'] ?? '' ),
            'assigned_tech'       => $this->format_tech( $job['assigned_user_name'] ?? '' ),
            'start_date'          => $job['scheduled_start']  ?: null,
            'due_date'            => $job['scheduled_finish'] ?: null,
            'time_estimate_hours' => round( (int) ( $job['estimated_minutes'] ?? 0 ) / 60, 1 ),
            'tags'                => array(),
            'latest_comment'      => $this->s( $job['notes'] ?? '' ),
        );
    }
}
