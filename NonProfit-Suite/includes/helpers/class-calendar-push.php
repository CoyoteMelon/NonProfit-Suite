<?php
/**
 * Multi-Provider Calendar Push Service
 *
 * Pushes calendar events from NonprofitSuite database to multiple
 * external calendar providers based on organizational and user preferences.
 *
 * @package    NonprofitSuite
 * @subpackage Helpers
 * @since      1.4.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonprofitSuite_Calendar_Push Class
 *
 * Manages pushing events to multiple calendar providers.
 */
class NonprofitSuite_Calendar_Push {

	/**
	 * Push an event to all relevant calendar providers.
	 *
	 * Determines which providers to push to based on:
	 * - Organizational default provider (for organizational calendars)
	 * - Individual user preferences (for personal calendars)
	 *
	 * @param int|array $event Event ID or event data array.
	 * @return array Results array with successes and errors.
	 */
	public static function push_event( $event ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_calendar_events';

		// If event is an ID, fetch full data
		if ( is_numeric( $event ) ) {
			$event_id = $event;
			$event = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$event_id
			), ARRAY_A );

			if ( ! $event ) {
				return array(
					'success' => false,
					'error'   => __( 'Event not found', 'nonprofitsuite' ),
				);
			}
		} else {
			$event_id = $event['id'];
		}

		// Get visible calendars
		$visible_calendars = ! empty( $event['visible_on_calendars'] )
			? json_decode( $event['visible_on_calendars'], true )
			: NonprofitSuite_Calendar_Visibility::calculate_visibility( $event );

		// Get Integration Manager
		$integration_manager = NonprofitSuite_Integration_Manager::get_instance();

		// Track push results
		$pushed_to_providers = ! empty( $event['pushed_to_providers'] )
			? json_decode( $event['pushed_to_providers'], true )
			: array();

		$results = array(
			'pushed'  => array(),
			'skipped' => array(),
			'errors'  => array(),
		);

		// Determine which providers to push to
		$providers_to_push = self::determine_providers( $visible_calendars );

		foreach ( $providers_to_push as $provider_key => $provider_info ) {
			// Get adapter for this provider
			$adapter = self::get_provider_adapter(
				$provider_info['type'],
				$provider_info['provider_id'],
				$provider_info['user_id']
			);

			if ( is_wp_error( $adapter ) ) {
				$results['errors'][] = array(
					'provider' => $provider_key,
					'error'    => $adapter->get_error_message(),
				);
				continue;
			}

			// Check if already pushed to this provider
			$external_id = isset( $pushed_to_providers[ $provider_key ] )
				? $pushed_to_providers[ $provider_key ]
				: null;

			// Prepare event data for external provider
			$external_event_data = self::prepare_event_data_for_provider( $event, $provider_info );

			// Push or update event
			if ( $external_id ) {
				// Update existing event
				$result = $adapter->update_event( $external_id, $external_event_data );
			} else {
				// Create new event
				$result = $adapter->create_event( $external_event_data );
			}

			if ( is_wp_error( $result ) ) {
				$results['errors'][] = array(
					'provider' => $provider_key,
					'error'    => $result->get_error_message(),
				);
			} else {
				// Store external ID
				$pushed_to_providers[ $provider_key ] = isset( $result['event_id'] )
					? $result['event_id']
					: $result['id'];

				$results['pushed'][] = $provider_key;
			}
		}

		// Update database with push results
		$wpdb->update(
			$table,
			array(
				'pushed_to_providers' => wp_json_encode( $pushed_to_providers ),
				'last_pushed_at'      => current_time( 'mysql' ),
			),
			array( 'id' => $event_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		/**
		 * Fires after event is pushed to providers.
		 *
		 * @param int   $event_id Event ID.
		 * @param array $results  Push results.
		 */
		do_action( 'ns_calendar_event_pushed', $event_id, $results );

		return $results;
	}

	/**
	 * Delete an event from all providers it was pushed to.
	 *
	 * @param int|array $event Event ID or event data.
	 * @return array Results array.
	 */
	public static function delete_event_from_providers( $event ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_calendar_events';

		// If event is an ID, fetch data
		if ( is_numeric( $event ) ) {
			$event = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$event
			), ARRAY_A );

			if ( ! $event ) {
				return array(
					'success' => false,
					'error'   => __( 'Event not found', 'nonprofitsuite' ),
				);
			}
		}

		$pushed_to_providers = ! empty( $event['pushed_to_providers'] )
			? json_decode( $event['pushed_to_providers'], true )
			: array();

		$results = array(
			'deleted' => array(),
			'errors'  => array(),
		);

		foreach ( $pushed_to_providers as $provider_key => $external_id ) {
			// Parse provider key (e.g., 'google_user_5', 'outlook_committee_3')
			$provider_info = self::parse_provider_key( $provider_key );

			// Get adapter
			$adapter = self::get_provider_adapter(
				$provider_info['type'],
				$provider_info['provider_id'],
				$provider_info['user_id']
			);

			if ( is_wp_error( $adapter ) ) {
				$results['errors'][] = array(
					'provider' => $provider_key,
					'error'    => $adapter->get_error_message(),
				);
				continue;
			}

			// Delete from provider
			$result = $adapter->delete_event( $external_id );

			if ( is_wp_error( $result ) ) {
				$results['errors'][] = array(
					'provider' => $provider_key,
					'error'    => $result->get_error_message(),
				);
			} else {
				$results['deleted'][] = $provider_key;
			}
		}

		return $results;
	}

	/**
	 * Determine which providers to push to based on visible calendars.
	 *
	 * @param array $visible_calendars Array of calendar identifiers.
	 * @return array Array of providers to push to.
	 */
	private static function determine_providers( $visible_calendars ) {
		$providers = array();
		$integration_manager = NonprofitSuite_Integration_Manager::get_instance();

		// Get organizational default provider for organizational calendars
		$org_provider_id = $integration_manager->get_active_provider_id( 'calendar' );

		foreach ( $visible_calendars as $calendar_id ) {
			// Parse calendar ID (e.g., 'user_5', 'committee_3', 'board', 'public')
			if ( strpos( $calendar_id, 'user_' ) === 0 ) {
				// User calendar - use user's preferred provider
				$user_id = str_replace( 'user_', '', $calendar_id );
				$user_pref = self::get_user_calendar_preference( $user_id );

				if ( $user_pref && $user_pref['auto_sync_enabled'] && $user_pref['preferred_provider'] !== 'builtin' ) {
					$provider_key = $user_pref['preferred_provider'] . '_user_' . $user_id;
					$providers[ $provider_key ] = array(
						'type'        => 'user',
						'provider_id' => $user_pref['preferred_provider'],
						'user_id'     => $user_id,
						'calendar_id' => $calendar_id,
					);
				}
			} else {
				// Organizational calendar (committee, board, public) - use org provider
				if ( $org_provider_id !== 'builtin' ) {
					$provider_key = $org_provider_id . '_' . $calendar_id;
					$providers[ $provider_key ] = array(
						'type'        => 'organizational',
						'provider_id' => $org_provider_id,
						'user_id'     => null,
						'calendar_id' => $calendar_id,
					);
				}
			}
		}

		return $providers;
	}

	/**
	 * Get adapter for a specific provider.
	 *
	 * @param string   $type        Provider type ('user' or 'organizational').
	 * @param string   $provider_id Provider identifier (google, outlook, icloud).
	 * @param int|null $user_id     User ID for user-specific providers.
	 * @return object|WP_Error Adapter instance or error.
	 */
	private static function get_provider_adapter( $type, $provider_id, $user_id = null ) {
		$integration_manager = NonprofitSuite_Integration_Manager::get_instance();

		// For user-specific providers, load user's credentials
		if ( $type === 'user' && $user_id ) {
			$user_pref = self::get_user_calendar_preference( $user_id );

			if ( ! $user_pref || ! $user_pref['auto_sync_enabled'] ) {
				return new WP_Error(
					'user_sync_disabled',
					__( 'User calendar sync is disabled', 'nonprofitsuite' )
				);
			}

			// Get provider class
			$provider = $integration_manager->get_provider( 'calendar', $provider_id );
			if ( ! $provider ) {
				return new WP_Error( 'invalid_provider', __( 'Invalid provider', 'nonprofitsuite' ) );
			}

			$adapter_class = $provider['class'];
			if ( ! class_exists( $adapter_class ) ) {
				return new WP_Error( 'class_not_found', __( 'Adapter class not found', 'nonprofitsuite' ) );
			}

			// Instantiate adapter with user settings
			$adapter = new $adapter_class();

			// Load user-specific credentials if available
			if ( ! empty( $user_pref['provider_settings'] ) ) {
				$settings = is_string( $user_pref['provider_settings'] )
					? json_decode( $user_pref['provider_settings'], true )
					: $user_pref['provider_settings'];

				// Apply settings to adapter (method varies by adapter)
				if ( method_exists( $adapter, 'load_credentials' ) ) {
					$adapter->load_credentials( $settings );
				} elseif ( method_exists( $adapter, 'save_credentials' ) ) {
					$adapter->save_credentials( $settings );
				}
			}

			return $adapter;
		}

		// For organizational providers, use default adapter
		return $integration_manager->get_active_provider( 'calendar' );
	}

	/**
	 * Get user's calendar preference.
	 *
	 * @param int $user_id User ID.
	 * @return array|null User preference or null.
	 */
	private static function get_user_calendar_preference( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_user_calendar_prefs';

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE user_id = %d",
			$user_id
		), ARRAY_A );
	}

	/**
	 * Prepare event data for external provider.
	 *
	 * @param array $event         Event data from database.
	 * @param array $provider_info Provider information.
	 * @return array Event data formatted for provider.
	 */
	private static function prepare_event_data_for_provider( $event, $provider_info ) {
		$event_data = array(
			'title'       => $event['title'],
			'description' => $event['description'],
			'location'    => $event['location'],
			'all_day'     => (bool) $event['all_day'],
		);

		// Use appropriate date based on entity type
		if ( ! empty( $event['entity_type'] ) && $event['entity_type'] === 'task' ) {
			// For tasks, use due_date if available
			if ( ! empty( $event['due_date'] ) ) {
				$event_data['start_datetime'] = $event['due_date'];
				$event_data['end_datetime']   = $event['due_date'];
			}
		} else {
			// For meetings/events, use start/end datetime
			$event_data['start_datetime'] = $event['start_datetime'];
			$event_data['end_datetime']   = $event['end_datetime'];
		}

		// Add attendees if present
		if ( ! empty( $event['attendees'] ) ) {
			$attendees = is_string( $event['attendees'] )
				? json_decode( $event['attendees'], true )
				: $event['attendees'];
			$event_data['attendees'] = $attendees;
		}

		/**
		 * Filter event data before pushing to provider.
		 *
		 * @param array $event_data    Prepared event data.
		 * @param array $event         Original event data.
		 * @param array $provider_info Provider information.
		 */
		return apply_filters( 'ns_calendar_push_event_data', $event_data, $event, $provider_info );
	}

	/**
	 * Parse provider key into components.
	 *
	 * @param string $provider_key Provider key (e.g., 'google_user_5').
	 * @return array Provider info.
	 */
	private static function parse_provider_key( $provider_key ) {
		$parts = explode( '_', $provider_key, 3 );

		$info = array(
			'provider_id' => $parts[0],
			'type'        => isset( $parts[1] ) ? $parts[1] : 'organizational',
			'user_id'     => null,
		);

		if ( $info['type'] === 'user' && isset( $parts[2] ) ) {
			$info['user_id'] = (int) $parts[2];
		}

		return $info;
	}

	/**
	 * Bulk push multiple events.
	 *
	 * @param array $event_ids Array of event IDs.
	 * @return array Combined results.
	 */
	public static function bulk_push_events( $event_ids ) {
		$combined_results = array(
			'total_pushed'  => 0,
			'total_errors'  => 0,
			'total_skipped' => 0,
		);

		foreach ( $event_ids as $event_id ) {
			$results = self::push_event( $event_id );

			$combined_results['total_pushed']  += count( $results['pushed'] );
			$combined_results['total_errors']  += count( $results['errors'] );
			$combined_results['total_skipped'] += count( $results['skipped'] );
		}

		return $combined_results;
	}
}
