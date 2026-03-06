import React, { useState } from 'react';
import { Item } from '../types';

interface ItemsDashboardProps {
  items: Item[];
}

export function ItemsDashboard({ items }: ItemsDashboardProps) {
  const [activeTab, setActiveTab] = useState('All Items');
  const [searchTerm, setSearchTerm] = useState('');

  const filteredItems = items.filter(item => {
    if (activeTab === 'Parts' && item.category !== 'Part') return false;
    if (activeTab === 'Labor' && item.category !== 'Labor') return false;
    if (activeTab === 'Fees' && item.category !== 'Fee') return false;
    return item.name.toLowerCase().includes(searchTerm.toLowerCase()) || item.sku.toLowerCase().includes(searchTerm.toLowerCase());
  });

  return (
    <div className="flex-1 overflow-y-auto bg-[#EAE8DC] p-8 font-sans">
      <div className="flex justify-between items-center mb-8">
        <div>
          <h1 className="text-3xl font-bold text-slate-900">Item Master</h1>
          <p className="text-slate-600">Manage parts, labor codes, and fees.</p>
        </div>
        <button className="bg-slate-700 hover:bg-slate-800 text-white font-bold py-2 px-4 rounded shadow-sm flex items-center gap-2">
          <span className="material-symbols-outlined text-sm">add</span>
          New Item
        </button>
      </div>

      <div className="bg-white rounded-xl shadow-sm overflow-hidden">
        {/* Controls */}
        <div className="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
          <div className="flex gap-1 bg-slate-200 p-1 rounded-lg">
            {['All Items', 'Parts', 'Labor', 'Fees'].map((tab) => (
              <button
                key={tab}
                onClick={() => setActiveTab(tab)}
                className={`px-4 py-1.5 text-xs font-bold rounded-md transition-all
                  ${activeTab === tab 
                    ? 'bg-white text-slate-900 shadow-sm' 
                    : 'text-slate-500 hover:text-slate-700'}`}
              >
                {tab}
              </button>
            ))}
          </div>
          <div className="relative">
            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 material-symbols-outlined text-sm">search</span>
            <input 
              type="text" 
              placeholder="Search SKU or Name..." 
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="pl-9 pr-4 py-1.5 border border-slate-200 rounded-md text-sm w-64 focus:ring-primary focus:border-primary"
            />
          </div>
        </div>

        {/* Table */}
        <table className="w-full text-sm text-left">
          <thead className="bg-white text-slate-500 font-bold text-xs uppercase tracking-wider border-b border-slate-100">
            <tr>
              <th className="px-6 py-4">SKU</th>
              <th className="px-6 py-4">Description</th>
              <th className="px-6 py-4">Category</th>
              <th className="px-6 py-4">Vendor</th>
              <th className="px-6 py-4 text-right">Cost</th>
              <th className="px-6 py-4 text-right">Retail</th>
              <th className="px-6 py-4 text-center">Stock</th>
              <th className="px-6 py-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {filteredItems.map((item) => (
              <tr key={item.id} className="hover:bg-slate-50 transition-colors group">
                <td className="px-6 py-4 font-mono text-slate-600 font-bold">{item.sku}</td>
                <td className="px-6 py-4 font-medium text-slate-900">{item.name}</td>
                <td className="px-6 py-4">
                  <span className={`inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide
                    ${item.category === 'Part' ? 'bg-blue-50 text-blue-700 border border-blue-100' : 
                      item.category === 'Labor' ? 'bg-orange-50 text-orange-700 border border-orange-100' : 
                      'bg-slate-100 text-slate-600 border border-slate-200'}`}>
                    {item.category}
                  </span>
                </td>
                <td className="px-6 py-4 text-slate-500">{item.vendor}</td>
                <td className="px-6 py-4 text-right font-mono text-slate-600">${item.cost.toFixed(2)}</td>
                <td className="px-6 py-4 text-right font-mono font-bold text-slate-900">${item.retail.toFixed(2)}</td>
                <td className="px-6 py-4 text-center">
                  {item.stock !== null ? (
                    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold
                      ${item.stock < 5 ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'}`}>
                      {item.stock}
                    </span>
                  ) : (
                    <span className="text-slate-300">-</span>
                  )}
                </td>
                <td className="px-6 py-4 text-right opacity-0 group-hover:opacity-100 transition-opacity">
                  <div className="flex justify-end gap-2">
                    <button className="text-slate-400 hover:text-blue-600">
                      <span className="material-symbols-outlined text-lg">edit</span>
                    </button>
                    <button className="text-slate-400 hover:text-red-600">
                      <span className="material-symbols-outlined text-lg">delete</span>
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        
        <div className="px-6 py-4 border-t border-slate-100 text-xs text-slate-500 flex justify-between items-center">
          <span>Showing {filteredItems.length} items</span>
          <div className="flex gap-2">
            <button className="px-3 py-1 border border-slate-200 rounded hover:bg-slate-50 disabled:opacity-50" disabled>Previous</button>
            <button className="px-3 py-1 border border-slate-200 rounded hover:bg-slate-50">Next</button>
          </div>
        </div>
      </div>
    </div>
  );
}
