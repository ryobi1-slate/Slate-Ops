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

export function CsDashboard({ dealers, jobs: initialJobs, onJobCreated }: CsDashboardProps) {
  const [jobs, setJobs] = useState<Job[]>(initialJobs);
  const [form, setForm] = useState({ ...EMPTY_FORM });
  const [submitting, setSubmitting] = useState(false);
  const [expanded, setExpanded] = useState<string | null>('PENDING_INTAKE');

  React.useEffect(() => { setJobs(initialJobs); }, [initialJobs]);

  const toggle = (s: string) => setExpanded(p => (p === s ? null : s));

  const stats = {
    pending: jobs.filter(j => j.status === 'PENDING_INTAKE').length,
    needs_so: jobs.filter(j => j.status === 'NEEDS_SO').length,
    active: jobs.filter(j => !['COMPLETE','COMPLETED','PENDING_INTAKE','NEEDS_SO'].includes(j.status)).length,
  };

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
        status: form.so_number ? 'ACTIVE' : 'PENDING_INTAKE',
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

  function Section({ id, label, count, children }: { id: string; label: string; count: number; children: React.ReactNode }) {
    const open = expanded === id;
    return (
      <div className="border border-dashed border-[#c0392b]/40 rounded-lg overflow-hidden mb-3">
        <button
          onClick={() => toggle(id)}
          className="w-full flex items-center justify-between px-5 py-3 hover:bg-white/50 transition-colors"
        >
          <div className="flex items-center gap-2">
            <span className="text-[#c0392b] font-bold text-xs uppercase tracking-wider">{label}</span>
            <span className="w-5 h-5 rounded-full bg-[#c0392b] text-white text-[10px] font-bold flex items-center justify-center">{count}</span>
          </div>
          <span className={`material-symbols-outlined text-slate-400 text-lg transition-transform ${open ? 'rotate-180' : ''}`}>expand_more</span>
        </button>
        {open && <div className="border-t border-dashed border-slate-200">{children}</div>}
      </div>
    );
  }

  function JobRows({ statusList }: { statusList: string[] }) {
    const rows = jobs.filter(j => statusList.includes(j.status));
    if (rows.length === 0) return <p className="px-5 py-4 text-sm text-slate-400 italic text-center">No jobs found</p>;
    return (
      <table className="w-full text-sm">
        <thead className="bg-slate-50 text-slate-400 text-[10px] uppercase tracking-wider">
          <tr>
            <th className="px-5 py-2 text-left font-bold">Customer</th>
            <th className="px-5 py-2 text-left font-bold">SO#</th>
            <th className="px-5 py-2 text-left font-bold">VIN</th>
            <th className="px-5 py-2 text-left font-bold">Dealer</th>
            <th className="px-5 py-2 text-left font-bold">Type</th>
            <th className="px-5 py-2 text-left font-bold">Parts</th>
            <th className="px-5 py-2 text-left font-bold">Status</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {rows.map(job => (
            <tr key={job.id ?? job.job_id} className="hover:bg-slate-50">
              <td className="px-5 py-3 font-medium text-slate-900">{job.customer_name}</td>
              <td className="px-5 py-3 font-mono text-slate-600 text-xs">{job.so_number || <span className="text-orange-500 font-bold">MISSING</span>}</td>
              <td className="px-5 py-3 font-mono text-slate-500 text-xs">{job.vin}</td>
              <td className="px-5 py-3 text-slate-600">{job.dealer_name || dealers.find(d => d.id === job.dealer_id)?.name || '—'}</td>
              <td className="px-5 py-3"><span className="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-700">{job.job_type}</span></td>
              <td className="px-5 py-3"><span className={`px-2 py-0.5 rounded text-[10px] font-bold ${job.parts_status === 'READY' ? 'bg-green-50 text-green-700' : job.parts_status === 'PARTIAL' ? 'bg-yellow-50 text-yellow-700' : 'bg-slate-100 text-slate-600'}`}>{job.parts_status}</span></td>
              <td className="px-5 py-3"><span className="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">{job.status}</span></td>
            </tr>
          ))}
        </tbody>
      </table>
    );
  }

  return (
    <div className="flex-1 overflow-y-auto bg-[#EAE8DC] p-6 font-sans">
      {/* Header Card */}
      <div className="bg-white rounded-xl shadow-sm p-6 mb-4 flex justify-between items-start">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Customer Service</h1>
          <p className="text-slate-500 text-sm mt-1">Complete intake and assign SO#s for incoming jobs.</p>
        </div>
        <div className="flex flex-col gap-2 items-end">
          <div className="flex gap-2">
            <div className="bg-[#EAE8DC] rounded-lg px-5 py-3 text-center min-w-[100px]">
              <div className="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Pending Intake</div>
              <div className="text-3xl font-bold text-slate-900">{stats.pending}</div>
            </div>
            <div className="bg-[#EAE8DC] rounded-lg px-5 py-3 text-center min-w-[100px]">
              <div className="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Needs SO#</div>
              <div className="text-3xl font-bold text-slate-900">{stats.needs_so}</div>
            </div>
          </div>
          <div className="bg-[#EAE8DC] rounded-lg px-5 py-3 text-center w-full">
            <div className="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Active Jobs</div>
            <div className="text-3xl font-bold text-slate-900">{stats.active}</div>
          </div>
        </div>
      </div>

      {/* Create Job Form */}
      <div className="bg-white rounded-xl shadow-sm p-5 mb-4">
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-xs font-bold text-slate-600 uppercase tracking-widest">Create Manual Job</h2>
          <button onClick={() => setForm({ ...EMPTY_FORM })} className="text-slate-400 hover:text-slate-600">
            <span className="material-symbols-outlined text-lg">close</span>
          </button>
        </div>
        <form onSubmit={handleCreate}>
          {/* Row 1 */}
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
              <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1">SO# (optional)</label>
              <input
                type="text" placeholder="S-ORD######"
                value={form.so_number}
                onChange={e => setForm({ ...form, so_number: e.target.value })}
                className="w-full border border-slate-200 rounded px-2.5 py-2 text-sm focus:ring-primary focus:border-primary"
              />
            </div>
            <div className="col-span-2">
              <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1">Due Date (optional)</label>
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
                className="bg-slate-700 hover:bg-slate-800 text-white font-bold py-2 px-5 rounded text-sm flex items-center gap-2 transition-colors disabled:opacity-50"
              >
                <span className="material-symbols-outlined text-sm">add_circle</span>
                {submitting ? 'Creating...' : 'Create Job'}
              </button>
            </div>
          </div>
        </form>
      </div>

      {/* Collapsible Sections */}
      <Section id="PENDING_INTAKE" label="Pending Intake — Portal Jobs" count={stats.pending}>
        <JobRows statusList={['PENDING_INTAKE']} />
      </Section>
      <Section id="NEEDS_SO" label="Needs SO# — Manual Jobs" count={stats.needs_so}>
        <JobRows statusList={['NEEDS_SO']} />
      </Section>
      <Section id="ACTIVE" label="Active Jobs" count={stats.active}>
        <JobRows statusList={['ACTIVE','UNSCHEDULED','READY_FOR_SCHEDULING','SCHEDULED','IN_PROGRESS','ON_HOLD','PENDING_QC']} />
      </Section>
    </div>
  );
}
