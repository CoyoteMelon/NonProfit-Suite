<?php
/**
 * Email Template Parser
 *
 * Handles variable substitution and template parsing for emails.
 *
 * @package    NonprofitSuite
 * @subpackage Helpers
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonprofitSuite_Email_Template_Parser Class
 *
 * Parses email templates and replaces variables with actual data.
 */
class NonprofitSuite_Email_Template_Parser {

	/**
	 * Parse template with variables
	 *
	 * @param string $template Template string with variables like {first_name}
	 * @param array  $data     Data for variable substitution
	 * @return string Parsed template
	 */
	public static function parse( $template, $data = array() ) {
		// Merge with default variables
		$data = wp_parse_args( $data, self::get_default_variables() );

		// Replace variables in format {variable_name}
		$template = preg_replace_callback(
			'/{([a-z0-9_]+)}/i',
			function( $matches ) use ( $data ) {
				$var_name = $matches[1];

				// Check if variable exists in data
				if ( isset( $data[ $var_name ] ) ) {
					return $data[ $var_name ];
				}

				// Try nested access with dot notation
				if ( strpos( $var_name, '.' ) !== false ) {
					$value = self::get_nested_value( $data, $var_name );
					if ( $value !== null ) {
						return $value;
					}
				}

				// Return original if not found
				return $matches[0];
			},
			$template
		);

		return $template;
	}

	/**
	 * Get default variables available in all templates
	 *
	 * @return array Default variables
	 */
	public static function get_default_variables() {
		$defaults = array(
			// Site/Organization
			'site_name'         => get_bloginfo( 'name' ),
			'site_url'          => get_bloginfo( 'url' ),
			'site_description'  => get_bloginfo( 'description' ),
			'admin_email'       => get_option( 'admin_email' ),
			'org_name'          => get_option( 'blogname' ),

			// Dates
			'current_date'      => date_i18n( get_option( 'date_format' ) ),
			'current_time'      => date_i18n( get_option( 'time_format' ) ),
			'current_year'      => date( 'Y' ),

			// User (if logged in)
			'user_email'        => is_user_logged_in() ? wp_get_current_user()->user_email : '',
			'user_display_name' => is_user_logged_in() ? wp_get_current_user()->display_name : '',
		);

		/**
		 * Filter default template variables
		 *
		 * @param array $defaults Default variables
		 */
		return apply_filters( 'ns_email_template_default_variables', $defaults );
	}

	/**
	 * Get nested value using dot notation
	 *
	 * @param array  $data Array of data
	 * @param string $path Dot-notated path (e.g., 'contact.first_name')
	 * @return mixed|null Value or null if not found
	 */
	private static function get_nested_value( $data, $path ) {
		$keys = explode( '.', $path );
		$value = $data;

		foreach ( $keys as $key ) {
			if ( is_array( $value ) && isset( $value[ $key ] ) ) {
				$value = $value[ $key ];
			} elseif ( is_object( $value ) && isset( $value->$key ) ) {
				$value = $value->$key;
			} else {
				return null;
			}
		}

		return $value;
	}

	/**
	 * Get contact-specific variables
	 *
	 * @param int $contact_id Contact ID
	 * @return array Contact variables
	 */
	public static function get_contact_variables( $contact_id ) {
		global $wpdb;

		$contact = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ns_contacts WHERE id = %d",
				$contact_id
			)
		);

		if ( ! $contact ) {
			return array();
		}

		return array(
			'contact_id'         => $contact->id,
			'first_name'         => $contact->first_name ?? '',
			'last_name'          => $contact->last_name ?? '',
			'full_name'          => trim( ( $contact->first_name ?? '' ) . ' ' . ( $contact->last_name ?? '' ) ),
			'email'              => $contact->email ?? '',
			'phone'              => $contact->phone ?? '',
			'organization'       => $contact->organization ?? '',
			'address'            => $contact->address ?? '',
			'city'               => $contact->city ?? '',
			'state'              => $contact->state ?? '',
			'zip'                => $contact->zip ?? '',
			'country'            => $contact->country ?? '',
		);
	}

	/**
	 * Get donation-specific variables
	 *
	 * @param int $donation_id Donation ID
	 * @return array Donation variables
	 */
	public static function get_donation_variables( $donation_id ) {
		global $wpdb;

		$donation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ns_donations WHERE id = %d",
				$donation_id
			)
		);

		if ( ! $donation ) {
			return array();
		}

		return array(
			'donation_id'       => $donation->id,
			'donation_amount'   => '$' . number_format( $donation->amount, 2 ),
			'donation_amount_raw' => $donation->amount,
			'donation_date'     => date_i18n( get_option( 'date_format' ), strtotime( $donation->donation_date ) ),
			'donation_method'   => $donation->payment_method ?? '',
			'donation_status'   => $donation->status ?? '',
			'is_recurring'      => ! empty( $donation->is_recurring ) ? 'Yes' : 'No',
			'donation_type'     => $donation->donation_type ?? '',
		);
	}

	/**
	 * Get meeting-specific variables
	 *
	 * @param int $meeting_id Meeting ID
	 * @return array Meeting variables
	 */
	public static function get_meeting_variables( $meeting_id ) {
		global $wpdb;

		$meeting = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ns_meetings WHERE id = %d",
				$meeting_id
			)
		);

		if ( ! $meeting ) {
			return array();
		}

		return array(
			'meeting_id'        => $meeting->id,
			'meeting_title'     => $meeting->title ?? '',
			'meeting_date'      => date_i18n( get_option( 'date_format' ), strtotime( $meeting->meeting_date ) ),
			'meeting_time'      => date_i18n( get_option( 'time_format' ), strtotime( $meeting->meeting_date ) ),
			'meeting_location'  => $meeting->location ?? '',
			'meeting_type'      => $meeting->meeting_type ?? '',
			'meeting_status'    => $meeting->status ?? '',
			'video_url'         => $meeting->video_url ?? '',
		);
	}

	/**
	 * Get task-specific variables
	 *
	 * @param int $task_id Task ID
	 * @return array Task variables
	 */
	public static function get_task_variables( $task_id ) {
		global $wpdb;

		$task = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ns_tasks WHERE id = %d",
				$task_id
			)
		);

		if ( ! $task ) {
			return array();
		}

		return array(
			'task_id'           => $task->id,
			'task_title'        => $task->title ?? '',
			'task_description'  => $task->description ?? '',
			'task_due_date'     => $task->due_date ? date_i18n( get_option( 'date_format' ), strtotime( $task->due_date ) ) : '',
			'task_priority'     => $task->priority ?? '',
			'task_status'       => $task->status ?? '',
			'task_type'         => $task->task_type ?? '',
		);
	}

	/**
	 * Get event-specific variables
	 *
	 * @param int $event_id Event ID
	 * @return array Event variables
	 */
	public static function get_event_variables( $event_id ) {
		global $wpdb;

		$event = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ns_events WHERE id = %d",
				$event_id
			)
		);

		if ( ! $event ) {
			return array();
		}

		return array(
			'event_id'          => $event->id,
			'event_name'        => $event->name ?? '',
			'event_description' => $event->description ?? '',
			'event_date'        => date_i18n( get_option( 'date_format' ), strtotime( $event->event_date ) ),
			'event_time'        => date_i18n( get_option( 'time_format' ), strtotime( $event->event_date ) ),
			'event_location'    => $event->location ?? '',
			'event_capacity'    => $event->capacity ?? '',
			'event_price'       => ! empty( $event->price ) ? '$' . number_format( $event->price, 2 ) : 'Free',
			'event_url'         => get_permalink( $event->id ),
		);
	}

	/**
	 * Get all available variable documentation
	 *
	 * @return array Variable documentation
	 */
	public static function get_variable_documentation() {
		return array(
			'General' => array(
				'{site_name}'         => 'Organization/site name',
				'{site_url}'          => 'Website URL',
				'{site_description}'  => 'Site tagline',
				'{admin_email}'       => 'Admin email address',
				'{org_name}'          => 'Organization name',
				'{current_date}'      => 'Current date',
				'{current_time}'      => 'Current time',
				'{current_year}'      => 'Current year',
			),
			'Contact' => array(
				'{first_name}'        => 'Contact first name',
				'{last_name}'         => 'Contact last name',
				'{full_name}'         => 'Contact full name',
				'{email}'             => 'Contact email',
				'{phone}'             => 'Contact phone',
				'{organization}'      => 'Contact organization',
				'{address}'           => 'Contact address',
				'{city}'              => 'Contact city',
				'{state}'             => 'Contact state',
				'{zip}'               => 'Contact ZIP code',
			),
			'Donation' => array(
				'{donation_id}'       => 'Donation ID',
				'{donation_amount}'   => 'Donation amount (formatted)',
				'{donation_date}'     => 'Donation date',
				'{donation_method}'   => 'Payment method',
				'{donation_status}'   => 'Donation status',
				'{is_recurring}'      => 'Recurring donation (Yes/No)',
			),
			'Meeting' => array(
				'{meeting_title}'     => 'Meeting title',
				'{meeting_date}'      => 'Meeting date',
				'{meeting_time}'      => 'Meeting time',
				'{meeting_location}'  => 'Meeting location',
				'{video_url}'         => 'Video conference link',
			),
			'Event' => array(
				'{event_name}'        => 'Event name',
				'{event_date}'        => 'Event date',
				'{event_time}'        => 'Event time',
				'{event_location}'    => 'Event location',
				'{event_price}'       => 'Event price',
				'{event_url}'         => 'Event registration URL',
			),
			'Task' => array(
				'{task_title}'        => 'Task title',
				'{task_description}'  => 'Task description',
				'{task_due_date}'     => 'Task due date',
				'{task_priority}'     => 'Task priority',
				'{task_status}'       => 'Task status',
			),
		);
	}

	/**
	 * Get default email templates
	 *
	 * @return array Default templates
	 */
	public static function get_default_templates() {
		return array(
			'donation_thank_you' => array(
				'name'    => 'Donation Thank You',
				'subject' => 'Thank you for your donation!',
				'html'    => '<p>Dear {first_name},</p>
<p>Thank you for your generous donation of {donation_amount} to {org_name}!</p>
<p>Your support makes a real difference in our mission. We are grateful for your contribution.</p>
<p>Donation Details:</p>
<ul>
<li>Amount: {donation_amount}</li>
<li>Date: {donation_date}</li>
<li>Method: {donation_method}</li>
</ul>
<p>This email serves as your tax receipt. Please keep it for your records.</p>
<p>With gratitude,<br>{org_name}</p>',
				'text'    => 'Dear {first_name},

Thank you for your generous donation of {donation_amount} to {org_name}!

Your support makes a real difference in our mission. We are grateful for your contribution.

Donation Details:
- Amount: {donation_amount}
- Date: {donation_date}
- Method: {donation_method}

This email serves as your tax receipt. Please keep it for your records.

With gratitude,
{org_name}',
			),
			'meeting_invitation' => array(
				'name'    => 'Meeting Invitation',
				'subject' => 'You are invited: {meeting_title}',
				'html'    => '<p>Hello {first_name},</p>
<p>You are invited to attend:</p>
<h3>{meeting_title}</h3>
<p><strong>Date:</strong> {meeting_date}<br>
<strong>Time:</strong> {meeting_time}<br>
<strong>Location:</strong> {meeting_location}</p>
<p>Join video conference: <a href="{video_url}">{video_url}</a></p>
<p>We look forward to seeing you there!</p>
<p>Best regards,<br>{org_name}</p>',
				'text'    => 'Hello {first_name},

You are invited to attend:

{meeting_title}

Date: {meeting_date}
Time: {meeting_time}
Location: {meeting_location}

Join video conference: {video_url}

We look forward to seeing you there!

Best regards,
{org_name}',
			),
			'task_assignment' => array(
				'name'    => 'Task Assignment',
				'subject' => 'New task assigned: {task_title}',
				'html'    => '<p>Hello {first_name},</p>
<p>A new task has been assigned to you:</p>
<h3>{task_title}</h3>
<p>{task_description}</p>
<p><strong>Due Date:</strong> {task_due_date}<br>
<strong>Priority:</strong> {task_priority}<br>
<strong>Status:</strong> {task_status}</p>
<p>Please log in to view full details and update progress.</p>
<p>Thank you,<br>{org_name}</p>',
				'text'    => 'Hello {first_name},

A new task has been assigned to you:

{task_title}

{task_description}

Due Date: {task_due_date}
Priority: {task_priority}
Status: {task_status}

Please log in to view full details and update progress.

Thank you,
{org_name}',
			),
			'volunteer_shift_confirmation' => array(
				'name'    => 'Volunteer Shift Confirmation',
				'subject' => 'Shift confirmed: {event_name}',
				'html'    => '<p>Hello {first_name},</p>
<p>Thank you for signing up to volunteer!</p>
<p><strong>Event:</strong> {event_name}<br>
<strong>Date:</strong> {event_date}<br>
<strong>Time:</strong> {event_time}<br>
<strong>Location:</strong> {event_location}</p>
<p>We appreciate your commitment to helping our community!</p>
<p>If you need to cancel or have questions, please contact us.</p>
<p>See you there!<br>{org_name}</p>',
				'text'    => 'Hello {first_name},

Thank you for signing up to volunteer!

Event: {event_name}
Date: {event_date}
Time: {event_time}
Location: {event_location}

We appreciate your commitment to helping our community!

If you need to cancel or have questions, please contact us.

See you there!
{org_name}',
			),
			'welcome_new_member' => array(
				'name'    => 'Welcome New Member',
				'subject' => 'Welcome to {org_name}!',
				'html'    => '<p>Dear {first_name},</p>
<p>Welcome to {org_name}! We are thrilled to have you as part of our community.</p>
<p>Here are some ways to get started:</p>
<ul>
<li>Visit our website: <a href="{site_url}">{site_url}</a></li>
<li>Attend our upcoming events</li>
<li>Connect with other members</li>
<li>Volunteer your time and talents</li>
</ul>
<p>If you have any questions, please don\'t hesitate to reach out to us at {admin_email}.</p>
<p>Welcome aboard!<br>{org_name} Team</p>',
				'text'    => 'Dear {first_name},

Welcome to {org_name}! We are thrilled to have you as part of our community.

Here are some ways to get started:
- Visit our website: {site_url}
- Attend our upcoming events
- Connect with other members
- Volunteer your time and talents

If you have any questions, please don\'t hesitate to reach out to us at {admin_email}.

Welcome aboard!
{org_name} Team',
			),
		);
	}
}
