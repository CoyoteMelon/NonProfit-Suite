<?php
/**
 * User Calendar Preferences
 *
 * Handles user-specific calendar provider preferences and settings.
 *
 * @package    NonprofitSuite
 * @subpackage Includes
 * @since      1.4.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonprofitSuite_User_Calendar_Prefs Class
 *
 * Manages user calendar preferences UI and data.
 */
class NonprofitSuite_User_Calendar_Prefs {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		// Add calendar preferences section to user profile
		add_action( 'show_user_profile', array( __CLASS__, 'render_profile_section' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_profile_section' ) );

		// Save calendar preferences
		add_action( 'personal_options_update', array( __CLASS__, 'save_profile_section' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_profile_section' ) );

		// Add submenu page for current user's calendar settings
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
	}

	/**
	 * Add calendar settings page to user menu.
	 */
	public static function add_settings_page() {
		add_submenu_page(
			'nonprofitsuite',
			__( 'My Calendar Settings', 'nonprofitsuite' ),
			__( 'My Calendar', 'nonprofitsuite' ),
			'read',
			'nonprofitsuite-my-calendar',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Render standalone calendar settings page.
	 */
	public static function render_settings_page() {
		$user_id = get_current_user_id();

		// Handle form submission
		if ( isset( $_POST['ns_user_calendar_prefs'] ) && check_admin_referer( 'ns_user_calendar_prefs_' . $user_id ) ) {
			self::save_preferences( $user_id );
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Calendar preferences saved successfully.', 'nonprofitsuite' ) . '</p></div>';
		}

		$prefs = self::get_preferences( $user_id );
		$integration_manager = NonprofitSuite_Integration_Manager::get_instance();
		$providers = $integration_manager->get_providers( 'calendar' );

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'My Calendar Settings', 'nonprofitsuite' ); ?></h1>
			<p class="description">
				<?php echo esc_html__( 'Configure how your organization\'s calendar events sync to your personal calendar.', 'nonprofitsuite' ); ?>
			</p>

			<form method="post" action="">
				<?php wp_nonce_field( 'ns_user_calendar_prefs_' . $user_id ); ?>
				<input type="hidden" name="ns_user_calendar_prefs" value="1">

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="preferred_provider"><?php echo esc_html__( 'Preferred Calendar Provider', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<select id="preferred_provider" name="preferred_provider" class="regular-text">
								<?php foreach ( $providers as $provider_id => $provider ) : ?>
									<option value="<?php echo esc_attr( $provider_id ); ?>" <?php selected( $prefs['preferred_provider'], $provider_id ); ?>>
										<?php echo esc_html( $provider['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php echo esc_html__( 'Choose which calendar service to sync your events to.', 'nonprofitsuite' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="auto_sync_enabled"><?php echo esc_html__( 'Automatic Sync', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" id="auto_sync_enabled" name="auto_sync_enabled" value="1" <?php checked( $prefs['auto_sync_enabled'], 1 ); ?>>
								<?php echo esc_html__( 'Automatically sync my events to my personal calendar', 'nonprofitsuite' ); ?>
							</label>
							<p class="description">
								<?php echo esc_html__( 'When enabled, events you\'re involved in will automatically appear in your calendar.', 'nonprofitsuite' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="calendar_color"><?php echo esc_html__( 'Calendar Color', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<input type="color" id="calendar_color" name="calendar_color" value="<?php echo esc_attr( $prefs['calendar_color'] ? $prefs['calendar_color'] : '#2271b1' ); ?>">
							<p class="description">
								<?php echo esc_html__( 'Choose a color for your NonprofitSuite events in your calendar.', 'nonprofitsuite' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="timezone"><?php echo esc_html__( 'Timezone', 'nonprofitsuite' ); ?></label>
						</th>
						<td>
							<select id="timezone" name="timezone" class="regular-text">
								<option value=""><?php echo esc_html__( 'Use WordPress Default', 'nonprofitsuite' ); ?></option>
								<?php
								$timezones = timezone_identifiers_list();
								$current_tz = $prefs['timezone'] ? $prefs['timezone'] : wp_timezone_string();
								foreach ( $timezones as $tz ) :
								?>
									<option value="<?php echo esc_attr( $tz ); ?>" <?php selected( $current_tz, $tz ); ?>>
										<?php echo esc_html( $tz ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php echo esc_html__( 'Select your local timezone for calendar events.', 'nonprofitsuite' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php
				$selected_provider = $prefs['preferred_provider'];
				if ( $selected_provider !== 'builtin' ) :
				?>
					<hr>
					<h2><?php echo esc_html__( 'Calendar Categories', 'nonprofitsuite' ); ?></h2>
					<p class="description">
						<?php echo esc_html__( 'Select which organization calendars to sync to your personal calendar.', 'nonprofitsuite' ); ?>
					</p>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php echo esc_html__( 'Sync Categories', 'nonprofitsuite' ); ?></th>
							<td>
								<?php
								$sync_categories = ! empty( $prefs['sync_categories'] )
									? json_decode( $prefs['sync_categories'], true )
									: array( 'personal', 'board', 'public' );

								$available_categories = array(
									'personal' => __( 'My Personal Events', 'nonprofitsuite' ),
									'public'   => __( 'Public Organization Events', 'nonprofitsuite' ),
									'board'    => __( 'Board Events', 'nonprofitsuite' ),
									'general'  => __( 'General Organization Events', 'nonprofitsuite' ),
								);

								// Add user's committees
								$user_calendars = NonprofitSuite_Calendar_Visibility::get_user_calendars( $user_id );
								foreach ( $user_calendars as $calendar_id ) {
									if ( strpos( $calendar_id, 'committee_' ) === 0 ) {
										$committee_id = str_replace( 'committee_', '', $calendar_id );
										$committee_name = self::get_committee_name( $committee_id );
										$available_categories[ $calendar_id ] = $committee_name;
									}
								}

								foreach ( $available_categories as $cat_id => $cat_name ) :
								?>
									<label style="display: block; margin-bottom: 8px;">
										<input type="checkbox" name="sync_categories[]" value="<?php echo esc_attr( $cat_id ); ?>"
											<?php checked( in_array( $cat_id, $sync_categories, true ) ); ?>>
										<?php echo esc_html( $cat_name ); ?>
									</label>
								<?php endforeach; ?>
								<p class="description">
									<?php echo esc_html__( 'Only events from selected categories will sync to your calendar.', 'nonprofitsuite' ); ?>
								</p>
							</td>
						</tr>
					</table>
				<?php endif; ?>

				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Calendar Preferences', 'nonprofitsuite' ); ?>">
				</p>
			</form>

			<?php if ( $selected_provider !== 'builtin' ) : ?>
				<hr>
				<h2><?php echo esc_html__( 'Provider Setup', 'nonprofitsuite' ); ?></h2>
				<p class="description">
					<?php
					printf(
						/* translators: %s: provider name */
						esc_html__( 'To sync events to %s, you need to connect your account.', 'nonprofitsuite' ),
						'<strong>' . esc_html( $providers[ $selected_provider ]['name'] ) . '</strong>'
					);
					?>
				</p>

				<?php if ( $selected_provider === 'google' || $selected_provider === 'outlook' ) : ?>
					<p>
						<?php echo esc_html__( 'Click the button below to authorize NonprofitSuite to access your calendar.', 'nonprofitsuite' ); ?>
					</p>
					<p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-calendar-sync' ) ); ?>" class="button">
							<?php echo esc_html__( 'Connect Calendar', 'nonprofitsuite' ); ?>
						</a>
					</p>
				<?php elseif ( $selected_provider === 'icloud' ) : ?>
					<p>
						<?php echo esc_html__( 'For iCloud Calendar, you need to generate an app-specific password.', 'nonprofitsuite' ); ?>
					</p>
					<p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-calendar-sync' ) ); ?>" class="button">
							<?php echo esc_html__( 'Configure iCloud Calendar', 'nonprofitsuite' ); ?>
						</a>
					</p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render calendar preferences section in user profile.
	 *
	 * @param WP_User $user User object.
	 */
	public static function render_profile_section( $user ) {
		$prefs = self::get_preferences( $user->ID );
		$integration_manager = NonprofitSuite_Integration_Manager::get_instance();
		$providers = $integration_manager->get_providers( 'calendar' );

		?>
		<h2><?php echo esc_html__( 'Calendar Preferences', 'nonprofitsuite' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="ns_preferred_provider"><?php echo esc_html__( 'Preferred Calendar Provider', 'nonprofitsuite' ); ?></label>
				</th>
				<td>
					<select id="ns_preferred_provider" name="ns_preferred_provider">
						<?php foreach ( $providers as $provider_id => $provider ) : ?>
							<option value="<?php echo esc_attr( $provider_id ); ?>" <?php selected( $prefs['preferred_provider'], $provider_id ); ?>>
								<?php echo esc_html( $provider['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php echo esc_html__( 'Choose which calendar service to sync your events to.', 'nonprofitsuite' ); ?>
						<br>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-my-calendar' ) ); ?>">
							<?php echo esc_html__( 'Manage calendar settings', 'nonprofitsuite' ); ?> →
						</a>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="ns_auto_sync"><?php echo esc_html__( 'Automatic Sync', 'nonprofitsuite' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" id="ns_auto_sync" name="ns_auto_sync" value="1" <?php checked( $prefs['auto_sync_enabled'], 1 ); ?>>
						<?php echo esc_html__( 'Automatically sync my events', 'nonprofitsuite' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save calendar preferences from user profile.
	 *
	 * @param int $user_id User ID.
	 */
	public static function save_profile_section( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$prefs = array();

		if ( isset( $_POST['ns_preferred_provider'] ) ) {
			$prefs['preferred_provider'] = sanitize_text_field( $_POST['ns_preferred_provider'] );
		}

		if ( isset( $_POST['ns_auto_sync'] ) ) {
			$prefs['auto_sync_enabled'] = 1;
		} else {
			$prefs['auto_sync_enabled'] = 0;
		}

		self::update_preferences( $user_id, $prefs );
	}

	/**
	 * Save preferences from standalone settings page.
	 *
	 * @param int $user_id User ID.
	 */
	private static function save_preferences( $user_id ) {
		$prefs = array();

		if ( isset( $_POST['preferred_provider'] ) ) {
			$prefs['preferred_provider'] = sanitize_text_field( $_POST['preferred_provider'] );
		}

		if ( isset( $_POST['auto_sync_enabled'] ) ) {
			$prefs['auto_sync_enabled'] = 1;
		} else {
			$prefs['auto_sync_enabled'] = 0;
		}

		if ( isset( $_POST['calendar_color'] ) ) {
			$prefs['calendar_color'] = sanitize_hex_color( $_POST['calendar_color'] );
		}

		if ( isset( $_POST['timezone'] ) ) {
			$prefs['timezone'] = sanitize_text_field( $_POST['timezone'] );
		}

		if ( isset( $_POST['sync_categories'] ) && is_array( $_POST['sync_categories'] ) ) {
			$categories = array_map( 'sanitize_text_field', $_POST['sync_categories'] );
			$prefs['sync_categories'] = wp_json_encode( $categories );
		}

		self::update_preferences( $user_id, $prefs );
	}

	/**
	 * Get user's calendar preferences.
	 *
	 * @param int $user_id User ID.
	 * @return array Preferences array.
	 */
	public static function get_preferences( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_user_calendar_prefs';

		$prefs = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE user_id = %d",
			$user_id
		), ARRAY_A );

		if ( ! $prefs ) {
			// Return defaults
			return array(
				'user_id'            => $user_id,
				'preferred_provider' => 'builtin',
				'auto_sync_enabled'  => 1,
				'sync_categories'    => wp_json_encode( array( 'personal', 'board', 'public' ) ),
				'calendar_color'     => '#2271b1',
				'timezone'           => wp_timezone_string(),
			);
		}

		return $prefs;
	}

	/**
	 * Update user's calendar preferences.
	 *
	 * @param int   $user_id User ID.
	 * @param array $prefs   Preferences to update.
	 * @return bool True on success.
	 */
	public static function update_preferences( $user_id, $prefs ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_user_calendar_prefs';

		// Check if preferences exist
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE user_id = %d",
			$user_id
		) );

		if ( $existing ) {
			// Update
			$result = $wpdb->update(
				$table,
				$prefs,
				array( 'user_id' => $user_id ),
				null,
				array( '%d' )
			);
		} else {
			// Insert
			$prefs['user_id'] = $user_id;
			$result = $wpdb->insert( $table, $prefs );
		}

		return false !== $result;
	}

	/**
	 * Get committee name by ID.
	 *
	 * @param int $committee_id Committee ID.
	 * @return string Committee name.
	 */
	private static function get_committee_name( $committee_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_committees';

		$name = $wpdb->get_var( $wpdb->prepare(
			"SELECT name FROM {$table} WHERE id = %d",
			$committee_id
		) );

		return $name ? $name : sprintf( __( 'Committee %d', 'nonprofitsuite' ), $committee_id );
	}
}

// Initialize hooks
NonprofitSuite_User_Calendar_Prefs::init();
