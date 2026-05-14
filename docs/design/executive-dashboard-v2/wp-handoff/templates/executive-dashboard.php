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

require_once dirname( __DIR__ ) . '/includes/class-slate-ops-executive.php';

$exec = Slate_Ops_Executive::instance();
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

$bk     = $exec->get_bottleneck_kpis();
$br     = $exec->get_blocker_reasons();
$blocks = $exec->get_blockers();
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
				<span>Period <b>30 days</b></span>
				<span class="sep">·</span>
				<span>WIP <b><?php echo (int) $bk['in_progress'] + (int) $bk['qc']; ?></b></span>
				<span class="sep">·</span>
				<span class="crit">Capture <b><?php echo (int) $ov['labor_capture_pct']; ?>%</b></span>
				<span class="sep">·</span>
				<span class="crit">Blocked <b><?php echo (int) $ov['blocked']; ?></b></span>
			</div>
		</header>

		<nav class="tabs" role="tablist">
			<button class="tab active" data-tab="overview" role="tab" type="button">
				<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="2" y="2" width="5" height="5"/><rect x="9" y="2" width="5" height="5"/><rect x="2" y="9" width="5" height="5"/><rect x="9" y="9" width="5" height="5"/></svg>
				<span>Overview</span>
			</button>
			<button class="tab" data-tab="tech" role="tab" type="button">
				<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"><circle cx="8" cy="6" r="2.4"/><path d="M3 14c0-2.5 2.2-4.2 5-4.2S13 11.5 13 14"/></svg>
				<span>Tech Performance</span>
				<span class="count"><?php echo (int) $tk['techs_active']; ?></span>
			</button>
			<button class="tab" data-tab="jobs" role="tab" type="button">
				<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M11 3.2 13.2 1l1.6 1.6L12.6 5l.7.7-2.1 2.1-1.4-1.4-5 5L3 9.8l5-5L6.6 3.4l2.1-2.1Z"/></svg>
				<span>Job Performance</span>
				<span class="count"><?php echo (int) $jk['total_jobs']; ?></span>
			</button>
			<button class="tab" data-tab="labor" role="tab" type="button">
				<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"><circle cx="8" cy="9" r="5"/><path d="M8 6.5V9l1.6 1.2"/><path d="M6.5 1.5h3M8 1.5v2"/></svg>
				<span>Labor Capture</span>
				<span class="count"><?php echo (int) $lk['jobs_no_logged']; ?></span>
			</button>
			<button class="tab" data-tab="blocks" role="tab" type="button">
				<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M2 13.5h12L8 2.5 2 13.5Z"/><path d="M8 6.5v3"/><circle cx="8" cy="11.4" r=".4" fill="currentColor"/></svg>
				<span>Bottlenecks</span>
				<span class="count"><?php echo (int) $bk['blocked']; ?></span>
			</button>
		</nav>

		<!-- ===== OVERVIEW ===== -->
		<section class="tab-pane active" id="pane-overview" data-screen-label="01 Overview">

			<div class="kpi-grid">
				<div class="kpi"><div class="k-label">Active Jobs</div><div class="k-value"><?php echo (int) $ov['active_jobs']; ?></div><div class="k-help"><?php echo esc_html( $ov['active_breakdown'] ); ?></div></div>
				<div class="kpi"><div class="k-label">In Progress</div><div class="k-value"><?php echo (int) $ov['in_progress']; ?></div></div>
				<div class="kpi"><div class="k-label">Pending QC</div><div class="k-value"><?php echo (int) $ov['pending_qc']; ?></div></div>
				<div class="kpi"><div class="k-label">Ready For Pickup</div><div class="k-value"><?php echo (int) $ov['ready_for_pickup']; ?></div></div>

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
					<div class="foot"><span>Source · Time entries 30d</span><span><?php echo count( $lwl ); ?> of <?php echo count( $lwl ); ?></span></div>
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
		<section class="tab-pane" id="pane-tech" data-screen-label="02 Tech Performance">
			<div class="kpi-grid">
				<div class="kpi"><div class="k-label">Techs Active</div><div class="k-value"><?php echo (int) $tk['techs_active']; ?></div></div>
				<div class="kpi"><div class="k-label">Logged Today</div><div class="k-value"><?php echo esc_html( $tk['logged_today'] ); ?></div></div>
				<div class="kpi"><div class="k-label">Logged This Week</div><div class="k-value"><?php echo esc_html( $tk['logged_week'] ); ?></div></div>
				<div class="kpi flag-warn">
					<div class="k-label">No Time Today</div>
					<div class="k-value"><?php echo (int) $tk['no_time_today']; ?></div>
					<div class="k-help"><?php echo esc_html( $tk['no_time_help'] ); ?></div>
				</div>
			</div>

			<div class="card">
				<div class="card-head">
					<h3>Tech Performance <span class="count-pill"><?php echo count( $techs ); ?> techs</span></h3>
					<div class="actions"><button class="btn" type="button">Export CSV</button></div>
				</div>
				<div class="help-card">
					<svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"><circle cx="8" cy="8" r="6"/><path d="M8 7v4M8 4.7v.4"/></svg>
					Spot workload imbalance, training needs, and time-tracking gaps. Capture % over 100 usually means under-estimated work.
				</div>
				<div class="filter-row">
					<div class="search">
						<svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"><circle cx="7" cy="7" r="4.5"/><path d="m10.5 10.5 3 3"/></svg>
						<input placeholder="Search tech…" type="search" />
					</div>
					<button class="chip" type="button">Department <span class="caret">▾</span></button>
					<button class="chip" type="button">Shift <span class="caret">▾</span></button>
					<button class="chip toggle" type="button">Has issues</button>
					<button class="chip toggle on" type="button">Active only</button>
					<div style="flex:1"></div>
					<button class="chip" type="button">This week <span class="caret">▾</span></button>
				</div>
				<table class="t">
					<thead><tr>
						<th>Tech</th>
						<th class="num">Assigned</th>
						<th class="num">Active</th>
						<th class="num">Logged Today</th>
						<th class="num">Logged Week</th>
						<th class="num">Est.</th>
						<th class="num">Logged</th>
						<th class="num">Variance</th>
						<th>Capture</th>
						<th>Flags</th>
					</tr></thead>
					<tbody>
					<?php foreach ( $techs as $t ) :
						$variance_style = '';
						if ( strpos( $t['variance'], '+' ) === 0 ) {
							$variance_style = 'color:var(--warn)';
						} elseif ( $t['variance'] === '-56h 0m' ) {
							$variance_style = 'color:var(--risk)';
						}
						?>
						<tr>
							<td class="tech">
								<?php echo Slate_Ops_Executive::avatar_html( $t['name'], $t['state'] ); ?><?php echo esc_html( $t['name'] ); ?>
								<?php if ( ! empty( $t['note'] ) ) : ?>
									<span class="sub"><?php echo esc_html( $t['note'] ); ?></span>
								<?php endif; ?>
							</td>
							<td class="num"><?php echo (int) $t['assigned']; ?></td>
							<td class="num"><?php echo (int) $t['active']; ?></td>
							<td class="num"><?php echo $t['today'] === '0h 0m' ? '<span class="bad">0h 0m</span>' : esc_html( $t['today'] ); ?></td>
							<td class="num"><?php echo $t['week']  === '0h 0m' ? '<span class="bad">0h 0m</span>' : esc_html( $t['week'] ); ?></td>
							<td class="num"><?php echo esc_html( $t['est'] ); ?></td>
							<td class="num"><?php echo esc_html( $t['logged'] ); ?></td>
							<td class="num" style="<?php echo esc_attr( $variance_style ); ?>"><?php echo esc_html( $t['variance'] ); ?></td>
							<td><?php echo Slate_Ops_Executive::meter_html( $t['capture'], $t['capture_flag'] ); ?></td>
							<td>
								<?php if ( empty( $t['flags'] ) ) : ?>
									<?php echo Slate_Ops_Executive::tag_html( 'ok', 'On track' ); ?>
								<?php else : ?>
									<div style="display:flex;gap:4px;flex-wrap:wrap">
										<?php foreach ( $t['flags'] as $f ) : ?>
											<?php echo Slate_Ops_Executive::tag_html( $f['kind'], $f['text'] ); ?>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<div class="foot"><span>Showing <?php echo count( $techs ); ?> of <?php echo count( $techs ); ?></span><span>Last sync <?php echo esc_html( wp_date( 'g:i A' ) ); ?></span></div>
			</div>
		</section>

		<!-- ===== JOBS ===== -->
		<section class="tab-pane" id="pane-jobs" data-screen-label="03 Job Performance">
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
					<button class="chip" type="button">Last 30 days <span class="caret">▾</span></button>
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
		<section class="tab-pane" id="pane-labor" data-screen-label="04 Labor Capture">
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
				<div class="foot"><span>Showing <?php echo count( $lcwl ); ?> of <?php echo count( $lcwl ); ?></span><span>Source · Time entries · Live</span></div>
			</div>
		</section>

		<!-- ===== BLOCKS ===== -->
		<section class="tab-pane" id="pane-blocks" data-screen-label="05 Bottlenecks">
			<div class="kpi-grid">
				<div class="kpi"><div class="k-label">Ready to Build</div><div class="k-value"><?php echo (int) $bk['ready_for_build']; ?></div></div>
				<div class="kpi"><div class="k-label">Scheduled</div><div class="k-value"><?php echo (int) $bk['scheduled']; ?></div></div>
				<div class="kpi"><div class="k-label">In Progress</div><div class="k-value"><?php echo (int) $bk['in_progress']; ?></div></div>
				<div class="kpi"><div class="k-label">QC</div><div class="k-value"><?php echo (int) $bk['qc']; ?></div></div>
				<div class="kpi flag-crit">
					<div class="k-label">Blocked <span class="k-status crit">4+ days</span></div>
					<div class="k-value"><?php echo (int) $bk['blocked']; ?></div>
					<div class="k-help">4 critical · 5 watch</div>
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
					<div class="actions"><span style="font-family:'JetBrains Mono',ui-monospace,monospace;font-size:10px;letter-spacing:.06em;color:var(--muted);text-transform:uppercase"><?php echo (int) $bk['blocked']; ?> blocked · last 30 days</span></div>
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
