<?php
/**
 * Migration Tools View
 *
 * Data migration and import interface.
 *
 * @package NonprofitSuite
 * @subpackage Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// Get organization ID (simplified - would use proper user org detection)
$organization_id = 1;

// Get migration manager
require_once NS_PLUGIN_DIR . 'includes/helpers/class-migration-manager.php';
$manager = NS_Migration_Manager::get_instance();

$jobs = $manager->get_jobs( $organization_id );

?>

<div class="wrap ns-migration-tools">
	<h1><?php esc_html_e( 'Data Migration Tools', 'nonprofitsuite' ); ?></h1>

	<div class="migration-tabs">
		<button class="tab-button active" data-tab="new-migration">
			<?php esc_html_e( 'CSV Import', 'nonprofitsuite' ); ?>
		</button>
		<button class="tab-button" data-tab="provider-migration">
			<?php esc_html_e( 'Provider Migration', 'nonprofitsuite' ); ?>
		</button>
		<button class="tab-button" data-tab="migration-history">
			<?php esc_html_e( 'Migration History', 'nonprofitsuite' ); ?>
		</button>
	</div>

	<!-- New Migration Tab -->
	<div class="tab-content" id="new-migration" style="display: block;">
		<div class="migration-wizard">
			<div class="wizard-step active" id="step-1">
				<h2><?php esc_html_e( 'Step 1: Select Migration Type', 'nonprofitsuite' ); ?></h2>

				<form id="migration-type-form">
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="job-name"><?php esc_html_e( 'Job Name', 'nonprofitsuite' ); ?></label>
							</th>
							<td>
								<input type="text" id="job-name" class="regular-text" required
									placeholder="<?php esc_attr_e( 'e.g., Import 2024 Donors', 'nonprofitsuite' ); ?>">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="migration-type"><?php esc_html_e( 'What do you want to import?', 'nonprofitsuite' ); ?></label>
							</th>
							<td>
								<select id="migration-type" required>
									<option value=""><?php esc_html_e( '-- Select Type --', 'nonprofitsuite' ); ?></option>
									<option value="contacts"><?php esc_html_e( 'Contacts/Donors', 'nonprofitsuite' ); ?></option>
									<option value="donations"><?php esc_html_e( 'Donations', 'nonprofitsuite' ); ?></option>
									<option value="events"><?php esc_html_e( 'Events', 'nonprofitsuite' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="source-system"><?php esc_html_e( 'Import From', 'nonprofitsuite' ); ?></label>
							</th>
							<td>
								<select id="source-system" required>
									<option value=""><?php esc_html_e( '-- Select Source --', 'nonprofitsuite' ); ?></option>
									<option value="csv"><?php esc_html_e( 'CSV File', 'nonprofitsuite' ); ?></option>
									<option value="salesforce"><?php esc_html_e( 'Salesforce (Coming Soon)', 'nonprofitsuite' ); ?></option>
									<option value="mailchimp"><?php esc_html_e( 'MailChimp (Coming Soon)', 'nonprofitsuite' ); ?></option>
									<option value="other"><?php esc_html_e( 'Other (Coming Soon)', 'nonprofitsuite' ); ?></option>
								</select>
							</td>
						</tr>
					</table>

					<p class="submit">
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Next: Upload File', 'nonprofitsuite' ); ?>
						</button>
					</p>
				</form>
			</div>

			<div class="wizard-step" id="step-2">
				<h2><?php esc_html_e( 'Step 2: Upload File', 'nonprofitsuite' ); ?></h2>

				<form id="file-upload-form">
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="csv-file"><?php esc_html_e( 'CSV File', 'nonprofitsuite' ); ?></label>
							</th>
							<td>
								<input type="file" id="csv-file" accept=".csv" required>
								<p class="description">
									<?php esc_html_e( 'Upload a CSV file with your data. The first row should contain column headers.', 'nonprofitsuite' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<p class="submit">
						<button type="button" class="button button-secondary prev-step">
							<?php esc_html_e( 'Back', 'nonprofitsuite' ); ?>
						</button>
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Next: Map Fields', 'nonprofitsuite' ); ?>
						</button>
					</p>
				</form>
			</div>

			<div class="wizard-step" id="step-3">
				<h2><?php esc_html_e( 'Step 3: Map Fields', 'nonprofitsuite' ); ?></h2>

				<p class="description">
					<?php esc_html_e( 'Map the columns from your CSV file to NonprofitSuite fields.', 'nonprofitsuite' ); ?>
				</p>

				<form id="field-mapping-form">
					<div id="field-mapping-container">
						<!-- Dynamically populated based on CSV headers -->
					</div>

					<p class="submit">
						<button type="button" class="button button-secondary prev-step">
							<?php esc_html_e( 'Back', 'nonprofitsuite' ); ?>
						</button>
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Start Import', 'nonprofitsuite' ); ?>
						</button>
					</p>
				</form>
			</div>

			<div class="wizard-step" id="step-4">
				<h2><?php esc_html_e( 'Step 4: Import Progress', 'nonprofitsuite' ); ?></h2>

				<div id="import-progress">
					<div class="progress-bar">
						<div class="progress-fill" style="width: 0%"></div>
					</div>
					<p class="progress-text">
						<?php esc_html_e( 'Preparing import...', 'nonprofitsuite' ); ?>
					</p>
					<div class="progress-stats" style="display: none;">
						<div class="stat">
							<strong><?php esc_html_e( 'Total:', 'nonprofitsuite' ); ?></strong>
							<span id="stat-total">0</span>
						</div>
						<div class="stat">
							<strong><?php esc_html_e( 'Processed:', 'nonprofitsuite' ); ?></strong>
							<span id="stat-processed">0</span>
						</div>
						<div class="stat">
							<strong><?php esc_html_e( 'Successful:', 'nonprofitsuite' ); ?></strong>
							<span id="stat-successful">0</span>
						</div>
						<div class="stat">
							<strong><?php esc_html_e( 'Failed:', 'nonprofitsuite' ); ?></strong>
							<span id="stat-failed">0</span>
						</div>
					</div>
				</div>

				<div id="import-complete" style="display: none;">
					<div class="dashicons dashicons-yes-alt"></div>
					<h3><?php esc_html_e( 'Import Complete!', 'nonprofitsuite' ); ?></h3>
					<p class="import-summary"></p>
					<p class="submit">
						<button type="button" class="button button-primary" onclick="window.location.reload();">
							<?php esc_html_e( 'Start Another Import', 'nonprofitsuite' ); ?>
						</button>
					</p>
				</div>
			</div>
		</div>
	</div>

	<!-- Provider Migration Tab -->
	<div class="tab-content" id="provider-migration" style="display: none;">
		<?php include NS_PLUGIN_DIR . 'admin/views/provider-migration.php'; ?>
	</div>

	<!-- Migration History Tab -->
	<div class="tab-content" id="migration-history" style="display: none;">
		<h2><?php esc_html_e( 'Previous Migrations', 'nonprofitsuite' ); ?></h2>

		<?php if ( empty( $jobs ) ) : ?>
			<p><?php esc_html_e( 'No migration jobs found.', 'nonprofitsuite' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Job Name', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Source', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Records', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Date', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $jobs as $job ) : ?>
						<tr>
							<td><?php echo esc_html( $job['job_name'] ); ?></td>
							<td><?php echo esc_html( ucfirst( $job['migration_type'] ) ); ?></td>
							<td><?php echo esc_html( strtoupper( $job['source_system'] ) ); ?></td>
							<td>
								<span class="status-badge status-<?php echo esc_attr( $job['job_status'] ); ?>">
									<?php echo esc_html( ucfirst( $job['job_status'] ) ); ?>
								</span>
							</td>
							<td>
								<?php
								if ( $job['job_status'] === 'completed' ) {
									printf(
										esc_html__( '%d successful, %d failed', 'nonprofitsuite' ),
										$job['successful_records'],
										$job['failed_records']
									);
								} else {
									echo esc_html( $job['processed_records'] ) . ' / ' . esc_html( $job['total_records'] );
								}
								?>
							</td>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $job['created_at'] ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>

<style>
.ns-migration-tools {
	max-width: 1000px;
}

.migration-tabs {
	margin: 20px 0;
	border-bottom: 1px solid #ccd0d4;
}

.tab-button {
	background: none;
	border: none;
	padding: 10px 20px;
	cursor: pointer;
	font-size: 14px;
	border-bottom: 2px solid transparent;
	margin-bottom: -1px;
}

.tab-button.active {
	border-bottom-color: #2271b1;
	font-weight: 700;
}

.tab-content {
	background: #fff;
	border: 1px solid #ccd0d4;
	padding: 30px;
	margin-top: 20px;
}

.wizard-step {
	display: none;
}

.wizard-step.active {
	display: block;
}

.progress-bar {
	width: 100%;
	height: 30px;
	background: #f0f0f1;
	border-radius: 4px;
	overflow: hidden;
	margin: 20px 0;
}

.progress-fill {
	height: 100%;
	background: #2271b1;
	transition: width 0.3s ease;
}

.progress-stats {
	display: flex;
	justify-content: space-around;
	margin-top: 20px;
}

.progress-stats .stat {
	text-align: center;
}

#import-complete {
	text-align: center;
	padding: 40px 20px;
}

#import-complete .dashicons {
	font-size: 100px;
	width: 100px;
	height: 100px;
	color: #00a32a;
}

.status-badge {
	display: inline-block;
	padding: 4px 10px;
	border-radius: 3px;
	font-size: 12px;
	font-weight: 600;
}

.status-pending {
	background: #f0f0f1;
	color: #646970;
}

.status-processing {
	background: #dff4ff;
	color: #0071a1;
}

.status-completed {
	background: #d7f1dd;
	color: #007017;
}

.status-failed {
	background: #fcf0f1;
	color: #a00;
}

#field-mapping-container {
	max-height: 400px;
	overflow-y: auto;
	margin: 20px 0;
}

.field-mapping-row {
	display: flex;
	align-items: center;
	margin-bottom: 15px;
	padding: 10px;
	background: #f6f7f7;
	border-radius: 4px;
}

.field-mapping-row label {
	flex: 0 0 200px;
	font-weight: 600;
}

.field-mapping-row select {
	flex: 1;
	max-width: 300px;
}
</style>

<script>
jQuery(document).ready(function($) {
	let currentStep = 1;
	let uploadedFile = null;
	let csvHeaders = [];
	let jobId = null;

	// Tab switching
	$('.tab-button').on('click', function() {
		const tab = $(this).data('tab');
		$('.tab-button').removeClass('active');
		$(this).addClass('active');
		$('.tab-content').hide();
		$('#' + tab).show();
	});

	// Step navigation
	function showStep(step) {
		$('.wizard-step').removeClass('active');
		$('#step-' + step).addClass('active');
		currentStep = step;
	}

	$('.prev-step').on('click', function() {
		showStep(currentStep - 1);
	});

	// Step 1: Migration type
	$('#migration-type-form').on('submit', function(e) {
		e.preventDefault();
		showStep(2);
	});

	// Step 2: File upload
	$('#file-upload-form').on('submit', function(e) {
		e.preventDefault();

		const fileInput = $('#csv-file')[0];
		if (!fileInput.files.length) {
			alert('<?php esc_html_e( 'Please select a file', 'nonprofitsuite' ); ?>');
			return;
		}

		const formData = new FormData();
		formData.append('action', 'ns_migration_upload_csv');
		formData.append('nonce', nsSetup.nonce);
		formData.append('csv_file', fileInput.files[0]);

		$(this).find('.button-primary').prop('disabled', true).text('<?php esc_html_e( 'Uploading...', 'nonprofitsuite' ); ?>');

		$.ajax({
			url: nsSetup.ajax_url,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function(response) {
				if (response.success) {
					uploadedFile = response.data.file_path;
					csvHeaders = response.data.headers;
					buildFieldMapping();
					showStep(3);
				} else {
					alert(response.data.message);
				}
				$('#file-upload-form .button-primary').prop('disabled', false).text('<?php esc_html_e( 'Next: Map Fields', 'nonprofitsuite' ); ?>');
			}
		});
	});

	// Build field mapping interface
	function buildFieldMapping() {
		const migrationType = $('#migration-type').val();
		const targetFields = getTargetFields(migrationType);
		const container = $('#field-mapping-container');

		container.empty();

		targetFields.forEach(function(field) {
			const row = $('<div class="field-mapping-row"></div>');
			row.append('<label>' + field.label + '</label>');

			const select = $('<select name="mapping[' + field.name + ']"></select>');
			select.append('<option value=""><?php esc_html_e( '-- Skip --', 'nonprofitsuite' ); ?></option>');

			csvHeaders.forEach(function(header) {
				const option = $('<option></option>').val(header).text(header);
				if (header.toLowerCase() === field.name.toLowerCase()) {
					option.prop('selected', true);
				}
				select.append(option);
			});

			row.append(select);
			container.append(row);
		});
	}

	function getTargetFields(type) {
		const fields = {
			'contacts': [
				{ name: 'first_name', label: '<?php esc_html_e( 'First Name', 'nonprofitsuite' ); ?>' },
				{ name: 'last_name', label: '<?php esc_html_e( 'Last Name', 'nonprofitsuite' ); ?>' },
				{ name: 'email', label: '<?php esc_html_e( 'Email', 'nonprofitsuite' ); ?>' },
				{ name: 'phone', label: '<?php esc_html_e( 'Phone', 'nonprofitsuite' ); ?>' },
				{ name: 'contact_type', label: '<?php esc_html_e( 'Contact Type', 'nonprofitsuite' ); ?>' }
			],
			'donations': [
				{ name: 'email', label: '<?php esc_html_e( 'Donor Email', 'nonprofitsuite' ); ?>' },
				{ name: 'amount', label: '<?php esc_html_e( 'Amount', 'nonprofitsuite' ); ?>' },
				{ name: 'currency', label: '<?php esc_html_e( 'Currency', 'nonprofitsuite' ); ?>' },
				{ name: 'date', label: '<?php esc_html_e( 'Date', 'nonprofitsuite' ); ?>' },
				{ name: 'payment_method', label: '<?php esc_html_e( 'Payment Method', 'nonprofitsuite' ); ?>' }
			],
			'events': [
				{ name: 'title', label: '<?php esc_html_e( 'Event Title', 'nonprofitsuite' ); ?>' },
				{ name: 'description', label: '<?php esc_html_e( 'Description', 'nonprofitsuite' ); ?>' },
				{ name: 'start_date', label: '<?php esc_html_e( 'Start Date', 'nonprofitsuite' ); ?>' },
				{ name: 'end_date', label: '<?php esc_html_e( 'End Date', 'nonprofitsuite' ); ?>' },
				{ name: 'type', label: '<?php esc_html_e( 'Event Type', 'nonprofitsuite' ); ?>' }
			]
		};

		return fields[type] || [];
	}

	// Step 3: Field mapping and start import
	$('#field-mapping-form').on('submit', function(e) {
		e.preventDefault();

		const mappingConfig = {};
		$(this).find('select').each(function() {
			const targetField = $(this).attr('name').replace('mapping[', '').replace(']', '');
			const sourceField = $(this).val();
			if (sourceField) {
				mappingConfig[targetField] = sourceField;
			}
		});

		// Create migration job
		$.ajax({
			url: nsSetup.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_migration_create_job',
				nonce: nsSetup.nonce,
				organization_id: <?php echo absint( $organization_id ); ?>,
				job_name: $('#job-name').val(),
				migration_type: $('#migration-type').val(),
				source_system: $('#source-system').val(),
				source_file: uploadedFile,
				mapping_config: mappingConfig
			},
			success: function(response) {
				if (response.success) {
					jobId = response.data.job_id;
					showStep(4);
					processJob(jobId);
				} else {
					alert(response.data.message);
				}
			}
		});
	});

	// Process migration job
	function processJob(id) {
		$.ajax({
			url: nsSetup.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_migration_process_job',
				nonce: nsSetup.nonce,
				job_id: id
			},
			success: function(response) {
				$('.progress-stats').show();

				if (response.success) {
					const status = response.data.status;

					$('#stat-total').text(status.total_records);
					$('#stat-processed').text(status.processed_records);
					$('#stat-successful').text(status.successful_records);
					$('#stat-failed').text(status.failed_records);

					const progress = (status.processed_records / status.total_records) * 100;
					$('.progress-fill').css('width', progress + '%');

					if (status.job_status === 'completed') {
						$('#import-progress').hide();
						$('#import-complete').show();
						$('.import-summary').html(
							'<?php esc_html_e( 'Successfully imported', 'nonprofitsuite' ); ?> ' +
							status.successful_records + ' <?php esc_html_e( 'records.', 'nonprofitsuite' ); ?><br>' +
							(status.failed_records > 0 ? status.failed_records + ' <?php esc_html_e( 'records failed.', 'nonprofitsuite' ); ?>' : '')
						);
					}
				} else {
					alert(response.data.message);
				}
			}
		});
	}
});
</script>
