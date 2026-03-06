import React, { useState } from 'react';
import { ProductionBay } from '../types';

const MOCK_BAYS: ProductionBay[] = [
  { id: 'BAY-A1', name: 'Bay A1', equipment_level: 'Full Lift / Electrical', status: 'Active' },
  { id: 'BAY-A2', name: 'Bay A2', equipment_level: 'Full Lift / Electrical', status: 'Active' },
  { id: 'BAY-B1', name: 'Bay B1', equipment_level: 'Storage / Pre-staging', status: 'Maintenance' },
];

export function SettingsDashboard() {
  const [general, setGeneral] = useState({
    companyName: 'Slate Automotive Upfitting Solutions Inc.',
    timezone: 'Mountain Standard Time',
    currency: 'USD ($)',
    holidaySync: true,
  });

  const [notifications, setNotifications] = useState({
    newJob: true,
    qcFailure: true,
    completionSms: true,
    marketingEmails: false,
    dailySummary: true,
    weeklyReport: false,
  });

  return (
    <div className="flex-1 overflow-y-auto bg-[#EAE8DC] p-8 font-sans">
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-slate-900">System Settings</h1>
        <p className="text-slate-600">Configure global parameters and production environment preferences.</p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        {/* General Settings */}
        <div className="bg-white rounded-xl shadow-sm p-6">
          <div className="mb-6">
            <h2 className="font-bold text-lg text-slate-800 mb-1">General Settings</h2>
            <p className="text-xs text-slate-400 uppercase tracking-wider">GLOBAL CONFIGURATION</p>
          </div>

          <div className="space-y-6">
            <div>
              <label className="block text-sm font-bold text-slate-700 mb-2">Company Legal Name</label>
              <input 
                type="text" 
                value={general.companyName}
                onChange={(e) => setGeneral({...general, companyName: e.target.value})}
                className="w-full border border-slate-200 rounded-md px-3 py-2 text-sm focus:ring-primary focus:border-primary"
              />
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-bold text-slate-700 mb-2">Timezone</label>
                <select 
                  value={general.timezone}
                  onChange={(e) => setGeneral({...general, timezone: e.target.value})}
                  className="w-full border border-slate-200 rounded-md px-3 py-2 text-sm focus:ring-primary focus:border-primary"
                >
                  <option>Mountain Standard Time</option>
                  <option>Pacific Standard Time</option>
                  <option>Central Standard Time</option>
                  <option>Eastern Standard Time</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-bold text-slate-700 mb-2">Currency Display</label>
                <select 
                  value={general.currency}
                  onChange={(e) => setGeneral({...general, currency: e.target.value})}
                  className="w-full border border-slate-200 rounded-md px-3 py-2 text-sm focus:ring-primary focus:border-primary"
                >
                  <option>USD ($)</option>
                  <option>CAD ($)</option>
                  <option>EUR (€)</option>
                </select>
              </div>
            </div>

            <div className="flex items-center justify-between pt-4 border-t border-slate-100">
              <div>
                <div className="font-bold text-slate-700 text-sm">Public Holiday Sync</div>
                <div className="text-xs text-slate-500">Automatically block off national holidays on the schedule.</div>
              </div>
              <label className="relative inline-flex items-center cursor-pointer">
                <input 
                  type="checkbox" 
                  checked={general.holidaySync}
                  onChange={(e) => setGeneral({...general, holidaySync: e.target.checked})}
                  className="sr-only peer" 
                />
                <div className="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-orange-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-orange-600"></div>
              </label>
            </div>

            <div className="pt-4 flex justify-end">
              <button className="bg-slate-700 hover:bg-slate-800 text-white font-bold py-2 px-6 rounded shadow-sm text-sm">
                Save General
              </button>
            </div>
          </div>
        </div>

        {/* Production Bay Configuration */}
        <div className="bg-white rounded-xl shadow-sm p-6">
          <div className="flex justify-between items-start mb-6">
            <div>
              <h2 className="font-bold text-lg text-slate-800 mb-1">Production Bay Configuration</h2>
              <p className="text-xs text-slate-400 uppercase tracking-wider">WORKSHOP CAPACITY</p>
            </div>
            <button className="px-3 py-1 border border-slate-200 rounded text-xs font-bold text-slate-600 hover:bg-slate-50 flex items-center gap-1">
              <span className="material-symbols-outlined text-sm">add</span>
              Add Bay
            </button>
          </div>

          <table className="w-full text-sm text-left mb-4">
            <thead className="text-slate-400 font-bold text-[10px] uppercase tracking-wider border-b border-slate-100">
              <tr>
                <th className="py-2">BAY ID</th>
                <th className="py-2">EQUIPMENT LEVEL</th>
                <th className="py-2">STATUS</th>
                <th className="py-2 text-right">ACTIONS</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {MOCK_BAYS.map((bay) => (
                <tr key={bay.id}>
                  <td className="py-3 font-bold text-slate-900">{bay.id}</td>
                  <td className="py-3">
                    <span className="bg-slate-100 text-slate-600 text-[10px] font-bold px-2 py-1 rounded">
                      {bay.equipment_level}
                    </span>
                  </td>
                  <td className="py-3">
                    <span className={`text-xs font-bold ${bay.status === 'Active' ? 'text-green-600' : 'text-orange-500'}`}>
                      {bay.status}
                    </span>
                  </td>
                  <td className="py-3 text-right">
                    <button className="text-slate-400 hover:text-slate-600 text-xs font-bold uppercase">Edit</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          
          <p className="text-xs text-slate-400 italic">
            Note: Changing bay configuration might affect the master production schedule.
          </p>
        </div>
      </div>

      {/* Notification Preferences */}
      <div className="bg-white rounded-xl shadow-sm p-6">
        <div className="mb-6">
          <h2 className="font-bold text-lg text-slate-800 mb-1">Notification Preferences</h2>
          <p className="text-xs text-slate-400 uppercase tracking-wider">ALERTS & COMMUNICATION</p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          {/* Column 1 */}
          <div>
            <h3 className="font-bold text-sm text-slate-700 border-b border-slate-100 pb-2 mb-4">Internal Staff Alerts</h3>
            <div className="space-y-4">
              <label className="flex items-start gap-3 cursor-pointer">
                <input 
                  type="checkbox" 
                  checked={notifications.newJob}
                  onChange={(e) => setNotifications({...notifications, newJob: e.target.checked})}
                  className="mt-1 rounded text-orange-600 focus:ring-orange-500" 
                />
                <div>
                  <div className="font-bold text-slate-800 text-sm">New Job Assignments</div>
                  <div className="text-xs text-slate-500">Email supervisor when new job is booked.</div>
                </div>
              </label>
              <label className="flex items-start gap-3 cursor-pointer">
                <input 
                  type="checkbox" 
                  checked={notifications.qcFailure}
                  onChange={(e) => setNotifications({...notifications, qcFailure: e.target.checked})}
                  className="mt-1 rounded text-orange-600 focus:ring-orange-500" 
                />
                <div>
                  <div className="font-bold text-slate-800 text-sm">QC Failure Warning</div>
                  <div className="text-xs text-slate-500">Push notification if QC check fails twice.</div>
                </div>
              </label>
            </div>
          </div>

          {/* Column 2 */}
          <div>
            <h3 className="font-bold text-sm text-slate-700 border-b border-slate-100 pb-2 mb-4">Customer Communications</h3>
            <div className="space-y-4">
              <label className="flex items-start gap-3 cursor-pointer">
                <input 
                  type="checkbox" 
                  checked={notifications.completionSms}
                  onChange={(e) => setNotifications({...notifications, completionSms: e.target.checked})}
                  className="mt-1 rounded text-orange-600 focus:ring-orange-500" 
                />
                <div>
                  <div className="font-bold text-slate-800 text-sm">Completion SMS</div>
                  <div className="text-xs text-slate-500">Auto-text when job moves to 'Ready for Pickup'.</div>
                </div>
              </label>
              <label className="flex items-start gap-3 cursor-pointer">
                <input 
                  type="checkbox" 
                  checked={notifications.marketingEmails}
                  onChange={(e) => setNotifications({...notifications, marketingEmails: e.target.checked})}
                  className="mt-1 rounded text-orange-600 focus:ring-orange-500" 
                />
                <div>
                  <div className="font-bold text-slate-800 text-sm">Marketing Emails</div>
                  <div className="text-xs text-slate-500">Newsletter and upfit recommendations.</div>
                </div>
              </label>
            </div>
          </div>

          {/* Column 3 */}
          <div>
            <h3 className="font-bold text-sm text-slate-700 border-b border-slate-100 pb-2 mb-4">System Reports</h3>
            <div className="space-y-4">
              <label className="flex items-start gap-3 cursor-pointer">
                <input 
                  type="checkbox" 
                  checked={notifications.dailySummary}
                  onChange={(e) => setNotifications({...notifications, dailySummary: e.target.checked})}
                  className="mt-1 rounded text-orange-600 focus:ring-orange-500" 
                />
                <div>
                  <div className="font-bold text-slate-800 text-sm">Daily Ops Summary</div>
                  <div className="text-xs text-slate-500">Sent every day at 18:00 local time.</div>
                </div>
              </label>
              <label className="flex items-start gap-3 cursor-pointer">
                <input 
                  type="checkbox" 
                  checked={notifications.weeklyReport}
                  onChange={(e) => setNotifications({...notifications, weeklyReport: e.target.checked})}
                  className="mt-1 rounded text-orange-600 focus:ring-orange-500" 
                />
                <div>
                  <div className="font-bold text-slate-800 text-sm">Weekly Inventory Report</div>
                  <div className="text-xs text-slate-500">Low stock level warnings across all bays.</div>
                </div>
              </label>
            </div>
          </div>
        </div>

        <div className="pt-8 flex justify-end">
          <button className="bg-slate-700 hover:bg-slate-800 text-white font-bold py-2 px-6 rounded shadow-sm text-sm">
            Apply Preferences
          </button>
        </div>
      </div>
    </div>
  );
}
