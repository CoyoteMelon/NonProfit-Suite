<?php
/**
 * Beta Admin Dashboard
 *
 * Admin dashboard for managing beta program.
 *
 * @package    NonprofitSuite
 * @subpackage Beta/Admin
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonprofitSuite_Beta_Admin_Dashboard Class
 *
 * Handles beta program admin dashboard and management pages.
 */
class NonprofitSuite_Beta_Admin_Dashboard {

	/**
	 * Singleton instance
	 *
	 * @var NonprofitSuite_Beta_Admin_Dashboard
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return NonprofitSuite_Beta_Admin_Dashboard
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Add menu pages
	 */
	public function add_menu_pages() {
		// Main beta dashboard
		add_menu_page(
			__( 'Beta Program', 'nonprofitsuite' ),
			__( 'Beta Program', 'nonprofitsuite' ),
			'manage_options',
			'beta-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-groups',
			60
		);

		// Applications submenu
		add_submenu_page(
			'beta-dashboard',
			__( 'Beta Applications', 'nonprofitsuite' ),
			__( 'Applications', 'nonprofitsuite' ),
			'manage_options',
			'beta-applications',
			array( $this, 'render_applications' )
		);

		// Surveys submenu
		add_submenu_page(
			'beta-dashboard',
			__( 'Survey Results', 'nonprofitsuite' ),
			__( 'Surveys', 'nonprofitsuite' ),
			'manage_options',
			'beta-surveys',
			array( $this, 'render_surveys' )
		);

		// Feedback submenu
		add_submenu_page(
			'beta-dashboard',
			__( 'Beta Feedback', 'nonprofitsuite' ),
			__( 'Feedback', 'nonprofitsuite' ),
			'manage_options',
			'beta-feedback',
			array( $this, 'render_feedback' )
		);

		// Settings submenu
		add_submenu_page(
			'beta-dashboard',
			__( 'Beta Settings', 'nonprofitsuite' ),
			__( 'Settings', 'nonprofitsuite' ),
			'manage_options',
			'beta-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Enqueue admin scripts
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( strpos( $hook, 'beta-' ) === false ) {
			return;
		}

		wp_enqueue_style( 'ns-beta-admin', plugins_url( 'assets/css/beta-admin.css', dirname( dirname( __FILE__ ) ) ), array(), '1.0.0' );
		wp_enqueue_script( 'ns-beta-admin', plugins_url( 'assets/js/beta-admin.js', dirname( dirname( __FILE__ ) ) ), array( 'jquery' ), '1.0.0', true );
	}

	/**
	 * Render dashboard
	 */
	public function render_dashboard() {
		$app_manager = NonprofitSuite_Beta_Application_Manager::get_instance();
		$survey_manager = NonprofitSuite_Beta_Survey_Manager::get_instance();
		$feedback_manager = NonprofitSuite_Beta_Feedback_Manager::get_instance();

		$app_stats = $app_manager->get_statistics();
		$survey_stats = $survey_manager->get_statistics();
		$feedback_stats = $feedback_manager->get_statistics();

		$settings = get_option( 'ns_beta_program_settings', array() );
		?>
		<div class="wrap">
			<h1><?php _e( 'Beta Program Dashboard', 'nonprofitsuite' ); ?></h1>

			<!-- Summary Stats -->
			<div class="ns-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">

				<!-- Applications -->
				<div class="ns-stat-card" style="background: white; padding: 20px; border-left: 4px solid #2271b1; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
					<h3 style="margin: 0 0 10px; color: #666; font-size: 14px; font-weight: normal;">Total Applications</h3>
					<div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo absint( $app_stats['total'] ); ?></div>
					<div style="margin-top: 10px; font-size: 13px; color: #666;">
						<?php echo absint( $app_stats['501c3_approved'] ); ?> 501(c)(3) | <?php echo absint( $app_stats['prenp_approved'] ); ?> Pre-Nonprofit
					</div>
				</div>

				<!-- Slots Available -->
				<div class="ns-stat-card" style="background: white; padding: 20px; border-left: 4px solid #10b981; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
					<h3 style="margin: 0 0 10px; color: #666; font-size: 14px; font-weight: normal;">501(c)(3) Slots</h3>
					<div style="font-size: 32px; font-weight: bold; color: #10b981;">
						<?php echo absint( max( 0, ( $settings['max_501c3_slots'] ?? 500 ) - $app_stats['501c3_approved'] ) ); ?>
					</div>
					<div style="margin-top: 10px; font-size: 13px; color: #666;">
						of <?php echo absint( $settings['max_501c3_slots'] ?? 500 ); ?> remaining
					</div>
				</div>

				<!-- Surveys -->
				<div class="ns-stat-card" style="background: white; padding: 20px; border-left: 4px solid #f59e0b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
					<h3 style="margin: 0 0 10px; color: #666; font-size: 14px; font-weight: normal;">Survey Responses</h3>
					<div style="font-size: 32px; font-weight: bold; color: #f59e0b;"><?php echo absint( $survey_stats['total_surveys'] ?? 0 ); ?></div>
					<div style="margin-top: 10px; font-size: 13px; color: #666;">
						Avg Satisfaction: <?php echo isset( $survey_stats['averages']['avg_satisfaction'] ) ? esc_html( number_format( $survey_stats['averages']['avg_satisfaction'], 1 ) ) : 'N/A'; ?>/5
					</div>
				</div>

				<!-- Feedback -->
				<div class="ns-stat-card" style="background: white; padding: 20px; border-left: 4px solid #ef4444; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
					<h3 style="margin: 0 0 10px; color: #666; font-size: 14px; font-weight: normal;">Feedback Items</h3>
					<div style="font-size: 32px; font-weight: bold; color: #ef4444;"><?php echo absint( $feedback_stats['total'] ); ?></div>
					<div style="margin-top: 10px; font-size: 13px; color: #666;">
						<?php
						$pending = 0;
						foreach ( $feedback_stats['by_status'] as $status ) {
							if ( $status['status'] === 'new' ) {
								$pending = absint( $status['count'] );
							}
						}
						echo absint( $pending ) . ' pending';
						?>
					</div>
				</div>
			</div>

			<!-- Recent Activity -->
			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px;">

				<!-- Recent Applications -->
				<div style="background: white; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
					<h2 style="margin: 0 0 15px; font-size: 18px;">Recent Applications</h2>
					<?php $this->render_recent_applications(); ?>
					<p style="margin-top: 15px;">
						<a href="<?php echo admin_url( 'admin.php?page=beta-applications' ); ?>" class="button">View All Applications →</a>
					</p>
				</div>

				<!-- Recent Feedback -->
				<div style="background: white; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
					<h2 style="margin: 0 0 15px; font-size: 18px;">Recent Feedback</h2>
					<?php $this->render_recent_feedback(); ?>
					<p style="margin-top: 15px;">
						<a href="<?php echo admin_url( 'admin.php?page=beta-feedback' ); ?>" class="button">View All Feedback →</a>
					</p>
				</div>
			</div>

			<!-- Survey Insights -->
			<div style="background: white; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-top: 20px;">
				<h2 style="margin: 0 0 15px; font-size: 18px;">Survey Insights</h2>
				<?php $this->render_survey_insights( $survey_stats ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render recent applications
	 */
	private function render_recent_applications() {
		global $wpdb;

		$applications = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}ns_beta_applications
			ORDER BY application_date DESC
			LIMIT 5",
			ARRAY_A
		);

		if ( empty( $applications ) ) {
			echo '<p>No applications yet.</p>';
			return;
		}

		echo '<table class="widefat" style="width: 100%;">';
		echo '<thead><tr><th>Organization</th><th>Status</th><th>Date</th></tr></thead><tbody>';
		foreach ( $applications as $app ) {
			$status_color = array(
				'approved' => '#10b981',
				'pending'  => '#f59e0b',
				'waitlist' => '#6b7280',
				'rejected' => '#ef4444',
			);
			echo '<tr>';
			echo '<td><strong>' . esc_html( $app['organization_name'] ) . '</strong><br><small>' . esc_html( $app['contact_email'] ) . '</small></td>';
			echo '<td><span style="color: ' . $status_color[ $app['status'] ] . ';">●</span> ' . ucfirst( $app['status'] ) . '</td>';
			echo '<td>' . date( 'M j, Y', strtotime( $app['application_date'] ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Render recent feedback
	 */
	private function render_recent_feedback() {
		global $wpdb;

		$feedback = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}ns_beta_feedback
			ORDER BY submitted_at DESC
			LIMIT 5",
			ARRAY_A
		);

		if ( empty( $feedback ) ) {
			echo '<p>No feedback yet.</p>';
			return;
		}

		echo '<table class="widefat" style="width: 100%;">';
		echo '<thead><tr><th>Type</th><th>Subject</th><th>Date</th></tr></thead><tbody>';
		foreach ( $feedback as $item ) {
			$icons = array(
				'bug'             => '🐛',
				'feature_request' => '💡',
				'improvement'     => '✨',
				'question'        => '❓',
				'praise'          => '👏',
			);
			echo '<tr>';
			echo '<td>' . ( $icons[ $item['feedback_type'] ] ?? '' ) . ' ' . ucfirst( str_replace( '_', ' ', $item['feedback_type'] ) ) . '</td>';
			echo '<td>' . esc_html( wp_trim_words( $item['subject'] ?: $item['message'], 8 ) ) . '</td>';
			echo '<td>' . human_time_diff( strtotime( $item['submitted_at'] ) ) . ' ago</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Render survey insights
	 */
	private function render_survey_insights( $stats ) {
		if ( empty( $stats['feature_ratings'] ) ) {
			echo '<p>No survey data yet.</p>';
			return;
		}

		echo '<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">';

		$features = array(
			'meetings'        => 'Meetings',
			'documents'       => 'Documents',
			'treasury'        => 'Treasury',
			'donors'          => 'Donors',
			'volunteers'      => 'Volunteers',
			'compliance'      => 'Compliance',
			'calendar'        => 'Calendar',
			'email'           => 'Email',
			'payments'        => 'Payments',
			'membership'      => 'Membership',
			'board'           => 'Board',
			'communications'  => 'Communications',
			'events'          => 'Events',
			'grants'          => 'Grants',
			'inventory'       => 'Inventory',
			'programs'        => 'Programs',
		);

		foreach ( $features as $key => $label ) {
			$rating = $stats['feature_ratings'][ $key ] ?? null;
			if ( $rating ) {
				$percentage = ( $rating / 5 ) * 100;
				$color = $rating >= 4 ? '#10b981' : ( $rating >= 3 ? '#f59e0b' : '#ef4444' );

				echo '<div style="padding: 12px; background: #f9fafb; border-radius: 4px;">';
				echo '<div style="font-weight: 600; margin-bottom: 5px;">' . $label . '</div>';
				echo '<div style="display: flex; align-items: center; gap: 8px;">';
				echo '<div style="flex: 1; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">';
				echo '<div style="width: ' . $percentage . '%; height: 100%; background: ' . $color . ';"></div>';
				echo '</div>';
				echo '<span style="font-weight: bold; color: ' . $color . ';">' . number_format( $rating, 1 ) . '</span>';
				echo '</div>';
				echo '</div>';
			}
		}

		echo '</div>';
	}

	/**
	 * Render applications page
	 */
	public function render_applications() {
		global $wpdb;

		// Handle bulk actions
		if ( isset( $_POST['action'] ) && isset( $_POST['application_ids'] ) ) {
			check_admin_referer( 'bulk-beta-applications' );

			$action = sanitize_text_field( $_POST['action'] );
			$ids = array_map( 'intval', $_POST['application_ids'] );

			$app_manager = NonprofitSuite_Beta_Application_Manager::get_instance();

			foreach ( $ids as $id ) {
				switch ( $action ) {
					case 'approve':
						$app_manager->update_application_status( $id, 'approved' );
						break;
					case 'reject':
						$app_manager->update_application_status( $id, 'rejected' );
						break;
					case 'waitlist':
						$app_manager->update_application_status( $id, 'waitlist' );
						break;
					case 'delete':
						$wpdb->delete( $wpdb->prefix . 'ns_beta_applications', array( 'id' => $id ), array( '%d' ) );
						break;
				}
			}

			echo '<div class="notice notice-success"><p>' . count( $ids ) . ' application(s) updated.</p></div>';
		}

		// Get filter parameters
		$status_filter = isset( $_GET['status_filter'] ) ? sanitize_text_field( $_GET['status_filter'] ) : '';
		$type_filter = isset( $_GET['type_filter'] ) ? sanitize_text_field( $_GET['type_filter'] ) : '';
		$state_filter = isset( $_GET['state_filter'] ) ? sanitize_text_field( $_GET['state_filter'] ) : '';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';

		// Build query
		$where = array( '1=1' );

		if ( $status_filter ) {
			$where[] = $wpdb->prepare( 'status = %s', $status_filter );
		}

		if ( $type_filter ) {
			$where[] = $wpdb->prepare( 'slot_type = %s', $type_filter );
		}

		if ( $state_filter ) {
			$where[] = $wpdb->prepare( 'state = %s', $state_filter );
		}

		if ( $search ) {
			$where[] = $wpdb->prepare( '(organization_name LIKE %s OR contact_email LIKE %s OR contact_name LIKE %s)', '%' . $wpdb->esc_like( $search ) . '%', '%' . $wpdb->esc_like( $search ) . '%', '%' . $wpdb->esc_like( $search ) . '%' );
		}

		$where_sql = implode( ' AND ', $where );

		// Get applications
		$applications = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}ns_beta_applications
			WHERE {$where_sql}
			ORDER BY application_date DESC",
			ARRAY_A
		);

		// Get stats for header
		$stats = $wpdb->get_row(
			"SELECT
				COUNT(*) as total,
				SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
				SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
				SUM(CASE WHEN status = 'waitlist' THEN 1 ELSE 0 END) as waitlist,
				SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
			FROM {$wpdb->prefix}ns_beta_applications",
			ARRAY_A
		);

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">Beta Applications</h1>
			<a href="#" class="page-title-action">Export to CSV</a>
			<hr class="wp-header-end">

			<!-- Stats Bar -->
			<div style="background: white; padding: 15px; margin: 20px 0; display: flex; gap: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
				<div><strong>Total:</strong> <?php echo $stats['total']; ?></div>
				<div><strong>Pending:</strong> <span style="color: #f59e0b;"><?php echo $stats['pending']; ?></span></div>
				<div><strong>Approved:</strong> <span style="color: #10b981;"><?php echo $stats['approved']; ?></span></div>
				<div><strong>Waitlist:</strong> <span style="color: #6b7280;"><?php echo $stats['waitlist']; ?></span></div>
				<div><strong>Rejected:</strong> <span style="color: #ef4444;"><?php echo $stats['rejected']; ?></span></div>
			</div>

			<!-- Filters -->
			<form method="get" style="background: white; padding: 15px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
				<input type="hidden" name="page" value="beta-applications">

				<div style="display: flex; gap: 10px; align-items: flex-end;">
					<div>
						<label><strong>Status:</strong></label><br>
						<select name="status_filter">
							<option value="">All Statuses</option>
							<option value="pending" <?php selected( $status_filter, 'pending' ); ?>>Pending</option>
							<option value="approved" <?php selected( $status_filter, 'approved' ); ?>>Approved</option>
							<option value="waitlist" <?php selected( $status_filter, 'waitlist' ); ?>>Waitlist</option>
							<option value="rejected" <?php selected( $status_filter, 'rejected' ); ?>>Rejected</option>
						</select>
					</div>

					<div>
						<label><strong>Type:</strong></label><br>
						<select name="type_filter">
							<option value="">All Types</option>
							<option value="501c3" <?php selected( $type_filter, '501c3' ); ?>>501(c)(3)</option>
							<option value="pre_nonprofit" <?php selected( $type_filter, 'pre_nonprofit' ); ?>>Pre-Nonprofit</option>
						</select>
					</div>

					<div>
						<label><strong>State:</strong></label><br>
						<input type="text" name="state_filter" value="<?php echo esc_attr( $state_filter ); ?>" placeholder="e.g., CA" style="width: 60px;">
					</div>

					<div style="flex: 1;">
						<label><strong>Search:</strong></label><br>
						<input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Organization, email, or contact name..." style="width: 100%;">
					</div>

					<div>
						<button type="submit" class="button">Filter</button>
						<a href="<?php echo admin_url( 'admin.php?page=beta-applications' ); ?>" class="button">Reset</a>
					</div>
				</div>
			</form>

			<!-- Applications Table -->
			<form method="post">
				<?php wp_nonce_field( 'bulk-beta-applications' ); ?>

				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<select name="action">
							<option value="">Bulk Actions</option>
							<option value="approve">Approve</option>
							<option value="waitlist">Move to Waitlist</option>
							<option value="reject">Reject</option>
							<option value="delete">Delete</option>
						</select>
						<button type="submit" class="button action">Apply</button>
					</div>
				</div>

				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<td class="check-column"><input type="checkbox" id="select-all"></td>
							<th>Organization</th>
							<th>Contact</th>
							<th>Type</th>
							<th>State</th>
							<th>Status</th>
							<th>Date</th>
							<th>License</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $applications ) ) : ?>
							<tr>
								<td colspan="9" style="text-align: center; padding: 40px;">
									No applications found.
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $applications as $app ) : ?>
								<tr>
									<th class="check-column">
										<input type="checkbox" name="application_ids[]" value="<?php echo absint( $app['id'] ); ?>">
									</th>
									<td>
										<strong><?php echo esc_html( $app['organization_name'] ); ?></strong>
										<?php if ( $app['ein'] ) : ?>
											<br><small>EIN: <?php echo esc_html( $app['ein'] ); ?></small>
										<?php endif; ?>
									</td>
									<td>
										<?php echo esc_html( $app['contact_name'] ); ?><br>
										<small><a href="mailto:<?php echo esc_attr( $app['contact_email'] ); ?>"><?php echo esc_html( $app['contact_email'] ); ?></a></small>
										<?php if ( $app['contact_phone'] ) : ?>
											<br><small><?php echo esc_html( $app['contact_phone'] ); ?></small>
										<?php endif; ?>
									</td>
									<td>
										<?php
										$type_label = $app['slot_type'] === '501c3' ? '501(c)(3)' : 'Pre-NP';
										$type_color = $app['slot_type'] === '501c3' ? '#2271b1' : '#f59e0b';
										?>
										<span style="background: <?php echo esc_attr( $type_color ); ?>; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">
											<?php echo esc_html( $type_label ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $app['state'] ); ?></td>
									<td>
										<?php
										$status_colors = array(
											'pending'  => array( 'bg' => '#fff3cd', 'text' => '#856404' ),
											'approved' => array( 'bg' => '#d1fae5', 'text' => '#065f46' ),
											'waitlist' => array( 'bg' => '#e5e7eb', 'text' => '#374151' ),
											'rejected' => array( 'bg' => '#fee2e2', 'text' => '#991b1b' ),
										);
										$colors = $status_colors[ $app['status'] ] ?? array( 'bg' => '#e5e7eb', 'text' => '#374151' );
										?>
										<span style="background: <?php echo esc_attr( $colors['bg'] ); ?>; color: <?php echo esc_attr( $colors['text'] ); ?>; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600;">
											<?php echo esc_html( ucfirst( $app['status'] ) ); ?>
										</span>
									</td>
									<td>
										<?php echo esc_html( date( 'M j, Y', strtotime( $app['application_date'] ) ) ); ?><br>
										<small><?php echo esc_html( human_time_diff( strtotime( $app['application_date'] ) ) ); ?> ago</small>
									</td>
									<td>
										<?php if ( $app['license_key'] ) : ?>
											<code style="font-size: 11px;"><?php echo esc_html( substr( $app['license_key'], 0, 12 ) . '...' ); ?></code><br>
											<?php if ( $app['license_activated'] ) : ?>
												<small style="color: #10b981;">✓ Activated</small>
											<?php else : ?>
												<small style="color: #6b7280;">Not activated</small>
											<?php endif; ?>
										<?php else : ?>
											<small style="color: #9ca3af;">No license</small>
										<?php endif; ?>
									</td>
									<td>
										<a href="#" class="button button-small">View Details</a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</form>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#select-all').on('click', function() {
				$('input[name="application_ids[]"]').prop('checked', this.checked);
			});
		});
		</script>
		<?php
	}

	/**
	 * Render surveys page
	 */
	public function render_surveys() {
		global $wpdb;

		// Get filter parameters
		$application_filter = isset( $_GET['application_id'] ) ? intval( $_GET['application_id'] ) : 0;
		$survey_number_filter = isset( $_GET['survey_number'] ) ? intval( $_GET['survey_number'] ) : 0;

		// Build query
		$where = array( '1=1' );

		if ( $application_filter ) {
			$where[] = $wpdb->prepare( 's.application_id = %d', $application_filter );
		}

		if ( $survey_number_filter ) {
			$where[] = $wpdb->prepare( 's.survey_number = %d', $survey_number_filter );
		}

		$where_sql = implode( ' AND ', $where );

		// Get survey responses
		$surveys = $wpdb->get_results(
			"SELECT s.*, a.organization_name, a.contact_name, a.slot_type
			FROM {$wpdb->prefix}ns_beta_surveys s
			LEFT JOIN {$wpdb->prefix}ns_beta_applications a ON s.application_id = a.id
			WHERE {$where_sql}
			ORDER BY s.submitted_at DESC
			LIMIT 100",
			ARRAY_A
		);

		// Get aggregated stats
		$stats = $wpdb->get_row(
			"SELECT
				COUNT(*) as total_surveys,
				AVG(overall_satisfaction) as avg_satisfaction,
				AVG(ease_of_use) as avg_ease,
				AVG(feature_completeness) as avg_completeness,
				AVG(performance) as avg_performance,
				AVG(support_quality) as avg_support,
				COUNT(DISTINCT application_id) as unique_testers
			FROM {$wpdb->prefix}ns_beta_surveys",
			ARRAY_A
		);

		// Get feature ratings aggregate
		$feature_ratings = $wpdb->get_results(
			"SELECT
				AVG(JSON_EXTRACT(feature_ratings, '$.meetings')) as meetings,
				AVG(JSON_EXTRACT(feature_ratings, '$.documents')) as documents,
				AVG(JSON_EXTRACT(feature_ratings, '$.treasury')) as treasury,
				AVG(JSON_EXTRACT(feature_ratings, '$.donors')) as donors,
				AVG(JSON_EXTRACT(feature_ratings, '$.volunteers')) as volunteers,
				AVG(JSON_EXTRACT(feature_ratings, '$.compliance')) as compliance,
				AVG(JSON_EXTRACT(feature_ratings, '$.calendar')) as calendar,
				AVG(JSON_EXTRACT(feature_ratings, '$.email')) as email,
				AVG(JSON_EXTRACT(feature_ratings, '$.payments')) as payments,
				AVG(JSON_EXTRACT(feature_ratings, '$.membership')) as membership,
				AVG(JSON_EXTRACT(feature_ratings, '$.board')) as board,
				AVG(JSON_EXTRACT(feature_ratings, '$.communications')) as communications,
				AVG(JSON_EXTRACT(feature_ratings, '$.events')) as events,
				AVG(JSON_EXTRACT(feature_ratings, '$.grants')) as grants,
				AVG(JSON_EXTRACT(feature_ratings, '$.inventory')) as inventory,
				AVG(JSON_EXTRACT(feature_ratings, '$.programs')) as programs
			FROM {$wpdb->prefix}ns_beta_surveys
			WHERE feature_ratings IS NOT NULL AND feature_ratings != ''",
			ARRAY_A
		);

		// Get applications for filter dropdown
		$applications = $wpdb->get_results(
			"SELECT id, organization_name, contact_name
			FROM {$wpdb->prefix}ns_beta_applications
			WHERE status = 'approved'
			ORDER BY organization_name",
			ARRAY_A
		);

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">Survey Results</h1>
			<a href="#" class="page-title-action">Export to CSV</a>
			<hr class="wp-header-end">

			<!-- Overall Stats -->
			<div style="background: white; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
				<h2 style="margin-top: 0;">Overall Survey Statistics</h2>
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
					<div>
						<div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo number_format( $stats['total_surveys'] ); ?></div>
						<div style="color: #6b7280;">Total Surveys</div>
					</div>
					<div>
						<div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo number_format( $stats['unique_testers'] ); ?></div>
						<div style="color: #6b7280;">Active Testers</div>
					</div>
					<div>
						<div style="font-size: 32px; font-weight: bold; color: <?php echo $stats['avg_satisfaction'] >= 4 ? '#10b981' : ( $stats['avg_satisfaction'] >= 3 ? '#f59e0b' : '#ef4444' ); ?>">
							<?php echo number_format( $stats['avg_satisfaction'], 1 ); ?>/5
						</div>
						<div style="color: #6b7280;">Avg Satisfaction</div>
					</div>
					<div>
						<div style="font-size: 32px; font-weight: bold; color: <?php echo $stats['avg_ease'] >= 4 ? '#10b981' : ( $stats['avg_ease'] >= 3 ? '#f59e0b' : '#ef4444' ); ?>">
							<?php echo number_format( $stats['avg_ease'], 1 ); ?>/5
						</div>
						<div style="color: #6b7280;">Ease of Use</div>
					</div>
					<div>
						<div style="font-size: 32px; font-weight: bold; color: <?php echo $stats['avg_completeness'] >= 4 ? '#10b981' : ( $stats['avg_completeness'] >= 3 ? '#f59e0b' : '#ef4444' ); ?>">
							<?php echo number_format( $stats['avg_completeness'], 1 ); ?>/5
						</div>
						<div style="color: #6b7280;">Feature Completeness</div>
					</div>
					<div>
						<div style="font-size: 32px; font-weight: bold; color: <?php echo $stats['avg_performance'] >= 4 ? '#10b981' : ( $stats['avg_performance'] >= 3 ? '#f59e0b' : '#ef4444' ); ?>">
							<?php echo number_format( $stats['avg_performance'], 1 ); ?>/5
						</div>
						<div style="color: #6b7280;">Performance</div>
					</div>
				</div>
			</div>

			<!-- Feature Ratings -->
			<?php if ( ! empty( $feature_ratings[0] ) ) : ?>
				<div style="background: white; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
					<h2 style="margin-top: 0;">Feature Ratings</h2>
					<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
						<?php
						$features = array(
							'meetings' => 'Meetings',
							'documents' => 'Documents',
							'treasury' => 'Treasury',
							'donors' => 'Donors',
							'volunteers' => 'Volunteers',
							'compliance' => 'Compliance',
							'calendar' => 'Calendar',
							'email' => 'Email',
							'payments' => 'Payments',
							'membership' => 'Membership',
							'board' => 'Board Management',
							'communications' => 'Communications',
							'events' => 'Events',
							'grants' => 'Grants',
							'inventory' => 'Inventory',
							'programs' => 'Programs',
						);

						foreach ( $features as $key => $label ) :
							$rating = floatval( $feature_ratings[0][ $key ] ?? 0 );
							if ( $rating > 0 ) :
								$percentage = ( $rating / 5 ) * 100;
								$color = $rating >= 4 ? '#10b981' : ( $rating >= 3 ? '#f59e0b' : '#ef4444' );
								?>
								<div>
									<div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
										<span style="font-weight: 500;"><?php echo esc_html( $label ); ?></span>
										<span style="font-weight: bold; color: <?php echo $color; ?>"><?php echo number_format( $rating, 1 ); ?></span>
									</div>
									<div style="background: #e5e7eb; height: 8px; border-radius: 4px; overflow: hidden;">
										<div style="background: <?php echo $color; ?>; height: 100%; width: <?php echo $percentage; ?>%; transition: width 0.3s;"></div>
									</div>
								</div>
							<?php endif;
						endforeach;
						?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Filters -->
			<form method="get" style="background: white; padding: 15px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
				<input type="hidden" name="page" value="beta-surveys">

				<div style="display: flex; gap: 10px; align-items: flex-end;">
					<div style="flex: 1;">
						<label><strong>Organization:</strong></label><br>
						<select name="application_id" style="width: 100%;">
							<option value="">All Organizations</option>
							<?php foreach ( $applications as $app ) : ?>
								<option value="<?php echo $app['id']; ?>" <?php selected( $application_filter, $app['id'] ); ?>>
									<?php echo esc_html( $app['organization_name'] ); ?> (<?php echo esc_html( $app['contact_name'] ); ?>)
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div>
						<label><strong>Survey Number:</strong></label><br>
						<select name="survey_number">
							<option value="">All Surveys</option>
							<?php for ( $i = 1; $i <= 10; $i++ ) : ?>
								<option value="<?php echo $i; ?>" <?php selected( $survey_number_filter, $i ); ?>>Survey #<?php echo $i; ?></option>
							<?php endfor; ?>
						</select>
					</div>

					<div>
						<button type="submit" class="button">Filter</button>
						<a href="<?php echo admin_url( 'admin.php?page=beta-surveys' ); ?>" class="button">Reset</a>
					</div>
				</div>
			</form>

			<!-- Survey Responses Table -->
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>Organization</th>
						<th>Survey #</th>
						<th>Type</th>
						<th>Satisfaction</th>
						<th>Ease</th>
						<th>Features</th>
						<th>Performance</th>
						<th>Date</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $surveys ) ) : ?>
						<tr>
							<td colspan="9" style="text-align: center; padding: 40px;">
								No survey responses found.
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $surveys as $survey ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $survey['organization_name'] ); ?></strong><br>
									<small><?php echo esc_html( $survey['contact_name'] ); ?></small>
								</td>
								<td>
									<strong><?php echo $survey['survey_number']; ?></strong>
									<?php
									$type_label = $survey['slot_type'] === '501c3' ? '501(c)(3)' : 'Pre-NP';
									$type_color = $survey['slot_type'] === '501c3' ? '#2271b1' : '#f59e0b';
									?>
								</td>
								<td>
									<span style="background: <?php echo $type_color; ?>; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">
										<?php echo $type_label; ?>
									</span>
								</td>
								<td><?php echo $this->render_rating_badge( $survey['overall_satisfaction'] ); ?></td>
								<td><?php echo $this->render_rating_badge( $survey['ease_of_use'] ); ?></td>
								<td><?php echo $this->render_rating_badge( $survey['feature_completeness'] ); ?></td>
								<td><?php echo $this->render_rating_badge( $survey['performance'] ); ?></td>
								<td>
									<?php echo date( 'M j, Y', strtotime( $survey['submitted_at'] ) ); ?><br>
									<small><?php echo human_time_diff( strtotime( $survey['submitted_at'] ) ); ?> ago</small>
								</td>
								<td>
									<a href="#" class="button button-small view-survey" data-survey-id="<?php echo $survey['id']; ?>">View Details</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render rating badge
	 */
	private function render_rating_badge( $rating ) {
		$color = $rating >= 4 ? '#10b981' : ( $rating >= 3 ? '#f59e0b' : '#ef4444' );
		$stars = str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating );
		return sprintf(
			'<div style="color: %s; font-size: 14px;" title="%d/5">%s</div>',
			$color,
			$rating,
			$stars
		);
	}

	/**
	 * Render feedback page
	 */
	public function render_feedback() {
		global $wpdb;

		// Handle admin response submission
		if ( isset( $_POST['feedback_id'] ) && isset( $_POST['admin_response'] ) ) {
			check_admin_referer( 'respond-to-feedback' );

			$feedback_id = intval( $_POST['feedback_id'] );
			$admin_response = sanitize_textarea_field( $_POST['admin_response'] );
			$new_status = sanitize_text_field( $_POST['new_status'] );

			$wpdb->update(
				$wpdb->prefix . 'ns_beta_feedback',
				array(
					'admin_response' => $admin_response,
					'status'         => $new_status,
					'responded_at'   => current_time( 'mysql' ),
					'responded_by'   => get_current_user_id(),
				),
				array( 'id' => $feedback_id ),
				array( '%s', '%s', '%s', '%d' ),
				array( '%d' )
			);

			echo '<div class="notice notice-success"><p>Response saved successfully.</p></div>';
		}

		// Get filter parameters
		$status_filter = isset( $_GET['status_filter'] ) ? sanitize_text_field( $_GET['status_filter'] ) : 'open';
		$type_filter = isset( $_GET['type_filter'] ) ? sanitize_text_field( $_GET['type_filter'] ) : '';
		$category_filter = isset( $_GET['category_filter'] ) ? sanitize_text_field( $_GET['category_filter'] ) : '';

		// Build query
		$where = array( '1=1' );

		if ( $status_filter ) {
			$where[] = $wpdb->prepare( 'f.status = %s', $status_filter );
		}

		if ( $type_filter ) {
			$where[] = $wpdb->prepare( 'f.feedback_type = %s', $type_filter );
		}

		if ( $category_filter ) {
			$where[] = $wpdb->prepare( 'f.category = %s', $category_filter );
		}

		$where_sql = implode( ' AND ', $where );

		// Get feedback
		$feedback_items = $wpdb->get_results(
			"SELECT f.*, a.organization_name, a.contact_name, a.contact_email
			FROM {$wpdb->prefix}ns_beta_feedback f
			LEFT JOIN {$wpdb->prefix}ns_beta_applications a ON f.application_id = a.id
			WHERE {$where_sql}
			ORDER BY
				CASE f.status
					WHEN 'open' THEN 1
					WHEN 'in_progress' THEN 2
					WHEN 'resolved' THEN 3
					WHEN 'closed' THEN 4
				END,
				f.submitted_at DESC
			LIMIT 100",
			ARRAY_A
		);

		// Get stats
		$stats = $wpdb->get_row(
			"SELECT
				COUNT(*) as total,
				SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
				SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
				SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
				SUM(CASE WHEN feedback_type = 'bug' THEN 1 ELSE 0 END) as bugs,
				SUM(CASE WHEN feedback_type = 'feature_request' THEN 1 ELSE 0 END) as features,
				SUM(CASE WHEN feedback_type = 'improvement' THEN 1 ELSE 0 END) as improvements
			FROM {$wpdb->prefix}ns_beta_feedback",
			ARRAY_A
		);

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">Beta Feedback</h1>
			<hr class="wp-header-end">

			<!-- Stats Bar -->
			<div style="background: white; padding: 15px; margin: 20px 0; display: flex; gap: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
				<div><strong>Total:</strong> <?php echo $stats['total']; ?></div>
				<div><strong>Open:</strong> <span style="color: #f59e0b;"><?php echo $stats['open']; ?></span></div>
				<div><strong>In Progress:</strong> <span style="color: #2271b1;"><?php echo $stats['in_progress']; ?></span></div>
				<div><strong>Resolved:</strong> <span style="color: #10b981;"><?php echo $stats['resolved']; ?></span></div>
				<div style="margin-left: 30px;"><strong>Bugs:</strong> <?php echo $stats['bugs']; ?></div>
				<div><strong>Features:</strong> <?php echo $stats['features']; ?></div>
				<div><strong>Improvements:</strong> <?php echo $stats['improvements']; ?></div>
			</div>

			<!-- Filters -->
			<form method="get" style="background: white; padding: 15px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
				<input type="hidden" name="page" value="beta-feedback">

				<div style="display: flex; gap: 10px; align-items: flex-end;">
					<div>
						<label><strong>Status:</strong></label><br>
						<select name="status_filter">
							<option value="">All Statuses</option>
							<option value="open" <?php selected( $status_filter, 'open' ); ?>>Open</option>
							<option value="in_progress" <?php selected( $status_filter, 'in_progress' ); ?>>In Progress</option>
							<option value="resolved" <?php selected( $status_filter, 'resolved' ); ?>>Resolved</option>
							<option value="closed" <?php selected( $status_filter, 'closed' ); ?>>Closed</option>
						</select>
					</div>

					<div>
						<label><strong>Type:</strong></label><br>
						<select name="type_filter">
							<option value="">All Types</option>
							<option value="bug" <?php selected( $type_filter, 'bug' ); ?>>Bug Report</option>
							<option value="feature_request" <?php selected( $type_filter, 'feature_request' ); ?>>Feature Request</option>
							<option value="improvement" <?php selected( $type_filter, 'improvement' ); ?>>Improvement</option>
							<option value="question" <?php selected( $type_filter, 'question' ); ?>>Question</option>
							<option value="other" <?php selected( $type_filter, 'other' ); ?>>Other</option>
						</select>
					</div>

					<div>
						<label><strong>Category:</strong></label><br>
						<select name="category_filter">
							<option value="">All Categories</option>
							<option value="ui_ux" <?php selected( $category_filter, 'ui_ux' ); ?>>UI/UX</option>
							<option value="performance" <?php selected( $category_filter, 'performance' ); ?>>Performance</option>
							<option value="functionality" <?php selected( $category_filter, 'functionality' ); ?>>Functionality</option>
							<option value="integrations" <?php selected( $category_filter, 'integrations' ); ?>>Integrations</option>
							<option value="documentation" <?php selected( $category_filter, 'documentation' ); ?>>Documentation</option>
							<option value="other" <?php selected( $category_filter, 'other' ); ?>>Other</option>
						</select>
					</div>

					<div>
						<button type="submit" class="button">Filter</button>
						<a href="<?php echo admin_url( 'admin.php?page=beta-feedback' ); ?>" class="button">Reset</a>
					</div>
				</div>
			</form>

			<!-- Feedback Items -->
			<div style="background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
				<?php if ( empty( $feedback_items ) ) : ?>
					<div style="text-align: center; padding: 40px;">
						No feedback found.
					</div>
				<?php else : ?>
					<?php foreach ( $feedback_items as $item ) : ?>
						<div style="border-bottom: 1px solid #e5e7eb; padding: 20px;">
							<div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
								<div style="flex: 1;">
									<h3 style="margin: 0 0 5px 0; font-size: 16px;">
										<?php echo esc_html( $item['subject'] ); ?>
									</h3>
									<div style="display: flex; gap: 10px; align-items: center; font-size: 13px; color: #6b7280;">
										<span>
											<strong><?php echo esc_html( $item['organization_name'] ); ?></strong>
											(<?php echo esc_html( $item['contact_name'] ); ?>)
										</span>
										<span>•</span>
										<span><?php echo human_time_diff( strtotime( $item['submitted_at'] ) ); ?> ago</span>
									</div>
								</div>
								<div style="display: flex; gap: 8px; align-items: center;">
									<?php
									$type_labels = array(
										'bug'             => array( 'Bug', '#ef4444' ),
										'feature_request' => array( 'Feature', '#2271b1' ),
										'improvement'     => array( 'Improvement', '#10b981' ),
										'question'        => array( 'Question', '#f59e0b' ),
										'other'           => array( 'Other', '#6b7280' ),
									);
									$type_info = $type_labels[ $item['feedback_type'] ] ?? array( 'Unknown', '#9ca3af' );
									?>
									<span style="background: <?php echo $type_info[1]; ?>; color: white; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: bold;">
										<?php echo $type_info[0]; ?>
									</span>

									<?php
									$status_labels = array(
										'open'        => array( 'Open', '#fff3cd', '#856404' ),
										'in_progress' => array( 'In Progress', '#dbeafe', '#1e40af' ),
										'resolved'    => array( 'Resolved', '#d1fae5', '#065f46' ),
										'closed'      => array( 'Closed', '#e5e7eb', '#374151' ),
									);
									$status_info = $status_labels[ $item['status'] ] ?? array( 'Unknown', '#e5e7eb', '#374151' );
									?>
									<span style="background: <?php echo $status_info[1]; ?>; color: <?php echo $status_info[2]; ?>; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: bold;">
										<?php echo $status_info[0]; ?>
									</span>

									<?php if ( $item['category'] ) : ?>
										<span style="background: #f3f4f6; color: #4b5563; padding: 4px 10px; border-radius: 4px; font-size: 11px;">
											<?php echo esc_html( ucwords( str_replace( '_', ' ', $item['category'] ) ) ); ?>
										</span>
									<?php endif; ?>
								</div>
							</div>

							<div style="background: #f9fafb; padding: 12px; border-radius: 4px; margin-bottom: 15px; white-space: pre-wrap;">
								<?php echo esc_html( $item['message'] ); ?>
							</div>

							<?php if ( $item['admin_response'] ) : ?>
								<div style="background: #eff6ff; border-left: 3px solid #2271b1; padding: 12px; margin-bottom: 15px;">
									<div style="font-weight: 600; margin-bottom: 5px; color: #1e40af;">Admin Response:</div>
									<div style="white-space: pre-wrap;"><?php echo esc_html( $item['admin_response'] ); ?></div>
									<?php if ( $item['responded_at'] ) : ?>
										<div style="font-size: 12px; color: #6b7280; margin-top: 5px;">
											Responded <?php echo human_time_diff( strtotime( $item['responded_at'] ) ); ?> ago
										</div>
									<?php endif; ?>
								</div>
							<?php endif; ?>

							<!-- Response Form -->
							<div id="response-form-<?php echo $item['id']; ?>" style="display: none; margin-top: 15px;">
								<form method="post">
									<?php wp_nonce_field( 'respond-to-feedback' ); ?>
									<input type="hidden" name="feedback_id" value="<?php echo $item['id']; ?>">

									<textarea name="admin_response" rows="4" style="width: 100%; margin-bottom: 10px;" placeholder="Enter your response..."><?php echo esc_textarea( $item['admin_response'] ); ?></textarea>

									<div style="display: flex; gap: 10px;">
										<select name="new_status" required>
											<option value="">Update Status...</option>
											<option value="in_progress" <?php selected( $item['status'], 'in_progress' ); ?>>In Progress</option>
											<option value="resolved" <?php selected( $item['status'], 'resolved' ); ?>>Resolved</option>
											<option value="closed" <?php selected( $item['status'], 'closed' ); ?>>Closed</option>
										</select>
										<button type="submit" class="button button-primary">Save Response</button>
										<button type="button" class="button cancel-response" data-feedback-id="<?php echo $item['id']; ?>">Cancel</button>
									</div>
								</form>
							</div>

							<div>
								<button class="button button-small show-response-form" data-feedback-id="<?php echo $item['id']; ?>">
									<?php echo $item['admin_response'] ? 'Update Response' : 'Respond'; ?>
								</button>
								<?php if ( $item['screenshot_url'] ) : ?>
									<a href="<?php echo esc_url( $item['screenshot_url'] ); ?>" target="_blank" class="button button-small">View Screenshot</a>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('.show-response-form').on('click', function() {
				var feedbackId = $(this).data('feedback-id');
				$('#response-form-' + feedbackId).slideDown();
				$(this).hide();
			});

			$('.cancel-response').on('click', function() {
				var feedbackId = $(this).data('feedback-id');
				$('#response-form-' + feedbackId).slideUp();
				$('.show-response-form[data-feedback-id="' + feedbackId + '"]').show();
			});
		});
		</script>
		<?php
	}

	/**
	 * Render settings page
	 */
	public function render_settings() {
		// Handle settings update
		if ( isset( $_POST['beta_settings_nonce'] ) && wp_verify_nonce( $_POST['beta_settings_nonce'], 'update_beta_settings' ) ) {
			$settings = array(
				'max_501c3_per_state'      => intval( $_POST['max_501c3_per_state'] ),
				'max_pre_nonprofit_per_state' => intval( $_POST['max_pre_nonprofit_per_state'] ),
				'encourage_forming_module' => isset( $_POST['encourage_forming_module'] ) ? 1 : 0,
				'auto_approve'             => isset( $_POST['auto_approve'] ) ? 1 : 0,
				'survey_schedule_days'     => array_map( 'intval', explode( ',', sanitize_text_field( $_POST['survey_schedule_days'] ) ) ),
				'trial_duration_days'      => intval( $_POST['trial_duration_days'] ),
			);

			update_option( 'ns_beta_program_settings', $settings );

			echo '<div class="notice notice-success"><p>Settings saved successfully.</p></div>';
		}

		// Get current settings
		$settings = get_option(
			'ns_beta_program_settings',
			array(
				'max_501c3_per_state'      => 2,
				'max_pre_nonprofit_per_state' => 1,
				'encourage_forming_module' => true,
				'auto_approve'             => false,
				'survey_schedule_days'     => array( 7, 14, 30, 60, 90, 120, 150, 180, 270, 365 ),
				'trial_duration_days'      => 30,
			)
		);

		global $wpdb;

		// Get current slot usage by state
		$slot_usage = $wpdb->get_results(
			"SELECT
				state,
				SUM(CASE WHEN slot_type = '501c3' THEN 1 ELSE 0 END) as c501c3_count,
				SUM(CASE WHEN slot_type = 'pre_nonprofit' THEN 1 ELSE 0 END) as pre_nonprofit_count
			FROM {$wpdb->prefix}ns_beta_applications
			WHERE status = 'approved'
			GROUP BY state
			ORDER BY state",
			ARRAY_A
		);

		?>
		<div class="wrap">
			<h1>Beta Program Settings</h1>

			<form method="post">
				<?php wp_nonce_field( 'update_beta_settings', 'beta_settings_nonce' ); ?>

				<!-- Slot Configuration -->
				<div style="background: white; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
					<h2 style="margin-top: 0;">Slot Configuration</h2>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="max_501c3_per_state">Max 501(c)(3) per State</label>
							</th>
							<td>
								<input type="number" id="max_501c3_per_state" name="max_501c3_per_state" value="<?php echo esc_attr( $settings['max_501c3_per_state'] ); ?>" min="1" max="100" class="small-text">
								<p class="description">Maximum number of approved 501(c)(3) organizations per state.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="max_pre_nonprofit_per_state">Max Pre-Nonprofits per State</label>
							</th>
							<td>
								<input type="number" id="max_pre_nonprofit_per_state" name="max_pre_nonprofit_per_state" value="<?php echo esc_attr( $settings['max_pre_nonprofit_per_state'] ); ?>" min="1" max="100" class="small-text">
								<p class="description">Maximum number of approved pre-nonprofit organizations per state.</p>
							</td>
						</tr>
					</table>

					<!-- Current Slot Usage -->
					<h3>Current Slot Usage by State</h3>
					<div style="max-height: 400px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 4px;">
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th>State</th>
									<th>501(c)(3) Slots</th>
									<th>Pre-Nonprofit Slots</th>
									<th>Status</th>
								</tr>
							</thead>
							<tbody>
								<?php if ( empty( $slot_usage ) ) : ?>
									<tr>
										<td colspan="4" style="text-align: center; padding: 20px;">
											No approved applications yet.
										</td>
									</tr>
								<?php else : ?>
									<?php foreach ( $slot_usage as $row ) : ?>
										<?php
										$c501c3_available = $settings['max_501c3_per_state'] - $row['c501c3_count'];
										$pre_nonprofit_available = $settings['max_pre_nonprofit_per_state'] - $row['pre_nonprofit_count'];
										$c501c3_full = $c501c3_available <= 0;
										$pre_nonprofit_full = $pre_nonprofit_available <= 0;
										?>
										<tr>
											<td><strong><?php echo esc_html( $row['state'] ); ?></strong></td>
											<td>
												<span style="<?php echo $c501c3_full ? 'color: #ef4444;' : ''; ?>">
													<?php echo $row['c501c3_count']; ?> / <?php echo $settings['max_501c3_per_state']; ?>
												</span>
												<?php if ( $c501c3_full ) : ?>
													<span style="background: #fee2e2; color: #991b1b; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 5px;">FULL</span>
												<?php endif; ?>
											</td>
											<td>
												<span style="<?php echo $pre_nonprofit_full ? 'color: #ef4444;' : ''; ?>">
													<?php echo $row['pre_nonprofit_count']; ?> / <?php echo $settings['max_pre_nonprofit_per_state']; ?>
												</span>
												<?php if ( $pre_nonprofit_full ) : ?>
													<span style="background: #fee2e2; color: #991b1b; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 5px;">FULL</span>
												<?php endif; ?>
											</td>
											<td>
												<?php if ( $c501c3_full && $pre_nonprofit_full ) : ?>
													<span style="color: #ef4444;">⚠ All slots full</span>
												<?php elseif ( $c501c3_available <= 1 || $pre_nonprofit_available <= 1 ) : ?>
													<span style="color: #f59e0b;">⚠ Limited slots</span>
												<?php else : ?>
													<span style="color: #10b981;">✓ Slots available</span>
												<?php endif; ?>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>

				<!-- Pre-Nonprofit Settings -->
				<div style="background: white; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
					<h2 style="margin-top: 0;">Pre-Nonprofit Settings</h2>

					<table class="form-table">
						<tr>
							<th scope="row">
								Forming a Nonprofit Module
							</th>
							<td>
								<label>
									<input type="checkbox" name="encourage_forming_module" value="1" <?php checked( $settings['encourage_forming_module'], 1 ); ?>>
									Encourage (but don't require) pre-nonprofits to complete the "Forming a Nonprofit" module
								</label>
								<p class="description">
									Pre-nonprofits may take 6-12 months to complete this module.
									This setting encourages completion but doesn't block license activation.
								</p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Survey Settings -->
				<div style="background: white; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
					<h2 style="margin-top: 0;">Survey Settings</h2>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="survey_schedule_days">Survey Schedule (Days)</label>
							</th>
							<td>
								<input type="text" id="survey_schedule_days" name="survey_schedule_days" value="<?php echo esc_attr( implode( ', ', $settings['survey_schedule_days'] ) ); ?>" class="regular-text">
								<p class="description">
									Comma-separated list of days after license activation when surveys should be sent.<br>
									Default: 7, 14, 30, 60, 90, 120, 150, 180, 270, 365 (10 surveys over 1 year)
								</p>
							</td>
						</tr>
					</table>

					<h3>Current Survey Schedule</h3>
					<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px;">
						<?php foreach ( $settings['survey_schedule_days'] as $index => $day ) : ?>
							<div style="background: #f3f4f6; padding: 10px; border-radius: 4px; text-align: center;">
								<div style="font-size: 24px; font-weight: bold; color: #2271b1;">
									<?php echo $index + 1; ?>
								</div>
								<div style="font-size: 12px; color: #6b7280;">
									Day <?php echo $day; ?>
									<?php if ( $day >= 365 ) : ?>
										(~<?php echo round( $day / 365, 1 ); ?> year)
									<?php elseif ( $day >= 30 ) : ?>
										(~<?php echo round( $day / 30 ); ?> months)
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- License Settings -->
				<div style="background: white; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
					<h2 style="margin-top: 0;">License Settings</h2>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="trial_duration_days">Trial Duration (Days)</label>
							</th>
							<td>
								<input type="number" id="trial_duration_days" name="trial_duration_days" value="<?php echo esc_attr( $settings['trial_duration_days'] ); ?>" min="1" max="3650" class="small-text">
								<p class="description">
									Duration of the beta trial license in days.
									For year-long beta testing, use 365+ days.
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								Auto-Approval
							</th>
							<td>
								<label>
									<input type="checkbox" name="auto_approve" value="1" <?php checked( $settings['auto_approve'], 1 ); ?>>
									Automatically approve applications when slots are available
								</label>
								<p class="description">
									When enabled, applications will be automatically approved if there are available slots for the state and organization type.
								</p>
							</td>
						</tr>
					</table>
				</div>

				<p class="submit">
					<button type="submit" class="button button-primary button-large">Save Settings</button>
				</p>
			</form>
		</div>
		<?php
	}
}

// Initialize
NonprofitSuite_Beta_Admin_Dashboard::get_instance();
