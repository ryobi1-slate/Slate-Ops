// Queue list — grouped by tech, drag-reorderable rows.
const { useState, useRef, useEffect, useMemo } = React;

function FilterBar({ filter, setFilter, counts, query, setQuery }) {
  const tabs = [
    { id: 'all',       label: 'All',                count: counts.all },
    { id: 'ready',     label: 'Ready',              count: counts.ready },
    { id: 'scheduled', label: 'Scheduled',          count: counts.scheduled },
    { id: 'inprog',    label: 'In Progress',        count: counts.inprog },
    { id: 'blocked',   label: 'Blocked',            count: counts.blocked },
    { id: 'closeout',  label: 'Ready for Closeout', count: counts.closeout },
    { id: 'unassigned',label: 'Unassigned',         count: counts.unassigned },
    { id: 'parts',     label: 'Parts Hold',         count: counts.parts },
  ];
  return (
    <div style={{
      display:'flex', alignItems:'center', gap: 14,
      paddingBottom: 12,
      borderBottom: '1px solid var(--rule)',
      marginBottom: 12,
    }}>
      <div style={{
        display:'flex', alignItems:'center', gap: 8,
        background:'#fff', border:'1px solid var(--rule)',
        padding:'6px 10px', flex: '0 0 240px',
      }}>
        <Icon.Search style={{ color:'#8a8a85' }}/>
        <input
          value={query}
          onChange={e => setQuery(e.target.value)}
          placeholder="Search SO#, customer, VIN…"
          style={{ border:'none', outline:'none', background:'transparent', flex: 1, fontSize: 13 }}
        />
      </div>
      <div style={{ display:'flex', gap: 2, flexWrap:'wrap' }}>
        {tabs.map(t => {
          const active = filter === t.id;
          return (
            <button key={t.id} onClick={() => setFilter(t.id)} style={{
              padding: '6px 11px',
              border: '1px solid ' + (active ? '#0f342a' : 'var(--rule)'),
              background: active ? '#0f342a' : '#fff',
              color: active ? '#f3f1e3' : '#1a1a1a',
              fontSize: 12.5, fontWeight: active ? 500 : 400,
              letterSpacing:'0.01em',
              display:'inline-flex', alignItems:'center', gap: 6,
            }}>
              {t.label}
              <span style={{
                fontSize: 11,
                color: active ? '#e1dfc8' : '#8a8a85',
                fontVariantNumeric:'tabular-nums',
              }}>{t.count}</span>
            </button>
          );
        })}
      </div>
    </div>
  );
}

function WarningBanner({ kind, onDismiss }) {
  const map = {
    blockedAtTop: {
      tone: 'arches',
      title: 'Blocked job at queue #1',
      body: 'A Blocked or Parts Hold job is currently first in a tech\'s queue. Promote a workable job or resolve the block.',
    },
    duplicate: {
      tone: 'arches',
      title: 'Duplicate queue position',
      body: 'Two jobs share queue #2 in Marco Vela\'s group. Renumber or run Normalize Queue.',
    },
    saved: {
      tone: 'ok',
      title: 'Saved.',
      body: '4 queue changes and 1 reassignment committed at 10:42 AM.',
    },
    saveFailed: {
      tone: 'bad',
      title: 'Save failed — staging API 502',
      body: 'Your changes are still local. Retry or refresh; nothing was lost.',
    },
  }[kind];
  if (!map) return null;
  const palette = {
    arches: { bg:'#fbe6d2', bd:'#e9c79b', fg:'#7a3d0c', icon:'#7a3d0c' },
    ok:     { bg:'#d8e8df', bd:'#aac6b6', fg:'#1c4a32', icon:'#1c4a32' },
    bad:    { bg:'#f3d7d3', bd:'#dcb1ac', fg:'#651510', icon:'#651510' },
  }[map.tone];
  return (
    <div style={{
      display:'flex', alignItems:'flex-start', gap: 10,
      padding: '10px 12px',
      background: palette.bg, border: `1px solid ${palette.bd}`,
      color: palette.fg, marginBottom: 12, fontSize: 13,
    }}>
      <span style={{ marginTop: 2, color: palette.icon }}>
        {map.tone === 'ok' ? <Icon.Check/> : <Icon.Warn/>}
      </span>
      <div style={{ flex: 1 }}>
        <div style={{ fontWeight: 500 }}>{map.title}</div>
        <div style={{ marginTop: 2, opacity: 0.9 }}>{map.body}</div>
      </div>
      {onDismiss && <button onClick={onDismiss} style={{ background:'transparent', border:'none', color: palette.fg }}><Icon.X/></button>}
    </div>
  );
}

function HeaderRow() {
  return (
    <div style={{
      display:'grid',
      gridTemplateColumns: '24px 44px 110px 1fr 130px 90px 60px 130px 100px 1fr 90px',
      alignItems:'center', gap: 12,
      padding: '6px 12px',
      borderBottom: '1px solid var(--rule)',
      color:'#5b5b5b',
      fontSize: 10.5, letterSpacing:'0.08em', textTransform:'uppercase',
      background:'#faf8ed',
    }}>
      <span></span>
      <span>Q#</span>
      <span>SO#</span>
      <span>Customer / Dealer</span>
      <span>Status</span>
      <span>Parts</span>
      <span style={{ textAlign:'right' }}>Hrs</span>
      <span>Tech</span>
      <span>Due</span>
      <span>Note</span>
      <span style={{ textAlign:'right' }}>Action</span>
    </div>
  );
}

function JobRow({ job, qnum, isSelected, isDirty, isDuplicate, isBlockedTop, dragHandlers, isDragging, isDropTarget, onSelect, onQNumChange }) {
  const dirtyBg = isDirty ? '#efeedc' : 'transparent';
  const selBg   = isSelected ? '#e3e8e5' : dirtyBg;
  const borderL = isSelected ? '3px solid var(--sage)' : isDirty ? '3px solid var(--sand-deep)' : '3px solid transparent';
  const noteSnippet = job.note ? (job.note.length > 36 ? job.note.slice(0, 36) + '…' : job.note) : '';
  const due = job.due || '—';
  return (
    <div
      onClick={() => onSelect(job.id)}
      style={{
        display:'grid',
        gridTemplateColumns: '24px 44px 110px 1fr 130px 90px 60px 130px 100px 1fr 90px',
        alignItems:'center', gap: 12,
        padding: '8px 12px',
        background: selBg,
        borderLeft: borderL,
        borderBottom: '1px solid var(--rule-soft)',
        cursor: 'pointer',
        opacity: isDragging ? 0.45 : 1,
        outline: isDropTarget ? '2px dashed var(--sage)' : 'none',
        outlineOffset: -2,
        position:'relative',
      }}
    >
      <div
        {...dragHandlers}
        onClick={(e) => e.stopPropagation()}
        title="Drag to reorder or reassign"
        style={{
          color:'#8a8a85', cursor:'grab',
          display:'inline-flex', alignItems:'center', justifyContent:'center',
          padding: 2,
        }}
        onMouseDown={(e) => e.currentTarget.style.cursor = 'grabbing'}
        onMouseUp={(e)   => e.currentTarget.style.cursor = 'grab'}
      >
        <Icon.Drag/>
      </div>

      <input
        type="number" value={qnum} onChange={(e) => onQNumChange(job.id, e.target.value)}
        onClick={(e) => e.stopPropagation()}
        style={{
          width: 38, padding: '3px 6px',
          fontFamily:'"Roboto Mono"', fontSize: 12,
          textAlign:'center',
          border:'1px solid ' + (isDuplicate ? 'var(--arches)' : 'var(--rule)'),
          background:'#fff', borderRadius: 0,
        }}
      />

      <div className="mono" style={{ fontSize: 12, color:'#1a1a1a' }}>{job.so}</div>

      <div style={{ minWidth: 0 }}>
        <div style={{ fontSize: 13, fontWeight: 500, whiteSpace:'nowrap', overflow:'hidden', textOverflow:'ellipsis' }}>
          {job.cust}
        </div>
        <div className="mono" style={{ fontSize: 10.5, color:'#8a8a85', textTransform:'uppercase', letterSpacing:'0.06em' }}>
          {job.dealer || '—'}
        </div>
      </div>

      <StatusPill kind={job.status}/>
      <PartsPill kind={job.parts}/>

      <div className="mono" style={{ fontSize: 12, textAlign:'right', fontVariantNumeric:'tabular-nums' }}>{job.est.toFixed(1)}h</div>

      <div style={{ display:'inline-flex', alignItems:'center', gap: 8, minWidth: 0 }}>
        <TechAvatar tech={job.tech} size={20}/>
        <span style={{ fontSize: 12.5, whiteSpace:'nowrap', overflow:'hidden', textOverflow:'ellipsis' }}>
          {window.WORKSPACE_DATA.TECHS.find(t => t.id === job.tech)?.name || '—'}
        </span>
      </div>

      <div className="mono" style={{ fontSize: 12, color: due === '—' ? '#8a8a85' : '#1a1a1a' }}>{due}</div>

      <div style={{ display:'flex', alignItems:'center', gap: 8, minWidth: 0, color:'#5b5b5b' }}>
        {job.note ? <Icon.Note style={{ color:'#8a8a85', flex:'0 0 auto' }}/> : <span style={{ width: 14 }}/>}
        <span style={{ fontSize: 12.5, whiteSpace:'nowrap', overflow:'hidden', textOverflow:'ellipsis' }}>
          {noteSnippet || <span style={{ color:'#a8a59a' }}>—</span>}
        </span>
      </div>

      <div style={{ display:'flex', justifyContent:'flex-end', gap: 6 }}>
        <PriorityDot prio={job.prio}/>
        <button onClick={(e) => { e.stopPropagation(); onSelect(job.id); }}
          style={{ background:'transparent', border:'1px solid var(--rule)', padding:'4px 6px', color:'#5b5b5b' }}
          title="Open detail">
          <Icon.ChevR/>
        </button>
      </div>

      {isBlockedTop && (
        <div style={{
          position:'absolute', top: 6, right: 6,
          padding:'2px 6px', background:'var(--arches)', color:'#fff',
          fontSize: 9, letterSpacing:'0.08em', textTransform:'uppercase', fontWeight: 500,
        }}>Blocking #1</div>
      )}
    </div>
  );
}

function TechGroup({
  tech, jobs, allJobs,
  selectedId, dirtyIds, duplicates, blockedTopTech,
  onSelect, onReorder, onReassign, onQNumChange,
  forceDragId, dragTargetTech,
}) {
  const techMeta = window.WORKSPACE_DATA.TECHS.find(t => t.id === tech);
  const totalHrs = jobs.reduce((a, j) => a + j.est, 0);
  const [collapsed, setCollapsed] = useState(false);
  const [draggingId, setDraggingId] = useState(forceDragId || null);
  const [dropIndex, setDropIndex] = useState(null);
  const isDropTargetGroup = dragTargetTech === tech;

  const handleDragStart = (id) => (e) => {
    setDraggingId(id);
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', id);
  };
  const handleDragEnd = () => { setDraggingId(null); setDropIndex(null); };
  const handleRowDragOver = (idx) => (e) => {
    e.preventDefault();
    setDropIndex(idx);
  };
  const handleDrop = (idx) => (e) => {
    e.preventDefault();
    const id = e.dataTransfer.getData('text/plain');
    const fromJob = allJobs.find(j => j.id === id);
    if (!fromJob) return;
    if (fromJob.tech !== tech) onReassign(id, tech, idx);
    else onReorder(id, idx);
    setDraggingId(null); setDropIndex(null);
  };
  const handleGroupDragOver = (e) => { e.preventDefault(); };

  return (
    <section
      onDragOver={handleGroupDragOver}
      style={{
        background:'#fff',
        border: '1px solid ' + (isDropTargetGroup ? 'var(--sage)' : 'var(--rule)'),
        marginBottom: 14,
        boxShadow: isDropTargetGroup ? '0 0 0 2px rgba(64,79,75,0.15)' : 'none',
      }}>
      <header style={{
        display:'flex', alignItems:'center', justifyContent:'space-between',
        padding: '10px 14px',
        background: tech === 'unassigned' ? '#efeedc' : '#faf8ed',
        borderBottom: '1px solid var(--rule)',
      }}>
        <div style={{ display:'flex', alignItems:'center', gap: 10 }}>
          <button onClick={() => setCollapsed(!collapsed)}
            style={{ background:'transparent', border:'none', color:'#5b5b5b', display:'inline-flex' }}>
            {collapsed ? <Icon.ChevR/> : <Icon.Chev/>}
          </button>
          <TechAvatar tech={tech} size={26}/>
          <div>
            <div style={{ fontSize: 14, fontWeight: 500, letterSpacing:'0.01em' }}>
              {techMeta?.name || 'Unassigned'}
            </div>
            <div className="label" style={{ marginTop: 1 }}>
              {jobs.length} {jobs.length === 1 ? 'job' : 'jobs'} · {totalHrs.toFixed(1)} hrs
              {tech === 'unassigned' && <span style={{ color:'var(--arches)', marginLeft: 8 }}>· needs assignment</span>}
            </div>
          </div>
        </div>
        <div style={{ display:'flex', alignItems:'center', gap: 8 }}>
          {tech !== 'unassigned' && (
            <span style={{ fontSize: 11, color:'#5b5b5b' }}>
              Capacity {Math.min(100, Math.round((totalHrs / 60) * 100))}%
            </span>
          )}
          <button style={{ background:'transparent', border:'1px solid var(--rule)', padding:'4px 8px', fontSize: 12 }}>
            <Icon.Plus/> <span style={{ marginLeft: 4 }}>Add</span>
          </button>
        </div>
      </header>

      {!collapsed && (
        <>
          {jobs.length > 0 && <HeaderRow/>}
          {jobs.length === 0 ? (
            <div
              onDrop={handleDrop(0)}
              onDragOver={handleRowDragOver(0)}
              style={{
                padding: '22px 14px', textAlign:'center',
                color:'#8a8a85', fontSize: 13, fontStyle:'italic',
                background: dropIndex === 0 ? '#efeedc' : '#fff',
                borderTop: '1px dashed var(--rule)',
              }}>
              No jobs assigned. Drop a job here to assign to {techMeta?.name || 'this tech'}.
            </div>
          ) : (
            <div>
              {jobs.map((j, idx) => {
                const dragHandlers = {
                  draggable: true,
                  onDragStart: handleDragStart(j.id),
                  onDragEnd: handleDragEnd,
                };
                return (
                  <div
                    key={j.id}
                    onDragOver={handleRowDragOver(idx)}
                    onDrop={handleDrop(idx)}
                    style={{ position:'relative' }}
                  >
                    {dropIndex === idx && draggingId && draggingId !== j.id && (
                      <div style={{
                        position:'absolute', left: 0, right: 0, top: -1, height: 2,
                        background:'var(--sage)', zIndex: 2,
                      }}/>
                    )}
                    <JobRow
                      job={j} qnum={idx + 1}
                      isSelected={selectedId === j.id}
                      isDirty={dirtyIds.has(j.id)}
                      isDuplicate={duplicates.has(j.id)}
                      isBlockedTop={idx === 0 && (j.status === 'blocked' || j.status === 'parts')}
                      dragHandlers={dragHandlers}
                      isDragging={draggingId === j.id || forceDragId === j.id}
                      onSelect={onSelect}
                      onQNumChange={onQNumChange}
                    />
                  </div>
                );
              })}
              <div onDrop={handleDrop(jobs.length)} onDragOver={handleRowDragOver(jobs.length)}
                style={{ height: 8, background: dropIndex === jobs.length ? '#efeedc' : 'transparent' }}/>
            </div>
          )}
        </>
      )}
    </section>
  );
}

Object.assign(window, { FilterBar, WarningBanner, TechGroup });
