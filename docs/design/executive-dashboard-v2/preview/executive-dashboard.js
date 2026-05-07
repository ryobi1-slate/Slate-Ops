/* Slate Ops Executive Dashboard V2 — vanilla JS */
(function () {
  'use strict';

  // ---------- Data ----------
  const techs = [
    { name: "info (system)", state: "warn",
      assigned: 3, active: 3, today: "0h 0m", week: "108h 53m",
      est: "38h 0m", logged: "108h 53m", variance: "+70h 53m",
      capture: 287, captureFlag: "crit",
      flags: [{t:"DATA ISSUE",k:"crit"},{t:"OVER ESTIMATE",k:"warn"}],
      note: "System account — bulk imported entries" },
    { name: "Jake Austin", state: "crit",
      assigned: 3, active: 3, today: "0h 0m", week: "0h 0m",
      est: "56h 0m", logged: "0h 0m", variance: "-56h 0m",
      capture: 0, captureFlag: "crit",
      flags: [{t:"NO TIME TODAY",k:"crit"},{t:"NO TIME THIS WEEK",k:"crit"}],
      note: "No clock-in events found" },
    { name: "Marcos Rivera", state: "ok",
      assigned: 5, active: 2, today: "5h 12m", week: "32h 40m",
      est: "44h 0m", logged: "39h 18m", variance: "-4h 42m",
      capture: 89, captureFlag: "ok", flags: [], note: "" },
    { name: "Devin Walsh", state: "warn",
      assigned: 4, active: 3, today: "1h 30m", week: "18h 05m",
      est: "30h 0m", logged: "26h 12m", variance: "-3h 48m",
      capture: 87, captureFlag: "ok",
      flags: [{t:"OPEN TIMER",k:"warn"}],
      note: "Open timer 4h 12m on S-ORD101422" },
    { name: "Priya Shah", state: "ok",
      assigned: 3, active: 1, today: "6h 02m", week: "29h 14m",
      est: "22h 0m", logged: "23h 40m", variance: "+1h 40m",
      capture: 107, captureFlag: "ok", flags: [], note: "" },
    { name: "Ben Okafor", state: "warn",
      assigned: 2, active: 2, today: "0h 0m", week: "11h 22m",
      est: "16h 0m", logged: "11h 22m", variance: "-4h 38m",
      capture: 71, captureFlag: "warn",
      flags: [{t:"NO TIME TODAY",k:"warn"}],
      note: "Last entry yesterday 4:18 PM" },
    { name: "Alyssa Tran", state: "ok",
      assigned: 4, active: 2, today: "4h 48m", week: "27h 30m",
      est: "28h 0m", logged: "26h 02m", variance: "-1h 58m",
      capture: 93, captureFlag: "ok", flags: [], note: "" },
  ];

  const jobs = [
    { so: "S-ORD101395", cust: "Sigma Industrial",      status: "BLOCKED",  lead: null, est: "5h 0m",  logged: "0h 0m",  variance: "-5h 0m",  used: 0,   risk: "crit",  reason: "Awaiting parts" },
    { so: "S-ORD101363", cust: "Cory Hua",              status: "BLOCKED",  lead: "Marcos Rivera",  est: "5h 0m", logged: "0h 0m", variance: "-5h 0m", used: 0, risk: "crit", reason: "Customer approval" },
    { so: "S-ORD101389", cust: "Sigma Industrial",      status: "BLOCKED",  lead: null, est: "5h 0m",  logged: "0h 0m",  variance: "-5h 0m",  used: 0,   risk: "crit",  reason: "Engineering" },
    { so: "S-ORD101391", cust: "Sigma Industrial",      status: "BLOCKED",  lead: null, est: "5h 0m",  logged: "0h 0m",  variance: "-5h 0m",  used: 0,   risk: "crit",  reason: "Awaiting parts" },
    { so: "S-ORD101392", cust: "Sigma Industrial",      status: "BLOCKED",  lead: null, est: "5h 0m",  logged: "0h 0m",  variance: "-5h 0m",  used: 0,   risk: "watch", reason: "Awaiting parts" },
    { so: "S-ORD101422", cust: "Northstar Equipment",   status: "IN PROGRESS", lead: "Devin Walsh", est: "12h 0m", logged: "8h 14m", variance: "-3h 46m", used: 68,  risk: "watch", reason: "Open timer" },
    { so: "S-ORD101418", cust: "Bridgeline Logistics",  status: "IN PROGRESS", lead: "Priya Shah",  est: "8h 0m",  logged: "9h 18m", variance: "+1h 18m", used: 116, risk: "watch", reason: "Over estimate" },
    { so: "S-ORD101411", cust: "Mercedes-Benz of Wilsonville", status: "QC", lead: "Alyssa Tran", est: "6h 0m",  logged: "5h 42m", variance: "-0h 18m", used: 95,  risk: "ok",    reason: "" },
    { so: "S-ORD101353", cust: "Gresham Toyota",        status: "COMPLETE", lead: null, est: "5h 0m",  logged: "0h 0m",  variance: "-5h 0m",  used: 0,   risk: "crit",  reason: "Closed with zero time" },
    { so: "S-ORD101365", cust: "Gresham Toyota",        status: "COMPLETE", lead: null, est: "5h 0m",  logged: "0h 0m",  variance: "-5h 0m",  used: 0,   risk: "crit",  reason: "Closed with zero time" },
    { so: "S-ORD101401", cust: "Mercedes-Benz Wilson.", status: "BLOCKED",  lead: null, est: "1h 0m",  logged: "0h 0m",  variance: "-1h 0m",  used: 0,   risk: "watch", reason: "Tech capacity" },
    { so: "S-ORD101393", cust: "Sigma Industrial",      status: "BLOCKED",  lead: null, est: "5h 0m",  logged: "0h 0m",  variance: "-5h 0m",  used: 0,   risk: "watch", reason: "Awaiting parts" },
    { so: "S-ORD101394", cust: "Sigma Industrial",      status: "BLOCKED",  lead: null, est: "5h 0m",  logged: "0h 0m",  variance: "-5h 0m",  used: 0,   risk: "crit",  reason: "QC rework" },
    { so: "S-ORD101396", cust: "Sigma Industrial",      status: "READY FOR BUILD", lead: "Ben Okafor",   est: "6h 0m",  logged: "0h 0m",  variance: "-6h 0m",  used: 0, risk: "ok", reason: "" },
    { so: "S-ORD101427", cust: "Sunset Transit",        status: "READY FOR BUILD", lead: "Marcos Rivera",est: "10h 0m", logged: "0h 0m",  variance: "-10h 0m", used: 0, risk: "ok", reason: "" },
    { so: "S-ORD101430", cust: "Pearl District Auto",   status: "SCHEDULED",       lead: "Priya Shah",   est: "4h 0m",  logged: "0h 0m",  variance: "-4h 0m",  used: 0, risk: "ok", reason: "" },
    { so: "S-ORD417524", cust: "Test Account",          status: "COMPLETE", lead: null, est: "1h 0m", logged: "0h 0m", variance: "-1h 0m", used: 0, risk: "watch", reason: "Test record" },
  ];

  const blockers = [
    { so: "S-ORD101389", cust: "Sigma Industrial", reason: "Engineering",      dept: "Eng",     owner: "—",          days: 7,  next: "Assign engineer",       sev: "crit" },
    { so: "S-ORD101394", cust: "Sigma Industrial", reason: "QC rework",        dept: "QC",      owner: "A. Tran",    days: 5,  next: "Schedule rework slot",  sev: "crit" },
    { so: "S-ORD101395", cust: "Sigma Industrial", reason: "Parts",            dept: "Parts",   owner: "M. Rivera",  days: 4,  next: "Vendor follow-up",      sev: "crit" },
    { so: "S-ORD101391", cust: "Sigma Industrial", reason: "Parts",            dept: "Parts",   owner: "M. Rivera",  days: 4,  next: "Vendor follow-up",      sev: "crit" },
    { so: "S-ORD101363", cust: "Cory Hua",         reason: "Customer approval",dept: "CS",      owner: "—",          days: 3,  next: "CS to call customer",   sev: "watch" },
    { so: "S-ORD101392", cust: "Sigma Industrial", reason: "Parts",            dept: "Parts",   owner: "M. Rivera",  days: 2,  next: "Vendor follow-up",      sev: "watch" },
    { so: "S-ORD101393", cust: "Sigma Industrial", reason: "Parts",            dept: "Parts",   owner: "—",          days: 2,  next: "Assign owner",          sev: "watch" },
    { so: "S-ORD101401", cust: "Mercedes-Benz",    reason: "Tech capacity",    dept: "Shop",    owner: "B. Okafor",  days: 1,  next: "Reschedule",            sev: "normal" },
    { so: "S-ORD101396", cust: "Sigma Industrial", reason: "Parts",            dept: "Parts",   owner: "M. Rivera",  days: 1,  next: "Vendor follow-up",      sev: "normal" },
  ];

  // ---------- Helpers ----------
  const $ = (sel, root) => (root || document).querySelector(sel);
  const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));
  const esc = (s) => String(s == null ? "" : s).replace(/[&<>"']/g, c =>
    ({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;" }[c]));

  function initials(name) {
    if (!name) return "—";
    return name.split(" ").filter(Boolean).map(s => s[0]).join("").slice(0, 2).toUpperCase();
  }
  function avatar(name, kind) {
    const k = kind ? ` ${kind}` : "";
    return `<span class="ava-sm${k}">${esc(initials(name))}</span>`;
  }
  function tag(kind, text, withArch) {
    const arch = withArch && (kind === "crit" || kind === "warn" || kind === "watch") ? `<span class="arch"></span>` : "";
    return `<span class="tag ${kind}">${arch}<span class="dot"></span>${esc(text)}</span>`;
  }
  function meter(value, opts) {
    opts = opts || {};
    const max = Math.max(opts.max || 120, value);
    const target = opts.target || 100;
    const pct = Math.min(100, (value / max) * 100);
    const tgt = Math.min(100, (target / max) * 100);
    const k = opts.kind || (value < target * 0.6 ? "crit" : value < target * 0.85 ? "warn" : "");
    return `<span class="meter">
      <span class="bar ${k}"><i style="width:${pct}%"></i><span class="target" style="left:${tgt}%"></span></span>
      <b>${value}%</b>
    </span>`;
  }
  const statusTag = (status) => {
    const m = {
      "BLOCKED":"crit","IN PROGRESS":"ok","QC":"watch","COMPLETE":"muted",
      "READY FOR BUILD":"normal","SCHEDULED":"normal","INTAKE":"normal",
    };
    return tag(m[status] || "normal", status);
  };

  // ---------- Tabs ----------
  function bindTabs() {
    $$(".tab").forEach(t => t.addEventListener("click", () => {
      const id = t.dataset.tab;
      $$(".tab").forEach(x => x.classList.toggle("active", x === t));
      $$(".tab-pane").forEach(p => p.classList.toggle("active", p.id === "pane-" + id));
      window.scrollTo({ top: 0, behavior: "instant" });
    }));
  }

  // ---------- Filters (job page) ----------
  function bindJobFilters() {
    const root = $("#pane-jobs");
    if (!root) return;
    const overChip = $("[data-filter='over']", root);
    const missChip = $("[data-filter='missing']", root);
    const riskSel = $("[data-filter='risk']", root);
    const search = $("[data-filter='search']", root);

    function apply() {
      const over = overChip.classList.contains("on");
      const miss = missChip.classList.contains("on");
      const risk = riskSel.value;
      const q = (search.value || "").trim().toLowerCase();
      let shown = 0;
      $$("[data-job]", root).forEach(row => {
        const j = JSON.parse(row.dataset.job);
        let ok = true;
        if (over && !j.variance.startsWith("+")) ok = false;
        if (miss && j.logged !== "0h 0m") ok = false;
        if (risk !== "all" && j.risk !== risk) ok = false;
        if (q && !(j.so.toLowerCase().includes(q) || j.cust.toLowerCase().includes(q))) ok = false;
        row.style.display = ok ? "" : "none";
        if (ok) shown++;
      });
      const counter = $("[data-jobs-count]", root);
      if (counter) counter.textContent = shown + " of " + jobs.length;
    }

    overChip.addEventListener("click", () => { overChip.classList.toggle("on"); apply(); });
    missChip.addEventListener("click", () => { missChip.classList.toggle("on"); apply(); });
    riskSel.addEventListener("change", apply);
    search.addEventListener("input", apply);
  }

  // ---------- Render Job Performance table ----------
  function renderJobsTable() {
    const tbody = $("#jobs-tbody");
    if (!tbody) return;
    tbody.innerHTML = jobs.map(j => `
      <tr data-job='${esc(JSON.stringify(j))}'>
        <td class="so">${esc(j.so)}</td>
        <td class="cust">${esc(j.cust)}</td>
        <td>${statusTag(j.status)}</td>
        <td>${j.lead ? avatar(j.lead) + esc(j.lead) : '<span class="bad">Unassigned</span>'}</td>
        <td class="num">${esc(j.est)}</td>
        <td class="num">${j.logged === "0h 0m" ? '<span class="bad">0h 0m</span>' : esc(j.logged)}</td>
        <td class="num" style="${j.variance.startsWith("+") ? "color:var(--warn)" : ""}">${esc(j.variance)}</td>
        <td>${meter(j.used, { max: Math.max(120, j.used || 100) })}</td>
        <td>${
          j.risk === "crit"  ? tag("crit",  j.reason || "Critical", true) :
          j.risk === "watch" ? tag("watch", j.reason || "Watch") :
                               tag("ok",    "On track")
        }</td>
      </tr>
    `).join("");
  }

  // ---------- Render Tech Performance table ----------
  function renderTechsTable() {
    const tbody = $("#techs-tbody");
    if (!tbody) return;
    tbody.innerHTML = techs.map(t => {
      const variantColor =
        t.variance.startsWith("+") ? "color:var(--warn)" :
        t.variance === "-56h 0m" ? "color:var(--risk)" : "";
      return `
      <tr>
        <td class="tech">
          ${avatar(t.name, t.state)}${esc(t.name)}
          ${t.note ? `<span class="sub">${esc(t.note)}</span>` : ""}
        </td>
        <td class="num">${t.assigned}</td>
        <td class="num">${t.active}</td>
        <td class="num">${t.today === "0h 0m" ? '<span class="bad">0h 0m</span>' : esc(t.today)}</td>
        <td class="num">${t.week === "0h 0m" ? '<span class="bad">0h 0m</span>' : esc(t.week)}</td>
        <td class="num">${esc(t.est)}</td>
        <td class="num">${esc(t.logged)}</td>
        <td class="num" style="${variantColor}">${esc(t.variance)}</td>
        <td>${meter(t.capture, { kind: t.captureFlag, max: Math.max(120, t.capture) })}</td>
        <td>${
          t.flags.length === 0
            ? tag("ok", "On track")
            : `<div style="display:flex;gap:4px;flex-wrap:wrap">` +
                t.flags.map(f => tag(f.k, f.t)).join("") + `</div>`
        }</td>
      </tr>`;
    }).join("");
  }

  // ---------- Render Blockers table ----------
  function renderBlockersTable() {
    const tbody = $("#blockers-tbody");
    if (!tbody) return;
    tbody.innerHTML = blockers.map(b => {
      const total = 7;
      const stripes = Array.from({length: total}).map((_, i) => {
        const cls = i < b.days ? `on ${b.sev === "crit" ? "crit" : b.sev === "watch" ? "warn" : ""}` : "";
        return `<i class="${cls}"></i>`;
      }).join("");
      const sev = b.sev === "crit"   ? tag("crit",  "Critical", true) :
                  b.sev === "watch"  ? tag("watch", "Watch", true) :
                                       tag("normal","Normal");
      return `
      <tr>
        <td class="so">${esc(b.so)}</td>
        <td class="cust">${esc(b.cust)}</td>
        <td>${esc(b.reason)}</td>
        <td>${esc(b.dept)}</td>
        <td>${b.owner === "—" ? '<span class="bad">Unassigned</span>' : esc(b.owner)}</td>
        <td><div class="age-cell"><span class="num">${b.days}d</span><span class="stripes">${stripes}</span></div></td>
        <td class="action">${esc(b.next)} →</td>
        <td>${sev}</td>
      </tr>`;
    }).join("");
  }

  // ---------- Init ----------
  document.addEventListener("DOMContentLoaded", () => {
    bindTabs();
    renderJobsTable();
    renderTechsTable();
    renderBlockersTable();
    bindJobFilters();
  });
})();
