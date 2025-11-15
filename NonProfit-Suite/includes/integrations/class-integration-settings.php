<?php
/**
 * Integration Settings
 *
 * Manages the settings UI for third-party integrations.
 *
 * @package    NonprofitSuite
 * @subpackage Integrations
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonprofitSuite_Integration_Settings Class
 *
 * Handles the administration interface for integration settings.
 */
class NonprofitSuite_Integration_Settings {

	/**
	 * Integration Manager instance
	 *
	 * @var NonprofitSuite_Integration_Manager
	 */
	private $manager;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->manager = NonprofitSuite_Integration_Manager::get_instance();
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ), 20 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_ns_test_integration', array( $this, 'ajax_test_integration' ) );
		add_action( 'wp_ajax_ns_disconnect_integration', array( $this, 'ajax_disconnect_integration' ) );
	}

	/**
	 * Add settings page to admin menu
	 */
	public function add_settings_page() {
		add_submenu_page(
			'nonprofitsuite',
			__( 'Integration Settings', 'nonprofitsuite' ),
			__( 'Integrations', 'nonprofitsuite' ),
			'manage_options',
			'nonprofitsuite-integrations',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		// Register settings for each category
		foreach ( $this->manager->get_categories() as $category => $label ) {
			register_setting(
				'nonprofitsuite_integrations',
				"ns_integration_{$category}_provider"
			);
		}
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to access this page.', 'nonprofitsuite' ) );
		}

		// Handle form submissions
		if ( isset( $_POST['ns_integration_settings'] ) && check_admin_referer( 'ns_integration_settings' ) ) {
			$this->save_settings();
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Integration Settings', 'nonprofitsuite' ); ?></h1>
			<p class="description">
				<?php echo esc_html__( 'Configure third-party integrations to extend NonprofitSuite with your preferred services.', 'nonprofitsuite' ); ?>
			</p>

			<form method="post" action="" id="ns-integration-settings-form">
				<?php wp_nonce_field( 'ns_integration_settings' ); ?>
				<input type="hidden" name="ns_integration_settings" value="1">

				<?php $this->render_integration_sections(); ?>

				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Changes', 'nonprofitsuite' ); ?>">
				</p>
			</form>
		</div>

		<style>
			.ns-integration-section {
				background: #fff;
				border: 1px solid #ccd0d4;
				box-shadow: 0 1px 1px rgba(0,0,0,.04);
				margin: 20px 0;
				padding: 0;
			}
			.ns-integration-section h2 {
				margin: 0;
				padding: 15px 20px;
				border-bottom: 1px solid #ccd0d4;
				font-size: 14px;
				line-height: 1.4;
			}
			.ns-integration-section .inside {
				padding: 20px;
			}
			.ns-provider-option {
				margin: 15px 0;
				padding: 15px;
				background: #f9f9f9;
				border-left: 4px solid #ddd;
			}
			.ns-provider-option.active {
				border-left-color: #46b450;
				background: #f0f9f1;
			}
			.ns-provider-option.pro-required {
				opacity: 0.6;
			}
			.ns-provider-name {
				font-weight: 600;
				font-size: 14px;
				margin-bottom: 5px;
			}
			.ns-provider-description {
				color: #666;
				font-size: 13px;
				margin: 5px 0;
			}
			.ns-provider-status {
				margin-top: 10px;
			}
			.ns-provider-status .dashicons {
				margin-right: 5px;
			}
			.ns-provider-actions {
				margin-top: 10px;
			}
			.ns-badge {
				display: inline-block;
				padding: 3px 8px;
				font-size: 11px;
				font-weight: 600;
				border-radius: 3px;
				margin-left: 8px;
			}
			.ns-badge.default {
				background: #2271b1;
				color: #fff;
			}
			.ns-badge.free {
				background: #46b450;
				color: #fff;
			}
			.ns-badge.recommended {
				background: #f0b849;
				color: #000;
			}
			.ns-badge.pro {
				background: #d63638;
				color: #fff;
			}
		</style>
		<?php
	}

	/**
	 * Render integration sections
	 */
	private function render_integration_sections() {
		$categories = $this->manager->get_categories();

		foreach ( $categories as $category => $label ) {
			$this->render_integration_section( $category, $label );
		}
	}

	/**
	 * Render a single integration section
	 *
	 * @param string $category Category slug
	 * @param string $label    Category label
	 */
	private function render_integration_section( $category, $label ) {
		$providers = $this->manager->get_providers( $category );
		$active_provider_id = $this->manager->get_active_provider_id( $category );

		?>
		<div class="ns-integration-section">
			<h2><?php echo esc_html( $label ); ?></h2>
			<div class="inside">
				<?php if ( empty( $providers ) ) : ?>
					<p><?php esc_html_e( 'No providers available for this category.', 'nonprofitsuite' ); ?></p>
				<?php else : ?>
					<?php foreach ( $providers as $provider_id => $provider ) : ?>
						<?php
						$is_active = ( $provider_id === $active_provider_id );
						$is_connected = $this->manager->is_provider_connected( $category, $provider_id );
						$requires_pro = ! empty( $provider['requires_pro'] );
						$has_pro = NonprofitSuite_License::is_pro_active();
						$can_use = ! $requires_pro || $has_pro;
						?>
						<div class="ns-provider-option <?php echo $is_active ? 'active' : ''; ?> <?php echo ! $can_use ? 'pro-required' : ''; ?>">
							<div class="ns-provider-header">
								<label>
									<input type="radio"
										name="ns_integration_<?php echo esc_attr( $category ); ?>_provider"
										value="<?php echo esc_attr( $provider_id ); ?>"
										<?php checked( $is_active ); ?>
										<?php disabled( ! $can_use ); ?>
									/>
									<span class="ns-provider-name">
										<?php echo esc_html( $provider['name'] ); ?>

										<?php if ( ! empty( $provider['is_default'] ) ) : ?>
											<span class="ns-badge default"><?php esc_html_e( 'Default', 'nonprofitsuite' ); ?></span>
										<?php endif; ?>

										<?php if ( ! empty( $provider['is_free'] ) ) : ?>
											<span class="ns-badge free"><?php esc_html_e( 'Free', 'nonprofitsuite' ); ?></span>
										<?php endif; ?>

										<?php if ( ! empty( $provider['recommended'] ) ) : ?>
											<span class="ns-badge recommended"><?php esc_html_e( 'Recommended', 'nonprofitsuite' ); ?></span>
										<?php endif; ?>

										<?php if ( $requires_pro ) : ?>
											<span class="ns-badge pro"><?php esc_html_e( 'PRO', 'nonprofitsuite' ); ?></span>
										<?php endif; ?>
									</span>
								</label>
							</div>

							<?php if ( ! empty( $provider['description'] ) ) : ?>
								<p class="ns-provider-description"><?php echo esc_html( $provider['description'] ); ?></p>
							<?php endif; ?>

							<?php if ( $is_active ) : ?>
								<div class="ns-provider-status">
									<?php if ( $is_connected ) : ?>
										<span style="color: #46b450;">
											<span class="dashicons dashicons-yes-alt"></span>
											<?php esc_html_e( 'Connected', 'nonprofitsuite' ); ?>
										</span>
									<?php else : ?>
										<span style="color: #d63638;">
											<span class="dashicons dashicons-warning"></span>
											<?php esc_html_e( 'Not configured', 'nonprofitsuite' ); ?>
										</span>
									<?php endif; ?>
								</div>

								<div class="ns-provider-actions">
									<button type="button"
										class="button button-secondary ns-test-connection"
										data-category="<?php echo esc_attr( $category ); ?>"
										data-provider="<?php echo esc_attr( $provider_id ); ?>">
										<?php esc_html_e( 'Test Connection', 'nonprofitsuite' ); ?>
									</button>

									<?php if ( ! $provider['is_default'] ) : ?>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-integrations&configure=' . $category . '/' . $provider_id ) ); ?>"
											class="button button-secondary">
											<?php esc_html_e( 'Configure', 'nonprofitsuite' ); ?>
										</a>

										<?php if ( $is_connected ) : ?>
											<button type="button"
												class="button button-link-delete ns-disconnect"
												data-category="<?php echo esc_attr( $category ); ?>"
												data-provider="<?php echo esc_attr( $provider_id ); ?>">
												<?php esc_html_e( 'Disconnect', 'nonprofitsuite' ); ?>
											</button>
										<?php endif; ?>
									<?php endif; ?>
								</div>
							<?php endif; ?>

							<?php if ( $requires_pro && ! $has_pro ) : ?>
								<p style="color: #d63638; margin-top: 10px;">
									<?php
									printf(
										__( 'This integration requires a <a href="%s">Pro license</a>.', 'nonprofitsuite' ),
										admin_url( 'admin.php?page=nonprofitsuite-license' )
									);
									?>
								</p>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save settings
	 */
	private function save_settings() {
		$categories = $this->manager->get_categories();

		foreach ( $categories as $category => $label ) {
			$field_name = "ns_integration_{$category}_provider";

			if ( isset( $_POST[ $field_name ] ) ) {
				$provider_id = sanitize_text_field( $_POST[ $field_name ] );
				$this->manager->set_active_provider( $category, $provider_id );
			}
		}

		add_settings_error(
			'nonprofitsuite_integrations',
			'settings_saved',
			__( 'Integration settings saved.', 'nonprofitsuite' ),
			'success'
		);

		settings_errors( 'nonprofitsuite_integrations' );
	}

	/**
	 * AJAX handler: Test integration connection
	 */
	public function ajax_test_integration() {
		check_ajax_referer( 'nonprofitsuite_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'nonprofitsuite' ) ) );
		}

		$category = isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : '';

		if ( empty( $category ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid category', 'nonprofitsuite' ) ) );
		}

		$adapter = $this->manager->get_active_provider( $category );

		if ( is_wp_error( $adapter ) ) {
			wp_send_json_error( array( 'message' => $adapter->get_error_message() ) );
		}

		$result = $adapter->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Connection successful!', 'nonprofitsuite' ) ) );
	}

	/**
	 * AJAX handler: Disconnect integration
	 */
	public function ajax_disconnect_integration() {
		check_ajax_referer( 'nonprofitsuite_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'nonprofitsuite' ) ) );
		}

		$category = isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : '';
		$provider_id = isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : '';

		if ( empty( $category ) || empty( $provider_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters', 'nonprofitsuite' ) ) );
		}

		$this->manager->mark_provider_disconnected( $category, $provider_id );
		$this->manager->delete_provider_settings( $category, $provider_id );

		wp_send_json_success( array( 'message' => __( 'Integration disconnected', 'nonprofitsuite' ) ) );
	}
}

// Initialize settings
new NonprofitSuite_Integration_Settings();
