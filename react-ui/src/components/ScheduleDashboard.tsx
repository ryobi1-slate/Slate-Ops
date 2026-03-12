import React, { useEffect, useState, useCallback } from 'react';
import { Job, WorkCenter, CapacitySummary } from '../types';
import { workCenterService, schedulerService } from '../services/api';

interface ScheduleDashboardProps {
  jobs: Job[];
}

// ── Helpers ────────────────────────────────────────────────────────

function getWeekDates(anchor: Date): { iso: string; label: string; shortDay: string }[] {
  // Returns Mon–Fri for the week containing anchor.
  const d = new Date(anchor);
  const day = d.getDay(); // 0=Sun
  const monday = new Date(d);
  monday.setDate(d.getDate() - (day === 0 ? 6 : day - 1));
  return Array.from({ length: 5 }, (_, i) => {
    const dd = new Date(monday);
    dd.setDate(monday.getDate() + i);
    return {
      iso: dd.toISOString().slice(0, 10),
      label: dd.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }),
      shortDay: dd.toLocaleDateString('en-US', { weekday: 'short' }),
    };
  });
}


function todayIso(): string {
  return new Date().toISOString().slice(0, 10);
}

function flagColor(flag?: string | null): string {
  switch (flag) {
    case 'LATE':       return 'bg-red-600';
    case 'AT_RISK':    return 'bg-orange-500';
    case 'OVERLOADED': return 'bg-yellow-500';
    default:           return 'bg-[#5A6B65]';
  }
}

function flagBadge(flag?: string | null): React.ReactNode {
  if (!flag || flag === 'ON_TIME') return null;
  const colors: Record<string, string> = {
    LATE:       'bg-red-100 text-red-700',
    AT_RISK:    'bg-orange-100 text-orange-700',
    OVERLOADED: 'bg-yellow-100 text-yellow-800',
  };
  return (
    <span className={`text-[9px] font-bold px-1 rounded uppercase ${colors[flag] || ''}`}>
      {flag.replace('_', ' ')}
    </span>
  );
}

function minutesToHours(min: number): string {
  return (min / 60).toFixed(1) + 'h';
}

// ── Component ──────────────────────────────────────────────────────

export function ScheduleDashboard({ jobs: propJobs }: ScheduleDashboardProps) {
  const [weekAnchor, setWeekAnchor] = useState<Date>(new Date());
  const weekDays = getWeekDates(weekAnchor);
  const weekFrom = weekDays[0].iso;
  const weekTo   = weekDays[4].iso;

  const [workCenters, setWorkCenters]     = useState<WorkCenter[]>([]);
  const [capacity, setCapacity]           = useState<CapacitySummary[]>([]);
  const [scheduledJobs, setScheduledJobs] = useState<Job[]>([]);
  const [loading, setLoading]             = useState(true);
  const [filterWc, setFilterWc]           = useState<string>('all');
  const [selectedJobId, setSelectedJobId] = useState<number | null>(null);
  const [error, setError]                 = useState<string | null>(null);

  const loadData = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [wcs, cap, scheduled] = await Promise.all([
        workCenterService.getAll(true),
        schedulerService.getCapacity(weekFrom, weekTo).then(r => r.summary),
        schedulerService.getScheduledJobs(weekFrom, weekTo),
      ]);
      setWorkCenters(wcs);
      setCapacity(cap);
      setScheduledJobs(scheduled);
    } catch (e: any) {
      setError(e?.message || 'Failed to load scheduler data');
    } finally {
      setLoading(false);
    }
  }, [weekFrom, weekTo]);

  useEffect(() => { loadData(); }, [loadData]);

  // ── Derived stats ──────────────────────────────────────────────

  const today = todayIso();

  // Jobs due this week (use propJobs which includes unscheduled)
  const allJobs = propJobs.length ? propJobs : scheduledJobs;
  const dueThisWeek = allJobs.filter(j => {
    const due = j.promised_date || j.target_ship_date || j.due_date;
    return due && due >= weekFrom && due <= weekTo;
  }).length;

  const lateJobs = allJobs.filter(j => {
    const due = j.promised_date || j.target_ship_date || j.due_date;
    return due && due < today && j.status !== 'COMPLETE' && j.status !== 'COMPLETED';
  }).length;

  const onHoldJobs = allJobs.filter(j => j.status === 'ON_HOLD').length;

  const overloadedWcs = capacity.filter(c => c.is_overloaded);

  // Constraint utilization
  const constraint = capacity.find(c => c.is_constraint);

  // ── Calendar grid ──────────────────────────────────────────────

  const displayWcs = workCenters.filter(wc =>
    filterWc === 'all' || wc.wc_code === filterWc
  );

  // Map jobs into calendar cells
  // A job "occupies" a work center row for each day its scheduled_start..scheduled_finish spans.
  function getJobsForWcDay(wcCode: string, dayIso: string): Job[] {
    return scheduledJobs.filter(j =>
      j.work_center === wcCode &&
      j.scheduled_start != null &&
      j.scheduled_start.slice(0, 10) <= dayIso &&
      (j.scheduled_finish ? j.scheduled_finish.slice(0, 10) : j.scheduled_start!.slice(0, 10)) >= dayIso
    );
  }

  // For a job that starts on a given day, how many days does it span (capped at week end)?
  function getJobSpan(job: Job, startDay: string): number {
    const finish = job.scheduled_finish?.slice(0, 10) || startDay;
    let span = 0;
    for (const d of weekDays) {
      if (d.iso >= startDay && d.iso <= finish) span++;
    }
    return Math.max(1, span);
  }

  const selectedJob = selectedJobId
    ? scheduledJobs.find(j => (j.job_id || j.id) === selectedJobId) || null
    : null;

  // ── Recalculate handler ──────────────────────────────────────────

  const handleRecalc = async () => {
    try {
      await schedulerService.recalculateFlags(weekFrom, weekTo);
      await loadData();
    } catch (e: any) {
      setError(e?.message || 'Recalculate failed');
    }
  };

  // ── Render ────────────────────────────────────────────────────────

  return (
    <div className="flex-1 overflow-y-auto bg-[#EAE8DC] p-6 font-sans">
      {/* Header */}
      <div className="flex justify-between items-center mb-6">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Production Schedule</h1>
          {workCenters.length === 0 && !loading && (
            <p className="text-sm text-orange-600 mt-1">
              No work centers configured. Add work centers in Settings to enable capacity tracking.
            </p>
          )}
        </div>
        <div className="flex items-center gap-3">
          <button
            onClick={handleRecalc}
            className="flex items-center gap-1.5 bg-white border border-slate-200 rounded-md px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
          >
            <span className="material-symbols-outlined text-base">refresh</span>
            Recalculate
          </button>
          {/* Week navigation */}
          <button
            onClick={() => { const d = new Date(weekAnchor); d.setDate(d.getDate() - 7); setWeekAnchor(d); }}
            className="p-1.5 rounded hover:bg-slate-200 text-slate-600"
          >
            <span className="material-symbols-outlined text-lg">chevron_left</span>
          </button>
          <span className="text-sm font-medium text-slate-700 whitespace-nowrap">
            {weekDays[0].label} – {weekDays[4].label}
          </span>
          <button
            onClick={() => { const d = new Date(weekAnchor); d.setDate(d.getDate() + 7); setWeekAnchor(d); }}
            className="p-1.5 rounded hover:bg-slate-200 text-slate-600"
          >
            <span className="material-symbols-outlined text-lg">chevron_right</span>
          </button>
          <button
            onClick={() => setWeekAnchor(new Date())}
            className="text-xs px-2 py-1 bg-white border border-slate-200 rounded text-slate-600 hover:bg-slate-50"
          >
            Today
          </button>
        </div>
      </div>

      {error && (
        <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded text-sm text-red-700">{error}</div>
      )}

      {/* Stats row */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        {/* Constraint utilization */}
        <div className="bg-white p-4 rounded-xl shadow-sm">
          <div className="text-xs font-bold text-slate-500 uppercase mb-1">
            {constraint ? constraint.display_name : 'Constraint'} Utilization
          </div>
          {constraint ? (
            <>
              <div className={`text-3xl font-bold mb-2 ${constraint.is_overloaded ? 'text-red-600' : 'text-slate-900'}`}>
                {constraint.utilization_pct}%
              </div>
              <div className="w-full bg-slate-100 rounded-full h-2">
                <div
                  className={`h-2 rounded-full ${constraint.utilization_pct > 100 ? 'bg-red-500' : constraint.utilization_pct > 80 ? 'bg-orange-400' : 'bg-[#5A6B65]'}`}
                  style={{ width: `${Math.min(constraint.utilization_pct, 100)}%` }}
                />
              </div>
              <div className="text-xs text-slate-400 mt-1">
                {minutesToHours(constraint.allocated_minutes)} / {minutesToHours(constraint.capacity_minutes)}
              </div>
            </>
          ) : (
            <div className="text-2xl font-bold text-slate-400">—</div>
          )}
        </div>

        {/* Due this week */}
        <div className="bg-white p-4 rounded-xl shadow-sm">
          <div className="text-xs font-bold text-slate-500 uppercase mb-1">Due This Week</div>
          <div className="text-3xl font-bold text-slate-900">{dueThisWeek}</div>
          <div className="text-xs text-slate-400 mt-1">jobs</div>
        </div>

        {/* Late jobs */}
        <div className="bg-white p-4 rounded-xl shadow-sm">
          <div className="text-xs font-bold text-slate-500 uppercase mb-1">Late Jobs</div>
          <div className={`text-3xl font-bold ${lateJobs > 0 ? 'text-red-600' : 'text-slate-900'}`}>{lateJobs}</div>
          <div className="text-xs text-slate-400 mt-1">past promised date</div>
        </div>

        {/* Overloads */}
        <div className="bg-white p-4 rounded-xl shadow-sm">
          <div className="text-xs font-bold text-slate-500 uppercase mb-1">Overloaded Centers</div>
          <div className={`text-3xl font-bold ${overloadedWcs.length > 0 ? 'text-orange-600' : 'text-slate-900'}`}>
            {overloadedWcs.length}
          </div>
          {overloadedWcs.length > 0 && (
            <div className="text-xs text-orange-600 mt-1">
              {overloadedWcs.map(w => w.display_name).join(', ')}
            </div>
          )}
          {overloadedWcs.length === 0 && (
            <div className="text-xs text-slate-400 mt-1">all clear</div>
          )}
        </div>
      </div>

      {/* Capacity bars row */}
      {capacity.length > 0 && (
        <div className="bg-white rounded-xl shadow-sm p-4 mb-6">
          <div className="text-xs font-bold text-slate-500 uppercase mb-3">Weekly Capacity by Work Center</div>
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
            {capacity.map(wc => (
              <div key={wc.wc_id}>
                <div className="flex justify-between items-center mb-1">
                  <span className="text-xs font-medium text-slate-700">{wc.display_name}</span>
                  <span className={`text-xs font-bold ${wc.is_overloaded ? 'text-red-600' : 'text-slate-500'}`}>
                    {wc.utilization_pct}%
                  </span>
                </div>
                <div className="w-full bg-slate-100 rounded-full h-1.5">
                  <div
                    className={`h-1.5 rounded-full ${wc.is_overloaded ? 'bg-red-500' : wc.utilization_pct > 80 ? 'bg-orange-400' : 'bg-[#5A6B65]'}`}
                    style={{ width: `${Math.min(wc.utilization_pct, 100)}%` }}
                  />
                </div>
                {wc.is_overloaded && (
                  <div className="text-[10px] text-red-600 mt-0.5">
                    +{minutesToHours(wc.overload_minutes)} over
                  </div>
                )}
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Main layout: calendar + detail panel */}
      <div className="flex gap-4">
        {/* Calendar */}
        <div className="flex-1 bg-white rounded-xl shadow-sm overflow-hidden">
          {/* Filter bar */}
          <div className="flex items-center gap-3 px-4 py-3 border-b border-slate-100">
            <span className="text-xs font-bold text-slate-500 uppercase">Work Center</span>
            <select
              value={filterWc}
              onChange={e => setFilterWc(e.target.value)}
              className="text-sm border border-slate-200 rounded px-2 py-1 text-slate-700"
            >
              <option value="all">All</option>
              {workCenters.map(wc => (
                <option key={wc.wc_id} value={wc.wc_code}>{wc.display_name}</option>
              ))}
            </select>
          </div>

          {loading ? (
            <div className="flex items-center justify-center h-48 text-slate-400">
              <span className="material-symbols-outlined animate-spin mr-2">refresh</span>
              Loading schedule…
            </div>
          ) : workCenters.length === 0 ? (
            <div className="flex flex-col items-center justify-center h-48 text-slate-400 gap-2">
              <span className="material-symbols-outlined text-3xl">dashboard</span>
              <p className="text-sm">No work centers yet.</p>
              <p className="text-xs">Add work centers in Settings to start scheduling.</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm border-collapse">
                <thead>
                  <tr className="bg-slate-50">
                    <th className="w-32 px-3 py-2 text-left text-xs font-bold text-slate-600 border-r border-slate-200 sticky left-0 bg-slate-50 z-10">
                      Work Center
                    </th>
                    {weekDays.map(d => (
                      <th
                        key={d.iso}
                        className={`px-2 py-2 text-center border-r border-slate-200 last:border-r-0 ${d.iso === today ? 'bg-blue-50' : ''}`}
                      >
                        <div className="text-xs font-bold text-slate-500">{d.shortDay}</div>
                        <div className={`text-sm font-bold ${d.iso === today ? 'text-blue-700' : 'text-slate-800'}`}>
                          {d.label.split(' ')[1]}
                        </div>
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {displayWcs.map(wc => {
                    const wcCap = capacity.find(c => c.wc_code === wc.wc_code);
                    return (
                      <tr key={wc.wc_id} className="border-t border-slate-100">
                        {/* Work center label */}
                        <td className="px-3 py-2 border-r border-slate-200 sticky left-0 bg-white z-10">
                          <div className="flex items-center gap-1.5">
                            <span className="w-2 h-2 rounded-full flex-shrink-0" style={{ backgroundColor: wc.color }} />
                            <span className="text-xs font-bold text-slate-800 leading-tight">{wc.display_name}</span>
                            {wc.is_constraint ? (
                              <span className="text-[9px] bg-slate-800 text-white px-1 rounded">★</span>
                            ) : null}
                          </div>
                          {wcCap && (
                            <div className={`text-[10px] mt-0.5 ${wcCap.is_overloaded ? 'text-red-600 font-bold' : 'text-slate-400'}`}>
                              {wcCap.is_overloaded
                                ? `OVERLOADED +${minutesToHours(wcCap.overload_minutes)}`
                                : `${minutesToHours(wcCap.allocated_minutes)} / ${minutesToHours(wcCap.capacity_minutes)}`}
                            </div>
                          )}
                        </td>

                        {/* Day cells */}
                        {weekDays.map(day => {
                          const dayJobs = getJobsForWcDay(wc.wc_code, day.iso);
                          const isToday = day.iso === today;
                          return (
                            <td
                              key={day.iso}
                              className={`px-1 py-1 border-r border-slate-100 last:border-r-0 align-top min-w-[110px] ${isToday ? 'bg-blue-50/40' : ''}`}
                            >
                              <div className="flex flex-col gap-1 min-h-[64px]">
                                {dayJobs
                                  .filter(j => (j.scheduled_start?.slice(0, 10) === day.iso))
                                  .map(j => {
                                    const jid = j.job_id || j.id;
                                    const isSelected = jid === selectedJobId;
                                    const isLocked = !!j.scheduler_locked;
                                    const barColor = flagColor(j.scheduling_flag);
                                    return (
                                      <div
                                        key={jid}
                                        onClick={() => setSelectedJobId(isSelected ? null : jid)}
                                        className={`rounded p-1.5 text-white text-xs cursor-pointer hover:opacity-90 transition-all
                                          ${barColor}
                                          ${isSelected ? 'ring-2 ring-offset-1 ring-blue-400' : ''}
                                          ${isLocked ? 'border-l-2 border-white/50' : ''}`}
                                      >
                                        <div className="font-bold truncate">
                                          {j.so_number || `#${jid}`}
                                        </div>
                                        <div className="opacity-90 truncate text-[10px]">
                                          {j.customer_name || j.dealer_name}
                                        </div>
                                        <div className="flex items-center gap-1 mt-0.5">
                                          {j.estimated_minutes && (
                                            <span className="opacity-70 text-[10px]">
                                              {minutesToHours(j.estimated_minutes)}
                                            </span>
                                          )}
                                          {flagBadge(j.scheduling_flag)}
                                          {isLocked && (
                                            <span className="material-symbols-outlined text-[10px] opacity-80">lock</span>
                                          )}
                                        </div>
                                      </div>
                                    );
                                  })}
                              </div>
                            </td>
                          );
                        })}
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>

        {/* Detail panel */}
        {selectedJob && (
          <div className="w-72 bg-white rounded-xl shadow-sm p-4 flex-shrink-0 self-start sticky top-0">
            <div className="flex items-center justify-between mb-3">
              <span className="text-sm font-bold text-slate-800">
                {selectedJob.so_number || `Job #${selectedJob.job_id || selectedJob.id}`}
              </span>
              <button
                onClick={() => setSelectedJobId(null)}
                className="text-slate-400 hover:text-slate-600"
              >
                <span className="material-symbols-outlined text-base">close</span>
              </button>
            </div>

            <div className="space-y-2 text-xs">
              <div className="flex justify-between">
                <span className="text-slate-500">Customer</span>
                <span className="font-medium text-slate-800">{selectedJob.customer_name || '—'}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Work Center</span>
                <span className="font-medium text-slate-800">{selectedJob.work_center || '—'}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Scheduled</span>
                <span className="font-medium text-slate-800">
                  {selectedJob.scheduled_start?.slice(0, 10) || '—'}
                  {selectedJob.scheduled_finish && ` → ${selectedJob.scheduled_finish.slice(0, 10)}`}
                </span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Est. Hours</span>
                <span className="font-medium text-slate-800">
                  {selectedJob.estimated_minutes ? minutesToHours(selectedJob.estimated_minutes) : '—'}
                </span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Promised</span>
                <span className="font-medium text-slate-800">{selectedJob.promised_date || selectedJob.target_ship_date || '—'}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Status</span>
                <span className="font-medium text-slate-800">{selectedJob.status}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Flag</span>
                <span>{flagBadge(selectedJob.scheduling_flag) || <span className="text-slate-400">ON_TIME</span>}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Priority</span>
                <span className="font-medium text-slate-800">
                  {selectedJob.priority || '—'}
                  {selectedJob.priority_score != null && ` (score: ${selectedJob.priority_score})`}
                </span>
              </div>
              {selectedJob.scheduler_locked ? (
                <div className="flex items-center gap-1 text-slate-600">
                  <span className="material-symbols-outlined text-sm">lock</span>
                  <span>Locked</span>
                </div>
              ) : null}
              {selectedJob.delay_reason && (
                <div className="flex items-center gap-1 text-orange-600">
                  <span className="material-symbols-outlined text-sm">pause_circle</span>
                  <span>Hold: {selectedJob.delay_reason}</span>
                </div>
              )}
              {selectedJob.schedule_notes && (
                <div className="pt-2 border-t border-slate-100 text-slate-600">
                  {selectedJob.schedule_notes}
                </div>
              )}
            </div>

            {/* Actions */}
            <div className="mt-4 flex flex-col gap-2">
              <button
                onClick={async () => {
                  if (selectedJob.scheduler_locked) {
                    await schedulerService.unlockJob(selectedJob.job_id || selectedJob.id);
                  } else {
                    await schedulerService.lockJob(selectedJob.job_id || selectedJob.id);
                  }
                  await loadData();
                }}
                className="w-full text-xs font-medium py-1.5 px-3 border border-slate-200 rounded hover:bg-slate-50 flex items-center gap-1.5"
              >
                <span className="material-symbols-outlined text-sm">
                  {selectedJob.scheduler_locked ? 'lock_open' : 'lock'}
                </span>
                {selectedJob.scheduler_locked ? 'Unlock Job' : 'Lock Job'}
              </button>
              {selectedJob.status === 'ON_HOLD' ? (
                <button
                  onClick={async () => {
                    await schedulerService.unholdJob(selectedJob.job_id || selectedJob.id);
                    setSelectedJobId(null);
                    await loadData();
                  }}
                  className="w-full text-xs font-medium py-1.5 px-3 bg-green-50 border border-green-200 text-green-700 rounded hover:bg-green-100 flex items-center gap-1.5"
                >
                  <span className="material-symbols-outlined text-sm">play_circle</span>
                  Clear Hold
                </button>
              ) : (
                <button
                  onClick={async () => {
                    const reason = prompt('Hold reason (e.g. parts, waiting_info, customer_hold):');
                    if (!reason) return;
                    await schedulerService.holdJob(selectedJob.job_id || selectedJob.id, reason);
                    setSelectedJobId(null);
                    await loadData();
                  }}
                  className="w-full text-xs font-medium py-1.5 px-3 bg-amber-50 border border-amber-200 text-amber-800 rounded hover:bg-amber-100 flex items-center gap-1.5 transition-colors"
                >
                  <span className="material-symbols-outlined text-sm">pause_circle</span>
                  Place on Hold
                </button>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
