<?php
/**
 * Provider Migration View
 *
 * Interface for migrating between integration providers.
 *
 * @package NonprofitSuite
 * @subpackage Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get organization ID
$organization_id = 1;

// Get migration manager
require_once NS_PLUGIN_DIR . 'includes/helpers/class-provider-migration-manager.php';
$manager = NS_Provider_Migration_Manager::get_instance();

$integrations = $manager->get_supported_integrations();
$jobs         = $manager->get_jobs( $organization_id );

?>

<div class="wrap ns-provider-migration">
	<h1><?php esc_html_e( 'Provider Migration', 'nonprofitsuite' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Migrate data between integration providers (e.g., switch from Salesforce to HubSpot).', 'nonprofitsuite' ); ?>
	</p>

	<div class="migration-tabs">
		<button class="tab-button active" data-tab="new-provider-migration">
			<?php esc_html_e( 'New Provider Migration', 'nonprofitsuite' ); ?>
		</button>
		<button class="tab-button" data-tab="provider-migration-history">
			<?php esc_html_e( 'Migration History', 'nonprofitsuite' ); ?>
		</button>
	</div>

	<!-- New Provider Migration Tab -->
	<div class="tab-content" id="new-provider-migration" style="display: block;">
		<div class="provider-migration-wizard">
			<!-- Step 1: Select Integration Type -->
			<div class="wizard-step active" id="provider-step-1">
				<h2><?php esc_html_e( 'Step 1: Select Integration Type', 'nonprofitsuite' ); ?></h2>

				<form id="provider-integration-form">
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="provider-migration-name"><?php esc_html_e( 'Migration Name', 'nonprofitsuite' ); ?></label>
							</th>
							<td>
								<input type="text" id="provider-migration-name" class="regular-text" required
									placeholder="<?php esc_attr_e( 'e.g., Migrate from Salesforce to HubSpot', 'nonprofitsuite' ); ?>">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="provider-integration-type"><?php esc_html_e( 'Integration Type', 'nonprofitsuite' ); ?></label>
							</th>
							<td>
								<select id="provider-integration-type" required>
									<option value=""><?php esc_html_e( '-- Select Type --', 'nonprofitsuite' ); ?></option>
									<?php foreach ( $integrations as $type => $data ) : ?>
										<option value="<?php echo esc_attr( $type ); ?>">
											<?php echo esc_html( $data['label'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Choose which type of integration you want to migrate.', 'nonprofitsuite' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<p class="submit">
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Next: Select Providers', 'nonprofitsuite' ); ?>
						</button>
					</p>
				</form>
			</div>

			<!-- Step 2: Select Source and Destination -->
			<div class="wizard-step" id="provider-step-2">
				<h2><?php esc_html_e( 'Step 2: Select Source and Destination Providers', 'nonprofitsuite' ); ?></h2>

				<form id="provider-selection-form">
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="source-provider"><?php esc_html_e( 'Source Provider', 'nonprofitsuite' ); ?></label>
							</th>
							<td>
								<select id="source-provider" required>
									<option value=""><?php esc_html_e( '-- Select Source --', 'nonprofitsuite' ); ?></option>
								</select>
								<p class="description">
									<?php esc_html_e( 'The provider you are migrating FROM.', 'nonprofitsuite' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="destination-provider"><?php esc_html_e( 'Destination Provider', 'nonprofitsuite' ); ?></label>
							</th>
							<td>
								<select id="destination-provider" required>
									<option value=""><?php esc_html_e( '-- Select Destination --', 'nonprofitsuite' ); ?></option>
								</select>
								<p class="description">
									<?php esc_html_e( 'The provider you are migrating TO.', 'nonprofitsuite' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label><?php esc_html_e( 'Data Types to Migrate', 'nonprofitsuite' ); ?></label>
							</th>
							<td id="data-types-container">
								<!-- Populated dynamically -->
							</td>
						</tr>
					</table>

					<p class="submit">
						<button type="button" class="button button-secondary prev-step">
							<?php esc_html_e( 'Back', 'nonprofitsuite' ); ?>
						</button>
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Next: Field Mapping', 'nonprofitsuite' ); ?>
						</button>
					</p>
				</form>
			</div>

			<!-- Step 3: Field Mapping (Optional) -->
			<div class="wizard-step" id="provider-step-3">
				<h2><?php esc_html_e( 'Step 3: Field Mapping (Optional)', 'nonprofitsuite' ); ?></h2>

				<p class="description">
					<?php esc_html_e( 'Map fields between providers if needed. Leave blank to use automatic mapping.', 'nonprofitsuite' ); ?>
				</p>

				<form id="provider-field-mapping-form">
					<div id="provider-field-mapping-container">
						<p class="no-mapping-needed">
							<?php esc_html_e( 'Automatic field mapping will be used. Click "Analyze" to proceed.', 'nonprofitsuite' ); ?>
						</p>
					</div>

					<p class="submit">
						<button type="button" class="button button-secondary prev-step">
							<?php esc_html_e( 'Back', 'nonprofitsuite' ); ?>
						</button>
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Analyze Source Provider', 'nonprofitsuite' ); ?>
						</button>
					</p>
				</form>
			</div>

			<!-- Step 4: Analysis Results -->
			<div class="wizard-step" id="provider-step-4">
				<h2><?php esc_html_e( 'Step 4: Analysis Results', 'nonprofitsuite' ); ?></h2>

				<div id="analysis-results">
					<div class="analyzing-message">
						<span class="spinner is-active"></span>
						<p><?php esc_html_e( 'Analyzing source provider...', 'nonprofitsuite' ); ?></p>
					</div>

					<div class="analysis-complete" style="display: none;">
						<h3><?php esc_html_e( 'Migration Plan', 'nonprofitsuite' ); ?></h3>

						<table class="widefat">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Data Type', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Record Count', 'nonprofitsuite' ); ?></th>
								</tr>
							</thead>
							<tbody id="analysis-record-counts">
								<!-- Populated dynamically -->
							</tbody>
						</table>

						<div class="migration-summary">
							<p>
								<strong><?php esc_html_e( 'Total Records:', 'nonprofitsuite' ); ?></strong>
								<span id="total-record-count">0</span>
							</p>
							<p>
								<strong><?php esc_html_e( 'Estimated Duration:', 'nonprofitsuite' ); ?></strong>
								<span id="estimated-duration">0</span> <?php esc_html_e( 'minutes', 'nonprofitsuite' ); ?>
							</p>
						</div>

						<div id="analysis-warnings" style="display: none;">
							<h4><?php esc_html_e( 'Warnings', 'nonprofitsuite' ); ?></h4>
							<ul id="warning-list"></ul>
						</div>
					</div>
				</div>

				<p class="submit" id="execute-migration-controls" style="display: none;">
					<button type="button" class="button button-secondary prev-step">
						<?php esc_html_e( 'Back', 'nonprofitsuite' ); ?>
					</button>
					<button type="button" class="button button-primary" id="btn-preview-migration">
						<?php esc_html_e( 'Preview Migration (Dry Run)', 'nonprofitsuite' ); ?>
					</button>
					<button type="button" class="button button-primary" id="btn-execute-migration">
						<?php esc_html_e( 'Execute Migration', 'nonprofitsuite' ); ?>
					</button>
				</p>
			</div>

			<!-- Step 5: Migration Progress -->
			<div class="wizard-step" id="provider-step-5">
				<h2><?php esc_html_e( 'Step 5: Migration Progress', 'nonprofitsuite' ); ?></h2>

				<div id="migration-progress">
					<div class="progress-bar">
						<div class="progress-fill" style="width: 0%"></div>
					</div>
					<p class="progress-text">
						<?php esc_html_e( 'Migrating data...', 'nonprofitsuite' ); ?>
					</p>
					<div class="progress-stats">
						<div class="stat">
							<strong><?php esc_html_e( 'Total:', 'nonprofitsuite' ); ?></strong>
							<span id="migration-stat-total">0</span>
						</div>
						<div class="stat">
							<strong><?php esc_html_e( 'Successful:', 'nonprofitsuite' ); ?></strong>
							<span id="migration-stat-successful">0</span>
						</div>
						<div class="stat">
							<strong><?php esc_html_e( 'Failed:', 'nonprofitsuite' ); ?></strong>
							<span id="migration-stat-failed">0</span>
						</div>
						<div class="stat">
							<strong><?php esc_html_e( 'Skipped:', 'nonprofitsuite' ); ?></strong>
							<span id="migration-stat-skipped">0</span>
						</div>
					</div>
				</div>

				<div id="migration-complete" style="display: none;">
					<div class="dashicons dashicons-yes-alt"></div>
					<h3><?php esc_html_e( 'Migration Complete!', 'nonprofitsuite' ); ?></h3>
					<p class="migration-summary-text"></p>

					<p class="submit">
						<button type="button" class="button button-secondary" id="btn-rollback-migration">
							<?php esc_html_e( 'Rollback Migration', 'nonprofitsuite' ); ?>
						</button>
						<button type="button" class="button button-primary" onclick="window.location.reload();">
							<?php esc_html_e( 'Start Another Migration', 'nonprofitsuite' ); ?>
						</button>
					</p>
				</div>
			</div>
		</div>
	</div>

	<!-- Provider Migration History Tab -->
	<div class="tab-content" id="provider-migration-history" style="display: none;">
		<h2><?php esc_html_e( 'Previous Provider Migrations', 'nonprofitsuite' ); ?></h2>

		<?php if ( empty( $jobs ) ) : ?>
			<p><?php esc_html_e( 'No provider migrations found.', 'nonprofitsuite' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Migration Name', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Source → Destination', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Records', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Date', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $jobs as $job ) : ?>
						<tr>
							<td><?php echo esc_html( $job['migration_name'] ); ?></td>
							<td><?php echo esc_html( strtoupper( $job['integration_type'] ) ); ?></td>
							<td>
								<?php
								echo esc_html( ucfirst( $job['source_provider'] ) );
								echo ' → ';
								echo esc_html( ucfirst( $job['destination_provider'] ) );
								?>
							</td>
							<td>
								<span class="status-badge status-<?php echo esc_attr( $job['migration_status'] ); ?>">
									<?php echo esc_html( ucfirst( str_replace( '_', ' ', $job['migration_status'] ) ) ); ?>
								</span>
							</td>
							<td>
								<?php
								if ( $job['migration_status'] === 'completed' ) {
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
							<td>
								<?php if ( $job['migration_status'] === 'completed' ) : ?>
									<button class="button button-small btn-rollback" data-job-id="<?php echo esc_attr( $job['id'] ); ?>">
										<?php esc_html_e( 'Rollback', 'nonprofitsuite' ); ?>
									</button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>

<style>
.ns-provider-migration .wizard-step {
	display: none;
}

.ns-provider-migration .wizard-step.active {
	display: block;
}

#data-types-container label {
	display: block;
	margin-bottom: 8px;
}

.migration-summary {
	margin: 20px 0;
	padding: 15px;
	background: #f0f0f1;
	border-radius: 4px;
}

.migration-summary p {
	margin: 8px 0;
}

#analysis-warnings {
	margin-top: 20px;
	padding: 15px;
	background: #fcf0f1;
	border-left: 4px solid #d63638;
	border-radius: 4px;
}

.analyzing-message {
	text-align: center;
	padding: 40px 20px;
}

#migration-complete .dashicons {
	font-size: 100px;
	width: 100px;
	height: 100px;
	color: #00a32a;
	display: block;
	margin: 0 auto 20px;
}

.progress-stats {
	display: flex;
	justify-content: space-around;
	margin-top: 20px;
}

.progress-stats .stat {
	text-align: center;
}

.status-badge {
	display: inline-block;
	padding: 4px 10px;
	border-radius: 3px;
	font-size: 12px;
	font-weight: 600;
}

.status-pending,
.status-analyzing {
	background: #f0f0f1;
	color: #646970;
}

.status-ready {
	background: #dff4ff;
	color: #0071a1;
}

.status-running {
	background: #fcf9e8;
	color: #8a6116;
}

.status-completed {
	background: #d7f1dd;
	color: #007017;
}

.status-failed {
	background: #fcf0f1;
	color: #a00;
}

.status-rolled_back {
	background: #f0f0f1;
	color: #646970;
}
</style>

<script>
jQuery(document).ready(function($) {
	let currentStep = 1;
	let jobId = null;
	let selectedIntegrationType = null;

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
		$('#provider-step-' + step).addClass('active');
		currentStep = step;
	}

	$('.prev-step').on('click', function() {
		showStep(currentStep - 1);
	});

	// Step 1: Select integration type
	$('#provider-integration-form').on('submit', function(e) {
		e.preventDefault();
		selectedIntegrationType = $('#provider-integration-type').val();

		// Load providers for this integration type
		$.ajax({
			url: nsSetup.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_provider_migration_get_providers',
				nonce: nsSetup.nonce,
				organization_id: <?php echo absint( $organization_id ); ?>,
				integration_type: selectedIntegrationType
			},
			success: function(response) {
				if (response.success) {
					// Populate source/destination selects
					const providers = response.data.providers;
					const dataTypes = response.data.data_types;

					$('#source-provider, #destination-provider').empty().append('<option value="">-- Select --</option>');
					providers.forEach(function(provider) {
						const option = $('<option></option>').val(provider).text(provider.charAt(0).toUpperCase() + provider.slice(1).replace('_', ' '));
						$('#source-provider, #destination-provider').append(option.clone());
					});

					// Populate data types checkboxes
					$('#data-types-container').empty();
					dataTypes.forEach(function(dataType) {
						const label = $('<label></label>');
						const checkbox = $('<input type="checkbox" name="data_types[]">').val(dataType);
						label.append(checkbox).append(' ' + dataType.charAt(0).toUpperCase() + dataType.slice(1).replace('_', ' '));
						$('#data-types-container').append(label);
					});

					showStep(2);
				}
			}
		});
	});

	// Step 2: Select providers
	$('#provider-selection-form').on('submit', function(e) {
		e.preventDefault();
		showStep(3);
	});

	// Step 3: Field mapping (create job and analyze)
	$('#provider-field-mapping-form').on('submit', function(e) {
		e.preventDefault();

		const dataTypes = [];
		$('input[name="data_types[]"]:checked').each(function() {
			dataTypes.push($(this).val());
		});

		// Create migration job
		$.ajax({
			url: nsSetup.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_provider_migration_create',
				nonce: nsSetup.nonce,
				organization_id: <?php echo absint( $organization_id ); ?>,
				migration_name: $('#provider-migration-name').val(),
				integration_type: selectedIntegrationType,
				source_provider: $('#source-provider').val(),
				destination_provider: $('#destination-provider').val(),
				data_types: dataTypes,
				field_mapping: {},
				migration_mode: 'preview'
			},
			success: function(response) {
				if (response.success) {
					jobId = response.data.job_id;
					showStep(4);
					analyzeSource();
				}
			}
		});
	});

	// Analyze source provider
	function analyzeSource() {
		$.ajax({
			url: nsSetup.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_provider_migration_analyze',
				nonce: nsSetup.nonce,
				job_id: jobId
			},
			success: function(response) {
				$('.analyzing-message').hide();
				$('.analysis-complete').show();
				$('#execute-migration-controls').show();

				if (response.success) {
					const analysis = response.data.analysis;

					// Display record counts
					$('#analysis-record-counts').empty();
					let totalRecords = 0;
					Object.keys(analysis.record_counts).forEach(function(dataType) {
						const count = analysis.record_counts[dataType];
						totalRecords += count;
						const row = $('<tr></tr>');
						row.append('<td>' + dataType + '</td>');
						row.append('<td>' + count + '</td>');
						$('#analysis-record-counts').append(row);
					});

					$('#total-record-count').text(totalRecords);
					$('#estimated-duration').text(Math.ceil(analysis.estimated_duration / 60));

					// Display warnings if any
					if (analysis.warnings.length > 0) {
						$('#analysis-warnings').show();
						$('#warning-list').empty();
						analysis.warnings.forEach(function(warning) {
							$('#warning-list').append('<li>' + warning + '</li>');
						});
					}
				}
			}
		});
	}

	// Execute migration (preview or actual)
	$('#btn-preview-migration, #btn-execute-migration').on('click', function() {
		const isPreview = $(this).attr('id') === 'btn-preview-migration';
		showStep(5);
		executeMigration(isPreview ? 'preview' : 'execute');
	});

	function executeMigration(mode) {
		$.ajax({
			url: nsSetup.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_provider_migration_execute',
				nonce: nsSetup.nonce,
				job_id: jobId
			},
			success: function(response) {
				if (response.success) {
					const results = response.data.results;

					// Update progress
					const progress = (results.total > 0) ? (results.successful / results.total * 100) : 0;
					$('.progress-fill').css('width', progress + '%');

					$('#migration-stat-total').text(results.total);
					$('#migration-stat-successful').text(results.successful);
					$('#migration-stat-failed').text(results.failed);
					$('#migration-stat-skipped').text(results.skipped);

					// Show completion
					$('#migration-progress').hide();
					$('#migration-complete').show();

					$('.migration-summary-text').html(
						'Successfully migrated ' + results.successful + ' records.<br>' +
						(results.failed > 0 ? results.failed + ' records failed.' : '')
					);
				}
			}
		});
	}

	// Rollback migration
	$('#btn-rollback-migration, .btn-rollback').on('click', function() {
		const rollbackJobId = $(this).data('job-id') || jobId;

		if (!confirm('<?php esc_html_e( 'Are you sure you want to rollback this migration? This will delete all migrated records.', 'nonprofitsuite' ); ?>')) {
			return;
		}

		$.ajax({
			url: nsSetup.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_provider_migration_rollback',
				nonce: nsSetup.nonce,
				job_id: rollbackJobId
			},
			success: function(response) {
				if (response.success) {
					alert('<?php esc_html_e( 'Migration rolled back successfully', 'nonprofitsuite' ); ?>');
					window.location.reload();
				} else {
					alert(response.data.message);
				}
			}
		});
	});
});
</script>
