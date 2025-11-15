<?php
/**
 * Google Forms Adapter
 *
 * Handles integration with Google Forms API.
 * Uses OAuth 2.0 for authentication and Google Forms API for form management.
 *
 * @package NonprofitSuite
 * @subpackage Integrations
 */

class NS_Google_Forms_Adapter implements NS_Form_Adapter {
	private $access_token;
	private $refresh_token;
	private $api_base = 'https://forms.googleapis.com/v1';

	/**
	 * Constructor.
	 *
	 * @param string $access_token OAuth access token.
	 * @param string $refresh_token OAuth refresh token.
	 */
	public function __construct( $access_token, $refresh_token = '' ) {
		$this->access_token  = $access_token;
		$this->refresh_token = $refresh_token;
	}

	/**
	 * Create a new form in Google Forms.
	 */
	public function create_form( $form_data, $fields ) {
		$body = array(
			'info' => array(
				'title'       => $form_data['form_name'],
				'documentTitle' => $form_data['form_name'],
			),
		);

		if ( ! empty( $form_data['description'] ) ) {
			$body['info']['description'] = $form_data['description'];
		}

		$result = $this->api_request( 'POST', '/forms', $body );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Add fields to the form
		if ( ! empty( $fields ) ) {
			$this->add_fields_to_form( $result['formId'], $fields );
		}

		return array(
			'provider_form_id' => $result['formId'],
			'form_url'         => $result['responderUri'],
			'edit_url'         => "https://docs.google.com/forms/d/{$result['formId']}/edit",
		);
	}

	/**
	 * Add fields to a Google Form.
	 */
	private function add_fields_to_form( $form_id, $fields ) {
		$requests = array();

		foreach ( $fields as $index => $field ) {
			$item = array(
				'title'    => $field['field_label'],
				'required' => (bool) $field['is_required'],
			);

			// Map field types to Google Forms question types
			switch ( $field['field_type'] ) {
				case 'text':
				case 'email':
					$item['questionItem'] = array(
						'question' => array(
							'required'     => (bool) $field['is_required'],
							'textQuestion' => array(
								'paragraph' => false,
							),
						),
					);
					break;

				case 'textarea':
					$item['questionItem'] = array(
						'question' => array(
							'required'     => (bool) $field['is_required'],
							'textQuestion' => array(
								'paragraph' => true,
							),
						),
					);
					break;

				case 'select':
				case 'radio':
					$options = json_decode( $field['field_options'], true );
					$choices = array();
					foreach ( $options as $option ) {
						$choices[] = array( 'value' => $option );
					}

					$item['questionItem'] = array(
						'question' => array(
							'required'       => (bool) $field['is_required'],
							'choiceQuestion' => array(
								'type'    => 'RADIO',
								'options' => $choices,
							),
						),
					);
					break;

				case 'checkbox':
					$options = json_decode( $field['field_options'], true );
					$choices = array();
					foreach ( $options as $option ) {
						$choices[] = array( 'value' => $option );
					}

					$item['questionItem'] = array(
						'question' => array(
							'required'       => (bool) $field['is_required'],
							'choiceQuestion' => array(
								'type'    => 'CHECKBOX',
								'options' => $choices,
							),
						),
					);
					break;

				case 'date':
					$item['questionItem'] = array(
						'question' => array(
							'required'     => (bool) $field['is_required'],
							'dateQuestion' => array(
								'includeTime' => false,
							),
						),
					);
					break;

				case 'rating':
					$item['questionItem'] = array(
						'question' => array(
							'required'      => (bool) $field['is_required'],
							'scaleQuestion' => array(
								'low'  => 1,
								'high' => 5,
							),
						),
					);
					break;
			}

			$requests[] = array(
				'createItem' => array(
					'item'     => $item,
					'location' => array(
						'index' => $index,
					),
				),
			);
		}

		// Batch update to add all fields
		if ( ! empty( $requests ) ) {
			$this->api_request(
				'POST',
				"/forms/{$form_id}:batchUpdate",
				array( 'requests' => $requests )
			);
		}
	}

	/**
	 * Update an existing form.
	 */
	public function update_form( $provider_form_id, $form_data, $fields ) {
		$requests = array();

		// Update form title and description
		$requests[] = array(
			'updateFormInfo' => array(
				'info' => array(
					'title'       => $form_data['form_name'],
					'description' => $form_data['description'] ?? '',
				),
				'updateMask' => 'title,description',
			),
		);

		$result = $this->api_request(
			'POST',
			"/forms/{$provider_form_id}:batchUpdate",
			array( 'requests' => $requests )
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
	 * Delete a form (Google Forms doesn't support deletion via API, only trashing).
	 */
	public function delete_form( $provider_form_id ) {
		// Google Forms API doesn't support deletion
		// Forms must be deleted manually through Drive API or Google Drive interface
		return new WP_Error(
			'not_supported',
			'Google Forms API does not support form deletion. Please delete the form manually in Google Drive.'
		);
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
			'form_id'     => $result['formId'],
			'title'       => $result['info']['title'],
			'description' => $result['info']['description'] ?? '',
			'form_url'    => $result['responderUri'],
			'fields'      => $this->parse_form_fields( $result['items'] ?? array() ),
		);
	}

	/**
	 * Parse Google Forms fields into our format.
	 */
	private function parse_form_fields( $items ) {
		$fields = array();

		foreach ( $items as $item ) {
			if ( ! isset( $item['questionItem'] ) ) {
				continue;
			}

			$question = $item['questionItem']['question'];
			$field    = array(
				'field_name'  => $item['itemId'],
				'field_label' => $item['title'],
				'is_required' => $question['required'] ?? false,
			);

			// Determine field type
			if ( isset( $question['textQuestion'] ) ) {
				$field['field_type'] = $question['textQuestion']['paragraph'] ? 'textarea' : 'text';
			} elseif ( isset( $question['choiceQuestion'] ) ) {
				$type = $question['choiceQuestion']['type'];
				$field['field_type'] = ( $type === 'CHECKBOX' ) ? 'checkbox' : 'radio';
				
				$options = array();
				foreach ( $question['choiceQuestion']['options'] as $option ) {
					$options[] = $option['value'];
				}
				$field['field_options'] = $options;
			} elseif ( isset( $question['dateQuestion'] ) ) {
				$field['field_type'] = 'date';
			} elseif ( isset( $question['scaleQuestion'] ) ) {
				$field['field_type'] = 'rating';
			}

			$fields[] = $field;
		}

		return $fields;
	}

	/**
	 * Get form submissions (responses).
	 */
	public function get_submissions( $provider_form_id, $args = array() ) {
		$result = $this->api_request( 'GET', "/forms/{$provider_form_id}/responses" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$submissions = array();
		foreach ( $result['responses'] ?? array() as $response ) {
			$submissions[] = $this->parse_submission( $response );
		}

		return $submissions;
	}

	/**
	 * Get a single submission.
	 */
	public function get_submission( $provider_form_id, $provider_submission_id ) {
		$result = $this->api_request(
			'GET',
			"/forms/{$provider_form_id}/responses/{$provider_submission_id}"
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->parse_submission( $result );
	}

	/**
	 * Parse a Google Forms response into our format.
	 */
	private function parse_submission( $response ) {
		$data = array(
			'provider_submission_id' => $response['responseId'],
			'submitted_at'           => $response['createTime'],
			'updated_at'             => $response['lastSubmittedTime'] ?? $response['createTime'],
			'fields'                 => array(),
		);

		foreach ( $response['answers'] ?? array() as $question_id => $answer ) {
			$field_data = array(
				'field_name' => $question_id,
			);

			if ( isset( $answer['textAnswers'] ) ) {
				$field_data['field_value'] = $answer['textAnswers']['answers'][0]['value'] ?? '';
			} elseif ( isset( $answer['fileUploadAnswers'] ) ) {
				$files = array();
				foreach ( $answer['fileUploadAnswers']['answers'] ?? array() as $file ) {
					$files[] = $file['fileId'];
				}
				$field_data['field_value'] = implode( ',', $files );
			}

			$data['fields'][] = $field_data;
		}

		return $data;
	}

	/**
	 * Get form analytics.
	 */
	public function get_form_analytics( $provider_form_id ) {
		// Google Forms API doesn't provide analytics directly
		// Would need to use Google Analytics API or count submissions
		$submissions = $this->get_submissions( $provider_form_id );

		if ( is_wp_error( $submissions ) ) {
			return $submissions;
		}

		return array(
			'total_submissions' => count( $submissions ),
			'form_id'          => $provider_form_id,
		);
	}

	/**
	 * Get embed code for the form.
	 */
	public function get_embed_code( $provider_form_id, $options = array() ) {
		$width  = $options['width'] ?? 640;
		$height = $options['height'] ?? 800;

		return sprintf(
			'<iframe src="https://docs.google.com/forms/d/e/%s/viewform?embedded=true" width="%d" height="%d" frameborder="0" marginheight="0" marginwidth="0">Loading…</iframe>',
			$provider_form_id,
			$width,
			$height
		);
	}

	/**
	 * Validate webhook signature.
	 */
	public function validate_webhook_signature( $payload, $signature ) {
		// Google Forms doesn't have webhook functionality
		return false;
	}

	/**
	 * Process webhook (not supported by Google Forms).
	 */
	public function process_webhook( $webhook_data ) {
		return new WP_Error( 'not_supported', 'Google Forms does not support webhooks.' );
	}

	/**
	 * Test the API connection.
	 */
	public function test_connection() {
		$result = $this->api_request( 'GET', '/forms' );
		return ! is_wp_error( $result );
	}

	/**
	 * Make an API request to Google Forms.
	 */
	private function api_request( $method, $endpoint, $body = null ) {
		$url = $this->api_base . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->access_token,
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
		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'api_error',
				$data['error']['message'] ?? 'Unknown API error',
				array( 'status' => $code )
			);
		}

		return $data;
	}
}
