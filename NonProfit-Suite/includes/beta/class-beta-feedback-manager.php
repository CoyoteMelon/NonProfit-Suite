<?php
/**
 * Beta Feedback Manager
 *
 * Manages ad-hoc feedback from beta testers (bugs, feature requests, etc.).
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
 * NonprofitSuite_Beta_Feedback_Manager Class
 *
 * Handles feedback submissions and responses.
 */
class NonprofitSuite_Beta_Feedback_Manager {

	/**
	 * Singleton instance
	 *
	 * @var NonprofitSuite_Beta_Feedback_Manager
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return NonprofitSuite_Beta_Feedback_Manager
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
		// Add quick feedback button to admin bar
		add_action( 'admin_bar_menu', array( $this, 'add_feedback_button' ), 999 );

		// Register AJAX handlers
		add_action( 'wp_ajax_ns_beta_submit_feedback', array( $this, 'ajax_submit_feedback' ) );

		// Enqueue scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Submit feedback
	 *
	 * @param int   $application_id Application ID
	 * @param array $feedback_data  Feedback data
	 * @return array|WP_Error Result or error
	 */
	public function submit_feedback( $application_id, $feedback_data ) {
		global $wpdb;

		// Validate application
		$app_manager = NonprofitSuite_Beta_Application_Manager::get_instance();
		$application = $app_manager->get_application( $application_id );

		if ( ! $application ) {
			return new WP_Error( 'invalid_application', __( 'Invalid application ID', 'nonprofitsuite' ) );
		}

		// Validate required fields
		if ( empty( $feedback_data['message'] ) ) {
			return new WP_Error( 'missing_message', __( 'Feedback message is required', 'nonprofitsuite' ) );
		}

		// Determine priority based on feedback type
		$priority = $this->determine_priority( $feedback_data['feedback_type'] ?? 'improvement' );

		// Prepare feedback data
		$data = array(
			'application_id' => $application_id,
			'feedback_type'  => sanitize_text_field( $feedback_data['feedback_type'] ?? 'improvement' ),
			'category'       => sanitize_text_field( $feedback_data['category'] ?? '' ),
			'subject'        => sanitize_text_field( $feedback_data['subject'] ?? '' ),
			'message'        => sanitize_textarea_field( $feedback_data['message'] ),
			'screenshot_url' => esc_url_raw( $feedback_data['screenshot_url'] ?? '' ),
			'user_agent'     => sanitize_text_field( $feedback_data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '' ),
			'browser'        => $this->detect_browser( $feedback_data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '' ),
			'os'             => $this->detect_os( $feedback_data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '' ),
			'status'         => 'new',
			'priority'       => $priority,
			'submitted_at'   => current_time( 'mysql' ),
		);

		// Insert feedback
		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_beta_feedback',
			$data,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $result ) {
			return new WP_Error( 'db_error', __( 'Failed to save feedback', 'nonprofitsuite' ) );
		}

		$feedback_id = $wpdb->insert_id;

		// Update application feedback count
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}ns_beta_applications
			SET feedback_count = feedback_count + 1
			WHERE id = %d",
			$application_id
		) );

		// Log activity
		$app_manager->log_activity( $application_id, 'feedback_submitted', array(
			'feedback_id'   => $feedback_id,
			'feedback_type' => $data['feedback_type'],
		) );

		// Send notification email to admin
		$this->send_admin_notification( $feedback_id );

		do_action( 'ns_beta_feedback_submitted', $feedback_id, $application_id, $feedback_data );

		return array(
			'success'     => true,
			'feedback_id' => $feedback_id,
			'message'     => __( 'Thank you for your feedback!', 'nonprofitsuite' ),
		);
	}

	/**
	 * Respond to feedback
	 *
	 * @param int    $feedback_id Feedback ID
	 * @param string $response    Admin response
	 * @param string $new_status  New status
	 * @return bool|WP_Error Success or error
	 */
	public function respond_to_feedback( $feedback_id, $response, $new_status = 'responded' ) {
		global $wpdb;

		$result = $wpdb->update(
			$wpdb->prefix . 'ns_beta_feedback',
			array(
				'admin_response' => sanitize_textarea_field( $response ),
				'responded_by'   => get_current_user_id(),
				'responded_at'   => current_time( 'mysql' ),
				'status'         => $new_status,
			),
			array( 'id' => $feedback_id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( ! $result ) {
			return new WP_Error( 'db_error', __( 'Failed to save response', 'nonprofitsuite' ) );
		}

		// Get feedback
		$feedback = $this->get_feedback( $feedback_id );

		if ( $feedback ) {
			// Send response email to beta tester
			$this->send_response_email( $feedback );

			do_action( 'ns_beta_feedback_responded', $feedback_id, $response );
		}

		return true;
	}

	/**
	 * Get feedback by ID
	 *
	 * @param int $feedback_id Feedback ID
	 * @return array|null Feedback data
	 */
	public function get_feedback( $feedback_id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}ns_beta_feedback WHERE id = %d",
			$feedback_id
		), ARRAY_A );
	}

	/**
	 * Get all feedback for an application
	 *
	 * @param int $application_id Application ID
	 * @return array Feedback items
	 */
	public function get_application_feedback( $application_id ) {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}ns_beta_feedback
			WHERE application_id = %d
			ORDER BY submitted_at DESC",
			$application_id
		), ARRAY_A );
	}

	/**
	 * Get feedback statistics
	 *
	 * @return array Statistics
	 */
	public function get_statistics() {
		global $wpdb;

		$stats = array();

		// Total feedback
		$stats['total'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ns_beta_feedback"
		);

		// By type
		$stats['by_type'] = $wpdb->get_results(
			"SELECT feedback_type, COUNT(*) as count
			FROM {$wpdb->prefix}ns_beta_feedback
			GROUP BY feedback_type
			ORDER BY count DESC",
			ARRAY_A
		);

		// By status
		$stats['by_status'] = $wpdb->get_results(
			"SELECT status, COUNT(*) as count
			FROM {$wpdb->prefix}ns_beta_feedback
			GROUP BY status",
			ARRAY_A
		);

		// By priority
		$stats['by_priority'] = $wpdb->get_results(
			"SELECT priority, COUNT(*) as count
			FROM {$wpdb->prefix}ns_beta_feedback
			GROUP BY priority
			ORDER BY FIELD(priority, 'critical', 'high', 'medium', 'low')",
			ARRAY_A
		);

		// Response time (average days to respond)
		$avg_response_time = $wpdb->get_var(
			"SELECT AVG(DATEDIFF(responded_at, submitted_at))
			FROM {$wpdb->prefix}ns_beta_feedback
			WHERE responded_at IS NOT NULL"
		);

		$stats['avg_response_days'] = $avg_response_time ? round( $avg_response_time, 1 ) : 0;

		return $stats;
	}

	/**
	 * Determine priority based on feedback type
	 *
	 * @param string $feedback_type Feedback type
	 * @return string Priority level
	 */
	private function determine_priority( $feedback_type ) {
		$priority_map = array(
			'bug'             => 'high',
			'feature_request' => 'medium',
			'improvement'     => 'medium',
			'question'        => 'low',
			'praise'          => 'low',
		);

		return $priority_map[ $feedback_type ] ?? 'medium';
	}

	/**
	 * Detect browser from user agent
	 *
	 * @param string $user_agent User agent string
	 * @return string Browser name
	 */
	private function detect_browser( $user_agent ) {
		if ( strpos( $user_agent, 'Chrome' ) !== false ) {
			return 'Chrome';
		} elseif ( strpos( $user_agent, 'Safari' ) !== false ) {
			return 'Safari';
		} elseif ( strpos( $user_agent, 'Firefox' ) !== false ) {
			return 'Firefox';
		} elseif ( strpos( $user_agent, 'Edge' ) !== false ) {
			return 'Edge';
		}
		return 'Unknown';
	}

	/**
	 * Detect OS from user agent
	 *
	 * @param string $user_agent User agent string
	 * @return string OS name
	 */
	private function detect_os( $user_agent ) {
		if ( strpos( $user_agent, 'Windows' ) !== false ) {
			return 'Windows';
		} elseif ( strpos( $user_agent, 'Mac' ) !== false ) {
			return 'macOS';
		} elseif ( strpos( $user_agent, 'Linux' ) !== false ) {
			return 'Linux';
		} elseif ( strpos( $user_agent, 'iPhone' ) !== false || strpos( $user_agent, 'iPad' ) !== false ) {
			return 'iOS';
		} elseif ( strpos( $user_agent, 'Android' ) !== false ) {
			return 'Android';
		}
		return 'Unknown';
	}

	/**
	 * Send admin notification
	 *
	 * @param int $feedback_id Feedback ID
	 * @return bool Success
	 */
	private function send_admin_notification( $feedback_id ) {
		$feedback = $this->get_feedback( $feedback_id );

		if ( ! $feedback ) {
			return false;
		}

		$admin_email = get_option( 'admin_email' );

		$subject = sprintf(
			'[Beta Feedback] %s: %s',
			ucfirst( $feedback['feedback_type'] ),
			$feedback['subject'] ?: 'No subject'
		);

		$message = sprintf(
			"New beta feedback received:\n\nType: %s\nPriority: %s\nCategory: %s\n\nMessage:\n%s\n\nView in dashboard: %s",
			$feedback['feedback_type'],
			$feedback['priority'],
			$feedback['category'],
			$feedback['message'],
			admin_url( 'admin.php?page=beta-feedback&feedback_id=' . $feedback_id )
		);

		return wp_mail( $admin_email, $subject, $message );
	}

	/**
	 * Send response email
	 *
	 * @param array $feedback Feedback data
	 * @return bool Success
	 */
	private function send_response_email( $feedback ) {
		$app_manager = NonprofitSuite_Beta_Application_Manager::get_instance();
		$application = $app_manager->get_application( $feedback['application_id'] );

		if ( ! $application ) {
			return false;
		}

		$template_manager = NonprofitSuite_Beta_Email_Templates::get_instance();
		$email_data = $template_manager->get_template( 'feedback_response', array_merge( $application, $feedback ) );

		return wp_mail(
			$application['contact_email'],
			$email_data['subject'],
			$email_data['body'],
			array( 'Content-Type: text/html; charset=UTF-8' )
		);
	}

	/**
	 * Add feedback button to admin bar
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar object
	 */
	public function add_feedback_button( $wp_admin_bar ) {
		// Only show for beta testers
		if ( ! $this->is_beta_tester() ) {
			return;
		}

		$wp_admin_bar->add_node( array(
			'id'    => 'beta-feedback',
			'title' => '<span class="ab-icon dashicons dashicons-megaphone"></span> ' . __( 'Beta Feedback', 'nonprofitsuite' ),
			'href'  => '#',
			'meta'  => array(
				'class' => 'beta-feedback-button',
			),
		) );
	}

	/**
	 * Check if current user is a beta tester
	 *
	 * @return bool True if beta tester
	 */
	private function is_beta_tester() {
		// Check if site has an active beta license
		// This would integrate with your license check system
		return defined( 'NS_BETA_LICENSE' ) && NS_BETA_LICENSE;
	}

	/**
	 * Enqueue scripts
	 */
	public function enqueue_scripts() {
		if ( ! $this->is_beta_tester() ) {
			return;
		}

		// Enqueue feedback widget CSS
		wp_enqueue_style(
			'ns-beta-feedback-widget',
			plugins_url( 'assets/css/beta-feedback-widget.css', dirname( dirname( __FILE__ ) ) ),
			array(),
			'1.0.0'
		);

		// Enqueue feedback widget JS
		wp_enqueue_script(
			'ns-beta-feedback-widget',
			plugins_url( 'assets/js/beta-feedback-widget.js', dirname( dirname( __FILE__ ) ) ),
			array( 'jquery' ),
			'1.0.0',
			true
		);

		// Localize script
		wp_localize_script(
			'ns-beta-feedback-widget',
			'nsBetaWidget',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'beta_feedback' ),
			)
		);
	}

	/**
	 * AJAX handler for feedback submission
	 */
	public function ajax_submit_feedback() {
		check_ajax_referer( 'beta_feedback', 'nonce' );

		// Get application ID from license
		$application_id = $this->get_current_application_id();

		if ( ! $application_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid beta license', 'nonprofitsuite' ) ) );
		}

		$feedback_data = array(
			'feedback_type'  => sanitize_text_field( $_POST['feedback_type'] ?? 'improvement' ),
			'category'       => sanitize_text_field( $_POST['category'] ?? '' ),
			'subject'        => sanitize_text_field( $_POST['subject'] ?? '' ),
			'message'        => sanitize_textarea_field( $_POST['message'] ?? '' ),
			'screenshot_url' => esc_url_raw( $_POST['screenshot_url'] ?? '' ),
		);

		$result = $this->submit_feedback( $application_id, $feedback_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Get current application ID from license
	 *
	 * @return int|null Application ID or null
	 */
	private function get_current_application_id() {
		// Would retrieve from license/Freemius
		return get_option( 'ns_beta_application_id' );
	}
}

// Initialize
NonprofitSuite_Beta_Feedback_Manager::get_instance();
