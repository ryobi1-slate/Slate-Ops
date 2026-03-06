import React, { useState } from 'react';
import { Job, Dealer } from '../types';
import { jobsService } from '../services/api';

interface CsDashboardProps {
  dealers: Dealer[];
  jobs: Job[];
  onJobCreated?: (job: Job) => void;
}

export function CsDashboard({ dealers, jobs: initialJobs, onJobCreated }: CsDashboardProps) {
  // We keep local state for optimistic updates or filtering if needed, 
  // but initialize from props. In a real app, we might just use props 
  // and trigger a re-fetch or update parent state.
  // For now, let's just use the props directly for display, 
  // but since we have a "Create Job" form that updates the list locally in the original code,
  // we might want to keep the local state pattern or lift the create handler.
  // Given the "do it all" instruction, let's try to use the service for creation.
  
  const [jobs, setJobs] = useState<Job[]>(initialJobs);

  // Sync props to state if they change (e.g. after a fetch)
  React.useEffect(() => {
    setJobs(initialJobs);
  }, [initialJobs]);

  const [form, setForm] = useState({
    customer_name: '',
    dealer_id: '',
    vin: '',
    job_type: 'UPFIT',
    parts_status: 'NOT_READY',
    est_hours: 1,
    so_number: '',
    due_date: ''
  });

  const [expandedSection, setExpandedSection] = useState<string | null>('PENDING_INTAKE');

  const toggleSection = (section: string) => {
    setExpandedSection(expandedSection === section ? null : section);
  };

  const handleCreateJob = async (e: React.FormEvent) => {
    e.preventDefault();
    
    try {
      const newJobData: Partial<Job> = {
        customer_name: form.customer_name,
        dealer_id: Number(form.dealer_id),
        vin: form.vin,
        job_type: form.job_type as any,
        parts_status: form.parts_status as any,
        est_hours: Number(form.est_hours),
        so_number: form.so_number,
        due_date: form.due_date,
        status: form.so_number ? 'ACTIVE' : 'PENDING_INTAKE', // Simple logic for now
        created_at: new Date().toISOString()
      };

      const createdJob = await jobsService.create(newJobData);
      
      // Update local state (optimistic or confirmed)
      setJobs([createdJob, ...jobs]);
      
      // Notify parent
      if (onJobCreated) {
        onJobCreated(createdJob);
      }

      // Reset form
      setForm({
        customer_name: '',
        dealer_id: '',
        vin: '',
        job_type: 'UPFIT',
        parts_status: 'NOT_READY',
        est_hours: 1,
        so_number: '',
        due_date: ''
      });
    } catch (error) {
      console.error("Failed to create job:", error);
      // Ideally show error toast
    }
  };

  const stats = {
    pending: jobs.filter(j => j.status === 'PENDING_INTAKE').length,
    needs_so: jobs.filter(j => j.status === 'NEEDS_SO').length,
    active: jobs.filter(j => j.status === 'ACTIVE').length,
  };

  const renderJobList = (status: string, title: string, count: number) => (
    <div className="bg-white rounded-xl shadow-sm overflow-hidden mb-4">
      <button 
        onClick={() => toggleSection(status)}
        className="w-full px-6 py-4 flex items-center justify-between bg-white hover:bg-gray-50 transition-colors"
      >
        <div className="flex items-center gap-3">
          <h3 className="font-bold text-sm text-slate-700 uppercase tracking-wide">{title}</h3>
          <span className="bg-slate-100 text-slate-600 text-xs font-bold px-2 py-0.5 rounded-full">{count}</span>
        </div>
        <span className={`material-symbols-outlined text-slate-400 transition-transform ${expandedSection === status ? 'rotate-180' : ''}`}>
          expand_more
        </span>
      </button>
      
      {expandedSection === status && (
        <div className="border-t border-slate-100">
          {jobs.filter(j => j.status === status).length === 0 ? (
            <div className="p-8 text-center text-slate-400 text-sm italic">No jobs found</div>
          ) : (
            <table className="w-full text-sm text-left">
              <thead className="bg-slate-50 text-slate-500 font-medium border-b border-slate-100">
                <tr>
                  <th className="px-6 py-3">Customer</th>
                  <th className="px-6 py-3">VIN</th>
                  <th className="px-6 py-3">Dealer</th>
                  <th className="px-6 py-3">Type</th>
                  <th className="px-6 py-3">Est. Hours</th>
                  <th className="px-6 py-3">Date</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {jobs.filter(j => j.status === status).map(job => (
                  <tr key={job.id} className="hover:bg-slate-50 transition-colors">
                    <td className="px-6 py-3 font-medium text-slate-900">{job.customer_name}</td>
                    <td className="px-6 py-3 font-mono text-slate-600">{job.vin}</td>
                    <td className="px-6 py-3 text-slate-600">{dealers.find(d => d.id === job.dealer_id)?.name || 'Unknown'}</td>
                    <td className="px-6 py-3">
                      <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700">
                        {job.job_type}
                      </span>
                    </td>
                    <td className="px-6 py-3 text-slate-600">{job.est_hours}</td>
                    <td className="px-6 py-3 text-slate-500">{new Date(job.created_at).toLocaleDateString()}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      )}
    </div>
  );

  return (
    <div className="flex-1 overflow-y-auto bg-[#EAE8DC] p-8">
      {/* Header */}
      <div className="flex justify-between items-start mb-8">
        <div>
          <h1 className="text-3xl font-bold text-slate-800 mb-2">Customer Service</h1>
          <p className="text-slate-600">Complete intake and assign SO#s for incoming jobs.</p>
        </div>
        <div className="flex gap-4">
          <div className="bg-white p-4 rounded-xl shadow-sm min-w-[140px]">
            <div className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Pending Intake</div>
            <div className="text-3xl font-bold text-slate-900">{stats.pending}</div>
          </div>
          <div className="bg-white p-4 rounded-xl shadow-sm min-w-[140px]">
            <div className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Needs SO#</div>
            <div className="text-3xl font-bold text-slate-900">{stats.needs_so}</div>
          </div>
        </div>
      </div>

      {/* Create Job Form */}
      <div className="bg-white rounded-xl shadow-sm p-6 mb-8">
        <div className="flex justify-between items-center mb-6">
          <h2 className="text-sm font-bold text-slate-700 uppercase tracking-wide">Create Manual Job</h2>
          <button className="text-slate-400 hover:text-slate-600">
            <span className="material-symbols-outlined">more_horiz</span>
          </button>
        </div>

        <form onSubmit={handleCreateJob}>
          <div className="grid grid-cols-12 gap-4 mb-4">
            <div className="col-span-3">
              <label className="block text-xs font-medium text-slate-500 mb-1">Customer</label>
              <input 
                type="text" 
                className="w-full border-slate-200 rounded-md text-sm focus:ring-primary focus:border-primary"
                placeholder="Enter customer name"
                value={form.customer_name}
                onChange={e => setForm({...form, customer_name: e.target.value})}
                required
              />
            </div>
            <div className="col-span-3">
              <label className="block text-xs font-medium text-slate-500 mb-1">Dealer</label>
              <select 
                className="w-full border-slate-200 rounded-md text-sm focus:ring-primary focus:border-primary"
                value={form.dealer_id}
                onChange={e => setForm({...form, dealer_id: e.target.value})}
                required
              >
                <option value="">Select...</option>
                {dealers.map(d => (
                  <option key={d.id} value={d.id}>{d.name}</option>
                ))}
              </select>
            </div>
            <div className="col-span-3">
              <label className="block text-xs font-medium text-slate-500 mb-1">VIN</label>
              <input 
                type="text" 
                className="w-full border-slate-200 rounded-md text-sm focus:ring-primary focus:border-primary"
                placeholder="VIN (required unless Portal Job)"
                value={form.vin}
                onChange={e => setForm({...form, vin: e.target.value})}
                required
              />
            </div>
            <div className="col-span-1">
              <label className="block text-xs font-medium text-slate-500 mb-1">Job Type</label>
              <select 
                className="w-full border-slate-200 rounded-md text-sm focus:ring-primary focus:border-primary"
                value={form.job_type}
                onChange={e => setForm({...form, job_type: e.target.value})}
              >
                <option value="UPFIT">UPFIT</option>
                <option value="REPAIR">REPAIR</option>
                <option value="WARRANTY">WARRANTY</option>
              </select>
            </div>
            <div className="col-span-1">
              <label className="block text-xs font-medium text-slate-500 mb-1">Parts Status</label>
              <select 
                className="w-full border-slate-200 rounded-md text-sm focus:ring-primary focus:border-primary"
                value={form.parts_status}
                onChange={e => setForm({...form, parts_status: e.target.value})}
              >
                <option value="NOT_READY">NOT_READY</option>
                <option value="PARTIAL">PARTIAL</option>
                <option value="READY">READY</option>
              </select>
            </div>
            <div className="col-span-1">
              <label className="block text-xs font-medium text-slate-500 mb-1">Est. Hours</label>
              <input 
                type="number" 
                className="w-full border-slate-200 rounded-md text-sm focus:ring-primary focus:border-primary"
                value={form.est_hours}
                onChange={e => setForm({...form, est_hours: Number(e.target.value)})}
                min="0.5"
                step="0.5"
              />
            </div>
          </div>

          <div className="grid grid-cols-12 gap-4 items-end">
            <div className="col-span-4">
              <label className="block text-xs font-medium text-slate-500 mb-1">SO# (optional)</label>
              <input 
                type="text" 
                className="w-full border-slate-200 rounded-md text-sm focus:ring-primary focus:border-primary"
                placeholder="S-ORD######"
                value={form.so_number}
                onChange={e => setForm({...form, so_number: e.target.value})}
              />
            </div>
            <div className="col-span-4">
              <label className="block text-xs font-medium text-slate-500 mb-1">Due Date (optional)</label>
              <input 
                type="date" 
                className="w-full border-slate-200 rounded-md text-sm focus:ring-primary focus:border-primary"
                value={form.due_date}
                onChange={e => setForm({...form, due_date: e.target.value})}
              />
            </div>
            <div className="col-span-4 flex justify-end">
              <button 
                type="submit"
                className="bg-slate-700 hover:bg-slate-800 text-white font-bold py-2 px-6 rounded-md text-sm flex items-center gap-2 transition-colors"
              >
                <span className="material-symbols-outlined text-sm">add_circle</span>
                Create Job
              </button>
            </div>
          </div>
        </form>
      </div>

      {/* Lists */}
      <div className="space-y-4">
        {renderJobList('PENDING_INTAKE', 'Pending Intake — Portal Jobs', stats.pending)}
        {renderJobList('NEEDS_SO', 'Needs SO# — Manual Jobs', stats.needs_so)}
        
        {/* Active Jobs Card (Separate Style per image) */}
        <div className="bg-white rounded-xl shadow-sm overflow-hidden">
           <button 
            onClick={() => toggleSection('ACTIVE')}
            className="w-full px-6 py-4 flex items-center justify-between bg-white hover:bg-gray-50 transition-colors"
          >
            <div className="flex items-center gap-3">
              <h3 className="font-bold text-sm text-slate-700 uppercase tracking-wide">Active Jobs</h3>
              <span className="bg-slate-100 text-slate-600 text-xs font-bold px-2 py-0.5 rounded-full">{stats.active}</span>
            </div>
            <span className={`material-symbols-outlined text-slate-400 transition-transform ${expandedSection === 'ACTIVE' ? 'rotate-180' : ''}`}>
              expand_more
            </span>
          </button>
          
          {expandedSection === 'ACTIVE' && (
             <div className="border-t border-slate-100">
               {/* Same table structure for now */}
               {jobs.filter(j => j.status === 'ACTIVE').length === 0 ? (
                 <div className="p-8 text-center text-slate-400 text-sm italic">No active jobs</div>
               ) : (
                 <table className="w-full text-sm text-left">
                   <thead className="bg-slate-50 text-slate-500 font-medium border-b border-slate-100">
                     <tr>
                       <th className="px-6 py-3">Customer</th>
                       <th className="px-6 py-3">VIN</th>
                       <th className="px-6 py-3">SO#</th>
                       <th className="px-6 py-3">Dealer</th>
                       <th className="px-6 py-3">Type</th>
                       <th className="px-6 py-3">Status</th>
                     </tr>
                   </thead>
                   <tbody className="divide-y divide-slate-100">
                     {jobs.filter(j => j.status === 'ACTIVE').map(job => (
                       <tr key={job.id} className="hover:bg-slate-50 transition-colors">
                         <td className="px-6 py-3 font-medium text-slate-900">{job.customer_name}</td>
                         <td className="px-6 py-3 font-mono text-slate-600">{job.vin}</td>
                         <td className="px-6 py-3 font-mono text-slate-600">{job.so_number || '-'}</td>
                         <td className="px-6 py-3 text-slate-600">{dealers.find(d => d.id === job.dealer_id)?.name || 'Unknown'}</td>
                         <td className="px-6 py-3">
                           <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700">
                             {job.job_type}
                           </span>
                         </td>
                         <td className="px-6 py-3">
                            <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-50 text-green-700">
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
      </div>
    </div>
  );
}
