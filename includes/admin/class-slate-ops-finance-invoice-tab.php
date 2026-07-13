<?php
/**
 * Finance Invoice — Tools page tab (admin form + listing + save handler).
 *
 * Registers itself as the first tab on the Slate Ops Tools page via the
 * `slate_ops_tools_tabs` filter. Owns its own POST/save handler (dispatched by
 * the Tools controller from load-{hook}) and enqueues its own assets.
 *
 * Functional form only — no print/PDF, no output styling beyond basic admin
 * layout. Totals are previewed live in JS but never persisted; the data class
 * recomputes them at render time.
 *
 * Every write is nonce-verified and capability-checked (Slate_Ops_Finance_Invoice::CAP);
 * every field is sanitized by the data class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Slate_Ops_Finance_Invoice_Tab {

	const TAB_ID = 'finance-invoice';
	const NONCE_ACTION = 'slate_fi_save';
	const NONCE_FIELD  = 'slate_fi_nonce';

	public static function boot() {
		add_filter( 'slate_ops_tools_tabs', [ __CLASS__, 'register_tab' ] );
		add_action( 'slate_ops_tools_enqueue_assets', [ __CLASS__, 'enqueue' ] );
	}

	/** Self-register as a tab. First tool registered => default tab. */
	public static function register_tab( $tabs ) {
		$tabs[] = [
			'id'         => self::TAB_ID,
			'label'      => 'Invoice Generator',
			'capability' => Slate_Ops_Finance_Invoice::CAP,
			'render'     => [ __CLASS__, 'render' ],
			'save'       => [ __CLASS__, 'save' ],
		];
		return $tabs;
	}

	public static function enqueue( $active_id ) {
		if ( self::TAB_ID !== $active_id ) {
			return;
		}
		wp_enqueue_script(
			'slate-ops-tools-finance-invoice',
			SLATE_OPS_URL . 'assets/js/ops-tools-finance-invoice.js',
			[],
			SLATE_OPS_VERSION,
			true
		);
	}

	// ── Save handler (POST) ─────────────────────────────────────────────────────

	/**
	 * Handle a create/update POST. Runs on load-{hook}; capability is already
	 * enforced by the Tools controller, re-checked here as defense-in-depth.
	 * Redirects (PRG) back to the tab with a status flag.
	 */
	public static function save() {
		if ( ! current_user_can( Slate_Ops_Finance_Invoice::CAP ) ) {
			wp_die(
				esc_html__( 'You do not have permission to save invoices.', 'slate-ops' ),
				esc_html__( 'Access denied', 'slate-ops' ),
				[ 'response' => 403 ]
			);
		}

		if ( ! isset( $_POST[ self::NONCE_FIELD ] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed.', 'slate-ops' ) );
		}

		$invoice_id = isset( $_POST['invoice_id'] ) ? absint( $_POST['invoice_id'] ) : 0;

		// Raw, unslashed input; the data class sanitizes every field.
		$raw = wp_unslash( $_POST );

		$result = $invoice_id
			? Slate_Ops_Finance_Invoice::update( $invoice_id, $raw )
			: Slate_Ops_Finance_Invoice::create( $raw );

		$saved_id = is_wp_error( $result ) ? 0 : (int) $result;

		$args = [
			'page' => Slate_Ops_Tools::MENU_SLUG,
			'tab'  => self::TAB_ID,
			'msg'  => $saved_id ? 'saved' : 'error',
		];
		if ( $saved_id ) {
			$args['invoice_id'] = $saved_id;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'tools.php' ) ) );
		exit;
	}

	// ── Render ──────────────────────────────────────────────────────────────────

	public static function render() {
		// Defense-in-depth (Tools controller already gated this route).
		if ( ! current_user_can( Slate_Ops_Finance_Invoice::CAP ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Access denied.', 'slate-ops' ) . '</p></div>';
			return;
		}

		$editing_id = isset( $_GET['invoice_id'] ) ? absint( $_GET['invoice_id'] ) : 0;
		$invoice    = $editing_id ? Slate_Ops_Finance_Invoice::get( $editing_id ) : null;

		self::render_notice();
		self::render_form( $invoice );
		self::render_list( $invoice ? (int) $invoice['id'] : 0 );
	}

	private static function render_notice() {
		$msg = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';
		if ( 'saved' === $msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html__( 'Invoice saved.', 'slate-ops' ) . '</p></div>';
		} elseif ( 'error' === $msg ) {
			echo '<div class="notice notice-error is-dismissible"><p>'
				. esc_html__( 'Could not save the invoice.', 'slate-ops' ) . '</p></div>';
		}
	}

	/** Value helper: read a field from the loaded invoice (or '' for a new one). */
	private static function val( $invoice, $field ) {
		return $invoice && isset( $invoice[ $field ] ) ? (string) $invoice[ $field ] : '';
	}

	private static function text_field( $invoice, $field, $label, $attrs = '' ) {
		printf(
			'<p class="slate-fi-field"><label for="fi-%1$s">%2$s</label>'
				. '<input type="text" id="fi-%1$s" name="%1$s" value="%3$s" %4$s></p>',
			esc_attr( $field ),
			esc_html( $label ),
			esc_attr( self::val( $invoice, $field ) ),
			$attrs // static, trusted attribute string from call sites
		);
	}

	private static function render_form( $invoice ) {
		$editing_id = $invoice ? (int) $invoice['id'] : 0;
		$form_url   = add_query_arg(
			[
				'page' => Slate_Ops_Tools::MENU_SLUG,
				'tab'  => self::TAB_ID,
			],
			admin_url( 'tools.php' )
		);
		?>
		<h2><?php echo $editing_id ? esc_html__( 'Edit Invoice', 'slate-ops' ) : esc_html__( 'New Invoice', 'slate-ops' ); ?></h2>

		<form method="post" action="<?php echo esc_url( $form_url ); ?>" class="slate-fi-form">
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
			<input type="hidden" name="invoice_id" value="<?php echo esc_attr( $editing_id ); ?>">

			<fieldset class="slate-fi-group">
				<legend><?php esc_html_e( 'Header / Vehicle', 'slate-ops' ); ?></legend>
				<?php
				self::text_field( $invoice, 'invoice_date', __( 'Invoice Date', 'slate-ops' ) );
				self::text_field( $invoice, 'invoice_no', __( 'Invoice No. (manual, e.g. PS-INV#####)', 'slate-ops' ) );
				self::text_field( $invoice, 'veh_make', __( 'Make', 'slate-ops' ) );
				self::text_field( $invoice, 'veh_model', __( 'Model', 'slate-ops' ) );
				self::text_field( $invoice, 'rvia_no', __( 'RVIA No.', 'slate-ops' ) );
				self::text_field( $invoice, 'vin', __( 'VIN', 'slate-ops' ) );
				?>
			</fieldset>

			<fieldset class="slate-fi-group">
				<legend><?php esc_html_e( 'Ship To', 'slate-ops' ); ?></legend>
				<?php self::text_field( $invoice, 'ship_to_name', __( 'Name', 'slate-ops' ) ); ?>
				<p class="slate-fi-field">
					<label for="fi-ship_to_address"><?php esc_html_e( 'Address', 'slate-ops' ); ?></label>
					<textarea id="fi-ship_to_address" name="ship_to_address" rows="3"><?php
						echo esc_textarea( self::val( $invoice, 'ship_to_address' ) );
					?></textarea>
				</p>
			</fieldset>

			<fieldset class="slate-fi-group">
				<legend><?php esc_html_e( 'Meta', 'slate-ops' ); ?></legend>
				<?php
				self::text_field( $invoice, 'salesperson', __( 'Salesperson', 'slate-ops' ) );
				self::text_field( $invoice, 'model_year', __( 'Model Year', 'slate-ops' ) );
				self::text_field( $invoice, 'meta_make', __( 'Make', 'slate-ops' ) );
				self::text_field( $invoice, 'meta_model', __( 'Model', 'slate-ops' ) );
				self::text_field( $invoice, 'wheelbase', __( 'Wheelbase', 'slate-ops' ) );
				self::text_field( $invoice, 'stock_no', __( 'Stock No.', 'slate-ops' ) );
				?>
			</fieldset>

			<fieldset class="slate-fi-group">
				<legend><?php esc_html_e( 'Line Items', 'slate-ops' ); ?></legend>
				<p class="description">
					<?php esc_html_e( 'Rows with blank MSRP / Discount / Invoice are valid feature sub-lines (they render $0.00). Nothing is computed — every number is exactly what you type.', 'slate-ops' ); ?>
				</p>
				<?php self::render_line_items_table( $invoice ); ?>
			</fieldset>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php echo $editing_id ? esc_html__( 'Update Invoice', 'slate-ops' ) : esc_html__( 'Create Invoice', 'slate-ops' ); ?>
				</button>
				<?php if ( $editing_id ) : ?>
					<a class="button" href="<?php echo esc_url( $form_url ); ?>"><?php esc_html_e( 'Cancel / New', 'slate-ops' ); ?></a>
				<?php endif; ?>
			</p>
		</form>
		<?php
	}

	private static function render_line_items_table( $invoice ) {
		$items = $invoice && ! empty( $invoice['line_items'] ) ? $invoice['line_items'] : [];
		$totals = Slate_Ops_Finance_Invoice::compute_totals( $items );
		?>
		<table class="widefat slate-fi-lines" id="slate-fi-lines">
			<thead>
				<tr>
					<th class="col-qty"><?php esc_html_e( 'Qty', 'slate-ops' ); ?></th>
					<th class="col-desc"><?php esc_html_e( 'Description', 'slate-ops' ); ?></th>
					<th class="col-num"><?php esc_html_e( 'MSRP', 'slate-ops' ); ?></th>
					<th class="col-num"><?php esc_html_e( 'Discount', 'slate-ops' ); ?></th>
					<th class="col-num"><?php esc_html_e( 'Invoice', 'slate-ops' ); ?></th>
					<th class="col-actions"></th>
				</tr>
			</thead>
			<tbody class="slate-fi-lines__body">
				<?php
				if ( empty( $items ) ) {
					self::render_line_row( 0, [] );
				} else {
					foreach ( array_values( $items ) as $i => $item ) {
						self::render_line_row( $i, $item );
					}
				}
				?>
			</tbody>
			<tfoot>
				<tr class="slate-fi-lines__totals">
					<th colspan="2"><?php esc_html_e( 'Totals (preview — not saved)', 'slate-ops' ); ?></th>
					<td class="col-num"><span data-total="msrp"><?php echo esc_html( number_format( $totals['total_msrp'], 2 ) ); ?></span></td>
					<td class="col-num">&mdash;</td>
					<td class="col-num"><span data-total="invoice"><?php echo esc_html( number_format( $totals['total_invoice'], 2 ) ); ?></span></td>
					<td></td>
				</tr>
			</tfoot>
		</table>
		<p>
			<button type="button" class="button" id="slate-fi-add-row"><?php esc_html_e( '+ Add Row', 'slate-ops' ); ?></button>
		</p>

		<!-- Template row for JS cloning; __i__ is replaced with a running index. -->
		<template id="slate-fi-row-template">
			<?php self::render_line_row( '__i__', [] ); ?>
		</template>
		<?php
	}

	/**
	 * Render one line-item row. $index may be an int or the literal '__i__'
	 * placeholder (template row); it is escaped either way.
	 */
	private static function render_line_row( $index, $item ) {
		$idx  = esc_attr( $index );
		$qty  = isset( $item['qty'] ) ? (string) $item['qty'] : '';
		$desc = isset( $item['description'] ) ? (string) $item['description'] : '';
		$msrp = isset( $item['msrp'] ) ? (string) $item['msrp'] : '';
		$disc = isset( $item['discount'] ) ? (string) $item['discount'] : '';
		$inv  = isset( $item['invoice'] ) ? (string) $item['invoice'] : '';
		?>
		<tr class="slate-fi-line">
			<td class="col-qty"><input type="text" name="line_items[<?php echo $idx; ?>][qty]" value="<?php echo esc_attr( $qty ); ?>"></td>
			<td class="col-desc"><input type="text" name="line_items[<?php echo $idx; ?>][description]" value="<?php echo esc_attr( $desc ); ?>"></td>
			<td class="col-num"><input type="number" step="any" class="slate-fi-num" data-col="msrp" name="line_items[<?php echo $idx; ?>][msrp]" value="<?php echo esc_attr( $msrp ); ?>"></td>
			<td class="col-num"><input type="number" step="any" name="line_items[<?php echo $idx; ?>][discount]" value="<?php echo esc_attr( $disc ); ?>"></td>
			<td class="col-num"><input type="number" step="any" class="slate-fi-num" data-col="invoice" name="line_items[<?php echo $idx; ?>][invoice]" value="<?php echo esc_attr( $inv ); ?>"></td>
			<td class="col-actions"><button type="button" class="button-link slate-fi-remove-row" aria-label="<?php esc_attr_e( 'Remove row', 'slate-ops' ); ?>">&times;</button></td>
		</tr>
		<?php
	}

	private static function render_list( $active_id ) {
		$rows = Slate_Ops_Finance_Invoice::list_invoices();
		?>
		<hr>
		<h2><?php esc_html_e( 'Saved Invoices', 'slate-ops' ); ?></h2>
		<?php if ( empty( $rows ) ) : ?>
			<p><?php esc_html_e( 'No invoices yet.', 'slate-ops' ); ?></p>
			<?php
			return;
		endif;
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Invoice No.', 'slate-ops' ); ?></th>
					<th><?php esc_html_e( 'Date', 'slate-ops' ); ?></th>
					<th><?php esc_html_e( 'VIN', 'slate-ops' ); ?></th>
					<th><?php esc_html_e( 'Last Modified', 'slate-ops' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) :
					$edit_url = add_query_arg(
						[
							'page'       => Slate_Ops_Tools::MENU_SLUG,
							'tab'        => self::TAB_ID,
							'invoice_id' => (int) $row['id'],
						],
						admin_url( 'tools.php' )
					);
					$is_active = ( (int) $row['id'] === $active_id );
					?>
					<tr<?php echo $is_active ? ' class="slate-fi-row-active"' : ''; ?>>
						<td><?php echo esc_html( $row['invoice_no'] !== '' ? $row['invoice_no'] : '—' ); ?></td>
						<td><?php echo esc_html( $row['invoice_date'] !== '' ? $row['invoice_date'] : '—' ); ?></td>
						<td><?php echo esc_html( $row['vin'] !== '' ? $row['vin'] : '—' ); ?></td>
						<td><?php echo esc_html( $row['modified'] ); ?></td>
						<td><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'slate-ops' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
