/* Blocked Jobs tab — full-screen table with filter chips by category */

const TabBlocked = ({ onOpenJob, selectedJobId }) => {
  const [cat, setCat] = React.useState("all");
  const blocked = JOBS.filter(j => j.status === "blocked" || j.status === "rework" || j.status === "hold");
  const filtered = cat === "all" ? blocked : blocked.filter(j => j.blockedCategory === cat);

  const catCounts = BLOCKED_CATEGORIES.reduce((acc, c) => {
    acc[c] = blocked.filter(j => j.blockedCategory === c).length;
    return acc;
  }, {});

  return (
    <div data-screen-label="03 Blocked Jobs">
      <div className="section-head">
        <div className="section-title">All Blockers · {blocked.length} jobs</div>
        <div className="section-hint">Categories aggregate Parts / Scope / Customer / QC / Tooling / Help / Admin holds</div>
      </div>

      <Card padded>
        <div className="chip-row" style={{marginBottom: 4}}>
          <button className={`filter-chip ${cat === "all" ? "active" : ""}`} onClick={() => setCat("all")}>
            All <span className="count">{blocked.length}</span>
          </button>
          {BLOCKED_CATEGORIES.map(c => (
            <button key={c}
              className={`filter-chip ${cat === c ? "active" : ""}`}
              onClick={() => setCat(c)}>
              {c} <span className="count">{catCounts[c] || 0}</span>
            </button>
          ))}
        </div>
      </Card>

      <div className="spacer-md"/>

      <Card title={`Blocked Jobs ${cat === "all" ? "" : "· " + cat}`} count={filtered.length}
        foot={<>
          <span>Sorted by time blocked — oldest first</span>
          <button className="action-link">Export <span className="arrow"><Icon name="arrowR" size={12}/></span></button>
        </>}>
        <div className="scroll-x">
          <table className="tbl">
            <thead>
              <tr>
                <th>Job / SO #</th>
                <th>Dealer / Customer</th>
                <th>Tech</th>
                <th>Blocked Category</th>
                <th>Reason</th>
                <th>Time Blocked</th>
                <th>Last Note</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map(j => (
                <tr key={j.id}
                    className={`row-link blocked-row ${selectedJobId === j.id ? "selected" : ""}`}
                    onClick={() => onOpenJob(j)}>
                  <td>
                    <span className="job-id">{j.id}</span>
                    <span className="job-id-sub">{j.so}</span>
                  </td>
                  <td>
                    <div className="cell-stack">
                      <span className="cell-bold">{j.customer}</span>
                      <span className="cell-sub">{j.dealer}</span>
                    </div>
                  </td>
                  <td>
                    {j.tech ? (
                      <div className="row-flex">
                        <span className="avatar" style={{width: 22, height: 22, fontSize: 10}}>{j.tech.initials}</span>
                        <span>{j.tech.name}</span>
                      </div>
                    ) : <span className="muted">—</span>}
                  </td>
                  <td><span className="pill pill--cat">{j.blockedCategory || "—"}</span></td>
                  <td style={{maxWidth: 260}}>{j.blockedReason || j.issue}</td>
                  <td><span className="job-id" style={{color: "var(--arches)"}}>{j.timeBlocked || "—"}</span></td>
                  <td style={{maxWidth: 220, color: "var(--muted)", fontSize: 12}}>{j.lastNote}</td>
                  <td onClick={(e) => e.stopPropagation()}>
                    <div className="row-flex" style={{gap: 4}}>
                      <button className="btn btn--sm btn--primary" onClick={() => onOpenJob(j)}>Clear</button>
                      <button className="btn btn--sm" title="More">
                        <Icon name="more" size={12}/>
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
              {filtered.length === 0 && (
                <tr><td colSpan="8" style={{textAlign: "center", padding: 40, color: "var(--muted)"}}>
                  No blockers in this category. <span className="text-sage">Shop is clear.</span>
                </td></tr>
              )}
            </tbody>
          </table>
        </div>
      </Card>

      <div className="spacer-md"/>

      <div className="row-3">
        <Card title="Action Set" padded>
          <ul style={{margin: 0, paddingLeft: 18, fontSize: 13, color: "var(--ink-2)", lineHeight: 1.7}}>
            <li><strong>Clear Blocker</strong> — closes the blocker, returns job to flow</li>
            <li><strong>Assign Helper</strong> — pairs an idle tech onto the job</li>
            <li><strong>Move to Hold</strong> — pulls the job from active board</li>
            <li><strong>Add Note</strong> — append visible shop-floor note</li>
            <li><strong>Escalate</strong> — flags for CS / Engineering / Customer</li>
          </ul>
        </Card>
        <Card title="Category Mix" padded>
          {BLOCKED_CATEGORIES.map(c => {
            const n = catCounts[c] || 0;
            const pct = blocked.length ? (n / blocked.length) * 100 : 0;
            return (
              <div key={c} className="load-row" style={{gridTemplateColumns: "1fr 80px 30px"}}>
                <span className="name" style={{fontSize: 11}}>{c}</span>
                <div className="bar" style={{width: 80}}><span style={{width: `${pct}%`}}/></div>
                <span className="pct">{n}</span>
              </div>
            );
          })}
        </Card>
        <Card title="Aging" padded>
          <div className="kv-grid">
            <div>
              <div className="lbl">Oldest blocker</div>
              <div className="val" style={{fontSize: 18, fontWeight: 600}}>2d 4h</div>
              <div className="cell-sub">S-ORD101392 · Parts</div>
            </div>
            <div>
              <div className="lbl">Avg age</div>
              <div className="val" style={{fontSize: 18, fontWeight: 600}}>14h</div>
              <div className="cell-sub">Across {blocked.length} blockers</div>
            </div>
            <div>
              <div className="lbl">{`>`} 1 day</div>
              <div className="val" style={{fontSize: 18, fontWeight: 600, color: "var(--arches)"}}>2</div>
            </div>
            <div>
              <div className="lbl">Cleared today</div>
              <div className="val" style={{fontSize: 18, fontWeight: 600, color: "var(--ok)"}}>3</div>
            </div>
          </div>
        </Card>
      </div>
    </div>
  );
};

Object.assign(window, { TabBlocked });
