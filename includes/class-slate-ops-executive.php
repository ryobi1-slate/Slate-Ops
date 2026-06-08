<?php
/**
 * Slate Ops · Executive Dashboard — data class
 *
 * Single source of truth for the server-rendered Executive Dashboard page.
 * Reads live Slate Ops job and technician time data; no checkout, payment,
 * shipping, or alternate order workflow is introduced here.
 *
 * @package Slate_Ops
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Slate_Ops_Executive {

	private $summary = null;
	private $period = null;

	/** Singleton accessor (matches Purchasing pattern). */
	public static function instance() {
		static $instance = null;
		if ( null === $instance ) {
			$instance = new self();
		}
		return $instance;
	}

	/* -----------------------------------------------------------------
	 * Public page contracts
	 * --------------------------------------------------------------- */

	public function get_overview_kpis() {
		$s = $this->summary();
		return $s['overview'];
	}

	public function get_executive_readout() {
		$s = $this->summary();
		return $s['readout'];
	}

	public function get_labor_watchlist() {
		$s = $this->summary();
		return $s['labor_watchlist'];
	}

	public function get_production_watchlist() {
		$s = $this->summary();
		return $s['production_watchlist'];
	}

	public function get_tech_kpis() {
		$s = $this->summary();
		return $s['tech_kpis'];
	}

	public function get_techs() {
		$s = $this->summary();
		return $s['techs'];
	}

	public function get_job_kpis() {
		$s = $this->summary();
		return $s['job_kpis'];
	}

	public function get_jobs() {
		$s = $this->summary();
		return $s['jobs'];
	}

	public function get_labor_kpis() {
		$s = $this->summary();
		return $s['labor_kpis'];
	}

	public function get_labor_trust() {
		$s = $this->summary();
		return $s['labor_trust'];
	}

	public function get_labor_capture_watchlist() {
		$s = $this->summary();
		return $s['labor_capture_watchlist'];
	}

	public function get_labor_diagnostics() {
		$s = $this->summary();
		return $s['labor_diagnostics'];
	}

	public function get_bottleneck_kpis() {
		$s = $this->summary();
		return $s['bottleneck_kpis'];
	}

	public function get_blocker_reasons() {
		$s = $this->summary();
		return $s['blocker_reasons'];
	}

	public function get_blockers() {
		$s = $this->summary();
		return $s['blockers'];
	}

	public function get_period() {
		if ( null !== $this->period ) {
			return $this->period;
		}

		$key = isset( $_GET['period'] ) ? sanitize_key( wp_unslash( $_GET['period'] ) ) : '30'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$options = self::period_options();
		if ( ! isset( $options[ $key ] ) ) {
			$key = '30';
		}

		$cutoff = null;
		if ( 'today' === $key ) {
			$cutoff = gmdate( 'Y-m-d 00:00:00', strtotime( Slate_Ops_Utils::now_gmt() ) );
		} elseif ( $options[ $key ]['days'] ) {
			$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( Slate_Ops_Utils::now_gmt() ) - ( (int) $options[ $key ]['days'] * DAY_IN_SECONDS ) );
		}

		$this->period = array(
			'key' => $key,
			'label' => $options[ $key ]['label'],
			'short_label' => $options[ $key ]['short_label'],
			'days' => $options[ $key ]['days'],
			'cutoff' => $cutoff,
		);

		return $this->period;
	}

	public static function period_options() {
		return array(
			'today' => array( 'label' => 'Today', 'short_label' => 'Today', 'days' => 1 ),
			'7' => array( 'label' => 'Last 7 days', 'short_label' => '7 days', 'days' => 7 ),
			'30' => array( 'label' => 'Last 30 days', 'short_label' => '30 days', 'days' => 30 ),
			'90' => array( 'label' => 'Last 90 days', 'short_label' => '90 days', 'days' => 90 ),
			'all' => array( 'label' => 'All time', 'short_label' => 'All time', 'days' => null ),
		);
	}

	/* -----------------------------------------------------------------
	 * Live data snapshot
	 * --------------------------------------------------------------- */

	private function summary() {
		if ( null !== $this->summary ) {
			return $this->summary;
		}

		global $wpdb;

		$jobs_t = $wpdb->prefix . 'slate_ops_jobs';
		$segs_t = $wpdb->prefix . 'slate_ops_time_segments';

		if ( ! $this->table_exists( $jobs_t ) ) {
			$this->summary = $this->empty_summary();
			return $this->summary;
		}

		$has_segments = $this->table_exists( $segs_t );
		$active_statuses = Slate_Ops_Statuses::active();
		$period = $this->get_period();

		$rows = $this->fetch_jobs( $jobs_t, $active_statuses, $period );

		$job_minutes = array();
		$job_last_entry = array();
		$open_timers = array();
		$open_timer_jobs = array();
		$tech_minutes = array();
		$tech_today = array();
		$tech_week = array();
		$tech_last_entry = array();
		$tech_names = array();

		if ( $has_segments ) {
			$time_rows = $this->fetch_time_rows( $segs_t, $period );

			foreach ( $time_rows as $tr ) {
				$job_id = (int) $tr['job_id'];
				$user_id = (int) $tr['user_id'];
				$minutes = max( 0, (int) $tr['minutes'] );
				$start = (string) $tr['start_ts'];
				$end = (string) ( $tr['end_ts'] ?: $tr['start_ts'] );

				if ( ! isset( $job_minutes[ $job_id ] ) ) {
					$job_minutes[ $job_id ] = 0;
				}
				$job_minutes[ $job_id ] += $minutes;
				if ( empty( $job_last_entry[ $job_id ] ) || $end > $job_last_entry[ $job_id ] ) {
					$job_last_entry[ $job_id ] = $end;
				}

				if ( ! isset( $tech_minutes[ $user_id ] ) ) {
					$tech_minutes[ $user_id ] = 0;
					$tech_today[ $user_id ] = 0;
					$tech_week[ $user_id ] = 0;
				}
				$tech_minutes[ $user_id ] += $minutes;
				$tech_names[ $user_id ] = $tr['tech_name'] ?: 'User ' . $user_id;
				if ( empty( $tech_last_entry[ $user_id ] ) || $end > $tech_last_entry[ $user_id ] ) {
					$tech_last_entry[ $user_id ] = $end;
				}
				if ( $this->is_today_gmt( $start ) ) {
					$tech_today[ $user_id ] += $minutes;
				}
				if ( $this->is_this_week_gmt( $start ) ) {
					$tech_week[ $user_id ] += $minutes;
				}
				if ( empty( $tr['end_ts'] ) && 'active' === (string) $tr['state'] ) {
					$open_timers[] = array(
						'job_id' => $job_id,
						'user_id' => $user_id,
						'tech' => $tech_names[ $user_id ],
						'minutes' => $minutes,
						'start_ts' => $start,
					);
					if ( $minutes >= 240 ) {
						$open_timer_jobs[ $job_id ] = true;
					}
				}
			}
		}

		$status_counts = array();
		$active_rows = array();
		$total_est = 0;
		$total_logged = 0;
		$jobs_with_estimate = 0;
		$jobs_with_logged = 0;
		$zero_time_completions = 0;
		$over_estimate = 0;
		$at_risk = 0;
		$assigned = array();
		$block_reason_counts = array();
		$blockers = array();
		$jobs = array();
		$labor_capture_watchlist = array();
		$diagnostic_jobs = array();
		$diagnostic_techs = array();

		foreach ( $rows as $row ) {
			$status = Slate_Ops_Statuses::normalize( (string) $row['status'] );
			$status_counts[ $status ] = ( $status_counts[ $status ] ?? 0 ) + 1;
			$is_active = in_array( $status, $active_statuses, true );
			$job_id = (int) $row['job_id'];
			$est = (int) $row['estimated_minutes'];
			$logged = isset( $job_minutes[ $job_id ] ) ? (int) $job_minutes[ $job_id ] : ( (int) $row['cached_approved'] + (int) $row['cached_pending'] );
			$variance = $logged - $est;
			$used = $est > 0 ? (int) round( $logged / $est * 100 ) : 0;
			$lead = (string) $row['assigned_name'];
			$customer = $row['customer_name'] ?: $row['dealer_name'] ?: 'Customer';
			$reason = $this->risk_reason( $status, $est, $logged, (string) $row['block_reason'], (string) $row['block_note'] );
			$risk = $this->job_risk( $status, $est, $logged, $reason );
			$assigned_user_id = (int) $row['assigned_user_id'];

			if ( $is_active ) {
				$active_rows[] = $row;
				$total_est += $est;
				$total_logged += $logged;
				if ( $est > 0 ) {
					$jobs_with_estimate++;
				}
				if ( $logged > 0 ) {
					$jobs_with_logged++;
				}
				if ( $logged > $est && $est > 0 ) {
					$over_estimate++;
				}
				if ( in_array( $risk, array( 'crit', 'watch' ), true ) ) {
					$at_risk++;
				}
			} elseif ( Slate_Ops_Statuses::COMPLETE === $status && $logged <= 0 ) {
				$zero_time_completions++;
			}

			if ( $assigned_user_id ) {
				$uid = $assigned_user_id;
				if ( ! isset( $assigned[ $uid ] ) ) {
					$assigned[ $uid ] = array(
						'user_id' => $uid,
						'name' => $lead ?: 'User ' . $uid,
						'assigned' => 0,
						'active' => 0,
						'est' => 0,
					);
				}
				$assigned[ $uid ]['assigned']++;
				if ( $is_active ) {
					$assigned[ $uid ]['active']++;
					$assigned[ $uid ]['est'] += $est;
				}
				$diagnostic_techs[ $uid ] = $lead ?: 'User ' . $uid;
			}

			if ( Slate_Ops_Statuses::BLOCKED === $status || $row['block_reason'] || $row['block_note'] ) {
				$block_reason = $this->block_reason_label( (string) $row['block_reason'], (string) $row['block_note'] );
				$block_reason_counts[ $block_reason ] = ( $block_reason_counts[ $block_reason ] ?? 0 ) + 1;
				$days = $this->days_since( $row['status_updated_at'] ?: ( $row['updated_at'] ?: $row['created_at'] ) );
				$blockers[] = array(
					'so' => $row['so_number'] ?: ( 'Job ' . $job_id ),
					'cust' => $customer,
					'reason' => $block_reason,
					'dept' => $this->block_department( $block_reason ),
					'owner' => $row['block_owner'] ?: ( $lead ?: null ),
					'days' => $days,
					'next' => $row['block_owner'] || $lead ? 'Follow up' : 'Assign owner',
					'sev' => $days >= 4 ? 'crit' : ( $days >= 2 ? 'watch' : 'normal' ),
				);
			}

			$jobs[] = array(
				'so' => $row['so_number'] ?: ( 'Job ' . $job_id ),
				'cust' => $customer,
				'status' => Slate_Ops_Statuses::label( $status ),
				'lead' => $lead ?: null,
				'est' => self::minutes_label( $est ),
				'logged' => self::minutes_label( $logged ),
				'variance' => self::signed_minutes_label( $variance ),
				'used' => $used,
				'risk' => $risk,
				'reason' => $reason,
			);

			if ( $is_active && ( $logged <= 0 || $est <= 0 || ! empty( $reason ) ) ) {
				$labor_capture_watchlist[] = array(
					'so' => $row['so_number'] ?: ( 'Job ' . $job_id ),
					'cust' => $customer,
					'tech' => $lead ?: null,
					'issue' => $this->labor_issue( $status, $est, $logged, $reason ),
					'kind' => $logged <= 0 ? 'crit' : ( $est <= 0 ? 'warn' : $risk ),
					'last' => isset( $job_last_entry[ $job_id ] ) ? $this->last_label( $job_last_entry[ $job_id ] ) : '-',
					'action' => $logged <= 0 ? 'Verify clock-in' : ( $est <= 0 ? 'Add estimate' : 'Review with lead' ),
					'tech_state' => $risk,
				);
			}

			if ( $is_active || ( Slate_Ops_Statuses::COMPLETE === $status && $logged <= 0 ) ) {
				$diag_issue = $this->diagnostic_issue( $status, $assigned_user_id, $est, $logged, ! empty( $open_timer_jobs[ $job_id ] ) );
				$diagnostic_jobs[] = array(
					'job_id' => $job_id,
					'so' => $row['so_number'] ?: ( 'Job ' . $job_id ),
					'cust' => $customer,
					'status' => Slate_Ops_Statuses::label( $status ),
					'tech_id' => $assigned_user_id,
					'tech' => $lead ?: 'Unassigned',
					'est_minutes' => $est,
					'logged_minutes' => $logged,
					'variance_minutes' => $variance,
					'capture' => $est > 0 ? (int) round( $logged / $est * 100 ) : 0,
					'last' => isset( $job_last_entry[ $job_id ] ) ? $this->last_label( $job_last_entry[ $job_id ] ) : '-',
					'issue_key' => $diag_issue['key'],
					'issue' => $diag_issue['label'],
					'risk' => $diag_issue['risk'],
					'action' => $diag_issue['action'],
				);
			}
		}

		$techs = $this->build_techs( $assigned, $tech_names, $tech_minutes, $tech_today, $tech_week, $tech_last_entry );
		$active_count = count( $active_rows );
		$missing_time = max( 0, $active_count - $jobs_with_logged );
		$missing_estimate = max( 0, $active_count - $jobs_with_estimate );
		$labor_capture_pct = $active_count > 0 ? (int) round( $jobs_with_logged / $active_count * 100 ) : 0;
		$estimate_coverage = $active_count > 0 ? (int) round( $jobs_with_estimate / $active_count * 100 ) : 0;
		$open_over_four = array_values( array_filter( $open_timers, function ( $timer ) {
			return (int) $timer['minutes'] >= 240;
		} ) );

		usort( $jobs, array( $this, 'sort_jobs_by_risk' ) );
		usort( $blockers, function ( $a, $b ) {
			return (int) $b['days'] <=> (int) $a['days'];
		} );

		$readout = $this->build_readout( $active_count, $jobs_with_logged, $labor_capture_pct, $status_counts, count( $blockers ), count( $open_over_four ) );
		$trust = $this->build_labor_trust( $estimate_coverage, $labor_capture_pct, $zero_time_completions, count( $open_over_four ) );
		$blocker_reasons = $this->build_blocker_reasons( $block_reason_counts );
		$labor_diagnostics = $this->build_labor_diagnostics( $diagnostic_jobs, $diagnostic_techs );

		$this->summary = array(
			'overview' => array(
				'active_jobs' => $active_count,
				'active_breakdown' => $this->active_breakdown( $status_counts ),
				'in_progress' => $status_counts[ Slate_Ops_Statuses::IN_PROGRESS ] ?? 0,
				'pending_qc' => $status_counts[ Slate_Ops_Statuses::QC ] ?? 0,
				'ready_for_pickup' => $status_counts[ Slate_Ops_Statuses::AWAITING_PICKUP ] ?? 0,
				'labor_capture_pct' => $labor_capture_pct,
				'labor_capture_help' => $missing_time . ' jobs have no logged time',
				'variance' => self::signed_minutes_label( $total_logged - $total_est ),
				'blocked' => count( $blockers ),
				'blocked_breakdown' => $this->blocked_breakdown( $blockers ),
				'open_timers' => count( $open_over_four ),
				'open_timer_help' => $open_over_four ? $open_over_four[0]['tech'] . ' · ' . self::minutes_label( $open_over_four[0]['minutes'] ) : 'No timers over 4h',
			),
			'readout' => $readout,
			'labor_watchlist' => $this->build_labor_watchlist( $missing_time, $over_estimate, $missing_estimate, count( $open_over_four ), $techs, $zero_time_completions ),
			'production_watchlist' => $this->build_production_watchlist( $status_counts, $blockers ),
			'tech_kpis' => array(
				'techs_active' => count( $techs ),
				'logged_today' => self::minutes_label( array_sum( $tech_today ) ),
				'logged_week' => self::minutes_label( array_sum( $tech_week ) ),
				'no_time_today' => count( array_filter( $techs, function ( $t ) {
					return '0h 0m' === $t['today'];
				} ) ),
				'no_time_help' => $this->no_time_today_help( $techs ),
			),
			'techs' => $techs,
			'job_kpis' => array(
				'total_jobs' => count( $jobs ),
				'over_estimate' => $over_estimate,
				'missing_time' => $missing_time,
				'at_risk' => $at_risk,
			),
			'jobs' => $jobs,
			'labor_kpis' => array(
				'jobs_with_estimate' => $jobs_with_estimate,
				'jobs_missing_estimate' => $missing_estimate,
				'jobs_with_logged' => $jobs_with_logged,
				'jobs_no_logged' => $missing_time,
				'total_raw_logged' => self::minutes_label( $total_logged ),
				'period_label' => $period['label'],
				'estimate_coverage' => $estimate_coverage,
				'open_timers' => count( $open_over_four ),
				'zero_time_completions' => $zero_time_completions,
			),
			'labor_trust' => $trust,
			'labor_capture_watchlist' => array_slice( $labor_capture_watchlist, 0, 50 ),
			'labor_diagnostics' => $labor_diagnostics,
			'bottleneck_kpis' => array(
				'ready_for_build' => $status_counts[ Slate_Ops_Statuses::READY_FOR_BUILD ] ?? 0,
				'scheduled' => $status_counts[ Slate_Ops_Statuses::SCHEDULED ] ?? 0,
				'in_progress' => $status_counts[ Slate_Ops_Statuses::IN_PROGRESS ] ?? 0,
				'qc' => $status_counts[ Slate_Ops_Statuses::QC ] ?? 0,
				'blocked' => count( $blockers ),
				'blocked_breakdown' => $this->blocked_breakdown( $blockers ),
				'on_hold' => $status_counts[ Slate_Ops_Statuses::ON_HOLD ] ?? 0,
				'cancelled' => $status_counts[ Slate_Ops_Statuses::CANCELLED ] ?? 0,
				'avg_block_age' => $this->average_block_age( $blockers ),
				'avg_age_help' => 'Live from job blocker state',
				'period_label' => $period['label'],
			),
			'blocker_reasons' => $blocker_reasons,
			'blockers' => array_slice( $blockers, 0, 50 ),
		);

		return $this->summary;
	}

	private function empty_summary() {
		return array(
			'overview' => array( 'active_jobs' => 0, 'active_breakdown' => 'No jobs', 'in_progress' => 0, 'pending_qc' => 0, 'ready_for_pickup' => 0, 'labor_capture_pct' => 0, 'labor_capture_help' => 'No job data found', 'variance' => '0h 0m', 'blocked' => 0, 'blocked_breakdown' => 'No blockers', 'open_timers' => 0, 'open_timer_help' => 'No open timers' ),
			'readout' => array(),
			'labor_watchlist' => array(),
			'production_watchlist' => array(),
			'tech_kpis' => array( 'techs_active' => 0, 'logged_today' => '0h 0m', 'logged_week' => '0h 0m', 'no_time_today' => 0, 'no_time_help' => 'No active techs' ),
			'techs' => array(),
			'job_kpis' => array( 'total_jobs' => 0, 'over_estimate' => 0, 'missing_time' => 0, 'at_risk' => 0 ),
			'jobs' => array(),
			'labor_kpis' => array( 'jobs_with_estimate' => 0, 'jobs_missing_estimate' => 0, 'jobs_with_logged' => 0, 'jobs_no_logged' => 0, 'total_raw_logged' => '0h 0m', 'estimate_coverage' => 0, 'open_timers' => 0, 'zero_time_completions' => 0 ),
			'labor_trust' => array( 'score' => 0, 'tier' => 'NO DATA', 'desc' => 'No live Slate Ops job data was found.', 'factors' => array() ),
			'labor_capture_watchlist' => array(),
			'labor_diagnostics' => $this->build_labor_diagnostics( array(), array() ),
			'bottleneck_kpis' => array( 'ready_for_build' => 0, 'scheduled' => 0, 'in_progress' => 0, 'qc' => 0, 'blocked' => 0, 'on_hold' => 0, 'cancelled' => 0, 'avg_block_age' => '0.0', 'avg_age_help' => 'No blockers' ),
			'blocker_reasons' => array(),
			'blockers' => array(),
		);
	}

	/* -----------------------------------------------------------------
	 * Builders and helpers
	 * --------------------------------------------------------------- */

	private function fetch_jobs( $jobs_t, $active_statuses, $period ) {
		global $wpdb;

		$status_placeholders = implode( ',', array_fill( 0, count( $active_statuses ), '%s' ) );
		$params = array();

		$where = 'j.archived_at IS NULL';
		if ( ! empty( $period['cutoff'] ) ) {
			$where .= " AND (j.status IN ($status_placeholders)
				OR COALESCE(j.actual_completed_at, j.status_updated_at, j.updated_at, j.created_at) >= %s)";
			$params = array_merge( $params, $active_statuses );
			$params[] = $period['cutoff'];
		}

		$params[] = 500;

		$sql = "SELECT
				j.job_id,
				j.so_number,
				j.customer_name,
				j.dealer_name,
				j.status,
				j.status_updated_at,
				j.updated_at,
				j.created_at,
				j.assigned_user_id,
				COALESCE(u.display_name, '') AS assigned_name,
				COALESCE(j.estimated_minutes, 0) AS estimated_minutes,
				COALESCE(j.actual_minutes_approved, 0) AS cached_approved,
				COALESCE(j.actual_minutes_pending, 0) AS cached_pending,
				j.block_reason,
				j.block_note,
				j.block_owner
			FROM $jobs_t j
			LEFT JOIN {$wpdb->users} u ON u.ID = j.assigned_user_id
			WHERE $where
			ORDER BY j.status ASC, j.priority ASC, j.updated_at DESC
			LIMIT %d";

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) ?: array();
	}

	private function fetch_time_rows( $segs_t, $period ) {
		global $wpdb;

		$where = "s.approval_status != 'voided'";
		$params = array();
		if ( ! empty( $period['cutoff'] ) ) {
			$where .= " AND (s.start_ts >= %s OR (s.end_ts IS NULL AND s.state = 'active'))";
			$params[] = $period['cutoff'];
		}

		$sql = "SELECT
				s.job_id,
				s.user_id,
				COALESCE(u.display_name, '') AS tech_name,
				s.start_ts,
				s.end_ts,
				s.state,
				s.approval_status,
				TIMESTAMPDIFF(MINUTE, s.start_ts, COALESCE(s.end_ts, UTC_TIMESTAMP())) AS minutes
			FROM $segs_t s
			LEFT JOIN {$wpdb->users} u ON u.ID = s.user_id
			WHERE $where";

		if ( $params ) {
			return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) ?: array();
		}

		return $wpdb->get_results( $sql, ARRAY_A ) ?: array();
	}

	private function build_techs( $assigned, $tech_names, $tech_minutes, $tech_today, $tech_week, $tech_last_entry ) {
		$uids = array_unique( array_merge( array_keys( $assigned ), array_keys( $tech_minutes ) ) );
		$out = array();
		foreach ( $uids as $uid ) {
			$uid = (int) $uid;
			if ( $uid <= 0 ) {
				continue;
			}
			$a = $assigned[ $uid ] ?? array( 'name' => $tech_names[ $uid ] ?? ( 'User ' . $uid ), 'assigned' => 0, 'active' => 0, 'est' => 0 );
			$logged = (int) ( $tech_minutes[ $uid ] ?? 0 );
			$today = (int) ( $tech_today[ $uid ] ?? 0 );
			$week = (int) ( $tech_week[ $uid ] ?? 0 );
			$est = (int) ( $a['est'] ?? 0 );
			$capture = $est > 0 ? (int) round( $logged / $est * 100 ) : 0;
			$flags = array();
			if ( $today <= 0 && (int) $a['active'] > 0 ) {
				$flags[] = array( 'text' => 'No time today', 'kind' => 'warn' );
			}
			if ( $week <= 0 && (int) $a['active'] > 0 ) {
				$flags[] = array( 'text' => 'No time this week', 'kind' => 'crit' );
			}
			if ( $est > 0 && $logged > $est ) {
				$flags[] = array( 'text' => 'Over estimate', 'kind' => 'warn' );
			}
			$out[] = array(
				'name' => $a['name'],
				'state' => $this->tech_state( $flags, $capture, (int) $a['active'] ),
				'assigned' => (int) $a['assigned'],
				'active' => (int) $a['active'],
				'today' => self::minutes_label( $today ),
				'week' => self::minutes_label( $week ),
				'est' => self::minutes_label( $est ),
				'logged' => self::minutes_label( $logged ),
				'variance' => self::signed_minutes_label( $logged - $est ),
				'capture' => $capture,
				'capture_flag' => $capture <= 0 && (int) $a['active'] > 0 ? 'crit' : ( $capture < 60 ? 'warn' : 'ok' ),
				'flags' => $flags,
				'note' => isset( $tech_last_entry[ $uid ] ) ? 'Last entry ' . $this->last_label( $tech_last_entry[ $uid ] ) : '',
			);
		}
		usort( $out, function ( $a, $b ) {
			return strcasecmp( $a['name'], $b['name'] );
		} );
		return $out;
	}

	private function build_readout( $active_count, $jobs_with_logged, $capture_pct, $status_counts, $blocked_count, $open_timer_count ) {
		$wip = ( $status_counts[ Slate_Ops_Statuses::IN_PROGRESS ] ?? 0 ) + ( $status_counts[ Slate_Ops_Statuses::QC ] ?? 0 );
		return array(
			array(
				'severity' => $capture_pct < 50 ? 'crit' : ( $capture_pct < 75 ? 'warn' : 'ok' ),
				'label' => 'Labor data quality',
				'body' => '<b>' . (int) $jobs_with_logged . ' of ' . (int) $active_count . ' active jobs</b> have logged technician time.',
				'stats' => array(
					array( 'label' => 'Trust', 'value' => $capture_pct >= 75 ? 'Usable' : 'Needs review' ),
					array( 'label' => 'Coverage', 'value' => (int) $capture_pct . '%' ),
				),
			),
			array(
				'severity' => $wip > 8 ? 'warn' : 'ok',
				'label' => 'Schedule risk',
				'body' => '<b>' . (int) $wip . ' jobs</b> are in progress or ready to close.',
				'stats' => array(
					array( 'label' => 'WIP', 'value' => (string) $wip ),
					array( 'label' => 'Open timers', 'value' => (string) $open_timer_count ),
				),
			),
			array(
				'severity' => $blocked_count > 0 ? 'crit' : 'ok',
				'label' => 'Bottleneck status',
				'body' => '<b>' . (int) $blocked_count . ' jobs</b> are blocked or carrying blocker notes.',
				'stats' => array(
					array( 'label' => 'Blocked', 'value' => (string) $blocked_count ),
					array( 'label' => 'Owners', 'value' => 'Review' ),
				),
			),
		);
	}

	private function build_labor_watchlist( $missing_time, $over_estimate, $missing_estimate, $open_timers, $techs, $zero_time_completions ) {
		$no_time_today = count( array_filter( $techs, function ( $t ) {
			return '0h 0m' === $t['today'] && (int) $t['active'] > 0;
		} ) );
		return array(
			array( 'issue' => 'Jobs with no logged time', 'count' => $missing_time, 'risk' => $missing_time > 0 ? 'crit' : 'watch', 'action' => 'Audit clock-in flow' ),
			array( 'issue' => 'Jobs over estimate', 'count' => $over_estimate, 'risk' => $over_estimate > 0 ? 'watch' : 'watch', 'action' => 'Review with lead' ),
			array( 'issue' => 'Jobs missing estimates', 'count' => $missing_estimate, 'risk' => $missing_estimate > 0 ? 'watch' : 'watch', 'action' => 'Add estimate' ),
			array( 'issue' => 'Open timers (>4h)', 'count' => $open_timers, 'risk' => $open_timers > 0 ? 'warn' : 'watch', 'action' => 'Confirm with tech' ),
			array( 'issue' => 'Techs with no time today', 'count' => $no_time_today, 'risk' => $no_time_today > 0 ? 'warn' : 'watch', 'action' => 'Direct check-in' ),
			array( 'issue' => 'Jobs complete with 0 logged time', 'count' => $zero_time_completions, 'risk' => $zero_time_completions > 0 ? 'crit' : 'watch', 'action' => 'Backfill or void' ),
		);
	}

	private function build_production_watchlist( $status_counts, $blockers ) {
		return array(
			array( 'area' => 'Blocked jobs', 'count' => count( $blockers ), 'status' => count( $blockers ) > 0 ? 'crit' : 'ok', 'owner' => 'Shop lead' ),
			array( 'area' => 'QC aging > 1 day', 'count' => $status_counts[ Slate_Ops_Statuses::QC ] ?? 0, 'status' => 'watch', 'owner' => 'Supervisor' ),
			array( 'area' => 'Jobs ready to build', 'count' => $status_counts[ Slate_Ops_Statuses::READY_FOR_BUILD ] ?? 0, 'status' => 'ok', 'owner' => 'Floor' ),
			array( 'area' => 'Jobs ready for pickup', 'count' => $status_counts[ Slate_Ops_Statuses::AWAITING_PICKUP ] ?? 0, 'status' => 'ok', 'owner' => 'CS' ),
			array( 'area' => 'Schedule risk (WIP)', 'count' => ( $status_counts[ Slate_Ops_Statuses::IN_PROGRESS ] ?? 0 ) + ( $status_counts[ Slate_Ops_Statuses::QC ] ?? 0 ), 'status' => 'warn', 'owner' => 'Ops' ),
			array( 'area' => 'On hold without owner', 'count' => $status_counts[ Slate_Ops_Statuses::ON_HOLD ] ?? 0, 'status' => 'warn', 'owner' => null ),
		);
	}

	private function build_labor_trust( $estimate_coverage, $logged_coverage, $zero_time_completions, $open_timers ) {
		$zero_score = $zero_time_completions > 0 ? max( 0, 100 - ( $zero_time_completions * 20 ) ) : 100;
		$timer_score = $open_timers > 0 ? max( 0, 100 - ( $open_timers * 15 ) ) : 100;
		$score = (int) round( ( $estimate_coverage + $logged_coverage + $zero_score + $timer_score ) / 4 );
		$tier = $score >= 80 ? 'HIGH' : ( $score >= 60 ? 'MEDIUM' : 'LOW' );
		return array(
			'score' => $score,
			'tier' => $tier,
			'desc' => $score >= 75 ? 'Labor capture is usable for directional job costing.' : 'Labor capture needs review before leadership relies on job costing.',
			'factors' => array(
				array( 'label' => 'Estimate coverage', 'value' => $estimate_coverage, 'kind' => $estimate_coverage >= 75 ? 'ok' : 'warn' ),
				array( 'label' => 'Logged time coverage', 'value' => $logged_coverage, 'kind' => $logged_coverage >= 75 ? 'ok' : 'crit' ),
				array( 'label' => 'Zero-time completions OK', 'value' => $zero_score, 'kind' => $zero_score >= 90 ? 'ok' : 'crit' ),
				array( 'label' => 'Open-timer hygiene', 'value' => $timer_score, 'kind' => $timer_score >= 90 ? 'ok' : 'warn' ),
			),
		);
	}

	private function build_labor_diagnostics( $jobs, $tech_options ) {
		$filters = $this->diagnostic_filters();
		$issue_options = $this->diagnostic_issue_options();
		$filtered = array();
		$issue_counts = array_fill_keys( array_keys( $issue_options ), 0 );
		$tech_rollup = array();

		foreach ( $jobs as $job ) {
			if ( isset( $issue_counts[ $job['issue_key'] ] ) ) {
				$issue_counts[ $job['issue_key'] ]++;
			}

			if ( '' !== $filters['job'] ) {
				$haystack = strtolower( $job['so'] . ' ' . $job['cust'] . ' ' . $job['job_id'] );
				if ( false === strpos( $haystack, strtolower( $filters['job'] ) ) ) {
					continue;
				}
			}
			if ( 'all' !== $filters['issue'] && $job['issue_key'] !== $filters['issue'] ) {
				continue;
			}
			if ( 'all' !== $filters['tech'] ) {
				if ( '0' === $filters['tech'] && (int) $job['tech_id'] !== 0 ) {
					continue;
				}
				if ( '0' !== $filters['tech'] && (int) $job['tech_id'] !== (int) $filters['tech'] ) {
					continue;
				}
			}

			$filtered[] = $job;
			$tid = (int) $job['tech_id'];
			if ( ! isset( $tech_rollup[ $tid ] ) ) {
				$tech_rollup[ $tid ] = array(
					'tech_id' => $tid,
					'tech' => $job['tech'],
					'jobs' => 0,
					'with_time' => 0,
					'no_time' => 0,
					'missing_estimate' => 0,
					'open_timer' => 0,
					'est_minutes' => 0,
					'logged_minutes' => 0,
					'main_issue' => 'On track',
					'main_risk' => 'ok',
				);
			}
			$tech_rollup[ $tid ]['jobs']++;
			$tech_rollup[ $tid ]['est_minutes'] += (int) $job['est_minutes'];
			$tech_rollup[ $tid ]['logged_minutes'] += (int) $job['logged_minutes'];
			if ( (int) $job['logged_minutes'] > 0 ) {
				$tech_rollup[ $tid ]['with_time']++;
			} else {
				$tech_rollup[ $tid ]['no_time']++;
			}
			if ( 'missing_estimate' === $job['issue_key'] ) {
				$tech_rollup[ $tid ]['missing_estimate']++;
			}
			if ( 'open_timer' === $job['issue_key'] ) {
				$tech_rollup[ $tid ]['open_timer']++;
			}
			if ( $this->diagnostic_risk_rank( $job['risk'] ) < $this->diagnostic_risk_rank( $tech_rollup[ $tid ]['main_risk'] ) ) {
				$tech_rollup[ $tid ]['main_issue'] = $job['issue'];
				$tech_rollup[ $tid ]['main_risk'] = $job['risk'];
			}
		}

		foreach ( $tech_rollup as &$row ) {
			$row['capture'] = $row['est_minutes'] > 0 ? (int) round( $row['logged_minutes'] / $row['est_minutes'] * 100 ) : 0;
			$row['est'] = self::minutes_label( $row['est_minutes'] );
			$row['logged'] = self::minutes_label( $row['logged_minutes'] );
			$row['variance'] = self::signed_minutes_label( $row['logged_minutes'] - $row['est_minutes'] );
		}
		unset( $row );

		usort( $filtered, array( $this, 'sort_diagnostic_jobs' ) );
		usort( $tech_rollup, function ( $a, $b ) {
			return (int) $b['no_time'] === (int) $a['no_time'] ? strcasecmp( $a['tech'], $b['tech'] ) : ( (int) $b['no_time'] <=> (int) $a['no_time'] );
		} );
		asort( $tech_options, SORT_NATURAL | SORT_FLAG_CASE );

		return array(
			'filters' => $filters,
			'issue_options' => $issue_options,
			'tech_options' => $tech_options,
			'issue_counts' => $issue_counts,
			'summary' => array(
				'jobs' => count( $filtered ),
				'with_time' => count( array_filter( $filtered, function ( $job ) { return (int) $job['logged_minutes'] > 0; } ) ),
				'no_time' => count( array_filter( $filtered, function ( $job ) { return (int) $job['logged_minutes'] <= 0; } ) ),
				'missing_estimate' => count( array_filter( $filtered, function ( $job ) { return (int) $job['est_minutes'] <= 0; } ) ),
				'open_timer' => count( array_filter( $filtered, function ( $job ) { return 'open_timer' === $job['issue_key']; } ) ),
			),
			'by_tech' => array_values( $tech_rollup ),
			'jobs' => array_slice( $filtered, 0, 100 ),
		);
	}

	private function build_blocker_reasons( $counts ) {
		if ( empty( $counts ) ) {
			return array( array( 'label' => 'None', 'count' => 0, 'delta' => '-', 'kind' => '' ) );
		}
		$out = array();
		foreach ( $counts as $label => $count ) {
			$out[] = array(
				'label' => $label,
				'count' => (int) $count,
				'delta' => 'Live',
				'kind' => (int) $count >= 3 ? 'crit' : ( (int) $count >= 1 ? 'warn' : '' ),
			);
		}
		usort( $out, function ( $a, $b ) {
			return (int) $b['count'] <=> (int) $a['count'];
		} );
		return $out;
	}

	private function table_exists( $table ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	private function is_today_gmt( $ts ) {
		return gmdate( 'Y-m-d', strtotime( $ts ) ) === gmdate( 'Y-m-d', strtotime( Slate_Ops_Utils::now_gmt() ) );
	}

	private function is_this_week_gmt( $ts ) {
		return gmdate( 'o-W', strtotime( $ts ) ) === gmdate( 'o-W', strtotime( Slate_Ops_Utils::now_gmt() ) );
	}

	private function days_since( $ts ) {
		if ( ! $ts ) {
			return 0;
		}
		return max( 0, (int) floor( ( strtotime( Slate_Ops_Utils::now_gmt() ) - strtotime( $ts ) ) / DAY_IN_SECONDS ) );
	}

	private function last_label( $ts ) {
		if ( ! $ts ) {
			return '-';
		}
		return human_time_diff( strtotime( $ts ), strtotime( Slate_Ops_Utils::now_gmt() ) ) . ' ago';
	}

	private function active_breakdown( $counts ) {
		return (int) ( $counts[ Slate_Ops_Statuses::READY_FOR_BUILD ] ?? 0 ) . ' ready · '
			. (int) ( $counts[ Slate_Ops_Statuses::SCHEDULED ] ?? 0 ) . ' sched · '
			. (int) ( $counts[ Slate_Ops_Statuses::IN_PROGRESS ] ?? 0 ) . ' WIP · '
			. (int) ( $counts[ Slate_Ops_Statuses::QC ] ?? 0 ) . ' QC';
	}

	private function blocked_breakdown( $blockers ) {
		$crit = count( array_filter( $blockers, function ( $b ) { return 'crit' === $b['sev']; } ) );
		$watch = count( array_filter( $blockers, function ( $b ) { return 'watch' === $b['sev']; } ) );
		$normal = max( 0, count( $blockers ) - $crit - $watch );
		return $crit . ' critical · ' . $watch . ' watch · ' . $normal . ' normal';
	}

	private function no_time_today_help( $techs ) {
		$names = array();
		foreach ( $techs as $t ) {
			if ( '0h 0m' === $t['today'] && (int) $t['active'] > 0 ) {
				$names[] = $t['name'];
			}
		}
		return $names ? implode( ', ', array_slice( $names, 0, 3 ) ) : 'All active techs have time today';
	}

	private function average_block_age( $blockers ) {
		if ( empty( $blockers ) ) {
			return '0.0';
		}
		$total = array_sum( array_map( function ( $b ) {
			return (int) $b['days'];
		}, $blockers ) );
		return number_format_i18n( $total / count( $blockers ), 1 );
	}

	private function job_risk( $status, $est, $logged, $reason ) {
		if ( Slate_Ops_Statuses::BLOCKED === $status ) {
			return 'crit';
		}
		if ( $reason ) {
			return 'watch';
		}
		if ( $est > 0 && $logged > $est ) {
			return 'watch';
		}
		if ( in_array( $status, array( Slate_Ops_Statuses::IN_PROGRESS, Slate_Ops_Statuses::QC ), true ) && $logged <= 0 ) {
			return 'crit';
		}
		return 'ok';
	}

	private function risk_reason( $status, $est, $logged, $block_reason, $block_note ) {
		if ( Slate_Ops_Statuses::BLOCKED === $status || $block_reason || $block_note ) {
			return $this->block_reason_label( $block_reason, $block_note );
		}
		if ( $est > 0 && $logged > $est ) {
			return 'Over estimate';
		}
		if ( $est <= 0 ) {
			return 'Missing estimate';
		}
		return '';
	}

	private function labor_issue( $status, $est, $logged, $reason ) {
		if ( $logged <= 0 ) {
			return 'No logged time';
		}
		if ( $est <= 0 ) {
			return 'Missing estimate';
		}
		return $reason ?: Slate_Ops_Statuses::label( $status );
	}

	private function diagnostic_issue( $status, $assigned_user_id, $est, $logged, $has_open_timer ) {
		if ( Slate_Ops_Statuses::COMPLETE === $status && $logged <= 0 ) {
			return array( 'key' => 'zero_time_complete', 'label' => 'Complete with no time', 'risk' => 'crit', 'action' => 'Backfill or void time' );
		}
		if ( $assigned_user_id <= 0 ) {
			return array( 'key' => 'no_tech', 'label' => 'No assigned tech', 'risk' => 'crit', 'action' => 'Assign a lead tech' );
		}
		if ( $logged <= 0 ) {
			return array( 'key' => 'no_time', 'label' => 'No logged time', 'risk' => 'crit', 'action' => 'Verify clock-in' );
		}
		if ( $est <= 0 ) {
			return array( 'key' => 'missing_estimate', 'label' => 'Missing estimate', 'risk' => 'warn', 'action' => 'Add labor estimate' );
		}
		if ( $has_open_timer ) {
			return array( 'key' => 'open_timer', 'label' => 'Open timer over 4h', 'risk' => 'warn', 'action' => 'Confirm with tech' );
		}
		if ( $logged > $est ) {
			return array( 'key' => 'over_estimate', 'label' => 'Over estimate', 'risk' => 'watch', 'action' => 'Review estimate vs work' );
		}
		return array( 'key' => 'on_track', 'label' => 'On track', 'risk' => 'ok', 'action' => 'No action' );
	}

	private function diagnostic_issue_options() {
		return array(
			'no_tech' => 'No assigned tech',
			'no_time' => 'No logged time',
			'missing_estimate' => 'Missing estimate',
			'open_timer' => 'Open timer over 4h',
			'over_estimate' => 'Over estimate',
			'zero_time_complete' => 'Complete with no time',
			'on_track' => 'On track',
		);
	}

	private function diagnostic_filters() {
		$issue_options = $this->diagnostic_issue_options();
		$issue = isset( $_GET['diag_issue'] ) ? sanitize_key( wp_unslash( $_GET['diag_issue'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'all' !== $issue && ! isset( $issue_options[ $issue ] ) ) {
			$issue = 'all';
		}

		$tech = isset( $_GET['diag_tech'] ) ? sanitize_text_field( wp_unslash( $_GET['diag_tech'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'all' !== $tech && '0' !== $tech ) {
			$tech = (string) max( 0, absint( $tech ) );
		}

		return array(
			'tech' => $tech,
			'issue' => $issue,
			'job' => isset( $_GET['diag_job'] ) ? sanitize_text_field( wp_unslash( $_GET['diag_job'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
	}

	private function diagnostic_risk_rank( $risk ) {
		$order = array( 'crit' => 0, 'warn' => 1, 'watch' => 2, 'ok' => 3 );
		return $order[ $risk ] ?? 4;
	}

	private function sort_diagnostic_jobs( $a, $b ) {
		$ra = $this->diagnostic_risk_rank( $a['risk'] );
		$rb = $this->diagnostic_risk_rank( $b['risk'] );
		return $ra === $rb ? strcmp( $a['so'], $b['so'] ) : ( $ra <=> $rb );
	}

	private function block_reason_label( $reason, $note ) {
		$raw = trim( (string) $reason );
		if ( '' === $raw ) {
			$raw = trim( (string) $note );
		}
		if ( '' === $raw ) {
			return 'Blocked';
		}
		$raw = preg_replace( '/[_-]+/', ' ', $raw );
		return ucwords( strtolower( $raw ) );
	}

	private function block_department( $reason ) {
		$r = strtolower( $reason );
		if ( false !== strpos( $r, 'part' ) ) {
			return 'Parts';
		}
		if ( false !== strpos( $r, 'customer' ) || false !== strpos( $r, 'approval' ) ) {
			return 'CS';
		}
		if ( false !== strpos( $r, 'engineer' ) ) {
			return 'Eng';
		}
		if ( false !== strpos( $r, 'qc' ) || false !== strpos( $r, 'rework' ) ) {
			return 'QC';
		}
		return 'Shop';
	}

	private function tech_state( $flags, $capture, $active ) {
		foreach ( $flags as $f ) {
			if ( 'crit' === $f['kind'] ) {
				return 'crit';
			}
		}
		return $active > 0 && $capture < 60 ? 'warn' : 'ok';
	}

	private function sort_jobs_by_risk( $a, $b ) {
		$order = array( 'crit' => 0, 'watch' => 1, 'ok' => 2 );
		$ra = $order[ $a['risk'] ] ?? 3;
		$rb = $order[ $b['risk'] ] ?? 3;
		return $ra === $rb ? strcmp( $a['so'], $b['so'] ) : ( $ra <=> $rb );
	}

	public static function minutes_label( $minutes ) {
		$minutes = max( 0, (int) $minutes );
		$hours = (int) floor( $minutes / 60 );
		$mins = $minutes % 60;
		return $hours . 'h ' . $mins . 'm';
	}

	public static function signed_minutes_label( $minutes ) {
		$minutes = (int) $minutes;
		$sign = $minutes > 0 ? '+' : ( $minutes < 0 ? '-' : '' );
		return $sign . self::minutes_label( abs( $minutes ) );
	}

	/* -----------------------------------------------------------------
	 * Render helpers — kept here so the template stays declarative.
	 * --------------------------------------------------------------- */

	/** Initials for an avatar token. */
	public static function initials( $name ) {
		if ( ! $name ) {
			return '-';
		}
		$parts = preg_split( '/\s+/', trim( $name ) );
		$out = '';
		foreach ( $parts as $p ) {
			if ( '' !== $p ) {
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
		$max = max( 120, $value );
		$pct = min( 100, ( $value / $max ) * 100 );
		$tgt = min( 100, ( $target / $max ) * 100 );
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

	/** Map a job status label to a tag kind. */
	public static function status_kind( $status ) {
		$labels = array(
			Slate_Ops_Statuses::label( Slate_Ops_Statuses::BLOCKED ) => Slate_Ops_Statuses::BLOCKED,
			Slate_Ops_Statuses::label( Slate_Ops_Statuses::IN_PROGRESS ) => Slate_Ops_Statuses::IN_PROGRESS,
			Slate_Ops_Statuses::label( Slate_Ops_Statuses::QC ) => Slate_Ops_Statuses::QC,
			Slate_Ops_Statuses::label( Slate_Ops_Statuses::AWAITING_PICKUP ) => Slate_Ops_Statuses::AWAITING_PICKUP,
			Slate_Ops_Statuses::label( Slate_Ops_Statuses::COMPLETE ) => Slate_Ops_Statuses::COMPLETE,
			Slate_Ops_Statuses::label( Slate_Ops_Statuses::READY_FOR_BUILD ) => Slate_Ops_Statuses::READY_FOR_BUILD,
			Slate_Ops_Statuses::label( Slate_Ops_Statuses::SCHEDULED ) => Slate_Ops_Statuses::SCHEDULED,
			Slate_Ops_Statuses::label( Slate_Ops_Statuses::INTAKE ) => Slate_Ops_Statuses::INTAKE,
			Slate_Ops_Statuses::label( Slate_Ops_Statuses::ON_HOLD ) => Slate_Ops_Statuses::ON_HOLD,
			Slate_Ops_Statuses::label( Slate_Ops_Statuses::CANCELLED ) => Slate_Ops_Statuses::CANCELLED,
		);
		if ( isset( $labels[ $status ] ) ) {
			$canonical = $labels[ $status ];
		} else {
			$canonical = Slate_Ops_Statuses::normalize( strtoupper( str_replace( array( ' ', '-' ), '_', $status ) ) );
		}
		$m = array(
			Slate_Ops_Statuses::BLOCKED => 'crit',
			Slate_Ops_Statuses::IN_PROGRESS => 'ok',
			Slate_Ops_Statuses::QC => 'watch',
			Slate_Ops_Statuses::AWAITING_PICKUP => 'ok',
			Slate_Ops_Statuses::COMPLETE => 'muted',
			Slate_Ops_Statuses::READY_FOR_BUILD => 'normal',
			Slate_Ops_Statuses::SCHEDULED => 'normal',
			Slate_Ops_Statuses::INTAKE => 'normal',
			Slate_Ops_Statuses::ON_HOLD => 'watch',
			Slate_Ops_Statuses::CANCELLED => 'muted',
		);
		return isset( $m[ $canonical ] ) ? $m[ $canonical ] : 'normal';
	}
}
