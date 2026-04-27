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
  var IS_ADMIN  = (_cfg.user && _cfg.user.is_admin) ? true : false;

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
    activeVendorId:       null,
    activePoId:           null,
    poLines:              {},
    poLinesLoading:       false,
    draftNotes:           null,   // notes textarea value while drawer is open
    integration:          null,
    integrationSending:   false,
    integrationSyncing:   null,   // feed name being synced, or null
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

  function apiPost(path, data) {
    if (!API_ROOT) return Promise.reject(new Error('API not configured'));
    return fetch(API_ROOT + '/purchasing/' + path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-WP-Nonce': API_NONCE, 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
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

  function apiPatch(path, data) {
    if (!API_ROOT) return Promise.reject(new Error('API not configured'));
    return fetch(API_ROOT + '/purchasing/' + path, {
      method: 'PATCH',
      credentials: 'same-origin',
      headers: { 'X-WP-Nonce': API_NONCE, 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    }).then(function (res) {
      if (!res.ok) {
        return res.json().then(function (body) {
          throw new Error(body.message || 'Request failed (' + res.status + ')');
        }, function () {
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
      apiFetch('integration/status'),
    ]).then(function (results) {
      var errors = [];
      function val(r, fallback) {
        if (r.status === 'fulfilled') return r.value;
        errors.push(r.reason && r.reason.message ? r.reason.message : 'A request failed');
        return fallback;
      }
      state.summary     = val(results[0], null);
      state.demand      = val(results[1], []);
      state.requests    = val(results[2], []);
      state.vendors     = val(results[3], []);
      state.openPos     = val(results[4], []);
      state.integration = val(results[5], null);
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

  function vendorFlags(v) {
    var flags = [];
    if (!v.contact_email)                flags.push('Missing email');
    if (!v.contact_phone)                flags.push('Missing phone');
    if (!parseInt(v.lead_time_days, 10)) flags.push('Missing lead time');
    if (!v.payment_terms)                flags.push('Missing payment terms');
    if (!v.freight_terms)                flags.push('Missing freight terms');
    if (v.min_order_amount == null)      flags.push('No minimum order set');
    if (v.status !== 'active')           flags.push('Vendor is ' + (v.status || 'unknown'));
    return flags;
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
      hold:         'On Hold',
      missing_info: 'Missing Info',
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

  // ─── Status transition map (mirrors SOT) ─────────────────────────────────
  var PR_TRANSITIONS = {
    draft:     ['review', 'held', 'cancelled'],
    review:    ['approved', 'held', 'cancelled'],
    approved:  ['ordered', 'held', 'cancelled'],
    held:      ['review', 'cancelled'],
    ordered:   [],
    cancelled: [],
  };

  var STATUS_ACTION_META = {
    review:    { label: 'Submit for Review', cls: 'pur-btn--outline'  },
    approved:  { label: 'Approve',           cls: 'pur-btn--primary'  },
    held:      { label: 'Place on Hold',     cls: 'pur-btn--warn'     },
    ordered:   { label: 'Mark as Ordered',   cls: 'pur-btn--primary'  },
    cancelled: { label: 'Cancel Request',    cls: 'pur-btn--danger'   },
  };

  // ─── PR detail drawer ─────────────────────────────────────────────────────
  function handleStatusChange(id, newStatus) {
    state.error = null;
    apiPatch('requests/' + id, { status: newStatus })
      .then(function () { return apiFetch('requests'); })
      .then(function (requests) {
        state.requests = requests || [];
        state.notice   = 'Status updated to "' + newStatus + '".';
        render();
      })
      .catch(function (err) {
        state.error = err.message || 'Failed to update status.';
        render();
      });
  }

  function handleSaveNotes(id) {
    var notes = state.draftNotes !== null ? state.draftNotes
      : (state.requests.find(function (r) { return String(r.id) === String(id); }) || {}).notes || '';
    state.error = null;
    apiPatch('requests/' + id, { notes: notes })
      .then(function () { return apiFetch('requests'); })
      .then(function (requests) {
        state.requests   = requests || [];
        state.draftNotes = null;
        state.notice     = 'Notes saved.';
        render();
      })
      .catch(function (err) {
        state.error = err.message || 'Failed to save notes.';
        render();
      });
  }

  function handleSendTestEvent() {
    state.integrationSending = true;
    state.error = null;
    render();
    apiPost('integration/test-event', {})
      .then(function (result) {
        state.integrationSending = false;
        state.notice = 'Test event sent: ' + (result.message || result.status);
        if (state.integration) {
          state.integration.last_test = {
            status:  result.status,
            message: result.message || '',
            at:      result.at || null,
          };
        }
        render();
      })
      .catch(function (err) {
        state.integrationSending = false;
        state.error = err.message || 'Failed to send test event.';
        render();
      });
  }

  function handleSaveIntegration() {
    var formEl = document.getElementById('ops-view');
    if (!formEl) return;
    var data = {};
    formEl.querySelectorAll('[data-integration-field]').forEach(function (input) {
      var field = input.getAttribute('data-integration-field');
      if (input.type === 'checkbox') {
        data[field] = input.checked;
      } else {
        data[field] = input.value;
      }
    });
    // Empty HMAC secret field = keep existing; omit the key so save_settings skips it.
    if (!data.hmac_secret) delete data.hmac_secret;
    state.error = null;
    apiPost('integration/settings', data)
      .then(function (result) {
        state.integration = result;
        state.notice = 'Integration settings saved.';
        render();
      })
      .catch(function (err) {
        state.error = err.message || 'Failed to save integration settings.';
        render();
      });
  }

  function handleSyncRequest(feed) {
    state.integrationSyncing = feed;
    state.error = null;
    render();
    apiPost('integration/sync/' + feed, {})
      .then(function (result) {
        state.integrationSyncing = null;
        var feedLabels = { vendor: 'Vendors', item: 'Items', po: 'Open POs', demand: 'Demand' };
        state.notice = 'Sync request sent for ' + (feedLabels[feed] || feed) + ': ' + (result.message || result.status);
        return apiFetch('integration/status');
      })
      .then(function (status) {
        state.integration = status;
        render();
      })
      .catch(function (err) {
        state.integrationSyncing = null;
        state.error = err.message || 'Failed to send sync request.';
        render();
      });
  }

  function renderDrawer() {
    if (!state.activeRequestId) return '';
    var r = state.requests.find(function (req) {
      return String(req.id) === String(state.activeRequestId);
    });
    if (!r) return '';

    var total     = parseFloat(r.qty || 0) * parseFloat(r.unit_cost || 0);
    var allowed   = PR_TRANSITIONS[r.status] || [];
    var notesVal  = state.draftNotes !== null ? state.draftNotes : (r.notes || '');
    var notesChanged = state.draftNotes !== null && state.draftNotes !== (r.notes || '');

    var actionBtns = allowed.map(function (s) {
      var meta = STATUS_ACTION_META[s] || { label: s, cls: 'pur-btn--outline' };
      return '<button class="pur-btn ' + meta.cls + '" data-action="status-change" data-status="' + s + '" data-request-id="' + r.id + '">' +
        meta.label +
      '</button>';
    }).join('');

    var fields = [
      ['Item',          esc(r.item_description)],
      ['Vendor',        esc(r.vendor_name_resolved || '—')],
      ['Qty',           esc(r.qty)],
      ['Unit Cost',     fmt$(r.unit_cost)],
      ['Total',         fmt$(total)],
      ['Requested By',  esc(r.requested_by_name || '—')],
      ['Created',       esc(fmtDate(r.created_at))],
      ['Updated',       esc(fmtDate(r.updated_at))],
    ].map(function (pair) {
      return '<div class="pur-drawer-field">' +
        '<span class="pur-drawer-label">' + pair[0] + '</span>' +
        '<span class="pur-drawer-value">' + pair[1] + '</span>' +
      '</div>';
    }).join('');

    return '<div class="pur-drawer-scrim" data-action="close-drawer"></div>' +
      '<div class="pur-drawer" role="dialog" aria-label="Purchase request details">' +
        '<div class="pur-drawer-header">' +
          '<div class="pur-drawer-header-left">' +
            '<span class="pur-drawer-pr-number">' + esc(r.request_number || 'PR') + '</span>' +
            badge(r.status) +
          '</div>' +
          '<button class="pur-drawer-close" data-action="close-drawer" aria-label="Close">✕</button>' +
        '</div>' +
        '<div class="pur-drawer-title">' + esc(r.item_description) + '</div>' +
        '<div class="pur-drawer-body">' +
          '<div class="pur-drawer-fields">' + fields + '</div>' +
          '<div class="pur-drawer-notes-section">' +
            '<label class="pur-drawer-label" for="pur-notes-input">Notes</label>' +
            '<textarea id="pur-notes-input" class="pur-drawer-notes-input" rows="4"' +
              ' data-action="edit-notes" data-request-id="' + r.id + '">' +
              esc(notesVal) +
            '</textarea>' +
            '<button class="pur-btn pur-btn--outline pur-drawer-notes-save' +
              (notesChanged ? '' : ' pur-btn--disabled') + '"' +
              (notesChanged ? '' : ' disabled') +
              ' data-action="save-notes" data-request-id="' + r.id + '">Save Notes</button>' +
          '</div>' +
        '</div>' +
        (allowed.length ? '<div class="pur-drawer-footer">' + actionBtns + '</div>' : '') +
      '</div>';
  }

  // ─── Vendor detail drawer ────────────────────────────────────────────────
  function renderVendorDrawer() {
    if (!state.activeVendorId) return '';
    var v = state.vendors.find(function (vv) {
      return String(vv.id) === String(state.activeVendorId);
    });
    if (!v) return '';

    var flags = vendorFlags(v);
    var warningsHtml = !flags.length ? '' :
      '<div class="pur-drawer-warnings">' +
      flags.map(function (f) {
        return '<div class="pur-drawer-warning-item">' +
          '<span class="material-symbols-outlined">warning</span>' +
          esc(f) +
        '</div>';
      }).join('') +
      '</div>';

    var fields = [
      ['Email',         esc(v.contact_email || '—')],
      ['Phone',         esc(v.contact_phone || '—')],
      ['Lead Time',     esc(fmtLeadTime(v.lead_time_days))],
      ['Payment Terms', esc(v.payment_terms || '—')],
      ['Freight Terms', esc(v.freight_terms || '—')],
      ['Min Order',     v.min_order_amount != null ? fmt$(v.min_order_amount) : '—'],
    ].map(function (pair) {
      return '<div class="pur-drawer-field">' +
        '<span class="pur-drawer-label">' + pair[0] + '</span>' +
        '<span class="pur-drawer-value">' + pair[1] + '</span>' +
      '</div>';
    }).join('');

    return '<div class="pur-drawer-scrim" data-action="close-vendor-drawer"></div>' +
      '<div class="pur-drawer" role="dialog" aria-label="Vendor details">' +
        '<div class="pur-drawer-header">' +
          '<div class="pur-drawer-header-left">' +
            badge(v.status) +
            (flags.length ? badge('missing_info') : '') +
          '</div>' +
          '<button class="pur-drawer-close" data-action="close-vendor-drawer" aria-label="Close">✕</button>' +
        '</div>' +
        '<div class="pur-drawer-title">' + esc(v.name) + '</div>' +
        '<div class="pur-drawer-body">' +
          warningsHtml +
          '<div class="pur-drawer-fields">' + fields + '</div>' +
        '</div>' +
      '</div>';
  }

  // ─── PO detail drawer ─────────────────────────────────────────────────────
  function renderPoDrawer() {
    if (!state.activePoId) return '';
    var p = state.openPos.find(function (po) {
      return String(po.id) === String(state.activePoId);
    });
    if (!p) return '';

    var source = p.bc_po_id ? 'Business Central' : 'Manual';
    var fieldPairs = [
      ['Ordered',    esc(fmtDate(p.ordered_at))],
      ['Expected',   esc(fmtDate(p.expected_date))],
      ['Total',      fmt$(p.total_value || 0)],
      ['Source',     esc(source)],
    ];
    if (p.notes) fieldPairs.push(['Notes', esc(p.notes)]);
    var fieldsHtml = fieldPairs.map(function (pair) {
      return '<div class="pur-drawer-field">' +
        '<span class="pur-drawer-label">' + pair[0] + '</span>' +
        '<span class="pur-drawer-value">' + pair[1] + '</span>' +
      '</div>';
    }).join('');

    var linesHtml;
    if (state.poLinesLoading) {
      linesHtml = renderLoading();
    } else {
      var lines = state.poLines[state.activePoId];
      if (!lines || !lines.length) {
        linesHtml = renderEmpty('receipt_long', 'No line items', 'No purchase order lines are recorded locally.');
      } else {
        var lineRows = lines.map(function (l) {
          var lineTotal = parseFloat(l.qty_ordered || 0) * parseFloat(l.unit_cost || 0);
          return '<tr>' +
            '<td>' + esc(l.item_description) + '</td>' +
            '<td class="pur-col-num">' + esc(l.qty_ordered) + '</td>' +
            '<td class="pur-col-num">' + esc(l.qty_received) + '</td>' +
            '<td class="pur-col-num">' + fmt$(l.unit_cost) + '</td>' +
            '<td class="pur-col-num">' + fmt$(lineTotal) + '</td>' +
          '</tr>';
        }).join('');
        linesHtml = '<div class="pur-table-wrap"><table class="pur-table">' +
          '<thead><tr>' +
            '<th>Item</th>' +
            '<th style="text-align:right">Ordered</th>' +
            '<th style="text-align:right">Received</th>' +
            '<th style="text-align:right">Unit Cost</th>' +
            '<th style="text-align:right">Total</th>' +
          '</tr></thead>' +
          '<tbody>' + lineRows + '</tbody>' +
        '</table></div>';
      }
    }

    return '<div class="pur-drawer-scrim" data-action="close-po-drawer"></div>' +
      '<div class="pur-drawer" role="dialog" aria-label="Purchase order details">' +
        '<div class="pur-drawer-header">' +
          '<div class="pur-drawer-header-left">' +
            '<span class="pur-drawer-pr-number">' + esc(p.po_number || 'PO') + '</span>' +
            badge(p.status) +
          '</div>' +
          '<button class="pur-drawer-close" data-action="close-po-drawer" aria-label="Close">✕</button>' +
        '</div>' +
        '<div class="pur-drawer-title">' + esc(p.vendor_name || 'Purchase Order') + '</div>' +
        '<div class="pur-drawer-body">' +
          '<div class="pur-drawer-fields">' + fieldsHtml + '</div>' +
          '<div class="pur-drawer-section-title">Line Items</div>' +
          linesHtml +
        '</div>' +
      '</div>';
  }

  // ─── Create purchase requests ─────────────────────────────────────────────
  function handleCreateRequests() {
    var selected = state.demand.filter(function (d) {
      return state.selectedDemandIds.indexOf(String(d.id)) !== -1 &&
             d.preferred_vendor_id;
    });

    if (!selected.length) {
      state.error = 'No eligible items selected. Items must have a vendor assigned.';
      render();
      return;
    }

    state.loading = true;
    state.error   = null;
    render();

    var items = selected.map(function (d) {
      return {
        item_id:          parseInt(d.id, 10),
        item_description: String(d.description || d.part_number || 'Unknown item'),
        vendor_id:        parseInt(d.preferred_vendor_id, 10),
        qty:              parseInt(d.suggested_order, 10) > 0 ? parseInt(d.suggested_order, 10) : 1,
        unit_cost:        parseFloat(d.unit_cost) || 0,
      };
    });

    apiPost('requests', { items: items })
      .then(function (res) {
        var count = (res.created || []).length;
        state.notice = 'Created ' + count + ' purchase request' + (count !== 1 ? 's' : '') + '.';
        state.selectedDemandIds = [];
        return apiFetch('requests');
      })
      .then(function (requests) {
        state.requests = requests || [];
        state.loading  = false;
        render();
      })
      .catch(function (err) {
        state.loading = false;
        state.error   = err.message || 'Failed to create purchase requests.';
        render();
      });
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

  function renderDemandActionBar() {
    if (!state.selectedDemandIds.length) return '';
    var vendorIds = {};
    state.demand.forEach(function (d) {
      if (state.selectedDemandIds.indexOf(String(d.id)) !== -1 && d.preferred_vendor_id) {
        vendorIds[d.preferred_vendor_id] = true;
      }
    });
    var n = state.selectedDemandIds.length;
    var v = Object.keys(vendorIds).length;
    return '<div class="pur-demand-action-bar">' +
      '<span class="pur-demand-action-info">' +
        n + ' item' + (n !== 1 ? 's' : '') + ' selected' +
        (v > 0 ? ' across ' + v + ' vendor' + (v !== 1 ? 's' : '') : '') +
      '</span>' +
      '<div class="pur-demand-action-btns">' +
        '<button class="pur-btn pur-btn--outline" data-action="clear-selection">Clear</button>' +
        '<button class="pur-btn pur-btn--primary" data-action="create-requests">' +
          '<span class="material-symbols-outlined">add_shopping_cart</span>' +
          'Create Request' + (v > 1 ? 's' : '') +
        '</button>' +
      '</div>' +
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
      var eligibleIds = items
        .filter(function (d) { return d.preferred_vendor_id; })
        .map(function (d) { return String(d.id); });
      var allSelected = eligibleIds.length > 0 && eligibleIds.every(function (id) {
        return state.selectedDemandIds.indexOf(id) !== -1;
      });

      var rows = items.map(function (d) {
        var onHand      = parseInt(d.on_hand, 10);
        var reorder     = parseInt(d.reorder_point, 10);
        var needsReorder = reorder > 0 && onHand <= reorder;
        var isDisabled  = !d.preferred_vendor_id;
        var isSelected  = state.selectedDemandIds.indexOf(String(d.id)) !== -1;
        var pct         = reorder > 0 ? Math.min(100, Math.round((onHand / reorder) * 100)) : 100;
        var level       = d.demand_level || 'low';
        var barClass    = level === 'high' ? 'pur-demand-bar--high'
                        : level === 'medium' ? 'pur-demand-bar--medium'
                        : 'pur-demand-bar--low';
        var rowClasses  = [
          needsReorder ? 'pur-row-alert'    : '',
          isSelected   ? 'pur-row-selected' : '',
          isDisabled   ? 'pur-row-disabled' : '',
        ].filter(Boolean).join(' ');
        return '<tr' + (rowClasses ? ' class="' + rowClasses + '"' : '') +
               ' data-demand-id="' + d.id + '">' +
          '<td class="pur-col-check">' +
            '<input type="checkbox" class="pur-demand-check" data-id="' + d.id + '"' +
              (isSelected ? ' checked' : '') + (isDisabled ? ' disabled' : '') +
            '>' +
          '</td>' +
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
          '<th class="pur-col-check">' +
            '<input type="checkbox" class="pur-demand-check" data-action="select-all-demand"' +
              (allSelected ? ' checked' : '') +
            '>' +
          '</th>' +
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
      renderDemandActionBar() +
      body +
    '</div>';
  }

  // ─── Purchase Requests tab ────────────────────────────────────────────────
  function renderRequests() {
    if (state.loading) return renderLoading();

    var body;
    if (!state.requests.length) {
      body = renderEmpty('request_quote', 'No purchase requests', 'Select items on the Demand tab to create purchase requests.');
    } else {
      var rows = state.requests.map(function (r) {
        var total      = parseFloat(r.qty || 0) * parseFloat(r.unit_cost || 0);
        var vendorName = r.vendor_name_resolved || '—';
        return '<tr class="pur-row-clickable" data-action="open-request" data-request-id="' + r.id + '">' +
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

    var totalV    = state.vendors.length;
    var activeV   = state.vendors.filter(function (v) { return v.status === 'active'; }).length;
    var inactiveV = totalV - activeV;
    var missingV  = state.vendors.filter(function (v) { return vendorFlags(v).length > 0; }).length;

    var countBar = totalV ? (
      '<div class="pur-vendor-counts">' +
        '<span class="pur-count-chip pur-count-chip--ok">' +
          '<span class="material-symbols-outlined">check_circle</span>' + activeV + ' active' +
        '</span>' +
        '<span class="pur-count-chip pur-count-chip--muted">' +
          '<span class="material-symbols-outlined">block</span>' + inactiveV + ' inactive / on hold' +
        '</span>' +
        (missingV ? (
          '<span class="pur-count-chip pur-count-chip--warn">' +
            '<span class="material-symbols-outlined">warning</span>' + missingV + ' missing info' +
          '</span>'
        ) : '') +
      '</div>'
    ) : '';

    var body;
    if (!totalV) {
      body = renderEmpty('storefront', 'No vendors', 'Vendors will appear here once added.');
    } else {
      var rows = state.vendors.map(function (v) {
        var flags   = vendorFlags(v);
        var flagHtml = flags.length
          ? '<span class="pur-vendor-warn-chip" title="' + esc(flags.join(', ')) + '">' +
              '<span class="material-symbols-outlined">warning</span>' + flags.length +
            '</span>'
          : '<span class="pur-vendor-ok-chip"><span class="material-symbols-outlined">check_circle</span></span>';
        return '<tr class="pur-row-clickable" data-action="open-vendor" data-vendor-id="' + esc(v.id) + '">' +
          '<td style="font-weight:500">' + esc(v.name) + '</td>' +
          '<td class="pur-col-muted">' + esc(v.contact_email || v.contact_phone || '—') + '</td>' +
          '<td class="pur-col-muted">' + esc(fmtLeadTime(v.lead_time_days)) + '</td>' +
          '<td class="pur-col-muted">' + esc(v.payment_terms || '—') + '</td>' +
          '<td>' + badge(v.status) + '</td>' +
          '<td>' + flagHtml + '</td>' +
        '</tr>';
      }).join('');
      body = '<div class="pur-table-wrap"><table class="pur-table">' +
        '<thead><tr>' +
          '<th>Name</th><th>Contact</th><th>Lead Time</th><th>Terms</th><th>Status</th><th>Flags</th>' +
        '</tr></thead>' +
        '<tbody>' + rows + '</tbody>' +
      '</table></div>';
    }

    return '<div class="pur-card">' +
      '<div class="pur-card-header">' +
        '<h2 class="pur-card-title">Vendors</h2>' +
        '<span class="pur-card-meta">' + activeV + ' of ' + totalV + ' active</span>' +
      '</div>' +
      countBar +
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
        return '<tr class="pur-row-clickable" data-action="open-po" data-po-id="' + esc(p.id) + '">' +
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
  var SYNC_FEED_LABELS = { vendor: 'Vendors', item: 'Items', po: 'Open POs', demand: 'Demand' };

  function renderAPIStatus() {
    var d = state.integration;

    function checkRow(label, ok, detail) {
      return '<div class="pur-api-row">' +
        '<span class="pur-api-row-label">' + esc(label) + '</span>' +
        '<span class="pur-api-row-value ' + (ok ? 'pur-api-row-value--ok' : 'pur-api-row-value--warn') + '">' +
          esc(detail != null ? detail : (ok ? 'Configured' : 'Not Configured')) +
        '</span>' +
      '</div>';
    }

    var flows = d ? d.flows_configured : {};
    var lastTest = d ? d.last_test : {};
    var lastTestCls = !lastTest.status ? 'pur-api-row-value--muted'
      : lastTest.status === 'success' ? 'pur-api-row-value--ok' : 'pur-api-row-value--warn';
    var lastTestStr = !lastTest.status ? 'Never sent'
      : (lastTest.status === 'success' ? '✓ ' : '✗ ') +
        (lastTest.message || lastTest.status) +
        (lastTest.at ? ' · ' + fmtDate(lastTest.at) : '');

    var statusCard = '<div class="pur-api-card">' +
      '<div class="pur-api-card-title">Integration Readiness</div>' +
      (d ? (
        checkRow('Power Automate',       d.enabled,              d.enabled ? 'Enabled' : 'Disabled') +
        checkRow('HMAC Secret',          d.hmac_configured) +
        checkRow('PR Approved Flow',     flows.pr) +
        checkRow('Vendor Sync Flow',     flows.vendor) +
        checkRow('Item Sync Flow',       flows.item) +
        checkRow('Open PO Sync Flow',    flows.po) +
        checkRow('Demand Sync Flow',     flows.demand) +
        '<div class="pur-api-row">' +
          '<span class="pur-api-row-label">Last Test Event</span>' +
          '<span class="pur-api-row-value ' + lastTestCls + '">' + esc(lastTestStr) + '</span>' +
        '</div>' +
        checkRow('Callback Receiver',    d.callback_available, d.callback_available ? 'Available' : 'Unavailable') +
        checkRow('Business Central',     false, 'Not Connected (WordPress)')
      ) : '<div class="pur-empty" style="padding:20px">Loading…</div>') +
    '</div>';

    var archCard = '<div class="pur-api-card">' +
      '<div class="pur-api-card-title">Architecture</div>' +
      '<div class="pur-integration-arch">' +
        '<span class="pur-arch-node">Slate Ops</span>' +
        '<span class="material-symbols-outlined">arrow_forward</span>' +
        '<span class="pur-arch-node">Power Automate</span>' +
        '<span class="material-symbols-outlined">arrow_forward</span>' +
        '<span class="pur-arch-node">Business Central</span>' +
      '</div>' +
      '<p class="pur-api-note" style="margin-top:14px">' +
        'Slate Ops emits HMAC-signed events to Power Automate flows. ' +
        'Flows read from Business Central and POST synced data back to the callback endpoint. ' +
        'No BC credentials are stored in WordPress.' +
      '</p>' +
    '</div>';

    // ── Sync status card ────────────────────────────────────────────────────
    var syncFeeds  = ['vendor', 'item', 'po', 'demand'];
    var syncStatus = d ? d.sync_status : null;
    var flows      = d ? (d.flows_configured || {}) : {};
    // Per-feed map from feed key → flows_configured key (all match 1:1 for sync feeds)
    var paNotEnabled = !d || !d.enabled;
    var unsignedMode = d && d.enabled && !d.hmac_configured;

    var syncRows = syncFeeds.map(function (feed) {
      var fs          = syncStatus ? syncStatus[feed] : null;
      var isSyncing   = state.integrationSyncing === feed;
      var feedDisabled = paNotEnabled || !flows[feed];
      var statusCls, statusStr;
      if (isSyncing) {
        statusCls = 'pur-sync-status--pending';
        statusStr = '⟳ Sending…';
      } else if (!fs || !fs.status) {
        statusCls = 'pur-sync-status--never';
        statusStr = 'Never synced';
      } else if (fs.status === 'success') {
        statusCls = 'pur-sync-status--ok';
        statusStr = '✓ ' + (fs.message || 'Synced');
      } else if (fs.status === 'pending') {
        statusCls = 'pur-sync-status--pending';
        statusStr = '⟳ ' + (fs.message || 'Pending');
      } else {
        statusCls = 'pur-sync-status--error';
        statusStr = '✗ ' + (fs.message || 'Error');
      }
      var lastAt = fs && fs.at ? fmtDate(fs.at)
        : (fs && fs.requested_at ? 'Requested ' + fmtDate(fs.requested_at) : '—');

      var syncBtn = IS_ADMIN
        ? '<button class="pur-btn pur-btn--outline pur-sync-btn"' +
            ' data-action="sync-request" data-feed="' + esc(feed) + '"' +
            (feedDisabled || !!state.integrationSyncing ? ' disabled' : '') + '>' +
            (isSyncing
              ? '<span class="material-symbols-outlined">sync</span>Syncing…'
              : '<span class="material-symbols-outlined">sync</span>Sync ' + esc(SYNC_FEED_LABELS[feed] || feed)) +
          '</button>'
        : '';

      return '<div class="pur-sync-row">' +
        '<span class="pur-sync-feed">' + esc(SYNC_FEED_LABELS[feed] || feed) + '</span>' +
        '<span class="pur-sync-status ' + statusCls + '">' + esc(statusStr) + '</span>' +
        '<span class="pur-sync-time">' + esc(lastAt) + '</span>' +
        syncBtn +
      '</div>';
    }).join('');

    var syncCard = '<div class="pur-api-card pur-sync-card">' +
      '<div class="pur-api-card-title">Sync Status' +
      (IS_ADMIN ? '<span class="pur-sync-card-hint">Admin — manual triggers</span>' : '') +
      '</div>' +
      (unsignedMode
        ? '<div class="pur-sync-unsigned-warning">' +
            '<span class="material-symbols-outlined">warning</span>' +
            'Unsigned sandbox callback mode active. Configure an HMAC secret before going to production.' +
          '</div>'
        : '') +
      (paNotEnabled && IS_ADMIN
        ? '<div class="pur-sync-disabled-note">Enable Power Automate to trigger syncs.</div>'
        : '') +
      '<div class="pur-sync-table">' + syncRows + '</div>' +
    '</div>';

    if (!IS_ADMIN) {
      return '<div class="pur-api-grid">' + statusCard + archCard + '</div>' + syncCard;
    }

    // Admin-only settings form
    var urls = d ? d.flow_urls : {};

    function flowField(label, fieldKey, urlKey) {
      var val = urls[urlKey] || '';
      return '<div class="pur-int-row">' +
        '<label class="pur-int-label">' + esc(label) + '</label>' +
        '<input type="url" class="pur-int-input" placeholder="https://prod-xx.logic.azure.com/…"' +
          ' data-integration-field="' + esc(fieldKey) + '" value="' + esc(val) + '">' +
      '</div>';
    }

    var testDisabled = !d || !d.hmac_configured || !d.enabled || state.integrationSending;
    var testBtn = '<button class="pur-btn pur-btn--outline" data-action="send-test-event"' +
      (testDisabled ? ' disabled' : '') + '>' +
      (state.integrationSending
        ? '<span class="material-symbols-outlined">sync</span>Sending…'
        : '<span class="material-symbols-outlined">send</span>Send Test Event') +
    '</button>';

    var settingsCard = '<div class="pur-card">' +
      '<div class="pur-card-header">' +
        '<h2 class="pur-card-title">Integration Settings</h2>' +
        '<span class="pur-badge pur-badge--draft">Admin Only</span>' +
      '</div>' +
      '<div class="pur-card-body pur-integration-form">' +
        '<div class="pur-int-row">' +
          '<label class="pur-int-label">Power Automate</label>' +
          '<label class="pur-toggle">' +
            '<input type="checkbox" data-integration-field="enabled"' + (d && d.enabled ? ' checked' : '') + '>' +
            '<span>Enabled</span>' +
          '</label>' +
        '</div>' +
        flowField('PR Approved Flow',  'flow_pr_url',     'pr') +
        flowField('Vendor Sync Flow',  'flow_vendor_url', 'vendor') +
        flowField('Item Sync Flow',    'flow_item_url',   'item') +
        flowField('Open PO Sync Flow', 'flow_po_url',     'po') +
        flowField('Demand Sync Flow',  'flow_demand_url', 'demand') +
        '<div class="pur-int-row">' +
          '<label class="pur-int-label">HMAC Secret</label>' +
          '<input type="password" class="pur-int-input" autocomplete="new-password"' +
            ' placeholder="' + (d && d.hmac_configured ? '••••••••' : 'Enter secret…') + '"' +
            ' data-integration-field="hmac_secret">' +
        '</div>' +
        '<p class="pur-int-hint">' +
          (d && d.hmac_configured
            ? 'Secret is configured. Enter a new value to replace it, or leave blank to keep the current secret.'
            : 'No secret configured. Enter a value to enable signing.') +
        '</p>' +
        '<div class="pur-int-row pur-int-row--actions">' +
          '<button class="pur-btn pur-btn--primary" data-action="save-integration">Save Settings</button>' +
          testBtn +
        '</div>' +
      '</div>' +
    '</div>';

    return '<div class="pur-api-grid">' + statusCard + archCard + '</div>' +
      syncCard + settingsCard;
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
      '</div>' +
      renderDrawer() +
      renderVendorDrawer() +
      renderPoDrawer();

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

    // Select-all demand checkbox
    el.querySelectorAll('[data-action="select-all-demand"]').forEach(function (cb) {
      cb.addEventListener('change', function () {
        var eligibleIds = filteredDemand()
          .filter(function (d) { return d.preferred_vendor_id; })
          .map(function (d) { return String(d.id); });
        state.selectedDemandIds = cb.checked ? eligibleIds : [];
        render();
      });
    });

    // Individual demand row checkboxes
    el.querySelectorAll('.pur-demand-check[data-id]').forEach(function (cb) {
      cb.addEventListener('change', function () {
        var id  = String(cb.getAttribute('data-id'));
        var idx = state.selectedDemandIds.indexOf(id);
        if (cb.checked && idx === -1) {
          state.selectedDemandIds.push(id);
        } else if (!cb.checked && idx !== -1) {
          state.selectedDemandIds.splice(idx, 1);
        }
        render();
      });
    });

    // Clear selection
    el.querySelectorAll('[data-action="clear-selection"]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        state.selectedDemandIds = [];
        render();
      });
    });

    // Create purchase requests
    el.querySelectorAll('[data-action="create-requests"]').forEach(function (btn) {
      btn.addEventListener('click', handleCreateRequests);
    });

    // Open PR detail drawer on row click
    el.querySelectorAll('[data-action="open-request"]').forEach(function (row) {
      row.addEventListener('click', function () {
        state.activeVendorId  = null;
        state.activePoId      = null;
        state.activeRequestId = row.getAttribute('data-request-id');
        state.draftNotes      = null;
        render();
      });
    });

    // Close drawer
    el.querySelectorAll('[data-action="close-drawer"]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        state.activeRequestId = null;
        state.draftNotes      = null;
        render();
      });
    });

    // Status change buttons in drawer
    el.querySelectorAll('[data-action="status-change"]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        handleStatusChange(
          btn.getAttribute('data-request-id'),
          btn.getAttribute('data-status')
        );
      });
    });

    // Notes textarea — update draftNotes without re-rendering
    el.querySelectorAll('[data-action="edit-notes"]').forEach(function (ta) {
      var reqId    = ta.getAttribute('data-request-id');
      var origNotes = (state.requests.find(function (r) { return String(r.id) === String(reqId); }) || {}).notes || '';
      ta.addEventListener('input', function () {
        state.draftNotes = ta.value;
        // Enable/disable save button without full re-render
        var section = ta.closest('.pur-drawer-notes-section');
        var saveBtn = section ? section.querySelector('[data-action="save-notes"]') : null;
        if (saveBtn) {
          var changed = ta.value !== origNotes;
          saveBtn.disabled = !changed;
          saveBtn.classList.toggle('pur-btn--disabled', !changed);
        }
      });
    });

    // Open vendor drawer
    el.querySelectorAll('[data-action="open-vendor"]').forEach(function (row) {
      row.addEventListener('click', function () {
        state.activeRequestId = null;
        state.activePoId      = null;
        state.activeVendorId  = row.getAttribute('data-vendor-id');
        render();
      });
    });

    // Close vendor drawer
    el.querySelectorAll('[data-action="close-vendor-drawer"]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        state.activeVendorId = null;
        render();
      });
    });

    // Open PO drawer — fetches lines on first open; caches thereafter
    el.querySelectorAll('[data-action="open-po"]').forEach(function (row) {
      row.addEventListener('click', function () {
        var id = row.getAttribute('data-po-id');
        state.activeRequestId = null;
        state.activeVendorId  = null;
        state.activePoId      = id;
        var needsLoad = !state.poLines.hasOwnProperty(id);
        state.poLinesLoading = needsLoad;
        render();
        if (needsLoad) {
          apiFetch('orders/' + id + '/lines')
            .then(function (lines) {
              state.poLines[id] = lines || [];
              if (state.activePoId === id) state.poLinesLoading = false;
              render();
            })
            .catch(function () {
              state.poLines[id] = [];
              if (state.activePoId === id) state.poLinesLoading = false;
              render();
            });
        }
      });
    });

    // Close PO drawer
    el.querySelectorAll('[data-action="close-po-drawer"]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        state.activePoId = null;
        render();
      });
    });

    // Save notes button
    el.querySelectorAll('[data-action="save-notes"]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        handleSaveNotes(btn.getAttribute('data-request-id'));
      });
    });

    // Integration settings save
    el.querySelectorAll('[data-action="save-integration"]').forEach(function (btn) {
      btn.addEventListener('click', handleSaveIntegration);
    });

    // Send test event
    el.querySelectorAll('[data-action="send-test-event"]').forEach(function (btn) {
      btn.addEventListener('click', handleSendTestEvent);
    });

    // Sync request buttons (admin only)
    el.querySelectorAll('[data-action="sync-request"]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var feed = btn.getAttribute('data-feed');
        if (feed) handleSyncRequest(feed);
      });
    });

    // Set indeterminate state on select-all after binding
    var selectAll = el.querySelector('[data-action="select-all-demand"]');
    if (selectAll) {
      var eligibleIds = filteredDemand()
        .filter(function (d) { return d.preferred_vendor_id; })
        .map(function (d) { return String(d.id); });
      var selCount = eligibleIds.filter(function (id) {
        return state.selectedDemandIds.indexOf(id) !== -1;
      }).length;
      selectAll.indeterminate = selCount > 0 && selCount < eligibleIds.length;
    }
  }

  // ─── Boot ─────────────────────────────────────────────────────────────────
  function boot() {
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && (state.activeRequestId || state.activeVendorId || state.activePoId)) {
        state.activeRequestId = null;
        state.activeVendorId  = null;
        state.activePoId      = null;
        state.draftNotes      = null;
        render();
      }
    });
    render();
    loadAll();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

})();
