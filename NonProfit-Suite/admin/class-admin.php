<?php
/**
 * The admin-specific functionality of the plugin
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/admin
 */

class NonprofitSuite_Admin {

	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Enqueue admin styles.
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			$this->plugin_name,
			NONPROFITSUITE_URL . 'admin/css/admin.css',
			array(),
			$this->version,
			'all'
		);

		wp_enqueue_style(
			$this->plugin_name . '-mobile',
			NONPROFITSUITE_URL . 'admin/css/mobile.css',
			array( $this->plugin_name ),
			$this->version,
			'all'
		);
	}

	/**
	 * Enqueue admin scripts.
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'jquery-ui-sortable' );

		wp_enqueue_script(
			$this->plugin_name,
			NONPROFITSUITE_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			$this->version,
			false
		);

		wp_localize_script(
			$this->plugin_name,
			'nonprofitsuiteAjax',
			array(
				'ajax_url'                           => admin_url( 'admin-ajax.php' ),
				'save_agenda_item_nonce'             => wp_create_nonce( 'ns_save_agenda_item' ),
				'delete_agenda_item_nonce'           => wp_create_nonce( 'ns_delete_agenda_item' ),
				'reorder_agenda_items_nonce'         => wp_create_nonce( 'ns_reorder_agenda_items' ),
				'auto_save_minutes_nonce'            => wp_create_nonce( 'ns_auto_save_minutes' ),
				'update_task_status_nonce'           => wp_create_nonce( 'ns_update_task_status' ),
				'add_task_comment_nonce'             => wp_create_nonce( 'ns_add_task_comment' ),
				'export_agenda_pdf_nonce'            => wp_create_nonce( 'ns_export_agenda_pdf' ),
				'export_minutes_pdf_nonce'           => wp_create_nonce( 'ns_export_minutes_pdf' ),
				'approve_minutes_nonce'              => wp_create_nonce( 'ns_approve_minutes' ),
				'create_task_from_action_item_nonce' => wp_create_nonce( 'ns_create_task_from_action_item' ),
			)
		);
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		// Main menu
		add_menu_page(
			__( 'NonprofitSuite', 'nonprofitsuite' ),
			__( 'NonprofitSuite', 'nonprofitsuite' ),
			'read',
			'nonprofitsuite',
			array( $this, 'display_dashboard' ),
			'dashicons-groups',
			30
		);

		// Dashboard
		add_submenu_page(
			'nonprofitsuite',
			__( 'Dashboard', 'nonprofitsuite' ),
			__( 'Dashboard', 'nonprofitsuite' ),
			'read',
			'nonprofitsuite',
			array( $this, 'display_dashboard' )
		);

		// Meetings
		add_submenu_page(
			'nonprofitsuite',
			__( 'Meetings', 'nonprofitsuite' ),
			__( 'Meetings', 'nonprofitsuite' ),
			'ns_manage_meetings',
			'nonprofitsuite-meetings',
			array( $this, 'display_meetings' )
		);

		// Tasks
		add_submenu_page(
			'nonprofitsuite',
			__( 'Tasks', 'nonprofitsuite' ),
			__( 'Tasks', 'nonprofitsuite' ),
			'ns_manage_own_tasks',
			'nonprofitsuite-tasks',
			array( $this, 'display_tasks' )
		);

		// Documents
		add_submenu_page(
			'nonprofitsuite',
			__( 'Documents', 'nonprofitsuite' ),
			__( 'Documents', 'nonprofitsuite' ),
			'ns_view_documents',
			'nonprofitsuite-documents',
			array( $this, 'display_documents' )
		);

		// Document Retention Policies (admin only)
		if ( current_user_can( 'manage_options' ) ) {
			add_submenu_page(
				'nonprofitsuite',
				__( 'Retention Policies', 'nonprofitsuite' ),
				__( 'Retention Policies', 'nonprofitsuite' ),
				'manage_options',
				'nonprofitsuite-retention-policies',
				array( $this, 'display_retention_policies' )
			);
		}

		// Calendar Sync (admin only)
		if ( current_user_can( 'manage_options' ) ) {
			add_submenu_page(
				'nonprofitsuite',
				__( 'Calendar Sync', 'nonprofitsuite' ),
				__( 'Calendar Sync', 'nonprofitsuite' ),
				'manage_options',
				'nonprofitsuite-calendar-sync',
				array( $this, 'display_calendar_sync' )
			);
		}

		// People
		add_submenu_page(
			'nonprofitsuite',
			__( 'People', 'nonprofitsuite' ),
			__( 'People', 'nonprofitsuite' ),
			'ns_manage_meetings',
			'nonprofitsuite-people',
			array( $this, 'display_people' )
		);

		// PRO MODULES
		if ( NonprofitSuite_License::is_pro_active() ) {
			// Treasury
			add_submenu_page(
				'nonprofitsuite',
				__( 'Treasury', 'nonprofitsuite' ),
				__( 'Treasury', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'ns_manage_treasury',
				'nonprofitsuite-treasury',
				array( $this, 'display_treasury' )
			);

			// Donors
			add_submenu_page(
				'nonprofitsuite',
				__( 'Donors', 'nonprofitsuite' ),
				__( 'Donors', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'ns_manage_donors',
				'nonprofitsuite-donors',
				array( $this, 'display_donors' )
			);

			// Volunteers
			add_submenu_page(
				'nonprofitsuite',
				__( 'Volunteers', 'nonprofitsuite' ),
				__( 'Volunteers', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'ns_manage_volunteers',
				'nonprofitsuite-volunteers',
				array( $this, 'display_volunteers' )
			);

			// Grants
			add_submenu_page(
				'nonprofitsuite',
				__( 'Grants', 'nonprofitsuite' ),
				__( 'Grants', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'ns_manage_grants',
				'nonprofitsuite-grants',
				array( $this, 'display_grants' )
			);

			// Programs
			add_submenu_page(
				'nonprofitsuite',
				__( 'Programs', 'nonprofitsuite' ),
				__( 'Programs', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'ns_manage_programs',
				'nonprofitsuite-programs',
				array( $this, 'display_programs' )
			);

			// Fundraising/Campaigns
			add_submenu_page(
				'nonprofitsuite',
				__( 'Fundraising', 'nonprofitsuite' ),
				__( 'Fundraising', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'ns_manage_programs',
				'nonprofitsuite-fundraising',
				array( $this, 'display_fundraising' )
			);

			// Membership
			add_submenu_page(
				'nonprofitsuite',
				__( 'Membership', 'nonprofitsuite' ),
				__( 'Membership', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'ns_manage_programs',
				'nonprofitsuite-membership',
				array( $this, 'display_membership' )
			);

			// Events
			add_submenu_page(
				'nonprofitsuite',
				__( 'Events', 'nonprofitsuite' ),
				__( 'Events', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'ns_manage_programs',
				'nonprofitsuite-events',
				array( $this, 'display_events' )
			);

			// Committees
			add_submenu_page(
				'nonprofitsuite',
				__( 'Committees', 'nonprofitsuite' ),
				__( 'Committees', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'ns_manage_meetings',
				'nonprofitsuite-committees',
				array( $this, 'display_committees' )
			);

			// Compliance
			add_submenu_page(
				'nonprofitsuite',
				__( 'Compliance', 'nonprofitsuite' ),
				__( 'Compliance', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'ns_manage_treasury',
				'nonprofitsuite-compliance',
				array( $this, 'display_compliance' )
			);

			// HR
			add_submenu_page(
				'nonprofitsuite',
				__( 'HR', 'nonprofitsuite' ),
				__( 'HR', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'manage_options',
				'nonprofitsuite-hr',
				array( $this, 'display_hr' )
			);

			// Communications
			add_submenu_page(
				'nonprofitsuite',
				__( 'Communications', 'nonprofitsuite' ),
				__( 'Communications', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'ns_manage_programs',
				'nonprofitsuite-communications',
				array( $this, 'display_communications' )
			);

			// Policy Management
			add_submenu_page(
				'nonprofitsuite',
				__( 'Policy Management', 'nonprofitsuite' ),
				__( 'Policy Management', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'manage_options',
				'nonprofitsuite-policy',
				array( $this, 'display_policy' )
			);

			// Board Development
			add_submenu_page(
				'nonprofitsuite',
				__( 'Board Development', 'nonprofitsuite' ),
				__( 'Board Development', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'ns_manage_meetings',
				'nonprofitsuite-board-development',
				array( $this, 'display_board_development' )
			);

			// Formation Assistant
			add_submenu_page(
				'nonprofitsuite',
				__( 'Formation Assistant', 'nonprofitsuite' ),
				__( 'Formation Assistant', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'manage_options',
				'nonprofitsuite-formation',
				array( $this, 'display_formation' )
			);

			// Project Management
			add_submenu_page(
				'nonprofitsuite',
				__( 'Project Management', 'nonprofitsuite' ),
				__( 'Project Management', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'ns_manage_programs',
				'nonprofitsuite-projects',
				array( $this, 'display_projects' )
			);

			// Training
			add_submenu_page(
				'nonprofitsuite',
				__( 'Training', 'nonprofitsuite' ),
				__( 'Training', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'ns_manage_programs',
				'nonprofitsuite-training',
				array( $this, 'display_training' )
			);

			// Secretary Module
			add_submenu_page(
				'nonprofitsuite',
				__( 'Secretary Dashboard', 'nonprofitsuite' ),
				__( 'Secretary', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'manage_options',
				'nonprofitsuite-secretary',
				array( $this, 'display_secretary' )
			);

			// Chair Module
			add_submenu_page(
				'nonprofitsuite',
				__( 'Board Chair Dashboard', 'nonprofitsuite' ),
				__( 'Board Chair', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'manage_options',
				'nonprofitsuite-chair',
				array( $this, 'display_chair' )
			);

			// Prospect Research Module
			add_submenu_page(
				'nonprofitsuite',
				__( 'Prospect Research', 'nonprofitsuite' ),
				__( 'Prospect Research', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'manage_options',
				'nonprofitsuite-prospect-research',
				array( $this, 'display_prospect_research' )
			);

			// In-Kind Donations & Asset Management
			add_submenu_page(
				'nonprofitsuite',
				__( 'In-Kind & Assets', 'nonprofitsuite' ),
				__( 'In-Kind & Assets', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'manage_options',
				'nonprofitsuite-in-kind',
				array( $this, 'display_in_kind' )
			);

			// Advocacy Module
			add_submenu_page(
				'nonprofitsuite',
				__( 'Advocacy', 'nonprofitsuite' ),
				__( 'Advocacy', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'manage_options',
				'nonprofitsuite-advocacy',
				array( $this, 'display_advocacy' )
			);

			// Audit Module
			add_submenu_page(
				'nonprofitsuite',
				__( 'Audit Management', 'nonprofitsuite' ),
				__( 'Audit', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'manage_options',
				'nonprofitsuite-audit',
				array( $this, 'display_audit' )
			);

			// CPA Dashboard
			add_submenu_page(
				'nonprofitsuite',
				__( 'CPA Portal', 'nonprofitsuite' ),
				__( 'CPA Portal', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'manage_options',
				'nonprofitsuite-cpa-dashboard',
				array( $this, 'display_cpa_dashboard' )
			);

			// Legal Counsel Dashboard
			add_submenu_page(
				'nonprofitsuite',
				__( 'Legal Counsel Portal', 'nonprofitsuite' ),
				__( 'Legal Counsel', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'manage_options',
				'nonprofitsuite-legal-counsel',
				array( $this, 'display_legal_counsel' )
			);

			// Program Evaluation
			add_submenu_page(
				'nonprofitsuite',
				__( 'Program Evaluation', 'nonprofitsuite' ),
				__( 'Program Evaluation', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'manage_options',
				'nonprofitsuite-program-evaluation',
				array( $this, 'display_program_evaluation' )
			);

			// Service Delivery
			add_submenu_page(
				'nonprofitsuite',
				__( 'Service Delivery', 'nonprofitsuite' ),
				__( 'Service Delivery', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'manage_options',
				'nonprofitsuite-service-delivery',
				array( $this, 'display_service_delivery' )
			);

			// State Compliance
			add_submenu_page(
				'nonprofitsuite',
				__( 'State Compliance', 'nonprofitsuite' ),
				__( 'State Compliance', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'manage_options',
				'nonprofitsuite-state-compliance',
				array( $this, 'display_state_compliance' )
			);

			// Anonymous Reporting
			add_submenu_page(
				'nonprofitsuite',
				__( 'Anonymous Reporting', 'nonprofitsuite' ),
				__( 'Anonymous Reporting', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'manage_options',
				'nonprofitsuite-anonymous-reporting',
				array( $this, 'display_anonymous_reporting' )
			);

			// Chapters & Affiliates
			add_submenu_page(
				'nonprofitsuite',
				__( 'Chapters & Affiliates', 'nonprofitsuite' ),
				__( 'Chapters', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'manage_options',
				'nonprofitsuite-chapters',
				array( $this, 'display_chapters' )
			);

			// AI Assistant
			add_submenu_page(
				'nonprofitsuite',
				__( 'AI Assistant', 'nonprofitsuite' ),
				__( 'AI Assistant', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'manage_options',
				'nonprofitsuite-ai-assistant',
				array( $this, 'display_ai_assistant' )
			);

			// Support
			add_submenu_page(
				'nonprofitsuite',
				__( 'Support', 'nonprofitsuite' ),
				__( 'Support', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'read',
				'nonprofitsuite-support',
				array( $this, 'display_support' )
			);

			// Calendar
			add_submenu_page(
				'nonprofitsuite',
				__( 'Calendar', 'nonprofitsuite' ),
				__( 'Calendar', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'read',
				'nonprofitsuite-calendar',
				array( $this, 'display_calendar' )
			);

			// Email Campaigns
			add_submenu_page(
				'nonprofitsuite',
				__( 'Email Campaigns', 'nonprofitsuite' ),
				__( 'Email Campaigns', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'manage_options',
				'nonprofitsuite-email-campaigns',
				array( $this, 'display_email_campaigns' )
			);

			// Mobile API
			add_submenu_page(
				'nonprofitsuite',
				__( 'Mobile API', 'nonprofitsuite' ),
				__( 'Mobile API', 'nonprofitsuite' ) . ' <span class="ns-pro-badge">PRO</span>',
				'manage_options',
				'nonprofitsuite-mobile-api',
				array( $this, 'display_mobile_api' )
			);
		}

		// Tools - Import
		add_submenu_page(
			'nonprofitsuite',
			__( 'Import Data', 'nonprofitsuite' ),
			__( 'Import', 'nonprofitsuite' ),
			'manage_options',
			'nonprofitsuite-import',
			array( $this, 'display_import' )
		);

		// Settings
		add_submenu_page(
			'nonprofitsuite',
			__( 'Settings', 'nonprofitsuite' ),
			__( 'Settings', 'nonprofitsuite' ),
			'manage_options',
			'nonprofitsuite-settings',
			array( $this, 'display_settings' )
		);
	}

	/**
	 * Display dashboard page.
	 */
	public function display_dashboard() {
		require_once NONPROFITSUITE_PATH . 'admin/partials/dashboard.php';
	}

	/**
	 * Display meetings page.
	 */
	public function display_meetings() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';

		switch ( $action ) {
			case 'edit':
			case 'add':
				require_once NONPROFITSUITE_PATH . 'admin/partials/meetings/edit.php';
				break;
			case 'agenda':
				require_once NONPROFITSUITE_PATH . 'admin/partials/meetings/agenda.php';
				break;
			case 'minutes':
				require_once NONPROFITSUITE_PATH . 'admin/partials/meetings/minutes.php';
				break;
			default:
				require_once NONPROFITSUITE_PATH . 'admin/partials/meetings/list.php';
		}
	}

	/**
	 * Display tasks page.
	 */
	public function display_tasks() {
		require_once NONPROFITSUITE_PATH . 'admin/partials/tasks.php';
	}

	/**
	 * Display documents page.
	 */
	public function display_documents() {
		require_once NONPROFITSUITE_PATH . 'admin/partials/documents.php';
	}

	/**
	 * Display retention policies page.
	 */
	public function display_retention_policies() {
		require_once NONPROFITSUITE_PATH . 'admin/partials/retention-policies.php';
	}

	/**
	 * Display calendar sync page.
	 */
	public function display_calendar_sync() {
		require_once NONPROFITSUITE_PATH . 'admin/partials/calendar-sync.php';
	}

	/**
	 * Display people page.
	 */
	public function display_people() {
		require_once NONPROFITSUITE_PATH . 'admin/partials/people.php';
	}

	/**
	 * Display import page.
	 */
	public function display_import() {
		require_once NONPROFITSUITE_PATH . 'admin/partials/import.php';
	}

	/**
	 * Display settings page.
	 */
	public function display_settings() {
		require_once NONPROFITSUITE_PATH . 'admin/partials/settings.php';
	}

	/**
	 * Display treasury page (PRO).
	 */
	public function display_treasury() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Treasury Module' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/treasury.php';
	}

	/**
	 * Display donors page (PRO).
	 */
	public function display_donors() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Donor Management' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/donors.php';
	}

	/**
	 * Display volunteers page (PRO).
	 */
	public function display_volunteers() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Volunteer Management' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/volunteers.php';
	}

	/**
	 * Display grants page (PRO).
	 */
	public function display_grants() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Grant Management' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/grants.php';
	}

	/**
	 * Display programs page (PRO).
	 */
	public function display_programs() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Programs Module' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/programs.php';
	}

	/**
	 * Display fundraising page (PRO).
	 */
	public function display_fundraising() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Fundraising Module' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/fundraising.php';
	}

	/**
	 * Display membership page (PRO).
	 */
	public function display_membership() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Membership Module' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/membership.php';
	}

	/**
	 * Display events page (PRO).
	 */
	public function display_events() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Events Module' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/events.php';
	}

	/**
	 * Display committees page (PRO).
	 */
	public function display_committees() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Committee Management' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/committees.php';
	}

	/**
	 * Display compliance page (PRO).
	 */
	public function display_compliance() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Compliance Module' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/compliance.php';
	}

	/**
	 * Display HR page (PRO).
	 */
	public function display_hr() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'HR Module' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/hr.php';
	}

	/**
	 * Display communications page (PRO).
	 */
	public function display_communications() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Communications Module' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/communications.php';
	}

	/**
	 * Display policy management page (PRO).
	 */
	public function display_policy() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Policy Management Module' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/policy.php';
	}

	/**
	 * Display board development page (PRO).
	 */
	public function display_board_development() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Board Development Module' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/board-development.php';
	}

	/**
	 * Display formation assistant page (PRO).
	 */
	public function display_formation() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Formation Assistant Module' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/formation.php';
	}

	/**
	 * Display project management page (PRO).
	 */
	public function display_projects() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Project Management Module' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/projects.php';
	}

	/**
	 * Display training page (PRO).
	 */
	public function display_training() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Training Module' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/training.php';
	}

	/**
	 * Display secretary dashboard page (PRO).
	 */
	public function display_secretary() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Secretary Module' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/secretary.php';
	}

	/**
	 * Display board chair dashboard page (PRO).
	 */
	public function display_chair() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Board Chair Module' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/chair.php';
	}

	/**
	 * Display prospect research page (PRO).
	 */
	public function display_prospect_research() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Prospect Research Module' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/prospect-research.php';
	}

	/**
	 * Display in-kind donations & asset management page (PRO).
	 */
	public function display_in_kind() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'In-Kind & Asset Management Module' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/in-kind.php';
	}

	/**
	 * Display advocacy page (PRO).
	 */
	public function display_advocacy() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Advocacy Module' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/advocacy.php';
	}

	/**
	 * Display audit management page (PRO).
	 */
	public function display_audit() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Audit Module' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/audit.php';
	}

	/**
	 * Display CPA dashboard page (PRO).
	 */
	public function display_cpa_dashboard() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'CPA Dashboard' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/cpa-dashboard.php';
	}

	/**
	 * Display legal counsel page (PRO).
	 */
	public function display_legal_counsel() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Legal Counsel Portal' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/legal-counsel.php';
	}

	/**
	 * Display program evaluation page (PRO).
	 */
	public function display_program_evaluation() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Program Evaluation' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/program-evaluation.php';
	}

	/**
	 * Display service delivery page (PRO).
	 */
	public function display_service_delivery() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Service Delivery' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/service-delivery.php';
	}

	/**
	 * Display state compliance page (PRO).
	 */
	public function display_state_compliance() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'State Compliance' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/state-compliance.php';
	}

	/**
	 * Display anonymous reporting page (PRO).
	 */
	public function display_anonymous_reporting() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Anonymous Reporting' );
			return;
		}
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/anonymous-reporting.php';
	}

	/**
	 * Display chapters page (PRO).
	 */
	public function display_chapters() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Chapters & Affiliates' );
			return;
		}
		$dashboard_data = NonprofitSuite_Chapters::get_dashboard_data();
		$chapters = NonprofitSuite_Chapters::get_chapters();
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/chapters.php';
	}

	/**
	 * Display AI assistant page (PRO).
	 */
	public function display_ai_assistant() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'AI Assistant' );
			return;
		}
		$is_configured = NonprofitSuite_AI_Assistant::is_api_configured();
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/ai-assistant.php';
	}

	/**
	 * Display support page (PRO).
	 */
	public function display_support() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Support System' );
			return;
		}
		$dashboard_data = NonprofitSuite_Support::get_dashboard_data();
		$tickets = NonprofitSuite_Support::get_tickets( array( 'user_id' => get_current_user_id() ) );
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/support.php';
	}

	/**
	 * Display calendar page (PRO).
	 */
	public function display_calendar() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Calendar Integration' );
			return;
		}
		$start_date = date( 'Y-m-01' );
		$end_date = date( 'Y-m-t' );
		$items = NonprofitSuite_Calendar::get_items( $start_date, $end_date );
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/calendar.php';
	}

	/**
	 * Display email campaigns page (PRO).
	 */
	public function display_email_campaigns() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Email Campaigns' );
			return;
		}
		$campaigns = NonprofitSuite_Email_Campaigns::get_campaigns();
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/email-campaigns.php';
	}

	/**
	 * Display mobile API page (PRO).
	 */
	public function display_mobile_api() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			echo NonprofitSuite_License::get_upgrade_notice( 'Mobile API' );
			return;
		}
		$tokens = NonprofitSuite_Mobile_API::get_user_tokens( get_current_user_id() );
		require_once NONPROFITSUITE_PATH . 'admin/partials/pro/mobile-api.php';
	}
}
