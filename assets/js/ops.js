(function(){
  const $ = (sel, root=document) => root.querySelector(sel);
  const $$ = (sel, root=document) => Array.from(root.querySelectorAll(sel));

  // Minimal escaping helper for attribute values.
  // Keep this local to avoid name collisions with other helpers in ops.js.
  const escapeAttr = (val) => {
    const s = String(val ?? '');
    return s
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  };

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
    // generic PATCH helper (CS uses this for SO# updates and intake edits)
    updateJob(id, payload){ return this.req('/jobs/' + id, {method:'PATCH', body: JSON.stringify(payload)}); },
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
    updateUserRole(id, role){ return this.req('/users/' + id + '/role', {method:'POST', body: JSON.stringify({role})}); },
    getActivity(id){ return this.req('/jobs/' + id + '/activity'); },
    submitQC(id, notes){ return this.req('/jobs/' + id + '/qc/submit', {method:'POST', body: JSON.stringify({notes})}); },
    reviewQC(id, decision, notes){ return this.req('/jobs/' + id + '/qc/review', {method:'POST', body: JSON.stringify({decision, notes})}); }
  };

  const state = {
    route: '/',
    jobs: [],
    settings: null,
    active: null,
    timerInterval: null,
  };

  // View mode helpers (phone / desktop toggle, persisted in localStorage)
  function getViewMode() { return localStorage.getItem('slateOpsViewMode') || 'desktop'; }
  function setViewMode(mode) { localStorage.setItem('slateOpsViewMode', mode); router(); }
  function viewModeToggle() {
    const isPhone = getViewMode() === 'phone';
    return `<div style="display:flex;align-items:center;gap:8px;">
      <span class="muted" style="font-size:12px;font-weight:600;">Desktop</span>
      <label class="toggle"><input type="checkbox" id="view-mode-toggle" ${isPhone ? 'checked' : ''} />
        <span class="toggle-track"><span class="toggle-off"></span><span class="toggle-on"></span><span class="toggle-thumb"></span></span>
      </label>
      <span class="muted" style="font-size:12px;font-weight:600;">Phone</span>
    </div>`;
  }
  function bindViewModeToggle() {
    const el = document.getElementById('view-mode-toggle');
    if (el) el.onchange = () => setViewMode(el.checked ? 'phone' : 'desktop');
  }

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

  function toast(msg, isError=false){
    const t = document.createElement('div');
    t.textContent = msg;
    t.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:99999;padding:10px 16px;border-radius:8px;font-size:14px;font-weight:600;color:#fff;box-shadow:0 4px 12px rgba(0,0,0,0.2);transition:opacity .3s;background:' + (isError ? '#dc2626' : '#16a34a');
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 3000);
  }

  function router(){
    const path = window.location.pathname.replace(/^\/ops/, '') || '/';
    state.route = path === '' ? '/' : path;
    render();
  }

  // Programmatic navigation helper (older views still call route('/ops/...')).
  function route(path){
    const raw = String(path || '');
    const p = raw.startsWith('/ops') ? raw : ('/ops' + (raw.startsWith('/') ? raw : '/' + raw));
    window.history.pushState({}, '', p);
    router();
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
      pendingIntake: state.jobs.filter(j => j.status === 'PENDING_INTAKE').length,
      needsSo: state.jobs.filter(j => !j.so_number).length,
      inProgress: state.jobs.filter(j => j.status === 'IN_PROGRESS').length,
      pendingQc: state.jobs.filter(j => j.status === 'PENDING_QC').length,
    };

    view(`
      <div class="card">
        <h2>Dashboard</h2>
        <div class="row">
          <div class="kpi"><div class="label">Pending Intake</div><div class="value">${counts.pendingIntake}</div></div>
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
    const [job, settingsResp, usersResp, activityResp] = await Promise.all([
      api.job(id),
      api.settings(),
      api.users().catch(() => ({users:[]})),
      api.getActivity(id).catch(() => ({activity:[]})),
    ]);

    const caps = slateOpsSettings.user.caps || {};
    const isSupervisor = !!caps.supervisor || !!caps.admin;
    const isCS = !!caps.cs || !!caps.admin;
    const canEdit = isSupervisor || isCS || isAdmin;

    const t = job.time || {approved_minutes_total:0, pending_minutes_total:0, by_tech:[]};
    const estHrs = job.estimated_minutes ? (job.estimated_minutes / 60) : 0;
    const actHrs = t.approved_minutes_total / 60;
    const varHrs = actHrs - estHrs;
    const notesLog = job.notes_log || [];
    const actLog = activityResp.activity || [];
    const partsHold    = job.status === 'ON_HOLD' && job.delay_reason === 'parts';
    const approvalHold = job.status === 'ON_HOLD' && job.delay_reason === 'approval';
    const hasHold      = partsHold || approvalHold;

    const partsLabel = {'NOT_READY':'Not Ready','PARTIAL':'Partial','READY':'Ready','HOLD':'Hold'};
    const jobTypeLabel = {
      'UPFIT':'Upfit','COMMERCIAL_UPFIT':'Commercial Upfit','COMMERCIAL_BUILD':'Commercial Build',
      'RV_BUILD':'RV Build','RV_UPFIT':'RV Upfit','PARTS_ONLY':'Parts Only','SERVICE':'Service','WARRANTY':'Warranty',
    };

    view(`
      <!-- Job header (always visible) -->
      <div class="card job-detail-header">
        <div class="row" style="align-items:flex-start;margin-bottom:14px;">
          <div style="flex:1;">
            <div class="job-detail-so">${escapeHtml(job.so_number || 'No SO#')}</div>
            <div class="muted">${escapeHtml(job.customer_name || '')}${job.dealer_name ? ' &middot; ' + escapeHtml(job.dealer_name) : ''}</div>
          </div>
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            ${hasHold?`<span class="badge badge-hold">${partsHold?'Parts Hold':'Approval Hold'}</span>`:''}
            <span class="badge ${badgeClass(job.status)}">${fmtStatus(job.status)}</span>
          </div>
        </div>
        <div class="job-detail-kpis">
          <div class="kpi"><div class="label">Estimate</div><div class="value">${estHrs ? estHrs.toFixed(1) : '—'} hrs</div></div>
          <div class="kpi"><div class="label">Actual</div><div class="value">${minutesToHours(t.approved_minutes_total)} hrs</div></div>
          <div class="kpi"><div class="label">Pending</div><div class="value">${minutesToHours(t.pending_minutes_total)} hrs</div></div>
          <div class="kpi"><div class="label">Variance</div><div class="value${estHrs&&varHrs>0?' kpi-over':''}">${estHrs ? (Math.round(varHrs*10)/10).toFixed(1) : '—'} hrs</div></div>
        </div>
      </div>

      <!-- Tab navigation -->
      <div class="job-tabs">
        <button class="job-tab active" data-tab="summary">Summary</button>
        <button class="job-tab" data-tab="time">Time</button>
        <button class="job-tab" data-tab="blockers">Blockers${hasHold?' <span class="job-tab-badge">!</span>':''}</button>
        <button class="job-tab" data-tab="activity">Activity${actLog.length?' <span class="job-tab-badge">'+actLog.length+'</span>':''}</button>
      </div>

      <!-- Tab: Summary -->
      <div class="job-tab-panel" id="tab-summary">
        <div class="card">
          <div class="form-grid" style="margin-bottom:14px;">
            <div><div class="label">VIN</div><div class="mono" style="margin-top:4px;">${escapeHtml(job.vin || '—')}</div></div>
            <div><div class="label">Job Type</div><div style="margin-top:4px;">${escapeHtml(jobTypeLabel[job.job_type] || job.job_type || '—')}</div></div>
            <div><div class="label">Parts Status</div><div style="margin-top:4px;">${escapeHtml(partsLabel[job.parts_status] || job.parts_status || '—')}</div></div>
            <div><div class="label">Promised Date</div><div style="margin-top:4px;">${escapeHtml(job.requested_date || '—')}</div></div>
            <div><div class="label">Assigned Tech</div><div style="margin-top:4px;">${escapeHtml(job.assigned_name || '—')}</div></div>
            <div><div class="label">Scheduled</div><div style="margin-top:4px;">${escapeHtml((job.scheduled_start||'—').split(' ')[0])} &rarr; ${escapeHtml((job.scheduled_finish||'—').split(' ')[0])}</div></div>
          </div>
          ${job.notes ? `<div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border,#e0e0e0);"><div class="label" style="margin-bottom:4px;">Notes</div><div style="white-space:pre-wrap;">${escapeHtml(job.notes)}</div></div>` : ''}
          <div class="row" style="margin-top:16px;flex-wrap:wrap;gap:8px;">
            ${isCS ? `<button class="btn secondary" id="so-btn">Set SO#</button>` : ``}
            ${job.status === 'IN_PROGRESS' ? `<button class="btn" id="submit-qc-btn">Submit for QC</button>` : ''}
            ${job.status === 'PENDING_QC' && isSupervisor ? `<button class="btn" id="qc-pass-btn">QC Pass</button><button class="btn secondary" id="qc-fail-btn">QC Fail</button>` : ''}
            ${canEdit ? `<button class="btn secondary" id="edit-btn">Edit Job</button>` : ``}
          </div>
        </div>
        ${canEdit ? `<div class="card" id="edit-panel" style="display:none;"><div id="edit-form-content"></div></div>` : ''}
        <div class="card">
          <h2>Notes</h2>
          ${notesLog.length === 0 ? `<div class="muted">No notes yet.</div>` : `<div style="display:flex;flex-direction:column;gap:8px;">${notesLog.map(n => `<div class="note-bubble"><div class="meta"><span class="author">${escapeHtml(n.user_name || '')}</span><span>${escapeHtml(n.created_at || '')}</span></div><div class="body">${escapeHtml(n.note || '')}</div></div>`).join('')}</div>`}
          ${canEdit ? `<div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border,#e0e0e0);"><div class="label" style="margin-bottom:6px;">Add Note</div><textarea class="input" id="new-note" rows="3" placeholder="Internal note…"></textarea><div style="margin-top:8px;display:flex;align-items:center;gap:10px;"><button class="btn secondary" id="add-note-btn">Add Note</button><div class="field-error" data-error-for="note" style="flex:1;"></div></div></div>` : ``}
        </div>
      </div>

      <!-- Tab: Time -->
      <div class="job-tab-panel" id="tab-time" style="display:none;">
        <div class="card">
          <h2 style="margin-top:0;">Time Tracking</h2>
          <div class="row" style="margin-bottom:16px;flex-wrap:wrap;gap:8px;">
            <button class="btn" id="start-btn">▶ Start Timer</button>
            <button class="btn secondary" id="stop-btn">■ Stop Timer</button>
            <button class="btn secondary" id="fix-btn">Fix Time Entry</button>
          </div>
          <table class="table"><thead><tr><th>Tech</th><th>Approved</th><th>Pending</th><th>Segments</th><th>Last Activity</th></tr></thead>
            <tbody>${(t.by_tech||[]).map(r=>`<tr><td>${escapeHtml(r.user_name||'')} </td><td>${minutesToHours(r.approved_minutes)} hrs</td><td>${minutesToHours(r.pending_minutes)} hrs</td><td>${r.segment_count}</td><td style="font-size:12px;">${r.last_activity||''}</td></tr>`).join('')||`<tr><td colspan="5" class="muted" style="padding:10px 0;">No time logged yet.</td></tr>`}</tbody>
          </table>
        </div>
      </div>

      <!-- Tab: Blockers -->
      <div class="job-tab-panel" id="tab-blockers" style="display:none;">
        <div class="card">
          <h2 style="margin-top:0;">Blockers</h2>
          ${canEdit ? `
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
              <button class="btn${partsHold ? ' btn-hold-active' : ' secondary'}" id="parts-hold-btn">${partsHold ? '● ' : ''}Parts Hold</button>
              <button class="btn${approvalHold ? ' btn-hold-active' : ' secondary'}" id="approval-hold-btn">${approvalHold ? '● ' : ''}Approval Hold</button>
              ${hasHold ? `<button class="btn secondary" id="clear-hold-btn">Clear Hold</button>` : ''}
            </div>
            <div id="blocker-note-area"${hasHold ? '' : ' style="display:none;"'}>
              <div class="label" style="margin-bottom:6px;">Hold Note</div>
              <textarea class="input" id="blocker-note" rows="2" placeholder="Reason for hold…">${escapeHtml(job.status_detail||'')} </textarea>
              <div style="margin-top:8px;display:flex;align-items:center;gap:10px;">
                <button class="btn" id="save-hold-note">Save Note</button>
                <div class="field-error" data-error-for="hold-note" style="flex:1;"></div>
              </div>
            </div>
          ` : `${hasHold?`<div class="dash-alert dash-alert-warn">${partsHold?'⚠ Parts Hold active':'⚠ Approval Hold active'}</div>`:`<div class="muted">No blockers active.</div>`}`}
        </div>
      </div>

      <!-- Tab: Activity -->
      <div class="job-tab-panel" id="tab-activity" style="display:none;">
        <div class="card">
          <h2 style="margin-top:0;">Activity Log</h2>
          ${actLog.length===0 ? '<div class="muted">No activity recorded yet.</div>' : `<div style="overflow-x:auto;"><table class="table" style="width:100%;font-size:13px;"><thead><tr><th>When</th><th>Who</th><th>Action</th><th>Detail</th></tr></thead><tbody>${actLog.map(a=>`<tr><td style="white-space:nowrap;font-size:11px;color:rgba(0,0,0,0.45);">${escapeHtml(a.created_at||'')} </td><td style="font-weight:600;">${escapeHtml(a.user_name||'System')}</td><td><span class="badge scheduled" style="font-size:11px;">${escapeHtml(a.action||'')} </span></td><td style="font-size:12px;">${a.field_name?'<strong>'+escapeHtml(a.field_name)+'</strong>: ':''} ${a.old_value?escapeHtml(String(a.old_value))+' \u2192 ':''} ${escapeHtml(String(a.new_value||a.note||''))}</td></tr>`).join('')}</tbody></table></div>`}
        </div>
      </div>
    `);

    // Tab switching
    $$('.job-tab').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const tabName = btn.getAttribute('data-tab');
        $$('.job-tab').forEach(b=>b.classList.remove('active'));
        btn.classList.add('active');
        $$('.job-tab-panel').forEach(p=>p.style.display='none');
        const panel = document.getElementById('tab-'+tabName);
        if(panel) panel.style.display='';
      });
    });

    // If there is a hold, auto-open the Blockers tab
    if(hasHold){
      const bTab = document.querySelector('.job-tab[data-tab="blockers"]');
      if(bTab) bTab.click();
    }

    const startBtn = document.getElementById('start-btn');
    if(startBtn) startBtn.onclick = async () => {
      try{
        let reason = null, note = '';
        const assigned = parseInt(job.assigned_user_id || 0, 10);
        const me = slateOpsSettings.user.id;
        if (assigned && assigned !== me){
          const picked = promptReason();
          if(!picked) return;
          reason = picked.reason; note = picked.note;
        }
        await api.timeStart(job.job_id, reason, note);
        window.history.replaceState({}, '', '/ops/job/' + job.job_id);
        router();
      }catch(e){ alert(e.message); }
    };

    const stopBtn = document.getElementById('stop-btn');
    if(stopBtn) stopBtn.onclick = async ()=>{ try{ await api.timeStop(); router(); }catch(e){ alert(e.message); } };

    const fixBtn = document.getElementById('fix-btn');
    if(fixBtn) fixBtn.onclick = async ()=>{
      const start = prompt('Start (YYYY-MM-DD HH:MM:SS)', ''); if(!start) return;
      const end   = prompt('End (YYYY-MM-DD HH:MM:SS)', '');   if(!end) return;
      const note  = prompt('Reason (required)', '');            if(!note) return;
      try{ await api.correction({job_id:job.job_id,start_ts:start,end_ts:end,note}); alert('Submitted for supervisor review.'); router(); }catch(e){ alert(e.message); }
    };

    const soBtn = document.getElementById('so-btn');
    if(soBtn) soBtn.onclick = async ()=>{
      const so = prompt('Enter SO# (S-ORD######)', job.so_number || ''); if(!so) return;
      try{ await api.setSO(job.job_id, so); router(); }catch(e){ alert(e.message); }
    };

    const submitQcBtn = document.getElementById('submit-qc-btn');
    if(submitQcBtn) submitQcBtn.onclick = async ()=>{
      const notes = prompt('Describe work completed (required):');
      if(!notes || !notes.trim()) return;
      submitQcBtn.disabled = true; submitQcBtn.textContent = 'Submitting…';
      try{ await api.submitQC(job.job_id, notes.trim()); toast('Submitted for QC'); router(); }
      catch(e){ alert(e.message); submitQcBtn.disabled = false; submitQcBtn.textContent = 'Submit for QC'; }
    };

    const qcPassBtn = document.getElementById('qc-pass-btn');
    if(qcPassBtn) qcPassBtn.onclick = async ()=>{
      if(!confirm('QC Pass — mark job Complete?')) return;
      qcPassBtn.disabled = true; qcPassBtn.textContent = '…';
      try{ await api.reviewQC(job.job_id, 'PASS', ''); toast('QC Passed'); router(); }
      catch(e){ alert(e.message); qcPassBtn.disabled = false; qcPassBtn.textContent = 'QC Pass'; }
    };

    const qcFailBtn = document.getElementById('qc-fail-btn');
    if(qcFailBtn) qcFailBtn.onclick = async ()=>{
      const notes = prompt('QC failure reason (required):');
      if(!notes || !notes.trim()) return;
      qcFailBtn.disabled = true; qcFailBtn.textContent = '…';
      try{ await api.reviewQC(job.job_id, 'FAIL', notes.trim()); toast('QC Failed — returned to tech'); router(); }
      catch(e){ alert(e.message); qcFailBtn.disabled = false; qcFailBtn.textContent = 'QC Fail'; }
    };

    const editBtn = document.getElementById('edit-btn');
    if(editBtn) editBtn.onclick = ()=>{
      const panel = document.getElementById('edit-panel');
      if(!panel) return;
      if(panel.style.display==='none'){ panel.style.display=''; renderEditForm(document.getElementById('edit-form-content'), job, isSupervisor, settingsResp, usersResp.users||[]); panel.scrollIntoView({behavior:'smooth',block:'start'}); }
      else { panel.style.display='none'; }
    };

    const addNoteBtn = document.getElementById('add-note-btn');
    if(addNoteBtn) addNoteBtn.onclick = async ()=>{
      const noteText = document.getElementById('new-note').value.trim();
      const errEl = document.querySelector('[data-error-for="note"]');
      if(errEl) errEl.textContent='';
      if(!noteText){ if(errEl) errEl.textContent='Note cannot be empty.'; return; }
      addNoteBtn.disabled=true; addNoteBtn.textContent='Adding…';
      try{ await api.addNote(job.job_id, noteText); router(); }
      catch(e){ if(errEl) errEl.textContent=e.message; addNoteBtn.disabled=false; addNoteBtn.textContent='Add Note'; }
    };

    const partsHoldBtn = document.getElementById('parts-hold-btn');
    if(partsHoldBtn) partsHoldBtn.onclick = async ()=>{
      if(partsHold){ document.getElementById('blocker-note-area').style.display=''; return; }
      try{ await api.editJob(job.job_id,{status:'ON_HOLD',delay_reason:'parts'}); router(); }catch(e){ alert(e.message); }
    };

    const apprHoldBtn = document.getElementById('approval-hold-btn');
    if(apprHoldBtn) apprHoldBtn.onclick = async ()=>{
      if(approvalHold){ document.getElementById('blocker-note-area').style.display=''; return; }
      try{ await api.editJob(job.job_id,{status:'ON_HOLD',delay_reason:'approval'}); router(); }catch(e){ alert(e.message); }
    };

    const clearHoldBtn = document.getElementById('clear-hold-btn');
    if(clearHoldBtn) clearHoldBtn.onclick = async ()=>{
      try{ await api.editJob(job.job_id,{status:'SCHEDULED',delay_reason:''}); router(); }catch(e){ alert(e.message); }
    };

    const saveHoldNote = document.getElementById('save-hold-note');
    if(saveHoldNote) saveHoldNote.onclick = async ()=>{
      const note = document.getElementById('blocker-note').value.trim();
      const errEl = document.querySelector('[data-error-for="hold-note"]');
      if(errEl) errEl.textContent='';
      if(!note){ if(errEl) errEl.textContent='Note cannot be empty.'; return; }
      try{ await api.addNote(job.job_id, '[HOLD NOTE] '+note); router(); }
      catch(e){ if(errEl) errEl.textContent=e.message; }
    };
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

    const statusOpts = ['PENDING_INTAKE','READY_FOR_SUPERVISOR_REVIEW','RETURNED_TO_CS','APPROVED_FOR_SCHEDULING','SCHEDULED','IN_PROGRESS','PENDING_QC','COMPLETE','ON_HOLD'].map(s =>
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

      <div class="sup-grid" style="margin-top:16px;">
        <div class="card" style="margin:0;">
          <div class="row" style="margin-bottom:10px;align-items:center;">
            <span class="collapse-title">Pending Corrections</span>
            ${pending.length ? `<span class="count-badge urgent">${pending.length} Critical</span>` : `<span class="muted" style="font-size:12px;">None</span>`}
          </div>
          <div>
            ${pending.map(p=>{
              const title = (p.note||'Correction needed').split(/\r?\n/)[0].slice(0,80);
              const tech  = p.user_name || '—';
              const so    = p.so_number || (p.job_id ? ('#'+p.job_id) : '—');
              return `
                <div class="sup-correction-item">
                  <div class="sup-correction-left">
                    <div class="sup-so">${escapeHtml(so)}</div>
                    <div class="sup-title">${escapeHtml(title)}</div>
                    <div class="sup-meta">Reported by: ${escapeHtml(tech)}</div>
                  </div>
                  <div style="display:flex;align-items:flex-start;gap:8px;">
                    <button class="btn secondary small-btn" data-open-job="${p.job_id}">Re-assign</button>
                  </div>
                </div>
              `;
            }).join('') || `<div class="muted" style="padding:10px;">No pending corrections.</div>`}
          </div>
        </div>

        <div class="card" style="margin:0;">
          <div class="row" style="margin-bottom:10px;align-items:center;">
            <span class="collapse-title">Unassigned Personnel Time</span>
            <span class="muted" style="font-size:12px;">Week Data</span>
          </div>

          <div class="sup-time-grid">
            ${(() => {
              const tiles = [];
              const take = unassigned.slice(0,4);
              for (let i=0;i<4;i++) {
                const u = take[i];
                if (!u) {
                  tiles.push(`
                    <div class="sup-time-tile" style="opacity:0.5;">
                      <div class="sup-time-hrs">No Data</div>
                      <div class="sup-time-label">Admin Overhead</div>
                    </div>
                  `);
                  continue;
                }
                let hrs = '—';
                if (u.start_ts && u.end_ts) {
                  const diff = (new Date(u.end_ts) - new Date(u.start_ts)) / 3600000;
                  hrs = diff.toFixed(1) + 'h';
                }
                tiles.push(`
                  <div class="sup-time-tile">
                    <div class="sup-time-hrs">${escapeHtml(hrs)}</div>
                    <div class="sup-time-label">${escapeHtml(u.user_name||u.reason||'Unassigned')}</div>
                    <button class="btn secondary sup-time-btn" data-open-job="${u.job_id}">View Block</button>
                  </div>
                `);
              }
              return tiles.join('');
            })()}
          </div>
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
    return (s === 'PENDING_INTAKE') && j.created_from === 'portal';
  });
  const manualNeeds = allJobs.filter(j => {
    const s = (j.status||'').toUpperCase();
    return (s === 'PENDING_INTAKE') && j.created_from !== 'portal' && !j.so_number;
  });
  const activeJobs = allJobs.filter(j => {
    const s = (j.status||'').toUpperCase();
    return s === 'SCHEDULED' || s === 'IN_PROGRESS' || s === 'PENDING_QC';
  });

  // helper: compact job row used inside accordions
  function jobRow(j, rightHtml) {
    const vin6 = (j.vin || j.vin_last8 || '').slice(-6) || '—';
    const so = j.so_number || '—';
    return `
      <div class="ops-list-row" data-job-id="${j.job_id}">
        <div class="ops-list-main">
          <div class="ops-list-title">
            <span class="mono">${escapeHtml(so)}</span>
            <span class="ops-dot">•</span>
            <span>${escapeHtml(j.customer_name||'—')}</span>
          </div>
          <div class="ops-list-sub">
            Dealer: ${escapeHtml(j.dealer_name||'—')}<span class="ops-dot">•</span>VIN: <span class="mono">${escapeHtml(vin6)}</span><span class="ops-dot">•</span>
            <span class="badge ${badgeClass(j.status)}">${fmtStatus(j.status)}</span>
          </div>
        </div>
        <div class="ops-list-actions">${rightHtml||''}</div>
      </div>
    `;
  }

  view(`
    <div class="cs-header">
      <div>
        <div class="cs-title">Customer Service</div>
        <div class="cs-sub">Complete intake, assign SO#s, and create jobs.</div>
      </div>
      <div class="cs-header-actions">
        <div class="cs-kpi-pills">
          <span class="cs-pill"><span class="cs-pill-label">Pending Intake</span><span class="cs-pill-val">${portalNeeds.length}</span></span>
          <span class="cs-pill"><span class="cs-pill-label">Needs SO#</span><span class="cs-pill-val">${manualNeeds.length}</span></span>
        </div>
        <input class="input" id="cs-search" placeholder="Search SO#, VIN, customer…" style="max-width:260px;" />
        <button class="btn" id="start-new-intake" type="button">Start Intake</button>
      </div>
    </div>

    <div class="cs-layout">
      <div class="cs-left">
        <div class="card">
          <div class="cs-section-head">
            <h2 class="cs-section-title">PENDING INTAKE - PORTAL JOBS</h2>
            <div class="muted">${portalNeeds.length} item(s)</div>
          </div>
          <table class="table">
            <thead>
              <tr>
                <th>CUSTOMER</th>
                <th>VIN</th>
                <th>DEALER</th>
                <th style="text-align:right;">&nbsp;</th>
              </tr>
            </thead>
            <tbody>
              ${portalNeeds.length ? portalNeeds.map(j => {
                const vin6 = (j.vin || j.vin_last8 || '').slice(-6) || '—';
                const search = [j.customer_name||'', j.dealer_name||'', j.so_number||'', j.vin||j.vin_last8||''].join(' ').toLowerCase();
                return `
                  <tr data-job-id="${j.job_id}" data-search="${escapeHtml(search)}">
                    <td>${escapeHtml(j.customer_name||'—')}</td>
                    <td class="mono">${escapeHtml(vin6)}</td>
                    <td>${escapeHtml(j.dealer_name||'—')}</td>
                    <td style="text-align:right; white-space:nowrap;">
                      <button class="btn small-btn intake-btn" data-id="${j.job_id}">Complete Intake</button>
                      <button class="btn secondary small-btn" data-open-job="${j.job_id}">View</button>
                    </td>
                  </tr>
                `;
              }).join('') : `
                <tr><td colspan="4" class="muted">No portal jobs pending intake.</td></tr>
              `}
            </tbody>
          </table>
        </div>

        <div class="card" style="margin-top:14px;">
          <div class="cs-section-head">
            <h2 class="cs-section-title">NEEDS SO# - MANUAL JOBS</h2>
            <div class="muted">${manualNeeds.length} item(s)</div>
          </div>
          <table class="table">
            <thead>
              <tr>
                <th>CUSTOMER</th>
                <th>VIN</th>
                <th>DEALER</th>
                <th>SO#</th>
                <th style="text-align:right;">&nbsp;</th>
              </tr>
            </thead>
            <tbody>
              ${manualNeeds.length ? manualNeeds.map(j => {
                const vin6 = (j.vin || j.vin_last8 || '').slice(-6) || '—';
                const search = [j.customer_name||'', j.dealer_name||'', j.so_number||'', j.vin||j.vin_last8||''].join(' ').toLowerCase();
                return `
                  <tr data-job-id="${j.job_id}" data-search="${escapeHtml(search)}">
                    <td>${escapeHtml(j.customer_name||'—')}</td>
                    <td class="mono">${escapeHtml(vin6)}</td>
                    <td>${escapeHtml(j.dealer_name||'—')}</td>
                    <td style="min-width:180px;">
                      <input class="input so mono" placeholder="S-ORD######" style="height:36px;" />
                    </td>
                    <td style="text-align:right; white-space:nowrap;">
                      <button class="btn small-btn save-so">Save</button>
                      <button class="btn secondary small-btn" data-open-job="${j.job_id}">View</button>
                    </td>
                  </tr>
                `;
              }).join('') : `
                <tr><td colspan="5" class="muted">No manual jobs need SO#.</td></tr>
              `}
            </tbody>
          </table>
        </div>
      </div>

      <aside class="cs-right">
        <div class="card cs-intake-card">
          <div class="cs-intake-head">
            <div>
              <div class="cs-intake-title">Intake</div>
              <div class="muted" id="cs-intake-hint">Pick a portal job or start a manual intake.</div>
            </div>
          </div>
          <div id="intake-form-content" class="cs-intake-body">
            <div class="muted">Nothing selected.</div>
          </div>
        </div>
      </aside>
    </div>

  `);

  bindJobsTable();

  // Start New Intake - open streamlined manual job form in the intake panel
  function mountManualCreate(){
    const host  = document.getElementById('intake-form-content');
    if (!host) return;
    host.innerHTML = `
      <h3 style="margin:0 0 10px;">Create Manual Job</h3>
      <form id="manual-create">
        <div class="cs-manual-grid">
          <div class="cs-field">
            <label>Customer</label>
            <input class="input" name="customer_name" id="create-customer" placeholder="Customer name" />
          </div>
          <div class="cs-field">
            <label>Dealer</label>
            <select class="select" name="dealer_name" id="create-dealer">
              <option value="">Select...</option>
            </select>
          </div>
          <div class="cs-field">
            <label>VIN</label>
            <input class="input mono" name="vin" id="create-vin" placeholder="VIN (required unless Parts Only)" />
          </div>
          <div class="cs-field">
            <label>Job Type</label>
            <select class="select" name="job_type" id="create-job-type">
              <option value="UPFIT">UPFIT</option>
              <option value="PARTS_ONLY">PARTS_ONLY</option>
              <option value="WARRANTY">WARRANTY</option>
              <option value="SERVICE">SERVICE</option>
            </select>
          </div>
          <div class="cs-field">
            <label>Parts Status</label>
            <select class="select" name="parts_status" id="create-parts-status">
              <option value="NOT_READY">NOT_READY</option>
              <option value="READY">READY</option>
              <option value="ORDERED">ORDERED</option>
              <option value="ARRIVED">ARRIVED</option>
            </select>
          </div>
          <div class="cs-field">
            <label>Est. Hours</label>
            <input class="input mono" name="estimated_hours" id="create-est-hours" value="1" />
          </div>

          <div class="cs-field">
            <label>SO#</label>
            <input class="input mono" name="so_number" id="create-so" placeholder="S-ORD######" />
          </div>
          <div class="cs-field">
            <label>Due Date (optional)</label>
            <input class="input" type="date" name="due_date" id="create-due-date" />
          </div>

          <div class="cs-field" style="grid-column: 1 / -1;">
            <label>Notes</label>
            <textarea class="textarea" name="notes" id="create-notes" rows="3" placeholder="Optional notes"></textarea>
          </div>
        </div>

        <div style="display:flex; gap:10px; align-items:center; justify-content:flex-end; margin-top:14px;">
          <div id="create-status" class="muted" style="margin-right:auto;"></div>
          <button class="btn secondary" type="button" id="create-cancel">Clear</button>
          <button class="btn" type="submit">Create Job</button>
        </div>
      </form>
    `;

    // Dealer options
    const dealerSel = document.getElementById('create-dealer');
    if (dealerSel) {
      const opts = ['<option value="">Select...</option>'];
      dealerList.forEach((d) => {
        opts.push('<option value="' + escapeHtml(d) + '">' + escapeHtml(d) + '</option>');
      });
      dealerSel.innerHTML = opts.join('');
    }

    const cancelBtn = document.getElementById('create-cancel');
    if (cancelBtn) cancelBtn.addEventListener('click', () => {
      host.innerHTML = '';
      host.innerHTML = `<div class="muted">Nothing selected.</div>`;
    });

    const createForm = document.getElementById('manual-create');
    if (!createForm) return;

    createForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const data = Object.fromEntries(new FormData(createForm).entries());

      // Minimal intake requirement: customer OR dealer must be present.
      if (!(data.customer_name || '').trim() && !(data.dealer_name || '').trim()) {
        toast('Enter customer or dealer', true);
        return;
      }

      const jobType = (data.job_type || '').toUpperCase();
      if (jobType !== 'PARTS_ONLY' && !(data.vin || '').trim()) {
        toast('VIN required unless Parts Only', true);
        return;
      }

      const notesVal = (data.notes || '').trim();
      const payload = {
        customer_name:   (data.customer_name || '').trim(),
        dealer_name:     (data.dealer_name || '').trim(),
        vin_last8:       (data.vin || '').trim(),
        job_type:        jobType,
        parts_status:    (data.parts_status || 'NOT_READY').toUpperCase(),
        estimated_hours: data.estimated_hours || '0',
        so_number:       (data.so_number || '').trim(),
        requested_date:  (data.due_date || '').trim(),
        notes:           notesVal,
        notes_type:      notesVal.toLowerCase().includes('part') ? 'parts' : '',
        created_from:    'manual',
      };

      const status = document.getElementById('create-status');
      if (status) status.textContent = 'Creating...';

      try {
        await api.createJob(payload);
        if (status) status.textContent = 'Created.';
        toast('Job created');
        route('/ops/cs');
      } catch(e) {
        const message = e?.data?.data?.message || e?.data?.message || e?.message || 'Error creating job';
        if (status) status.textContent = message;
        toast(message, true);
      }
    });
  }

  const startBtn = document.getElementById('start-new-intake');
  if (startBtn) startBtn.addEventListener('click', mountManualCreate);

  // Intake button opens intake form

  // Intake button opens intake form
  document.querySelectorAll('.intake-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = btn.getAttribute('data-id');
      if (!id) return;
      await openIntake(id, dealerList, salesList);
    });
  });

  // Save SO# for manual jobs
  document.querySelectorAll('.save-so').forEach(btn => {
    btn.addEventListener('click', async () => {
      let id = btn.closest('tr')?.getAttribute('data-job-id');
      const row = btn.closest('tr');
      const input = row?.querySelector('input.so');
      const so = (input?.value || '').trim().toUpperCase();
      if (!id || !so) {
        toast('Enter an SO#', true);
        return;
      }
      try {
        await api.updateJob(id, { so_number: so });
        toast('Saved');
        route('/ops/cs');
      } catch(e) {
        const message = e?.data?.data?.message || e?.data?.message || e?.message || 'Error saving SO#';
        toast(message, true);
      }
    });
  });

  // Local search across both tables
  const csSearch = document.getElementById('cs-search');
  if (csSearch) {
    csSearch.addEventListener('input', () => {
      const q = (csSearch.value || '').trim().toLowerCase();
      document.querySelectorAll('tr[data-search]').forEach(tr => {
        const hay = (tr.getAttribute('data-search') || '');
        tr.style.display = (!q || hay.includes(q)) ? '' : 'none';
      });
    });
  }
}

// CS: render intake form into the right-side panel
async function openIntake(jobId, dealerList, salesList) {
  const host = document.getElementById('intake-form-content');
  const hint = document.getElementById('cs-intake-hint');
  if (!host) return;

  if (hint) hint.textContent = 'Loading…';
  host.innerHTML = `<div class="muted">Loading…</div>`;

  let job;
  try {
    job = await api.job(jobId);
  } catch (e) {
    const msg = e?.message || 'Error loading job';
    if (hint) hint.textContent = msg;
    host.innerHTML = `<div class="muted">${escapeHtml(msg)}</div>`;
    return;
  }

  const vin = (job.vin || job.vin_last8 || '').trim();
  const due = (job.requested_date || '').split(' ')[0];

  if (hint) hint.textContent = `Editing Job #${job.job_id}`;

  host.innerHTML = `
    <div class="cs-intake-meta">
      <div class="cs-intake-meta-row"><span class="muted">Customer</span><span>${escapeHtml(job.customer_name || '—')}</span></div>
      <div class="cs-intake-meta-row"><span class="muted">Dealer</span><span>${escapeHtml(job.dealer_name || '—')}</span></div>
      <div class="cs-intake-meta-row"><span class="muted">Status</span><span class="badge ${badgeClass(job.status)}">${fmtStatus(job.status)}</span></div>
    </div>

    <form id="cs-intake-form" style="margin-top:12px;">
      <div class="cs-manual-grid">
        <div class="cs-field">
          <label>Customer</label>
          <input class="input" name="customer_name" value="${escapeHtml(job.customer_name || '')}" placeholder="Customer name" />
        </div>
        <div class="cs-field">
          <label>Dealer</label>
          <select class="select" name="dealer_name" id="cs-intake-dealer">
            <option value="">Select…</option>
          </select>
        </div>
        <div class="cs-field">
          <label>Sales</label>
          <select class="select" name="sales_person" id="cs-intake-sales">
            <option value="">Select…</option>
          </select>
        </div>
        <div class="cs-field">
          <label>VIN</label>
          <input class="input mono" name="vin" value="${escapeHtml(vin)}" placeholder="VIN" />
        </div>
        <div class="cs-field">
          <label>SO#</label>
          <input class="input mono" name="so_number" value="${escapeHtml(job.so_number || '')}" placeholder="S-ORD######" />
        </div>
        <div class="cs-field">
          <label>Due Date</label>
          <input class="input" type="date" name="due_date" value="${escapeHtml(due)}" />
        </div>
        <div class="cs-field">
          <label>Job Type</label>
          <select class="select" name="job_type">
            ${['UPFIT','PARTS_ONLY','WARRANTY','SERVICE'].map(t => `<option value="${t}" ${String(job.job_type||'UPFIT').toUpperCase()===t?'selected':''}>${t}</option>`).join('')}
          </select>
        </div>
        <div class="cs-field">
          <label>Parts Status</label>
          <select class="select" name="parts_status">
            ${['NOT_READY','READY','ORDERED','ARRIVED'].map(p => `<option value="${p}" ${String(job.parts_status||'NOT_READY').toUpperCase()===p?'selected':''}>${p}</option>`).join('')}
          </select>
        </div>
        <div class="cs-field">
          <label>Est. Hours</label>
          <input class="input mono" name="estimated_hours" value="${escapeHtml(String(job.estimated_hours || job.estimated_minutes ? ((job.estimated_minutes||0)/60) : '1'))}" />
        </div>

        <div class="cs-field" style="grid-column: 1 / -1;">
          <label>Notes</label>
          <textarea class="textarea" name="notes" rows="3" placeholder="Notes">${escapeHtml(job.notes || '')}</textarea>
        </div>
      </div>

      <div class="cs-intake-actions">
        <label class="cs-check"><input type="checkbox" id="cs-ready" checked /> Mark Ready for Scheduling</label>
        <div id="cs-intake-status" class="muted" style="margin-right:auto;"></div>
        <button class="btn secondary" type="button" id="cs-intake-clear">Clear</button>
        <button class="btn" type="submit">Save</button>
      </div>
    </form>
  `;

  // Dealer options
  const dealerSel = document.getElementById('cs-intake-dealer');
  if (dealerSel) {
    dealerSel.innerHTML = ['<option value="">Select…</option>']
      .concat((dealerList||[]).map(d => `<option value="${escapeHtml(d)}" ${d===job.dealer_name?'selected':''}>${escapeHtml(d)}</option>`))
      .join('');
  }
  // Sales options
  const salesSel = document.getElementById('cs-intake-sales');
  if (salesSel) {
    salesSel.innerHTML = ['<option value="">Select…</option>']
      .concat((salesList||[]).map(s => `<option value="${escapeHtml(s)}" ${s===job.sales_person?'selected':''}>${escapeHtml(s)}</option>`))
      .join('');
  }

  const clearBtn = document.getElementById('cs-intake-clear');
  if (clearBtn) {
    clearBtn.onclick = () => {
      if (hint) hint.textContent = 'Pick a portal job or start a manual intake.';
      host.innerHTML = `<div class="muted">Nothing selected.</div>`;
    };
  }

  const form = document.getElementById('cs-intake-form');
  if (!form) return;
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(form).entries());

    if (!(data.customer_name || '').trim() && !(data.dealer_name || '').trim()) {
      toast('Enter customer or dealer', true);
      return;
    }

    const jobType = (data.job_type || '').toUpperCase();
    if (jobType !== 'PARTS_ONLY' && !(data.vin || '').trim()) {
      toast('VIN required unless Parts Only', true);
      return;
    }

    const payload = {
      customer_name:   (data.customer_name || '').trim(),
      dealer_name:     (data.dealer_name || '').trim(),
      sales_person:    (data.sales_person || '').trim(),
      vin_last8:       (data.vin || '').trim(),
      so_number:       (data.so_number || '').trim().toUpperCase(),
      requested_date:  (data.due_date || '').trim(),
      job_type:        jobType,
      parts_status:    (data.parts_status || '').toUpperCase(),
      estimated_hours: (data.estimated_hours || '').trim(),
      notes:           (data.notes || '').trim(),
    };

    const ready = document.getElementById('cs-ready');
    if (ready && ready.checked) payload.status = 'READY_FOR_SUPERVISOR_REVIEW';

    const statusEl = document.getElementById('cs-intake-status');
    if (statusEl) statusEl.textContent = 'Saving…';
    try {
      await api.updateJob(job.job_id, payload);
      if (statusEl) statusEl.textContent = 'Saved.';
      toast('Saved');
      route('/ops/cs');
    } catch (e) {
      const msg = e?.data?.data?.message || e?.data?.message || e?.message || 'Error saving';
      if (statusEl) statusEl.textContent = msg;
      toast(msg, true);
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
      return s==='APPROVED_FOR_SCHEDULING';
    });

    // KPIs
    const delayed = allJobs.filter(j=>j.status==='ON_HOLD').length;

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
          const blocked = j.status==='ON_HOLD';
          return `<div class="sched-card${pri?' sched-card-priority':''}${blocked?' sched-card-blocked':''}" draggable="true" data-job-id="${j.job_id}" data-bay="${escapeHtml(bay.name)}">
            <div class="sched-card-title">${escapeHtml(j.customer_name||j.so_number||'#'+j.job_id)}</div>
            <div class="sched-card-so">${escapeHtml(j.so_number||'')}</div>
            <div class="sched-card-meta">${escapeHtml(j.job_type?fmtStatus(j.job_type):'') }${j.assigned_name?' · '+escapeHtml(j.assigned_name):''}</div>
            ${blocked?'<div class="sched-card-flag sched-card-flag-blocked">HOLD</div>':pri?'<div class="sched-card-flag">PRIORITY</div>':''}
          </div>`;
        }).join('');
        return `<td class="sched-cell${t?' sched-today':''}" data-bay="${escapeHtml(bay.name)}" data-date="${dStr}">${cards}</td>`;
      }).join('');
      return `<tr><td class="sched-bay-label">${escapeHtml(bay.name)}</td>${cells}</tr>`;
    }).join('');

    const weekLabel = shortDate(dates[0])+' — '+shortDate(dates[6])+', '+dates[6].getFullYear();

    // unscheduled queue items
    const unsRows = unscheduled.map(j=>{
      const blocked = j.status==='ON_HOLD';
      const partsHold = j.delay_reason==='parts';
      return `
        <div class="sched-queue-item" draggable="true" data-job-id="${j.job_id}">
          <div class="sched-queue-item-body">
            <div class="sched-queue-item-title">${escapeHtml(j.customer_name||j.so_number||'#'+j.job_id)}</div>
            <div class="sched-queue-item-meta">${escapeHtml(j.so_number||'')}${j.estimated_minutes?' · '+minutesToHours(j.estimated_minutes)+'h':''}</div>
            ${j.requested_date?`<div class="sched-queue-item-due">Due: ${escapeHtml(j.requested_date)}</div>`:''}
            ${partsHold?'<span class="sched-queue-badge sched-queue-badge-hold">Parts Hold</span>':''}
            ${j.priority<=2?'<span class="sched-queue-badge sched-queue-badge-pri">RUSH</span>':''}
          </div>
          <button class="btn secondary small-btn sched-quick-add" data-id="${j.job_id}">+ Schedule</button>
        </div>
      `;
    }).join('');

    view(`
      <!-- Toolbar strip -->
      <div class="sched-topbar">
        <div class="sched-topbar-kpis">
          ${kpi('This Week', weekJobs.length)}
          ${kpi('Unscheduled', unscheduled.length)}
          ${kpi('Delayed', delayed)}
        </div>
        <div class="inline">
          <button class="btn secondary small-btn" id="sched-prev">← Prev</button>
          <button class="btn secondary small-btn" id="sched-today-btn">Today</button>
          <button class="btn secondary small-btn" id="sched-next">Next →</button>
          <span class="sched-week-label">${weekLabel}</span>
          <button class="btn small-btn" id="sched-new-entry">+ New Entry</button>
        </div>
      </div>

      <!-- 3-column layout -->
      <div class="sched-3col">

        <!-- Left: Unscheduled Queue -->
        <div class="sched-queue-panel">
          <div class="sched-panel-header">
            Unscheduled / Ready
            ${unscheduled.length?`<span class="count-badge">${unscheduled.length}</span>`:''}
          </div>
          <div class="sched-queue-filter-row">
            <input class="input sched-queue-search" id="sched-q-search" placeholder="Search..." />
          </div>
          <div class="sched-queue-list" id="sched-queue-list">
            ${unsRows || '<div class="sched-queue-empty">No unscheduled jobs.</div>'}
          </div>
        </div>

        <!-- Center: Calendar Grid -->
        <div class="sched-board-panel">
          <div class="sched-grid-wrap">
            <table class="sched-grid">
              <thead><tr><th class="sched-bay-header">Bay</th>${dayHeaders}</tr></thead>
              <tbody>${bayRows}</tbody>
            </table>
          </div>
        </div>

        <!-- Right: Inspector Panel -->
        <div class="sched-inspector-panel" id="sched-inspector">
          <div class="sched-panel-header">Inspector</div>
          <div class="sched-inspector-empty" id="sched-inspector-empty">
            <span class="material-symbols-outlined" style="font-size:28px;opacity:.3;">touch_app</span>
            <div style="margin-top:8px;opacity:.5;font-size:12px;">Click a job card to inspect</div>
          </div>
          <div class="sched-inspector-content" id="sched-inspector-content" style="display:none;"></div>
        </div>

      </div>
    `);

    bindBoard();
  }

  // ── board bindings ──────────────────────────────────
  function bindBoard(){
    document.getElementById('sched-prev').onclick     = ()=>{ weekOffset--; renderBoard(); };
    document.getElementById('sched-next').onclick     = ()=>{ weekOffset++; renderBoard(); };
    document.getElementById('sched-today-btn').onclick= ()=>{ weekOffset=0; renderBoard(); };
    document.getElementById('sched-new-entry').onclick= ()=> openNewEntryModal();

    // Queue search filter
    const qSearch = document.getElementById('sched-q-search');
    if(qSearch) qSearch.addEventListener('input', ()=>{
      const q = qSearch.value.trim().toLowerCase();
      document.querySelectorAll('#sched-queue-list .sched-queue-item').forEach(item=>{
        const txt = (item.textContent||'').toLowerCase();
        item.style.display = (!q || txt.includes(q)) ? '' : 'none';
      });
    });

    // click card → inspector panel (not modal)
    $$('.sched-card').forEach(c=>{
      c.addEventListener('click', e=>{
        if(c.classList.contains('dragging')) return;
        $$('.sched-card').forEach(x=>x.classList.remove('sched-card-selected'));
        c.classList.add('sched-card-selected');
        showInspector(parseInt(c.getAttribute('data-job-id'),10));
      });
    });

    // ── drag from board cards ───────────────────────
    $$('.sched-card, .sched-queue-item[draggable]').forEach(el=>{
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
          if(job.status==='APPROVED_FOR_SCHEDULING') job.status='SCHEDULED';
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

  // ── Inspector panel ─────────────────────────────────
  async function showInspector(jobId){
    const emptyEl   = document.getElementById('sched-inspector-empty');
    const contentEl = document.getElementById('sched-inspector-content');
    if(!emptyEl||!contentEl) return;

    emptyEl.style.display   = 'none';
    contentEl.style.display = '';
    contentEl.innerHTML     = '<div class="sched-inspector-loading">Loading…</div>';

    let job = allJobs.find(j=>j.job_id===jobId);
    try { job = await api.job(jobId); } catch(_){}
    if(!job){ contentEl.innerHTML='<div class="sched-inspector-loading muted">Job not found.</div>'; return; }

    const estH       = job.estimated_minutes ? (job.estimated_minutes/60).toFixed(1) : '—';
    const actH       = job.actual_minutes    ? (job.actual_minutes/60).toFixed(1)    : '0.0';
    const partsHold  = job.status==='ON_HOLD' && job.delay_reason==='parts';
    const apprHold   = job.status==='ON_HOLD' && job.delay_reason==='approval';
    const t = job.time || {approved_minutes_total:0};

    contentEl.innerHTML = `
      <div class="sched-insp-header">
        <div class="sched-insp-so">${escapeHtml(job.so_number||'#'+job.job_id)}</div>
        <span class="badge ${badgeClass(job.status)}">${fmtStatus(job.status)}</span>
      </div>
      <div class="sched-insp-customer">${escapeHtml(job.customer_name||job.dealer_name||'—')}</div>

      ${(partsHold||apprHold)?`<div class="sched-insp-alert">${partsHold?'⚠ Parts Hold':'⚠ Approval Hold'}</div>`:''}
      ${job.priority&&job.priority<=2?'<div class="sched-insp-rush">⚡ RUSH</div>':''}

      <div class="sched-insp-rows">
        <div class="sched-insp-row"><span class="label">Bay</span><span>${escapeHtml(job.work_center||'—')}</span></div>
        <div class="sched-insp-row"><span class="label">Start</span><span>${escapeHtml((job.scheduled_start||'—').split(' ')[0])}</span></div>
        <div class="sched-insp-row"><span class="label">Finish</span><span>${escapeHtml((job.scheduled_finish||'—').split(' ')[0])}</span></div>
        <div class="sched-insp-row"><span class="label">Tech</span><span>${escapeHtml(job.assigned_name||'—')}</span></div>
        <div class="sched-insp-row"><span class="label">Est / Act</span><span>${estH} / ${(t.approved_minutes_total/60).toFixed(1)} hrs</span></div>
        ${job.requested_date?`<div class="sched-insp-row"><span class="label">Promised</span><span>${escapeHtml(job.requested_date)}</span></div>`:''}
      </div>

      ${job.notes?`<div class="sched-insp-notes">${escapeHtml(job.notes)}</div>`:''}

      <div class="sched-insp-btns">
        <button class="btn btn-xl sched-insp-start-btn" data-jid="${job.job_id}">▶ Start</button>
        <button class="btn secondary btn-xl sched-insp-stop-btn">■ Stop</button>
      </div>
      <div style="margin-top:8px;">
        <button class="btn secondary small-btn sched-insp-open-btn" style="width:100%;" data-jid="${job.job_id}">Open Full Detail →</button>
      </div>
    `;

    contentEl.querySelector('.sched-insp-start-btn').onclick = async ()=>{
      try{
        await api.timeStart(job.job_id, null, '');
        toast('Timer started.');
        showInspector(job.job_id);
      }catch(e){ toast(e.message,true); }
    };
    contentEl.querySelector('.sched-insp-stop-btn').onclick = async ()=>{
      try{
        await api.timeStop();
        toast('Timer stopped.');
      }catch(e){ toast(e.message,true); }
    };
    contentEl.querySelector('.sched-insp-open-btn').onclick = ()=>{
      window.history.pushState({},'','/ops/job/'+job.job_id);
      router();
    };
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
      return s==='APPROVED_FOR_SCHEDULING'||s==='SCHEDULED';
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
          if(job.status==='APPROVED_FOR_SCHEDULING') job.status='SCHEDULED';
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
  const isPhone = getViewMode() === 'phone';

  function techJobCard(job) {
    const isActive = job.job_id === activeJobId;
    const estHrs   = job.estimated_minutes ? (job.estimated_minutes / 60).toFixed(1) : null;

    // Action buttons depend on state
    let actionHtml;
    if (isActive) {
      actionHtml = `<div style="flex:1;font-size:13px;font-weight:700;color:var(--arches);">▲ Active — see timer above</div>`;
    } else if (job.status === 'IN_PROGRESS') {
      actionHtml = `<button class="btn btn-xl" data-submit-qc="${job.job_id}" style="flex:1;">Submit for QC</button>`;
    } else {
      actionHtml = `<button class="btn btn-xl" data-start-job="${job.job_id}" style="flex:1;">Start</button>`;
    }

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
          ${actionHtml}
          <button class="btn secondary btn-xl" data-open-job="${job.job_id}">View</button>
        </div>
      </div>
    `;
  }

  view(`
    <div${isPhone ? ' class="phone-mode"' : ''}>

    <div style="display:flex;justify-content:flex-end;margin-bottom:10px;">
      ${viewModeToggle()}
    </div>

    ${active ? `
      <div class="active-job-card">
        <div class="label-sm">Active Job</div>
        <div class="tech-job-so" style="margin-top:2px;">${escapeHtml(active.so_number || '#' + active.job_id)}</div>
        <div class="tech-job-meta">${escapeHtml(active.customer_name||'')}${active.vin ? ' · …'+escapeHtml(active.vin.slice(-6)) : ''}${active.work_center ? ' · '+escapeHtml(active.work_center) : ''}</div>
        <div class="timer-display" id="live-timer">00:00:00</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <button class="btn danger btn-xl" id="stop-active" style="flex:1;">Stop Job</button>
          <button class="btn btn-xl" id="submit-qc-active" style="flex:1;">Submit for QC</button>
          <button class="btn secondary btn-xl" id="note-toggle" style="flex:1;">+ Note</button>
        </div>
        <div id="qc-submit-panel" style="display:none;margin-top:14px;">
          <textarea class="input" id="qc-submit-notes" rows="3" placeholder="Describe work completed…"></textarea>
          <div style="margin-top:8px;display:flex;gap:8px;">
            <button class="btn" id="qc-submit-confirm" style="flex:1;">Confirm Submit for QC</button>
            <button class="btn secondary" id="qc-submit-cancel">Cancel</button>
          </div>
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

    ${isPhone ? '' : `
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
    `}

    </div>
  `);

  bindViewModeToggle();

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

  // Submit for QC from active job card
  const submitQcActive = document.getElementById('submit-qc-active');
  if (submitQcActive) {
    submitQcActive.onclick = () => {
      const panel = document.getElementById('qc-submit-panel');
      panel.style.display = panel.style.display === 'none' ? '' : 'none';
      if (panel.style.display !== 'none') document.getElementById('qc-submit-notes').focus();
    };
  }
  const qcSubmitConfirm = document.getElementById('qc-submit-confirm');
  if (qcSubmitConfirm) {
    qcSubmitConfirm.onclick = async () => {
      const notes = (document.getElementById('qc-submit-notes').value || '').trim();
      if (!notes) { toast('Describe work completed', true); return; }
      qcSubmitConfirm.disabled = true;
      qcSubmitConfirm.textContent = 'Submitting…';
      try {
        clearInterval(state.timerInterval);
        await api.submitQC(activeJobId, notes);
        toast('Submitted for QC');
        router();
      } catch(e) { alert(e.message); qcSubmitConfirm.disabled = false; qcSubmitConfirm.textContent = 'Confirm Submit for QC'; }
    };
  }
  const qcSubmitCancel = document.getElementById('qc-submit-cancel');
  if (qcSubmitCancel) {
    qcSubmitCancel.onclick = () => { document.getElementById('qc-submit-panel').style.display = 'none'; };
  }

  // Submit for QC from job cards (non-active IN_PROGRESS jobs)
  $$('[data-submit-qc]').forEach(btn => {
    btn.onclick = async () => {
      const jobId = parseInt(btn.getAttribute('data-submit-qc'), 10);
      const notes = prompt('Describe work completed (required):');
      if (!notes || !notes.trim()) return;
      btn.disabled = true;
      btn.textContent = 'Submitting…';
      try {
        await api.submitQC(jobId, notes.trim());
        toast('Submitted for QC');
        router();
      } catch(e) { alert(e.message); btn.disabled = false; btn.textContent = 'Submit for QC'; }
    };
  });

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
  const [jobsResp, settingsResp, usersResp] = await Promise.all([
    api.jobs('?limit=500'),
    api.settings(),
    api.users().catch(()=>({users:[]})),
  ]);
  const jobs     = jobsResp.jobs || [];
  const settings = settingsResp || {};
  const users    = usersResp.users || [];

  // ── Capacity math ──────────────────────────────────
  function timeToH(t){ const p=(t||'0:0').split(':'); return parseInt(p[0]||0)+parseInt(p[1]||0)/60; }
  const shiftH   = Math.max(1, timeToH(settings.shift_end||'15:30') - timeToH(settings.shift_start||'07:00') - ((parseInt(settings.lunch_minutes||30,10))/60));
  const techCount= Math.max(1, users.filter(u=>u.ops_role==='tech'||u.ops_role==='supervisor').length);
  const dailyCap = shiftH * techCount;

  // ── Today's plan ────────────────────────────────────
  const todayStr  = new Date().toISOString().split('T')[0];
  const todayJobs = jobs.filter(j=>(j.scheduled_start||'').startsWith(todayStr));
  const todayH    = todayJobs.reduce((s,j)=>s+((parseInt(j.estimated_minutes||0,10))/60),0);
  const lateBlocked = jobs.filter(j=>{ const s=(j.status||'').toUpperCase(); return s==='ON_HOLD'; }).length;
  const loadPct   = dailyCap>0 ? Math.min(100,Math.round((todayH/dailyCap)*100)) : 0;
  const capColor  = loadPct>90 ? '#dc2626' : loadPct>75 ? '#d97706' : '#16a34a';

  // ── Flow health ─────────────────────────────────────
  const stages = [
    {key:'PENDING_INTAKE',              label:'Pending Intake'},
    {key:'READY_FOR_SUPERVISOR_REVIEW', label:'Supervisor Review'},
    {key:'APPROVED_FOR_SCHEDULING',     label:'Approved'},
    {key:'SCHEDULED',                   label:'Scheduled'},
    {key:'IN_PROGRESS',                 label:'In Progress'},
    {key:'PENDING_QC',                  label:'Pending QC'},
  ];
  const now = Date.now();
  const stageData = stages.map(({key,label})=>{
    const group = jobs.filter(j=>(j.status||'').toUpperCase()===key);
    const oldest = group.length
      ? Math.max(...group.map(j=>Math.round((now-new Date(j.created_at||j.updated_at||now))/(1000*3600*24))))
      : 0;
    return {label, count:group.length, oldest};
  });

  // ── Alerts ──────────────────────────────────────────
  const partsHold    = jobs.filter(j=>j.delay_reason==='parts').length;
  const approvalHold = jobs.filter(j=>j.delay_reason==='approval').length;
  const overHours    = jobs.filter(j=>{ const e=parseInt(j.estimated_minutes||0,10); const a=parseInt(j.actual_minutes||0,10); return e>0&&a>e*1.15; }).length;

  const todayLabel = new Date().toLocaleDateString('en-US',{weekday:'long',year:'numeric',month:'long',day:'numeric'});

  view(`
    <!-- Page header + quick actions -->
    <div class="dash-header-row">
      <div>
        <div class="dash-page-label">Dashboard</div>
        <div class="muted" style="margin-top:2px;font-size:13px;">${todayLabel}</div>
      </div>
      <div class="dash-quick-actions">
        <button class="btn small-btn" id="qa-create-job">+ Create Job</button>
        <button class="btn secondary small-btn" id="qa-sched">+ Schedule Block</button>
        <button class="btn secondary small-btn" id="qa-add-note">+ Add Note</button>
      </div>
    </div>

    <!-- Today's Plan -->
    <div class="dash-section-title">Today's Plan</div>
    <div class="dash-kpi-row">
      <div class="dash-kpi-card">
        <div class="dash-kpi-label">Scheduled Today</div>
        <div class="dash-kpi-value">${todayJobs.length}</div>
      </div>
      <div class="dash-kpi-card">
        <div class="dash-kpi-label">Late / Blocked</div>
        <div class="dash-kpi-value${lateBlocked>0?' dash-kpi-warn':''}">${lateBlocked}</div>
      </div>
      <div class="dash-kpi-card dash-kpi-capacity">
        <div class="dash-kpi-label">Hours Loaded <span class="dash-kpi-sub">${todayH.toFixed(1)} / ${dailyCap.toFixed(1)} hrs</span></div>
        <div class="cap-bar-wrap"><div class="cap-bar" style="width:${loadPct}%;background:${capColor};"></div></div>
        <div class="dash-cap-pct">${loadPct}% capacity (${techCount} tech${techCount!==1?'s':''})</div>
      </div>
    </div>

    <!-- Flow Health -->
    <div class="dash-section-title">Flow Health</div>
    <div class="dash-flow-grid">
      ${stageData.map(s=>`
        <div class="dash-flow-card${s.count===0?' dash-flow-empty':''}">
          <div class="dash-flow-label">${s.label}</div>
          <div class="dash-flow-count">${s.count}</div>
          <div class="dash-flow-age">${s.count>0?'Oldest: '+s.oldest+'d':'—'}</div>
        </div>
      `).join('')}
    </div>

    ${(partsHold+approvalHold+overHours)>0 ? `
    <!-- Alerts -->
    <div class="dash-section-title">Alerts</div>
    <div class="dash-alerts-row">
      ${partsHold>0    ?`<div class="dash-alert dash-alert-warn"><span class="material-symbols-outlined dash-alert-icon">inventory_2</span> Parts Hold: <strong>${partsHold}</strong> job${partsHold!==1?'s':''}</div>`:''}
      ${approvalHold>0 ?`<div class="dash-alert dash-alert-warn"><span class="material-symbols-outlined dash-alert-icon">pending_actions</span> Approval Hold: <strong>${approvalHold}</strong> job${approvalHold!==1?'s':''}</div>`:''}
      ${overHours>0    ?`<div class="dash-alert dash-alert-red"><span class="material-symbols-outlined dash-alert-icon">schedule</span> Over Hours: <strong>${overHours}</strong> job${overHours!==1?'s':''}</div>`:''}
    </div>
    `:''}

    <!-- Recent Jobs -->
    <div class="dash-section-title">Recent Activity</div>
    <div class="card">
      ${jobsTable(jobs.slice(0,20))}
    </div>

    <!-- Quick-add note modal (hidden until triggered) -->
    <div id="qa-note-modal" class="modal-overlay" style="display:none;">
      <div class="modal" style="max-width:440px;">
        <div class="modal-header">
          <h2>Add Note to Job</h2>
          <button class="modal-close" id="qa-note-close">&times;</button>
        </div>
        <div class="modal-body">
          <div class="label" style="margin-bottom:6px;">Job ID</div>
          <input class="input" id="qa-note-jobid" type="number" min="1" placeholder="Enter Job ID..." style="margin-bottom:12px;" />
          <div class="label" style="margin-bottom:6px;">Note</div>
          <textarea class="input" id="qa-note-text" rows="4" placeholder="Internal note..."></textarea>
          <div class="field-error" id="qa-note-err" style="margin-top:6px;display:none;"></div>
        </div>
        <div class="modal-footer">
          <button class="btn" id="qa-note-submit">Add Note</button>
          <button class="btn secondary" id="qa-note-cancel">Cancel</button>
        </div>
      </div>
    </div>
  `);

  bindJobsTable();

  // Quick actions
  document.getElementById('qa-create-job').onclick = ()=>{ window.history.pushState({},'','/ops/new'); router(); };
  document.getElementById('qa-sched').onclick      = ()=>{ window.history.pushState({},'','/ops/schedule'); router(); };

  // Note modal
  const noteModal  = document.getElementById('qa-note-modal');
  const closeNote  = ()=>{ noteModal.style.display='none'; };
  document.getElementById('qa-add-note').onclick    = ()=>{ noteModal.style.display='flex'; };
  document.getElementById('qa-note-close').onclick  = closeNote;
  document.getElementById('qa-note-cancel').onclick = closeNote;
  noteModal.addEventListener('click', e=>{ if(e.target===noteModal) closeNote(); });
  document.getElementById('qa-note-submit').onclick = async ()=>{
    const jobId = parseInt(document.getElementById('qa-note-jobid').value||'0',10);
    const note  = (document.getElementById('qa-note-text').value||'').trim();
    const err   = document.getElementById('qa-note-err');
    err.style.display='none'; err.textContent='';
    if(!jobId){ err.textContent='Enter a valid job ID.'; err.style.display=''; return; }
    if(!note) { err.textContent='Note cannot be empty.';  err.style.display=''; return; }
    try{
      await api.addNote(jobId, note);
      toast('Note added.');
      closeNote();
      document.getElementById('qa-note-text').value='';
      document.getElementById('qa-note-jobid').value='';
    }catch(e){ err.textContent=e.message; err.style.display=''; }
  };
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
  const failedQC    = allJobs.filter(j => (j.qc_failed_count > 0 && j.status !== 'COMPLETE')).length;
  const isPhone = getViewMode() === 'phone';

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

  function qcPhoneCard(j) {
    return `
      <div class="card qc-card">
        <div class="qc-card-so">${escapeHtml(j.so_number || '#'+j.job_id)}</div>
        <div class="qc-card-meta">
          ${escapeHtml(j.customer_name||'—')} · ${escapeHtml(j.assigned_name||'—')} · <span style="padding:2px 6px;border-radius:4px;font-size:11px;font-weight:600;${queueBadgeStyle(j.updated_at)}">${timeInQueue(j.updated_at)}</span>
        </div>
        <div class="qc-card-actions">
          <button class="btn" data-qc-pass="${j.job_id}">Pass</button>
          <button class="btn secondary" data-qc-fail="${j.job_id}">Fail</button>
          <button class="btn secondary" data-open-job="${j.job_id}">Inspect</button>
        </div>
      </div>`;
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
            <td style="white-space:nowrap;">
              <button class="btn small-btn" data-qc-pass="${j.job_id}">Pass</button>
              <button class="btn secondary small-btn" data-qc-fail="${j.job_id}">Fail</button>
              <button class="btn secondary small-btn" data-open-job="${j.job_id}">Inspect</button>
            </td>
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
    <div${isPhone ? ' class="phone-mode"' : ''}>

    <div class="card">
      <div class="row" style="align-items:flex-start;">
        <div style="flex:1 1 280px;">
          <h2 style="margin:0;">QC Queue</h2>
          <div class="muted" style="margin-top:4px;">Quality Control Station</div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
          ${kpi('Pending', jobs.length)}
          <div class="kpi" style="border-left:3px solid #c0392b;">
            <div class="kpi-label">Failed QC</div>
            <div class="kpi-value" style="color:#c0392b;">${failedQC}</div>
          </div>
          <div class="kpi" style="border-left:3px solid #27ae60;">
            <div class="kpi-label">Passed Today</div>
            <div class="kpi-value" style="color:#27ae60;">${passedToday}</div>
          </div>
          ${viewModeToggle()}
        </div>
      </div>
    </div>

    <div>
      <input class="input" id="qc-search" placeholder="Search SO# or VIN…" style="margin-bottom:10px;max-width:300px;" />

      ${isPhone ? `
        <div id="qc-phone-list">
          ${jobs.length ? jobs.map(qcPhoneCard).join('') : '<div class="card muted" style="text-align:center;padding:20px;">No jobs pending QC.</div>'}
        </div>
      ` : `
        <div class="card">
          <table class="table" id="qc-table">
            <thead><tr>
              <th>SO Number</th><th>Customer</th><th>VIN (Last 8)</th><th>Time in Queue</th><th>Technician</th><th>Actions</th>
            </tr></thead>
            ${renderTable(jobs)}
          </table>
        </div>
      `}
    </div>

    </div>
  `);

  bindViewModeToggle();
  bindJobsTable();

  let filtered = [...jobs];

  function rebindQcActions() {
    $$('[data-qc-pass]').forEach(btn => {
      btn.onclick = async () => {
        if (!confirm('QC Pass — mark job Complete?')) return;
        const id = parseInt(btn.getAttribute('data-qc-pass'), 10);
        btn.disabled = true; btn.textContent = '…';
        try { await api.reviewQC(id, 'PASS', ''); toast('QC Passed'); router(); }
        catch(e) { alert(e.message); btn.disabled = false; btn.textContent = 'Pass'; }
      };
    });
    $$('[data-qc-fail]').forEach(btn => {
      btn.onclick = async () => {
        const id = parseInt(btn.getAttribute('data-qc-fail'), 10);
        const notes = prompt('QC failure reason (required):');
        if (!notes || !notes.trim()) return;
        btn.disabled = true; btn.textContent = '…';
        try { await api.reviewQC(id, 'FAIL', notes.trim()); toast('QC Failed — returned to tech'); router(); }
        catch(e) { alert(e.message); btn.disabled = false; btn.textContent = 'Fail'; }
      };
    });
  }

  rebindQcActions();

  function rebind() {
    const prev = $('#qc-prev'), next = $('#qc-next');
    if (prev) prev.onclick = () => { if (page > 0) { page--; refresh(); } };
    if (next) next.onclick = () => { if ((page+1)*PAGE_SIZE < filtered.length) { page++; refresh(); } };
  }

  function refresh() {
    if (isPhone) {
      const list = document.getElementById('qc-phone-list');
      if (list) {
        list.innerHTML = filtered.length ? filtered.map(qcPhoneCard).join('') : '<div class="card muted" style="text-align:center;padding:20px;">No matches.</div>';
        bindJobsTable();
        rebindQcActions();
      }
    } else {
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
      rebindQcActions();
    }
  }

  rebind();

  const search = $('#qc-search');
  if (search) {
    search.addEventListener('input', () => {
      const q = search.value.trim().toLowerCase();
      page = 0;
      filtered = q ? jobs.filter(j =>
        (j.so_number||'').toLowerCase().includes(q) ||
        (j.vin||'').toLowerCase().includes(q) ||
        (j.customer_name||'').toLowerCase().includes(q)
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
        <div class="label">Pending Intake</div>
        <div class="value">${byS('PENDING_INTAKE')}${deltaChip(1, true)}</div>
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
      <div class="collapse-title" style="margin-bottom:10px;">Admin Tools</div>
      <div class="row">
        <a class="btn secondary" href="/wp-admin/users.php">WP Users</a>
        <a class="btn secondary" href="/ops/supervisor" data-link>Supervisor</a>
        <a class="btn secondary" href="/ops/jobs" data-link>Jobs</a>
        <a class="btn secondary" href="/ops/qc" data-link>QC</a>
        <a class="btn secondary" href="/ops/bom" data-link>BOMs</a>
        <a class="btn secondary" href="/ops/settings" data-link>Settings</a>
        <a class="btn secondary" href="/ops/admin#work-centers" data-link>Work Centers</a>
        <a class="btn secondary" href="/ops/quotes" data-link>Pricing</a>
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

  
  
  async function loadBOM(){
    view(`
      <div class="ops-grid" style="display:grid; grid-template-columns: 360px 1fr; gap: 14px;">
        <div class="card">
          <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
            <h2 style="margin:0;">BOMs</h2>
            <button class="btn btn-primary" id="bom-new">+ New</button>
          </div>
          <div style="margin-top:10px;">
            <input id="bom-search" class="input" placeholder="Search BOM # or name..." style="width:100%;" />
          </div>
          <div id="bom-list" style="margin-top:10px; max-height: 70vh; overflow:auto;"></div>
        </div>

        <div class="card" id="bom-editor">
          <h2 style="margin:0 0 8px;">BOM Builder</h2>
          <p style="margin:0; color:rgba(0,0,0,0.65);">Select a BOM on the left, or click <b>New</b>.</p>
        </div>
      </div>
    `);

    const state = { boms: [], activeId: null, active: null, lines: [] };

    const elList = $('#bom-list');
    const elSearch = $('#bom-search');
    const elNew = $('#bom-new');
    const elEditor = $('#bom-editor');

    function fmt(v){ return (v===null||v===undefined)?'':String(v); }

    function renderList(){
      const q = (elSearch.value||'').toLowerCase().trim();
      const rows = state.boms.filter(b=>{
        const key = `${b.bom_no||''} ${b.name||''}`.toLowerCase();
        return !q || key.includes(q);
      });
      if (!rows.length){
        elList.innerHTML = `<div style="padding:10px; color:rgba(0,0,0,0.65);">No BOMs found.</div>`;
        return;
      }
      elList.innerHTML = rows.map(b=>{
        const active = (String(b.id)===String(state.activeId)) ? 'background:#f5f5f5;' : '';
        const rev = b.revision ? `<span style="font-size:12px; opacity:.7;">Rev ${escapeHtml(b.revision)}</span>` : '';
        return `
          <div class="bom-row" data-id="${b.id}" style="padding:10px; border:1px solid rgba(0,0,0,0.08); border-radius:10px; margin-bottom:8px; cursor:pointer; ${active}">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
              <div style="font-weight:600;">${escapeHtml(b.bom_no||'')}</div>
              ${rev}
            </div>
            <div style="font-size:13px; opacity:.8; margin-top:4px;">${escapeHtml(b.name||'')}</div>
          </div>
        `;
      }).join('');
      $$('.bom-row', elList).forEach(row=>{
        row.onclick = ()=> openBom(row.getAttribute('data-id'));
      });
    }

    
      function money(n){
        try {
          const v = Number(n || 0);
          return v.toLocaleString(undefined, {style:'currency', currency:'USD'});
        } catch(e){
          return '$' + (Number(n||0).toFixed(2));
        }
      }

	      function computeTotals(){
	        const dealers = Array.isArray(state.dealers) ? state.dealers : [];
	        const dealer = dealers.find(d => String(d.id) === String(state.activeDealerId)) || dealers[0] || null;
        const laborRateR = dealer ? Number(dealer.labor_rate_retail_published||0) : 0;
        const laborRateW = dealer ? Number(dealer.labor_rate_wholesale_published||0) : 0;
        const shopBaseR  = dealer ? Number(dealer.shop_supply_base_retail_published||0) : 0;
        const shopBaseW  = dealer ? Number(dealer.shop_supply_base_wholesale_published||0) : 0;

        let partsR = 0, partsW = 0;
        state.active.lines.forEach(ln => {
          if ((ln.line_type || 'PART').toUpperCase() !== 'PART') return;
          const q = Number(ln.qty || 0);
          partsR += Number(ln.unit_retail || 0) * q;
          partsW += Number(ln.unit_wholesale || 0) * q;
        });

        const hrs = Number(state.active.bom.install_hours || 0);
        const su  = Number(state.active.bom.shop_supply_units || 0);

        const laborR = hrs * laborRateR;
        const laborW = hrs * laborRateW;
        const shopR  = su * shopBaseR;
        const shopW  = su * shopBaseW;

        return {
          dealer,
          partsR, partsW,
          hrs, su,
          laborR, laborW,
          shopR, shopW,
          installedR: partsR + laborR + shopR,
          installedW: partsW + laborW + shopW
        };
      }

	      async function ensureDealersLoaded(){
	        if (state.dealers && state.dealers.length) return;
	        try {
	          const res = await apiFetch('/wp-json/slate-ops/v1/pricing/dealers');
	          const dealers = Array.isArray(res)
	            ? res
	            : (Array.isArray(res?.dealers) ? res.dealers : []);
	          // Support both {ok:true, dealers:[...]} and bare array payloads.
	          if (dealers.length || (res && res.ok)) {
	            state.dealers = dealers;
	            if (!state.activeDealerId && state.dealers.length) state.activeDealerId = state.dealers[0].id;
	          }
	        } catch(e){}
	      }

      async function lookupSkuIntoLine(idx, sku){
        if (!sku) return;
        try {
          const res = await apiFetch('/wp-json/slate-ops/v1/pricing/products/lookup?sku=' + encodeURIComponent(sku));
          if (res && res.ok && res.product) {
            const p = res.product;
            const ln = state.active.lines[idx];
            if (!ln) return;
            ln.product_id = p.id;
            ln.sku = p.sku;
            ln.product_name = p.product_name;
            ln.unit_wholesale = Number(p.dealer_price_published||0);
            ln.unit_retail = Number(p.retail_price_published||0);
            renderEditor();
          }
        } catch(e){}
      }

      function renderEditor(){
        if(!state.active){
          elEditor.innerHTML = '<div class="slate-muted">Select a BOM on the left, or click <b>New</b>.</div>';
          return;
        }

        const t = computeTotals();

        const dealerOptions = (state.dealers||[]).map(d => `
          <option value="${escapeAttr(d.id)}" ${String(d.id)===String(state.activeDealerId)?'selected':''}>
            ${escapeHtml(d.dealer_code || '')} - ${escapeHtml(d.dealer_name || '')}
          </option>`).join('');

        const linesHtml = state.active.lines.map((ln, idx) => {
          const type = (ln.line_type||'PART').toUpperCase();
          if (type !== 'PART') return '';
          const q = Number(ln.qty||1);
          const extW = Number(ln.unit_wholesale||0) * q;
          const extR = Number(ln.unit_retail||0) * q;

          return `
            <tr data-idx="${idx}">
              <td style="width:180px">
                <input class="slate-input bom-sku" list="bom-sku-list" value="${escapeAttr(ln.sku||'')}" placeholder="SKU..." />
              </td>
              <td>${escapeHtml(ln.product_name||'')}</td>
              <td style="width:90px"><input class="slate-input bom-qty" type="number" step="0.01" value="${escapeAttr(ln.qty||1)}" /></td>
              <td style="width:130px" class="bom-unitw">${money(ln.unit_wholesale||0)}</td>
              <td style="width:130px" class="bom-extw">${money(extW)}</td>
              <td style="width:130px" class="bom-unitr">${money(ln.unit_retail||0)}</td>
              <td style="width:130px" class="bom-extr">${money(extR)}</td>
              <td style="width:50px"><button class="slate-btn slate-btn--sm slate-btn--ghost bom-del">×</button></td>
            </tr>
          `;
        }).join('');

        elEditor.innerHTML = `
          <div class="slate-card">
            <div class="slate-row" style="justify-content:space-between; gap:12px; align-items:flex-end;">
              <div style="flex:1">
                <div class="slate-row" style="gap:10px; align-items:flex-end; flex-wrap:wrap;">
                  <div style="min-width:240px">
                    <label class="slate-label">BOM #</label>
                    <input id="bom_no" class="slate-input" value="${escapeAttr(state.active.bom.bom_no||'')}" />
                  </div>
                  <div style="flex:1; min-width:260px">
                    <label class="slate-label">BOM Name</label>
                    <input id="bom_name" class="slate-input" value="${escapeAttr(state.active.bom.bom_name||'')}" />
                  </div>
                  <div style="min-width:120px">
                    <label class="slate-label">Rev</label>
                    <input id="bom_rev" class="slate-input" value="${escapeAttr(state.active.bom.revision||'1.0')}" />
                  </div>
                  <div style="min-width:140px">
                    <label class="slate-label">Install Hours</label>
                    <input id="install_hours" class="slate-input" type="number" step="0.1" value="${escapeAttr(state.active.bom.install_hours||0)}" />
                  </div>
                  <div style="min-width:160px">
                    <label class="slate-label">Shop Supply Units</label>
                    <input id="shop_supply_units" class="slate-input" type="number" step="0.1" value="${escapeAttr(state.active.bom.shop_supply_units||0)}" />
                  </div>
                  <div style="min-width:260px">
                    <label class="slate-label">Dealer (for totals)</label>
                    <select id="dealer_id" class="slate-input">${dealerOptions}</select>
                  </div>
                </div>
              </div>

              <div class="slate-row" style="gap:8px;">
                <button id="bom-save" class="slate-btn slate-btn--green">Save BOM</button>
                <button id="bom-clone" class="slate-btn">Clone / Revise</button>
                <button id="bom-create-quote" class="slate-btn slate-btn--ghost">Create Quote</button>
              </div>
            </div>
          </div>

          <div class="slate-card" style="margin-top:12px;">
            <div class="slate-row" style="justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
              <div class="slate-row" style="gap:8px; align-items:center;">
                <h3 style="margin:0;">Lines</h3>
                <button id="bom-add-line" class="slate-btn slate-btn--sm">+ Add Part</button>
                <datalist id="bom-sku-list"></datalist>
              </div>
              <div class="slate-row" style="gap:18px; flex-wrap:wrap;">
                <div><div class="slate-muted" style="font-size:12px;">Parts (Wholesale)</div><div><b>${money(t.partsW)}</b></div></div>
                <div><div class="slate-muted" style="font-size:12px;">Labor</div><div><b>${money(t.laborW)}</b></div></div>
                <div><div class="slate-muted" style="font-size:12px;">Shop</div><div><b>${money(t.shopW)}</b></div></div>
                <div><div class="slate-muted" style="font-size:12px;">Installed (Wholesale)</div><div><b>${money(t.installedW)}</b></div></div>
                <div><div class="slate-muted" style="font-size:12px;">Installed (Retail)</div><div><b>${money(t.installedR)}</b></div></div>
              </div>
            </div>

            <div style="overflow:auto; margin-top:10px;">
              <table class="slate-table">
                <thead>
                  <tr>
                    <th>SKU</th>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Unit (Wholesale)</th>
                    <th>Ext (Wholesale)</th>
                    <th>Unit (Retail)</th>
                    <th>Ext (Retail)</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  ${linesHtml || '<tr><td colspan="8" class="slate-muted">No parts lines yet. Click <b>Add Part</b>.</td></tr>'}
                </tbody>
              </table>
            </div>
          </div>
        `;

        wireEditor();
      }

      let skuSearchTimer = null;
      async function skuSearch(q){
        try {
          const res = await apiFetch('/wp-json/slate-ops/v1/pricing/products/search?q=' + encodeURIComponent(q) + '&limit=15');
          if (res && res.ok) {
            const dl = $('#bom-sku-list');
            if (!dl) return;
            dl.innerHTML = (res.products||[]).map(p => `<option value="${escapeAttr(p.sku)}">${escapeHtml(p.product_name||'')}</option>`).join('');
          }
        } catch(e){}
      }

      function wireEditor(){
        $('#bom-save').onclick = saveActive;
        $('#bom-clone').onclick = cloneActive;
        $('#bom-add-line').onclick = ()=>{
          state.active.lines.push({ line_type:'PART', sku:'', qty:1, unit_wholesale:0, unit_retail:0, product_name:'' });
          renderEditor();
        };

        $('#dealer_id').onchange = (e)=>{ state.activeDealerId = e.target.value; renderEditor(); };

        $('#install_hours').oninput = (e)=>{ state.active.bom.install_hours = Number(e.target.value||0); renderEditor(); };
        $('#shop_supply_units').oninput = (e)=>{ state.active.bom.shop_supply_units = Number(e.target.value||0); renderEditor(); };

        $('#bom_create_quote') && ($('#bom_create_quote').onclick = ()=>{});
        const quoteBtn = $('#bom-create-quote');
        if (quoteBtn) {
          quoteBtn.onclick = async ()=>{
            const bom_id = state.active.bom.id;
            const dealer_id = state.activeDealerId;
            if (!bom_id || !dealer_id) { alert('Select a BOM and dealer.'); return; }
            try {
              const res = await apiFetch('/wp-json/slate-ops/v1/pricing/quotes/from-bom', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ bom_id, dealer_id, qty: 1 })
              });
              if (res && res.ok) {
                alert('Quote created: ' + res.quote_no);
              } else {
                alert('Quote failed: ' + (res && res.error ? res.error : 'Unknown error'));
              }
            } catch(e){
              alert('Quote failed. See console.');
              console.error(e);
            }
          };
        }

        // header fields
        $('#bom_no').oninput = e => { state.active.bom.bom_no = e.target.value; };
        $('#bom_name').oninput = e => { state.active.bom.bom_name = e.target.value; };
        $('#bom_rev').oninput = e => { state.active.bom.revision = e.target.value; };

        // rows
        elEditor.querySelectorAll('.slate-table tbody tr[data-idx]').forEach(tr=>{
          const idx = Number(tr.getAttribute('data-idx'));
          const skuInput = tr.querySelector('.bom-sku');
          const qtyInput = tr.querySelector('.bom-qty');
          const delBtn = tr.querySelector('.bom-del');

          if (skuInput) {
            skuInput.oninput = (e)=>{
              const v = e.target.value || '';
              state.active.lines[idx].sku = v;
              clearTimeout(skuSearchTimer);
              skuSearchTimer = setTimeout(()=>skuSearch(v), 200);
            };
            skuInput.onblur = (e)=>{
              const v = (e.target.value||'').trim();
              state.active.lines[idx].sku = v;
              if (v) lookupSkuIntoLine(idx, v);
            };
          }
          if (qtyInput) {
            qtyInput.oninput = (e)=>{
              state.active.lines[idx].qty = Number(e.target.value||0);
              renderEditor();
            };
          }
          if (delBtn) {
            delBtn.onclick = ()=>{
              state.active.lines.splice(idx,1);
              renderEditor();
            };
          }
        });
      }


function suggestCloneBomNo(bomNo){
      if (!bomNo) return '';
      if (/-CL\d+$/i.test(bomNo)){
        const m = bomNo.match(/-CL(\d+)$/i);
        const n = Number(m[1]||1)+1;
        return bomNo.replace(/-CL\d+$/i, `-CL${n}`);
      }
      return `${bomNo}-CL1`;
    }

    function suggestNextRevisionBomNo(bomNo){
      if (!bomNo) return '';
      // If already has -R#, increment
      const m = bomNo.match(/(.*)-R(\d+)$/i);
      if (m){
        const base = m[1];
        const n = Number(m[2]||1)+1;
        return `${base}-R${n}`;
      }
      return `${bomNo}-R2`;
    }

    async function saveActive(){
      if(!state.activeId || !state.active) return;

      const payload = {
        bom_no: ($('#bom_no')?.value || '').trim(),
        name: ($('#bom_name')?.value || '').trim(),
        revision: ($('#bom_rev')?.value || '').trim(),
        install_hours: ($('#install_hours')?.value || '').trim(),
        shop_supply_units: ($('#shop_supply_units')?.value || '').trim(),
        lines: (Array.isArray(state.active.lines) ? state.active.lines : []).map((l,i)=>({
          sku: (l.sku||'').trim(),
          qty: Number(l.qty||1),
          sort_order: i+1
        }))
      };

      try{
        const res = await api.req('/boms/' + encodeURIComponent(state.activeId), {
          method:'POST',
          body: JSON.stringify(payload)
        });
        if (res && res.ok === false){
          alert(res.error || 'Save failed');
          return;
        }
        await refreshList();
        await openBom(state.activeId);
        toast('Saved');
      }catch(err){
        alert((err && err.message) ? err.message : 'Save failed');
      }
    }

    async function cloneActive(){
      if(!state.activeId || !state.active) return;
      const currentNo = state.active.bom?.bom_no || '';
      const suggested = suggestCloneBomNo(currentNo);
      const newBomNo = (prompt('New BOM #', suggested) || '').trim();
      if(!newBomNo) return;

      try{
        const res = await api.req('/boms/' + encodeURIComponent(state.activeId) + '/clone', {
          method:'POST',
          body: JSON.stringify({ new_bom_no: newBomNo })
        });
        if (!res || res.ok === false){
          alert((res && res.error) ? res.error : 'Clone failed');
          return;
        }
        const newId = res.new_id || res.id;
        await refreshList();
        if(newId){ await openBom(newId); }
        toast('Cloned');
      }catch(err){
        alert((err && err.message) ? err.message : 'Clone failed');
      }
    }

    async function openBom(id){
      state.activeId = id;
      await ensureDealersLoaded();
      try{
        const res = await api.req(`/boms/${id}`);
        if (!res || !res.ok){
          const msg = (res && res.error) ? res.error : 'Failed to load BOM';
          elEditor.innerHTML = `<h2 style="margin:0 0 8px;">BOM Builder</h2><div style="color:#b00;">${escapeHtml(msg)}</div>`;
          return;
        }
        // Normalize shape for the editor.
        // Some older responses may return just the BOM object without wrappers.
        const bom = res.bom || res;
        const lines = Array.isArray(res.lines) ? res.lines : [];
        state.active = { bom, lines };
        // Legacy alias for older code paths.
        state.lines = lines;
        renderList();
        renderEditor();
      }catch(err){
        const msg = (err && err.message) ? err.message : 'Failed to load BOM';
        elEditor.innerHTML = `<h2 style="margin:0 0 8px;">BOM Builder</h2><div style="color:#b00;">${escapeHtml(msg)}</div>`;
      }
    }

    async function refreshList(){
      try{
        const res = await api.req('/boms');
        if (!res || !res.ok){
          const msg = (res && res.error) ? res.error : 'Failed to load BOM list';
          elList.innerHTML = `<div style="padding:10px; color:#b00;">${escapeHtml(msg)}</div>`;
          return;
        }
        state.boms = res.boms || [];
        renderList();
      }catch(err){
        const msg = (err && err.message) ? err.message : 'Failed to load BOM list';
        elList.innerHTML = `<div style="padding:10px; color:#b00;">${escapeHtml(msg)}</div>`;
      }
    }

    function newBomTemplate(){
      return {
        bom: { bom_no:'', name:'', revision:'', install_hours:'0', shop_supply_units:'0' },
        lines: []
      };
    }

    elNew.onclick = ()=>{
      state.activeId = 0;
      state.active = newBomTemplate();
      state.lines = [];
      renderList();
      renderEditor();
    };

    elSearch.oninput = renderList;

    // load list on entry
    await refreshList();
  }


async function render(){
    if (state.timerInterval) { clearInterval(state.timerInterval); state.timerInterval = null; }
    const r = state.route;
    const c = slateOpsSettings.user.caps || {};
    const canExecutive = !!c.admin || !!c.supervisor;
    const canAdminTools = canExecutive;
    setActiveNav(
      r.startsWith('/job/') ? '/admin' :
      r.startsWith('/jobs') ? '/admin' :
      r.startsWith('/new') ? '/admin' :
      r.startsWith('/settings') ? '/settings' :
      r.startsWith('/supervisor') ? '/admin' :
      r.startsWith('/cs') ? '/cs' :
      r.startsWith('/tech') ? '/tech' :
      r.startsWith('/admin') ? '/admin' :
      r.startsWith('/exec') ? '/exec' :
      r.startsWith('/qc') ? '/admin' :
      r.startsWith('/schedule') ? '/schedule' :
      r.startsWith('/bom') ? '/admin' :
      '/'
    );
    setPageTitle(
      r.startsWith('/exec')       ? 'Executive'        :
      r.startsWith('/cs')         ? 'Customer Service' :
      r.startsWith('/tech')       ? 'Tech'             :
      r.startsWith('/qc')         ? 'QC Queue'         :
      r.startsWith('/admin')      ? 'Admin'            :
      r.startsWith('/job/')       ? 'Job Detail'       :
      r.startsWith('/jobs')       ? 'Jobs'             :
      r.startsWith('/new')        ? 'Create Job'       :
      r.startsWith('/supervisor') ? 'Supervisor'       :
      r.startsWith('/schedule')   ? 'Schedule'         :
      r.startsWith('/bom')        ? 'BOM Builder'      :
      r.startsWith('/settings')   ? 'Settings'         :
      'Executive'
    );

    try{
      if (r === '/' || r === '') {
        if (canExecutive) { window.history.replaceState({}, '', '/ops/exec'); state.route='/exec'; }
        else if (c.cs) { window.history.replaceState({}, '', '/ops/cs'); state.route='/cs'; }
        else if (c.tech) { window.history.replaceState({}, '', '/ops/tech'); state.route='/tech'; }
        else { window.history.replaceState({}, '', '/ops/exec'); state.route='/exec'; }
        return await render();
      }
      if (r.startsWith('/exec')) {
        if (!canExecutive) {
          const fallback = c.cs ? '/ops/cs' : '/ops/tech';
          const fallbackRoute = c.cs ? '/cs' : '/tech';
          window.history.replaceState({}, '', fallback);
          state.route = fallbackRoute;
          return await render();
        }
        return await loadExecutive();
      }
      if (r.startsWith('/cs')) return await loadCS();
      if (r.startsWith('/tech')) return await loadTech();
      if (r.startsWith('/qc')) return await loadQC();
      if (r.startsWith('/admin')) {
        if (!canAdminTools) {
          const fallback = c.cs ? '/ops/cs' : '/ops/tech';
          const fallbackRoute = c.cs ? '/cs' : '/tech';
          window.history.replaceState({}, '', fallback);
          state.route = fallbackRoute;
          return await render();
        }
        return await loadAdmin();
      }
      if (r === '/' || r === '') return await loadDashboard();
      if (r.startsWith('/jobs')) return await loadJobsList();
      if (r.startsWith('/job/')) {
        const id = r.split('/')[2];
        return await loadJobDetail(id);
      }
      if (r.startsWith('/new')) return await loadCreateJob();
      if (r.startsWith('/supervisor')) return await loadSupervisor();
      if (r.startsWith('/schedule')) return await loadSchedule();
      if (r.startsWith('/bom')) return await loadBOM();
      if (r.startsWith('/settings')) {
        if (!canAdminTools) {
          const fallback = c.cs ? '/ops/cs' : '/ops/tech';
          const fallbackRoute = c.cs ? '/cs' : '/tech';
          window.history.replaceState({}, '', fallback);
          state.route = fallbackRoute;
          return await render();
        }
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
