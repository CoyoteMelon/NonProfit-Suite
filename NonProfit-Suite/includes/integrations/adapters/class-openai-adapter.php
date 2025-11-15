<?php
/**
 * OpenAI Adapter
 *
 * Handles integration with OpenAI API (GPT-4, GPT-3.5, etc.).
 * Uses API key for authentication.
 *
 * @package NonprofitSuite
 * @subpackage Integrations
 */

class NS_OpenAI_Adapter implements NS_AI_Adapter {
	private $api_key;
	private $model;
	private $api_base = 'https://api.openai.com/v1';

	/**
	 * Constructor.
	 *
	 * @param string $api_key OpenAI API key.
	 * @param string $model Model name (gpt-4, gpt-3.5-turbo, etc.).
	 */
	public function __construct( $api_key, $model = 'gpt-4' ) {
		$this->api_key = $api_key;
		$this->model   = $model;
	}

	/**
	 * Send a chat completion request.
	 */
	public function chat_completion( $messages, $options = array() ) {
		$body = array(
			'model'    => $options['model'] ?? $this->model,
			'messages' => $messages,
		);

		if ( isset( $options['temperature'] ) ) {
			$body['temperature'] = (float) $options['temperature'];
		}

		if ( isset( $options['max_tokens'] ) ) {
			$body['max_tokens'] = (int) $options['max_tokens'];
		}

		if ( isset( $options['functions'] ) ) {
			$body['functions'] = $options['functions'];
		}

		$result = $this->api_request( 'POST', '/chat/completions', $body );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$choice = $result['choices'][0];
		$usage  = $result['usage'];

		return array(
			'content'       => $choice['message']['content'] ?? null,
			'function_call' => $choice['message']['function_call'] ?? null,
			'finish_reason' => $choice['finish_reason'],
			'tokens'        => array(
				'prompt'     => $usage['prompt_tokens'],
				'completion' => $usage['completion_tokens'],
				'total'      => $usage['total_tokens'],
			),
			'cost'          => $this->calculate_cost( $usage['prompt_tokens'], $usage['completion_tokens'] ),
		);
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
			"Extract the following fields from the text and return as JSON:\n\n%s\n\nText:\n%s",
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
			"Analyze the sentiment of the following text and return a JSON object with 'sentiment' (positive/negative/neutral) and 'score' (0-1):\n\n%s",
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
	 */
	public function create_embedding( $text ) {
		$body = array(
			'model' => 'text-embedding-ada-002',
			'input' => $text,
		);

		$result = $this->api_request( 'POST', '/embeddings', $body );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result['data'][0]['embedding'];
	}

	/**
	 * Moderate content.
	 */
	public function moderate_content( $content ) {
		$body = array(
			'input' => $content,
		);

		$result = $this->api_request( 'POST', '/moderations', $body );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result['results'][0];
	}

	/**
	 * Function calling.
	 */
	public function function_call( $messages, $functions, $options = array() ) {
		$options['functions'] = $functions;
		return $this->chat_completion( $messages, $options );
	}

	/**
	 * Calculate cost based on pricing.
	 */
	public function calculate_cost( $input_tokens, $output_tokens ) {
		// Pricing as of 2024 (per 1000 tokens)
		$pricing = array(
			'gpt-4'              => array( 'input' => 0.03, 'output' => 0.06 ),
			'gpt-4-32k'          => array( 'input' => 0.06, 'output' => 0.12 ),
			'gpt-3.5-turbo'      => array( 'input' => 0.0015, 'output' => 0.002 ),
			'gpt-3.5-turbo-16k'  => array( 'input' => 0.003, 'output' => 0.004 ),
		);

		$rates = $pricing[ $this->model ] ?? $pricing['gpt-3.5-turbo'];

		$input_cost  = ( $input_tokens / 1000 ) * $rates['input'];
		$output_cost = ( $output_tokens / 1000 ) * $rates['output'];

		return round( $input_cost + $output_cost, 4 );
	}

	/**
	 * Count tokens (approximate).
	 */
	public function count_tokens( $text ) {
		// Rough approximation: 1 token ≈ 4 characters
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
			'gpt-4'              => 'GPT-4 (most capable, higher cost)',
			'gpt-4-32k'          => 'GPT-4 32K (extended context)',
			'gpt-3.5-turbo'      => 'GPT-3.5 Turbo (fast, cost-effective)',
			'gpt-3.5-turbo-16k'  => 'GPT-3.5 Turbo 16K (extended context)',
		);
	}

	/**
	 * Make an API request to OpenAI.
	 */
	private function api_request( $method, $endpoint, $body = null ) {
		$url = $this->api_base . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
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
