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

  function showBetaNotice(msg, tone) {
    var notice = document.getElementById('cs-beta-notice');
    if (!notice) return;
    if (!msg) {
      notice.hidden = true;
      notice.textContent = '';
      notice.className = 'cs-beta__notice';
      return;
    }
    notice.hidden = false;
    notice.textContent = msg;
    notice.className = 'cs-beta__notice cs-beta__notice--' + (tone || 'info');
  }

  // ─── Sub-tabs ─────────────────────────────────────────────────────────
  $$('.ops-subtab').forEach(function (btn) {
    btn.addEventListener('click', function () {
      $$('.ops-subtab').forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      if (btn.scrollIntoView) {
        try { btn.scrollIntoView({ block: 'nearest', inline: 'nearest' }); } catch (_) {}
      }
      var tab = btn.dataset.tab;
      state.activeTab = tab;
      $$('.ops-tab-content').forEach(function (c) {
        c.hidden = c.dataset.tabContent !== tab;
      });
      if (tab === 'queue') {
        activateBeta();
      }
    });
  });


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

  // ─── CS Workspace tab ────────────────────────────────────────────────
  // CS-owned queue/detail surface. Reuses GET/POST /cs/queue for queue
  // fields and PATCH /jobs/{id} for general job fields. Manual queue # input
  // remains the keyboard/accessibility fallback alongside drag/drop.
  var betaState = {
    loaded:    false,
    loading:   false,
    jobs:      [],
    users:     [],
    edits:     {},        // { id: { queue_order, queue_visible, queue_note, assigned_user_id, customer_name, dealer_name, sales_person, vin_last8, notes, parts_status, estimated_hours, requested_date } }
    filter:    'all',
    query:     '',
    selected:  null,      // currently selected job id
    drag:      null,      // { jobId, groupKey } while a drag is active
    jobDetails:        {},  // id → full /jobs/{id} response (cached)
    jobDetailsLoading: {}   // id → in-flight Promise
  };

  // Fields persisted via PATCH /jobs/{id} (Phase 4). The remaining keys
  // (queue_order, queue_visible, queue_note, assigned_user_id) go through
  // POST /cs/queue. Splitting at save-time means the bulk queue endpoint
  // stays focused on queue concerns and isn't asked to handle general
  // job edits.
  var BETA_JOB_FIELDS = [
    'customer_name', 'dealer_name', 'sales_person',
    'vin_last8', 'notes',
    'parts_status', 'estimated_hours', 'requested_date'
  ];
  function betaIsJobField(name) { return BETA_JOB_FIELDS.indexOf(name) >= 0; }

  function betaApi() {
    return (window.slateOpsCsDashboard && window.slateOpsCsDashboard.api) || null;
  }

  function activateBeta() {
    if (betaState.loaded || betaState.loading) {
      renderBeta();
      return;
    }
    loadBeta();
  }

  function loadBeta(opts) {
    var api = betaApi();
    var body = document.getElementById('cs-beta-body');
    if (!api) {
      if (body) body.innerHTML = '<div class="cs-beta__placeholder cs-beta__placeholder--error"><span class="material-symbols-outlined">error</span><span>CS Workspace API not available.</span></div>';
      return;
    }
    if (!opts || !opts.silent) {
      betaState.loading = true;
      if (body) {
        body.innerHTML = '<div class="cs-beta__placeholder"><span class="material-symbols-outlined cs-beta__spinner">progress_activity</span><span>Loading queue…</span></div>';
      }
    }
    var queueRequest = fetch(api.root + '/cs/queue', {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'X-WP-Nonce': api.nonce, 'Accept': 'application/json' }
    })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); });

    var usersRequest = fetch(api.root + '/users', {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'X-WP-Nonce': api.nonce, 'Accept': 'application/json' }
    })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
      .catch(function () { return { ok: false, body: { users: [] } }; });

    Promise.all([queueRequest, usersRequest])
      .then(function (results) {
        var res = results[0];
        var usersRes = results[1];
        betaState.loading = false;
        if (!res.ok || !res.body || !res.body.ok) {
          if (body) body.innerHTML = '<div class="cs-beta__placeholder cs-beta__placeholder--error"><span class="material-symbols-outlined">error</span><span>Failed to load CS Workspace.</span></div>';
          return;
        }
        betaState.jobs   = res.body.jobs || [];
        betaState.users  = (usersRes && usersRes.ok && usersRes.body && usersRes.body.users) ? usersRes.body.users : [];
        betaState.edits  = {};
        betaState.loaded = true;
        renderBeta();
      })
      .catch(function () {
        betaState.loading = false;
        if (body) body.innerHTML = '<div class="cs-beta__placeholder cs-beta__placeholder--error"><span class="material-symbols-outlined">error</span><span>Failed to load CS Workspace.</span></div>';
      });
  }

  function betaJobById(id) {
    for (var i = 0; i < betaState.jobs.length; i++) {
      if (betaState.jobs[i].id === id) return betaState.jobs[i];
    }
    return null;
  }

  function betaTechDirectory() {
    // Build a map of user_id → display name from the current server snapshot.
    // Used to label the moved row's tech after a cross-tech drag.
    var map = {};
    betaState.users.forEach(function (u) {
      if (u.id && u.name && !map[u.id]) {
        map[u.id] = u.name;
      }
    });
    betaState.jobs.forEach(function (j) {
      if (j.assigned_user_id && j.assigned_tech && !map[j.assigned_user_id]) {
        map[j.assigned_user_id] = j.assigned_tech;
      }
    });
    return map;
  }

  function betaTechUsers() {
    var users = [];
    var seen = {};

    betaState.users.forEach(function (u) {
      if (!u || !u.id) return;
      if (u.ops_role !== 'slate_ops_tech') return;
      seen[u.id] = true;
      users.push({ id: u.id, name: u.name || ('User #' + u.id) });
    });

    betaState.jobs.forEach(function (j) {
      if (!j.assigned_user_id || seen[j.assigned_user_id]) return;
      seen[j.assigned_user_id] = true;
      users.push({ id: j.assigned_user_id, name: j.assigned_tech || ('User #' + j.assigned_user_id) });
    });

    users.sort(function (a, b) { return a.name.localeCompare(b.name); });
    return users;
  }

  function betaTechOptions(selectedId) {
    var selected = selectedId == null ? '' : String(selectedId);
    var html = '<option value=""' + (selected === '' ? ' selected' : '') + '>Unassigned</option>';
    betaTechUsers().forEach(function (u) {
      var id = String(u.id);
      html += '<option value="' + escapeHtml(id) + '"' + (selected === id ? ' selected' : '') + '>' + escapeHtml(u.name) + '</option>';
    });
    return html;
  }

  function betaEffectiveJob(j) {
    var e = betaState.edits[j.id] || {};
    var hasAssignmentEdit = ('assigned_user_id' in e);
    var aid = hasAssignmentEdit ? e.assigned_user_id : j.assigned_user_id;
    var atech;
    if (hasAssignmentEdit) {
      if (aid == null) {
        atech = '';
      } else {
        var dir = betaTechDirectory();
        atech = dir[aid] || ('User #' + aid);
      }
    } else {
      atech = j.assigned_tech;
    }
    return {
      id:               j.id,
      job_number:       j.job_number,
      so_number:        j.so_number,
      customer:         e.customer_name !== undefined ? e.customer_name : j.customer,
      dealer:           e.dealer_name   !== undefined ? e.dealer_name   : j.dealer,
      status:           j.status,
      status_label:     j.status_label,
      parts_status:     e.parts_status  !== undefined ? e.parts_status  : j.parts_status,
      due_date:         j.due_date,
      promised_date:    j.promised_date,
      scheduled_start:  j.scheduled_start,
      assigned_user_id: aid,
      assigned_tech:    atech,
      queue_priority:   j.queue_priority,
      queue_order:      e.queue_order   !== undefined ? e.queue_order   : j.queue_order,
      queue_visible:    e.queue_visible !== undefined ? e.queue_visible : j.queue_visible,
      queue_note:       e.queue_note    !== undefined ? e.queue_note    : (j.queue_note || ''),
      _dirty:           Object.keys(e).length > 0,
      _reassigned:      hasAssignmentEdit && aid !== j.assigned_user_id,
      _orig_assigned_user_id: j.assigned_user_id,
      _orig_assigned_tech:    j.assigned_tech || ''
    };
  }

  /**
   * Single source of truth for "is this job in a parts-blocking state".
   * Both HOLD and NOT_READY count — the Tech can't make progress on
   * either, so CS treats them the same on the parts hold filter, the
   * parts-hold counter, the parts pill class, and the queue-#1 warning.
   *
   * Adjust this list once if the parts-status taxonomy ever expands
   * (e.g. an explicit PARTIAL_HOLD value).
   */
  function betaIsPartsHold(parts_status) {
    var ps = String(parts_status || '').toUpperCase();
    return ps === 'HOLD' || ps === 'NOT_READY';
  }

  // Shared display helpers used by the row, the detail panel, and the
  // overview's existing pills.
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
    if (betaIsPartsHold(ps))                              return 'pill--blocked';
    var v = String(ps || '').toUpperCase();
    if (v === 'PARTIAL')                                  return 'pill--parts';
    if (v === 'READY')                                    return 'pill--ready';
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
    return String(s).substr(0, 10);
  }

  function betaPassesChip(j) {
    switch (betaState.filter) {
      case 'all':        return true;
      case 'ready':      return j.status === 'READY_FOR_BUILD';
      case 'scheduled':  return j.status === 'SCHEDULED';
      case 'inprog':     return j.status === 'IN_PROGRESS';
      case 'blocked':    return j.status === 'BLOCKED';
      case 'closeout':   return j.status === 'QC' || j.status === 'PENDING_QC';
      case 'unassigned': return !j.assigned_user_id;
      case 'parts':      return betaIsPartsHold(j.parts_status);
      default:           return true;
    }
  }

  function betaPassesQuery(j) {
    var q = betaState.query;
    if (!q) return true;
    var hay = [
      j.so_number, j.job_number, j.customer, j.dealer,
      j.assigned_tech, j.queue_note, j.status_label
    ].join(' ').toLowerCase();
    return hay.indexOf(q) !== -1;
  }

  function betaCounts(jobs) {
    var c = { all: 0, ready: 0, scheduled: 0, inprog: 0, blocked: 0, closeout: 0, unassigned: 0, parts: 0 };
    jobs.forEach(function (j) {
      c.all++;
      if (j.status === 'READY_FOR_BUILD')                  c.ready++;
      if (j.status === 'SCHEDULED')                        c.scheduled++;
      if (j.status === 'IN_PROGRESS')                      c.inprog++;
      if (j.status === 'BLOCKED')                          c.blocked++;
      if (j.status === 'QC' || j.status === 'PENDING_QC')  c.closeout++;
      if (!j.assigned_user_id)                             c.unassigned++;
      if (betaIsPartsHold(j.parts_status))                 c.parts++;
    });
    return c;
  }

  function betaInitials(name) {
    var s = String(name || '').trim();
    if (!s) return '?';
    var parts = s.split(/\s+/);
    var a = parts[0] ? parts[0].charAt(0) : '';
    var b = parts[1] ? parts[1].charAt(0) : '';
    return (a + b).toUpperCase() || s.charAt(0).toUpperCase();
  }

  function betaFmtNote(s) {
    if (!s) return '';
    var t = String(s).trim();
    if (t.length > 60) return t.slice(0, 57) + '…';
    return t;
  }

  function renderBetaCounts(allJobs) {
    var counts = betaCounts(allJobs);
    Object.keys(counts).forEach(function (k) {
      var el = document.querySelector('.cs-beta-chip__count[data-count="' + k + '"]');
      if (el) el.textContent = counts[k];
    });
    var queueCount = document.getElementById('queue-tab-count');
    if (queueCount) queueCount.textContent = counts.all;
  }

  function betaGroupKey(j) {
    return j.assigned_user_id ? ('u:' + j.assigned_user_id) : 'unassigned';
  }

  function betaWarningHtml(w) {
    return ''
      + '<div class="cs-beta-warning cs-beta-warning--' + escapeHtml(w.tone) + '">'
      +   '<span class="material-symbols-outlined">' + (w.tone === 'alert' ? 'error' : 'warning') + '</span>'
      +   '<span>' + escapeHtml(w.text) + '</span>'
      + '</div>';
  }

  function betaWarningsByGroup(allJobs) {
    var groups = {};
    allJobs.forEach(function (j) {
      var key = betaGroupKey(j);
      if (!groups[key]) {
        groups[key] = {
          label: j.assigned_user_id ? (j.assigned_tech || 'Tech') : 'Unassigned',
          jobs: []
        };
      }
      groups[key].jobs.push(j);
    });

    var warnings = {};
    function add(key, tone, text) {
      (warnings[key] = warnings[key] || []).push({ tone: tone, text: text });
    }

    Object.keys(groups).forEach(function (k) {
      var g = groups[k];
      var seen = {}, dupNums = {};
      g.jobs.forEach(function (j) {
        if (!j.queue_visible || j.queue_order == null) return;
        if (seen[j.queue_order]) dupNums[j.queue_order] = true;
        seen[j.queue_order] = true;
      });
      Object.keys(dupNums).forEach(function (n) {
        add(k, 'alert', 'Duplicate queue # ' + n + ' in ' + g.label + ' — only one job can hold a given slot.');
      });
      g.jobs.forEach(function (j) {
        if (j.status === 'SCHEDULED' && !j.assigned_user_id) {
          add(k, 'alert', (j.so_number || j.job_number || ('Job ' + j.id)) + ' is Scheduled but has no assigned tech.');
        }
        if (!j.queue_visible || j.queue_order !== 1) return;
        var bad = j.status === 'BLOCKED' || betaIsPartsHold(j.parts_status);
        if (bad) {
          add(k, 'warn', (j.so_number || j.job_number || ('Job ' + j.id)) + ' is queue #1 in ' + g.label + ' but is ' + (j.status === 'BLOCKED' ? 'blocked' : 'on parts hold') + '.');
        }
      });
    });

    return warnings;
  }

  function betaGroupWarningsHtml(groupKey, warningsByGroup) {
    var warnings = warningsByGroup[groupKey] || [];
    if (warnings.length === 0) {
      return '<div class="cs-beta-group__warnings" data-group-warnings="' + escapeHtml(groupKey) + '" hidden></div>';
    }
    return ''
      + '<div class="cs-beta-group__warnings" data-group-warnings="' + escapeHtml(groupKey) + '">'
      + warnings.map(betaWarningHtml).join('')
      + '</div>';
  }

  function renderBeta() {
    var body = document.getElementById('cs-beta-body');
    if (!body) return;

    var allJobs   = betaState.jobs.map(betaEffectiveJob);
    var warningsByGroup = betaWarningsByGroup(allJobs);
    renderBetaCounts(allJobs);

    var filtered  = allJobs.filter(betaPassesChip).filter(betaPassesQuery);

    // Group by tech
    var groups   = {};
    var keyOrder = [];
    filtered.forEach(function (j) {
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

    // Pin Unassigned to the top of the queue so CS sees the work that
    // still needs a tech first; tech groups follow in alphabetical order.
    keyOrder.sort(function (a, b) {
      if (groups[a].unassigned && !groups[b].unassigned) return -1;
      if (!groups[a].unassigned && groups[b].unassigned) return 1;
      return groups[a].label.localeCompare(groups[b].label);
    });

    if (keyOrder.length === 0) {
      var emptyMsg = (betaState.query || betaState.filter !== 'all')
        ? 'No jobs match this filter'
        : 'No active jobs in the queue right now.';
      var emptySub = (betaState.query || betaState.filter !== 'all')
        ? 'Try a different filter or clear all to see every job.'
        : 'Create a job or move active work into the queue to begin ordering.';
      body.innerHTML = ''
        + '<div class="cs-beta__placeholder cs-beta__placeholder--empty">'
        +   '<span class="material-symbols-outlined">inbox</span>'
        +   '<span class="cs-beta__empty-copy">'
        +     '<strong>' + escapeHtml(emptyMsg) + '</strong>'
        +     '<small>' + escapeHtml(emptySub) + '</small>'
        +   '</span>'
        + '</div>';
      updateBetaWarnings(allJobs);
      updateBetaSaveButton();
      renderBetaDetail();
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
      var seen = {}, dupSet = {};
      g.jobs.forEach(function (j) {
        if (!j.queue_visible || j.queue_order == null) return;
        if (seen[j.queue_order]) dupSet[j.queue_order] = true;
        seen[j.queue_order] = true;
      });

      var visibleCount = g.jobs.filter(function (j) { return j.queue_visible; }).length;

      var rows = g.jobs.map(function (j) {
        var dup        = j.queue_visible && j.queue_order != null && dupSet[j.queue_order];
        var blockedTop = j.queue_visible && j.queue_order === 1 && (j.status === 'BLOCKED' || betaIsPartsHold(j.parts_status));
        var sel        = (betaState.selected === j.id);
        var qTitle     = (!j.assigned_user_id && j.queue_order == null)
          ? 'Assign a tech before queue order affects the Tech page.'
          : 'Queue number';

        var rowCls = 'cs-beta-row';
        if (j._dirty)         rowCls += ' is-dirty';
        if (sel)              rowCls += ' is-selected';
        if (!j.queue_visible) rowCls += ' is-hidden';
        if (dup)              rowCls += ' is-dup';
        if (blockedTop)       rowCls += ' is-warn';
        if (j._reassigned)    rowCls += ' is-reassigned';
        if (j.status === 'SCHEDULED' && !j.assigned_user_id) rowCls += ' is-missing-tech';

        var noteText = betaFmtNote(j.queue_note);
        var noteIcon = j.queue_note ? 'sticky_note_2' : '';

        return ''
          + '<div class="' + rowCls + '" data-job="' + j.id + '" tabindex="0" role="button">'
          +   '<div class="cs-beta-row__handle" title="Drag to reorder within ' + escapeHtml(g.label) + '" aria-label="Drag handle">'
          +     '<span class="material-symbols-outlined">drag_indicator</span>'
          +   '</div>'
          +   '<div class="cs-beta-row__qnum">'
          +     '<input type="number" min="1" step="1" class="cs-beta-row__qinput"'
          +       ' value="' + (j.queue_order == null ? '' : j.queue_order) + '"'
          +       ' data-field="queue_order" aria-label="Queue number" title="' + escapeHtml(qTitle) + '">'
          +     (dup ? '<span class="cs-beta-row__dup" title="Duplicate queue number">!</span>' : '')
          +   '</div>'
          +   '<div class="cs-beta-row__so"><span class="cs-beta-mono">' + escapeHtml(j.so_number || j.job_number || '') + '</span></div>'
          +   '<div class="cs-beta-row__cust">'
          +     '<div class="cs-beta-row__name">' + escapeHtml(j.customer || '—') + '</div>'
          +     '<div class="cs-beta-row__sub">' + escapeHtml(j.dealer || '') + '</div>'
          +     (j._reassigned
                ? '<div class="cs-beta-row__staged" title="Reassigned from ' + escapeHtml(j._orig_assigned_tech || 'Unassigned') + '">'
                +     '<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>'
                +     'Reassigned to ' + escapeHtml(j.assigned_tech || 'Unassigned')
                + '</div>'
                : '')
          +   '</div>'
          +   '<div class="cs-beta-row__status"><span class="pill ' + statusPillClass(j.status) + '">' + escapeHtml(j.status_label || j.status || '—') + '</span></div>'
          +   '<div class="cs-beta-row__parts"><span class="pill ' + partsPillClass(j.parts_status) + '">' + escapeHtml(partsLabel(j.parts_status)) + '</span></div>'
          +   '<div class="cs-beta-row__tech">'
          +     '<span class="cs-beta-avatar' + (!j.assigned_user_id ? ' cs-beta-avatar--unassigned' : '') + '" aria-hidden="true">' + escapeHtml(j.assigned_user_id ? betaInitials(j.assigned_tech) : 'UN') + '</span>'
          +     '<select class="cs-beta-row__techselect" data-field="assigned_user_id" aria-label="Assigned tech">'
          +       betaTechOptions(j.assigned_user_id)
          +     '</select>'
          +     (j.status === 'SCHEDULED' && !j.assigned_user_id ? '<span class="cs-beta-row__tag cs-beta-row__tag--warn">No tech</span>' : '')
          +   '</div>'
          +   '<div class="cs-beta-row__due cs-beta-mono">' + escapeHtml(fmtDate(j.due_date)) + '</div>'
          +   '<div class="cs-beta-row__note">'
          +     (noteIcon ? '<span class="material-symbols-outlined cs-beta-row__noteicon" aria-hidden="true">' + noteIcon + '</span>' : '<span class="cs-beta-row__noteicon-spacer" aria-hidden="true"></span>')
          +     '<span class="cs-beta-row__notetext">' + (noteText ? escapeHtml(noteText) : '<span class="cs-beta-row__notetext--empty">—</span>') + '</span>'
          +   '</div>'
          +   '<div class="cs-beta-row__actions">'
          +     '<button type="button" class="cs-beta-row__iconbtn" data-action="toggle-visible"'
          +       ' aria-pressed="' + (j.queue_visible ? 'true' : 'false') + '"'
          +       ' title="' + (j.queue_visible ? 'Hide from queue' : 'Show in queue') + '">'
          +       '<span class="material-symbols-outlined">' + (j.queue_visible ? 'visibility' : 'visibility_off') + '</span>'
          +     '</button>'
          +     '<button type="button" class="cs-beta-row__iconbtn" data-action="open-detail" title="Open job detail" aria-label="Open job detail">'
          +       '<span class="material-symbols-outlined">chevron_right</span>'
          +     '</button>'
          +   '</div>'
          + '</div>';
      }).join('');

      var totalHrsBlock = ''; // est. hours not in /cs/queue payload yet — omit per spec.

      var sectionCls = 'cs-beta-group' + (g.unassigned ? ' cs-beta-group--unassigned' : '');

      return ''
        + '<section class="' + sectionCls + '" data-group="' + escapeHtml(g.key) + '">'
        +   '<header class="cs-beta-group__head">'
        +     '<div class="cs-beta-group__name">'
        +       '<span class="cs-beta-avatar cs-beta-avatar--lg' + (g.unassigned ? ' cs-beta-avatar--ghost' : '') + '" aria-hidden="true">' + escapeHtml(betaInitials(g.label)) + '</span>'
        +       '<div>'
        +         '<div class="cs-beta-group__title">' + escapeHtml(g.label) + '</div>'
        +         '<div class="cs-beta-group__meta">' + g.jobs.length + ' jobs · ' + visibleCount + ' visible' + (g.unassigned ? ' · needs assignment' : '') + '</div>'
        +         (g.unassigned ? '<div class="cs-beta-group__helper">Assign a tech before queue order affects the Tech page.</div>' : '')
        +       '</div>'
        +     '</div>'
        +     '<div class="cs-beta-group__right">' + totalHrsBlock + '</div>'
        +   '</header>'
        +   betaGroupWarningsHtml(g.key, warningsByGroup)
        +   '<div class="cs-beta-list">'
        +     '<div class="cs-beta-list__head" aria-hidden="true">'
        +       '<span></span>'
        +       '<span>Q#</span>'
        +       '<span>SO #</span>'
        +       '<span>Customer / Dealer</span>'
        +       '<span>Status</span>'
        +       '<span>Parts</span>'
        +       '<span>Tech</span>'
        +       '<span>Due</span>'
        +       '<span>Note</span>'
        +       '<span></span>'
        +     '</div>'
        +     rows
        +   '</div>'
        + '</section>';
    }).join('');

    body.innerHTML = html;
    wireBetaRows();
    updateBetaWarnings(allJobs);
    updateBetaSaveButton();
    renderBetaDetail();
  }

  function wireBetaRows() {
    var body = document.getElementById('cs-beta-body');
    if (!body) return;

    $$('.cs-beta-row__qinput', body).forEach(function (input) {
      input.addEventListener('click',  function (e) { e.stopPropagation(); });
      input.addEventListener('input',  function () {
        var row = input.closest('.cs-beta-row'); if (!row) return;
        var id  = parseInt(row.dataset.job, 10);
        var v   = input.value.trim();
        var parsed = v === '' ? null : parseInt(v, 10);
        if (parsed != null && (!isFinite(parsed) || parsed < 1)) parsed = null;
        recordBetaEdit(id, 'queue_order', parsed);
      });
    });

    $$('.cs-beta-row__techselect', body).forEach(function (select) {
      select.addEventListener('click', function (e) { e.stopPropagation(); });
      select.addEventListener('keydown', function (e) { e.stopPropagation(); });
      select.addEventListener('change', function () {
        var row = select.closest('.cs-beta-row'); if (!row) return;
        var id  = parseInt(row.dataset.job, 10);
        var v   = select.value.trim();
        var parsed = v === '' ? null : parseInt(v, 10);
        if (parsed != null && (!isFinite(parsed) || parsed <= 0)) parsed = null;
        recordBetaEdit(id, 'assigned_user_id', parsed);
        renderBeta();
      });
    });

    $$('.cs-beta-row__iconbtn[data-action="toggle-visible"]', body).forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var row = btn.closest('.cs-beta-row'); if (!row) return;
        var id  = parseInt(row.dataset.job, 10);
        var snap = betaJobById(id); if (!snap) return;
        var cur  = betaEffectiveJob(snap);
        recordBetaEdit(id, 'queue_visible', !cur.queue_visible);
        renderBeta();
      });
    });

    $$('.cs-beta-row__iconbtn[data-action="open-detail"]', body).forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var row = btn.closest('.cs-beta-row'); if (!row) return;
        var id  = parseInt(row.dataset.job, 10);
        selectBetaJob(id);
      });
    });

    $$('.cs-beta-row', body).forEach(function (row) {
      row.addEventListener('click', function () {
        var id = parseInt(row.dataset.job, 10);
        selectBetaJob(id);
      });
      row.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          var id = parseInt(row.dataset.job, 10);
          selectBetaJob(id);
        }
      });
    });

    // ── Drag/drop reorder (Phase 2) + cross-tech reassign (Phase 3) ────
    // Only the drag handle initiates a drag; clicking a row, input, or
    // button never starts one. Same-group drops reorder; cross-group
    // drops stage a tech reassignment. After every drop, visible jobs
    // in each affected group get renumbered 1, 2, 3 and changed rows
    // are marked dirty.
    $$('.cs-beta-row__handle', body).forEach(function (handle) {
      handle.addEventListener('mousedown', function () {
        var row = handle.closest('.cs-beta-row');
        if (row) row.setAttribute('draggable', 'true');
      });
    });

    $$('.cs-beta-row', body).forEach(function (row) {
      row.addEventListener('dragstart', function (e) {
        if (row.getAttribute('draggable') !== 'true') {
          e.preventDefault();
          return;
        }
        var section = row.closest('.cs-beta-group');
        var groupKey = section ? section.getAttribute('data-group') : null;
        betaState.drag = {
          jobId:    parseInt(row.dataset.job, 10),
          groupKey: groupKey
        };
        row.classList.add('is-dragging');
        if (e.dataTransfer) {
          e.dataTransfer.effectAllowed = 'move';
          try { e.dataTransfer.setData('text/plain', String(row.dataset.job)); } catch (_) {}
        }
      });

      row.addEventListener('dragend', function () {
        row.classList.remove('is-dragging');
        row.setAttribute('draggable', 'false');
        clearBetaDropIndicators();
        setBetaDropZone(null);
        betaState.drag = null;
      });

      row.addEventListener('dragover', function (e) {
        if (!betaState.drag) return;
        if (parseInt(row.dataset.job, 10) === betaState.drag.jobId) return;
        var section = row.closest('.cs-beta-group');
        var thisGroup = section ? section.getAttribute('data-group') : null;
        e.preventDefault();
        if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';

        var sameGroup = (thisGroup === betaState.drag.groupKey);
        var rect = row.getBoundingClientRect();
        var above = e.clientY < (rect.top + rect.height / 2);
        clearBetaDropIndicators();
        row.classList.add(above ? 'is-drop-above' : 'is-drop-below');
        // Group-level drop zone outline only on cross-tech to make the
        // reassignment intent explicit.
        setBetaDropZone(sameGroup ? null : section);
      });

      row.addEventListener('dragleave', function () {
        row.classList.remove('is-drop-above', 'is-drop-below');
      });

      row.addEventListener('drop', function (e) {
        if (!betaState.drag) return;
        if (parseInt(row.dataset.job, 10) === betaState.drag.jobId) return;
        var section = row.closest('.cs-beta-group');
        var thisGroup = section ? section.getAttribute('data-group') : null;
        e.preventDefault();

        var dropAbove = row.classList.contains('is-drop-above');
        var refId     = parseInt(row.dataset.job, 10);
        var draggedId = betaState.drag.jobId;
        clearBetaDropIndicators();
        setBetaDropZone(null);

        if (thisGroup === betaState.drag.groupKey) {
          applyBetaReorder(draggedId, refId, dropAbove);
        } else {
          applyBetaReorder(draggedId, refId, dropAbove, {
            targetUserId: betaTargetUserIdFromKey(thisGroup)
          });
        }
      });
    });

    // Group-level drop: catches drops that land in empty space at the
    // bottom of the list, between rows, or anywhere on a group with
    // no rows yet (only possible for an empty Unassigned group).
    $$('.cs-beta-group', body).forEach(function (section) {
      section.addEventListener('dragover', function (e) {
        if (!betaState.drag) return;
        var thisGroup = section.getAttribute('data-group');
        e.preventDefault();
        if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
        var sameGroup = (thisGroup === betaState.drag.groupKey);
        setBetaDropZone(sameGroup ? null : section);
      });
      section.addEventListener('dragleave', function (e) {
        // Only clear if we're leaving the section entirely. dragleave
        // fires when entering a child too, so verify the related target.
        if (!e.relatedTarget || !section.contains(e.relatedTarget)) {
          if (section.classList.contains('is-drop-zone')) {
            section.classList.remove('is-drop-zone');
          }
        }
      });
      section.addEventListener('drop', function (e) {
        if (!betaState.drag) return;
        if (e.defaultPrevented) return; // a row drop already handled it
        var thisGroup = section.getAttribute('data-group');
        var sameGroup = (thisGroup === betaState.drag.groupKey);
        var rows = $$('.cs-beta-row', section);
        var lastRow = rows[rows.length - 1];
        e.preventDefault();
        clearBetaDropIndicators();
        setBetaDropZone(null);

        if (sameGroup) {
          if (!lastRow) return;
          if (parseInt(lastRow.dataset.job, 10) === betaState.drag.jobId) return;
          applyBetaReorder(betaState.drag.jobId, parseInt(lastRow.dataset.job, 10), false);
        } else {
          var refId = lastRow ? parseInt(lastRow.dataset.job, 10) : null;
          applyBetaReorder(betaState.drag.jobId, refId, false, {
            targetUserId: betaTargetUserIdFromKey(thisGroup)
          });
        }
      });
    });
  }

  function betaTargetUserIdFromKey(groupKey) {
    if (!groupKey || groupKey === 'unassigned') return null;
    var m = /^u:(\d+)$/.exec(groupKey);
    return m ? parseInt(m[1], 10) : null;
  }

  function clearBetaDropIndicators() {
    $$('.cs-beta-row.is-drop-above, .cs-beta-row.is-drop-below').forEach(function (el) {
      el.classList.remove('is-drop-above', 'is-drop-below');
    });
  }

  function setBetaDropZone(section) {
    $$('.cs-beta-group.is-drop-zone').forEach(function (el) {
      if (el !== section) el.classList.remove('is-drop-zone');
    });
    if (section) section.classList.add('is-drop-zone');
  }

  /**
   * Reorder a job within its tech group, or — when `opts.targetUserId`
   * is provided (including null for Unassigned) — move it across groups
   * and stage an `assigned_user_id` reassignment edit.
   *
   * `refId` is the job whose row the cursor was over at drop time;
   * pass `null` to append to the end of the target group.
   */
  function applyBetaReorder(draggedId, refId, dropAbove, opts) {
    opts = opts || {};
    var crossTech     = ('targetUserId' in opts);
    var newAssignedId = crossTech ? (opts.targetUserId == null ? null : (opts.targetUserId | 0)) : undefined;

    var allJobs = betaState.jobs.map(betaEffectiveJob);
    var dragged = null;
    for (var i = 0; i < allJobs.length; i++) {
      if (allJobs[i].id === draggedId) { dragged = allJobs[i]; break; }
    }
    if (!dragged) return;

    var srcKey = dragged.assigned_user_id ? ('u:' + dragged.assigned_user_id) : 'unassigned';
    var tgtKey;
    if (crossTech) {
      tgtKey = newAssignedId ? ('u:' + newAssignedId) : 'unassigned';
    } else {
      tgtKey = srcKey;
    }

    // Same sort order as renderBeta uses for display.
    var sortFn = function (a, b) {
      var ao = a.queue_order == null ? 1e9 : a.queue_order;
      var bo = b.queue_order == null ? 1e9 : b.queue_order;
      if (ao !== bo) return ao - bo;
      return (a.job_number || '').localeCompare(b.job_number || '');
    };

    var srcGroup = allJobs.filter(function (j) {
      var k = j.assigned_user_id ? ('u:' + j.assigned_user_id) : 'unassigned';
      return k === srcKey;
    }).sort(sortFn);

    var tgtGroup;
    if (srcKey === tgtKey) {
      tgtGroup = srcGroup;
    } else {
      tgtGroup = allJobs.filter(function (j) {
        if (j.id === draggedId) return false;
        var k = j.assigned_user_id ? ('u:' + j.assigned_user_id) : 'unassigned';
        return k === tgtKey;
      }).sort(sortFn);
    }

    // Stage the assignment edit BEFORE renumbering so subsequent
    // recordBetaEdit() calls see the correct group context.
    if (crossTech) {
      recordBetaEdit(draggedId, 'assigned_user_id', newAssignedId);
      // Update the local `dragged` object's group affiliation so the
      // splice/insert math below routes it to the target group.
      dragged.assigned_user_id = newAssignedId;
    }

    // Same-group reorder: just splice within srcGroup.
    if (srcKey === tgtKey) {
      var fromIdx = -1;
      for (var i2 = 0; i2 < srcGroup.length; i2++) {
        if (srcGroup[i2].id === draggedId) { fromIdx = i2; break; }
      }
      if (fromIdx === -1) return;
      srcGroup.splice(fromIdx, 1);

      var refIdx = -1;
      for (var i3 = 0; i3 < srcGroup.length; i3++) {
        if (srcGroup[i3].id === refId) { refIdx = i3; break; }
      }
      var insertAt = (refIdx === -1) ? srcGroup.length : (dropAbove ? refIdx : refIdx + 1);
      srcGroup.splice(insertAt, 0, dragged);
      betaRenumberGroup(srcGroup);
      renderBeta();
      return;
    }

    // Cross-tech: remove from src, insert into tgt at the right spot.
    var srcIdx = -1;
    for (var i4 = 0; i4 < srcGroup.length; i4++) {
      if (srcGroup[i4].id === draggedId) { srcIdx = i4; break; }
    }
    if (srcIdx !== -1) srcGroup.splice(srcIdx, 1);

    var tRefIdx = -1;
    if (refId != null) {
      for (var i5 = 0; i5 < tgtGroup.length; i5++) {
        if (tgtGroup[i5].id === refId) { tRefIdx = i5; break; }
      }
    }
    var tInsertAt = (tRefIdx === -1) ? tgtGroup.length : (dropAbove ? tRefIdx : tRefIdx + 1);
    tgtGroup.splice(tInsertAt, 0, dragged);

    // Renumber both groups (visible jobs only).
    betaRenumberGroup(srcGroup);
    betaRenumberGroup(tgtGroup);

    // Move the focused selection with the dragged row so the detail
    // panel keeps showing this job.
    if (betaState.selected !== draggedId) betaState.selected = draggedId;

    renderBeta();
  }

  function betaRenumberGroup(group) {
    var n = 1;
    group.forEach(function (j) {
      if (!j.queue_visible) return;
      if (j.queue_order !== n) {
        recordBetaEdit(j.id, 'queue_order', n);
      }
      n++;
    });
  }

  /**
   * Look up a field's "server snapshot" value for dirty-comparison.
   * Queue fields come from /cs/queue; PATCH /jobs/{id} fields come from
   * the per-row jobDetails cache (must be populated first by selecting
   * the row, which lazy-loads /jobs/{id}).
   */
  function betaOrigFieldValue(id, field) {
    if (betaIsJobField(field)) {
      var det = betaState.jobDetails[id];
      if (!det) return undefined;
      if (field === 'estimated_hours') {
        var m = det.estimated_minutes != null ? Number(det.estimated_minutes) : 0;
        return m > 0 ? String(+(m / 60).toFixed(2)) : '';
      }
      if (field === 'vin_last8') {
        return String(det.vin_last8 || det.vin || '');
      }
      var v = det[field];
      return v == null ? '' : String(v);
    }
    var snap = betaJobById(id);
    if (!snap) return undefined;
    return snap[field];
  }

  function recordBetaEdit(id, field, value) {
    if (!betaJobById(id)) return;
    var orig = betaOrigFieldValue(id, field);
    var bag  = betaState.edits[id] || {};
    // For job fields the orig might be undefined if the detail hasn't
    // loaded yet; in that case treat any input as a tentative edit.
    var same = (orig !== undefined) && ((value === orig) || (value == null && orig == null));
    if (same) {
      delete bag[field];
    } else {
      bag[field] = value;
    }
    if (Object.keys(bag).length === 0) {
      delete betaState.edits[id];
    } else {
      betaState.edits[id] = bag;
    }
    // Lightweight repaint — DO NOT call renderBetaDetail() here, that
    // would clobber input focus on every keystroke. Detail panel inputs
    // are uncontrolled; their values stay in the DOM, and edit state is
    // tracked in betaState.edits.
    var allJobs = betaState.jobs.map(betaEffectiveJob);
    updateBetaWarnings(allJobs);
    updateBetaSaveButton();
    renderBetaCounts(allJobs);
    var row = document.querySelector('.cs-beta-row[data-job="' + id + '"]');
    if (row) {
      if (betaState.edits[id]) row.classList.add('is-dirty');
      else row.classList.remove('is-dirty');
    }
    // The detail-panel "staged" / "Was X" hints depend on staged
    // assigned_user_id changes; only re-render the panel for that one
    // field, since drag triggers a full renderBeta() anyway.
  }

  /**
   * Lazy-load the full /jobs/{id} payload for the editable detail form
   * and cache it. Returns a promise resolved with the job object.
   */
  function ensureBetaJobDetail(id) {
    if (betaState.jobDetails[id]) return Promise.resolve(betaState.jobDetails[id]);
    if (betaState.jobDetailsLoading[id]) return betaState.jobDetailsLoading[id];
    var api = betaApi();
    if (!api) return Promise.reject(new Error('no_api'));
    var p = fetch(api.root + '/jobs/' + id, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'X-WP-Nonce': api.nonce, 'Accept': 'application/json' }
    })
      .then(function (r) {
        if (!r.ok) throw new Error('fetch_failed_' + r.status);
        return r.json();
      })
      .then(function (job) {
        betaState.jobDetails[id] = job || {};
        delete betaState.jobDetailsLoading[id];
        return betaState.jobDetails[id];
      })
      .catch(function (err) {
        delete betaState.jobDetailsLoading[id];
        throw err;
      });
    betaState.jobDetailsLoading[id] = p;
    return p;
  }

  function selectBetaJob(id) {
    betaState.selected = id;
    $$('.cs-beta-row.is-selected').forEach(function (el) { el.classList.remove('is-selected'); });
    var row = document.querySelector('.cs-beta-row[data-job="' + id + '"]');
    if (row) row.classList.add('is-selected');
    renderBetaDetail();
    // Background-fetch the full /jobs/{id} so the editable form fields
    // can render. If it's already cached, the promise resolves
    // immediately and the second render is a no-op.
    ensureBetaJobDetail(id)
      .then(function () { if (betaState.selected === id) renderBetaDetail(); })
      .catch(function () { if (betaState.selected === id) renderBetaDetail(); });
  }

  function clearBetaSelection() {
    betaState.selected = null;
    $$('.cs-beta-row.is-selected').forEach(function (el) { el.classList.remove('is-selected'); });
    var panel = document.getElementById('cs-beta-detail');
    if (panel) panel.hidden = true;
  }

  /**
   * Read the effective value of a field for the currently selected job.
   * Returns the staged edit if present, otherwise the server snapshot
   * (queue snapshot for queue fields, /jobs/{id} cache for job fields).
   */
  function betaFieldValue(id, field) {
    var bag = betaState.edits[id] || {};
    if (field in bag) return bag[field] == null ? '' : String(bag[field]);
    var orig = betaOrigFieldValue(id, field);
    return orig == null ? '' : String(orig);
  }

  function betaIsEdited(id, field) {
    var bag = betaState.edits[id];
    return !!(bag && (field in bag));
  }

  function renderBetaDetail() {
    var panel = document.getElementById('cs-beta-detail');
    var grid  = document.getElementById('cs-beta-detail-grid');
    if (!panel || !grid) return;
    if (betaState.selected == null) { panel.hidden = true; return; }
    var snap = betaJobById(betaState.selected);
    if (!snap) { panel.hidden = true; return; }
    var j = betaEffectiveJob(snap);
    var id = j.id;
    var det = betaState.jobDetails[id];      // may be undefined while loading
    var detailLoading = !det && betaState.jobDetailsLoading[id];

    var jobEl  = document.getElementById('cs-beta-detail-job');
    var custEl = document.getElementById('cs-beta-detail-cust');
    if (jobEl)  jobEl.textContent  = j.so_number || j.job_number || ('Job #' + id);
    if (custEl) custEl.textContent = j.customer || '';

    panel.hidden = false;
    grid.setAttribute('data-job-id', String(id));

    // Field helpers — value() reads effective value, cls() flags edited.
    function fv(field) { return escapeHtml(betaFieldValue(id, field)); }
    function ec(field) { return betaIsEdited(id, field) ? ' is-edited' : ''; }
    function readOnly(value) {
      return '<div class="cs-beta-detail-readonly">' + (value == null || value === '' ? '—' : escapeHtml(String(value))) + '</div>';
    }

    var partsOptions = ['', 'NOT_READY', 'PARTIAL', 'READY', 'HOLD'].map(function (v) {
      var label = v === '' ? '—' : (partsLabel(v) || v);
      var sel = (betaFieldValue(id, 'parts_status') === v) ? ' selected' : '';
      return '<option value="' + escapeHtml(v) + '"' + sel + '>' + escapeHtml(label) + '</option>';
    }).join('');

    var loadingNote = detailLoading
      ? '<div class="cs-beta-detail-loading"><span class="material-symbols-outlined cs-beta__spinner" aria-hidden="true">progress_activity</span><span>Loading job…</span></div>'
      : '';
    var loadFailed = !det && !detailLoading
      ? '<div class="cs-beta-detail-loading cs-beta-detail-loading--error"><span class="material-symbols-outlined">error</span><span>Couldn\'t load full job detail. Queue fields are still editable.</span></div>'
      : '';

    grid.innerHTML = ''
      + loadingNote
      + loadFailed

      + '<section class="cs-beta-detail-section">'
      +   '<h4 class="cs-beta-detail-section__title">Job Identity</h4>'
      +   '<div class="cs-beta-detail-fields">'
      +     '<label class="cs-beta-field">'
      +       '<span class="cs-beta-field__label">Customer</span>'
      +       (det
                ? '<input type="text" class="cs-beta-field__input' + ec('customer_name') + '" data-field="customer_name" value="' + fv('customer_name') + '" maxlength="160">'
                : readOnly(j.customer))
      +     '</label>'
      +     '<label class="cs-beta-field">'
      +       '<span class="cs-beta-field__label">Dealer</span>'
      +       (det
                ? '<input type="text" class="cs-beta-field__input' + ec('dealer_name') + '" data-field="dealer_name" value="' + fv('dealer_name') + '" maxlength="160">'
                : readOnly(j.dealer))
      +     '</label>'
      +     '<label class="cs-beta-field">'
      +       '<span class="cs-beta-field__label">Salesperson</span>'
      +       (det
                ? '<input type="text" class="cs-beta-field__input' + ec('sales_person') + '" data-field="sales_person" value="' + fv('sales_person') + '" maxlength="120" placeholder="—">'
                : readOnly(det && det.sales_person))
      +     '</label>'
      +     '<label class="cs-beta-field">'
      +       '<span class="cs-beta-field__label">VIN (last 8)</span>'
      +       (det
                ? '<input type="text" class="cs-beta-mono cs-beta-field__input' + ec('vin_last8') + '" data-field="vin_last8" value="' + fv('vin_last8') + '" maxlength="8" placeholder="—" pattern="[A-HJ-NPR-Z0-9]{7,8}">'
                : readOnly(det && (det.vin_last8 || det.vin)))
      +     '</label>'
      +     '<dl class="cs-beta-detail-kv cs-beta-detail-kv--inline">'
      +       '<dt>SO #</dt><dd class="cs-beta-mono">' + escapeHtml(j.so_number || '—') + '</dd>'
      +       '<dt>Job ID</dt><dd class="cs-beta-mono">#' + id + '</dd>'
      +     '</dl>'
      +   '</div>'
      + '</section>'

      + '<section class="cs-beta-detail-section">'
      +   '<h4 class="cs-beta-detail-section__title">Scheduling</h4>'
      +   '<div class="cs-beta-detail-fields">'
      +     '<label class="cs-beta-field">'
      +       '<span class="cs-beta-field__label">Requested Date</span>'
      +       (det
                ? '<input type="date" class="cs-beta-mono cs-beta-field__input' + ec('requested_date') + '" data-field="requested_date" value="' + fv('requested_date') + '">'
                : readOnly(det && det.requested_date))
      +     '</label>'
      +     '<label class="cs-beta-field">'
      +       '<span class="cs-beta-field__label">Estimated Hours</span>'
      +       (det
                ? '<input type="number" min="0" step="0.25" class="cs-beta-mono cs-beta-field__input' + ec('estimated_hours') + '" data-field="estimated_hours" value="' + fv('estimated_hours') + '" placeholder="—">'
                : readOnly(det && det.estimated_minutes ? +(det.estimated_minutes / 60).toFixed(2) : null))
      +     '</label>'
      +     '<dl class="cs-beta-detail-kv cs-beta-detail-kv--inline">'
      +       '<dt>Due</dt><dd class="cs-beta-mono">' + escapeHtml(fmtDate(j.due_date)) + '</dd>'
      +       '<dt>Promised</dt><dd class="cs-beta-mono">' + escapeHtml(fmtDate(j.promised_date)) + '</dd>'
      +       '<dt>Sch. Start</dt><dd class="cs-beta-mono">' + escapeHtml(fmtDate(j.scheduled_start)) + '</dd>'
      +       '<dt>Queue #</dt><dd class="cs-beta-mono">' + (j.queue_order == null ? '—' : j.queue_order) + '</dd>'
      +     '</dl>'
      +   '</div>'
      + '</section>'

      + '<section class="cs-beta-detail-section">'
      +   '<h4 class="cs-beta-detail-section__title">Handoff'
      +     (j._reassigned ? ' <span class="cs-beta-detail-section__hint">staged</span>' : '')
      +   '</h4>'
      +   '<div class="cs-beta-detail-fields">'
      +     '<label class="cs-beta-field">'
      +       '<span class="cs-beta-field__label">Assigned Tech</span>'
      +       '<select class="cs-beta-field__input' + ec('assigned_user_id') + '" data-field="assigned_user_id">'
      +         betaTechOptions(j.assigned_user_id)
      +       '</select>'
      +     '</label>'
      +     (j._reassigned
            ? '<div class="cs-beta-detail-was">Was ' + escapeHtml(j._orig_assigned_tech || 'Unassigned') + '</div>'
            : '')
      +     '<label class="cs-beta-field">'
      +       '<span class="cs-beta-field__label">Queue #</span>'
      +       '<input type="number" min="1" step="1" class="cs-beta-mono cs-beta-field__input' + ec('queue_order') + '" data-field="queue_order" value="' + fv('queue_order') + '" placeholder="—">'
      +     '</label>'
      +     '<label class="cs-beta-field cs-beta-field--inline">'
      +       '<input type="checkbox" data-field="queue_visible"' + (j.queue_visible ? ' checked' : '') + '>'
      +       '<span>Visible on tech queue</span>'
      +     '</label>'
      +   '</div>'
      + '</section>'

      + '<section class="cs-beta-detail-section">'
      +   '<h4 class="cs-beta-detail-section__title">Parts / Status</h4>'
      +   '<div class="cs-beta-detail-fields">'
      +     '<label class="cs-beta-field">'
      +       '<span class="cs-beta-field__label">Parts Status</span>'
      +       (det
                ? '<select class="cs-beta-field__input' + ec('parts_status') + '" data-field="parts_status">' + partsOptions + '</select>'
                : readOnly(partsLabel(j.parts_status)))
      +     '</label>'
      +     '<dl class="cs-beta-detail-kv cs-beta-detail-kv--inline">'
      +       '<dt>Status</dt><dd><span class="pill ' + statusPillClass(j.status) + '">' + escapeHtml(j.status_label || j.status || '—') + '</span></dd>'
      +     '</dl>'
      +     '<div class="cs-beta-detail-section__hint">Status transitions are managed via Tech / QC workflows; not editable here.</div>'
      +   '</div>'
      + '</section>'

      + '<section class="cs-beta-detail-section cs-beta-detail-section--wide">'
      +   '<h4 class="cs-beta-detail-section__title">Queue Note <span class="cs-beta-detail-section__hint">visible to Tech</span></h4>'
      +   '<textarea class="cs-beta-detail-note' + ec('queue_note') + '" data-field="queue_note" maxlength="240" placeholder="Add a short note for the tech…">' + escapeHtml(betaFieldValue(id, 'queue_note')) + '</textarea>'
      + '</section>'

      + '<section class="cs-beta-detail-section cs-beta-detail-section--wide">'
      +   '<h4 class="cs-beta-detail-section__title">Internal Notes <span class="cs-beta-detail-section__hint">CS only — not visible to Tech</span></h4>'
      +   (det
          ? '<textarea class="cs-beta-detail-note' + ec('notes') + '" data-field="notes" maxlength="4000" placeholder="Internal CS notes, customer comms, history…">' + escapeHtml(betaFieldValue(id, 'notes')) + '</textarea>'
          : '<div class="cs-beta-detail-loading">Loading…</div>')
      + '</section>'

      + '<section class="cs-beta-detail-section cs-beta-detail-section--actions">'
      +   '<h4 class="cs-beta-detail-section__title">Actions</h4>'
      +   '<div class="cs-beta-detail-actions">'
      +     '<button type="button" class="btn btn--secondary" data-action="beta-discard-row"' + (betaState.edits[id] ? '' : ' disabled') + '>'
      +       '<span class="material-symbols-outlined">undo</span>'
      +       'Discard row edits'
      +     '</button>'
      +     '<a class="btn btn--secondary" href="' + escapeHtml(window.location.origin + '/ops/cs/?embed=1') + '" target="_blank" rel="noopener">'
      +       '<span class="material-symbols-outlined">open_in_new</span>'
      +       'Open in legacy CS'
      +     '</a>'
      +   '</div>'
      + '</section>';

    // One-time delegated input listener on the grid. The grid element is
    // long-lived; renderBetaDetail() only replaces its innerHTML, so a
    // listener attached once survives every re-render.
    if (!grid.dataset.wiredInputs) {
      grid.dataset.wiredInputs = '1';
      grid.addEventListener('input', betaDetailInputHandler);
      grid.addEventListener('change', betaDetailInputHandler);
      grid.addEventListener('click', function (e) {
        var t = e.target.closest('[data-action="beta-discard-row"]');
        if (!t) return;
        e.preventDefault();
        betaDiscardRowEdits();
      });
    }
  }

  function betaDetailInputHandler(e) {
    var t = e.target;
    if (!t || !t.dataset || !t.dataset.field) return;
    var grid = document.getElementById('cs-beta-detail-grid');
    if (!grid) return;
    var id = parseInt(grid.getAttribute('data-job-id'), 10);
    if (!id || isNaN(id)) return;
    var field = t.dataset.field;
    var value = t.type === 'checkbox' ? !!t.checked : t.value;
    if (field === 'assigned_user_id') {
      value = value === '' ? null : parseInt(value, 10);
      if (value != null && (!isFinite(value) || value <= 0)) value = null;
    }
    if (field === 'queue_order') {
      value = String(value).trim() === '' ? null : parseInt(value, 10);
      if (value != null && (!isFinite(value) || value < 1)) value = null;
    }
    // Normalise empty-string job-text fields to '' for clean comparison
    // against the cached snapshot value.
    if (typeof value === 'string') value = value.trim() === '' ? '' : value;
    recordBetaEdit(id, field, value);
    if (betaState.edits[id]) t.classList.add('is-edited');
    else t.classList.remove('is-edited');
    var discard = grid.querySelector('[data-action="beta-discard-row"]');
    if (discard) discard.disabled = !betaState.edits[id];
    if (e.type === 'change' && [
      'assigned_user_id',
      'queue_order',
      'queue_visible',
      'parts_status',
      'customer_name',
      'dealer_name'
    ].indexOf(field) >= 0) {
      renderBeta();
    }
  }

  function betaDiscardRowEdits() {
    var id = betaState.selected;
    if (id == null) return;
    if (!betaState.edits[id]) return;
    delete betaState.edits[id];
    // Force a full row + detail re-render so all input values reset to
    // the snapshot, the row's dirty class clears, and warnings recompute.
    renderBeta();
    var allJobs = betaState.jobs.map(betaEffectiveJob);
    updateBetaWarnings(allJobs);
    updateBetaSaveButton();
    showToast('Row edits discarded');
  }

  function updateBetaWarnings(allJobs) {
    var box = document.getElementById('cs-beta-warnings');
    if (box) {
      box.innerHTML = '';
      box.hidden = true;
    }

    var warningsByGroup = betaWarningsByGroup(allJobs);
    $$('.cs-beta-group__warnings').forEach(function (el) {
      var key = el.getAttribute('data-group-warnings') || '';
      var warnings = warningsByGroup[key] || [];
      if (warnings.length === 0) {
        el.hidden = true;
        el.innerHTML = '';
        return;
      }
      el.hidden = false;
      el.innerHTML = warnings.map(betaWarningHtml).join('');
    });
  }

  function updateBetaSaveButton() {
    var btn = document.getElementById('cs-beta-save');
    if (!btn) return;
    var n = Object.keys(betaState.edits).length;
    btn.disabled = (n === 0);
    var label = document.getElementById('cs-beta-save-label');
    if (label) {
      var hasJob = false;
      var ids = Object.keys(betaState.edits);
      ids.forEach(function (id) {
        Object.keys(betaState.edits[id] || {}).forEach(function (field) {
          if (betaIsJobField(field)) hasJob = true;
        });
      });
      if (n === 0) {
        label.textContent = 'Save Changes';
      } else {
        label.textContent = (hasJob ? 'Save Changes + Job' : 'Save Changes') + ' (' + n + ')';
      }
    }
  }

  function normalizeBeta() {
    var jobs   = betaState.jobs.map(betaEffectiveJob);
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
          recordBetaEdit(j.id, 'queue_order', n);
        }
        n++;
      });
    });
    renderBeta();
    showBetaNotice('Queue numbers normalized. Duplicate queue warnings are cleared locally; review the remaining warnings, then Save Changes.', 'success');
    showToast('Queue numbers normalized — Save to commit');
  }

  function resetBetaDemo() {
    var api = betaApi();
    var btn = document.getElementById('cs-beta-demo-reset');
    if (!api || !btn) return;
    if (Object.keys(betaState.edits).length > 0) {
      if (!window.confirm('Reset the demo queue and discard unsaved changes?')) return;
    }

    btn.disabled = true;
    showBetaNotice('Resetting the local demo queue...', 'info');
    fetch(api.root + '/cs/demo-seed', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'X-WP-Nonce':   api.nonce,
        'Content-Type': 'application/json',
        'Accept':       'application/json'
      },
      body: JSON.stringify({ reset: true })
    })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
      .then(function (res) {
        if (!res.ok || !res.body || !res.body.ok) {
          var msg = (res.body && (res.body.message || res.body.code)) || 'Demo reset failed.';
          throw new Error(msg);
        }
        betaState.loaded            = false;
        betaState.edits             = {};
        betaState.jobDetails        = {};
        betaState.jobDetailsLoading = {};
        showBetaNotice('Demo queue reset. Duplicate, parts-hold, and missing-tech warnings are ready to test.', 'success');
        loadBeta();
        showToast('Demo queue reset');
      })
      .catch(function (err) {
        showBetaNotice('Demo reset failed: ' + (err && err.message ? err.message : 'unknown error'), 'error');
        showToast('Demo reset failed');
      })
      .finally(function () {
        btn.disabled = false;
      });
  }

  function saveBeta() {
    var api = betaApi();
    if (!api) return;
    var ids = Object.keys(betaState.edits);
    if (ids.length === 0) return;

    // Split edits per row into the queue payload (POST /cs/queue) and
    // the per-job PATCH payload (PATCH /jobs/{id}). Each endpoint
    // handles only the fields it owns.
    var queueUpdates = [];
    var jobUpdates   = [];
    ids.forEach(function (idStr) {
      var id = parseInt(idStr, 10);
      var e  = betaState.edits[idStr];
      var qBody = { id: id };
      var jBody = {};
      var hasQ = false, hasJ = false;
      Object.keys(e).forEach(function (field) {
        var v = e[field];
        if (betaIsJobField(field)) {
          // Empty-string text fields are sent as '' so the server can
          // clear them to NULL (sanitize_text_field() + ?: null).
          jBody[field] = v == null ? '' : v;
          hasJ = true;
        } else {
          if (field === 'queue_visible') qBody[field] = !!v;
          else                            qBody[field] = v;
          hasQ = true;
        }
      });
      if (hasQ) queueUpdates.push(qBody);
      if (hasJ) jobUpdates.push({ id: id, body: jBody });
    });

    var btn  = document.getElementById('cs-beta-save');
    var lbl  = document.getElementById('cs-beta-save-label');
    if (btn) btn.disabled = true;
    if (lbl) lbl.textContent = 'Saving…';

    var savedQueue = 0;
    var savedJobs  = 0;
    var jobErrors  = [];

    var step = Promise.resolve();

    // Helper: drop fields from a row's edit bag once they've been
    // saved server-side, so a partial-failure retry only re-sends
    // what genuinely still needs saving.
    function pruneEdits(id, fields) {
      var bag = betaState.edits[id];
      if (!bag) return;
      fields.forEach(function (f) { delete bag[f]; });
      if (Object.keys(bag).length === 0) delete betaState.edits[id];
    }
    var QUEUE_FIELDS = ['queue_order', 'queue_visible', 'queue_note', 'assigned_user_id'];

    if (queueUpdates.length > 0) {
      step = step.then(function () {
        return fetch(api.root + '/cs/queue', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'X-WP-Nonce':   api.nonce,
            'Content-Type': 'application/json',
            'Accept':       'application/json'
          },
          body: JSON.stringify({ updates: queueUpdates })
        })
          .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
          .then(function (res) {
            if (!res.ok || !res.body || !res.body.ok) {
              throw new Error('queue_save_failed');
            }
            savedQueue = res.body.saved || 0;
            // Queue side succeeded for every row in queueUpdates — drop
            // those fields so a later PATCH failure doesn't cause us to
            // re-POST identical queue values on retry.
            queueUpdates.forEach(function (u) { pruneEdits(u.id, QUEUE_FIELDS); });
          });
      });
    }

    // PATCH each job sequentially. On failure, surface the first error
    // and abort the rest. Edits for already-saved rows are pruned so
    // the dirty marker clears for them; failed rows keep their job
    // edits in betaState.edits so the user can retry.
    jobUpdates.forEach(function (u) {
      step = step.then(function () {
        return fetch(api.root + '/jobs/' + u.id, {
          method: 'PATCH',
          credentials: 'same-origin',
          headers: {
            'X-WP-Nonce':   api.nonce,
            'Content-Type': 'application/json',
            'Accept':       'application/json'
          },
          body: JSON.stringify(u.body)
        })
          .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
          .then(function (res) {
            if (!res.ok) {
              var msg = (res.body && (res.body.message || res.body.code)) || ('PATCH ' + u.id + ' failed');
              jobErrors.push({ id: u.id, message: msg });
              throw new Error('job_save_failed:' + u.id + ':' + msg);
            }
            savedJobs++;
            pruneEdits(u.id, BETA_JOB_FIELDS);
          });
      });
    });

    step
      .then(function () {
        var total = savedQueue + savedJobs;
        showToast('Saved · ' + total + ' update' + (total === 1 ? '' : 's'));
        showBetaNotice('', 'info');
        betaState.loaded            = false;
        betaState.edits             = {};
        betaState.jobDetails        = {};
        betaState.jobDetailsLoading = {};
        loadBeta();
      })
      .catch(function (err) {
        var prefix = (savedQueue + savedJobs) > 0
          ? 'Partial save (' + (savedQueue + savedJobs) + ' ok) — '
          : 'Save failed — ';
        var msg = prefix.replace(/—\s*$/, '');
        if (jobErrors.length > 0) msg = prefix + jobErrors[0].message;
        else if (err && err.message) msg = prefix + err.message.replace(/^[a-z_]+:\d+:/i, '');
        else msg = prefix + 'unknown error';
        showToast(msg);
        if (btn) btn.disabled = false;
        if (lbl) lbl.textContent = 'Save Changes';
        // Refresh the queue snapshot so already-saved fields stop
        // flagging dirty in the UI; remaining edits stay queued.
        if (savedQueue > 0 || savedJobs > 0) {
          betaState.loaded            = false;
          betaState.jobDetails        = {};
          betaState.jobDetailsLoading = {};
          loadBeta();
        } else {
          updateBetaSaveButton();
        }
      });
  }

  // ─── Beta tab wiring ─────────────────────────────────────────────────
  $$('.cs-beta-chip').forEach(function (chip) {
    chip.addEventListener('click', function () {
      $$('.cs-beta-chip').forEach(function (c) {
        c.classList.remove('is-active');
        c.setAttribute('aria-selected', 'false');
      });
      chip.classList.add('is-active');
      chip.setAttribute('aria-selected', 'true');
      betaState.filter = chip.dataset.filter || 'all';
      renderBeta();
    });
  });

  var betaSearch = document.getElementById('cs-beta-search');
  if (betaSearch) {
    var searchT;
    betaSearch.addEventListener('input', function () {
      clearTimeout(searchT);
      searchT = setTimeout(function () {
        betaState.query = betaSearch.value.trim().toLowerCase();
        renderBeta();
      }, 120);
    });
  }

  var betaSaveBtn = document.getElementById('cs-beta-save');
  if (betaSaveBtn) betaSaveBtn.addEventListener('click', saveBeta);

  var betaNormBtn = document.getElementById('cs-beta-normalize');
  if (betaNormBtn) betaNormBtn.addEventListener('click', normalizeBeta);

  var betaDemoResetBtn = document.getElementById('cs-beta-demo-reset');
  if (betaDemoResetBtn) betaDemoResetBtn.addEventListener('click', resetBetaDemo);

  var betaRefreshBtn = document.getElementById('cs-beta-refresh');
  if (betaRefreshBtn) {
    betaRefreshBtn.addEventListener('click', function () {
      if (Object.keys(betaState.edits).length > 0) {
        if (!window.confirm('You have unsaved changes. Refresh and discard them?')) return;
      }
      betaState.loaded = false;
      loadBeta();
    });
  }

  // ── New Job intake modal ────────────────────────────────────────────
  // Opens from the CS Workspace header, posts to the existing
  // POST /jobs endpoint (perm_create_jobs = CS / Supervisor / Admin),
  // and reloads the queue on success so the new job lands in the
  // Unassigned group at the top.
  function betaOpenNewJob() {
    var modal = document.getElementById('cs-beta-newjob-modal');
    var form  = document.getElementById('cs-beta-newjob-form');
    var err   = document.getElementById('cs-beta-newjob-error');
    if (!modal || !form) return;
    form.reset();
    if (err) { err.hidden = true; err.textContent = ''; }
    modal.hidden = false;
    document.body.classList.add('cs-beta-modal-open');
    var first = form.querySelector('input, select, textarea');
    if (first) first.focus();
  }

  function betaCloseNewJob() {
    var modal = document.getElementById('cs-beta-newjob-modal');
    if (!modal) return;
    modal.hidden = true;
    document.body.classList.remove('cs-beta-modal-open');
  }

  function betaSetNewJobSubmitting(isSubmitting) {
    var btn   = document.getElementById('cs-beta-newjob-submit');
    var label = document.getElementById('cs-beta-newjob-submit-label');
    if (btn) btn.disabled = !!isSubmitting;
    if (label) label.textContent = isSubmitting ? 'Creating…' : 'Create Job';
  }

  function betaShowNewJobError(msg) {
    var err = document.getElementById('cs-beta-newjob-error');
    betaSetNewJobSubmitting(false);
    if (!err) return;
    err.textContent = msg;
    err.hidden = false;
  }

  function betaSubmitNewJob(e) {
    e.preventDefault();
    var api  = betaApi();
    var form = document.getElementById('cs-beta-newjob-form');
    if (!api || !form) return;
    betaSetNewJobSubmitting(false);
    var existingErr = document.getElementById('cs-beta-newjob-error');
    if (existingErr) { existingErr.hidden = true; existingErr.textContent = ''; }
    var fd = new FormData(form);
    function val(name) { return (fd.get(name) || '').toString().trim(); }

    var body = {
      so_number:        val('so_number').toUpperCase(),
      job_type:         val('job_type'),
      estimated_hours:  val('estimated_hours'),
      customer_name:    val('customer_name'),
      dealer_name:      val('dealer_name'),
      vin_last8:        val('vin_last8').toUpperCase(),
      no_vin_required:  fd.get('no_vin_required') ? true : false,
      parts_status:     val('parts_status') || 'NOT_READY',
      requested_date:   val('requested_date'),
      sales_person:     val('sales_person'),
      job_description:  val('job_description'),
      notes:            val('notes')
    };

    // Light client-side guard rails so we can fail fast before hitting
    // the server. The PHP endpoint repeats every check authoritatively.
    if (!body.job_type) {
      betaShowNewJobError('Job Type is required.');
      return;
    }
    if (!body.estimated_hours || isNaN(parseFloat(body.estimated_hours)) || parseFloat(body.estimated_hours) <= 0) {
      betaShowNewJobError('Estimated Hours is required and must be greater than zero.');
      return;
    }
    if (!body.customer_name && !body.dealer_name) {
      betaShowNewJobError('Provide a customer name, dealer, or both.');
      return;
    }
    if (body.so_number && !/^S-ORD\d{6}$/.test(body.so_number)) {
      betaShowNewJobError('SO# format: S-ORD followed by 6 digits (e.g. S-ORD101350).');
      return;
    }
    if (body.job_type !== 'PARTS_ONLY' && !body.no_vin_required) {
      if (!/^[A-HJ-NPR-Z0-9]{7,8}$/.test(body.vin_last8)) {
        betaShowNewJobError('VIN is required and must be 7–8 alphanumeric characters (or check “No VIN required”).');
        return;
      }
    } else if (body.vin_last8 && !/^[A-HJ-NPR-Z0-9]{7,8}$/.test(body.vin_last8)) {
      betaShowNewJobError('VIN must be 7–8 alphanumeric characters.');
      return;
    }

    var err   = document.getElementById('cs-beta-newjob-error');
    if (err) err.hidden = true;
    betaSetNewJobSubmitting(true);

    var controller = window.AbortController ? new AbortController() : null;
    var timeoutId = controller ? setTimeout(function () {
      controller.abort();
    }, 25000) : null;

    fetch(api.root + '/jobs', {
      method: 'POST',
      credentials: 'same-origin',
      signal: controller ? controller.signal : undefined,
      headers: {
        'X-WP-Nonce':   api.nonce,
        'Content-Type': 'application/json',
        'Accept':       'application/json'
      },
      body: JSON.stringify(body)
    })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, status: r.status, body: j }; }); })
      .then(function (res) {
        if (!res.ok) {
          var msg = (res.body && (res.body.message || res.body.code)) || ('Create failed (HTTP ' + res.status + ').');
          betaShowNewJobError(String(msg));
          return;
        }
        showToast('Job created' + (res.body && res.body.so_number ? ' · ' + res.body.so_number : ''));
        betaCloseNewJob();
        betaSetNewJobSubmitting(false);
        // Reload the queue so the new job (status INTAKE → Unassigned)
        // appears at the top.
        betaState.loaded = false;
        betaState.edits  = {};
        betaState.jobDetails = {};
        betaState.jobDetailsLoading = {};
        loadBeta();
      })
      .catch(function (err) {
        if (err && err.name === 'AbortError') {
          betaShowNewJobError('Create is taking too long. Please check the queue before trying again.');
          return;
        }
        betaShowNewJobError('Create failed — please try again.');
      })
      .finally(function () {
        if (timeoutId) clearTimeout(timeoutId);
      });
  }

  var betaNewBtn = document.getElementById('cs-beta-new');
  if (betaNewBtn) {
    betaNewBtn.disabled = false;
    betaNewBtn.removeAttribute('title');
    betaNewBtn.addEventListener('click', betaOpenNewJob);
  }
  var newJobModal = document.getElementById('cs-beta-newjob-modal');
  if (newJobModal) {
    newJobModal.addEventListener('click', function (e) {
      var t = e.target.closest('[data-action="cs-beta-newjob-close"]');
      if (t) { e.preventDefault(); betaCloseNewJob(); }
    });
  }
  var newJobForm = document.getElementById('cs-beta-newjob-form');
  if (newJobForm) newJobForm.addEventListener('submit', betaSubmitNewJob);
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var m = document.getElementById('cs-beta-newjob-modal');
    if (m && !m.hidden) betaCloseNewJob();
  });

  var betaDetailClose = document.getElementById('cs-beta-detail-close');
  if (betaDetailClose) {
    betaDetailClose.addEventListener('click', clearBetaSelection);
  }

  // Defensive: any row left with draggable="true" after a handle mousedown
  // but without an active drag (e.g., user pressed the handle and let go
  // without moving) is reset on the next document-level mouseup.
  document.addEventListener('mouseup', function () {
    if (betaState.drag) return;
    $$('.cs-beta-row[draggable="true"]').forEach(function (r) {
      if (!r.classList.contains('is-dragging')) r.setAttribute('draggable', 'false');
    });
  });

  // ─── Init ─────────────────────────────────────────────────────────────
  // Server already rendered the lists; re-render priorities/health to
  // attach event handlers and trigger the initial bar-fill animation.
  renderPriorities();
  renderHealth();
  // Parts / QC / Pickup lists are read-only and have no row interactions,
  // so the server-rendered markup is left in place.
  setTimeout(animateKPIs, 50);

  // Sub-tab hash router. Allows /ops/cs-dashboard#queue and
  // /ops/cs-dashboard#cs-workspace to land directly on the CS Workspace tab.
  // Legacy Workspace hashes are kept as aliases for saved links.
  var betaHashAliases = {
    'workspace':      'queue',
    'cs-workspace':   'queue',
    'workspace-beta': 'queue',
    'queue':          'queue',
    'overview':       'overview',
    'intake':         'intake',
    'parts':          'parts',
    'qc':             'qc',
    'pickup':         'pickup',
    'exceptions':     'exceptions'
  };
  function applyHashRoute() {
    var raw = (window.location.hash || '').replace(/^#/, '').toLowerCase().trim();
    if (!raw) return;
    var key = betaHashAliases[raw];
    if (!key) return;
    var btn = document.querySelector('.ops-subtab[data-tab="' + key + '"]');
    if (!btn) return;
    if (btn.classList.contains('active')) return;  // already there
    btn.click();
  }
  applyHashRoute();
  window.addEventListener('hashchange', applyHashRoute);

})();
