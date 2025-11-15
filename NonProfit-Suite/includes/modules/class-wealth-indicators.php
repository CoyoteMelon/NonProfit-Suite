<?php
/**
 * Wealth Indicators Module
 *
 * Track wealth signals, calculate giving capacity, integrate with
 * external screening services.
 *
 * @package    NonprofitSuite
 * @subpackage Modules
 * @since      2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Wealth_Indicators {

	/**
	 * Add wealth indicator
	 *
	 * @param int $prospect_id Prospect ID
	 * @param string $type Indicator type
	 * @param array $data Indicator data
	 * @return int|WP_Error Indicator ID or error
	 */
	public static function add_indicator( $prospect_id, $type, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_prospects();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;

		$defaults = array(
			'indicator_value' => '',
			'verified' => 0,
			'source' => null,
			'date_found' => current_time( 'Y-m-d' ),
			'notes' => null,
		);

		$data = wp_parse_args( $data, $defaults );

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_wealth_indicators',
			array(
				'prospect_id' => absint( $prospect_id ),
				'indicator_type' => sanitize_text_field( $type ),
				'indicator_value' => sanitize_textarea_field( $data['indicator_value'] ),
				'verified' => absint( $data['verified'] ),
				'source' => sanitize_text_field( $data['source'] ),
				'date_found' => $data['date_found'],
				'notes' => sanitize_textarea_field( $data['notes'] ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to add indicator.', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'wealth_indicators' );
		return $wpdb->insert_id;
	}

	/**
	 * Get all indicators for a prospect
	 *
	 * @param int $prospect_id Prospect ID
	 * @return array|WP_Error
	 */
	public static function get_indicators( $prospect_id ) {
		global $wpdb;

		// Use caching for wealth indicators
		$cache_key = NonprofitSuite_Cache::list_key( 'wealth_indicators', array( 'prospect_id' => $prospect_id ) );
		$results = NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $prospect_id ) {
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT id, prospect_id, indicator_type, indicator_value, verified, source,
				        date_found, notes, created_at
				 FROM {$wpdb->prefix}ns_wealth_indicators
				WHERE prospect_id = %d
				ORDER BY date_found DESC",
				$prospect_id
			) );
		}, 300 );

		if ( null === $results ) {
			return new WP_Error( 'db_error', __( 'Failed to fetch indicators.', 'nonprofitsuite' ) );
		}

		return $results;
	}

	/**
	 * Verify an indicator
	 *
	 * @param int $id Indicator ID
	 * @return bool|WP_Error
	 */
	public static function verify_indicator( $id ) {
		global $wpdb;

		$result = $wpdb->update(
			$wpdb->prefix . 'ns_wealth_indicators',
			array( 'verified' => 1 ),
			array( 'id' => absint( $id ) ),
			array( '%d' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to verify indicator.', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'wealth_indicators' );
		return true;
	}

	/**
	 * Calculate capacity based on wealth indicators
	 *
	 * @param int $prospect_id Prospect ID
	 * @return float
	 */
	public static function calculate_capacity( $prospect_id ) {
		$indicators = self::get_indicators( $prospect_id );

		if ( is_wp_error( $indicators ) || empty( $indicators ) ) {
			return 0;
		}

		$total_capacity = 0;

		// Weighted capacity calculation
		$weights = array(
			'real_estate' => 0.05,        // 5% of value
			'business_ownership' => 0.10, // 10% of estimated value
			'stock_holdings' => 0.10,     // 10% of holdings
			'philanthropic_history' => 1.5, // 150% of largest gift
			'professional_position' => 50000, // Flat amount for C-level
			'family_foundation' => 100000,    // Flat amount
		);

		foreach ( $indicators as $indicator ) {
			$value = 0;

			switch ( $indicator->indicator_type ) {
				case 'real_estate':
				case 'business_ownership':
				case 'stock_holdings':
					// Extract numeric value from indicator_value
					preg_match( '/\$?([0-9,]+)/', $indicator->indicator_value, $matches );
					if ( ! empty( $matches[1] ) ) {
						$numeric_value = (float) str_replace( ',', '', $matches[1] );
						$multiplier = isset( $weights[ $indicator->indicator_type ] ) ? $weights[ $indicator->indicator_type ] : 0.05;
						$value = $numeric_value * $multiplier;
					}
					break;

				case 'philanthropic_history':
					preg_match( '/\$?([0-9,]+)/', $indicator->indicator_value, $matches );
					if ( ! empty( $matches[1] ) ) {
						$numeric_value = (float) str_replace( ',', '', $matches[1] );
						$value = $numeric_value * 1.5;
					}
					break;

				case 'professional_position':
				case 'family_foundation':
					$value = isset( $weights[ $indicator->indicator_type ] ) ? $weights[ $indicator->indicator_type ] : 0;
					break;
			}

			$total_capacity += $value;
		}

		return round( $total_capacity, 2 );
	}

	/**
	 * Get capacity score (0-100) based on indicators
	 *
	 * @param int $prospect_id Prospect ID
	 * @return int
	 */
	public static function get_capacity_score( $prospect_id ) {
		$capacity = self::calculate_capacity( $prospect_id );

		// Score mapping
		if ( $capacity >= 1000000 ) {
			return 100;
		} elseif ( $capacity >= 500000 ) {
			return 90;
		} elseif ( $capacity >= 100000 ) {
			return 75;
		} elseif ( $capacity >= 50000 ) {
			return 60;
		} elseif ( $capacity >= 25000 ) {
			return 45;
		} else {
			return max( 20, min( 40, $capacity / 1000 ) );
		}
	}
}
