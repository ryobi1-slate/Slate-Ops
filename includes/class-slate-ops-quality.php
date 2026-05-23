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
  /**
   * Returns the full registry of QMS form templates. Each form's checklist
   * mirrors the approved paper form one-for-one — same items, same labels,
   * same descriptions, same order. Do not add items that are not in the
   * paper form; the paper "Photos" checklist row stays in the checklist
   * even though we ALSO collect the required digital photo slots via
   * `photo_slots`. The two serve different purposes:
   *   • Photos (checklist) is the tech's PASS/FAIL attestation that
   *     they took the required pictures (matches the paper row).
   *   • photo_slots is the structured upload tray of specific slots
   *     the tech must capture (matches the QMS spec photo requirements).
   *
   * Source of truth: the QMS-004/005/006/009/010 paper forms shipped with
   * the design package.
   */
  public static function form_registry() {
    $registry = [
      // ── QMS-004 Rev. 1.1 — RVIA Check-In Sign-Off ──────────────────
      // Paper form: 6 checklist items.
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
            'key'   => 'checklist',
            'label' => 'RVIA Check-In Checklist',
            'items' => [
              ['key' => 'exterior_inspection',  'label' => 'Exterior Inspection',
                'desc' => 'Check for body damage or defects'],
              ['key' => 'interior_inspection',  'label' => 'Interior Inspection',
                'desc' => 'Verify no damage to seats, dash, and door panels'],
              ['key' => 'lighting_check',       'label' => 'Lighting Check',
                'desc' => 'Test all interior and exterior lights'],
              ['key' => 'instrument_cluster',   'label' => 'Instrument Cluster',
                'desc' => 'Ensure no warning lights are displayed'],
              ['key' => 'photos',               'label' => 'Photos',
                'desc' => 'Photos of the van have been taken'],
              ['key' => 'test_drive',           'label' => 'Test Drive',
                'desc' => 'Identify any abnormal noises or performance issues'],
            ],
          ],
        ],
        'photo_slots' => self::check_in_photo_slots(),
      ],

      // ── QMS-005 Rev. 1.1 — RVIA Testing Sign-Off ───────────────────
      // Paper form: 15 checklist items keyed by NEC / NFPA / ANSI codes.
      self::FORM_QMS_005 => [
        'code'        => self::FORM_QMS_005,
        'revision'    => '1.1',
        'name'        => 'RVIA Testing Sign-Off',
        'eyebrow'     => 'QMS-005 · RVIA',
        'kind'        => 'testing',
        'job_types'   => ['rvia'],
        'description' => 'Electrical, plumbing, and life-safety verification per RVIA code.',
        'depends_on'  => [self::FORM_QMS_004],
        'sections'    => [
          [
            'key'   => 'checklist',
            'label' => 'RVIA Testing Checklist',
            'items' => [
              ['key' => 'highpot_pre',     'label' => '120V High-Pot Test (Prior to Energizing)',
                'code' => 'NEC 551.60',
                'desc' => 'Ensures insulation integrity before power is applied'],
              ['key' => 'continuity_bond', 'label' => 'Continuity & Bonding Test',
                'code' => 'NEC 551.60(1)',
                'desc' => 'Ensures all metal components are properly connected'],
              ['key' => 'polarity',        'label' => 'Polarity Test',
                'code' => 'NEC 551.60(3)',
                'desc' => 'Confirms that hot, neutral, and ground wiring are correctly connected'],
              ['key' => 'gfci',            'label' => 'GFCI Function Test',
                'code' => 'NEC 551.60(4)',
                'desc' => 'Verifies that Ground Fault Circuit Interrupters (GFCIs) function correctly'],
              ['key' => 'lighting_marker', 'label' => 'Lighting & Exterior Marker Lamp Check',
                'code' => 'NFPA 1194',
                'desc' => 'Ensures required lights meet visibility standards'],
              ['key' => 'fire_safety',     'label' => 'Fire & Life Safety Equip. Verification',
                'code' => 'NFPA 1194',
                'desc' => 'Confirms all safety equipment is functional'],
              ['key' => 'chassis_ground',  'label' => 'Chassis Grounding Verification',
                'code' => 'NEC 551.60',
                'desc' => 'Ensures chassis is properly grounded'],
              ['key' => 'highpot_post',    'label' => 'Final 120V High-Pot Test (Post Assembly)',
                'code' => 'NEC 551.60',
                'desc' => 'Verifies insulation integrity post-assembly'],
              ['key' => 'twelve_v',        'label' => '12V Operational Test',
                'code' => 'ANSI/RVIA LV 8-1',
                'desc' => 'Confirms correct function of low-voltage systems'],
              ['key' => 'sink_install',    'label' => 'Sink Installation Verification',
                'code' => 'NFPA 1192 7.1.2.1',
                'desc' => 'Non-listed sink constructed of 304 stainless steel. Used in non-pressurized system with justification memo on file.'],
              ['key' => 'trap',            'label' => 'Trap Installation (Sink)',
                'code' => 'NFPA 1192 7.4.4.1',
                'desc' => 'HepvO ASME A112.18.8-certified waterless trap installed and secured.'],
              ['key' => 'gravity_drain',   'label' => 'Gravity Drain System',
                'code' => 'NFPA 1192 7.4.7',
                'desc' => 'Drain tubing is flexible and secured. Gravity-fed only, no pressurized plumbing.'],
              ['key' => 'tank_labels',     'label' => 'Tank Identification Labels',
                'code' => 'RVIA Labeling',
                'desc' => 'Labels present: "POTABLE WATER ONLY" and "GREY WATER ONLY" on tanks and cabinet.'],
              ['key' => 'bond_gauge',      'label' => 'Bonding Wire Gauge Check',
                'code' => 'NEC 551.56(C)',
                'desc' => 'Grounding wire is 8 AWG copper or larger. Resistance under 0.1 ohms verified.'],
              ['key' => 'bond_access',     'label' => 'Bonding Point Accessibility',
                'code' => 'NEC 551.56(B)',
                'desc' => 'Ground terminal is visible and accessible.'],
            ],
          ],
        ],
        // QMS-005 has no required photo slots on the paper form.
        'photo_slots' => [],
      ],

      // ── QMS-006 Rev. 1.2 — Completed RVIA Sign-Off ─────────────────
      // Paper form: 13 checklist items, final delivery verification.
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
            'key'   => 'checklist',
            'label' => 'Completed RVIA Checklist',
            'items' => [
              ['key' => 'work_matches_estimate',  'label' => 'Work completed matches the estimate',
                'desc' => ''],
              ['key' => 'power_and_battery',      'label' => 'All power inputs and battery system working',
                'desc' => ''],
              ['key' => 'lights_fan_pump',        'label' => 'All installed lights, fan, water pump, monitors working',
                'desc' => ''],
              ['key' => 'paperwork_remotes',      'label' => 'Paperwork & remote controls in glovebox',
                'desc' => ''],
              ['key' => 'equipment_secured',      'label' => 'All installed equipment secured and travel-ready',
                'desc' => ''],
              ['key' => 'windows_stickers',       'label' => 'Windows cleaned, stickers, and protective film removed',
                'desc' => ''],
              ['key' => 'interior_clean',         'label' => 'Interior is cleaned, free of dust and fingerprints',
                'desc' => ''],
              ['key' => 'lugs_torqued',           'label' => 'Wheel lugs torqued',
                'desc' => 'Record front and rear torque values in the notes.'],
              ['key' => 'tire_pressure_tpms',     'label' => 'Tires are at the correct air pressure, TPMS Programmed',
                'desc' => ''],
              ['key' => 'warning_stickers',       'label' => 'Warning stickers installed',
                'desc' => ''],
              ['key' => 'cluster_clear',          'label' => 'Check for warning lights in cluster',
                'desc' => ''],
              ['key' => 'leak_test',              'label' => 'Leak-test van (windows, fan, shore power inlet, solar inlet)',
                'desc' => ''],
              ['key' => 'final_test_drive',       'label' => 'Test drive to check for rattles/noises',
                'desc' => ''],
            ],
          ],
        ],
        'photo_slots' => self::final_signoff_photo_slots(),
      ],

      // ── QMS-009 Rev. 1.1 — Commercial & Non-RVIA Check-In Sign-Off ─
      // Paper form: 6 checklist items.
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
            'key'   => 'checklist',
            'label' => 'Commercial & Non-RVIA Check-In Checklist',
            'items' => [
              ['key' => 'exterior_inspection', 'label' => 'Exterior Inspection',
                'desc' => 'Check for body damage or defects'],
              ['key' => 'interior_inspection', 'label' => 'Interior Inspection',
                'desc' => 'Verify no damage to seats, dash, door panels, and floor'],
              ['key' => 'lighting_check',      'label' => 'Lighting Check',
                'desc' => 'Test all interior and exterior lights'],
              ['key' => 'instrument_cluster',  'label' => 'Instrument Cluster',
                'desc' => 'Ensure no warning lights are displayed'],
              ['key' => 'photos',              'label' => 'Photos',
                'desc' => 'Photos of the van have been taken'],
              ['key' => 'test_drive',          'label' => 'Test Drive',
                'desc' => 'Identify any abnormal noises or performance issues'],
            ],
          ],
        ],
        'photo_slots' => self::check_in_photo_slots(),
      ],

      // ── QMS-010 Rev. 1.3 — Completed Commercial & Non-RVIA Sign-Off ─
      // Paper form: 15 checklist items, final delivery verification.
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
            'key'   => 'checklist',
            'label' => 'Completed Commercial & Non-RVIA Checklist',
            'items' => [
              ['key' => 'work_matches_estimate', 'label' => 'Work completed matches the estimate',
                'desc' => ''],
              ['key' => 'hardware_tightened',    'label' => 'Hardware checked and tightened',
                'desc' => ''],
              ['key' => 'protective_film',       'label' => 'Remove any protective film from installed items',
                'desc' => ''],
              ['key' => 'wipe_down',             'label' => 'Wipe down installed items to be free of fingerprints and dust',
                'desc' => ''],
              ['key' => 'vacuum_blowout',        'label' => 'Vacuum and blow out the van to be free of metal shavings and any debris left from installation',
                'desc' => ''],
              ['key' => 'driver_compartment',    'label' => "Driver's compartment cleaned and free of debris",
                'desc' => ''],
              ['key' => 'seats_normal',          'label' => 'Seats returned to normal positions',
                'desc' => ''],
              ['key' => 'customer_items_back',   'label' => 'Place any removed items belonging to the customer back into the van',
                'desc' => ''],
              ['key' => 'leak_test',             'label' => 'Leak test if windows, fan, or large hole is cut into van',
                'desc' => ''],
              ['key' => 'photos_completed',      'label' => 'Photos of completed van have been taken',
                'desc' => ''],
              ['key' => 'intake_form_in_slip',   'label' => 'Van intake form placed in the slip with invoice',
                'desc' => ''],
              ['key' => 'test_drive_noises',     'label' => 'Test drive van to check for unusual noises',
                'desc' => ''],
              ['key' => 'park_outside',          'label' => 'Park the van outside to be picked up',
                'desc' => ''],
              ['key' => 'keys_in_slip',          'label' => 'Place keys in slip with the invoice and hang slip "Done" hook outside office',
                'desc' => ''],
              ['key' => 'completed_checklist',   'label' => 'Completed checklist with invoice',
                'desc' => ''],
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

  /**
   * Deterministic short fingerprint of the current registry, derived from
   * the item-key topology only (section keys + item keys per form, in
   * order). Surfaced on REST responses so staging/prod can prove which
   * registry version the server is actually serving when a cache layer is
   * suspected.
   */
  public static function registry_fingerprint() {
    $topology = [];
    foreach (self::form_registry() as $code => $tpl) {
      $section_keys = [];
      foreach (($tpl['sections'] ?? []) as $section) {
        $item_keys = array_map(function ($i) { return $i['key'] ?? ''; }, $section['items'] ?? []);
        $section_keys[] = ($section['key'] ?? '') . ':' . implode(',', $item_keys);
      }
      $topology[] = $code . '#' . ($tpl['revision'] ?? '') . '#' . implode('|', $section_keys);
    }
    return substr(hash('sha256', implode("\n", $topology)), 0, 12);
  }

  // ── Job type → form set resolution ──────────────────────────────────────

  /**
   * Normalize a job's stored job_type to a Quality bucket.
   *
   * Slate Ops `slate_ops_jobs.job_type` currently carries the values listed
   * in Slate_Ops_Utils::cs_job_types(): UPFIT, COMMERCIAL_UPFIT,
   * COMMERCIAL_BUILD, RV_BUILD, RV_UPFIT, PARTS_ONLY, SERVICE, WARRANTY.
   * Only the RV_* family runs the RVIA sign-off track; everything else
   * (including PARTS_ONLY / SERVICE / WARRANTY) gets the shorter Commercial
   * & Non-RVIA pair as a safe fallback per the QMS spec.
   *
   * Match is whitelist-first on a normalized token so unknown future values
   * cannot accidentally land in the RVIA bucket.
   *
   * Returns 'rvia' or 'commercial'.
   */
  public static function quality_job_type($job_type) {
    $jt = strtoupper(trim((string) $job_type));

    // Whitelist of values that must take the RVIA track.
    $rvia_set = [
      'RV_BUILD',
      'RV_UPFIT',
      'RVIA',
      'RVIA_BUILD',
      'RVIA_UPFIT',
    ];
    if (in_array($jt, $rvia_set, true)) {
      return 'rvia';
    }

    // Allow downstream integrations to override mapping for custom job-type
    // labels without forking this class.
    $override = apply_filters('slate_ops_quality_job_type_bucket', null, $jt);
    if ($override === 'rvia' || $override === 'commercial') {
      return $override;
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
   * Return dependency form codes that have not yet been supervisor-passed.
   */
  public static function unmet_dependencies($job_id, $form_code) {
    $template = self::get_form_template($form_code);
    $deps = is_array($template['depends_on'] ?? null) ? $template['depends_on'] : [];
    $unmet = [];
    foreach ($deps as $dep) {
      $dep_row = self::get_form($job_id, $dep);
      if (!$dep_row || $dep_row['status'] !== self::STATUS_PASSED) {
        $unmet[] = $dep;
      }
    }
    return $unmet;
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
   * Decode JSON payload columns and add convenience fields. The checklist
   * inside the payload is normalized against the current registry template
   * for non-terminal rows so saved drafts can never resurrect items that
   * were removed from the registry.
   *
   * Historical (submitted/passed) rows keep their on-disk payload intact —
   * normalization on those would rewrite the historical record.
   */
  private static function hydrate_row(array $row) {
    $row['payload'] = json_decode((string)($row['payload'] ?? '{}'), true) ?: [];
    $row['photos']  = json_decode((string)($row['photos']  ?? '{}'), true) ?: [];
    $row['status_label'] = self::status_label($row['status']);
    $row['locked']  = !empty($row['locked_at']);

    $is_historical = in_array($row['status'], [self::STATUS_SUBMITTED, self::STATUS_PASSED], true);
    if (!$is_historical) {
      $template = self::get_form_template($row['form_code'] ?? '');
      if ($template) {
        $row['payload'] = self::normalize_payload_against_template($row['payload'], $template);
      }
    }
    return $row;
  }

  /**
   * Reshape a saved payload so its `checklist` keys mirror the current
   * registry template exactly. Per-item responses (result / note / initials
   * / user_id / timestamp) are preserved when their (section_key, item_key)
   * still exists in the registry; obsolete keys are dropped. New registry
   * items appear as missing (no entry in the checklist).
   *
   * Photos, vehicle, notes, signature, review, and unlocks are passed
   * through untouched — they're identified by their own keys, not by
   * checklist position. This intentionally only edits the checklist
   * sub-tree; it does NOT delete uploaded media or audit trail data.
   */
  public static function normalize_payload_against_template(array $payload, array $template) {
    $saved_checklist = is_array($payload['checklist'] ?? null) ? $payload['checklist'] : [];
    $current = [];
    foreach (($template['sections'] ?? []) as $section) {
      $sk = $section['key'] ?? '';
      if ($sk === '') continue;
      $current[$sk] = [];
      $saved_section = is_array($saved_checklist[$sk] ?? null) ? $saved_checklist[$sk] : [];
      foreach (($section['items'] ?? []) as $item) {
        $ik = $item['key'] ?? '';
        if ($ik === '') continue;
        if (isset($saved_section[$ik]) && is_array($saved_section[$ik])) {
          $current[$sk][$ik] = $saved_section[$ik];
        }
        // Else: leave it absent (renders as unanswered).
      }
    }
    $payload['checklist'] = $current;
    return $payload;
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
    if (!empty(self::unmet_dependencies($job_id, $form_code))) {
      return new WP_Error('quality_dependency_locked', 'This form is locked until the prior step is passed.', ['status' => 423]);
    }

    $row = self::ensure_form_row($job_id, $form_code);
    if (!empty($row['locked_at'])) {
      return new WP_Error('quality_locked', 'This form is locked. Ask a supervisor to unlock it before editing.', ['status' => 423]);
    }

    // Normalize the incoming checklist against the current registry so
    // obsolete item keys submitted by a stale client are dropped before
    // they get persisted. Photos / vehicle / notes / signature / review
    // are passed through untouched.
    $template = self::get_form_template($form_code);
    if ($template) {
      $payload = self::normalize_payload_against_template($payload, $template);
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
    if (!empty(self::unmet_dependencies($job_id, $form_code))) {
      return new WP_Error('quality_dependency_locked', 'This form is locked until the prior step is passed.', ['status' => 423]);
    }

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
    if (!empty(self::unmet_dependencies($job_id, $form_code))) {
      return new WP_Error('quality_dependency_locked', 'This form is locked until the prior step is passed.', ['status' => 423]);
    }

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
