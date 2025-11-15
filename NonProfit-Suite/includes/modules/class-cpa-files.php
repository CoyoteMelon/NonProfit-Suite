<?php
/**
 * CPA File Sharing Module
 *
 * Manage document sharing between nonprofit and CPA,
 * track downloads, and organize by category.
 *
 * @package    NonprofitSuite
 * @subpackage Modules
 * @since      2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_CPA_Files {

	/**
	 * Share file with CPA
	 *
	 * @param array $data File sharing data
	 * @return int|WP_Error Shared file ID or error
	 */
	public static function share_file( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_cpa_access();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		// Validate file path to prevent directory traversal
		$validated_path = NonprofitSuite_Security::validate_file_path( $data['file_path'] );
		if ( is_wp_error( $validated_path ) ) {
			return $validated_path;
		}

		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_cpa_shared_files',
			array(
				'access_id' => absint( $data['access_id'] ),
				'file_name' => sanitize_text_field( $data['file_name'] ),
				'file_type' => sanitize_text_field( $data['file_type'] ),
				'file_path' => $validated_path,
				'file_size' => absint( $data['file_size'] ),
				'category' => isset( $data['category'] ) ? sanitize_text_field( $data['category'] ) : null,
				'description' => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : null,
				'shared_by' => absint( $data['shared_by'] ),
				'shared_date' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to share file', 'nonprofitsuite' ) );
		}

		// Invalidate only list caches, not all caches
		NonprofitSuite_Cache::invalidate_lists( 'cpa_shared_files' );
		return $wpdb->insert_id;
	}

	/**
	 * Get shared files
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error Array of shared files or error
	 */
	public static function get_shared_files( $args = array() ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$defaults = array(
			'access_id' => null,
			'category' => null,
		);

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = array( '1=1' );
		$values = array();

		if ( $args['access_id'] ) {
			$where[] = 'access_id = %d';
			$values[] = absint( $args['access_id'] );
		}

		if ( $args['category'] ) {
			$where[] = 'category = %s';
			$values[] = sanitize_text_field( $args['category'] );
		}

		$where_clause = implode( ' AND ', $where );

		// Use caching for shared files
		$cache_key = NonprofitSuite_Cache::list_key( 'cpa_shared_files', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $where_clause, $values, $args ) {
			$sql = "SELECT id, access_id, file_name, file_type, file_path, file_size, category,
			               description, shared_by, shared_date, download_count, last_downloaded, created_at
			        FROM {$wpdb->prefix}ns_cpa_shared_files
			        WHERE $where_clause
			        ORDER BY shared_date DESC
			        " . NonprofitSuite_Utilities::build_limit_clause( $args );

			if ( ! empty( $values ) ) {
				$sql = $wpdb->prepare( $sql, $values );
			}

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	/**
	 * Record file download
	 *
	 * @param int $file_id File ID
	 * @param int $user_id User ID who downloaded
	 * @return bool True on success
	 */
	public static function record_download( $file_id, $user_id ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return false;
		}

		global $wpdb;

		// Update last downloaded timestamp and increment count
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}ns_cpa_shared_files
			SET download_count = download_count + 1,
			    last_downloaded = %s
			WHERE id = %d",
			current_time( 'mysql' ),
			absint( $file_id )
		) );

		// Only invalidate this specific file and list caches
		NonprofitSuite_Cache::invalidate_related( 'cpa_shared_files', $file_id );
		return true;
	}

	/**
	 * Delete shared file
	 *
	 * @param int $file_id File ID
	 * @return bool|WP_Error True on success or error
	 */
	public static function delete_shared_file( $file_id ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::can_manage_cpa_access();
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'nonprofitsuite' ) );
		}

		global $wpdb;

		$result = $wpdb->delete(
			$wpdb->prefix . 'ns_cpa_shared_files',
			array( 'id' => absint( $file_id ) ),
			array( '%d' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to delete file', 'nonprofitsuite' ) );
		}

		// Only invalidate this specific file and list caches
		NonprofitSuite_Cache::invalidate_related( 'cpa_shared_files', $file_id );
		return true;
	}

	/**
	 * Get files by category
	 *
	 * @param int    $access_id Access ID
	 * @param string $category File category
	 * @return array Array of files
	 */
	public static function get_files_by_category( $access_id, $category ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		// Use caching for files by category
		$cache_key = NonprofitSuite_Cache::list_key( 'cpa_files_by_category', array( 'access_id' => $access_id, 'category' => $category ) );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $access_id, $category ) {
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT id, access_id, file_name, file_type, file_path, file_size, category,
				        description, shared_by, shared_date, download_count, last_downloaded, created_at
				 FROM {$wpdb->prefix}ns_cpa_shared_files
				WHERE access_id = %d AND category = %s
				ORDER BY shared_date DESC",
				absint( $access_id ),
				sanitize_text_field( $category )
			) );
		}, 300 );
	}
}
