<?php
/**
 * Documents Module
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Documents {

	public static function get_all( $args = array() ) {
		return NonprofitSuite_Document::get_all( $args );
	}

	public static function upload( $file, $data = array() ) {
		// 1. Check permissions FIRST
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error(
				'permission_denied',
				__( 'You do not have permission to upload files.', 'nonprofitsuite' )
			);
		}

		// 2. Validate file type (whitelist approach)
		$allowed_types = array(
			'pdf' => 'application/pdf',
			'doc' => 'application/msword',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'xls' => 'application/vnd.ms-excel',
			'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png' => 'image/png',
		);

		$file_type = wp_check_filetype( $file['name'], $allowed_types );
		if ( ! $file_type['ext'] || ! isset( $allowed_types[ $file_type['ext'] ] ) ) {
			return new WP_Error(
				'invalid_file_type',
				__( 'File type not allowed. Only PDF, Office documents, and images are permitted.', 'nonprofitsuite' )
			);
		}

		// 3. Enforce file size limit (10MB)
		$max_size = 10 * 1024 * 1024;
		if ( $file['size'] > $max_size ) {
			return new WP_Error(
				'file_too_large',
				__( 'File size exceeds 10MB limit.', 'nonprofitsuite' )
			);
		}

		// 4. Basic malware scan - check for suspicious content
		if ( file_exists( $file['tmp_name'] ) ) {
			$contents = file_get_contents( $file['tmp_name'], false, null, 0, 8192 );
			if ( preg_match( '/<\?php|<script|eval\(|base64_decode|system\(|exec\(/i', $contents ) ) {
				return new WP_Error(
					'suspicious_content',
					__( 'File contains suspicious content and cannot be uploaded.', 'nonprofitsuite' )
				);
			}
		}

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
		}

		$upload = wp_handle_upload( $file, array(
			'test_form' => false,
			'mimes' => $allowed_types,
		) );

		if ( isset( $upload['error'] ) ) {
			return new WP_Error( 'upload_error', $upload['error'] );
		}

		$attachment = array(
			'post_mime_type' => $upload['type'],
			'post_title' => isset( $data['title'] ) ? $data['title'] : basename( $upload['file'] ),
			'post_content' => '',
			'post_status' => 'inherit',
		);

		$attach_id = wp_insert_attachment( $attachment, $upload['file'] );

		if ( ! $attach_id ) {
			return new WP_Error( 'attachment_error', __( 'Failed to create attachment.', 'nonprofitsuite' ) );
		}

		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		$attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
		wp_update_attachment_metadata( $attach_id, $attach_data );

		$doc_data = array_merge( $data, array( 'attachment_id' => $attach_id ) );
		$doc_id = NonprofitSuite_Document::create( $doc_data );

		if ( ! $doc_id ) {
			wp_delete_attachment( $attach_id, true );
			return new WP_Error( 'document_error', __( 'Failed to create document record.', 'nonprofitsuite' ) );
		}

		return $doc_id;
	}

	public static function link_to_meeting( $document_id, $meeting_id ) {
		return NonprofitSuite_Document::update( $document_id, array(
			'linked_to_type' => 'meeting',
			'linked_to_id' => $meeting_id,
		) );
	}

	public static function get_categories() {
		return NonprofitSuite_Document::get_categories();
	}

	/**
	 * Apply retention policy to a document.
	 *
	 * @param int    $document_id The document ID.
	 * @param string $policy_key  Optional policy key.
	 * @return bool True on success, false on failure.
	 */
	public static function apply_retention_policy( $document_id, $policy_key = null ) {
		return NonprofitSuite_Document_Retention::apply_policy( $document_id, $policy_key );
	}

	/**
	 * Archive a document.
	 *
	 * @param int $document_id The document ID.
	 * @return bool True on success, false on failure.
	 */
	public static function archive( $document_id ) {
		return NonprofitSuite_Document_Retention::archive_document( $document_id );
	}

	/**
	 * Unarchive a document.
	 *
	 * @param int $document_id The document ID.
	 * @return bool True on success, false on failure.
	 */
	public static function unarchive( $document_id ) {
		return NonprofitSuite_Document_Retention::unarchive_document( $document_id );
	}

	/**
	 * Get archived documents.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of document objects.
	 */
	public static function get_archived( $args = array() ) {
		return NonprofitSuite_Document_Retention::get_archived_documents( $args );
	}

	/**
	 * Get retention statistics.
	 *
	 * @return array Statistics array.
	 */
	public static function get_retention_stats() {
		return NonprofitSuite_Document_Retention::get_statistics();
	}
}
