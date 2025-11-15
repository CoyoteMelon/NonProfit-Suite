<?php
/**
 * Document entity class
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/entities
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Document entity for managing document metadata.
 */
class NonprofitSuite_Document {

	/**
	 * Get a document by ID.
	 *
	 * @param int $doc_id The document ID.
	 * @return object|null Document object or null if not found.
	 */
	public static function get( $doc_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_documents';

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT id, attachment_id, title, description, category, tags,
			        linked_to_type, linked_to_id, version, access_level,
			        is_archived, archived_at, retention_policy, expiration_date, is_expired,
			        uploaded_by, created_at, updated_at
			 FROM {$table} WHERE id = %d",
			$doc_id
		) );
	}

	/**
	 * Get all documents.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of document objects.
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_documents';

		$defaults = array(
			'category' => '',
			'linked_to_type' => '',
			'linked_to_id' => 0,
			'access_level' => '',
			'orderby' => 'created_at',
			'order' => 'DESC',
			'limit' => -1,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = "WHERE 1=1";
		if ( $args['category'] ) {
			$where .= $wpdb->prepare( " AND category = %s", $args['category'] );
		}
		if ( $args['linked_to_type'] ) {
			$where .= $wpdb->prepare( " AND linked_to_type = %s", $args['linked_to_type'] );
		}
		if ( $args['linked_to_id'] > 0 ) {
			$where .= $wpdb->prepare( " AND linked_to_id = %d", $args['linked_to_id'] );
		}
		if ( $args['access_level'] ) {
			$where .= $wpdb->prepare( " AND access_level = %s", $args['access_level'] );
		}

		$orderby = sanitize_sql_orderby( "{$args['orderby']} {$args['order']}" );

		// Apply safe limit to prevent unbounded queries
		$safe_limit = NonprofitSuite_Query_Optimizer::apply_safe_limit( $args['limit'], 'documents' );
		$limit = $wpdb->prepare( "LIMIT %d", $safe_limit );

		$query = "SELECT id, attachment_id, title, description, category, tags,
		                 linked_to_type, linked_to_id, version, access_level,
		                 is_archived, archived_at, retention_policy, expiration_date, is_expired,
		                 uploaded_by, created_at, updated_at
		          FROM {$table} {$where} ORDER BY {$orderby} {$limit}";

		return $wpdb->get_results( $query );
	}

	/**
	 * Create a new document record.
	 *
	 * @param array $data Document data.
	 * @return int|false Document ID on success, false on failure.
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_documents';

		$defaults = array(
			'attachment_id' => 0,
			'title' => '',
			'description' => '',
			'category' => '',
			'tags' => '',
			'linked_to_type' => '',
			'linked_to_id' => 0,
			'version' => '1.0',
			'access_level' => 'all',
			'uploaded_by' => get_current_user_id(),
		);

		$data = wp_parse_args( $data, $defaults );

		$result = $wpdb->insert(
			$table,
			array(
				'attachment_id' => absint( $data['attachment_id'] ),
				'title' => sanitize_text_field( $data['title'] ),
				'description' => sanitize_textarea_field( $data['description'] ),
				'category' => sanitize_text_field( $data['category'] ),
				'tags' => sanitize_text_field( $data['tags'] ),
				'linked_to_type' => sanitize_text_field( $data['linked_to_type'] ),
				'linked_to_id' => absint( $data['linked_to_id'] ),
				'version' => sanitize_text_field( $data['version'] ),
				'access_level' => sanitize_text_field( $data['access_level'] ),
				'uploaded_by' => absint( $data['uploaded_by'] ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update a document.
	 *
	 * @param int   $doc_id The document ID.
	 * @param array $data Document data to update.
	 * @return bool True on success, false on failure.
	 */
	public static function update( $doc_id, $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_documents';

		$update_data = array();
		$format = array();

		$allowed_fields = array( 'title', 'description', 'category', 'tags', 'linked_to_type', 'linked_to_id', 'version', 'access_level' );

		foreach ( $data as $key => $value ) {
			if ( in_array( $key, $allowed_fields ) ) {
				switch ( $key ) {
					case 'linked_to_id':
					case 'attachment_id':
						$update_data[ $key ] = absint( $value );
						$format[] = '%d';
						break;
					case 'description':
						$update_data[ $key ] = sanitize_textarea_field( $value );
						$format[] = '%s';
						break;
					default:
						$update_data[ $key ] = sanitize_text_field( $value );
						$format[] = '%s';
				}
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		return $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $doc_id ),
			$format,
			array( '%d' )
		) !== false;
	}

	/**
	 * Delete a document.
	 *
	 * @param int  $doc_id The document ID.
	 * @param bool $delete_attachment Whether to delete the WordPress attachment.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( $doc_id, $delete_attachment = false ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_documents';

		if ( $delete_attachment ) {
			$doc = self::get( $doc_id );
			if ( $doc && $doc->attachment_id ) {
				wp_delete_attachment( $doc->attachment_id, true );
			}
		}

		return $wpdb->delete(
			$table,
			array( 'id' => $doc_id ),
			array( '%d' )
		) !== false;
	}

	/**
	 * Get documents linked to a specific entity.
	 *
	 * @param string $type Entity type (meeting, task, etc.).
	 * @param int    $id Entity ID.
	 * @return array Array of document objects.
	 */
	public static function get_linked_documents( $type, $id ) {
		return self::get_all( array(
			'linked_to_type' => $type,
			'linked_to_id' => $id,
		) );
	}

	/**
	 * Get document categories.
	 *
	 * @return array Array of categories.
	 */
	public static function get_categories() {
		return array(
			'board' => __( 'Board', 'nonprofitsuite' ),
			'committee' => __( 'Committee', 'nonprofitsuite' ),
			'financial' => __( 'Financial', 'nonprofitsuite' ),
			'policies' => __( 'Policies', 'nonprofitsuite' ),
			'legal' => __( 'Legal', 'nonprofitsuite' ),
			'programs' => __( 'Programs', 'nonprofitsuite' ),
			'grants' => __( 'Grants', 'nonprofitsuite' ),
			'other' => __( 'Other', 'nonprofitsuite' ),
		);
	}

	/**
	 * Check if current user can access document.
	 *
	 * @param object $document Document object.
	 * @return bool True if user can access, false otherwise.
	 */
	public static function user_can_access( $document ) {
		if ( ! $document ) {
			return false;
		}

		// Admins can access everything
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		// Check access level
		switch ( $document->access_level ) {
			case 'all':
				return current_user_can( 'ns_view_documents' ) || current_user_can( 'ns_view_documents_limited' );

			case 'board':
				return current_user_can( 'ns_view_documents' );

			case 'admin':
				return current_user_can( 'ns_manage_documents' );

			default:
				return false;
		}
	}
}
