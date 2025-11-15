<?php
/**
 * Integration Migrator
 *
 * Handles data migration between integration providers.
 *
 * @package    NonprofitSuite
 * @subpackage Integrations
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonprofitSuite_Integration_Migrator Class
 *
 * Manages provider switching and data migration.
 */
class NonprofitSuite_Integration_Migrator {

	/**
	 * Integration Manager instance
	 *
	 * @var NonprofitSuite_Integration_Manager
	 */
	private $manager;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->manager = NonprofitSuite_Integration_Manager::get_instance();
	}

	/**
	 * Migrate storage files between providers
	 *
	 * @param string $from_provider_id Source provider ID
	 * @param string $to_provider_id   Destination provider ID
	 * @param array  $args             Migration arguments
	 *                                 - batch_size: Number of files per batch (default 50)
	 *                                 - verify: Verify checksums after migration (default true)
	 *                                 - delete_source: Delete from source after migration (default false)
	 * @return array|WP_Error Migration result
	 */
	public function migrate_storage( $from_provider_id, $to_provider_id, $args = array() ) {
		$args = wp_parse_args( $args, array(
			'batch_size'    => 50,
			'verify'        => true,
			'delete_source' => false,
		) );

		// TODO: Implement storage migration
		// 1. Get list of files from source provider
		// 2. Upload files to destination provider in batches
		// 3. Verify checksums if requested
		// 4. Update database references
		// 5. Delete from source if requested

		return new WP_Error( 'not_implemented', __( 'Storage migration coming in Phase 2', 'nonprofitsuite' ) );
	}

	/**
	 * Switch provider for a category
	 *
	 * This is a high-level method that handles the complete provider switch process,
	 * including data migration if needed.
	 *
	 * @param string $category        Category slug
	 * @param string $new_provider_id New provider ID
	 * @param array  $args            Switch arguments
	 * @return array|WP_Error Switch result
	 */
	public function switch_provider( $category, $new_provider_id, $args = array() ) {
		$old_provider_id = $this->manager->get_active_provider_id( $category );

		// Validate new provider exists
		$new_provider = $this->manager->get_provider( $category, $new_provider_id );
		if ( ! $new_provider ) {
			return new WP_Error( 'invalid_provider', __( 'Invalid provider', 'nonprofitsuite' ) );
		}

		// Check if migration is needed
		$needs_migration = ! empty( $args['migrate_data'] ) && $old_provider_id !== $new_provider_id;

		$result = array(
			'old_provider'     => $old_provider_id,
			'new_provider'     => $new_provider_id,
			'migration_needed' => $needs_migration,
			'migrated_items'   => 0,
			'errors'           => array(),
		);

		// Perform migration if needed
		if ( $needs_migration ) {
			switch ( $category ) {
				case 'storage':
					$migration_result = $this->migrate_storage( $old_provider_id, $new_provider_id, $args );
					if ( is_wp_error( $migration_result ) ) {
						$result['errors'][] = $migration_result->get_error_message();
					} else {
						$result['migrated_items'] = $migration_result['migrated_count'];
					}
					break;

				case 'calendar':
					// Calendar migration not implemented yet
					break;

				default:
					// Other categories don't require data migration
					break;
			}
		}

		// Switch active provider
		$switch_result = $this->manager->set_active_provider( $category, $new_provider_id );

		if ( is_wp_error( $switch_result ) ) {
			return $switch_result;
		}

		/**
		 * Fires after provider is switched
		 *
		 * @param string $category        Category
		 * @param string $new_provider_id New provider ID
		 * @param string $old_provider_id Old provider ID
		 * @param array  $result          Switch result
		 */
		do_action( 'ns_provider_switched', $category, $new_provider_id, $old_provider_id, $result );

		return $result;
	}

	/**
	 * Create migration job
	 *
	 * For long-running migrations, create a background job.
	 *
	 * @param string $category        Category slug
	 * @param string $from_provider   Source provider ID
	 * @param string $to_provider     Destination provider ID
	 * @param array  $args            Migration arguments
	 * @return array|WP_Error Job data with keys: job_id
	 */
	public function create_migration_job( $category, $from_provider, $to_provider, $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_migration_jobs';

		// Check if table exists
		$exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
		if ( ! $exists ) {
			return new WP_Error( 'table_missing', __( 'Migration jobs table does not exist', 'nonprofitsuite' ) );
		}

		$job = array(
			'category'      => $category,
			'from_provider' => $from_provider,
			'to_provider'   => $to_provider,
			'status'        => 'pending',
			'total_items'   => 0,
			'processed'     => 0,
			'failed'        => 0,
			'settings'      => json_encode( $args ),
			'created_at'    => current_time( 'mysql' ),
		);

		$wpdb->insert( $table, $job );

		if ( $wpdb->last_error ) {
			return new WP_Error( 'db_error', $wpdb->last_error );
		}

		return array(
			'job_id' => $wpdb->insert_id,
		);
	}

	/**
	 * Get migration job status
	 *
	 * @param int $job_id Job ID
	 * @return array|WP_Error Job data
	 */
	public function get_migration_job_status( $job_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_migration_jobs';

		$job = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$job_id
		), ARRAY_A );

		if ( ! $job ) {
			return new WP_Error( 'job_not_found', __( 'Migration job not found', 'nonprofitsuite' ) );
		}

		$job['settings'] = json_decode( $job['settings'], true );

		// Calculate progress percentage
		$job['progress'] = $job['total_items'] > 0
			? round( ( $job['processed'] / $job['total_items'] ) * 100 )
			: 0;

		return $job;
	}

	/**
	 * Cancel migration job
	 *
	 * @param int $job_id Job ID
	 * @return bool|WP_Error True on success
	 */
	public function cancel_migration_job( $job_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_migration_jobs';

		$result = $wpdb->update(
			$table,
			array(
				'status'      => 'cancelled',
				'completed_at' => current_time( 'mysql' ),
			),
			array( 'id' => $job_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result ? true : new WP_Error( 'update_failed', __( 'Failed to cancel job', 'nonprofitsuite' ) );
	}

	/**
	 * Rollback provider switch
	 *
	 * Switch back to previous provider if migration fails.
	 *
	 * @param string $category        Category slug
	 * @param string $old_provider_id Previous provider ID
	 * @return bool|WP_Error True on success
	 */
	public function rollback_provider_switch( $category, $old_provider_id ) {
		$result = $this->manager->set_active_provider( $category, $old_provider_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		/**
		 * Fires after provider switch is rolled back
		 *
		 * @param string $category        Category
		 * @param string $old_provider_id Provider ID restored
		 */
		do_action( 'ns_provider_switch_rollback', $category, $old_provider_id );

		return true;
	}

	/**
	 * Verify data integrity after migration
	 *
	 * @param string $category Category slug
	 * @param array  $args     Verification arguments
	 * @return array|WP_Error Verification result
	 */
	public function verify_migration( $category, $args = array() ) {
		// TODO: Implement verification logic
		// 1. Compare item counts between providers
		// 2. Verify checksums for sample of items
		// 3. Check for missing data

		return new WP_Error( 'not_implemented', __( 'Verification coming in Phase 2', 'nonprofitsuite' ) );
	}
}
