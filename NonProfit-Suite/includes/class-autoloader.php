<?php
/**
 * Autoloader for lazy loading plugin classes
 *
 * This dramatically improves performance by only loading classes when they're actually used,
 * rather than loading all 43 modules on every page load.
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Autoloader {

	/**
	 * Register the autoloader.
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload classes.
	 *
	 * @param string $class_name The fully-qualified class name.
	 */
	public static function autoload( $class_name ) {
		// Only handle NonprofitSuite classes
		if ( strpos( $class_name, 'NonprofitSuite_' ) !== 0 ) {
			return;
		}

		// Map class names to file paths
		$class_map = array(
			// Core classes
			'NonprofitSuite_Module_Base'   => 'includes/abstract-class-module.php',
			'NonprofitSuite_Migrator'      => 'includes/class-migrator.php',
			'NonprofitSuite_License'       => 'includes/class-license.php',
			'NonprofitSuite_Roles'         => 'includes/class-roles.php',

			// Entity classes
			'NonprofitSuite_Person'        => 'includes/entities/class-person.php',
			'NonprofitSuite_Organization'  => 'includes/entities/class-organization.php',
			'NonprofitSuite_Document'      => 'includes/entities/class-document.php',
			'NonprofitSuite_Retention_Policy' => 'includes/entities/class-retention-policy.php',

			// FREE Module classes
			'NonprofitSuite_Meetings'      => 'includes/modules/class-meetings.php',
			'NonprofitSuite_Agenda'        => 'includes/modules/class-agenda.php',
			'NonprofitSuite_Minutes'       => 'includes/modules/class-minutes.php',
			'NonprofitSuite_Tasks'         => 'includes/modules/class-tasks.php',
			'NonprofitSuite_Documents'     => 'includes/modules/class-documents.php',
			'NonprofitSuite_Calendar'      => 'includes/modules/class-calendar.php',

			// PRO Module classes (Phase 2)
			'NonprofitSuite_Treasury'      => 'includes/modules/class-treasury.php',
			'NonprofitSuite_Donors'        => 'includes/modules/class-donors.php',
			'NonprofitSuite_Volunteers'    => 'includes/modules/class-volunteers.php',
			'NonprofitSuite_Grants'        => 'includes/modules/class-grants.php',
			'NonprofitSuite_Programs'      => 'includes/modules/class-programs.php',

			// PRO Module classes (Phase 3)
			'NonprofitSuite_Fundraising'   => 'includes/modules/class-fundraising.php',
			'NonprofitSuite_Membership'    => 'includes/modules/class-membership.php',
			'NonprofitSuite_Events'        => 'includes/modules/class-events.php',
			'NonprofitSuite_Committees'    => 'includes/modules/class-committees.php',
			'NonprofitSuite_Compliance'    => 'includes/modules/class-compliance.php',
			'NonprofitSuite_HR'            => 'includes/modules/class-hr.php',
			'NonprofitSuite_Communications' => 'includes/modules/class-communications.php',

			// PRO Module classes (Phase 4)
			'NonprofitSuite_Policy'        => 'includes/modules/class-policy.php',
			'NonprofitSuite_Board_Development' => 'includes/modules/class-board-development.php',
			'NonprofitSuite_Formation'     => 'includes/modules/class-formation.php',
			'NonprofitSuite_Projects'      => 'includes/modules/class-projects.php',
			'NonprofitSuite_Training'      => 'includes/modules/class-training.php',

			// PRO Module classes (Phase 5)
			'NonprofitSuite_Secretary'     => 'includes/modules/class-secretary.php',
			'NonprofitSuite_Chair'         => 'includes/modules/class-chair.php',
			'NonprofitSuite_Prospect_Research' => 'includes/modules/class-prospect-research.php',
			'NonprofitSuite_In_Kind'       => 'includes/modules/class-in-kind.php',
			'NonprofitSuite_Advocacy'      => 'includes/modules/class-advocacy.php',

			// PRO Module classes (Phase 5 - Refactored Modules)
			'NonprofitSuite_InKind_Donations'  => 'includes/modules/class-in-kind-donations.php',
			'NonprofitSuite_Asset_Management'  => 'includes/modules/class-asset-management.php',
			'NonprofitSuite_Advocacy_Issues'   => 'includes/modules/class-advocacy-issues.php',
			'NonprofitSuite_Advocacy_Campaigns' => 'includes/modules/class-advocacy-campaigns.php',
			'NonprofitSuite_Advocacy_Actions'  => 'includes/modules/class-advocacy-actions.php',
			'NonprofitSuite_Prospects'         => 'includes/modules/class-prospects.php',
			'NonprofitSuite_Wealth_Indicators' => 'includes/modules/class-wealth-indicators.php',

			// PRO Module classes (Phase 6)
			'NonprofitSuite_Audit'         => 'includes/modules/class-audit.php',
			'NonprofitSuite_CPA_Dashboard' => 'includes/modules/class-cpa-dashboard.php',
			'NonprofitSuite_Legal_Counsel' => 'includes/modules/class-legal-counsel.php',

			// PRO Module classes (Phase 6 - Refactored Modules)
			'NonprofitSuite_Legal_Access'      => 'includes/modules/class-legal-access.php',
			'NonprofitSuite_Legal_Matters'     => 'includes/modules/class-legal-matters.php',
			'NonprofitSuite_Bar_Admissions'    => 'includes/modules/class-bar-admissions.php',
			'NonprofitSuite_CPA_Access'        => 'includes/modules/class-cpa-access.php',
			'NonprofitSuite_CPA_Files'         => 'includes/modules/class-cpa-files.php',
			'NonprofitSuite_CPA_Licensing'     => 'includes/modules/class-cpa-licensing.php',
			'NonprofitSuite_Program_Evaluation' => 'includes/modules/class-program-evaluation.php',
			'NonprofitSuite_Service_Delivery' => 'includes/modules/class-service-delivery.php',
			'NonprofitSuite_State_Compliance' => 'includes/modules/class-state-compliance.php',
			'NonprofitSuite_Alternative_Assets' => 'includes/modules/class-alternative-assets.php',
			'NonprofitSuite_Anonymous_Reporting' => 'includes/modules/class-anonymous-reporting.php',
			'NonprofitSuite_Chapters'      => 'includes/modules/class-chapters.php',
			'NonprofitSuite_AI_Assistant'  => 'includes/modules/class-ai-assistant.php',
			'NonprofitSuite_Support'       => 'includes/modules/class-support.php',
			'NonprofitSuite_Dashboard_Widgets' => 'includes/modules/class-dashboard-widgets.php',
			'NonprofitSuite_Email_Campaigns' => 'includes/modules/class-email-campaigns.php',
			'NonprofitSuite_Mobile_API'    => 'includes/modules/class-mobile-api.php',

			// Helper classes
			'NonprofitSuite_Notifications' => 'includes/helpers/class-notifications.php',
			'NonprofitSuite_Utilities'     => 'includes/helpers/class-utilities.php',
			'NonprofitSuite_PDF_Generator' => 'includes/helpers/class-pdf-generator.php',
			'NonprofitSuite_Cache'         => 'includes/helpers/class-cache.php',
			'NonprofitSuite_Security'      => 'includes/helpers/class-security.php',
			'NonprofitSuite_Document_Retention' => 'includes/helpers/class-document-retention.php',

			// Admin classes
			'NonprofitSuite_Admin'         => 'admin/class-admin.php',

			// Integration framework classes
			'NonprofitSuite_Integration_Manager'   => 'includes/integrations/class-integration-manager.php',
			'NonprofitSuite_Integration_Settings'  => 'includes/integrations/class-integration-settings.php',
			'NonprofitSuite_Webhook_Handler'       => 'includes/integrations/class-webhook-handler.php',
			'NonprofitSuite_Integration_Migrator'  => 'includes/integrations/class-integration-migrator.php',

			// Integration adapter interfaces
			'NonprofitSuite_Storage_Adapter_Interface'     => 'includes/integrations/interfaces/interface-storage-adapter.php',
			'NonprofitSuite_Calendar_Adapter_Interface'    => 'includes/integrations/interfaces/interface-calendar-adapter.php',
			'NonprofitSuite_Email_Adapter_Interface'       => 'includes/integrations/interfaces/interface-email-adapter.php',
			'NonprofitSuite_Accounting_Adapter_Interface'  => 'includes/integrations/interfaces/interface-accounting-adapter.php',
			'NonprofitSuite_Payment_Adapter_Interface'     => 'includes/integrations/interfaces/interface-payment-adapter.php',
			'NonprofitSuite_CRM_Adapter_Interface'         => 'includes/integrations/interfaces/interface-crm-adapter.php',
			'NonprofitSuite_Marketing_Adapter_Interface'   => 'includes/integrations/interfaces/interface-marketing-adapter.php',
			'NonprofitSuite_Video_Adapter_Interface'       => 'includes/integrations/interfaces/interface-video-adapter.php',
			'NonprofitSuite_Forms_Adapter_Interface'       => 'includes/integrations/interfaces/interface-forms-adapter.php',
			'NonprofitSuite_Project_Adapter_Interface'     => 'includes/integrations/interfaces/interface-project-adapter.php',
			'NonprofitSuite_AI_Adapter_Interface'          => 'includes/integrations/interfaces/interface-ai-adapter.php',
			'NonprofitSuite_SMS_Adapter_Interface'         => 'includes/integrations/interfaces/interface-sms-adapter.php',
			'NonprofitSuite_Analytics_Adapter_Interface'   => 'includes/integrations/interfaces/interface-analytics-adapter.php',

			// Built-in integration adapters
			'NonprofitSuite_Storage_Local_Adapter'         => 'includes/integrations/adapters/class-storage-local-adapter.php',
			'NonprofitSuite_Calendar_Builtin_Adapter'      => 'includes/integrations/adapters/class-calendar-builtin-adapter.php',
			'NonprofitSuite_Email_WordPress_Adapter'       => 'includes/integrations/adapters/class-email-wordpress-adapter.php',
			'NonprofitSuite_Accounting_Treasury_Adapter'   => 'includes/integrations/adapters/class-accounting-treasury-adapter.php',
			'NonprofitSuite_Forms_Builtin_Adapter'         => 'includes/integrations/adapters/class-forms-builtin-adapter.php',

			// Cloud storage adapters
			'NonprofitSuite_Storage_S3_Adapter'            => 'includes/integrations/adapters/class-storage-s3-adapter.php',
			'NonprofitSuite_Storage_B2_Adapter'            => 'includes/integrations/adapters/class-storage-b2-adapter.php',
			'NonprofitSuite_Storage_Dropbox_Adapter'       => 'includes/integrations/adapters/class-storage-dropbox-adapter.php',
			'NonprofitSuite_Storage_GoogleDrive_Adapter'   => 'includes/integrations/adapters/class-storage-googledrive-adapter.php',

			// Storage system classes
			'NonprofitSuite_Storage_Orchestrator'          => 'includes/integrations/storage/class-storage-orchestrator.php',
			'NonprofitSuite_Storage_Cache'                 => 'includes/integrations/storage/class-storage-cache.php',
			'NonprofitSuite_Storage_Version_Manager'       => 'includes/integrations/storage/class-storage-version-manager.php',
			'NonprofitSuite_Storage_Sync_Manager'          => 'includes/integrations/storage/class-storage-sync-manager.php',
			'NonprofitSuite_Storage_Admin_UI'              => 'includes/integrations/storage/class-storage-admin-ui.php',
			'NonprofitSuite_Document_Discovery'            => 'includes/integrations/storage/class-document-discovery.php',
			'NonprofitSuite_Duplicate_Detector'            => 'includes/integrations/storage/class-duplicate-detector.php',
			'NonprofitSuite_Workspace_Manager'             => 'includes/integrations/storage/class-workspace-manager.php',
			'NonprofitSuite_Orchestrator_Automation'       => 'includes/integrations/storage/class-orchestrator-automation.php',
			'NonprofitSuite_Document_Protection'           => 'includes/integrations/storage/class-document-protection.php',
			'NonprofitSuite_Entity_Attachment_Manager'     => 'includes/integrations/storage/class-entity-attachment-manager.php',
			'NonprofitSuite_Document_Permissions'          => 'includes/integrations/storage/class-document-permissions.php',
		);

		// Check if class is in our map
		if ( isset( $class_map[ $class_name ] ) ) {
			$file = NONPROFITSUITE_PATH . $class_map[ $class_name ];

			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	}

	/**
	 * Preload critical classes that are always needed.
	 *
	 * These classes are loaded immediately as they're used on every page load.
	 */
	public static function preload_critical_classes() {
		$critical_classes = array(
			'NonprofitSuite_License',
			'NonprofitSuite_Utilities',
			'NonprofitSuite_Cache',
			'NonprofitSuite_Admin',
			'NonprofitSuite_Integration_Manager',
		);

		foreach ( $critical_classes as $class ) {
			if ( ! class_exists( $class ) ) {
				class_exists( $class ); // Triggers autoload
			}
		}
	}
}
