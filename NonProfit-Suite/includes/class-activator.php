<?php
/**
 * Fired during plugin activation
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 */
class NonprofitSuite_Activator {

	/**
	 * Activate the plugin.
	 *
	 * Creates database tables and sets up initial configuration.
	 */
	public static function activate() {
		self::create_tables();
		self::create_roles();
		self::register_custom_capabilities();
		self::set_default_options();

		// Run database migrations
		require_once NONPROFITSUITE_PATH . 'includes/class-migrator.php';
		NonprofitSuite_Migrator::check_and_run();

		// Schedule document retention cron job (daily at 2 AM)
		if ( ! wp_next_scheduled( 'nonprofitsuite_process_document_retention' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 2:00 AM' ), 'daily', 'nonprofitsuite_process_document_retention' );
		}

		// Schedule calendar sync cron job (hourly by default)
		if ( ! wp_next_scheduled( 'nonprofitsuite_calendar_sync' ) ) {
			$sync_frequency = get_option( 'ns_calendar_sync_frequency', 'hourly' );
			wp_schedule_event( time(), $sync_frequency, 'nonprofitsuite_calendar_sync' );
		}

		// Schedule calendar reminder processing (every 5 minutes)
		if ( ! wp_next_scheduled( 'nonprofitsuite_process_calendar_reminders' ) ) {
			wp_schedule_event( time(), 'ns_every_5_minutes', 'nonprofitsuite_process_calendar_reminders' );
		}

		// Set a flag to trigger setup wizard
		set_transient( 'nonprofitsuite_activation_redirect', true, 30 );
	}

	/**
	 * Create all database tables.
	 */
	private static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		// Core Entities Tables

		// People table
		$sql = "CREATE TABLE {$wpdb->prefix}ns_people (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned DEFAULT NULL,
			first_name varchar(100) NOT NULL,
			last_name varchar(100) NOT NULL,
			email varchar(100) DEFAULT NULL,
			phone varchar(50) DEFAULT NULL,
			address text DEFAULT NULL,
			city varchar(100) DEFAULT NULL,
			state varchar(50) DEFAULT NULL,
			zip varchar(20) DEFAULT NULL,
			notes text DEFAULT NULL,
			status varchar(20) DEFAULT 'active',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY status (status),
			KEY email (email)
		) $charset_collate;";
		dbDelta( $sql );

		// Organizations table
		$sql = "CREATE TABLE {$wpdb->prefix}ns_organizations (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			type varchar(100) DEFAULT NULL,
			email varchar(100) DEFAULT NULL,
			phone varchar(50) DEFAULT NULL,
			address text DEFAULT NULL,
			city varchar(100) DEFAULT NULL,
			state varchar(50) DEFAULT NULL,
			zip varchar(20) DEFAULT NULL,
			website varchar(255) DEFAULT NULL,
			notes text DEFAULT NULL,
			status varchar(20) DEFAULT 'active',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY type (type)
		) $charset_collate;";
		dbDelta( $sql );

		// Person-Organization relationships
		$sql = "CREATE TABLE {$wpdb->prefix}ns_person_org_relations (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			person_id bigint(20) unsigned NOT NULL,
			organization_id bigint(20) unsigned NOT NULL,
			relationship_type varchar(100) DEFAULT NULL,
			title varchar(100) DEFAULT NULL,
			start_date date DEFAULT NULL,
			end_date date DEFAULT NULL,
			is_current tinyint(1) DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY person_id (person_id),
			KEY organization_id (organization_id),
			KEY is_current (is_current)
		) $charset_collate;";
		dbDelta( $sql );

		// Meetings Module Tables

		// Meetings table
		$sql = "CREATE TABLE {$wpdb->prefix}ns_meetings (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			meeting_type varchar(50) DEFAULT 'board',
			meeting_date datetime NOT NULL,
			location varchar(255) DEFAULT NULL,
			virtual_url varchar(500) DEFAULT NULL,
			description text DEFAULT NULL,
			status varchar(20) DEFAULT 'scheduled',
			quorum_required int DEFAULT NULL,
			quorum_met tinyint(1) DEFAULT NULL,
			created_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY meeting_date (meeting_date),
			KEY meeting_type (meeting_type),
			KEY status (status),
			KEY created_by (created_by)
		) $charset_collate;";
		dbDelta( $sql );

		// Agenda items table
		$sql = "CREATE TABLE {$wpdb->prefix}ns_agenda_items (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			meeting_id bigint(20) unsigned NOT NULL,
			item_type varchar(50) DEFAULT 'discussion',
			title varchar(255) NOT NULL,
			description text DEFAULT NULL,
			presenter_id bigint(20) unsigned DEFAULT NULL,
			time_allocated int DEFAULT NULL,
			sort_order int DEFAULT 0,
			document_ids text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY meeting_id (meeting_id),
			KEY item_type (item_type),
			KEY sort_order (sort_order),
			KEY presenter_id (presenter_id)
		) $charset_collate;";
		dbDelta( $sql );

		// Minutes table
		$sql = "CREATE TABLE {$wpdb->prefix}ns_minutes (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			meeting_id bigint(20) unsigned NOT NULL,
			content longtext DEFAULT NULL,
			status varchar(20) DEFAULT 'draft',
			approved_by bigint(20) unsigned DEFAULT NULL,
			approved_at datetime DEFAULT NULL,
			version int DEFAULT 1,
			created_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY meeting_id (meeting_id),
			KEY status (status),
			KEY approved_by (approved_by),
			KEY created_by (created_by)
		) $charset_collate;";
		dbDelta( $sql );

		// Attendance table
		$sql = "CREATE TABLE {$wpdb->prefix}ns_attendance (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			meeting_id bigint(20) unsigned NOT NULL,
			person_id bigint(20) unsigned NOT NULL,
			status varchar(20) DEFAULT 'present',
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY meeting_person (meeting_id, person_id),
			KEY meeting_id (meeting_id),
			KEY person_id (person_id),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql );

		// Votes table
		$sql = "CREATE TABLE {$wpdb->prefix}ns_votes (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			meeting_id bigint(20) unsigned NOT NULL,
			agenda_item_id bigint(20) unsigned DEFAULT NULL,
			motion_text text NOT NULL,
			moved_by bigint(20) unsigned DEFAULT NULL,
			seconded_by bigint(20) unsigned DEFAULT NULL,
			result varchar(20) DEFAULT NULL,
			vote_count_for int DEFAULT 0,
			vote_count_against int DEFAULT 0,
			vote_count_abstain int DEFAULT 0,
			vote_data longtext DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY meeting_id (meeting_id),
			KEY agenda_item_id (agenda_item_id),
			KEY moved_by (moved_by),
			KEY seconded_by (seconded_by)
		) $charset_collate;";
		dbDelta( $sql );

		// Tasks table
		$sql = "CREATE TABLE {$wpdb->prefix}ns_tasks (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			description text DEFAULT NULL,
			assigned_to bigint(20) unsigned DEFAULT NULL,
			created_by bigint(20) unsigned DEFAULT NULL,
			due_date date DEFAULT NULL,
			priority varchar(20) DEFAULT 'medium',
			status varchar(20) DEFAULT 'not_started',
			source_type varchar(50) DEFAULT NULL,
			source_id bigint(20) unsigned DEFAULT NULL,
			estimated_hours decimal(10,2) DEFAULT NULL,
			actual_hours decimal(10,2) DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY assigned_to (assigned_to),
			KEY created_by (created_by),
			KEY due_date (due_date),
			KEY priority (priority),
			KEY status (status),
			KEY source_type_id (source_type, source_id)
		) $charset_collate;";
		dbDelta( $sql );

		// Task comments table
		$sql = "CREATE TABLE {$wpdb->prefix}ns_task_comments (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			task_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			comment text NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY task_id (task_id),
			KEY user_id (user_id)
		) $charset_collate;";
		dbDelta( $sql );

		// Documents table
		$sql = "CREATE TABLE {$wpdb->prefix}ns_documents (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			attachment_id bigint(20) unsigned NOT NULL,
			title varchar(255) NOT NULL,
			description text DEFAULT NULL,
			category varchar(100) DEFAULT NULL,
			tags text DEFAULT NULL,
			linked_to_type varchar(50) DEFAULT NULL,
			linked_to_id bigint(20) unsigned DEFAULT NULL,
			version varchar(20) DEFAULT '1.0',
			access_level varchar(50) DEFAULT 'all',
			uploaded_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY attachment_id (attachment_id),
			KEY category (category),
			KEY linked_to (linked_to_type, linked_to_id),
			KEY access_level (access_level),
			KEY uploaded_by (uploaded_by)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO MODULE TABLES

		// Treasury: Chart of Accounts
		$sql = "CREATE TABLE {$wpdb->prefix}ns_accounts (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			account_number varchar(50) NOT NULL,
			account_name varchar(255) NOT NULL,
			account_type varchar(50) NOT NULL,
			parent_account_id bigint(20) unsigned DEFAULT NULL,
			balance decimal(15,2) DEFAULT 0.00,
			description text DEFAULT NULL,
			is_active tinyint(1) DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY account_number (account_number),
			KEY account_type (account_type),
			KEY parent_account_id (parent_account_id),
			KEY is_active (is_active)
		) $charset_collate;";
		dbDelta( $sql );

		// Treasury: Transactions
		$sql = "CREATE TABLE {$wpdb->prefix}ns_transactions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			transaction_date date NOT NULL,
			transaction_type varchar(50) NOT NULL,
			account_id bigint(20) unsigned NOT NULL,
			amount decimal(15,2) NOT NULL,
			description text DEFAULT NULL,
			reference_number varchar(100) DEFAULT NULL,
			payee varchar(255) DEFAULT NULL,
			category varchar(100) DEFAULT NULL,
			fund_id bigint(20) unsigned DEFAULT NULL,
			attachment_id bigint(20) unsigned DEFAULT NULL,
			reconciled tinyint(1) DEFAULT 0,
			created_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY transaction_date (transaction_date),
			KEY account_id (account_id),
			KEY transaction_type (transaction_type),
			KEY fund_id (fund_id),
			KEY reconciled (reconciled)
		) $charset_collate;";
		dbDelta( $sql );

		// Treasury: Budgets
		$sql = "CREATE TABLE {$wpdb->prefix}ns_budgets (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			fiscal_year int NOT NULL,
			account_id bigint(20) unsigned NOT NULL,
			budgeted_amount decimal(15,2) NOT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY fiscal_year_account (fiscal_year, account_id),
			KEY account_id (account_id)
		) $charset_collate;";
		dbDelta( $sql );

		// Treasury: Funds
		$sql = "CREATE TABLE {$wpdb->prefix}ns_funds (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			fund_name varchar(255) NOT NULL,
			fund_type varchar(50) DEFAULT 'unrestricted',
			balance decimal(15,2) DEFAULT 0.00,
			description text DEFAULT NULL,
			is_active tinyint(1) DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY fund_type (fund_type),
			KEY is_active (is_active)
		) $charset_collate;";
		dbDelta( $sql );

		// Donor Management: Donors
		$sql = "CREATE TABLE {$wpdb->prefix}ns_donors (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			person_id bigint(20) unsigned DEFAULT NULL,
			organization_id bigint(20) unsigned DEFAULT NULL,
			donor_type varchar(50) DEFAULT 'individual',
			donor_status varchar(50) DEFAULT 'active',
			donor_level varchar(100) DEFAULT NULL,
			total_donated decimal(15,2) DEFAULT 0.00,
			first_donation_date date DEFAULT NULL,
			last_donation_date date DEFAULT NULL,
			communication_preferences text DEFAULT NULL,
			acknowledgment_preferences varchar(100) DEFAULT 'email',
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY person_id (person_id),
			KEY organization_id (organization_id),
			KEY donor_status (donor_status),
			KEY donor_level (donor_level)
		) $charset_collate;";
		dbDelta( $sql );

		// Donor Management: Donations
		$sql = "CREATE TABLE {$wpdb->prefix}ns_donations (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			donor_id bigint(20) unsigned NOT NULL,
			donation_date date NOT NULL,
			amount decimal(15,2) NOT NULL,
			payment_method varchar(50) DEFAULT NULL,
			transaction_id varchar(255) DEFAULT NULL,
			campaign_id bigint(20) unsigned DEFAULT NULL,
			fund_id bigint(20) unsigned DEFAULT NULL,
			is_recurring tinyint(1) DEFAULT 0,
			recurring_frequency varchar(50) DEFAULT NULL,
			tax_deductible tinyint(1) DEFAULT 1,
			acknowledgment_sent tinyint(1) DEFAULT 0,
			acknowledgment_date date DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY donor_id (donor_id),
			KEY donation_date (donation_date),
			KEY campaign_id (campaign_id),
			KEY fund_id (fund_id),
			KEY is_recurring (is_recurring)
		) $charset_collate;";
		dbDelta( $sql );

		// Donor Management: Pledges
		$sql = "CREATE TABLE {$wpdb->prefix}ns_pledges (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			donor_id bigint(20) unsigned NOT NULL,
			pledge_amount decimal(15,2) NOT NULL,
			paid_amount decimal(15,2) DEFAULT 0.00,
			pledge_date date NOT NULL,
			due_date date DEFAULT NULL,
			status varchar(50) DEFAULT 'pending',
			campaign_id bigint(20) unsigned DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY donor_id (donor_id),
			KEY status (status),
			KEY campaign_id (campaign_id)
		) $charset_collate;";
		dbDelta( $sql );

		// Volunteer Management: Volunteers
		$sql = "CREATE TABLE {$wpdb->prefix}ns_volunteers (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			person_id bigint(20) unsigned NOT NULL,
			application_date date DEFAULT NULL,
			application_status varchar(50) DEFAULT 'pending',
			volunteer_status varchar(50) DEFAULT 'active',
			skills text DEFAULT NULL,
			interests text DEFAULT NULL,
			availability text DEFAULT NULL,
			background_check_status varchar(50) DEFAULT NULL,
			background_check_date date DEFAULT NULL,
			orientation_completed tinyint(1) DEFAULT 0,
			orientation_date date DEFAULT NULL,
			total_hours decimal(10,2) DEFAULT 0.00,
			emergency_contact_name varchar(255) DEFAULT NULL,
			emergency_contact_phone varchar(50) DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY person_id (person_id),
			KEY volunteer_status (volunteer_status),
			KEY application_status (application_status)
		) $charset_collate;";
		dbDelta( $sql );

		// Volunteer Management: Volunteer Hours
		$sql = "CREATE TABLE {$wpdb->prefix}ns_volunteer_hours (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			volunteer_id bigint(20) unsigned NOT NULL,
			activity_date date NOT NULL,
			hours decimal(10,2) NOT NULL,
			program_id bigint(20) unsigned DEFAULT NULL,
			description text DEFAULT NULL,
			approved tinyint(1) DEFAULT 0,
			approved_by bigint(20) unsigned DEFAULT NULL,
			approved_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY volunteer_id (volunteer_id),
			KEY activity_date (activity_date),
			KEY program_id (program_id),
			KEY approved (approved)
		) $charset_collate;";
		dbDelta( $sql );

		// Grant Management: Grants
		$sql = "CREATE TABLE {$wpdb->prefix}ns_grants (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			grant_name varchar(255) NOT NULL,
			funder_organization_id bigint(20) unsigned DEFAULT NULL,
			grant_amount decimal(15,2) NOT NULL,
			status varchar(50) DEFAULT 'prospect',
			application_deadline date DEFAULT NULL,
			application_date date DEFAULT NULL,
			award_date date DEFAULT NULL,
			start_date date DEFAULT NULL,
			end_date date DEFAULT NULL,
			fund_id bigint(20) unsigned DEFAULT NULL,
			program_id bigint(20) unsigned DEFAULT NULL,
			grant_purpose text DEFAULT NULL,
			restrictions text DEFAULT NULL,
			reporting_requirements text DEFAULT NULL,
			amount_spent decimal(15,2) DEFAULT 0.00,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY funder_organization_id (funder_organization_id),
			KEY status (status),
			KEY fund_id (fund_id),
			KEY program_id (program_id)
		) $charset_collate;";
		dbDelta( $sql );

		// Grant Management: Grant Reports
		$sql = "CREATE TABLE {$wpdb->prefix}ns_grant_reports (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			grant_id bigint(20) unsigned NOT NULL,
			report_type varchar(50) DEFAULT 'progress',
			due_date date NOT NULL,
			submitted_date date DEFAULT NULL,
			status varchar(50) DEFAULT 'pending',
			report_data longtext DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY grant_id (grant_id),
			KEY due_date (due_date),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql );

		// Programs/Operations: Programs
		$sql = "CREATE TABLE {$wpdb->prefix}ns_programs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			program_name varchar(255) NOT NULL,
			program_type varchar(100) DEFAULT NULL,
			description text DEFAULT NULL,
			goals text DEFAULT NULL,
			budget decimal(15,2) DEFAULT 0.00,
			start_date date DEFAULT NULL,
			end_date date DEFAULT NULL,
			status varchar(50) DEFAULT 'active',
			manager_id bigint(20) unsigned DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY program_type (program_type),
			KEY status (status),
			KEY manager_id (manager_id)
		) $charset_collate;";
		dbDelta( $sql );

		// Programs/Operations: Activities
		$sql = "CREATE TABLE {$wpdb->prefix}ns_activities (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			program_id bigint(20) unsigned NOT NULL,
			activity_name varchar(255) NOT NULL,
			activity_date datetime NOT NULL,
			location varchar(255) DEFAULT NULL,
			description text DEFAULT NULL,
			capacity int DEFAULT NULL,
			registered_count int DEFAULT 0,
			status varchar(50) DEFAULT 'scheduled',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY program_id (program_id),
			KEY activity_date (activity_date),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql );

		// Programs/Operations: Program Participants
		$sql = "CREATE TABLE {$wpdb->prefix}ns_program_participants (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			program_id bigint(20) unsigned NOT NULL,
			person_id bigint(20) unsigned NOT NULL,
			enrollment_date date NOT NULL,
			status varchar(50) DEFAULT 'active',
			completion_date date DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY program_person (program_id, person_id),
			KEY program_id (program_id),
			KEY person_id (person_id),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql );

		// PHASE 3 PRO MODULE TABLES

		// Fundraising: Campaigns
		$sql = "CREATE TABLE {$wpdb->prefix}ns_campaigns (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_name varchar(255) NOT NULL,
			campaign_type varchar(50) DEFAULT 'general',
			goal_amount decimal(15,2) DEFAULT 0.00,
			amount_raised decimal(15,2) DEFAULT 0.00,
			start_date date DEFAULT NULL,
			end_date date DEFAULT NULL,
			description text DEFAULT NULL,
			status varchar(50) DEFAULT 'active',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY campaign_type (campaign_type),
			KEY status (status),
			KEY start_date (start_date)
		) $charset_collate;";
		dbDelta( $sql );

		// Membership: Members
		$sql = "CREATE TABLE {$wpdb->prefix}ns_members (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			person_id bigint(20) unsigned NOT NULL,
			membership_type varchar(100) DEFAULT NULL,
			membership_level varchar(100) DEFAULT NULL,
			join_date date NOT NULL,
			renewal_date date DEFAULT NULL,
			expiration_date date DEFAULT NULL,
			status varchar(50) DEFAULT 'active',
			dues_amount decimal(10,2) DEFAULT 0.00,
			payment_frequency varchar(50) DEFAULT 'annual',
			auto_renew tinyint(1) DEFAULT 0,
			benefits text DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY person_id (person_id),
			KEY membership_type (membership_type),
			KEY status (status),
			KEY expiration_date (expiration_date)
		) $charset_collate;";
		dbDelta( $sql );

		// Events: Events
		$sql = "CREATE TABLE {$wpdb->prefix}ns_events (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_name varchar(255) NOT NULL,
			event_type varchar(50) DEFAULT 'fundraiser',
			event_date datetime NOT NULL,
			location varchar(255) DEFAULT NULL,
			virtual_url varchar(500) DEFAULT NULL,
			description text DEFAULT NULL,
			capacity int DEFAULT NULL,
			registered_count int DEFAULT 0,
			ticket_price decimal(10,2) DEFAULT 0.00,
			revenue_goal decimal(15,2) DEFAULT 0.00,
			total_revenue decimal(15,2) DEFAULT 0.00,
			total_expenses decimal(15,2) DEFAULT 0.00,
			status varchar(50) DEFAULT 'planned',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY event_type (event_type),
			KEY event_date (event_date),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql );

		// Events: Registrations
		$sql = "CREATE TABLE {$wpdb->prefix}ns_event_registrations (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id bigint(20) unsigned NOT NULL,
			person_id bigint(20) unsigned NOT NULL,
			registration_date datetime DEFAULT CURRENT_TIMESTAMP,
			ticket_count int DEFAULT 1,
			amount_paid decimal(10,2) DEFAULT 0.00,
			payment_status varchar(50) DEFAULT 'pending',
			checked_in tinyint(1) DEFAULT 0,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY event_id (event_id),
			KEY person_id (person_id),
			KEY payment_status (payment_status)
		) $charset_collate;";
		dbDelta( $sql );

		// Committee Management: Committees
		$sql = "CREATE TABLE {$wpdb->prefix}ns_committees (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			committee_name varchar(255) NOT NULL,
			committee_type varchar(100) DEFAULT 'standing',
			description text DEFAULT NULL,
			chair_person_id bigint(20) unsigned DEFAULT NULL,
			meeting_schedule varchar(255) DEFAULT NULL,
			status varchar(50) DEFAULT 'active',
			formed_date date DEFAULT NULL,
			dissolved_date date DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY committee_type (committee_type),
			KEY status (status),
			KEY chair_person_id (chair_person_id)
		) $charset_collate;";
		dbDelta( $sql );

		// Committee Management: Committee Members
		$sql = "CREATE TABLE {$wpdb->prefix}ns_committee_members (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			committee_id bigint(20) unsigned NOT NULL,
			person_id bigint(20) unsigned NOT NULL,
			role varchar(100) DEFAULT 'member',
			start_date date DEFAULT NULL,
			end_date date DEFAULT NULL,
			status varchar(50) DEFAULT 'active',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY committee_person (committee_id, person_id),
			KEY committee_id (committee_id),
			KEY person_id (person_id),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql );

		// Compliance: Compliance Items
		$sql = "CREATE TABLE {$wpdb->prefix}ns_compliance_items (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			item_name varchar(255) NOT NULL,
			compliance_type varchar(50) NOT NULL,
			description text DEFAULT NULL,
			due_date date NOT NULL,
			completed_date date DEFAULT NULL,
			status varchar(50) DEFAULT 'pending',
			responsible_person_id bigint(20) unsigned DEFAULT NULL,
			is_recurring tinyint(1) DEFAULT 0,
			recurrence_frequency varchar(50) DEFAULT NULL,
			document_id bigint(20) unsigned DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY compliance_type (compliance_type),
			KEY due_date (due_date),
			KEY status (status),
			KEY responsible_person_id (responsible_person_id)
		) $charset_collate;";
		dbDelta( $sql );

		// HR: Employees
		$sql = "CREATE TABLE {$wpdb->prefix}ns_employees (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			person_id bigint(20) unsigned NOT NULL,
			employee_number varchar(50) DEFAULT NULL,
			position_title varchar(255) DEFAULT NULL,
			department varchar(100) DEFAULT NULL,
			hire_date date NOT NULL,
			termination_date date DEFAULT NULL,
			employment_type varchar(50) DEFAULT 'full-time',
			salary decimal(15,2) DEFAULT 0.00,
			pay_frequency varchar(50) DEFAULT 'monthly',
			supervisor_id bigint(20) unsigned DEFAULT NULL,
			status varchar(50) DEFAULT 'active',
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY person_id (person_id),
			KEY employee_number (employee_number),
			KEY status (status),
			KEY supervisor_id (supervisor_id)
		) $charset_collate;";
		dbDelta( $sql );

		// HR: Time Off Requests
		$sql = "CREATE TABLE {$wpdb->prefix}ns_time_off (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			employee_id bigint(20) unsigned NOT NULL,
			request_type varchar(50) DEFAULT 'vacation',
			start_date date NOT NULL,
			end_date date NOT NULL,
			total_days decimal(5,2) NOT NULL,
			status varchar(50) DEFAULT 'pending',
			approved_by bigint(20) unsigned DEFAULT NULL,
			approved_at datetime DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY employee_id (employee_id),
			KEY request_type (request_type),
			KEY status (status),
			KEY start_date (start_date)
		) $charset_collate;";
		dbDelta( $sql );

		// Communications: Email Campaigns
		$sql = "CREATE TABLE {$wpdb->prefix}ns_email_campaigns (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_name varchar(255) NOT NULL,
			subject varchar(500) NOT NULL,
			content longtext NOT NULL,
			recipient_type varchar(50) DEFAULT 'all',
			recipient_list longtext DEFAULT NULL,
			scheduled_date datetime DEFAULT NULL,
			sent_date datetime DEFAULT NULL,
			status varchar(50) DEFAULT 'draft',
			total_recipients int DEFAULT 0,
			total_sent int DEFAULT 0,
			total_opened int DEFAULT 0,
			total_clicked int DEFAULT 0,
			created_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY scheduled_date (scheduled_date),
			KEY sent_date (sent_date)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 4: Policy Management
		$sql = "CREATE TABLE {$wpdb->prefix}ns_policies (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			policy_number varchar(50) DEFAULT NULL,
			category varchar(100) NOT NULL,
			description text DEFAULT NULL,
			content longtext NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'draft',
			version varchar(20) DEFAULT '1.0',
			effective_date date DEFAULT NULL,
			review_frequency varchar(50) DEFAULT NULL,
			next_review_date date DEFAULT NULL,
			responsible_person_id bigint(20) unsigned DEFAULT NULL,
			approved_by bigint(20) unsigned DEFAULT NULL,
			approved_date datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY category (category),
			KEY status (status),
			KEY next_review_date (next_review_date)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 4: Board Development - Prospects
		$sql = "CREATE TABLE {$wpdb->prefix}ns_board_prospects (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			person_id bigint(20) unsigned NOT NULL,
			prospect_status varchar(50) NOT NULL DEFAULT 'identified',
			source varchar(100) DEFAULT NULL,
			skills_expertise text DEFAULT NULL,
			networks_connections text DEFAULT NULL,
			giving_capacity varchar(50) DEFAULT NULL,
			cultivation_stage varchar(50) DEFAULT NULL,
			contact_log longtext DEFAULT NULL,
			readiness_score int(3) DEFAULT NULL,
			assigned_to bigint(20) unsigned DEFAULT NULL,
			target_join_date date DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY person_id (person_id),
			KEY prospect_status (prospect_status)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 4: Board Development - Terms
		$sql = "CREATE TABLE {$wpdb->prefix}ns_board_terms (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			person_id bigint(20) unsigned NOT NULL,
			position varchar(100) NOT NULL,
			term_start date NOT NULL,
			term_end date NOT NULL,
			term_number int(3) DEFAULT 1,
			status varchar(50) NOT NULL DEFAULT 'active',
			committee_assignments text DEFAULT NULL,
			attendance_record text DEFAULT NULL,
			giving_history text DEFAULT NULL,
			evaluation_scores text DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY person_id (person_id),
			KEY status (status),
			KEY term_end (term_end)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 4: Formation Assistant
		$sql = "CREATE TABLE {$wpdb->prefix}ns_formation_progress (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			organization_id bigint(20) unsigned DEFAULT NULL,
			current_phase varchar(50) NOT NULL DEFAULT 'planning',
			steps_completed longtext DEFAULT NULL,
			incorporation_state varchar(50) DEFAULT NULL,
			incorporation_date date DEFAULT NULL,
			ein varchar(20) DEFAULT NULL,
			irs_determination_date date DEFAULT NULL,
			state_charity_registration varchar(50) DEFAULT NULL,
			state_registration_date date DEFAULT NULL,
			bylaws_adopted_date date DEFAULT NULL,
			board_established_date date DEFAULT NULL,
			bank_account_opened_date date DEFAULT NULL,
			insurance_obtained_date date DEFAULT NULL,
			website_launched_date date DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 4: Project Management - Projects
		$sql = "CREATE TABLE {$wpdb->prefix}ns_projects (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'planning',
			priority varchar(50) DEFAULT 'medium',
			start_date date DEFAULT NULL,
			target_completion date DEFAULT NULL,
			actual_completion date DEFAULT NULL,
			budget decimal(10,2) DEFAULT NULL,
			spent decimal(10,2) DEFAULT 0,
			project_manager bigint(20) unsigned DEFAULT NULL,
			program_id bigint(20) unsigned DEFAULT NULL,
			grant_id bigint(20) unsigned DEFAULT NULL,
			progress_percentage int(3) DEFAULT 0,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY project_manager (project_manager)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 4: Project Management - Milestones
		$sql = "CREATE TABLE {$wpdb->prefix}ns_project_milestones (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			project_id bigint(20) unsigned NOT NULL,
			title varchar(255) NOT NULL,
			description text DEFAULT NULL,
			target_date date NOT NULL,
			completed_date date DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'pending',
			assigned_to bigint(20) unsigned DEFAULT NULL,
			order_num int(3) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY project_id (project_id),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 4: Training - Courses
		$sql = "CREATE TABLE {$wpdb->prefix}ns_training_courses (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			description text DEFAULT NULL,
			category varchar(100) DEFAULT NULL,
			duration_minutes int(5) DEFAULT NULL,
			required_for text DEFAULT NULL,
			frequency varchar(50) DEFAULT NULL,
			content_type varchar(50) DEFAULT 'internal',
			external_url text DEFAULT NULL,
			passing_score int(3) DEFAULT NULL,
			certificate_enabled tinyint(1) DEFAULT 0,
			status varchar(50) NOT NULL DEFAULT 'active',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY category (category),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 4: Training - Completions
		$sql = "CREATE TABLE {$wpdb->prefix}ns_training_completions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			course_id bigint(20) unsigned NOT NULL,
			person_id bigint(20) unsigned NOT NULL,
			completion_date datetime NOT NULL,
			score int(3) DEFAULT NULL,
			passed tinyint(1) DEFAULT 1,
			certificate_issued tinyint(1) DEFAULT 0,
			next_due_date date DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY course_id (course_id),
			KEY person_id (person_id),
			KEY next_due_date (next_due_date)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 5: Secretary Module
		$sql = "CREATE TABLE {$wpdb->prefix}ns_secretary_tasks (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			task_type varchar(50) NOT NULL,
			related_id bigint(20) unsigned DEFAULT NULL,
			related_type varchar(50) DEFAULT NULL,
			title varchar(255) NOT NULL,
			description text DEFAULT NULL,
			due_date date DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'pending',
			priority varchar(50) DEFAULT 'medium',
			completed_date datetime DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY task_type (task_type),
			KEY status (status),
			KEY due_date (due_date)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 5: Chair Module
		$sql = "CREATE TABLE {$wpdb->prefix}ns_chair_notes (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			note_type varchar(50) NOT NULL,
			related_id bigint(20) unsigned DEFAULT NULL,
			related_type varchar(50) DEFAULT NULL,
			title varchar(255) DEFAULT NULL,
			content text NOT NULL,
			is_private tinyint(1) DEFAULT 1,
			meeting_id bigint(20) unsigned DEFAULT NULL,
			person_id bigint(20) unsigned DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY note_type (note_type),
			KEY meeting_id (meeting_id)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 5: Prospect Research - Prospects
		$sql = "CREATE TABLE {$wpdb->prefix}ns_prospects (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			person_id bigint(20) unsigned NOT NULL,
			organization_id bigint(20) unsigned DEFAULT NULL,
			prospect_type varchar(50) NOT NULL DEFAULT 'individual',
			stage varchar(50) NOT NULL DEFAULT 'identification',
			rating varchar(10) DEFAULT NULL,
			estimated_capacity decimal(12,2) DEFAULT NULL,
			likelihood varchar(50) DEFAULT NULL,
			ask_amount decimal(10,2) DEFAULT NULL,
			target_ask_date date DEFAULT NULL,
			assigned_to bigint(20) unsigned DEFAULT NULL,
			source varchar(255) DEFAULT NULL,
			interests text DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY person_id (person_id),
			KEY stage (stage),
			KEY rating (rating),
			KEY assigned_to (assigned_to)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 5: Prospect Research - Wealth Indicators
		$sql = "CREATE TABLE {$wpdb->prefix}ns_wealth_indicators (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			prospect_id bigint(20) unsigned NOT NULL,
			indicator_type varchar(50) NOT NULL,
			indicator_value text DEFAULT NULL,
			verified tinyint(1) DEFAULT 0,
			source varchar(255) DEFAULT NULL,
			date_found date DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY prospect_id (prospect_id),
			KEY indicator_type (indicator_type),
			KEY idx_prospect_date (prospect_id, date_found)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 5: Prospect Research - Interactions
		$sql = "CREATE TABLE {$wpdb->prefix}ns_prospect_interactions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			prospect_id bigint(20) unsigned NOT NULL,
			interaction_type varchar(50) NOT NULL,
			interaction_date date NOT NULL,
			staff_person bigint(20) unsigned DEFAULT NULL,
			purpose text DEFAULT NULL,
			outcome text DEFAULT NULL,
			next_steps text DEFAULT NULL,
			rating_change varchar(10) DEFAULT NULL,
			stage_change varchar(50) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY prospect_id (prospect_id),
			KEY interaction_date (interaction_date)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 5: In-Kind Donations
		$sql = "CREATE TABLE {$wpdb->prefix}ns_in_kind_donations (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			donor_id bigint(20) unsigned DEFAULT NULL,
			donation_date date NOT NULL,
			category varchar(100) NOT NULL,
			description text NOT NULL,
			quantity decimal(10,2) DEFAULT 1,
			unit varchar(50) DEFAULT NULL,
			fair_market_value decimal(10,2) NOT NULL,
			valuation_method varchar(100) DEFAULT NULL,
			appraised tinyint(1) DEFAULT 0,
			appraiser_name varchar(255) DEFAULT NULL,
			appraisal_date date DEFAULT NULL,
			condition_rating varchar(50) DEFAULT NULL,
			location varchar(255) DEFAULT NULL,
			restricted tinyint(1) DEFAULT 0,
			restriction_terms text DEFAULT NULL,
			intended_use text DEFAULT NULL,
			tax_receipt_issued tinyint(1) DEFAULT 0,
			receipt_number varchar(50) DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY donor_id (donor_id),
			KEY donation_date (donation_date),
			KEY category (category)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 5: Asset Management
		$sql = "CREATE TABLE {$wpdb->prefix}ns_assets (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			asset_type varchar(100) NOT NULL,
			asset_name varchar(255) NOT NULL,
			description text DEFAULT NULL,
			category varchar(100) DEFAULT NULL,
			acquisition_date date DEFAULT NULL,
			acquisition_method varchar(50) DEFAULT NULL,
			in_kind_donation_id bigint(20) unsigned DEFAULT NULL,
			purchase_price decimal(10,2) DEFAULT NULL,
			current_value decimal(10,2) DEFAULT NULL,
			last_valuation_date date DEFAULT NULL,
			depreciation_method varchar(50) DEFAULT NULL,
			useful_life_years int(3) DEFAULT NULL,
			location varchar(255) DEFAULT NULL,
			condition_rating varchar(50) DEFAULT NULL,
			serial_number varchar(255) DEFAULT NULL,
			model varchar(255) DEFAULT NULL,
			manufacturer varchar(255) DEFAULT NULL,
			warranty_expiration date DEFAULT NULL,
			assigned_to bigint(20) unsigned DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'active',
			disposal_date date DEFAULT NULL,
			disposal_method varchar(100) DEFAULT NULL,
			disposal_value decimal(10,2) DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY asset_type (asset_type),
			KEY status (status),
			KEY assigned_to (assigned_to)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 5: Advocacy Issues
		$sql = "CREATE TABLE {$wpdb->prefix}ns_advocacy_issues (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			description text DEFAULT NULL,
			issue_type varchar(50) NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'monitoring',
			priority varchar(50) DEFAULT 'medium',
			position varchar(50) DEFAULT NULL,
			legislative_body varchar(100) DEFAULT NULL,
			bill_number varchar(50) DEFAULT NULL,
			sponsor varchar(255) DEFAULT NULL,
			current_stage varchar(100) DEFAULT NULL,
			target_decision_date date DEFAULT NULL,
			decision_date date DEFAULT NULL,
			outcome varchar(50) DEFAULT NULL,
			impact_level varchar(50) DEFAULT NULL,
			talking_points text DEFAULT NULL,
			resources text DEFAULT NULL,
			assigned_to bigint(20) unsigned DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY issue_type (issue_type),
			KEY priority (priority)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 5: Advocacy Campaigns
		$sql = "CREATE TABLE {$wpdb->prefix}ns_advocacy_campaigns (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			issue_id bigint(20) unsigned NOT NULL,
			campaign_name varchar(255) NOT NULL,
			campaign_type varchar(50) NOT NULL,
			description text DEFAULT NULL,
			start_date date NOT NULL,
			end_date date DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'planning',
			target_audience text DEFAULT NULL,
			call_to_action text DEFAULT NULL,
			action_count int(10) unsigned DEFAULT 0,
			goal_count int(10) unsigned DEFAULT NULL,
			email_template text DEFAULT NULL,
			letter_template text DEFAULT NULL,
			talking_points text DEFAULT NULL,
			resources text DEFAULT NULL,
			hashtags varchar(255) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY issue_id (issue_id),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 5: Advocacy Actions
		$sql = "CREATE TABLE {$wpdb->prefix}ns_advocacy_actions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned NOT NULL,
			person_id bigint(20) unsigned DEFAULT NULL,
			action_type varchar(50) NOT NULL,
			action_date datetime NOT NULL,
			target_name varchar(255) DEFAULT NULL,
			target_type varchar(50) DEFAULT NULL,
			message text DEFAULT NULL,
			outcome varchar(50) DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY campaign_id (campaign_id),
			KEY person_id (person_id),
			KEY action_date (action_date)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 6: Audit Module - Audits
		$sql = "CREATE TABLE {$wpdb->prefix}ns_audits (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			audit_year int(4) NOT NULL,
			audit_type varchar(50) NOT NULL,
			auditor_firm varchar(255) DEFAULT NULL,
			auditor_contact_id bigint(20) unsigned DEFAULT NULL,
			audit_start_date date DEFAULT NULL,
			audit_end_date date DEFAULT NULL,
			fieldwork_start date DEFAULT NULL,
			fieldwork_end date DEFAULT NULL,
			report_date date DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'planning',
			opinion varchar(50) DEFAULT NULL,
			fee decimal(10,2) DEFAULT NULL,
			engagement_letter_signed tinyint(1) DEFAULT 0,
			management_letter_received tinyint(1) DEFAULT 0,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY audit_year (audit_year),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 6: Audit Module - Requests
		$sql = "CREATE TABLE {$wpdb->prefix}ns_audit_requests (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			audit_id bigint(20) unsigned NOT NULL,
			request_number varchar(50) DEFAULT NULL,
			category varchar(100) DEFAULT NULL,
			description text NOT NULL,
			requested_date date NOT NULL,
			due_date date DEFAULT NULL,
			assigned_to bigint(20) unsigned DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'pending',
			response text DEFAULT NULL,
			completed_date date DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY audit_id (audit_id),
			KEY status (status),
			KEY due_date (due_date)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 6: Audit Module - Findings
		$sql = "CREATE TABLE {$wpdb->prefix}ns_audit_findings (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			audit_id bigint(20) unsigned NOT NULL,
			finding_number varchar(50) DEFAULT NULL,
			finding_type varchar(50) NOT NULL,
			severity varchar(50) NOT NULL,
			category varchar(100) DEFAULT NULL,
			description text NOT NULL,
			recommendation text DEFAULT NULL,
			management_response text DEFAULT NULL,
			corrective_action_plan text DEFAULT NULL,
			responsible_person bigint(20) unsigned DEFAULT NULL,
			target_completion_date date DEFAULT NULL,
			actual_completion_date date DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'open',
			follow_up_notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY audit_id (audit_id),
			KEY status (status),
			KEY severity (severity)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 6: CPA Dashboard - Access
		$sql = "CREATE TABLE {$wpdb->prefix}ns_cpa_access (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			firm_name varchar(255) NOT NULL,
			contact_name varchar(255) NOT NULL,
			contact_email varchar(255) NOT NULL,
			contact_phone varchar(50) DEFAULT NULL,
			access_level varchar(50) DEFAULT 'full',
			granted_date datetime DEFAULT CURRENT_TIMESTAMP,
			expiration_date datetime DEFAULT NULL,
			status varchar(50) DEFAULT 'active',
			notes text DEFAULT NULL,
			revoked_date datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 6: CPA Dashboard - Shared Files
		$sql = "CREATE TABLE {$wpdb->prefix}ns_cpa_shared_files (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			access_id bigint(20) unsigned NOT NULL,
			file_name varchar(255) NOT NULL,
			file_type varchar(50) NOT NULL,
			file_path varchar(500) NOT NULL,
			file_size bigint(20) DEFAULT 0,
			category varchar(100) DEFAULT NULL,
			description text DEFAULT NULL,
			shared_by bigint(20) unsigned NOT NULL,
			shared_date datetime DEFAULT CURRENT_TIMESTAMP,
			download_count int(11) DEFAULT 0,
			last_downloaded datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY access_id (access_id),
			KEY category (category),
			KEY idx_access_category (access_id, category)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 6: Legal Counsel - Access
		$sql = "CREATE TABLE {$wpdb->prefix}ns_legal_access (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			firm_name varchar(255) NOT NULL,
			attorney_name varchar(255) NOT NULL,
			attorney_email varchar(255) NOT NULL,
			attorney_phone varchar(50) DEFAULT NULL,
			bar_number varchar(100) DEFAULT NULL,
			specialization varchar(255) DEFAULT NULL,
			access_level varchar(50) DEFAULT 'full',
			granted_date datetime DEFAULT CURRENT_TIMESTAMP,
			expiration_date datetime DEFAULT NULL,
			status varchar(50) DEFAULT 'active',
			notes text DEFAULT NULL,
			revoked_date datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 6: Legal Counsel - Matters
		$sql = "CREATE TABLE {$wpdb->prefix}ns_legal_matters (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			legal_access_id bigint(20) unsigned DEFAULT NULL,
			matter_type varchar(100) NOT NULL,
			matter_title varchar(255) NOT NULL,
			description text DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'open',
			priority varchar(50) DEFAULT 'medium',
			opened_date date NOT NULL,
			closed_date date DEFAULT NULL,
			outcome text DEFAULT NULL,
			fees_incurred decimal(10,2) DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY legal_access_id (legal_access_id),
			KEY status (status),
			KEY matter_type (matter_type)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 6: Program Evaluation - Evaluations
		$sql = "CREATE TABLE {$wpdb->prefix}ns_program_evaluations (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			program_id bigint(20) unsigned DEFAULT NULL,
			evaluation_name varchar(255) NOT NULL,
			evaluation_type varchar(50) NOT NULL,
			start_date date NOT NULL,
			end_date date DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'planning',
			evaluator varchar(255) DEFAULT NULL,
			methodology text DEFAULT NULL,
			sample_size int(10) DEFAULT NULL,
			response_rate decimal(5,2) DEFAULT NULL,
			findings_summary text DEFAULT NULL,
			recommendations text DEFAULT NULL,
			report_url varchar(255) DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY program_id (program_id),
			KEY status (status),
			KEY evaluation_type (evaluation_type)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 6: Program Evaluation - Metrics
		$sql = "CREATE TABLE {$wpdb->prefix}ns_evaluation_metrics (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			evaluation_id bigint(20) unsigned NOT NULL,
			metric_name varchar(255) NOT NULL,
			metric_type varchar(50) NOT NULL,
			measurement_unit varchar(100) DEFAULT NULL,
			target_value decimal(10,2) DEFAULT NULL,
			actual_value decimal(10,2) DEFAULT NULL,
			variance decimal(10,2) DEFAULT NULL,
			measurement_date date DEFAULT NULL,
			data_source varchar(255) DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY evaluation_id (evaluation_id),
			KEY metric_type (metric_type)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 6: Program Evaluation - Responses
		$sql = "CREATE TABLE {$wpdb->prefix}ns_evaluation_responses (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			evaluation_id bigint(20) unsigned NOT NULL,
			respondent_type varchar(50) DEFAULT NULL,
			response_date date NOT NULL,
			response_data text DEFAULT NULL,
			satisfaction_score int(2) DEFAULT NULL,
			comments text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY evaluation_id (evaluation_id),
			KEY respondent_type (respondent_type)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 6: Program Evaluation - Data Points
		$sql = "CREATE TABLE {$wpdb->prefix}ns_evaluation_data (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			evaluation_id bigint(20) unsigned NOT NULL,
			metric_id bigint(20) unsigned DEFAULT NULL,
			data_point_date date NOT NULL,
			data_value decimal(10,2) NOT NULL,
			data_label varchar(255) DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY evaluation_id (evaluation_id),
			KEY metric_id (metric_id)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 6: Service Delivery - Clients
		$sql = "CREATE TABLE {$wpdb->prefix}ns_clients (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_number varchar(50) NOT NULL,
			person_id bigint(20) unsigned DEFAULT NULL,
			intake_date date NOT NULL,
			caseworker_id bigint(20) unsigned DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'active',
			household_size int(3) DEFAULT NULL,
			primary_needs text DEFAULT NULL,
			service_plan text DEFAULT NULL,
			consent_signed tinyint(1) DEFAULT 0,
			consent_date date DEFAULT NULL,
			discharge_date date DEFAULT NULL,
			discharge_reason text DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY client_number (client_number),
			KEY person_id (person_id),
			KEY caseworker_id (caseworker_id),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 6: Service Delivery - Service Records
		$sql = "CREATE TABLE {$wpdb->prefix}ns_service_records (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id bigint(20) unsigned NOT NULL,
			service_date date NOT NULL,
			service_type varchar(100) NOT NULL,
			staff_id bigint(20) unsigned DEFAULT NULL,
			program_id bigint(20) unsigned DEFAULT NULL,
			units decimal(10,2) DEFAULT NULL,
			unit_type varchar(50) DEFAULT NULL,
			service_notes text DEFAULT NULL,
			location varchar(255) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY client_id (client_id),
			KEY service_date (service_date),
			KEY service_type (service_type),
			KEY staff_id (staff_id)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 6: Service Delivery - Client Goals
		$sql = "CREATE TABLE {$wpdb->prefix}ns_client_goals (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id bigint(20) unsigned NOT NULL,
			goal_description text NOT NULL,
			goal_category varchar(100) DEFAULT NULL,
			target_date date DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'in_progress',
			progress_percentage int(3) DEFAULT 0,
			milestones text DEFAULT NULL,
			completion_date date DEFAULT NULL,
			outcome text DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY client_id (client_id),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 6: State Compliance - State Operations
		$sql = "CREATE TABLE {$wpdb->prefix}ns_state_operations (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			state_code varchar(2) NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'active',
			registration_date date DEFAULT NULL,
			registration_number varchar(100) DEFAULT NULL,
			filing_frequency varchar(50) DEFAULT NULL,
			fiscal_year_end varchar(5) DEFAULT NULL,
			chapter_name varchar(255) DEFAULT NULL,
			chapter_id bigint(20) unsigned DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY state_code (state_code),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 6: State Compliance - State Requirements
		$sql = "CREATE TABLE {$wpdb->prefix}ns_state_requirements (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			state_code varchar(2) NOT NULL,
			requirement_type varchar(100) NOT NULL,
			requirement_name varchar(255) NOT NULL,
			description text DEFAULT NULL,
			filing_agency varchar(255) DEFAULT NULL,
			frequency varchar(50) NOT NULL,
			due_date_rule text DEFAULT NULL,
			fee_amount decimal(10,2) DEFAULT NULL,
			form_name varchar(100) DEFAULT NULL,
			form_url varchar(255) DEFAULT NULL,
			instructions_url varchar(255) DEFAULT NULL,
			online_filing_url varchar(255) DEFAULT NULL,
			active tinyint(1) DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY state_code (state_code),
			KEY requirement_type (requirement_type),
			KEY active (active)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 6 (Wave 3) - Part 2: Anonymous Reporting
		$sql = "CREATE TABLE {$wpdb->prefix}ns_anonymous_reports (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			report_number varchar(50) NOT NULL,
			category varchar(100) NOT NULL,
			description text NOT NULL,
			incident_date date DEFAULT NULL,
			location varchar(255) DEFAULT NULL,
			witnesses text DEFAULT NULL,
			evidence_description text DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'submitted',
			priority varchar(50) DEFAULT 'medium',
			assigned_to bigint(20) unsigned DEFAULT NULL,
			investigation_notes text DEFAULT NULL,
			resolution text DEFAULT NULL,
			resolved_date datetime DEFAULT NULL,
			followup_required tinyint(1) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY report_number (report_number),
			KEY status (status),
			KEY priority (priority),
			KEY category (category)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 6 (Wave 3) - Part 2: Chapters & Affiliates
		$sql = "CREATE TABLE {$wpdb->prefix}ns_chapters (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			chapter_name varchar(255) NOT NULL,
			chapter_number varchar(50) DEFAULT NULL,
			chapter_type varchar(50) NOT NULL DEFAULT 'chapter',
			state_code varchar(2) DEFAULT NULL,
			city varchar(100) DEFAULT NULL,
			established_date date DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'active',
			ein varchar(20) DEFAULT NULL,
			president_id bigint(20) unsigned DEFAULT NULL,
			contact_email varchar(100) DEFAULT NULL,
			contact_phone varchar(20) DEFAULT NULL,
			website_url varchar(255) DEFAULT NULL,
			member_count int(11) DEFAULT 0,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY state_code (state_code),
			KEY chapter_type (chapter_type)
		) $charset_collate;";
		dbDelta( $sql );

		$sql = "CREATE TABLE {$wpdb->prefix}ns_chapter_financials (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			chapter_id bigint(20) unsigned NOT NULL,
			fiscal_year int(4) NOT NULL,
			revenue decimal(12,2) DEFAULT 0.00,
			expenses decimal(12,2) DEFAULT 0.00,
			net_assets decimal(12,2) DEFAULT 0.00,
			dues_collected decimal(12,2) DEFAULT 0.00,
			grants_awarded decimal(12,2) DEFAULT 0.00,
			report_date date DEFAULT NULL,
			audit_status varchar(50) DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY chapter_id (chapter_id),
			KEY fiscal_year (fiscal_year)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 6 (Wave 3) - Part 2: AI Assistant
		$sql = "CREATE TABLE {$wpdb->prefix}ns_ai_conversations (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			conversation_title varchar(255) DEFAULT NULL,
			model varchar(50) DEFAULT 'claude',
			total_messages int(11) DEFAULT 0,
			total_tokens int(11) DEFAULT 0,
			cost decimal(8,4) DEFAULT 0.00,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id)
		) $charset_collate;";
		dbDelta( $sql );

		$sql = "CREATE TABLE {$wpdb->prefix}ns_ai_messages (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) unsigned NOT NULL,
			role varchar(20) NOT NULL,
			message text NOT NULL,
			tokens int(11) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY conversation_id (conversation_id)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 6 (Wave 3) - Part 2: Support System
		$sql = "CREATE TABLE {$wpdb->prefix}ns_support_tickets (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ticket_number varchar(50) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			subject varchar(255) NOT NULL,
			description text NOT NULL,
			category varchar(100) DEFAULT NULL,
			priority varchar(50) DEFAULT 'normal',
			status varchar(50) NOT NULL DEFAULT 'open',
			silverhost_ticket_id varchar(100) DEFAULT NULL,
			last_response_date datetime DEFAULT NULL,
			closed_date datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY ticket_number (ticket_number),
			KEY user_id (user_id),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql );

		$sql = "CREATE TABLE {$wpdb->prefix}ns_support_responses (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ticket_id bigint(20) unsigned NOT NULL,
			author_type varchar(20) NOT NULL,
			author_id bigint(20) unsigned DEFAULT NULL,
			message text NOT NULL,
			is_internal tinyint(1) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY ticket_id (ticket_id)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 6 (Wave 3) - Part 2: Dashboard Widgets
		$sql = "CREATE TABLE {$wpdb->prefix}ns_dashboard_widgets (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			widget_type varchar(100) NOT NULL,
			position int(11) DEFAULT 0,
			settings text DEFAULT NULL,
			is_visible tinyint(1) DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY position (position)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 6 (Wave 3) - Part 2: Calendar Integration
		$sql = "CREATE TABLE {$wpdb->prefix}ns_calendar_items (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			description text DEFAULT NULL,
			item_type varchar(50) NOT NULL,
			source_module varchar(50) NOT NULL,
			source_id bigint(20) unsigned DEFAULT NULL,
			start_date datetime NOT NULL,
			end_date datetime DEFAULT NULL,
			all_day tinyint(1) DEFAULT 0,
			recurrence varchar(50) DEFAULT NULL,
			location varchar(255) DEFAULT NULL,
			attendees text DEFAULT NULL,
			color varchar(20) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY item_type (item_type),
			KEY source_module (source_module),
			KEY start_date (start_date)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 6 (Wave 3) - Part 2: Email Campaigns
		$sql = "CREATE TABLE {$wpdb->prefix}ns_email_campaigns (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_name varchar(255) NOT NULL,
			subject_line varchar(255) NOT NULL,
			from_name varchar(100) DEFAULT NULL,
			from_email varchar(100) DEFAULT NULL,
			reply_to varchar(100) DEFAULT NULL,
			email_content longtext NOT NULL,
			template_id bigint(20) unsigned DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'draft',
			segment varchar(100) DEFAULT NULL,
			scheduled_date datetime DEFAULT NULL,
			sent_date datetime DEFAULT NULL,
			total_recipients int(11) DEFAULT 0,
			opened_count int(11) DEFAULT 0,
			clicked_count int(11) DEFAULT 0,
			unsubscribed_count int(11) DEFAULT 0,
			bounced_count int(11) DEFAULT 0,
			created_by bigint(20) unsigned NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY scheduled_date (scheduled_date)
		) $charset_collate;";
		dbDelta( $sql );

		$sql = "CREATE TABLE {$wpdb->prefix}ns_campaign_sends (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned NOT NULL,
			recipient_email varchar(100) NOT NULL,
			recipient_name varchar(255) DEFAULT NULL,
			sent_date datetime NOT NULL,
			opened_date datetime DEFAULT NULL,
			clicked_date datetime DEFAULT NULL,
			unsubscribed_date datetime DEFAULT NULL,
			bounced tinyint(1) DEFAULT 0,
			bounce_reason varchar(255) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY campaign_id (campaign_id),
			KEY recipient_email (recipient_email)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 6 (Wave 3) - Part 2: Mobile API
		$sql = "CREATE TABLE {$wpdb->prefix}ns_api_tokens (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			token varchar(255) NOT NULL,
			token_name varchar(100) DEFAULT NULL,
			permissions text DEFAULT NULL,
			last_used datetime DEFAULT NULL,
			expires_at datetime DEFAULT NULL,
			is_active tinyint(1) DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			KEY user_id (user_id),
			KEY is_active (is_active)
		) $charset_collate;";
		dbDelta( $sql );

		$sql = "CREATE TABLE {$wpdb->prefix}ns_api_logs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			token_id bigint(20) unsigned DEFAULT NULL,
			endpoint varchar(255) NOT NULL,
			method varchar(10) NOT NULL,
			request_data text DEFAULT NULL,
			response_code int(11) DEFAULT NULL,
			response_time int(11) DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			user_agent text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY token_id (token_id),
			KEY endpoint (endpoint),
			KEY created_at (created_at)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 3 (CONTINUE4): Multi-State Professional Licensing System

		// Professional Licenses Table
		$sql = "CREATE TABLE {$wpdb->prefix}ns_professional_licenses (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			professional_id bigint(20) unsigned NOT NULL,
			professional_type varchar(50) NOT NULL,
			state_code varchar(2) NOT NULL,
			license_number varchar(100) DEFAULT NULL,
			license_status varchar(50) NOT NULL DEFAULT 'active',
			issue_date date DEFAULT NULL,
			expiration_date date DEFAULT NULL,
			continuing_education_required tinyint(1) DEFAULT 0,
			ce_hours_required int(3) DEFAULT NULL,
			ce_deadline date DEFAULT NULL,
			verified tinyint(1) DEFAULT 0,
			verification_date date DEFAULT NULL,
			verification_source varchar(255) DEFAULT NULL,
			disciplinary_actions text DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY professional_id (professional_id),
			KEY professional_type (professional_type),
			KEY state_code (state_code),
			KEY license_status (license_status),
			KEY expiration_date (expiration_date),
			UNIQUE KEY unique_license (professional_id, professional_type, state_code)
		) $charset_collate;";
		dbDelta( $sql );

		// State Professional Assignments Table
		$sql = "CREATE TABLE {$wpdb->prefix}ns_state_professional_assignments (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			state_code varchar(2) NOT NULL,
			chapter_id bigint(20) unsigned DEFAULT NULL,
			professional_id bigint(20) unsigned NOT NULL,
			professional_type varchar(50) NOT NULL,
			assignment_type varchar(50) NOT NULL,
			start_date date NOT NULL,
			end_date date DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'active',
			hourly_rate decimal(8,2) DEFAULT NULL,
			retainer_amount decimal(10,2) DEFAULT NULL,
			services_description text DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY state_code (state_code),
			KEY professional_id (professional_id),
			KEY chapter_id (chapter_id),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 4 (CONTINUE4): Alternative Asset Handling System

		// Alternative Assets Table
		$sql = "CREATE TABLE {$wpdb->prefix}ns_alternative_assets (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			donation_id bigint(20) unsigned DEFAULT NULL,
			in_kind_donation_id bigint(20) unsigned DEFAULT NULL,
			asset_type varchar(50) NOT NULL,
			asset_subtype varchar(100) DEFAULT NULL,
			description text NOT NULL,
			quantity decimal(18,8) DEFAULT NULL,
			unit varchar(50) DEFAULT NULL,
			date_received date NOT NULL,
			valuation_date date NOT NULL,
			valuation_method varchar(100) NOT NULL,
			fair_market_value decimal(15,2) NOT NULL,
			cost_basis decimal(15,2) DEFAULT NULL,
			donor_holding_period varchar(50) DEFAULT NULL,
			current_value decimal(15,2) DEFAULT NULL,
			last_valued_date date DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'held',
			handling_policy varchar(50) NOT NULL,
			liquidation_date date DEFAULT NULL,
			liquidation_value decimal(15,2) DEFAULT NULL,
			liquidation_method varchar(100) DEFAULT NULL,
			liquidation_fees decimal(10,2) DEFAULT NULL,
			net_proceeds decimal(15,2) DEFAULT NULL,
			gain_loss decimal(15,2) DEFAULT NULL,
			custody_location varchar(255) DEFAULT NULL,
			wallet_address varchar(255) DEFAULT NULL,
			blockchain varchar(50) DEFAULT NULL,
			serial_number varchar(255) DEFAULT NULL,
			certificate_number varchar(255) DEFAULT NULL,
			purity varchar(50) DEFAULT NULL,
			weight varchar(50) DEFAULT NULL,
			appraiser_id bigint(20) unsigned DEFAULT NULL,
			appraisal_document_id bigint(20) unsigned DEFAULT NULL,
			tax_lot_id varchar(100) DEFAULT NULL,
			restricted tinyint(1) DEFAULT 0,
			restriction_terms text DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY asset_type (asset_type),
			KEY status (status),
			KEY date_received (date_received),
			KEY donor_holding_period (donor_holding_period),
			KEY donation_id (donation_id)
		) $charset_collate;";
		dbDelta( $sql );

		// PRO Phase 6 (CONTINUE4): Public Metrics System

		// Public Metrics Table
		$sql = "CREATE TABLE {$wpdb->prefix}ns_public_metrics (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			year int(4) NOT NULL,
			organization_name varchar(255) NOT NULL,
			year_founded int(4) DEFAULT NULL,
			organization_type varchar(100) DEFAULT NULL,
			states_operating varchar(255) DEFAULT NULL,
			states_count int(3) DEFAULT 0,
			total_revenue decimal(15,2) DEFAULT 0,
			total_expenses decimal(15,2) DEFAULT 0,
			program_expenses decimal(15,2) DEFAULT 0,
			fundraising_expenses decimal(15,2) DEFAULT 0,
			admin_expenses decimal(15,2) DEFAULT 0,
			total_assets decimal(15,2) DEFAULT 0,
			program_expense_ratio decimal(5,2) DEFAULT 0,
			fundraising_efficiency decimal(5,2) DEFAULT 0,
			admin_overhead decimal(5,2) DEFAULT 0,
			employee_count int(5) DEFAULT 0,
			volunteer_count int(5) DEFAULT 0,
			board_meetings_held int(3) DEFAULT 0,
			programs_count int(3) DEFAULT 0,
			grants_received_count int(3) DEFAULT 0,
			donor_count int(5) DEFAULT 0,
			last_submitted datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY year (year),
			KEY organization_type (organization_type),
			KEY states_count (states_count)
		) $charset_collate;";
		dbDelta( $sql );

		// Update database version
		update_option( 'nonprofitsuite_db_version', NONPROFITSUITE_VERSION );
	}

	/**
	 * Create custom roles and capabilities.
	 */
	private static function create_roles() {
		require_once NONPROFITSUITE_PATH . 'includes/class-roles.php';
		NonprofitSuite_Roles::create_roles();
	}

	/**
	 * Set default plugin options.
	 */
	private static function set_default_options() {
		$defaults = array(
			'nonprofitsuite_setup_complete' => false,
			'nonprofitsuite_organization_name' => '',
			'nonprofitsuite_organization_type' => '',
			'nonprofitsuite_formation_status' => '',
			'nonprofitsuite_storage_adapter' => 'builtin',
			'nonprofitsuite_email_adapter' => 'builtin',
			'nonprofitsuite_metrics_opt_in' => false,
		);

		foreach ( $defaults as $key => $value ) {
			if ( get_option( $key ) === false ) {
				add_option( $key, $value );
			}
		}

		// Feature flags - Disable Background Checks and Wealth Research pending legal review
		if ( get_option( 'ns_feature_flags' ) === false ) {
			add_option( 'ns_feature_flags', array(
				'disable_background_checks' => true,  // Disabled pending FCRA legal review
				'disable_wealth_research'    => true,  // Disabled pending legal review
			) );
		}
	}

	/**
	 * Register custom capabilities for sensitive features.
	 */
	private static function register_custom_capabilities() {
		require_once NONPROFITSUITE_PATH . 'includes/helpers/class-capability-manager.php';
		\NonprofitSuite\Helpers\NS_Capability_Manager::register_capabilities();
	}
}
