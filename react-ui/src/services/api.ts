import { AppState, Bom, BomLine, BomSummary, Dealer, Job, QcInspection, ProductionBay, Item } from '../types';

// --- CONFIGURATION ---

const API_BASE_URL = typeof window !== 'undefined' && window.slateOpsSettings?.api?.root 
  ? window.slateOpsSettings.api.root.replace(/\/$/, '') 
  : '/wp-json/slate-ops/v1'; // Fallback for dev proxy or local

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
  { id: 5, sku: 'WHL-OFF-17', name: '17-inch
