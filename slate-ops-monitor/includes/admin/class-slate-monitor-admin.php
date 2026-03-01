<?php

class Slate_Monitor_Admin {

    public function add_plugin_page() {
        add_options_page(
            'Slate Shop Monitor',
            'Slate Shop Monitor',
            'manage_options',
            'slate-shop-monitor',
            array( $this, 'create_admin_page' )
        );
    }

    public function create_admin_page() {
        ?>
        <div class="wrap">
            <h1>Slate Shop Monitor Configuration</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'slate_monitor_option_group' );
                do_settings_sections( 'slate-shop-monitor' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function page_init() {
        register_setting(
            'slate_monitor_option_group',
            'slate_monitor_settings', 
            array( $this, 'sanitize' )
        );

        // --- ClickUp API Section ---
        add_settings_section(
            'slate_monitor_clickup_api',
            'ClickUp API Settings',
            null,
            'slate-shop-monitor'
        );

        $api_fields = [
            'api_key' => 'API Key',
            'list_id' => 'List ID',
            'field_so' => 'SO# Field ID',
            'field_vin' => 'VIN Field ID',
            'field_dealer' => 'Dealer Field ID',
            'field_customer' => 'Customer Field ID',
            'field_sales' => 'Sales Person Field ID',
            'field_tech' => 'Assigned Tech Field ID',
            'field_notes' => 'Notes Field ID'
        ];

        foreach ($api_fields as $id => $label) {
            add_settings_field(
                $id,
                $label,
                array( $this, 'render_text_field' ),
                'slate-shop-monitor',
                'slate_monitor_clickup_api',
                array( 'id' => $id )
            );
        }

        // --- Display Options Section ---
        add_settings_section(
            'slate_monitor_display_options',
            'Display Options',
            null,
            'slate-shop-monitor'
        );

        add_settings_field(
            'refresh_interval',
            'Refresh Interval (ms)',
            array( $this, 'render_text_field' ),
            'slate-shop-monitor',
            'slate_monitor_display_options',
            array( 'id' => 'refresh_interval', 'description' => 'Default: 60000 (60 seconds)' )
        );

        add_settings_field(
            'ui_scale',
            'UI Scale (4K TV)',
            array( $this, 'render_text_field' ),
            'slate-shop-monitor',
            'slate_monitor_display_options',
            array( 'id' => 'ui_scale', 'description' => '1.0 = Default. Try 1.5 or 2.0 for large screens.' )
        );
    }

    public function sanitize( $input ) {
        $new_input = array();
        foreach($input as $key => $val) {
            $new_input[$key] = sanitize_text_field($val);
        }
        return $new_input;
    }

    public function render_text_field( $args ) {
        $options = get_option( 'slate_monitor_settings' );
        $id = $args['id'];
        $val = isset( $options[$id] ) ? $options[$id] : '';
        $desc = isset( $args['description'] ) ? "<p class='description'>{$args['description']}</p>" : '';
        echo "<input type='text' name='slate_monitor_settings[$id]' value='$val' class='regular-text' />$desc";
    }
}