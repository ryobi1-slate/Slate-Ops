import React, { useEffect, useState, useCallback } from 'react';
import { Dashboard } from './components/Dashboard';
import { Job, MonitorData } from './types';

// Define the interface for the global settings injected by WordPress
declare global {
  interface Window {
    slateMonitorSettings?: {
      root: string;
      nonce: string;
      interval: string;
      scale?: string;
      pluginUrl?: string;
      version?: string;
    };
  }
}

// Default settings for development fallback
const DEFAULT_SETTINGS = {
  root: '/wp-json/slate-monitor/v1/jobs',
  interval: '60000',
  nonce: '',
  scale: '1',
  pluginUrl: '',
  version: ''
};

// MOCK DATA FOR DEMONSTRATION
const MOCK_JOBS: Job[] = [
  {
    id: '101',
    so_number: 'S-ORD101411',
    vin: '90ABCDEF',
    dealer: 'MBWIL',
    customer: 'John Doe',
    sales_person: 'Alice Smith',
    assigned_tech: 'Bob Jones',
    start_date: '10/24',
    due_date: '10/26',
    time_estimate: '6h',
    notes: 'Waiting on brake pads from OEM. ETA tomorrow morning.',
    status: 'DELAY – PARTS'
  },
  {
    id: '102',
    so_number: 'S-ORD99281',
    vin: 'XY928371',
    dealer: '',
    customer: 'Jane Rogue',
    sales_person: 'Unassigned',
    assigned_tech: 'Mike Ross',
    start_date: '10/25',
    due_date: '10/27',
    time_estimate: '12h',
    notes: 'Customer requested callback regarding tire options before proceeding.',
    status: 'DELAY – UPDATE REQUIRED'
  },
  {
    id: '103',
    so_number: 'S-ORD77123',
    vin: 'WIN112233',
    dealer: 'MBSEA',
    customer: '',
    sales_person: 'Tom Cruz',
    assigned_tech: 'Unassigned',
    start_date: '',
    due_date: '',
    time_estimate: '2h',
    notes: 'Vehicle arriving via tow truck. Front bumper damage reported. Key in lockbox 4590.',
    status: 'SCHEDULED – NOT ARRIVED'
  },
  {
    id: '104',
    so_number: 'S-ORD55511',
    vin: 'FASTCAR1',
    dealer: 'GTOY',
    customer: 'Acme Corp',
    sales_person: 'Sarah Connor',
    assigned_tech: 'T-800',
    start_date: '10/20',
    due_date: '10/22',
    time_estimate: '48h',
    notes: 'Heavy rust on undercarriage. Inspect carefully. Client wants photos.',
    status: 'IN PROGRESS'
  },
  {
    id: '105',
    so_number: 'S-ORD44112',
    vin: 'OLDTIMER',
    dealer: 'MBWIL',
    customer: 'Local PD',
    sales_person: 'Gordon',
    assigned_tech: 'Dent',
    start_date: '10/21',
    due_date: '10/21',
    time_estimate: '1h',
    notes: 'Standard oil change and multi-point inspection.',
    status: 'QUALITY CONTROL'
  },
  {
    id: '106',
    so_number: 'S-ORD00099',
    vin: 'LASTONE1',
    dealer: 'MBWIL',
    customer: 'Wayne Ent',
    sales_person: 'Bruce',
    assigned_tech: 'Lucius',
    start_date: '10/10',
    due_date: '10/12',
    time_estimate: '4h',
    notes: 'Wash and wax complete. Ready for pickup.',
    status: 'COMPLETE – AWAITING PICKUP'
  },
  {
    id: '107',
    so_number: 'S-ORD22188',
    vin: 'ELEC-001',
    dealer: 'MBSEA',
    customer: 'Stark Ind',
    sales_person: 'Pepper',
    assigned_tech: 'Jarvis',
    start_date: '10/28',
    due_date: '10/29',
    time_estimate: '8h',
    notes: 'Firmware update required.',
    status: 'SCHEDULED – READY TO START'
  }
];

const getSettings = () => {
  return window.slateMonitorSettings || DEFAULT_SETTINGS;
};

const App: React.FC = () => {
  const [data, setData] = useState<MonitorData>({ jobs: [] });
  const [loading, setLoading] = useState<boolean>(true);
  const [lastUpdated, setLastUpdated] = useState<Date>(new Date());
  
  // Real-time clock and Health monitoring
  const [currentTime, setCurrentTime] = useState<Date>(new Date());
  const [failureCount, setFailureCount] = useState<number>(0);
  
  const settings = getSettings();
  const pollInterval = parseInt(settings.interval, 10) || 60000;
  const uiScale = parseFloat(settings.scale || '1');

  // Clock Ticker
  useEffect(() => {
    const timer = setInterval(() => setCurrentTime(new Date()), 1000);
    return () => clearInterval(timer);
  }, []);

  const fetchData = useCallback(async () => {
    try {
      const response = await fetch(settings.root, {
        headers: {
            'X-WP-Nonce': settings.nonce
        }
      });
      
      if (!response.ok) {
        throw new Error(`HTTP Error: ${response.status}`);
      }

      const jsonData = await response.json();
      
      if (jsonData && Array.isArray(jsonData.jobs)) {
        setData(jsonData);
        setLastUpdated(new Date());
        setFailureCount(0); // Reset failures on success
      } else {
        setData({ jobs: [] }); 
        setLastUpdated(new Date());
        setFailureCount(0);
      }
    } catch (err) {
      console.warn("API Connection failed, falling back to MOCK DATA for demonstration.", err);
      // Increment failure count to trigger status change
      setFailureCount(prev => prev + 1);

      // Fallback to Mock Data if no data present
      if (loading) {
         setData({ jobs: MOCK_JOBS });
      }
    } finally {
      setLoading(false);
    }
  }, [settings.root, settings.nonce, loading]);

  useEffect(() => {
    fetchData();
    const intervalId = setInterval(fetchData, pollInterval);
    return () => clearInterval(intervalId);
  }, [fetchData, pollInterval]);

  // Apply scale to body/root for zoom effect if needed
  const contentStyle = {
    transform: `scale(${uiScale})`,
    transformOrigin: 'top left',
    width: `${100 / uiScale}vw`,
    height: `${100 / uiScale}vh`
  };

  if (loading && data.jobs.length === 0) {
    return (
      <div className="flex items-center justify-center h-screen w-screen bg-slate-950 text-slate-400">
        <div className="flex flex-col items-center gap-4">
           <div className="w-12 h-12 border-4 border-slate-800 border-t-slate-500 rounded-full animate-spin"></div>
        </div>
      </div>
    );
  }

  // Status Logic - Production Grade (No Animations)
  let statusColor = 'bg-emerald-500';
  let statusText = 'text-emerald-500';
  let statusLabel = 'LIVE';

  if (failureCount > 0) {
    if (failureCount < 2) {
      // First failure (Amber)
      statusColor = 'bg-amber-500';
      statusText = 'text-amber-500';
      statusLabel = 'LIVE'; 
    } else {
      // Repeated failures (Red)
      statusColor = 'bg-red-600';
      statusText = 'text-red-600';
      statusLabel = 'OFFLINE';
    }
  }

  return (
    <div style={contentStyle} className="bg-slate-950 flex flex-col overflow-hidden font-sans text-slate-100 selection:bg-slate-700/50">
      {/* Industrial Header */}
      <div className="h-16 bg-transparent flex items-center justify-between px-6 shrink-0 z-20">
        
        {/* Left: Branding */}
        <div className="flex items-center">
          <img
            src={`${settings.pluginUrl || ''}assets/slate-logo.svg`}
            alt="Slate"
            className="h-9 w-auto"
            draggable={false}
          />
        </div>
        
        {/* Right: Clock & Status */}
        <div className="flex flex-col items-end justify-center">
            <div className="flex items-center gap-5">
                {/* LIVE Indicator - Static, calm */}
                <div className="flex items-center gap-2">
                    <div className={`w-1.5 h-1.5 rounded-full ${statusColor}`}></div>
                    <span className={`text-[9px] font-black tracking-[0.2em] uppercase ${statusText}`}>
                        {statusLabel}
                    </span>
                </div>

                {/* Real-time Clock */}
                <div className="font-mono text-lg font-bold text-slate-100 leading-none">
                    {currentTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                </div>
            </div>

            {/* Last Sync Timestamp */}
            <div className="mt-0.5 flex items-center gap-2">
                 <span className="text-[9px] font-bold uppercase tracking-wide text-slate-600">Last Sync</span>
                 <span className="font-mono text-[9px] text-slate-500">
                    {lastUpdated.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                 </span>
            </div>
            {/* Version chip */}
            {settings.version && (
              <div className="mt-0.5 font-mono text-[9px] text-slate-700 opacity-60">
                Slate Ops v{settings.version}
              </div>
            )}
        </div>
      </div>

      {/* Main Board */}
      <div className="flex-1 overflow-hidden relative">
        <Dashboard jobs={data.jobs} />
      </div>
    </div>
  );
};

export default App;