<?php
/**
 * Wealth Research Contact Integration
 *
 * Integrates wealth research with contact profiles.
 *
 * @package NonprofitSuite
 * @subpackage Integrations
 * @since 1.18.0
 */

class NS_Wealth_Research_Contact_Integration {

	/**
	 * Wealth research manager
	 *
	 * @var NS_Wealth_Research_Manager
	 */
	private $manager;

	/**
	 * Constructor
	 */
	public function __construct() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'helpers/class-wealth-research-manager.php';
		$this->manager = new \NonprofitSuite\Helpers\NS_Wealth_Research_Manager();

		// Add wealth research widget to contact page
		add_action( 'ns_contact_sidebar_widgets', array( $this, 'render_wealth_research_widget' ), 10, 1 );

		// Add wealth research tab to contact page
		add_filter( 'ns_contact_tabs', array( $this, 'add_wealth_research_tab' ), 10, 1 );

		// Add AJAX handlers
		add_action( 'wp_ajax_ns_research_contact', array( $this, 'ajax_research_contact' ) );
	}

	/**
	 * Render wealth research widget on contact sidebar
	 *
	 * @param int $contact_id Contact ID
	 */
	public function render_wealth_research_widget( $contact_id ) {
		global $wpdb;

		// Get latest research summary
		$summary = $this->manager->get_research_summary( $contact_id );

		?>
		<div class="ns-wealth-research-widget postbox">
			<div class="postbox-header">
				<h3><?php esc_html_e( 'Wealth Research', 'nonprofitsuite' ); ?></h3>
			</div>
			<div class="inside">
				<?php if ( $summary['researched'] ) : ?>
					<div class="wealth-research-summary">
						<div class="summary-item">
							<div class="summary-label"><?php esc_html_e( 'Capacity Rating', 'nonprofitsuite' ); ?></div>
							<div class="summary-value">
								<span class="capacity-badge capacity-<?php echo esc_attr( strtolower( str_replace( '+', 'plus', $summary['capacity_rating'] ) ) ); ?>">
									<?php echo esc_html( $summary['capacity_rating'] ); ?>
								</span>
							</div>
						</div>

						<div class="summary-item">
							<div class="summary-label"><?php esc_html_e( 'Income Range', 'nonprofitsuite' ); ?></div>
							<div class="summary-value"><?php echo esc_html( $summary['income_range'] ?: '—' ); ?></div>
						</div>

						<div class="summary-item">
							<div class="summary-label"><?php esc_html_e( 'Net Worth Range', 'nonprofitsuite' ); ?></div>
							<div class="summary-value"><?php echo esc_html( $summary['net_worth_range'] ?: '—' ); ?></div>
						</div>

						<div class="summary-item">
							<div class="summary-label"><?php esc_html_e( 'Philanthropic Score', 'nonprofitsuite' ); ?></div>
							<div class="summary-value"><?php echo esc_html( $summary['philanthropic_score'] ); ?>/100</div>
						</div>
					</div>

					<p style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 0.9em; color: #646970;">
						<?php
						printf(
							/* translators: %s: Time since last research */
							esc_html__( 'Last researched %s', 'nonprofitsuite' ),
							esc_html( human_time_diff( strtotime( $summary['last_research'] ), time() ) . ' ago' )
						);
						?>
						<br>
						<?php if ( ! $summary['is_current'] ) : ?>
							<span style="color: #d63638;">⚠ <?php esc_html_e( 'Data expired', 'nonprofitsuite' ); ?></span>
						<?php endif; ?>
					</p>

					<p>
						<button type="button" class="button button-secondary button-small" id="refresh-research" data-contact-id="<?php echo esc_attr( $contact_id ); ?>">
							<?php esc_html_e( 'Refresh Research', 'nonprofitsuite' ); ?>
						</button>
						<a href="<?php echo admin_url( 'admin.php?page=ns-wealth-research' ); ?>" class="button button-small">
							<?php esc_html_e( 'View History', 'nonprofitsuite' ); ?>
						</a>
					</p>
				<?php else : ?>
					<p class="description">
						<?php esc_html_e( 'No wealth research available for this contact.', 'nonprofitsuite' ); ?>
					</p>
					<p>
						<button type="button" class="button button-primary" id="research-contact" data-contact-id="<?php echo esc_attr( $contact_id ); ?>">
							<?php esc_html_e( 'Research This Contact', 'nonprofitsuite' ); ?>
						</button>
					</p>
				<?php endif; ?>

				<div id="research-status" style="margin-top: 10px; display: none;"></div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Research contact
			$('#research-contact, #refresh-research').on('click', function() {
				const button = $(this);
				const contactId = button.data('contact-id');
				const status = $('#research-status');

				if (!confirm('Research this contact? This will use your API quota.')) {
					return;
				}

				button.prop('disabled', true).text('Researching...');
				status.removeClass('success error').show().text('Performing wealth research...');

				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'ns_research_contact',
						nonce: '<?php echo wp_create_nonce( 'ns_wealth_research' ); ?>',
						contact_id: contactId,
						depth: 'basic'
					},
					success: function(response) {
						if (response.success) {
							status.addClass('success').text('✓ Research complete! Refreshing...');
							setTimeout(() => location.reload(), 1000);
						} else {
							status.addClass('error').text('✗ ' + (response.data.message || 'Research failed'));
							button.prop('disabled', false).text('Research This Contact');
						}
					},
					error: function() {
						status.addClass('error').text('✗ Research failed');
						button.prop('disabled', false).text('Research This Contact');
					}
				});
			});
		});
		</script>

		<style>
		.wealth-research-summary {
			display: grid;
			grid-template-columns: repeat(2, 1fr);
			gap: 10px;
			margin-bottom: 15px;
		}

		.wealth-research-summary .summary-item {
			padding: 10px;
			background: #f9f9f9;
			border-radius: 3px;
		}

		.wealth-research-summary .summary-label {
			font-weight: 600;
			color: #646970;
			font-size: 0.9em;
		}

		.wealth-research-summary .summary-value {
			font-size: 1.1em;
			color: #1d2327;
			margin-top: 5px;
		}

		.capacity-badge {
			display: inline-block;
			padding: 4px 8px;
			border-radius: 3px;
			font-weight: bold;
			font-size: 0.9em;
		}

		.capacity-aplus {
			background: #d4f4dd;
			color: #0e6245;
		}

		.capacity-a {
			background: #e5f6fd;
			color: #0073aa;
		}

		.capacity-b {
			background: #fff4ce;
			color: #826200;
		}

		.capacity-c {
			background: #ffe4e1;
			color: #a00;
		}

		.capacity-d {
			background: #f0f0f1;
			color: #646970;
		}

		#research-status.success {
			color: #46b450;
			font-weight: bold;
		}

		#research-status.error {
			color: #dc3232;
			font-weight: bold;
		}
		</style>
		<?php
	}

	/**
	 * Add wealth research tab to contact tabs
	 *
	 * @param array $tabs Existing tabs
	 * @return array Modified tabs
	 */
	public function add_wealth_research_tab( $tabs ) {
		$tabs['wealth_research'] = array(
			'label'    => __( 'Wealth Research', 'nonprofitsuite' ),
			'callback' => array( $this, 'render_wealth_research_tab' ),
			'priority' => 50,
		);

		return $tabs;
	}

	/**
	 * Render wealth research tab content
	 *
	 * @param int $contact_id Contact ID
	 */
	public function render_wealth_research_tab( $contact_id ) {
		global $wpdb;
		$reports_table = $wpdb->prefix . 'ns_wealth_research_reports';

		// Get all reports for this contact
		$reports = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $reports_table WHERE contact_id = %d ORDER BY researched_at DESC",
				$contact_id
			)
		);

		?>
		<div class="ns-wealth-research-tab">
			<h3><?php esc_html_e( 'Research History', 'nonprofitsuite' ); ?></h3>

			<?php if ( empty( $reports ) ) : ?>
				<p><?php esc_html_e( 'No research reports for this contact yet.', 'nonprofitsuite' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Provider', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Capacity', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Income Range', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Net Worth', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Cost', 'nonprofitsuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $reports as $report ) : ?>
							<tr>
								<td><?php echo esc_html( date( 'M j, Y', strtotime( $report->researched_at ) ) ); ?></td>
								<td><?php echo esc_html( ucfirst( $report->provider ) ); ?></td>
								<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $report->report_type ) ) ); ?></td>
								<td>
									<span class="capacity-badge capacity-<?php echo esc_attr( strtolower( str_replace( '+', 'plus', $report->giving_capacity_rating ) ) ); ?>">
										<?php echo esc_html( $report->giving_capacity_rating ?: '—' ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $report->estimated_income_range ?: '—' ); ?></td>
								<td><?php echo esc_html( $report->estimated_net_worth_range ?: '—' ); ?></td>
								<td>$<?php echo number_format( $report->cost, 2 ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * AJAX: Research contact
	 */
	public function ajax_research_contact() {
		check_ajax_referer( 'ns_wealth_research', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$contact_id = intval( $_POST['contact_id'] ?? 0 );
		$depth      = sanitize_text_field( $_POST['depth'] ?? 'basic' );

		if ( ! $contact_id ) {
			wp_send_json_error( array( 'message' => 'Invalid contact ID' ) );
		}

		$result = $this->manager->research_contact( $contact_id, $depth );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( array( 'message' => $result['error'] ?? 'Research failed' ) );
		}
	}
}

// Initialize
new NS_Wealth_Research_Contact_Integration();
