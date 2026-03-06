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
  // Filter for active jobs for the schedule
  const schedule = jobs.filter(j => j.status === 'ACTIVE').slice(0, 10); // Limit to 10 for display

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
            <span className="bg-orange-500 text-white text-xs font-bold px-2 py-1 rounded">4 Critical</span>
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
        
        <table className="w-full text-sm text-left">
          <thead className="bg-white text-slate-400 font-bold text-[10px] uppercase tracking-wider border-b border-slate-100">
            <tr>
              <th className="px-6 py-4">SO NUMBER</th>
              <th className="px-6 py-4">VEHICLE TYPE</th>
              <th className="px-6 py-4">STATION</th>
              <th className="px-6 py-4">TECHNICIAN</th>
              <th className="px-6 py-4">ETA COMPLETION</th>
              <th className="px-6 py-4 text-right">STATUS</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {schedule.map((item) => (
              <tr key={item.id} className="hover:bg-slate-50 transition-colors">
                <td className="px-6 py-4 font-bold text-slate-900">{item.so_number || '-'}</td>
                <td className="px-6 py-4 text-slate-700">{item.vehicle}</td>
                <td className="px-6 py-4">
                  <span className={`text-[10px] font-bold px-2 py-1 rounded uppercase tracking-wide
                    ${(item.stage || '').includes('QC') ? 'bg-blue-50 text-blue-600' : 'bg-slate-100 text-slate-600'}`}>
                    {item.stage || 'PENDING'}
                  </span>
                </td>
                <td className="px-6 py-4 text-slate-700">Unassigned</td>
                <td className="px-6 py-4 font-mono text-slate-600">{item.due_date || 'TBD'}</td>
                <td className="px-6 py-4 text-right">
                  <span className={`inline-flex items-center px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wide
                    ${item.status === 'ACTIVE' ? 'bg-green-100 text-green-800' : 
                      item.stage === 'FINAL QC' ? 'bg-blue-100 text-blue-800' : 
                      'bg-slate-100 text-slate-600'}`}>
                    <span className={`w-1.5 h-1.5 rounded-full mr-1.5 
                      ${item.status === 'ACTIVE' ? 'bg-green-500' : 
                        item.stage === 'FINAL QC' ? 'bg-blue-500' : 
                        'bg-slate-500'}`}></span>
                    {item.stage || item.status}
                  </span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
