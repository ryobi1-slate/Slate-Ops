import React, { useState } from 'react';
import { Bom } from '../types';

interface CloneBomModalProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: (mode: 'revision' | 'new_bom', newBomNo?: string) => void;
  currentBom: Bom;
}

export function CloneBomModal({ isOpen, onClose, onConfirm, currentBom }: CloneBomModalProps) {
  const [mode, setMode] = useState<'revision' | 'new_bom'>('revision');
  const [newBomNo, setNewBomNo] = useState(`${currentBom.bom_no}-COPY`);

  if (!isOpen) return null;

  const nextRevision = (() => {
    const match = currentBom.revision.match(/R(\d+)/);
    if (match) {
      return `R${parseInt(match[1]) + 1}`;
    }
    return 'R2'; // Default fallback
  })();

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    onConfirm(mode, mode === 'new_bom' ? newBomNo : undefined);
    onClose();
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
      <div className="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
        <div className="px-6 py-4 border-b border-sage/10 flex justify-between items-center bg-sage/5">
          <h3 className="font-technical font-bold text-lg text-sage uppercase tracking-tight">
            Clone / Revise BOM
          </h3>
          <button onClick={onClose} className="text-sage/40 hover:text-sage transition-colors">
            <span className="material-symbols-outlined">close</span>
          </button>
        </div>
        
        <form onSubmit={handleSubmit} className="p-6 space-y-6">
          <div className="space-y-4">
            {/* Option 1: New Revision */}
            <label className={`
              flex items-start gap-3 p-4 rounded-lg border cursor-pointer transition-all
              ${mode === 'revision' ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-sage/20 hover:border-sage/40'}
            `}>
              <input 
                type="radio" 
                name="clone_mode" 
                value="revision"
                checked={mode === 'revision'}
                onChange={() => setMode('revision')}
                className="mt-1 text-primary focus:ring-primary"
              />
              <div>
                <span className="block font-bold text-sage text-sm">New Revision ({nextRevision})</span>
                <span className="block text-xs text-sage/60 mt-1">
                  Create a new version of <strong>{currentBom.bom_no}</strong>. The current version ({currentBom.revision}) will be archived.
                </span>
              </div>
            </label>

            {/* Option 2: New BOM Number */}
            <label className={`
              flex items-start gap-3 p-4 rounded-lg border cursor-pointer transition-all
              ${mode === 'new_bom' ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-sage/20 hover:border-sage/40'}
            `}>
              <input 
                type="radio" 
                name="clone_mode" 
                value="new_bom"
                checked={mode === 'new_bom'}
                onChange={() => setMode('new_bom')}
                className="mt-1 text-primary focus:ring-primary"
              />
              <div className="w-full">
                <span className="block font-bold text-sage text-sm">Clone to New BOM Number</span>
                <span className="block text-xs text-sage/60 mt-1 mb-3">
                  Create a completely separate BOM based on this one.
                </span>
                
                {mode === 'new_bom' && (
                  <div className="mt-2">
                    <label className="block text-[10px] font-bold text-sage/60 uppercase tracking-widest mb-1">
                      New BOM Number
                    </label>
                    <input 
                      type="text" 
                      value={newBomNo}
                      onChange={(e) => setNewBomNo(e.target.value)}
                      className="w-full bg-white border-sage/20 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary"
                      placeholder="e.g. BOM-105"
                      autoFocus
                      required
                    />
                  </div>
                )}
              </div>
            </label>
          </div>

          <div className="flex justify-end gap-3 pt-2">
            <button 
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-sage/60 font-bold text-sm hover:text-sage transition-colors"
            >
              Cancel
            </button>
            <button 
              type="submit"
              className="px-6 py-2 bg-primary hover:bg-primary/90 text-white font-bold rounded-lg text-sm shadow-sm transition-all"
            >
              {mode === 'revision' ? 'Create Revision' : 'Clone BOM'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
