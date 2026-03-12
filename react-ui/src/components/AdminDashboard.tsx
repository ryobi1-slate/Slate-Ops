import React, { useEffect, useState } from 'react';
import { Job } from '../types';

interface AdminDashboardProps {
  jobs: Job[];
}

interface OpsUser {
  id: number;
  name: string;
  email: string;
  ops_role: string;
}

const API_ROOT = typeof window !== 'undefined' && window.slateOpsSettings?.api?.root || '/wp-json/slate-ops/v1';
const NONCE   = typeof window !== 'undefined' && window.slateOpsSettings?.api?.nonce || '';

function apiFetch(path: string, opts: RequestInit = {}) {
  return fetch(`${API_ROOT}${path}`, {
    ...opts,
    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE, ...(opts.headers || {}) },
  });
}

export function AdminDashboard({ jobs }: AdminDashboardProps) {
  const [users, setUsers] = useState<OpsUser[]>([]);
  const [saving, setSaving] = useState<number | null>(null);
  const [flash, setFlash] = useState<{ id: number; ok: boolean } | null>(null);

  useEffect(() => {
    apiFetch('/users')
      .then(r => r.json())
      .then(d => setUsers(d.users || []))
      .catch(() => {});
  }, []);

  const activeJobs    = jobs.filter(j => !['COMPLETE','COMPLETED','NEEDS_SO','PENDING_INTAKE'].includes(j.status)).length;
  const inProgress    = jobs.filter(j => j.status === 'IN_PROGRESS').length;
  const pendingQc     = jobs.filter(j => j.status === 'PENDING_QC').length;
  const unscheduled   = jobs.filter(j => ['UNSCHEDULED','READY_FOR_SCHEDULING'].includes(j.status)).length;

  async function changeRole(userId: number, newRole: string) {
    setSaving(userId);
    try {
      const res = await apiFetch(`/users/${userId}/role`, {
        method: 'POST',
        body: JSON.stringify({ role: newRole }),
      });
      if (res.ok) {
        setUsers(prev => prev.map(u => u.id === userId ? { ...u, ops_role: newRole } : u));
        setFlash({ id: userId, ok: true });
        setTimeout(() => setFlash(null), 2000);
      } else {
        setFlash({ id: userId, ok: false });
        setTimeout(() => setFlash(null), 2000);
      }
    } catch {
      setFlash({ id: userId, ok: false });
      setTimeout(() => setFlash(null), 2000);
    } finally {
      setSaving(null);
    }
  }

  const wpAdminUrl = typeof window !== 'undefined' ? `${window.location.origin}/wp-admin/users.php` : '#';

  return (
    <div className="flex-1 overflow-y-auto bg-[#EAE8DC] p-8 font-sans">
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-slate-900 uppercase tracking-tight">Admin</h1>
        <p className="text-slate-500 text-sm mt-1">Ops role management and system overview.</p>
      </div>

      {/* Stats Row */}
      <div className="grid grid-cols-5 gap-4 mb-8">
        {[
          { label: 'Active Jobs',  value: activeJobs },
          { label: 'In Progress',  value: inProgress },
          { label: 'Pending QC',   value: pendingQc },
          { label: 'Unscheduled',  value: unscheduled },
          { label: 'Users',        value: users.length },
        ].map(stat => (
          <div key={stat.label} className="bg-white rounded-xl shadow-sm p-6 text-center">
            <div className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">{stat.label}</div>
            <div className="text-4xl font-bold text-slate-900">{stat.value}</div>
          </div>
        ))}
      </div>

      {/* Users & Ops Roles */}
      <div className="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
        <div className="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
          <h2 className="font-bold text-sm text-slate-700 uppercase tracking-wide">Users &amp; Ops Roles</h2>
          <a
            href={wpAdminUrl}
            target="_blank"
            rel="noreferrer"
            className="flex items-center gap-1 px-3 py-1.5 border border-slate-200 rounded text-xs font-bold text-slate-600 hover:bg-slate-50"
          >
            WP Admin
            <span className="material-symbols-outlined text-sm">open_in_new</span>
          </a>
        </div>

        <table className="w-full text-sm text-left">
          <thead className="text-slate-400 font-bold text-[10px] uppercase tracking-wider border-b border-slate-100 bg-slate-50">
            <tr>
              <th className="px-6 py-3">Name</th>
              <th className="px-6 py-3">Email</th>
              <th className="px-6 py-3">Ops Role</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {users.map(user => (
              <tr key={user.id} className="hover:bg-slate-50 transition-colors">
                <td className="px-6 py-4 font-medium text-slate-900">{user.name}</td>
                <td className="px-6 py-4 text-slate-500">{user.email}</td>
                <td className="px-6 py-4">
                  <div className="flex items-center gap-2">
                    <select
                      value={user.ops_role || ''}
                      onChange={e => changeRole(user.id, e.target.value)}
                      disabled={saving === user.id}
                      className="border border-slate-200 rounded-md px-3 py-2 text-sm focus:ring-primary focus:border-primary w-44 disabled:opacity-50"
                    >
                      <option value="">No Role</option>
                      <option value="tech">Tech</option>
                      <option value="cs">CS</option>
                      <option value="supervisor">Supervisor</option>
                      <option value="admin">Admin</option>
                    </select>
                    {saving === user.id && (
                      <span className="material-symbols-outlined text-slate-400 animate-spin text-base">progress_activity</span>
                    )}
                    {flash?.id === user.id && (
                      <span className={`material-symbols-outlined text-base ${flash.ok ? 'text-green-500' : 'text-red-500'}`}>
                        {flash.ok ? 'check_circle' : 'error'}
                      </span>
                    )}
                  </div>
                </td>
              </tr>
            ))}
            {users.length === 0 && (
              <tr>
                <td colSpan={3} className="px-6 py-8 text-center text-slate-400 text-sm">Loading users...</td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
