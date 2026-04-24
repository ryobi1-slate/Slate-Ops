<?php
/**
 * One-time ClickUp import runner — bootstraps WordPress then executes a step.
 *
 * Usage (run from repo root or any directory):
 *
 *   php local-imports/run-import.php --wp-path=/path/to/wordpress --step=STEP [--confirm]
 *
 * Steps (run in order):
 *   backup          Export all current jobs → backup/jobs-pre-clickup-import.json
 *   clear-dry       Count jobs + time records (safe, no changes)
 *   clear-execute   Delete all jobs and related time records  (requires --confirm)
 *   import-dry      Parse JSONL and show what would be inserted (safe, no changes)
 *   import-execute  Insert rows from JSONL into the database  (requires --confirm)
 *
 * Examples:
 *   php local-imports/run-import.php --wp-path=/var/www/html --step=backup
 *   php local-imports/run-import.php --wp-path=/var/www/html --step=clear-dry
 *   php local-imports/run-import.php --wp-path=/var/www/html --step=clear-execute --confirm
 *   php local-imports/run-import.php --wp-path=/var/www/html --step=import-dry
 *   php local-imports/run-import.php --wp-path=/var/www/html --step=import-execute --confirm
 *
 * JSONL input:  (repo-root)/local-imports/clickup-active-cleaned.jsonl
 * Backup output:(repo-root)/backup/jobs-pre-clickup-import.json
 *
 * Regenerate the JSONL first if needed:
 *   python3 local-imports/build-jsonl.py
 */

// ── Must be run from CLI ─────────────────────────────────────────────────────
if ( php_sapi_name() !== 'cli' ) {
	die( "This script must be run from the command line.\n" );
}

// ── Parse arguments ──────────────────────────────────────────────────────────
$opts    = getopt( '', [ 'wp-path:', 'step:', 'confirm' ] );
$wp_path = rtrim( $opts['wp-path'] ?? '', '/' );
$step    = $opts['step'] ?? '';
$confirm = isset( $opts['confirm'] );

$valid_steps = [ 'backup', 'clear-dry', 'clear-execute', 'import-dry', 'import-execute' ];

if ( ! $wp_path ) {
	die( "ERROR: --wp-path is required.\nExample: --wp-path=/var/www/html\n" );
}
if ( ! in_array( $step, $valid_steps, true ) ) {
	die( "ERROR: --step must be one of: " . implode( ', ', $valid_steps ) . "\n" );
}
if ( in_array( $step, [ 'clear-execute', 'import-execute' ], true ) && ! $confirm ) {
	die( "ERROR: $step requires --confirm flag.\n" );
}

// ── Bootstrap WordPress ──────────────────────────────────────────────────────
$wp_load = $wp_path . '/wp-load.php';
if ( ! file_exists( $wp_load ) ) {
	die( "ERROR: wp-load.php not found at $wp_load\n" );
}

// Prevent any output buffering or HTTP headers from WP
$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
define( 'WP_USE_THEMES', false );

require $wp_load;

// ── Load importer class ──────────────────────────────────────────────────────
$importer_path = __DIR__ . '/../includes/cli/class-clickup-importer.php';
if ( ! file_exists( $importer_path ) ) {
	die( "ERROR: Importer class not found at $importer_path\n" );
}
require_once $importer_path;

// ── Paths ────────────────────────────────────────────────────────────────────
$repo_root   = dirname( __DIR__ );
$backup_path = $repo_root . '/backup/jobs-pre-clickup-import.json';
$jsonl_path  = $repo_root . '/local-imports/clickup-active-cleaned.jsonl';

// ── Execute step ─────────────────────────────────────────────────────────────

echo "\n=== Slate Ops ClickUp Import Runner ===\n";
echo "Step: $step" . ( $confirm ? ' [CONFIRMED]' : '' ) . "\n";
echo str_repeat( '-', 50 ) . "\n\n";

switch ( $step ) {

	// ── backup ───────────────────────────────────────────────────────────────
	case 'backup':
		echo "Exporting existing jobs to:\n  $backup_path\n\n";
		$result = Slate_Ops_ClickUp_Importer::backup( $backup_path );
		echo "DONE.\n";
		echo "  Jobs exported: {$result['count']}\n";
		echo "  File:          {$result['path']}\n\n";
		break;

	// ── clear-dry ────────────────────────────────────────────────────────────
	case 'clear-dry':
		echo "DRY-RUN — counting records (no changes made).\n\n";
		$result = Slate_Ops_ClickUp_Importer::clear_dry_run();
		echo "  Jobs:           {$result['jobs']}\n";
		echo "  Time segments:  {$result['time_segments']}\n";
		echo "  Audit entries:  {$result['audit_entries']}\n\n";
		echo "Run with --step=clear-execute --confirm to delete.\n\n";
		break;

	// ── clear-execute ────────────────────────────────────────────────────────
	case 'clear-execute':
		echo "EXECUTING CLEAR — deleting all jobs and related records.\n\n";
		$result = Slate_Ops_ClickUp_Importer::clear_execute( $confirm );
		if ( is_wp_error( $result ) ) {
			echo "ERROR: " . $result->get_error_message() . "\n\n";
			exit( 1 );
		}
		echo "DONE.\n";
		echo "  Jobs deleted:           {$result['jobs_deleted']}\n";
		echo "  Time segments deleted:  {$result['time_segments_deleted']}\n";
		echo "  Audit entries deleted:  {$result['audit_deleted']}\n\n";
		echo "Users, roles, settings, and work centers untouched.\n\n";
		break;

	// ── import-dry ───────────────────────────────────────────────────────────
	case 'import-dry':
		if ( ! file_exists( $jsonl_path ) ) {
			echo "ERROR: JSONL not found at $jsonl_path\n";
			echo "Run:  python3 local-imports/build-jsonl.py\n\n";
			exit( 1 );
		}
		echo "DRY-RUN — parsing JSONL (no changes made).\n";
		echo "JSONL: $jsonl_path\n\n";
		$result = Slate_Ops_ClickUp_Importer::import_dry_run( $jsonl_path );
		echo "  Would insert: {$result['would_insert']}\n";
		echo "  Would skip:   {$result['would_skip']}\n\n";

		echo "Preview of rows to insert:\n";
		echo str_repeat( '-', 50 ) . "\n";
		foreach ( $result['rows'] as $i => $row ) {
			$n    = $i + 1;
			$est  = $row['est_minutes'] ? ( $row['est_minutes'] / 60 ) . 'h' : '-';
			echo "  {$n}. [{$row['status']} / {$row['parts_status']}] {$row['customer_name']}\n";
			if ( $row['lead_tech'] ) echo "       Tech: {$row['lead_tech']}\n";
			echo "       Est: $est";
			if ( $row['scheduled_start'] ) echo "  Start: {$row['scheduled_start']}";
			if ( $row['scheduled_finish'] ) echo "  Due: {$row['scheduled_finish']}";
			echo "\n";
			if ( $row['notes'] ) echo "       Notes: {$row['notes']}\n";
		}

		if ( ! empty( $result['skipped'] ) ) {
			echo "\nSkipped rows:\n";
			foreach ( $result['skipped'] as $s ) {
				echo "  - {$s['customer_name']}: {$s['reason']}\n";
			}
		}
		echo "\nRun with --step=import-execute --confirm to insert.\n\n";
		break;

	// ── import-execute ───────────────────────────────────────────────────────
	case 'import-execute':
		if ( ! file_exists( $jsonl_path ) ) {
			echo "ERROR: JSONL not found at $jsonl_path\n";
			echo "Run:  python3 local-imports/build-jsonl.py\n\n";
			exit( 1 );
		}
		echo "EXECUTING IMPORT from:\n  $jsonl_path\n\n";
		$result = Slate_Ops_ClickUp_Importer::import_execute( $jsonl_path, $confirm );
		if ( is_wp_error( $result ) ) {
			echo "ERROR: " . $result->get_error_message() . "\n\n";
			exit( 1 );
		}
		echo "DONE.\n";
		echo "  Inserted: {$result['inserted']}\n";
		echo "  Skipped:  {$result['skipped']}\n\n";

		if ( ! empty( $result['errors'] ) ) {
			echo "Errors:\n";
			foreach ( $result['errors'] as $e ) {
				echo "  - {$e['customer_name']}: {$e['db_error']}\n";
			}
			echo "\n";
		}

		if ( ! empty( $result['skipped_rows'] ) ) {
			echo "Skipped rows:\n";
			foreach ( $result['skipped_rows'] as $s ) {
				echo "  - {$s['customer_name']}: {$s['reason']}\n";
			}
			echo "\n";
		}
		break;
}

echo "=== Done ===\n\n";
