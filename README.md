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
