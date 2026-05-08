// Mobile combined view — collapsible tech sections, stacked cards, bottom-sheet detail.
function MobileFrame({ children }) {
  return (
    <div style={{
      width: 390, margin: '24px auto',
      background:'#fff', border:'1px solid var(--rule)',
      boxShadow:'0 24px 60px -30px rgba(15,52,42,0.25)',
      overflow:'hidden',
    }}>
      <div style={{
        height: 28, background:'#0a0a0a', color:'#f3f1e3',
        display:'flex', alignItems:'center', justifyContent:'space-between',
        padding:'0 14px', fontSize: 11, fontFamily:'"Roboto Mono"',
      }}>
        <span>10:42</span>
        <span>● ● ●</span>
      </div>
      {children}
    </div>
  );
}

function MobileJobCard({ job, qnum, selected, onSelect, dirty }) {
  return (
    <div onClick={() => onSelect(job.id)} style={{
      padding: '12px 14px',
      background: selected ? '#e3e8e5' : (dirty ? '#efeedc' : '#fff'),
      borderTop: '1px solid var(--rule-soft)',
      borderLeft: selected ? '3px solid var(--sage)' : (dirty ? '3px solid var(--sand-deep)' : '3px solid transparent'),
      display:'flex', gap: 10,
    }}>
      <div style={{
        color:'#8a8a85', display:'inline-flex', alignItems:'flex-start', paddingTop: 2,
      }}>
        <Icon.Drag/>
      </div>
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ display:'flex', alignItems:'center', gap: 8, marginBottom: 4 }}>
          <span className="mono" style={{
            fontSize: 11, padding:'1px 6px', background:'#efeedc', border:'1px solid var(--rule)',
          }}>#{qnum}</span>
          <span className="mono" style={{ fontSize: 11.5, color:'#1a1a1a' }}>{job.so}</span>
          <span style={{ marginLeft:'auto', fontSize: 11, color:'#5b5b5b' }} className="mono">{job.due || '—'}</span>
        </div>
        <div style={{ fontSize: 14, fontWeight: 500, whiteSpace:'nowrap', overflow:'hidden', textOverflow:'ellipsis' }}>
          {job.cust}
        </div>
        <div className="label" style={{ marginTop: 1 }}>{job.dealer || '—'}</div>
        <div style={{ display:'flex', gap: 6, marginTop: 8, flexWrap:'wrap' }}>
          <StatusPill kind={job.status}/>
          <PartsPill kind={job.parts}/>
          <span className="mono" style={{ fontSize: 11, color:'#5b5b5b' }}>{job.est.toFixed(1)}h</span>
        </div>
        {job.note && (
          <div style={{ marginTop: 6, fontSize: 12, color:'#5b5b5b', display:'flex', gap: 6 }}>
            <Icon.Note style={{ color:'#8a8a85', flex:'0 0 auto', marginTop: 2 }}/>
            <span>{job.note}</span>
          </div>
        )}
      </div>
    </div>
  );
}

function MobileGroup({ tech, jobs, selectedId, dirtyIds, onSelect, defaultOpen = true }) {
  const [open, setOpen] = useState(defaultOpen);
  const techMeta = window.WORKSPACE_DATA.TECHS.find(t => t.id === tech);
  return (
    <section style={{ borderTop: '1px solid var(--rule)' }}>
      <button onClick={() => setOpen(!open)} style={{
        width:'100%', textAlign:'left',
        display:'flex', alignItems:'center', gap: 10,
        padding:'12px 14px',
        background: tech === 'unassigned' ? '#efeedc' : '#faf8ed',
        border:'none', borderBottom:'1px solid var(--rule)',
      }}>
        <span style={{ color:'#5b5b5b', display:'inline-flex' }}>
          {open ? <Icon.Chev/> : <Icon.ChevR/>}
        </span>
        <TechAvatar tech={tech} size={24}/>
        <div style={{ flex: 1 }}>
          <div style={{ fontSize: 14, fontWeight: 500 }}>{techMeta?.name || 'Unassigned'}</div>
          <div className="label" style={{ marginTop: 1 }}>
            {jobs.length} {jobs.length === 1 ? 'job' : 'jobs'}
          </div>
        </div>
      </button>
      {open && jobs.map((j, idx) => (
        <MobileJobCard key={j.id} job={j} qnum={idx + 1}
          selected={selectedId === j.id}
          dirty={dirtyIds.has(j.id)}
          onSelect={onSelect}/>
      ))}
    </section>
  );
}

function MobileSheet({ job, onClose, onChange }) {
  if (!job) return null;
  return (
    <div style={{
      position:'absolute', left: 0, right: 0, bottom: 0, top: 60,
      background:'#fff', borderTop:'2px solid var(--redwood)',
      display:'flex', flexDirection:'column',
      animation:'slideup 240ms ease',
    }}>
      <div style={{
        padding:'10px 14px', background:'#0f342a', color:'#f3f1e3',
        display:'flex', alignItems:'center', justifyContent:'space-between',
      }}>
        <div style={{ minWidth: 0 }}>
          <div className="mono" style={{ fontSize: 10, opacity: 0.8, letterSpacing:'0.06em' }}>JOB DETAIL</div>
          <div style={{ fontSize: 14, fontWeight: 500, whiteSpace:'nowrap', overflow:'hidden', textOverflow:'ellipsis' }}>
            {job.cust}
          </div>
        </div>
        <button onClick={onClose} style={{ background:'transparent', border:'1px solid rgba(225,223,200,0.2)', color:'#e1dfc8', padding:'4px 6px' }}><Icon.X/></button>
      </div>
      <div style={{ overflow:'auto', padding: 14, display:'flex', flexDirection:'column', gap: 14 }}>
        <Section title="Identity" gridCols="1fr 1fr">
          <Field label="SO #"><Input className="mono" value={job.so} readOnly/></Field>
          <Field label="Dealer"><Input className="mono" value={job.dealer}/></Field>
          <Field label="Customer" span={2}><Input value={job.cust}/></Field>
        </Section>
        <Section title="Scheduling" gridCols="1fr 1fr">
          <Field label="Due"><Input type="date" className="mono" value={job.due}/></Field>
          <Field label="Hrs"><Input className="mono" value={job.est}/></Field>
          <Field label="Tech" span={2}>
            <Select value={job.tech} onChange={e => onChange({ tech: e.target.value })}>
              {window.WORKSPACE_DATA.TECHS.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
            </Select>
          </Field>
        </Section>
        <Section title="Status" gridCols="1fr 1fr">
          <Field label="Status">
            <Select value={job.status} onChange={e => onChange({ status: e.target.value })}>
              {Object.entries(window.WORKSPACE_DATA.STATUS).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}
            </Select>
          </Field>
          <Field label="Parts">
            <Select value={job.parts} onChange={e => onChange({ parts: e.target.value })}>
              {Object.entries(window.WORKSPACE_DATA.PARTS).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}
            </Select>
          </Field>
        </Section>
        <Section title="Note" gridCols="1fr">
          <Field label="Queue note"><Textarea value={job.note} onChange={e => onChange({ note: e.target.value })}/></Field>
        </Section>
      </div>
      <div style={{
        padding:'10px 14px', borderTop:'1px solid var(--rule)',
        display:'flex', gap: 8,
      }}>
        <Btn variant="outline" style={{ flex: 1 }}>Cancel</Btn>
        <Btn variant="primary" style={{ flex: 1 }} icon={<Icon.Save/>}>Save</Btn>
      </div>
    </div>
  );
}

function MobileView({ groups, selectedId, dirtyIds, selectedJob, onSelect, onCloseDetail, onChange }) {
  return (
    <MobileFrame>
      <div style={{ position:'relative', minHeight: 720 }}>
        <div style={{ padding: '12px 14px', borderBottom:'1px solid var(--rule)' }}>
          <div className="label" style={{ color:'var(--arches)' }}>CS / Supervisor</div>
          <div style={{ fontSize: 18, fontWeight: 500, marginTop: 2 }}>CS Workspace</div>
          <div style={{ display:'flex', gap: 6, marginTop: 10, overflow:'auto' }}>
            {['All','Ready','Scheduled','In Prog','Blocked','Closeout','Unassign'].map((t, i) => (
              <span key={t} style={{
                padding:'4px 9px', fontSize: 11.5,
                border:'1px solid ' + (i === 0 ? '#0f342a' : 'var(--rule)'),
                background: i === 0 ? '#0f342a' : '#fff',
                color: i === 0 ? '#f3f1e3' : '#1a1a1a',
                whiteSpace:'nowrap',
              }}>{t}</span>
            ))}
          </div>
        </div>
        <div style={{ display:'flex', justifyContent:'space-between', padding: '10px 14px', alignItems:'center' }}>
          <span className="label">Groups</span>
          <span style={{ fontSize: 11, color:'#8a8a85' }}>Reorder one group at a time</span>
        </div>
        {groups.map(g => (
          <MobileGroup key={g.tech} tech={g.tech} jobs={g.jobs}
            selectedId={selectedId} dirtyIds={dirtyIds}
            onSelect={onSelect} defaultOpen={g.tech !== 'unassigned'}/>
        ))}
        {selectedJob && (
          <MobileSheet job={selectedJob} onClose={onCloseDetail} onChange={onChange}/>
        )}
      </div>
    </MobileFrame>
  );
}

Object.assign(window, { MobileView });
