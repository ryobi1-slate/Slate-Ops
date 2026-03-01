<?php

class Slate_Monitor_Clickup_Service {

    private $api_key;
    private $list_id;
    private $settings;

    public function __construct() {
        $this->settings = get_option( 'slate_monitor_settings' );
        $this->api_key  = isset( $this->settings['api_key'] ) ? trim( (string) $this->settings['api_key'] ) : '';
        $this->list_id  = isset( $this->settings['list_id'] ) ? trim( (string) $this->settings['list_id'] ) : '';
    }

    public function get_jobs() {

        if ( $this->api_key === '' || $this->list_id === '' ) {
            return [];
        }

        $url  = "https://api.clickup.com/api/v2/list/{$this->list_id}/task?include_closed=true&archived=false&subtasks=true";
        $args = array(
            'headers' => array(
                'Authorization' => $this->api_key,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 20,
        );

        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            return [];
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = (string) wp_remote_retrieve_body( $response );

        if ( $code < 200 || $code >= 300 ) {
            // If ClickUp errors, don't break the monitor page. Just return no jobs.
            return [];
        }

        $data = json_decode( $body, true );
        if ( ! is_array( $data ) || ! isset( $data['tasks'] ) || ! is_array( $data['tasks'] ) ) {
            return [];
        }

        return $this->transform_tasks( $data['tasks'] );
    }

    private function transform_tasks( array $tasks ): array {
        $jobs = [];

        foreach ( $tasks as $task ) {
            if ( ! is_array( $task ) ) {
                continue;
            }

            $job = $this->map_task_to_job( $task );

            // Hide "Closed" tasks (default). Flip this to true if you want to show them.
            $hide_closed = true;

            if ( $hide_closed && isset( $job['status_detail'] ) && strtolower( trim( (string) $job['status_detail'] ) ) === 'closed' ) {
                continue;
            }

            $jobs[] = $job;
        }

        return $jobs;
    }

    private function map_task_to_job( array $task ): array {

        $fields = isset( $task['custom_fields'] ) && is_array( $task['custom_fields'] ) ? $task['custom_fields'] : [];

        // Helper to find field object by configured ID
        $get_field = function( string $field_key ) use ( $fields ) {
            $field_id = isset( $this->settings[ $field_key ] ) ? (string) $this->settings[ $field_key ] : '';
            if ( $field_id === '' ) {
                return null;
            }
            foreach ( $fields as $f ) {
                if ( isset( $f['id'] ) && (string) $f['id'] === $field_id ) {
                    return $f;
                }
            }
            return null;
        };

        // Helper to safely extract simple value
        $get_value = function( $field ) {
            if ( ! $field || ! isset( $field['value'] ) ) {
                return '';
            }
            if ( is_string( $field['value'] ) || is_numeric( $field['value'] ) ) {
                return (string) $field['value'];
            }
            return $field['value']; // could be array for labels
        };

        // SO Number
        $so_field  = $get_field( 'field_so' );
        $so_number = $so_field ? (string) $get_value( $so_field ) : '';

        // VIN (last 8)
        $vin_field = $get_field( 'field_vin' );
        $vin_raw   = $vin_field ? (string) $get_value( $vin_field ) : '';
        $vin_raw   = trim( $vin_raw );
        $vin       = ( strlen( $vin_raw ) > 8 ) ? substr( $vin_raw, -8 ) : $vin_raw;

        // Dealer (dropdown index mapping)
        $dealer_field = $get_field( 'field_dealer' );
        $dealer       = '';
        if ( $dealer_field && isset( $dealer_field['value'] ) && $dealer_field['value'] !== '' ) {
            $idx = (int) $dealer_field['value'];
            switch ( $idx ) {
                case 0: $dealer = 'MBWIL'; break;
                case 1: $dealer = 'MBSEA'; break;
                case 2: $dealer = 'GTOY';  break;
                default: $dealer = '';
            }
        }

        // Customer
        $cust_field = $get_field( 'field_customer' );
        $customer   = $cust_field ? (string) $get_value( $cust_field ) : '';

        // Sales person (dropdown options)
        $sales_field  = $get_field( 'field_sales' );
        $sales_person = 'Unassigned';
        if ( $sales_field && isset( $sales_field['value'] ) && isset( $sales_field['type_config']['options'] ) && is_array( $sales_field['type_config']['options'] ) ) {
            $val_index = $sales_field['value'];
            foreach ( $sales_field['type_config']['options'] as $opt ) {
                if ( isset( $opt['orderindex'] ) && (string) $opt['orderindex'] === (string) $val_index ) {
                    $sales_person = isset( $opt['name'] ) ? (string) $opt['name'] : $sales_person;
                    break;
                }
            }
        }

        // Assigned tech (labels type)
        $tech_field   = $get_field( 'field_tech' );
        $assigned_tech = 'Unassigned';
        if ( $tech_field && isset( $tech_field['value'] ) && is_array( $tech_field['value'] ) && ! empty( $tech_field['value'] ) ) {
            $first_val = $tech_field['value'][0];

            if ( isset( $tech_field['type_config']['options'] ) && is_array( $tech_field['type_config']['options'] ) ) {
                foreach ( $tech_field['type_config']['options'] as $opt ) {
                    if ( ! isset( $opt['id'] ) ) {
                        continue;
                    }

                    // value could be a string id OR an object with id
                    if ( ( ! is_array( $first_val ) && (string) $opt['id'] === (string) $first_val )
                      || ( is_array( $first_val ) && isset( $first_val['id'] ) && (string) $opt['id'] === (string) $first_val['id'] ) ) {
                        $assigned_tech = isset( $opt['label'] ) ? (string) $opt['label'] : ( isset( $opt['name'] ) ? (string) $opt['name'] : $assigned_tech );
                        break;
                    }
                }
            }
        }

        // Dates (ClickUp uses ms timestamps)
        $start_ts   = isset( $task['start_date'] ) ? $task['start_date'] : '';
        $due_ts     = isset( $task['due_date'] ) ? $task['due_date'] : '';
        $start_date = $start_ts ? date( 'm/d', (int) $start_ts / 1000 ) : '';
        $due_date   = $due_ts ? date( 'm/d', (int) $due_ts / 1000 ) : '';

        // Time estimate ms -> hours
        $time_est_ms = isset( $task['time_estimate'] ) ? (int) $task['time_estimate'] : 0;
        $hours       = $time_est_ms > 0 ? round( $time_est_ms / 3600000 ) . 'h' : '';

        // Notes
        $notes_field = $get_field( 'field_notes' );
        $notes       = $notes_field ? (string) $get_value( $notes_field ) : '';

        // Status normalization (THIS is the fix)
        $raw_status = ( isset( $task['status']['status'] ) && $task['status']['status'] !== '' )
            ? (string) $task['status']['status']
            : 'unknown';

        $status_bucket = $this->normalize_status_bucket( $raw_status );

        return [
            'id'            => isset( $task['id'] ) ? (string) $task['id'] : '',
            'so_number'     => $so_number,
            'vin'           => $vin,
            'dealer'        => $dealer,
            'customer'      => $customer,
            'sales_person'  => $sales_person,
            'assigned_tech' => $assigned_tech,
            'start_date'    => $start_date,
            'due_date'      => $due_date,
            'time_estimate' => $hours,
            'notes'         => $notes,

            // Monitor uses this bucket
            'status'        => $status_bucket,

            // Keep the original status for debugging / future UI use
            'status_detail' => $raw_status,
        ];
    }

    private function normalize_status_bucket( string $clickup_status ): string {

        $s = strtolower( trim( $clickup_status ) );

        // Delayed / holds
        if ( strpos( $s, 'delay' ) !== false || strpos( $s, 'parts' ) !== false || strpos( $s, 'hold' ) !== false ) {
            return 'DELAYED';
        }

        // In progress
        if ( strpos( $s, 'in progress' ) !== false || strpos( $s, 'progress' ) !== false || strpos( $s, 'in bay' ) !== false ) {
            return 'IN PROGRESS';
        }

        // Completed
        if ( strpos( $s, 'complete' ) !== false || strpos( $s, 'completed' ) !== false || strpos( $s, 'awaiting pickup' ) !== false || $s === 'closed' ) {
            return 'COMPLETED';
        }

        // Default
        return 'SCHEDULED';
    }
}