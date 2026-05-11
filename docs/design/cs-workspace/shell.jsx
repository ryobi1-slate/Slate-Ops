// Slate Ops shell — left nav, top bar, page header. Only the CS Workspace tab is redesigned.
function SlateLogo() {
  return (
    <div style={{ display:'flex', alignItems:'center', gap: 0, fontFamily:'"Roboto Flex"', fontWeight: 700, fontSize: 20, letterSpacing:'0.04em' }}>
      <span style={{
        display:'inline-flex', alignItems:'center', justifyContent:'center',
        width: 22, height: 22, marginRight: 6,
        border:'1.5px solid #0a0a0a',
        transform:'rotate(45deg)',
      }} />
      <span>SLATE</span>
    </div>
  );
}

function NavItem({ icon, label, active }) {
  return (
    <a style={{
      display:'flex', alignItems:'center', gap: 10,
      padding: '8px 14px',
      color: active ? '#0a0a0a' : '#5b5b5b',
      background: active ? 'rgba(64,79,75,0.08)' : 'transparent',
      borderLeft: active ? '2px solid #404f4b' : '2px solid transparent',
      fontSize: 13, fontWeight: active ? 500 : 400,
      cursor:'pointer', textDecoration:'none',
      letterSpacing: '0.01em',
    }}>
      <span style={{ width: 16, color: active ? '#404f4b' : '#8a8a85' }}>{icon}</span>
      {label}
    </a>
  );
}

function Sidebar() {
  const items = [
    { label: 'Executive', icon: <Icon.Sort/> },
    { label: 'CS', icon: <Icon.Note/>, active: true },
    { label: 'CS (legacy)', icon: <Icon.Eye/> },
    { label: 'Tech', icon: <Icon.Bolt/> },
    { label: 'Schedule', icon: <Icon.Refresh/> },
    { label: 'Purchasing', icon: <Icon.Save/> },
    { label: 'Admin', icon: <Icon.Filter/> },
    { label: 'Settings', icon: <Icon.Sort/> },
    { label: 'Monitor', icon: <Icon.Eye/> },
  ];
  return (
    <aside style={{
      width: 200, flex: '0 0 200px',
      borderRight: '1px solid var(--rule)',
      background: '#fff',
      display:'flex', flexDirection:'column',
      paddingTop: 24,
    }}>
      <div className="label" style={{ padding: '0 14px 8px', color:'var(--arches)' }}>‹ Navigate</div>
      <div style={{ display:'flex', flexDirection:'column', gap: 2, marginTop: 4 }}>
        {items.map(i => <NavItem key={i.label} {...i} />)}
      </div>
      <div style={{ marginTop:'auto', padding: '14px', borderTop:'1px solid var(--rule)', display:'flex', alignItems:'center', justifyContent:'space-between' }}>
        <div>
          <div style={{ fontSize: 12, fontWeight: 500 }}>info</div>
          <div className="label" style={{ marginTop: 2 }}>Admin</div>
        </div>
        <button style={{ background:'transparent', border: 'none', color:'#8a8a85' }}><Icon.X/></button>
      </div>
    </aside>
  );
}

function TopBar() {
  return (
    <header style={{
      height: 48, borderBottom:'1px solid var(--rule)',
      background:'#fff',
      display:'flex', alignItems:'center', justifyContent:'space-between',
      padding: '0 18px',
    }}>
      <SlateLogo/>
      <div style={{ display:'flex', alignItems:'center', gap: 14 }}>
        <span className="mono" style={{
          fontSize: 11, padding: '4px 8px',
          background:'#efeedc', border:'1px solid var(--rule)',
          color:'#3a3a3a',
        }}>Slate Ops v0.56.0 · staging</span>
        <div style={{ width: 28, height: 28, background:'#0a0a0a', color:'#f3f1e3', display:'flex', alignItems:'center', justifyContent:'center', fontSize: 11, fontWeight: 600 }}>IN</div>
      </div>
    </header>
  );
}

function PageTabs({ active = 'workspace' }) {
  // After the redesign Workspace + Queue collapse into one tab. We show
  // "Workspace" with a small "combined" mark, and Queue is muted/deprecated.
  const tabs = [
    { id: 'overview',  label: 'Overview',  count: null },
    { id: 'workspace', label: 'Workspace', count: 26, badge: 'combined' },
    { id: 'queue',     label: 'Queue',     count: null, deprecated: true },
    { id: 'intake',    label: 'Intake',    count: 7 },
    { id: 'parts',     label: 'Parts',     count: 6 },
    { id: 'qc',        label: 'QC',        count: 1 },
    { id: 'pickup',    label: 'Pickup',    count: 2 },
    { id: 'exceptions',label: 'Exceptions',count: 3 },
  ];
  return (
    <div style={{ display:'flex', gap: 4, borderBottom:'1px solid var(--rule)' }}>
      {tabs.map(t => {
        const isActive = t.id === active;
        return (
          <div key={t.id} style={{
            padding:'10px 14px',
            borderBottom: isActive ? '2px solid var(--redwood)' : '2px solid transparent',
            color: t.deprecated ? '#a8a59a' : (isActive ? '#0a0a0a' : '#5b5b5b'),
            fontSize: 13, fontWeight: isActive ? 500 : 400,
            display:'flex', alignItems:'center', gap: 6,
            cursor:'pointer',
            textDecoration: t.deprecated ? 'line-through' : 'none',
            position:'relative',
          }}>
            {t.label}
            {t.count != null && (
              <span style={{ fontSize: 11, color:'#8a8a85' }}>{t.count}</span>
            )}
            {t.badge && (
              <span style={{
                fontSize: 9, letterSpacing:'0.08em', textTransform:'uppercase',
                padding:'2px 5px', background:'var(--sand)', color:'#5b5a3e',
                marginLeft: 2, fontWeight: 500,
              }}>{t.badge}</span>
            )}
            {t.deprecated && (
              <span title="Folded into Workspace" style={{
                fontSize: 9, letterSpacing:'0.08em', textTransform:'uppercase',
                padding:'2px 5px', background:'#f3d7d3', color:'#651510',
                marginLeft: 2, fontWeight: 500, textDecoration:'none',
              }}>removing</span>
            )}
          </div>
        );
      })}
    </div>
  );
}

function PageHeader({ dirtyCount, onSave, onNormalize, onRefresh, saveState }) {
  const saveLabel =
    saveState === 'saving' ? 'Saving…' :
    saveState === 'success' ? 'Saved' :
    saveState === 'error' ? 'Retry save' :
    `Save changes${dirtyCount ? ` (${dirtyCount})` : ''}`;
  const saveVariant =
    saveState === 'success' ? 'sage' :
    saveState === 'error' ? 'arches' :
    dirtyCount > 0 ? 'primary' : 'outline';

  return (
    <div style={{ paddingTop: 22, paddingBottom: 14 }}>
      <div className="label" style={{ color:'var(--arches)' }}>CS / Supervisor</div>
      <div style={{ display:'flex', alignItems:'flex-end', justifyContent:'space-between', gap: 24, marginTop: 6 }}>
        <div>
          <h1 style={{
            margin: 0, fontSize: 28, fontWeight: 500,
            fontVariationSettings:'"opsz" 32, "wght" 500',
            letterSpacing: '-0.01em',
          }}>CS Workspace</h1>
          <p style={{ margin: '4px 0 0', color:'#5b5b5b', fontSize: 13.5 }}>
            Manage intake, job updates, assignments, and shop queue order.
          </p>
        </div>
        <div style={{ display:'flex', alignItems:'center', gap: 8 }}>
          <Btn variant="outline" size="md" icon={<Icon.Plus/>}>New Job</Btn>
          <Btn variant="outline" size="md" icon={<Icon.Sort/>} onClick={onNormalize}>Normalize Queue</Btn>
          <Btn variant="outline" size="md" icon={<Icon.Refresh/>} onClick={onRefresh} title="Refreshed 2m ago">Refresh</Btn>
          <Btn variant={saveVariant} size="md" icon={saveState==='success' ? <Icon.Check/> : <Icon.Save/>} onClick={onSave} disabled={saveState==='saving'}>
            {saveLabel}
          </Btn>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { Sidebar, TopBar, PageTabs, PageHeader });
