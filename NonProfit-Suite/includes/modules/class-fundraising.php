<?php
/**
 * Fundraising/Campaigns Module (PRO)
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/modules
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class NonprofitSuite_Fundraising {

	private static function check_pro() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'Fundraising module requires Pro license.', 'nonprofitsuite' ) );
		}
		return true;
	}

	public static function create_campaign( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_campaigns';

		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_finances();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Validate required fields
		if ( empty( $data['campaign_name'] ) ) {
			return new WP_Error( 'missing_required_field', __( 'Campaign name is required.', 'nonprofitsuite' ) );
		}

		// Validate goal_amount if provided
		if ( isset( $data['goal_amount'] ) && ( ! is_numeric( $data['goal_amount'] ) || $data['goal_amount'] < 0 ) ) {
			return new WP_Error( 'invalid_amount', __( 'Campaign goal amount must be zero or greater.', 'nonprofitsuite' ) );
		}

		// Validate date formats if provided
		if ( ! empty( $data['start_date'] ) && ! strtotime( $data['start_date'] ) ) {
			return new WP_Error( 'invalid_date', __( 'Invalid start date format.', 'nonprofitsuite' ) );
		}

		if ( ! empty( $data['end_date'] ) && ! strtotime( $data['end_date'] ) ) {
			return new WP_Error( 'invalid_date', __( 'Invalid end date format.', 'nonprofitsuite' ) );
		}

		// Validate end date is after start date if both provided
		if ( ! empty( $data['start_date'] ) && ! empty( $data['end_date'] ) ) {
			if ( strtotime( $data['end_date'] ) < strtotime( $data['start_date'] ) ) {
				return new WP_Error( 'invalid_date_range', __( 'End date must be after start date.', 'nonprofitsuite' ) );
			}
		}

		$result = $wpdb->insert(
			$table,
			array(
				'campaign_name' => sanitize_text_field( $data['campaign_name'] ),
				'campaign_type' => isset( $data['campaign_type'] ) ? sanitize_text_field( $data['campaign_type'] ) : 'general',
				'goal_amount' => isset( $data['goal_amount'] ) ? floatval( $data['goal_amount'] ) : 0,
				'start_date' => isset( $data['start_date'] ) ? sanitize_text_field( $data['start_date'] ) : null,
				'end_date' => isset( $data['end_date'] ) ? sanitize_text_field( $data['end_date'] ) : null,
				'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : null,
				'status' => 'active',
			),
			array( '%s', '%s', '%f', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			error_log( 'NonprofitSuite: Failed to create campaign - ' . $wpdb->last_error );
			return new WP_Error( 'db_error', __( 'Failed to create campaign.', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'fundraising_campaigns' );
		return $wpdb->insert_id;
	}

	public static function add_donation_to_campaign( $campaign_id, $donation_amount ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_campaigns';

		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_finances();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$result = $wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET amount_raised = amount_raised + %f WHERE id = %d",
			$donation_amount,
			$campaign_id
		) );

		if ( false === $result ) {
			error_log( 'NonprofitSuite: Failed to add donation to campaign - ' . $wpdb->last_error );
			return new WP_Error( 'db_error', __( 'Failed to add donation to campaign.', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'fundraising_campaigns' );
		NonprofitSuite_Cache::delete( NonprofitSuite_Cache::item_key( 'fundraising_campaign', $campaign_id ) );
		return true;
	}

	public static function get_campaigns( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_campaigns';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Parse pagination arguments
		$defaults = array( 'status' => 'active' );
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = $args['status'] ? $wpdb->prepare( "WHERE status = %s", $args['status'] ) : "WHERE 1=1";

		// Use caching for campaign lists
		$cache_key = NonprofitSuite_Cache::list_key( 'fundraising_campaigns', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $where, $args ) {
			$sql = "SELECT id, campaign_name, campaign_type, goal_amount, amount_raised,
			               start_date, end_date, description, status, created_at
			        FROM {$table} {$where}
			        ORDER BY start_date DESC
			        " . NonprofitSuite_Utilities::build_limit_clause( $args );

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	public static function get_campaign_progress( $campaign_id ) {
		$campaign = self::get_campaign( $campaign_id );
		if ( ! $campaign ) return null;

		$percentage = $campaign->goal_amount > 0
			? ( $campaign->amount_raised / $campaign->goal_amount ) * 100
			: 0;

		return array(
			'goal' => $campaign->goal_amount,
			'raised' => $campaign->amount_raised,
			'percentage' => round( $percentage, 2 ),
			'remaining' => max( 0, $campaign->goal_amount - $campaign->amount_raised ),
		);
	}

	public static function get_campaign( $campaign_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_campaigns';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Use caching for individual campaigns
		$cache_key = NonprofitSuite_Cache::item_key( 'fundraising_campaign', $campaign_id );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $campaign_id ) {
			return $wpdb->get_row( $wpdb->prepare(
				"SELECT id, campaign_name, campaign_type, goal_amount, amount_raised,
				        start_date, end_date, description, status, created_at
				 FROM {$table}
				 WHERE id = %d",
				$campaign_id
			) );
		}, 300 );
	}

	public static function get_campaign_types() {
		return array(
			'general' => __( 'General Fund', 'nonprofitsuite' ),
			'annual' => __( 'Annual Appeal', 'nonprofitsuite' ),
			'capital' => __( 'Capital Campaign', 'nonprofitsuite' ),
			'special' => __( 'Special Project', 'nonprofitsuite' ),
			'endowment' => __( 'Endowment', 'nonprofitsuite' ),
		);
	}
}
