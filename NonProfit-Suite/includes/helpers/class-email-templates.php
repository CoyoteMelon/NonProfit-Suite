<?php
/**
 * Email Template System
 *
 * Manages email templates with variable substitution.
 *
 * @package    NonprofitSuite
 * @subpackage Helpers
 * @since      1.6.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonprofitSuite_Email_Templates Class
 *
 * Email template management and rendering.
 */
class NonprofitSuite_Email_Templates {

	/**
	 * Get a template by slug.
	 *
	 * @param string $template_slug Template slug.
	 * @return array|null Template data or null.
	 */
	public static function get_template( $template_slug ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_email_templates';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE template_slug = %s AND is_active = 1",
				$template_slug
			),
			ARRAY_A
		);
	}

	/**
	 * Render template with variables.
	 *
	 * @param string $template_slug Template slug.
	 * @param array  $variables     Variables for substitution.
	 * @return array|WP_Error {
	 *     Rendered template data.
	 *
	 *     @type string $subject   Rendered subject.
	 *     @type string $body_html Rendered HTML body.
	 *     @type string $body_text Rendered text body.
	 *     @type string $from_name From name.
	 *     @type string $from_email From email.
	 *     @type string $reply_to  Reply-to address.
	 * }
	 */
	public static function render( $template_slug, $variables = array() ) {
		$template = self::get_template( $template_slug );

		if ( ! $template ) {
			return new WP_Error( 'template_not_found', __( 'Email template not found', 'nonprofitsuite' ) );
		}

		// Add default variables
		$variables = array_merge( self::get_default_variables(), $variables );

		return array(
			'subject'    => self::replace_variables( $template['subject_template'], $variables ),
			'body_html'  => self::replace_variables( $template['body_html_template'], $variables ),
			'body_text'  => self::replace_variables( $template['body_text_template'], $variables ),
			'from_name'  => $template['from_name'] ?: get_bloginfo( 'name' ),
			'from_email' => $template['from_email'] ?: get_option( 'admin_email' ),
			'reply_to'   => $template['reply_to'],
		);
	}

	/**
	 * Replace variables in template string.
	 *
	 * @param string $template  Template string with {variables}.
	 * @param array  $variables Variable values.
	 * @return string Rendered string.
	 */
	private static function replace_variables( $template, $variables ) {
		if ( empty( $template ) ) {
			return '';
		}

		foreach ( $variables as $key => $value ) {
			$template = str_replace( '{' . $key . '}', $value, $template );
		}

		return $template;
	}

	/**
	 * Get default template variables.
	 *
	 * @return array Default variables.
	 */
	private static function get_default_variables() {
		return array(
			'org_name'       => get_bloginfo( 'name' ),
			'org_url'        => home_url(),
			'current_year'   => gmdate( 'Y' ),
			'current_date'   => wp_date( 'F j, Y' ),
			'unsubscribe_url' => home_url( '/unsubscribe' ), // Would link to actual unsubscribe page
		);
	}

	/**
	 * Create or update a template.
	 *
	 * @param array $template_data Template data.
	 * @return int|WP_Error Template ID or error.
	 */
	public static function save_template( $template_data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_email_templates';

		$data = array(
			'template_name'      => $template_data['template_name'],
			'template_slug'      => isset( $template_data['template_slug'] ) ? $template_data['template_slug'] : sanitize_title( $template_data['template_name'] ),
			'description'        => isset( $template_data['description'] ) ? $template_data['description'] : '',
			'subject_template'   => $template_data['subject_template'],
			'body_text_template' => isset( $template_data['body_text_template'] ) ? $template_data['body_text_template'] : '',
			'body_html_template' => isset( $template_data['body_html_template'] ) ? $template_data['body_html_template'] : '',
			'template_category'  => isset( $template_data['template_category'] ) ? $template_data['template_category'] : 'custom',
			'from_name'          => isset( $template_data['from_name'] ) ? $template_data['from_name'] : '',
			'from_email'         => isset( $template_data['from_email'] ) ? $template_data['from_email'] : '',
			'reply_to'           => isset( $template_data['reply_to'] ) ? $template_data['reply_to'] : '',
			'is_system'          => isset( $template_data['is_system'] ) ? $template_data['is_system'] : 0,
			'is_active'          => isset( $template_data['is_active'] ) ? $template_data['is_active'] : 1,
			'created_by'         => get_current_user_id(),
		);

		// Check if template exists
		if ( isset( $template_data['id'] ) ) {
			$result = $wpdb->update( $table, $data, array( 'id' => $template_data['id'] ) );
			return $result !== false ? $template_data['id'] : new WP_Error( 'update_failed', 'Failed to update template' );
		}

		$result = $wpdb->insert( $table, $data );

		return $result ? $wpdb->insert_id : new WP_Error( 'create_failed', 'Failed to create template' );
	}

	/**
	 * Initialize default system templates.
	 */
	public static function initialize_system_templates() {
		$templates = array(
			array(
				'template_name'      => 'Welcome New Member',
				'template_slug'      => 'welcome_member',
				'description'        => 'Sent to new members when they join',
				'subject_template'   => 'Welcome to {org_name}, {first_name}!',
				'body_html_template' => '<h1>Welcome {first_name}!</h1><p>We\'re excited to have you as part of {org_name}.</p><p>You can access your member portal at: {member_portal_url}</p><p>If you have questions, reply to this email.</p><p>Best regards,<br>{org_name}</p>',
				'body_text_template' => 'Welcome {first_name}!\n\nWe\'re excited to have you as part of {org_name}.\n\nYou can access your member portal at: {member_portal_url}\n\nIf you have questions, reply to this email.\n\nBest regards,\n{org_name}',
				'template_category'  => 'member',
				'is_system'          => 1,
			),
			array(
				'template_name'      => 'Donation Thank You',
				'template_slug'      => 'donation_thanks',
				'description'        => 'Thank you email for donations',
				'subject_template'   => 'Thank you for your donation, {donor_name}!',
				'body_html_template' => '<h1>Thank You!</h1><p>Dear {donor_name},</p><p>Thank you for your generous donation of ${donation_amount} to {org_name}.</p><p>Your support makes our mission possible.</p><p>Donation Details:<br>Amount: ${donation_amount}<br>Date: {donation_date}<br>Tax ID: {org_tax_id}</p><p>With gratitude,<br>{org_name}</p>',
				'body_text_template' => 'Dear {donor_name},\n\nThank you for your generous donation of ${donation_amount} to {org_name}.\n\nYour support makes our mission possible.\n\nDonation Details:\nAmount: ${donation_amount}\nDate: {donation_date}\nTax ID: {org_tax_id}\n\nWith gratitude,\n{org_name}',
				'template_category'  => 'donor',
				'is_system'          => 1,
			),
			array(
				'template_name'      => 'Task Assignment',
				'template_slug'      => 'task_assigned',
				'description'        => 'Notification when a task is assigned',
				'subject_template'   => 'New Task Assigned: {task_title}',
				'body_html_template' => '<h1>New Task Assigned</h1><p>Hi {assignee_name},</p><p>You have been assigned a new task:</p><p><strong>{task_title}</strong></p><p>{task_description}</p><p>Due Date: {task_due_date}</p><p><a href="{task_url}">View Task</a></p>',
				'body_text_template' => 'Hi {assignee_name},\n\nYou have been assigned a new task:\n\n{task_title}\n\n{task_description}\n\nDue Date: {task_due_date}\n\nView Task: {task_url}',
				'template_category'  => 'system',
				'is_system'          => 1,
			),
			array(
				'template_name'      => 'Volunteer Shift Confirmation',
				'template_slug'      => 'volunteer_shift_confirmed',
				'description'        => 'Confirmation for volunteer shift signup',
				'subject_template'   => 'Shift Confirmed: {shift_name}',
				'body_html_template' => '<h1>Shift Confirmed!</h1><p>Hi {volunteer_name},</p><p>Your volunteer shift has been confirmed:</p><p><strong>{shift_name}</strong><br>Date: {shift_date}<br>Time: {shift_time}<br>Location: {shift_location}</p><p>Thank you for volunteering!</p>',
				'body_text_template' => 'Hi {volunteer_name},\n\nYour volunteer shift has been confirmed:\n\n{shift_name}\nDate: {shift_date}\nTime: {shift_time}\nLocation: {shift_location}\n\nThank you for volunteering!',
				'template_category'  => 'volunteer',
				'is_system'          => 1,
			),
		);

		foreach ( $templates as $template ) {
			// Check if template already exists
			$existing = self::get_template( $template['template_slug'] );
			if ( ! $existing ) {
				self::save_template( $template );
			}
		}
	}

	/**
	 * Send email using a template.
	 *
	 * @param string $template_slug Template slug.
	 * @param string $to            Recipient email.
	 * @param array  $variables     Template variables.
	 * @param string $provider      Optional. Email provider to use.
	 * @return array|WP_Error Send result.
	 */
	public static function send( $template_slug, $to, $variables = array(), $provider = null ) {
		$rendered = self::render( $template_slug, $variables );

		if ( is_wp_error( $rendered ) ) {
			return $rendered;
		}

		$email_data = array_merge( $rendered, array(
			'to' => $to,
		) );

		return NonprofitSuite_Email_Manager::send( $email_data, $provider );
	}
}
