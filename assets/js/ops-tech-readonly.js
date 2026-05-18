(function () {
  'use strict';

  var settings = window.slateOpsSettings || {};
  var caps = settings.user && settings.user.caps ? settings.user.caps : {};
  if (caps.time_tracking) return;

  var ACTION_SELECTORS = [
    '.slate-work-btn--start',
    '.slate-work-btn--resume',
    '.slate-work-btn--stop',
    '.slate-work-btn--secondary',
    '.ops-tech-help-btn'
  ];

  function textOf(el) {
    return (el && el.textContent ? el.textContent : '').replace(/\s+/g, ' ').trim().toLowerCase();
  }

  function shouldHideButton(button) {
    if (!button || button.tagName !== 'BUTTON') return false;
    if (button.matches(ACTION_SELECTORS.join(','))) return true;
    var text = textOf(button);
    return text === 'start work' ||
      text === 'resume work' ||
      text === 'pause work' ||
      text === 'ready for closeout' ||
      text === 'help' ||
      text.indexOf('help on another job') !== -1;
  }

  function insertBanner() {
    if (document.querySelector('.ops-tech-readonly-banner')) return;
    var mount = document.querySelector('#ops-view');
    var firstPanel = mount && mount.firstElementChild ? mount.firstElementChild : null;
    if (!mount || !firstPanel) return;

    var banner = document.createElement('div');
    banner.className = 'ops-tech-readonly-banner';
    banner.setAttribute('role', 'status');
    banner.textContent = 'Read-only Tech view. Timer actions are available to technicians only.';
    mount.insertBefore(banner, firstPanel);
  }

  function applyReadOnly() {
    document.body.classList.add('ops-tech-readonly');
    insertBanner();
    Array.prototype.forEach.call(document.querySelectorAll('button'), function (button) {
      if (!shouldHideButton(button)) return;
      button.classList.add('ops-tech-readonly-action');
      button.disabled = true;
      button.setAttribute('aria-disabled', 'true');
      button.setAttribute('title', 'Technician action unavailable in read-only view');
    });
  }

  function start() {
    applyReadOnly();
    var observer = new MutationObserver(applyReadOnly);
    observer.observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
