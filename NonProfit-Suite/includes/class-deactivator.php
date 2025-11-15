<?php
/**
 * Fired during plugin deactivation
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 */
class NonprofitSuite_Deactivator {

	/**
	 * Deactivate the plugin.
	 *
	 * Clean up temporary data and scheduled events.
	 * Note: We do NOT remove database tables or user data on deactivation.
	 * Data is only removed if the user explicitly uninstalls the plugin.
	 */
	public static function deactivate() {
		// Clear any scheduled cron events
		self::clear_scheduled_events();

		// Clear any transients
		self::clear_transients();
	}

	/**
	 * Clear scheduled cron events.
	 */
	private static function clear_scheduled_events() {
		// Clear meeting reminders
		$timestamp = wp_next_scheduled( 'nonprofitsuite_meeting_reminders' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'nonprofitsuite_meeting_reminders' );
		}

		// Clear task reminders
		$timestamp = wp_next_scheduled( 'nonprofitsuite_task_reminders' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'nonprofitsuite_task_reminders' );
		}

		// Clear any other scheduled events
		$timestamp = wp_next_scheduled( 'nonprofitsuite_daily_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'nonprofitsuite_daily_cleanup' );
		}

		// Clear document retention processing
		$timestamp = wp_next_scheduled( 'nonprofitsuite_process_document_retention' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'nonprofitsuite_process_document_retention' );
		}

		// Clear calendar sync
		$timestamp = wp_next_scheduled( 'nonprofitsuite_calendar_sync' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'nonprofitsuite_calendar_sync' );
		}

		// Clear calendar reminder processing
		$timestamp = wp_next_scheduled( 'nonprofitsuite_process_calendar_reminders' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'nonprofitsuite_process_calendar_reminders' );
		}
	}

	/**
	 * Clear plugin transients.
	 */
	private static function clear_transients() {
		delete_transient( 'nonprofitsuite_activation_redirect' );
		delete_transient( 'nonprofitsuite_dashboard_stats' );
	}
}
