<?php
/**
 * Public Metrics Module
 *
 * Share already-public data to create sector benchmarks.
 * Provides transparency while protecting privacy.
 *
 * @package NonprofitSuite
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NonprofitSuite_Public_Metrics {

	/**
	 * Calculate annual metrics for a given fiscal year
	 *
	 * @param int $year Fiscal year
	 * @return array|WP_Error Calculated metrics or error
	 */
	public static function calculate_annual_metrics( $year ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		// Cache the expensive metrics calculation (TTL: 1 hour)
		$cache_key = NonprofitSuite_Cache::item_key( 'calculated_metrics', $year );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $year ) {
			return self::calculate_annual_metrics_uncached( $year );
		}, 3600 );
	}

	/**
	 * Calculate annual metrics without caching (internal method)
	 *
	 * @param int $year Fiscal year
	 * @return array Calculated metrics
	 */
	private static function calculate_annual_metrics_uncached( $year ) {
		global $wpdb;
		$settings = get_option( 'nonprofitsuite_settings', array() );

		// Determine fiscal year dates
		$fiscal_year_end = isset( $settings['fiscal_year_end'] ) ? $settings['fiscal_year_end'] : '12-31';
		list( $month, $day ) = explode( '-', $fiscal_year_end );
		$fy_start = date( 'Y-m-d', strtotime( ( $year - 1 ) . '-' . $month . '-' . $day . ' +1 day' ) );
		$fy_end   = date( 'Y-m-d', strtotime( $year . '-' . $month . '-' . $day ) );

		$metrics = array(
			'year'                    => absint( $year ),
			'organization_name'       => isset( $settings['organization_name'] ) ? $settings['organization_name'] : '',
			'year_founded'            => isset( $settings['year_founded'] ) ? absint( $settings['year_founded'] ) : null,
			'organization_type'       => isset( $settings['organization_type'] ) ? sanitize_text_field( $settings['organization_type'] ) : '',
		);

		// State operations
		if ( class_exists( 'NonprofitSuite_State_Compliance' ) ) {
			$states = NonprofitSuite_State_Compliance::get_state_operations();
			if ( ! empty( $states ) ) {
				$state_list                   = array_column( $states, 'state_code' );
				$metrics['states_operating']  = implode( ',', $state_list );
				$metrics['states_count']      = count( $state_list );
			} else {
				$metrics['states_operating']  = '';
				$metrics['states_count']      = 0;
			}
		}

		// Financial data from Treasury
		if ( class_exists( 'NonprofitSuite_Treasury' ) ) {
			// Total revenue (income)
			$total_revenue = $wpdb->get_var( $wpdb->prepare(
				"SELECT SUM(amount) FROM {$wpdb->prefix}ns_treasury
				WHERE type = 'income' AND date BETWEEN %s AND %s",
				$fy_start,
				$fy_end
			) );

			// Total expenses
			$total_expenses = $wpdb->get_var( $wpdb->prepare(
				"SELECT SUM(amount) FROM {$wpdb->prefix}ns_treasury
				WHERE type = 'expense' AND date BETWEEN %s AND %s",
				$fy_start,
				$fy_end
			) );

			// Program expenses
			$program_expenses = $wpdb->get_var( $wpdb->prepare(
				"SELECT SUM(amount) FROM {$wpdb->prefix}ns_treasury
				WHERE type = 'expense' AND category = 'Program Expenses' AND date BETWEEN %s AND %s",
				$fy_start,
				$fy_end
			) );

			// Fundraising expenses
			$fundraising_expenses = $wpdb->get_var( $wpdb->prepare(
				"SELECT SUM(amount) FROM {$wpdb->prefix}ns_treasury
				WHERE type = 'expense' AND category = 'Fundraising' AND date BETWEEN %s AND %s",
				$fy_start,
				$fy_end
			) );

			// Administrative expenses
			$admin_expenses = $wpdb->get_var( $wpdb->prepare(
				"SELECT SUM(amount) FROM {$wpdb->prefix}ns_treasury
				WHERE type = 'expense' AND category IN ('Administrative', 'Management & General') AND date BETWEEN %s AND %s",
				$fy_start,
				$fy_end
			) );

			// Total assets (current balance)
			$total_assets = $wpdb->get_var( "SELECT SUM(amount) FROM {$wpdb->prefix}ns_treasury WHERE type = 'income'" ) -
			                $wpdb->get_var( "SELECT SUM(amount) FROM {$wpdb->prefix}ns_treasury WHERE type = 'expense'" );

			$metrics['total_revenue']         = floatval( $total_revenue );
			$metrics['total_expenses']        = floatval( $total_expenses );
			$metrics['program_expenses']      = floatval( $program_expenses );
			$metrics['fundraising_expenses']  = floatval( $fundraising_expenses );
			$metrics['admin_expenses']        = floatval( $admin_expenses );
			$metrics['total_assets']          = floatval( $total_assets );

			// Calculate ratios
			if ( $total_expenses > 0 ) {
				$metrics['program_expense_ratio']   = round( ( $program_expenses / $total_expenses ) * 100, 2 );
				$metrics['fundraising_efficiency']  = round( ( $fundraising_expenses / $total_expenses ) * 100, 2 );
				$metrics['admin_overhead']          = round( ( $admin_expenses / $total_expenses ) * 100, 2 );
			} else {
				$metrics['program_expense_ratio']   = 0;
				$metrics['fundraising_efficiency']  = 0;
				$metrics['admin_overhead']          = 0;
			}
		}

		// Employee and volunteer counts
		$metrics['employee_count'] = isset( $settings['employee_count'] ) ? absint( $settings['employee_count'] ) : 0;

		if ( class_exists( 'NonprofitSuite_Volunteers' ) ) {
			$volunteer_count = $wpdb->get_var( "SELECT COUNT(DISTINCT volunteer_id) FROM {$wpdb->prefix}ns_volunteer_hours" );
			$metrics['volunteer_count'] = absint( $volunteer_count );
		} else {
			$metrics['volunteer_count'] = 0;
		}

		// Board meetings
		$meeting_count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ns_meetings WHERE meeting_date BETWEEN %s AND %s",
			$fy_start,
			$fy_end
		) );
		$metrics['board_meetings_held'] = absint( $meeting_count );

		// Programs count
		if ( class_exists( 'NonprofitSuite_Programs' ) ) {
			$program_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ns_programs WHERE status = 'active'" );
			$metrics['programs_count'] = absint( $program_count );
		} else {
			$metrics['programs_count'] = 0;
		}

		// Grants received (count only)
		if ( class_exists( 'NonprofitSuite_Grants' ) ) {
			$grants_count = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ns_grants WHERE status = 'awarded' AND award_date BETWEEN %s AND %s",
				$fy_start,
				$fy_end
			) );
			$metrics['grants_received_count'] = absint( $grants_count );
		} else {
			$metrics['grants_received_count'] = 0;
		}

		// Donors (count only - NO names or amounts)
		if ( class_exists( 'NonprofitSuite_Donors' ) ) {
			$donor_count = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(DISTINCT donor_id) FROM {$wpdb->prefix}ns_donations WHERE donation_date BETWEEN %s AND %s",
				$fy_start,
				$fy_end
			) );
			$metrics['donor_count'] = absint( $donor_count );
		} else {
			$metrics['donor_count'] = 0;
		}

		return $metrics;
	}

	/**
	 * Save annual metrics
	 *
	 * @param array $metrics Metrics data
	 * @return int|WP_Error Metrics ID or error
	 */
	public static function save_metrics( $metrics ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'manage_options', 'manage public metrics' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		// Check if metrics for this year already exist
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}ns_public_metrics WHERE year = %d",
			absint( $metrics['year'] )
		) );

		$data = array(
			'year'                     => absint( $metrics['year'] ),
			'organization_name'        => sanitize_text_field( $metrics['organization_name'] ),
			'year_founded'             => isset( $metrics['year_founded'] ) ? absint( $metrics['year_founded'] ) : null,
			'organization_type'        => isset( $metrics['organization_type'] ) ? sanitize_text_field( $metrics['organization_type'] ) : null,
			'states_operating'         => isset( $metrics['states_operating'] ) ? sanitize_text_field( $metrics['states_operating'] ) : null,
			'states_count'             => isset( $metrics['states_count'] ) ? absint( $metrics['states_count'] ) : 0,
			'total_revenue'            => isset( $metrics['total_revenue'] ) ? floatval( $metrics['total_revenue'] ) : 0,
			'total_expenses'           => isset( $metrics['total_expenses'] ) ? floatval( $metrics['total_expenses'] ) : 0,
			'program_expenses'         => isset( $metrics['program_expenses'] ) ? floatval( $metrics['program_expenses'] ) : 0,
			'fundraising_expenses'     => isset( $metrics['fundraising_expenses'] ) ? floatval( $metrics['fundraising_expenses'] ) : 0,
			'admin_expenses'           => isset( $metrics['admin_expenses'] ) ? floatval( $metrics['admin_expenses'] ) : 0,
			'total_assets'             => isset( $metrics['total_assets'] ) ? floatval( $metrics['total_assets'] ) : 0,
			'program_expense_ratio'    => isset( $metrics['program_expense_ratio'] ) ? floatval( $metrics['program_expense_ratio'] ) : 0,
			'fundraising_efficiency'   => isset( $metrics['fundraising_efficiency'] ) ? floatval( $metrics['fundraising_efficiency'] ) : 0,
			'admin_overhead'           => isset( $metrics['admin_overhead'] ) ? floatval( $metrics['admin_overhead'] ) : 0,
			'employee_count'           => isset( $metrics['employee_count'] ) ? absint( $metrics['employee_count'] ) : 0,
			'volunteer_count'          => isset( $metrics['volunteer_count'] ) ? absint( $metrics['volunteer_count'] ) : 0,
			'board_meetings_held'      => isset( $metrics['board_meetings_held'] ) ? absint( $metrics['board_meetings_held'] ) : 0,
			'programs_count'           => isset( $metrics['programs_count'] ) ? absint( $metrics['programs_count'] ) : 0,
			'grants_received_count'    => isset( $metrics['grants_received_count'] ) ? absint( $metrics['grants_received_count'] ) : 0,
			'donor_count'              => isset( $metrics['donor_count'] ) ? absint( $metrics['donor_count'] ) : 0,
		);

		if ( $existing ) {
			$result = $wpdb->update(
				$wpdb->prefix . 'ns_public_metrics',
				$data,
				array( 'id' => $existing->id )
			);

			if ( $result === false ) {
				return new WP_Error( 'db_error', __( 'Failed to update metrics', 'nonprofitsuite' ) );
			}

			NonprofitSuite_Cache::invalidate_module( 'public_metrics' );
			NonprofitSuite_Cache::delete( NonprofitSuite_Cache::item_key( 'calculated_metrics', absint( $metrics['year'] ) ) );
			return $existing->id;
		} else {
			$result = $wpdb->insert(
				$wpdb->prefix . 'ns_public_metrics',
				$data
			);

			if ( $result === false ) {
				return new WP_Error( 'db_error', __( 'Failed to save metrics', 'nonprofitsuite' ) );
			}

			NonprofitSuite_Cache::invalidate_module( 'public_metrics' );
			NonprofitSuite_Cache::delete( NonprofitSuite_Cache::item_key( 'calculated_metrics', absint( $metrics['year'] ) ) );
			return $wpdb->insert_id;
		}
	}

	/**
	 * Get metrics for a specific year
	 *
	 * @param int $year Fiscal year
	 * @return object|null Metrics object or null
	 */
	public static function get_metrics( $year ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return null;
		}

		global $wpdb;

		// Use caching for public metrics
		$cache_key = NonprofitSuite_Cache::item_key( 'public_metrics', $year );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $year ) {
			return $wpdb->get_row( $wpdb->prepare(
				"SELECT id, year, organization_name, year_founded, organization_type, states_operating,
				        states_count, total_revenue, total_expenses, program_expenses, fundraising_expenses,
				        admin_expenses, total_assets, program_expense_ratio, fundraising_efficiency,
				        admin_overhead, employee_count, volunteer_count, board_meetings_held, programs_count,
				        grants_received_count, donor_count, last_submitted, created_at
				 FROM {$wpdb->prefix}ns_public_metrics WHERE year = %d",
				absint( $year )
			) );
		}, 300 );
	}

	/**
	 * Get all saved metrics
	 *
	 * @return array Array of metrics objects
	 */
	public static function get_all_metrics() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		// Use caching for all public metrics
		$cache_key = NonprofitSuite_Cache::item_key( 'public_metrics_all', 'list' );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb ) {
			return $wpdb->get_results(
				"SELECT id, year, organization_name, year_founded, organization_type, states_operating,
				        states_count, total_revenue, total_expenses, program_expenses, fundraising_expenses,
				        admin_expenses, total_assets, program_expense_ratio, fundraising_efficiency,
				        admin_overhead, employee_count, volunteer_count, board_meetings_held, programs_count,
				        grants_received_count, donor_count, last_submitted, created_at
				 FROM {$wpdb->prefix}ns_public_metrics ORDER BY year DESC"
			);
		}, 300 );
	}

	/**
	 * Set sharing preference
	 *
	 * @param bool $enabled True to enable sharing
	 * @return bool Success
	 */
	public static function set_sharing_preference( $enabled ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'manage_options', 'manage public metrics' );
		if ( is_wp_error( $permission_check ) ) {
			return false;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return false;
		}

		return update_option( 'nonprofitsuite_metrics_sharing_enabled', (bool) $enabled );
	}

	/**
	 * Get sharing preference
	 *
	 * @return bool True if sharing enabled
	 */
	public static function is_sharing_enabled() {
		return (bool) get_option( 'nonprofitsuite_metrics_sharing_enabled', false );
	}

	/**
	 * Get installation ID (for anonymous submission)
	 *
	 * @return string Installation ID
	 */
	public static function get_installation_id() {
		$installation_id = get_option( 'nonprofitsuite_installation_id' );

		if ( ! $installation_id ) {
			$installation_id = wp_generate_password( 32, false );
			update_option( 'nonprofitsuite_installation_id', $installation_id );
		}

		return $installation_id;
	}

	/**
	 * Submit metrics anonymously to aggregator
	 *
	 * @param int $year Fiscal year
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public static function submit_metrics( $year ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		if ( ! self::is_sharing_enabled() ) {
			return new WP_Error( 'sharing_disabled', __( 'Metrics sharing is not enabled', 'nonprofitsuite' ) );
		}

		$metrics = self::get_metrics( $year );

		if ( ! $metrics ) {
			return new WP_Error( 'no_metrics', __( 'No metrics found for this year', 'nonprofitsuite' ) );
		}

		// Prepare anonymous submission (NO organization name or identifying info)
		$submission = array(
			'installation_id'          => self::get_installation_id(),
			'year'                     => $metrics->year,
			'year_founded'             => $metrics->year_founded,
			'organization_type'        => $metrics->organization_type,
			'states_count'             => $metrics->states_count,
			'total_revenue'            => $metrics->total_revenue,
			'total_expenses'           => $metrics->total_expenses,
			'program_expenses'         => $metrics->program_expenses,
			'fundraising_expenses'     => $metrics->fundraising_expenses,
			'admin_expenses'           => $metrics->admin_expenses,
			'total_assets'             => $metrics->total_assets,
			'program_expense_ratio'    => $metrics->program_expense_ratio,
			'fundraising_efficiency'   => $metrics->fundraising_efficiency,
			'admin_overhead'           => $metrics->admin_overhead,
			'employee_count'           => $metrics->employee_count,
			'volunteer_count'          => $metrics->volunteer_count,
			'board_meetings_held'      => $metrics->board_meetings_held,
			'programs_count'           => $metrics->programs_count,
			'grants_received_count'    => $metrics->grants_received_count,
			'donor_count'              => $metrics->donor_count,
		);

		// Submit to aggregator API (placeholder - would be actual endpoint)
		$response = wp_remote_post(
			'https://metrics.nonprofitsuite.com/api/submit',
			array(
				'body'    => wp_json_encode( $submission ),
				'headers' => array( 'Content-Type' => 'application/json' ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Update last submitted timestamp
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'ns_public_metrics',
			array( 'last_submitted' => current_time( 'mysql' ) ),
			array( 'year' => $year )
		);

		return true;
	}

	/**
	 * Get benchmarking data
	 *
	 * @param array $filters Filters (org_type, revenue_range, states_count, etc.)
	 * @return array|WP_Error Benchmark data or error
	 */
	public static function get_benchmarks( $filters = array() ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		// Fetch from aggregator API (placeholder)
		$response = wp_remote_get(
			add_query_arg( $filters, 'https://metrics.nonprofitsuite.com/api/benchmarks' ),
			array( 'timeout' => 15 )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! $data ) {
			return new WP_Error( 'invalid_response', __( 'Invalid response from benchmarking service', 'nonprofitsuite' ) );
		}

		return $data;
	}

	/**
	 * Export metrics to array
	 *
	 * @param int $year Fiscal year
	 * @return array|WP_Error Metrics array or error
	 */
	public static function export_metrics( $year ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		$metrics = self::get_metrics( $year );

		if ( ! $metrics ) {
			return new WP_Error( 'no_metrics', __( 'No metrics found for this year', 'nonprofitsuite' ) );
		}

		return (array) $metrics;
	}

	/**
	 * Get public display widget data
	 *
	 * @param int $year Fiscal year
	 * @return array|WP_Error Widget data or error
	 */
	public static function get_widget_data( $year ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		$metrics = self::get_metrics( $year );

		if ( ! $metrics ) {
			return new WP_Error( 'no_metrics', __( 'No metrics found for this year', 'nonprofitsuite' ) );
		}

		// Return public-safe data for widget display
		return array(
			'organization_name'       => $metrics->organization_name,
			'year'                    => $metrics->year,
			'total_revenue'           => $metrics->total_revenue,
			'total_expenses'          => $metrics->total_expenses,
			'program_expense_ratio'   => $metrics->program_expense_ratio,
			'fundraising_efficiency'  => $metrics->fundraising_efficiency,
			'admin_overhead'          => $metrics->admin_overhead,
			'employee_count'          => $metrics->employee_count,
			'volunteer_count'         => $metrics->volunteer_count,
			'board_meetings_held'     => $metrics->board_meetings_held,
			'programs_count'          => $metrics->programs_count,
		);
	}

	/**
	 * Generate embed code for public metrics widget
	 *
	 * @param int $year Fiscal year
	 * @return string HTML embed code
	 */
	public static function generate_embed_code( $year ) {
		$widget_data = self::get_widget_data( $year );

		if ( is_wp_error( $widget_data ) ) {
			return '';
		}

		$embed = '<div class="nonprofitsuite-metrics-widget">';
		$embed .= '<h3>' . esc_html( $widget_data['organization_name'] ) . ' - ' . esc_html( $widget_data['year'] ) . ' Metrics</h3>';
		$embed .= '<ul>';
		$embed .= '<li>Total Revenue: $' . number_format( $widget_data['total_revenue'], 2 ) . '</li>';
		$embed .= '<li>Total Expenses: $' . number_format( $widget_data['total_expenses'], 2 ) . '</li>';
		$embed .= '<li>Program Expense Ratio: ' . esc_html( $widget_data['program_expense_ratio'] ) . '%</li>';
		$embed .= '<li>Fundraising Efficiency: ' . esc_html( $widget_data['fundraising_efficiency'] ) . '%</li>';
		$embed .= '<li>Admin Overhead: ' . esc_html( $widget_data['admin_overhead'] ) . '%</li>';
		$embed .= '<li>Employees: ' . esc_html( $widget_data['employee_count'] ) . '</li>';
		$embed .= '<li>Volunteers: ' . esc_html( $widget_data['volunteer_count'] ) . '</li>';
		$embed .= '<li>Board Meetings: ' . esc_html( $widget_data['board_meetings_held'] ) . '</li>';
		$embed .= '<li>Programs: ' . esc_html( $widget_data['programs_count'] ) . '</li>';
		$embed .= '</ul>';
		$embed .= '<p><small>Powered by NonprofitSuite</small></p>';
		$embed .= '</div>';

		return $embed;
	}
}
