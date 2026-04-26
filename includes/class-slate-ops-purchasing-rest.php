<?php
if (!defined('ABSPATH')) exit;

/**
 * Phase 1: read-only REST endpoints for the Purchasing workspace.
 * Write endpoints (create vendor, create request, update status) are Phase 2.
 */
class Slate_Ops_Purchasing_REST {

  // ── Permission ─────────────────────────────────────────────────────────────

  public static function check_access() {
    return is_user_logged_in() && (
      current_user_can(Slate_Ops_Utils::CAP_SUPERVISOR) ||
      current_user_can(Slate_Ops_Utils::CAP_ADMIN)
    );
  }

  // ── Route registration ─────────────────────────────────────────────────────

  public static function register_routes() {
    $ns   = 'slate-ops/v1';
    $perm = [__CLASS__, 'check_access'];

    register_rest_route($ns, '/purchasing/overview', [
      'methods'             => 'GET',
      'permission_callback' => $perm,
      'callback'            => [__CLASS__, 'h_overview'],
    ]);

    register_rest_route($ns, '/purchasing/vendors', [
      'methods'             => 'GET',
      'permission_callback' => $perm,
      'callback'            => [__CLASS__, 'h_list_vendors'],
    ]);

    register_rest_route($ns, '/purchasing/items', [
      'methods'             => 'GET',
      'permission_callback' => $perm,
      'callback'            => [__CLASS__, 'h_list_items'],
    ]);

    register_rest_route($ns, '/purchasing/requests', [
      'methods'             => 'GET',
      'permission_callback' => $perm,
      'callback'            => [__CLASS__, 'h_list_requests'],
    ]);

    register_rest_route($ns, '/purchasing/orders', [
      'methods'             => 'GET',
      'permission_callback' => $perm,
      'callback'            => [__CLASS__, 'h_list_orders'],
    ]);

    register_rest_route($ns, '/purchasing/orders/(?P<id>\d+)/lines', [
      'methods'             => 'GET',
      'permission_callback' => $perm,
      'callback'            => [__CLASS__, 'h_list_order_lines'],
    ]);
  }

  // ── Handlers ───────────────────────────────────────────────────────────────

  public static function h_overview($req) {
    return rest_ensure_response(Slate_Ops_Purchasing::get_overview());
  }

  public static function h_list_vendors($req) {
    return rest_ensure_response(Slate_Ops_Purchasing::list_vendors());
  }

  public static function h_list_items($req) {
    return rest_ensure_response(Slate_Ops_Purchasing::list_items());
  }

  public static function h_list_requests($req) {
    return rest_ensure_response(Slate_Ops_Purchasing::list_requests());
  }

  public static function h_list_orders($req) {
    return rest_ensure_response(Slate_Ops_Purchasing::list_orders());
  }

  public static function h_list_order_lines($req) {
    return rest_ensure_response(Slate_Ops_Purchasing::list_order_lines((int) $req['id']));
  }
}
