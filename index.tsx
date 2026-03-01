import React from 'react';
import { Job } from '../types';
import { JobCard } from './JobCard';

interface ColumnProps {
  title: string;
  jobs: Job[];
  colorClass: string;
  headerColorClass: string;
  accentColor: string;
}

export const Column: React.FC<ColumnProps> = ({ title, jobs, colorClass, headerColorClass, accentColor }) => {
  return (
    <div className={`w-1/4 flex flex-col h-full border-r border-slate-800/50 last:border-r-0 relative bg-black/20`}>
      {/* Structural Header - 1px top accent via border-t if needed, but styling handled by classes */}
      <div className={`flex items-center justify-between px-4 py-3 z-10 bg-slate-900/90 border-b border-slate-800 ${headerColorClass.split(' ').filter(c => c.startsWith('border-')).join(' ')}`}>
          {/* Title: Bold (was semibold), Tightened tracking (0.2 -> 0.1em) */}
          <h2 className={`text-xs font-bold tracking-[0.1em] uppercase ${headerColorClass.replace(/border-\S+/g, '')}`}>{title}</h2>
          {/* Job Count: Opacity reduced to 70% to support header without competing */}
          <span className="text-lg font-bold text-slate-400 opacity-70 font-mono leading-none">{jobs.length}</span>
      </div>

      {/* Scrollable Area - Dense */}
      <div className="flex-1 overflow-y-auto px-2 py-2 space-y-2 no-scrollbar scroll-smooth">
        {jobs.length === 0 ? (
          <div className="h-32 flex items-center justify-center opacity-40">
            <span className="text-[10px] font-bold uppercase tracking-widest text-slate-600">No Active Jobs</span>
          </div>
        ) : (
          jobs.map((job) => (
            <JobCard key={job.id} job={job} accentColor={accentColor} />
          ))
        )}
      </div>
    </div>
  );
};