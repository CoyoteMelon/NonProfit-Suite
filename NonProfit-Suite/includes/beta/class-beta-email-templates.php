<?php
/**
 * Beta Email Templates
 *
 * Email templates for beta program communications.
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
 * NonprofitSuite_Beta_Email_Templates Class
 *
 * Manages email templates for beta program.
 */
class NonprofitSuite_Beta_Email_Templates {

	/**
	 * Singleton instance
	 *
	 * @var NonprofitSuite_Beta_Email_Templates
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return NonprofitSuite_Beta_Email_Templates
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
		// Constructor
	}

	/**
	 * Get email template
	 *
	 * @param string $template_key Template key
	 * @param array  $data         Template data
	 * @return array Email data (subject, body)
	 */
	public function get_template( $template_key, $data = array() ) {
		$templates = array(
			'beta_received'      => array( $this, 'template_application_received' ),
			'beta_approved'      => array( $this, 'template_application_approved' ),
			'survey_invitation'  => array( $this, 'template_survey_invitation' ),
			'feedback_response'  => array( $this, 'template_feedback_response' ),
		);

		if ( ! isset( $templates[ $template_key ] ) ) {
			return array(
				'subject' => 'NonprofitSuite Beta Program',
				'body'    => 'Thank you for participating in our beta program.',
			);
		}

		return call_user_func( $templates[ $template_key ], $data );
	}

	/**
	 * Application received template
	 *
	 * @param array $data Application data
	 * @return array Email data
	 */
	private function template_application_received( $data ) {
		$subject = 'Your NonprofitSuite Beta Application Has Been Received';

		$body = sprintf(
			'<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
			<h2 style="color: #2271b1;">Beta Application Received!</h2>
			<p>Dear %s,</p>
			<p>Thank you for applying to the NonprofitSuite Beta Testing Program!</p>
			<p><strong>Organization:</strong> %s</p>
			<p><strong>Status:</strong> Waitlist</p>
			<p>Your application has been placed on the waitlist. We will notify you as soon as a slot becomes available.</p>
			<h3 style="color: #2271b1;">What Happens Next?</h3>
			<ul>
				<li>We review applications in the order received</li>
				<li>You\'ll receive an email if a slot opens up</li>
				<li>Priority is given to registered 501(c)(3) nonprofits</li>
			</ul>
			%s
			<p>Thank you for your interest in NonprofitSuite!</p>
			<p>Best regards,<br>
			The NonprofitSuite Team</p>
			</body></html>',
			esc_html( $data['contact_name'] ),
			esc_html( $data['organization_name'] ),
			$data['slot_type'] === 'pre_nonprofit' ?
				'<p><strong>Note for Pre-Nonprofits:</strong> We encourage you to complete the "Forming a Nonprofit" module as you build your organization. This will help you establish a strong foundation!</p>' :
				''
		);

		return array(
			'subject' => $subject,
			'body'    => $body,
		);
	}

	/**
	 * Application approved template
	 *
	 * @param array $data Application data
	 * @return array Email data
	 */
	private function template_application_approved( $data ) {
		$subject = '🎉 Welcome to the NonprofitSuite Beta Program!';

		$body = sprintf(
			'<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
			<h2 style="color: #2271b1;">Congratulations! You\'re In!</h2>
			<p>Dear %s,</p>
			<p>We\'re excited to welcome <strong>%s</strong> to the NonprofitSuite Beta Testing Program!</p>

			<div style="background-color: #f0f6fc; padding: 20px; border-left: 4px solid #2271b1; margin: 20px 0;">
				<h3 style="margin-top: 0;">Your Beta License Key:</h3>
				<p style="font-size: 18px; font-family: monospace; background: white; padding: 10px; border: 1px solid #ddd;">
					<strong>%s</strong>
				</p>
			</div>

			<h3 style="color: #2271b1;">Getting Started:</h3>
			<ol>
				<li><strong>Install NonprofitSuite:</strong> Download from your account dashboard</li>
				<li><strong>Activate Your License:</strong> Use the license key above during setup</li>
				<li><strong>Complete Initial Setup:</strong> Follow the setup wizard</li>
				<li><strong>Start Exploring:</strong> All features are unlocked for you!</li>
			</ol>

			%s

			<h3 style="color: #2271b1;">As a Beta Tester, You Get:</h3>
			<ul>
				<li>✅ Free lifetime license (no recurring costs, ever!)</li>
				<li>✅ All features unlocked</li>
				<li>✅ Direct feedback channel to our development team</li>
				<li>✅ Early access to new features</li>
				<li>✅ Beta tester badge and recognition</li>
			</ul>

			<h3 style="color: #2271b1;">We Need Your Help:</h3>
			<p>Throughout your beta testing journey, we\'ll periodically ask for your feedback through:</p>
			<ul>
				<li><strong>Scheduled Surveys:</strong> At days 7, 14, 30, 60, 90, 120, 150, 180, 270, and 365</li>
				<li><strong>Quick Feedback:</strong> Use the "Beta Feedback" button in your WordPress admin bar anytime</li>
			</ul>

			<h3 style="color: #2271b1;">Important Information:</h3>
			<p><strong>License Type:</strong> %s</p>
			<p><strong>Support:</strong> Email us at support@nonprofitsuite.com</p>
			<p><strong>Documentation:</strong> Visit docs.nonprofitsuite.com</p>

			<p style="margin-top: 30px;">We\'re thrilled to have you as part of our beta community. Your feedback will directly shape the future of NonprofitSuite!</p>

			<p>Welcome aboard! 🚀</p>

			<p>Best regards,<br>
			The NonprofitSuite Team</p>

			<hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
			<p style="font-size: 12px; color: #666;">
				Need help? Reply to this email or visit our support page.<br>
				This is an automated message. Your license key is precious - keep it safe!
			</p>
			</body></html>',
			esc_html( $data['contact_name'] ),
			esc_html( $data['organization_name'] ),
			esc_html( $data['license_key'] ),
			$data['slot_type'] === 'pre_nonprofit' ?
				'<div style="background-color: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;">
					<h4 style="margin-top: 0; color: #856404;">📚 Recommended for Pre-Nonprofits:</h4>
					<p>We strongly encourage you to complete the "Forming a Nonprofit" module. While not required for your beta license, this comprehensive guide will help you successfully establish your nonprofit organization over the next 6-12 months.</p>
				</div>' :
				'',
			$data['slot_type'] === '501c3' ? 'Registered 501(c)(3) Nonprofit' : 'Pre-Nonprofit (Forming Stage)'
		);

		return array(
			'subject' => $subject,
			'body'    => $body,
		);
	}

	/**
	 * Survey invitation template
	 *
	 * @param array $data Application data
	 * @return array Email data
	 */
	private function template_survey_invitation( $data ) {
		$survey_number = ( (int) $data['survey_count'] ) + 1;
		$days_active = $this->get_survey_day( $survey_number );

		$subject = sprintf( 'NonprofitSuite Beta Survey #%d - We Value Your Feedback!', $survey_number );

		$survey_url = add_query_arg(
			array(
				'beta_survey'    => 1,
				'application_id' => $data['id'],
				'token'          => wp_create_nonce( 'beta_survey_' . $data['id'] ),
			),
			home_url( '/beta-survey/' )
		);

		$body = sprintf(
			'<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
			<h2 style="color: #2271b1;">Time for Survey #%d!</h2>
			<p>Hi %s,</p>
			<p>You\'ve been using NonprofitSuite for about <strong>%d days</strong> now. We\'d love to hear how it\'s going!</p>

			<p>This survey takes about <strong>5-10 minutes</strong> and helps us make NonprofitSuite better for everyone.</p>

			<div style="text-align: center; margin: 30px 0;">
				<a href="%s" style="background-color: #2271b1; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 16px; display: inline-block;">
					Take Survey #%d
				</a>
			</div>

			<h3 style="color: #2271b1;">What We\'ll Ask:</h3>
			<ul>
				<li>Overall satisfaction and ease of use</li>
				<li>Ratings for features you\'ve used</li>
				<li>What\'s working well</li>
				<li>What needs improvement</li>
				<li>Any bugs you\'ve encountered</li>
				<li>Feature requests</li>
			</ul>

			<p>Your honest feedback directly impacts our development priorities. Thank you for being an essential part of our beta community!</p>

			<p>Best regards,<br>
			The NonprofitSuite Team</p>

			<hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
			<p style="font-size: 12px; color: #666;">
				Survey link expires in 14 days. Can\'t click the button? Copy this URL:<br>
				%s
			</p>
			</body></html>',
			$survey_number,
			esc_html( $data['contact_name'] ),
			$days_active,
			esc_url( $survey_url ),
			$survey_number,
			esc_url( $survey_url )
		);

		return array(
			'subject' => $subject,
			'body'    => $body,
		);
	}

	/**
	 * Feedback response template
	 *
	 * @param array $data Feedback and application data
	 * @return array Email data
	 */
	private function template_feedback_response( $data ) {
		$subject = sprintf( 'Re: Your Beta Feedback - %s', $data['subject'] ?: 'No Subject' );

		$body = sprintf(
			'<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
			<h2 style="color: #2271b1;">Thank You for Your Feedback!</h2>
			<p>Hi %s,</p>
			<p>Thank you for taking the time to submit feedback about <strong>%s</strong>.</p>

			<div style="background-color: #f0f6fc; padding: 20px; border-left: 4px solid #2271b1; margin: 20px 0;">
				<h3 style="margin-top: 0;">Your Original Message:</h3>
				<p><strong>Type:</strong> %s</p>
				<p><strong>Subject:</strong> %s</p>
				<p style="background: white; padding: 10px; border: 1px solid #ddd;">%s</p>
			</div>

			<div style="background-color: #f0fff4; padding: 20px; border-left: 4px solid #10b981; margin: 20px 0;">
				<h3 style="margin-top: 0; color: #10b981;">Our Response:</h3>
				<p>%s</p>
			</div>

			<p>We appreciate your participation in our beta program and your continued feedback!</p>

			<p>Best regards,<br>
			The NonprofitSuite Team</p>
			</body></html>',
			esc_html( $data['contact_name'] ),
			esc_html( $data['organization_name'] ),
			esc_html( ucfirst( str_replace( '_', ' ', $data['feedback_type'] ) ) ),
			esc_html( $data['subject'] ?: 'No Subject' ),
			nl2br( esc_html( $data['message'] ) ),
			nl2br( esc_html( $data['admin_response'] ) )
		);

		return array(
			'subject' => $subject,
			'body'    => $body,
		);
	}

	/**
	 * Get survey day from survey number
	 *
	 * @param int $survey_number Survey number (1-based)
	 * @return int Days since activation
	 */
	private function get_survey_day( $survey_number ) {
		$schedule = array( 7, 14, 30, 60, 90, 120, 150, 180, 270, 365 );
		$index = $survey_number - 1;
		return isset( $schedule[ $index ] ) ? $schedule[ $index ] : 365;
	}
}

// Initialize
NonprofitSuite_Beta_Email_Templates::get_instance();
