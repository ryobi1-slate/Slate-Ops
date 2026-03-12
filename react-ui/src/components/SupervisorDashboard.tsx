import React from 'react';
import { Job } from '../types';

interface SupervisorDashboardProps {
  jobs: Job[];
}

const MOCK_CORRECTIONS = [
  { id: 1, so: 'S-ORD10284', title: 'Wiring Harness Misrouting', reporter: 'Dr. Nick Riviera' },
  { id: 2, so: 'S-ORD10311', title: 'Front Winch Mounting Torque Issue', reporter: 'Dr. Nick Riviera' },
  { id: 3, so: 'S-ORD10316', title: 'Inverter Panel Sealant Inconsistency', reporter: 'Auto Lag Part' },
];

const MOCK_PERSONNEL = [
  { name: 'Ryan Ouellette', hours: '14.5h', label: '' },
  { name: 'Eddrick Bridge', hours: '08.2h', label: '' },
  { name: 'Eddrick Bridge', hours: '03.0h', label: 'Auto-Overhead' },
];

export function SupervisorDashboard({ jobs }: SupervisorDashboardProps) {
  const schedule = jobs
    .filter(j => ['IN_PROGRESS', 'SCHEDULED', 'READY_FOR_SCHEDULING'].includes(j.status))
    .slice(0, 10);

  return (
    <div className="flex-1 overflow-y-auto bg-[#EAE8DC] p-8 font-sans">
      <div className="flex justify-between items-center mb-8">
        <h1 className="text-3xl font-bold text-slate-900">Supervisor Operations Center</h1>
        <div className="flex items-center gap-4">
          <div className="text-sm text-slate-500">System Status: <span className="text-green-600 font-bold">Optimal</span></div>
          <button className="w-9 h-9 rounded-full bg-slate-700 text-white flex items-center justify-center hover:bg-slate-800">
            <span className="material-symbols-outlined text-base">settings</span>
          </button>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        {/* Pending Corrections */}
        <div className="bg-white rounded-xl shadow-sm overflow-hidden">
          <div className="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
            <h2 className="font-bold text-sm text-slate-900 uppercase tracking-wide">Pending Corrections</h2>
            <button className="bg-[#d86b19] hover:bg-[#c05e14] text-white ops-btn-arches-solid text-xs font-bold px-4 py-1.5 rounded transition-colors">
              SCHEDULE
            </button>
          </div>
          <div className="p-4 flex flex-col gap-3">
            {MOCK_CORRECTIONS.map((item) => (
              <div key={item.id} className="border border-slate-200 rounded-lg p-4 flex justify-between items-center">
                <div>
                  <div className="text-xs font-bold text-slate-900 mb-0.5">{item.so}</div>
                  <div className="font-bold text-slate-900 text-sm mb-0.5">{item.title}</div>
                  <div className="text-xs text-slate-400">Reported by {item.reporter}</div>
                </div>
                <button className="border border-[#d86b19] text-[#d86b19] ops-btn-arches hover:bg-orange-50 text-xs font-bold px-3 py-1.5 rounded transition-colors flex-shrink-0 ml-4">
                  Re-assign
                </button>
              </div>
            ))}
          </div>
        </div>

        {/* Unassigned Personnel Time */}
        <div className="bg-white rounded-xl shadow-sm overflow-hidden">
          <div className="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
            <h2 className="font-bold text-sm text-slate-900 uppercase tracking-wide">Unassigned Personnel Time</h2>
            <button className="border border-slate-300 text-slate-700 hover:bg-slate-50 text-xs font-bold px-3 py-1.5 rounded transition-colors">
              Mark All Edit
            </button>
          </div>
          <div className="p-4 grid grid-cols-3 gap-3">
            {MOCK_PERSONNEL.map((p, i) => (
              <div key={i} className="border border-slate-200 rounded-lg p-4 text-center flex flex-col items-center gap-2">
                <div className="text-xs font-bold text-slate-700">{p.name}</div>
                <div className="text-3xl font-bold text-slate-900">{p.hours}</div>
                {p.label && <div className="text-[10px] text-slate-400">{p.label}</div>}
                <button className="w-full border border-[#d86b19] text-[#d86b19] ops-btn-arches hover:bg-orange-50 text-xs font-bold py-1.5 rounded transition-colors mt-1">
                  View Block
                </button>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Today's Production Schedule */}
      <div className="bg-white rounded-xl shadow-sm overflow-hidden">
        <div className="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
          <h2 className="font-bold text-sm text-slate-900 uppercase tracking-wide">Today's Production Schedule</h2>
          <div className="flex gap-2">
            <button className="border border-[#d86b19] text-[#d86b19] ops-btn-arches hover:bg-orange-50 rounded text-xs font-bold px-3 py-1.5 transition-colors">Export CSV</button>
            <button className="border border-[#d86b19] text-[#d86b19] ops-btn-arches hover:bg-orange-50 rounded text-xs font-bold px-3 py-1.5 transition-colors">Add Job</button>
          </div>
        </div>

        {schedule.length === 0 ? (
          <div className="px-6 py-10 text-center text-slate-400 text-sm">No scheduled jobs for today</div>
        ) : (
          <table className="w-full text-sm text-left">
            <thead className="bg-slate-50 text-slate-400 font-bold text-[10px] uppercase tracking-wider border-b border-slate-100">
              <tr>
                <th className="px-6 py-3">TB Number</th>
                <th className="px-6 py-3">Vehicle Type</th>
                <th className="px-6 py-3">Station</th>
                <th className="px-6 py-3">Technician</th>
                <th className="px-6 py-3">Est. Completion</th>
                <th className="px-6 py-3 text-right">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {schedule.map((item) => (
                <tr key={item.id ?? item.job_id} className="hover:bg-slate-50 transition-colors">
                  <td className="px-6 py-3 font-bold text-slate-900 align-middle">{item.so_number || '—'}</td>
                  <td className="px-6 py-3 text-slate-700 align-middle">{item.customer_name || '—'}</td>
                  <td className="px-6 py-3 align-middle">
                    <span className="text-[10px] font-bold px-2 py-1 rounded uppercase tracking-wide bg-slate-100 text-slate-600">
                      {item.work_center || 'Unassigned'}
                    </span>
                  </td>
                  <td className="px-6 py-3 text-slate-600 align-middle">—</td>
                  <td className="px-6 py-3 font-mono text-slate-600 align-middle">{item.scheduled_finish ? new Date(item.scheduled_finish).toLocaleDateString() : (item.due_date || 'TBD')}</td>
                  <td className="px-6 py-3 text-right align-middle">
                    <span className={`inline-flex items-center px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wide
                      ${item.status === 'IN_PROGRESS' ? 'bg-green-100 text-green-800' :
                        item.status === 'SCHEDULED'   ? 'bg-blue-100 text-blue-700' :
                        'bg-slate-100 text-slate-600'}`}>
                      <span className={`w-1.5 h-1.5 rounded-full mr-1.5
                        ${item.status === 'IN_PROGRESS' ? 'bg-green-500' :
                          item.status === 'SCHEDULED'   ? 'bg-blue-400' :
                          'bg-slate-400'}`} />
                      {item.status.replace(/_/g, ' ')}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
