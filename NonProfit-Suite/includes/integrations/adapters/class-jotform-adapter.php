<?php
/**
 * JotForm Adapter
 *
 * Handles integration with JotForm API.
 * Uses API key for authentication.
 *
 * @package NonprofitSuite
 * @subpackage Integrations
 */

class NS_JotForm_Adapter implements NS_Form_Adapter {
	private $api_key;
	private $api_base = 'https://api.jotform.com';

	/**
	 * Constructor.
	 *
	 * @param string $api_key JotForm API key.
	 */
	public function __construct( $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Create a new form in JotForm.
	 */
	public function create_form( $form_data, $fields ) {
		// JotForm form creation
		$properties = array(
			'title' => $form_data['form_name'],
		);

		$result = $this->api_request( 'POST', '/form', array( 'properties' => $properties ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$form_id = $result['content']['id'];

		// Add fields to the form
		if ( ! empty( $fields ) ) {
			foreach ( $fields as $index => $field ) {
				$question = $this->convert_field_to_jotform( $field, $index );
				if ( $question ) {
					$this->api_request(
						'POST',
						"/form/{$form_id}/questions",
						array( 'question' => $question )
					);
				}
			}
		}

		return array(
			'provider_form_id' => $form_id,
			'form_url'         => "https://form.jotform.com/{$form_id}",
		);
	}

	/**
	 * Convert our field format to JotForm question format.
	 */
	private function convert_field_to_jotform( $field, $order ) {
		$question = array(
			'type'     => '',
			'text'     => $field['field_label'],
			'order'    => $order + 1,
			'required' => $field['is_required'] ? 'Yes' : 'No',
		);

		// Map field types to JotForm control types
		switch ( $field['field_type'] ) {
			case 'text':
				$question['type'] = 'control_textbox';
				break;

			case 'email':
				$question['type'] = 'control_email';
				break;

			case 'phone':
				$question['type'] = 'control_phone';
				break;

			case 'number':
				$question['type'] = 'control_number';
				break;

			case 'textarea':
				$question['type'] = 'control_textarea';
				break;

			case 'select':
				$question['type'] = 'control_dropdown';
				$options = json_decode( $field['field_options'], true );
				$question['options'] = implode( '|', $options );
				break;

			case 'radio':
				$question['type'] = 'control_radio';
				$options = json_decode( $field['field_options'], true );
				$question['options'] = implode( '|', $options );
				break;

			case 'checkbox':
				$question['type'] = 'control_checkbox';
				$options = json_decode( $field['field_options'], true );
				$question['options'] = implode( '|', $options );
				break;

			case 'date':
				$question['type'] = 'control_datetime';
				break;

			case 'file':
				$question['type'] = 'control_fileupload';
				break;

			default:
				return null; // Unsupported type
		}

		return $question;
	}

	/**
	 * Update an existing form.
	 */
	public function update_form( $provider_form_id, $form_data, $fields ) {
		$properties = array(
			'title' => $form_data['form_name'],
		);

		$result = $this->api_request(
			'POST',
			"/form/{$provider_form_id}/properties",
			array( 'properties' => $properties )
		);

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
		$result = $this->api_request( 'DELETE', "/form/{$provider_form_id}" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Get form details.
	 */
	public function get_form( $provider_form_id ) {
		$result = $this->api_request( 'GET', "/form/{$provider_form_id}" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$form = $result['content'];

		// Get form questions
		$questions_result = $this->api_request( 'GET', "/form/{$provider_form_id}/questions" );
		$fields           = array();

		if ( ! is_wp_error( $questions_result ) ) {
			$fields = $this->parse_jotform_questions( $questions_result['content'] );
		}

		return array(
			'form_id'     => $form['id'],
			'title'       => $form['title'],
			'description' => '',
			'form_url'    => $form['url'],
			'fields'      => $fields,
		);
	}

	/**
	 * Parse JotForm questions into our format.
	 */
	private function parse_jotform_questions( $questions ) {
		$fields = array();

		foreach ( $questions as $qid => $question ) {
			// Skip control types that aren't actual fields
			if ( in_array( $question['type'], array( 'control_head', 'control_button', 'control_pagebreak' ), true ) ) {
				continue;
			}

			$field = array(
				'field_name'  => $qid,
				'field_label' => $question['text'],
				'is_required' => ( $question['required'] ?? 'No' ) === 'Yes',
			);

			// Map JotForm types to our types
			$type_map = array(
				'control_textbox'    => 'text',
				'control_email'      => 'email',
				'control_phone'      => 'phone',
				'control_number'     => 'number',
				'control_textarea'   => 'textarea',
				'control_dropdown'   => 'select',
				'control_radio'      => 'radio',
				'control_checkbox'   => 'checkbox',
				'control_datetime'   => 'date',
				'control_fileupload' => 'file',
			);

			$field['field_type'] = $type_map[ $question['type'] ] ?? 'text';

			// Parse options for choice fields
			if ( in_array( $question['type'], array( 'control_dropdown', 'control_radio', 'control_checkbox' ), true ) ) {
				if ( ! empty( $question['options'] ) ) {
					$field['field_options'] = explode( '|', $question['options'] );
				}
			}

			$fields[] = $field;
		}

		return $fields;
	}

	/**
	 * Get form submissions.
	 */
	public function get_submissions( $provider_form_id, $args = array() ) {
		$limit  = $args['limit'] ?? 100;
		$offset = $args['offset'] ?? 0;

		$result = $this->api_request(
			'GET',
			"/form/{$provider_form_id}/submissions?limit={$limit}&offset={$offset}"
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$submissions = array();
		foreach ( $result['content'] ?? array() as $submission ) {
			$submissions[] = $this->parse_jotform_submission( $submission );
		}

		return $submissions;
	}

	/**
	 * Get a single submission.
	 */
	public function get_submission( $provider_form_id, $provider_submission_id ) {
		$result = $this->api_request( 'GET', "/submission/{$provider_submission_id}" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->parse_jotform_submission( $result['content'] );
	}

	/**
	 * Parse JotForm submission into our format.
	 */
	private function parse_jotform_submission( $submission ) {
		$data = array(
			'provider_submission_id' => $submission['id'],
			'submitted_at'           => $submission['created_at'],
			'ip_address'             => $submission['ip'] ?? '',
			'fields'                 => array(),
		);

		foreach ( $submission['answers'] ?? array() as $qid => $answer ) {
			// Skip non-answer fields
			if ( ! is_array( $answer ) || ! isset( $answer['answer'] ) ) {
				continue;
			}

			$field_data = array(
				'field_name'  => $qid,
				'field_value' => is_array( $answer['answer'] ) ? implode( ', ', $answer['answer'] ) : $answer['answer'],
			);

			// Handle file uploads
			if ( $answer['type'] === 'control_fileupload' && ! empty( $answer['answer'] ) ) {
				$field_data['file_url'] = is_array( $answer['answer'] ) ? $answer['answer'][0] : $answer['answer'];
			}

			$data['fields'][] = $field_data;
		}

		return $data;
	}

	/**
	 * Get form analytics.
	 */
	public function get_form_analytics( $provider_form_id ) {
		// Get form properties which include submission count
		$result = $this->api_request( 'GET', "/form/{$provider_form_id}/properties" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'total_submissions' => (int) ( $result['content']['count'] ?? 0 ),
			'total_views'       => (int) ( $result['content']['views'] ?? 0 ),
			'form_id'          => $provider_form_id,
		);
	}

	/**
	 * Get embed code for the form.
	 */
	public function get_embed_code( $provider_form_id, $options = array() ) {
		$height = $options['height'] ?? 539;
		$width  = $options['width'] ?? '100%';

		return sprintf(
			'<iframe id="JotFormIFrame-%s" title="Form" onload="window.parent.scrollTo(0,0)" allowtransparency="true" allowfullscreen="true" allow="geolocation; microphone; camera" src="https://form.jotform.com/%s" frameborder="0" style="min-width:%s;max-width:100%%;height:%dpx;border:none;" scrolling="no"></iframe>',
			$provider_form_id,
			$provider_form_id,
			is_numeric( $width ) ? $width . 'px' : $width,
			$height
		);
	}

	/**
	 * Validate webhook signature.
	 */
	public function validate_webhook_signature( $payload, $signature ) {
		// JotForm doesn't use HMAC signatures
		// Validation is done via checking the submission data
		return true;
	}

	/**
	 * Process webhook from JotForm.
	 */
	public function process_webhook( $webhook_data ) {
		// JotForm webhooks send rawRequest parameter with submission data
		if ( isset( $webhook_data['rawRequest'] ) ) {
			$submission = json_decode( $webhook_data['rawRequest'], true );
			return $this->parse_jotform_submission( $submission );
		}

		// Or they send submission ID that we need to fetch
		if ( isset( $webhook_data['submissionID'] ) ) {
			$form_id = $webhook_data['formID'] ?? '';
			return $this->get_submission( $form_id, $webhook_data['submissionID'] );
		}

		return new WP_Error( 'invalid_webhook', 'Invalid webhook data.' );
	}

	/**
	 * Test the API connection.
	 */
	public function test_connection() {
		$result = $this->api_request( 'GET', '/user' );
		return ! is_wp_error( $result );
	}

	/**
	 * Make an API request to JotForm.
	 */
	private function api_request( $method, $endpoint, $body = null ) {
		$url = $this->api_base . $endpoint;

		// Add API key to URL
		$separator = strpos( $url, '?' ) !== false ? '&' : '?';
		$url      .= $separator . 'apiKey=' . $this->api_key;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Content-Type' => 'application/x-www-form-urlencoded',
			),
			'timeout' => 30,
		);

		if ( $body !== null ) {
			// JotForm expects form-encoded data
			$args['body'] = http_build_query( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'api_error',
				$data['message'] ?? 'Unknown API error',
				array( 'status' => $code )
			);
		}

		// JotForm wraps responses in responseCode and content
		if ( isset( $data['responseCode'] ) && $data['responseCode'] !== 200 ) {
			return new WP_Error(
				'api_error',
				$data['message'] ?? 'API request failed',
				array( 'status' => $data['responseCode'] )
			);
		}

		return $data;
	}
}
