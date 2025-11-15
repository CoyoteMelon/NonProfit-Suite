<?php
/**
 * Volunteer Shifts UI
 *
 * User interface for volunteers to browse and sign up for available shifts.
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
 * NonprofitSuite_Volunteer_Shifts_UI Class
 *
 * Handles volunteer shift signup interface and AJAX operations.
 */
class NonprofitSuite_Volunteer_Shifts_UI {

	/**
	 * Initialize the UI components.
	 */
	public static function init() {
		// Register shortcode
		add_shortcode( 'ns_volunteer_shifts', array( __CLASS__, 'render_shifts_shortcode' ) );

		// Register AJAX handlers
		add_action( 'wp_ajax_ns_signup_for_shift', array( __CLASS__, 'ajax_signup_for_shift' ) );
		add_action( 'wp_ajax_ns_cancel_shift_signup', array( __CLASS__, 'ajax_cancel_shift_signup' ) );
		add_action( 'wp_ajax_ns_get_available_shifts', array( __CLASS__, 'ajax_get_available_shifts' ) );

		// Enqueue scripts and styles
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Enqueue frontend assets.
	 */
	public static function enqueue_frontend_assets() {
		global $post;

		// Only load on pages with the shortcode
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'ns_volunteer_shifts' ) ) {
			return;
		}

		wp_enqueue_style(
			'ns-volunteer-shifts',
			plugin_dir_url( dirname( __FILE__ ) ) . 'public/css/volunteer-shifts.css',
			array(),
			NONPROFITSUITE_VERSION
		);

		wp_enqueue_script(
			'ns-volunteer-shifts',
			plugin_dir_url( dirname( __FILE__ ) ) . 'public/js/volunteer-shifts.js',
			array( 'jquery' ),
			NONPROFITSUITE_VERSION,
			true
		);

		wp_localize_script(
			'ns-volunteer-shifts',
			'nsVolunteerShifts',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'ns_volunteer_shifts' ),
				'user_id'  => get_current_user_id(),
				'strings'  => array(
					'confirm_cancel'  => __( 'Are you sure you want to cancel this shift signup?', 'nonprofitsuite' ),
					'signup_success'  => __( 'Successfully signed up for shift!', 'nonprofitsuite' ),
					'cancel_success'  => __( 'Shift signup cancelled.', 'nonprofitsuite' ),
					'error'           => __( 'An error occurred. Please try again.', 'nonprofitsuite' ),
					'shift_full'      => __( 'This shift is now full.', 'nonprofitsuite' ),
					'conflict'        => __( 'You have a scheduling conflict with this shift.', 'nonprofitsuite' ),
					'already_signed'  => __( 'You are already signed up for this shift.', 'nonprofitsuite' ),
					'must_login'      => __( 'You must be logged in to sign up for shifts.', 'nonprofitsuite' ),
				),
			)
		);
	}

	/**
	 * Render volunteer shifts shortcode.
	 *
	 * Usage: [ns_volunteer_shifts days_ahead="30" show_my_shifts="yes"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_shifts_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'days_ahead'     => 30,
				'show_my_shifts' => 'yes',
				'event_id'       => null,
				'role'           => null,
			),
			$atts,
			'ns_volunteer_shifts'
		);

		ob_start();
		?>
		<div class="ns-volunteer-shifts-container">

			<?php if ( 'yes' === $atts['show_my_shifts'] && is_user_logged_in() ) : ?>
				<div class="ns-my-shifts-section">
					<h2><?php esc_html_e( 'My Upcoming Shifts', 'nonprofitsuite' ); ?></h2>
					<?php self::render_user_shifts( get_current_user_id() ); ?>
				</div>
			<?php endif; ?>

			<div class="ns-available-shifts-section">
				<h2><?php esc_html_e( 'Available Volunteer Shifts', 'nonprofitsuite' ); ?></h2>

				<div class="ns-shifts-filters">
					<label>
						<?php esc_html_e( 'Filter by Event:', 'nonprofitsuite' ); ?>
						<select id="ns-filter-event">
							<option value=""><?php esc_html_e( 'All Events', 'nonprofitsuite' ); ?></option>
							<?php self::render_event_options(); ?>
						</select>
					</label>

					<label>
						<?php esc_html_e( 'Filter by Role:', 'nonprofitsuite' ); ?>
						<select id="ns-filter-role">
							<option value=""><?php esc_html_e( 'All Roles', 'nonprofitsuite' ); ?></option>
							<?php self::render_role_options(); ?>
						</select>
					</label>

					<label>
						<?php esc_html_e( 'Show:', 'nonprofitsuite' ); ?>
						<select id="ns-filter-availability">
							<option value="available"><?php esc_html_e( 'Available Only', 'nonprofitsuite' ); ?></option>
							<option value="all"><?php esc_html_e( 'All Shifts', 'nonprofitsuite' ); ?></option>
						</select>
					</label>

					<button type="button" class="button" id="ns-apply-filters">
						<?php esc_html_e( 'Apply Filters', 'nonprofitsuite' ); ?>
					</button>
				</div>

				<div class="ns-shifts-list" id="ns-shifts-list">
					<?php
					self::render_available_shifts(
						array(
							'days_ahead' => $atts['days_ahead'],
							'event_id'   => $atts['event_id'],
							'role'       => $atts['role'],
						)
					);
					?>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render user's upcoming shifts.
	 *
	 * @param int $user_id User ID.
	 */
	private static function render_user_shifts( $user_id ) {
		$shifts = NonprofitSuite_Work_Schedules::get_user_shifts( $user_id );

		if ( empty( $shifts ) ) {
			echo '<p class="ns-no-shifts">' . esc_html__( 'You have no upcoming shifts.', 'nonprofitsuite' ) . '</p>';
			return;
		}

		echo '<div class="ns-shifts-grid ns-my-shifts-grid">';

		foreach ( $shifts as $shift ) {
			$event = null;
			if ( $shift['event_id'] ) {
				global $wpdb;
				$event = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}ns_calendar_events WHERE id = %d",
						$shift['event_id']
					),
					ARRAY_A
				);
			}

			$shift_date = $shift['start_date'];
			$shift_time = sprintf(
				'%s - %s',
				date( 'g:i A', strtotime( $shift['start_time'] ) ),
				date( 'g:i A', strtotime( $shift['end_time'] ) )
			);

			?>
			<div class="ns-shift-card ns-my-shift-card" data-shift-id="<?php echo esc_attr( $shift['id'] ); ?>">
				<div class="ns-shift-header">
					<div class="ns-shift-date">
						<span class="ns-shift-day"><?php echo esc_html( date( 'd', strtotime( $shift_date ) ) ); ?></span>
						<span class="ns-shift-month"><?php echo esc_html( date( 'M', strtotime( $shift_date ) ) ); ?></span>
					</div>
					<div class="ns-shift-title">
						<?php if ( $event ) : ?>
							<h3><?php echo esc_html( $event['title'] ); ?></h3>
						<?php endif; ?>
						<p class="ns-shift-name"><?php echo esc_html( $shift['shift_name'] ?: $shift['role'] ); ?></p>
					</div>
				</div>

				<div class="ns-shift-details">
					<div class="ns-shift-time">
						<span class="dashicons dashicons-clock"></span>
						<?php echo esc_html( $shift_time ); ?>
					</div>
					<?php if ( $shift['role'] ) : ?>
						<div class="ns-shift-role">
							<span class="dashicons dashicons-groups"></span>
							<?php echo esc_html( $shift['role'] ); ?>
						</div>
					<?php endif; ?>
					<?php if ( $shift['notes'] ) : ?>
						<div class="ns-shift-notes">
							<span class="dashicons dashicons-info"></span>
							<?php echo esc_html( $shift['notes'] ); ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="ns-shift-actions">
					<button type="button" class="button ns-cancel-shift-btn" data-shift-id="<?php echo esc_attr( $shift['id'] ); ?>">
						<?php esc_html_e( 'Cancel Signup', 'nonprofitsuite' ); ?>
					</button>
				</div>
			</div>
			<?php
		}

		echo '</div>';
	}

	/**
	 * Render available shifts.
	 *
	 * @param array $args Query arguments.
	 */
	private static function render_available_shifts( $args = array() ) {
		$defaults = array(
			'days_ahead'     => 30,
			'event_id'       => null,
			'role'           => null,
			'only_available' => true,
		);

		$args = wp_parse_args( $args, $defaults );

		global $wpdb;
		$table = $wpdb->prefix . 'ns_work_schedules';

		$start_date = gmdate( 'Y-m-d' );
		$end_date = gmdate( 'Y-m-d', strtotime( '+' . $args['days_ahead'] . ' days' ) );

		$where = array(
			"schedule_type = 'volunteer_shift'",
			"is_published = 1",
			$wpdb->prepare( 'start_date >= %s', $start_date ),
			$wpdb->prepare( 'start_date <= %s', $end_date ),
		);

		if ( $args['event_id'] ) {
			$where[] = $wpdb->prepare( 'event_id = %d', $args['event_id'] );
		}

		if ( $args['role'] ) {
			$where[] = $wpdb->prepare( 'role = %s', $args['role'] );
		}

		if ( $args['only_available'] ) {
			$where[] = 'positions_filled < positions_needed';
		}

		$where_clause = implode( ' AND ', $where );

		$shifts = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY start_date ASC, start_time ASC",
			ARRAY_A
		);

		if ( empty( $shifts ) ) {
			echo '<p class="ns-no-shifts">' . esc_html__( 'No volunteer shifts available at this time.', 'nonprofitsuite' ) . '</p>';
			return;
		}

		// Group shifts by event
		$grouped_shifts = array();
		foreach ( $shifts as $shift ) {
			$event_id = $shift['event_id'] ?: 0;
			if ( ! isset( $grouped_shifts[ $event_id ] ) ) {
				$grouped_shifts[ $event_id ] = array();
			}
			$grouped_shifts[ $event_id ][] = $shift;
		}

		foreach ( $grouped_shifts as $event_id => $event_shifts ) {
			$event = null;
			if ( $event_id ) {
				$event = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}ns_calendar_events WHERE id = %d",
						$event_id
					),
					ARRAY_A
				);
			}

			if ( $event ) {
				echo '<div class="ns-event-group">';
				echo '<h3 class="ns-event-title">' . esc_html( $event['title'] ) . '</h3>';
				if ( $event['description'] ) {
					echo '<p class="ns-event-description">' . esc_html( wp_trim_words( $event['description'], 30 ) ) . '</p>';
				}
			}

			echo '<div class="ns-shifts-grid">';

			foreach ( $event_shifts as $shift ) {
				self::render_shift_card( $shift );
			}

			echo '</div>';

			if ( $event ) {
				echo '</div>';
			}
		}
	}

	/**
	 * Render a single shift card.
	 *
	 * @param array $shift Shift data.
	 */
	private static function render_shift_card( $shift ) {
		$shift_date = $shift['start_date'];
		$shift_time = sprintf(
			'%s - %s',
			date( 'g:i A', strtotime( $shift['start_time'] ) ),
			date( 'g:i A', strtotime( $shift['end_time'] ) )
		);

		$positions_available = $shift['positions_needed'] - $shift['positions_filled'];
		$is_full = $positions_available <= 0;

		$user_id = get_current_user_id();
		$is_signed_up = false;

		if ( $user_id ) {
			global $wpdb;
			// Check if user has a personal assignment for this shift
			$assignment = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}ns_work_schedules
					WHERE user_id = %d
					AND event_id = %d
					AND start_date = %s
					AND start_time = %s
					AND schedule_type = 'volunteer_shift'
					AND is_published = 0",
					$user_id,
					$shift['event_id'],
					$shift['start_date'],
					$shift['start_time']
				)
			);
			$is_signed_up = ! empty( $assignment );
		}

		$card_classes = array( 'ns-shift-card' );
		if ( $is_full ) {
			$card_classes[] = 'ns-shift-full';
		}
		if ( $is_signed_up ) {
			$card_classes[] = 'ns-shift-signed-up';
		}

		?>
		<div class="<?php echo esc_attr( implode( ' ', $card_classes ) ); ?>" data-shift-id="<?php echo esc_attr( $shift['id'] ); ?>">
			<div class="ns-shift-header">
				<div class="ns-shift-date">
					<span class="ns-shift-day"><?php echo esc_html( date( 'd', strtotime( $shift_date ) ) ); ?></span>
					<span class="ns-shift-month"><?php echo esc_html( date( 'M', strtotime( $shift_date ) ) ); ?></span>
					<span class="ns-shift-year"><?php echo esc_html( date( 'Y', strtotime( $shift_date ) ) ); ?></span>
				</div>
				<div class="ns-shift-title">
					<h4><?php echo esc_html( $shift['shift_name'] ?: $shift['role'] ); ?></h4>
				</div>
			</div>

			<div class="ns-shift-details">
				<div class="ns-shift-time">
					<span class="dashicons dashicons-clock"></span>
					<?php echo esc_html( $shift_time ); ?>
				</div>

				<?php if ( $shift['role'] ) : ?>
					<div class="ns-shift-role">
						<span class="dashicons dashicons-groups"></span>
						<?php echo esc_html( $shift['role'] ); ?>
					</div>
				<?php endif; ?>

				<div class="ns-shift-positions <?php echo $is_full ? 'ns-positions-full' : ''; ?>">
					<span class="dashicons dashicons-admin-users"></span>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: positions filled, 2: positions needed */
							__( '%1$d of %2$d positions filled', 'nonprofitsuite' ),
							$shift['positions_filled'],
							$shift['positions_needed']
						)
					);
					?>
				</div>

				<?php if ( $shift['notes'] ) : ?>
					<div class="ns-shift-notes">
						<span class="dashicons dashicons-info"></span>
						<?php echo esc_html( $shift['notes'] ); ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="ns-shift-actions">
				<?php if ( ! is_user_logged_in() ) : ?>
					<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="button">
						<?php esc_html_e( 'Login to Sign Up', 'nonprofitsuite' ); ?>
					</a>
				<?php elseif ( $is_signed_up ) : ?>
					<span class="ns-status-badge ns-signed-up">
						<span class="dashicons dashicons-yes-alt"></span>
						<?php esc_html_e( 'You are signed up', 'nonprofitsuite' ); ?>
					</span>
				<?php elseif ( $is_full ) : ?>
					<span class="ns-status-badge ns-full">
						<?php esc_html_e( 'Full', 'nonprofitsuite' ); ?>
					</span>
				<?php else : ?>
					<button type="button" class="button button-primary ns-signup-btn" data-shift-id="<?php echo esc_attr( $shift['id'] ); ?>">
						<?php esc_html_e( 'Sign Up', 'nonprofitsuite' ); ?>
					</button>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render event filter options.
	 */
	private static function render_event_options() {
		global $wpdb;

		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT e.id, e.title
				FROM {$wpdb->prefix}ns_calendar_events e
				INNER JOIN {$wpdb->prefix}ns_work_schedules s ON e.id = s.event_id
				WHERE s.schedule_type = 'volunteer_shift'
				AND s.is_published = 1
				AND s.start_date >= %s
				ORDER BY e.title ASC",
				gmdate( 'Y-m-d' )
			),
			ARRAY_A
		);

		foreach ( $events as $event ) {
			echo '<option value="' . esc_attr( $event['id'] ) . '">' . esc_html( $event['title'] ) . '</option>';
		}
	}

	/**
	 * Render role filter options.
	 */
	private static function render_role_options() {
		global $wpdb;

		$roles = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT role
				FROM {$wpdb->prefix}ns_work_schedules
				WHERE schedule_type = 'volunteer_shift'
				AND is_published = 1
				AND role IS NOT NULL
				AND start_date >= %s
				ORDER BY role ASC",
				gmdate( 'Y-m-d' )
			)
		);

		foreach ( $roles as $role ) {
			echo '<option value="' . esc_attr( $role ) . '">' . esc_html( $role ) . '</option>';
		}
	}

	/**
	 * AJAX handler: Sign up for shift.
	 */
	public static function ajax_signup_for_shift() {
		check_ajax_referer( 'ns_volunteer_shifts', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'nonprofitsuite' ) ) );
		}

		$shift_id = isset( $_POST['shift_id'] ) ? intval( $_POST['shift_id'] ) : 0;
		$user_id = get_current_user_id();

		if ( ! $shift_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid shift.', 'nonprofitsuite' ) ) );
		}

		$result = NonprofitSuite_Work_Schedules::signup_for_shift( $shift_id, $user_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'        => __( 'Successfully signed up for shift!', 'nonprofitsuite' ),
				'assignment_id'  => $result,
			)
		);
	}

	/**
	 * AJAX handler: Cancel shift signup.
	 */
	public static function ajax_cancel_shift_signup() {
		check_ajax_referer( 'ns_volunteer_shifts', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'nonprofitsuite' ) ) );
		}

		$shift_id = isset( $_POST['shift_id'] ) ? intval( $_POST['shift_id'] ) : 0;
		$user_id = get_current_user_id();

		if ( ! $shift_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid shift.', 'nonprofitsuite' ) ) );
		}

		$result = NonprofitSuite_Work_Schedules::cancel_shift_signup( $shift_id, $user_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Shift signup cancelled.', 'nonprofitsuite' ),
			)
		);
	}

	/**
	 * AJAX handler: Get available shifts (for dynamic filtering).
	 */
	public static function ajax_get_available_shifts() {
		check_ajax_referer( 'ns_volunteer_shifts', 'nonce' );

		$event_id = isset( $_POST['event_id'] ) ? intval( $_POST['event_id'] ) : null;
		$role = isset( $_POST['role'] ) ? sanitize_text_field( $_POST['role'] ) : null;
		$only_available = isset( $_POST['only_available'] ) && 'all' !== $_POST['only_available'];

		ob_start();
		self::render_available_shifts(
			array(
				'event_id'       => $event_id,
				'role'           => $role,
				'only_available' => $only_available,
			)
		);
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}
}

// Initialize
NonprofitSuite_Volunteer_Shifts_UI::init();
