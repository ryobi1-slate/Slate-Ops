// Design notes block — appears under the prototype.
function DesignNotes() {
  const Card = ({ title, children, accent = 'sage' }) => (
    <section style={{
      background:'#fff', border:'1px solid var(--rule)',
      borderTop:`3px solid var(--${accent})`,
      padding: '16px 18px',
    }}>
      <h3 style={{ margin: 0, fontSize: 14, fontWeight: 500, letterSpacing:'0.01em' }}>{title}</h3>
      <div style={{ marginTop: 10, fontSize: 13, lineHeight: 1.55, color:'#2a2a2a' }}>{children}</div>
    </section>
  );
  const Bullets = ({ items }) => (
    <ul style={{ margin: 0, paddingLeft: 18, display:'flex', flexDirection:'column', gap: 4 }}>
      {items.map((it, i) => <li key={i}>{it}</li>)}
    </ul>
  );
  return (
    <div style={{ padding: '32px 28px 60px', background:'#f3f1e3', borderTop:'1px solid var(--rule)', marginTop: 28 }}>
      <div style={{ maxWidth: 1280, margin: '0 auto' }}>
        <div className="label" style={{ color:'var(--arches)' }}>Design Notes</div>
        <h2 style={{ margin:'4px 0 0', fontSize: 22, fontWeight: 500, letterSpacing:'-0.01em' }}>
          Combined CS Workspace + Queue — Future state
        </h2>
        <p style={{ marginTop: 6, color:'#5b5b5b', maxWidth: 760, fontSize: 13.5 }}>
          One page replaces the old Workspace iframe and the standalone Queue. The queue is the
          working surface; the legacy CS edit screen becomes a compact bottom panel that opens on
          row select. Designed for a WordPress plugin build — PHP + vanilla JS + a localized React
          bundle for the queue/detail interaction.
        </p>

        <div style={{ display:'grid', gridTemplateColumns:'repeat(3, 1fr)', gap: 14, marginTop: 18 }}>
          <Card title="Component list" accent="sage">
            <Bullets items={[
              'PageHeader — title, subtitle, action cluster',
              'FilterBar — search + status chips with counts',
              'WarningBanner — blocked-at-#1, duplicate, save success/failed',
              'TechGroup — collapsible, capacity meter, drop target',
              'JobRow — drag handle, queue # input, status/parts pills, tech, due, note, action',
              'DetailPanel — sticky bottom, six sections, internal scroll',
              'MobileFrame / MobileGroup / MobileSheet — stacked cards + bottom-sheet edit',
              'Atoms — Pill, Btn, Icon, TechAvatar, PriorityDot',
            ]}/>
          </Card>

          <Card title="Interaction rules" accent="redwood">
            <Bullets items={[
              'Row click selects job and opens bottom detail. Drag handle is the only drag target.',
              'Drag within a group reorders queue #. Drag across groups reassigns tech.',
              'All queue/order/tech changes are staged client-side; nothing posts until Save Changes.',
              'Dirty rows: subtle Sand fill + Sand-Deep left rule. Selected: Sage fill + Sage left rule.',
              'Manual Q# input is always editable as a keyboard fallback. Duplicates highlight in Arches.',
              'Blocked or Parts Hold at queue #1 surfaces a banner and a per-row "Blocking #1" tag.',
              'Save state on header button: idle → primary, saving → disabled, success → sage, error → arches.',
              'Leaving the page while dirty triggers the browser unload guard.',
            ]}/>
          </Card>

          <Card title="Build notes (Claude Code)" accent="arches">
            <Bullets items={[
              'Mount on /wp-admin/admin.php?page=slate-ops&tab=workspace. PHP renders shell + bootstraps a React island.',
              'React island: queue list + detail panel only. Everything else (left nav, top bar, page tabs) stays PHP/Twig.',
              'Use existing Slate Ops CSS tokens (sage/sand/arches/redwood). New CSS lives in slate-ops/assets/css/cs-workspace.css.',
              'REST endpoints: GET /jobs?scope=cs, PATCH /jobs/:id, POST /queue/reorder (batch), POST /queue/normalize.',
              'Reorder/reassign batches into a single transaction; server returns canonical queue numbers.',
              'Persist filter, group-collapse state, and last-selected job in localStorage keyed by user.',
              'Drag uses native HTML5 DnD — no library. Touch fallback uses long-press → arrow controls on mobile.',
              'Polling: refresh every 60s when idle, suspended while dirty. Manual Refresh shows "refreshed Xm ago".',
            ]}/>
          </Card>
        </div>

        <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap: 14, marginTop: 14 }}>
          <Card title="Remove from current pages" accent="redwood">
            <Bullets items={[
              'Remove the Workspace tab\'s legacy CS iframe entirely. Replaced by the detail panel.',
              'Remove the standalone Queue tab. Mark "removing" for one staging cycle, then delete.',
              'Remove "By Tech / All Jobs" toggle in the legacy table — grouping is the default now.',
              'Remove duplicated header KPIs (Pending / Ready to Build / Ready to Close) — folded into the filter bar counts.',
              'Drop the right-side persistent edit drawer from the legacy iframe.',
            ]}/>
          </Card>
          <Card title="Keep from existing pages" accent="sage">
            <Bullets items={[
              'Status & parts vocabulary — Pending, Scheduled, In Progress, Blocked, Parts Hold, Build Ready, Ready to Close.',
              'Tech grouping with per-tech queue numbers (the current Queue tab\'s core idea).',
              'Normalize Queue action — invaluable when queue numbers drift; promote to header.',
              'SO# / VIN / Salesperson identity fields — relocated into the detail panel.',
              'Sage / Sand / Arches / Redwood palette and Roboto Flex typography.',
              'Per-row queue note input — kept in the row; long notes move to detail panel.',
            ]}/>
          </Card>
        </div>

        <Card title="States covered in this prototype" accent="sage">
          <div style={{ display:'grid', gridTemplateColumns:'repeat(4, 1fr)', gap: 6, fontSize: 12.5 }}>
            <span>1 · Normal loaded</span>
            <span>2 · Dragging a job</span>
            <span>3 · Job dropped, dirty</span>
            <span>4 · Selected + bottom panel</span>
            <span>5 · Blocked at queue #1 warning</span>
            <span>6 · Duplicate queue warning</span>
            <span>7 · Empty tech group</span>
            <span>8 · Unassigned jobs</span>
            <span>9 · Save success</span>
            <span>10 · Save failed</span>
            <span>11 · Mobile</span>
            <span style={{ color:'#8a8a85' }}>Toggle via Tweaks panel ↘</span>
          </div>
        </Card>
      </div>
    </div>
  );
}

Object.assign(window, { DesignNotes });
