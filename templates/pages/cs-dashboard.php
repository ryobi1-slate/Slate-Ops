<?php
/**
 * Slate Ops — Customer Service dashboard.
 *
 * Server-rendered first: every section paints with JS off. The companion
 * vanilla JS at assets/js/ops-cs-dashboard.js is enhancement only —
 * it re-renders from the JSON data blob below for refresh, sub-tab
 * switching, drawer, and KPI animations.
 *
 * Included by templates/ops-app.php when the route is `cs-dashboard`.
 * Access is gated via the existing slate_ops_current_user_can_access_ops_page().
 *
 * Page-only chrome (drawer, toast) is emitted at the bottom so it lives
 * inside the same template; CSS is in assets/css/ops-cs-dashboard.css.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('Slate_Ops_CS')) {
  require_once SLATE_OPS_PATH . 'includes/class-slate-ops-cs.php';
}

if (!slate_ops_current_user_can_access_ops_page('cs-dashboard')) {
  // Match the access-denied pattern ops-app.php already uses.
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

$payload       = Slate_Ops_CS::get_payload();
$kpis          = $payload['kpis'];
$priorities    = $payload['priorities'];
$health        = $payload['health'];
$parts         = $payload['parts'];
$qc            = $payload['qc'];
$pickup        = $payload['pickup'];
$drawer_seed   = $priorities[0] ?? [
  'id'     => '',
  'cust'   => '',
  'status' => '',
  'owner'  => '',
  'action' => 'Open queue',
  'detail' => 'Open the Job Queue to review live work.',
];

$pill_class_map = [
  'parts'   => 'pill--danger',
  'qc'      => 'pill--warn',
  'pickup'  => 'pill--success',
  'blocked' => 'pill--danger',
  'ready'   => 'pill--success',
  'neutral' => 'pill--neutral',
];

$tone_class_map = [
  'alert' => 'ops-list__count--alert',
  'zero'  => 'ops-list__count--zero',
  'block' => 'ops-list__count--block',
  'good'  => 'ops-list__count--good',
];

$health_tone_class_map = [
  'warn'  => 'warn',
  'alert' => 'alert',
  'good'  => 'good',
];
?>
<div class="ops-page">

  <!-- Page header -->
  <div class="ops-page-header">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:24px;">
      <div>
        <div class="ops-page-eyebrow">Customer Service</div>
        <h1 class="ops-page-title" style="font-size:22px;font-weight:500;color:var(--slate-ink);margin:0 0 4px;letter-spacing:normal;text-transform:none;">Dashboard</h1>
        <div class="ops-page-desc" style="font-size:13px;color:var(--slate-ink-muted);">Daily job control, blockers, QC, parts, and pickup readiness.</div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
        <div class="range-chips" id="range-chips">
          <button class="range-chip active" data-range="today">Today</button>
          <button class="range-chip" data-range="week">This week</button>
          <button class="range-chip" data-range="all">All open</button>
        </div>
        <button class="slate-btn slate-btn--secondary" id="refresh-btn" title="Refresh data">
          <span class="material-symbols-outlined">refresh</span>
          <span id="refresh-label">Refreshed 2m ago</span>
        </button>
      </div>
    </div>
  </div>

  <!-- Sub-tabs -->
  <nav class="ops-subnav" id="ops-subnav">
    <button class="ops-subtab active" data-tab="overview"><span class="material-symbols-outlined">dashboard</span>Overview</button>
    <button class="ops-subtab" data-tab="queue"><span class="material-symbols-outlined">format_list_numbered</span>Job Queue <span class="count" id="queue-tab-count">0</span></button>
  </nav>

  <!-- ── OVERVIEW TAB ── -->
  <div class="ops-tab-content" data-tab-content="overview">

    <!-- KPI grid: 8 cards, 4 columns -->
    <div class="ops-kpi-grid" id="kpi-grid">
      <div class="ops-kpi" data-kpi="active">
        <div class="ops-kpi__label">Active Jobs</div>
        <div class="ops-kpi__value" data-target="<?php echo esc_attr((string) $kpis['active']); ?>"><?php echo esc_html((string) $kpis['active']); ?></div>
        <div class="ops-kpi__sub">Jobs currently open</div>
      </div>
      <div class="ops-kpi ops-kpi--alert" data-kpi="parts">
        <div class="ops-kpi__label"><span class="ops-kpi__dot"></span>Waiting on Parts</div>
        <div class="ops-kpi__value" data-target="<?php echo esc_attr((string) $kpis['parts']); ?>"><?php echo esc_html((string) $kpis['parts']); ?></div>
        <div class="ops-kpi__sub">Parts blocking progress</div>
      </div>
      <div class="ops-kpi" data-kpi="ready">
        <div class="ops-kpi__label">Ready to Schedule</div>
        <div class="ops-kpi__value" data-target="<?php echo esc_attr((string) $kpis['ready']); ?>"><?php echo esc_html((string) $kpis['ready']); ?></div>
        <div class="ops-kpi__sub">Cleared for shop planning</div>
      </div>
      <div class="ops-kpi" data-kpi="inprogress">
        <div class="ops-kpi__label">In Progress</div>
        <div class="ops-kpi__value" data-target="<?php echo esc_attr((string) $kpis['inprogress']); ?>"><?php echo esc_html((string) $kpis['inprogress']); ?></div>
        <div class="ops-kpi__sub">Currently being worked</div>
      </div>
      <div class="ops-kpi" data-kpi="qc">
        <div class="ops-kpi__label">Pending QC</div>
        <div class="ops-kpi__value" data-target="<?php echo esc_attr((string) $kpis['qc']); ?>"><?php echo esc_html((string) $kpis['qc']); ?></div>
        <div class="ops-kpi__sub">Needs supervisor sign-off</div>
      </div>
      <div class="ops-kpi" data-kpi="pickup">
        <div class="ops-kpi__label">Awaiting Pickup</div>
        <div class="ops-kpi__value" data-target="<?php echo esc_attr((string) $kpis['pickup']); ?>"><?php echo esc_html((string) $kpis['pickup']); ?></div>
        <div class="ops-kpi__sub">Customer can be notified</div>
      </div>
      <div class="ops-kpi ops-kpi--block" data-kpi="blocked">
        <div class="ops-kpi__label"><span class="ops-kpi__dot ops-kpi__dot--block"></span>Blocked Jobs</div>
        <div class="ops-kpi__value" data-target="<?php echo esc_attr((string) $kpis['blocked']); ?>"><?php echo esc_html((string) $kpis['blocked']); ?></div>
        <div class="ops-kpi__sub">Needs action today</div>
      </div>
      <div class="ops-kpi" data-kpi="updates">
        <div class="ops-kpi__label">Past Due Updates</div>
        <div class="ops-kpi__value" data-target="<?php echo esc_attr((string) $kpis['updates']); ?>"><?php echo esc_html((string) $kpis['updates']); ?></div>
        <div class="ops-kpi__sub">CS follow-up needed</div>
      </div>
    </div>

    <!-- Two-column row: Priorities | Operational Health -->
    <div class="ops-row">

      <!-- LEFT: Today's Priorities -->
      <div class="ops-card">
        <div class="ops-card__head">
          <h2 class="ops-card__title">Today's Priorities</h2>
          <span class="ops-card__meta"><?php echo esc_html((string) count($priorities)); ?> items</span>
        </div>
        <table class="ops-prio-table">
          <thead>
            <tr>
              <th>Job</th>
              <th>Customer / Dealer</th>
              <th>Status</th>
              <th>Owner</th>
              <th>Action Needed</th>
            </tr>
          </thead>
          <tbody id="prio-tbody">
            <?php foreach ($priorities as $i => $p) :
              $pill_class = isset($pill_class_map[$p['pill']]) ? $pill_class_map[$p['pill']] : 'pill--neutral';
              ?>
              <tr data-row="<?php echo esc_attr((string) $i); ?>"<?php echo $i === 0 ? ' class="selected"' : ''; ?>>
                <td class="job-id"><?php echo esc_html($p['id']); ?></td>
                <td class="customer"><?php echo esc_html($p['cust']); ?></td>
                <td><span class="pill <?php echo esc_attr($pill_class); ?>"><?php echo esc_html($p['status']); ?></span></td>
                <td class="owner"><?php echo esc_html($p['owner']); ?></td>
                <td class="action"><?php echo esc_html($p['action']); ?> <span class="material-symbols-outlined arrow" style="font-size:14px;vertical-align:-3px;">arrow_forward</span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div class="ops-card__foot">
          <span style="font-size:11px;color:var(--slate-ink-subtle);">Click any row to view detail · <span class="kbd">↑</span> <span class="kbd">↓</span> to navigate</span>
          <button class="slate-btn slate-btn--secondary" type="button" data-jump="queue" data-filter-target="all">View all open jobs <span class="material-symbols-outlined">arrow_forward</span></button>
        </div>
      </div>

      <!-- RIGHT: Operational Health -->
      <div class="ops-card">
        <div class="ops-card__head">
          <h2 class="ops-card__title">Operational Health</h2>
          <span class="ops-card__meta">Live</span>
        </div>
        <div class="ops-health" id="health-list">
          <?php foreach ($health as $h) :
            $tone = isset($health_tone_class_map[$h['tone']]) ? $health_tone_class_map[$h['tone']] : '';
            ?>
            <div class="ops-health__row">
              <div class="ops-health__label"><?php echo esc_html($h['label']); ?></div>
              <div class="ops-health__value"><?php echo esc_html($h['value']); ?></div>
              <div class="ops-health__bar">
                <div class="ops-health__bar-fill<?php echo $tone ? ' ' . esc_attr($tone) : ''; ?>" style="width:<?php echo esc_attr((string) (int) $h['pct']); ?>%" data-target="<?php echo esc_attr((string) (int) $h['pct']); ?>"></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="ops-card__foot">
          <span style="font-size:11px;color:var(--slate-ink-subtle);">Updated every 5 minutes</span>
          <button class="slate-btn slate-btn--secondary" type="button" data-jump="queue" data-filter-target="blocked">Review blockers <span class="material-symbols-outlined">arrow_forward</span></button>
        </div>
      </div>

    </div>

    <!-- Three-column row: Parts | QC | Pickup -->
    <div class="ops-row-3">

      <!-- Parts Watch -->
      <div class="ops-card">
        <div class="ops-card__head">
          <h2 class="ops-card__title">Parts Watch</h2>
          <span class="ops-card__meta">Top blockers</span>
        </div>
        <div class="ops-list" id="parts-list">
          <?php foreach ($parts as $r) :
            $tone = isset($tone_class_map[$r['tone']]) ? $tone_class_map[$r['tone']] : '';
            ?>
            <div class="ops-list__row">
              <div>
                <div class="ops-list__name"><?php echo esc_html($r['name']); ?></div>
                <div class="ops-list__sub"><?php echo esc_html($r['sub']); ?></div>
              </div>
              <div class="ops-list__count<?php echo $tone ? ' ' . esc_attr($tone) : ''; ?>"><?php echo esc_html((string) $r['count']); ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="ops-card__foot">
          <span style="font-size:11px;color:var(--slate-ink-subtle);"><?php echo esc_html((string) $kpis['parts']); ?> jobs affected</span>
          <button class="slate-btn slate-btn--secondary" type="button" data-jump="queue" data-filter-target="blocked">Open queue <span class="material-symbols-outlined">arrow_forward</span></button>
        </div>
      </div>

      <!-- QC Queue -->
      <div class="ops-card">
        <div class="ops-card__head">
          <h2 class="ops-card__title">QC Queue</h2>
          <span class="ops-card__meta">Today</span>
        </div>
        <div class="ops-list" id="qc-list">
          <?php foreach ($qc as $r) :
            $tone = isset($tone_class_map[$r['tone']]) ? $tone_class_map[$r['tone']] : '';
            ?>
            <div class="ops-list__row">
              <div>
                <div class="ops-list__name"><?php echo esc_html($r['name']); ?></div>
                <div class="ops-list__sub"><?php echo esc_html($r['sub']); ?></div>
              </div>
              <div class="ops-list__count<?php echo $tone ? ' ' . esc_attr($tone) : ''; ?>"><?php echo esc_html((string) $r['count']); ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="ops-card__foot">
          <span style="font-size:11px;color:var(--slate-ink-subtle);"><?php echo esc_html((string) $kpis['qc']); ?> awaiting sign-off</span>
          <button class="slate-btn slate-btn--secondary" type="button" data-jump="queue" data-filter-target="closeout">Open queue <span class="material-symbols-outlined">arrow_forward</span></button>
        </div>
      </div>

      <!-- Pickup / Customer Contact -->
      <div class="ops-card">
        <div class="ops-card__head">
          <h2 class="ops-card__title">Pickup / Customer Contact</h2>
          <span class="ops-card__meta">Outbound</span>
        </div>
        <div class="ops-list" id="pickup-list">
          <?php foreach ($pickup as $r) :
            $tone = isset($tone_class_map[$r['tone']]) ? $tone_class_map[$r['tone']] : '';
            ?>
            <div class="ops-list__row">
              <div>
                <div class="ops-list__name"><?php echo esc_html($r['name']); ?></div>
                <div class="ops-list__sub"><?php echo esc_html($r['sub']); ?></div>
              </div>
              <div class="ops-list__count<?php echo $tone ? ' ' . esc_attr($tone) : ''; ?>"><?php echo esc_html((string) $r['count']); ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="ops-card__foot">
          <span style="font-size:11px;color:var(--slate-ink-subtle);"><?php echo esc_html((string) $kpis['pickup']); ?> ready · 1 awaiting reply</span>
          <button class="slate-btn slate-btn--secondary" type="button" data-jump="queue" data-filter-target="closeout">Open queue <span class="material-symbols-outlined">arrow_forward</span></button>
        </div>
      </div>

    </div>

  </div>

  <!-- ── JOB QUEUE TAB ── -->
  <!-- CS-owned shop queue surface. Read/edit queue with grouped-by-tech
       list, filter chips, search, drag/drop, and a bottom detail panel.
       Uses the existing /cs/queue endpoint for queue fields; job detail
       edits continue through the existing /jobs/{id} endpoint. -->
  <div class="ops-tab-content" data-tab-content="queue" hidden>
    <div class="ops-cs-workspace-beta cs-beta">
      <header class="cs-beta__header">
        <div class="cs-beta__actions">
          <button type="button" class="slate-btn slate-btn--accent" id="cs-beta-new" title="Create a new job (CS intake)">
            <span class="material-symbols-outlined">add</span>
            New Job
          </button>
          <button type="button" class="slate-btn slate-btn--secondary" id="cs-beta-normalize" title="Renumber visible queue jobs to 1, 2, 3 within each tech group">
            <span class="material-symbols-outlined">low_priority</span>
            Normalize Order
          </button>
          <button type="button" class="slate-btn slate-btn--secondary" id="cs-beta-refresh" title="Reload queue data">
            <span class="material-symbols-outlined">refresh</span>
            Refresh
          </button>
          <?php
          $cs_demo_host = isset($_SERVER['HTTP_HOST']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']))) : '';
          $cs_demo_host = preg_replace('/:\d+$/', '', $cs_demo_host);
          $cs_demo_local = in_array($cs_demo_host, ['localhost', '127.0.0.1', '::1', '[::1]'], true)
            || (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'local');
          ?>
          <?php if ($cs_demo_local && current_user_can(Slate_Ops_Utils::CAP_ADMIN)) : ?>
          <button type="button" class="slate-btn slate-btn--secondary cs-beta__demo-reset" id="cs-beta-demo-reset" title="Reset the local CS demo queue">
            <span class="material-symbols-outlined">restart_alt</span>
            Reset demo
          </button>
          <?php endif; ?>
          <button type="button" class="slate-btn slate-btn--primary" id="cs-beta-save" disabled>
            <span class="material-symbols-outlined">save</span>
            <span id="cs-beta-save-label">Save Changes</span>
          </button>
        </div>
      </header>

      <div class="cs-beta__filterbar">
        <label class="cs-beta__search" for="cs-beta-search">
          <span class="material-symbols-outlined" aria-hidden="true">search</span>
          <input type="search" id="cs-beta-search" placeholder="Search jobs…" autocomplete="off">
        </label>
        <div class="cs-beta__chips" id="cs-beta-chips" role="tablist" aria-label="Filter queue">
          <button type="button" class="slate-btn slate-btn--ghost slate-btn--sm cs-beta-chip is-active" data-filter="all" role="tab" aria-selected="true">
            <span>All</span><span class="cs-beta-chip__count" data-count="all">0</span>
          </button>
          <button type="button" class="slate-btn slate-btn--ghost slate-btn--sm cs-beta-chip" data-filter="scheduled" role="tab" aria-selected="false">
            <span>Scheduled</span><span class="cs-beta-chip__count" data-count="scheduled">0</span>
          </button>
          <button type="button" class="slate-btn slate-btn--ghost slate-btn--sm cs-beta-chip" data-filter="blocked" role="tab" aria-selected="false">
            <span>Blocked</span><span class="cs-beta-chip__count" data-count="blocked">0</span>
          </button>
          <button type="button" class="slate-btn slate-btn--ghost slate-btn--sm cs-beta-chip" data-filter="closeout" role="tab" aria-selected="false">
            <span>Ready to Close</span><span class="cs-beta-chip__count" data-count="closeout">0</span>
          </button>
          <button type="button" class="slate-btn slate-btn--ghost slate-btn--sm cs-beta-chip" data-filter="pickup" role="tab" aria-selected="false">
            <span>Awaiting Pickup</span><span class="cs-beta-chip__count" data-count="pickup">0</span>
          </button>
          <button type="button" class="slate-btn slate-btn--ghost slate-btn--sm cs-beta-chip" data-filter="unassigned" role="tab" aria-selected="false">
            <span>Unassigned</span><span class="cs-beta-chip__count" data-count="unassigned">0</span>
          </button>
        </div>
      </div>

      <div class="cs-beta__warnings" id="cs-beta-warnings" hidden></div>
      <div class="cs-beta__notice" id="cs-beta-notice" hidden></div>

      <div class="cs-beta__body" id="cs-beta-body" aria-live="polite">
        <div class="cs-beta__placeholder">
          <span class="material-symbols-outlined cs-beta__spinner" aria-hidden="true">progress_activity</span>
          <span>Loading queue…</span>
        </div>
      </div>

      <div class="cs-beta__tech-note">
        Tech page surfaces only Current Job / Next Job / Up Next. CS owns the full queue order here; techs do not manage it.
      </div>

      <div class="cs-beta-modal cs-beta-job-modal" id="cs-beta-detail" hidden role="dialog" aria-modal="true" aria-labelledby="cs-beta-detail-job">
        <div class="cs-beta-modal__backdrop" data-action="cs-beta-detail-close"></div>
        <div class="cs-beta-modal__panel cs-beta-job-modal__panel" role="document">
          <div class="cs-beta-modal__head cs-beta__detail-bar">
            <div class="cs-beta__detail-id">
              <span class="cs-beta__detail-eyebrow">Job Detail</span>
              <span class="cs-beta__detail-job" id="cs-beta-detail-job">—</span>
              <span class="cs-beta__detail-cust" id="cs-beta-detail-cust"></span>
            </div>
            <div class="cs-beta__detail-bar-actions">
              <button type="button" class="cs-beta__detail-close" id="cs-beta-detail-close" data-action="cs-beta-detail-close" aria-label="Close detail">
                <span class="material-symbols-outlined">close</span>
              </button>
            </div>
          </div>
          <div class="cs-beta__detail-grid" id="cs-beta-detail-grid"></div>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- ─── Detail Drawer ─── -->
<div class="ops-drawer-backdrop" id="drawer-backdrop"></div>
<aside class="ops-drawer" id="job-drawer" aria-hidden="true">
  <div class="ops-drawer__head">
    <div>
      <div class="ops-drawer__title">Job Detail</div>
      <h3 class="ops-drawer__job" id="drawer-job"><?php echo esc_html($drawer_seed['id']); ?></h3>
      <div class="ops-drawer__cust" id="drawer-cust"><?php echo esc_html($drawer_seed['cust']); ?></div>
    </div>
    <button class="ops-drawer__close" id="drawer-close" aria-label="Close">
      <span class="material-symbols-outlined">close</span>
    </button>
  </div>
  <div class="ops-drawer__body">
    <div class="ops-drawer__section">
      <span class="ops-drawer__label">Status</span>
      <span class="pill" id="drawer-pill"><?php echo esc_html($drawer_seed['status']); ?></span>
    </div>
    <div class="ops-drawer__section">
      <span class="ops-drawer__label">Action needed</span>
      <div class="ops-drawer__text" id="drawer-action"><?php echo esc_html($drawer_seed['detail']); ?></div>
    </div>
    <div class="ops-drawer__section">
      <span class="ops-drawer__label">Job summary</span>
      <dl class="ops-drawer__kv" id="drawer-kv">
        <dt>Owner</dt><dd id="drawer-owner"><?php echo esc_html($drawer_seed['owner']); ?></dd>
        <dt>Review location</dt><dd>Job Queue</dd>
        <dt>Source</dt><dd>Live Ops jobs</dd>
      </dl>
    </div>
    <div class="ops-drawer__section">
      <span class="ops-drawer__label">Recent activity</span>
      <div class="ops-drawer__text" style="font-size:12px;line-height:1.7;">
        <div>Open the matching Job Queue filter to update status, blocker notes, parts, due date, and assignment.</div>
      </div>
    </div>
  </div>
  <div class="ops-drawer__foot">
    <button class="slate-btn slate-btn--primary" id="drawer-action-btn">
      <span class="material-symbols-outlined">check</span>
      <span id="drawer-action-btn-label"><?php echo esc_html($drawer_seed['action']); ?></span>
    </button>
  </div>
</aside>

<!-- ─── New Job intake modal (Phase 6) ───
     Hidden by default. Opens from the Job Queue tab's New Job button.
     Posts to existing POST /jobs (perm_create_jobs = CS / Supervisor / Admin). -->
<div class="cs-beta-modal" id="cs-beta-newjob-modal" hidden role="dialog" aria-modal="true" aria-labelledby="cs-beta-newjob-title">
  <div class="cs-beta-modal__backdrop" data-action="cs-beta-newjob-close"></div>
  <div class="cs-beta-modal__panel" role="document">
    <header class="cs-beta-modal__head">
      <div>
        <div class="cs-beta-modal__eyebrow">CS Intake</div>
        <h2 class="cs-beta-modal__title" id="cs-beta-newjob-title">New Job</h2>
      </div>
      <button type="button" class="cs-beta-modal__close" data-action="cs-beta-newjob-close" aria-label="Close">
        <span class="material-symbols-outlined">close</span>
      </button>
    </header>
    <form class="cs-beta-modal__form" id="cs-beta-newjob-form" novalidate>
      <div class="cs-beta-modal__error" id="cs-beta-newjob-error" hidden role="alert"></div>

      <div class="cs-beta-modal__grid">
        <label class="cs-beta-field cs-beta-field--span2">
          <span class="cs-beta-field__label">SO #<span class="cs-beta-field__hint">optional · format S-ORD###### </span></span>
          <input type="text" class="cs-beta-mono cs-beta-field__input" name="so_number" placeholder="S-ORD101350" autocomplete="off">
        </label>
        <label class="cs-beta-field">
          <span class="cs-beta-field__label">Job Type<span class="cs-beta-field__hint">required</span></span>
          <select class="cs-beta-field__input" name="job_type" required>
            <option value="">Select…</option>
            <option value="UPFIT">Upfit</option>
            <option value="COMMERCIAL_UPFIT">Commercial Upfit</option>
            <option value="COMMERCIAL_BUILD">Commercial Build</option>
            <option value="RV_BUILD">RV Build</option>
            <option value="RV_UPFIT">RV Upfit</option>
            <option value="PARTS_ONLY">Parts Only</option>
            <option value="SERVICE">Service</option>
            <option value="WARRANTY">Warranty</option>
          </select>
        </label>
        <label class="cs-beta-field">
          <span class="cs-beta-field__label">Estimated Hours<span class="cs-beta-field__hint">required</span></span>
          <input type="number" class="cs-beta-mono cs-beta-field__input" name="estimated_hours" min="0" step="0.25" required placeholder="e.g. 8.5">
        </label>

        <label class="cs-beta-field">
          <span class="cs-beta-field__label">Customer Name<span class="cs-beta-field__hint">customer or dealer required</span></span>
          <input type="text" class="cs-beta-field__input" name="customer_name" maxlength="160" placeholder="e.g. Smith RV">
        </label>
        <label class="cs-beta-field">
          <span class="cs-beta-field__label">Dealer<span class="cs-beta-field__hint">optional if customer set</span></span>
          <input type="text" class="cs-beta-field__input" name="dealer_name" maxlength="160" placeholder="e.g. Slate RV Dealer">
        </label>

        <label class="cs-beta-field">
          <span class="cs-beta-field__label">VIN (last 8)<span class="cs-beta-field__hint">required unless Parts Only or “No VIN”</span></span>
          <input type="text" class="cs-beta-mono cs-beta-field__input" name="vin_last8" maxlength="8" pattern="[A-HJ-NPR-Z0-9]{7,8}" autocapitalize="characters" autocomplete="off" placeholder="ABCDE123">
        </label>
        <label class="cs-beta-field cs-beta-field--checkbox">
          <input type="checkbox" name="no_vin_required" value="1">
          <span>No VIN required</span>
        </label>

        <label class="cs-beta-field">
          <span class="cs-beta-field__label">Parts Status</span>
          <select class="cs-beta-field__input" name="parts_status">
            <option value="NOT_READY" selected>Not Ready</option>
            <option value="PARTIAL">Partial</option>
            <option value="READY">Ready</option>
            <option value="HOLD">On Hold</option>
          </select>
        </label>
        <label class="cs-beta-field">
          <span class="cs-beta-field__label">Requested Date<span class="cs-beta-field__hint">optional</span></span>
          <input type="date" class="cs-beta-mono cs-beta-field__input" name="requested_date">
        </label>

        <label class="cs-beta-field cs-beta-field--span2">
          <span class="cs-beta-field__label">Salesperson<span class="cs-beta-field__hint">optional</span></span>
          <input type="text" class="cs-beta-field__input" name="sales_person" maxlength="120">
        </label>

        <label class="cs-beta-field cs-beta-field--span4">
          <span class="cs-beta-field__label">Job Notes<span class="cs-beta-field__hint">visible to Tech, optional</span></span>
          <textarea class="cs-beta-field__input" name="queue_note" rows="2" maxlength="240" placeholder="Short instruction or context for the technician…"></textarea>
        </label>

        <label class="cs-beta-field cs-beta-field--span4">
          <span class="cs-beta-field__label">Internal Notes<span class="cs-beta-field__hint">CS only · not visible to Tech</span></span>
          <textarea class="cs-beta-field__input" name="notes" rows="2" maxlength="4000" placeholder="Customer comms, history, etc."></textarea>
        </label>
      </div>

      <footer class="cs-beta-modal__foot">
        <button type="button" class="slate-btn slate-btn--secondary" data-action="cs-beta-newjob-close">Cancel</button>
        <button type="submit" class="slate-btn slate-btn--primary" id="cs-beta-newjob-submit">
          <span class="material-symbols-outlined">add</span>
          <span id="cs-beta-newjob-submit-label">Create Job</span>
        </button>
      </footer>
    </form>
  </div>
</div>

<!-- Toast -->
<div class="toast" id="toast">
  <span class="material-symbols-outlined">check_circle</span>
  <span id="toast-msg">Action completed</span>
</div>

<!-- Initial-state JSON for client-side re-renders. -->
<script type="application/json" id="cs-dashboard-data"><?php echo wp_json_encode($payload); ?></script>
