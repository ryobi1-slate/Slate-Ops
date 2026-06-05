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

  function logoutUrl() {
    var settings = window.slateOpsSettings || {};
    if (settings.auth && settings.auth.logoutUrl) return settings.auth.logoutUrl;

    var shellLogout = document.querySelector('.ops-topbar-logout[href]');
    return shellLogout ? shellLogout.getAttribute('href') : '';
  }

  function ensureTechLogout() {
    if (document.querySelector('.ops-tech-logout')) return;

    var href = logoutUrl();
    if (!href) return;

    var link = document.createElement('a');
    link.className = 'ops-tech-logout';
    link.href = href;
    link.title = 'Log out';
    link.setAttribute('aria-label', 'Log out');
    link.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">logout</span>';
    document.body.appendChild(link);
  }

  function start() {
    ensureTechLogout();
    hideUnwiredNoteButtons();
    var observer = new MutationObserver(function () {
      ensureTechLogout();
      hideUnwiredNoteButtons();
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
