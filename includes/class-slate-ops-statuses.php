<?php
if (!defined('ABSPATH')) exit;

/**
 * Canonical job status definitions for Slate Ops.
 *
 * Single source of truth for:
 *   - Status string constants
 *   - Human-readable labels
 *   - Legacy → canonical normalization
 *   - Badge CSS class mapping
 */
class Slate_Ops_Statuses {

    // ── Canonical constants ───────────────────────────────────────────

    const INTAKE           = 'INTAKE';
    const READY_FOR_BUILD  = 'READY_FOR_BUILD';
    const QUEUED           = 'QUEUED';
    const IN_PROGRESS      = 'IN_PROGRESS';
    const PENDING_QC       = 'PENDING_QC';
    const READY_FOR_PICKUP = 'READY_FOR_PICKUP';
    const COMPLETE         = 'COMPLETE';
    const DELAYED          = 'DELAYED';
    const ON_HOLD          = 'ON_HOLD';

    // ── Non-canonical transitional constants (not in all()) ───────────

    const READY_FOR_SUPERVISOR_REVIEW = 'READY_FOR_SUPERVISOR_REVIEW';
    const RETURNED_TO_CS              = 'RETURNED_TO_CS';

    // ── Canonical set ─────────────────────────────────────────────────

    public static function all(): array {
        static $all = null;
        if ($all === null) {
            $all = [
                self::INTAKE,
                self::READY_FOR_BUILD,
                self::QUEUED,
                self::IN_PROGRESS,
                self::PENDING_QC,
                self::READY_FOR_PICKUP,
                self::COMPLETE,
                self::DELAYED,
                self::ON_HOLD,
            ];
        }
        return $all;
    }

    // ── Labels ────────────────────────────────────────────────────────

    private static function label_map(): array {
        return [
            self::INTAKE           => 'Intake',
            self::READY_FOR_BUILD  => 'Ready for Build',
            self::QUEUED           => 'Queued',
            self::IN_PROGRESS      => 'In Progress',
            self::PENDING_QC       => 'Pending QC',
            self::READY_FOR_PICKUP => 'Ready for Pickup',
            self::COMPLETE         => 'Complete',
            self::DELAYED          => 'Delayed',
            self::ON_HOLD          => 'On Hold',
        ];
    }

    // ── Legacy normalization ──────────────────────────────────────────

    private static function legacy_map(): array {
        static $map = null;
        if ($map === null) {
            $map = [
                'PENDING_INTAKE'           => self::INTAKE,
                'NEEDS_SO'                 => self::INTAKE,
                'READY_TO_SCHEDULE'        => self::READY_FOR_BUILD,
                'READY_FOR_SCHEDULING'     => self::READY_FOR_BUILD,
                'APPROVED_FOR_SCHEDULING'  => self::READY_FOR_BUILD,
                'SCHEDULED'                => self::QUEUED,
                'COMPLETE_AWAITING_PICKUP' => self::READY_FOR_PICKUP,
                'COMPLETED'                => self::COMPLETE,
            ];
        }
        return $map;
    }

    // ── Badge CSS classes ─────────────────────────────────────────────

    private static function badge_map(): array {
        return [
            self::INTAKE           => 'ops-badge-status-intake',
            self::READY_FOR_BUILD  => 'ops-badge-status-ready-for-build',
            self::QUEUED           => 'ops-badge-status-queued',
            self::IN_PROGRESS      => 'ops-badge-status-inprogress',
            self::PENDING_QC       => 'ops-badge-status-pending-qc',
            self::READY_FOR_PICKUP => 'ops-badge-status-ready-for-pickup',
            self::COMPLETE         => 'ops-badge-status-complete',
            self::DELAYED          => 'ops-badge-status-delayed',
            self::ON_HOLD          => 'ops-badge-status-hold',
        ];
    }

    // ── Public API ────────────────────────────────────────────────────

    /**
     * Normalize a raw status value to its canonical form.
     *
     * Canonical values pass through unchanged.
     * Legacy values are mapped to their canonical equivalent.
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
        $canonical = self::normalize($status);
        $map = self::label_map();
        return $map[$canonical] ?? ucwords(strtolower(str_replace('_', ' ', $canonical)));
    }

    /**
     * Return the CSS badge class for a status (normalizes input first).
     */
    public static function badge_class(string $status): string {
        $canonical = self::normalize($status);
        $map = self::badge_map();
        return $map[$canonical] ?? 'ops-badge-status-intake';
    }

    /**
     * Return true if the value is already a canonical status string.
     */
    public static function is_canonical(string $status): bool {
        return in_array(strtoupper(trim($status)), self::all(), true);
    }
}
