<?php
/**
 * Time Clock UI
 *
 * User interface for employees and volunteers to clock in/out and track time.
 *
 * @package    NonprofitSuite
 * @subpackage Includes
 * @since      1.5.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonprofitSuite_Time_Clock_UI Class
 *
 * Handles time clock interface and time entry management.
 */
class NonprofitSuite_Time_Clock_UI {

	/**
	 * Initialize the UI components.
	 */
	public static function init() {
		// Register shortcodes
		add_shortcode( 'ns_time_clock', array( __CLASS__, 'render_time_clock_shortcode' ) );
		add_shortcode( 'ns_my_timesheet', array( __CLASS__, 'render_timesheet_shortcode' ) );

		// Register AJAX handlers
		add_action( 'wp_ajax_ns_clock_in', array( __CLASS__, 'ajax_clock_in' ) );
		add_action( 'wp_ajax_ns_clock_out', array( __CLASS__, 'ajax_clock_out' ) );
		add_action( 'wp_ajax_ns_submit_time_entries', array( __CLASS__, 'ajax_submit_entries' ) );

		// Enqueue assets
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Enqueue frontend assets.
	 */
	public static function enqueue_frontend_assets() {
		global $post;

		// Only load on pages with the shortcodes
		if ( ! is_a( $post, 'WP_Post' ) ) {
			return;
		}

		if ( ! has_shortcode( $post->post_content, 'ns_time_clock' ) &&
		     ! has_shortcode( $post->post_content, 'ns_my_timesheet' ) ) {
			return;
		}

		wp_enqueue_style(
			'ns-time-clock',
			plugin_dir_url( dirname( __FILE__ ) ) . 'public/css/time-clock.css',
			array(),
			NONPROFITSUITE_VERSION
		);

		wp_enqueue_script(
			'ns-time-clock',
			plugin_dir_url( dirname( __FILE__ ) ) . 'public/js/time-clock.js',
			array( 'jquery' ),
			NONPROFITSUITE_VERSION,
			true
		);

		wp_localize_script(
			'ns-time-clock',
			'nsTimeClock',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'ns_time_clock' ),
				'user_id'  => get_current_user_id(),
				'strings'  => array(
					'clock_in_success'  => __( 'Clocked in successfully!', 'nonprofitsuite' ),
					'clock_out_success' => __( 'Clocked out successfully!', 'nonprofitsuite' ),
					'submit_success'    => __( 'Time entries submitted for approval!', 'nonprofitsuite' ),
					'error'             => __( 'An error occurred. Please try again.', 'nonprofitsuite' ),
					'must_login'        => __( 'You must be logged in to use the time clock.', 'nonprofitsuite' ),
					'confirm_submit'    => __( 'Submit selected time entries for approval?', 'nonprofitsuite' ),
				),
			)
		);
	}

	/**
	 * Render time clock shortcode.
	 *
	 * Usage: [ns_time_clock]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_time_clock_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p class="ns-login-required">' . esc_html__( 'Please log in to access the time clock.', 'nonprofitsuite' ) . '</p>';
		}

		$user_id = get_current_user_id();
		$active_entry = NonprofitSuite_Time_Tracking::get_active_entry( $user_id );

		ob_start();
		?>
		<div class="ns-time-clock-container">
			<div class="ns-time-clock-card">
				<h2 class="ns-time-clock-title">
					<span class="dashicons dashicons-clock"></span>
					<?php esc_html_e( 'Time Clock', 'nonprofitsuite' ); ?>
				</h2>

				<div id="ns-clock-display" class="ns-clock-display">
					<div class="ns-current-time"><?php echo esc_html( current_time( 'g:i:s A' ) ); ?></div>
					<div class="ns-current-date"><?php echo esc_html( current_time( 'l, F j, Y' ) ); ?></div>
				</div>

				<?php if ( $active_entry ) : ?>
					<div class="ns-clock-status ns-clocked-in">
						<div class="ns-status-indicator">
							<span class="ns-status-dot"></span>
							<strong><?php esc_html_e( 'Clocked In', 'nonprofitsuite' ); ?></strong>
						</div>

						<div class="ns-clock-in-time">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: clock in time */
									__( 'Since %s', 'nonprofitsuite' ),
									wp_date( 'g:i A', strtotime( $active_entry['start_datetime'] ) )
								)
							);
							?>
						</div>

						<div class="ns-elapsed-time" data-start="<?php echo esc_attr( strtotime( $active_entry['start_datetime'] ) ); ?>">
							<?php echo esc_html( self::format_elapsed_time( $active_entry['start_datetime'] ) ); ?>
						</div>

						<form id="ns-clock-out-form" class="ns-clock-form">
							<input type="hidden" name="entry_id" value="<?php echo esc_attr( $active_entry['id'] ); ?>">

							<div class="ns-form-field">
								<label for="ns-break-minutes">
									<?php esc_html_e( 'Break Time (minutes)', 'nonprofitsuite' ); ?>
								</label>
								<input type="number" id="ns-break-minutes" name="break_minutes" min="0" value="0" class="ns-input">
							</div>

							<div class="ns-form-field">
								<label for="ns-time-description">
									<?php esc_html_e( 'Description (optional)', 'nonprofitsuite' ); ?>
								</label>
								<textarea id="ns-time-description" name="description" rows="3" class="ns-input"></textarea>
							</div>

							<button type="submit" class="ns-btn ns-btn-primary ns-btn-large">
								<span class="dashicons dashicons-upload"></span>
								<?php esc_html_e( 'Clock Out', 'nonprofitsuite' ); ?>
							</button>
						</form>
					</div>

				<?php else : ?>
					<div class="ns-clock-status ns-clocked-out">
						<div class="ns-status-indicator">
							<span class="ns-status-dot"></span>
							<strong><?php esc_html_e( 'Clocked Out', 'nonprofitsuite' ); ?></strong>
						</div>

						<form id="ns-clock-in-form" class="ns-clock-form">
							<div class="ns-form-field">
								<label for="ns-entry-type">
									<?php esc_html_e( 'Entry Type', 'nonprofitsuite' ); ?>
								</label>
								<select id="ns-entry-type" name="entry_type" class="ns-input">
									<option value="work"><?php esc_html_e( 'Work', 'nonprofitsuite' ); ?></option>
									<option value="volunteer"><?php esc_html_e( 'Volunteer', 'nonprofitsuite' ); ?></option>
								</select>
							</div>

							<div class="ns-form-field">
								<label for="ns-clock-in-description">
									<?php esc_html_e( 'Description (optional)', 'nonprofitsuite' ); ?>
								</label>
								<textarea id="ns-clock-in-description" name="description" rows="3" class="ns-input"></textarea>
							</div>

							<button type="submit" class="ns-btn ns-btn-success ns-btn-large">
								<span class="dashicons dashicons-download"></span>
								<?php esc_html_e( 'Clock In', 'nonprofitsuite' ); ?>
							</button>
						</form>
					</div>
				<?php endif; ?>
			</div>

			<div class="ns-recent-entries">
				<h3><?php esc_html_e( 'Recent Time Entries', 'nonprofitsuite' ); ?></h3>
				<?php self::render_recent_entries( $user_id, 5 ); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render timesheet shortcode.
	 *
	 * Usage: [ns_my_timesheet period="current_week"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_timesheet_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p class="ns-login-required">' . esc_html__( 'Please log in to view your timesheet.', 'nonprofitsuite' ) . '</p>';
		}

		$atts = shortcode_atts(
			array(
				'period' => 'current_week', // current_week, last_week, current_month, custom
			),
			$atts,
			'ns_my_timesheet'
		);

		$user_id = get_current_user_id();
		$dates = self::get_period_dates( $atts['period'] );

		$time_card = NonprofitSuite_Time_Tracking::get_time_card( $user_id, $dates['start'], $dates['end'] );

		ob_start();
		?>
		<div class="ns-timesheet-container">
			<div class="ns-timesheet-header">
				<h2><?php esc_html_e( 'My Timesheet', 'nonprofitsuite' ); ?></h2>

				<div class="ns-period-selector">
					<label for="ns-period-select"><?php esc_html_e( 'Period:', 'nonprofitsuite' ); ?></label>
					<select id="ns-period-select" class="ns-input">
						<option value="current_week" <?php selected( $atts['period'], 'current_week' ); ?>>
							<?php esc_html_e( 'Current Week', 'nonprofitsuite' ); ?>
						</option>
						<option value="last_week" <?php selected( $atts['period'], 'last_week' ); ?>>
							<?php esc_html_e( 'Last Week', 'nonprofitsuite' ); ?>
						</option>
						<option value="current_month" <?php selected( $atts['period'], 'current_month' ); ?>>
							<?php esc_html_e( 'Current Month', 'nonprofitsuite' ); ?>
						</option>
					</select>
				</div>
			</div>

			<div class="ns-timesheet-summary">
				<div class="ns-summary-card">
					<div class="ns-summary-label"><?php esc_html_e( 'Total Hours', 'nonprofitsuite' ); ?></div>
					<div class="ns-summary-value"><?php echo esc_html( number_format( $time_card['total_hours'], 2 ) ); ?></div>
				</div>

				<?php if ( isset( $time_card['billable_hours'] ) && $time_card['billable_hours'] > 0 ) : ?>
					<div class="ns-summary-card">
						<div class="ns-summary-label"><?php esc_html_e( 'Billable Hours', 'nonprofitsuite' ); ?></div>
						<div class="ns-summary-value"><?php echo esc_html( number_format( $time_card['billable_hours'], 2 ) ); ?></div>
					</div>
				<?php endif; ?>

				<?php if ( isset( $time_card['total_amount'] ) && $time_card['total_amount'] > 0 ) : ?>
					<div class="ns-summary-card">
						<div class="ns-summary-label"><?php esc_html_e( 'Total Amount', 'nonprofitsuite' ); ?></div>
						<div class="ns-summary-value">$<?php echo esc_html( number_format( $time_card['total_amount'], 2 ) ); ?></div>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $time_card['entries'] ) ) : ?>
				<div class="ns-timesheet-actions">
					<button type="button" class="ns-btn ns-btn-primary" id="ns-submit-selected">
						<?php esc_html_e( 'Submit Selected for Approval', 'nonprofitsuite' ); ?>
					</button>
				</div>

				<div class="ns-timesheet-table-wrapper">
					<table class="ns-timesheet-table">
						<thead>
							<tr>
								<th class="ns-col-select">
									<input type="checkbox" id="ns-select-all-entries">
								</th>
								<th class="ns-col-date"><?php esc_html_e( 'Date', 'nonprofitsuite' ); ?></th>
								<th class="ns-col-time"><?php esc_html_e( 'Time', 'nonprofitsuite' ); ?></th>
								<th class="ns-col-hours"><?php esc_html_e( 'Hours', 'nonprofitsuite' ); ?></th>
								<th class="ns-col-description"><?php esc_html_e( 'Description', 'nonprofitsuite' ); ?></th>
								<th class="ns-col-status"><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $time_card['entries'] as $entry ) : ?>
								<tr class="ns-entry-row ns-status-<?php echo esc_attr( $entry['status'] ); ?>">
									<td class="ns-col-select">
										<?php if ( 'draft' === $entry['status'] ) : ?>
											<input type="checkbox" class="ns-entry-checkbox" value="<?php echo esc_attr( $entry['id'] ); ?>">
										<?php endif; ?>
									</td>
									<td class="ns-col-date">
										<?php echo esc_html( wp_date( 'M j, Y', strtotime( $entry['start_datetime'] ) ) ); ?>
									</td>
									<td class="ns-col-time">
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
									<td class="ns-col-hours">
										<?php
										if ( $entry['duration_minutes'] ) {
											echo esc_html( number_format( $entry['duration_minutes'] / 60, 2 ) );
										} else {
											echo '<em>' . esc_html__( 'In Progress', 'nonprofitsuite' ) . '</em>';
										}
										?>
									</td>
									<td class="ns-col-description">
										<?php echo esc_html( $entry['description'] ?: '—' ); ?>
									</td>
									<td class="ns-col-status">
										<span class="ns-status-badge ns-status-<?php echo esc_attr( $entry['status'] ); ?>">
											<?php echo esc_html( ucfirst( $entry['status'] ) ); ?>
										</span>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else : ?>
				<p class="ns-no-entries"><?php esc_html_e( 'No time entries for this period.', 'nonprofitsuite' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render recent time entries.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Number of entries to show.
	 */
	private static function render_recent_entries( $user_id, $limit = 5 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_time_entries';

		$entries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE user_id = %d
				ORDER BY start_datetime DESC
				LIMIT %d",
				$user_id,
				$limit
			),
			ARRAY_A
		);

		if ( empty( $entries ) ) {
			echo '<p class="ns-no-entries">' . esc_html__( 'No recent time entries.', 'nonprofitsuite' ) . '</p>';
			return;
		}

		echo '<ul class="ns-entries-list">';

		foreach ( $entries as $entry ) {
			$duration_hours = $entry['duration_minutes'] ? number_format( $entry['duration_minutes'] / 60, 2 ) : null;

			$status_class = 'ns-status-' . $entry['status'];

			echo '<li class="ns-entry-item ' . esc_attr( $status_class ) . '">';
			echo '<div class="ns-entry-date">' . esc_html( wp_date( 'M j', strtotime( $entry['start_datetime'] ) ) ) . '</div>';
			echo '<div class="ns-entry-details">';
			echo '<div class="ns-entry-time">';
			echo esc_html( wp_date( 'g:i A', strtotime( $entry['start_datetime'] ) ) );
			if ( $entry['end_datetime'] ) {
				echo ' - ' . esc_html( wp_date( 'g:i A', strtotime( $entry['end_datetime'] ) ) );
			}
			echo '</div>';
			if ( $duration_hours ) {
				echo '<div class="ns-entry-hours">' . esc_html( $duration_hours ) . ' ' . esc_html__( 'hours', 'nonprofitsuite' ) . '</div>';
			}
			echo '</div>';
			echo '<div class="ns-entry-status">';
			echo '<span class="ns-status-badge ' . esc_attr( $status_class ) . '">' . esc_html( ucfirst( $entry['status'] ) ) . '</span>';
			echo '</div>';
			echo '</li>';
		}

		echo '</ul>';
	}

	/**
	 * Get date range for a period.
	 *
	 * @param string $period Period identifier.
	 * @return array Start and end dates.
	 */
	private static function get_period_dates( $period ) {
		$dates = array();

		switch ( $period ) {
			case 'current_week':
				$dates['start'] = gmdate( 'Y-m-d', strtotime( 'monday this week' ) );
				$dates['end'] = gmdate( 'Y-m-d', strtotime( 'sunday this week' ) );
				break;

			case 'last_week':
				$dates['start'] = gmdate( 'Y-m-d', strtotime( 'monday last week' ) );
				$dates['end'] = gmdate( 'Y-m-d', strtotime( 'sunday last week' ) );
				break;

			case 'current_month':
				$dates['start'] = gmdate( 'Y-m-01' );
				$dates['end'] = gmdate( 'Y-m-t' );
				break;

			default:
				$dates['start'] = gmdate( 'Y-m-d', strtotime( 'monday this week' ) );
				$dates['end'] = gmdate( 'Y-m-d', strtotime( 'sunday this week' ) );
				break;
		}

		return $dates;
	}

	/**
	 * Format elapsed time.
	 *
	 * @param string $start_datetime Start datetime.
	 * @return string Formatted elapsed time.
	 */
	private static function format_elapsed_time( $start_datetime ) {
		$start = strtotime( $start_datetime );
		$elapsed = time() - $start;

		$hours = floor( $elapsed / 3600 );
		$minutes = floor( ( $elapsed % 3600 ) / 60 );
		$seconds = $elapsed % 60;

		return sprintf( '%02d:%02d:%02d', $hours, $minutes, $seconds );
	}

	/**
	 * AJAX handler: Clock in.
	 */
	public static function ajax_clock_in() {
		check_ajax_referer( 'ns_time_clock', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'nonprofitsuite' ) ) );
		}

		$user_id = get_current_user_id();
		$entry_type = isset( $_POST['entry_type'] ) ? sanitize_text_field( $_POST['entry_type'] ) : 'work';
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( $_POST['description'] ) : '';

		$result = NonprofitSuite_Time_Tracking::clock_in(
			$user_id,
			array(
				'entry_type'  => $entry_type,
				'description' => $description,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'  => __( 'Clocked in successfully!', 'nonprofitsuite' ),
				'entry_id' => $result,
			)
		);
	}

	/**
	 * AJAX handler: Clock out.
	 */
	public static function ajax_clock_out() {
		check_ajax_referer( 'ns_time_clock', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'nonprofitsuite' ) ) );
		}

		$user_id = get_current_user_id();
		$break_minutes = isset( $_POST['break_minutes'] ) ? intval( $_POST['break_minutes'] ) : 0;
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( $_POST['description'] ) : '';

		$result = NonprofitSuite_Time_Tracking::clock_out(
			$user_id,
			array(
				'break_minutes' => $break_minutes,
				'description'   => $description,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Clocked out successfully!', 'nonprofitsuite' ),
			)
		);
	}

	/**
	 * AJAX handler: Submit time entries for approval.
	 */
	public static function ajax_submit_entries() {
		check_ajax_referer( 'ns_time_clock', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'nonprofitsuite' ) ) );
		}

		$user_id = get_current_user_id();
		$entry_ids = isset( $_POST['entry_ids'] ) ? array_map( 'intval', $_POST['entry_ids'] ) : array();

		if ( empty( $entry_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No entries selected.', 'nonprofitsuite' ) ) );
		}

		$result = NonprofitSuite_Time_Tracking::submit_for_approval( $entry_ids, $user_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Time entries submitted for approval!', 'nonprofitsuite' ),
				'count'   => count( $entry_ids ),
			)
		);
	}
}

// Initialize
NonprofitSuite_Time_Clock_UI::init();
