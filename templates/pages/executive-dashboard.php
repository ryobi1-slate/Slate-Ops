<?php
/**
 * Slate Ops · Executive Dashboard
 *
 * Content-only page template for the Executive dashboard.
 * Outputs the page body inside `<div class="so-exec">`.
 * Outer document, header, sidebar, fonts, and global CSS reset are
 * provided by the Ops shell. No <html>, <head>, or <body> here.
 *
 * Intended assets:
 * - assets/css/executive-dashboard.css
 * - assets/js/executive-dashboard.js
 *
 * @package Slate_Ops
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$exec = Slate_Ops_Executive::instance();
$period = $exec->get_period();
$period_options = Slate_Ops_Executive::period_options();
$ov   = $exec->get_overview_kpis();
$read = $exec->get_executive_readout();
$lwl  = $exec->get_labor_watchlist();
$pwl  = $exec->get_production_watchlist();

$tk     = $exec->get_tech_kpis();
$techs  = $exec->get_techs();

$jk     = $exec->get_job_kpis();
$jobs   = $exec->get_jobs();
$jobs_total = count( $jobs );

$lk     = $exec->get_labor_kpis();
$trust  = $exec->get_labor_trust();
$lcwl   = $exec->get_labor_capture_watchlist();
$diag   = $exec->get_labor_diagnostics();

$bk     = $exec->get_bottleneck_kpis();
$br     = $exec->get_blocker_reasons();
$blocks = $exec->get_blockers();
$allowed_tabs = array( 'overview', 'tech', 'jobs', 'labor', 'diagnostics', 'blocks' );
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
if ( ! in_array( $active_tab, $allowed_tabs, true ) ) {
	$active_tab = 'overview';
}
$tech_risk_counts = array( 'critical' => 0, 'warning' => 0, 'watch' => 0, 'ok' => 0 );
$tech_attention_count = 0;
$tech_open_timer_count = 0;
foreach ( $techs as $tech_summary ) {
	$risk_key = isset( $tech_summary['risk_key'] ) ? $tech_summary['risk_key'] : 'ok';
	if ( ! isset( $tech_risk_counts[ $risk_key ] ) ) {
		$risk_key = 'ok';
	}
	$tech_risk_counts[ $risk_key ]++;
	if ( in_array( $risk_key, array( 'critical', 'warning' ), true ) ) {
		$tech_attention_count++;
	}
	$tech_open_timer_count += (int) ( $tech_summary['open_timer_count'] ?? 0 );
}
?>
<div class="so-exec">
	<div class="page-wrap">

		<header class="page-head">
			<div>
				<div class="eyebrow">Executive · Production Control</div>
				<h1 class="page-title">Labor &amp; Job Performance</h1>
				<div class="page-sub">Job costing, performance tracking, and labor capture · Shop PDX-01</div>
			</div>
			<div class="page-meta">
				<form class="period-filter" method="get" action="<?php echo esc_url( home_url( '/ops/exec' ) ); ?>">
					<input type="hidden" name="tab" value="<?php echo esc_attr( $active_tab ); ?>" data-period-tab />
					<label for="so-exec-period">Period</label>
					<select id="so-exec-period" name="period" data-period-select>
						<?php foreach ( $period_options as $key => $option ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $period['key'], $key ); ?>><?php echo esc_html( $option['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="submit">Apply</button>
				</form>
				<span class="sep">·</span>
				<span>WIP <b><?php echo (int) $bk['in_progress'] + (int) $bk['qc']; ?></b></span>
				<span class="sep">·</span>
				<span class="crit">Capture <b><?php echo (int) $ov['labor_capture_pct']; ?>%</b></span>
				<span class="sep">·</span>
				<span class="crit">Blocked <b><?php echo (int) $ov['blocked']; ?></b></span>
			</div>
		</header>

		<nav class="tabs" role="tablist">
			<button class="tab <?php echo $active_tab === 'overview' ? 'active' : ''; ?>" data-tab="overview" role="tab" type="button" aria-controls="pane-overview" aria-selected="<?php echo $active_tab === 'overview' ? 'true' : 'false'; ?>" tabindex="<?php echo $active_tab === 'overview' ? '0' : '-1'; ?>">
				<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="2" y="2" width="5" height="5"/><rect x="9" y="2" width="5" height="5"/><rect x="2" y="9" width="5" height="5"/><rect x="9" y="9" width="5" height="5"/></svg>
				<span>Overview</span>
			</button>
			<button class="tab <?php echo $active_tab === 'tech' ? 'active' : ''; ?>" data-tab="tech" role="tab" type="button" aria-controls="pane-tech" aria-selected="<?php echo $active_tab === 'tech' ? 'true' : 'false'; ?>" tabindex="<?php echo $active_tab === 'tech' ? '0' : '-1'; ?>">
				<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"><circle cx="8" cy="6" r="2.4"/><path d="M3 14c0-2.5 2.2-4.2 5-4.2S13 11.5 13 14"/></svg>
				<span>Tech Overview</span>
				<span class="count"><?php echo (int) $tk['techs_active']; ?></span>
			</button>
			<button class="tab <?php echo $active_tab === 'jobs' ? 'active' : ''; ?>" data-tab="jobs" role="tab" type="button" aria-controls="pane-jobs" aria-selected="<?php echo $active_tab === 'jobs' ? 'true' : 'false'; ?>" tabindex="<?php echo $active_tab === 'jobs' ? '0' : '-1'; ?>">
				<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M11 3.2 13.2 1l1.6 1.6L12.6 5l.7.7-2.1 2.1-1.4-1.4-5 5L3 9.8l5-5L6.6 3.4l2.1-2.1Z"/></svg>
				<span>Job Performance</span>
				<span class="count"><?php echo (int) $jk['total_jobs']; ?></span>
			</button>
			<button class="tab <?php echo $active_tab === 'labor' ? 'active' : ''; ?>" data-tab="labor" role="tab" type="button" aria-controls="pane-labor" aria-selected="<?php echo $active_tab === 'labor' ? 'true' : 'false'; ?>" tabindex="<?php echo $active_tab === 'labor' ? '0' : '-1'; ?>">
				<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"><circle cx="8" cy="9" r="5"/><path d="M8 6.5V9l1.6 1.2"/><path d="M6.5 1.5h3M8 1.5v2"/></svg>
				<span>Labor Capture</span>
				<span class="count"><?php echo (int) $lk['jobs_no_logged']; ?></span>
			</button>
			<button class="tab <?php echo $active_tab === 'diagnostics' ? 'active' : ''; ?>" data-tab="diagnostics" role="tab" type="button" aria-controls="pane-diagnostics" aria-selected="<?php echo $active_tab === 'diagnostics' ? 'true' : 'false'; ?>" tabindex="<?php echo $active_tab === 'diagnostics' ? '0' : '-1'; ?>">
				<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M3 3h10M3 8h10M3 13h10"/><circle cx="6" cy="3" r="1.4" fill="currentColor"/><circle cx="10" cy="8" r="1.4" fill="currentColor"/><circle cx="7.5" cy="13" r="1.4" fill="currentColor"/></svg>
				<span>Labor Diagnostics</span>
				<span class="count"><?php echo (int) $diag['summary']['no_time']; ?></span>
			</button>
			<button class="tab <?php echo $active_tab === 'blocks' ? 'active' : ''; ?>" data-tab="blocks" role="tab" type="button" aria-controls="pane-blocks" aria-selected="<?php echo $active_tab === 'blocks' ? 'true' : 'false'; ?>" tabindex="<?php echo $active_tab === 'blocks' ? '0' : '-1'; ?>">
				<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M2 13.5h12L8 2.5 2 13.5Z"/><path d="M8 6.5v3"/><circle cx="8" cy="11.4" r=".4" fill="currentColor"/></svg>
				<span>Bottlenecks</span>
				<span class="count"><?php echo (int) $bk['blocked']; ?></span>
			</button>
		</nav>

		<!-- ===== OVERVIEW ===== -->
		<section class="tab-pane <?php echo $active_tab === 'overview' ? 'active' : ''; ?>" id="pane-overview" data-screen-label="01 Overview" <?php echo $active_tab === 'overview' ? '' : 'hidden'; ?>>

			<div class="kpi-grid">
				<div class="kpi"><div class="k-label">Active Jobs</div><div class="k-value"><?php echo (int) $ov['active_jobs']; ?></div><div class="k-help"><?php echo esc_html( $ov['active_breakdown'] ); ?></div></div>
				<div class="kpi"><div class="k-label">In Progress</div><div class="k-value"><?php echo (int) $ov['in_progress']; ?></div></div>
				<div class="kpi"><div class="k-label">Pending QC</div><div class="k-value"><?php echo (int) $ov['pending_qc']; ?></div></div>
				<div class="kpi"><div class="k-label">Awaiting Pickup</div><div class="k-value"><?php echo (int) $ov['ready_for_pickup']; ?></div></div>

				<div class="kpi flag-crit">
					<div class="k-label">Labor Capture <span class="k-status crit">Low</span></div>
					<div class="k-value"><?php echo (int) $ov['labor_capture_pct']; ?><span class="unit">%</span></div>
					<div class="k-help"><?php echo esc_html( $ov['labor_capture_help'] ); ?></div>
				</div>
				<div class="kpi">
					<div class="k-label">Variance</div>
					<div class="k-value"><?php echo esc_html( $ov['variance'] ); ?></div>
					<div class="k-help">Tracking gap likely</div>
				</div>
				<div class="kpi flag-crit">
					<div class="k-label">Blocked</div>
					<div class="k-value"><?php echo (int) $ov['blocked']; ?></div>
					<div class="k-help"><?php echo esc_html( $ov['blocked_breakdown'] ); ?></div>
				</div>
				<div class="kpi flag-warn">
					<div class="k-label">Open Timers</div>
					<div class="k-value"><?php echo (int) $ov['open_timers']; ?></div>
					<div class="k-help"><?php echo esc_html( $ov['open_timer_help'] ); ?></div>
				</div>
			</div>

			<div class="readout">
				<div class="readout-head">
					<div>
						<div class="eyebrow">Executive Readout</div>
						<div class="title">Where leadership should look first.</div>
					</div>
					<div class="stamp">Updated <?php echo esc_html( wp_date( 'g:i A' ) ); ?> · Auto-refresh 5m</div>
				</div>
				<?php foreach ( $read as $r ) : ?>
					<div class="readout-cell">
						<div class="label"><i class="dot <?php echo esc_attr( $r['severity'] ); ?>"></i><?php echo esc_html( $r['label'] ); ?></div>
						<p><?php echo wp_kses_post( $r['body'] ); ?></p>
						<div class="row-stat">
							<?php foreach ( $r['stats'] as $s ) : ?>
								<span><?php echo esc_html( $s['label'] ); ?> <b><?php echo esc_html( $s['value'] ); ?></b></span>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="split">
				<div class="card">
					<div class="card-head">
						<h3>Labor Watchlist <span class="count-pill"><?php echo count( $lwl ); ?> issues</span></h3>
						<div class="actions"><button class="btn ghost" type="button">Export</button></div>
					</div>
					<table class="t">
						<thead><tr><th>Issue</th><th class="num">Count</th><th>Risk</th><th>Action</th></tr></thead>
						<tbody>
						<?php foreach ( $lwl as $row ) :
							$risk_label = array(
								'crit'  => 'Critical',
								'warn'  => 'Warn',
								'watch' => 'Watch',
							);
							$rl = isset( $risk_label[ $row['risk'] ] ) ? $risk_label[ $row['risk'] ] : 'Watch';
							$with_arch = $row['risk'] === 'warn';
							$is_muted  = $row['risk'] === 'watch';
							?>
							<tr>
								<td><?php echo esc_html( $row['issue'] ); ?></td>
								<td class="num"><?php echo (int) $row['count']; ?></td>
								<td><?php
									if ( $is_muted ) {
										echo Slate_Ops_Executive::tag_html( 'muted', $rl );
									} else {
										echo Slate_Ops_Executive::tag_html( $row['risk'], $rl, $with_arch );
									}
								?></td>
								<td class="action"><?php echo esc_html( $row['action'] ); ?> &rarr;</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<div class="foot"><span>Source · Time entries · <?php echo esc_html( $period['label'] ); ?></span><span><?php echo count( $lwl ); ?> of <?php echo count( $lwl ); ?></span></div>
				</div>

				<div class="card">
					<div class="card-head">
						<h3>Production Watchlist <span class="count-pill"><?php echo count( $pwl ); ?> areas</span></h3>
					</div>
					<table class="t">
						<thead><tr><th>Area</th><th class="num">Count</th><th>Status</th><th>Owner</th></tr></thead>
						<tbody>
						<?php
						$status_map = array(
							'crit'  => array( 'Critical', 'crit',  false ),
							'warn'  => array( 'Warn',     'warn',  true ),
							'watch' => array( 'Watch',    'muted', false ),
							'ok'    => array( 'Normal',   'muted', false ),
						);
						foreach ( $pwl as $row ) :
							$m = isset( $status_map[ $row['status'] ] ) ? $status_map[ $row['status'] ] : array( 'Normal', 'muted', false );
							?>
							<tr>
								<td><?php echo esc_html( $row['area'] ); ?></td>
								<td class="num"><?php echo (int) $row['count']; ?></td>
								<td><?php echo Slate_Ops_Executive::tag_html( $m[1], $m[0], $m[2] ); ?></td>
								<td><?php echo $row['owner'] ? esc_html( $row['owner'] ) : '<span class="bad">Unassigned</span>'; ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<div class="foot"><span>Source · Job statuses · Live</span><span><?php echo count( $pwl ); ?> of <?php echo count( $pwl ); ?></span></div>
				</div>
			</div>
		</section>

		<!-- ===== TECH ===== -->
		<section class="tab-pane <?php echo $active_tab === 'tech' ? 'active' : ''; ?>" id="pane-tech" data-screen-label="02 Tech Overview" <?php echo $active_tab === 'tech' ? '' : 'hidden'; ?>>
			<div class="to">
				<div class="to-kpis">
					<div class="to-kpi to-kpi--attention">
						<div class="to-kpi__label">Needs Attention</div>
						<div class="to-kpi__value <?php echo $tech_attention_count > 0 ? 'is-crit' : ''; ?>"><?php echo (int) $tech_attention_count; ?></div>
						<div class="to-kpi__split">
							<span><span class="pip crit"></span><b><?php echo (int) $tech_risk_counts['critical']; ?></b> Critical</span>
							<span><span class="pip warn"></span><b><?php echo (int) $tech_risk_counts['warning']; ?></b> Warning</span>
							<span><span class="pip watch"></span><b><?php echo (int) $tech_risk_counts['watch']; ?></b> Watch</span>
						</div>
					</div>
					<div class="to-kpi">
						<div class="to-kpi__label">Techs in Scope</div>
						<div class="to-kpi__value"><?php echo (int) $tk['techs_active']; ?></div>
						<div class="to-kpi__sub"><?php echo (int) $tech_risk_counts['ok']; ?> on track · <?php echo esc_html( $tk['period_label'] ); ?></div>
					</div>
					<div class="to-kpi">
						<div class="to-kpi__label">Logged in Period</div>
						<div class="to-kpi__value"><?php echo esc_html( $tk['logged_period'] ); ?></div>
						<div class="to-kpi__sub">Approved, pending and active timer time</div>
					</div>
					<div class="to-kpi">
						<div class="to-kpi__label">No Time in Period</div>
						<div class="to-kpi__value <?php echo (int) $tk['no_time_period'] > 0 ? 'is-crit' : ''; ?>"><?php echo (int) $tk['no_time_period']; ?></div>
						<div class="to-kpi__sub"><?php echo esc_html( $tk['no_time_help'] ); ?></div>
					</div>
					<div class="to-kpi">
						<div class="to-kpi__label">Open Timers</div>
						<div class="to-kpi__value"><?php echo (int) $tech_open_timer_count; ?></div>
						<div class="to-kpi__sub"><?php echo $tech_open_timer_count > 0 ? 'Confirm active timer hygiene' : 'No active tech timers'; ?></div>
					</div>
				</div>

				<div class="card">
					<div class="card-head">
						<h3>Tech Overview <span class="count-pill" data-tech-count><?php echo count( $techs ); ?> of <?php echo count( $techs ); ?> techs</span></h3>
						<button class="btn" type="button"><span class="material-symbols-outlined" style="font-size:15px">download</span> Export CSV</button>
					</div>

					<div class="help-card">
						<span class="material-symbols-outlined">lightbulb</span>
						Sorted by attention. Click a row to view the jobs driving that tech's risk.
					</div>

					<div class="to-controls">
						<div class="seg">
							<button class="active" data-risk="all" type="button">All <span class="seg-count"><?php echo count( $techs ); ?></span></button>
							<button data-risk="attention" type="button"><span class="seg-dot crit"></span>Attention <span class="seg-count"><?php echo (int) $tech_attention_count; ?></span></button>
							<button data-risk="critical" type="button"><span class="seg-dot crit"></span>Critical <span class="seg-count"><?php echo (int) $tech_risk_counts['critical']; ?></span></button>
							<button data-risk="warning" type="button"><span class="seg-dot warn"></span>Warning <span class="seg-count"><?php echo (int) $tech_risk_counts['warning']; ?></span></button>
							<button data-risk="watch" type="button"><span class="seg-dot watch"></span>Watch <span class="seg-count"><?php echo (int) $tech_risk_counts['watch']; ?></span></button>
							<button data-risk="ok" type="button"><span class="seg-dot ok"></span>On track <span class="seg-count"><?php echo (int) $tech_risk_counts['ok']; ?></span></button>
						</div>
						<div class="spacer"></div>
						<div class="search">
							<span class="material-symbols-outlined">search</span>
							<input placeholder="Search tech…" data-tech-search type="search" />
						</div>
						<div class="mini-select">Sort:
							<select data-tech-sort>
								<option value="attention">Needs attention</option>
								<option value="logged-desc">Most logged</option>
								<option value="capture-asc">Lowest capture</option>
								<option value="capture-desc">Highest capture</option>
								<option value="last-asc">Oldest last entry</option>
								<option value="name">Name</option>
							</select>
						</div>
					</div>

					<div class="to-head to-grid">
						<span>Tech</span>
						<span>Status</span>
						<span>Why — flags</span>
						<span class="c">Workload</span>
						<span class="r">Logged / Est</span>
						<span>Capture</span>
						<span>Action</span>
						<span class="r">Jobs</span>
					</div>

					<div class="to-list">
						<?php foreach ( $techs as $t ) :
							$risk_key = isset( $t['risk_key'] ) ? $t['risk_key'] : 'ok';
							$risk_pill = $risk_key;
							$capture_class = $t['capture_flag'] === 'crit' ? 'crit' : ( $t['capture_flag'] === 'warn' ? 'warn' : '' );
							$variance_class = (int) $t['variance_minutes'] > 0 ? 'over' : ( ( (int) $t['period_minutes'] <= 0 && (int) $t['active'] > 0 ) ? 'under' : 'ok' );
							$meter_width = min( 100, max( 0, (int) $t['capture'] ) );
							$focus_count = count( $t['focus_jobs'] );
							?>
							<div class="to-row to-grid" data-risk="<?php echo esc_attr( $risk_key ); ?>" data-name="<?php echo esc_attr( $t['name'] ); ?>" data-period="<?php echo (int) $t['period_minutes']; ?>" data-capture="<?php echo (int) $t['capture']; ?>" data-last="<?php echo (int) $t['last_entry_ts']; ?>" data-attention="<?php echo (int) $t['attention_score']; ?>">
								<button class="to-row__main" type="button">
									<div class="to-tech">
										<?php echo Slate_Ops_Executive::avatar_html( $t['name'], $t['state'] ); ?>
										<span class="to-tech__name"><b><?php echo esc_html( $t['name'] ); ?></b><span><?php echo esc_html( $t['note'] ?: 'No recent entry' ); ?></span></span>
									</div>
									<div class="to-status"><span class="risk-pill <?php echo esc_attr( $risk_pill ); ?>"><span class="dot"></span><?php echo esc_html( $t['risk_label'] ); ?></span></div>
									<div class="to-why">
										<?php if ( empty( $t['flags'] ) ) : ?>
											<span class="flag">On track</span>
										<?php else : ?>
											<?php foreach ( $t['flags'] as $flag ) :
												$flag_class = 'sev-' . ( 'crit' === $flag['kind'] ? 'crit' : ( 'watch' === $flag['kind'] ? 'watch' : 'warn' ) );
												?>
												<span class="flag <?php echo esc_attr( $flag_class ); ?>"><?php echo esc_html( $flag['text'] ); ?></span>
											<?php endforeach; ?>
										<?php endif; ?>
									</div>
									<div class="to-work"><b><?php echo (int) $t['active']; ?><span style="color:var(--slate-ink-subtle);font-weight:400">/<?php echo (int) $t['assigned']; ?></span></b><span>active · <?php echo (int) $t['touched_jobs']; ?> touched</span></div>
									<div class="to-labor">
										<div class="to-labor__top"><span class="<?php echo (int) $t['period_minutes'] <= 0 && (int) $t['active'] > 0 ? 'z' : ''; ?>"><?php echo esc_html( $t['logged'] ); ?></span> <span class="sep">/</span> <?php echo esc_html( $t['est'] ); ?></div>
										<div class="to-labor__var <?php echo esc_attr( $variance_class ); ?>"><?php echo esc_html( $t['variance'] ); ?> variance</div>
									</div>
									<div class="to-capwrap"><div class="to-cap">
										<span class="to-cap__val <?php echo esc_attr( $capture_class ); ?>"><?php echo (int) $t['capture']; ?>%</span>
										<span class="to-cap__bar <?php echo esc_attr( $capture_class ); ?>"><i style="width:<?php echo esc_attr( $meter_width ); ?>%"></i><span class="tick" style="left:80%"></span></span>
									</div></div>
									<div class="to-actionwrap"><span class="to-action-link"><?php echo esc_html( $t['primary_action'] ); ?> &rarr;</span></div>
									<div class="to-expwrap"><span class="to-exp"><?php echo (int) $focus_count; ?><span class="material-symbols-outlined">expand_more</span></span></div>
								</button>
								<div class="to-row__drawer">
									<div class="to-drawer__label">Focus jobs</div>
									<?php if ( empty( $t['focus_jobs'] ) ) : ?>
										<div class="to-empty"><span class="material-symbols-outlined">check_circle</span><b>No focus jobs</b><span>This tech has no assigned job detail in the current report scope.</span></div>
									<?php else : ?>
										<div class="fj-grid">
											<?php foreach ( $t['focus_jobs'] as $job ) :
												$job_sev = 'crit' === $job['risk'] ? 'crit' : ( 'warn' === $job['risk'] || 'watch' === $job['risk'] ? 'warn' : 'ok' );
												?>
												<div class="fj" data-sev="<?php echo esc_attr( $job_sev ); ?>">
													<span class="fj__so"><?php echo esc_html( $job['so'] ); ?></span>
													<span class="fj__tag"><span class="fj-tag <?php echo esc_attr( $job_sev ); ?>"><?php echo esc_html( $job['issue'] ); ?></span></span>
													<span class="fj__cust"><?php echo esc_html( $job['cust'] ); ?></span>
													<span class="fj__meta"><?php echo esc_html( $job['logged'] ); ?> logged · <?php echo esc_html( $job['est'] ); ?> est.</span>
												</div>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>
									<div class="to-drawer__foot">
										<button class="to-action-btn" type="button"><span class="material-symbols-outlined">schedule</span><?php echo esc_html( $t['primary_action'] ); ?></button>
										<button class="to-action-btn secondary" type="button">Review jobs</button>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
						<div class="to-empty to-empty-list" style="display:none"><span class="material-symbols-outlined">filter_alt_off</span><b>No techs match</b><span>Adjust the segment, search, or sort controls.</span></div>
					</div>
				</div>
			</div>
		</section>

		<!-- ===== JOBS ===== -->
		<section class="tab-pane <?php echo $active_tab === 'jobs' ? 'active' : ''; ?>" id="pane-jobs" data-screen-label="03 Job Performance" <?php echo $active_tab === 'jobs' ? '' : 'hidden'; ?>>
			<div class="kpi-grid">
				<div class="kpi"><div class="k-label">Total Jobs</div><div class="k-value"><?php echo (int) $jk['total_jobs']; ?></div></div>
				<div class="kpi flag-warn"><div class="k-label">Over Estimate</div><div class="k-value"><?php echo (int) $jk['over_estimate']; ?></div></div>
				<div class="kpi flag-crit"><div class="k-label">Missing Time</div><div class="k-value"><?php echo (int) $jk['missing_time']; ?></div></div>
				<div class="kpi flag-crit"><div class="k-label">At Risk</div><div class="k-value"><?php echo (int) $jk['at_risk']; ?></div></div>
			</div>

			<div class="card">
				<div class="card-head">
					<h3>Job Performance <span class="count-pill" data-jobs-count><?php echo $jobs_total . ' of ' . $jobs_total; ?></span></h3>
					<div class="actions"><button class="btn" type="button">Export</button></div>
				</div>
				<div class="filter-row">
					<div class="search">
						<svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"><circle cx="7" cy="7" r="4.5"/><path d="m10.5 10.5 3 3"/></svg>
						<input placeholder="Search SO# or customer…" type="search" data-filter="search" />
					</div>
					<button class="chip" type="button">Status <span class="caret">▾</span></button>
					<button class="chip" type="button">Lead Tech <span class="caret">▾</span></button>
					<button class="chip" type="button">Job Type <span class="caret">▾</span></button>
					<button class="chip" type="button"><?php echo esc_html( $period['label'] ); ?> <span class="caret">▾</span></button>
					<button class="chip toggle" type="button" data-filter="over">Over estimate</button>
					<button class="chip toggle" type="button" data-filter="missing">Missing time</button>
					<div style="flex:1"></div>
					<span class="chip" style="cursor:default">Risk:&nbsp;
						<select data-filter="risk">
							<option value="all">All</option>
							<option value="crit">Critical</option>
							<option value="watch">Watch</option>
							<option value="ok">OK</option>
						</select>
					</span>
				</div>
				<table class="t">
					<thead><tr>
						<th>SO#</th><th>Customer</th><th>Status</th><th>Lead Tech</th>
						<th class="num">Est.</th><th class="num">Logged</th>
						<th class="num">Variance</th><th>% Used</th><th>Risk</th>
					</tr></thead>
					<tbody>
					<?php foreach ( $jobs as $j ) : ?>
						<tr
							data-job-row
							data-so="<?php echo esc_attr( $j['so'] ); ?>"
							data-cust="<?php echo esc_attr( $j['cust'] ); ?>"
							data-variance="<?php echo esc_attr( $j['variance'] ); ?>"
							data-logged="<?php echo esc_attr( $j['logged'] ); ?>"
							data-risk="<?php echo esc_attr( $j['risk'] ); ?>">
							<td class="so"><?php echo esc_html( $j['so'] ); ?></td>
							<td class="cust"><?php echo esc_html( $j['cust'] ); ?></td>
							<td><?php echo Slate_Ops_Executive::tag_html( Slate_Ops_Executive::status_kind( $j['status'] ), $j['status'] ); ?></td>
							<td><?php
								if ( $j['lead'] ) {
									echo Slate_Ops_Executive::avatar_html( $j['lead'] ) . ' ' . esc_html( $j['lead'] );
								} else {
									echo '<span class="bad">Unassigned</span>';
								}
							?></td>
							<td class="num"><?php echo esc_html( $j['est'] ); ?></td>
							<td class="num"><?php echo $j['logged'] === '0h 0m' ? '<span class="bad">0h 0m</span>' : esc_html( $j['logged'] ); ?></td>
							<td class="num" style="<?php echo strpos( $j['variance'], '+' ) === 0 ? 'color:var(--warn)' : ''; ?>"><?php echo esc_html( $j['variance'] ); ?></td>
							<td><?php echo Slate_Ops_Executive::meter_html( $j['used'] ); ?></td>
							<td><?php
								if ( $j['risk'] === 'crit' ) {
									echo Slate_Ops_Executive::tag_html( 'crit', $j['reason'] ?: 'Critical' );
								} elseif ( $j['risk'] === 'watch' ) {
									echo Slate_Ops_Executive::tag_html( 'watch', $j['reason'] ?: 'Watch' );
								} else {
									echo Slate_Ops_Executive::tag_html( 'ok', 'On track' );
								}
							?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<div class="foot"><span>Sorted by risk</span><span>Updated <?php echo esc_html( wp_date( 'g:i A' ) ); ?></span></div>
			</div>
		</section>

		<!-- ===== LABOR ===== -->
		<section class="tab-pane <?php echo $active_tab === 'labor' ? 'active' : ''; ?>" id="pane-labor" data-screen-label="04 Labor Capture" <?php echo $active_tab === 'labor' ? '' : 'hidden'; ?>>
			<div class="kpi-grid">
				<div class="kpi"><div class="k-label">Jobs With Estimate</div><div class="k-value"><?php echo (int) $lk['jobs_with_estimate']; ?></div></div>
				<div class="kpi flag-warn"><div class="k-label">Jobs Missing Estimate</div><div class="k-value"><?php echo (int) $lk['jobs_missing_estimate']; ?></div></div>
				<div class="kpi flag-crit"><div class="k-label">Jobs With Logged Time</div><div class="k-value"><?php echo (int) $lk['jobs_with_logged']; ?></div></div>
				<div class="kpi flag-crit">
					<div class="k-label">Jobs With No Logged Time <span class="k-status crit">Audit</span></div>
					<div class="k-value"><?php echo (int) $lk['jobs_no_logged']; ?></div>
				</div>
				<div class="kpi"><div class="k-label">Total Raw Logged</div><div class="k-value"><?php echo esc_html( $lk['total_raw_logged'] ); ?></div></div>
				<div class="kpi"><div class="k-label">Estimate Coverage</div><div class="k-value"><?php echo (int) $lk['estimate_coverage']; ?><span class="unit">%</span></div></div>
				<div class="kpi flag-warn"><div class="k-label">Open Timers</div><div class="k-value"><?php echo (int) $lk['open_timers']; ?></div></div>
				<div class="kpi flag-crit"><div class="k-label">Zero-Time Completions</div><div class="k-value"><?php echo (int) $lk['zero_time_completions']; ?></div></div>
			</div>

			<div class="card">
				<div class="card-head"><h3>Labor Data Trust</h3></div>
				<div class="trust">
					<div class="score">
						<div class="lbl">Trust Score</div>
						<div class="val"><?php echo esc_html( $trust['tier'] ); ?> <span class="pct"><?php echo (int) $trust['score']; ?> / 100</span></div>
						<div class="desc"><?php echo esc_html( $trust['desc'] ); ?></div>
					</div>
					<div class="factors">
						<?php foreach ( $trust['factors'] as $f ) : ?>
							<div class="factor">
								<div class="row"><span><?php echo esc_html( $f['label'] ); ?></span><b><?php echo (int) $f['value']; ?>%</b></div>
								<div class="bar <?php echo esc_attr( $f['kind'] !== 'ok' ? $f['kind'] : '' ); ?>"><i style="width:<?php echo (int) $f['value']; ?>%"></i></div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="card-head" style="border-top:1px solid var(--rule)">
					<h3>Labor Capture Watchlist <span class="count-pill"><?php echo count( $lcwl ); ?> issues</span></h3>
					<div class="actions"><button class="btn" type="button">Export</button></div>
				</div>
				<table class="t">
					<thead><tr>
						<th>Job</th><th>Customer</th><th>Assigned Tech</th>
						<th>Issue</th><th>Last Entry</th><th>Action Needed</th>
					</tr></thead>
					<tbody>
					<?php foreach ( $lcwl as $row ) :
						$tech_state = isset( $row['tech_state'] ) ? $row['tech_state'] : '';
						$with_arch  = $row['kind'] === 'warn';
						?>
						<tr>
							<td class="so"><?php echo esc_html( $row['so'] ); ?></td>
							<td class="cust"><?php echo esc_html( $row['cust'] ); ?></td>
							<td><?php
								if ( $row['tech'] ) {
									echo Slate_Ops_Executive::avatar_html( $row['tech'], $tech_state ) . ' ' . esc_html( $row['tech'] );
								} else {
									echo '<span class="bad">Unassigned</span>';
								}
							?></td>
							<td><?php echo Slate_Ops_Executive::tag_html( $row['kind'], $row['issue'], $with_arch ); ?></td>
							<td style="font-family:'JetBrains Mono',ui-monospace,monospace;font-size:11.5px"><?php echo esc_html( $row['last'] ); ?></td>
							<td class="<?php echo $row['action'] ? 'action' : ''; ?>"><?php echo $row['action'] ? esc_html( $row['action'] ) . ' &rarr;' : '—'; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<div class="foot"><span>Showing <?php echo count( $lcwl ); ?> of <?php echo count( $lcwl ); ?></span><span>Source · Time entries · <?php echo esc_html( $period['label'] ); ?></span></div>
			</div>
		</section>

		<!-- ===== LABOR DIAGNOSTICS ===== -->
		<section class="tab-pane <?php echo $active_tab === 'diagnostics' ? 'active' : ''; ?>" id="pane-diagnostics" data-screen-label="05 Labor Diagnostics" <?php echo $active_tab === 'diagnostics' ? '' : 'hidden'; ?>>
			<div class="kpi-grid">
				<div class="kpi"><div class="k-label">Jobs in Scope</div><div class="k-value"><?php echo (int) $diag['summary']['jobs']; ?></div><div class="k-help"><?php echo esc_html( $period['label'] ); ?></div></div>
				<div class="kpi flag-crit"><div class="k-label">No Logged Time</div><div class="k-value"><?php echo (int) $diag['summary']['no_time']; ?></div><div class="k-help">Primary capture gap</div></div>
				<div class="kpi flag-warn"><div class="k-label">Missing Estimate</div><div class="k-value"><?php echo (int) $diag['summary']['missing_estimate']; ?></div><div class="k-help">Cannot trust capture %</div></div>
				<div class="kpi flag-warn"><div class="k-label">Open Timers</div><div class="k-value"><?php echo (int) $diag['summary']['open_timer']; ?></div><div class="k-help">Timers over 4h</div></div>
			</div>

			<div class="card">
				<div class="card-head">
					<h3>Labor Diagnostics <span class="count-pill"><?php echo count( $diag['jobs'] ); ?> jobs</span></h3>
					<div class="actions"><button class="btn" type="button">Export</button></div>
				</div>
				<form class="filter-row diag-filters" method="get" action="<?php echo esc_url( home_url( '/ops/exec' ) ); ?>">
					<input type="hidden" name="tab" value="diagnostics" />
					<input type="hidden" name="period" value="<?php echo esc_attr( $period['key'] ); ?>" />
					<label class="field-chip">Tech
						<select name="diag_tech">
							<option value="all">All techs</option>
							<option value="0" <?php selected( $diag['filters']['tech'], '0' ); ?>>Unassigned</option>
							<?php foreach ( $diag['tech_options'] as $tech_id => $tech_name ) : ?>
								<option value="<?php echo (int) $tech_id; ?>" <?php selected( $diag['filters']['tech'], (string) $tech_id ); ?>><?php echo esc_html( $tech_name ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label class="field-chip">Issue
						<select name="diag_issue">
							<option value="all">All issues</option>
							<?php foreach ( $diag['issue_options'] as $issue_key => $issue_label ) : ?>
								<option value="<?php echo esc_attr( $issue_key ); ?>" <?php selected( $diag['filters']['issue'], $issue_key ); ?>><?php echo esc_html( $issue_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<div class="search">
						<svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"><circle cx="7" cy="7" r="4.5"/><path d="m10.5 10.5 3 3"/></svg>
						<input name="diag_job" value="<?php echo esc_attr( $diag['filters']['job'] ); ?>" placeholder="Search SO#, customer, or job ID" type="search" />
					</div>
					<button class="btn" type="submit">Apply</button>
					<a class="btn ghost" href="<?php echo esc_url( add_query_arg( array( 'period' => $period['key'], 'tab' => 'diagnostics' ), home_url( '/ops/exec' ) ) ); ?>">Reset</a>
				</form>
				<div class="reason-grid diag-reasons">
					<?php foreach ( $diag['issue_options'] as $issue_key => $issue_label ) :
						$count = (int) ( $diag['issue_counts'][ $issue_key ] ?? 0 );
						$kind = in_array( $issue_key, array( 'no_tech', 'no_time', 'zero_time_complete' ), true ) && $count > 0 ? 'crit' : ( $count > 0 ? 'warn' : '' );
						?>
						<div class="reason <?php echo esc_attr( $kind ); ?>">
							<div class="lbl"><?php echo esc_html( $issue_label ); ?></div>
							<div class="val"><?php echo $count; ?><span class="delta">Live</span></div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="split">
				<div class="card">
					<div class="card-head"><h3>By Tech <span class="count-pill"><?php echo count( $diag['by_tech'] ); ?> techs</span></h3></div>
					<table class="t">
						<thead><tr><th>Tech</th><th class="num">Jobs</th><th class="num">No Time</th><th class="num">Logged</th><th class="num">Variance</th><th>Top Issue</th></tr></thead>
						<tbody>
						<?php foreach ( $diag['by_tech'] as $row ) : ?>
							<tr>
								<td><?php echo $row['tech_id'] ? Slate_Ops_Executive::avatar_html( $row['tech'], $row['main_risk'] ) . ' ' . esc_html( $row['tech'] ) : '<span class="bad">Unassigned</span>'; ?></td>
								<td class="num"><?php echo (int) $row['jobs']; ?></td>
								<td class="num"><?php echo (int) $row['no_time']; ?></td>
								<td class="num"><?php echo esc_html( $row['logged'] ); ?></td>
								<td class="num" style="<?php echo strpos( $row['variance'], '+' ) === 0 ? 'color:var(--warn)' : ''; ?>"><?php echo esc_html( $row['variance'] ); ?></td>
								<td><?php echo Slate_Ops_Executive::tag_html( $row['main_risk'], $row['main_issue'], 'warn' === $row['main_risk'] ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<div class="foot"><span>Filtered by current report controls</span><span><?php echo count( $diag['by_tech'] ); ?> rows</span></div>
				</div>

				<div class="card">
					<div class="card-head"><h3>Job Action Queue <span class="count-pill"><?php echo count( $diag['jobs'] ); ?> jobs</span></h3></div>
					<table class="t">
						<thead><tr><th>Job</th><th>Customer</th><th>Tech</th><th>Issue</th><th class="num">Est.</th><th class="num">Logged</th><th>Action</th></tr></thead>
						<tbody>
						<?php foreach ( $diag['jobs'] as $row ) : ?>
							<tr>
								<td class="so"><?php echo esc_html( $row['so'] ); ?></td>
								<td class="cust"><?php echo esc_html( $row['cust'] ); ?></td>
								<td><?php echo $row['tech_id'] ? esc_html( $row['tech'] ) : '<span class="bad">Unassigned</span>'; ?></td>
								<td><?php echo Slate_Ops_Executive::tag_html( $row['risk'], $row['issue'], 'warn' === $row['risk'] ); ?></td>
								<td class="num"><?php echo esc_html( Slate_Ops_Executive::minutes_label( $row['est_minutes'] ) ); ?></td>
								<td class="num"><?php echo $row['logged_minutes'] <= 0 ? '<span class="bad">0h 0m</span>' : esc_html( Slate_Ops_Executive::minutes_label( $row['logged_minutes'] ) ); ?></td>
								<td class="action"><?php echo esc_html( $row['action'] ); ?> &rarr;</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<div class="foot"><span>Sorted by severity</span><span>Showing <?php echo count( $diag['jobs'] ); ?> of <?php echo (int) $diag['summary']['jobs']; ?></span></div>
				</div>
			</div>
		</section>

		<!-- ===== BLOCKS ===== -->
		<section class="tab-pane <?php echo $active_tab === 'blocks' ? 'active' : ''; ?>" id="pane-blocks" data-screen-label="06 Bottlenecks" <?php echo $active_tab === 'blocks' ? '' : 'hidden'; ?>>
			<div class="kpi-grid">
				<div class="kpi"><div class="k-label">Ready to Build</div><div class="k-value"><?php echo (int) $bk['ready_for_build']; ?></div></div>
				<div class="kpi"><div class="k-label">Scheduled</div><div class="k-value"><?php echo (int) $bk['scheduled']; ?></div></div>
				<div class="kpi"><div class="k-label">In Progress</div><div class="k-value"><?php echo (int) $bk['in_progress']; ?></div></div>
				<div class="kpi"><div class="k-label">QC</div><div class="k-value"><?php echo (int) $bk['qc']; ?></div></div>
				<div class="kpi flag-crit">
					<div class="k-label">Blocked <span class="k-status crit">4+ days</span></div>
					<div class="k-value"><?php echo (int) $bk['blocked']; ?></div>
					<div class="k-help"><?php echo esc_html( $bk['blocked_breakdown'] ); ?></div>
				</div>
				<div class="kpi"><div class="k-label">On Hold</div><div class="k-value"><?php echo (int) $bk['on_hold']; ?></div></div>
				<div class="kpi"><div class="k-label">Cancelled</div><div class="k-value"><?php echo (int) $bk['cancelled']; ?></div></div>
				<div class="kpi flag-warn">
					<div class="k-label">Avg Block Age</div>
					<div class="k-value"><?php echo esc_html( $bk['avg_block_age'] ); ?><span class="unit">days</span></div>
					<div class="k-help"><?php echo esc_html( $bk['avg_age_help'] ); ?></div>
				</div>
			</div>

			<div class="card">
				<div class="card-head">
					<h3>Blocker Reasons</h3>
					<div class="actions"><span style="font-family:'JetBrains Mono',ui-monospace,monospace;font-size:10px;letter-spacing:.06em;color:var(--muted);text-transform:uppercase"><?php echo (int) $bk['blocked']; ?> blocked · <?php echo esc_html( $period['label'] ); ?></span></div>
				</div>
				<div class="reason-grid">
					<?php foreach ( $br as $r ) : ?>
						<div class="reason <?php echo esc_attr( $r['kind'] ); ?>">
							<div class="lbl"><?php echo esc_html( $r['label'] ); ?></div>
							<div class="val"><?php echo (int) $r['count']; ?><span class="delta"><?php echo esc_html( $r['delta'] ); ?></span></div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="card">
				<div class="card-head">
					<h3>Bottleneck Aging <span class="count-pill"><?php echo count( $blocks ); ?> jobs</span></h3>
					<div class="actions"><button class="btn" type="button">Export</button></div>
				</div>
				<div class="filter-row">
					<button class="chip" type="button">Department <span class="caret">▾</span></button>
					<button class="chip" type="button">Reason <span class="caret">▾</span></button>
					<button class="chip toggle on" type="button">Aging only (2+ d)</button>
					<button class="chip toggle" type="button">No owner</button>
					<div style="flex:1"></div>
					<button class="chip" type="button">Sort: oldest first <span class="caret">▾</span></button>
				</div>
				<table class="t">
					<thead><tr>
						<th>Job</th><th>Customer</th><th>Block Reason</th>
						<th>Department</th><th>Owner</th>
						<th>Days Blocked</th><th>Next Action</th><th>Severity</th>
					</tr></thead>
					<tbody>
					<?php foreach ( $blocks as $b ) :
						$total_stripes = 7;
						$stripes = '';
						for ( $i = 0; $i < $total_stripes; $i++ ) {
							if ( $i < (int) $b['days'] ) {
								$variant = $b['sev'] === 'crit' ? ' crit' : ( $b['sev'] === 'watch' ? ' warn' : '' );
								$stripes .= '<i class="on' . $variant . '"></i>';
							} else {
								$stripes .= '<i></i>';
							}
						}
						$sev_html = $b['sev'] === 'crit'
							? Slate_Ops_Executive::tag_html( 'crit', 'Critical' )
							: ( $b['sev'] === 'watch'
								? Slate_Ops_Executive::tag_html( 'watch', 'Watch', true )
								: Slate_Ops_Executive::tag_html( 'normal', 'Normal' ) );
						?>
						<tr>
							<td class="so"><?php echo esc_html( $b['so'] ); ?></td>
							<td class="cust"><?php echo esc_html( $b['cust'] ); ?></td>
							<td><?php echo esc_html( $b['reason'] ); ?></td>
							<td><?php echo esc_html( $b['dept'] ); ?></td>
							<td><?php echo $b['owner'] ? esc_html( $b['owner'] ) : '<span class="bad">Unassigned</span>'; ?></td>
							<td><div class="age-cell"><span class="num"><?php echo (int) $b['days']; ?>d</span><span class="stripes"><?php echo $stripes; // already-built static markup ?></span></div></td>
							<td class="action"><?php echo esc_html( $b['next'] ); ?> &rarr;</td>
							<td><?php echo $sev_html; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<div class="foot"><span><?php echo count( $blocks ); ?> of <?php echo count( $blocks ); ?> blocked jobs</span><span>Severity rules · 0–1d Normal · 2–3d Watch · 4+d Critical</span></div>
			</div>
		</section>

	</div>
</div>
