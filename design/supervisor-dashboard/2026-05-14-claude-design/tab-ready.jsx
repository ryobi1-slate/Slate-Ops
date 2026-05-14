/* Ready Queue tab — jobs ready for shop action.
   Parts readiness is shown as a *risk*, not a hard stop. */

const TabReady = ({ onOpenJob }) => {
  const toAssign  = JOBS.filter(j => j.statusLabel === "Ready to Assign");
  const toSchedule= JOBS.filter(j => j.statusLabel === "Ready to Schedule");
  const partial   = JOBS.filter(j => j.partsRisk === "med" && j.tech == null);
  const adminHold = JOBS.filter(j => j.blockedCategory === "Admin / Paperwork");

  const Section = ({ title, subtitle, list, badge, badgeKind, action }) => (
    <Card title={title} count={list.length}
      right={badge ? <span className={`pill ${badgeKind}`}>{badge}</span> : null}>
      {subtitle && (
        <div style={{padding: "0 18px 8px", color: "var(--muted)", fontSize: 12}}>{subtitle}</div>
      )}
      <div className="scroll-x">
        <table className="tbl">
          <thead>
            <tr><th>Job</th><th>Customer</th><th>Work Center</th><th>Parts</th><th>Due</th><th>Action</th></tr>
          </thead>
          <tbody>
            {list.map(j => (
              <tr key={j.id} className="row-link" onClick={() => onOpenJob(j)}>
                <td><span className="job-id">{j.id}</span><span className="job-id-sub">{j.so}</span></td>
                <td>{j.customer}</td>
                <td>{j.workCenter}</td>
                <td>
                  {j.partsRisk === "low" && <span className="pill pill--ready"><span className="dot"/>Complete</span>}
                  {j.partsRisk === "med" && <span className="pill pill--rework"><span className="dot"/>{j.parts}</span>}
                  {j.partsRisk === "high"&& <span className="pill pill--blocked"><span className="dot"/>{j.parts}</span>}
                </td>
                <td><span style={{fontFamily: "var(--mono)", fontSize: 12}}>{j.due}</span></td>
                <td onClick={(e) => e.stopPropagation()}>
                  <button className="action-link" onClick={() => onOpenJob(j)}>
                    {action} <span className="arrow"><Icon name="arrowR" size={12}/></span>
                  </button>
                </td>
              </tr>
            ))}
            {list.length === 0 && (
              <tr><td colSpan="6" style={{textAlign: "center", padding: 22, color: "var(--muted)"}}>None right now.</td></tr>
            )}
          </tbody>
        </table>
      </div>
    </Card>
  );

  return (
    <div data-screen-label="07 Ready Queue">
      <Card padded>
        <div className="row-flex" style={{justifyContent: "space-between"}}>
          <div>
            <div className="eyebrow text-sage">Parts policy</div>
            <div style={{fontSize: 14, fontWeight: 600, marginTop: 2}}>
              Partial parts are a <span className="text-arches">risk</span>, not a hard stop.
            </div>
            <div className="muted" style={{fontSize: 12, marginTop: 2}}>
              If the next stage can begin with what's on-hand, the job stays schedulable. Watch the parts pill.
            </div>
          </div>
          <div className="row-flex" style={{gap: 14}}>
            <div className="row-flex" style={{gap: 6}}>
              <span className="pill pill--ready"><span className="dot"/>Complete</span>
              <span className="muted" style={{fontSize: 11}}>all lines in</span>
            </div>
            <div className="row-flex" style={{gap: 6}}>
              <span className="pill pill--rework"><span className="dot"/>Partial</span>
              <span className="muted" style={{fontSize: 11}}>schedulable, watch</span>
            </div>
            <div className="row-flex" style={{gap: 6}}>
              <span className="pill pill--blocked"><span className="dot"/>Held</span>
              <span className="muted" style={{fontSize: 11}}>cannot start stage</span>
            </div>
          </div>
        </div>
      </Card>

      <div className="spacer-md"/>

      <Section title="Ready to Assign" list={toAssign}
        subtitle="Cleared by Parts &amp; Engineering. Pick a tech and the job enters the active board."
        action="Assign tech"/>

      <div className="spacer-md"/>

      <Section title="Ready to Schedule" list={toSchedule}
        subtitle="Cleared into a work center but not yet on the daily schedule."
        action="Schedule"/>

      <div className="spacer-md"/>

      <Section title="Parts Partial · Still Schedulable" list={partial}
        subtitle="Next stage can begin with on-hand parts. Treat partial as risk, not stop."
        badge="risk, not stop" badgeKind="pill--rework"
        action="Schedule with watch"/>

      <div className="spacer-md"/>

      <Section title="Waiting SO / Admin Hold" list={adminHold}
        subtitle="Paperwork or SO not finalized. CS owns clearing these."
        badge="CS owns" badgeKind="pill--waiting"
        action="Open with CS"/>
    </div>
  );
};

Object.assign(window, { TabReady });
