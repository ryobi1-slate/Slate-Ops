# ClickUp Import — On-Server Runbook

This directory holds the one-time ClickUp → Slate Ops import tooling. The
workflow runs **on the WordPress server** after deployment. It is not run
locally and is not invoked by normal WordPress request handling.

> Tooling reference: PR #128 (importer code, merged). This README documents
> the on-server workflow only — no local execution, no database writes from
> a developer machine.

## Files in this workflow

| File | Where it runs | Purpose |
|---|---|---|
| `local-imports/build-jsonl.py` | (already run; output committed-ready) | CSV → JSONL conversion |
| `local-imports/clickup-active-cleaned.jsonl` | placed on server | Canonical input the importer reads |
| `local-imports/run-import.php` | WordPress server (CLI) | Step runner — bootstraps WP, calls the importer |
| `includes/cli/class-clickup-importer.php` | WordPress server (loaded by runner) | Backup, clear, import logic |
| `backup/jobs-pre-clickup-import.json` | WordPress server (written by `backup` step) | Pre-clear snapshot of all jobs |

`local-imports/*.jsonl` and `backup/*.json` are gitignored — they are
generated/placed on the server, never committed.

## Expected counts

After a successful run the numbers must match:

| Metric | Expected |
|---|---|
| Active rows in JSONL | **23** |
| Rows with `so_number` populated | **23** |
| Rows missing `so_number` | **0** |

If `import-dry` reports anything other than 23 / 23 / 0, **stop** and
investigate before running `import-execute`.

---

## 1. Place `clickup-active-cleaned.jsonl` on the server

The JSONL is gitignored, so it must be uploaded out-of-band.

**Target path on the WordPress server:**

```
<plugin-root>/local-imports/clickup-active-cleaned.jsonl
```

`<plugin-root>` is the directory containing `slate-ops.php` (typically
`wp-content/plugins/slate-ops/`).

Upload via SFTP/SCP/rsync — for example:

```bash
scp clickup-active-cleaned.jsonl \
    user@server:/var/www/html/wp-content/plugins/slate-ops/local-imports/
```

Verify on the server:

```bash
wc -l /var/www/html/wp-content/plugins/slate-ops/local-imports/clickup-active-cleaned.jsonl
# expect: 23
```

## 2. Run backup

Snapshots every existing job row to `backup/jobs-pre-clickup-import.json`.
Non-destructive — safe to run any time.

```bash
cd /var/www/html/wp-content/plugins/slate-ops
php local-imports/run-import.php \
    --wp-path=/var/www/html \
    --step=backup
```

Expected output ends with:

```
DONE.
  Jobs exported: <N>
  File:          .../backup/jobs-pre-clickup-import.json
```

Confirm the backup file exists and is non-empty before continuing.

## 3. Run clear-dry

Counts what `clear-execute` would delete. **Makes no changes.**

```bash
php local-imports/run-import.php \
    --wp-path=/var/www/html \
    --step=clear-dry
```

Expected output:

```
  Jobs:           <N>
  Time segments:  <N>
  Audit entries:  <N>
```

Sanity-check the counts match what you saw on the live CS page before
proceeding.

## 4. Run import-dry

Parses the JSONL and shows what would be inserted. **Makes no changes.**

```bash
php local-imports/run-import.php \
    --wp-path=/var/www/html \
    --step=import-dry
```

Expected output (must match exactly before running execute):

```
  Would insert: 23
  Would skip:   0
```

The preview also prints all 23 customer names with status, tech, dates and
notes. Eyeball the list — if any row looks wrong, stop and fix the JSONL.

## 5. Execute (clear, then import)

Run only after backup, clear-dry, and import-dry have all succeeded and
the counts above match.

```bash
# 5a — delete existing jobs + time segments (users / settings untouched)
php local-imports/run-import.php \
    --wp-path=/var/www/html \
    --step=clear-execute --confirm

# 5b — insert the 23 ClickUp rows
php local-imports/run-import.php \
    --wp-path=/var/www/html \
    --step=import-execute --confirm
```

Expected output of `import-execute`:

```
DONE.
  Inserted: 23
  Skipped:  0
```

`--confirm` is required for both execute steps; the runner refuses to
write otherwise.

---

## Post-import checks

- CS page lists ~23 active jobs (no Closed history clutter).
- Status mix matches `import-report.txt`:
  Scheduled 7, In Progress 4, Delayed 9, Complete-Awaiting-Pickup 3.
- Parts mix: Ready 10, Not Ready 13.
- Users, roles, settings, and `work_centers` are unchanged.

## Rollback

If the import is wrong, restore from the backup written in step 2:

```bash
# Inspect the backup
jq 'length' backup/jobs-pre-clickup-import.json
```

Restoration is a manual `wp db import` / SQL replay from the backup JSON —
do not script this without a current DB dump in hand.

## Regenerating the JSONL (only if the CSV changes)

`clickup-active-cleaned.jsonl` is built from `clickup-export.csv` by
`build-jsonl.py`. The script is pure Python (no DB writes) and may be run
on any machine with Python 3:

```bash
python3 local-imports/build-jsonl.py
```

Then re-upload the regenerated JSONL to the server (step 1).
