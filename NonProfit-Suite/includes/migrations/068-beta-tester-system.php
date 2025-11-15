<?php
/**
 * Migration 068: Beta Tester System
 *
 * Creates tables for beta testing program with 1,060 slots:
 * - 500 registered 501(c)(3) nonprofits
 * - First 10 pre-nonprofits per state/territory (560 total)
 *
 * @package    NonprofitSuite
 * @subpackage Migrations
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Run migration 068
 */
function ns_migration_068_beta_tester_system() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	// Beta Tester Applications
	$sql_applications = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ns_beta_applications (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		organization_name varchar(255) NOT NULL,
		ein varchar(20),
		contact_name varchar(255) NOT NULL,
		contact_email varchar(255) NOT NULL,
		contact_phone varchar(50),

		-- Organization Type
		is_501c3 tinyint(1) DEFAULT 0,
		has_determination_letter tinyint(1) DEFAULT 0,
		determination_letter_file varchar(255),

		-- Location
		state varchar(2) NOT NULL COMMENT 'Two-letter state/territory code',
		city varchar(100),

		-- Status
		status varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, approved, rejected, waitlist',
		slot_type varchar(20) COMMENT '501c3, pre_nonprofit',
		application_date datetime DEFAULT CURRENT_TIMESTAMP,
		approved_date datetime,
		approved_by bigint(20) UNSIGNED,

		-- License (Freemius integration)
		license_key varchar(100) UNIQUE,
		license_activated tinyint(1) DEFAULT 0,
		license_activated_date datetime,
		freemius_user_id bigint(20) UNSIGNED COMMENT 'Freemius user ID',
		freemius_license_id bigint(20) UNSIGNED COMMENT 'Freemius license ID',

		-- Pre-nonprofit requirement
		forming_module_completed tinyint(1) DEFAULT 0 COMMENT 'Pre-nonprofits must complete Forming a Nonprofit module',
		forming_module_completed_date datetime,

		-- Tracking
		last_survey_date datetime,
		survey_count int DEFAULT 0,
		feedback_count int DEFAULT 0,

		PRIMARY KEY (id),
		KEY status (status),
		KEY state (state),
		KEY is_501c3 (is_501c3),
		KEY slot_type (slot_type),
		KEY contact_email (contact_email),
		KEY license_key (license_key)
	) $charset_collate;";

	// Beta Tester Survey Responses
	$sql_surveys = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ns_beta_surveys (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		application_id bigint(20) UNSIGNED NOT NULL,
		survey_version varchar(20) DEFAULT '1.0',
		submitted_at datetime DEFAULT CURRENT_TIMESTAMP,

		-- Overall Experience (1-5 scale)
		overall_satisfaction int,
		ease_of_use int,
		feature_completeness int,
		performance int,
		would_recommend int,

		-- Feature Ratings (1-5 scale, NULL if not used)
		meetings_rating int,
		documents_rating int,
		treasury_rating int,
		donors_rating int,
		volunteers_rating int,
		compliance_rating int,
		calendar_rating int,
		email_rating int,
		payments_rating int,
		membership_rating int,
		board_rating int,
		communications_rating int,
		events_rating int,
		grants_rating int,
		inventory_rating int,
		programs_rating int,

		-- Integration Usage
		integrations_used longtext COMMENT 'JSON array of used integrations',
		integration_issues longtext COMMENT 'JSON array of integration problems',

		-- Open Feedback
		what_works_well longtext,
		what_is_broken longtext,
		what_is_missing longtext,
		feature_requests longtext,
		pain_points longtext,

		-- Technical Info
		wordpress_version varchar(20),
		php_version varchar(20),
		server_type varchar(50),
		active_users int,
		org_size varchar(50),

		PRIMARY KEY (id),
		KEY application_id (application_id),
		KEY submitted_at (submitted_at),
		KEY survey_version (survey_version)
	) $charset_collate;";

	// Beta Tester Feedback (ongoing, ad-hoc feedback)
	$sql_feedback = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ns_beta_feedback (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		application_id bigint(20) UNSIGNED NOT NULL,
		feedback_type varchar(50) NOT NULL COMMENT 'bug, feature_request, improvement, question, praise',
		category varchar(50) COMMENT 'module name or integration category',
		subject varchar(255),
		message longtext NOT NULL,

		-- Attachments
		screenshot_url text,

		-- Browser/System info
		user_agent text,
		browser varchar(100),
		os varchar(100),

		-- Status
		status varchar(20) DEFAULT 'new' COMMENT 'new, reviewing, resolved, closed',
		priority varchar(20) COMMENT 'low, medium, high, critical',

		-- Response
		admin_response longtext,
		responded_by bigint(20) UNSIGNED,
		responded_at datetime,

		submitted_at datetime DEFAULT CURRENT_TIMESTAMP,

		PRIMARY KEY (id),
		KEY application_id (application_id),
		KEY feedback_type (feedback_type),
		KEY status (status),
		KEY priority (priority),
		KEY submitted_at (submitted_at)
	) $charset_collate;";

	// Beta Tester Activity Log
	$sql_activity = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ns_beta_activity (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		application_id bigint(20) UNSIGNED NOT NULL,
		activity_type varchar(50) NOT NULL COMMENT 'login, feature_used, error, survey_completed, feedback_submitted',
		activity_data longtext COMMENT 'JSON data',
		occurred_at datetime DEFAULT CURRENT_TIMESTAMP,

		PRIMARY KEY (id),
		KEY application_id (application_id),
		KEY activity_type (activity_type),
		KEY occurred_at (occurred_at)
	) $charset_collate;";

	// Execute migrations
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	dbDelta( $sql_applications );
	dbDelta( $sql_surveys );
	dbDelta( $sql_feedback );
	dbDelta( $sql_activity );

	// Create beta program settings
	$default_settings = array(
		'program_active'          => true,
		'max_501c3_slots'         => 500,
		'max_prenp_per_state'     => 10,
		'auto_approval'           => true,
		'encourage_forming_module' => true, // Pre-nonprofits encouraged (not required) to complete module
		// Survey schedule: Days 7, 14, 30, 60, 90, 120, 150, 180, 270, 365
		// Weekly (7, 14), monthly (30, 60, 90, 120, 150, 180), quarterly (270), annual (365)
		'survey_schedule_days'    => array( 7, 14, 30, 60, 90, 120, 150, 180, 270, 365 ),
		'email_templates_enabled' => true,
	);

	add_option( 'ns_beta_program_settings', $default_settings );

	return true;
}

// Register migration
ns_register_migration( 68, 'ns_migration_068_beta_tester_system' );
