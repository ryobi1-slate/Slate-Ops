/*
 * Slate Ops — Quality module front-end.
 *
 * Three views are mounted off the same root .oq element:
 *   - dashboard  → filters and search the server-rendered job list
 *   - job        → handles supervisor review actions
 *   - form       → renders the responsive form runner (mobile stepper,
 *                  tablet two-panel)
 *
 * Vanilla JS only. No jQuery. All API calls go through the REST nonce.
 */
(function () {
  'use strict';

  var settings = window.slateOpsQuality || {};
  var API   = (settings.api && settings.api.root)  || '/wp-json/slate-ops/v1';
  var NONCE = (settings.api && settings.api.nonce) || '';
  var CAPS  = (settings.user && settings.user.caps) || {};

  function api(method, path, body, opts) {
    opts = opts || {};
    var headers = { 'X-WP-Nonce': NONCE };
    var init = { method: method, headers: headers, credentials: 'same-origin' };
    if (body instanceof FormData) {
      init.body = body;
    } else if (body) {
      headers['Content-Type'] = 'application/json';
      init.body = JSON.stringify(body);
    }
    return fetch(API + path, init).then(function (r) {
      return r.json().then(function (data) {
        if (!r.ok) {
          var err = new Error(data && data.message ? data.message : 'Request failed');
          err.payload = data;
          err.status  = r.status;
          throw err;
        }
        return data;
      });
    });
  }

  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (k) {
        if (k === 'class') node.className = attrs[k];
        else if (k === 'html') node.innerHTML = attrs[k];
        else if (k.indexOf('on') === 0) node.addEventListener(k.slice(2).toLowerCase(), attrs[k]);
        else if (k === 'data' && typeof attrs[k] === 'object') {
          Object.keys(attrs[k]).forEach(function (dk) { node.dataset[dk] = attrs[k][dk]; });
        } else if (attrs[k] != null) {
          node.setAttribute(k, attrs[k]);
        }
      });
    }
    if (children) {
      if (!Array.isArray(children)) children = [children];
      children.forEach(function (c) {
        if (c == null) return;
        if (typeof c === 'string') node.appendChild(document.createTextNode(c));
        else node.appendChild(c);
      });
    }
    return node;
  }

  function icon(name) {
    return el('span', { class: 'material-symbols-outlined' }, name);
  }

  function pill(status) {
    var labels = {
      not_started: 'Not Started', in_progress: 'In Progress',
      submitted: 'Submitted', needs_correction: 'Needs Correction',
      passed: 'Passed', locked: 'Locked'
    };
    var cls = status.replace(/_/g, '-');
    return el('span', { class: 'oq-pill oq-pill--' + cls }, labels[status] || status);
  }

  // ── Dashboard view ───────────────────────────────────────────────────
  function initDashboard(root) {
    var search = root.querySelector('#oq-search-input');
    var rows   = root.querySelectorAll('#oq-job-table tr.oq-row');
    var count  = root.querySelector('#oq-row-count');
    var buckets = root.querySelectorAll('.oq-bucket');
    var activeBucket = null;

    function apply() {
      var q = (search.value || '').toLowerCase().trim();
      var visible = 0;
      rows.forEach(function (tr) {
        var hay = tr.dataset.search || '';
        var st  = tr.dataset.status || '';
        var hit = (q === '' || hay.indexOf(q) !== -1)
               && (!activeBucket || st === activeBucket);
        tr.style.display = hit ? '' : 'none';
        if (hit) visible++;
      });
      if (count) count.textContent = visible;
    }

    if (search) search.addEventListener('input', apply);

    buckets.forEach(function (b) {
      b.addEventListener('click', function () {
        var bucket = b.dataset.bucket;
        if (activeBucket === bucket) {
          activeBucket = null;
          buckets.forEach(function (x) { x.classList.remove('is-active'); });
        } else {
          activeBucket = bucket;
          buckets.forEach(function (x) { x.classList.toggle('is-active', x === b); });
        }
        apply();
      });
    });
  }

  // ── Job review (supervisor) ─────────────────────────────────────────
  function initJobReview(root) {
    var jobId = parseInt(root.dataset.jobId, 10);
    if (!jobId) return;

    var select   = root.querySelector('#oq-review-form');
    var note     = root.querySelector('#oq-review-note');
    var btnPass  = root.querySelector('[data-action="review-pass"]');
    var btnFail  = root.querySelector('[data-action="review-fail"]');
    var btnUnlock = root.querySelector('[data-action="unlock"]');

    function busy(b, on) { if (b) { b.disabled = on; b.style.opacity = on ? '.6' : ''; } }

    function reload() { setTimeout(function () { window.location.reload(); }, 250); }

    if (btnPass) btnPass.addEventListener('click', function () {
      if (!select) return;
      busy(btnPass, true);
      api('POST', '/quality/jobs/' + jobId + '/forms/' + select.value + '/review', {
        decision: 'passed',
        note: note ? note.value : ''
      }).then(reload).catch(function (e) {
        busy(btnPass, false);
        window.alert(e.message);
      });
    });

    if (btnFail) btnFail.addEventListener('click', function () {
      if (!select) return;
      var reason = note && note.value.trim();
      if (!reason) { window.alert('Add correction notes so the tech knows what to fix.'); return; }
      busy(btnFail, true);
      api('POST', '/quality/jobs/' + jobId + '/forms/' + select.value + '/review', {
        decision: 'needs_correction',
        note: reason
      }).then(reload).catch(function (e) {
        busy(btnFail, false);
        window.alert(e.message);
      });
    });

    if (btnUnlock) btnUnlock.addEventListener('click', function () {
      if (!select) return;
      var reason = window.prompt('Reason to unlock this form?');
      if (!reason || !reason.trim()) return;
      busy(btnUnlock, true);
      api('POST', '/quality/jobs/' + jobId + '/forms/' + select.value + '/unlock', {
        reason: reason
      }).then(reload).catch(function (e) {
        busy(btnUnlock, false);
        window.alert(e.message);
      });
    });
  }

  // ── Form runner ─────────────────────────────────────────────────────
  function initRunner(root) {
    var host = root.querySelector('[data-runner]');
    if (!host) return;
    var raw  = host.getAttribute('data-initial') || '{}';
    var data;
    try { data = JSON.parse(raw); } catch (e) { data = {}; }
    if (!data.template) return;

    var jobId = (data.job && data.job.job_id) || parseInt(root.dataset.jobId, 10);
    var code  = data.template.code;

    var state = {
      stepIdx: 0,
      template: data.template,
      job: data.job,
      row: data.row || { status: 'not_started', payload: {}, photos: {} },
      activeItem: null,    // { sectionKey, itemKey }
      activeSlot: null,    // photo slot key
      saving: false,
      photoCache: {},      // slot → [{attachment_id, url, thumb_url}]
    };

    // Hydrate via REST so we (a) get the freshest template from the live
    // registry — not whatever was baked into the page HTML at render time
    // (defeats stale page caches), and (b) get photo URLs for any
    // attachments already on the row.
    api('GET', '/quality/jobs/' + jobId + '/forms/' + code).then(function (res) {
      if (res && res.template) state.template = res.template;
      state.row        = res.row || state.row;
      state.photos     = res.photos || {};
      state.photoCache = res.photos || {};
      // If the live registry no longer contains the item the user was
      // looking at, reset the active item to the first one in the
      // current template so the tablet detail pane doesn't blank out.
      if (state.activeItem) {
        var found = (state.template.sections || []).some(function (s) {
          return s.key === state.activeItem.sectionKey
              && (s.items || []).some(function (i) { return i.key === state.activeItem.itemKey; });
        });
        if (!found) state.activeItem = null;
      }
      render();
    }).catch(function () { render(); });

    // STEPS — mobile stepper order matches the spec
    var STEPS = [
      { key: 'vehicle',   label: 'Vehicle' },
      { key: 'checklist', label: 'Checklist' },
      { key: 'photos',    label: 'Photos' },
      { key: 'notes',     label: 'Notes' },
      { key: 'sign',      label: 'Sign' },
    ];

    function layoutMode() {
      // < 768 → stepper; 768-1199 → tablet two-panel; 1200+ → tablet (still
      // two-panel but wider). Desktop "review" lives on the job page.
      return window.innerWidth >= 768 ? 'tablet' : 'mobile';
    }

    function ensureChecklist() {
      state.row.payload = state.row.payload || {};
      if (!isPlainObject(state.row.payload.checklist)) {
        state.row.payload.checklist = {};
      }
      state.template.sections.forEach(function (s) {
        if (!isPlainObject(state.row.payload.checklist[s.key])) {
          state.row.payload.checklist[s.key] = {};
        }
      });
    }

    function isPlainObject(value) {
      return !!value && typeof value === 'object' && !Array.isArray(value);
    }

    function draftBody() {
      return JSON.parse(JSON.stringify({
        checklist: state.row.payload.checklist || {},
        vehicle: state.row.payload.vehicle || {},
        notes: state.row.payload.notes || ''
      }));
    }

    function setResult(sectionKey, itemKey, result) {
      ensureChecklist();
      var bucket = state.row.payload.checklist[sectionKey];
      bucket[itemKey] = bucket[itemKey] || {};
      bucket[itemKey].result = result;
      saveDraft();
      render();
    }

    function setNote(sectionKey, itemKey, note) {
      ensureChecklist();
      var bucket = state.row.payload.checklist[sectionKey];
      bucket[itemKey] = bucket[itemKey] || {};
      bucket[itemKey].note = note;
      saveDraft();
    }

    var saveTimer = null;
    var saveSeq = 0;
    function saveDraft() {
      var seq = ++saveSeq;
      var body = draftBody();
      if (saveTimer) clearTimeout(saveTimer);
      saveTimer = setTimeout(function () {
        api('POST', '/quality/jobs/' + jobId + '/forms/' + code + '/draft', body)
          .then(function (row) {
            if (seq === saveSeq) state.row = row;
          }).catch(function () { /* keep local optimistic state */ });
      }, 400);
    }

    function uploadPhoto(slotKey, file) {
      var fd = new FormData();
      fd.append('slot', slotKey);
      fd.append('file', file);
      return api('POST', '/quality/jobs/' + jobId + '/forms/' + code + '/photos', fd)
        .then(function (res) {
          state.row = res.row || state.row;
          state.photoCache = res.photos || state.photoCache;
          render();
        });
    }

    // ── Rendering ─────────────────────────────────────────────────
    function render() {
      ensureChecklist();
      host.innerHTML = '';
      host.dataset.layout = layoutMode() === 'tablet' ? 'tablet' : 'mobile';

      var header = renderHeader();
      host.appendChild(header);

      if (layoutMode() === 'tablet') {
        renderTablet();
      } else {
        renderMobile();
      }
    }

    function renderHeader() {
      var head = el('div', { class: 'oq-rh' }, [
        el('div', { class: 'oq-rh__left' }, [
          el('div', { class: 'oq-eyebrow' }, state.template.eyebrow),
          el('div', { class: 'oq-rh__title' }, state.template.name + ' · Q-' + jobId)
        ]),
        el('div', { class: 'oq-rh__right' }, [
          pill(state.row.status || 'not_started'),
          el('a', { class: 'oq-btn oq-btn--ghost', href: '/ops/quality/job/' + jobId }, [
            icon('close'),
            'Close'
          ])
        ])
      ]);
      return head;
    }

    function stepDots() {
      var wrap = el('div', { class: 'oq-steps' });
      STEPS.forEach(function (s, i) {
        var cls = 'oq-step';
        if (i < state.stepIdx) cls += ' is-done';
        if (i === state.stepIdx) cls += ' is-active';
        wrap.appendChild(el('div', { class: cls, onclick: function () { state.stepIdx = i; render(); } }, [
          el('div', { class: 'oq-step__bar' }),
          el('div', { class: 'oq-step__label' }, s.label)
        ]));
      });
      return wrap;
    }

    function renderMobile() {
      host.appendChild(stepDots());
      var body = el('div', { class: 'oq-rb' });
      var step = STEPS[state.stepIdx].key;
      if (step === 'vehicle')   body.appendChild(renderVehicleStep());
      if (step === 'checklist') body.appendChild(renderChecklistStep());
      if (step === 'photos')    body.appendChild(renderPhotosStep());
      if (step === 'notes')     body.appendChild(renderNotesStep());
      if (step === 'sign')      body.appendChild(renderSignStep());
      host.appendChild(body);
      host.appendChild(renderFooter());
    }

    function renderTablet() {
      var step = STEPS[state.stepIdx].key;
      if (step === 'sign') {
        var signBody = el('div', { class: 'oq-rb oq-rb--tablet-sign' });
        signBody.appendChild(renderSignStep());
        host.appendChild(signBody);
        host.appendChild(renderFooter());
        return;
      }

      // Two-panel layout: checklist on the left, detail/photos/notes on the right.
      var main = el('div', { class: 'oq-runner__main' });

      var left = el('div', { class: 'oq-checklist-pane' });
      state.template.sections.forEach(function (section) {
        left.appendChild(el('div', { class: 'oq-rsection-label' }, [el('span', null, section.label)]));
        section.items.forEach(function (item) {
          var resp = (state.row.payload.checklist[section.key] || {})[item.key] || {};
          var isActive = state.activeItem
            && state.activeItem.sectionKey === section.key
            && state.activeItem.itemKey === item.key;
          var cardCls = 'oq-cl-item' + (isActive ? ' is-active' : '');
          var card = el('div', { class: cardCls, onclick: function () {
            state.activeItem = { sectionKey: section.key, itemKey: item.key };
            render();
          }}, [
            el('div', { class: 'oq-meta-row' }, [
              el('span', { class: 'oq-eyebrow' }, (item.code || item.label || item.key)),
              resp.result === 'pass' ? pill('passed') : resp.result === 'fail' ? el('span', { class: 'oq-pill oq-pill--needs-correction' }, 'FAIL') : null
            ]),
            el('div', { class: 'oq-cl-item__title' }, item.label),
            el('div', { class: 'oq-cl-item__desc' }, item.desc || '')
          ]);
          left.appendChild(card);
        });
      });
      main.appendChild(left);

      var right = el('div', { class: 'oq-detail-pane' });
      if (!state.activeItem) {
        state.activeItem = {
          sectionKey: state.template.sections[0].key,
          itemKey: state.template.sections[0].items[0].key,
        };
      }
      right.appendChild(renderActiveDetail());
      right.appendChild(el('div', { class: 'oq-rsection-label' }, 'Photos'));
      right.appendChild(renderPhotosStep());
      main.appendChild(right);

      host.appendChild(main);
      host.appendChild(renderFooter());
    }

    function renderActiveDetail() {
      var sk = state.activeItem.sectionKey;
      var ik = state.activeItem.itemKey;
      var section = state.template.sections.find(function (s) { return s.key === sk; });
      var item    = section.items.find(function (i) { return i.key === ik; });
      return renderChecklistCard(section, item);
    }

    function renderChecklistCard(section, item) {
      var resp = (state.row.payload.checklist[section.key] || {})[item.key] || {};
      var card = el('div', { class: 'oq-cl-item is-active' }, [
        el('div', { class: 'oq-meta-row' }, [
          el('span', { class: 'oq-eyebrow' }, (item.code || item.label || item.key)),
          resp.result === 'pass' ? pill('passed') : resp.result === 'fail' ? el('span', { class: 'oq-pill oq-pill--needs-correction' }, 'FAIL') : null,
        ]),
        el('div', { class: 'oq-cl-item__title' }, item.label),
        el('div', { class: 'oq-cl-item__desc' }, item.desc || ''),
        renderPF(section, item, resp),
        resp.result === 'fail' ? renderFailPanel(section, item, resp) : null,
      ]);
      return card;
    }

    function renderPF(section, item, resp) {
      var passBtn = el('button', {
        type: 'button',
        class: resp.result === 'pass' ? 'is-pass' : '',
        onclick: function () { setResult(section.key, item.key, 'pass'); }
      }, 'PASS');
      var failBtn = el('button', {
        type: 'button',
        class: resp.result === 'fail' ? 'is-fail' : '',
        onclick: function () { setResult(section.key, item.key, 'fail'); }
      }, 'FAIL');
      return el('div', { class: 'oq-pf' }, [passBtn, failBtn]);
    }

    function renderFailPanel(section, item, resp) {
      var ta = el('textarea', {
        placeholder: 'What failed? What did you try? What\'s next?',
        oninput: function (e) { setNote(section.key, item.key, e.target.value); }
      });
      ta.value = resp.note || '';
      return el('div', { class: 'oq-fail-panel' }, [
        el('div', { class: 'oq-fail-panel__head' }, [icon('flag'), 'Note required for FAIL']),
        ta
      ]);
    }

    function renderVehicleStep() {
      var v = (state.row.payload && state.row.payload.vehicle) || {};
      var wrap = el('div', { class: 'oq-cl-item' }, [
        el('div', { class: 'oq-eyebrow' }, 'Vehicle info'),
        el('div', { class: 'oq-cl-item__title' }, 'Confirm what\'s on the dash'),
        el('div', { class: 'oq-cl-item__desc' }, 'These values are saved as part of the form record.'),
      ]);
      ['vin', 'odometer', 'key_count', 'rvia_no'].forEach(function (key) {
        var label = ({ vin: 'VIN', odometer: 'Odometer', key_count: 'Key count', rvia_no: 'RVIA #' })[key];
        var inp = el('input', {
          class: 'oq-input', type: 'text', placeholder: label,
          oninput: function (e) {
            state.row.payload.vehicle = state.row.payload.vehicle || {};
            state.row.payload.vehicle[key] = e.target.value;
            saveDraft();
          }
        });
        inp.value = v[key] || '';
        wrap.appendChild(el('div', { class: 'oq-rsection-label' }, label));
        wrap.appendChild(inp);
      });
      return wrap;
    }

    function renderChecklistStep() {
      var box = document.createDocumentFragment();
      state.template.sections.forEach(function (section) {
        box.appendChild(el('div', { class: 'oq-rsection-label' }, section.label));
        section.items.forEach(function (item) {
          box.appendChild(renderChecklistCard(section, item));
        });
      });
      var wrap = el('div', null);
      wrap.appendChild(box);
      return wrap;
    }

    function renderPhotosStep() {
      var slots = state.template.photo_slots || [];
      var photos = state.photoCache || {};
      var grid = el('div', { class: 'oq-photo-tray' });
      slots.forEach(function (slot, idx) {
        var attached = photos[slot.key] || [];
        var filled = attached.length > 0;
        var first = filled ? attached[0] : null;
        var slotEl = el('div', {
          class: 'oq-photo-slot ' + (filled ? 'oq-photo-slot--filled' : ''),
          role: 'button',
          tabindex: '0',
          style: first ? 'background-image:url(' + (first.thumb_url || first.url) + ')' : '',
          onclick: function () { triggerCapture(slot.key); }
        }, [
          el('span', { class: 'oq-photo-slot__status ' + (filled ? 'oq-photo-slot__status--ok' : 'oq-photo-slot__status--missing') }, filled ? '✓' : '!'),
          el('span', { class: 'oq-photo-slot__label' }, (idx + 1) + '/' + slots.length + ' · ' + slot.label)
        ]);
        grid.appendChild(slotEl);
      });
      return el('div', null, [
        el('div', { class: 'oq-rsection-label' }, [
          el('span', null, 'Required photos'),
          el('span', null, attachedCount(photos, slots) + ' of ' + slots.filter(function (s) { return s.required; }).length)
        ]),
        grid,
      ]);
    }

    function attachedCount(photos, slots) {
      return slots.filter(function (s) { return s.required && (photos[s.key] || []).length > 0; }).length;
    }

    function triggerCapture(slotKey) {
      var inp = document.createElement('input');
      inp.type = 'file';
      inp.accept = 'image/*';
      // Camera-first on mobile, photo library otherwise.
      if (window.innerWidth < 768) inp.capture = 'environment';
      inp.addEventListener('change', function () {
        if (!inp.files || !inp.files[0]) return;
        uploadPhoto(slotKey, inp.files[0]).catch(function (e) { window.alert(e.message); });
      });
      inp.click();
    }

    function renderNotesStep() {
      var ta = el('textarea', {
        class: 'oq-input oq-textarea',
        placeholder: 'Anything else the supervisor should know.',
        oninput: function (e) {
          state.row.payload.notes = e.target.value;
          saveDraft();
        }
      });
      ta.value = state.row.payload.notes || '';
      return el('div', null, [
        el('div', { class: 'oq-rsection-label' }, 'Notes'),
        ta,
      ]);
    }

    function renderSignStep() {
      var issues = validate();
      var summary = el('div', { class: 'oq-summary' }, [
        el('div', { class: 'oq-summary__title' }, issues.length === 0 ? 'Ready to submit' : 'Still to complete'),
        el('div', { class: 'oq-summary__sub' }, issues.length === 0 ? 'Type your name to sign and submit. Once submitted, the form locks.' : 'Resolve these before submitting.'),
      ]);
      if (issues.length) {
        var ul = el('ul', { class: 'oq-summary__list' });
        issues.forEach(function (msg) { ul.appendChild(el('li', null, ['• ', msg])); });
        summary.appendChild(ul);
      }
      var sig = el('div', { class: 'oq-signature' }, [
        el('label', { for: 'oq-sig' }, 'Typed signature'),
        el('input', { id: 'oq-sig', type: 'text', placeholder: 'Type your full name', autocomplete: 'name' }),
        el('div', { class: 'oq-signature__meta' }, 'Your user ID and timestamp are recorded automatically.'),
      ]);
      return el('div', null, [
        el('div', { class: 'oq-rsection-label' }, 'Sign & submit'),
        summary,
        sig,
      ]);
    }

    function validate() {
      var issues = [];
      state.template.sections.forEach(function (section) {
        section.items.forEach(function (item) {
          var resp = (state.row.payload.checklist[section.key] || {})[item.key] || {};
          if (resp.result !== 'pass' && resp.result !== 'fail') {
            issues.push('"' + item.label + '" needs PASS or FAIL');
          } else if (resp.result === 'fail' && !(resp.note || '').trim()) {
            issues.push('"' + item.label + '" failed — add a note');
          }
        });
      });
      var photos = state.photoCache || {};
      (state.template.photo_slots || []).forEach(function (s) {
        if (s.required && !(photos[s.key] || []).length) {
          issues.push('Missing photo: ' + s.label);
        }
      });
      return issues;
    }

    function renderFooter() {
      var prevDisabled = state.stepIdx === 0;
      var isLast = state.stepIdx === STEPS.length - 1;
      var ftr = el('div', { class: 'oq-rfooter' });
      ftr.appendChild(el('button', {
        class: 'oq-btn oq-btn--secondary oq-btn--icon',
        disabled: prevDisabled ? 'disabled' : null,
        onclick: function () { if (!prevDisabled) { state.stepIdx--; render(); } }
      }, [icon('chevron_left')]));

      if (isLast) {
        ftr.appendChild(el('button', {
          class: 'oq-btn oq-btn--primary',
          onclick: function () {
            var input = document.getElementById('oq-sig');
            if (!input || !input.value.trim()) { window.alert('Type your name to sign.'); return; }
            var name = input.value.trim();
            api('POST', '/quality/jobs/' + jobId + '/forms/' + code + '/submit', {
              signature: { typed_name: name },
              payload: state.row.payload || {}
            }).then(function () {
              window.location.href = '/ops/quality/job/' + jobId;
            }).catch(function (e) {
              if (e.payload && e.payload.data && e.payload.data.errors) {
                window.alert('Cannot submit yet — finish required items.');
              } else {
                window.alert(e.message);
              }
            });
          }
        }, ['Sign & submit', icon('check')]));
      } else {
        ftr.appendChild(el('button', {
          class: 'oq-btn oq-btn--primary',
          onclick: function () { state.stepIdx++; render(); }
        }, ['Next', icon('arrow_forward')]));
      }
      return ftr;
    }

    var resizeT = null;
    window.addEventListener('resize', function () {
      if (resizeT) clearTimeout(resizeT);
      resizeT = setTimeout(render, 120);
    });
  }

  function start() {
    var root = document.querySelector('.oq');
    if (!root) return;
    var view = root.dataset.view;
    if (view === 'dashboard') initDashboard(root);
    if (view === 'job')       initJobReview(root);
    if (view === 'form')      initRunner(root);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
