import React from 'react';
import { Job } from '../types';

interface ExecutiveDashboardProps {
  jobs: Job[];
}

export function ExecutiveDashboard({ jobs }: ExecutiveDashboardProps) {
  // Calculate stats
  const stats = {
    needs_so: jobs.filter(j => j.status === 'NEEDS_SO').length,
    unscheduled: jobs.filter(j => j.status === 'PENDING_INTAKE').length, // Proxy
    scheduled: jobs.filter(j => j.stage === 'SCHEDULED').length,
    in_progress: jobs.filter(j => j.status === 'ACTIVE' && j.stage !== 'SCHEDULED' && j.stage !== 'FINAL QC').length,
    pending_qc: jobs.filter(j => j.stage === 'FINAL QC').length,
    completed: jobs.filter(j => j.status === 'COMPLETED').length
  };

  // Recent Activity (using created_at as proxy for updated)
  const recentActivity = [...jobs]
    .sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
    .slice(0, 5);

  return (
    <div className="flex-1 overflow-y-auto bg-[#EAE8DC] p-8 font-sans">
      <div className="flex justify-between items-center mb-8">
        <div>
          <h1 className="text-3xl font-bold text-slate-900">EXECUTIVE DASHBOARD</h1>
        </div>
        <div className="flex items-center gap-4">
          <input 
            type="text" 
            placeholder="Search orders, clients..." 
            className="border border-slate-200 rounded-md px-4 py-2 text-sm w-64 focus:ring-primary focus:border-primary"
          />
          <button className="text-slate-400 hover:text-slate-600">
            <span className="material-symbols-outlined">notifications</span>
          </button>
          <div className="flex items-center gap-3">
            <div className="text-right">
              <div className="text-sm font-bold text-slate-900">Alex Sterling</div>
              <div className="text-xs text-slate-500">Executive Admin</div>
            </div>
            <div className="w-10 h-10 rounded-full bg-orange-200 flex items-center justify-center text-orange-700 font-bold">
              AS
            </div>
          </div>
        </div>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-6 gap-4 mb-8">
        <div className="bg-white p-4 rounded-xl shadow-sm flex flex-col justify-between h-32">
          <div className="flex justify-between items-start">
            <div className="w-8 h-8 rounded bg-orange-100 text-orange-600 flex items-center justify-center">
              <span className="material-symbols-outlined text-lg">error</span>
            </div>
            <span className="bg-slate-800 text-white text-[10px] font-bold px-1.5 py-0.5 rounded">+5%</span>
          </div>
          <div>
            <div className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">NEEDS SO#</div>
            <div className="text-2xl font-bold text-slate-900">{stats.needs_so}</div>
          </div>
        </div>

        <div className="bg-white p-4 rounded-xl shadow-sm flex flex-col justify-between h-32">
          <div className="flex justify-between items-start">
            <div className="w-8 h-8 rounded bg-blue-100 text-blue-600 flex items-center justify-center">
              <span className="material-symbols-outlined text-lg">schedule</span>
            </div>
            <span className="bg-orange-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded">-2%</span>
          </div>
          <div>
            <div className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">UNSCHEDULED</div>
            <div className="text-2xl font-bold text-slate-900">{stats.unscheduled}</div>
          </div>
        </div>

        <div className="bg-white p-4 rounded-xl shadow-sm flex flex-col justify-between h-32">
          <div className="flex justify-between items-start">
            <div className="w-8 h-8 rounded bg-slate-100 text-slate-600 flex items-center justify-center">
              <span className="material-symbols-outlined text-lg">calendar_today</span>
            </div>
            <span className="bg-slate-800 text-white text-[10px] font-bold px-1.5 py-0.5 rounded">+5%</span>
          </div>
          <div>
            <div className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">SCHEDULED</div>
            <div className="text-2xl font-bold text-slate-900">{stats.scheduled}</div>
          </div>
        </div>

        <div className="bg-white p-4 rounded-xl shadow-sm flex flex-col justify-between h-32">
          <div className="flex justify-between items-start">
            <div className="w-8 h-8 rounded bg-indigo-100 text-indigo-600 flex items-center justify-center">
              <span className="material-symbols-outlined text-lg">autorenew</span>
            </div>
            <span className="bg-slate-800 text-white text-[10px] font-bold px-1.5 py-0.5 rounded">+5%</span>
          </div>
          <div>
            <div className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">IN PROGRESS</div>
            <div className="text-2xl font-bold text-slate-900">{stats.in_progress}</div>
          </div>
        </div>

        <div className="bg-white p-4 rounded-xl shadow-sm flex flex-col justify-between h-32">
          <div className="flex justify-between items-start">
            <div className="w-8 h-8 rounded bg-purple-100 text-purple-600 flex items-center justify-center">
              <span className="material-symbols-outlined text-lg">fact_check</span>
            </div>
            <span className="bg-slate-100 text-slate-500 text-[10px] font-bold px-1.5 py-0.5 rounded">0%</span>
          </div>
          <div>
            <div className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">PENDING QC</div>
            <div className="text-2xl font-bold text-slate-900">{stats.pending_qc}</div>
          </div>
        </div>

        <div className="bg-white p-4 rounded-xl shadow-sm flex flex-col justify-between h-32">
          <div className="flex justify-between items-start">
            <span className="bg-slate-800 text-white text-[10px] font-bold px-1.5 py-0.5 rounded">+5%</span>
            <span className="bg-slate-800 text-white text-[10px] font-bold px-1.5 py-0.5 rounded">+5%</span>
          </div>
          <div>
            <div className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">COMPLETE</div>
            <div className="text-2xl font-bold text-slate-900">{stats.completed}</div>
          </div>
        </div>
      </div>

      {/* Recent Activity */}
      <div className="bg-white rounded-xl shadow-sm overflow-hidden">
        <div className="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
          <h2 className="font-bold text-lg text-slate-800">Recent Activity</h2>
          <button className="text-sm font-bold text-slate-500 hover:text-slate-700">View All Activities</button>
        </div>
        
        <table className="w-full text-sm text-left">
          <thead className="bg-slate-50 text-slate-500 font-bold text-xs uppercase tracking-wider border-b border-slate-100">
            <tr>
              <th className="px-6 py-4">SO#</th>
              <th className="px-6 py-4">Customer</th>
              <th className="px-6 py-4">Status</th>
              <th className="px-6 py-4">Updated</th>
              <th className="px-6 py-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {recentActivity.map((item) => (
              <tr key={item.id} className="hover:bg-slate-50 transition-colors">
                <td className="px-6 py-4 font-bold text-slate-900">{item.so_number || '-'}</td>
                <td className="px-6 py-4 font-medium text-slate-600 flex items-center gap-2">
                  <span className="w-6 h-6 rounded bg-slate-100 flex items-center justify-center text-slate-400 text-xs">
                    <span className="material-symbols-outlined text-[14px]">business</span>
                  </span>
                  {item.customer_name}
                </td>
                <td className="px-6 py-4">
                  <span className={`inline-flex items-center px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide
                    ${item.status === 'NEEDS_SO' ? 'bg-[#EAE8DC] text-slate-700 border border-slate-300' : 
                      item.status === 'ACTIVE' ? 'bg-slate-700 text-white' : 
                      item.stage === 'FINAL QC' ? 'bg-[#1D3331] text-white' : 
                      item.stage === 'SCHEDULED' ? 'bg-orange-600 text-white' : 
                      'bg-slate-600 text-white'}`}>
                    {item.stage || item.status}
                  </span>
                </td>
                <td className="px-6 py-4 text-slate-500">{new Date(item.created_at).toLocaleDateString()}</td>
                <td className="px-6 py-4 text-right text-slate-400">
                  <button className="hover:text-slate-600">
                    <span className="material-symbols-outlined">more_horiz</span>
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
