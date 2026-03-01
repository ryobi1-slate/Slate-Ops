/**
 * Slate Ops — Phase 0 Admin JS
 * Handles CS, Schedule, and Settings pages in WP Admin.
 * No framework dependencies; uses native fetch + DOM APIs.
 */
(function (win, doc) {
  'use strict';

  if (!win.slateAdminSettings) return;
  var cfg = win.slateAdminSettings;

  // ── API ─────────────────────────────────────────────────────────────

  var api = {
    req: function (method, path, body) {
      var opts = {
        method: method,
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': cfg.nonce,
        },
      };
      if (body !== undefined) {
        opts.body = JSON.stringify(body);
      }
      return fetch(cfg.restRoot + path, opts).then(function (r) {
        return r.json().then(function (data) {
          if (!r.ok) {
            var msg = (data && data.message) ? data.message : 'Request failed (' + r.status + ')';
            throw new Error(msg);
          }
          return data;
        });
      });
    },
    getJobs:          function (params) { return api.req('GET', '/scheduler/jobs' + buildQuery(params)); },
    createJob:        function (d)      { return api.req('POST', '/scheduler/jobs', d); },
    updateJob:        function (uid, d) { return api.req('PUT', '/scheduler/jobs/' + uid, d); },
    deleteJob:        function (uid)    { return api.req('DELETE', '/scheduler/jobs/' + uid); },
    assignWeek:       function (uid, d) { return api.req('POST', '/scheduler/jobs/' + uid + '/assign-week', d); },
    unassignWeek:     function (uid)    { return api.req('POST', '/scheduler/jobs/' + uid + '/unassign-week'); },
    getWeeks:         function ()       { return api.req('GET', '/scheduler/weeks'); },
    regenerateWeeks:  function ()       { return api.req('POST', '/scheduler/weeks/regenerate'); },
    getSettings:      function ()       { return api.req('GET', '/scheduler/settings'); },
    saveSettings:     function (d)      { return api.req('POST', '/scheduler/settings', d); },
  };

  // ── Helpers ─────────────────────────────────────────────────────────

  function buildQuery(params) {
    if (!params) return '';
    var parts = [];
    for (var k in params) {
      if (params[k] !== '' && params[k] !== null && params[k] !== undefined) {
        parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(params[k]));
      }
    }
    return parts.length ? '?' + parts.join('&') : '';
  }

  function esc(s) {
    if (s === null || s === undefined) return '';
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function hoursLabel(minutes) {
    var h = (minutes / 60).toFixed(1);
    return h + ' h';
  }

  function fmtDate(dt) {
    if (!dt) return '—';
    var d = new Date(dt.replace(' ', 'T') + 'Z');
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  }

  // ── Toast ────────────────────────────────────────────────────────────

  var toastEl = null;
  var toastTimer = null;

  function toast(msg, type) {
    type = type || 'info';
    if (!toastEl) {
      toastEl = doc.createElement('div');
      toastEl.id = 'slate-toast';
      doc.body.appendChild(toastEl);
    }
    toastEl.textContent = msg;
    toastEl.className = 'slate-toast slate-toast--' + type + ' slate-toast--visible';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () {
      toastEl.className = 'slate-toast';
    }, 3500);
  }

  // ── Modal ────────────────────────────────────────────────────────────

  var modalEl = null;

  function ensureModal() {
    if (modalEl) return;
    modalEl = doc.createElement('div');
    modalEl.id = 'slate-modal-overlay';
    modalEl.className = 'slate-modal-overlay';
    doc.body.appendChild(modalEl);
    modalEl.addEventListener('click', function (e) {
      if (e.target === modalEl) closeModal();
    });
    doc.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeModal();
    });
  }

  function openModal(html) {
    ensureModal();
    modalEl.innerHTML = '<div class="slate-modal-box">' + html + '</div>';
    modalEl.classList.add('slate-modal-overlay--open');
  }

  function closeModal() {
    if (modalEl) {
      modalEl.classList.remove('slate-modal-overlay--open');
      modalEl.innerHTML = '';
    }
  }

  function modalFormHtml(title, job) {
    job = job || {};
    var isEdit = !!job.job_uid;
    return (
      '<h2 class="slate-modal-title">' + esc(title) + '</h2>' +
      '<form id="slate-job-form">' +
        '<table class="form-table">' +
          '<tr>' +
            '<th><label for="f-customer">Customer Name <span class="required">*</span></label></th>' +
            '<td><input id="f-customer" name="customer_name" type="text" class="regular-text" required ' +
                 'value="' + esc(job.customer_name || '') + '"></td>' +
          '</tr>' +
          '<tr>' +
            '<th><label for="f-type">Job Type <span class="required">*</span></label></th>' +
            '<td>' +
              '<select id="f-type" name="job_type">' +
                ['RV', 'UPFIT', 'COMMERCIAL'].map(function (t) {
                  return '<option value="' + t + '"' + (job.job_type === t ? ' selected' : '') + '>' + t + '</option>';
                }).join('') +
              '</select>' +
            '</td>' +
          '</tr>' +
          '<tr>' +
            '<th><label for="f-hours">Planned Hours <span class="required">*</span></label></th>' +
            '<td><input id="f-hours" name="planned_hours" type="number" step="0.5" min="0.5" class="small-text" required ' +
                 'value="' + esc(job.planned_hours || '') + '"></td>' +
          '</tr>' +
          '<tr>' +
            '<th><label for="f-so">SO#</label></th>' +
            '<td><input id="f-so" name="so_number" type="text" class="regular-text" ' +
                 'value="' + esc(job.so_number || '') + '"></td>' +
          '</tr>' +
          '<tr>' +
            '<th><label for="f-notes">Notes</label></th>' +
            '<td><textarea id="f-notes" name="notes" rows="3" class="large-text">' + esc(job.notes || '') + '</textarea></td>' +
          '</tr>' +
        '</table>' +
        '<p class="submit">' +
          '<button type="submit" class="button button-primary">' + (isEdit ? 'Save Changes' : 'Add Job') + '</button>' +
          '&nbsp;<button type="button" class="button slate-modal-cancel">Cancel</button>' +
          '<span class="slate-form-error" id="slate-form-error"></span>' +
        '</p>' +
      '</form>'
    );
  }

  function bindModalCancel() {
    var btn = doc.querySelector('.slate-modal-cancel');
    if (btn) btn.addEventListener('click', closeModal);
  }

  // ══════════════════════════════════════════════════════════════════════
  // CS PAGE
  // ══════════════════════════════════════════════════════════════════════

  var csPage = {
    el: null,
    state: { jobs: [], filter: { status: '', search: '', missing_so: false } },

    init: function () {
      csPage.el = doc.getElementById('slate-cs-app');
      if (!csPage.el) return;
      csPage.renderShell();
      csPage.load();
    },

    renderShell: function () {
      csPage.el.innerHTML =
        '<h1 class="wp-heading-inline">Customer Service</h1>' +
        '<button id="slate-add-job" class="page-title-action">Add Job</button>' +
        '<hr class="wp-header-end">' +
        '<div class="slate-filters tablenav top">' +
          '<div class="alignleft actions">' +
            '<select id="filter-status">' +
              '<option value="">All Statuses</option>' +
              '<option value="INTAKE">Intake</option>' +
              '<option value="SCHEDULED">Scheduled</option>' +
            '</select>' +
            '&nbsp;<label><input type="checkbox" id="filter-missing-so"> Missing SO#</label>' +
          '</div>' +
          '<div class="alignleft actions" style="margin-left:8px">' +
            '<input type="search" id="filter-search" placeholder="Search customer or SO#" class="regular-text">' +
            '&nbsp;<button class="button" id="filter-apply">Search</button>' +
          '</div>' +
        '</div>' +
        '<div id="slate-jobs-wrap"></div>';

      doc.getElementById('slate-add-job').addEventListener('click', csPage.openAddModal);
      doc.getElementById('filter-apply').addEventListener('click', csPage.applyFilter);
      doc.getElementById('filter-status').addEventListener('change', csPage.applyFilter);
      doc.getElementById('filter-missing-so').addEventListener('change', csPage.applyFilter);
      doc.getElementById('filter-search').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') csPage.applyFilter();
      });
    },

    applyFilter: function () {
      csPage.state.filter.status     = doc.getElementById('filter-status').value;
      csPage.state.filter.search     = doc.getElementById('filter-search').value;
      csPage.state.filter.missing_so = doc.getElementById('filter-missing-so').checked;
      csPage.load();
    },

    load: function () {
      var wrap = doc.getElementById('slate-jobs-wrap');
      if (wrap) wrap.innerHTML = '<p class="slate-loading">Loading jobs…</p>';

      var params = {};
      if (csPage.state.filter.status)     params.status     = csPage.state.filter.status;
      if (csPage.state.filter.search)     params.search     = csPage.state.filter.search;
      if (csPage.state.filter.missing_so) params.missing_so = 1;

      api.getJobs(params).then(function (jobs) {
        csPage.state.jobs = jobs;
        csPage.renderTable(jobs);
      }).catch(function (err) {
        if (wrap) wrap.innerHTML = '<div class="notice notice-error"><p>' + esc(err.message) + '</p></div>';
      });
    },

    renderTable: function (jobs) {
      var wrap = doc.getElementById('slate-jobs-wrap');
      if (!wrap) return;

      if (!jobs.length) {
        wrap.innerHTML = '<p>No jobs found.</p>';
        return;
      }

      var rows = jobs.map(function (j) {
        return (
          '<tr data-uid="' + esc(j.job_uid) + '">' +
            '<td>' + esc(j.customer_name) + '</td>' +
            '<td><span class="slate-badge slate-badge--type">' + esc(j.job_type) + '</span></td>' +
            '<td>' + hoursLabel(j.planned_minutes) + '</td>' +
            '<td>' + esc(j.so_number || '—') + '</td>' +
            '<td><span class="slate-badge slate-badge--' + (j.scheduling_status === 'SCHEDULED' ? 'scheduled' : 'intake') + '">' +
                esc(j.scheduling_status) + '</span></td>' +
            '<td>' + esc(j.target_week_id || '—') + '</td>' +
            '<td>' + fmtDate(j.updated_at) + '</td>' +
            '<td class="slate-actions">' +
              '<button class="button button-small btn-edit-job">Edit</button> ' +
              '<button class="button button-small btn-delete-job">Delete</button>' +
            '</td>' +
          '</tr>'
        );
      }).join('');

      wrap.innerHTML =
        '<table class="wp-list-table widefat fixed striped">' +
          '<thead><tr>' +
            '<th>Customer</th>' +
            '<th>Job Type</th>' +
            '<th>Planned Hrs</th>' +
            '<th>SO#</th>' +
            '<th>Status</th>' +
            '<th>Week</th>' +
            '<th>Updated</th>' +
            '<th>Actions</th>' +
          '</tr></thead>' +
          '<tbody>' + rows + '</tbody>' +
        '</table>';

      wrap.querySelectorAll('.btn-edit-job').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var uid = btn.closest('tr').dataset.uid;
          csPage.openEditModal(uid);
        });
      });
      wrap.querySelectorAll('.btn-delete-job').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var uid = btn.closest('tr').dataset.uid;
          csPage.confirmDelete(uid);
        });
      });
    },

    openAddModal: function () {
      openModal(modalFormHtml('Add Job', null));
      bindModalCancel();
      doc.getElementById('slate-job-form').addEventListener('submit', function (e) {
        e.preventDefault();
        csPage.submitForm(null);
      });
    },

    openEditModal: function (uid) {
      var job = csPage.state.jobs.find(function (j) { return j.job_uid === uid; });
      if (!job) return;
      openModal(modalFormHtml('Edit Job', job));
      bindModalCancel();
      doc.getElementById('slate-job-form').addEventListener('submit', function (e) {
        e.preventDefault();
        csPage.submitForm(uid);
      });
    },

    submitForm: function (uid) {
      var form = doc.getElementById('slate-job-form');
      var errEl = doc.getElementById('slate-form-error');
      errEl.textContent = '';

      var data = {
        customer_name: form.customer_name.value.trim(),
        job_type:      form.job_type.value,
        planned_hours: parseFloat(form.planned_hours.value),
        so_number:     form.so_number.value.trim() || null,
        notes:         form.notes.value.trim() || null,
      };

      var submitBtn = form.querySelector('[type=submit]');
      submitBtn.disabled = true;

      var promise = uid ? api.updateJob(uid, data) : api.createJob(data);
      promise.then(function () {
        closeModal();
        toast(uid ? 'Job updated.' : 'Job created.', 'success');
        csPage.load();
      }).catch(function (err) {
        errEl.textContent = ' ' + err.message;
        submitBtn.disabled = false;
      });
    },

    confirmDelete: function (uid) {
      var job = csPage.state.jobs.find(function (j) { return j.job_uid === uid; });
      if (!job) return;
      if (!confirm('Delete job for "' + job.customer_name + '"? This cannot be undone.')) return;
      api.deleteJob(uid).then(function () {
        toast('Job deleted.', 'success');
        csPage.load();
      }).catch(function (err) {
        toast(err.message, 'error');
      });
    },
  };

  // ══════════════════════════════════════════════════════════════════════
  // SCHEDULE PAGE
  // ══════════════════════════════════════════════════════════════════════

  var schedulePage = {
    el: null,
    state: { activeTab: 'week', weeks: [], jobs: [] },

    init: function () {
      schedulePage.el = doc.getElementById('slate-schedule-app');
      if (!schedulePage.el) return;
      schedulePage.renderShell();
      schedulePage.loadAll();
    },

    renderShell: function () {
      schedulePage.el.innerHTML =
        '<h1>Schedule</h1>' +
        '<nav class="nav-tab-wrapper">' +
          '<a href="#" class="nav-tab nav-tab-active" data-tab="week">Week View</a>' +
          '<a href="#" class="nav-tab" data-tab="jobs">Job View</a>' +
        '</nav>' +
        '<div id="sched-tab-week" class="slate-tab-content"></div>' +
        '<div id="sched-tab-jobs" class="slate-tab-content" hidden></div>';

      schedulePage.el.querySelectorAll('.nav-tab').forEach(function (tab) {
        tab.addEventListener('click', function (e) {
          e.preventDefault();
          schedulePage.switchTab(tab.dataset.tab);
        });
      });
    },

    switchTab: function (name) {
      schedulePage.state.activeTab = name;
      schedulePage.el.querySelectorAll('.nav-tab').forEach(function (t) {
        t.classList.toggle('nav-tab-active', t.dataset.tab === name);
      });
      doc.getElementById('sched-tab-week').hidden = (name !== 'week');
      doc.getElementById('sched-tab-jobs').hidden = (name !== 'jobs');
    },

    loadAll: function () {
      var wrapW = doc.getElementById('sched-tab-week');
      var wrapJ = doc.getElementById('sched-tab-jobs');
      if (wrapW) wrapW.innerHTML = '<p class="slate-loading">Loading…</p>';
      if (wrapJ) wrapJ.innerHTML = '<p class="slate-loading">Loading…</p>';

      Promise.all([api.getWeeks(), api.getJobs()]).then(function (results) {
        schedulePage.state.weeks = results[0];
        schedulePage.state.jobs  = results[1];
        schedulePage.renderWeekView();
        schedulePage.renderJobView();
      }).catch(function (err) {
        var msg = '<div class="notice notice-error"><p>' + esc(err.message) + '</p></div>';
        if (wrapW) wrapW.innerHTML = msg;
        if (wrapJ) wrapJ.innerHTML = msg;
      });
    },

    renderWeekView: function () {
      var wrap = doc.getElementById('sched-tab-week');
      if (!wrap) return;

      var weeks = schedulePage.state.weeks;
      if (!weeks.length) {
        wrap.innerHTML = '<p>No weeks found. Generate weeks from the Settings page.</p>';
        return;
      }

      var html = weeks.map(function (w) {
        var pct = w.planned_limit_minutes > 0
          ? Math.min(100, Math.round(w.current_allocated_minutes / w.planned_limit_minutes * 100))
          : 0;
        var allocH  = hoursLabel(w.current_allocated_minutes);
        var limitH  = w.planned_limit_minutes > 0 ? hoursLabel(w.planned_limit_minutes) : '—';
        var totalH  = w.total_capacity_minutes > 0 ? hoursLabel(w.total_capacity_minutes) : '—';

        var jobRows = (w.jobs && w.jobs.length)
          ? w.jobs.map(function (j) {
              return (
                '<tr>' +
                  '<td>' + esc(j.customer_name) + '</td>' +
                  '<td>' + esc(j.job_type) + '</td>' +
                  '<td>' + hoursLabel(j.planned_minutes) + '</td>' +
                  '<td>' + esc(j.so_number || '—') + '</td>' +
                  '<td>' +
                    '<button class="button button-small btn-unassign" data-uid="' + esc(j.job_uid) + '" data-week="' + esc(w.week_id) + '">Unassign</button>' +
                  '</td>' +
                '</tr>'
              );
            }).join('')
          : '<tr><td colspan="5" class="slate-empty-row">No jobs assigned</td></tr>';

        return (
          '<div class="slate-week-block">' +
            '<div class="slate-week-header">' +
              '<strong>' + esc(w.label) + '</strong>' +
              ' <span class="slate-week-id">(' + esc(w.week_id) + ')</span>' +
              '<div class="slate-week-cap">' +
                'Allocated: <strong>' + allocH + '</strong> / Limit: ' + limitH + ' / Capacity: ' + totalH +
                (w.planned_limit_minutes > 0
                  ? ' <span class="slate-cap-bar"><span class="slate-cap-fill" style="width:' + pct + '%"></span></span> ' + pct + '%'
                  : '') +
              '</div>' +
            '</div>' +
            '<table class="wp-list-table widefat fixed striped slate-week-jobs">' +
              '<thead><tr><th>Customer</th><th>Type</th><th>Hrs</th><th>SO#</th><th></th></tr></thead>' +
              '<tbody>' + jobRows + '</tbody>' +
            '</table>' +
          '</div>'
        );
      }).join('');

      wrap.innerHTML = html;

      wrap.querySelectorAll('.btn-unassign').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var uid = btn.dataset.uid;
          schedulePage.doUnassign(uid, btn);
        });
      });
    },

    renderJobView: function () {
      var wrap = doc.getElementById('sched-tab-jobs');
      if (!wrap) return;

      var allJobs = schedulePage.state.jobs;
      var intake    = allJobs.filter(function (j) { return j.scheduling_status === 'INTAKE'; });
      var scheduled = allJobs.filter(function (j) { return j.scheduling_status === 'SCHEDULED'; });
      var weekOpts  = schedulePage.state.weeks.map(function (w) {
        return '<option value="' + esc(w.week_id) + '">' + esc(w.week_id) + ' — ' + esc(w.label) + '</option>';
      }).join('');

      var intakeRows = intake.length
        ? intake.map(function (j) {
            return (
              '<tr data-uid="' + esc(j.job_uid) + '">' +
                '<td>' + esc(j.customer_name) + '</td>' +
                '<td>' + esc(j.job_type) + '</td>' +
                '<td>' + hoursLabel(j.planned_minutes) + '</td>' +
                '<td>' + esc(j.so_number || '—') + '</td>' +
                '<td>' +
                  '<select class="week-assign-select">' +
                    '<option value="">— Assign Week —</option>' +
                    weekOpts +
                  '</select>' +
                '</td>' +
              '</tr>'
            );
          }).join('')
        : '<tr><td colspan="5" class="slate-empty-row">No intake jobs</td></tr>';

      var schedRows = scheduled.length
        ? scheduled.map(function (j) {
            return (
              '<tr data-uid="' + esc(j.job_uid) + '">' +
                '<td>' + esc(j.customer_name) + '</td>' +
                '<td>' + esc(j.job_type) + '</td>' +
                '<td>' + hoursLabel(j.planned_minutes) + '</td>' +
                '<td>' + esc(j.so_number || '—') + '</td>' +
                '<td>' + esc(j.target_week_id || '') + '</td>' +
                '<td>' +
                  '<select class="week-assign-select">' +
                    '<option value="">— Change Week —</option>' +
                    weekOpts +
                  '</select>' +
                  ' <button class="button button-small btn-unassign" data-uid="' + esc(j.job_uid) + '">Unassign</button>' +
                '</td>' +
              '</tr>'
            );
          }).join('')
        : '<tr><td colspan="6" class="slate-empty-row">No scheduled jobs</td></tr>';

      wrap.innerHTML =
        '<h2>Intake Jobs</h2>' +
        '<table class="wp-list-table widefat fixed striped">' +
          '<thead><tr><th>Customer</th><th>Type</th><th>Hrs</th><th>SO#</th><th>Assign Week</th></tr></thead>' +
          '<tbody>' + intakeRows + '</tbody>' +
        '</table>' +
        '<h2 style="margin-top:24px">Scheduled Jobs</h2>' +
        '<table class="wp-list-table widefat fixed striped">' +
          '<thead><tr><th>Customer</th><th>Type</th><th>Hrs</th><th>SO#</th><th>Week</th><th>Actions</th></tr></thead>' +
          '<tbody>' + schedRows + '</tbody>' +
        '</table>';

      // Assign week on dropdown change.
      wrap.querySelectorAll('.week-assign-select').forEach(function (sel) {
        sel.addEventListener('change', function () {
          var weekId = sel.value;
          if (!weekId) return;
          var uid = sel.closest('tr').dataset.uid;
          sel.disabled = true;
          api.assignWeek(uid, { target_week_id: weekId }).then(function () {
            toast('Job assigned to ' + weekId + '.', 'success');
            schedulePage.loadAll();
          }).catch(function (err) {
            toast(err.message, 'error');
            sel.disabled = false;
            sel.value = '';
          });
        });
      });

      // Unassign buttons in Job View.
      wrap.querySelectorAll('.btn-unassign').forEach(function (btn) {
        btn.addEventListener('click', function () {
          schedulePage.doUnassign(btn.dataset.uid, btn);
        });
      });
    },

    doUnassign: function (uid, btn) {
      if (btn) btn.disabled = true;
      api.unassignWeek(uid).then(function () {
        toast('Job returned to Intake.', 'success');
        schedulePage.loadAll();
      }).catch(function (err) {
        toast(err.message, 'error');
        if (btn) btn.disabled = false;
      });
    },
  };

  // ══════════════════════════════════════════════════════════════════════
  // SETTINGS PAGE
  // ══════════════════════════════════════════════════════════════════════

  var settingsPage = {
    el: null,

    init: function () {
      settingsPage.el = doc.getElementById('slate-settings-app');
      if (!settingsPage.el) return;
      settingsPage.el.innerHTML = '<p class="slate-loading">Loading…</p>';

      api.getSettings().then(function (s) {
        settingsPage.render(s);
      }).catch(function (err) {
        settingsPage.el.innerHTML = '<div class="notice notice-error"><p>' + esc(err.message) + '</p></div>';
      });
    },

    render: function (s) {
      var tableStatus = function (ok) {
        return ok
          ? '<span class="slate-badge slate-badge--scheduled">&#10003; Exists</span>'
          : '<span class="slate-badge slate-badge--intake">&#10007; Missing</span>';
      };

      settingsPage.el.innerHTML =
        '<h1>Slate Ops Settings</h1>' +

        '<form id="slate-settings-form">' +
          '<h2>Phase 0 Scheduler</h2>' +
          '<table class="form-table">' +
            '<tr>' +
              '<th><label for="s-enabled">Enable Phase 0 Screens</label></th>' +
              '<td><input type="checkbox" id="s-enabled" name="enabled"' + (s.enabled ? ' checked' : '') + '>' +
                  '<p class="description">Show CS and Schedule pages in the Slate Ops admin menu.</p></td>' +
            '</tr>' +
            '<tr>' +
              '<th><label for="s-weeks">Weeks Ahead</label></th>' +
              '<td><input type="number" id="s-weeks" name="weeks_ahead" class="small-text" min="1" max="52" value="' + esc(s.weeks_ahead) + '">' +
                  '<p class="description">How many weeks ahead to generate when seeding (default 12).</p></td>' +
            '</tr>' +
            '<tr>' +
              '<th><label for="s-capacity">Default Capacity (minutes / week)</label></th>' +
              '<td><input type="number" id="s-capacity" name="capacity_minutes" class="small-text" min="0" value="' + esc(s.capacity_minutes) + '">' +
                  '<p class="description">Optional. Stored now, enforced in Phase 1. 0 = not set.</p></td>' +
            '</tr>' +
          '</table>' +
          '<p class="submit">' +
            '<button type="submit" class="button button-primary">Save Settings</button>' +
            '<span id="slate-settings-msg" style="margin-left:12px;vertical-align:middle;"></span>' +
          '</p>' +
        '</form>' +

        '<hr>' +

        '<h2>System Info</h2>' +
        '<table class="form-table">' +
          '<tr><th>Plugin Version</th><td>' + esc(s.version) + '</td></tr>' +
          '<tr><th>wp_slate_jobs</th><td>' + tableStatus(s.tables && s.tables.slate_jobs) + '</td></tr>' +
          '<tr><th>wp_slate_weeks</th><td>' + tableStatus(s.tables && s.tables.slate_weeks) + '</td></tr>' +
        '</table>' +

        '<p>' +
          '<button type="button" id="btn-regen-weeks" class="button">Regenerate Weeks</button>' +
          '<span id="regen-msg" style="margin-left:12px;vertical-align:middle;"></span>' +
        '</p>';

      doc.getElementById('slate-settings-form').addEventListener('submit', function (e) {
        e.preventDefault();
        settingsPage.save();
      });

      doc.getElementById('btn-regen-weeks').addEventListener('click', function () {
        settingsPage.regenWeeks();
      });
    },

    save: function () {
      var form = doc.getElementById('slate-settings-form');
      var msg  = doc.getElementById('slate-settings-msg');
      msg.textContent = 'Saving…';

      var data = {
        enabled:          form.enabled.checked,
        weeks_ahead:      parseInt(form.weeks_ahead.value, 10),
        capacity_minutes: parseInt(form.capacity_minutes.value, 10),
      };

      api.saveSettings(data).then(function () {
        msg.textContent = 'Settings saved.';
        toast('Settings saved.', 'success');
        setTimeout(function () { msg.textContent = ''; }, 3000);
      }).catch(function (err) {
        msg.textContent = err.message;
        toast(err.message, 'error');
      });
    },

    regenWeeks: function () {
      var btn = doc.getElementById('btn-regen-weeks');
      var msg = doc.getElementById('regen-msg');
      btn.disabled = true;
      msg.textContent = 'Generating…';
      api.regenerateWeeks().then(function () {
        msg.textContent = 'Weeks regenerated.';
        toast('Weeks regenerated.', 'success');
        setTimeout(function () { msg.textContent = ''; btn.disabled = false; }, 3000);
      }).catch(function (err) {
        msg.textContent = err.message;
        toast(err.message, 'error');
        btn.disabled = false;
      });
    },
  };

  // ── Init ─────────────────────────────────────────────────────────────

  doc.addEventListener('DOMContentLoaded', function () {
    switch (cfg.page) {
      case 'slate-ops-cs':       csPage.init();       break;
      case 'slate-ops-schedule': schedulePage.init(); break;
      case 'slate-ops-settings': settingsPage.init(); break;
    }
  });

}(window, document));
