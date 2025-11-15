<?php
/**
 * Migration Manager
 *
 * Handles data imports and migrations from other systems.
 *
 * @package NonprofitSuite
 * @subpackage Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_Migration_Manager {
	/**
	 * Singleton instance.
	 *
	 * @var NS_Migration_Manager
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return NS_Migration_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Create a migration job.
	 *
	 * @param array $job_data Job configuration.
	 * @return int|WP_Error Job ID or error.
	 */
	public function create_job( $job_data ) {
		global $wpdb;

		$defaults = array(
			'organization_id' => 1,
			'job_name'        => '',
			'migration_type'  => 'contacts',
			'source_system'   => 'csv',
			'source_file'     => '',
			'mapping_config'  => array(),
		);

		$job_data = wp_parse_args( $job_data, $defaults );

		$table = $wpdb->prefix . 'ns_data_migration_jobs';

		$inserted = $wpdb->insert(
			$table,
			array(
				'organization_id' => $job_data['organization_id'],
				'job_name'        => $job_data['job_name'],
				'migration_type'  => $job_data['migration_type'],
				'source_system'   => $job_data['source_system'],
				'source_file'     => $job_data['source_file'],
				'mapping_config'  => wp_json_encode( $job_data['mapping_config'] ),
				'job_status'      => 'pending',
				'created_by'      => get_current_user_id(),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'db_error', __( 'Failed to create migration job.', 'nonprofitsuite' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Process a migration job.
	 *
	 * @param int $job_id Job ID.
	 * @return bool|WP_Error Success or error.
	 */
	public function process_job( $job_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_data_migration_jobs';
		$job   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $job_id ),
			ARRAY_A
		);

		if ( ! $job ) {
			return new WP_Error( 'job_not_found', __( 'Migration job not found.', 'nonprofitsuite' ) );
		}

		// Update status to processing
		$wpdb->update(
			$table,
			array(
				'job_status' => 'processing',
				'started_at' => current_time( 'mysql' ),
			),
			array( 'id' => $job_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		try {
			$result = $this->run_migration( $job );

			// Update job with results
			$wpdb->update(
				$table,
				array(
					'job_status'         => 'completed',
					'completed_at'       => current_time( 'mysql' ),
					'total_records'      => $result['total'],
					'processed_records'  => $result['processed'],
					'successful_records' => $result['successful'],
					'failed_records'     => $result['failed'],
					'error_log'          => wp_json_encode( $result['errors'] ),
				),
				array( 'id' => $job_id ),
				array( '%s', '%s', '%d', '%d', '%d', '%d', '%s' ),
				array( '%d' )
			);

			return true;
		} catch ( Exception $e ) {
			// Update job as failed
			$wpdb->update(
				$table,
				array(
					'job_status' => 'failed',
					'error_log'  => wp_json_encode( array( $e->getMessage() ) ),
				),
				array( 'id' => $job_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			return new WP_Error( 'migration_failed', $e->getMessage() );
		}
	}

	/**
	 * Run the migration.
	 *
	 * @param array $job Job data.
	 * @return array Results.
	 */
	private function run_migration( $job ) {
		$mapping_config = json_decode( $job['mapping_config'], true );

		switch ( $job['source_system'] ) {
			case 'csv':
				return $this->import_from_csv( $job, $mapping_config );

			default:
				throw new Exception( sprintf( __( 'Unsupported source system: %s', 'nonprofitsuite' ), $job['source_system'] ) );
		}
	}

	/**
	 * Import from CSV file.
	 *
	 * @param array $job Job data.
	 * @param array $mapping Field mapping.
	 * @return array Results.
	 */
	private function import_from_csv( $job, $mapping ) {
		$file_path = $job['source_file'];

		if ( ! file_exists( $file_path ) ) {
			throw new Exception( __( 'Source file not found.', 'nonprofitsuite' ) );
		}

		$results = array(
			'total'      => 0,
			'processed'  => 0,
			'successful' => 0,
			'failed'     => 0,
			'errors'     => array(),
		);

		$handle = fopen( $file_path, 'r' );

		if ( ! $handle ) {
			throw new Exception( __( 'Failed to open CSV file.', 'nonprofitsuite' ) );
		}

		// Read header row
		$headers = fgetcsv( $handle );

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$results['total']++;

			$data = array_combine( $headers, $row );
			$mapped_data = $this->map_row( $data, $mapping );

			try {
				$this->import_record( $job['migration_type'], $mapped_data, $job['organization_id'] );
				$results['successful']++;
			} catch ( Exception $e ) {
				$results['failed']++;
				$results['errors'][] = array(
					'row'     => $results['total'],
					'message' => $e->getMessage(),
				);
			}

			$results['processed']++;

			// Prevent timeouts on large files
			if ( $results['processed'] % 100 === 0 ) {
				usleep( 10000 ); // 0.01 seconds
			}
		}

		fclose( $handle );

		return $results;
	}

	/**
	 * Map a row using the mapping configuration.
	 *
	 * @param array $row CSV row data.
	 * @param array $mapping Field mapping.
	 * @return array Mapped data.
	 */
	private function map_row( $row, $mapping ) {
		$mapped = array();

		foreach ( $mapping as $target_field => $source_field ) {
			if ( isset( $row[ $source_field ] ) ) {
				$mapped[ $target_field ] = $row[ $source_field ];
			}
		}

		return $mapped;
	}

	/**
	 * Import a single record.
	 *
	 * @param string $type Record type.
	 * @param array  $data Record data.
	 * @param int    $organization_id Organization ID.
	 * @throws Exception If import fails.
	 */
	private function import_record( $type, $data, $organization_id ) {
		global $wpdb;

		switch ( $type ) {
			case 'contacts':
				$this->import_contact( $data, $organization_id );
				break;

			case 'donations':
				$this->import_donation( $data, $organization_id );
				break;

			case 'events':
				$this->import_event( $data, $organization_id );
				break;

			default:
				throw new Exception( sprintf( __( 'Unsupported migration type: %s', 'nonprofitsuite' ), $type ) );
		}
	}

	/**
	 * Import a contact.
	 *
	 * @param array $data Contact data.
	 * @param int   $organization_id Organization ID.
	 */
	private function import_contact( $data, $organization_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_contacts';

		$wpdb->insert(
			$table,
			array(
				'organization_id' => $organization_id,
				'first_name'      => $data['first_name'] ?? '',
				'last_name'       => $data['last_name'] ?? '',
				'email'           => $data['email'] ?? '',
				'phone'           => $data['phone'] ?? '',
				'contact_type'    => $data['contact_type'] ?? 'individual',
				'status'          => 'active',
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Import a donation.
	 *
	 * @param array $data Donation data.
	 * @param int   $organization_id Organization ID.
	 */
	private function import_donation( $data, $organization_id ) {
		global $wpdb;

		// Find or create contact
		$contact_id = null;
		if ( ! empty( $data['email'] ) ) {
			$contacts_table = $wpdb->prefix . 'ns_contacts';
			$contact_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$contacts_table} WHERE organization_id = %d AND email = %s LIMIT 1",
					$organization_id,
					$data['email']
				)
			);
		}

		$table = $wpdb->prefix . 'ns_payment_transactions';

		$wpdb->insert(
			$table,
			array(
				'organization_id' => $organization_id,
				'contact_id'      => $contact_id,
				'amount'          => floatval( $data['amount'] ?? 0 ),
				'currency'        => $data['currency'] ?? 'USD',
				'status'          => 'completed',
				'payment_method'  => $data['payment_method'] ?? 'imported',
				'transaction_type' => 'donation',
				'transaction_date' => $data['date'] ?? current_time( 'mysql' ),
			),
			array( '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Import an event.
	 *
	 * @param array $data Event data.
	 * @param int   $organization_id Organization ID.
	 */
	private function import_event( $data, $organization_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_calendar_events';

		$wpdb->insert(
			$table,
			array(
				'organization_id' => $organization_id,
				'event_title'     => $data['title'] ?? '',
				'description'     => $data['description'] ?? '',
				'start_datetime'  => $data['start_date'] ?? current_time( 'mysql' ),
				'end_datetime'    => $data['end_date'] ?? current_time( 'mysql' ),
				'event_type'      => $data['type'] ?? 'event',
				'status'          => 'confirmed',
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get job status.
	 *
	 * @param int $job_id Job ID.
	 * @return array|null Job data.
	 */
	public function get_job_status( $job_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_data_migration_jobs';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $job_id ),
			ARRAY_A
		);
	}

	/**
	 * Get all jobs for an organization.
	 *
	 * @param int $organization_id Organization ID.
	 * @return array Jobs.
	 */
	public function get_jobs( $organization_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_data_migration_jobs';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE organization_id = %d ORDER BY created_at DESC",
				$organization_id
			),
			ARRAY_A
		);
	}
}
