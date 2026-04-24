<?php
/**
 * Slate_Ops_ClickUp_Importer
 *
 * One-time import tooling for the ClickUp → Slate Ops migration (issue #127).
 * Called exclusively by local-imports/run-import.php; never auto-loaded in
 * normal WordPress request handling.
 *
 * Supported steps:
 *   backup        — export all current jobs to a JSON file (non-destructive)
 *   clear-dry     — count jobs + time records that would be deleted
 *   clear-execute — delete all jobs and their time segments (requires $confirm)
 *   import-dry    — parse JSONL and report what would be inserted
 *   import-execute— insert rows from JSONL into the DB (requires $confirm)
 *
 * Safety constraints enforced here:
 *   - clear never touches users, roles, settings, or work_centers
 *   - clear deletes time_segments first (FK-safe order)
 *   - import writes source='clickup' so records can be distinguished
 *   - dry-run mode never writes anything
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Slate_Ops_ClickUp_Importer {

	// ── Status / parts maps ──────────────────────────────────────────────────

	private static $status_map = [
		'Delayed'                    => 'DELAYED',
		'Scheduled'                  => 'QUEUED',
		'In Progress'                => 'IN_PROGRESS',
		'Complete - Awaiting Pickup' => 'READY_FOR_PICKUP',
		'Complete'                   => 'COMPLETE',
		'On Hold'                    => 'ON_HOLD',
	];

	private static $parts_map = [
		'Ready'     => 'READY',
		'Partial'   => 'PARTIAL',
		'Not Ready' => 'NOT_READY',
	];

	// ── Step 1: Backup ───────────────────────────────────────────────────────

	/**
	 * Export every job row to $output_path as a JSON array.
	 *
	 * @param string $output_path  Absolute path for the output .json file.
	 * @return array{count:int, path:string}
	 */
	public static function backup( $output_path ) {
		global $wpdb;
		$t    = $wpdb->prefix . 'slate_ops_jobs';
		$jobs = $wpdb->get_results( "SELECT * FROM $t ORDER BY job_id ASC", ARRAY_A );

		$dir = dirname( $output_path );
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0755, true );
		}

		file_put_contents(
			$output_path,
			json_encode( $jobs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n"
		);

		return [
			'count' => count( $jobs ),
			'path'  => $output_path,
		];
	}

	// ── Step 2: Clear dry-run ────────────────────────────────────────────────

	/**
	 * Count jobs and related time segments without touching anything.
	 *
	 * @return array{jobs:int, time_segments:int}
	 */
	public static function clear_dry_run() {
		global $wpdb;
		$jobs_t  = $wpdb->prefix . 'slate_ops_jobs';
		$segs_t  = $wpdb->prefix . 'slate_ops_time_segments';
		$audit_t = $wpdb->prefix . 'slate_ops_audit_log';

		$job_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $jobs_t" );
		$seg_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $segs_t" );
		$audit_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $audit_t WHERE entity_type = 'job'" );

		return [
			'jobs'          => $job_count,
			'time_segments' => $seg_count,
			'audit_entries' => $audit_count,
		];
	}

	// ── Step 3: Clear execute ────────────────────────────────────────────────

	/**
	 * Delete all jobs, their time segments, and their audit log entries.
	 * Does NOT touch users, roles, settings, work_centers, or any non-job data.
	 *
	 * @param bool $confirm  Must be true or this is a no-op.
	 * @return array{jobs_deleted:int, time_segments_deleted:int, audit_deleted:int}|WP_Error
	 */
	public static function clear_execute( $confirm = false ) {
		if ( ! $confirm ) {
			return new WP_Error( 'confirm_required', 'Pass confirm=true to execute clear.' );
		}

		global $wpdb;
		$jobs_t  = $wpdb->prefix . 'slate_ops_jobs';
		$segs_t  = $wpdb->prefix . 'slate_ops_time_segments';
		$audit_t = $wpdb->prefix . 'slate_ops_audit_log';

		// Delete time segments first (no FK constraint but semantically correct)
		$segs_deleted  = (int) $wpdb->query( "DELETE FROM $segs_t" );
		$audit_deleted = (int) $wpdb->query( "DELETE FROM $audit_t WHERE entity_type = 'job'" );
		$jobs_deleted  = (int) $wpdb->query( "DELETE FROM $jobs_t" );

		return [
			'jobs_deleted'          => $jobs_deleted,
			'time_segments_deleted' => $segs_deleted,
			'audit_deleted'         => $audit_deleted,
		];
	}

	// ── Step 4a: Import dry-run ──────────────────────────────────────────────

	/**
	 * Parse the JSONL and report what would be inserted.
	 *
	 * @param string $jsonl_path  Absolute path to clickup-active-cleaned.jsonl.
	 * @return array{would_insert:int, would_skip:int, skipped:array, rows:array}
	 */
	public static function import_dry_run( $jsonl_path ) {
		[ $rows, $skipped ] = self::parse_jsonl( $jsonl_path );

		$preview = [];
		foreach ( $rows as $r ) {
			$preview[] = [
				'customer_name'  => $r['customer_name'],
				'status'         => self::$status_map[ $r['job_status'] ] ?? '?',
				'parts_status'   => self::$parts_map[ $r['parts_status'] ] ?? '?',
				'est_minutes'    => $r['est_hours'] ? (int) round( $r['est_hours'] * 60 ) : null,
				'scheduled_start'=> $r['start_date'] ?: null,
				'scheduled_finish'=> $r['due_date'] ?: null,
				'lead_tech'      => $r['lead_tech'],
				'notes'          => $r['notes'],
				'clickup_task_id'=> $r['source_task_id'],
			];
		}

		return [
			'would_insert' => count( $rows ),
			'would_skip'   => count( $skipped ),
			'skipped'      => $skipped,
			'rows'         => $preview,
		];
	}

	// ── Step 4b: Import execute ──────────────────────────────────────────────

	/**
	 * Insert rows from the JSONL into the jobs table.
	 *
	 * @param string $jsonl_path  Absolute path to clickup-active-cleaned.jsonl.
	 * @param bool   $confirm     Must be true or this is a no-op.
	 * @return array{inserted:int, skipped:int, skipped_rows:array, errors:array}|WP_Error
	 */
	public static function import_execute( $jsonl_path, $confirm = false ) {
		if ( ! $confirm ) {
			return new WP_Error( 'confirm_required', 'Pass confirm=true to execute import.' );
		}

		global $wpdb;
		$t   = $wpdb->prefix . 'slate_ops_jobs';
		$now = gmdate( 'Y-m-d H:i:s' );

		[ $rows, $skipped ] = self::parse_jsonl( $jsonl_path );

		$inserted = 0;
		$errors   = [];

		foreach ( $rows as $r ) {
			$status       = self::$status_map[ $r['job_status'] ]   ?? 'INTAKE';
			$parts_status = self::$parts_map[ $r['parts_status'] ]  ?? 'NOT_READY';

			// For DELAYED jobs, set a delay_reason so the UI can reflect it
			$delay_reason = ( $status === 'DELAYED' ) ? 'parts' : null;

			// Lead tech goes into schedule_notes (no user-ID lookup possible at import time)
			$schedule_notes = $r['lead_tech'] ? 'Lead Tech: ' . $r['lead_tech'] : null;

			$row = [
				// Source tracking
				'source'             => 'clickup',
				'created_from'       => 'clickup',

				// ClickUp linkage
				'clickup_task_id'    => $r['source_task_id'] ?: null,

				// Identifiers — SO# and VIN are absent from ClickUp export
				'so_number'          => ( $r['so_number'] !== '' ) ? $r['so_number'] : null,
				'customer_name'      => $r['customer_name'],
				'vin'                => ( $r['vin'] !== '' ) ? $r['vin'] : null,
				'vin_last8'          => ( $r['vin'] !== '' ) ? substr( $r['vin'], -8 ) : null,
				'dealer_name'        => ( $r['dealer'] !== '' ) ? $r['dealer'] : null,
				'sales_person'       => ( $r['sales_person'] !== '' ) ? $r['sales_person'] : null,

				// Classification
				'job_type'           => 'UPFIT',
				'parts_status'       => $parts_status,

				// Status
				'status'             => $status,
				'status_updated_at'  => $now,
				'delay_reason'       => $delay_reason,

				// Scheduling
				'priority'           => 3,
				'estimated_minutes'  => $r['est_hours'] ? (int) round( $r['est_hours'] * 60 ) : null,
				'scheduled_start'    => ( $r['start_date'] !== '' ) ? $r['start_date'] : null,
				'scheduled_finish'   => ( $r['due_date'] !== '' ) ? $r['due_date'] : null,

				// Notes
				'notes'              => ( $r['notes'] !== '' ) ? $r['notes'] : null,
				'schedule_notes'     => $schedule_notes,

				// Defaults
				'dealer_status'      => 'waiting',
				'created_by'         => 0,
				'created_at'         => $now,
				'updated_at'         => $now,
			];

			$result = $wpdb->insert( $t, $row );
			if ( $result === false ) {
				$errors[] = [
					'customer_name' => $r['customer_name'],
					'db_error'      => $wpdb->last_error,
				];
			} else {
				$job_id = (int) $wpdb->insert_id;

				// Write minimal audit entry so the job shows up in activity log
				$wpdb->insert(
					$wpdb->prefix . 'slate_ops_audit_log',
					[
						'entity_type' => 'job',
						'entity_id'   => $job_id,
						'action'      => 'import',
						'new_value'   => wp_json_encode( [ 'source' => 'clickup', 'task_id' => $r['source_task_id'] ] ),
						'note'        => 'Imported from ClickUp (issue #127)',
						'user_id'     => 0,
						'created_at'  => $now,
					]
				);

				$inserted++;
			}
		}

		return [
			'inserted'     => $inserted,
			'skipped'      => count( $skipped ),
			'skipped_rows' => $skipped,
			'errors'       => $errors,
		];
	}

	// ── Private helpers ──────────────────────────────────────────────────────

	/**
	 * Parse the JSONL file.  Returns [ $valid_rows, $skipped_rows ].
	 * Applies the same skip rules as build-jsonl.py (customer_name AND so_number both absent).
	 */
	private static function parse_jsonl( $jsonl_path ) {
		if ( ! file_exists( $jsonl_path ) ) {
			return new WP_Error( 'file_missing', "JSONL not found: $jsonl_path" );
		}

		$valid   = [];
		$skipped = [];

		foreach ( file( $jsonl_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
			$r = json_decode( $line, true );
			if ( ! is_array( $r ) ) {
				$skipped[] = [ 'line' => $line, 'reason' => 'JSON parse error' ];
				continue;
			}

			// Only import active lane
			if ( ( $r['import_lane'] ?? '' ) !== 'active' ) {
				$skipped[] = [ 'customer_name' => $r['customer_name'] ?? '', 'reason' => 'Not active lane' ];
				continue;
			}

			// Must have at least customer_name or so_number
			$customer = trim( $r['customer_name'] ?? '' );
			$so       = trim( $r['so_number'] ?? '' );
			if ( $customer === '' && $so === '' ) {
				$skipped[] = [ 'customer_name' => '(blank)', 'reason' => 'No identifier (customer_name + so_number both empty)' ];
				continue;
			}

			$valid[] = $r;
		}

		return [ $valid, $skipped ];
	}
}
