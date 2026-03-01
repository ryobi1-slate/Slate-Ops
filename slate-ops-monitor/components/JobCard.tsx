import React from 'react';
import { Job, normalizeStatus } from '../types';

interface JobCardProps {
  job: Job;
  accentColor: string;
}

export const JobCard: React.FC<JobCardProps> = ({ job, accentColor }) => {
  // Use detailed status when available (status_detail), normalized to match column mapping.
  const displayStatus = normalizeStatus(job);
  // Logic to display Dealer | Customer
  const dealer = job.dealer ? job.dealer.trim() : '';
  const customer = job.customer ? job.customer.trim() : '';
  let entityDisplay = '';

  if (dealer && customer) {
    entityDisplay = `${dealer} | ${customer}`;
  } else if (dealer) {
    entityDisplay = dealer;
  } else {
    entityDisplay = customer;
  }

  // Dates logic
  const hasDates = job.start_date || job.due_date;
  const dateStr = hasDates ? `${job.start_date || '?'} → ${job.due_date || '?'}` : '';
  
  // Tech status check
  const isUnassigned = !job.assigned_tech || 
                       job.assigned_tech.toLowerCase().includes('unassigned') || 
                       job.assigned_tech === '-';

  // Industrial Badge Colors (High Contrast, Stamped)
  // Matching column accent, strong background, slightly bolder text
  const badgeStyle = {
    'rose': 'bg-rose-950/90 text-rose-100 border-rose-900',
    'blue': 'bg-blue-950/90 text-blue-100 border-blue-900',
    'amber': 'bg-amber-950/90 text-amber-100 border-amber-900',
    'emerald': 'bg-emerald-950/90 text-emerald-100 border-emerald-900',
  }[accentColor] || 'bg-slate-800 text-slate-300 border-slate-700';

  // Tech Name - Tier 1 visibility
  const techColorClass = isUnassigned 
    ? 'text-slate-600 font-medium' // Dimmer neutral
    : {
        'rose': 'text-rose-300',
        'blue': 'text-blue-300',
        'amber': 'text-amber-300',
        'emerald': 'text-emerald-300',
      }[accentColor] || 'text-slate-300';
  
  const techFontWeight = isUnassigned ? 'font-medium' : 'font-bold';

  return (
    <div className="relative bg-slate-900 rounded-sm shadow-none overflow-hidden flex flex-col group border-l-[3px] border-slate-800 transition-all duration-300">
      
      {/* Structural Accent Strip - Left Side Indicator */}
      <div className={`absolute left-0 top-0 bottom-0 w-[3px] bg-${accentColor}-500`}></div>

      {/* Main Content Container - Compressed Spacing */}
      <div className="pl-3.5 pr-2 py-1.5 flex flex-col gap-[2px]">
          
          {/* Main Grid Layout */}
          <div className="flex justify-between items-start">
            
            {/* Left Group: SO#, Entity, Status */}
            <div className="flex flex-col gap-[1px] max-w-[65%]">
                {/* SO# - Anchor: Heaviest (800), Brightest (Slate-50) */}
                <div className="font-extrabold text-2xl text-slate-50 tracking-tighter leading-none">
                  {job.so_number || <span className="opacity-20 text-xl">NO SO#</span>}
                </div>
                
                {/* Dealer | Customer - Tier 2: Semibold, Slate-200 */}
                <div className="text-[14px] font-semibold text-slate-200 truncate leading-tight">
                   {entityDisplay || <span className="text-slate-600 font-normal text-sm">No Client Info</span>}
                </div>

                {/* Status Badge - Tier 2: Bold, contained */}
                <div className="mt-1 flex">
                    <span className={`inline-block px-1.5 py-[1px] rounded-[2px] text-[9px] font-bold uppercase tracking-wider border ${badgeStyle} leading-none shadow-sm`}>
                        {displayStatus || job.status}
                    </span>
                </div>
            </div>

            {/* Right Group: VIN, Tech, Hours, Dates - Vertical Stack */}
            <div className="flex flex-col items-end text-right flex-1 min-w-0 pl-1">
                
                {/* VIN - Tier 3: Dimmed (Slate-600), Monospace */}
                <div className="font-mono text-[9px] font-medium tracking-tight mb-1" style={{color:'#fff'}}>
                    {job.vin}
                </div>

                {/* Execution Stack - Tightly packed */}
                <div className="flex flex-col items-end gap-[2px]">
                    {/* Tech Name - Tier 1: Bold, Tinted */}
                    <div className={`text-[13px] ${techFontWeight} ${techColorClass} truncate leading-none tracking-tight`}>
                        {job.assigned_tech || <span className="text-slate-700 opacity-50">-</span>}
                    </div>

                    {/* Hours - Tier 1: Structural Pill, High Contrast */}
                    <div className="bg-slate-800 border border-slate-700/60 px-1.5 py-[1px] rounded-[2px] mt-[1px]">
                        <div className="text-[11px] font-bold font-mono text-slate-100 leading-none">
                             {job.time_estimate || '--'}
                        </div>
                    </div>

                    {/* Dates - Tier 3: Slate-400 */}
                    {hasDates && (
                       <div className="mt-[2px] text-[9px] font-mono text-slate-500 font-medium leading-none">
                          {dateStr}
                       </div>
                    )}
                </div>
            </div>

          </div>

          {/* Notes - Tier 3: Slate-500, small, italic */}
          {job.notes && (
            <div className="mt-0.5">
                <p className="text-[9px] text-slate-500 font-normal italic leading-tight line-clamp-1 opacity-80">
                  {job.notes}
                </p>
            </div>
          )}

      </div>
    </div>
  );
};