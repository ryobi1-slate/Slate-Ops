/**
 * Slate Ops — frontend route/nav access guard.
 *
 * Reads allowed_pages from the server-injected slateOpsSettings object
 * (populated by /me on initial page load) and:
 *   1. Hides nav links the user cannot access.
 *   2. Checks the current URL segment against allowed_pages.
 *      If the segment is disallowed, replaces #ops-view with a denial panel
 *      instead of rendering the React app for that route.
 *   3. If slateOpsSettings is missing (session expired / not logged in),
 *      shows a login-required panel and does not redirect-loop.
 *
 * Runs before the React app mounts so there is no flash of unauthorised content.
 * Does NOT hide elements with CSS — it replaces #ops-view DOM content entirely.
 */
(function () {
  'use strict';

  // Page segments that map to nav items.
  var PAGE_SEGMENTS = {
    exec:      'executive',
    executive: 'executive',
    cs:        'cs',
    tech:      'tech',
    schedule:  'schedule',
    admin:     'admin',
    settings:  'settings',
    monitor:   'monitor',
  };

  function getSettings() {
    return (typeof slateOpsSettings !== 'undefined') ? slateOpsSettings : null;
  }

  function currentSegment() {
    var path = window.location.pathname.replace(/^\/+/, '');
    // Strip leading "ops/"
    var parts = path.split('/');
    var opsIdx = parts.indexOf('ops');
    if (opsIdx !== -1 && parts.length > opsIdx + 1) {
      return parts[opsIdx + 1];
    }
    return '';
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
      + '.</p>'
      + '</div>';
  }

  function sessionErrorPanel() {
    return '<div style="padding:3rem;text-align:center;color:#fff;">'
      + '<span class="material-symbols-outlined" style="font-size:3rem;opacity:.6">login</span>'
      + '<h2 style="margin:.75rem 0 .5rem;font-size:1.4rem;">Session Expired</h2>'
      + '<p style="opacity:.7;max-width:360px;margin:0 auto;">Your session could not be verified. '
      + '<a href="/wp-login.php" style="color:#d86b19;">Log in again</a> to continue.</p>'
      + '</div>';
  }

  function escHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function guardNav(allowedPages) {
    // Remove nav links whose page is not in allowedPages.
    var links = document.querySelectorAll('.ops-nav-link[data-route]');
    for (var i = 0; i < links.length; i++) {
      var link = links[i];
      var route = link.getAttribute('data-route').replace(/^\//, ''); // strip leading /
      var page  = PAGE_SEGMENTS[route] || route;
      if (allowedPages.indexOf(page) === -1) {
        link.parentNode.removeChild(link);
      }
    }
  }

  function run() {
    var settings = getSettings();

    if (!settings || !settings.user) {
      // No settings injected — session error.
      document.addEventListener('DOMContentLoaded', function () {
        showPanel(sessionErrorPanel());
      });
      return;
    }

    var allowedPages = (settings.user && Array.isArray(settings.user.allowed_pages))
      ? settings.user.allowed_pages
      : [];

    var segment  = currentSegment();
    var pageName = PAGE_SEGMENTS[segment] || segment;

    // Guard nav links (DOM may not be ready yet; run after DOMContentLoaded).
    document.addEventListener('DOMContentLoaded', function () {
      guardNav(allowedPages);

      // Guard the current route: if a known page segment is not in allowed_pages,
      // block the React app from rendering that view.
      if (segment && PAGE_SEGMENTS.hasOwnProperty(segment) && allowedPages.indexOf(pageName) === -1) {
        showPanel(accessDeniedPanel(segment));
        // Prevent the React app from replacing our panel.
        if (window.__slateOpsGuardBlocked === undefined) {
          window.__slateOpsGuardBlocked = true;
        }
      }
    });
  }

  run();
})();
