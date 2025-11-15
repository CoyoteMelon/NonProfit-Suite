<?php
/**
 * Grant Management Module (PRO)
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/modules
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class NonprofitSuite_Grants {

	private static function check_pro() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'Grant Management requires Pro license.', 'nonprofitsuite' ) );
		}
		return true;
	}

	public static function create_grant( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_grants';

		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_finances();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Validate required fields
		if ( empty( $data['grant_name'] ) ) {
			return new WP_Error( 'missing_required_field', __( 'Grant name is required.', 'nonprofitsuite' ) );
		}

		if ( empty( $data['grant_amount'] ) || ! is_numeric( $data['grant_amount'] ) || $data['grant_amount'] <= 0 ) {
			return new WP_Error( 'invalid_amount', __( 'Grant amount must be greater than zero.', 'nonprofitsuite' ) );
		}

		// Validate date format if provided
		if ( ! empty( $data['application_deadline'] ) && ! strtotime( $data['application_deadline'] ) ) {
			return new WP_Error( 'invalid_date', __( 'Invalid application deadline date format.', 'nonprofitsuite' ) );
		}

		$result = $wpdb->insert(
			$table,
			array(
				'grant_name' => sanitize_text_field( $data['grant_name'] ),
				'funder_organization_id' => isset( $data['funder_organization_id'] ) ? absint( $data['funder_organization_id'] ) : null,
				'grant_amount' => floatval( $data['grant_amount'] ),
				'status' => 'prospect',
				'application_deadline' => isset( $data['application_deadline'] ) ? sanitize_text_field( $data['application_deadline'] ) : null,
				'grant_purpose' => isset( $data['grant_purpose'] ) ? sanitize_textarea_field( $data['grant_purpose'] ) : null,
			),
			array( '%s', '%d', '%f', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			error_log( 'NonprofitSuite: Failed to create grant - ' . $wpdb->last_error );
			return new WP_Error( 'db_error', __( 'Failed to create grant.', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'grants' );
		return $wpdb->insert_id;
	}

	public static function update_grant_status( $grant_id, $status, $additional_data = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_grants';

		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_finances();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$update_data = array_merge(
			array( 'status' => sanitize_text_field( $status ) ),
			$additional_data
		);

		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $grant_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			error_log( 'NonprofitSuite: Failed to update grant status - ' . $wpdb->last_error );
			return new WP_Error( 'db_error', __( 'Failed to update grant status.', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'grants' );
		return true;
	}

	public static function get_grants( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_grants';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Parse pagination arguments
		$defaults = array( 'status' => null );
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = "WHERE 1=1";
		if ( $args['status'] ) {
			$where .= $wpdb->prepare( " AND status = %s", $args['status'] );
		}

		// Use caching for grant lists
		$cache_key = NonprofitSuite_Cache::list_key( 'grants', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $where, $args ) {
			$sql = "SELECT id, grant_name, funder_organization_id, grant_amount, amount_spent,
			               status, application_deadline, award_date, grant_purpose, start_date,
			               end_date, reporting_frequency, created_at
			        FROM {$table} {$where}
			        ORDER BY application_deadline ASC
			        " . NonprofitSuite_Utilities::build_limit_clause( $args );

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	public static function track_spending( $grant_id, $amount ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_grants';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$result = $wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET amount_spent = amount_spent + %f WHERE id = %d",
			$amount,
			$grant_id
		) );

		if ( false === $result ) {
			error_log( 'NonprofitSuite: Failed to track grant spending - ' . $wpdb->last_error );
			return new WP_Error( 'db_error', __( 'Failed to track grant spending.', 'nonprofitsuite' ) );
		}

		return true;
	}

	public static function create_grant_report( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_grant_reports';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$result = $wpdb->insert(
			$table,
			array(
				'grant_id' => absint( $data['grant_id'] ),
				'report_type' => sanitize_text_field( $data['report_type'] ),
				'due_date' => sanitize_text_field( $data['due_date'] ),
				'status' => 'pending',
			),
			array( '%d', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			error_log( 'NonprofitSuite: Failed to create grant report - ' . $wpdb->last_error );
			return new WP_Error( 'db_error', __( 'Failed to create grant report.', 'nonprofitsuite' ) );
		}

		return $wpdb->insert_id;
	}

	public static function get_grant_statuses() {
		return array(
			'prospect' => __( 'Prospect', 'nonprofitsuite' ),
			'applied' => __( 'Applied', 'nonprofitsuite' ),
			'awarded' => __( 'Awarded', 'nonprofitsuite' ),
			'reporting' => __( 'Reporting', 'nonprofitsuite' ),
			'completed' => __( 'Completed', 'nonprofitsuite' ),
			'declined' => __( 'Declined', 'nonprofitsuite' ),
		);
	}
}
