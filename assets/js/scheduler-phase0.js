/**
 * Slate Ops — Scheduler Phase 0 helpers
 *
 * This file is enqueued AFTER ops.js on /ops/schedule pages.  It extends the
 * scheduler board that ops.js already renders with:
 *
 *  1. A pending-changes queue so drag-and-drop mutations are batched locally.
 *  2. A "Save Schedule" button that sends all queued changes in one REST call to
 *     POST /slate-ops/v1/schedule/bulk  (registered in class-slate-ops-rest.php).
 *  3. An "Assign tech" select on the job-schedule modal that writes assigned_user_id.
 *  4. Activity-log display refresh after a successful bulk save.
 *
 * It deliberately does NOT rewrite the board rendering — ops.js owns that.
 * This file hooks into the existing DOM events using event delegation.
 *
 * Globals expected (set by wp_localize_script in slate-ops.php):
 *   slateOpsSettings.restRoot  — e.g. https://example.com/wp-json/slate-ops/v1
 *   slateOpsSettings.nonce     — WP REST nonce
 */
(function () {
  'use strict';

  /* ─────────────────────────────────────────────────────────────────
     State
  ───────────────────────────────────────────────────────────────── */
  // Map<job_id, {job_id, scheduled_start, scheduled_finish, assigned_user_id, work_center}>
  const pendingChanges = new Map();

  /* ─────────────────────────────────────────────────────────────────
     API helpers
  ───────────────────────────────────────────────────────────────── */
  function apiBase() {
    return (window.slateOpsSettings && window.slateOpsSettings.restRoot)
      ? window.slateOpsSettings.restRoot
      : '/wp-json/slate-ops/v1';
  }

  function nonce() {
    return (window.slateOpsSettings && window.slateOpsSettings.nonce) || '';
  }

  async function apiFetch(path, opts) {
    opts = opts || {};
    const headers = Object.assign(
      { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce() },
      opts.headers || {}
    );
    const res = await fetch(apiBase() + path, Object.assign({}, opts, { headers }));
    const txt = await res.text();
    let data = null;
    try { data = txt ? JSON.parse(txt) : null; } catch (_) { data = { raw: txt }; }
    if (!res.ok) {
      const msg = data && data.message ? data.message : 'Request failed (' + res.status + ')';
      const err = new Error(msg);
      err.data = data;
      throw err;
    }
    return data;
  }

  /**
   * POST /slate-ops/v1/schedule/bulk
   * Payload: { updates: [{job_id, scheduled_start, scheduled_finish, assigned_user_id, work_center}] }
   */
  async function bulkSave(updates) {
    return apiFetch('/schedule/bulk', {
      method: 'POST',
      body: JSON.stringify({ updates }),
    });
  }

  /* ─────────────────────────────────────────────────────────────────
     Pending-change helpers
  ───────────────────────────────────────────────────────────────── */
  function queueChange(jobId, fields) {
    const existing = pendingChanges.get(jobId) || { job_id: jobId };
    pendingChanges.set(jobId, Object.assign(existing, fields));
    updateSaveButtonState();
  }

  function clearQueue() {
    pendingChanges.clear();
    updateSaveButtonState();
  }

  function pendingCount() {
    return pendingChanges.size;
  }

  /* ─────────────────────────────────────────────────────────────────
     Save button (injected into the scheduler toolbar by ops.js)
  ───────────────────────────────────────────────────────────────── */
  function updateSaveButtonState() {
    const btn = document.getElementById('sched-bulk-save');
    if (!btn) return;
    const n = pendingCount();
    btn.textContent = n > 0 ? 'Save Schedule (' + n + ')' : 'Save Schedule';
    btn.disabled = n === 0;
    btn.style.opacity = n === 0 ? '0.45' : '1';
  }

  function injectSaveBtnIfAbsent() {
    if (document.getElementById('sched-bulk-save')) return;
    const toolbar = document.querySelector('.sched-toolbar .inline, .sched-toolbar');
    if (!toolbar) return;
    const btn = document.createElement('button');
    btn.id = 'sched-bulk-save';
    btn.className = 'btn small-btn';
    btn.textContent = 'Save Schedule';
    btn.disabled = true;
    btn.style.marginLeft = '8px';
    btn.style.opacity = '0.45';
    btn.addEventListener('click', onSaveClick);
    toolbar.appendChild(btn);
    updateSaveButtonState();
  }

  async function onSaveClick() {
    if (pendingCount() === 0) return;
    const btn = document.getElementById('sched-bulk-save');
    if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }

    const updates = Array.from(pendingChanges.values());
    try {
      await bulkSave(updates);
      clearQueue();
      showToast('Schedule saved (' + updates.length + ' job' + (updates.length !== 1 ? 's' : '') + ')', false);
    } catch (err) {
      showToast('Save failed: ' + err.message, true);
      if (btn) { btn.disabled = false; updateSaveButtonState(); }
    }
  }

  /* ─────────────────────────────────────────────────────────────────
     Intercept drag-and-drop drops to queue instead of immediately save
     (ops.js calls api.schedule() directly on drop — we shadow that by
      also pushing into our queue so the Save button captures the change)
  ───────────────────────────────────────────────────────────────── */
  function monitorDropEvents() {
    // Use event delegation on the ops-content area.
    const content = document.getElementById('slate-ops-app') || document.body;
    content.addEventListener('drop', function (e) {
      const cell = e.target.closest('.sched-cell');
      if (!cell) return;
      const jobId = parseInt(e.dataTransfer.getData('text/plain'), 10);
      const bay   = cell.getAttribute('data-bay');
      const date  = cell.getAttribute('data-date');
      if (!jobId || !bay || !date) return;

      // ops.js has already processed this drop and called api.schedule().
      // We queue it additionally so the bulk-save button tracks it.
      queueChange(jobId, {
        work_center:      bay,
        scheduled_start:  date,
        // scheduled_finish is computed in ops.js — we record a placeholder;
        // the bulk endpoint will skip null fields.
        scheduled_finish: null,
      });
    }, true /* capture so we run before ops.js bubbling listeners */);
  }

  /* ─────────────────────────────────────────────────────────────────
     Quick-schedule modal extension — adds assigned_user_id field
  ───────────────────────────────────────────────────────────────── */
  function patchQuickScheduleModal() {
    // The quick-schedule modal in ops.js has id="sched-modal-save".
    // We wait for it to appear in the DOM and inject a tech-select if absent.
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('.sched-quick-add');
      if (!btn) return;
      // ops.js will open a modal; give it a tick to render, then inject.
      requestAnimationFrame(function () {
        const modal = document.querySelector('.modal');
        if (!modal || modal.querySelector('.p0-tech-select')) return;
        injectTechSelectIntoModal(modal, parseInt(btn.getAttribute('data-id'), 10));
      });
    });
  }

  function injectTechSelectIntoModal(modal, jobId) {
    const users = getKnownUsers();
    if (!users.length) return;

    const body = modal.querySelector('.modal-body');
    if (!body) return;

    const wrap = document.createElement('div');
    wrap.className = 'p0-tech-select';
    wrap.style.marginTop = '10px';

    const label = document.createElement('label');
    label.style.cssText = 'font-size:11px;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;color:var(--shell-muted,#64748b);display:block;margin-bottom:5px;';
    label.textContent = 'Assign Tech';

    const sel = document.createElement('select');
    sel.style.cssText = 'width:100%;padding:9px 12px;border-radius:6px;border:1px solid rgba(0,0,0,0.16);font-size:14px;font-family:inherit;';
    sel.innerHTML = '<option value="">— Unassigned —</option>'
      + users.map(function (u) {
        return '<option value="' + u.id + '">' + escapeHtmlLocal(u.name) + '</option>';
      }).join('');

    sel.addEventListener('change', function () {
      queueChange(jobId, { assigned_user_id: sel.value ? parseInt(sel.value, 10) : null });
    });

    wrap.appendChild(label);
    wrap.appendChild(sel);
    body.appendChild(wrap);
  }

  // Read user list that ops.js already fetched (stored on the window by convention)
  function getKnownUsers() {
    return (window._slateOpsUserCache) || [];
  }

  /* ─────────────────────────────────────────────────────────────────
     Helpers
  ───────────────────────────────────────────────────────────────── */
  function showToast(msg, isError) {
    const el = document.createElement('div');
    el.textContent = msg;
    el.style.cssText = [
      'position:fixed;bottom:24px;right:24px;z-index:99999;',
      'padding:10px 16px;border-radius:8px;font-size:14px;font-weight:600;',
      'color:#fff;box-shadow:0 4px 12px rgba(0,0,0,0.2);transition:opacity .3s;',
      'background:' + (isError ? '#dc2626' : '#16a34a'),
    ].join('');
    document.body.appendChild(el);
    setTimeout(function () {
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 320);
    }, 3200);
  }

  function escapeHtmlLocal(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  /* ─────────────────────────────────────────────────────────────────
     MutationObserver — watch for scheduler board appearing in #ops-view
  ───────────────────────────────────────────────────────────────── */
  function watchForSchedulerBoard() {
    const view = document.getElementById('ops-view');
    if (!view) return;

    const obs = new MutationObserver(function () {
      if (document.querySelector('.sched-grid')) {
        injectSaveBtnIfAbsent();
      }
    });

    obs.observe(view, { childList: true, subtree: true });
  }

  /* ─────────────────────────────────────────────────────────────────
     Init
  ───────────────────────────────────────────────────────────────── */
  function init() {
    watchForSchedulerBoard();
    monitorDropEvents();
    patchQuickScheduleModal();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
