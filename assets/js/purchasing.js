/**
 * Slate Ops — Purchasing Workspace
 *
 * Standalone vanilla-JS module. Loaded only on /ops/purchasing.
 * Phase 1: reads from local WordPress purchasing tables via REST.
 * No Business Central. No Power Automate. No jQuery.
 *
 * Tabs: Overview · Demand · Purchase Requests · Vendors · Open POs · API Status
 */
(function () {
  'use strict';

  // ─── API config (injected by wp_localize_script as slateOpsPurchasing) ────
  var _cfg      = window.slateOpsPurchasing || {};
  var API_ROOT  = (_cfg.api && _cfg.api.root)  ? _cfg.api.root  : null;
  var API_NONCE = (_cfg.api && _cfg.api.nonce) ? _cfg.api.nonce : '';

  // ─── State (shape per SOT) ────────────────────────────────────────────────
  var state = {
    tab:              'overview',
    loading:          false,
    error:            null,
    notice:           null,
    summary:          null,   // overview data
    demand:           [],
    requests:         [],
    vendors:          [],
    openPos:          [],
    activity:         [],
    selectedDemandIds: [],
    filters: {
      search:  '',
      urgency: 'all',
      vendor:  'all',
    },
    activeRequestId: null,
  };

  // Track which tabs have been loaded to avoid redundant fetches.
  var _loaded = {};

  // ─── REST fetch ───────────────────────────────────────────────────────────
  function apiFetch(path) {
    if (!API_ROOT) {
      return Promise.reject(new Error('API not configured'));
    }
    return fetch(API_ROOT + '/purchasing/' + path, {
      credentials: 'same-origin',
      headers: { 'X-WP-Nonce': API_NONCE },
    }).then(function (res) {
      if (!res.ok) {
        return res.json().then(function (body) {
          throw new Error(body.message || 'Request failed (' + res.status + ')');
        }).catch(function () {
          throw new Error('Request failed (' + res.status + ')');
        });
      }
      return res.json();
    });
  }

  function loadAll() {
    state.loading = true;
    state.error   = null;
    render();

    Promise.allSettled([
      apiFetch('overview'),
      apiFetch('items'),
      apiFetch('requests'),
      apiFetch('vendors'),
      apiFetch('orders'),
    ]).then(function (results) {
      var errors = [];
      function val(r, fallback) {
        if (r.status === 'fulfilled') return r.value;
        errors.push(r.reason && r.reason.message ? r.reason.message : 'A request failed');
        return fallback;
      }
      state.summary  = val(results[0], null);
      state.demand   = val(results[1], []);
      state.requests = val(results[2], []);
      state.vendors  = val(results[3], []);
      state.openPos  = val(results[4], []);
      state.activity = (state.summary && state.summary.recent_activity) ? state.summary.recent_activity : [];
      state.loading  = false;
      _loaded.all    = true;
      if (errors.length) {
        state.error = 'Some data failed to load: ' + errors.join('; ');
      }
      render();
    });
  }

  // ─── Utility ──────────────────────────────────────────────────────────────
  function esc(val) {
    return String(val == null ? '' : val)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function fmt$(n) {
    return '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function fmtDate(dt) {
    if (!dt) return '—';
    var d = new Date(String(dt).replace(' ', 'T') + 'Z');
    if (isNaN(d.getTime())) return String(dt);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  }

  function fmtLeadTime(days) {
    var n = parseInt(days, 10);
    if (!n) return '—';
    return n + (n === 1 ? ' day' : ' days');
  }

  function badge(status) {
    var labels = {
      // Purchase request statuses (SOT)
      draft:        'Draft',
      review:       'In Review',
      approved:     'Approved',
      held:         'Held',
      ordered:      'Ordered',
      cancelled:    'Cancelled',
      // PO statuses
      submitted:    'Submitted',
      acknowledged: 'Acknowledged',
      shipped:      'Shipped',
      partial:      'Partial Receipt',
      received:     'Received',
      closed:       'Closed',
      // Vendor statuses
      active:       'Active',
      inactive:     'Inactive',
    };
    return '<span class="pur-badge pur-badge--' + esc(status) + '">' + esc(labels[status] || status) + '</span>';
  }

  function setPageTitle(title) {
    var el = document.getElementById('ops-page-title');
    if (el) el.textContent = title;
  }

  function setActiveNav() {
    document.querySelectorAll('.ops-nav-link').forEach(function (a) {
      a.classList.toggle('active', a.getAttribute('data-route') === '/purchasing');
    });
  }

  function clearNotice() {
    state.notice = null;
  }

  // ─── Micro-states ─────────────────────────────────────────────────────────
  function renderLoading() {
    return '<div class="pur-empty">' +
      '<span class="material-symbols-outlined">hourglass_empty</span>' +
      '<div class="pur-empty-title">Loading…</div>' +
    '</div>';
  }

  function renderEmpty(icon, title, sub) {
    return '<div class="pur-empty">' +
      '<span class="material-symbols-outlined">' + esc(icon) + '</span>' +
      '<div class="pur-empty-title">' + esc(title) + '</div>' +
      (sub ? '<div>' + esc(sub) + '</div>' : '') +
    '</div>';
  }

  // ─── Top-level banners ────────────────────────────────────────────────────
  function renderErrorBanner() {
    if (!state.error) return '';
    return '<div class="pur-error-banner">' +
      '<span class="material-symbols-outlined">error_outline</span>' +
      '<span>' + esc(state.error) + '</span>' +
      '<button class="pur-banner-close" data-action="clear-error">✕</button>' +
    '</div>';
  }

  function renderNoticeBanner() {
    if (!state.notice) return '';
    return '<div class="pur-notice-banner">' +
      '<span class="material-symbols-outlined">check_circle</span>' +
      '<span>' + esc(state.notice) + '</span>' +
      '<button class="pur-banner-close" data-action="clear-notice">✕</button>' +
    '</div>';
  }

  // ─── Sub-nav ──────────────────────────────────────────────────────────────
  var TABS = [
    { id: 'overview',   icon: 'dashboard',     label: 'Overview' },
    { id: 'demand',     icon: 'trending_up',   label: 'Demand' },
    { id: 'requests',   icon: 'request_quote', label: 'Purchase Requests' },
    { id: 'vendors',    icon: 'storefront',    label: 'Vendors' },
    { id: 'open-pos',   icon: 'receipt_long',  label: 'Open POs' },
    { id: 'api-status', icon: 'cloud_off',     label: 'API Status' },
  ];

  function renderSubnav() {
    return '<nav class="pur-subnav">' +
      TABS.map(function (t) {
        return '<button class="pur-tab' + (t.id === state.tab ? ' active' : '') + '" data-tab="' + esc(t.id) + '">' +
          '<span class="material-symbols-outlined">' + esc(t.icon) + '</span>' +
          esc(t.label) +
        '</button>';
      }).join('') +
    '</nav>';
  }

  // ─── Overview tab ─────────────────────────────────────────────────────────
  function renderOverview() {
    if (state.loading) return renderLoading();

    var d = state.summary;
    if (!d) return renderEmpty('dashboard', 'No data yet', '');

    var kpis = [
      { label: 'Open Purchase Requests', value: d.open_requests,       sub: d.pending_review + ' in review',                        mod: d.pending_review > 0 ? ' pur-kpi--alert' : '' },
      { label: 'Items Below Reorder',    value: d.items_below_reorder, sub: 'of ' + d.items_tracked + ' tracked parts',             mod: d.items_below_reorder > 0 ? ' pur-kpi--alert' : '' },
      { label: 'Items on Order',         value: d.items_on_order,      sub: d.open_orders + ' open purchase order' + (d.open_orders !== 1 ? 's' : ''), mod: '' },
      { label: 'Active Vendors',         value: d.active_vendors,      sub: d.total_vendors + ' vendor' + (d.total_vendors !== 1 ? 's' : '') + ' total', mod: '' },
    ];

    var kpiHtml = '<div class="pur-kpi-grid">' +
      kpis.map(function (k) {
        return '<div class="pur-kpi' + k.mod + '">' +
          '<div class="pur-kpi-label">' + esc(k.label) + '</div>' +
          '<div class="pur-kpi-value">' + esc(String(k.value)) + '</div>' +
          '<div class="pur-kpi-sub">' + esc(k.sub) + '</div>' +
        '</div>';
      }).join('') +
    '</div>';

    var activityBody = !state.activity.length
      ? renderEmpty('history', 'No recent activity', 'Activity will appear here as purchase requests are created.')
      : '<ul class="pur-activity-list">' +
          state.activity.map(function (a) {
            return '<li class="pur-activity-item">' +
              '<div class="pur-activity-icon"><span class="material-symbols-outlined">' + esc(a.icon) + '</span></div>' +
              '<div class="pur-activity-body">' +
                '<div class="pur-activity-text">' + esc(a.text) + '</div>' +
                '<div class="pur-activity-time">' + esc(a.time) + '</div>' +
              '</div>' +
            '</li>';
          }).join('') +
        '</ul>';

    var activityHtml = '<div class="pur-card">' +
      '<div class="pur-card-header"><h2 class="pur-card-title">Recent Activity</h2></div>' +
      '<div class="pur-card-body">' + activityBody + '</div>' +
    '</div>';

    var poRows = d.open_po_summary || [];
    var summaryBody = !poRows.length
      ? renderEmpty('receipt_long', 'No open purchase orders', '')
      : '<div class="pur-table-wrap"><table class="pur-table"><thead><tr>' +
          '<th>PO #</th><th>Vendor</th><th>Lines</th><th>Status</th><th>Expected</th>' +
        '</tr></thead><tbody>' +
        poRows.map(function (p) {
          return '<tr>' +
            '<td class="pur-col-mono">' + esc(p.po_number) + '</td>' +
            '<td>' + esc(p.vendor_name || '—') + '</td>' +
            '<td class="pur-col-num">' + esc(p.line_count || 0) + '</td>' +
            '<td>' + badge(p.status) + '</td>' +
            '<td class="pur-col-muted">' + esc(fmtDate(p.expected_date)) + '</td>' +
          '</tr>';
        }).join('') +
        '</tbody></table></div>';

    return kpiHtml + activityHtml +
      '<div class="pur-card">' +
        '<div class="pur-card-header"><h2 class="pur-card-title">Open PO Summary</h2></div>' +
        summaryBody +
      '</div>';
  }

  // ─── Demand tab ───────────────────────────────────────────────────────────
  function filteredDemand() {
    var f = state.filters;
    return state.demand.filter(function (d) {
      if (f.search) {
        var q = f.search.toLowerCase();
        if (String(d.part_number || '').toLowerCase().indexOf(q) === -1 &&
            String(d.description || '').toLowerCase().indexOf(q) === -1) return false;
      }
      if (f.urgency !== 'all' && d.demand_level !== f.urgency) return false;
      if (f.vendor !== 'all' && (d.preferred_vendor_name || '') !== f.vendor) return false;
      return true;
    });
  }

  function renderDemandFilters() {
    // Collect unique vendors from demand items for the vendor select
    var vendorSet = {};
    state.demand.forEach(function (d) { if (d.preferred_vendor_name) vendorSet[d.preferred_vendor_name] = true; });
    var vendorOptions = Object.keys(vendorSet).sort().map(function (v) {
      return '<option value="' + esc(v) + '"' + (state.filters.vendor === v ? ' selected' : '') + '>' + esc(v) + '</option>';
    }).join('');

    return '<div class="pur-filters">' +
      '<input class="pur-filter-input" type="text" placeholder="Search part # or description…"' +
        ' value="' + esc(state.filters.search) + '" data-filter="search">' +
      '<select class="pur-filter-select" data-filter="urgency">' +
        '<option value="all"' + (state.filters.urgency === 'all'    ? ' selected' : '') + '>All urgency</option>' +
        '<option value="high"' + (state.filters.urgency === 'high'   ? ' selected' : '') + '>High</option>' +
        '<option value="medium"' + (state.filters.urgency === 'medium' ? ' selected' : '') + '>Medium</option>' +
        '<option value="low"' + (state.filters.urgency === 'low'    ? ' selected' : '') + '>Low</option>' +
      '</select>' +
      '<select class="pur-filter-select" data-filter="vendor">' +
        '<option value="all"' + (state.filters.vendor === 'all' ? ' selected' : '') + '>All vendors</option>' +
        vendorOptions +
      '</select>' +
    '</div>';
  }

  function renderDemand() {
    if (state.loading) return renderLoading();

    var items = filteredDemand();
    var belowReorder = items.filter(function (d) {
      return parseInt(d.reorder_point, 10) > 0 && parseInt(d.on_hand, 10) <= parseInt(d.reorder_point, 10);
    }).length;

    var body;
    if (!state.demand.length) {
      body = renderEmpty('inventory_2', 'No items tracked', 'Items will appear here once the inventory catalog is populated.');
    } else if (!items.length) {
      body = renderEmpty('search_off', 'No results', 'Try adjusting the filters.');
    } else {
      var rows = items.map(function (d) {
        var onHand  = parseInt(d.on_hand, 10);
        var reorder = parseInt(d.reorder_point, 10);
        var needsReorder = reorder > 0 && onHand <= reorder;
        var pct      = reorder > 0 ? Math.min(100, Math.round((onHand / reorder) * 100)) : 100;
        var level    = d.demand_level || 'low';
        var barClass = level === 'high' ? 'pur-demand-bar--high'
                     : level === 'medium' ? 'pur-demand-bar--medium'
                     : 'pur-demand-bar--low';
        return '<tr' + (needsReorder ? ' class="pur-row-alert"' : '') + '>' +
          '<td class="pur-col-mono">' + esc(d.part_number) + '</td>' +
          '<td>' + esc(d.description) + '</td>' +
          '<td class="pur-col-muted">' + esc(d.preferred_vendor_name || '—') + '</td>' +
          '<td class="pur-col-num' + (needsReorder ? ' pur-col-alert' : '') + '">' + onHand + '</td>' +
          '<td class="pur-col-num pur-col-muted">' + reorder + '</td>' +
          '<td class="pur-col-num">' +
            '<div class="pur-demand-bar-wrap"><div class="pur-demand-bar ' + barClass + '" style="width:' + pct + '%"></div></div>' +
          '</td>' +
          '<td class="pur-col-num">' + esc(d.forecasted_need || 0) + '</td>' +
          '<td class="pur-col-num">' + esc(d.suggested_order || 0) + '</td>' +
        '</tr>';
      }).join('');
      body = '<div class="pur-table-wrap"><table class="pur-table">' +
        '<thead><tr>' +
          '<th>Part #</th>' +
          '<th>Description</th>' +
          '<th>Vendor</th>' +
          '<th style="text-align:right">On Hand</th>' +
          '<th style="text-align:right">Reorder Pt.</th>' +
          '<th style="text-align:right">Stock Level</th>' +
          '<th style="text-align:right">Forecasted Need</th>' +
          '<th style="text-align:right">Suggested Order</th>' +
        '</tr></thead>' +
        '<tbody>' + rows + '</tbody>' +
      '</table></div>';
    }

    var meta = state.demand.length
      ? items.length + ' of ' + state.demand.length + ' items · ' + belowReorder + ' below reorder'
      : '';

    return '<div class="pur-card">' +
      '<div class="pur-card-header">' +
        '<h2 class="pur-card-title">Demand Forecast</h2>' +
        '<span class="pur-card-meta">' + esc(meta) + '</span>' +
      '</div>' +
      renderDemandFilters() +
      body +
    '</div>';
  }

  // ─── Purchase Requests tab ────────────────────────────────────────────────
  function renderRequests() {
    if (state.loading) return renderLoading();

    var body;
    if (!state.requests.length) {
      body = renderEmpty('request_quote', 'No purchase requests', 'Purchase requests will appear here once created (Phase 2).');
    } else {
      var rows = state.requests.map(function (r) {
        var total      = parseFloat(r.qty || 0) * parseFloat(r.unit_cost || 0);
        var vendorName = r.vendor_name_resolved || '—';
        return '<tr>' +
          '<td class="pur-col-mono">' + esc(r.request_number || '—') + '</td>' +
          '<td>' + esc(r.item_description) + '</td>' +
          '<td class="pur-col-muted">' + esc(vendorName) + '</td>' +
          '<td class="pur-col-num">' + esc(r.qty) + '</td>' +
          '<td class="pur-col-num pur-col-muted">' + fmt$(r.unit_cost) + '</td>' +
          '<td class="pur-col-num">' + fmt$(total) + '</td>' +
          '<td>' + badge(r.status) + '</td>' +
          '<td class="pur-col-muted">' + esc(fmtDate(r.created_at)) + '</td>' +
          '<td class="pur-col-muted">' + esc(r.requested_by_name || '—') + '</td>' +
        '</tr>';
      }).join('');
      body = '<div class="pur-table-wrap"><table class="pur-table">' +
        '<thead><tr>' +
          '<th>PR #</th><th>Item</th><th>Vendor</th>' +
          '<th style="text-align:right">Qty</th>' +
          '<th style="text-align:right">Unit Cost</th>' +
          '<th style="text-align:right">Total</th>' +
          '<th>Status</th><th>Date</th><th>Requested By</th>' +
        '</tr></thead>' +
        '<tbody>' + rows + '</tbody>' +
      '</table></div>';
    }

    return '<div class="pur-card">' +
      '<div class="pur-card-header">' +
        '<h2 class="pur-card-title">Purchase Requests</h2>' +
        '<span class="pur-card-meta">' + state.requests.length + ' total</span>' +
      '</div>' +
      body +
    '</div>';
  }

  // ─── Vendors tab ──────────────────────────────────────────────────────────
  function renderVendors() {
    if (state.loading) return renderLoading();

    var body;
    if (!state.vendors.length) {
      body = renderEmpty('storefront', 'No vendors', 'Vendors will appear here once added.');
    } else {
      var rows = state.vendors.map(function (v) {
        return '<tr>' +
          '<td class="pur-col-mono">' + esc(v.id) + '</td>' +
          '<td style="font-weight:500">' + esc(v.name) + '</td>' +
          '<td class="pur-col-muted">' + esc(v.contact_email || v.contact_phone || '—') + '</td>' +
          '<td class="pur-col-muted">' + esc(fmtLeadTime(v.lead_time_days)) + '</td>' +
          '<td class="pur-col-muted">' + esc(v.payment_terms || '—') + '</td>' +
          '<td>' + badge(v.status) + '</td>' +
        '</tr>';
      }).join('');
      body = '<div class="pur-table-wrap"><table class="pur-table">' +
        '<thead><tr>' +
          '<th>ID</th><th>Name</th><th>Contact</th><th>Lead Time</th><th>Terms</th><th>Status</th>' +
        '</tr></thead>' +
        '<tbody>' + rows + '</tbody>' +
      '</table></div>';
    }

    return '<div class="pur-card">' +
      '<div class="pur-card-header">' +
        '<h2 class="pur-card-title">Vendors</h2>' +
        '<span class="pur-card-meta">' + state.vendors.filter(function (v) { return v.status === 'active'; }).length + ' active</span>' +
      '</div>' +
      body +
    '</div>';
  }

  // ─── Open POs tab ─────────────────────────────────────────────────────────
  function renderOpenPOs() {
    if (state.loading) return renderLoading();

    var body;
    if (!state.openPos.length) {
      body = renderEmpty('receipt_long', 'No open purchase orders', 'Purchase orders will appear here once created.');
    } else {
      var rows = state.openPos.map(function (p) {
        return '<tr>' +
          '<td class="pur-col-mono">' + esc(p.po_number) + '</td>' +
          '<td style="font-weight:500">' + esc(p.vendor_name || '—') + '</td>' +
          '<td class="pur-col-num pur-col-muted">' + esc(p.line_count || 0) + '</td>' +
          '<td class="pur-col-num">' + fmt$(p.total_value || 0) + '</td>' +
          '<td class="pur-col-muted">' + esc(fmtDate(p.ordered_at)) + '</td>' +
          '<td class="pur-col-muted">' + esc(fmtDate(p.expected_date)) + '</td>' +
          '<td>' + badge(p.status) + '</td>' +
        '</tr>';
      }).join('');
      var totalValue = state.openPos.reduce(function (s, p) { return s + parseFloat(p.total_value || 0); }, 0);
      body = '<div class="pur-table-wrap"><table class="pur-table">' +
        '<thead><tr>' +
          '<th>PO #</th><th>Vendor</th>' +
          '<th style="text-align:right">Lines</th>' +
          '<th style="text-align:right">Total</th>' +
          '<th>Ordered</th><th>Expected</th><th>Status</th>' +
        '</tr></thead>' +
        '<tbody>' + rows + '</tbody>' +
      '</table></div>';
    }

    var meta = state.openPos.length
      ? state.openPos.length + ' open'
      : '0 open';

    return '<div class="pur-card">' +
      '<div class="pur-card-header">' +
        '<h2 class="pur-card-title">Open Purchase Orders</h2>' +
        '<span class="pur-card-meta">' + esc(meta) + '</span>' +
      '</div>' +
      body +
    '</div>';
  }

  // ─── API Status tab ───────────────────────────────────────────────────────
  function renderAPIStatus() {
    var rows = [
      { label: 'Business Central',    value: 'Not Connected',    cls: 'pur-api-row-value--warn' },
      { label: 'Power Automate',      value: 'Not Configured',   cls: 'pur-api-row-value--muted' },
      { label: 'Flow URLs',           value: 'Not Configured',   cls: 'pur-api-row-value--muted' },
      { label: 'Flow Secrets',        value: 'Not Configured',   cls: 'pur-api-row-value--muted' },
      { label: 'Last Sync',           value: 'Never',            cls: 'pur-api-row-value--muted' },
      { label: 'WP Purchasing Tables','value': 'Available',      cls: 'pur-api-row-value--ok' },
    ];

    var statusCard = '<div class="pur-api-card">' +
      '<div class="pur-api-card-title">Integration Status</div>' +
      rows.map(function (r) {
        return '<div class="pur-api-row">' +
          '<span class="pur-api-row-label">' + esc(r.label) + '</span>' +
          '<span class="pur-api-row-value ' + r.cls + '">' + esc(r.value) + '</span>' +
        '</div>';
      }).join('') +
    '</div>';

    var entitiesCard = '<div class="pur-api-card">' +
      '<div class="pur-api-card-title">BC Entities (Phase 2 — read confirmed)</div>' +
      [
        { entity: 'vendors',            note: 'Vendor master data'       },
        { entity: 'items',              note: 'Parts catalog'             },
        { entity: 'purchaseOrders',     note: 'PO headers'                },
        { entity: 'purchaseOrderLines', note: 'PO line items'             },
      ].map(function (e) {
        return '<div class="pur-api-row">' +
          '<span class="pur-api-row-label" style="font-family:var(--font-mono);font-size:12px;">' + esc(e.entity) + '</span>' +
          '<span class="pur-api-row-value pur-api-row-value--muted">' + esc(e.note) + '</span>' +
        '</div>';
      }).join('') +
    '</div>';

    var infoCard = '<div class="pur-card">' +
      '<div class="pur-card-header">' +
        '<h2 class="pur-card-title">Power Automate / BC Integration</h2>' +
        '<span class="pur-badge pur-badge--draft">Phase 2</span>' +
      '</div>' +
      '<div class="pur-card-body">' +
        '<p class="pur-api-note">' +
          'WordPress is the local purchasing layer in Phase 1. Business Central integration ' +
          'will be added in Phase 2 via Power Automate flows. ' +
          'Slate Ops will emit signed events to PA; PA will talk to BC. ' +
          'No BC credentials are stored in this application.' +
        '</p>' +
        '<div class="pur-btn-row" style="margin-top:16px">' +
          '<button class="pur-btn pur-btn--primary pur-btn--disabled" disabled>' +
            '<span class="material-symbols-outlined">sync</span>Sync from BC' +
          '</button>' +
          '<button class="pur-btn pur-btn--outline pur-btn--disabled" disabled>' +
            '<span class="material-symbols-outlined">send</span>Push Approved POs to BC' +
          '</button>' +
        '</div>' +
      '</div>' +
    '</div>';

    return '<div class="pur-api-grid">' + statusCard + entitiesCard + '</div>' + infoCard;
  }

  // ─── Master render ────────────────────────────────────────────────────────
  function renderCurrentTab() {
    switch (state.tab) {
      case 'overview':   return renderOverview();
      case 'demand':     return renderDemand();
      case 'requests':   return renderRequests();
      case 'vendors':    return renderVendors();
      case 'open-pos':   return renderOpenPOs();
      case 'api-status': return renderAPIStatus();
      default:           return renderOverview();
    }
  }

  function render() {
    var el = document.getElementById('ops-view');
    if (!el) return;

    // Save focus state before innerHTML replacement destroys the active element.
    var focusedFilter = null, selStart = 0, selEnd = 0;
    var ae = document.activeElement;
    if (ae && ae.getAttribute('data-filter')) {
      focusedFilter = ae.getAttribute('data-filter');
      selStart = ae.selectionStart || 0;
      selEnd   = ae.selectionEnd   || 0;
    }

    setPageTitle('Purchasing');
    setActiveNav();

    el.innerHTML =
      '<div class="pur-page">' +
        renderErrorBanner() +
        renderNoticeBanner() +
        '<div class="pur-page-header">' +
          '<div class="pur-page-eyebrow">Operations</div>' +
          '<h1 class="pur-page-title">Purchasing</h1>' +
          '<p class="pur-page-desc">Manage purchase requests, vendors, and open orders.</p>' +
        '</div>' +
        renderSubnav() +
        '<div class="pur-tab-content">' +
          renderCurrentTab() +
        '</div>' +
      '</div>';

    bindEvents(el);

    // Restore focus and cursor position for active filter inputs.
    if (focusedFilter) {
      var target = el.querySelector('[data-filter="' + focusedFilter + '"]');
      if (target) {
        target.focus();
        if (target.setSelectionRange) {
          try { target.setSelectionRange(selStart, selEnd); } catch (e) {}
        }
      }
    }
  }

  // ─── Event binding ────────────────────────────────────────────────────────
  function bindEvents(el) {
    // Tab navigation
    el.querySelectorAll('.pur-tab').forEach(function (btn) {
      btn.addEventListener('click', function () {
        state.tab = btn.getAttribute('data-tab');
        render();
      });
    });

    // Dismiss banners
    el.querySelectorAll('[data-action="clear-error"]').forEach(function (btn) {
      btn.addEventListener('click', function () { state.error = null; render(); });
    });
    el.querySelectorAll('[data-action="clear-notice"]').forEach(function (btn) {
      btn.addEventListener('click', function () { state.notice = null; render(); });
    });

    // Demand filters — input fires for both <input> and <select> in all modern browsers.
    el.querySelectorAll('[data-filter]').forEach(function (input) {
      var key = input.getAttribute('data-filter');
      input.addEventListener('input', function () {
        state.filters[key] = input.value;
        render();
      });
    });
  }

  // ─── Boot ─────────────────────────────────────────────────────────────────
  function boot() {
    render();
    loadAll();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

})();
