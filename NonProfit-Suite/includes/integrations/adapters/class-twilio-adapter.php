<?php
/**
 * Twilio SMS Adapter
 *
 * Integrates with Twilio's Programmable SMS API for sending and receiving messages.
 *
 * @package NonprofitSuite
 * @subpackage Integrations/Adapters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_Twilio_Adapter implements NS_SMS_Adapter {
	/**
	 * Twilio Account SID.
	 *
	 * @var string
	 */
	private $account_sid;

	/**
	 * Twilio Auth Token.
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
	private $api_base = 'https://api.twilio.com/2010-04-01';

	/**
	 * Constructor.
	 *
	 * @param string $account_sid Twilio Account SID.
	 * @param string $auth_token Twilio Auth Token.
	 * @param string $from_number Default sending phone number.
	 */
	public function __construct( $account_sid, $auth_token, $from_number = '' ) {
		$this->account_sid = $account_sid;
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
			'To'   => $to,
			'From' => $from,
			'Body' => $message,
		);

		// Optional parameters
		if ( isset( $options['media_url'] ) ) {
			$body['MediaUrl'] = $options['media_url'];
		}

		if ( isset( $options['status_callback'] ) ) {
			$body['StatusCallback'] = $options['status_callback'];
		}

		$response = $this->api_request(
			'/Accounts/' . $this->account_sid . '/Messages.json',
			'POST',
			$body
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'message_id' => $response['sid'],
			'status'     => $this->map_status( $response['status'] ),
			'segments'   => $response['num_segments'] ?? 1,
			'price'      => abs( floatval( $response['price'] ?? 0 ) ),
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
	 * @param string $message_id Twilio message SID.
	 * @return array|WP_Error Message status information.
	 */
	public function get_message_status( $message_id ) {
		$response = $this->api_request(
			'/Accounts/' . $this->account_sid . '/Messages/' . $message_id . '.json',
			'GET'
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'message_id'   => $response['sid'],
			'status'       => $this->map_status( $response['status'] ),
			'error_code'   => $response['error_code'] ?? null,
			'error_message' => $response['error_message'] ?? null,
			'price'        => abs( floatval( $response['price'] ?? 0 ) ),
			'sent_at'      => $response['date_sent'] ?? null,
			'updated_at'   => $response['date_updated'] ?? null,
		);
	}

	/**
	 * Get account balance.
	 *
	 * @return array|WP_Error Balance information.
	 */
	public function get_balance() {
		$response = $this->api_request(
			'/Accounts/' . $this->account_sid . '/Balance.json',
			'GET'
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'balance'  => floatval( $response['balance'] ?? 0 ),
			'currency' => $response['currency'] ?? 'USD',
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
		$query_params = array();

		if ( isset( $filters['area_code'] ) ) {
			$query_params['AreaCode'] = $filters['area_code'];
		}

		if ( isset( $filters['contains'] ) ) {
			$query_params['Contains'] = $filters['contains'];
		}

		$query_params['SmsEnabled'] = 'true';

		$query_string = http_build_query( $query_params );
		$endpoint     = '/Accounts/' . $this->account_sid . '/AvailablePhoneNumbers/' . $country_code . '/Local.json?' . $query_string;

		$response = $this->api_request( $endpoint, 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$numbers = array();
		foreach ( $response['available_phone_numbers'] ?? array() as $number ) {
			$numbers[] = array(
				'phone_number' => $number['phone_number'],
				'friendly_name' => $number['friendly_name'],
				'capabilities' => array(
					'sms'   => $number['capabilities']['SMS'] ?? false,
					'mms'   => $number['capabilities']['MMS'] ?? false,
					'voice' => $number['capabilities']['voice'] ?? false,
				),
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
			'/Accounts/' . $this->account_sid . '/IncomingPhoneNumbers.json',
			'POST',
			array(
				'PhoneNumber' => $phone_number,
				'SmsUrl'      => site_url( '/wp-json/nonprofitsuite/v1/sms/webhook/twilio' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'sid'          => $response['sid'],
			'phone_number' => $response['phone_number'],
			'friendly_name' => $response['friendly_name'],
		);
	}

	/**
	 * Validate webhook signature.
	 *
	 * @param array  $payload Webhook payload.
	 * @param string $signature Signature from X-Twilio-Signature header.
	 * @param string $url Webhook URL.
	 * @return bool True if valid.
	 */
	public function validate_webhook_signature( $payload, $signature, $url ) {
		// Build the signature data
		$data = $url;

		// Sort payload alphabetically
		ksort( $payload );

		// Append parameters
		foreach ( $payload as $key => $value ) {
			$data .= $key . $value;
		}

		// Compute HMAC SHA1 hash
		$expected = base64_encode( hash_hmac( 'sha1', $data, $this->auth_token, true ) );

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
			'message_id'   => $payload['MessageSid'] ?? $payload['SmsSid'] ?? null,
			'from'         => $payload['From'] ?? null,
			'to'           => $payload['To'] ?? null,
			'body'         => $payload['Body'] ?? null,
			'status'       => $this->map_status( $payload['MessageStatus'] ?? $payload['SmsStatus'] ?? 'unknown' ),
			'num_segments' => intval( $payload['NumSegments'] ?? 1 ),
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

		// Twilio US/Canada pricing (approximate)
		$price_per_segment = 0.0079;

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
		$response = $this->api_request(
			'/Accounts/' . $this->account_sid . '.json',
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
	 * Make API request to Twilio.
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
				'Authorization' => 'Basic ' . base64_encode( $this->account_sid . ':' . $this->auth_token ),
			),
			'timeout' => 30,
		);

		if ( $method === 'POST' || $method === 'PUT' ) {
			$args['body'] = $body;
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
				'twilio_error',
				$data['message'] ?? 'Twilio API error',
				array(
					'status' => $code,
					'code'   => $data['code'] ?? null,
				)
			);
		}

		return $data;
	}

	/**
	 * Map Twilio status to standard status.
	 *
	 * @param string $twilio_status Twilio status.
	 * @return string Standard status.
	 */
	private function map_status( $twilio_status ) {
		$status_map = array(
			'queued'      => 'queued',
			'sending'     => 'sent',
			'sent'        => 'sent',
			'delivered'   => 'delivered',
			'undelivered' => 'undelivered',
			'failed'      => 'failed',
			'received'    => 'delivered',
		);

		return $status_map[ $twilio_status ] ?? 'queued';
	}
}
