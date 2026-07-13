<?php
/**
 * Finance Invoice — data layer + CPT registration.
 *
 * Standalone "dummy" combined invoice (chassis + build on one document) for
 * RV financing. All fields are manual entry — nothing is pulled from Business
 * Central, the Dealer Portal, or any order/quote data. Replaces a hand-kept
 * spreadsheet. Slate staff only (Slate_Ops_Utils::CAP_CS).
 *
 * Storage: the `slate_finance_invoice` CPT (non-public, no native editor UI —
 * all create/edit happens through the Tools page invoice tab) plus post meta.
 * Totals are NEVER stored; they are summed at render time only.
 *
 * This class owns: CPT registration, field definitions, sanitize + save,
 * read/assemble, listing, and total computation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Slate_Ops_Finance_Invoice {

	/** Custom post type slug. */
	const CPT = 'slate_finance_invoice';

	/** Staff capability gate — reuses the existing CS/office-staff cap. */
	const CAP = Slate_Ops_Utils::CAP_CS;

	/** Meta key prefix (leading underscore hides from the generic custom-fields UI). */
	const META_PREFIX = '_slate_fi_';

	/** Meta key holding the serialized line-item array. */
	const META_LINE_ITEMS = '_slate_fi_line_items';

	// ── CPT registration ──────────────────────────────────────────────────────

	/**
	 * Register the CPT. Hooked to `init` from the plugin bootstrap.
	 *
	 * Non-public and show_ui => false: this record is driven entirely by the
	 * Tools page invoice tab, never the native post editor. Access is enforced
	 * manually (capability + nonce) at every read/write in the tab, so we do not
	 * grant post-type-specific caps to roles.
	 */
	public static function register() {
		register_post_type(
			self::CPT,
			[
				'label'               => 'Finance Invoices',
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'hierarchical'        => false,
				'supports'            => [ 'title' ],
				'map_meta_cap'        => true,
			]
		);
	}

	// ── Field definitions ──────────────────────────────────────────────────────

	/**
	 * Scalar meta fields => sanitize kind ('text' | 'textarea').
	 * Order here is only used by the data layer; the form controls its own layout.
	 * Numbers on scalar fields do not appear here — the only numeric data lives in
	 * line items, which are sanitized separately.
	 */
	public static function scalar_fields() {
		return [
			// Header / vehicle
			'invoice_date'    => 'text',
			'invoice_no'      => 'text', // MANUAL — mirrors a BC PS-INV number, not auto-sequenced.
			'veh_make'        => 'text',
			'veh_model'       => 'text',
			'rvia_no'         => 'text',
			'vin'             => 'text',
			// Ship-to
			'ship_to_name'    => 'text',
			'ship_to_address' => 'textarea',
			// Meta row
			'salesperson'     => 'text',
			'model_year'      => 'text',
			'meta_make'       => 'text',
			'meta_model'      => 'text',
			'wheelbase'       => 'text',
			'stock_no'        => 'text',
		];
	}

	// ── Sanitize ────────────────────────────────────────────────────────────────

	/**
	 * Sanitize a scalar field value by its declared kind.
	 * Assumes $value is already unslashed.
	 */
	private static function sanitize_scalar( $value, $kind ) {
		if ( 'textarea' === $kind ) {
			return sanitize_textarea_field( (string) $value );
		}
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Sanitize a numeric line-item cell.
	 *
	 * Blank stays blank (feature sub-lines legitimately have no number, and must
	 * render $0.00 later — not be coerced to a stored 0). A supplied value is
	 * normalized through floatval and returned as a string.
	 */
	private static function sanitize_number( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		return (string) floatval( $value );
	}

	/**
	 * Sanitize the repeatable line-item rows.
	 *
	 * Input: an array of rows (already unslashed), each row an associative array
	 * with any of qty/description/msrp/discount/invoice. Order is preserved and
	 * reindexed 0..n. Fully-empty rows are dropped; rows with only a description
	 * (feature sub-lines) are kept. Nothing is computed or derived.
	 */
	public static function sanitize_line_items( $rows ) {
		$clean = [];
		if ( ! is_array( $rows ) ) {
			return $clean;
		}

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$item = [
				'qty'         => sanitize_text_field( (string) ( $row['qty'] ?? '' ) ),
				'description' => sanitize_text_field( (string) ( $row['description'] ?? '' ) ),
				'msrp'        => self::sanitize_number( $row['msrp'] ?? '' ),
				'discount'    => self::sanitize_number( $row['discount'] ?? '' ),
				'invoice'     => self::sanitize_number( $row['invoice'] ?? '' ),
			];

			// Drop a row only when every cell is empty.
			$has_content = '' !== $item['qty']
				|| '' !== $item['description']
				|| '' !== $item['msrp']
				|| '' !== $item['discount']
				|| '' !== $item['invoice'];

			if ( $has_content ) {
				$clean[] = $item;
			}
		}

		return array_values( $clean );
	}

	// ── Save ────────────────────────────────────────────────────────────────────

	/**
	 * Create a new invoice from a raw (unslashed) input array.
	 * Returns the new post ID or a WP_Error.
	 */
	public static function create( array $raw ) {
		$post_id = wp_insert_post(
			[
				'post_type'   => self::CPT,
				'post_status' => 'publish',
				'post_title'  => self::title_from_raw( $raw ),
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		self::store_meta( $post_id, $raw );
		return $post_id;
	}

	/**
	 * Update an existing invoice from a raw (unslashed) input array.
	 * Returns the post ID or a WP_Error.
	 */
	public static function update( $post_id, array $raw ) {
		$post_id = absint( $post_id );
		$post    = get_post( $post_id );

		if ( ! $post || self::CPT !== $post->post_type ) {
			return new WP_Error( 'not_found', 'Invoice not found.' );
		}

		$result = wp_update_post(
			[
				'ID'         => $post_id,
				'post_title' => self::title_from_raw( $raw ),
			],
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		self::store_meta( $post_id, $raw );
		return $post_id;
	}

	/**
	 * Persist all meta (scalar fields + line items) from a raw input array.
	 */
	private static function store_meta( $post_id, array $raw ) {
		foreach ( self::scalar_fields() as $field => $kind ) {
			$value = self::sanitize_scalar( $raw[ $field ] ?? '', $kind );
			update_post_meta( $post_id, self::META_PREFIX . $field, $value );
		}

		$line_items = self::sanitize_line_items( $raw['line_items'] ?? [] );
		update_post_meta( $post_id, self::META_LINE_ITEMS, $line_items );
	}

	/** Derive a human post_title for listing. Falls back when invoice_no is blank. */
	private static function title_from_raw( array $raw ) {
		$invoice_no = sanitize_text_field( (string) ( $raw['invoice_no'] ?? '' ) );
		if ( '' !== $invoice_no ) {
			return $invoice_no;
		}
		$vin = sanitize_text_field( (string) ( $raw['vin'] ?? '' ) );
		if ( '' !== $vin ) {
			return 'VIN ' . $vin;
		}
		return 'Untitled Invoice';
	}

	// ── Read ──────────────────────────────────────────────────────────────────

	/**
	 * Assemble a single invoice into a flat array: every scalar field plus
	 * 'line_items'. Returns null if the ID is not an invoice.
	 */
	public static function get( $post_id ) {
		$post_id = absint( $post_id );
		$post    = get_post( $post_id );

		if ( ! $post || self::CPT !== $post->post_type ) {
			return null;
		}

		$data = [ 'id' => $post_id ];
		foreach ( self::scalar_fields() as $field => $kind ) {
			$data[ $field ] = (string) get_post_meta( $post_id, self::META_PREFIX . $field, true );
		}

		$line_items = get_post_meta( $post_id, self::META_LINE_ITEMS, true );
		$data['line_items'] = is_array( $line_items ) ? $line_items : [];

		return $data;
	}

	/**
	 * List invoices, newest first. Returns an array of lightweight rows for the
	 * listing table: id, invoice_no, invoice_date, vin, modified.
	 */
	public static function list_invoices( $limit = 100 ) {
		$posts = get_posts(
			[
				'post_type'      => self::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => (int) $limit,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			]
		);

		$rows = [];
		foreach ( $posts as $post ) {
			$rows[] = [
				'id'           => $post->ID,
				'invoice_no'   => (string) get_post_meta( $post->ID, self::META_PREFIX . 'invoice_no', true ),
				'invoice_date' => (string) get_post_meta( $post->ID, self::META_PREFIX . 'invoice_date', true ),
				'vin'          => (string) get_post_meta( $post->ID, self::META_PREFIX . 'vin', true ),
				'modified'     => $post->post_modified,
			];
		}

		return $rows;
	}

	// ── Totals (render-time only, never stored) ─────────────────────────────────

	/**
	 * Compute totals from a line-item array.
	 * total_msrp = sum(msrp), total_invoice = sum(invoice). No discount total,
	 * no net, no per-row derivation.
	 */
	public static function compute_totals( $line_items ) {
		$total_msrp    = 0.0;
		$total_invoice = 0.0;

		if ( is_array( $line_items ) ) {
			foreach ( $line_items as $item ) {
				$total_msrp    += floatval( $item['msrp'] ?? 0 );
				$total_invoice += floatval( $item['invoice'] ?? 0 );
			}
		}

		return [
			'total_msrp'    => $total_msrp,
			'total_invoice' => $total_invoice,
		];
	}
}
