import React, { useMemo } from 'react';
import { Job, STATUS_MAPPING, ColumnType, normalizeStatus } from '../types';
import { Column } from './Column';

interface DashboardProps {
  jobs: Job[];
}

export const Dashboard: React.FC<DashboardProps> = ({ jobs }) => {

  const categorizedJobs = useMemo(() => {
    const map: Record<ColumnType, Job[]> = {
      [ColumnType.DELAYED]: [],
      [ColumnType.SCHEDULED]: [],
      [ColumnType.IN_PROGRESS]: [],
      [ColumnType.COMPLETED]: [],
    };

    jobs.forEach(job => {
      const status = normalizeStatus(job);
      
      let assigned = false;
      for (const [colKey, statuses] of Object.entries(STATUS_MAPPING)) {
        if (statuses.includes(status)) {
          map[colKey as ColumnType].push(job);
          assigned = true;
          break;
        }
      }
    });

    return map;
  }, [jobs]);

  return (
    <div className="flex h-full w-full bg-[#020617]">
      <Column 
        title="DELAYED" 
        jobs={categorizedJobs[ColumnType.DELAYED]} 
        colorClass="" // Handled by default column class in component
        headerColorClass="text-rose-400 border-rose-900/50"
        accentColor="rose"
      />
      <Column 
        title="SCHEDULED" 
        jobs={categorizedJobs[ColumnType.SCHEDULED]} 
        colorClass=""
        headerColorClass="text-blue-400 border-blue-900/50"
        accentColor="blue"
      />
      <Column 
        title="IN PROGRESS" 
        jobs={categorizedJobs[ColumnType.IN_PROGRESS]} 
        colorClass=""
        headerColorClass="text-amber-400 border-amber-900/50"
        accentColor="amber"
      />
      <Column 
        title="COMPLETED" 
        jobs={categorizedJobs[ColumnType.COMPLETED]} 
        colorClass=""
        headerColorClass="text-emerald-400 border-emerald-900/50"
        accentColor="emerald"
      />
    </div>
  );
};