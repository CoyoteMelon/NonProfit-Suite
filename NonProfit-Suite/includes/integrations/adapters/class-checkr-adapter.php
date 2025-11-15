<?php
/**
 * Checkr Background Check Adapter
 *
 * Integration with Checkr for FCRA-compliant background screening.
 * Supports volunteer, staff, and board member checks.
 *
 * API Documentation: https://docs.checkr.com/
 * Pricing: Basic ($35), Standard ($50), Premium ($75)
 * Turnaround: 1-5 days
 *
 * @package NonprofitSuite
 * @subpackage Integrations/Adapters
 * @since 1.18.0
 */

namespace NonprofitSuite\Integrations\Adapters;

class NS_Checkr_Adapter implements NS_Background_Check_Adapter {

	/**
	 * API key
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Webhook secret
	 *
	 * @var string
	 */
	private $webhook_secret;

	/**
	 * API endpoint
	 *
	 * @var string
	 */
	private $api_endpoint;

	/**
	 * Organization ID
	 *
	 * @var int
	 */
	private $organization_id;

	/**
	 * Test mode
	 *
	 * @var bool
	 */
	private $test_mode;

	/**
	 * Constructor
	 *
	 * @param array $config Configuration array
	 */
	public function __construct( $config ) {
		$this->api_key         = $config['api_key'] ?? '';
		$this->webhook_secret  = $config['webhook_secret'] ?? '';
		$this->api_endpoint    = $config['api_endpoint'] ?? 'https://api.checkr.com/v1';
		$this->organization_id = $config['organization_id'] ?? 0;
		$this->test_mode       = $config['test_mode'] ?? false;
	}

	/**
	 * Create candidate in the system
	 *
	 * @param array $candidate_data Candidate information
	 * @return array Candidate creation result
	 */
	public function create_candidate( $candidate_data ) {
		$endpoint = '/candidates';

		$body = array(
			'email'      => $candidate_data['email'],
			'first_name' => $candidate_data['first_name'],
			'last_name'  => $candidate_data['last_name'],
			'phone'      => $candidate_data['phone'] ?? '',
			'dob'        => $candidate_data['dob'] ?? '',
			'ssn'        => $candidate_data['ssn_last_4'] ?? '',
			'zipcode'    => $candidate_data['zipcode'] ?? '',
		);

		$response = $this->api_request( $endpoint, 'POST', $body );

		if ( ! $response['success'] ) {
			return array(
				'success'       => false,
				'error_message' => $response['error_message'] ?? 'Failed to create candidate',
			);
		}

		$data = $response['data'] ?? array();

		return array(
			'success'      => true,
			'candidate_id' => $data['id'] ?? '',
			'portal_url'   => $data['candidate_portal_url'] ?? '',
		);
	}

	/**
	 * Create invitation for consent
	 *
	 * @param string $candidate_id Candidate ID
	 * @param string $package      Package name
	 * @param array  $options      Invitation options
	 * @return array Invitation result
	 */
	public function create_invitation( $candidate_id, $package, $options = array() ) {
		$endpoint = '/invitations';

		$body = array(
			'candidate_id' => $candidate_id,
			'package'      => $this->map_package_name( $package ),
		);

		if ( ! empty( $options['custom_message'] ) ) {
			$body['custom_message'] = $options['custom_message'];
		}

		if ( ! empty( $options['return_url'] ) ) {
			$body['redirect_url'] = $options['return_url'];
		}

		$response = $this->api_request( $endpoint, 'POST', $body );

		if ( ! $response['success'] ) {
			return array(
				'success'       => false,
				'error_message' => $response['error_message'] ?? 'Failed to create invitation',
			);
		}

		$data = $response['data'] ?? array();

		return array(
			'success'        => true,
			'invitation_id'  => $data['id'] ?? '',
			'invitation_url' => $data['invitation_url'] ?? '',
			'expires_at'     => $data['expires_at'] ?? '',
		);
	}

	/**
	 * Create background check
	 *
	 * @param string $candidate_id Candidate ID
	 * @param string $package      Package name
	 * @param array  $options      Check options
	 * @return array Check creation result
	 */
	public function create_check( $candidate_id, $package, $options = array() ) {
		$endpoint = '/reports';

		$body = array(
			'candidate_id' => $candidate_id,
			'package'      => $this->map_package_name( $package ),
		);

		// Add custom components if specified
		if ( ! empty( $options['components'] ) ) {
			$body['screenings'] = $options['components'];
		}

		$response = $this->api_request( $endpoint, 'POST', $body );

		if ( ! $response['success'] ) {
			return array(
				'success'       => false,
				'error_message' => $response['error_message'] ?? 'Failed to create check',
			);
		}

		$data = $response['data'] ?? array();

		return array(
			'success'               => true,
			'check_id'              => $data['id'] ?? '',
			'status'                => $this->map_status( $data['status'] ?? '' ),
			'estimated_completion'  => $data['eta'] ?? '',
			'cost'                  => $this->calculate_cost( $package, $options ),
		);
	}

	/**
	 * Get check status
	 *
	 * @param string $check_id Check ID
	 * @return array Check status information
	 */
	public function get_check_status( $check_id ) {
		$endpoint = '/reports/' . $check_id;

		$response = $this->api_request( $endpoint, 'GET' );

		if ( ! $response['success'] ) {
			return array(
				'success'       => false,
				'error_message' => $response['error_message'] ?? 'Failed to get check status',
			);
		}

		$data = $response['data'] ?? array();

		return array(
			'success'                => true,
			'check_id'               => $data['id'] ?? '',
			'status'                 => $this->map_status( $data['status'] ?? '' ),
			'completion_percentage'  => $this->calculate_completion( $data ),
			'overall_result'         => $this->map_result( $data['adjudication'] ?? '' ),
			'component_results'      => $this->parse_component_results( $data ),
			'completed_at'           => $data['completed_at'] ?? '',
		);
	}

	/**
	 * Get full report
	 *
	 * @param string $check_id Check ID
	 * @return array Full report data
	 */
	public function get_report( $check_id ) {
		$endpoint = '/reports/' . $check_id;

		$response = $this->api_request( $endpoint, 'GET' );

		if ( ! $response['success'] ) {
			return array(
				'success'       => false,
				'error_message' => $response['error_message'] ?? 'Failed to get report',
			);
		}

		$data = $response['data'] ?? array();

		return array(
			'success'                 => true,
			'check_id'                => $data['id'] ?? '',
			'status'                  => $this->map_status( $data['status'] ?? '' ),
			'overall_result'          => $this->map_result( $data['adjudication'] ?? '' ),
			'criminal_records'        => $this->parse_criminal_records( $data ),
			'motor_vehicle'           => $this->parse_motor_vehicle( $data ),
			'education_verification'  => $this->parse_education( $data ),
			'employment_verification' => $this->parse_employment( $data ),
			'report_url'              => $data['report_url'] ?? '',
			'candidate_portal_url'    => $data['candidate_portal_url'] ?? '',
		);
	}

	/**
	 * Get available packages
	 *
	 * @return array Available packages
	 */
	public function get_packages() {
		// Checkr packages
		$packages = array(
			array(
				'package_id'  => 'basic',
				'name'        => 'Basic Package',
				'description' => 'Criminal records check (county, state, federal)',
				'components'  => array( 'criminal' ),
				'price'       => 35.00,
				'turnaround'  => '1-3 days',
			),
			array(
				'package_id'  => 'standard',
				'name'        => 'Standard Package',
				'description' => 'Criminal records + Motor vehicle records',
				'components'  => array( 'criminal', 'mvr' ),
				'price'       => 50.00,
				'turnaround'  => '2-4 days',
			),
			array(
				'package_id'  => 'premium',
				'name'        => 'Premium Package',
				'description' => 'Criminal + MVR + Education + Employment verification',
				'components'  => array( 'criminal', 'mvr', 'education', 'employment' ),
				'price'       => 75.00,
				'turnaround'  => '3-5 days',
			),
		);

		return array(
			'success'  => true,
			'packages' => $packages,
		);
	}

	/**
	 * Cancel check
	 *
	 * @param string $check_id Check ID
	 * @param string $reason   Cancellation reason
	 * @return array Cancellation result
	 */
	public function cancel_check( $check_id, $reason = '' ) {
		$endpoint = '/reports/' . $check_id;

		$body = array(
			'status' => 'canceled',
			'reason' => $reason,
		);

		$response = $this->api_request( $endpoint, 'PATCH', $body );

		if ( ! $response['success'] ) {
			return array(
				'success'       => false,
				'error_message' => $response['error_message'] ?? 'Failed to cancel check',
			);
		}

		$data = $response['data'] ?? array();

		return array(
			'success'       => true,
			'status'        => 'cancelled',
			'refund_issued' => false, // Checkr handles refunds separately
			'refund_amount' => 0,
		);
	}

	/**
	 * Validate webhook signature
	 *
	 * @param string $payload   Raw webhook payload
	 * @param string $signature Signature from webhook headers
	 * @param array  $headers   All webhook headers
	 * @return bool Whether signature is valid
	 */
	public function validate_webhook( $payload, $signature, $headers = array() ) {
		if ( empty( $this->webhook_secret ) ) {
			return false;
		}

		// Checkr uses HMAC-SHA256
		$expected_signature = hash_hmac( 'sha256', $payload, $this->webhook_secret );

		return hash_equals( $expected_signature, $signature );
	}

	/**
	 * Process webhook
	 *
	 * @param array $payload Parsed webhook payload
	 * @return array Webhook processing result
	 */
	public function process_webhook( $payload ) {
		$event_type = $payload['type'] ?? '';
		$data       = $payload['data'] ?? array();

		// Extract check ID
		$check_id = $data['object']['id'] ?? '';

		return array(
			'success'    => true,
			'event_type' => $event_type,
			'check_id'   => $check_id,
			'data'       => $data,
		);
	}

	/**
	 * Get FCRA disclosure text
	 *
	 * @param string $check_type Type of check
	 * @return array FCRA disclosure information
	 */
	public function get_fcra_disclosure( $check_type = 'employment' ) {
		$disclosure_text = <<<'EOT'
DISCLOSURE REGARDING BACKGROUND INVESTIGATION

In connection with your application for employment or volunteering, we may obtain one or more reports regarding your driving and/or criminal history, and other background information about you from a consumer reporting agency for employment purposes. This includes, but is not limited to, verification of Social Security number; current and previous residences; employment history, including all personnel files; education; references; credit history and reports; criminal history, including records from any criminal justice agency in any or all federal, state, or county jurisdictions; birth records; motor vehicle records, including traffic citations and registration; and any other public records.

The consumer reporting agency that will prepare the report may contact you to verify the information you provided on this form.  You must provide additional information as requested by the consumer reporting agency and/or the organization to complete this process.  Please be aware that the information you provide on this form will be transmitted to a consumer reporting agency.
EOT;

		$authorization_text = <<<'EOT'
AUTHORIZATION FOR BACKGROUND CHECK

I acknowledge receipt of the DISCLOSURE REGARDING BACKGROUND INVESTIGATION and A SUMMARY OF YOUR RIGHTS UNDER THE FAIR CREDIT REPORTING ACT and certify that I have read and understand both of those documents. I hereby authorize the obtaining of "consumer reports" and/or "investigative consumer reports" by the Company or organization at any time after receipt of this authorization and throughout my employment or volunteer service, if applicable. To this end, I hereby authorize, without reservation, any law enforcement agency, administrator, state or federal agency, institution, school or university (public or private), information service bureau, employer, or insurance company to furnish any and all background information requested by Checkr, Inc., 1 Montgomery St., Suite 2400, San Francisco, CA 94104 (888) 390-8012, another consumer reporting agency ("CRA"), or the Company itself. I agree that a facsimile ("fax"), electronic or photographic copy of this Authorization shall be as valid as the original.
EOT;

		$summary_rights = <<<'EOT'
A SUMMARY OF YOUR RIGHTS UNDER THE FAIR CREDIT REPORTING ACT

The federal Fair Credit Reporting Act (FCRA) promotes the accuracy, fairness, and privacy of information in the files of consumer reporting agencies. There are many types of consumer reporting agencies, including credit bureaus and specialty agencies (such as agencies that sell information about check writing histories, medical records, and rental history records). Here is a summary of your major rights under the FCRA. For more information, including information about additional rights, go to www.consumerfinance.gov/learnmore or write to: Consumer Financial Protection Bureau, 1700 G Street N.W., Washington, DC 20552.

- You must be told if information in your file has been used against you.
- You have the right to know what is in your file.
- You have the right to ask for a credit score.
- You have the right to dispute incomplete or inaccurate information.
- Consumer reporting agencies must correct or delete inaccurate, incomplete, or unverifiable information.
- Consumer reporting agencies may not report outdated negative information.
- Access to your file is limited.
- You must give your consent for reports to be provided to employers.
- You may limit "prescreened" offers of credit and insurance you get based on information in your credit report.
- You may seek damages from violators.
- Identity theft victims and active duty military personnel have additional rights.
EOT;

		return array(
			'disclosure_text'     => $disclosure_text,
			'authorization_text'  => $authorization_text,
			'summary_rights'      => $summary_rights,
			'version'             => '2025-01-01',
		);
	}

	/**
	 * Initiate adverse action
	 *
	 * @param string $check_id Check ID
	 * @param array  $details  Adverse action details
	 * @return array Adverse action result
	 */
	public function initiate_adverse_action( $check_id, $details ) {
		$endpoint = '/adverse_actions';

		$body = array(
			'report_id'      => $check_id,
			'pre_notice'     => $details['pre_adverse'] ?? true,
			'post_notice'    => ! ( $details['pre_adverse'] ?? true ),
			'individualized_assessment_engaged' => false,
		);

		$response = $this->api_request( $endpoint, 'POST', $body );

		if ( ! $response['success'] ) {
			return array(
				'success'       => false,
				'error_message' => $response['error_message'] ?? 'Failed to initiate adverse action',
			);
		}

		$data = $response['data'] ?? array();

		return array(
			'success'           => true,
			'adverse_action_id' => $data['id'] ?? '',
			'notice_sent_at'    => $data['created_at'] ?? '',
			'dispute_period'    => date( 'Y-m-d H:i:s', strtotime( '+7 days' ) ), // 7-day dispute period
		);
	}

	/**
	 * Validate configuration
	 *
	 * @return array Validation results
	 */
	public function validate_configuration() {
		$errors = array();

		if ( empty( $this->api_key ) ) {
			$errors[] = 'API key is required';
		}

		if ( empty( $this->organization_id ) ) {
			$errors[] = 'Organization ID is required';
		}

		if ( ! empty( $errors ) ) {
			return array(
				'valid'         => false,
				'errors'        => $errors,
				'error_message' => implode( ', ', $errors ),
			);
		}

		// Test API connection
		$test = $this->api_request( '/candidates', 'GET' );

		if ( ! $test['success'] ) {
			return array(
				'valid'         => false,
				'errors'        => array( 'API connection failed' ),
				'error_message' => $test['error_message'] ?? 'Could not connect to Checkr API',
			);
		}

		return array(
			'valid'  => true,
			'errors' => array(),
		);
	}

	/**
	 * Calculate cost for check
	 *
	 * @param string $package Package name
	 * @param array  $options Check options
	 * @return float Estimated cost
	 */
	public function calculate_cost( $package, $options = array() ) {
		$costs = array(
			'basic'    => 35.00,
			'standard' => 50.00,
			'premium'  => 75.00,
		);

		$package = strtolower( $package );

		return $costs[ $package ] ?? 35.00;
	}

	/**
	 * Make API request
	 *
	 * @param string $endpoint API endpoint
	 * @param string $method   HTTP method
	 * @param array  $body     Request body
	 * @return array Response data
	 */
	private function api_request( $endpoint, $method = 'GET', $body = array() ) {
		$url = $this->api_endpoint . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->api_key . ':' ),
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		);

		if ( ! empty( $body ) && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'success'       => false,
				'error_message' => $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			return array(
				'success'       => false,
				'error_message' => $data['error'] ?? 'API request failed',
				'status_code'   => $status_code,
			);
		}

		return array(
			'success' => true,
			'data'    => $data,
		);
	}

	/**
	 * Map package name to Checkr package
	 *
	 * @param string $package Package name
	 * @return string Checkr package name
	 */
	private function map_package_name( $package ) {
		$mapping = array(
			'volunteer' => 'basic',
			'staff'     => 'standard',
			'board'     => 'premium',
		);

		$package = strtolower( $package );

		return $mapping[ $package ] ?? $package;
	}

	/**
	 * Map Checkr status to standard status
	 *
	 * @param string $status Checkr status
	 * @return string Standard status
	 */
	private function map_status( $status ) {
		$mapping = array(
			'pending'   => 'pending',
			'consider'  => 'in_progress',
			'complete'  => 'completed',
			'suspended' => 'suspended',
			'canceled'  => 'cancelled',
		);

		return $mapping[ $status ] ?? 'pending';
	}

	/**
	 * Map Checkr adjudication to result
	 *
	 * @param string $adjudication Checkr adjudication
	 * @return string Result
	 */
	private function map_result( $adjudication ) {
		$mapping = array(
			'engaged'     => 'consider',
			'pre_adverse' => 'consider',
			'adverse'     => 'suspended',
			'approved'    => 'clear',
		);

		return $mapping[ $adjudication ] ?? 'pending';
	}

	/**
	 * Calculate completion percentage
	 *
	 * @param array $data Check data
	 * @return int Completion percentage
	 */
	private function calculate_completion( $data ) {
		$status = $data['status'] ?? '';

		if ( 'complete' === $status ) {
			return 100;
		} elseif ( 'pending' === $status ) {
			return 0;
		} else {
			return 50; // In progress
		}
	}

	/**
	 * Parse component results
	 *
	 * @param array $data Check data
	 * @return array Component results
	 */
	private function parse_component_results( $data ) {
		return array(
			'criminal'   => $data['screenings']['criminal'] ?? array(),
			'mvr'        => $data['screenings']['mvr'] ?? array(),
			'education'  => $data['screenings']['education'] ?? array(),
			'employment' => $data['screenings']['employment'] ?? array(),
		);
	}

	/**
	 * Parse criminal records
	 *
	 * @param array $data Check data
	 * @return array Criminal records data
	 */
	private function parse_criminal_records( $data ) {
		$criminal = $data['screenings']['criminal'] ?? array();

		return array(
			'records_found' => ! empty( $criminal['records'] ),
			'records'       => $criminal['records'] ?? array(),
			'status'        => $criminal['status'] ?? 'pending',
		);
	}

	/**
	 * Parse motor vehicle records
	 *
	 * @param array $data Check data
	 * @return array Motor vehicle data
	 */
	private function parse_motor_vehicle( $data ) {
		$mvr = $data['screenings']['mvr'] ?? array();

		return array(
			'license_status' => $mvr['license_status'] ?? '',
			'violations'     => $mvr['violations'] ?? array(),
		);
	}

	/**
	 * Parse education verification
	 *
	 * @param array $data Check data
	 * @return array Education data
	 */
	private function parse_education( $data ) {
		return $data['screenings']['education'] ?? array();
	}

	/**
	 * Parse employment verification
	 *
	 * @param array $data Check data
	 * @return array Employment data
	 */
	private function parse_employment( $data ) {
		return $data['screenings']['employment'] ?? array();
	}
}
