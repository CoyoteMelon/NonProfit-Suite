<?php
/**
 * Chapter & Affiliate Management Module
 *
 * @package NonprofitSuite
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NonprofitSuite_Chapters {

	/**
	 * Create chapter/affiliate
	 *
	 * @param array $data Chapter data
	 * @return int|WP_Error Chapter ID or error
	 */
	public static function create_chapter( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'manage_options', 'manage chapters' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_chapters',
			array(
				'chapter_name' => sanitize_text_field( $data['chapter_name'] ),
				'chapter_number' => isset( $data['chapter_number'] ) ? sanitize_text_field( $data['chapter_number'] ) : null,
				'chapter_type' => isset( $data['chapter_type'] ) ? sanitize_text_field( $data['chapter_type'] ) : 'chapter',
				'state_code' => isset( $data['state_code'] ) ? sanitize_text_field( $data['state_code'] ) : null,
				'city' => isset( $data['city'] ) ? sanitize_text_field( $data['city'] ) : null,
				'established_date' => isset( $data['established_date'] ) ? sanitize_text_field( $data['established_date'] ) : null,
				'status' => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'active',
				'ein' => isset( $data['ein'] ) ? sanitize_text_field( $data['ein'] ) : null,
				'president_id' => isset( $data['president_id'] ) ? absint( $data['president_id'] ) : null,
				'contact_email' => isset( $data['contact_email'] ) ? sanitize_email( $data['contact_email'] ) : null,
				'contact_phone' => isset( $data['contact_phone'] ) ? sanitize_text_field( $data['contact_phone'] ) : null,
				'website_url' => isset( $data['website_url'] ) ? esc_url_raw( $data['website_url'] ) : null,
				'member_count' => isset( $data['member_count'] ) ? absint( $data['member_count'] ) : 0,
				'notes' => isset( $data['notes'] ) ? wp_kses_post( $data['notes'] ) : null,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to create chapter', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'chapters' );
		$chapter_id = $wpdb->insert_id;

		// Auto-add state compliance if state is provided
		if ( ! empty( $data['state_code'] ) && class_exists( 'NonprofitSuite_State_Compliance' ) ) {
			NonprofitSuite_State_Compliance::add_state_operation( array(
				'state_code' => $data['state_code'],
				'state_name' => self::get_state_name( $data['state_code'] ),
				'operation_type' => 'chapter',
				'notes' => 'Automatically created for ' . $data['chapter_name'],
			) );
		}

		return $chapter_id;
	}

	/**
	 * Get chapters
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error Array of chapters or error
	 */
	public static function get_chapters( $args = array() ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$defaults = array(
			'status' => null,
			'chapter_type' => null,
			'state_code' => null,
			'limit' => 100,
			'offset' => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );
		$values = array();

		if ( $args['status'] ) {
			$where[] = 'status = %s';
			$values[] = sanitize_text_field( $args['status'] );
		}

		if ( $args['chapter_type'] ) {
			$where[] = 'chapter_type = %s';
			$values[] = sanitize_text_field( $args['chapter_type'] );
		}

		if ( $args['state_code'] ) {
			$where[] = 'state_code = %s';
			$values[] = sanitize_text_field( $args['state_code'] );
		}

		$sql = "SELECT id, chapter_name, chapter_number, chapter_type, state_code, city,
		               established_date, status, ein, president_id, contact_email, contact_phone,
		               website_url, member_count, notes, created_at
		        FROM {$wpdb->prefix}ns_chapters
				WHERE " . implode( ' AND ', $where ) . "
				ORDER BY chapter_name ASC
				LIMIT %d OFFSET %d";

		$values[] = absint( $args['limit'] );
		$values[] = absint( $args['offset'] );

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values );
		}

		return $wpdb->get_results( $sql );
	}

	/**
	 * Get state name from code
	 *
	 * @param string $code State code
	 * @return string State name
	 */
	private static function get_state_name( $code ) {
		$states = array(
			'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
			'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'FL' => 'Florida', 'GA' => 'Georgia',
			'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
			'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
			'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri',
			'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey',
			'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
			'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
			'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont',
			'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
			'DC' => 'District of Columbia',
		);

		return $states[ strtoupper( $code ) ] ?? $code;
	}

	/**
	 * Record chapter financials
	 *
	 * @param array $data Financial data
	 * @return int|WP_Error Financial record ID or error
	 */
	public static function record_financials( $data ) {
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
			$wpdb->prefix . 'ns_chapter_financials',
			array(
				'chapter_id' => absint( $data['chapter_id'] ),
				'fiscal_year' => absint( $data['fiscal_year'] ),
				'revenue' => isset( $data['revenue'] ) ? floatval( $data['revenue'] ) : 0,
				'expenses' => isset( $data['expenses'] ) ? floatval( $data['expenses'] ) : 0,
				'net_assets' => isset( $data['net_assets'] ) ? floatval( $data['net_assets'] ) : 0,
				'dues_collected' => isset( $data['dues_collected'] ) ? floatval( $data['dues_collected'] ) : 0,
				'grants_awarded' => isset( $data['grants_awarded'] ) ? floatval( $data['grants_awarded'] ) : 0,
				'report_date' => isset( $data['report_date'] ) ? sanitize_text_field( $data['report_date'] ) : null,
				'audit_status' => isset( $data['audit_status'] ) ? sanitize_text_field( $data['audit_status'] ) : null,
				'notes' => isset( $data['notes'] ) ? wp_kses_post( $data['notes'] ) : null,
			),
			array( '%d', '%d', '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%s' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to record financials', 'nonprofitsuite' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get consolidated financial summary
	 *
	 * @param int $fiscal_year Fiscal year
	 * @return array Financial summary
	 */
	public static function get_consolidated_financials( $fiscal_year ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT
				COUNT(DISTINCT chapter_id) as chapter_count,
				SUM(revenue) as total_revenue,
				SUM(expenses) as total_expenses,
				SUM(net_assets) as total_net_assets,
				SUM(dues_collected) as total_dues
			FROM {$wpdb->prefix}ns_chapter_financials
			WHERE fiscal_year = %d",
			absint( $fiscal_year )
		), ARRAY_A );
	}

	/**
	 * Get dashboard data
	 *
	 * @return array Dashboard metrics
	 */
	public static function get_dashboard_data() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		$active_chapters = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ns_chapters WHERE status = 'active'"
		);

		$total_members = $wpdb->get_var(
			"SELECT SUM(member_count) FROM {$wpdb->prefix}ns_chapters WHERE status = 'active'"
		);

		$chapters_by_state = $wpdb->get_results(
			"SELECT state_code, COUNT(*) as count
			FROM {$wpdb->prefix}ns_chapters
			WHERE status = 'active' AND state_code IS NOT NULL
			GROUP BY state_code
			ORDER BY count DESC"
		);

		return array(
			'active_chapters' => absint( $active_chapters ),
			'total_members' => absint( $total_members ),
			'chapters_by_state' => $chapters_by_state,
		);
	}
}
