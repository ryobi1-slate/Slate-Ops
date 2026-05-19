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

  window.__slateOpsConfirmCloseout = function () {
    return new Promise(function (resolve) {
      var overlay = document.createElement('div');
      overlay.className = 'ops-pw-overlay';
      overlay.innerHTML =
        '<div class="ops-pw-modal" role="dialog" aria-modal="true" aria-labelledby="ops-closeout-title">' +
          '<div class="ops-pw-head">' +
            '<div>' +
              '<h2 id="ops-closeout-title" class="ops-pw-title">Ready for closeout?</h2>' +
              '<p class="ops-pw-body">This will stop active labor and send the job to CS closeout.</p>' +
            '</div>' +
            '<button id="ops-closeout-x" type="button" class="ops-pw-x" aria-label="Cancel">&times;</button>' +
          '</div>' +
          '<div class="ops-pw-actions">' +
            '<button id="ops-closeout-cancel" type="button" class="ops-pw-btn ops-pw-btn--cancel">Cancel</button>' +
            '<button id="ops-closeout-confirm" type="button" class="ops-pw-btn ops-pw-btn--primary">Ready for closeout</button>' +
          '</div>' +
        '</div>';

      document.body.appendChild(overlay);
      document.body.classList.add('ops-pw-open');

      var modal = overlay.querySelector('.ops-pw-modal');
      var cancelEl = overlay.querySelector('#ops-closeout-cancel');
      var xEl = overlay.querySelector('#ops-closeout-x');
      var confirmEl = overlay.querySelector('#ops-closeout-confirm');
      var FOCUSABLE = 'a[href],button:not([disabled]),input,select,textarea,[tabindex]:not([tabindex="-1"])';

      function done(result) {
        overlay.remove();
        document.body.classList.remove('ops-pw-open');
        document.removeEventListener('keydown', onKey);
        resolve(result);
      }

      function getFocusable() {
        return Array.prototype.slice.call(modal.querySelectorAll(FOCUSABLE));
      }

      function onKey(e) {
        if (e.key === 'Escape') {
          done(false);
          return;
        }
        if (e.key === 'Enter') {
          done(true);
          return;
        }
        if (e.key !== 'Tab') return;

        var focusable = getFocusable();
        if (!focusable.length) {
          e.preventDefault();
          return;
        }
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }

      cancelEl.addEventListener('click', function () { done(false); });
      xEl.addEventListener('click', function () { done(false); });
      confirmEl.addEventListener('click', function () { done(true); });
      overlay.addEventListener('click', function (e) { if (e.target === overlay) done(false); });
      document.addEventListener('keydown', onKey);

      setTimeout(function () { confirmEl.focus(); }, 50);
    });
  };

  window.__slateOpsPauseWork = function (options) {
    options = options || {};
    var afterHoursRequired = !!(options.afterHoursRequired || options.overtimeRequired);
    return new Promise(function (resolve) {
      var ACTIONS = [
        ['step_away', 'Step away', 'Quick interruption or brief wait. Scheduled breaks and lunch are handled automatically.'],
        ['switch_job', 'Switch job', 'Move to another assigned job or help another tech.']
      ];

      function esc(value) {
        return String(value == null ? '' : value)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
      }

      function actionButton(action) {
        return '<button type="button" class="ops-pw-choice" data-action-choice="' + esc(action[0]) + '">' +
          '<span class="ops-pw-choice__mark" aria-hidden="true"></span>' +
          '<span class="ops-pw-choice__text">' +
            '<span class="ops-pw-choice__title">' + esc(action[1]) + '</span>' +
            '<span class="ops-pw-choice__sub">' + esc(action[2]) + '</span>' +
          '</span>' +
        '</button>';
      }

      var actionButtons = ACTIONS.map(function (action) {
        return actionButton(action);
      }).join('');
      var switchTargets = Array.isArray(options.switchTargets) ? options.switchTargets : [];
      var currentJobId = parseInt(options.currentJobId || options.job_id || options.jobId || 0, 10);
      var seenTargets = {};
      var targetOptions = switchTargets.map(function (job) {
        var id = parseInt(job.job_id || job.id || 0, 10);
        if (!id) return '';
        if (currentJobId && id === currentJobId) return '';
        if (seenTargets[id]) return '';
        seenTargets[id] = true;
        var currentUserId = window.slateOpsSettings && window.slateOpsSettings.user
          ? parseInt(window.slateOpsSettings.user.id || 0, 10)
          : 0;
        var assignedUserId = parseInt(job.assigned_user_id || 0, 10);
        var role = job.switch_role || job.assignment_type || '';
        var roleLabel = 'Unassigned';
        if (role === 'assigned' || (currentUserId && assignedUserId === currentUserId)) {
          roleLabel = 'My job';
        } else if (role === 'help' || (assignedUserId && assignedUserId !== currentUserId)) {
          roleLabel = 'Help';
        }
        var label = job.so_number || job.customer_name || ('Job #' + id);
        var meta = job.customer_name && job.so_number ? ' - ' + job.customer_name : '';
        return '<option value="' + id + '">' + esc(roleLabel + ' - ' + label + meta) + '</option>';
      }).join('');

      var overlay = document.createElement('div');
      overlay.className = 'ops-pw-overlay';
      overlay.innerHTML =
        '<div class="ops-pw-modal" role="dialog" aria-modal="true" aria-labelledby="ops-pw-title">' +
          '<div class="ops-pw-head">' +
            '<div>' +
              '<h2 id="ops-pw-title" class="ops-pw-title">Pause work</h2>' +
              '<p class="ops-pw-body">Choose what you need to do next.</p>' +
            '</div>' +
            '<button id="ops-pw-x" type="button" class="ops-pw-x" aria-label="Cancel">&times;</button>' +
          '</div>' +
          '<div class="ops-pw-content">' +
            '<section class="ops-pw-section">' +
              '<h3 class="ops-pw-section-title">Choose action</h3>' +
              '<div class="ops-pw-choices" role="listbox" aria-label="Pause action">' + actionButtons + '</div>' +
            '</section>' +
            '<div id="ops-pw-err" class="ops-pw-field-err" hidden>Choose an action.</div>' +
            '<div id="ops-pw-detail-fields" class="ops-pw-detail-fields" hidden>' +
              (targetOptions
                ? '<div class="ops-pw-field" id="ops-pw-target-field" hidden>' +
                    '<label class="ops-pw-label" for="ops-pw-target-job">Next job <span class="ops-pw-required">(required)</span></label>' +
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
            '<button id="ops-pw-submit" type="button" class="ops-pw-btn ops-pw-btn--primary" disabled>Choose an action</button>' +
          '</div>' +
          '<div class="ops-pw-clockout">' +
            '<span>Leaving for the day?</span>' +
            '<button id="ops-pw-clockout" type="button" class="ops-pw-clockout-btn">Clock Out</button>' +
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
      var targetFieldEl = overlay.querySelector('#ops-pw-target-field');
      var targetJobEl = overlay.querySelector('#ops-pw-target-job');
      var cancelEl = overlay.querySelector('#ops-pw-cancel');
      var xEl      = overlay.querySelector('#ops-pw-x');
      var submitEl = overlay.querySelector('#ops-pw-submit');
      var clockoutEl = overlay.querySelector('#ops-pw-clockout');
      var selectedAction = '';

      function needsAfterHoursNote() {
        return afterHoursRequired && selectedAction !== '';
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

      function selectedTargetLabel() {
        if (!targetJobEl || !targetJobEl.value) return '';
        return targetJobEl.options[targetJobEl.selectedIndex].text.replace(/\s+/g, ' ').trim();
      }

      function switchNeedsTypedNote() {
        return selectedAction === 'switch_job' && !targetFieldEl;
      }

      function isComplete() {
        if (!selectedAction) return false;
        if (selectedAction === 'switch_job' && targetFieldEl && !selectedTargetLabel()) return false;
        if (switchNeedsTypedNote() && !noteEl.value.trim()) return false;
        if (
          needsAfterHoursNote() &&
          !afterNoteEl.value.trim() &&
          !noteEl.value.trim()
        ) return false;
        return true;
      }

      function updateSubmit() {
        if (!selectedAction) {
          submitEl.textContent = 'Choose an action';
        } else if (selectedAction === 'switch_job' && targetFieldEl && !selectedTargetLabel()) {
          submitEl.textContent = 'Select Job';
        } else if (selectedAction === 'switch_job') {
          submitEl.textContent = 'Pause & Switch';
        } else {
          submitEl.textContent = 'Pause Job';
        }
        submitEl.disabled = !isComplete();
      }

      function placeDetailsAfter(btn) {
        var shouldShowDetails = !!(
          selectedAction === 'switch_job' ||
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
          selectedAction = btn.getAttribute('data-action-choice') || '';
          var isSwitch = selectedAction === 'switch_job';
          overlay.querySelectorAll('.ops-pw-choice').forEach(function (b) {
            b.classList.toggle('is-selected', b === btn);
            b.setAttribute('aria-selected', b === btn ? 'true' : 'false');
          });
          noteFieldEl.hidden = !(isSwitch && switchNeedsTypedNote());
          noteStateEl.textContent = switchNeedsTypedNote() ? '(required)' : '(optional)';
          noteStateEl.className = switchNeedsTypedNote() ? 'ops-pw-required' : 'ops-pw-optional';
          if (targetFieldEl) targetFieldEl.hidden = !isSwitch;
          afterFieldEl.hidden = !needsAfterHoursNote();
          placeDetailsAfter(btn);
          errEl.hidden = true;
          updateSubmit();
        });
      });

      [noteEl, afterNoteEl].forEach(function (el) {
        if (el) el.addEventListener('input', updateSubmit);
      });
      if (targetJobEl) {
        targetJobEl.addEventListener('change', function () {
          noteStateEl.textContent = switchNeedsTypedNote() ? '(required)' : '(optional)';
          noteStateEl.className = switchNeedsTypedNote() ? 'ops-pw-required' : 'ops-pw-optional';
          updateSubmit();
        });
      }

      cancelEl.addEventListener('click', function () { done(null); });
      xEl.addEventListener('click', function () { done(null); });
      overlay.addEventListener('click', function (e) { if (e.target === overlay) done(null); });
      document.addEventListener('keydown', onKey);

      clockoutEl.addEventListener('click', function () {
        done({
          pause_reason: 'END_OF_SHIFT',
          pause_note: '',
          pause_type: 'paused',
          source: 'tech_manual',
          blocked: false,
          requires_clearance: false,
          after_hours: false,
          after_hours_note: '',
          overtime_note: '',
          other_work_can_continue: null,
          target_job_id: 0
        });
      });

      submitEl.addEventListener('click', function () {
        var note = noteEl.value.trim();
        var afterHoursNote = afterNoteEl.value.trim();
        if (!selectedAction) {
          setError('Choose an action.', overlay.querySelector('.ops-pw-choice'));
          return;
        }
        if (selectedAction === 'switch_job' && targetFieldEl && !selectedTargetLabel()) {
          setError('Choose the next job.', targetJobEl);
          return;
        }
        if (switchNeedsTypedNote() && !note) {
          setError('Add a short note for this switch.', noteEl);
          return;
        }
        if (selectedAction === 'switch_job' && !note) {
          note = 'Switched to ' + selectedTargetLabel() + '.';
        }
        if (needsAfterHoursNote() && !afterHoursNote && note) {
          afterHoursNote = note;
        }
        if (needsAfterHoursNote() && !afterHoursNote) {
          setError('Add an after-hours note before pausing.', afterNoteEl);
          return;
        }
        done({
          pause_reason: 'SWITCH_JOB',
          pause_note: note,
          pause_type: 'paused',
          source: 'tech_manual',
          blocked: false,
          requires_clearance: false,
          after_hours: needsAfterHoursNote(),
          after_hours_note: afterHoursNote,
          overtime_note: afterHoursNote,
          other_work_can_continue: null,
          target_job_id: selectedAction === 'switch_job' && targetJobEl && targetJobEl.value ? parseInt(targetJobEl.value, 10) : 0
        });
      });

      setTimeout(function () {
        var first = overlay.querySelector('.ops-pw-choice');
        if (first) first.focus();
      }, 50);
      updateSubmit();
    });
  };
})();
