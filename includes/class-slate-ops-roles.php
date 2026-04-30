<?php
if (!defined('ABSPATH')) exit;

/**
 * Centralized role and capability installer for Slate Ops.
 *
 * Called on plugin activation, on `init`, and on `admin_init` (self-healing).
 * Safe to run repeatedly — it only adds missing caps/roles, never removes
 * anything unrelated.
 */
class Slate_Ops_Roles {

  /**
   * Full capability set for each built-in Slate Ops role.
   * Administrators receive every cap in every role.
   */
  private static function role_definitions() {
    $C = 'Slate_Ops_Utils';
    return [

      // ── Viewer ───────────────────────────────────────────────────────────
      'slate_ops_viewer' => [
        'label' => 'Slate Ops Viewer',
        'caps'  => [
          $C::CAP_ACCESS       => true,
          $C::CAP_VIEWER       => true,
          $C::CAP_VIEW_MONITOR => true,
          'read'               => true,
        ],
      ],

      // ── Tech ─────────────────────────────────────────────────────────────
      'slate_ops_tech' => [
        'label' => 'Slate Ops Tech',
        'caps'  => [
          $C::CAP_ACCESS        => true,
          $C::CAP_TECH          => true,
          $C::CAP_UPDATE_STATUS => true,
          $C::CAP_TIME_TRACKING => true,
          $C::CAP_SUBMIT_QC     => true,
          'read'                => true,
        ],
      ],

      // ── Customer Service ─────────────────────────────────────────────────
      'slate_ops_cs' => [
        'label' => 'Slate Ops CS',
        'caps'  => [
          $C::CAP_ACCESS        => true,
          $C::CAP_CS            => true,
          $C::CAP_UPDATE_STATUS => true,
          $C::CAP_CREATE_JOBS   => true,
          $C::CAP_EDIT_JOBS     => true,
          $C::CAP_DELETE_JOBS   => true,
          $C::CAP_SCHEDULE_JOBS => true,
          'read'                => true,
        ],
      ],

      // ── Supervisor / QC ──────────────────────────────────────────────────
      'slate_ops_supervisor' => [
        'label' => 'Slate Ops Supervisor',
        'caps'  => [
          $C::CAP_ACCESS        => true,
          $C::CAP_SUPERVISOR    => true,
          $C::CAP_TECH          => true,
          $C::CAP_CS            => true,
          $C::CAP_UPDATE_STATUS => true,
          $C::CAP_CREATE_JOBS   => true,
          $C::CAP_EDIT_JOBS     => true,
          $C::CAP_DELETE_JOBS   => true,
          $C::CAP_SCHEDULE_JOBS => true,
          $C::CAP_ASSIGN_JOBS   => true,
          $C::CAP_TIME_TRACKING => true,
          $C::CAP_SUBMIT_QC     => true,
          $C::CAP_REVIEW_QC     => true,
          $C::CAP_VIEW_EXECUTIVE => true,
          $C::CAP_VIEW_MONITOR  => true,
          'read'                => true,
        ],
      ],

      // ── Ops Admin ────────────────────────────────────────────────────────
      'slate_ops_admin' => [
        'label' => 'Slate Ops Admin',
        'caps'  => [
          $C::CAP_ACCESS          => true,
          $C::CAP_ADMIN           => true,
          $C::CAP_SUPERVISOR      => true,
          $C::CAP_TECH            => true,
          $C::CAP_CS              => true,
          $C::CAP_UPDATE_STATUS   => true,
          $C::CAP_MANAGE_SETTINGS => true,
          $C::CAP_MANAGE_USERS    => true,
          $C::CAP_CREATE_JOBS     => true,
          $C::CAP_EDIT_JOBS       => true,
          $C::CAP_DELETE_JOBS     => true,
          $C::CAP_SCHEDULE_JOBS   => true,
          $C::CAP_ASSIGN_JOBS     => true,
          $C::CAP_TIME_TRACKING   => true,
          $C::CAP_SUBMIT_QC       => true,
          $C::CAP_REVIEW_QC       => true,
          $C::CAP_VIEW_EXECUTIVE  => true,
          $C::CAP_VIEW_MONITOR    => true,
          'read'                  => true,
        ],
      ],
    ];
  }

  /**
   * The complete set of Slate Ops capabilities (for administrator grant).
   * Uses the canonical list from Slate_Ops_Utils to avoid duplication.
   */
  private static function all_caps() {
    $caps = [];
    foreach (Slate_Ops_Utils::all_cap_names() as $cap) {
      $caps[$cap] = true;
    }
    return $caps;
  }

  /**
   * Version-gated installer for `init` hook.
   *
   * Skips the full repair on every request once the current plugin version
   * has already been installed. admin_init still calls install() directly
   * for self-healing without the version gate.
   */
  public static function maybe_install() {
    if (get_option('slate_ops_roles_version') === SLATE_OPS_VERSION) {
      return;
    }
    self::install();
    update_option('slate_ops_roles_version', SLATE_OPS_VERSION);
  }

  /**
   * Main installer. Safe to call repeatedly.
   *
   * Hooked on: plugin activation, `init`, `admin_init`.
   */
  public static function install() {
    // 1. Grant every ops cap to the administrator role.
    $admin_role = get_role('administrator');
    if ($admin_role) {
      foreach (self::all_caps() as $cap => $grant) {
        $admin_role->add_cap($cap, true);
      }
    }

    // 2. Ensure each new canonical role exists and has the correct caps.
    foreach (self::role_definitions() as $key => $def) {
      self::ensure_role($key, $def['label'], $def['caps']);
    }

    // 3. Migrate legacy roles: add new caps to old roles so existing users
    //    keep access after the capability rename.
    self::migrate_legacy_roles();
  }

  /**
   * Backward-compat shim: the old hook was register_roles_caps().
   */
  public static function register_roles_caps() {
    self::install();
  }

  // ── Internals ─────────────────────────────────────────────────────────────

  /**
   * Creates the role if absent; adds any missing caps if it already exists.
   * Never removes caps.
   */
  private static function ensure_role($key, $label, array $caps) {
    $role = get_role($key);
    if (!$role) {
      add_role($key, $label, $caps);
      return;
    }
    // Role exists — add any caps that are missing.
    foreach ($caps as $cap => $grant) {
      if ($grant && empty($role->capabilities[$cap])) {
        $role->add_cap($cap, true);
      }
    }
  }

  /**
   * Ensures legacy role keys (slate_tech, slate_shop_supervisor,
   * slate_customer_service) carry the new capability names so users
   * assigned to those old roles are not locked out.
   */
  private static function migrate_legacy_roles() {
    $C = 'Slate_Ops_Utils';

    $legacy = [
      'slate_tech' => [
        $C::CAP_ACCESS        => true,
        $C::CAP_TECH          => true,
        $C::CAP_UPDATE_STATUS => true,
        $C::CAP_TIME_TRACKING => true,
        $C::CAP_SUBMIT_QC     => true,
      ],
      'slate_shop_supervisor' => [
        $C::CAP_ACCESS          => true,
        $C::CAP_SUPERVISOR      => true,
        $C::CAP_TECH            => true,
        $C::CAP_CS              => true,
        $C::CAP_UPDATE_STATUS   => true,
        $C::CAP_CREATE_JOBS     => true,
        $C::CAP_EDIT_JOBS       => true,
        $C::CAP_DELETE_JOBS     => true,
        $C::CAP_SCHEDULE_JOBS   => true,
        $C::CAP_ASSIGN_JOBS     => true,
        $C::CAP_TIME_TRACKING   => true,
        $C::CAP_SUBMIT_QC       => true,
        $C::CAP_REVIEW_QC       => true,
        $C::CAP_VIEW_EXECUTIVE  => true,
        $C::CAP_VIEW_MONITOR    => true,
      ],
      'slate_customer_service' => [
        $C::CAP_ACCESS        => true,
        $C::CAP_CS            => true,   // replaces slate_ops_customer_service
        $C::CAP_UPDATE_STATUS => true,
        $C::CAP_CREATE_JOBS   => true,
        $C::CAP_EDIT_JOBS     => true,
        $C::CAP_DELETE_JOBS   => true,
        $C::CAP_SCHEDULE_JOBS => true,
      ],
    ];

    foreach ($legacy as $key => $caps) {
      $role = get_role($key);
      if (!$role) continue;
      foreach ($caps as $cap => $grant) {
        if ($grant && empty($role->capabilities[$cap])) {
          $role->add_cap($cap, true);
        }
      }
    }
  }
}
