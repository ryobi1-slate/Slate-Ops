import React, { useState } from 'react';
import { Job, Dealer } from '../types';
import { jobsService } from '../services/api';

interface CsDashboardProps {
  dealers: Dealer[];
  jobs: Job[];
  onJobCreated?: (job: Job) => void;
}

const EMPTY_FORM = {
  customer_name: '',
  dealer_id: '',
  vin: '',
  job_type: 'UPFIT',
  parts_status: 'NOT_READY',
  est_hours: 1,
  so_number: '',
  due_date: '',
};

const PARTS_COLOR: Record<string, string> = {
  READY:     'bg-green-100 text-green-700',
  PARTIAL:   'bg-yellow-100 text-yellow-700',
  NOT_READY: 'bg-slate-100 text-slate-600',
  received:  'bg-slate-100 text-slate-600',
};
const STATUS_COLOR: Record<string, string> = {
  READY_FOR_SCHEDULING: 'bg-slate-700 text-white',
  IN_PROGRESS:          'bg-blue-700 text-white',
  SCHEDULED:            'bg-orange-600 text-white',
  ON_HOLD:              'bg-amber-600 text-white',
  PENDING_QC:           'bg-purple-700 text-white',
  PENDING_INTAKE:       'bg-slate-500 text-white',
  NEEDS_SO:             'bg-red-600 text-white',
  UNSCHEDULED:          'bg-slate-600 text-white',
};

export function CsDashboard({ dealers, jobs: initialJobs, onJobCreated }: CsDashboardProps) {
  const [jobs, setJobs] = useState<Job[]>(initialJobs);
  const [form, setForm] = useState({ ...EMPTY_FORM });
  const [submitting, setSubmitting] = useState(false);
  const [expanded, setExpanded] = useState<string | null>(null);

  React.useEffect(() => { setJobs(initialJobs); }, [initialJobs]);

  const toggle = (s: string) => setExpanded(p => (p === s ? null : s));

  const pendingJobs  = jobs.filter(j => j.status === 'PENDING_INTAKE');
  const needsSoJobs  = jobs.filter(j => j.status === 'NEEDS_SO');
  const activeJobs   = jobs.filter(j => !['COMPLETE','COMPLETED','PENDING_INTAKE','NEEDS_SO'].includes(j.status));

  async function handleCreate(e: React.FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    try {
      const created = await jobsService.create({
        customer_name: form.customer_name,
        dealer_id: Number(form.dealer_id),
        vin: form.vin,
        job_type: form.job_type as any,
        parts_status: form.parts_status as any,
        est_hours: Number(form.est_hours),
        so_number: form.so_number || undefined,
        due_date: form.due_date || undefined,
        status: form.so_number ? 'UNSCHEDULED' : 'PENDING_INTAKE',
        created_at: new Date().toISOString(),
      });
      setJobs(prev => [created, ...prev]);
      onJobCreated?.(created);
      setForm({ ...EMPTY_FORM });
    } catch (err) {
      console.error('Failed to create job:', err);
    } finally {
      setSubmitting(false);
    }
  }

  function Section({ id, label, rows }: { id: string; label: string; rows: Job[] }) {
    const open = expanded === id;
    return (
      <div className="bg-white rounded-xl shadow-sm overflow-hidden mb-3">
        <button
          onClick={() => toggle(id)}
          className="w-full flex items-center justify-between px-6 py-4 hover:bg-slate-50 transition-colors"
        >
          <div className="flex items-center gap-3">
            <span className="text-xs font-bold text-slate-600 uppercase tracking-widest">{label}</span>
            <span className="w-6 h-6 rounded-full bg-slate-100 text-slate-600 text-[11px] font-bold flex items-center justify-center">{rows.length}</span>
          </div>
          <span className={`material-symbols-outlined text-slate-400 text-xl transition-transform ${open ? 'rotate-180' : ''}`}>expand_more</span>
        </button>

        {open && (
          <div className="border-t border-slate-100">
            {rows.length === 0 ? (
              <p className="px-6 py-5 text-sm text-slate-400 italic text-center">No jobs found</p>
            ) : (
              <table className="w-full text-sm">
                <thead className="bg-slate-50 text-slate-400 text-[10px] font-bold uppercase tracking-wider">
                  <tr>
                    <th className="px-6 py-2.5 text-left">Customer</th>
                    <th className="px-6 py-2.5 text-left">SO#</th>
                    <th className="px-6 py-2.5 text-left">VIN</th>
                    <th className="px-6 py-2.5 text-left">Dealer</th>
                    <th className="px-6 py-2.5 text-left">Type</th>
                    <th className="px-6 py-2.5 text-left">Parts</th>
                    <th className="px-6 py-2.5 text-left">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {rows.map(job => (
                    <tr key={job.id ?? job.job_id} className="hover:bg-slate-50">
                      <td className="px-6 py-3 font-bold text-slate-900">{job.customer_name || '—'}</td>
                      <td className="px-6 py-3 font-mono text-slate-600 text-xs">{job.so_number || <span className="text-slate-400">—</span>}</td>
                      <td className="px-6 py-3 font-mono text-blue-600 text-xs hover:underline cursor-pointer">{job.vin}</td>
                      <td className="px-6 py-3 text-slate-600">{job.dealer_name || dealers.find(d => d.id === job.dealer_id)?.name || '—'}</td>
                      <td className="px-6 py-3">
                        <span className="px-2 py-0.5 rounded text-[10px] font-bold border border-slate-300 text-slate-600">
                          {job.job_type}
                        </span>
                      </td>
                      <td className="px-6 py-3">
                        <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${PARTS_COLOR[job.parts_status] || 'bg-slate-100 text-slate-600'}`}>
                          {job.parts_status}
                        </span>
                      </td>
                      <td className="px-6 py-3">
                        <span className={`px-2.5 py-1 rounded text-[10px] font-bold ${STATUS_COLOR[job.status] || 'bg-slate-200 text-slate-700'}`}>
                          {job.status}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        )}
      </div>
    );
  }

  return (
    <div className="flex-1 overflow-y-auto bg-[#EAE8DC] p-6 font-sans">

      {/* Top section: header card + stat boxes */}
      <div className="flex gap-4 mb-4">
        {/* Title card */}
        <div className="flex-1 bg-white rounded-xl shadow-sm p-6 flex flex-col justify-center">
          <h1 className="text-2xl font-bold text-slate-900 mb-1">Customer Service</h1>
          <p className="text-slate-500 text-sm">Complete intake and assign SO#s for incoming jobs.</p>
        </div>

        {/* Stat boxes */}
        <div className="flex flex-col gap-3">
          <div className="flex gap-3">
            <div className="bg-[#EAE8DC] rounded-xl px-6 py-4 text-center min-w-[110px]">
              <div className="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Pending<br/>Intake</div>
              <div className="text-3xl font-bold text-slate-900">{pendingJobs.length}</div>
            </div>
            <div className="bg-[#EAE8DC] rounded-xl px-6 py-4 text-center min-w-[110px]">
              <div className="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Needs<br/>SO#</div>
              <div className="text-3xl font-bold text-slate-900">{needsSoJobs.length}</div>
            </div>
          </div>
          <div className="bg-[#EAE8DC] rounded-xl px-6 py-4 text-center">
            <div className="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Active Jobs</div>
            <div className="text-3xl font-bold text-slate-900">{activeJobs.length}</div>
          </div>
        </div>
      </div>

      {/* Create Job Form */}
      <div className="bg-white rounded-xl shadow-sm p-5 mb-4">
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-xs font-bold text-slate-700 uppercase tracking-widest">Create Manual Job</h2>
          <button className="text-slate-400 hover:text-slate-600">
            <span className="material-symbols-outlined text-base">more_horiz</span>
          </button>
        </div>
        <form onSubmit={handleCreate}>
          {/* Row 1: all six fields */}
          <div className="grid grid-cols-6 gap-3 mb-3">
            <div>
              <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1">Customer</label>
              <input
                type="text" placeholder="Enter customer name" required
                value={form.customer_name}
                onChange={e => setForm({ ...form, customer_name: e.target.value })}
                className="w-full border border-slate-200 rounded px-2.5 py-2 text-sm focus:ring-primary focus:border-primary"
              />
            </div>
            <div>
              <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1">Dealer</label>
              <select
                value={form.dealer_id}
                onChange={e => setForm({ ...form, dealer_id: e.target.value })}
                className="w-full border border-slate-200 rounded px-2.5 py-2 text-sm focus:ring-primary focus:border-primary"
              >
                <option value="">Select...</option>
                {dealers.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1">VIN</label>
              <input
                type="text" placeholder="VIN (required unless Portal Job)"
                value={form.vin}
                onChange={e => setForm({ ...form, vin: e.target.value })}
                className="w-full border border-slate-200 rounded px-2.5 py-2 text-sm focus:ring-primary focus:border-primary"
              />
            </div>
            <div>
              <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1">Job Type</label>
              <select
                value={form.job_type}
                onChange={e => setForm({ ...form, job_type: e.target.value })}
                className="w-full border border-slate-200 rounded px-2.5 py-2 text-sm focus:ring-primary focus:border-primary"
              >
                <option value="UPFIT">UPFIT</option>
                <option value="REPAIR">REPAIR</option>
                <option value="WARRANTY">WARRANTY</option>
              </select>
            </div>
            <div>
              <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1">Parts Status</label>
              <select
                value={form.parts_status}
                onChange={e => setForm({ ...form, parts_status: e.target.value })}
                className="w-full border border-slate-200 rounded px-2.5 py-2 text-sm focus:ring-primary focus:border-primary"
              >
                <option value="NOT_READY">NOT_READY</option>
                <option value="PARTIAL">PARTIAL</option>
                <option value="READY">READY</option>
              </select>
            </div>
            <div>
              <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1">Est. Hours</label>
              <input
                type="number" min="0.5" step="0.5"
                value={form.est_hours}
                onChange={e => setForm({ ...form, est_hours: Number(e.target.value) })}
                className="w-full border border-slate-200 rounded px-2.5 py-2 text-sm focus:ring-primary focus:border-primary"
              />
            </div>
          </div>
          {/* Row 2 */}
          <div className="grid grid-cols-6 gap-3 items-end">
            <div className="col-span-2">
              <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1">SO# (Optional)</label>
              <input
                type="text" placeholder="S-ORD######"
                value={form.so_number}
                onChange={e => setForm({ ...form, so_number: e.target.value })}
                className="w-full border border-slate-200 rounded px-2.5 py-2 text-sm focus:ring-primary focus:border-primary"
              />
            </div>
            <div className="col-span-2">
              <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1">Due Date (Optional)</label>
              <input
                type="date"
                value={form.due_date}
                onChange={e => setForm({ ...form, due_date: e.target.value })}
                className="w-full border border-slate-200 rounded px-2.5 py-2 text-sm focus:ring-primary focus:border-primary"
              />
            </div>
            <div className="col-span-2 flex justify-end">
              <button
                type="submit" disabled={submitting}
                className="bg-slate-800 hover:bg-slate-900 text-white font-bold py-2 px-5 rounded text-sm flex items-center gap-2 transition-colors disabled:opacity-50"
              >
                <span className="material-symbols-outlined text-sm">add_circle</span>
                {submitting ? 'Creating...' : 'Create Job'}
              </button>
            </div>
          </div>
        </form>
      </div>

      {/* Collapsible job sections */}
      <Section id="PENDING_INTAKE" label="Pending Intake — Portal Jobs" rows={pendingJobs} />
      <Section id="NEEDS_SO"       label="Needs SO# — Manual Jobs"       rows={needsSoJobs} />
      <Section id="ACTIVE"         label="Active Jobs"                   rows={activeJobs} />
    </div>
  );
}
