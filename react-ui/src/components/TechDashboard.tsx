import React, { useState, useEffect, useCallback, useRef } from 'react';
import { Job } from '../types';
import { timeService, ActiveSegment, jobsService } from '../services/api';

// ── Helpers ────────────────────────────────────────────────────────

function pad(n: number) {
  return String(n).padStart(2, '0');
}

function elapsedSeconds(startTsGmt: string): number {
  // start_ts comes from MySQL without timezone. Treat as UTC by appending Z.
  const adjusted = startTsGmt.endsWith('Z') ? startTsGmt : startTsGmt.replace(' ', 'T') + 'Z';
  return Math.max(0, Math.floor((Date.now() - new Date(adjusted).getTime()) / 1000));
}

function formatElapsed(seconds: number) {
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  const s = seconds % 60;
  return { h: pad(h), m: pad(m), s: pad(s) };
}

type JobStatusBadge = { label: string; color: string };

function getStatusBadge(job: Job): JobStatusBadge {
  if (job.delay_reason || job.status === 'ON_HOLD') {
    return { label: 'BLOCKED', color: 'bg-slate-100 text-slate-500 border border-slate-300' };
  }
  if (job.parts_status === 'NOT_READY' || job.parts_status === 'PARTIAL') {
    return { label: 'PARTS PENDING', color: 'bg-orange-100 text-orange-700 border border-orange-300' };
  }
  return { label: 'READY', color: 'bg-green-100 text-green-700 border border-green-300' };
}

function canStart(job: Job): boolean {
  return job.status !== 'ON_HOLD' && job.status !== 'COMPLETE' && job.status !== 'COMPLETED';
}

const userName = typeof window !== 'undefined'
  ? (window.slateOpsSettings?.user?.name || 'Tech')
  : 'Tech';

// ── Component ──────────────────────────────────────────────────────

type Tab = 'myjobs' | 'alerts' | 'inspection' | 'settings';

export function TechDashboard() {
  const [active, setActive] = useState<ActiveSegment | null>(null);
  const [elapsed, setElapsed] = useState(0);
  const [myJobs, setMyJobs] = useState<Job[]>([]);
  const [allJobs, setAllJobs] = useState<Job[]>([]);
  const [tab, setTab] = useState<Tab>('myjobs');
  const [showHelp, setShowHelp] = useState(false);
  const [loading, setLoading] = useState(true);
  const [actionInFlight, setActionInFlight] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);

  // ── Data loading ─────────────────────────────────────────────────

  const loadActive = useCallback(async () => {
    try {
      const seg = await timeService.getActive();
      setActive(seg);
      if (seg) {
        setElapsed(elapsedSeconds(seg.start_ts));
      }
    } catch {
      // silently fail on polling errors
    }
  }, []);

  const loadMyJobs = useCallback(async () => {
    try {
      const resp = await fetch(
        `${(window.slateOpsSettings?.api?.root || '/wp-json/slate-ops/v1').replace(/\/$/, '')}/jobs?assigned_me=1&limit=50&status_in=SCHEDULED,IN_PROGRESS,READY_FOR_SCHEDULING,UNSCHEDULED`,
        {
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': window.slateOpsSettings?.api?.nonce || '',
          },
        }
      );
      const data = await resp.json();
      setMyJobs(Array.isArray(data) ? data : data.jobs || []);
    } catch {
      setMyJobs([]);
    }
  }, []);

  const loadAllJobs = useCallback(async () => {
    try {
      const resp = await fetch(
        `${(window.slateOpsSettings?.api?.root || '/wp-json/slate-ops/v1').replace(/\/$/, '')}/jobs?status_in=SCHEDULED,READY_FOR_SCHEDULING&limit=100`,
        {
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': window.slateOpsSettings?.api?.nonce || '',
          },
        }
      );
      const data = await resp.json();
      setAllJobs(Array.isArray(data) ? data : data.jobs || []);
    } catch {
      setAllJobs([]);
    }
  }, []);

  // Initial load
  useEffect(() => {
    const init = async () => {
      setLoading(true);
      await Promise.all([loadActive(), loadMyJobs()]);
      setLoading(false);
    };
    init();
  }, [loadActive, loadMyJobs]);

  // Load all jobs when help panel opens
  useEffect(() => {
    if (showHelp && allJobs.length === 0) loadAllJobs();
  }, [showHelp, allJobs.length, loadAllJobs]);

  // Ticker
  useEffect(() => {
    if (timerRef.current) clearInterval(timerRef.current);
    if (active?.start_ts) {
      timerRef.current = setInterval(() => {
        setElapsed(elapsedSeconds(active.start_ts));
      }, 1000);
    }
    return () => {
      if (timerRef.current) clearInterval(timerRef.current);
    };
  }, [active?.start_ts]);

  // ── Actions ───────────────────────────────────────────────────────

  const startTimer = async (job: Job, reason = 'assigned') => {
    if (actionInFlight) return;
    setError(null);
    setActionInFlight(true);
    try {
      await timeService.start(job.job_id || job.id, reason);
      await loadActive();
      await loadMyJobs();
      setShowHelp(false);
    } catch (e: any) {
      setError(e?.message || 'Failed to start timer');
    } finally {
      setActionInFlight(false);
    }
  };

  const pauseTimer = async () => {
    if (!active || actionInFlight) return;
    setError(null);
    setActionInFlight(true);
    try {
      await timeService.stop();
      setActive(null);
      setElapsed(0);
      await loadMyJobs();
    } catch (e: any) {
      setError(e?.message || 'Failed to pause timer');
    } finally {
      setActionInFlight(false);
    }
  };

  const completeJob = async () => {
    if (!active || actionInFlight) return;
    const confirmed = window.confirm('Mark this job as complete and stop the timer?');
    if (!confirmed) return;
    setError(null);
    setActionInFlight(true);
    try {
      await timeService.stop();
      // Advance status to PENDING_QC
      await fetch(
        `${(window.slateOpsSettings?.api?.root || '/wp-json/slate-ops/v1').replace(/\/$/, '')}/jobs/${active.job_id}/status`,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': window.slateOpsSettings?.api?.nonce || '',
          },
          body: JSON.stringify({ status: 'PENDING_QC' }),
        }
      );
      setActive(null);
      setElapsed(0);
      await loadMyJobs();
    } catch (e: any) {
      setError(e?.message || 'Failed to complete job');
    } finally {
      setActionInFlight(false);
    }
  };

  // ── Render ────────────────────────────────────────────────────────

  const { h, m, s } = formatElapsed(elapsed);
  const activeJobId = active?.job_id;
  const queueJobs = myJobs.filter(j => (j.job_id || j.id) !== activeJobId);

  return (
    <div className="flex-1 flex flex-col bg-[#EAE8DC] overflow-hidden">
      {/* Mobile-centered layout */}
      <div className="flex-1 flex flex-col max-w-md mx-auto w-full">

        {/* Top bar */}
        <div className="bg-[#2d3e39] text-white px-5 py-3 flex items-center justify-between flex-shrink-0">
          <div className="flex items-center gap-3">
            <div className="w-8 h-8 rounded-sm overflow-hidden bg-white/10 flex items-center justify-center">
              <span className="material-symbols-outlined text-white text-lg">precision_manufacturing</span>
            </div>
            <span className="font-bold text-lg">{userName}</span>
          </div>
          <div className="flex items-center gap-3">
            <button className="relative text-white/80 hover:text-white">
              <span className="material-symbols-outlined">notifications</span>
              <span className="absolute -top-0.5 -right-0.5 w-2 h-2 bg-[#d86b19] rounded-full" />
            </button>
            <div className="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center font-bold text-sm">
              {userName.charAt(0)}
            </div>
          </div>
        </div>

        {/* Error banner */}
        {error && (
          <div className="bg-red-50 border-b border-red-200 px-5 py-2 text-sm text-red-700 flex items-center justify-between">
            {error}
            <button onClick={() => setError(null)} className="text-red-400 hover:text-red-600">
              <span className="material-symbols-outlined text-sm">close</span>
            </button>
          </div>
        )}

        {/* Scroll area */}
        <div className="flex-1 overflow-y-auto px-4 py-5 space-y-5">

          {loading ? (
            <div className="flex flex-col items-center justify-center h-48 text-slate-400 gap-3">
              <span className="material-symbols-outlined animate-spin text-3xl">refresh</span>
              <span className="text-sm">Loading your jobs…</span>
            </div>
          ) : (
            <>
              {/* ── Active Labor ─────────────────────────────────── */}
              <section>
                <div className="text-xs font-bold uppercase tracking-widest text-slate-500 mb-2">Active Labor</div>

                {active ? (
                  <div className="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
                    <div className="flex items-start justify-between mb-1">
                      <div>
                        <div className="text-xs font-bold text-slate-500 mb-0.5">SO# {active.so_number || active.job_id}</div>
                        <div className="text-xl font-bold text-slate-900 leading-tight">{active.customer_name || 'Job in progress'}</div>
                        {active.work_center && (
                          <div className="text-xs font-bold text-slate-400 uppercase mt-0.5 tracking-wide">
                            PHASE: {active.work_center}
                          </div>
                        )}
                      </div>
                      <span className="text-[10px] font-bold bg-[#2d3e39] text-white px-2 py-0.5 rounded uppercase tracking-wide">
                        ACTIVE
                      </span>
                    </div>

                    {/* Timer display */}
                    <div className="flex gap-2 my-4">
                      {[{ val: h, label: 'HOURS' }, { val: m, label: 'MINUTES' }, { val: s, label: 'SECONDS' }].map(({ val, label }) => (
                        <div key={label} className="flex-1 bg-slate-50 rounded-xl py-3 text-center border border-slate-100">
                          <div className="text-3xl font-bold text-slate-900 font-mono">{val}</div>
                          <div className="text-[9px] font-bold text-slate-400 tracking-widest mt-0.5">{label}</div>
                        </div>
                      ))}
                    </div>

                    {/* Action buttons */}
                    <div className="flex gap-2">
                      <button
                        onClick={pauseTimer}
                        disabled={actionInFlight}
                        className="flex-1 flex items-center justify-center gap-2 bg-[#2d3e39] text-white font-bold py-3 rounded-xl hover:bg-[#1f2d29] disabled:opacity-60 transition-colors"
                      >
                        <span className="material-symbols-outlined text-lg">pause_circle</span>
                        Pause Labor
                      </button>
                      <button
                        onClick={completeJob}
                        disabled={actionInFlight}
                        className="flex-1 font-bold py-3 rounded-xl bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 disabled:opacity-60 transition-colors"
                      >
                        Complete
                      </button>
                      <button
                        className="px-4 py-3 rounded-xl bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 transition-colors"
                        onClick={() => alert('Notes coming soon')}
                      >
                        <span className="material-symbols-outlined text-lg">edit_note</span>
                      </button>
                    </div>
                  </div>
                ) : (
                  <div className="bg-white rounded-2xl shadow-sm border border-dashed border-slate-200 p-6 text-center">
                    <span className="material-symbols-outlined text-3xl text-slate-300 mb-2 block">timer_off</span>
                    <p className="text-sm text-slate-400">No active timer — tap ▶ on a job to start</p>
                  </div>
                )}
              </section>

              {/* ── Jobs Queue ────────────────────────────────────── */}
              <section>
                <div className="flex items-center justify-between mb-2">
                  <div className="text-xs font-bold uppercase tracking-widest text-slate-500">Jobs Queue</div>
                  <span className="text-xs font-bold bg-slate-200 text-slate-600 px-2 py-0.5 rounded-full">
                    {queueJobs.length} Assigned
                  </span>
                </div>

                {queueJobs.length === 0 ? (
                  <div className="bg-white rounded-2xl border border-dashed border-slate-200 p-6 text-center">
                    <p className="text-sm text-slate-400">No other jobs assigned to you</p>
                  </div>
                ) : (
                  <div className="space-y-2">
                    {queueJobs.map(job => {
                      const badge = getStatusBadge(job);
                      const jid = job.job_id || job.id;
                      const startable = canStart(job);
                      return (
                        <div key={jid} className="bg-white rounded-2xl shadow-sm border border-slate-100 px-4 py-3.5 flex items-center gap-3">
                          <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-2 mb-0.5">
                              <span className="text-sm font-bold text-slate-800">
                                SO #{job.so_number || jid}
                              </span>
                              <span className={`text-[10px] font-bold px-1.5 py-0.5 rounded ${badge.color}`}>
                                {badge.label}
                              </span>
                            </div>
                            <div className="text-sm font-bold text-slate-900 truncate">
                              {job.customer_name || job.dealer_name || 'Job'}
                            </div>
                            {job.vin && (
                              <div className="text-xs text-slate-400 mt-0.5">
                                VIN: …{job.vin.slice(-8)}
                              </div>
                            )}
                          </div>
                          <button
                            onClick={() => startable ? startTimer(job) : undefined}
                            disabled={!startable || actionInFlight}
                            className={`w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 transition-colors
                              ${startable
                                ? 'bg-slate-100 hover:bg-[#2d3e39] hover:text-white text-slate-600'
                                : 'bg-slate-50 text-slate-300 cursor-not-allowed'}`}
                          >
                            <span className="material-symbols-outlined text-lg">play_arrow</span>
                          </button>
                        </div>
                      );
                    })}
                  </div>
                )}
              </section>

              {/* ── Help someone else ─────────────────────────────── */}
              <section>
                <button
                  onClick={() => setShowHelp(prev => !prev)}
                  className="w-full flex items-center justify-between px-4 py-3 bg-white rounded-2xl border border-slate-100 shadow-sm text-sm font-medium text-slate-600 hover:bg-slate-50 transition-colors"
                >
                  <span className="flex items-center gap-2">
                    <span className="material-symbols-outlined text-lg text-slate-400">group</span>
                    Help on another job
                  </span>
                  <span className="material-symbols-outlined text-slate-400">
                    {showHelp ? 'expand_less' : 'expand_more'}
                  </span>
                </button>

                {showHelp && (
                  <div className="mt-2 space-y-2">
                    {allJobs.length === 0 ? (
                      <div className="bg-white rounded-2xl border border-dashed border-slate-200 p-5 text-center text-sm text-slate-400">
                        No available jobs
                      </div>
                    ) : (
                      allJobs
                        .filter(j => (j.job_id || j.id) !== activeJobId)
                        .map(job => {
                          const badge = getStatusBadge(job);
                          const jid = job.job_id || job.id;
                          return (
                            <div key={jid} className="bg-white rounded-2xl shadow-sm border border-slate-100 px-4 py-3.5 flex items-center gap-3">
                              <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2 mb-0.5">
                                  <span className="text-sm font-bold text-slate-800">SO #{job.so_number || jid}</span>
                                  <span className={`text-[10px] font-bold px-1.5 py-0.5 rounded ${badge.color}`}>
                                    {badge.label}
                                  </span>
                                </div>
                                <div className="text-sm font-bold text-slate-900 truncate">
                                  {job.customer_name || job.dealer_name || 'Job'}
                                </div>
                                {job.work_center && (
                                  <div className="text-xs text-slate-400 mt-0.5">{job.work_center}</div>
                                )}
                              </div>
                              <button
                                onClick={() => startTimer(job, 'helping')}
                                disabled={actionInFlight}
                                className="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-slate-100 hover:bg-[#2d3e39] hover:text-white text-slate-600 transition-colors"
                              >
                                <span className="material-symbols-outlined text-lg">play_arrow</span>
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

        {/* ── Bottom tab bar ────────────────────────────────────── */}
        <div className="bg-white border-t border-slate-100 px-4 py-2 flex justify-around flex-shrink-0">
          {[
            { key: 'myjobs',     icon: 'engineering',   label: 'MY JOBS' },
            { key: 'alerts',     icon: 'notifications', label: 'ALERTS' },
            { key: 'inspection', icon: 'fact_check',    label: 'INSPECTION' },
            { key: 'settings',   icon: 'settings',      label: 'SETTINGS' },
          ].map(({ key, icon, label }) => (
            <button
              key={key}
              onClick={() => setTab(key as Tab)}
              className={`flex flex-col items-center gap-0.5 px-3 py-1 rounded-lg transition-colors
                ${tab === key ? 'text-[#2d3e39]' : 'text-slate-400 hover:text-slate-600'}`}
            >
              <span className={`material-symbols-outlined text-2xl ${tab === key ? 'fill-1' : ''}`}>
                {icon}
              </span>
              <span className="text-[9px] font-bold tracking-wide">{label}</span>
            </button>
          ))}
        </div>
      </div>
    </div>
  );
}
