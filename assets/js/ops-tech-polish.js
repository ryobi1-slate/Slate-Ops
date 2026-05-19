(function () {
  'use strict';

  function isNoteButton(button) {
    if (!button || button.tagName !== 'BUTTON') return false;
    var label = (button.getAttribute('aria-label') || '').trim().toLowerCase();
    var text = (button.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
    return label === 'notes' || text === 'edit_note';
  }

  function hideUnwiredNoteButtons() {
    Array.prototype.forEach.call(document.querySelectorAll('button'), function (button) {
      if (!isNoteButton(button)) return;
      button.classList.add('ops-tech-unwired-note-action');
      button.disabled = true;
      button.setAttribute('aria-hidden', 'true');
      button.setAttribute('tabindex', '-1');
      button.setAttribute('title', 'Notes are managed from Customer Service for now');
    });
  }

  function start() {
    hideUnwiredNoteButtons();
    var observer = new MutationObserver(hideUnwiredNoteButtons);
    observer.observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
