<?php
/**
 * WordPress admin page: Tools → ClickUp Import
 *
 * Exposes the Slate_Ops_ClickUp_Importer steps (backup, clear-dry/execute,
 * import-dry/execute) via a form-based admin UI. No CLI or React required.
 *
 * Security: manage_options cap + per-action nonces.
 * CLI runner (local-imports/run-import.php) is unchanged.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Slate_Ops_ClickUp_Import_Admin {

	private static function jsonl_path() {
		return SLATE_OPS_PATH . 'local-imports/clickup-active-cleaned.jsonl';
	}

	private static function backup_path() {
		return SLATE_OPS_PATH . 'backup/jobs-pre-clickup-import.json';
	}

	public static function register_menu() {
		add_management_page(
			'ClickUp Import',
			'ClickUp Import',
			'manage_options',
			'clickup-import',
			[ __CLASS__, 'render' ]
		);
	}

	// ── Page renderer ────────────────────────────────────────────────────────

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Access denied.' );
		}

		require_once SLATE_OPS_PATH . 'includes/cli/class-clickup-importer.php';

		$log     = null;   // array of strings when a step was run
		$notice  = null;   // error message
		$success = null;   // success message

		// ── Handle file upload ────────────────────────────────────────────────
		if ( isset( $_POST['clickup_action'] ) && $_POST['clickup_action'] === 'upload_jsonl' ) {
			check_admin_referer( 'clickup_upload_jsonl' );

			if ( empty( $_FILES['jsonl_file'] ) || $_FILES['jsonl_file']['error'] !== UPLOAD_ERR_OK ) {
				$notice = 'No file received or upload error (code ' . ( $_FILES['jsonl_file']['error'] ?? '-' ) . ').';
			} else {
				$file = $_FILES['jsonl_file'];
				$ext  = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

				if ( $ext !== 'jsonl' ) {
					$notice = 'Rejected: file must have a .jsonl extension (got .' . esc_html( $ext ) . ').';
				} elseif ( ! self::validate_jsonl_content( $file['tmp_name'] ) ) {
					$notice = 'Rejected: file does not appear to be valid JSONL (first line failed JSON decode).';
				} elseif ( ! move_uploaded_file( $file['tmp_name'], self::jsonl_path() ) ) {
					$notice = 'Upload failed: could not save file. Check directory permissions.';
				} else {
					$lines   = count( file( self::jsonl_path(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) );
					$success = "Uploaded clickup-active-cleaned.jsonl ($lines rows).";
				}
			}
		}

		// ── Handle step ──────────────────────────────────────────────────────
		if ( isset( $_POST['clickup_action'] ) && $_POST['clickup_action'] === 'run_step' ) {
			$step        = sanitize_key( $_POST['step'] ?? '' );
			$valid_steps = [ 'backup', 'clear-dry', 'clear-execute', 'import-dry', 'import-execute' ];

			if ( ! in_array( $step, $valid_steps, true ) ) {
				wp_die( 'Invalid step.' );
			}

			check_admin_referer( 'clickup_step_' . $step );

			$log = self::run_step( $step );
		}

		// ── Current state ─────────────────────────────────────────────────────
		$jsonl_exists  = file_exists( self::jsonl_path() );
		$jsonl_lines   = $jsonl_exists
			? count( file( self::jsonl_path(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) )
			: 0;
		$backup_exists = file_exists( self::backup_path() );

		// ── HTML ─────────────────────────────────────────────────────────────
		?>
		<div class="wrap">
			<h1>ClickUp Import</h1>
			<p>One-time ClickUp → Slate Ops migration. Run steps in order:
				<strong>Backup → Clear dry-run → Import dry-run → Clear execute → Import execute.</strong>
			</p>

			<?php if ( $notice ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>
			<?php if ( $success ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $success ); ?></p></div>
			<?php endif; ?>

			<hr>

			<!-- ── Upload ──────────────────────────────────────────────────── -->
			<h2 style="margin-top:0">1. Upload JSONL</h2>
			<p>
				<?php if ( $jsonl_exists ) : ?>
					<span style="color:#1e7e34;font-weight:600">&#10003; clickup-active-cleaned.jsonl present
						(<?php echo (int) $jsonl_lines; ?> rows)</span>
				<?php else : ?>
					<span style="color:#c0392b;font-weight:600">&#10007; clickup-active-cleaned.jsonl not found — upload before running import steps</span>
				<?php endif; ?>
			</p>
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'clickup_upload_jsonl' ); ?>
				<input type="hidden" name="clickup_action" value="upload_jsonl">
				<input type="file" name="jsonl_file" accept=".jsonl" required style="margin-right:8px">
				<?php submit_button( 'Upload', 'secondary', 'submit_upload', false ); ?>
			</form>

			<hr>

			<!-- ── Steps ──────────────────────────────────────────────────── -->
			<h2 style="margin-top:0">2. Run Steps</h2>
			<p>
				<?php if ( $backup_exists ) : ?>
					<span style="color:#1e7e34">&#10003; Backup file present at <code>backup/jobs-pre-clickup-import.json</code></span>
				<?php else : ?>
					<span style="color:#856404">&#9888; No backup yet — run Backup before any execute step</span>
				<?php endif; ?>
			</p>

			<?php
			$steps = [
				'backup'         => [
					'label'   => 'Backup',
					'style'   => 'secondary',
					'confirm' => false,
					'desc'    => 'Snapshot all existing jobs to backup/jobs-pre-clickup-import.json. Safe.',
				],
				'clear-dry'      => [
					'label'   => 'Clear (dry-run)',
					'style'   => 'secondary',
					'confirm' => false,
					'desc'    => 'Count jobs + time records that would be deleted. No changes.',
				],
				'import-dry'     => [
					'label'   => 'Import (dry-run)',
					'style'   => 'secondary',
					'confirm' => false,
					'desc'    => 'Parse JSONL and preview what would be inserted. No changes.',
				],
				'clear-execute'  => [
					'label'   => 'Clear (execute)',
					'style'   => 'primary',
					'confirm' => 'This will DELETE all jobs and time records permanently. Run Backup first. Continue?',
					'desc'    => 'Delete all jobs and time records. Requires backup to be run first.',
				],
				'import-execute' => [
					'label'   => 'Import (execute)',
					'style'   => 'primary',
					'confirm' => 'This will INSERT rows from the JSONL into the database. Continue?',
					'desc'    => 'Insert all 23 rows from the JSONL. Expected: 23 inserted, 0 skipped.',
				],
			];
			?>

			<table class="widefat striped" style="max-width:680px">
				<thead>
					<tr>
						<th>Step</th>
						<th>Description</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $steps as $step_key => $step ) :
					$onclick = $step['confirm']
						? 'onclick="return confirm(' . esc_attr( wp_json_encode( $step['confirm'] ) ) . ')"'
						: '';
				?>
					<tr>
						<td><strong><?php echo esc_html( $step['label'] ); ?></strong></td>
						<td><?php echo esc_html( $step['desc'] ); ?></td>
						<td>
							<form method="post" style="margin:0">
								<?php wp_nonce_field( 'clickup_step_' . $step_key ); ?>
								<input type="hidden" name="clickup_action" value="run_step">
								<input type="hidden" name="step" value="<?php echo esc_attr( $step_key ); ?>">
								<button type="submit"
									class="button button-<?php echo esc_attr( $step['style'] ); ?>"
									<?php echo $onclick; // already escaped above ?>>
									<?php echo esc_html( $step['label'] ); ?>
								</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<!-- ── Log panel ───────────────────────────────────────────────── -->
			<?php if ( $log !== null ) : ?>
				<hr>
				<h2>Output</h2>
				<pre style="background:#1d2327;color:#a8e6cf;padding:16px 20px;overflow:auto;max-height:520px;font-size:13px;line-height:1.6;border-radius:4px"><?php echo esc_html( implode( "\n", $log ) ); ?></pre>
			<?php endif; ?>
		</div>
		<?php
	}

	// ── Upload validation ────────────────────────────────────────────────────

	private static function validate_jsonl_content( $tmp_path ) {
		$fh = fopen( $tmp_path, 'r' );
		if ( ! $fh ) {
			return false;
		}
		$first = trim( fgets( $fh ) );
		fclose( $fh );
		if ( $first === '' ) {
			return false;
		}
		$decoded = json_decode( $first, true );
		return is_array( $decoded );
	}

	// ── Step dispatcher ──────────────────────────────────────────────────────

	private static function run_step( $step ) {
		$log         = [ '=== ' . $step . ' ===' ];
		$jsonl_path  = self::jsonl_path();
		$backup_path = self::backup_path();

		switch ( $step ) {

			case 'backup':
				$result = Slate_Ops_ClickUp_Importer::backup( $backup_path );
				$log[]  = 'Jobs exported : ' . $result['count'];
				$log[]  = 'Saved to      : ' . $result['path'];
				break;

			case 'clear-dry':
				$result = Slate_Ops_ClickUp_Importer::clear_dry_run();
				$log[]  = 'Jobs          : ' . $result['jobs'];
				$log[]  = 'Time segments : ' . $result['time_segments'];
				$log[]  = 'Audit entries : ' . $result['audit_entries'];
				$log[]  = '(dry-run — no changes made)';
				break;

			case 'clear-execute':
				$result = Slate_Ops_ClickUp_Importer::clear_execute( true );
				if ( is_wp_error( $result ) ) {
					$log[] = 'ERROR: ' . $result->get_error_message();
				} else {
					$log[] = 'Jobs deleted          : ' . $result['jobs_deleted'];
					$log[] = 'Time segments deleted : ' . $result['time_segments_deleted'];
					$log[] = 'Audit entries deleted : ' . $result['audit_deleted'];
					$log[] = 'Users / roles / settings untouched.';
				}
				break;

			case 'import-dry':
				if ( ! file_exists( $jsonl_path ) ) {
					$log[] = 'ERROR: JSONL not found. Upload clickup-active-cleaned.jsonl first.';
					break;
				}
				$result = Slate_Ops_ClickUp_Importer::import_dry_run( $jsonl_path );
				$log[]  = 'Would insert : ' . $result['would_insert'];
				$log[]  = 'Would skip   : ' . $result['would_skip'];
				if ( ! empty( $result['rows'] ) ) {
					$log[] = '';
					$log[] = 'Preview:';
					foreach ( $result['rows'] as $i => $r ) {
						$n   = $i + 1;
						$est = $r['est_minutes'] ? ( $r['est_minutes'] / 60 ) . 'h' : '-';
						$log[] = sprintf( '  %2d. [%s / %s] %s', $n, $r['status'], $r['parts_status'], $r['customer_name'] );
						if ( $r['lead_tech'] ) {
							$log[] = '      Tech  : ' . $r['lead_tech'];
						}
						$log[] = '      Est   : ' . $est;
						$s = $r['scheduled_start'] ?? null;
						$f = $r['scheduled_finish'] ?? null;
						if ( $s || $f ) {
							$log[] = '      Dates : start=' . ( $s ?: '-' ) . '  due=' . ( $f ?: '-' );
						}
					}
				}
				if ( ! empty( $result['skipped'] ) ) {
					$log[] = '';
					$log[] = 'Skipped:';
					foreach ( $result['skipped'] as $s ) {
						$name  = $s['customer_name'] ?? ( $s['line'] ?? '?' );
						$log[] = '  - ' . $name . ': ' . $s['reason'];
					}
				}
				break;

			case 'import-execute':
				if ( ! file_exists( $jsonl_path ) ) {
					$log[] = 'ERROR: JSONL not found. Upload clickup-active-cleaned.jsonl first.';
					break;
				}
				$result = Slate_Ops_ClickUp_Importer::import_execute( $jsonl_path, true );
				if ( is_wp_error( $result ) ) {
					$log[] = 'ERROR: ' . $result->get_error_message();
				} else {
					$log[] = 'Inserted : ' . $result['inserted'];
					$log[] = 'Skipped  : ' . $result['skipped'];
					if ( ! empty( $result['errors'] ) ) {
						$log[] = '';
						$log[] = 'DB errors:';
						foreach ( $result['errors'] as $e ) {
							$log[] = '  - ' . $e['customer_name'] . ': ' . $e['db_error'];
						}
					}
					if ( ! empty( $result['skipped_rows'] ) ) {
						$log[] = '';
						$log[] = 'Skipped rows:';
						foreach ( $result['skipped_rows'] as $s ) {
							$log[] = '  - ' . ( $s['customer_name'] ?? '?' ) . ': ' . $s['reason'];
						}
					}
				}
				break;
		}

		return $log;
	}
}
