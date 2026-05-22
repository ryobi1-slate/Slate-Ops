<?php
/**
 * Slate_Ops_Quality — Quality module data layer.
 *
 * Owns the form template registry (the five QMS forms), the canonical
 * status vocabulary, photo-slot definitions, and storage helpers backed
 * by the wp_slate_ops_quality_forms table. Quality state is always
 * attached to an existing Ops job — Quality never creates jobs.
 *
 * The form templates (checklist items, photo slots, signature shape) are
 * defined inline here so the registry is the single source of truth. Job
 * type → required forms is also resolved here.
 *
 * All methods are static.
 */
if (!defined('ABSPATH')) exit;

class Slate_Ops_Quality {

  // ── Canonical status vocabulary (the only six allowed) ──────────────────
  const STATUS_NOT_STARTED      = 'not_started';
  const STATUS_IN_PROGRESS      = 'in_progress';
  const STATUS_SUBMITTED        = 'submitted';
  const STATUS_NEEDS_CORRECTION = 'needs_correction';
  const STATUS_PASSED           = 'passed';
  const STATUS_LOCKED           = 'locked';

  // ── Form codes ──────────────────────────────────────────────────────────
  const FORM_QMS_004 = 'QMS-004';
  const FORM_QMS_005 = 'QMS-005';
  const FORM_QMS_006 = 'QMS-006';
  const FORM_QMS_009 = 'QMS-009';
  const FORM_QMS_010 = 'QMS-010';

  // ── Table ───────────────────────────────────────────────────────────────
  public static function table() {
    global $wpdb;
    return $wpdb->prefix . 'slate_ops_quality_forms';
  }

  // ── Status helpers ──────────────────────────────────────────────────────

  public static function allowed_statuses() {
    return [
      self::STATUS_NOT_STARTED,
      self::STATUS_IN_PROGRESS,
      self::STATUS_SUBMITTED,
      self::STATUS_NEEDS_CORRECTION,
      self::STATUS_PASSED,
      self::STATUS_LOCKED,
    ];
  }

  public static function status_label($status) {
    $labels = [
      self::STATUS_NOT_STARTED      => 'Not Started',
      self::STATUS_IN_PROGRESS      => 'In Progress',
      self::STATUS_SUBMITTED        => 'Submitted',
      self::STATUS_NEEDS_CORRECTION => 'Needs Correction',
      self::STATUS_PASSED           => 'Passed',
      self::STATUS_LOCKED           => 'Locked',
    ];
    return $labels[$status] ?? 'Not Started';
  }

  public static function status_pill_class($status) {
    $map = [
      self::STATUS_NOT_STARTED      => 'not-started',
      self::STATUS_IN_PROGRESS      => 'in-progress',
      self::STATUS_SUBMITTED        => 'submitted',
      self::STATUS_NEEDS_CORRECTION => 'needs-correction',
      self::STATUS_PASSED           => 'passed',
      self::STATUS_LOCKED           => 'locked',
    ];
    return $map[$status] ?? 'not-started';
  }

  // ── Photo slot catalogues ───────────────────────────────────────────────

  public static function check_in_photo_slots() {
    return [
      ['key' => 'front_exterior',     'label' => 'Front exterior',     'required' => true],
      ['key' => 'rear_exterior',      'label' => 'Rear exterior',      'required' => true],
      ['key' => 'driver_side',        'label' => 'Driver side',        'required' => true],
      ['key' => 'passenger_side',     'label' => 'Passenger side',     'required' => true],
      ['key' => 'vin_plate',          'label' => 'VIN plate',          'required' => true],
      ['key' => 'odometer',           'label' => 'Odometer',           'required' => true],
      ['key' => 'driver_cockpit',     'label' => 'Driver cockpit',     'required' => true],
      ['key' => 'cargo_area',         'label' => 'Cargo area',         'required' => true],
      ['key' => 'existing_damage',    'label' => 'Existing damage',    'required' => false,
        'hint' => 'Required if exterior or interior inspection fails'],
    ];
  }

  public static function final_signoff_photo_slots() {
    return [
      ['key' => 'completed_front',          'label' => 'Completed front',          'required' => true],
      ['key' => 'completed_rear',           'label' => 'Completed rear',           'required' => true],
      ['key' => 'completed_driver_side',    'label' => 'Completed driver side',    'required' => true],
      ['key' => 'completed_passenger_side', 'label' => 'Completed passenger side', 'required' => true],
      ['key' => 'completed_interior',       'label' => 'Completed interior',       'required' => true],
      ['key' => 'completed_equipment',      'label' => 'Completed installed equipment', 'required' => true],
      ['key' => 'correction_photos',        'label' => 'Correction photos',        'required' => false,
        'hint' => 'Required if supervisor requested correction'],
    ];
  }

  // ── Form template registry ──────────────────────────────────────────────

  /**
   * Returns the full registry of QMS form templates. Each form defines its
   * checklist sections, photo slots and signature shape. This is the single
   * source of truth for form structure used by both the runner and the
   * supervisor review.
   */
  public static function form_registry() {
    $registry = [
      self::FORM_QMS_004 => [
        'code'        => self::FORM_QMS_004,
        'revision'    => '1.1',
        'name'        => 'RVIA Check-In Sign-Off',
        'eyebrow'     => 'QMS-004 · RVIA',
        'kind'        => 'check_in',
        'job_types'   => ['rvia'],
        'description' => 'Documents condition of the chassis at intake before any RVIA work begins.',
        'sections'    => [
          [
            'key'   => 'inspection',
            'label' => 'Vehicle inspection',
            'items' => [
              ['key' => 'exterior',        'label' => 'Exterior inspection',
                'desc' => 'Walk the chassis. Note any body damage, paint defects, or panel gaps.'],
              ['key' => 'interior',        'label' => 'Interior inspection',
                'desc' => 'Check seats, dash, door panels, headliner, and floor.'],
              ['key' => 'lighting',        'label' => 'Lighting check',
                'desc' => 'Test every exterior and interior lamp.'],
              ['key' => 'cluster',         'label' => 'Instrument cluster',
                'desc' => 'Ensure no warning lights are displayed before delivery.'],
              ['key' => 'fluids',          'label' => 'Fluid levels',
                'desc' => 'Verify coolant, oil, washer, brake, and DEF where applicable.'],
              ['key' => 'tires',           'label' => 'Tires and wheels',
                'desc' => 'Check tread depth, air pressure, and lug torque.'],
              ['key' => 'keys',            'label' => 'Keys delivered',
                'desc' => 'Confirm count of physical keys and key fobs.'],
              ['key' => 'documents',       'label' => 'Documents present',
                'desc' => 'Title or MSO, registration, RVIA paperwork.'],
            ],
          ],
        ],
        'photo_slots' => self::check_in_photo_slots(),
      ],

      self::FORM_QMS_005 => [
        'code'        => self::FORM_QMS_005,
        'revision'    => '1.1',
        'name'        => 'RVIA Testing Sign-Off',
        'eyebrow'     => 'QMS-005 · RVIA',
        'kind'        => 'testing',
        'job_types'   => ['rvia'],
        'description' => 'Electrical, plumbing, and life-safety verification per RVIA code.',
        'sections'    => [
          [
            'key'   => 'electrical',
            'label' => 'Electrical & bonding',
            'items' => [
              ['key' => 'highpot',  'label' => '120V high-pot test',     'desc' => 'NEC 551.60 high-pot verification.'],
              ['key' => 'bond',     'label' => 'Continuity & bonding',   'desc' => 'NEC 551.60(1) chassis ground continuity.'],
              ['key' => 'polarity', 'label' => 'Polarity test',          'desc' => 'NEC 551.60(3) receptacle polarity.'],
              ['key' => 'gfci',     'label' => 'GFCI function test',     'desc' => 'NEC 551.60(4) GFCI trip + reset under load.'],
              ['key' => 'lighting', 'label' => 'Lighting & marker lamp', 'desc' => 'NFPA 1194 exterior marker verification.'],
              ['key' => 'ground',   'label' => 'Chassis grounding',      'desc' => 'NEC 551.60 chassis ground bond.'],
            ],
          ],
          [
            'key'   => 'plumbing',
            'label' => 'Plumbing & gas',
            'items' => [
              ['key' => 'trap',     'label' => 'Trap installation',      'desc' => 'NFPA 1192 7.4.4.1 trap fitment for every fixture.'],
              ['key' => 'water',    'label' => 'Water system pressure',  'desc' => 'Hold 100 psi for 10 minutes — confirm no drop.'],
              ['key' => 'lpg',      'label' => 'LP gas leak test',       'desc' => 'NFPA 1192 LP system pressure & leak check.'],
              ['key' => 'fire',     'label' => 'Fire & life safety',     'desc' => 'NFPA 1194 smoke, CO, LP detectors armed.'],
            ],
          ],
        ],
        'photo_slots' => [
          ['key' => 'panel',      'label' => 'Power panel',      'required' => true],
          ['key' => 'gfci',       'label' => 'GFCI under load',  'required' => true],
          ['key' => 'water_test', 'label' => 'Water pressure',   'required' => true],
          ['key' => 'lpg_test',   'label' => 'LP gas test',      'required' => true],
        ],
      ],

      self::FORM_QMS_006 => [
        'code'        => self::FORM_QMS_006,
        'revision'    => '1.2',
        'name'        => 'Completed RVIA Sign-Off',
        'eyebrow'     => 'QMS-006 · RVIA',
        'kind'        => 'final',
        'job_types'   => ['rvia'],
        'description' => 'Final delivery sign-off. Unlocks after install completes and QMS-005 passes.',
        'depends_on'  => [self::FORM_QMS_005],
        'sections'    => [
          [
            'key'   => 'final',
            'label' => 'Final delivery checks',
            'items' => [
              ['key' => 'exterior',  'label' => 'Exterior finished',
                'desc' => 'Body and paint complete, panels aligned, no rework outstanding.'],
              ['key' => 'interior',  'label' => 'Interior finished',
                'desc' => 'All fitments installed and clean.'],
              ['key' => 'systems',   'label' => 'All systems operational',
                'desc' => 'Power, plumbing, gas, lighting all functioning.'],
              ['key' => 'cleanup',   'label' => 'Vehicle clean & detailed',
                'desc' => 'Interior vacuumed, exterior washed.'],
              ['key' => 'paperwork', 'label' => 'Customer paperwork ready',
                'desc' => 'Sign-off packet, manuals, warranty registration.'],
            ],
          ],
        ],
        'photo_slots' => self::final_signoff_photo_slots(),
      ],

      self::FORM_QMS_009 => [
        'code'        => self::FORM_QMS_009,
        'revision'    => '1.1',
        'name'        => 'Commercial & Non-RVIA Check-In Sign-Off',
        'eyebrow'     => 'QMS-009 · Commercial',
        'kind'        => 'check_in',
        'job_types'   => ['commercial'],
        'description' => 'Intake inspection for commercial and non-RVIA upfit jobs.',
        'sections'    => [
          [
            'key'   => 'inspection',
            'label' => 'Vehicle inspection',
            'items' => [
              ['key' => 'exterior',  'label' => 'Exterior inspection',
                'desc' => 'Walk the chassis. Note damage, paint, panels.'],
              ['key' => 'interior',  'label' => 'Interior inspection',
                'desc' => 'Check seats, dash, doors, cargo area.'],
              ['key' => 'lighting',  'label' => 'Lighting check',
                'desc' => 'Test exterior and interior lamps.'],
              ['key' => 'cluster',   'label' => 'Instrument cluster',
                'desc' => 'No warning lights displayed.'],
              ['key' => 'fluids',    'label' => 'Fluid levels',
                'desc' => 'Coolant, oil, washer, brake.'],
              ['key' => 'tires',     'label' => 'Tires and wheels',
                'desc' => 'Tread depth, pressure, torque.'],
              ['key' => 'keys',      'label' => 'Keys delivered',
                'desc' => 'Confirm count and condition.'],
            ],
          ],
        ],
        'photo_slots' => self::check_in_photo_slots(),
      ],

      self::FORM_QMS_010 => [
        'code'        => self::FORM_QMS_010,
        'revision'    => '1.3',
        'name'        => 'Completed Commercial & Non-RVIA Sign-Off',
        'eyebrow'     => 'QMS-010 · Commercial',
        'kind'        => 'final',
        'job_types'   => ['commercial'],
        'description' => 'Final sign-off for commercial and non-RVIA jobs.',
        'depends_on'  => [self::FORM_QMS_009],
        'sections'    => [
          [
            'key'   => 'final',
            'label' => 'Final delivery checks',
            'items' => [
              ['key' => 'exterior',  'label' => 'Exterior finished',
                'desc' => 'Body and paint complete, no rework outstanding.'],
              ['key' => 'interior',  'label' => 'Interior finished',
                'desc' => 'All fitments installed and clean.'],
              ['key' => 'equipment', 'label' => 'Installed equipment functional',
                'desc' => 'Customer-specified upfit equipment tested.'],
              ['key' => 'cleanup',   'label' => 'Vehicle clean & detailed',
                'desc' => 'Interior vacuumed, exterior washed.'],
              ['key' => 'paperwork', 'label' => 'Customer paperwork ready',
                'desc' => 'Sign-off packet, manuals, warranty registration.'],
            ],
          ],
        ],
        'photo_slots' => self::final_signoff_photo_slots(),
      ],
    ];

    return apply_filters('slate_ops_quality_form_registry', $registry);
  }

  public static function get_form_template($form_code) {
    $reg = self::form_registry();
    return $reg[$form_code] ?? null;
  }

  // ── Job type → form set resolution ──────────────────────────────────────

  /**
   * Normalize a job's stored job_type to a Quality bucket.
   * Returns 'rvia' or 'commercial'.
   */
  public static function quality_job_type($job_type) {
    $jt = strtoupper((string) $job_type);
    if (in_array($jt, ['RV_BUILD', 'RV_UPFIT', 'RVIA', 'RVIA_BUILD'], true)) {
      return 'rvia';
    }
    return 'commercial';
  }

  /**
   * Returns the ordered list of required form codes for a given job type.
   */
  public static function required_forms_for_job_type($job_type) {
    if (self::quality_job_type($job_type) === 'rvia') {
      return [self::FORM_QMS_004, self::FORM_QMS_005, self::FORM_QMS_006];
    }
    return [self::FORM_QMS_009, self::FORM_QMS_010];
  }

  /**
   * Computes the overall Quality status for a job from its form rows.
   */
  public static function rollup_status(array $form_rows, array $required_codes) {
    if (empty($form_rows)) return self::STATUS_NOT_STARTED;

    $by_code = [];
    foreach ($form_rows as $row) {
      $by_code[$row['form_code']] = $row['status'];
    }

    $has_correction = false;
    $has_submitted  = false;
    $has_progress   = false;
    $all_passed     = true;

    foreach ($required_codes as $code) {
      $st = $by_code[$code] ?? self::STATUS_NOT_STARTED;
      if ($st === self::STATUS_NEEDS_CORRECTION) $has_correction = true;
      if ($st === self::STATUS_SUBMITTED)        $has_submitted  = true;
      if ($st === self::STATUS_IN_PROGRESS)      $has_progress   = true;
      if ($st !== self::STATUS_PASSED)           $all_passed     = false;
    }

    if ($all_passed) return self::STATUS_PASSED;
    if ($has_correction) return self::STATUS_NEEDS_CORRECTION;
    if ($has_submitted)  return self::STATUS_SUBMITTED;
    if ($has_progress)   return self::STATUS_IN_PROGRESS;
    return self::STATUS_NOT_STARTED;
  }

  // ── Storage helpers ─────────────────────────────────────────────────────

  /**
   * Fetch all Quality form rows for a job. Returns an array of assoc rows.
   */
  public static function get_forms_for_job($job_id) {
    global $wpdb;
    $t = self::table();
    $rows = $wpdb->get_results(
      $wpdb->prepare("SELECT * FROM $t WHERE job_id = %d ORDER BY form_code ASC", (int) $job_id),
      ARRAY_A
    ) ?: [];
    return array_map([__CLASS__, 'hydrate_row'], $rows);
  }

  public static function get_form($job_id, $form_code) {
    global $wpdb;
    $t = self::table();
    $row = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM $t WHERE job_id = %d AND form_code = %s", (int) $job_id, $form_code),
      ARRAY_A
    );
    return $row ? self::hydrate_row($row) : null;
  }

  /**
   * Decode JSON payload columns and add convenience fields.
   */
  private static function hydrate_row(array $row) {
    $row['payload'] = json_decode((string)($row['payload'] ?? '{}'), true) ?: [];
    $row['photos']  = json_decode((string)($row['photos']  ?? '{}'), true) ?: [];
    $row['status_label'] = self::status_label($row['status']);
    $row['locked']  = !empty($row['locked_at']);
    return $row;
  }

  /**
   * Ensure a form row exists for (job, form_code). Returns the hydrated row.
   * Status defaults to in_progress on first touch unless an initial status
   * is provided. Will not downgrade an existing row.
   */
  public static function ensure_form_row($job_id, $form_code, $initial_status = self::STATUS_NOT_STARTED) {
    global $wpdb;
    $existing = self::get_form($job_id, $form_code);
    if ($existing) return $existing;

    $now = current_time('mysql');
    $wpdb->insert(self::table(), [
      'job_id'      => (int) $job_id,
      'form_code'   => $form_code,
      'status'      => in_array($initial_status, self::allowed_statuses(), true) ? $initial_status : self::STATUS_NOT_STARTED,
      'payload'     => '{}',
      'photos'      => '{}',
      'created_by'  => get_current_user_id() ?: null,
      'created_at'  => $now,
      'updated_at'  => $now,
    ], ['%d','%s','%s','%s','%s','%d','%s','%s']);

    return self::get_form($job_id, $form_code);
  }

  /**
   * Save draft of a form's payload. Promotes status to in_progress if it was
   * not_started. Refuses if the form is currently locked.
   */
  public static function save_draft($job_id, $form_code, array $payload) {
    global $wpdb;
    $row = self::ensure_form_row($job_id, $form_code);
    if (!empty($row['locked_at'])) {
      return new WP_Error('quality_locked', 'This form is locked. Ask a supervisor to unlock it before editing.', ['status' => 423]);
    }

    $new_status = ($row['status'] === self::STATUS_NOT_STARTED || $row['status'] === self::STATUS_NEEDS_CORRECTION)
      ? self::STATUS_IN_PROGRESS
      : $row['status'];

    $wpdb->update(self::table(),
      [
        'status'     => $new_status,
        'payload'    => wp_json_encode($payload),
        'updated_by' => get_current_user_id() ?: null,
        'updated_at' => current_time('mysql'),
      ],
      ['job_id' => (int) $job_id, 'form_code' => $form_code],
      ['%s','%s','%d','%s'],
      ['%d','%s']
    );

    return self::get_form($job_id, $form_code);
  }

  /**
   * Attach an attachment ID to a photo slot. Stored as photos[slot] = [ids…].
   * Multiple photos per slot are allowed; the first is the primary.
   */
  public static function attach_photo($job_id, $form_code, $slot_key, $attachment_id) {
    global $wpdb;
    $row = self::ensure_form_row($job_id, $form_code);
    if (!empty($row['locked_at'])) {
      return new WP_Error('quality_locked', 'This form is locked.', ['status' => 423]);
    }
    $photos = is_array($row['photos']) ? $row['photos'] : [];
    if (!isset($photos[$slot_key]) || !is_array($photos[$slot_key])) {
      $photos[$slot_key] = [];
    }
    $aid = (int) $attachment_id;
    if ($aid && !in_array($aid, $photos[$slot_key], true)) {
      $photos[$slot_key][] = $aid;
    }
    $wpdb->update(self::table(),
      ['photos' => wp_json_encode($photos), 'updated_by' => get_current_user_id() ?: null, 'updated_at' => current_time('mysql')],
      ['job_id' => (int) $job_id, 'form_code' => $form_code],
      ['%s','%d','%s'],
      ['%d','%s']
    );
    return self::get_form($job_id, $form_code);
  }

  /**
   * Submit and lock a form. Caller is responsible for validating that every
   * checklist item has a PASS/FAIL response and required photos are present.
   */
  public static function submit_form($job_id, $form_code, array $signature) {
    global $wpdb;
    $row = self::ensure_form_row($job_id, $form_code);
    if (!empty($row['locked_at']) && $row['status'] === self::STATUS_SUBMITTED) {
      return new WP_Error('quality_already_submitted', 'This form has already been submitted.', ['status' => 409]);
    }

    $now = current_time('mysql');
    $sig = [
      'typed_name' => sanitize_text_field($signature['typed_name'] ?? ''),
      'user_id'    => (int) get_current_user_id(),
      'timestamp'  => $now,
    ];
    if ($sig['typed_name'] === '') {
      return new WP_Error('quality_signature_required', 'Type your name to sign and submit.', ['status' => 400]);
    }

    $payload = is_array($row['payload']) ? $row['payload'] : [];
    $payload['signature'] = $sig;

    $wpdb->update(self::table(),
      [
        'status'         => self::STATUS_SUBMITTED,
        'payload'        => wp_json_encode($payload),
        'submitted_by'   => get_current_user_id() ?: null,
        'submitted_at'   => $now,
        'locked_at'      => $now,
        'signature_name' => $sig['typed_name'],
        'updated_by'     => get_current_user_id() ?: null,
        'updated_at'     => $now,
      ],
      ['job_id' => (int) $job_id, 'form_code' => $form_code],
      ['%s','%s','%d','%s','%s','%s','%d','%s'],
      ['%d','%s']
    );
    return self::get_form($job_id, $form_code);
  }

  /**
   * Supervisor review. $decision is 'passed' or 'needs_correction'.
   * Needs Correction also unlocks the form so the tech can resubmit.
   */
  public static function review_form($job_id, $form_code, $decision, $reviewer_note = '') {
    global $wpdb;
    $row = self::get_form($job_id, $form_code);
    if (!$row) return new WP_Error('quality_not_found', 'Form not found.', ['status' => 404]);
    if ($row['status'] !== self::STATUS_SUBMITTED && $row['status'] !== self::STATUS_NEEDS_CORRECTION) {
      return new WP_Error('quality_invalid_review', 'Form must be submitted before review.', ['status' => 409]);
    }

    $now      = current_time('mysql');
    $decision = $decision === self::STATUS_PASSED ? self::STATUS_PASSED : self::STATUS_NEEDS_CORRECTION;

    $payload = is_array($row['payload']) ? $row['payload'] : [];
    $payload['review'] = [
      'decision'    => $decision,
      'note'        => sanitize_textarea_field($reviewer_note),
      'reviewer_id' => (int) get_current_user_id(),
      'timestamp'   => $now,
    ];

    $wpdb->update(self::table(),
      [
        'status'      => $decision,
        'payload'     => wp_json_encode($payload),
        'reviewed_by' => get_current_user_id() ?: null,
        'reviewed_at' => $now,
        // Needs Correction unlocks for re-edit; Passed stays locked.
        'locked_at'   => $decision === self::STATUS_PASSED ? $row['locked_at'] : null,
        'updated_by'  => get_current_user_id() ?: null,
        'updated_at'  => $now,
      ],
      ['job_id' => (int) $job_id, 'form_code' => $form_code],
      ['%s','%s','%d','%s','%s','%d','%s'],
      ['%d','%s']
    );
    return self::get_form($job_id, $form_code);
  }

  /**
   * Supervisor-only unlock. Requires a reason.
   */
  public static function unlock_form($job_id, $form_code, $reason) {
    global $wpdb;
    $row = self::get_form($job_id, $form_code);
    if (!$row) return new WP_Error('quality_not_found', 'Form not found.', ['status' => 404]);

    $reason = sanitize_textarea_field((string) $reason);
    if ($reason === '') {
      return new WP_Error('quality_unlock_reason_required', 'Unlock reason is required.', ['status' => 400]);
    }
    $now = current_time('mysql');
    $payload = is_array($row['payload']) ? $row['payload'] : [];
    $payload['unlocks'][] = [
      'reason'    => $reason,
      'user_id'   => (int) get_current_user_id(),
      'timestamp' => $now,
    ];
    $wpdb->update(self::table(),
      [
        'locked_at'  => null,
        'status'     => self::STATUS_IN_PROGRESS,
        'payload'    => wp_json_encode($payload),
        'updated_by' => get_current_user_id() ?: null,
        'updated_at' => $now,
      ],
      ['job_id' => (int) $job_id, 'form_code' => $form_code],
      ['%s','%s','%s','%d','%s'],
      ['%d','%s']
    );
    return self::get_form($job_id, $form_code);
  }

  // ── Job helpers (read-only convenience for the dashboard) ───────────────

  /**
   * Return a normalized job descriptor for Quality views. Pulls from the
   * jobs table when present; falls back to an empty descriptor.
   */
  public static function describe_job($job_id) {
    if (!class_exists('Slate_Ops_Jobs')) return null;
    $row = Slate_Ops_Jobs::get($job_id);
    if (!$row) return null;

    $required = self::required_forms_for_job_type($row['job_type'] ?? '');
    $forms    = self::get_forms_for_job($job_id);

    return [
      'job_id'         => (int) $row['job_id'],
      'so_number'      => $row['so_number'] ?? '',
      'vin'            => $row['vin'] ?? '',
      'vin_last8'      => $row['vin_last8'] ?? '',
      'customer_name'  => $row['customer_name'] ?? '',
      'dealer_name'    => $row['dealer_name'] ?? '',
      'job_type'       => $row['job_type'] ?? '',
      'quality_type'   => self::quality_job_type($row['job_type'] ?? ''),
      'assigned_user_id' => $row['assigned_user_id'] ? (int) $row['assigned_user_id'] : null,
      'required_forms' => $required,
      'forms'          => $forms,
      'rollup_status'  => self::rollup_status($forms, $required),
      'updated_at'     => $row['updated_at'] ?? null,
    ];
  }

  /**
   * Lightweight listing for the dashboard. Returns up to $limit job
   * descriptors with their Quality rollup status. Filters by status bucket
   * when provided.
   */
  public static function list_jobs(array $args = []) {
    if (!class_exists('Slate_Ops_Jobs')) return [];
    $limit  = max(1, (int) ($args['limit'] ?? 100));
    $status = $args['status'] ?? null;

    $rows = Slate_Ops_Jobs::query([
      'limit'            => $limit,
      'exclude_archived' => true,
      'order_by'         => 'updated_at',
      'order'            => 'DESC',
    ]);

    $out = [];
    foreach ($rows as $r) {
      $desc = self::describe_job((int) $r['job_id']);
      if (!$desc) continue;
      if ($status && $desc['rollup_status'] !== $status) continue;
      $out[] = $desc;
    }
    return $out;
  }

  /**
   * Status bucket counts for the dashboard.
   */
  public static function bucket_counts() {
    $buckets = array_fill_keys(self::allowed_statuses(), 0);
    foreach (self::list_jobs(['limit' => 500]) as $j) {
      $buckets[$j['rollup_status']] = ($buckets[$j['rollup_status']] ?? 0) + 1;
    }
    return $buckets;
  }
}
