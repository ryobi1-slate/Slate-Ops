// Mock data for the CS Workspace prototype.
const TECHS = [
  { id: 'unassigned', name: 'Unassigned', initials: '—' },
  { id: 'jake',     name: 'Jake Austin',   initials: 'JA' },
  { id: 'info',     name: 'Info',          initials: 'IN' },
  { id: 'marco',    name: 'Marco Vela',    initials: 'MV' },
  { id: 'priya',    name: 'Priya Shah',    initials: 'PS' },
  { id: 'devon',    name: 'Devon Ruiz',    initials: 'DR' },
];

const STATUS = {
  intake:    { label: 'Intake',     tone: 'sand' },
  scheduled: { label: 'Scheduled',  tone: 'info' },
  ready:     { label: 'Ready',      tone: 'sage' },
  inprog:    { label: 'In Progress',tone: 'arches' },
  blocked:   { label: 'Blocked',    tone: 'bad' },
  parts:     { label: 'Parts Hold', tone: 'warn' },
  closeout:  { label: 'Ready for Closeout', tone: 'redwood' },
  build:     { label: 'Build Ready', tone: 'sage' },
};

const PARTS = {
  ready:   { label: 'Ready',   tone: 'sage' },
  partial: { label: 'Partial', tone: 'warn' },
  hold:    { label: 'On Hold', tone: 'bad' },
  none:    { label: '—',       tone: 'muted' },
};

// Seed jobs grouped by tech.
const SEED = [
  { id: 'j1', tech: 'info',    so: 'S-ORD101411', cust: 'Mercedes-Benz of Wilsonville', dealer: 'MBWIL', status: 'closeout', parts: 'ready',   est: 1.0,  due: '2026-05-08', note: 'Awaiting customer pickup window confirmation', vin: 'WDDZF8KB1NA6...', sales: 'R. Cole',   prio: 'high'  },
  { id: 'j2', tech: 'info',    so: 'S-ORD101350', cust: 'Mercedes-Benz of Wilsonville', dealer: 'MBWIL', status: 'closeout', parts: 'ready',   est: 10.0, due: '2026-04-30', note: '', vin: 'W1KZF8DB4NA6...', sales: 'R. Cole', prio: 'normal' },
  { id: 'j3', tech: 'info',    so: 'S-ORD101379', cust: 'Earl Thompson',                dealer: 'MBWIL', status: 'inprog',   parts: 'ready',   est: 27.0, due: '2026-05-12', note: 'PDR + paint blend, customer flexible', vin: '5YJ3E1EA1JF...', sales: 'M. Park', prio: 'normal' },

  { id: 'j4', tech: 'jake',    so: 'S-ORD101372', cust: 'Mercedes-Benz of Anchorage',   dealer: 'MBAK',  status: 'inprog',   parts: 'ready',   est: 53.0, due: '2026-05-15', note: 'Heavy hail — 14 panels mapped',           vin: 'WDDWJ8DB6NF...', sales: 'A. Ng',   prio: 'high' },
  { id: 'j5', tech: 'jake',    so: 'S-ORD101413', cust: 'Mercedes-Benz of Wilsonville', dealer: 'MBWIL', status: 'scheduled',parts: 'partial', est: 2.0,  due: '2026-05-01', note: '', vin: 'WDC0G4KB2NF...', sales: 'R. Cole', prio: 'normal' },
  { id: 'j6', tech: 'jake',    so: 'S-ORD101408', cust: 'Hadi Ahmadi',                  dealer: 'MBWIL', status: 'inprog',   parts: 'ready',   est: 3.0,  due: '2026-05-09', note: 'Door ding cluster — front passenger',     vin: '1HGCM82633A...', sales: 'L. Brun', prio: 'normal' },

  { id: 'j7', tech: 'marco',   so: 'S-ORD101363', cust: 'Cory Hua',                     dealer: 'MBWIL', status: 'blocked',  parts: 'partial', est: 5.0,  due: '2026-05-06', note: 'Customer waiting on insurance adj.',      vin: '1FTFW1ET1EF...', sales: 'M. Park', prio: 'high' },
  { id: 'j8', tech: 'marco',   so: 'S-ORD101364', cust: 'Gresham Toyota',               dealer: 'GRTOY', status: 'build',    parts: 'partial', est: 5.0,  due: '2026-05-11', note: '', vin: '4T1B11HK5JU...', sales: 'A. Ng',   prio: 'normal' },
  { id: 'j9', tech: 'marco',   so: 'S-ORD101390', cust: 'Sigma Lithium',                dealer: 'SIGMA', status: 'build',    parts: 'partial', est: 5.0,  due: '2026-05-14', note: 'Fleet — batch with j8 if possible',        vin: '5N1AT2MV9HC...', sales: 'L. Brun', prio: 'normal' },
  { id: 'j10', tech:'marco',   so: 'S-ORD101361', cust: 'Mercedes-Benz of Wilsonville', dealer: 'MBWIL', status: 'inprog',   parts: 'ready',   est: 46.0, due: '2026-05-22', note: '', vin: 'WDD2J5KB1NA...', sales: 'R. Cole', prio: 'high' },

  { id: 'j11', tech:'priya',   so: 'S-ORD101400', cust: 'Lithia of Tualatin',           dealer: 'LITHTU',status: 'parts',    parts: 'hold',    est: 8.0,  due: '2026-05-13', note: 'Bumper cover ETA 5/12',                   vin: '3VW2K7AJ9FM...', sales: 'M. Park', prio: 'normal' },
  { id: 'j12', tech:'priya',   so: 'S-ORD101401', cust: 'Ron Tonkin Honda',             dealer: 'RTHON', status: 'ready',    parts: 'ready',   est: 4.0,  due: '2026-05-09', note: '',                                       vin: '19XFC2F58JE...', sales: 'A. Ng',   prio: 'normal' },

  { id: 'j13', tech:'unassigned', so: 'S-ORD101415', cust: 'Test',                       dealer: '—',     status: 'intake',   parts: 'none',    est: 1.0,  due: '',           note: 'Intake — needs assignment',               vin: '',                sales: '',         prio: 'high' },
  { id: 'j14', tech:'unassigned', so: 'S-ORD101416', cust: 'Walker Subaru',              dealer: 'WLKSB', status: 'intake',   parts: 'none',    est: 6.0,  due: '',           note: 'New intake from dealer portal',           vin: 'JF2GTAEC1L8...', sales: 'L. Brun', prio: 'normal' },
];

window.WORKSPACE_DATA = { TECHS, STATUS, PARTS, SEED };
