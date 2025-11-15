<?php
/**
 * Setup Wizard View
 *
 * Multi-step wizard for initial setup.
 *
 * @package NonprofitSuite
 * @subpackage Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get organization ID (simplified - would use proper user org detection)
$organization_id = 1;

// Get wizard manager
require_once NS_PLUGIN_DIR . 'includes/helpers/class-setup-wizard-manager.php';
$manager = NS_Setup_Wizard_Manager::get_instance();

$steps           = $manager->get_steps();
$progress        = $manager->get_progress( $organization_id );
$current_step    = $manager->get_current_step( $organization_id );
$is_complete     = $manager->is_wizard_complete( $organization_id );

?>

<div class="wrap ns-setup-wizard">
	<h1><?php esc_html_e( 'NonprofitSuite Setup Wizard', 'nonprofitsuite' ); ?></h1>

	<?php if ( $is_complete ) : ?>
		<div class="notice notice-success">
			<p><?php esc_html_e( 'Setup complete! Your NonprofitSuite is ready to use.', 'nonprofitsuite' ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Progress Bar -->
	<div class="wizard-progress">
		<?php
		$step_index = 0;
		$total_steps = count( $steps );
		foreach ( $steps as $step_name => $step_data ) :
			$step_index++;
			$is_current = ( $step_name === $current_step );
			$is_completed = isset( $progress[ $step_name ] ) && $progress[ $step_name ]['step_status'] === 'completed';
			$is_skipped = isset( $progress[ $step_name ] ) && $progress[ $step_name ]['step_status'] === 'skipped';

			$class = array( 'wizard-step-indicator' );
			if ( $is_current ) {
				$class[] = 'current';
			}
			if ( $is_completed ) {
				$class[] = 'completed';
			}
			if ( $is_skipped ) {
				$class[] = 'skipped';
			}
			?>
			<div class="<?php echo esc_attr( implode( ' ', $class ) ); ?>">
				<div class="step-number"><?php echo esc_html( $step_index ); ?></div>
				<div class="step-title"><?php echo esc_html( $step_data['title'] ); ?></div>
			</div>
		<?php endforeach; ?>
	</div>

	<!-- Step Content -->
	<div class="wizard-content">
		<?php
		$step_data = $steps[ $current_step ];
		$saved_data = isset( $progress[ $current_step ] ) ? json_decode( $progress[ $current_step ]['step_data'], true ) : array();
		?>

		<div class="wizard-step active" data-step="<?php echo esc_attr( $current_step ); ?>">
			<h2><?php echo esc_html( $step_data['title'] ); ?></h2>
			<p class="description"><?php echo esc_html( $step_data['description'] ); ?></p>

			<?php if ( $current_step === 'complete' ) : ?>
				<div class="wizard-complete">
					<div class="dashicons dashicons-yes-alt"></div>
					<h3><?php esc_html_e( 'Congratulations!', 'nonprofitsuite' ); ?></h3>
					<p><?php esc_html_e( 'Your NonprofitSuite is now configured and ready to use.', 'nonprofitsuite' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite' ) ); ?>" class="button button-primary button-hero">
						<?php esc_html_e( 'Go to Dashboard', 'nonprofitsuite' ); ?>
					</a>
				</div>
			<?php else : ?>
				<form class="wizard-form">
					<table class="form-table">
						<?php foreach ( $step_data['fields'] as $field_name => $field_config ) : ?>
							<tr>
								<th scope="row">
									<label for="field-<?php echo esc_attr( $field_name ); ?>">
										<?php echo esc_html( $field_config['label'] ); ?>
										<?php if ( ! empty( $field_config['required'] ) ) : ?>
											<span class="required">*</span>
										<?php endif; ?>
									</label>
								</th>
								<td>
									<?php
									$value = $saved_data[ $field_name ] ?? '';

									switch ( $field_config['type'] ) {
										case 'text':
										case 'email':
										case 'url':
											?>
											<input type="<?php echo esc_attr( $field_config['type'] ); ?>"
												id="field-<?php echo esc_attr( $field_name ); ?>"
												name="<?php echo esc_attr( $field_name ); ?>"
												value="<?php echo esc_attr( $value ); ?>"
												class="regular-text"
												<?php echo ! empty( $field_config['required'] ) ? 'required' : ''; ?>>
											<?php
											break;

										case 'textarea':
											?>
											<textarea id="field-<?php echo esc_attr( $field_name ); ?>"
												name="<?php echo esc_attr( $field_name ); ?>"
												rows="4"
												class="large-text"
												<?php echo ! empty( $field_config['required'] ) ? 'required' : ''; ?>><?php echo esc_textarea( $value ); ?></textarea>
											<?php
											break;

										case 'select':
											?>
											<select id="field-<?php echo esc_attr( $field_name ); ?>"
												name="<?php echo esc_attr( $field_name ); ?>"
												<?php echo ! empty( $field_config['required'] ) ? 'required' : ''; ?>>
												<option value=""><?php esc_html_e( '-- Select --', 'nonprofitsuite' ); ?></option>
												<?php foreach ( $field_config['options'] as $option ) : ?>
													<option value="<?php echo esc_attr( $option ); ?>"
														<?php selected( $value, $option ); ?>>
														<?php echo esc_html( ucfirst( $option ) ); ?>
													</option>
												<?php endforeach; ?>
											</select>
											<?php
											break;

										case 'radio':
											foreach ( $field_config['options'] as $option ) :
												?>
												<label style="display: block; margin-bottom: 5px;">
													<input type="radio"
														name="<?php echo esc_attr( $field_name ); ?>"
														value="<?php echo esc_attr( $option ); ?>"
														<?php checked( $value, $option ); ?>>
													<?php echo esc_html( ucfirst( $option ) ); ?>
												</label>
												<?php
											endforeach;
											break;

										case 'checkbox':
											?>
											<label>
												<input type="checkbox"
													id="field-<?php echo esc_attr( $field_name ); ?>"
													name="<?php echo esc_attr( $field_name ); ?>"
													value="1"
													<?php checked( $value, 1 ); ?>>
												<?php echo esc_html( $field_config['label'] ); ?>
											</label>
											<?php
											break;
									}
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>

					<div class="wizard-actions">
						<button type="button" class="button button-secondary skip-step">
							<?php esc_html_e( 'Skip This Step', 'nonprofitsuite' ); ?>
						</button>
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Continue', 'nonprofitsuite' ); ?>
						</button>
					</div>

					<div class="wizard-message" style="display: none;"></div>
				</form>
			<?php endif; ?>
		</div>
	</div>
</div>

<style>
.ns-setup-wizard {
	max-width: 900px;
	margin: 20px auto;
}

.wizard-progress {
	display: flex;
	justify-content: space-between;
	margin: 40px 0;
	padding: 0 20px;
}

.wizard-step-indicator {
	flex: 1;
	text-align: center;
	position: relative;
}

.wizard-step-indicator::after {
	content: '';
	position: absolute;
	top: 20px;
	left: 50%;
	width: 100%;
	height: 2px;
	background: #ddd;
	z-index: -1;
}

.wizard-step-indicator:last-child::after {
	display: none;
}

.step-number {
	width: 40px;
	height: 40px;
	line-height: 40px;
	border-radius: 50%;
	background: #ddd;
	color: #666;
	font-weight: 700;
	margin: 0 auto 10px;
}

.wizard-step-indicator.current .step-number {
	background: #2271b1;
	color: #fff;
}

.wizard-step-indicator.completed .step-number {
	background: #00a32a;
	color: #fff;
}

.wizard-step-indicator.skipped .step-number {
	background: #dba617;
	color: #fff;
}

.step-title {
	font-size: 12px;
	color: #666;
}

.wizard-content {
	background: #fff;
	border: 1px solid #ccd0d4;
	padding: 40px;
	margin-top: 20px;
}

.wizard-step h2 {
	margin-top: 0;
}

.wizard-actions {
	margin-top: 30px;
	display: flex;
	justify-content: space-between;
}

.wizard-message {
	margin-top: 20px;
	padding: 10px;
	border-radius: 4px;
}

.wizard-message.success {
	background: #d7f1dd;
	border-left: 4px solid #00a32a;
}

.wizard-message.error {
	background: #fcf0f1;
	border-left: 4px solid #d63638;
}

.wizard-complete {
	text-align: center;
	padding: 60px 20px;
}

.wizard-complete .dashicons {
	font-size: 120px;
	width: 120px;
	height: 120px;
	color: #00a32a;
}

.required {
	color: #d63638;
}
</style>

<script>
jQuery(document).ready(function($) {
	$('.wizard-form').on('submit', function(e) {
		e.preventDefault();

		const $form = $(this);
		const $step = $form.closest('.wizard-step');
		const stepName = $step.data('step');

		// Collect form data
		const stepData = {};
		$form.serializeArray().forEach(function(field) {
			stepData[field.name] = field.value;
		});

		// Show loading
		$form.find('.button-primary').prop('disabled', true).text('<?php esc_html_e( 'Processing...', 'nonprofitsuite' ); ?>');

		$.ajax({
			url: nsSetup.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_setup_process_step',
				nonce: nsSetup.nonce,
				organization_id: <?php echo absint( $organization_id ); ?>,
				step_name: stepName,
				step_data: stepData
			},
			success: function(response) {
				if (response.success) {
					// Show success and reload
					$('.wizard-message')
						.addClass('success')
						.text(response.data.message)
						.show();

					setTimeout(function() {
						window.location.reload();
					}, 1000);
				} else {
					$('.wizard-message')
						.addClass('error')
						.text(response.data.message)
						.show();

					$form.find('.button-primary').prop('disabled', false).text('<?php esc_html_e( 'Continue', 'nonprofitsuite' ); ?>');
				}
			},
			error: function() {
				$('.wizard-message')
					.addClass('error')
					.text('<?php esc_html_e( 'An error occurred. Please try again.', 'nonprofitsuite' ); ?>')
					.show();

				$form.find('.button-primary').prop('disabled', false).text('<?php esc_html_e( 'Continue', 'nonprofitsuite' ); ?>');
			}
		});
	});

	$('.skip-step').on('click', function() {
		const $step = $(this).closest('.wizard-step');
		const stepName = $step.data('step');

		if (!confirm('<?php esc_html_e( 'Are you sure you want to skip this step?', 'nonprofitsuite' ); ?>')) {
			return;
		}

		$.ajax({
			url: nsSetup.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_setup_skip_step',
				nonce: nsSetup.nonce,
				organization_id: <?php echo absint( $organization_id ); ?>,
				step_name: stepName
			},
			success: function(response) {
				if (response.success) {
					window.location.reload();
				}
			}
		});
	});
});
</script>
