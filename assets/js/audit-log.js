(function () {
  'use strict';

  var cfg = window.slateOpsAudit || {};
  var apiRoot = cfg.api && cfg.api.root ? cfg.api.root.replace(/\/$/, '') : '/wp-json/slate-ops/v1';
  var nonce = cfg.api && cfg.api.nonce ? cfg.api.nonce : '';

  var state = {
    page: 1,
    limit: 50,
    pages: 1,
    items: [],
    facetsLoaded: false
  };

  function $(selector, root) {
    return (root || document).querySelector(selector);
  }

  function $$(selector, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(selector));
  }

  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function titleize(value) {
    return String(value || '')
      .replace(/_/g, ' ')
      .replace(/-/g, ' ')
      .replace(/\s+/g, ' ')
      .trim()
      .replace(/\b\w/g, function (m) { return m.toUpperCase(); });
  }

  function sentence(value) {
    var s = titleize(value);
    return s ? s.charAt(0).toUpperCase() + s.slice(1) : 'Event';
  }

  function formatDate(value) {
    if (!value) return 'Unknown';
    var iso = String(value).replace(' ', 'T') + 'Z';
    var date = new Date(iso);
    if (isNaN(date.getTime())) return value;
    return date.toLocaleString([], {
      month: 'short',
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit'
    });
  }

  function toDateInput(date) {
    return date.toISOString().slice(0, 10);
  }

  function valueText(value) {
    if (value == null || value === '') return '';
    if (typeof value === 'object') return JSON.stringify(value, null, 2);
    return String(value);
  }

  function shortValue(value) {
    var text = valueText(value);
    if (!text) return '';
    text = text.replace(/\s+/g, ' ').trim();
    return text.length > 90 ? text.slice(0, 87) + '...' : text;
  }

  function changeText(item) {
    var oldText = shortValue(item.old_value);
    var newText = shortValue(item.new_value);

    if (oldText && newText) return oldText + ' -> ' + newText;
    if (newText) return newText;
    if (oldText) return 'Previous: ' + oldText;
    return item.field_name ? sentence(item.field_name) : '';
  }

  function targetText(item) {
    if (item.entity_type === 'job') {
      var parts = [];
      if (item.so_number) parts.push(item.so_number);
      if (item.customer_name) parts.push(item.customer_name);
      if (!parts.length) parts.push('Job ' + item.entity_id);
      return parts.join(' · ');
    }
    if (item.entity_type === 'user') return 'User ' + item.entity_id;
    if (item.entity_type === 'settings') return 'Settings';
    if (item.entity_type === 'scheduler') return 'Scheduler';
    if (item.entity_type === 'work_center') return 'Work center ' + item.entity_id;
    return sentence(item.entity_type) + ' ' + item.entity_id;
  }

  function buildQuery() {
    var params = new URLSearchParams();
    params.set('page', state.page);
    params.set('limit', state.limit);
    params.set('q', $('#audit-search').value.trim());
    params.set('date_from', $('#audit-date-from').value);
    params.set('date_to', $('#audit-date-to').value);
    params.set('entity_type', $('#audit-entity').value);
    params.set('action', $('#audit-action').value);
    params.set('user_id', $('#audit-user').value);
    return params.toString();
  }

  async function apiGet(path) {
    var res = await fetch(apiRoot + path, {
      headers: {
        'Accept': 'application/json',
        'X-WP-Nonce': nonce
      }
    });
    var text = await res.text();
    var data = text ? JSON.parse(text) : {};
    if (!res.ok) throw new Error(data && data.message ? data.message : 'Request failed');
    return data;
  }

  function setSummary(summary) {
    $('#audit-summary-total').textContent = summary.total || 0;
    $('#audit-summary-jobs').textContent = summary.jobs || 0;
    $('#audit-summary-users').textContent = summary.users || 0;
    $('#audit-summary-system').textContent = summary.system || 0;
    $('#audit-summary-high-signal').textContent = summary.high_signal || 0;
  }

  function option(value, label) {
    return '<option value="' + esc(value) + '">' + esc(label) + '</option>';
  }

  function hydrateFacets(facets) {
    if (state.facetsLoaded || !facets) return;

    var entity = $('#audit-entity');
    entity.innerHTML = option('all', 'All areas') + (facets.entities || []).map(function (item) {
      return option(item, sentence(item));
    }).join('');

    var action = $('#audit-action');
    action.innerHTML = option('all', 'All actions') + (facets.actions || []).map(function (item) {
      return option(item, sentence(item));
    }).join('');

    var user = $('#audit-user');
    user.innerHTML = option('0', 'All users') + (facets.users || []).map(function (item) {
      var label = item.name || item.email || ('User ' + item.id);
      return option(item.id, label);
    }).join('');

    state.facetsLoaded = true;
  }

  function renderRows(items) {
    var body = $('#audit-log-body');
    if (!items.length) {
      body.innerHTML = '<tr><td colspan="7" class="ops-audit-empty">No audit events match these filters.</td></tr>';
      return;
    }

    body.innerHTML = items.map(function (item, index) {
      var change = changeText(item);
      return ''
        + '<tr class="ops-audit-row" data-index="' + index + '" tabindex="0">'
        + '<td><strong>' + esc(formatDate(item.created_at)) + '</strong><br><span class="ops-audit-muted">#' + esc(item.audit_id) + '</span></td>'
        + '<td>' + esc(item.user_name || 'System') + '</td>'
        + '<td><span class="ops-audit-pill ops-audit-pill--' + esc(String(item.entity_type || '').replace(/_/g, '-')) + '">' + esc(sentence(item.entity_type)) + '</span></td>'
        + '<td>' + esc(sentence(item.action)) + '</td>'
        + '<td>' + esc(targetText(item)) + '</td>'
        + '<td><span class="ops-audit-change">' + esc(change || 'No field change') + '</span></td>'
        + '<td>' + esc(item.note || '') + '</td>'
        + '</tr>';
    }).join('');

    $$('.ops-audit-row', body).forEach(function (row) {
      row.addEventListener('click', function () {
        openDrawer(state.items[Number(row.getAttribute('data-index'))]);
      });
      row.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          openDrawer(state.items[Number(row.getAttribute('data-index'))]);
        }
      });
    });
  }

  function renderPagination(pagination) {
    state.pages = pagination.pages || 1;
    $('#audit-page-status').textContent = 'Page ' + (pagination.page || 1) + ' of ' + state.pages + ' · ' + (pagination.total || 0) + ' events';
    $('#audit-prev').disabled = state.page <= 1;
    $('#audit-next').disabled = state.page >= state.pages;
  }

  async function load() {
    var body = $('#audit-log-body');
    body.innerHTML = '<tr><td colspan="7" class="ops-audit-empty">Loading audit events.</td></tr>';

    try {
      var data = await apiGet('/audit-log?' + buildQuery());
      state.items = data.items || [];
      hydrateFacets(data.facets);
      setSummary(data.summary || {});
      renderRows(state.items);
      renderPagination(data.pagination || {});
    } catch (error) {
      body.innerHTML = '<tr><td colspan="7" class="ops-audit-empty">' + esc(error.message || 'Unable to load audit events.') + '</td></tr>';
    }
  }

  function detailList(rows) {
    return '<dl class="ops-audit-detail-list">' + rows.map(function (row) {
      return '<div><dt>' + esc(row[0]) + '</dt><dd>' + esc(row[1] || 'None') + '</dd></div>';
    }).join('') + '</dl>';
  }

  function openDrawer(item) {
    if (!item) return;
    $('#audit-detail-kicker').textContent = 'Audit event #' + item.audit_id;
    $('#audit-detail-title').textContent = sentence(item.action) + ' · ' + sentence(item.entity_type);

    var oldText = valueText(item.old_value);
    var newText = valueText(item.new_value);
    var technical = [
      ['Audit id', item.audit_id],
      ['IP address', item.ip_address],
      ['User agent', item.user_agent],
      ['Stored time', item.created_at]
    ];

    $('#audit-detail-body').innerHTML = ''
      + '<section class="ops-audit-detail-group"><h3>Summary</h3>'
      + detailList([
        ['Time', formatDate(item.created_at)],
        ['User', item.user_name || 'System'],
        ['Target', targetText(item)],
        ['Field', item.field_name ? sentence(item.field_name) : 'None'],
        ['Note', item.note || 'None']
      ])
      + '</section>'
      + '<section class="ops-audit-detail-group"><h3>Before</h3><pre class="ops-audit-code">' + esc(oldText || 'None') + '</pre></section>'
      + '<section class="ops-audit-detail-group"><h3>After</h3><pre class="ops-audit-code">' + esc(newText || 'None') + '</pre></section>'
      + '<section class="ops-audit-detail-group"><h3>Technical details</h3>' + detailList(technical) + '</section>';

    $('#audit-detail-drawer').setAttribute('aria-hidden', 'false');
  }

  function closeDrawer() {
    $('#audit-detail-drawer').setAttribute('aria-hidden', 'true');
  }

  function resetDefaults() {
    var today = new Date();
    var sevenDaysAgo = new Date(today.getTime() - 6 * 24 * 60 * 60 * 1000);
    $('#audit-search').value = '';
    $('#audit-date-from').value = toDateInput(sevenDaysAgo);
    $('#audit-date-to').value = toDateInput(today);
    $('#audit-entity').value = 'all';
    $('#audit-action').value = 'all';
    $('#audit-user').value = '0';
    state.page = 1;
  }

  function bind() {
    $('#audit-filter-form').addEventListener('submit', function (event) {
      event.preventDefault();
      state.page = 1;
      load();
    });

    $('#audit-reset').addEventListener('click', function () {
      resetDefaults();
      load();
    });

    $('#audit-prev').addEventListener('click', function () {
      if (state.page > 1) {
        state.page -= 1;
        load();
      }
    });

    $('#audit-next').addEventListener('click', function () {
      if (state.page < state.pages) {
        state.page += 1;
        load();
      }
    });

    $$('[data-audit-close]').forEach(function (el) {
      el.addEventListener('click', closeDrawer);
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') closeDrawer();
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var title = document.getElementById('ops-page-title');
    if (title) title.textContent = 'Audit log';
    bind();
    resetDefaults();
    load();
  });
})();
