<?php
/**
 * SMS Manager
 *
 * Central coordination for SMS operations across all providers.
 * Handles message sending, campaigns, opt-outs, and webhooks.
 *
 * @package NonprofitSuite
 * @subpackage Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_SMS_Manager {
	/**
	 * Singleton instance.
	 *
	 * @var NS_SMS_Manager
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return NS_SMS_Manager
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
		// Register webhook endpoints
		add_action( 'rest_api_init', array( $this, 'register_webhook_endpoints' ) );

		// Schedule campaign sending
		add_action( 'ns_send_sms_campaign', array( $this, 'process_scheduled_campaign' ) );
	}

	/**
	 * Get SMS adapter for a provider.
	 *
	 * @param string $provider Provider name (twilio, plivo, vonage).
	 * @param int    $organization_id Organization ID.
	 * @return NS_SMS_Adapter|WP_Error Adapter instance or error.
	 */
	public function get_adapter( $provider, $organization_id ) {
		global $wpdb;

		// Get provider settings
		$settings_table = $wpdb->prefix . 'ns_sms_settings';
		$settings       = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$settings_table} WHERE organization_id = %d AND provider = %s AND is_active = 1",
				$organization_id,
				$provider
			),
			ARRAY_A
		);

		if ( ! $settings ) {
			return new WP_Error( 'provider_not_configured', 'SMS provider not configured or not active.' );
		}

		// Load adapter
		require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/interface-sms-adapter.php';

		switch ( $provider ) {
			case 'twilio':
				require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/class-twilio-adapter.php';
				return new NS_Twilio_Adapter(
					$settings['account_sid'],
					$settings['api_key'],
					$settings['phone_number']
				);

			case 'plivo':
				require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/class-plivo-adapter.php';
				return new NS_Plivo_Adapter(
					$settings['account_sid'], // Auth ID
					$settings['api_key'],     // Auth Token
					$settings['phone_number']
				);

			case 'vonage':
				require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/class-vonage-adapter.php';
				return new NS_Vonage_Adapter(
					$settings['api_key'],
					$settings['api_secret'],
					$settings['phone_number']
				);

			default:
				return new WP_Error( 'invalid_provider', 'Invalid SMS provider.' );
		}
	}

	/**
	 * Send SMS message.
	 *
	 * @param array $args Message arguments.
	 * @return array|WP_Error Send result.
	 */
	public function send_message( $args ) {
		global $wpdb;

		$defaults = array(
			'organization_id' => 1,
			'contact_id'      => null,
			'to'              => '',
			'message'         => '',
			'provider'        => 'twilio',
			'campaign_id'     => null,
			'message_type'    => 'transactional',
			'from'            => null,
		);

		$args = wp_parse_args( $args, $defaults );

		// Check opt-out
		if ( $this->is_opted_out( $args['to'], $args['organization_id'] ) ) {
			return new WP_Error( 'opted_out', 'Recipient has opted out of SMS messages.' );
		}

		// Get adapter
		$adapter = $this->get_adapter( $args['provider'], $args['organization_id'] );

		if ( is_wp_error( $adapter ) ) {
			return $adapter;
		}

		// Send message
		$options = array();
		if ( $args['from'] ) {
			$options['from'] = $args['from'];
		}

		$result = $adapter->send_message( $args['to'], $args['message'], $options );

		if ( is_wp_error( $result ) ) {
			// Log failed message
			$this->log_message(
				array(
					'organization_id'     => $args['organization_id'],
					'campaign_id'         => $args['campaign_id'],
					'contact_id'          => $args['contact_id'],
					'recipient_phone'     => $args['to'],
					'sender_phone'        => $options['from'] ?? '',
					'message_body'        => $args['message'],
					'message_type'        => $args['message_type'],
					'provider'            => $args['provider'],
					'provider_message_id' => null,
					'status'              => 'failed',
					'error_message'       => $result->get_error_message(),
					'segments'            => 1,
					'cost'                => 0,
				)
			);

			return $result;
		}

		// Log successful message
		$message_id = $this->log_message(
			array(
				'organization_id'     => $args['organization_id'],
				'campaign_id'         => $args['campaign_id'],
				'contact_id'          => $args['contact_id'],
				'recipient_phone'     => $args['to'],
				'sender_phone'        => $options['from'] ?? '',
				'message_body'        => $args['message'],
				'message_type'        => $args['message_type'],
				'provider'            => $args['provider'],
				'provider_message_id' => $result['message_id'],
				'status'              => $result['status'],
				'segments'            => $result['segments'],
				'cost'                => $result['price'] ?? 0,
				'sent_at'             => current_time( 'mysql' ),
			)
		);

		// Update monthly count and cost
		$this->update_monthly_stats( $args['organization_id'], $args['provider'], $result['segments'], $result['price'] ?? 0 );

		return array(
			'success'    => true,
			'message_id' => $message_id,
			'provider_message_id' => $result['message_id'],
			'status'     => $result['status'],
			'cost'       => $result['price'] ?? 0,
		);
	}

	/**
	 * Send bulk SMS messages.
	 *
	 * @param array $recipients Array of phone numbers or contacts.
	 * @param string $message Message body.
	 * @param array  $args Additional arguments.
	 * @return array Results.
	 */
	public function send_bulk( $recipients, $message, $args = array() ) {
		$results = array(
			'sent'   => 0,
			'failed' => 0,
			'errors' => array(),
		);

		foreach ( $recipients as $recipient ) {
			$phone = is_array( $recipient ) ? $recipient['phone'] : $recipient;
			$contact_id = is_array( $recipient ) ? $recipient['contact_id'] : null;

			$send_args = array_merge(
				$args,
				array(
					'to'         => $phone,
					'message'    => $message,
					'contact_id' => $contact_id,
				)
			);

			$result = $this->send_message( $send_args );

			if ( is_wp_error( $result ) ) {
				$results['failed']++;
				$results['errors'][ $phone ] = $result->get_error_message();
			} else {
				$results['sent']++;
			}

			// Small delay to avoid rate limiting
			usleep( 100000 ); // 0.1 seconds
		}

		return $results;
	}

	/**
	 * Create SMS campaign.
	 *
	 * @param array $args Campaign arguments.
	 * @return int|WP_Error Campaign ID or error.
	 */
	public function create_campaign( $args ) {
		global $wpdb;

		$defaults = array(
			'organization_id' => 1,
			'campaign_name'   => '',
			'message_body'    => '',
			'campaign_type'   => 'one_time',
			'target_segment'  => 'all',
			'segment_filter'  => null,
			'provider'        => 'twilio',
			'status'          => 'draft',
			'scheduled_at'    => null,
			'created_by'      => get_current_user_id(),
		);

		$args = wp_parse_args( $args, $defaults );

		$table = $wpdb->prefix . 'ns_sms_campaigns';

		$wpdb->insert(
			$table,
			array(
				'organization_id' => $args['organization_id'],
				'campaign_name'   => $args['campaign_name'],
				'message_body'    => $args['message_body'],
				'campaign_type'   => $args['campaign_type'],
				'target_segment'  => $args['target_segment'],
				'segment_filter'  => is_array( $args['segment_filter'] ) ? wp_json_encode( $args['segment_filter'] ) : $args['segment_filter'],
				'provider'        => $args['provider'],
				'status'          => $args['status'],
				'scheduled_at'    => $args['scheduled_at'],
				'created_by'      => $args['created_by'],
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		$campaign_id = $wpdb->insert_id;

		// Schedule campaign if scheduled_at is set
		if ( $args['scheduled_at'] && $args['status'] === 'scheduled' ) {
			wp_schedule_single_event(
				strtotime( $args['scheduled_at'] ),
				'ns_send_sms_campaign',
				array( $campaign_id )
			);
		}

		return $campaign_id;
	}

	/**
	 * Send campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array|WP_Error Send results.
	 */
	public function send_campaign( $campaign_id ) {
		global $wpdb;

		$table    = $wpdb->prefix . 'ns_sms_campaigns';
		$campaign = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $campaign_id ),
			ARRAY_A
		);

		if ( ! $campaign ) {
			return new WP_Error( 'campaign_not_found', 'Campaign not found.' );
		}

		// Update status to sending
		$wpdb->update(
			$table,
			array(
				'status'     => 'sending',
				'started_at' => current_time( 'mysql' ),
			),
			array( 'id' => $campaign_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		// Get recipients based on segment
		$recipients = $this->get_campaign_recipients( $campaign );

		// Send to all recipients
		$results = $this->send_bulk(
			$recipients,
			$campaign['message_body'],
			array(
				'organization_id' => $campaign['organization_id'],
				'provider'        => $campaign['provider'],
				'campaign_id'     => $campaign_id,
				'message_type'    => 'marketing',
			)
		);

		// Update campaign stats
		$wpdb->update(
			$table,
			array(
				'status'           => 'sent',
				'completed_at'     => current_time( 'mysql' ),
				'total_recipients' => count( $recipients ),
				'total_sent'       => $results['sent'],
				'total_failed'     => $results['failed'],
			),
			array( 'id' => $campaign_id ),
			array( '%s', '%s', '%d', '%d', '%d' ),
			array( '%d' )
		);

		return $results;
	}

	/**
	 * Process scheduled campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 */
	public function process_scheduled_campaign( $campaign_id ) {
		$this->send_campaign( $campaign_id );
	}

	/**
	 * Get campaign recipients.
	 *
	 * @param array $campaign Campaign data.
	 * @return array Recipients.
	 */
	private function get_campaign_recipients( $campaign ) {
		global $wpdb;

		$contacts_table = $wpdb->prefix . 'ns_contacts';
		$recipients     = array();

		// Base query
		$query = "SELECT id as contact_id, phone FROM {$contacts_table} WHERE organization_id = %d AND phone IS NOT NULL AND phone != ''";
		$params = array( $campaign['organization_id'] );

		// Apply segment filter
		if ( $campaign['target_segment'] !== 'all' ) {
			$query .= " AND contact_type = %s";
			$params[] = $campaign['target_segment'];
		}

		// Exclude opted-out numbers
		$optouts_table = $wpdb->prefix . 'ns_sms_optouts';
		$query .= " AND phone NOT IN (SELECT phone_number FROM {$optouts_table} WHERE organization_id = %d AND is_active = 1)";
		$params[] = $campaign['organization_id'];

		$results = $wpdb->get_results( $wpdb->prepare( $query, $params ), ARRAY_A );

		foreach ( $results as $result ) {
			$recipients[] = array(
				'phone'      => $result['phone'],
				'contact_id' => $result['contact_id'],
			);
		}

		return $recipients;
	}

	/**
	 * Log SMS message.
	 *
	 * @param array $args Message data.
	 * @return int Message ID.
	 */
	private function log_message( $args ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_sms_messages';

		$wpdb->insert(
			$table,
			$args,
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s' )
		);

		return $wpdb->insert_id;
	}

	/**
	 * Update monthly statistics.
	 *
	 * @param int    $organization_id Organization ID.
	 * @param string $provider Provider name.
	 * @param int    $segments Number of segments.
	 * @param float  $cost Cost.
	 */
	private function update_monthly_stats( $organization_id, $provider, $segments, $cost ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_sms_settings';

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET current_month_count = current_month_count + %d, current_month_cost = current_month_cost + %f WHERE organization_id = %d AND provider = %s",
				$segments,
				$cost,
				$organization_id,
				$provider
			)
		);
	}

	/**
	 * Check if phone number is opted out.
	 *
	 * @param string $phone Phone number.
	 * @param int    $organization_id Organization ID.
	 * @return bool True if opted out.
	 */
	public function is_opted_out( $phone, $organization_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_sms_optouts';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE organization_id = %d AND phone_number = %s AND is_active = 1",
				$organization_id,
				$phone
			)
		);

		return $count > 0;
	}

	/**
	 * Add phone number to opt-out list.
	 *
	 * @param string $phone Phone number.
	 * @param int    $organization_id Organization ID.
	 * @param array  $args Additional arguments.
	 */
	public function add_optout( $phone, $organization_id, $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_sms_optouts';

		$wpdb->replace(
			$table,
			array(
				'organization_id' => $organization_id,
				'phone_number'    => $phone,
				'contact_id'      => $args['contact_id'] ?? null,
				'opt_out_type'    => $args['opt_out_type'] ?? 'stop',
				'opt_out_message' => $args['opt_out_message'] ?? null,
				'opt_out_source'  => $args['opt_out_source'] ?? 'manual',
				'is_active'       => 1,
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s', '%d' )
		);
	}

	/**
	 * Register webhook endpoints.
	 */
	public function register_webhook_endpoints() {
		register_rest_route(
			'nonprofitsuite/v1',
			'/sms/webhook/twilio',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_twilio_webhook' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'nonprofitsuite/v1',
			'/sms/webhook/plivo',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_plivo_webhook' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'nonprofitsuite/v1',
			'/sms/webhook/vonage',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_vonage_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle Twilio webhook.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function handle_twilio_webhook( $request ) {
		$payload = $request->get_params();

		// Process webhook data
		$this->process_delivery_receipt( 'twilio', $payload );

		// Check for STOP/START keywords (opt-out/opt-in)
		if ( isset( $payload['Body'] ) ) {
			$body = strtoupper( trim( $payload['Body'] ) );
			if ( in_array( $body, array( 'STOP', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT' ), true ) ) {
				$this->add_optout( $payload['From'], 1 ); // Organization ID would need to be determined from context
			}
		}

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Handle Plivo webhook.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function handle_plivo_webhook( $request ) {
		$payload = $request->get_params();

		$this->process_delivery_receipt( 'plivo', $payload );

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Handle Vonage webhook.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function handle_vonage_webhook( $request ) {
		$payload = $request->get_params();

		$this->process_delivery_receipt( 'vonage', $payload );

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Process delivery receipt from webhook.
	 *
	 * @param string $provider Provider name.
	 * @param array  $payload Webhook payload.
	 */
	private function process_delivery_receipt( $provider, $payload ) {
		global $wpdb;

		// Get adapter to process payload
		require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/interface-sms-adapter.php';

		$adapter = null;
		switch ( $provider ) {
			case 'twilio':
				require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/class-twilio-adapter.php';
				$adapter = new NS_Twilio_Adapter( '', '', '' ); // Dummy instance for webhook processing
				break;
			case 'plivo':
				require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/class-plivo-adapter.php';
				$adapter = new NS_Plivo_Adapter( '', '', '' );
				break;
			case 'vonage':
				require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/class-vonage-adapter.php';
				$adapter = new NS_Vonage_Adapter( '', '', '' );
				break;
		}

		if ( ! $adapter ) {
			return;
		}

		$processed = $adapter->process_webhook( $payload );

		// Update message status
		$table = $wpdb->prefix . 'ns_sms_messages';
		$wpdb->update(
			$table,
			array(
				'status'       => $processed['status'],
				'delivered_at' => ( $processed['status'] === 'delivered' ) ? current_time( 'mysql' ) : null,
			),
			array( 'provider_message_id' => $processed['message_id'] ),
			array( '%s', '%s' ),
			array( '%s' )
		);
	}
}
