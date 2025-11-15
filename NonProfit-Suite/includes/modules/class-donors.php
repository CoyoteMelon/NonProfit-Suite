<?php
/**
 * Donor Management Module (PRO)
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/modules
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class NonprofitSuite_Donors {

	private static function check_pro() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'Donor Management requires Pro license.', 'nonprofitsuite' ) );
		}
		return true;
	}

	public static function create_donor( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_donors';

		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_donors();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Validate required fields
		if ( empty( $data['donor_type'] ) ) {
			return new WP_Error( 'missing_required_field', __( 'Donor type is required.', 'nonprofitsuite' ) );
		}

		// Validate that at least person_id OR organization_id is provided
		if ( empty( $data['person_id'] ) && empty( $data['organization_id'] ) ) {
			return new WP_Error( 'missing_required_field', __( 'Either person or organization must be specified.', 'nonprofitsuite' ) );
		}

		// Validate donor_type
		$valid_types = array( 'individual', 'organization', 'foundation' );
		if ( ! in_array( $data['donor_type'], $valid_types, true ) ) {
			return new WP_Error( 'invalid_donor_type', __( 'Invalid donor type. Must be individual, organization, or foundation.', 'nonprofitsuite' ) );
		}

		$result = $wpdb->insert(
			$table,
			array(
				'person_id' => isset( $data['person_id'] ) ? absint( $data['person_id'] ) : null,
				'organization_id' => isset( $data['organization_id'] ) ? absint( $data['organization_id'] ) : null,
				'donor_type' => sanitize_text_field( $data['donor_type'] ),
				'donor_status' => 'active',
				'donor_level' => isset( $data['donor_level'] ) ? sanitize_text_field( $data['donor_level'] ) : null,
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			error_log( 'NonprofitSuite: Failed to create donor - ' . $wpdb->last_error );
			return new WP_Error( 'db_error', __( 'Failed to create donor record.', 'nonprofitsuite' ) );
		}

		return $wpdb->insert_id;
	}

	public static function record_donation( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_donations';

		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_donors();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Validate required fields
		if ( empty( $data['donor_id'] ) || ! is_numeric( $data['donor_id'] ) || $data['donor_id'] <= 0 ) {
			return new WP_Error( 'invalid_donor_id', __( 'Valid donor ID is required.', 'nonprofitsuite' ) );
		}

		if ( empty( $data['amount'] ) || ! is_numeric( $data['amount'] ) || $data['amount'] <= 0 ) {
			return new WP_Error( 'invalid_amount', __( 'Donation amount must be greater than zero.', 'nonprofitsuite' ) );
		}

		if ( empty( $data['donation_date'] ) ) {
			return new WP_Error( 'missing_required_field', __( 'Donation date is required.', 'nonprofitsuite' ) );
		}

		// Validate date format
		if ( ! strtotime( $data['donation_date'] ) ) {
			return new WP_Error( 'invalid_date', __( 'Invalid donation date format.', 'nonprofitsuite' ) );
		}

		// Start transaction for data consistency
		$wpdb->query( 'START TRANSACTION' );

		$result = $wpdb->insert(
			$table,
			array(
				'donor_id' => absint( $data['donor_id'] ),
				'donation_date' => sanitize_text_field( $data['donation_date'] ),
				'amount' => floatval( $data['amount'] ),
				'payment_method' => isset( $data['payment_method'] ) ? sanitize_text_field( $data['payment_method'] ) : null,
				'fund_id' => isset( $data['fund_id'] ) ? absint( $data['fund_id'] ) : null,
				'is_recurring' => isset( $data['is_recurring'] ) ? 1 : 0,
			),
			array( '%d', '%s', '%f', '%s', '%d', '%d' )
		);

		if ( false === $result ) {
			$wpdb->query( 'ROLLBACK' );
			error_log( 'NonprofitSuite: Failed to record donation - ' . $wpdb->last_error );
			return new WP_Error( 'db_error', __( 'Failed to record donation.', 'nonprofitsuite' ) );
		}

		$donation_id = $wpdb->insert_id;

		// Update donor totals
		$update_result = self::update_donor_totals( $data['donor_id'] );
		if ( is_wp_error( $update_result ) ) {
			$wpdb->query( 'ROLLBACK' );
			error_log( 'NonprofitSuite: Failed to update donor totals - ' . $update_result->get_error_message() );
			return new WP_Error( 'db_error', __( 'Failed to update donor totals.', 'nonprofitsuite' ) );
		}

		// Commit transaction
		$wpdb->query( 'COMMIT' );

		return $donation_id;
	}

	public static function get_donors( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_donors';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, array(
			'status' => 'active',
		) ) );

		$where = "WHERE 1=1";
		if ( ! empty( $args['status'] ) ) {
			$where .= $wpdb->prepare( " AND donor_status = %s", $args['status'] );
		}

		// Use specific columns and pagination
		$sql = "SELECT id, person_id, organization_id, donor_type, donor_status, donor_level,
		               total_donated, first_donation_date, last_donation_date, created_at
		        FROM {$table}
		        {$where}
		        ORDER BY total_donated DESC
		        " . NonprofitSuite_Utilities::build_limit_clause( $args );

		return $wpdb->get_results( $sql );
	}

	public static function get_donation_history( $donor_id, $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_donations';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( $args );

		// Use specific columns with pagination
		$sql = $wpdb->prepare(
			"SELECT id, donor_id, donation_date, amount, payment_method, transaction_id,
			        campaign_id, fund_id, is_recurring, tax_deductible,
			        acknowledgment_sent, acknowledgment_date, created_at
			 FROM {$table}
			 WHERE donor_id = %d
			 ORDER BY donation_date DESC
			 " . NonprofitSuite_Utilities::build_limit_clause( $args ),
			$donor_id
		);

		return $wpdb->get_results( $sql );
	}

	private static function update_donor_totals( $donor_id ) {
		global $wpdb;
		$donors_table = $wpdb->prefix . 'ns_donors';
		$donations_table = $wpdb->prefix . 'ns_donations';

		$totals = $wpdb->get_row( $wpdb->prepare(
			"SELECT
				SUM(amount) as total_donated,
				MIN(donation_date) as first_donation,
				MAX(donation_date) as last_donation
			FROM {$donations_table}
			WHERE donor_id = %d",
			$donor_id
		) );

		if ( null === $totals && $wpdb->last_error ) {
			error_log( 'NonprofitSuite: Failed to calculate donor totals - ' . $wpdb->last_error );
			return new WP_Error( 'db_error', __( 'Failed to calculate donor totals.', 'nonprofitsuite' ) );
		}

		$result = $wpdb->update(
			$donors_table,
			array(
				'total_donated' => $totals->total_donated,
				'first_donation_date' => $totals->first_donation,
				'last_donation_date' => $totals->last_donation,
			),
			array( 'id' => $donor_id ),
			array( '%f', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			error_log( 'NonprofitSuite: Failed to update donor totals - ' . $wpdb->last_error );
			return new WP_Error( 'db_error', __( 'Failed to update donor totals.', 'nonprofitsuite' ) );
		}

		return true;
	}

	public static function get_donor_levels() {
		return array(
			'bronze' => __( 'Bronze ($1-$499)', 'nonprofitsuite' ),
			'silver' => __( 'Silver ($500-$999)', 'nonprofitsuite' ),
			'gold' => __( 'Gold ($1,000-$4,999)', 'nonprofitsuite' ),
			'platinum' => __( 'Platinum ($5,000+)', 'nonprofitsuite' ),
		);
	}

	public static function generate_tax_receipt( $donation_id ) {
		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Generate tax receipt PDF
		// Implementation would use PDF generator
		return true;
	}
}
