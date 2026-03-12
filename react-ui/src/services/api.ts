import { AppState, Bom, BomLine, BomSummary, Dealer, Job, QcInspection, ProductionBay, Item, WorkCenter, CapacitySummary, BufferSettings } from '../types';

// --- CONFIGURATION ---

const API_BASE_URL = typeof window !== 'undefined' && window.slateOpsSettings?.api?.root 
  ? window.slateOpsSettings.api.root.replace(/\/$/, '') 
  : '/wp-json/slate-ops/v1';

const NONCE = typeof window !== 'undefined' && window.slateOpsSettings?.api?.nonce 
  ? window.slateOpsSettings.api.nonce 
  : '';

// Helper to handle API responses
async function handleResponse<T>(response: Response): Promise<T> {
  const rawBody = await response.text();

  if (!response.ok) {
    let message = `API Error: ${response.statusText}`;
    if (rawBody) {
      try {
        const parsedError = JSON.parse(rawBody);
        message = parsedError?.message || rawBody;
      } catch {
        message = rawBody;
      }
    }
    throw new Error(message);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  if (!rawBody.trim()) {
    throw new Error(`API returned status ${response.status} but the response body is empty.`);
  }

  try {
    return JSON.parse(rawBody) as T;
  } catch {
    return rawBody as T;
  }
}
// Helper for headers
function getHeaders() {
  return {
    'Content-Type': 'application/json',
    'X-WP-Nonce': NONCE,
  };
}

// --- MOCK DATA (Fallback) ---
// Kept here for development when API is unreachable or for testing
const MOCK_BOMS: BomSummary[] = [
  { id: 101, bom_no: 'BOM-101', name: '2024 Ford Transit - Base Upfit', revision: 'R1', status: 'draft', updated_at: '2023-10-24' },
  { id: 102, bom_no: 'BOM-102', name: 'Ram Promaster - Plumbing Package', revision: 'R2', status: 'active', updated_at: '2023-10-23' },
  { id: 103, bom_no: 'BOM-103', name: 'Sprinter 144 - Delivery Config', revision: 'R1', status: 'archived', updated_at: '2023-10-22' },
];

const MOCK_JOBS: Job[] = [
  { id: 1, so_number: '#10245', customer_name: 'Amazon Logistics', fleet: 'Fleet Upfit', vehicle: 'Ford Transit - High Roof', vin: 'K1283944', status: 'ACTIVE', stage: 'INTERIOR UPFIT', created_at: '2023-10-12', dealer_id: 1, job_type: 'UPFIT', parts_status: 'READY', est_hours: 12 },
  { id: 2, so_number: '#10246', customer_name: 'L. Miller', fleet: 'Overland Build', vehicle: 'Mercedes Sprinter 4x4', vin: 'N5542012', status: 'ACTIVE', stage: 'SUSPENSION', created_at: '2023-10-15', dealer_id: 2, job_type: 'UPFIT', parts_status: 'PARTIAL', est_hours: 24 },
  { id: 3, so_number: '#10248', customer_name: 'City Power & Light', fleet: 'Service Body', vehicle: 'Ram 3500 Chassis Cab', vin: 'G9921045', status: 'ACTIVE', stage: 'ELECTRICAL', created_at: '2023-10-18', dealer_id: 1, job_type: 'UPFIT', parts_status: 'READY', est_hours: 8 },
  { id: 4, so_number: '#10250', customer_name: 'S. Williams', fleet: 'Custom Camper', vehicle: 'Ford E-350 Cutaway', vin: 'M0039211', status: 'ACTIVE', stage: 'FINAL QC', created_at: '2023-10-20', dealer_id: 2, job_type: 'UPFIT', parts_status: 'READY', est_hours: 40 },
  { id: 5, so_number: '#10252', customer_name: 'Metro Transit', fleet: 'Para-transit Mod', vehicle: 'Chevy Express 2500', vin: 'J7761022', status: 'ACTIVE', stage: 'PAINT & BODY', created_at: '2023-10-22', dealer_id: 1, job_type: 'REPAIR', parts_status: 'READY', est_hours: 6 },
  { id: 6, so_number: '#10255', customer_name: 'David G.', fleet: 'Personal RV', vehicle: 'Volkswagen Crafter', vin: 'W1120934', status: 'ACTIVE', stage: 'SCHEDULED', created_at: '2023-10-25', dealer_id: 2, job_type: 'UPFIT', parts_status: 'NOT_READY', est_hours: 16 },
  // Pending Intake Jobs (for CS Dashboard)
  { id: 7, customer_name: 'John Doe', dealer_id: 1, vin: '1G1...', job_type: 'UPFIT', parts_status: 'NOT_READY', est_hours: 4, status: 'PENDING_INTAKE', created_at: '2023-11-01' },
  { id: 8, customer_name: 'Jane Smith', dealer_id: 2, vin: '2T2...', job_type: 'REPAIR', parts_status: 'READY', est_hours: 2, status: 'NEEDS_SO', created_at: '2023-11-02' },
];

const MOCK_QC_INSPECTIONS: QcInspection[] = [
  { id: 1, job_id: 101, so_number: 'S-ORD101204', customer: 'City Power & Light', vin: 'PJ384102', time_in_queue: '2h 15m', technician: 'R. Miller', status: 'pending' },
  { id: 2, job_id: 102, so_number: 'S-ORD101205', customer: 'Verizon Fleet Svcs', vin: 'KK992384', time_in_queue: '45m', technician: 'M. Stevens', status: 'pending' },
  { id: 3, job_id: 103, so_number: 'S-ORD101206', customer: 'Global Logistics Corp', vin: 'LA441092', time_in_queue: '4h 02m', technician: 'J. Peterson', status: 'failed' },
  { id: 4, job_id: 104, so_number: 'S-ORD101207', customer: 'State Fire Dept.', vin: 'MD112933', time_in_queue: '12m', technician: 'R. Miller', status: 'pending' },
  { id: 5, job_id: 105, so_number: 'S-ORD101208', customer: 'Roadside Rescue', vin: 'RR556211', time_in_queue: '5m', technician: 'L. Thompson', status: 'pending' },
];

const MOCK_ITEMS: Item[] = [
  { id: 1, sku: 'EV-MOTOR-01', name: 'Dual Motor Assembly', category: 'Part', vendor: 'ElectroDrive', cost: 1800.00, retail: 2500.00, stock: 12 },
  { id: 2, sku: 'EV-CTRL-02', name: 'Performance Controller', category: 'Part', vendor: 'ElectroDrive', cost: 600.00, retail: 850.00, stock: 5 },
  { id: 3, sku: 'L-INSTALL-01', name: 'Standard Installation Labor', category: 'Labor', vendor: 'Internal', cost: 85.00, retail: 150.00, stock: null },
  { id: 4, sku: 'SUSP-LIFT-01', name: '2-inch Lift Kit', category: 'Part', vendor: 'OffRoad Pro', cost: 800.00, retail: 1200.00, stock: 3 },
  { id: 5, sku: 'WHL-OFF-17', name: '17-inch Off-Road Wheels', category: 'Part', vendor: 'WheelMaster', cost: 220.00, retail: 350.00, stock: 24 },
  { id: 6, sku: 'F-SHIP-01', name: 'Standard Shipping Fee', category: 'Fee', vendor: 'Internal', cost: 0.00, retail: 150.00, stock: null },
];

// --- API SERVICES ---

export const itemsService = {
  // GET /items
  getAll: async (): Promise<Item[]> => {
    if (!NONCE) return MOCK_ITEMS;
    const response = await fetch(`${API_BASE_URL}/items`, { headers: getHeaders() });
    return handleResponse<Item[]>(response);
  },
  // POST /items
  create: async (itemData: Partial<Item>): Promise<Item> => {
    if (!NONCE) {
       return { id: Math.floor(Math.random() * 10000), ...itemData } as Item;
    }
    const response = await fetch(`${API_BASE_URL}/items`, {
      method: 'POST',
      headers: getHeaders(),
      body: JSON.stringify(itemData),
    });
    return handleResponse<Item>(response);
  }
};

export const bomService = {
  // GET /boms
  getAll: async (): Promise<BomSummary[]> => {
    if (!NONCE) return MOCK_BOMS; // Return mocks if no nonce (dev mode)
    const response = await fetch(`${API_BASE_URL}/boms`, { headers: getHeaders() });
    return handleResponse<BomSummary[]>(response);
  },

  // GET /boms/:id
  getById: async (id: number): Promise<Bom> => {
    if (!NONCE) {
      // Mock detail response
      const summary = MOCK_BOMS.find(b => b.id === id);
      return {
        id,
        bom_no: summary?.bom_no || 'UNK',
        name: summary?.name || 'Unknown BOM',
        revision: summary?.revision || 'R1',
        status: summary?.status || 'draft',
        install_hours: 10,
        shop_supply_units: 5,
        created_at: '2023-01-01',
        updated_at: summary?.updated_at || '2023-01-01',
        lines: [
          { id: 1, bom_id: id, line_type: 'part', sku: 'PART-001', description: 'Mock Part 1', qty: 2, unit_retail: 50.00, sort_order: 1 },
          { id: 2, bom_id: id, line_type: 'labor', sku: 'LAB-001', description: 'Install Labor', qty: 1, unit_retail: 100.00, sort_order: 2 }
        ]
      };
    }
    const response = await fetch(`${API_BASE_URL}/boms/${id}`, { headers: getHeaders() });
    return handleResponse<Bom>(response);
  },

  // POST /boms
  create: async (data: Partial<Bom>): Promise<Bom> => {
    if (!NONCE) {
      console.log('Mock Create BOM:', data);
      return { 
        id: Math.floor(Math.random() * 1000), 
        bom_no: data.bom_no || 'NEW-BOM',
        name: data.name || 'New BOM', 
        revision: 'R1',
        status: 'draft',
        install_hours: 0,
        shop_supply_units: 0,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        lines: [] 
      };
    }
    const response = await fetch(`${API_BASE_URL}/boms`, {
      method: 'POST',
      headers: getHeaders(),
      body: JSON.stringify(data),
    });
    return handleResponse<Bom>(response);
  },

  // PUT /boms/:id
  update: async (id: number, data: Partial<Bom>): Promise<Bom> => {
    if (!NONCE) {
      console.log('Mock Update BOM:', id, data);
      return { 
        id, 
        bom_no: data.bom_no || 'UPDATED-BOM',
        name: data.name || 'Updated BOM', 
        revision: 'R1',
        status: 'draft',
        install_hours: 0,
        shop_supply_units: 0,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        lines: [] 
      };
    }
    const response = await fetch(`${API_BASE_URL}/boms/${id}`, {
      method: 'PUT',
      headers: getHeaders(),
      body: JSON.stringify(data),
    });
    return handleResponse<Bom>(response);
  },

  // DELETE /boms/:id
  delete: async (id: number): Promise<void> => {
    if (!NONCE) {
      console.log('Mock Delete BOM:', id);
      return;
    }
    const response = await fetch(`${API_BASE_URL}/boms/${id}`, {
      method: 'DELETE',
      headers: getHeaders(),
    });
    return handleResponse<void>(response);
  },

  // POST /boms/:id/revise
  revise: async (id: number): Promise<{ success: boolean; new_id: number }> => {
    if (!NONCE) {
       console.log('Mock Revise BOM:', id);
       return { success: true, new_id: id + 1000 }; // Mock ID
    }
    const response = await fetch(`${API_BASE_URL}/boms/${id}/revise`, {
      method: 'POST',
      headers: getHeaders(),
    });
    return handleResponse<{ success: boolean; new_id: number }>(response);
  },

  // POST /boms/:id/clone
  clone: async (id: number, newBomNo?: string): Promise<{ success: boolean; new_id: number }> => {
    if (!NONCE) {
       console.log('Mock Clone BOM:', id, newBomNo);
       return { success: true, new_id: id + 2000 }; // Mock ID
    }
    const response = await fetch(`${API_BASE_URL}/boms/${id}/clone`, {
      method: 'POST',
      headers: getHeaders(),
      body: JSON.stringify({ bom_no: newBomNo }),
    });
    return handleResponse<{ success: boolean; new_id: number }>(response);
  }
};

export const jobsService = {
  // GET /jobs
  getAll: async (): Promise<Job[]> => {
    if (!NONCE) return MOCK_JOBS; 
    const response = await fetch(`${API_BASE_URL}/jobs`, { headers: getHeaders() });
    return handleResponse<Job[]>(response);
  },

  // POST /jobs
  create: async (jobData: Partial<Job>): Promise<Job> => {
    if (!NONCE) {
      console.log('Mock Create Job:', jobData);
      return { id: Math.floor(Math.random() * 10000), ...jobData } as Job;
    }
    const response = await fetch(`${API_BASE_URL}/jobs`, {
      method: 'POST',
      headers: getHeaders(),
      body: JSON.stringify(jobData),
    });
    return handleResponse<Job>(response);
  }
};

export interface ActiveSegment {
  segment_id: number;
  job_id: number;
  user_id: number;
  start_ts: string;      // GMT datetime from DB
  so_number?: string;
  customer_name?: string;
  work_center?: string;
  reason?: string;
}

export const timeService = {
  /** GET /time/active — returns the current open timer segment, or null */
  getActive: async (): Promise<ActiveSegment | null> => {
    if (!NONCE) return null;
    const response = await fetch(`${API_BASE_URL}/time/active`, { headers: getHeaders() });
    if (response.status === 204 || response.status === 404) return null;
    const data = await handleResponse<{ active: ActiveSegment | null }>(response);
    return data?.active ?? null;
  },

  /** POST /time/start — start timer on a job (auto-stops any open timer) */
  start: async (jobId: number, reason = 'assigned', note = ''): Promise<{ segment_id: number; job_id: number; started_at: string }> => {
    const response = await fetch(`${API_BASE_URL}/time/start`, {
      method: 'POST',
      headers: getHeaders(),
      body: JSON.stringify({ job_id: jobId, reason, note }),
    });
    return handleResponse(response);
  },

  /** POST /time/stop — stop the current open timer */
  stop: async (): Promise<void> => {
    await fetch(`${API_BASE_URL}/time/stop`, { method: 'POST', headers: getHeaders() });
  },
};

export const workCenterService = {
  getAll: async (activeOnly = true): Promise<WorkCenter[]> => {
    if (!NONCE) return [];
    const url = `${API_BASE_URL}/work-centers${activeOnly ? '' : '?active=0'}`;
    const response = await fetch(url, { headers: getHeaders() });
    const data = await handleResponse<{ ok: boolean; work_centers: WorkCenter[] }>(response);
    return data.work_centers || [];
  },

  getById: async (id: number): Promise<WorkCenter> => {
    const response = await fetch(`${API_BASE_URL}/work-centers/${id}`, { headers: getHeaders() });
    const data = await handleResponse<{ ok: boolean; work_center: WorkCenter }>(response);
    return data.work_center;
  },

  create: async (payload: Partial<WorkCenter>): Promise<WorkCenter> => {
    const response = await fetch(`${API_BASE_URL}/work-centers`, {
      method: 'POST',
      headers: getHeaders(),
      body: JSON.stringify(payload),
    });
    const data = await handleResponse<{ ok: boolean; work_center: WorkCenter }>(response);
    return data.work_center;
  },

  update: async (id: number, payload: Partial<WorkCenter>): Promise<WorkCenter> => {
    const response = await fetch(`${API_BASE_URL}/work-centers/${id}`, {
      method: 'PATCH',
      headers: getHeaders(),
      body: JSON.stringify(payload),
    });
    const data = await handleResponse<{ ok: boolean; work_center: WorkCenter }>(response);
    return data.work_center;
  },
};

export const schedulerService = {
  getCapacity: async (from: string, to: string): Promise<{ from: string; to: string; summary: CapacitySummary[] }> => {
    if (!NONCE) return { from, to, summary: [] };
    const response = await fetch(`${API_BASE_URL}/scheduler/capacity?from=${from}&to=${to}`, { headers: getHeaders() });
    return handleResponse(response);
  },

  getOverloads: async (from: string, to: string): Promise<{ overloads: CapacitySummary[] }> => {
    if (!NONCE) return { overloads: [] };
    const response = await fetch(`${API_BASE_URL}/scheduler/overloads?from=${from}&to=${to}`, { headers: getHeaders() });
    return handleResponse(response);
  },

  recalculateFlags: async (from?: string, to?: string): Promise<{ flags_updated: number; scores_updated: number }> => {
    const response = await fetch(`${API_BASE_URL}/scheduler/recalculate-flags`, {
      method: 'POST',
      headers: getHeaders(),
      body: JSON.stringify({ from, to }),
    });
    return handleResponse(response);
  },

  getBufferSettings: async (): Promise<BufferSettings> => {
    if (!NONCE) return { shipping_buffer_days: 1, qc_buffer_days: 1, total_buffer_days: 2 };
    const response = await fetch(`${API_BASE_URL}/scheduler/buffer-settings`, { headers: getHeaders() });
    return handleResponse(response);
  },

  updateBufferSettings: async (shipping: number, qc: number): Promise<BufferSettings> => {
    const response = await fetch(`${API_BASE_URL}/scheduler/buffer-settings`, {
      method: 'POST',
      headers: getHeaders(),
      body: JSON.stringify({ shipping_buffer_days: shipping, qc_buffer_days: qc }),
    });
    return handleResponse(response);
  },

  lockJob: async (jobId: number): Promise<void> => {
    await fetch(`${API_BASE_URL}/jobs/${jobId}/lock`, { method: 'POST', headers: getHeaders() });
  },

  unlockJob: async (jobId: number): Promise<void> => {
    await fetch(`${API_BASE_URL}/jobs/${jobId}/unlock`, { method: 'POST', headers: getHeaders() });
  },

  holdJob: async (jobId: number, holdReason: string, note?: string): Promise<void> => {
    await fetch(`${API_BASE_URL}/jobs/${jobId}/hold`, {
      method: 'POST',
      headers: getHeaders(),
      body: JSON.stringify({ hold_reason: holdReason, note }),
    });
  },

  unholdJob: async (jobId: number, note?: string): Promise<void> => {
    await fetch(`${API_BASE_URL}/jobs/${jobId}/unhold`, {
      method: 'POST',
      headers: getHeaders(),
      body: JSON.stringify({ note }),
    });
  },

  getJobBuffer: async (jobId: number): Promise<any> => {
    const response = await fetch(`${API_BASE_URL}/jobs/${jobId}/buffer`, { headers: getHeaders() });
    return handleResponse(response);
  },

  bulkSchedule: async (updates: Array<Partial<Job> & { job_id: number }>): Promise<{ saved: number[]; errors: Array<{ job_id: number; message: string }> }> => {
    const response = await fetch(`${API_BASE_URL}/schedule/bulk`, {
      method: 'POST',
      headers: getHeaders(),
      body: JSON.stringify({ updates }),
    });
    return handleResponse(response);
  },

  getScheduledJobs: async (from: string, to: string): Promise<Job[]> => {
    if (!NONCE) return [];
    const response = await fetch(`${API_BASE_URL}/jobs?scheduled_from=${from}&scheduled_to=${to}&limit=500`, { headers: getHeaders() });
    const data = await handleResponse<{ jobs?: Job[] } | Job[]>(response);
    return Array.isArray(data) ? data : (data as any).jobs || [];
  },
};

export const dealerService = {
  // GET /dealers
  getAll: async (): Promise<Dealer[]> => {
    if (!NONCE) return [
      { id: 1, name: 'Metro Ford', market_weight: 'A', labor_rate_retail: 150, labor_rate_wholesale: 120, shop_supply_rate_retail: 15, shop_supply_rate_wholesale: 10 },
      { id: 2, name: 'City Dodge', market_weight: 'B', labor_rate_retail: 180, labor_rate_wholesale: 140, shop_supply_rate_retail: 20, shop_supply_rate_wholesale: 15 },
    ];
    const response = await fetch(`${API_BASE_URL}/dealers`, { headers: getHeaders() });
    return handleResponse<Dealer[]>(response);
  }
};

export const qcService = {
  // GET /qc/inspections
  getAll: async (): Promise<QcInspection[]> => {
    if (!NONCE) return MOCK_QC_INSPECTIONS;
    const response = await fetch(`${API_BASE_URL}/qc/inspections`, { headers: getHeaders() });
    return handleResponse<QcInspection[]>(response);
  },
  
  // POST /qc/inspections/:id/pass
  passInspection: async (id: number): Promise<void> => {
    if (!NONCE) {
      console.log('Mock Pass Inspection:', id);
      return;
    }
    await fetch(`${API_BASE_URL}/qc/inspections/${id}/pass`, { 
      method: 'POST',
      headers: getHeaders() 
    });
  },

  // POST /qc/inspections/:id/fail
  failInspection: async (id: number, reason: string): Promise<void> => {
    if (!NONCE) {
      console.log('Mock Fail Inspection:', id, reason);
      return;
    }
    await fetch(`${API_BASE_URL}/qc/inspections/${id}/fail`, { 
      method: 'POST',
      headers: getHeaders(),
      body: JSON.stringify({ reason })
    });
  }
};
