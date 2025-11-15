<?php
/**
 * Calendar Event Reminders
 *
 * Manages multiple reminders per calendar event with support for
 * email, push notifications, SMS, and in-app notifications.
 *
 * @package    NonprofitSuite
 * @subpackage Helpers
 * @since      1.5.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonprofitSuite_Calendar_Reminders Class
 *
 * Handles creation, scheduling, and sending of event reminders.
 */
class NonprofitSuite_Calendar_Reminders {

	/**
	 * Create a reminder for an event.
	 *
	 * @param int   $event_id       Event ID.
	 * @param array $reminder_data  Reminder data.
	 * @return int|WP_Error Reminder ID on success, WP_Error on failure.
	 */
	public static function create_reminder( $event_id, $reminder_data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_calendar_event_reminders';

		// Get event details for scheduling
		$event = self::get_event( $event_id );
		if ( ! $event ) {
			return new WP_Error( 'invalid_event', __( 'Event not found', 'nonprofitsuite' ) );
		}

		// Calculate scheduled send time
		$scheduled_for = self::calculate_reminder_time( $event, $reminder_data['reminder_offset'] );

		$data = array(
			'event_id'          => $event_id,
			'reminder_offset'   => (int) $reminder_data['reminder_offset'],
			'reminder_type'     => isset( $reminder_data['reminder_type'] ) ? $reminder_data['reminder_type'] : 'email',
			'reminder_method'   => isset( $reminder_data['reminder_method'] ) ? $reminder_data['reminder_method'] : 'notification',
			'recipient_user_id' => isset( $reminder_data['recipient_user_id'] ) ? $reminder_data['recipient_user_id'] : null,
			'recipient_email'   => isset( $reminder_data['recipient_email'] ) ? $reminder_data['recipient_email'] : null,
			'recipient_phone'   => isset( $reminder_data['recipient_phone'] ) ? $reminder_data['recipient_phone'] : null,
			'reminder_status'   => 'pending',
			'scheduled_for'     => $scheduled_for,
			'custom_message'    => isset( $reminder_data['custom_message'] ) ? $reminder_data['custom_message'] : null,
		);

		$result = $wpdb->insert( $table, $data );

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to create reminder', 'nonprofitsuite' ) );
		}

		/**
		 * Fires after a reminder is created.
		 *
		 * @param int   $reminder_id   Reminder ID.
		 * @param int   $event_id      Event ID.
		 * @param array $reminder_data Reminder data.
		 */
		do_action( 'ns_calendar_reminder_created', $wpdb->insert_id, $event_id, $data );

		return $wpdb->insert_id;
	}

	/**
	 * Create default reminders for an event.
	 *
	 * @param int   $event_id    Event ID.
	 * @param array $attendees   Array of user IDs or emails.
	 * @param array $offset_plan Optional reminder offsets (minutes). Defaults to [10080, 1440, 60] (1 week, 1 day, 1 hour).
	 * @return array Array of created reminder IDs.
	 */
	public static function create_default_reminders( $event_id, $attendees, $offset_plan = null ) {
		if ( null === $offset_plan ) {
			// Default: 1 week, 1 day, 1 hour before
			$offset_plan = array( 10080, 1440, 60 );
		}

		$reminder_ids = array();

		foreach ( $attendees as $attendee ) {
			foreach ( $offset_plan as $offset_minutes ) {
				$reminder_data = array(
					'reminder_offset' => $offset_minutes,
					'reminder_type'   => 'email',
				);

				// Determine if attendee is user ID or email
				if ( is_numeric( $attendee ) ) {
					$reminder_data['recipient_user_id'] = $attendee;
					$user = get_userdata( $attendee );
					if ( $user ) {
						$reminder_data['recipient_email'] = $user->user_email;
					}
				} elseif ( is_email( $attendee ) ) {
					$reminder_data['recipient_email'] = $attendee;
				}

				$reminder_id = self::create_reminder( $event_id, $reminder_data );
				if ( ! is_wp_error( $reminder_id ) ) {
					$reminder_ids[] = $reminder_id;
				}
			}
		}

		return $reminder_ids;
	}

	/**
	 * Update a reminder.
	 *
	 * @param int   $reminder_id   Reminder ID.
	 * @param array $reminder_data Data to update.
	 * @return bool True on success.
	 */
	public static function update_reminder( $reminder_id, $reminder_data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_calendar_event_reminders';

		$result = $wpdb->update(
			$table,
			$reminder_data,
			array( 'id' => $reminder_id ),
			null,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a reminder.
	 *
	 * @param int $reminder_id Reminder ID.
	 * @return bool True on success.
	 */
	public static function delete_reminder( $reminder_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_calendar_event_reminders';

		$result = $wpdb->delete(
			$table,
			array( 'id' => $reminder_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get reminders for an event.
	 *
	 * @param int    $event_id Event ID.
	 * @param string $status   Optional status filter.
	 * @return array Array of reminders.
	 */
	public static function get_event_reminders( $event_id, $status = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_calendar_event_reminders';

		$where = $wpdb->prepare( 'event_id = %d', $event_id );

		if ( $status ) {
			$where .= $wpdb->prepare( ' AND reminder_status = %s', $status );
		}

		$reminders = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE {$where} ORDER BY scheduled_for ASC",
			ARRAY_A
		);

		return $reminders;
	}

	/**
	 * Get due reminders that need to be sent.
	 *
	 * @return array Array of due reminders.
	 */
	public static function get_due_reminders() {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_calendar_event_reminders';

		$now = current_time( 'mysql' );

		$reminders = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table}
			WHERE reminder_status = 'pending'
			AND scheduled_for <= %s
			ORDER BY scheduled_for ASC
			LIMIT 100",
			$now
		), ARRAY_A );

		return $reminders;
	}

	/**
	 * Process and send due reminders.
	 *
	 * Called by cron job every 5 minutes.
	 *
	 * @return array Results with sent count and errors.
	 */
	public static function process_due_reminders() {
		$due_reminders = self::get_due_reminders();

		$results = array(
			'sent_count'   => 0,
			'failed_count' => 0,
			'errors'       => array(),
		);

		foreach ( $due_reminders as $reminder ) {
			$result = self::send_reminder( $reminder );

			if ( is_wp_error( $result ) ) {
				$results['failed_count']++;
				$results['errors'][] = array(
					'reminder_id' => $reminder['id'],
					'error'       => $result->get_error_message(),
				);

				// Update reminder status to failed
				self::mark_reminder_failed( $reminder['id'], $result->get_error_message() );
			} else {
				$results['sent_count']++;
				// Mark as sent
				self::mark_reminder_sent( $reminder['id'] );
			}
		}

		return $results;
	}

	/**
	 * Send a single reminder.
	 *
	 * @param array $reminder Reminder data.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function send_reminder( $reminder ) {
		// Get event details
		$event = self::get_event( $reminder['event_id'] );
		if ( ! $event ) {
			return new WP_Error( 'event_not_found', __( 'Event not found', 'nonprofitsuite' ) );
		}

		// Determine recipient
		$recipient_email = $reminder['recipient_email'];
		$recipient_name = '';

		if ( $reminder['recipient_user_id'] ) {
			$user = get_userdata( $reminder['recipient_user_id'] );
			if ( $user ) {
				$recipient_name = $user->display_name;
				if ( ! $recipient_email ) {
					$recipient_email = $user->user_email;
				}
			}
		}

		if ( ! $recipient_email ) {
			return new WP_Error( 'no_recipient', __( 'No recipient email found', 'nonprofitsuite' ) );
		}

		// Send based on reminder type
		switch ( $reminder['reminder_type'] ) {
			case 'email':
				$result = self::send_email_reminder( $event, $reminder, $recipient_email, $recipient_name );
				break;

			case 'sms':
				$result = self::send_sms_reminder( $event, $reminder );
				break;

			case 'push':
				$result = self::send_push_reminder( $event, $reminder );
				break;

			case 'in_app':
				$result = self::send_in_app_reminder( $event, $reminder );
				break;

			default:
				$result = new WP_Error( 'invalid_type', __( 'Invalid reminder type', 'nonprofitsuite' ) );
		}

		/**
		 * Fires after a reminder is sent.
		 *
		 * @param int   $reminder_id Reminder ID.
		 * @param array $event       Event data.
		 * @param array $reminder    Reminder data.
		 * @param mixed $result      Send result.
		 */
		do_action( 'ns_calendar_reminder_sent', $reminder['id'], $event, $reminder, $result );

		return $result;
	}

	/**
	 * Send email reminder.
	 *
	 * @param array  $event          Event data.
	 * @param array  $reminder       Reminder data.
	 * @param string $recipient_email Recipient email.
	 * @param string $recipient_name  Recipient name.
	 * @return bool|WP_Error True on success.
	 */
	private static function send_email_reminder( $event, $reminder, $recipient_email, $recipient_name ) {
		$subject = sprintf(
			/* translators: %s: event title */
			__( 'Reminder: %s', 'nonprofitsuite' ),
			$event['title']
		);

		// Format time until event
		$time_until = self::format_time_until( $reminder['reminder_offset'] );

		// Build email message
		$message = ! empty( $reminder['custom_message'] ) ? $reminder['custom_message'] : '';

		$message .= "\n\n";
		$message .= sprintf(
			/* translators: 1: event title, 2: time until event */
			__( 'This is a reminder that "%1$s" is coming up in %2$s.', 'nonprofitsuite' ),
			$event['title'],
			$time_until
		);

		$message .= "\n\n";
		$message .= __( 'Event Details:', 'nonprofitsuite' ) . "\n";
		$message .= sprintf( __( 'When: %s', 'nonprofitsuite' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $event['start_datetime'] ) ) ) . "\n";

		if ( ! empty( $event['location'] ) ) {
			$message .= sprintf( __( 'Where: %s', 'nonprofitsuite' ), $event['location'] ) . "\n";
		}

		if ( ! empty( $event['description'] ) ) {
			$message .= "\n" . __( 'Description:', 'nonprofitsuite' ) . "\n";
			$message .= wp_strip_all_tags( $event['description'] ) . "\n";
		}

		$message .= "\n";
		$message .= __( 'View event:', 'nonprofitsuite' ) . ' ' . admin_url( 'admin.php?page=nonprofitsuite-calendar&event_id=' . $event['id'] );

		// Send email
		$sent = wp_mail( $recipient_email, $subject, $message );

		if ( ! $sent ) {
			return new WP_Error( 'email_failed', __( 'Failed to send email', 'nonprofitsuite' ) );
		}

		return true;
	}

	/**
	 * Send SMS reminder (placeholder for future SMS integration).
	 *
	 * @param array $event    Event data.
	 * @param array $reminder Reminder data.
	 * @return bool|WP_Error
	 */
	private static function send_sms_reminder( $event, $reminder ) {
		// TODO: Implement SMS integration (Twilio, etc.)
		/**
		 * Allows SMS reminder sending via third-party integration.
		 *
		 * @param array $event    Event data.
		 * @param array $reminder Reminder data.
		 */
		$result = apply_filters( 'ns_send_sms_reminder', false, $event, $reminder );

		if ( ! $result ) {
			return new WP_Error( 'sms_not_configured', __( 'SMS reminders not configured', 'nonprofitsuite' ) );
		}

		return $result;
	}

	/**
	 * Send push notification reminder (placeholder for future push integration).
	 *
	 * @param array $event    Event data.
	 * @param array $reminder Reminder data.
	 * @return bool|WP_Error
	 */
	private static function send_push_reminder( $event, $reminder ) {
		// TODO: Implement push notification integration
		/**
		 * Allows push notification sending via third-party integration.
		 *
		 * @param array $event    Event data.
		 * @param array $reminder Reminder data.
		 */
		$result = apply_filters( 'ns_send_push_reminder', false, $event, $reminder );

		if ( ! $result ) {
			return new WP_Error( 'push_not_configured', __( 'Push notifications not configured', 'nonprofitsuite' ) );
		}

		return $result;
	}

	/**
	 * Send in-app notification reminder.
	 *
	 * @param array $event    Event data.
	 * @param array $reminder Reminder data.
	 * @return bool
	 */
	private static function send_in_app_reminder( $event, $reminder ) {
		if ( ! $reminder['recipient_user_id'] ) {
			return new WP_Error( 'no_user', __( 'No user specified for in-app notification', 'nonprofitsuite' ) );
		}

		// Create in-app notification
		$notification_data = array(
			'user_id'     => $reminder['recipient_user_id'],
			'type'        => 'calendar_reminder',
			'title'       => sprintf( __( 'Reminder: %s', 'nonprofitsuite' ), $event['title'] ),
			'message'     => sprintf(
				__( '%s is coming up soon.', 'nonprofitsuite' ),
				$event['title']
			),
			'link'        => admin_url( 'admin.php?page=nonprofitsuite-calendar&event_id=' . $event['id'] ),
			'is_read'     => 0,
			'created_at'  => current_time( 'mysql' ),
		);

		/**
		 * Allows storing in-app notification.
		 *
		 * @param array $notification_data Notification data.
		 */
		do_action( 'ns_create_notification', $notification_data );

		return true;
	}

	/**
	 * Mark reminder as sent.
	 *
	 * @param int $reminder_id Reminder ID.
	 * @return bool
	 */
	private static function mark_reminder_sent( $reminder_id ) {
		return self::update_reminder( $reminder_id, array(
			'reminder_status' => 'sent',
			'sent_at'         => current_time( 'mysql' ),
		) );
	}

	/**
	 * Mark reminder as failed.
	 *
	 * @param int    $reminder_id    Reminder ID.
	 * @param string $error_message  Error message.
	 * @return bool
	 */
	private static function mark_reminder_failed( $reminder_id, $error_message ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_calendar_event_reminders';

		// Increment retry count
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET retry_count = retry_count + 1 WHERE id = %d",
			$reminder_id
		) );

		return self::update_reminder( $reminder_id, array(
			'reminder_status' => 'failed',
			'error_message'   => $error_message,
		) );
	}

	/**
	 * Calculate reminder send time based on event and offset.
	 *
	 * @param array $event           Event data.
	 * @param int   $offset_minutes  Minutes before event.
	 * @return string MySQL datetime.
	 */
	private static function calculate_reminder_time( $event, $offset_minutes ) {
		// Use start_datetime for meetings/events, due_date for tasks
		$event_time = ! empty( $event['start_datetime'] ) ? $event['start_datetime'] : $event['due_date'];

		$timestamp = strtotime( $event_time );
		$reminder_timestamp = $timestamp - ( $offset_minutes * 60 );

		return gmdate( 'Y-m-d H:i:s', $reminder_timestamp );
	}

	/**
	 * Format time until event in human-readable format.
	 *
	 * @param int $minutes Minutes.
	 * @return string Formatted time.
	 */
	private static function format_time_until( $minutes ) {
		if ( $minutes < 60 ) {
			return sprintf( _n( '%d minute', '%d minutes', $minutes, 'nonprofitsuite' ), $minutes );
		}

		$hours = floor( $minutes / 60 );
		if ( $hours < 24 ) {
			return sprintf( _n( '%d hour', '%d hours', $hours, 'nonprofitsuite' ), $hours );
		}

		$days = floor( $hours / 24 );
		if ( $days < 7 ) {
			return sprintf( _n( '%d day', '%d days', $days, 'nonprofitsuite' ), $days );
		}

		$weeks = floor( $days / 7 );
		return sprintf( _n( '%d week', '%d weeks', $weeks, 'nonprofitsuite' ), $weeks );
	}

	/**
	 * Get event data.
	 *
	 * @param int $event_id Event ID.
	 * @return array|null Event data or null.
	 */
	private static function get_event( $event_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_calendar_events';

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$event_id
		), ARRAY_A );
	}

	/**
	 * Reschedule all reminders for an event (when event time changes).
	 *
	 * @param int $event_id Event ID.
	 * @return int Number of reminders rescheduled.
	 */
	public static function reschedule_event_reminders( $event_id ) {
		$event = self::get_event( $event_id );
		if ( ! $event ) {
			return 0;
		}

		$reminders = self::get_event_reminders( $event_id, 'pending' );
		$count = 0;

		foreach ( $reminders as $reminder ) {
			$new_scheduled_time = self::calculate_reminder_time( $event, $reminder['reminder_offset'] );

			self::update_reminder( $reminder['id'], array(
				'scheduled_for' => $new_scheduled_time,
			) );

			$count++;
		}

		return $count;
	}

	/**
	 * Cancel all reminders for an event.
	 *
	 * @param int $event_id Event ID.
	 * @return int Number of reminders cancelled.
	 */
	public static function cancel_event_reminders( $event_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_calendar_event_reminders';

		$result = $wpdb->update(
			$table,
			array( 'reminder_status' => 'cancelled' ),
			array(
				'event_id'        => $event_id,
				'reminder_status' => 'pending',
			),
			array( '%s' ),
			array( '%d', '%s' )
		);

		return $result ? $result : 0;
	}
}
