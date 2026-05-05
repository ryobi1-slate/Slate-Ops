/**
 * Slate Ops — Executive: Tech Time Records (Phase 0 validation panel)
 *
 * Injected as a sibling of #ops-view so it lives outside React's DOM tree.
 * Enqueued only on /ops/exec pages. Read-only — no write operations.
 */
(function () {
  'use strict';

  if (window.location.pathname.indexOf('/ops/exec') === -1) return;

  var settings = (typeof slateOpsSettings !== 'undefined') ? slateOpsSettings : null;
  if (!settings || !settings.api) return;

  // ── Scoped styles ──────────────────────────────────────────────────────────
  var style = document.createElement('style');
  style.textContent = [
    '.sotr-wrap{max-width:var(--slate-shell-maxwidth,1440px);margin:0 auto;padding:0 1.5rem 2.5rem}',
    '.sotr-card{background:var(--slate-surface,#fff);border:1px solid var(--slate-divider,#e6e2d4);border-radius:8px;padding:1.5rem}',
    '.sotr-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1rem}',
    '.sotr-eyebrow{font-size:.7rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--slate-ink-subtle,#8a8880);margin-bottom:.25rem}',
    '.sotr-title{font-size:1.1rem;font-weight:600;color:var(--slate-ink,#2a2e2c);margin:0 0 .25rem}',
    '.sotr-subtitle{font-size:.82rem;color:var(--slate-ink-muted,#5a5e5c);margin:0}',
    '.sotr-refresh{flex-shrink:0;background:transparent;border:1px solid var(--slate-divider,#e6e2d4);border-radius:4px;padding:.35rem .75rem;font-size:.8rem;cursor:pointer;color:var(--slate-ink-muted,#5a5e5c)}',
    '.sotr-refresh:hover{background:var(--slate-surface-tint,#fafaf7)}',
    '.sotr-filters{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem}',
    '.sotr-select,.sotr-input{font-size:.82rem;padding:.35rem .6rem;border:1px solid var(--slate-divider,#e6e2d4);border-radius:4px;background:var(--slate-surface,#fff);color:var(--slate-ink,#2a2e2c)}',
    '.sotr-input{min-width:200px}',
    '.sotr-table-wrap{overflow-x:auto}',
    '.sotr-table{width:100%;border-collapse:collapse;font-size:.8rem}',
    '.sotr-table th{text-align:left;padding:.5rem .75rem;font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--slate-ink-subtle,#8a8880);border-bottom:1px solid var(--slate-divider,#e6e2d4);white-space:nowrap}',
    '.sotr-table td{padding:.5rem .75rem;border-bottom:1px solid var(--slate-divider-soft,#f0ede3);vertical-align:top;color:var(--slate-ink,#2a2e2c)}',
    '.sotr-table tr:last-child td{border-bottom:none}',
    '.sotr-table tr:hover td{background:var(--slate-surface-tint,#fafaf7)}',
    '.sotr-mono{font-family:ui-monospace,monospace;font-size:.76rem}',
    '.sotr-ts{white-space:nowrap;font-size:.76rem;color:var(--slate-ink-muted,#5a5e5c)}',
    '.sotr-num{text-align:right;font-variant-numeric:tabular-nums}',
    '.sotr-badge{display:inline-block;padding:.15rem .5rem;border-radius:3px;font-size:.7rem;font-weight:600;text-transform:lowercase;letter-spacing:.02em}',
    '.sotr-badge--active{background:var(--slate-sage,#40464b);color:#fff}',
    '.sotr-badge--closed{background:var(--slate-sage-wash,#f2f3f1);color:var(--slate-sage-ink,#2b3034)}',
    '.sotr-badge--approved{background:var(--slate-redwood-wash,#e4eee9);color:var(--slate-redwood,#0f3a2a)}',
    '.sotr-badge--pending{background:var(--slate-arches-wash,#fdf2df);color:var(--slate-arches-ink,#b07212)}',
    '.sotr-badge--voided{background:var(--slate-flag-wash,#f8e5e5);color:var(--slate-flag,#b22222)}',
    '.sotr-loading,.sotr-empty,.sotr-error{padding:2rem;text-align:center;font-size:.85rem;color:var(--slate-ink-muted,#5a5e5c)}',
    '.sotr-error{color:var(--slate-flag,#b22222)}',
  ].join('');
  document.head.appendChild(style);

  // ── Helpers ────────────────────────────────────────────────────────────────
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function fmtTs(ts) {
    if (!ts) return '—';
    try {
      var d = new Date(ts.replace(' ', 'T') + (ts.indexOf('T') === -1 ? 'Z' : ''));
      if (isNaN(d.getTime())) return esc(ts);
      return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
        + ' ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    } catch (e) { return esc(ts); }
  }

  function badgeState(s) {
    if (s === 'active') return 'sotr-badge sotr-badge--active';
    if (s === 'closed') return 'sotr-badge sotr-badge--closed';
    return 'sotr-badge';
  }

  function badgeApproval(s) {
    if (s === 'approved') return 'sotr-badge sotr-badge--approved';
    if (s === 'voided')   return 'sotr-badge sotr-badge--voided';
    return 'sotr-badge sotr-badge--pending';
  }

  // ── Render ─────────────────────────────────────────────────────────────────
  function renderRows(segments) {
    if (!segments.length) {
      return '<tr><td colspan="11" class="sotr-empty">No time segments found for these filters.</td></tr>';
    }
    return segments.map(function (s) {
      return '<tr>'
        + '<td class="sotr-mono">' + esc(s.segment_id) + '</td>'
        + '<td class="sotr-mono">' + esc(s.so_number || ('#' + s.job_id)) + '</td>'
        + '<td>' + esc(s.customer_name || '—') + '</td>'
        + '<td>' + esc(s.tech_name || '—') + '</td>'
        + '<td class="sotr-ts">' + fmtTs(s.start_ts) + '</td>'
        + '<td class="sotr-ts">' + (s.end_ts ? fmtTs(s.end_ts) : '<span class="sotr-badge sotr-badge--active">open</span>') + '</td>'
        + '<td class="sotr-num">' + (s.duration_minutes != null ? s.duration_minutes : '—') + '</td>'
        + '<td><span class="' + badgeState(s.state) + '">' + esc(s.state || '—') + '</span></td>'
        + '<td>' + esc(s.reason || '—') + '</td>'
        + '<td>' + (s.note_preview ? esc(s.note_preview) : '—') + '</td>'
        + '<td><span class="' + badgeApproval(s.approval_status) + '">' + esc(s.approval_status || '—') + '</span></td>'
        + '</tr>';
    }).join('');
  }

  // ── Fetch ──────────────────────────────────────────────────────────────────
  function load(tbody, filters) {
    tbody.innerHTML = '<tr><td colspan="11" class="sotr-loading">Loading…</td></tr>';

    var base   = settings.api.root.replace(/\/$/, '') + '/executive/time-segments';
    var params = [];
    if (filters.state && filters.state !== 'all') params.push('state=' + encodeURIComponent(filters.state));
    if (filters.date_range && filters.date_range !== 'all') params.push('date_range=' + encodeURIComponent(filters.date_range));
    if (filters.q) params.push('q=' + encodeURIComponent(filters.q));
    var url = base + (params.length ? '?' + params.join('&') : '');

    fetch(url, { headers: { 'X-WP-Nonce': settings.api.nonce } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && Array.isArray(data.segments)) {
          tbody.innerHTML = renderRows(data.segments);
        } else {
          tbody.innerHTML = '<tr><td colspan="11" class="sotr-error">Unexpected response from server.</td></tr>';
        }
      })
      .catch(function (err) {
        tbody.innerHTML = '<tr><td colspan="11" class="sotr-error">Error: ' + esc(String(err)) + '</td></tr>';
      });
  }

  // ── Build card ─────────────────────────────────────────────────────────────
  function buildCard() {
    var wrap = document.createElement('div');
    wrap.id  = 'sotr-wrap';
    wrap.className = 'sotr-wrap';

    wrap.innerHTML = '<section class="sotr-card">'
      + '<div class="sotr-head">'
      +   '<div>'
      +     '<div class="sotr-eyebrow">Phase 0 Validation</div>'
      +     '<h2 class="sotr-title">Tech Time Records</h2>'
      +     '<p class="sotr-subtitle">Recent timer segments from the Slate Ops database.</p>'
      +   '</div>'
      +   '<button class="sotr-refresh" type="button" aria-label="Refresh">&#8635; Refresh</button>'
      + '</div>'
      + '<div class="sotr-filters">'
      +   '<select class="sotr-select" data-f="state" aria-label="Filter by state">'
      +     '<option value="all">All States</option>'
      +     '<option value="active">Active</option>'
      +     '<option value="closed">Closed</option>'
      +   '</select>'
      +   '<select class="sotr-select" data-f="date_range" aria-label="Filter by date">'
      +     '<option value="all">All Time</option>'
      +     '<option value="today">Today</option>'
      +     '<option value="7">Last 7 days</option>'
      +     '<option value="30">Last 30 days</option>'
      +   '</select>'
      +   '<input class="sotr-input" type="search" data-f="q" placeholder="Search SO#, customer, job…" aria-label="Search" />'
      + '</div>'
      + '<div class="sotr-table-wrap">'
      +   '<table class="sotr-table">'
      +     '<thead><tr>'
      +       '<th>Seg</th><th>Job / SO#</th><th>Customer</th><th>Tech</th>'
      +       '<th>Start</th><th>End</th><th>Min</th><th>State</th>'
      +       '<th>Reason</th><th>Note</th><th>Approval</th>'
      +     '</tr></thead>'
      +     '<tbody id="sotr-tbody"></tbody>'
      +   '</table>'
      + '</div>'
      + '</section>';

    return wrap;
  }

  // ── Wire and mount ─────────────────────────────────────────────────────────
  function mount() {
    if (document.getElementById('sotr-wrap')) return;

    var content = document.querySelector('.ops-content');
    if (!content) return;

    var card = buildCard();
    content.appendChild(card);

    var tbody  = document.getElementById('sotr-tbody');
    var filters = { state: 'all', date_range: 'all', q: '' };
    var debounce;

    card.querySelector('.sotr-refresh').addEventListener('click', function () {
      load(tbody, filters);
    });

    card.querySelectorAll('[data-f]').forEach(function (el) {
      var key = el.getAttribute('data-f');
      el.addEventListener('change', function () {
        filters[key] = el.value;
        load(tbody, filters);
      });
      el.addEventListener('input', function () {
        if (el.type === 'search') {
          clearTimeout(debounce);
          debounce = setTimeout(function () {
            filters[key] = el.value.trim();
            load(tbody, filters);
          }, 400);
        }
      });
    });

    load(tbody, filters);
  }

  // Mount immediately (script runs in footer, DOM is ready)
  mount();
})();
