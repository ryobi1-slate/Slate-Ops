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
    assign(id, userId){ return this.req('/jobs/' + id + '/assign', {method:'POST', body: JSON.stringify({assigned_user_id: userId})}); },
    schedule(id, payload){ return this.req('/jobs/' + id + '/schedule', {method:'POST', body: JSON.stringify(payload)}); },
    setStatus(id, status, note=''){ return this.req('/jobs/' + id + '/status', {method:'POST', body: JSON.stringify({status, note})}); },
    timeStart(job_id, reason, note){ return this.req('/time/start', {method:'POST', body: JSON.stringify({job_id, reason, note})}); },
    timeStop(){ return this.req('/time/stop', {method:'POST'}); },
    timeActive(){ return this.req('/time/active'); },
    correction(payload){ return this.req('/time/correction', {method:'POST', body: JSON.stringify(payload)}); },
    supervisorQueues(){ return this.req('/supervisor/queues'); }
  };

  const state = {
    route: '/',
    jobs: [],
    settings: null,
    active: null,
  };

  function setActiveNav(route){
    $$('.ops-nav-link').forEach(a => {
      const r = a.getAttribute('data-route');
      a.classList.toggle('active', r === route);
    });
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
    const job = await api.job(id);
    const caps = slateOpsSettings.user.caps || {};
    const isSupervisor = !!caps.supervisor || !!caps.admin;
    const isCS = !!caps.cs || !!caps.admin;

    const t = job.time || {approved_minutes_total:0, pending_minutes_total:0, by_tech:[]};
    const estHrs = job.clickup_estimate_ms ? (job.clickup_estimate_ms / 1000 / 60 / 60) : 0;
    const actHrs = t.approved_minutes_total / 60;
    const varHrs = actHrs - estHrs;

    view(`
      <div class="card">
        <h2>Job</h2>

        <div class="row">
          <div class="kpi">
            <div class="label">SO#</div>
            <div class="value mono" style="font-size:18px;">${job.so_number || '—'}</div>
          </div>
          <div class="kpi">
            <div class="label">Status</div>
            <div class="value" style="font-size:18px;">${fmtStatus(job.status)}</div>
          </div>
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

        <div class="row" style="margin-top:10px;">
          <div style="flex:1 1 300px;">
            <div class="label" style="margin-bottom:6px;">VIN</div>
            <div class="mono">${job.vin || ''}</div>
          </div>
          <div style="flex:1 1 300px;">
            <div class="label" style="margin-bottom:6px;">Customer</div>
            <div>${escapeHtml(job.customer_name || '')}</div>
          </div>
          <div style="flex:1 1 300px;">
            <div class="label" style="margin-bottom:6px;">Scheduled</div>
            <div>${job.scheduled_start || ''} - ${job.scheduled_finish || ''}</div>
          </div>
        </div>

        <div class="row" style="margin-top:14px;">
          <button class="btn" id="start-btn">Start</button>
          <button class="btn secondary" id="stop-btn">Stop</button>
          <button class="btn secondary" id="fix-btn">Fix Time</button>
          ${isCS ? `<button class="btn secondary" id="so-btn">Set SO#</button>` : ``}
          ${isSupervisor ? `<button class="btn secondary" id="qc-btn">QC Approve</button>` : ``}
        </div>
      </div>

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

  async function loadSupervisor(){
    const data = await api.supervisorQueues();
    const pending = data.pending_corrections || [];
    const unassigned = data.unassigned_segments || [];

    view(`
      <div class="card">
        <h2>Supervisor</h2>
        <div class="row">
          <div class="kpi"><div class="label">Pending Corrections</div><div class="value">${pending.length}</div></div>
          <div class="kpi"><div class="label">Unassigned Segments</div><div class="value">${unassigned.length}</div></div>
        </div>
      </div>

      <div class="card">
        <h2>Pending Corrections</h2>
        <table class="table">
          <thead><tr><th>SO#</th><th>Tech</th><th>Start</th><th>End</th><th>Note</th></tr></thead>
          <tbody>
            ${pending.map(p => `
              <tr>
                <td><button class="btn secondary" data-open-job="${p.job_id}">Open</button></td>
                <td>${escapeHtml(p.user_name||'')}</td>
                <td>${p.start_ts}</td>
                <td>${p.end_ts}</td>
                <td>${escapeHtml(p.note||'')}</td>
              </tr>
            `).join('') || `<tr><td colspan="5">None.</td></tr>`}
          </tbody>
        </table>
      </div>

      <div class="card">
        <h2>Unassigned Time</h2>
        <table class="table">
          <thead><tr><th>Job</th><th>Tech</th><th>Start</th><th>End</th><th>Reason</th></tr></thead>
          <tbody>
            ${unassigned.map(u => `
              <tr>
                <td><button class="btn secondary" data-open-job="${u.job_id}">Open</button></td>
                <td>${escapeHtml(u.user_name||'')}</td>
                <td>${u.start_ts}</td>
                <td>${u.end_ts || ''}</td>
                <td>${escapeHtml(u.reason||'')}</td>
              </tr>
            `).join('') || `<tr><td colspan="5">None.</td></tr>`}
          </tbody>
        </table>
      </div>
    `);

    bindJobsTable();
  }

  async function loadSettings(){
    const s = await api.settings();
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
        <div style="margin-top:10px;">
          <div class="label" style="margin-bottom:6px;">Dealers (one per line)</div>
          <textarea class="input" id="dealers" rows="6" placeholder="Dealer name">${escapeHtml((s.dealers || []).join('\n'))}</textarea>
        </div>
        <div style="margin-top:10px;">
          <div class="label" style="margin-bottom:6px;">Sales People (one per line)</div>
          <textarea class="input" id="sales_people" rows="6" placeholder="Sales person name">${escapeHtml((s.sales_people || []).join('\n'))}</textarea>
        </div>
        <div style="margin-top:12px;">
          <button class="btn" id="save_settings">Save</button>
        </div>
      </div>
    `);

    $('#save_settings').onclick = async () => {
      try{
        const payload = {
          shift_start: $('#shift_start').value.trim(),
          shift_end: $('#shift_end').value.trim(),
          lunch_minutes: parseInt($('#lunch_minutes').value, 10),
          break_minutes: parseInt($('#break_minutes').value, 10),
          dealers: $('#dealers').value,
          sales_people: $('#sales_people').value,
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
    <div class="label" style="margin-bottom:8px;">Create Job</div>

    <div class="row">
      <div style="flex:1 1 220px;">
        <div class="label" style="margin-bottom:6px;">SO#</div>
        <input class="input" id="so_number" placeholder="S-ORD123456" />
        <div class="field-error" data-error-for="so_number"></div>
      </div>
      <div style="flex:1 1 220px;">
        <div class="label" style="margin-bottom:6px;">Customer</div>
        <input class="input" id="customer_name" placeholder="Customer name" />
        <div class="field-error" data-error-for="customer_name"></div>
      </div>
      <div style="flex:1 1 220px;">
        <div class="label" style="margin-bottom:6px;">Dealer</div>
        <select class="input" id="dealer_name">
          <option value="">Select dealer</option>
          ${dealerOptions}
        </select>
        <div class="field-error" data-error-for="dealer_name"></div>
      </div>
    </div>

    <div class="row" style="margin-top:10px;">
      <div style="flex:1 1 220px;">
        <div class="label" style="margin-bottom:6px;">VIN Last 8</div>
        <input class="input" id="vin_last8" maxlength="8" placeholder="A1B2C3D4" />
        <div class="field-error" data-error-for="vin_last8"></div>
      </div>
    </div>

    <div class="row" style="margin-top:10px;">
      <div style="flex:1 1 220px;">
        <div class="label" style="margin-bottom:6px;">Job Type</div>
        <select class="input" id="job_type">
          <option value="UPFIT">UPFIT</option>
          <option value="COMMERCIAL_UPFIT">COMMERCIAL_UPFIT</option>
          <option value="COMMERCIAL_BUILD">COMMERCIAL_BUILD</option>
          <option value="RV_BUILD">RV_BUILD</option>
          <option value="RV_UPFIT">RV_UPFIT</option>
          <option value="PARTS_ONLY">PARTS_ONLY</option>
          <option value="SERVICE">SERVICE</option>
          <option value="WARRANTY">WARRANTY</option>
        </select>
        <div class="field-error" data-error-for="job_type"></div>
      </div>
      <div style="flex:1 1 220px;">
        <div class="label" style="margin-bottom:6px;">Created From</div>
        <input class="input" value="Manual" disabled />
      </div>
    </div>

    <div class="row" style="margin-top:10px;">
      <div style="flex:1 1 220px;">
        <div class="label" style="margin-bottom:6px;">Estimated Hours</div>
        <input class="input" id="estimated_hours" type="number" min="0" step="0.1" />
        <div class="field-error" data-error-for="estimated_hours"></div>
      </div>
      <div style="flex:1 1 220px;">
        <div class="label" style="margin-bottom:6px;">Parts Status</div>
        <select class="input" id="parts_status">
          <option value="NOT_READY" selected>NOT_READY</option>
          <option value="PARTIAL">PARTIAL</option>
          <option value="READY">READY</option>
          <option value="HOLD">HOLD</option>
        </select>
        <div class="field-error" data-error-for="parts_status"></div>
      </div>
      <div style="flex:1 1 220px;">
        <div class="label" style="margin-bottom:6px;">Requested Completion Date</div>
        <input class="input" id="requested_date" type="date" />
        <div class="field-error" data-error-for="requested_date"></div>
      </div>
    </div>

    <div class="row" style="margin-top:10px;">
      <div style="flex:1 1 220px;">
        <div class="label" style="margin-bottom:6px;">Sales Person</div>
        <select class="input" id="sales_person">
          <option value="">Select sales person</option>
          ${salesOptions}
        </select>
        <div class="field-error" data-error-for="sales_person"></div>
      </div>
    </div>

    <div class="row" style="margin-top:10px;">
      <div style="flex:1 1 100%;">
        <div class="label" style="margin-bottom:6px;">Notes</div>
        <textarea class="input" id="notes" placeholder="Notes"></textarea>
      </div>
    </div>

    <div class="row" style="margin-top:12px;">
      <button class="btn" id="create_btn">Create</button>
      <div class="field-error" data-error-for="general"></div>
    </div>
  `;

  host.querySelector('#create_btn').onclick = async () => {
    clearCreateJobErrors(host);

    const notesInput = host.querySelector('#notes').value.trim();
    const payload = {
      so_number: host.querySelector('#so_number').value.trim(),
      customer_name: host.querySelector('#customer_name').value.trim(),
      dealer_name: host.querySelector('#dealer_name').value.trim(),
      vin_last8: host.querySelector('#vin_last8').value.trim().toUpperCase(),
      job_type: host.querySelector('#job_type').value,
      created_from: 'manual',
      estimated_hours: host.querySelector('#estimated_hours').value.trim(),
      parts_status: host.querySelector('#parts_status').value,
      requested_date: host.querySelector('#requested_date').value,
      sales_person: host.querySelector('#sales_person').value.trim(),
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

async function loadCS(){
  const jobsResp = await api.jobs('?limit=300&so_missing=1');
  const needs = (jobsResp.jobs||[]).filter(j => (j.status||'').toUpperCase() === 'UNSCHEDULED' || (j.status||'').toUpperCase() === 'READY_FOR_SCHEDULING');
  view(`
    <div class="card">
      <div class="row">
        <div style="flex:1 1 520px;">
          <h2 style="margin:0;">Customer Service</h2>
          <div class="muted">Enter SO# and create jobs fast. No scheduling here.</div>
        </div>
        <div style="display:flex; gap:10px; align-items:flex-end;">
          ${kpi('Needs SO#', needs.length)}
        </div>
      </div>
    </div>

    <div class="card">
      <div class="label" style="margin-bottom:8px;">Needs SO#</div>
      <table class="table">
        <thead><tr>
          <th style="min-width:220px;">Customer</th>
          <th style="min-width:90px;">VIN</th>
          <th style="min-width:180px;">Dealer</th>
          <th style="min-width:200px;">SO#</th>
          <th style="min-width:90px;"></th>
        </tr></thead>
        <tbody>
          ${needs.map(j=>`
            <tr data-id="${j.job_id}">
              <td>${escapeHtml(j.customer_name||'')}</td>
              <td>${escapeHtml((j.vin||'').slice(-6))}</td>
              <td>${escapeHtml(j.dealer_name||'')}</td>
              <td><input class="input so" placeholder="S-ORD101350" /></td>
              <td><button class="btn small-btn save-so">Save</button></td>
            </tr>
          `).join('') || `<tr><td colspan="5">No jobs need SO#.</td></tr>`}
        </tbody>
      </table>
    </div>

    <div class="card" id="cs-create"></div>
  `);

  await loadCreateJobInto('#cs-create');

  $$('.save-so').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      const tr = btn.closest('tr');
      const id = parseInt(tr.dataset.id,10);
      const so = $('.so', tr).value.trim();
      if(!so){ alert('SO# required'); return; }
      btn.disabled=true;
      const old=btn.textContent;
      btn.textContent='Saving…';
      try{
        await api.setSO(id, so);
        btn.textContent='Saved';
        setTimeout(()=>router(), 250);
      }catch(e){
        alert(e.message);
        btn.textContent=old;
      }finally{
        btn.disabled=false;
      }
    });
  });
}

async function loadTech(){
  const [activeResp, jobsResp] = await Promise.all([ api.timeActive(), api.jobs('?limit=250') ]);
  const active = activeResp.active;
  const jobs = jobsResp.jobs || [];
  view(`
    <div class="card">
      <h2 style="margin:0;">Tech</h2>
      <div class="muted">Start/Stop job. Fix time if you forgot. One active timer per tech.</div>
    </div>

    <div class="card">
      <div class="label" style="margin-bottom:8px;">My Active Timer</div>
      ${active ? `
        <div class="row" style="align-items:center;">
          <div style="flex:1 1 320px;">
            <div><strong>${escapeHtml(active.so_number || '')}</strong> <span class="muted">${escapeHtml((active.vin||'').slice(-6))}</span></div>
            <div class="small">Started: ${escapeHtml(active.start_at || '')}</div>
          </div>
          <button class="btn" id="stop-now">Stop</button>
        </div>
      ` : `<div class="muted">No active job.</div>`}
    </div>

    <div class="card">
      <div class="label" style="margin-bottom:6px;">All Jobs</div>
      <input class="input" id="tech-search" placeholder="Search SO#, VIN, customer" />
      <div style="margin-top:10px;">
        <table class="table" id="tech-table">
          <thead><tr>
            <th style="min-width:140px;">SO#</th>
            <th style="min-width:90px;">VIN</th>
            <th style="min-width:220px;">Customer</th>
            <th style="min-width:160px;">Status</th>
            <th style="min-width:90px;"></th>
          </tr></thead>
          <tbody>
            ${jobs.map(j=>`
              <tr>
                <td>${escapeHtml(j.so_number||'')}</td>
                <td>${escapeHtml((j.vin||'').slice(-6))}</td>
                <td>${escapeHtml(j.customer_name||'')}</td>
                <td><span class="badge ${badgeClass(j.status)}">${escapeHtml(fmtStatus(j.status))}</span></td>
                <td><a class="btn secondary small-btn" href="/ops/job/${j.job_id}" data-link>Open</a></td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    </div>
  `);

  if (active){
    $('#stop-now').onclick = async ()=>{ try{ await api.timeStop(); router(); }catch(e){ alert(e.message);} };
  }

  const search = $('#tech-search');
  const rows = $$('#tech-table tbody tr');
  search.addEventListener('input', ()=>{
    const q = search.value.trim().toLowerCase();
    rows.forEach(r=>{ r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none'; });
  });
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

async function loadAdmin(){
  const usersResp = await api.users();
  const users = usersResp.users || [];
  view(`
    <div class="card">
      <h2 style="margin:0;">Admin</h2>
      <div class="muted">Settings + user visibility. Role changes happen in WP Users.</div>
    </div>

    <div class="card">
      <div class="label" style="margin-bottom:8px;">Quick Links</div>
      <div class="row">
        <a class="btn secondary" href="/wp-admin/users.php">WP Users</a>
        <a class="btn secondary" href="/ops/settings" data-link>Ops Settings</a>
        <a class="btn secondary" href="/ops/exec" data-link>Executive Dashboard</a>
      </div>
    </div>

    <div class="card">
      <div class="label" style="margin-bottom:8px;">Users</div>
      <table class="table">
        <thead><tr><th>Name</th><th>Email</th></tr></thead>
        <tbody>
          ${users.slice(0,200).map(u=>`
            <tr><td>${escapeHtml(u.name)}</td><td>${escapeHtml(u.email)}</td></tr>
          `).join('')}
        </tbody>
      </table>
    </div>
  `);
}

  async function render(){
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
