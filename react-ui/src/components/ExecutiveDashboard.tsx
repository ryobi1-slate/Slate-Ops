import React from 'react';
import { Job } from '../types';

interface ExecutiveDashboardProps {
  jobs: Job[];
}

const STATUS_COLOR: Record<string, string> = {
  NEEDS_SO:             'bg-slate-700 text-white',
  PENDING_INTAKE:       'bg-slate-500 text-white',
  UNSCHEDULED:          'bg-slate-600 text-white',
  READY_FOR_SCHEDULING: 'bg-slate-700 text-white',
  SCHEDULED:            'bg-orange-600 text-white',
  IN_PROGRESS:          'bg-blue-700 text-white',
  PENDING_QC:           'bg-purple-700 text-white',
  COMPLETE:             'bg-green-700 text-white',
  COMPLETED:            'bg-green-700 text-white',
  ON_HOLD:              'bg-amber-600 text-white',
};

export function ExecutiveDashboard({ jobs }: ExecutiveDashboardProps) {
  const userName = typeof window !== 'undefined' ? (window.slateOpsSettings?.user?.name || 'Admin') : 'Admin';
  const initials = userName.split(' ').map((w: string) => w[0]).join('').slice(0, 2).toUpperCase();

  const stats = {
    needs_so:    jobs.filter(j => j.status === 'NEEDS_SO').length,
    unscheduled: jobs.filter(j => ['UNSCHEDULED', 'PENDING_INTAKE'].includes(j.status)).length,
    scheduled:   jobs.filter(j => ['SCHEDULED', 'READY_FOR_SCHEDULING'].includes(j.status)).length,
    in_progress: jobs.filter(j => j.status === 'IN_PROGRESS').length,
    pending_qc:  jobs.filter(j => j.status === 'PENDING_QC').length,
    completed:   jobs.filter(j => ['COMPLETE', 'COMPLETED'].includes(j.status)).length,
  };

  const recentActivity = [...jobs]
    .sort((a, b) => new Date(b.updated_at || b.created_at).getTime() - new Date(a.updated_at || a.created_at).getTime())
    .slice(0, 10);

  return (
    <div className="flex-1 overflow-y-auto bg-[#EAE8DC] p-8 font-sans">
      {/* Header */}
      <div className="flex justify-between items-center mb-8">
        <h1 className="text-3xl font-bold text-slate-900">EXECUTIVE DASHBOARD</h1>
        <div className="flex items-center gap-4">
          <input
            type="text"
            placeholder="Search orders, clients..."
            className="border border-slate-200 rounded-md px-4 py-2 text-sm w-64 bg-white focus:ring-primary focus:border-primary"
          />
          <button className="border border-slate-200 rounded p-2 text-slate-500 hover:text-slate-700 hover:bg-slate-50 transition-colors">
            <span className="material-symbols-outlined text-lg">notifications</span>
          </button>
          <div className="flex items-center gap-3">
            <div className="text-right">
              <div className="text-sm font-bold text-slate-900">{userName}</div>
              <div className="text-xs text-slate-500">Executive Admin</div>
            </div>
            <div className="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center text-slate-700 font-bold text-sm">
              {initials}
            </div>
          </div>
        </div>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-6 gap-4 mb-8">
        {[
          { label: 'NEEDS SO#',    value: stats.needs_so,    icon: 'error',         iconBg: 'bg-amber-100 text-amber-700',   badge: '', badgeBg: 'bg-slate-800 text-white' },
          { label: 'UNSCHEDULED',  value: stats.unscheduled, icon: 'schedule',       iconBg: 'bg-blue-100 text-blue-600',    badge: '', badgeBg: 'bg-slate-800 text-white' },
          { label: 'SCHEDULED',    value: stats.scheduled,   icon: 'calendar_today', iconBg: 'bg-slate-100 text-slate-600',  badge: '', badgeBg: 'bg-slate-800 text-white' },
          { label: 'IN PROGRESS',  value: stats.in_progress, icon: 'autorenew',      iconBg: 'bg-indigo-100 text-indigo-600',badge: '', badgeBg: 'bg-slate-800 text-white' },
          { label: 'PENDING QC',   value: stats.pending_qc,  icon: 'fact_check',     iconBg: 'bg-purple-100 text-purple-600',badge: '0%',badgeBg: 'bg-slate-100 text-slate-500' },
          { label: 'COMPLETE',     value: stats.completed,   icon: 'check_circle',   iconBg: 'bg-green-100 text-green-600',  badge: '', badgeBg: 'bg-slate-800 text-white' },
        ].map(stat => (
          <div key={stat.label} className="bg-white p-4 rounded-xl shadow-sm flex flex-col justify-between h-32">
            <div className="flex justify-between items-start">
              <div className={`w-8 h-8 rounded flex items-center justify-center ${stat.iconBg}`}>
                <span className="material-symbols-outlined text-lg">{stat.icon}</span>
              </div>
              {stat.badge && <span className={`text-[10px] font-bold px-1.5 py-0.5 rounded ${stat.badgeBg}`}>{stat.badge}</span>}
            </div>
            <div>
              <div className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">{stat.label}</div>
              <div className="text-2xl font-bold text-slate-900">{stat.value}</div>
            </div>
          </div>
        ))}
      </div>

      {/* Recent Activity */}
      <div className="bg-white rounded-xl shadow-sm overflow-hidden">
        <div className="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
          <h2 className="font-bold text-lg text-slate-800">Recent Activity</h2>
          <button className="text-sm font-bold text-slate-600 border border-slate-200 rounded px-3 py-1 hover:bg-slate-50 transition-colors">
            View All Activities
          </button>
        </div>

        <table className="w-full text-sm text-left">
          <thead className="bg-slate-50 text-slate-400 font-bold text-[10px] uppercase tracking-wider border-b border-slate-100">
            <tr>
              <th className="px-6 py-3">SO#</th>
              <th className="px-6 py-3">Customer</th>
              <th className="px-6 py-3">Status</th>
              <th className="px-6 py-3">Updated</th>
              <th className="px-6 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {recentActivity.length === 0 && (
              <tr><td colSpan={5} className="px-6 py-8 text-center text-slate-400 text-sm italic">No jobs yet</td></tr>
            )}
            {recentActivity.map((item) => (
              <tr key={item.id ?? item.job_id} className="hover:bg-slate-50 transition-colors">
                <td className="px-6 py-3 font-bold text-slate-900 align-middle">{item.so_number || '—'}</td>
                <td className="px-6 py-3 font-medium text-slate-600 align-middle">
                  <div className="flex items-center gap-2">
                    <span className="w-6 h-6 rounded bg-slate-100 flex items-center justify-center flex-shrink-0">
                      <span className="material-symbols-outlined text-slate-400 text-[14px]">business</span>
                    </span>
                    {item.customer_name || '—'}
                  </div>
                </td>
                <td className="px-6 py-3 align-middle">
                  <span className={`inline-flex items-center px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wide ${STATUS_COLOR[item.status] || 'bg-slate-200 text-slate-700'}`}>
                    {item.status}
                  </span>
                </td>
                <td className="px-6 py-3 text-slate-500 align-middle">
                  {item.updated_at ? new Date(item.updated_at).toLocaleDateString() : '—'}
                </td>
                <td className="px-6 py-3 text-right align-middle">
                  <button className="border border-slate-200 rounded p-1 text-slate-400 hover:text-slate-600 hover:bg-slate-50 transition-colors">
                    <span className="material-symbols-outlined text-sm">more_horiz</span>
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
