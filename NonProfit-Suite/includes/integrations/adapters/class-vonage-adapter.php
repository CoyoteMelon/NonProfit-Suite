<?php
/**
 * Vonage SMS Adapter
 *
 * Integrates with Vonage (formerly Nexmo) SMS API for sending and receiving messages.
 *
 * @package NonprofitSuite
 * @subpackage Integrations/Adapters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_Vonage_Adapter implements NS_SMS_Adapter {
	/**
	 * Vonage API Key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Vonage API Secret.
	 *
	 * @var string
	 */
	private $api_secret;

	/**
	 * Default from name/number.
	 *
	 * @var string
	 */
	private $from_number;

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private $api_base = 'https://rest.nexmo.com';

	/**
	 * Constructor.
	 *
	 * @param string $api_key Vonage API Key.
	 * @param string $api_secret Vonage API Secret.
	 * @param string $from_number Default sending phone number or brand name.
	 */
	public function __construct( $api_key, $api_secret, $from_number = '' ) {
		$this->api_key     = $api_key;
		$this->api_secret  = $api_secret;
		$this->from_number = $from_number;
	}

	/**
	 * Send a single SMS message.
	 *
	 * @param string $to Recipient phone number (E.164 format).
	 * @param string $message Message body.
	 * @param array  $options Optional parameters.
	 * @return array|WP_Error Response with message_id and status.
	 */
	public function send_message( $to, $message, $options = array() ) {
		$from = $options['from'] ?? $this->from_number;

		if ( empty( $from ) ) {
			return new WP_Error( 'missing_from', 'From number/name is required.' );
		}

		$body = array(
			'api_key'    => $this->api_key,
			'api_secret' => $this->api_secret,
			'from'       => $from,
			'to'         => ltrim( $to, '+' ), // Vonage doesn't want the + prefix
			'text'       => $message,
		);

		// Optional parameters
		if ( isset( $options['callback'] ) ) {
			$body['callback'] = $options['callback'];
		}

		$response = $this->api_request(
			'/sms/json',
			'POST',
			$body
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Vonage returns an array of messages
		$message_data = $response['messages'][0] ?? array();

		if ( $message_data['status'] !== '0' ) {
			return new WP_Error(
				'vonage_error',
				$message_data['error-text'] ?? 'Failed to send message',
				array( 'status' => $message_data['status'] )
			);
		}

		return array(
			'message_id' => $message_data['message-id'],
			'status'     => 'queued',
			'segments'   => intval( $message_data['message-count'] ?? 1 ),
			'price'      => floatval( $message_data['message-price'] ?? 0 ),
			'raw'        => $message_data,
		);
	}

	/**
	 * Send bulk SMS messages.
	 *
	 * @param array  $recipients Array of phone numbers.
	 * @param string $message Message body.
	 * @param array  $options Optional parameters.
	 * @return array|WP_Error Array of results per recipient.
	 */
	public function send_bulk( $recipients, $message, $options = array() ) {
		$results = array();

		foreach ( $recipients as $recipient ) {
			$result = $this->send_message( $recipient, $message, $options );

			$results[ $recipient ] = $result;

			// Add small delay to avoid rate limiting
			usleep( 100000 ); // 0.1 seconds
		}

		return $results;
	}

	/**
	 * Get message status.
	 *
	 * @param string $message_id Vonage message ID.
	 * @return array|WP_Error Message status information.
	 */
	public function get_message_status( $message_id ) {
		$response = $this->api_request(
			'/search/message',
			'GET',
			array(
				'api_key'    => $this->api_key,
				'api_secret' => $this->api_secret,
				'id'         => $message_id,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'message_id'    => $response['message-id'] ?? $message_id,
			'status'        => $this->map_status( $response['status'] ?? 'unknown' ),
			'error_code'    => $response['error-code'] ?? null,
			'error_message' => $response['error-text'] ?? null,
			'price'         => floatval( $response['price'] ?? 0 ),
			'sent_at'       => $response['date-sent'] ?? null,
			'delivered_at'  => $response['date-finalized'] ?? null,
		);
	}

	/**
	 * Get account balance.
	 *
	 * @return array|WP_Error Balance information.
	 */
	public function get_balance() {
		$response = $this->api_request(
			'/account/get-balance',
			'GET',
			array(
				'api_key'    => $this->api_key,
				'api_secret' => $this->api_secret,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'balance'  => floatval( $response['value'] ?? 0 ),
			'currency' => 'EUR', // Vonage reports in EUR
		);
	}

	/**
	 * Search available phone numbers.
	 *
	 * @param string $country_code Country code (e.g., 'US').
	 * @param array  $filters Optional filters.
	 * @return array|WP_Error Available numbers.
	 */
	public function search_available_numbers( $country_code, $filters = array() ) {
		$params = array(
			'api_key'    => $this->api_key,
			'api_secret' => $this->api_secret,
			'country'    => $country_code,
		);

		if ( isset( $filters['pattern'] ) ) {
			$params['pattern'] = $filters['pattern'];
		}

		if ( isset( $filters['features'] ) ) {
			$params['features'] = $filters['features'];
		} else {
			$params['features'] = 'SMS'; // Default to SMS-enabled numbers
		}

		$response = $this->api_request(
			'/number/search',
			'GET',
			$params
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$numbers = array();
		foreach ( $response['numbers'] ?? array() as $number ) {
			$numbers[] = array(
				'phone_number'  => '+' . $number['msisdn'],
				'friendly_name' => $number['msisdn'],
				'capabilities'  => array(
					'sms'   => in_array( 'SMS', $number['features'] ?? array(), true ),
					'mms'   => in_array( 'MMS', $number['features'] ?? array(), true ),
					'voice' => in_array( 'VOICE', $number['features'] ?? array(), true ),
				),
				'type'          => $number['type'] ?? 'mobile',
				'monthly_cost'  => floatval( $number['cost'] ?? 0 ),
			);
		}

		return $numbers;
	}

	/**
	 * Purchase a phone number.
	 *
	 * @param string $phone_number Phone number to purchase.
	 * @return array|WP_Error Purchase result.
	 */
	public function purchase_number( $phone_number ) {
		// Extract country code from phone number
		$country_code = 'US'; // Default
		if ( preg_match( '/^\+(\d{1,2})/', $phone_number, $matches ) ) {
			$country_codes_map = array(
				'1'  => 'US',
				'44' => 'GB',
				'49' => 'DE',
				// Add more as needed
			);
			$country_code = $country_codes_map[ $matches[1] ] ?? 'US';
		}

		$response = $this->api_request(
			'/number/buy',
			'POST',
			array(
				'api_key'    => $this->api_key,
				'api_secret' => $this->api_secret,
				'country'    => $country_code,
				'msisdn'     => ltrim( $phone_number, '+' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( $response['error-code'] !== '200' ) {
			return new WP_Error(
				'vonage_purchase_error',
				$response['error-code-label'] ?? 'Failed to purchase number'
			);
		}

		return array(
			'phone_number' => $phone_number,
			'status'       => 'purchased',
		);
	}

	/**
	 * Validate webhook signature.
	 *
	 * @param array  $payload Webhook payload.
	 * @param string $signature Signature (not used by Vonage).
	 * @param string $url Webhook URL.
	 * @return bool True if valid.
	 */
	public function validate_webhook_signature( $payload, $signature, $url ) {
		// Vonage doesn't use signature validation by default
		// Instead, you should verify the request came from Vonage IPs
		// or use their signed webhooks feature with JWT
		return true;
	}

	/**
	 * Process webhook payload.
	 *
	 * @param array $payload Webhook data.
	 * @return array Processed data.
	 */
	public function process_webhook( $payload ) {
		// Handle delivery receipts
		if ( isset( $payload['messageId'] ) ) {
			return array(
				'message_id'   => $payload['messageId'],
				'from'         => $payload['msisdn'] ?? null,
				'to'           => $payload['to'] ?? null,
				'body'         => $payload['text'] ?? null,
				'status'       => $this->map_status( $payload['status'] ?? 'unknown' ),
				'num_segments' => 1,
				'error_code'   => $payload['err-code'] ?? null,
				'raw'          => $payload,
			);
		}

		// Handle inbound messages
		return array(
			'message_id'   => $payload['messageId'] ?? null,
			'from'         => $payload['msisdn'] ?? null,
			'to'           => $payload['to'] ?? null,
			'body'         => $payload['text'] ?? null,
			'status'       => 'delivered',
			'num_segments' => 1,
			'raw'          => $payload,
		);
	}

	/**
	 * Calculate message cost.
	 *
	 * @param string $to Destination phone number.
	 * @param string $message Message body.
	 * @return float Estimated cost in USD.
	 */
	public function calculate_cost( $to, $message ) {
		$segments = $this->count_segments( $message );

		// Vonage US/Canada pricing (approximate, in USD)
		$price_per_segment = 0.0076;

		// International numbers cost more
		if ( ! preg_match( '/^\+1/', $to ) ) {
			$price_per_segment = 0.05; // Rough international estimate
		}

		return $segments * $price_per_segment;
	}

	/**
	 * Count message segments.
	 *
	 * @param string $message Message body.
	 * @return int Number of segments.
	 */
	public function count_segments( $message ) {
		$length = mb_strlen( $message );

		// Check if message contains non-GSM characters
		$has_unicode = ! mb_check_encoding( $message, 'ASCII' );

		if ( $has_unicode ) {
			// Unicode messages: 70 chars per segment, 67 for multi-part
			if ( $length <= 70 ) {
				return 1;
			}
			return ceil( $length / 67 );
		} else {
			// GSM-7 messages: 160 chars per segment, 153 for multi-part
			if ( $length <= 160 ) {
				return 1;
			}
			return ceil( $length / 153 );
		}
	}

	/**
	 * Test connection.
	 *
	 * @return bool|WP_Error True on success.
	 */
	public function test_connection() {
		$response = $this->get_balance();

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Get provider capabilities.
	 *
	 * @return array Supported features.
	 */
	public function get_capabilities() {
		return array(
			'mms'              => false, // Vonage SMS API doesn't support MMS
			'unicode'          => true,
			'delivery_reports' => true,
			'two_way'          => true,
			'scheduled'        => false,
			'bulk'             => true,
		);
	}

	/**
	 * Make API request to Vonage.
	 *
	 * @param string $endpoint API endpoint.
	 * @param string $method HTTP method.
	 * @param array  $params Request parameters.
	 * @return array|WP_Error API response.
	 */
	private function api_request( $endpoint, $method = 'GET', $params = array() ) {
		$url = $this->api_base . $endpoint;

		$args = array(
			'method'  => $method,
			'timeout' => 30,
		);

		if ( $method === 'GET' ) {
			$url .= '?' . http_build_query( $params );
		} else {
			$args['body'] = $params;
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code >= 400 ) {
			return new WP_Error(
				'vonage_error',
				$data['error-code-label'] ?? 'Vonage API error',
				array(
					'status' => $code,
					'code'   => $data['error-code'] ?? null,
				)
			);
		}

		return $data;
	}

	/**
	 * Map Vonage status to standard status.
	 *
	 * @param string $vonage_status Vonage status.
	 * @return string Standard status.
	 */
	private function map_status( $vonage_status ) {
		$status_map = array(
			'submitted'   => 'queued',
			'buffered'    => 'queued',
			'accepted'    => 'sent',
			'delivered'   => 'delivered',
			'failed'      => 'failed',
			'rejected'    => 'failed',
			'expired'     => 'undelivered',
			'undelivered' => 'undelivered',
		);

		return $status_map[ $vonage_status ] ?? 'queued';
	}
}
