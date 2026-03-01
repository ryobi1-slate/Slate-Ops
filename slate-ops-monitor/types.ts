export enum ColumnType {
  DELAYED = 'DELAYED',
  SCHEDULED = 'SCHEDULED',
  IN_PROGRESS = 'IN_PROGRESS',
  COMPLETED = 'COMPLETED',
}

export interface Job {
  id: string
  so_number: string
  vin: string
  dealer: string
  customer: string
  sales_person: string
  assigned_tech: string
  start_date: string
  due_date: string
  time_estimate: string
  notes: string

  // Primary ClickUp status (ex: DELAYED, SCHEDULED, COMPLETED)
  status: string

  // More detailed status from backend (ex: delay - parts)
  status_detail?: string
}

export const STATUS_MAPPING: Record<ColumnType, string[]> = {
  [ColumnType.DELAYED]: [
    'DELAYED',
    'DELAY – PARTS',
    'DELAY – UPDATE REQUIRED',
    'DELAY – ON HOLD',
  ],

  [ColumnType.SCHEDULED]: [
    'SCHEDULED',
    'SCHEDULED – NOT ARRIVED',
    'SCHEDULED – READY TO START',
  ],

  [ColumnType.IN_PROGRESS]: [
    'IN PROGRESS',
    'IN_PROGRESS',
    'QUALITY CONTROL',
  ],

  [ColumnType.COMPLETED]: [
    'COMPLETED',
    'COMPLETE – AWAITING PICKUP',
  ],
}

/**
 * Normalizes backend status strings.
 * - Uses status_detail when available
 * - Uppercases everything
 * - Converts " - " to " – "
 * - Trims whitespace
 */
export function normalizeStatus(job: Job): string {
  const raw = (job.status_detail || job.status || '').toString()

  return raw
    .toUpperCase()
    .replace(/\s*-\s*/g, ' – ')
    .trim()
}
