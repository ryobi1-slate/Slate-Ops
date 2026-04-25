/**
 * Slate Ops — Purchasing Workspace
 *
 * Standalone vanilla-JS module. Loaded only on /ops/purchasing.
 * Owns #ops-view entirely. No React. No Business Central API.
 * All data is mock — no REST calls, no writeback, no PO creation.
 *
 * Tabs: Overview · Demand · Purchase Requests · Vendors · Open POs · API Status
 */
(function () {
  'use strict';

  // ─── Escape helper ────────────────────────────────────────────────────────
  function esc(val) {
    return String(val ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // ─── State ────────────────────────────────────────────────────────────────
  var state = { tab: 'overview' };

  // ─── Mock data ────────────────────────────────────────────────────────────

  var DEMAND = [
    { part: 'B-1042', desc: 'Brake Pad Set (Front)',  onHand: 8,  reorder: 10, forecast: 24, suggest: 20, level: 'high' },
    { part: 'L-0318', desc: 'Oil Filter',              onHand: 14, reorder: 20, forecast: 60, suggest: 50, level: 'high' },
    { part: 'T-2201', desc: 'Tire (P225/60R18)',       onHand: 2,  reorder: 4,  forecast: 12, suggest: 10, level: 'high' },
    { part: 'F-0071', desc: 'Air Filter',              onHand: 6,  reorder: 8,  forecast: 18, suggest: 15, level: 'medium' },
    { part: 'S-0885', desc: 'Spark Plug Set',          onHand: 3,  reorder: 6,  forecast: 20, suggest: 20, level: 'high' },
    { part: 'W-1124', desc: 'Wiper Blade',             onHand: 12, reorder: 15, forecast: 30, suggest: 20, level: 'medium' },
    { part: 'C-4410', desc: 'Cabin Air Filter',        onHand: 4,  reorder: 5,  forecast: 16, suggest: 15, level: 'medium' },
    { part: 'B-0093', desc: 'Battery (Group 35)',      onHand: 1,  reorder: 3,  forecast: 8,  suggest: 10, level: 'high' },
  ];

  var REQUESTS = [
    { id: 'PR-0042', item: 'Brake Pad Set (Front)',   vendor: 'SlateAuto Supply', qty: 20, unit: 28.50,  status: 'pending',  date: 'Apr 22', by: 'J. Martinez' },
    { id: 'PR-0041', item: 'Battery (Group 35)',      vendor: 'Parts Direct',     qty: 10, unit: 94.00,  status: 'approved', date: 'Apr 21', by: 'K. Thompson' },
    { id: 'PR-0040', item: 'Tire (P225/60R18)',       vendor: 'TireHub',          qty: 10, unit: 145.00, status: 'draft',    date: 'Apr 20', by: 'J. Martinez' },
    { id: 'PR-0039', item: 'Oil Filter (50-pack)',    vendor: 'SlateAuto Supply', qty: 50, unit: 4.25,   status: 'approved', date: 'Apr 18', by: 'K. Thompson' },
    { id: 'PR-0038', item: 'Spark Plug Set',          vendor: 'AutoParts Plus',   qty: 20, unit: 22.00,  status: 'rejected', date: 'Apr 17', by: 'J. Martinez' },
  ];

  var VENDORS = [
    { id: 'V-001', name: 'SlateAuto Supply',       contact: 'orders@slateauto.example',      lead: '2 days',  terms: 'Net 30', status: 'active' },
    { id: 'V-002', name: 'Parts Direct',            contact: 'supply@partsdirect.example',    lead: '3 days',  terms: 'Net 15', status: 'active' },
    { id: 'V-003', name: 'TireHub',                 contact: 'wholesale@tirehub.example',     lead: '5 days',  terms: 'Net 30', status: 'active' },
    { id: 'V-004', name: 'AutoParts Plus',          contact: 'accounts@autopartsplus.example',lead: '4 days',  terms: 'Net 30', status: 'active' },
    { id: 'V-005', name: 'MotorPro Distribution',  contact: 'orders@motorpro.example',       lead: '7 days',  terms: 'Net 45', status: 'inactive' },
  ];

  var OPEN_POS = [
    { po: 'PO-0018', vendor: 'Parts Direct',     items: 3, total: 1240.00, ordered: 'Apr 21', expected: 'Apr 24', status: 'acknowledged' },
    { po: 'PO-0017', vendor: 'SlateAuto Supply', items: 5, total:  892.50, ordered: 'Apr 20', expected: 'Apr 22', status: 'shipped' },
    { po: 'PO-0016', vendor: 'TireHub',          items: 2, total: 1450.00, ordered: 'Apr 19', expected: 'Apr 26', status: 'submitted' },
    { po: 'PO-0015', vendor: 'AutoParts Plus',   items: 4, total:  580.00, ordered: 'Apr 16', expected: 'Apr 23', status: 'partial' },
  ];

  var ACTIVITY = [
    { icon: 'edit_note',         text: 'PR-0042 submitted for approval — Brake Pad Set (Front) × 20',     time: 'Apr 22 · 2:41 PM', color: '' },
    { icon: 'check_circle',      text: 'PR-0041 approved — Battery (Group 35) × 10',                       time: 'Apr 21 · 10:14 AM', color: '' },
    { icon: 'local_shipping',    text: 'PO-0017 marked Shipped by SlateAuto Supply',                        time: 'Apr 20 · 4:05 PM', color: '' },
    { icon: 'inventory_2',       text: 'PO-0015 partial receipt recorded — 2 of 4 lines received',          time: 'Apr 19 · 9:30 AM', color: '' },
    { icon: 'cancel',            text: 'PR-0038 rejected — duplicate request for Spark Plug Set',           time: 'Apr 17 · 3:58 PM', color: '' },
  ];

  // ─── Utility ──────────────────────────────────────────────────────────────

  function fmt$(n) {
    return '$' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
      var r = a.getAttribute('data-route');
      a.classList.toggle('active', r === '/purchasing');
    });
  }

  // ─── Preview banner ───────────────────────────────────────────────────────

  function renderBanner() {
    return '<div class="pur-preview-banner">' +
      '<span class="material-symbols-outlined">science</span>' +
      '<span><strong>Preview / Manual Trigger Mode</strong> — No Business Central connection. ' +
      'Data is mock only. Purchase requests are manual; no automatic PO creation.</span>' +
      '</div>';
  }

  // ─── Sub-nav ──────────────────────────────────────────────────────────────

  var TABS = [
    { id: 'overview',   icon: 'dashboard',      label: 'Overview' },
    { id: 'demand',     icon: 'trending_up',    label: 'Demand' },
    { id: 'requests',   icon: 'request_quote',  label: 'Purchase Requests' },
    { id: 'vendors',    icon: 'storefront',     label: 'Vendors' },
    { id: 'open-pos',   icon: 'receipt_long',   label: 'Open POs' },
    { id: 'api-status', icon: 'cloud_off',      label: 'API Status' },
  ];

  function renderSubnav() {
    return '<nav class="pur-subnav">' +
      TABS.map(function (t) {
        var active = t.id === state.tab ? ' active' : '';
        return '<button class="pur-tab' + active + '" data-tab="' + esc(t.id) + '">' +
          '<span class="material-symbols-outlined">' + esc(t.icon) + '</span>' +
          esc(t.label) +
          '</button>';
      }).join('') +
      '</nav>';
  }

  // ─── Overview tab ─────────────────────────────────────────────────────────

  function renderOverview() {
    var belowReorder = DEMAND.filter(function (d) { return d.onHand <= d.reorder; }).length;
    var openPRs      = REQUESTS.filter(function (r) { return r.status === 'pending'; }).length;
    var totalOnOrder = OPEN_POS.reduce(function (s, p) { return s + p.items; }, 0);
    var activeVendors = VENDORS.filter(function (v) { return v.status === 'active'; }).length;

    var kpis = [
      { label: 'Open Purchase Requests', value: REQUESTS.length, sub: openPRs + ' pending approval',                   mod: openPRs > 0 ? ' pur-kpi--alert' : '' },
      { label: 'Items Below Reorder',    value: belowReorder,    sub: 'of ' + DEMAND.length + ' tracked parts',         mod: belowReorder > 0 ? ' pur-kpi--alert' : '' },
      { label: 'Items on Order',         value: totalOnOrder,    sub: OPEN_POS.length + ' open purchase orders',         mod: '' },
      { label: 'Active Vendors',         value: activeVendors,   sub: VENDORS.length + ' vendors total',                 mod: '' },
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

    var activityHtml = '<div class="pur-card">' +
      '<div class="pur-card-header">' +
        '<h2 class="pur-card-title">Recent Activity</h2>' +
        '<span class="pur-card-meta">Mock data</span>' +
      '</div>' +
      '<div class="pur-card-body">' +
        '<ul class="pur-activity-list">' +
          ACTIVITY.map(function (a) {
            return '<li class="pur-activity-item">' +
              '<div class="pur-activity-icon"><span class="material-symbols-outlined">' + esc(a.icon) + '</span></div>' +
              '<div class="pur-activity-body">' +
                '<div class="pur-activity-text">' + esc(a.text) + '</div>' +
                '<div class="pur-activity-time">' + esc(a.time) + '</div>' +
              '</div>' +
              '</li>';
          }).join('') +
        '</ul>' +
      '</div>' +
      '</div>';

    var summaryHtml = '<div class="pur-card">' +
      '<div class="pur-card-header"><h2 class="pur-card-title">Open PO Summary</h2></div>' +
      '<div class="pur-table-wrap">' +
        '<table class="pur-table">' +
          '<thead><tr>' +
            '<th>PO #</th><th>Vendor</th><th>Lines</th><th>Status</th><th>Expected</th>' +
          '</tr></thead>' +
          '<tbody>' +
          OPEN_POS.map(function (p) {
            return '<tr>' +
              '<td class="pur-col-mono">' + esc(p.po) + '</td>' +
              '<td>' + esc(p.vendor) + '</td>' +
              '<td class="pur-col-num">' + p.items + '</td>' +
              '<td>' + badge(p.status) + '</td>' +
              '<td class="pur-col-muted">' + esc(p.expected) + '</td>' +
            '</tr>';
          }).join('') +
          '</tbody>' +
        '</table>' +
      '</div>' +
      '</div>';

    return kpiHtml + activityHtml + summaryHtml;
  }

  // ─── Demand tab ───────────────────────────────────────────────────────────

  function renderDemand() {
    var rows = DEMAND.map(function (d) {
      var needsReorder = d.onHand <= d.reorder;
      var pct = Math.min(100, Math.round((d.onHand / Math.max(d.reorder, 1)) * 100));
      var barClass = d.level === 'high' ? 'pur-demand-bar--high'
                   : d.level === 'medium' ? 'pur-demand-bar--medium'
                   : 'pur-demand-bar--low';
      return '<tr' + (needsReorder ? ' class="pur-row-alert"' : '') + '>' +
        '<td class="pur-col-mono">' + esc(d.part) + '</td>' +
        '<td>' + esc(d.desc) + '</td>' +
        '<td class="pur-col-num' + (needsReorder ? ' pur-col-alert' : '') + '">' + d.onHand + '</td>' +
        '<td class="pur-col-num pur-col-muted">' + d.reorder + '</td>' +
        '<td class="pur-col-num">' +
          '<div class="pur-demand-bar-wrap"><div class="pur-demand-bar ' + barClass + '" style="width:' + pct + '%"></div></div>' +
        '</td>' +
        '<td class="pur-col-num">' + d.forecast + '</td>' +
        '<td class="pur-col-num">' + d.suggest + '</td>' +
      '</tr>';
    }).join('');

    return '<div class="pur-card">' +
      '<div class="pur-card-header">' +
        '<h2 class="pur-card-title">Demand Forecast</h2>' +
        '<span class="pur-card-meta">Mock data · 30-day window · ' +
          DEMAND.filter(function (d) { return d.onHand <= d.reorder; }).length +
          ' parts below reorder point</span>' +
      '</div>' +
      '<div class="pur-table-wrap">' +
        '<table class="pur-table">' +
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
        '</table>' +
      '</div>' +
      '</div>';
  }

  // ─── Purchase Requests tab ────────────────────────────────────────────────

  function renderRequests() {
    var rows = REQUESTS.map(function (r) {
      var total = r.qty * r.unit;
      return '<tr>' +
        '<td class="pur-col-mono">' + esc(r.id) + '</td>' +
        '<td>' + esc(r.item) + '</td>' +
        '<td class="pur-col-muted">' + esc(r.vendor) + '</td>' +
        '<td class="pur-col-num">' + r.qty + '</td>' +
        '<td class="pur-col-num pur-col-muted">' + fmt$(r.unit) + '</td>' +
        '<td class="pur-col-num">' + fmt$(total) + '</td>' +
        '<td>' + badge(r.status) + '</td>' +
        '<td class="pur-col-muted">' + esc(r.date) + '</td>' +
        '<td class="pur-col-muted">' + esc(r.by) + '</td>' +
        '<td>' +
          '<span class="pur-btn pur-btn--outline pur-btn--disabled" style="font-size:12px;padding:4px 10px;">' +
            (r.status === 'pending' ? 'Review' : 'View') +
          '</span>' +
        '</td>' +
      '</tr>';
    }).join('');

    return '<div class="pur-card">' +
      '<div class="pur-card-header">' +
        '<h2 class="pur-card-title">Purchase Requests</h2>' +
        '<div class="pur-btn-row">' +
          '<span class="pur-badge pur-badge--preview">Preview Mode</span>' +
          '<button class="pur-btn pur-btn--primary pur-btn--disabled" disabled>' +
            '<span class="material-symbols-outlined">add</span>New Request' +
          '</button>' +
        '</div>' +
      '</div>' +
      '<div class="pur-table-wrap">' +
        '<table class="pur-table">' +
          '<thead><tr>' +
            '<th>PR #</th>' +
            '<th>Item</th>' +
            '<th>Vendor</th>' +
            '<th style="text-align:right">Qty</th>' +
            '<th style="text-align:right">Unit Cost</th>' +
            '<th style="text-align:right">Total</th>' +
            '<th>Status</th>' +
            '<th>Date</th>' +
            '<th>Requested By</th>' +
            '<th></th>' +
          '</tr></thead>' +
          '<tbody>' + rows + '</tbody>' +
        '</table>' +
      '</div>' +
      '</div>';
  }

  // ─── Vendors tab ──────────────────────────────────────────────────────────

  function renderVendors() {
    var rows = VENDORS.map(function (v) {
      return '<tr>' +
        '<td class="pur-col-mono">' + esc(v.id) + '</td>' +
        '<td style="font-weight:500">' + esc(v.name) + '</td>' +
        '<td class="pur-col-muted">' + esc(v.contact) + '</td>' +
        '<td class="pur-col-muted">' + esc(v.lead) + '</td>' +
        '<td class="pur-col-muted">' + esc(v.terms) + '</td>' +
        '<td>' + badge(v.status) + '</td>' +
        '<td>' +
          '<span class="pur-btn pur-btn--outline pur-btn--disabled" style="font-size:12px;padding:4px 10px;">View</span>' +
        '</td>' +
      '</tr>';
    }).join('');

    return '<div class="pur-card">' +
      '<div class="pur-card-header">' +
        '<h2 class="pur-card-title">Vendors</h2>' +
        '<div class="pur-btn-row">' +
          '<span class="pur-badge pur-badge--preview">Preview Mode</span>' +
          '<button class="pur-btn pur-btn--primary pur-btn--disabled" disabled>' +
            '<span class="material-symbols-outlined">add</span>Add Vendor' +
          '</button>' +
        '</div>' +
      '</div>' +
      '<div class="pur-table-wrap">' +
        '<table class="pur-table">' +
          '<thead><tr>' +
            '<th>Vendor ID</th>' +
            '<th>Name</th>' +
            '<th>Contact</th>' +
            '<th>Lead Time</th>' +
            '<th>Terms</th>' +
            '<th>Status</th>' +
            '<th></th>' +
          '</tr></thead>' +
          '<tbody>' + rows + '</tbody>' +
        '</table>' +
      '</div>' +
      '</div>';
  }

  // ─── Open POs tab ─────────────────────────────────────────────────────────

  function renderOpenPOs() {
    var rows = OPEN_POS.map(function (p) {
      return '<tr>' +
        '<td class="pur-col-mono">' + esc(p.po) + '</td>' +
        '<td style="font-weight:500">' + esc(p.vendor) + '</td>' +
        '<td class="pur-col-num pur-col-muted">' + p.items + '</td>' +
        '<td class="pur-col-num">' + fmt$(p.total) + '</td>' +
        '<td class="pur-col-muted">' + esc(p.ordered) + '</td>' +
        '<td class="pur-col-muted">' + esc(p.expected) + '</td>' +
        '<td>' + badge(p.status) + '</td>' +
        '<td>' +
          '<span class="pur-btn pur-btn--outline pur-btn--disabled" style="font-size:12px;padding:4px 10px;">View</span>' +
        '</td>' +
      '</tr>';
    }).join('');

    var totalValue = OPEN_POS.reduce(function (s, p) { return s + p.total; }, 0);

    return '<div class="pur-card">' +
      '<div class="pur-card-header">' +
        '<h2 class="pur-card-title">Open Purchase Orders</h2>' +
        '<span class="pur-card-meta">' + OPEN_POS.length + ' open · ' + fmt$(totalValue) + ' total value</span>' +
      '</div>' +
      '<div class="pur-table-wrap">' +
        '<table class="pur-table">' +
          '<thead><tr>' +
            '<th>PO #</th>' +
            '<th>Vendor</th>' +
            '<th style="text-align:right">Lines</th>' +
            '<th style="text-align:right">Total</th>' +
            '<th>Ordered</th>' +
            '<th>Expected</th>' +
            '<th>Status</th>' +
            '<th></th>' +
          '</tr></thead>' +
          '<tbody>' + rows + '</tbody>' +
        '</table>' +
      '</div>' +
      '</div>';
  }

  // ─── API Status tab ───────────────────────────────────────────────────────

  function renderAPIStatus() {
    var connCard = '<div class="pur-api-card">' +
      '<div class="pur-api-card-title">Business Central Connection</div>' +
      '<div class="pur-api-row">' +
        '<span class="pur-api-row-label">Status</span>' +
        '<span>' + badge('disconnected') + '</span>' +
      '</div>' +
      '<div class="pur-api-row">' +
        '<span class="pur-api-row-label">Environment</span>' +
        '<span class="pur-api-row-value">' + badge('preview') + '</span>' +
      '</div>' +
      '<div class="pur-api-row">' +
        '<span class="pur-api-row-label">Last Sync</span>' +
        '<span class="pur-api-row-value pur-api-row-value--muted">Never</span>' +
      '</div>' +
      '<div class="pur-api-row">' +
        '<span class="pur-api-row-label">Credential Status</span>' +
        '<span class="pur-api-row-value pur-api-row-value--muted">No credentials configured</span>' +
      '</div>' +
    '</div>';

    var configCard = '<div class="pur-api-card">' +
      '<div class="pur-api-card-title">Configuration</div>' +
      '<div class="pur-field">' +
        '<label class="pur-label">BC Tenant ID</label>' +
        '<input class="pur-input" type="text" placeholder="Not configured" disabled>' +
      '</div>' +
      '<div class="pur-field">' +
        '<label class="pur-label">BC Environment Name</label>' +
        '<input class="pur-input" type="text" placeholder="Not configured" disabled>' +
      '</div>' +
      '<div class="pur-field">' +
        '<label class="pur-label">BC Company ID</label>' +
        '<input class="pur-input" type="text" placeholder="Not configured" disabled>' +
      '</div>' +
    '</div>';

    var triggerCard = '<div class="pur-card">' +
      '<div class="pur-card-header">' +
        '<h2 class="pur-card-title">Manual Trigger</h2>' +
        '<span class="pur-badge pur-badge--preview">Preview Mode</span>' +
      '</div>' +
      '<div class="pur-card-body">' +
        '<p class="pur-api-note">' +
          'Manual triggers are disabled in Preview Mode. Once a Business Central connection is ' +
          'configured and credentials are set, you will be able to manually trigger a demand sync, ' +
          'generate draft purchase requests, and push approved POs to BC from this panel.' +
        '</p>' +
        '<div class="pur-btn-row" style="margin-top:16px">' +
          '<button class="pur-btn pur-btn--primary pur-btn--disabled" disabled>' +
            '<span class="material-symbols-outlined">sync</span>Sync Demand from BC' +
          '</button>' +
          '<button class="pur-btn pur-btn--outline pur-btn--disabled" disabled>' +
            '<span class="material-symbols-outlined">send</span>Push Approved POs to BC' +
          '</button>' +
        '</div>' +
      '</div>' +
    '</div>';

    return '<div class="pur-api-grid">' + connCard + configCard + '</div>' + triggerCard;
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
          '<p class="pur-page-desc">Manage purchase requests, vendors, and open orders. ' +
            'Business Central integration is not yet active.</p>' +
        '</div>' +
        renderSubnav() +
        '<div class="pur-tab-content">' +
          renderCurrentTab() +
        '</div>' +
      '</div>';

    // Bind tab clicks after render
    el.querySelectorAll('.pur-tab').forEach(function (btn) {
      btn.addEventListener('click', function () {
        state.tab = btn.getAttribute('data-tab');
        render();
      });
    });
  }

  // ─── Boot ─────────────────────────────────────────────────────────────────

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', render);
  } else {
    render();
  }

})();
