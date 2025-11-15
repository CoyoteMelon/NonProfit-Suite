<?php
/**
 * Storage Schema Migration
 *
 * Creates database tables for multi-tier storage architecture.
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
 * Create storage tables
 *
 * @return bool True on success
 */
function nonprofitsuite_create_storage_tables() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	// Table: Main file registry (source of truth)
	$table_files = $wpdb->prefix . 'ns_storage_files';
	$sql_files = "CREATE TABLE IF NOT EXISTS {$table_files} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		file_uuid varchar(36) NOT NULL,
		filename varchar(255) NOT NULL,
		original_filename varchar(255) NOT NULL,
		mime_type varchar(100) NOT NULL,
		file_size bigint(20) UNSIGNED NOT NULL,
		checksum_md5 varchar(32) NOT NULL,
		checksum_sha256 varchar(64) DEFAULT NULL,
		folder_path varchar(500) DEFAULT '',
		is_public tinyint(1) DEFAULT 0,
		is_archived tinyint(1) DEFAULT 0,
		current_version int UNSIGNED DEFAULT 1,
		has_physical_copy tinyint(1) DEFAULT 0 COMMENT 'Whether hardcopy exists',
		physical_location varchar(255) DEFAULT NULL COMMENT 'Filing cabinet, box, etc',
		physical_location_details text DEFAULT NULL COMMENT 'Detailed location info',
		physical_verified_at datetime DEFAULT NULL COMMENT 'Last physical verification',
		physical_verified_by bigint(20) UNSIGNED DEFAULT NULL COMMENT 'User who verified',
		category varchar(100) DEFAULT 'general' COMMENT 'Document category for organization',
		document_author varchar(255) DEFAULT NULL COMMENT 'Author name: individual, board, committee, etc',
		document_author_type varchar(50) DEFAULT NULL COMMENT 'individual, board, committee, organization, other',
		document_status varchar(50) DEFAULT 'draft' COMMENT 'draft, revised, final, approved, rejected, archived',
		document_status_changed_at datetime DEFAULT NULL COMMENT 'When status last changed',
		document_status_changed_by bigint(20) UNSIGNED DEFAULT NULL COMMENT 'User who changed status',
		workspace_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Workspace (group/committee) this file belongs to',
		is_protected tinyint(1) DEFAULT 0 COMMENT 'Document is protected from modification',
		protected_at datetime DEFAULT NULL COMMENT 'When protection was enabled',
		protected_by bigint(20) UNSIGNED DEFAULT NULL COMMENT 'User who enabled protection',
		protection_reason varchar(255) DEFAULT NULL COMMENT 'Why protection was enabled',
		protection_level varchar(20) DEFAULT 'none' COMMENT 'none, replace_only, edit_only, full',
		can_unprotect_capability varchar(100) DEFAULT 'manage_options' COMMENT 'Required capability to override',
		created_by bigint(20) UNSIGNED NOT NULL,
		created_at datetime NOT NULL,
		updated_at datetime DEFAULT NULL,
		deleted_at datetime DEFAULT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY file_uuid (file_uuid),
		KEY filename (filename),
		KEY folder_path (folder_path(191)),
		KEY is_public (is_public),
		KEY has_physical_copy (has_physical_copy),
		KEY category (category),
		KEY document_status (document_status),
		KEY document_author_type (document_author_type),
		KEY workspace_id (workspace_id),
		KEY checksum_md5 (checksum_md5),
		KEY created_by (created_by),
		KEY created_at (created_at),
		KEY is_protected (is_protected),
		KEY protection_level (protection_level)
	) $charset_collate;";

	// Table: Version history
	$table_versions = $wpdb->prefix . 'ns_storage_versions';
	$sql_versions = "CREATE TABLE IF NOT EXISTS {$table_versions} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		file_id bigint(20) UNSIGNED NOT NULL,
		version_number int UNSIGNED NOT NULL,
		file_size bigint(20) UNSIGNED NOT NULL,
		checksum_md5 varchar(32) NOT NULL,
		checksum_sha256 varchar(64) DEFAULT NULL,
		change_description text DEFAULT NULL,
		uploaded_by bigint(20) UNSIGNED NOT NULL,
		uploaded_at datetime NOT NULL,
		is_current tinyint(1) DEFAULT 0,
		PRIMARY KEY (id),
		KEY file_id (file_id),
		KEY version_number (version_number),
		KEY is_current (is_current),
		KEY uploaded_at (uploaded_at),
		UNIQUE KEY file_version (file_id, version_number)
	) $charset_collate;";

	// Table: Physical storage locations (multi-tier)
	$table_locations = $wpdb->prefix . 'ns_storage_locations';
	$sql_locations = "CREATE TABLE IF NOT EXISTS {$table_locations} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		file_id bigint(20) UNSIGNED NOT NULL,
		version_id bigint(20) UNSIGNED NOT NULL,
		tier varchar(20) NOT NULL COMMENT 'cdn, cloud, cache, local',
		provider varchar(50) NOT NULL COMMENT 's3, backblaze, dropbox, gdrive, local',
		provider_file_id varchar(255) DEFAULT NULL,
		provider_path varchar(500) NOT NULL,
		provider_url text DEFAULT NULL,
		cdn_url text DEFAULT NULL,
		sync_status varchar(20) DEFAULT 'synced' COMMENT 'synced, syncing, pending, failed',
		last_synced_at datetime DEFAULT NULL,
		last_verified_at datetime DEFAULT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY (id),
		KEY file_id (file_id),
		KEY version_id (version_id),
		KEY tier (tier),
		KEY provider (provider),
		KEY sync_status (sync_status),
		UNIQUE KEY unique_location (file_id, version_id, tier, provider)
	) $charset_collate;";

	// Table: Cache metadata
	$table_cache = $wpdb->prefix . 'ns_storage_cache';
	$sql_cache = "CREATE TABLE IF NOT EXISTS {$table_cache} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		file_id bigint(20) UNSIGNED NOT NULL,
		version_id bigint(20) UNSIGNED NOT NULL,
		cache_path varchar(500) NOT NULL,
		cache_size bigint(20) UNSIGNED NOT NULL,
		hit_count int UNSIGNED DEFAULT 0,
		last_accessed_at datetime NOT NULL,
		expires_at datetime DEFAULT NULL,
		is_valid tinyint(1) DEFAULT 1,
		created_at datetime NOT NULL,
		PRIMARY KEY (id),
		KEY file_id (file_id),
		KEY version_id (version_id),
		KEY expires_at (expires_at),
		KEY last_accessed_at (last_accessed_at),
		KEY is_valid (is_valid)
	) $charset_collate;";

	// Table: Sync queue
	$table_sync_queue = $wpdb->prefix . 'ns_storage_sync_queue';
	$sql_sync_queue = "CREATE TABLE IF NOT EXISTS {$table_sync_queue} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		file_id bigint(20) UNSIGNED NOT NULL,
		version_id bigint(20) UNSIGNED NOT NULL,
		operation varchar(20) NOT NULL COMMENT 'upload, delete, sync, verify',
		from_tier varchar(20) DEFAULT NULL,
		to_tier varchar(20) NOT NULL,
		priority int DEFAULT 5 COMMENT '1=highest, 10=lowest',
		status varchar(20) DEFAULT 'pending' COMMENT 'pending, processing, completed, failed',
		attempts int DEFAULT 0,
		max_attempts int DEFAULT 3,
		error_message text DEFAULT NULL,
		scheduled_at datetime NOT NULL,
		started_at datetime DEFAULT NULL,
		completed_at datetime DEFAULT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY (id),
		KEY file_id (file_id),
		KEY status (status),
		KEY priority (priority),
		KEY scheduled_at (scheduled_at)
	) $charset_collate;";

	// Table: Permissions (Unix-style owner/group/world with RWX)
	$table_permissions = $wpdb->prefix . 'ns_storage_permissions';
	$sql_permissions = "CREATE TABLE IF NOT EXISTS {$table_permissions} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		file_id bigint(20) UNSIGNED NOT NULL,
		permission_type varchar(20) NOT NULL COMMENT 'owner, group, world',
		owner_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'User ID for owner type',
		group_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Workspace/committee ID for group type',
		can_read tinyint(1) DEFAULT 0 COMMENT 'R permission',
		can_write tinyint(1) DEFAULT 0 COMMENT 'W permission',
		can_execute tinyint(1) DEFAULT 0 COMMENT 'X permission (download/export)',
		inherit_to_children tinyint(1) DEFAULT 0 COMMENT 'Child groups inherit permissions',
		expires_at datetime DEFAULT NULL,
		created_by bigint(20) UNSIGNED NOT NULL,
		created_at datetime NOT NULL,
		updated_at datetime DEFAULT NULL,
		PRIMARY KEY (id),
		KEY file_id (file_id),
		KEY permission_type (permission_type),
		KEY owner_id (owner_id),
		KEY group_id (group_id),
		KEY expires_at (expires_at),
		UNIQUE KEY file_permission_unique (file_id, permission_type, owner_id, group_id)
	) $charset_collate;";

	// Table: Document discovery metadata (AI categorization)
	$table_discovery = $wpdb->prefix . 'ns_document_discovery';
	$sql_discovery = "CREATE TABLE IF NOT EXISTS {$table_discovery} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		file_id bigint(20) UNSIGNED NOT NULL,
		discovered_category varchar(100) DEFAULT NULL,
		discovered_subcategory varchar(100) DEFAULT NULL,
		auto_tags text DEFAULT NULL COMMENT 'JSON array of tags',
		content_summary text DEFAULT NULL,
		document_date date DEFAULT NULL COMMENT 'Extracted date from content',
		key_entities text DEFAULT NULL COMMENT 'JSON: people, orgs, places',
		language varchar(10) DEFAULT 'en',
		ocr_text longtext DEFAULT NULL,
		confidence_score decimal(3,2) DEFAULT NULL COMMENT '0.00 to 1.00',
		needs_review tinyint(1) DEFAULT 0,
		reviewed_by bigint(20) UNSIGNED DEFAULT NULL,
		reviewed_at datetime DEFAULT NULL,
		discovered_at datetime NOT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY file_id (file_id),
		KEY discovered_category (discovered_category),
		KEY needs_review (needs_review),
		KEY confidence_score (confidence_score)
	) $charset_collate;";

	// Table: Usage tracking
	$table_usage = $wpdb->prefix . 'ns_storage_usage';
	$sql_usage = "CREATE TABLE IF NOT EXISTS {$table_usage} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		provider varchar(50) NOT NULL,
		tier varchar(20) NOT NULL,
		date date NOT NULL,
		total_files int UNSIGNED DEFAULT 0,
		total_bytes bigint(20) UNSIGNED DEFAULT 0,
		bandwidth_uploaded bigint(20) UNSIGNED DEFAULT 0,
		bandwidth_downloaded bigint(20) UNSIGNED DEFAULT 0,
		api_calls int UNSIGNED DEFAULT 0,
		estimated_cost decimal(10,4) DEFAULT 0.0000,
		created_at datetime NOT NULL,
		updated_at datetime DEFAULT NULL,
		PRIMARY KEY (id),
		KEY provider (provider),
		KEY tier (tier),
		KEY date (date),
		UNIQUE KEY provider_tier_date (provider, tier, date)
	) $charset_collate;";

	// Table: Document status history (workflow tracking)
	$table_status_history = $wpdb->prefix . 'ns_storage_status_history';
	$sql_status_history = "CREATE TABLE IF NOT EXISTS {$table_status_history} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		file_id bigint(20) UNSIGNED NOT NULL,
		previous_status varchar(50) DEFAULT NULL COMMENT 'Previous status',
		new_status varchar(50) NOT NULL COMMENT 'New status',
		changed_by bigint(20) UNSIGNED NOT NULL COMMENT 'User who made the change',
		changed_at datetime NOT NULL,
		change_note text DEFAULT NULL COMMENT 'Optional note about status change',
		PRIMARY KEY (id),
		KEY file_id (file_id),
		KEY new_status (new_status),
		KEY changed_by (changed_by),
		KEY changed_at (changed_at)
	) $charset_collate;";

	// Table: Workspaces (isolated folder areas for groups/committees)
	$table_workspaces = $wpdb->prefix . 'ns_storage_workspaces';
	$sql_workspaces = "CREATE TABLE IF NOT EXISTS {$table_workspaces} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL COMMENT 'Workspace display name',
		slug varchar(255) NOT NULL COMMENT 'URL-safe identifier',
		type varchar(50) NOT NULL COMMENT 'group, board, committee, joint-committee, organization',
		parent_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Parent workspace ID',
		is_published tinyint(1) DEFAULT 0 COMMENT 'Visible to entire organization',
		description text DEFAULT NULL,
		created_by bigint(20) UNSIGNED NOT NULL,
		created_at datetime NOT NULL,
		updated_at datetime DEFAULT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY slug (slug),
		KEY type (type),
		KEY parent_id (parent_id),
		KEY is_published (is_published)
	) $charset_collate;";

	// Table: Workspace access (user permissions for workspaces)
	$table_workspace_access = $wpdb->prefix . 'ns_storage_workspace_access';
	$sql_workspace_access = "CREATE TABLE IF NOT EXISTS {$table_workspace_access} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		workspace_id bigint(20) UNSIGNED NOT NULL,
		user_id bigint(20) UNSIGNED NOT NULL,
		role varchar(50) NOT NULL COMMENT 'admin, editor, viewer',
		granted_by bigint(20) UNSIGNED NOT NULL,
		granted_at datetime NOT NULL,
		updated_at datetime DEFAULT NULL,
		PRIMARY KEY (id),
		KEY workspace_id (workspace_id),
		KEY user_id (user_id),
		KEY role (role),
		UNIQUE KEY workspace_user (workspace_id, user_id)
	) $charset_collate;";

	// Table: Workspace links (for joint committees)
	$table_workspace_links = $wpdb->prefix . 'ns_storage_workspace_links';
	$sql_workspace_links = "CREATE TABLE IF NOT EXISTS {$table_workspace_links} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		workspace_id bigint(20) UNSIGNED NOT NULL,
		linked_workspace_id bigint(20) UNSIGNED NOT NULL,
		relationship varchar(50) NOT NULL COMMENT 'joint-member, parent-child, shared',
		created_at datetime NOT NULL,
		PRIMARY KEY (id),
		KEY workspace_id (workspace_id),
		KEY linked_workspace_id (linked_workspace_id),
		UNIQUE KEY workspace_link (workspace_id, linked_workspace_id)
	) $charset_collate;";

	// Table: File usage tracking (for automation)
	$table_file_usage = $wpdb->prefix . 'ns_storage_file_usage';
	$sql_file_usage = "CREATE TABLE IF NOT EXISTS {$table_file_usage} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		file_id bigint(20) UNSIGNED NOT NULL,
		access_count int UNSIGNED DEFAULT 0 COMMENT 'Total access count',
		download_count int UNSIGNED DEFAULT 0 COMMENT 'Download count',
		last_accessed_at datetime DEFAULT NULL,
		last_accessed_by bigint(20) UNSIGNED DEFAULT NULL,
		access_hourly int UNSIGNED DEFAULT 0 COMMENT 'Access in last hour',
		access_daily int UNSIGNED DEFAULT 0 COMMENT 'Access in last 24 hours',
		access_weekly int UNSIGNED DEFAULT 0 COMMENT 'Access in last 7 days',
		PRIMARY KEY (id),
		UNIQUE KEY file_id (file_id),
		KEY last_accessed_at (last_accessed_at),
		KEY access_daily (access_daily)
	) $charset_collate;";

	// Table: Automation log (tier migration tracking)
	$table_automation_log = $wpdb->prefix . 'ns_storage_automation_log';
	$sql_automation_log = "CREATE TABLE IF NOT EXISTS {$table_automation_log} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		file_id bigint(20) UNSIGNED NOT NULL,
		preset varchar(50) NOT NULL COMMENT 'Automation preset used',
		action varchar(50) NOT NULL COMMENT 'move, archive, etc',
		from_tier varchar(20) DEFAULT NULL,
		to_tier varchar(20) DEFAULT NULL,
		reason varchar(100) DEFAULT NULL COMMENT 'Why action was taken',
		executed_at datetime NOT NULL,
		PRIMARY KEY (id),
		KEY file_id (file_id),
		KEY preset (preset),
		KEY action (action),
		KEY executed_at (executed_at)
	) $charset_collate;";

	// Table: File links (virtual file system - one file in multiple locations)
	$table_file_links = $wpdb->prefix . 'ns_storage_file_links';
	$sql_file_links = "CREATE TABLE IF NOT EXISTS {$table_file_links} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		file_id bigint(20) UNSIGNED NOT NULL COMMENT 'The actual file',
		workspace_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Additional workspace where file appears',
		virtual_path varchar(500) DEFAULT NULL COMMENT 'Virtual folder path (e.g. /2023 Archive/Events)',
		link_type varchar(50) DEFAULT 'reference' COMMENT 'reference, shortcut, alias',
		created_by bigint(20) UNSIGNED NOT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY (id),
		KEY file_id (file_id),
		KEY workspace_id (workspace_id),
		KEY virtual_path (virtual_path(191)),
		UNIQUE KEY file_location (file_id, workspace_id, virtual_path(191))
	) $charset_collate;";

	// Table: Entity attachments (many-to-many files to entities)
	$table_entity_attachments = $wpdb->prefix . 'ns_storage_entity_attachments';
	$sql_entity_attachments = "CREATE TABLE IF NOT EXISTS {$table_entity_attachments} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		file_id bigint(20) UNSIGNED NOT NULL,
		entity_type varchar(50) NOT NULL COMMENT 'task, project, meeting, person, donor, grant, agenda, etc',
		entity_id bigint(20) UNSIGNED NOT NULL,
		attachment_note text DEFAULT NULL COMMENT 'Optional context about attachment',
		attached_by bigint(20) UNSIGNED NOT NULL,
		attached_at datetime NOT NULL,
		PRIMARY KEY (id),
		KEY file_id (file_id),
		KEY entity_type (entity_type),
		KEY entity_id (entity_id),
		KEY attached_at (attached_at),
		UNIQUE KEY file_entity (file_id, entity_type, entity_id)
	) $charset_collate;";

	// Table: Document relationships (document-to-document links)
	$table_document_relationships = $wpdb->prefix . 'ns_storage_document_relationships';
	$sql_document_relationships = "CREATE TABLE IF NOT EXISTS {$table_document_relationships} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		source_file_id bigint(20) UNSIGNED NOT NULL,
		target_file_id bigint(20) UNSIGNED NOT NULL,
		relationship_type varchar(50) NOT NULL COMMENT 'citation, reference, related, supersedes, superseded_by, amendment, parent, child',
		relationship_note text DEFAULT NULL COMMENT 'Optional context',
		created_by bigint(20) UNSIGNED NOT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY (id),
		KEY source_file_id (source_file_id),
		KEY target_file_id (target_file_id),
		KEY relationship_type (relationship_type),
		UNIQUE KEY document_relationship (source_file_id, target_file_id, relationship_type)
	) $charset_collate;";

	// Table: Protection rules (automatic protection triggers)
	$table_protection_rules = $wpdb->prefix . 'ns_storage_protection_rules';
	$sql_protection_rules = "CREATE TABLE IF NOT EXISTS {$table_protection_rules} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		rule_name varchar(255) NOT NULL,
		is_active tinyint(1) DEFAULT 1,
		trigger_type varchar(50) NOT NULL COMMENT 'status_change, workspace_change, manual',
		trigger_value varchar(100) DEFAULT NULL COMMENT 'approved, published, workspace_id, etc',
		protection_level varchar(20) NOT NULL COMMENT 'full, replace_only, edit_only',
		required_override_capability varchar(100) DEFAULT 'manage_options',
		description text DEFAULT NULL,
		created_by bigint(20) UNSIGNED NOT NULL,
		created_at datetime NOT NULL,
		updated_at datetime DEFAULT NULL,
		PRIMARY KEY (id),
		KEY is_active (is_active),
		KEY trigger_type (trigger_type)
	) $charset_collate;";

	// Table: Protection log (audit trail for protection changes)
	$table_protection_log = $wpdb->prefix . 'ns_storage_protection_log';
	$sql_protection_log = "CREATE TABLE IF NOT EXISTS {$table_protection_log} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		file_id bigint(20) UNSIGNED NOT NULL,
		action varchar(50) NOT NULL COMMENT 'protected, unprotected, override_edit, override_delete, override_replace',
		protection_level varchar(20) DEFAULT NULL,
		trigger_reason varchar(255) DEFAULT NULL COMMENT 'Why protection changed',
		authorization_reason text DEFAULT NULL COMMENT 'Why override was granted',
		performed_by bigint(20) UNSIGNED NOT NULL,
		performed_at datetime NOT NULL,
		ip_address varchar(45) DEFAULT NULL,
		user_agent varchar(255) DEFAULT NULL,
		PRIMARY KEY (id),
		KEY file_id (file_id),
		KEY action (action),
		KEY performed_by (performed_by),
		KEY performed_at (performed_at)
	) $charset_collate;";

	// Execute all table creations
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	dbDelta( $sql_files );
	dbDelta( $sql_versions );
	dbDelta( $sql_locations );
	dbDelta( $sql_cache );
	dbDelta( $sql_sync_queue );
	dbDelta( $sql_permissions );
	dbDelta( $sql_discovery );
	dbDelta( $sql_usage );
	dbDelta( $sql_status_history );
	dbDelta( $sql_workspaces );
	dbDelta( $sql_workspace_access );
	dbDelta( $sql_workspace_links );
	dbDelta( $sql_file_usage );
	dbDelta( $sql_automation_log );
	dbDelta( $sql_file_links );
	dbDelta( $sql_entity_attachments );
	dbDelta( $sql_document_relationships );
	dbDelta( $sql_protection_rules );
	dbDelta( $sql_protection_log );

	// Create indexes that dbDelta might miss
	$wpdb->query( "CREATE INDEX IF NOT EXISTS folder_path_created ON {$table_files}(folder_path(191), created_at)" );

	return true;
}

/**
 * Drop storage tables (for uninstall)
 *
 * @return bool True on success
 */
function nonprofitsuite_drop_storage_tables() {
	global $wpdb;

	$tables = array(
		$wpdb->prefix . 'ns_storage_files',
		$wpdb->prefix . 'ns_storage_versions',
		$wpdb->prefix . 'ns_storage_locations',
		$wpdb->prefix . 'ns_storage_cache',
		$wpdb->prefix . 'ns_storage_sync_queue',
		$wpdb->prefix . 'ns_storage_permissions',
		$wpdb->prefix . 'ns_document_discovery',
		$wpdb->prefix . 'ns_storage_usage',
	);

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	return true;
}
