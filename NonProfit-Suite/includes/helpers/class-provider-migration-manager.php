<?php
/**
 * Provider Migration Manager
 *
 * Handles migration of data between integration providers.
 * Supports migrating from one provider to another (e.g., Salesforce to HubSpot).
 *
 * @package NonprofitSuite
 * @subpackage Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_Provider_Migration_Manager {

	/**
	 * Singleton instance.
	 *
	 * @var NS_Provider_Migration_Manager
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return NS_Provider_Migration_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {
		// Load necessary managers
		$this->load_dependencies();
	}

	/**
	 * Load required manager classes.
	 */
	private function load_dependencies() {
		require_once NS_PLUGIN_DIR . 'includes/helpers/class-crm-manager.php';
		require_once NS_PLUGIN_DIR . 'includes/helpers/class-calendar-manager.php';
		require_once NS_PLUGIN_DIR . 'includes/helpers/class-email-manager.php';
		require_once NS_PLUGIN_DIR . 'includes/helpers/class-payment-manager.php';
		require_once NS_PLUGIN_DIR . 'includes/helpers/class-video-conferencing-manager.php';
		require_once NS_PLUGIN_DIR . 'includes/helpers/class-forms-manager.php';
		require_once NS_PLUGIN_DIR . 'includes/helpers/class-project-manager.php';
		require_once NS_PLUGIN_DIR . 'includes/helpers/class-sms-manager.php';
		require_once NS_PLUGIN_DIR . 'includes/helpers/class-analytics-manager.php';
		require_once NS_PLUGIN_DIR . 'includes/helpers/class-ai-manager.php';
	}

	/**
	 * Get supported integration types.
	 *
	 * @return array Integration types with their supported data types.
	 */
	public function get_supported_integrations() {
		return array(
			'crm'       => array(
				'label'      => __( 'CRM', 'nonprofitsuite' ),
				'data_types' => array( 'contacts', 'donations', 'memberships', 'activities' ),
				'providers'  => array( 'salesforce', 'hubspot', 'bloomerang' ),
			),
			'calendar'  => array(
				'label'      => __( 'Calendar', 'nonprofitsuite' ),
				'data_types' => array( 'events', 'reminders', 'schedules' ),
				'providers'  => array( 'google_calendar', 'outlook_calendar', 'icloud_calendar' ),
			),
			'email'     => array(
				'label'      => __( 'Email Marketing', 'nonprofitsuite' ),
				'data_types' => array( 'contacts', 'segments', 'campaigns', 'templates' ),
				'providers'  => array( 'mailchimp', 'constant_contact', 'sendinblue' ),
			),
			'payment'   => array(
				'label'      => __( 'Payment Processing', 'nonprofitsuite' ),
				'data_types' => array( 'payment_methods', 'recurring_donations', 'customers' ),
				'providers'  => array( 'stripe', 'paypal', 'square' ),
			),
			'video'     => array(
				'label'      => __( 'Video Conferencing', 'nonprofitsuite' ),
				'data_types' => array( 'meeting_templates', 'recordings', 'settings' ),
				'providers'  => array( 'zoom', 'google_meet', 'microsoft_teams' ),
			),
			'forms'     => array(
				'label'      => __( 'Forms & Surveys', 'nonprofitsuite' ),
				'data_types' => array( 'forms', 'submissions', 'form_fields' ),
				'providers'  => array( 'google_forms', 'typeform', 'jotform' ),
			),
			'pm'        => array(
				'label'      => __( 'Project Management', 'nonprofitsuite' ),
				'data_types' => array( 'projects', 'tasks', 'team_members', 'comments' ),
				'providers'  => array( 'asana', 'trello', 'monday' ),
			),
			'sms'       => array(
				'label'      => __( 'SMS Messaging', 'nonprofitsuite' ),
				'data_types' => array( 'phone_numbers', 'message_templates', 'opt_outs' ),
				'providers'  => array( 'twilio', 'plivo', 'vonage' ),
			),
			'analytics' => array(
				'label'      => __( 'Analytics', 'nonprofitsuite' ),
				'data_types' => array( 'events', 'user_properties', 'conversions' ),
				'providers'  => array( 'google_analytics', 'mixpanel', 'segment' ),
			),
			'ai'        => array(
				'label'      => __( 'AI & Automation', 'nonprofitsuite' ),
				'data_types' => array( 'conversations', 'automation_rules', 'prompts' ),
				'providers'  => array( 'openai', 'anthropic', 'google_ai' ),
			),
		);
	}

	/**
	 * Create a new provider migration job.
	 *
	 * @param array $args Migration job arguments.
	 * @return int|WP_Error Job ID on success, WP_Error on failure.
	 */
	public function create_migration_job( $args ) {
		global $wpdb;

		$defaults = array(
			'organization_id'      => 0,
			'migration_name'       => '',
			'integration_type'     => '',
			'source_provider'      => '',
			'destination_provider' => '',
			'data_types'           => array(),
			'field_mapping'        => array(),
			'migration_mode'       => 'preview',
			'created_by'           => get_current_user_id(),
		);

		$data = wp_parse_args( $args, $defaults );

		// Validate
		if ( empty( $data['organization_id'] ) || empty( $data['integration_type'] ) ||
		     empty( $data['source_provider'] ) || empty( $data['destination_provider'] ) ) {
			return new WP_Error( 'invalid_data', __( 'Missing required fields', 'nonprofitsuite' ) );
		}

		// Insert job
		$table = $wpdb->prefix . 'ns_provider_migrations';

		$inserted = $wpdb->insert(
			$table,
			array(
				'organization_id'      => $data['organization_id'],
				'migration_name'       => $data['migration_name'],
				'integration_type'     => $data['integration_type'],
				'source_provider'      => $data['source_provider'],
				'destination_provider' => $data['destination_provider'],
				'data_types'           => wp_json_encode( $data['data_types'] ),
				'field_mapping'        => wp_json_encode( $data['field_mapping'] ),
				'migration_mode'       => $data['migration_mode'],
				'migration_status'     => 'pending',
				'created_by'           => $data['created_by'],
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'db_error', __( 'Failed to create migration job', 'nonprofitsuite' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get migration job by ID.
	 *
	 * @param int $job_id Job ID.
	 * @return array|null Job data or null if not found.
	 */
	public function get_job( $job_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_provider_migrations';
		$job   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $job_id ),
			ARRAY_A
		);

		if ( $job ) {
			// Decode JSON fields
			$job['data_types']          = json_decode( $job['data_types'], true );
			$job['field_mapping']       = json_decode( $job['field_mapping'], true );
			$job['validation_results']  = json_decode( $job['validation_results'], true );
			$job['error_log']           = json_decode( $job['error_log'], true );
			$job['rollback_data']       = json_decode( $job['rollback_data'], true );
		}

		return $job;
	}

	/**
	 * Get all migration jobs for an organization.
	 *
	 * @param int $organization_id Organization ID.
	 * @return array Jobs.
	 */
	public function get_jobs( $organization_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_provider_migrations';
		$jobs  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE organization_id = %d ORDER BY created_at DESC",
				$organization_id
			),
			ARRAY_A
		);

		return $jobs;
	}

	/**
	 * Analyze source provider to determine what can be migrated.
	 *
	 * @param int $job_id Job ID.
	 * @return array|WP_Error Analysis results or WP_Error on failure.
	 */
	public function analyze_source( $job_id ) {
		$job = $this->get_job( $job_id );

		if ( ! $job ) {
			return new WP_Error( 'job_not_found', __( 'Migration job not found', 'nonprofitsuite' ) );
		}

		// Update status
		$this->update_job_status( $job_id, 'analyzing' );

		// Get source adapter
		$source_adapter = $this->get_adapter( $job['integration_type'], $job['source_provider'], $job['organization_id'] );

		if ( is_wp_error( $source_adapter ) ) {
			$this->update_job_status( $job_id, 'failed' );
			return $source_adapter;
		}

		$analysis = array(
			'available_data_types' => array(),
			'record_counts'        => array(),
			'estimated_duration'   => 0,
			'warnings'             => array(),
		);

		// Analyze each data type
		foreach ( $job['data_types'] as $data_type ) {
			try {
				$count = $this->count_records( $source_adapter, $data_type, $job['integration_type'] );

				$analysis['record_counts'][ $data_type ] = $count;
				$analysis['available_data_types'][]      = $data_type;

				// Estimate 1 record per second (conservative)
				$analysis['estimated_duration'] += $count;

			} catch ( Exception $e ) {
				$analysis['warnings'][] = sprintf(
					__( 'Could not analyze %s: %s', 'nonprofitsuite' ),
					$data_type,
					$e->getMessage()
				);
			}
		}

		// Calculate total records
		$total_records = array_sum( $analysis['record_counts'] );

		// Update job
		global $wpdb;
		$table = $wpdb->prefix . 'ns_provider_migrations';

		$wpdb->update(
			$table,
			array(
				'total_records'       => $total_records,
				'validation_results'  => wp_json_encode( $analysis ),
				'migration_status'    => 'ready',
			),
			array( 'id' => $job_id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);

		return $analysis;
	}

	/**
	 * Execute migration job.
	 *
	 * @param int $job_id Job ID.
	 * @return array|WP_Error Migration results or WP_Error on failure.
	 */
	public function execute_migration( $job_id ) {
		$job = $this->get_job( $job_id );

		if ( ! $job ) {
			return new WP_Error( 'job_not_found', __( 'Migration job not found', 'nonprofitsuite' ) );
		}

		// Update status
		$this->update_job_status( $job_id, 'running' );

		// Get adapters
		$source_adapter      = $this->get_adapter( $job['integration_type'], $job['source_provider'], $job['organization_id'] );
		$destination_adapter = $this->get_adapter( $job['integration_type'], $job['destination_provider'], $job['organization_id'] );

		if ( is_wp_error( $source_adapter ) ) {
			$this->update_job_status( $job_id, 'failed' );
			return $source_adapter;
		}

		if ( is_wp_error( $destination_adapter ) ) {
			$this->update_job_status( $job_id, 'failed' );
			return $destination_adapter;
		}

		$results = array(
			'total'      => 0,
			'successful' => 0,
			'failed'     => 0,
			'skipped'    => 0,
			'errors'     => array(),
		);

		$rollback_data = array();

		// Mark start time
		global $wpdb;
		$table = $wpdb->prefix . 'ns_provider_migrations';
		$wpdb->update(
			$table,
			array( 'started_at' => current_time( 'mysql' ) ),
			array( 'id' => $job_id ),
			array( '%s' ),
			array( '%d' )
		);

		// Migrate each data type
		foreach ( $job['data_types'] as $data_type ) {
			try {
				// Extract from source
				$records = $this->extract_records( $source_adapter, $data_type, $job['integration_type'] );

				foreach ( $records as $record ) {
					$results['total']++;

					try {
						// Transform data
						$transformed = $this->transform_record( $record, $data_type, $job['field_mapping'] );

						// Preview mode: just validate
						if ( $job['migration_mode'] === 'preview' ) {
							$results['successful']++;
							continue;
						}

						// Load to destination
						$created_id = $this->load_record( $destination_adapter, $transformed, $data_type, $job['integration_type'] );

						if ( $created_id ) {
							$results['successful']++;

							// Store for rollback
							$rollback_data[] = array(
								'data_type' => $data_type,
								'id'        => $created_id,
							);
						} else {
							$results['failed']++;
							$results['errors'][] = array(
								'data_type' => $data_type,
								'record'    => $record,
								'error'     => __( 'Failed to create record', 'nonprofitsuite' ),
							);
						}

					} catch ( Exception $e ) {
						$results['failed']++;
						$results['errors'][] = array(
							'data_type' => $data_type,
							'record'    => $record,
							'error'     => $e->getMessage(),
						);
					}

					// Update progress
					$wpdb->update(
						$table,
						array(
							'processed_records'  => $results['total'],
							'successful_records' => $results['successful'],
							'failed_records'     => $results['failed'],
							'skipped_records'    => $results['skipped'],
						),
						array( 'id' => $job_id ),
						array( '%d', '%d', '%d', '%d' ),
						array( '%d' )
					);

					// Prevent timeout
					if ( $results['total'] % 50 === 0 ) {
						sleep( 1 );
					}
				}

			} catch ( Exception $e ) {
				$results['errors'][] = array(
					'data_type' => $data_type,
					'error'     => $e->getMessage(),
				);
			}
		}

		// Mark completion
		$wpdb->update(
			$table,
			array(
				'migration_status'    => 'completed',
				'completed_at'        => current_time( 'mysql' ),
				'processed_records'   => $results['total'],
				'successful_records'  => $results['successful'],
				'failed_records'      => $results['failed'],
				'skipped_records'     => $results['skipped'],
				'error_log'           => wp_json_encode( $results['errors'] ),
				'rollback_data'       => wp_json_encode( $rollback_data ),
			),
			array( 'id' => $job_id ),
			array( '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s' ),
			array( '%d' )
		);

		return $results;
	}

	/**
	 * Rollback a migration.
	 *
	 * @param int $job_id Job ID.
	 * @return array|WP_Error Rollback results or WP_Error on failure.
	 */
	public function rollback_migration( $job_id ) {
		$job = $this->get_job( $job_id );

		if ( ! $job ) {
			return new WP_Error( 'job_not_found', __( 'Migration job not found', 'nonprofitsuite' ) );
		}

		if ( empty( $job['rollback_data'] ) ) {
			return new WP_Error( 'no_rollback_data', __( 'No rollback data available', 'nonprofitsuite' ) );
		}

		$destination_adapter = $this->get_adapter( $job['integration_type'], $job['destination_provider'], $job['organization_id'] );

		if ( is_wp_error( $destination_adapter ) ) {
			return $destination_adapter;
		}

		$results = array(
			'deleted' => 0,
			'failed'  => 0,
			'errors'  => array(),
		);

		foreach ( $job['rollback_data'] as $item ) {
			try {
				$this->delete_record( $destination_adapter, $item['id'], $item['data_type'], $job['integration_type'] );
				$results['deleted']++;
			} catch ( Exception $e ) {
				$results['failed']++;
				$results['errors'][] = $e->getMessage();
			}
		}

		// Update job status
		$this->update_job_status( $job_id, 'rolled_back' );

		return $results;
	}

	/**
	 * Get adapter for integration type and provider.
	 *
	 * @param string $integration_type Integration type.
	 * @param string $provider         Provider name.
	 * @param int    $organization_id  Organization ID.
	 * @return object|WP_Error Adapter instance or WP_Error.
	 */
	private function get_adapter( $integration_type, $provider, $organization_id ) {
		switch ( $integration_type ) {
			case 'crm':
				return NS_CRM_Manager::get_instance()->get_adapter( $provider, $organization_id );

			case 'calendar':
				return NS_Calendar_Manager::get_instance()->get_adapter( $provider, $organization_id );

			case 'email':
				return NS_Marketing_Manager::get_instance()->get_adapter( $provider, $organization_id );

			case 'payment':
				return NS_Payment_Manager::get_instance()->get_adapter( $provider, $organization_id );

			case 'video':
				return NS_Video_Conferencing_Manager::get_instance()->get_adapter( $provider, $organization_id );

			case 'forms':
				return NS_Forms_Manager::get_instance()->get_adapter( $provider, $organization_id );

			case 'pm':
				return NS_Project_Manager::get_instance()->get_adapter( $provider, $organization_id );

			case 'sms':
				return NS_SMS_Manager::get_instance()->get_adapter( $provider, $organization_id );

			case 'analytics':
				return NS_Analytics_Manager::get_instance()->get_adapter( $provider, $organization_id );

			case 'ai':
				return NS_AI_Manager::get_instance()->get_adapter( $provider, $organization_id );

			default:
				return new WP_Error( 'unsupported_integration', __( 'Unsupported integration type', 'nonprofitsuite' ) );
		}
	}

	/**
	 * Count records in source provider.
	 *
	 * @param object $adapter          Adapter instance.
	 * @param string $data_type        Data type.
	 * @param string $integration_type Integration type.
	 * @return int Record count.
	 */
	private function count_records( $adapter, $data_type, $integration_type ) {
		// This would call provider-specific count methods
		// For now, return a placeholder
		return 0;
	}

	/**
	 * Extract records from source provider.
	 *
	 * @param object $adapter          Adapter instance.
	 * @param string $data_type        Data type.
	 * @param string $integration_type Integration type.
	 * @return array Records.
	 */
	private function extract_records( $adapter, $data_type, $integration_type ) {
		// Call provider-specific extraction methods
		// This would be implemented based on each adapter's capabilities
		return array();
	}

	/**
	 * Transform record using field mapping.
	 *
	 * @param array  $record        Source record.
	 * @param string $data_type     Data type.
	 * @param array  $field_mapping Field mapping configuration.
	 * @return array Transformed record.
	 */
	private function transform_record( $record, $data_type, $field_mapping ) {
		if ( empty( $field_mapping ) ) {
			return $record;
		}

		$transformed = array();

		foreach ( $field_mapping as $dest_field => $source_field ) {
			if ( isset( $record[ $source_field ] ) ) {
				$transformed[ $dest_field ] = $record[ $source_field ];
			}
		}

		return $transformed;
	}

	/**
	 * Load record to destination provider.
	 *
	 * @param object $adapter          Adapter instance.
	 * @param array  $record           Transformed record.
	 * @param string $data_type        Data type.
	 * @param string $integration_type Integration type.
	 * @return mixed Created record ID or false on failure.
	 */
	private function load_record( $adapter, $record, $data_type, $integration_type ) {
		// Call provider-specific create methods
		// This would be implemented based on each adapter's capabilities
		return false;
	}

	/**
	 * Delete record from provider.
	 *
	 * @param object $adapter          Adapter instance.
	 * @param mixed  $record_id        Record ID.
	 * @param string $data_type        Data type.
	 * @param string $integration_type Integration type.
	 * @return bool True on success, false on failure.
	 */
	private function delete_record( $adapter, $record_id, $data_type, $integration_type ) {
		// Call provider-specific delete methods
		return true;
	}

	/**
	 * Update job status.
	 *
	 * @param int    $job_id Job ID.
	 * @param string $status New status.
	 * @return bool True on success, false on failure.
	 */
	private function update_job_status( $job_id, $status ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_provider_migrations';

		return $wpdb->update(
			$table,
			array( 'migration_status' => $status ),
			array( 'id' => $job_id ),
			array( '%s' ),
			array( '%d' )
		);
	}
}
