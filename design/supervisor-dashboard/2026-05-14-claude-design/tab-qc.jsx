/* QC / Rework tab */

const TabQc = ({ onOpenJob }) => {
  const pending = JOBS.filter(j => j.qc && j.qc.toLowerCase().includes("pending"));
  const failed  = JOBS.filter(j => j.qc && j.qc.toLowerCase().includes("failed"));
  const rework  = JOBS.filter(j => j.status === "rework");
  const signoff = JOBS.filter(j => j.status === "complete" || (j.qc && j.qc.toLowerCase().includes("signed")));

  const Block = ({ title, list, statusLabel, statusKind, actionLabel }) => (
    <Card title={title} count={list.length}>
      <div className="scroll-x">
        <table className="tbl">
          <thead>
            <tr><th>Job</th><th>Customer</th><th>Tech</th><th>Stage</th><th>Action</th></tr>
          </thead>
          <tbody>
            {list.map(j => (
              <tr key={j.id} className="row-link" onClick={() => onOpenJob(j)}>
                <td><span className="job-id">{j.id}</span></td>
                <td>{j.customer}</td>
                <td>{j.tech?.name || <span className="muted">—</span>}</td>
                <td><span className={`pill pill--${statusKind}`}><span className="dot"/>{statusLabel}</span></td>
                <td onClick={(e) => e.stopPropagation()}>
                  <button className="action-link" onClick={() => onOpenJob(j)}>
                    {actionLabel} <span className="arrow"><Icon name="arrowR" size={12}/></span>
                  </button>
                </td>
              </tr>
            ))}
            {list.length === 0 && (
              <tr><td colSpan="5" style={{textAlign: "center", padding: 24, color: "var(--muted)"}}>—</td></tr>
            )}
          </tbody>
        </table>
      </div>
    </Card>
  );

  return (
    <div data-screen-label="06 QC and Rework">
      <div className="row-4">
        <div className="mini-stat">
          <div className="lbl">Pending QC</div>
          <div className="val">{pending.length}</div>
          <div className="sub">Awaiting QC team</div>
        </div>
        <div className="mini-stat">
          <div className="lbl text-arches">Failed QC</div>
          <div className="val" style={{color: "var(--arches)"}}>{failed.length}</div>
          <div className="sub">Needs disposition</div>
        </div>
        <div className="mini-stat">
          <div className="lbl">Rework Required</div>
          <div className="val">{rework.length}</div>
          <div className="sub">Back to tech</div>
        </div>
        <div className="mini-stat">
          <div className="lbl text-sage">Ready for Sign-off</div>
          <div className="val" style={{color: "var(--ok)"}}>{signoff.length}</div>
          <div className="sub">Supervisor approval</div>
        </div>
      </div>

      <div className="spacer-md"/>

      <div className="row-2">
        <Block title="Pending QC" list={pending} statusLabel="Pending QC" statusKind="qc" actionLabel="Review QC"/>
        <Block title="Failed QC" list={failed} statusLabel="Failed" statusKind="hold" actionLabel="Disposition"/>
      </div>

      <div className="spacer-md"/>

      <div className="row-2">
        <Block title="Rework Required" list={rework} statusLabel="Rework" statusKind="rework" actionLabel="Send Back to Tech"/>
        <Block title="Ready for Sign-off" list={signoff} statusLabel="Signed-off" statusKind="ready" actionLabel="Approve Closeout"/>
      </div>

      <div className="spacer-md"/>

      <Card title="Action Set" padded>
        <div className="row-4" style={{gap: 16}}>
          <div>
            <div className="lbl" style={{fontSize: 10, fontWeight: 900, letterSpacing: "0.22em", textTransform: "uppercase", color: "var(--muted)", marginBottom: 6}}>Review QC</div>
            <div className="muted" style={{fontSize: 12}}>Open punch list, confirm photos &amp; checklist completeness.</div>
          </div>
          <div>
            <div className="lbl" style={{fontSize: 10, fontWeight: 900, letterSpacing: "0.22em", textTransform: "uppercase", color: "var(--muted)", marginBottom: 6}}>Approve Closeout</div>
            <div className="muted" style={{fontSize: 12}}>Sign-off, flip to "Awaiting Pickup", notify CS.</div>
          </div>
          <div>
            <div className="lbl" style={{fontSize: 10, fontWeight: 900, letterSpacing: "0.22em", textTransform: "uppercase", color: "var(--muted)", marginBottom: 6}}>Send Back to Tech</div>
            <div className="muted" style={{fontSize: 12}}>Return with rework punch list. Re-opens shop status.</div>
          </div>
          <div>
            <div className="lbl" style={{fontSize: 10, fontWeight: 900, letterSpacing: "0.22em", textTransform: "uppercase", color: "var(--muted)", marginBottom: 6}}>Add Rework Note</div>
            <div className="muted" style={{fontSize: 12}}>Append note to job log, no status change.</div>
          </div>
        </div>
      </Card>
    </div>
  );
};

Object.assign(window, { TabQc });
