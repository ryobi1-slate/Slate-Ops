(function () {
  'use strict';

  window.__slateOpsToast = function (msg) {
    var el = document.createElement('div');
    el.className = 'ops-toast ops-toast--success';
    el.setAttribute('role', 'status');
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(function () {
      el.classList.add('ops-toast--out');
      setTimeout(function () { el.remove(); }, 300);
    }, 3500);
  };

  window.__slateOpsPauseWork = function (options) {
    options = options || {};
    return new Promise(function (resolve) {
      var REASONS = [
        ['end_of_day',            'End of day',                'Pause and continue later.'],
        ['waiting_on_parts',      'Waiting on parts',          'Parts are needed before work continues.'],
        ['need_help',             'Need help',                 'Another tech or supervisor support is needed.'],
        ['vehicle_issue',         'Vehicle issue',             'Vehicle condition is blocking work.'],
        ['customer_cs_question',  'Customer / CS question',    'CS needs to clarify before work continues.'],
        ['other',                 'Other',                     'Use a short note to explain.'],
      ];
      var NOTE_REQUIRED = {
        need_help: true,
        vehicle_issue: true,
        customer_cs_question: true,
        other: true
      };

      var reasonButtons = REASONS.map(function (r) {
        return '<button type="button" class="ops-pw-choice" data-reason="' + r[0] + '">' +
          '<span class="ops-pw-choice__title">' + r[1] + '</span>' +
          '<span class="ops-pw-choice__sub">' + r[2] + '</span>' +
        '</button>';
      }).join('');

      var overlay = document.createElement('div');
      overlay.className = 'ops-pw-overlay';
      overlay.innerHTML =
        '<div class="ops-pw-modal" role="dialog" aria-modal="true" aria-labelledby="ops-pw-title">' +
          '<div class="ops-pw-head">' +
            '<div>' +
              '<h2 id="ops-pw-title" class="ops-pw-title">Pause work</h2>' +
              '<p class="ops-pw-body">Select why this job is stopping.</p>' +
            '</div>' +
            '<button id="ops-pw-x" type="button" class="ops-pw-x" aria-label="Cancel">&times;</button>' +
          '</div>' +
          '<div class="ops-pw-choices" role="listbox" aria-label="Pause reason">' + reasonButtons + '</div>' +
          '<div id="ops-pw-err" class="ops-pw-field-err" hidden>Select a pause reason.</div>' +
          '<div class="ops-pw-field">' +
            '<label class="ops-pw-label" for="ops-pw-note">' +
              'Note <span id="ops-pw-note-state" class="ops-pw-optional">(optional)</span>' +
            '</label>' +
            '<textarea id="ops-pw-note" class="ops-pw-textarea" placeholder="Add details…" rows="3"></textarea>' +
          '</div>' +
          (options.overtimeRequired
            ? '<div class="ops-pw-field">' +
                '<label class="ops-pw-label" for="ops-pw-ot-note">Overtime note <span class="ops-pw-required">(required)</span></label>' +
                '<textarea id="ops-pw-ot-note" class="ops-pw-textarea" placeholder="Why was work needed after shift?" rows="2"></textarea>' +
              '</div>'
            : '') +
          '<div class="ops-pw-actions">' +
            '<button id="ops-pw-cancel" type="button" class="ops-pw-btn ops-pw-btn--cancel">Cancel</button>' +
            '<button id="ops-pw-submit" type="button" class="ops-pw-btn ops-pw-btn--primary">Pause work</button>' +
          '</div>' +
        '</div>';

      document.body.appendChild(overlay);
      document.body.classList.add('ops-pw-open');

      var modal    = overlay.querySelector('.ops-pw-modal');
      var errEl    = overlay.querySelector('#ops-pw-err');
      var noteEl   = overlay.querySelector('#ops-pw-note');
      var noteStateEl = overlay.querySelector('#ops-pw-note-state');
      var otNoteEl = overlay.querySelector('#ops-pw-ot-note');
      var cancelEl = overlay.querySelector('#ops-pw-cancel');
      var xEl      = overlay.querySelector('#ops-pw-x');
      var submitEl = overlay.querySelector('#ops-pw-submit');
      var selectedReason = '';

      var FOCUSABLE = 'a[href],button:not([disabled]),input,select,textarea,[tabindex]:not([tabindex="-1"])';
      function getFocusable() { return Array.prototype.slice.call(modal.querySelectorAll(FOCUSABLE)); }

      function done(result) {
        overlay.remove();
        document.body.classList.remove('ops-pw-open');
        document.removeEventListener('keydown', onKey);
        resolve(result);
      }

      function setError(msg, target) {
        errEl.hidden = false;
        errEl.textContent = msg;
        if (target && target.focus) target.focus();
      }

      function onKey(e) {
        if (e.key === 'Escape') { done(null); return; }
        if (e.key !== 'Tab') return;

        var focusable = getFocusable();
        if (!focusable.length) { e.preventDefault(); return; }
        var first = focusable[0];
        var last  = focusable[focusable.length - 1];

        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }

      overlay.querySelectorAll('.ops-pw-choice').forEach(function (btn) {
        btn.addEventListener('click', function () {
          selectedReason = btn.getAttribute('data-reason') || '';
          overlay.querySelectorAll('.ops-pw-choice').forEach(function (b) {
            b.classList.toggle('is-selected', b === btn);
            b.setAttribute('aria-selected', b === btn ? 'true' : 'false');
          });
          noteStateEl.textContent = NOTE_REQUIRED[selectedReason] ? '(required)' : '(optional)';
          noteStateEl.className = NOTE_REQUIRED[selectedReason] ? 'ops-pw-required' : 'ops-pw-optional';
          errEl.hidden = true;
        });
      });

      cancelEl.addEventListener('click', function () { done(null); });
      xEl.addEventListener('click', function () { done(null); });
      overlay.addEventListener('click', function (e) { if (e.target === overlay) done(null); });
      document.addEventListener('keydown', onKey);

      submitEl.addEventListener('click', function () {
        var note = noteEl.value.trim();
        var overtimeNote = otNoteEl ? otNoteEl.value.trim() : '';
        if (!selectedReason) {
          setError('Select a pause reason.', overlay.querySelector('.ops-pw-choice'));
          return;
        }
        if (NOTE_REQUIRED[selectedReason] && !note) {
          setError('Add a short note for this pause reason.', noteEl);
          return;
        }
        if (otNoteEl && !overtimeNote) {
          setError('Add an overtime note before pausing.', otNoteEl);
          return;
        }
        done({
          pause_reason: selectedReason,
          pause_note: note,
          overtime_note: overtimeNote
        });
      });

      setTimeout(function () {
        var first = overlay.querySelector('.ops-pw-choice');
        if (first) first.focus();
      }, 50);
    });
  };
})();
