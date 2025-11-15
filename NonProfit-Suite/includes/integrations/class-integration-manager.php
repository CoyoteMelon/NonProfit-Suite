<?php
/**
 * Integration Manager
 *
 * Central orchestrator for all third-party integrations.
 * Manages provider registration, activation, switching, and provides
 * a unified interface for modules to interact with integrations.
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
 * NonprofitSuite_Integration_Manager Class
 *
 * Singleton pattern for managing all integrations across the plugin.
 */
class NonprofitSuite_Integration_Manager {

	/**
	 * Singleton instance
	 *
	 * @var NonprofitSuite_Integration_Manager
	 */
	private static $instance = null;

	/**
	 * Registered providers by category
	 *
	 * @var array
	 */
	private $providers = array();

	/**
	 * Active provider instances (cached)
	 *
	 * @var array
	 */
	private $active_instances = array();

	/**
	 * Integration categories
	 *
	 * @var array
	 */
	private $categories = array(
		'storage'     => 'Storage',
		'calendar'    => 'Calendar',
		'email'       => 'Email',
		'accounting'  => 'Accounting',
		'payment'     => 'Payment Processing',
		'crm'         => 'CRM',
		'marketing'   => 'Marketing',
		'video'       => 'Video Conferencing',
		'forms'       => 'Forms & Surveys',
		'project'     => 'Project Management',
		'ai'          => 'AI & Automation',
		'sms'         => 'SMS & Messaging',
		'analytics'   => 'Analytics',
	);

	/**
	 * Get singleton instance
	 *
	 * @return NonprofitSuite_Integration_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor (singleton pattern)
	 */
	private function __construct() {
		$this->register_built_in_providers();
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'load_providers' ), 5 );
	}

	/**
	 * Register built-in providers
	 *
	 * These are the default providers that ship with NonprofitSuite.
	 */
	private function register_built_in_providers() {
		// Storage - Built-in Local Storage
		$this->register_provider( 'storage', 'local', array(
			'name'        => __( 'Built-in Local Storage', 'nonprofitsuite' ),
			'class'       => 'NonprofitSuite_Storage_Local_Adapter',
			'description' => __( 'Store files on your WordPress server (included, no setup required)', 'nonprofitsuite' ),
			'is_default'  => true,
			'is_free'     => true,
		) );

		// Calendar - Built-in Calendar
		$this->register_provider( 'calendar', 'builtin', array(
			'name'        => __( 'Built-in Calendar', 'nonprofitsuite' ),
			'class'       => 'NonprofitSuite_Calendar_Builtin_Adapter',
			'description' => __( 'Simple calendar system (included, no setup required)', 'nonprofitsuite' ),
			'is_default'  => true,
			'is_free'     => true,
		) );

		// Calendar - Google Calendar
		$this->register_provider( 'calendar', 'google', array(
			'name'        => __( 'Google Calendar', 'nonprofitsuite' ),
			'class'       => 'NonprofitSuite_Calendar_Google_Adapter',
			'description' => __( 'Sync with Google Calendar, includes Google Meet integration', 'nonprofitsuite' ),
			'is_free'     => true,
		) );

		// Calendar - Microsoft Outlook Calendar
		$this->register_provider( 'calendar', 'outlook', array(
			'name'        => __( 'Microsoft Outlook Calendar', 'nonprofitsuite' ),
			'class'       => 'NonprofitSuite_Calendar_Outlook_Adapter',
			'description' => __( 'Sync with Outlook Calendar, includes Microsoft Teams integration', 'nonprofitsuite' ),
			'is_free'     => true,
		) );

		// Calendar - Apple iCloud Calendar
		$this->register_provider( 'calendar', 'icloud', array(
			'name'        => __( 'Apple iCloud Calendar', 'nonprofitsuite' ),
			'class'       => 'NonprofitSuite_Calendar_iCloud_Adapter',
			'description' => __( 'Sync with iCloud Calendar using CalDAV protocol', 'nonprofitsuite' ),
			'is_free'     => true,
		) );

		// Email - Built-in WordPress Email
		$this->register_provider( 'email', 'wordpress', array(
			'name'        => __( 'Built-in Email (WordPress)', 'nonprofitsuite' ),
			'class'       => 'NonprofitSuite_Email_WordPress_Adapter',
			'description' => __( 'Uses WordPress wp_mail() function (included, configure SMTP recommended)', 'nonprofitsuite' ),
			'is_default'  => true,
			'is_free'     => true,
		) );

		// Email - Gmail API
		$this->register_provider( 'email', 'gmail', array(
			'name'        => __( 'Gmail API', 'nonprofitsuite' ),
			'class'       => 'NonprofitSuite_Email_Gmail_Adapter',
			'description' => __( 'Send via Gmail with OAuth 2.0, thread tracking, and analytics', 'nonprofitsuite' ),
			'is_free'     => true,
		) );

		// Email - Microsoft Outlook
		$this->register_provider( 'email', 'outlook', array(
			'name'        => __( 'Microsoft Outlook', 'nonprofitsuite' ),
			'class'       => 'NonprofitSuite_Email_Outlook_Adapter',
			'description' => __( 'Send via Outlook/Exchange with Microsoft Graph API', 'nonprofitsuite' ),
			'is_free'     => true,
		) );

		// Email - SendGrid
		$this->register_provider( 'email', 'sendgrid', array(
			'name'        => __( 'SendGrid', 'nonprofitsuite' ),
			'class'       => 'NonprofitSuite_Email_SendGrid_Adapter',
			'description' => __( 'Professional transactional email with advanced tracking and analytics', 'nonprofitsuite' ),
			'is_free'     => false, // Free tier: 100 emails/day
		) );

		// Payment - Square
		$this->register_provider( 'payment', 'square', array(
			'name'        => __( 'Square', 'nonprofitsuite' ),
			'class'       => 'NonprofitSuite_Payment_Square_Adapter',
			'description' => __( 'Online + in-person payments, Square Terminal support, 2.6% + $0.10 per transaction', 'nonprofitsuite' ),
			'is_free'     => false,
		) );

		// Accounting - Built-in Treasury
		$this->register_provider( 'accounting', 'treasury', array(
			'name'        => __( 'Built-in Treasury Module', 'nonprofitsuite' ),
			'class'       => 'NonprofitSuite_Accounting_Treasury_Adapter',
			'description' => __( 'Complete nonprofit accounting system (recommended for most organizations)', 'nonprofitsuite' ),
			'is_default'  => true,
			'is_free'     => true,
			'recommended' => true,
		) );

		// Forms - Built-in Forms
		$this->register_provider( 'forms', 'builtin', array(
			'name'        => __( 'Built-in Forms', 'nonprofitsuite' ),
			'class'       => 'NonprofitSuite_Forms_Builtin_Adapter',
			'description' => __( 'Simple form builder (included)', 'nonprofitsuite' ),
			'is_default'  => true,
			'is_free'     => true,
		) );

		// Payment - Venmo (via Braintree)
		$this->register_provider( 'payment', 'venmo', array(
			'name'        => __( 'Venmo', 'nonprofitsuite' ),
			'class'       => 'NonprofitSuite_Payment_Venmo_Adapter',
			'description' => __( 'Accept Venmo payments via Braintree SDK (requires Braintree account)', 'nonprofitsuite' ),
			'is_free'     => false,
		) );

		// Payment - Zelle (Manual reconciliation)
		$this->register_provider( 'payment', 'zelle', array(
			'name'        => __( 'Zelle', 'nonprofitsuite' ),
			'class'       => 'NonprofitSuite_Payment_Zelle_Adapter',
			'description' => __( 'Manual tracking for Zelle payments (no API available)', 'nonprofitsuite' ),
			'is_free'     => true,
		) );

		// Payment - ACH/eCheck (via Stripe)
		$this->register_provider( 'payment', 'ach', array(
			'name'        => __( 'ACH/eCheck', 'nonprofitsuite' ),
			'class'       => 'NonprofitSuite_Payment_ACH_Adapter',
			'description' => __( 'Bank transfers via Stripe ACH, lower fees (0.8% + $5 cap)', 'nonprofitsuite' ),
			'is_free'     => false,
		) );

		// Accounting - QuickBooks Online API
		$this->register_provider( 'accounting', 'quickbooks', array(
			'name'        => __( 'QuickBooks Online', 'nonprofitsuite' ),
			'class'       => 'NonprofitSuite_Accounting_QuickBooks_Adapter',
			'description' => __( 'Real-time sync with QuickBooks Online via API', 'nonprofitsuite' ),
			'is_free'     => false,
		) );

		// Accounting - Xero API
		$this->register_provider( 'accounting', 'xero', array(
			'name'        => __( 'Xero', 'nonprofitsuite' ),
			'class'       => 'NonprofitSuite_Accounting_Xero_Adapter',
			'description' => __( 'Real-time sync with Xero accounting software', 'nonprofitsuite' ),
			'is_free'     => false,
		) );

		// CRM - DonorPerfect
		$this->register_provider( 'crm', 'donorperfect', array(
			'name'        => __( 'DonorPerfect', 'nonprofitsuite' ),
			'class'       => 'NonprofitSuite_CRM_DonorPerfect_Adapter',
			'description' => __( 'Sync donors and donations with DonorPerfect', 'nonprofitsuite' ),
			'is_free'     => false,
		) );

		// CRM - NeonCRM
		$this->register_provider( 'crm', 'neoncrm', array(
			'name'        => __( 'NeonCRM', 'nonprofitsuite' ),
			'class'       => 'NonprofitSuite_CRM_NeonCRM_Adapter',
			'description' => __( 'Sync donors and donations with NeonCRM', 'nonprofitsuite' ),
			'is_free'     => false,
		) );

		// CRM - Little Green Light
		$this->register_provider( 'crm', 'littlegreenlight', array(
			'name'        => __( 'Little Green Light', 'nonprofitsuite' ),
			'class'       => 'NonprofitSuite_CRM_LittleGreenLight_Adapter',
			'description' => __( 'Sync donors and donations with Little Green Light', 'nonprofitsuite' ),
			'is_free'     => false,
		) );

		// Marketing - Constant Contact
		$this->register_provider( 'marketing', 'constantcontact', array(
			'name'        => __( 'Constant Contact', 'nonprofitsuite' ),
			'class'       => 'NonprofitSuite_Marketing_ConstantContact_Adapter',
			'description' => __( 'Email marketing and campaigns with Constant Contact', 'nonprofitsuite' ),
			'is_free'     => false,
		) );

		// Marketing - Brevo (formerly Sendinblue)
		$this->register_provider( 'marketing', 'brevo', array(
			'name'        => __( 'Brevo', 'nonprofitsuite' ),
			'class'       => 'NonprofitSuite_Marketing_Brevo_Adapter',
			'description' => __( 'Email marketing and transactional emails with Brevo (formerly Sendinblue)', 'nonprofitsuite' ),
			'is_free'     => false, // Free tier: 300 emails/day
		) );

		// Video - Jitsi Meet
		$this->register_provider( 'video', 'jitsi', array(
			'name'        => __( 'Jitsi Meet', 'nonprofitsuite' ),
			'class'       => 'NonprofitSuite_Video_Jitsi_Adapter',
			'description' => __( 'Open-source video conferencing (free, no account required)', 'nonprofitsuite' ),
			'is_free'     => true,
		) );
	}

	/**
	 * Load third-party provider definitions
	 *
	 * This runs after WordPress init, allowing other plugins/themes
	 * to register custom providers.
	 */
	public function load_providers() {
		/**
		 * Allow third-party providers to register
		 *
		 * @param NonprofitSuite_Integration_Manager $manager Integration manager instance
		 */
		do_action( 'ns_register_integration_providers', $this );
	}

	/**
	 * Register a provider
	 *
	 * @param string $category     Category slug (storage, calendar, etc.)
	 * @param string $provider_id  Unique provider ID (e.g., 's3', 'google-calendar')
	 * @param array  $args         Provider arguments
	 *                             - name: Display name
	 *                             - class: Adapter class name
	 *                             - description: Provider description
	 *                             - is_default: Whether this is the default provider (optional)
	 *                             - is_free: Whether provider is free (optional)
	 *                             - recommended: Whether recommended (optional)
	 *                             - requires_pro: Whether requires Pro license (optional)
	 * @return bool True if registered, false on error
	 */
	public function register_provider( $category, $provider_id, $args ) {
		// Validate category
		if ( ! isset( $this->categories[ $category ] ) ) {
			return false;
		}

		// Validate required arguments
		if ( empty( $args['name'] ) || empty( $args['class'] ) ) {
			return false;
		}

		// Initialize category if needed
		if ( ! isset( $this->providers[ $category ] ) ) {
			$this->providers[ $category ] = array();
		}

		// Store provider definition
		$this->providers[ $category ][ $provider_id ] = wp_parse_args( $args, array(
			'name'         => '',
			'class'        => '',
			'description'  => '',
			'is_default'   => false,
			'is_free'      => false,
			'recommended'  => false,
			'requires_pro' => false,
		) );

		return true;
	}

	/**
	 * Get all registered providers for a category
	 *
	 * @param string $category Category slug
	 * @return array Provider definitions
	 */
	public function get_providers( $category ) {
		if ( ! isset( $this->providers[ $category ] ) ) {
			return array();
		}

		return $this->providers[ $category ];
	}

	/**
	 * Get all integration categories
	 *
	 * @return array Categories
	 */
	public function get_categories() {
		return $this->categories;
	}

	/**
	 * Get active provider ID for a category
	 *
	 * @param string $category Category slug
	 * @return string|null Provider ID or null if none active
	 */
	public function get_active_provider_id( $category ) {
		$option_key = "ns_integration_{$category}_provider";
		$provider_id = get_option( $option_key );

		// If no provider set, use default
		if ( empty( $provider_id ) ) {
			$provider_id = $this->get_default_provider_id( $category );
		}

		return $provider_id;
	}

	/**
	 * Get default provider ID for a category
	 *
	 * @param string $category Category slug
	 * @return string|null Default provider ID or null
	 */
	private function get_default_provider_id( $category ) {
		if ( ! isset( $this->providers[ $category ] ) ) {
			return null;
		}

		foreach ( $this->providers[ $category ] as $provider_id => $provider ) {
			if ( ! empty( $provider['is_default'] ) ) {
				return $provider_id;
			}
		}

		return null;
	}

	/**
	 * Get active provider instance (adapter)
	 *
	 * Returns a cached instance of the active adapter for the category.
	 *
	 * @param string $category Category slug
	 * @return object|WP_Error Adapter instance or WP_Error on failure
	 */
	public function get_active_provider( $category ) {
		// Return cached instance if available
		if ( isset( $this->active_instances[ $category ] ) ) {
			return $this->active_instances[ $category ];
		}

		// Get active provider ID
		$provider_id = $this->get_active_provider_id( $category );
		if ( empty( $provider_id ) ) {
			return new WP_Error(
				'no_provider',
				sprintf( __( 'No provider configured for %s', 'nonprofitsuite' ), $category )
			);
		}

		// Get provider definition
		$provider = $this->get_provider( $category, $provider_id );
		if ( ! $provider ) {
			return new WP_Error(
				'invalid_provider',
				sprintf( __( 'Invalid provider: %s', 'nonprofitsuite' ), $provider_id )
			);
		}

		// Check Pro license if required
		if ( ! empty( $provider['requires_pro'] ) && ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error(
				'pro_required',
				__( 'This integration requires a Pro license.', 'nonprofitsuite' )
			);
		}

		// Load adapter class
		$adapter_class = $provider['class'];
		if ( ! class_exists( $adapter_class ) ) {
			return new WP_Error(
				'class_not_found',
				sprintf( __( 'Adapter class not found: %s', 'nonprofitsuite' ), $adapter_class )
			);
		}

		// Instantiate adapter
		try {
			$adapter = new $adapter_class();

			// Verify adapter implements correct interface
			$interface = $this->get_interface_for_category( $category );
			if ( $interface && ! ( $adapter instanceof $interface ) ) {
				return new WP_Error(
					'invalid_adapter',
					sprintf( __( 'Adapter does not implement %s', 'nonprofitsuite' ), $interface )
				);
			}

			// Cache instance
			$this->active_instances[ $category ] = $adapter;

			/**
			 * Fires when a provider instance is created
			 *
			 * @param object $adapter      Adapter instance
			 * @param string $category     Category slug
			 * @param string $provider_id  Provider ID
			 */
			do_action( 'ns_provider_instance_created', $adapter, $category, $provider_id );

			return $adapter;

		} catch ( Exception $e ) {
			return new WP_Error(
				'instantiation_failed',
				sprintf( __( 'Failed to instantiate adapter: %s', 'nonprofitsuite' ), $e->getMessage() )
			);
		}
	}

	/**
	 * Get provider definition
	 *
	 * @param string $category     Category slug
	 * @param string $provider_id  Provider ID
	 * @return array|null Provider definition or null
	 */
	public function get_provider( $category, $provider_id ) {
		if ( ! isset( $this->providers[ $category ][ $provider_id ] ) ) {
			return null;
		}

		return $this->providers[ $category ][ $provider_id ];
	}

	/**
	 * Get interface name for a category
	 *
	 * @param string $category Category slug
	 * @return string|null Interface name or null
	 */
	private function get_interface_for_category( $category ) {
		$interfaces = array(
			'storage'    => 'NonprofitSuite_Storage_Adapter_Interface',
			'calendar'   => 'NonprofitSuite_Calendar_Adapter_Interface',
			'email'      => 'NonprofitSuite_Email_Adapter_Interface',
			'accounting' => 'NonprofitSuite_Accounting_Adapter_Interface',
			'payment'    => 'NonprofitSuite_Payment_Adapter_Interface',
			'crm'        => 'NonprofitSuite_CRM_Adapter_Interface',
			'marketing'  => 'NonprofitSuite_Marketing_Adapter_Interface',
			'video'      => 'NonprofitSuite_Video_Adapter_Interface',
			'forms'      => 'NonprofitSuite_Forms_Adapter_Interface',
			'project'    => 'NonprofitSuite_Project_Adapter_Interface',
			'ai'         => 'NonprofitSuite_AI_Adapter_Interface',
			'sms'        => 'NonprofitSuite_SMS_Adapter_Interface',
			'analytics'  => 'NonprofitSuite_Analytics_Adapter_Interface',
		);

		return isset( $interfaces[ $category ] ) ? $interfaces[ $category ] : null;
	}

	/**
	 * Set active provider for a category
	 *
	 * @param string $category     Category slug
	 * @param string $provider_id  Provider ID
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function set_active_provider( $category, $provider_id ) {
		// Validate category
		if ( ! isset( $this->categories[ $category ] ) ) {
			return new WP_Error( 'invalid_category', __( 'Invalid integration category', 'nonprofitsuite' ) );
		}

		// Validate provider exists
		if ( ! isset( $this->providers[ $category ][ $provider_id ] ) ) {
			return new WP_Error( 'invalid_provider', __( 'Invalid provider ID', 'nonprofitsuite' ) );
		}

		// Get old provider for hooks
		$old_provider_id = $this->get_active_provider_id( $category );

		// Update option
		$option_key = "ns_integration_{$category}_provider";
		update_option( $option_key, $provider_id );

		// Clear cached instance
		unset( $this->active_instances[ $category ] );

		/**
		 * Fires when active provider is changed
		 *
		 * @param string $category         Category slug
		 * @param string $new_provider_id  New provider ID
		 * @param string $old_provider_id  Old provider ID
		 */
		do_action( 'ns_integration_provider_switched', $category, $provider_id, $old_provider_id );

		return true;
	}

	/**
	 * Check if a provider is connected/configured
	 *
	 * @param string $category     Category slug
	 * @param string $provider_id  Provider ID (optional, defaults to active)
	 * @return bool True if connected
	 */
	public function is_provider_connected( $category, $provider_id = null ) {
		if ( null === $provider_id ) {
			$provider_id = $this->get_active_provider_id( $category );
		}

		// Built-in providers are always "connected"
		$provider = $this->get_provider( $category, $provider_id );
		if ( $provider && ! empty( $provider['is_default'] ) ) {
			return true;
		}

		// Check for connection settings
		$connection_key = "ns_integration_{$category}_{$provider_id}_connected";
		return (bool) get_option( $connection_key, false );
	}

	/**
	 * Mark a provider as connected
	 *
	 * @param string $category     Category slug
	 * @param string $provider_id  Provider ID
	 * @param array  $settings     Connection settings to store (optional)
	 * @return bool True on success
	 */
	public function mark_provider_connected( $category, $provider_id, $settings = array() ) {
		$connection_key = "ns_integration_{$category}_{$provider_id}_connected";
		update_option( $connection_key, true );

		// Store encrypted settings if provided
		if ( ! empty( $settings ) ) {
			$this->save_provider_settings( $category, $provider_id, $settings );
		}

		/**
		 * Fires when a provider is successfully connected
		 *
		 * @param string $category     Category slug
		 * @param string $provider_id  Provider ID
		 * @param array  $settings     Connection settings
		 */
		do_action( 'ns_integration_connected', $category, $provider_id, $settings );

		return true;
	}

	/**
	 * Mark a provider as disconnected
	 *
	 * @param string $category     Category slug
	 * @param string $provider_id  Provider ID
	 * @return bool True on success
	 */
	public function mark_provider_disconnected( $category, $provider_id ) {
		$connection_key = "ns_integration_{$category}_{$provider_id}_connected";
		delete_option( $connection_key );

		/**
		 * Fires when a provider is disconnected
		 *
		 * @param string $category     Category slug
		 * @param string $provider_id  Provider ID
		 */
		do_action( 'ns_integration_disconnected', $category, $provider_id );

		return true;
	}

	/**
	 * Save provider settings (encrypted)
	 *
	 * @param string $category     Category slug
	 * @param string $provider_id  Provider ID
	 * @param array  $settings     Settings to save
	 * @return bool True on success
	 */
	public function save_provider_settings( $category, $provider_id, $settings ) {
		// TODO: Implement encryption when NonprofitSuite_Encryption class is available
		// For now, store unencrypted (will be updated in security phase)
		$settings_key = "ns_integration_{$category}_{$provider_id}_settings";
		return update_option( $settings_key, $settings, false ); // Don't autoload
	}

	/**
	 * Get provider settings (decrypted)
	 *
	 * @param string $category     Category slug
	 * @param string $provider_id  Provider ID
	 * @param mixed  $default      Default value if not found
	 * @return array Settings
	 */
	public function get_provider_settings( $category, $provider_id, $default = array() ) {
		$settings_key = "ns_integration_{$category}_{$provider_id}_settings";
		$settings = get_option( $settings_key, $default );

		// TODO: Implement decryption when NonprofitSuite_Encryption class is available

		return $settings;
	}

	/**
	 * Delete provider settings
	 *
	 * @param string $category     Category slug
	 * @param string $provider_id  Provider ID
	 * @return bool True on success
	 */
	public function delete_provider_settings( $category, $provider_id ) {
		$settings_key = "ns_integration_{$category}_{$provider_id}_settings";
		return delete_option( $settings_key );
	}

	/**
	 * Get connection status for all categories
	 *
	 * Useful for dashboard display.
	 *
	 * @return array Status array with category => provider info
	 */
	public function get_all_connection_status() {
		$status = array();

		foreach ( $this->categories as $category => $label ) {
			$provider_id = $this->get_active_provider_id( $category );
			$provider = $this->get_provider( $category, $provider_id );

			$status[ $category ] = array(
				'label'       => $label,
				'provider_id' => $provider_id,
				'provider'    => $provider ? $provider['name'] : __( 'None', 'nonprofitsuite' ),
				'connected'   => $this->is_provider_connected( $category ),
			);
		}

		return $status;
	}
}
