import React, { useMemo } from 'react';

type Role = 'tech' | 'cs' | 'supervisor' | 'admin' | 'fallback';

interface MenuItem {
  label: string;
  href: string;
  icon: string;
  active?: boolean;
}

export function Sidebar() {
  const userRole = useMemo((): Role => {
    if (typeof window === 'undefined' || !window.slateOpsSettings) return 'admin'; // Default to admin for dev
    
    const roles = window.slateOpsSettings.user.roles || [];
    
    if (roles.includes('administrator')) return 'admin';
    if (roles.includes('slate_supervisor')) return 'supervisor';
    if (roles.includes('slate_cs')) return 'cs';
    if (roles.includes('slate_tech')) return 'tech';
    
    return 'fallback';
  }, []);

  const menuItems = useMemo((): MenuItem[] => {
    const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';
    const isActive = (href: string) => currentPath.includes(href);

    switch (userRole) {
      case 'tech':
        return [
          { label: 'My Jobs', href: '/ops/tech', icon: 'engineering', active: isActive('/ops/tech') },
          { label: 'All Jobs', href: '/ops/jobs', icon: 'work', active: isActive('/ops/jobs') },
        ];
      case 'cs':
        return [
          { label: 'CS Dashboard', href: '/ops/cs', icon: 'support_agent', active: isActive('/ops/cs') },
          { label: 'Jobs', href: '/ops/jobs', icon: 'work', active: isActive('/ops/jobs') },
          { label: 'Schedule', href: '/ops/schedule', icon: 'calendar_month', active: isActive('/ops/schedule') },
          { label: 'Quotes', href: '/ops/quotes', icon: 'request_quote', active: isActive('/ops/quotes') },
          { label: 'Dealers', href: '/ops/dealers', icon: 'store', active: isActive('/ops/dealers') },
        ];
      case 'supervisor':
        return [
          { label: 'Supervisor Dash', href: '/ops/supervisor', icon: 'dashboard', active: isActive('/ops/supervisor') },
          { label: 'Tech View', href: '/ops/tech', icon: 'engineering', active: isActive('/ops/tech') },
          { label: 'Jobs', href: '/ops/jobs', icon: 'work', active: isActive('/ops/jobs') },
          { label: 'Schedule', href: '/ops/schedule', icon: 'calendar_month', active: isActive('/ops/schedule') },
          { label: 'QC', href: '/ops/qc', icon: 'fact_check', active: isActive('/ops/qc') },
          { label: 'Items', href: '/ops/items', icon: 'category', active: isActive('/ops/items') },
          { label: 'BOMs', href: '/ops/bom', icon: 'inventory_2', active: isActive('/ops/bom') },
        ];
      case 'admin':
        return [
          { label: 'Exec Dashboard', href: '/ops/exec', icon: 'dashboard', active: isActive('/ops/exec') },
          { label: 'CS', href: '/ops/cs', icon: 'support_agent', active: isActive('/ops/cs') },
          { label: 'Supervisor', href: '/ops/supervisor', icon: 'visibility', active: isActive('/ops/supervisor') },
          { label: 'Jobs', href: '/ops/jobs', icon: 'work', active: isActive('/ops/jobs') },
          { label: 'Schedule', href: '/ops/schedule', icon: 'calendar_month', active: isActive('/ops/schedule') },
          { label: 'QC', href: '/ops/qc', icon: 'fact_check', active: isActive('/ops/qc') },
          { label: 'Items', href: '/ops/items', icon: 'category', active: isActive('/ops/items') },
          { label: 'BOMs', href: '/ops/bom', icon: 'inventory_2', active: isActive('/ops/bom') },
          { label: 'Quotes', href: '/ops/quotes', icon: 'request_quote', active: isActive('/ops/quotes') },
          { label: 'Dealers', href: '/ops/dealers', icon: 'store', active: isActive('/ops/dealers') },
          { label: 'Settings', href: '/ops/settings', icon: 'settings', active: isActive('/ops/settings') },
        ];
      default: // Fallback
        return [
          { label: 'Exec Dashboard', href: '/ops/exec', icon: 'dashboard', active: isActive('/ops/exec') },
          { label: 'Jobs', href: '/ops/jobs', icon: 'work', active: isActive('/ops/jobs') },
        ];
    }
  }, [userRole]);

  return (
    <aside className="w-64 bg-sage text-white flex flex-col h-full shadow-xl z-20 flex-shrink-0">
      <div className="p-6 flex items-center gap-3">
        <div className="size-10 bg-white/10 rounded-lg flex items-center justify-center">
          <span className="material-symbols-outlined text-white text-3xl">precision_manufacturing</span>
        </div>
        <div className="flex flex-col">
          <h1 className="font-technical font-bold text-lg leading-tight uppercase tracking-wider">Slate Built</h1>
          <p className="text-white/60 text-xs font-medium uppercase tracking-widest">Ops Portal</p>
        </div>
      </div>
      
      <div className="px-6 pb-2">
        <div className="text-xs font-bold uppercase tracking-widest text-white/40 mb-2">
          {userRole.toUpperCase()} VIEW
        </div>
      </div>

      <nav className="flex-1 px-4 space-y-1 overflow-y-auto">
        {menuItems.map((item, index) => (
          <a 
            key={index}
            href={item.href}
            className={`
              flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all
              ${item.active 
                ? 'bg-white/20 border-l-4 border-white text-white font-bold' 
                : 'text-white/70 hover:bg-white/10 hover:text-white font-semibold'}
            `}
          >
            <span className={`material-symbols-outlined ${item.active ? 'fill-1' : ''}`}>{item.icon}</span>
            <span className="text-sm">{item.label}</span>
          </a>
        ))}
      </nav>

      <div className="p-4 border-t border-white/10">
        <div className="flex items-center gap-3 px-2">
          <div className="size-8 rounded-full bg-primary flex items-center justify-center text-xs font-bold">
            {typeof window !== 'undefined' && window.slateOpsSettings?.user?.name?.charAt(0) || 'U'}
          </div>
          <div className="flex flex-col overflow-hidden">
            <span className="text-sm font-bold truncate">
              {typeof window !== 'undefined' && window.slateOpsSettings?.user?.name || 'User'}
            </span>
            <span className="text-xs text-white/50 truncate">
              {userRole}
            </span>
          </div>
        </div>
      </div>
    </aside>
  );
}
