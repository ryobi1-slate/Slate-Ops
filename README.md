{
  "compilerOptions": {
    "target": "ES2022",
    "experimentalDecorators": true,
    "useDefineForClassFields": false,
    "module": "ESNext",
    "lib": [
      "ES2022",
      "DOM",
      "DOM.Iterable"
    ],
    "skipLibCheck": true,
    "types": [
      "node"
    ],
    "moduleResolution": "bundler",
    "isolatedModules": true,
    "moduleDetection": "force",
    "allowJs": true,
    "jsx": "react-jsx",
    "paths": {
      "@/*": [
        "./*"
      ]
    },
    "allowImportingTsExtensions": true,
    "noEmit": true
  }
}
## Local Browser Smoke Tests

Slate Ops includes a local WordPress + browser smoke setup using `wp-env` and Playwright.

1. Install dependencies:
   - `npm install`
2. Start local WordPress (plugin auto-activated from this repo):
   - `npm run wp-env:start`
3. Run JS check:
   - `npm run test:js`
4. Run smoke test:
   - `npm run test:smoke`
5. Stop environment when done:
   - `npm run wp-env:stop`

Default local site URL is `http://localhost:8888`.

Smoke test targets:
- CS route: `/ops/cs`
- Monitor route: `/slate-ops-monitor/`

By default tests use wp-env admin credentials (`admin` / `password`). Override with:
- `WP_ADMIN_USER`
- `WP_ADMIN_PASSWORD`

## Slate Ops Tools (admin)

Tabbed **Tools** admin page (under WP **Tools**) hosting self-registering tool tabs. First tool: a standalone combined-invoice generator for RV financing (manual entry only — no BC / Dealer Portal / order data). Gated to `Slate_Ops_Utils::CAP_CS`.

New classes:
- `Slate_Ops_Tools` (`includes/admin/class-slate-ops-tools.php`) — Tools page shell, `slate_ops_tools_tabs` registry, server-side `?tab=` routing, per-tab capability gate (route + save + strip), save dispatch.
- `Slate_Ops_Finance_Invoice` (`includes/class-slate-ops-finance-invoice.php`) — `slate_finance_invoice` CPT registration + data layer (field definitions, sanitize/save, read, listing, render-time totals).
- `Slate_Ops_Finance_Invoice_Tab` (`includes/admin/class-slate-ops-finance-invoice-tab.php`) — invoice tab: self-registers as the first tab, admin create/edit/list form, nonce- and capability-checked save handler.
