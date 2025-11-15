<?php
/**
 * Background Check Manager
 *
 * Coordinates FCRA-compliant background check operations across multiple providers.
 * Manages consent workflow, check status, and adverse action processes.
 *
 * CRITICAL: All operations must comply with Fair Credit Reporting Act (FCRA).
 *
 * @package NonprofitSuite
 * @subpackage Helpers
 * @since 1.18.0
 */

namespace NonprofitSuite\Helpers;

class NS_Background_Check_Manager {

	/**
	 * Database instance
	 *
	 * @var \wpdb
	 */
	private $db;

	/**
	 * Settings table name
	 *
	 * @var string
	 */
	private $settings_table;

	/**
	 * Requests table name
	 *
	 * @var string
	 */
	private $requests_table;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->db             = $wpdb;
		$this->settings_table = $wpdb->prefix . 'ns_background_check_settings';
		$this->requests_table = $wpdb->prefix . 'ns_background_check_requests';

		// Register webhook endpoint
		add_action( 'rest_api_init', array( $this, 'register_webhook_endpoint' ) );
	}

	/**
	 * Request background check
	 *
	 * Main entry point for background check requests.
	 * Initiates consent workflow.
	 *
	 * @param int    $contact_id Contact ID
	 * @param string $check_type Check type (volunteer, staff, board)
	 * @param string $package    Package name
	 * @return array Request result
	 */
	public function request_check( $contact_id, $check_type, $package ) {
		// Get contact information
		$contact = $this->get_contact_info( $contact_id );
		if ( ! $contact ) {
			return array(
				'success' => false,
				'error'   => 'Contact not found',
			);
		}

		// Validate required fields
		$validation = $this->validate_candidate_data( $contact );
		if ( ! $validation['valid'] ) {
			return array(
				'success' => false,
				'error'   => $validation['message'],
			);
		}

		// Get active provider
		$provider = $this->get_active_provider( $contact['organization_id'] );
		if ( ! $provider ) {
			return array(
				'success' => false,
				'error'   => 'No active background check provider configured',
			);
		}

		// Get adapter instance
		$adapter = $this->get_adapter( $provider );
		if ( ! $adapter ) {
			return array(
				'success' => false,
				'error'   => 'Provider adapter not available',
			);
		}

		// Create candidate in provider system
		$candidate_result = $adapter->create_candidate( array(
			'first_name' => $contact['first_name'],
			'last_name'  => $contact['last_name'],
			'email'      => $contact['email'],
			'phone'      => $contact['phone'],
			'dob'        => $contact['dob'],
			'ssn_last_4' => $contact['ssn_last_4'],
			'zipcode'    => $contact['zip'],
		) );

		if ( ! $candidate_result['success'] ) {
			return array(
				'success' => false,
				'error'   => $candidate_result['error_message'] ?? 'Failed to create candidate',
			);
		}

		// Create request record
		$request_id = $this->create_request_record(
			$contact_id,
			$provider,
			$check_type,
			$package,
			$candidate_result
		);

		return array(
			'success'         => true,
			'request_id'      => $request_id,
			'candidate_id'    => $candidate_result['candidate_id'],
			'portal_url'      => $candidate_result['portal_url'] ?? '',
			'next_step'       => 'Send consent invitation',
		);
	}

	/**
	 * Send consent invitation to candidate
	 *
	 * FCRA Requirement: Must obtain valid consent before ordering check.
	 *
	 * @param int   $request_id Request ID
	 * @param array $options    Invitation options
	 * @return array Invitation result
	 */
	public function send_consent_invitation( $request_id, $options = array() ) {
		// Get request
		$request = $this->get_request( $request_id );
		if ( ! $request ) {
			return array(
				'success' => false,
				'error'   => 'Request not found',
			);
		}

		// Get provider and adapter
		$provider = $this->get_provider_by_id( $request->provider );
		$adapter  = $this->get_adapter( $provider );

		if ( ! $adapter ) {
			return array(
				'success' => false,
				'error'   => 'Provider adapter not available',
			);
		}

		// Create invitation
		$invitation = $adapter->create_invitation(
			$request->provider_request_id,
			$request->package_name,
			$options
		);

		if ( ! $invitation['success'] ) {
			return array(
				'success' => false,
				'error'   => $invitation['error_message'] ?? 'Failed to create invitation',
			);
		}

		// Update request status
		$this->db->update(
			$this->requests_table,
			array( 'request_status' => 'sent' ),
			array( 'id' => $request_id ),
			array( '%s' ),
			array( '%d' )
		);

		return array(
			'success'        => true,
			'invitation_url' => $invitation['invitation_url'] ?? '',
			'expires_at'     => $invitation['expires_at'] ?? '',
		);
	}

	/**
	 * Record consent given by candidate
	 *
	 * Called when candidate provides consent via webhook or portal.
	 *
	 * @param int    $request_id Request ID
	 * @param string $ip_address Candidate IP address
	 * @return array Consent recording result
	 */
	public function record_consent( $request_id, $ip_address = '' ) {
		$this->db->update(
			$this->requests_table,
			array(
				'consent_given'      => 1,
				'consent_given_at'   => current_time( 'mysql' ),
				'consent_ip_address' => $ip_address,
			),
			array( 'id' => $request_id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);

		// Automatically order check after consent
		return $this->order_check( $request_id );
	}

	/**
	 * Order background check
	 *
	 * Order check after consent has been obtained.
	 * FCRA Requirement: Must have valid consent before ordering.
	 *
	 * @param int $request_id Request ID
	 * @return array Order result
	 */
	private function order_check( $request_id ) {
		$request = $this->get_request( $request_id );

		if ( ! $request->consent_given ) {
			return array(
				'success' => false,
				'error'   => 'Consent required before ordering check',
			);
		}

		// Get provider and adapter
		$provider = $this->get_provider_by_id( $request->provider );
		$adapter  = $this->get_adapter( $provider );

		// Order check
		$check = $adapter->create_check(
			$request->provider_request_id,
			$request->package_name
		);

		if ( ! $check['success'] ) {
			return array(
				'success' => false,
				'error'   => $check['error_message'] ?? 'Failed to order check',
			);
		}

		// Update request
		$this->db->update(
			$this->requests_table,
			array(
				'request_status'            => 'in_progress',
				'estimated_completion_date' => $check['estimated_completion'] ?? null,
				'cost'                      => $check['cost'] ?? 0,
			),
			array( 'id' => $request_id ),
			array( '%s', '%s', '%f' ),
			array( '%d' )
		);

		return array(
			'success'               => true,
			'check_id'              => $check['check_id'],
			'status'                => $check['status'],
			'estimated_completion'  => $check['estimated_completion'] ?? '',
		);
	}

	/**
	 * Get check status
	 *
	 * @param int $request_id Request ID
	 * @return array Check status
	 */
	public function get_check_status( $request_id ) {
		$request = $this->get_request( $request_id );

		if ( ! $request ) {
			return array(
				'success' => false,
				'error'   => 'Request not found',
			);
		}

		// Get provider and adapter
		$provider = $this->get_provider_by_id( $request->provider );
		$adapter  = $this->get_adapter( $provider );

		if ( ! $adapter ) {
			return array(
				'success' => false,
				'error'   => 'Provider adapter not available',
			);
		}

		// Get status from provider
		$status = $adapter->get_check_status( $request->provider_request_id );

		if ( $status['success'] ) {
			// Update local record
			$this->update_request_from_status( $request_id, $status );
		}

		return $status;
	}

	/**
	 * Approve candidate
	 *
	 * Approve candidate after reviewing results.
	 *
	 * @param int    $request_id Request ID
	 * @param string $notes      Review notes
	 * @return bool Success
	 */
	public function approve_candidate( $request_id, $notes = '' ) {
		$this->db->update(
			$this->requests_table,
			array(
				'adjudication' => 'approved',
				'reviewed_by'  => get_current_user_id(),
				'reviewed_at'  => current_time( 'mysql' ),
				'review_notes' => $notes,
			),
			array( 'id' => $request_id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Initiate adverse action
	 *
	 * Begin adverse action process for negative results.
	 * FCRA Requirement: Must follow proper adverse action procedures.
	 *
	 * @param int   $request_id Request ID
	 * @param array $details    Adverse action details
	 * @return array Adverse action result
	 */
	public function initiate_adverse_action( $request_id, $details = array() ) {
		$request = $this->get_request( $request_id );

		if ( ! $request ) {
			return array(
				'success' => false,
				'error'   => 'Request not found',
			);
		}

		// Get provider and adapter
		$provider = $this->get_provider_by_id( $request->provider );
		$adapter  = $this->get_adapter( $provider );

		// Initiate adverse action
		$result = $adapter->initiate_adverse_action(
			$request->provider_request_id,
			$details
		);

		if ( $result['success'] ) {
			// Update request
			$this->db->update(
				$this->requests_table,
				array(
					'adjudication'  => $details['pre_adverse'] ? 'pre_adverse' : 'adverse',
					'reviewed_by'   => get_current_user_id(),
					'reviewed_at'   => current_time( 'mysql' ),
					'review_notes'  => $details['reason'] ?? '',
				),
				array( 'id' => $request_id ),
				array( '%s', '%d', '%s', '%s' ),
				array( '%d' )
			);
		}

		return $result;
	}

	/**
	 * Get compliance report
	 *
	 * Generate report for compliance audits.
	 *
	 * @param array $date_range Date range (start_date, end_date)
	 * @return array Compliance report data
	 */
	public function get_compliance_report( $date_range ) {
		$start = $date_range['start_date'] ?? date( 'Y-m-01' );
		$end   = $date_range['end_date'] ?? date( 'Y-m-t' );

		$data = array(
			'period'                => "$start to $end",
			'total_checks'          => 0,
			'checks_by_type'        => array(),
			'checks_by_status'      => array(),
			'adverse_actions'       => 0,
			'average_turnaround'    => 0,
			'total_cost'            => 0,
		);

		// Get all checks in period
		$checks = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->requests_table}
				WHERE created_at BETWEEN %s AND %s
				ORDER BY created_at DESC",
				$start,
				$end
			)
		);

		$data['total_checks'] = count( $checks );

		// Analyze checks
		foreach ( $checks as $check ) {
			// By type
			$type = $check->check_type;
			$data['checks_by_type'][ $type ] = ( $data['checks_by_type'][ $type ] ?? 0 ) + 1;

			// By status
			$status = $check->request_status;
			$data['checks_by_status'][ $status ] = ( $data['checks_by_status'][ $status ] ?? 0 ) + 1;

			// Adverse actions
			if ( in_array( $check->adjudication, array( 'pre_adverse', 'adverse' ), true ) ) {
				$data['adverse_actions']++;
			}

			// Cost
			$data['total_cost'] += floatval( $check->cost );
		}

		return $data;
	}

	/**
	 * Batch request checks
	 *
	 * Request checks for multiple contacts at once.
	 *
	 * @param array  $contact_ids Array of contact IDs
	 * @param string $check_type  Check type
	 * @param string $package     Package name
	 * @return array Batch request results
	 */
	public function batch_request_checks( $contact_ids, $check_type, $package ) {
		$results = array(
			'success'    => array(),
			'failed'     => array(),
			'total_cost' => 0,
		);

		foreach ( $contact_ids as $contact_id ) {
			$result = $this->request_check( $contact_id, $check_type, $package );

			if ( $result['success'] ) {
				$results['success'][] = $contact_id;
			} else {
				$results['failed'][] = array(
					'contact_id' => $contact_id,
					'error'      => $result['error'] ?? 'Unknown error',
				);
			}

			// Rate limiting
			sleep( 1 );
		}

		return $results;
	}

	/**
	 * Register webhook endpoint
	 *
	 * REST API endpoint for provider webhooks.
	 */
	public function register_webhook_endpoint() {
		register_rest_route(
			'nonprofitsuite/v1',
			'/background-check-webhook/(?P<provider>[a-zA-Z0-9]+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true', // Validated via signature
			)
		);
	}

	/**
	 * Handle webhook from provider
	 *
	 * @param \WP_REST_Request $request REST request
	 * @return \WP_REST_Response REST response
	 */
	public function handle_webhook( $request ) {
		$provider_name = $request->get_param( 'provider' );
		$payload       = $request->get_body();
		$signature     = $request->get_header( 'X-Signature' ) ?? '';
		$headers       = $request->get_headers();

		// Get provider
		$provider = $this->get_provider_by_name( $provider_name );
		if ( ! $provider ) {
			return new \WP_REST_Response( array( 'error' => 'Unknown provider' ), 404 );
		}

		// Get adapter
		$adapter = $this->get_adapter( $provider );
		if ( ! $adapter ) {
			return new \WP_REST_Response( array( 'error' => 'Provider adapter not available' ), 500 );
		}

		// Validate signature
		$valid = $adapter->validate_webhook( $payload, $signature, $headers );
		if ( ! $valid ) {
			return new \WP_REST_Response( array( 'error' => 'Invalid signature' ), 401 );
		}

		// Process webhook
		$data   = json_decode( $payload, true );
		$result = $adapter->process_webhook( $data );

		if ( $result['success'] ) {
			// Update request based on webhook data
			$this->process_webhook_update( $result );
		}

		return new \WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Process webhook update
	 *
	 * @param array $webhook_data Webhook data from adapter
	 */
	private function process_webhook_update( $webhook_data ) {
		$check_id   = $webhook_data['check_id'] ?? '';
		$event_type = $webhook_data['event_type'] ?? '';

		// Find request by provider check ID
		$request = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->requests_table} WHERE provider_request_id = %s",
				$check_id
			)
		);

		if ( ! $request ) {
			return;
		}

		// Update based on event type
		if ( strpos( $event_type, 'completed' ) !== false ) {
			$this->db->update(
				$this->requests_table,
				array(
					'request_status'        => 'completed',
					'completion_percentage' => 100,
					'completed_at'          => current_time( 'mysql' ),
				),
				array( 'id' => $request->id ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Get active provider
	 *
	 * @param int $organization_id Organization ID
	 * @return object|null Provider settings
	 */
	private function get_active_provider( $organization_id ) {
		return $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->settings_table}
				WHERE organization_id = %d AND is_active = 1
				LIMIT 1",
				$organization_id
			)
		);
	}

	/**
	 * Get provider by ID
	 *
	 * @param string $provider Provider name
	 * @return object|null Provider settings
	 */
	private function get_provider_by_id( $provider ) {
		return $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->settings_table} WHERE provider = %s LIMIT 1",
				$provider
			)
		);
	}

	/**
	 * Get provider by name
	 *
	 * @param string $provider_name Provider name
	 * @return object|null Provider settings
	 */
	private function get_provider_by_name( $provider_name ) {
		return $this->get_provider_by_id( $provider_name );
	}

	/**
	 * Get adapter instance
	 *
	 * @param object $provider Provider settings
	 * @return object|null Adapter instance
	 */
	private function get_adapter( $provider ) {
		if ( ! $provider ) {
			return null;
		}

		$config = array(
			'api_key'         => $provider->api_key,
			'api_secret'      => $provider->api_secret,
			'webhook_secret'  => $provider->webhook_secret,
			'organization_id' => $provider->organization_id,
		);

		// Parse additional settings
		if ( ! empty( $provider->settings ) ) {
			$additional = json_decode( $provider->settings, true );
			if ( is_array( $additional ) ) {
				$config = array_merge( $config, $additional );
			}
		}

		switch ( $provider->provider ) {
			case 'checkr':
				require_once plugin_dir_path( __FILE__ ) . '../integrations/adapters/class-checkr-adapter.php';
				return new \NonprofitSuite\Integrations\Adapters\NS_Checkr_Adapter( $config );

			default:
				return null;
		}
	}

	/**
	 * Get contact information
	 *
	 * @param int $contact_id Contact ID
	 * @return array|null Contact data
	 */
	private function get_contact_info( $contact_id ) {
		$contact = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->db->prefix}ns_contacts WHERE id = %d",
				$contact_id
			),
			ARRAY_A
		);

		return $contact;
	}

	/**
	 * Get request
	 *
	 * @param int $request_id Request ID
	 * @return object|null Request object
	 */
	private function get_request( $request_id ) {
		return $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->requests_table} WHERE id = %d",
				$request_id
			)
		);
	}

	/**
	 * Create request record
	 *
	 * @param int    $contact_id       Contact ID
	 * @param object $provider         Provider settings
	 * @param string $check_type       Check type
	 * @param string $package          Package name
	 * @param array  $candidate_result Candidate creation result
	 * @return int Request ID
	 */
	private function create_request_record( $contact_id, $provider, $check_type, $package, $candidate_result ) {
		$contact = $this->get_contact_info( $contact_id );

		$data = array(
			'organization_id'        => $provider->organization_id,
			'contact_id'             => $contact_id,
			'provider'               => $provider->provider,
			'provider_request_id'    => $candidate_result['candidate_id'],
			'check_type'             => $check_type,
			'package_name'           => $package,
			'candidate_email'        => $contact['email'],
			'candidate_first_name'   => $contact['first_name'],
			'candidate_last_name'    => $contact['last_name'],
			'candidate_dob'          => $contact['dob'] ?? null,
			'candidate_phone'        => $contact['phone'] ?? null,
			'candidate_zipcode'      => $contact['zip'] ?? null,
			'request_status'         => 'pending',
			'candidate_portal_url'   => $candidate_result['portal_url'] ?? null,
			'requested_by'           => get_current_user_id(),
		);

		$this->db->insert( $this->requests_table, $data );

		return $this->db->insert_id;
	}

	/**
	 * Update request from status
	 *
	 * @param int   $request_id Request ID
	 * @param array $status     Status data from provider
	 */
	private function update_request_from_status( $request_id, $status ) {
		$update_data = array(
			'overall_status'        => $status['overall_result'] ?? null,
			'completion_percentage' => $status['completion_percentage'] ?? 0,
		);

		if ( ! empty( $status['completed_at'] ) ) {
			$update_data['completed_at'] = $status['completed_at'];
			$update_data['request_status'] = 'completed';
		}

		$this->db->update(
			$this->requests_table,
			$update_data,
			array( 'id' => $request_id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Validate candidate data
	 *
	 * @param array $contact Contact data
	 * @return array Validation result
	 */
	private function validate_candidate_data( $contact ) {
		$required = array( 'first_name', 'last_name', 'email' );
		$missing  = array();

		foreach ( $required as $field ) {
			if ( empty( $contact[ $field ] ) ) {
				$missing[] = $field;
			}
		}

		if ( ! empty( $missing ) ) {
			return array(
				'valid'   => false,
				'message' => 'Missing required fields: ' . implode( ', ', $missing ),
			);
		}

		return array( 'valid' => true );
	}
}
