<?php
/**
 * Slate Ops — CS / Supervisor Operations Dashboard data layer.
 *
 * Phase 1: stub data. Real ops data sources are not wired yet.
 * Each method returns the exact shape the page template + JS consume.
 *
 * Refresh point for downstream wiring:
 *   apply_filters( 'slate_ops_cs_refresh', $payload )
 * is fired in get_payload() so a single filter swap replaces all stubs.
 */
if (!defined('ABSPATH')) exit;

class Slate_Ops_CS {

  public static function get_kpis() {
    return [
      'active'     => 23,
      'parts'      => 6,
      'ready'      => 5,
      'inprogress' => 4,
      'qc'         => 1,
      'pickup'     => 2,
      'blocked'    => 3,
      'updates'    => 4,
    ];
  }

  public static function get_priorities() {
    return [
      [
        'id'     => 'SO-1042',
        'cust'   => 'MB Wilsonville',
        'status' => 'Waiting on Parts',
        'pill'   => 'parts',
        'owner'  => 'CS',
        'action' => 'Confirm ETA',
        'detail' => 'Electrical kit ETA pushed by vendor. Confirm new date and notify dealer.',
      ],
      [
        'id'     => 'SO-1045',
        'cust'   => 'MB Seattle',
        'status' => 'Pending QC',
        'pill'   => 'qc',
        'owner'  => 'Supervisor',
        'action' => 'Final sign-off',
        'detail' => 'Build complete. Awaiting supervisor walk-through and QC checklist sign-off.',
      ],
      [
        'id'     => 'SO-1051',
        'cust'   => 'MB Anchorage',
        'status' => 'Complete - Awaiting Pickup',
        'pill'   => 'pickup',
        'owner'  => 'CS',
        'action' => 'Notify dealer',
        'detail' => 'Cleared QC. Send pickup notification and prep delivery paperwork.',
      ],
      [
        'id'     => 'SO-1053',
        'cust'   => 'MB Wilsonville',
        'status' => 'Blocked',
        'pill'   => 'blocked',
        'owner'  => 'Supervisor',
        'action' => 'Review blocker',
        'detail' => 'Tech flagged scope mismatch on cabinet hardware. Needs supervisor review before continuing.',
      ],
    ];
  }

  public static function get_health() {
    return [
      ['label' => 'Parts Risk',         'value' => '6 jobs affected',    'pct' => 60, 'tone' => 'warn'],
      ['label' => 'QC Load',            'value' => '1 pending sign-off', 'pct' => 14, 'tone' => ''],
      ['label' => 'Pickup Queue',       'value' => '2 ready',            'pct' => 28, 'tone' => 'good'],
      ['label' => 'Schedule Readiness', 'value' => '5 jobs cleared',     'pct' => 71, 'tone' => 'good'],
      ['label' => 'Update Discipline',  'value' => '4 jobs need notes',  'pct' => 42, 'tone' => 'alert'],
    ];
  }

  public static function get_parts() {
    return [
      ['name' => 'Electrical components', 'sub' => '3 vendors · longest ETA 9 days', 'count' => 3, 'tone' => 'alert'],
      ['name' => 'Flooring kit',          'sub' => '1 vendor · ETA 4 days',          'count' => 2, 'tone' => 'alert'],
      ['name' => 'Cabinet hardware',      'sub' => 'Inbound · ETA tomorrow',         'count' => 1, 'tone' => ''],
    ];
  }

  public static function get_qc() {
    return [
      ['name' => 'Pending QC',       'sub' => 'Awaiting supervisor walk', 'count' => 1, 'tone' => 'alert'],
      ['name' => 'Failed QC',        'sub' => 'No failures today',        'count' => 0, 'tone' => 'zero'],
      ['name' => 'Rework Required',  'sub' => 'Clean queue',              'count' => 0, 'tone' => 'zero'],
      ['name' => 'Ready to Release', 'sub' => 'Cleared for pickup prep',  'count' => 2, 'tone' => 'good'],
    ];
  }

  public static function get_pickup() {
    return [
      ['name' => 'Complete - Awaiting Pickup', 'sub' => 'Customer can be contacted', 'count' => 2, 'tone' => 'good'],
      ['name' => 'Customer notified',         'sub' => 'Awaiting acknowledgement',  'count' => 1, 'tone' => ''],
      ['name' => 'Waiting customer response', 'sub' => '> 24 hours',                'count' => 1, 'tone' => 'alert'],
      ['name' => 'Delivery paperwork open',   'sub' => 'Needs CS to finalize',      'count' => 2, 'tone' => 'alert'],
    ];
  }

  public static function get_subtab_counts() {
    return [
      'intake'     => 7,
      'parts'      => 6,
      'qc'         => 1,
      'pickup'     => 2,
      'exceptions' => 3,
    ];
  }

  /**
   * Single payload for both the server-rendered template and the
   * #cs-dashboard-data JSON blob the JS reads on init.
   *
   * Wiring point for real data: hook 'slate_ops_cs_refresh' and replace
   * the stub arrays.
   */
  public static function get_payload() {
    $payload = [
      'kpis'          => self::get_kpis(),
      'priorities'    => self::get_priorities(),
      'health'        => self::get_health(),
      'parts'         => self::get_parts(),
      'qc'            => self::get_qc(),
      'pickup'        => self::get_pickup(),
      'subtab_counts' => self::get_subtab_counts(),
    ];
    return apply_filters('slate_ops_cs_refresh', $payload);
  }
}
