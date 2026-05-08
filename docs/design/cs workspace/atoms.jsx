// Atomic UI components for Slate Ops CS Workspace.
const PillStyles = {
  base: {
    display: 'inline-flex',
    alignItems: 'center',
    gap: 6,
    padding: '3px 8px',
    fontSize: 11,
    fontWeight: 500,
    letterSpacing: '0.04em',
    textTransform: 'uppercase',
    border: '1px solid transparent',
    borderRadius: 2,
    whiteSpace: 'nowrap',
    lineHeight: 1.1,
  },
  sage:    { background: '#e3e8e5', color: '#2a3835', borderColor: '#bdc8c2' },
  sand:    { background: '#efeedc', color: '#5b5a3e', borderColor: '#d8d5b5' },
  warn:    { background: '#fbe6d2', color: '#7a3d0c', borderColor: '#e9c79b' },
  arches:  { background: '#fbe6d2', color: '#7a3d0c', borderColor: '#e9c79b' },
  bad:     { background: '#f3d7d3', color: '#651510', borderColor: '#dcb1ac' },
  info:    { background: '#dde6ec', color: '#1f3c52', borderColor: '#b9c9d4' },
  redwood: { background: '#d6dfd9', color: '#0f342a', borderColor: '#a9bcb2' },
  muted:   { background: '#eeeada', color: '#7a7960', borderColor: '#dad6bd' },
  ok:      { background: '#d8e8df', color: '#1c4a32', borderColor: '#aac6b6' },
};

function Pill({ tone = 'sage', children, dot }) {
  const s = { ...PillStyles.base, ...(PillStyles[tone] || PillStyles.sage) };
  return (
    <span style={s}>
      {dot && <span style={{ width: 6, height: 6, borderRadius: '50%', background: 'currentColor', opacity: 0.8 }} />}
      {children}
    </span>
  );
}

function StatusPill({ kind }) {
  const s = window.WORKSPACE_DATA.STATUS[kind];
  if (!s) return null;
  return <Pill tone={s.tone}>{s.label}</Pill>;
}

function PartsPill({ kind }) {
  const p = window.WORKSPACE_DATA.PARTS[kind];
  if (!p) return null;
  return <Pill tone={p.tone}>{p.label}</Pill>;
}

function Btn({ variant = 'ghost', size = 'md', children, icon, onClick, disabled, style, title }) {
  const sizes = {
    sm: { padding: '5px 9px', fontSize: 12, gap: 6 },
    md: { padding: '8px 14px', fontSize: 13, gap: 8 },
  };
  const variants = {
    primary: { background: '#0f342a', color: '#f3f1e3', border: '1px solid #0f342a' },
    sage:    { background: '#404f4b', color: '#f3f1e3', border: '1px solid #404f4b' },
    arches:  { background: '#d86b19', color: '#fff',    border: '1px solid #d86b19' },
    outline: { background: '#fff',    color: '#1a1a1a', border: '1px solid #c9c6a8' },
    ghost:   { background: 'transparent', color: '#1a1a1a', border: '1px solid transparent' },
    danger:  { background: '#fff',    color: '#651510', border: '1px solid #dcb1ac' },
  };
  return (
    <button
      onClick={onClick}
      disabled={disabled}
      title={title}
      style={{
        display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
        fontFamily: 'inherit', fontWeight: 500,
        letterSpacing: '0.01em',
        borderRadius: 2,
        cursor: disabled ? 'not-allowed' : 'pointer',
        opacity: disabled ? 0.55 : 1,
        transition: 'background 120ms ease, border-color 120ms ease, transform 80ms ease',
        ...sizes[size], ...variants[variant], ...style,
      }}
      onMouseDown={(e) => { e.currentTarget.style.transform = 'translateY(1px)'; }}
      onMouseUp={(e)   => { e.currentTarget.style.transform = ''; }}
      onMouseLeave={(e)=> { e.currentTarget.style.transform = ''; }}
    >
      {icon && <span style={{ display: 'inline-flex', alignItems: 'center' }}>{icon}</span>}
      {children}
    </button>
  );
}

// Slim line icons.
const Icon = {
  Drag: (p) => (
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" {...p}>
      <circle cx="9" cy="6" r="1.4" fill="currentColor"/><circle cx="15" cy="6" r="1.4" fill="currentColor"/>
      <circle cx="9" cy="12" r="1.4" fill="currentColor"/><circle cx="15" cy="12" r="1.4" fill="currentColor"/>
      <circle cx="9" cy="18" r="1.4" fill="currentColor"/><circle cx="15" cy="18" r="1.4" fill="currentColor"/>
    </svg>
  ),
  Plus: (p) => (<svg width="14" height="14" viewBox="0 0 24 24" fill="none" {...p}><path d="M12 5v14M5 12h14" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round"/></svg>),
  Save: (p) => (<svg width="14" height="14" viewBox="0 0 24 24" fill="none" {...p}><path d="M5 4h11l3 3v13H5z" stroke="currentColor" strokeWidth="1.4"/><path d="M8 4v5h7V4M8 14h8" stroke="currentColor" strokeWidth="1.4"/></svg>),
  Refresh: (p) => (<svg width="14" height="14" viewBox="0 0 24 24" fill="none" {...p}><path d="M4 12a8 8 0 0 1 14-5.3M20 12a8 8 0 0 1-14 5.3M18 3v4h-4M6 21v-4h4" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round"/></svg>),
  Sort: (p) => (<svg width="14" height="14" viewBox="0 0 24 24" fill="none" {...p}><path d="M7 5v14M4 8l3-3 3 3M17 19V5M14 16l3 3 3-3" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round"/></svg>),
  Eye: (p) => (<svg width="14" height="14" viewBox="0 0 24 24" fill="none" {...p}><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" stroke="currentColor" strokeWidth="1.4"/><circle cx="12" cy="12" r="2.6" stroke="currentColor" strokeWidth="1.4"/></svg>),
  X: (p) => (<svg width="14" height="14" viewBox="0 0 24 24" fill="none" {...p}><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round"/></svg>),
  Warn: (p) => (<svg width="14" height="14" viewBox="0 0 24 24" fill="none" {...p}><path d="M12 3l10 18H2z" stroke="currentColor" strokeWidth="1.5" strokeLinejoin="round"/><path d="M12 10v5M12 18v.01" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round"/></svg>),
  Check: (p) => (<svg width="14" height="14" viewBox="0 0 24 24" fill="none" {...p}><path d="M5 12l4.5 4.5L20 6" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"/></svg>),
  Note: (p) => (<svg width="14" height="14" viewBox="0 0 24 24" fill="none" {...p}><path d="M5 4h11l3 3v13H5z" stroke="currentColor" strokeWidth="1.4"/><path d="M8 10h8M8 14h6" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round"/></svg>),
  Chev: (p) => (<svg width="14" height="14" viewBox="0 0 24 24" fill="none" {...p}><path d="M6 9l6 6 6-6" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"/></svg>),
  ChevR: (p) => (<svg width="14" height="14" viewBox="0 0 24 24" fill="none" {...p}><path d="M9 6l6 6-6 6" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"/></svg>),
  Search: (p) => (<svg width="14" height="14" viewBox="0 0 24 24" fill="none" {...p}><circle cx="11" cy="11" r="6.5" stroke="currentColor" strokeWidth="1.5"/><path d="M20 20l-4-4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round"/></svg>),
  Bolt: (p) => (<svg width="14" height="14" viewBox="0 0 24 24" fill="none" {...p}><path d="M13 2L4 14h6l-1 8 9-12h-6l1-8z" stroke="currentColor" strokeWidth="1.4" strokeLinejoin="round"/></svg>),
  Filter: (p) => (<svg width="14" height="14" viewBox="0 0 24 24" fill="none" {...p}><path d="M3 5h18l-7 9v6l-4-2v-4z" stroke="currentColor" strokeWidth="1.4" strokeLinejoin="round"/></svg>),
};

function PriorityDot({ prio }) {
  const c = prio === 'high' ? 'var(--arches)' : prio === 'low' ? 'var(--ink-4)' : 'var(--sage)';
  return <span title={`Priority: ${prio}`} style={{ display:'inline-block', width: 7, height: 7, borderRadius: 0, background: c, transform:'rotate(45deg)' }} />;
}

function TechAvatar({ tech, size = 24 }) {
  const t = window.WORKSPACE_DATA.TECHS.find(x => x.id === tech);
  const palette = {
    jake:  '#3b5d70', info: '#404f4b', marco: '#6b4a2c',
    priya: '#5a3e6a', devon: '#2d5e4a', unassigned: '#8a8a85',
  };
  return (
    <div style={{
      width: size, height: size, borderRadius: 0,
      background: palette[tech] || '#404f4b',
      color: '#f3f1e3', fontSize: size * 0.42, fontWeight: 600,
      display:'inline-flex', alignItems:'center', justifyContent:'center',
      letterSpacing: '0.02em', flex: '0 0 auto',
    }}>{t?.initials || '?'}</div>
  );
}

Object.assign(window, { Pill, StatusPill, PartsPill, Btn, Icon, PriorityDot, TechAvatar });
