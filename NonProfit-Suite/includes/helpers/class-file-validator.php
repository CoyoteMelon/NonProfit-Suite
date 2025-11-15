<?php
/**
 * File Upload Validator
 *
 * Validates file uploads to prevent malicious files
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * File Validator class for secure file uploads
 */
class NonprofitSuite_File_Validator {

	/**
	 * Allowed file types for document uploads
	 *
	 * @var array
	 */
	private static $allowed_types = array(
		// Documents
		'pdf'  => 'application/pdf',
		'doc'  => 'application/msword',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'xls'  => 'application/vnd.ms-excel',
		'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'ppt'  => 'application/vnd.ms-powerpoint',
		'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		'txt'  => 'text/plain',
		'rtf'  => 'application/rtf',
		'odt'  => 'application/vnd.oasis.opendocument.text',
		'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
		// Images		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'gif'  => 'image/gif',
		'webp' => 'image/webp',
		// Archives (with caution)
		'zip'  => 'application/zip',
	);

	/**
	 * Maximum file size in bytes (default: 10MB)
	 *
	 * @var int
	 */
	private static $max_file_size = 10485760; // 10MB

	/**
	 * Validate uploaded file
	 *
	 * @param array  $file $_FILES array element
	 * @param string $context Upload context (document, image, etc.)
	 * @return bool|WP_Error True if valid, WP_Error on failure
	 */
	public static function validate( $file, $context = 'document' ) {
		// Check if file was uploaded
		if ( empty( $file ) || ! isset( $file['tmp_name'] ) || empty( $file['tmp_name'] ) ) {
			return new WP_Error( 'no_file', __( 'No file was uploaded.', 'nonprofitsuite' ) );
		}

		// Check for upload errors
		if ( isset( $file['error'] ) && $file['error'] !== UPLOAD_ERR_OK ) {
			return self::get_upload_error( $file['error'] );
		}

		// Check file size
		$size_check = self::validate_size( $file['size'], $context );
		if ( is_wp_error( $size_check ) ) {
			return $size_check;
		}

		// Check file type
		$type_check = self::validate_type( $file, $context );
		if ( is_wp_error( $type_check ) ) {
			return $type_check;
		}

		// Check for malicious content
		$malware_check = self::check_malicious_content( $file['tmp_name'], $file['name'] );
		if ( is_wp_error( $malware_check ) ) {
			return $malware_check;
		}

		// All checks passed
		return true;
	}

	/**
	 * Validate file size
	 *
	 * @param int    $size File size in bytes
	 * @param string $context Upload context
	 * @return bool|WP_Error True if valid, WP_Error on failure
	 */
	private static function validate_size( $size, $context = 'document' ) {
		// Get max file size for context
		$max_size = apply_filters( 'nonprofitsuite_max_file_size', self::$max_file_size, $context );

		// Also check WordPress max upload size
		$wp_max_size = wp_max_upload_size();
		$max_size = min( $max_size, $wp_max_size );

		if ( $size > $max_size ) {
			return new WP_Error(
				'file_too_large',
				sprintf(
					/* translators: %s: Maximum file size in MB */
					__( 'File is too large. Maximum allowed size is %s.', 'nonprofitsuite' ),
					size_format( $max_size )
				)
			);
		}

		return true;
	}

	/**
	 * Validate file type and extension
	 *
	 * @param array  $file File array from $_FILES
	 * @param string $context Upload context
	 * @return bool|WP_Error True if valid, WP_Error on failure
	 */
	private static function validate_type( $file, $context = 'document' ) {
		$filename = isset( $file['name'] ) ? $file['name'] : '';
		$tmp_name = isset( $file['tmp_name'] ) ? $file['tmp_name'] : '';

		// Get file extension
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		// Get allowed types for context
		$allowed_types = apply_filters( 'nonprofitsuite_allowed_file_types', self::$allowed_types, $context );

		// Check if extension is allowed
		if ( ! isset( $allowed_types[ $ext ] ) ) {
			return new WP_Error(
				'invalid_file_type',
				sprintf(
					/* translators: %s: File extension */
					__( 'File type "%s" is not allowed. Allowed types: %s', 'nonprofitsuite' ),
					$ext,
					implode( ', ', array_keys( $allowed_types ) )
				)
			);
		}

		// Verify MIME type
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$mime_type = finfo_file( $finfo, $tmp_name );
		finfo_close( $finfo );

		// Check if MIME type matches expected type
		$expected_mime = $allowed_types[ $ext ];

		// Some MIME types have variations, so check if it starts with expected
		if ( is_array( $expected_mime ) ) {
			$mime_match = in_array( $mime_type, $expected_mime, true );
		} else {
			// Allow some variations in MIME type (e.g., text/plain vs application/octet-stream for txt)
			$mime_match = ( $mime_type === $expected_mime ) ||
			              ( strpos( $mime_type, 'text/' ) === 0 && $ext === 'txt' ) ||
			              ( strpos( $mime_type, 'image/' ) === 0 && in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) );
		}

		if ( ! $mime_match ) {
			return new WP_Error(
				'mime_mismatch',
				sprintf(
					/* translators: 1: Detected MIME type, 2: Expected MIME type */
					__( 'File MIME type mismatch. Detected: %1$s, Expected: %2$s', 'nonprofitsuite' ),
					$mime_type,
					is_array( $expected_mime ) ? implode( ' or ', $expected_mime ) : $expected_mime
				)
			);
		}

		return true;
	}

	/**
	 * Check for malicious content in file
	 *
	 * @param string $tmp_name Temporary file path
	 * @param string $filename Original filename
	 * @return bool|WP_Error True if safe, WP_Error if suspicious
	 */
	private static function check_malicious_content( $tmp_name, $filename ) {
		// Check for double extensions (e.g., file.pdf.exe)
		$parts = explode( '.', $filename );
		if ( count( $parts ) > 2 ) {
			// Check if second-to-last part is also an executable extension
			$executable_exts = array( 'exe', 'php', 'phtml', 'sh', 'bash', 'cgi', 'pl', 'py', 'js', 'jar' );
			$second_ext = strtolower( $parts[ count( $parts ) - 2 ] );
			if ( in_array( $second_ext, $executable_exts, true ) ) {
				return new WP_Error(
					'suspicious_filename',
					__( 'File has suspicious double extension and cannot be uploaded.', 'nonprofitsuite' )
				);
			}
		}

		// Check for null bytes in filename (security issue)
		if ( strpos( $filename, "\0" ) !== false ) {
			return new WP_Error(
				'null_byte_filename',
				__( 'File name contains null byte and cannot be uploaded.', 'nonprofitsuite' )
			);
		}

		// Read first few bytes to check for executable signatures
		$handle = fopen( $tmp_name, 'rb' );
		if ( $handle ) {
			$header = fread( $handle, 4096 );
			fclose( $handle );

			// Check for PHP code in file
			if ( preg_match( '/<\?php|<\?=|<\?(?!\s*xml)/i', $header ) ) {
				return new WP_Error(
					'php_code_detected',
					__( 'File contains PHP code and cannot be uploaded.', 'nonprofitsuite' )
				);
			}

			// Check for common executable signatures
			$signatures = array(
				'MZ',     // Windows executable
				'#!',     // Script with shebang
				"\x7FELF", // Linux executable
			);

			foreach ( $signatures as $signature ) {
				if ( strpos( $header, $signature ) === 0 ) {
					return new WP_Error(
						'executable_detected',
						__( 'File appears to be an executable and cannot be uploaded.', 'nonprofitsuite' )
					);
				}
			}
		}

		return true;
	}

	/**
	 * Get human-readable error message for upload error code
	 *
	 * @param int $error_code PHP upload error code
	 * @return WP_Error
	 */
	private static function get_upload_error( $error_code ) {
		$errors = array(
			UPLOAD_ERR_INI_SIZE   => __( 'File exceeds the maximum upload size set in PHP configuration.', 'nonprofitsuite' ),
			UPLOAD_ERR_FORM_SIZE  => __( 'File exceeds the maximum upload size set in the form.', 'nonprofitsuite' ),
			UPLOAD_ERR_PARTIAL    => __( 'File was only partially uploaded.', 'nonprofitsuite' ),
			UPLOAD_ERR_NO_FILE    => __( 'No file was uploaded.', 'nonprofitsuite' ),
			UPLOAD_ERR_NO_TMP_DIR => __( 'Missing temporary folder on server.', 'nonprofitsuite' ),
			UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to disk.', 'nonprofitsuite' ),
			UPLOAD_ERR_EXTENSION  => __( 'File upload was stopped by a PHP extension.', 'nonprofitsuite' ),
		);

		$message = isset( $errors[ $error_code ] ) ? $errors[ $error_code ] : __( 'Unknown upload error.', 'nonprofitsuite' );

		return new WP_Error( 'upload_error', $message );
	}

	/**
	 * Sanitize filename for safe storage
	 *
	 * @param string $filename Original filename
	 * @return string Sanitized filename
	 */
	public static function sanitize_filename( $filename ) {
		// Remove any path information
		$filename = basename( $filename );

		// Remove special characters
		$filename = preg_replace( '/[^a-zA-Z0-9_\-\.]/', '_', $filename );

		// Remove multiple underscores/dashes
		$filename = preg_replace( '/[_\-]+/', '_', $filename );

		// Limit length
		if ( strlen( $filename ) > 255 ) {
			$ext = pathinfo( $filename, PATHINFO_EXTENSION );
			$name = pathinfo( $filename, PATHINFO_FILENAME );
			$name = substr( $name, 0, 255 - strlen( $ext ) - 1 );
			$filename = $name . '.' . $ext;
		}

		return $filename;
	}

	/**
	 * Get allowed file types for display
	 *
	 * @param string $context Upload context
	 * @return string Comma-separated list of allowed extensions
	 */
	public static function get_allowed_extensions( $context = 'document' ) {
		$allowed_types = apply_filters( 'nonprofitsuite_allowed_file_types', self::$allowed_types, $context );
		return implode( ', ', array_keys( $allowed_types ) );
	}

	/**
	 * Get maximum file size for context
	 *
	 * @param string $context Upload context
	 * @return int Maximum file size in bytes
	 */
	public static function get_max_file_size( $context = 'document' ) {
		$max_size = apply_filters( 'nonprofitsuite_max_file_size', self::$max_file_size, $context );
		$wp_max_size = wp_max_upload_size();
		return min( $max_size, $wp_max_size );
	}
}
