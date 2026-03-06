import React, { useState, useEffect } from 'react';
import { Sidebar } from './components/Sidebar';
import { BomList } from './components/BomList';
import { BomEditor } from './components/BomEditor';
import { CsDashboard } from './components/CsDashboard';
import { QcDashboard } from './components/QcDashboard';
import { ExecutiveDashboard } from './components/ExecutiveDashboard';
import { JobsDashboard } from './components/JobsDashboard';
import { SupervisorDashboard } from './components/SupervisorDashboard';
import { SettingsDashboard } from './components/SettingsDashboard';
import { ScheduleDashboard } from './components/ScheduleDashboard';
import { ItemsDashboard } from './components/ItemsDashboard';
import { ExternalPortalRedirect } from './components/ExternalPortalRedirect';
import { AppState, Bom, BomLine, BomSummary, Dealer, Job } from './types';
import { bomService, dealerService, jobsService, qcService, itemsService } from './services/api';

export default function App() {
  const [state, setState] = useState<AppState>({
    boms: [],
    dealers: [],
    jobs: [],
    qcInspections: [],
    items: [],
    active: { bom: null, lines: [] },
    ui: {
      selectedDealerId: null,
      isLoading: true,
      error: null,
      dirty: false,
      view: 'list'
    }
  });

  const [currentView, setCurrentView] = useState('bom'); // Default view

  // Mock WP Environment on Mount & Handle Routing
  useEffect(() => {
    if (!window.slateOpsSettings) {
      window.slateOpsSettings = {
        api: {
          root: 'http://localhost:3000/wp-json/',
          nonce: '', // Empty nonce triggers mock mode in services
        },
        user: { id: 1, name: 'Admin', caps: ['manage_options'], roles: ['administrator'] },
        colors: {},
        dealers: []
      };
    }

    // Load Initial Data
    const loadData = async () => {
      try {
        const [boms, dealers, jobs, qcInspections, items] = await Promise.all([
          bomService.getAll(),
          dealerService.getAll(),
          jobsService.getAll(),
          qcService.getAll(),
          itemsService.getAll()
        ]);
        
        setState(prev => ({
          ...prev,
          boms,
          dealers,
          jobs,
          qcInspections,
          items,
          ui: { ...prev.ui, isLoading: false }
        }));
      } catch (error) {
        console.error("Failed to load data:", error);
        setState(prev => ({
          ...prev,
          ui: { ...prev.ui, isLoading: false, error: 'Failed to load data' }
        }));
      }
    };

    loadData();

    // Simple URL-based routing for prototype
    const path = window.location.pathname;
    if (path.includes('/ops/cs')) {
      setCurrentView('cs');
    } else if (path.includes('/ops/bom')) {
      setCurrentView('bom');
    } else if (path.includes('/ops/qc')) {
      setCurrentView('qc');
    } else if (path.includes('/ops/exec')) {
      setCurrentView('exec');
    } else if (path.includes('/ops/jobs')) {
      setCurrentView('jobs');
    } else if (path.includes('/ops/supervisor')) {
      setCurrentView('supervisor');
    } else if (path.includes('/ops/settings')) {
      setCurrentView('settings');
    } else if (path.includes('/ops/schedule')) {
      setCurrentView('schedule');
    } else if (path.includes('/ops/items')) {
      setCurrentView('items');
    } else if (path.includes('/ops/quotes')) {
      setCurrentView('quotes');
    } else if (path.includes('/ops/dealers')) {
      setCurrentView('dealers');
    } else if (path.includes('/ops/new')) {
      setCurrentView('cs'); 
    } else {
      // Default to BOM for now if no match, or handle other routes
      setCurrentView('bom');
    }
  }, []);

  // ... (Handlers remain the same: handleSelectBom, handleDealerChange, etc.)
  const handleSelectBom = async (id: number) => {
    setState(prev => ({ ...prev, ui: { ...prev.ui, isLoading: true } }));
    
    try {
      const bom = await bomService.getById(id);
      setState(prev => ({
        ...prev,
        active: {
          bom: bom,
          lines: bom.lines || []
        },
        ui: { ...prev.ui, isLoading: false }
      }));
    } catch (error) {
      console.error("Failed to load BOM details:", error);
      setState(prev => ({
        ...prev,
        active: { bom: null, lines: [] },
        ui: { ...prev.ui, isLoading: false, error: 'Failed to load BOM' }
      }));
    }
  };

  const handleDealerChange = (dealerId: number | null) => {
    setState(prev => ({
      ...prev,
      ui: { ...prev.ui, selectedDealerId: dealerId }
    }));
  };

  const handleHeaderChange = (field: keyof Bom, value: any) => {
    if (!state.active.bom) return;
    
    setState(prev => ({
      ...prev,
      active: {
        ...prev.active,
        bom: prev.active.bom ? { ...prev.active.bom, [field]: value } : null
      },
      ui: { ...prev.ui, dirty: true }
    }));
  };

  const handleLineChange = (lineId: number, field: keyof BomLine, value: any) => {
    setState(prev => ({
      ...prev,
      active: {
        ...prev.active,
        lines: prev.active.lines.map(line => 
          line.id === lineId ? { ...line, [field]: value } : line
        )
      },
      ui: { ...prev.ui, dirty: true }
    }));
  };

  const handleAddLine = () => {
    if (!state.active.bom) return;

    const newLine: BomLine = {
      id: Date.now(),
      bom_id: state.active.bom.id,
      line_type: 'part',
      sku: '',
      description: '',
      qty: 1,
      unit_retail: 0,
      sort_order: state.active.lines.length + 1
    };

    setState(prev => ({
      ...prev,
      active: {
        ...prev.active,
        lines: [...prev.active.lines, newLine]
      },
      ui: { ...prev.ui, dirty: true }
    }));
  };

  const handleDeleteLine = (lineId: number) => {
    setState(prev => ({
      ...prev,
      active: {
        ...prev.active,
        lines: prev.active.lines.filter(l => l.id !== lineId)
      },
      ui: { ...prev.ui, dirty: true }
    }));
  };

  const handleCloneBom = async (mode: 'revision' | 'new_bom', newBomNo?: string) => {
    if (!state.active.bom) return;

    setState(prev => ({ ...prev, ui: { ...prev.ui, isLoading: true } }));

    try {
      // 1. Call Backend
      let result: { success: boolean; new_id: number };
      
      // Check if we are in standalone/dev mode (no backend)
      if (!window.slateOpsSettings) {
        throw new Error("Dev mode - fallback to local logic");
      }

      if (mode === 'revision') {
        result = await bomService.revise(state.active.bom.id);
      } else {
        result = await bomService.clone(state.active.bom.id, newBomNo);
      }

      // 2. Fetch New BOM Details
      const newBom = await bomService.getById(result.new_id);
      const newLines = newBom.lines || [];

      // 3. Update State
      const newSummary: BomSummary = {
        id: newBom.id,
        bom_no: newBom.bom_no,
        name: newBom.name,
        revision: newBom.revision,
        status: newBom.status,
        updated_at: newBom.updated_at
      };

      setState(prev => ({
        ...prev,
        boms: [newSummary, ...prev.boms],
        active: {
          bom: newBom,
          lines: newLines
        },
        ui: { ...prev.ui, isLoading: false, dirty: false }
      }));

    } catch (error) {
      console.log("Using local clone logic (Dev Mode or API Error)");
      
      // Fallback: Local Logic (Original Implementation)
      const currentBom = state.active.bom;
      const currentLines = state.active.lines;
      const newId = Date.now();

      let newBom: Bom;
      
      if (mode === 'revision') {
        const match = currentBom.revision.match(/R(\d+)/);
        const nextRev = match ? `R${parseInt(match[1]) + 1}` : 'R2';

        newBom = {
          ...currentBom,
          id: newId,
          revision: nextRev,
          status: 'draft',
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        };
      } else {
        newBom = {
          ...currentBom,
          id: newId,
          bom_no: newBomNo || `${currentBom.bom_no}-COPY`,
          revision: 'R1',
          status: 'draft',
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        };
      }

      const newLines = currentLines.map((line, index) => ({
        ...line,
        id: newId + index + 1,
        bom_id: newId
      }));

      const newSummary: BomSummary = {
        id: newBom.id,
        bom_no: newBom.bom_no,
        name: newBom.name,
        revision: newBom.revision,
        status: newBom.status,
        updated_at: newBom.updated_at
      };

      setState(prev => ({
        ...prev,
        boms: [newSummary, ...prev.boms],
        active: {
          bom: newBom,
          lines: newLines
        },
        ui: { ...prev.ui, isLoading: false, dirty: false }
      }));
    }
  };

  // Check if we are running inside the WP shell (prod) or standalone (dev)
  const isStandalone = !window.slateOpsSettings;

  const handleJobCreated = (newJob: Job) => {
    setState(prev => ({
      ...prev,
      jobs: [newJob, ...prev.jobs]
    }));
  };

  const renderContent = () => {
    switch (currentView) {
      case 'cs':
        return <CsDashboard dealers={state.dealers} jobs={state.jobs} onJobCreated={handleJobCreated} />;
      case 'qc':
        return <QcDashboard inspections={state.qcInspections} />;
      case 'exec':
        return <ExecutiveDashboard jobs={state.jobs} />;
      case 'jobs':
        return <JobsDashboard jobs={state.jobs} dealers={state.dealers} />;
      case 'supervisor':
        return <SupervisorDashboard jobs={state.jobs} />;
      case 'settings':
        return <SettingsDashboard />;
      case 'schedule':
        return <ScheduleDashboard jobs={state.jobs} />;
      case 'items':
        return <ItemsDashboard items={state.items} />;
      case 'quotes':
        return <ExternalPortalRedirect moduleName="Quotes" />;
      case 'dealers':
        return <ExternalPortalRedirect moduleName="Dealer Management" />;
      case 'bom':
      default:
        return (
          <>
            <BomList 
              boms={state.boms} 
              selectedId={state.active.bom?.id || null} 
              onSelect={handleSelectBom} 
            />
            <BomEditor 
              bom={state.active.bom} 
              lines={state.active.lines}
              dealers={state.dealers}
              selectedDealerId={state.ui.selectedDealerId}
              onDealerChange={handleDealerChange}
              onHeaderChange={handleHeaderChange}
              onLineChange={handleLineChange}
              onAddLine={handleAddLine}
              onDeleteLine={handleDeleteLine}
              onCloneBom={handleCloneBom}
            />
          </>
        );
    }
  };

  return (
    <div className="flex h-screen overflow-hidden bg-background-light font-sans text-slate-900">
      {/* Only render Sidebar in standalone mode (dev) */}
      {isStandalone && <Sidebar />}
      
      <main className="flex-1 flex overflow-hidden">
        {renderContent()}
      </main>
    </div>
  );
}
