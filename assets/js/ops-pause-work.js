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

  window.__slateOpsPauseWork = function () {
    return new Promise(function (resolve) {
      var REASONS = [
        ['waiting_on_parts',  'Waiting on parts'],
        ['need_supervisor',   'Need supervisor'],
        ['need_another_tech', 'Need another tech'],
        ['need_clarification','Need clarification'],
        ['other',             'Other'],
      ];

      var optionsHtml = REASONS.map(function (r) {
        return '<option value="' + r[0] + '">' + r[1] + '</option>';
      }).join('');

      var overlay = document.createElement('div');
      overlay.className = 'ops-pw-overlay';
      overlay.innerHTML =
        '<div class="ops-pw-modal" role="dialog" aria-modal="true" aria-labelledby="ops-pw-title">' +
          '<h2 id="ops-pw-title" class="ops-pw-title">Pause Work</h2>' +
          '<p class="ops-pw-body">Tell CS why work is stopping.</p>' +
          '<div class="ops-pw-field">' +
            '<label class="ops-pw-label" for="ops-pw-reason">Reason</label>' +
            '<select id="ops-pw-reason" class="ops-pw-select">' +
              '<option value="">Select a reason…</option>' +
              optionsHtml +
            '</select>' +
            '<div id="ops-pw-err" class="ops-pw-field-err" hidden>Please select a reason.</div>' +
          '</div>' +
          '<div class="ops-pw-field">' +
            '<label class="ops-pw-label" for="ops-pw-note">' +
              'Note <span class="ops-pw-optional">(optional)</span>' +
            '</label>' +
            '<textarea id="ops-pw-note" class="ops-pw-textarea"' +
              ' placeholder="Add details…" rows="3"></textarea>' +
          '</div>' +
          '<div class="ops-pw-actions">' +
            '<button id="ops-pw-cancel" type="button" class="ops-pw-btn ops-pw-btn--cancel">Cancel</button>' +
            '<button id="ops-pw-submit" type="button" class="ops-pw-btn ops-pw-btn--primary">Pause Work</button>' +
          '</div>' +
        '</div>';

      document.body.appendChild(overlay);

      var modal    = overlay.querySelector('.ops-pw-modal');
      var reasonEl = overlay.querySelector('#ops-pw-reason');
      var noteEl   = overlay.querySelector('#ops-pw-note');
      var errEl    = overlay.querySelector('#ops-pw-err');
      var cancelEl = overlay.querySelector('#ops-pw-cancel');
      var submitEl = overlay.querySelector('#ops-pw-submit');

      // Focus trap: collect focusable elements within the modal.
      var FOCUSABLE = 'a[href],button:not([disabled]),input,select,textarea,[tabindex]:not([tabindex="-1"])';
      function getFocusable() { return Array.prototype.slice.call(modal.querySelectorAll(FOCUSABLE)); }

      function done(result) {
        overlay.remove();
        document.removeEventListener('keydown', onKey);
        resolve(result);
      }

      function onKey(e) {
        if (e.key === 'Escape') { done(null); return; }
        if (e.key !== 'Tab') return;

        var focusable = getFocusable();
        if (!focusable.length) { e.preventDefault(); return; }

        var first = focusable[0];
        var last  = focusable[focusable.length - 1];

        if (e.shiftKey) {
          if (document.activeElement === first) { e.preventDefault(); last.focus(); }
        } else {
          if (document.activeElement === last)  { e.preventDefault(); first.focus(); }
        }
      }

      cancelEl.addEventListener('click', function () { done(null); });
      overlay.addEventListener('click', function (e) { if (e.target === overlay) done(null); });
      document.addEventListener('keydown', onKey);

      submitEl.addEventListener('click', function () {
        var reason = reasonEl.value;
        if (!reason) {
          errEl.hidden = false;
          reasonEl.style.borderColor = '#ef4444';
          reasonEl.focus();
          return;
        }
        done({
          reason: reasonEl.options[reasonEl.selectedIndex].text,
          note:   noteEl.value.trim(),
        });
      });

      reasonEl.addEventListener('change', function () {
        if (reasonEl.value) {
          errEl.hidden = true;
          reasonEl.style.borderColor = '';
        }
      });

      setTimeout(function () { reasonEl.focus(); }, 50);
    });
  };
})();
