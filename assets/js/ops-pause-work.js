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
    var afterHoursRequired = !!(options.afterHoursRequired || options.overtimeRequired);
    var rolloutMode = options.rolloutMode || (window.slateOpsSettings && window.slateOpsSettings.tech_rollout_mode) || 'PHASE_1_TIMER_HABIT';
    var showBlockedReasons = rolloutMode !== 'PHASE_1_TIMER_HABIT';
    return new Promise(function (resolve) {
      var NORMAL_REASONS = [
        ['END_OF_SHIFT', 'End of shift', 'Pause and continue later.', false],
        ['SWITCH_JOB', 'Switching to another job', 'Priority changed or tech was redirected.', true]
      ];
      var BLOCKED_REASONS = [
        ['WAITING_ON_PARTS', 'Waiting on parts', 'Parts are missing, wrong, damaged, or not staged.'],
        ['TECH_SUPPORT_NEEDED', 'Tech support needed', 'Another tech or supervisor needs to help resolve an install issue.'],
        ['VEHICLE_ISSUE', 'Vehicle issue', 'Vehicle condition is stopping work.'],
        ['CUSTOMER_DEALER_QUESTION', 'Customer / dealer question', 'CS needs clarification before work continues.'],
        ['SCOPE_OR_JOB_ISSUE', 'Scope or job issue', 'Quote, SO, job details, or requested work do not match.'],
        ['QUALITY_REWORK_ISSUE', 'Quality / rework issue', 'A quality issue needs correction before work continues.'],
        ['OTHER_ISSUE', 'Other issue', 'Use only if none of the above fit.']
      ];
      var reasonMeta = {};

      function esc(value) {
        return String(value == null ? '' : value)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
      }

      function reasonButton(reason, group, noteRequired) {
        reasonMeta[reason[0]] = {
          label: reason[1],
          group: group,
          noteRequired: !!noteRequired,
          blocked: group === 'blocked'
        };
        return '<button type="button" class="ops-pw-choice" data-reason="' + esc(reason[0]) + '">' +
          '<span class="ops-pw-choice__title">' + esc(reason[1]) + '</span>' +
          '<span class="ops-pw-choice__sub">' + esc(reason[2]) + '</span>' +
        '</button>';
      }

      var normalButtons = NORMAL_REASONS.map(function (reason) {
        return reasonButton(reason, 'pause', reason[3]);
      }).join('');
      var blockedButtons = BLOCKED_REASONS.map(function (reason) {
        return reasonButton(reason, 'blocked', true);
      }).join('');
      var switchTargets = Array.isArray(options.switchTargets) ? options.switchTargets : [];
      var targetOptions = switchTargets.map(function (job) {
        var id = parseInt(job.job_id || job.id || 0, 10);
        if (!id) return '';
        var label = job.so_number || job.customer_name || ('Job #' + id);
        var meta = job.customer_name && job.so_number ? ' - ' + job.customer_name : '';
        return '<option value="' + id + '">' + esc(label + meta) + '</option>';
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
          '<div class="ops-pw-content">' +
            '<section class="ops-pw-section">' +
              '<h3 class="ops-pw-section-title">Normal pause</h3>' +
              '<div class="ops-pw-choices" role="listbox" aria-label="Normal pause reason">' + normalButtons + '</div>' +
            '</section>' +
            (showBlockedReasons ? '<section class="ops-pw-section">' +
              '<h3 class="ops-pw-section-title">Blocked - needs action</h3>' +
              '<div class="ops-pw-choices" role="listbox" aria-label="Blocked reason">' + blockedButtons + '</div>' +
            '</section>' : '') +
            '<div id="ops-pw-err" class="ops-pw-field-err" hidden>Select a pause reason.</div>' +
            '<div id="ops-pw-detail-fields" class="ops-pw-detail-fields" hidden>' +
              '<div class="ops-pw-field" id="ops-pw-block-scope" hidden>' +
                '<div class="ops-pw-label">Can other work continue?</div>' +
                '<div class="ops-pw-radio-grid">' +
                  '<label><input type="radio" name="ops-pw-scope" value="yes"> Yes, other work can continue</label>' +
                  '<label><input type="radio" name="ops-pw-scope" value="no"> No, job is fully stopped</label>' +
                '</div>' +
              '</div>' +
              (targetOptions
                ? '<div class="ops-pw-field" id="ops-pw-target-field" hidden>' +
                    '<label class="ops-pw-label" for="ops-pw-target-job">Switch target <span class="ops-pw-optional">(optional)</span></label>' +
                    '<select id="ops-pw-target-job" class="ops-pw-select"><option value="">Select job...</option>' + targetOptions + '</select>' +
                  '</div>'
                : '') +
              '<div class="ops-pw-field" id="ops-pw-note-field" hidden>' +
                '<label class="ops-pw-label" for="ops-pw-note">' +
                  'Note <span id="ops-pw-note-state" class="ops-pw-optional">(optional)</span>' +
                '</label>' +
                '<textarea id="ops-pw-note" class="ops-pw-textarea" placeholder="Add details..." rows="3"></textarea>' +
              '</div>' +
              '<div class="ops-pw-field" id="ops-pw-after-field" hidden>' +
                '<label class="ops-pw-label" for="ops-pw-after-note">After-hours note <span class="ops-pw-required">(required)</span></label>' +
                '<textarea id="ops-pw-after-note" class="ops-pw-textarea" placeholder="Why was work needed after shift?" rows="2"></textarea>' +
              '</div>' +
            '</div>' +
          '</div>' +
          '<div class="ops-pw-actions">' +
            '<button id="ops-pw-cancel" type="button" class="ops-pw-btn ops-pw-btn--cancel">Cancel</button>' +
            '<button id="ops-pw-submit" type="button" class="ops-pw-btn ops-pw-btn--primary" disabled>Pause job</button>' +
          '</div>' +
        '</div>';

      document.body.appendChild(overlay);
      document.body.classList.add('ops-pw-open');

      var modal    = overlay.querySelector('.ops-pw-modal');
      var detailFields = overlay.querySelector('#ops-pw-detail-fields');
      var errEl    = overlay.querySelector('#ops-pw-err');
      var noteEl   = overlay.querySelector('#ops-pw-note');
      var noteFieldEl = overlay.querySelector('#ops-pw-note-field');
      var noteStateEl = overlay.querySelector('#ops-pw-note-state');
      var afterFieldEl = overlay.querySelector('#ops-pw-after-field');
      var afterNoteEl = overlay.querySelector('#ops-pw-after-note');
      var blockScopeEl = overlay.querySelector('#ops-pw-block-scope');
      var targetFieldEl = overlay.querySelector('#ops-pw-target-field');
      var targetJobEl = overlay.querySelector('#ops-pw-target-job');
      var cancelEl = overlay.querySelector('#ops-pw-cancel');
      var xEl      = overlay.querySelector('#ops-pw-x');
      var submitEl = overlay.querySelector('#ops-pw-submit');
      var selectedReason = '';

      function needsAfterHoursNote() {
        return afterHoursRequired && selectedReason !== 'END_OF_SHIFT';
      }

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

      function selectedScope() {
        var checked = overlay.querySelector('input[name="ops-pw-scope"]:checked');
        return checked ? checked.value : '';
      }

      function isComplete() {
        if (!selectedReason) return false;
        var meta = reasonMeta[selectedReason] || {};
        if (meta.noteRequired && !noteEl.value.trim()) return false;
        if (meta.blocked && !selectedScope()) return false;
        if (
          needsAfterHoursNote() &&
          !afterNoteEl.value.trim() &&
          !noteEl.value.trim()
        ) return false;
        return true;
      }

      function updateSubmit() {
        var meta = reasonMeta[selectedReason] || {};
        if (!selectedReason) {
          submitEl.textContent = 'Pause job';
        } else if (meta.blocked) {
          submitEl.textContent = 'Mark blocked';
        } else if (selectedReason === 'SWITCH_JOB') {
          submitEl.textContent = 'Pause and switch';
        } else {
          submitEl.textContent = 'Pause job';
        }
        submitEl.disabled = !isComplete();
      }

      function placeDetailsAfter(btn, meta) {
        var shouldShowDetails = !!(
          meta.noteRequired ||
          meta.blocked ||
          selectedReason === 'SWITCH_JOB' ||
          needsAfterHoursNote()
        );
        if (!shouldShowDetails) {
          detailFields.hidden = true;
          return;
        }
        btn.insertAdjacentElement('afterend', detailFields);
        detailFields.hidden = false;
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
          var meta = reasonMeta[selectedReason] || {};
          overlay.querySelectorAll('.ops-pw-choice').forEach(function (b) {
            b.classList.toggle('is-selected', b === btn);
            b.setAttribute('aria-selected', b === btn ? 'true' : 'false');
          });
          noteFieldEl.hidden = !meta.noteRequired;
          noteStateEl.textContent = meta.noteRequired ? '(required)' : '(optional)';
          noteStateEl.className = meta.noteRequired ? 'ops-pw-required' : 'ops-pw-optional';
          blockScopeEl.hidden = !meta.blocked;
          if (targetFieldEl) targetFieldEl.hidden = selectedReason !== 'SWITCH_JOB';
          afterFieldEl.hidden = !needsAfterHoursNote();
          placeDetailsAfter(btn, meta);
          errEl.hidden = true;
          updateSubmit();
        });
      });

      [noteEl, afterNoteEl].forEach(function (el) {
        if (el) el.addEventListener('input', updateSubmit);
      });
      overlay.querySelectorAll('input[name="ops-pw-scope"]').forEach(function (el) {
        el.addEventListener('change', updateSubmit);
      });

      cancelEl.addEventListener('click', function () { done(null); });
      xEl.addEventListener('click', function () { done(null); });
      overlay.addEventListener('click', function (e) { if (e.target === overlay) done(null); });
      document.addEventListener('keydown', onKey);

      submitEl.addEventListener('click', function () {
        var meta = reasonMeta[selectedReason] || {};
        var note = noteEl.value.trim();
        var afterHoursNote = afterNoteEl.value.trim();
        var scope = selectedScope();
        if (!selectedReason) {
          setError('Select a pause reason.', overlay.querySelector('.ops-pw-choice'));
          return;
        }
        if (meta.noteRequired && !note) {
          setError('Add a short note for this reason.', noteEl);
          return;
        }
        if (meta.blocked && !scope) {
          setError('Choose whether other work can continue.', overlay.querySelector('input[name="ops-pw-scope"]'));
          return;
        }
        if (needsAfterHoursNote() && !afterHoursNote && note) {
          afterHoursNote = note;
        }
        if (needsAfterHoursNote() && !afterHoursNote) {
          setError('Add an after-hours note before pausing.', afterNoteEl);
          return;
        }
        done({
          pause_reason: selectedReason,
          pause_note: note,
          pause_type: meta.blocked ? 'blocked' : 'paused',
          source: 'tech_manual',
          blocked: !!meta.blocked,
          requires_clearance: !!meta.blocked,
          after_hours: needsAfterHoursNote(),
          after_hours_note: afterHoursNote,
          overtime_note: afterHoursNote,
          other_work_can_continue: meta.blocked ? scope === 'yes' : null,
          target_job_id: targetJobEl && targetJobEl.value ? parseInt(targetJobEl.value, 10) : 0
        });
      });

      setTimeout(function () {
        var first = overlay.querySelector('.ops-pw-choice');
        if (first) first.focus();
      }, 50);
    });
  };
})();
