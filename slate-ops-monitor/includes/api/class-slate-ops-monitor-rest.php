<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Slate_Ops_Monitor_Rest {

    public function register_routes() {
        register_rest_route( 'slate-ops-monitor/v1', '/jobs', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_jobs' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public function get_jobs() {
        $service = new Slate_Ops_Monitor_Service();
        $jobs    = $service->get_jobs();
        return new WP_REST_Response( array( 'jobs' => $jobs ), 200 );
    }
}
