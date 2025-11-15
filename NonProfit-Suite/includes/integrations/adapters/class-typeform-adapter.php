<?php
/**
 * Typeform Adapter
 *
 * Handles integration with Typeform API.
 * Uses personal access token for authentication.
 *
 * @package NonprofitSuite
 * @subpackage Integrations
 */

class NS_Typeform_Adapter implements NS_Form_Adapter {
	private $api_token;
	private $api_base = 'https://api.typeform.com';
	private $webhook_secret;

	/**
	 * Constructor.
	 *
	 * @param string $api_token Typeform personal access token.
	 * @param string $webhook_secret Optional webhook secret for validation.
	 */
	public function __construct( $api_token, $webhook_secret = '' ) {
		$this->api_token      = $api_token;
		$this->webhook_secret = $webhook_secret;
	}

	/**
	 * Create a new form in Typeform.
	 */
	public function create_form( $form_data, $fields ) {
		$typeform_fields = array();

		foreach ( $fields as $field ) {
			$typeform_field = $this->convert_field_to_typeform( $field );
			if ( $typeform_field ) {
				$typeform_fields[] = $typeform_field;
			}
		}

		$body = array(
			'title'  => $form_data['form_name'],
			'fields' => $typeform_fields,
		);

		if ( ! empty( $form_data['description'] ) ) {
			$body['settings'] = array(
				'is_public' => true,
				'show_progress_bar' => true,
			);
			// Typeform uses welcome screen for description
			$body['welcome_screens'] = array(
				array(
					'title' => $form_data['form_name'],
					'properties' => array(
						'description' => $form_data['description'],
						'show_button' => true,
						'button_text' => 'Start',
					),
				),
			);
		}

		if ( ! empty( $form_data['confirmation_message'] ) ) {
			$body['thankyou_screens'] = array(
				array(
					'title' => 'Thank you!',
					'properties' => array(
						'description' => $form_data['confirmation_message'],
						'show_button' => false,
					),
				),
			);
		}

		$result = $this->api_request( 'POST', '/forms', $body );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'provider_form_id' => $result['id'],
			'form_url'         => $result['_links']['display'],
		);
	}

	/**
	 * Convert our field format to Typeform field format.
	 */
	private function convert_field_to_typeform( $field ) {
		$typeform_field = array(
			'title'      => $field['field_label'],
			'properties' => array(),
			'validations' => array(
				'required' => (bool) $field['is_required'],
			),
		);

		// Map field types
		switch ( $field['field_type'] ) {
			case 'text':
				$typeform_field['type'] = 'short_text';
				break;

			case 'email':
				$typeform_field['type'] = 'email';
				break;

			case 'phone':
				$typeform_field['type'] = 'phone_number';
				break;

			case 'number':
				$typeform_field['type'] = 'number';
				break;

			case 'textarea':
				$typeform_field['type'] = 'long_text';
				break;

			case 'select':
			case 'radio':
				$typeform_field['type'] = 'multiple_choice';
				$options = json_decode( $field['field_options'], true );
				$choices = array();
				foreach ( $options as $option ) {
					$choices[] = array( 'label' => $option );
				}
				$typeform_field['properties']['choices'] = $choices;
				$typeform_field['properties']['allow_multiple_selection'] = false;
				break;

			case 'checkbox':
				$typeform_field['type'] = 'multiple_choice';
				$options = json_decode( $field['field_options'], true );
				$choices = array();
				foreach ( $options as $option ) {
					$choices[] = array( 'label' => $option );
				}
				$typeform_field['properties']['choices'] = $choices;
				$typeform_field['properties']['allow_multiple_selection'] = true;
				break;

			case 'date':
				$typeform_field['type'] = 'date';
				break;

			case 'file':
				$typeform_field['type'] = 'file_upload';
				break;

			case 'rating':
				$typeform_field['type'] = 'rating';
				$typeform_field['properties']['steps'] = 5;
				break;

			default:
				return null; // Unsupported field type
		}

		if ( ! empty( $field['help_text'] ) ) {
			$typeform_field['properties']['description'] = $field['help_text'];
		}

		return $typeform_field;
	}

	/**
	 * Update an existing form.
	 */
	public function update_form( $provider_form_id, $form_data, $fields ) {
		// Typeform uses PATCH for updates
		$body = array(
			'title' => $form_data['form_name'],
		);

		$result = $this->api_request( 'PATCH', "/forms/{$provider_form_id}", $body );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success' => true,
			'form_id' => $provider_form_id,
		);
	}

	/**
	 * Delete a form.
	 */
	public function delete_form( $provider_form_id ) {
		$result = $this->api_request( 'DELETE', "/forms/{$provider_form_id}" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Get form details.
	 */
	public function get_form( $provider_form_id ) {
		$result = $this->api_request( 'GET', "/forms/{$provider_form_id}" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'form_id'     => $result['id'],
			'title'       => $result['title'],
			'description' => $result['welcome_screens'][0]['properties']['description'] ?? '',
			'form_url'    => $result['_links']['display'],
			'fields'      => $this->parse_typeform_fields( $result['fields'] ?? array() ),
		);
	}

	/**
	 * Parse Typeform fields into our format.
	 */
	private function parse_typeform_fields( $fields ) {
		$parsed = array();

		foreach ( $fields as $field ) {
			$parsed_field = array(
				'field_name'  => $field['id'],
				'field_label' => $field['title'],
				'is_required' => $field['validations']['required'] ?? false,
			);

			// Map Typeform types to our types
			$type_map = array(
				'short_text'      => 'text',
				'long_text'       => 'textarea',
				'email'           => 'email',
				'phone_number'    => 'phone',
				'number'          => 'number',
				'multiple_choice' => 'radio',
				'date'            => 'date',
				'file_upload'     => 'file',
				'rating'          => 'rating',
			);

			$parsed_field['field_type'] = $type_map[ $field['type'] ] ?? 'text';

			// Handle multiple choice options
			if ( $field['type'] === 'multiple_choice' ) {
				if ( $field['properties']['allow_multiple_selection'] ?? false ) {
					$parsed_field['field_type'] = 'checkbox';
				}
				
				$options = array();
				foreach ( $field['properties']['choices'] ?? array() as $choice ) {
					$options[] = $choice['label'];
				}
				$parsed_field['field_options'] = $options;
			}

			$parsed[] = $parsed_field;
		}

		return $parsed;
	}

	/**
	 * Get form submissions (responses).
	 */
	public function get_submissions( $provider_form_id, $args = array() ) {
		$page_size = $args['limit'] ?? 100;
		$endpoint  = "/forms/{$provider_form_id}/responses?page_size={$page_size}";

		$result = $this->api_request( 'GET', $endpoint );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$submissions = array();
		foreach ( $result['items'] ?? array() as $response ) {
			$submissions[] = $this->parse_typeform_response( $response );
		}

		return $submissions;
	}

	/**
	 * Get a single submission.
	 */
	public function get_submission( $provider_form_id, $provider_submission_id ) {
		// Typeform doesn't have a direct endpoint for single response
		// Need to fetch all and filter, or use webhooks for real-time
		$all_submissions = $this->get_submissions( $provider_form_id );

		foreach ( $all_submissions as $submission ) {
			if ( $submission['provider_submission_id'] === $provider_submission_id ) {
				return $submission;
			}
		}

		return new WP_Error( 'not_found', 'Submission not found.' );
	}

	/**
	 * Parse Typeform response into our format.
	 */
	private function parse_typeform_response( $response ) {
		$data = array(
			'provider_submission_id' => $response['response_id'],
			'submitted_at'           => $response['submitted_at'],
			'fields'                 => array(),
		);

		foreach ( $response['answers'] ?? array() as $answer ) {
			$field_data = array(
				'field_name' => $answer['field']['id'],
			);

			// Extract answer based on type
			if ( isset( $answer['text'] ) ) {
				$field_data['field_value'] = $answer['text'];
			} elseif ( isset( $answer['email'] ) ) {
				$field_data['field_value'] = $answer['email'];
			} elseif ( isset( $answer['phone_number'] ) ) {
				$field_data['field_value'] = $answer['phone_number'];
			} elseif ( isset( $answer['number'] ) ) {
				$field_data['field_value'] = $answer['number'];
			} elseif ( isset( $answer['date'] ) ) {
				$field_data['field_value'] = $answer['date'];
			} elseif ( isset( $answer['choice'] ) ) {
				$field_data['field_value'] = $answer['choice']['label'];
			} elseif ( isset( $answer['choices'] ) ) {
				$labels = array_map( function( $c ) {
					return $c['label'];
				}, $answer['choices']['labels'] );
				$field_data['field_value'] = implode( ', ', $labels );
			} elseif ( isset( $answer['file_url'] ) ) {
				$field_data['field_value'] = $answer['file_url'];
				$field_data['file_url']    = $answer['file_url'];
			}

			$data['fields'][] = $field_data;
		}

		return $data;
	}

	/**
	 * Get form analytics.
	 */
	public function get_form_analytics( $provider_form_id ) {
		// Get form insights from Typeform Insights API
		$result = $this->api_request( 'GET', "/insights/{$provider_form_id}/summary" );

		if ( is_wp_error( $result ) ) {
			// Fallback to counting submissions
			$submissions = $this->get_submissions( $provider_form_id );
			return array(
				'total_submissions' => is_array( $submissions ) ? count( $submissions ) : 0,
			);
		}

		return array(
			'total_submissions' => $result['summary']['responses']['total'] ?? 0,
			'completion_rate'   => $result['summary']['responses']['completed'] ?? 0,
			'average_time'      => $result['summary']['time']['average'] ?? 0,
		);
	}

	/**
	 * Get embed code for the form.
	 */
	public function get_embed_code( $provider_form_id, $options = array() ) {
		$width  = $options['width'] ?? '100%';
		$height = $options['height'] ?? 500;
		$style  = $options['style'] ?? 'standard'; // standard, popup, slider, popover

		if ( $style === 'standard' ) {
			return sprintf(
				'<div data-tf-widget="%s" data-tf-iframe-props="title=Form" style="width:%s;height:%dpx;"></div><script src="//embed.typeform.com/next/embed.js"></script>',
				$provider_form_id,
				$width,
				$height
			);
		} elseif ( $style === 'popup' ) {
			return sprintf(
				'<button data-tf-popup="%s" data-tf-iframe-props="title=Form">Open Form</button><script src="//embed.typeform.com/next/embed.js"></script>',
				$provider_form_id
			);
		}

		return '';
	}

	/**
	 * Validate webhook signature.
	 */
	public function validate_webhook_signature( $payload, $signature ) {
		if ( empty( $this->webhook_secret ) ) {
			return false;
		}

		$expected_signature = base64_encode( hash_hmac( 'sha256', $payload, $this->webhook_secret, true ) );
		
		return hash_equals( $expected_signature, $signature );
	}

	/**
	 * Process webhook from Typeform.
	 */
	public function process_webhook( $webhook_data ) {
		if ( ! isset( $webhook_data['form_response'] ) ) {
			return new WP_Error( 'invalid_webhook', 'Invalid webhook data.' );
		}

		return $this->parse_typeform_response( $webhook_data['form_response'] );
	}

	/**
	 * Test the API connection.
	 */
	public function test_connection() {
		$result = $this->api_request( 'GET', '/me' );
		return ! is_wp_error( $result );
	}

	/**
	 * Make an API request to Typeform.
	 */
	private function api_request( $method, $endpoint, $body = null ) {
		$url = $this->api_base . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_token,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		);

		if ( $body !== null ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		// DELETE requests return 204 No Content
		if ( $code === 204 ) {
			return true;
		}

		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'api_error',
				$data['description'] ?? 'Unknown API error',
				array( 'status' => $code )
			);
		}

		return $data;
	}
}
