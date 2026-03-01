<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Admin settings page — mirrors the original monitor's settings structure
 * (refresh_interval, ui_scale) but removes all ClickUp fields.
 */
class Slate_Ops_Monitor_Admin {

    public function add_plugin_page() {
        add_options_page(
            'Slate Ops Monitor Settings',
            'Slate Ops Monitor',
            'manage_options',
            'slate-ops-monitor',
            array( $this, 'create_admin_page' )
        );
    }

    public function page_init() {
        register_setting(
            'slate_ops_monitor_group',
            'slate_ops_monitor_settings',
            array( $this, 'sanitize' )
        );

        add_settings_section(
            'slate_ops_monitor_display',
            'Display Settings',
            null,
            'slate-ops-monitor'
        );

        add_settings_field(
            'refresh_interval',
            'Refresh Interval (ms)',
            array( $this, 'field_refresh_interval' ),
            'slate-ops-monitor',
            'slate_ops_monitor_display'
        );

        add_settings_field(
            'ui_scale',
            'UI Scale',
            array( $this, 'field_ui_scale' ),
            'slate-ops-monitor',
            'slate_ops_monitor_display'
        );
    }

    public function sanitize( $input ) {
        $out = array();
        $out['refresh_interval'] = isset( $input['refresh_interval'] ) ? absint( $input['refresh_interval'] ) : 60000;
        $out['ui_scale']         = isset( $input['ui_scale'] )         ? floatval( $input['ui_scale'] )       : 1;
        return $out;
    }

    public function create_admin_page() {
        $options = get_option( 'slate_ops_monitor_settings', array() );
        ?>
        <div class="wrap">
            <h1>Slate Ops Monitor Settings</h1>
            <p>Data source: <strong>Slate Ops</strong> (<code><?php echo esc_html( rest_url( 'slate/v2/shop-floor' ) ); ?></code>)</p>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'slate_ops_monitor_group' );
                do_settings_sections( 'slate-ops-monitor' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function field_refresh_interval() {
        $options = get_option( 'slate_ops_monitor_settings', array() );
        $val     = ! empty( $options['refresh_interval'] ) ? $options['refresh_interval'] : '60000';
        echo '<input type="number" name="slate_ops_monitor_settings[refresh_interval]" value="' . esc_attr( $val ) . '" min="5000" step="1000" /> ms';
    }

    public function field_ui_scale() {
        $options = get_option( 'slate_ops_monitor_settings', array() );
        $val     = ! empty( $options['ui_scale'] ) ? $options['ui_scale'] : '1';
        echo '<input type="number" name="slate_ops_monitor_settings[ui_scale]" value="' . esc_attr( $val ) . '" min="0.5" max="3" step="0.05" />';
    }
}
