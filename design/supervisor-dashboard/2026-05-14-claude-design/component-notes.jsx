/* Component notes block — designer-to-developer reference */

const ComponentNotes = () => (
  <section className="notes-section" data-screen-label="08 Component Notes">
    <div className="eyebrow">Handoff</div>
    <h2 className="page-title" style={{fontSize: 32}}>Component Notes</h2>
    <p className="page-subtitle">
      Tokens, components, and patterns used on this page. All implementable in PHP-rendered HTML with
      vanilla JS — no React dependency in production.
    </p>

    <div className="notes-grid">
      <div className="note-card">
        <h4>Color tokens</h4>
        <div className="swatches">
          <div className="swatch" style={{background: "#404f4b"}} title="Sage"/>
          <div className="swatch" style={{background: "#0f342a"}} title="Redwood"/>
          <div className="swatch" style={{background: "#d86b19"}} title="Arches"/>
          <div className="swatch" style={{background: "#e1dfc8"}} title="Sand"/>
          <div className="swatch" style={{background: "#f4f2e3", border: "1px solid var(--line)"}} title="Page bg"/>
          <div className="swatch" style={{background: "#ffffff", border: "1px solid var(--line)"}} title="Surface"/>
        </div>
        <p>Sage is the dominant action color. Sand &amp; off-white carry the page. Arches is reserved for alert dots and small badges. Redwood is for serious states or the blocked-emphasis KPI background.</p>
        <p><code>--sage</code> <code>--sand</code> <code>--arches</code> <code>--redwood</code></p>
      </div>

      <div className="note-card">
        <h4>Typography</h4>
        <p>Roboto Flex with width variation. Eyebrow / labels at 10px / 900 / 0.22em uppercase. Page title condensed (wdth 90, opsz 60).</p>
        <ul>
          <li>Eyebrow — 10/900/uppercase, Arches color on page header, muted elsewhere</li>
          <li>Title — 44px condensed, weight 600</li>
          <li>Body — 14px regular, ink-2 color</li>
          <li>Job IDs &amp; numerics — Roboto Mono</li>
        </ul>
      </div>

      <div className="note-card">
        <h4>KPI card</h4>
        <p>White surface, 1px sand border. Large ultralight numeral. Top label uppercase. Optional dot before label encodes severity.</p>
        <p>Blocked KPI gets a heavier treatment — Redwood background with Arches accent dot. Three variants offered via Tweaks.</p>
        <p><code>.kpi</code> <code>.kpi--blocked</code> <code>.density-compact</code></p>
      </div>

      <div className="note-card">
        <h4>Table</h4>
        <p>Dense tables. Column headers are small uppercase muted. Rows are clickable to open the drawer. Blocked rows use a faint Arches-tinted background.</p>
        <ul>
          <li>Row height ≈ 44px</li>
          <li>Hover: Sand-50 background</li>
          <li>Selected: Sand-100 background</li>
          <li>Blocked: Arches @ 4% opacity</li>
        </ul>
      </div>

      <div className="note-card">
        <h4>Sub-tabs</h4>
        <p>Underline-style. Active tab uses Sage 2px bottom border and 600 weight. Optional inline count chip on the right of each label.</p>
        <p><code>.subtabs</code> <code>.subtab.active</code> <code>.subtab .count</code></p>
      </div>

      <div className="note-card">
        <h4>Buttons</h4>
        <div style={{display: "flex", gap: 6, flexWrap: "wrap", margin: "6px 0 10px"}}>
          <button className="btn btn--sm btn--primary">Primary</button>
          <button className="btn btn--sm">Default</button>
          <button className="btn btn--sm btn--ghost">Ghost</button>
          <button className="btn btn--sm btn--danger">Danger</button>
        </div>
        <p>Uppercase, 12px, 0.04em tracking. Sage primary. Default has stronger sand border. Danger uses Redwood outline → fill on hover.</p>
      </div>

      <div className="note-card">
        <h4>Status pills</h4>
        <div style={{display: "flex", gap: 6, flexWrap: "wrap", margin: "4px 0 8px"}}>
          <span className="pill pill--blocked"><span className="dot"/>Blocked</span>
          <span className="pill pill--progress"><span className="dot"/>In Progress</span>
          <span className="pill pill--qc"><span className="dot"/>In QC</span>
          <span className="pill pill--rework"><span className="dot"/>Rework</span>
          <span className="pill pill--ready"><span className="dot"/>Ready</span>
          <span className="pill pill--complete"><span className="dot"/>Complete</span>
        </div>
        <p>All pills share shape and 6px dot. Colors mapped from Sand / Arches / Sage / Redwood / Ok states. Never bright SaaS reds or greens.</p>
      </div>

      <div className="note-card">
        <h4>Drawer</h4>
        <p>Right-side, 440px max. Slides in over a Redwood-tinted scrim. ESC closes. Header carries job ID, status pill, dealer/SO meta. Footer has Close + View Full Job actions.</p>
        <p>Sections: blocker callout (when present), key-value grid, notes/activity, supervisor actions.</p>
        <p><code>.drawer</code> <code>.drawer.open</code></p>
      </div>

      <div className="note-card">
        <h4>Filter chips</h4>
        <p>Pill-shaped buttons with Sand border. Active state flips to filled Sage. Inline mono count tucked to the right of label.</p>
        <p>Used for the Blocked Jobs category filter, Techs status filter, and Overview quick filters.</p>
      </div>

      <div className="note-card">
        <h4>Future-state cards</h4>
        <p>Dashed Sand border + "Planned" tag in the corner. Used so the Schedule tab can ship today with empty slots for the eventual scheduler without making the page dependent on it.</p>
        <p><code>.future-card</code></p>
      </div>

      <div className="note-card">
        <h4>Implementation notes</h4>
        <ul>
          <li>Tokens live in <code>:root</code> CSS variables — zero JS to theme.</li>
          <li>All interactive bits are vanilla event handlers — the drawer is a simple toggled class.</li>
          <li>No animations beyond the 0.18s drawer slide and the live-dot pulse.</li>
          <li>Tables collapse to horizontal-scroll on tablet; mobile stacks the KPI grid 2-up.</li>
        </ul>
      </div>

      <div className="note-card">
        <h4>Accessibility</h4>
        <ul>
          <li>Tabs use <code>role="tab"</code> and <code>aria-selected</code>.</li>
          <li>Drawer scrim is clickable, ESC closes.</li>
          <li>All status info is text-labelled, not color-only.</li>
          <li>Prefers-reduced-motion disables transitions.</li>
        </ul>
      </div>
    </div>
  </section>
);

Object.assign(window, { ComponentNotes });
