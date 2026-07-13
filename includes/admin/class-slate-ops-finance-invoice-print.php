<?php
/**
 * Finance Invoice — print output (Phase 2).
 *
 * Read-only rendering of a saved slate_finance_invoice into a clean,
 * brand-styled, Letter-portrait HTML document that staff print to PDF via the
 * browser (Save-as-PDF). No PDF library this phase.
 *
 * Route: admin-post.php?action=slate_ops_finance_invoice_print — CAP_CS-gated
 * and nonce-verified in the handler (the route runs outside the Tools page, so
 * the gate is enforced here directly, reusing Slate_Ops_Finance_Invoice::CAP).
 *
 * The HTML builder (build_html) returns a string and is intentionally separate
 * from the request handler so the documented v2 fast-follow — server-side PDF
 * via a pure-PHP library (Dompdf/mPDF) — can reuse the exact same builder.
 *
 * v2 fast-follow (NOT built here): feed build_html() output to Dompdf/mPDF to
 * emit application/pdf instead of streaming HTML to the browser.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Slate_Ops_Finance_Invoice_Print {

	const PRINT_ACTION = 'slate_ops_finance_invoice_print';
	const NONCE_ACTION = 'slate_ops_fi_print';

	public static function boot() {
		add_action( 'admin_post_' . self::PRINT_ACTION, [ __CLASS__, 'handle' ] );
	}

	/**
	 * Build the nonced print URL for a saved invoice. Used by the tab to link
	 * the "Print / PDF" action.
	 */
	public static function print_url( $invoice_id ) {
		$url = add_query_arg(
			[
				'action'     => self::PRINT_ACTION,
				'invoice_id' => (int) $invoice_id,
			],
			admin_url( 'admin-post.php' )
		);
		return wp_nonce_url( $url, self::NONCE_ACTION );
	}

	// ── Request handler ──────────────────────────────────────────────────────────

	public static function handle() {
		if ( ! current_user_can( Slate_Ops_Finance_Invoice::CAP ) ) {
			wp_die(
				esc_html__( 'You do not have permission to print invoices.', 'slate-ops' ),
				esc_html__( 'Access denied', 'slate-ops' ),
				[ 'response' => 403 ]
			);
		}

		// Nonce (GET link) — check_admin_referer dies on failure.
		check_admin_referer( self::NONCE_ACTION );

		$invoice_id = isset( $_GET['invoice_id'] ) ? absint( $_GET['invoice_id'] ) : 0;
		$invoice    = $invoice_id ? Slate_Ops_Finance_Invoice::get( $invoice_id ) : null;

		if ( ! $invoice ) {
			wp_die( esc_html__( 'Invoice not found.', 'slate-ops' ) );
		}

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );

		echo self::build_html( $invoice ); // phpcs:ignore WordPress.Security.EscapeOutput -- builder escapes every field internally.
		exit;
	}

	// ── Formatting helpers ───────────────────────────────────────────────────────

	/** True when a stored numeric cell is blank (Phase 1 preserves '' for blanks). */
	private static function is_blank( $value ) {
		return '' === trim( (string) $value );
	}

	/** $#,##0.00 */
	private static function money( $value ) {
		return '$' . number_format( floatval( $value ), 2 );
	}

	/** Parenthesized negative for the discount column, e.g. ($5,197.00). */
	private static function discount( $value ) {
		return '($' . number_format( abs( floatval( $value ) ), 2 ) . ')';
	}

	// ── HTML builder (reused by v2 server-side PDF) ──────────────────────────────

	/**
	 * Build the full standalone invoice document as an HTML string.
	 * Every dynamic value is escaped here; the caller may echo the result as-is.
	 */
	public static function build_html( array $invoice ) {
		$totals = Slate_Ops_Finance_Invoice::compute_totals( $invoice['line_items'] );

		$title = $invoice['invoice_no'] !== ''
			? sprintf( 'Invoice %s', $invoice['invoice_no'] )
			: 'Invoice';

		ob_start();
		?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $title ); ?></title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
	<style><?php echo self::styles(); // trusted static CSS ?></style>
</head>
<body>
<div class="inv">

	<!-- Top band -->
	<header class="inv-top">
		<div class="inv-top__left">
			<div class="inv-logo"><?php echo self::logo_svg(); // trusted repo SVG ?></div>
			<div class="inv-addr">
				19400 SE McLoughlin Blvd<br>
				Gladstone, OR 97027<br>
				Phone: (503) 486-5461
			</div>
		</div>
		<div class="inv-top__right">
			<div class="inv-title">INVOICE</div>
			<div class="inv-page">Page 1</div>
			<table class="inv-meta-top">
				<tr>
					<th>Invoice Number</th>
					<td><?php echo esc_html( $invoice['invoice_no'] ); ?></td>
				</tr>
				<tr>
					<th>Invoice Date</th>
					<td><?php echo esc_html( $invoice['invoice_date'] ); ?></td>
				</tr>
			</table>
		</div>
	</header>

	<!-- Vehicle + Ship-To -->
	<section class="inv-cols">
		<div class="inv-block">
			<div class="inv-block__head">Vehicle Description</div>
			<table class="inv-kv">
				<tr><th>Make</th><td><?php echo esc_html( $invoice['veh_make'] ); ?></td></tr>
				<tr><th>Model</th><td><?php echo esc_html( $invoice['veh_model'] ); ?></td></tr>
				<tr><th>RVIA #</th><td><?php echo esc_html( $invoice['rvia_no'] ); ?></td></tr>
				<tr><th>VIN</th><td><?php echo esc_html( $invoice['vin'] ); ?></td></tr>
			</table>
		</div>
		<div class="inv-block">
			<div class="inv-block__head">Ship To</div>
			<div class="inv-shipto">
				<div class="inv-shipto__name"><?php echo esc_html( $invoice['ship_to_name'] ); ?></div>
				<div class="inv-shipto__addr"><?php echo nl2br( esc_html( $invoice['ship_to_address'] ) ); ?></div>
			</div>
		</div>
	</section>

	<!-- Meta row -->
	<table class="inv-metarow">
		<thead>
			<tr>
				<th>Salesperson</th>
				<th>Year</th>
				<th>Make</th>
				<th>Model</th>
				<th>Wheelbase</th>
				<th>Stock #</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><?php echo esc_html( $invoice['salesperson'] ); ?></td>
				<td><?php echo esc_html( $invoice['model_year'] ); ?></td>
				<td><?php echo esc_html( $invoice['meta_make'] ); ?></td>
				<td><?php echo esc_html( $invoice['meta_model'] ); ?></td>
				<td><?php echo esc_html( $invoice['wheelbase'] ); ?></td>
				<td><?php echo esc_html( $invoice['stock_no'] ); ?></td>
			</tr>
		</tbody>
	</table>

	<!-- Line items -->
	<table class="inv-lines">
		<thead>
			<tr>
				<th class="inv-caption" colspan="5">Equipment Installed By Manufacturer</th>
			</tr>
			<tr class="inv-lines__head">
				<th class="c-qty">Quantity</th>
				<th class="c-desc">Description</th>
				<th class="c-num">MSRP</th>
				<th class="c-num">Discount</th>
				<th class="c-num">Invoice</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $invoice['line_items'] as $item ) :
				$msrp_blank = self::is_blank( $item['msrp'] ?? '' );
				$disc_blank = self::is_blank( $item['discount'] ?? '' );
				$inv_blank  = self::is_blank( $item['invoice'] ?? '' );
				// Feature sub-line: no pricing (MSRP and Discount both blank) → indent.
				$is_feature = $msrp_blank && $disc_blank;
				?>
				<tr>
					<td class="c-qty"><?php echo esc_html( $item['qty'] ?? '' ); ?></td>
					<td class="c-desc<?php echo $is_feature ? ' is-feature' : ''; ?>"><?php echo esc_html( $item['description'] ?? '' ); ?></td>
					<td class="c-num"><?php echo $msrp_blank ? '' : esc_html( self::money( $item['msrp'] ) ); ?></td>
					<td class="c-num"><?php echo $disc_blank ? '' : esc_html( self::discount( $item['discount'] ) ); ?></td>
					<td class="c-num"><?php echo $inv_blank ? esc_html( self::money( 0 ) ) : esc_html( self::money( $item['invoice'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
		<tfoot>
			<tr class="inv-lines__totals">
				<td class="c-qty"></td>
				<td class="c-desc inv-totals-label">Total MSRP / TOTAL</td>
				<td class="c-num"><?php echo esc_html( self::money( $totals['total_msrp'] ) ); ?></td>
				<td class="c-num"></td>
				<td class="c-num"><?php echo esc_html( self::money( $totals['total_invoice'] ) ); ?></td>
			</tr>
		</tfoot>
	</table>

	<footer class="inv-foot">THANK YOU FOR YOUR BUSINESS!</footer>

</div>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	/** Inline the repo Slate logo SVG (trusted); text fallback if unreadable. */
	private static function logo_svg() {
		$path = SLATE_OPS_PATH . 'assets/logos/slate-logo.svg';
		if ( is_readable( $path ) ) {
			$svg = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false !== $svg ) {
				// Print-doc-local recolor of the logo bar to the invoice accent so
				// the mark matches the "INVOICE" title. Applied to this inlined copy
				// ONLY — the shared assets/logos/slate-logo.svg is not modified, and
				// this is not a resolution of the Ops/Portal brand-token discrepancy.
				return str_replace( '#d86b19', '#BA7517', $svg );
			}
		}
		return '<span class="inv-logo__fallback">SLATE</span>';
	}

	/** Inline the print stylesheet (trusted static asset). */
	private static function styles() {
		$path = SLATE_OPS_PATH . 'assets/css/ops-finance-invoice-print.css';
		if ( is_readable( $path ) ) {
			$css = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false !== $css ) {
				return $css;
			}
		}
		return '';
	}
}
