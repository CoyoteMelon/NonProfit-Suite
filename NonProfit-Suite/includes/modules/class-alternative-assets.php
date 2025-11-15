<?php
/**
 * Alternative Assets Module
 *
 * Handles non-cash donations: cryptocurrency, precious metals, NFTs, commodities, unusual assets
 *
 * @package NonprofitSuite
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NonprofitSuite_Alternative_Assets {

	/**
	 * Asset types
	 */
	const ASSET_TYPES = array(
		'Cryptocurrency',
		'Precious Metals',
		'NFT',
		'Commodity',
		'Intellectual Property',
		'Domain Name',
		'Vehicle',
		'Real Estate',
		'Other'
	);

	/**
	 * Asset statuses
	 */
	const STATUSES = array(
		'held' => 'Held (Currently Owned)',
		'liquidated' => 'Liquidated (Sold/Converted)',
		'transferred' => 'Transferred (Given to Another Org)',
		'lost' => 'Lost (Lost/Stolen)',
		'used' => 'Used (Consumed/Utilized)'
	);

	/**
	 * Handling policies
	 */
	const HANDLING_POLICIES = array(
		'liquidate_immediate' => 'Liquidate Immediately',
		'liquidate_gradual' => 'Liquidate Gradually',
		'hold_short' => 'Hold Short Term (<1 year)',
		'hold_long' => 'Hold Long Term (>1 year)',
		'hold_endowment' => 'Hold for Endowment',
		'use_directly' => 'Use Directly',
		'transfer_affiliate' => 'Transfer to Affiliate'
	);

	/**
	 * Record alternative asset donation
	 *
	 * @param array $data Asset data
	 * @return int|WP_Error Asset ID or error
	 */
	public static function record_asset( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_finances();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_alternative_assets',
			array(
				'donation_id' => isset( $data['donation_id'] ) ? absint( $data['donation_id'] ) : null,
				'in_kind_donation_id' => isset( $data['in_kind_donation_id'] ) ? absint( $data['in_kind_donation_id'] ) : null,
				'asset_type' => sanitize_text_field( $data['asset_type'] ),
				'asset_subtype' => isset( $data['asset_subtype'] ) ? sanitize_text_field( $data['asset_subtype'] ) : null,
				'description' => wp_kses_post( $data['description'] ),
				'quantity' => isset( $data['quantity'] ) ? floatval( $data['quantity'] ) : null,
				'unit' => isset( $data['unit'] ) ? sanitize_text_field( $data['unit'] ) : null,
				'date_received' => sanitize_text_field( $data['date_received'] ),
				'valuation_date' => isset( $data['valuation_date'] ) ? sanitize_text_field( $data['valuation_date'] ) : $data['date_received'],
				'valuation_method' => sanitize_text_field( $data['valuation_method'] ),
				'fair_market_value' => floatval( $data['fair_market_value'] ),
				'cost_basis' => isset( $data['cost_basis'] ) ? floatval( $data['cost_basis'] ) : null,
				'donor_holding_period' => isset( $data['donor_holding_period'] ) ? sanitize_text_field( $data['donor_holding_period'] ) : 'unknown',
				'current_value' => floatval( $data['fair_market_value'] ),
				'last_valued_date' => isset( $data['valuation_date'] ) ? sanitize_text_field( $data['valuation_date'] ) : $data['date_received'],
				'status' => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'held',
				'handling_policy' => sanitize_text_field( $data['handling_policy'] ),
				'custody_location' => isset( $data['custody_location'] ) ? sanitize_text_field( $data['custody_location'] ) : null,
				'wallet_address' => isset( $data['wallet_address'] ) ? sanitize_text_field( $data['wallet_address'] ) : null,
				'blockchain' => isset( $data['blockchain'] ) ? sanitize_text_field( $data['blockchain'] ) : null,
				'serial_number' => isset( $data['serial_number'] ) ? sanitize_text_field( $data['serial_number'] ) : null,
				'certificate_number' => isset( $data['certificate_number'] ) ? sanitize_text_field( $data['certificate_number'] ) : null,
				'purity' => isset( $data['purity'] ) ? sanitize_text_field( $data['purity'] ) : null,
				'weight' => isset( $data['weight'] ) ? sanitize_text_field( $data['weight'] ) : null,
				'appraiser_id' => isset( $data['appraiser_id'] ) ? absint( $data['appraiser_id'] ) : null,
				'restricted' => isset( $data['restricted'] ) ? 1 : 0,
				'restriction_terms' => isset( $data['restriction_terms'] ) ? wp_kses_post( $data['restriction_terms'] ) : null,
				'notes' => isset( $data['notes'] ) ? wp_kses_post( $data['notes'] ) : null,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to record alternative asset', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'alternative_assets' );
		return $wpdb->insert_id;
	}

	/**
	 * Get alternative assets
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error Array of assets or error
	 */
	public static function get_assets( $args = array() ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$defaults = array(
			'asset_type' => null,
			'status' => null,
		);

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = array( '1=1' );
		$values = array();

		if ( $args['asset_type'] ) {
			$where[] = 'asset_type = %s';
			$values[] = sanitize_text_field( $args['asset_type'] );
		}

		if ( $args['status'] ) {
			$where[] = 'status = %s';
			$values[] = sanitize_text_field( $args['status'] );
		}

		$where_clause = implode( ' AND ', $where );

		// Use caching for alternative assets
		$cache_key = NonprofitSuite_Cache::list_key( 'alternative_assets', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $where_clause, $values, $args ) {
			$orderby = sanitize_sql_orderby( "{$args['orderby']} {$args['order']}" );

			$sql = "SELECT id, donation_id, in_kind_donation_id, asset_type, asset_subtype, description,
			               quantity, unit, date_received, valuation_date, valuation_method, fair_market_value,
			               cost_basis, donor_holding_period, current_value, last_valued_date, status,
			               handling_policy, custody_location, wallet_address, blockchain, serial_number,
			               certificate_number, purity, weight, appraiser_id, restricted, restriction_terms,
			               notes, liquidation_date, liquidation_value, liquidation_method, liquidation_fees,
			               net_proceeds, gain_loss, created_at
			        FROM {$wpdb->prefix}ns_alternative_assets
					WHERE $where_clause
					ORDER BY $orderby
					" . NonprofitSuite_Utilities::build_limit_clause( $args );

			if ( ! empty( $values ) ) {
				$sql = $wpdb->prepare( $sql, $values );
			}

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	/**
	 * Get single asset
	 *
	 * @param int $asset_id Asset ID
	 * @return object|null Asset object or null
	 */
	public static function get_asset( $asset_id ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return null;
		}

		global $wpdb;

		// Use caching for single alternative asset
		$cache_key = NonprofitSuite_Cache::item_key( 'alternative_asset', $asset_id );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $asset_id ) {
			return $wpdb->get_row( $wpdb->prepare(
				"SELECT id, donation_id, in_kind_donation_id, asset_type, asset_subtype, description,
				        quantity, unit, date_received, valuation_date, valuation_method, fair_market_value,
				        cost_basis, donor_holding_period, current_value, last_valued_date, status,
				        handling_policy, custody_location, wallet_address, blockchain, serial_number,
				        certificate_number, purity, weight, appraiser_id, restricted, restriction_terms,
				        notes, liquidation_date, liquidation_value, liquidation_method, liquidation_fees,
				        net_proceeds, gain_loss, created_at
				 FROM {$wpdb->prefix}ns_alternative_assets WHERE id = %d",
				absint( $asset_id )
			) );
		}, 300 );
	}

	/**
	 * Update asset value
	 *
	 * @param int   $asset_id Asset ID
	 * @param float $new_value New current value
	 * @return bool|WP_Error True on success or error
	 */
	public static function update_value( $asset_id, $new_value ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_finances();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$result = $wpdb->update(
			$wpdb->prefix . 'ns_alternative_assets',
			array(
				'current_value' => floatval( $new_value ),
				'last_valued_date' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $asset_id ) ),
			array( '%f', '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			NonprofitSuite_Cache::invalidate_module( 'alternative_assets' );
			NonprofitSuite_Cache::delete( NonprofitSuite_Cache::item_key( 'alternative_asset', $asset_id ) );
		}

		return $result !== false;
	}

	/**
	 * Liquidate asset (convert to cash)
	 *
	 * @param int   $asset_id Asset ID
	 * @param array $data Liquidation data
	 * @return int|WP_Error Treasury transaction ID or error
	 */
	public static function liquidate_asset( $asset_id, $data ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$asset = self::get_asset( $asset_id );
		if ( ! $asset ) {
			return new WP_Error( 'not_found', __( 'Asset not found', 'nonprofitsuite' ) );
		}

		// Calculate gain/loss
		$liquidation_value = floatval( $data['liquidation_value'] );
		$liquidation_fees = isset( $data['liquidation_fees'] ) ? floatval( $data['liquidation_fees'] ) : 0;
		$net_proceeds = $liquidation_value - $liquidation_fees;
		$gain_loss = $net_proceeds - floatval( $asset->fair_market_value );

		// Update asset record
		$result = $wpdb->update(
			$wpdb->prefix . 'ns_alternative_assets',
			array(
				'status' => 'liquidated',
				'liquidation_date' => isset( $data['liquidation_date'] ) ? sanitize_text_field( $data['liquidation_date'] ) : current_time( 'mysql' ),
				'liquidation_value' => $liquidation_value,
				'liquidation_method' => sanitize_text_field( $data['liquidation_method'] ),
				'liquidation_fees' => $liquidation_fees,
				'net_proceeds' => $net_proceeds,
				'gain_loss' => $gain_loss,
			),
			array( 'id' => absint( $asset_id ) ),
			array( '%s', '%s', '%f', '%s', '%f', '%f', '%f' ),
			array( '%d' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to liquidate asset', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'alternative_assets' );
		NonprofitSuite_Cache::delete( NonprofitSuite_Cache::item_key( 'alternative_asset', $asset_id ) );

		// Record treasury transaction
		if ( class_exists( 'NonprofitSuite_Treasury' ) ) {
			$transaction_id = NonprofitSuite_Treasury::add_transaction( array(
				'type' => 'income',
				'category' => 'Donations',
				'amount' => $net_proceeds,
				'date' => isset( $data['liquidation_date'] ) ? $data['liquidation_date'] : current_time( 'Y-m-d' ),
				'description' => sprintf( __( 'Liquidation of %s (%s)', 'nonprofitsuite' ), $asset->asset_type, $asset->description ),
				'notes' => sprintf( __( 'FMV: $%.2f, Liquidation: $%.2f, Fees: $%.2f, Gain/Loss: $%.2f', 'nonprofitsuite' ),
					$asset->fair_market_value,
					$liquidation_value,
					$liquidation_fees,
					$gain_loss
				),
			) );

			return $transaction_id;
		}

		return absint( $asset_id );
	}

	/**
	 * Get portfolio summary
	 *
	 * @return array Portfolio summary with totals by asset type
	 */
	public static function get_portfolio_summary() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		$summary = $wpdb->get_results(
			"SELECT
				asset_type,
				COUNT(*) as count,
				SUM(fair_market_value) as total_fmv,
				SUM(current_value) as total_current_value,
				SUM(CASE WHEN status = 'held' THEN current_value ELSE 0 END) as total_held_value
			FROM {$wpdb->prefix}ns_alternative_assets
			GROUP BY asset_type
			ORDER BY total_held_value DESC"
		);

		return $summary;
	}

	/**
	 * Get assets by donor holding period (for tax reporting)
	 *
	 * @param string $holding_period Holding period (long_term, short_term, unknown)
	 * @return array Array of assets
	 */
	public static function get_assets_by_holding_period( $holding_period ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		// Use caching for assets by holding period
		$cache_key = NonprofitSuite_Cache::list_key( 'alternative_assets_by_holding', array( 'holding_period' => $holding_period ) );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $holding_period ) {
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT id, donation_id, in_kind_donation_id, asset_type, asset_subtype, description,
				        quantity, unit, date_received, valuation_date, valuation_method, fair_market_value,
				        cost_basis, donor_holding_period, current_value, last_valued_date, status,
				        handling_policy, custody_location, wallet_address, blockchain, serial_number,
				        certificate_number, purity, weight, appraiser_id, restricted, restriction_terms,
				        notes, liquidation_date, liquidation_value, liquidation_method, liquidation_fees,
				        net_proceeds, gain_loss, created_at
				 FROM {$wpdb->prefix}ns_alternative_assets
				WHERE donor_holding_period = %s
				ORDER BY date_received DESC",
				sanitize_text_field( $holding_period )
			) );
		}, 300 );
	}

	/**
	 * Get Schedule D data for Form 990
	 *
	 * @param string $fiscal_year_end Fiscal year end date (YYYY-MM-DD)
	 * @return array Schedule D data
	 */
	public static function get_schedule_d_data( $fiscal_year_end ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		// Use caching for Schedule D data
		$cache_key = NonprofitSuite_Cache::item_key( 'alternative_assets_schedule_d', $fiscal_year_end );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $fiscal_year_end ) {
			$fiscal_year_start = date( 'Y-m-d', strtotime( $fiscal_year_end . ' -1 year +1 day' ) );

			// Assets received during fiscal year
			$received = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, donation_id, in_kind_donation_id, asset_type, asset_subtype, description,
				        quantity, unit, date_received, valuation_date, valuation_method, fair_market_value,
				        cost_basis, donor_holding_period, current_value, last_valued_date, status,
				        handling_policy, custody_location, wallet_address, blockchain, serial_number,
				        certificate_number, purity, weight, appraiser_id, restricted, restriction_terms,
				        notes, liquidation_date, liquidation_value, liquidation_method, liquidation_fees,
				        net_proceeds, gain_loss, created_at
				 FROM {$wpdb->prefix}ns_alternative_assets
				WHERE date_received BETWEEN %s AND %s
				ORDER BY asset_type, date_received",
				$fiscal_year_start,
				$fiscal_year_end
			) );

			// Assets liquidated during fiscal year
			$liquidated = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, donation_id, in_kind_donation_id, asset_type, asset_subtype, description,
				        quantity, unit, date_received, valuation_date, valuation_method, fair_market_value,
				        cost_basis, donor_holding_period, current_value, last_valued_date, status,
				        handling_policy, custody_location, wallet_address, blockchain, serial_number,
				        certificate_number, purity, weight, appraiser_id, restricted, restriction_terms,
				        notes, liquidation_date, liquidation_value, liquidation_method, liquidation_fees,
				        net_proceeds, gain_loss, created_at
				 FROM {$wpdb->prefix}ns_alternative_assets
				WHERE liquidation_date BETWEEN %s AND %s
				ORDER BY asset_type, liquidation_date",
				$fiscal_year_start,
				$fiscal_year_end
			) );

			// Assets held at end of fiscal year
			$held = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, donation_id, in_kind_donation_id, asset_type, asset_subtype, description,
				        quantity, unit, date_received, valuation_date, valuation_method, fair_market_value,
				        cost_basis, donor_holding_period, current_value, last_valued_date, status,
				        handling_policy, custody_location, wallet_address, blockchain, serial_number,
				        certificate_number, purity, weight, appraiser_id, restricted, restriction_terms,
				        notes, liquidation_date, liquidation_value, liquidation_method, liquidation_fees,
				        net_proceeds, gain_loss, created_at
				 FROM {$wpdb->prefix}ns_alternative_assets
				WHERE status = 'held'
				AND date_received <= %s
				ORDER BY asset_type, date_received",
				$fiscal_year_end
			) );

			return array(
				'received' => $received,
				'liquidated' => $liquidated,
				'held' => $held,
				'total_received_fmv' => array_sum( wp_list_pluck( $received, 'fair_market_value' ) ),
				'total_liquidated_proceeds' => array_sum( wp_list_pluck( $liquidated, 'net_proceeds' ) ),
				'total_held_value' => array_sum( wp_list_pluck( $held, 'current_value' ) ),
			);
		}, 300 );
	}

	/**
	 * Delete asset
	 *
	 * @param int $asset_id Asset ID
	 * @return bool|WP_Error True on success or error
	 */
	public static function delete_asset( $asset_id ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_finances();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$result = $wpdb->delete(
			$wpdb->prefix . 'ns_alternative_assets',
			array( 'id' => absint( $asset_id ) ),
			array( '%d' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to delete asset', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'alternative_assets' );
		NonprofitSuite_Cache::delete( NonprofitSuite_Cache::item_key( 'alternative_asset', $asset_id ) );

		return true;
	}

	/**
	 * Get cryptocurrency subtypes
	 *
	 * @return array Crypto subtypes
	 */
	public static function get_crypto_subtypes() {
		return array(
			'Bitcoin (BTC)',
			'Ethereum (ETH)',
			'Litecoin (LTC)',
			'Ripple (XRP)',
			'Cardano (ADA)',
			'Solana (SOL)',
			'Dogecoin (DOGE)',
			'Polkadot (DOT)',
			'USD Coin (USDC)',
			'Other'
		);
	}

	/**
	 * Get precious metals subtypes
	 *
	 * @return array Metal subtypes
	 */
	public static function get_metals_subtypes() {
		return array(
			'Gold Bullion',
			'Silver Coins',
			'Gold Eagle Coins',
			'Platinum Bars',
			'Palladium',
			'Gold Jewelry',
			'Other'
		);
	}

	/**
	 * Get valuation methods
	 *
	 * @return array Valuation methods
	 */
	public static function get_valuation_methods() {
		return array(
			'exchange_price' => 'Exchange Price (Cryptocurrency)',
			'dealer_quote' => 'Dealer Quote (Precious Metals)',
			'professional_appraisal' => 'Professional Appraisal',
			'comparable_sales' => 'Comparable Sales',
			'third_party_service' => 'Third-Party Valuation Service',
			'face_value' => 'Face Value',
		);
	}
}
