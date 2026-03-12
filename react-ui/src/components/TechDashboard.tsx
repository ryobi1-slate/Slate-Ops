import React, { useState, useEffect, useCallback, useRef } from 'react';
import { Job } from '../types';
import { timeService, ActiveSegment } from '../services/api';

// ── Helpers ────────────────────────────────────────────────────────

function pad(n: number) {
  return String(n).padStart(2, '0');
}

function elapsedSeconds(startTsGmt: string): number {
  const adjusted = startTsGmt.endsWith('Z') ? startTsGmt : startTsGmt.replace(' ', 'T') + 'Z';
  return Math.max(0, Math.floor((Date.now() - new Date(adjusted).getTime()) / 1000));
}

function formatElapsed(seconds: number) {
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  const s = seconds % 60;
  return { h: pad(h), m: pad(m), s: pad(s) };
}

function getStatusBadge(job: Job): { label: string; color: string } {
  if (job.delay_reason || job.status === 'ON_HOLD') {
    return { label: 'BLOCKED', color: 'bg-slate-100 text-slate-500 border border-slate-300' };
  }
  if (job.parts_status === 'NOT_READY' || job.parts_status === 'PARTIAL') {
    return { label: 'PARTS PENDING', color: 'bg-orange-100 text-orange-700 border border-orange-300' };
  }
  return { label: 'READY', color: 'bg-emerald-100 text-emerald-700 border border-emerald-300' };
}

function canStart(job: Job): boolean {
  return job.status !== 'ON_HOLD' && job.status !== 'COMPLETE' && job.status !== 'COMPLETED';
}

const API = () =>
  (typeof window !== 'undefined' ? window.slateOpsSettings?.api?.root || '/wp-json/slate-ops/v1' : '/wp-json/slate-ops/v1')
    .replace(/\/$/, '');
const NONCE = () =>
  typeof window !== 'undefined' ? window.slateOpsSettings?.api?.nonce || '' : '';
const USER_NAME = () =>
  typeof window !== 'undefined' ? window.slateOpsSettings?.user?.name || 'Tech' : 'Tech';

type Tab = 'myjobs' | 'alerts' | 'inspection' | 'settings';

// ── Component ──────────────────────────────────────────────────────

interface DailySummary {
  raw_minutes: number;
  deduction_minutes: number;
  net_minutes: number;
  overtime_minutes: number;
  is_overtime: boolean;
  ot_threshold_minutes: number;
  lunch_minutes: number;
  break_minutes: number;
  break_count: number;
  deduction_applied: boolean;
}

export function TechDashboard() {
  const [active, setActive]               = useState<ActiveSegment | null>(null);
  const [elapsed, setElapsed]             = useState(0);
  const [myJobs, setMyJobs]               = useState<Job[]>([]);
  const [allJobs, setAllJobs]             = useState<Job[]>([]);
  const [tab, setTab]                     = useState<Tab>('myjobs');
  const [showHelp, setShowHelp]           = useState(false);
  const [loading, setLoading]             = useState(true);
  const [busy, setBusy]                   = useState(false);
  const [error, setError]                 = useState<string | null>(null);
  const [dailySummary, setDailySummary]   = useState<DailySummary | null>(null);
  const timerRef                          = useRef<ReturnType<typeof setInterval> | null>(null);

  // ── Fetch helpers ─────────────────────────────────────────────────

  const apiFetch = useCallback(async (path: string) => {
    const r = await fetch(`${API()}${path}`, {
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE() },
    });
    return r.json();
  }, []);

  const loadActive = useCallback(async () => {
    try {
      const seg = await timeService.getActive();
      setActive(seg);
      if (seg) setElapsed(elapsedSeconds(seg.start_ts));
    } catch { /* polling — ignore transient errors */ }
  }, []);

  const loadMyJobs = useCallback(async () => {
    try {
      const data = await apiFetch('/jobs?assigned_me=1&limit=50&status_in=SCHEDULED,IN_PROGRESS,READY_FOR_SCHEDULING,UNSCHEDULED,ON_HOLD');
      setMyJobs(data.jobs || []);
    } catch { setMyJobs([]); }
  }, [apiFetch]);

  const loadAllJobs = useCallback(async () => {
    try {
      // IN_PROGRESS included so techs can help on jobs already being worked
      const data = await apiFetch('/jobs?status_in=IN_PROGRESS,SCHEDULED,READY_FOR_SCHEDULING&limit=100');
      setAllJobs(data.jobs || []);
    } catch { setAllJobs([]); }
  }, [apiFetch]);

  const loadDailySummary = useCallback(async () => {
    try {
      const data = await apiFetch('/time/daily-summary');
      setDailySummary(data);
    } catch { /* non-critical */ }
  }, [apiFetch]);

  // ── Init ──────────────────────────────────────────────────────────

  useEffect(() => {
    (async () => {
      setLoading(true);
      await Promise.all([loadActive(), loadMyJobs(), loadDailySummary()]);
      setLoading(false);
    })();
  }, [loadActive, loadMyJobs, loadDailySummary]);

  useEffect(() => {
    if (showHelp && allJobs.length === 0) loadAllJobs();
  }, [showHelp, allJobs.length, loadAllJobs]);

  // ── Live ticker ───────────────────────────────────────────────────

  useEffect(() => {
    if (timerRef.current) clearInterval(timerRef.current);
    if (active?.start_ts) {
      timerRef.current = setInterval(() => setElapsed(elapsedSeconds(active.start_ts)), 1000);
    }
    return () => { if (timerRef.current) clearInterval(timerRef.current); };
  }, [active?.start_ts]);

  // ── Actions ───────────────────────────────────────────────────────

  const startTimer = async (job: Job, reason = 'assigned') => {
    if (busy) return;
    setError(null);
    setBusy(true);
    try {
      await timeService.start(job.job_id || job.id, reason);
      await loadActive();
      await loadMyJobs();
      setShowHelp(false);
    } catch (e: any) {
      setError(e?.message || 'Failed to start timer');
    } finally { setBusy(false); }
  };

  const pauseTimer = async () => {
    if (!active || busy) return;
    setError(null);
    setBusy(true);
    try {
      await timeService.stop();
      setActive(null);
      setElapsed(0);
      await Promise.all([loadMyJobs(), loadDailySummary()]);
    } catch (e: any) {
      setError(e?.message || 'Failed to pause');
    } finally { setBusy(false); }
  };

  const completeJob = async () => {
    if (!active || busy) return;
    if (!window.confirm('Mark job complete and send to QC?')) return;
    setError(null);
    setBusy(true);
    try {
      await timeService.stop();
      await fetch(`${API()}/jobs/${active.job_id}/status`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE() },
        body: JSON.stringify({ status: 'PENDING_QC' }),
      });
      setActive(null);
      setElapsed(0);
      await loadMyJobs();
    } catch (e: any) {
      setError(e?.message || 'Failed to complete job');
    } finally { setBusy(false); }
  };

  // ── Derived state ─────────────────────────────────────────────────

  const { h, m, s } = formatElapsed(elapsed);
  const activeJobId  = active?.job_id;
  const queueJobs    = myJobs.filter(j => (j.job_id || j.id) !== activeJobId);
  const userName     = USER_NAME();

  // ── Render ────────────────────────────────────────────────────────

  return (
    <div className="flex-1 flex flex-col bg-[#EAE8DC] overflow-hidden">
      {/* Max-width mobile shell */}
      <div className="flex-1 flex flex-col max-w-[480px] mx-auto w-full shadow-2xl">

        {/* ── Top bar ──────────────────────────────────────── */}
        <div className="bg-[#1f2d29] text-white px-5 py-3.5 flex items-center justify-between flex-shrink-0">
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 rounded-lg bg-[#d86b19] flex items-center justify-center font-bold text-base">
              S
            </div>
            <div>
              <div className="text-[10px] text-white/50 uppercase tracking-widest leading-none">Slate Upfit</div>
              <div className="font-bold text-base leading-tight">{userName}</div>
            </div>
          </div>
          <div className="flex items-center gap-3">
            <button className="relative text-white/70 hover:text-white">
              <span className="material-symbols-outlined text-2xl">notifications</span>
              <span className="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 bg-[#d86b19] rounded-full border-2 border-[#1f2d29]" />
            </button>
            <div className="w-9 h-9 rounded-full bg-white/20 flex items-center justify-center font-bold">
              {userName.charAt(0).toUpperCase()}
            </div>
          </div>
        </div>

        {/* ── Error ─────────────────────────────────────────── */}
        {error && (
          <div className="bg-red-50 border-b border-red-200 px-4 py-2.5 flex items-center justify-between text-sm text-red-700">
            <span>{error}</span>
            <button onClick={() => setError(null)}>
              <span className="material-symbols-outlined text-sm">close</span>
            </button>
          </div>
        )}

        {/* ── Scrollable body ────────────────────────────────── */}
        <div className="flex-1 overflow-y-auto bg-[#e8e5d6] px-4 py-5 space-y-6">
          {loading ? (
            <div className="flex flex-col items-center justify-center h-60 text-slate-400 gap-3">
              <span className="material-symbols-outlined text-4xl animate-spin">refresh</span>
              <span className="text-base">Loading your jobs…</span>
            </div>
          ) : (
            <>
              {/* ── ACTIVE LABOR ─────────────────────────────── */}
              <section>
                <div className="text-xs font-bold uppercase tracking-widest text-slate-500 mb-3">Active Labor</div>

                {active ? (
                  <div className="bg-white rounded-3xl shadow-md overflow-hidden">
                    {/* Job info */}
                    <div className="px-5 pt-5 pb-4 border-b border-slate-100">
                      <div className="flex items-start justify-between">
                        <div>
                          <div className="text-xs font-bold text-slate-400 mb-0.5 tracking-wide">
                            SO# {active.so_number || active.job_id}
                          </div>
                          <div className="text-xl font-bold text-slate-900 leading-tight">
                            {active.customer_name || 'Job in progress'}
                          </div>
                          {active.work_center && (
                            <div className="text-xs font-bold uppercase tracking-widest text-slate-400 mt-1">
                              Phase: {active.work_center}
                            </div>
                          )}
                        </div>
                        <span className="text-[10px] font-bold bg-emerald-100 text-emerald-700 px-2.5 py-1 rounded-full uppercase tracking-wide border border-emerald-200">
                          ● ACTIVE
                        </span>
                      </div>

                      {/* Big timer */}
                      <div className="flex gap-3 mt-4">
                        {[{ val: h, label: 'HRS' }, { val: m, label: 'MIN' }, { val: s, label: 'SEC' }].map(({ val, label }) => (
                          <div key={label} className="flex-1 bg-[#1f2d29] rounded-2xl py-4 text-center">
                            <div className="text-4xl font-bold text-white font-mono tracking-tighter leading-none">{val}</div>
                            <div className="text-[10px] font-bold text-white/40 tracking-widest mt-1.5">{label}</div>
                          </div>
                        ))}
                      </div>
                    </div>

                    {/* Traffic-light action buttons */}
                    <div className="p-4 flex gap-3">
                      {/* RED — Stop/Pause */}
                      <button
                        onClick={pauseTimer}
                        disabled={busy}
                        className="flex-1 flex flex-col items-center justify-center gap-1.5 bg-red-500 hover:bg-red-600 active:bg-red-700 text-white font-bold py-5 rounded-2xl shadow-md shadow-red-200 disabled:opacity-60 transition-all"
                      >
                        <span className="material-symbols-outlined text-3xl">pause_circle</span>
                        <span className="text-sm">PAUSE</span>
                      </button>

                      {/* GREEN — Complete */}
                      <button
                        onClick={completeJob}
                        disabled={busy}
                        className="flex-1 flex flex-col items-center justify-center gap-1.5 bg-emerald-500 hover:bg-emerald-600 active:bg-emerald-700 text-white font-bold py-5 rounded-2xl shadow-md shadow-emerald-200 disabled:opacity-60 transition-all"
                      >
                        <span className="material-symbols-outlined text-3xl">check_circle</span>
                        <span className="text-sm">COMPLETE</span>
                      </button>

                      {/* NOTES */}
                      <button
                        className="w-16 flex flex-col items-center justify-center gap-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold py-5 rounded-2xl transition-all"
                        onClick={() => alert('Notes coming soon')}
                      >
                        <span className="material-symbols-outlined text-2xl">edit_note</span>
                        <span className="text-xs">NOTES</span>
                      </button>
                    </div>
                  </div>
                ) : (
                  /* No active job — show idle state */
                  <div className="bg-white rounded-3xl shadow-sm border-2 border-dashed border-slate-200 p-8 text-center">
                    <div className="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-3">
                      <span className="material-symbols-outlined text-3xl text-slate-300">timer_off</span>
                    </div>
                    <p className="font-bold text-slate-500">No active timer</p>
                    <p className="text-sm text-slate-400 mt-1">Tap ▶ on a job below to start</p>
                  </div>
                )}
              </section>

              {/* ── TODAY'S TIME ─────────────────────────────── */}
              {dailySummary && dailySummary.raw_minutes > 0 && (
                <section>
                  <div className="text-xs font-bold uppercase tracking-widest text-slate-500 mb-3">Today's Time</div>
                  <div className="bg-white rounded-3xl shadow-sm px-5 py-4">
                    <div className="grid grid-cols-3 gap-3 mb-3">
                      <div className="text-center">
                        <div className="text-2xl font-bold font-mono text-slate-900">
                          {Math.floor(dailySummary.raw_minutes / 60)}h {dailySummary.raw_minutes % 60}m
                        </div>
                        <div className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Raw</div>
                      </div>
                      <div className="text-center">
                        <div className="text-2xl font-bold font-mono text-red-400">
                          -{dailySummary.deduction_minutes}m
                        </div>
                        <div className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Breaks/Lunch</div>
                      </div>
                      <div className="text-center">
                        <div className="text-2xl font-bold font-mono text-emerald-600">
                          {Math.floor(dailySummary.net_minutes / 60)}h {dailySummary.net_minutes % 60}m
                        </div>
                        <div className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Net</div>
                      </div>
                    </div>
                    {dailySummary.is_overtime && (
                      <div className="border-t border-slate-100 pt-3 flex items-center justify-center gap-2">
                        <span className="bg-amber-100 text-amber-700 text-[11px] font-bold px-3 py-1 rounded-full flex items-center gap-1">
                          <span className="material-symbols-outlined text-sm">schedule</span>
                          OT: +{Math.floor(dailySummary.overtime_minutes / 60) > 0 ? `${Math.floor(dailySummary.overtime_minutes / 60)}h ` : ''}{dailySummary.overtime_minutes % 60}m over {Math.floor(dailySummary.ot_threshold_minutes / 60)}h threshold
                        </span>
                      </div>
                    )}
                    {dailySummary.deduction_applied && (
                      <div className={`${dailySummary.is_overtime ? '' : 'border-t border-slate-100 '}pt-3 text-[11px] text-slate-400 text-center`}>
                        {dailySummary.break_count}× {dailySummary.break_minutes}m break + {dailySummary.lunch_minutes}m lunch auto-deducted
                      </div>
                    )}
                  </div>
                </section>
              )}

              {/* ── JOBS QUEUE ───────────────────────────────── */}
              <section>
                <div className="flex items-center justify-between mb-3">
                  <div className="text-xs font-bold uppercase tracking-widest text-slate-500">Jobs Queue</div>
                  <span className="text-xs font-bold bg-[#1f2d29] text-white px-2.5 py-1 rounded-full">
                    {queueJobs.length} Assigned
                  </span>
                </div>

                {queueJobs.length === 0 ? (
                  <div className="bg-white rounded-3xl border-2 border-dashed border-slate-200 p-6 text-center">
                    <p className="text-sm text-slate-400">No other jobs assigned to you</p>
                  </div>
                ) : (
                  <div className="space-y-3">
                    {queueJobs.map(job => {
                      const badge    = getStatusBadge(job);
                      const jid      = job.job_id || job.id;
                      const startable = canStart(job);
                      return (
                        <div key={jid} className="bg-white rounded-3xl shadow-sm px-4 py-4 flex items-center gap-3">
                          <div className="flex-1 min-w-0">
                            <div className="flex flex-wrap items-center gap-2 mb-1">
                              <span className="text-sm font-bold text-slate-800">
                                SO #{job.so_number || jid}
                              </span>
                              <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${badge.color}`}>
                                {badge.label}
                              </span>
                            </div>
                            <div className="text-base font-bold text-slate-900 truncate leading-tight">
                              {job.customer_name || job.dealer_name || 'Job'}
                            </div>
                            {job.vin && (
                              <div className="text-xs text-slate-400 mt-0.5 font-mono">
                                VIN: …{job.vin.slice(-8)}
                              </div>
                            )}
                          </div>
                          {/* GREEN play button */}
                          <button
                            onClick={() => startable ? startTimer(job) : undefined}
                            disabled={!startable || busy}
                            className={`w-14 h-14 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-sm transition-all
                              ${startable
                                ? 'bg-emerald-500 hover:bg-emerald-600 active:bg-emerald-700 shadow-emerald-200 text-white'
                                : 'bg-slate-100 text-slate-300 cursor-not-allowed'}`}
                          >
                            <span className="material-symbols-outlined text-3xl">play_arrow</span>
                          </button>
                        </div>
                      );
                    })}
                  </div>
                )}
              </section>

              {/* ── HELP SOMEONE ─────────────────────────────── */}
              <section>
                <button
                  onClick={() => setShowHelp(p => !p)}
                  className="w-full flex items-center justify-between px-5 py-4 bg-white rounded-3xl shadow-sm text-slate-600 hover:bg-slate-50 transition-colors"
                >
                  <span className="flex items-center gap-3 font-bold">
                    <span className="material-symbols-outlined text-xl text-slate-400">group_add</span>
                    Help on another job
                  </span>
                  <span className="material-symbols-outlined text-slate-400">
                    {showHelp ? 'expand_less' : 'expand_more'}
                  </span>
                </button>

                {showHelp && (
                  <div className="mt-3 space-y-3">
                    {allJobs.length === 0 ? (
                      <div className="bg-white rounded-3xl border-2 border-dashed border-slate-200 p-5 text-center text-sm text-slate-400">
                        No available jobs
                      </div>
                    ) : (
                      allJobs
                        .filter(j => (j.job_id || j.id) !== activeJobId)
                        .map(job => {
                          const badge = getStatusBadge(job);
                          const jid   = job.job_id || job.id;
                          return (
                            <div key={jid} className="bg-white rounded-3xl shadow-sm px-4 py-4 flex items-center gap-3">
                              <div className="flex-1 min-w-0">
                                <div className="flex flex-wrap items-center gap-2 mb-1">
                                  <span className="text-sm font-bold text-slate-800">SO #{job.so_number || jid}</span>
                                  <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${badge.color}`}>
                                    {badge.label}
                                  </span>
                                </div>
                                <div className="text-base font-bold text-slate-900 truncate leading-tight">
                                  {job.customer_name || job.dealer_name || 'Job'}
                                </div>
                                {job.work_center && (
                                  <div className="text-xs text-slate-400 mt-0.5">{job.work_center}</div>
                                )}
                              </div>
                              <button
                                onClick={() => startTimer(job, 'helping')}
                                disabled={busy}
                                className="w-14 h-14 rounded-2xl flex items-center justify-center flex-shrink-0 bg-emerald-500 hover:bg-emerald-600 text-white shadow-sm shadow-emerald-200 transition-all"
                              >
                                <span className="material-symbols-outlined text-3xl">play_arrow</span>
                              </button>
                            </div>
                          );
                        })
                    )}
                  </div>
                )}
              </section>
            </>
          )}
        </div>

        {/* ── Bottom tab bar ─────────────────────────────────── */}
        <div className="bg-white border-t border-slate-100 px-2 py-2 flex justify-around flex-shrink-0">
          {[
            { key: 'myjobs',     icon: 'engineering',   label: 'MY JOBS' },
            { key: 'alerts',     icon: 'notifications', label: 'ALERTS' },
            { key: 'inspection', icon: 'fact_check',    label: 'INSPECTION' },
            { key: 'settings',   icon: 'settings',      label: 'SETTINGS' },
          ].map(({ key, icon, label }) => (
            <button
              key={key}
              onClick={() => setTab(key as Tab)}
              className={`flex flex-col items-center gap-0.5 px-4 py-2 rounded-xl transition-colors
                ${tab === key ? 'text-[#1f2d29]' : 'text-slate-400 hover:text-slate-600'}`}
            >
              <span className={`material-symbols-outlined text-2xl ${tab === key ? 'fill-1' : ''}`}>{icon}</span>
              <span className="text-[9px] font-bold tracking-widest">{label}</span>
            </button>
          ))}
        </div>
      </div>
    </div>
  );
}
