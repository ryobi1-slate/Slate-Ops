import React from 'react';
import { QcInspection } from '../types';

interface QcDashboardProps {
  inspections: QcInspection[];
}

export function QcDashboard({ inspections }: QcDashboardProps) {
  return (
    <div className="flex-1 overflow-y-auto bg-[#EAE8DC] p-8 font-sans">
      <div className="flex justify-between items-center mb-6">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">QC Queue</h1>
          <p className="text-slate-500 text-sm mt-0.5">Automotive UpFit Quality Control Station</p>
        </div>
        <button className="border border-[#d86b19] text-[#d86b19] hover:bg-orange-50 font-bold py-2 px-4 rounded text-sm transition-colors">
          New Inspection
        </button>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-3 gap-4 mb-6">
        <div className="bg-white p-6 rounded-xl shadow-sm">
          <div className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Pending Inspection</div>
          <div className="flex items-baseline gap-2">
            <span className="text-4xl font-bold text-slate-900">12</span>
            <span className="text-sm font-medium text-[#d86b19]">+3 since 8AM</span>
          </div>
        </div>
        <div className="bg-white p-6 rounded-xl shadow-sm">
          <div className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Failed QC</div>
          <div className="flex items-baseline gap-2">
            <span className="text-4xl font-bold text-slate-900">4</span>
            <span className="text-sm font-medium text-red-600">Requiring Rework</span>
          </div>
        </div>
        <div className="bg-white p-6 rounded-xl shadow-sm">
          <div className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Passed Today</div>
          <div className="flex items-baseline gap-2">
            <span className="text-4xl font-bold text-slate-900">28</span>
            <span className="text-sm font-medium text-green-600">52% Daily Target</span>
          </div>
        </div>
      </div>

      {/* Jobs Pending QC List */}
      <div className="bg-white rounded-xl shadow-sm overflow-hidden">
        <div className="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
          <h2 className="font-bold text-sm text-slate-900 uppercase tracking-wide">Jobs Pending QC</h2>
          <input
            type="text"
            placeholder="Search SO# or VIN..."
            className="border border-slate-200 rounded-md px-3 py-1.5 text-sm w-56 focus:outline-none focus:ring-1 focus:ring-[#d86b19] focus:border-[#d86b19]"
          />
        </div>

        <table className="w-full text-sm text-left">
          <thead className="text-slate-400 font-bold text-[10px] uppercase tracking-wider border-b border-slate-100">
            <tr>
              <th className="px-6 py-3">SO Number</th>
              <th className="px-6 py-3">Customer</th>
              <th className="px-6 py-3">VIN (Last 6)</th>
              <th className="px-6 py-3">Time in Queue</th>
              <th className="px-6 py-3">Technician</th>
              <th className="px-6 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {inspections.map(job => (
              <tr key={job.id} className="hover:bg-slate-50 transition-colors">
                <td className="px-6 py-3 font-bold text-[#d86b19] align-middle">{job.so_number}</td>
                <td className="px-6 py-3 font-medium text-slate-900 align-middle">{job.customer}</td>
                <td className="px-6 py-3 font-mono text-slate-600 align-middle text-xs">{job.vin ? job.vin.slice(-6) : '—'}</td>
                <td className="px-6 py-3 align-middle">
                  <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                    ${job.time_in_queue && job.time_in_queue.includes('h') ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-600'}`}>
                    {job.time_in_queue}
                  </span>
                </td>
                <td className="px-6 py-3 text-slate-600 align-middle">{job.technician}</td>
                <td className="px-6 py-3 text-right align-middle">
                  <button className="text-slate-600 hover:text-slate-900 font-bold text-xs uppercase tracking-wide">
                    Inspect
                  </button>
                </td>
              </tr>
            ))}
            {inspections.length === 0 && (
              <tr>
                <td colSpan={6} className="px-6 py-12 text-center">
                  <div className="flex flex-col items-center gap-2 text-slate-400">
                    <span className="material-symbols-outlined text-2xl">check</span>
                    <span className="text-sm">Showing 0 of 12 pending jobs</span>
                  </div>
                </td>
              </tr>
            )}
          </tbody>
        </table>

        <div className="px-6 py-4 border-t border-slate-100 flex justify-between items-center">
          <span className="text-xs text-slate-500">Showing {inspections.length} of 12 pending jobs</span>
          <div className="flex gap-2">
            <button className="border border-[#d86b19] text-[#d86b19] hover:bg-orange-50 text-xs font-bold px-4 py-1.5 rounded transition-colors">Prev</button>
            <button className="border border-slate-800 text-slate-800 hover:bg-slate-50 text-xs font-bold px-4 py-1.5 rounded transition-colors">Next</button>
          </div>
        </div>
      </div>
    </div>
  );
}
