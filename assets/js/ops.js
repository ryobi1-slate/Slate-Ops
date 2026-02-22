(function(){
  const $ = (sel, root=document) => root.querySelector(sel);
  const $$ = (sel, root=document) => Array.from(root.querySelectorAll(sel));

  const api = {
    async req(path, opts={}){
      const url = slateOpsSettings.restRoot + path;
      const headers = Object.assign({
        'Content-Type': 'application/json',
        'X-WP-Nonce': slateOpsSettings.nonce
      }, opts.headers||{});
      const res = await fetch(url, Object.assign({}, opts, {headers}));
      const txt = await res.text();
      let data = null;
      try { data = txt ? JSON.parse(txt) : null; } catch(e){ data = {raw: txt}; }
      if(!res.ok){
        const msg = (data && data.message) ? data.message : 'Request failed';
        const err = new Error(msg);
        err.data = data;
        throw err;
      }
      return data;
    },
    me(){ return this.req('/me'); },
    settings(){ return this.req('/settings'); },
    users(){ return this.req('/users'); },
    updateSettings(payload){ return this.req('/settings', {method:'POST', body: JSON.stringify(payload)}); },
    jobs(q=''){ return this.req('/jobs' + q); },
    job(id){ return this.req('/jobs/' + id); },
    createJob(payload){ return this.req('/jobs', {method:'POST', body: JSON.stringify(payload)}); },
    setSO(id, so){ return this.req('/jobs/' + id + '/so', {method:'POST', body: JSON.stringify({so_number: so})}); },
    intake(id, payload){ return this.req('/jobs/' + id + '/so', {method:'POST', body: JSON.stringify(payload)}); },
    assign(id, userId){ return this.req('/jobs/' + id + '/assign', {method:'POST', body: JSON.stringify({assigned_user_id: userId})}); },
    schedule(id, payload){ return this.req('/jobs/' + id + '/schedule', {method:'POST', body: JSON.stringify(payload)}); },
    setStatus(id, status, note=''){ return this.req('/jobs/' + id + '/status', {method:'POST', body: JSON.stringify({status, note})}); },
    timeStart(job_id, reason, note){ return this.req('/time/start', {method:'POST', body: JSON.stringify({job_id, reason, note})}); },
    timeStop(){ return this.req('/time/stop', {method:'POST'}); },
    timeActive(){ return this.req('/time/active'); },
    correction(payload){ return this.req('/time/correction', {method:'POST', body: JSON.stringify(payload)}); },
    supervisorQueues(){ return this.req('/supervisor/queues'); },
    editJob(id, payload){ return this.req('/jobs/' + id, {method:'PATCH', body: JSON.stringify(payload)}); },
    addNote(id, note){ return this.req('/jobs/' + id + '/notes', {method:'POST', body: JSON.stringify({note})}); },
    updateUserRole(id, role){ return this.req('/users/' + id + '/role', {method:'POST', body: JSON.stringify({role})}); }
  };

  const state = {
    route: '/',
    jobs: [],
    settings: null,
    active: null,
    timerInterval: null,
  };

  function setActiveNav(route){
    $$('.ops-nav-link').forEach(a => {
      const r = a.getAttribute('data-route');
      a.classList.toggle('active', r === route);
    });
  }

  function setPageTitle(title){
    const el = document.getElementById('ops-page-title');
    if (el) el.textContent = title;
  }

  function fmtStatus(s){
    if(!s) return '';
    return s.replaceAll('_',' ');
  }

  function badgeClass(status){
    const s = (status||'').toUpperCase();
    if (s === 'COMPLETE') return 'complete';
    if (s === 'IN_PROGRESS' || s === 'PENDING_QC') return 'progress';
    return 'scheduled';
  }

  function minutesToHours(min){
    const h = (min/60);
    return (Math.round(h*10)/10).toFixed(1);
  }

  function router(){
    const path = window.location.pathname.replace(/^\/ops/, '') || '/';
    state.route = path === '' ? '/' : path;
    render();
  }

  function linkify(){
    $$('#slate-ops-app a[data-route]').forEach(a => {
      a.onclick = (e) => {
        e.preventDefault();
        const r = a.getAttribute('data-route');
        window.history.pushState({}, '', '/ops' + (r === '/' ? '/' : r));
        router();
      };
    });
  }

  function view(html){
    const root = $('#ops-view');
    root.innerHTML = html;
  }

  async function loadDashboard(){
    const data = await api.jobs('?limit=50');
    state.jobs = data.jobs || [];
    const counts = {
      unscheduled: state.jobs.filter(j => j.status === 'UNSCHEDULED').length,
      needsSo: state.jobs.filter(j => !j.so_number).length,
      inProgress: state.jobs.filter(j => j.status === 'IN_PROGRESS').length,
      pendingQc: state.jobs.filter(j => j.status === 'PENDING_QC').length,
    };

    view(`
      <div class="card">
        <h2>Dashboard</h2>
        <div class="row">
          <div class="kpi"><div class="label">Unscheduled</div><div class="value">${counts.unscheduled}</div></div>
          <div class="kpi"><div class="label">Needs SO#</div><div class="value">${counts.needsSo}</div></div>
          <div class="kpi"><div class="label">In Progress</div><div class="value">${counts.inProgress}</div></div>
          <div class="kpi"><div class="label">Pending QC</div><div class="value">${counts.pendingQc}</div></div>
        </div>
      </div>

      <div class="card">
        <h2>Recent Jobs</h2>
        ${jobsTable(state.jobs.slice(0, 25))}
      </div>
    `);

    bindJobsTable();
  }

  function jobsTable(jobs){
    return `
      <table class="table">
        <thead>
          <tr>
            <th>SO#</th>
            <th>VIN</th>
            <th>Status</th>
            <th>Start</th>
            <th>Finish</th>
            <th>Assigned</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          ${jobs.map(j => `
            <tr>
              <td class="mono">${j.so_number || ''}</td>
              <td class="mono">${(j.vin || '').slice(-6)}</td>
              <td><span class="badge ${badgeClass(j.status)}">${fmtStatus(j.status)}</span></td>
              <td>${j.scheduled_start || ''}</td>
              <td>${j.scheduled_finish || ''}</td>
              <td>${j.assigned_name || ''}</td>
              <td><button class="btn secondary" data-open-job="${j.job_id}">Open</button></td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    `;
  }

  function bindJobsTable(){
    $$('button[data-open-job]').forEach(b => {
      b.onclick = () => {
        const id = b.getAttribute('data-open-job');
        window.history.pushState({}, '', '/ops/job/' + id);
        router();
      };
    });
  }

  async function loadJobsList(){
    const params = new URLSearchParams(window.location.search);
    const q = params.get('q') || '';
    const data = await api.jobs('?limit=200' + (q ? '&q=' + encodeURIComponent(q) : ''));
    state.jobs = data.jobs || [];

    view(`
      <div class="card">
        <h2>Jobs</h2>
        <div class="inline" style="margin-bottom:10px;">
          <input class="input" id="job-search" placeholder="Search SO#, VIN, customer..." value="${escapeHtml(q)}" />
          <button class="btn secondary" id="job-search-btn">Search</button>
        </div>
        ${jobsTable(state.jobs)}
      </div>
    `);

    $('#job-search-btn').onclick = () => {
      const v = $('#job-search').value.trim();
      const url = '/ops/jobs' + (v ? ('?q=' + encodeURIComponent(v)) : '');
      window.history.pushState({}, '', url);
      router();
    };

    bindJobsTable();
  }

  async function loadJobDetail(id){
    const [job, settingsResp, usersResp] = await Promise.all([
      api.job(id),
      api.settings(),
      api.users().catch(() => ({users:[]})),
    ]);

    const caps = slateOpsSettings.user.caps || {};
    const isSupervisor = !!caps.supervisor || !!caps.admin;
    const isCS = !!caps.cs || !!caps.admin;
    const canEdit = isCS || isSupervisor;

    const t = job.time || {approved_minutes_total:0, pending_minutes_total:0, by_tech:[]};
    const estHrs = job.estimated_minutes ? (job.estimated_minutes / 60) : 0;
    const actHrs = t.approved_minutes_total / 60;
    const varHrs = actHrs - estHrs;
    const notesLog = job.notes_log || [];

    const partsLabel = {'NOT_READY':'Not Ready','PARTIAL':'Partial','READY':'Ready','HOLD':'Hold'};
    const jobTypeLabel = {
      'UPFIT':'Upfit','COMMERCIAL_UPFIT':'Commercial Upfit','COMMERCIAL_BUILD':'Commercial Build',
      'RV_BUILD':'RV Build','RV_UPFIT':'RV Upfit','PARTS_ONLY':'Parts Only','SERVICE':'Service','WARRANTY':'Warranty',
    };

    view(`
      <div class="card">
        <div class="row" style="align-items:flex-start;margin-bottom:14px;">
          <div style="flex:1;">
            <h2 style="margin:0 0 4px;">${escapeHtml(job.so_number || 'No SO#')}</h2>
            <div class="muted">${escapeHtml(job.customer_name || '')}${job.dealer_name ? ' &middot; ' + escapeHtml(job.dealer_name) : ''}</div>
          </div>
          <span class="badge ${badgeClass(job.status)}">${fmtStatus(job.status)}</span>
        </div>

        <div class="row">
          <div class="kpi">
            <div class="label">Estimate</div>
            <div class="value">${estHrs ? estHrs.toFixed(1) : '—'} hrs</div>
          </div>
          <div class="kpi">
            <div class="label">Actual Approved</div>
            <div class="value">${minutesToHours(t.approved_minutes_total)} hrs</div>
          </div>
          <div class="kpi">
            <div class="label">Pending</div>
            <div class="value">${minutesToHours(t.pending_minutes_total)} hrs</div>
          </div>
          <div class="kpi">
            <div class="label">Variance</div>
            <div class="value">${estHrs ? (Math.round(varHrs*10)/10).toFixed(1) : '—'} hrs</div>
          </div>
        </div>

        <div class="form-grid" style="margin-top:14px;">
          <div>
            <div class="label" style="margin-bottom:4px;">VIN</div>
            <div class="mono">${escapeHtml(job.vin || '—')}</div>
          </div>
          <div>
            <div class="label" style="margin-bottom:4px;">Job Type</div>
            <div>${escapeHtml(jobTypeLabel[job.job_type] || job.job_type || '—')}</div>
          </div>
          <div>
            <div class="label" style="margin-bottom:4px;">Parts Status</div>
            <div>${escapeHtml(partsLabel[job.parts_status] || job.parts_status || '—')}</div>
          </div>
          <div>
            <div class="label" style="margin-bottom:4px;">Requested Date</div>
            <div>${escapeHtml(job.requested_date || '—')}</div>
          </div>
          <div>
            <div class="label" style="margin-bottom:4px;">Assigned Tech</div>
            <div>${escapeHtml(job.assigned_name || '—')}</div>
          </div>
          <div>
            <div class="label" style="margin-bottom:4px;">Scheduled</div>
            <div>${escapeHtml(job.scheduled_start || '—')} &rarr; ${escapeHtml(job.scheduled_finish || '—')}</div>
          </div>
        </div>

        ${job.notes ? `
          <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border,#e0e0e0);">
            <div class="label" style="margin-bottom:4px;">Notes</div>
            <div style="white-space:pre-wrap;">${escapeHtml(job.notes)}</div>
          </div>
        ` : ''}

        <div class="row" style="margin-top:14px;">
          <button class="btn" id="start-btn">Start</button>
          <button class="btn secondary" id="stop-btn">Stop</button>
          <button class="btn secondary" id="fix-btn">Fix Time</button>
          ${isCS ? `<button class="btn secondary" id="so-btn">Set SO#</button>` : ``}
          ${isSupervisor ? `<button class="btn secondary" id="qc-btn">QC Approve</button>` : ``}
          ${canEdit ? `<button class="btn secondary" id="edit-btn">Edit Job</button>` : ``}
        </div>
      </div>

      ${canEdit ? `
      <div class="card" id="edit-panel" style="display:none;">
        <div id="edit-form-content"></div>
      </div>
      ` : ''}

      <div class="card">
        <h2>Time Breakdown</h2>
        <table class="table">
          <thead>
            <tr>
              <th>Tech</th>
              <th>Approved</th>
              <th>Pending</th>
              <th>Segments</th>
              <th>Last Activity</th>
            </tr>
          </thead>
          <tbody>
            ${(t.by_tech||[]).map(r => `
              <tr>
                <td>${escapeHtml(r.user_name || '')}</td>
                <td>${minutesToHours(r.approved_minutes)} hrs</td>
                <td>${minutesToHours(r.pending_minutes)} hrs</td>
                <td>${r.segment_count}</td>
                <td>${r.last_activity || ''}</td>
              </tr>
            `).join('') || `<tr><td colspan="5">No time logged yet.</td></tr>`}
          </tbody>
        </table>
      </div>

      <div class="card">
        <h2>Notes</h2>
        ${notesLog.length === 0 ? `<div class="muted">No notes yet.</div>` : `
          <div style="display:flex;flex-direction:column;gap:10px;">
            ${notesLog.map(n => `
              <div style="padding:10px 12px;background:var(--surface2,#f4f4f4);border-radius:6px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                  <strong style="font-size:13px;">${escapeHtml(n.user_name || '')}</strong>
                  <span class="muted" style="font-size:12px;">${escapeHtml(n.created_at || '')}</span>
                </div>
                <div style="white-space:pre-wrap;font-size:14px;">${escapeHtml(n.note || '')}</div>
              </div>
            `).join('')}
          </div>
        `}
        ${canEdit ? `
          <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border,#e0e0e0);">
            <div class="label" style="margin-bottom:6px;">Add Note</div>
            <textarea class="input" id="new-note" rows="3" placeholder="Internal note…"></textarea>
            <div style="margin-top:8px;display:flex;align-items:center;gap:10px;">
              <button class="btn secondary" id="add-note-btn">Add Note</button>
              <div class="field-error" data-error-for="note" style="flex:1;"></div>
            </div>
          </div>
        ` : ``}
      </div>
    `);

    // Start/Stop
    $('#start-btn').onclick = async () => {
      try{
        let reason = null, note = '';
        const assigned = parseInt(job.assigned_user_id || 0, 10);
        const me = slateOpsSettings.user.id;
        if (assigned && assigned !== me){
          const picked = promptReason();
          if(!picked) return;
          reason = picked.reason;
          note = picked.note;
        }
        await api.timeStart(job.job_id, reason, note);
        window.history.replaceState({}, '', '/ops/job/' + job.job_id);
        router();
      }catch(e){
        alert(e.message);
      }
    };

    $('#stop-btn').onclick = async () => {
      try{
        await api.timeStop();
        router();
      }catch(e){
        alert(e.message);
      }
    };

    // Fix time
    $('#fix-btn').onclick = async () => {
      const start = prompt('Start (YYYY-MM-DD HH:MM:SS)', '');
      if(!start) return;
      const end = prompt('End (YYYY-MM-DD HH:MM:SS)', '');
      if(!end) return;
      const note = prompt('Reason (required)', '');
      if(!note) return;
      try{
        await api.correction({job_id: job.job_id, start_ts: start, end_ts: end, note});
        alert('Submitted for supervisor review.');
        router();
      }catch(e){
        alert(e.message);
      }
    };

    if ($('#so-btn')) {
      $('#so-btn').onclick = async () => {
        const so = prompt('Enter SO# (S-ORD######)', job.so_number || '');
        if(!so) return;
        try{
          await api.setSO(job.job_id, so);
          router();
        }catch(e){
          alert(e.message);
        }
      };
    }

    if ($('#qc-btn')) {
      $('#qc-btn').onclick = async () => {
        if(!confirm('QC Approve and mark Complete?')) return;
        try{
          await api.setStatus(job.job_id, 'COMPLETE', 'QC approved');
          router();
        }catch(e){
          alert(e.message);
        }
      };
    }

    if ($('#edit-btn')) {
      $('#edit-btn').onclick = () => {
        const panel = $('#edit-panel');
        if (panel.style.display === 'none') {
          panel.style.display = '';
          renderEditForm($('#edit-form-content'), job, isSupervisor, settingsResp, usersResp.users || []);
          panel.scrollIntoView({behavior: 'smooth', block: 'start'});
        } else {
          panel.style.display = 'none';
        }
      };
    }

    if ($('#add-note-btn')) {
      $('#add-note-btn').onclick = async () => {
        const noteText = $('#new-note').value.trim();
        const errEl = $('[data-error-for="note"]');
        errEl.textContent = '';
        if (!noteText) { errEl.textContent = 'Note cannot be empty.'; return; }
        const btn = $('#add-note-btn');
        btn.disabled = true;
        btn.textContent = 'Adding…';
        try {
          await api.addNote(job.job_id, noteText);
          router();
        } catch(e) {
          errEl.textContent = e.message;
          btn.disabled = false;
          btn.textContent = 'Add Note';
        }
      };
    }
  }

  function renderEditForm(el, job, isSupervisor, settings, users) {
    const dealerOpts = (settings.dealers || []).map(d =>
      `<option value="${escapeHtml(d)}"${job.dealer_name===d?' selected':''}>${escapeHtml(d)}</option>`
    ).join('');
    const salesOpts = (settings.sales_people || []).map(p =>
      `<option value="${escapeHtml(p)}"${job.sales_person===p?' selected':''}>${escapeHtml(p)}</option>`
    ).join('');

    const jobTypes = [
      ['UPFIT','Upfit'],['COMMERCIAL_UPFIT','Commercial Upfit'],['COMMERCIAL_BUILD','Commercial Build'],
      ['RV_BUILD','RV Build'],['RV_UPFIT','RV Upfit'],['PARTS_ONLY','Parts Only'],
      ['SERVICE','Service'],['WARRANTY','Warranty'],
    ];
    const jobTypeOpts = jobTypes.map(([v,l]) =>
      `<option value="${v}"${job.job_type===v?' selected':''}>${l}</option>`
    ).join('');

    const partsOpts = [['NOT_READY','Not Ready'],['PARTIAL','Partial'],['READY','Ready'],['HOLD','Hold']].map(([v,l]) =>
      `<option value="${v}"${(job.parts_status||'NOT_READY')===v?' selected':''}>${l}</option>`
    ).join('');

    const estHours = job.estimated_minutes ? (job.estimated_minutes / 60).toFixed(1) : '';

    const statusOpts = ['UNSCHEDULED','READY_FOR_SCHEDULING','SCHEDULED','IN_PROGRESS','PENDING_QC','COMPLETE','DELAYED','BLOCKED','ON_HOLD'].map(s =>
      `<option value="${s}"${job.status===s?' selected':''}>${fmtStatus(s)}</option>`
    ).join('');

    const userOpts = users.map(u =>
      `<option value="${u.id}"${(job.assigned_user_id==u.id)?' selected':''}>${escapeHtml(u.name)}</option>`
    ).join('');

    el.innerHTML = `
      <div class="label" style="margin-bottom:6px;">Edit Job — ${escapeHtml(job.so_number || '#' + job.job_id)}</div>
      <div class="muted" style="margin-bottom:12px;">Changes are logged to the audit trail.</div>

      <div class="form-grid">
        <div>
          <div class="label" style="margin-bottom:6px;">Customer</div>
          <input class="input" id="ef-customer" value="${escapeHtml(job.customer_name||'')}" placeholder="Customer name" />
          <div class="field-error" data-error-for="customer_name"></div>
        </div>
        <div>
          <div class="label" style="margin-bottom:6px;">Dealer</div>
          <select class="input" id="ef-dealer">
            <option value="">Select dealer</option>
            ${dealerOpts}
          </select>
          <div class="field-error" data-error-for="dealer_name"></div>
        </div>
        <div>
          <div class="label" style="margin-bottom:6px;">VIN Last 7–8</div>
          <input class="input" id="ef-vin" maxlength="8" value="${escapeHtml(job.vin_last8||job.vin||'')}" placeholder="A1B2C3D4" />
          <div class="field-error" data-error-for="vin_last8"></div>
        </div>
        <div>
          <div class="label" style="margin-bottom:6px;">Job Type</div>
          <select class="input" id="ef-job-type">
            ${jobTypeOpts}
          </select>
          <div class="field-error" data-error-for="job_type"></div>
        </div>
        <div>
          <div class="label" style="margin-bottom:6px;">Parts Status</div>
          <select class="input" id="ef-parts">
            ${partsOpts}
          </select>
          <div class="field-error" data-error-for="parts_status"></div>
        </div>
        <div>
          <div class="label" style="margin-bottom:6px;">Estimated Hours</div>
          <input class="input" id="ef-est" type="number" min="0.5" step="0.5" value="${escapeHtml(estHours)}" placeholder="e.g. 2.5" />
          <div class="field-error" data-error-for="estimated_hours"></div>
        </div>
        <div>
          <div class="label" style="margin-bottom:6px;">Requested Date</div>
          <input class="input" id="ef-date" type="date" value="${escapeHtml(job.requested_date||'')}" />
          <div class="field-error" data-error-for="requested_date"></div>
        </div>
        <div>
          <div class="label" style="margin-bottom:6px;">Sales Person</div>
          <select class="input" id="ef-sales">
            <option value="">Select sales person</option>
            ${salesOpts}
          </select>
        </div>
      </div>

      <div style="margin-top:10px;">
        <div class="label" style="margin-bottom:6px;">Notes</div>
        <textarea class="input" id="ef-notes" rows="3" placeholder="Additional notes…">${escapeHtml(job.notes||'')}</textarea>
      </div>

      ${isSupervisor ? `
      <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border,#e0e0e0);">
        <div class="label" style="margin-bottom:8px;">Supervisor Fields</div>
        <div class="form-grid">
          <div>
            <div class="label" style="margin-bottom:6px;">Status</div>
            <select class="input" id="ef-status">
              ${statusOpts}
            </select>
          </div>
          <div>
            <div class="label" style="margin-bottom:6px;">Status Detail</div>
            <input class="input" id="ef-status-detail" value="${escapeHtml(job.status_detail||'')}" placeholder="Optional detail" />
          </div>
          <div>
            <div class="label" style="margin-bottom:6px;">Assigned Tech</div>
            <select class="input" id="ef-tech">
              <option value="">Unassigned</option>
              ${userOpts}
            </select>
          </div>
          <div>
            <div class="label" style="margin-bottom:6px;">Work Center / Bay</div>
            <input class="input" id="ef-bay" value="${escapeHtml(job.work_center||'')}" placeholder="e.g. Bay 3" />
          </div>
          <div>
            <div class="label" style="margin-bottom:6px;">Scheduled Start</div>
            <input class="input" id="ef-start" type="datetime-local" value="${escapeHtml((job.scheduled_start||'').replace(' ','T'))}" />
          </div>
          <div>
            <div class="label" style="margin-bottom:6px;">Scheduled Finish</div>
            <input class="input" id="ef-finish" type="datetime-local" value="${escapeHtml((job.scheduled_finish||'').replace(' ','T'))}" />
          </div>
          <div>
            <div class="label" style="margin-bottom:6px;">Priority (1–5)</div>
            <input class="input" id="ef-priority" type="number" min="1" max="5" value="${escapeHtml(String(job.priority||3))}" />
          </div>
          <div>
            <div class="label" style="margin-bottom:6px;">Delay Reason</div>
            <select class="input" id="ef-delay">
              <option value="">None</option>
              <option value="parts"${job.delay_reason==='parts'?' selected':''}>Parts</option>
              <option value="customer"${job.delay_reason==='customer'?' selected':''}>Customer</option>
              <option value="vendor"${job.delay_reason==='vendor'?' selected':''}>Vendor</option>
              <option value="labor"${job.delay_reason==='labor'?' selected':''}>Labor</option>
              <option value="other"${job.delay_reason==='other'?' selected':''}>Other</option>
            </select>
          </div>
        </div>
      </div>
      ` : ''}

      <div class="row" style="margin-top:14px;">
        <button class="btn" id="ef-save">Save Changes</button>
        <button class="btn secondary" id="ef-cancel">Cancel</button>
        <div class="field-error" data-error-for="ef-general" style="flex:1;"></div>
      </div>
    `;

    el.querySelector('#ef-cancel').onclick = () => {
      el.closest('#edit-panel').style.display = 'none';
    };

    el.querySelector('#ef-save').onclick = async () => {
      el.querySelectorAll('.field-error').forEach(e => e.textContent = '');

      const payload = {
        customer_name:   el.querySelector('#ef-customer').value.trim(),
        dealer_name:     el.querySelector('#ef-dealer').value.trim(),
        vin_last8:       el.querySelector('#ef-vin').value.trim().toUpperCase(),
        job_type:        el.querySelector('#ef-job-type').value,
        parts_status:    el.querySelector('#ef-parts').value,
        estimated_hours: el.querySelector('#ef-est').value.trim(),
        requested_date:  el.querySelector('#ef-date').value,
        sales_person:    el.querySelector('#ef-sales').value.trim(),
        notes:           el.querySelector('#ef-notes').value.trim(),
      };

      if (isSupervisor) {
        const start  = el.querySelector('#ef-start').value.replace('T', ' ');
        const finish = el.querySelector('#ef-finish').value.replace('T', ' ');
        Object.assign(payload, {
          status:           el.querySelector('#ef-status').value,
          status_detail:    el.querySelector('#ef-status-detail').value.trim(),
          assigned_user_id: parseInt(el.querySelector('#ef-tech').value, 10) || 0,
          work_center:      el.querySelector('#ef-bay').value.trim(),
          scheduled_start:  start || '',
          scheduled_finish: finish || '',
          priority:         parseInt(el.querySelector('#ef-priority').value, 10) || 3,
          delay_reason:     el.querySelector('#ef-delay').value,
        });
      }

      const btn = el.querySelector('#ef-save');
      btn.disabled = true;
      btn.textContent = 'Saving…';

      try {
        await api.editJob(job.job_id, payload);
        router();
      } catch(e) {
        const field   = e?.data?.data?.field || e?.data?.field;
        const message = e?.data?.data?.message || e?.message || 'Save failed.';
        if (field) {
          const errEl = el.querySelector(`[data-error-for="${field}"]`);
          if (errEl) errEl.textContent = message;
          else el.querySelector('[data-error-for="ef-general"]').textContent = message;
        } else {
          el.querySelector('[data-error-for="ef-general"]').textContent = message;
        }
        btn.disabled = false;
        btn.textContent = 'Save Changes';
      }
    };
  }

  function promptReason(){
    const options = [
      ['helping_assigned_tech','Helping assigned tech'],
      ['parts_kit_support','Parts/kit support'],
      ['qc_support','QC support'],
      ['diagnosis','Diagnosis'],
      ['rework','Rework'],
      ['other','Other'],
    ];
    const choice = prompt(
      'Reason (enter number):\n' +
      options.map((o,i)=> `${i+1}) ${o[1]}`).join('\n'),
      '1'
    );
    if(!choice) return null;
    const idx = parseInt(choice, 10) - 1;
    const reason = options[idx] ? options[idx][0] : 'other';
    let note = '';
    if(reason === 'other'){
      note = prompt('Note (required for Other):', '') || '';
      if(!note.trim()) return null;
    }
    return {reason, note};
  }

  function bindCollapsibles() {
    $$('.section-header[data-collapse]').forEach(btn => {
      if (btn._collapsebound) return;
      btn._collapsebound = true;
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-collapse');
        const body = document.getElementById('collapse-' + id);
        const chevron = btn.querySelector('.collapse-chevron');
        if (!body) return;
        const hidden = body.style.display === 'none';
        body.style.display = hidden ? '' : 'none';
        if (chevron) chevron.textContent = hidden ? '▾' : '▸';
      });
    });
  }

  function parseGMTTimestamp(ts) {
    if (!ts) return null;
    return new Date(ts.replace(' ', 'T') + 'Z');
  }

  function formatElapsed(totalSeconds) {
    const s = Math.max(0, totalSeconds);
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(sec).padStart(2,'0')}`;
  }

  async function loadSupervisor() {
    const [queuesResp, jobsResp] = await Promise.all([
      api.supervisorQueues(),
      api.jobs('?limit=300'),
    ]);
    const pending    = queuesResp.pending_corrections || [];
    const unassigned = queuesResp.unassigned_segments || [];
    const jobs       = jobsResp.jobs || [];

    const byS      = (s) => jobs.filter(j => j.status === s);
    const today    = new Date().toISOString().slice(0, 10);
    const todayJobs = jobs.filter(j => j.scheduled_start && j.scheduled_start.startsWith(today));

    view(`
      <div class="card">
        <h2 style="margin:0 0 14px;">Supervisor</h2>
        <div class="row">
          ${kpi('In Progress',    byS('IN_PROGRESS').length)}
          ${kpi('Scheduled',      byS('SCHEDULED').length)}
          ${kpi('Pending QC',     byS('PENDING_QC').length)}
          ${kpi('Needs Attention', pending.length + unassigned.length)}
        </div>
      </div>

      <div class="card">
        <button class="section-header" data-collapse="corrections">
          <span class="collapse-title">Pending Corrections</span>
          <div style="display:flex;align-items:center;gap:8px;">
            ${pending.length ? `<span class="count-badge urgent">${pending.length}</span>` : `<span class="muted">None</span>`}
            <span class="collapse-chevron">${pending.length ? '▾' : '▸'}</span>
          </div>
        </button>
        <div class="collapse-body" id="collapse-corrections" ${!pending.length ? 'style="display:none;"' : ''}>
          <table class="table">
            <thead><tr><th>Job</th><th>Tech</th><th>Start</th><th>End</th><th>Note</th></tr></thead>
            <tbody>
              ${pending.map(p=>`
                <tr>
                  <td><button class="btn secondary small-btn" data-open-job="${p.job_id}">Open</button></td>
                  <td>${escapeHtml(p.user_name||'')}</td>
                  <td style="font-size:12px;">${p.start_ts}</td>
                  <td style="font-size:12px;">${p.end_ts}</td>
                  <td>${escapeHtml(p.note||'')}</td>
                </tr>
              `).join('') || `<tr><td colspan="5" class="muted" style="padding:10px;">None.</td></tr>`}
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <button class="section-header" data-collapse="unassigned">
          <span class="collapse-title">Unassigned Time</span>
          <div style="display:flex;align-items:center;gap:8px;">
            ${unassigned.length ? `<span class="count-badge">${unassigned.length}</span>` : `<span class="muted">None</span>`}
            <span class="collapse-chevron">${unassigned.length ? '▾' : '▸'}</span>
          </div>
        </button>
        <div class="collapse-body" id="collapse-unassigned" ${!unassigned.length ? 'style="display:none;"' : ''}>
          <table class="table">
            <thead><tr><th>Job</th><th>Tech</th><th>Start</th><th>End</th><th>Reason</th></tr></thead>
            <tbody>
              ${unassigned.map(u=>`
                <tr>
                  <td><button class="btn secondary small-btn" data-open-job="${u.job_id}">Open</button></td>
                  <td>${escapeHtml(u.user_name||'')}</td>
                  <td style="font-size:12px;">${u.start_ts}</td>
                  <td style="font-size:12px;">${u.end_ts||''}</td>
                  <td>${escapeHtml(u.reason||'')}</td>
                </tr>
              `).join('') || `<tr><td colspan="5" class="muted" style="padding:10px;">None.</td></tr>`}
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <button class="section-header" data-collapse="today">
          <span class="collapse-title">Today's Schedule</span>
          <div style="display:flex;align-items:center;gap:8px;">
            ${todayJobs.length ? `<span class="count-badge">${todayJobs.length}</span>` : `<span class="muted">None today</span>`}
            <span class="collapse-chevron">▾</span>
          </div>
        </button>
        <div class="collapse-body" id="collapse-today">
          <table class="table">
            <thead><tr><th>SO#</th><th>Customer</th><th>Assigned</th><th>Start</th><th>Bay</th><th></th></tr></thead>
            <tbody>
              ${todayJobs.map(j=>`
                <tr>
                  <td class="mono">${escapeHtml(j.so_number||'—')}</td>
                  <td>${escapeHtml(j.customer_name||j.dealer_name||'—')}</td>
                  <td>${escapeHtml(j.assigned_name||'—')}</td>
                  <td style="font-size:12px;">${escapeHtml(j.scheduled_start||'')}</td>
                  <td>${escapeHtml(j.work_center||'—')}</td>
                  <td><button class="btn secondary small-btn" data-open-job="${j.job_id}">Open</button></td>
                </tr>
              `).join('') || `<tr><td colspan="6" class="muted" style="padding:10px;">No jobs scheduled today.</td></tr>`}
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <button class="section-header" data-collapse="alljobs">
          <span class="collapse-title">All Active Jobs</span>
          <span class="collapse-chevron">▸</span>
        </button>
        <div class="collapse-body" id="collapse-alljobs" style="display:none;">
          ${jobsTable(jobs)}
        </div>
      </div>
    `);

    bindCollapsibles();
    bindJobsTable();
  }

  async function loadSettings(){
    const s = await api.settings();
    let dealers = [...(s.dealers || [])];
    let salesPeople = [...(s.sales_people || [])];

    function tagListHtml(id, items, placeholder) {
      const tags = items.map(v => `
        <span class="tag-item">${escapeHtml(v)}<button class="tag-remove" data-value="${escapeHtml(v)}" title="Remove">&times;</button></span>
      `).join('');
      const empty = items.length === 0 ? `<span style="color:rgba(0,0,0,0.45);font-size:13px;">None added yet.</span>` : '';
      return `
        <div class="tag-list" id="${id}-list">${tags}${empty}</div>
        <div class="inline" style="margin-top:8px;">
          <input class="input" id="${id}-input" placeholder="${escapeHtml(placeholder)}" style="max-width:300px;" />
          <button class="btn secondary" id="${id}-add">Add</button>
        </div>
      `;
    }

    view(`
      <div class="card">
        <h2>Settings</h2>
        <div class="row">
          <div style="flex:1 1 180px;">
            <div class="label" style="margin-bottom:6px;">Shift Start</div>
            <input class="input" id="shift_start" value="${s.shift_start || '07:00:00'}" />
          </div>
          <div style="flex:1 1 180px;">
            <div class="label" style="margin-bottom:6px;">Shift End</div>
            <input class="input" id="shift_end" value="${s.shift_end || '15:30:00'}" />
          </div>
          <div style="flex:1 1 180px;">
            <div class="label" style="margin-bottom:6px;">Lunch Minutes</div>
            <input class="input" id="lunch_minutes" value="${s.lunch_minutes || 30}" />
          </div>
          <div style="flex:1 1 180px;">
            <div class="label" style="margin-bottom:6px;">Break Minutes</div>
            <input class="input" id="break_minutes" value="${s.break_minutes || 20}" />
          </div>
        </div>
        <div style="margin-top:14px;">
          <div class="label" style="margin-bottom:6px;">Dealers</div>
          ${tagListHtml('dealers', dealers, 'Dealer name...')}
        </div>
        <div style="margin-top:14px;">
          <div class="label" style="margin-bottom:6px;">Sales People</div>
          ${tagListHtml('sales_people', salesPeople, 'Sales person name...')}
        </div>
        <div style="margin-top:12px;">
          <button class="btn" id="save_settings">Save</button>
        </div>
      </div>
    `);

    function renderList(id, arr) {
      const tags = arr.map(v => `
        <span class="tag-item">${escapeHtml(v)}<button class="tag-remove" data-value="${escapeHtml(v)}" title="Remove">&times;</button></span>
      `).join('');
      const empty = arr.length === 0 ? `<span style="color:rgba(0,0,0,0.45);font-size:13px;">None added yet.</span>` : '';
      $(`#${id}-list`).innerHTML = tags + empty;
      bindRemove(id);
    }

    function bindRemove(id) {
      $$(`#${id}-list .tag-remove`).forEach(btn => {
        btn.onclick = () => {
          const val = btn.getAttribute('data-value');
          if (id === 'dealers') { dealers = dealers.filter(d => d !== val); renderList('dealers', dealers); }
          else { salesPeople = salesPeople.filter(p => p !== val); renderList('sales_people', salesPeople); }
        };
      });
    }

    function bindAdd(id, getArr, addFn) {
      const input = $(`#${id}-input`);
      const btn = $(`#${id}-add`);
      const doAdd = () => {
        const val = input.value.trim();
        if (!val) return;
        addFn(val);
        input.value = '';
        renderList(id, getArr());
        input.focus();
      };
      btn.onclick = doAdd;
      input.onkeydown = (e) => { if (e.key === 'Enter') { e.preventDefault(); doAdd(); } };
    }

    bindRemove('dealers');
    bindRemove('sales_people');

    bindAdd('dealers', () => dealers, (val) => { if (!dealers.includes(val)) dealers.push(val); });
    bindAdd('sales_people', () => salesPeople, (val) => { if (!salesPeople.includes(val)) salesPeople.push(val); });

    $('#save_settings').onclick = async () => {
      try{
        const payload = {
          shift_start: $('#shift_start').value.trim(),
          shift_end: $('#shift_end').value.trim(),
          lunch_minutes: parseInt($('#lunch_minutes').value, 10),
          break_minutes: parseInt($('#break_minutes').value, 10),
          dealers,
          sales_people: salesPeople,
        };
        await api.updateSettings(payload);
        alert('Saved.');
      }catch(e){
        alert(e.message);
      }
    };
  }

  async function loadCreateJob(){
  view(`<div class="card" id="create-job-page"></div>`);
  await loadCreateJobInto('#create-job-page');
}

function escapeHtml(s){
    return String(s||'')
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'","&#039;");
  }

  function kpi(label, value){
  return `
    <div class="card" style="flex:1 1 160px;">
      <div class="kpi-label">${escapeHtml(label)}</div>
      <div class="kpi-value">${value}</div>
    </div>
  `;
}

function setFieldError(host, field, message){
  const errorEl = host.querySelector(`[data-error-for="${field}"]`);
  if (errorEl) {
    errorEl.textContent = message || '';
  }
}

function clearCreateJobErrors(host){
  host.querySelectorAll('.field-error').forEach((el) => {
    el.textContent = '';
  });
}

async function loadCreateJobInto(selector){
  const host = document.querySelector(selector);
  if(!host) return;

  const currentSettings = await api.settings();
  const dealerOptions = ((currentSettings.dealers || slateOpsSettings.dealers || [])).map((dealer) => `
    <option value="${escapeHtml(dealer)}">${escapeHtml(dealer)}</option>
  `).join('');
  const salesOptions = (currentSettings.sales_people || []).map((person) => `
    <option value="${escapeHtml(person)}">${escapeHtml(person)}</option>
  `).join('');

  host.innerHTML = `
    <div class="label" style="margin-bottom:10px;">Create Job</div>

    <div class="form-grid">
      <div>
        <div class="label" style="margin-bottom:6px;">SO#</div>
        <input class="input" id="so_number" placeholder="S-ORD101350" />
        <div class="field-error" data-error-for="so_number"></div>
      </div>
      <div>
        <div class="label" style="margin-bottom:6px;">Dealer</div>
        <select class="input" id="dealer_name">
          <option value="">Select dealer</option>
          ${dealerOptions}
        </select>
        <div class="field-error" data-error-for="dealer_name"></div>
      </div>
      <div>
        <div class="label" style="margin-bottom:6px;">Customer</div>
        <input class="input" id="customer_name" placeholder="Customer name" />
        <div class="field-error" data-error-for="customer_name"></div>
      </div>
      <div>
        <div class="label" style="margin-bottom:6px;">VIN Last 7–8</div>
        <input class="input" id="vin_last8" maxlength="8" placeholder="A1B2C3D4" />
        <div class="field-error" data-error-for="vin_last8"></div>
      </div>
      <div>
        <div class="label" style="margin-bottom:6px;">Sales Person</div>
        <select class="input" id="sales_person">
          <option value="">Select sales person</option>
          ${salesOptions}
        </select>
        <div class="field-error" data-error-for="sales_person"></div>
      </div>
      <div>
        <div class="label" style="margin-bottom:6px;">Job Type</div>
        <select class="input" id="job_type">
          <option value="UPFIT">Upfit</option>
          <option value="COMMERCIAL_UPFIT">Commercial Upfit</option>
          <option value="COMMERCIAL_BUILD">Commercial Build</option>
          <option value="RV_BUILD">RV Build</option>
          <option value="RV_UPFIT">RV Upfit</option>
          <option value="PARTS_ONLY">Parts Only</option>
          <option value="SERVICE">Service</option>
          <option value="WARRANTY">Warranty</option>
        </select>
        <div class="field-error" data-error-for="job_type"></div>
      </div>
      <div>
        <div class="label" style="margin-bottom:6px;">Created From</div>
        <div class="input" style="background:var(--surface2,#f4f4f4);color:var(--muted,#888);cursor:default;">Manual Entry</div>
      </div>
      <div>
        <div class="label" style="margin-bottom:6px;">Estimated Hours</div>
        <input class="input" id="estimated_hours" type="number" min="0.5" step="0.5" placeholder="e.g. 2.5" />
        <div class="field-error" data-error-for="estimated_hours"></div>
      </div>
      <div>
        <div class="label" style="margin-bottom:6px;">Parts Status</div>
        <select class="input" id="parts_status">
          <option value="NOT_READY" selected>Not Ready</option>
          <option value="PARTIAL">Partial</option>
          <option value="READY">Ready</option>
          <option value="HOLD">Hold</option>
        </select>
        <div class="field-error" data-error-for="parts_status"></div>
      </div>
      <div>
        <div class="label" style="margin-bottom:6px;">Requested Completion Date</div>
        <input class="input" id="requested_date" type="date" />
        <div class="field-error" data-error-for="requested_date"></div>
      </div>
    </div>

    <div style="margin-top:10px;">
      <div class="label" style="margin-bottom:6px;">Notes</div>
      <textarea class="input" id="notes" rows="4" placeholder="Additional notes, special instructions, parts details…"></textarea>
    </div>

    <div class="row" style="margin-top:12px;">
      <button class="btn" id="create_btn">Create Job</button>
      <div class="field-error" data-error-for="general"></div>
    </div>
  `;

  host.querySelector('#create_btn').onclick = async () => {
    clearCreateJobErrors(host);

    const notesInput = host.querySelector('#notes').value.trim();
    const soInput = host.querySelector('#so_number').value.trim().toUpperCase();
    const payload = {
      so_number: soInput || undefined,
      dealer_name: host.querySelector('#dealer_name').value.trim(),
      customer_name: host.querySelector('#customer_name').value.trim(),
      vin_last8: host.querySelector('#vin_last8').value.trim().toUpperCase(),
      sales_person: host.querySelector('#sales_person').value.trim(),
      job_type: host.querySelector('#job_type').value,
      created_from: 'manual',
      estimated_hours: host.querySelector('#estimated_hours').value.trim(),
      parts_status: host.querySelector('#parts_status').value,
      requested_date: host.querySelector('#requested_date').value,
      notes: notesInput,
      notes_type: notesInput.toLowerCase().includes('part') ? 'parts' : '',
    };

    try {
      const job = await api.createJob(payload);
      window.history.pushState({}, '', '/ops/job/' + job.job_id);
      router();
    } catch(e) {
      const field = e?.data?.data?.field || e?.data?.field;
      const message = e?.data?.data?.message || e?.message || 'Request failed';
      if (field) {
        setFieldError(host, field, message);
      } else {
        setFieldError(host, 'general', message);
      }
    }
  };
}

async function loadCS() {
  const [jobsResp, settingsResp] = await Promise.all([
    api.jobs('?limit=300&so_missing=1'),
    api.settings(),
  ]);

  const allNeeds    = (jobsResp.jobs||[]).filter(j => {
    const s = (j.status||'').toUpperCase();
    return s === 'UNSCHEDULED' || s === 'READY_FOR_SCHEDULING';
  });
  const portalNeeds = allNeeds.filter(j => j.created_from === 'portal');
  const manualNeeds = allNeeds.filter(j => j.created_from !== 'portal');
  const dealerList  = settingsResp.dealers || [];
  const salesList   = settingsResp.sales_people || [];

  view(`
    <div class="card">
      <div class="row" style="align-items:flex-start;">
        <div style="flex:1 1 320px;">
          <h2 style="margin:0;">Customer Service</h2>
          <div class="muted" style="margin-top:4px;">Complete intake, assign SO#s, and create jobs.</div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          ${kpi('Pending Intake', portalNeeds.length)}
          ${kpi('Needs SO#', manualNeeds.length)}
        </div>
      </div>
    </div>

    <div class="card" id="intake-panel" style="display:none;">
      <div id="intake-form-content"></div>
    </div>

    <div class="card">
      <button class="section-header" data-collapse="intake">
        <span class="collapse-title">Pending Intake — Portal Jobs</span>
        <div style="display:flex;align-items:center;gap:8px;">
          ${portalNeeds.length ? `<span class="count-badge urgent">${portalNeeds.length}</span>` : ''}
          <span class="collapse-chevron">${portalNeeds.length ? '▾' : '▸'}</span>
        </div>
      </button>
      <div class="collapse-body" id="collapse-intake" ${!portalNeeds.length ? 'style="display:none;"' : ''}>
        <table class="table">
          <thead><tr>
            <th>Customer</th><th>VIN</th><th>Dealer</th><th></th>
          </tr></thead>
          <tbody>
            ${portalNeeds.map(j=>`
              <tr data-id="${j.job_id}">
                <td>${escapeHtml(j.customer_name||'—')}</td>
                <td class="mono">${escapeHtml((j.vin||'').slice(-6)||'—')}</td>
                <td>${escapeHtml(j.dealer_name||'—')}</td>
                <td>
                  <button class="btn small-btn intake-btn" data-id="${j.job_id}">Complete Intake</button>
                  <button class="btn secondary small-btn" data-open-job="${j.job_id}" style="margin-left:4px;">View</button>
                </td>
              </tr>
            `).join('') || `<tr><td colspan="4" class="muted" style="padding:10px;">No portal jobs pending intake.</td></tr>`}
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <button class="section-header" data-collapse="so">
        <span class="collapse-title">Needs SO# — Manual Jobs</span>
        <div style="display:flex;align-items:center;gap:8px;">
          ${manualNeeds.length ? `<span class="count-badge">${manualNeeds.length}</span>` : ''}
          <span class="collapse-chevron">${manualNeeds.length ? '▾' : '▸'}</span>
        </div>
      </button>
      <div class="collapse-body" id="collapse-so" ${!manualNeeds.length ? 'style="display:none;"' : ''}>
        <table class="table">
          <thead><tr>
            <th>Customer</th><th>VIN</th><th>Dealer</th><th>SO#</th><th></th>
          </tr></thead>
          <tbody>
            ${manualNeeds.map(j=>`
              <tr data-id="${j.job_id}">
                <td>${escapeHtml(j.customer_name||'')}</td>
                <td class="mono">${escapeHtml((j.vin||'').slice(-6))}</td>
                <td>${escapeHtml(j.dealer_name||'')}</td>
                <td><input class="input so" placeholder="S-ORD101350" /></td>
                <td><button class="btn small-btn save-so">Save</button></td>
              </tr>
            `).join('') || `<tr><td colspan="5" class="muted" style="padding:10px;">No manual jobs need SO#.</td></tr>`}
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <button class="section-header" data-collapse="create">
        <span class="collapse-title">Create Job</span>
        <span class="collapse-chevron">▸</span>
      </button>
      <div class="collapse-body" id="collapse-create" style="display:none;">
        <div id="cs-create-inner"></div>
      </div>
    </div>
  `);

  bindCollapsibles();
  bindJobsTable();

  // Lazy-load create form when section is opened
  let createLoaded = false;
  const createBtn = document.querySelector('[data-collapse="create"]');
  if (createBtn) {
    createBtn._collapsebound = false; // allow re-bind with lazy-load logic
    createBtn.addEventListener('click', async () => {
      const body = document.getElementById('collapse-create');
      const chevron = createBtn.querySelector('.collapse-chevron');
      const hidden = body.style.display === 'none';
      body.style.display = hidden ? '' : 'none';
      if (chevron) chevron.textContent = hidden ? '▾' : '▸';
      if (hidden && !createLoaded) {
        createLoaded = true;
        await loadCreateJobInto('#cs-create-inner');
      }
    });
  }

  $$('.intake-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const jobId = parseInt(btn.dataset.id, 10);
      const job   = portalNeeds.find(j => j.job_id === jobId);
      const panel = $('#intake-panel');
      panel.style.display = '';
      renderIntakeForm($('#intake-form-content'), job, dealerList, salesList);
      panel.scrollIntoView({behavior:'smooth', block:'start'});
    });
  });

  $$('.save-so').forEach(btn => {
    btn.addEventListener('click', async () => {
      const tr = btn.closest('tr');
      const id = parseInt(tr.dataset.id, 10);
      const so = $('.so', tr).value.trim();
      if (!so) { alert('SO# required'); return; }
      btn.disabled = true;
      btn.textContent = 'Saving…';
      try {
        await api.setSO(id, so);
        btn.textContent = 'Saved';
        setTimeout(() => router(), 250);
      } catch(e) {
        alert(e.message);
        btn.textContent = 'Save';
      } finally {
        btn.disabled = false;
      }
    });
  });
}

function renderIntakeForm(el, job, dealerList, salesList) {
  const dealerOpts = dealerList.map(d =>
    `<option value="${escapeHtml(d)}"${job.dealer_name===d?' selected':''}>${escapeHtml(d)}</option>`
  ).join('');

  const jobTypes = [
    ['UPFIT','Upfit'],['COMMERCIAL_UPFIT','Commercial Upfit'],['COMMERCIAL_BUILD','Commercial Build'],
    ['RV_BUILD','RV Build'],['RV_UPFIT','RV Upfit'],['PARTS_ONLY','Parts Only'],
    ['SERVICE','Service'],['WARRANTY','Warranty'],
  ];
  const jobTypeOpts = jobTypes.map(([v,l]) =>
    `<option value="${v}"${job.job_type===v?' selected':''}>${l}</option>`
  ).join('');

  const partsSts = [['NOT_READY','Not Ready'],['PARTIAL','Partial'],['READY','Ready'],['HOLD','Hold']];
  const partsOpts = partsSts.map(([v,l]) =>
    `<option value="${v}"${(job.parts_status||'NOT_READY')===v?' selected':''}>${l}</option>`
  ).join('');

  const estHours = job.estimated_minutes ? (job.estimated_minutes/60).toFixed(1) : '';

  el.innerHTML = `
    <div class="label" style="margin-bottom:6px;">Complete Intake — Portal Job #${job.job_id}</div>
    <div class="muted" style="margin-bottom:12px;">Fill in all required fields to move this job to Ready for Scheduling.</div>

    <div class="form-grid">
      <div>
        <div class="label" style="margin-bottom:6px;">SO#</div>
        <input class="input" id="in-so" placeholder="S-ORD101350" value="${escapeHtml(job.so_number||'')}" />
        <div class="field-error" data-error-for="so_number"></div>
      </div>
      <div>
        <div class="label" style="margin-bottom:6px;">Dealer</div>
        <select class="input" id="in-dealer">
          <option value="">Select dealer</option>
          ${dealerOpts}
        </select>
        <div class="field-error" data-error-for="dealer_name"></div>
      </div>
      <div>
        <div class="label" style="margin-bottom:6px;">Customer</div>
        <input class="input" id="in-customer" placeholder="Customer name" value="${escapeHtml(job.customer_name||'')}" />
        <div class="field-error" data-error-for="customer_name"></div>
      </div>
      <div>
        <div class="label" style="margin-bottom:6px;">VIN Last 7–8</div>
        <input class="input" id="in-vin" maxlength="8" placeholder="A1B2C3D4" value="${escapeHtml(job.vin_last8||job.vin||'')}" />
        <div class="field-error" data-error-for="vin_last8"></div>
      </div>
      <div>
        <div class="label" style="margin-bottom:6px;">Job Type</div>
        <select class="input" id="in-job-type">
          ${jobTypeOpts}
        </select>
        <div class="field-error" data-error-for="job_type"></div>
      </div>
      <div>
        <div class="label" style="margin-bottom:6px;">Estimated Hours</div>
        <input class="input" id="in-est" type="number" min="0.5" step="0.5" placeholder="e.g. 2.5" value="${escapeHtml(estHours)}" />
        <div class="field-error" data-error-for="estimated_hours"></div>
      </div>
      <div>
        <div class="label" style="margin-bottom:6px;">Parts Status</div>
        <select class="input" id="in-parts">
          ${partsOpts}
        </select>
        <div class="field-error" data-error-for="parts_status"></div>
      </div>
      <div>
        <div class="label" style="margin-bottom:6px;">Requested Completion Date</div>
        <input class="input" id="in-date" type="date" value="${escapeHtml(job.requested_date||'')}" />
        <div class="field-error" data-error-for="requested_date"></div>
      </div>
    </div>

    <div style="margin-top:10px;">
      <div class="label" style="margin-bottom:6px;">Notes</div>
      <textarea class="input" id="in-notes" rows="3" placeholder="Additional notes…">${escapeHtml(job.notes||'')}</textarea>
    </div>

    <div class="row" style="margin-top:12px;">
      <button class="btn" id="in-submit">Submit Intake</button>
      <button class="btn" id="in-cancel" style="background:var(--surface2,#e0e0e0);color:var(--fg,#222);">Cancel</button>
      <div class="field-error" data-error-for="general"></div>
    </div>
  `;

  el.querySelector('#in-cancel').addEventListener('click', () => {
    el.closest('#intake-panel').style.display = 'none';
  });

  el.querySelector('#in-submit').addEventListener('click', async () => {
    el.querySelectorAll('.field-error').forEach(e => e.textContent = '');

    const payload = {
      so_number:       el.querySelector('#in-so').value.trim().toUpperCase(),
      customer_name:   el.querySelector('#in-customer').value.trim(),
      dealer_name:     el.querySelector('#in-dealer').value.trim(),
      vin_last8:       el.querySelector('#in-vin').value.trim().toUpperCase(),
      job_type:        el.querySelector('#in-job-type').value,
      estimated_hours: el.querySelector('#in-est').value.trim(),
      parts_status:    el.querySelector('#in-parts').value,
      requested_date:  el.querySelector('#in-date').value,
      notes:           el.querySelector('#in-notes').value.trim(),
    };

    const btn = el.querySelector('#in-submit');
    btn.disabled = true;
    btn.textContent = 'Submitting…';

    try {
      await api.intake(job.job_id, payload);
      router();
    } catch(e) {
      const field   = e?.data?.data?.field || e?.data?.field;
      const message = e?.data?.data?.message || e?.message || 'Request failed';
      if (field) {
        const errEl = el.querySelector(`[data-error-for="${field}"]`);
        if (errEl) errEl.textContent = message;
        else el.querySelector('[data-error-for="general"]').textContent = message;
      } else {
        el.querySelector('[data-error-for="general"]').textContent = message;
      }
      btn.disabled = false;
      btn.textContent = 'Submit Intake';
    }
  });
}

async function loadSchedule(){
  const [jobsResp, usersResp] = await Promise.all([
    api.jobs('?status=READY_FOR_SCHEDULING&limit=200'),
    api.users()
  ]);
  const ready = jobsResp.jobs || [];
  const users = usersResp.users || [];
  const userOptions = users.map(u =>
    `<option value="${u.id}">${escapeHtml(u.name)}</option>`
  ).join('');

  view(`
    <div class="card">
      <div class="row">
        <div style="flex:1 1 520px;">
          <h2 style="margin:0;">Schedule</h2>
          <div class="muted">Assign jobs to technicians and set start and end times.</div>
        </div>
        <div style="display:flex; gap:10px; align-items:flex-end;">
          ${kpi('Ready to Schedule', ready.length)}
        </div>
      </div>
    </div>

    <div class="card" id="sched-panel" style="display:none">
      <div id="sched-form-content"></div>
    </div>

    <div class="card">
      <div class="label" style="margin-bottom:8px;">Ready for Scheduling</div>
      <table class="table">
        <thead><tr>
          <th>SO#</th>
          <th>Customer</th>
          <th>VIN</th>
          <th>Job Type</th>
          <th>Est.</th>
          <th>Requested Date</th>
          <th></th>
        </tr></thead>
        <tbody>
          ${ready.map(j=>`
            <tr>
              <td>${escapeHtml(j.so_number||'—')}</td>
              <td>${escapeHtml(j.customer_name||j.dealer_name||'—')}</td>
              <td>${escapeHtml((j.vin||'').slice(-6)||'—')}</td>
              <td>${escapeHtml(fmtStatus(j.job_type||''))}</td>
              <td>${j.estimated_minutes ? minutesToHours(j.estimated_minutes)+'h' : '—'}</td>
              <td>${escapeHtml(j.requested_date||'—')}</td>
              <td><button class="btn small-btn sched-btn" data-id="${j.job_id}">Schedule</button></td>
            </tr>
          `).join('') || `<tr><td colspan="7" class="muted" style="padding:12px;">No jobs ready to schedule.</td></tr>`}
        </tbody>
      </table>
    </div>
  `);

  $$('.sched-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const jobId = parseInt(btn.dataset.id, 10);
      const job = ready.find(j => j.job_id === jobId);
      const panel = $('#sched-panel');
      panel.style.display = '';
      renderScheduleForm($('#sched-form-content'), job, userOptions);
      panel.scrollIntoView({behavior: 'smooth', block: 'start'});
    });
  });
}

function renderScheduleForm(el, job, userOptions) {
  const estHours = job.estimated_minutes ? (job.estimated_minutes/60).toFixed(1) : '';
  const jobLabel = job.so_number || ('Job #' + job.job_id);
  const jobSub   = [job.customer_name, job.dealer_name].filter(Boolean).join(' / ');

  el.innerHTML = `
    <div class="label" style="margin-bottom:6px;">Schedule — ${escapeHtml(jobLabel)}</div>
    ${jobSub ? `<div class="muted" style="margin-bottom:12px;">${escapeHtml(jobSub)}${job.vin ? ' · …'+escapeHtml(job.vin.slice(-6)) : ''}</div>` : ''}

    <div class="form-grid">
      <div>
        <div class="label" style="margin-bottom:6px;">Assign Tech</div>
        <select class="input" id="sf-tech">
          <option value="">Unassigned</option>
          ${userOptions}
        </select>
      </div>
      <div>
        <div class="label" style="margin-bottom:6px;">Work Center / Bay</div>
        <input class="input" id="sf-bay" placeholder="e.g. Bay 3" />
      </div>
      <div>
        <div class="label" style="margin-bottom:6px;">Scheduled Start</div>
        <input class="input" id="sf-start" type="datetime-local" />
      </div>
      <div>
        <div class="label" style="margin-bottom:6px;">Scheduled Finish</div>
        <input class="input" id="sf-finish" type="datetime-local" />
      </div>
      <div>
        <div class="label" style="margin-bottom:6px;">Estimated Hours</div>
        <input class="input" id="sf-est" type="number" min="0.5" step="0.5" placeholder="e.g. 2.5" value="${escapeHtml(estHours)}" />
      </div>
    </div>

    <div class="row" style="margin-top:12px;">
      <button class="btn" id="sf-submit">Confirm Schedule</button>
      <button class="btn" id="sf-cancel" style="background:var(--surface2,#e0e0e0);color:var(--fg,#222);">Cancel</button>
      <div class="field-error" data-error-for="sf-general"></div>
    </div>
  `;

  el.querySelector('#sf-cancel').addEventListener('click', () => {
    el.closest('#sched-panel').style.display = 'none';
  });

  el.querySelector('#sf-submit').addEventListener('click', async () => {
    const techId    = parseInt(el.querySelector('#sf-tech').value, 10) || 0;
    const workCenter= el.querySelector('#sf-bay').value.trim();
    const start     = el.querySelector('#sf-start').value;
    const finish    = el.querySelector('#sf-finish').value;
    const estVal    = parseFloat(el.querySelector('#sf-est').value) || 0;

    const payload = {
      job_id:            job.job_id,
      assigned_user_id:  techId || undefined,
      work_center:       workCenter || undefined,
      scheduled_start:   start || undefined,
      scheduled_finish:  finish || undefined,
      estimated_minutes: estVal > 0 ? Math.round(estVal * 60) : undefined,
    };

    const btn = el.querySelector('#sf-submit');
    btn.disabled = true;
    btn.textContent = 'Scheduling…';

    try {
      await api.schedule(job.job_id, payload);
      el.closest('#sched-panel').style.display = 'none';
      router();
    } catch(e) {
      el.querySelector('[data-error-for="sf-general"]').textContent = e.message || 'Failed to schedule.';
      btn.disabled = false;
      btn.textContent = 'Confirm Schedule';
    }
  });
}

async function loadTech() {
  const [activeResp, myJobsResp] = await Promise.all([
    api.timeActive(),
    api.jobs('?assigned_me=1&limit=100'),
  ]);
  const active  = activeResp.active;
  const allMyJobs = myJobsResp.jobs || [];
  const myJobs  = allMyJobs.filter(j => ['SCHEDULED','IN_PROGRESS','PENDING_QC'].includes(j.status));

  const activeJobId = active ? parseInt(active.job_id, 10) : 0;

  function techJobCard(job) {
    const isActive = job.job_id === activeJobId;
    const estHrs   = job.estimated_minutes ? (job.estimated_minutes / 60).toFixed(1) : null;
    return `
      <div class="tech-job-card${isActive ? ' is-active' : ''}">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:6px;">
          <div>
            <div class="tech-job-so">${escapeHtml(job.so_number || '#' + job.job_id)}</div>
            <div class="tech-job-meta">
              ${escapeHtml(job.customer_name||'')}${job.dealer_name ? ' · '+escapeHtml(job.dealer_name) : ''}${job.vin ? ' · …'+escapeHtml(job.vin.slice(-6)) : ''}
            </div>
          </div>
          <span class="badge ${badgeClass(job.status)}" style="white-space:nowrap;">${fmtStatus(job.status)}</span>
        </div>
        ${job.work_center ? `<div style="font-size:13px;font-weight:700;color:var(--sage);margin-bottom:4px;">${escapeHtml(job.work_center)}</div>` : ''}
        ${estHrs ? `<div class="muted" style="font-size:12px;margin-bottom:10px;">Est ${estHrs} hrs</div>` : ''}
        <div style="display:flex;gap:8px;align-items:center;">
          ${isActive
            ? `<div style="flex:1;font-size:13px;font-weight:700;color:var(--arches);">▲ Active — see timer above</div>`
            : `<button class="btn btn-xl" data-start-job="${job.job_id}" style="flex:1;">Start</button>`
          }
          <button class="btn secondary btn-xl" data-open-job="${job.job_id}">View</button>
        </div>
      </div>
    `;
  }

  view(`
    ${active ? `
      <div class="active-job-card">
        <div class="label-sm">Active Job</div>
        <div class="tech-job-so" style="margin-top:2px;">${escapeHtml(active.so_number || '#' + active.job_id)}</div>
        <div class="tech-job-meta">${escapeHtml(active.customer_name||'')}${active.vin ? ' · …'+escapeHtml(active.vin.slice(-6)) : ''}${active.work_center ? ' · '+escapeHtml(active.work_center) : ''}</div>
        <div class="timer-display" id="live-timer">00:00:00</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <button class="btn danger btn-xl" id="stop-active" style="flex:1;">Stop Job</button>
          <button class="btn secondary btn-xl" id="note-toggle" style="flex:1;">+ Note</button>
        </div>
        <div id="note-panel" style="display:none;margin-top:14px;">
          <textarea class="input" id="note-input" rows="3" placeholder="Add a note to this job…"></textarea>
          <div style="margin-top:8px;display:flex;gap:8px;align-items:center;">
            <button class="btn secondary" id="note-submit">Save Note</button>
            <span class="field-error" data-error-for="note-err" style="flex:1;"></span>
          </div>
        </div>
      </div>
    ` : `
      <div class="card" style="text-align:center;padding:28px 16px;">
        <div style="font-size:13px;font-weight:700;color:rgba(0,0,0,.4);letter-spacing:.1em;text-transform:uppercase;margin-bottom:4px;">No Active Job</div>
        <div class="muted">Start a job below to begin tracking time.</div>
      </div>
    `}

    ${myJobs.length ? myJobs.map(techJobCard).join('') : `
      <div class="card" style="text-align:center;padding:20px;">
        <div class="muted">No jobs currently assigned to you.</div>
      </div>
    `}

    <div class="card">
      <button class="section-header" data-collapse="alljobs-tech">
        <span class="collapse-title">All Jobs</span>
        <span class="collapse-chevron">▸</span>
      </button>
      <div class="collapse-body" id="collapse-alljobs-tech" style="display:none;">
        <input class="input" id="tech-search" placeholder="Search SO#, VIN, customer" style="margin-bottom:10px;" />
        <table class="table" id="tech-table">
          <thead><tr>
            <th>SO#</th><th>VIN</th><th>Customer</th><th>Status</th><th></th>
          </tr></thead>
          <tbody>
            ${allMyJobs.map(j=>`
              <tr>
                <td class="mono">${escapeHtml(j.so_number||'')}</td>
                <td class="mono">${escapeHtml((j.vin||'').slice(-6))}</td>
                <td>${escapeHtml(j.customer_name||'')}</td>
                <td><span class="badge ${badgeClass(j.status)}">${fmtStatus(j.status)}</span></td>
                <td><button class="btn secondary small-btn" data-open-job="${j.job_id}">Open</button></td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    </div>
  `);

  // Live timer
  if (active && active.start_ts) {
    const tick = () => {
      const el = document.getElementById('live-timer');
      if (!el) { clearInterval(state.timerInterval); return; }
      const start = parseGMTTimestamp(active.start_ts);
      el.textContent = formatElapsed(Math.floor((Date.now() - start.getTime()) / 1000));
    };
    tick();
    state.timerInterval = setInterval(tick, 1000);
  }

  const stopBtn = document.getElementById('stop-active');
  if (stopBtn) {
    stopBtn.onclick = async () => {
      stopBtn.disabled = true;
      stopBtn.textContent = 'Stopping…';
      try {
        await api.timeStop();
        clearInterval(state.timerInterval);
        router();
      } catch(e) { alert(e.message); stopBtn.disabled = false; stopBtn.textContent = 'Stop Job'; }
    };
  }

  const noteToggle = document.getElementById('note-toggle');
  if (noteToggle) {
    noteToggle.onclick = () => {
      const p = document.getElementById('note-panel');
      p.style.display = p.style.display === 'none' ? '' : 'none';
      if (p.style.display !== 'none') document.getElementById('note-input').focus();
    };
  }

  const noteSubmit = document.getElementById('note-submit');
  if (noteSubmit) {
    noteSubmit.onclick = async () => {
      const text   = document.getElementById('note-input').value.trim();
      const errEl  = document.querySelector('[data-error-for="note-err"]');
      errEl.textContent = '';
      if (!text) { errEl.textContent = 'Note required.'; return; }
      noteSubmit.disabled = true;
      noteSubmit.textContent = 'Saving…';
      try {
        await api.addNote(activeJobId, text);
        document.getElementById('note-input').value = '';
        document.getElementById('note-panel').style.display = 'none';
        errEl.style.color = 'var(--sage)';
        errEl.textContent = 'Saved.';
        setTimeout(() => { errEl.textContent = ''; errEl.style.color = ''; }, 2000);
      } catch(e) {
        errEl.textContent = e.message;
      }
      noteSubmit.disabled = false;
      noteSubmit.textContent = 'Save Note';
    };
  }

  $$('[data-start-job]').forEach(btn => {
    btn.onclick = async () => {
      const jobId = parseInt(btn.getAttribute('data-start-job'), 10);
      btn.disabled = true;
      btn.textContent = 'Starting…';
      try {
        await api.timeStart(jobId, null, '');
        router();
      } catch(e) {
        alert(e.message);
        btn.disabled = false;
        btn.textContent = 'Start';
      }
    };
  });

  bindCollapsibles();
  bindJobsTable();

  const search = document.getElementById('tech-search');
  if (search) {
    search.addEventListener('input', () => {
      const q = search.value.trim().toLowerCase();
      $$('#tech-table tbody tr').forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    });
  }
}

async function loadExecutive(){
  const jobsResp = await api.jobs('?limit=500');
  const jobs = jobsResp.jobs || [];
  const by = (s)=>jobs.filter(j=>(j.status||'').toUpperCase()===s).length;
  const uns = by('UNSCHEDULED');
  const sch = by('SCHEDULED');
  const prog = by('IN_PROGRESS');
  const qc = by('PENDING_QC');
  const complete = by('COMPLETE');
  const needsSO = jobs.filter(j=>!j.so_number).length;

  view(`
    <div class="card">
      <h2 style="margin:0;">Executive Dashboard</h2>
      <div class="muted">Fast visibility.</div>
    </div>

    <div class="row">
      ${kpi('Needs SO#', needsSO)}
      ${kpi('Unscheduled', uns)}
      ${kpi('Scheduled', sch)}
      ${kpi('In Progress', prog)}
      ${kpi('Pending QC', qc)}
      ${kpi('Complete', complete)}
    </div>

    <div class="card">
      <div class="label" style="margin-bottom:8px;">Recent Activity</div>
      <table class="table">
        <thead><tr><th>SO#</th><th>Customer</th><th>Status</th><th>Updated</th></tr></thead>
        <tbody>
          ${jobs.slice(0,15).map(j=>`
            <tr>
              <td>${escapeHtml(j.so_number||'')}</td>
              <td>${escapeHtml(j.customer_name||'')}</td>
              <td><span class="badge ${badgeClass(j.status)}">${escapeHtml(fmtStatus(j.status))}</span></td>
              <td>${escapeHtml(j.updated_at||'')}</td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>
  `);
}

async function loadQC(){
  const jobsResp = await api.jobs('?limit=300&status=PENDING_QC');
  const jobs = jobsResp.jobs || [];
  view(`
    <div class="card">
      <h2 style="margin:0;">QC Queue</h2>
      <div class="muted">Jobs marked Complete - Pending QC.</div>
    </div>
    <div class="card">
      <table class="table">
        <thead><tr><th>SO#</th><th>Customer</th><th>VIN</th><th></th></tr></thead>
        <tbody>
          ${jobs.map(j=>`
            <tr>
              <td>${escapeHtml(j.so_number||'')}</td>
              <td>${escapeHtml(j.customer_name||'')}</td>
              <td>${escapeHtml((j.vin||'').slice(-6))}</td>
              <td><a class="btn secondary small-btn" href="/ops/job/${j.job_id}" data-link>Open</a></td>
            </tr>
          `).join('') || `<tr><td colspan="4">No jobs pending QC.</td></tr>`}
        </tbody>
      </table>
    </div>
  `);
}

async function loadAdmin() {
  const [usersResp, jobsResp] = await Promise.all([
    api.users(),
    api.jobs('?limit=500'),
  ]);
  const users = usersResp.users || [];
  const jobs  = jobsResp.jobs  || [];
  const byS   = (s) => jobs.filter(j => j.status === s).length;

  view(`
    <div class="card">
      <h2 style="margin:0 0 4px;">Admin</h2>
      <div class="muted">Ops role management and system overview.</div>
    </div>

    <div class="stat-grid">
      <div class="stat-tile"><div class="label">Active Jobs</div><div class="value">${jobs.filter(j=>j.status!=='COMPLETE').length}</div></div>
      <div class="stat-tile"><div class="label">In Progress</div><div class="value">${byS('IN_PROGRESS')}</div></div>
      <div class="stat-tile"><div class="label">Pending QC</div><div class="value">${byS('PENDING_QC')}</div></div>
      <div class="stat-tile"><div class="label">Unscheduled</div><div class="value">${byS('UNSCHEDULED')}</div></div>
      <div class="stat-tile"><div class="label">Users</div><div class="value">${users.length}</div></div>
    </div>

    <div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
        <div class="collapse-title">Users &amp; Ops Roles</div>
        <a class="btn secondary small-btn" href="/wp-admin/users.php">WP Admin ↗</a>
      </div>
      <table class="table">
        <thead>
          <tr><th>Name</th><th>Email</th><th>Ops Role</th><th style="width:60px;"></th></tr>
        </thead>
        <tbody>
          ${users.map(u => `
            <tr>
              <td style="font-weight:600;">${escapeHtml(u.name)}</td>
              <td style="font-size:13px;color:rgba(0,0,0,.5);">${escapeHtml(u.email)}</td>
              <td>
                <select class="input role-select" style="padding:6px 8px;font-size:13px;width:auto;min-width:120px;" data-uid="${u.id}">
                  <option value=""          ${u.ops_role===''         ?'selected':''}>No Role</option>
                  <option value="tech"       ${u.ops_role==='tech'       ?'selected':''}>Tech</option>
                  <option value="cs"         ${u.ops_role==='cs'         ?'selected':''}>CS</option>
                  <option value="supervisor" ${u.ops_role==='supervisor' ?'selected':''}>Supervisor</option>
                  <option value="admin"      ${u.ops_role==='admin'      ?'selected':''}>Admin</option>
                </select>
              </td>
              <td>
                <span class="role-save-status" data-uid="${u.id}" style="font-size:11px;color:rgba(0,0,0,.4);white-space:nowrap;"></span>
              </td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>

    <div class="card">
      <div class="collapse-title" style="margin-bottom:10px;">Quick Links</div>
      <div class="row">
        <a class="btn secondary" href="/wp-admin/users.php">WP Users</a>
        <a class="btn secondary" href="/ops/settings" data-link>Settings</a>
        <a class="btn secondary" href="/ops/exec" data-link>Exec Dashboard</a>
        <a class="btn secondary" href="/ops/jobs" data-link>All Jobs</a>
      </div>
    </div>
  `);

  $$('.role-select').forEach(sel => {
    sel.onchange = async () => {
      const uid      = parseInt(sel.getAttribute('data-uid'), 10);
      const statusEl = document.querySelector(`.role-save-status[data-uid="${uid}"]`);
      statusEl.textContent = 'Saving…';
      try {
        await api.updateUserRole(uid, sel.value);
        statusEl.textContent = 'Saved ✓';
        setTimeout(() => { statusEl.textContent = ''; }, 2500);
      } catch(e) {
        statusEl.textContent = 'Error';
      }
    };
  });

  linkify();
}

  async function render(){
    if (state.timerInterval) { clearInterval(state.timerInterval); state.timerInterval = null; }
    const r = state.route;
    setActiveNav(
      r.startsWith('/job/') ? '/jobs' :
      r.startsWith('/jobs') ? '/jobs' :
      r.startsWith('/new') ? '/new' :
      r.startsWith('/settings') ? '/settings' :
      r.startsWith('/supervisor') ? '/supervisor' :
      r.startsWith('/cs') ? '/cs' :
      r.startsWith('/tech') ? '/tech' :
      r.startsWith('/admin') ? '/admin' :
      r.startsWith('/exec') ? '/exec' :
      r.startsWith('/qc') ? '/qc' :
      r.startsWith('/schedule') ? '/schedule' :
      '/'
    );
    setPageTitle(
      r.startsWith('/exec')       ? 'Dashboard'        :
      r.startsWith('/cs')         ? 'Customer Service' :
      r.startsWith('/tech')       ? 'Tech'             :
      r.startsWith('/qc')         ? 'QC Queue'         :
      r.startsWith('/admin')      ? 'Admin'            :
      r.startsWith('/job/')       ? 'Job Detail'       :
      r.startsWith('/jobs')       ? 'Jobs'             :
      r.startsWith('/new')        ? 'Create Job'       :
      r.startsWith('/supervisor') ? 'Supervisor'       :
      r.startsWith('/schedule')   ? 'Schedule'         :
      r.startsWith('/settings')   ? 'Settings'         :
      'Dashboard'
    );

    try{
      if (r === '/' || r === '') {
        const c = slateOpsSettings.user.caps || {};
        if (c.admin) { window.history.replaceState({}, '', '/ops/exec'); state.route='/exec'; }
        else if (c.supervisor) { window.history.replaceState({}, '', '/ops/supervisor'); state.route='/supervisor'; }
        else if (c.cs) { window.history.replaceState({}, '', '/ops/cs'); state.route='/cs'; }
        else if (c.tech) { window.history.replaceState({}, '', '/ops/tech'); state.route='/tech'; }
        else { window.history.replaceState({}, '', '/ops/exec'); state.route='/exec'; }
        return await render();
      }
      if (r.startsWith('/exec')) return await loadExecutive();
      if (r.startsWith('/cs')) return await loadCS();
      if (r.startsWith('/tech')) return await loadTech();
      if (r.startsWith('/qc')) return await loadQC();
      if (r.startsWith('/admin')) return await loadAdmin();
      if (r === '/' || r === '') return await loadDashboard();
      if (r.startsWith('/jobs')) return await loadJobsList();
      if (r.startsWith('/job/')) {
        const id = r.split('/')[2];
        return await loadJobDetail(id);
      }
      if (r.startsWith('/new')) return await loadCreateJob();
      if (r.startsWith('/supervisor')) return await loadSupervisor();
      if (r.startsWith('/schedule')) return await loadSchedule();
      if (r.startsWith('/settings')) return await loadSettings();

      // fallback
      return await loadDashboard();
    }catch(e){
      view(`<div class="card"><h2>Error</h2><div>${escapeHtml(e.message)}</div></div>`);
    }
  }

  window.addEventListener('popstate', router);

  document.addEventListener('DOMContentLoaded', () => {
    linkify();
    router();
  });

})();
