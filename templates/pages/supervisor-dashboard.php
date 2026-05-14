<?php
/**
 * Slate Ops — Supervisor Dashboard.
 *
 * Server-rendered PHP page with vanilla JS enhancement only.
 * Included by templates/ops-app.php for `/ops/supervisor-dashboard`.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('Slate_Ops_Supervisor_Dashboard')) {
  require_once SLATE_OPS_PATH . 'includes/class-slate-ops-supervisor-dashboard.php';
}

if (!slate_ops_current_user_can_access_ops_page('supervisor-dashboard')) {
  ?>
  <section style="display:grid;place-items:center;height:100%;padding:32px;">
    <div style="max-width:520px;text-align:center;background:#fff;border:1px solid #D9D5C7;border-radius:10px;padding:28px;">
      <h1 style="margin:0 0 8px;font-size:24px;">Access Denied</h1>
      <p style="margin:0;color:#5E646B;">You do not have permission to view this Slate Ops page.</p>
    </div>
  </section>
  <?php
  return;
}

$payload = Slate_Ops_Supervisor_Dashboard::get_payload();
$jobs = $payload['jobs'];
$blocked_jobs = array_values(array_filter($jobs, function($job) { return !empty($job['blocked_category']); }));

function slate_ops_supervisor_status_class($status) {
  $status = sanitize_html_class((string) $status);
  return $status ? 'ops-supervisor-pill--' . $status : 'ops-supervisor-pill--neutral';
}

function slate_ops_supervisor_tech_name($job) {
  return !empty($job['tech']['name']) ? $job['tech']['name'] : 'Unassigned';
}

function slate_ops_supervisor_job_json($job) {
  return esc_attr(wp_json_encode($job));
}

function slate_ops_supervisor_button($label, $extra_class = '') {
  echo '<button type="button" class="ops-supervisor-action ' . esc_attr($extra_class) . '" data-readonly-action="1">' . esc_html($label) . '</button>';
}

function slate_ops_supervisor_job_table($jobs, $columns = 'control') {
  ?>
  <div class="ops-supervisor-table-wrap">
    <table class="ops-supervisor-table">
      <thead>
        <tr>
          <?php if ($columns === 'blocked') : ?>
            <th>Job / SO #</th><th>Dealer / Customer</th><th>Tech</th><th>Blocked Category</th><th>Reason</th><th>Time Blocked</th><th>Last Note</th><th>Action</th>
          <?php elseif ($columns === 'tech') : ?>
            <th>Tech</th><th>Current Job</th><th>Time on Job</th><th>Next Job</th><th>Status</th><th>Needs Help</th><th>Open Blocker</th><th>Today's Logged Hours</th>
          <?php else : ?>
            <th>Job</th><th>Dealer / Customer</th><th>Assigned Tech</th><th>Status</th><th>Current Step</th><th>Due</th><th>Issue / Next Action</th><th>Supervisor Action</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($jobs as $job) : ?>
          <tr class="ops-supervisor-job-row" tabindex="0" data-job="<?php echo slate_ops_supervisor_job_json($job); ?>" data-category="<?php echo esc_attr((string) ($job['blocked_category'] ?? '')); ?>">
            <?php if ($columns === 'blocked') : ?>
              <td><span class="ops-supervisor-mono"><?php echo esc_html($job['id']); ?></span><span class="ops-supervisor-sub"><?php echo esc_html($job['so']); ?></span></td>
              <td><?php echo esc_html($job['dealer']); ?><span class="ops-supervisor-sub"><?php echo esc_html($job['customer']); ?></span></td>
              <td><?php echo esc_html(slate_ops_supervisor_tech_name($job)); ?></td>
              <td><span class="ops-supervisor-category"><?php echo esc_html((string) $job['blocked_category']); ?></span></td>
              <td><?php echo esc_html((string) $job['blocked_reason']); ?></td>
              <td><span class="ops-supervisor-mono"><?php echo esc_html((string) $job['time_blocked']); ?></span></td>
              <td><?php echo esc_html($job['last_note']); ?></td>
              <td class="ops-supervisor-actions-cell">
                <?php slate_ops_supervisor_button('Clear Blocker'); ?>
                <?php slate_ops_supervisor_button('Assign Helper'); ?>
                <?php slate_ops_supervisor_button('Move to Hold'); ?>
                <?php slate_ops_supervisor_button('Add Note'); ?>
                <?php slate_ops_supervisor_button('Escalate', 'ops-supervisor-action--strong'); ?>
              </td>
            <?php else : ?>
              <td><span class="ops-supervisor-mono"><?php echo esc_html($job['id']); ?></span><span class="ops-supervisor-sub"><?php echo esc_html($job['so']); ?></span></td>
              <td><?php echo esc_html($job['dealer']); ?><span class="ops-supervisor-sub"><?php echo esc_html($job['customer']); ?></span></td>
              <td><?php echo esc_html(slate_ops_supervisor_tech_name($job)); ?></td>
              <td><span class="ops-supervisor-pill <?php echo esc_attr(slate_ops_supervisor_status_class($job['status'])); ?>"><?php echo esc_html($job['status_label']); ?></span></td>
              <td><?php echo esc_html($job['step']); ?></td>
              <td><span class="ops-supervisor-mono"><?php echo esc_html($job['due']); ?></span></td>
              <td><?php echo esc_html($job['issue']); ?></td>
              <td><?php slate_ops_supervisor_button($job['action'], 'ops-supervisor-action--strong'); ?></td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php
}

function slate_ops_supervisor_job_list_card($title, $jobs, $empty = 'No jobs in this queue.') {
  ?>
  <section class="ops-supervisor-card">
    <div class="ops-supervisor-card__head">
      <h2><?php echo esc_html($title); ?></h2>
      <span><?php echo esc_html((string) count($jobs)); ?></span>
    </div>
    <?php if (empty($jobs)) : ?>
      <p class="ops-supervisor-empty"><?php echo esc_html($empty); ?></p>
    <?php else : ?>
      <div class="ops-supervisor-list">
        <?php foreach ($jobs as $job) : ?>
          <button type="button" class="ops-supervisor-list-row ops-supervisor-job-row" data-job="<?php echo slate_ops_supervisor_job_json($job); ?>">
            <span>
              <strong><?php echo esc_html($job['id']); ?></strong>
              <small><?php echo esc_html($job['customer']); ?> · <?php echo esc_html($job['step']); ?></small>
            </span>
            <span class="ops-supervisor-pill <?php echo esc_attr(slate_ops_supervisor_status_class($job['status'])); ?>"><?php echo esc_html($job['status_label']); ?></span>
          </button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
  <?php
}
?>
<div class="ops-page ops-supervisor" data-supervisor-dashboard>
  <header class="ops-supervisor-header">
    <div>
      <div class="ops-page-eyebrow">Shop Supervisor</div>
      <h1 class="ops-supervisor-title">Supervisor Dashboard</h1>
      <p class="ops-supervisor-desc">Shop control, blockers, QC, schedule risk, and active work.</p>
    </div>
    <div class="ops-supervisor-header__actions">
      <button type="button" class="ops-supervisor-btn" id="ops-supervisor-refresh"><span class="material-symbols-outlined">refresh</span><span id="ops-supervisor-refresh-label">Refresh</span></button>
      <button type="button" class="ops-supervisor-btn" data-tab-jump="schedule"><span class="material-symbols-outlined">calendar_month</span>View Schedule</button>
      <button type="button" class="ops-supervisor-btn ops-supervisor-btn--primary" data-tab-jump="blocked"><span class="material-symbols-outlined">warning</span>Open Blocked Jobs</button>
    </div>
  </header>

  <section class="ops-supervisor-kpis" aria-label="Supervisor KPIs">
    <?php foreach ($payload['kpis'] as $kpi) : ?>
      <article class="ops-supervisor-kpi ops-supervisor-kpi--<?php echo esc_attr($kpi['tone'] ?: 'default'); ?>">
        <div class="ops-supervisor-kpi__label"><?php echo esc_html($kpi['label']); ?></div>
        <div class="ops-supervisor-kpi__value"><?php echo esc_html((string) $kpi['value']); ?></div>
        <div class="ops-supervisor-kpi__sub"><?php echo esc_html($kpi['sub']); ?></div>
      </article>
    <?php endforeach; ?>
  </section>

  <nav class="ops-supervisor-tabs" aria-label="Supervisor dashboard sections">
    <?php foreach ($payload['tabs'] as $index => $tab) : ?>
      <?php
      $count = '';
      if (!empty($tab['count_key'])) {
        if ($tab['count_key'] === 'blocked') $count = count($blocked_jobs);
        if ($tab['count_key'] === 'qc') $count = count($payload['qc']['pending']) + count($payload['qc']['rework']);
        if ($tab['count_key'] === 'ready') $count = count($payload['ready_queue']['assign']) + count($payload['ready_queue']['schedule']);
      }
      ?>
      <button type="button" class="ops-supervisor-tab<?php echo $index === 0 ? ' is-active' : ''; ?>" data-tab="<?php echo esc_attr($tab['id']); ?>" aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>">
        <?php echo esc_html($tab['label']); ?>
        <?php if ($count !== '') : ?><span><?php echo esc_html((string) $count); ?></span><?php endif; ?>
      </button>
    <?php endforeach; ?>
  </nav>

  <section class="ops-supervisor-tab-panel is-active" data-tab-panel="overview">
    <div class="ops-supervisor-focus">
      <div>
        <span>First Focus</span>
        <strong><?php echo esc_html($payload['attention'][0]['value']); ?>: <?php echo esc_html($payload['attention'][0]['detail']); ?></strong>
      </div>
      <button type="button" class="ops-supervisor-btn ops-supervisor-btn--light" data-tab-jump="blocked">Review blockers</button>
    </div>
    <div class="ops-supervisor-grid ops-supervisor-grid--wide">
      <section class="ops-supervisor-card">
        <div class="ops-supervisor-card__head"><h2>Shop Control Board</h2><span><?php echo esc_html((string) count($jobs)); ?> jobs</span></div>
        <?php slate_ops_supervisor_job_table($jobs); ?>
      </section>
      <section class="ops-supervisor-card">
        <div class="ops-supervisor-card__head"><h2>Supervisor Attention</h2><span>Today</span></div>
        <div class="ops-supervisor-attention">
          <?php foreach ($payload['attention'] as $item) : ?>
            <div>
              <span><?php echo esc_html($item['label']); ?></span>
              <strong><?php echo esc_html($item['value']); ?></strong>
              <small><?php echo esc_html($item['detail']); ?></small>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    </div>
  </section>

  <section class="ops-supervisor-tab-panel" data-tab-panel="blocked" hidden>
    <div class="ops-supervisor-filterbar">
      <button type="button" class="ops-supervisor-chip is-active" data-category-filter="all">All</button>
      <?php foreach ($payload['blocked_categories'] as $category) : ?>
        <button type="button" class="ops-supervisor-chip" data-category-filter="<?php echo esc_attr($category); ?>"><?php echo esc_html($category); ?></button>
      <?php endforeach; ?>
    </div>
    <section class="ops-supervisor-card">
      <div class="ops-supervisor-card__head"><h2>Blocked Jobs</h2><span><?php echo esc_html((string) count($blocked_jobs)); ?> active</span></div>
      <?php slate_ops_supervisor_job_table($blocked_jobs, 'blocked'); ?>
    </section>
  </section>

  <section class="ops-supervisor-tab-panel" data-tab-panel="schedule" hidden>
    <div class="ops-supervisor-mini-grid">
      <?php foreach ($payload['schedule']['cards'] as $card) : ?>
        <article class="ops-supervisor-mini"><span><?php echo esc_html($card['label']); ?></span><strong><?php echo esc_html((string) $card['value']); ?></strong><small><?php echo esc_html($card['sub']); ?></small></article>
      <?php endforeach; ?>
    </div>
    <div class="ops-supervisor-grid">
      <?php slate_ops_supervisor_job_list_card('Today’s Scheduled Work', $payload['schedule']['today']); ?>
      <?php slate_ops_supervisor_job_list_card('Jobs at Risk', $payload['schedule']['at_risk']); ?>
      <?php slate_ops_supervisor_job_list_card('Ready to Schedule', $payload['schedule']['ready_to_schedule']); ?>
      <?php slate_ops_supervisor_job_list_card('Unassigned Jobs', $payload['schedule']['unassigned']); ?>
    </div>
    <section class="ops-supervisor-card">
      <div class="ops-supervisor-card__head"><h2>Future scheduler placeholders</h2><span>Planned</span></div>
      <div class="ops-supervisor-future-grid">
        <?php foreach ($payload['schedule']['future'] as $card) : ?>
          <div class="ops-supervisor-future"><span><?php echo esc_html($card['label']); ?></span><strong><?php echo esc_html($card['value']); ?></strong><small><?php echo esc_html($card['sub']); ?></small></div>
        <?php endforeach; ?>
      </div>
    </section>
  </section>

  <section class="ops-supervisor-tab-panel" data-tab-panel="techs" hidden>
    <div class="ops-supervisor-tech-cards">
      <?php foreach ($payload['techs'] as $tech) : ?>
        <article class="ops-supervisor-tech-card">
          <div class="ops-supervisor-avatar"><?php echo esc_html($tech['initials']); ?></div>
          <div><strong><?php echo esc_html($tech['name']); ?></strong><span><?php echo esc_html($tech['role']); ?></span></div>
          <span class="ops-supervisor-pill ops-supervisor-pill--<?php echo esc_attr(sanitize_html_class(strtolower(str_replace([' / ', ' '], '-', $tech['status'])))); ?>"><?php echo esc_html($tech['status']); ?></span>
        </article>
      <?php endforeach; ?>
    </div>
    <section class="ops-supervisor-card">
      <div class="ops-supervisor-card__head"><h2>Tech Status</h2><span><?php echo esc_html((string) count($payload['techs'])); ?> techs</span></div>
      <div class="ops-supervisor-table-wrap">
        <table class="ops-supervisor-table">
          <thead><tr><th>Tech</th><th>Current Job</th><th>Time on Job</th><th>Next Job</th><th>Status</th><th>Needs Help</th><th>Open Blocker</th><th>Today’s Logged Hours</th></tr></thead>
          <tbody>
            <?php foreach ($payload['techs'] as $tech) : ?>
              <tr>
                <td><?php echo esc_html($tech['name']); ?><span class="ops-supervisor-sub"><?php echo esc_html($tech['role']); ?></span></td>
                <td><?php echo $tech['current'] ? '<span class="ops-supervisor-mono">' . esc_html($tech['current']['id']) . '</span><span class="ops-supervisor-sub">' . esc_html($tech['current']['label']) . '</span>' : esc_html('—'); ?></td>
                <td><span class="ops-supervisor-mono"><?php echo esc_html($tech['on_job_time']); ?></span></td>
                <td><span class="ops-supervisor-mono"><?php echo esc_html($tech['next']); ?></span></td>
                <td><?php echo esc_html($tech['status']); ?></td>
                <td><?php echo $tech['needs_help'] ? esc_html('Yes') : esc_html('No'); ?></td>
                <td><?php echo esc_html($tech['blocker'] ?: '—'); ?></td>
                <td><span class="ops-supervisor-mono"><?php echo esc_html($tech['hours']); ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </section>

  <section class="ops-supervisor-tab-panel" data-tab-panel="qc" hidden>
    <div class="ops-supervisor-grid">
      <?php slate_ops_supervisor_job_list_card('Pending QC', $payload['qc']['pending']); ?>
      <?php slate_ops_supervisor_job_list_card('Failed QC', $payload['qc']['failed']); ?>
      <?php slate_ops_supervisor_job_list_card('Rework Required', $payload['qc']['rework']); ?>
      <?php slate_ops_supervisor_job_list_card('Ready for Sign-off', $payload['qc']['signoff']); ?>
    </div>
    <div class="ops-supervisor-action-row">
      <?php slate_ops_supervisor_button('Review QC'); ?>
      <?php slate_ops_supervisor_button('Approve Closeout'); ?>
      <?php slate_ops_supervisor_button('Send Back to Tech'); ?>
      <?php slate_ops_supervisor_button('Add Rework Note'); ?>
    </div>
  </section>

  <section class="ops-supervisor-tab-panel" data-tab-panel="ready" hidden>
    <div class="ops-supervisor-grid">
      <?php slate_ops_supervisor_job_list_card('Ready to Assign', $payload['ready_queue']['assign']); ?>
      <?php slate_ops_supervisor_job_list_card('Ready to Schedule', $payload['ready_queue']['schedule']); ?>
      <?php slate_ops_supervisor_job_list_card('Parts Partial but Schedulable', $payload['ready_queue']['partial_schedulable']); ?>
      <?php slate_ops_supervisor_job_list_card('Waiting SO / Admin Hold', $payload['ready_queue']['admin_hold']); ?>
    </div>
  </section>
</div>

<div class="ops-supervisor-drawer-backdrop" id="ops-supervisor-drawer-backdrop"></div>
<aside class="ops-supervisor-drawer" id="ops-supervisor-drawer" aria-hidden="true" aria-label="Job detail">
  <header class="ops-supervisor-drawer__head">
    <div>
      <span class="ops-supervisor-drawer__eyebrow">Job detail</span>
      <h2 id="ops-supervisor-drawer-job">Select a job</h2>
      <p id="ops-supervisor-drawer-customer"></p>
    </div>
    <button type="button" class="ops-supervisor-drawer__close" id="ops-supervisor-drawer-close" aria-label="Close job detail"><span class="material-symbols-outlined">close</span></button>
  </header>
  <div class="ops-supervisor-drawer__body">
    <span class="ops-supervisor-pill" id="ops-supervisor-drawer-status">—</span>
    <dl class="ops-supervisor-drawer__kv" id="ops-supervisor-drawer-kv"></dl>
    <section class="ops-supervisor-drawer__blocker" id="ops-supervisor-drawer-blocker" hidden></section>
    <section>
      <h3>Last note</h3>
      <p id="ops-supervisor-drawer-last-note"></p>
    </section>
    <section>
      <h3>Recent notes/activity</h3>
      <div id="ops-supervisor-drawer-notes"></div>
    </section>
  </div>
  <footer class="ops-supervisor-drawer__foot">
    <?php slate_ops_supervisor_button('Clear Blocker'); ?>
    <?php slate_ops_supervisor_button('Assign Helper'); ?>
    <?php slate_ops_supervisor_button('Add Note'); ?>
    <?php slate_ops_supervisor_button('Escalate', 'ops-supervisor-action--strong'); ?>
  </footer>
</aside>

<div class="ops-supervisor-toast" id="ops-supervisor-toast">Read-only first pass. No job change was written.</div>
<script type="application/json" id="supervisor-dashboard-data"><?php echo wp_json_encode($payload); ?></script>
