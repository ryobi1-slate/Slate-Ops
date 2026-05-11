// Main app — assembles the combined CS Workspace, manages state, exposes Tweaks.

const { TWEAK_DEFAULTS_HOLDER } = window;

// State preset definitions.
const STATE_PRESETS = {
  normal:        { label: '1. Normal loaded',           desc: 'Default workspace, nothing selected, no dirty rows.' },
  dragging:      { label: '2. Dragging a job',          desc: 'Earl Thompson is being dragged from Info → Marco group.' },
  dirty:         { label: '3. Dropped, unsaved',        desc: 'Three rows reordered, tech reassigned. Save button armed.' },
  selected:      { label: '4. Detail panel open',       desc: 'Earl Thompson selected; bottom detail panel showing.' },
  blockedTop:    { label: '5. Blocked at queue #1',     desc: 'Cory Hua (Blocked) sits at #1 of Marco\'s queue. Banner + tag.' },
  duplicate:     { label: '6. Duplicate queue #',       desc: 'Two jobs share queue #2 in Marco\'s group; banner + arches outline.' },
  empty:         { label: '7. Empty tech group',        desc: 'Devon Ruiz has no jobs — drop target visible.' },
  unassigned:    { label: '8. Unassigned focus',        desc: 'Filter chip: Unassigned. Other groups hidden.' },
  saved:         { label: '9. Save success',            desc: 'Sage success banner; dirty state cleared.' },
  saveFailed:    { label: '10. Save failed',            desc: 'Bad-tone banner; dirty state preserved for retry.' },
  mobile:        { label: '11. Mobile view',            desc: 'Stacked cards, collapsible groups, bottom-sheet edit.' },
};

const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "state": "normal",
  "showShell": true,
  "showNotes": true,
  "density": "comfortable",
  "accent": "redwood"
}/*EDITMODE-END*/;

function App() {
  const [t, setTweak] = useTweaks(TWEAK_DEFAULTS);
  const [jobs, setJobs] = useState(() => window.WORKSPACE_DATA.SEED.map(j => ({ ...j })));
  const [filter, setFilter] = useState('all');
  const [query, setQuery] = useState('');
  const [selectedId, setSelectedId] = useState(null);
  const [dirtyIds, setDirtyIds] = useState(new Set());
  const [duplicates, setDuplicates] = useState(new Set());
  const [saveState, setSaveState] = useState('idle'); // idle | saving | success | error
  const [bannerDismissed, setBannerDismissed] = useState({});

  // Apply state preset side-effects.
  useEffect(() => {
    const s = t.state;
    setSelectedId(null);
    setDirtyIds(new Set());
    setDuplicates(new Set());
    setSaveState('idle');
    setFilter('all');
    setBannerDismissed({});

    let next = window.WORKSPACE_DATA.SEED.map(j => ({ ...j }));

    if (s === 'dirty' || s === 'dragging' || s === 'saveFailed') {
      // Reorder Info group + reassign one to Marco
      next = next.map(j => {
        if (j.id === 'j3') return { ...j, tech: 'marco' };
        return j;
      });
      setDirtyIds(new Set(['j3','j1','j2','j5']));
      if (s === 'saveFailed') setSaveState('error');
    }
    if (s === 'selected') {
      setSelectedId('j3');
    }
    if (s === 'blockedTop') {
      // Make Cory Hua (j7) the first item in Marco's group already (it is)
      // ensure we keep status blocked at top.
    }
    if (s === 'duplicate') {
      setDuplicates(new Set(['j8','j9']));
    }
    if (s === 'empty') {
      // Devon group is naturally empty since SEED has no devon jobs.
    }
    if (s === 'unassigned') {
      setFilter('unassigned');
    }
    if (s === 'saved') {
      setDirtyIds(new Set());
      setSaveState('success');
    }
    setJobs(next);
  }, [t.state]);

  // Derive groups in tech order; filter by filter+query.
  const filtered = useMemo(() => {
    return jobs.filter(j => {
      if (filter === 'all') return true;
      if (filter === 'unassigned') return j.tech === 'unassigned';
      if (filter === 'parts') return j.parts === 'partial' || j.parts === 'hold' || j.status === 'parts';
      return j.status === filter;
    }).filter(j => {
      if (!query) return true;
      const q = query.toLowerCase();
      return [j.so, j.cust, j.dealer, j.vin, j.note].some(v => (v||'').toLowerCase().includes(q));
    });
  }, [jobs, filter, query]);

  const groups = useMemo(() => {
    const techOrder = ['unassigned','jake','info','marco','priya','devon'];
    return techOrder.map(tech => ({
      tech,
      jobs: filtered.filter(j => j.tech === tech),
    }));
  }, [filtered]);

  const counts = useMemo(() => ({
    all: jobs.length,
    ready: jobs.filter(j => j.status === 'ready').length,
    scheduled: jobs.filter(j => j.status === 'scheduled').length,
    inprog: jobs.filter(j => j.status === 'inprog').length,
    blocked: jobs.filter(j => j.status === 'blocked').length,
    closeout: jobs.filter(j => j.status === 'closeout').length,
    unassigned: jobs.filter(j => j.tech === 'unassigned').length,
    parts: jobs.filter(j => j.parts === 'partial' || j.parts === 'hold' || j.status === 'parts').length,
  }), [jobs]);

  const selectedJob = jobs.find(j => j.id === selectedId);

  // Handlers
  const onSelect = (id) => setSelectedId(id === selectedId ? null : id);
  const onCloseDetail = () => setSelectedId(null);

  const onChange = (patch) => {
    setJobs(js => js.map(j => j.id === selectedId ? { ...j, ...patch } : j));
    setDirtyIds(s => new Set([...s, selectedId]));
  };

  const onReorder = (id, idx) => {
    setJobs(js => {
      const job = js.find(j => j.id === id);
      if (!job) return js;
      const inGroup = js.filter(j => j.tech === job.tech && j.id !== id);
      inGroup.splice(idx, 0, job);
      const others = js.filter(j => j.tech !== job.tech);
      return [...others, ...inGroup];
    });
    setDirtyIds(s => new Set([...s, id]));
  };
  const onReassign = (id, newTech, idx) => {
    setJobs(js => {
      const job = js.find(j => j.id === id);
      if (!job) return js;
      const updated = { ...job, tech: newTech };
      const inGroup = js.filter(j => j.tech === newTech && j.id !== id);
      inGroup.splice(idx, 0, updated);
      const others = js.filter(j => j.tech !== newTech && j.id !== id);
      return [...others, ...inGroup];
    });
    setDirtyIds(s => new Set([...s, id]));
  };
  const onQNumChange = (id, val) => {
    setDirtyIds(s => new Set([...s, id]));
  };

  const onSave = () => {
    if (dirtyIds.size === 0) return;
    setSaveState('saving');
    setTimeout(() => {
      setSaveState('success');
      setDirtyIds(new Set());
      setTimeout(() => setSaveState('idle'), 2200);
    }, 600);
  };
  const onNormalize = () => {
    // A no-op visual hint: mark all in-group with a tiny dirty pulse.
    setDirtyIds(new Set(jobs.map(j => j.id)));
    setTimeout(() => setDirtyIds(new Set()), 700);
  };
  const onRefresh = () => {};

  // Compute banner state.
  const banners = [];
  if (t.state === 'blockedTop' && !bannerDismissed.blockedAtTop) banners.push('blockedAtTop');
  if (t.state === 'duplicate' && !bannerDismissed.duplicate) banners.push('duplicate');
  if (saveState === 'success') banners.push('saved');
  if (saveState === 'error') banners.push('saveFailed');

  const isMobile = t.state === 'mobile';

  // Tweak preset → forced visual: dragging shows row at lowered opacity + drop indicator.
  const forceDragId = t.state === 'dragging' ? 'j3' : null;
  const dragTargetTech = t.state === 'dragging' ? 'marco' : null;

  return (
    <div style={{ minHeight:'100vh', background:'var(--bg)' }}>
      {t.showShell && !isMobile && <TopBar/>}
      <div style={{ display: isMobile ? 'block' : 'flex', minHeight: isMobile ? 'auto' : 'calc(100vh - 48px)' }}>
        {t.showShell && !isMobile && <Sidebar/>}
        <main style={{ flex: 1, padding: isMobile ? 0 : '0 28px 0', minWidth: 0 }}>
          {!isMobile && (
            <>
              <PageHeader
                dirtyCount={dirtyIds.size}
                onSave={onSave}
                onNormalize={onNormalize}
                onRefresh={onRefresh}
                saveState={saveState}
              />
              <PageTabs active="workspace"/>
              <div style={{ paddingTop: 14 }}>
                <FilterBar filter={filter} setFilter={setFilter} counts={counts} query={query} setQuery={setQuery}/>

                {banners.map(b => (
                  <WarningBanner key={b} kind={b}
                    onDismiss={() => setBannerDismissed(d => ({ ...d, [b]: true }))}/>
                ))}

                {groups.map(g => (g.jobs.length > 0 || g.tech === 'unassigned' || (t.state === 'empty' && g.tech === 'devon')) && (
                  <TechGroup
                    key={g.tech}
                    tech={g.tech}
                    jobs={g.jobs}
                    allJobs={jobs}
                    selectedId={selectedId}
                    dirtyIds={dirtyIds}
                    duplicates={duplicates}
                    onSelect={onSelect}
                    onReorder={onReorder}
                    onReassign={onReassign}
                    onQNumChange={onQNumChange}
                    forceDragId={forceDragId}
                    dragTargetTech={dragTargetTech}
                  />
                ))}

                {selectedJob && (
                  <DetailPanel
                    job={selectedJob}
                    dirty={dirtyIds.has(selectedJob.id)}
                    onSave={onSave}
                    onCancel={() => onCloseDetail()}
                    onClose={() => onCloseDetail()}
                    onChange={onChange}
                  />
                )}
              </div>
            </>
          )}

          {isMobile && (
            <MobileView
              groups={groups.filter(g => g.jobs.length > 0 || g.tech === 'unassigned')}
              selectedId={selectedId}
              dirtyIds={dirtyIds}
              selectedJob={selectedJob}
              onSelect={onSelect}
              onCloseDetail={onCloseDetail}
              onChange={onChange}
            />
          )}
        </main>
      </div>

      {t.showNotes && <DesignNotes/>}

      <TweaksPanel title="CS Workspace · Tweaks">
        <TweakSection label="Demo state">
          <TweakSelect
            label="State preset"
            value={t.state}
            options={Object.entries(STATE_PRESETS).map(([id, p]) => ({ value: id, label: p.label }))}
            onChange={v => setTweak('state', v)}
          />
          <div style={{ fontSize: 12, color:'#8a8a85', lineHeight: 1.45, padding: '4px 2px' }}>
            {STATE_PRESETS[t.state]?.desc}
          </div>
        </TweakSection>
        <TweakSection label="Display">
          <TweakToggle label="Show app shell" value={t.showShell} onChange={v => setTweak('showShell', v)}/>
          <TweakToggle label="Show design notes" value={t.showNotes} onChange={v => setTweak('showNotes', v)}/>
        </TweakSection>
      </TweaksPanel>

      <style>{`
        @keyframes slideup { from { transform: translateY(20px); opacity: 0;} to { transform: translateY(0); opacity: 1;} }
      `}</style>
    </div>
  );
}

const root = ReactDOM.createRoot(document.getElementById('root'));
root.render(<App/>);
