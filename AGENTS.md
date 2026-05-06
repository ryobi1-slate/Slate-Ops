# Agent guidelines — Slate Portal

This file is read by coding agents (Jules, Claude Code, Codex). All work must respect these rules.

## Stack
- WordPress custom plugin, PHP 8.x
- WooCommerce + B2BKing Pro — quote system only, NO checkout, NO payments, NO shipping
- Vanilla JS only — no React, no Vue, no jQuery
- Flexbox / Grid only — no floats
- Server-side first; JS only when necessary

## Locked workflow
Products → Product Detail → Quote Builder → Quote → Order Request → Ops

Do not propose alternate orderings. Do not propose retail/checkout flows.

## Data ownership (Platform SOT)
- Quotes: `b2bking_offer` (Dealer-Portal owns)
- Orders: `wp_slate_orders` (Dealer-Portal owns)
- BC integration: `bc_quote_no` at quote stage, `sales_order_no` at order stage
- Ops execution state: Slate-Ops owns (`ops_physical_readiness`, job state)
- `sales_order_no` is the only job number. No parallel numbering schemes.

## Naming + conventions
- All classes, functions, hooks: `Slate_` / `slate_` prefix
- Sentence case in all user-facing strings
- esc_html / esc_attr / esc_url / wp_kses on all output
- `$wpdb->prepare` for all SQL
- URLs resolved via `Slate_Pages::get_url()`, never hardcoded

## Architecture priorities
1. Stability over novelty
2. Clear workflows over flexibility
3. Server-side logic first
4. Clean separation: UI templates / business logic / data handling

## Hard nos
- Do not propose React, Vue, Laravel, Symfony, or any framework migration
- Do not propose checkout, payment, or shipping logic
- Do not invent parallel job numbering
- Do not break the locked workflow
- Do not modify schema for fields the SOTs say are computed
- If a finding contradicts an SOT, the SOT wins — flag and stop

## Source of truth docs
Read `/docs/*SOT*` (markdown or docx) before flagging anything as wrong.

## Maintenance reports
Weekly audit reports live at `/docs/maintenance/REPORT-YYYY-MM-DD.md`. Append a new dated report each run; do not rewrite history.