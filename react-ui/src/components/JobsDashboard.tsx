import React, { useState } from 'react';
import { Job, Dealer } from '../types';

interface JobsDashboardProps {
  jobs: Job[];
  dealers: Dealer[];
}

export function JobsDashboard({ jobs, dealers }: JobsDashboardProps) {
  const [activeTab, setActiveTab] = useState('Active Jobs');
  const [search, setSearch] = useState('');

  const filteredJobs = jobs.filter(job => {
    let match = false;
    if (activeTab === 'Active Jobs')             match = ['IN_PROGRESS','SCHEDULED','READY_FOR_SCHEDULING','UNSCHEDULED'].includes(job.status);
    else if (activeTab === 'Pending QC')         match = job.status === 'PENDING_QC';
    else if (activeTab === 'Recently Completed') match = ['COMPLETE','COMPLETED'].includes(job.status);
    else                                         match = true;

    if (search) {
      const s = search.toLowerCase();
      return match && (
        (job.so_number || '').toLowerCase().includes(s) ||
        (job.customer_name || '').toLowerCase().includes(s) ||
        (job.vin || '').toLowerCase().includes(s)
      );
    }
    return match;
  });

  const stats = {
    total:      jobs.filter(j => ['IN_PROGRESS','SCHEDULED','READY_FOR_SCHEDULING','UNSCHEDULED'].includes(j.status)).length,
    pending_qc: jobs.filter(j => j.status === 'PENDING_QC').length,
    delayed:    0,
    completed:  jobs.filter(j => ['COMPLETE','COMPLETED'].includes(j.status)).length,
  };

  return (
    <div className="flex-1 overflow-y-auto bg-[#EAE8DC] font-sans">
      {/* Top bar */}
      <div className="flex justify-between items-center px-8 py-4 bg-[#EAE8DC]">
        <h1 className="text-2xl font-bold text-slate-900 uppercase tracking-tight">Jobs</h1>
        <div className="flex items-center gap-3">
          <input
            type="text"
            placeholder="Search by SO#, customer..."
            value={search}
            onChange={e => setSearch(e.target.value)}
            className="border border-slate-300 rounded-md px-4 py-2 text-sm w-64 bg-white focus:outline-none focus:ring-1 focus:ring-[#d86b19] focus:border-[#d86b19]"
          />
          <button className="text-slate-500 hover:text-slate-700 p-2">
            <span className="material-symbols-outlined text-xl">download</span>
          </button>
          <button className="text-slate-500 hover:text-slate-700 p-2">
            <span className="material-symbols-outlined text-xl">settings</span>
          </button>
          <button className="bg-[#d86b19] hover:bg-[#c05e14] text-white font-bold py-2 px-4 rounded flex items-center gap-1.5 text-sm transition-colors">
            <span className="material-symbols-outlined text-sm">add</span>
            New Job
          </button>
        </div>
      </div>

      <div className="px-8 pb-8">
        {/* Stats Cards */}
        <div className="grid grid-cols-4 gap-4 mb-6">
          {[
            { label: 'Total Active',    value: stats.total },
            { label: 'Pending QC',      value: stats.pending_qc },
            { label: 'Delayed',         value: stats.delayed },
            { label: 'Completed (MTD)', value: stats.completed },
          ].map(s => (
            <div key={s.label} className="bg-white p-6 rounded-xl shadow-sm">
              <div className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">{s.label}</div>
              <div className="text-4xl font-bold text-slate-900">{s.value}</div>
            </div>
          ))}
        </div>

        {/* Jobs Table */}
        <div className="bg-white rounded-xl shadow-sm overflow-hidden mb-4">
          {/* Tabs */}
          <div className="flex border-b border-slate-200">
            {['Active Jobs', 'Pending QC', 'Recently Completed', 'Archive'].map(tab => (
              <button
                key={tab}
                onClick={() => setActiveTab(tab)}
                className={`px-6 py-4 text-sm font-bold transition-colors border-b-2 -mb-px
                  ${activeTab === tab
                    ? 'border-[#d86b19] text-slate-900'
                    : 'border-transparent text-slate-500 hover:text-slate-700'}`}
              >
                {tab}
              </button>
            ))}
          </div>

          <table className="w-full text-sm text-left">
            <thead className="text-slate-400 font-bold text-[10px] uppercase tracking-wider border-b border-slate-100">
              <tr>
                <th className="px-6 py-3">#SO#</th>
                <th className="px-6 py-3">Customer Name</th>
                <th className="px-6 py-3">Vehicle / Model</th>
                <th className="px-6 py-3">VIN (Last 6)</th>
                <th className="px-6 py-3">Stage</th>
                <th className="px-6 py-3">Scheduled Date</th>
                <th className="px-6 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {filteredJobs.map(job => (
                <tr key={job.id ?? job.job_id} className="hover:bg-slate-50 transition-colors">
                  <td className="px-6 py-3 font-bold text-slate-900 align-middle">{job.so_number || '—'}</td>
                  <td className="px-6 py-3 font-medium text-slate-900 align-middle">{job.customer_name || '—'}</td>
                  <td className="px-6 py-3 text-slate-600 align-middle">{job.job_type || '—'}</td>
                  <td className="px-6 py-3 font-mono text-slate-500 align-middle text-xs">
                    {job.vin ? job.vin.slice(-6) : '—'}
                  </td>
                  <td className="px-6 py-3 align-middle">
                    <span className="inline-flex items-center px-2.5 py-1 rounded border text-[10px] font-bold uppercase tracking-wide bg-slate-100 text-slate-600 border-slate-200">
                      {job.status.replace(/_/g, ' ')}
                    </span>
                  </td>
                  <td className="px-6 py-3 text-slate-600 align-middle">
                    {job.due_date || (job.created_at ? new Date(job.created_at).toLocaleDateString() : '—')}
                  </td>
                  <td className="px-6 py-3 text-right align-middle">
                    <button className="text-slate-500 hover:text-slate-800 font-bold text-[10px] uppercase tracking-wide">
                      View
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>

          <div className="px-6 py-4 border-t border-slate-100 flex justify-between items-center">
            <span className="text-xs text-slate-500">Showing {filteredJobs.length} results</span>
            <div className="flex gap-2">
              <button className="border border-[#d86b19] text-[#d86b19] hover:bg-orange-50 text-xs font-bold px-4 py-1.5 rounded transition-colors">Previous</button>
              <button className="border border-slate-300 text-slate-700 hover:bg-slate-50 text-xs font-bold px-4 py-1.5 rounded transition-colors">Next</button>
            </div>
          </div>
        </div>

        {/* Bay Assignment info card */}
        <div className="bg-slate-50 border border-slate-200 rounded-xl p-4 flex justify-between items-center">
          <div className="flex items-start gap-3">
            <span className="material-symbols-outlined text-slate-500 text-xl mt-0.5">info</span>
            <div>
              <div className="font-bold text-slate-800 text-sm">Bay Assignment View Available</div>
              <div className="text-xs text-slate-500 mt-0.5">Switch to the workshop floor map or list layout to view jobs by bays.</div>
            </div>
          </div>
          <button className="border border-[#d86b19] text-[#d86b19] hover:bg-orange-50 font-bold text-sm px-4 py-2 rounded transition-colors flex-shrink-0 ml-4">
            Switch to Bay View
          </button>
        </div>
      </div>
    </div>
  );
}
