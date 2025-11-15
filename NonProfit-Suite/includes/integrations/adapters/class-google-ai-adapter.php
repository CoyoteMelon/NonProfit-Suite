<?php
/**
 * Google AI Adapter
 *
 * Handles integration with Google AI API (Gemini models).
 * Uses API key for authentication.
 *
 * @package NonprofitSuite
 * @subpackage Integrations
 */

class NS_Google_AI_Adapter implements NS_AI_Adapter {
	private $api_key;
	private $model;
	private $api_base = 'https://generativelanguage.googleapis.com/v1';

	/**
	 * Constructor.
	 *
	 * @param string $api_key Google AI API key.
	 * @param string $model Model name (gemini-pro, gemini-pro-vision).
	 */
	public function __construct( $api_key, $model = 'gemini-pro' ) {
		$this->api_key = $api_key;
		$this->model   = $model;
	}

	/**
	 * Send a chat completion request.
	 */
	public function chat_completion( $messages, $options = array() ) {
		$model = $options['model'] ?? $this->model;

		// Convert messages to Gemini format
		$contents = $this->convert_messages( $messages );

		$body = array(
			'contents' => $contents,
		);

		if ( isset( $options['temperature'] ) ) {
			$body['generationConfig'] = array(
				'temperature' => (float) $options['temperature'],
			);
		}

		if ( isset( $options['max_tokens'] ) ) {
			$body['generationConfig']['maxOutputTokens'] = (int) $options['max_tokens'];
		}

		$result = $this->api_request( 'POST', "/models/{$model}:generateContent", $body );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$candidate = $result['candidates'][0];
		$content   = '';

		foreach ( $candidate['content']['parts'] as $part ) {
			if ( isset( $part['text'] ) ) {
				$content .= $part['text'];
			}
		}

		$input_tokens  = $result['usageMetadata']['promptTokenCount'] ?? 0;
		$output_tokens = $result['usageMetadata']['candidatesTokenCount'] ?? 0;

		return array(
			'content'       => $content,
			'finish_reason' => $candidate['finishReason'] ?? 'STOP',
			'tokens'        => array(
				'prompt'     => $input_tokens,
				'completion' => $output_tokens,
				'total'      => $input_tokens + $output_tokens,
			),
			'cost'          => $this->calculate_cost( $input_tokens, $output_tokens ),
		);
	}

	/**
	 * Convert messages to Gemini format.
	 */
	private function convert_messages( $messages ) {
		$contents = array();

		foreach ( $messages as $message ) {
			$role = $message['role'] === 'assistant' ? 'model' : 'user';

			$contents[] = array(
				'role'  => $role,
				'parts' => array(
					array( 'text' => $message['content'] ),
				),
			);
		}

		return $contents;
	}

	/**
	 * Send a single prompt (simplified).
	 */
	public function complete( $prompt, $options = array() ) {
		$messages = array(
			array(
				'role'    => 'user',
				'content' => $prompt,
			),
		);

		$result = $this->chat_completion( $messages, $options );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result['content'];
	}

	/**
	 * Summarize text content.
	 */
	public function summarize( $content, $max_length = 100 ) {
		$prompt = sprintf(
			"Summarize the following content in no more than %d words:\n\n%s",
			$max_length,
			$content
		);

		return $this->complete( $prompt, array( 'temperature' => 0.3 ) );
	}

	/**
	 * Extract structured data from text.
	 */
	public function extract_data( $content, $fields ) {
		$field_descriptions = array();
		foreach ( $fields as $field ) {
			$field_descriptions[] = sprintf(
				'- %s (%s): %s',
				$field['name'],
				$field['type'],
				$field['description']
			);
		}

		$prompt = sprintf(
			"Extract the following fields from the text and return as JSON:\n\n%s\n\nText:\n%s\n\nReturn only valid JSON.",
			implode( "\n", $field_descriptions ),
			$content
		);

		$result = $this->complete( $prompt, array( 'temperature' => 0.1 ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$data = json_decode( $result, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'parse_error', 'Failed to parse AI response as JSON.' );
		}

		return $data;
	}

	/**
	 * Categorize content.
	 */
	public function categorize( $content, $categories ) {
		$prompt = sprintf(
			"Categorize the following content into ONE of these categories: %s\n\nReturn only the category name.\n\nContent:\n%s",
			implode( ', ', $categories ),
			$content
		);

		$result = $this->complete( $prompt, array( 'temperature' => 0.2, 'max_tokens' => 50 ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return trim( $result );
	}

	/**
	 * Analyze sentiment.
	 */
	public function analyze_sentiment( $content ) {
		$prompt = sprintf(
			"Analyze the sentiment of the following text and return a JSON object with 'sentiment' (positive/negative/neutral) and 'score' (0-1):\n\n%s\n\nReturn only valid JSON.",
			$content
		);

		$result = $this->complete( $prompt, array( 'temperature' => 0.1 ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$data = json_decode( $result, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			if ( stripos( $result, 'positive' ) !== false ) {
				return array( 'sentiment' => 'positive', 'score' => 0.7 );
			} elseif ( stripos( $result, 'negative' ) !== false ) {
				return array( 'sentiment' => 'negative', 'score' => 0.7 );
			} else {
				return array( 'sentiment' => 'neutral', 'score' => 0.5 );
			}
		}

		return $data;
	}

	/**
	 * Create text embedding.
	 */
	public function create_embedding( $text ) {
		$body = array(
			'content' => array(
				'parts' => array(
					array( 'text' => $text ),
				),
			),
		);

		$result = $this->api_request( 'POST', '/models/embedding-001:embedContent', $body );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result['embedding']['values'];
	}

	/**
	 * Moderate content.
	 */
	public function moderate_content( $content ) {
		// Use Gemini's safety settings
		$prompt = sprintf(
			"Analyze this content for safety concerns. Return JSON with 'flagged' (boolean) and 'categories' (array):\n\n%s",
			$content
		);

		$result = $this->complete( $prompt, array( 'temperature' => 0.1 ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$data = json_decode( $result, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return array( 'flagged' => false, 'categories' => array() );
		}

		return $data;
	}

	/**
	 * Function calling.
	 */
	public function function_call( $messages, $functions, $options = array() ) {
		// Gemini supports function calling
		$tools = array(
			'functionDeclarations' => array(),
		);

		foreach ( $functions as $function ) {
			$tools['functionDeclarations'][] = array(
				'name'        => $function['name'],
				'description' => $function['description'],
				'parameters'  => $function['parameters'] ?? array(),
			);
		}

		$options['tools'] = array( $tools );
		return $this->chat_completion( $messages, $options );
	}

	/**
	 * Calculate cost.
	 */
	public function calculate_cost( $input_tokens, $output_tokens ) {
		// Gemini Pro pricing (per million tokens)
		$input_cost  = ( $input_tokens / 1000000 ) * 0.50;
		$output_cost = ( $output_tokens / 1000000 ) * 1.50;

		return round( $input_cost + $output_cost, 4 );
	}

	/**
	 * Count tokens (approximate).
	 */
	public function count_tokens( $text ) {
		return (int) ceil( strlen( $text ) / 4 );
	}

	/**
	 * Test the API connection.
	 */
	public function test_connection() {
		$result = $this->complete( 'Say "OK"', array( 'max_tokens' => 10 ) );
		return ! is_wp_error( $result );
	}

	/**
	 * Get available models.
	 */
	public function get_available_models() {
		return array(
			'gemini-pro'        => 'Gemini Pro (text generation)',
			'gemini-pro-vision' => 'Gemini Pro Vision (text + image)',
		);
	}

	/**
	 * Make an API request to Google AI.
	 */
	private function api_request( $method, $endpoint, $body = null ) {
		$url = $this->api_base . $endpoint . '?key=' . $this->api_key;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'timeout' => 60,
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
