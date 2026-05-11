# CS / Supervisor Dashboard Design Reference

This folder stores the exported CS / Supervisor dashboard design reference.

## Purpose

Use this as a visual reference for future Slate Ops implementation work. It is not production code.

## Rules

- Do not enqueue this HTML in WordPress.
- Do not copy exported CSS into `assets/css/ops-shell.css`.
- Do not treat this as the source of truth for data logic.
- Production work must be built inside the existing Slate Ops PHP, REST, JavaScript, and CSS structure.
- The Queue tab should be adapted from this design and wired to real job records.

## Intended implementation path

The CS Queue should be added to the existing CS dashboard and backed by job records. CS owns queue order. Tech should only read queue order for Current Job, Next Job, and Up Next.

## Source export notes

The uploaded zip included:

- `Operations Dashboard.html` - newest design export
- `Operations Dashboard v1.html` - duplicate / older export
- `Operations Dashboard v1 (full app).html` - duplicate / older export
- exported CSS
- duplicated upload folders
- logo asset
- screenshot asset

Only safe design-reference files should live in this docs folder. Do not place exported files into live plugin paths.