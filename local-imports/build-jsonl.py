#!/usr/bin/env python3
"""
Build clickup-active-cleaned.jsonl from clickup-export.csv.

Converts the raw ClickUp Schedule export into the canonical JSONL format
defined in GitHub issue #127.  Writes two output files:

  local-imports/clickup-active-cleaned.jsonl   — one job object per line
  local-imports/import-report.txt              — human-readable summary

Run from the repo root:
  python3 local-imports/build-jsonl.py

Both output files are gitignored.
"""

import csv
import json
import re
import sys
from datetime import datetime
from pathlib import Path

REPO_ROOT   = Path(__file__).parent.parent
CSV_IN      = REPO_ROOT / 'local-imports' / 'clickup-export.csv'
JSONL_OUT   = REPO_ROOT / 'local-imports' / 'clickup-active-cleaned.jsonl'
REPORT_OUT  = REPO_ROOT / 'local-imports' / 'import-report.txt'

# ── Status / parts mappings ──────────────────────────────────────────────────

STATUS_MAP = {
    'in progress':               'In Progress',
    'scheduled - ready to start': 'Scheduled',
    'scheduled - not arrived':    'Scheduled',
    'delay - parts':              'Delayed',
    'complete - awaiting pickup': 'Complete - Awaiting Pickup',
}

PARTS_MAP = {
    'in progress':               'Ready',
    'scheduled - ready to start': 'Ready',
    'scheduled - not arrived':    'Not Ready',
    'delay - parts':              'Not Ready',
    'complete - awaiting pickup': 'Ready',
}

# ── Helpers ──────────────────────────────────────────────────────────────────

def parse_date(raw: str) -> str:
    """'Wednesday, April 29th 2026' → '2026-04-29'.  Returns '' on failure."""
    s = raw.strip()
    if not s:
        return ''
    s = re.sub(r'(\d+)(st|nd|rd|th)', r'\1', s)
    for fmt in ('%A, %B %d %Y', '%B %d %Y', '%Y-%m-%d'):
        try:
            return datetime.strptime(s, fmt).strftime('%Y-%m-%d')
        except ValueError:
            continue
    return ''


def parse_est_hours(raw: str) -> float:
    """'5h' → 5.0  |  '1w  6h' → 46.0  |  '3d 3h' → 27.0.
    ClickUp convention: 1w = 40 h, 1d = 8 h."""
    s = raw.strip()
    if not s:
        return 0.0
    weeks  = re.search(r'(\d+)\s*w', s)
    days   = re.search(r'(\d+)\s*d', s)
    hours  = re.search(r'(\d+)\s*h', s)
    total  = 0.0
    if weeks:  total += int(weeks.group(1)) * 40
    if days:   total += int(days.group(1)) * 8
    if hours:  total += int(hours.group(1))
    return total


def clean_tech(raw: str) -> str:
    """'[Jake , Seth]' → 'Jake, Seth'.  '[First Available]' → ''."""
    s = raw.strip().strip('[]')
    parts = [
        p.strip() for p in s.split(',')
        if p.strip() and p.strip().lower() not in ('first available', '')
    ]
    return ', '.join(parts)

# ── Core ─────────────────────────────────────────────────────────────────────

def build():
    if not CSV_IN.exists():
        sys.exit(f'ERROR: CSV not found at {CSV_IN}')

    with CSV_IN.open(newline='', encoding='utf-8') as f:
        rows = list(csv.DictReader(f))

    total_rows   = len(rows)
    skipped      = []
    active_out   = []
    warnings     = []

    for i, r in enumerate(rows, start=2):   # row 1 = header
        task_type  = r.get('Task Type', '').strip()
        status_raw = r.get('Status', '').strip().lower()
        task_name  = r.get('Task Name', '').strip()
        task_id    = r.get('Task ID', '').strip()

        # Skip non-Task rows (Account, Contact, etc.)
        if task_type != 'Task':
            skipped.append({'row': i, 'id': task_id, 'name': task_name,
                            'reason': f'Task Type = {task_type!r} (not a Task)'})
            continue

        # Skip Closed → these are history, not active jobs
        if status_raw == 'closed':
            skipped.append({'row': i, 'id': task_id, 'name': task_name,
                            'reason': 'Status = Closed (closed history — use separate import)'})
            continue

        # Skip unknown statuses
        if status_raw not in STATUS_MAP:
            skipped.append({'row': i, 'id': task_id, 'name': task_name,
                            'reason': f'Unknown status {status_raw!r}'})
            continue

        customer = task_name
        so       = r.get('SO# (short text)', '').strip() or r.get('STOCK # (short text)', '').strip()

        # Skip if no identifier at all
        if not customer and not so:
            skipped.append({'row': i, 'id': task_id, 'name': '(blank)',
                            'reason': 'Missing both customer_name and so_number'})
            continue

        # Warn (but do NOT skip) if SO# absent — common for ClickUp-originated jobs
        if not so:
            warnings.append({'row': i, 'id': task_id, 'name': customer,
                              'warning': 'SO# absent — will need to be set manually after import'})

        est_hours  = parse_est_hours(r.get('Time Estimate', ''))
        start_date = parse_date(r.get('Start Date', ''))
        due_date   = parse_date(r.get('Due Date', ''))
        lead_tech  = clean_tech(r.get('Assigned Tech (labels)', ''))
        notes      = r.get('Latest Comment', '').strip()

        obj = {
            'source_task_id': task_id,
            'customer_name':  customer,
            'so_number':      so,
            'vin':            '',
            'dealer':         '',
            'sales_person':   '',
            'lead_tech':      lead_tech,
            'est_hours':      est_hours,
            'start_date':     start_date,
            'due_date':       due_date,
            'notes':          notes,
            'parts_status':   PARTS_MAP[status_raw],
            'job_status':     STATUS_MAP[status_raw],
            'import_lane':    'active',
        }
        active_out.append(obj)

    # Write JSONL
    with JSONL_OUT.open('w', encoding='utf-8') as f:
        for obj in active_out:
            f.write(json.dumps(obj) + '\n')

    # Write report
    lines = []
    lines.append('ClickUp Active Import — Pre-Import Report')
    lines.append('=' * 60)
    lines.append(f'Generated:       {datetime.now().strftime("%Y-%m-%d %H:%M:%S")}')
    lines.append(f'Source CSV:      {CSV_IN.name}')
    lines.append(f'Total CSV rows:  {total_rows}')
    lines.append(f'Active (queued): {len(active_out)}')
    lines.append(f'Skipped:         {len(skipped)}')
    lines.append(f'Warnings:        {len(warnings)}')
    lines.append('')

    # Status breakdown
    from collections import Counter
    status_counts = Counter(o['job_status'] for o in active_out)
    lines.append('Active rows by job_status:')
    for s, n in sorted(status_counts.items()):
        lines.append(f'  {n:3d}  {s}')
    lines.append('')

    parts_counts = Counter(o['parts_status'] for o in active_out)
    lines.append('Active rows by parts_status:')
    for s, n in sorted(parts_counts.items()):
        lines.append(f'  {n:3d}  {s}')
    lines.append('')

    # Active rows detail
    lines.append('Active rows queued for import:')
    lines.append('-' * 60)
    for j, o in enumerate(active_out, start=1):
        lines.append(f'  {j:2d}. [{o["job_status"]} / {o["parts_status"]}] {o["customer_name"]}')
        if o['lead_tech']:
            lines.append(f'      Tech: {o["lead_tech"]}')
        if o['est_hours']:
            lines.append(f'      Est:  {o["est_hours"]}h')
        if o['start_date'] or o['due_date']:
            lines.append(f'      Dates: start={o["start_date"] or "-"}  due={o["due_date"] or "-"}')
        if o['notes']:
            lines.append(f'      Notes: {o["notes"]}')
    lines.append('')

    # Warnings
    if warnings:
        lines.append(f'Warnings ({len(warnings)} rows — will import, action needed after):')
        lines.append('-' * 60)
        for w in warnings:
            lines.append(f'  row {w["row"]:3d}  {w["name"]!r}')
            lines.append(f'           {w["warning"]}')
        lines.append('')

    # Skipped
    if skipped:
        lines.append(f'Skipped rows ({len(skipped)} total):')
        lines.append('-' * 60)
        for s in skipped[:20]:   # cap at 20 for readability
            lines.append(f'  row {s["row"]:3d}  {s["name"]!r}  — {s["reason"]}')
        if len(skipped) > 20:
            lines.append(f'  ... and {len(skipped) - 20} more (all Closed history)')

    with REPORT_OUT.open('w', encoding='utf-8') as f:
        f.write('\n'.join(lines) + '\n')

    # Print summary
    print(f'Source rows:   {total_rows}')
    print(f'Active queued: {len(active_out)}')
    print(f'Skipped:       {len(skipped)}')
    print(f'Warnings:      {len(warnings)}')
    print(f'JSONL:  {JSONL_OUT}')
    print(f'Report: {REPORT_OUT}')


if __name__ == '__main__':
    build()
