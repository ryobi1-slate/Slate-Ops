/**
 * Slate Ops — Purchasing Workspace
 *
 * Standalone vanilla-JS module. Loaded only on /ops/purchasing.
 * Reads from local WordPress purchasing tables via REST (slate-ops/v1/purchasing/*).
 * No Business Central API calls. No Power Automate in Phase 1.
 *
 * Tabs: Overview · Demand · Purchase Requests · Vendors · Open POs · API Status
 */
(function () {
  'use strict';

  // ─── API config (injected by wp_localize_script as slateOpsPurchasing) ────
  var _cfg      = window.slateOpsPurchasing || {};
  var API_ROOT  = (_cfg.api && _cfg.api.root)  ? _cfg.api.root  : null;
  var API_NONCE = (_cfg.api && _cfg.api.nonce) ? _cfg.api.nonce : '';

  // ─── State ────────────────────────────────────────────────────────────────
  var state = {
    tab:   'overview',
    cache: {},   // keyed by tab ID; undefined = not yet fetched, null = failed
    error: null, // last fetch error message
  };

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

  // Tab → endpoint mapping; null means no fetch needed.
  var TAB_ENDPOINTS = {
    'overview':   'overview',
    'demand':     'items',
    'requests':   'requests',
    'vendors':    'vendors',
    'open-pos':   'orders',
    'api-status': null,
  };

  function loadTab(tabId) {
    var endpoint = TAB_ENDPOINTS[tabId];
    if (!endpoint) return;
    if (state.cache[tabId] !== undefined) return; // already cached (even if [])

    state.error = null;

    apiFetch(endpoint).then(function (data) {
      state.cache[tabId] = data;
      render();
    }).catch(function (err) {
      state.cache[tabId] = null; // mark as failed so re-render shows error
      state.error = err.message || 'Failed to load data';
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
    var d = new Date(dt.replace(' ', 'T'));
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
      draft:        'Draft',
      pending:      'Pending Approval',
      approved:     'Approved',
      rejected:     'Rejected',
      submitted:    'Submitted',
      acknowledged: 'Acknowledged',
      shipped:      'Shipped',
      partial:      'Partial Receipt',
      received:     'Received',
      closed:       'Closed',
      active:       'Active',
      inactive:     'Inactive',
      disconnected: 'Disconnected',
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

  // ─── Shared micro-states ──────────────────────────────────────────────────
  function renderLoading() {
    return '<div class="pur-empty">' +
      '<span class="material-symbols-outlined">hourglass_empty</span>' +
      '<div class="pur-empty-title">Loading…</div>' +
    '</div>';
  }

  function renderFetchError() {
    return '<div class="pur-empty">' +
      '<span class="material-symbols-outlined">error_outline</span>' +
      '<div class="pur-empty-title">Failed to load</div>' +
      '<div>' + esc(state.error || 'Unknown error') + '</div>' +
    '</div>';
  }

  function renderEmpty(icon, title, sub) {
    return '<div class="pur-empty">' +
      '<span class="material-symbols-outlined">' + esc(icon) + '</span>' +
      '<div class="pur-empty-title">' + esc(title) + '</div>' +
      (sub ? '<div>' + esc(sub) + '</div>' : '') +
    '</div>';
  }

  // tabData(tabId) → data array / object, or a sentinel string 'loading' / 'error'
  function tabData(tabId) {
    var cached = state.cache[tabId];
    if (cached === undefined) return 'loading'; // fetch not yet complete
    if (cached === null)      return 'error';   // fetch failed
    return cached;
  }

  // ─── Preview banner ───────────────────────────────────────────────────────
  function renderBanner() {
    return '<div class="pur-preview-banner">' +
      '<span class="material-symbols-outlined">inventory</span>' +
      '<span><strong>Phase 1 — Local data only.</strong> ' +
      'Business Central integration is not yet active. ' +
      'Purchase requests and vendor records are managed locally in this phase.</span>' +
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
    var d = tabData('overview');
    if (d === 'loading') return renderLoading();
    if (d === 'error')   return renderFetchError();

    var kpis = [
      { label: 'Open Purchase Requests', value: d.open_requests,       sub: d.pending_approval + ' pending approval',               mod: d.pending_approval > 0 ? ' pur-kpi--alert' : '' },
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

    var activity     = d.recent_activity  || [];
    var activityBody = !activity.length
      ? renderEmpty('history', 'No recent activity', 'Activity will appear here as purchase requests are created.')
      : '<ul class="pur-activity-list">' +
          activity.map(function (a) {
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

    var poRows   = d.open_po_summary || [];
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

    var summaryHtml = '<div class="pur-card">' +
      '<div class="pur-card-header"><h2 class="pur-card-title">Open PO Summary</h2></div>' +
      summaryBody +
    '</div>';

    return kpiHtml + activityHtml + summaryHtml;
  }

  // ─── Demand tab ───────────────────────────────────────────────────────────
  function renderDemand() {
    var items = tabData('demand');
    if (items === 'loading') return renderLoading();
    if (items === 'error')   return renderFetchError();

    if (!items.length) {
      return '<div class="pur-card">' +
        '<div class="pur-card-header"><h2 class="pur-card-title">Demand Forecast</h2></div>' +
        renderEmpty('inventory_2', 'No items tracked', 'Add items to the inventory catalog to see demand data here.') +
      '</div>';
    }

    var belowReorder = items.filter(function (d) {
      return parseInt(d.reorder_point, 10) > 0 && parseInt(d.on_hand, 10) <= parseInt(d.reorder_point, 10);
    }).length;

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
        '<td class="pur-col-num' + (needsReorder ? ' pur-col-alert' : '') + '">' + onHand + '</td>' +
        '<td class="pur-col-num pur-col-muted">' + reorder + '</td>' +
        '<td class="pur-col-num">' +
          '<div class="pur-demand-bar-wrap"><div class="pur-demand-bar ' + barClass + '" style="width:' + pct + '%"></div></div>' +
        '</td>' +
        '<td class="pur-col-num">' + esc(d.forecasted_need || 0) + '</td>' +
        '<td class="pur-col-num">' + esc(d.suggested_order || 0) + '</td>' +
      '</tr>';
    }).join('');

    return '<div class="pur-card">' +
      '<div class="pur-card-header">' +
        '<h2 class="pur-card-title">Demand Forecast</h2>' +
        '<span class="pur-card-meta">30-day window · ' + belowReorder + ' part' + (belowReorder !== 1 ? 's' : '') + ' below reorder point</span>' +
      '</div>' +
      '<div class="pur-table-wrap"><table class="pur-table">' +
        '<thead><tr>' +
          '<th>Part #</th>' +
          '<th>Description</th>' +
          '<th style="text-align:right">On Hand</th>' +
          '<th style="text-align:right">Reorder Pt.</th>' +
          '<th style="text-align:right">Stock Level</th>' +
          '<th style="text-align:right">Forecasted Need</th>' +
          '<th style="text-align:right">Suggested Order</th>' +
        '</tr></thead>' +
        '<tbody>' + rows + '</tbody>' +
      '</table></div>' +
    '</div>';
  }

  // ─── Purchase Requests tab ────────────────────────────────────────────────
  function renderRequests() {
    var requests = tabData('requests');
    if (requests === 'loading') return renderLoading();
    if (requests === 'error')   return renderFetchError();

    var body;
    if (!requests.length) {
      body = renderEmpty('request_quote', 'No purchase requests', 'Create a purchase request to get started.');
    } else {
      var rows = requests.map(function (r) {
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
          '<td>' +
            '<span class="pur-btn pur-btn--outline pur-btn--disabled" style="font-size:12px;padding:4px 10px;">' +
              (r.status === 'pending' ? 'Review' : 'View') +
            '</span>' +
          '</td>' +
        '</tr>';
      }).join('');
      body = '<div class="pur-table-wrap"><table class="pur-table">' +
        '<thead><tr>' +
          '<th>PR #</th><th>Item</th><th>Vendor</th>' +
          '<th style="text-align:right">Qty</th>' +
          '<th style="text-align:right">Unit Cost</th>' +
          '<th style="text-align:right">Total</th>' +
          '<th>Status</th><th>Date</th><th>Requested By</th><th></th>' +
        '</tr></thead>' +
        '<tbody>' + rows + '</tbody>' +
      '</table></div>';
    }

    return '<div class="pur-card">' +
      '<div class="pur-card-header">' +
        '<h2 class="pur-card-title">Purchase Requests</h2>' +
        '<div class="pur-btn-row">' +
          '<button class="pur-btn pur-btn--primary pur-btn--disabled" disabled>' +
            '<span class="material-symbols-outlined">add</span>New Request' +
          '</button>' +
        '</div>' +
      '</div>' +
      body +
    '</div>';
  }

  // ─── Vendors tab ──────────────────────────────────────────────────────────
  function renderVendors() {
    var vendors = tabData('vendors');
    if (vendors === 'loading') return renderLoading();
    if (vendors === 'error')   return renderFetchError();

    var body;
    if (!vendors.length) {
      body = renderEmpty('storefront', 'No vendors', 'Vendors will appear here once added.');
    } else {
      var rows = vendors.map(function (v) {
        return '<tr>' +
          '<td class="pur-col-mono">' + esc(v.id) + '</td>' +
          '<td style="font-weight:500">' + esc(v.name) + '</td>' +
          '<td class="pur-col-muted">' + esc(v.contact_email || v.contact_phone || '—') + '</td>' +
          '<td class="pur-col-muted">' + esc(fmtLeadTime(v.lead_time_days)) + '</td>' +
          '<td class="pur-col-muted">' + esc(v.payment_terms || '—') + '</td>' +
          '<td>' + badge(v.status) + '</td>' +
          '<td><span class="pur-btn pur-btn--outline pur-btn--disabled" style="font-size:12px;padding:4px 10px;">View</span></td>' +
        '</tr>';
      }).join('');
      body = '<div class="pur-table-wrap"><table class="pur-table">' +
        '<thead><tr>' +
          '<th>ID</th><th>Name</th><th>Contact</th><th>Lead Time</th><th>Terms</th><th>Status</th><th></th>' +
        '</tr></thead>' +
        '<tbody>' + rows + '</tbody>' +
      '</table></div>';
    }

    return '<div class="pur-card">' +
      '<div class="pur-card-header">' +
        '<h2 class="pur-card-title">Vendors</h2>' +
        '<div class="pur-btn-row">' +
          '<button class="pur-btn pur-btn--primary pur-btn--disabled" disabled>' +
            '<span class="material-symbols-outlined">add</span>Add Vendor' +
          '</button>' +
        '</div>' +
      '</div>' +
      body +
    '</div>';
  }

  // ─── Open POs tab ─────────────────────────────────────────────────────────
  function renderOpenPOs() {
    var orders = tabData('open-pos');
    if (orders === 'loading') return renderLoading();
    if (orders === 'error')   return renderFetchError();

    var body;
    if (!orders.length) {
      body = renderEmpty('receipt_long', 'No open purchase orders', 'Purchase orders will appear here once created.');
    } else {
      var totalValue = orders.reduce(function (s, p) { return s + parseFloat(p.total_value || 0); }, 0);
      var rows = orders.map(function (p) {
        return '<tr>' +
          '<td class="pur-col-mono">' + esc(p.po_number) + '</td>' +
          '<td style="font-weight:500">' + esc(p.vendor_name || '—') + '</td>' +
          '<td class="pur-col-num pur-col-muted">' + esc(p.line_count || 0) + '</td>' +
          '<td class="pur-col-num">' + fmt$(p.total_value || 0) + '</td>' +
          '<td class="pur-col-muted">' + esc(fmtDate(p.ordered_at)) + '</td>' +
          '<td class="pur-col-muted">' + esc(fmtDate(p.expected_date)) + '</td>' +
          '<td>' + badge(p.status) + '</td>' +
          '<td><span class="pur-btn pur-btn--outline pur-btn--disabled" style="font-size:12px;padding:4px 10px;">View</span></td>' +
        '</tr>';
      }).join('');
      body = '<div class="pur-table-wrap"><table class="pur-table">' +
        '<thead><tr>' +
          '<th>PO #</th><th>Vendor</th>' +
          '<th style="text-align:right">Lines</th>' +
          '<th style="text-align:right">Total</th>' +
          '<th>Ordered</th><th>Expected</th><th>Status</th><th></th>' +
        '</tr></thead>' +
        '<tbody>' + rows + '</tbody>' +
      '</table></div>';
    }

    var meta = orders.length
      ? orders.length + ' open · ' + fmt$(orders.reduce(function (s, p) { return s + parseFloat(p.total_value || 0); }, 0)) + ' total value'
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
    var connCard = '<div class="pur-api-card">' +
      '<div class="pur-api-card-title">Business Central Connection</div>' +
      '<div class="pur-api-row"><span class="pur-api-row-label">Status</span><span>' + badge('disconnected') + '</span></div>' +
      '<div class="pur-api-row"><span class="pur-api-row-label">Environment</span><span class="pur-api-row-value">SANDBOX093024</span></div>' +
      '<div class="pur-api-row"><span class="pur-api-row-label">Last Sync</span><span class="pur-api-row-value pur-api-row-value--muted">Never</span></div>' +
      '<div class="pur-api-row"><span class="pur-api-row-label">Credential Status</span><span class="pur-api-row-value pur-api-row-value--muted">Not configured</span></div>' +
    '</div>';

    var entitiesCard = '<div class="pur-api-card">' +
      '<div class="pur-api-card-title">Available BC Entities (Phase 2)</div>' +
      [
        { entity: 'vendors',            note: 'Vendor master data'         },
        { entity: 'items',              note: 'Parts catalog'               },
        { entity: 'purchaseOrders',     note: 'Purchase order headers'      },
        { entity: 'purchaseOrderLines', note: 'Purchase order line items'   },
      ].map(function (e) {
        return '<div class="pur-api-row">' +
          '<span class="pur-api-row-label" style="font-family:var(--font-mono);font-size:12px;">' + esc(e.entity) + '</span>' +
          '<span class="pur-api-row-value pur-api-row-value--muted">' + esc(e.note) + '</span>' +
        '</div>';
      }).join('') +
    '</div>';

    var triggerCard = '<div class="pur-card">' +
      '<div class="pur-card-header">' +
        '<h2 class="pur-card-title">Power Automate / BC Integration</h2>' +
        '<span class="pur-badge pur-badge--draft">Not connected</span>' +
      '</div>' +
      '<div class="pur-card-body">' +
        '<p class="pur-api-note">' +
          'Phase 2 will connect this workspace to Business Central via Power Automate. ' +
          'Once configured, vendor and item data can be synced from BC and approved ' +
          'purchase orders can be pushed back. The local data model is already shaped ' +
          'for BC entity compatibility (vendors, items, purchaseOrders, purchaseOrderLines).' +
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

    return '<div class="pur-api-grid">' + connCard + entitiesCard + '</div>' + triggerCard;
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

    setPageTitle('Purchasing');
    setActiveNav();

    el.innerHTML =
      '<div class="pur-page">' +
        renderBanner() +
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

    el.querySelectorAll('.pur-tab').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var tabId = btn.getAttribute('data-tab');
        state.tab = tabId;
        render();
        loadTab(tabId);
      });
    });
  }

  // ─── Boot ─────────────────────────────────────────────────────────────────
  function boot() {
    render();        // initial paint (shows loading spinner for overview)
    loadTab('overview');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

})();
