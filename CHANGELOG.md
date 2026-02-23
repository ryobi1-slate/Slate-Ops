<!DOCTYPE html>
<html lang="en">
<head>
<title>Slate Upfit – CS</title>

<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto+Flex:opsz,wght@8..144,100..1000&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        'sand-bg': '#e1dfc8',
        'sage-sidebar': '#404f4b',
        'arches-orange': '#d86b19'
      },
      fontFamily: { sans: ['Roboto Flex','sans-serif'] }
    }
  }
}
</script>
<link rel="stylesheet" href="assets/app.css">

<style>body{font-family:'Roboto Flex',sans-serif;}</style>
</head>
<body class="bg-sand-bg min-h-screen flex">

<aside class="w-64 bg-sage-sidebar flex-shrink-0 flex flex-col h-screen sticky top-0">
  <div class="p-6 border-b border-white/10 flex items-center justify-start">
    <img src="assets/slate-logo-white.svg" alt="Slate Upfit" class="h-6 w-auto">
  </div>

  <nav class="mt-6 flex-grow px-4 space-y-2">
    <a class="flex items-center px-4 py-3 rounded-lg transition-colors text-white hover:bg-white/10 font-medium" href="dashboard.html">Dashboard</a>
    <a class="flex items-center px-4 py-3 rounded-lg transition-colors bg-white/20 font-bold" href="cs.html">CS</a>
    <a class="flex items-center px-4 py-3 rounded-lg transition-colors text-white hover:bg-white/10 font-medium" href="supervisor.html">Supervisor</a>
    <a class="flex items-center px-4 py-3 rounded-lg transition-colors text-white hover:bg-white/10 font-medium" href="jobs.html">Jobs</a>
    <a class="flex items-center px-4 py-3 rounded-lg transition-colors text-white hover:bg-white/10 font-medium" href="qc.html">QC</a>
    <a class="flex items-center px-4 py-3 rounded-lg transition-colors text-white hover:bg-white/10 font-medium" href="schedule.html">Schedule</a>
    <a class="flex items-center px-4 py-3 rounded-lg transition-colors text-white hover:bg-white/10 font-medium" href="settings.html">Settings</a>
  </nav>

  <div class="p-6 mt-auto border-t border-white/10">
    <div class="flex items-center">
      <div class="w-8 h-8 rounded-full bg-white/10 border border-white/10 flex items-center justify-center text-white font-bold mr-3">JD</div>
      <div class="overflow-hidden">
        <p class="text-white text-sm font-semibold truncate">John Doe</p>
        <p class="text-white/60 text-xs truncate">Ops Manager</p>
      </div>
    </div>
  </div>
</aside>

<main class="flex-grow p-8 overflow-y-auto">
<header class="mb-8 flex items-start justify-between gap-6">
  <div>
    <h1 class="text-3xl font-black text-sage-sidebar tracking-tight">CS</h1>
    <p class="text-slate-600">Create and update jobs manually during Phase 1.</p>
  </div>
  
</header>
<div class='max-w-3xl'><section class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
  <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex items-start justify-between gap-6">
    <div>
      <h2 class="text-lg font-bold text-sage-sidebar">CS Intake</h2>
      <p class="kicker mt-1">Manual entry - Phase 1</p>
    </div>
    
  </div>
  <div class="p-6">
    
<form class="space-y-6">
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="space-y-1">
      <label class="block text-sm font-bold text-slate-700">SO#</label>
      <input class="w-full border-slate-300 rounded-md shadow-sm focus:border-sage-sidebar focus:ring-sage-sidebar" placeholder="S-ORD10456" />
    </div>
    <div class="space-y-1">
      <label class="block text-sm font-bold text-slate-700">Job Type</label>
      <select class="w-full border-slate-300 rounded-md shadow-sm focus:border-sage-sidebar focus:ring-sage-sidebar">
        <option>UPFIT</option><option>RV_BUILD</option><option>PARTS_ONLY</option><option>SERVICE</option><option>WARRANTY</option>
      </select>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="space-y-1">
      <label class="block text-sm font-bold text-slate-700">Dealer</label>
      <select class="w-full border-slate-300 rounded-md shadow-sm focus:border-sage-sidebar focus:ring-sage-sidebar">
        <option>Mercedes-Benz of Wilsonville</option>
        <option>Mercedes-Benz of Seattle</option>
        <option>Mercedes-Benz of Anchorage</option>
      </select>
    </div>
    <div class="space-y-1">
      <label class="block text-sm font-bold text-slate-700">Parts Status</label>
      <select class="w-full border-slate-300 rounded-md shadow-sm focus:border-sage-sidebar focus:ring-sage-sidebar">
        <option>NOT_READY</option><option>PARTIAL</option><option>READY</option><option>HOLD</option>
      </select>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="space-y-1">
      <label class="block text-sm font-bold text-slate-700">Priority</label>
      <select class="w-full border-slate-300 rounded-md shadow-sm focus:border-sage-sidebar focus:ring-sage-sidebar">
        <option>3</option><option>1</option><option>2</option><option>4</option><option>5</option>
      </select>
    </div>
    <div class="space-y-1">
      <label class="block text-sm font-bold text-slate-700">Estimated Hours</label>
      <input class="w-full border-slate-300 rounded-md shadow-sm focus:border-sage-sidebar focus:ring-sage-sidebar" placeholder="30" />
    </div>
    <div class="space-y-1">
      <label class="block text-sm font-bold text-slate-700">VIN</label>
      <input class="w-full border-slate-300 rounded-md shadow-sm focus:border-sage-sidebar focus:ring-sage-sidebar" placeholder="Optional for Phase 1" />
    </div>
  </div>

  <div class="space-y-1">
    <label class="block text-sm font-bold text-slate-700">Notes</label>
    <textarea class="w-full border-slate-300 rounded-md shadow-sm focus:border-sage-sidebar focus:ring-sage-sidebar" rows="4" placeholder="Anything CS needs the shop to know..."></textarea>
  </div>
</form>

  </div>
  <div class="px-6 py-4 bg-slate-50 flex justify-end">
      <div class="flex items-center gap-3">
        <button class="text-sage-sidebar border border-sage-sidebar hover:bg-sage-sidebar/5 px-4 py-2 rounded-md text-sm font-bold transition-colors">Save draft</button>
        <button class="bg-sage-sidebar text-white px-6 py-2 rounded-lg font-bold hover:bg-slate-700 transition-colors shadow-sm">Create job</button>
      </div>
    </div>
</section></div>
</main>
</body>
</html>
