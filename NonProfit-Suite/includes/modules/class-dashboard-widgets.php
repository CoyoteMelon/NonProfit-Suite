<?php
/**
 * Dashboard Widgets & Reporting Module
 *
 * @package NonprofitSuite
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NonprofitSuite_Dashboard_Widgets {

	/**
	 * Available widget types
	 *
	 * @return array Widget types
	 */
	public static function get_available_widgets() {
		return array(
			'financial_summary' => __( 'Financial Summary', 'nonprofitsuite' ),
			'upcoming_meetings' => __( 'Upcoming Meetings', 'nonprofitsuite' ),
			'recent_donations' => __( 'Recent Donations', 'nonprofitsuite' ),
			'compliance_alerts' => __( 'Compliance Alerts', 'nonprofitsuite' ),
			'volunteer_stats' => __( 'Volunteer Statistics', 'nonprofitsuite' ),
			'task_summary' => __( 'Task Summary', 'nonprofitsuite' ),
			'grant_deadlines' => __( 'Grant Deadlines', 'nonprofitsuite' ),
			'member_growth' => __( 'Membership Growth', 'nonprofitsuite' ),
		);
	}

	/**
	 * Save user widget configuration
	 *
	 * @param int    $user_id User ID
	 * @param string $widget_type Widget type
	 * @param array  $settings Widget settings
	 * @param int    $position Position in dashboard
	 * @return int|WP_Error Widget ID or error
	 */
	public static function save_widget( $user_id, $widget_type, $settings = array(), $position = 0 ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		// Check if widget already exists
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}ns_dashboard_widgets WHERE user_id = %d AND widget_type = %s",
			absint( $user_id ),
			sanitize_text_field( $widget_type )
		) );

		if ( $existing ) {
			$result = $wpdb->update(
				$wpdb->prefix . 'ns_dashboard_widgets',
				array(
					'position' => absint( $position ),
					'settings' => wp_json_encode( $settings ),
					'is_visible' => 1,
				),
				array( 'id' => $existing ),
				array( '%d', '%s', '%d' ),
				array( '%d' )
			);

			return $existing;
		} else {
			$result = $wpdb->insert(
				$wpdb->prefix . 'ns_dashboard_widgets',
				array(
					'user_id' => absint( $user_id ),
					'widget_type' => sanitize_text_field( $widget_type ),
					'position' => absint( $position ),
					'settings' => wp_json_encode( $settings ),
					'is_visible' => 1,
				),
				array( '%d', '%s', '%d', '%s', '%d' )
			);

			if ( $result === false ) {
				return new WP_Error( 'db_error', __( 'Failed to save widget', 'nonprofitsuite' ) );
			}

			NonprofitSuite_Cache::invalidate_module( 'dashboard_widgets' );
			return $wpdb->insert_id;
		}
	}

	/**
	 * Get user widgets
	 *
	 * @param int $user_id User ID
	 * @return array Array of widgets
	 */
	public static function get_user_widgets( $user_id ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		// Use caching for user widgets
		$cache_key = NonprofitSuite_Cache::item_key( 'user_dashboard_widgets', $user_id );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $user_id ) {
			$widgets = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, user_id, widget_type, position, settings, is_visible, created_at
				 FROM {$wpdb->prefix}ns_dashboard_widgets WHERE user_id = %d AND is_visible = 1 ORDER BY position ASC",
				absint( $user_id )
			) );

			// Decode settings
			foreach ( $widgets as $widget ) {
				$widget->settings = json_decode( $widget->settings, true );
			}

			return $widgets;
		}, 300 );
	}

	/**
	 * Toggle widget visibility
	 *
	 * @param int  $widget_id Widget ID
	 * @param bool $visible Visibility
	 * @return bool True on success
	 */
	public static function toggle_visibility( $widget_id, $visible ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return false;
		}

		global $wpdb;

		$result = $wpdb->update(
			$wpdb->prefix . 'ns_dashboard_widgets',
			array( 'is_visible' => $visible ? 1 : 0 ),
			array( 'id' => absint( $widget_id ) ),
			array( '%d' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			NonprofitSuite_Cache::invalidate_module( 'dashboard_widgets' );
		}

		return $result !== false;
	}

	/**
	 * Update widget positions
	 *
	 * @param array $positions Array of widget_id => position
	 * @return bool True on success
	 */
	public static function update_positions( $positions ) {
		// Check permissions FIRST - users can arrange their own dashboards
		$permission_check = NonprofitSuite_Security::check_capability( 'read', 'arrange dashboard widgets' );
		if ( is_wp_error( $permission_check ) ) {
			return false;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return false;
		}

		global $wpdb;

		foreach ( $positions as $widget_id => $position ) {
			$wpdb->update(
				$wpdb->prefix . 'ns_dashboard_widgets',
				array( 'position' => absint( $position ) ),
				array( 'id' => absint( $widget_id ) ),
				array( '%d' ),
				array( '%d' )
			);
		}

		NonprofitSuite_Cache::invalidate_module( 'dashboard_widgets' );
		return true;
	}

	/**
	 * Generate widget data
	 *
	 * @param string $widget_type Widget type
	 * @param array  $settings Widget settings
	 * @return array Widget data
	 */
	public static function generate_widget_data( $widget_type, $settings = array() ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		switch ( $widget_type ) {
			case 'financial_summary':
				return self::get_financial_summary();
			case 'upcoming_meetings':
				return self::get_upcoming_meetings();
			case 'compliance_alerts':
				return self::get_compliance_alerts();
			default:
				return array();
		}
	}

	/**
	 * Get financial summary data
	 *
	 * @return array Financial data
	 */
	private static function get_financial_summary() {
		global $wpdb;

		// Use date ranges instead of DATE_FORMAT() to allow index usage
		$month_start = date( 'Y-m-01 00:00:00' );
		$month_end = date( 'Y-m-t 23:59:59' );

		$revenue = $wpdb->get_var( $wpdb->prepare(
			"SELECT SUM(amount) FROM {$wpdb->prefix}ns_transactions
			WHERE type = 'income' AND transaction_date >= %s AND transaction_date <= %s",
			$month_start,
			$month_end
		) );

		$expenses = $wpdb->get_var( $wpdb->prepare(
			"SELECT SUM(amount) FROM {$wpdb->prefix}ns_transactions
			WHERE type = 'expense' AND transaction_date >= %s AND transaction_date <= %s",
			$month_start,
			$month_end
		) );

		return array(
			'revenue' => floatval( $revenue ),
			'expenses' => floatval( $expenses ),
			'net' => floatval( $revenue ) - floatval( $expenses ),
		);
	}

	/**
	 * Get upcoming meetings
	 *
	 * @return array Meetings data
	 */
	private static function get_upcoming_meetings() {
		global $wpdb;

		return $wpdb->get_results(
			"SELECT id, title, meeting_date, meeting_time, location, meeting_type, agenda,
			        attendees, minutes, minutes_status, notes, created_at
			 FROM {$wpdb->prefix}ns_meetings
			WHERE meeting_date >= CURDATE()
			ORDER BY meeting_date ASC
			LIMIT 5"
		);
	}

	/**
	 * Get compliance alerts
	 *
	 * @return array Compliance data
	 */
	private static function get_compliance_alerts() {
		global $wpdb;

		return $wpdb->get_results(
			"SELECT id, title, description, category, due_date, recurring, recurring_frequency,
			        responsible_party, status, priority, notes, completed_date, created_at
			 FROM {$wpdb->prefix}ns_compliance_items
			WHERE due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
			AND status != 'completed'
			ORDER BY due_date ASC
			LIMIT 5"
		);
	}
}
