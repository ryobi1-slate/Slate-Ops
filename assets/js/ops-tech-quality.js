/*
 * Slate Ops — Tech page Quality injection.
 *
 * Overlays a Quality section onto each .ops-tech-card on /ops/tech without
 * touching the React bundle. Reads the job_id baked into the card's
 * surrounding markup (or its SO link), fetches Quality state for that job
 * via REST, and renders status pills + a "Open Quality" deep link to
 * /ops/quality/job/{job_id}.
 *
 * Vanilla JS only. Mirrors the read-only/polish overlay pattern.
 */
(function () {
  'use strict';

  var settings = window.slateOpsTechQuality || {};
  var API   = (settings.api && settings.api.root)  || '/wp-json/slate-ops/v1';
  var NONCE = (settings.api && settings.api.nonce) || '';
  var QUALITY_BASE = (settings.urls && settings.urls.quality_job) || '/ops/quality/job/';

  var jobCache = {};       // job_id → descriptor (cached for 60s)
  var cardSeen = new WeakSet();

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
    // The React app embeds job identifiers in a few possible ways. Try the most
    // robust signal first: a "Q-1234" style mono badge or a link/href that
    // includes ?job_id= or /jobs/.
    var explicit = card.getAttribute('data-job-id');
    if (explicit) return parseInt(explicit, 10);

    var links = card.querySelectorAll('a[href*="job"]');
    for (var i = 0; i < links.length; i++) {
      var m = links[i].getAttribute('href').match(/(?:job_id=|\/jobs\/|\/job\/)(\d+)/);
      if (m) return parseInt(m[1], 10);
    }
    // Fall back to scanning text for "Q-" or "#1234"
    var text = card.textContent || '';
    var m2 = text.match(/Q-(\d{2,})/) || text.match(/#\s?(\d{3,})/);
    return m2 ? parseInt(m2[1], 10) : null;
  }

  function loadJob(jobId) {
    if (jobCache[jobId]) return Promise.resolve(jobCache[jobId]);
    return api('/quality/jobs/' + jobId).then(function (data) {
      if (data && data.job_id) jobCache[jobId] = data;
      return data;
    });
  }

  function renderSection(card, desc) {
    if (!desc) return;
    var existing = card.querySelector('.ops-tech-quality');
    if (existing) existing.remove();

    var section = document.createElement('div');
    section.className = 'ops-tech-quality';
    var requiredCount = desc.required_forms.length;
    var doneCount = 0;
    desc.required_forms.forEach(function (code) {
      var row = (desc.forms || []).find(function (r) { return r.form_code === code; });
      if (row && (row.status === 'passed' || row.status === 'submitted')) doneCount++;
    });

    var statusCls = statusClass(desc.rollup_status);
    section.innerHTML =
      '<div class="ops-tech-quality__head">' +
        '<span class="ops-tech-quality__eyebrow">Quality · ' + (desc.quality_type === 'rvia' ? 'RVIA' : 'Commercial') + '</span>' +
        '<span class="oq-pill oq-pill--' + statusCls + '">' + statusLabel(desc.rollup_status) + '</span>' +
      '</div>' +
      '<div class="ops-tech-quality__progress">' +
        '<div class="ops-tech-quality__bar"><span style="width:' + (requiredCount ? Math.round(doneCount / requiredCount * 100) : 0) + '%"></span></div>' +
        '<div class="ops-tech-quality__progress-meta">' + doneCount + ' of ' + requiredCount + ' Quality forms complete</div>' +
      '</div>' +
      '<div class="ops-tech-quality__forms"></div>' +
      '<a class="ops-tech-quality__open" href="' + QUALITY_BASE + desc.job_id + '">Open Quality →</a>';

    var formsWrap = section.querySelector('.ops-tech-quality__forms');
    desc.required_forms.forEach(function (code) {
      var row = (desc.forms || []).find(function (r) { return r.form_code === code; });
      var st  = row ? row.status : 'not_started';
      var chip = document.createElement('a');
      chip.className = 'ops-tech-quality__form';
      chip.href = QUALITY_BASE + desc.job_id + '/form/' + code;
      chip.innerHTML =
        '<span class="ops-tech-quality__form-code">' + code.replace('QMS-', '') + '</span>' +
        '<span class="ops-tech-quality__form-name">' + (row && row.payload && row.payload.template && row.payload.template.name ? row.payload.template.name : code) + '</span>' +
        '<span class="oq-pill oq-pill--' + statusClass(st) + '">' + statusLabel(st) + '</span>';
      formsWrap.appendChild(chip);
    });

    // Append the section inside the card so it sits with the job actions.
    card.appendChild(section);
  }

  function scan() {
    var cards = document.querySelectorAll('.ops-tech-card');
    Array.prototype.forEach.call(cards, function (card) {
      if (cardSeen.has(card)) return;
      var jobId = findJobId(card);
      if (!jobId) return;
      cardSeen.add(card);
      loadJob(jobId).then(function (desc) {
        if (desc) renderSection(card, desc);
      });
    });
  }

  function start() {
    scan();
    var observer = new MutationObserver(function () { scan(); });
    observer.observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
