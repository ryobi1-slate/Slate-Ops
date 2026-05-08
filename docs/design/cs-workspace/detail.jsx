// Detail / edit panel — bottom drawer when a job is selected.
function Section({ title, right, children, gridCols = '1fr 1fr' }) {
  return (
    <section style={{ display:'flex', flexDirection:'column', gap: 8 }}>
      <header style={{ display:'flex', alignItems:'baseline', justifyContent:'space-between', borderBottom:'1px solid var(--rule)', paddingBottom: 4 }}>
        <span className="label">{title}</span>
        {right}
      </header>
      <div style={{ display:'grid', gridTemplateColumns: gridCols, gap: 10 }}>
        {children}
      </div>
    </section>
  );
}

function Field({ label, children, span = 1 }) {
  return (
    <label style={{ gridColumn: `span ${span}`, display:'flex', flexDirection:'column', gap: 4 }}>
      <span className="label">{label}</span>
      {children}
    </label>
  );
}

const inputStyle = {
  border: '1px solid var(--rule)',
  background:'#fff',
  padding: '6px 8px',
  fontSize: 13,
  borderRadius: 0,
  width: '100%',
};

function Input(props) { return <input {...props} style={{ ...inputStyle, ...props.style }} />; }
function Select({ children, ...props }) {
  return <select {...props} style={{ ...inputStyle, ...props.style }}>{children}</select>;
}
function Textarea(props) {
  return <textarea {...props} style={{ ...inputStyle, minHeight: 70, resize:'vertical', fontFamily:'inherit', ...props.style }} />;
}

function DetailPanel({ job, dirty, onSave, onCancel, onClose, onChange }) {
  if (!job) return null;
  const TECHS = window.WORKSPACE_DATA.TECHS;
  const STATUS = window.WORKSPACE_DATA.STATUS;
  const PARTS = window.WORKSPACE_DATA.PARTS;
  return (
    <div style={{
      position:'sticky', bottom: 0, zIndex: 10,
      background:'#fff',
      borderTop:'2px solid var(--redwood)',
      boxShadow:'0 -10px 24px -16px rgba(15,52,42,0.25)',
      marginTop: 14,
    }}>
      {/* sticky save bar */}
      <div style={{
        display:'flex', alignItems:'center', justifyContent:'space-between',
        padding:'10px 18px', background:'#0f342a', color:'#f3f1e3',
      }}>
        <div style={{ display:'flex', alignItems:'center', gap: 12, minWidth: 0 }}>
          <span className="mono" style={{ fontSize: 11, opacity: 0.8, letterSpacing:'0.06em' }}>JOB DETAIL</span>
          <span style={{ fontSize: 14, fontWeight: 500, whiteSpace:'nowrap', overflow:'hidden', textOverflow:'ellipsis' }}>
            {job.cust} · <span className="mono">{job.so}</span>
          </span>
          <StatusPill kind={job.status}/>
          {dirty && (
            <span style={{
              fontSize: 11, padding:'3px 7px', background:'var(--sand)', color:'#0f342a',
              letterSpacing:'0.06em', textTransform:'uppercase', fontWeight: 500,
            }}>Unsaved</span>
          )}
        </div>
        <div style={{ display:'flex', alignItems:'center', gap: 8 }}>
          <Btn variant="ghost" size="sm" onClick={onCancel} style={{ color:'#e1dfc8', borderColor:'rgba(225,223,200,0.2)' }}>Cancel</Btn>
          <Btn variant="arches" size="sm" icon={<Icon.Save/>} onClick={onSave}>Save & next</Btn>
          <button onClick={onClose} style={{ background:'transparent', border:'1px solid rgba(225,223,200,0.2)', color:'#e1dfc8', padding:'4px 6px' }}>
            <Icon.X/>
          </button>
        </div>
      </div>

      <div style={{
        maxHeight: 320,
        overflow:'auto',
        padding:'14px 18px',
        display:'grid', gridTemplateColumns: 'repeat(12, 1fr)',
        gap: 18,
      }}>
        {/* IDENTITY */}
        <div style={{ gridColumn: 'span 4' }}>
          <Section title="Job Identity">
            <Field label="Customer / Dealer" span={2}>
              <Input value={job.cust} onChange={e => onChange({ cust: e.target.value })}/>
            </Field>
            <Field label="SO #">
              <Input className="mono" value={job.so} onChange={e => onChange({ so: e.target.value })}/>
            </Field>
            <Field label="Dealer">
              <Input className="mono" value={job.dealer} onChange={e => onChange({ dealer: e.target.value })}/>
            </Field>
            <Field label="VIN" span={2}>
              <Input className="mono" value={job.vin} placeholder="—" onChange={e => onChange({ vin: e.target.value })}/>
            </Field>
            <Field label="Salesperson" span={2}>
              <Input value={job.sales} placeholder="—" onChange={e => onChange({ sales: e.target.value })}/>
            </Field>
          </Section>
        </div>

        {/* SCHEDULING + ASSIGNMENT */}
        <div style={{ gridColumn: 'span 4', display:'flex', flexDirection:'column', gap: 16 }}>
          <Section title="Scheduling" gridCols="1fr 1fr 1fr">
            <Field label="Due Date">
              <Input type="date" className="mono" value={job.due} onChange={e => onChange({ due: e.target.value })}/>
            </Field>
            <Field label="Est. Hrs">
              <Input className="mono" value={job.est} onChange={e => onChange({ est: parseFloat(e.target.value) || 0 })}/>
            </Field>
            <Field label="Priority">
              <Select value={job.prio} onChange={e => onChange({ prio: e.target.value })}>
                <option value="low">Low</option>
                <option value="normal">Normal</option>
                <option value="high">High</option>
              </Select>
            </Field>
          </Section>

          <Section title="Assignment" gridCols="1fr 1fr">
            <Field label="Assigned Tech">
              <Select value={job.tech} onChange={e => onChange({ tech: e.target.value })}>
                {TECHS.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
              </Select>
            </Field>
            <Field label="Queue # (manual)">
              <Input type="number" className="mono" value={job.qnum || ''} placeholder="auto"/>
            </Field>
          </Section>
        </div>

        {/* PARTS / STATUS */}
        <div style={{ gridColumn: 'span 4', display:'flex', flexDirection:'column', gap: 16 }}>
          <Section title="Parts / Status" gridCols="1fr 1fr">
            <Field label="Status">
              <Select value={job.status} onChange={e => onChange({ status: e.target.value })}>
                {Object.entries(STATUS).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}
              </Select>
            </Field>
            <Field label="Parts">
              <Select value={job.parts} onChange={e => onChange({ parts: e.target.value })}>
                {Object.entries(PARTS).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}
              </Select>
            </Field>
            <Field label="Parts ETA" span={2}>
              <Input type="date" className="mono" placeholder="—"/>
            </Field>
          </Section>

          <Section title="Actions" gridCols="1fr 1fr">
            <Btn variant="outline" size="sm">Move to QC</Btn>
            <Btn variant="outline" size="sm">Send to Pickup</Btn>
            <Btn variant="outline" size="sm">Mark Blocked</Btn>
            <Btn variant="danger" size="sm">Cancel job</Btn>
          </Section>
        </div>

        {/* NOTES — full row */}
        <div style={{ gridColumn: 'span 12', display:'grid', gridTemplateColumns:'1fr 1fr', gap: 18 }}>
          <Section title="Queue Note (visible to Tech)" gridCols="1fr">
            <Field label="Short note shown on queue card">
              <Textarea value={job.note} onChange={e => onChange({ note: e.target.value })}
                placeholder="e.g. Customer flexible, batch with j8 if possible."/>
            </Field>
          </Section>
          <Section title="Internal Notes (CS only)" gridCols="1fr">
            <Field label="Long-form notes, history, customer comms">
              <Textarea defaultValue="" placeholder="Internal CS notes — never shown on Tech view."
                style={{ minHeight: 70 }}/>
            </Field>
          </Section>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { DetailPanel });
