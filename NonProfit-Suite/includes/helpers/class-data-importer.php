<?php
/**
 * Data Import Helper
 *
 * Handles CSV and Excel imports for entity migrations
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data Importer class for CSV/Excel imports
 */
class NonprofitSuite_Data_Importer {

	/**
	 * Maximum file size for imports (10MB)
	 *
	 * @var int
	 */
	const MAX_FILE_SIZE = 10485760;

	/**
	 * Supported file types
	 *
	 * @var array
	 */
	private static $supported_types = array(
		'csv' => 'text/csv',
		'xls' => 'application/vnd.ms-excel',
		'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
	);

	/**
	 * Import CSV file
	 *
	 * @param array  $file $_FILES array element
	 * @param string $entity_class Entity class name (e.g., 'NonprofitSuite_Person')
	 * @param array  $column_mapping Column index => entity field mapping
	 * @param array  $options Import options (skip_first_row, update_existing, etc.)
	 * @return array Results with success/failure counts
	 */
	public static function import_csv( $file, $entity_class, $column_mapping, $options = array() ) {
		// Validate file
		$validation = self::validate_import_file( $file );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Validate entity class
		if ( ! class_exists( $entity_class ) || ! method_exists( $entity_class, 'create' ) ) {
			return new WP_Error(
				'invalid_entity',
				__( 'Invalid entity type for import.', 'nonprofitsuite' )
			);
		}

		// Parse options
		$defaults = array(
			'skip_first_row' => true,
			'update_existing' => false,
			'match_field' => 'email', // Field to match for updates
			'delimiter' => ',',
			'enclosure' => '"',
			'escape' => '\\',
		);
		$options = wp_parse_args( $options, $defaults );

		// Initialize results
		$results = array(
			'success' => 0,
			'updated' => 0,
			'failed' => 0,
			'skipped' => 0,
			'errors' => array(),
			'total_rows' => 0,
		);

		// Open CSV file
		$handle = fopen( $file['tmp_name'], 'r' );
		if ( ! $handle ) {
			return new WP_Error(
				'file_read_error',
				__( 'Could not open import file for reading.', 'nonprofitsuite' )
			);
		}

		$row_num = 0;

		// Process each row
		while ( ( $row = fgetcsv( $handle, 0, $options['delimiter'], $options['enclosure'], $options['escape'] ) ) !== false ) {
			$row_num++;

			// Skip first row if it's headers
			if ( $row_num === 1 && $options['skip_first_row'] ) {
				continue;
			}

			$results['total_rows']++;

			// Map columns to entity fields
			$data = array();
			foreach ( $column_mapping as $col_index => $field_name ) {
				if ( isset( $row[ $col_index ] ) ) {
					$data[ $field_name ] = trim( $row[ $col_index ] );
				}
			}

			// Skip empty rows
			if ( empty( array_filter( $data ) ) ) {
				$results['skipped']++;
				continue;
			}

			// Check if updating existing record
			$existing_id = null;
			if ( $options['update_existing'] && isset( $data[ $options['match_field'] ] ) ) {
				$existing_id = self::find_existing_record(
					$entity_class,
					$options['match_field'],
					$data[ $options['match_field'] ]
				);
			}

			// Create or update record
			if ( $existing_id ) {
				// Update existing
				$result = call_user_func( array( $entity_class, 'update' ), $existing_id, $data );
				if ( is_wp_error( $result ) || $result === false ) {
					$results['failed']++;
					$results['errors'][] = sprintf(
						/* translators: 1: Row number, 2: Error message */
						__( 'Row %1$d: %2$s', 'nonprofitsuite' ),
						$row_num,
						is_wp_error( $result ) ? $result->get_error_message() : __( 'Update failed', 'nonprofitsuite' )
					);
				} else {
					$results['updated']++;
				}
			} else {
				// Create new
				$result = call_user_func( array( $entity_class, 'create' ), $data );
				if ( is_wp_error( $result ) || ! $result ) {
					$results['failed']++;
					$results['errors'][] = sprintf(
						/* translators: 1: Row number, 2: Error message */
						__( 'Row %1$d: %2$s', 'nonprofitsuite' ),
						$row_num,
						is_wp_error( $result ) ? $result->get_error_message() : __( 'Creation failed', 'nonprofitsuite' )
					);
				} else {
					$results['success']++;
				}
			}
		}

		fclose( $handle );

		return $results;
	}

	/**
	 * Validate import file
	 *
	 * @param array $file $_FILES array element
	 * @return bool|WP_Error True if valid, WP_Error on failure
	 */
	private static function validate_import_file( $file ) {
		// Check if file was uploaded
		if ( empty( $file ) || ! isset( $file['tmp_name'] ) || empty( $file['tmp_name'] ) ) {
			return new WP_Error(
				'no_file',
				__( 'No file was uploaded.', 'nonprofitsuite' )
			);
		}

		// Check for upload errors
		if ( isset( $file['error'] ) && $file['error'] !== UPLOAD_ERR_OK ) {
			return new WP_Error(
				'upload_error',
				__( 'File upload failed.', 'nonprofitsuite' )
			);
		}

		// Check file size
		if ( $file['size'] > self::MAX_FILE_SIZE ) {
			return new WP_Error(
				'file_too_large',
				sprintf(
					/* translators: %s: Maximum file size */
					__( 'File is too large. Maximum size: %s', 'nonprofitsuite' ),
					size_format( self::MAX_FILE_SIZE )
				)
			);
		}

		// Check file extension
		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( ! isset( self::$supported_types[ $ext ] ) ) {
			return new WP_Error(
				'invalid_file_type',
				sprintf(
					/* translators: %s: Supported file types */
					__( 'Invalid file type. Supported types: %s', 'nonprofitsuite' ),
					implode( ', ', array_keys( self::$supported_types ) )
				)
			);
		}

		return true;
	}

	/**
	 * Find existing record by field value
	 *
	 * @param string $entity_class Entity class name
	 * @param string $field Field name
	 * @param mixed  $value Field value
	 * @return int|null Record ID if found, null otherwise
	 */
	private static function find_existing_record( $entity_class, $field, $value ) {
		global $wpdb;

		// Try to use a search method if available
		if ( method_exists( $entity_class, 'search' ) ) {
			$results = call_user_func( array( $entity_class, 'search' ), $value );
			if ( ! empty( $results ) && isset( $results[0]->id ) ) {
				return $results[0]->id;
			}
		}

		// Fallback to direct query (requires knowledge of table structure)
		// This is a simplified version - child classes may need custom implementation
		if ( method_exists( $entity_class, 'get_all' ) ) {
			$results = call_user_func( array( $entity_class, 'get_all' ), array( 'limit' => 1 ) );
			// Note: This is a basic implementation - production code would need more sophisticated matching
		}

		return null;
	}

	/**
	 * Generate sample CSV for entity type
	 *
	 * @param string $entity_type Entity type slug (people, organizations, etc.)
	 * @return string CSV content
	 */
	public static function generate_sample_csv( $entity_type ) {
		$samples = array(
			'people' => array(
				'headers' => array( 'First Name', 'Last Name', 'Email', 'Phone', 'Address', 'City', 'State', 'Zip' ),
				'data' => array(
					array( 'John', 'Doe', 'john.doe@example.com', '555-0100', '123 Main St', 'Anytown', 'CA', '90210' ),
					array( 'Jane', 'Smith', 'jane.smith@example.com', '555-0101', '456 Oak Ave', 'Somewhere', 'NY', '10001' ),
				),
			),
			'organizations' => array(
				'headers' => array( 'Name', 'Type', 'Email', 'Phone', 'Address', 'City', 'State', 'Zip', 'Website' ),
				'data' => array(
					array( 'ABC Foundation', 'Foundation', 'info@abcfoundation.org', '555-0200', '789 Charity Ln', 'Givesville', 'TX', '75001', 'https://abcfoundation.org' ),
				),
			),
			'donors' => array(
				'headers' => array( 'First Name', 'Last Name', 'Email', 'Phone', 'Total Donated', 'Last Donation Date' ),
				'data' => array(
					array( 'Alice', 'Johnson', 'alice@example.com', '555-0300', '500.00', '2024-01-15' ),
				),
			),
		);

		if ( ! isset( $samples[ $entity_type ] ) ) {
			return '';
		}

		$sample = $samples[ $entity_type ];
		$csv = '';

		// Add headers
		$csv .= '"' . implode( '","', $sample['headers'] ) . '"' . "\n";

		// Add sample data
		foreach ( $sample['data'] as $row ) {
			$csv .= '"' . implode( '","', $row ) . '"' . "\n";
		}

		return $csv;
	}

	/**
	 * Download sample CSV file
	 *
	 * @param string $entity_type Entity type slug
	 */
	public static function download_sample_csv( $entity_type ) {
		$csv = self::generate_sample_csv( $entity_type );

		if ( empty( $csv ) ) {
			wp_die( esc_html__( 'Invalid entity type.', 'nonprofitsuite' ) );
		}

		// Set headers for download
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $entity_type . '-sample.csv"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Display import results
	 *
	 * @param array $results Results array from import operation
	 */
	public static function display_import_results( $results ) {
		if ( is_wp_error( $results ) ) {
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php echo esc_html( $results->get_error_message() ); ?></p>
			</div>
			<?php
			return;
		}

		?>
		<div class="notice notice-info is-dismissible">
			<h3><?php esc_html_e( 'Import Complete', 'nonprofitsuite' ); ?></h3>
			<p><strong><?php esc_html_e( 'Summary:', 'nonprofitsuite' ); ?></strong></p>
			<ul style="margin-left: 20px;">
				<li><?php echo esc_html( sprintf( __( 'Total rows processed: %d', 'nonprofitsuite' ), $results['total_rows'] ) ); ?></li>
				<li style="color: green;"><?php echo esc_html( sprintf( __( 'Successfully created: %d', 'nonprofitsuite' ), $results['success'] ) ); ?></li>
				<?php if ( $results['updated'] > 0 ) : ?>
					<li style="color: blue;"><?php echo esc_html( sprintf( __( 'Updated: %d', 'nonprofitsuite' ), $results['updated'] ) ); ?></li>
				<?php endif; ?>
				<?php if ( $results['skipped'] > 0 ) : ?>
					<li style="color: orange;"><?php echo esc_html( sprintf( __( 'Skipped (empty rows): %d', 'nonprofitsuite' ), $results['skipped'] ) ); ?></li>
				<?php endif; ?>
				<?php if ( $results['failed'] > 0 ) : ?>
					<li style="color: red;"><?php echo esc_html( sprintf( __( 'Failed: %d', 'nonprofitsuite' ), $results['failed'] ) ); ?></li>
				<?php endif; ?>
			</ul>

			<?php if ( ! empty( $results['errors'] ) ) : ?>
				<p><strong><?php esc_html_e( 'Errors:', 'nonprofitsuite' ); ?></strong></p>
				<ul style="margin-left: 20px; max-height: 200px; overflow-y: auto;">
					<?php foreach ( array_slice( $results['errors'], 0, 10 ) as $error ) : ?>
						<li><?php echo esc_html( $error ); ?></li>
					<?php endforeach; ?>
					<?php if ( count( $results['errors'] ) > 10 ) : ?>
						<li><?php echo esc_html( sprintf( __( '...and %d more errors', 'nonprofitsuite' ), count( $results['errors'] ) - 10 ) ); ?></li>
					<?php endif; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render import form
	 *
	 * @param string $entity_type Entity type slug
	 * @param array  $field_mapping Available fields for mapping
	 */
	public static function render_import_form( $entity_type, $field_mapping ) {
		?>
		<div class="ns-card">
			<h2><?php esc_html_e( 'Import Data', 'nonprofitsuite' ); ?></h2>

			<p>
				<?php
				printf(
					/* translators: 1: Opening link tag, 2: Closing link tag */
					esc_html__( '%1$sDownload a sample CSV file%2$s to see the correct format.', 'nonprofitsuite' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=nonprofitsuite-import&action=download_sample&type=' . $entity_type ) ) . '">',
					'</a>'
				);
				?>
			</p>

			<form method="post" enctype="multipart/form-data" action="">
				<?php wp_nonce_field( 'ns_import_' . $entity_type, 'ns_import_nonce' ); ?>
				<input type="hidden" name="entity_type" value="<?php echo esc_attr( $entity_type ); ?>">

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="import_file"><?php esc_html_e( 'Select File', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="file" name="import_file" id="import_file" accept=".csv,.xls,.xlsx" required>
							<p class="description">
								<?php esc_html_e( 'Supported formats: CSV, Excel (.xls, .xlsx). Maximum size: 10MB', 'nonprofitsuite' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="skip_first_row"><?php esc_html_e( 'Options', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" name="skip_first_row" id="skip_first_row" value="1" checked>
								<?php esc_html_e( 'First row contains headers (skip it)', 'nonprofitsuite' ); ?>
							</label>
							<br>
							<label>
								<input type="checkbox" name="update_existing" id="update_existing" value="1">
								<?php esc_html_e( 'Update existing records (match by email)', 'nonprofitsuite' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" name="import_submit" class="ns-button ns-button-primary">
						<?php esc_html_e( 'Import Data', 'nonprofitsuite' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
	}
}
