/* Tech Overview redesign — interactions only (progressive enhancement).
 * Server renders all rows; JS handles expand + risk segment + sort/filter.
 */
(function () {
  'use strict';
  var root = document.querySelector('.so-exec .to');
  if (!root) return;
  var $ = function (s, r) { return (r || root).querySelector(s); };
  var $$ = function (s, r) { return [].slice.call((r || root).querySelectorAll(s)); };

  /* ── Expand / collapse focus-job drawers ── */
  $$('.to-row__main').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      if (e.target.closest('a, .to-action-link')) return;
      btn.closest('.to-row').classList.toggle('open');
    });
  });

  /* ── Segmented risk filter ── */
  var rows = $$('.to-row');
  var seg = $('.seg');
  var search = $('[data-tech-search]');
  var sortSel = $('[data-tech-sort]');
  var list = $('.to-list');
  var countEl = $('[data-tech-count]');
  var activeRisk = 'all';

  function apply() {
    var q = (search && search.value || '').trim().toLowerCase();
    var shown = 0;
    rows.forEach(function (row) {
      var risk = row.getAttribute('data-risk');
      var name = (row.getAttribute('data-name') || '').toLowerCase();
      var ok = true;
      if (activeRisk === 'attention') ok = (risk === 'critical' || risk === 'warning');
      else if (activeRisk !== 'all') ok = (risk === activeRisk);
      if (ok && q && name.indexOf(q) === -1) ok = false;
      row.style.display = ok ? '' : 'none';
      if (ok) shown++;
    });
    if (countEl) countEl.textContent = shown + ' of ' + rows.length + ' techs';
    var empty = $('.to-empty-list');
    if (empty) empty.style.display = shown === 0 ? '' : 'none';
  }

  if (seg) {
    $$('button', seg).forEach(function (b) {
      b.addEventListener('click', function () {
        $$('button', seg).forEach(function (x) { x.classList.remove('active'); });
        b.classList.add('active');
        activeRisk = b.getAttribute('data-risk');
        apply();
      });
    });
  }
  if (search) search.addEventListener('input', apply);

  /* ── Sort ── */
  var order = { critical: 0, warning: 1, watch: 2, ok: 3 };
  function sortBy(key) {
    var arr = rows.slice();
    arr.sort(function (a, b) {
      switch (key) {
        case 'attention':
          var ra = order[a.getAttribute('data-risk')], rb = order[b.getAttribute('data-risk')];
          if (ra !== rb) return ra - rb;
          return (+b.getAttribute('data-attention')) - (+a.getAttribute('data-attention'));
        case 'logged-desc':   return (+b.getAttribute('data-period')) - (+a.getAttribute('data-period'));
        case 'capture-asc':   return (+a.getAttribute('data-capture')) - (+b.getAttribute('data-capture'));
        case 'capture-desc':  return (+b.getAttribute('data-capture')) - (+a.getAttribute('data-capture'));
        case 'last-asc':      return (+a.getAttribute('data-last')) - (+b.getAttribute('data-last'));
        case 'name':          return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'));
        default: return 0;
      }
    });
    arr.forEach(function (r) { list.appendChild(r); });
  }
  if (sortSel) sortSel.addEventListener('change', function () { sortBy(sortSel.value); });

  apply();
})();
