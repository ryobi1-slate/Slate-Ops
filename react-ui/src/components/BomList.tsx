import React, { useState } from 'react';
import { BomSummary } from '../types';

interface BomListProps {
  boms: BomSummary[];
  selectedId: number | null;
  onSelect: (id: number) => void;
}

export function BomList({ boms, selectedId, onSelect }: BomListProps) {
  const [searchTerm, setSearchTerm] = useState('');

  const filteredBoms = boms.filter(bom => 
    bom.bom_no.toLowerCase().includes(searchTerm.toLowerCase()) ||
    bom.name.toLowerCase().includes(searchTerm.toLowerCase())
  );

  return (
    <div className="w-80 bg-white border-r border-sage/10 flex flex-col h-full">
      <div className="p-4 border-b border-sage/10">
        <h2 className="font-technical font-bold text-lg text-sage uppercase tracking-tight mb-3">BOMs</h2>
        <div className="relative">
          <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-sage/40 text-lg">search</span>
          <input 
            className="w-full bg-sage/5 border-none rounded-lg pl-9 pr-4 py-2 text-sm focus:ring-2 focus:ring-primary/20 focus:bg-white transition-all placeholder:text-sage/40" 
            placeholder="Search BOMs..." 
            type="text"
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
          />
        </div>
      </div>
      
      <div className="flex-1 overflow-y-auto p-2 space-y-2">
        {filteredBoms.map(bom => (
          <div 
            key={bom.id}
            onClick={() => onSelect(bom.id)}
            className={`
              p-3 rounded-lg cursor-pointer transition-all border
              ${selectedId === bom.id 
                ? 'bg-primary/5 border-primary/30 shadow-sm' 
                : 'bg-white border-transparent hover:bg-sage/5 hover:border-sage/10'}
            `}
          >
            <div className="flex justify-between items-start mb-1">
              <span className={`font-technical font-bold text-sm ${selectedId === bom.id ? 'text-primary' : 'text-sage'}`}>
                {bom.bom_no}
              </span>
              <span className={`
                text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider
                ${bom.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-sage/10 text-sage/60'}
              `}>
                {bom.status}
              </span>
            </div>
            <h3 className="text-sm font-medium text-sage/80 truncate mb-2">{bom.name}</h3>
            <div className="flex items-center gap-2">
              <span className="text-[10px] font-bold bg-sage/10 text-sage/60 px-1.5 py-0.5 rounded">
                {bom.revision}
              </span>
              <span className="text-[10px] text-sage/40">
                Updated {new Date(bom.updated_at).toLocaleDateString()}
              </span>
            </div>
          </div>
        ))}
        
        {filteredBoms.length === 0 && (
          <div className="p-8 text-center text-sage/40">
            <span className="material-symbols-outlined text-4xl mb-2">search_off</span>
            <p className="text-sm">No BOMs found</p>
          </div>
        )}
      </div>
    </div>
  );
}
