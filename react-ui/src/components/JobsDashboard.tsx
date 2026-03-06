import React, { useState } from 'react';
import { Job, Dealer } from '../types';

interface JobsDashboardProps {
  jobs: Job[];
  dealers: Dealer[];
}

export function JobsDashboard({ jobs, dealers }: JobsDashboardProps) {
  const [activeTab, setActiveTab] = useState('Active Jobs');

  // Filter logic based on tabs
  const filteredJobs = jobs.filter(job => {
    if (activeTab === 'Active Jobs') return job.status === 'ACTIVE';
    if (activeTab === 'Pending QC') return job.stage === 'FINAL QC'; // Example logic
    if (activeTab === 'Recently Completed') return job.status === 'COMPLETED';
    return true;
  });

  const stats = {
    total: jobs.filter(j => j.status === 'ACTIVE').length,
    pending_qc: jobs.filter(j => j.stage === 'FINAL QC').length,
    delayed: 0, // Logic for delayed jobs would go here
    completed: jobs.filter(j => j.status === 'COMPLETED').length
  };

  return (
    <div className="flex-1 overflow-y-auto bg-[#EAE8DC] p-8 font-sans">
      <div className="flex justify-between items-center mb-8">
        <div className="flex items-center gap-4">
          <h1 className="text-3xl font-bold text-slate-900">JOBS</h1>
          <div className="relative">
            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 material-symbols-outlined text-sm">search</span>
            <input 
              type="text" 
              placeholder="Search by SO#, customer, or address..." 
              className="pl-9 pr-4 py-2 border border-slate-200 rounded-md text-sm w-96 focus:ring-primary focus:border-primary bg-slate-50"
            />
          </div>
        </div>
        <div className="flex items-center gap-3">
          <button className="text-slate-500 hover:text-slate-700">
            <span className="material-symbols-outlined">notifications</span>
          </button>
          <button className="text-slate-500 hover:text-slate-700">
            <span className="material-symbols-outlined">mail</span>
          </button>
          <button className="bg-slate-700 hover:bg-slate-800 text-white font-bold py-2 px-4 rounded shadow-sm flex items-center gap-2">
            <span className="material-symbols-outlined text-sm">add</span>
            New Job
          </button>
        </div>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div className="bg-white p-6 rounded-xl shadow-sm">
          <div className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">TOTAL ACTIVE</div>
          <div className="text-4xl font-bold text-slate-900">{stats.total}</div>
        </div>
        <div className="bg-white p-6 rounded-xl shadow-sm">
          <div className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">PENDING QC</div>
          <div className="text-4xl font-bold text-orange-400">{stats.pending_qc}</div>
        </div>
        <div className="bg-white p-6 rounded-xl shadow-sm">
          <div className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">DELAYED</div>
          <div className="text-4xl font-bold text-red-700">{stats.delayed}</div>
        </div>
        <div className="bg-white p-6 rounded-xl shadow-sm">
          <div className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">COMPLETED (MTD)</div>
          <div className="text-4xl font-bold text-slate-700">{stats.completed}</div>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex border-b border-slate-300 mb-6">
        {['Active Jobs', 'Pending QC', 'Recently Completed', 'Archive'].map((tab) => (
          <button
            key={tab}
            onClick={() => setActiveTab(tab)}
            className={`px-6 py-3 text-sm font-bold transition-colors border-b-2 
              ${activeTab === tab 
                ? 'border-blue-600 text-slate-900' 
                : 'border-transparent text-slate-500 hover:text-slate-700'}`}
          >
            {tab}
          </button>
        ))}
      </div>

      {/* Jobs List */}
      <div className="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
        <table className="w-full text-sm text-left">
          <thead className="bg-white text-slate-500 font-bold text-xs uppercase tracking-wider border-b border-slate-100">
            <tr>
              <th className="px-6 py-4">SO#</th>
              <th className="px-6 py-4">Customer Name</th>
              <th className="px-6 py-4">Vehicle / Model</th>
              <th className="px-6 py-4">VIN (Last 8)</th>
              <th className="px-6 py-4">Stage</th>
              <th className="px-6 py-4">Scheduled Date</th>
              <th className="px-6 py-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {filteredJobs.map((job) => (
              <tr key={job.id} className="hover:bg-slate-50 transition-colors">
                <td className="px-6 py-4 font-bold text-slate-900">{job.so_number || '-'}</td>
                <td className="px-6 py-4">
                  <div className="font-bold text-slate-900">{job.customer_name}</div>
                  <div className="text-xs text-slate-500">{job.fleet}</div>
                </td>
                <td className="px-6 py-4 text-slate-600">{job.vehicle}</td>
                <td className="px-6 py-4 font-mono text-slate-500">{job.vin}</td>
                <td className="px-6 py-4">
                  <span className={`inline-flex items-center px-3 py-1 rounded border text-[10px] font-bold uppercase tracking-wide
                    ${job.stage === 'INTERIOR UPFIT' ? 'bg-orange-50 text-orange-800 border-orange-200' : 
                      job.stage === 'SUSPENSION' ? 'bg-red-50 text-red-800 border-red-200' : 
                      job.stage === 'ELECTRICAL' ? 'bg-slate-100 text-slate-700 border-slate-300' : 
                      job.stage === 'FINAL QC' ? 'bg-blue-50 text-blue-700 border-blue-200' : 
                      job.stage === 'PAINT & BODY' ? 'bg-orange-100 text-orange-700 border-orange-200' : 
                      'bg-slate-100 text-slate-600 border-slate-200'}`}>
                    {job.stage || job.status}
                  </span>
                </td>
                <td className="px-6 py-4 text-slate-600">{new Date(job.created_at).toLocaleDateString()}</td>
                <td className="px-6 py-4 text-right">
                  <button className="text-slate-500 hover:text-slate-800 font-bold text-[10px] uppercase tracking-wide">
                    View Details
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        
        <div className="px-6 py-4 border-t border-slate-100 flex justify-between items-center text-xs text-slate-500">
          <span>Showing {filteredJobs.length} results</span>
          <div className="flex gap-2">
            <button className="px-3 py-1 border border-slate-200 rounded hover:bg-slate-50 disabled:opacity-50" disabled>Previous</button>
            <button className="px-3 py-1 border border-slate-200 rounded hover:bg-slate-50">Next</button>
          </div>
        </div>
      </div>

      {/* Bottom Action */}
      <div className="bg-white p-4 rounded-xl shadow-sm flex justify-between items-center">
        <div className="flex items-center gap-4">
          <div className="w-10 h-10 bg-[#EAE8DC] rounded flex items-center justify-center text-slate-600">
            <span className="material-symbols-outlined">garage</span>
          </div>
          <div>
            <div className="font-bold text-slate-900">Bay Assignment View Available</div>
            <div className="text-xs text-slate-500">View all active vehicle bays on the workshop floor map.</div>
          </div>
        </div>
        <button className="px-4 py-2 border border-slate-300 rounded font-bold text-slate-700 hover:bg-slate-50 text-sm">
          Switch to Bay View
        </button>
      </div>
    </div>
  );
}
