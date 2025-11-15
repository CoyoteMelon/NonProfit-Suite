<?php
/**
 * Notifications Helper
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/helpers
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class NonprofitSuite_Notifications {

	/**
	 * Send meeting reminder.
	 *
	 * @param int $meeting_id The meeting ID.
	 * @return bool True on success, false on failure.
	 */
	public static function send_meeting_reminder( $meeting_id ) {
		$meeting = NonprofitSuite_Meetings::get( $meeting_id );
		if ( ! $meeting ) {
			return false;
		}

		// Get meeting attendees (would need to implement attendee selection)
		// For now, send to all board members

		$users = get_users( array( 'role__in' => array( 'ns_board_member', 'ns_secretary', 'ns_treasurer' ) ) );

		$subject = sprintf( __( 'Reminder: %s', 'nonprofitsuite' ), $meeting->title );
		$message = self::get_meeting_reminder_message( $meeting );

		foreach ( $users as $user ) {
			wp_mail( $user->user_email, $subject, $message );
		}

		return true;
	}

	/**
	 * Send task assignment notification.
	 *
	 * @param int $task_id The task ID.
	 * @return bool True on success, false on failure.
	 */
	public static function send_task_assignment( $task_id ) {
		$task = NonprofitSuite_Tasks::get( $task_id );
		if ( ! $task || ! $task->assigned_to ) {
			return false;
		}

		$user = get_userdata( $task->assigned_to );
		if ( ! $user ) {
			return false;
		}

		$subject = sprintf( __( 'New Task Assigned: %s', 'nonprofitsuite' ), $task->title );
		$message = self::get_task_assignment_message( $task );

		return wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * Get meeting reminder message.
	 *
	 * @param object $meeting Meeting object.
	 * @return string Email message.
	 */
	private static function get_meeting_reminder_message( $meeting ) {
		$org_name = get_option( 'nonprofitsuite_organization_name', get_bloginfo( 'name' ) );

		$message = sprintf( __( 'This is a reminder about the upcoming meeting:', 'nonprofitsuite' ) ) . "\n\n";
		$message .= sprintf( __( 'Meeting: %s', 'nonprofitsuite' ), $meeting->title ) . "\n";
		$message .= sprintf( __( 'Date: %s', 'nonprofitsuite' ), date( 'F j, Y g:i A', strtotime( $meeting->meeting_date ) ) ) . "\n";
		$message .= sprintf( __( 'Location: %s', 'nonprofitsuite' ), $meeting->location ) . "\n";

		if ( $meeting->virtual_url ) {
			$message .= sprintf( __( 'Virtual Link: %s', 'nonprofitsuite' ), $meeting->virtual_url ) . "\n";
		}

		$message .= "\n" . sprintf( __( 'View meeting details: %s', 'nonprofitsuite' ), admin_url( 'admin.php?page=nonprofitsuite-meetings&action=view&id=' . $meeting->id ) );

		return $message;
	}

	/**
	 * Get task assignment message.
	 *
	 * @param object $task Task object.
	 * @return string Email message.
	 */
	private static function get_task_assignment_message( $task ) {
		$message = sprintf( __( 'You have been assigned a new task:', 'nonprofitsuite' ) ) . "\n\n";
		$message .= sprintf( __( 'Task: %s', 'nonprofitsuite' ), $task->title ) . "\n";
		$message .= sprintf( __( 'Description: %s', 'nonprofitsuite' ), $task->description ) . "\n";
		$message .= sprintf( __( 'Due Date: %s', 'nonprofitsuite' ), $task->due_date ? date( 'F j, Y', strtotime( $task->due_date ) ) : 'Not set' ) . "\n";
		$message .= sprintf( __( 'Priority: %s', 'nonprofitsuite' ), ucfirst( $task->priority ) ) . "\n";

		$message .= "\n" . sprintf( __( 'View task details: %s', 'nonprofitsuite' ), admin_url( 'admin.php?page=nonprofitsuite-tasks&action=view&id=' . $task->id ) );

		return $message;
	}
}
