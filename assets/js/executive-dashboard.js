/**
 * Slate Ops · Executive Dashboard — page JS
 *
 * Progressive enhancement only.
 * The dashboard renders fully server-side from PHP.
 *
 * Responsibilities:
 *   - Tab switching
 *   - Job table search / filter chips / risk select
 *   - (Other tabs are static; add their filter handlers here when needed.)
 *
 * Scope: everything is queried inside `.so-exec` so the script never
 * touches the rest of the Ops shell.
 */
(function () {
	'use strict';

	var root = document.querySelector('.so-exec');
	if (!root) return;

	var $  = function (sel, r) { return (r || root).querySelector(sel); };
	var $$ = function (sel, r) { return Array.prototype.slice.call((r || root).querySelectorAll(sel)); };

	/* ---------- Tabs ---------- */
	function activateTab(id) {
		var tab = $('.tab[data-tab="' + id + '"]');
		if (!tab) return;
		$$('.tab').forEach(function (x) { x.classList.toggle('active', x === tab); });
		$$('.tab-pane').forEach(function (p) {
			p.classList.toggle('active', p.id === 'pane-' + id);
		});
	}

	function bindTabs() {
		$$('.tab').forEach(function (t) {
			t.addEventListener('click', function () {
				var id = t.getAttribute('data-tab');
				activateTab(id);
				if (window.history && window.URLSearchParams) {
					var url = new URL(window.location.href);
					url.searchParams.set('tab', id);
					window.history.replaceState({}, '', url.toString());
				}
				window.scrollTo({ top: 0, behavior: 'instant' });
			});
		});

		if (window.URLSearchParams) {
			var selected = new URLSearchParams(window.location.search).get('tab');
			if (selected) activateTab(selected);
		}
	}

	/* ---------- Job filters ---------- */
	function bindJobFilters() {
		var pane = $('#pane-jobs');
		if (!pane) return;

		var overChip = $('[data-filter="over"]', pane);
		var missChip = $('[data-filter="missing"]', pane);
		var riskSel  = $('[data-filter="risk"]', pane);
		var search   = $('[data-filter="search"]', pane);
		var counter  = $('[data-jobs-count]', pane);
		var rows     = $$('[data-job-row]', pane);
		var total    = rows.length;

		function apply() {
			var over   = overChip.classList.contains('on');
			var miss   = missChip.classList.contains('on');
			var risk   = riskSel.value;
			var q      = (search.value || '').trim().toLowerCase();
			var shown  = 0;

			rows.forEach(function (row) {
				var ok = true;
				var variance = row.getAttribute('data-variance') || '';
				var logged   = row.getAttribute('data-logged')   || '';
				var rowRisk  = row.getAttribute('data-risk')     || '';
				var so       = (row.getAttribute('data-so')   || '').toLowerCase();
				var cust     = (row.getAttribute('data-cust') || '').toLowerCase();

				if (over && variance.charAt(0) !== '+') ok = false;
				if (miss && logged !== '0h 0m')         ok = false;
				if (risk !== 'all' && rowRisk !== risk) ok = false;
				if (q && so.indexOf(q) === -1 && cust.indexOf(q) === -1) ok = false;

				row.style.display = ok ? '' : 'none';
				if (ok) shown++;
			});

			if (counter) counter.textContent = shown + ' of ' + total;
		}

		if (overChip) overChip.addEventListener('click', function () { overChip.classList.toggle('on'); apply(); });
		if (missChip) missChip.addEventListener('click', function () { missChip.classList.toggle('on'); apply(); });
		if (riskSel)  riskSel.addEventListener('change', apply);
		if (search)   search.addEventListener('input',  apply);
	}

	/* ---------- Generic chip toggles (decorative, no-op filter) ---------- */
	function bindChipToggles() {
		$$('.chip.toggle').forEach(function (c) {
			// skip job-page chips which already have explicit handlers
			if (c.hasAttribute('data-filter')) return;
			c.addEventListener('click', function () { c.classList.toggle('on'); });
		});
	}

	function bindPeriodFilter() {
		var select = $('[data-period-select]');
		if (!select || !select.form) return;
		select.addEventListener('change', function () {
			select.form.submit();
		});
	}

	function init() {
		bindTabs();
		bindPeriodFilter();
		bindJobFilters();
		bindChipToggles();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
