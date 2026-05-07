(function () {
  'use strict';

  const cfg = window.slateOpsFloorBoard || {};
  const apiRoot = String(cfg.apiRoot || '').replace(/\/$/, '');
  const nonce = cfg.nonce || '';
  const refreshMs = Math.max(15000, Number(cfg.refreshMs || 60000));
  const columns = ['scheduled', 'in_progress', 'blocked', 'qc', 'complete'];

  const statusLabels = {
    INTAKE: 'Intake',
    READY_FOR_BUILD: 'Ready',
    READY_FOR_SCHEDULING: 'Ready',
    QUEUED: 'Scheduled',
    SCHEDULED: 'Scheduled',
    IN_PROGRESS: 'In Progress',
    BLOCKED: 'Blocked',
    DELAYED: 'Delayed',
    ON_HOLD: 'On Hold',
    PENDING_QC: 'Pending QC',
    QC: 'QC',
    READY_FOR_SUPERVISOR_REVIEW: 'Supervisor Review',
    COMPLETE: 'Complete',
    WORK_COMPLETE: 'Work Complete',
    READY_FOR_PICKUP: 'Ready for Pickup'
  };

  function qs(selector) {
    return document.querySelector(selector);
  }

  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function first(job, keys, fallback) {
    for (const key of keys) {
      if (job && job[key] !== undefined && job[key] !== null && String(job[key]).trim() !== '') {
        return job[key];
      }
    }
    return fallback || '';
  }

  function normalizeStatus(job) {
    return String(first(job, ['status', 'job_status'], '')).trim().toUpperCase();
  }

  function displayStatus(job) {
    const status = normalizeStatus(job);
    return statusLabels[status] || status.replace(/_/g, ' ') || 'Status Open';
  }

  function jobNumber(job) {
    return first(job, ['so_number', 'sales_order_number', 'bc_sales_order_no', 'order_no', 'job_number'], '') ||
      (job && job.job_id ? 'SJ-' + String(job.job_id).padStart(6, '0') : 'Job');
  }

  function customer(job) {
    return first(job, ['dealer_name', 'customer_name', 'account_name', 'company_name'], 'No customer listed');
  }

  function vehicle(job) {
    return first(job, ['vehicle', 'vehicle_description', 'unit', 'unit_info', 'model', 'van_model', 'vin_last8', 'vin'], '');
  }

  function assigned(job) {
    return first(job, ['assigned_name', 'assigned_tech_name', 'tech_name', 'assigned_user_name'], 'Unassigned');
  }

  function estimatedHours(job) {
    const raw = first(job, ['estimated_hours', 'labor_hours'], '');
    if (raw !== '') return Number(raw).toFixed(Number(raw) % 1 === 0 ? 0 : 1) + 'h';
    const minutes = Number(first(job, ['estimated_minutes'], 0));
    if (minutes > 0) return (minutes / 60).toFixed(minutes % 60 === 0 ? 0 : 1) + 'h';
    return '';
  }

  function actualHours(job) {
    const approved = Number(first(job, ['actual_minutes_approved'], 0));
    const pending = Number(first(job, ['actual_minutes_pending'], 0));
    const minutes = approved + pending;
    if (minutes <= 0) return '';
    return (minutes / 60).toFixed(minutes % 60 === 0 ? 0 : 1) + 'h logged';
  }

  function dueValue(job) {
    return first(job, ['due_date', 'scheduled_finish', 'target_finish', 'promise_date'], '');
  }

  function dateOnly(value) {
    if (!value) return '';
    const text = String(value);
    const match = text.match(/^(\d{4})-(\d{2})-(\d{2})/);
    return match ? match[0] : text;
  }

  function formatDate(value) {
    const d = dateOnly(value);
    if (!d) return '';
    const parts = d.split('-');
    if (parts.length === 3) return parts[1] + '/' + parts[2];
    return d;
  }

  function todayIso() {
    const now = new Date();
    const tz = new Date(now.getTime() - now.getTimezoneOffset() * 60000);
    return tz.toISOString().slice(0, 10);
  }

  function dueState(job) {
    const due = dateOnly(dueValue(job));
    if (!due) return '';
    const today = todayIso();
    if (due < today) return 'overdue';
    if (due === today) return 'today';
    return '';
  }

  function hasBlock(job) {
    const status = normalizeStatus(job);
    return status === 'BLOCKED' || status === 'DELAYED' || status === 'ON_HOLD' ||
      Boolean(first(job, ['blocked_at', 'block_reason', 'blocked_reason', 'hold_reason'], ''));
  }

  function bucket(job) {
    const status = normalizeStatus(job);
    if (hasBlock(job)) return 'blocked';
    if (['IN_PROGRESS'].includes(status)) return 'in_progress';
    if (['PENDING_QC', 'QC', 'READY_FOR_SUPERVISOR_REVIEW'].includes(status)) return 'qc';
    if (['COMPLETE', 'WORK_COMPLETE', 'READY_FOR_PICKUP'].includes(status)) return 'complete';
    if (['READY_FOR_BUILD', 'READY_FOR_SCHEDULING', 'QUEUED', 'SCHEDULED', 'INTAKE'].includes(status)) return 'scheduled';
    return '';
  }

  function blockReason(job) {
    return first(job, ['block_reason', 'blocked_reason', 'hold_reason', 'delay_reason'], 'Needs review');
  }

  function card(job, group) {
    const due = dueValue(job);
    const dueClass = dueState(job);
    const type = first(job, ['job_type', 'type', 'work_type'], '');
    const veh = vehicle(job);
    const est = estimatedHours(job);
    const actual = actualHours(job);
    const reason = group === 'blocked' ? `<div class="ops-floor-card-alert">${esc(blockReason(job))}</div>` : '';
    const meta = [type, veh].filter(Boolean).map(esc).join(' / ');
    const hours = [actual, est ? 'Est ' + est : ''].filter(Boolean).join(' / ');

    return `
      <article class="ops-floor-card ${dueClass ? 'is-' + dueClass : ''} ${group === 'blocked' ? 'is-blocked' : ''}">
        <div class="ops-floor-card-top">
          <strong>${esc(jobNumber(job))}</strong>
          <span>${esc(displayStatus(job))}</span>
        </div>
        <h2>${esc(customer(job))}</h2>
        ${meta ? `<p class="ops-floor-meta">${meta}</p>` : ''}
        <div class="ops-floor-card-bottom">
          <span>${esc(assigned(job))}</span>
          ${hours ? `<span>${esc(hours)}</span>` : '<span>-</span>'}
          ${due ? `<span class="ops-floor-due">Due ${esc(formatDate(due))}</span>` : '<span>-</span>'}
        </div>
        ${reason}
      </article>`;
  }

  function sortJobs(a, b) {
    const ad = dateOnly(dueValue(a)) || '9999-12-31';
    const bd = dateOnly(dueValue(b)) || '9999-12-31';
    if (ad !== bd) return ad.localeCompare(bd);
    return String(jobNumber(a)).localeCompare(String(jobNumber(b)));
  }

  function render(jobs) {
    const groups = { scheduled: [], in_progress: [], blocked: [], qc: [], complete: [] };
    jobs.forEach((job) => {
      const group = bucket(job);
      if (group) groups[group].push(job);
    });

    columns.forEach((key) => {
      const list = qs(`[data-floor-list="${key}"]`);
      const count = qs(`[data-floor-count="${key}"]`);
      const items = groups[key].sort(sortJobs);
      if (count) count.textContent = String(items.length);
      if (list) {
        list.innerHTML = items.length ? items.map((job) => card(job, key)).join('') : '<div class="ops-floor-column-empty">-</div>';
        list.classList.toggle('is-scroll', items.length > (key === 'in_progress' ? 5 : 6));
      }
    });

    const active = groups.scheduled.length + groups.in_progress.length + groups.blocked.length + groups.qc.length;
    const dueToday = jobs.filter((job) => dueState(job) === 'today').length;
    const statActive = qs('[data-floor-stat="active"]');
    const statProgress = qs('[data-floor-stat="progress"]');
    const statBlocked = qs('[data-floor-stat="blocked"]');
    const statDue = qs('[data-floor-stat="due"]');
    if (statActive) statActive.textContent = String(active);
    if (statProgress) statProgress.textContent = String(groups.in_progress.length);
    if (statBlocked) statBlocked.textContent = String(groups.blocked.length);
    if (statDue) statDue.textContent = String(dueToday);

    const empty = qs('[data-floor-empty]');
    if (empty) empty.hidden = active > 0;
  }

  function getJobs(payload) {
    if (Array.isArray(payload)) return payload;
    if (payload && Array.isArray(payload.jobs)) return payload.jobs;
    if (payload && Array.isArray(payload.data)) return payload.data;
    return [];
  }

  function setSync(text) {
    const el = qs('[data-floor-sync]');
    if (el) el.textContent = text;
  }

  function setError(show) {
    const el = qs('[data-floor-error]');
    if (el) el.hidden = !show;
  }

  async function load() {
    if (!apiRoot) return;
    try {
      const res = await fetch(apiRoot + '/jobs?limit=500', {
        credentials: 'same-origin',
        headers: nonce ? { 'X-WP-Nonce': nonce } : {}
      });
      if (!res.ok) throw new Error('Job request failed');
      const payload = await res.json();
      render(getJobs(payload));
      setError(false);
      setSync(new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));
    } catch (err) {
      setError(true);
      setSync('Sync failed');
    }
  }

  function tickClock() {
    const el = qs('[data-floor-clock]');
    if (!el) return;
    el.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  function init() {
    tickClock();
    load();
    setInterval(tickClock, 1000);
    setInterval(load, refreshMs);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
