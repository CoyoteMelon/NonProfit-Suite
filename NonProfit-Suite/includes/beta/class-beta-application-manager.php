<?php
/**
 * Beta Application Manager
 *
 * Manages beta testing program applications, slot allocation, and approvals.
 *
 * @package    NonprofitSuite
 * @subpackage Beta
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonprofitSuite_Beta_Application_Manager Class
 *
 * Handles beta program applications and license management.
 */
class NonprofitSuite_Beta_Application_Manager {

	/**
	 * Singleton instance
	 *
	 * @var NonprofitSuite_Beta_Application_Manager
	 */
	private static $instance = null;

	/**
	 * US States and Territories
	 *
	 * @var array
	 */
	private $states_territories = array(
		// 50 States
		'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
		'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
		'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
		'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
		'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY',
		// 6 Territories
		'PR', 'VI', 'GU', 'AS', 'MP', 'DC',
	);

	/**
	 * Get singleton instance
	 *
	 * @return NonprofitSuite_Beta_Application_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Hook into WordPress
		add_action( 'init', array( $this, 'register_shortcodes' ) );
	}

	/**
	 * Register shortcodes
	 */
	public function register_shortcodes() {
		add_shortcode( 'nonprofitsuite_beta_signup', array( $this, 'render_signup_form' ) );
	}

	/**
	 * Submit beta application
	 *
	 * @param array $data Application data
	 * @return array|WP_Error Application result or error
	 */
	public function submit_application( $data ) {
		global $wpdb;

		// Validate required fields
		$required = array( 'organization_name', 'contact_name', 'contact_email', 'state' );
		foreach ( $required as $field ) {
			if ( empty( $data[ $field ] ) ) {
				return new WP_Error( 'missing_field', sprintf( __( 'Required field missing: %s', 'nonprofitsuite' ), $field ) );
			}
		}

		// Validate state/territory
		if ( ! in_array( strtoupper( $data['state'] ), $this->states_territories ) ) {
			return new WP_Error( 'invalid_state', __( 'Invalid state or territory code', 'nonprofitsuite' ) );
		}

		// Validate email
		if ( ! is_email( $data['contact_email'] ) ) {
			return new WP_Error( 'invalid_email', __( 'Invalid email address', 'nonprofitsuite' ) );
		}

		// Check for duplicate email
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}ns_beta_applications WHERE contact_email = %s",
			$data['contact_email']
		) );

		if ( $existing ) {
			return new WP_Error( 'duplicate_email', __( 'An application with this email already exists', 'nonprofitsuite' ) );
		}

		// Determine slot type and check availability
		$is_501c3 = ! empty( $data['is_501c3'] );
		$slot_type = $is_501c3 ? '501c3' : 'pre_nonprofit';
		$status = 'pending';

		$slots_available = $this->check_slot_availability( $slot_type, strtoupper( $data['state'] ) );

		if ( $slots_available ) {
			// Auto-approve if slots available
			$status = 'approved';
		} else {
			// Put on waitlist
			$status = 'waitlist';
		}

		// Prepare application data
		$application_data = array(
			'organization_name'   => sanitize_text_field( $data['organization_name'] ),
			'ein'                 => sanitize_text_field( $data['ein'] ?? '' ),
			'contact_name'        => sanitize_text_field( $data['contact_name'] ),
			'contact_email'       => sanitize_email( $data['contact_email'] ),
			'contact_phone'       => sanitize_text_field( $data['contact_phone'] ?? '' ),
			'is_501c3'            => $is_501c3 ? 1 : 0,
			'has_determination_letter' => ! empty( $data['has_determination_letter'] ) ? 1 : 0,
			'determination_letter_file' => sanitize_text_field( $data['determination_letter_file'] ?? '' ),
			'state'               => strtoupper( $data['state'] ),
			'city'                => sanitize_text_field( $data['city'] ?? '' ),
			'status'              => $status,
			'slot_type'           => $slot_type,
			'application_date'    => current_time( 'mysql' ),
		);

		// Generate license key if approved
		if ( $status === 'approved' ) {
			$application_data['license_key'] = $this->generate_license_key();
			$application_data['approved_date'] = current_time( 'mysql' );
			$application_data['approved_by'] = get_current_user_id();
		}

		// Insert application
		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_beta_applications',
			$application_data,
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( ! $result ) {
			return new WP_Error( 'db_error', __( 'Failed to save application', 'nonprofitsuite' ) );
		}

		$application_id = $wpdb->insert_id;

		// Send confirmation email
		$this->send_application_email( $application_id );

		// Log activity
		$this->log_activity( $application_id, 'application_submitted', array(
			'status' => $status,
			'slot_type' => $slot_type,
		) );

		// Trigger action
		do_action( 'ns_beta_application_submitted', $application_id, $application_data );

		return array(
			'success'        => true,
			'application_id' => $application_id,
			'status'         => $status,
			'license_key'    => $application_data['license_key'] ?? null,
			'message'        => $status === 'approved' ?
				__( 'Congratulations! Your application has been approved. Check your email for your license key.', 'nonprofitsuite' ) :
				__( 'Your application has been received and placed on the waitlist. We will notify you if a slot becomes available.', 'nonprofitsuite' ),
		);
	}

	/**
	 * Check slot availability
	 *
	 * @param string $slot_type Slot type (501c3 or pre_nonprofit)
	 * @param string $state     State code (for pre-nonprofits)
	 * @return bool True if slots available
	 */
	public function check_slot_availability( $slot_type, $state = '' ) {
		global $wpdb;

		$settings = get_option( 'ns_beta_program_settings', array() );

		if ( $slot_type === '501c3' ) {
			// Check 501(c)(3) slots (max 500)
			$max_slots = $settings['max_501c3_slots'] ?? 500;
			$current_count = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ns_beta_applications
				WHERE slot_type = %s AND status IN ('approved', 'pending')",
				'501c3'
			) );

			return $current_count < $max_slots;
		} else {
			// Check pre-nonprofit slots (max 10 per state/territory)
			$max_per_state = $settings['max_prenp_per_state'] ?? 10;
			$current_count = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ns_beta_applications
				WHERE slot_type = %s AND state = %s AND status IN ('approved', 'pending')",
				'pre_nonprofit',
				$state
			) );

			return $current_count < $max_per_state;
		}
	}

	/**
	 * Generate license key
	 *
	 * Generates a unique beta license key
	 *
	 * @return string License key
	 */
	private function generate_license_key() {
		// Format: BETA-XXXX-XXXX-XXXX-XXXX
		$segments = array();
		for ( $i = 0; $i < 4; $i++ ) {
			$segments[] = strtoupper( substr( md5( wp_generate_uuid4() ), 0, 4 ) );
		}

		return 'BETA-' . implode( '-', $segments );
	}

	/**
	 * Activate beta license
	 *
	 * Activates a beta license for a site and integrates with Freemius
	 *
	 * @param string $license_key License key
	 * @param array  $site_data   Site activation data
	 * @return bool|WP_Error True on success, error on failure
	 */
	public function activate_license( $license_key, $site_data = array() ) {
		global $wpdb;

		// Find application by license key
		$application = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}ns_beta_applications WHERE license_key = %s",
			$license_key
		), ARRAY_A );

		if ( ! $application ) {
			return new WP_Error( 'invalid_license', __( 'Invalid license key', 'nonprofitsuite' ) );
		}

		if ( $application['status'] !== 'approved' ) {
			return new WP_Error( 'not_approved', __( 'License not approved', 'nonprofitsuite' ) );
		}

		// Note: Pre-nonprofits are ENCOURAGED to complete "Forming a Nonprofit" module,
		// but it's NOT required for license activation. They may take 6-12 months to complete it.
		// We track completion but don't block activation.

		// Mark as activated
		$wpdb->update(
			$wpdb->prefix . 'ns_beta_applications',
			array(
				'license_activated'      => 1,
				'license_activated_date' => current_time( 'mysql' ),
			),
			array( 'id' => $application['id'] ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		// Integrate with Freemius (if available)
		$this->sync_with_freemius( $application['id'], $license_key );

		// Log activity
		$this->log_activity( $application['id'], 'license_activated', $site_data );

		do_action( 'ns_beta_license_activated', $application['id'], $license_key );

		return true;
	}

	/**
	 * Sync with Freemius
	 *
	 * Creates or updates license in Freemius system
	 *
	 * @param int    $application_id Application ID
	 * @param string $license_key    License key
	 * @return bool Success
	 */
	private function sync_with_freemius( $application_id, $license_key ) {
		// Check if Freemius is available
		if ( ! function_exists( 'ns_fs' ) ) {
			return false;
		}

		global $wpdb;

		$application = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}ns_beta_applications WHERE id = %d",
			$application_id
		), ARRAY_A );

		if ( ! $application ) {
			return false;
		}

		try {
			// Create Freemius user if doesn't exist
			// This would integrate with Freemius API
			// Freemius documentation: https://freemius.com/help/documentation/

			// Store Freemius IDs
			$wpdb->update(
				$wpdb->prefix . 'ns_beta_applications',
				array(
					'freemius_user_id'    => 0, // Would be actual Freemius user ID
					'freemius_license_id' => 0, // Would be actual Freemius license ID
				),
				array( 'id' => $application_id ),
				array( '%d', '%d' ),
				array( '%d' )
			);

			return true;
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Mark forming module as completed
	 *
	 * For pre-nonprofits who complete the Forming a Nonprofit module
	 *
	 * @param int $application_id Application ID
	 * @return bool Success
	 */
	public function mark_forming_module_completed( $application_id ) {
		global $wpdb;

		$result = $wpdb->update(
			$wpdb->prefix . 'ns_beta_applications',
			array(
				'forming_module_completed'      => 1,
				'forming_module_completed_date' => current_time( 'mysql' ),
			),
			array( 'id' => $application_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		if ( $result ) {
			$this->log_activity( $application_id, 'forming_module_completed', array() );
			do_action( 'ns_beta_forming_module_completed', $application_id );
		}

		return (bool) $result;
	}

	/**
	 * Get application by ID
	 *
	 * @param int $application_id Application ID
	 * @return array|null Application data
	 */
	public function get_application( $application_id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}ns_beta_applications WHERE id = %d",
			$application_id
		), ARRAY_A );
	}

	/**
	 * Get application by license key
	 *
	 * @param string $license_key License key
	 * @return array|null Application data
	 */
	public function get_application_by_license( $license_key ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}ns_beta_applications WHERE license_key = %s",
			$license_key
		), ARRAY_A );
	}

	/**
	 * Get application statistics
	 *
	 * @return array Statistics
	 */
	public function get_statistics() {
		global $wpdb;

		$stats = array();

		// Total applications
		$stats['total'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ns_beta_applications"
		);

		// 501(c)(3) counts
		$stats['501c3_total'] = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ns_beta_applications WHERE slot_type = %s",
			'501c3'
		) );

		$stats['501c3_approved'] = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ns_beta_applications WHERE slot_type = %s AND status = %s",
			'501c3',
			'approved'
		) );

		// Pre-nonprofit counts
		$stats['prenp_total'] = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ns_beta_applications WHERE slot_type = %s",
			'pre_nonprofit'
		) );

		$stats['prenp_approved'] = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ns_beta_applications WHERE slot_type = %s AND status = %s",
			'pre_nonprofit',
			'approved'
		) );

		// By state
		$stats['by_state'] = $wpdb->get_results(
			"SELECT state, slot_type, COUNT(*) as count
			FROM {$wpdb->prefix}ns_beta_applications
			WHERE slot_type = 'pre_nonprofit' AND status IN ('approved', 'pending')
			GROUP BY state, slot_type",
			ARRAY_A
		);

		// Status breakdown
		$stats['by_status'] = $wpdb->get_results(
			"SELECT status, COUNT(*) as count
			FROM {$wpdb->prefix}ns_beta_applications
			GROUP BY status",
			ARRAY_A
		);

		// Activated licenses
		$stats['activated'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ns_beta_applications WHERE license_activated = 1"
		);

		return $stats;
	}

	/**
	 * Log activity
	 *
	 * @param int    $application_id Application ID
	 * @param string $activity_type  Activity type
	 * @param array  $activity_data  Activity data
	 * @return bool Success
	 */
	public function log_activity( $application_id, $activity_type, $activity_data = array() ) {
		global $wpdb;

		return (bool) $wpdb->insert(
			$wpdb->prefix . 'ns_beta_activity',
			array(
				'application_id' => $application_id,
				'activity_type'  => $activity_type,
				'activity_data'  => wp_json_encode( $activity_data ),
				'occurred_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Send application email
	 *
	 * @param int $application_id Application ID
	 * @return bool Success
	 */
	private function send_application_email( $application_id ) {
		$application = $this->get_application( $application_id );

		if ( ! $application ) {
			return false;
		}

		// Determine which email template to use
		$template_key = $application['status'] === 'approved' ?
			'beta_approved' :
			'beta_received';

		// Get email template
		$template_manager = NonprofitSuite_Beta_Email_Templates::get_instance();
		$email_data = $template_manager->get_template( $template_key, $application );

		// Send email
		$sent = wp_mail(
			$application['contact_email'],
			$email_data['subject'],
			$email_data['body'],
			array( 'Content-Type: text/html; charset=UTF-8' )
		);

		return $sent;
	}

	/**
	 * Render beta signup form (shortcode)
	 *
	 * @param array $atts Shortcode attributes
	 * @return string HTML output
	 */
	public function render_signup_form( $atts ) {
		ob_start();
		include dirname( __FILE__ ) . '/views/beta-signup-form.php';
		return ob_get_clean();
	}
}

// Initialize
NonprofitSuite_Beta_Application_Manager::get_instance();
