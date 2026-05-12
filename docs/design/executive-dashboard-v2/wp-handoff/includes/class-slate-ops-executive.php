<?php
/**
 * Slate Ops · Executive Dashboard — data class
 *
 * Single source of truth for the Executive Dashboard page.
 * Methods return plain PHP arrays so the template can server-render the
 * initial dashboard without any JS dependency.
 *
 * Replace the static return blocks with real queries when wiring to live data.
 * Method signatures and array shapes are the contract.
 *
 * @package Slate_Ops
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Slate_Ops_Executive {

	/** Singleton accessor (matches Purchasing pattern). */
	public static function instance() {
		static $instance = null;
		if ( null === $instance ) {
			$instance = new self();
		}
		return $instance;
	}

	/* -----------------------------------------------------------------
	 * Top-level KPIs (Overview tab)
	 * --------------------------------------------------------------- */

	public function get_overview_kpis() {
		return array(
			'active_jobs'        => 23,
			'active_breakdown'   => '6 ready · 1 sched · 4 WIP · 2 QC',
			'in_progress'        => 4,
			'pending_qc'         => 2,
			'ready_for_pickup'   => 6,
			'labor_capture_pct'  => 55,
			'labor_capture_help' => '19 jobs have no logged time',
			'variance'           => '−89h 7m',
			'blocked'            => 9,
			'blocked_breakdown'  => '2 critical · 5 watch · 2 normal',
			'open_timers'        => 1,
			'open_timer_help'    => 'D. Walsh · 4h 12m',
		);
	}

	public function get_executive_readout() {
		return array(
			array(
				'severity' => 'crit',
				'label'    => 'Labor Data Quality',
				'body'     => '<b>19 of 23 jobs</b> have no logged time. Capture is not reliable enough for job costing yet.',
				'stats'    => array(
					array( 'label' => 'Trust',    'value' => 'Low' ),
					array( 'label' => 'Coverage', 'value' => '17%' ),
				),
			),
			array(
				'severity' => 'warn',
				'label'    => 'Schedule Risk',
				'body'     => '<b>4 in progress</b>, <b>2 in QC</b>. Hold new releases until blocked work clears.',
				'stats'    => array(
					array( 'label' => 'WIP',    'value' => '6' ),
					array( 'label' => 'Buffer', 'value' => '2' ),
				),
			),
			array(
				'severity' => 'crit',
				'label'    => 'Bottleneck Status',
				'body'     => '<b>9 jobs blocked</b>. 4 are 4+ days old. Parts and engineering are the largest sources.',
				'stats'    => array(
					array( 'label' => 'Aging',  'value' => '4' ),
					array( 'label' => 'Owners', 'value' => '3 of 9' ),
				),
			),
		);
	}

	public function get_labor_watchlist() {
		return array(
			array( 'issue' => 'Jobs with no logged time',          'count' => 19, 'risk' => 'crit',  'action' => 'Audit clock-in flow' ),
			array( 'issue' => 'Jobs over estimate',                'count' => 1,  'risk' => 'watch', 'action' => 'Review with lead' ),
			array( 'issue' => 'Jobs missing estimates',            'count' => 2,  'risk' => 'watch', 'action' => 'Add estimate' ),
			array( 'issue' => 'Open timers (>4h)',                 'count' => 1,  'risk' => 'warn',  'action' => 'Confirm with tech' ),
			array( 'issue' => 'Techs with no time today',          'count' => 3,  'risk' => 'warn',  'action' => 'Direct check-in' ),
			array( 'issue' => 'Jobs complete with 0 logged time',  'count' => 3,  'risk' => 'crit',  'action' => 'Backfill or void' ),
		);
	}

	public function get_production_watchlist() {
		return array(
			array( 'area' => 'Blocked jobs',                  'count' => 9, 'status' => 'crit',  'owner' => 'Shop lead' ),
			array( 'area' => 'QC aging > 1 day',              'count' => 2, 'status' => 'watch', 'owner' => 'A. Tran' ),
			array( 'area' => 'Jobs ready to build',           'count' => 6, 'status' => 'ok',    'owner' => 'Floor' ),
			array( 'area' => 'Jobs ready for pickup',         'count' => 6, 'status' => 'ok',    'owner' => 'CS' ),
			array( 'area' => 'Schedule risk (WIP > buffer)',  'count' => 4, 'status' => 'warn',  'owner' => 'Ops' ),
			array( 'area' => 'On hold without owner',         'count' => 2, 'status' => 'warn',  'owner' => null ),
		);
	}

	/* -----------------------------------------------------------------
	 * Tech Performance tab
	 * --------------------------------------------------------------- */

	public function get_tech_kpis() {
		return array(
			'techs_active'    => 7,
			'logged_today'    => '17h 32m',
			'logged_week'     => '227h 04m',
			'no_time_today'   => 3,
			'no_time_help'    => 'Jake A., Ben O., info',
		);
	}

	public function get_techs() {
		return array(
			array(
				'name' => 'info (system)', 'state' => 'warn',
				'assigned' => 3, 'active' => 3, 'today' => '0h 0m', 'week' => '108h 53m',
				'est' => '38h 0m', 'logged' => '108h 53m', 'variance' => '+70h 53m',
				'capture' => 287, 'capture_flag' => 'crit',
				'flags' => array(
					array( 'text' => 'DATA ISSUE', 'kind' => 'crit' ),
					array( 'text' => 'OVER ESTIMATE', 'kind' => 'warn' ),
				),
				'note' => 'System account — bulk imported entries',
			),
			array(
				'name' => 'Jake Austin', 'state' => 'crit',
				'assigned' => 3, 'active' => 3, 'today' => '0h 0m', 'week' => '0h 0m',
				'est' => '56h 0m', 'logged' => '0h 0m', 'variance' => '-56h 0m',
				'capture' => 0, 'capture_flag' => 'crit',
				'flags' => array(
					array( 'text' => 'NO TIME TODAY',     'kind' => 'crit' ),
					array( 'text' => 'NO TIME THIS WEEK', 'kind' => 'crit' ),
				),
				'note' => 'No clock-in events found',
			),
			array(
				'name' => 'Marcos Rivera', 'state' => 'ok',
				'assigned' => 5, 'active' => 2, 'today' => '5h 12m', 'week' => '32h 40m',
				'est' => '44h 0m', 'logged' => '39h 18m', 'variance' => '-4h 42m',
				'capture' => 89, 'capture_flag' => 'ok', 'flags' => array(), 'note' => '',
			),
			array(
				'name' => 'Devin Walsh', 'state' => 'warn',
				'assigned' => 4, 'active' => 3, 'today' => '1h 30m', 'week' => '18h 05m',
				'est' => '30h 0m', 'logged' => '26h 12m', 'variance' => '-3h 48m',
				'capture' => 87, 'capture_flag' => 'ok',
				'flags' => array( array( 'text' => 'OPEN TIMER', 'kind' => 'warn' ) ),
				'note' => 'Open timer 4h 12m on S-ORD101422',
			),
			array(
				'name' => 'Priya Shah', 'state' => 'ok',
				'assigned' => 3, 'active' => 1, 'today' => '6h 02m', 'week' => '29h 14m',
				'est' => '22h 0m', 'logged' => '23h 40m', 'variance' => '+1h 40m',
				'capture' => 107, 'capture_flag' => 'ok', 'flags' => array(), 'note' => '',
			),
			array(
				'name' => 'Ben Okafor', 'state' => 'warn',
				'assigned' => 2, 'active' => 2, 'today' => '0h 0m', 'week' => '11h 22m',
				'est' => '16h 0m', 'logged' => '11h 22m', 'variance' => '-4h 38m',
				'capture' => 71, 'capture_flag' => 'warn',
				'flags' => array( array( 'text' => 'NO TIME TODAY', 'kind' => 'warn' ) ),
				'note' => 'Last entry yesterday 4:18 PM',
			),
			array(
				'name' => 'Alyssa Tran', 'state' => 'ok',
				'assigned' => 4, 'active' => 2, 'today' => '4h 48m', 'week' => '27h 30m',
				'est' => '28h 0m', 'logged' => '26h 02m', 'variance' => '-1h 58m',
				'capture' => 93, 'capture_flag' => 'ok', 'flags' => array(), 'note' => '',
			),
		);
	}

	/* -----------------------------------------------------------------
	 * Job Performance tab
	 * --------------------------------------------------------------- */

	public function get_job_kpis() {
		return array(
			'total_jobs'    => 23,
			'over_estimate' => 1,
			'missing_time'  => 14,
			'at_risk'       => 8,
		);
	}

	public function get_jobs() {
		return array(
			array( 'so' => 'S-ORD101395', 'cust' => 'Sigma Industrial',             'status' => 'BLOCKED',         'lead' => null,             'est' => '5h 0m',  'logged' => '0h 0m',  'variance' => '-5h 0m',  'used' => 0,   'risk' => 'crit',  'reason' => 'Awaiting parts' ),
			array( 'so' => 'S-ORD101363', 'cust' => 'Cory Hua',                     'status' => 'BLOCKED',         'lead' => 'Marcos Rivera',  'est' => '5h 0m',  'logged' => '0h 0m',  'variance' => '-5h 0m',  'used' => 0,   'risk' => 'crit',  'reason' => 'Customer approval' ),
			array( 'so' => 'S-ORD101389', 'cust' => 'Sigma Industrial',             'status' => 'BLOCKED',         'lead' => null,             'est' => '5h 0m',  'logged' => '0h 0m',  'variance' => '-5h 0m',  'used' => 0,   'risk' => 'crit',  'reason' => 'Engineering' ),
			array( 'so' => 'S-ORD101391', 'cust' => 'Sigma Industrial',             'status' => 'BLOCKED',         'lead' => null,             'est' => '5h 0m',  'logged' => '0h 0m',  'variance' => '-5h 0m',  'used' => 0,   'risk' => 'crit',  'reason' => 'Awaiting parts' ),
			array( 'so' => 'S-ORD101392', 'cust' => 'Sigma Industrial',             'status' => 'BLOCKED',         'lead' => null,             'est' => '5h 0m',  'logged' => '0h 0m',  'variance' => '-5h 0m',  'used' => 0,   'risk' => 'watch', 'reason' => 'Awaiting parts' ),
			array( 'so' => 'S-ORD101422', 'cust' => 'Northstar Equipment',          'status' => 'IN PROGRESS',     'lead' => 'Devin Walsh',    'est' => '12h 0m', 'logged' => '8h 14m', 'variance' => '-3h 46m', 'used' => 68,  'risk' => 'watch', 'reason' => 'Open timer' ),
			array( 'so' => 'S-ORD101418', 'cust' => 'Bridgeline Logistics',         'status' => 'IN PROGRESS',     'lead' => 'Priya Shah',     'est' => '8h 0m',  'logged' => '9h 18m', 'variance' => '+1h 18m', 'used' => 116, 'risk' => 'watch', 'reason' => 'Over estimate' ),
			array( 'so' => 'S-ORD101411', 'cust' => 'Mercedes-Benz of Wilsonville', 'status' => 'QC',              'lead' => 'Alyssa Tran',    'est' => '6h 0m',  'logged' => '5h 42m', 'variance' => '-0h 18m', 'used' => 95,  'risk' => 'ok',    'reason' => '' ),
			array( 'so' => 'S-ORD101353', 'cust' => 'Gresham Toyota',               'status' => 'COMPLETE',        'lead' => null,             'est' => '5h 0m',  'logged' => '0h 0m',  'variance' => '-5h 0m',  'used' => 0,   'risk' => 'crit',  'reason' => 'Closed with zero time' ),
			array( 'so' => 'S-ORD101365', 'cust' => 'Gresham Toyota',               'status' => 'COMPLETE',        'lead' => null,             'est' => '5h 0m',  'logged' => '0h 0m',  'variance' => '-5h 0m',  'used' => 0,   'risk' => 'crit',  'reason' => 'Closed with zero time' ),
			array( 'so' => 'S-ORD101401', 'cust' => 'Mercedes-Benz Wilson.',        'status' => 'BLOCKED',         'lead' => null,             'est' => '1h 0m',  'logged' => '0h 0m',  'variance' => '-1h 0m',  'used' => 0,   'risk' => 'watch', 'reason' => 'Tech capacity' ),
			array( 'so' => 'S-ORD101393', 'cust' => 'Sigma Industrial',             'status' => 'BLOCKED',         'lead' => null,             'est' => '5h 0m',  'logged' => '0h 0m',  'variance' => '-5h 0m',  'used' => 0,   'risk' => 'watch', 'reason' => 'Awaiting parts' ),
			array( 'so' => 'S-ORD101394', 'cust' => 'Sigma Industrial',             'status' => 'BLOCKED',         'lead' => null,             'est' => '5h 0m',  'logged' => '0h 0m',  'variance' => '-5h 0m',  'used' => 0,   'risk' => 'crit',  'reason' => 'QC rework' ),
			array( 'so' => 'S-ORD101396', 'cust' => 'Sigma Industrial',             'status' => 'READY FOR BUILD', 'lead' => 'Ben Okafor',     'est' => '6h 0m',  'logged' => '0h 0m',  'variance' => '-6h 0m',  'used' => 0,   'risk' => 'ok',    'reason' => '' ),
			array( 'so' => 'S-ORD101427', 'cust' => 'Sunset Transit',               'status' => 'READY FOR BUILD', 'lead' => 'Marcos Rivera',  'est' => '10h 0m', 'logged' => '0h 0m',  'variance' => '-10h 0m', 'used' => 0,   'risk' => 'ok',    'reason' => '' ),
			array( 'so' => 'S-ORD101430', 'cust' => 'Pearl District Auto',          'status' => 'SCHEDULED',       'lead' => 'Priya Shah',     'est' => '4h 0m',  'logged' => '0h 0m',  'variance' => '-4h 0m',  'used' => 0,   'risk' => 'ok',    'reason' => '' ),
			array( 'so' => 'S-ORD417524', 'cust' => 'Test Account',                 'status' => 'COMPLETE',        'lead' => null,             'est' => '1h 0m',  'logged' => '0h 0m',  'variance' => '-1h 0m',  'used' => 0,   'risk' => 'watch', 'reason' => 'Test record' ),
		);
	}

	/* -----------------------------------------------------------------
	 * Labor Capture tab
	 * --------------------------------------------------------------- */

	public function get_labor_kpis() {
		return array(
			'jobs_with_estimate'    => 21,
			'jobs_missing_estimate' => 2,
			'jobs_with_logged'      => 4,
			'jobs_no_logged'        => 19,
			'total_raw_logged'      => '108h 53m',
			'estimate_coverage'     => 91,
			'open_timers'           => 1,
			'zero_time_completions' => 3,
		);
	}

	public function get_labor_trust() {
		return array(
			'score'  => 42,
			'tier'   => 'LOW',
			'desc'   => 'Too many jobs have no logged labor. Costing reports should not be relied on for the current period until capture clears 75%.',
			'factors' => array(
				array( 'label' => 'Estimate coverage',          'value' => 91, 'kind' => 'ok' ),
				array( 'label' => 'Logged time coverage',       'value' => 17, 'kind' => 'crit' ),
				array( 'label' => 'Zero-time completions OK',   'value' => 86, 'kind' => 'crit' ),
				array( 'label' => 'Open-timer hygiene',         'value' => 71, 'kind' => 'warn' ),
			),
		);
	}

	public function get_labor_capture_watchlist() {
		return array(
			array( 'so' => 'S-ORD101353', 'cust' => 'Gresham Toyota',           'tech' => null,              'issue' => 'Complete with 0 time', 'kind' => 'crit',  'last' => '—',       'action' => 'Backfill or void' ),
			array( 'so' => 'S-ORD101365', 'cust' => 'Gresham Toyota',           'tech' => null,              'issue' => 'Complete with 0 time', 'kind' => 'crit',  'last' => '—',       'action' => 'Backfill or void' ),
			array( 'so' => 'S-ORD417524', 'cust' => 'Test Account',             'tech' => null,              'issue' => 'Test record',          'kind' => 'muted', 'last' => '—',       'action' => 'Archive' ),
			array( 'so' => 'S-ORD101422', 'cust' => 'Northstar Equipment',      'tech' => 'Devin Walsh',     'issue' => 'Open timer 4h 12m',    'kind' => 'warn',  'last' => '9:24 AM', 'action' => 'Confirm with tech', 'tech_state' => 'warn' ),
			array( 'so' => 'S-ORD101396', 'cust' => 'Sigma Industrial',         'tech' => 'Ben Okafor',      'issue' => 'No logged time',       'kind' => 'crit',  'last' => '—',       'action' => 'Verify clock-in' ),
			array( 'so' => 'S-ORD101427', 'cust' => 'Sunset Transit',           'tech' => 'Marcos Rivera',   'issue' => 'No logged time',       'kind' => 'crit',  'last' => '2 days',  'action' => 'Verify clock-in' ),
			array( 'so' => 'S-ORD101430', 'cust' => 'Pearl District Auto',      'tech' => 'Priya Shah',      'issue' => 'Missing estimate',     'kind' => 'warn',  'last' => '—',       'action' => 'Add estimate' ),
			array( 'so' => 'S-ORD101411', 'cust' => 'Mercedes-Benz Wilson.',    'tech' => 'Alyssa Tran',     'issue' => 'On track',             'kind' => 'muted', 'last' => '9:38 AM', 'action' => '' ),
		);
	}

	/* -----------------------------------------------------------------
	 * Bottlenecks tab
	 * --------------------------------------------------------------- */

	public function get_bottleneck_kpis() {
		return array(
			'ready_for_build' => 6,
			'scheduled'       => 1,
			'in_progress'     => 4,
			'qc'              => 2,
			'blocked'         => 9,
			'on_hold'         => 0,
			'cancelled'       => 0,
			'avg_block_age'   => '3.1',
			'avg_age_help'    => 'Up from 2.4d last week',
		);
	}

	public function get_blocker_reasons() {
		return array(
			array( 'label' => 'Parts',         'count' => 5, 'delta' => '+2 wk', 'kind' => 'crit' ),
			array( 'label' => 'Approval',      'count' => 1, 'delta' => '—',     'kind' => '' ),
			array( 'label' => 'Engineering',   'count' => 1, 'delta' => '+1',    'kind' => 'warn' ),
			array( 'label' => 'Customer',      'count' => 1, 'delta' => '—',     'kind' => '' ),
			array( 'label' => 'Tech Capacity', 'count' => 1, 'delta' => '—',     'kind' => '' ),
			array( 'label' => 'QC / Rework',   'count' => 1, 'delta' => '—',     'kind' => 'warn' ),
		);
	}

	public function get_blockers() {
		return array(
			array( 'so' => 'S-ORD101389', 'cust' => 'Sigma Industrial', 'reason' => 'Engineering',       'dept' => 'Eng',   'owner' => null,         'days' => 7, 'next' => 'Assign engineer',      'sev' => 'crit' ),
			array( 'so' => 'S-ORD101394', 'cust' => 'Sigma Industrial', 'reason' => 'QC rework',         'dept' => 'QC',    'owner' => 'A. Tran',    'days' => 5, 'next' => 'Schedule rework slot', 'sev' => 'crit' ),
			array( 'so' => 'S-ORD101395', 'cust' => 'Sigma Industrial', 'reason' => 'Parts',             'dept' => 'Parts', 'owner' => 'M. Rivera',  'days' => 4, 'next' => 'Vendor follow-up',     'sev' => 'crit' ),
			array( 'so' => 'S-ORD101391', 'cust' => 'Sigma Industrial', 'reason' => 'Parts',             'dept' => 'Parts', 'owner' => 'M. Rivera',  'days' => 4, 'next' => 'Vendor follow-up',     'sev' => 'crit' ),
			array( 'so' => 'S-ORD101363', 'cust' => 'Cory Hua',         'reason' => 'Customer approval', 'dept' => 'CS',    'owner' => null,         'days' => 3, 'next' => 'CS to call customer',  'sev' => 'watch' ),
			array( 'so' => 'S-ORD101392', 'cust' => 'Sigma Industrial', 'reason' => 'Parts',             'dept' => 'Parts', 'owner' => 'M. Rivera',  'days' => 2, 'next' => 'Vendor follow-up',     'sev' => 'watch' ),
			array( 'so' => 'S-ORD101393', 'cust' => 'Sigma Industrial', 'reason' => 'Parts',             'dept' => 'Parts', 'owner' => null,         'days' => 2, 'next' => 'Assign owner',         'sev' => 'watch' ),
			array( 'so' => 'S-ORD101401', 'cust' => 'Mercedes-Benz',    'reason' => 'Tech capacity',     'dept' => 'Shop',  'owner' => 'B. Okafor',  'days' => 1, 'next' => 'Reschedule',           'sev' => 'normal' ),
			array( 'so' => 'S-ORD101396', 'cust' => 'Sigma Industrial', 'reason' => 'Parts',             'dept' => 'Parts', 'owner' => 'M. Rivera',  'days' => 1, 'next' => 'Vendor follow-up',     'sev' => 'normal' ),
		);
	}

	/* -----------------------------------------------------------------
	 * Render helpers — kept here so the template stays declarative.
	 * --------------------------------------------------------------- */

	/** Initials for an avatar token. */
	public static function initials( $name ) {
		if ( ! $name ) {
			return '—';
		}
		$parts = preg_split( '/\s+/', trim( $name ) );
		$out   = '';
		foreach ( $parts as $p ) {
			if ( $p !== '' ) {
				$out .= mb_substr( $p, 0, 1 );
			}
			if ( mb_strlen( $out ) >= 2 ) {
				break;
			}
		}
		return strtoupper( $out );
	}

	/** Avatar pill. */
	public static function avatar_html( $name, $kind = '' ) {
		$cls = 'ava-sm' . ( $kind ? ' ' . esc_attr( $kind ) : '' );
		return '<span class="' . $cls . '">' . esc_html( self::initials( $name ) ) . '</span>';
	}

	/** Tag chip. $kind: ok|crit|warn|watch|muted|normal. */
	public static function tag_html( $kind, $text, $with_arch = false ) {
		$arch = ( $with_arch && in_array( $kind, array( 'warn', 'watch' ), true ) ) ? '<span class="arch"></span>' : '';
		return '<span class="tag ' . esc_attr( $kind ) . '">' . $arch . '<span class="dot"></span>' . esc_html( $text ) . '</span>';
	}

	/** Capture/usage meter. */
	public static function meter_html( $value, $kind = null, $target = 100 ) {
		$value = (int) $value;
		$max   = max( 120, $value );
		$pct   = min( 100, ( $value / $max ) * 100 );
		$tgt   = min( 100, ( $target / $max ) * 100 );
		if ( null === $kind ) {
			$kind = $value < $target * 0.6 ? 'crit' : ( $value < $target * 0.85 ? 'warn' : '' );
		}
		ob_start(); ?>
		<span class="meter">
			<span class="bar <?php echo esc_attr( $kind ); ?>">
				<i style="width:<?php echo esc_attr( $pct ); ?>%"></i>
				<span class="target" style="left:<?php echo esc_attr( $tgt ); ?>%"></span>
			</span>
			<b><?php echo esc_html( $value ); ?>%</b>
		</span>
		<?php
		return ob_get_clean();
	}

	/** Map a job status to a tag kind. */
	public static function status_kind( $status ) {
		$m = array(
			'BLOCKED'         => 'crit',
			'IN PROGRESS'     => 'ok',
			'QC'              => 'watch',
			'COMPLETE'        => 'muted',
			'READY FOR BUILD' => 'normal',
			'SCHEDULED'       => 'normal',
			'INTAKE'          => 'normal',
		);
		return isset( $m[ $status ] ) ? $m[ $status ] : 'normal';
	}
}
