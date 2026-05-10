/**
 * Slate Ops — frontend route/nav access guard.
 *
 * Execution model (important for correctness):
 *   This script is loaded in <head> (blocking). The React app (app.js) is
 *   loaded before </body>. DOMContentLoaded fires AFTER footer scripts run.
 *   Therefore:
 *
 *   1. Guard runs synchronously in <head>: computes whether to block and sets
 *      window.__slateOpsGuardBlocked = true synchronously if so.
 *   2. app.js runs in footer: checks window.__slateOpsGuardBlocked — if true,
 *      skips React mount entirely.
 *   3. DOMContentLoaded fires after all scripts: guard callback replaces
 *      #ops-view content and removes disallowed nav links from the DOM.
 *
 * The flag MUST be set synchronously here (not inside DOMContentLoaded) so
 * that app.js can see it before it attempts to mount.
 *
 * Does NOT hide elements with CSS — removes nav links from the DOM entirely
 * and replaces #ops-view content. No redirect loop. If slateOpsSettings is
 * missing, shows a session-error panel.
 */
(function () {
  'use strict';

  // Segment → allowed_pages key mapping.
  var PAGE_SEGMENTS = {
    exec:      'executive',
    executive: 'executive',
    cs:        'cs',
    tech:      'tech',
    schedule:  'schedule',
    'resource-hub': 'resource-hub',
    admin:     'admin',
    settings:  'settings',
    monitor:   'monitor',
  };

  function getSettings() {
    return (typeof slateOpsSettings !== 'undefined') ? slateOpsSettings : null;
  }

  function currentSegment() {
    var path  = window.location.pathname.replace(/^\/+/, '');
    var parts = path.split('/');
    var opsIdx = parts.indexOf('ops');
    if (opsIdx !== -1 && parts.length > opsIdx + 1) {
      return parts[opsIdx + 1];
    }
    return '';
  }

  function escHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function showPanel(html) {
    var view = document.getElementById('ops-view');
    if (!view) return;
    view.innerHTML = html;
  }

  function accessDeniedPanel(segment) {
    return '<div style="padding:3rem;text-align:center;color:#fff;">'
      + '<span class="material-symbols-outlined" style="font-size:3rem;opacity:.6">lock</span>'
      + '<h2 style="margin:.75rem 0 .5rem;font-size:1.4rem;">Access Denied</h2>'
      + '<p style="opacity:.7;max-width:360px;margin:0 auto;">Your account does not have permission to view'
      + (segment ? ' <strong>' + escHtml(segment) + '</strong>' : ' this page')
      + '.</p></div>';
  }

  function sessionErrorPanel() {
    return '<div style="padding:3rem;text-align:center;color:#fff;">'
      + '<span class="material-symbols-outlined" style="font-size:3rem;opacity:.6">login</span>'
      + '<h2 style="margin:.75rem 0 .5rem;font-size:1.4rem;">Session Expired</h2>'
      + '<p style="opacity:.7;max-width:360px;margin:0 auto;">Your session could not be verified. '
      + '<a href="/wp-login.php" style="color:#d86b19;">Log in again</a> to continue.</p></div>';
  }

  function guardNav(allowedPages) {
    var links = document.querySelectorAll('.ops-nav-link[data-route]');
    for (var i = 0; i < links.length; i++) {
      var link  = links[i];
      var route = link.getAttribute('data-route').replace(/^\//, '');
      var page  = PAGE_SEGMENTS[route] || route;
      if (allowedPages.indexOf(page) === -1) {
        link.parentNode.removeChild(link);
      }
    }
  }

  // ── Synchronous gate decision ────────────────────────────────────────────
  // Must run NOW (not in DOMContentLoaded) so app.js sees the flag.

  var settings      = getSettings();
  var segment       = currentSegment();
  var pageName      = PAGE_SEGMENTS[segment] || segment;
  var allowedPages  = (settings && settings.user && Array.isArray(settings.user.allowed_pages))
                      ? settings.user.allowed_pages : [];

  var sessionError  = (!settings || !settings.user);
  var routeBlocked  = !sessionError
                      && segment !== ''
                      && PAGE_SEGMENTS.hasOwnProperty(segment)
                      && allowedPages.indexOf(pageName) === -1;

  if (sessionError || routeBlocked) {
    // Set flag synchronously — app.js checks this before mounting React.
    window.__slateOpsGuardBlocked = true;
  }

  // ── DOM-dependent work (after DOM is ready) ──────────────────────────────

  document.addEventListener('DOMContentLoaded', function () {
    if (sessionError) {
      showPanel(sessionErrorPanel());
      return;
    }
    if (routeBlocked) {
      showPanel(accessDeniedPanel(segment));
      return;
    }
    // Allowed route: remove nav links the user cannot access.
    guardNav(allowedPages);
  });
}());
