/* Techs tab — tech status cards + full table */

const TECH_STATUS_CLASS = {
  "Working": "pill--progress",
  "Paused / Blocked": "pill--blocked",
  "Waiting": "pill--waiting",
  "In QC": "pill--qc",
  "No Active Job": "pill--waiting",
  "Needs Assignment": "pill--rework"
};

const TabTechs = ({ onOpenJob }) => {
  const [filter, setFilter] = React.useState("all");
  const filtered = TECHS.filter(t => {
    if (filter === "all") return true;
    if (filter === "blocked") return t.status === "Paused / Blocked";
    if (filter === "idle") return !t.current || t.status === "No Active Job" || t.status === "Needs Assignment";
    if (filter === "help") return t.needsHelp;
    return true;
  });

  return (
    <div data-screen-label="05 Techs">
      <div className="row-4">
        <div className="mini-stat">
          <div className="lbl">Techs on shift</div>
          <div className="val">{TECHS.length}</div>
          <div className="sub">Logged in today</div>
        </div>
        <div className="mini-stat">
          <div className="lbl">Working</div>
          <div className="val" style={{color: "var(--ok)"}}>{TECHS.filter(t => t.status === "Working").length}</div>
          <div className="sub">Active on a job</div>
        </div>
        <div className="mini-stat">
          <div className="lbl text-arches">Blocked / Waiting</div>
          <div className="val" style={{color: "var(--arches)"}}>
            {TECHS.filter(t => t.status === "Paused / Blocked" || t.status === "Waiting").length}
          </div>
          <div className="sub">Needs supervisor</div>
        </div>
        <div className="mini-stat">
          <div className="lbl">Needs Assignment</div>
          <div className="val">{TECHS.filter(t => t.status === "Needs Assignment").length}</div>
          <div className="sub">Idle / between jobs</div>
        </div>
      </div>

      <div className="spacer-md"/>

      <Card padded>
        <div className="chip-row">
          {[
            ["all", "All", TECHS.length],
            ["blocked", "Paused / Blocked", TECHS.filter(t => t.status === "Paused / Blocked").length],
            ["help", "Needs Help", TECHS.filter(t => t.needsHelp).length],
            ["idle", "Idle / Needs Assignment", TECHS.filter(t => !t.current || t.status === "Needs Assignment").length]
          ].map(([id, label, n]) => (
            <button key={id} className={`filter-chip ${filter === id ? "active" : ""}`} onClick={() => setFilter(id)}>
              {label} <span className="count">{n}</span>
            </button>
          ))}
        </div>
      </Card>

      <div className="spacer-md"/>

      <div className="row-4">
        {filtered.slice(0, 8).map(t => {
          const blocked = t.status === "Paused / Blocked";
          return (
            <div key={t.initials} className={`tech-card ${blocked ? "blocked" : ""}`}
                 onClick={() => t.current && onOpenJob(JOBS.find(j => j.id === t.current.id))}>
              <div className="head">
                <div className="row-flex">
                  <span className="avatar">{t.initials}</span>
                  <div>
                    <div className="name">{t.name}</div>
                    <div className="role">{t.role}</div>
                  </div>
                </div>
                <span className={`pill ${TECH_STATUS_CLASS[t.status] || ""}`}>
                  <span className="dot"/>{t.status}
                </span>
              </div>
              <div className="job">
                {t.current ? (
                  <>
                    <div className="job-name">{t.current.id}</div>
                    <div className="job-step">{t.current.label} · on job {t.onJobTime}</div>
                  </>
                ) : (
                  <div className="job-step muted">No active job</div>
                )}
              </div>
              <div className="meta">
                <span>Today {t.hours}</span>
                <span>Next: {t.next}</span>
              </div>
              {t.needsHelp && (
                <div style={{
                  display: "flex", justifyContent: "space-between", alignItems: "center",
                  padding: "6px 10px",
                  background: "rgba(216,107,25,0.12)",
                  borderRadius: 4,
                  fontSize: 11,
                  color: "#8d4310",
                  fontWeight: 600,
                  letterSpacing: "0.05em",
                  textTransform: "uppercase"
                }}>
                  <span><Icon name="warn" size={12}/> Needs help</span>
                  <span style={{fontFamily: "var(--mono)"}}>{t.blocker}</span>
                </div>
              )}
            </div>
          );
        })}
      </div>

      <div className="spacer-md"/>

      <Card title="Tech Roster" count={filtered.length}>
        <div className="scroll-x">
          <table className="tbl">
            <thead>
              <tr>
                <th>Tech</th>
                <th>Current Job</th>
                <th>Time on Job</th>
                <th>Next Job</th>
                <th>Status</th>
                <th>Needs Help</th>
                <th>Open Blocker</th>
                <th>Logged</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map(t => (
                <tr key={t.initials} className="row-link"
                    onClick={() => t.current && onOpenJob(JOBS.find(j => j.id === t.current.id))}>
                  <td>
                    <div className="row-flex">
                      <span className="avatar" style={{width: 24, height: 24, fontSize: 10}}>{t.initials}</span>
                      <div className="cell-stack">
                        <span className="cell-bold">{t.name}</span>
                        <span className="cell-sub">{t.role}</span>
                      </div>
                    </div>
                  </td>
                  <td>{t.current ? <span className="job-id">{t.current.id}</span> : <span className="muted">—</span>}</td>
                  <td><span style={{fontFamily: "var(--mono)", fontSize: 12}}>{t.onJobTime}</span></td>
                  <td>{t.next !== "—" ? <span className="job-id" style={{color: "var(--muted)"}}>{t.next}</span> : <span className="muted">—</span>}</td>
                  <td><span className={`pill ${TECH_STATUS_CLASS[t.status] || ""}`}><span className="dot"/>{t.status}</span></td>
                  <td>{t.needsHelp ? <span className="text-arches" style={{fontWeight: 600}}>● Yes</span> : <span className="muted">—</span>}</td>
                  <td>{t.blocker || <span className="muted">—</span>}</td>
                  <td><span style={{fontFamily: "var(--mono)", fontSize: 12}}>{t.hours}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
};

Object.assign(window, { TabTechs });
