<?php
if (!defined('ABSPATH')) exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Slate Ops</title>
  <?php wp_head(); ?>
</head>
<body class="slate-ops">
  <header class="ops-header">
    <div class="ops-title"><?php echo Slate_Ops_Assets::logo_img(); ?></div>
    <nav class="ops-nav">
      <a class="ops-link" href="/ops/">Jobs</a>
      <?php if (current_user_can(Slate_Ops_Utils::CAP_SUPERVISOR) || current_user_can(Slate_Ops_Utils::CAP_ADMIN) || current_user_can('administrator')): ?>
        <a class="ops-link" href="/ops/schedule/">Schedule</a>
      <?php endif; ?>
      <a class="ops-link" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Logout</a>
    </nav>
  </header>

  <main class="ops-main">
    <div class="card">
      <div class="kpi-row">
        <div class="kpi">
          <div class="kpi-label">Unscheduled</div>
          <div class="kpi-val" id="kpi-unscheduled">—</div>
        </div>
        <div class="kpi">
          <div class="kpi-label">Scheduled</div>
          <div class="kpi-val" id="kpi-scheduled">—</div>
        </div>
        <div class="kpi">
          <div class="kpi-label">In Progress</div>
          <div class="kpi-val" id="kpi-inprogress">—</div>
        </div>
        <div class="kpi">
          <div class="kpi-label">Pending QC</div>
          <div class="kpi-val" id="kpi-pendingqc">—</div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="row">
        <div style="flex:1 1 380px;">
          <div class="section-title">Jobs</div>
          <div class="muted">Internal list (SO# anchored). Scheduler reads/writes schedule fields via REST.</div>
        </div>
        <?php if (current_user_can(Slate_Ops_Utils::CAP_CS) || current_user_can(Slate_Ops_Utils::CAP_ADMIN) || current_user_can('administrator')): ?>
        <div style="flex:0 0 auto;">
          <button class="btn" id="btn-new-job">New Job</button>
        </div>
        <?php endif; ?>
      </div>

      <div class="table-wrap">
        <table class="table" id="jobs-table">
          <thead>
            <tr>
              <th>SO#</th>
              <th>VIN</th>
              <th>Customer</th>
              <th>Status</th>
              <th>Work Center</th>
              <th>Est (min)</th>
              <th>Start</th>
              <th>Finish</th>
              <th>Updated</th>
            </tr>
          </thead>
          <tbody id="jobs-body">
            <tr><td colspan="9">Loading…</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="modal" id="modal" aria-hidden="true">
      <div class="modal-card">
        <div class="modal-title">New Job</div>
        <div class="modal-grid">
          <label>SO# (optional)<input class="input" id="m-so" placeholder="S-ORD101350"></label>
          <label>VIN (optional)<input class="input" id="m-vin" placeholder="Last 6 ok"></label>
          <label>Customer<input class="input" id="m-cust" placeholder="Customer name"></label>
          <label>Dealer (optional)<input class="input" id="m-dealer" placeholder="Dealer"></label>
        </div>
        <div class="modal-actions">
          <button class="btn secondary" id="m-cancel">Cancel</button>
          <button class="btn" id="m-save">Create</button>
        </div>
      </div>
    </div>

  </main>
  <?php wp_footer(); ?>
</body>
</html>
