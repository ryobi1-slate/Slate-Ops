<?php
/**
 * Slate Ops — Quality module page.
 *
 * Routes (all under /ops/quality):
 *   /ops/quality                                    → Dashboard (supervisor/CS)
 *   /ops/quality/job/{job_id}                       → Job Quality Review
 *   /ops/quality/job/{job_id}/form/{form_code}      → Form Runner (responsive)
 *
 * Server-rendered first. Vanilla JS at assets/js/ops-quality.js enhances
 * interactivity (form runner, photo upload, supervisor decisions).
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('Slate_Ops_Quality')) {
  require_once SLATE_OPS_PATH . 'includes/class-slate-ops-quality.php';
}

if (!slate_ops_current_user_can_access_ops_page('quality')) {
  ?>
  <section style="display:grid;place-items:center;height:100%;padding:32px;">
    <div style="max-width:520px;text-align:center;background:#fff;border:1px solid #D9D5C7;border-radius:10px;padding:28px;">
      <h1 style="margin:0 0 8px;font-size:24px;">Access Denied</h1>
      <p style="margin:0;color:#5E646B;">You do not have permission to view Quality.</p>
    </div>
  </section>
  <?php
  return;
}

$path     = Slate_Ops_Routes::current_path();
$segments = $path === '' ? [] : explode('/', $path);

// segments[0] is 'quality'. Resolve sub-view.
$view    = 'dashboard';
$job_id  = 0;
$form_code = '';
if (isset($segments[1]) && $segments[1] === 'job' && !empty($segments[2])) {
  $view   = 'job';
  $job_id = (int) $segments[2];
  if (!empty($segments[3]) && $segments[3] === 'form' && !empty($segments[4])) {
    $view      = 'form';
    $form_code = strtoupper(sanitize_text_field($segments[4]));
  }
}

$current_user_caps = Slate_Ops_Utils::current_user_caps_summary();
$can_review = !empty($current_user_caps['supervisor']) || !empty($current_user_caps['admin']);
$can_submit = !empty($current_user_caps['tech']) || !empty($current_user_caps['admin']);

$registry = Slate_Ops_Quality::form_registry();

/**
 * Helper: render the canonical Quality status pill.
 */
if (!function_exists('slate_ops_quality_pill')) {
  function slate_ops_quality_pill($status) {
    $cls = Slate_Ops_Quality::status_pill_class($status);
    $lbl = Slate_Ops_Quality::status_label($status);
    return '<span class="oq-pill oq-pill--' . esc_attr($cls) . '">' . esc_html($lbl) . '</span>';
  }
}

if (!function_exists('slate_ops_quality_form_chip')) {
  function slate_ops_quality_form_chip($form_code, $missing = false) {
    $cls = $missing ? 'oq-form-chip--missing' : 'oq-form-chip--present';
    $glyph = $missing ? '○' : '●';
    return '<span class="oq-form-chip ' . esc_attr($cls) . '">' . esc_html($glyph) . ' ' . esc_html($form_code) . '</span>';
  }
}

?>
<div class="oq" data-view="<?php echo esc_attr($view); ?>" data-job-id="<?php echo (int) $job_id; ?>" data-form-code="<?php echo esc_attr($form_code); ?>">

<?php if ($view === 'dashboard') :
  $buckets = Slate_Ops_Quality::bucket_counts();
  $jobs    = Slate_Ops_Quality::list_jobs(['limit' => 200]);
?>

  <div class="ops-page-header">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:24px;flex-wrap:wrap;">
      <div>
        <div class="ops-page-eyebrow">Quality</div>
        <h1 class="ops-page-title">Quality Dashboard</h1>
        <div class="ops-page-desc">Sign-Off queue for every open Ops job. Quality forms always attach to an existing job — start a Check-In from the job's page.</div>
      </div>
    </div>
  </div>

  <div class="oq-buckets" role="tablist">
    <?php
    $bucket_meta = [
      Slate_Ops_Quality::STATUS_NOT_STARTED      => ['hint' => 'awaiting check-in'],
      Slate_Ops_Quality::STATUS_IN_PROGRESS      => ['hint' => 'techs working now'],
      Slate_Ops_Quality::STATUS_SUBMITTED        => ['hint' => 'awaiting Supervisor Review'],
      Slate_Ops_Quality::STATUS_NEEDS_CORRECTION => ['hint' => 'sent back to tech'],
      Slate_Ops_Quality::STATUS_PASSED           => ['hint' => 'last 7 days'],
      Slate_Ops_Quality::STATUS_LOCKED           => ['hint' => 'waiting on prior step'],
    ];
    foreach (Slate_Ops_Quality::allowed_statuses() as $bucket) :
      $count = (int) ($buckets[$bucket] ?? 0);
      $cls   = Slate_Ops_Quality::status_pill_class($bucket);
    ?>
      <button type="button" class="oq-bucket oq-bucket--<?php echo esc_attr($cls); ?>" data-bucket="<?php echo esc_attr($bucket); ?>">
        <div class="oq-bucket__label"><?php echo esc_html(Slate_Ops_Quality::status_label($bucket)); ?></div>
        <div class="oq-bucket__count"><?php echo (int) $count; ?></div>
        <div class="oq-bucket__hint"><?php echo esc_html($bucket_meta[$bucket]['hint']); ?></div>
      </button>
    <?php endforeach; ?>
  </div>

  <div class="oq-filterbar">
    <label class="oq-search">
      <span class="material-symbols-outlined">search</span>
      <input type="search" id="oq-search-input" placeholder="Search by Job # / SO / VIN / customer">
    </label>
    <span class="oq-filterbar__count"><span id="oq-row-count"><?php echo count($jobs); ?></span> jobs</span>
  </div>

  <div class="oq-table-wrap">
    <table class="oq-table" id="oq-job-table">
      <thead>
        <tr>
          <th>Job #</th>
          <th>SO #</th>
          <th>VIN</th>
          <th>Customer / Dealer</th>
          <th>Type</th>
          <th>Forms</th>
          <th>Missing</th>
          <th>Tech</th>
          <th>Updated</th>
          <th>Status</th>
          <th class="oq-col-action">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($jobs)) : ?>
          <tr><td colspan="11" class="oq-empty">No jobs in the Quality queue.</td></tr>
        <?php endif; ?>
        <?php foreach ($jobs as $j) :
          $missing = [];
          $present_codes = array_column($j['forms'], 'form_code');
          foreach ($j['required_forms'] as $rc) {
            $row = null;
            foreach ($j['forms'] as $f) { if ($f['form_code'] === $rc) { $row = $f; break; } }
            if (!$row || !in_array($row['status'], [Slate_Ops_Quality::STATUS_PASSED, Slate_Ops_Quality::STATUS_SUBMITTED], true)) {
              $missing[] = $rc;
            }
          }
          $rollup = $j['rollup_status'];
          $assigned_name = '';
          if (!empty($j['assigned_user_id'])) {
            $u = get_userdata($j['assigned_user_id']);
            if ($u) $assigned_name = $u->display_name;
          }
          $action_label = 'Open';
          if      ($rollup === Slate_Ops_Quality::STATUS_NOT_STARTED) $action_label = 'Start Check-In';
          elseif  ($rollup === Slate_Ops_Quality::STATUS_IN_PROGRESS) $action_label = 'Continue Form';
          elseif  ($rollup === Slate_Ops_Quality::STATUS_SUBMITTED)   $action_label = 'Review';
          elseif  ($rollup === Slate_Ops_Quality::STATUS_NEEDS_CORRECTION) $action_label = 'Review';
          elseif  ($rollup === Slate_Ops_Quality::STATUS_PASSED)      $action_label = 'View Packet';
        ?>
          <tr class="oq-row" data-status="<?php echo esc_attr($rollup); ?>" data-search="<?php echo esc_attr(strtolower($j['so_number'] . ' ' . $j['vin'] . ' ' . $j['customer_name'] . ' ' . $j['dealer_name'] . ' Q-' . $j['job_id'])); ?>">
            <td class="oq-mono">Q-<?php echo (int) $j['job_id']; ?></td>
            <td class="oq-mono"><?php echo esc_html($j['so_number']); ?></td>
            <td class="oq-mono oq-ink-3"><?php echo esc_html($j['vin_last8'] ? '…' . $j['vin_last8'] : $j['vin']); ?></td>
            <td>
              <div><?php echo esc_html($j['customer_name']); ?></div>
              <div class="oq-mono oq-ink-3"><?php echo esc_html($j['dealer_name']); ?></div>
            </td>
            <td class="oq-mono oq-type"><?php echo esc_html(strtoupper($j['quality_type'])); ?></td>
            <td>
              <div class="oq-form-chips">
                <?php foreach ($j['required_forms'] as $rc) :
                  $row = null;
                  foreach ($j['forms'] as $f) { if ($f['form_code'] === $rc) { $row = $f; break; } }
                  $is_missing = !$row || !in_array($row['status'] ?? '', [Slate_Ops_Quality::STATUS_PASSED, Slate_Ops_Quality::STATUS_SUBMITTED], true);
                  $short = preg_replace('/^QMS-/', '', $rc);
                  echo slate_ops_quality_form_chip($short, $is_missing);
                endforeach; ?>
              </div>
            </td>
            <td class="oq-mono"><?php echo count($missing) ? '<span class="oq-warn">' . count($missing) . ' form' . (count($missing) > 1 ? 's' : '') . '</span>' : '<span class="oq-ink-3">—</span>'; ?></td>
            <td><?php echo esc_html($assigned_name); ?></td>
            <td class="oq-mono oq-ink-3"><?php echo esc_html(human_time_diff(strtotime($j['updated_at'] ?? 'now'), current_time('timestamp'))) . ' ago'; ?></td>
            <td><?php echo slate_ops_quality_pill($rollup); ?></td>
            <td class="oq-col-action">
              <a class="oq-link" href="<?php echo esc_url(home_url('/ops/quality/job/' . (int) $j['job_id'])); ?>">
                <?php echo esc_html($action_label); ?> <span class="material-symbols-outlined">chevron_right</span>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

<?php elseif ($view === 'job') :
  $job = Slate_Ops_Quality::describe_job($job_id);
  if (!$job) : ?>
    <div class="oq-empty-panel">
      <div class="ops-page-eyebrow">Quality</div>
      <h1 class="ops-page-title">Job not found</h1>
      <p>Job <?php echo (int) $job_id; ?> is not in the Quality queue.</p>
      <a class="oq-btn oq-btn--secondary" href="<?php echo esc_url(home_url('/ops/quality')); ?>">← Back to dashboard</a>
    </div>
  <?php else :
    $forms_by_code = [];
    foreach ($job['forms'] as $f) $forms_by_code[$f['form_code']] = $f;
    $submitted_count = 0;
    $failed_items = []; $missing_photos = [];
    foreach ($job['required_forms'] as $rc) {
      $row = $forms_by_code[$rc] ?? null;
      if ($row && in_array($row['status'], [Slate_Ops_Quality::STATUS_PASSED, Slate_Ops_Quality::STATUS_SUBMITTED], true)) {
        $submitted_count++;
      }
      if (!$row) continue;
      $template = Slate_Ops_Quality::get_form_template($rc);
      $checklist = $row['payload']['checklist'] ?? [];
      foreach ($template['sections'] as $section) {
        foreach ($section['items'] as $item) {
          $resp = $checklist[$section['key']][$item['key']] ?? null;
          if (($resp['result'] ?? '') === 'fail') {
            $failed_items[] = [
              'form'     => $rc,
              'task'     => $item['label'],
              'note'     => $resp['note'] ?? '',
              'user_id'  => (int) ($resp['user_id'] ?? 0),
              'timestamp'=> $resp['timestamp'] ?? '',
            ];
          }
        }
      }
      foreach (($template['photo_slots'] ?? []) as $slot) {
        if (!empty($slot['required']) && empty($row['photos'][$slot['key']])) {
          $missing_photos[] = ['form' => $rc, 'slot' => $slot['label']];
        }
      }
    }
  ?>
    <div class="oq-job-review">
      <div class="oq-breadcrumb">
        <a href="<?php echo esc_url(home_url('/ops/quality')); ?>">
          <span class="material-symbols-outlined">chevron_left</span> Back to dashboard
        </a>
      </div>

      <div class="oq-card oq-job-header">
        <div class="oq-job-header__top">
          <div class="oq-job-header__title">
            <div class="oq-meta-row">
              <span class="oq-eyebrow">Job</span>
              <span class="oq-mono"><?php echo 'Q-' . (int) $job['job_id'] . ' · ' . esc_html($job['so_number']); ?></span>
              <?php echo slate_ops_quality_pill($job['rollup_status']); ?>
            </div>
            <h1 class="oq-job-header__name"><?php echo esc_html($job['customer_name'] ?: 'Unnamed job'); ?></h1>
            <div class="oq-job-header__facts">
              <?php if (!empty($job['vin'])) : ?>
                <span><span class="oq-eyebrow">VIN</span> <span class="oq-mono"><?php echo esc_html($job['vin']); ?></span></span>
              <?php endif; ?>
              <span><span class="oq-eyebrow">Type</span> <?php echo esc_html(strtoupper($job['quality_type'])); ?></span>
              <?php if (!empty($job['dealer_name'])) : ?>
                <span><span class="oq-eyebrow">Dealer</span> <?php echo esc_html($job['dealer_name']); ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="oq-grid">
        <div class="oq-col-left">
          <div class="oq-card">
            <div class="oq-card__head">
              <div>
                <div class="oq-card__title">Required forms</div>
                <div class="oq-card__sub"><?php echo strtoupper($job['quality_type']) === 'RVIA' ? 'RVIA jobs require QMS-004, 005 and 006.' : 'Commercial jobs require QMS-009 and 010.'; ?></div>
              </div>
              <span class="oq-eyebrow"><?php echo (int) $submitted_count; ?> of <?php echo count($job['required_forms']); ?> submitted</span>
            </div>
            <div class="oq-form-list">
              <?php foreach ($job['required_forms'] as $rc) :
                $row = $forms_by_code[$rc] ?? null;
                $template = Slate_Ops_Quality::get_form_template($rc);
                $status = $row['status'] ?? Slate_Ops_Quality::STATUS_NOT_STARTED;
                $is_locked_by_deps = false;
                if (!$row && !empty($template['depends_on'])) {
                  foreach ($template['depends_on'] as $dep) {
                    $dep_row = $forms_by_code[$dep] ?? null;
                    if (!$dep_row || $dep_row['status'] !== Slate_Ops_Quality::STATUS_PASSED) $is_locked_by_deps = true;
                  }
                }
                $effective_status = $is_locked_by_deps ? Slate_Ops_Quality::STATUS_LOCKED : $status;
                $short = preg_replace('/^QMS-/', '', $rc);
              ?>
                <div class="oq-form-row" data-status="<?php echo esc_attr($effective_status); ?>">
                  <div class="oq-form-row__badge oq-form-row__badge--<?php echo esc_attr(Slate_Ops_Quality::status_pill_class($effective_status)); ?>"><?php echo esc_html($short); ?></div>
                  <div class="oq-form-row__body">
                    <div class="oq-form-row__title">
                      <?php echo esc_html($template['name']); ?>
                      <?php echo slate_ops_quality_pill($effective_status); ?>
                    </div>
                    <div class="oq-form-row__meta">
                      <?php if ($is_locked_by_deps) : ?>
                        Locked — unlocks after prior step passes.
                      <?php elseif (!empty($row['submitted_at'])) :
                        $sub_user = $row['submitted_by'] ? get_userdata($row['submitted_by']) : null;
                        echo 'Submitted by <b>' . esc_html($sub_user ? $sub_user->display_name : 'Unknown') . '</b> · ' . esc_html(mysql2date('M j, g:i A', $row['submitted_at']));
                      else : ?>
                        Not started.
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="oq-form-row__actions">
                    <?php if (!$is_locked_by_deps) : ?>
                      <a class="oq-btn oq-btn--secondary" href="<?php echo esc_url(home_url('/ops/quality/job/' . (int) $job['job_id'] . '/form/' . $rc)); ?>">Open</a>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <?php if (!empty($failed_items)) : ?>
            <div class="oq-card">
              <div class="oq-card__head">
                <div>
                  <div class="oq-card__title">Failed items <span class="oq-redwood">· <?php echo count($failed_items); ?></span></div>
                  <div class="oq-card__sub">From submitted forms.</div>
                </div>
              </div>
              <div class="oq-fails">
                <?php foreach ($failed_items as $fi) :
                  $u = $fi['user_id'] ? get_userdata($fi['user_id']) : null;
                ?>
                  <div class="oq-fail">
                    <div class="oq-fail__icon"><span class="material-symbols-outlined">close</span></div>
                    <div class="oq-fail__body">
                      <div class="oq-fail__task"><?php echo esc_html($fi['task']); ?></div>
                      <div class="oq-fail__meta">
                        <span class="oq-mono"><?php echo esc_html($fi['form']); ?></span>
                        <?php if ($u) : ?>· <?php echo esc_html($u->display_name); ?><?php endif; ?>
                        <?php if ($fi['timestamp']) : ?>· <?php echo esc_html(mysql2date('M j, g:i A', $fi['timestamp'])); ?><?php endif; ?>
                      </div>
                      <?php if (!empty($fi['note'])) : ?>
                        <div class="oq-fail__note"><?php echo esc_html($fi['note']); ?></div>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <?php
          // Photo gallery (check-in slots from first form)
          $ci_form = null;
          foreach ($job['required_forms'] as $rc) {
            $row = $forms_by_code[$rc] ?? null;
            if (!$row) continue;
            $tmpl = Slate_Ops_Quality::get_form_template($rc);
            if (!empty($tmpl) && ($tmpl['kind'] ?? '') === 'check_in') { $ci_form = ['code' => $rc, 'row' => $row, 'template' => $tmpl]; break; }
          }
          if ($ci_form) :
            $captured = 0;
            $required_slots = array_filter($ci_form['template']['photo_slots'], fn($s) => !empty($s['required']));
            foreach ($required_slots as $s) {
              if (!empty($ci_form['row']['photos'][$s['key']])) $captured++;
            }
          ?>
            <div class="oq-card">
              <div class="oq-card__head">
                <div>
                  <div class="oq-card__title">Check-in photos <span class="oq-ink-3">· <?php echo $captured; ?> of <?php echo count($required_slots); ?></span></div>
                  <div class="oq-card__sub"><?php echo (count($required_slots) - $captured) > 0 ? (count($required_slots) - $captured) . ' missing' : 'All slots captured.'; ?></div>
                </div>
              </div>
              <div class="oq-photo-grid">
                <?php foreach ($required_slots as $idx => $slot) :
                  $photos = $ci_form['row']['photos'][$slot['key']] ?? [];
                  $first  = !empty($photos[0]) ? wp_get_attachment_image_url((int) $photos[0], 'medium') : '';
                  $filled = !empty($first);
                ?>
                  <div class="oq-photo-slot <?php echo $filled ? 'oq-photo-slot--filled' : ''; ?>" style="<?php echo $filled ? 'background-image:url(' . esc_url($first) . ')' : ''; ?>">
                    <span class="oq-photo-slot__status <?php echo $filled ? 'oq-photo-slot__status--ok' : 'oq-photo-slot__status--missing'; ?>"><?php echo $filled ? '✓' : '!'; ?></span>
                    <span class="oq-photo-slot__label"><?php echo esc_html($slot['label']); ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <div class="oq-col-right">
          <?php if ($can_review) : ?>
            <div class="oq-card oq-supervisor">
              <div class="oq-supervisor__head">
                <div class="oq-eyebrow">Supervisor Review</div>
                <div class="oq-supervisor__title">Decide on submitted forms</div>
                <div class="oq-supervisor__sub"><?php echo count($failed_items); ?> failed items · <?php echo count($missing_photos); ?> missing photos</div>
              </div>
              <div class="oq-supervisor__body">
                <label class="oq-eyebrow" for="oq-review-form">Form</label>
                <select id="oq-review-form" class="oq-input">
                  <?php foreach ($job['required_forms'] as $rc) :
                    $row = $forms_by_code[$rc] ?? null;
                    $disabled = !$row || !in_array($row['status'] ?? '', [Slate_Ops_Quality::STATUS_SUBMITTED, Slate_Ops_Quality::STATUS_NEEDS_CORRECTION], true);
                  ?>
                    <option value="<?php echo esc_attr($rc); ?>" <?php disabled($disabled, true); ?>><?php echo esc_html($rc); ?> · <?php echo esc_html(Slate_Ops_Quality::status_label($row['status'] ?? Slate_Ops_Quality::STATUS_NOT_STARTED)); ?></option>
                  <?php endforeach; ?>
                </select>
                <textarea id="oq-review-note" class="oq-input oq-textarea" placeholder="Correction notes for the tech — be specific. They'll see these on their mobile."></textarea>
                <div class="oq-supervisor__actions">
                  <button class="oq-btn oq-btn--primary oq-btn--lg" data-action="review-pass">
                    <span class="material-symbols-outlined">check</span> Mark Passed
                  </button>
                  <button class="oq-btn oq-btn--danger oq-btn--lg" data-action="review-fail">
                    <span class="material-symbols-outlined">flag</span> Needs Correction
                  </button>
                </div>
                <div class="oq-supervisor__actions">
                  <button class="oq-btn oq-btn--ghost" data-action="unlock">
                    <span class="material-symbols-outlined">lock_open</span> Unlock form
                  </button>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <div class="oq-card">
            <div class="oq-card__head">
              <div class="oq-card__title">Job at a glance</div>
            </div>
            <div class="oq-stats">
              <div class="oq-stat">
                <div class="oq-stat__value"><?php echo (int) $submitted_count; ?>/<?php echo count($job['required_forms']); ?></div>
                <div class="oq-stat__label">Forms submitted</div>
              </div>
              <div class="oq-stat">
                <div class="oq-stat__value oq-redwood"><?php echo count($failed_items); ?></div>
                <div class="oq-stat__label">Failed items</div>
              </div>
              <div class="oq-stat">
                <div class="oq-stat__value"><?php echo (count($required_slots ?? []) - ($captured ?? 0)) > 0 ? ($captured ?? 0) . '/' . count($required_slots ?? []) : '—'; ?></div>
                <div class="oq-stat__label">Check-in photos</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

<?php elseif ($view === 'form') :
  $job      = Slate_Ops_Quality::describe_job($job_id);
  $template = Slate_Ops_Quality::get_form_template($form_code);
  if (!$job || !$template) : ?>
    <div class="oq-empty-panel">
      <div class="ops-page-eyebrow">Quality</div>
      <h1 class="ops-page-title">Form not found</h1>
      <p>Could not load form <?php echo esc_html($form_code); ?> for job <?php echo (int) $job_id; ?>.</p>
      <a class="oq-btn oq-btn--secondary" href="<?php echo esc_url(home_url('/ops/quality')); ?>">← Back to dashboard</a>
    </div>
  <?php else :
    $row = Slate_Ops_Quality::ensure_form_row($job_id, $form_code);
    // Server-rendered initial state for the runner; JS replaces with interactive UI.
    $initial = [
      'job'      => $job,
      'template' => $template,
      'row'      => $row,
    ];
  ?>
    <div class="oq-runner" data-runner data-initial='<?php echo esc_attr(wp_json_encode($initial)); ?>'>
      <div class="oq-runner__loading">
        <div class="oq-runner__skel">
          <div class="oq-eyebrow"><?php echo esc_html($template['eyebrow']); ?></div>
          <div class="oq-runner__skel-title"><?php echo esc_html($template['name']); ?></div>
          <p class="oq-runner__skel-sub">Loading form…</p>
        </div>
      </div>
    </div>
  <?php endif;
endif; ?>

</div>
