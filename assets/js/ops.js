(function(){
  const $ = (s, r=document)=>r.querySelector(s);

  async function req(path, opts={}){
    const url = slateOpsSettings.restRoot + path;
    const headers = Object.assign({
      'Content-Type':'application/json',
      'X-WP-Nonce': slateOpsSettings.nonce
    }, opts.headers||{});
    const res = await fetch(url, Object.assign({}, opts, {headers}));
    const txt = await res.text();
    let data = null;
    try{ data = txt ? JSON.parse(txt) : null; }catch(e){ data = {raw:txt}; }
    if(!res.ok) throw new Error((data && data.message) ? data.message : 'Request failed');
    return data;
  }

  function esc(s){
    return String(s||'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'","&#039;");
  }

  function setModal(open){
    const m = $('#modal');
    m.setAttribute('aria-hidden', open ? 'false' : 'true');
  }

  async function loadKPIs(){
    const statuses = ['UNSCHEDULED','SCHEDULED','IN_PROGRESS','PENDING_QC'];
    const ids = ['#kpi-unscheduled','#kpi-scheduled','#kpi-inprogress','#kpi-pendingqc'];

    for (let i=0; i<statuses.length; i++){
      try{
        const r = await req('/jobs?status=' + encodeURIComponent(statuses[i]) + '&limit=1');
        const count = (r && r.jobs) ? r.jobs.length : 0;
        // NOTE: v0 endpoint doesn't return total count - keep it simple for now
        $(ids[i]).textContent = count ? '1+' : '0';
      }catch(e){
        $(ids[i]).textContent = '—';
      }
    }
  }

  async function loadJobs(){
    const tbody = $('#jobs-body');
    tbody.innerHTML = '<tr><td colspan="9">Loading…</td></tr>';

    const r = await req('/jobs?limit=200');
    const jobs = (r && r.jobs) ? r.jobs : [];

    if (!jobs.length){
      tbody.innerHTML = '<tr><td colspan="9">No jobs.</td></tr>';
      return;
    }

    tbody.innerHTML = jobs.map(j => `
      <tr>
        <td>${esc(j.so_number || '')}</td>
        <td>${esc((j.vin||''))}</td>
        <td>${esc(j.customer_name || '')}</td>
        <td>${esc(j.status || '')}</td>
        <td>${esc(j.work_center || '')}</td>
        <td>${esc(j.estimated_minutes || '')}</td>
        <td>${esc(j.scheduled_start || '')}</td>
        <td>${esc(j.scheduled_finish || '')}</td>
        <td>${esc(j.updated_at || '')}</td>
      </tr>
    `).join('');
  }

  async function createJob(){
    const payload = {
      so_number: $('#m-so').value.trim(),
      vin: $('#m-vin').value.trim(),
      customer_name: $('#m-cust').value.trim(),
      dealer_name: $('#m-dealer').value.trim(),
      status: 'UNSCHEDULED',
      dealer_status: 'waiting'
    };
    if (!payload.customer_name){
      alert('Customer is required');
      return;
    }

    await req('/jobs', {method:'POST', body: JSON.stringify(payload)});
    setModal(false);
    $('#m-so').value = '';
    $('#m-vin').value = '';
    $('#m-cust').value = '';
    $('#m-dealer').value = '';
    await loadJobs();
    await loadKPIs();
  }

  document.addEventListener('DOMContentLoaded', () => {
    const btn = $('#btn-new-job');
    if (btn) btn.addEventListener('click', () => setModal(true));
    $('#m-cancel').addEventListener('click', () => setModal(false));
    $('#m-save').addEventListener('click', () => createJob().catch(e => alert(e.message)));

    loadJobs().catch(e => {
      $('#jobs-body').innerHTML = `<tr><td colspan="9">Error: ${esc(e.message)}</td></tr>`;
    });
    loadKPIs();
  });
})();
