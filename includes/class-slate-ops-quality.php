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

  private static function qms_005_item($section, $key, $title, $description, $severity, $photo_rule, array $info, $code = '') {
    return [
      'key'        => $key,
      'id'         => $section . '_' . $key,
      'section'    => $section,
      'label'      => $title,
      'title'      => $title,
      'code'       => $code,
      'desc'       => $description,
      'description'=> $description,
      'severity'   => $severity,
      'photo_rule' => $photo_rule,
      'info'       => $info,
    ];
  }

  private static function qms_005_info($purpose, array $procedure, array $pass_criteria, array $common_failures, array $required_evidence, $reference_note) {
    return [
      'purpose'           => $purpose,
      'procedure'         => $procedure,
      'pass_criteria'     => $pass_criteria,
      'common_failures'   => $common_failures,
      'required_evidence' => $required_evidence,
      'reference_note'    => $reference_note,
    ];
  }

  public static function qms_005_sections() {
    return [
      [
        'key'   => 'electrical',
        'label' => 'Electrical',
        'items' => [
          self::qms_005_item('electrical', 'highpot_pre', '120V high-pot test before energizing', 'Verify insulation integrity before shore power or 120V circuits are energized.', 'critical', 'required', self::qms_005_info(
            'Catch wiring insulation or termination issues before power is applied.',
            ['Confirm the test area is clear.', 'Run the approved high-pot test before energizing the circuit.', 'Record any abnormal reading in the item notes.'],
            ['Test completes without breakdown or unsafe leakage.', 'No damaged insulation or exposed conductors are found.'],
            ['Loose termination', 'Pinched insulation', 'Incorrect conductor routing'],
            ['Photo of tester result or test setup tied to the unit.'],
            'NEC 551.60'
          ), 'NEC 551.60'),
          self::qms_005_item('electrical', 'continuity_bond', 'Continuity and bonding test', 'Confirm metal components and bonding paths are electrically continuous.', 'critical', 'required', self::qms_005_info(
            'Verify the van body and installed conductive components share a safe bonding path.',
            ['Inspect bonding points.', 'Test continuity across required metal components.', 'Record resistance concerns in notes.'],
            ['Continuity is present at required bonding points.', 'Bonding hardware is tight and accessible.'],
            ['Paint under bonding lug', 'Loose fastener', 'Missing bonding conductor'],
            ['Photo of bonding point or meter reading.'],
            'NEC 551.60(1)'
          ), 'NEC 551.60(1)'),
          self::qms_005_item('electrical', 'polarity', 'Polarity test', 'Confirm hot, neutral, and ground are correctly wired at applicable outlets and circuits.', 'critical', 'required', self::qms_005_info(
            'Prevent reversed polarity or open-ground conditions before delivery.',
            ['Test applicable receptacles or circuits with the approved tester.', 'Correct any abnormal reading before pass.'],
            ['Tester indicates correct wiring.', 'No open ground, reversed hot/neutral, or unsafe reading remains.'],
            ['Reversed conductors', 'Open ground', 'Loose receptacle termination'],
            ['Photo of tester showing correct result.'],
            'NEC 551.60(3)'
          ), 'NEC 551.60(3)'),
          self::qms_005_item('electrical', 'gfci', 'GFCI function test', 'Verify Ground Fault Circuit Interrupters trip and reset correctly.', 'critical', 'required', self::qms_005_info(
            'Confirm personnel protection works anywhere GFCI protection is installed.',
            ['Press test on the GFCI device or use the approved tester.', 'Confirm protected circuit opens.', 'Reset and confirm power returns.'],
            ['Device trips, resets, and restores power normally.', 'Protected outlets respond as expected.'],
            ['Device will not trip', 'Device will not reset', 'Protected outlet wired incorrectly'],
            ['Photo of tested GFCI or tester result.'],
            'NEC 551.60(4)'
          ), 'NEC 551.60(4)'),
          self::qms_005_item('electrical', 'chassis_ground', 'Chassis grounding verification', 'Confirm chassis ground is installed, protected, and serviceable.', 'critical', 'required', self::qms_005_info(
            'Verify the chassis grounding path is secure and available for service inspection.',
            ['Locate chassis ground.', 'Inspect hardware, conductor condition, and routing.', 'Confirm no corrosion, paint barrier, or loose connection.'],
            ['Ground point is tight, clean, visible, and protected.', 'Conductor is correctly routed and undamaged.'],
            ['Loose hardware', 'Paint or debris under lug', 'Damaged conductor'],
            ['Photo of the chassis ground point.'],
            'NEC 551.60'
          ), 'NEC 551.60'),
          self::qms_005_item('electrical', 'highpot_post', 'Final 120V high-pot test after assembly', 'Run final insulation integrity test after assembly is complete.', 'critical', 'required', self::qms_005_info(
            'Confirm final assembly did not introduce an insulation or wiring fault.',
            ['Run the approved final high-pot test.', 'Inspect any circuit that produces an abnormal result.', 'Document correction before pass.'],
            ['Final test completes without unsafe leakage or breakdown.', 'No unresolved electrical faults remain.'],
            ['Post-assembly wire pinch', 'Damaged insulation', 'Incorrect terminal cover'],
            ['Photo of final tester result or test setup.'],
            'NEC 551.60'
          ), 'NEC 551.60'),
          self::qms_005_item('electrical', 'twelve_v', '12V operational test', 'Confirm low-voltage systems operate correctly under normal use.', 'major', 'required', self::qms_005_info(
            'Verify installed 12V loads and controls work before final quality review.',
            ['Operate installed 12V components.', 'Check switches, indicators, and expected load behavior.', 'Record abnormal operation in notes.'],
            ['Installed 12V components power on and operate normally.', 'No loose, intermittent, or mislabeled control is found.'],
            ['Loose connector', 'Incorrect fuse', 'Mislabeled switch', 'Intermittent operation'],
            ['Photo of operating component, control panel, or meter reading.'],
            'ANSI/RVIA LV 8-1'
          ), 'ANSI/RVIA LV 8-1'),
        ],
      ],
      [
        'key'   => 'plumbing',
        'label' => 'Plumbing',
        'items' => [
          self::qms_005_item('plumbing', 'fresh_tank_securement', 'Fresh tank securement', 'Verify the fresh tank is secured, protected, and serviceable.', 'critical', 'required', self::qms_005_info(
            'Confirm the installed fresh tank cannot shift or rub during normal vehicle use.',
            ['Inspect mounting hardware and support surfaces.', 'Check that the tank is protected from sharp edges and movement.', 'Verify service access remains available.'],
            ['Tank is secure with no movement under hand pressure.', 'Mounting and routing do not damage the tank.'],
            ['Loose strap', 'Unsupported tank span', 'Abrasion point near tank'],
            ['Photo of tank mounting and support.'],
            'Slate RVIA plumbing scope: gravity fill, pump, sink, and grey tank installed.'
          )),
          self::qms_005_item('plumbing', 'gravity_fill_routing', 'Gravity fill hose routing', 'Verify gravity fill hose routing is secure, sloped, and protected.', 'major', 'required', self::qms_005_info(
            'Confirm gravity fill can be used without kinks, sags, or abrasion.',
            ['Inspect the complete fill route.', 'Check clamps, bend radius, and protection at pass-throughs.', 'Confirm the gravity-fill path is the active fill method for this unit.'],
            ['Fill hose is secured and serviceable.', 'Route is protected and does not kink.'],
            ['Kinked hose', 'Missing clamp', 'Unprotected edge contact'],
            ['Photo of fill hose routing and clamps.'],
            'Current Slate RVIA plumbing scope uses gravity fill for fresh-water fill.'
          )),
          self::qms_005_item('plumbing', 'fresh_tank_vent_overflow', 'Fresh tank vent / overflow', 'Confirm vent and overflow routing is open, secured, and correctly terminated.', 'major', 'required', self::qms_005_info(
            'Prevent tank pressurization, water backing up, or overflow into the van.',
            ['Inspect vent and overflow lines.', 'Confirm line ends are protected and routed away from damage.', 'Verify no low trap or pinch point blocks flow.'],
            ['Vent and overflow are open, secured, and routed safely.', 'No water can drain into finished interior surfaces.'],
            ['Pinched vent', 'Loose overflow line', 'Termination inside cabinet'],
            ['Photo of vent or overflow routing.'],
            'Gravity fill system requires a clear vent and overflow path.'
          )),
          self::qms_005_item('plumbing', 'pump_pressure_hold', 'Pump pressure hold test', 'Pressurize the pump system and confirm pressure holds with no cycling or leaks.', 'critical', 'required', self::qms_005_info(
            'Verify the pressurized pump side is sealed and ready for customer use.',
            ['Fill the fresh tank enough for testing.', 'Run pump until it reaches cutoff.', 'Observe for pump cycling or leaks at fittings.'],
            ['Pump reaches cutoff and stays off during hold period.', 'No visible leak is present.'],
            ['Pump cycles repeatedly', 'Loose fitting', 'Air leak at pickup side'],
            ['Photo of pump installation or test condition.'],
            'Pressurized pump is installed for the active sink and fresh-water system.'
          )),
          self::qms_005_item('plumbing', 'sink_fixture_flow', 'Sink fixture flow test', 'Verify sink fixture flow and drain behavior under normal operation.', 'major', 'required', self::qms_005_info(
            'Confirm the installed sink delivers and drains water correctly.',
            ['Run water at the sink.', 'Confirm flow, shutoff, and drain behavior.', 'Watch under-sink area during operation.'],
            ['Water flows and shuts off normally.', 'Drain clears without backing up.'],
            ['Low flow', 'Loose fixture', 'Slow or backed-up drain'],
            ['Photo of running sink or fixture.'],
            'Sink is installed in current Slate RVIA plumbing scope.'
          )),
          self::qms_005_item('plumbing', 'under_sink_leak', 'Under-sink leak check', 'Inspect under-sink supply and drain connections for leaks while the system is running.', 'critical', 'required', self::qms_005_info(
            'Catch visible leaks before final completion.',
            ['Run the sink and pump.', 'Inspect supply fittings, trap, drain line, and cabinet floor.', 'Dry and retest after any correction.'],
            ['No drips, seepage, or wet cabinet surfaces are found.', 'Connections are tight and supported.'],
            ['Seeping fitting', 'Loose drain connection', 'Unsupported line pulling on fitting'],
            ['Photo of dry under-sink connections after test.'],
            'Under-sink supply and drain checks cover the active plumbing fixtures in this build.'
          )),
          self::qms_005_item('plumbing', 'grey_tank_drain_leak', 'Grey tank drain / leak test', 'Verify grey tank drain routing and check for leaks during water discharge.', 'critical', 'required', self::qms_005_info(
            'Confirm collected sink water routes to the grey tank and drains without leaks.',
            ['Run water through the sink.', 'Inspect grey tank inlet, body, outlet, and drain route.', 'Confirm drain valve or cap is secure.'],
            ['No leak is visible at grey tank, inlet, outlet, or drain path.', 'Drain hardware is secure and serviceable.'],
            ['Loose drain fitting', 'Tank seam leak', 'Drain cap not sealing'],
            ['Photo of grey tank or drain connection after test.'],
            'Grey tank is installed in current Slate RVIA plumbing scope.'
          )),
          self::qms_005_item('plumbing', 'low_point_winterization', 'Low point drain / winterization check', 'Confirm drain or winterization provisions are identified and serviceable.', 'major', 'required', self::qms_005_info(
            'Make sure the installed plumbing can be drained or winterized by service staff.',
            ['Locate low point drain or winterization access.', 'Verify access is not blocked.', 'Confirm routing and labeling are clear enough for service.'],
            ['Drain or winterization access is reachable and correctly routed.', 'No installed component blocks service access.'],
            ['Hidden drain', 'Blocked access', 'Unclear routing'],
            ['Photo of drain or winterization access.'],
            'Current scope uses gravity fill, pump, sink, and grey tank only.'
          )),
        ],
      ],
      [
        'key'   => 'fire_life_safety',
        'label' => 'Fire / Life Safety',
        'items' => [
          self::qms_005_item('fire_life_safety', 'smoke_alarm', 'Smoke alarm installed and tested', 'Confirm smoke alarm is installed, visible, and responds to test.', 'critical', 'required', self::qms_005_info(
            'Verify smoke detection equipment is installed and operational.',
            ['Locate smoke alarm.', 'Press test button.', 'Confirm audible response and secure mounting.'],
            ['Alarm is mounted securely and test response is audible.', 'Alarm location is visible and not blocked.'],
            ['No audible test', 'Loose mount', 'Blocked location'],
            ['Photo of installed smoke alarm.'],
            'NFPA 1194 / RVIA life-safety verification.'
          )),
          self::qms_005_item('fire_life_safety', 'co_alarm', 'CO alarm installed and tested', 'Confirm CO alarm is installed, visible, and responds to test.', 'critical', 'required', self::qms_005_info(
            'Verify carbon monoxide detection equipment is installed and operational.',
            ['Locate CO alarm.', 'Press test button.', 'Confirm audible response and secure mounting.'],
            ['Alarm is mounted securely and test response is audible.', 'Alarm location is visible and not blocked.'],
            ['No audible test', 'Missing alarm', 'Loose mount'],
            ['Photo of installed CO alarm.'],
            'NFPA 1194 / RVIA life-safety verification.'
          )),
          self::qms_005_item('fire_life_safety', 'fire_extinguisher_installed', 'Fire extinguisher installed and visible', 'Confirm fire extinguisher is installed in its intended location and visible.', 'critical', 'required', self::qms_005_info(
            'Confirm the extinguisher is present and available to the customer.',
            ['Locate extinguisher.', 'Check mounting and visibility.', 'Confirm no installed component blocks access.'],
            ['Extinguisher is installed, visible, and secure.', 'Access is not blocked.'],
            ['Missing extinguisher', 'Loose bracket', 'Hidden behind cargo'],
            ['Photo of installed extinguisher.'],
            'NFPA 1194 / RVIA life-safety verification.'
          )),
          self::qms_005_item('fire_life_safety', 'fire_extinguisher_location', 'Fire extinguisher location verified', 'Confirm extinguisher location matches the expected delivery configuration.', 'major', 'required', self::qms_005_info(
            'Verify the extinguisher can be found quickly and matches the expected build location.',
            ['Compare extinguisher location to the job configuration.', 'Check reach path and customer visibility.', 'Record any deviation in notes.'],
            ['Location is correct, reachable, and visible.', 'Deviation is resolved or supervisor-reviewed.'],
            ['Wrong location', 'Blocked access', 'Unclear customer visibility'],
            ['Photo showing extinguisher location in context.'],
            'NFPA 1194 / RVIA life-safety verification.'
          )),
        ],
      ],
      [
        'key'   => 'safety_labels',
        'label' => 'Safety Labels / Consumer Notices',
        'items' => [
          self::qms_005_item('safety_labels', 'warning_labels', 'Required warning labels installed', 'Confirm required warning labels are installed, readable, and correctly located.', 'major', 'required', self::qms_005_info(
            'Verify customer-facing safety warnings are present before delivery.',
            ['Inspect required label locations.', 'Confirm labels are readable and adhered.', 'Replace damaged or missing labels before pass.'],
            ['Required warning labels are present, readable, and secure.', 'Labels match the installed systems.'],
            ['Missing label', 'Damaged label', 'Wrong location'],
            ['Photo of installed warning labels.'],
            'RVIA labeling requirements and Slate delivery checklist.'
          )),
          self::qms_005_item('safety_labels', 'rvia_cert_vin_label', 'RVIA / certification / VIN label confirmed', 'Confirm certification and VIN label information is present and legible.', 'critical', 'required', self::qms_005_info(
            'Verify certification and unit identification labels can be inspected later.',
            ['Locate RVIA, certification, and VIN labels applicable to the unit.', 'Confirm labels are readable and match the job.', 'Record mismatch immediately.'],
            ['Labels are present, legible, and match the unit.', 'No label mismatch remains unresolved.'],
            ['Missing certification label', 'Unreadable VIN label', 'Mismatch to job record'],
            ['Photo of label area with legible identification.'],
            'RVIA / certification / VIN confirmation.'
          )),
          self::qms_005_item('safety_labels', 'owner_packet_notices', 'Owner packet / consumer notices included', 'Confirm owner packet and consumer notices are included for delivery.', 'major', 'required', self::qms_005_info(
            'Make sure customer handoff materials travel with the completed unit.',
            ['Locate owner packet and notices.', 'Confirm expected documents are included.', 'Place packet in the approved handoff location.'],
            ['Owner packet and consumer notices are included and placed correctly.', 'Missing documents are replaced before pass.'],
            ['Missing packet', 'Incomplete notices', 'Packet left outside handoff location'],
            ['Photo of packet or handoff location.'],
            'Slate delivery and consumer notice requirements.'
          )),
        ],
      ],
      [
        'key'   => 'final_supervisor_review',
        'label' => 'Final Supervisor Review',
        'items' => [
          self::qms_005_item('final_supervisor_review', 'supervisor_rvia_testing_review', 'Supervisor RVIA testing review', 'Supervisor confirms testing evidence, notes, and unresolved warnings before QMS-005 is submitted.', 'critical', 'optional', self::qms_005_info(
            'Give the supervisor one final checkpoint before the RVIA testing sign-off leaves the technician workflow.',
            ['Review all red, yellow, and green status rows.', 'Confirm required evidence is attached.', 'Add notes for any warning or override condition.'],
            ['No blocker remains unresolved.', 'Any warning has a clear note and supervisor direction.'],
            ['Missing evidence', 'Unclear warning note', 'Unresolved failed item'],
            ['Optional photo if a supervisor needs extra context.'],
            'Internal Slate Ops final review step.'
          )),
        ],
      ],
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

      // ── QMS-005 Rev. 1.2 — RVIA Testing Sign-Off ───────────────────
      // Structured RVIA compliance checklist for Slate's active RVIA scope.
      self::FORM_QMS_005 => [
        'code'        => self::FORM_QMS_005,
        'revision'    => '1.2',
        'name'        => 'RVIA Testing Sign-Off',
        'eyebrow'     => 'QMS-005 · RVIA',
        'kind'        => 'testing',
        'job_types'   => ['rvia'],
        'description' => 'Structured RVIA compliance testing with item-level evidence.',
        'depends_on'  => [self::FORM_QMS_004],
        'compliance'  => [
          'enabled'  => true,
          'statuses' => ['pass', 'warning', 'fail', 'not_tested'],
        ],
        'system_scope' => [
          'gravity_fill'       => true,
          'pressurized_pump'   => true,
          'sink'               => true,
          'grey_tank'          => true,
        ],
        'sections'    => self::qms_005_sections(),
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
    $include_test_data = !empty($args['include_test_data']) || (bool) apply_filters('slate_ops_quality_include_test_jobs', false);

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
      if (!$include_test_data && self::is_seed_or_test_job($desc)) continue;
      if ($status && $desc['rollup_status'] !== $status) continue;
      $out[] = $desc;
    }
    return $out;
  }

  /**
   * Identify seeded QA/demo records so they do not pollute live Quality queues.
   * This intentionally avoids matching generic names like "Test" by itself.
   */
  public static function is_seed_or_test_job(array $job) {
    $so = strtoupper(trim((string) ($job['so_number'] ?? '')));
    $vin = strtoupper(trim((string) ($job['vin'] ?? '')));
    $customer = strtoupper(trim((string) ($job['customer_name'] ?? '')));
    $dealer = strtoupper(trim((string) ($job['dealer_name'] ?? '')));
    $combined = $customer . ' ' . $dealer;

    $is_seed = 0 === strpos($so, 'TEST-SO')
      || 0 === strpos($vin, 'SCHEDTEST')
      || false !== strpos($combined, 'SCHEDULER TEST DATA')
      || false !== strpos($combined, 'RVIA QUALITY TEST')
      || false !== strpos($combined, 'SLATE INTERNAL TEST');

    return (bool) apply_filters('slate_ops_quality_is_seed_or_test_job', $is_seed, $job);
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
