<?php
/**
 * AI Adapter Interface
 *
 * Defines the contract for AI provider integrations.
 * All AI adapters must implement this interface.
 *
 * @package NonprofitSuite
 * @subpackage Integrations
 */

interface NS_AI_Adapter {
	/**
	 * Send a chat completion request to the AI provider.
	 *
	 * @param array $messages Array of message objects with 'role' and 'content'.
	 * @param array $options Optional parameters (temperature, max_tokens, etc).
	 * @return array|WP_Error Response with 'content', 'tokens', 'cost', or WP_Error on failure.
	 */
	public function chat_completion( $messages, $options = array() );

	/**
	 * Send a single prompt to the AI provider (simplified interface).
	 *
	 * @param string $prompt The prompt text.
	 * @param array  $options Optional parameters.
	 * @return string|WP_Error Response content or WP_Error on failure.
	 */
	public function complete( $prompt, $options = array() );

	/**
	 * Analyze and summarize text content.
	 *
	 * @param string $content Content to summarize.
	 * @param int    $max_length Maximum summary length in words.
	 * @return string|WP_Error Summary or WP_Error on failure.
	 */
	public function summarize( $content, $max_length = 100 );

	/**
	 * Extract structured data from text.
	 *
	 * @param string $content Content to extract from.
	 * @param array  $fields Fields to extract (name, description, type).
	 * @return array|WP_Error Extracted data or WP_Error on failure.
	 */
	public function extract_data( $content, $fields );

	/**
	 * Categorize content into predefined categories.
	 *
	 * @param string $content Content to categorize.
	 * @param array  $categories Available categories.
	 * @return string|WP_Error Category name or WP_Error on failure.
	 */
	public function categorize( $content, $categories );

	/**
	 * Analyze sentiment of text (positive, negative, neutral).
	 *
	 * @param string $content Content to analyze.
	 * @return array|WP_Error Sentiment with score and label, or WP_Error on failure.
	 */
	public function analyze_sentiment( $content );

	/**
	 * Generate embeddings for text (for semantic search).
	 *
	 * @param string $text Text to embed.
	 * @return array|WP_Error Vector embedding or WP_Error on failure.
	 */
	public function create_embedding( $text );

	/**
	 * Moderate content for safety/compliance.
	 *
	 * @param string $content Content to moderate.
	 * @return array|WP_Error Moderation results or WP_Error on failure.
	 */
	public function moderate_content( $content );

	/**
	 * Generate a response based on function calling.
	 *
	 * @param array $messages Conversation messages.
	 * @param array $functions Available functions with descriptions.
	 * @param array $options Optional parameters.
	 * @return array|WP_Error Function call or text response, or WP_Error on failure.
	 */
	public function function_call( $messages, $functions, $options = array() );

	/**
	 * Calculate estimated cost for a request.
	 *
	 * @param int $input_tokens Number of input tokens.
	 * @param int $output_tokens Number of output tokens.
	 * @return float Cost in USD.
	 */
	public function calculate_cost( $input_tokens, $output_tokens );

	/**
	 * Count tokens in text (approximate).
	 *
	 * @param string $text Text to count tokens for.
	 * @return int Approximate token count.
	 */
	public function count_tokens( $text );

	/**
	 * Test the API connection.
	 *
	 * @return bool|WP_Error True if connection successful, WP_Error on failure.
	 */
	public function test_connection();

	/**
	 * Get available models for this provider.
	 *
	 * @return array Array of model names with descriptions.
	 */
	public function get_available_models();
}
