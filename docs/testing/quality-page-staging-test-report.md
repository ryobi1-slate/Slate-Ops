# Slate Quality Page - Staging Test Report

## Test Build
- Branch: `staging`
- Commit: `6bfdbad`
- Date: 2026-05-26
- Tester: Codex
- Staging URL: Not provided in test context

## Summary
- Overall result: Fail
- Highest-risk issue: Shop Tech users are explicitly removed from Quality page access, so the technician sign-off flow cannot be reached from the Tech page links.
- Recommended action: Do not merge until the blocker and high-severity permission/workflow issues below are fixed and retested on the staging site with real role accounts.

## Tested Roles
- Admin: Static route, permission, and code review only; live browser test blocked by missing staging URL/credentials.
- Supervisor/QC: Static route, permission, and code review only; live browser test blocked by missing staging URL/credentials.
- Tech: Failed static permission review because `quality` is removed from tech page access.
- CS: Static read-only review only; live browser test blocked by missing staging URL/credentials.

## Tested Viewports
- Desktop 1440: Not completed live; CSS/static layout reviewed.
- Tablet 768: Not completed live; CSS/static layout reviewed.
- Mobile 390: Not completed live; CSS/static layout reviewed.

## Test Results

| Area | Test | Result | Notes | Screenshot path if captured | Issue ID if created |
|---|---|---:|---|---|---|
| Repo and branch | Confirm current branch | Pass | Current branch is `staging`. | N/A | N/A |
| Repo and branch | Pull latest staging branch | Pass | `git pull origin staging` reported already up to date after elevated retry. | N/A | N/A |
| Repo and branch | Record tested commit | Pass | Commit `6bfdbad`. | N/A | N/A |
| Repo and branch | Clean working tree before testing | Fail | Existing untracked folders were present before report creation: `.codex-worktrees/`, `docs/design/Slate Ops Design Touchups/`, `docs/qa-screenshots/`. This report adds `docs/testing/...`. | N/A | BUG-006 |
| Build checks | PHP syntax checks | Pass | `php -l` passed for `templates/pages/quality.php`, `includes/class-slate-ops-quality.php`, and `includes/class-slate-ops-quality-rest.php`. | N/A | N/A |
| Build checks | JS syntax checks | Pass | Bundled Node passed `node --check` for `assets/js/ops-quality.js` and `assets/js/ops-tech-quality.js`. | N/A | N/A |
| Build checks | npm scripts | Blocked | `npm` is not on PATH and `node_modules` is absent. No `build` script exists; package scripts are `wp-env:*`, `test:js`, and `test:smoke`. | N/A | N/A |
| Build checks | Composer tests | Not applicable | No `composer.json` found. | N/A | N/A |
| Page load and routing | Quality page availability for Tech | Fail | Tech role defaults forcibly remove `quality` from allowed pages, while Tech page injection links point to `/ops/quality/job/{id}`. | N/A | BUG-001 |
| Page load and routing | Admin/Supervisor/CS route availability | Blocked | Static checks show page access exists for admin, supervisor, and CS defaults. Live browser verification not performed. | N/A | N/A |
| Navigation integration | Sidebar Quality link | Partial | Sidebar includes Quality when allowed; Tech cannot have Quality allowed by default logic. | N/A | BUG-001 |
| Desktop layout | Dashboard summary and queue | Partial | Server-rendered dashboard exists with buckets, filter, and table. Live 1440px visual verification not performed. | N/A | N/A |
| Tablet layout | Form runner usability | Partial | JS switches to two-panel layout at `>=768px`. Live 768px visual verification not performed. | N/A | N/A |
| Mobile layout | Technician checklist completion | Fail | Mobile runner exists, but Tech users cannot access the Quality route. Also no N/A control exists. | N/A | BUG-001, BUG-005 |
| RVIA check-in | Required validation and photos | Partial | Backend validates pass/fail responses and required photo slots. Live workflow and persistence not performed. | N/A | N/A |
| RVIA testing sign-off | Failed item documentation | Fail | Failed checklist items require a note only; there is no failed-item photo requirement or per-item photo upload path. | N/A | BUG-003 |
| RVIA testing sign-off | Supervisor rejection/approval | Fail | Supervisor approval can mark a submitted form passed even when checklist items are failed; no override reason is required. | N/A | BUG-004 |
| Commercial check-in | RVIA-only fields hidden | Fail | Form runner always renders `rvia_no` in Vehicle info, including commercial/non-RVIA forms. | N/A | BUG-007 |
| Completed sign-off | Final forms and supervisor approval | Partial | QMS-006 and QMS-010 templates exist with required final photo slots and dependency checks. Live completion workflow not performed. | N/A | N/A |
| Photo upload | Accepted/invalid type handling | Partial | REST upload restricts MIME types and stores attachment metadata by job/form/slot. Live desktop/tablet/mobile upload tests not performed. | N/A | N/A |
| Data persistence | Draft/submit/review persistence | Partial | Data layer stores drafts, photos, signatures, submit/review timestamps in `wp_slate_ops_quality_forms`. Live refresh/logout tests not performed. | N/A | N/A |
| Permissions and role test | Tech assigned-job write scope | Partial | Write endpoints check assigned user/primary owner; route access currently blocks normal Tech use. | N/A | BUG-001 |
| Permissions and role test | Tech/CS read scope | Fail | `GET /quality/jobs/{job}/forms/{form}` does not repeat the assigned-job read check used by `GET /quality/jobs/{job}`. | N/A | BUG-002 |
| REST/API security | Bad form/job tuple | Pass | Mutating endpoints resolve job/form and reject forms not required for that job type. | N/A | N/A |
| REST/API security | Input sanitization | Partial | Payload text fields are sanitized; live invalid nonce/wrong-role tests not performed. | N/A | N/A |
| Browser console/network | Console and REST errors | Blocked | Live staging browser session was not available. | N/A | N/A |
| Cross-page impact | Tech page integration | Fail | Tech Quality injection creates links to a page Tech users cannot access. | N/A | BUG-001 |
| Brand/UI consistency | Slate colors and non-retail language | Partial | Static scan found no cart/checkout/shipping/tax strings in Quality files. CSS primarily uses Slate tokens. Live comparison not performed. | N/A | N/A |
| Accessibility/usability | Keyboard/focus/labels | Partial | Focus styles and labels exist in several controls; live keyboard pass not performed. Icon-only generated buttons need accessible-name verification. | N/A | N/A |
| Negative testing | Blank/duplicate/bad file/script input | Partial | Backend rejects incomplete submit and sanitizes notes; live negative testing not performed. | N/A | N/A |

## Bugs Found

### BUG-001 - Tech users cannot access the Quality page required for sign-off
- Severity: Blocker
- Steps to reproduce:
  1. Review default role page access in `includes/class-slate-ops-utils.php`.
  2. Observe `tech` defaults exclude `quality`.
  3. Observe `get_role_page_access()` removes `quality` from the tech role even if present.
  4. Review Tech injection links in `assets/js/ops-tech-quality.js`, which route to `/ops/quality/job/{job_id}` and `/ops/quality/job/{job_id}/form/{code}`.
- Expected result: Techs can open assigned Quality forms from their Tech job card and complete assigned sign-off steps.
- Actual result: Tech users are blocked from the Quality page by page access, making technician mobile/tablet sign-off unreachable through the intended UI.
- Screenshot/video path: Not captured.
- Console/network evidence: Not captured; static evidence in `includes/class-slate-ops-utils.php` and `assets/js/ops-tech-quality.js`.
- Suggested fix area: Allow Tech access to the Quality route but keep route/data/action scope limited to assigned jobs and submit-only capabilities.

### BUG-002 - Form-detail REST reads are not scoped to assigned Tech jobs
- Severity: High
- Steps to reproduce:
  1. Review `Slate_Ops_Quality_REST::h_job()` in `includes/class-slate-ops-quality-rest.php`.
  2. Note it blocks tech-only users from reading unassigned jobs.
  3. Review `Slate_Ops_Quality_REST::h_form()`.
  4. Note it resolves the job/form and returns template, row, and hydrated photos without repeating the tech assignment check.
- Expected result: Tech-only users can read form details and photos only for assigned jobs.
- Actual result: Any logged-in Ops user with base read access can request form detail rows/photos by job ID and form code.
- Screenshot/video path: Not captured.
- Console/network evidence: Not captured.
- Suggested fix area: Reuse the same assigned-job read guard in `h_form()` that exists in `h_job()`, and confirm CS read scope is intentional.

### BUG-003 - Failed checklist items require notes but not photos
- Severity: High
- Steps to reproduce:
  1. Review `validate_for_submit()` in `includes/class-slate-ops-quality-rest.php`.
  2. Mark an item as `fail`.
  3. Observe validation requires a note only.
  4. Review `assets/js/ops-quality.js`; there is no per-failed-item photo capture path.
- Expected result: Failed items require both a useful comment and photo documentation when the flow calls for it.
- Actual result: A failed item can be submitted with a note and no photo. QMS-005 has no photo slots, so RVIA testing failures cannot attach item-level evidence.
- Screenshot/video path: Not captured.
- Console/network evidence: Not captured.
- Suggested fix area: Add per-item failure photo slots or a failed-item evidence collection model, then enforce it in submit validation.

### BUG-004 - Supervisor can approve submitted forms with unresolved failed items
- Severity: High
- Steps to reproduce:
  1. Submit a form that contains a failed checklist item with the required note.
  2. As Supervisor/QC, choose `Mark Passed`.
  3. Review `review_form()` in `includes/class-slate-ops-quality.php`; it accepts `passed` for any submitted form.
- Expected result: Approval is blocked while failed items remain unresolved unless a deliberate override path exists and records the override reason.
- Actual result: Supervisor approval can set the form to `passed` with failed checklist responses still present.
- Screenshot/video path: Not captured.
- Console/network evidence: Not captured.
- Suggested fix area: Validate submitted payload before `passed` review decisions; require correction/resubmission or an explicit override model.

### BUG-005 - Checklist controls do not support N/A
- Severity: Medium
- Steps to reproduce:
  1. Open `assets/js/ops-quality.js`.
  2. Review `renderPF()` and `validate()`.
  3. Review backend `sanitize_payload()` and `validate_for_submit()`.
- Expected result: Checklist items support Pass, Fail, and N/A where required by the quality workflow.
- Actual result: Only `pass` and `fail` are accepted and rendered.
- Screenshot/video path: Not captured.
- Console/network evidence: Not captured.
- Suggested fix area: Add `na` to frontend controls, payload sanitization, validation, status display, and reporting.

### BUG-006 - Working tree was not clean before testing
- Severity: Low
- Steps to reproduce:
  1. Run `git status --short`.
- Expected result: No unrelated modified or untracked files before testing.
- Actual result: Existing untracked directories were present before this report was created.
- Screenshot/video path: Not captured.
- Console/network evidence: `?? .codex-worktrees/`, `?? docs/design/Slate Ops Design Touchups/`, `?? docs/qa-screenshots/`.
- Suggested fix area: Confirm whether these folders are intentional local artifacts; add to `.gitignore` or remove outside this test pass.

### BUG-007 - Commercial/non-RVIA form runner shows RVIA number field
- Severity: Medium
- Steps to reproduce:
  1. Open a commercial/non-RVIA form in the runner.
  2. Review the Vehicle info step.
  3. Static evidence: `renderVehicleStep()` always renders `vin`, `odometer`, `key_count`, and `rvia_no`.
- Expected result: RVIA-only fields are hidden from Commercial / Non-RVIA check-in and completion flows.
- Actual result: `RVIA #` appears for all templates.
- Screenshot/video path: Not captured.
- Console/network evidence: Not captured.
- Suggested fix area: Render `rvia_no` only when `state.job.quality_type === 'rvia'` or when the template explicitly declares that field.

### BUG-008 - Mobile Quality controls inherit pink theme button styling
- Severity: Low
- Steps to reproduce:
  1. Open the Quality page on mobile Safari.
  2. Navigate to Supervisor review or the photo step.
  3. Observe the `Mark Passed` and optional existing damage controls.
- Expected result: Quality controls use Slate Ops colors: sage for primary/pass and arches for warning/correction.
- Actual result: Some buttons inherit a pink theme color, and the optional existing damage slot appears visually required because it is numbered with required photo slots.
- Screenshot/video path: User-provided mobile screenshots, not saved in repo.
- Console/network evidence: Not captured.
- Suggested fix area: Scope Quality button styles to the Ops Quality page and separate required photo-slot numbering from optional existing damage.

## Screenshots

Live Chrome screenshots captured during the follow-up staging pass:

- `docs/testing/screenshots/quality-page/quality-chrome-desktop-dashboard.png`
- `docs/testing/screenshots/quality-page/quality-chrome-job-1011-current.png`
- `docs/testing/screenshots/quality-page/quality-chrome-qms004-form-current.png`
- `docs/testing/screenshots/quality-page/quality-chrome-qms004-sign-validation.png`
- `docs/testing/screenshots/quality-page/quality-chrome-supervisor-review-q1012.png`
- `docs/testing/screenshots/quality-page/quality-chrome-commercial-qms009.png`

Planned names for live retest:

- `docs/testing/screenshots/quality-page/quality-desktop-dashboard.png`
- `docs/testing/screenshots/quality-page/quality-mobile-tech-checklist.png`
- `docs/testing/screenshots/quality-page/quality-tablet-supervisor-review.png`
- `docs/testing/screenshots/quality-page/quality-photo-upload-error.png`

## Chrome Staging Retest - 2026-05-26

- Staging URL tested: `https://staging-6c9f-infod7ae72dc14c-qjuxg.wpcomstaging.com/ops/quality/`
- Chrome session role: Admin
- Desktop viewport observed: 1920 x 855
- Dashboard loaded with 36 jobs, no WordPress admin bar, no detected retail language, and no horizontal scroll.
- Dashboard search worked: searching `Q-1012` showed 1 row.
- Dashboard bucket filter worked: Submitted bucket showed 1 submitted row.
- QMS-004 for job Q-1011 opened and rendered the responsive runner.
- QMS-004 sign step correctly blocked incomplete submit with `Missing photo: Cargo area`.
- Commercial job Q-1004 correctly loaded QMS-009 and QMS-010, with QMS-010 dependency-locked.
- Supervisor review page for submitted job Q-1012 loaded; QMS-006 was the only enabled review option, while passed QMS-004/QMS-005 options were disabled.
- New issue found: completed/submitted check-in photo tiles for Q-1012 render as mostly blank because thumbnail URLs are proxied as `fit=1%2C1`, making review photos visually unusable even though attachment slots exist.
- Viewport limitation: Chrome automation could not resize the normal Chrome tab; tablet/mobile checks still need manual Chrome resize or DevTools device mode.

## Mobile Screenshot Follow-Up - 2026-05-26

- User-provided mobile Safari screenshots showed the Supervisor `Mark Passed` button and existing damage waiver controls inheriting pink button styling instead of Slate sage.
- Existing damage was also counted and labeled like a required `9/9` photo slot, even when the required-photo summary showed `7 of 8`.
- Local fix prepared: Quality page button styles are page-scoped to Slate tokens, optional existing damage now labels as optional/no-damage instead of a required count, and the waiver copy now reads `No existing damage noted` when active.

## Final Recommendation

Do not merge.

The Quality feature has the right broad architecture: server-rendered Ops page, vanilla JS runner, Slate token-based styling, dedicated REST endpoints, required QMS form templates, draft/submit/review persistence, and photo-slot metadata tied to job/form/slot. However, the current build fails the core technician workflow and has high-risk review/security gaps that should be fixed before live staging sign-off.
