<?php
/**
 * Beta Survey Manager
 *
 * Manages scheduled surveys for beta testers over a year+ timeline.
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
 * NonprofitSuite_Beta_Survey_Manager Class
 *
 * Handles survey scheduling, submissions, and analysis.
 */
class NonprofitSuite_Beta_Survey_Manager {

	/**
	 * Singleton instance
	 *
	 * @var NonprofitSuite_Beta_Survey_Manager
	 */
	private static $instance = null;

	/**
	 * Survey version
	 *
	 * @var string
	 */
	const SURVEY_VERSION = '1.0';

	/**
	 * Get singleton instance
	 *
	 * @return NonprofitSuite_Beta_Survey_Manager
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
		// Schedule daily cron to check for surveys
		add_action( 'ns_beta_check_surveys', array( $this, 'check_scheduled_surveys' ) );

		if ( ! wp_next_scheduled( 'ns_beta_check_surveys' ) ) {
			wp_schedule_event( time(), 'daily', 'ns_beta_check_surveys' );
		}

		// Register shortcode
		add_shortcode( 'nonprofitsuite_beta_survey', array( $this, 'render_survey_form' ) );
	}

	/**
	 * Submit survey response
	 *
	 * @param int   $application_id Application ID
	 * @param array $survey_data    Survey response data
	 * @return array|WP_Error Result or error
	 */
	public function submit_survey( $application_id, $survey_data ) {
		global $wpdb;

		// Validate application exists
		$app_manager = NonprofitSuite_Beta_Application_Manager::get_instance();
		$application = $app_manager->get_application( $application_id );

		if ( ! $application ) {
			return new WP_Error( 'invalid_application', __( 'Invalid application ID', 'nonprofitsuite' ) );
		}

		// Prepare survey data
		$data = array(
			'application_id'     => $application_id,
			'survey_version'     => self::SURVEY_VERSION,
			'submitted_at'       => current_time( 'mysql' ),

			// Overall Experience (1-5 scale)
			'overall_satisfaction' => isset( $survey_data['overall_satisfaction'] ) ? (int) $survey_data['overall_satisfaction'] : null,
			'ease_of_use'          => isset( $survey_data['ease_of_use'] ) ? (int) $survey_data['ease_of_use'] : null,
			'feature_completeness' => isset( $survey_data['feature_completeness'] ) ? (int) $survey_data['feature_completeness'] : null,
			'performance'          => isset( $survey_data['performance'] ) ? (int) $survey_data['performance'] : null,
			'would_recommend'      => isset( $survey_data['would_recommend'] ) ? (int) $survey_data['would_recommend'] : null,

			// Feature Ratings (1-5 scale, NULL if not used)
			'meetings_rating'        => isset( $survey_data['meetings_rating'] ) ? (int) $survey_data['meetings_rating'] : null,
			'documents_rating'       => isset( $survey_data['documents_rating'] ) ? (int) $survey_data['documents_rating'] : null,
			'treasury_rating'        => isset( $survey_data['treasury_rating'] ) ? (int) $survey_data['treasury_rating'] : null,
			'donors_rating'          => isset( $survey_data['donors_rating'] ) ? (int) $survey_data['donors_rating'] : null,
			'volunteers_rating'      => isset( $survey_data['volunteers_rating'] ) ? (int) $survey_data['volunteers_rating'] : null,
			'compliance_rating'      => isset( $survey_data['compliance_rating'] ) ? (int) $survey_data['compliance_rating'] : null,
			'calendar_rating'        => isset( $survey_data['calendar_rating'] ) ? (int) $survey_data['calendar_rating'] : null,
			'email_rating'           => isset( $survey_data['email_rating'] ) ? (int) $survey_data['email_rating'] : null,
			'payments_rating'        => isset( $survey_data['payments_rating'] ) ? (int) $survey_data['payments_rating'] : null,
			'membership_rating'      => isset( $survey_data['membership_rating'] ) ? (int) $survey_data['membership_rating'] : null,
			'board_rating'           => isset( $survey_data['board_rating'] ) ? (int) $survey_data['board_rating'] : null,
			'communications_rating'  => isset( $survey_data['communications_rating'] ) ? (int) $survey_data['communications_rating'] : null,
			'events_rating'          => isset( $survey_data['events_rating'] ) ? (int) $survey_data['events_rating'] : null,
			'grants_rating'          => isset( $survey_data['grants_rating'] ) ? (int) $survey_data['grants_rating'] : null,
			'inventory_rating'       => isset( $survey_data['inventory_rating'] ) ? (int) $survey_data['inventory_rating'] : null,
			'programs_rating'        => isset( $survey_data['programs_rating'] ) ? (int) $survey_data['programs_rating'] : null,

			// Integration Usage
			'integrations_used'   => isset( $survey_data['integrations_used'] ) ? wp_json_encode( $survey_data['integrations_used'] ) : null,
			'integration_issues'  => isset( $survey_data['integration_issues'] ) ? wp_json_encode( $survey_data['integration_issues'] ) : null,

			// Open Feedback
			'what_works_well' => isset( $survey_data['what_works_well'] ) ? sanitize_textarea_field( $survey_data['what_works_well'] ) : '',
			'what_is_broken'  => isset( $survey_data['what_is_broken'] ) ? sanitize_textarea_field( $survey_data['what_is_broken'] ) : '',
			'what_is_missing' => isset( $survey_data['what_is_missing'] ) ? sanitize_textarea_field( $survey_data['what_is_missing'] ) : '',
			'feature_requests' => isset( $survey_data['feature_requests'] ) ? sanitize_textarea_field( $survey_data['feature_requests'] ) : '',
			'pain_points'     => isset( $survey_data['pain_points'] ) ? sanitize_textarea_field( $survey_data['pain_points'] ) : '',

			// Technical Info
			'wordpress_version' => isset( $survey_data['wordpress_version'] ) ? sanitize_text_field( $survey_data['wordpress_version'] ) : get_bloginfo( 'version' ),
			'php_version'       => isset( $survey_data['php_version'] ) ? sanitize_text_field( $survey_data['php_version'] ) : phpversion(),
			'server_type'       => isset( $survey_data['server_type'] ) ? sanitize_text_field( $survey_data['server_type'] ) : $_SERVER['SERVER_SOFTWARE'] ?? '',
			'active_users'      => isset( $survey_data['active_users'] ) ? (int) $survey_data['active_users'] : null,
			'org_size'          => isset( $survey_data['org_size'] ) ? sanitize_text_field( $survey_data['org_size'] ) : '',
		);

		// Insert survey
		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_beta_surveys',
			$data,
			array(
				'%d', '%s', '%s',
				'%d', '%d', '%d', '%d', '%d',
				'%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d',
				'%s', '%s',
				'%s', '%s', '%s', '%s', '%s',
				'%s', '%s', '%s', '%d', '%s',
			)
		);

		if ( ! $result ) {
			return new WP_Error( 'db_error', __( 'Failed to save survey', 'nonprofitsuite' ) );
		}

		$survey_id = $wpdb->insert_id;

		// Update application survey count and last survey date
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}ns_beta_applications
			SET survey_count = survey_count + 1,
			    last_survey_date = %s
			WHERE id = %d",
			current_time( 'mysql' ),
			$application_id
		) );

		// Log activity
		$app_manager->log_activity( $application_id, 'survey_completed', array(
			'survey_id' => $survey_id,
			'version'   => self::SURVEY_VERSION,
		) );

		do_action( 'ns_beta_survey_submitted', $survey_id, $application_id, $survey_data );

		return array(
			'success'   => true,
			'survey_id' => $survey_id,
			'message'   => __( 'Thank you for completing the survey!', 'nonprofitsuite' ),
		);
	}

	/**
	 * Check for scheduled surveys
	 *
	 * Runs daily to check which beta testers are due for surveys
	 */
	public function check_scheduled_surveys() {
		global $wpdb;

		$settings = get_option( 'ns_beta_program_settings', array() );
		$schedule_days = $settings['survey_schedule_days'] ?? array( 7, 14, 30, 60, 90, 120, 150, 180, 270, 365 );

		// Get all approved and activated applications
		$applications = $wpdb->get_results(
			"SELECT id, contact_email, organization_name, license_activated_date, survey_count
			FROM {$wpdb->prefix}ns_beta_applications
			WHERE status = 'approved'
			AND license_activated = 1
			AND license_activated_date IS NOT NULL",
			ARRAY_A
		);

		foreach ( $applications as $application ) {
			$days_since_activation = $this->get_days_since_activation( $application['license_activated_date'] );
			$survey_count = (int) $application['survey_count'];

			// Check if they're due for a survey
			if ( $survey_count < count( $schedule_days ) && $days_since_activation >= $schedule_days[ $survey_count ] ) {
				// They're due for their next scheduled survey
				$this->send_survey_invitation( $application['id'] );
			}
		}
	}

	/**
	 * Get days since activation
	 *
	 * @param string $activation_date Activation date
	 * @return int Days since activation
	 */
	private function get_days_since_activation( $activation_date ) {
		$activated = strtotime( $activation_date );
		$now = time();
		return floor( ( $now - $activated ) / DAY_IN_SECONDS );
	}

	/**
	 * Send survey invitation
	 *
	 * @param int $application_id Application ID
	 * @return bool Success
	 */
	public function send_survey_invitation( $application_id ) {
		$app_manager = NonprofitSuite_Beta_Application_Manager::get_instance();
		$application = $app_manager->get_application( $application_id );

		if ( ! $application ) {
			return false;
		}

		// Get email template
		$template_manager = NonprofitSuite_Beta_Email_Templates::get_instance();
		$email_data = $template_manager->get_template( 'survey_invitation', $application );

		// Send email
		$sent = wp_mail(
			$application['contact_email'],
			$email_data['subject'],
			$email_data['body'],
			array( 'Content-Type: text/html; charset=UTF-8' )
		);

		if ( $sent ) {
			$app_manager->log_activity( $application_id, 'survey_invitation_sent', array(
				'survey_number' => $application['survey_count'] + 1,
			) );
		}

		return $sent;
	}

	/**
	 * Get survey statistics
	 *
	 * @return array Statistics
	 */
	public function get_statistics() {
		global $wpdb;

		$stats = array();

		// Total surveys submitted
		$stats['total_surveys'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ns_beta_surveys"
		);

		// Average ratings
		$avg_ratings = $wpdb->get_row(
			"SELECT
				AVG(overall_satisfaction) as avg_satisfaction,
				AVG(ease_of_use) as avg_ease,
				AVG(feature_completeness) as avg_completeness,
				AVG(performance) as avg_performance,
				AVG(would_recommend) as avg_recommend
			FROM {$wpdb->prefix}ns_beta_surveys",
			ARRAY_A
		);

		$stats['averages'] = $avg_ratings;

		// Feature ratings
		$feature_ratings = $wpdb->get_row(
			"SELECT
				AVG(meetings_rating) as meetings,
				AVG(documents_rating) as documents,
				AVG(treasury_rating) as treasury,
				AVG(donors_rating) as donors,
				AVG(volunteers_rating) as volunteers,
				AVG(compliance_rating) as compliance,
				AVG(calendar_rating) as calendar,
				AVG(email_rating) as email,
				AVG(payments_rating) as payments,
				AVG(membership_rating) as membership,
				AVG(board_rating) as board,
				AVG(communications_rating) as communications,
				AVG(events_rating) as events,
				AVG(grants_rating) as grants,
				AVG(inventory_rating) as inventory,
				AVG(programs_rating) as programs
			FROM {$wpdb->prefix}ns_beta_surveys",
			ARRAY_A
		);

		$stats['feature_ratings'] = $feature_ratings;

		// Response rate by survey number
		$response_rates = $wpdb->get_results(
			"SELECT
				a.survey_count,
				COUNT(*) as count
			FROM {$wpdb->prefix}ns_beta_applications a
			WHERE a.status = 'approved' AND a.license_activated = 1
			GROUP BY a.survey_count
			ORDER BY a.survey_count",
			ARRAY_A
		);

		$stats['response_rates'] = $response_rates;

		return $stats;
	}

	/**
	 * Get survey responses for an application
	 *
	 * @param int $application_id Application ID
	 * @return array Survey responses
	 */
	public function get_application_surveys( $application_id ) {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}ns_beta_surveys
			WHERE application_id = %d
			ORDER BY submitted_at DESC",
			$application_id
		), ARRAY_A );
	}

	/**
	 * Render survey form (shortcode)
	 *
	 * @param array $atts Shortcode attributes
	 * @return string HTML output
	 */
	public function render_survey_form( $atts ) {
		$atts = shortcode_atts( array(
			'application_id' => 0,
		), $atts );

		if ( empty( $atts['application_id'] ) ) {
			return '<p>' . __( 'Invalid survey link.', 'nonprofitsuite' ) . '</p>';
		}

		ob_start();
		include dirname( __FILE__ ) . '/views/beta-survey-form.php';
		return ob_get_clean();
	}
}

// Initialize
NonprofitSuite_Beta_Survey_Manager::get_instance();
