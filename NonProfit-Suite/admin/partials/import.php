<?php
/**
 * Data Import Page
 *
 * @package NonprofitSuite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Handle sample CSV download
if ( isset( $_GET['action'] ) && $_GET['action'] === 'download_sample' && isset( $_GET['type'] ) ) {
	NonprofitSuite_Data_Importer::download_sample_csv( sanitize_text_field( wp_unslash( $_GET['type'] ) ) );
}

// Handle import submission
$import_results = null;
if ( isset( $_POST['import_submit'] ) && isset( $_POST['entity_type'] ) && check_admin_referer( 'ns_import_' . $_POST['entity_type'], 'ns_import_nonce' ) ) {
	$entity_type = sanitize_text_field( wp_unslash( $_POST['entity_type'] ) );

	// Define column mappings for different entity types
	$column_mappings = array(
		'people' => array(
			0 => 'first_name',
			1 => 'last_name',
			2 => 'email',
			3 => 'phone',
			4 => 'address',
			5 => 'city',
			6 => 'state',
			7 => 'zip',
		),
		'organizations' => array(
			0 => 'name',
			1 => 'type',
			2 => 'email',
			3 => 'phone',
			4 => 'address',
			5 => 'city',
			6 => 'state',
			7 => 'zip',
			8 => 'website',
		),
	);

	// Define entity classes
	$entity_classes = array(
		'people' => 'NonprofitSuite_Person',
		'organizations' => 'NonprofitSuite_Organization',
	);

	if ( isset( $column_mappings[ $entity_type ] ) && isset( $entity_classes[ $entity_type ] ) && isset( $_FILES['import_file'] ) ) {
		$options = array(
			'skip_first_row' => isset( $_POST['skip_first_row'] ),
			'update_existing' => isset( $_POST['update_existing'] ),
		);

		$import_results = NonprofitSuite_Data_Importer::import_csv(
			$_FILES['import_file'],
			$entity_classes[ $entity_type ],
			$column_mappings[ $entity_type ],
			$options
		);
	}
}

$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'people';
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Data Import', 'nonprofitsuite' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Import data from CSV or Excel files to quickly populate your NonprofitSuite database.', 'nonprofitsuite' ); ?>
	</p>

	<?php
	// Display import results if available
	if ( $import_results ) {
		NonprofitSuite_Data_Importer::display_import_results( $import_results );
	}
	?>

	<!-- Tabs -->
	<nav class="nav-tab-wrapper" style="margin: 20px 0;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-import&tab=people' ) ); ?>"
		   class="nav-tab <?php echo $current_tab === 'people' ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'People', 'nonprofitsuite' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-import&tab=organizations' ) ); ?>"
		   class="nav-tab <?php echo $current_tab === 'organizations' ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Organizations', 'nonprofitsuite' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-import&tab=donors' ) ); ?>"
		   class="nav-tab <?php echo $current_tab === 'donors' ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Donors', 'nonprofitsuite' ); ?>
		</a>
	</nav>

	<!-- Tab Content -->
	<div class="tab-content">
		<?php
		// Define field mappings for each entity type
		$field_mappings = array(
			'people' => array(
				'first_name' => __( 'First Name', 'nonprofitsuite' ),
				'last_name' => __( 'Last Name', 'nonprofitsuite' ),
				'email' => __( 'Email', 'nonprofitsuite' ),
				'phone' => __( 'Phone', 'nonprofitsuite' ),
				'address' => __( 'Address', 'nonprofitsuite' ),
				'city' => __( 'City', 'nonprofitsuite' ),
				'state' => __( 'State', 'nonprofitsuite' ),
				'zip' => __( 'ZIP Code', 'nonprofitsuite' ),
			),
			'organizations' => array(
				'name' => __( 'Organization Name', 'nonprofitsuite' ),
				'type' => __( 'Type', 'nonprofitsuite' ),
				'email' => __( 'Email', 'nonprofitsuite' ),
				'phone' => __( 'Phone', 'nonprofitsuite' ),
				'address' => __( 'Address', 'nonprofitsuite' ),
				'city' => __( 'City', 'nonprofitsuite' ),
				'state' => __( 'State', 'nonprofitsuite' ),
				'zip' => __( 'ZIP Code', 'nonprofitsuite' ),
				'website' => __( 'Website', 'nonprofitsuite' ),
			),
			'donors' => array(
				'first_name' => __( 'First Name', 'nonprofitsuite' ),
				'last_name' => __( 'Last Name', 'nonprofitsuite' ),
				'email' => __( 'Email', 'nonprofitsuite' ),
				'phone' => __( 'Phone', 'nonprofitsuite' ),
				'total_donated' => __( 'Total Donated', 'nonprofitsuite' ),
				'last_donation_date' => __( 'Last Donation Date', 'nonprofitsuite' ),
			),
		);

		if ( isset( $field_mappings[ $current_tab ] ) ) {
			NonprofitSuite_Data_Importer::render_import_form( $current_tab, $field_mappings[ $current_tab ] );
		}
		?>
	</div>

	<!-- Instructions -->
	<div class="ns-card" style="margin-top: 20px;">
		<h3><?php esc_html_e( 'Import Instructions', 'nonprofitsuite' ); ?></h3>

		<ol style="line-height: 1.8;">
			<li>
				<strong><?php esc_html_e( 'Download the sample CSV file', 'nonprofitsuite' ); ?></strong>
				<?php esc_html_e( 'to see the required format and column order.', 'nonprofitsuite' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Prepare your data', 'nonprofitsuite' ); ?></strong>
				<?php esc_html_e( 'in a spreadsheet program (Excel, Google Sheets, etc.) matching the sample format.', 'nonprofitsuite' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Save as CSV', 'nonprofitsuite' ); ?></strong>
				<?php esc_html_e( 'or keep as Excel format (.xlsx).', 'nonprofitsuite' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Upload the file', 'nonprofitsuite' ); ?></strong>
				<?php esc_html_e( 'using the form above and configure your import options.', 'nonprofitsuite' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Review the results', 'nonprofitsuite' ); ?></strong>
				<?php esc_html_e( 'and check for any errors that need to be addressed.', 'nonprofitsuite' ); ?>
			</li>
		</ol>

		<h4><?php esc_html_e( 'Import Options:', 'nonprofitsuite' ); ?></h4>
		<ul style="line-height: 1.8;">
			<li>
				<strong><?php esc_html_e( 'Skip First Row:', 'nonprofitsuite' ); ?></strong>
				<?php esc_html_e( 'Check this if your CSV file has column headers in the first row.', 'nonprofitsuite' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Update Existing:', 'nonprofitsuite' ); ?></strong>
				<?php esc_html_e( 'If checked, existing records with matching email addresses will be updated instead of creating duplicates.', 'nonprofitsuite' ); ?>
			</li>
		</ul>

		<h4><?php esc_html_e( 'Tips:', 'nonprofitsuite' ); ?></h4>
		<ul style="line-height: 1.8;">
			<li><?php esc_html_e( 'Test with a small sample file (5-10 rows) before importing large datasets.', 'nonprofitsuite' ); ?></li>
			<li><?php esc_html_e( 'Keep a backup of your original data in case you need to re-import.', 'nonprofitsuite' ); ?></li>
			<li><?php esc_html_e( 'Make sure dates are in YYYY-MM-DD format (e.g., 2024-01-15).', 'nonprofitsuite' ); ?></li>
			<li><?php esc_html_e( 'Remove any special formatting (colors, formulas) before exporting to CSV.', 'nonprofitsuite' ); ?></li>
		</ul>
	</div>
</div>
