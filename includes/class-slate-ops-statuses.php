<?php
if (!defined('ABSPATH')) exit;

/**
 * Canonical job status definitions for Slate Ops — Status System v2.0.
 *
 * Single source of truth for:
 *   - Status string constants
 *   - Human-readable labels
 *   - Legacy → canonical normalization
 *   - Badge CSS class mapping
 *   - CS-allowed status selection
 */
class Slate_Ops_Statuses {

    // ── v2 Canonical constants ────────────────────────────────────────

    const INTAKE           = 'INTAKE';
    const NEEDS_SO         = 'NEEDS_SO';
    const READY_FOR_BUILD  = 'READY_FOR_BUILD';
    const SCHEDULED        = 'SCHEDULED';
    const IN_PROGRESS      = 'IN_PROGRESS';
    const BLOCKED          = 'BLOCKED';
    const QC               = 'QC';
    const COMPLETE         = 'COMPLETE';
    const ON_HOLD          = 'ON_HOLD';
    const CANCELLED        = 'CANCELLED';

    // ── Legacy constants (kept for backward compat — normalize on read) ──

    const QUEUED           = 'QUEUED';           // → SCHEDULED
    const PENDING_QC       = 'PENDING_QC';       // → QC
    const READY_FOR_PICKUP = 'READY_FOR_PICKUP'; // → COMPLETE
    const DELAYED          = 'DELAYED';           // → BLOCKED

    // ── Non-canonical transitional constants ──────────────────────────

    const READY_FOR_SUPERVISOR_REVIEW = 'READY_FOR_SUPERVISOR_REVIEW';
    const RETURNED_TO_CS              = 'RETURNED_TO_CS';

    // ── v2 Canonical set ─────────────────────────────────────────────

    public static function all(): array {
        static $all = null;
        if ($all === null) {
            $all = [
                self::INTAKE,
                self::NEEDS_SO,
                self::READY_FOR_BUILD,
                self::SCHEDULED,
                self::IN_PROGRESS,
                self::BLOCKED,
                self::QC,
                self::COMPLETE,
                self::ON_HOLD,
                self::CANCELLED,
                // Legacy values remain recognized so existing DB rows are valid
                self::QUEUED,
                self::PENDING_QC,
                self::READY_FOR_PICKUP,
                self::DELAYED,
            ];
        }
        return $all;
    }

    /**
     * Statuses CS users are permitted to set manually.
     * IN_PROGRESS, QC, and COMPLETE must come from Tech/QC workflow.
     */
    public static function cs_settable(): array {
        return [
            self::INTAKE,
            self::NEEDS_SO,
            self::READY_FOR_BUILD,
            self::SCHEDULED,
            self::BLOCKED,
            self::ON_HOLD,
            self::CANCELLED,
        ];
    }

    /**
     * "All Active" filter set: excludes COMPLETE and CANCELLED.
     */
    public static function active(): array {
        return [
            self::INTAKE,
            self::NEEDS_SO,
            self::READY_FOR_BUILD,
            self::SCHEDULED,
            self::IN_PROGRESS,
            self::BLOCKED,
            self::QC,
            self::ON_HOLD,
            // Legacy equivalents included so they surface under "All Active"
            self::QUEUED,
            self::PENDING_QC,
            self::DELAYED,
        ];
    }

    /**
     * Capacity-counting statuses: jobs that consume bay/tech capacity.
     */
    public static function capacity_statuses(): array {
        return [
            self::SCHEDULED,
            self::IN_PROGRESS,
            self::BLOCKED,
            self::QUEUED,  // legacy alias
        ];
    }

    // ── Labels ────────────────────────────────────────────────────────

    private static function label_map(): array {
        return [
            self::INTAKE           => 'Intake',
            self::NEEDS_SO         => 'Needs SO',
            self::READY_FOR_BUILD  => 'Ready for Build',
            self::SCHEDULED        => 'Scheduled',
            self::IN_PROGRESS      => 'In Progress',
            self::BLOCKED          => 'Blocked',
            self::QC               => 'QC',
            self::COMPLETE         => 'Complete',
            self::ON_HOLD          => 'On Hold',
            self::CANCELLED        => 'Cancelled',
            // Legacy display labels
            self::QUEUED           => 'Scheduled',
            self::PENDING_QC       => 'QC',
            self::READY_FOR_PICKUP => 'Complete',
            self::DELAYED          => 'Blocked',
        ];
    }

    // ── Legacy normalization ──────────────────────────────────────────

    private static function legacy_map(): array {
        static $map = null;
        if ($map === null) {
            $map = [
                // v1 legacy → v2 canonical
                'QUEUED'                      => self::SCHEDULED,
                'SCHEDULED'                   => self::SCHEDULED,
                'PENDING_QC'                  => self::QC,
                'READY_FOR_PICKUP'            => self::COMPLETE,
                'DELAYED'                     => self::BLOCKED,
                // Older legacy values
                'PENDING_INTAKE'              => self::INTAKE,
                'NEEDS_SO'                    => self::NEEDS_SO,
                'RETURNED_TO_CS'              => self::NEEDS_SO,
                'READY_TO_SCHEDULE'           => self::READY_FOR_BUILD,
                'READY_FOR_SCHEDULING'        => self::READY_FOR_BUILD,
                'APPROVED_FOR_SCHEDULING'     => self::READY_FOR_BUILD,
                'READY_FOR_SUPERVISOR_REVIEW' => self::READY_FOR_BUILD,
                'COMPLETE_AWAITING_PICKUP'    => self::COMPLETE,
                'COMPLETED'                   => self::COMPLETE,
            ];
        }
        return $map;
    }

    // ── Badge CSS classes ─────────────────────────────────────────────

    private static function badge_map(): array {
        return [
            self::INTAKE           => 'ops-badge-status-intake',
            self::NEEDS_SO         => 'ops-badge-status-needs-so',
            self::READY_FOR_BUILD  => 'ops-badge-status-ready-for-build',
            self::SCHEDULED        => 'ops-badge-status-scheduled',
            self::IN_PROGRESS      => 'ops-badge-status-inprogress',
            self::BLOCKED          => 'ops-badge-status-blocked',
            self::QC               => 'ops-badge-status-qc',
            self::COMPLETE         => 'ops-badge-status-complete',
            self::ON_HOLD          => 'ops-badge-status-hold',
            self::CANCELLED        => 'ops-badge-status-cancelled',
            // Legacy → same class as v2 equivalent
            self::QUEUED           => 'ops-badge-status-scheduled',
            self::PENDING_QC       => 'ops-badge-status-qc',
            self::READY_FOR_PICKUP => 'ops-badge-status-complete',
            self::DELAYED          => 'ops-badge-status-blocked',
        ];
    }

    // ── Public API ────────────────────────────────────────────────────

    /**
     * Normalize a raw status value to its canonical v2 form.
     * Legacy values are mapped to their v2 equivalent.
     * Unrecognized values are returned uppercased as-is.
     */
    public static function normalize(string $raw): string {
        $upper = strtoupper(trim($raw));
        $legacy = self::legacy_map();
        return $legacy[$upper] ?? $upper;
    }

    /**
     * Return the display label for a status (normalizes input first).
     */
    public static function label(string $status): string {
        $upper = strtoupper(trim($status));
        $map = self::label_map();
        if (isset($map[$upper])) return $map[$upper];
        $canonical = self::normalize($status);
        return $map[$canonical] ?? ucwords(strtolower(str_replace('_', ' ', $canonical)));
    }

    /**
     * Return the CSS badge class for a status (normalizes input first).
     */
    public static function badge_class(string $status): string {
        $upper = strtoupper(trim($status));
        $map = self::badge_map();
        if (isset($map[$upper])) return $map[$upper];
        $canonical = self::normalize($status);
        return $map[$canonical] ?? 'ops-badge-status-intake';
    }

    /**
     * Return true if the value is a recognized status (canonical or legacy).
     */
    public static function is_canonical(string $status): bool {
        return in_array(strtoupper(trim($status)), self::all(), true);
    }

    // ── Transition enforcement ────────────────────────────────────────

    /**
     * Returns the set of statuses each status may transition TO.
     * ON_HOLD, BLOCKED, and CANCELLED are reachable from any active status.
     * Legacy statuses use their v2 equivalent's transition rules.
     */
    public static function allowed_transitions(): array {
        $any = self::all();
        return [
            self::INTAKE           => [self::NEEDS_SO, self::READY_FOR_BUILD, self::ON_HOLD, self::CANCELLED],
            self::NEEDS_SO         => [self::INTAKE, self::READY_FOR_BUILD, self::ON_HOLD, self::CANCELLED],
            self::READY_FOR_BUILD  => [self::SCHEDULED, self::IN_PROGRESS, self::ON_HOLD, self::CANCELLED, self::BLOCKED],
            self::SCHEDULED        => [self::IN_PROGRESS, self::READY_FOR_BUILD, self::ON_HOLD, self::CANCELLED, self::BLOCKED],
            self::IN_PROGRESS      => [self::QC, self::BLOCKED, self::ON_HOLD, self::SCHEDULED],
            self::BLOCKED          => $any,
            self::QC               => [self::IN_PROGRESS, self::COMPLETE],
            self::COMPLETE         => [],
            self::ON_HOLD          => $any,
            self::CANCELLED        => [],
            // Legacy aliases map to same rules as their v2 equivalents
            self::QUEUED           => [self::IN_PROGRESS, self::READY_FOR_BUILD, self::SCHEDULED, self::ON_HOLD, self::CANCELLED, self::BLOCKED],
            self::PENDING_QC       => [self::IN_PROGRESS, self::COMPLETE],
            self::READY_FOR_PICKUP => [self::COMPLETE],
            self::DELAYED          => $any,
        ];
    }

    /**
     * True if moving a job from $from to $to is a permitted transition.
     * Idempotent moves (same → same) always pass.
     * Any active status may enter ON_HOLD, BLOCKED, or CANCELLED.
     */
    public static function is_valid_transition(string $from, string $to): bool {
        if ($from === $to) return true;
        // Pause/terminal overrides: any status may enter these
        if (in_array($to, [self::ON_HOLD, self::BLOCKED, self::CANCELLED], true)) return true;
        $map = self::allowed_transitions();
        return in_array($to, $map[$from] ?? [], true);
    }
}
