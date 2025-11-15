<?php
/**
 * Built-in Forms Adapter
 *
 * Adapter for NonprofitSuite's built-in forms system.
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
 * NonprofitSuite_Forms_Builtin_Adapter Class
 *
 * Implements forms integration using a simple built-in forms system.
 */
class NonprofitSuite_Forms_Builtin_Adapter implements NonprofitSuite_Forms_Adapter_Interface {

	/**
	 * Create a form
	 *
	 * @param array $form_data Form data
	 * @return array|WP_Error Form data
	 */
	public function create_form( $form_data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_forms';

		$form = array(
			'title'       => $form_data['title'],
			'description' => isset( $form_data['description'] ) ? $form_data['description'] : '',
			'fields'      => json_encode( $form_data['fields'] ),
			'settings'    => isset( $form_data['settings'] ) ? json_encode( $form_data['settings'] ) : '{}',
			'status'      => 'active',
			'created_by'  => get_current_user_id(),
			'created_at'  => current_time( 'mysql' ),
		);

		$wpdb->insert( $table, $form );

		if ( $wpdb->last_error ) {
			return new WP_Error( 'db_error', $wpdb->last_error );
		}

		$form_id = $wpdb->insert_id;

		return array(
			'form_id' => $form_id,
			'url'     => add_query_arg( 'form_id', $form_id, home_url( '/forms/' ) ),
		);
	}

	/**
	 * Update a form
	 *
	 * @param string $form_id   Form identifier
	 * @param array  $form_data Updated form data
	 * @return array|WP_Error Updated form data
	 */
	public function update_form( $form_id, $form_data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_forms';

		$update_data = array();

		if ( isset( $form_data['title'] ) ) {
			$update_data['title'] = $form_data['title'];
		}
		if ( isset( $form_data['description'] ) ) {
			$update_data['description'] = $form_data['description'];
		}
		if ( isset( $form_data['fields'] ) ) {
			$update_data['fields'] = json_encode( $form_data['fields'] );
		}
		if ( isset( $form_data['settings'] ) ) {
			$update_data['settings'] = json_encode( $form_data['settings'] );
		}

		if ( empty( $update_data ) ) {
			return new WP_Error( 'no_data', __( 'No data to update', 'nonprofitsuite' ) );
		}

		$update_data['updated_at'] = current_time( 'mysql' );

		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $form_id ),
			null,
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'update_failed', __( 'Failed to update form', 'nonprofitsuite' ) );
		}

		return $this->get_form( $form_id );
	}

	/**
	 * Delete a form
	 *
	 * @param string $form_id Form identifier
	 * @return bool|WP_Error True on success
	 */
	public function delete_form( $form_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_forms';

		$result = $wpdb->delete(
			$table,
			array( 'id' => $form_id ),
			array( '%d' )
		);

		return false !== $result ? true : new WP_Error( 'delete_failed', __( 'Failed to delete form', 'nonprofitsuite' ) );
	}

	/**
	 * Get form details
	 *
	 * @param string $form_id Form identifier
	 * @return array|WP_Error Form data
	 */
	public function get_form( $form_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_forms';
		$form = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$form_id
		), ARRAY_A );

		if ( ! $form ) {
			return new WP_Error( 'form_not_found', __( 'Form not found', 'nonprofitsuite' ) );
		}

		// Decode JSON fields
		$form['fields'] = json_decode( $form['fields'], true );
		$form['settings'] = json_decode( $form['settings'], true );

		return $form;
	}

	/**
	 * List forms
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error Array of forms
	 */
	public function list_forms( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args( $args, array(
			'limit'  => 100,
			'offset' => 0,
		) );

		$table = $wpdb->prefix . 'ns_forms';
		$limit = (int) $args['limit'];
		$offset = (int) $args['offset'];

		$forms = $wpdb->get_results(
			"SELECT id, title, description, status, created_at FROM {$table} WHERE status = 'active' ORDER BY created_at DESC LIMIT {$offset}, {$limit}",
			ARRAY_A
		);

		return $forms ? $forms : array();
	}

	/**
	 * Get form responses/submissions
	 *
	 * @param string $form_id Form identifier
	 * @param array  $args    Query arguments
	 * @return array|WP_Error Array of responses
	 */
	public function get_responses( $form_id, $args = array() ) {
		global $wpdb;

		$args = wp_parse_args( $args, array(
			'limit' => 100,
			'since' => null,
			'until' => null,
		) );

		$table = $wpdb->prefix . 'ns_form_responses';
		$where = array( 'form_id = %d' );
		$prepare_args = array( $form_id );

		if ( $args['since'] ) {
			$where[] = 'submitted_at >= %s';
			$prepare_args[] = $args['since'];
		}

		if ( $args['until'] ) {
			$where[] = 'submitted_at <= %s';
			$prepare_args[] = $args['until'];
		}

		$where_clause = implode( ' AND ', $where );
		$limit = (int) $args['limit'];

		$query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY submitted_at DESC LIMIT {$limit}";
		$query = $wpdb->prepare( $query, $prepare_args );

		$responses = $wpdb->get_results( $query, ARRAY_A );

		// Decode response data
		foreach ( $responses as &$response ) {
			$response['data'] = json_decode( $response['data'], true );
		}

		return $responses ? $responses : array();
	}

	/**
	 * Get a single response
	 *
	 * @param string $form_id     Form identifier
	 * @param string $response_id Response identifier
	 * @return array|WP_Error Response data
	 */
	public function get_response( $form_id, $response_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_form_responses';
		$response = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d AND form_id = %d",
			$response_id,
			$form_id
		), ARRAY_A );

		if ( ! $response ) {
			return new WP_Error( 'response_not_found', __( 'Response not found', 'nonprofitsuite' ) );
		}

		$response['data'] = json_decode( $response['data'], true );

		return $response;
	}

	/**
	 * Get form statistics
	 *
	 * @param string $form_id Form identifier
	 * @return array|WP_Error Statistics
	 */
	public function get_form_stats( $form_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_form_responses';

		$total_responses = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE form_id = %d",
			$form_id
		) );

		return array(
			'total_responses' => (int) $total_responses,
			'completion_rate' => 100, // Simple forms don't track partial submissions
			'average_time'    => null, // Not tracked in simple implementation
		);
	}

	/**
	 * Get embed code for form
	 *
	 * @param string $form_id Form identifier
	 * @param array  $args    Embed arguments
	 * @return string|WP_Error Embed code
	 */
	public function get_embed_code( $form_id, $args = array() ) {
		$args = wp_parse_args( $args, array(
			'width'  => '100%',
			'height' => '600px',
		) );

		$form_url = add_query_arg( 'form_id', $form_id, home_url( '/forms/' ) );

		$embed_code = sprintf(
			'<iframe src="%s" width="%s" height="%s" frameborder="0"></iframe>',
			esc_url( $form_url ),
			esc_attr( $args['width'] ),
			esc_attr( $args['height'] )
		);

		return $embed_code;
	}

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error True if connected
	 */
	public function test_connection() {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_forms';

		// Check if table exists
		$exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );

		if ( ! $exists ) {
			return new WP_Error( 'table_missing', __( 'Forms table does not exist', 'nonprofitsuite' ) );
		}

		return true;
	}

	/**
	 * Get provider name
	 *
	 * @return string Provider name
	 */
	public function get_provider_name() {
		return __( 'Built-in Forms', 'nonprofitsuite' );
	}
}
