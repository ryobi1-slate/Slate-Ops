<?php
/**
 * Production Floor Job Board
 * Display-only monitor view for active Slate Ops jobs.
 */
if (!defined('ABSPATH')) exit;
?>
<?php
$floor_css_ver = file_exists(SLATE_OPS_PATH . 'assets/css/production-floor-board.css') ? filemtime(SLATE_OPS_PATH . 'assets/css/production-floor-board.css') : SLATE_OPS_VERSION;
$floor_js_ver  = file_exists(SLATE_OPS_PATH . 'assets/js/production-floor-board.js') ? filemtime(SLATE_OPS_PATH . 'assets/js/production-floor-board.js') : SLATE_OPS_VERSION;
$floor_settings = [
  'apiRoot'   => esc_url_raw(rest_url('slate-ops/v1')),
  'nonce'     => wp_create_nonce('wp_rest'),
  'refreshMs' => 60000,
];
?>
<link rel="stylesheet" href="<?php echo esc_url(SLATE_OPS_URL . 'assets/css/production-floor-board.css?ver=' . rawurlencode((string) $floor_css_ver)); ?>">
<section class="ops-floor-board" aria-label="Production Floor Job Board">
  <header class="ops-floor-header">
    <div class="ops-floor-brand">
      <div class="ops-floor-mark" aria-hidden="true">SLATE</div>
      <div>
        <p class="ops-floor-kicker">Production Floor</p>
        <h1>Job Board</h1>
      </div>
    </div>

    <div class="ops-floor-stats" aria-label="Production summary">
      <div class="ops-floor-stat"><span>Total Active</span><strong data-floor-stat="active">0</strong></div>
      <div class="ops-floor-stat"><span>In Progress</span><strong data-floor-stat="progress">0</strong></div>
      <div class="ops-floor-stat ops-floor-stat-alert"><span>Blocked</span><strong data-floor-stat="blocked">0</strong></div>
      <div class="ops-floor-stat"><span>Due Today</span><strong data-floor-stat="due">0</strong></div>
    </div>

    <div class="ops-floor-clock" aria-label="Current time and sync status">
      <strong data-floor-clock>--:--</strong>
      <span><i aria-hidden="true"></i> Live - <b data-floor-sync>Sync pending</b></span>
    </div>
  </header>

  <main class="ops-floor-grid" aria-live="polite">
    <section class="ops-floor-column" data-floor-column="scheduled">
      <header><span>Scheduled</span><strong data-floor-count="scheduled">0</strong></header>
      <div class="ops-floor-list" data-floor-list="scheduled"></div>
    </section>

    <section class="ops-floor-column ops-floor-column-primary" data-floor-column="in_progress">
      <header><span>In Progress</span><strong data-floor-count="in_progress">0</strong></header>
      <div class="ops-floor-list" data-floor-list="in_progress"></div>
    </section>

    <section class="ops-floor-column ops-floor-column-blocked" data-floor-column="blocked">
      <header><span>Blocked</span><strong data-floor-count="blocked">0</strong></header>
      <div class="ops-floor-list" data-floor-list="blocked"></div>
    </section>

    <section class="ops-floor-column" data-floor-column="qc">
      <header><span>QC</span><strong data-floor-count="qc">0</strong></header>
      <div class="ops-floor-list" data-floor-list="qc"></div>
    </section>

    <section class="ops-floor-column" data-floor-column="complete">
      <header><span>Work Complete</span><strong data-floor-count="complete">0</strong></header>
      <div class="ops-floor-list" data-floor-list="complete"></div>
    </section>
  </main>

  <div class="ops-floor-empty" data-floor-empty hidden>No active production jobs.</div>
  <div class="ops-floor-error" data-floor-error hidden>Unable to load production jobs.</div>
</section>
<script>
window.slateOpsFloorBoard = <?php echo wp_json_encode($floor_settings); ?>;
</script>
<script src="<?php echo esc_url(SLATE_OPS_URL . 'assets/js/production-floor-board.js?ver=' . rawurlencode((string) $floor_js_ver)); ?>" defer></script>
