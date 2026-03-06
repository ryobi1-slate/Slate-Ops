import React from 'react';
import { QcInspection } from '../types';

interface QcDashboardProps {
  inspections: QcInspection[];
}

export function QcDashboard({ inspections }: QcDashboardProps) {
  return (
    <div className="flex-1 overflow-y-auto bg-[#EAE8DC] p-8 font-sans">
      <div className="flex justify-between items-center mb-8">
        <div>
          <h1 className="text-3xl font-bold text-slate-900">QC Queue</h1>
          <p className="text-slate-600">Automotive Upfit Quality Control Station</p>
        </div>
        <button className="bg-slate-700 hover:bg-slate-800 text-white font-bold py-2 px-4 rounded shadow-sm transition-colors">
          New Inspection
        </button>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div className="bg-white p-6 rounded-xl shadow-sm">
          <div className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Pending Inspection</div>
          <div className="flex items-baseline gap-2">
            <span className="text-4xl font-bold text-slate-900">12</span>
            <span className="text-sm font-medium text-orange-500">+3 since 8AM</span>
          </div>
        </div>
        <div className="bg-white p-6 rounded-xl shadow-sm">
          <div className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Failed QC</div>
          <div className="flex items-baseline gap-2">
            <span className="text-4xl font-bold text-red-600">4</span>
            <span className="text-sm font-medium text-slate-500">Requiring Rework</span>
          </div>
        </div>
        <div className="bg-white p-6 rounded-xl shadow-sm">
          <div className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Passed Today</div>
          <div className="flex items-baseline gap-2">
            <span className="text-4xl font-bold text-green-600">28</span>
            <span className="text-sm font-medium text-slate-400">92% Daily Target</span>
          </div>
        </div>
      </div>

      {/* Jobs Pending QC List */}
      <div className="bg-white rounded-xl shadow-sm overflow-hidden">
        <div className="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
          <h2 className="font-bold text-sm text-slate-700 uppercase tracking-wide">Jobs Pending QC</h2>
          <input 
            type="text" 
            placeholder="Search SO# or VIN..." 
            className="border border-slate-200 rounded-md px-3 py-1.5 text-sm w-64 focus:ring-primary focus:border-primary"
          />
        </div>
        
        <table className="w-full text-sm text-left">
          <thead className="bg-slate-50 text-slate-500 font-bold text-xs uppercase tracking-wider border-b border-slate-100">
            <tr>
              <th className="px-6 py-4">SO Number</th>
              <th className="px-6 py-4">Customer</th>
              <th className="px-6 py-4">VIN (Last 8)</th>
              <th className="px-6 py-4">Time in Queue</th>
              <th className="px-6 py-4">Technician</th>
              <th className="px-6 py-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {inspections.map((job) => (
              <tr key={job.id} className="hover:bg-slate-50 transition-colors">
                <td className="px-6 py-4 font-bold text-orange-600">{job.so_number}</td>
                <td className="px-6 py-4 font-medium text-slate-900">{job.customer}</td>
                <td className="px-6 py-4 font-mono text-slate-600">{job.vin}</td>
                <td className="px-6 py-4">
                  <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                    ${job.time_in_queue.includes('h') ? 'bg-red-100 text-red-800' : 
                      job.time_in_queue.includes('45m') ? 'bg-slate-100 text-slate-600' : 
                      'bg-slate-100 text-slate-600'}`}>
                    {job.time_in_queue}
                  </span>
                </td>
                <td className="px-6 py-4 text-slate-600">{job.technician}</td>
                <td className="px-6 py-4 text-right">
                  <button className="text-slate-600 hover:text-slate-900 font-bold text-xs uppercase tracking-wide">
                    Inspect
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        
        <div className="px-6 py-4 border-t border-slate-100 flex justify-between items-center text-xs text-slate-500">
          <span>Showing 5 of 12 pending jobs</span>
          <div className="flex gap-2">
            <button className="px-3 py-1 border border-slate-200 rounded hover:bg-slate-50 disabled:opacity-50" disabled>Prev</button>
            <button className="px-3 py-1 border border-slate-200 rounded hover:bg-slate-50">Next</button>
          </div>
        </div>
      </div>
    </div>
  );
}
