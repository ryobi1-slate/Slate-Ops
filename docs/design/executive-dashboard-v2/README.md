# Slate Ops Executive Dashboard V2 — Design Handoff

This folder is a cleaned Claude Design handoff for the Slate Ops Executive Dashboard.
It is not production plugin code by itself. Use it as a source for Claude Code to port into the existing Slate Ops plugin.

## Recommended repo location

```text
docs/design/executive-dashboard-v2/
```

## Folder map

```text
preview/
  executive-dashboard.html      Full static preview from Claude Design
  executive-dashboard.css       Preview CSS
  executive-dashboard.js        Preview JS

wp-handoff/
  templates/executive-dashboard.php
  includes/class-slate-ops-executive.php
  assets/css/executive-dashboard.css
  assets/js/executive-dashboard.js
```

## What changed in this cleanup

- Removed CS Dashboard naming from file names and comments.
- Renamed the page scope from `.so-cs` to `.so-exec`.
- Renamed the PHP class from `Slate_Ops_CS` to `Slate_Ops_Executive`.
- Renamed handoff files to Executive Dashboard naming.
- Corrected Slate token fallbacks.
- Removed instructions to create `/ops/cs-dashboard`.
- Removed instructions to add a “CS Dashboard” sidebar item.
- Excluded prototype-only JSX files, data.js, styles.css, screenshots, and tweak-panel files.

## Slate tokens

Use these tokens in the implementation:

```css
--slate-sage: #404f4b;
--slate-sand: #e1dfc8;
--slate-arches: #d86b19;
--slate-redwood: #0f342a;
--slate-black: #000000;
--slate-white: #ffffff;
--page-bg: #f3f1e6;
--card: #ffffff;
```

## Implementation notes for Claude Code

This should be ported into the existing Executive dashboard area unless you intentionally choose to create a new Executive sub-route.
Do not create `/ops/cs-dashboard`.
Do not add a CS Dashboard sidebar entry.

Use the Purchasing pattern:

- server-rendered first
- PHP template
- page-specific scoped CSS
- page-specific vanilla JS
- no React
- no Babel
- no build step

Do not enqueue fonts on this page. The Ops shell already loads Roboto Flex and JetBrains Mono.
Do not include `<html>`, `<head>`, or `<body>` in the PHP template.

## Suggested target production files

```text
templates/pages/executive-dashboard.php
includes/class-slate-ops-executive.php
assets/css/executive-dashboard.css
assets/js/executive-dashboard.js
```

If the current Executive page is still rendered by the monolithic React bundle, use this handoff as the source for replacing or adding a server-rendered Executive surface under the same routing rules.

## Validation checklist

Run after porting:

```bash
php -l slate-ops.php
php -l templates/ops-app.php
php -l includes/class-slate-ops-utils.php
php -l includes/class-slate-ops-executive.php
php -l templates/pages/executive-dashboard.php
node --check assets/js/executive-dashboard.js
grep -R "<<<<<<<\|=======\|>>>>>>>" -n .
```

Also confirm:

- Executive dashboard renders inside the existing Ops shell.
- No duplicate app shell appears.
- No CS dashboard route or sidebar item is added.
- JS disabled still shows the dashboard content.
- Tabs and filters enhance behavior only.
