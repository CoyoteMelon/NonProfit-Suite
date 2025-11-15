<?php
/**
 * Analytics Manager
 *
 * Central coordination for analytics operations across all providers.
 * Handles event tracking, metric calculation, and report generation.
 *
 * @package NonprofitSuite
 * @subpackage Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_Analytics_Manager {
	/**
	 * Singleton instance.
	 *
	 * @var NS_Analytics_Manager
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return NS_Analytics_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Hook into WordPress actions for automatic tracking
		add_action( 'wp_footer', array( $this, 'add_tracking_code' ) );
		add_action( 'wp_ajax_ns_track_event', array( $this, 'ajax_track_event' ) );
		add_action( 'wp_ajax_nopriv_ns_track_event', array( $this, 'ajax_track_event' ) );
	}

	/**
	 * Get analytics adapter for a provider.
	 *
	 * @param string $provider Provider name.
	 * @param int    $organization_id Organization ID.
	 * @return NS_Analytics_Adapter|WP_Error Adapter instance or error.
	 */
	public function get_adapter( $provider, $organization_id ) {
		global $wpdb;

		// Get provider settings
		$settings_table = $wpdb->prefix . 'ns_analytics_settings';
		$settings       = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$settings_table} WHERE organization_id = %d AND provider = %s AND is_active = 1",
				$organization_id,
				$provider
			),
			ARRAY_A
		);

		if ( ! $settings ) {
			return new WP_Error( 'provider_not_configured', 'Analytics provider not configured or not active.' );
		}

		// Load adapter
		require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/interface-analytics-adapter.php';

		switch ( $provider ) {
			case 'google_analytics':
				require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/class-google-analytics-adapter.php';
				return new NS_Google_Analytics_Adapter(
					$settings['tracking_id'],
					$settings['api_key'],
					$settings['property_id'],
					$settings['api_secret'] // OAuth access token
				);

			case 'mixpanel':
				require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/class-mixpanel-adapter.php';
				$settings_data = json_decode( $settings['settings'], true );
				return new NS_Mixpanel_Adapter(
					$settings['tracking_id'], // Project token
					$settings['api_secret'],
					$settings_data['service_account'] ?? ''
				);

			case 'segment':
				require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/class-segment-adapter.php';
				return new NS_Segment_Adapter(
					$settings['api_key'] // Write key
				);

			default:
				return new WP_Error( 'invalid_provider', 'Invalid analytics provider.' );
		}
	}

	/**
	 * Track an event across all active providers.
	 *
	 * @param array $event_data Event data.
	 * @return array Results per provider.
	 */
	public function track_event( $event_data ) {
		global $wpdb;

		$organization_id = $event_data['organization_id'] ?? 1;

		// Log event to database
		$event_id = $this->log_event( $event_data );

		// Get all active providers
		$settings_table = $wpdb->prefix . 'ns_analytics_settings';
		$providers      = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT provider FROM {$settings_table} WHERE organization_id = %d AND is_active = 1 AND tracking_enabled = 1",
				$organization_id
			),
			ARRAY_A
		);

		$results = array();

		foreach ( $providers as $provider_row ) {
			$provider = $provider_row['provider'];
			$adapter  = $this->get_adapter( $provider, $organization_id );

			if ( is_wp_error( $adapter ) ) {
				$results[ $provider ] = $adapter;
				continue;
			}

			$result = $adapter->track_event( $event_data );
			$results[ $provider ] = $result;

			// Update synced providers
			if ( ! is_wp_error( $result ) ) {
				$this->update_synced_providers( $event_id, $provider );
			}
		}

		return $results;
	}

	/**
	 * Log event to database.
	 *
	 * @param array $event_data Event data.
	 * @return int Event ID.
	 */
	private function log_event( $event_data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_analytics_events';

		$wpdb->insert(
			$table,
			array(
				'organization_id' => $event_data['organization_id'] ?? 1,
				'event_name'      => $event_data['event_name'],
				'event_category'  => $event_data['event_category'] ?? null,
				'event_action'    => $event_data['event_action'] ?? null,
				'event_label'     => $event_data['event_label'] ?? null,
				'user_id'         => $event_data['user_id'] ?? null,
				'contact_id'      => $event_data['contact_id'] ?? null,
				'session_id'      => $event_data['session_id'] ?? null,
				'event_value'     => $event_data['event_value'] ?? null,
				'properties'      => isset( $event_data['properties'] ) ? wp_json_encode( $event_data['properties'] ) : null,
				'page_url'        => $event_data['page_url'] ?? null,
				'referrer_url'    => $event_data['referrer_url'] ?? null,
				'user_agent'      => $event_data['user_agent'] ?? null,
				'ip_address'      => $event_data['ip_address'] ?? null,
				'device_type'     => $event_data['device_type'] ?? null,
				'browser'         => $event_data['browser'] ?? null,
				'os'              => $event_data['os'] ?? null,
				'country'         => $event_data['country'] ?? null,
				'city'            => $event_data['city'] ?? null,
				'event_timestamp' => isset( $event_data['event_timestamp'] ) ? $event_data['event_timestamp'] : current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $wpdb->insert_id;
	}

	/**
	 * Update synced providers for an event.
	 *
	 * @param int    $event_id Event ID.
	 * @param string $provider Provider name.
	 */
	private function update_synced_providers( $event_id, $provider ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_analytics_events';
		$event = $wpdb->get_row(
			$wpdb->prepare( "SELECT synced_to_providers FROM {$table} WHERE id = %d", $event_id ),
			ARRAY_A
		);

		$synced = json_decode( $event['synced_to_providers'], true ) ?? array();
		$synced[] = $provider;

		$wpdb->update(
			$table,
			array( 'synced_to_providers' => wp_json_encode( array_unique( $synced ) ) ),
			array( 'id' => $event_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Calculate and store metrics.
	 *
	 * @param array $metrics_config Metrics configuration.
	 */
	public function calculate_metrics( $metrics_config ) {
		global $wpdb;

		$organization_id = $metrics_config['organization_id'] ?? 1;
		$time_period     = $metrics_config['time_period'] ?? 'daily';
		$period_date     = $metrics_config['period_date'] ?? date( 'Y-m-d' );

		// Define default metrics
		$metrics = array(
			'total_donations'    => array(
				'type'  => 'sum',
				'query' => "SELECT SUM(event_value) FROM {$wpdb->prefix}ns_analytics_events WHERE organization_id = %d AND event_category = 'donation' AND DATE(event_timestamp) = %s",
			),
			'donation_count'     => array(
				'type'  => 'count',
				'query' => "SELECT COUNT(*) FROM {$wpdb->prefix}ns_analytics_events WHERE organization_id = %d AND event_category = 'donation' AND DATE(event_timestamp) = %s",
			),
			'new_volunteers'     => array(
				'type'  => 'count',
				'query' => "SELECT COUNT(*) FROM {$wpdb->prefix}ns_analytics_events WHERE organization_id = %d AND event_name = 'volunteer_signup' AND DATE(event_timestamp) = %s",
			),
			'active_users'       => array(
				'type'  => 'count',
				'query' => "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}ns_analytics_events WHERE organization_id = %d AND DATE(event_timestamp) = %s AND user_id IS NOT NULL",
			),
			'engagement_rate'    => array(
				'type'  => 'ratio',
				'query' => "SELECT COUNT(*) FROM {$wpdb->prefix}ns_analytics_events WHERE organization_id = %d AND event_category = 'engagement' AND DATE(event_timestamp) = %s",
			),
		);

		foreach ( $metrics as $metric_name => $config ) {
			$value = $wpdb->get_var( $wpdb->prepare( $config['query'], $organization_id, $period_date ) );

			$this->store_metric(
				array(
					'organization_id' => $organization_id,
					'metric_name'     => $metric_name,
					'metric_type'     => $config['type'],
					'metric_category' => 'general',
					'time_period'     => $time_period,
					'period_date'     => $period_date,
					'metric_value'    => floatval( $value ),
				)
			);
		}
	}

	/**
	 * Store a metric.
	 *
	 * @param array $metric_data Metric data.
	 */
	private function store_metric( $metric_data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_analytics_metrics';

		// Get previous value for change calculation
		$previous = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT metric_value FROM {$table} WHERE organization_id = %d AND metric_name = %s AND time_period = %s AND period_date < %s ORDER BY period_date DESC LIMIT 1",
				$metric_data['organization_id'],
				$metric_data['metric_name'],
				$metric_data['time_period'],
				$metric_data['period_date']
			)
		);

		$change_percent = null;
		if ( $previous !== null && $previous > 0 ) {
			$change_percent = ( ( $metric_data['metric_value'] - $previous ) / $previous ) * 100;
		}

		$wpdb->replace(
			$table,
			array(
				'organization_id' => $metric_data['organization_id'],
				'metric_name'     => $metric_data['metric_name'],
				'metric_type'     => $metric_data['metric_type'],
				'metric_category' => $metric_data['metric_category'],
				'time_period'     => $metric_data['time_period'],
				'period_date'     => $metric_data['period_date'],
				'metric_value'    => $metric_data['metric_value'],
				'previous_value'  => $previous,
				'change_percent'  => $change_percent,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f' )
		);
	}

	/**
	 * Add tracking code to footer.
	 */
	public function add_tracking_code() {
		// Add client-side tracking code if needed
		?>
		<script>
		// NonprofitSuite Analytics Tracking
		window.nsAnalytics = {
			trackEvent: function(eventName, properties) {
				if (typeof jQuery !== 'undefined') {
					jQuery.post('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
						action: 'ns_track_event',
						event_name: eventName,
						properties: JSON.stringify(properties)
					});
				}
			}
		};
		</script>
		<?php
	}

	/**
	 * AJAX handler for event tracking.
	 */
	public function ajax_track_event() {
		$event_name = sanitize_text_field( $_POST['event_name'] ?? '' );
		$properties = json_decode( stripslashes( $_POST['properties'] ?? '{}' ), true );

		$event_data = array(
			'event_name' => $event_name,
			'properties' => $properties,
			'user_id'    => get_current_user_id() ?: null,
			'page_url'   => sanitize_text_field( $_SERVER['HTTP_REFERER'] ?? '' ),
			'user_agent' => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
			'ip_address' => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
		);

		$this->track_event( $event_data );

		wp_send_json_success();
	}
}
