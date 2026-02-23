<?php
if (!defined('ABSPATH')) exit;

class Slate_Ops_Roles {

  public static function register_roles_caps() {
    // Add caps to administrator always.
    $admin = get_role('administrator');
    if ($admin) {
      foreach ([Slate_Ops_Utils::CAP_TECH, Slate_Ops_Utils::CAP_SUPERVISOR, Slate_Ops_Utils::CAP_CS, Slate_Ops_Utils::CAP_ADMIN] as $cap) {
        $admin->add_cap($cap);
      }
    }

    // Create custom roles if they do not exist.
    self::maybe_add_role('slate_tech', 'Slate Tech', [
      Slate_Ops_Utils::CAP_TECH => true,
      'read' => true,
    ]);

    self::maybe_add_role('slate_shop_supervisor', 'Slate Shop Supervisor', [
      Slate_Ops_Utils::CAP_TECH => true,
      Slate_Ops_Utils::CAP_SUPERVISOR => true,
      'read' => true,
    ]);

    self::maybe_add_role('slate_customer_service', 'Slate Customer Service', [
      Slate_Ops_Utils::CAP_CS => true,
      'read' => true,
    ]);

    self::maybe_add_role('slate_ops_admin', 'Slate Ops Admin', [
      Slate_Ops_Utils::CAP_TECH => true,
      Slate_Ops_Utils::CAP_SUPERVISOR => true,
      Slate_Ops_Utils::CAP_CS => true,
      Slate_Ops_Utils::CAP_ADMIN => true,
      'read' => true,
    ]);
  }

  private static function maybe_add_role($key, $label, $caps) {
    if (get_role($key)) return;
    add_role($key, $label, $caps);
  }
}
