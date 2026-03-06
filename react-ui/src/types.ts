export type LineType = 'part' | 'labor' | 'shop';
export type BomStatus = 'draft' | 'active' | 'archived';
export type MarketWeight = 'A' | 'B' | 'C';

// Matches wp_slate_quotes
export interface Quote {
  id: number;
  quote_no: string;
  dealer_id: number;
  status: 'draft' | 'sent' | 'approved' | 'rejected';
  total: number;
  created_at: string;
}

// Matches wp_slate_boms
export interface Bom {
  id: number;
  bom_no: string;
  name: string;
  revision: string;
  install_hours: number;
  shop_supply_units: number;
  status: BomStatus;
  created_at: string;
  updated_at: string;
  lines?: BomLine[]; // Optional for list views, present in detail
}

// Matches wp_slate_bom_lines
export interface BomLine {
  id: number;
  bom_id: number;
  line_type: LineType;
  sku: string;
  description: string;
  qty: number;
  
  // Pricing columns
  unit_cost?: number;        // Internal cost
  unit_retail: number;       // Base Retail (MSRP/MAP)
  
  // Overrides (if supported by schema, otherwise handled via logic)
  override_wholesale?: number;
  override_retail?: number;
  
  sort_order: number;
}

// Matches wp_slate_dealers
export interface Dealer {
  id: number;
  name: string;
  market_weight: MarketWeight; // Likely stored as string/enum in DB
  labor_rate_retail: number;
  labor_rate_wholesale: number;
  shop_supply_rate_retail: number;
  shop_supply_rate_wholesale: number;
}

export interface BomSummary {
  id: number;
  bom_no: string;
  name: string;
  revision: string;
  status: BomStatus;
  updated_at: string;
}

// Matches wp_slate_jobs (implied)
export interface Job {
  id: number;
  customer_name: string;
  dealer_id: number;
  vin: string;
  vehicle?: string; // e.g. "Ford Transit"
  fleet?: string;   // e.g. "Amazon Logistics"
  job_type: 'UPFIT' | 'REPAIR' | 'WARRANTY';
  parts_status: 'NOT_READY' | 'PARTIAL' | 'READY';
  est_hours: number;
  so_number?: string;
  due_date?: string;
  // High level status
  status: 'PENDING_INTAKE' | 'NEEDS_SO' | 'ACTIVE' | 'COMPLETED';
  // Granular production stage
  stage?: 'SCHEDULED' | 'INTERIOR UPFIT' | 'ELECTRICAL' | 'SUSPENSION' | 'PAINT & BODY' | 'FINAL QC';
  created_at: string;
}

export interface QcInspection {
  id: number;
  job_id: number;
  so_number: string;
  customer: string;
  vin: string;
  technician: string;
  time_in_queue: string;
  status: 'pending' | 'failed' | 'passed';
}

export interface ProductionBay {
  id: string;
  name: string;
  equipment_level: string;
  status: 'Active' | 'Maintenance';
}

export interface Item {
  id: number;
  sku: string;
  name: string;
  category: 'Part' | 'Labor' | 'Fee';
  vendor: string;
  cost: number;
  retail: number;
  stock: number | null;
}

export interface AppState {
  boms: BomSummary[];
  dealers: Dealer[];
  jobs: Job[];
  qcInspections: QcInspection[];
  items: Item[];
  active: {
    bom: Bom | null;
    lines: BomLine[];
  };
  ui: {
    selectedDealerId: number | null;
    isLoading: boolean;
    error: string | null;
    dirty: boolean;
    view: 'list' | 'detail';
  };
}

// Mock Global Object for WP Environment
declare global {
  interface Window {
    slateOpsSettings: {
      api: {
        root: string;
        nonce: string;
      };
      user: {
        id: number;
        name: string;
        caps: string[];
        roles: string[];
      };
      colors: any;
      dealers: Dealer[];
    };
  }
}
