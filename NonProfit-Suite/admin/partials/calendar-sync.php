<?php
/**
 * Calendar Sync Settings Admin Page
 *
 * @package    NonprofitSuite
 * @subpackage Admin
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get Integration Manager instance
$integration_manager = NonprofitSuite_Integration_Manager::get_instance();

// Get active calendar provider
$active_provider_id = $integration_manager->get_active_provider_id( 'calendar' );
$active_provider = $integration_manager->get_provider( 'calendar', $active_provider_id );

// Handle OAuth callbacks
if ( isset( $_GET['code'] ) && isset( $_GET['provider'] ) ) {
	$provider = sanitize_text_field( $_GET['provider'] );
	$code = sanitize_text_field( $_GET['code'] );

	if ( $provider === 'google' ) {
		$adapter = new NonprofitSuite_Calendar_Google_Adapter();
		$result = $adapter->handle_oauth_callback( $code );

		if ( is_wp_error( $result ) ) {
			add_settings_error(
				'nonprofitsuite_calendar',
				'google_oauth_error',
				sprintf( __( 'Google Calendar connection failed: %s', 'nonprofitsuite' ), $result->get_error_message() ),
				'error'
			);
		} else {
			$integration_manager->mark_provider_connected( 'calendar', 'google' );
			add_settings_error(
				'nonprofitsuite_calendar',
				'google_oauth_success',
				__( 'Google Calendar connected successfully!', 'nonprofitsuite' ),
				'success'
			);
		}
	} elseif ( $provider === 'outlook' ) {
		$adapter = new NonprofitSuite_Calendar_Outlook_Adapter();
		$result = $adapter->handle_oauth_callback( $code );

		if ( is_wp_error( $result ) ) {
			add_settings_error(
				'nonprofitsuite_calendar',
				'outlook_oauth_error',
				sprintf( __( 'Outlook Calendar connection failed: %s', 'nonprofitsuite' ), $result->get_error_message() ),
				'error'
			);
		} else {
			$integration_manager->mark_provider_connected( 'calendar', 'outlook' );
			add_settings_error(
				'nonprofitsuite_calendar',
				'outlook_oauth_success',
				__( 'Outlook Calendar connected successfully!', 'nonprofitsuite' ),
				'success'
			);
		}
	}
}

// Handle form submissions
if ( isset( $_POST['ns_calendar_sync_settings'] ) && check_admin_referer( 'ns_calendar_sync_settings' ) ) {

	// Save sync frequency
	if ( isset( $_POST['sync_frequency'] ) ) {
		update_option( 'ns_calendar_sync_frequency', sanitize_text_field( $_POST['sync_frequency'] ) );
	}

	// Save two-way sync setting
	$two_way_sync = isset( $_POST['two_way_sync'] ) ? 1 : 0;
	update_option( 'ns_calendar_two_way_sync', $two_way_sync );

	// Save auto-sync setting
	$auto_sync = isset( $_POST['auto_sync'] ) ? 1 : 0;
	update_option( 'ns_calendar_auto_sync', $auto_sync );

	// Save selected calendar ID
	if ( isset( $_POST['selected_calendar_id'] ) ) {
		update_option( 'ns_calendar_selected_calendar_id', sanitize_text_field( $_POST['selected_calendar_id'] ) );
	}

	// Handle iCloud CalDAV credentials
	if ( isset( $_POST['icloud_username'] ) && isset( $_POST['icloud_password'] ) ) {
		$icloud_adapter = new NonprofitSuite_Calendar_iCloud_Adapter();
		$credentials = array(
			'username' => sanitize_email( $_POST['icloud_username'] ),
			'password' => $_POST['icloud_password'], // Don't sanitize password
		);

		// Discover calendar home if credentials provided
		if ( ! empty( $credentials['username'] ) && ! empty( $credentials['password'] ) ) {
			$icloud_adapter->save_credentials( $credentials );

			// Test connection
			$test = $icloud_adapter->test_connection();
			if ( is_wp_error( $test ) ) {
				add_settings_error(
					'nonprofitsuite_calendar',
					'icloud_connection_error',
					sprintf( __( 'iCloud Calendar connection failed: %s', 'nonprofitsuite' ), $test->get_error_message() ),
					'error'
				);
			} else {
				$integration_manager->mark_provider_connected( 'calendar', 'icloud' );

				// Save calendar home and URL
				$calendar_home = $icloud_adapter->discover_calendar_home();
				if ( ! is_wp_error( $calendar_home ) ) {
					$credentials['calendar_home'] = $calendar_home;

					// Get first available calendar
					$calendars = $icloud_adapter->list_calendars();
					if ( ! is_wp_error( $calendars ) && ! empty( $calendars ) ) {
						$credentials['calendar_url'] = $calendars[0]['url'];
					}

					$icloud_adapter->save_credentials( $credentials );
				}

				add_settings_error(
					'nonprofitsuite_calendar',
					'icloud_connection_success',
					__( 'iCloud Calendar connected successfully!', 'nonprofitsuite' ),
					'success'
				);
			}
		}
	}

	add_settings_error(
		'nonprofitsuite_calendar',
		'settings_saved',
		__( 'Calendar sync settings saved successfully.', 'nonprofitsuite' ),
		'success'
	);
}

// Handle disconnect
if ( isset( $_POST['disconnect_provider'] ) && check_admin_referer( 'ns_disconnect_calendar' ) ) {
	$provider_id = sanitize_text_field( $_POST['disconnect_provider'] );
	$integration_manager->mark_provider_disconnected( 'calendar', $provider_id );

	// Clear provider-specific settings
	if ( $provider_id === 'google' ) {
		delete_option( 'nonprofitsuite_google_calendar_settings' );
	} elseif ( $provider_id === 'outlook' ) {
		delete_option( 'nonprofitsuite_outlook_calendar_settings' );
	} elseif ( $provider_id === 'icloud' ) {
		delete_option( 'nonprofitsuite_icloud_calendar_settings' );
	}

	add_settings_error(
		'nonprofitsuite_calendar',
		'disconnected',
		sprintf( __( '%s disconnected successfully.', 'nonprofitsuite' ), $active_provider['name'] ),
		'success'
	);
}

// Handle manual sync
if ( isset( $_POST['manual_sync'] ) && check_admin_referer( 'ns_manual_calendar_sync' ) ) {
	$adapter = $integration_manager->get_active_provider( 'calendar' );

	if ( ! is_wp_error( $adapter ) && method_exists( $adapter, 'sync_events' ) ) {
		$result = $adapter->sync_events();

		if ( is_wp_error( $result ) ) {
			add_settings_error(
				'nonprofitsuite_calendar',
				'sync_error',
				sprintf( __( 'Sync failed: %s', 'nonprofitsuite' ), $result->get_error_message() ),
				'error'
			);
		} else {
			add_settings_error(
				'nonprofitsuite_calendar',
				'sync_success',
				sprintf(
					__( 'Sync completed: %d events synced, %d skipped', 'nonprofitsuite' ),
					$result['synced_count'],
					$result['skipped_count']
				),
				'success'
			);
		}
	}
}

// Get current settings
$sync_frequency = get_option( 'ns_calendar_sync_frequency', 'hourly' );
$two_way_sync = get_option( 'ns_calendar_two_way_sync', 0 );
$auto_sync = get_option( 'ns_calendar_auto_sync', 1 );
$selected_calendar_id = get_option( 'ns_calendar_selected_calendar_id', 'primary' );

// Get connection status
$is_connected = $integration_manager->is_provider_connected( 'calendar', $active_provider_id );

?>

<div class="wrap">
	<h1><?php echo esc_html__( 'Calendar Sync Settings', 'nonprofitsuite' ); ?></h1>

	<?php settings_errors( 'nonprofitsuite_calendar' ); ?>

	<div class="ns-calendar-sync-container">

		<!-- Provider Selection -->
		<div class="ns-card">
			<h2><?php echo esc_html__( 'Calendar Provider', 'nonprofitsuite' ); ?></h2>
			<p class="description">
				<?php echo esc_html__( 'Select your calendar provider from the Integrations page.', 'nonprofitsuite' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-integrations' ) ); ?>">
					<?php echo esc_html__( 'Manage Integrations', 'nonprofitsuite' ); ?>
				</a>
			</p>

			<div class="ns-current-provider">
				<strong><?php echo esc_html__( 'Active Provider:', 'nonprofitsuite' ); ?></strong>
				<?php echo esc_html( $active_provider['name'] ); ?>

				<?php if ( $is_connected ) : ?>
					<span class="ns-status-badge connected">
						<span class="dashicons dashicons-yes-alt"></span>
						<?php echo esc_html__( 'Connected', 'nonprofitsuite' ); ?>
					</span>
				<?php else : ?>
					<span class="ns-status-badge disconnected">
						<span class="dashicons dashicons-warning"></span>
						<?php echo esc_html__( 'Not Connected', 'nonprofitsuite' ); ?>
					</span>
				<?php endif; ?>
			</div>
		</div>

		<!-- Connection Setup -->
		<?php if ( ! $is_connected && $active_provider_id !== 'builtin' ) : ?>
			<div class="ns-card">
				<h2><?php echo esc_html__( 'Connect Calendar', 'nonprofitsuite' ); ?></h2>

				<?php if ( $active_provider_id === 'google' ) : ?>
					<p class="description">
						<?php echo esc_html__( 'Connect your Google Calendar account to enable sync.', 'nonprofitsuite' ); ?>
					</p>
					<?php
					$google_adapter = new NonprofitSuite_Calendar_Google_Adapter();
					$auth_url = $google_adapter->get_auth_url();
					if ( ! is_wp_error( $auth_url ) ) :
					?>
						<p>
							<a href="<?php echo esc_url( $auth_url ); ?>" class="button button-primary">
								<span class="dashicons dashicons-google"></span>
								<?php echo esc_html__( 'Connect Google Calendar', 'nonprofitsuite' ); ?>
							</a>
						</p>
					<?php else : ?>
						<div class="notice notice-error inline">
							<p><?php echo esc_html( $auth_url->get_error_message() ); ?></p>
						</div>
					<?php endif; ?>

				<?php elseif ( $active_provider_id === 'outlook' ) : ?>
					<p class="description">
						<?php echo esc_html__( 'Connect your Microsoft Outlook Calendar account to enable sync.', 'nonprofitsuite' ); ?>
					</p>
					<?php
					$outlook_adapter = new NonprofitSuite_Calendar_Outlook_Adapter();
					$auth_url = $outlook_adapter->get_auth_url();
					if ( ! is_wp_error( $auth_url ) ) :
					?>
						<p>
							<a href="<?php echo esc_url( $auth_url ); ?>" class="button button-primary">
								<span class="dashicons dashicons-calendar-alt"></span>
								<?php echo esc_html__( 'Connect Outlook Calendar', 'nonprofitsuite' ); ?>
							</a>
						</p>
					<?php else : ?>
						<div class="notice notice-error inline">
							<p><?php echo esc_html( $auth_url->get_error_message() ); ?></p>
						</div>
					<?php endif; ?>

				<?php elseif ( $active_provider_id === 'icloud' ) : ?>
					<form method="post" action="">
						<?php wp_nonce_field( 'ns_calendar_sync_settings' ); ?>
						<input type="hidden" name="ns_calendar_sync_settings" value="1">

						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="icloud_username"><?php echo esc_html__( 'Apple ID', 'nonprofitsuite' ); ?></label>
								</th>
								<td>
									<input type="email" id="icloud_username" name="icloud_username" class="regular-text" required>
									<p class="description">
										<?php echo esc_html__( 'Your Apple ID email address', 'nonprofitsuite' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="icloud_password"><?php echo esc_html__( 'App-Specific Password', 'nonprofitsuite' ); ?></label>
								</th>
								<td>
									<input type="password" id="icloud_password" name="icloud_password" class="regular-text" required>
									<p class="description">
										<?php
										echo wp_kses_post(
											sprintf(
												__( 'Generate an app-specific password at <a href="%s" target="_blank">appleid.apple.com</a>', 'nonprofitsuite' ),
												'https://appleid.apple.com/account/manage'
											)
										);
										?>
									</p>
								</td>
							</tr>
						</table>

						<p class="submit">
							<button type="submit" class="button button-primary">
								<?php echo esc_html__( 'Connect iCloud Calendar', 'nonprofitsuite' ); ?>
							</button>
						</p>
					</form>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<!-- Sync Settings -->
		<?php if ( $is_connected || $active_provider_id === 'builtin' ) : ?>
			<form method="post" action="">
				<?php wp_nonce_field( 'ns_calendar_sync_settings' ); ?>
				<input type="hidden" name="ns_calendar_sync_settings" value="1">

				<div class="ns-card">
					<h2><?php echo esc_html__( 'Sync Settings', 'nonprofitsuite' ); ?></h2>

					<table class="form-table">
						<?php if ( $active_provider_id !== 'builtin' ) : ?>
						<tr>
							<th scope="row">
								<label for="auto_sync"><?php echo esc_html__( 'Automatic Sync', 'nonprofitsuite' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox" id="auto_sync" name="auto_sync" value="1" <?php checked( $auto_sync, 1 ); ?>>
									<?php echo esc_html__( 'Enable automatic sync', 'nonprofitsuite' ); ?>
								</label>
								<p class="description">
									<?php echo esc_html__( 'Automatically sync events in the background', 'nonprofitsuite' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="sync_frequency"><?php echo esc_html__( 'Sync Frequency', 'nonprofitsuite' ); ?></label>
							</th>
							<td>
								<select id="sync_frequency" name="sync_frequency">
									<option value="hourly" <?php selected( $sync_frequency, 'hourly' ); ?>><?php echo esc_html__( 'Every Hour', 'nonprofitsuite' ); ?></option>
									<option value="twicedaily" <?php selected( $sync_frequency, 'twicedaily' ); ?>><?php echo esc_html__( 'Twice Daily', 'nonprofitsuite' ); ?></option>
									<option value="daily" <?php selected( $sync_frequency, 'daily' ); ?>><?php echo esc_html__( 'Daily', 'nonprofitsuite' ); ?></option>
								</select>
								<p class="description">
									<?php echo esc_html__( 'How often to sync events automatically', 'nonprofitsuite' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="two_way_sync"><?php echo esc_html__( 'Two-Way Sync', 'nonprofitsuite' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox" id="two_way_sync" name="two_way_sync" value="1" <?php checked( $two_way_sync, 1 ); ?>>
									<?php echo esc_html__( 'Enable two-way sync', 'nonprofitsuite' ); ?>
								</label>
								<p class="description">
									<?php echo esc_html__( 'Sync events both ways (NonprofitSuite ↔ External Calendar)', 'nonprofitsuite' ); ?>
								</p>
							</td>
						</tr>
						<?php endif; ?>
					</table>
				</div>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php echo esc_html__( 'Save Settings', 'nonprofitsuite' ); ?>
					</button>
				</p>
			</form>

			<!-- Manual Sync -->
			<?php if ( $active_provider_id !== 'builtin' && $is_connected ) : ?>
				<div class="ns-card">
					<h2><?php echo esc_html__( 'Manual Sync', 'nonprofitsuite' ); ?></h2>
					<p class="description">
						<?php echo esc_html__( 'Manually trigger a sync with your external calendar.', 'nonprofitsuite' ); ?>
					</p>

					<form method="post" action="">
						<?php wp_nonce_field( 'ns_manual_calendar_sync' ); ?>
						<input type="hidden" name="manual_sync" value="1">
						<p>
							<button type="submit" class="button button-secondary">
								<span class="dashicons dashicons-update"></span>
								<?php echo esc_html__( 'Sync Now', 'nonprofitsuite' ); ?>
							</button>
						</p>
					</form>
				</div>

				<!-- Disconnect -->
				<div class="ns-card">
					<h2><?php echo esc_html__( 'Disconnect', 'nonprofitsuite' ); ?></h2>
					<p class="description">
						<?php echo esc_html__( 'Disconnect your calendar and remove all sync data.', 'nonprofitsuite' ); ?>
					</p>

					<form method="post" action="" onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to disconnect? This will remove all sync data.', 'nonprofitsuite' ) ); ?>');">
						<?php wp_nonce_field( 'ns_disconnect_calendar' ); ?>
						<input type="hidden" name="disconnect_provider" value="<?php echo esc_attr( $active_provider_id ); ?>">
						<p>
							<button type="submit" class="button button-secondary">
								<span class="dashicons dashicons-no"></span>
								<?php echo esc_html__( 'Disconnect Calendar', 'nonprofitsuite' ); ?>
							</button>
						</p>
					</form>
				</div>
			<?php endif; ?>
		<?php endif; ?>

	</div>
</div>

<style>
.ns-calendar-sync-container {
	max-width: 900px;
}

.ns-card {
	background: #fff;
	border: 1px solid #ccd0d4;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
	margin: 20px 0;
	padding: 20px;
}

.ns-card h2 {
	margin-top: 0;
	margin-bottom: 15px;
	font-size: 18px;
	border-bottom: 1px solid #ccd0d4;
	padding-bottom: 10px;
}

.ns-current-provider {
	padding: 15px;
	background: #f9f9f9;
	border-radius: 4px;
	margin-top: 15px;
}

.ns-status-badge {
	display: inline-block;
	padding: 4px 12px;
	border-radius: 12px;
	font-size: 12px;
	font-weight: 600;
	margin-left: 10px;
}

.ns-status-badge.connected {
	background: #d4edda;
	color: #155724;
}

.ns-status-badge.disconnected {
	background: #fff3cd;
	color: #856404;
}

.ns-status-badge .dashicons {
	font-size: 14px;
	line-height: 1;
	vertical-align: middle;
	margin-right: 3px;
}

.button .dashicons {
	line-height: 1.3;
	margin-right: 5px;
}
</style>
