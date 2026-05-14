/**
 * Slate Ops — Supervisor Dashboard.
 * Vanilla JS enhancement for tabs, filters, refresh timestamp, and drawer.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-supervisor-dashboard]');
  if (!root) return;

  var localized = window.slateOpsSupervisorDashboard || {};
  var payload = localized.payload || {};
  var dataBlob = document.getElementById('supervisor-dashboard-data');
  if (dataBlob) {
    try { payload = JSON.parse(dataBlob.textContent || '{}'); } catch (e) {}
  }

  function $(selector, scope) {
    return (scope || document).querySelector(selector);
  }

  function $$(selector, scope) {
    return Array.prototype.slice.call((scope || document).querySelectorAll(selector));
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function statusClass(status) {
    return 'ops-supervisor-pill ops-supervisor-pill--' + String(status || 'neutral').replace(/[^a-z0-9-]/gi, '-').toLowerCase();
  }

  function setActiveTab(tab) {
    if (!tab) return;
    $$('.ops-supervisor-tab').forEach(function (button) {
      var active = button.getAttribute('data-tab') === tab;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    $$('.ops-supervisor-tab-panel').forEach(function (panel) {
      var active = panel.getAttribute('data-tab-panel') === tab;
      panel.hidden = !active;
      panel.classList.toggle('is-active', active);
    });
  }

  $$('.ops-supervisor-tab').forEach(function (button) {
    button.addEventListener('click', function () {
      setActiveTab(button.getAttribute('data-tab'));
      if (button.scrollIntoView) {
        try { button.scrollIntoView({ block: 'nearest', inline: 'nearest' }); } catch (e) {}
      }
    });
  });

  $$('[data-tab-jump]').forEach(function (button) {
    button.addEventListener('click', function () {
      setActiveTab(button.getAttribute('data-tab-jump'));
      root.scrollIntoView({ block: 'start' });
    });
  });

  var refreshButton = $('#ops-supervisor-refresh');
  var refreshLabel = $('#ops-supervisor-refresh-label');
  if (refreshButton && refreshLabel) {
    refreshButton.addEventListener('click', function () {
      refreshLabel.textContent = 'Refreshed just now';
      showToast('Dashboard refreshed');
    });
  }

  $$('.ops-supervisor-chip').forEach(function (chip) {
    chip.addEventListener('click', function () {
      var category = chip.getAttribute('data-category-filter') || 'all';
      $$('.ops-supervisor-chip').forEach(function (item) {
        item.classList.toggle('is-active', item === chip);
      });
      $$('.ops-supervisor-tab-panel[data-tab-panel="blocked"] .ops-supervisor-job-row').forEach(function (row) {
        var rowCategory = row.getAttribute('data-category') || '';
        row.hidden = category !== 'all' && rowCategory !== category;
      });
    });
  });

  var drawer = $('#ops-supervisor-drawer');
  var backdrop = $('#ops-supervisor-drawer-backdrop');
  var closeButton = $('#ops-supervisor-drawer-close');
  var toast = $('#ops-supervisor-toast');
  var toastTimer;

  function showToast(message) {
    if (!toast) return;
    toast.textContent = message || 'Read-only first pass. No job change was written.';
    toast.classList.add('is-open');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () {
      toast.classList.remove('is-open');
    }, 2400);
  }

  function openDrawer(job) {
    if (!drawer || !job) return;
    var tech = job.tech && job.tech.name ? job.tech.name : 'Unassigned';
    var customer = (job.dealer || '—') + ' / ' + (job.customer || '—');
    $('#ops-supervisor-drawer-job').textContent = job.id || '—';
    $('#ops-supervisor-drawer-customer').textContent = customer;

    var status = $('#ops-supervisor-drawer-status');
    status.textContent = job.status_label || job.status || '—';
    status.className = statusClass(job.status);

    var kv = $('#ops-supervisor-drawer-kv');
    kv.innerHTML = [
      ['SO #', job.so],
      ['Assigned tech', tech],
      ['Current step', job.step],
      ['Due date', job.due],
      ['Bay', job.bay],
      ['Work center', job.work_center],
      ['Parts status', job.parts],
      ['QC status', job.qc]
    ].map(function (item) {
      return '<dt>' + escapeHtml(item[0]) + '</dt><dd>' + escapeHtml(item[1] || '—') + '</dd>';
    }).join('');

    var blocker = $('#ops-supervisor-drawer-blocker');
    if (job.blocked_reason) {
      blocker.hidden = false;
      blocker.innerHTML = '<strong>' + escapeHtml(job.blocked_category || 'Blocker') + '</strong><br>' + escapeHtml(job.blocked_reason);
    } else {
      blocker.hidden = true;
      blocker.textContent = '';
    }

    $('#ops-supervisor-drawer-last-note').textContent = job.last_note || 'No note recorded.';
    var notes = $('#ops-supervisor-drawer-notes');
    var rows = Array.isArray(job.notes) ? job.notes : [];
    if (!rows.length) {
      notes.innerHTML = '<p>No recent activity in the stub payload.</p>';
    } else {
      notes.innerHTML = rows.map(function (note) {
        return '<div class="ops-supervisor-note"><strong>' + escapeHtml(note.who) + '</strong> <span>' + escapeHtml(note.role) + ' · ' + escapeHtml(note.when) + '</span><br>' + escapeHtml(note.text) + '</div>';
      }).join('');
    }

    drawer.classList.add('is-open');
    drawer.setAttribute('aria-hidden', 'false');
    if (backdrop) backdrop.classList.add('is-open');
    if (closeButton) closeButton.focus();
  }

  function closeDrawer() {
    if (!drawer) return;
    drawer.classList.remove('is-open');
    drawer.setAttribute('aria-hidden', 'true');
    if (backdrop) backdrop.classList.remove('is-open');
  }

  function jobFromRow(row) {
    try { return JSON.parse(row.getAttribute('data-job') || '{}'); }
    catch (e) { return null; }
  }

  $$('.ops-supervisor-job-row').forEach(function (row) {
    row.addEventListener('click', function (event) {
      if (event.target.closest('[data-readonly-action]')) return;
      openDrawer(jobFromRow(row));
    });
    row.addEventListener('keydown', function (event) {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        openDrawer(jobFromRow(row));
      }
    });
  });

  if (closeButton) closeButton.addEventListener('click', closeDrawer);
  if (backdrop) backdrop.addEventListener('click', closeDrawer);
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeDrawer();
  });

  $$('[data-readonly-action]').forEach(function (button) {
    button.addEventListener('click', function (event) {
      event.stopPropagation();
      showToast('Read-only first pass. No job change was written.');
    });
  });

  if (payload && payload.tabs && window.location.hash) {
    var hashTab = window.location.hash.replace(/^#/, '');
    if ($('.ops-supervisor-tab[data-tab="' + hashTab + '"]')) {
      setActiveTab(hashTab);
    }
  }
})();
