/* Schedule tab — present-state lists + future-state scheduler placeholders */

const TabSchedule = ({ onOpenJob }) => {
  const today = JOBS.filter(j => ["in-progress","qc","rework"].includes(j.status));
  const ready = JOBS.filter(j => j.status === "ready");
  const atRisk = JOBS.filter(j => j.dueDelta <= 0 && j.status !== "complete");
  const unassigned = JOBS.filter(j => !j.tech);

  return (
    <div data-screen-label="04 Schedule">
      <div className="row-4">
        <div className="mini-stat">
          <div className="lbl">Scheduled today</div>
          <div className="val">{today.length}</div>
          <div className="sub">Across 6 bays</div>
        </div>
        <div className="mini-stat">
          <div className="lbl">Ready to schedule</div>
          <div className="val">{ready.length}</div>
          <div className="sub">Cleared for planning</div>
        </div>
        <div className="mini-stat">
          <div className="lbl text-arches">At risk</div>
          <div className="val" style={{color: "var(--arches)"}}>{atRisk.length}</div>
          <div className="sub">Past due or due today</div>
        </div>
        <div className="mini-stat">
          <div className="lbl">Unassigned</div>
          <div className="val">{unassigned.length}</div>
          <div className="sub">Needs tech</div>
        </div>
      </div>

      <div className="spacer-md"/>

      <div className="row-2">
        <Card title="Today's Scheduled Work" count={today.length}
          right={<button className="btn btn--sm">View by Bay</button>}>
          <div className="scroll-x">
            <table className="tbl">
              <thead>
                <tr><th>Job</th><th>Tech</th><th>Bay</th><th>Step</th><th>Due</th></tr>
              </thead>
              <tbody>
                {today.map(j => (
                  <tr key={j.id} className="row-link" onClick={() => onOpenJob(j)}>
                    <td><span className="job-id">{j.id}</span></td>
                    <td>{j.tech?.name || <span className="muted">—</span>}</td>
                    <td>{j.bay}</td>
                    <td>{j.step}</td>
                    <td><span className="cell-sub" style={{fontFamily: "var(--mono)"}}>{j.due}</span></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>

        <Card title="Jobs at Risk" count={atRisk.length} meta={<><span className="dot"/> Track</>}>
          <div className="scroll-x">
            <table className="tbl">
              <thead>
                <tr><th>Job</th><th>Customer</th><th>Issue</th><th>Δ Due</th></tr>
              </thead>
              <tbody>
                {atRisk.map(j => (
                  <tr key={j.id} className="row-link blocked-row" onClick={() => onOpenJob(j)}>
                    <td><span className="job-id">{j.id}</span></td>
                    <td>{j.customer}</td>
                    <td style={{maxWidth: 200}}>{j.issueShort || j.issue}</td>
                    <td><span className="job-id" style={{color: "var(--arches)"}}>{j.dueDelta === 0 ? "today" : `${j.dueDelta}d`}</span></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      </div>

      <div className="spacer-md"/>

      <div className="row-2">
        <Card title="Ready to Schedule" count={ready.length}
          right={<button className="btn btn--sm btn--primary">Bulk Schedule</button>}>
          <div className="scroll-x">
            <table className="tbl">
              <thead>
                <tr><th>Job</th><th>Customer</th><th>Work Center</th><th>Parts</th><th></th></tr>
              </thead>
              <tbody>
                {ready.map(j => (
                  <tr key={j.id} className="row-link" onClick={() => onOpenJob(j)}>
                    <td><span className="job-id">{j.id}</span></td>
                    <td>{j.customer}</td>
                    <td>{j.workCenter}</td>
                    <td>
                      {j.partsRisk === "low" ? <span className="pill pill--ready">Complete</span> :
                       <span className="pill pill--rework">{j.parts}</span>}
                    </td>
                    <td onClick={(e) => e.stopPropagation()}>
                      <button className="action-link" onClick={() => onOpenJob(j)}>
                        Schedule <span className="arrow"><Icon name="arrowR" size={12}/></span>
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>

        <Card title="Unassigned Jobs" count={unassigned.length}
          right={<button className="btn btn--sm">Auto-Suggest</button>}>
          <div className="scroll-x">
            <table className="tbl">
              <thead>
                <tr><th>Job</th><th>Customer</th><th>Work Center</th><th>Status</th></tr>
              </thead>
              <tbody>
                {unassigned.map(j => (
                  <tr key={j.id} className="row-link" onClick={() => onOpenJob(j)}>
                    <td><span className="job-id">{j.id}</span></td>
                    <td>{j.customer}</td>
                    <td>{j.workCenter}</td>
                    <td><StatusPill status={j.status} label={j.statusLabel}/></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      </div>

      <div className="spacer-md"/>

      <div className="section-head">
        <div className="section-title">Scheduler — Planned (placeholders for future build)</div>
        <div className="section-hint">Read-only previews · live data when scheduler ships</div>
      </div>

      <div className="row-3">
        <div className="future-card">
          <div className="lbl">Planned Capacity</div>
          <div className="val">— hrs / wk</div>
          <div className="sub">Tech hours available next 7 days</div>
        </div>
        <div className="future-card">
          <div className="lbl">Buffer Usage</div>
          <div className="val">— %</div>
          <div className="sub">Slack consumed vs. planned</div>
        </div>
        <div className="future-card">
          <div className="lbl">Overload Warning</div>
          <div className="val">—</div>
          <div className="sub">Bays / work-centers in red</div>
        </div>
      </div>

      <div className="spacer-md"/>

      <div className="row-2">
        <Card title="Bay Load · Planned" meta="Preview">
          <div className="card-body padded" style={{padding: "8px 18px 18px"}}>
            {[
              ["Bay 1", 78, "high"],
              ["Bay 2", 62, ""],
              ["Bay 3", 91, "over"],
              ["Bay 4", 44, ""],
              ["Bay 5", 0,  "off"],
              ["Bay 6", 55, ""],
              ["QC Bay", 70, ""]
            ].map(([name, pct, level]) => (
              <div key={name} className="load-row">
                <span className="name">{name}</span>
                <div className={`bar ${level === "over" ? "warn" : level === "high" ? "ok" : ""}`}>
                  <span style={{width: `${pct}%`}}/>
                </div>
                <span className={`pct ${level === "over" ? "over" : level === "high" ? "high" : ""}`}>
                  {level === "off" ? "OFF" : `${pct}%`}
                </span>
              </div>
            ))}
          </div>
        </Card>

        <Card title="Work Center Load · Planned" meta="Preview">
          <div className="card-body padded" style={{padding: "8px 18px 18px"}}>
            {[
              ["Electrical", 88, "over"],
              ["Interior", 64, ""],
              ["Cabinetry", 72, "high"],
              ["Mechanical", 40, ""],
              ["Closeout", 55, ""],
              ["QC", 70, "high"]
            ].map(([name, pct, level]) => (
              <div key={name} className="load-row">
                <span className="name">{name}</span>
                <div className={`bar ${level === "over" ? "warn" : level === "high" ? "ok" : ""}`}>
                  <span style={{width: `${pct}%`}}/>
                </div>
                <span className={`pct ${level === "over" ? "over" : level === "high" ? "high" : ""}`}>{pct}%</span>
              </div>
            ))}
          </div>
        </Card>
      </div>
    </div>
  );
};

Object.assign(window, { TabSchedule });
