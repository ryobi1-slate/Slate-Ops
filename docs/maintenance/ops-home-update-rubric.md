# OPS Home update rubric

Use OPS Home as a shop-facing bulletin board, not as the technical changelog.

## When to add an OPS Home update

Add an entry when a release changes one or more of these:

- What CS, techs, supervisors, or admins should do differently.
- Where a user should go to complete work.
- Which action is available, removed, renamed, or newly required.
- Schedule, blocker, QC, assignment, queue, or readiness accountability.
- Live data replacing mock/prototype data.
- A workflow risk that shop users need to watch.

Skip items that are only:

- CSS polish with no workflow impact.
- Internal refactors.
- REST endpoint or schema detail that does not change user behavior.
- Build/version bumps with no visible change.

## Required entry fields

Each entry in `includes/data/ops-home-updates.php` should answer:

- `title`: What changed, in shop language.
- `description`: Why the user will notice it.
- `action`: What the user should do differently.
- `priority`: `Must know`, `Helpful`, or `FYI`.
- `tags`: Affected audience or workflow area.

## Weekly review

During the weekly maintenance pass:

1. Compare the latest `CHANGELOG.md` version with `source_version` in `includes/data/ops-home-updates.php`.
2. Add only shop-relevant changes.
3. Remove or rotate out stale updates once the behavior is normal.
4. Update `reviewed_on`.
5. Note the review in the dated maintenance report.
