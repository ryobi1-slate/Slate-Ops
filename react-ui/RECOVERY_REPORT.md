# Slate Ops React Recovery Build Report

## Bottom Line

Historical editable React source from `3d8c9397edee2234ac916aa12fbd1f024bea0fbd` was restored on recovery branch `codex/react-source-recovery`.

The restored source is a usable recovery starting point, but it did not build in this environment because `npm` is not installed or available on `PATH`. The bundled runtime provides Node and pnpm only; the restored project uses `package-lock.json`, so pnpm was not used to avoid changing the package-manager state.

The restored source should not be treated as current production truth. There are 100 post-removal commits touching `assets/react/app.js` after removal commit `169e29286260ad6228a52ab438ac614c0dabcf1b`.

## Recovery Branch

- Branch: `codex/react-source-recovery`
- Source restored from: `3d8c9397edee2234ac916aa12fbd1f024bea0fbd`
- Removed by: `169e29286260ad6228a52ab438ac614c0dabcf1b`
- Production bundle preserved: yes
- Build output redirected to: `react-ui/recovery-dist/`

## Restored Source Structure

- `react-ui/package.json`
- `react-ui/package-lock.json`
- `react-ui/tsconfig.json`
- `react-ui/vite.config.ts`
- `react-ui/src/App.tsx`
- `react-ui/src/main.tsx`
- `react-ui/src/index.css`
- `react-ui/src/types.ts`
- `react-ui/src/services/api.ts`
- `react-ui/src/utils/pricing.ts`
- Components: `AdminDashboard`, `BomEditor`, `BomList`, `CloneBomModal`, `CsDashboard`, `ExecutiveDashboard`, `ExternalPortalRedirect`, `ItemsDashboard`, `JobsDashboard`, `QcDashboard`, `ScheduleDashboard`, `SettingsDashboard`, `Sidebar`, `SupervisorDashboard`, `TechDashboard`

## Dependency Versions

- Package manager: npm, lockfile version 3
- React: `^19.0.0`
- React DOM: `^19.0.0`
- Vite: `^6.2.0`
- TypeScript: `~5.8.2`
- Tailwind: `^4.1.14`
- Tailwind Vite plugin: `^4.1.14`
- Vite React plugin: `^5.0.4`

## Build Result

`npm ci` could not run because npm is unavailable:

```text
npm: The term 'npm' is not recognized as a name of a cmdlet, function, script file, or executable program.
```

Node is available through the bundled runtime at version `v24.14.0`, but no npm executable or npm CLI package was found. No dependency update, lockfile rewrite, audit fix, or pnpm fallback was performed.

## Historical vs Current Bundle

| Area | Historical Source | Current Bundle | Recovery Gap |
| ---- | ----------------- | -------------- | ------------ |
| Build output | Vite config originally wrote to `../assets/react`; now safely redirected to `react-ui/recovery-dist` | Existing production files remain `assets/react/app.js` and `assets/react/app.css` | Build validation pending npm availability |
| Bundle size | Source tree is 25 files, 392,522 bytes | `app.js` 340,260 bytes; `app.css` 44,774 bytes | Cannot compare generated recovered assets yet |
| Routes | `/ops/cs`, `/ops/bom`, `/ops/qc`, `/ops/exec`, `/ops/jobs`, `/ops/supervisor`, `/ops/settings`, `/ops/schedule`, `/ops/tech`, `/ops/items`, `/ops/quotes`, `/ops/dealers`, `/ops/admin` | `/ops/admin`, `/ops/bom`, `/ops/cs`, `/ops/exec`, `/ops/items`, `/ops/jobs`, `/ops/qc`, `/ops/schedule`, `/ops/settings`, `/ops/supervisor`, `/ops/tech` | Current route set resembles historical route shell, but later behavior differs |
| REST endpoints | `/boms`, `/jobs`, `/items`, `/dealers`, `/time/*`, `/work-centers`, `/scheduler/*`, `/schedule/bulk`, `/qc/inspections` under `slate-ops/v1` | Current bundle contains `/wp-json/slate-ops/v1`, `/boms`, `/jobs`, `/items`, `/dealers`, `/time/*`, `/work-centers`, `/scheduler`, `/schedule/bulk` | Endpoint family is compatible, but request/response expectations need replay audit |
| BOMs | Editable source includes BOM list/editor/clone modal and `utils/pricing.ts` wholesale/retail calculations | Current bundle still contains BOM route string | Source likely recoverable, but current production parity unverified |
| CS | Historical `CsDashboard.tsx` predates Phase 0/Status System v2 work | Many later direct edits add CS split view, queue priority, By Tech, due/notes, status labels, closeout wording | High gap |
| Tech | Historical `TechDashboard.tsx` includes timer, assigned jobs, daily summary | Many later direct edits add mobile polish, pause/switch flow, queue readiness, active crew, closeout handoff | High gap |
| Schedule | Historical `ScheduleDashboard.tsx` and scheduler services exist | Current bundle includes schedule route and later monitor/queue changes | Medium gap |
| Settings/Admin | Historical source includes both modules | Current bundle includes admin role-save fix and settings/admin route strings | Medium gap |
| Styling | Historical source uses Tailwind 4 and Slate palette classes | Current CSS is generated Tailwind output with newer direct visual polish | Medium to high gap |

## API Configuration Pattern

The restored source reads `window.slateOpsSettings.api.root` and `window.slateOpsSettings.api.nonce`, defaults to `/wp-json/slate-ops/v1`, and sends `X-WP-Nonce` on REST calls.

## BOM Implementation Status

BOM source is present and editable: list, editor, clone/revise modal, API service calls, type definitions, and pricing helper. It includes dealer-specific labor/shop supply rates and wholesale/retail part calculations.

## Post-Removal Bundle Change Inventory

Chronological inventory found 100 commits after `169e292` that touched `assets/react/app.js` or `assets/react/app.css`. All observed file changes in the inventory were to `assets/react/app.js`; no post-removal `app.css` commits were found in that range.

| Area | Commits | Representative changes | Source Equivalent | Difficulty | Risk |
| ---- | ------- | ---------------------- | ----------------- | ---------- | ---- |
| Customer Service | 71e33f6, bcf226f, 1698103, fa8c505, d299e5c, ed9ef31, adbc3ce, cfd6b65, 77a11bc, fe2ed85, 3f266dc, 544fed2, 684cf53, 4071b25, b828577, a2e394b, 62e250b, 0d679ea, f3043b1, a53d3a1, c61fdf3, cda3dc3, cbbce66, ea6e23f, 534494e, 1c8963b, 9a40710, 30b7206, 566f933, 461991a, 4fe56f4, 90c8a4a, 9781683, 951a06a | CS job list/editor, create/delete permissions, status normalization, Phase 0 queue, By Tech, due/notes, compact table, closeout labels | Partial old `CsDashboard.tsx` only | High | High |
| Technician | 461a548, 69a9099, 8e1391a, 36cd0c3, 9a7801f, c4e75e9, d41f033, bb81c30, 7493e4c, a8fe13c, 03d549e, 7a284cb, 99350b9, cf89661, ec36dff, ced2bc1, a10bc49, cef6369, 53753c0, be92547, 0813b4e, 7cc53ee, 84660ae, 349a80a, fee1c3c, c0436d1, c62a33f, b20bf94, cb852fd, 150ea25, cec7bc4, 00b8b92, 54ae854, b408d21, caf1c79, 75624d6, 2f1b7cc, 9417c07, 7535f86, acf8d14, c642b17, b859d5e | Mobile timer, queue ordering/readiness, pause/switch, active crew, closeout handoff, mobile active card | Partial old `TechDashboard.tsx` only | High | High |
| Schedule | c753378 | Ready Queue and Scheduled list | Partial old `ScheduleDashboard.tsx` | Medium | Medium |
| Settings/Admin | 4a53575 | Admin role-save stale role cleanup | Partial old `AdminDashboard.tsx` | Medium | Medium |
| Executive | 452f164, fde7e11, 5b3b049, d61736e, 524390f | Executive page structure, KPI tabs, visual parity | Partial old `ExecutiveDashboard.tsx` | Medium | Medium |
| Navigation | 5ce0f53, 783596c | Frontend fallback and role-based landing route | Partial old `App.tsx`/`Sidebar.tsx` | Medium | Medium |
| Styling | 2bcac64, 81a1b07, c9c9abd, 80b7019, 8bdba06, 64f108f | Design tokens, badges, sidebar/logo, Dealer Portal alignment | Tailwind source exists, later polish missing | Medium | Medium |
| Other/Merge safety | 5418121, 4632399, 843bc57, 8550db9, ba6ad23, bc8c94d, d434a66, c79d23d | Status map, merges, conflict-marker cleanup | Some source concepts exist | Unknown | Medium |

## Recommended Recovery Strategy

Strategy B - Restore selectively and migrate routes.

The historical source is structurally close enough to recover some modules, especially BOM, Schedule, Settings/Admin, and perhaps Executive. It is too far behind current CS and Technician production behavior to safely replay as a straight bundle replacement without a significant reconstruction pass.

Suggested route treatment:

- Retain/recover in React first: `/ops/bom`, `/ops/schedule`, `/ops/settings`, `/ops/admin`
- Evaluate after source replay: `/ops/exec`, `/ops/jobs`, `/ops/items`, `/ops/qc`, `/ops/supervisor`
- Treat as high-risk/full-replay: `/ops/cs`, `/ops/tech`
- Continue migration to PHP/vanilla where already established: canonical CS dashboard, supervisor dashboard, quality, purchasing, resource hub

## Estimated Replay Scope

- Commits requiring review: 100
- Likely feature recreations: at least 25
- High-risk gaps: CS Phase 0/Status System v2, Tech pause/switch/closeout/mobile flow, role landing/fallback behavior, status normalization
- Routes requiring full regression testing: `/ops/cs`, `/ops/tech`, `/ops/schedule`, `/ops/admin`, `/ops/settings`, `/ops/bom`, `/ops/exec`

## Files Changed on Recovery Branch

- Restored `react-ui/`
- Modified `react-ui/vite.config.ts` to build into `react-ui/recovery-dist/`
- Modified `.gitignore` to ignore `react-ui/recovery-dist/`
- Added this recovery report

## Safety Confirmation

- `main` was not modified
- Production bundle was not changed
- No deployment occurred
- Generated recovery output was not committed
- No unrelated files were intentionally changed
