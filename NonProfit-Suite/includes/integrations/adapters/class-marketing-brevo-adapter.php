<?php
/**
 * Brevo Marketing Adapter
 *
 * Adapter for Brevo (formerly Sendinblue) email marketing integration.
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
 * NonprofitSuite_Marketing_Brevo_Adapter Class
 *
 * Implements marketing integration using Brevo API v3.
 */
class NonprofitSuite_Marketing_Brevo_Adapter implements NonprofitSuite_Marketing_Adapter_Interface {

	/**
	 * Brevo API base URL
	 */
	const API_BASE_URL = 'https://api.brevo.com/v3/';

	/**
	 * Provider settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * API key
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Constructor
	 */
	public function __construct() {
		$manager = NonprofitSuite_Integration_Manager::get_instance();
		$this->settings = $manager->get_provider_settings( 'marketing', 'brevo' );
		$this->api_key = $this->settings['api_key'] ?? '';
	}

	/**
	 * Add subscriber
	 *
	 * @param array $subscriber Subscriber data
	 * @return array|WP_Error
	 */
	public function add_subscriber( $subscriber ) {
		$subscriber = wp_parse_args( $subscriber, array(
			'email'      => '',
			'first_name' => '',
			'last_name'  => '',
			'lists'      => array(),
			'attributes' => array(),
		) );

		$payload = array(
			'email'      => $subscriber['email'],
			'attributes' => array_merge(
				array(
					'FIRSTNAME' => $subscriber['first_name'],
					'LASTNAME'  => $subscriber['last_name'],
				),
				$subscriber['attributes']
			),
			'listIds'    => array_map( 'intval', $subscriber['lists'] ),
			'updateEnabled' => true,
		);

		$response = $this->make_request( 'contacts', $payload, 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'subscriber_id' => $response['id'] ?? $subscriber['email'],
			'status'        => 'subscribed',
		);
	}

	/**
	 * Remove subscriber
	 *
	 * @param string $email Email address
	 * @return bool|WP_Error
	 */
	public function remove_subscriber( $email ) {
		$response = $this->make_request( 'contacts/' . urlencode( $email ), array(), 'DELETE' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Get subscriber
	 *
	 * @param string $email Email address
	 * @return array|WP_Error
	 */
	public function get_subscriber( $email ) {
		$response = $this->make_request( 'contacts/' . urlencode( $email ), array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$attributes = $response['attributes'] ?? array();

		return array(
			'subscriber_id' => $response['id'],
			'email'         => $response['email'],
			'first_name'    => $attributes['FIRSTNAME'] ?? '',
			'last_name'     => $attributes['LASTNAME'] ?? '',
			'status'        => $response['emailBlacklisted'] ? 'unsubscribed' : 'subscribed',
		);
	}

	/**
	 * Update subscriber
	 *
	 * @param string $email Email address
	 * @param array  $data  Update data
	 * @return bool|WP_Error
	 */
	public function update_subscriber( $email, $data ) {
		$payload = array();

		if ( isset( $data['first_name'] ) || isset( $data['last_name'] ) ) {
			$payload['attributes'] = array();
			if ( isset( $data['first_name'] ) ) {
				$payload['attributes']['FIRSTNAME'] = $data['first_name'];
			}
			if ( isset( $data['last_name'] ) ) {
				$payload['attributes']['LASTNAME'] = $data['last_name'];
			}
		}

		if ( isset( $data['lists'] ) ) {
			$payload['listIds'] = array_map( 'intval', $data['lists'] );
		}

		$response = $this->make_request( 'contacts/' . urlencode( $email ), $payload, 'PUT' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Get lists
	 *
	 * @return array|WP_Error
	 */
	public function get_lists() {
		$response = $this->make_request( 'contacts/lists', array( 'limit' => 50 ), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$lists = array();
		if ( ! empty( $response['lists'] ) ) {
			foreach ( $response['lists'] as $list ) {
				$lists[] = array(
					'id'   => $list['id'],
					'name' => $list['name'],
					'subscribers' => $list['totalSubscribers'] ?? 0,
				);
			}
		}

		return $lists;
	}

	/**
	 * Create campaign
	 *
	 * @param array $campaign Campaign data
	 * @return array|WP_Error
	 */
	public function create_campaign( $campaign ) {
		$campaign = wp_parse_args( $campaign, array(
			'name'         => '',
			'subject'      => '',
			'from_name'    => '',
			'from_email'   => '',
			'reply_to'     => '',
			'html_content' => '',
			'text_content' => '',
			'list_ids'     => array(),
			'tag'          => '',
		) );

		$payload = array(
			'name'         => $campaign['name'],
			'subject'      => $campaign['subject'],
			'sender'       => array(
				'name'  => $campaign['from_name'],
				'email' => $campaign['from_email'],
			),
			'replyTo'      => $campaign['reply_to'] ?: $campaign['from_email'],
			'htmlContent'  => $campaign['html_content'],
			'tag'          => $campaign['tag'],
		);

		// Add recipients
		if ( ! empty( $campaign['list_ids'] ) ) {
			$payload['recipients'] = array(
				'listIds' => array_map( 'intval', $campaign['list_ids'] ),
			);
		}

		$response = $this->make_request( 'emailCampaigns', $payload, 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'campaign_id' => $response['id'] ?? '',
			'status'      => 'draft',
		);
	}

	/**
	 * Send campaign
	 *
	 * @param string $campaign_id Campaign ID
	 * @return bool|WP_Error
	 */
	public function send_campaign( $campaign_id ) {
		// Send campaign immediately
		$response = $this->make_request( 'emailCampaigns/' . $campaign_id . '/sendNow', array(), 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Schedule campaign
	 *
	 * @param string $campaign_id Campaign ID
	 * @param string $schedule_at ISO 8601 datetime
	 * @return bool|WP_Error
	 */
	public function schedule_campaign( $campaign_id, $schedule_at ) {
		$payload = array(
			'scheduledAt' => $schedule_at,
		);

		$response = $this->make_request( 'emailCampaigns/' . $campaign_id . '/sendLater', $payload, 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Get campaign stats
	 *
	 * @param string $campaign_id Campaign ID
	 * @return array|WP_Error
	 */
	public function get_campaign_stats( $campaign_id ) {
		$response = $this->make_request( 'emailCampaigns/' . $campaign_id, array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$stats = $response['statistics'] ?? array();

		return array(
			'sent'         => $stats['sent'] ?? 0,
			'delivered'    => $stats['delivered'] ?? 0,
			'opens'        => $stats['uniqueOpens'] ?? 0,
			'clicks'       => $stats['uniqueClicks'] ?? 0,
			'bounces'      => $stats['hardBounces'] + $stats['softBounces'] ?? 0,
			'unsubscribes' => $stats['unsubscriptions'] ?? 0,
			'complaints'   => $stats['complaints'] ?? 0,
		);
	}

	/**
	 * Send transactional email
	 *
	 * @param array $email Email data
	 * @return array|WP_Error
	 */
	public function send_transactional_email( $email ) {
		$email = wp_parse_args( $email, array(
			'to'           => '',
			'subject'      => '',
			'from_name'    => '',
			'from_email'   => '',
			'reply_to'     => '',
			'html_content' => '',
			'text_content' => '',
			'template_id'  => null,
			'params'       => array(),
		) );

		$payload = array(
			'to' => array(
				array( 'email' => $email['to'] ),
			),
			'sender' => array(
				'name'  => $email['from_name'],
				'email' => $email['from_email'],
			),
		);

		if ( ! empty( $email['template_id'] ) ) {
			$payload['templateId'] = $email['template_id'];
			$payload['params'] = $email['params'];
		} else {
			$payload['subject'] = $email['subject'];
			$payload['htmlContent'] = $email['html_content'];
			if ( ! empty( $email['text_content'] ) ) {
				$payload['textContent'] = $email['text_content'];
			}
		}

		if ( ! empty( $email['reply_to'] ) ) {
			$payload['replyTo'] = array( 'email' => $email['reply_to'] );
		}

		$response = $this->make_request( 'smtp/email', $payload, 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'message_id' => $response['messageId'] ?? '',
			'status'     => 'sent',
		);
	}

	/**
	 * Get templates
	 *
	 * @return array|WP_Error
	 */
	public function get_templates() {
		$response = $this->make_request( 'smtp/templates', array( 'limit' => 50 ), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$templates = array();
		if ( ! empty( $response['templates'] ) ) {
			foreach ( $response['templates'] as $template ) {
				$templates[] = array(
					'id'   => $template['id'],
					'name' => $template['name'],
				);
			}
		}

		return $templates;
	}

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error
	 */
	public function test_connection() {
		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'not_configured', __( 'Brevo API key not configured', 'nonprofitsuite' ) );
		}

		// Test with account info request
		$response = $this->make_request( 'account', array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return isset( $response['email'] );
	}

	/**
	 * Get provider name
	 *
	 * @return string
	 */
	public function get_provider_name() {
		return 'Brevo';
	}

	/**
	 * Make API request
	 *
	 * @param string $endpoint Endpoint
	 * @param array  $data     Request data
	 * @param string $method   HTTP method
	 * @return array|WP_Error
	 */
	private function make_request( $endpoint, $data = array(), $method = 'GET' ) {
		$url = self::API_BASE_URL . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'api-key'      => $this->api_key,
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
			'timeout' => 30,
		);

		if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ) ) && ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
		} elseif ( $method === 'GET' && ! empty( $data ) ) {
			$url .= '?' . http_build_query( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$error_message = $body['message'] ?? $body['error'] ?? __( 'Unknown error', 'nonprofitsuite' );
			return new WP_Error( 'api_error', sprintf( __( 'Brevo API error: %s', 'nonprofitsuite' ), $error_message ) );
		}

		return $body;
	}
}
