<?php
/**
 * Custom roles and capabilities
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Define custom roles and capabilities for nonprofit management.
 */
class NonprofitSuite_Roles {

	/**
	 * Create all custom roles.
	 */
	public static function create_roles() {
		// Board Member
		add_role(
			'ns_board_member',
			__( 'Board Member', 'nonprofitsuite' ),
			array(
				'read' => true,
				'ns_manage_meetings' => true,
				'ns_edit_minutes' => true,
				'ns_vote' => true,
				'ns_view_documents' => true,
				'ns_upload_documents' => true,
				'ns_view_financials' => true,
				'ns_manage_own_tasks' => true,
			)
		);

		// Committee Member
		add_role(
			'ns_committee_member',
			__( 'Committee Member', 'nonprofitsuite' ),
			array(
				'read' => true,
				'ns_manage_meetings' => true,
				'ns_edit_minutes' => true,
				'ns_vote' => true,
				'ns_view_documents' => true,
				'ns_manage_own_tasks' => true,
			)
		);

		// Staff
		add_role(
			'ns_staff',
			__( 'Staff', 'nonprofitsuite' ),
			array(
				'read' => true,
				'ns_manage_meetings' => true,
				'ns_view_minutes' => true,
				'ns_view_documents' => true,
				'ns_upload_documents' => true,
				'ns_manage_programs' => true,
				'ns_manage_volunteers' => true,
				'ns_manage_donors' => true,
				'ns_manage_tasks' => true,
			)
		);

		// Volunteer
		add_role(
			'ns_volunteer',
			__( 'Volunteer', 'nonprofitsuite' ),
			array(
				'read' => true,
				'ns_view_meetings' => true,
				'ns_view_documents_limited' => true,
				'ns_manage_own_tasks' => true,
				'ns_log_volunteer_hours' => true,
			)
		);

		// Treasurer
		add_role(
			'ns_treasurer',
			__( 'Treasurer', 'nonprofitsuite' ),
			array(
				'read' => true,
				'ns_manage_meetings' => true,
				'ns_edit_minutes' => true,
				'ns_vote' => true,
				'ns_view_documents' => true,
				'ns_upload_documents' => true,
				'ns_view_financials' => true,
				'ns_manage_financials' => true,
				'ns_manage_treasury' => true,
				'ns_reconcile_accounts' => true,
				'ns_generate_reports' => true,
				'ns_manage_own_tasks' => true,
			)
		);

		// Secretary
		add_role(
			'ns_secretary',
			__( 'Secretary', 'nonprofitsuite' ),
			array(
				'read' => true,
				'ns_manage_meetings' => true,
				'ns_edit_minutes' => true,
				'ns_approve_minutes' => true,
				'ns_vote' => true,
				'ns_view_documents' => true,
				'ns_upload_documents' => true,
				'ns_manage_documents' => true,
				'ns_view_financials' => true,
				'ns_manage_own_tasks' => true,
			)
		);

		// Add capabilities to Administrator
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			self::add_admin_capabilities( $admin );
		}
	}

	/**
	 * Add NonprofitSuite capabilities to Administrator role.
	 *
	 * @param WP_Role $admin The administrator role object.
	 */
	private static function add_admin_capabilities( $admin ) {
		$capabilities = array(
			'ns_manage_meetings',
			'ns_edit_minutes',
			'ns_approve_minutes',
			'ns_vote',
			'ns_view_documents',
			'ns_upload_documents',
			'ns_manage_documents',
			'ns_view_financials',
			'ns_manage_financials',
			'ns_manage_treasury',
			'ns_reconcile_accounts',
			'ns_generate_reports',
			'ns_manage_programs',
			'ns_manage_volunteers',
			'ns_manage_donors',
			'ns_manage_tasks',
			'ns_manage_own_tasks',
			'ns_manage_settings',
			'ns_manage_users',
			'ns_view_all',
		);

		foreach ( $capabilities as $cap ) {
			$admin->add_cap( $cap );
		}
	}

	/**
	 * Remove all custom roles.
	 */
	public static function remove_roles() {
		remove_role( 'ns_board_member' );
		remove_role( 'ns_committee_member' );
		remove_role( 'ns_staff' );
		remove_role( 'ns_volunteer' );
		remove_role( 'ns_treasurer' );
		remove_role( 'ns_secretary' );

		// Remove capabilities from Administrator
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			self::remove_admin_capabilities( $admin );
		}
	}

	/**
	 * Remove NonprofitSuite capabilities from Administrator role.
	 *
	 * @param WP_Role $admin The administrator role object.
	 */
	private static function remove_admin_capabilities( $admin ) {
		$capabilities = array(
			'ns_manage_meetings',
			'ns_edit_minutes',
			'ns_approve_minutes',
			'ns_vote',
			'ns_view_documents',
			'ns_upload_documents',
			'ns_manage_documents',
			'ns_view_financials',
			'ns_manage_financials',
			'ns_manage_treasury',
			'ns_reconcile_accounts',
			'ns_generate_reports',
			'ns_manage_programs',
			'ns_manage_volunteers',
			'ns_manage_donors',
			'ns_manage_tasks',
			'ns_manage_own_tasks',
			'ns_manage_settings',
			'ns_manage_users',
			'ns_view_all',
		);

		foreach ( $capabilities as $cap ) {
			$admin->remove_cap( $cap );
		}
	}

	/**
	 * Check if current user has a NonprofitSuite capability.
	 *
	 * @param string $capability The capability to check.
	 * @return bool True if user has capability, false otherwise.
	 */
	public static function current_user_can( $capability ) {
		return current_user_can( $capability );
	}

	/**
	 * Get all NonprofitSuite roles.
	 *
	 * @return array Array of role names and display names.
	 */
	public static function get_nonprofit_roles() {
		return array(
			'ns_board_member' => __( 'Board Member', 'nonprofitsuite' ),
			'ns_committee_member' => __( 'Committee Member', 'nonprofitsuite' ),
			'ns_staff' => __( 'Staff', 'nonprofitsuite' ),
			'ns_volunteer' => __( 'Volunteer', 'nonprofitsuite' ),
			'ns_treasurer' => __( 'Treasurer', 'nonprofitsuite' ),
			'ns_secretary' => __( 'Secretary', 'nonprofitsuite' ),
		);
	}
}
