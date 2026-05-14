/* Overview tab — Shop Control Board (table) + Supervisor Attention (right rail) */

const TabOverview = ({ onOpenJob, selectedJobId, jobs }) => {
  const board = jobs.filter(j => j.status !== "complete" || j.id === "S-ORD101350").slice(0, 10);

  return (
    <div data-screen-label="02 Overview">
      <div className="banner">
        <div>
          <div className="lbl">First focus</div>
          <div className="txt">
            <strong>4 jobs blocked</strong> · oldest blocker is <strong>S-ORD101392</strong> at 2d 4h.
            Parts risk on 2 active jobs. 1 rework returned from QC overnight.
          </div>
        </div>
        <button className="btn">Open All Blockers <Icon name="arrowR" size={14}/></button>
      </div>

      <div className="overview-grid">
        <Card
          title="Shop Control Board"
          count={board.length}
          right={
            <div className="row-flex" style={{gap: 8}}>
              <button className="filter-chip active">All</button>
              <button className="filter-chip">Blocked</button>
              <button className="filter-chip">Due ≤ 2d</button>
              <button className="filter-chip">Unassigned</button>
            </div>
          }
          foot={<>
            <span>Click any row to view detail · <span className="kbd">↑</span> <span className="kbd">↓</span> to navigate</span>
            <button className="action-link">View all open jobs <span className="arrow"><Icon name="arrowR" size={12}/></span></button>
          </>}
        >
          <div className="scroll-x">
            <table className="tbl">
              <thead>
                <tr>
                  <th>Job</th>
                  <th>Dealer / Customer</th>
                  <th>Assigned Tech</th>
                  <th>Status</th>
                  <th>Current Step</th>
                  <th>Due</th>
                  <th>Issue / Next Action</th>
                  <th>Supervisor Action</th>
                </tr>
              </thead>
              <tbody>
                {board.map(j => (
                  <tr key={j.id}
                      className={`row-link ${selectedJobId === j.id ? "selected" : ""} ${j.status === "blocked" ? "blocked-row" : ""}`}
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
                          <span className="avatar" style={{width: 24, height: 24, fontSize: 10}}>{j.tech.initials}</span>
                          <span>{j.tech.name}</span>
                        </div>
                      ) : <span className="muted">— Unassigned</span>}
                    </td>
                    <td><StatusPill status={j.status} label={j.statusLabel}/></td>
                    <td>{j.step}</td>
                    <td>
                      <div className="cell-stack">
                        <span className="cell-bold">{j.due}</span>
                        <span className="cell-sub" style={{fontFamily: "var(--mono)"}}>
                          {j.dueDelta === 0 ? "today" : j.dueDelta > 0 ? `+${j.dueDelta}d` : `${j.dueDelta}d`}
                        </span>
                      </div>
                    </td>
                    <td style={{maxWidth: 220}}>{j.issue}</td>
                    <td>
                      <button className="action-link" onClick={(e) => { e.stopPropagation(); onOpenJob(j); }}>
                        {j.action} <span className="arrow"><Icon name="arrowR" size={12}/></span>
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>

        <Card title="Supervisor Attention" meta={<><span className="dot"/> Live</>}
          foot={<>
            <span>Updated every 60s</span>
            <button className="action-link">All flagged items <span className="arrow"><Icon name="arrowR" size={12}/></span></button>
          </>}>
          <div className="attn-list">
            <button className="attn-item" onClick={() => onOpenJob(JOBS.find(j => j.id === "S-ORD101392"))}>
              <span className="attn-icon danger"><Icon name="block" size={14}/></span>
              <div>
                <div className="attn-title">Oldest blocked job</div>
                <div className="attn-body"><strong>S-ORD101392</strong> · Wiring harness back-order. Vendor revised ETA to 5/16.</div>
              </div>
              <span className="attn-meta">2d 4h</span>
            </button>
            <button className="attn-item" onClick={() => onOpenJob(JOBS.find(j => j.id === "S-ORD101392"))}>
              <span className="attn-icon warn"><Icon name="user" size={14}/></span>
              <div>
                <div className="attn-title">Tech needing help</div>
                <div className="attn-body"><strong>Marco R.</strong> on electrical rough-in. Flagged "needs help" 12m ago.</div>
              </div>
              <span className="attn-meta">12m</span>
            </button>
            <button className="attn-item" onClick={() => onOpenJob(JOBS.find(j => j.id === "S-ORD101388"))}>
              <span className="attn-icon"><Icon name="due" size={14}/></span>
              <div>
                <div className="attn-title">Job due soon</div>
                <div className="attn-body"><strong>S-ORD101388</strong> · Cascade Electric — due tomorrow, QC slot tight.</div>
              </div>
              <span className="attn-meta">due tmrw</span>
            </button>
            <button className="attn-item" onClick={() => onOpenJob(JOBS.find(j => j.id === "S-ORD101377"))}>
              <span className="attn-icon"><Icon name="qc" size={14}/></span>
              <div>
                <div className="attn-title">QC item waiting</div>
                <div className="attn-body"><strong>S-ORD101377</strong> · Stage 2 QC pending supervisor sign-off (2h).</div>
              </div>
              <span className="attn-meta">2h</span>
            </button>
            <button className="attn-item" onClick={() => onOpenJob(JOBS.find(j => j.id === "S-ORD101394"))}>
              <span className="attn-icon warn"><Icon name="parts" size={14}/></span>
              <div>
                <div className="attn-title">Parts risk</div>
                <div className="attn-body"><strong>S-ORD101394</strong> · 2 of 14 lines outstanding — shelving subassembly. Schedulable but watch.</div>
              </div>
              <span className="attn-meta">watch</span>
            </button>
          </div>
        </Card>
      </div>
    </div>
  );
};

Object.assign(window, { TabOverview });
