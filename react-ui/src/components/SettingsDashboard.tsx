import React, { useEffect, useState } from 'react';
import { ProductionBay } from '../types';

const MOCK_BAYS: ProductionBay[] = [
  { id: 'BAY-A1', name: 'Bay A1', equipment_level: 'Full Lift / Electrical', status: 'Active' },
  { id: 'BAY-A2', name: 'Bay A2', equipment_level: 'Full Lift / Electrical', status: 'Active' },
  { id: 'BAY-B1', name: 'Bay B1', equipment_level: 'Storage / Pre-staging', status: 'Maintenance' },
];

const API_ROOT = typeof window !== 'undefined' && window.slateOpsSettings?.api?.root || '/wp-json/slate-ops/v1';
const NONCE   = typeof window !== 'undefined' && window.slateOpsSettings?.api?.nonce || '';

function apiFetch(path: string, opts: RequestInit = {}) {
  return fetch(`${API_ROOT}${path}`, {
    ...opts,
    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE, ...(opts.headers || {}) },
  });
}

export function SettingsDashboard() {
  const [general, setGeneral] = useState({
    companyName: 'Slate Automotive Upfitting Solutions Inc.',
    timezone: 'Mountain Standard Time',
    currency: 'USD ($)',
    holidaySync: true,
  });

  const [shift, setShift] = useState({
    shiftStart: '07:00',
    shiftEnd: '15:30',
    lunchMinutes: 30,
    breakMinutes: 10,
    breakCount: 2,
  });

  const [notifications, setNotifications] = useState({
    newJob: true,
    qcFailure: true,
    completionSms: true,
    marketingEmails: false,
    dailySummary: true,
    weeklyReport: false,
  });

  const [generalSaving, setGeneralSaving]   = useState(false);
  const [generalFlash,  setGeneralFlash]    = useState<'ok' | 'err' | null>(null);
  const [shiftSaving,   setShiftSaving]     = useState(false);
  const [shiftFlash,    setShiftFlash]      = useState<'ok' | 'err' | null>(null);
  const [notifSaving,   setNotifSaving]     = useState(false);
  const [notifFlash,    setNotifFlash]      = useState<'ok' | 'err' | null>(null);

  // Load current settings on mount
  useEffect(() => {
    apiFetch('/settings')
      .then(r => r.json())
      .then(d => {
        if (d.company_name)     setGeneral(g => ({ ...g, companyName: d.company_name }));
        if (d.timezone)         setGeneral(g => ({ ...g, timezone: d.timezone }));
        if (d.currency_display) setGeneral(g => ({ ...g, currency: d.currency_display }));
        if (d.holiday_sync !== undefined) setGeneral(g => ({ ...g, holidaySync: !!d.holiday_sync }));
        if (d.notifications && typeof d.notifications === 'object') {
          setNotifications(n => ({ ...n, ...d.notifications }));
        }
        if (d.shift_start)    setShift(s => ({ ...s, shiftStart: (d.shift_start as string).slice(0,5) }));
        if (d.shift_end)      setShift(s => ({ ...s, shiftEnd: (d.shift_end as string).slice(0,5) }));
        if (d.lunch_minutes)  setShift(s => ({ ...s, lunchMinutes: Number(d.lunch_minutes) }));
        if (d.break_minutes)  setShift(s => ({ ...s, breakMinutes: Number(d.break_minutes) }));
        if (d.break_count)    setShift(s => ({ ...s, breakCount: Number(d.break_count) }));
      })
      .catch(() => {});
  }, []);

  async function saveShift() {
    setShiftSaving(true);
    try {
      const res = await apiFetch('/settings', {
        method: 'POST',
        body: JSON.stringify({
          shift_start:    shift.shiftStart + ':00',
          shift_end:      shift.shiftEnd + ':00',
          lunch_minutes: shift.lunchMinutes,
          break_minutes: shift.breakMinutes,
          break_count:   shift.breakCount,
        }),
      });
      setShiftFlash(res.ok ? 'ok' : 'err');
    } catch {
      setShiftFlash('err');
    } finally {
      setShiftSaving(false);
      setTimeout(() => setShiftFlash(null), 2500);
    }
  }

  async function saveGeneral() {
    setGeneralSaving(true);
    try {
      const res = await apiFetch('/settings', {
        method: 'POST',
        body: JSON.stringify({
          company_name:     general.companyName,
          timezone:         general.timezone,
          currency_display: general.currency,
          holiday_sync:     general.holidaySync,
        }),
      });
      setGeneralFlash(res.ok ? 'ok' : 'err');
    } catch {
      setGeneralFlash('err');
    } finally {
      setGeneralSaving(false);
      setTimeout(() => setGeneralFlash(null), 2500);
    }
  }

  async function saveNotifications() {
    setNotifSaving(true);
    try {
      const res = await apiFetch('/settings', {
        method: 'POST',
        body: JSON.stringify({ notifications }),
      });
      setNotifFlash(res.ok ? 'ok' : 'err');
    } catch {
      setNotifFlash('err');
    } finally {
      setNotifSaving(false);
      setTimeout(() => setNotifFlash(null), 2500);
    }
  }

  return (
    <div className="flex-1 overflow-y-auto bg-[#EAE8DC] p-8 font-sans">
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
                onChange={(e) => setGeneral({ ...general, companyName: e.target.value })}
                className="w-full border border-slate-200 rounded-md px-3 py-2 text-sm focus:ring-primary focus:border-primary"
              />
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-bold text-slate-700 mb-2">Timezone</label>
                <select
                  value={general.timezone}
                  onChange={(e) => setGeneral({ ...general, timezone: e.target.value })}
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
                  onChange={(e) => setGeneral({ ...general, currency: e.target.value })}
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
                  onChange={(e) => setGeneral({ ...general, holidaySync: e.target.checked })}
                  className="sr-only peer"
                />
                <div className="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-orange-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-orange-600"></div>
              </label>
            </div>

            <div className="pt-4 flex justify-end items-center gap-3">
              {generalFlash === 'ok' && <span className="text-green-600 text-xs font-bold flex items-center gap-1"><span className="material-symbols-outlined text-sm">check_circle</span>Saved</span>}
              {generalFlash === 'err' && <span className="text-red-500 text-xs font-bold flex items-center gap-1"><span className="material-symbols-outlined text-sm">error</span>Failed</span>}
              <button
                onClick={saveGeneral}
                disabled={generalSaving}
                className="bg-slate-700 hover:bg-slate-800 text-white font-bold py-2 px-6 rounded shadow-sm text-sm disabled:opacity-50"
              >
                {generalSaving ? 'Saving...' : 'Save General'}
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
            <button className="bg-[#d86b19] hover:bg-[#c05e14] text-white text-xs font-bold px-3 py-1.5 rounded flex items-center gap-1 transition-colors">
              <span className="material-symbols-outlined text-sm">add</span>
              + Add Bay
            </button>
          </div>

          <div className="divide-y divide-slate-100">
            {MOCK_BAYS.map(bay => (
              <div key={bay.id} className="py-3 flex justify-between items-center">
                <div>
                  <div className="font-bold text-slate-900 text-sm">{bay.id}</div>
                  <div className="text-xs text-slate-500">{bay.equipment_level}</div>
                </div>
                <div className="flex items-center gap-2">
                  <span className={`text-[10px] font-bold px-2 py-1 rounded ${bay.status === 'Active' ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700'}`}>
                    {bay.status}
                  </span>
                  <button className="border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-bold px-3 py-1 rounded transition-colors">Edit</button>
                </div>
              </div>
            ))}
          </div>

          <p className="text-xs text-slate-400 mt-4">
            Note: Changing bay configuration might affect the current production schedule.
          </p>
        </div>
      </div>

      {/* Shift & Break Configuration */}
      <div className="bg-white rounded-xl shadow-sm p-6 mb-8">
        <div className="mb-6">
          <h2 className="font-bold text-lg text-slate-800 mb-1">Shift &amp; Break Configuration</h2>
          <p className="text-xs text-slate-400 uppercase tracking-wider">DAILY HOURS &amp; DEDUCTIONS</p>
        </div>

        {/* Row 1: 4 cols */}
        <div className="grid grid-cols-4 gap-4 mb-4">
          <div>
            <label className="block text-sm font-bold text-slate-700 mb-2">Shift Start</label>
            <input
              type="time"
              value={shift.shiftStart}
              onChange={(e) => setShift({ ...shift, shiftStart: e.target.value })}
              className="w-full border border-slate-200 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#d86b19] focus:border-[#d86b19]"
            />
          </div>
          <div>
            <label className="block text-sm font-bold text-slate-700 mb-2">Shift End</label>
            <input
              type="time"
              value={shift.shiftEnd}
              onChange={(e) => setShift({ ...shift, shiftEnd: e.target.value })}
              className="w-full border border-slate-200 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#d86b19] focus:border-[#d86b19]"
            />
          </div>
          <div>
            <label className="block text-sm font-bold text-slate-700 mb-2">Breaks per Day</label>
            <select
              value={shift.breakCount}
              onChange={(e) => setShift({ ...shift, breakCount: Number(e.target.value) })}
              className="w-full border border-slate-200 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#d86b19] focus:border-[#d86b19]"
            >
              {[0,1,2,3,4].map(n => <option key={n} value={n}>{n}</option>)}
            </select>
          </div>
          <div>
            <label className="block text-sm font-bold text-slate-700 mb-2">Break Duration (min each)</label>
            <input
              type="number" min={0} max={60}
              value={shift.breakMinutes}
              onChange={(e) => setShift({ ...shift, breakMinutes: Number(e.target.value) })}
              className="w-full border border-slate-200 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#d86b19] focus:border-[#d86b19]"
            />
          </div>
        </div>

        {/* Row 2: 2 cols */}
        <div className="grid grid-cols-2 gap-4 mb-4">
          <div>
            <label className="block text-sm font-bold text-slate-700 mb-2">Lunch (minutes)</label>
            <input
              type="number" min={0} max={120}
              value={shift.lunchMinutes}
              onChange={(e) => setShift({ ...shift, lunchMinutes: Number(e.target.value) })}
              className="w-full border border-slate-200 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#d86b19] focus:border-[#d86b19]"
            />
          </div>
          <div>
            <label className="block text-sm font-bold text-slate-700 mb-2">OT After (hours/day)</label>
            <input
              type="number" min={1} max={24} step={0.5} defaultValue={8}
              className="w-full border border-slate-200 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#d86b19] focus:border-[#d86b19]"
            />
          </div>
        </div>

        {/* Daily Deduction preview */}
        <div className="bg-[#EAE8DC] rounded-lg p-4 mb-4">
          <div className="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Daily Deduction</div>
          <div className="text-4xl font-bold text-slate-900">
            {shift.lunchMinutes + shift.breakCount * shift.breakMinutes}
            <span className="text-base font-normal text-slate-500 ml-1">min</span>
          </div>
          <div className="text-xs text-slate-500 mt-1">
            Breaks ({shift.breakCount} × {shift.breakMinutes}) + Lunch ({shift.lunchMinutes} min) = {shift.lunchMinutes + shift.breakCount * shift.breakMinutes} minutes/day
          </div>
        </div>

        <div className="flex justify-end items-center gap-3">
          {shiftFlash === 'ok' && <span className="text-green-600 text-xs font-bold flex items-center gap-1"><span className="material-symbols-outlined text-sm">check_circle</span>Saved</span>}
          {shiftFlash === 'err' && <span className="text-red-500 text-xs font-bold flex items-center gap-1"><span className="material-symbols-outlined text-sm">error</span>Failed</span>}
          <button
            onClick={saveShift}
            disabled={shiftSaving}
            className="bg-slate-800 hover:bg-slate-900 text-white font-bold py-2 px-6 rounded shadow-sm text-sm disabled:opacity-50"
          >
            {shiftSaving ? 'Saving...' : 'Update Shift Settings'}
          </button>
        </div>
      </div>

      {/* Notification Preferences */}
      <div className="bg-white rounded-xl shadow-sm p-6">
        <div className="mb-6">
          <h2 className="font-bold text-lg text-slate-800 mb-1">Notification Preferences</h2>
          <p className="text-xs text-slate-400 uppercase tracking-wider">ALERTS &amp; COMMUNICATION</p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div>
            <h3 className="font-bold text-sm text-slate-700 border-b border-slate-100 pb-2 mb-4">Internal Staff Alerts</h3>
            <div className="space-y-4">
              <label className="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" checked={notifications.newJob} onChange={(e) => setNotifications({ ...notifications, newJob: e.target.checked })} className="mt-1 rounded text-orange-600 focus:ring-orange-500" />
                <div>
                  <div className="font-bold text-slate-800 text-sm">New Job Assignments</div>
                  <div className="text-xs text-slate-500">Email supervisor when new job is booked.</div>
                </div>
              </label>
              <label className="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" checked={notifications.qcFailure} onChange={(e) => setNotifications({ ...notifications, qcFailure: e.target.checked })} className="mt-1 rounded text-orange-600 focus:ring-orange-500" />
                <div>
                  <div className="font-bold text-slate-800 text-sm">QC Failure Warning</div>
                  <div className="text-xs text-slate-500">Push notification if QC check fails twice.</div>
                </div>
              </label>
            </div>
          </div>

          <div>
            <h3 className="font-bold text-sm text-slate-700 border-b border-slate-100 pb-2 mb-4">Customer Communications</h3>
            <div className="space-y-4">
              <label className="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" checked={notifications.completionSms} onChange={(e) => setNotifications({ ...notifications, completionSms: e.target.checked })} className="mt-1 rounded text-orange-600 focus:ring-orange-500" />
                <div>
                  <div className="font-bold text-slate-800 text-sm">Completion SMS</div>
                  <div className="text-xs text-slate-500">Auto-text when job moves to 'Ready for Pickup'.</div>
                </div>
              </label>
              <label className="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" checked={notifications.marketingEmails} onChange={(e) => setNotifications({ ...notifications, marketingEmails: e.target.checked })} className="mt-1 rounded text-orange-600 focus:ring-orange-500" />
                <div>
                  <div className="font-bold text-slate-800 text-sm">Marketing Emails</div>
                  <div className="text-xs text-slate-500">Newsletter and upfit recommendations.</div>
                </div>
              </label>
            </div>
          </div>

          <div>
            <h3 className="font-bold text-sm text-slate-700 border-b border-slate-100 pb-2 mb-4">System Reports</h3>
            <div className="space-y-4">
              <label className="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" checked={notifications.dailySummary} onChange={(e) => setNotifications({ ...notifications, dailySummary: e.target.checked })} className="mt-1 rounded text-orange-600 focus:ring-orange-500" />
                <div>
                  <div className="font-bold text-slate-800 text-sm">Daily Ops Summary</div>
                  <div className="text-xs text-slate-500">Sent every day at 18:00 local time.</div>
                </div>
              </label>
              <label className="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" checked={notifications.weeklyReport} onChange={(e) => setNotifications({ ...notifications, weeklyReport: e.target.checked })} className="mt-1 rounded text-orange-600 focus:ring-orange-500" />
                <div>
                  <div className="font-bold text-slate-800 text-sm">Weekly Inventory Report</div>
                  <div className="text-xs text-slate-500">Low stock level warnings across all bays.</div>
                </div>
              </label>
            </div>
          </div>
        </div>

        <div className="pt-8 flex justify-end items-center gap-3">
          {notifFlash === 'ok' && <span className="text-green-600 text-xs font-bold flex items-center gap-1"><span className="material-symbols-outlined text-sm">check_circle</span>Saved</span>}
          {notifFlash === 'err' && <span className="text-red-500 text-xs font-bold flex items-center gap-1"><span className="material-symbols-outlined text-sm">error</span>Failed</span>}
          <button
            onClick={saveNotifications}
            disabled={notifSaving}
            className="bg-slate-700 hover:bg-slate-800 text-white font-bold py-2 px-6 rounded shadow-sm text-sm disabled:opacity-50"
          >
            {notifSaving ? 'Saving...' : 'Apply Preferences'}
          </button>
        </div>
      </div>
    </div>
  );
}
