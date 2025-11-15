<?php
/**
 * Utilities Helper
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/helpers
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class NonprofitSuite_Utilities {

	/**
	 * Format date for display.
	 *
	 * @param string $date Date string.
	 * @param string $format Date format.
	 * @return string Formatted date.
	 */
	public static function format_date( $date, $format = 'F j, Y' ) {
		if ( empty( $date ) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00' ) {
			return '-';
		}

		return date_i18n( $format, strtotime( $date ) );
	}

	/**
	 * Format datetime for display.
	 *
	 * @param string $datetime Datetime string.
	 * @return string Formatted datetime.
	 */
	public static function format_datetime( $datetime ) {
		return self::format_date( $datetime, 'F j, Y g:i A' );
	}

	/**
	 * Get user display name.
	 *
	 * @param int $user_id User ID.
	 * @return string User display name.
	 */
	public static function get_user_display_name( $user_id ) {
		if ( ! $user_id ) {
			return '-';
		}

		$user = get_userdata( $user_id );
		return $user ? $user->display_name : '-';
	}

	/**
	 * Get status badge HTML.
	 *
	 * @param string $status Status value.
	 * @param string $type Status type (meeting, task, etc.).
	 * @return string HTML badge.
	 */
	public static function get_status_badge( $status, $type = 'general' ) {
		$colors = array(
			'scheduled' => 'blue',
			'completed' => 'green',
			'cancelled' => 'red',
			'draft' => 'gray',
			'approved' => 'green',
			'not_started' => 'gray',
			'in_progress' => 'yellow',
			'active' => 'green',
		);

		$color = isset( $colors[ $status ] ) ? $colors[ $status ] : 'gray';

		$class_map = array(
			'blue' => 'bg-blue-100 text-blue-800',
			'green' => 'bg-green-100 text-green-800',
			'red' => 'bg-red-100 text-red-800',
			'gray' => 'bg-gray-100 text-gray-800',
			'yellow' => 'bg-yellow-100 text-yellow-800',
		);

		$class = $class_map[ $color ];

		return sprintf(
			'<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium %s">%s</span>',
			esc_attr( $class ),
			esc_html( ucwords( str_replace( '_', ' ', $status ) ) )
		);
	}

	/**
	 * Get priority badge HTML.
	 *
	 * @param string $priority Priority value.
	 * @return string HTML badge.
	 */
	public static function get_priority_badge( $priority ) {
		$colors = array(
			'low' => 'green',
			'medium' => 'yellow',
			'high' => 'red',
		);

		$color = isset( $colors[ $priority ] ) ? $colors[ $priority ] : 'gray';

		$class_map = array(
			'green' => 'bg-green-100 text-green-800',
			'yellow' => 'bg-yellow-100 text-yellow-800',
			'red' => 'bg-red-100 text-red-800',
			'gray' => 'bg-gray-100 text-gray-800',
		);

		$class = $class_map[ $color ];

		return sprintf(
			'<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium %s">%s</span>',
			esc_attr( $class ),
			esc_html( ucfirst( $priority ) )
		);
	}

	/**
	 * Sanitize meeting data.
	 *
	 * @param array $data Raw meeting data.
	 * @return array Sanitized data.
	 */
	public static function sanitize_meeting_data( $data ) {
		return array(
			'title' => isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '',
			'meeting_type' => isset( $data['meeting_type'] ) ? sanitize_text_field( $data['meeting_type'] ) : 'board',
			'meeting_date' => isset( $data['meeting_date'] ) ? sanitize_text_field( $data['meeting_date'] ) : '',
			'location' => isset( $data['location'] ) ? sanitize_text_field( $data['location'] ) : '',
			'virtual_url' => isset( $data['virtual_url'] ) ? esc_url_raw( $data['virtual_url'] ) : '',
			'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
			'status' => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'scheduled',
			'quorum_required' => isset( $data['quorum_required'] ) ? absint( $data['quorum_required'] ) : null,
		);
	}

	/**
	 * Generate nonce for AJAX requests.
	 *
	 * @return string Nonce value.
	 */
	public static function get_ajax_nonce() {
		return wp_create_nonce( 'nonprofitsuite_nonce' );
	}

	/**
	 * Check if setup is complete.
	 *
	 * @return bool True if setup is complete.
	 */
	public static function is_setup_complete() {
		return (bool) get_option( 'nonprofitsuite_setup_complete', false );
	}

	/**
	 * Parse pagination arguments with defaults.
	 *
	 * @param array $args Query arguments.
	 * @return array Parsed arguments with pagination.
	 */
	public static function parse_pagination_args( $args = array() ) {
		$defaults = array(
			'page'     => 1,
			'per_page' => apply_filters( 'nonprofitsuite_default_per_page', 50 ),
			'orderby'  => 'id',
			'order'    => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		// Allow customization of max per_page limit
		$max_per_page = apply_filters( 'nonprofitsuite_max_per_page', 200 );

		// Sanitize
		$args['page']     = max( 1, absint( $args['page'] ) );
		$args['per_page'] = max( 1, min( $max_per_page, absint( $args['per_page'] ) ) );
		$args['orderby']  = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
		$args['offset']   = ( $args['page'] - 1 ) * $args['per_page'];

		// Allow modification of final pagination args
		return apply_filters( 'nonprofitsuite_pagination_args', $args );
	}

	/**
	 * Build LIMIT clause for pagination.
	 *
	 * @param array $args Pagination arguments.
	 * @return string SQL LIMIT clause.
	 */
	public static function build_limit_clause( $args ) {
		global $wpdb;
		return $wpdb->prepare( 'LIMIT %d OFFSET %d', $args['per_page'], $args['offset'] );
	}

	/**
	 * Get pagination info for API responses.
	 *
	 * @param int   $total_count Total number of items.
	 * @param array $args        Pagination arguments.
	 * @return array Pagination metadata.
	 */
	public static function get_pagination_meta( $total_count, $args ) {
		return array(
			'total'        => (int) $total_count,
			'per_page'     => (int) $args['per_page'],
			'current_page' => (int) $args['page'],
			'total_pages'  => (int) ceil( $total_count / $args['per_page'] ),
			'has_more'     => ( $args['page'] * $args['per_page'] ) < $total_count,
		);
	}
}
