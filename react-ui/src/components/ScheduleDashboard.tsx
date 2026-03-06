import React from 'react';
import { Job } from '../types';

interface ScheduleDashboardProps {
  jobs: Job[];
}

const MOCK_SCHEDULE_ITEMS = [
  { 
    id: 1, 
    bay: 'Bay 1', 
    startDay: 0, // Monday relative index
    span: 1, 
    vehicle: 'Ford Transit', 
    jobId: '#1024', 
    type: 'Electrical Upfit', 
    lead: 'M. Davis',
    status: 'standard'
  },
  { 
    id: 2, 
    bay: 'Bay 1', 
    startDay: 2, // Wednesday
    span: 1, 
    vehicle: 'Chevrolet Express', 
    jobId: '#1027', 
    type: 'Cargo Management', 
    lead: 'T. Nguyen',
    status: 'standard'
  },
  { 
    id: 3, 
    bay: 'Bay 2', 
    startDay: 0, 
    span: 2, 
    vehicle: 'Mercedes Sprinter', 
    jobId: '#1025', 
    type: 'Custom Shelving & Ladder Rack', 
    lead: 'S. Lee',
    status: 'priority'
  },
  { 
    id: 4, 
    bay: 'Bay 2', 
    startDay: 3, 
    span: 1, 
    vehicle: 'Nissan NV', 
    jobId: '#1029', 
    type: 'Interior Liner', 
    lead: 'J. Smith',
    status: 'standard'
  },
  { 
    id: 5, 
    bay: 'Bay 3', 
    startDay: 1, 
    span: 2, 
    vehicle: 'Ram Promaster', 
    jobId: '#1026', 
    type: 'Partition & Floor Install', 
    lead: 'K. Patel',
    status: 'standard'
  },
  { 
    id: 6, 
    bay: 'Bay 4', 
    startDay: 3, 
    span: 2, 
    vehicle: 'Ford F-150', 
    jobId: '#1028', 
    type: 'Utility Body Upfit', 
    lead: 'A. Kim',
    status: 'priority'
  },
  { 
    id: 7, 
    bay: 'Bay 5', 
    startDay: 3, 
    span: 1, 
    vehicle: 'Nissan NV', 
    jobId: '#1029', 
    type: 'Interior Liner', 
    lead: 'J. Smith',
    status: 'standard'
  },
];

const BAYS = ['Bay 1', 'Bay 2', 'Bay 3', 'Bay 4', 'Bay 5', 'Bay 6'];
const DAYS = [
  { name: 'Mon', date: '23' },
  { name: 'Tue', date: '24' },
  { name: 'Wed', date: '25' },
  { name: 'Thu', date: '26' },
  { name: 'Fri', date: '27' },
  { name: 'Sat', date: '28' },
  { name: 'Sun', date: '29' },
];

export function ScheduleDashboard({ jobs }: ScheduleDashboardProps) {
  // Calculate stats from jobs
  const upcomingDeliveries = jobs.filter(j => {
    if (!j.due_date) return false;
    const due = new Date(j.due_date);
    const now = new Date();
    // Check if due in next 7 days
    const diffTime = due.getTime() - now.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)); 
    return diffDays >= 0 && diffDays <= 7;
  }).length;

  const delayedJobs = jobs.filter(j => {
    if (!j.due_date) return false;
    return new Date(j.due_date) < new Date() && j.status !== 'COMPLETED';
  }).length;

  return (
    <div className="flex-1 overflow-y-auto bg-[#EAE8DC] p-8 font-sans">
      {/* Header */}
      <div className="flex justify-between items-center mb-8">
        <h1 className="text-3xl font-bold text-slate-900">Production Schedule</h1>
        <div className="flex items-center gap-4">
          <button className="flex items-center gap-2 bg-white border border-slate-200 rounded-md px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
            <span className="material-symbols-outlined text-sm">calendar_today</span>
            Oct 23 - Oct 29, 2023
            <span className="material-symbols-outlined text-sm">expand_more</span>
          </button>
          <button className="text-slate-500 hover:text-slate-700 relative">
            <span className="material-symbols-outlined">notifications</span>
            <span className="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full border-2 border-[#EAE8DC]"></span>
          </button>
          <div className="w-8 h-8 rounded-full bg-slate-300 overflow-hidden">
             <img src="https://picsum.photos/seed/user/100/100" alt="User" />
          </div>
        </div>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div className="bg-white p-6 rounded-xl shadow-sm relative overflow-hidden">
          <div className="flex justify-between items-start mb-2">
            <div className="text-sm font-bold text-slate-700">Current Capacity</div>
            <div className="text-xs font-bold text-slate-400 uppercase">Sage</div>
          </div>
          <div className="text-4xl font-bold text-slate-900 mb-4">85%</div>
          <div className="w-full bg-slate-100 rounded-full h-2">
            <div className="bg-slate-600 h-2 rounded-full" style={{ width: '85%' }}></div>
          </div>
        </div>

        <div className="bg-white p-6 rounded-xl shadow-sm">
          <div className="flex justify-between items-start mb-2">
            <div className="text-sm font-bold text-slate-700">Upcoming Deliveries</div>
            <div className="text-xs font-bold text-slate-400 uppercase">Sage</div>
          </div>
          <div className="text-4xl font-bold text-slate-900">{upcomingDeliveries} Vehicles</div>
        </div>

        <div className="bg-white p-6 rounded-xl shadow-sm">
          <div className="flex justify-between items-start mb-2">
            <div className="text-sm font-bold text-slate-700">Delayed Jobs</div>
            <div className="text-xs font-bold text-orange-500 uppercase flex items-center gap-1">
              Archess <span className="material-symbols-outlined text-sm">warning</span>
            </div>
          </div>
          <div className="text-4xl font-bold text-orange-600">{delayedJobs} Jobs</div>
        </div>
      </div>

      {/* Main Schedule Area */}
      <div className="bg-white rounded-xl shadow-sm p-6">
        {/* Controls */}
        <div className="flex flex-wrap gap-4 mb-6">
          <div className="w-64">
            <label className="block text-xs font-bold text-slate-500 mb-1">Date range</label>
            <button className="w-full flex justify-between items-center border border-slate-200 rounded-md px-3 py-2 text-sm text-slate-700 hover:border-slate-300">
              <div className="flex items-center gap-2">
                <span className="material-symbols-outlined text-slate-400 text-lg">calendar_month</span>
                Week of Oct 23, 2023
              </div>
              <span className="material-symbols-outlined text-slate-400">expand_more</span>
            </button>
          </div>
          <div className="w-48">
            <label className="block text-xs font-bold text-slate-500 mb-1">Filter by Production Bay</label>
            <select className="w-full border border-slate-200 rounded-md px-3 py-2 text-sm text-slate-700 focus:ring-primary focus:border-primary">
              <option>All Bays</option>
              <option>Bay 1</option>
              <option>Bay 2</option>
            </select>
          </div>
          <div className="w-48">
            <label className="block text-xs font-bold text-slate-500 mb-1">Filter by Lead Technician</label>
            <select className="w-full border border-slate-200 rounded-md px-3 py-2 text-sm text-slate-700 focus:ring-primary focus:border-primary">
              <option>All Technicians</option>
            </select>
          </div>
        </div>

        {/* Calendar Grid */}
        <div className="border border-slate-200 rounded-lg overflow-hidden">
          {/* Header Row */}
          <div className="grid grid-cols-8 bg-slate-50 border-b border-slate-200">
            <div className="p-3 font-bold text-sm text-slate-700 border-r border-slate-200">Production Bays</div>
            {DAYS.map((day, index) => (
              <div key={index} className={`p-3 text-center border-r border-slate-200 last:border-r-0 ${index >= 5 ? 'bg-slate-50/50' : ''}`}>
                <div className="text-xs font-bold text-slate-500 uppercase">{day.name}</div>
                <div className="font-bold text-slate-900">{day.date}</div>
              </div>
            ))}
          </div>

          {/* Bay Rows */}
          {BAYS.map((bay, bayIndex) => (
            <div key={bay} className="grid grid-cols-8 border-b border-slate-100 last:border-b-0 min-h-[100px]">
              {/* Bay Label */}
              <div className="p-4 font-bold text-sm text-slate-900 border-r border-slate-200 flex items-center bg-white">
                {bay}
              </div>

              {/* Day Cells */}
              {DAYS.map((day, dayIndex) => {
                // Find job starting in this bay on this day
                const job = MOCK_SCHEDULE_ITEMS.find(item => item.bay === bay && item.startDay === dayIndex);
                
                // Check if this cell is covered by a spanning job from a previous day
                const isCovered = MOCK_SCHEDULE_ITEMS.some(item => 
                  item.bay === bay && 
                  item.startDay < dayIndex && 
                  (item.startDay + item.span) > dayIndex
                );

                if (isCovered) return null; // Don't render cell if covered

                return (
                  <div 
                    key={dayIndex} 
                    className={`relative border-r border-slate-100 last:border-r-0 p-1 ${dayIndex >= 5 ? 'bg-slate-50/30' : ''}`}
                    style={job ? { gridColumn: `span ${job.span}` } : {}}
                  >
                    {job && (
                      <div className={`h-full rounded p-2 text-xs flex flex-col justify-between shadow-sm cursor-pointer hover:opacity-90 transition-opacity
                        ${job.status === 'priority' ? 'bg-orange-600 text-white' : 'bg-[#5A6B65] text-white'}`}>
                        <div>
                          <div className="font-bold mb-0.5">{job.vehicle}</div>
                          <div className="opacity-90 text-[10px] leading-tight mb-1">{job.jobId} - {job.type}</div>
                        </div>
                        <div className="flex justify-between items-end">
                          <div className="opacity-80 font-medium">Lead: {job.lead}</div>
                          {job.status === 'priority' && (
                            <span className="text-[9px] font-bold bg-white/20 px-1 rounded uppercase">PRIORITY</span>
                          )}
                        </div>
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
