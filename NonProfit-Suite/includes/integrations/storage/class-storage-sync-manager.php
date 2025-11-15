<?php
/**
 * Storage Sync Manager
 *
 * Coordinates file synchronization between storage tiers (cloud, cache, local).
 * Handles background sync operations, queue processing, and conflict resolution.
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
 * NonprofitSuite_Storage_Sync_Manager Class
 *
 * Manages file synchronization across storage tiers.
 */
class NonprofitSuite_Storage_Sync_Manager {

	/**
	 * Queue a file sync operation
	 *
	 * @param int    $file_id    File ID
	 * @param int    $version_id Version ID
	 * @param string $operation  Operation type (upload, delete, sync, verify)
	 * @param string $from_tier  Source tier (optional)
	 * @param string $to_tier    Destination tier
	 * @param array  $args       Additional arguments
	 * @return int|WP_Error Queue ID or WP_Error
	 */
	public function queue_sync( $file_id, $version_id, $operation, $from_tier, $to_tier, $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_storage_sync_queue';

		$args = wp_parse_args( $args, array(
			'priority'     => 5,
			'max_attempts' => 3,
		) );

		$queue_data = array(
			'file_id'      => $file_id,
			'version_id'   => $version_id,
			'operation'    => $operation,
			'from_tier'    => $from_tier,
			'to_tier'      => $to_tier,
			'priority'     => $args['priority'],
			'status'       => 'pending',
			'attempts'     => 0,
			'max_attempts' => $args['max_attempts'],
			'scheduled_at' => current_time( 'mysql' ),
			'created_at'   => current_time( 'mysql' ),
		);

		$wpdb->insert( $table, $queue_data );

		if ( $wpdb->last_error ) {
			return new WP_Error( 'db_error', $wpdb->last_error );
		}

		$queue_id = $wpdb->insert_id;

		do_action( 'ns_storage_sync_queued', $queue_id, $file_id, $operation );

		return $queue_id;
	}

	/**
	 * Process sync queue
	 *
	 * @param int $limit Maximum number of items to process
	 * @return array Processing results
	 */
	public function process_queue( $limit = 10 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_storage_sync_queue';

		// Get pending items by priority
		$items = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table}
			WHERE status = 'pending'
			AND scheduled_at <= NOW()
			AND attempts < max_attempts
			ORDER BY priority ASC, created_at ASC
			LIMIT %d",
			$limit
		), ARRAY_A );

		$results = array(
			'processed' => 0,
			'succeeded' => 0,
			'failed'    => 0,
			'errors'    => array(),
		);

		foreach ( $items as $item ) {
			$result = $this->process_sync_item( $item );
			$results['processed']++;

			if ( is_wp_error( $result ) ) {
				$results['failed']++;
				$results['errors'][] = array(
					'queue_id' => $item['id'],
					'error'    => $result->get_error_message(),
				);
			} else {
				$results['succeeded']++;
			}
		}

		return $results;
	}

	/**
	 * Process a single sync item
	 *
	 * @param array $item Queue item
	 * @return bool|WP_Error True on success
	 */
	private function process_sync_item( $item ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_storage_sync_queue';

		// Mark as processing
		$wpdb->update(
			$table,
			array(
				'status'     => 'processing',
				'started_at' => current_time( 'mysql' ),
				'attempts'   => $item['attempts'] + 1,
			),
			array( 'id' => $item['id'] ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);

		// Process based on operation type
		$result = null;

		switch ( $item['operation'] ) {
			case 'upload':
				$result = $this->sync_upload( $item );
				break;

			case 'delete':
				$result = $this->sync_delete( $item );
				break;

			case 'sync':
				$result = $this->sync_file( $item );
				break;

			case 'verify':
				$result = $this->verify_sync( $item );
				break;

			default:
				$result = new WP_Error( 'invalid_operation', __( 'Invalid sync operation', 'nonprofitsuite' ) );
		}

		// Update queue status
		if ( is_wp_error( $result ) ) {
			// Check if max attempts reached
			if ( $item['attempts'] + 1 >= $item['max_attempts'] ) {
				$wpdb->update(
					$table,
					array(
						'status'        => 'failed',
						'error_message' => $result->get_error_message(),
						'completed_at'  => current_time( 'mysql' ),
					),
					array( 'id' => $item['id'] ),
					array( '%s', '%s', '%s' ),
					array( '%d' )
				);
			} else {
				// Retry later
				$wpdb->update(
					$table,
					array(
						'status'        => 'pending',
						'error_message' => $result->get_error_message(),
						'scheduled_at'  => date( 'Y-m-d H:i:s', strtotime( '+5 minutes' ) ),
					),
					array( 'id' => $item['id'] ),
					array( '%s', '%s', '%s' ),
					array( '%d' )
				);
			}
		} else {
			// Success
			$wpdb->update(
				$table,
				array(
					'status'       => 'completed',
					'completed_at' => current_time( 'mysql' ),
				),
				array( 'id' => $item['id'] ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}

		return $result;
	}

	/**
	 * Sync upload operation
	 *
	 * @param array $item Queue item
	 * @return bool|WP_Error True on success
	 */
	private function sync_upload( $item ) {
		// Get file from source tier and upload to destination tier
		$manager = NonprofitSuite_Integration_Manager::get_instance();

		// Get source location
		$source_location = $this->get_file_location( $item['file_id'], $item['version_id'], $item['from_tier'] );

		if ( ! $source_location ) {
			return new WP_Error( 'source_not_found', __( 'Source file not found', 'nonprofitsuite' ) );
		}

		// Download from source to temp
		$source_adapter = $this->get_tier_adapter( $item['from_tier'] );
		if ( is_wp_error( $source_adapter ) ) {
			return $source_adapter;
		}

		$temp_file = $source_adapter->download( $source_location['provider_file_id'] );
		if ( is_wp_error( $temp_file ) ) {
			return $temp_file;
		}

		// Upload to destination
		$dest_adapter = $this->get_tier_adapter( $item['to_tier'] );
		if ( is_wp_error( $dest_adapter ) ) {
			@unlink( $temp_file );
			return $dest_adapter;
		}

		$result = $dest_adapter->upload( $temp_file, array(
			'folder'   => dirname( $source_location['provider_path'] ),
			'filename' => basename( $source_location['provider_path'] ),
		) );

		@unlink( $temp_file );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Record new location
		$this->record_file_location( $item['file_id'], $item['version_id'], $item['to_tier'], $result );

		return true;
	}

	/**
	 * Sync delete operation
	 *
	 * @param array $item Queue item
	 * @return bool|WP_Error True on success
	 */
	private function sync_delete( $item ) {
		$location = $this->get_file_location( $item['file_id'], $item['version_id'], $item['to_tier'] );

		if ( ! $location ) {
			return true; // Already deleted
		}

		$adapter = $this->get_tier_adapter( $item['to_tier'] );
		if ( is_wp_error( $adapter ) ) {
			return $adapter;
		}

		$result = $adapter->delete( $location['provider_file_id'] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Delete location record
		global $wpdb;
		$table = $wpdb->prefix . 'ns_storage_locations';
		$wpdb->delete( $table, array( 'id' => $location['id'] ), array( '%d' ) );

		return true;
	}

	/**
	 * Sync file between tiers
	 *
	 * @param array $item Queue item
	 * @return bool|WP_Error True on success
	 */
	private function sync_file( $item ) {
		return $this->sync_upload( $item );
	}

	/**
	 * Verify sync integrity
	 *
	 * @param array $item Queue item
	 * @return bool|WP_Error True on success
	 */
	private function verify_sync( $item ) {
		global $wpdb;

		// Get both locations
		$source_location = $this->get_file_location( $item['file_id'], $item['version_id'], $item['from_tier'] );
		$dest_location = $this->get_file_location( $item['file_id'], $item['version_id'], $item['to_tier'] );

		if ( ! $source_location || ! $dest_location ) {
			return new WP_Error( 'location_not_found', __( 'Locations not found', 'nonprofitsuite' ) );
		}

		// Get metadata from both
		$source_adapter = $this->get_tier_adapter( $item['from_tier'] );
		$dest_adapter = $this->get_tier_adapter( $item['to_tier'] );

		$source_meta = $source_adapter->get_metadata( $source_location['provider_file_id'] );
		$dest_meta = $dest_adapter->get_metadata( $dest_location['provider_file_id'] );

		if ( is_wp_error( $source_meta ) || is_wp_error( $dest_meta ) ) {
			return new WP_Error( 'metadata_failed', __( 'Failed to get metadata', 'nonprofitsuite' ) );
		}

		// Compare sizes
		if ( $source_meta['size'] !== $dest_meta['size'] ) {
			return new WP_Error( 'size_mismatch', __( 'File sizes do not match', 'nonprofitsuite' ) );
		}

		// Update verification timestamp
		$table = $wpdb->prefix . 'ns_storage_locations';
		$wpdb->update(
			$table,
			array( 'last_verified_at' => current_time( 'mysql' ) ),
			array( 'id' => $dest_location['id'] ),
			array( '%s' ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Get file location for tier
	 *
	 * @param int    $file_id    File ID
	 * @param int    $version_id Version ID
	 * @param string $tier       Tier name
	 * @return array|null Location data
	 */
	private function get_file_location( $file_id, $version_id, $tier ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_storage_locations';

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE file_id = %d AND version_id = %d AND tier = %s LIMIT 1",
			$file_id,
			$version_id,
			$tier
		), ARRAY_A );
	}

	/**
	 * Record file location
	 *
	 * @param int    $file_id    File ID
	 * @param int    $version_id Version ID
	 * @param string $tier       Tier name
	 * @param array  $result     Upload result
	 * @return int|false Location ID
	 */
	private function record_file_location( $file_id, $version_id, $tier, $result ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_storage_locations';

		$manager = NonprofitSuite_Integration_Manager::get_instance();
		$provider_name = $tier; // Simplified for now

		$location_data = array(
			'file_id'          => $file_id,
			'version_id'       => $version_id,
			'tier'             => $tier,
			'provider'         => $provider_name,
			'provider_file_id' => isset( $result['file_id'] ) ? $result['file_id'] : '',
			'provider_path'    => isset( $result['path'] ) ? $result['path'] : '',
			'provider_url'     => isset( $result['url'] ) ? $result['url'] : '',
			'cdn_url'          => isset( $result['cdn_url'] ) ? $result['cdn_url'] : null,
			'sync_status'      => 'synced',
			'last_synced_at'   => current_time( 'mysql' ),
			'created_at'       => current_time( 'mysql' ),
		);

		$wpdb->insert( $table, $location_data );

		return $wpdb->insert_id;
	}

	/**
	 * Get adapter for tier
	 *
	 * @param string $tier Tier name
	 * @return object|WP_Error Adapter instance
	 */
	private function get_tier_adapter( $tier ) {
		$manager = NonprofitSuite_Integration_Manager::get_instance();

		switch ( $tier ) {
			case 'cloud':
			case 'cdn':
				return $manager->get_active_provider( 'storage' );

			case 'local':
			case 'cache':
				return new NonprofitSuite_Storage_Local_Adapter();

			default:
				return new WP_Error( 'invalid_tier', __( 'Invalid storage tier', 'nonprofitsuite' ) );
		}
	}

	/**
	 * Get queue statistics
	 *
	 * @return array Queue stats
	 */
	public function get_queue_stats() {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_storage_sync_queue';

		return array(
			'pending'    => $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" ),
			'processing' => $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'processing'" ),
			'completed'  => $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'completed'" ),
			'failed'     => $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'failed'" ),
		);
	}

	/**
	 * Clean completed queue items
	 *
	 * @param int $days_old Delete items older than this many days
	 * @return int Number of items deleted
	 */
	public function clean_queue( $days_old = 7 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_storage_sync_queue';

		$result = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table}
			WHERE status IN ('completed', 'failed')
			AND completed_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
			$days_old
		) );

		return $result ? $result : 0;
	}
}
