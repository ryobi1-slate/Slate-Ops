<?php
/**
 * Slate Ops — CS / Supervisor Operations Dashboard.
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
$subtab_counts = $payload['subtab_counts'];

$pill_class_map = [
  'parts'   => 'pill--parts',
  'qc'      => 'pill--qc',
  'pickup'  => 'pill--pickup',
  'blocked' => 'pill--blocked',
  'ready'   => 'pill--ready',
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
        <div class="ops-page-eyebrow">CS / Supervisor</div>
        <h1 class="ops-page-title" style="font-size:22px;font-weight:500;color:var(--slate-ink);margin:0 0 4px;letter-spacing:normal;text-transform:none;">Operations Dashboard</h1>
        <div class="ops-page-desc" style="font-size:13px;color:var(--slate-ink-muted);">Daily job control, blockers, QC, parts, and pickup readiness.</div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
        <div class="range-chips" id="range-chips">
          <button class="range-chip active" data-range="today">Today</button>
          <button class="range-chip" data-range="week">This week</button>
          <button class="range-chip" data-range="all">All open</button>
        </div>
        <button class="btn btn--secondary" id="refresh-btn" title="Refresh data">
          <span class="material-symbols-outlined">refresh</span>
          <span id="refresh-label">Refreshed 2m ago</span>
        </button>
      </div>
    </div>
  </div>

  <!-- Sub-tabs -->
  <nav class="ops-subnav" id="ops-subnav">
    <button class="ops-subtab active" data-tab="overview"><span class="material-symbols-outlined">dashboard</span>Overview</button>
    <button class="ops-subtab" data-tab="workspace-beta"><span class="material-symbols-outlined">workspaces</span>CS Workspace</button>
    <button class="ops-subtab ops-subtab--legacy" data-tab="workspace" title="Legacy iframe Workspace — kept as fallback while CS Workspace is on staging."><span class="material-symbols-outlined">support_agent</span>Legacy Workspace</button>
    <button class="ops-subtab ops-subtab--legacy" data-tab="queue" title="Legacy Queue tab — kept as fallback while CS Workspace is on staging."><span class="material-symbols-outlined">format_list_numbered</span>Legacy Queue</button>
    <button class="ops-subtab" data-tab="intake"><span class="material-symbols-outlined">inbox</span>Intake <span class="count"><?php echo esc_html((string) $subtab_counts['intake']); ?></span></button>
    <button class="ops-subtab" data-tab="parts"><span class="material-symbols-outlined">inventory_2</span>Parts <span class="count"><?php echo esc_html((string) $subtab_counts['parts']); ?></span></button>
    <button class="ops-subtab" data-tab="qc"><span class="material-symbols-outlined">verified</span>QC <span class="count"><?php echo esc_html((string) $subtab_counts['qc']); ?></span></button>
    <button class="ops-subtab" data-tab="pickup"><span class="material-symbols-outlined">local_shipping</span>Pickup <span class="count"><?php echo esc_html((string) $subtab_counts['pickup']); ?></span></button>
    <button class="ops-subtab" data-tab="exceptions"><span class="material-symbols-outlined">report</span>Exceptions <span class="count"><?php echo esc_html((string) $subtab_counts['exceptions']); ?></span></button>
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
        <div class="ops-kpi__label">Ready for Pickup</div>
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
          <button class="ops-link">View all open jobs <span class="material-symbols-outlined">arrow_forward</span></button>
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
          <button class="ops-link">Open report <span class="material-symbols-outlined">arrow_forward</span></button>
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
          <button class="ops-link" data-jump="parts">Go to Parts <span class="material-symbols-outlined">arrow_forward</span></button>
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
          <button class="ops-link" data-jump="qc">Go to QC <span class="material-symbols-outlined">arrow_forward</span></button>
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
          <button class="ops-link" data-jump="pickup">Go to Pickup <span class="material-symbols-outlined">arrow_forward</span></button>
        </div>
      </div>

    </div>

  </div>

  <!-- ── WORKSPACE TAB ── -->
  <!-- Embeds the legacy React /ops/cs page in an iframe. Lazy-loaded on
       first activation; iframe persists in the DOM after that so subsequent
       tab switches are instant. The iframe uses ?embed=1 which suppresses
       the parent shell's topbar and sidebar (see layout-shell.php). -->
  <div class="ops-tab-content" data-tab-content="workspace" hidden>
    <div class="ops-workspace">
      <div class="ops-workspace__skeleton" id="workspace-skeleton">
        <span class="material-symbols-outlined ops-workspace__spinner" aria-hidden="true">progress_activity</span>
        <span class="ops-workspace__skeleton-label">Loading workspace…</span>
      </div>
      <iframe
        class="ops-workspace__frame"
        id="workspace-frame"
        src=""
        data-src="<?php echo esc_url(home_url('/ops/cs/?embed=1')); ?>"
        title="CS Workspace"></iframe>
      <div class="ops-workspace__error" id="workspace-error" hidden role="alert">
        <span class="material-symbols-outlined ops-workspace__error-icon" aria-hidden="true">error</span>
        <div class="ops-workspace__error-msg">Workspace failed to load.</div>
        <div class="ops-workspace__error-actions">
          <button type="button" class="btn btn--primary" id="workspace-retry">Retry</button>
          <a class="btn btn--secondary" id="workspace-open-legacy" href="<?php echo esc_url(home_url('/ops/cs/?embed=1')); ?>" target="_top">Open Legacy CS</a>
        </div>
      </div>
    </div>
  </div>

  <!-- ── QUEUE TAB ── -->
  <!-- Shop Queue: CS sequences jobs per tech. Tech reads queue order via
       /jobs but cannot reorder. Data loads via /cs/queue on first activation. -->
  <div class="ops-tab-content" data-tab-content="queue" hidden>
    <div class="ops-queue">
      <div class="ops-queue__header">
        <div>
          <h2 class="ops-queue__title">Shop Queue</h2>
          <div class="ops-queue__sub">Manage the order CS wants jobs worked. Tech reads this queue but cannot reorder it.</div>
        </div>
        <div class="ops-queue__actions">
          <button class="btn btn--secondary" id="queue-normalize-btn" title="Reset visible queue numbers to 1, 2, 3 within each tech group">
            <span class="material-symbols-outlined">low_priority</span>
            Normalize Order
          </button>
          <button class="btn btn--primary" id="queue-save-btn" disabled>
            <span class="material-symbols-outlined">save</span>
            <span id="queue-save-label">Save Queue</span>
          </button>
        </div>
      </div>

      <div class="ops-queue__filterbar" id="queue-filters">
        <button class="queue-chip active" data-filter="all">All</button>
        <button class="queue-chip" data-filter="scheduled">Scheduled</button>
        <button class="queue-chip" data-filter="blocked">Blocked</button>
        <button class="queue-chip" data-filter="qc">Ready for Closeout</button>
        <button class="queue-chip" data-filter="unassigned">Unassigned</button>
      </div>

      <div class="ops-queue__warnings" id="queue-warnings" hidden></div>

      <div class="ops-queue__body" id="queue-body">
        <div class="ops-queue__empty">
          <span class="material-symbols-outlined ops-queue__spinner" aria-hidden="true">progress_activity</span>
          <span>Loading queue…</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ── CS WORKSPACE (BETA) TAB ── -->
  <!-- Phase 1: combined Workspace + Queue surface. Read/edit queue with
       grouped-by-tech list, filter chips, search, and a bottom detail
       panel. Uses the same /cs/queue endpoint as the Queue tab; saves
       reuse queue_order / queue_visible / queue_note. No drag/drop yet,
       no deeper job edit/save. -->
  <div class="ops-tab-content" data-tab-content="workspace-beta" hidden>
    <div class="ops-cs-workspace-beta cs-beta">
      <header class="cs-beta__header">
        <div class="cs-beta__heading">
          <div class="cs-beta__eyebrow">CS / Supervisor</div>
          <h2 class="cs-beta__title">CS Workspace</h2>
          <div class="cs-beta__sub">Manage intake, job updates, assignments, and shop queue order.</div>
        </div>
        <div class="cs-beta__actions">
          <button type="button" class="btn btn--secondary" id="cs-beta-new" title="Coming soon — create new job from intake">
            <span class="material-symbols-outlined">add</span>
            New Job
          </button>
          <button type="button" class="btn btn--secondary" id="cs-beta-normalize" title="Renumber visible queue jobs to 1, 2, 3 within each tech group">
            <span class="material-symbols-outlined">low_priority</span>
            Normalize Queue
          </button>
          <button type="button" class="btn btn--secondary" id="cs-beta-refresh" title="Reload queue data">
            <span class="material-symbols-outlined">refresh</span>
            Refresh
          </button>
          <button type="button" class="btn btn--primary" id="cs-beta-save" disabled>
            <span class="material-symbols-outlined">save</span>
            <span id="cs-beta-save-label">Save Changes</span>
          </button>
        </div>
      </header>

      <div class="cs-beta__filterbar">
        <label class="cs-beta__search" for="cs-beta-search">
          <span class="material-symbols-outlined" aria-hidden="true">search</span>
          <input type="search" id="cs-beta-search" placeholder="Search SO #, customer, dealer, note…" autocomplete="off">
        </label>
        <div class="cs-beta__chips" id="cs-beta-chips" role="tablist" aria-label="Filter queue">
          <button type="button" class="cs-beta-chip is-active" data-filter="all" role="tab" aria-selected="true">
            <span>All</span><span class="cs-beta-chip__count" data-count="all">0</span>
          </button>
          <button type="button" class="cs-beta-chip" data-filter="ready" role="tab" aria-selected="false">
            <span>Ready</span><span class="cs-beta-chip__count" data-count="ready">0</span>
          </button>
          <button type="button" class="cs-beta-chip" data-filter="scheduled" role="tab" aria-selected="false">
            <span>Scheduled</span><span class="cs-beta-chip__count" data-count="scheduled">0</span>
          </button>
          <button type="button" class="cs-beta-chip" data-filter="inprog" role="tab" aria-selected="false">
            <span>In Progress</span><span class="cs-beta-chip__count" data-count="inprog">0</span>
          </button>
          <button type="button" class="cs-beta-chip" data-filter="blocked" role="tab" aria-selected="false">
            <span>Blocked</span><span class="cs-beta-chip__count" data-count="blocked">0</span>
          </button>
          <button type="button" class="cs-beta-chip" data-filter="closeout" role="tab" aria-selected="false">
            <span>Ready for Closeout</span><span class="cs-beta-chip__count" data-count="closeout">0</span>
          </button>
          <button type="button" class="cs-beta-chip" data-filter="unassigned" role="tab" aria-selected="false">
            <span>Unassigned</span><span class="cs-beta-chip__count" data-count="unassigned">0</span>
          </button>
          <button type="button" class="cs-beta-chip" data-filter="parts" role="tab" aria-selected="false">
            <span>Parts Hold</span><span class="cs-beta-chip__count" data-count="parts">0</span>
          </button>
        </div>
      </div>

      <div class="cs-beta__warnings" id="cs-beta-warnings" hidden></div>

      <div class="cs-beta__body" id="cs-beta-body" aria-live="polite">
        <div class="cs-beta__placeholder">
          <span class="material-symbols-outlined cs-beta__spinner" aria-hidden="true">progress_activity</span>
          <span>Loading workspace…</span>
        </div>
      </div>

      <aside class="cs-beta__detail" id="cs-beta-detail" hidden aria-label="Job detail">
        <div class="cs-beta__detail-bar">
          <div class="cs-beta__detail-id">
            <span class="cs-beta__detail-eyebrow">Job Detail</span>
            <span class="cs-beta__detail-job" id="cs-beta-detail-job">—</span>
            <span class="cs-beta__detail-cust" id="cs-beta-detail-cust"></span>
          </div>
          <div class="cs-beta__detail-bar-actions">
            <button type="button" class="cs-beta__detail-close" id="cs-beta-detail-close" aria-label="Close detail">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>
        </div>
        <div class="cs-beta__detail-grid" id="cs-beta-detail-grid"></div>
      </aside>
    </div>
  </div>

  <!-- Other tabs (stub views) -->
  <div class="ops-tab-content" data-tab-content="intake" hidden>
    <div class="ops-tab-stub">
      <span class="material-symbols-outlined ops-tab-stub__icon">inbox</span>
      <div class="ops-tab-stub__title">Intake Queue</div>
      <div><?php echo esc_html((string) $subtab_counts['intake']); ?> new jobs awaiting triage. Detailed intake view connects here.</div>
    </div>
  </div>
  <div class="ops-tab-content" data-tab-content="parts" hidden>
    <div class="ops-tab-stub">
      <span class="material-symbols-outlined ops-tab-stub__icon">inventory_2</span>
      <div class="ops-tab-stub__title">Parts Board</div>
      <div>Full parts blocker board. Drill-in by job, vendor, ETA.</div>
    </div>
  </div>
  <div class="ops-tab-content" data-tab-content="qc" hidden>
    <div class="ops-tab-stub">
      <span class="material-symbols-outlined ops-tab-stub__icon">verified</span>
      <div class="ops-tab-stub__title">QC Queue</div>
      <div>Supervisor sign-off list with checklists per job.</div>
    </div>
  </div>
  <div class="ops-tab-content" data-tab-content="pickup" hidden>
    <div class="ops-tab-stub">
      <span class="material-symbols-outlined ops-tab-stub__icon">local_shipping</span>
      <div class="ops-tab-stub__title">Pickup Readiness</div>
      <div>Ready-for-pickup queue with customer contact status.</div>
    </div>
  </div>
  <div class="ops-tab-content" data-tab-content="exceptions" hidden>
    <div class="ops-tab-stub">
      <span class="material-symbols-outlined ops-tab-stub__icon">report</span>
      <div class="ops-tab-stub__title">Exceptions</div>
      <div>Blocked jobs, past-due updates, escalations needing attention.</div>
    </div>
  </div>

</div>

<!-- ─── Detail Drawer ─── -->
<div class="ops-drawer-backdrop" id="drawer-backdrop"></div>
<aside class="ops-drawer" id="job-drawer" aria-hidden="true">
  <div class="ops-drawer__head">
    <div>
      <div class="ops-drawer__title">Job Detail</div>
      <h3 class="ops-drawer__job" id="drawer-job"><?php echo esc_html($priorities[0]['id']); ?></h3>
      <div class="ops-drawer__cust" id="drawer-cust"><?php echo esc_html($priorities[0]['cust']); ?></div>
    </div>
    <button class="ops-drawer__close" id="drawer-close" aria-label="Close">
      <span class="material-symbols-outlined">close</span>
    </button>
  </div>
  <div class="ops-drawer__body">
    <div class="ops-drawer__section">
      <span class="ops-drawer__label">Status</span>
      <span class="pill" id="drawer-pill"><?php echo esc_html($priorities[0]['status']); ?></span>
    </div>
    <div class="ops-drawer__section">
      <span class="ops-drawer__label">Action needed</span>
      <div class="ops-drawer__text" id="drawer-action"><?php echo esc_html($priorities[0]['detail']); ?></div>
    </div>
    <div class="ops-drawer__section">
      <span class="ops-drawer__label">Job summary</span>
      <dl class="ops-drawer__kv" id="drawer-kv">
        <dt>Owner</dt><dd id="drawer-owner"><?php echo esc_html($priorities[0]['owner']); ?></dd>
        <dt>Opened</dt><dd>Apr 22, 2026</dd>
        <dt>Promised</dt><dd>May 6, 2026</dd>
        <dt>Workcenter</dt><dd>Bay 2 · Main floor</dd>
        <dt>Last update</dt><dd id="drawer-update">2 hours ago — CS</dd>
      </dl>
    </div>
    <div class="ops-drawer__section">
      <span class="ops-drawer__label">Recent activity</span>
      <div class="ops-drawer__text" style="font-size:12px;line-height:1.7;">
        <div>· Apr 28 — Parts ETA pushed by vendor (electrical kit)</div>
        <div>· Apr 27 — Intake complete, scope confirmed by dealer</div>
        <div>· Apr 26 — Job opened from dealer ticket #4421</div>
      </div>
    </div>
  </div>
  <div class="ops-drawer__foot">
    <button class="btn btn--primary" id="drawer-action-btn">
      <span class="material-symbols-outlined">check</span>
      <span id="drawer-action-btn-label"><?php echo esc_html($priorities[0]['action']); ?></span>
    </button>
    <button class="btn btn--secondary">
      <span class="material-symbols-outlined">edit_note</span>
      Add note
    </button>
  </div>
</aside>

<!-- Toast -->
<div class="toast" id="toast">
  <span class="material-symbols-outlined">check_circle</span>
  <span id="toast-msg">Action completed</span>
</div>

<!-- Initial-state JSON for client-side re-renders. -->
<script type="application/json" id="cs-dashboard-data"><?php echo wp_json_encode($payload); ?></script>
