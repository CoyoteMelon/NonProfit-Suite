<?php
/**
 * Anthropic Adapter
 *
 * Handles integration with Anthropic API (Claude models).
 * Uses API key for authentication.
 *
 * @package NonprofitSuite
 * @subpackage Integrations
 */

class NS_Anthropic_Adapter implements NS_AI_Adapter {
	private $api_key;
	private $model;
	private $api_base = 'https://api.anthropic.com/v1';

	/**
	 * Constructor.
	 *
	 * @param string $api_key Anthropic API key.
	 * @param string $model Model name (claude-3-opus, claude-3-sonnet, claude-3-haiku).
	 */
	public function __construct( $api_key, $model = 'claude-3-sonnet-20240229' ) {
		$this->api_key = $api_key;
		$this->model   = $model;
	}

	/**
	 * Send a chat completion request.
	 */
	public function chat_completion( $messages, $options = array() ) {
		$body = array(
			'model'      => $options['model'] ?? $this->model,
			'max_tokens' => $options['max_tokens'] ?? 1000,
			'messages'   => $this->convert_messages( $messages ),
		);

		if ( isset( $options['system'] ) ) {
			$body['system'] = $options['system'];
		}

		if ( isset( $options['temperature'] ) ) {
			$body['temperature'] = (float) $options['temperature'];
		}

		$result = $this->api_request( 'POST', '/messages', $body );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$content = '';
		foreach ( $result['content'] as $block ) {
			if ( $block['type'] === 'text' ) {
				$content .= $block['text'];
			}
		}

		$input_tokens  = $result['usage']['input_tokens'];
		$output_tokens = $result['usage']['output_tokens'];

		return array(
			'content'       => $content,
			'finish_reason' => $result['stop_reason'],
			'tokens'        => array(
				'prompt'     => $input_tokens,
				'completion' => $output_tokens,
				'total'      => $input_tokens + $output_tokens,
			),
			'cost'          => $this->calculate_cost( $input_tokens, $output_tokens ),
		);
	}

	/**
	 * Convert OpenAI-style messages to Anthropic format.
	 */
	private function convert_messages( $messages ) {
		$converted = array();

		foreach ( $messages as $message ) {
			// Skip system messages (handled separately in Anthropic)
			if ( $message['role'] === 'system' ) {
				continue;
			}

			$converted[] = array(
				'role'    => $message['role'] === 'assistant' ? 'assistant' : 'user',
				'content' => $message['content'],
			);
		}

		return $converted;
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
			"Extract the following fields from the text and return as JSON:\n\n%s\n\nText:\n%s\n\nReturn only valid JSON, no explanation.",
			implode( "\n", $field_descriptions ),
			$content
		);

		$result = $this->complete( $prompt, array( 'temperature' => 0.1 ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Parse JSON response
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
			"Categorize the following content into ONE of these categories: %s\n\nReturn only the category name, nothing else.\n\nContent:\n%s",
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
			// Fallback parsing
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
	 * Note: Anthropic doesn't provide embeddings API as of now.
	 */
	public function create_embedding( $text ) {
		return new WP_Error( 'not_supported', 'Anthropic does not currently provide embeddings API.' );
	}

	/**
	 * Moderate content.
	 * Note: Use Claude's built-in safety features via prompting.
	 */
	public function moderate_content( $content ) {
		$prompt = sprintf(
			"Analyze this content for safety concerns (hate speech, violence, explicit content, etc). Return JSON with 'flagged' (boolean) and 'categories' (array of concerns):\n\n%s",
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
	 * Note: Anthropic uses tool use, which is similar to function calling.
	 */
	public function function_call( $messages, $functions, $options = array() ) {
		// Convert functions to Anthropic tools format
		$tools = array();
		foreach ( $functions as $function ) {
			$tools[] = array(
				'name'        => $function['name'],
				'description' => $function['description'],
				'input_schema' => $function['parameters'] ?? array(),
			);
		}

		$options['tools'] = $tools;
		return $this->chat_completion( $messages, $options );
	}

	/**
	 * Calculate cost based on pricing.
	 */
	public function calculate_cost( $input_tokens, $output_tokens ) {
		// Pricing as of 2024 (per million tokens)
		$pricing = array(
			'claude-3-opus-20240229'   => array( 'input' => 15.00, 'output' => 75.00 ),
			'claude-3-sonnet-20240229' => array( 'input' => 3.00, 'output' => 15.00 ),
			'claude-3-haiku-20240307'  => array( 'input' => 0.25, 'output' => 1.25 ),
		);

		$rates = $pricing[ $this->model ] ?? $pricing['claude-3-sonnet-20240229'];

		$input_cost  = ( $input_tokens / 1000000 ) * $rates['input'];
		$output_cost = ( $output_tokens / 1000000 ) * $rates['output'];

		return round( $input_cost + $output_cost, 4 );
	}

	/**
	 * Count tokens (approximate).
	 */
	public function count_tokens( $text ) {
		// Rough approximation: 1 token ≈ 4 characters (similar to OpenAI)
		return (int) ceil( strlen( $text ) / 4 );
	}

	/**
	 * Test the API connection.
	 */
	public function test_connection() {
		$result = $this->complete( 'Say "OK" if you can read this.', array( 'max_tokens' => 10 ) );
		return ! is_wp_error( $result );
	}

	/**
	 * Get available models.
	 */
	public function get_available_models() {
		return array(
			'claude-3-opus-20240229'   => 'Claude 3 Opus (most capable)',
			'claude-3-sonnet-20240229' => 'Claude 3 Sonnet (balanced performance and speed)',
			'claude-3-haiku-20240307'  => 'Claude 3 Haiku (fastest, most cost-effective)',
		);
	}

	/**
	 * Make an API request to Anthropic.
	 */
	private function api_request( $method, $endpoint, $body = null ) {
		$url = $this->api_base . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'x-api-key'         => $this->api_key,
				'anthropic-version' => '2023-06-01',
				'Content-Type'      => 'application/json',
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
