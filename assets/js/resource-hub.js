(function () {
  'use strict';

  var root = document.querySelector('.rh-app');
  if (!root) return;

  var titleEl = document.getElementById('ops-page-title');
  if (titleEl) titleEl.textContent = 'Resource hub';

  var STORAGE_KEY = 'slate_ops_resource_hub_resources';
  var dataEl = document.getElementById('slate-resource-hub-data');
  var payload = { resources: [], canReview: false };
  try {
    payload = JSON.parse(dataEl ? dataEl.textContent : '{}') || payload;
  } catch (err) {}

  function storedResources() {
    try {
      var saved = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || '[]');
      return Array.isArray(saved) ? saved : [];
    } catch (err) {
      return [];
    }
  }

  function mergeResources(base, saved) {
    var seen = {};
    var merged = [];
    saved.concat(base).forEach(function (resource) {
      if (!resource || !resource.id || seen[resource.id]) return;
      seen[resource.id] = true;
      merged.push(resource);
    });
    return merged;
  }

  function persistResources() {
    if (!state.canReview) return;
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(state.resources));
    } catch (err) {}
  }

  var state = {
    tab: 'library',
    query: '',
    filters: {},
    selectedId: null,
    reviewId: null,
    source: 'vendor',
    addFile: null,
    sort: { key: 'vendor', dir: 'ascending' },
    resources: mergeResources(Array.isArray(payload.resources) ? payload.resources.slice() : [], storedResources()),
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
    clear: root.querySelector('[data-rh-clear]'),
    addModal: root.querySelector('[data-rh-add-modal]'),
    addForm: root.querySelector('[data-rh-add-form]'),
    fileInput: root.querySelector('[data-rh-file]'),
    fileTitle: root.querySelector('[data-rh-file-title]'),
    fileSub: root.querySelector('[data-rh-file-sub]'),
    sourceHint: root.querySelector('[data-rh-source-hint]'),
    addSubmit: root.querySelector('[data-rh-add-submit]'),
    reviewDrawer: root.querySelector('[data-rh-review-drawer]'),
    reviewSku: root.querySelector('[data-rh-review-sku]'),
    reviewTitle: root.querySelector('[data-rh-review-title]'),
    reviewPills: root.querySelector('[data-rh-review-pills]'),
    reviewBody: root.querySelector('[data-rh-review-body]')
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

  function todayIso() {
    return new Date().toISOString().slice(0, 10);
  }

  function updatedLabel() {
    var now = new Date();
    return 'Updated ' + now.toLocaleString('en-US', { month: 'short', day: '2-digit' });
  }

  function formatBytes(bytes) {
    if (!bytes) return '0 KB';
    var units = ['bytes', 'KB', 'MB', 'GB'];
    var size = bytes;
    var unit = 0;
    while (size >= 1024 && unit < units.length - 1) {
      size = size / 1024;
      unit += 1;
    }
    return (unit === 0 ? String(size) : size.toFixed(size >= 10 ? 1 : 2)) + ' ' + units[unit];
  }

  function fileLabel(file) {
    if (!file) return 'No file selected';
    var type = file.type || '';
    if (type.indexOf('image/') === 0) return 'image - ' + formatBytes(file.size);
    if (type.indexOf('video/') === 0) return 'video - ' + formatBytes(file.size);
    if (type === 'application/pdf' || /\.pdf$/i.test(file.name)) return 'pdf - ' + formatBytes(file.size);
    return 'file - ' + formatBytes(file.size);
  }

  function showNotice(message) {
    var existing = root.querySelector('.rh-notice');
    if (existing) existing.remove();
    var notice = document.createElement('div');
    notice.className = 'rh-notice';
    notice.setAttribute('role', 'status');
    notice.textContent = message;
    root.appendChild(notice);
    window.setTimeout(function () {
      if (notice.parentNode) notice.parentNode.removeChild(notice);
    }, 2600);
  }

  function statusPill(resource) {
    var key = resource.status_key;
    var cls = key === 'needs_review' ? 'pill--warn' : key === 'reviewed' ? 'pill--info' : 'pill--neutral';
    var dot = key === 'current' ? '' : '<span class="rh-pill__dot"></span>';
    return '<span class="rh-pill ' + cls + '">' + dot + esc(resource.status) + '</span>';
  }

  function docPill(resource) {
    return '<span class="rh-pill pill--info">' + esc(resource.doc_type) + '</span>';
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

  function setSource(source) {
    state.source = source === 'slate' ? 'slate' : 'vendor';
    Array.prototype.slice.call(root.querySelectorAll('[data-rh-source]')).forEach(function (button) {
      button.setAttribute('aria-pressed', button.getAttribute('data-rh-source') === state.source ? 'true' : 'false');
    });
    if (els.sourceHint) {
      els.sourceHint.innerHTML = state.source === 'vendor'
        ? 'Vendor doc lands in the review queue with status <em>Needs Slate review</em>.'
        : 'Slate-authored docs publish straight to the library, marked <em>Reviewed by Slate</em>.';
    }
    if (els.addSubmit) {
      els.addSubmit.textContent = state.source === 'vendor' ? 'Add to queue' : 'Publish to library';
    }
    var vendor = root.querySelector('#rh-add-vendor');
    if (vendor && state.source === 'slate') vendor.value = 'Slate-authored';
  }

  function setAddFile(file) {
    state.addFile = file || null;
    if (els.fileTitle) els.fileTitle.textContent = file ? file.name : 'Drop a PDF, image, or video here';
    if (els.fileSub) els.fileSub.textContent = file ? fileLabel(file) : 'Up to 25 MB - pdf, jpg, png, mp4';
  }

  function selectedChassis() {
    var selected = Array.prototype.slice.call(root.querySelectorAll('[data-rh-chassis][aria-pressed="true"]'))
      .map(function (chip) { return chip.getAttribute('data-rh-chassis'); });
    return selected.length ? selected.join(', ') : 'All chassis';
  }

  function resetAddForm() {
    state.source = 'vendor';
    setSource('vendor');
    setAddFile(null);
    if (els.fileInput) els.fileInput.value = '';
    if (els.addForm) els.addForm.reset();
    Array.prototype.slice.call(root.querySelectorAll('[data-rh-chassis]')).forEach(function (chip, index) {
      chip.setAttribute('aria-pressed', index === 0 ? 'true' : 'false');
      renderMultiChip(chip);
    });
  }

  function openAddModal() {
    if (!els.addModal || !state.canReview) return;
    resetAddForm();
    els.addModal.hidden = false;
  }

  function closeOverlays() {
    if (els.addModal) els.addModal.hidden = true;
    if (els.reviewDrawer) els.reviewDrawer.hidden = true;
    state.reviewId = null;
  }

  function formValue(name) {
    if (!els.addForm) return '';
    var field = els.addForm.querySelector('[name="' + name + '"]');
    return field ? field.value.trim() : '';
  }

  function addResource(draftOnly) {
    if (!state.canReview || !els.addForm) return;
    if (!els.addForm.reportValidity()) return;
    if (!draftOnly && !state.addFile) {
      showNotice('Choose a file before adding the resource.');
      return;
    }

    var sourceType = state.source === 'slate' ? 'slate' : 'vendor';
    var sku = formValue('sku').toUpperCase();
    var title = formValue('title');
    var notesValue = formValue('notes');
    var fileName = state.addFile ? state.addFile.name : sku + '_resource.pdf';
    var id = sku.toLowerCase().replace(/[^a-z0-9]+/g, '-') + '-' + Date.now().toString(36);
    var statusKey = draftOnly ? 'draft' : (sourceType === 'vendor' ? 'needs_review' : 'reviewed');
    var status = draftOnly ? 'Draft' : (sourceType === 'vendor' ? 'Needs Slate review' : 'Reviewed by Slate');
    var resource = {
      id: id,
      sku: sku,
      title: title,
      vendor: sourceType === 'slate' ? 'Slate-authored' : formValue('vendor'),
      doc_type: formValue('doc_type'),
      chassis: selectedChassis(),
      updated_label: updatedLabel(),
      updated_date: todayIso(),
      status: status,
      status_key: statusKey,
      source_type: sourceType,
      vendor_revision: formValue('vendor_revision') || 'Rev. pending - ' + todayIso(),
      last_review: sourceType === 'slate' && !draftOnly ? todayIso() + ' - Slate admin' : 'Not reviewed',
      source: sourceType === 'slate' ? 'Slate-authored upload' : 'Admin upload',
      file: fileName + ' - ' + (state.addFile ? fileLabel(state.addFile) : 'file pending'),
      notes: notesValue ? notesValue.split(/\n+/).filter(Boolean) : [],
      attachments: [],
      related: []
    };

    state.resources.unshift(resource);
    persistResources();
    closeOverlays();
    renderList();
    renderQueue();
    updateTabCounts();
    showNotice(draftOnly ? 'Draft added to the library.' : (sourceType === 'vendor' ? 'Resource added to the review queue.' : 'Resource published to the library.'));
    if (sourceType === 'vendor' && !draftOnly) setTab('queue');
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
      '<span class="rh-pill pill--neutral">' + esc(resource.status_key === 'needs_review' ? 'Pending' : 'Current') + '</span>',
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
        '<div class="rh-queue-card__action"><button class="rh-btn rh-btn--primary rh-btn--sm" type="button" data-rh-review="' + esc(resource.id) + '">Review</button></div>',
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
        '<td class="rh-cell--action"><button class="rh-btn rh-btn--primary rh-btn--sm" type="button" data-rh-review="' + esc(resource.id) + '">Review</button></td>',
        '</tr>'
      ].join('');
    }).join('');

    els.queue.innerHTML = [
      '<div class="rh-page__head">',
      '<div><div class="rh-eyebrow">Engineering / admin</div><h2 class="rh-page__title">Admin queue</h2><p class="rh-page__sub">Vendor docs awaiting Slate review. Add notes, attach floor references, then publish.</p></div>',
      '<div class="rh-page__actions"><button class="slate-btn slate-btn--accent slate-btn--sm" type="button" data-rh-add-open><span class="material-symbols-outlined" aria-hidden="true">add</span>Add resource</button></div>',
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

  function checklistItem(label, checked) {
    return [
      '<button class="rh-checklist__item" type="button" aria-checked="' + (checked ? 'true' : 'false') + '" role="checkbox" data-rh-check>',
      '<span class="rh-checklist__box">' + (checked ? '<span class="material-symbols-outlined" style="font-size:13px">check</span>' : '') + '</span>',
      '<span class="rh-checklist__label">' + esc(label) + '</span>',
      '</button>'
    ].join('');
  }

  function attachmentTile(attachment) {
    return [
      '<div class="rh-attach-tile rh-attach-tile--filled">',
      '<div class="rh-attach-tile__img">' + esc(attachment.label || 'Attachment') + '</div>',
      '<div class="rh-attach-tile__cap">' + esc(attachment.name || 'Attachment') + '</div>',
      '</div>'
    ].join('');
  }

  function renderReviewDrawer(resource) {
    if (!els.reviewDrawer || !els.reviewBody || !resource) return;
    state.reviewId = resource.id;
    if (els.reviewSku) els.reviewSku.textContent = resource.sku;
    if (els.reviewTitle) els.reviewTitle.textContent = resource.title;
    if (els.reviewPills) {
      els.reviewPills.innerHTML = statusPill(resource) + docPill(resource) + '<span class="rh-pill pill--neutral">' + esc(resource.vendor + ' - ' + resource.vendor_revision) + '</span>';
    }

    var notesText = (resource.notes || []).join('\n\n');
    var attachments = (resource.attachments || []).map(attachmentTile).join('');
    els.reviewBody.innerHTML = [
      '<div class="rh-drawer__pdf">',
      '<div class="rh-drawer__pdf-thumb">',
      '<div class="rh-pdf__icon" aria-hidden="true">PDF</div>',
      '<div class="rh-drawer__pdf-name">' + esc(resource.file || 'Resource file') + '</div>',
      '<div class="rh-mute" style="font-size:11px">' + esc(resource.source || 'Admin upload') + '</div>',
      '<button class="rh-btn rh-btn--sm" type="button" disabled>Open file</button>',
      '</div>',
      '<div class="rh-mute" style="font-size:11px">Uploaded ' + esc(resource.updated_date || todayIso()) + '</div>',
      '</div>',
      '<div class="rh-drawer__notes">',
      '<div class="rh-field">',
      '<label class="rh-field__label" for="rh-review-notes">Slate notes for techs</label>',
      '<textarea class="rh-textarea" id="rh-review-notes" data-rh-review-notes rows="7" placeholder="Hardware variants, sealant call-outs, vendor errata, references to attached photos...">' + esc(notesText) + '</textarea>',
      '</div>',
      '<div class="rh-field">',
      '<span class="rh-field__label">QC checks</span>',
      '<div class="rh-checklist">',
      checklistItem('Torque values verified against current production hardware', true),
      checklistItem('Hardware variants noted', notesText.length > 0),
      checklistItem('Sealant / adhesive specified', false),
      checklistItem('Photos / diagrams attached for tricky steps', (resource.attachments || []).length > 0),
      '</div>',
      '</div>',
      '<div class="rh-field">',
      '<span class="rh-field__label">Slate attachments</span>',
      '<div class="rh-attach-grid" data-rh-review-attachments>',
      attachments,
      '<label class="rh-attach-tile" data-rh-attach-drop>',
      '<input class="rh-file-input" type="file" data-rh-attach-file accept=".pdf,.jpg,.jpeg,.png,.mp4,.mov,image/*,application/pdf,video/*">',
      '<span class="material-symbols-outlined" aria-hidden="true">add</span>',
      '<span>Add photo or diagram</span>',
      '</label>',
      '</div>',
      '</div>',
      '</div>'
    ].join('');
    els.reviewDrawer.hidden = false;
  }

  function openReview(id) {
    var resource = byId(id);
    if (!resource || !state.canReview) return;
    renderReviewDrawer(resource);
  }

  function saveReviewDraft() {
    var resource = byId(state.reviewId);
    var textarea = root.querySelector('[data-rh-review-notes]');
    if (!resource || !textarea) return;
    resource.notes = textarea.value.split(/\n+/).map(function (line) { return line.trim(); }).filter(Boolean);
    resource.updated_date = todayIso();
    resource.updated_label = updatedLabel();
    persistResources();
    renderList();
    renderQueue();
    showNotice('Review draft saved.');
  }

  function approveReview() {
    var resource = byId(state.reviewId);
    if (!resource) return;
    saveReviewDraft();
    resource.status_key = 'reviewed';
    resource.status = 'Reviewed by Slate';
    resource.last_review = todayIso() + ' - Slate admin';
    persistResources();
    closeOverlays();
    renderList();
    renderQueue();
    updateTabCounts();
    showNotice('Resource approved and published.');
  }

  function addReviewAttachment(file) {
    var resource = byId(state.reviewId);
    if (!resource || !file) return;
    resource.attachments = resource.attachments || [];
    resource.attachments.push({
      name: file.name,
      meta: fileLabel(file),
      label: file.type && file.type.indexOf('image/') === 0 ? 'Image' : file.type && file.type.indexOf('video/') === 0 ? 'Video' : 'File'
    });
    persistResources();
    renderReviewDrawer(resource);
    showNotice('Attachment added to review draft.');
  }

  function renderMultiChip(chip) {
    var on = chip.getAttribute('aria-pressed') === 'true';
    var x = chip.querySelector('.rh-multi__chip__x');
    if (on && !x) {
      var span = document.createElement('span');
      span.className = 'rh-multi__chip__x';
      span.textContent = 'x';
      chip.appendChild(document.createTextNode(' '));
      chip.appendChild(span);
    }
    if (!on && x) x.remove();
  }

  function openResource(id) {
    var resource = byId(id);
    if (!resource) return;
    state.selectedId = id;
    renderDetail(resource);
  }

  root.addEventListener('click', function (event) {
    var addOpen = event.target.closest('[data-rh-add-open]');
    if (addOpen) {
      openAddModal();
      return;
    }

    var dismiss = event.target.closest('[data-rh-dismiss]');
    if (dismiss) {
      closeOverlays();
      return;
    }

    if (event.target === els.addModal || event.target === els.reviewDrawer) {
      closeOverlays();
      return;
    }

    var source = event.target.closest('[data-rh-source]');
    if (source) {
      setSource(source.getAttribute('data-rh-source'));
      return;
    }

    var multi = event.target.closest('[data-rh-chassis]');
    if (multi) {
      var on = multi.getAttribute('aria-pressed') === 'true';
      multi.setAttribute('aria-pressed', on ? 'false' : 'true');
      renderMultiChip(multi);
      return;
    }

    var saveDraft = event.target.closest('[data-rh-save-draft]');
    if (saveDraft) {
      addResource(true);
      return;
    }

    var addSubmit = event.target.closest('[data-rh-add-submit]');
    if (addSubmit) {
      addResource(false);
      return;
    }

    var review = event.target.closest('[data-rh-review]');
    if (review) {
      openReview(review.getAttribute('data-rh-review'));
      return;
    }

    var reviewSave = event.target.closest('[data-rh-review-save]');
    if (reviewSave) {
      saveReviewDraft();
      return;
    }

    var requestChanges = event.target.closest('[data-rh-request-changes]');
    if (requestChanges) {
      saveReviewDraft();
      showNotice('Vendor change request noted.');
      return;
    }

    var approve = event.target.closest('[data-rh-review-approve]');
    if (approve) {
      approveReview();
      return;
    }

    var check = event.target.closest('[data-rh-check]');
    if (check) {
      var checked = check.getAttribute('aria-checked') === 'true';
      check.setAttribute('aria-checked', checked ? 'false' : 'true');
      var box = check.querySelector('.rh-checklist__box');
      if (box) box.innerHTML = checked ? '' : '<span class="material-symbols-outlined" style="font-size:13px">check</span>';
      return;
    }

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

  if (els.fileInput) {
    els.fileInput.addEventListener('change', function () {
      setAddFile(els.fileInput.files && els.fileInput.files[0] ? els.fileInput.files[0] : null);
    });
  }

  var drop = root.querySelector('[data-rh-drop]');
  if (drop) {
    ['dragenter', 'dragover'].forEach(function (name) {
      drop.addEventListener(name, function (event) {
        event.preventDefault();
        drop.classList.add('is-dragging');
      });
    });
    ['dragleave', 'drop'].forEach(function (name) {
      drop.addEventListener(name, function (event) {
        event.preventDefault();
        drop.classList.remove('is-dragging');
      });
    });
    drop.addEventListener('drop', function (event) {
      var file = event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0];
      if (file) setAddFile(file);
    });
  }

  root.addEventListener('change', function (event) {
    var attachmentInput = event.target.closest('[data-rh-attach-file]');
    if (attachmentInput && attachmentInput.files && attachmentInput.files[0]) {
      addReviewAttachment(attachmentInput.files[0]);
    }
  });

  renderList();
}());
