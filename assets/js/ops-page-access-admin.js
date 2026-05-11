(function () {
  function ready(fn){ if(document.readyState!=='loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }
  function apiRoot(){ return (window.slateOpsSettings && window.slateOpsSettings.api && window.slateOpsSettings.api.root) || '/wp-json/slate-ops/v1'; }
  function nonce(){ return (window.slateOpsSettings && window.slateOpsSettings.api && window.slateOpsSettings.api.nonce) || ''; }
  const roles = ['admin','supervisor','cs','tech','executive'];
  const fallbackPages = [
    ['executive','Executive'],
    ['cs-dashboard','CS Dashboard'],
    ['tech','Tech'],
    ['schedule','Schedule'],
    ['purchasing','Purchasing'],
    ['resource-hub','Resource hub'],
    ['admin','Admin'],
    ['settings','Settings'],
    ['monitor','Monitor'],
  ];

  async function req(path, opts){
    const res = await fetch(apiRoot()+path, Object.assign({headers:{'Content-Type':'application/json','X-WP-Nonce':nonce()}}, opts||{}));
    const data = await res.json();
    if(!res.ok) throw new Error((data && data.message) || 'Request failed');
    return data;
  }

  ready(function(){
    if (!location.pathname.includes('/ops/admin')) return;
    const mount = document.querySelector('#ops-view .bg-white.rounded-xl.shadow-sm.overflow-hidden.mb-8');
    if (!mount) return;
    const panel = document.createElement('div');
    panel.className = 'bg-white rounded-xl shadow-sm overflow-hidden mb-8';
    panel.innerHTML = '<div class="px-6 py-4 border-b border-slate-100"><h2 class="font-bold text-sm text-slate-700 uppercase tracking-wide">Feature availability</h2><p class="text-xs text-slate-500 mt-1">Turn whole Slate Ops surfaces on or off. Disabled features stay hidden and blocked even if a role has page access.</p></div><div class="px-6 py-4"><div id="ops-feature-list" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 18px;margin-bottom:12px;"></div></div><div class="px-6 py-4 border-t border-slate-100"><h2 class="font-bold text-sm text-slate-700 uppercase tracking-wide">Page Access by Role</h2><p class="text-xs text-slate-500 mt-1">Controls page visibility for enabled features. It does not grant action permissions; capabilities still gate actions inside each page.</p><div style="display:flex;gap:12px;align-items:center;margin:12px 0;"><label class="text-xs font-bold text-slate-500">Role</label><select id="ops-page-access-role" class="border border-slate-200 rounded-md px-3 py-2 text-sm"></select></div><div id="ops-page-access-list" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 18px;margin-bottom:12px;"></div><div style="display:flex;gap:8px;"><button id="ops-page-access-save" class="bg-[#404f4b] text-white text-xs font-bold px-3 py-2 rounded">Save</button><button id="ops-page-access-reset" class="border border-slate-300 text-xs font-bold px-3 py-2 rounded">Reset to Defaults</button><span id="ops-page-access-msg" class="text-xs text-slate-500" style="align-self:center;"></span></div></div>';
    mount.parentNode.insertBefore(panel, mount.nextSibling);

    const roleSel = panel.querySelector('#ops-page-access-role');
    const featureList = panel.querySelector('#ops-feature-list');
    const list = panel.querySelector('#ops-page-access-list');
    const msg = panel.querySelector('#ops-page-access-msg');
    let state = {roles:{}, defaults:{}, features:{}, feature_defaults:{}, page_labels:{}};
    roles.forEach(r=>{ const o=document.createElement('option'); o.value=r; o.textContent=r.charAt(0).toUpperCase()+r.slice(1); roleSel.appendChild(o); });

    function pageRows() {
      const labels = state.page_labels || {};
      return fallbackPages.map(([slug,label]) => [slug, labels[slug] || label]);
    }

    function renderFeatures() {
      const features = state.features || {};
      featureList.innerHTML = '';
      pageRows().forEach(([slug,label])=>{
        const row = document.createElement('label');
        row.style.display='flex'; row.style.gap='8px'; row.style.alignItems='center'; row.style.fontSize='13px';
        const locked = slug === 'admin' || slug === 'settings';
        row.innerHTML = '<input type="checkbox" data-feature="'+slug+'" '+(features[slug]?'checked':'')+(locked?' disabled':'')+'> <span>'+label+'</span>';
        featureList.appendChild(row);
      });
    }

    function renderChecks() {
      const role = roleSel.value;
      const allowed = new Set(state.roles[role] || []);
      const features = state.features || {};
      list.innerHTML = '';
      pageRows().forEach(([slug,label])=>{
        const row = document.createElement('label');
        row.style.display='flex'; row.style.gap='8px'; row.style.alignItems='center'; row.style.fontSize='13px';
        const locked = role==='admin' && (slug==='admin' || slug==='settings');
        const disabled = locked || !features[slug];
        const suffix = !features[slug] ? ' <span style="color:#94a3b8;">(off)</span>' : '';
        row.innerHTML = '<input type="checkbox" data-slug="'+slug+'" '+(allowed.has(slug)?'checked':'')+(disabled?' disabled':'')+'> <span>'+label+suffix+'</span>';
        list.appendChild(row);
      });
    }

    featureList.addEventListener('change', function(event){
      const input = event.target.closest('[data-feature]');
      if (!input) return;
      state.features[input.getAttribute('data-feature')] = input.checked;
      renderChecks();
    });

    panel.querySelector('#ops-page-access-save').addEventListener('click', async function(){
      const role = roleSel.value;
      const checked = Array.from(list.querySelectorAll('input[type=\"checkbox\"]:checked')).map(i=>i.getAttribute('data-slug'));
      state.roles[role] = checked;
      if (role==='admin') ['admin','settings'].forEach(s=>{ if(!state.roles[role].includes(s)) state.roles[role].push(s); });
      msg.textContent='Saving...';
      try {
        const res = await req('/page-access', {method:'POST', body:JSON.stringify({roles:state.roles, features:state.features})});
        state.roles = res.roles || state.roles;
        state.features = res.features || state.features;
        msg.textContent='Saved';
        renderFeatures();
        renderChecks();
      } catch(e){ msg.textContent=e.message; }
    });

    panel.querySelector('#ops-page-access-reset').addEventListener('click', function(){
      state.roles = JSON.parse(JSON.stringify(state.defaults || {}));
      state.features = JSON.parse(JSON.stringify(state.feature_defaults || {}));
      renderFeatures();
      renderChecks();
      msg.textContent='Defaults loaded (not saved)';
    });
    roleSel.addEventListener('change', renderChecks);

    req('/page-access').then(function(data){
      state = data || state;
      roleSel.value = 'admin';
      renderFeatures();
      renderChecks();
    }).catch(function(e){ msg.textContent = e.message; });
  });
})();
