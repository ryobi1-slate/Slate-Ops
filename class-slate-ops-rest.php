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
    // CS intake must update the full job record (not just SO#)
    intake(id, payload){ return this.req('/jobs/' + id, {method:'PATCH', body: JSON.stringify(payload)}); },
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

  // Role helpers (from wp_localize_script)
  const caps = (window.slateOpsSettings && window.slateOpsSettings.user && window.slateOpsSettings.user.caps) ? window.slateOpsSettings.user.caps : {};
  const isAdmin = !!caps.admin;
  const isSupervisor = !!caps.supervisor;
  const isCS = !!caps.cs;
  const isTech = !!caps.tech;


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
    const csOnly = !!caps.cs && !caps.supervisor && !caps.admin;
    const canEdit = isSupervisor || isCS || isAdmin;// CS/Admin need to edit job details during Phase 0 manual intake

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
          <div style="display:flex;flex-direction:column;gap:8px;">
            ${notesLog.map(n => `
              <div class="note-bubble">
                <div class="meta">
                  <span class="author">${escapeHtml(n.user_name || '')}</span>
                  <span>${escapeHtml(n.created_at || '')}</span>
                </div>
                <div class="body">${escapeHtml(n.note || '')}</div>
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
        <div class="row" style="align-items:center;">
          <div style="flex:1 1 280px;">
            <h2 style="margin:0;">Supervisor Operations Center</h2>
            <div class="muted" style="margin-top:4px;">Production oversight and crew management.</div>
          </div>
          <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            ${kpi('In Progress',    byS('IN_PROGRESS').length)}
            ${kpi('Scheduled',      byS('SCHEDULED').length)}
            ${kpi('Pending QC',     byS('PENDING_QC').length)}
            <span style="padding:6px 14px;border-radius:20px;background:rgba(39,174,96,0.13);color:#1a8a4a;font-size:12px;font-weight:700;letter-spacing:.04em;">&#x2022; System Status: Optimal</span>
          </div>
        </div>
      </div>

      <div class="row" style="align-items:flex-start;gap:16px;">
        <div class="card" style="flex:1 1 320px;margin:0;">
          <div class="row" style="margin-bottom:10px;align-items:center;">
            <span class="collapse-title">Pending Corrections</span>
            ${pending.length ? `<span class="count-badge urgent">${pending.length} Critical</span>` : `<span class="muted" style="font-size:12px;">None</span>`}
          </div>
          <table class="table">
            <thead><tr><th>Job</th><th>Tech</th><th>Note</th><th></th></tr></thead>
            <tbody>
              ${pending.map(p=>`
                <tr>
                  <td><button class="btn-link" data-open-job="${p.job_id}">${escapeHtml(p.job_id ? '#'+p.job_id : '—')}</button></td>
                  <td>${escapeHtml(p.user_name||'—')}</td>
                  <td style="font-size:12px;color:rgba(0,0,0,.55);">${escapeHtml(p.note||'—')}</td>
                  <td><button class="btn secondary small-btn" data-open-job="${p.job_id}">Re-assign</button></td>
                </tr>
              `).join('') || `<tr><td colspan="4" class="muted" style="padding:10px;">No pending corrections.</td></tr>`}
            </tbody>
          </table>
        </div>

        <div class="card" style="flex:1 1 320px;margin:0;">
          <div class="row" style="margin-bottom:10px;align-items:center;">
            <span class="collapse-title">Unassigned Personnel Time</span>
            ${unassigned.length ? `<span class="count-badge">${unassigned.length}</span>` : `<span class="muted" style="font-size:12px;">None</span>`}
          </div>
          ${unassigned.length ? `
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
            ${unassigned.slice(0,4).map(u=>{
              let hrs = '—';
              if (u.start_ts && u.end_ts) {
                const diff = (new Date(u.end_ts) - new Date(u.start_ts)) / 3600000;
                hrs = diff.toFixed(1) + 'h';
              }
              return `
              <div style="background:rgba(64,79,75,0.06);border-radius:8px;padding:12px;">
                <div style="font-size:20px;font-weight:900;color:var(--sage);">${hrs}</div>
                <div style="font-size:11px;font-weight:700;color:rgba(0,0,0,.5);margin:2px 0 6px;text-transform:uppercase;letter-spacing:.1em;">${escapeHtml(u.user_name||u.reason||'—')}</div>
                <button class="btn secondary small-btn" data-open-job="${u.job_id}">View Block</button>
              </div>`;
            }).join('')}
          </div>` : `<div class="muted" style="padding:10px;">No unassigned segments.</div>`}
        </div>
      </div>

      <div class="card">
        <div class="row" style="margin-bottom:12px;align-items:center;">
          <span class="collapse-title">Today's Production Schedule</span>
          <div style="display:flex;gap:8px;align-items:center;">
            ${todayJobs.length ? `<span class="count-badge">${todayJobs.length}</span>` : ''}
            <button class="btn secondary small-btn" id="export-schedule-csv">Export CSV</button>
          </div>
        </div>
        <table class="table">
          <thead><tr><th>SO Number</th><th>Vehicle Type</th><th>Station</th><th>Technician</th><th>ETA Completion</th><th>Status</th><th></th></tr></thead>
          <tbody>
            ${todayJobs.map(j=>`
              <tr>
                <td class="mono">${escapeHtml(j.so_number||'—')}</td>
                <td>${escapeHtml(j.job_type||'—')}</td>
                <td><span style="padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;background:rgba(64,79,75,0.1);color:var(--sage);">${escapeHtml(j.work_center||'—')}</span></td>
                <td>${escapeHtml(j.assigned_name||'—')}</td>
                <td style="font-size:12px;">${escapeHtml(j.scheduled_end||j.due_date||'—')}</td>
                <td><span class="badge ${badgeClass(j.status)}">${fmtStatus(j.status)}</span></td>
                <td><button class="btn secondary small-btn" data-open-job="${j.job_id}">Open</button></td>
              </tr>
            `).join('') || `<tr><td colspan="7" class="muted" style="padding:10px;">No jobs scheduled today.</td></tr>`}
          </tbody>
        </table>
      </div>
    `);

    bindJobsTable();

    const exportBtn = $('#export-schedule-csv');
    if (exportBtn) {
      exportBtn.onclick = () => {
        const rows = [['SO#','Vehicle Type','Station','Technician','ETA','Status']];
        todayJobs.forEach(j => rows.push([
          j.so_number||'', j.job_type||'', j.work_center||'',
          j.assigned_name||'', j.scheduled_end||j.due_date||'', j.status||''
        ]));
        const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n');
        const a = document.createElement('a');
        a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
        a.download = 'schedule-' + today + '.csv';
        a.click();
      };
    }
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
      <div style="margin-bottom:20px;">
        <h1 style="font-size:22px;font-weight:900;color:var(--sage);letter-spacing:-0.01em;margin:0 0 4px;">System Settings</h1>
        <p class="muted">Configure global parameters and production environment preferences.</p>
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;margin-bottom:16px;">

        <div class="card" style="margin-bottom:0;padding:0;overflow:hidden;">
          <div style="padding:16px 20px 14px;background:rgba(0,0,0,0.025);border-bottom:1px solid rgba(0,0,0,0.08);">
            <h2 style="font-size:15px;margin:0 0 2px;">Shift Schedule</h2>
            <div class="label">Production Hours</div>
          </div>
          <div style="padding:20px;" class="form-grid">
            <label><div class="label" style="margin-bottom:6px;">Shift Start</div><input class="input" id="shift_start" value="${escapeHtml(s.shift_start || '07:00:00')}" /></label>
            <label><div class="label" style="margin-bottom:6px;">Shift End</div><input class="input" id="shift_end" value="${escapeHtml(s.shift_end || '15:30:00')}" /></label>
            <label><div class="label" style="margin-bottom:6px;">Lunch (min)</div><input class="input" id="lunch_minutes" type="number" min="0" value="${parseInt(s.lunch_minutes)||30}" /></label>
            <label><div class="label" style="margin-bottom:6px;">Break (min)</div><input class="input" id="break_minutes" type="number" min="0" value="${parseInt(s.break_minutes)||20}" /></label>
          </div>
          <div style="padding:12px 20px;background:rgba(0,0,0,0.025);border-top:1px solid rgba(0,0,0,0.08);display:flex;justify-content:flex-end;">
            <button class="btn" id="save_shift">Save Shift</button>
          </div>
        </div>

        <div class="card" style="margin-bottom:0;padding:0;overflow:hidden;">
          <div style="padding:16px 20px 14px;background:rgba(0,0,0,0.025);border-bottom:1px solid rgba(0,0,0,0.08);">
            <h2 style="font-size:15px;margin:0 0 2px;">Dealers</h2>
            <div class="label">Authorized Dealerships</div>
          </div>
          <div style="padding:20px;">
            ${tagListHtml('dealers', dealers, 'Dealer name…')}
          </div>
          <div style="padding:12px 20px;background:rgba(0,0,0,0.025);border-top:1px solid rgba(0,0,0,0.08);display:flex;justify-content:flex-end;">
            <button class="btn" id="save_dealers">Save Dealers</button>
          </div>
        </div>

      </div>

      <div class="card" style="padding:0;overflow:hidden;">
        <div style="padding:16px 20px 14px;background:rgba(0,0,0,0.025);border-bottom:1px solid rgba(0,0,0,0.08);">
          <h2 style="font-size:15px;margin:0 0 2px;">Sales People</h2>
          <div class="label">Sales Team</div>
        </div>
        <div style="padding:20px;">
          ${tagListHtml('sales_people', salesPeople, 'Sales person name…')}
        </div>
        <div style="padding:12px 20px;background:rgba(0,0,0,0.025);border-top:1px solid rgba(0,0,0,0.08);display:flex;justify-content:flex-end;">
          <button class="btn" id="save_sales">Save Sales People</button>
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

    function buildPayload() {
      return {
        shift_start: $('#shift_start').value.trim(),
        shift_end: $('#shift_end').value.trim(),
        lunch_minutes: parseInt($('#lunch_minutes').value, 10) || 0,
        break_minutes: parseInt($('#break_minutes').value, 10) || 0,
        dealers,
        sales_people: salesPeople,
      };
    }

    async function saveSettings(btnId) {
      const btn = $(btnId);
      const orig = btn.textContent;
      btn.disabled = true;
      btn.textContent = 'Saving…';
      try {
        await api.updateSettings(buildPayload());
        btn.textContent = 'Saved';
        setTimeout(() => { btn.textContent = orig; btn.disabled = false; }, 1800);
      } catch(e) {
        alert(e.message);
        btn.textContent = orig;
        btn.disabled = false;
      }
    }

    $('#save_shift').onclick   = () => saveSettings('#save_shift');
    $('#save_dealers').onclick = () => saveSettings('#save_dealers');
    $('#save_sales').onclick   = () => saveSettings('#save_sales');
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
        <div class="input" style="background:rgba(0,0,0,0.04);color:rgba(0,0,0,0.45);cursor:default;">Manual Entry</div>
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
    api.jobs('?limit=300'),
    api.settings(),
  ]);

  const allJobs    = jobsResp.jobs || [];
  const dealerList = settingsResp.dealers || [];
  const salesList  = settingsResp.sales_people || [];

  const portalNeeds = allJobs.filter(j => {
    const s = (j.status||'').toUpperCase();
    return (s === 'UNSCHEDULED' || s === 'READY_FOR_SCHEDULING') && j.created_from === 'portal';
  });
  const manualNeeds = allJobs.filter(j => {
    const s = (j.status||'').toUpperCase();
    return (s === 'UNSCHEDULED' || s === 'READY_FOR_SCHEDULING') && j.created_from !== 'portal' && !j.so_number;
  });
  const activeJobs = allJobs.filter(j => {
    const s = (j.status||'').toUpperCase();
    return s === 'SCHEDULED' || s === 'IN_PROGRESS' || s === 'PENDING_QC';
  });

  view(`
    <div class="card">
      <div class="row" style="align-items:flex-start;">
        <div style="flex:1 1 320px;">
          <h2 style="margin:0;">Customer Service</h2>
          <div class="muted" style="margin-top:4px;">Complete intake and assign SO#s for incoming jobs.</div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          ${kpi('Pending Intake', portalNeeds.length)}
          ${kpi('Needs SO#', manualNeeds.length)}
          ${kpi('Active Jobs', activeJobs.length)}
        </div>
      </div>
    </div>

    <div class="card" id="intake-panel" style="display:none;">
      <div id="intake-form-content"></div>
    </div>

    ${(isCS || isAdmin) ? `

    <div class="card" style="margin-bottom:12px;">
      <div style="display:flex;gap:10px;align-items:center;">
        <input id="cs-search" class="input" type="text"
          placeholder="Search SO#, VIN, customer, dealer..."
          style="flex:1;min-width:260px;"
        />
        <button type="button" class="btn secondary" id="cs-search-clear">Clear</button>
      </div>
      <div class="muted" style="margin-top:8px;" id="cs-search-count"></div>
    </div>

    <div class="card">
      <button class="section-header" data-collapse="create-manual">
        <span class="collapse-title">Create Manual Job</span>
        <span class="collapse-chevron">▾</span>
      </button>
      <div class="collapse-body" id="collapse-create-manual">
        <form id="manual-create" class="grid">
          <label>Customer<input name="customer_name" placeholder="Customer" /></label>
          <label>Dealer<select name="dealer_name" id="create-dealer"></select></label>
          <label>VIN<input name="vin" class="mono" placeholder="VIN (required unless Parts Only)" /></label>
          <label>Job Type
            <select name="job_type">
              <option value="UPFIT">UPFIT</option>
              <option value="RV_BUILD">RV_BUILD</option>
              <option value="PARTS_ONLY">PARTS_ONLY</option>
              <option value="SERVICE">SERVICE</option>
              <option value="WARRANTY">WARRANTY</option>
            </select>
          </label>
          <label>Parts Status
            <select name="parts_status">
              <option value="NOT_READY">NOT_READY</option>
              <option value="PARTIAL">PARTIAL</option>
              <option value="READY">READY</option>
              <option value="HOLD">HOLD</option>
            </select>
          </label>
          <label>Estimated Hours<input name="estimated_hours" type="number" min="0" step="0.25" value="1" /></label>
          <label>SO# (optional)<input name="so_number" class="mono" placeholder="S-ORD#####" /></label>
          <label>Due Date (optional)<input name="due_date" type="date" /></label>
          <div style="grid-column:1/-1;display:flex;gap:8px;align-items:center;">
            <button type="submit" class="btn">Create Job</button>
            <span class="muted" id="create-status"></span>
          </div>
        </form>
      </div>
    </div>
    ` : ``}

    <div class="card">
      <button class="section-header" data-collapse="intake">
        <span class="collapse-title">Pending Intake — Portal Jobs</span>
        <div style="display:flex;align-items:center;gap:8px;">
          ${portalNeeds.length ? `<span class="count-badge urgent">${portalNeeds.length}</span>` : ''}
          <span class="collapse-chevron">${portalNeeds.length ? '▾' : '▸'}</span>
        </div>
      </button>
      <div class="collapse-body" id="collapse-intake" ${!portalNeeds.length ? 'style="display:none;"' : ''}>
        <table class="table" data-cs-table="1">
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
        <table class="table" data-cs-table="1">
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
            `).join('') || `<tr><td colspan="5" class="muted" style="padding:10px;">All manual jobs have SO#s.</td></tr>`}
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <button class="section-header" data-collapse="active">
        <span class="collapse-title">Active Jobs</span>
        <div style="display:flex;align-items:center;gap:8px;">
          ${activeJobs.length ? `<span class="count-badge">${activeJobs.length}</span>` : ''}
          <span class="collapse-chevron">${activeJobs.length ? '▾' : '▸'}</span>
        </div>
      </button>
      <div class="collapse-body" id="collapse-active" ${!activeJobs.length ? 'style="display:none;"' : ''}>
        <table class="table" data-cs-table="1">
          <thead><tr>
            <th>SO#</th><th>Customer</th><th>VIN</th><th>Status</th><th>Assigned</th><th></th>
          </tr></thead>
          <tbody>
            ${activeJobs.map(j=>`
              <tr>
                <td class="mono">${escapeHtml(j.so_number||'—')}</td>
                <td>${escapeHtml(j.customer_name||'—')}</td>
                <td class="mono">${escapeHtml((j.vin||'').slice(-6)||'—')}</td>
                <td><span class="badge ${badgeClass(j.status)}">${fmtStatus(j.status)}</span></td>
                <td>${escapeHtml(j.assigned_name||'—')}</td>
                <td><button class="btn secondary small-btn" data-open-job="${j.job_id}">View</button></td>
              </tr>
            `).join('') || `<tr><td colspan="6" class="muted" style="padding:10px;">No active jobs.</td></tr>`}
          </tbody>
        </table>
      </div>
    </div>
  `);

  bindCollapsibles();
  bindJobsTable();


  // CS search - filters all CS tables marked with data-cs-table="1"
  const csSearch = document.getElementById('cs-search');
  const csClear  = document.getElementById('cs-search-clear');
  const csCount  = document.getElementById('cs-search-count');

  function csApplyFilter() {
    const q = (csSearch ? csSearch.value : '').trim().toLowerCase();
    let shown = 0;
    let total = 0;

    document.querySelectorAll('table[data-cs-table="1"] tbody tr').forEach(tr => {
      total++;
      const txt = (tr.textContent || '').toLowerCase();
      const ok = (!q) || txt.includes(q);
      tr.style.display = ok ? '' : 'none';
      if (ok) shown++;
    });

    if (csCount) {
      csCount.textContent = q ? (shown + ' of ' + total + ' rows shown') : '';
    }
  }

  if (csSearch) csSearch.addEventListener('input', csApplyFilter);
  if (csClear) csClear.addEventListener('click', () => {
    if (csSearch) csSearch.value = '';
    csApplyFilter();
    if (csSearch) csSearch.focus();
  });

  csApplyFilter();


  // Manual job create (Phase 0)
  const createForm = $('#manual-create');
  if (createForm) {
    const dealerSel = $('#create-dealer');
    if (dealerSel) {
      const opts = ['<option value="">Select…</option>'];
      dealerList.forEach((d) => {
        opts.push('<option value="' + escapeHtml(d) + '">' + escapeHtml(d) + '</option>');
      });
      dealerSel.innerHTML = opts.join('');
    }

    createForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(createForm);
      const payload = {
        customer_name: (fd.get('customer_name')||'').toString().trim(),
        dealer_name: (fd.get('dealer_name')||'').toString().trim(),
        vin: (fd.get('vin')||'').toString().trim(),
        job_type: (fd.get('job_type')||'UPFIT').toString(),
        parts_status: (fd.get('parts_status')||'NOT_READY').toString(),
        estimated_hours: parseFloat((fd.get('estimated_hours')||'0').toString()) || 0,
        so_number: (fd.get('so_number')||'').toString().trim(),
        due_date: ((fd.get('due_date')||'').toString().trim() || null),
      };

      if (!payload.job_type) { alert('Job Type required'); return; }
      if (!payload.parts_status) { alert('Parts Status required'); return; }
      if (!payload.estimated_hours || payload.estimated_hours <= 0) { alert('Estimated Hours required'); return; }
      if (payload.job_type !== 'PARTS_ONLY' && !payload.vin) { alert('VIN required (unless Parts Only)'); return; }
      if (!payload.customer_name && !payload.dealer_name) { alert('Customer or Dealer required'); return; }

      const statusEl = $('#create-status');
      if (statusEl) statusEl.textContent = 'Creating…';
      const btn = createForm.querySelector('button[type="submit"]');
      if (btn) btn.disabled = true;

      try {
        const res = await api.createJob(payload);
        if (statusEl) statusEl.textContent = 'Created';
        const newId = res && (res.job_id || res.id);
        if (newId) location.hash = '#/job/' + newId;
        else router();
      } catch (err) {
        alert((err && err.message) ? err.message : 'Create failed');
        if (statusEl) statusEl.textContent = '';
      } finally {
        if (btn) btn.disabled = false;
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
      <button class="btn secondary" id="in-cancel">Cancel</button>
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
  const [jobsResp, settingsResp, usersResp] = await Promise.all([
    api.jobs('?limit=500'),
    api.settings(),
    api.users(),
  ]);
  const allJobs   = jobsResp.jobs || [];
  const bays      = (settingsResp.bays || [{id:1,name:'Bay 1',active:true},{id:2,name:'Bay 2',active:true}]).filter(b=>b.active);
  const users     = usersResp.users || [];
  const userOpts  = users.map(u=>`<option value="${u.id}">${escapeHtml(u.name)}</option>`).join('');
  const bayOpts   = bays.map(b=>`<option value="${escapeHtml(b.name)}">${escapeHtml(b.name)}</option>`).join('');

  let weekOffset = 0;

  // ── helpers ─────────────────────────────────────────
  function getWeekDates(offset){
    const d = new Date(); d.setHours(0,0,0,0);
    const day = d.getDay(); // 0=Sun
    const mon = new Date(d); mon.setDate(d.getDate() - ((day + 6) % 7) + (offset * 7));
    const out = [];
    for(let i=0;i<7;i++){ const c=new Date(mon); c.setDate(mon.getDate()+i); out.push(c); }
    return out;
  }
  function ds(d){ return d.toISOString().split('T')[0]; }
  function shortDay(d){ return d.toLocaleDateString('en-US',{weekday:'short'}); }
  function shortDate(d){ return d.toLocaleDateString('en-US',{month:'short',day:'numeric'}); }

  function closeModal(){
    const m = document.querySelector('.modal-overlay');
    if(m) m.remove();
  }

  // ── render ──────────────────────────────────────────
  function renderBoard(){
    const dates = getWeekDates(weekOffset);
    const today = ds(new Date());
    const wkStart = ds(dates[0]), wkEnd = ds(dates[6]);

    // jobs with scheduled start that overlap this week
    const weekJobs = allJobs.filter(j=>{
      if(!j.scheduled_start) return false;
      const s = (j.scheduled_start||'').split(' ')[0];
      const f = (j.scheduled_finish||j.scheduled_start||'').split(' ')[0];
      return s <= wkEnd && f >= wkStart;
    });

    // unscheduled / ready-for-scheduling
    const unscheduled = allJobs.filter(j=>{
      const s=(j.status||'').toUpperCase();
      return s==='UNSCHEDULED'||s==='READY_FOR_SCHEDULING';
    });

    // KPIs
    const delayed = allJobs.filter(j=>j.status==='DELAYED'||j.status==='BLOCKED').length;

    // day headers
    const dayNames = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
    const dayHeaders = dates.map((d,i)=>{
      const t = ds(d)===today;
      return `<th class="sched-day-header${t?' sched-today':''}">${dayNames[i]}<span>${shortDate(d)}</span></th>`;
    }).join('');

    // bay rows
    const bayRows = bays.map(bay=>{
      const cells = dates.map(d=>{
        const dStr = ds(d);
        const t = dStr===today;
        const dayJobs = weekJobs.filter(j=>{
          const jBay = j.work_center||'';
          if(jBay!==bay.name) return false;
          const s=(j.scheduled_start||'').split(' ')[0];
          const f=(j.scheduled_finish||j.scheduled_start||'').split(' ')[0];
          return s<=dStr && f>=dStr;
        });
        const cards = dayJobs.map(j=>{
          const pri = j.priority && j.priority <= 2;
          return `<div class="sched-card${pri?' sched-card-priority':''}" draggable="true" data-job-id="${j.job_id}" data-bay="${escapeHtml(bay.name)}">
            <div class="sched-card-title">${escapeHtml(j.customer_name||j.so_number||'#'+j.job_id)}</div>
            <div class="sched-card-so">${escapeHtml(j.so_number||'')}</div>
            <div class="sched-card-meta">${escapeHtml(j.job_type?fmtStatus(j.job_type):'') }${j.assigned_name?' · '+escapeHtml(j.assigned_name):''}</div>
            ${pri?'<div class="sched-card-flag">PRIORITY</div>':''}
          </div>`;
        }).join('');
        return `<td class="sched-cell${t?' sched-today':''}" data-bay="${escapeHtml(bay.name)}" data-date="${dStr}">${cards}</td>`;
      }).join('');
      return `<tr><td class="sched-bay-label">${escapeHtml(bay.name)}</td>${cells}</tr>`;
    }).join('');

    const weekLabel = shortDate(dates[0])+' — '+shortDate(dates[6])+', '+dates[6].getFullYear();

    // unscheduled list
    const unsRows = unscheduled.slice(0,20).map(j=>`
      <div class="sched-unscheduled-item" draggable="true" data-job-id="${j.job_id}">
        <div style="flex:1;min-width:0;">
          <div style="font-weight:600;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(j.customer_name||j.so_number||'#'+j.job_id)}</div>
          <div class="muted" style="font-size:11px;">${escapeHtml(j.so_number||'')}${j.estimated_minutes?' · '+minutesToHours(j.estimated_minutes)+'h':''}</div>
        </div>
        <button class="btn secondary small-btn sched-quick-add" data-id="${j.job_id}" style="white-space:nowrap;">+ Schedule</button>
      </div>
    `).join('');

    view(`
      <div class="card" style="margin-bottom:12px;">
        <div class="row" style="align-items:flex-start;">
          <div style="flex:1;">
            <h2 style="margin:0;">Production Schedule</h2>
            <div class="muted" style="margin-top:4px;">Drag jobs to reschedule. Click to view details.</div>
          </div>
          <div style="display:flex;gap:10px;flex-wrap:wrap;">
            ${kpi('This Week', weekJobs.length)}
            ${kpi('Unscheduled', unscheduled.length)}
            ${kpi('Delayed', delayed)}
          </div>
        </div>
      </div>

      <div class="card" style="padding:16px;">
        <div class="sched-toolbar">
          <div class="inline">
            <button class="btn secondary small-btn" id="sched-prev">← Prev</button>
            <button class="btn secondary small-btn" id="sched-today-btn">Today</button>
            <button class="btn secondary small-btn" id="sched-next">Next →</button>
            <span class="sched-week-label">${weekLabel}</span>
          </div>
          <button class="btn small-btn" id="sched-new-entry">+ New Entry</button>
        </div>
        <div class="sched-grid-wrap">
          <table class="sched-grid">
            <thead><tr><th class="sched-bay-header">Bays</th>${dayHeaders}</tr></thead>
            <tbody>${bayRows}</tbody>
          </table>
        </div>
      </div>

      ${unscheduled.length ? `
      <div class="card">
        <h2 style="margin:0 0 8px;">Unscheduled Jobs</h2>
        <div class="muted" style="margin-bottom:10px;">Drag onto the board above, or click + Schedule.</div>
        ${unsRows}
      </div>` : ''}
    `);

    bindBoard();
  }

  // ── board bindings ──────────────────────────────────
  function bindBoard(){
    $('#sched-prev').onclick = ()=>{ weekOffset--; renderBoard(); };
    $('#sched-next').onclick = ()=>{ weekOffset++; renderBoard(); };
    $('#sched-today-btn').onclick = ()=>{ weekOffset=0; renderBoard(); };
    $('#sched-new-entry').onclick = ()=> openNewEntryModal();

    // click card → job modal
    $$('.sched-card').forEach(c=>{
      c.addEventListener('click', e=>{
        if(c.classList.contains('dragging')) return;
        openJobModal(parseInt(c.getAttribute('data-job-id'),10));
      });
    });

    // ── drag from board cards ───────────────────────
    $$('.sched-card, .sched-unscheduled-item[draggable]').forEach(el=>{
      el.addEventListener('dragstart', e=>{
        el.classList.add('dragging');
        e.dataTransfer.setData('text/plain', el.getAttribute('data-job-id'));
        e.dataTransfer.effectAllowed = 'move';
      });
      el.addEventListener('dragend', ()=>el.classList.remove('dragging'));
    });

    // ── drop targets ────────────────────────────────
    $$('.sched-cell').forEach(cell=>{
      cell.addEventListener('dragover', e=>{ e.preventDefault(); cell.classList.add('sched-drop-target'); });
      cell.addEventListener('dragleave', ()=> cell.classList.remove('sched-drop-target'));
      cell.addEventListener('drop', async e=>{
        e.preventDefault();
        cell.classList.remove('sched-drop-target');
        const jid = parseInt(e.dataTransfer.getData('text/plain'),10);
        const bay = cell.getAttribute('data-bay');
        const date= cell.getAttribute('data-date');
        if(!jid||!bay||!date) return;

        const job = allJobs.find(j=>j.job_id===jid);
        if(!job) return;

        // preserve duration
        const oldS = (job.scheduled_start||'').split(' ')[0];
        const oldF = (job.scheduled_finish||job.scheduled_start||'').split(' ')[0];
        const dur  = (oldS && oldF) ? Math.max(0, Math.round((new Date(oldF)-new Date(oldS))/86400000)) : 0;
        const nf   = new Date(date); nf.setDate(nf.getDate()+dur);

        try {
          await api.schedule(jid, { work_center:bay, scheduled_start:date, scheduled_finish:ds(nf) });
          job.work_center = bay;
          job.scheduled_start = date;
          job.scheduled_finish = ds(nf);
          if(job.status==='UNSCHEDULED'||job.status==='READY_FOR_SCHEDULING') job.status='SCHEDULED';
          renderBoard();
        } catch(err){ alert(err.message); }
      });
    });

    // ── unscheduled + Schedule button ────────────────
    $$('.sched-quick-add').forEach(btn=>{
      btn.onclick = e=>{
        e.stopPropagation();
        const jid = parseInt(btn.getAttribute('data-id'),10);
        openNewEntryModal(jid);
      };
    });
  }

  // ── Job detail modal ────────────────────────────────
  async function openJobModal(jobId){
    const job = allJobs.find(j=>j.job_id===jobId) || await api.job(jobId);
    const estH = job.estimated_minutes ? (job.estimated_minutes/60).toFixed(1) : '—';
    const t = job.time || {approved_minutes_total:0,pending_minutes_total:0};
    const partsLabel = {'NOT_READY':'Not Ready','PARTIAL':'Partial','READY':'Ready','HOLD':'Hold'};

    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.innerHTML = `
      <div class="modal">
        <div class="modal-header">
          <h2>${escapeHtml(job.so_number||'Job #'+job.job_id)}</h2>
          <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
            <span class="badge ${badgeClass(job.status)}">${fmtStatus(job.status)}</span>
            ${job.priority&&job.priority<=2 ? '<span class="badge progress">Priority</span>' : ''}
          </div>
          <div class="form-grid" style="gap:10px;margin-bottom:14px;">
            <div><div class="label">Customer</div><div style="margin-top:4px;">${escapeHtml(job.customer_name||'—')}</div></div>
            <div><div class="label">Dealer</div><div style="margin-top:4px;">${escapeHtml(job.dealer_name||'—')}</div></div>
            <div><div class="label">VIN</div><div class="mono" style="margin-top:4px;">${escapeHtml(job.vin||'—')}</div></div>
            <div><div class="label">Job Type</div><div style="margin-top:4px;">${escapeHtml(fmtStatus(job.job_type||'—'))}</div></div>
            <div><div class="label">Parts Status</div><div style="margin-top:4px;">${escapeHtml(partsLabel[job.parts_status]||job.parts_status||'—')}</div></div>
            <div><div class="label">Estimate</div><div style="margin-top:4px;">${estH} hrs</div></div>
            <div><div class="label">Approved Time</div><div style="margin-top:4px;">${minutesToHours(t.approved_minutes_total)} hrs</div></div>
            <div><div class="label">Pending Time</div><div style="margin-top:4px;">${minutesToHours(t.pending_minutes_total)} hrs</div></div>
          </div>
          <div style="border-top:1px solid rgba(0,0,0,0.08);padding-top:14px;margin-bottom:14px;">
            <div class="form-grid" style="gap:10px;">
              <div><div class="label">Bay</div><div style="margin-top:4px;font-weight:600;color:var(--sage);">${escapeHtml(job.work_center||'—')}</div></div>
              <div><div class="label">Assigned Tech</div><div style="margin-top:4px;">${escapeHtml(job.assigned_name||'—')}</div></div>
              <div><div class="label">Start</div><div style="margin-top:4px;">${escapeHtml(job.scheduled_start||'—')}</div></div>
              <div><div class="label">Finish</div><div style="margin-top:4px;">${escapeHtml(job.scheduled_finish||'—')}</div></div>
            </div>
          </div>
          ${job.notes ? `<div><div class="label" style="margin-bottom:4px;">Notes</div><div style="white-space:pre-wrap;font-size:14px;">${escapeHtml(job.notes)}</div></div>` : ''}
        </div>
        <div class="modal-footer">
          <button class="btn" id="modal-open-job">Open Full Detail</button>
          <button class="btn secondary" id="modal-close-btn">Close</button>
        </div>
      </div>`;
    document.body.appendChild(overlay);

    overlay.querySelector('.modal-close').onclick = closeModal;
    overlay.querySelector('#modal-close-btn').onclick = closeModal;
    overlay.onclick = e=>{ if(e.target===overlay) closeModal(); };
    overlay.querySelector('#modal-open-job').onclick = ()=>{
      closeModal();
      window.history.pushState({},'','/ops/job/'+job.job_id);
      router();
    };
  }

  // ── New entry modal ─────────────────────────────────
  function openNewEntryModal(prefillJobId){
    const prefill = prefillJobId ? allJobs.find(j=>j.job_id===prefillJobId) : null;

    // jobs eligible to schedule
    const eligible = allJobs.filter(j=>{
      const s=(j.status||'').toUpperCase();
      return s==='UNSCHEDULED'||s==='READY_FOR_SCHEDULING'||s==='SCHEDULED';
    });
    const jobSelectOpts = eligible.map(j=>{
      const label = (j.so_number||'#'+j.job_id)+' — '+(j.customer_name||j.dealer_name||'');
      const sel = prefill && prefill.job_id===j.job_id ? ' selected' : '';
      return `<option value="${j.job_id}"${sel}>${escapeHtml(label)}</option>`;
    }).join('');

    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.innerHTML = `
      <div class="modal">
        <div class="modal-header">
          <h2>New Schedule Entry</h2>
          <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-grid" style="gap:10px;">
            <div style="grid-column:1/-1;">
              <div class="label" style="margin-bottom:6px;">Job / SO#</div>
              <select class="input" id="ne-job"><option value="">Select a job…</option>${jobSelectOpts}</select>
              <div class="field-error" data-error-for="ne-job"></div>
            </div>
            <div>
              <div class="label" style="margin-bottom:6px;">Production Bay</div>
              <select class="input" id="ne-bay"><option value="">Select bay…</option>${bayOpts}</select>
            </div>
            <div>
              <div class="label" style="margin-bottom:6px;">Lead Technician</div>
              <select class="input" id="ne-tech"><option value="">Unassigned</option>${userOpts}</select>
            </div>
            <div>
              <div class="label" style="margin-bottom:6px;">Start Date</div>
              <input class="input" id="ne-start" type="date" />
            </div>
            <div>
              <div class="label" style="margin-bottom:6px;">Est. Completion</div>
              <input class="input" id="ne-finish" type="date" />
            </div>
          </div>
          ${prefill ? '' : `
          <div style="margin-top:12px;">
            <div class="label" style="margin-bottom:6px;">Notes</div>
            <textarea class="input" id="ne-notes" rows="2" placeholder="Optional scheduling notes…"></textarea>
          </div>`}
        </div>
        <div class="modal-footer">
          <button class="btn" id="ne-submit">Create Entry</button>
          <button class="btn secondary" id="ne-cancel">Cancel</button>
          <div class="field-error" data-error-for="ne-general" style="flex:1;"></div>
        </div>
      </div>`;
    document.body.appendChild(overlay);

    overlay.querySelector('.modal-close').onclick = closeModal;
    overlay.querySelector('#ne-cancel').onclick = closeModal;
    overlay.onclick = e=>{ if(e.target===overlay) closeModal(); };

    overlay.querySelector('#ne-submit').onclick = async ()=>{
      const jid   = parseInt(overlay.querySelector('#ne-job').value, 10);
      const bay   = overlay.querySelector('#ne-bay').value;
      const tech  = parseInt(overlay.querySelector('#ne-tech').value, 10) || 0;
      const start = overlay.querySelector('#ne-start').value;
      const fin   = overlay.querySelector('#ne-finish').value;
      const notes = overlay.querySelector('#ne-notes')?.value?.trim() || '';

      overlay.querySelectorAll('.field-error').forEach(e=>e.textContent='');
      if(!jid){ overlay.querySelector('[data-error-for="ne-job"]').textContent='Select a job.'; return; }

      const btn = overlay.querySelector('#ne-submit');
      btn.disabled = true; btn.textContent = 'Scheduling…';

      try {
        const payload = {};
        if(bay) payload.work_center = bay;
        if(start) payload.scheduled_start = start;
        if(fin) payload.scheduled_finish = fin;
        if(tech) payload.assigned_user_id = tech;
        if(notes) payload.note = notes;
        await api.schedule(jid, payload);

        // update local
        const job = allJobs.find(j=>j.job_id===jid);
        if(job){
          if(bay) job.work_center = bay;
          if(start) job.scheduled_start = start;
          if(fin) job.scheduled_finish = fin;
          if(tech){ job.assigned_user_id = tech; const u=users.find(x=>x.id===tech); if(u) job.assigned_name=u.name; }
          if(job.status==='UNSCHEDULED'||job.status==='READY_FOR_SCHEDULING') job.status='SCHEDULED';
        }
        closeModal();
        renderBoard();
      } catch(e){
        overlay.querySelector('[data-error-for="ne-general"]').textContent = e.message||'Failed';
        btn.disabled = false; btn.textContent = 'Create Entry';
      }
    };
  }

  renderBoard();
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
  const activeOrders = jobs.filter(j=>(j.status||'').toUpperCase() !== 'COMPLETE').length;
  const pendingCritical = jobs.filter(j=>!j.so_number || (j.status||'').toUpperCase() === 'QC_FAILED').length;
  const complete = by('COMPLETE');
  const convRate = jobs.length ? Math.round((complete / jobs.length) * 1000) / 10 : 0;
  const today = new Date().toLocaleDateString('en-US', {year:'numeric',month:'long',day:'numeric'});

  view(`
    <div class="card">
      <div class="row" style="align-items:center;">
        <div style="flex:1 1 280px;">
          <h2 style="margin:0;">Executive Performance Overview</h2>
          <div class="muted" style="margin-top:4px;">${today}</div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          ${kpi('Active Orders', activeOrders)}
          <div class="kpi" style="border-left:3px solid #c0392b;">
            <div class="kpi-label">Pending Critical</div>
            <div class="kpi-value" style="color:#c0392b;">${pendingCritical}</div>
          </div>
          <div class="kpi" style="border-left:3px solid #27ae60;">
            <div class="kpi-label">Completion Rate</div>
            <div class="kpi-value" style="color:#27ae60;">${convRate}%</div>
          </div>
          ${kpi('Complete', complete)}
        </div>
      </div>
    </div>

    <div class="card">
      <div class="row" style="margin-bottom:12px;align-items:center;">
        <span class="collapse-title">Recent Sales Orders</span>
        <button class="btn secondary small-btn" data-link href="/ops/jobs">View All</button>
      </div>
      <table class="table">
        <thead><tr>
          <th>Order ID</th><th>Client</th><th>Dealer</th><th>Due Date</th><th>Status</th><th></th>
        </tr></thead>
        <tbody>
          ${jobs.slice(0,15).map(j=>`
            <tr>
              <td><button class="btn-link" data-open-job="${j.job_id}">${escapeHtml(j.so_number||'#'+j.job_id)}</button></td>
              <td>${escapeHtml(j.customer_name||'—')}</td>
              <td>${escapeHtml(j.dealer_name||'—')}</td>
              <td style="font-size:12px;">${escapeHtml(j.due_date||'—')}</td>
              <td><span class="badge ${badgeClass(j.status)}">${fmtStatus(j.status)}</span></td>
              <td><button class="btn secondary small-btn" data-open-job="${j.job_id}">View</button></td>
            </tr>
          `).join('') || `<tr><td colspan="6" class="muted" style="padding:10px;">No jobs found.</td></tr>`}
        </tbody>
      </table>
    </div>
  `);

  bindJobsTable();
}

async function loadQC(){
  const [pendingResp, allResp] = await Promise.all([
    api.jobs('?limit=300&status=PENDING_QC'),
    api.jobs('?limit=300'),
  ]);
  const jobs      = pendingResp.jobs || [];
  const allJobs   = allResp.jobs || [];
  const today     = new Date().toISOString().slice(0, 10);
  const passedToday = allJobs.filter(j => j.status === 'COMPLETE' && (j.updated_at||'').startsWith(today)).length;
  const failedQC    = allJobs.filter(j => j.status === 'QC_FAILED' || (j.qc_failed_count > 0 && j.status !== 'COMPLETE')).length;

  const PAGE_SIZE = 10;
  let page = 0;

  function timeInQueue(ts) {
    if (!ts) return '—';
    const mins = Math.round((Date.now() - new Date(ts).getTime()) / 60000);
    if (mins < 60) return `${mins}m`;
    const h = Math.floor(mins / 60), m = mins % 60;
    return `${h}h ${m}m`;
  }
  function queueBadgeStyle(ts) {
    if (!ts) return '';
    const h = (Date.now() - new Date(ts).getTime()) / 3600000;
    if (h >= 4) return 'background:rgba(216,107,25,0.15);color:#b05b17;';
    if (h >= 2) return 'background:rgba(255,200,0,0.18);color:#7a6000;';
    return 'background:rgba(64,79,75,0.1);color:var(--sage);';
  }

  function renderTable(filtered) {
    const slice = filtered.slice(page * PAGE_SIZE, (page + 1) * PAGE_SIZE);
    const total = filtered.length;
    return `
      <tbody id="qc-tbody">
        ${slice.map(j=>`
          <tr>
            <td><button class="btn-link" data-open-job="${j.job_id}">${escapeHtml(j.so_number||'—')}</button></td>
            <td>${escapeHtml(j.customer_name||'—')}</td>
            <td class="mono">${escapeHtml((j.vin||'').slice(-8)||'—')}</td>
            <td><span style="padding:3px 8px;border-radius:4px;font-size:12px;font-weight:600;${queueBadgeStyle(j.updated_at)}">${timeInQueue(j.updated_at)}</span></td>
            <td>${escapeHtml(j.assigned_name||'—')}</td>
            <td><button class="btn small-btn" data-open-job="${j.job_id}">Inspect</button></td>
          </tr>
        `).join('') || `<tr><td colspan="6" class="muted" style="padding:12px;">No jobs pending QC.</td></tr>`}
      </tbody>
      <tfoot>
        <tr>
          <td colspan="6" style="padding:12px 0 0;font-size:12px;color:rgba(0,0,0,.5);">
            Showing ${Math.min((page+1)*PAGE_SIZE, total)} of ${total} pending jobs
            ${total > PAGE_SIZE ? `
              <span style="float:right;display:flex;gap:6px;">
                <button class="btn secondary small-btn" id="qc-prev" ${page===0?'disabled':''}>← Prev</button>
                <button class="btn secondary small-btn" id="qc-next" ${(page+1)*PAGE_SIZE>=total?'disabled':''}>Next →</button>
              </span>` : ''}
          </td>
        </tr>
      </tfoot>
    `;
  }

  view(`
    <div class="card">
      <div class="row" style="align-items:flex-start;">
        <div style="flex:1 1 280px;">
          <h2 style="margin:0;">QC Queue</h2>
          <div class="muted" style="margin-top:4px;">Automotive Upfit Quality Control Station</div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
          ${kpi('Pending Inspection', jobs.length)}
          <div class="kpi" style="border-left:3px solid #c0392b;">
            <div class="kpi-label">Failed QC</div>
            <div class="kpi-value" style="color:#c0392b;">${failedQC}</div>
          </div>
          <div class="kpi" style="border-left:3px solid #27ae60;">
            <div class="kpi-label">Passed Today</div>
            <div class="kpi-value" style="color:#27ae60;">${passedToday}</div>
          </div>
          <button class="btn" data-open-create style="margin-left:8px;">+ New Inspection</button>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="row" style="margin-bottom:12px;align-items:center;">
        <span class="collapse-title">Jobs Pending QC</span>
        <input class="input" id="qc-search" placeholder="Search SO# or VIN…" style="max-width:220px;" />
      </div>
      <table class="table" id="qc-table">
        <thead><tr>
          <th>SO Number</th><th>Customer</th><th>VIN (Last 8)</th><th>Time in Queue</th><th>Technician</th><th>Actions</th>
        </tr></thead>
        ${renderTable(jobs)}
      </table>
    </div>
  `);

  bindJobsTable();

  let filtered = [...jobs];

  function rebind() {
    const prev = $('#qc-prev'), next = $('#qc-next');
    if (prev) prev.onclick = () => { if (page > 0) { page--; refresh(); } };
    if (next) next.onclick = () => { if ((page+1)*PAGE_SIZE < filtered.length) { page++; refresh(); } };
  }

  function refresh() {
    const t = $('#qc-table');
    if (!t) return;
    const old = t.querySelector('tbody');
    const foot = t.querySelector('tfoot');
    const tmp = document.createElement('table');
    tmp.innerHTML = renderTable(filtered);
    if (old) t.replaceChild(tmp.querySelector('tbody'), old);
    const newFoot = tmp.querySelector('tfoot');
    if (foot && newFoot) t.replaceChild(newFoot, foot);
    else if (newFoot) t.appendChild(newFoot);
    rebind();
    bindJobsTable();
  }

  rebind();

  const search = $('#qc-search');
  if (search) {
    search.addEventListener('input', () => {
      const q = search.value.trim().toLowerCase();
      page = 0;
      filtered = q ? jobs.filter(j =>
        (j.so_number||'').toLowerCase().includes(q) ||
        (j.vin||'').toLowerCase().includes(q)
      ) : [...jobs];
      refresh();
    });
  }
}

async function loadAdmin() {
  const [usersResp, jobsResp, settingsResp] = await Promise.all([
    api.users(),
    api.jobs('?limit=500'),
    api.settings(),
  ]);
  const users = usersResp.users || [];
  const jobs  = jobsResp.jobs  || [];
  const byS   = (s) => jobs.filter(j => j.status === s).length;

  let dealers    = [...(settingsResp.dealers      || [])];
  let salesPeople= [...(settingsResp.sales_people || [])];
  let bays       = (settingsResp.bays || [
    {id:1, name:'Bay 1', active:true},
    {id:2, name:'Bay 2', active:true},
  ]).map(b => ({...b}));
  let nextBayId  = bays.reduce((m,b) => Math.max(m, b.id||0), 0) + 1;

  const notify = {
    email_qc:       settingsResp.notify_email_qc       !== false,
    email_complete: settingsResp.notify_email_complete  !== false,
    sms_qc:         settingsResp.notify_sms_qc          === true,
    sms_complete:   settingsResp.notify_sms_complete     === true,
  };

  function tagListHtml(id, items, placeholder) {
    const tags  = items.map(v => `<span class="tag-item">${escapeHtml(v)}<button class="tag-remove" data-value="${escapeHtml(v)}" title="Remove">&times;</button></span>`).join('');
    const empty = items.length === 0 ? `<span style="color:rgba(0,0,0,0.45);font-size:13px;">None added yet.</span>` : '';
    return `
      <div class="tag-list" id="${id}-list">${tags}${empty}</div>
      <div class="inline" style="margin-top:8px;">
        <input class="input" id="${id}-input" placeholder="${escapeHtml(placeholder)}" style="max-width:280px;" />
        <button class="btn secondary" id="${id}-add">Add</button>
      </div>`;
  }

  function deltaChip(pct, up) {
    const color = up ? '#27ae60' : '#c0392b';
    const arrow = up ? '▲' : '▼';
    return `<span style="font-size:10px;font-weight:700;color:${color};margin-left:4px;">${arrow} ${Math.abs(pct)}%</span>`;
  }
  const activeCount = jobs.filter(j=>j.status!=='COMPLETE').length;

  view(`
    <div class="card">
      <div class="row" style="align-items:center;flex-wrap:wrap;gap:10px;">
        <div style="flex:1 1 200px;">
          <h2 style="margin:0;">Admin Management</h2>
          <div class="muted" style="margin-top:4px;">Role management, system overview, and configuration.</div>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
          <div style="display:flex;background:rgba(0,0,0,0.06);border-radius:8px;padding:3px;gap:2px;">
            <button class="btn secondary small-btn" id="tab-overview" style="background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.1);">Overview</button>
            <button class="btn secondary small-btn" id="tab-reports" style="background:transparent;box-shadow:none;color:rgba(0,0,0,.5);">Reports</button>
            <button class="btn secondary small-btn" id="tab-archive" style="background:transparent;box-shadow:none;color:rgba(0,0,0,.5);">Archive</button>
          </div>
          <input class="input" id="admin-user-search" placeholder="Search users…" style="max-width:180px;" />
          <a class="btn secondary small-btn" href="/wp-admin/users.php">WP Admin ↗</a>
        </div>
      </div>
    </div>

    <div class="stat-grid">
      <div class="stat-tile">
        <div class="label">Active Jobs</div>
        <div class="value">${activeCount}${deltaChip(12, true)}</div>
      </div>
      <div class="stat-tile">
        <div class="label">In Progress</div>
        <div class="value">${byS('IN_PROGRESS')}${deltaChip(5, true)}</div>
      </div>
      <div class="stat-tile">
        <div class="label">Pending QC</div>
        <div class="value">${byS('PENDING_QC')}${deltaChip(2, false)}</div>
      </div>
      <div class="stat-tile">
        <div class="label">Unscheduled</div>
        <div class="value">${byS('UNSCHEDULED')}${deltaChip(1, true)}</div>
      </div>
      <div class="stat-tile">
        <div class="label">Users</div>
        <div class="value">${users.length}${deltaChip(4, false)}</div>
      </div>
    </div>

    <div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
        <span class="collapse-title">Users &amp; Ops Roles</span>
        <div style="display:flex;gap:6px;">
          <button class="btn secondary small-btn" id="admin-filter-btn">Filter</button>
          <button class="btn secondary small-btn" id="admin-export-btn">Export</button>
        </div>
      </div>
      <table class="table" id="admin-users-table">
        <thead>
          <tr><th>User Name</th><th>Role</th><th>Email</th><th>Status</th><th>Last Active</th><th></th></tr>
        </thead>
        <tbody>
          ${users.map(u => {
            const roleColor = {admin:'var(--arches)',supervisor:'var(--sage)',cs:'#2980b9',tech:'#7f8c8d'}[u.ops_role] || 'rgba(0,0,0,.35)';
            const roleLabel = {admin:'Admin',supervisor:'Supervisor',cs:'CS',tech:'Tech'}[u.ops_role] || 'No Role';
            return `
            <tr data-user-name="${escapeHtml((u.name||'').toLowerCase())}" data-user-email="${escapeHtml((u.email||'').toLowerCase())}">
              <td>
                <div style="font-weight:600;">${escapeHtml(u.name)}</div>
                <div style="font-size:11px;color:rgba(0,0,0,.45);">${escapeHtml(u.email)}</div>
              </td>
              <td>
                <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:${roleColor}22;color:${roleColor};">${roleLabel}</span>
              </td>
              <td>
                <select class="input role-select" style="padding:5px 8px;font-size:12px;width:auto;min-width:110px;" data-uid="${u.id}">
                  <option value=""          ${u.ops_role===''         ?'selected':''}>No Role</option>
                  <option value="tech"       ${u.ops_role==='tech'       ?'selected':''}>Tech</option>
                  <option value="cs"         ${u.ops_role==='cs'         ?'selected':''}>CS</option>
                  <option value="supervisor" ${u.ops_role==='supervisor' ?'selected':''}>Supervisor</option>
                  <option value="admin"      ${u.ops_role==='admin'      ?'selected':''}>Admin</option>
                </select>
              </td>
              <td>
                <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;color:#27ae60;">
                  <span style="width:7px;height:7px;border-radius:50%;background:#27ae60;display:inline-block;"></span>Active
                </span>
              </td>
              <td style="font-size:12px;color:rgba(0,0,0,.5);">${escapeHtml(u.last_active||'—')}</td>
              <td><span class="role-save-status" data-uid="${u.id}" style="font-size:11px;color:rgba(0,0,0,.4);white-space:nowrap;"></span></td>
            </tr>`;
          }).join('')}
        </tbody>
      </table>
    </div>

    <div class="card">
      <h2>Settings</h2>
      <div class="row" style="margin-bottom:14px;">
        <div style="flex:1 1 160px;">
          <div class="label" style="margin-bottom:6px;">Shift Start</div>
          <input class="input" id="shift_start" value="${settingsResp.shift_start || '07:00:00'}" />
        </div>
        <div style="flex:1 1 160px;">
          <div class="label" style="margin-bottom:6px;">Shift End</div>
          <input class="input" id="shift_end" value="${settingsResp.shift_end || '15:30:00'}" />
        </div>
        <div style="flex:1 1 160px;">
          <div class="label" style="margin-bottom:6px;">Lunch Minutes</div>
          <input class="input" id="lunch_minutes" value="${settingsResp.lunch_minutes || 30}" />
        </div>
        <div style="flex:1 1 160px;">
          <div class="label" style="margin-bottom:6px;">Break Minutes</div>
          <input class="input" id="break_minutes" value="${settingsResp.break_minutes || 20}" />
        </div>
      </div>
      <div style="margin-bottom:14px;">
        <div class="label" style="margin-bottom:6px;">Dealers</div>
        ${tagListHtml('dealers', dealers, 'Dealer name…')}
      </div>
      <div style="margin-bottom:14px;">
        <div class="label" style="margin-bottom:6px;">Sales People</div>
        ${tagListHtml('sales_people', salesPeople, 'Sales person name…')}
      </div>
      <button class="btn" id="save_settings">Save Settings</button>
    </div>

    <div class="card">
      <h2>Production Bays</h2>
      <div id="bay-list"></div>
      <button class="btn secondary bay-add-btn" id="bay-add-btn">+ Add New Bay</button>
      <div id="bay-new-row" style="display:none;margin-top:12px;">
        <div class="inline" style="flex-wrap:wrap;">
          <input class="input" id="bay-new-name" placeholder="Bay name…" style="max-width:200px;" />
          <select class="input" id="bay-new-status" style="width:auto;max-width:130px;">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
          <button class="btn small-btn" id="bay-new-save">Add Bay</button>
          <button class="btn secondary small-btn" id="bay-new-cancel">Cancel</button>
        </div>
      </div>
    </div>

    <div class="card">
      <h2>Notification Preferences</h2>
      <div style="margin-bottom:20px;">
        <div class="notify-group-label">Email Alerts</div>
        <div class="notify-row">
          <div>
            <div class="notify-title">QC Failures</div>
            <div class="notify-desc">Send an email when a job fails QC review</div>
          </div>
          <label class="toggle">
            <input type="checkbox" id="notify-email-qc" ${notify.email_qc ? 'checked' : ''}>
            <span class="toggle-track">
              <span class="toggle-off">OFF</span>
              <span class="toggle-on">ON</span>
              <span class="toggle-thumb"></span>
            </span>
          </label>
        </div>
        <div class="notify-row">
          <div>
            <div class="notify-title">Job Completion</div>
            <div class="notify-desc">Send an email when a job is marked complete</div>
          </div>
          <label class="toggle">
            <input type="checkbox" id="notify-email-complete" ${notify.email_complete ? 'checked' : ''}>
            <span class="toggle-track">
              <span class="toggle-off">OFF</span>
              <span class="toggle-on">ON</span>
              <span class="toggle-thumb"></span>
            </span>
          </label>
        </div>
      </div>
      <div>
        <div class="notify-group-label">SMS Alerts</div>
        <div class="notify-row">
          <div>
            <div class="notify-title">QC Failures</div>
            <div class="notify-desc">Send a text message when a job fails QC review</div>
          </div>
          <label class="toggle">
            <input type="checkbox" id="notify-sms-qc" ${notify.sms_qc ? 'checked' : ''}>
            <span class="toggle-track">
              <span class="toggle-off">OFF</span>
              <span class="toggle-on">ON</span>
              <span class="toggle-thumb"></span>
            </span>
          </label>
        </div>
        <div class="notify-row">
          <div>
            <div class="notify-title">Job Completion</div>
            <div class="notify-desc">Send a text message when a job is marked complete</div>
          </div>
          <label class="toggle">
            <input type="checkbox" id="notify-sms-complete" ${notify.sms_complete ? 'checked' : ''}>
            <span class="toggle-track">
              <span class="toggle-off">OFF</span>
              <span class="toggle-on">ON</span>
              <span class="toggle-thumb"></span>
            </span>
          </label>
        </div>
      </div>
      <button class="btn" id="save-notify" style="margin-top:16px;">Save Notification Preferences</button>
    </div>

    <div class="card">
      <div class="collapse-title" style="margin-bottom:10px;">Quick Links</div>
      <div class="row">
        <a class="btn secondary" href="/wp-admin/users.php">WP Users</a>
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

  // Settings bindings
  function renderTagList(id, arr) {
    const tags  = arr.map(v => `<span class="tag-item">${escapeHtml(v)}<button class="tag-remove" data-value="${escapeHtml(v)}" title="Remove">&times;</button></span>`).join('');
    const empty = arr.length === 0 ? `<span style="color:rgba(0,0,0,0.45);font-size:13px;">None added yet.</span>` : '';
    $(`#${id}-list`).innerHTML = tags + empty;
    bindTagRemove(id);
  }
  function bindTagRemove(id) {
    $$(`#${id}-list .tag-remove`).forEach(btn => {
      btn.onclick = () => {
        const val = btn.getAttribute('data-value');
        if (id === 'dealers') { dealers = dealers.filter(d => d !== val); renderTagList('dealers', dealers); }
        else { salesPeople = salesPeople.filter(p => p !== val); renderTagList('sales_people', salesPeople); }
      };
    });
  }
  function bindTagAdd(id, getArr, addFn) {
    const input = $(`#${id}-input`);
    const btn   = $(`#${id}-add`);
    const doAdd = () => {
      const val = input.value.trim();
      if (!val) return;
      addFn(val);
      input.value = '';
      renderTagList(id, getArr());
      input.focus();
    };
    btn.onclick = doAdd;
    input.onkeydown = (e) => { if (e.key === 'Enter') { e.preventDefault(); doAdd(); } };
  }

  bindTagRemove('dealers');
  bindTagRemove('sales_people');
  bindTagAdd('dealers',     () => dealers,     (v) => { if (!dealers.includes(v))     dealers.push(v); });
  bindTagAdd('sales_people',() => salesPeople, (v) => { if (!salesPeople.includes(v)) salesPeople.push(v); });

  $('#save_settings').onclick = async () => {
    try {
      await api.updateSettings({
        shift_start:    $('#shift_start').value.trim(),
        shift_end:      $('#shift_end').value.trim(),
        lunch_minutes:  parseInt($('#lunch_minutes').value, 10),
        break_minutes:  parseInt($('#break_minutes').value, 10),
        dealers,
        sales_people: salesPeople,
        bays,
      });
      alert('Settings saved.');
    } catch(e) {
      alert(e.message);
    }
  };

  // ── Bay configuration ──────────────────────────────────────
  function bayRowHtml(b) {
    return `
      <div class="bay-row" data-bay-id="${b.id}">
        <div class="bay-row-view">
          <span class="bay-row-name">${escapeHtml(b.name)}</span>
          <span class="badge ${b.active ? 'complete' : 'scheduled'}">${b.active ? 'Active' : 'Inactive'}</span>
          <button class="btn secondary small-btn bay-edit" data-id="${b.id}">Edit</button>
        </div>
        <div class="bay-row-edit" style="display:none;">
          <input class="input bay-name-input" value="${escapeHtml(b.name)}" style="max-width:200px;" />
          <select class="input bay-status-select" style="width:auto;max-width:130px;">
            <option value="1" ${b.active ? 'selected' : ''}>Active</option>
            <option value="0" ${!b.active ? 'selected' : ''}>Inactive</option>
          </select>
          <button class="btn small-btn bay-save" data-id="${b.id}">Save</button>
          <button class="btn secondary small-btn bay-cancel" data-id="${b.id}">Cancel</button>
          <button class="btn danger small-btn bay-delete" data-id="${b.id}">Remove</button>
        </div>
      </div>`;
  }

  function renderBayList() {
    $('#bay-list').innerHTML = bays.length
      ? bays.map(bayRowHtml).join('')
      : `<div class="muted" style="padding:8px 0;">No bays configured yet.</div>`;
    bindBayEvents();
  }

  function bindBayEvents() {
    $$('.bay-edit').forEach(btn => {
      btn.onclick = () => {
        const row = btn.closest('.bay-row');
        row.querySelector('.bay-row-view').style.display = 'none';
        row.querySelector('.bay-row-edit').style.display = 'flex';
        row.querySelector('.bay-name-input').focus();
      };
    });
    $$('.bay-cancel').forEach(btn => {
      btn.onclick = () => {
        const row = btn.closest('.bay-row');
        row.querySelector('.bay-row-view').style.display = 'flex';
        row.querySelector('.bay-row-edit').style.display = 'none';
      };
    });
    $$('.bay-save').forEach(btn => {
      btn.onclick = () => {
        const id   = parseInt(btn.getAttribute('data-id'), 10);
        const row  = btn.closest('.bay-row');
        const name = row.querySelector('.bay-name-input').value.trim();
        const active = row.querySelector('.bay-status-select').value === '1';
        if (!name) return;
        const bay = bays.find(b => b.id === id);
        if (bay) { bay.name = name; bay.active = active; }
        renderBayList();
      };
    });
    $$('.bay-delete').forEach(btn => {
      btn.onclick = () => {
        const id = parseInt(btn.getAttribute('data-id'), 10);
        if (!confirm('Remove this bay?')) return;
        bays = bays.filter(b => b.id !== id);
        renderBayList();
      };
    });
  }

  renderBayList();

  $('#bay-add-btn').onclick = () => {
    $('#bay-new-row').style.display = '';
    $('#bay-new-name').value = '';
    $('#bay-new-name').focus();
  };
  $('#bay-new-cancel').onclick = () => { $('#bay-new-row').style.display = 'none'; };
  $('#bay-new-save').onclick = () => {
    const name   = $('#bay-new-name').value.trim();
    const active = $('#bay-new-status').value === '1';
    if (!name) return;
    bays.push({ id: nextBayId++, name, active });
    $('#bay-new-row').style.display = 'none';
    renderBayList();
  };
  $('#bay-new-name').onkeydown = (e) => { if (e.key === 'Enter') { e.preventDefault(); $('#bay-new-save').click(); } };

  // ── Notification preferences ───────────────────────────────
  $('#save-notify').onclick = async () => {
    try {
      await api.updateSettings({
        notify_email_qc:       $('#notify-email-qc').checked,
        notify_email_complete: $('#notify-email-complete').checked,
        notify_sms_qc:         $('#notify-sms-qc').checked,
        notify_sms_complete:   $('#notify-sms-complete').checked,
      });
      alert('Notification preferences saved.');
    } catch(e) {
      alert(e.message);
    }
  };

  linkify();

  const adminSearch = $('#admin-user-search');
  if (adminSearch) {
    adminSearch.addEventListener('input', () => {
      const q = adminSearch.value.trim().toLowerCase();
      $$('#admin-users-table tbody tr').forEach(tr => {
        const name = tr.getAttribute('data-user-name') || '';
        const email = tr.getAttribute('data-user-email') || '';
        tr.style.display = (!q || name.includes(q) || email.includes(q)) ? '' : 'none';
      });
    });
  }

  const exportUsersBtn = $('#admin-export-btn');
  if (exportUsersBtn) {
    exportUsersBtn.onclick = () => {
      const rows = [['Name','Email','Role']];
      users.forEach(u => rows.push([u.name||'', u.email||'', u.ops_role||'']));
      const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n');
      const a = document.createElement('a');
      a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
      a.download = 'users.csv';
      a.click();
    };
  }
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
      if (r.startsWith('/settings')) {
        // Admins manage settings inside the Admin view
        const c = slateOpsSettings.user.caps || {};
        if (c.admin) { window.history.replaceState({}, '', '/ops/admin'); state.route = '/admin'; return await render(); }
        return await loadSettings();
      }

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
