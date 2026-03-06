import React, { useMemo, useState } from 'react';
import { Bom, BomLine, Dealer } from '../types';
import { calculateBomTotals } from '../utils/pricing';
import { CloneBomModal } from './CloneBomModal';

interface BomEditorProps {
  bom: Bom | null;
  lines: BomLine[];
  dealers: Dealer[];
  selectedDealerId: number | null;
  onDealerChange: (dealerId: number | null) => void;
  onHeaderChange: (field: keyof Bom, value: any) => void;
  onLineChange: (lineId: number, field: keyof BomLine, value: any) => void;
  onAddLine: () => void;
  onDeleteLine: (lineId: number) => void;
  onCloneBom: (mode: 'revision' | 'new_bom', newBomNo?: string) => void;
}

export function BomEditor({ 
  bom, 
  lines, 
  dealers, 
  selectedDealerId,
  onDealerChange,
  onHeaderChange,
  onLineChange,
  onAddLine,
  onDeleteLine,
  onCloneBom
}: BomEditorProps) {
  const [isCloneModalOpen, setIsCloneModalOpen] = useState(false);

  const selectedDealer = useMemo(() => 
    dealers.find(d => d.id === selectedDealerId) || null, 
    [dealers, selectedDealerId]
  );

  const totals = useMemo(() => {
    if (!bom) return null;
    return calculateBomTotals(lines, bom, selectedDealer);
  }, [bom, lines, selectedDealer]);

  if (!bom) {
    return (
      <div className="flex-1 flex items-center justify-center bg-background-light text-sage/40">
        <div className="text-center">
          <span className="material-symbols-outlined text-6xl mb-4">inventory_2</span>
          <p className="text-lg font-medium">Select a BOM to edit</p>
        </div>
      </div>
    );
  }

  return (
    <div className="flex-1 flex flex-col h-full overflow-hidden bg-background-light relative">
      {/* Clone Modal */}
      {isCloneModalOpen && (
        <CloneBomModal 
          isOpen={isCloneModalOpen}
          onClose={() => setIsCloneModalOpen(false)}
          onConfirm={onCloneBom}
          currentBom={bom}
        />
      )}

      {/* Header */}
      <header className="h-16 flex items-center justify-between px-8 bg-white/50 backdrop-blur-sm border-b border-sage/10 sticky top-0 z-10 flex-shrink-0">
        <div className="flex items-center gap-6">
          <h2 className="font-technical font-bold text-xl text-sage uppercase tracking-tight">
            {bom.bom_no} <span className="text-sage/40 mx-2">/</span> {bom.name}
          </h2>
          <span className={`
            text-xs font-bold px-2 py-1 rounded uppercase tracking-wider
            ${bom.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-sage/10 text-sage/60'}
          `}>
            {bom.revision}
          </span>
        </div>
        <div className="flex items-center gap-4">
          <select 
            className="bg-white border border-sage/20 rounded-lg px-3 py-1.5 text-sm text-sage focus:ring-2 focus:ring-primary/20 focus:border-primary/50"
            value={selectedDealerId || ''}
            onChange={(e) => onDealerChange(e.target.value ? Number(e.target.value) : null)}
          >
            <option value="">Select Dealer Context...</option>
            {dealers.map(d => (
              <option key={d.id} value={d.id}>{d.name} ({d.market_weight})</option>
            ))}
          </select>
          <div className="h-8 w-px bg-sage/10"></div>
          
          <button 
            onClick={() => setIsCloneModalOpen(true)}
            className="text-sage/60 hover:text-primary font-bold text-xs uppercase tracking-wider px-3 py-2 rounded-lg hover:bg-sage/5 transition-colors flex items-center gap-2"
          >
            <span className="material-symbols-outlined text-lg">content_copy</span>
            Clone / Revise
          </button>

          <button className="bg-primary hover:bg-primary/90 text-white font-bold py-2 px-4 rounded-lg text-sm transition-colors shadow-sm cursor-pointer">
            Save Changes
          </button>
        </div>
      </header>

      {/* Main Content */}
      <div className="flex-1 overflow-y-auto p-8 space-y-8 pb-32">
        {/* BOM Header Fields */}
        <div className="bg-white p-6 rounded-xl shadow-sm border border-sage/5 grid grid-cols-1 md:grid-cols-3 gap-6">
          <div>
            <label className="block text-xs font-bold text-sage/60 uppercase tracking-widest mb-2">Install Hours</label>
            <input 
              type="number" 
              className="w-full bg-sage/5 border-none rounded-lg px-4 py-2 text-sage font-technical font-bold focus:ring-2 focus:ring-primary/20 transition-all"
              value={bom.install_hours}
              onChange={(e) => onHeaderChange('install_hours', parseFloat(e.target.value) || 0)}
              step="0.1"
              min="0"
            />
          </div>
          <div>
            <label className="block text-xs font-bold text-sage/60 uppercase tracking-widest mb-2">Shop Supply Units</label>
            <input 
              type="number" 
              className="w-full bg-sage/5 border-none rounded-lg px-4 py-2 text-sage font-technical font-bold focus:ring-2 focus:ring-primary/20 transition-all"
              value={bom.shop_supply_units}
              onChange={(e) => onHeaderChange('shop_supply_units', parseFloat(e.target.value) || 0)}
              step="1"
              min="0"
            />
          </div>
          <div className="flex items-end justify-end">
             {/* Placeholder for more header actions */}
          </div>
        </div>

        {/* Lines Table */}
        <div className="bg-white rounded-xl shadow-sm border border-sage/5 overflow-hidden">
          <div className="px-6 py-5 border-b border-sage/5 flex items-center justify-between bg-white">
            <h3 className="font-technical font-bold text-lg text-sage uppercase tracking-tight">Line Items</h3>
            <button 
              onClick={onAddLine}
              className="px-4 py-2 bg-sage/5 text-sage text-xs font-bold rounded-lg hover:bg-sage/10 transition-colors uppercase flex items-center gap-2 cursor-pointer"
            >
              <span className="material-symbols-outlined text-sm">add</span> Add Part
            </button>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="bg-sage/5">
                  <th className="px-6 py-4 text-xs font-bold text-sage/60 uppercase tracking-widest w-16">Type</th>
                  <th className="px-6 py-4 text-xs font-bold text-sage/60 uppercase tracking-widest">SKU</th>
                  <th className="px-6 py-4 text-xs font-bold text-sage/60 uppercase tracking-widest">Description</th>
                  <th className="px-6 py-4 text-xs font-bold text-sage/60 uppercase tracking-widest w-24 text-right">Qty</th>
                  <th className="px-6 py-4 text-xs font-bold text-sage/60 uppercase tracking-widest w-32 text-right">Unit Whsle</th>
                  <th className="px-6 py-4 text-xs font-bold text-sage/60 uppercase tracking-widest w-32 text-right">Total Whsle</th>
                  <th className="px-6 py-4 text-xs font-bold text-sage/60 uppercase tracking-widest w-32 text-right">Unit Retail</th>
                  <th className="px-6 py-4 text-xs font-bold text-sage/60 uppercase tracking-widest w-32 text-right">Total Retail</th>
                  <th className="px-6 py-4 text-xs font-bold text-sage/60 uppercase tracking-widest w-16"></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-sage/5">
                {lines.map(line => {
                  // Calculate display values for this row
                  const unitRetail = line.override_retail ?? (line.unit_retail * 1.10);
                  const unitWholesale = line.override_wholesale ?? (unitRetail * 0.80);
                  
                  return (
                    <tr key={line.id} className="hover:bg-sage/5 transition-colors group">
                      <td className="px-6 py-4">
                        <span className={`
                          inline-flex px-2 py-1 rounded text-[10px] font-black uppercase tracking-wider
                          ${line.line_type === 'part' ? 'bg-primary/10 text-primary' : 'bg-sage/10 text-sage'}
                        `}>
                          {line.line_type}
                        </span>
                      </td>
                      <td className="px-6 py-4">
                        <input 
                          type="text"
                          className="w-full bg-transparent border-none p-0 font-technical font-bold text-sage text-sm focus:ring-0 placeholder:text-sage/30"
                          value={line.sku}
                          onChange={(e) => onLineChange(line.id, 'sku', e.target.value)}
                          placeholder="SKU"
                        />
                      </td>
                      <td className="px-6 py-4">
                        <input 
                          type="text"
                          className="w-full bg-transparent border-none p-0 text-sm text-sage/80 focus:ring-0 placeholder:text-sage/30"
                          value={line.description}
                          onChange={(e) => onLineChange(line.id, 'description', e.target.value)}
                          placeholder="Description"
                        />
                      </td>
                      <td className="px-6 py-4 text-right">
                        <input 
                          type="number"
                          className="w-full bg-transparent border-none p-0 text-sm font-technical text-sage text-right focus:ring-0"
                          value={line.qty}
                          onChange={(e) => onLineChange(line.id, 'qty', parseFloat(e.target.value) || 0)}
                          step="1"
                          min="0"
                        />
                      </td>
                      
                      {/* Wholesale Columns (Calculated) */}
                      <td className="px-6 py-4 text-sm font-technical text-sage/60 text-right">
                        ${unitWholesale.toFixed(2)}
                      </td>
                      <td className="px-6 py-4 text-sm font-technical text-sage/60 font-bold text-right">
                        ${(unitWholesale * line.qty).toFixed(2)}
                      </td>

                      {/* Retail Columns */}
                      <td className="px-6 py-4 text-right">
                        <div className="flex items-center justify-end gap-1">
                          <span className="text-sage/40 text-xs">$</span>
                          <input 
                            type="number"
                            className="w-24 bg-transparent border-none p-0 text-sm font-technical text-sage/60 text-right focus:ring-0"
                            value={line.unit_retail}
                            onChange={(e) => onLineChange(line.id, 'unit_retail', parseFloat(e.target.value) || 0)}
                            step="0.01"
                            min="0"
                          />
                        </div>
                        <div className="text-[10px] text-sage/40 text-right">
                          (Vendor)
                        </div>
                      </td>
                      <td className="px-6 py-4 text-sm font-technical text-sage font-bold text-right">
                        ${(unitRetail * line.qty).toFixed(2)}
                      </td>
                      
                      <td className="px-6 py-4 text-right">
                        <button 
                          onClick={() => onDeleteLine(line.id)}
                          className="text-sage/40 hover:text-rose-500 transition-colors opacity-0 group-hover:opacity-100 cursor-pointer"
                        >
                          <span className="material-symbols-outlined text-lg">delete</span>
                        </button>
                      </td>
                    </tr>
                  );
                })}
                {lines.length === 0 && (
                  <tr>
                    <td colSpan={7} className="px-6 py-8 text-center text-sage/40 italic">
                      No line items added yet.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* Totals Panel (Sticky Footer) */}
      {totals && (
        <div className="bg-white border-t border-sage/10 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)] p-6 z-20">
          <div className="max-w-7xl mx-auto grid grid-cols-2 md:grid-cols-4 gap-8">
            <div>
              <p className="text-xs font-bold text-sage/40 uppercase tracking-widest mb-1">Parts Total</p>
              <div className="flex justify-between items-baseline">
                <span className="text-sm text-sage/60">Wholesale</span>
                <span className="font-technical font-bold text-sage">${totals.parts.wholesale.toFixed(2)}</span>
              </div>
              <div className="flex justify-between items-baseline">
                <span className="text-sm text-sage/60">Retail</span>
                <span className="font-technical font-bold text-sage">${totals.parts.retail.toFixed(2)}</span>
              </div>
            </div>
            
            <div>
              <p className="text-xs font-bold text-sage/40 uppercase tracking-widest mb-1">Labor & Shop</p>
              <div className="flex justify-between items-baseline">
                <span className="text-sm text-sage/60">Labor (Ret)</span>
                <span className="font-technical font-bold text-sage">${totals.labor.retail.toFixed(2)}</span>
              </div>
              <div className="flex justify-between items-baseline">
                <span className="text-sm text-sage/60">Shop (Ret)</span>
                <span className="font-technical font-bold text-sage">${totals.shop.retail.toFixed(2)}</span>
              </div>
            </div>

            <div className="bg-sage/5 rounded-lg p-3">
              <p className="text-xs font-bold text-sage/40 uppercase tracking-widest mb-1">Installed Total</p>
              <div className="flex justify-between items-baseline mb-1">
                <span className="text-xs font-bold text-sage/60 uppercase">Wholesale</span>
                <span className="font-technical font-bold text-sage">${totals.total.wholesale.toFixed(2)}</span>
              </div>
              <div className="flex justify-between items-baseline">
                <span className="text-xs font-bold text-primary uppercase">Retail</span>
                <span className="font-technical font-black text-xl text-primary">${totals.total.retail.toFixed(2)}</span>
              </div>
            </div>

            <div className="flex flex-col justify-center items-end">
              <p className="text-xs font-bold text-sage/40 uppercase tracking-widest mb-1">Est. Margin</p>
              <span className="font-technical font-black text-3xl text-emerald-600 tracking-tighter">
                ${totals.margin.toFixed(2)}
              </span>
              <span className="text-xs font-bold text-emerald-700/60 uppercase tracking-wide">
                {totals.total.retail > 0 
                  ? ((totals.margin / totals.total.retail) * 100).toFixed(1) 
                  : '0.0'}%
              </span>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
