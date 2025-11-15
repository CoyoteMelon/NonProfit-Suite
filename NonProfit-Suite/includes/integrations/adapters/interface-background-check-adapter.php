<?php
/**
 * Background Check Adapter Interface
 *
 * Defines the contract for all background check provider integrations.
 * Supports FCRA-compliant volunteer, staff, and board member screening.
 *
 * CRITICAL: All implementations MUST comply with Fair Credit Reporting Act (FCRA).
 *
 * @package NonprofitSuite
 * @subpackage Integrations/Adapters
 * @since 1.18.0
 */

namespace NonprofitSuite\Integrations\Adapters;

interface NS_Background_Check_Adapter {

	/**
	 * Create candidate in the system
	 *
	 * Registers a candidate for future background checks.
	 * FCRA Requirement: Must include proper disclosures.
	 *
	 * @param array $candidate_data {
	 *     Candidate information.
	 *
	 *     @type string $first_name    First name (required)
	 *     @type string $last_name     Last name (required)
	 *     @type string $email         Email address (required)
	 *     @type string $phone         Phone number (optional)
	 *     @type string $dob           Date of birth (YYYY-MM-DD) (required for most checks)
	 *     @type string $ssn_last_4    Last 4 of SSN (required for most checks)
	 *     @type string $zipcode       ZIP code (required)
	 * }
	 * @return array {
	 *     Candidate creation result.
	 *
	 *     @type bool   $success        Whether candidate was created
	 *     @type string $candidate_id   Provider's candidate ID
	 *     @type string $portal_url     URL for candidate to access their portal
	 *     @type string $error_message  Error message if failed
	 * }
	 */
	public function create_candidate( $candidate_data );

	/**
	 * Create invitation for consent
	 *
	 * Send consent request to candidate via email.
	 * FCRA Requirement: Must include proper disclosures and authorization forms.
	 *
	 * @param string $candidate_id Candidate ID from provider
	 * @param string $package      Package name (Basic, Standard, Premium, etc)
	 * @param array  $options {
	 *     Optional invitation parameters.
	 *
	 *     @type string $custom_message   Custom message to candidate
	 *     @type string $return_url       URL to redirect after consent
	 *     @type array  $disclosures      Custom FCRA disclosures
	 * }
	 * @return array {
	 *     Invitation result.
	 *
	 *     @type bool   $success          Whether invitation was sent
	 *     @type string $invitation_id    Provider's invitation ID
	 *     @type string $invitation_url   URL for candidate to give consent
	 *     @type string $expires_at       When invitation expires
	 *     @type string $error_message    Error message if failed
	 * }
	 */
	public function create_invitation( $candidate_id, $package, $options = array() );

	/**
	 * Create background check
	 *
	 * Order a background check for a candidate.
	 * FCRA Requirement: Must have valid consent before ordering.
	 *
	 * @param string $candidate_id Candidate ID from provider
	 * @param string $package      Package name or custom check configuration
	 * @param array  $options {
	 *     Optional check parameters.
	 *
	 *     @type array  $components       Custom check components (if not using package)
	 *     @type string $purpose          Purpose of check (employment, volunteer, etc)
	 *     @type bool   $expedited        Rush processing (if available)
	 * }
	 * @return array {
	 *     Check creation result.
	 *
	 *     @type bool   $success                 Whether check was created
	 *     @type string $check_id                Provider's check ID
	 *     @type string $status                  Current check status
	 *     @type string $estimated_completion    Estimated completion date
	 *     @type float  $cost                    Cost of check
	 *     @type string $error_message           Error message if failed
	 * }
	 */
	public function create_check( $candidate_id, $package, $options = array() );

	/**
	 * Get check status
	 *
	 * Retrieve current status of a background check.
	 *
	 * @param string $check_id Provider's check ID
	 * @return array {
	 *     Check status information.
	 *
	 *     @type bool   $success                 Whether request succeeded
	 *     @type string $check_id                Provider's check ID
	 *     @type string $status                  Current status (pending, in_progress, completed, etc)
	 *     @type int    $completion_percentage   Percentage complete (0-100)
	 *     @type string $overall_result          Overall result (clear, consider, suspended)
	 *     @type array  $component_results       Results for each component
	 *     @type string $completed_at            Completion timestamp
	 *     @type string $error_message           Error message if failed
	 * }
	 */
	public function get_check_status( $check_id );

	/**
	 * Get full report
	 *
	 * Retrieve the complete background check report.
	 * FCRA Requirement: Must log access for audit trail.
	 *
	 * @param string $check_id Provider's check ID
	 * @return array {
	 *     Full report data.
	 *
	 *     @type bool   $success                 Whether request succeeded
	 *     @type string $check_id                Provider's check ID
	 *     @type string $status                  Check status
	 *     @type string $overall_result          Overall result (clear, consider, suspended)
	 *     @type array  $criminal_records {
	 *         Criminal record search results.
	 *         @type bool   $records_found       Whether records were found
	 *         @type array  $records             Array of criminal records
	 *         @type string $status              Search status (clear, consider)
	 *     }
	 *     @type array  $motor_vehicle {
	 *         Motor vehicle record results.
	 *         @type string $license_status      License status (valid, suspended, etc)
	 *         @type array  $violations          Traffic violations
	 *     }
	 *     @type array  $education_verification  Education verification results
	 *     @type array  $employment_verification Employment verification results
	 *     @type string $report_url              URL to view full report
	 *     @type string $candidate_portal_url    URL for candidate to view their report
	 *     @type string $error_message           Error message if failed
	 * }
	 */
	public function get_report( $check_id );

	/**
	 * Get available packages
	 *
	 * Retrieve list of available background check packages.
	 *
	 * @return array {
	 *     Available packages.
	 *
	 *     @type bool   $success        Whether request succeeded
	 *     @type array  $packages {
	 *         Array of package definitions.
	 *         @type string $package_id   Package identifier
	 *         @type string $name         Package name
	 *         @type string $description  Package description
	 *         @type array  $components   Included check components
	 *         @type float  $price        Package price
	 *         @type string $turnaround   Expected turnaround time
	 *     }
	 *     @type string $error_message  Error message if failed
	 * }
	 */
	public function get_packages();

	/**
	 * Cancel check
	 *
	 * Cancel a pending or in-progress background check.
	 * Note: May not be able to cancel checks that are already in progress.
	 *
	 * @param string $check_id Provider's check ID
	 * @param string $reason   Reason for cancellation
	 * @return array {
	 *     Cancellation result.
	 *
	 *     @type bool   $success        Whether check was cancelled
	 *     @type string $status         New status after cancellation
	 *     @type bool   $refund_issued  Whether refund was issued
	 *     @type float  $refund_amount  Refund amount (if applicable)
	 *     @type string $error_message  Error message if failed
	 * }
	 */
	public function cancel_check( $check_id, $reason = '' );

	/**
	 * Validate webhook signature
	 *
	 * Verify that webhook payload is authentic.
	 * Security: CRITICAL for preventing spoofed status updates.
	 *
	 * @param string $payload   Raw webhook payload
	 * @param string $signature Signature from webhook headers
	 * @param array  $headers   All webhook headers
	 * @return bool Whether signature is valid
	 */
	public function validate_webhook( $payload, $signature, $headers = array() );

	/**
	 * Process webhook
	 *
	 * Parse and process webhook notification from provider.
	 *
	 * @param array $payload Parsed webhook payload
	 * @return array {
	 *     Webhook processing result.
	 *
	 *     @type bool   $success        Whether webhook was processed
	 *     @type string $event_type     Type of event (check.completed, check.updated, etc)
	 *     @type string $check_id       Associated check ID
	 *     @type array  $data           Event data
	 *     @type string $error_message  Error message if failed
	 * }
	 */
	public function process_webhook( $payload );

	/**
	 * Get FCRA disclosure text
	 *
	 * Retrieve the required FCRA disclosure text for this provider.
	 * FCRA Requirement: Must provide proper disclosure before obtaining authorization.
	 *
	 * @param string $check_type Type of check (employment, volunteer, etc)
	 * @return array {
	 *     FCRA disclosure information.
	 *
	 *     @type string $disclosure_text     Full FCRA disclosure text
	 *     @type string $authorization_text  Authorization form text
	 *     @type string $summary_rights      Summary of Rights text
	 *     @type string $version             Disclosure version/date
	 * }
	 */
	public function get_fcra_disclosure( $check_type = 'employment' );

	/**
	 * Initiate adverse action
	 *
	 * Begin adverse action process for a candidate with negative results.
	 * FCRA Requirement: Must follow proper adverse action procedures.
	 *
	 * @param string $check_id Provider's check ID
	 * @param array  $details {
	 *     Adverse action details.
	 *
	 *     @type string $reason           Reason for adverse action
	 *     @type bool   $pre_adverse      Whether this is pre-adverse notice
	 *     @type string $contact_name     Contact person for disputes
	 *     @type string $contact_email    Contact email
	 *     @type string $contact_phone    Contact phone
	 * }
	 * @return array {
	 *     Adverse action result.
	 *
	 *     @type bool   $success           Whether adverse action was initiated
	 *     @type string $adverse_action_id Provider's adverse action ID
	 *     @type string $notice_sent_at    When notice was sent to candidate
	 *     @type string $dispute_period    Dispute period end date
	 *     @type string $error_message     Error message if failed
	 * }
	 */
	public function initiate_adverse_action( $check_id, $details );

	/**
	 * Validate configuration
	 *
	 * Check if adapter is properly configured with valid credentials.
	 *
	 * @return array {
	 *     Validation results.
	 *
	 *     @type bool   $valid             Whether configuration is valid
	 *     @type array  $errors            Array of configuration errors
	 *     @type string $error_message     Primary error message
	 * }
	 */
	public function validate_configuration();

	/**
	 * Calculate cost for check
	 *
	 * Estimate the cost of a background check before ordering.
	 *
	 * @param string $package Package name or custom configuration
	 * @param array  $options Check options
	 * @return float Estimated cost in dollars
	 */
	public function calculate_cost( $package, $options = array() );
}
