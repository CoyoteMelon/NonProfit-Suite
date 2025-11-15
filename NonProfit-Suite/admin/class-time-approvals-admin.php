<?php
/**
 * Time Entry Approvals Admin Page
 *
 * Admin interface for managers to review and approve time entries.
 *
 * @package    NonprofitSuite
 * @subpackage Admin
 * @since      1.5.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonprofitSuite_Time_Approvals_Admin Class
 *
 * Handles admin interface for time entry approvals.
 */
class NonprofitSuite_Time_Approvals_Admin {

	/**
	 * Initialize the admin page.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );

		// AJAX handlers
		add_action( 'wp_ajax_ns_admin_approve_entries', array( __CLASS__, 'ajax_approve_entries' ) );
		add_action( 'wp_ajax_ns_admin_reject_entries', array( __CLASS__, 'ajax_reject_entries' ) );
		add_action( 'wp_ajax_ns_admin_mark_paid', array( __CLASS__, 'ajax_mark_paid' ) );
	}

	/**
	 * Add admin menu page.
	 */
	public static function add_admin_menu() {
		add_submenu_page(
			'nonprofitsuite',
			__( 'Time Approvals', 'nonprofitsuite' ),
			__( 'Time Approvals', 'nonprofitsuite' ),
			'manage_options',
			'ns-time-approvals',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_admin_assets( $hook ) {
		if ( 'nonprofitsuite_page_ns-time-approvals' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'ns-time-approvals-admin',
			plugin_dir_url( dirname( __FILE__ ) ) . 'admin/css/time-approvals.css',
			array(),
			NONPROFITSUITE_VERSION
		);

		wp_enqueue_script(
			'ns-time-approvals-admin',
			plugin_dir_url( dirname( __FILE__ ) ) . 'admin/js/time-approvals.js',
			array( 'jquery' ),
			NONPROFITSUITE_VERSION,
			true
		);

		wp_localize_script(
			'ns-time-approvals-admin',
			'nsTimeApprovals',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'ns_time_approvals' ),
				'strings'  => array(
					'approve_success' => __( 'Time entries approved successfully!', 'nonprofitsuite' ),
					'reject_success'  => __( 'Time entries rejected.', 'nonprofitsuite' ),
					'paid_success'    => __( 'Entries marked as paid.', 'nonprofitsuite' ),
					'error'           => __( 'An error occurred. Please try again.', 'nonprofitsuite' ),
					'confirm_approve' => __( 'Approve selected time entries?', 'nonprofitsuite' ),
					'confirm_reject'  => __( 'Reject selected time entries?', 'nonprofitsuite' ),
					'confirm_paid'    => __( 'Mark selected entries as paid?', 'nonprofitsuite' ),
				),
			)
		);
	}

	/**
	 * Render admin page.
	 */
	public static function render_admin_page() {
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'pending';

		?>
		<div class="wrap ns-time-approvals-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Time Entry Approvals', 'nonprofitsuite' ); ?></h1>

			<nav class="nav-tab-wrapper wp-clearfix">
				<a href="?page=ns-time-approvals&tab=pending" class="nav-tab <?php echo 'pending' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Pending Approval', 'nonprofitsuite' ); ?>
					<?php
					$pending_count = self::get_pending_count();
					if ( $pending_count > 0 ) {
						echo '<span class="ns-count-badge">' . esc_html( $pending_count ) . '</span>';
					}
					?>
				</a>
				<a href="?page=ns-time-approvals&tab=approved" class="nav-tab <?php echo 'approved' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Approved', 'nonprofitsuite' ); ?>
				</a>
				<a href="?page=ns-time-approvals&tab=all" class="nav-tab <?php echo 'all' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'All Entries', 'nonprofitsuite' ); ?>
				</a>
				<a href="?page=ns-time-approvals&tab=export" class="nav-tab <?php echo 'export' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Export', 'nonprofitsuite' ); ?>
				</a>
			</nav>

			<div class="ns-tab-content">
				<?php
				switch ( $current_tab ) {
					case 'pending':
						self::render_pending_tab();
						break;
					case 'approved':
						self::render_approved_tab();
						break;
					case 'all':
						self::render_all_tab();
						break;
					case 'export':
						self::render_export_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render pending approvals tab.
	 */
	private static function render_pending_tab() {
		$entries = NonprofitSuite_Time_Tracking::get_pending_approvals();

		?>
		<div class="ns-pending-approvals">
			<?php if ( ! empty( $entries ) ) : ?>
				<div class="ns-bulk-actions">
					<button type="button" class="button button-primary" id="ns-bulk-approve">
						<span class="dashicons dashicons-yes"></span>
						<?php esc_html_e( 'Approve Selected', 'nonprofitsuite' ); ?>
					</button>
					<button type="button" class="button" id="ns-bulk-reject">
						<span class="dashicons dashicons-no"></span>
						<?php esc_html_e( 'Reject Selected', 'nonprofitsuite' ); ?>
					</button>
				</div>

				<?php self::render_entries_table( $entries, true ); ?>

			<?php else : ?>
				<div class="ns-empty-state">
					<span class="dashicons dashicons-yes-alt"></span>
					<h3><?php esc_html_e( 'No pending approvals', 'nonprofitsuite' ); ?></h3>
					<p><?php esc_html_e( 'All time entries have been reviewed!', 'nonprofitsuite' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render approved entries tab.
	 */
	private static function render_approved_tab() {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_time_entries';

		$entries = $wpdb->get_results(
			"SELECT * FROM {$table}
			WHERE status = 'approved'
			ORDER BY approved_at DESC
			LIMIT 100",
			ARRAY_A
		);

		?>
		<div class="ns-approved-entries">
			<?php if ( ! empty( $entries ) ) : ?>
				<div class="ns-bulk-actions">
					<button type="button" class="button button-primary" id="ns-bulk-mark-paid">
						<span class="dashicons dashicons-yes"></span>
						<?php esc_html_e( 'Mark Selected as Paid', 'nonprofitsuite' ); ?>
					</button>
				</div>

				<?php self::render_entries_table( $entries, true ); ?>

			<?php else : ?>
				<div class="ns-empty-state">
					<span class="dashicons dashicons-info"></span>
					<h3><?php esc_html_e( 'No approved entries', 'nonprofitsuite' ); ?></h3>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render all entries tab.
	 */
	private static function render_all_tab() {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_time_entries';

		// Filters
		$filter_user = isset( $_GET['filter_user'] ) ? intval( $_GET['filter_user'] ) : 0;
		$filter_status = isset( $_GET['filter_status'] ) ? sanitize_text_field( $_GET['filter_status'] ) : '';
		$filter_start = isset( $_GET['filter_start'] ) ? sanitize_text_field( $_GET['filter_start'] ) : '';
		$filter_end = isset( $_GET['filter_end'] ) ? sanitize_text_field( $_GET['filter_end'] ) : '';

		$where = array();
		if ( $filter_user ) {
			$where[] = $wpdb->prepare( 'user_id = %d', $filter_user );
		}
		if ( $filter_status ) {
			$where[] = $wpdb->prepare( 'status = %s', $filter_status );
		}
		if ( $filter_start ) {
			$where[] = $wpdb->prepare( 'start_datetime >= %s', $filter_start . ' 00:00:00' );
		}
		if ( $filter_end ) {
			$where[] = $wpdb->prepare( 'start_datetime <= %s', $filter_end . ' 23:59:59' );
		}

		$where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$entries = $wpdb->get_results(
			"SELECT * FROM {$table} {$where_clause} ORDER BY start_datetime DESC LIMIT 100",
			ARRAY_A
		);

		?>
		<div class="ns-all-entries">
			<div class="ns-filters-bar">
				<form method="get" class="ns-filters-form">
					<input type="hidden" name="page" value="ns-time-approvals">
					<input type="hidden" name="tab" value="all">

					<select name="filter_user">
						<option value=""><?php esc_html_e( 'All Users', 'nonprofitsuite' ); ?></option>
						<?php
						$users = get_users( array( 'fields' => array( 'ID', 'display_name' ) ) );
						foreach ( $users as $user ) {
							printf(
								'<option value="%d" %s>%s</option>',
								$user->ID,
								selected( $filter_user, $user->ID, false ),
								esc_html( $user->display_name )
							);
						}
						?>
					</select>

					<select name="filter_status">
						<option value=""><?php esc_html_e( 'All Statuses', 'nonprofitsuite' ); ?></option>
						<option value="draft" <?php selected( $filter_status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'nonprofitsuite' ); ?></option>
						<option value="submitted" <?php selected( $filter_status, 'submitted' ); ?>><?php esc_html_e( 'Submitted', 'nonprofitsuite' ); ?></option>
						<option value="approved" <?php selected( $filter_status, 'approved' ); ?>><?php esc_html_e( 'Approved', 'nonprofitsuite' ); ?></option>
						<option value="rejected" <?php selected( $filter_status, 'rejected' ); ?>><?php esc_html_e( 'Rejected', 'nonprofitsuite' ); ?></option>
						<option value="paid" <?php selected( $filter_status, 'paid' ); ?>><?php esc_html_e( 'Paid', 'nonprofitsuite' ); ?></option>
					</select>

					<input type="date" name="filter_start" value="<?php echo esc_attr( $filter_start ); ?>" placeholder="<?php esc_attr_e( 'Start Date', 'nonprofitsuite' ); ?>">
					<input type="date" name="filter_end" value="<?php echo esc_attr( $filter_end ); ?>" placeholder="<?php esc_attr_e( 'End Date', 'nonprofitsuite' ); ?>">

					<button type="submit" class="button"><?php esc_html_e( 'Filter', 'nonprofitsuite' ); ?></button>
					<a href="?page=ns-time-approvals&tab=all" class="button"><?php esc_html_e( 'Clear', 'nonprofitsuite' ); ?></a>
				</form>
			</div>

			<?php if ( ! empty( $entries ) ) : ?>
				<?php self::render_entries_table( $entries, false ); ?>
			<?php else : ?>
				<div class="ns-empty-state">
					<span class="dashicons dashicons-info"></span>
					<h3><?php esc_html_e( 'No entries found', 'nonprofitsuite' ); ?></h3>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render export tab.
	 */
	private static function render_export_tab() {
		?>
		<div class="ns-export-tab">
			<div class="ns-export-card">
				<h2><?php esc_html_e( 'Export Time Entries', 'nonprofitsuite' ); ?></h2>
				<p><?php esc_html_e( 'Export time entries to CSV for payroll processing or record keeping.', 'nonprofitsuite' ); ?></p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ns-export-form">
					<input type="hidden" name="action" value="ns_export_time_entries">
					<?php wp_nonce_field( 'ns_export_time_entries', 'export_nonce' ); ?>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="export_start_date"><?php esc_html_e( 'Start Date', 'nonprofitsuite' ); ?></label>
							</th>
							<td>
								<input type="date" id="export_start_date" name="start_date" required class="regular-text">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="export_end_date"><?php esc_html_e( 'End Date', 'nonprofitsuite' ); ?></label>
							</th>
							<td>
								<input type="date" id="export_end_date" name="end_date" required class="regular-text">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="export_status"><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></label>
							</th>
							<td>
								<select id="export_status" name="status">
									<option value=""><?php esc_html_e( 'All Statuses', 'nonprofitsuite' ); ?></option>
									<option value="approved"><?php esc_html_e( 'Approved Only', 'nonprofitsuite' ); ?></option>
									<option value="paid"><?php esc_html_e( 'Paid Only', 'nonprofitsuite' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="export_user"><?php esc_html_e( 'User', 'nonprofitsuite' ); ?></label>
							</th>
							<td>
								<select id="export_user" name="user_id">
									<option value=""><?php esc_html_e( 'All Users', 'nonprofitsuite' ); ?></option>
									<?php
									$users = get_users( array( 'fields' => array( 'ID', 'display_name' ) ) );
									foreach ( $users as $user ) {
										printf(
											'<option value="%d">%s</option>',
											$user->ID,
											esc_html( $user->display_name )
										);
									}
									?>
								</select>
							</td>
						</tr>
					</table>

					<p class="submit">
						<button type="submit" class="button button-primary">
							<span class="dashicons dashicons-download"></span>
							<?php esc_html_e( 'Download CSV', 'nonprofitsuite' ); ?>
						</button>
					</p>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render entries table.
	 *
	 * @param array $entries       Time entries.
	 * @param bool  $show_checkbox Show checkboxes for bulk actions.
	 */
	private static function render_entries_table( $entries, $show_checkbox = false ) {
		?>
		<table class="wp-list-table widefat fixed striped ns-time-entries-table">
			<thead>
				<tr>
					<?php if ( $show_checkbox ) : ?>
						<th class="check-column">
							<input type="checkbox" id="ns-select-all">
						</th>
					<?php endif; ?>
					<th><?php esc_html_e( 'User', 'nonprofitsuite' ); ?></th>
					<th><?php esc_html_e( 'Date', 'nonprofitsuite' ); ?></th>
					<th><?php esc_html_e( 'Time', 'nonprofitsuite' ); ?></th>
					<th><?php esc_html_e( 'Hours', 'nonprofitsuite' ); ?></th>
					<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
					<th><?php esc_html_e( 'Description', 'nonprofitsuite' ); ?></th>
					<th><?php esc_html_e( 'Amount', 'nonprofitsuite' ); ?></th>
					<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $entries as $entry ) : ?>
					<?php
					$user = get_userdata( $entry['user_id'] );
					$hours = $entry['duration_minutes'] ? number_format( $entry['duration_minutes'] / 60, 2 ) : '--';
					?>
					<tr>
						<?php if ( $show_checkbox ) : ?>
							<th class="check-column">
								<input type="checkbox" class="ns-entry-checkbox" value="<?php echo esc_attr( $entry['id'] ); ?>">
							</th>
						<?php endif; ?>
						<td><?php echo esc_html( $user ? $user->display_name : '--' ); ?></td>
						<td><?php echo esc_html( wp_date( 'M j, Y', strtotime( $entry['start_datetime'] ) ) ); ?></td>
						<td>
							<?php
							echo esc_html(
								sprintf(
									'%s - %s',
									wp_date( 'g:i A', strtotime( $entry['start_datetime'] ) ),
									$entry['end_datetime'] ? wp_date( 'g:i A', strtotime( $entry['end_datetime'] ) ) : '--'
								)
							);
							?>
						</td>
						<td><?php echo esc_html( $hours ); ?></td>
						<td><?php echo esc_html( ucfirst( $entry['entry_type'] ) ); ?></td>
						<td><?php echo esc_html( wp_trim_words( $entry['description'] ?: '--', 10 ) ); ?></td>
						<td>
							<?php
							if ( $entry['total_amount'] ) {
								echo '$' . esc_html( number_format( $entry['total_amount'], 2 ) );
							} else {
								echo '--';
							}
							?>
						</td>
						<td>
							<span class="ns-status-badge ns-status-<?php echo esc_attr( $entry['status'] ); ?>">
								<?php echo esc_html( ucfirst( $entry['status'] ) ); ?>
							</span>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Get count of pending approvals.
	 *
	 * @return int Count.
	 */
	private static function get_pending_count() {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_time_entries';

		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE status = 'submitted'"
		);
	}

	/**
	 * AJAX handler: Approve entries.
	 */
	public static function ajax_approve_entries() {
		check_ajax_referer( 'ns_time_approvals', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'nonprofitsuite' ) ) );
		}

		$entry_ids = isset( $_POST['entry_ids'] ) ? array_map( 'intval', $_POST['entry_ids'] ) : array();

		if ( empty( $entry_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No entries selected.', 'nonprofitsuite' ) ) );
		}

		$result = NonprofitSuite_Time_Tracking::approve_entries( $entry_ids, get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Time entries approved successfully!', 'nonprofitsuite' ),
				'count'   => count( $entry_ids ),
			)
		);
	}

	/**
	 * AJAX handler: Reject entries.
	 */
	public static function ajax_reject_entries() {
		check_ajax_referer( 'ns_time_approvals', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'nonprofitsuite' ) ) );
		}

		$entry_ids = isset( $_POST['entry_ids'] ) ? array_map( 'intval', $_POST['entry_ids'] ) : array();
		$reason = isset( $_POST['reason'] ) ? sanitize_textarea_field( $_POST['reason'] ) : '';

		if ( empty( $entry_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No entries selected.', 'nonprofitsuite' ) ) );
		}

		$result = NonprofitSuite_Time_Tracking::reject_entries( $entry_ids, get_current_user_id(), $reason );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Time entries rejected.', 'nonprofitsuite' ),
				'count'   => count( $entry_ids ),
			)
		);
	}

	/**
	 * AJAX handler: Mark as paid.
	 */
	public static function ajax_mark_paid() {
		check_ajax_referer( 'ns_time_approvals', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'nonprofitsuite' ) ) );
		}

		$entry_ids = isset( $_POST['entry_ids'] ) ? array_map( 'intval', $_POST['entry_ids'] ) : array();

		if ( empty( $entry_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No entries selected.', 'nonprofitsuite' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_time_entries';

		foreach ( $entry_ids as $entry_id ) {
			$wpdb->update(
				$table,
				array(
					'status'  => 'paid',
					'paid_at' => current_time( 'mysql' ),
				),
				array( 'id' => $entry_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Entries marked as paid.', 'nonprofitsuite' ),
				'count'   => count( $entry_ids ),
			)
		);
	}
}

// Initialize
NonprofitSuite_Time_Approvals_Admin::init();

// Handle CSV export
add_action( 'admin_post_ns_export_time_entries', 'ns_handle_time_entries_export' );

/**
 * Handle time entries CSV export.
 */
function ns_handle_time_entries_export() {
	check_admin_referer( 'ns_export_time_entries', 'export_nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'nonprofitsuite' ) );
	}

	$args = array(
		'start_date' => isset( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : '',
		'end_date'   => isset( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : '',
		'status'     => isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '',
		'user_id'    => isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : null,
	);

	NonprofitSuite_Time_Tracking::export_to_csv( $args );
	exit;
}
