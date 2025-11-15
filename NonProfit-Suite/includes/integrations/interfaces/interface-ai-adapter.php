<?php
/**
 * AI Adapter Interface
 *
 * Defines the contract for AI providers (OpenAI, Anthropic Claude, Google Gemini, Ollama, etc.)
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
 * AI Adapter Interface
 *
 * All AI adapters must implement this interface.
 */
interface NonprofitSuite_AI_Adapter_Interface {

	/**
	 * Generate text completion
	 *
	 * @param array $args Completion arguments
	 *                    - prompt: Prompt text (required)
	 *                    - system_message: System message/context (optional)
	 *                    - max_tokens: Maximum response tokens (optional)
	 *                    - temperature: Creativity level 0-1 (optional)
	 *                    - model: Specific model to use (optional)
	 * @return array|WP_Error Completion result with keys: text, tokens_used, model
	 */
	public function generate_text( $args );

	/**
	 * Generate chat completion (conversation)
	 *
	 * @param array $messages Array of message objects
	 *                        Each message: role (system|user|assistant), content
	 * @param array $args     Additional arguments
	 *                        - max_tokens: Maximum response tokens (optional)
	 *                        - temperature: Creativity level 0-1 (optional)
	 *                        - model: Specific model to use (optional)
	 * @return array|WP_Error Chat result with keys: message, tokens_used, model
	 */
	public function chat( $messages, $args = array() );

	/**
	 * Summarize text
	 *
	 * @param string $text Text to summarize
	 * @param array  $args Summarization arguments
	 *                     - max_length: Maximum summary length (optional)
	 *                     - style: brief, detailed, bullet_points (optional)
	 * @return string|WP_Error Summary text or WP_Error on failure
	 */
	public function summarize( $text, $args = array() );

	/**
	 * Extract key points from text
	 *
	 * @param string $text Text to analyze
	 * @param int    $num_points Number of key points to extract (optional)
	 * @return array|WP_Error Array of key points or WP_Error on failure
	 */
	public function extract_key_points( $text, $num_points = 5 );

	/**
	 * Generate meeting minutes from transcript
	 *
	 * @param string $transcript Meeting transcript
	 * @param array  $args       Generation arguments
	 *                           - format: standard, executive, detailed (optional)
	 *                           - include_action_items: Extract action items (optional)
	 * @return array|WP_Error Minutes data or WP_Error on failure
	 *                        - summary: Meeting summary
	 *                        - key_points: Array of key points
	 *                        - action_items: Array of action items (if requested)
	 *                        - decisions: Array of decisions made
	 */
	public function generate_meeting_minutes( $transcript, $args = array() );

	/**
	 * Transcribe audio to text
	 *
	 * @param string $audio_file Path to audio file
	 * @param array  $args       Transcription arguments
	 *                           - language: Language code (optional)
	 *                           - timestamps: Include timestamps (optional)
	 * @return array|WP_Error Transcription result with keys: text, duration
	 */
	public function transcribe_audio( $audio_file, $args = array() );

	/**
	 * Analyze sentiment of text
	 *
	 * @param string $text Text to analyze
	 * @return array|WP_Error Sentiment result or WP_Error on failure
	 *                        - sentiment: positive, negative, neutral
	 *                        - score: Confidence score 0-1
	 */
	public function analyze_sentiment( $text );

	/**
	 * Generate email draft
	 *
	 * @param array $args Email generation arguments
	 *                    - purpose: Purpose/context of email (required)
	 *                    - recipient: Recipient info (optional)
	 *                    - tone: formal, friendly, professional (optional)
	 *                    - key_points: Array of points to include (optional)
	 * @return array|WP_Error Email draft with keys: subject, body
	 */
	public function generate_email( $args );

	/**
	 * Answer questions about document content
	 *
	 * @param string $question Question to answer
	 * @param string $context  Document content/context
	 * @return string|WP_Error Answer or WP_Error on failure
	 */
	public function answer_question( $question, $context );

	/**
	 * Get token/usage information
	 *
	 * @param array $args Query arguments
	 *                    - start_date: Start date (optional)
	 *                    - end_date: End date (optional)
	 * @return array|WP_Error Usage data or WP_Error on failure
	 *                        - total_tokens: Total tokens used
	 *                        - total_requests: Total requests made
	 *                        - estimated_cost: Estimated cost (if available)
	 */
	public function get_usage( $args = array() );

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error True if connected, WP_Error on failure
	 */
	public function test_connection();

	/**
	 * Get provider name
	 *
	 * @return string Provider name (e.g., "OpenAI", "Claude")
	 */
	public function get_provider_name();
}
