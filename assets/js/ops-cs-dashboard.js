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
  function activateWorkspace() {
    document.documentElement.classList.add('cs-workspace-active');
    document.body.classList.add('cs-workspace-active');

    var frame    = document.getElementById('workspace-frame');
    var skeleton = document.getElementById('workspace-skeleton');
    if (!frame) return;

    if (!frame.getAttribute('src')) {
      if (skeleton) skeleton.hidden = false;
      frame.hidden = true;
      frame.addEventListener('load', function () {
        if (skeleton) skeleton.hidden = true;
        frame.hidden = false;
      }, { once: true });
      frame.setAttribute('src', frame.dataset.src);
    }
  }

  function deactivateWorkspace() {
    document.documentElement.classList.remove('cs-workspace-active');
    document.body.classList.remove('cs-workspace-active');
    // Iframe stays in the DOM with src intact — do NOT clear it.
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

  // ─── Init ─────────────────────────────────────────────────────────────
  // Server already rendered the lists; re-render priorities/health to
  // attach event handlers and trigger the initial bar-fill animation.
  renderPriorities();
  renderHealth();
  // Parts / QC / Pickup lists are read-only and have no row interactions,
  // so the server-rendered markup is left in place.
  setTimeout(animateKPIs, 50);

})();
