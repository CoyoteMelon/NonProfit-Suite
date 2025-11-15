<?php
/**
 * The core plugin class
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 */
class NonprofitSuite_Core {

	/**
	 * The unique identifier of this plugin.
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 */
	public function __construct() {
		$this->plugin_name = 'nonprofitsuite';
		$this->version = NONPROFITSUITE_VERSION;

		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_cron_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * NOTE: With autoloader enabled, classes are now loaded on-demand.
	 * This method is kept for backwards compatibility and any immediate dependencies.
	 */
	private function load_dependencies() {
		// All classes are now loaded via autoloader (class-autoloader.php)
		// They will be loaded automatically when first used, dramatically improving performance.

		// Critical classes are already preloaded in nonprofitsuite.php via:
		// NonprofitSuite_Autoloader::preload_critical_classes()

		// No need to manually require_once 43+ module files anymore!
	}

	/**
	 * Register all hooks related to the admin area functionality.
	 */
	private function define_admin_hooks() {
		$plugin_admin = new NonprofitSuite_Admin( $this->plugin_name, $this->version );

		// Enqueue styles and scripts
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_scripts' ) );

		// Add admin menu
		add_action( 'admin_menu', array( $plugin_admin, 'add_admin_menu' ) );

		// Handle setup wizard redirect
		add_action( 'admin_init', array( $this, 'setup_wizard_redirect' ) );

		// Register AJAX handlers
		add_action( 'wp_ajax_ns_save_agenda_item', array( $this, 'ajax_save_agenda_item' ) );
		add_action( 'wp_ajax_ns_delete_agenda_item', array( $this, 'ajax_delete_agenda_item' ) );
		add_action( 'wp_ajax_ns_reorder_agenda_items', array( $this, 'ajax_reorder_agenda_items' ) );
		add_action( 'wp_ajax_ns_auto_save_minutes', array( $this, 'ajax_auto_save_minutes' ) );
		add_action( 'wp_ajax_ns_update_task_status', array( $this, 'ajax_update_task_status' ) );
		add_action( 'wp_ajax_ns_add_task_comment', array( $this, 'ajax_add_task_comment' ) );
		add_action( 'wp_ajax_ns_export_agenda_pdf', array( $this, 'ajax_export_agenda_pdf' ) );
		add_action( 'wp_ajax_ns_export_minutes_pdf', array( $this, 'ajax_export_minutes_pdf' ) );
		add_action( 'wp_ajax_ns_approve_minutes', array( $this, 'ajax_approve_minutes' ) );
		add_action( 'wp_ajax_ns_create_task_from_action_item', array( $this, 'ajax_create_task_from_action_item' ) );
	}

	/**
	 * Register all hooks related to the public-facing functionality.
	 */
	private function define_public_hooks() {
		// Public-facing functionality hooks will go here
		// For Phase 1 (Free version), this is primarily admin-focused
	}

	/**
	 * Register all cron hooks.
	 */
	private function define_cron_hooks() {
		// Register custom cron schedules
		add_filter( 'cron_schedules', array( $this, 'add_custom_cron_schedules' ) );

		// Document retention processing (runs daily)
		add_action( 'nonprofitsuite_process_document_retention', array( $this, 'cron_process_document_retention' ) );

		// Calendar sync processing (runs based on user setting: hourly, twicedaily, or daily)
		add_action( 'nonprofitsuite_calendar_sync', array( $this, 'cron_calendar_sync' ) );

		// Calendar reminder processing (runs every 5 minutes)
		add_action( 'nonprofitsuite_process_calendar_reminders', array( $this, 'cron_process_calendar_reminders' ) );
	}

	/**
	 * Add custom cron schedules.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array Modified schedules.
	 */
	public function add_custom_cron_schedules( $schedules ) {
		$schedules['ns_every_5_minutes'] = array(
			'interval' => 300, // 5 minutes in seconds
			'display'  => __( 'Every 5 Minutes', 'nonprofitsuite' ),
		);

		return $schedules;
	}

	/**
	 * Cron handler: Process document retention.
	 *
	 * Runs daily to archive and expire documents based on retention policies.
	 */
	public function cron_process_document_retention() {
		// Process automatic archival
		$archival_results = NonprofitSuite_Document_Retention::process_auto_archival();

		// Process expiration
		$expiration_results = NonprofitSuite_Document_Retention::process_expiration();

		// Log results
		error_log( sprintf(
			'NonProfit-Suite Document Retention: %d documents archived, %d documents expired',
			$archival_results['archived_count'],
			$expiration_results['expired_count']
		) );

		if ( ! empty( $archival_results['errors'] ) ) {
			error_log( 'Document archival errors: ' . print_r( $archival_results['errors'], true ) );
		}
	}

	/**
	 * Cron handler: Sync calendar events.
	 *
	 * Runs periodically to sync events with external calendar providers.
	 */
	public function cron_calendar_sync() {
		// Check if auto-sync is enabled
		$auto_sync = get_option( 'ns_calendar_auto_sync', 1 );
		if ( ! $auto_sync ) {
			return;
		}

		// Get Integration Manager
		$integration_manager = NonprofitSuite_Integration_Manager::get_instance();

		// Get active calendar provider
		$active_provider_id = $integration_manager->get_active_provider_id( 'calendar' );

		// Skip sync for built-in calendar (no external provider to sync with)
		if ( $active_provider_id === 'builtin' ) {
			return;
		}

		// Check if provider is connected
		if ( ! $integration_manager->is_provider_connected( 'calendar', $active_provider_id ) ) {
			error_log( 'NonProfit-Suite Calendar Sync: Provider not connected, skipping sync' );
			return;
		}

		// Get adapter instance
		$adapter = $integration_manager->get_active_provider( 'calendar' );

		if ( is_wp_error( $adapter ) ) {
			error_log( 'NonProfit-Suite Calendar Sync error: ' . $adapter->get_error_message() );
			return;
		}

		// Perform sync
		$result = $adapter->sync_events();

		if ( is_wp_error( $result ) ) {
			error_log( 'NonProfit-Suite Calendar Sync failed: ' . $result->get_error_message() );
		} else {
			error_log( sprintf(
				'NonProfit-Suite Calendar Sync completed: %d events synced, %d skipped',
				$result['synced_count'],
				$result['skipped_count']
			) );

			if ( ! empty( $result['errors'] ) ) {
				error_log( 'Calendar sync errors: ' . print_r( $result['errors'], true ) );
			}
		}
	}

	/**
	 * Cron handler: Process calendar reminders.
	 *
	 * Runs every 5 minutes to send due reminder notifications.
	 */
	public function cron_process_calendar_reminders() {
		$results = NonprofitSuite_Calendar_Reminders::process_due_reminders();

		if ( $results['sent_count'] > 0 || $results['failed_count'] > 0 ) {
			error_log( sprintf(
				'NonProfit-Suite Calendar Reminders: %d sent, %d failed',
				$results['sent_count'],
				$results['failed_count']
			) );

			if ( ! empty( $results['errors'] ) ) {
				error_log( 'Reminder errors: ' . print_r( $results['errors'], true ) );
			}
		}
	}

	/**
	 * Redirect to setup wizard on activation.
	 */
	public function setup_wizard_redirect() {
		if ( get_transient( 'nonprofitsuite_activation_redirect' ) ) {
			delete_transient( 'nonprofitsuite_activation_redirect' );

			// Don't redirect if activating multiple plugins or doing bulk activation
			if ( ! isset( $_GET['activate-multi'] ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=nonprofitsuite-setup' ) );
				exit;
			}
		}
	}

	/**
	 * AJAX handler: Save agenda item.
	 */
	public function ajax_save_agenda_item() {
		check_ajax_referer( 'ns_save_agenda_item', 'nonce' );

		// Rate limiting
		if ( ! NonprofitSuite_Rate_Limiter::check( 'save_agenda_item', 'ajax_write' ) ) {
			NonprofitSuite_Rate_Limiter::send_error( 'save_agenda_item' );
		}

		if ( ! current_user_can( 'ns_manage_meetings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'nonprofitsuite' ) ), 403 );
		}

		// Sanitize and validate input
		$raw = wp_unslash( $_POST );
		$payload = array(
			'meeting_id' => isset( $raw['meeting_id'] ) ? absint( $raw['meeting_id'] ) : 0,
			'title'      => isset( $raw['title'] ) ? sanitize_text_field( $raw['title'] ) : '',
			'notes'      => isset( $raw['notes'] ) ? wp_kses_post( $raw['notes'] ) : '',
			'duration'   => isset( $raw['duration'] ) ? absint( $raw['duration'] ) : 0,
			'order'      => isset( $raw['order'] ) ? absint( $raw['order'] ) : 0,
		);

		if ( $payload['meeting_id'] <= 0 || empty( $payload['title'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing required parameters.', 'nonprofitsuite' ) ), 400 );
		}

		$agenda = new NonprofitSuite_Agenda();
		$result = $agenda->save_item( $payload );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Agenda item saved.', 'nonprofitsuite' ), 'item_id' => $result ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to save agenda item.', 'nonprofitsuite' ) ) );
		}
	}

	/**
	 * AJAX handler: Delete agenda item.
	 */
	public function ajax_delete_agenda_item() {
		check_ajax_referer( 'ns_delete_agenda_item', 'nonce' );

		// Rate limiting
		if ( ! NonprofitSuite_Rate_Limiter::check( 'delete_agenda_item', 'ajax_write' ) ) {
			NonprofitSuite_Rate_Limiter::send_error( 'delete_agenda_item' );
		}

		if ( ! current_user_can( 'ns_manage_meetings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'nonprofitsuite' ) ), 403 );
		}

		$item_id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;

		if ( $item_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid item ID.', 'nonprofitsuite' ) ), 400 );
		}

		$agenda = new NonprofitSuite_Agenda();
		$result = $agenda->delete_item( $item_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Agenda item deleted.', 'nonprofitsuite' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete agenda item.', 'nonprofitsuite' ) ) );
		}
	}

	/**
	 * AJAX handler: Reorder agenda items.
	 */
	public function ajax_reorder_agenda_items() {
		check_ajax_referer( 'ns_reorder_agenda_items', 'nonce' );

		// Rate limiting
		if ( ! NonprofitSuite_Rate_Limiter::check( 'reorder_agenda_items', 'ajax_write' ) ) {
			NonprofitSuite_Rate_Limiter::send_error( 'reorder_agenda_items' );
		}

		if ( ! current_user_can( 'ns_manage_meetings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'nonprofitsuite' ) ), 403 );
		}

		// Decode and validate JSON data using WordPress functions
		$order_raw = isset( $_POST['order'] ) ? wp_unslash( $_POST['order'] ) : '[]';
		$order_arr = json_decode( $order_raw, true );

		if ( ! is_array( $order_arr ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order data.', 'nonprofitsuite' ) ), 400 );
		}

		$order = array_map( 'absint', $order_arr );

		$agenda = new NonprofitSuite_Agenda();
		$result = $agenda->reorder_items( $order );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Agenda order updated.', 'nonprofitsuite' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to update order.', 'nonprofitsuite' ) ) );
		}
	}

	/**
	 * AJAX handler: Auto-save minutes.
	 */
	public function ajax_auto_save_minutes() {
		check_ajax_referer( 'ns_auto_save_minutes', 'nonce' );

		// Rate limiting - Higher limit for auto-save
		if ( ! NonprofitSuite_Rate_Limiter::check( 'auto_save_minutes', 'ajax_autosave' ) ) {
			NonprofitSuite_Rate_Limiter::send_error( 'auto_save_minutes' );
		}

		if ( ! current_user_can( 'ns_edit_minutes' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'nonprofitsuite' ) ), 403 );
		}

		// Sanitize and validate input
		$raw = wp_unslash( $_POST );
		$payload = array(
			'meeting_id' => isset( $raw['meeting_id'] ) ? absint( $raw['meeting_id'] ) : 0,
			'content'    => isset( $raw['content'] ) ? wp_kses_post( $raw['content'] ) : '',
			'attendees'  => isset( $raw['attendees'] ) ? array_map( 'absint', (array) $raw['attendees'] ) : array(),
		);

		if ( $payload['meeting_id'] <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid meeting ID.', 'nonprofitsuite' ) ), 400 );
		}

		$minutes = new NonprofitSuite_Minutes();
		$result = $minutes->auto_save( $payload );

		if ( $result ) {
			wp_send_json_success( array(
				'message' => __( 'Minutes auto-saved.', 'nonprofitsuite' ),
				'timestamp' => current_time( 'mysql' )
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to auto-save minutes.', 'nonprofitsuite' ) ) );
		}
	}

	/**
	 * AJAX handler: Update task status.
	 */
	public function ajax_update_task_status() {
		check_ajax_referer( 'ns_update_task_status', 'nonce' );

		// Rate limiting
		if ( ! NonprofitSuite_Rate_Limiter::check( 'update_task_status', 'ajax_write' ) ) {
			NonprofitSuite_Rate_Limiter::send_error( 'update_task_status' );
		}

		if ( ! current_user_can( 'ns_manage_own_tasks' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'nonprofitsuite' ) ), 403 );
		}

		$task_id = isset( $_POST['task_id'] ) ? absint( $_POST['task_id'] ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';

		if ( $task_id <= 0 || empty( $status ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'nonprofitsuite' ) ), 400 );
		}

		// Validate status value
		$valid_statuses = array( 'pending', 'in_progress', 'completed', 'cancelled' );
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid status value.', 'nonprofitsuite' ) ), 400 );
		}

		$tasks = new NonprofitSuite_Tasks();
		$result = $tasks->update_status( $task_id, $status );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Task status updated.', 'nonprofitsuite' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to update task status.', 'nonprofitsuite' ) ) );
		}
	}

	/**
	 * AJAX handler: Add task comment.
	 */
	public function ajax_add_task_comment() {
		check_ajax_referer( 'ns_add_task_comment', 'nonce' );

		// Rate limiting
		if ( ! NonprofitSuite_Rate_Limiter::check( 'add_task_comment', 'ajax_write' ) ) {
			NonprofitSuite_Rate_Limiter::send_error( 'add_task_comment' );
		}

		if ( ! current_user_can( 'ns_manage_own_tasks' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'nonprofitsuite' ) ), 403 );
		}

		// Sanitize and validate input
		$raw = wp_unslash( $_POST );
		$payload = array(
			'task_id' => isset( $raw['task_id'] ) ? absint( $raw['task_id'] ) : 0,
			'comment' => isset( $raw['comment'] ) ? sanitize_textarea_field( $raw['comment'] ) : '',
		);

		if ( $payload['task_id'] <= 0 || empty( $payload['comment'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing required parameters.', 'nonprofitsuite' ) ), 400 );
		}

		$tasks = new NonprofitSuite_Tasks();
		$result = $tasks->add_comment( $payload );

		if ( $result ) {
			wp_send_json_success( array(
				'message' => __( 'Comment added.', 'nonprofitsuite' ),
				'comment_id' => $result
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to add comment.', 'nonprofitsuite' ) ) );
		}
	}

	/**
	 * AJAX handler: Export agenda to PDF.
	 */
	public function ajax_export_agenda_pdf() {
		check_ajax_referer( 'ns_export_agenda_pdf', 'nonce' );

		// Rate limiting - Lower limit for resource-intensive exports
		if ( ! NonprofitSuite_Rate_Limiter::check( 'export_agenda_pdf', 'ajax_export' ) ) {
			NonprofitSuite_Rate_Limiter::send_error( 'export_agenda_pdf' );
		}

		if ( ! current_user_can( 'ns_view_meetings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'nonprofitsuite' ) ), 403 );
		}

		$meeting_id = isset( $_POST['meeting_id'] ) ? absint( $_POST['meeting_id'] ) : 0;

		if ( $meeting_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid meeting ID.', 'nonprofitsuite' ) ), 400 );
		}

		$result = NonprofitSuite_PDF_Generator::generate_agenda( $meeting_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			// Validate and convert path to URL safely
			$upload_dir = wp_upload_dir();
			$basedir = wp_normalize_path( $upload_dir['basedir'] );
			$result_path = wp_normalize_path( $result );

			// Ensure the file is within uploads directory
			if ( 0 !== strpos( $result_path, $basedir ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid file path returned.', 'nonprofitsuite' ) ) );
			}

			$pdf_url = str_replace( $basedir, $upload_dir['baseurl'], $result_path );
			$pdf_url = esc_url_raw( $pdf_url );

			wp_send_json_success( array(
				'message' => __( 'Agenda exported successfully.', 'nonprofitsuite' ),
				'url' => $pdf_url
			) );
		}
	}

	/**
	 * AJAX handler: Export minutes to PDF.
	 */
	public function ajax_export_minutes_pdf() {
		check_ajax_referer( 'ns_export_minutes_pdf', 'nonce' );

		// Rate limiting - Lower limit for resource-intensive exports
		if ( ! NonprofitSuite_Rate_Limiter::check( 'export_minutes_pdf', 'ajax_export' ) ) {
			NonprofitSuite_Rate_Limiter::send_error( 'export_minutes_pdf' );
		}

		if ( ! current_user_can( 'ns_view_meetings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'nonprofitsuite' ) ), 403 );
		}

		$meeting_id = isset( $_POST['meeting_id'] ) ? absint( $_POST['meeting_id'] ) : 0;

		if ( $meeting_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid meeting ID.', 'nonprofitsuite' ) ), 400 );
		}

		$result = NonprofitSuite_PDF_Generator::generate_minutes( $meeting_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			// Validate and convert path to URL safely
			$upload_dir = wp_upload_dir();
			$basedir = wp_normalize_path( $upload_dir['basedir'] );
			$result_path = wp_normalize_path( $result );

			// Ensure the file is within uploads directory
			if ( 0 !== strpos( $result_path, $basedir ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid file path returned.', 'nonprofitsuite' ) ) );
			}

			$pdf_url = str_replace( $basedir, $upload_dir['baseurl'], $result_path );
			$pdf_url = esc_url_raw( $pdf_url );

			wp_send_json_success( array(
				'message' => __( 'Minutes exported successfully.', 'nonprofitsuite' ),
				'url' => $pdf_url
			) );
		}
	}

	/**
	 * AJAX handler: Approve minutes.
	 */
	public function ajax_approve_minutes() {
		check_ajax_referer( 'ns_approve_minutes', 'nonce' );

		// Rate limiting
		if ( ! NonprofitSuite_Rate_Limiter::check( 'approve_minutes', 'ajax_write' ) ) {
			NonprofitSuite_Rate_Limiter::send_error( 'approve_minutes' );
		}

		if ( ! current_user_can( 'ns_approve_minutes' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied. Only board members can approve minutes.', 'nonprofitsuite' ) ), 403 );
		}

		$minutes_id = isset( $_POST['minutes_id'] ) ? absint( $_POST['minutes_id'] ) : 0;

		if ( $minutes_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid minutes ID.', 'nonprofitsuite' ) ), 400 );
		}

		$approver_id = get_current_user_id();

		$result = NonprofitSuite_Minutes::approve( $minutes_id, $approver_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Minutes approved successfully.', 'nonprofitsuite' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to approve minutes.', 'nonprofitsuite' ) ) );
		}
	}

	/**
	 * AJAX handler: Create task from action item.
	 */
	public function ajax_create_task_from_action_item() {
		check_ajax_referer( 'ns_create_task_from_action_item', 'nonce' );

		// Rate limiting
		if ( ! NonprofitSuite_Rate_Limiter::check( 'create_task_from_action_item', 'ajax_write' ) ) {
			NonprofitSuite_Rate_Limiter::send_error( 'create_task_from_action_item' );
		}

		if ( ! current_user_can( 'ns_manage_own_tasks' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'nonprofitsuite' ) ), 403 );
		}

		// Sanitize and validate input using wp_unslash
		$raw = wp_unslash( $_POST );
		$task_data = array(
			'title'       => isset( $raw['title'] ) ? sanitize_text_field( $raw['title'] ) : '',
			'description' => isset( $raw['description'] ) ? sanitize_textarea_field( $raw['description'] ) : '',
			'assigned_to' => isset( $raw['assigned_to'] ) ? absint( $raw['assigned_to'] ) : null,
			'due_date'    => isset( $raw['due_date'] ) ? sanitize_text_field( $raw['due_date'] ) : null,
			'priority'    => isset( $raw['priority'] ) ? sanitize_text_field( $raw['priority'] ) : 'medium',
			'source_type' => 'meeting',
			'source_id'   => isset( $raw['meeting_id'] ) ? absint( $raw['meeting_id'] ) : null,
		);

		if ( empty( $task_data['title'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Task title is required.', 'nonprofitsuite' ) ), 400 );
		}

		// Validate priority
		$valid_priorities = array( 'low', 'medium', 'high', 'urgent' );
		if ( ! in_array( $task_data['priority'], $valid_priorities, true ) ) {
			$task_data['priority'] = 'medium';
		}

		$task_id = NonprofitSuite_Tasks::create( $task_data );

		if ( $task_id ) {
			wp_send_json_success( array(
				'message' => __( 'Action item created as task successfully.', 'nonprofitsuite' ),
				'task_id' => $task_id
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to create task.', 'nonprofitsuite' ) ) );
		}
	}

	/**
	 * Run the plugin.
	 */
	public function run() {
		// Plugin is now running with all hooks registered
	}

	/**
	 * Get the plugin name.
	 *
	 * @return string
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Get the plugin version.
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}
}
