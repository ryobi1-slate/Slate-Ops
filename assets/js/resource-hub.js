(function () {
  'use strict';

  var root = document.querySelector('.rh-app');
  if (!root) return;

  var titleEl = document.getElementById('ops-page-title');
  if (titleEl) titleEl.textContent = 'Resource hub';

  var dataEl = document.getElementById('slate-resource-hub-data');
  var payload = { resources: [], canReview: false };
  try {
    payload = JSON.parse(dataEl ? dataEl.textContent : '{}') || payload;
  } catch (err) {}

  var state = {
    tab: 'library',
    query: '',
    filters: {},
    selectedId: null,
    sort: { key: 'vendor', dir: 'ascending' },
    resources: Array.isArray(payload.resources) ? payload.resources.slice() : [],
    canReview: !!payload.canReview
  };

  var els = {
    tabs: Array.prototype.slice.call(root.querySelectorAll('[data-rh-tab]')),
    library: root.querySelector('[data-rh-screen="library"]'),
    detail: root.querySelector('[data-rh-screen="detail"]'),
    queue: root.querySelector('[data-rh-screen="queue"]'),
    list: root.querySelector('[data-rh-list]'),
    search: root.querySelector('[data-rh-search]'),
    empty: root.querySelector('[data-rh-empty]'),
    clear: root.querySelector('[data-rh-clear]')
  };

  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function byId(id) {
    return state.resources.find(function (resource) {
      return resource.id === id;
    }) || null;
  }

  function statusPill(resource) {
    var key = resource.status_key;
    var cls = key === 'needs_review' ? 'rh-pill--parts' : key === 'reviewed' ? 'rh-pill--ready' : 'rh-pill--neutral';
    var dot = key === 'current' ? '' : '<span class="rh-pill__dot"></span>';
    return '<span class="rh-pill ' + cls + '">' + dot + esc(resource.status) + '</span>';
  }

  function docPill(resource) {
    return '<span class="rh-pill rh-pill--qc">' + esc(resource.doc_type) + '</span>';
  }

  function matchesTab(resource) {
    if (state.tab === 'slate') return resource.source_type === 'slate';
    if (state.tab === 'recent') return resource.updated_date >= '2026-04-01';
    return true;
  }

  function matchesFilters(resource) {
    var keys = Object.keys(state.filters);
    for (var i = 0; i < keys.length; i += 1) {
      var key = keys[i];
      if (state.filters[key] && resource[key] !== state.filters[key]) return false;
    }
    return true;
  }

  function matchesQuery(resource) {
    if (!state.query) return true;
    var haystack = [
      resource.sku,
      resource.title,
      resource.vendor,
      resource.doc_type,
      resource.chassis,
      resource.status
    ].join(' ').toLowerCase();
    return haystack.indexOf(state.query.toLowerCase()) !== -1;
  }

  function visibleResources() {
    return state.resources.filter(function (resource) {
      return matchesTab(resource) && matchesFilters(resource) && matchesQuery(resource);
    });
  }

  function updateTabCounts() {
    var counts = {
      library: state.resources.length,
      slate: state.resources.filter(function (resource) { return resource.source_type === 'slate'; }).length,
      queue: queueItems().length,
      recent: state.resources.filter(function (resource) { return resource.updated_date >= '2026-04-01'; }).length
    };

    els.tabs.forEach(function (button) {
      var key = button.getAttribute('data-rh-tab');
      var count = button.querySelector('.rh-tab__count');
      if (count && Object.prototype.hasOwnProperty.call(counts, key)) {
        count.textContent = String(counts[key]);
      }
    });
  }

  function setScreen(screen) {
    if (els.library) els.library.hidden = screen !== 'library';
    if (els.detail) els.detail.hidden = screen !== 'detail';
    if (els.queue) els.queue.hidden = screen !== 'queue';
  }

  function setTab(tab) {
    state.tab = tab;
    state.selectedId = null;
    els.tabs.forEach(function (button) {
      button.setAttribute('aria-current', button.getAttribute('data-rh-tab') === tab ? 'page' : 'false');
    });

    if (tab === 'queue') {
      setScreen('queue');
      renderQueue();
      updateTabCounts();
      return;
    }

    setScreen('library');
    renderList();
    updateTabCounts();
  }

  function resourceCard(resource) {
    return [
      '<li class="rh-card" tabindex="0" data-rh-resource="' + esc(resource.id) + '">',
      '<div class="rh-card__sku">' + esc(resource.sku) + '</div>',
      '<div class="rh-card__titlewrap">',
      '<h3 class="rh-card__title">' + esc(resource.title) + '</h3>',
      '<div class="rh-card__vendor">' + esc(resource.vendor) + '</div>',
      '</div>',
      '<div class="rh-card__pillrow">' + docPill(resource) + '</div>',
      '<div class="rh-card__chassis">' + esc(resource.chassis) + '</div>',
      '<div class="rh-card__updated">' + esc(resource.updated_label) + '</div>',
      '<div class="rh-card__review">' + statusPill(resource) + '</div>',
      '</li>'
    ].join('');
  }

  function renderList() {
    if (!els.list) return;
    var rows = visibleResources();
    els.list.innerHTML = rows.map(resourceCard).join('');
    if (els.empty) els.empty.hidden = rows.length !== 0;
    updateTabCounts();
  }

  function renderDetail(resource) {
    if (!els.detail) return;

    var notes = resource.notes && resource.notes.length
      ? resource.notes.map(function (note) { return '<li>' + esc(note) + '</li>'; }).join('')
      : '<li>No Slate notes have been attached yet.</li>';

    var attachments = resource.attachments && resource.attachments.length
      ? resource.attachments.map(function (attachment) {
          return [
            '<div class="rh-thumb">',
            '<div class="rh-thumb__img">' + esc(attachment.label) + '</div>',
            '<div class="rh-thumb__cap">',
            '<div class="rh-thumb__name">' + esc(attachment.name) + '</div>',
            '<div class="rh-thumb__meta">' + esc(attachment.meta) + '</div>',
            '</div>',
            '</div>'
          ].join('');
        }).join('')
      : '<div class="rh-empty"><h2 class="rh-empty__title">No Slate attachments yet</h2><p class="rh-empty__sub">Attachments can be added when the document storage workflow is connected.</p></div>';

    var related = (resource.related || []).map(byId).filter(Boolean);
    var relatedHtml = related.length
      ? related.map(function (item) {
          return [
            '<button class="rh-mini" type="button" data-rh-resource="' + esc(item.id) + '">',
            '<div class="rh-mini__sku">' + esc(item.sku) + '</div>',
            '<h3 class="rh-mini__title">' + esc(item.title) + '</h3>',
            '<div class="rh-mini__vendor">' + esc(item.vendor) + '</div>',
            '<div>' + docPill(item) + '</div>',
            '</button>'
          ].join('');
        }).join('')
      : '<div class="rh-empty"><h2 class="rh-empty__title">No related resources</h2><p class="rh-empty__sub">Related docs will appear here as the library grows.</p></div>';

    els.detail.innerHTML = [
      '<button class="rh-back" type="button" data-rh-back><span class="material-symbols-outlined">chevron_left</span>Back to library</button>',
      '<div class="rh-detail-head">',
      '<div class="rh-eyebrow">' + esc(resource.doc_type) + ' / ' + esc(resource.source_type === 'slate' ? 'Slate-authored' : 'vendor doc') + '</div>',
      '<h1 class="rh-detail-head__title">' + esc(resource.title) + '</h1>',
      '<div class="rh-detail-head__row">',
      '<span class="rh-detail-head__sku">' + esc(resource.sku) + '</span>',
      statusPill(resource),
      docPill(resource),
      '<span class="rh-pill rh-pill--neutral">' + esc(resource.status_key === 'needs_review' ? 'Pending' : 'Current') + '</span>',
      '</div>',
      '</div>',
      '<div class="rh-detail-grid">',
      '<aside class="rh-meta" aria-label="Document metadata">',
      metaRow('Vendor', resource.vendor),
      metaRow('Vendor revision', resource.vendor_revision, true),
      metaRow('Last Slate review', resource.last_review, true),
      metaRow('Chassis', resource.chassis),
      metaRow('Doc type', resource.doc_type),
      metaRow('Source', resource.source, true),
      '</aside>',
      '<section class="rh-pdf" aria-label="Document preview">',
      '<div class="rh-pdf__placeholder">',
      '<div class="rh-pdf__icon">PDF</div>',
      '<div class="rh-pdf__filename">' + esc(resource.file) + '</div>',
      '<div class="rh-mute" style="font-size:12px">Preview is ready for the document storage workflow.</div>',
      '</div>',
      '<div class="rh-pdf__footer">',
      '<span class="rh-mute" style="font-size:12px">Last updated ' + esc(resource.updated_date) + '</span>',
      '<button class="rh-btn rh-btn--primary" type="button" disabled>Open PDF</button>',
      '</div>',
      '</section>',
      '</div>',
      '<section class="rh-slate-notes" aria-labelledby="slate-notes-title">',
      '<div class="rh-slate-notes__eyebrow" id="slate-notes-title"><span class="material-symbols-outlined" style="font-size:14px">verified</span>Slate-authored notes for techs</div>',
      '<div class="rh-slate-notes__body"><ul>' + notes + '</ul></div>',
      '</section>',
      '<div class="rh-section-head"><h2 class="rh-section-head__title">Slate attachments</h2><span class="rh-section-head__count">' + esc(String((resource.attachments || []).length)) + ' files</span></div>',
      '<div class="rh-attach">' + attachments + '</div>',
      '<div class="rh-section-head"><h2 class="rh-section-head__title">Related resources</h2><span class="rh-section-head__count">' + esc(String(related.length)) + ' items</span></div>',
      '<div class="rh-related">' + relatedHtml + '</div>'
    ].join('');

    setScreen('detail');
  }

  function metaRow(label, value, mono) {
    return [
      '<div class="rh-meta__row">',
      '<div class="rh-meta__label">' + esc(label) + '</div>',
      '<div class="rh-meta__value' + (mono ? ' rh-meta__value--mono' : '') + '">' + esc(value) + '</div>',
      '</div>'
    ].join('');
  }

  function queueItems() {
    return state.resources.filter(function (resource) {
      return resource.status_key === 'needs_review';
    }).sort(function (a, b) {
      var key = state.sort.key;
      var aVal = String(a[key] || '').toLowerCase();
      var bVal = String(b[key] || '').toLowerCase();
      if (aVal < bVal) return state.sort.dir === 'ascending' ? -1 : 1;
      if (aVal > bVal) return state.sort.dir === 'ascending' ? 1 : -1;
      return 0;
    });
  }

  function renderQueue() {
    if (!els.queue) return;
    updateTabCounts();
    var rows = queueItems();
    if (!rows.length) {
      els.queue.innerHTML = '<section class="rh-empty" role="status"><div class="rh-empty__icon"><span class="material-symbols-outlined">task_alt</span></div><h2 class="rh-empty__title">The review queue is clear</h2><p class="rh-empty__sub">Vendor docs that need Slate review will appear here.</p></section>';
      return;
    }

    var cards = rows.map(function (resource) {
      return [
        '<article class="rh-queue-card">',
        '<div class="rh-queue-card__head">',
        '<div><div class="rh-queue-card__vendor">' + esc(resource.vendor) + '</div>',
        '<h3 class="rh-queue-card__title">' + esc(resource.title) + '</h3>',
        '<div class="rh-queue-card__sku">' + esc(resource.sku) + '</div></div>',
        statusPill(resource),
        '</div>',
        '<div class="rh-queue-card__rev">' + esc(resource.vendor_revision) + '</div>',
        '<div class="rh-queue-card__action"><button class="rh-btn rh-btn--primary rh-btn--sm" type="button" data-rh-reviewed="' + esc(resource.id) + '">Mark reviewed</button></div>',
        '</article>'
      ].join('');
    }).join('');

    var tableRows = rows.map(function (resource) {
      return [
        '<tr>',
        '<td class="rh-cell--vendor">' + esc(resource.vendor) + '</td>',
        '<td class="rh-cell--mono">' + esc(resource.sku) + '</td>',
        '<td class="rh-cell--title">' + esc(resource.title) + '</td>',
        '<td class="rh-cell--mono">' + esc(resource.vendor_revision) + '</td>',
        '<td>' + statusPill(resource) + '</td>',
        '<td class="rh-cell--action"><button class="rh-btn rh-btn--primary rh-btn--sm" type="button" data-rh-reviewed="' + esc(resource.id) + '">Mark reviewed</button></td>',
        '</tr>'
      ].join('');
    }).join('');

    els.queue.innerHTML = [
      '<div class="rh-page__head">',
      '<div><div class="rh-eyebrow">Engineering / admin</div><h2 class="rh-page__title">Admin queue</h2><p class="rh-page__sub">Vendor docs awaiting Slate review. Mark reviewed once notes are attached and the doc is cleared for the floor.</p></div>',
      '</div>',
      '<div class="rh-queue__cards">' + cards + '</div>',
      '<div class="rh-queue__table">',
      '<table class="rh-table">',
      '<thead><tr>',
      queueHead('vendor', 'Vendor'),
      queueHead('sku', 'SKU'),
      queueHead('title', 'Title'),
      queueHead('vendor_revision', 'Vendor revision'),
      '<th>Status</th><th class="rh-cell--action">&nbsp;</th>',
      '</tr></thead>',
      '<tbody>' + tableRows + '</tbody>',
      '</table>',
      '</div>'
    ].join('');
  }

  function queueHead(key, label) {
    var active = state.sort.key === key;
    var arrow = active ? (state.sort.dir === 'ascending' ? 'up' : 'down') : 'unfold_more';
    return '<th data-rh-sort="' + esc(key) + '" aria-sort="' + (active ? esc(state.sort.dir) : 'none') + '">' + esc(label) + ' <span class="material-symbols-outlined rh-sort__arrow">' + arrow + '</span></th>';
  }

  function markReviewed(id) {
    var resource = byId(id);
    if (!resource || !state.canReview) return;
    resource.status_key = 'reviewed';
    resource.status = 'Reviewed by Slate';
    resource.last_review = 'Reviewed in this session';
    renderQueue();
    renderList();
  }

  function openResource(id) {
    var resource = byId(id);
    if (!resource) return;
    state.selectedId = id;
    renderDetail(resource);
  }

  root.addEventListener('click', function (event) {
    var tab = event.target.closest('[data-rh-tab]');
    if (tab) {
      setTab(tab.getAttribute('data-rh-tab'));
      return;
    }

    var jump = event.target.closest('[data-rh-tab-jump]');
    if (jump) {
      setTab(jump.getAttribute('data-rh-tab-jump'));
      return;
    }

    var chip = event.target.closest('[data-rh-filter]');
    if (chip) {
      var filter = chip.getAttribute('data-rh-filter');
      var value = chip.getAttribute('data-rh-value');
      var pressed = chip.getAttribute('aria-pressed') === 'true';
      Array.prototype.slice.call(root.querySelectorAll('[data-rh-filter="' + filter + '"]')).forEach(function (other) {
        other.setAttribute('aria-pressed', 'false');
        var caret = other.querySelector('.rh-chip__caret');
        if (caret) caret.textContent = '+';
      });
      if (pressed) {
        delete state.filters[filter];
      } else {
        state.filters[filter] = value;
        chip.setAttribute('aria-pressed', 'true');
        var activeCaret = chip.querySelector('.rh-chip__caret');
        if (activeCaret) activeCaret.textContent = 'x';
      }
      renderList();
      return;
    }

    var clear = event.target.closest('[data-rh-clear]');
    if (clear) {
      state.query = '';
      state.filters = {};
      if (els.search) els.search.value = '';
      Array.prototype.slice.call(root.querySelectorAll('[data-rh-filter]')).forEach(function (chipButton) {
        chipButton.setAttribute('aria-pressed', 'false');
        var caret = chipButton.querySelector('.rh-chip__caret');
        if (caret) caret.textContent = '+';
      });
      renderList();
      return;
    }

    var back = event.target.closest('[data-rh-back]');
    if (back) {
      state.selectedId = null;
      setScreen('library');
      renderList();
      return;
    }

    var reviewed = event.target.closest('[data-rh-reviewed]');
    if (reviewed) {
      markReviewed(reviewed.getAttribute('data-rh-reviewed'));
      return;
    }

    var sort = event.target.closest('[data-rh-sort]');
    if (sort) {
      var key = sort.getAttribute('data-rh-sort');
      if (state.sort.key === key) {
        state.sort.dir = state.sort.dir === 'ascending' ? 'descending' : 'ascending';
      } else {
        state.sort.key = key;
        state.sort.dir = 'ascending';
      }
      renderQueue();
      return;
    }

    var card = event.target.closest('[data-rh-resource]');
    if (card) {
      openResource(card.getAttribute('data-rh-resource'));
    }
  });

  root.addEventListener('keydown', function (event) {
    var card = event.target.closest('[data-rh-resource]');
    if (!card) return;
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      openResource(card.getAttribute('data-rh-resource'));
    }
  });

  if (els.search) {
    els.search.addEventListener('input', function () {
      state.query = els.search.value.trim();
      renderList();
    });
  }

  renderList();
}());
