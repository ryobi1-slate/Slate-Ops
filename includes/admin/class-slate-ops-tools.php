<?php
/**
 * Slate Ops Tools — reusable tabbed admin page + tab registry.
 *
 * One admin page (under Tools) that hosts self-registering tool tabs. Adding a
 * future tool is "register a tab + provide render/save callbacks" via the
 * `slate_ops_tools_tabs` filter — zero edits to this page.
 *
 * Routing is server-side via `?tab=`. Each tab renders on page load; there is no
 * JS show/hide of hidden panels. The active tab defaults to the first tab the
 * current user can access.
 *
 * Capability model — a tab's declared capability gates ALL THREE of:
 *   1. its POST/save handler (dispatched from load-{hook}),
 *   2. the `?tab=` route + render callback,
 *   3. its visibility in the tab strip.
 * Requesting a `?tab=` the user lacks the cap for is denied, not silently
 * redirected to a tab they can see.
 *
 * Tab descriptor contract (array pushed onto the `slate_ops_tools_tabs` filter):
 *   [
 *     'id'         => 'finance-invoice',           // slug used in ?tab=
 *     'label'      => 'Invoice Generator',
 *     'capability' => Slate_Ops_Utils::CAP_CS,     // gates route + save + strip
 *     'render'     => callable,                     // echoes the tab body
 *     'save'       => callable,                     // owns its POST handler (optional)
 *   ]
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Slate_Ops_Tools {

	const MENU_SLUG = 'slate-ops-tools';

	/** Page-level gate. Menu visibility; per-tab caps gate individual tools. */
	const PAGE_CAP = Slate_Ops_Utils::CAP_CS;

	/** Admin page hook suffix, captured at registration (for enqueue + load). */
	private static $hook = '';

	// ── Menu registration ──────────────────────────────────────────────────────

	public static function register_menu() {
		self::$hook = add_management_page(
			'Slate Ops Tools',
			'Slate Ops Tools',
			self::PAGE_CAP,
			self::MENU_SLUG,
			[ __CLASS__, 'render' ]
		);

		if ( self::$hook ) {
			// Runs before any output on our page — safe for POST handling + redirects.
			add_action( 'load-' . self::$hook, [ __CLASS__, 'handle_load' ] );
			add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
		}
	}

	// ── Tab registry ───────────────────────────────────────────────────────────

	/**
	 * Read the tab registry, keyed by id, order preserved. Malformed entries
	 * (missing id/label/render) are skipped.
	 */
	private static function get_tabs() {
		$raw   = apply_filters( 'slate_ops_tools_tabs', [] );
		$tabs  = [];

		if ( ! is_array( $raw ) ) {
			return $tabs;
		}

		foreach ( $raw as $tab ) {
			if ( empty( $tab['id'] ) || empty( $tab['label'] ) || empty( $tab['render'] ) || ! is_callable( $tab['render'] ) ) {
				continue;
			}
			$id = sanitize_key( $tab['id'] );
			if ( '' === $id || isset( $tabs[ $id ] ) ) {
				continue;
			}
			$tabs[ $id ] = [
				'id'         => $id,
				'label'      => (string) $tab['label'],
				'capability' => ! empty( $tab['capability'] ) ? (string) $tab['capability'] : self::PAGE_CAP,
				'render'     => $tab['render'],
				'save'       => isset( $tab['save'] ) && is_callable( $tab['save'] ) ? $tab['save'] : null,
			];
		}

		return $tabs;
	}

	/**
	 * Resolve the active tab.
	 *
	 * An explicit, existing `?tab=` is returned as-is (its capability is enforced
	 * by the caller, so an unauthorized explicit request is denied rather than
	 * silently swapped). With no/invalid `?tab=`, the default is the first tab the
	 * current user can access.
	 */
	private static function get_active_tab( array $tabs ) {
		if ( empty( $tabs ) ) {
			return null;
		}

		$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		if ( '' !== $requested && isset( $tabs[ $requested ] ) ) {
			return $tabs[ $requested ];
		}

		foreach ( $tabs as $tab ) {
			if ( current_user_can( $tab['capability'] ) ) {
				return $tab;
			}
		}

		return null;
	}

	// ── Load handler: gate + dispatch save ──────────────────────────────────────

	public static function handle_load() {
		$tabs   = self::get_tabs();
		$active = self::get_active_tab( $tabs );

		if ( ! $active ) {
			return;
		}

		// Capability governs the route + POST, not just strip visibility.
		if ( ! current_user_can( $active['capability'] ) ) {
			wp_die(
				esc_html__( 'You do not have permission to access this tool.', 'slate-ops' ),
				esc_html__( 'Access denied', 'slate-ops' ),
				[ 'response' => 403 ]
			);
		}

		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) && $active['save'] ) {
			call_user_func( $active['save'] );
		}
	}

	// ── Assets ─────────────────────────────────────────────────────────────────

	public static function enqueue( $hook_suffix ) {
		if ( $hook_suffix !== self::$hook ) {
			return;
		}

		wp_enqueue_style(
			'slate-ops-tools',
			SLATE_OPS_URL . 'assets/css/ops-tools.css',
			[],
			SLATE_OPS_VERSION
		);

		// Let the active tab enqueue its own assets (drop-in; no edits here needed).
		$tabs   = self::get_tabs();
		$active = self::get_active_tab( $tabs );
		if ( $active && current_user_can( $active['capability'] ) ) {
			do_action( 'slate_ops_tools_enqueue_assets', $active['id'] );
		}
	}

	// ── Render ─────────────────────────────────────────────────────────────────

	public static function render() {
		if ( ! current_user_can( self::PAGE_CAP ) ) {
			wp_die( esc_html__( 'Access denied.', 'slate-ops' ) );
		}

		$tabs   = self::get_tabs();
		$active = self::get_active_tab( $tabs );

		echo '<div class="wrap slate-ops-tools">';
		echo '<h1>' . esc_html__( 'Slate Ops Tools', 'slate-ops' ) . '</h1>';

		if ( empty( $tabs ) || ! $active ) {
			echo '<p>' . esc_html__( 'No tools are available.', 'slate-ops' ) . '</p>';
			echo '</div>';
			return;
		}

		self::render_tab_strip( $tabs, $active['id'] );

		// Defense-in-depth: gate the render callback on the tab capability.
		if ( ! current_user_can( $active['capability'] ) ) {
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'You do not have permission to access this tool.', 'slate-ops' )
				. '</p></div>';
			echo '</div>';
			return;
		}

		echo '<div class="slate-ops-tools__panel">';
		call_user_func( $active['render'] );
		echo '</div>';

		echo '</div>';
	}

	/** Render the WP nav-tab strip, showing only tabs the user can access. */
	private static function render_tab_strip( array $tabs, $active_id ) {
		echo '<nav class="nav-tab-wrapper slate-ops-tools__tabs">';
		foreach ( $tabs as $tab ) {
			if ( ! current_user_can( $tab['capability'] ) ) {
				continue;
			}
			$url   = add_query_arg(
				[
					'page' => self::MENU_SLUG,
					'tab'  => $tab['id'],
				],
				admin_url( 'tools.php' )
			);
			$class = 'nav-tab' . ( $tab['id'] === $active_id ? ' nav-tab-active' : '' );
			printf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( $url ),
				esc_attr( $class ),
				esc_html( $tab['label'] )
			);
		}
		echo '</nav>';
	}
}
