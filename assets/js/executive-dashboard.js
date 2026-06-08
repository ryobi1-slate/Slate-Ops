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

	/* ---------- Tech filters ---------- */
	function bindTechFilters() {
		var pane = $('#pane-tech');
		if (!pane) return;

		var search = $('[data-tech-filter="search"]', pane);
		var issuesChip = $('[data-tech-filter="issues"]', pane);
		var activeChip = $('[data-tech-filter="active"]', pane);
		var windowSel = $('[data-tech-filter="window"]', pane);
		var sortSel = $('[data-tech-sort]', pane);
		var counter = $('[data-tech-count]', pane);
		var footCounter = $('[data-tech-foot-count]', pane);
		var tbody = $('tbody', pane);
		var rows = $$('[data-tech-row]', pane);
		var total = rows.length;

		function setPressed(chip) {
			if (!chip) return;
			chip.setAttribute('aria-pressed', chip.classList.contains('on') ? 'true' : 'false');
		}

		function minutes(row, attr) {
			return parseInt(row.getAttribute(attr) || '0', 10) || 0;
		}

		function attentionScore(row) {
			var active = minutes(row, 'data-tech-active');
			var period = minutes(row, 'data-tech-period');
			var variance = minutes(row, 'data-tech-variance');
			var capture = minutes(row, 'data-tech-capture');
			var hasIssues = row.getAttribute('data-tech-issues') === '1';
			var score = 0;
			if (hasIssues) score += 1000;
			if (active > 0 && period <= 0) score += 700;
			if (variance > 0) score += 400 + Math.min(variance, 999);
			if (capture <= 0 && active > 0) score += 250;
			return score;
		}

		function sortRows() {
			if (!tbody) return;
			var mode = sortSel ? sortSel.value : 'attention';
			var sorted = rows.slice().sort(function (a, b) {
				var an = (a.getAttribute('data-tech-name') || '').toLowerCase();
				var bn = (b.getAttribute('data-tech-name') || '').toLowerCase();
				if (mode === 'name') return an.localeCompare(bn);
				if (mode === 'logged-desc') return minutes(b, 'data-tech-period') - minutes(a, 'data-tech-period') || an.localeCompare(bn);
				if (mode === 'jobs-desc') return minutes(b, 'data-tech-touched') - minutes(a, 'data-tech-touched') || an.localeCompare(bn);
				if (mode === 'capture-asc') return minutes(a, 'data-tech-capture') - minutes(b, 'data-tech-capture') || an.localeCompare(bn);
				if (mode === 'capture-desc') return minutes(b, 'data-tech-capture') - minutes(a, 'data-tech-capture') || an.localeCompare(bn);
				if (mode === 'variance-desc') return minutes(b, 'data-tech-variance') - minutes(a, 'data-tech-variance') || an.localeCompare(bn);
				return attentionScore(b) - attentionScore(a) || an.localeCompare(bn);
			});
			sorted.forEach(function (row) { tbody.appendChild(row); });
			rows = sorted;
		}

		function apply() {
			var q = search ? (search.value || '').trim().toLowerCase() : '';
			var issuesOnly = issuesChip && issuesChip.classList.contains('on');
			var activeOnly = activeChip && activeChip.classList.contains('on');
			var windowValue = windowSel ? windowSel.value : 'all';
			var shown = 0;

			sortRows();
			rows.forEach(function (row) {
				var ok = true;
				var name = (row.getAttribute('data-tech-name') || '').toLowerCase();
				var active = minutes(row, 'data-tech-active');
				var today = minutes(row, 'data-tech-today');
				var week = minutes(row, 'data-tech-week');
				var hasIssues = row.getAttribute('data-tech-issues') === '1';

				if (q && name.indexOf(q) === -1) ok = false;
				if (issuesOnly && !hasIssues) ok = false;
				if (activeOnly && active <= 0) ok = false;
				if (windowValue === 'today' && today <= 0) ok = false;
				if (windowValue === 'week' && week <= 0) ok = false;
				if (windowValue === 'no-week' && week > 0) ok = false;

				row.style.display = ok ? '' : 'none';
				if (ok) shown++;
			});

			if (counter) counter.textContent = shown + ' of ' + total + ' techs';
			if (footCounter) footCounter.textContent = 'Showing ' + shown + ' of ' + total;
		}

		if (search) search.addEventListener('input', apply);
		if (issuesChip) issuesChip.addEventListener('click', function () {
			issuesChip.classList.toggle('on');
			setPressed(issuesChip);
			apply();
		});
		if (activeChip) activeChip.addEventListener('click', function () {
			activeChip.classList.toggle('on');
			setPressed(activeChip);
			apply();
		});
		if (windowSel) windowSel.addEventListener('change', apply);
		if (sortSel) sortSel.addEventListener('change', apply);
		setPressed(issuesChip);
		setPressed(activeChip);
		apply();
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
			// skip chips which already have explicit handlers
			if (c.hasAttribute('data-filter')) return;
			if (c.hasAttribute('data-tech-filter')) return;
			c.addEventListener('click', function () { c.classList.toggle('on'); });
		});
	}

	function bindPeriodFilter() {
		var select = $('[data-period-select]');
		if (!select || !select.form) return;
		function syncPeriodTab() {
			var active = $('.tab.active');
			var input = $('[data-period-tab]', select.form);
			if (active && input) input.value = active.getAttribute('data-tab') || 'overview';
		}
		select.form.addEventListener('submit', syncPeriodTab);
		select.addEventListener('change', function () {
			syncPeriodTab();
			select.form.submit();
		});
	}

	function init() {
		bindTabs();
		bindPeriodFilter();
		bindTechFilters();
		bindJobFilters();
		bindChipToggles();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
