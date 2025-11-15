<?php
/**
 * Provider Migration Admin
 *
 * Admin interface for provider-to-provider migrations.
 *
 * @package NonprofitSuite
 * @subpackage Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_Provider_Migration_Admin {

	/**
	 * Manager instance.
	 *
	 * @var NS_Provider_Migration_Manager
	 */
	private $manager;

	/**
	 * Constructor.
	 */
	public function __construct() {
		require_once NS_PLUGIN_DIR . 'includes/helpers/class-provider-migration-manager.php';
		$this->manager = NS_Provider_Migration_Manager::get_instance();

		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function init_hooks() {
		// AJAX handlers
		add_action( 'wp_ajax_ns_provider_migration_create', array( $this, 'ajax_create_migration' ) );
		add_action( 'wp_ajax_ns_provider_migration_analyze', array( $this, 'ajax_analyze_source' ) );
		add_action( 'wp_ajax_ns_provider_migration_execute', array( $this, 'ajax_execute_migration' ) );
		add_action( 'wp_ajax_ns_provider_migration_rollback', array( $this, 'ajax_rollback_migration' ) );
		add_action( 'wp_ajax_ns_provider_migration_get_providers', array( $this, 'ajax_get_providers' ) );
	}

	/**
	 * AJAX: Create provider migration job.
	 */
	public function ajax_create_migration() {
		check_ajax_referer( 'nonprofitsuite_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'nonprofitsuite' ) ) );
		}

		$organization_id      = absint( $_POST['organization_id'] ?? 0 );
		$migration_name       = sanitize_text_field( $_POST['migration_name'] ?? '' );
		$integration_type     = sanitize_text_field( $_POST['integration_type'] ?? '' );
		$source_provider      = sanitize_text_field( $_POST['source_provider'] ?? '' );
		$destination_provider = sanitize_text_field( $_POST['destination_provider'] ?? '' );
		$data_types           = isset( $_POST['data_types'] ) ? array_map( 'sanitize_text_field', (array) $_POST['data_types'] ) : array();
		$field_mapping        = isset( $_POST['field_mapping'] ) ? (array) $_POST['field_mapping'] : array();
		$migration_mode       = sanitize_text_field( $_POST['migration_mode'] ?? 'preview' );

		$job_id = $this->manager->create_migration_job( array(
			'organization_id'      => $organization_id,
			'migration_name'       => $migration_name,
			'integration_type'     => $integration_type,
			'source_provider'      => $source_provider,
			'destination_provider' => $destination_provider,
			'data_types'           => $data_types,
			'field_mapping'        => $field_mapping,
			'migration_mode'       => $migration_mode,
		) );

		if ( is_wp_error( $job_id ) ) {
			wp_send_json_error( array( 'message' => $job_id->get_error_message() ) );
		}

		wp_send_json_success( array(
			'job_id'  => $job_id,
			'message' => __( 'Migration job created successfully', 'nonprofitsuite' ),
		) );
	}

	/**
	 * AJAX: Analyze source provider.
	 */
	public function ajax_analyze_source() {
		check_ajax_referer( 'nonprofitsuite_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'nonprofitsuite' ) ) );
		}

		$job_id = absint( $_POST['job_id'] ?? 0 );

		$analysis = $this->manager->analyze_source( $job_id );

		if ( is_wp_error( $analysis ) ) {
			wp_send_json_error( array( 'message' => $analysis->get_error_message() ) );
		}

		wp_send_json_success( array(
			'analysis' => $analysis,
			'message'  => __( 'Analysis completed', 'nonprofitsuite' ),
		) );
	}

	/**
	 * AJAX: Execute migration.
	 */
	public function ajax_execute_migration() {
		check_ajax_referer( 'nonprofitsuite_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'nonprofitsuite' ) ) );
		}

		$job_id = absint( $_POST['job_id'] ?? 0 );

		$results = $this->manager->execute_migration( $job_id );

		if ( is_wp_error( $results ) ) {
			wp_send_json_error( array( 'message' => $results->get_error_message() ) );
		}

		wp_send_json_success( array(
			'results' => $results,
			'message' => __( 'Migration completed', 'nonprofitsuite' ),
		) );
	}

	/**
	 * AJAX: Rollback migration.
	 */
	public function ajax_rollback_migration() {
		check_ajax_referer( 'nonprofitsuite_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'nonprofitsuite' ) ) );
		}

		$job_id = absint( $_POST['job_id'] ?? 0 );

		$results = $this->manager->rollback_migration( $job_id );

		if ( is_wp_error( $results ) ) {
			wp_send_json_error( array( 'message' => $results->get_error_message() ) );
		}

		wp_send_json_success( array(
			'results' => $results,
			'message' => __( 'Migration rolled back', 'nonprofitsuite' ),
		) );
	}

	/**
	 * AJAX: Get available providers for integration type.
	 */
	public function ajax_get_providers() {
		check_ajax_referer( 'nonprofitsuite_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'nonprofitsuite' ) ) );
		}

		$integration_type = sanitize_text_field( $_POST['integration_type'] ?? '' );
		$organization_id  = absint( $_POST['organization_id'] ?? 0 );

		$integrations = $this->manager->get_supported_integrations();

		if ( ! isset( $integrations[ $integration_type ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid integration type', 'nonprofitsuite' ) ) );
		}

		// Get configured providers for this organization
		$configured_providers = $this->get_configured_providers( $integration_type, $organization_id );

		wp_send_json_success( array(
			'providers'            => $integrations[ $integration_type ]['providers'],
			'data_types'           => $integrations[ $integration_type ]['data_types'],
			'configured_providers' => $configured_providers,
		) );
	}

	/**
	 * Get configured providers for an integration type.
	 *
	 * @param string $integration_type Integration type.
	 * @param int    $organization_id  Organization ID.
	 * @return array Configured providers.
	 */
	private function get_configured_providers( $integration_type, $organization_id ) {
		global $wpdb;

		$tables = array(
			'crm'       => 'ns_crm_settings',
			'calendar'  => 'ns_calendar_settings',
			'email'     => 'ns_marketing_settings',
			'payment'   => 'ns_payment_processors',
			'video'     => 'ns_video_conferencing_settings',
			'forms'     => 'ns_forms_settings',
			'pm'        => 'ns_project_management_settings',
			'sms'       => 'ns_sms_settings',
			'analytics' => 'ns_analytics_settings',
			'ai'        => 'ns_ai_settings',
		);

		if ( ! isset( $tables[ $integration_type ] ) ) {
			return array();
		}

		$table = $wpdb->prefix . $tables[ $integration_type ];

		$providers = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT provider FROM {$table} WHERE organization_id = %d AND is_active = 1",
				$organization_id
			)
		);

		return $providers;
	}
}

// Initialize
new NS_Provider_Migration_Admin();
