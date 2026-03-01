<?php

class Slate_Monitor_Rest {

    public function register_routes() {
        register_rest_route( 'slate-monitor/v1', '/jobs', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_jobs' ),
            'permission_callback' => '__return_true', // Public access as requested
        ));
    }

    public function get_jobs( $request ) {
        $service = new Slate_Monitor_Clickup_Service();
        $jobs = $service->get_jobs();

        return new WP_REST_Response( array( 'jobs' => $jobs ), 200 );
    }
}