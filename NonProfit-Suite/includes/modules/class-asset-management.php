<?php
/**
 * Asset Management Module
 *
 * Manage physical assets, track depreciation, handle disposal,
 * maintain comprehensive asset inventory and registers.
 *
 * @package    NonprofitSuite
 * @subpackage Modules
 * @since      2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Asset_Management {

	/**
	 * Create an asset
	 *
	 * @param array $data Asset data
	 * @return int|WP_Error Asset ID or error
	 */
	public static function create_asset( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_finances();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;

		$defaults = array(
			'asset_type' => '',
			'asset_name' => '',
			'description' => null,
			'category' => null,
			'acquisition_date' => current_time( 'Y-m-d' ),
			'acquisition_method' => 'purchased',
			'in_kind_donation_id' => null,
			'purchase_price' => null,
			'current_value' => null,
			'last_valuation_date' => current_time( 'Y-m-d' ),
			'depreciation_method' => null,
			'useful_life_years' => null,
			'location' => null,
			'condition_rating' => 'good',
			'serial_number' => null,
			'model' => null,
			'manufacturer' => null,
			'warranty_expiration' => null,
			'assigned_to' => null,
			'status' => 'active',
			'disposal_date' => null,
			'disposal_method' => null,
			'disposal_value' => null,
			'notes' => null,
		);

		$data = wp_parse_args( $data, $defaults );

		// Validate required fields
		if ( empty( $data['asset_type'] ) || empty( $data['asset_name'] ) ) {
			return new WP_Error( 'missing_required', __( 'Asset type and name are required.', 'nonprofitsuite' ) );
		}

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_assets',
			array(
				'asset_type' => sanitize_text_field( $data['asset_type'] ),
				'asset_name' => sanitize_text_field( $data['asset_name'] ),
				'description' => sanitize_textarea_field( $data['description'] ),
				'category' => sanitize_text_field( $data['category'] ),
				'acquisition_date' => $data['acquisition_date'],
				'acquisition_method' => sanitize_text_field( $data['acquisition_method'] ),
				'in_kind_donation_id' => absint( $data['in_kind_donation_id'] ),
				'purchase_price' => $data['purchase_price'],
				'current_value' => $data['current_value'],
				'last_valuation_date' => $data['last_valuation_date'],
				'depreciation_method' => sanitize_text_field( $data['depreciation_method'] ),
				'useful_life_years' => absint( $data['useful_life_years'] ),
				'location' => sanitize_text_field( $data['location'] ),
				'condition_rating' => sanitize_text_field( $data['condition_rating'] ),
				'serial_number' => sanitize_text_field( $data['serial_number'] ),
				'model' => sanitize_text_field( $data['model'] ),
				'manufacturer' => sanitize_text_field( $data['manufacturer'] ),
				'warranty_expiration' => $data['warranty_expiration'],
				'assigned_to' => absint( $data['assigned_to'] ),
				'status' => sanitize_text_field( $data['status'] ),
				'disposal_date' => $data['disposal_date'],
				'disposal_method' => sanitize_text_field( $data['disposal_method'] ),
				'disposal_value' => $data['disposal_value'],
				'notes' => sanitize_textarea_field( $data['notes'] ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%f', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%f', '%s' )
		);

		if ( false === $result ) {
			error_log( 'NonprofitSuite: Failed to create asset - ' . $wpdb->last_error );
			return new WP_Error( 'db_error', __( 'Failed to create asset.', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'assets' );
		return $wpdb->insert_id;
	}

	/**
	 * Get a single asset
	 *
	 * @param int $id Asset ID
	 * @return object|WP_Error
	 */
	public static function get_asset( $id ) {
		global $wpdb;

		// Use caching for individual assets
		$cache_key = NonprofitSuite_Cache::item_key( 'asset', $id );
		$asset = NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $id ) {
			return $wpdb->get_row( $wpdb->prepare(
				"SELECT id, asset_type, asset_name, description, category, acquisition_date, acquisition_method,
				        in_kind_donation_id, purchase_price, current_value, last_valuation_date,
				        depreciation_method, useful_life_years, location, condition_rating, serial_number,
				        model, manufacturer, warranty_expiration, assigned_to, status, disposal_date,
				        disposal_method, disposal_value, notes, created_at
				 FROM {$wpdb->prefix}ns_assets
				 WHERE id = %d",
				$id
			) );
		}, 300 );

		if ( ! $asset ) {
			return new WP_Error( 'not_found', __( 'Asset not found.', 'nonprofitsuite' ) );
		}

		return $asset;
	}

	/**
	 * Get assets with filters
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error
	 */
	public static function get_assets( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'asset_type' => null,
			'status' => null,
			'location' => null,
			'assigned_to' => null,
		);

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = array( '1=1' );
		$params = array();

		if ( ! empty( $args['asset_type'] ) ) {
			$where[] = 'asset_type = %s';
			$params[] = $args['asset_type'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where[] = 'status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['location'] ) ) {
			$where[] = 'location = %s';
			$params[] = $args['location'];
		}

		if ( ! empty( $args['assigned_to'] ) ) {
			$where[] = 'assigned_to = %d';
			$params[] = $args['assigned_to'];
		}

		$where_clause = implode( ' AND ', $where );

		// Use caching for asset lists
		$cache_key = NonprofitSuite_Cache::list_key( 'assets', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $where_clause, $params, $args ) {
			$orderby = sanitize_sql_orderby( "{$args['orderby']} {$args['order']}" );

			if ( ! empty( $params ) ) {
				$sql = $wpdb->prepare(
					"SELECT id, asset_type, asset_name, description, category, acquisition_date, acquisition_method,
					        in_kind_donation_id, purchase_price, current_value, last_valuation_date,
					        depreciation_method, useful_life_years, location, condition_rating, serial_number,
					        model, manufacturer, warranty_expiration, assigned_to, status, disposal_date,
					        disposal_method, disposal_value, notes, created_at
					 FROM {$wpdb->prefix}ns_assets
					 WHERE $where_clause
					 ORDER BY $orderby
					 " . NonprofitSuite_Utilities::build_limit_clause( $args ),
					$params
				);
			} else {
				$sql = "SELECT id, asset_type, asset_name, description, category, acquisition_date, acquisition_method,
				        in_kind_donation_id, purchase_price, current_value, last_valuation_date,
				        depreciation_method, useful_life_years, location, condition_rating, serial_number,
				        model, manufacturer, warranty_expiration, assigned_to, status, disposal_date,
				        disposal_method, disposal_value, notes, created_at
				        FROM {$wpdb->prefix}ns_assets
				        WHERE $where_clause
				        ORDER BY $orderby
				        " . NonprofitSuite_Utilities::build_limit_clause( $args );
			}

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	/**
	 * Update asset
	 *
	 * @param int $id Asset ID
	 * @param array $data Updated data
	 * @return bool|WP_Error
	 */
	public static function update_asset( $id, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_finances();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;

		// Sanitize and prepare update data
		$update_data = array();
		$update_format = array();

		$allowed_fields = array(
			'asset_name', 'description', 'current_value', 'condition_rating',
			'location', 'assigned_to', 'status', 'notes',
		);

		foreach ( $data as $key => $value ) {
			if ( in_array( $key, $allowed_fields, true ) ) {
				if ( $key === 'current_value' ) {
					$update_data[ $key ] = $value;
					$update_format[] = '%f';
				} elseif ( $key === 'assigned_to' ) {
					$update_data[ $key ] = absint( $value );
					$update_format[] = '%d';
				} else {
					$update_data[ $key ] = sanitize_text_field( $value );
					$update_format[] = '%s';
				}
			}
		}

		if ( empty( $update_data ) ) {
			return new WP_Error( 'no_data', __( 'No valid fields to update.', 'nonprofitsuite' ) );
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'ns_assets',
			$update_data,
			array( 'id' => absint( $id ) ),
			$update_format,
			array( '%d' )
		);

		if ( false === $result ) {
			error_log( 'NonprofitSuite: Failed to update asset - ' . $wpdb->last_error );
			return new WP_Error( 'db_error', __( 'Failed to update asset.', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'assets' );
		NonprofitSuite_Cache::delete( NonprofitSuite_Cache::item_key( 'asset', $id ) );
		return true;
	}

	/**
	 * Calculate depreciation for an asset
	 *
	 * @param int $id Asset ID
	 * @return float|WP_Error Depreciation amount or error
	 */
	public static function depreciate_asset( $id ) {
		$asset = self::get_asset( $id );

		if ( is_wp_error( $asset ) ) {
			return $asset;
		}

		if ( empty( $asset->depreciation_method ) || empty( $asset->useful_life_years ) ) {
			return 0;
		}

		$original_value = $asset->purchase_price ? $asset->purchase_price : $asset->current_value;
		$years_owned = ( time() - strtotime( $asset->acquisition_date ) ) / ( 365 * 24 * 60 * 60 );

		switch ( $asset->depreciation_method ) {
			case 'straight_line':
				$annual_depreciation = $original_value / $asset->useful_life_years;
				return min( $annual_depreciation * $years_owned, $original_value );

			case 'declining_balance':
				$rate = 2 / $asset->useful_life_years; // Double declining balance
				$depreciation = $original_value * ( 1 - pow( 1 - $rate, $years_owned ) );
				return min( $depreciation, $original_value );

			default:
				return 0;
		}
	}

	/**
	 * Calculate current value after depreciation
	 *
	 * @param int $id Asset ID
	 * @return float
	 */
	public static function calculate_current_value( $id ) {
		$asset = self::get_asset( $id );

		if ( is_wp_error( $asset ) ) {
			return 0;
		}

		$original_value = $asset->purchase_price ? $asset->purchase_price : $asset->current_value;
		$depreciation = self::depreciate_asset( $id );

		return max( 0, $original_value - $depreciation );
	}

	/**
	 * Dispose of an asset
	 *
	 * @param int $id Asset ID
	 * @param string $method Disposal method
	 * @param float $value Disposal value
	 * @return bool|WP_Error
	 */
	public static function dispose_asset( $id, $method, $value = 0 ) {
		global $wpdb;

		$result = $wpdb->update(
			$wpdb->prefix . 'ns_assets',
			array(
				'status' => 'disposed',
				'disposal_date' => current_time( 'Y-m-d' ),
				'disposal_method' => sanitize_text_field( $method ),
				'disposal_value' => (float) $value,
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s', '%s', '%f' ),
			array( '%d' )
		);

		if ( false === $result ) {
			error_log( 'NonprofitSuite: Failed to dispose asset - ' . $wpdb->last_error );
			return new WP_Error( 'db_error', __( 'Failed to dispose asset.', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'assets' );
		NonprofitSuite_Cache::delete( NonprofitSuite_Cache::item_key( 'asset', $id ) );
		return true;
	}

	/**
	 * Get asset register (complete inventory list)
	 *
	 * @return array|WP_Error
	 */
	public static function get_asset_register() {
		return self::get_assets( array(
			'status' => 'active',
			'limit' => 1000,
		) );
	}

	/**
	 * Get depreciation schedule
	 *
	 * @return array
	 */
	public static function get_depreciation_schedule() {
		$assets = self::get_assets( array(
			'status' => 'active',
			'limit' => 1000,
		) );

		if ( is_wp_error( $assets ) ) {
			return array();
		}

		$schedule = array();

		foreach ( $assets as $asset ) {
			if ( ! empty( $asset->depreciation_method ) ) {
				$depreciation = self::depreciate_asset( $asset->id );
				$schedule[] = array(
					'asset_id' => $asset->id,
					'asset_name' => $asset->asset_name,
					'original_value' => $asset->purchase_price ? $asset->purchase_price : $asset->current_value,
					'depreciation' => $depreciation,
					'current_value' => self::calculate_current_value( $asset->id ),
				);
			}
		}

		return $schedule;
	}

	/**
	 * Assign asset to person
	 *
	 * @param int $id Asset ID
	 * @param int $person_id Person ID
	 * @return bool|WP_Error
	 */
	public static function assign_asset( $id, $person_id ) {
		return self::update_asset( $id, array( 'assigned_to' => $person_id ) );
	}

	/**
	 * Transfer asset to new location
	 *
	 * @param int $id Asset ID
	 * @param string $new_location New location
	 * @return bool|WP_Error
	 */
	public static function transfer_asset( $id, $new_location ) {
		return self::update_asset( $id, array( 'location' => $new_location ) );
	}

	/**
	 * Get asset summary by category
	 *
	 * @return array
	 */
	public static function get_asset_summary() {
		global $wpdb;

		$results = $wpdb->get_results(
			"SELECT category, COUNT(*) as count, SUM(current_value) as total_value
			FROM {$wpdb->prefix}ns_assets
			WHERE status = 'active'
			GROUP BY category
			ORDER BY total_value DESC"
		);

		return $results ? $results : array();
	}

	/**
	 * Get retired/disposed assets for year
	 *
	 * @param int $year Year
	 * @return array|WP_Error
	 */
	public static function get_retired_assets( $year ) {
		global $wpdb;

		// Use caching for retired assets by year
		$cache_key = NonprofitSuite_Cache::list_key( 'retired_assets', array( 'year' => $year ) );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $year ) {
			// Use date range instead of YEAR() to allow index usage
			$year_start = $year . '-01-01 00:00:00';
			$year_end = $year . '-12-31 23:59:59';

			return $wpdb->get_results( $wpdb->prepare(
				"SELECT id, asset_type, asset_name, description, category, acquisition_date, acquisition_method,
				        in_kind_donation_id, purchase_price, current_value, last_valuation_date,
				        depreciation_method, useful_life_years, location, condition_rating, serial_number,
				        model, manufacturer, warranty_expiration, assigned_to, status, disposal_date,
				        disposal_method, disposal_value, notes, created_at
				 FROM {$wpdb->prefix}ns_assets
				 WHERE status = 'disposed'
				 AND disposal_date >= %s AND disposal_date <= %s
				 ORDER BY disposal_date DESC",
				$year_start,
				$year_end
			) );
		}, 300 );
	}

	/**
	 * Convert in-kind donation to asset
	 *
	 * @param int $donation_id Donation ID
	 * @return int|WP_Error Asset ID or error
	 */
	public static function convert_donation_to_asset( $donation_id ) {
		global $wpdb;

		$donation = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, category, description, donation_date, fair_market_value, condition_rating, location
			 FROM {$wpdb->prefix}ns_in_kind_donations
			 WHERE id = %d",
			$donation_id
		) );

		if ( ! $donation ) {
			return new WP_Error( 'not_found', __( 'Donation not found.', 'nonprofitsuite' ) );
		}

		// Create asset from donation
		$asset_data = array(
			'asset_type' => $donation->category,
			'asset_name' => $donation->description,
			'description' => $donation->description,
			'category' => $donation->category,
			'acquisition_date' => $donation->donation_date,
			'acquisition_method' => 'donated',
			'in_kind_donation_id' => $donation->id,
			'purchase_price' => 0,
			'current_value' => $donation->fair_market_value,
			'last_valuation_date' => $donation->donation_date,
			'condition_rating' => $donation->condition_rating,
			'location' => $donation->location,
			'status' => 'active',
		);

		return self::create_asset( $asset_data );
	}
}
