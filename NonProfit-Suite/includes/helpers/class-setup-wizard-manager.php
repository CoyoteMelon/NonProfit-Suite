<?php
/**
 * Setup Wizard Manager
 *
 * Handles the initial setup wizard flow and progress tracking.
 *
 * @package NonprofitSuite
 * @subpackage Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_Setup_Wizard_Manager {
	/**
	 * Singleton instance.
	 *
	 * @var NS_Setup_Wizard_Manager
	 */
	private static $instance = null;

	/**
	 * Setup wizard steps.
	 *
	 * @var array
	 */
	private $steps = array();

	/**
	 * Get singleton instance.
	 *
	 * @return NS_Setup_Wizard_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->define_steps();
	}

	/**
	 * Define setup wizard steps.
	 */
	private function define_steps() {
		$this->steps = array(
			'welcome'          => array(
				'title'       => __( 'Welcome', 'nonprofitsuite' ),
				'description' => __( 'Welcome to NonprofitSuite! Let\'s get you set up.', 'nonprofitsuite' ),
				'fields'      => array(),
			),
			'organization'     => array(
				'title'       => __( 'Organization Details', 'nonprofitsuite' ),
				'description' => __( 'Tell us about your nonprofit organization.', 'nonprofitsuite' ),
				'fields'      => array(
					'org_name'    => array( 'type' => 'text', 'label' => __( 'Organization Name', 'nonprofitsuite' ), 'required' => true ),
					'ein'         => array( 'type' => 'text', 'label' => __( 'EIN (Tax ID)', 'nonprofitsuite' ), 'required' => false ),
					'org_type'    => array( 'type' => 'select', 'label' => __( 'Organization Type', 'nonprofitsuite' ), 'options' => array( '501c3', '501c4', '501c6', 'other' ) ),
					'address'     => array( 'type' => 'textarea', 'label' => __( 'Address', 'nonprofitsuite' ), 'required' => false ),
					'phone'       => array( 'type' => 'text', 'label' => __( 'Phone', 'nonprofitsuite' ), 'required' => false ),
					'email'       => array( 'type' => 'email', 'label' => __( 'Email', 'nonprofitsuite' ), 'required' => false ),
					'website'     => array( 'type' => 'url', 'label' => __( 'Website', 'nonprofitsuite' ), 'required' => false ),
				),
			),
			'integrations'     => array(
				'title'       => __( 'Integrations', 'nonprofitsuite' ),
				'description' => __( 'Connect your essential services.', 'nonprofitsuite' ),
				'fields'      => array(
					'payment_processor' => array( 'type' => 'checkbox', 'label' => __( 'Payment Processing (Stripe recommended)', 'nonprofitsuite' ) ),
					'email_provider'    => array( 'type' => 'checkbox', 'label' => __( 'Email Provider', 'nonprofitsuite' ) ),
					'calendar'          => array( 'type' => 'checkbox', 'label' => __( 'Calendar Integration', 'nonprofitsuite' ) ),
					'accounting'        => array( 'type' => 'checkbox', 'label' => __( 'Accounting Software', 'nonprofitsuite' ) ),
				),
			),
			'users'            => array(
				'title'       => __( 'Team Members', 'nonprofitsuite' ),
				'description' => __( 'Invite your team members.', 'nonprofitsuite' ),
				'fields'      => array(
					'invite_emails' => array( 'type' => 'textarea', 'label' => __( 'Email addresses (one per line)', 'nonprofitsuite' ) ),
				),
			),
			'migration'        => array(
				'title'       => __( 'Import Data', 'nonprofitsuite' ),
				'description' => __( 'Import existing data from another system.', 'nonprofitsuite' ),
				'fields'      => array(
					'has_existing_data' => array( 'type' => 'radio', 'label' => __( 'Do you have existing data to import?', 'nonprofitsuite' ), 'options' => array( 'yes', 'no' ) ),
					'migration_source'  => array( 'type' => 'select', 'label' => __( 'Import from', 'nonprofitsuite' ), 'options' => array( 'csv', 'salesforce', 'mailchimp', 'other' ) ),
				),
			),
			'complete'         => array(
				'title'       => __( 'All Set!', 'nonprofitsuite' ),
				'description' => __( 'Your NonprofitSuite is ready to use.', 'nonprofitsuite' ),
				'fields'      => array(),
			),
		);
	}

	/**
	 * Get all steps.
	 *
	 * @return array Steps.
	 */
	public function get_steps() {
		return $this->steps;
	}

	/**
	 * Get a specific step.
	 *
	 * @param string $step_name Step name.
	 * @return array|null Step data or null.
	 */
	public function get_step( $step_name ) {
		return $this->steps[ $step_name ] ?? null;
	}

	/**
	 * Get wizard progress.
	 *
	 * @param int $organization_id Organization ID.
	 * @return array Progress data.
	 */
	public function get_progress( $organization_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_setup_wizard_progress';
		$steps = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE organization_id = %d ORDER BY created_at ASC",
				$organization_id
			),
			ARRAY_A
		);

		$progress = array();
		foreach ( $steps as $step ) {
			$progress[ $step['step_name'] ] = $step;
		}

		return $progress;
	}

	/**
	 * Update step progress.
	 *
	 * @param int    $organization_id Organization ID.
	 * @param string $step_name Step name.
	 * @param string $status Status.
	 * @param array  $data Step data.
	 * @return bool Success.
	 */
	public function update_step_progress( $organization_id, $step_name, $status, $data = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_setup_wizard_progress';

		$update_data = array(
			'step_status' => $status,
			'step_data'   => wp_json_encode( $data ),
		);

		if ( $status === 'completed' ) {
			$update_data['completed_at'] = current_time( 'mysql' );
			$update_data['completed_by'] = get_current_user_id();
		}

		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE organization_id = %d AND step_name = %s",
				$organization_id,
				$step_name
			)
		);

		if ( $existing ) {
			return $wpdb->update(
				$table,
				$update_data,
				array(
					'organization_id' => $organization_id,
					'step_name'       => $step_name,
				),
				array( '%s', '%s', '%s', '%d' ),
				array( '%d', '%s' )
			) !== false;
		} else {
			return $wpdb->insert(
				$table,
				array_merge(
					array(
						'organization_id' => $organization_id,
						'step_name'       => $step_name,
					),
					$update_data
				),
				array( '%d', '%s', '%s', '%s', '%s', '%d' )
			) !== false;
		}
	}

	/**
	 * Check if wizard is complete.
	 *
	 * @param int $organization_id Organization ID.
	 * @return bool True if complete.
	 */
	public function is_wizard_complete( $organization_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_setup_wizard_progress';
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE organization_id = %d AND step_status = 'completed'",
				$organization_id
			)
		);

		$required_steps = count( $this->steps ) - 1; // Exclude welcome step

		return $count >= $required_steps;
	}

	/**
	 * Get current step.
	 *
	 * @param int $organization_id Organization ID.
	 * @return string Current step name.
	 */
	public function get_current_step( $organization_id ) {
		$progress = $this->get_progress( $organization_id );

		foreach ( $this->steps as $step_name => $step_data ) {
			if ( ! isset( $progress[ $step_name ] ) || $progress[ $step_name ]['step_status'] !== 'completed' ) {
				return $step_name;
			}
		}

		return 'complete';
	}

	/**
	 * Process step submission.
	 *
	 * @param int    $organization_id Organization ID.
	 * @param string $step_name Step name.
	 * @param array  $data Submitted data.
	 * @return bool|WP_Error Success or error.
	 */
	public function process_step( $organization_id, $step_name, $data ) {
		$step = $this->get_step( $step_name );

		if ( ! $step ) {
			return new WP_Error( 'invalid_step', __( 'Invalid step.', 'nonprofitsuite' ) );
		}

		// Validate required fields
		foreach ( $step['fields'] as $field_name => $field_config ) {
			if ( ! empty( $field_config['required'] ) && empty( $data[ $field_name ] ) ) {
				return new WP_Error( 'missing_field', sprintf( __( '%s is required.', 'nonprofitsuite' ), $field_config['label'] ) );
			}
		}

		// Process step-specific logic
		switch ( $step_name ) {
			case 'organization':
				$this->process_organization_step( $organization_id, $data );
				break;

			case 'users':
				$this->process_users_step( $organization_id, $data );
				break;

			case 'migration':
				$this->process_migration_step( $organization_id, $data );
				break;
		}

		// Update progress
		$this->update_step_progress( $organization_id, $step_name, 'completed', $data );

		return true;
	}

	/**
	 * Process organization step.
	 *
	 * @param int   $organization_id Organization ID.
	 * @param array $data Submitted data.
	 */
	private function process_organization_step( $organization_id, $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_organizations';

		$wpdb->update(
			$table,
			array(
				'name'    => $data['org_name'] ?? '',
				'ein'     => $data['ein'] ?? '',
				'type'    => $data['org_type'] ?? '',
				'address' => $data['address'] ?? '',
				'phone'   => $data['phone'] ?? '',
				'email'   => $data['email'] ?? '',
				'website' => $data['website'] ?? '',
			),
			array( 'id' => $organization_id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Process users step.
	 *
	 * @param int   $organization_id Organization ID.
	 * @param array $data Submitted data.
	 */
	private function process_users_step( $organization_id, $data ) {
		if ( empty( $data['invite_emails'] ) ) {
			return;
		}

		$emails = array_filter( array_map( 'trim', explode( "\n", $data['invite_emails'] ) ) );

		foreach ( $emails as $email ) {
			if ( is_email( $email ) ) {
				// Send invitation email (simplified - would use proper email system)
				wp_mail(
					$email,
					__( 'You\'re invited to join our team on NonprofitSuite', 'nonprofitsuite' ),
					sprintf( __( 'You\'ve been invited to join %s on NonprofitSuite.', 'nonprofitsuite' ), get_bloginfo( 'name' ) )
				);
			}
		}
	}

	/**
	 * Process migration step.
	 *
	 * @param int   $organization_id Organization ID.
	 * @param array $data Submitted data.
	 */
	private function process_migration_step( $organization_id, $data ) {
		if ( $data['has_existing_data'] === 'yes' && ! empty( $data['migration_source'] ) ) {
			// Migration will be handled separately
			update_option( 'ns_pending_migration_' . $organization_id, array(
				'source' => $data['migration_source'],
			) );
		}
	}

	/**
	 * Skip a step.
	 *
	 * @param int    $organization_id Organization ID.
	 * @param string $step_name Step name.
	 * @return bool Success.
	 */
	public function skip_step( $organization_id, $step_name ) {
		return $this->update_step_progress( $organization_id, $step_name, 'skipped', array() );
	}
}
