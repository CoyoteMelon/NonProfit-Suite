<?php
/**
 * CRM Manager
 *
 * Central coordinator for all CRM integrations.
 * Manages adapters, synchronization, conflict resolution, and logging.
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_CRM_Manager {

	/**
	 * Registered CRM adapters.
	 *
	 * @var array
	 */
	private static $adapters = array();

	/**
	 * Initialize the CRM manager.
	 */
	public static function init() {
		// Register default adapters
		self::register_adapter( 'salesforce', 'NonprofitSuite_Salesforce_Adapter' );
		self::register_adapter( 'hubspot', 'NonprofitSuite_HubSpot_Adapter' );
		self::register_adapter( 'bloomerang', 'NonprofitSuite_Bloomerang_Adapter' );

		// Hook into entity changes for automatic sync
		add_action( 'nonprofitsuite_contact_created', array( __CLASS__, 'sync_contact_on_change' ), 10, 2 );
		add_action( 'nonprofitsuite_contact_updated', array( __CLASS__, 'sync_contact_on_change' ), 10, 2 );
		add_action( 'nonprofitsuite_donation_created', array( __CLASS__, 'sync_donation_on_change' ), 10, 2 );

		// Schedule automated syncs
		add_action( 'nonprofitsuite_crm_hourly_sync', array( __CLASS__, 'run_scheduled_sync' ) );

		if ( ! wp_next_scheduled( 'nonprofitsuite_crm_hourly_sync' ) ) {
			wp_schedule_event( time(), 'hourly', 'nonprofitsuite_crm_hourly_sync' );
		}
	}

	/**
	 * Register a CRM adapter.
	 *
	 * @param string $key Adapter key.
	 * @param string $class_name Adapter class name.
	 */
	public static function register_adapter( $key, $class_name ) {
		self::$adapters[ $key ] = $class_name;
	}

	/**
	 * Get a CRM adapter instance.
	 *
	 * @param string $key Adapter key.
	 * @param int    $org_id Organization ID.
	 * @return NonprofitSuite_CRM_Adapter|null
	 */
	public static function get_adapter( $key, $org_id ) {
		if ( ! isset( self::$adapters[ $key ] ) ) {
			return null;
		}

		$credentials = self::get_crm_credentials( $key, $org_id );

		if ( ! $credentials ) {
			return null;
		}

		$class_name = self::$adapters[ $key ];
		return new $class_name( $credentials );
	}

	/**
	 * Get CRM credentials for an organization.
	 *
	 * @param string $provider CRM provider.
	 * @param int    $org_id Organization ID.
	 * @return array|null
	 */
	public static function get_crm_credentials( $provider, $org_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_crm_settings';

		$setting = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE organization_id = %d AND crm_provider = %s AND is_active = 1",
				$org_id,
				$provider
			),
			ARRAY_A
		);

		if ( ! $setting ) {
			return null;
		}

		$credentials = array(
			'api_key'            => $setting['api_key'],
			'api_secret'         => $setting['api_secret'],
			'api_url'            => $setting['api_url'],
			'access_token'       => $setting['oauth_token'],
			'refresh_token'      => $setting['oauth_refresh_token'],
			'expires_at'         => $setting['oauth_expires_at'],
		);

		// Salesforce-specific
		if ( $provider === 'salesforce' ) {
			$settings_json = json_decode( $setting['settings'], true );
			$credentials['client_id'] = isset( $settings_json['client_id'] ) ? $settings_json['client_id'] : '';
			$credentials['client_secret'] = isset( $settings_json['client_secret'] ) ? $settings_json['client_secret'] : '';
			$credentials['instance_url'] = $setting['api_url'];
		}

		// HubSpot-specific
		if ( $provider === 'hubspot' ) {
			$settings_json = json_decode( $setting['settings'], true );
			$credentials['auth_type'] = isset( $settings_json['auth_type'] ) ? $settings_json['auth_type'] : 'api_key';
			$credentials['client_id'] = isset( $settings_json['client_id'] ) ? $settings_json['client_id'] : '';
			$credentials['client_secret'] = isset( $settings_json['client_secret'] ) ? $settings_json['client_secret'] : '';
		}

		return $credentials;
	}

	/**
	 * Push a contact to CRM.
	 *
	 * @param string $provider CRM provider.
	 * @param int    $org_id Organization ID.
	 * @param int    $contact_id Contact ID.
	 * @return array|WP_Error
	 */
	public static function push_contact( $provider, $org_id, $contact_id ) {
		$adapter = self::get_adapter( $provider, $org_id );

		if ( ! $adapter ) {
			return new WP_Error( 'adapter_not_found', 'CRM adapter not found or not configured' );
		}

		// Get contact data from NS database
		$contact_data = self::get_contact_data( $contact_id, $org_id );

		if ( ! $contact_data ) {
			return new WP_Error( 'contact_not_found', 'Contact not found' );
		}

		// Get field mappings
		$field_mappings = self::get_field_mappings( $provider, $org_id, 'contact' );

		// Push to CRM
		$result = $adapter->push_contact( $contact_data, $field_mappings );

		if ( is_wp_error( $result ) ) {
			self::log_sync( $org_id, $provider, 'push', 'contact', $contact_id, null, 'error', $result->get_error_message() );
			return $result;
		}

		// Log success
		self::log_sync( $org_id, $provider, 'push', 'contact', $contact_id, $result['crm_id'], 'success' );

		return $result;
	}

	/**
	 * Pull a contact from CRM.
	 *
	 * @param string $provider CRM provider.
	 * @param int    $org_id Organization ID.
	 * @param string $crm_id CRM contact ID.
	 * @return array|WP_Error
	 */
	public static function pull_contact( $provider, $org_id, $crm_id ) {
		$adapter = self::get_adapter( $provider, $org_id );

		if ( ! $adapter ) {
			return new WP_Error( 'adapter_not_found', 'CRM adapter not found or not configured' );
		}

		$field_mappings = self::get_field_mappings( $provider, $org_id, 'contact' );

		$result = $adapter->pull_contact( $crm_id, $field_mappings );

		if ( is_wp_error( $result ) ) {
			self::log_sync( $org_id, $provider, 'pull', 'contact', null, $crm_id, 'error', $result->get_error_message() );
			return $result;
		}

		// Save to NS database
		$contact_id = self::save_contact_data( $result, $org_id, $crm_id, $provider );

		self::log_sync( $org_id, $provider, 'pull', 'contact', $contact_id, $crm_id, 'success' );

		return $result;
	}

	/**
	 * Sync contact on change (automatic push).
	 *
	 * @param int   $contact_id Contact ID.
	 * @param array $contact_data Contact data.
	 */
	public static function sync_contact_on_change( $contact_id, $contact_data ) {
		$org_id = isset( $contact_data['organization_id'] ) ? $contact_data['organization_id'] : 0;

		if ( ! $org_id ) {
			return;
		}

		// Get active CRM providers for this organization
		$providers = self::get_active_providers( $org_id );

		foreach ( $providers as $provider ) {
			$settings = self::get_crm_setting( $provider, $org_id );

			// Only sync if mode is 'external' or 'both' and direction allows push
			if ( ! in_array( $settings['crm_mode'], array( 'external', 'both' ), true ) ) {
				continue;
			}

			if ( ! in_array( $settings['sync_direction'], array( 'push', 'bidirectional' ), true ) ) {
				continue;
			}

			// Check if realtime sync is enabled
			if ( $settings['sync_frequency'] === 'realtime' ) {
				self::push_contact( $provider, $org_id, $contact_id );
			}
		}
	}

	/**
	 * Sync donation on change.
	 *
	 * @param int   $donation_id Donation ID.
	 * @param array $donation_data Donation data.
	 */
	public static function sync_donation_on_change( $donation_id, $donation_data ) {
		$org_id = isset( $donation_data['organization_id'] ) ? $donation_data['organization_id'] : 0;

		if ( ! $org_id ) {
			return;
		}

		$providers = self::get_active_providers( $org_id );

		foreach ( $providers as $provider ) {
			$settings = self::get_crm_setting( $provider, $org_id );

			if ( ! in_array( $settings['crm_mode'], array( 'external', 'both' ), true ) ) {
				continue;
			}

			if ( ! in_array( $settings['sync_direction'], array( 'push', 'bidirectional' ), true ) ) {
				continue;
			}

			if ( $settings['sync_frequency'] === 'realtime' ) {
				$adapter = self::get_adapter( $provider, $org_id );
				if ( $adapter ) {
					$field_mappings = self::get_field_mappings( $provider, $org_id, 'donation' );
					$result = $adapter->push_donation( $donation_data, $field_mappings );

					if ( is_wp_error( $result ) ) {
						self::log_sync( $org_id, $provider, 'push', 'donation', $donation_id, null, 'error', $result->get_error_message() );
					} else {
						self::log_sync( $org_id, $provider, 'push', 'donation', $donation_id, $result['crm_id'], 'success' );
					}
				}
			}
		}
	}

	/**
	 * Run scheduled sync.
	 */
	public static function run_scheduled_sync() {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_crm_settings';

		// Get all organizations with hourly or daily sync enabled
		$settings = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE is_active = 1 AND sync_frequency IN ('hourly', 'daily')",
			ARRAY_A
		);

		foreach ( $settings as $setting ) {
			// Check if it's time to sync based on frequency
			$last_sync = strtotime( $setting['last_sync_at'] );
			$frequency = $setting['sync_frequency'];

			$should_sync = false;

			if ( $frequency === 'hourly' && ( time() - $last_sync ) >= 3600 ) {
				$should_sync = true;
			} elseif ( $frequency === 'daily' && ( time() - $last_sync ) >= 86400 ) {
				$should_sync = true;
			}

			if ( $should_sync ) {
				self::sync_organization( $setting['crm_provider'], $setting['organization_id'] );
			}
		}
	}

	/**
	 * Sync all data for an organization.
	 *
	 * @param string $provider CRM provider.
	 * @param int    $org_id Organization ID.
	 * @return bool|WP_Error
	 */
	public static function sync_organization( $provider, $org_id ) {
		global $wpdb;

		$adapter = self::get_adapter( $provider, $org_id );

		if ( ! $adapter ) {
			return new WP_Error( 'adapter_not_found', 'CRM adapter not found' );
		}

		$settings = self::get_crm_setting( $provider, $org_id );

		if ( ! $settings ) {
			return new WP_Error( 'settings_not_found', 'CRM settings not found' );
		}

		// Update sync status
		$wpdb->update(
			$wpdb->prefix . 'ns_crm_settings',
			array( 'sync_status' => 'syncing' ),
			array(
				'crm_provider'    => $provider,
				'organization_id' => $org_id,
			)
		);

		// Sync contacts if direction allows
		if ( in_array( $settings['sync_direction'], array( 'pull', 'bidirectional' ), true ) ) {
			$since = $settings['last_sync_at'] ? $settings['last_sync_at'] : gmdate( 'Y-m-d H:i:s', strtotime( '-1 year' ) );
			$changes = $adapter->get_changes_since( 'contact', $since );

			if ( ! is_wp_error( $changes ) ) {
				foreach ( $changes as $change ) {
					self::pull_contact( $provider, $org_id, $change['Id'] );
				}
			}
		}

		// Update last sync time
		$wpdb->update(
			$wpdb->prefix . 'ns_crm_settings',
			array(
				'sync_status'  => 'idle',
				'last_sync_at' => current_time( 'mysql', 1 ),
			),
			array(
				'crm_provider'    => $provider,
				'organization_id' => $org_id,
			)
		);

		return true;
	}

	/**
	 * Log a sync operation.
	 *
	 * @param int    $org_id Organization ID.
	 * @param string $provider CRM provider.
	 * @param string $direction Sync direction (push/pull).
	 * @param string $entity_type Entity type.
	 * @param int    $entity_id NS entity ID.
	 * @param string $crm_entity_id CRM entity ID.
	 * @param string $status Sync status (success/error/conflict).
	 * @param string $error_message Optional error message.
	 */
	public static function log_sync( $org_id, $provider, $direction, $entity_type, $entity_id, $crm_entity_id, $status, $error_message = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_crm_sync_log';

		$sync_action = $crm_entity_id ? 'update' : 'create';

		$wpdb->insert(
			$table,
			array(
				'organization_id' => $org_id,
				'crm_provider'    => $provider,
				'sync_direction'  => $direction,
				'entity_type'     => $entity_type,
				'entity_id'       => $entity_id,
				'crm_entity_id'   => $crm_entity_id,
				'sync_action'     => $sync_action,
				'sync_status'     => $status,
				'error_message'   => $error_message,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get field mappings for an entity type.
	 *
	 * @param string $provider CRM provider.
	 * @param int    $org_id Organization ID.
	 * @param string $entity_type Entity type.
	 * @return array
	 */
	public static function get_field_mappings( $provider, $org_id, $entity_type ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_crm_field_mappings';

		$mappings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE organization_id = %d AND crm_provider = %s AND entity_type = %s AND is_active = 1",
				$org_id,
				$provider,
				$entity_type
			),
			ARRAY_A
		);

		return $mappings ? $mappings : array();
	}

	/**
	 * Get active CRM providers for an organization.
	 *
	 * @param int $org_id Organization ID.
	 * @return array
	 */
	public static function get_active_providers( $org_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_crm_settings';

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT crm_provider FROM {$table} WHERE organization_id = %d AND is_active = 1",
				$org_id
			)
		);
	}

	/**
	 * Get CRM setting.
	 *
	 * @param string $provider CRM provider.
	 * @param int    $org_id Organization ID.
	 * @return array|null
	 */
	public static function get_crm_setting( $provider, $org_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_crm_settings';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE organization_id = %d AND crm_provider = %s",
				$org_id,
				$provider
			),
			ARRAY_A
		);
	}

	/**
	 * Get contact data from NS database.
	 *
	 * @param int $contact_id Contact ID.
	 * @param int $org_id Organization ID.
	 * @return array|null
	 */
	private static function get_contact_data( $contact_id, $org_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_people';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d AND organization_id = %d",
				$contact_id,
				$org_id
			),
			ARRAY_A
		);
	}

	/**
	 * Save contact data to NS database.
	 *
	 * @param array  $contact_data Contact data.
	 * @param int    $org_id Organization ID.
	 * @param string $crm_id CRM contact ID.
	 * @param string $provider CRM provider.
	 * @return int Contact ID.
	 */
	private static function save_contact_data( $contact_data, $org_id, $crm_id, $provider ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_people';

		// Check if contact already exists
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE organization_id = %d AND email = %s",
				$org_id,
				$contact_data['email']
			)
		);

		$contact_data['organization_id'] = $org_id;

		if ( $existing ) {
			$wpdb->update( $table, $contact_data, array( 'id' => $existing ) );
			return $existing;
		} else {
			$wpdb->insert( $table, $contact_data );
			return $wpdb->insert_id;
		}
	}
}
