import React from 'react';
import { Job } from '../types';

interface SupervisorDashboardProps {
  jobs: Job[];
}

const MOCK_CORRECTIONS = [
  { id: 1, so: 'S-ORD101204', title: 'Wiring Harness Misrouting', reporter: 'QC Lead (Sarah M.)', status: 'Critical' },
  { id: 2, so: 'S-ORD101211', title: 'Front Winch Mounting Torque Issue', reporter: 'QC Tech (Dave R.)', status: 'Critical' },
  { id: 3, so: 'S-ORD101215', title: 'Inverter Panel Sealant Inconsistency', reporter: 'Auto-Log Alert', status: 'Critical' },
];

export function SupervisorDashboard({ jobs }: SupervisorDashboardProps) {
  // Active = any job that is in production but not yet complete
  const schedule = jobs
    .filter(j => ['IN_PROGRESS', 'SCHEDULED', 'READY_FOR_SCHEDULING'].includes(j.status))
    .slice(0, 10);

  return (
    <div className="flex-1 overflow-y-auto bg-[#EAE8DC] p-8 font-sans">
      <div className="flex justify-between items-center mb-8">
        <h1 className="text-3xl font-bold text-slate-900">Supervisor Operations Center</h1>
        <div className="flex items-center gap-4">
          <div className="text-sm text-slate-500">System Status: <span className="text-green-600 font-bold">Optimal</span></div>
          <div className="w-8 h-8 rounded-full bg-slate-700 text-white flex items-center justify-center font-bold text-xs">JD</div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        {/* Pending Corrections */}
        <div className="bg-white rounded-xl shadow-sm overflow-hidden">
          <div className="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h2 className="font-bold text-sm text-slate-700 uppercase tracking-wide">PENDING CORRECTIONS</h2>
            <span className="bg-slate-700 text-white text-xs font-bold px-2 py-1 rounded">{MOCK_CORRECTIONS.length} Critical</span>
          </div>
          <div className="divide-y divide-slate-100">
            {MOCK_CORRECTIONS.map((item) => (
              <div key={item.id} className="p-6 flex justify-between items-start">
                <div>
                  <div className="text-xs font-bold text-slate-500 mb-1">{item.so}</div>
                  <div className="font-bold text-slate-900 mb-1">{item.title}</div>
                  <div className="text-xs text-slate-400">Reported by: {item.reporter}</div>
                </div>
                <button className="px-3 py-1 border border-slate-200 rounded text-xs font-bold text-slate-600 hover:bg-slate-50">
                  Re-assign
                </button>
              </div>
            ))}
          </div>
        </div>

        {/* Unassigned Personnel Time */}
        <div className="bg-white rounded-xl shadow-sm overflow-hidden">
          <div className="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h2 className="font-bold text-sm text-slate-700 uppercase tracking-wide">UNASSIGNED PERSONNEL TIME</h2>
            <span className="text-xs text-slate-400">Week 42 Data</span>
          </div>
          <div className="p-6 grid grid-cols-2 gap-6">
            <div className="border border-dashed border-slate-200 rounded-lg p-6 text-center">
              <div className="text-3xl font-bold text-slate-900 mb-1">14.5h</div>
              <div className="text-xs text-slate-500 mb-4">Shop Floor General</div>
              <button className="w-full py-2 bg-slate-700 text-white text-xs font-bold rounded hover:bg-slate-800">View Block</button>
            </div>
            <div className="border border-dashed border-slate-200 rounded-lg p-6 text-center">
              <div className="text-3xl font-bold text-slate-900 mb-1">08.2h</div>
              <div className="text-xs text-slate-500 mb-4">Electrical Bench</div>
              <button className="w-full py-2 bg-slate-700 text-white text-xs font-bold rounded hover:bg-slate-800">View Block</button>
            </div>
            <div className="border border-dashed border-slate-200 rounded-lg p-6 text-center">
              <div className="text-3xl font-bold text-slate-900 mb-1">03.0h</div>
              <div className="text-xs text-slate-500 mb-4">Paint Booth Prep</div>
              <button className="w-full py-2 bg-slate-700 text-white text-xs font-bold rounded hover:bg-slate-800">View Block</button>
            </div>
            <div className="border border-dashed border-slate-200 rounded-lg p-6 text-center flex flex-col justify-center items-center bg-slate-50/50">
              <div className="text-xl font-bold text-slate-300 italic mb-1">No Data</div>
              <div className="text-xs text-slate-400">Admin Overhead</div>
            </div>
          </div>
        </div>
      </div>

      {/* Today's Production Schedule */}
      <div className="bg-white rounded-xl shadow-sm overflow-hidden">
        <div className="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
          <h2 className="font-bold text-sm text-slate-700 uppercase tracking-wide">TODAY'S PRODUCTION SCHEDULE</h2>
          <div className="flex gap-2">
            <button className="px-3 py-1 border border-slate-200 rounded text-xs font-bold text-slate-600 hover:bg-slate-50">Export CSV</button>
            <button className="px-3 py-1 bg-slate-100 text-slate-400 rounded text-xs font-bold cursor-not-allowed">Add Job</button>
          </div>
        </div>
        
        {schedule.length === 0 ? (
          <div className="px-6 py-10 text-center text-slate-400 text-sm italic">No jobs currently in production</div>
        ) : (
        <table className="w-full text-sm text-left">
          <thead className="bg-slate-50 text-slate-400 font-bold text-[10px] uppercase tracking-wider border-b border-slate-100">
            <tr>
              <th className="px-6 py-3">SO NUMBER</th>
              <th className="px-6 py-3">CUSTOMER</th>
              <th className="px-6 py-3">WORK CENTER</th>
              <th className="px-6 py-3">ETA COMPLETION</th>
              <th className="px-6 py-3 text-right">STATUS</th>
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
