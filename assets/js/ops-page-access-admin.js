(function () {
  function apiRoot(){ return (window.slateOpsSettings && window.slateOpsSettings.api && window.slateOpsSettings.api.root) || '/wp-json/slate-ops/v1'; }
  function nonce(){ return (window.slateOpsSettings && window.slateOpsSettings.api && window.slateOpsSettings.api.nonce) || ''; }
  function isAdmin(){ return !!(window.slateOpsSettings && window.slateOpsSettings.user && window.slateOpsSettings.user.caps && window.slateOpsSettings.user.caps.admin); }
  const MOUNT_SELECTOR = '#ops-view .bg-white.rounded-xl.shadow-sm.overflow-hidden.mb-8';
  const PANEL_ID = 'ops-page-access-panel';
  const roles = ['admin','supervisor','cs','tech','executive'];
  const pages = [
    ['executive','Executive'],
    ['cs','Customer Service'],
    ['tech','Tech'],
    ['schedule','Schedule'],
    ['purchasing','Purchasing'],
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

  function mountPanel(mount) {
    if (document.getElementById(PANEL_ID)) return;
    const panel = document.createElement('div');
    panel.id = PANEL_ID;
    panel.className = 'bg-white rounded-xl shadow-sm overflow-hidden mb-8';
    panel.innerHTML = '<div class="px-6 py-4 border-b border-slate-100"><h2 class="font-bold text-sm text-slate-700 uppercase tracking-wide">Page Access by Role</h2><p class="text-xs text-slate-500 mt-1">Control which Slate Ops pages are visible and accessible for each role.</p></div><div class="px-6 py-4"><div style="display:flex;gap:12px;align-items:center;margin-bottom:12px;"><label class="text-xs font-bold text-slate-500">Role</label><select id="ops-page-access-role" class="border border-slate-200 rounded-md px-3 py-2 text-sm"></select></div><div id="ops-page-access-list" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 18px;margin-bottom:12px;"></div><div style="display:flex;gap:8px;"><button id="ops-page-access-save" class="bg-[#404f4b] text-white text-xs font-bold px-3 py-2 rounded">Save</button><button id="ops-page-access-reset" class="border border-slate-300 text-xs font-bold px-3 py-2 rounded">Reset to Defaults</button><span id="ops-page-access-msg" class="text-xs text-slate-500" style="align-self:center;"></span></div></div>';
    mount.parentNode.insertBefore(panel, mount.nextSibling);

    const roleSel = panel.querySelector('#ops-page-access-role');
    const list = panel.querySelector('#ops-page-access-list');
    const msg = panel.querySelector('#ops-page-access-msg');
    let state = {roles:{}, defaults:{}};
    roles.forEach(r=>{ const o=document.createElement('option'); o.value=r; o.textContent=r.charAt(0).toUpperCase()+r.slice(1); roleSel.appendChild(o); });

    function renderChecks() {
      const role = roleSel.value;
      const allowed = new Set(state.roles[role] || []);
      list.innerHTML = '';
      pages.forEach(([slug,label])=>{
        const row = document.createElement('label');
        row.style.display='flex'; row.style.gap='8px'; row.style.alignItems='center'; row.style.fontSize='13px';
        const dis = role==='admin' && (slug==='admin' || slug==='settings');
        row.innerHTML = '<input type="checkbox" data-slug="'+slug+'" '+(allowed.has(slug)?'checked':'')+(dis?' disabled':'')+'> <span>'+label+'</span>';
        list.appendChild(row);
      });
    }

    panel.querySelector('#ops-page-access-save').addEventListener('click', async function(){
      const role = roleSel.value;
      const checked = Array.from(list.querySelectorAll('input[type="checkbox"]:checked')).map(i=>i.getAttribute('data-slug'));
      state.roles[role] = checked;
      if (role==='admin') ['admin','settings'].forEach(s=>{ if(!state.roles[role].includes(s)) state.roles[role].push(s); });
      msg.textContent='Saving...';
      try {
        const res = await req('/page-access', {method:'POST', body:JSON.stringify({roles:state.roles})});
        state.roles = res.roles || state.roles;
        msg.textContent='Saved';
        renderChecks();
      } catch(e){ msg.textContent=e.message; }
    });

    panel.querySelector('#ops-page-access-reset').addEventListener('click', function(){
      state.roles = JSON.parse(JSON.stringify(state.defaults || {}));
      renderChecks();
      msg.textContent='Defaults loaded (not saved)';
    });
    roleSel.addEventListener('change', renderChecks);

    req('/page-access').then(function(data){
      state = data || state;
      roleSel.value = 'admin';
      renderChecks();
    }).catch(function(e){ msg.textContent = e.message; });
  }

  // Wait for the React-rendered mount element. React mounts asynchronously,
  // so #ops-view is typically empty at DOMContentLoaded — observe until the
  // first card panel appears, then attach. Re-attach on SPA route changes
  // back to /ops/admin if the panel was removed by a re-render.
  function tryMount() {
    if (!isAdmin()) return false;
    if (!/\/ops\/admin(\/|$)/.test(location.pathname)) return false;
    if (document.getElementById(PANEL_ID)) return true;
    const mount = document.querySelector(MOUNT_SELECTOR);
    if (!mount) return false;
    mountPanel(mount);
    return true;
  }

  function startObserver() {
    if (tryMount()) return;
    const root = document.getElementById('ops-view') || document.body;
    const obs = new MutationObserver(function(){
      if (tryMount()) {
        // Keep observing — React may re-render and drop our panel; we'll re-attach.
        if (!/\/ops\/admin(\/|$)/.test(location.pathname)) obs.disconnect();
      }
    });
    obs.observe(root, {childList:true, subtree:true});
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startObserver);
  } else {
    startObserver();
  }
})();
