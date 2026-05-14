/* Right-side detail drawer for a job. */

const Drawer = ({ job, onClose, onAction }) => {
  const open = !!job;
  const [note, setNote] = React.useState("");

  React.useEffect(() => {
    if (open) setNote("");
  }, [job?.id]);

  // ESC to close
  React.useEffect(() => {
    if (!open) return;
    const onKey = (e) => { if (e.key === "Escape") onClose(); };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [open, onClose]);

  if (!job) return (
    <>
      <div className="drawer-scrim" onClick={onClose} />
      <aside className="drawer" aria-hidden="true" />
    </>
  );

  const isBlocked = job.status === "blocked";

  return (
    <>
      <div className={`drawer-scrim ${open ? "open" : ""}`} onClick={onClose} />
      <aside className={`drawer ${open ? "open" : ""}`} aria-label="Job detail">
        <div className="drawer-head">
          <div className="row">
            <span className="job-id">{job.id}</span>
            <button className="icon-btn" onClick={onClose} title="Close (Esc)">
              <Icon name="close" size={14}/>
            </button>
          </div>
          <h2>{job.customer}</h2>
          <div className="row-flex" style={{gap: 10, marginTop: 6}}>
            <StatusPill status={job.status} label={job.statusLabel} />
            <span className="muted" style={{fontSize: 12}}>
              SO {job.so} · Dealer {job.dealer}
            </span>
          </div>
        </div>

        <div className="drawer-body">
          {isBlocked && (
            <div className="drawer-section" style={{
              background: "rgba(216,107,25,0.08)",
              border: "1px solid rgba(216,107,25,0.3)",
              borderRadius: 6,
              padding: "12px 14px"
            }}>
              <div className="lbl text-arches">Active blocker · {job.blockedCategory}</div>
              <div className="val" style={{marginBottom: 6}}>{job.blockedReason}</div>
              <div style={{fontFamily: "var(--mono)", fontSize: 11, color: "var(--muted)"}}>
                Blocked for {job.timeBlocked}
              </div>
            </div>
          )}

          <div className="kv-grid">
            <div>
              <div className="lbl">Assigned Tech</div>
              <div className="val">{job.tech?.name || "—"}</div>
            </div>
            <div>
              <div className="lbl">Bay / Work Center</div>
              <div className="val">{job.bay} · {job.workCenter}</div>
            </div>
            <div>
              <div className="lbl">Current Step</div>
              <div className="val">{job.step}</div>
            </div>
            <div>
              <div className="lbl">Due Date</div>
              <div className="val">
                {job.due}{" "}
                <span className={`muted`} style={{fontFamily: "var(--mono)", fontSize: 11}}>
                  ({job.dueDelta === 0 ? "today" : job.dueDelta > 0 ? `+${job.dueDelta}d` : `${job.dueDelta}d`})
                </span>
              </div>
            </div>
            <div>
              <div className="lbl">Parts Status</div>
              <div className="val">
                {job.parts}{" "}
                {job.partsRisk && job.partsRisk !== "low" && (
                  <span className={`pill ${job.partsRisk === "high" ? "pill--blocked" : "pill--rework"}`}
                        style={{marginLeft: 6, fontSize: 10}}>
                    {job.partsRisk === "high" ? "High risk" : "Watch"}
                  </span>
                )}
              </div>
            </div>
            <div>
              <div className="lbl">QC Status</div>
              <div className="val">{job.qc}</div>
            </div>
          </div>

          <hr className="divider"/>

          <div className="drawer-section">
            <div className="lbl">Notes &amp; Activity</div>
            {(job.notes && job.notes.length) ? (
              <div className="note-list">
                {job.notes.map((n, i) => (
                  <div className="note" key={i}>
                    <div className="meta"><span>{n.who}</span><span>·</span><span>{n.role}</span><span style={{marginLeft:"auto"}}>{n.when}</span></div>
                    <div>{n.text}</div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="muted" style={{fontSize: 12, padding: "8px 0"}}>
                No activity yet. Last system note: <em>{job.lastNote}</em>
              </div>
            )}
            <div className="note-add" style={{marginTop: 10}}>
              <textarea
                placeholder="Add a note for the shop floor…"
                value={note}
                onChange={(e) => setNote(e.target.value)}
              />
              <div style={{display: "flex", justifyContent: "flex-end", marginTop: 6}}>
                <button className="btn btn--sm btn--ghost" onClick={() => { onAction("note", note); setNote(""); }}>
                  Add Note
                </button>
              </div>
            </div>
          </div>

          <hr className="divider"/>

          <div className="drawer-section">
            <div className="lbl">Supervisor Actions</div>
            <div style={{display: "grid", gridTemplateColumns: "1fr 1fr", gap: 8}}>
              {isBlocked && (
                <>
                  <button className="btn btn--primary" onClick={() => onAction("clear")}>Clear Blocker</button>
                  <button className="btn" onClick={() => onAction("helper")}>Assign Helper</button>
                  <button className="btn" onClick={() => onAction("hold")}>Move to Hold</button>
                  <button className="btn btn--danger" onClick={() => onAction("escalate")}>Escalate</button>
                </>
              )}
              {!isBlocked && (
                <>
                  <button className="btn btn--primary" onClick={() => onAction("reassign")}>Reassign Tech</button>
                  <button className="btn" onClick={() => onAction("reschedule")}>Reschedule</button>
                  <button className="btn" onClick={() => onAction("review-qc")}>Review QC</button>
                  <button className="btn btn--ghost" onClick={() => onAction("schedule")}>Open in Schedule</button>
                </>
              )}
            </div>
          </div>
        </div>

        <div className="drawer-foot">
          <button className="btn" onClick={onClose}>Close</button>
          <button className="btn btn--primary">View Full Job</button>
        </div>
      </aside>
    </>
  );
};

Object.assign(window, { Drawer });
