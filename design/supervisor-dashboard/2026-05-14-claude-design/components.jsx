/* Shared UI primitives used across Supervisor tabs. */

// ── Icons (inline SVG) ─────────────────────────────────────────────────────
const Icon = ({ name, size = 16, className = "" }) => {
  const props = {
    width: size, height: size, viewBox: "0 0 24 24",
    fill: "none", stroke: "currentColor", strokeWidth: 1.6,
    strokeLinecap: "round", strokeLinejoin: "round",
    className: `ico ${className}`, "aria-hidden": "true"
  };
  switch (name) {
    case "home":      return <svg {...props}><path d="M3 11l9-8 9 8" /><path d="M5 9v12h14V9" /></svg>;
    case "cs":        return <svg {...props}><circle cx="12" cy="12" r="9"/><path d="M9 12h6M12 9v6"/></svg>;
    case "tech":      return <svg {...props}><path d="M14 4l6 6-9 9H5v-6z"/><path d="M14 4l2-2 4 4-2 2"/></svg>;
    case "executive": return <svg {...props}><path d="M3 20h18"/><path d="M5 20V8l4-3 6 4 4-2v13"/></svg>;
    case "schedule":  return <svg {...props}><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>;
    case "purchasing":return <svg {...props}><circle cx="9" cy="20" r="1.5"/><circle cx="17" cy="20" r="1.5"/><path d="M3 4h3l2.5 12h11"/></svg>;
    case "monitor":   return <svg {...props}><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg>;
    case "resource":  return <svg {...props}><path d="M4 5h11l5 5v9H4z"/><path d="M15 5v5h5"/></svg>;
    case "settings":  return <svg {...props}><circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1.2l2-1.5-2-3.5-2.4.8a7 7 0 0 0-2-1.2L14 3h-4l-.5 2.4a7 7 0 0 0-2 1.2L5 5.8l-2 3.5 2 1.5A7 7 0 0 0 5 12c0 .4 0 .8.1 1.2l-2 1.5 2 3.5 2.4-.8a7 7 0 0 0 2 1.2L10 21h4l.5-2.4a7 7 0 0 0 2-1.2l2.4.8 2-3.5-2-1.5c.1-.4.1-.8.1-1.2z"/></svg>;
    case "users":     return <svg {...props}><circle cx="9" cy="8" r="3.5"/><path d="M2 21a7 7 0 0 1 14 0"/><circle cx="17" cy="9" r="2.5"/><path d="M22 19a5 5 0 0 0-5-5"/></svg>;
    case "audit":     return <svg {...props}><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>;
    case "chevL":     return <svg {...props}><path d="M14 6l-6 6 6 6"/></svg>;
    case "chevR":     return <svg {...props}><path d="M10 6l6 6-6 6"/></svg>;
    case "arrowR":    return <svg {...props}><path d="M5 12h14M13 6l6 6-6 6"/></svg>;
    case "refresh":   return <svg {...props}><path d="M4 4v6h6"/><path d="M20 20v-6h-6"/><path d="M5 14a8 8 0 0 0 14 2"/><path d="M19 10A8 8 0 0 0 5 8"/></svg>;
    case "logout":    return <svg {...props}><path d="M9 4H4v16h5"/><path d="M16 16l4-4-4-4"/><path d="M20 12H10"/></svg>;
    case "overview":  return <svg {...props}><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>;
    case "block":     return <svg {...props}><circle cx="12" cy="12" r="9"/><path d="M5.5 5.5l13 13"/></svg>;
    case "qc":        return <svg {...props}><circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/></svg>;
    case "queue":     return <svg {...props}><path d="M4 6h16M4 12h16M4 18h10"/></svg>;
    case "wrench":    return <svg {...props}><path d="M14 7a4 4 0 0 1-5 5l-6 6 3 3 6-6a4 4 0 0 1 5-5"/></svg>;
    case "warn":      return <svg {...props}><path d="M12 3l10 18H2z"/><path d="M12 10v5M12 18v.5"/></svg>;
    case "clock":     return <svg {...props}><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>;
    case "user":      return <svg {...props}><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>;
    case "parts":     return <svg {...props}><rect x="3" y="7" width="18" height="13" rx="1"/><path d="M3 12h18M8 7V4h8v3"/></svg>;
    case "due":       return <svg {...props}><circle cx="12" cy="13" r="8"/><path d="M12 9v5h4M9 3h6"/></svg>;
    case "more":      return <svg {...props}><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg>;
    case "close":     return <svg {...props}><path d="M6 6l12 12M18 6L6 18"/></svg>;
    case "plus":      return <svg {...props}><path d="M12 5v14M5 12h14"/></svg>;
    case "search":    return <svg {...props}><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.5-4.5"/></svg>;
    case "filter":    return <svg {...props}><path d="M3 5h18l-7 9v6l-4-2v-4z"/></svg>;
    case "bay":       return <svg {...props}><rect x="3" y="7" width="18" height="13"/><path d="M3 11h18M9 7V4h6v3"/></svg>;
    case "lightning": return <svg {...props}><path d="M13 3L4 14h7l-1 7 9-11h-7z"/></svg>;
    default: return null;
  }
};

// ── Status pill ────────────────────────────────────────────────────────────
const STATUS_CLASS = {
  "blocked": "pill--blocked",
  "in-progress": "pill--progress",
  "qc": "pill--qc",
  "rework": "pill--rework",
  "ready": "pill--ready",
  "hold": "pill--hold",
  "complete": "pill--complete",
  "waiting": "pill--waiting",
  "paused": "pill--paused"
};
const StatusPill = ({ status, label }) => (
  <span className={`pill ${STATUS_CLASS[status] || ""}`}>
    <span className="dot" />
    {label}
  </span>
);

// ── Slate brand mark ───────────────────────────────────────────────────────
const SlateMark = ({ width = 84 }) => (
  <svg className="brand-mark" viewBox="0 0 100 28" width={width} aria-label="Slate" role="img">
    <defs>
      <linearGradient id="slate-mark-g" x1="0" x2="1" y1="0" y2="0">
        <stop offset="0" stopColor="#404f4b"/>
        <stop offset="1" stopColor="#0f342a"/>
      </linearGradient>
    </defs>
    <path d="M8 4 L24 4 L18 24 L2 24 Z" fill="url(#slate-mark-g)"/>
    <text x="30" y="20"
          fontFamily="Roboto Flex, sans-serif"
          fontSize="18"
          fontWeight="900"
          letterSpacing="0.16em"
          fill="#404f4b">SLATE</text>
  </svg>
);

// ── Sidebar nav (decorative, non-functional) ───────────────────────────────
const Sidebar = ({ collapsed, onToggle }) => {
  const items1 = [
    { ico: "home", label: "OPS Home" },
    { ico: "cs", label: "CS Workspace" },
    { ico: "tech", label: "Tech Screen" },
    { ico: "executive", label: "Executive" },
    { ico: "schedule", label: "Schedule" },
    { ico: "purchasing", label: "Purchasing" },
    { ico: "monitor", label: "Monitor" },
    { ico: "resource", label: "Resource Hub" }
  ];
  const items2 = [
    { ico: "settings", label: "Settings / Admin" },
    { ico: "users", label: "Users & Roles" },
    { ico: "audit", label: "Audit Log" }
  ];
  return (
    <aside className={`sidebar ${collapsed ? "collapsed" : ""}`}>
      <div className="brand">
        <SlateMark />
      </div>
      <button className="collapse-btn" onClick={onToggle} title="Collapse">
        <Icon name={collapsed ? "chevR" : "chevL"} size={14} />
      </button>
      <div className="nav-section-label">Workspaces</div>
      <nav className="nav-list">
        {items1.map(it => (
          <button key={it.label} className={`nav-item ${it.label === "CS Workspace" ? "active" : ""}`}>
            <Icon name={it.ico} />
            <span>{it.label}</span>
          </button>
        ))}
      </nav>
      <div className="nav-section-label" style={{marginTop: 8}}>Administration</div>
      <nav className="nav-list">
        {items2.map(it => (
          <button key={it.label} className="nav-item">
            <Icon name={it.ico} />
            <span>{it.label}</span>
          </button>
        ))}
      </nav>
      <div className="sidebar-foot">Slate OPS v0.59.1</div>
    </aside>
  );
};

// ── Page header ────────────────────────────────────────────────────────────
const PageHeader = ({ onRefresh, refreshed }) => (
  <header className="page-header" data-screen-label="01 Supervisor Header">
    <div>
      <div className="eyebrow">Shop Supervisor</div>
      <h1 className="page-title">Supervisor Dashboard</h1>
      <p className="page-subtitle">Shop control, blockers, QC, schedule risk, and active work.</p>
    </div>
    <div className="header-actions">
      <button className="btn" onClick={onRefresh}>
        <Icon name="refresh" size={14}/> Refreshed {refreshed}
      </button>
      <button className="btn">View Schedule</button>
      <button className="btn btn--primary">
        Open Blocked Jobs <Icon name="arrowR" size={14}/>
      </button>
    </div>
  </header>
);

// ── Topbar (user pill) ─────────────────────────────────────────────────────
const Topbar = () => (
  <div className="topbar">
    <span className="user-pill"><span className="dot"/> supervisor / Marco Reyes</span>
    <button className="icon-btn" title="Log out"><Icon name="logout" size={14}/></button>
  </div>
);

// ── KPI card ───────────────────────────────────────────────────────────────
const KpiCard = ({ kpi, density, blockedEmphasis }) => {
  const emph = kpi.emphasis ? `kpi--blocked emph-${blockedEmphasis}` : "";
  const dot = kpi.dot ? <span className={`dot ${kpi.dot}`} /> : null;
  return (
    <div className={`kpi density-${density} ${emph}`}>
      <div className="kpi-label">
        {dot}{kpi.label}
      </div>
      <div className="kpi-value">{kpi.value}</div>
      <div className="kpi-sub">
        <span>{kpi.sub}</span>
        {kpi.delta ? <span className={`kpi-delta ${kpi.deltaDir||''}`}>{kpi.delta}</span> : null}
      </div>
    </div>
  );
};

// ── Sub-tab nav ────────────────────────────────────────────────────────────
const Subtabs = ({ tabs, active, onChange, counts }) => (
  <div className="subtabs" role="tablist">
    {tabs.map(t => (
      <button
        key={t.id}
        role="tab"
        aria-selected={active === t.id}
        className={`subtab ${active === t.id ? "active" : ""}`}
        onClick={() => onChange(t.id)}
      >
        {t.label}
        {t.countKey && counts[t.countKey] != null ? (
          <span className="count">{counts[t.countKey]}</span>
        ) : null}
      </button>
    ))}
  </div>
);

// ── Filter chips ───────────────────────────────────────────────────────────
const FilterChips = ({ chips, active, onChange }) => (
  <div className="chip-row">
    {chips.map(c => (
      <button key={c.id}
        className={`filter-chip ${active === c.id ? "active" : ""}`}
        onClick={() => onChange(c.id)}>
        {c.label}
        {c.count != null ? <span className="count">{c.count}</span> : null}
      </button>
    ))}
  </div>
);

// ── Card frame ─────────────────────────────────────────────────────────────
const Card = ({ title, count, meta, right, foot, children, padded }) => (
  <section className="card">
    {(title || meta || right) && (
      <div className="card-head">
        {title ? (
          <h3 className="card-title">{title}{count != null && <span className="count">{count} items</span>}</h3>
        ) : <span/>}
        <div className="row-flex">
          {meta ? <span className="card-meta">{meta}</span> : null}
          {right}
        </div>
      </div>
    )}
    <div className={`card-body ${padded ? "padded" : ""}`}>{children}</div>
    {foot ? <div className="card-foot">{foot}</div> : null}
  </section>
);

Object.assign(window, {
  Icon, StatusPill, Sidebar, PageHeader, Topbar, KpiCard, Subtabs, FilterChips, Card, SlateMark
});
