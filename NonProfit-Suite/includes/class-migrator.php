<?php
/**
 * Database Migration System
 *
 * Tracks and manages database schema changes across plugin versions.
 * Ensures safe, versioned, and rollback-capable database updates.
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Migrator {

	/**
	 * Migrations table name (without prefix).
	 *
	 * @var string
	 */
	const MIGRATIONS_TABLE = 'ns_migrations';

	/**
	 * Option name for database version.
	 *
	 * @var string
	 */
	const VERSION_OPTION = 'nonprofitsuite_db_version';

	/**
	 * Initialize migrations table.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function init() {
		global $wpdb;

		$table_name = $wpdb->prefix . self::MIGRATIONS_TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			version varchar(20) NOT NULL,
			migration varchar(255) NOT NULL,
			batch int(11) NOT NULL,
			executed_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY version (version),
			KEY migration (migration)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		return true;
	}

	/**
	 * Get current database version.
	 *
	 * @return string Current version or '0.0.0' if not set.
	 */
	public static function get_current_version() {
		return get_option( self::VERSION_OPTION, '0.0.0' );
	}

	/**
	 * Set database version.
	 *
	 * @param string $version Version to set.
	 * @return bool True on success.
	 */
	public static function set_version( $version ) {
		return update_option( self::VERSION_OPTION, $version );
	}

	/**
	 * Get all executed migrations.
	 *
	 * @return array Array of migration records.
	 */
	public static function get_executed_migrations() {
		global $wpdb;

		$table = $wpdb->prefix . self::MIGRATIONS_TABLE;

		return $wpdb->get_results(
			"SELECT * FROM {$table} ORDER BY id ASC",
			ARRAY_A
		);
	}

	/**
	 * Check if a migration has been executed.
	 *
	 * @param string $migration Migration name.
	 * @return bool True if executed, false otherwise.
	 */
	public static function has_run( $migration ) {
		global $wpdb;

		$table = $wpdb->prefix . self::MIGRATIONS_TABLE;

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE migration = %s",
			$migration
		) );

		return $count > 0;
	}

	/**
	 * Record a migration as executed.
	 *
	 * @param string $version   Version number.
	 * @param string $migration Migration name.
	 * @param int    $batch     Batch number.
	 * @return bool True on success, false on failure.
	 */
	public static function record_migration( $version, $migration, $batch ) {
		global $wpdb;

		$table = $wpdb->prefix . self::MIGRATIONS_TABLE;

		$result = $wpdb->insert(
			$table,
			array(
				'version'   => $version,
				'migration' => $migration,
				'batch'     => $batch,
			),
			array( '%s', '%s', '%d' )
		);

		return $result !== false;
	}

	/**
	 * Run all pending migrations.
	 *
	 * @param array $migrations Array of migration definitions.
	 * @return array Results of migration execution.
	 */
	public static function run( $migrations = array() ) {
		$current_version = self::get_current_version();
		$results = array(
			'success' => array(),
			'failed'  => array(),
			'skipped' => array(),
		);

		// Get next batch number
		$batch = self::get_next_batch_number();

		foreach ( $migrations as $migration ) {
			// Skip if already executed
			if ( self::has_run( $migration['name'] ) ) {
				$results['skipped'][] = $migration['name'];
				continue;
			}

			// Skip if version requirement not met
			if ( isset( $migration['min_version'] ) && version_compare( $current_version, $migration['min_version'], '<' ) ) {
				$results['skipped'][] = $migration['name'];
				continue;
			}

			// Execute migration
			try {
				if ( isset( $migration['callback'] ) && is_callable( $migration['callback'] ) ) {
					call_user_func( $migration['callback'] );

					// Record successful migration
					self::record_migration( $migration['version'], $migration['name'], $batch );
					self::set_version( $migration['version'] );

					$results['success'][] = $migration['name'];
				} else {
					$results['failed'][] = array(
						'name'  => $migration['name'],
						'error' => 'Callback not callable',
					);
				}
			} catch ( Exception $e ) {
				$results['failed'][] = array(
					'name'  => $migration['name'],
					'error' => $e->getMessage(),
				);
			}
		}

		return $results;
	}

	/**
	 * Rollback last batch of migrations.
	 *
	 * @param int $steps Number of batches to rollback (default 1).
	 * @return array Results of rollback.
	 */
	public static function rollback( $steps = 1 ) {
		global $wpdb;

		$table = $wpdb->prefix . self::MIGRATIONS_TABLE;
		$results = array(
			'success' => array(),
			'failed'  => array(),
		);

		// Get last N batches
		$last_batch = $wpdb->get_var( "SELECT MAX(batch) FROM {$table}" );
		$target_batch = max( 0, $last_batch - $steps );

		// Get migrations to rollback
		$migrations = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE batch > %d ORDER BY id DESC",
			$target_batch
		) );

		foreach ( $migrations as $migration ) {
			// Attempt to rollback (if rollback callback exists)
			// For now, just remove from tracking table
			$deleted = $wpdb->delete(
				$table,
				array( 'id' => $migration->id ),
				array( '%d' )
			);

			if ( $deleted ) {
				$results['success'][] = $migration->migration;
			} else {
				$results['failed'][] = $migration->migration;
			}
		}

		// Update version to previous
		if ( ! empty( $results['success'] ) ) {
			$previous_version = $wpdb->get_var(
				"SELECT version FROM {$table} ORDER BY id DESC LIMIT 1"
			);
			if ( $previous_version ) {
				self::set_version( $previous_version );
			}
		}

		return $results;
	}

	/**
	 * Get next batch number.
	 *
	 * @return int Next batch number.
	 */
	private static function get_next_batch_number() {
		global $wpdb;

		$table = $wpdb->prefix . self::MIGRATIONS_TABLE;

		$max_batch = $wpdb->get_var( "SELECT MAX(batch) FROM {$table}" );

		return (int) $max_batch + 1;
	}

	/**
	 * Get migration status summary.
	 *
	 * @return array Summary of migration status.
	 */
	public static function get_status() {
		global $wpdb;

		$table = $wpdb->prefix . self::MIGRATIONS_TABLE;

		$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$last_batch = $wpdb->get_var( "SELECT MAX(batch) FROM {$table}" );
		$last_migration = $wpdb->get_row(
			"SELECT * FROM {$table} ORDER BY id DESC LIMIT 1"
		);

		return array(
			'current_version' => self::get_current_version(),
			'total_migrations' => (int) $total,
			'last_batch' => (int) $last_batch,
			'last_migration' => $last_migration ? array(
				'name' => $last_migration->migration,
				'version' => $last_migration->version,
				'executed_at' => $last_migration->executed_at,
			) : null,
		);
	}

	/**
	 * Reset all migrations (DANGEROUS - for development only).
	 *
	 * @return bool True on success.
	 */
	public static function reset() {
		global $wpdb;

		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return false; // Only allow in debug mode
		}

		$table = $wpdb->prefix . self::MIGRATIONS_TABLE;

		$wpdb->query( "TRUNCATE TABLE {$table}" );
		delete_option( self::VERSION_OPTION );

		return true;
	}

	/**
	 * Check for pending migrations and run them automatically.
	 *
	 * This should be called on plugin activation and updates.
	 *
	 * @return array|WP_Error Results or error.
	 */
	public static function check_and_run() {
		// Initialize migrations table if needed
		self::init();

		$current_db_version = self::get_current_version();
		$plugin_version = defined( 'NONPROFITSUITE_VERSION' ) ? NONPROFITSUITE_VERSION : '1.0.0';

		// No migrations needed if DB is up to date
		if ( version_compare( $current_db_version, $plugin_version, '>=' ) ) {
			return array(
				'status' => 'up_to_date',
				'current_version' => $current_db_version,
				'plugin_version' => $plugin_version,
			);
		}

		// Get all defined migrations
		$migrations = self::get_migrations();

		// Run pending migrations
		$results = self::run( $migrations );

		// Log results if any migrations ran
		if ( ! empty( $results['success'] ) || ! empty( $results['failed'] ) ) {
			error_log( sprintf(
				'NonProfit-Suite Migrations: %d succeeded, %d failed, %d skipped',
				count( $results['success'] ),
				count( $results['failed'] ),
				count( $results['skipped'] )
			) );

			if ( ! empty( $results['failed'] ) ) {
				error_log( 'Failed migrations: ' . print_r( $results['failed'], true ) );
				return new WP_Error(
					'migration_failed',
					__( 'Some database migrations failed. Check error log for details.', 'nonprofitsuite' ),
					$results
				);
			}
		}

		return $results;
	}

	/**
	 * Get all defined migrations.
	 *
	 * Migrations are executed in order. Each migration should be idempotent
	 * and check if the change is already applied before executing.
	 *
	 * @return array Array of migration definitions.
	 */
	public static function get_migrations() {
		return array(
			// Migration 001: Fix CPA Shared Files table schema
			array(
				'name'        => '001_fix_cpa_shared_files_schema',
				'version'     => '1.1.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$table = $wpdb->prefix . 'ns_cpa_shared_files';

					// Check if old column names exist
					$columns = $wpdb->get_col( "DESC {$table}", 0 );

					// Rename columns if they exist
					if ( in_array( 'cpa_access_id', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} CHANGE COLUMN cpa_access_id access_id bigint(20) unsigned NOT NULL" );
					}
					if ( in_array( 'file_category', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} CHANGE COLUMN file_category category varchar(100) DEFAULT NULL" );
					}
					if ( in_array( 'file_description', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} CHANGE COLUMN file_description description text DEFAULT NULL" );
					}

					// Add new columns if they don't exist
					if ( ! in_array( 'file_name', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN file_name varchar(255) NOT NULL AFTER access_id" );
					}
					if ( ! in_array( 'file_type', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN file_type varchar(50) NOT NULL AFTER file_name" );
					}
					if ( ! in_array( 'file_size', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN file_size bigint(20) DEFAULT 0 AFTER file_path" );
					}
					if ( ! in_array( 'shared_by', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN shared_by bigint(20) unsigned NOT NULL AFTER description" );
					}
					if ( ! in_array( 'download_count', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN download_count int(11) DEFAULT 0 AFTER shared_date" );
					}
					if ( ! in_array( 'last_downloaded', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN last_downloaded datetime DEFAULT NULL AFTER download_count" );
					}

					// Add composite index if it doesn't exist
					$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'idx_access_category'" );
					if ( empty( $indexes ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_access_category (access_id, category)" );
					}
				},
			),

			// Migration 002: Add missing index to wealth indicators
			array(
				'name'        => '002_add_wealth_indicators_index',
				'version'     => '1.1.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$table = $wpdb->prefix . 'ns_wealth_indicators';

					// Add composite index if it doesn't exist
					$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'idx_prospect_date'" );
					if ( empty( $indexes ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_prospect_date (prospect_id, date_found)" );
					}
				},
			),

			// Migration 003: Fix CPA Access table schema
			array(
				'name'        => '003_fix_cpa_access_schema',
				'version'     => '1.1.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$table = $wpdb->prefix . 'ns_cpa_access';

					// Check if old column names exist
					$columns = $wpdb->get_col( "DESC {$table}", 0 );

					// Add new columns if they don't exist
					if ( ! in_array( 'firm_name', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN firm_name varchar(255) NOT NULL AFTER user_id" );
					}
					if ( ! in_array( 'contact_name', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN contact_name varchar(255) NOT NULL AFTER firm_name" );
					}
					if ( ! in_array( 'contact_email', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN contact_email varchar(255) NOT NULL AFTER contact_name" );
					}
					if ( ! in_array( 'contact_phone', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN contact_phone varchar(50) DEFAULT NULL AFTER contact_email" );
					}
					if ( ! in_array( 'granted_date', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN granted_date datetime DEFAULT CURRENT_TIMESTAMP AFTER access_level" );
					}
					if ( ! in_array( 'expiration_date', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN expiration_date datetime DEFAULT NULL AFTER granted_date" );
					}
					if ( ! in_array( 'revoked_date', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN revoked_date datetime DEFAULT NULL AFTER notes" );
					}
				},
			),

			// Migration 004: Fix Legal Access table schema
			array(
				'name'        => '004_fix_legal_access_schema',
				'version'     => '1.1.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$table = $wpdb->prefix . 'ns_legal_access';

					// Check if old column names exist
					$columns = $wpdb->get_col( "DESC {$table}", 0 );

					// Add new columns if they don't exist
					if ( ! in_array( 'firm_name', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN firm_name varchar(255) NOT NULL AFTER user_id" );
					}
					if ( ! in_array( 'attorney_email', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN attorney_email varchar(255) NOT NULL AFTER attorney_name" );
					}
					if ( ! in_array( 'attorney_phone', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN attorney_phone varchar(50) DEFAULT NULL AFTER attorney_email" );
					}
					if ( ! in_array( 'bar_number', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN bar_number varchar(100) DEFAULT NULL AFTER attorney_phone" );
					}
					if ( ! in_array( 'specialization', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN specialization varchar(255) DEFAULT NULL AFTER bar_number" );
					}
					if ( ! in_array( 'granted_date', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN granted_date datetime DEFAULT CURRENT_TIMESTAMP AFTER access_level" );
					}
					if ( ! in_array( 'expiration_date', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN expiration_date datetime DEFAULT NULL AFTER granted_date" );
					}
					if ( ! in_array( 'revoked_date', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN revoked_date datetime DEFAULT NULL AFTER notes" );
					}
				},
			),

			// Migration 005: Add composite indexes for common query patterns
			array(
				'name'        => '005_add_composite_indexes',
				'version'     => '1.2.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;

					// Donors: status + level (common filter combination)
					$table = $wpdb->prefix . 'ns_donors';
					$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'idx_donor_status_level'" );
					if ( empty( $indexes ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_donor_status_level (donor_status, donor_level)" );
					}

					// Transactions: account + date (common reporting query)
					$table = $wpdb->prefix . 'ns_transactions';
					$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'idx_account_date'" );
					if ( empty( $indexes ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_account_date (account_id, transaction_date)" );
					}

					// Attendance: meeting + status (join table performance)
					$table = $wpdb->prefix . 'ns_attendance';
					$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'idx_meeting_status'" );
					if ( empty( $indexes ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_meeting_status (meeting_id, status)" );
					}

					// Tasks: assignee + status + due date (dashboard/list queries)
					$table = $wpdb->prefix . 'ns_tasks';
					$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'idx_assigned_status_due'" );
					if ( empty( $indexes ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_assigned_status_due (assigned_to, status, due_date)" );
					}

					// Grants: deadline + status (covering index for dashboard)
					$table = $wpdb->prefix . 'ns_grants';
					$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'idx_deadline_status'" );
					if ( empty( $indexes ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_deadline_status (application_deadline, status)" );
					}

					// Donations: donor + date (common reporting query)
					$table = $wpdb->prefix . 'ns_donations';
					$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'idx_donor_date'" );
					if ( empty( $indexes ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_donor_date (donor_id, donation_date)" );
					}

					// Volunteer hours: volunteer + date (reporting query)
					$table = $wpdb->prefix . 'ns_volunteer_hours';
					$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'idx_volunteer_date'" );
					if ( empty( $indexes ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_volunteer_date (volunteer_id, activity_date)" );
					}
				},
			),

			// Migration 006: Add document retention policy fields
			array(
				'name'        => '006_add_document_retention_fields',
				'version'     => '1.3.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$table = $wpdb->prefix . 'ns_documents';
					$charset_collate = $wpdb->get_charset_collate();

					// Check existing columns
					$columns = $wpdb->get_col( "DESC {$table}", 0 );

					// Add retention policy fields
					if ( ! in_array( 'is_archived', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN is_archived tinyint(1) DEFAULT 0 AFTER access_level" );
					}
					if ( ! in_array( 'archived_at', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN archived_at datetime DEFAULT NULL AFTER is_archived" );
					}
					if ( ! in_array( 'retention_policy', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN retention_policy varchar(50) DEFAULT 'standard' AFTER archived_at" );
					}
					if ( ! in_array( 'expiration_date', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN expiration_date datetime DEFAULT NULL AFTER retention_policy" );
					}
					if ( ! in_array( 'is_expired', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN is_expired tinyint(1) DEFAULT 0 AFTER expiration_date" );
					}

					// Add indexes for performance
					$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'idx_archived_status'" );
					if ( empty( $indexes ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_archived_status (is_archived, is_expired)" );
					}

					$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'idx_retention_policy'" );
					if ( empty( $indexes ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_retention_policy (retention_policy)" );
					}

					// Create retention policies table
					$policies_table = $wpdb->prefix . 'ns_retention_policies';
					$sql = "CREATE TABLE IF NOT EXISTS {$policies_table} (
						id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
						policy_name varchar(255) NOT NULL,
						policy_key varchar(50) NOT NULL,
						document_categories text DEFAULT NULL COMMENT 'JSON array of applicable categories',
						retention_years int DEFAULT 0 COMMENT '0 means keep forever',
						auto_archive_after_days int DEFAULT 365,
						description text DEFAULT NULL,
						is_active tinyint(1) DEFAULT 1,
						created_at datetime DEFAULT CURRENT_TIMESTAMP,
						updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
						PRIMARY KEY (id),
						UNIQUE KEY policy_key (policy_key),
						KEY is_active (is_active)
					) {$charset_collate};";

					require_once ABSPATH . 'wp-admin/includes/upgrade.php';
					dbDelta( $sql );

					// Insert default retention policies
					$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$policies_table}" );
					if ( $count == 0 ) {
						// Published/Public Documents - Keep Forever
						$wpdb->insert(
							$policies_table,
							array(
								'policy_name' => 'Published Documents',
								'policy_key' => 'published',
								'document_categories' => wp_json_encode( array( 'board', 'financial', 'policies', 'legal' ) ),
								'retention_years' => 0,
								'auto_archive_after_days' => 365,
								'description' => 'Published or public documents are retained permanently in archives. These documents remain accessible but are not prominently displayed after archival.',
								'is_active' => 1,
							),
							array( '%s', '%s', '%s', '%d', '%d', '%s', '%d' )
						);

						// Work Products - 7 years default
						$wpdb->insert(
							$policies_table,
							array(
								'policy_name' => 'Work Products',
								'policy_key' => 'work_products',
								'document_categories' => wp_json_encode( array( 'committee', 'programs', 'grants' ) ),
								'retention_years' => 7,
								'auto_archive_after_days' => 365,
								'description' => 'Work products and project documents are archived after 1 year and may expire after the retention period set by policy.',
								'is_active' => 1,
							),
							array( '%s', '%s', '%s', '%d', '%d', '%s', '%d' )
						);

						// Notes and Drafts - 3 years default
						$wpdb->insert(
							$policies_table,
							array(
								'policy_name' => 'Notes and Drafts',
								'policy_key' => 'notes',
								'document_categories' => wp_json_encode( array( 'other' ) ),
								'retention_years' => 3,
								'auto_archive_after_days' => 180,
								'description' => 'Notes, drafts, and other temporary documents are archived after 6 months and may expire after the retention period.',
								'is_active' => 1,
							),
							array( '%s', '%s', '%s', '%d', '%d', '%s', '%d' )
						);
					}
				},
			),

			// Migration 007: Enhance calendar events with relationship fields
			array(
				'name'        => '007_add_calendar_relationship_fields',
				'version'     => '1.4.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$table = $wpdb->prefix . 'ns_calendar_events';
					$charset_collate = $wpdb->get_charset_collate();

					// Check existing columns
					$columns = $wpdb->get_col( "DESC {$table}", 0 );

					// Add entity relationship fields
					if ( ! in_array( 'entity_type', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN entity_type varchar(50) DEFAULT NULL AFTER id" );
					}
					if ( ! in_array( 'entity_id', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN entity_id bigint(20) unsigned DEFAULT NULL AFTER entity_type" );
					}

					// Add calendar category
					if ( ! in_array( 'calendar_category', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN calendar_category varchar(50) DEFAULT 'general' AFTER entity_id" );
					}

					// Add date fields for tasks/projects
					if ( ! in_array( 'proposed_date', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN proposed_date datetime DEFAULT NULL AFTER end_datetime" );
					}
					if ( ! in_array( 'assigned_date', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN assigned_date datetime DEFAULT NULL AFTER proposed_date" );
					}
					if ( ! in_array( 'due_date', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN due_date datetime DEFAULT NULL AFTER assigned_date" );
					}
					if ( ! in_array( 'completed_date', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN completed_date datetime DEFAULT NULL AFTER due_date" );
					}

					// Add relationship fields
					if ( ! in_array( 'assigner_id', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN assigner_id bigint(20) unsigned DEFAULT NULL AFTER created_by" );
					}
					if ( ! in_array( 'assignee_id', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN assignee_id bigint(20) unsigned DEFAULT NULL AFTER assigner_id" );
					}
					if ( ! in_array( 'source_committee_id', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN source_committee_id bigint(20) unsigned DEFAULT NULL AFTER assignee_id" );
					}
					if ( ! in_array( 'target_committee_id', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN target_committee_id bigint(20) unsigned DEFAULT NULL AFTER source_committee_id" );
					}
					if ( ! in_array( 'related_users', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN related_users longtext DEFAULT NULL AFTER target_committee_id COMMENT 'JSON array of user IDs'" );
					}

					// Add visibility tracking (auto-calculated)
					if ( ! in_array( 'visible_on_calendars', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN visible_on_calendars longtext DEFAULT NULL AFTER related_users COMMENT 'JSON array of calendar identifiers'" );
					}

					// Add provider sync tracking
					if ( ! in_array( 'pushed_to_providers', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN pushed_to_providers longtext DEFAULT NULL AFTER visible_on_calendars COMMENT 'JSON object mapping provider_calendar to external IDs'" );
					}
					if ( ! in_array( 'last_pushed_at', $columns, true ) ) {
						$wpdb->query( "ALTER TABLE {$table} ADD COLUMN last_pushed_at datetime DEFAULT NULL AFTER pushed_to_providers" );
					}

					// Add indexes for performance
					$wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_entity (entity_type, entity_id)" );
					$wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_calendar_category (calendar_category)" );
					$wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_assignee (assignee_id)" );
					$wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_due_date (due_date)" );
				},
			),

			// Migration 008: Create user calendar preferences table
			array(
				'name'        => '008_create_user_calendar_prefs',
				'version'     => '1.4.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$table = $wpdb->prefix . 'ns_user_calendar_prefs';
					$charset_collate = $wpdb->get_charset_collate();

					$sql = "CREATE TABLE IF NOT EXISTS {$table} (
						id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
						user_id bigint(20) unsigned NOT NULL,
						preferred_provider varchar(50) DEFAULT 'builtin',
						provider_settings longtext DEFAULT NULL COMMENT 'JSON object with provider-specific settings',
						auto_sync_enabled tinyint(1) DEFAULT 1,
						sync_categories longtext DEFAULT NULL COMMENT 'JSON array of calendar categories to sync',
						calendar_color varchar(20) DEFAULT NULL,
						timezone varchar(100) DEFAULT NULL,
						created_at datetime DEFAULT CURRENT_TIMESTAMP,
						updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
						PRIMARY KEY (id),
						UNIQUE KEY user_id (user_id)
					) {$charset_collate};";

					require_once ABSPATH . 'wp-admin/includes/upgrade.php';
					dbDelta( $sql );
				},
			),

			// Migration 009: Create calendar event reminders table
			array(
				'name'        => '009_create_calendar_event_reminders',
				'version'     => '1.5.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$table = $wpdb->prefix . 'ns_calendar_event_reminders';
					$charset_collate = $wpdb->get_charset_collate();

					$sql = "CREATE TABLE IF NOT EXISTS {$table} (
						id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
						event_id bigint(20) unsigned NOT NULL,
						reminder_offset int(11) NOT NULL COMMENT 'Minutes before event',
						reminder_type varchar(20) DEFAULT 'email' COMMENT 'email, push, sms, in_app',
						reminder_method varchar(50) DEFAULT 'notification' COMMENT 'notification, digest, custom',
						recipient_user_id bigint(20) unsigned DEFAULT NULL,
						recipient_email varchar(100) DEFAULT NULL,
						recipient_phone varchar(50) DEFAULT NULL,
						reminder_status varchar(20) DEFAULT 'pending' COMMENT 'pending, sent, failed, cancelled',
						scheduled_for datetime DEFAULT NULL COMMENT 'Calculated send time',
						sent_at datetime DEFAULT NULL,
						error_message text DEFAULT NULL,
						retry_count int(11) DEFAULT 0,
						custom_message text DEFAULT NULL,
						created_at datetime DEFAULT CURRENT_TIMESTAMP,
						PRIMARY KEY (id),
						KEY event_id (event_id),
						KEY reminder_status (reminder_status),
						KEY scheduled_for (scheduled_for),
						KEY recipient_user_id (recipient_user_id)
					) {$charset_collate};";

					require_once ABSPATH . 'wp-admin/includes/upgrade.php';
					dbDelta( $sql );
				},
			),

			// Migration 010: Create work schedules table
			array(
				'name'        => '010_create_work_schedules',
				'version'     => '1.5.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$table = $wpdb->prefix . 'ns_work_schedules';
					$charset_collate = $wpdb->get_charset_collate();

					$sql = "CREATE TABLE IF NOT EXISTS {$table} (
						id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
						user_id bigint(20) unsigned NOT NULL,
						schedule_type varchar(20) DEFAULT 'shift' COMMENT 'shift, availability, time_off, volunteer_shift',
						shift_name varchar(100) DEFAULT NULL,
						role varchar(100) DEFAULT NULL COMMENT 'For volunteer shifts: booth_attendant, greeter, etc.',
						event_id bigint(20) unsigned DEFAULT NULL COMMENT 'Link to calendar event if applicable',
						day_of_week int(1) DEFAULT NULL COMMENT '0-6 for recurring weekly schedules',
						start_date date DEFAULT NULL COMMENT 'For one-time or date range schedules',
						end_date date DEFAULT NULL,
						start_time time NOT NULL,
						end_time time NOT NULL,
						is_recurring tinyint(1) DEFAULT 0,
						recurrence_pattern longtext DEFAULT NULL COMMENT 'JSON: daily, weekly, monthly pattern',
						positions_needed int(11) DEFAULT 1 COMMENT 'For volunteer shifts: how many people needed',
						positions_filled int(11) DEFAULT 0 COMMENT 'How many people signed up',
						is_published tinyint(1) DEFAULT 1 COMMENT 'For volunteer shifts: visible to volunteers',
						notes text DEFAULT NULL,
						created_by bigint(20) unsigned DEFAULT NULL,
						created_at datetime DEFAULT CURRENT_TIMESTAMP,
						updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
						PRIMARY KEY (id),
						KEY user_id (user_id),
						KEY schedule_type (schedule_type),
						KEY event_id (event_id),
						KEY start_date (start_date),
						KEY day_of_week (day_of_week)
					) {$charset_collate};";

					require_once ABSPATH . 'wp-admin/includes/upgrade.php';
					dbDelta( $sql );
				},
			),

			// Migration 011: Create time entries table
			array(
				'name'        => '011_create_time_entries',
				'version'     => '1.5.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$table = $wpdb->prefix . 'ns_time_entries';
					$charset_collate = $wpdb->get_charset_collate();

					$sql = "CREATE TABLE IF NOT EXISTS {$table} (
						id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
						user_id bigint(20) unsigned NOT NULL,
						entry_type varchar(20) DEFAULT 'work' COMMENT 'work, volunteer, meeting, event',
						calendar_event_id bigint(20) unsigned DEFAULT NULL,
						work_schedule_id bigint(20) unsigned DEFAULT NULL,
						project_id bigint(20) unsigned DEFAULT NULL,
						task_id bigint(20) unsigned DEFAULT NULL,
						start_datetime datetime NOT NULL,
						end_datetime datetime DEFAULT NULL COMMENT 'NULL if still clocked in',
						duration_minutes int(11) DEFAULT NULL COMMENT 'Calculated when clocked out',
						break_minutes int(11) DEFAULT 0,
						description text DEFAULT NULL,
						location varchar(255) DEFAULT NULL,
						is_billable tinyint(1) DEFAULT 0,
						hourly_rate decimal(10,2) DEFAULT NULL,
						total_amount decimal(10,2) DEFAULT NULL COMMENT 'Calculated: duration * rate',
						status varchar(20) DEFAULT 'draft' COMMENT 'draft, submitted, approved, rejected, paid',
						submitted_at datetime DEFAULT NULL,
						approved_by bigint(20) unsigned DEFAULT NULL,
						approved_at datetime DEFAULT NULL,
						rejection_reason text DEFAULT NULL,
						paid_at datetime DEFAULT NULL,
						created_at datetime DEFAULT CURRENT_TIMESTAMP,
						updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
						PRIMARY KEY (id),
						KEY user_id (user_id),
						KEY entry_type (entry_type),
						KEY calendar_event_id (calendar_event_id),
						KEY work_schedule_id (work_schedule_id),
						KEY status (status),
						KEY start_datetime (start_datetime)
					) {$charset_collate};";

					require_once ABSPATH . 'wp-admin/includes/upgrade.php';
					dbDelta( $sql );
				},
			),

			// Migration 012: Create email addresses table
			array(
				'name'        => '012_create_email_addresses',
				'version'     => '1.6.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$table = $wpdb->prefix . 'ns_email_addresses';
					$charset_collate = $wpdb->get_charset_collate();

					$sql = "CREATE TABLE IF NOT EXISTS {$table} (
						id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
						email_address varchar(255) NOT NULL COMMENT 'Full email address (e.g., president@org.org)',
						local_part varchar(255) NOT NULL COMMENT 'Part before @ (e.g., president)',
						address_type varchar(20) DEFAULT 'role' COMMENT 'role, group, functional, ai, personal',
						role_id bigint(20) unsigned DEFAULT NULL COMMENT 'Links to position/role',
						group_type varchar(50) DEFAULT NULL COMMENT 'board, committee, staff, volunteers',
						functional_category varchar(50) DEFAULT NULL COMMENT 'irs, casos, legal, filing',
						forward_to_users longtext DEFAULT NULL COMMENT 'JSON array of user IDs to forward to',
						archive_category varchar(50) DEFAULT NULL COMMENT 'Category for archiving incoming mail',
						auto_reply_enabled tinyint(1) DEFAULT 0,
						auto_reply_template_id bigint(20) unsigned DEFAULT NULL,
						trigger_automations tinyint(1) DEFAULT 0 COMMENT 'Should incoming email trigger automation?',
						automation_rules longtext DEFAULT NULL COMMENT 'JSON: conditions and actions',
						is_active tinyint(1) DEFAULT 1,
						notes text DEFAULT NULL,
						created_at datetime DEFAULT CURRENT_TIMESTAMP,
						updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
						PRIMARY KEY (id),
						UNIQUE KEY email_address (email_address),
						KEY address_type (address_type),
						KEY role_id (role_id),
						KEY is_active (is_active)
					) {$charset_collate};";

					require_once ABSPATH . 'wp-admin/includes/upgrade.php';
					dbDelta( $sql );
				},
			),

			// Migration 013: Create email log table
			array(
				'name'        => '013_create_email_log',
				'version'     => '1.6.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$table = $wpdb->prefix . 'ns_email_log';
					$charset_collate = $wpdb->get_charset_collate();

					$sql = "CREATE TABLE IF NOT EXISTS {$table} (
						id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
						direction varchar(10) DEFAULT 'outbound' COMMENT 'outbound, inbound',
						from_address varchar(255) DEFAULT NULL,
						to_addresses longtext DEFAULT NULL COMMENT 'JSON array of recipients',
						cc_addresses longtext DEFAULT NULL COMMENT 'JSON array',
						bcc_addresses longtext DEFAULT NULL COMMENT 'JSON array',
						subject varchar(500) DEFAULT NULL,
						body_text longtext DEFAULT NULL,
						body_html longtext DEFAULT NULL,
						attachments longtext DEFAULT NULL COMMENT 'JSON: file paths and metadata',
						email_address_id bigint(20) unsigned DEFAULT NULL COMMENT 'Which org email this relates to',
						archive_category varchar(50) DEFAULT NULL,
						related_entity_type varchar(50) DEFAULT NULL COMMENT 'task, meeting, filing, project',
						related_entity_id bigint(20) unsigned DEFAULT NULL,
						adapter varchar(50) DEFAULT NULL COMMENT 'smtp, gmail, outlook, sendgrid',
						message_id varchar(255) DEFAULT NULL COMMENT 'Email Message-ID header',
						thread_id varchar(255) DEFAULT NULL COMMENT 'Conversation thread identifier',
						status varchar(20) DEFAULT 'pending' COMMENT 'pending, sent, failed, received',
						sent_at datetime DEFAULT NULL,
						received_at datetime DEFAULT NULL,
						error_message text DEFAULT NULL,
						created_at datetime DEFAULT CURRENT_TIMESTAMP,
						PRIMARY KEY (id),
						KEY direction (direction),
						KEY email_address_id (email_address_id),
						KEY archive_category (archive_category),
						KEY related_entity_type (related_entity_type, related_entity_id),
						KEY message_id (message_id),
						KEY thread_id (thread_id),
						KEY status (status)
					) {$charset_collate};";

					require_once ABSPATH . 'wp-admin/includes/upgrade.php';
					dbDelta( $sql );
				},
			),

			// Migration 014: Create email templates table
			array(
				'name'        => '014_create_email_templates',
				'version'     => '1.6.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$table = $wpdb->prefix . 'ns_email_templates';
					$charset_collate = $wpdb->get_charset_collate();

					$sql = "CREATE TABLE IF NOT EXISTS {$table} (
						id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
						template_name varchar(255) NOT NULL,
						template_slug varchar(255) NOT NULL,
						description text DEFAULT NULL,
						subject_template varchar(500) DEFAULT NULL COMMENT 'Subject with {variables}',
						body_text_template longtext DEFAULT NULL,
						body_html_template longtext DEFAULT NULL,
						template_category varchar(50) DEFAULT NULL COMMENT 'system, donor, member, volunteer',
						available_variables longtext DEFAULT NULL COMMENT 'JSON: list of available {variables}',
						from_name varchar(255) DEFAULT NULL,
						from_email varchar(255) DEFAULT NULL,
						reply_to varchar(255) DEFAULT NULL,
						is_system tinyint(1) DEFAULT 0 COMMENT 'System templates cannot be deleted',
						is_active tinyint(1) DEFAULT 1,
						created_by bigint(20) unsigned DEFAULT NULL,
						created_at datetime DEFAULT CURRENT_TIMESTAMP,
						updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
						PRIMARY KEY (id),
						UNIQUE KEY template_slug (template_slug),
						KEY template_category (template_category),
						KEY is_active (is_active)
					) {$charset_collate};";

					require_once ABSPATH . 'wp-admin/includes/upgrade.php';
					dbDelta( $sql );
				},
			),

			// Migration 015: Create payment processors table
			array(
				'name'        => '015_create_payment_processors',
				'version'     => '1.7.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$table = $wpdb->prefix . 'ns_payment_processors';
					$charset_collate = $wpdb->get_charset_collate();

					$sql = "CREATE TABLE IF NOT EXISTS {$table} (
						id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
						processor_type varchar(50) NOT NULL COMMENT 'stripe, paypal, square, ach, venmo, zelle, etc',
						processor_name varchar(255) NOT NULL COMMENT 'Display name for donors',
						credentials longtext DEFAULT NULL COMMENT 'JSON: API keys, secrets',
						processor_config longtext DEFAULT NULL COMMENT 'JSON: Additional config',
						is_active tinyint(1) DEFAULT 1,
						is_preferred tinyint(1) DEFAULT 0 COMMENT 'Org preferred processor',
						display_order int(11) DEFAULT 0 COMMENT 'Display order in payment UI',
						min_amount decimal(10,2) DEFAULT NULL COMMENT 'Minimum transaction amount',
						max_amount decimal(10,2) DEFAULT NULL COMMENT 'Maximum transaction amount',
						supported_currencies longtext DEFAULT NULL COMMENT 'JSON array of currency codes',
						created_at datetime DEFAULT CURRENT_TIMESTAMP,
						updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
						PRIMARY KEY (id),
						KEY processor_type (processor_type),
						KEY is_active (is_active)
					) {$charset_collate};";

					require_once ABSPATH . 'wp-admin/includes/upgrade.php';
					dbDelta( $sql );
				},
			),

			// Migration 016: Create bank accounts table
			array(
				'name'        => '016_create_bank_accounts',
				'version'     => '1.7.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$table = $wpdb->prefix . 'ns_bank_accounts';
					$charset_collate = $wpdb->get_charset_collate();

					$sql = "CREATE TABLE IF NOT EXISTS {$table} (
						id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
						account_type varchar(50) NOT NULL COMMENT 'online_payments, operating, reserve, payroll',
						account_name varchar(255) NOT NULL,
						bank_name varchar(255) DEFAULT NULL,
						account_number_last4 varchar(4) DEFAULT NULL COMMENT 'Last 4 for security',
						routing_number varchar(20) DEFAULT NULL,
						account_details longtext DEFAULT NULL COMMENT 'JSON: Full details encrypted',
						current_balance decimal(15,2) DEFAULT 0.00,
						minimum_buffer decimal(15,2) DEFAULT 0.00 COMMENT 'Buffer for chargebacks/refunds',
						is_active tinyint(1) DEFAULT 1,
						last_sync_at datetime DEFAULT NULL,
						notes text DEFAULT NULL,
						created_at datetime DEFAULT CURRENT_TIMESTAMP,
						updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
						PRIMARY KEY (id),
						KEY account_type (account_type),
						KEY is_active (is_active)
					) {$charset_collate};";

					require_once ABSPATH . 'wp-admin/includes/upgrade.php';
					dbDelta( $sql );
				},
			),

			// Migration 017: Create sweep schedules table
			array(
				'name'        => '017_create_sweep_schedules',
				'version'     => '1.7.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$table = $wpdb->prefix . 'ns_sweep_schedules';
					$charset_collate = $wpdb->get_charset_collate();

					$sql = "CREATE TABLE IF NOT EXISTS {$table} (
						id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
						sweep_name varchar(255) NOT NULL,
						source_type varchar(50) NOT NULL COMMENT 'processor, bank_account',
						source_id bigint(20) unsigned DEFAULT NULL COMMENT 'Processor or bank account ID',
						destination_account_id bigint(20) unsigned NOT NULL COMMENT 'Bank account ID',
						sweep_frequency varchar(20) DEFAULT 'daily' COMMENT 'nightly, daily, weekly',
						schedule_time time DEFAULT '02:00:00' COMMENT 'Time of day to run',
						minimum_amount decimal(10,2) DEFAULT 0.00 COMMENT 'Only sweep if balance exceeds',
						leave_buffer_amount decimal(10,2) DEFAULT 0.00 COMMENT 'Amount to leave in source',
						sweep_percentage decimal(5,2) DEFAULT 100.00 COMMENT '% of available to sweep',
						is_active tinyint(1) DEFAULT 1,
						last_run_at datetime DEFAULT NULL,
						next_run_at datetime DEFAULT NULL,
						created_at datetime DEFAULT CURRENT_TIMESTAMP,
						updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
						PRIMARY KEY (id),
						KEY source_type (source_type, source_id),
						KEY destination_account_id (destination_account_id),
						KEY is_active (is_active),
						KEY next_run_at (next_run_at)
					) {$charset_collate};";

					require_once ABSPATH . 'wp-admin/includes/upgrade.php';
					dbDelta( $sql );
				},
			),

			// Migration 018: Create payment fee policies table
			array(
				'name'        => '018_create_payment_fee_policies',
				'version'     => '1.7.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$table = $wpdb->prefix . 'ns_payment_fee_policies';
					$charset_collate = $wpdb->get_charset_collate();

					$sql = "CREATE TABLE IF NOT EXISTS {$table} (
						id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
						processor_id bigint(20) unsigned NOT NULL,
						payment_type varchar(50) DEFAULT 'all' COMMENT 'donation, membership, event, all',
						policy_type varchar(20) DEFAULT 'donor_pays' COMMENT 'org_absorbs, donor_pays, hybrid, incentivize',
						fee_percentage decimal(5,2) DEFAULT 2.90 COMMENT 'Processor fee %',
						fee_fixed_amount decimal(10,2) DEFAULT 0.30 COMMENT 'Processor fixed fee',
						incentive_type varchar(50) DEFAULT NULL COMMENT 'free_service, discount, recognition',
						incentive_message varchar(500) DEFAULT NULL COMMENT 'We cover fees for ACH donations!',
						discount_amount decimal(10,2) DEFAULT NULL COMMENT 'Discount if using this processor',
						is_active tinyint(1) DEFAULT 1,
						created_at datetime DEFAULT CURRENT_TIMESTAMP,
						updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
						PRIMARY KEY (id),
						KEY processor_id (processor_id),
						KEY payment_type (payment_type),
						KEY is_active (is_active)
					) {$charset_collate};";

					require_once ABSPATH . 'wp-admin/includes/upgrade.php';
					dbDelta( $sql );
				},
			),

			// Migration 019: Create pledges table
			array(
				'name'        => '019_create_pledges',
				'version'     => '1.7.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$table = $wpdb->prefix . 'ns_pledges';
					$charset_collate = $wpdb->get_charset_collate();

					$sql = "CREATE TABLE IF NOT EXISTS {$table} (
						id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
						donor_id bigint(20) unsigned NOT NULL COMMENT 'User ID or contact ID',
						donor_name varchar(255) DEFAULT NULL,
						donor_email varchar(255) DEFAULT NULL,
						total_amount decimal(10,2) NOT NULL COMMENT 'Total pledge commitment',
						amount_paid decimal(10,2) DEFAULT 0.00 COMMENT 'Amount paid so far',
						amount_remaining decimal(10,2) DEFAULT 0.00 COMMENT 'Calculated remaining',
						frequency varchar(20) DEFAULT 'monthly' COMMENT 'one_time, weekly, monthly, quarterly, annual',
						installments_total int(11) DEFAULT 1,
						installments_paid int(11) DEFAULT 0,
						installment_amount decimal(10,2) DEFAULT NULL COMMENT 'Amount per installment',
						start_date date DEFAULT NULL,
						end_date date DEFAULT NULL,
						next_due_date date DEFAULT NULL,
						fund_restriction varchar(255) DEFAULT NULL COMMENT 'Restricted fund designation',
						campaign_id bigint(20) unsigned DEFAULT NULL COMMENT 'Related campaign',
						status varchar(20) DEFAULT 'active' COMMENT 'active, completed, cancelled, defaulted',
						notes text DEFAULT NULL,
						created_at datetime DEFAULT CURRENT_TIMESTAMP,
						updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
						PRIMARY KEY (id),
						KEY donor_id (donor_id),
						KEY status (status),
						KEY next_due_date (next_due_date),
						KEY campaign_id (campaign_id)
					) {$charset_collate};";

					require_once ABSPATH . 'wp-admin/includes/upgrade.php';
					dbDelta( $sql );
				},
			),

			// Migration 020: Create recurring donations table
			array(
				'name'        => '020_create_recurring_donations',
				'version'     => '1.7.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$table = $wpdb->prefix . 'ns_recurring_donations';
					$charset_collate = $wpdb->get_charset_collate();

					$sql = "CREATE TABLE IF NOT EXISTS {$table} (
						id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
						donor_id bigint(20) unsigned NOT NULL,
						donor_name varchar(255) DEFAULT NULL,
						donor_email varchar(255) DEFAULT NULL,
						processor_id bigint(20) unsigned NOT NULL,
						subscription_id varchar(255) NOT NULL COMMENT 'External subscription ID',
						amount decimal(10,2) NOT NULL,
						frequency varchar(20) DEFAULT 'monthly' COMMENT 'weekly, monthly, quarterly, annual',
						currency varchar(3) DEFAULT 'USD',
						processor_data longtext DEFAULT NULL COMMENT 'JSON: Processor-specific data',
						fund_restriction varchar(255) DEFAULT NULL,
						campaign_id bigint(20) unsigned DEFAULT NULL,
						status varchar(20) DEFAULT 'active' COMMENT 'active, paused, cancelled, failed',
						start_date date DEFAULT NULL,
						end_date date DEFAULT NULL COMMENT 'NULL for indefinite',
						next_charge_date date DEFAULT NULL,
						last_charge_date date DEFAULT NULL,
						total_charged decimal(10,2) DEFAULT 0.00 COMMENT 'Lifetime total',
						charge_count int(11) DEFAULT 0 COMMENT 'Number of successful charges',
						failed_charge_count int(11) DEFAULT 0,
						cancellation_reason text DEFAULT NULL,
						cancelled_at datetime DEFAULT NULL,
						created_at datetime DEFAULT CURRENT_TIMESTAMP,
						updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
						PRIMARY KEY (id),
						UNIQUE KEY subscription_id (subscription_id),
						KEY donor_id (donor_id),
						KEY processor_id (processor_id),
						KEY status (status),
						KEY next_charge_date (next_charge_date)
					) {$charset_collate};";

					require_once ABSPATH . 'wp-admin/includes/upgrade.php';
					dbDelta( $sql );
				},
			),

			// Migration 021: Create payment transactions table
			array(
				'name'        => '021_create_payment_transactions',
				'version'     => '1.7.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$table = $wpdb->prefix . 'ns_payment_transactions';
					$charset_collate = $wpdb->get_charset_collate();

					$sql = "CREATE TABLE IF NOT EXISTS {$table} (
						id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
						transaction_type varchar(50) DEFAULT 'donation' COMMENT 'donation, membership, event_ticket, refund, chargeback',
						processor_id bigint(20) unsigned NOT NULL,
						processor_transaction_id varchar(255) DEFAULT NULL COMMENT 'External transaction ID',
						bank_account_id bigint(20) unsigned DEFAULT NULL COMMENT 'Which bank account received funds',
						donor_id bigint(20) unsigned DEFAULT NULL,
						donor_name varchar(255) DEFAULT NULL,
						donor_email varchar(255) DEFAULT NULL,
						amount decimal(10,2) NOT NULL,
						fee_amount decimal(10,2) DEFAULT 0.00,
						net_amount decimal(10,2) DEFAULT 0.00 COMMENT 'amount - fee_amount',
						currency varchar(3) DEFAULT 'USD',
						fee_paid_by varchar(10) DEFAULT 'org' COMMENT 'donor, org',
						payment_method varchar(50) DEFAULT NULL COMMENT 'card, ach, paypal, venmo, etc',
						card_last4 varchar(4) DEFAULT NULL,
						status varchar(20) DEFAULT 'pending' COMMENT 'pending, completed, failed, refunded, disputed',
						pledge_id bigint(20) unsigned DEFAULT NULL COMMENT 'If paying toward pledge',
						recurring_donation_id bigint(20) unsigned DEFAULT NULL COMMENT 'If from subscription',
						fund_restriction varchar(255) DEFAULT NULL,
						campaign_id bigint(20) unsigned DEFAULT NULL,
						sweep_batch_id bigint(20) unsigned DEFAULT NULL COMMENT 'Which sweep batch moved these funds',
						processor_metadata longtext DEFAULT NULL COMMENT 'JSON: Full processor response',
						refund_reason text DEFAULT NULL,
						refunded_at datetime DEFAULT NULL,
						failed_reason text DEFAULT NULL,
						transaction_date datetime NOT NULL,
						created_at datetime DEFAULT CURRENT_TIMESTAMP,
						PRIMARY KEY (id),
						UNIQUE KEY processor_transaction_id (processor_transaction_id),
						KEY transaction_type (transaction_type),
						KEY processor_id (processor_id),
						KEY bank_account_id (bank_account_id),
						KEY donor_id (donor_id),
						KEY status (status),
						KEY pledge_id (pledge_id),
						KEY recurring_donation_id (recurring_donation_id),
						KEY transaction_date (transaction_date)
					) {$charset_collate};";

					require_once ABSPATH . 'wp-admin/includes/upgrade.php';
					dbDelta( $sql );
				},
			),

			// Migration 022: Create chart of accounts table
			array(
				'name'        => '022_create_chart_of_accounts_table',
				'version'     => '1.7.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$charset_collate = $wpdb->get_charset_collate();
					$table           = $wpdb->prefix . 'ns_chart_of_accounts';

					$sql = "CREATE TABLE {$table} (
						id bigint(20) NOT NULL AUTO_INCREMENT,
						organization_id bigint(20) NOT NULL,
						account_number varchar(50) NOT NULL,
						account_name varchar(255) NOT NULL,
						account_type enum('asset','liability','equity','revenue','expense') NOT NULL,
						account_subtype varchar(100) DEFAULT NULL,
						parent_account_id bigint(20) DEFAULT NULL,
						description text,
						is_active tinyint(1) DEFAULT 1,
						is_system tinyint(1) DEFAULT 0,
						current_balance decimal(15,2) DEFAULT 0.00,
						created_at datetime DEFAULT CURRENT_TIMESTAMP,
						updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
						PRIMARY KEY (id),
						UNIQUE KEY account_number_org (organization_id, account_number),
						KEY organization_id (organization_id),
						KEY account_type (account_type),
						KEY parent_account_id (parent_account_id),
						KEY is_active (is_active)
					) {$charset_collate};";

					require_once ABSPATH . 'wp-admin/includes/upgrade.php';
					dbDelta( $sql );
				},
			),

			// Migration 023: Create journal entries table
			array(
				'name'        => '023_create_journal_entries_table',
				'version'     => '1.7.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$charset_collate = $wpdb->get_charset_collate();
					$table           = $wpdb->prefix . 'ns_journal_entries';

					$sql = "CREATE TABLE {$table} (
						id bigint(20) NOT NULL AUTO_INCREMENT,
						organization_id bigint(20) NOT NULL,
						entry_number varchar(50) NOT NULL,
						entry_date date NOT NULL,
						account_id bigint(20) NOT NULL,
						debit_amount decimal(15,2) DEFAULT 0.00,
						credit_amount decimal(15,2) DEFAULT 0.00,
						description text,
						reference_type varchar(50) DEFAULT NULL COMMENT 'donation, payment, transaction, manual',
						reference_id bigint(20) DEFAULT NULL,
						batch_id varchar(50) DEFAULT NULL COMMENT 'Groups related entries together',
						is_reconciled tinyint(1) DEFAULT 0,
						reconciled_date datetime DEFAULT NULL,
						created_by bigint(20) DEFAULT NULL,
						created_at datetime DEFAULT CURRENT_TIMESTAMP,
						PRIMARY KEY (id),
						KEY organization_id (organization_id),
						KEY account_id (account_id),
						KEY entry_date (entry_date),
						KEY batch_id (batch_id),
						KEY reference_type_id (reference_type, reference_id),
						KEY is_reconciled (is_reconciled)
					) {$charset_collate};";

					require_once ABSPATH . 'wp-admin/includes/upgrade.php';
					dbDelta( $sql );
				},
			),

			// Migration 024: Create accounting settings table
			array(
				'name'        => '024_create_accounting_settings_table',
				'version'     => '1.7.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$charset_collate = $wpdb->get_charset_collate();
					$table           = $wpdb->prefix . 'ns_accounting_settings';

					$sql = "CREATE TABLE {$table} (
						id bigint(20) NOT NULL AUTO_INCREMENT,
						organization_id bigint(20) NOT NULL,
						setting_key varchar(100) NOT NULL,
						setting_value longtext,
						created_at datetime DEFAULT CURRENT_TIMESTAMP,
						updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
						PRIMARY KEY (id),
						UNIQUE KEY org_setting (organization_id, setting_key),
						KEY organization_id (organization_id)
					) {$charset_collate};";

					require_once ABSPATH . 'wp-admin/includes/upgrade.php';
					dbDelta( $sql );
				},
			),

			// Migration 025: Create CRM settings table
			array(
				'name'        => '025_create_crm_settings_table',
				'version'     => '1.8.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$charset_collate = $wpdb->get_charset_collate();
					$table           = $wpdb->prefix . 'ns_crm_settings';

					$sql = "CREATE TABLE {$table} (
						id bigint(20) NOT NULL AUTO_INCREMENT,
						organization_id bigint(20) NOT NULL,
						crm_provider varchar(50) NOT NULL COMMENT 'civicrm, salesforce, hubspot, zoho, bloomerang',
						crm_mode enum('disabled','builtin','external','both') DEFAULT 'builtin',
						api_url varchar(255) DEFAULT NULL,
						api_key varchar(255) DEFAULT NULL,
						api_secret varchar(255) DEFAULT NULL,
						oauth_token longtext DEFAULT NULL,
						oauth_refresh_token longtext DEFAULT NULL,
						oauth_expires_at datetime DEFAULT NULL,
						sync_direction enum('push','pull','bidirectional') DEFAULT 'push',
						sync_frequency varchar(20) DEFAULT 'hourly' COMMENT 'realtime, hourly, daily, manual',
						last_sync_at datetime DEFAULT NULL,
						sync_status varchar(20) DEFAULT 'idle' COMMENT 'idle, syncing, error',
						is_active tinyint(1) DEFAULT 1,
						settings longtext DEFAULT NULL COMMENT 'JSON for provider-specific settings',
						created_at datetime DEFAULT CURRENT_TIMESTAMP,
						updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
						PRIMARY KEY (id),
						UNIQUE KEY org_provider (organization_id, crm_provider),
						KEY organization_id (organization_id)
					) {$charset_collate};";

					require_once ABSPATH . 'wp-admin/includes/upgrade.php';
					dbDelta( $sql );
				},
			),

			// Migration 026: Create CRM sync log table
			array(
				'name'        => '026_create_crm_sync_log_table',
				'version'     => '1.8.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$charset_collate = $wpdb->get_charset_collate();
					$table           = $wpdb->prefix . 'ns_crm_sync_log';

					$sql = "CREATE TABLE {$table} (
						id bigint(20) NOT NULL AUTO_INCREMENT,
						organization_id bigint(20) NOT NULL,
						crm_provider varchar(50) NOT NULL,
						sync_direction enum('push','pull') NOT NULL,
						entity_type varchar(50) NOT NULL COMMENT 'contact, donation, membership, activity, etc',
						entity_id bigint(20) NOT NULL COMMENT 'NS entity ID',
						crm_entity_id varchar(100) DEFAULT NULL COMMENT 'CRM entity ID',
						sync_action varchar(20) NOT NULL COMMENT 'create, update, delete',
						sync_status varchar(20) NOT NULL COMMENT 'success, error, conflict',
						error_message text DEFAULT NULL,
						field_conflicts longtext DEFAULT NULL COMMENT 'JSON of conflicting fields',
						sync_data longtext DEFAULT NULL COMMENT 'JSON snapshot of synced data',
						synced_at datetime DEFAULT CURRENT_TIMESTAMP,
						PRIMARY KEY (id),
						KEY organization_id (organization_id),
						KEY entity_type_id (entity_type, entity_id),
						KEY crm_entity_id (crm_entity_id),
						KEY sync_status (sync_status),
						KEY synced_at (synced_at)
					) {$charset_collate};";

					require_once ABSPATH . 'wp-admin/includes/upgrade.php';
					dbDelta( $sql );
				},
			),

			// Migration 027: Create CRM field mappings table
			array(
				'name'        => '027_create_crm_field_mappings_table',
				'version'     => '1.8.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$charset_collate = $wpdb->get_charset_collate();
					$table           = $wpdb->prefix . 'ns_crm_field_mappings';

					$sql = "CREATE TABLE {$table} (
						id bigint(20) NOT NULL AUTO_INCREMENT,
						organization_id bigint(20) NOT NULL,
						crm_provider varchar(50) NOT NULL,
						entity_type varchar(50) NOT NULL COMMENT 'contact, donation, membership, etc',
						ns_field_name varchar(100) NOT NULL,
						ns_field_type varchar(50) NOT NULL COMMENT 'text, email, phone, number, date, boolean, etc',
						crm_field_name varchar(100) NOT NULL,
						crm_field_type varchar(50) DEFAULT NULL,
						sync_direction enum('push','pull','bidirectional') DEFAULT 'bidirectional',
						conflict_resolution enum('ns_wins','crm_wins','newest_wins','manual') DEFAULT 'newest_wins',
						is_required tinyint(1) DEFAULT 0,
						is_active tinyint(1) DEFAULT 1,
						transform_function varchar(100) DEFAULT NULL COMMENT 'PHP function to transform data',
						created_at datetime DEFAULT CURRENT_TIMESTAMP,
						updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
						PRIMARY KEY (id),
						UNIQUE KEY org_provider_entity_field (organization_id, crm_provider, entity_type, ns_field_name),
						KEY organization_id (organization_id)
					) {$charset_collate};";

					require_once ABSPATH . 'wp-admin/includes/upgrade.php';
					dbDelta( $sql );
				},
			),

		// Migration 028: Create marketing settings table
		array(
			'name'        => '028_create_marketing_settings_table',
			'version'     => '1.9.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_marketing_settings';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					platform varchar(50) NOT NULL COMMENT 'mailchimp, constant_contact, twilio, sendgrid',
					platform_mode enum('disabled','builtin','external','both') DEFAULT 'builtin',
					api_key varchar(255) DEFAULT NULL,
					api_secret varchar(255) DEFAULT NULL,
					account_sid varchar(255) DEFAULT NULL COMMENT 'Twilio Account SID',
					auth_token varchar(255) DEFAULT NULL COMMENT 'Twilio Auth Token',
					oauth_token longtext DEFAULT NULL,
					oauth_refresh_token longtext DEFAULT NULL,
					oauth_expires_at datetime DEFAULT NULL,
					server_prefix varchar(50) DEFAULT NULL COMMENT 'Mailchimp server prefix (us1, us2, etc)',
					from_email varchar(255) DEFAULT NULL,
					from_name varchar(255) DEFAULT NULL,
					reply_to_email varchar(255) DEFAULT NULL,
					is_active tinyint(1) DEFAULT 1,
					settings longtext DEFAULT NULL COMMENT 'JSON for platform-specific settings',
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					UNIQUE KEY org_platform (organization_id, platform),
					KEY organization_id (organization_id)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 029: Create marketing campaigns table
		array(
			'name'        => '029_create_marketing_campaigns_table',
			'version'     => '1.9.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_marketing_campaigns';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					campaign_name varchar(255) NOT NULL,
					campaign_type enum('email','sms','social','multi') NOT NULL,
					platform varchar(50) DEFAULT NULL COMMENT 'mailchimp, constant_contact, twilio, builtin',
					platform_campaign_id varchar(100) DEFAULT NULL COMMENT 'External platform campaign ID',
					subject varchar(500) DEFAULT NULL,
					preview_text varchar(500) DEFAULT NULL,
					content longtext,
					status enum('draft','scheduled','sending','sent','paused','cancelled') DEFAULT 'draft',
					segment_id bigint(20) DEFAULT NULL COMMENT 'Target audience segment',
					scheduled_at datetime DEFAULT NULL,
					sent_at datetime DEFAULT NULL,
					total_recipients int(11) DEFAULT 0,
					total_sent int(11) DEFAULT 0,
					total_delivered int(11) DEFAULT 0,
					total_opened int(11) DEFAULT 0,
					total_clicked int(11) DEFAULT 0,
					total_bounced int(11) DEFAULT 0,
					total_unsubscribed int(11) DEFAULT 0,
					campaign_settings longtext DEFAULT NULL COMMENT 'JSON settings (A/B test, tracking, etc)',
					created_by bigint(20) DEFAULT NULL,
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY organization_id (organization_id),
					KEY segment_id (segment_id),
					KEY status (status)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 030: Create marketing segments table
		array(
			'name'        => '030_create_marketing_segments_table',
			'version'     => '1.9.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_marketing_segments';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					segment_name varchar(255) NOT NULL,
					segment_type enum('static','dynamic') DEFAULT 'dynamic',
					description text,
					criteria longtext COMMENT 'JSON filter criteria for dynamic segments',
					platform varchar(50) DEFAULT NULL COMMENT 'If synced to external platform',
					platform_list_id varchar(100) DEFAULT NULL COMMENT 'External platform list/segment ID',
					contact_count int(11) DEFAULT 0,
					last_sync_at datetime DEFAULT NULL,
					is_active tinyint(1) DEFAULT 1,
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY organization_id (organization_id),
					KEY segment_type (segment_type)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 031: Create marketing segment members table
		array(
			'name'        => '031_create_marketing_segment_members_table',
			'version'     => '1.9.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_marketing_segment_members';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					segment_id bigint(20) NOT NULL,
					contact_id bigint(20) NOT NULL,
					email varchar(255) NOT NULL,
					status enum('subscribed','unsubscribed','pending','bounced') DEFAULT 'subscribed',
					added_at datetime DEFAULT CURRENT_TIMESTAMP,
					unsubscribed_at datetime DEFAULT NULL,
					PRIMARY KEY (id),
					UNIQUE KEY segment_contact (segment_id, contact_id),
					KEY segment_id (segment_id),
					KEY contact_id (contact_id),
					KEY email (email),
					KEY status (status)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 032: Create marketing analytics table
		array(
			'name'        => '032_create_marketing_analytics_table',
			'version'     => '1.9.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_marketing_analytics';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					campaign_id bigint(20) NOT NULL,
					contact_id bigint(20) DEFAULT NULL,
					email varchar(255) DEFAULT NULL,
					event_type enum('sent','delivered','opened','clicked','bounced','unsubscribed','complained') NOT NULL,
					event_data longtext COMMENT 'JSON event details (link clicked, bounce reason, etc)',
					user_agent varchar(500) DEFAULT NULL,
					ip_address varchar(45) DEFAULT NULL,
					event_at datetime DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY organization_id (organization_id),
					KEY campaign_id (campaign_id),
					KEY contact_id (contact_id),
					KEY event_type (event_type),
					KEY event_at (event_at)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),
		);
	}

	/**
	 * Example migration definition structure (for documentation).
	 *
	 * @return array Example migrations array.
	 */
	public static function get_example_migrations() {
		return array(
			array(
				'name'        => '001_add_treasury_reconciliation_column',
				'version'     => '1.1.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$table = $wpdb->prefix . 'ns_transactions';
					$wpdb->query( "ALTER TABLE {$table} ADD COLUMN reconciled tinyint(1) DEFAULT 0 AFTER amount" );
				},
			),
			array(
				'name'        => '002_add_donors_tax_id_column',
				'version'     => '1.1.0',
				'min_version' => '1.0.0',
				'callback'    => function() {
					global $wpdb;
					$table = $wpdb->prefix . 'ns_donors';
					$wpdb->query( "ALTER TABLE {$table} ADD COLUMN tax_id varchar(50) NULL AFTER donor_level" );
				},
			),

		// Migration 033: Create video conferencing settings table
		array(
			'name'        => '033_create_video_conferencing_settings_table',
			'version'     => '1.10.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_video_conferencing_settings';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					provider varchar(50) NOT NULL COMMENT 'zoom, google_meet, teams',
					api_key varchar(255) DEFAULT NULL,
					api_secret varchar(255) DEFAULT NULL,
					oauth_token longtext DEFAULT NULL,
					oauth_refresh_token longtext DEFAULT NULL,
					oauth_expires_at datetime DEFAULT NULL,
					account_email varchar(255) DEFAULT NULL,
					is_active tinyint(1) DEFAULT 1,
					settings longtext DEFAULT NULL COMMENT 'JSON provider-specific settings',
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					UNIQUE KEY org_provider (organization_id, provider),
					KEY organization_id (organization_id)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 034: Create video meetings table
		array(
			'name'        => '034_create_video_meetings_table',
			'version'     => '1.10.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_video_meetings';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					calendar_event_id bigint(20) DEFAULT NULL COMMENT 'Link to calendar event',
					provider varchar(50) NOT NULL COMMENT 'zoom, google_meet, teams',
					meeting_id varchar(255) NOT NULL COMMENT 'Provider meeting ID',
					meeting_url varchar(500) NOT NULL,
					join_url varchar(500) DEFAULT NULL,
					meeting_password varchar(100) DEFAULT NULL,
					host_id bigint(20) DEFAULT NULL COMMENT 'WordPress user ID of host',
					topic varchar(500) NOT NULL,
					agenda text,
					start_time datetime NOT NULL,
					duration int(11) DEFAULT NULL COMMENT 'Duration in minutes',
					timezone varchar(100) DEFAULT 'UTC',
					status varchar(50) DEFAULT 'scheduled' COMMENT 'scheduled, started, ended, cancelled',
					settings longtext DEFAULT NULL COMMENT 'JSON meeting settings',
					recording_url varchar(500) DEFAULT NULL,
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY organization_id (organization_id),
					KEY calendar_event_id (calendar_event_id),
					KEY meeting_id (meeting_id),
					KEY start_time (start_time),
					KEY status (status)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 035: Create video meeting participants table
		array(
			'name'        => '035_create_video_meeting_participants_table',
			'version'     => '1.10.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_video_meeting_participants';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					meeting_id bigint(20) NOT NULL,
					contact_id bigint(20) DEFAULT NULL,
					participant_name varchar(255) NOT NULL,
					participant_email varchar(255) DEFAULT NULL,
					join_time datetime DEFAULT NULL,
					leave_time datetime DEFAULT NULL,
					duration int(11) DEFAULT NULL COMMENT 'Duration in seconds',
					status enum('invited','joined','left','no_show') DEFAULT 'invited',
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY meeting_id (meeting_id),
					KEY contact_id (contact_id),
					KEY participant_email (participant_email)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 036: Create forms settings table
		array(
			'name'        => '036_create_forms_settings_table',
			'version'     => '1.11.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_forms_settings';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					provider varchar(50) NOT NULL COMMENT 'builtin, google_forms, typeform, jotform, surveymonkey',
					api_key varchar(255) DEFAULT NULL,
					oauth_token longtext DEFAULT NULL,
					oauth_refresh_token longtext DEFAULT NULL,
					oauth_expires_at datetime DEFAULT NULL,
					webhook_secret varchar(255) DEFAULT NULL,
					is_active tinyint(1) DEFAULT 1,
					settings longtext DEFAULT NULL COMMENT 'JSON provider-specific settings',
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					UNIQUE KEY org_provider (organization_id, provider),
					KEY organization_id (organization_id)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 037: Create forms table
		array(
			'name'        => '037_create_forms_table',
			'version'     => '1.11.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_forms';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					form_name varchar(255) NOT NULL,
					form_type enum('contact','survey','registration','donation','volunteer','custom') NOT NULL,
					description text,
					provider varchar(50) DEFAULT 'builtin' COMMENT 'builtin, google_forms, typeform, jotform',
					provider_form_id varchar(255) DEFAULT NULL COMMENT 'External provider form ID',
					form_url varchar(500) DEFAULT NULL COMMENT 'External form URL',
					embed_code text DEFAULT NULL COMMENT 'Embed code for external forms',
					status enum('draft','active','closed','archived') DEFAULT 'draft',
					submission_limit int(11) DEFAULT NULL COMMENT 'Max submissions allowed',
					submission_count int(11) DEFAULT 0,
					start_date datetime DEFAULT NULL,
					end_date datetime DEFAULT NULL,
					confirmation_message text,
					notification_emails text COMMENT 'Comma-separated email addresses',
					redirect_url varchar(500) DEFAULT NULL,
					allow_anonymous tinyint(1) DEFAULT 1,
					require_login tinyint(1) DEFAULT 0,
					enable_captcha tinyint(1) DEFAULT 0,
					settings longtext DEFAULT NULL COMMENT 'JSON form settings (multi-page, save progress, etc)',
					created_by bigint(20) DEFAULT NULL,
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY organization_id (organization_id),
					KEY form_type (form_type),
					KEY status (status),
					KEY provider (provider)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 038: Create form fields table
		array(
			'name'        => '038_create_form_fields_table',
			'version'     => '1.11.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_form_fields';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					form_id bigint(20) NOT NULL,
					field_name varchar(255) NOT NULL,
					field_label varchar(500) NOT NULL,
					field_type varchar(50) NOT NULL COMMENT 'text, email, phone, number, textarea, select, radio, checkbox, date, file, rating, matrix',
					field_options longtext DEFAULT NULL COMMENT 'JSON options for select/radio/checkbox',
					placeholder varchar(255) DEFAULT NULL,
					default_value text DEFAULT NULL,
					is_required tinyint(1) DEFAULT 0,
					validation_rules longtext DEFAULT NULL COMMENT 'JSON validation rules',
					conditional_logic longtext DEFAULT NULL COMMENT 'JSON conditional display rules',
					field_order int(11) DEFAULT 0,
					page_number int(11) DEFAULT 1 COMMENT 'For multi-page forms',
					help_text text DEFAULT NULL,
					css_class varchar(255) DEFAULT NULL,
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY form_id (form_id),
					KEY field_order (field_order),
					KEY page_number (page_number)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 039: Create form submissions table
		array(
			'name'        => '039_create_form_submissions_table',
			'version'     => '1.11.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_form_submissions';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					form_id bigint(20) NOT NULL,
					organization_id bigint(20) NOT NULL,
					contact_id bigint(20) DEFAULT NULL COMMENT 'Linked contact if available',
					user_id bigint(20) DEFAULT NULL COMMENT 'WordPress user if logged in',
					submission_status enum('completed','partial','spam','flagged') DEFAULT 'completed',
					ip_address varchar(45) DEFAULT NULL,
					user_agent varchar(500) DEFAULT NULL,
					referrer varchar(500) DEFAULT NULL,
					submission_time int(11) DEFAULT NULL COMMENT 'Time to complete in seconds',
					provider_submission_id varchar(255) DEFAULT NULL COMMENT 'External provider submission ID',
					score int(11) DEFAULT NULL COMMENT 'For surveys/quizzes',
					is_read tinyint(1) DEFAULT 0,
					notes text DEFAULT NULL,
					submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY form_id (form_id),
					KEY organization_id (organization_id),
					KEY contact_id (contact_id),
					KEY user_id (user_id),
					KEY submission_status (submission_status),
					KEY submitted_at (submitted_at)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 040: Create form submission data table
		array(
			'name'        => '040_create_form_submission_data_table',
			'version'     => '1.11.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_form_submission_data';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					submission_id bigint(20) NOT NULL,
					field_id bigint(20) DEFAULT NULL COMMENT 'NULL for external forms',
					field_name varchar(255) NOT NULL,
					field_value longtext,
					file_url varchar(500) DEFAULT NULL COMMENT 'For file uploads',
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY submission_id (submission_id),
					KEY field_id (field_id),
					KEY field_name (field_name)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 041: Create project management settings table
		array(
			'name'        => '041_create_project_management_settings_table',
			'version'     => '1.12.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_project_management_settings';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					provider varchar(50) NOT NULL COMMENT 'builtin, asana, trello, monday, jira, basecamp',
					api_key varchar(255) DEFAULT NULL,
					api_secret varchar(255) DEFAULT NULL,
					oauth_token longtext DEFAULT NULL,
					oauth_refresh_token longtext DEFAULT NULL,
					oauth_expires_at datetime DEFAULT NULL,
					workspace_id varchar(255) DEFAULT NULL COMMENT 'External workspace/organization ID',
					is_active tinyint(1) DEFAULT 1,
					sync_enabled tinyint(1) DEFAULT 0,
					sync_direction enum('push','pull','bidirectional') DEFAULT 'bidirectional',
					last_sync_at datetime DEFAULT NULL,
					settings longtext DEFAULT NULL COMMENT 'JSON provider-specific settings',
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					UNIQUE KEY org_provider (organization_id, provider),
					KEY organization_id (organization_id)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 042: Create projects table
		array(
			'name'        => '042_create_projects_table',
			'version'     => '1.12.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_projects';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					project_name varchar(255) NOT NULL,
					project_key varchar(50) DEFAULT NULL COMMENT 'Unique project identifier/code',
					description text,
					project_status enum('planning','active','on_hold','completed','cancelled') DEFAULT 'planning',
					priority enum('low','medium','high','critical') DEFAULT 'medium',
					provider varchar(50) DEFAULT 'builtin',
					provider_project_id varchar(255) DEFAULT NULL,
					start_date date DEFAULT NULL,
					end_date date DEFAULT NULL,
					budget decimal(15,2) DEFAULT NULL,
					owner_id bigint(20) DEFAULT NULL COMMENT 'WordPress user ID of project owner',
					parent_project_id bigint(20) DEFAULT NULL COMMENT 'For sub-projects',
					progress_percent int(11) DEFAULT 0,
					color varchar(7) DEFAULT NULL COMMENT 'Hex color code for visual identification',
					is_archived tinyint(1) DEFAULT 0,
					settings longtext DEFAULT NULL COMMENT 'JSON project settings',
					created_by bigint(20) DEFAULT NULL,
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY organization_id (organization_id),
					KEY project_status (project_status),
					KEY owner_id (owner_id),
					KEY parent_project_id (parent_project_id),
					KEY is_archived (is_archived)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 043: Create tasks table
		array(
			'name'        => '043_create_tasks_table',
			'version'     => '1.12.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_tasks';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					project_id bigint(20) NOT NULL,
					task_name varchar(500) NOT NULL,
					description longtext,
					task_status enum('todo','in_progress','in_review','blocked','completed','cancelled') DEFAULT 'todo',
					priority enum('low','medium','high','critical') DEFAULT 'medium',
					provider_task_id varchar(255) DEFAULT NULL,
					assigned_to bigint(20) DEFAULT NULL COMMENT 'WordPress user ID',
					parent_task_id bigint(20) DEFAULT NULL COMMENT 'For sub-tasks',
					start_date datetime DEFAULT NULL,
					due_date datetime DEFAULT NULL,
					completed_at datetime DEFAULT NULL,
					estimated_hours decimal(8,2) DEFAULT NULL,
					actual_hours decimal(8,2) DEFAULT NULL,
					progress_percent int(11) DEFAULT 0,
					task_type varchar(50) DEFAULT 'task' COMMENT 'task, bug, feature, epic, story',
					labels text COMMENT 'Comma-separated labels/tags',
					attachments longtext COMMENT 'JSON array of attachment URLs',
					checklist longtext COMMENT 'JSON array of checklist items',
					custom_fields longtext COMMENT 'JSON for custom field values',
					created_by bigint(20) DEFAULT NULL,
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY organization_id (organization_id),
					KEY project_id (project_id),
					KEY task_status (task_status),
					KEY assigned_to (assigned_to),
					KEY parent_task_id (parent_task_id),
					KEY due_date (due_date)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 044: Create task dependencies table
		array(
			'name'        => '044_create_task_dependencies_table',
			'version'     => '1.12.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_task_dependencies';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					task_id bigint(20) NOT NULL,
					depends_on_task_id bigint(20) NOT NULL,
					dependency_type enum('finish_to_start','start_to_start','finish_to_finish','start_to_finish') DEFAULT 'finish_to_start',
					lag_days int(11) DEFAULT 0 COMMENT 'Delay in days between dependent tasks',
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					UNIQUE KEY task_dependency (task_id, depends_on_task_id),
					KEY task_id (task_id),
					KEY depends_on_task_id (depends_on_task_id)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 045: Create project members table
		array(
			'name'        => '045_create_project_members_table',
			'version'     => '1.12.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_project_members';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					project_id bigint(20) NOT NULL,
					user_id bigint(20) NOT NULL,
					role varchar(50) DEFAULT 'member' COMMENT 'owner, admin, member, viewer',
					permissions longtext DEFAULT NULL COMMENT 'JSON array of specific permissions',
					hourly_rate decimal(10,2) DEFAULT NULL COMMENT 'For time tracking and budgeting',
					added_at datetime DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					UNIQUE KEY project_user (project_id, user_id),
					KEY project_id (project_id),
					KEY user_id (user_id)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 046: Create AI settings table
		array(
			'name'        => '046_create_ai_settings_table',
			'version'     => '1.13.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_ai_settings';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					provider varchar(50) NOT NULL COMMENT 'openai, anthropic, google, azure',
					api_key varchar(255) DEFAULT NULL,
					api_endpoint varchar(255) DEFAULT NULL COMMENT 'For custom endpoints',
					model_name varchar(100) DEFAULT NULL COMMENT 'gpt-4, claude-3, gemini-pro, etc',
					is_active tinyint(1) DEFAULT 1,
					default_temperature decimal(3,2) DEFAULT 0.70,
					default_max_tokens int(11) DEFAULT 1000,
					rate_limit_per_minute int(11) DEFAULT 60,
					monthly_budget decimal(10,2) DEFAULT NULL COMMENT 'Budget limit in USD',
					current_month_spend decimal(10,2) DEFAULT 0,
					settings longtext DEFAULT NULL COMMENT 'JSON provider-specific settings',
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					UNIQUE KEY org_provider (organization_id, provider),
					KEY organization_id (organization_id)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 047: Create AI conversations table
		array(
			'name'        => '047_create_ai_conversations_table',
			'version'     => '1.13.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_ai_conversations';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					user_id bigint(20) DEFAULT NULL COMMENT 'WordPress user ID',
					conversation_title varchar(500) DEFAULT NULL,
					provider varchar(50) NOT NULL,
					model varchar(100) NOT NULL,
					context_type varchar(50) DEFAULT NULL COMMENT 'email, task, document, general',
					context_id bigint(20) DEFAULT NULL COMMENT 'Related entity ID',
					total_messages int(11) DEFAULT 0,
					total_tokens int(11) DEFAULT 0,
					total_cost decimal(10,4) DEFAULT 0,
					metadata longtext DEFAULT NULL COMMENT 'JSON metadata',
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY organization_id (organization_id),
					KEY user_id (user_id),
					KEY context_type (context_type, context_id),
					KEY created_at (created_at)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 048: Create AI messages table
		array(
			'name'        => '048_create_ai_messages_table',
			'version'     => '1.13.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_ai_messages';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					conversation_id bigint(20) NOT NULL,
					role enum('user','assistant','system','function') NOT NULL,
					content longtext NOT NULL,
					function_call longtext DEFAULT NULL COMMENT 'JSON function call data',
					tokens int(11) DEFAULT NULL,
					cost decimal(10,4) DEFAULT NULL,
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY conversation_id (conversation_id),
					KEY created_at (created_at)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 049: Create AI automation rules table
		array(
			'name'        => '049_create_ai_automation_rules_table',
			'version'     => '1.13.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_ai_automation_rules';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					rule_name varchar(255) NOT NULL,
					trigger_type varchar(50) NOT NULL COMMENT 'email_received, form_submitted, task_created, etc',
					trigger_conditions longtext DEFAULT NULL COMMENT 'JSON conditions',
					ai_action varchar(50) NOT NULL COMMENT 'summarize, categorize, extract, respond, etc',
					ai_prompt longtext DEFAULT NULL COMMENT 'Custom prompt template',
					action_type varchar(50) NOT NULL COMMENT 'create_task, send_email, update_field, etc',
					action_config longtext DEFAULT NULL COMMENT 'JSON action configuration',
					provider varchar(50) NOT NULL,
					model varchar(100) DEFAULT NULL,
					is_active tinyint(1) DEFAULT 1,
					execution_count int(11) DEFAULT 0,
					last_executed_at datetime DEFAULT NULL,
					created_by bigint(20) DEFAULT NULL,
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY organization_id (organization_id),
					KEY trigger_type (trigger_type),
					KEY is_active (is_active)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},

		// Migration 050: Create SMS settings table
		array(
			'name'        => '050_create_sms_settings_table',
			'version'     => '1.14.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_sms_settings';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					provider varchar(50) NOT NULL COMMENT 'twilio, plivo, vonage, etc',
					account_sid varchar(255) DEFAULT NULL COMMENT 'Provider account ID',
					api_key varchar(255) NOT NULL,
					api_secret varchar(255) DEFAULT NULL,
					phone_number varchar(20) DEFAULT NULL COMMENT 'Sending phone number',
					is_active tinyint(1) DEFAULT 1,
					monthly_limit int(11) DEFAULT 10000 COMMENT 'Monthly SMS limit',
					current_month_count int(11) DEFAULT 0,
					current_month_cost decimal(10,4) DEFAULT 0.00,
					settings longtext DEFAULT NULL COMMENT 'JSON additional settings',
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY organization_id (organization_id),
					KEY provider (provider),
					KEY is_active (is_active),
					UNIQUE KEY org_provider (organization_id, provider)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 051: Create SMS messages table
		array(
			'name'        => '051_create_sms_messages_table',
			'version'     => '1.14.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_sms_messages';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					campaign_id bigint(20) DEFAULT NULL COMMENT 'NULL for individual messages',
					contact_id bigint(20) DEFAULT NULL,
					recipient_phone varchar(20) NOT NULL,
					sender_phone varchar(20) NOT NULL,
					message_body text NOT NULL,
					message_type enum('transactional','marketing','notification','automation') DEFAULT 'transactional',
					provider varchar(50) NOT NULL,
					provider_message_id varchar(255) DEFAULT NULL,
					status enum('queued','sent','delivered','failed','undelivered') DEFAULT 'queued',
					error_message text DEFAULT NULL,
					segments int(11) DEFAULT 1 COMMENT 'Number of SMS segments',
					cost decimal(8,4) DEFAULT 0.00,
					direction enum('outbound','inbound') DEFAULT 'outbound',
					metadata longtext DEFAULT NULL COMMENT 'JSON additional data',
					sent_at datetime DEFAULT NULL,
					delivered_at datetime DEFAULT NULL,
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY organization_id (organization_id),
					KEY campaign_id (campaign_id),
					KEY contact_id (contact_id),
					KEY recipient_phone (recipient_phone),
					KEY status (status),
					KEY provider_message_id (provider_message_id),
					KEY created_at (created_at)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 052: Create SMS campaigns table
		array(
			'name'        => '052_create_sms_campaigns_table',
			'version'     => '1.14.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_sms_campaigns';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					campaign_name varchar(255) NOT NULL,
					message_body text NOT NULL,
					campaign_type enum('one_time','recurring','drip','automation') DEFAULT 'one_time',
					target_segment varchar(100) DEFAULT NULL COMMENT 'all, donors, volunteers, members, custom',
					segment_filter longtext DEFAULT NULL COMMENT 'JSON filter criteria',
					provider varchar(50) NOT NULL,
					status enum('draft','scheduled','sending','sent','paused','cancelled') DEFAULT 'draft',
					scheduled_at datetime DEFAULT NULL,
					started_at datetime DEFAULT NULL,
					completed_at datetime DEFAULT NULL,
					total_recipients int(11) DEFAULT 0,
					total_sent int(11) DEFAULT 0,
					total_delivered int(11) DEFAULT 0,
					total_failed int(11) DEFAULT 0,
					total_cost decimal(10,2) DEFAULT 0.00,
					metadata longtext DEFAULT NULL COMMENT 'JSON campaign settings',
					created_by bigint(20) DEFAULT NULL,
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY organization_id (organization_id),
					KEY status (status),
					KEY scheduled_at (scheduled_at),
					KEY created_by (created_by)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 053: Create SMS opt-outs table
		array(
			'name'        => '053_create_sms_optouts_table',
			'version'     => '1.14.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_sms_optouts';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					phone_number varchar(20) NOT NULL,
					contact_id bigint(20) DEFAULT NULL,
					opt_out_type enum('stop','unsubscribe','global') DEFAULT 'stop',
					opt_out_message text DEFAULT NULL COMMENT 'Message that triggered opt-out',
					opt_out_source varchar(50) DEFAULT NULL COMMENT 'campaign_id or manual',
					opted_out_at datetime DEFAULT CURRENT_TIMESTAMP,
					opted_back_in_at datetime DEFAULT NULL,
					is_active tinyint(1) DEFAULT 1 COMMENT '1 = opted out, 0 = opted back in',
					notes text DEFAULT NULL,
					PRIMARY KEY (id),
					KEY organization_id (organization_id),
					KEY phone_number (phone_number),
					KEY contact_id (contact_id),
					KEY is_active (is_active),
					UNIQUE KEY org_phone (organization_id, phone_number)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},

		// Migration 054: Create analytics settings table
		array(
			'name'        => '054_create_analytics_settings_table',
			'version'     => '1.15.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_analytics_settings';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					provider varchar(50) NOT NULL COMMENT 'google_analytics, mixpanel, segment, etc',
					tracking_id varchar(255) DEFAULT NULL COMMENT 'GA tracking ID, Mixpanel token, etc',
					api_key varchar(255) DEFAULT NULL,
					api_secret varchar(255) DEFAULT NULL,
					property_id varchar(255) DEFAULT NULL COMMENT 'GA4 property ID',
					is_active tinyint(1) DEFAULT 1,
					tracking_enabled tinyint(1) DEFAULT 1 COMMENT 'Enable automatic event tracking',
					settings longtext DEFAULT NULL COMMENT 'JSON additional settings',
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY organization_id (organization_id),
					KEY provider (provider),
					KEY is_active (is_active),
					UNIQUE KEY org_provider (organization_id, provider)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 055: Create analytics events table
		array(
			'name'        => '055_create_analytics_events_table',
			'version'     => '1.15.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_analytics_events';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					event_name varchar(100) NOT NULL,
					event_category varchar(50) DEFAULT NULL COMMENT 'donation, volunteer, engagement, etc',
					event_action varchar(50) DEFAULT NULL COMMENT 'click, view, submit, etc',
					event_label varchar(255) DEFAULT NULL,
					user_id bigint(20) DEFAULT NULL,
					contact_id bigint(20) DEFAULT NULL,
					session_id varchar(100) DEFAULT NULL,
					event_value decimal(10,2) DEFAULT NULL COMMENT 'Monetary value or numeric metric',
					properties longtext DEFAULT NULL COMMENT 'JSON event properties',
					page_url varchar(500) DEFAULT NULL,
					referrer_url varchar(500) DEFAULT NULL,
					user_agent text DEFAULT NULL,
					ip_address varchar(45) DEFAULT NULL,
					device_type varchar(20) DEFAULT NULL COMMENT 'desktop, mobile, tablet',
					browser varchar(50) DEFAULT NULL,
					os varchar(50) DEFAULT NULL,
					country varchar(2) DEFAULT NULL,
					city varchar(100) DEFAULT NULL,
					synced_to_providers longtext DEFAULT NULL COMMENT 'JSON array of synced providers',
					event_timestamp datetime NOT NULL,
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY organization_id (organization_id),
					KEY event_name (event_name),
					KEY event_category (event_category),
					KEY user_id (user_id),
					KEY contact_id (contact_id),
					KEY event_timestamp (event_timestamp),
					KEY created_at (created_at)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 056: Create analytics metrics table
		array(
			'name'        => '056_create_analytics_metrics_table',
			'version'     => '1.15.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_analytics_metrics';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					metric_name varchar(100) NOT NULL,
					metric_type varchar(50) NOT NULL COMMENT 'count, sum, average, ratio, etc',
					metric_category varchar(50) DEFAULT NULL COMMENT 'donations, engagement, growth, etc',
					time_period varchar(20) NOT NULL COMMENT 'daily, weekly, monthly, yearly',
					period_date date NOT NULL COMMENT 'Start date of the period',
					metric_value decimal(15,2) NOT NULL,
					previous_value decimal(15,2) DEFAULT NULL,
					change_percent decimal(8,2) DEFAULT NULL,
					metadata longtext DEFAULT NULL COMMENT 'JSON additional data',
					calculated_at datetime DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY organization_id (organization_id),
					KEY metric_name (metric_name),
					KEY time_period (time_period),
					KEY period_date (period_date),
					UNIQUE KEY org_metric_period (organization_id, metric_name, time_period, period_date)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 057: Create analytics reports table
		array(
			'name'        => '057_create_analytics_reports_table',
			'version'     => '1.15.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_analytics_reports';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					report_name varchar(255) NOT NULL,
					report_type varchar(50) NOT NULL COMMENT 'dashboard, custom, scheduled',
					description text DEFAULT NULL,
					report_config longtext NOT NULL COMMENT 'JSON report configuration',
					metrics longtext DEFAULT NULL COMMENT 'JSON array of metrics to include',
					date_range varchar(50) DEFAULT 'last_30_days' COMMENT 'last_7_days, last_30_days, custom, etc',
					start_date date DEFAULT NULL,
					end_date date DEFAULT NULL,
					is_default tinyint(1) DEFAULT 0 COMMENT 'Default dashboard',
					is_public tinyint(1) DEFAULT 0,
					schedule_frequency varchar(20) DEFAULT NULL COMMENT 'daily, weekly, monthly',
					last_generated_at datetime DEFAULT NULL,
					recipients longtext DEFAULT NULL COMMENT 'JSON array of email recipients',
					created_by bigint(20) DEFAULT NULL,
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY organization_id (organization_id),
					KEY report_type (report_type),
					KEY created_by (created_by)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},

		// Migration 058: Create setup wizard progress table
		array(
			'name'        => '058_create_setup_wizard_progress_table',
			'version'     => '1.16.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_setup_wizard_progress';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					step_name varchar(100) NOT NULL,
					step_status enum('pending','in_progress','completed','skipped') DEFAULT 'pending',
					step_data longtext DEFAULT NULL COMMENT 'JSON data for step',
					completed_at datetime DEFAULT NULL,
					completed_by bigint(20) DEFAULT NULL,
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY organization_id (organization_id),
					KEY step_name (step_name),
					KEY step_status (step_status),
					UNIQUE KEY org_step (organization_id, step_name)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 059: Create data migration jobs table
		array(
			'name'        => '059_create_data_migration_jobs_table',
			'version'     => '1.16.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_data_migration_jobs';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					job_name varchar(255) NOT NULL,
					migration_type varchar(50) NOT NULL COMMENT 'contacts, donations, events, etc',
					source_system varchar(50) NOT NULL COMMENT 'csv, salesforce, mailchimp, etc',
					source_file varchar(500) DEFAULT NULL,
					mapping_config longtext DEFAULT NULL COMMENT 'JSON field mapping',
					job_status enum('pending','processing','completed','failed','cancelled') DEFAULT 'pending',
					total_records int(11) DEFAULT 0,
					processed_records int(11) DEFAULT 0,
					successful_records int(11) DEFAULT 0,
					failed_records int(11) DEFAULT 0,
					error_log longtext DEFAULT NULL COMMENT 'JSON array of errors',
					started_at datetime DEFAULT NULL,
					completed_at datetime DEFAULT NULL,
					created_by bigint(20) DEFAULT NULL,
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY organization_id (organization_id),
					KEY migration_type (migration_type),
					KEY job_status (job_status),
					KEY created_by (created_by)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 060: Create provider migration jobs table
		array(
			'name'        => '060_create_provider_migration_jobs_table',
			'version'     => '1.16.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_provider_migrations';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					migration_name varchar(255) NOT NULL,
					integration_type varchar(50) NOT NULL COMMENT 'crm, calendar, email, payment, video, forms, pm, sms, analytics, ai',
					source_provider varchar(100) NOT NULL,
					destination_provider varchar(100) NOT NULL,
					data_types longtext DEFAULT NULL COMMENT 'JSON array of data types to migrate',
					field_mapping longtext DEFAULT NULL COMMENT 'JSON field mapping configuration',
					migration_mode enum('preview','execute','validate') DEFAULT 'preview',
					migration_status enum('pending','analyzing','ready','running','completed','failed','cancelled','rolled_back') DEFAULT 'pending',
					total_records int(11) DEFAULT 0,
					processed_records int(11) DEFAULT 0,
					successful_records int(11) DEFAULT 0,
					failed_records int(11) DEFAULT 0,
					skipped_records int(11) DEFAULT 0,
					validation_results longtext DEFAULT NULL COMMENT 'JSON validation results',
					error_log longtext DEFAULT NULL COMMENT 'JSON array of errors',
					rollback_data longtext DEFAULT NULL COMMENT 'JSON data for rollback',
					started_at datetime DEFAULT NULL,
					completed_at datetime DEFAULT NULL,
					created_by bigint(20) DEFAULT NULL,
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY organization_id (organization_id),
					KEY integration_type (integration_type),
					KEY migration_status (migration_status),
					KEY created_at (created_at)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 061: Create public document shares table
		array(
			'name'        => '061_create_public_document_shares_table',
			'version'     => '1.17.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_public_document_shares';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					document_id bigint(20) NOT NULL,
					organization_id bigint(20) NOT NULL,
					share_token varchar(64) NOT NULL,
					share_name varchar(255) DEFAULT NULL,
					share_type enum('public','password','expiring','limited') DEFAULT 'public',
					password_hash varchar(255) DEFAULT NULL,
					expires_at datetime DEFAULT NULL,
					max_downloads int(11) DEFAULT NULL,
					current_downloads int(11) DEFAULT 0,
					permissions longtext DEFAULT NULL COMMENT 'JSON: view, download, print',
					require_email boolean DEFAULT 0,
					require_tos_acceptance boolean DEFAULT 0,
					watermark_text varchar(255) DEFAULT NULL,
					is_active boolean DEFAULT 1,
					created_by bigint(20) DEFAULT NULL,
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					UNIQUE KEY share_token (share_token),
					KEY document_id (document_id),
					KEY organization_id (organization_id),
					KEY is_active (is_active),
					KEY expires_at (expires_at)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 062: Create document access logs table
		array(
			'name'        => '062_create_document_access_logs_table',
			'version'     => '1.17.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_document_access_logs';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					document_id bigint(20) NOT NULL,
					share_id bigint(20) DEFAULT NULL,
					organization_id bigint(20) NOT NULL,
					access_type enum('view','download','preview') NOT NULL,
					user_id bigint(20) DEFAULT NULL COMMENT 'Logged in user if applicable',
					visitor_email varchar(255) DEFAULT NULL,
					ip_address varchar(45) DEFAULT NULL,
					user_agent text DEFAULT NULL,
					referer_url varchar(500) DEFAULT NULL,
					country_code varchar(2) DEFAULT NULL,
					accepted_tos boolean DEFAULT 0,
					accessed_at datetime DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY document_id (document_id),
					KEY share_id (share_id),
					KEY organization_id (organization_id),
					KEY access_type (access_type),
					KEY accessed_at (accessed_at),
					KEY ip_address (ip_address)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 063: Create document categories table
		array(
			'name'        => '063_create_document_categories_table',
			'version'     => '1.17.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_document_categories';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					category_name varchar(255) NOT NULL,
					category_slug varchar(255) NOT NULL,
					category_description text DEFAULT NULL,
					parent_category_id bigint(20) DEFAULT NULL,
					display_order int(11) DEFAULT 0,
					is_public boolean DEFAULT 1,
					icon varchar(50) DEFAULT NULL,
					color varchar(7) DEFAULT NULL,
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY organization_id (organization_id),
					KEY parent_category_id (parent_category_id),
					KEY is_public (is_public),
					KEY category_slug (category_slug)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},

		// Migration 064: Create wealth research settings table
		array(
			'name'        => '064_create_wealth_research_settings_table',
			'version'     => '1.18.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_wealth_research_settings';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					provider varchar(100) NOT NULL COMMENT 'wealthengine, donorsearch, blackbaud',
					api_key varchar(255) DEFAULT NULL,
					api_secret varchar(255) DEFAULT NULL,
					api_endpoint varchar(500) DEFAULT NULL,
					settings longtext DEFAULT NULL COMMENT 'JSON provider-specific settings',
					is_active boolean DEFAULT 1,
					monthly_limit int(11) DEFAULT NULL COMMENT 'Max API calls per month',
					current_month_usage int(11) DEFAULT 0,
					usage_reset_date date DEFAULT NULL,
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY organization_id (organization_id),
					KEY provider (provider),
					KEY is_active (is_active)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 065: Create wealth research reports table
		array(
			'name'        => '065_create_wealth_research_reports_table',
			'version'     => '1.18.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_wealth_research_reports';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					contact_id bigint(20) NOT NULL,
					provider varchar(100) NOT NULL,
					report_type varchar(50) NOT NULL COMMENT 'screening, profile, capacity_rating, philanthropy',

					-- Wealth Indicators
					estimated_income_range varchar(50) DEFAULT NULL,
					estimated_net_worth_range varchar(50) DEFAULT NULL,
					real_estate_value decimal(15,2) DEFAULT NULL,
					business_affiliations longtext DEFAULT NULL COMMENT 'JSON array',
					stock_holdings longtext DEFAULT NULL COMMENT 'JSON array',

					-- Philanthropic Indicators
					giving_capacity_rating varchar(20) DEFAULT NULL COMMENT 'A+, A, B, C, D',
					philanthropic_history longtext DEFAULT NULL COMMENT 'JSON past donations',
					board_affiliations longtext DEFAULT NULL COMMENT 'JSON nonprofit boards',
					political_contributions decimal(15,2) DEFAULT NULL,

					-- Biographical Data
					age_range varchar(20) DEFAULT NULL,
					education longtext DEFAULT NULL COMMENT 'JSON degrees',
					professional_background longtext DEFAULT NULL COMMENT 'JSON career',

					-- Social/Lifestyle
					social_media_presence longtext DEFAULT NULL COMMENT 'JSON profiles',
					interests_hobbies longtext DEFAULT NULL COMMENT 'JSON',

					-- Meta
					raw_response longtext DEFAULT NULL COMMENT 'Full API response',
					confidence_score int(11) DEFAULT NULL COMMENT '0-100',
					researched_at datetime DEFAULT CURRENT_TIMESTAMP,
					expires_at datetime DEFAULT NULL COMMENT 'Cache expiry',
					cost decimal(10,2) DEFAULT NULL,

					created_by bigint(20) DEFAULT NULL,
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

					PRIMARY KEY (id),
					KEY organization_id (organization_id),
					KEY contact_id (contact_id),
					KEY provider (provider),
					KEY report_type (report_type),
					KEY researched_at (researched_at),
					KEY expires_at (expires_at)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 066: Create background check settings table
		array(
			'name'        => '066_create_background_check_settings_table',
			'version'     => '1.18.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_background_check_settings';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					provider varchar(100) NOT NULL COMMENT 'checkr, sterling, goodhire, accurate',
					api_key varchar(255) DEFAULT NULL,
					api_secret varchar(255) DEFAULT NULL,
					webhook_secret varchar(255) DEFAULT NULL,
					api_endpoint varchar(500) DEFAULT NULL,
					settings longtext DEFAULT NULL COMMENT 'JSON provider-specific settings',

					-- Default Check Packages
					default_volunteer_package varchar(100) DEFAULT NULL,
					default_staff_package varchar(100) DEFAULT NULL,
					default_board_package varchar(100) DEFAULT NULL,

					is_active boolean DEFAULT 1,
					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY organization_id (organization_id),
					KEY provider (provider),
					KEY is_active (is_active)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),

		// Migration 067: Create background check requests table
		array(
			'name'        => '067_create_background_check_requests_table',
			'version'     => '1.18.0',
			'min_version' => '1.0.0',
			'callback'    => function() {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table           = $wpdb->prefix . 'ns_background_check_requests';

				$sql = "CREATE TABLE {$table} (
					id bigint(20) NOT NULL AUTO_INCREMENT,
					organization_id bigint(20) NOT NULL,
					contact_id bigint(20) DEFAULT NULL COMMENT 'Link to contact being checked',
					user_id bigint(20) DEFAULT NULL COMMENT 'WP user if volunteer/staff',
					provider varchar(100) NOT NULL,
					provider_request_id varchar(255) DEFAULT NULL COMMENT 'Provider reference ID',

					-- Request Details
					check_type varchar(50) NOT NULL COMMENT 'volunteer, staff, board, contractor',
					package_name varchar(100) DEFAULT NULL COMMENT 'Basic, Standard, Premium, etc',
					check_components longtext DEFAULT NULL COMMENT 'JSON: criminal, credit, motor_vehicle, education, employment',

					-- Subject Information
					candidate_email varchar(255) DEFAULT NULL,
					candidate_first_name varchar(100) DEFAULT NULL,
					candidate_last_name varchar(100) DEFAULT NULL,
					candidate_dob date DEFAULT NULL,
					candidate_ssn_last_4 varchar(4) DEFAULT NULL,
					candidate_phone varchar(20) DEFAULT NULL,
					candidate_zipcode varchar(10) DEFAULT NULL,

					-- Status
					request_status enum('pending','sent','in_progress','completed','disputed','cancelled') DEFAULT 'pending',
					consent_given boolean DEFAULT 0,
					consent_given_at datetime DEFAULT NULL,
					consent_ip_address varchar(45) DEFAULT NULL,

					-- Results
					overall_status varchar(50) DEFAULT NULL COMMENT 'clear, consider, suspended',
					completion_percentage int(11) DEFAULT 0,
					adjudication varchar(50) DEFAULT NULL COMMENT 'engaged, pre_adverse, adverse, approved',

					-- Components Results
					criminal_records_status varchar(50) DEFAULT NULL,
					criminal_records_found boolean DEFAULT 0,
					motor_vehicle_status varchar(50) DEFAULT NULL,
					education_verified boolean DEFAULT NULL,
					employment_verified boolean DEFAULT NULL,

					-- Report URLs
					report_url varchar(500) DEFAULT NULL,
					candidate_portal_url varchar(500) DEFAULT NULL,

					-- Costs & Timing
					cost decimal(10,2) DEFAULT NULL,
					estimated_completion_date datetime DEFAULT NULL,
					completed_at datetime DEFAULT NULL,

					-- Audit
					requested_by bigint(20) DEFAULT NULL,
					reviewed_by bigint(20) DEFAULT NULL,
					reviewed_at datetime DEFAULT NULL,
					review_notes text DEFAULT NULL,

					created_at datetime DEFAULT CURRENT_TIMESTAMP,
					updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

					PRIMARY KEY (id),
					KEY organization_id (organization_id),
					KEY contact_id (contact_id),
					KEY provider (provider),
					KEY request_status (request_status),
					KEY created_at (created_at)
				) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			},
		),
		),
		);
	}
}
