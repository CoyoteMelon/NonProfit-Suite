<?php
/**
 * Plivo SMS Adapter
 *
 * Integrates with Plivo's SMS API for sending and receiving messages.
 *
 * @package NonprofitSuite
 * @subpackage Integrations/Adapters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_Plivo_Adapter implements NS_SMS_Adapter {
	/**
	 * Plivo Auth ID.
	 *
	 * @var string
	 */
	private $auth_id;

	/**
	 * Plivo Auth Token.
	 *
	 * @var string
	 */
	private $auth_token;

	/**
	 * Default from phone number.
	 *
	 * @var string
	 */
	private $from_number;

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private $api_base = 'https://api.plivo.com/v1';

	/**
	 * Constructor.
	 *
	 * @param string $auth_id Plivo Auth ID.
	 * @param string $auth_token Plivo Auth Token.
	 * @param string $from_number Default sending phone number.
	 */
	public function __construct( $auth_id, $auth_token, $from_number = '' ) {
		$this->auth_id     = $auth_id;
		$this->auth_token  = $auth_token;
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
			return new WP_Error( 'missing_from', 'From number is required.' );
		}

		$body = array(
			'src'  => $from,
			'dst'  => $to,
			'text' => $message,
		);

		// Optional parameters
		if ( isset( $options['url'] ) ) {
			$body['url'] = $options['url']; // Delivery callback URL
		}

		if ( isset( $options['method'] ) ) {
			$body['method'] = $options['method'];
		}

		$response = $this->api_request(
			'/Account/' . $this->auth_id . '/Message/',
			'POST',
			$body
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'message_id' => $response['message_uuid'][0] ?? null,
			'status'     => 'queued',
			'segments'   => $this->count_segments( $message ),
			'price'      => $this->calculate_cost( $to, $message ),
			'raw'        => $response,
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
		$from = $options['from'] ?? $this->from_number;

		if ( empty( $from ) ) {
			return new WP_Error( 'missing_from', 'From number is required.' );
		}

		// Plivo supports bulk sending with comma-separated numbers
		$body = array(
			'src'  => $from,
			'dst'  => implode( '<', $recipients ), // Plivo uses < as separator
			'text' => $message,
		);

		if ( isset( $options['url'] ) ) {
			$body['url'] = $options['url'];
		}

		$response = $this->api_request(
			'/Account/' . $this->auth_id . '/Message/',
			'POST',
			$body
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Map UUIDs to recipients
		$results = array();
		$uuids   = $response['message_uuid'] ?? array();

		foreach ( $recipients as $index => $recipient ) {
			$results[ $recipient ] = array(
				'message_id' => $uuids[ $index ] ?? null,
				'status'     => 'queued',
				'segments'   => $this->count_segments( $message ),
				'price'      => $this->calculate_cost( $recipient, $message ),
			);
		}

		return $results;
	}

	/**
	 * Get message status.
	 *
	 * @param string $message_id Plivo message UUID.
	 * @return array|WP_Error Message status information.
	 */
	public function get_message_status( $message_id ) {
		$response = $this->api_request(
			'/Account/' . $this->auth_id . '/Message/' . $message_id . '/',
			'GET'
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'message_id'    => $response['message_uuid'],
			'status'        => $this->map_status( $response['message_state'] ),
			'error_code'    => $response['error_code'] ?? null,
			'error_message' => null,
			'price'         => floatval( $response['total_rate'] ?? 0 ),
			'sent_at'       => $response['message_time'] ?? null,
			'units'         => intval( $response['units'] ?? 1 ),
		);
	}

	/**
	 * Get account balance.
	 *
	 * @return array|WP_Error Balance information.
	 */
	public function get_balance() {
		$response = $this->api_request(
			'/Account/' . $this->auth_id . '/',
			'GET'
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'balance'  => floatval( $response['cash_credits'] ?? 0 ),
			'currency' => 'USD',
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
		$query_params = array(
			'country_iso' => $country_code,
			'type'        => 'local',
		);

		if ( isset( $filters['region'] ) ) {
			$query_params['region'] = $filters['region'];
		}

		if ( isset( $filters['pattern'] ) ) {
			$query_params['pattern'] = $filters['pattern'];
		}

		$query_string = http_build_query( $query_params );
		$endpoint     = '/Account/' . $this->auth_id . '/PhoneNumber/?' . $query_string;

		$response = $this->api_request( $endpoint, 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$numbers = array();
		foreach ( $response['objects'] ?? array() as $number ) {
			$numbers[] = array(
				'phone_number'  => $number['number'],
				'friendly_name' => $number['number'],
				'capabilities'  => array(
					'sms'   => $number['sms_enabled'] ?? false,
					'mms'   => $number['mms_enabled'] ?? false,
					'voice' => $number['voice_enabled'] ?? false,
				),
				'monthly_cost'  => floatval( $number['monthly_rental_rate'] ?? 0 ),
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
		$response = $this->api_request(
			'/Account/' . $this->auth_id . '/PhoneNumber/' . $phone_number . '/',
			'POST',
			array(
				'app_id' => null, // Can be configured to point to an application
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'number'       => $response['number'] ?? $phone_number,
			'phone_number' => $phone_number,
			'status'       => $response['status'] ?? 'purchased',
		);
	}

	/**
	 * Validate webhook signature.
	 *
	 * @param array  $payload Webhook payload.
	 * @param string $signature Signature from X-Plivo-Signature header.
	 * @param string $url Webhook URL.
	 * @return bool True if valid.
	 */
	public function validate_webhook_signature( $payload, $signature, $url ) {
		// Build the signature string
		$data = $url;

		// Sort payload alphabetically
		ksort( $payload );

		// Append parameters
		foreach ( $payload as $key => $value ) {
			$data .= $key . $value;
		}

		// Compute SHA1 hash
		$expected = hash( 'sha1', $data . $this->auth_token );

		return hash_equals( $expected, $signature );
	}

	/**
	 * Process webhook payload.
	 *
	 * @param array $payload Webhook data.
	 * @return array Processed data.
	 */
	public function process_webhook( $payload ) {
		return array(
			'message_id'   => $payload['MessageUUID'] ?? null,
			'from'         => $payload['From'] ?? null,
			'to'           => $payload['To'] ?? null,
			'body'         => $payload['Text'] ?? null,
			'status'       => $this->map_status( $payload['Status'] ?? 'unknown' ),
			'num_segments' => intval( $payload['Units'] ?? 1 ),
			'error_code'   => $payload['ErrorCode'] ?? null,
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

		// Plivo US/Canada pricing (approximate)
		$price_per_segment = 0.0065;

		// International numbers cost more
		if ( ! preg_match( '/^\+1/', $to ) ) {
			$price_per_segment = 0.04; // Rough international estimate
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
		$response = $this->api_request(
			'/Account/' . $this->auth_id . '/',
			'GET'
		);

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
			'mms'              => true,
			'unicode'          => true,
			'delivery_reports' => true,
			'two_way'          => true,
			'scheduled'        => false,
			'bulk'             => true,
		);
	}

	/**
	 * Make API request to Plivo.
	 *
	 * @param string $endpoint API endpoint.
	 * @param string $method HTTP method.
	 * @param array  $body Request body.
	 * @return array|WP_Error API response.
	 */
	private function api_request( $endpoint, $method = 'GET', $body = array() ) {
		$url = $this->api_base . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->auth_id . ':' . $this->auth_token ),
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		);

		if ( $method === 'POST' || $method === 'PUT' ) {
			$args['body'] = wp_json_encode( $body );
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
				'plivo_error',
				$data['error'] ?? 'Plivo API error',
				array(
					'status' => $code,
				)
			);
		}

		return $data;
	}

	/**
	 * Map Plivo status to standard status.
	 *
	 * @param string $plivo_status Plivo status.
	 * @return string Standard status.
	 */
	private function map_status( $plivo_status ) {
		$status_map = array(
			'queued'      => 'queued',
			'sent'        => 'sent',
			'delivered'   => 'delivered',
			'undelivered' => 'undelivered',
			'failed'      => 'failed',
			'rejected'    => 'failed',
		);

		return $status_map[ $plivo_status ] ?? 'queued';
	}
}
