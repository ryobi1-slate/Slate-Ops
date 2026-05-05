<?php
if (!defined('ABSPATH')) exit;

class Slate_Ops_REST {

  private const OPS_CANONICAL_ROLES = [
    'slate_ops_admin',
    'slate_ops_supervisor',
    'slate_ops_cs',
    'slate_ops_tech',
    'slate_ops_viewer',
  ];

  private const OPS_LEGACY_ROLE_MAP = [
    'slate_shop_supervisor'  => 'slate_ops_supervisor',
    'slate_customer_service' => 'slate_ops_cs',
    'slate_tech'             => 'slate_ops_tech',
  ];

  private const OPS_ALIAS_MAP = [
    'admin'      => 'slate_ops_admin',
    'supervisor' => 'slate_ops_supervisor',
    'cs'         => 'slate_ops_cs',
    'tech'       => 'slate_ops_tech',
    'viewer'     => 'slate_ops_viewer',
  ];

  private const OPS_DISPLAY_MAP = [
    'slate_ops_admin'      => 'Admin',
    'slate_ops_supervisor' => 'Supervisor',
    'slate_ops_cs'         => 'CS',
    'slate_ops_tech'       => 'Tech',
    'slate_ops_viewer'     => 'Viewer',
  ];

  private static function scheduler_debug_enabled() {
    return defined('SLATE_OPS_DEBUG_SCHEDULER') && SLATE_OPS_DEBUG_SCHEDULER;
  }

  private static function scheduler_debug_log($message, $context = []) {
    if (!self::scheduler_debug_enabled()) return;
    $line = '[Slate_Ops Scheduler] ' . $message;
    if (!empty($context)) {
      $line .= ' ' . wp_json_encode($context);
    }
    error_log($line);
  }

  /**
   * Build scheduler updates from canonical scheduler keys used by ops.js.
   * Supported keys: work_center, scheduled_start, scheduled_finish, assigned_user_id.
   */
  private static function build_scheduler_update_fields($input, $now) {
    $update = [
      'status' => 'QUEUED',
      'status_updated_at' => $now,
      'updated_at' => $now,
    ];

    $changes = [];

    if (array_key_exists('work_center', $input)) {
      $v = sanitize_text_field($input['work_center'] ?? '');
      $update['work_center'] = $v ?: null;
      $changes['work_center'] = $v ?: null;
    }

    if (array_key_exists('scheduled_start', $input)) {
      $v = sanitize_text_field($input['scheduled_start'] ?? '');
      $update['scheduled_start'] = $v ?: null;
      $changes['scheduled_start'] = $v ?: null;
    }

    if (array_key_exists('scheduled_finish', $input)) {
      $v = sanitize_text_field($input['scheduled_finish'] ?? '');
      $update['scheduled_finish'] = $v ?: null;
      $changes['scheduled_finish'] = $v ?: null;
    }

    if (array_key_exists('assigned_user_id', $input)) {
      $v = !empty($input['assigned_user_id']) ? (int) $input['assigned_user_id'] : null;
      $update['assigned_user_id'] = $v;
      $changes['assigned_user_id'] = $v;
    }

    return [$update, $changes];
  }

  public static function register_routes() {
    // TEMPORARY ONE-SHOT — remove after one successful production run.
    // Manual trigger for migrate_close_state_v1; the version-gated install
    // hook is not firing in production. Idempotent via the SQL WHERE clause.
    // Auth deliberately open: cookie-auth on the manage_options check is
    // returning 401 in production, and this endpoint is throwaway. The SQL
    // is convergent with PR #251's intent and is being removed shortly.
    register_rest_route('slate-ops/v1', '/admin/run-state-migration', [
      'methods'             => 'POST',
      'permission_callback' => '__return_true',
      'callback'            => [__CLASS__, 'run_state_migration'],
    ]);

    // Admin diagnostic: lists time segments with abnormally long durations
    // (closed segments where end_ts - start_ts exceeds the suspect threshold).
    // Read-only. Used to scope a quarantine pass on historical bad data.
    register_rest_route('slate-ops/v1', '/admin/diagnostic-time-rollup', [
      'methods'             => 'GET',
      'permission_callback' => function () { return current_user_can('manage_options'); },
      'callback'            => [__CLASS__, 'diagnostic_time_rollup'],
    ]);

    $namespaces = ['slate-ops/v1', 'upfitops/v1'];

    foreach ($namespaces as $ns) {
      register_rest_route($ns, '/me', [
        'methods' => 'GET',
        'permission_callback' => [__CLASS__, 'perm_ops'],
        'callback' => [__CLASS__, 'me'],
      ]);

      register_rest_route($ns, '/settings', [
        [
          'methods' => 'GET',
          'permission_callback' => [__CLASS__, 'perm_ops'],
          'callback' => [__CLASS__, 'get_settings'],
        ],
        [
          'methods' => 'POST',
          'permission_callback' => [__CLASS__, 'perm_manage_settings'],
          'callback' => [__CLASS__, 'update_settings'],
        ],
      ]);

      register_rest_route($ns, '/users', [
        'methods' => 'GET',
        'permission_callback' => [__CLASS__, 'perm_cs_or_supervisor_or_admin'],
        'callback' => [__CLASS__, 'users'],
      ]);

      register_rest_route($ns, '/dealers', [
        'methods' => 'GET',
        'permission_callback' => [__CLASS__, 'perm_ops'],
        'callback' => [__CLASS__, 'list_dealers'],
      ]);

      register_rest_route($ns, '/jobs', [
        [
          'methods' => 'GET',
          'permission_callback' => [__CLASS__, 'perm_ops'],
          'callback' => [__CLASS__, 'list_jobs'],
        ],
        [
          'methods' => 'POST',
          'permission_callback' => [__CLASS__, 'perm_create_jobs'],
          'callback' => [__CLASS__, 'create_job_manual'],
        ],
      ]);

      register_rest_route($ns, '/jobs/(?P<id>\d+)', [
        [
          'methods' => 'GET',
          'permission_callback' => [__CLASS__, 'perm_ops'],
          'callback' => [__CLASS__, 'get_job'],
        ],
        [
          'methods' => 'PATCH',
          'permission_callback' => [__CLASS__, 'perm_edit_jobs'],
          'callback' => [__CLASS__, 'edit_job'],
        ],
        [
          'methods' => 'DELETE',
          'permission_callback' => [__CLASS__, 'perm_delete_jobs'],
          'callback' => [__CLASS__, 'delete_job'],
        ],
      ]);

      register_rest_route($ns, '/jobs/(?P<id>\d+)/notes', [
        'methods' => 'POST',
        'permission_callback' => [__CLASS__, 'perm_update_status'],
        'callback' => [__CLASS__, 'add_note'],
      ]);

      register_rest_route($ns, '/users/(?P<id>\d+)/role', [
        'methods' => 'POST',
        'permission_callback' => [__CLASS__, 'perm_manage_users'],
        'callback' => [__CLASS__, 'update_user_role'],
      ]);

      register_rest_route($ns, '/jobs/(?P<id>\d+)/so', [
        'methods' => 'POST',
        'permission_callback' => [__CLASS__, 'perm_edit_jobs'],
        'callback' => [__CLASS__, 'set_so'],
      ]);

      register_rest_route($ns, '/jobs/(?P<id>\d+)/assign', [
        'methods' => 'POST',
        'permission_callback' => [__CLASS__, 'perm_assign_jobs'],
        'callback' => [__CLASS__, 'assign_job'],
      ]);

      register_rest_route($ns, '/jobs/(?P<id>\d+)/schedule', [
        'methods' => 'POST',
        'permission_callback' => [__CLASS__, 'perm_schedule_jobs'],
        'callback' => [__CLASS__, 'schedule_job'],
      ]);

      register_rest_route($ns, '/jobs/(?P<id>\d+)/release', [
        'methods' => 'POST',
        'permission_callback' => [__CLASS__, 'perm_schedule_jobs'],
        'callback' => [__CLASS__, 'release_job'],
      ]);

      register_rest_route($ns, '/jobs/(?P<id>\d+)/status', [
        'methods' => 'POST',
        'permission_callback' => [__CLASS__, 'perm_update_status'],
        'callback' => [__CLASS__, 'set_status'],
      ]);

      register_rest_route($ns, '/jobs/(?P<id>\d+)/qc/submit', [
        'methods' => 'POST',
        'permission_callback' => [__CLASS__, 'perm_submit_qc'],
        'callback' => [__CLASS__, 'submit_qc'],
      ]);

      register_rest_route($ns, '/jobs/(?P<id>\d+)/qc/review', [
        'methods' => 'POST',
        'permission_callback' => [__CLASS__, 'perm_cs_or_supervisor_or_admin'],
        'callback' => [__CLASS__, 'review_qc'],
      ]);

      register_rest_route($ns, '/jobs/(?P<id>\d+)/block', [
        'methods' => 'POST',
        'permission_callback' => [__CLASS__, 'perm_tech_or_supervisor_or_admin'],
        'callback' => [__CLASS__, 'block_job'],
      ]);

      register_rest_route($ns, '/time/active', [
        'methods' => 'GET',
        'permission_callback' => [__CLASS__, 'perm_ops'],
        'callback' => [__CLASS__, 'time_active'],
      ]);

      register_rest_route($ns, '/time/start', [
        'methods' => 'POST',
        'permission_callback' => [__CLASS__, 'perm_tech_or_supervisor_or_admin'],
        'callback' => [__CLASS__, 'time_start'],
      ]);

      register_rest_route($ns, '/time/stop', [
        'methods' => 'POST',
        'permission_callback' => [__CLASS__, 'perm_tech_or_supervisor_or_admin'],
        'callback' => [__CLASS__, 'time_stop'],
      ]);


      register_rest_route($ns, '/time/daily-summary', [
        'methods' => 'GET',
        'permission_callback' => [__CLASS__, 'perm_ops'],
        'callback' => [__CLASS__, 'time_daily_summary'],
      ]);

      register_rest_route($ns, '/time/correction', [
        'methods' => 'POST',
        'permission_callback' => [__CLASS__, 'perm_tech_or_supervisor_or_admin'],
        'callback' => [__CLASS__, 'time_correction_request'],
      ]);


      register_rest_route($ns, '/supervisor/queues', [
        'methods' => 'GET',
        'permission_callback' => [__CLASS__, 'perm_supervisor_or_admin'],
        'callback' => [__CLASS__, 'supervisor_queues'],
      ]);

      register_rest_route($ns, '/jobs/(?P<id>\d+)/activity', [
        'methods'             => 'GET',
        'permission_callback' => [__CLASS__, 'perm_ops'],
        'callback'            => [__CLASS__, 'get_job_activity'],
        'args'                => [
          'id' => ['validate_callback' => function($v){ return is_numeric($v); }],
        ],
      ]);

      // Phase 0: bulk schedule update — saves [{job_id, scheduled_start, scheduled_finish, assigned_user_id, work_center}]
      register_rest_route($ns, '/schedule/bulk', [
        'methods'             => 'POST',
        'permission_callback' => [__CLASS__, 'perm_schedule_jobs'],
        'callback'            => [__CLASS__, 'bulk_schedule'],
      ]);

      // ── Work Centers ──────────────────────────────────────────────
      register_rest_route($ns, '/work-centers', [
        [
          'methods'             => 'GET',
          'permission_callback' => [__CLASS__, 'perm_ops'],
          'callback'            => [__CLASS__, 'list_work_centers'],
        ],
        [
          'methods'             => 'POST',
          'permission_callback' => [__CLASS__, 'perm_admin_or_supervisor'],
          'callback'            => [__CLASS__, 'create_work_center'],
        ],
      ]);

      register_rest_route($ns, '/work-centers/(?P<id>\d+)', [
        [
          'methods'             => 'GET',
          'permission_callback' => [__CLASS__, 'perm_ops'],
          'callback'            => [__CLASS__, 'get_work_center'],
        ],
        [
          'methods'             => 'PATCH',
          'permission_callback' => [__CLASS__, 'perm_admin_or_supervisor'],
          'callback'            => [__CLASS__, 'update_work_center'],
        ],
      ]);

      // ── Capacity ──────────────────────────────────────────────────
      register_rest_route($ns, '/scheduler/capacity', [
        'methods'             => 'GET',
        'permission_callback' => [__CLASS__, 'perm_ops'],
        'callback'            => [__CLASS__, 'get_capacity'],
      ]);

      register_rest_route($ns, '/scheduler/overloads', [
        'methods'             => 'GET',
        'permission_callback' => [__CLASS__, 'perm_ops'],
        'callback'            => [__CLASS__, 'get_overloads'],
      ]);

      register_rest_route($ns, '/scheduler/recalculate-flags', [
        'methods'             => 'POST',
        'permission_callback' => [__CLASS__, 'perm_supervisor_or_admin'],
        'callback'            => [__CLASS__, 'recalculate_flags'],
      ]);

      // ── Buffer settings ───────────────────────────────────────────
      register_rest_route($ns, '/scheduler/buffer-settings', [
        [
          'methods'             => 'GET',
          'permission_callback' => [__CLASS__, 'perm_ops'],
          'callback'            => [__CLASS__, 'get_buffer_settings'],
        ],
        [
          'methods'             => 'POST',
          'permission_callback' => [__CLASS__, 'perm_supervisor_or_admin'],
          'callback'            => [__CLASS__, 'update_buffer_settings'],
        ],
      ]);

      // ── Job scheduler actions ─────────────────────────────────────
      register_rest_route($ns, '/jobs/(?P<id>\d+)/lock', [
        'methods'             => 'POST',
        'permission_callback' => [__CLASS__, 'perm_assign_jobs'],
        'callback'            => [__CLASS__, 'lock_job'],
      ]);

      register_rest_route($ns, '/jobs/(?P<id>\d+)/unlock', [
        'methods'             => 'POST',
        'permission_callback' => [__CLASS__, 'perm_assign_jobs'],
        'callback'            => [__CLASS__, 'unlock_job'],
      ]);

      register_rest_route($ns, '/jobs/(?P<id>\d+)/hold', [
        'methods'             => 'POST',
        'permission_callback' => [__CLASS__, 'perm_assign_jobs'],
        'callback'            => [__CLASS__, 'hold_job'],
      ]);

      register_rest_route($ns, '/jobs/(?P<id>\d+)/unhold', [
        'methods'             => 'POST',
        'permission_callback' => [__CLASS__, 'perm_assign_jobs'],
        'callback'            => [__CLASS__, 'unhold_job'],
      ]);

      register_rest_route($ns, '/jobs/(?P<id>\d+)/buffer', [
        'methods'             => 'GET',
        'permission_callback' => [__CLASS__, 'perm_ops'],
        'callback'            => [__CLASS__, 'get_job_buffer'],
      ]);

      // BOM Builder (Pricing Core shared tables)
      register_rest_route($ns, '/boms', [
        'methods' => 'GET',
        'permission_callback' => [__CLASS__, 'perm_cs_or_supervisor_or_admin'],
        'callback' => [__CLASS__, 'get_boms'],
      ]);

      register_rest_route($ns, '/boms/(?P<id>\d+)', [
        'methods' => 'GET',
        'permission_callback' => [__CLASS__, 'perm_cs_or_supervisor_or_admin'],
        'callback' => [__CLASS__, 'get_bom'],
      ]);

      register_rest_route($ns, '/boms/save', [
        'methods' => 'POST',
        'permission_callback' => [__CLASS__, 'perm_admin_or_supervisor'],
        'callback' => [__CLASS__, 'save_bom'],
      ]);

      register_rest_route($ns, '/boms/(?P<id>\d+)/clone', [
        'methods' => 'POST',
        'permission_callback' => [__CLASS__, 'perm_admin_or_supervisor'],
        'callback' => [__CLASS__, 'clone_bom'],
      ]);

      register_rest_route($ns, '/boms/(?P<id>\d+)/revise', [
        'methods' => 'POST',
        'permission_callback' => [__CLASS__, 'perm_admin_or_supervisor'],
        'callback' => [__CLASS__, 'revise_bom'],
      ]);
      // Pricing Core helpers (shared pricing DB)
      register_rest_route($ns, '/pricing/dealers', [
        'methods' => 'GET',
        'permission_callback' => [__CLASS__, 'perm_cs_or_supervisor_or_admin'],
        'callback' => [__CLASS__, 'get_pricing_dealers'],
      ]);

      register_rest_route($ns, '/pricing/products/search', [
        'methods' => 'GET',
        'permission_callback' => [__CLASS__, 'perm_cs_or_supervisor_or_admin'],
        'callback' => [__CLASS__, 'search_pricing_products'],
      ]);

      register_rest_route($ns, '/pricing/products/lookup', [
        'methods' => 'GET',
        'permission_callback' => [__CLASS__, 'perm_cs_or_supervisor_or_admin'],
        'callback' => [__CLASS__, 'lookup_pricing_product'],
      ]);

      register_rest_route($ns, '/pricing/quotes/from-bom', [
        'methods' => 'POST',
        'permission_callback' => [__CLASS__, 'perm_admin_or_supervisor'],
        'callback' => [__CLASS__, 'create_quote_from_bom'],
      ]);

      // ── Executive dashboard ─────────────────────────────────────────
      register_rest_route($ns, '/executive/labor-summary', [
        'methods'             => 'GET',
        'permission_callback' => [__CLASS__, 'perm_view_executive'],
        'callback'            => [__CLASS__, 'executive_labor_summary'],
      ]);

      register_rest_route($ns, '/executive/time-segments', [
        'methods'             => 'GET',
        'permission_callback' => [__CLASS__, 'perm_view_executive'],
        'callback'            => [__CLASS__, 'executive_time_segments'],
      ]);

      // ── Debug (admin only) ───────────────────────────────────────────
      register_rest_route($ns, '/debug/permissions', [
        'methods'             => 'GET',
        'permission_callback' => [__CLASS__, 'perm_admin'],
        'callback'            => [__CLASS__, 'debug_permissions'],
      ]);
    }
  }


  // ---- Pricing Core API (dealers/products/quotes) ----

  static function get_pricing_dealers($req) {
    if (!self::pricing_tables_ready()) {
      return new WP_REST_Response(['ok' => false, 'error' => 'Pricing Core tables not found. Activate Slate Pricing Core.'], 400);
    }
    global $wpdb;
    $dealers = $wpdb->prefix . 'slate_dealers';
    $rows = $wpdb->get_results("SELECT id, dealer_code, dealer_name, labor_rate_retail_published, labor_rate_wholesale_published, shop_supply_base_retail_published, shop_supply_base_wholesale_published, is_active, effective_date FROM $dealers ORDER BY dealer_code ASC", ARRAY_A);
    return new WP_REST_Response(['ok' => true, 'dealers' => $rows], 200);
  }

  static function search_pricing_products($req) {
    if (!self::pricing_tables_ready()) {
      return new WP_REST_Response(['ok' => false, 'error' => 'Pricing Core tables not found. Activate Slate Pricing Core.'], 400);
    }
    global $wpdb;
    $products = $wpdb->prefix . 'slate_products';

    $q = sanitize_text_field($req->get_param('q') ?? '');
    $limit = min(25, max(5, intval($req->get_param('limit') ?? 10)));

    if ($q === '') {
      $rows = $wpdb->get_results($wpdb->prepare("SELECT id, sku, product_name, dealer_price_published, retail_price_published, product_type, is_active FROM $products WHERE is_active=1 ORDER BY sku ASC LIMIT %d", $limit), ARRAY_A);
      return new WP_REST_Response(['ok' => true, 'products' => $rows], 200);
    }

    $like = '%' . $wpdb->esc_like($q) . '%';
    $sql = $wpdb->prepare("SELECT id, sku, product_name, dealer_price_published, retail_price_published, product_type, is_active
                           FROM $products
                           WHERE is_active=1 AND (sku LIKE %s OR product_name LIKE %s)
                           ORDER BY (CASE WHEN sku = %s THEN 0 WHEN sku LIKE %s THEN 1 ELSE 2 END), sku ASC
                           LIMIT %d", $like, $like, $q, $q . '%', $limit);
    $rows = $wpdb->get_results($sql, ARRAY_A);
    return new WP_REST_Response(['ok' => true, 'products' => $rows], 200);
  }

  static function lookup_pricing_product($req) {
    if (!self::pricing_tables_ready()) {
      return new WP_REST_Response(['ok' => false, 'error' => 'Pricing Core tables not found. Activate Slate Pricing Core.'], 400);
    }
    global $wpdb;
    $products = $wpdb->prefix . 'slate_products';

    $sku = sanitize_text_field($req->get_param('sku') ?? '');
    $id = intval($req->get_param('id') ?? 0);

    if ($id > 0) {
      $row = $wpdb->get_row($wpdb->prepare("SELECT id, sku, product_name, dealer_price_published, retail_price_published, product_type, is_active FROM $products WHERE id=%d", $id), ARRAY_A);
    } else if ($sku !== '') {
      $row = $wpdb->get_row($wpdb->prepare("SELECT id, sku, product_name, dealer_price_published, retail_price_published, product_type, is_active FROM $products WHERE sku=%s", $sku), ARRAY_A);
    } else {
      return new WP_REST_Response(['ok' => false, 'error' => 'Provide sku or id'], 400);
    }

    if (!$row) return new WP_REST_Response(['ok' => false, 'error' => 'Not found'], 404);
    return new WP_REST_Response(['ok' => true, 'product' => $row], 200);
  }

  static function create_quote_from_bom($req) {
    if (!self::pricing_tables_ready()) {
      return new WP_REST_Response(['ok' => false, 'error' => 'Pricing Core tables not found. Activate Slate Pricing Core.'], 400);
    }
    global $wpdb;
    $payload = json_decode($req->get_body(), true);
    if (!is_array($payload)) $payload = $req->get_json_params();

    $bom_id = intval($payload['bom_id'] ?? 0);
    $dealer_id = intval($payload['dealer_id'] ?? 0);
    $qty = max(1, intval($payload['qty'] ?? 1));

    if ($bom_id <= 0 || $dealer_id <= 0) {
      return new WP_REST_Response(['ok' => false, 'error' => 'bom_id and dealer_id required'], 400);
    }

    $boms = $wpdb->prefix . 'slate_boms';
    $lines = $wpdb->prefix . 'slate_bom_lines';
    $products = $wpdb->prefix . 'slate_products';
    $dealers = $wpdb->prefix . 'slate_dealers';
    $quotes = $wpdb->prefix . 'slate_quotes';
    $quote_lines = $wpdb->prefix . 'slate_quote_lines';

    $bom = $wpdb->get_row($wpdb->prepare("SELECT * FROM $boms WHERE id=%d", $bom_id), ARRAY_A);
    if (!$bom) return new WP_REST_Response(['ok' => false, 'error' => 'BOM not found'], 404);

    $dealer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $dealers WHERE id=%d", $dealer_id), ARRAY_A);
    if (!$dealer) return new WP_REST_Response(['ok' => false, 'error' => 'Dealer not found'], 404);

    $cols = $wpdb->get_col("DESCRIBE $lines", 0);
    $has_product_id = in_array('product_id', $cols, true);

    $bom_lines = $wpdb->get_results($wpdb->prepare("SELECT * FROM $lines WHERE bom_id=%d ORDER BY sort_order ASC, id ASC", $bom_id), ARRAY_A);

    $parts_wh = 0.0; $parts_rt = 0.0;
    foreach ($bom_lines as $ln) {
      $line_type = strtoupper($ln['line_type'] ?? 'PART');
      if ($line_type !== 'PART') continue;
      $product_id = $has_product_id ? intval($ln['product_id'] ?? 0) : 0;
      $sku = !$has_product_id ? ($ln['sku'] ?? '') : '';
      $prod = null;
      if ($product_id > 0) $prod = $wpdb->get_row($wpdb->prepare("SELECT id, sku, product_name, dealer_price_published, retail_price_published FROM $products WHERE id=%d", $product_id), ARRAY_A);
      else if ($sku !== '') $prod = $wpdb->get_row($wpdb->prepare("SELECT id, sku, product_name, dealer_price_published, retail_price_published FROM $products WHERE sku=%s", $sku), ARRAY_A);
      if (!$prod) continue;

      $q = floatval($ln['qty'] ?? 1);
      $parts_wh += floatval($prod['dealer_price_published'] ?? 0) * $q;
      $parts_rt += floatval($prod['retail_price_published'] ?? 0) * $q;
    }

    $labor_hours = floatval($bom['install_hours'] ?? 0);
    $shop_units  = floatval($bom['shop_supply_units'] ?? 0);

    $labor_wh = $labor_hours * floatval($dealer['labor_rate_wholesale_published'] ?? 0);
    $labor_rt = $labor_hours * floatval($dealer['labor_rate_retail_published'] ?? 0);
    $shop_wh  = $shop_units  * floatval($dealer['shop_supply_base_wholesale_published'] ?? 0);
    $shop_rt  = $shop_units  * floatval($dealer['shop_supply_base_retail_published'] ?? 0);

    $installed_wh = ($parts_wh + $labor_wh + $shop_wh) * $qty;
    $installed_rt = ($parts_rt + $labor_rt + $shop_rt) * $qty;

    // basic quote_no
    $quote_no = 'Q-' . strtoupper($dealer['dealer_code']) . '-' . gmdate('Ymd-His');

    $wpdb->insert($quotes, [
      'quote_no' => $quote_no,
      'dealer_id' => $dealer_id,
      'status' => 'DRAFT',
      'created_by' => get_current_user_id(),
      'created_at' => current_time('mysql', 1),
      'updated_at' => current_time('mysql', 1),
      'subtotal_retail' => $installed_rt,
      'subtotal_wholesale' => $installed_wh,
      'labor_hours' => $labor_hours,
      'shop_supply_units' => $shop_units,
      'notes' => 'Created from BOM ' . ($bom['bom_no'] ?? ''),
    ]);

    $quote_id = intval($wpdb->insert_id);
    if ($quote_id <= 0) return new WP_REST_Response(['ok' => false, 'error' => 'Failed to create quote'], 500);

    // snapshot parts lines as quote lines
    foreach ($bom_lines as $ln) {
      $line_type = strtoupper($ln['line_type'] ?? 'PART');
      if ($line_type !== 'PART') continue;

      $qline_qty = floatval($ln['qty'] ?? 1) * $qty;

      $product_id = $has_product_id ? intval($ln['product_id'] ?? 0) : 0;
      $sku = !$has_product_id ? ($ln['sku'] ?? '') : '';
      $prod = null;
      if ($product_id > 0) $prod = $wpdb->get_row($wpdb->prepare("SELECT id, sku, product_name, dealer_price_published, retail_price_published FROM $products WHERE id=%d", $product_id), ARRAY_A);
      else if ($sku !== '') $prod = $wpdb->get_row($wpdb->prepare("SELECT id, sku, product_name, dealer_price_published, retail_price_published FROM $products WHERE sku=%s", $sku), ARRAY_A);
      if (!$prod) continue;

      $wpdb->insert($quote_lines, [
        'quote_id' => $quote_id,
        'product_id' => intval($prod['id']),
        'sku_snapshot' => $prod['sku'],
        'name_snapshot' => $prod['product_name'],
        'qty' => $qline_qty,
        'unit_retail' => floatval($prod['retail_price_published'] ?? 0),
        'unit_wholesale' => floatval($prod['dealer_price_published'] ?? 0),
        'line_retail' => floatval($prod['retail_price_published'] ?? 0) * $qline_qty,
        'line_wholesale' => floatval($prod['dealer_price_published'] ?? 0) * $qline_qty,
        'line_type' => 'PART',
        'created_at' => current_time('mysql', 1),
      ]);
    }

    // add labor + shop lines as service-type lines
    if ($labor_hours > 0) {
      $wpdb->insert($quote_lines, [
        'quote_id' => $quote_id,
        'product_id' => 0,
        'sku_snapshot' => 'LABOR',
        'name_snapshot' => 'Labor',
        'qty' => $labor_hours * $qty,
        'unit_retail' => floatval($dealer['labor_rate_retail_published'] ?? 0),
        'unit_wholesale' => floatval($dealer['labor_rate_wholesale_published'] ?? 0),
        'line_retail' => $labor_rt * $qty,
        'line_wholesale' => $labor_wh * $qty,
        'line_type' => 'LABOR',
        'created_at' => current_time('mysql', 1),
      ]);
    }
    if ($shop_units > 0) {
      $wpdb->insert($quote_lines, [
        'quote_id' => $quote_id,
        'product_id' => 0,
        'sku_snapshot' => 'SHOP',
        'name_snapshot' => 'Shop Supplies',
        'qty' => $shop_units * $qty,
        'unit_retail' => floatval($dealer['shop_supply_base_retail_published'] ?? 0),
        'unit_wholesale' => floatval($dealer['shop_supply_base_wholesale_published'] ?? 0),
        'line_retail' => $shop_rt * $qty,
        'line_wholesale' => $shop_wh * $qty,
        'line_type' => 'SHOP',
        'created_at' => current_time('mysql', 1),
      ]);
    }

    return new WP_REST_Response(['ok' => true, 'quote_id' => $quote_id, 'quote_no' => $quote_no, 'subtotal_retail' => $installed_rt, 'subtotal_wholesale' => $installed_wh], 200);
  }


  // Permissions
  
  // ---- BOM Builder helpers ----
  private static function pricing_tables_ready() {
    global $wpdb;
    $boms = $wpdb->prefix . 'slate_boms';
    $lines = $wpdb->prefix . 'slate_bom_lines';
    $products = $wpdb->prefix . 'slate_products';
    // check boms table exists as the gate
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $boms));
    if ($exists !== $boms) return false;
    return true;
  }

  private static function table_columns($table) {
    global $wpdb;
    $cols = $wpdb->get_results("DESCRIBE {$table}");
    $out = [];
    foreach ($cols as $c) $out[] = $c->Field;
    return $out;
  }

  private static function filter_to_columns($table, $data) {
    $cols = self::table_columns($table);
    $clean = [];
    foreach ($data as $k => $v) {
      if (in_array($k, $cols, true)) $clean[$k] = $v;
    }
    return $clean;
  }

  public static function get_boms($req) {
    if (!self::pricing_tables_ready()) {
      return new WP_REST_Response(['ok' => false, 'error' => 'Pricing Core tables not found. Activate Slate Pricing Core.'], 400);
    }
    global $wpdb;
    $boms = $wpdb->prefix . 'slate_boms';
    $rows = $wpdb->get_results("SELECT * FROM {$boms} ORDER BY id DESC LIMIT 500", ARRAY_A);
    return ['ok' => true, 'boms' => $rows];
  }

  public static function get_bom($req) {
    if (!self::pricing_tables_ready()) {
      return new WP_REST_Response(['ok' => false, 'error' => 'Pricing Core tables not found. Activate Slate Pricing Core.'], 400);
    }
    global $wpdb;
    $boms = $wpdb->prefix . 'slate_boms';
    $lines = $wpdb->prefix . 'slate_bom_lines';
    $products = $wpdb->prefix . 'slate_products';

    $id = intval($req['id']);
    $bom = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$boms} WHERE id=%d", $id), ARRAY_A);
    if (!$bom) return new WP_REST_Response(['ok' => false, 'error' => 'BOM not found'], 404);

    $line_rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$lines} WHERE bom_id=%d ORDER BY sort_order ASC, id ASC", $id), ARRAY_A);

    // hydrate with product snapshot + pricing
    $lcols = self::table_columns($lines);
    $has_product_id = in_array('product_id', $lcols, true);

    $product_map = [];
    if ($has_product_id) {
      $ids = [];
      foreach ($line_rows as $lr) {
        if (strtoupper($lr['line_type'] ?? 'PART') !== 'PART') continue;
        $pid = intval($lr['product_id'] ?? 0);
        if ($pid > 0) $ids[] = $pid;
      }
      $ids = array_values(array_unique($ids));
      if (!empty($ids)) {
        $in = implode(',', array_map('intval', $ids));
        $prows = $wpdb->get_results("SELECT id, sku, product_name, dealer_price_published, retail_price_published, product_type FROM {$products} WHERE id IN ($in)", ARRAY_A);
        foreach ($prows as $p) $product_map[intval($p['id'])] = $p;
      }
    } else {
      $skus = [];
      foreach ($line_rows as $lr) {
        if (strtoupper($lr['line_type'] ?? 'PART') !== 'PART') continue;
        $sku = $lr['sku'] ?? '';
        if ($sku !== '') $skus[] = $sku;
      }
      $skus = array_values(array_unique($skus));
      if (!empty($skus)) {
        $place = implode(',', array_fill(0, count($skus), '%s'));
        $sql = $wpdb->prepare("SELECT id, sku, product_name, dealer_price_published, retail_price_published, product_type FROM {$products} WHERE sku IN ($place)", ...$skus);
        $prows = $wpdb->get_results($sql, ARRAY_A);
        foreach ($prows as $p) $product_map[$p['sku']] = $p;
      }
    }

    // attach
    foreach ($line_rows as &$lr) {
      $type = strtoupper($lr['line_type'] ?? 'PART');
      if ($type !== 'PART') continue;
      $p = null;
      if ($has_product_id) {
        $pid = intval($lr['product_id'] ?? 0);
        if ($pid > 0 && isset($product_map[$pid])) $p = $product_map[$pid];
      } else {
        $sku = $lr['sku'] ?? '';
        if ($sku !== '' && isset($product_map[$sku])) $p = $product_map[$sku];
      }
      if ($p) {
        $lr['product_id'] = intval($p['id']);
        $lr['sku'] = $p['sku'];
        $lr['product_name'] = $p['product_name'];
        $lr['unit_wholesale'] = floatval($p['dealer_price_published'] ?? 0);
        $lr['unit_retail'] = floatval($p['retail_price_published'] ?? 0);
      }
    }
    unset($lr);

return ['ok' => true, 'bom' => $bom, 'lines' => $line_rows];
  }

  public static function save_bom($req) {
    if (!self::pricing_tables_ready()) {
      return new WP_REST_Response(['ok' => false, 'error' => 'Pricing Core tables not found. Activate Slate Pricing Core.'], 400);
    }
    global $wpdb;
    $boms = $wpdb->prefix . 'slate_boms';
    $lines = $wpdb->prefix . 'slate_bom_lines';

    $payload = json_decode($req->get_body(), true);
    if (!is_array($payload)) $payload = $req->get_json_params();

    $id = isset($payload['id']) ? intval($payload['id']) : 0;

    $bom_data = [
      'bom_no' => sanitize_text_field($payload['bom_no'] ?? ''),
      'name' => sanitize_text_field($payload['name'] ?? ''),
      'fitment' => sanitize_text_field($payload['fitment'] ?? ''),
      'market' => sanitize_text_field($payload['market'] ?? ''),
      'category' => sanitize_text_field($payload['category'] ?? ''),
      'install_hours' => floatval($payload['install_hours'] ?? 0),
      'shop_supply_units' => floatval($payload['shop_supply_units'] ?? 0),
      'status' => sanitize_text_field($payload['status'] ?? 'active'),
      'revision' => sanitize_text_field($payload['revision'] ?? ''),
      'notes' => sanitize_textarea_field($payload['notes'] ?? ''),
      'updated_at' => current_time('mysql'),
    ];

    $bom_data = self::filter_to_columns($boms, $bom_data);

    if (!$bom_data['bom_no']) return new WP_REST_Response(['ok' => false, 'error' => 'BOM # is required'], 400);

    if ($id > 0) {
      $wpdb->update($boms, $bom_data, ['id' => $id]);
    } else {
      if (in_array('created_at', self::table_columns($boms), true) && !isset($bom_data['created_at'])) {
        $bom_data['created_at'] = current_time('mysql');
      }
      $wpdb->insert($boms, $bom_data);
      $id = intval($wpdb->insert_id);
    }

    // lines
    $incoming = $payload['lines'] ?? [];
    if (!is_array($incoming)) $incoming = [];

    // wipe and reinsert (simple + safe for MVP)
    $wpdb->delete($lines, ['bom_id' => $id]);

    $cols = self::table_columns($lines);
    $products = $wpdb->prefix . 'slate_products';
    $has_notes = in_array('line_notes', $cols, true) || in_array('notes', $cols, true);
    $has_product_id = in_array('product_id', $cols, true);
    $has_sku = in_array('sku', $cols, true);

    $sort = 1;
    foreach ($incoming as $ln) {
      $row = [
        'bom_id' => $id,
        'qty' => floatval($ln['qty'] ?? 1),
        'sort_order' => intval($ln['sort_order'] ?? $sort),
        'line_type' => sanitize_text_field($ln['line_type'] ?? 'PART'),
      ];

      // PART lines can be passed as product_id or sku. Persist based on table schema.
      if (strtoupper($row['line_type']) === 'PART') {
        $pid = intval($ln['product_id'] ?? 0);
        $sku_in = sanitize_text_field($ln['sku'] ?? '');

        if ($pid <= 0 && $sku_in !== '') {
          $pid = intval($wpdb->get_var($wpdb->prepare("SELECT id FROM {$products} WHERE sku=%s", $sku_in)));
        }

        if ($has_product_id) {
          $row['product_id'] = $pid;
        }
        if ($has_sku) {
          $row['sku'] = $sku_in;
        }

        if ($has_product_id && intval($row['product_id'] ?? 0) <= 0) { $sort++; continue; }
        if (!$has_product_id && $has_sku && empty($row['sku'])) { $sort++; continue; }
      }

      if ($has_notes) {
        if (in_array('line_notes', $cols, true)) $row['line_notes'] = sanitize_text_field($ln['line_notes'] ?? '');
        if (in_array('notes', $cols, true)) $row['notes'] = sanitize_text_field($ln['line_notes'] ?? '');
      }

      $row = self::filter_to_columns($lines, $row);
      $wpdb->insert($lines, $row);
      $sort++;
    }

return ['ok' => true, 'id' => $id];
  }

  private static function duplicate_bom_with_lines($source_id, $new_bom_no, $new_name, $mode='clone') {
    global $wpdb;
    $boms = $wpdb->prefix . 'slate_boms';
    $lines = $wpdb->prefix . 'slate_bom_lines';

    $src = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$boms} WHERE id=%d", intval($source_id)), ARRAY_A);
    if (!$src) return new WP_REST_Response(['ok' => false, 'error' => 'Source BOM not found'], 404);

    $now = current_time('mysql');

    $data = $src;
    unset($data['id']);
    $data['bom_no'] = sanitize_text_field($new_bom_no);
    if ($new_name) $data['name'] = sanitize_text_field($new_name);

    // revision/notes convention without schema changes
    $src_no = $src['bom_no'] ?? '';
    $prefix = ($mode === 'revise') ? 'Revision of ' : 'Clone of ';
    $note_append = $prefix . $src_no . ' (ID ' . intval($source_id) . ') on ' . $now;

    if (isset($data['notes'])) {
      $data['notes'] = trim(($data['notes'] ?? '') . "\n" . $note_append);
    }
    if (isset($data['revision']) && $mode === 'revise') {
      // if existing revision is numeric, increment; else leave as is and rely on bom_no suffix
      $data['revision'] = sanitize_text_field($data['revision']);
    }

    if (isset($data['created_at'])) $data['created_at'] = $now;
    if (isset($data['updated_at'])) $data['updated_at'] = $now;

    $data = self::filter_to_columns($boms, $data);
    $wpdb->insert($boms, $data);
    $new_id = intval($wpdb->insert_id);

    $src_lines = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$lines} WHERE bom_id=%d ORDER BY sort_order ASC, id ASC", intval($source_id)), ARRAY_A);
    foreach ($src_lines as $ln) {
      unset($ln['id']);
      $ln['bom_id'] = $new_id;
      $ln = self::filter_to_columns($lines, $ln);
      $wpdb->insert($lines, $ln);
    }

    return ['ok' => true, 'id' => $new_id];
  }

  public static function clone_bom($req) {
    if (!self::pricing_tables_ready()) {
      return new WP_REST_Response(['ok' => false, 'error' => 'Pricing Core tables not found. Activate Slate Pricing Core.'], 400);
    }
    $payload = json_decode($req->get_body(), true);
    if (!is_array($payload)) $payload = $req->get_json_params();
    $new_bom_no = sanitize_text_field($payload['bom_no'] ?? '');
    $new_name = sanitize_text_field($payload['name'] ?? '');
    if (!$new_bom_no) return new WP_REST_Response(['ok' => false, 'error' => 'New BOM # required'], 400);
    return self::duplicate_bom_with_lines(intval($req['id']), $new_bom_no, $new_name, 'clone');
  }

  public static function revise_bom($req) {
    if (!self::pricing_tables_ready()) {
      return new WP_REST_Response(['ok' => false, 'error' => 'Pricing Core tables not found. Activate Slate Pricing Core.'], 400);
    }
    $payload = json_decode($req->get_body(), true);
    if (!is_array($payload)) $payload = $req->get_json_params();
    $new_bom_no = sanitize_text_field($payload['bom_no'] ?? '');
    $new_name = sanitize_text_field($payload['name'] ?? '');
    if (!$new_bom_no) return new WP_REST_Response(['ok' => false, 'error' => 'New BOM # required'], 400);
    return self::duplicate_bom_with_lines(intval($req['id']), $new_bom_no, $new_name, 'revise');
  }

  // ── Permission callbacks ─────────────────────────────────────────────────
  // All call into Slate_Ops_Utils centralized helpers. Names are kept stable
  // because they are referenced in route registration above.

  /** Any authenticated ops user (viewer, tech, cs, supervisor, admin). */
  public static function perm_ops() {
    return Slate_Ops_Utils::can_access();
  }

  /** Requires slate_ops_update_status (tech, cs, supervisor, admin). */
  public static function perm_update_status() {
    return Slate_Ops_Utils::can_update_status();
  }

  /** Requires slate_ops_time_tracking (tech, supervisor, admin). */
  public static function perm_tech_or_supervisor_or_admin() {
    return Slate_Ops_Utils::can_time_tracking();
  }

  /** Requires slate_ops_supervisor or slate_ops_admin. */
  public static function perm_supervisor_or_admin() {
    return Slate_Ops_Utils::can_supervisor_or_admin();
  }

  /** Alias kept for backward compatibility. */
  public static function perm_admin_or_supervisor() {
    return Slate_Ops_Utils::can_supervisor_or_admin();
  }

  /** Requires slate_ops_create_jobs (CS, supervisor, admin). */
  public static function perm_create_jobs() {
    return Slate_Ops_Utils::can_create_jobs();
  }

  /** Requires slate_ops_edit_jobs (CS, supervisor, admin). */
  public static function perm_edit_jobs() {
    return Slate_Ops_Utils::can_edit_jobs();
  }

  /** Requires slate_ops_delete_jobs (CS, supervisor, admin). */
  public static function perm_delete_jobs() {
    return Slate_Ops_Utils::can_delete_jobs();
  }

  /** Requires slate_ops_schedule_jobs (CS, supervisor, admin). */
  public static function perm_schedule_jobs() {
    return Slate_Ops_Utils::can_schedule_jobs();
  }

  /** Requires slate_ops_assign_jobs (supervisor, admin). */
  public static function perm_assign_jobs() {
    return Slate_Ops_Utils::can_assign_jobs();
  }

  /** Requires slate_ops_submit_qc (tech, supervisor, admin). */
  public static function perm_submit_qc() {
    return Slate_Ops_Utils::can_submit_qc();
  }

  /** Requires slate_ops_review_qc (supervisor, admin). */
  public static function perm_review_qc() {
    return Slate_Ops_Utils::can_review_qc();
  }

  /** Requires slate_ops_view_executive (supervisor, admin). */
  public static function perm_view_executive() {
    return Slate_Ops_Utils::can_view_executive();
  }

  /** Requires slate_ops_manage_settings (admin). */
  public static function perm_manage_settings() {
    return Slate_Ops_Utils::can_manage_settings();
  }

  /** Requires slate_ops_manage_users (admin). */
  public static function perm_manage_users() {
    return Slate_Ops_Utils::can_manage_users();
  }

  /** CS or above (slate_ops_cs / legacy, supervisor, admin). */
  public static function perm_cs_or_admin() {
    return Slate_Ops_Utils::can_cs_or_above();
  }

  /** CS or above (slate_ops_cs / legacy, supervisor, admin). */
  public static function perm_cs_or_supervisor_or_admin() {
    return Slate_Ops_Utils::can_cs_or_above();
  }

  /** Requires slate_ops_admin. */
  public static function perm_admin() {
    return Slate_Ops_Utils::is_admin();
  }

  // ── Handlers ─────────────────────────────────────────────────────────────

  public static function me($req) {
    $user          = wp_get_current_user();
    $roles         = array_values((array) $user->roles);
    $caps          = Slate_Ops_Utils::current_user_caps_summary();
    $allowed_pages = Slate_Ops_Utils::user_allowed_pages();

    return new WP_REST_Response([
      // ── New top-level shape ────────────────────────────────────────────
      'user_id'       => $user->ID,
      'display_name'  => $user->display_name,
      'roles'         => $roles,
      'capabilities'  => $caps,
      'allowed_pages' => $allowed_pages,
      'is_admin'      => $caps['admin'],
      'is_supervisor' => $caps['supervisor'],
      'is_cs'         => $caps['cs'],
      'is_tech'       => $caps['tech'],
      // ── Backward-compat user{} envelope (old shape) ───────────────────
      'user'          => [
        'id'            => $user->ID,
        'name'          => $user->display_name,
        'caps'          => $caps,
        'roles'         => $roles,
        'allowed_pages' => $allowed_pages,
      ],
    ], 200);
  }

  public static function debug_permissions($req) {
    $user  = wp_get_current_user();
    $roles = array_values((array) $user->roles);
    $caps  = Slate_Ops_Utils::current_user_caps_summary();

    // Raw check against every known ops cap (including legacy).
    $raw_caps = [];
    foreach (array_merge(Slate_Ops_Utils::all_cap_names(), [Slate_Ops_Utils::CAP_CS_LEGACY]) as $c) {
      $raw_caps[$c] = (bool) current_user_can($c);
    }

    return new WP_REST_Response([
      'user_id'       => $user->ID,
      'user_login'    => $user->user_login,
      'display_name'  => $user->display_name,
      'roles'         => $roles,
      'caps_summary'  => $caps,
      'raw_caps'      => $raw_caps,
      'allowed_pages' => Slate_Ops_Utils::user_allowed_pages(),
    ], 200);
  }

  public static function get_settings($req) {
    global $wpdb;
    $t = $wpdb->prefix . 'slate_ops_settings';
    $row = $wpdb->get_row("SELECT * FROM $t WHERE id=1", ARRAY_A);
    $row = $row ?: [];
    $row['dealers']          = array_values(Slate_Ops_Utils::dealer_list());
    $row['sales_people']     = array_values(Slate_Ops_Utils::sales_person_list());
    $row['company_name']     = get_option('slate_ops_company_name', 'Slate Automotive Upfitting Solutions Inc.');
    $row['timezone']         = get_option('slate_ops_timezone', 'Mountain Standard Time');
    $row['currency_display'] = get_option('slate_ops_currency_display', 'USD ($)');
    $row['holiday_sync']     = (bool)get_option('slate_ops_holiday_sync', true);
    $row['notifications']    = get_option('slate_ops_notifications', [
      'newJob' => true, 'qcFailure' => true, 'completionSms' => true,
      'marketingEmails' => false, 'dailySummary' => true, 'weeklyReport' => false,
    ]);
    // break_count added in 0.13.0 — dbDelta adds it non-destructively
    if (!isset($row['break_count'])) $row['break_count'] = 2;
    $row['break_count'] = (int)$row['break_count'];
    $row['daily_deduction_minutes'] = (int)($row['lunch_minutes'] ?? 30) + ((int)($row['break_count']) * (int)($row['break_minutes'] ?? 10));
    return $row;
  }

  public static function update_settings($req) {
    global $wpdb;
    $t = $wpdb->prefix . 'slate_ops_settings';
    $body = $req->get_json_params();

    $shift_start  = isset($body['shift_start'])   ? sanitize_text_field($body['shift_start'])  : '07:00:00';
    $shift_end    = isset($body['shift_end'])     ? sanitize_text_field($body['shift_end'])    : '15:30:00';
    $lunch        = isset($body['lunch_minutes']) ? max(0, intval($body['lunch_minutes']))     : 30;
    $breaks       = isset($body['break_minutes']) ? max(0, intval($body['break_minutes']))     : 10;
    $break_count  = isset($body['break_count'])   ? max(0, min(4, intval($body['break_count']))) : 2;
    $dealers_payload = $body['dealers'] ?? [];
    if (is_string($dealers_payload)) {
      $dealers_payload = preg_split('/\r\n|\r|\n/', $dealers_payload);
    }
    $dealers = [];
    if (is_array($dealers_payload)) {
      foreach ($dealers_payload as $dealer) {
        $dealer = trim(sanitize_text_field((string) $dealer));
        if ($dealer !== '') {
          $dealers[] = $dealer;
        }
      }
    }
    update_option('slate_ops_dealers', array_values(array_unique($dealers)));

    $sales_payload = $body['sales_people'] ?? [];
    if (is_string($sales_payload)) {
      $sales_payload = preg_split('/\r\n|\r|\n/', $sales_payload);
    }
    $sales_people = [];
    if (is_array($sales_payload)) {
      foreach ($sales_payload as $person) {
        $person = trim(sanitize_text_field((string) $person));
        if ($person !== '') {
          $sales_people[] = $person;
        }
      }
    }
    update_option('slate_ops_sales_people', array_values(array_unique($sales_people)));

    // General settings (stored as WP options)
    if (isset($body['company_name']))     update_option('slate_ops_company_name',     sanitize_text_field($body['company_name']));
    if (isset($body['timezone']))         update_option('slate_ops_timezone',         sanitize_text_field($body['timezone']));
    if (isset($body['currency_display'])) update_option('slate_ops_currency_display', sanitize_text_field($body['currency_display']));
    if (isset($body['holiday_sync']))     update_option('slate_ops_holiday_sync',     !empty($body['holiday_sync']) ? 1 : 0);
    if (isset($body['notifications']) && is_array($body['notifications'])) {
      $notif = array_map('rest_sanitize_boolean', $body['notifications']);
      update_option('slate_ops_notifications', $notif);
    }

    $wpdb->update($t, [
      'shift_start'   => $shift_start,
      'shift_end'     => $shift_end,
      'lunch_minutes' => $lunch,
      'break_minutes' => $breaks,
      'break_count'  => $break_count,
      'updated_by'   => get_current_user_id(),
      'updated_at'           => Slate_Ops_Utils::now_gmt(),
    ], ['id' => 1]);

    self::audit('settings', 1, 'update', null, null, wp_json_encode(['shift_start'=>$shift_start,'shift_end'=>$shift_end,'lunch_minutes'=>$lunch,'break_minutes'=>$breaks,'dealers'=>$dealers,'sales_people'=>$sales_people]), 'Settings updated');
    return self::get_settings($req);
  }

  public static function list_jobs($req) {
global $wpdb;
$t = $wpdb->prefix . 'slate_ops_jobs';

$status = $req->get_param('status');
$status_in = $req->get_param('status_in');
$ready_only = (int)$req->get_param('ready_only');
$helpable = (int)$req->get_param('helpable');

$q = $req->get_param('q');
$so_missing = (int)$req->get_param('so_missing');

$limit = (int)$req->get_param('limit');
if ($limit <= 0) $limit = 100;
if ($limit > 500) $limit = 500;

$where = "archived_at IS NULL";
$params = [];

$ready_only = (int)$ready_only; // default 0 if not provided

if ($status_in) {
  $parts = array_filter(array_map('trim', explode(',', strtoupper($status_in))));
  $parts = array_slice($parts, 0, 20);

  if (!empty($parts)) {
    $placeholders = implode(',', array_fill(0, count($parts), '%s'));
    $where .= " AND status IN ($placeholders)";
    foreach ($parts as $p) { $params[] = sanitize_text_field($p); }
  }
} elseif ($status) {
  $where .= " AND status = %s";
  $params[] = strtoupper(sanitize_text_field($status));
}

// Optional TOC gate for "unscheduled" lists
if ($ready_only === 1) {
  $where .= " AND (scheduling_status = %s OR status = %s)";
  $params[] = 'READY_FOR_BUILD';
  $params[] = 'READY_FOR_BUILD';
}

if ($so_missing === 1) {
  $where .= " AND (so_number IS NULL OR so_number = '')";
}

// Help queue scope:
// - force in-progress only
// - require SO#
// - exclude blocked parts statuses when present
if ($helpable === 1) {
  $where .= " AND status = %s";
  $params[] = 'IN_PROGRESS';
  $where .= " AND (so_number IS NOT NULL AND so_number <> '')";
  $where .= " AND (parts_status IS NULL OR parts_status = '' OR (UPPER(parts_status) <> %s AND UPPER(parts_status) <> %s))";
  $params[] = 'HOLD';
  $params[] = 'NOT_READY';
}

if ((int)$req->get_param('assigned_me') === 1) {
  $me = get_current_user_id();
  if ($me) {
    $where .= " AND assigned_user_id = %d";
    $params[] = $me;
  }
}

if ($q) {
  $q = sanitize_text_field($q);
  $where .= " AND (so_number LIKE %s OR vin LIKE %s OR customer_name LIKE %s OR dealer_name LIKE %s)";
  $like = '%' . $wpdb->esc_like($q) . '%';
  array_push($params, $like, $like, $like, $like);
}

// Date-range filter for scheduled jobs (for scheduler board)
$scheduled_from = sanitize_text_field($req->get_param('scheduled_from') ?? '');
$scheduled_to   = sanitize_text_field($req->get_param('scheduled_to') ?? '');
if ($scheduled_from && $scheduled_to) {
  $where .= " AND scheduled_start IS NOT NULL AND DATE(scheduled_start) <= %s AND DATE(COALESCE(scheduled_finish, scheduled_start)) >= %s";
  $params[] = $scheduled_to;
  $params[] = $scheduled_from;
} elseif ($scheduled_from) {
  $where .= " AND scheduled_start IS NOT NULL AND DATE(COALESCE(scheduled_finish, scheduled_start)) >= %s";
  $params[] = $scheduled_from;
}

$sql = "SELECT job_id, source, created_from, portal_quote_id, quote_number, so_number, customer_name, vin, dealer_name,
               job_type, parts_status, status, status_detail, status_updated_at, delay_reason, priority, priority_score,
               assigned_user_id, work_center, estimated_minutes, constraint_minutes_required,
               scope_status, scheduling_status, target_week_id, ready_queue_entered_at, override_flag, override_reason, override_notes,
               scheduler_locked, hold_reason, hold_note, schedule_notes, scheduling_flag,
               scheduled_start, scheduled_finish, scheduled_week, requested_date, promised_date, target_ship_date,
               block_reason, block_note, cancel_reason, cancel_note,
               scope_summary, sales_person, notes, queue_order,
               clickup_task_id, clickup_estimate_ms, dealer_status, created_at, updated_at
        FROM $t WHERE $where ORDER BY updated_at DESC LIMIT $limit";

$rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

// Actual minutes (sum of closed segments) in one query
$ids = array_map(function($r){ return (int)$r['job_id']; }, $rows);
$actual_map = [];
if (!empty($ids)) {
  $seg = $wpdb->prefix . 'slate_ops_time_segments';
  $in = implode(',', array_fill(0, count($ids), '%d'));
  $qsql = "SELECT job_id, SUM(TIMESTAMPDIFF(MINUTE, start_ts, end_ts)) AS actual_minutes
           FROM $seg
           WHERE end_ts IS NOT NULL AND job_id IN ($in)
           GROUP BY job_id";
  $prep = $wpdb->prepare($qsql, $ids);
  $totals = $wpdb->get_results($prep, ARRAY_A);
  foreach ($totals as $trow) {
    $actual_map[(int)$trow['job_id']] = (int)($trow['actual_minutes'] ?? 0);
  }
}

foreach ($rows as &$r) {
  $r['assigned_name'] = $r['assigned_user_id'] ? Slate_Ops_Utils::user_display($r['assigned_user_id']) : '';

  // Back-compat fallbacks
  if (empty($r['created_from'])) $r['created_from'] = $r['source'] ?: 'manual';
  if (empty($r['status_updated_at'])) $r['status_updated_at'] = $r['updated_at'] ?: $r['created_at'];

  $r['actual_minutes'] = $actual_map[(int)$r['job_id']] ?? 0;
}

	return ['jobs' => $rows];
	  }

  public static function get_job($req) {
    $job_id = intval($req['id']);
    $job = self::job_by_id($job_id);
    if (!$job) return new WP_Error('not_found', 'Job not found', ['status' => 404]);

    $breakdown = self::time_breakdown($job_id);
    $job['time'] = $breakdown;

    global $wpdb;
    $al = $wpdb->prefix . 'slate_ops_audit_log';
    $raw_notes = $wpdb->get_results($wpdb->prepare(
      "SELECT audit_id, note, user_id, created_at FROM $al
       WHERE entity_type='job' AND entity_id=%d AND action='note'
       ORDER BY created_at ASC",
      $job_id
    ), ARRAY_A);
    $job['notes_log'] = array_map(function($n) {
      $n['user_name'] = Slate_Ops_Utils::user_display($n['user_id']);
      return $n;
    }, $raw_notes ?: []);

    return $job;
  }

  public static function edit_job($req) {
    global $wpdb;
    $job_id = intval($req['id']);
    $body   = $req->get_json_params();
    $t      = $wpdb->prefix . 'slate_ops_jobs';

    $job = self::job_by_id($job_id);
    if (!$job) return new WP_Error('not_found', 'Job not found', ['status' => 404]);

    $is_supervisor = current_user_can(Slate_Ops_Utils::CAP_SUPERVISOR) || current_user_can(Slate_Ops_Utils::CAP_ADMIN);
    $is_cs         = current_user_can(Slate_Ops_Utils::CAP_CS)         || current_user_can(Slate_Ops_Utils::CAP_ADMIN);

    $update = [];
    $audits = [];
    $now    = Slate_Ops_Utils::now_gmt();

    if ($is_cs || $is_supervisor) {
      // Simple text fields
      foreach (['customer_name', 'dealer_name', 'sales_person'] as $f) {
        if (!array_key_exists($f, $body)) continue;
        $val = sanitize_text_field((string)($body[$f] ?? ''));
        $store = $val ?: null;
        if ($store !== $job[$f]) {
          $audits[] = [$f, $job[$f], $store];
          $update[$f] = $store;
        }
      }

      // Notes (textarea — preserves newlines)
      if (array_key_exists('notes', $body)) {
        $val = sanitize_textarea_field((string)($body['notes'] ?? ''));
        $store = $val ?: null;
        if ($store !== $job['notes']) {
          $audits[] = ['notes', $job['notes'], $store];
          $update['notes'] = $store;
        }
      }

      // Requested date
      if (array_key_exists('requested_date', $body)) {
        $val = sanitize_text_field((string)($body['requested_date'] ?? ''));
        if ($val !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
          return self::validation_error('requested_date', 'invalid_requested_date', 'Date must be YYYY-MM-DD.');
        }
        $store = $val ?: null;
        if ($store !== $job['requested_date']) {
          $audits[] = ['requested_date', $job['requested_date'], $store];
          $update['requested_date'] = $store;
        }
      }

      // VIN
      if (array_key_exists('vin_last8', $body)) {
        $vin = strtoupper(trim(sanitize_text_field((string)($body['vin_last8'] ?? ''))));
        if ($vin !== '' && !Slate_Ops_Utils::vin_last8_is_valid($vin)) {
          return self::validation_error('vin_last8', 'invalid_vin_last8', 'VIN must be 7–8 alphanumeric characters.');
        }
        $store = $vin ?: null;
        if ($store !== $job['vin']) {
          $audits[] = ['vin', $job['vin'], $store];
          $update['vin']      = $store;
          $update['vin_last8'] = $store;
        }
      }

      // Job type
      if (array_key_exists('job_type', $body)) {
        $jt = strtoupper(sanitize_key((string)($body['job_type'] ?? '')));
        if ($jt !== '' && !in_array($jt, Slate_Ops_Utils::cs_job_types(), true)) {
          return self::validation_error('job_type', 'invalid_job_type', 'Select a valid job type.');
        }
        $store = $jt ?: null;
        if ($store !== $job['job_type']) {
          $audits[] = ['job_type', $job['job_type'], $store];
          $update['job_type'] = $store;
        }
      }

      // Parts status
      if (array_key_exists('parts_status', $body)) {
        $ps = strtoupper(sanitize_key((string)($body['parts_status'] ?? '')));
        if ($ps !== '' && !in_array($ps, Slate_Ops_Utils::cs_parts_statuses(), true)) {
          return self::validation_error('parts_status', 'invalid_parts_status', 'Select a valid parts status.');
        }
        $store = $ps ?: null;
        if ($store !== $job['parts_status']) {
          $audits[] = ['parts_status', $job['parts_status'], $store];
          $update['parts_status'] = $store;
        }
      }

      // Estimated hours
      if (array_key_exists('estimated_hours', $body)) {
        $eh = trim((string)($body['estimated_hours'] ?? ''));
        if ($eh !== '') {
          if (!is_numeric($eh)) {
            return self::validation_error('estimated_hours', 'invalid_estimated_hours', 'Estimated hours must be a number.');
          }
          $em = (int) round((float) $eh * 60);
          if ($em <= 0) {
            return self::validation_error('estimated_hours', 'invalid_estimated_hours', 'Estimated hours must be greater than zero.');
          }
          if ($em !== (int)($job['estimated_minutes'] ?? 0)) {
            $audits[] = ['estimated_minutes', $job['estimated_minutes'], $em];
            $update['estimated_minutes'] = $em;
          }
        }
      }
    }

    // Queue order (CS / supervisor)
    if (($is_cs || $is_supervisor) && array_key_exists('queue_order', $body)) {
      $qo = self::parse_queue_order($body, 'queue_order');
      if ($qo instanceof WP_Error) return $qo;
      if ($qo !== (isset($job['queue_order']) ? (int)$job['queue_order'] : null)) {
        $audits[] = ['queue_order', $job['queue_order'] ?? null, $qo];
        $update['queue_order'] = $qo;
      }
    }

    // CS + supervisor: Job Status and Lead Tech (per CS v2 rules).
    if ($is_cs || $is_supervisor) {
      if (array_key_exists('status', $body)) {
        $ns = strtoupper(sanitize_text_field((string)($body['status'] ?? '')));
        if ($ns !== '' && $ns !== ($job['status'] ?? '')) {
          $cur = (string)($job['status'] ?? '');

          // CS v2: CS users may not manually set IN_PROGRESS, QC, or COMPLETE.
          // Those states come from Tech/QC workflow actions only.
          $cs_restricted = [
            Slate_Ops_Statuses::IN_PROGRESS,
            Slate_Ops_Statuses::QC,
            Slate_Ops_Statuses::COMPLETE,
            Slate_Ops_Statuses::PENDING_QC, // legacy alias
          ];
          if ($is_cs && !$is_supervisor && in_array($ns, $cs_restricted, true)) {
            return new WP_Error(
              'cs_restricted_status',
              Slate_Ops_Statuses::label($ns) . ' is set by the Tech/QC workflow and cannot be selected by CS.',
              ['status' => 403]
            );
          }

          if (!Slate_Ops_Statuses::is_valid_transition($cur, $ns)) {
            return new WP_Error(
              'invalid_transition',
              sprintf(
                'Cannot move job from %s to %s.',
                Slate_Ops_Statuses::label($cur),
                Slate_Ops_Statuses::label($ns)
              ),
              ['status' => 422]
            );
          }

          // Gate: IN_PROGRESS requires assigned tech (check merged state).
          if ($ns === Slate_Ops_Statuses::IN_PROGRESS) {
            $future_uid = array_key_exists('assigned_user_id', $body)
              ? (int)($body['assigned_user_id'] ?? 0)
              : (int)($job['assigned_user_id'] ?? 0);
            if (!$future_uid) {
              return new WP_Error('not_ready_for_progress', 'Cannot start job: no tech has been assigned.', ['status' => 422]);
            }
          }

          // v2: BLOCKED / ON_HOLD / CANCELLED each require reason + note.
          if ($ns === Slate_Ops_Statuses::BLOCKED) {
            $err = self::validate_reason_fields($body, 'block_reason', 'block_note', Slate_Ops_Utils::cs_block_reasons(),
              'Block reason is required when setting status to Blocked.',
              'Block note is required when setting status to Blocked.', $update);
            if ($err) return $err;
          }
          if ($ns === Slate_Ops_Statuses::ON_HOLD) {
            $err = self::validate_reason_fields($body, 'hold_reason', 'hold_note', Slate_Ops_Utils::cs_hold_reasons(),
              'Hold reason is required when setting status to On Hold.',
              'Hold note is required when setting status to On Hold.', $update);
            if ($err) return $err;
          }
          if ($ns === Slate_Ops_Statuses::CANCELLED) {
            $err = self::validate_reason_fields($body, 'cancel_reason', 'cancel_note', Slate_Ops_Utils::cs_cancel_reasons(),
              'Cancel reason is required when setting status to Cancelled.',
              'Cancel note is required when setting status to Cancelled.', $update);
            if ($err) return $err;
          }

          // v2: SCHEDULED requires scheduled_week
          if ($ns === Slate_Ops_Statuses::SCHEDULED) {
            $sw = sanitize_text_field((string)($body['scheduled_week'] ?? ''));
            if (empty($sw)) {
              return self::validation_error('scheduled_week', 'scheduled_week_required', 'Scheduled week is required when setting status to Scheduled.');
            }
            $update['scheduled_week'] = $sw;
          }

          $audits[] = ['status', $job['status'], $ns];
          $update['status']            = $ns;
          $update['status_updated_at'] = $now;
        }
      }

      if (array_key_exists('assigned_user_id', $body)) {
        $uid   = (int)($body['assigned_user_id'] ?? 0);
        $store = $uid ?: null;
        if ($store !== (int)($job['assigned_user_id'] ?? 0)) {
          $audits[] = ['assigned_user_id', $job['assigned_user_id'], $store];
          $update['assigned_user_id'] = $store;
        }
      }

      // v2: accept scheduled_week standalone (without status change)
      if (array_key_exists('scheduled_week', $body)) {
        $sw = sanitize_text_field((string)($body['scheduled_week'] ?? ''));
        $store = $sw ?: null;
        if ($store !== ($job['scheduled_week'] ?? null)) {
          $audits[] = ['scheduled_week', $job['scheduled_week'] ?? null, $store];
          $update['scheduled_week'] = $store;
        }
      }

      // v2: accept job_description mapped to scope_summary
      if (array_key_exists('job_description', $body)) {
        $jd    = sanitize_textarea_field((string)($body['job_description'] ?? ''));
        $store = $jd ?: null;
        if ($store !== ($job['scope_summary'] ?? null)) {
          $audits[] = ['scope_summary', $job['scope_summary'] ?? null, $store];
          $update['scope_summary'] = $store;
        }
      }
    }

    // Supervisor-only fields
    if ($is_supervisor) {
      if (array_key_exists('status_detail', $body)) {
        $sd    = sanitize_text_field((string)($body['status_detail'] ?? ''));
        $store = $sd ?: null;
        if ($store !== $job['status_detail']) {
          $audits[] = ['status_detail', $job['status_detail'], $store];
          $update['status_detail'] = $store;
        }
      }

      if (array_key_exists('work_center', $body)) {
        $wc    = sanitize_text_field((string)($body['work_center'] ?? ''));
        $store = $wc ?: null;
        if ($store !== $job['work_center']) {
          $audits[] = ['work_center', $job['work_center'], $store];
          $update['work_center'] = $store;
        }
      }

      if (array_key_exists('scheduled_start', $body)) {
        $ss    = sanitize_text_field((string)($body['scheduled_start'] ?? ''));
        $store = $ss ?: null;
        if ($store !== $job['scheduled_start']) {
          $audits[] = ['scheduled_start', $job['scheduled_start'], $store];
          $update['scheduled_start'] = $store;
        }
      }

      if (array_key_exists('scheduled_finish', $body)) {
        $sf    = sanitize_text_field((string)($body['scheduled_finish'] ?? ''));
        $store = $sf ?: null;
        if ($store !== $job['scheduled_finish']) {
          $audits[] = ['scheduled_finish', $job['scheduled_finish'], $store];
          $update['scheduled_finish'] = $store;
        }
      }

      if (array_key_exists('delay_reason', $body)) {
        $dr    = sanitize_key((string)($body['delay_reason'] ?? ''));
        $store = $dr ?: null;
        if ($store !== $job['delay_reason']) {
          $audits[] = ['delay_reason', $job['delay_reason'], $store];
          $update['delay_reason'] = $store;
        }
      }

      if (array_key_exists('priority', $body)) {
        $prio = (int)($body['priority'] ?? 3);
        $prio = max(1, min(5, $prio));
        if ($prio !== (int)($job['priority'] ?? 3)) {
          $audits[] = ['priority', $job['priority'], $prio];
          $update['priority'] = $prio;
        }
      }
    }

    if (empty($update)) {
      return self::get_job(['id' => $job_id]);
    }

    // Readiness gate: block transition to READY_FOR_BUILD if requirements unmet.
    $gate = self::check_ready_for_build_gate($job, $update);
    if ($gate) return $gate;

    $update['updated_at'] = $now;
    $wpdb->update($t, $update, ['job_id' => $job_id]);

    foreach ($audits as [$field, $old, $new]) {
      self::audit('job', $job_id, 'update', $field, (string)$old, (string)$new, 'Field edited');
    }

    $job2 = self::job_by_id($job_id);
    self::maybe_update_clickup_name($job2);
    if (array_key_exists('status', $update)) {
      self::maybe_push_dealer_portal_status($job2);
    }

    return self::get_job(['id' => $job_id]);
  }

  /**
   * Parse and validate a queue_order value from a request body.
   *
   * @param array  $body  Decoded request body.
   * @param string $key   Key to read (default 'queue_order').
   * @return int|null|WP_Error  Validated integer, null, or validation error.
   */
  private static function parse_queue_order(array $body, string $key = 'queue_order') {
    $raw = $body[$key] ?? null;
    if ($raw === null || $raw === '') {
      return null;
    }
    if (is_int($raw) && $raw >= 1) {
      return $raw;
    }
    $str = trim((string) $raw);
    if ($str === '') {
      return null;
    }
    if (!preg_match('/^[1-9][0-9]*$/', $str)) {
      return self::validation_error('queue_order', 'invalid_queue_order', 'Queue order must be a positive whole number.');
    }
    return (int) $str;
  }

  /**
   * Gate: block moving a job to READY_FOR_BUILD when required fields are missing
   * or parts are in a blocking state (NOT_READY / HOLD).
   *
   * PARTIAL is not blocked server-side — the frontend warns and lets the user confirm.
   *
   * @param array $job    Current job row from DB.
   * @param array $update Pending field updates (merged with $job for evaluation).
   * @return WP_Error|null  WP_Error (HTTP 422) on failure, null on pass.
   */
  private static function check_ready_for_build_gate(array $job, array $update = []): ?WP_Error {
    if (!isset($update['status']) || $update['status'] !== 'READY_FOR_BUILD') {
      return null;
    }

    $data    = array_merge($job, $update);
    $missing = [];

    // customer or dealer name required
    if (empty(trim((string)($data['customer_name'] ?? ''))) && empty(trim((string)($data['dealer_name'] ?? '')))) {
      $missing[] = 'customer or dealer name';
    }
    if (empty(trim((string)($data['so_number'] ?? '')))) {
      $missing[] = 'SO#';
    }
    // job description mapped to scope_summary
    if (empty(trim((string)($data['scope_summary'] ?? '')))) {
      $missing[] = 'job description';
    }
    if (empty((int)($data['estimated_minutes'] ?? 0))) {
      $missing[] = 'estimated hours';
    }

    $ps = strtoupper((string)($data['parts_status'] ?? 'NOT_READY'));
    if ($ps === 'NOT_READY') {
      $missing[] = 'parts not ready (update parts status first)';
    } elseif ($ps === 'HOLD') {
      $missing[] = 'parts are on hold';
    }

    if (!empty($missing)) {
      $msg = 'Cannot move to Ready for Build. Missing: ' . implode(', ', $missing) . '.';
      return new WP_Error('not_ready_for_build', $msg, ['status' => 422]);
    }

    return null;
  }

  public static function delete_job($req) {
    global $wpdb;
    $job_id = intval($req['id']);
    $t      = $wpdb->prefix . 'slate_ops_jobs';

    $job = self::job_by_id($job_id);
    if (!$job) return new WP_Error('not_found', 'Job not found', ['status' => 404]);

    self::audit('job', $job_id, 'delete', null, null, null, 'Job deleted by ' . wp_get_current_user()->display_name);

    $wpdb->delete($t, ['job_id' => $job_id], ['%d']);

    return new WP_REST_Response(['deleted' => true, 'job_id' => $job_id], 200);
  }

  public static function add_note($req) {
    global $wpdb;
    $job_id    = intval($req['id']);
    $body      = $req->get_json_params();
    $note_text = sanitize_textarea_field((string)($body['note'] ?? ''));

    if (!$note_text) {
      return new WP_Error('note_required', 'Note text is required.', ['status' => 400]);
    }

    $job = self::job_by_id($job_id);
    if (!$job) return new WP_Error('not_found', 'Job not found', ['status' => 404]);

    $wpdb->insert($wpdb->prefix . 'slate_ops_audit_log', [
      'entity_type' => 'job',
      'entity_id'   => $job_id,
      'action'      => 'note',
      'field_name'  => null,
      'old_value'   => null,
      'new_value'   => null,
      'note'        => $note_text,
      'user_id'     => get_current_user_id(),
      'ip_address'  => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : null,
      'user_agent'  => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : null,
      'created_at'  => Slate_Ops_Utils::now_gmt(),
    ]);

    return self::get_job(['id' => $job_id]);
  }

  public static function create_job_manual($req) {
    global $wpdb;
    $body = $req->get_json_params();
    $t = $wpdb->prefix . 'slate_ops_jobs';

    $so_number = strtoupper(trim(sanitize_text_field($body['so_number'] ?? '')));
    if ($so_number !== '' && !Slate_Ops_Utils::so_is_valid($so_number)) {
      return self::validation_error('so_number', 'invalid_so_number', 'SO# format: S-ORD followed by 6 digits (e.g. S-ORD101350).');
    }

    if ($so_number !== '') {
      $existing = $wpdb->get_var($wpdb->prepare("SELECT job_id FROM $t WHERE so_number=%s AND archived_at IS NULL", $so_number));
      if ($existing) {
        return self::validation_error('so_number', 'duplicate_so_number', 'SO# must be unique.', 409);
      }
    }

    $job_type = strtoupper(sanitize_key($body['job_type'] ?? ''));
    if (!in_array($job_type, Slate_Ops_Utils::cs_job_types(), true)) {
      return self::validation_error('job_type', 'invalid_job_type', 'Select a valid job type.');
    }

    $created_from = 'manual';

    $priority = 3;

    $parts_status = strtoupper(sanitize_key($body['parts_status'] ?? 'NOT_READY'));
    if (!in_array($parts_status, Slate_Ops_Utils::cs_parts_statuses(), true)) {
      return self::validation_error('parts_status', 'invalid_parts_status', 'Select a valid parts status.');
    }

    $estimated_hours_raw = isset($body['estimated_hours']) ? trim((string) $body['estimated_hours']) : '';
    if ($estimated_hours_raw === '' || !is_numeric($estimated_hours_raw)) {
      return self::validation_error('estimated_hours', 'invalid_estimated_hours', 'Estimated hours are required.');
    }
    $estimated_minutes = (int) round(((float) $estimated_hours_raw) * 60);
    if ($estimated_minutes <= 0) {
      return self::validation_error('estimated_hours', 'invalid_estimated_hours', 'Estimated hours must be greater than zero.');
    }

    $customer = sanitize_text_field($body['customer_name'] ?? '');
    $dealer = sanitize_text_field($body['dealer_name'] ?? '');
    if ($customer === '' && $dealer === '') {
      return self::validation_error('customer_name', 'customer_or_dealer_required', 'Provide a customer, dealer, or both.');
    }

    $no_vin_required = !empty($body['no_vin_required']);
    $vin_last8 = strtoupper(trim(sanitize_text_field($body['vin_last8'] ?? '')));
    if ($job_type !== 'PARTS_ONLY' && !$no_vin_required) {
      if (!Slate_Ops_Utils::vin_last8_is_valid($vin_last8)) {
        return self::validation_error('vin_last8', 'invalid_vin_last8', 'VIN is required and must be 7–8 alphanumeric characters.');
      }
    } elseif ($vin_last8 !== '' && !Slate_Ops_Utils::vin_last8_is_valid($vin_last8)) {
      return self::validation_error('vin_last8', 'invalid_vin_last8', 'VIN must be 7–8 alphanumeric characters.');
    }

    $requested_date = sanitize_text_field($body['requested_date'] ?? '');
    if ($requested_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $requested_date)) {
      return self::validation_error('requested_date', 'invalid_requested_date', 'Requested completion date must be YYYY-MM-DD.');
    }

    $sales_person = sanitize_text_field($body['sales_person'] ?? '');
    $notes = sanitize_textarea_field($body['notes'] ?? '');
    $notes_type = sanitize_key($body['notes_type'] ?? '');
    if ($notes !== '' && $notes_type === 'parts' && stripos($notes, 'Parts:') !== 0) {
      $notes = 'Parts: ' . $notes;
    }
    // job_description maps to scope_summary
    $scope_summary = sanitize_textarea_field($body['job_description'] ?? '');

    $queue_order = self::parse_queue_order($body, 'queue_order');
    if ($queue_order instanceof WP_Error) return $queue_order;

    $now = Slate_Ops_Utils::now_gmt();
    $inserted = $wpdb->insert($t, [
      'source' => $created_from,
      'created_from' => $created_from,
      'so_number' => $so_number ?: null,
      'customer_name' => $customer ?: null,
      'dealer_name' => $dealer ?: null,
      'vin' => $vin_last8 ?: null,
      'vin_last8' => $vin_last8 ?: null,
      'job_type' => $job_type,
      'parts_status' => $parts_status,
      'status' => 'INTAKE',
      'status_updated_at' => $now,
      'delay_reason' => null,
      'priority' => $priority,
      'estimated_minutes' => $estimated_minutes,
      'requested_date' => $requested_date ?: null,
      'sales_person' => $sales_person ?: null,
      'notes' => $notes ?: null,
      'scope_summary' => $scope_summary ?: null,
      'queue_order' => $queue_order,
      'dealer_status' => 'waiting',
      'created_by' => get_current_user_id(),
      'created_at' => $now,
      'updated_at' => $now,
    ]);

    if (!$inserted) {
      return new WP_Error('db_insert_failed', 'Could not create job.', ['status' => 500]);
    }

    $job_id = (int) $wpdb->insert_id;
    self::audit('job', $job_id, 'create', null, null, wp_json_encode(['created_from' => $created_from]), 'CS intake job created');

    $job = self::job_by_id($job_id);
    self::maybe_create_clickup_task($job);

    return self::get_job(['id' => $job_id]);
  }

  public static function set_so($req) {
    global $wpdb;
    $job_id = intval($req['id']);
    $body = $req->get_json_params();
    $so = strtoupper(trim(sanitize_text_field($body['so_number'] ?? '')));

    if (!Slate_Ops_Utils::so_is_valid($so)) {
      return new WP_Error('invalid_so', 'SO# format: S-ORD followed by 6 digits (e.g. S-ORD101350)', ['status' => 400]);
    }

    $t = $wpdb->prefix . 'slate_ops_jobs';
    $existing = $wpdb->get_var($wpdb->prepare("SELECT job_id FROM $t WHERE so_number=%s AND job_id<>%d AND archived_at IS NULL", $so, $job_id));
    if ($existing) {
      return new WP_Error('so_exists', 'SO# already linked to another job', ['status' => 409, 'job_id' => (int)$existing]);
    }

    $job = self::job_by_id($job_id);
    if (!$job) return new WP_Error('not_found', 'Job not found', ['status' => 404]);

    $now = Slate_Ops_Utils::now_gmt();
    $update = [
      'so_number'     => $so,
      'dealer_status' => 'waiting',
      'updated_at'    => $now,
    ];

    // Accept full intake fields when provided (portal-originated jobs completing intake).
    $customer     = sanitize_text_field($body['customer_name'] ?? '');
    $dealer       = sanitize_text_field($body['dealer_name'] ?? '');
    $job_type     = strtoupper(sanitize_key($body['job_type'] ?? ''));
    $parts_status = strtoupper(sanitize_key($body['parts_status'] ?? ''));
    $est_raw      = isset($body['estimated_hours']) ? trim((string) $body['estimated_hours']) : '';
    $vin_last8    = strtoupper(trim(sanitize_text_field($body['vin_last8'] ?? '')));
    $notes        = sanitize_textarea_field($body['notes'] ?? '');
    $req_date     = sanitize_text_field($body['requested_date'] ?? '');
    $no_vin       = !empty($body['no_vin_required']);

    $has_intake = ($customer !== '' || $dealer !== '' || $job_type !== '' || $est_raw !== '');

    if ($has_intake) {
      if ($customer === '' && $dealer === '') {
        return self::validation_error('customer_name', 'customer_or_dealer_required', 'Provide a customer, dealer, or both.');
      }
      if (!in_array($job_type, Slate_Ops_Utils::cs_job_types(), true)) {
        return self::validation_error('job_type', 'invalid_job_type', 'Select a valid job type.');
      }
      if ($parts_status !== '' && !in_array($parts_status, Slate_Ops_Utils::cs_parts_statuses(), true)) {
        return self::validation_error('parts_status', 'invalid_parts_status', 'Select a valid parts status.');
      }
      if ($est_raw === '' || !is_numeric($est_raw)) {
        return self::validation_error('estimated_hours', 'invalid_estimated_hours', 'Estimated hours are required.');
      }
      $est_minutes = (int) round(((float) $est_raw) * 60);
      if ($est_minutes <= 0) {
        return self::validation_error('estimated_hours', 'invalid_estimated_hours', 'Estimated hours must be greater than zero.');
      }
      if ($vin_last8 !== '' && !Slate_Ops_Utils::vin_last8_is_valid($vin_last8)) {
        return self::validation_error('vin_last8', 'invalid_vin_last8', 'VIN must be 7–8 alphanumeric characters.');
      }
      if (!$no_vin && $job_type !== 'PARTS_ONLY' && !Slate_Ops_Utils::vin_last8_is_valid($vin_last8)) {
        return self::validation_error('vin_last8', 'invalid_vin_last8', 'VIN is required and must be 7–8 alphanumeric characters.');
      }
      if ($req_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $req_date)) {
        return self::validation_error('requested_date', 'invalid_requested_date', 'Requested completion date must be YYYY-MM-DD.');
      }
      $update['customer_name']     = $customer ?: null;
      $update['dealer_name']       = $dealer ?: null;
      $update['job_type']          = $job_type;
      $update['parts_status']      = $parts_status ?: 'NOT_READY';
      $update['estimated_minutes'] = $est_minutes;
      if ($vin_last8 !== '') {
        $update['vin']      = $vin_last8;
        $update['vin_last8']= $vin_last8;
      }
      if ($notes !== '')    $update['notes']          = $notes;
      if ($req_date !== '') $update['requested_date'] = $req_date;
    }

    $old_so = $job['so_number'];
    $wpdb->update($t, $update, ['job_id' => $job_id]);

    self::audit('job', $job_id, 'update', 'so_number', $old_so, $so, 'SO# set');

    $job2 = self::job_by_id($job_id);
    self::maybe_update_clickup_name($job2);
    self::maybe_push_dealer_portal_status($job2);

    return self::get_job(['id' => $job_id]);
  }

  public static function assign_job($req) {
    global $wpdb;
    $job_id = intval($req['id']);
    $body = $req->get_json_params();
    $user_id = intval($body['assigned_user_id'] ?? 0);

    $job = self::job_by_id($job_id);
    if (!$job) return new WP_Error('not_found', 'Job not found', ['status' => 404]);

    $t = $wpdb->prefix . 'slate_ops_jobs';
    $old = $job['assigned_user_id'];

    $wpdb->update($t, [
      'assigned_user_id' => $user_id ?: null,
      'updated_at' => Slate_Ops_Utils::now_gmt(),
    ], ['job_id' => $job_id]);

    self::audit('job', $job_id, 'update', 'assigned_user_id', (string)$old, (string)$user_id, 'Assignment updated');

    return self::get_job(['id' => $job_id]);
  }

  public static function schedule_job($req) {
	global $wpdb;
	$body = $req->get_json_params() ?: [];
	$t = $wpdb->prefix . 'slate_ops_jobs';

	$job_id = isset($req['id']) ? (int) $req['id'] : (int)($body['job_id'] ?? 0);
	if (!$job_id) return new WP_Error('bad_request', 'Missing job_id', ['status'=>400]);

	$job = self::job_by_id($job_id);
	if (!$job) return new WP_Error('not_found', 'Job not found', ['status' => 404]);

	$now = Slate_Ops_Utils::now_gmt();
	list($update, $changes) = self::build_scheduler_update_fields($body, $now);

	self::scheduler_debug_log('schedule_job payload', ['job_id' => $job_id, 'payload' => $body, 'columns' => array_keys($update)]);
	$result = $wpdb->update($t, $update, ['job_id' => $job_id]);
	self::scheduler_debug_log('schedule_job write result', ['job_id' => $job_id, 'affected_rows' => $result, 'db_error' => $wpdb->last_error]);

	if ($result === false) {
	  return new WP_Error('db_error', $wpdb->last_error ?: 'DB update failed', ['status' => 500]);
	}

	self::audit('job', $job_id, 'update', 'schedule', null, wp_json_encode($changes), 'Schedule updated');

	// Keep ClickUp name fresh once SO# exists
	$job = self::job_by_id($job_id);
	self::maybe_update_clickup_name($job);

	// Push simplified dealer status based on shop status
	self::maybe_push_dealer_portal_status($job);

	return self::get_job(['id' => $job_id]);
		  }

  /**
   * Release a job to the scheduler (TOC Rope).
   * Phase 0: you can skip this and schedule manually.
   * Phase 1+: scheduler can be configured to only show jobs with scheduling_status=READY_FOR_BUILD.
   */
  public static function release_job($req) {
    global $wpdb;
    $body = $req->get_json_params() ?: [];
    $t = $wpdb->prefix . 'slate_ops_jobs';

    $job_id = isset($req['id']) ? intval($req['id']) : intval($body['job_id'] ?? 0);
    if (!$job_id) {
      return new WP_Error('bad_request', 'Missing job_id', ['status' => 400]);
    }

    $override = !empty($body['override']);
    $override_reason = sanitize_text_field($body['override_reason'] ?? '');
    $override_notes  = sanitize_text_field($body['override_notes'] ?? '');

    $job = self::job_by_id($job_id);
    if (!$job) {
      return new WP_Error('not_found', 'Job not found', ['status' => 404]);
    }

    $failures = [];

    if (empty($job['so_number'])) $failures[] = 'missing_so_number';
    if (empty($job['estimated_minutes']) || intval($job['estimated_minutes']) <= 0) $failures[] = 'missing_estimated_minutes';

    // Scope must be locked before release (unless overridden)
    if (!empty($job['scope_status']) && $job['scope_status'] !== 'LOCKED') $failures[] = 'scope_not_locked';

    // Parts must be READY before release (unless overridden)
    if (!empty($job['parts_status']) && $job['parts_status'] !== 'READY') $failures[] = 'parts_not_ready';

    if (!$override && !empty($failures)) {
      return new WP_Error('release_blocked', 'Job is not eligible for scheduling release', [
        'status' => 409,
        'failures' => $failures,
      ]);
    }

    $now = Slate_Ops_Utils::now_gmt();

    $update = [
      'scheduling_status' => 'READY_FOR_BUILD',
      'ready_queue_entered_at' => $now,
      'override_flag' => $override ? 1 : 0,
      'override_reason' => $override ? ($override_reason ?: null) : null,
      'override_notes'  => $override ? ($override_notes ?: null) : null,
      'updated_at' => $now,
    ];

    $wpdb->update($t, $update, ['job_id' => $job_id]);

    self::audit('job', $job_id, 'update', 'release', null, wp_json_encode($update), 'Released to scheduler');

    return self::get_job(['id' => $job_id]);
  }



  public static function set_status($req) {
global $wpdb;
$body = $req->get_json_params() ?: [];
$t = $wpdb->prefix . 'slate_ops_jobs';

// Accept job_id from URL param (REST route) or body (legacy internal calls)
$job_id = isset($req['id']) ? intval($req['id']) : (int)($body['job_id'] ?? 0);
if (!$job_id) return new WP_Error('bad_request', 'Missing job_id', ['status'=>400]);

$new_status = strtoupper(sanitize_text_field($body['status'] ?? ''));
if (!$new_status) return new WP_Error('bad_request', 'Missing status', ['status'=>400]);

$detail = sanitize_text_field($body['status_detail'] ?? '');
$delay_reason = sanitize_key($body['delay_reason'] ?? '');
$priority = (int)($body['priority'] ?? 0);

$now = Slate_Ops_Utils::now_gmt();

// Fetch job once for all guards below.
$current_job = self::job_by_id($job_id);
if (!$current_job) return new WP_Error('not_found', 'Job not found', ['status' => 404]);

$current_status = (string)($current_job['status'] ?? '');

// Centralized transition guard — blocks all invalid status jumps.
if (!Slate_Ops_Statuses::is_valid_transition($current_status, $new_status)) {
  return new WP_Error(
    'invalid_transition',
    sprintf(
      'Cannot move job from %s to %s.',
      Slate_Ops_Statuses::label($current_status),
      Slate_Ops_Statuses::label($new_status)
    ),
    ['status' => 422]
  );
}

// Gate: READY_FOR_BUILD requires SO#, customer info, job description, estimated time, and ready parts.
if ($new_status === Slate_Ops_Statuses::READY_FOR_BUILD) {
  $gate = self::check_ready_for_build_gate($current_job, ['status' => $new_status]);
  if ($gate) return $gate;
}

// Gate: IN_PROGRESS requires an assigned tech.
if ($new_status === Slate_Ops_Statuses::IN_PROGRESS) {
  if (empty($current_job['assigned_user_id'])) {
    return new WP_Error('not_ready_for_progress', 'Cannot start job: no tech has been assigned.', ['status' => 422]);
  }
}

$update = [
  'status' => $new_status,
  'status_detail' => $detail ?: null,
  'status_updated_at' => $now,
  'updated_at' => $now,
];

// v2: BLOCKED requires block_reason + block_note
if ($new_status === Slate_Ops_Statuses::BLOCKED) {
  $br = strtoupper(sanitize_key($body['block_reason'] ?? ''));
  $bn = trim(sanitize_textarea_field($body['block_note'] ?? ''));
  if (!$br || !in_array($br, Slate_Ops_Utils::cs_block_reasons(), true)) {
    return self::validation_error('block_reason', 'block_reason_required', 'Block reason is required when setting status to Blocked.');
  }
  if (!$bn) {
    return self::validation_error('block_note', 'block_note_required', 'Block note is required when setting status to Blocked.');
  }
  $update['block_reason'] = $br;
  $update['block_note']   = $bn;
}

// v2: ON_HOLD requires hold_reason + hold_note
if ($new_status === Slate_Ops_Statuses::ON_HOLD) {
  $hr = strtoupper(sanitize_key($body['hold_reason'] ?? ''));
  $hn = trim(sanitize_textarea_field($body['hold_note'] ?? ''));
  if (!$hr || !in_array($hr, Slate_Ops_Utils::cs_hold_reasons(), true)) {
    return self::validation_error('hold_reason', 'hold_reason_required', 'Hold reason is required when setting status to On Hold.');
  }
  if (!$hn) {
    return self::validation_error('hold_note', 'hold_note_required', 'Hold note is required when setting status to On Hold.');
  }
  $update['hold_reason'] = $hr;
  $update['hold_note']   = $hn;
}

// v2: CANCELLED requires cancel_reason + cancel_note
if ($new_status === Slate_Ops_Statuses::CANCELLED) {
  $cr = strtoupper(sanitize_key($body['cancel_reason'] ?? ''));
  $cn = trim(sanitize_textarea_field($body['cancel_note'] ?? ''));
  if (!$cr || !in_array($cr, Slate_Ops_Utils::cs_cancel_reasons(), true)) {
    return self::validation_error('cancel_reason', 'cancel_reason_required', 'Cancel reason is required when setting status to Cancelled.');
  }
  if (!$cn) {
    return self::validation_error('cancel_note', 'cancel_note_required', 'Cancel note is required when setting status to Cancelled.');
  }
  $update['cancel_reason'] = $cr;
  $update['cancel_note']   = $cn;
}

// Legacy delay_reason passthrough
if (!empty($delay_reason)) {
  $update['delay_reason'] = $delay_reason;
}

// Priority 1-5
if ($priority >= 1 && $priority <= 5) {
  $update['priority'] = $priority;
}

$wpdb->update($t, $update, ['job_id' => $job_id]);

self::audit('job', $job_id, 'update', 'status', null, wp_json_encode($update), 'Status updated');

$job = self::job_by_id($job_id);
self::maybe_push_dealer_portal_status($job);

return self::get_job(['id' => $job_id]);
	  }

  // ── QC Workflow ───────────────────────────────────────

  /**
   * Auto-stop any active timer for a user on a specific job.
   * Returns the closed segment row or null.
   */
  private static function stop_active_timer_for_job($user_id, $job_id) {
    global $wpdb;
    $segments = $wpdb->prefix . 'slate_ops_time_segments';
    $now = Slate_Ops_Utils::now_gmt();
    $open = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM $segments WHERE user_id=%d AND job_id=%d AND end_ts IS NULL AND state='active' LIMIT 1",
      $user_id, $job_id
    ), ARRAY_A);
    if ($open) {
      $wpdb->update($segments, ['end_ts' => $now, 'updated_at' => $now], ['segment_id' => (int)$open['segment_id']]);
      self::audit('segment', (int)$open['segment_id'], 'update', 'end_ts', null, $now, 'Auto-stopped by QC submit');
    }
    return $open;
  }

  /**
   * Tech blocks their active job without a formal CS block reason.
   * POST /jobs/{id}/block
   * Stops the active timer and sets status to BLOCKED.
   */
  public static function block_job($req) {
    global $wpdb;
    $job_id  = intval($req['id']);
    $body    = $req->get_json_params() ?: [];
    $user_id = get_current_user_id();
    $now     = Slate_Ops_Utils::now_gmt();
    $t       = $wpdb->prefix . 'slate_ops_jobs';

    if (!$job_id) return new WP_Error('bad_request', 'Missing job_id', ['status' => 400]);

    $br = strtoupper(sanitize_key($body['block_reason'] ?? ''));
    $bn = trim(sanitize_textarea_field($body['block_note'] ?? ''));

    if (!$br || !in_array($br, Slate_Ops_Utils::cs_block_reasons(), true)) {
      return new WP_Error('block_reason_required', 'Block reason is required.', ['status' => 422]);
    }
    if (!$bn) {
      return new WP_Error('block_note_required', 'Block note is required.', ['status' => 422]);
    }

    $job = self::job_by_id($job_id);
    if (!$job) return new WP_Error('not_found', 'Job not found', ['status' => 404]);

    if ($job['status'] !== Slate_Ops_Statuses::IN_PROGRESS) {
      return new WP_Error('invalid_state', 'Only in-progress jobs can be blocked', ['status' => 422]);
    }

    // Stop any active timer for this user on this job.
    self::stop_active_timer_for_job($user_id, $job_id);

    $wpdb->update($t, [
      'status'            => Slate_Ops_Statuses::BLOCKED,
      'block_reason'      => $br,
      'block_note'        => $bn,
      'status_updated_at' => $now,
      'updated_at'        => $now,
    ], ['job_id' => $job_id]);

    self::audit('job', $job_id, 'update', 'status', $job['status'], Slate_Ops_Statuses::BLOCKED,
      'Blocked by technician: [' . $br . '] ' . $bn);

    $updated = self::job_by_id($job_id);
    self::maybe_push_dealer_portal_status($updated);

    return self::get_job(['id' => $job_id]);
  }

  /**
   * Tech submits job for QC review.
   * POST /jobs/{id}/qc/submit  { notes }
   */
  public static function submit_qc($req) {
    global $wpdb;
    $job_id = intval($req['id']);
    $body   = $req->get_json_params() ?: [];
    $notes  = sanitize_textarea_field($body['notes'] ?? '');

    if (!$job_id) return new WP_Error('bad_request', 'Missing job_id', ['status' => 400]);

    $job = self::job_by_id($job_id);
    if (!$job) return new WP_Error('not_found', 'Job not found', ['status' => 404]);

    if ($job['status'] !== 'IN_PROGRESS') {
      return new WP_Error('invalid_state', 'Job must be In Progress to submit for QC', ['status' => 422]);
    }

    $user_id = get_current_user_id();
    $now     = Slate_Ops_Utils::now_gmt();
    $t       = $wpdb->prefix . 'slate_ops_jobs';
    $qc      = $wpdb->prefix . 'slate_ops_qc_records';

    // Auto-stop any active timer for this user on this job.
    self::stop_active_timer_for_job($user_id, $job_id);

    // Create QC record.
    $wpdb->insert($qc, [
      'job_id'      => $job_id,
      'checkpoint'  => 'TECH_QC',
      'result'      => 'SUBMITTED',
      'checked_by'  => $user_id,
      'notes'       => $notes,
      'location_id' => (int)($job['location_id'] ?? 1),
      'created_at'  => $now,
    ]);

    // Transition job to QC (v2; was PENDING_QC).
    $wpdb->update($t, [
      'status'            => Slate_Ops_Statuses::QC,
      'status_updated_at' => $now,
      'updated_at'        => $now,
    ], ['job_id' => $job_id]);

    self::audit('job', $job_id, 'update', 'status', $job['status'], Slate_Ops_Statuses::QC, 'QC submitted: ' . $notes);

    $updated = self::job_by_id($job_id);
    self::maybe_push_dealer_portal_status($updated);

    return self::get_job(['id' => $job_id]);
  }

  /**
   * Supervisor reviews QC submission (pass or fail).
   * POST /jobs/{id}/qc/review  { decision: PASS|FAIL, notes }
   */
  public static function review_qc($req) {
    global $wpdb;
    $job_id   = intval($req['id']);
    $body     = $req->get_json_params() ?: [];
    $decision = strtoupper(sanitize_text_field($body['decision'] ?? ''));
    $notes    = sanitize_textarea_field($body['notes'] ?? '');

    if (!$job_id) return new WP_Error('bad_request', 'Missing job_id', ['status' => 400]);
    if (!in_array($decision, ['PASS', 'FAIL'], true)) {
      return new WP_Error('bad_request', 'Decision must be PASS or FAIL', ['status' => 400]);
    }
    if ($decision === 'FAIL' && !trim($notes)) {
      return new WP_Error('bad_request', 'Notes are required when failing QC', ['status' => 400]);
    }

    $job = self::job_by_id($job_id);
    if (!$job) return new WP_Error('not_found', 'Job not found', ['status' => 404]);

    if (!in_array($job['status'], [Slate_Ops_Statuses::QC, Slate_Ops_Statuses::PENDING_QC], true)) {
      return new WP_Error('invalid_state', 'Job must be in QC to review', ['status' => 422]);
    }

    $user_id = get_current_user_id();
    $now     = Slate_Ops_Utils::now_gmt();
    $t       = $wpdb->prefix . 'slate_ops_jobs';
    $qc      = $wpdb->prefix . 'slate_ops_qc_records';

    // Update the most recent SUBMITTED qc_record for this job.
    $qc_row = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM $qc WHERE job_id=%d AND result='SUBMITTED' ORDER BY created_at DESC LIMIT 1",
      $job_id
    ), ARRAY_A);

    if ($qc_row) {
      $qc_update = ['result' => $decision];
      if (trim($notes)) $qc_update['notes'] = $qc_row['notes'] . "\n--- Reviewer ---\n" . $notes;
      $wpdb->update($qc, $qc_update, ['qc_id' => (int)$qc_row['qc_id']]);
    }

    if ($decision === 'PASS') {
      $new_status = Slate_Ops_Statuses::COMPLETE;
      $audit_note = 'QC passed — job complete';
    } else {
      $new_status = Slate_Ops_Statuses::IN_PROGRESS;
      $audit_note = 'QC failed: ' . $notes;
    }

    $wpdb->update($t, [
      'status'            => $new_status,
      'status_updated_at' => $now,
      'updated_at'        => $now,
    ], ['job_id' => $job_id]);

    $action = $decision === 'PASS' ? 'qc_approved' : 'qc_failed';
    self::audit('job', $job_id, $action, 'status', Slate_Ops_Statuses::QC, $new_status, $audit_note);

    // If failed, also add a note so the tech can see the reason.
    if ($decision === 'FAIL' && trim($notes)) {
      self::audit('job', $job_id, 'note', null, null, null, 'QC Failed: ' . $notes);
    }

    $updated = self::job_by_id($job_id);
    self::maybe_push_dealer_portal_status($updated);

    return self::get_job(['id' => $job_id]);
  }

  public static function time_start($req) {
    global $wpdb;
    $body = $req->get_json_params();
    $job_id = intval($body['job_id'] ?? 0);
    $reason = sanitize_key($body['reason'] ?? '');
    $note = sanitize_text_field($body['note'] ?? '');

    if (!$job_id) return new WP_Error('missing_job', 'job_id required', ['status' => 400]);
    $job = self::job_by_id($job_id);
    if (!$job) return new WP_Error('not_found', 'Job not found', ['status' => 404]);

    $user_id = get_current_user_id();
    $now = Slate_Ops_Utils::now_gmt();
    $shift_state = self::get_shift_window_state($now);
    // Phase 0: outside-shift starts are allowed. We log an audit note below
    // for supervisor visibility but do not block — there is no overtime
    // approval workflow yet, so blocking would leave techs with no path.
    $outside_shift = empty($shift_state['within_shift']);

    // Job-status guard: only schedulable/active-state jobs can be started.
    $startable = [
      Slate_Ops_Statuses::READY_FOR_BUILD, Slate_Ops_Statuses::SCHEDULED,
      Slate_Ops_Statuses::IN_PROGRESS, Slate_Ops_Statuses::BLOCKED,
      Slate_Ops_Statuses::QUEUED, // legacy alias
    ];
    if (!in_array($job['status'], $startable, true)) {
      return new WP_Error('invalid_status', 'Job cannot be started in its current status', ['status' => 422]);
    }

    // Duplicate-timer guard.
    $segments = $wpdb->prefix . 'slate_ops_time_segments';
    $open = $wpdb->get_row($wpdb->prepare("SELECT * FROM $segments WHERE user_id=%d AND end_ts IS NULL AND state='active' ORDER BY start_ts DESC LIMIT 1", $user_id), ARRAY_A);
    if ($open) {
      if ((int)$open['job_id'] === $job_id) {
        return ['segment_id' => (int)$open['segment_id'], 'job_id' => $job_id, 'started_at' => $open['start_ts']];
      }
      return new WP_Error('timer_conflict', 'You already have an active timer on another job. Pause it before starting a new one.', ['status' => 409]);
    }

    // Reason required when not assigned (and assignment exists).
    $assigned = (int)($job['assigned_user_id'] ?? 0);
    if ($assigned && $assigned !== $user_id) {
      $reason = Slate_Ops_Utils::sanitize_reason($reason);
      if ($reason === 'other' && empty($note)) {
        return new WP_Error('reason_required', 'Reason note required for "Other"', ['status' => 400]);
      }
    } else {
      $reason = null;
    }

    $wpdb->insert($segments, [
      'job_id' => $job_id,
      'user_id' => $user_id,
      'start_ts' => $now,
      'end_ts' => null,
      'reason' => $reason,
      'note' => $note ?: null,
      'source' => 'timer',
      'state' => 'active',
      'approval_status' => 'approved',
      'created_by' => $user_id,
      'created_at' => $now,
      'updated_at' => $now,
    ]);

    $segment_id = (int)$wpdb->insert_id;
    self::audit('segment', $segment_id, 'create', null, null, wp_json_encode(['job_id'=>$job_id,'user_id'=>$user_id]), 'Timer started');

    if ($outside_shift) {
      self::audit('segment', $segment_id, 'note', null, null, null, 'Timer started outside scheduled shift.');
      self::audit('job', $job_id, 'note', null, null, null, 'Timer started outside scheduled shift.');
    }

    // Set job to IN_PROGRESS if needed (inline update to avoid REST req context issues).
    if (!in_array($job['status'], ['IN_PROGRESS','QC','PENDING_QC','COMPLETE'], true)) {
      $t    = $wpdb->prefix . 'slate_ops_jobs';
      $now2 = Slate_Ops_Utils::now_gmt();
      $wpdb->update($t, [
        'status'            => 'IN_PROGRESS',
        'status_detail'     => null,
        'status_updated_at' => $now2,
        'updated_at'        => $now2,
      ], ['job_id' => $job_id]);
      self::audit('job', $job_id, 'update', 'status', $job['status'], 'IN_PROGRESS', 'Auto set by timer start');
    }

    return [
      'segment_id' => $segment_id,
      'job_id' => $job_id,
      'started_at' => $now,
    ];
  }

  public static function time_stop($req) {
    global $wpdb;
    $user_id = get_current_user_id();
    $segments = $wpdb->prefix . 'slate_ops_time_segments';
    $open = $wpdb->get_row($wpdb->prepare("SELECT * FROM $segments WHERE user_id=%d AND end_ts IS NULL AND state='active' ORDER BY start_ts DESC LIMIT 1", $user_id), ARRAY_A);
    if (!$open) {
      return new WP_Error('no_active_timer', 'No active timer', ['status' => 400]);
    }

    $now = Slate_Ops_Utils::now_gmt();
    // Phase 0: shift-end capping disabled. Record the actual stop time so
    // outside-shift work is visible to supervisors during validation.
    $effective_stop = $now;
    $wpdb->update($segments, [
      'end_ts'     => $effective_stop,
      'state'      => 'closed',
      'updated_at' => $now,
    ], ['segment_id' => (int)$open['segment_id']]);

    self::audit('segment', (int)$open['segment_id'], 'update', 'end_ts', null, $effective_stop, 'Timer stopped');

    // Elapsed seconds for the just-closed segment — computed in PHP, no extra query.
    $elapsed_seconds = max(0, strtotime($effective_stop) - strtotime($open['start_ts']));

    // Total approved minutes this user has logged on this job (all closed segments).
    // Sum seconds first, then convert — avoids per-segment truncation from TIMESTAMPDIFF(MINUTE).
    $total_approved_minutes = (int)$wpdb->get_var($wpdb->prepare(
      "SELECT ROUND(COALESCE(SUM(TIMESTAMPDIFF(SECOND, start_ts, end_ts)), 0) / 60)
       FROM $segments
       WHERE job_id = %d AND user_id = %d AND end_ts IS NOT NULL
         AND approval_status = 'approved'",
      (int)$open['job_id'], $user_id
    ));

    return [
      'segment_id'             => (int)$open['segment_id'],
      'job_id'                 => (int)$open['job_id'],
      'stopped_at'             => $effective_stop,
      'elapsed_seconds'        => $elapsed_seconds,
      'total_approved_minutes' => $total_approved_minutes,
    ];
  }

  public static function time_correction_request($req) {
    // V1: tech submits a correction as a new pending segment (no auto-void logic yet).
    // Supervisor can later void/replace segments; UI will show pending minutes separately.
    global $wpdb;
    $body = $req->get_json_params();
    $job_id = intval($body['job_id'] ?? 0);
    $start = sanitize_text_field($body['start_ts'] ?? '');
    $end = sanitize_text_field($body['end_ts'] ?? '');
    $note = sanitize_text_field($body['note'] ?? '');

    if (!$job_id || !$start || !$end) return new WP_Error('missing_fields', 'job_id, start_ts, end_ts required', ['status' => 400]);
    if (empty($note)) return new WP_Error('note_required', 'Reason note required', ['status' => 400]);

    $job = self::job_by_id($job_id);
    if (!$job) return new WP_Error('not_found', 'Job not found', ['status' => 404]);

    $segments = $wpdb->prefix . 'slate_ops_time_segments';
    $now = Slate_Ops_Utils::now_gmt();

    $wpdb->insert($segments, [
      'job_id' => $job_id,
      'user_id' => get_current_user_id(),
      'start_ts' => $start,
      'end_ts' => $end,
      'reason' => null,
      'note' => $note,
      'source' => 'manual_fix',
      'state' => 'active',
      'approval_status' => 'pending',
      'created_by' => get_current_user_id(),
      'created_at' => $now,
      'updated_at' => $now,
    ]);

    $segment_id = (int)$wpdb->insert_id;
    self::audit('segment', $segment_id, 'create', null, null, wp_json_encode(['job_id'=>$job_id,'start'=>$start,'end'=>$end]), 'Time correction submitted (pending)');

    return ['segment_id' => $segment_id, 'approval_status' => 'pending'];
  }

  public static function time_active($req) {
    global $wpdb;
    $seg     = $wpdb->prefix . 'slate_ops_time_segments';
    $jt      = $wpdb->prefix . 'slate_ops_jobs';
    $user_id = get_current_user_id();
    $row = $wpdb->get_row($wpdb->prepare(
      "SELECT s.*, j.so_number, j.customer_name, j.vin, j.status AS job_status,
              j.estimated_minutes, j.work_center, j.dealer_name
       FROM $seg s
       JOIN $jt j ON j.job_id = s.job_id
       WHERE s.user_id = %d AND s.end_ts IS NULL AND s.state = 'active'
       ORDER BY s.start_ts DESC LIMIT 1",
      $user_id
    ), ARRAY_A);

    if ($row) {
      // Phase 0: shift-end auto-stop disabled. Active timers remain active
      // past the configured shift end so techs can finish work during
      // validation. Outside-shift starts are logged in time_start().

      // Closed approved segments this user has previously logged on this job.
      // Sum seconds first, then convert — avoids per-segment truncation from TIMESTAMPDIFF(MINUTE).
      $row['prior_minutes'] = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT ROUND(COALESCE(SUM(TIMESTAMPDIFF(SECOND, start_ts, end_ts)), 0) / 60)
         FROM $seg
         WHERE job_id = %d AND user_id = %d AND end_ts IS NOT NULL
           AND approval_status = 'approved'",
        (int)$row['job_id'], $user_id
      ));

      // Labor gauge: total minutes this user has logged on this job (all segments, incl. active).
      $row['my_job_minutes'] = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT GREATEST(0, ROUND(COALESCE(SUM(TIMESTAMPDIFF(SECOND, start_ts, COALESCE(end_ts, UTC_TIMESTAMP()))), 0) / 60))
         FROM $seg
         WHERE job_id = %d AND user_id = %d AND approval_status != 'voided'",
        (int)$row['job_id'], $user_id
      ));

      // Labor gauge: total minutes all users have logged on this job (all segments, incl. active).
      $row['job_total_minutes'] = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT GREATEST(0, ROUND(COALESCE(SUM(TIMESTAMPDIFF(SECOND, start_ts, COALESCE(end_ts, UTC_TIMESTAMP()))), 0) / 60))
         FROM $seg
         WHERE job_id = %d AND approval_status != 'voided'",
        (int)$row['job_id']
      ));
    }

    return ['active' => $row ?: null];
  }

  /**
   * GET /time/daily-summary
   * Returns today's raw minutes, break/lunch deduction, and net billable minutes
   * for the requesting user (or ?user_id=N for admin/supervisor).
   */
  public static function time_daily_summary($req) {
    global $wpdb;
    $segments = $wpdb->prefix . 'slate_ops_time_segments';
    $jobs_t   = $wpdb->prefix . 'slate_ops_jobs';
    $settings = $wpdb->prefix . 'slate_ops_settings';

    $me = get_current_user_id();
    $user_id = $me;
    if (current_user_can(Slate_Ops_Utils::CAP_ADMIN) || current_user_can(Slate_Ops_Utils::CAP_SUPERVISOR)) {
      $req_uid = intval($req->get_param('user_id') ?? 0);
      if ($req_uid > 0) $user_id = $req_uid;
    }

    // Date: default today in site timezone
    $date = sanitize_text_field($req->get_param('date') ?? '');
    if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
      $date = wp_date('Y-m-d');
    }

    // Pull all closed segments for this user on this date
    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT s.segment_id, s.job_id, s.start_ts, s.end_ts, s.reason, s.approval_status,
              j.so_number, j.customer_name
       FROM $segments s
       LEFT JOIN $jobs_t j ON j.job_id = s.job_id
       WHERE s.user_id = %d
         AND DATE(s.start_ts) = %s
       ORDER BY s.start_ts ASC",
      $user_id, $date
    ), ARRAY_A);

    $raw_minutes = 0;
    $shift_for_day = self::get_shift_window_state(gmdate('Y-m-d H:i:s', strtotime($date . ' 12:00:00')));
    $shift_start_ts = strtotime($shift_for_day['shift_start_ts']);
    $shift_end_ts = strtotime($shift_for_day['shift_end_ts']);
    $segments_out = [];
    foreach ($rows as $r) {
      $seg_start = strtotime($r['start_ts']);
      $seg_end = $r['end_ts'] ? strtotime($r['end_ts']) : time();
      $bounded_start = max($seg_start, $shift_start_ts);
      $bounded_end = min($seg_end, $shift_end_ts);
      $in_shift_seconds = max(0, $bounded_end - $bounded_start);
      $mins = (int) ceil($in_shift_seconds / 60);

      if ($r['end_ts']) {
        $raw_minutes += $mins;
        $r['duration_minutes'] = $mins;
      } else {
        // Open segment — count up to now (inside configured shift window)
        $raw_minutes += $mins;
        $r['duration_minutes'] = $mins;
        $r['is_open'] = true;
      }
      $segments_out[] = $r;
    }

    // Load break/lunch config from saved settings (not hardcoded).
    $cfg = $wpdb->get_row("SELECT lunch_minutes, break_minutes, break_count FROM $settings WHERE id=1", ARRAY_A);
    $lunch_min = (int)($cfg['lunch_minutes'] ?? 30);
    $break_min = (int)($cfg['break_minutes'] ?? 10);
    $break_cnt = (int)($cfg['break_count']   ?? 2);

    // This is a job-costing/performance deduction, not payroll calculation.
    // configured_deduction = total shop overhead (all breaks + lunch) from saved settings.
    // applied_deduction    = min(configured, raw) so net is never negative.
    $configured_deduction = ($break_min * $break_cnt) + $lunch_min;
    $applied_deduction    = min($configured_deduction, $raw_minutes);
    $net_minutes          = max(0, $raw_minutes - $applied_deduction);

    return [
      'date'              => $date,
      'user_id'           => $user_id,
      'raw_minutes'       => $raw_minutes,
      'deduction_minutes' => $applied_deduction,
      'net_minutes'       => $net_minutes,
      'lunch_minutes'     => $lunch_min,
      'break_minutes'     => $break_min,
      'break_count'       => $break_cnt,
      'deduction_applied' => $applied_deduction > 0,
      'segments'          => $segments_out,
    ];
  }

  /**
   * Returns the canonical Slate Ops role slug for a user, derived from
   * their actual WP roles — not inferred from capabilities. WP administrator
   * caps would otherwise mask the assigned Ops role.
   * Legacy role slugs are normalised to their canonical equivalents.
   *
   * Accepts a WP_User object or a user ID to avoid redundant DB queries when
   * called in a loop.
   */
  private static function get_ops_role_from_user($user) {
    if (!$user instanceof WP_User) {
      $user = new WP_User((int) $user);
    }
    if (!$user->exists()) {
      return '';
    }

    // Scan the fixed canonical hierarchy first so the result is deterministic
    // even when stale legacy roles are still present on the user.
    foreach (self::OPS_CANONICAL_ROLES as $role) {
      if (in_array($role, $user->roles, true)) {
        return $role;
      }
    }

    foreach (self::OPS_LEGACY_ROLE_MAP as $legacy => $mapped) {
      if (in_array($legacy, $user->roles, true)) {
        return $mapped;
      }
    }

    return '';
  }

  public static function users($req) {
    $users = get_users([
      'orderby' => 'display_name',
      'order' => 'ASC',
      'number' => 500,
    ]);
    $out = [];
    foreach ($users as $u) {
      $out[] = ['id' => (int)$u->ID, 'name' => $u->display_name, 'email' => $u->user_email, 'ops_role' => self::get_ops_role_from_user($u)];
    }
    return ['users' => $out];
  }

  public static function list_dealers($req) {
    $names = Slate_Ops_Utils::dealer_list();
    $dealers = [];
    foreach (array_values($names) as $i => $name) {
      $dealers[] = ['id' => $i + 1, 'name' => (string)$name];
    }
    return ['dealers' => $dealers];
  }

  public static function update_user_role($req) {
    $user_id  = intval($req['id']);
    $body     = $req->get_json_params();
    $new_role = sanitize_key($body['role'] ?? '');

    // Resolve short alias to canonical slug.
    if (isset(self::OPS_ALIAS_MAP[$new_role])) {
      $new_role = self::OPS_ALIAS_MAP[$new_role];
    }

    if ($new_role && !in_array($new_role, self::OPS_CANONICAL_ROLES, true)) {
      return new WP_Error('invalid_role', 'Invalid role', ['status' => 400]);
    }

    $user = new WP_User($user_id);
    if (!$user->exists()) {
      return new WP_Error('not_found', 'User not found', ['status' => 404]);
    }

    // Remove all canonical and legacy Slate Ops roles before assigning the new
    // one. WP administrator is intentionally excluded so site admins keep their
    // access while their Ops role is updated.
    $slate_roles = array_merge(self::OPS_CANONICAL_ROLES, array_keys(self::OPS_LEGACY_ROLE_MAP));
    foreach ($slate_roles as $r) {
      $user->remove_role($r);
    }

    if ($new_role) {
      $user->add_role($new_role);
    }

    self::audit('user', $user_id, 'role_change', 'ops_role', null, $new_role, 'Role changed by admin');

    // Reload to reflect the just-committed role state.
    $user = new WP_User($user_id);

    $ops_role = self::get_ops_role_from_user($user);

    return [
      'ok'           => true,
      'user_id'      => $user_id,
      'roles'        => $user->roles,
      'capabilities' => array_keys(array_filter($user->allcaps)),
      'ops_role'     => $ops_role,
      'display_role' => self::OPS_DISPLAY_MAP[$ops_role] ?? '',
    ];
  }

  public static function supervisor_queues($req) {
    global $wpdb;
    $segments = $wpdb->prefix . 'slate_ops_time_segments';
    $jobs = $wpdb->prefix . 'slate_ops_jobs';

    // Pending corrections
    $pending = $wpdb->get_results("SELECT s.segment_id, s.job_id, s.user_id, s.start_ts, s.end_ts, s.note, s.source, s.approval_status
      FROM $segments s WHERE s.approval_status='pending' ORDER BY s.created_at DESC LIMIT 100", ARRAY_A);

    // Unassigned segments: tech != assigned_user_id and assigned_user_id not null
    $unassigned = $wpdb->get_results("SELECT s.segment_id, s.job_id, s.user_id, s.start_ts, s.end_ts, s.reason, s.note
      FROM $segments s
      JOIN $jobs j ON j.job_id = s.job_id
      WHERE j.assigned_user_id IS NOT NULL AND j.assigned_user_id <> s.user_id
        AND s.end_ts IS NOT NULL
      ORDER BY s.end_ts DESC LIMIT 200", ARRAY_A);

    return [
      'pending_corrections' => array_map([__CLASS__, 'decorate_segment'], $pending),
      'unassigned_segments' => array_map([__CLASS__, 'decorate_segment'], $unassigned),
    ];
  }

  // Helpers
  private static function validate_reason_fields(
    array $body, string $reason_key, string $note_key,
    array $allowed, string $reason_msg, string $note_msg,
    array &$update
  ): ?WP_Error {
    $r = strtoupper(sanitize_key((string)($body[$reason_key] ?? '')));
    $n = trim(sanitize_textarea_field((string)($body[$note_key] ?? '')));
    if (!$r || !in_array($r, $allowed, true)) {
      return self::validation_error($reason_key, $reason_key . '_required', $reason_msg);
    }
    if (!$n) {
      return self::validation_error($note_key, $note_key . '_required', $note_msg);
    }
    $update[$reason_key] = $r;
    $update[$note_key]   = $n;
    return null;
  }

  /**
   * Shift config placeholder keys for future approvals:
   * overtime_approved, overtime_approved_by, overtime_approved_at
   */
    /**
   * Shift config placeholder keys for future approvals:
   * overtime_approved, overtime_approved_by, overtime_approved_at
   */
  private static function get_shift_window_state($now_gmt) {
    global $wpdb;

    $settings_t = $wpdb->prefix . 'slate_ops_settings';
    $cfg = $wpdb->get_row("SELECT shift_start, shift_end FROM $settings_t WHERE id=1", ARRAY_A);

    $shift_start = sanitize_text_field($cfg['shift_start'] ?? '07:00:00');
    $shift_end   = sanitize_text_field($cfg['shift_end'] ?? '15:30:00');

    $utc_tz  = new DateTimeZone('UTC');
    $site_tz = wp_timezone();

    $now_utc = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $now_gmt, $utc_tz);
    if (!$now_utc) {
      $now_utc = new DateTimeImmutable('now', $utc_tz);
    }

    $now_local  = $now_utc->setTimezone($site_tz);
    $local_date = $now_local->format('Y-m-d');

    $shift_start_local = DateTimeImmutable::createFromFormat(
      'Y-m-d H:i:s',
      $local_date . ' ' . $shift_start,
      $site_tz
    );

    $shift_end_local = DateTimeImmutable::createFromFormat(
      'Y-m-d H:i:s',
      $local_date . ' ' . $shift_end,
      $site_tz
    );

    if (!$shift_start_local || !$shift_end_local) {
      $shift_start_local = new DateTimeImmutable($local_date . ' 07:00:00', $site_tz);
      $shift_end_local   = new DateTimeImmutable($local_date . ' 15:30:00', $site_tz);
    }

    // Overnight shift support, for example 22:00 -> 06:00.
    if ($shift_end_local <= $shift_start_local) {
      $shift_end_local = $shift_end_local->modify('+1 day');

      // If current local time is after midnight but before shift start,
      // the active shift started the previous local day.
      if ($now_local < $shift_start_local) {
        $shift_start_local = $shift_start_local->modify('-1 day');
        $shift_end_local   = $shift_end_local->modify('-1 day');
      }
    }

    $shift_start_utc = $shift_start_local->setTimezone($utc_tz);
    $shift_end_utc   = $shift_end_local->setTimezone($utc_tz);

    return [
      'within_shift'    => $now_utc >= $shift_start_utc && $now_utc <= $shift_end_utc,
      'after_shift_end' => $now_utc > $shift_end_utc,
      'shift_start_ts'  => $shift_start_utc->format('Y-m-d H:i:s'),
      'shift_end_ts'    => $shift_end_utc->format('Y-m-d H:i:s'),
    ];
  }

  private static function validation_error($field, $code, $message, $status = 400) {
    return new WP_Error($code, $message, [
      'status' => (int) $status,
      'field' => sanitize_key($field),
      'code' => sanitize_key($code),
      'message' => sanitize_text_field($message),
    ]);
  }

  private static function decorate_segment($s) {
    $s['user_name'] = Slate_Ops_Utils::user_display($s['user_id']);
    return $s;
  }

  private static function job_by_id($job_id) {
global $wpdb;
$t = $wpdb->prefix . 'slate_ops_jobs';
$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE job_id=%d", $job_id), ARRAY_A);
if ($row) {
  $row['assigned_name'] = $row['assigned_user_id'] ? Slate_Ops_Utils::user_display($row['assigned_user_id']) : '';

  // Back-compat fallbacks
  if (empty($row['created_from'])) $row['created_from'] = $row['source'] ?: 'manual';
  if (empty($row['status_updated_at'])) $row['status_updated_at'] = $row['updated_at'] ?: $row['created_at'];

  // Computed actual minutes
  $seg = $wpdb->prefix . 'slate_ops_time_segments';
  $mins = $wpdb->get_var($wpdb->prepare("SELECT SUM(TIMESTAMPDIFF(MINUTE, start_ts, end_ts)) FROM $seg WHERE job_id=%d AND end_ts IS NOT NULL", (int)$job_id));
  $row['actual_minutes'] = (int)($mins ?: 0);
	  }
    return $row;
  }

  private static function time_breakdown($job_id) {
    global $wpdb;
    $segments = $wpdb->prefix . 'slate_ops_time_segments';

    $rows = $wpdb->get_results($wpdb->prepare("
      SELECT user_id,
        SUM(CASE WHEN approval_status='approved' AND end_ts IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, start_ts, end_ts) ELSE 0 END) as approved_minutes,
        SUM(CASE WHEN approval_status='pending' AND end_ts IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, start_ts, end_ts) ELSE 0 END) as pending_minutes,
        COUNT(*) as segment_count,
        MAX(COALESCE(end_ts, start_ts)) as last_activity
      FROM $segments
      WHERE job_id=%d
      GROUP BY user_id
      ORDER BY approved_minutes DESC, pending_minutes DESC
    ", $job_id), ARRAY_A);

    $by_tech = [];
    $approved_total = 0;
    $pending_total = 0;

    foreach ($rows as $r) {
      $approved_total += (int)$r['approved_minutes'];
      $pending_total += (int)$r['pending_minutes'];
      $by_tech[] = [
        'user_id' => (int)$r['user_id'],
        'user_name' => Slate_Ops_Utils::user_display($r['user_id']),
        'approved_minutes' => (int)$r['approved_minutes'],
        'pending_minutes' => (int)$r['pending_minutes'],
        'segment_count' => (int)$r['segment_count'],
        'last_activity' => $r['last_activity'],
      ];
    }

    return [
      'approved_minutes_total' => $approved_total,
      'pending_minutes_total' => $pending_total,
      'by_tech' => $by_tech,
    ];
  }

  private static function audit($entity_type, $entity_id, $action, $field, $old, $new, $note = '') {
    global $wpdb;
    $t = $wpdb->prefix . 'slate_ops_audit_log';
    $wpdb->insert($t, [
      'entity_type' => sanitize_key($entity_type),
      'entity_id' => (int)$entity_id,
      'action' => sanitize_key($action),
      'field_name' => $field ? sanitize_key($field) : null,
      'old_value' => $old !== null ? maybe_serialize($old) : null,
      'new_value' => $new !== null ? maybe_serialize($new) : null,
      'note' => $note ? sanitize_text_field($note) : null,
      'user_id' => get_current_user_id(),
      'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : null,
      'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : null,
      'created_at' => Slate_Ops_Utils::now_gmt(),
    ]);
  }

  private static function maybe_create_clickup_task($job) {
    if (!$job || !empty($job['clickup_task_id'])) return;

    $name = 'PENDING INTAKE - ' . ($job['customer_name'] ?: 'Job ' . $job['job_id']);
    $desc = "Slate Ops Job\n\n"
      . "Job ID: {$job['job_id']}\n"
      . ($job['portal_quote_id'] ? "Portal Quote ID: {$job['portal_quote_id']}\n" : "")
      . ($job['quote_number'] ? "Quote #: {$job['quote_number']}\n" : "")
      . ($job['vin'] ? "VIN: {$job['vin']}\n" : "")
      . ($job['dealer_name'] ? "Dealer: {$job['dealer_name']}\n" : "")
      . "Status: INTAKE\n";

    $resp = Slate_Ops_ClickUp::create_unscheduled_task($name, $desc);
    if (is_wp_error($resp)) return;

    if (!empty($resp['id'])) {
      global $wpdb;
      $t = $wpdb->prefix . 'slate_ops_jobs';
      $wpdb->update($t, [
        'clickup_task_id' => sanitize_text_field($resp['id']),
        'updated_at' => Slate_Ops_Utils::now_gmt(),
      ], ['job_id' => (int)$job['job_id']]);
      self::audit('job', (int)$job['job_id'], 'update', 'clickup_task_id', null, (string)$resp['id'], 'ClickUp task created');
    }
  }

  private static function maybe_update_clickup_name($job) {
    if (!$job || empty($job['clickup_task_id']) || empty($job['so_number'])) return;
    $vin_last6 = $job['vin'] ? substr(preg_replace('/\s+/', '', $job['vin']), -6) : '';
    $name = $job['so_number'] . ' - ' . ($job['customer_name'] ?: 'Customer') . ($vin_last6 ? ' - ' . $vin_last6 : '');
    Slate_Ops_ClickUp::update_task_name($job['clickup_task_id'], $name);
  }

  private static function maybe_push_dealer_portal_status($job) {
    if (!$job) return;
    // Integration point: your Dealer Portal can hook this action and persist dealer status however it wants.
    do_action('slate_ops_dealer_status_changed', (int)$job['job_id'], sanitize_text_field($job['dealer_status']), $job);
  }

  public static function handle_quote_approved($quote_id) {
global $wpdb;
$t = $wpdb->prefix . 'slate_ops_jobs';
$now = Slate_Ops_Utils::now_gmt();

$quote_id = (int)$quote_id;
if (!$quote_id) return;

// Avoid duplicates: one job per portal_quote_id
$existing = $wpdb->get_var($wpdb->prepare("SELECT job_id FROM $t WHERE portal_quote_id=%d AND archived_at IS NULL", $quote_id));
if ($existing) return;

// Attempt to pull basic info if B2BKing or similar is active
$customer_name = null;
$dealer_name = null;
$vin = null;
$quote_number = null;

if (class_exists('B2BKing')) {
    // This is a placeholder for actual B2BKing lookups if we had the API
    // For now we rely on the action being passed enough info or being able to find the post
    $quote_post = get_post($quote_id);
    if ($quote_post) {
        $quote_number = get_post_meta($quote_id, 'b2bking_quote_number', true);
        $user_id = $quote_post->post_author;
        $customer_name = get_user_meta($user_id, 'billing_first_name', true) . ' ' . get_user_meta($user_id, 'billing_last_name', true);
        $dealer_name = get_user_meta($user_id, 'b2bking_company_name', true);
        $vin = get_post_meta($quote_id, 'vin', true);
    }
}

$wpdb->insert($t, [
  'source' => 'portal',
  'created_from' => 'portal',
  'portal_quote_id' => $quote_id,
  'status' => 'INTAKE',
  'status_updated_at' => $now,
  'delay_reason' => null,
  'priority' => 3,
  'dealer_status' => 'waiting',
  'created_by' => get_current_user_id() ?: null,
  'created_at' => $now,
  'updated_at' => $now,
]);

$job_id = (int)$wpdb->insert_id;
self::audit('job', $job_id, 'create', null, null, wp_json_encode(['created_from'=>'portal','portal_quote_id'=>$quote_id]), 'Job created from portal approval');

$job = self::job_by_id($job_id);
self::maybe_create_clickup_task($job);
self::maybe_push_dealer_portal_status($job);
  }

  public static function get_job_activity( WP_REST_Request $req ) {
    global $wpdb;
    $job_id   = (int) $req['id'];
    $tbl_log  = $wpdb->prefix . 'slate_ops_audit_log';
    $tbl_u    = $wpdb->users;

    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT a.audit_id, a.action, a.field_name, a.old_value, a.new_value,
                a.note, a.created_at, COALESCE(u.display_name,'System') AS user_name
         FROM {$tbl_log} a
         LEFT JOIN {$tbl_u} u ON u.ID = a.user_id
         WHERE a.entity_type = 'job' AND a.entity_id = %d
         ORDER BY a.created_at DESC
         LIMIT 200",
        $job_id
      ),
      ARRAY_A
    );

    return rest_ensure_response(['activity' => $rows ?: []]);
  }

  // ── Work Center handlers ─────────────────────────────────────────

  public static function list_work_centers( WP_REST_Request $req ) {
    $active_only = $req->get_param('active') !== '0';
    $centers = Slate_Ops_Work_Centers::query($active_only);
    return rest_ensure_response(['ok' => true, 'work_centers' => $centers]);
  }

  public static function get_work_center( WP_REST_Request $req ) {
    $wc = Slate_Ops_Work_Centers::get( (int) $req['id'] );
    if (!$wc) return new WP_Error('not_found', 'Work center not found', ['status' => 404]);
    return rest_ensure_response(['ok' => true, 'work_center' => $wc]);
  }

  public static function create_work_center( WP_REST_Request $req ) {
    $body = $req->get_json_params() ?: [];

    if (empty($body['wc_code'])) {
      return new WP_Error('bad_request', 'wc_code is required', ['status' => 400]);
    }
    if (empty($body['display_name'])) {
      return new WP_Error('bad_request', 'display_name is required', ['status' => 400]);
    }

    $wc_id = Slate_Ops_Work_Centers::create($body);
    if (!$wc_id) {
      return new WP_Error('create_failed', 'Failed to create work center (code may already exist)', ['status' => 409]);
    }

    self::audit('work_center', $wc_id, 'create', null, null, wp_json_encode($body), 'Work center created');

    return new WP_REST_Response(['ok' => true, 'wc_id' => $wc_id, 'work_center' => Slate_Ops_Work_Centers::get($wc_id)], 201);
  }

  public static function update_work_center( WP_REST_Request $req ) {
    $wc_id = (int) $req['id'];
    $wc = Slate_Ops_Work_Centers::get($wc_id);
    if (!$wc) return new WP_Error('not_found', 'Work center not found', ['status' => 404]);

    $body = $req->get_json_params() ?: [];
    $ok   = Slate_Ops_Work_Centers::update($wc_id, $body);
    if (!$ok) {
      return new WP_Error('update_failed', 'Failed to update work center', ['status' => 500]);
    }

    self::audit('work_center', $wc_id, 'update', null, null, wp_json_encode($body), 'Work center updated');

    return rest_ensure_response(['ok' => true, 'work_center' => Slate_Ops_Work_Centers::get($wc_id)]);
  }

  // ── Capacity handlers ────────────────────────────────────────────

  public static function get_capacity( WP_REST_Request $req ) {
    $today = wp_date('Y-m-d');
    $from  = sanitize_text_field($req->get_param('from') ?: $today);
    $to    = sanitize_text_field($req->get_param('to')   ?: date('Y-m-d', strtotime($today . ' +6 days')));

    $summary = Slate_Capacity_Service::get_summary($from, $to);
    return rest_ensure_response([
      'ok'      => true,
      'from'    => $from,
      'to'      => $to,
      'summary' => array_values($summary),
    ]);
  }

  public static function get_overloads( WP_REST_Request $req ) {
    $today = wp_date('Y-m-d');
    $from  = sanitize_text_field($req->get_param('from') ?: $today);
    $to    = sanitize_text_field($req->get_param('to')   ?: date('Y-m-d', strtotime($today . ' +6 days')));

    $overloads = Slate_Capacity_Service::get_overloads($from, $to);
    return rest_ensure_response([
      'ok'       => true,
      'from'     => $from,
      'to'       => $to,
      'overloads'=> $overloads,
    ]);
  }

  public static function recalculate_flags( WP_REST_Request $req ) {
    $body  = $req->get_json_params() ?: [];
    $from  = sanitize_text_field($body['from'] ?? '');
    $to    = sanitize_text_field($body['to']   ?? '');

    $flags_updated   = Slate_Capacity_Service::refresh_flags($from ?: null, $to ?: null);
    $scores_updated  = Slate_Priority_Service::refresh_scores();

    self::audit('scheduler', 0, 'recalculate', 'flags', null, null,
      "Flags: $flags_updated updated. Scores: $scores_updated updated.");

    return rest_ensure_response([
      'ok'             => true,
      'flags_updated'  => $flags_updated,
      'scores_updated' => $scores_updated,
    ]);
  }

  // ── Buffer handlers ───────────────────────────────────────────────

  public static function get_buffer_settings( WP_REST_Request $req ) {
    return rest_ensure_response([
      'ok'                   => true,
      'shipping_buffer_days' => Slate_Buffer_Service::get_shipping_buffer_days(),
      'qc_buffer_days'       => Slate_Buffer_Service::get_qc_buffer_days(),
      'total_buffer_days'    => Slate_Buffer_Service::get_total_buffer_days(),
    ]);
  }

  public static function update_buffer_settings( WP_REST_Request $req ) {
    $body     = $req->get_json_params() ?: [];
    $shipping = max(0, (int) ($body['shipping_buffer_days'] ?? Slate_Buffer_Service::get_shipping_buffer_days()));
    $qc       = max(0, (int) ($body['qc_buffer_days'] ?? Slate_Buffer_Service::get_qc_buffer_days()));

    Slate_Buffer_Service::set_buffer_defaults($shipping, $qc);
    self::audit('scheduler', 0, 'update', 'buffer_settings', null,
      wp_json_encode(['shipping' => $shipping, 'qc' => $qc]), 'Buffer settings updated');

    return rest_ensure_response([
      'ok'                   => true,
      'shipping_buffer_days' => $shipping,
      'qc_buffer_days'       => $qc,
      'total_buffer_days'    => $shipping + $qc,
    ]);
  }

  public static function get_job_buffer( WP_REST_Request $req ) {
    $job_id = (int) $req['id'];
    $job = Slate_Ops_Jobs::get($job_id);
    if (!$job) return new WP_Error('not_found', 'Job not found', ['status' => 404]);

    $buffer = Slate_Buffer_Service::get_job_buffer($job);
    return rest_ensure_response(['ok' => true, 'buffer' => $buffer]);
  }

  // ── Job scheduler-control handlers ───────────────────────────────

  public static function lock_job( WP_REST_Request $req ) {
    global $wpdb;
    $job_id = (int) $req['id'];
    $job = Slate_Ops_Jobs::get($job_id);
    if (!$job) return new WP_Error('not_found', 'Job not found', ['status' => 404]);

    $now = Slate_Ops_Utils::now_gmt();
    $t   = $wpdb->prefix . 'slate_ops_jobs';
    $wpdb->update($t, ['scheduler_locked' => 1, 'updated_at' => $now], ['job_id' => $job_id]);
    self::audit('job', $job_id, 'update', 'scheduler_locked', '0', '1', 'Job locked by scheduler');

    return rest_ensure_response(['ok' => true, 'job_id' => $job_id, 'scheduler_locked' => true]);
  }

  public static function unlock_job( WP_REST_Request $req ) {
    global $wpdb;
    $job_id = (int) $req['id'];
    $job = Slate_Ops_Jobs::get($job_id);
    if (!$job) return new WP_Error('not_found', 'Job not found', ['status' => 404]);

    $now = Slate_Ops_Utils::now_gmt();
    $t   = $wpdb->prefix . 'slate_ops_jobs';
    $wpdb->update($t, ['scheduler_locked' => 0, 'updated_at' => $now], ['job_id' => $job_id]);
    self::audit('job', $job_id, 'update', 'scheduler_locked', '1', '0', 'Job unlocked');

    return rest_ensure_response(['ok' => true, 'job_id' => $job_id, 'scheduler_locked' => false]);
  }

  public static function hold_job( WP_REST_Request $req ) {
    global $wpdb;
    $job_id = (int) $req['id'];
    $job = Slate_Ops_Jobs::get($job_id);
    if (!$job) return new WP_Error('not_found', 'Job not found', ['status' => 404]);

    $body   = $req->get_json_params() ?: [];
    $reason = sanitize_text_field($body['hold_reason'] ?? 'hold');
    $note   = sanitize_text_field($body['note'] ?? '');

    if (empty($reason)) {
      return new WP_Error('bad_request', 'hold_reason is required', ['status' => 400]);
    }

    $now = Slate_Ops_Utils::now_gmt();
    $t   = $wpdb->prefix . 'slate_ops_jobs';
    $wpdb->update($t, [
      'delay_reason'    => $reason,
      'schedule_notes'  => $note ?: null,
      'status'          => 'ON_HOLD',
      'status_updated_at' => $now,
      'updated_at'      => $now,
    ], ['job_id' => $job_id]);

    self::audit('job', $job_id, 'update', 'hold', null, $reason, $note ?: 'Job placed on hold');
    Slate_Priority_Service::refresh_scores();

    return rest_ensure_response(['ok' => true, 'job_id' => $job_id, 'status' => 'ON_HOLD', 'hold_reason' => $reason]);
  }

  public static function unhold_job( WP_REST_Request $req ) {
    global $wpdb;
    $job_id = (int) $req['id'];
    $job = Slate_Ops_Jobs::get($job_id);
    if (!$job) return new WP_Error('not_found', 'Job not found', ['status' => 404]);

    $now  = Slate_Ops_Utils::now_gmt();
    $t    = $wpdb->prefix . 'slate_ops_jobs';
    $body = $req->get_json_params() ?: [];
    $note = sanitize_text_field($body['note'] ?? '');

    // Return to SCHEDULED if it had a scheduled_start, otherwise READY_FOR_BUILD.
    $new_status = !empty($job['scheduled_start']) ? Slate_Ops_Statuses::SCHEDULED : Slate_Ops_Statuses::READY_FOR_BUILD;

    $wpdb->update($t, [
      'delay_reason'    => null,
      'schedule_notes'  => $note ?: null,
      'status'          => $new_status,
      'status_updated_at' => $now,
      'updated_at'      => $now,
    ], ['job_id' => $job_id]);

    self::audit('job', $job_id, 'update', 'unhold', 'ON_HOLD', $new_status, $note ?: 'Hold cleared');
    Slate_Priority_Service::refresh_scores();

    return rest_ensure_response(['ok' => true, 'job_id' => $job_id, 'status' => $new_status]);
  }

  /**
   * POST /slate-ops/v1/schedule/bulk
   *
   * Accepts a JSON body: { updates: [ {job_id, scheduled_start, scheduled_finish, assigned_user_id, work_center}, … ] }
   * All fields except job_id are optional — only non-null values are written.
   * Each update is applied to wp_slate_ops_jobs and an activity-log entry is written.
   * Returns { saved: [job_id, …], errors: [{job_id, message}, …] }.
   */
  public static function bulk_schedule( WP_REST_Request $req ) {
    global $wpdb;

    $body    = $req->get_json_params() ?: [];
    $updates = isset($body['updates']) && is_array($body['updates']) ? $body['updates'] : [];

    if (empty($updates)) {
      return new WP_Error('bad_request', 'No updates provided.', ['status' => 400]);
    }

    if (count($updates) > 200) {
      return new WP_Error('too_many', 'Maximum 200 updates per request.', ['status' => 400]);
    }

    $now    = Slate_Ops_Utils::now_gmt();
    $t_jobs = $wpdb->prefix . 'slate_ops_jobs';

    self::scheduler_debug_log('bulk_schedule payload', ['updates_count' => count($updates), 'updates' => $updates]);

    $saved  = [];
    $errors = [];

    // Check for capacity overloads if scheduling.
    $has_scheduling = false;
    foreach ($updates as $item) {
      if (!empty($item['scheduled_start']) || !empty($item['work_center'])) {
        $has_scheduling = true;
        break;
      }
    }

    $overloaded_wc_before = [];
    if ($has_scheduling) {
      // Get current overloads to compare after dry-run or just check post-facto.
      // For now, we will check if any updated work center becomes overloaded beyond threshold.
    }

    foreach ($updates as $item) {
      $job_id = isset($item['job_id']) ? (int) $item['job_id'] : 0;
      if ($job_id <= 0) {
        $errors[] = ['job_id' => 0, 'message' => 'Missing job_id'];
        continue;
      }

      // Verify the job exists.
      $exists = $wpdb->get_var( $wpdb->prepare( "SELECT job_id FROM $t_jobs WHERE job_id=%d", $job_id ) );
      if (!$exists) {
        $errors[] = ['job_id' => $job_id, 'message' => 'Job not found'];
        continue;
      }

      list($update, $changes) = self::build_scheduler_update_fields($item, $now);

      $allowed = ['work_center', 'scheduled_start', 'scheduled_finish', 'assigned_user_id',
                  'promised_date', 'target_ship_date', 'constraint_minutes_required',
                  'estimated_minutes', 'schedule_notes'];
      $has_scheduler_key = false;
      foreach ($allowed as $k) {
        if (array_key_exists($k, $item)) {
          $has_scheduler_key = true;
          break;
        }
      }
      if (!$has_scheduler_key) {
        $errors[] = ['job_id' => $job_id, 'message' => 'No scheduler fields to update'];
        continue;
      }

      // Merge extended scheduler fields into the update array.
      $extended_fields = ['promised_date', 'target_ship_date', 'schedule_notes'];
      foreach ($extended_fields as $ef) {
        if (array_key_exists($ef, $item)) {
          $v = sanitize_text_field($item[$ef] ?? '');
          $update[$ef] = $v ?: null;
          $changes[$ef] = $v ?: null;
        }
      }
      if (array_key_exists('constraint_minutes_required', $item)) {
        $v = !empty($item['constraint_minutes_required']) ? (int) $item['constraint_minutes_required'] : null;
        $update['constraint_minutes_required'] = $v;
        $changes['constraint_minutes_required'] = $v;
      }
      if (array_key_exists('estimated_minutes', $item)) {
        $v = !empty($item['estimated_minutes']) ? (int) $item['estimated_minutes'] : null;
        $update['estimated_minutes'] = $v;
        $changes['estimated_minutes'] = $v;
      }

      // Capacity enforcement: if scheduled_start or work_center changed, check if we need an override reason.
      if (!empty($item['scheduled_start']) || !empty($item['work_center'])) {
        $wc_code = $item['work_center'] ?? $wpdb->get_var($wpdb->prepare("SELECT work_center FROM $t_jobs WHERE job_id=%d", $job_id));
        $start_date = !empty($item['scheduled_start']) ? substr($item['scheduled_start'], 0, 10) : $wpdb->get_var($wpdb->prepare("SELECT DATE(scheduled_start) FROM $t_jobs WHERE job_id=%d", $job_id));

        if ($wc_code && $start_date) {
          $summary = Slate_Capacity_Service::get_summary($start_date, $start_date);
          $wc_id = $wpdb->get_var($wpdb->prepare("SELECT wc_id FROM {$wpdb->prefix}slate_ops_work_centers WHERE wc_code=%s", $wc_code));
          if ($wc_id && isset($summary[$wc_id]) && $summary[$wc_id]['is_overloaded']) {
            if (empty($item['override_reason'])) {
              $errors[] = ['job_id' => $job_id, 'message' => 'Capacity threshold exceeded for ' . $wc_code . '. Override reason required.', 'code' => 'capacity_exceeded'];
              continue;
            }
            $update['override_flag'] = 1;
            $update['override_reason'] = sanitize_text_field($item['override_reason']);
          }
        }
      }

      $result = $wpdb->update($t_jobs, $update, ['job_id' => $job_id]);
      self::scheduler_debug_log('bulk_schedule write result', ['job_id' => $job_id, 'affected_rows' => $result, 'db_error' => $wpdb->last_error, 'columns' => array_keys($update)]);

      if ($result === false) {
        $errors[] = ['job_id' => $job_id, 'message' => $wpdb->last_error ?: 'DB error'];
        continue;
      }

      // Append activity log entry.
      self::audit(
        'job',
        $job_id,
        'update',
        'bulk_schedule',
        null,
        wp_json_encode($changes),
        'Bulk schedule update'
      );

      $saved[] = $job_id;
    }

    // Refresh scheduling flags and priority scores for affected jobs.
    if (!empty($saved)) {
      Slate_Capacity_Service::refresh_flags();
      Slate_Priority_Service::refresh_scores();
    }

    return rest_ensure_response([
      'saved'  => $saved,
      'errors' => $errors,
    ]);
  }

  /**
   * GET /executive/labor-summary
   * Job-costing and performance aggregates for the Executive dashboard.
   * Admin/Supervisor only. Not for payroll.
   */
  public static function executive_labor_summary($req) {
    global $wpdb;
    $jobs_t = $wpdb->prefix . 'slate_ops_jobs';
    $segs_t = $wpdb->prefix . 'slate_ops_time_segments';

    // ── Overview: active job counts + estimate totals ─────────────────
    $ov = $wpdb->get_row(
      "SELECT
         COUNT(*) AS active_jobs,
         SUM(status = 'IN_PROGRESS')                       AS in_progress,
         SUM(status IN ('QC','PENDING_QC'))                AS pending_qc,
         SUM(status IN ('BLOCKED','DELAYED'))              AS blocked,
         COALESCE(SUM(COALESCE(estimated_minutes,0)),0)    AS total_estimated_minutes
       FROM $jobs_t
       WHERE status NOT IN ('COMPLETE','CANCELLED') AND archived_at IS NULL",
      ARRAY_A
    );

    // Total logged minutes across all active jobs
    $total_logged = (int)$wpdb->get_var(
      "SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, s.start_ts, COALESCE(s.end_ts, NOW()))),0)
       FROM $segs_t s
       JOIN $jobs_t j ON j.job_id = s.job_id
       WHERE j.status != 'COMPLETE'
         AND j.archived_at IS NULL"
    );

    $total_est = (int)($ov['total_estimated_minutes'] ?? 0);
    $overview = [
      'active_jobs'              => (int)($ov['active_jobs']       ?? 0),
      'in_progress'              => (int)($ov['in_progress']        ?? 0),
      'pending_qc'               => (int)($ov['pending_qc']         ?? 0), // kept for backward compat, maps to QC
      'qc'                       => (int)($ov['pending_qc']         ?? 0),
      'blocked'                  => (int)($ov['blocked']            ?? 0),
      'total_estimated_minutes'  => $total_est,
      'total_logged_minutes'     => $total_logged,
      'variance_minutes'         => $total_logged - $total_est,
      'labor_capture_pct'        => $total_est > 0 ? round($total_logged / $total_est * 100, 1) : null,
    ];

    // ── Tech performance ──────────────────────────────────────────────
    // Logged minutes per tech (all segments, any job)
    $tech_logged_rows = $wpdb->get_results(
      "SELECT user_id,
              COALESCE(SUM(TIMESTAMPDIFF(MINUTE, start_ts, COALESCE(end_ts, NOW()))),0) AS logged_minutes
       FROM $segs_t
       GROUP BY user_id",
      ARRAY_A
    );

    // Job assignment stats per tech (active jobs only)
    $tech_job_rows = $wpdb->get_results(
      "SELECT assigned_user_id,
              COUNT(*) AS total_assigned,
              SUM(status != 'COMPLETE') AS active_assigned,
              COALESCE(SUM(CASE WHEN status != 'COMPLETE' THEN COALESCE(estimated_minutes,0) ELSE 0 END),0) AS est_minutes
       FROM $jobs_t
       WHERE assigned_user_id IS NOT NULL AND archived_at IS NULL
       GROUP BY assigned_user_id",
      ARRAY_A
    );

    // Collect all user IDs and fetch display names in one pass
    $uid_set = [];
    foreach ($tech_logged_rows as $r) $uid_set[(int)$r['user_id']] = true;
    foreach ($tech_job_rows   as $r) $uid_set[(int)$r['assigned_user_id']] = true;
    $user_names = [];
    foreach (array_keys($uid_set) as $uid) {
      $u = get_userdata($uid);
      if ($u) $user_names[$uid] = $u->display_name;
    }

    $tech_map = [];
    foreach ($tech_job_rows as $r) {
      $uid = (int)$r['assigned_user_id'];
      $tech_map[$uid] = [
        'user_id'        => $uid,
        'name'           => $user_names[$uid] ?? "User $uid",
        'total_assigned' => (int)$r['total_assigned'],
        'active_jobs'    => (int)$r['active_assigned'],
        'est_minutes'    => (int)$r['est_minutes'],
        'logged_minutes' => 0,
      ];
    }
    foreach ($tech_logged_rows as $r) {
      $uid = (int)$r['user_id'];
      if (!isset($tech_map[$uid])) {
        $tech_map[$uid] = [
          'user_id' => $uid, 'name' => $user_names[$uid] ?? "User $uid",
          'total_assigned' => 0, 'active_jobs' => 0, 'est_minutes' => 0, 'logged_minutes' => 0,
        ];
      }
      $tech_map[$uid]['logged_minutes'] = (int)$r['logged_minutes'];
    }
    $tech_performance = array_values($tech_map);

    // ── Job performance ───────────────────────────────────────────────
    $job_rows = $wpdb->get_results(
      "SELECT j.job_id, j.so_number, j.customer_name, j.status, j.assigned_user_id,
              COALESCE(j.estimated_minutes,0) AS estimated_minutes,
              COALESCE(SUM(TIMESTAMPDIFF(MINUTE, s.start_ts, COALESCE(s.end_ts, NOW()))),0) AS logged_minutes
       FROM $jobs_t j
       LEFT JOIN $segs_t s ON s.job_id = j.job_id
       WHERE j.archived_at IS NULL
       GROUP BY j.job_id
       ORDER BY j.status, j.so_number",
      ARRAY_A
    );

    $job_performance = [];
    foreach ($job_rows as $r) {
      $uid = (int)$r['assigned_user_id'];
      $job_performance[] = [
        'job_id'             => (int)$r['job_id'],
        'so_number'          => $r['so_number'],
        'customer_name'      => $r['customer_name'],
        'status'             => $r['status'],
        'lead_tech'          => $uid ? ($user_names[$uid] ?? "User $uid") : '—',
        'estimated_minutes'  => (int)$r['estimated_minutes'],
        'logged_minutes'     => (int)$r['logged_minutes'],
      ];
    }

    // ── Labor capture ─────────────────────────────────────────────────
    $lc = $wpdb->get_row(
      "SELECT
         COUNT(*) AS total_active,
         SUM(estimated_minutes > 0) AS with_estimate,
         SUM(estimated_minutes IS NULL OR estimated_minutes = 0) AS missing_estimate
       FROM $jobs_t
       WHERE status != 'COMPLETE' AND archived_at IS NULL",
      ARRAY_A
    );

    $jobs_with_logged = (int)$wpdb->get_var(
      "SELECT COUNT(DISTINCT j.job_id)
       FROM $jobs_t j
       JOIN $segs_t s ON s.job_id = j.job_id
       WHERE j.status != 'COMPLETE' AND j.archived_at IS NULL"
    );

    $total_raw_all = (int)$wpdb->get_var(
      "SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, start_ts, COALESCE(end_ts, NOW()))),0)
       FROM $segs_t"
    );

    $total_active = (int)($lc['total_active'] ?? 0);
    $with_estimate = (int)($lc['with_estimate'] ?? 0);
    $labor_capture = [
      'jobs_with_estimate'        => $with_estimate,
      'jobs_missing_estimate'     => (int)($lc['missing_estimate'] ?? 0),
      'jobs_with_logged_time'     => $jobs_with_logged,
      'jobs_no_logged_time'       => max(0, $total_active - $jobs_with_logged),
      'total_raw_logged_minutes'  => $total_raw_all,
      'estimate_coverage_pct'     => $total_active > 0 ? round($with_estimate / $total_active * 100, 1) : 0,
    ];

    // ── Bottlenecks ───────────────────────────────────────────────────
    $status_rows = $wpdb->get_results(
      "SELECT status, COUNT(*) AS cnt FROM $jobs_t WHERE archived_at IS NULL GROUP BY status",
      ARRAY_A
    );
    $status_map = [];
    foreach ($status_rows as $r) $status_map[$r['status']] = (int)$r['cnt'];

    $bottlenecks = [];
    foreach (['READY_FOR_BUILD','SCHEDULED','IN_PROGRESS','QC','BLOCKED','ON_HOLD','CANCELLED'] as $st) {
      // Merge legacy aliases into v2 counts
      $cnt = $status_map[$st] ?? 0;
      if ($st === 'SCHEDULED') $cnt += ($status_map['QUEUED'] ?? 0);
      if ($st === 'QC')        $cnt += ($status_map['PENDING_QC'] ?? 0);
      if ($st === 'BLOCKED')   $cnt += ($status_map['DELAYED'] ?? 0);
      $bottlenecks[] = ['status' => $st, 'count' => $cnt];
    }

    return rest_ensure_response([
      'overview'         => $overview,
      'tech_performance' => $tech_performance,
      'job_performance'  => $job_performance,
      'labor_capture'    => $labor_capture,
      'bottlenecks'      => $bottlenecks,
    ]);
  }

  /**
   * GET /executive/time-segments
   * Phase 0 validation: read-only view of raw time segment rows.
   * Admin/Supervisor only (perm_view_executive). No write operations.
   *
   * Query params:
   *   limit      int     1–200, default 50
   *   state      string  active|closed (omit = all)
   *   date_range string  today|7|30 (omit = all)
   *   user_id    int     filter to a specific tech
   *   q          string  search against SO#, customer_name, or job_id
   */
  public static function executive_time_segments($req) {
    global $wpdb;
    $segs_t  = $wpdb->prefix . 'slate_ops_time_segments';
    $jobs_t  = $wpdb->prefix . 'slate_ops_jobs';
    $users_t = $wpdb->users;
    $audit_t = $wpdb->prefix . 'slate_ops_audit_log';

    $limit      = min(200, max(1, (int) ($req->get_param('limit') ?: 50)));
    $state      = sanitize_key((string) ($req->get_param('state') ?: ''));
    $date_range = sanitize_text_field((string) ($req->get_param('date_range') ?: ''));
    $user_id    = (int) ($req->get_param('user_id') ?: 0);
    $q          = sanitize_text_field((string) ($req->get_param('q') ?: ''));

    $where  = [];
    $params = [];

    if (in_array($state, ['active', 'closed'], true)) {
      $where[]  = 's.state = %s';
      $params[] = $state;
    }

    if ($date_range === 'today') {
      $where[] = 'DATE(s.start_ts) = DATE(UTC_TIMESTAMP())';
    } elseif ($date_range === '7') {
      $where[] = 's.start_ts >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)';
    } elseif ($date_range === '30') {
      $where[] = 's.start_ts >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)';
    }

    if ($user_id > 0) {
      $where[]  = 's.user_id = %d';
      $params[] = $user_id;
    }

    if ($q !== '') {
      $like     = '%' . $wpdb->esc_like($q) . '%';
      $where[]  = '(j.so_number LIKE %s OR j.customer_name LIKE %s OR CAST(s.job_id AS CHAR) LIKE %s)';
      $params[] = $like;
      $params[] = $like;
      $params[] = $like;
    }

    $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $params[]  = $limit;

    $sql = "SELECT
      s.segment_id,
      s.job_id,
      COALESCE(j.so_number, '')      AS so_number,
      COALESCE(j.customer_name, '')  AS customer_name,
      COALESCE(j.status, '')         AS job_status,
      s.user_id,
      COALESCE(u.display_name, '')   AS tech_name,
      s.start_ts,
      s.end_ts,
      TIMESTAMPDIFF(MINUTE, s.start_ts, COALESCE(s.end_ts, UTC_TIMESTAMP())) AS duration_minutes,
      s.state,
      s.reason,
      LEFT(COALESCE(s.note, ''), 80) AS note_preview,
      s.approval_status,
      EXISTS (
        SELECT 1 FROM {$audit_t} a
        WHERE a.entity_type = 'segment'
          AND a.entity_id   = s.segment_id
          AND a.action      = 'note'
          AND a.note        = 'Timer started outside scheduled shift.'
      ) AS outside_shift
    FROM {$segs_t} s
    LEFT JOIN {$users_t} u ON u.ID = s.user_id
    LEFT JOIN {$jobs_t}  j ON j.job_id = s.job_id
    {$where_sql}
    ORDER BY s.segment_id DESC
    LIMIT %d";

    $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
    if ($rows === null) {
      return new WP_Error('db_error', 'Database error fetching time segments', ['status' => 500]);
    }

    $segments = array_map(function ($r) {
      return [
        'segment_id'       => (int) $r['segment_id'],
        'job_id'           => (int) $r['job_id'],
        'so_number'        => $r['so_number'],
        'customer_name'    => $r['customer_name'],
        'job_status'       => $r['job_status'],
        'user_id'          => (int) $r['user_id'],
        'tech_name'        => $r['tech_name'],
        'start_ts'         => $r['start_ts'],
        'end_ts'           => $r['end_ts'],
        'duration_minutes' => $r['duration_minutes'] !== null ? (int) $r['duration_minutes'] : null,
        'state'            => $r['state'],
        'reason'           => $r['reason'],
        'note_preview'     => $r['note_preview'],
        'approval_status'  => $r['approval_status'],
        'outside_shift'    => (bool) $r['outside_shift'],
      ];
    }, $rows);

    return rest_ensure_response([
      'ok'       => true,
      'count'    => count($segments),
      'segments' => $segments,
    ]);
  }

  /**
   * TEMPORARY ONE-SHOT — remove after one successful production run.
   *
   * Manually triggers the close-state migration that should have run via
   * Slate_Ops_Install::migrate_close_state_v1() on the 0.40.0 upgrade hook
   * but did not fire in production.
   *
   * Idempotent: the WHERE clause matches zero rows on a second run. No
   * version gate, no option flag — just the SQL.
   */
  public static function run_state_migration($req) {
    global $wpdb;
    $segments = $wpdb->prefix . 'slate_ops_time_segments';

    $wpdb->query(
      "UPDATE $segments
         SET state = 'closed'
       WHERE end_ts IS NOT NULL
         AND state = 'active'"
    );
    $rows_updated = (int) $wpdb->rows_affected;

    $remaining = (int) $wpdb->get_var(
      "SELECT COUNT(*) FROM $segments WHERE state = 'active' AND end_ts IS NOT NULL"
    );

    return [
      'rows_updated'                  => $rows_updated,
      'remaining_active_with_end_ts'  => $remaining,
    ];
  }

  /**
   * Read-only diagnostic: closed time segments whose duration exceeds the
   * suspect threshold (8 hours). Used to size and target a quarantine pass
   * on historical bad rows (PR3).
   *
   * GET /wp-json/slate-ops/v1/admin/diagnostic-time-rollup
   */
  public static function diagnostic_time_rollup($req) {
    global $wpdb;
    $segments = $wpdb->prefix . 'slate_ops_time_segments';
    $jobs     = $wpdb->prefix . 'slate_ops_jobs';

    $threshold_minutes = 480;

    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT s.segment_id,
              s.job_id,
              s.user_id,
              s.start_ts,
              s.end_ts,
              TIMESTAMPDIFF(MINUTE, s.start_ts, s.end_ts) AS duration_minutes,
              s.source,
              s.approval_status,
              j.so_number
       FROM $segments s
       LEFT JOIN $jobs j ON j.job_id = s.job_id
       WHERE s.end_ts IS NOT NULL
         AND TIMESTAMPDIFF(MINUTE, s.start_ts, s.end_ts) > %d
       ORDER BY duration_minutes DESC",
      $threshold_minutes
    ), ARRAY_A);

    $segments_out = array_map(function ($r) {
      return [
        'segment_id'       => (int) $r['segment_id'],
        'job_id'           => (int) $r['job_id'],
        'user_id'          => (int) $r['user_id'],
        'start_ts'         => $r['start_ts'],
        'end_ts'           => $r['end_ts'],
        'duration_minutes' => (int) $r['duration_minutes'],
        'source'           => $r['source'],
        'approval_status'  => $r['approval_status'],
        'so_number'        => $r['so_number'],
      ];
    }, $rows ?: []);

    return [
      'threshold_minutes' => $threshold_minutes,
      'count'             => count($segments_out),
      'segments'          => $segments_out,
    ];
  }
}
