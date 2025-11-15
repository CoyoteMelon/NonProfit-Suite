<?php
/**
 * SendGrid Email Adapter
 *
 * Adapter that uses SendGrid API for professional transactional email.
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
 * NonprofitSuite_Email_SendGrid_Adapter Class
 *
 * Implements email integration using SendGrid API.
 */
class NonprofitSuite_Email_SendGrid_Adapter implements NonprofitSuite_Email_Adapter_Interface {

	/**
	 * SendGrid API base URL
	 */
	const API_BASE_URL = 'https://api.sendgrid.com/v3/';

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
		$this->settings = $manager->get_provider_settings( 'email', 'sendgrid' );
		$this->api_key = $this->settings['api_key'] ?? '';
	}

	/**
	 * Send an email
	 *
	 * @param array $args Email arguments
	 * @return array|WP_Error Result
	 */
	public function send( $args ) {
		$args = wp_parse_args( $args, array(
			'to'          => '',
			'subject'     => '',
			'message'     => '',
			'from'        => $this->settings['from_email'] ?? get_option( 'admin_email' ),
			'from_name'   => $this->settings['from_name'] ?? get_option( 'blogname' ),
			'reply_to'    => '',
			'cc'          => '',
			'bcc'         => '',
			'attachments' => array(),
			'html'        => true,
			'template_id' => '',
			'tags'        => array(),
		) );

		// Validate required fields
		if ( empty( $args['to'] ) || empty( $args['subject'] ) || empty( $args['message'] ) ) {
			return new WP_Error( 'missing_required', __( 'Missing required email fields', 'nonprofitsuite' ) );
		}

		// Check if we have an API key
		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'not_configured', __( 'SendGrid API key not configured', 'nonprofitsuite' ) );
		}

		// Build email payload
		$payload = $this->build_payload( $args );

		// Send via SendGrid API
		$response = wp_remote_post( self::API_BASE_URL . 'mail/send', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			do_action( 'ns_email_failed', $args['to'], $args['subject'], $response, 'sendgrid' );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code !== 202 ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			$error_message = $body['errors'][0]['message'] ?? __( 'Unknown error', 'nonprofitsuite' );

			do_action( 'ns_email_failed', $args['to'], $args['subject'], new WP_Error( 'send_failed', $error_message ), 'sendgrid' );

			return new WP_Error(
				'send_failed',
				sprintf( __( 'SendGrid API error: %s', 'nonprofitsuite' ), $error_message )
			);
		}

		// Get message ID from headers
		$message_id = wp_remote_retrieve_header( $response, 'X-Message-Id' );

		do_action( 'ns_email_sent', $args['to'], $args['subject'], $args['message'], 'sendgrid' );

		return array(
			'message_id' => $message_id,
			'status'     => 'sent',
		);
	}

	/**
	 * Build SendGrid payload
	 *
	 * @param array $args Email arguments
	 * @return array
	 */
	private function build_payload( $args ) {
		// Build base payload
		$payload = array(
			'personalizations' => array(
				array(
					'to'      => $this->format_addresses( $args['to'] ),
					'subject' => $args['subject'],
				),
			),
			'from' => array(
				'email' => $args['from'],
				'name'  => $args['from_name'],
			),
			'content' => array(
				array(
					'type'  => $args['html'] ? 'text/html' : 'text/plain',
					'value' => $args['message'],
				),
			),
		);

		// Add CC
		if ( ! empty( $args['cc'] ) ) {
			$payload['personalizations'][0]['cc'] = $this->format_addresses( $args['cc'] );
		}

		// Add BCC
		if ( ! empty( $args['bcc'] ) ) {
			$payload['personalizations'][0]['bcc'] = $this->format_addresses( $args['bcc'] );
		}

		// Add reply-to
		if ( ! empty( $args['reply_to'] ) ) {
			$payload['reply_to'] = array(
				'email' => $args['reply_to'],
			);
		}

		// Add template ID if provided
		if ( ! empty( $args['template_id'] ) ) {
			$payload['template_id'] = $args['template_id'];
		}

		// Add categories/tags for tracking
		if ( ! empty( $args['tags'] ) ) {
			$payload['categories'] = $args['tags'];
		}

		// Add tracking settings
		$payload['tracking_settings'] = array(
			'click_tracking'        => array(
				'enable' => true,
			),
			'open_tracking'         => array(
				'enable' => true,
			),
			'subscription_tracking' => array(
				'enable' => false,
			),
		);

		return $payload;
	}

	/**
	 * Format email addresses for SendGrid API
	 *
	 * @param string|array $addresses
	 * @return array
	 */
	private function format_addresses( $addresses ) {
		if ( ! is_array( $addresses ) ) {
			$addresses = array( $addresses );
		}

		$formatted = array();

		foreach ( $addresses as $address ) {
			$formatted[] = array(
				'email' => trim( $address ),
			);
		}

		return $formatted;
	}

	/**
	 * Send bulk emails
	 *
	 * SendGrid supports batch API - up to 1000 at a time
	 *
	 * @param array $emails Array of email argument arrays
	 * @return array|WP_Error Result
	 */
	public function send_bulk( $emails ) {
		$sent_count = 0;
		$failed_count = 0;
		$errors = array();

		// Process in batches of 100 for safety
		$chunks = array_chunk( $emails, 100 );

		foreach ( $chunks as $chunk ) {
			foreach ( $chunk as $email ) {
				$result = $this->send( $email );

				if ( is_wp_error( $result ) ) {
					$failed_count++;
					$errors[] = $result->get_error_message();
				} else {
					$sent_count++;
				}

				// Small delay to avoid rate limiting
				usleep( 50000 ); // 0.05 second
			}
		}

		return array(
			'sent_count'   => $sent_count,
			'failed_count' => $failed_count,
			'errors'       => $errors,
		);
	}

	/**
	 * Get email delivery status
	 *
	 * @param string $message_id Message identifier
	 * @return array|WP_Error Status array
	 */
	public function get_status( $message_id ) {
		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'not_configured', __( 'SendGrid API key not configured', 'nonprofitsuite' ) );
		}

		// Query Events API
		$response = wp_remote_get(
			self::API_BASE_URL . 'messages/' . $message_id,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code !== 200 ) {
			return new WP_Error( 'status_failed', __( 'Failed to get message status', 'nonprofitsuite' ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		// Parse events to determine status
		$status = 'sent';
		$opens = 0;
		$clicks = 0;

		if ( isset( $body['events'] ) ) {
			foreach ( $body['events'] as $event ) {
				switch ( $event['event'] ) {
					case 'delivered':
						$status = 'delivered';
						break;
					case 'bounce':
					case 'dropped':
						$status = 'bounced';
						break;
					case 'open':
						$opens++;
						break;
					case 'click':
						$clicks++;
						break;
				}
			}
		}

		return array(
			'message_id' => $message_id,
			'status'     => $status,
			'opens'      => $opens,
			'clicks'     => $clicks,
			'timestamp'  => $body['last_event_time'] ?? '',
		);
	}

	/**
	 * Create email template
	 *
	 * @param array $template_data Template data
	 * @return array|WP_Error Template data
	 */
	public function create_template( $template_data ) {
		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'not_configured', __( 'SendGrid API key not configured', 'nonprofitsuite' ) );
		}

		$payload = array(
			'name'       => $template_data['name'] ?? '',
			'generation' => 'dynamic',
		);

		$response = wp_remote_post( self::API_BASE_URL . 'templates', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $payload ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code !== 201 ) {
			return new WP_Error( 'create_failed', __( 'Failed to create template', 'nonprofitsuite' ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		// Now create a version for the template
		if ( isset( $body['id'] ) ) {
			$version_payload = array(
				'template_id' => $body['id'],
				'active'      => 1,
				'name'        => $template_data['name'] . ' Version 1',
				'subject'     => $template_data['subject'] ?? '',
				'html_content' => $template_data['html'] ?? '',
				'plain_content' => $template_data['text'] ?? '',
			);

			wp_remote_post( self::API_BASE_URL . 'templates/' . $body['id'] . '/versions', array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $version_payload ),
			) );
		}

		return array(
			'template_id' => $body['id'],
		);
	}

	/**
	 * Update email template
	 *
	 * @param string $template_id   Template identifier
	 * @param array  $template_data Updated template data
	 * @return bool|WP_Error True on success
	 */
	public function update_template( $template_id, $template_data ) {
		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'not_configured', __( 'SendGrid API key not configured', 'nonprofitsuite' ) );
		}

		// Update template name
		if ( isset( $template_data['name'] ) ) {
			$payload = array(
				'name' => $template_data['name'],
			);

			wp_remote_request( self::API_BASE_URL . 'templates/' . $template_id, array(
				'method'  => 'PATCH',
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			) );
		}

		// Get active version and update it
		$response = wp_remote_get( self::API_BASE_URL . 'templates/' . $template_id, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
			),
		) );

		if ( ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( isset( $body['versions'][0]['id'] ) ) {
				$version_id = $body['versions'][0]['id'];

				$version_payload = array();

				if ( isset( $template_data['subject'] ) ) {
					$version_payload['subject'] = $template_data['subject'];
				}
				if ( isset( $template_data['html'] ) ) {
					$version_payload['html_content'] = $template_data['html'];
				}
				if ( isset( $template_data['text'] ) ) {
					$version_payload['plain_content'] = $template_data['text'];
				}

				wp_remote_request(
					self::API_BASE_URL . 'templates/' . $template_id . '/versions/' . $version_id,
					array(
						'method'  => 'PATCH',
						'headers' => array(
							'Authorization' => 'Bearer ' . $this->api_key,
							'Content-Type'  => 'application/json',
						),
						'body'    => wp_json_encode( $version_payload ),
					)
				);
			}
		}

		return true;
	}

	/**
	 * Delete email template
	 *
	 * @param string $template_id Template identifier
	 * @return bool|WP_Error True on success
	 */
	public function delete_template( $template_id ) {
		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'not_configured', __( 'SendGrid API key not configured', 'nonprofitsuite' ) );
		}

		$response = wp_remote_request( self::API_BASE_URL . 'templates/' . $template_id, array(
			'method'  => 'DELETE',
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code !== 204 ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete template', 'nonprofitsuite' ) );
		}

		return true;
	}

	/**
	 * List email templates
	 *
	 * @param array $args List arguments
	 * @return array|WP_Error Array of templates
	 */
	public function list_templates( $args = array() ) {
		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'not_configured', __( 'SendGrid API key not configured', 'nonprofitsuite' ) );
		}

		$args = wp_parse_args( $args, array(
			'limit'  => 50,
			'offset' => 0,
		) );

		$response = wp_remote_get(
			self::API_BASE_URL . 'templates?generations=dynamic&page_size=' . $args['limit'],
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return $body['templates'] ?? array();
	}

	/**
	 * Get email statistics
	 *
	 * @param array $args Statistics arguments
	 * @return array|WP_Error Statistics array
	 */
	public function get_statistics( $args = array() ) {
		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'not_configured', __( 'SendGrid API key not configured', 'nonprofitsuite' ) );
		}

		$args = wp_parse_args( $args, array(
			'start_date' => date( 'Y-m-d', strtotime( '-30 days' ) ),
			'end_date'   => date( 'Y-m-d' ),
		) );

		$response = wp_remote_get(
			self::API_BASE_URL . 'stats?start_date=' . $args['start_date'] . '&end_date=' . $args['end_date'],
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		// Aggregate stats
		$stats = array(
			'sent'      => 0,
			'delivered' => 0,
			'bounced'   => 0,
			'opened'    => 0,
			'clicked'   => 0,
		);

		if ( isset( $body[0]['stats'] ) ) {
			foreach ( $body[0]['stats'] as $stat ) {
				$metrics = $stat['metrics'];
				$stats['sent']      += $metrics['requests'] ?? 0;
				$stats['delivered'] += $metrics['delivered'] ?? 0;
				$stats['bounced']   += ( $metrics['bounces'] ?? 0 ) + ( $metrics['blocks'] ?? 0 );
				$stats['opened']    += $metrics['unique_opens'] ?? 0;
				$stats['clicked']   += $metrics['unique_clicks'] ?? 0;
			}
		}

		return $stats;
	}

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error True if connected
	 */
	public function test_connection() {
		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'not_configured', __( 'SendGrid API key not configured', 'nonprofitsuite' ) );
		}

		// Test with a simple API call
		$response = wp_remote_get( self::API_BASE_URL . 'scopes', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code === 200 ) {
			return true;
		}

		return new WP_Error( 'connection_failed', __( 'SendGrid API key is invalid', 'nonprofitsuite' ) );
	}

	/**
	 * Get provider name
	 *
	 * @return string Provider name
	 */
	public function get_provider_name() {
		return 'SendGrid';
	}
}
