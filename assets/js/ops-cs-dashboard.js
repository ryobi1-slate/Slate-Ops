/**
 * Slate Ops — CS / Supervisor Operations Dashboard.
 *
 * Vanilla JS enhancement layer. The page is server-rendered and works
 * with JS off; this file adds re-renders, drawer, animations, keyboard
 * nav, sub-tab switching, range chips, and refresh.
 *
 * Loaded only on /ops/cs-dashboard. Initial state comes from the
 * #cs-dashboard-data JSON blob the template emits.
 */
(function () {
  'use strict';

  // ─── Initial data from server ─────────────────────────────────────────
  var data = { priorities: [], health: [], parts: [], qc: [], pickup: [], kpis: {}, subtab_counts: {} };
  var blob = document.getElementById('cs-dashboard-data');
  if (blob) {
    try { data = JSON.parse(blob.textContent || '{}'); }
    catch (e) { /* leave defaults */ }
  }

  var prefersReducedMotion = window.matchMedia &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // ─── State ────────────────────────────────────────────────────────────
  var state = {
    selectedRow: 0,
    activeTab: 'overview'
  };

  var pillLabel = {
    parts:   'Waiting on Parts',
    qc:      'Pending QC',
    pickup:  'Ready for Pickup',
    blocked: 'Blocked',
    ready:   'Ready'
  };

  var toneClassMap = {
    alert: 'ops-list__count--alert',
    zero:  'ops-list__count--zero',
    block: 'ops-list__count--block',
    good:  'ops-list__count--good'
  };

  // ─── DOM helpers ──────────────────────────────────────────────────────
  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  // ─── Render: Priorities table ─────────────────────────────────────────
  function renderPriorities() {
    var tbody = document.getElementById('prio-tbody');
    if (!tbody) return;
    var rows = data.priorities || [];
    tbody.innerHTML = rows.map(function (p, i) {
      return ''
        + '<tr data-row="' + i + '"' + (i === state.selectedRow ? ' class="selected"' : '') + '>'
        + '<td class="job-id">' + escapeHtml(p.id) + '</td>'
        + '<td class="customer">' + escapeHtml(p.cust) + '</td>'
        + '<td><span class="pill pill--' + escapeHtml(p.pill) + '">' + escapeHtml(p.status) + '</span></td>'
        + '<td class="owner">' + escapeHtml(p.owner) + '</td>'
        + '<td class="action">' + escapeHtml(p.action) + ' <span class="material-symbols-outlined arrow" style="font-size:14px;vertical-align:-3px;">arrow_forward</span></td>'
        + '</tr>';
    }).join('');

    $$('tr', tbody).forEach(function (tr) {
      tr.addEventListener('click', function () {
        var idx = parseInt(tr.dataset.row, 10);
        state.selectedRow = idx;
        openDrawer(rows[idx]);
        renderPriorities();
      });
    });
  }

  // ─── Render: Operational Health ───────────────────────────────────────
  function renderHealth() {
    var list = document.getElementById('health-list');
    if (!list) return;
    var rows = data.health || [];
    list.innerHTML = rows.map(function (h) {
      var tone = h.tone ? ' ' + h.tone : '';
      return ''
        + '<div class="ops-health__row">'
        + '<div class="ops-health__label">' + escapeHtml(h.label) + '</div>'
        + '<div class="ops-health__value">' + escapeHtml(h.value) + '</div>'
        + '<div class="ops-health__bar">'
        + '<div class="ops-health__bar-fill' + tone + '" style="width:0%" data-target="' + (h.pct | 0) + '"></div>'
        + '</div>'
        + '</div>';
    }).join('');

    if (prefersReducedMotion) {
      $$('.ops-health__bar-fill', list).forEach(function (el) {
        el.style.width = el.dataset.target + '%';
      });
      return;
    }
    requestAnimationFrame(function () {
      $$('.ops-health__bar-fill', list).forEach(function (el) {
        setTimeout(function () { el.style.width = el.dataset.target + '%'; }, 100);
      });
    });
  }

  // ─── Render: Mini-card lists ──────────────────────────────────────────
  function renderList(id, rows) {
    var el = document.getElementById(id);
    if (!el) return;
    el.innerHTML = rows.map(function (r) {
      var tone = toneClassMap[r.tone] || '';
      return ''
        + '<div class="ops-list__row">'
        + '<div>'
        + '<div class="ops-list__name">' + escapeHtml(r.name) + '</div>'
        + '<div class="ops-list__sub">' + escapeHtml(r.sub) + '</div>'
        + '</div>'
        + '<div class="ops-list__count' + (tone ? ' ' + tone : '') + '">' + escapeHtml(r.count) + '</div>'
        + '</div>';
    }).join('');
  }

  // ─── KPI count-up ─────────────────────────────────────────────────────
  function animateKPIs() {
    $$('.ops-kpi__value').forEach(function (el) {
      var target = parseInt(el.dataset.target, 10) || 0;
      if (prefersReducedMotion) {
        el.textContent = target;
        return;
      }
      var dur = 600 + Math.random() * 200;
      var start = performance.now();
      function tick(now) {
        var t = Math.min(1, (now - start) / dur);
        var eased = 1 - Math.pow(1 - t, 3);
        el.textContent = Math.round(eased * target);
        if (t < 1) requestAnimationFrame(tick);
      }
      requestAnimationFrame(tick);
    });
  }

  // ─── Drawer ───────────────────────────────────────────────────────────
  var drawer   = document.getElementById('job-drawer');
  var backdrop = document.getElementById('drawer-backdrop');

  function openDrawer(p) {
    if (!drawer || !p) return;
    var el;
    if ((el = document.getElementById('drawer-job')))             el.textContent = p.id || '';
    if ((el = document.getElementById('drawer-cust')))            el.textContent = p.cust || '';
    var pill = document.getElementById('drawer-pill');
    if (pill) {
      pill.textContent = p.status || '';
      pill.className = 'pill pill--' + (p.pill || 'neutral');
    }
    if ((el = document.getElementById('drawer-action')))          el.textContent = p.detail || '';
    if ((el = document.getElementById('drawer-owner')))            el.textContent = p.owner || '';
    if ((el = document.getElementById('drawer-action-btn-label'))) el.textContent = p.action || '';
    drawer.classList.add('open');
    if (backdrop) backdrop.classList.add('open');
    drawer.setAttribute('aria-hidden', 'false');
  }

  function closeDrawer() {
    if (!drawer) return;
    drawer.classList.remove('open');
    if (backdrop) backdrop.classList.remove('open');
    drawer.setAttribute('aria-hidden', 'true');
  }

  if (drawer) {
    var closeBtn = document.getElementById('drawer-close');
    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
    if (backdrop) backdrop.addEventListener('click', closeDrawer);

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeDrawer();
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        if (state.activeTab !== 'overview') return;
        var rows = data.priorities || [];
        if (!rows.length) return;
        e.preventDefault();
        var dir = e.key === 'ArrowDown' ? 1 : -1;
        state.selectedRow = (state.selectedRow + dir + rows.length) % rows.length;
        renderPriorities();
      }
      if (e.key === 'Enter' && state.activeTab === 'overview') {
        var rows = data.priorities || [];
        if (rows[state.selectedRow]) openDrawer(rows[state.selectedRow]);
      }
    });

    var actionBtn = document.getElementById('drawer-action-btn');
    if (actionBtn) {
      actionBtn.addEventListener('click', function () {
        showToast('Action logged · job updated');
        closeDrawer();
      });
    }
  }

  // ─── Toast ────────────────────────────────────────────────────────────
  var toast = document.getElementById('toast');
  var toastT;
  function showToast(msg) {
    if (!toast) return;
    var msgEl = document.getElementById('toast-msg');
    if (msgEl) msgEl.textContent = msg;
    toast.classList.add('show');
    clearTimeout(toastT);
    toastT = setTimeout(function () { toast.classList.remove('show'); }, 2400);
  }

  // ─── Workspace tab (lazy iframe + scroll lock) ───────────────────────
  // The legacy React /ops/cs page is embedded in an iframe. The iframe
  // src is left empty in the template and only set on first activation,
  // so users who never click Workspace don't pay for the React bundle.
  // Once loaded, the iframe stays in the DOM with its src intact.
  var WORKSPACE_LOAD_TIMEOUT_MS = 8000;
  var workspaceTimer = null;
  var workspaceLoaded = false;

  function clearWorkspaceTimer() {
    if (workspaceTimer) {
      clearTimeout(workspaceTimer);
      workspaceTimer = null;
    }
  }

  function showWorkspaceError() {
    var frame    = document.getElementById('workspace-frame');
    var skeleton = document.getElementById('workspace-skeleton');
    var error    = document.getElementById('workspace-error');
    if (skeleton) skeleton.hidden = true;
    if (frame)    frame.hidden    = true;
    if (error)    error.hidden    = false;
  }

  function startWorkspaceLoad() {
    var frame    = document.getElementById('workspace-frame');
    var skeleton = document.getElementById('workspace-skeleton');
    var error    = document.getElementById('workspace-error');
    if (!frame) return;

    workspaceLoaded = false;
    if (error)    error.hidden    = true;
    if (skeleton) skeleton.hidden = false;
    frame.hidden = true;

    clearWorkspaceTimer();
    workspaceTimer = setTimeout(function () {
      if (workspaceLoaded) return;
      showWorkspaceError();
    }, WORKSPACE_LOAD_TIMEOUT_MS);

    frame.setAttribute('src', frame.dataset.src);
  }

  function activateWorkspace() {
    document.documentElement.classList.add('cs-workspace-active');
    document.body.classList.add('cs-workspace-active');

    var frame = document.getElementById('workspace-frame');
    if (!frame) return;

    if (!frame.getAttribute('src')) {
      startWorkspaceLoad();
    }
  }

  function deactivateWorkspace() {
    document.documentElement.classList.remove('cs-workspace-active');
    document.body.classList.remove('cs-workspace-active');
    // Iframe stays in the DOM with src intact — do NOT clear it.
  }

  var workspaceFrame = document.getElementById('workspace-frame');
  if (workspaceFrame) {
    workspaceFrame.addEventListener('load', function () {
      // Browsers fire `load` once with src="" on initial parse — ignore that.
      if (!workspaceFrame.getAttribute('src')) return;
      workspaceLoaded = true;
      clearWorkspaceTimer();
      var skeleton = document.getElementById('workspace-skeleton');
      var error    = document.getElementById('workspace-error');
      if (skeleton) skeleton.hidden = true;
      if (error)    error.hidden    = true;
      workspaceFrame.hidden = false;
    });
  }

  var workspaceRetry = document.getElementById('workspace-retry');
  if (workspaceRetry) {
    workspaceRetry.addEventListener('click', function () {
      var frame = document.getElementById('workspace-frame');
      if (!frame) return;
      frame.setAttribute('src', '');
      startWorkspaceLoad();
    });
  }

  // ─── Sub-tabs ─────────────────────────────────────────────────────────
  $$('.ops-subtab').forEach(function (btn) {
    btn.addEventListener('click', function () {
      $$('.ops-subtab').forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      var tab = btn.dataset.tab;
      state.activeTab = tab;
      $$('.ops-tab-content').forEach(function (c) {
        c.hidden = c.dataset.tabContent !== tab;
      });
      if (tab === 'workspace') {
        activateWorkspace();
      } else {
        deactivateWorkspace();
      }
      if (tab === 'queue') {
        activateQueue();
      }
    });
  });

  // ─── Queue tab (Shop Queue) ──────────────────────────────────────────
  // Loads on first activation, then renders from in-memory state. CS edits
  // queue_order (number input) and queue_note (text input) inline; Save
  // pushes a single bulk POST to /cs/queue. Normalize re-numbers visible
  // jobs in each tech group to 1, 2, 3 in their current sort order.
  var queueState = {
    loaded:   false,
    loading:  false,
    jobs:     [],          // server snapshot (last loaded)
    edits:    {},          // { id: { queue_order, queue_visible, queue_note } }
    filter:   'all'
  };

  function queueApi() {
    return (window.slateOpsCsDashboard && window.slateOpsCsDashboard.api) || null;
  }

  function activateQueue() {
    if (queueState.loaded || queueState.loading) {
      renderQueue();
      return;
    }
    loadQueue();
  }

  function loadQueue() {
    var api = queueApi();
    var body = document.getElementById('queue-body');
    if (!api) {
      if (body) body.innerHTML = '<div class="ops-queue__empty"><span>Queue API not available.</span></div>';
      return;
    }
    queueState.loading = true;
    fetch(api.root + '/cs/queue', {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'X-WP-Nonce': api.nonce, 'Accept': 'application/json' }
    })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
      .then(function (res) {
        queueState.loading = false;
        if (!res.ok || !res.body || !res.body.ok) {
          if (body) body.innerHTML = '<div class="ops-queue__empty"><span>Failed to load queue.</span></div>';
          return;
        }
        queueState.jobs   = res.body.jobs || [];
        queueState.edits  = {};
        queueState.loaded = true;
        renderQueue();
      })
      .catch(function () {
        queueState.loading = false;
        if (body) body.innerHTML = '<div class="ops-queue__empty"><span>Failed to load queue.</span></div>';
      });
  }

  function effectiveJob(j) {
    var e = queueState.edits[j.id] || {};
    return {
      id:               j.id,
      job_number:       j.job_number,
      so_number:        j.so_number,
      customer:         j.customer,
      dealer:           j.dealer,
      status:           j.status,
      status_label:     j.status_label,
      parts_status:     j.parts_status,
      due_date:         j.due_date,
      assigned_user_id: j.assigned_user_id,
      assigned_tech:    j.assigned_tech,
      queue_order:      e.queue_order   !== undefined ? e.queue_order   : j.queue_order,
      queue_visible:    e.queue_visible !== undefined ? e.queue_visible : j.queue_visible,
      queue_note:       e.queue_note    !== undefined ? e.queue_note    : (j.queue_note || ''),
      _dirty:           Object.keys(e).length > 0
    };
  }

  function passesFilter(j) {
    switch (queueState.filter) {
      case 'all':        return true;
      case 'scheduled':  return j.status === 'SCHEDULED';
      case 'blocked':    return j.status === 'BLOCKED';
      case 'qc':         return j.status === 'QC' || j.status === 'PENDING_QC';
      case 'unassigned': return !j.assigned_user_id;
      default:           return true;
    }
  }

  function statusPillClass(status) {
    switch (status) {
      case 'BLOCKED':         return 'pill--blocked';
      case 'SCHEDULED':       return 'pill--ready';
      case 'IN_PROGRESS':     return 'pill--qc';
      case 'QC':
      case 'PENDING_QC':      return 'pill--qc';
      case 'READY_FOR_BUILD': return 'pill--ready';
      default:                return 'pill--neutral';
    }
  }

  function partsPillClass(ps) {
    var v = String(ps || '').toUpperCase();
    if (v === 'HOLD' || v === 'NOT_READY') return 'pill--blocked';
    if (v === 'PARTIAL')                   return 'pill--parts';
    if (v === 'READY')                     return 'pill--ready';
    return 'pill--neutral';
  }

  function partsLabel(ps) {
    var v = String(ps || '').toUpperCase();
    if (!v) return '—';
    var map = { READY: 'Ready', PARTIAL: 'Partial', NOT_READY: 'Not ready', HOLD: 'On hold' };
    return map[v] || v;
  }

  function fmtDate(s) {
    if (!s) return '—';
    // Date-only ('YYYY-MM-DD') or datetime — keep first 10 chars.
    return String(s).substr(0, 10);
  }

  function renderQueue() {
    var body = document.getElementById('queue-body');
    if (!body) return;

    var jobs    = queueState.jobs.map(effectiveJob);
    var visible = jobs.filter(passesFilter);

    // Group by tech (string label keyed for stability)
    var groups   = {};
    var keyOrder = [];
    visible.forEach(function (j) {
      var key = j.assigned_user_id ? ('u:' + j.assigned_user_id) : 'unassigned';
      if (!groups[key]) {
        groups[key] = {
          key:        key,
          label:      j.assigned_user_id ? (j.assigned_tech || ('User #' + j.assigned_user_id)) : 'Unassigned',
          unassigned: !j.assigned_user_id,
          jobs:       []
        };
        keyOrder.push(key);
      }
      groups[key].jobs.push(j);
    });

    keyOrder.sort(function (a, b) {
      if (groups[a].unassigned && !groups[b].unassigned) return 1;
      if (!groups[a].unassigned && groups[b].unassigned) return -1;
      return groups[a].label.localeCompare(groups[b].label);
    });

    if (keyOrder.length === 0) {
      body.innerHTML = '<div class="ops-queue__empty"><span>No jobs match this filter.</span></div>';
      updateQueueWarnings();
      updateSaveButton();
      return;
    }

    var html = keyOrder.map(function (k) {
      var g = groups[k];
      g.jobs.sort(function (a, b) {
        var ao = a.queue_order == null ? 1e9 : a.queue_order;
        var bo = b.queue_order == null ? 1e9 : b.queue_order;
        if (ao !== bo) return ao - bo;
        return (a.job_number || '').localeCompare(b.job_number || '');
      });

      // Detect duplicate visible queue numbers within the group.
      var seen   = {};
      var dupSet = {};
      g.jobs.forEach(function (j) {
        if (!j.queue_visible) return;
        if (j.queue_order == null) return;
        if (seen[j.queue_order]) dupSet[j.queue_order] = true;
        seen[j.queue_order] = true;
      });

      var rows = g.jobs.map(function (j) {
        var dup = j.queue_visible && j.queue_order != null && dupSet[j.queue_order];
        var rowCls = 'queue-row';
        if (j._dirty)               rowCls += ' queue-row--dirty';
        if (!j.queue_visible)       rowCls += ' queue-row--hidden';
        if (dup)                    rowCls += ' queue-row--dup';

        return ''
          + '<tr class="' + rowCls + '" data-job="' + j.id + '">'
          + '<td class="queue-col-num">'
          +   '<input type="number" min="1" step="1" class="queue-num-input"'
          +     ' value="' + (j.queue_order == null ? '' : j.queue_order) + '"'
          +     ' data-field="queue_order" aria-label="Queue number">'
          +   (dup ? '<span class="queue-dup-flag" title="Duplicate queue number">!</span>' : '')
          + '</td>'
          + '<td class="queue-col-job"><span class="job-id">' + escapeHtml(j.job_number || '') + '</span></td>'
          + '<td class="queue-col-cust">'
          +   '<div>' + escapeHtml(j.customer || '—') + '</div>'
          +   '<div class="queue-sub">' + escapeHtml(j.dealer || '') + '</div>'
          + '</td>'
          + '<td class="queue-col-status"><span class="pill ' + statusPillClass(j.status) + '">' + escapeHtml(j.status_label || j.status || '—') + '</span></td>'
          + '<td class="queue-col-parts"><span class="pill ' + partsPillClass(j.parts_status) + '">' + escapeHtml(partsLabel(j.parts_status)) + '</span></td>'
          + '<td class="queue-col-due">' + escapeHtml(fmtDate(j.due_date)) + '</td>'
          + '<td class="queue-col-tech">' + escapeHtml(j.assigned_tech || 'Unassigned') + '</td>'
          + '<td class="queue-col-note">'
          +   '<input type="text" class="queue-note-input" maxlength="240"'
          +     ' value="' + escapeHtml(j.queue_note || '') + '"'
          +     ' data-field="queue_note" placeholder="Add note…" aria-label="Queue note">'
          + '</td>'
          + '<td class="queue-col-actions">'
          +   '<button type="button" class="queue-icon-btn" data-field="queue_visible"'
          +     ' title="' + (j.queue_visible ? 'Hide from queue' : 'Show in queue') + '"'
          +     ' aria-pressed="' + (j.queue_visible ? 'true' : 'false') + '">'
          +     '<span class="material-symbols-outlined">' + (j.queue_visible ? 'visibility' : 'visibility_off') + '</span>'
          +   '</button>'
          + '</td>'
          + '</tr>';
      }).join('');

      var visibleCount = g.jobs.filter(function (j) { return j.queue_visible; }).length;

      return ''
        + '<section class="queue-group" data-group="' + escapeHtml(g.key) + '">'
        + '<header class="queue-group__head">'
        +   '<div class="queue-group__name"><span class="material-symbols-outlined">' + (g.unassigned ? 'help' : 'engineering') + '</span>' + escapeHtml(g.label) + '</div>'
        +   '<div class="queue-group__meta">' + g.jobs.length + ' jobs · ' + visibleCount + ' visible</div>'
        + '</header>'
        + '<div class="queue-table-wrap">'
        + '<table class="queue-table">'
        +   '<thead><tr>'
        +     '<th class="queue-col-num">Queue #</th>'
        +     '<th class="queue-col-job">SO / Job</th>'
        +     '<th class="queue-col-cust">Customer / Dealer</th>'
        +     '<th class="queue-col-status">Status</th>'
        +     '<th class="queue-col-parts">Parts</th>'
        +     '<th class="queue-col-due">Due Date</th>'
        +     '<th class="queue-col-tech">Assigned Tech</th>'
        +     '<th class="queue-col-note">Queue Note</th>'
        +     '<th class="queue-col-actions">Actions</th>'
        +   '</tr></thead>'
        +   '<tbody>' + rows + '</tbody>'
        + '</table>'
        + '</div>'
        + '</section>';
    }).join('');

    body.innerHTML = html;

    // Wire up edits.
    $$('.queue-num-input', body).forEach(function (input) {
      input.addEventListener('input', function () {
        var tr = input.closest('tr'); if (!tr) return;
        var id = parseInt(tr.dataset.job, 10);
        var v  = input.value.trim();
        var parsed = v === '' ? null : parseInt(v, 10);
        if (parsed != null && (!isFinite(parsed) || parsed < 1)) parsed = null;
        recordEdit(id, 'queue_order', parsed);
      });
    });
    $$('.queue-note-input', body).forEach(function (input) {
      input.addEventListener('input', function () {
        var tr = input.closest('tr'); if (!tr) return;
        var id = parseInt(tr.dataset.job, 10);
        recordEdit(id, 'queue_note', input.value);
      });
    });
    $$('.queue-icon-btn[data-field="queue_visible"]', body).forEach(function (btn) {
      btn.addEventListener('click', function () {
        var tr = btn.closest('tr'); if (!tr) return;
        var id = parseInt(tr.dataset.job, 10);
        var j  = effectiveJob(jobById(id));
        recordEdit(id, 'queue_visible', !j.queue_visible);
        renderQueue();
      });
    });

    updateQueueWarnings();
    updateSaveButton();
  }

  function jobById(id) {
    for (var i = 0; i < queueState.jobs.length; i++) {
      if (queueState.jobs[i].id === id) return queueState.jobs[i];
    }
    return null;
  }

  function recordEdit(id, field, value) {
    var snap = jobById(id);
    if (!snap) return;
    var orig = snap[field];
    var bag  = queueState.edits[id] || {};
    var same = (value === orig) || (value == null && orig == null);
    if (same) {
      delete bag[field];
    } else {
      bag[field] = value;
    }
    if (Object.keys(bag).length === 0) {
      delete queueState.edits[id];
    } else {
      queueState.edits[id] = bag;
    }
    updateQueueWarnings();
    updateSaveButton();
  }

  function updateQueueWarnings() {
    var box = document.getElementById('queue-warnings');
    if (!box) return;
    var jobs   = queueState.jobs.map(effectiveJob);
    var groups = {};
    jobs.forEach(function (j) {
      var key = j.assigned_user_id ? ('u:' + j.assigned_user_id) : 'unassigned';
      (groups[key] = groups[key] || { label: j.assigned_user_id ? (j.assigned_tech || 'Tech') : 'Unassigned', jobs: [] }).jobs.push(j);
    });

    var warnings = [];

    Object.keys(groups).forEach(function (k) {
      var g = groups[k];
      // Duplicate queue numbers within group (visible jobs only).
      var seen = {}, dupNums = {};
      g.jobs.forEach(function (j) {
        if (!j.queue_visible || j.queue_order == null) return;
        if (seen[j.queue_order]) dupNums[j.queue_order] = true;
        seen[j.queue_order] = true;
      });
      Object.keys(dupNums).forEach(function (n) {
        warnings.push({
          tone: 'alert',
          text: 'Duplicate queue # ' + n + ' in ' + g.label + ' — only one job can hold a given slot.'
        });
      });

      // Blocked or parts-hold job at queue #1.
      g.jobs.forEach(function (j) {
        if (!j.queue_visible) return;
        if (j.queue_order !== 1) return;
        var ps  = String(j.parts_status || '').toUpperCase();
        var bad = j.status === 'BLOCKED' || ps === 'HOLD' || ps === 'NOT_READY';
        if (bad) {
          warnings.push({
            tone: 'warn',
            text: (j.job_number || ('Job ' + j.id)) + ' is queue #1 in ' + g.label + ' but is ' + (j.status === 'BLOCKED' ? 'blocked' : 'waiting on parts') + '.'
          });
        }
      });
    });

    if (warnings.length === 0) {
      box.hidden = true;
      box.innerHTML = '';
      return;
    }
    box.hidden = false;
    box.innerHTML = warnings.map(function (w) {
      return ''
        + '<div class="queue-warning queue-warning--' + w.tone + '">'
        +   '<span class="material-symbols-outlined">' + (w.tone === 'alert' ? 'error' : 'warning') + '</span>'
        +   '<span>' + escapeHtml(w.text) + '</span>'
        + '</div>';
    }).join('');
  }

  function updateSaveButton() {
    var btn = document.getElementById('queue-save-btn');
    if (!btn) return;
    var n = Object.keys(queueState.edits).length;
    btn.disabled = (n === 0);
    var label = document.getElementById('queue-save-label');
    if (label) label.textContent = n > 0 ? ('Save Queue (' + n + ')') : 'Save Queue';
  }

  function normalizeQueue() {
    var jobs   = queueState.jobs.map(effectiveJob);
    var groups = {};
    jobs.forEach(function (j) {
      var key = j.assigned_user_id ? ('u:' + j.assigned_user_id) : 'unassigned';
      (groups[key] = groups[key] || []).push(j);
    });

    Object.keys(groups).forEach(function (k) {
      var g = groups[k];
      g.sort(function (a, b) {
        var ao = a.queue_order == null ? 1e9 : a.queue_order;
        var bo = b.queue_order == null ? 1e9 : b.queue_order;
        if (ao !== bo) return ao - bo;
        return (a.job_number || '').localeCompare(b.job_number || '');
      });
      var n = 1;
      g.forEach(function (j) {
        if (!j.queue_visible) return;
        if (j.queue_order !== n) {
          recordEdit(j.id, 'queue_order', n);
        }
        n++;
      });
    });
    renderQueue();
    showToast('Queue numbers normalized — Save to commit');
  }

  function saveQueue() {
    var api = queueApi();
    if (!api) return;
    var ids = Object.keys(queueState.edits);
    if (ids.length === 0) return;

    var updates = ids.map(function (id) {
      var e = queueState.edits[id];
      var u = { id: parseInt(id, 10) };
      if ('queue_order'   in e) u.queue_order   = e.queue_order;
      if ('queue_visible' in e) u.queue_visible = !!e.queue_visible;
      if ('queue_note'    in e) u.queue_note    = e.queue_note;
      return u;
    });

    var btn = document.getElementById('queue-save-btn');
    if (btn) btn.disabled = true;

    fetch(api.root + '/cs/queue', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'X-WP-Nonce':   api.nonce,
        'Content-Type': 'application/json',
        'Accept':       'application/json'
      },
      body: JSON.stringify({ updates: updates })
    })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
      .then(function (res) {
        if (!res.ok || !res.body || !res.body.ok) {
          showToast('Save failed — check warnings');
          if (btn) btn.disabled = false;
          return;
        }
        showToast('Queue saved · ' + (res.body.saved || 0) + ' jobs');
        queueState.loaded  = false;
        queueState.edits   = {};
        loadQueue();
      })
      .catch(function () {
        showToast('Save failed');
        if (btn) btn.disabled = false;
      });
  }

  $$('.queue-chip').forEach(function (chip) {
    chip.addEventListener('click', function () {
      $$('.queue-chip').forEach(function (c) { c.classList.remove('active'); });
      chip.classList.add('active');
      queueState.filter = chip.dataset.filter || 'all';
      renderQueue();
    });
  });

  var qSaveBtn = document.getElementById('queue-save-btn');
  if (qSaveBtn) qSaveBtn.addEventListener('click', saveQueue);

  var qNormalizeBtn = document.getElementById('queue-normalize-btn');
  if (qNormalizeBtn) qNormalizeBtn.addEventListener('click', normalizeQueue);

  // Card "Go to" links jump to tab
  $$('[data-jump]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var target = document.querySelector('.ops-subtab[data-tab="' + btn.dataset.jump + '"]');
      if (target) target.click();
      window.scrollTo({ top: 0, behavior: prefersReducedMotion ? 'auto' : 'smooth' });
    });
  });

  // ─── Range chips ──────────────────────────────────────────────────────
  $$('.range-chip').forEach(function (chip) {
    chip.addEventListener('click', function () {
      $$('.range-chip').forEach(function (c) { c.classList.remove('active'); });
      chip.classList.add('active');
      showToast('Range: ' + chip.textContent);
    });
  });

  // ─── Refresh ──────────────────────────────────────────────────────────
  var refreshBtn = document.getElementById('refresh-btn');
  if (refreshBtn) {
    refreshBtn.addEventListener('click', function () {
      var label = document.getElementById('refresh-label');
      if (label) label.textContent = 'Refreshing…';
      setTimeout(function () {
        if (label) label.textContent = 'Refreshed just now';
        animateKPIs();
        renderHealth();
        showToast('Dashboard refreshed');
      }, 600);
    });
  }

  // ─── Init ─────────────────────────────────────────────────────────────
  // Server already rendered the lists; re-render priorities/health to
  // attach event handlers and trigger the initial bar-fill animation.
  renderPriorities();
  renderHealth();
  // Parts / QC / Pickup lists are read-only and have no row interactions,
  // so the server-rendered markup is left in place.
  setTimeout(animateKPIs, 50);

})();
