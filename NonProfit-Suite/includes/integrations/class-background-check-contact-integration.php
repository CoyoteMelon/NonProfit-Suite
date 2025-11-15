<?php
/**
 * Background Check Contact Integration
 *
 * Integrates background checks with contact profiles.
 *
 * @package NonprofitSuite
 * @subpackage Integrations
 * @since 1.18.0
 */

class NS_Background_Check_Contact_Integration {

	/**
	 * Background check manager
	 *
	 * @var NS_Background_Check_Manager
	 */
	private $manager;

	/**
	 * Constructor
	 */
	public function __construct() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'helpers/class-background-check-manager.php';
		$this->manager = new \NonprofitSuite\Helpers\NS_Background_Check_Manager();

		// Add background check widget to contact page
		add_action( 'ns_contact_sidebar_widgets', array( $this, 'render_background_check_widget' ), 15, 1 );

		// Add background check tab to contact page
		add_filter( 'ns_contact_tabs', array( $this, 'add_background_check_tab' ), 10, 1 );

		// Add AJAX handlers
		add_action( 'wp_ajax_ns_request_background_check', array( $this, 'ajax_request_background_check' ) );
	}

	/**
	 * Render background check widget on contact sidebar
	 *
	 * @param int $contact_id Contact ID
	 */
	public function render_background_check_widget( $contact_id ) {
		global $wpdb;
		$requests_table = $wpdb->prefix . 'ns_background_check_requests';

		// Get latest background check for this contact
		$latest_check = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $requests_table WHERE contact_id = %d ORDER BY created_at DESC LIMIT 1",
				$contact_id
			)
		);

		?>
		<div class="ns-background-check-widget postbox">
			<div class="postbox-header">
				<h3><?php esc_html_e( 'Background Check', 'nonprofitsuite' ); ?></h3>
			</div>
			<div class="inside">
				<?php if ( $latest_check ) : ?>
					<div class="background-check-status">
						<div class="status-item">
							<div class="status-label"><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></div>
							<div class="status-value">
								<span class="status-badge status-<?php echo esc_attr( $latest_check->request_status ); ?>">
									<?php echo esc_html( ucwords( str_replace( '_', ' ', $latest_check->request_status ) ) ); ?>
								</span>
							</div>
						</div>

						<div class="status-item">
							<div class="status-label"><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></div>
							<div class="status-value"><?php echo esc_html( ucfirst( $latest_check->check_type ) ); ?></div>
						</div>

						<?php if ( $latest_check->overall_status ) : ?>
							<div class="status-item">
								<div class="status-label"><?php esc_html_e( 'Result', 'nonprofitsuite' ); ?></div>
								<div class="status-value">
									<span class="result-badge result-<?php echo esc_attr( $latest_check->overall_status ); ?>">
										<?php echo esc_html( ucfirst( $latest_check->overall_status ) ); ?>
									</span>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( $latest_check->adjudication ) : ?>
							<div class="status-item">
								<div class="status-label"><?php esc_html_e( 'Adjudication', 'nonprofitsuite' ); ?></div>
								<div class="status-value">
									<?php
									$adjudication_labels = array(
										'approved'    => '✓ Approved',
										'pre_adverse' => '⚠ Pre-Adverse',
										'adverse'     => '✗ Adverse',
									);
									echo esc_html( $adjudication_labels[ $latest_check->adjudication ] ?? ucfirst( $latest_check->adjudication ) );
									?>
								</div>
							</div>
						<?php endif; ?>
					</div>

					<?php if ( 'in_progress' === $latest_check->request_status ) : ?>
						<div class="progress-section" style="margin-top: 15px;">
							<div class="progress-bar" style="width: 100%; height: 20px; background: #f0f0f1; border-radius: 10px; overflow: hidden;">
								<div class="progress-fill" style="width: <?php echo esc_attr( $latest_check->completion_percentage ); ?>%; height: 100%; background: #2271b1;"></div>
							</div>
							<small style="display: block; margin-top: 5px; color: #646970;">
								<?php echo esc_html( $latest_check->completion_percentage ); ?>% complete
							</small>
						</div>
					<?php endif; ?>

					<p style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 0.9em; color: #646970;">
						<?php
						if ( $latest_check->completed_at ) {
							printf(
								/* translators: %s: Time since completion */
								esc_html__( 'Completed %s', 'nonprofitsuite' ),
								esc_html( human_time_diff( strtotime( $latest_check->completed_at ), time() ) . ' ago' )
							);
						} else {
							printf(
								/* translators: %s: Time since request */
								esc_html__( 'Requested %s', 'nonprofitsuite' ),
								esc_html( human_time_diff( strtotime( $latest_check->created_at ), time() ) . ' ago' )
							);
						}
						?>
					</p>

					<p>
						<a href="<?php echo admin_url( 'admin.php?page=ns-background-checks' ); ?>" class="button button-secondary button-small">
							<?php esc_html_e( 'View All Checks', 'nonprofitsuite' ); ?>
						</a>
						<button type="button" class="button button-small" id="request-new-check" data-contact-id="<?php echo esc_attr( $contact_id ); ?>">
							<?php esc_html_e( 'New Check', 'nonprofitsuite' ); ?>
						</button>
					</p>
				<?php else : ?>
					<p class="description">
						<?php esc_html_e( 'No background check on record for this contact.', 'nonprofitsuite' ); ?>
					</p>
					<p>
						<button type="button" class="button button-primary" id="request-background-check" data-contact-id="<?php echo esc_attr( $contact_id ); ?>">
							<?php esc_html_e( 'Request Background Check', 'nonprofitsuite' ); ?>
						</button>
					</p>
				<?php endif; ?>

				<div id="background-check-status" style="margin-top: 10px; display: none;"></div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Request background check
			$('#request-background-check, #request-new-check').on('click', function() {
				const button = $(this);
				const contactId = button.data('contact-id');

				// Prompt for check type
				const checkType = prompt('Enter check type:\n- volunteer\n- staff\n- board', 'volunteer');
				if (!checkType) return;

				if (!['volunteer', 'staff', 'board'].includes(checkType.toLowerCase())) {
					alert('Invalid check type. Please enter: volunteer, staff, or board');
					return;
				}

				if (!confirm('Request a ' + checkType + ' background check for this contact?')) {
					return;
				}

				const status = $('#background-check-status');
				button.prop('disabled', true).text('Requesting...');
				status.hide().removeClass('success error');

				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'ns_request_background_check',
						nonce: '<?php echo wp_create_nonce( 'ns_background_check' ); ?>',
						contact_id: contactId,
						check_type: checkType.toLowerCase()
					},
					success: function(response) {
						if (response.success) {
							status.addClass('success').html(
								'✓ Background check requested!<br>' +
								'Request ID: ' + response.data.request_id + '<br>' +
								'Next: Send consent invitation from Background Checks dashboard.<br>' +
								'Refreshing page...'
							).show();
							setTimeout(() => location.reload(), 3000);
						} else {
							status.addClass('error').text('✗ ' + (response.data.message || 'Request failed')).show();
							button.prop('disabled', false).text('Request Background Check');
						}
					},
					error: function() {
						status.addClass('error').text('✗ Request failed').show();
						button.prop('disabled', false).text('Request Background Check');
					}
				});
			});
		});
		</script>

		<style>
		.background-check-status {
			display: grid;
			grid-template-columns: repeat(2, 1fr);
			gap: 10px;
			margin-bottom: 15px;
		}

		.background-check-status .status-item {
			padding: 10px;
			background: #f9f9f9;
			border-radius: 3px;
		}

		.background-check-status .status-label {
			font-weight: 600;
			color: #646970;
			font-size: 0.9em;
		}

		.background-check-status .status-value {
			font-size: 1.1em;
			color: #1d2327;
			margin-top: 5px;
		}

		.status-badge {
			display: inline-block;
			padding: 4px 8px;
			border-radius: 3px;
			font-size: 0.9em;
			font-weight: bold;
		}

		.status-pending {
			background: #f0f0f1;
			color: #646970;
		}

		.status-sent {
			background: #e5f6fd;
			color: #0073aa;
		}

		.status-in_progress {
			background: #fff4ce;
			color: #826200;
		}

		.status-completed {
			background: #d4f4dd;
			color: #0e6245;
		}

		.result-badge {
			display: inline-block;
			padding: 4px 8px;
			border-radius: 3px;
			font-size: 0.9em;
			font-weight: bold;
		}

		.result-clear {
			background: #d4f4dd;
			color: #0e6245;
		}

		.result-consider {
			background: #fff4ce;
			color: #826200;
		}

		.result-suspended {
			background: #ffe4e1;
			color: #a00;
		}

		#background-check-status.success {
			padding: 10px;
			background: #d4f4dd;
			border: 1px solid #46b450;
			border-radius: 3px;
			color: #0e6245;
			font-weight: bold;
		}

		#background-check-status.error {
			padding: 10px;
			background: #ffe4e1;
			border: 1px solid #dc3232;
			border-radius: 3px;
			color: #a00;
			font-weight: bold;
		}
		</style>
		<?php
	}

	/**
	 * Add background check tab to contact tabs
	 *
	 * @param array $tabs Existing tabs
	 * @return array Modified tabs
	 */
	public function add_background_check_tab( $tabs ) {
		$tabs['background_checks'] = array(
			'label'    => __( 'Background Checks', 'nonprofitsuite' ),
			'callback' => array( $this, 'render_background_check_tab' ),
			'priority' => 60,
		);

		return $tabs;
	}

	/**
	 * Render background check tab content
	 *
	 * @param int $contact_id Contact ID
	 */
	public function render_background_check_tab( $contact_id ) {
		global $wpdb;
		$requests_table = $wpdb->prefix . 'ns_background_check_requests';

		// Get all background checks for this contact
		$checks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $requests_table WHERE contact_id = %d ORDER BY created_at DESC",
				$contact_id
			)
		);

		?>
		<div class="ns-background-checks-tab">
			<h3><?php esc_html_e( 'Background Check History', 'nonprofitsuite' ); ?></h3>

			<?php if ( empty( $checks ) ) : ?>
				<p><?php esc_html_e( 'No background checks for this contact yet.', 'nonprofitsuite' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Package', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Result', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Adjudication', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Consent', 'nonprofitsuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $checks as $check ) : ?>
							<tr>
								<td><?php echo esc_html( date( 'M j, Y', strtotime( $check->created_at ) ) ); ?></td>
								<td><?php echo esc_html( ucfirst( $check->check_type ) ); ?></td>
								<td><?php echo esc_html( ucfirst( $check->package_name ) ); ?></td>
								<td>
									<span class="status-badge status-<?php echo esc_attr( $check->request_status ); ?>">
										<?php echo esc_html( ucwords( str_replace( '_', ' ', $check->request_status ) ) ); ?>
									</span>
								</td>
								<td>
									<?php if ( $check->overall_status ) : ?>
										<span class="result-badge result-<?php echo esc_attr( $check->overall_status ); ?>">
											<?php echo esc_html( ucfirst( $check->overall_status ) ); ?>
										</span>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $check->adjudication ? ucwords( str_replace( '_', ' ', $check->adjudication ) ) : '—' ); ?></td>
								<td>
									<?php if ( $check->consent_given ) : ?>
										✓ <?php echo esc_html( date( 'M j, Y', strtotime( $check->consent_given_at ) ) ); ?>
									<?php else : ?>
										Pending
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p style="margin-top: 15px;">
					<a href="<?php echo admin_url( 'admin.php?page=ns-background-checks' ); ?>" class="button">
						<?php esc_html_e( 'Manage All Background Checks', 'nonprofitsuite' ); ?>
					</a>
				</p>
			<?php endif; ?>

			<?php if ( ! empty( $checks ) && $checks[0]->reviewed_by ) : ?>
				<div class="review-notes" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 3px;">
					<h4><?php esc_html_e( 'Latest Review', 'nonprofitsuite' ); ?></h4>
					<p>
						<strong><?php esc_html_e( 'Reviewed by:', 'nonprofitsuite' ); ?></strong>
						<?php
						$reviewer = get_userdata( $checks[0]->reviewed_by );
						echo esc_html( $reviewer ? $reviewer->display_name : 'Unknown' );
						?>
					</p>
					<p>
						<strong><?php esc_html_e( 'Date:', 'nonprofitsuite' ); ?></strong>
						<?php echo esc_html( date( 'F j, Y g:i A', strtotime( $checks[0]->reviewed_at ) ) ); ?>
					</p>
					<?php if ( $checks[0]->review_notes ) : ?>
						<p>
							<strong><?php esc_html_e( 'Notes:', 'nonprofitsuite' ); ?></strong><br>
							<?php echo nl2br( esc_html( $checks[0]->review_notes ) ); ?>
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * AJAX: Request background check
	 */
	public function ajax_request_background_check() {
		check_ajax_referer( 'ns_background_check', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$contact_id = intval( $_POST['contact_id'] ?? 0 );
		$check_type = sanitize_text_field( $_POST['check_type'] ?? 'volunteer' );

		if ( ! $contact_id ) {
			wp_send_json_error( array( 'message' => 'Invalid contact ID' ) );
		}

		// Get default package based on check type
		global $wpdb;
		$settings = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ns_background_check_settings WHERE is_active = 1 LIMIT 1"
			)
		);

		$package = 'basic';
		if ( $settings ) {
			$package_field = 'default_' . $check_type . '_package';
			$package       = $settings->$package_field ?? 'basic';
		}

		$result = $this->manager->request_check( $contact_id, $check_type, $package );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( array( 'message' => $result['error'] ?? 'Request failed' ) );
		}
	}
}

// Initialize
new NS_Background_Check_Contact_Integration();
