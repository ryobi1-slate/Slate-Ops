/*
 * Slate Ops — Tech page Quality injection.
 *
 * Overlays a Quality section onto each .ops-tech-card on /ops/tech without
 * touching the React bundle. Reads the job_id from the card's surrounding
 * markup (or its SO link), fetches Quality state for that job via REST,
 * and renders status pills + a deep link to /ops/quality/job/{job_id}.
 *
 * React rerender resilience:
 *  - Each card is marked with a stable data-oq-job-id attribute once we
 *    resolve its job id; we never re-fetch.
 *  - On every observer tick we check whether OUR child (.ops-tech-quality)
 *    is still present. If React wiped it during a rerender, we re-attach
 *    synchronously from the in-memory descriptor cache — no extra REST
 *    calls, no flicker beyond the React frame.
 *  - Mutations are debounced so the existing tech-page timer/parts/notes
 *    React paths aren't slowed by re-entry.
 *
 * .ops-tech-card is the React tech page's job-card class; the same hook
 * the readonly/polish overlays use. If the React app renames this class
 * the injection silently no-ops (no errors) and Quality remains reachable
 * from the sidebar entry at /ops/quality.
 *
 * Vanilla JS only. No jQuery. Mirrors the readonly/polish overlay pattern.
 */
(function () {
  'use strict';

  var settings = window.slateOpsTechQuality || {};
  var API   = (settings.api && settings.api.root)  || '/wp-json/slate-ops/v1';
  var NONCE = (settings.api && settings.api.nonce) || '';
  var QUALITY_BASE = (settings.urls && settings.urls.quality_job) || '/ops/quality/job/';

  // Single chokepoint for finding job cards. Keep selector logic here so a
  // future React class rename only needs to touch one constant.
  var CARD_SELECTOR = '.ops-tech-card';
  var INJECTED_CLASS = 'ops-tech-quality';
  var CARD_MARK_ATTR = 'data-oq-job-id';

  var descCache = {};      // job_id → descriptor
  var inFlight  = {};      // job_id → Promise

  function api(path) {
    return fetch(API + path, {
      headers: { 'X-WP-Nonce': NONCE },
      credentials: 'same-origin',
    }).then(function (r) { return r.ok ? r.json() : null; });
  }

  function statusLabel(s) {
    return ({
      not_started: 'Not Started', in_progress: 'In Progress',
      submitted: 'Submitted', needs_correction: 'Needs Correction',
      passed: 'Passed', locked: 'Locked'
    })[s] || 'Not Started';
  }
  function statusClass(s) { return (s || 'not_started').replace(/_/g, '-'); }

  function findJobId(card) {
    // The React app embeds the job id in a few possible ways. Try the most
    // robust signal first: an explicit data-* attribute, then any link
    // href containing ?job_id= / /jobs/ / /job/, then text "Q-1234".
    var explicit = card.getAttribute('data-job-id') || card.getAttribute(CARD_MARK_ATTR);
    if (explicit) return parseInt(explicit, 10) || null;

    var links = card.querySelectorAll('a[href*="job"]');
    for (var i = 0; i < links.length; i++) {
      var m = links[i].getAttribute('href').match(/(?:job_id=|\/jobs\/|\/job\/)(\d+)/);
      if (m) return parseInt(m[1], 10);
    }
    var text = card.textContent || '';
    var m2 = text.match(/Q-(\d{2,})/) || text.match(/#\s?(\d{3,})/);
    return m2 ? parseInt(m2[1], 10) : null;
  }

  function loadJob(jobId) {
    if (descCache[jobId]) return Promise.resolve(descCache[jobId]);
    if (inFlight[jobId])  return inFlight[jobId];
    inFlight[jobId] = api('/quality/jobs/' + jobId).then(function (data) {
      delete inFlight[jobId];
      if (data && data.job_id) descCache[jobId] = data;
      return data;
    }, function () { delete inFlight[jobId]; return null; });
    return inFlight[jobId];
  }

  function buildSection(desc) {
    var section = document.createElement('div');
    section.className = INJECTED_CLASS;
    section.setAttribute('data-oq-job-id', String(desc.job_id));

    var required = desc.required_forms || [];
    var done = 0;
    required.forEach(function (code) {
      var row = (desc.forms || []).find(function (r) { return r.form_code === code; });
      if (row && (row.status === 'passed' || row.status === 'submitted')) done++;
    });
    var pct = required.length ? Math.round(done / required.length * 100) : 0;
    var statusCls = statusClass(desc.rollup_status);

    section.innerHTML =
      '<div class="ops-tech-quality__head">' +
        '<span class="ops-tech-quality__eyebrow">Quality · ' + (desc.quality_type === 'rvia' ? 'RVIA' : 'Commercial') + '</span>' +
        '<span class="oq-pill oq-pill--' + statusCls + '">' + statusLabel(desc.rollup_status) + '</span>' +
      '</div>' +
      '<div class="ops-tech-quality__progress">' +
        '<div class="ops-tech-quality__bar"><span style="width:' + pct + '%"></span></div>' +
        '<div class="ops-tech-quality__progress-meta">' + done + ' of ' + required.length + ' Quality forms complete</div>' +
      '</div>' +
      '<div class="ops-tech-quality__forms"></div>' +
      '<a class="ops-tech-quality__open" href="' + QUALITY_BASE + desc.job_id + '">Open Quality →</a>';

    var formsWrap = section.querySelector('.ops-tech-quality__forms');
    required.forEach(function (code) {
      var row = (desc.forms || []).find(function (r) { return r.form_code === code; });
      var st  = row ? row.status : 'not_started';
      var chip = document.createElement('a');
      chip.className = 'ops-tech-quality__form';
      chip.href = QUALITY_BASE + desc.job_id + '/form/' + code;
      chip.innerHTML =
        '<span class="ops-tech-quality__form-code">' + code.replace('QMS-', '') + '</span>' +
        '<span class="ops-tech-quality__form-name">' + code + '</span>' +
        '<span class="oq-pill oq-pill--' + statusClass(st) + '">' + statusLabel(st) + '</span>';
      formsWrap.appendChild(chip);
    });
    return section;
  }

  function ensureSection(card, desc) {
    if (!desc) return;
    // If our injected child is already there for this job, leave it alone.
    var existing = card.querySelector(':scope > .' + INJECTED_CLASS);
    if (existing && existing.getAttribute('data-oq-job-id') === String(desc.job_id)) return;
    if (existing) existing.remove();
    card.appendChild(buildSection(desc));
  }

  function processCard(card) {
    // Resolve job id once per card; the marker attribute survives React
    // child rerenders because we set it on the card root, not on a child
    // React owns.
    var cached = card.getAttribute(CARD_MARK_ATTR);
    var jobId  = cached ? parseInt(cached, 10) : findJobId(card);
    if (!jobId) return;
    if (!cached) card.setAttribute(CARD_MARK_ATTR, String(jobId));

    // Fast path: descriptor already in memory → re-attach synchronously.
    if (descCache[jobId]) { ensureSection(card, descCache[jobId]); return; }

    loadJob(jobId).then(function (desc) {
      if (desc) ensureSection(card, desc);
    });
  }

  function scan() {
    var cards = document.querySelectorAll(CARD_SELECTOR);
    for (var i = 0; i < cards.length; i++) processCard(cards[i]);
  }

  // Debounced observer — coalesce React rerender churn into a single tick.
  var pending = false;
  function schedule() {
    if (pending) return;
    pending = true;
    // rAF gives React time to finish its current commit before we look.
    (window.requestAnimationFrame || function (fn) { setTimeout(fn, 16); })(function () {
      pending = false;
      scan();
    });
  }

  function start() {
    scan();
    var observer = new MutationObserver(function (mutations) {
      // Ignore mutations that only touched our own injected nodes — keeps
      // us from looping when we attach a section.
      for (var i = 0; i < mutations.length; i++) {
        var m = mutations[i];
        var target = m.target;
        if (target && target.classList && target.classList.contains(INJECTED_CLASS)) continue;
        if (target && target.closest && target.closest('.' + INJECTED_CLASS)) continue;
        schedule();
        return;
      }
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
