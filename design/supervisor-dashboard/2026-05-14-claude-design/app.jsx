/* Supervisor Dashboard — root app */

const { useState, useEffect } = React;

function App() {
  const [t, setTweak] = useTweaks(window.__TWEAK_DEFAULTS);
  const [active, setActive] = useState("overview");
  const [openJob, setOpenJob] = useState(null);
  const [refreshed, setRefreshed] = useState("just now");
  const [sidebar, setSidebar] = useState(true);
  const [isMobile, setIsMobile] = useState(false);

  // Force responsive mode if Tweaks says so
  useEffect(() => {
    if (t.responsiveMode === "tablet") {
      document.documentElement.style.setProperty("--force-w", "900px");
      setIsMobile(false);
    } else if (t.responsiveMode === "mobile") {
      document.documentElement.style.setProperty("--force-w", "420px");
      setIsMobile(true);
      setSidebar(false);
    } else {
      document.documentElement.style.removeProperty("--force-w");
      setIsMobile(false);
    }
  }, [t.responsiveMode]);

  // Refresh tick
  useEffect(() => {
    const t = setInterval(() => {
      setRefreshed((prev) => {
        if (prev === "just now") return "1m ago";
        const m = parseInt(prev);
        return isNaN(m) ? "1m ago" : `${m + 1}m ago`;
      });
    }, 60000);
    return () => clearInterval(t);
  }, []);

  const counts = {
    blocked: JOBS.filter(j => j.status === "blocked").length,
    qc:      JOBS.filter(j => j.status === "qc" || j.status === "rework").length,
    ready:   JOBS.filter(j => j.status === "ready").length
  };

  const handleAction = (kind) => {
    // Demo: any action closes drawer + bumps refreshed
    setRefreshed("just now");
  };

  const TabBody = () => {
    switch (active) {
      case "overview": return <TabOverview onOpenJob={setOpenJob} selectedJobId={openJob?.id} jobs={JOBS}/>;
      case "blocked":  return <TabBlocked onOpenJob={setOpenJob} selectedJobId={openJob?.id}/>;
      case "schedule": return <TabSchedule onOpenJob={setOpenJob}/>;
      case "techs":    return <TabTechs onOpenJob={setOpenJob}/>;
      case "qc":       return <TabQc onOpenJob={setOpenJob}/>;
      case "ready":    return <TabReady onOpenJob={setOpenJob}/>;
      default: return null;
    }
  };

  const showSidebar = t.showSidebar && !isMobile;

  return (
    <div className={`app ${!showSidebar ? "sidebar-collapsed" : ""} ${isMobile ? "mobile" : ""}`}>
      {showSidebar && <Sidebar collapsed={!sidebar} onToggle={() => setSidebar(!sidebar)} />}
      {!showSidebar && !isMobile && (
        <aside className="sidebar collapsed" style={{width: "var(--rail-w)"}}>
          <SlateMark width={28}/>
        </aside>
      )}

      <main className="main">
        <Topbar />
        <PageHeader onRefresh={() => setRefreshed("just now")} refreshed={refreshed}/>

        {/* KPI row */}
        <div className="kpi-grid">
          {KPIS.map(k => (
            <KpiCard key={k.key} kpi={k}
              density={t.kpiDensity}
              blockedEmphasis={t.blockedEmphasis}/>
          ))}
        </div>

        <Subtabs tabs={TAB_DEFS} active={active} onChange={setActive} counts={counts}/>

        <TabBody />

        <ComponentNotes />
      </main>

      <Drawer job={openJob} onClose={() => setOpenJob(null)} onAction={handleAction}/>

      <SupervisorTweaks t={t} setTweak={setTweak}/>
    </div>
  );
}

function SupervisorTweaks({ t, setTweak }) {
  return (
    <TweaksPanel title="Tweaks">
      <TweakSection title="KPI Row">
        <TweakRadio
          label="Density"
          value={t.kpiDensity}
          onChange={(v) => setTweak("kpiDensity", v)}
          options={[
            { value: "comfortable", label: "Comfortable" },
            { value: "compact",     label: "Compact" }
          ]}
        />
        <TweakSelect
          label="Blocked emphasis"
          value={t.blockedEmphasis}
          onChange={(v) => setTweak("blockedEmphasis", v)}
          options={[
            { value: "redwood", label: "Redwood fill (default)" },
            { value: "sage",    label: "Sage fill" },
            { value: "outline", label: "Outlined / inset rail" }
          ]}
        />
      </TweakSection>

      <TweakSection title="Layout">
        <TweakToggle
          label="Show full sidebar"
          value={t.showSidebar}
          onChange={(v) => setTweak("showSidebar", v)}
        />
        <TweakSelect
          label="Responsive preview"
          value={t.responsiveMode}
          onChange={(v) => setTweak("responsiveMode", v)}
          options={[
            { value: "desktop", label: "Desktop" },
            { value: "tablet",  label: "Tablet (narrow)" },
            { value: "mobile",  label: "Mobile (stacked)" }
          ]}
        />
      </TweakSection>
    </TweaksPanel>
  );
}

const root = ReactDOM.createRoot(document.getElementById("root"));
root.render(<App />);
