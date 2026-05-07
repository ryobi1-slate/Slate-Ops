<?php
/**
 * Standalone tests for Slate_Ops_Statuses.
 *
 * Run: php tests/test-statuses.php
 * No WordPress required.
 */

define('ABSPATH', __DIR__ . '/../');
require_once __DIR__ . '/../includes/class-slate-ops-statuses.php';

// ── Helpers ──────────────────────────────────────────────────────────

$pass = 0;
$fail = 0;

function ok(bool $result, string $label): void {
    global $pass, $fail;
    if ($result) {
        echo "  PASS  $label\n";
        $pass++;
    } else {
        echo "  FAIL  $label\n";
        $fail++;
    }
}

function eq(mixed $actual, mixed $expected, string $label): void {
    $result = $actual === $expected;
    global $pass, $fail;
    if ($result) {
        echo "  PASS  $label\n";
        $pass++;
    } else {
        echo "  FAIL  $label  (got: " . json_encode($actual) . "  want: " . json_encode($expected) . ")\n";
        $fail++;
    }
}

// ── Constants ────────────────────────────────────────────────────────

echo "\nConstants — canonical\n";
eq(Slate_Ops_Statuses::INTAKE,           'INTAKE',           'INTAKE');
eq(Slate_Ops_Statuses::READY_FOR_BUILD,  'READY_FOR_BUILD',  'READY_FOR_BUILD');
eq(Slate_Ops_Statuses::QUEUED,           'QUEUED',           'QUEUED');
eq(Slate_Ops_Statuses::IN_PROGRESS,      'IN_PROGRESS',      'IN_PROGRESS');
eq(Slate_Ops_Statuses::PENDING_QC,       'PENDING_QC',       'PENDING_QC');
eq(Slate_Ops_Statuses::READY_FOR_PICKUP, 'READY_FOR_PICKUP', 'READY_FOR_PICKUP');
eq(Slate_Ops_Statuses::COMPLETE,         'COMPLETE',         'COMPLETE');
eq(Slate_Ops_Statuses::DELAYED,          'DELAYED',          'DELAYED');
eq(Slate_Ops_Statuses::ON_HOLD,          'ON_HOLD',          'ON_HOLD');

echo "\nConstants — non-canonical transitional\n";
eq(Slate_Ops_Statuses::READY_FOR_SUPERVISOR_REVIEW, 'READY_FOR_SUPERVISOR_REVIEW', 'READY_FOR_SUPERVISOR_REVIEW');
eq(Slate_Ops_Statuses::RETURNED_TO_CS,              'RETURNED_TO_CS',              'RETURNED_TO_CS');
ok(!Slate_Ops_Statuses::is_canonical('READY_FOR_SUPERVISOR_REVIEW'), 'READY_FOR_SUPERVISOR_REVIEW not in canonical set');
ok(!Slate_Ops_Statuses::is_canonical('RETURNED_TO_CS'),              'RETURNED_TO_CS not in canonical set');

// ── all() ────────────────────────────────────────────────────────────

echo "\nall()\n";
$all = Slate_Ops_Statuses::all();
ok(count($all) === 9, 'returns 9 statuses');
ok(in_array('INTAKE', $all, true), 'INTAKE in all()');
ok(in_array('READY_FOR_BUILD', $all, true), 'READY_FOR_BUILD in all()');
ok(in_array('QUEUED', $all, true), 'QUEUED in all()');
ok(in_array('READY_FOR_PICKUP', $all, true), 'READY_FOR_PICKUP in all()');

// ── normalize() — canonical pass-through ─────────────────────────────

echo "\nnormalize() — canonical pass-through\n";
eq(Slate_Ops_Statuses::normalize('INTAKE'),           'INTAKE',           'INTAKE passes through');
eq(Slate_Ops_Statuses::normalize('READY_FOR_BUILD'),  'READY_FOR_BUILD',  'READY_FOR_BUILD passes through');
eq(Slate_Ops_Statuses::normalize('QUEUED'),           'QUEUED',           'QUEUED passes through');
eq(Slate_Ops_Statuses::normalize('IN_PROGRESS'),      'IN_PROGRESS',      'IN_PROGRESS passes through');
eq(Slate_Ops_Statuses::normalize('PENDING_QC'),       'PENDING_QC',       'PENDING_QC passes through');
eq(Slate_Ops_Statuses::normalize('READY_FOR_PICKUP'), 'READY_FOR_PICKUP', 'READY_FOR_PICKUP passes through');
eq(Slate_Ops_Statuses::normalize('COMPLETE'),         'COMPLETE',         'COMPLETE passes through');
eq(Slate_Ops_Statuses::normalize('DELAYED'),          'DELAYED',          'DELAYED passes through');
eq(Slate_Ops_Statuses::normalize('ON_HOLD'),          'ON_HOLD',          'ON_HOLD passes through');

// ── normalize() — legacy mapping ─────────────────────────────────────

echo "\nnormalize() — legacy mapping\n";
eq(Slate_Ops_Statuses::normalize('PENDING_INTAKE'),           'INTAKE',           'PENDING_INTAKE → INTAKE');
eq(Slate_Ops_Statuses::normalize('NEEDS_SO'),                 'INTAKE',           'NEEDS_SO → INTAKE');
eq(Slate_Ops_Statuses::normalize('READY_TO_SCHEDULE'),        'READY_FOR_BUILD',  'READY_TO_SCHEDULE → READY_FOR_BUILD');
eq(Slate_Ops_Statuses::normalize('READY_FOR_SCHEDULING'),     'READY_FOR_BUILD',  'READY_FOR_SCHEDULING → READY_FOR_BUILD');
eq(Slate_Ops_Statuses::normalize('APPROVED_FOR_SCHEDULING'),  'READY_FOR_BUILD',  'APPROVED_FOR_SCHEDULING → READY_FOR_BUILD');
eq(Slate_Ops_Statuses::normalize('SCHEDULED'),                'QUEUED',           'SCHEDULED → QUEUED');
eq(Slate_Ops_Statuses::normalize('COMPLETE_AWAITING_PICKUP'), 'READY_FOR_PICKUP', 'COMPLETE_AWAITING_PICKUP → READY_FOR_PICKUP');
eq(Slate_Ops_Statuses::normalize('COMPLETED'),                'COMPLETE',         'COMPLETED → COMPLETE');

// ── normalize() — case insensitivity & trimming ───────────────────────

echo "\nnormalize() — case and whitespace\n";
eq(Slate_Ops_Statuses::normalize('pending_intake'),   'INTAKE',        'lowercase legacy maps correctly');
eq(Slate_Ops_Statuses::normalize('  SCHEDULED  '),    'QUEUED',        'trims whitespace before mapping');
eq(Slate_Ops_Statuses::normalize('in_progress'),      'IN_PROGRESS',   'lowercase canonical passes through uppercased');

// ── normalize() — unknown values ─────────────────────────────────────

echo "\nnormalize() — unknown values\n";
eq(Slate_Ops_Statuses::normalize('SOME_FUTURE_STATUS'), 'SOME_FUTURE_STATUS', 'unknown canonical returned uppercased');
eq(Slate_Ops_Statuses::normalize('bogus'),              'BOGUS',              'unknown lowercase returned uppercased');

// ── label() ──────────────────────────────────────────────────────────

echo "\nlabel()\n";
eq(Slate_Ops_Statuses::label('INTAKE'),                    'Intake',           'INTAKE label');
eq(Slate_Ops_Statuses::label('READY_FOR_BUILD'),           'Ready for Build',  'READY_FOR_BUILD label');
eq(Slate_Ops_Statuses::label('QUEUED'),                    'Queued',           'QUEUED label');
eq(Slate_Ops_Statuses::label('IN_PROGRESS'),               'In Progress',      'IN_PROGRESS label');
eq(Slate_Ops_Statuses::label('PENDING_QC'),                'Ready for Closeout', 'PENDING_QC label');
eq(Slate_Ops_Statuses::label('READY_FOR_PICKUP'),          'Closed',           'READY_FOR_PICKUP label');
eq(Slate_Ops_Statuses::label('COMPLETE'),                  'Closed',           'COMPLETE label');
eq(Slate_Ops_Statuses::label('DELAYED'),                   'Delayed',          'DELAYED label');
eq(Slate_Ops_Statuses::label('ON_HOLD'),                   'On Hold',          'ON_HOLD label');
eq(Slate_Ops_Statuses::label('PENDING_INTAKE'),            'Intake',           'legacy PENDING_INTAKE → label "Intake"');
eq(Slate_Ops_Statuses::label('APPROVED_FOR_SCHEDULING'),   'Ready for Build',  'legacy APPROVED_FOR_SCHEDULING → label');
eq(Slate_Ops_Statuses::label('SCHEDULED'),                 'Queued',           'legacy SCHEDULED → label "Queued"');

// ── badge_class() ─────────────────────────────────────────────────────

echo "\nbadge_class()\n";
eq(Slate_Ops_Statuses::badge_class('INTAKE'),                    'ops-badge-status-intake',           'INTAKE badge');
eq(Slate_Ops_Statuses::badge_class('READY_FOR_BUILD'),           'ops-badge-status-ready-for-build',  'READY_FOR_BUILD badge');
eq(Slate_Ops_Statuses::badge_class('QUEUED'),                    'ops-badge-status-queued',           'QUEUED badge');
eq(Slate_Ops_Statuses::badge_class('IN_PROGRESS'),               'ops-badge-status-inprogress',       'IN_PROGRESS badge');
eq(Slate_Ops_Statuses::badge_class('PENDING_QC'),                'ops-badge-status-pending-qc',       'PENDING_QC badge');
eq(Slate_Ops_Statuses::badge_class('READY_FOR_PICKUP'),          'ops-badge-status-ready-for-pickup', 'READY_FOR_PICKUP badge');
eq(Slate_Ops_Statuses::badge_class('COMPLETE'),                  'ops-badge-status-complete',         'COMPLETE badge');
eq(Slate_Ops_Statuses::badge_class('DELAYED'),                   'ops-badge-status-delayed',          'DELAYED badge');
eq(Slate_Ops_Statuses::badge_class('ON_HOLD'),                   'ops-badge-status-hold',             'ON_HOLD badge');
eq(Slate_Ops_Statuses::badge_class('PENDING_INTAKE'),            'ops-badge-status-intake',           'legacy PENDING_INTAKE badge');
eq(Slate_Ops_Statuses::badge_class('APPROVED_FOR_SCHEDULING'),   'ops-badge-status-ready-for-build',  'legacy APPROVED_FOR_SCHEDULING badge');
eq(Slate_Ops_Statuses::badge_class('SCHEDULED'),                 'ops-badge-status-queued',           'legacy SCHEDULED badge');

// ── is_canonical() ────────────────────────────────────────────────────

echo "\nis_canonical()\n";
ok(Slate_Ops_Statuses::is_canonical('INTAKE'),              'INTAKE is canonical');
ok(Slate_Ops_Statuses::is_canonical('READY_FOR_BUILD'),     'READY_FOR_BUILD is canonical');
ok(Slate_Ops_Statuses::is_canonical('QUEUED'),              'QUEUED is canonical');
ok(Slate_Ops_Statuses::is_canonical('COMPLETE'),            'COMPLETE is canonical');
ok(!Slate_Ops_Statuses::is_canonical('PENDING_INTAKE'),     'PENDING_INTAKE is NOT canonical');
ok(!Slate_Ops_Statuses::is_canonical('APPROVED_FOR_SCHEDULING'), 'APPROVED_FOR_SCHEDULING is NOT canonical');
ok(!Slate_Ops_Statuses::is_canonical('SCHEDULED'),          'SCHEDULED is NOT canonical');
ok(!Slate_Ops_Statuses::is_canonical('BOGUS'),              'BOGUS is NOT canonical');

// ── Summary ───────────────────────────────────────────────────────────

echo "\n";
$total = $pass + $fail;
echo "$pass/$total passed";
if ($fail > 0) {
    echo " — $fail FAILED\n";
    exit(1);
}
echo "\n";
exit(0);
