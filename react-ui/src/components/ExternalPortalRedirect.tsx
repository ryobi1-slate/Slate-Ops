import React from 'react';

export function ExternalPortalRedirect({ moduleName }: { moduleName: string }) {
  return (
    <div className="flex-1 flex flex-col items-center justify-center bg-[#EAE8DC] p-8 font-sans">
      <div className="bg-white p-12 rounded-xl shadow-lg text-center max-w-md w-full">
        <div className="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-6">
          <span className="material-symbols-outlined text-4xl text-slate-400">open_in_new</span>
        </div>
        <h1 className="text-2xl font-bold text-slate-900 mb-2">{moduleName} Portal</h1>
        <p className="text-slate-500 mb-8">
          This module is managed in the external Dealer Portal. You will be redirected to a new window.
        </p>
        <button 
          onClick={() => window.open('https://portal.slatebuilt.com', '_blank')}
          className="w-full bg-slate-800 hover:bg-slate-900 text-white font-bold py-3 px-6 rounded-lg transition-all flex items-center justify-center gap-2"
        >
          Open Dealer Portal
          <span className="material-symbols-outlined text-sm">arrow_forward</span>
        </button>
        <div className="mt-6 pt-6 border-t border-slate-100">
          <button 
            onClick={() => window.history.back()}
            className="text-sm font-bold text-slate-400 hover:text-slate-600"
          >
            Go Back
          </button>
        </div>
      </div>
    </div>
  );
}
