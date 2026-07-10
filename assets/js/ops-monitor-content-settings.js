(function () {
  'use strict';

  var PANEL_ID = 'ops-monitor-content-settings';
  var API_ROOT = (window.slateOpsSettings && window.slateOpsSettings.api && window.slateOpsSettings.api.root) || '/wp-json/slate-ops/v1';
  var NONCE = (window.slateOpsSettings && window.slateOpsSettings.api && window.slateOpsSettings.api.nonce) || '';

  function esc(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function request(path, options) {
    options = options || {};
    var headers = Object.assign({
      'Content-Type': 'application/json',
      'X-WP-Nonce': NONCE
    }, options.headers || {});

    return fetch(API_ROOT.replace(/\/$/, '') + path, Object.assign({}, options, { headers: headers }))
      .then(function (response) {
        return response.text().then(function (text) {
          var data = {};
          try {
            data = text ? JSON.parse(text) : {};
          } catch (e) {
            data = {};
          }
          if (!response.ok) {
            throw new Error(data.message || 'Request failed');
          }
          return data;
        });
      });
  }

  function field(id) {
    return document.getElementById(id);
  }

  function defaultContent() {
    return {
      tenmm_enabled: false,
      tenmm_rotation: 'weekday',
      tenmm_entries: '',
      fun_days_enabled: false,
      fun_days_entries: ''
    };
  }

  function render(settings) {
    var host = document.querySelector('#ops-view > div') || document.getElementById('ops-view');
    if (!host || document.getElementById(PANEL_ID)) return;

    var content = Object.assign(defaultContent(), (settings && settings.shop_monitor_content) || {});

    var section = document.createElement('section');
    section.id = PANEL_ID;
    section.className = 'bg-white rounded-xl shadow-sm p-6 mb-8';
    section.innerHTML = [
      '<div class="mb-6">',
      '  <h2 class="font-bold text-lg text-slate-800 mb-1">Shop Monitor Content</h2>',
      '  <p class="text-xs text-slate-400 uppercase tracking-wider">Team Calendar / Shop Monitor Display</p>',
      '</div>',
      '<div class="grid grid-cols-1 xl:grid-cols-2 gap-8">',
      '  <div>',
      '    <div class="flex items-start justify-between gap-4 mb-4">',
      '      <div>',
      '        <h3 class="font-bold text-sm text-slate-700 mb-1">10MM Report</h3>',
      '        <p class="text-xs text-slate-500">One entry per line: CATEGORY | TEXT</p>',
      '      </div>',
      '      <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input id="monitor-tenmm-enabled" type="checkbox"' + (content.tenmm_enabled ? ' checked' : '') + '> Enabled</label>',
      '    </div>',
      '    <label class="block text-sm font-bold text-slate-700 mb-2" for="monitor-tenmm-rotation">Rotation Mode</label>',
      '    <select id="monitor-tenmm-rotation" class="w-full border border-slate-200 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#d86b19] focus:border-[#d86b19] mb-4">',
      '      <option value="weekday"' + (content.tenmm_rotation === 'weekday' ? ' selected' : '') + '>weekday</option>',
      '      <option value="sequential"' + (content.tenmm_rotation === 'sequential' ? ' selected' : '') + '>sequential</option>',
      '      <option value="random"' + (content.tenmm_rotation === 'random' ? ' selected' : '') + '>random</option>',
      '    </select>',
      '    <label class="block text-sm font-bold text-slate-700 mb-2" for="monitor-tenmm-entries">Entries</label>',
      '    <textarea id="monitor-tenmm-entries" rows="10" class="w-full border border-slate-200 rounded-md px-3 py-2 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-[#d86b19] focus:border-[#d86b19]" placeholder="SHOP DEBATE | Best cabinet finish: light gray, dark gray, wood, or black?">' + esc(content.tenmm_entries) + '</textarea>',
      '    <p class="text-xs text-slate-500 mt-2">Supported categories: WEIRD BUT TRUE, LITTLE INSTRUCTION, DAD JOKE, SHOP TIP, SHOP DEBATE</p>',
      '    <p class="text-xs text-slate-500 mt-1">Example: SHOP DEBATE | Best cabinet finish: light gray, dark gray, wood, or black?</p>',
      '  </div>',
      '  <div>',
      '    <div class="flex items-start justify-between gap-4 mb-4">',
      '      <div>',
      '        <h3 class="font-bold text-sm text-slate-700 mb-1">Fun Days</h3>',
      '        <p class="text-xs text-slate-500">One entry per line: MM/DD | NAME</p>',
      '      </div>',
      '      <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input id="monitor-fun-days-enabled" type="checkbox"' + (content.fun_days_enabled ? ' checked' : '') + '> Enabled</label>',
      '    </div>',
      '    <label class="block text-sm font-bold text-slate-700 mb-2" for="monitor-fun-days-entries">Entries</label>',
      '    <textarea id="monitor-fun-days-entries" rows="13" class="w-full border border-slate-200 rounded-md px-3 py-2 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-[#d86b19] focus:border-[#d86b19]" placeholder="07/16 | National Ice Cream Day">' + esc(content.fun_days_entries) + '</textarea>',
      '    <p class="text-xs text-slate-500 mt-2">Example: 07/16 | National Ice Cream Day</p>',
      '  </div>',
      '</div>',
      '<div class="pt-6 mt-6 border-t border-slate-100 flex justify-end items-center gap-3">',
      '  <span id="monitor-content-status" class="text-xs font-bold"></span>',
      '  <button id="monitor-content-save" class="bg-slate-700 hover:bg-slate-800 text-white font-bold py-2 px-6 rounded shadow-sm text-sm">Save Shop Monitor Content</button>',
      '</div>'
    ].join('');

    host.appendChild(section);
    field('monitor-content-save').addEventListener('click', function () {
      save();
    });
  }

  function hydrate(settings) {
    if (!settings || !document.getElementById(PANEL_ID)) return;
    var content = Object.assign(defaultContent(), settings.shop_monitor_content || {});
    field('monitor-tenmm-enabled').checked = !!content.tenmm_enabled;
    field('monitor-tenmm-rotation').value = content.tenmm_rotation || 'weekday';
    field('monitor-tenmm-entries').value = content.tenmm_entries || '';
    field('monitor-fun-days-enabled').checked = !!content.fun_days_enabled;
    field('monitor-fun-days-entries').value = content.fun_days_entries || '';
  }

  function save() {
    var button = field('monitor-content-save');
    var status = field('monitor-content-status');
    if (!button || !status) return;

    button.disabled = true;
    button.textContent = 'Saving...';
    status.className = 'text-xs font-bold text-slate-500';
    status.textContent = '';

    var nextContent = {
      tenmm_enabled: field('monitor-tenmm-enabled').checked,
      tenmm_rotation: field('monitor-tenmm-rotation').value,
      tenmm_entries: field('monitor-tenmm-entries').value,
      fun_days_enabled: field('monitor-fun-days-enabled').checked,
      fun_days_entries: field('monitor-fun-days-entries').value
    };

    request('/settings').then(function (currentSettings) {
      return request('/settings', {
        method: 'POST',
        body: JSON.stringify(Object.assign({}, currentSettings, {
          shop_monitor_content: nextContent
        }))
      });
    }).then(function (updated) {
      status.className = 'text-xs font-bold text-green-600';
      status.textContent = 'Saved';
      hydrate(updated);
    }).catch(function (error) {
      status.className = 'text-xs font-bold text-red-600';
      status.textContent = error.message || 'Save failed';
    }).finally(function () {
      button.disabled = false;
      button.textContent = 'Save Shop Monitor Content';
      window.setTimeout(function () {
        if (status.textContent === 'Saved') status.textContent = '';
      }, 2200);
    });
  }

  function init() {
    if (!document.body.classList.contains('ops-page-settings')) return;
    var attempts = 0;
    var timer = window.setInterval(function () {
      attempts += 1;
      render(null);
      if (document.getElementById(PANEL_ID) || attempts > 40) {
        window.clearInterval(timer);
        request('/settings').then(hydrate).catch(function () {});
      }
    }, 150);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
