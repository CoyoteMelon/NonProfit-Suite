<?php
/**
 * WordPress Email Adapter
 *
 * Adapter that uses WordPress wp_mail() function for sending emails.
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
 * NonprofitSuite_Email_WordPress_Adapter Class
 *
 * Implements email integration using WordPress wp_mail().
 */
class NonprofitSuite_Email_WordPress_Adapter implements NonprofitSuite_Email_Adapter_Interface {

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
			'from'        => get_option( 'admin_email' ),
			'from_name'   => get_option( 'blogname' ),
			'reply_to'    => '',
			'cc'          => '',
			'bcc'         => '',
			'attachments' => array(),
			'html'        => true,
		) );

		// Validate required fields
		if ( empty( $args['to'] ) || empty( $args['subject'] ) || empty( $args['message'] ) ) {
			return new WP_Error( 'missing_required', __( 'Missing required email fields', 'nonprofitsuite' ) );
		}

		// Build headers
		$headers = array();

		if ( $args['html'] ) {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
		}

		if ( $args['from'] && $args['from_name'] ) {
			$headers[] = sprintf( 'From: %s <%s>', $args['from_name'], $args['from'] );
		}

		if ( $args['reply_to'] ) {
			$headers[] = sprintf( 'Reply-To: %s', $args['reply_to'] );
		}

		if ( $args['cc'] ) {
			$cc = is_array( $args['cc'] ) ? implode( ',', $args['cc'] ) : $args['cc'];
			$headers[] = sprintf( 'Cc: %s', $cc );
		}

		if ( $args['bcc'] ) {
			$bcc = is_array( $args['bcc'] ) ? implode( ',', $args['bcc'] ) : $args['bcc'];
			$headers[] = sprintf( 'Bcc: %s', $bcc );
		}

		// Send email
		$result = wp_mail(
			$args['to'],
			$args['subject'],
			$args['message'],
			$headers,
			$args['attachments']
		);

		if ( ! $result ) {
			do_action( 'ns_email_failed', $args['to'], $args['subject'], new WP_Error( 'send_failed', 'Email send failed' ), 'wordpress' );

			return new WP_Error( 'send_failed', __( 'Failed to send email', 'nonprofitsuite' ) );
		}

		// Generate message ID
		$message_id = md5( $args['to'] . $args['subject'] . microtime() );

		do_action( 'ns_email_sent', $args['to'], $args['subject'], $args['message'], 'wordpress' );

		return array(
			'message_id' => $message_id,
			'status'     => 'sent',
		);
	}

	/**
	 * Send bulk emails
	 *
	 * @param array $emails Array of email argument arrays
	 * @return array|WP_Error Result
	 */
	public function send_bulk( $emails ) {
		$sent_count = 0;
		$failed_count = 0;
		$errors = array();

		foreach ( $emails as $email ) {
			$result = $this->send( $email );

			if ( is_wp_error( $result ) ) {
				$failed_count++;
				$errors[] = $result->get_error_message();
			} else {
				$sent_count++;
			}
		}

		return array(
			'sent_count'   => $sent_count,
			'failed_count' => $failed_count,
			'errors'       => $errors,
		);
	}

	/**
	 * Get email delivery status (not supported by wp_mail)
	 *
	 * @param string $message_id Message identifier
	 * @return array|WP_Error Status array
	 */
	public function get_status( $message_id ) {
		// wp_mail doesn't provide delivery tracking
		return new WP_Error( 'not_supported', __( 'Delivery tracking not supported by WordPress mail', 'nonprofitsuite' ) );
	}

	/**
	 * Create email template (stored in database)
	 *
	 * @param array $template_data Template data
	 * @return array|WP_Error Template data
	 */
	public function create_template( $template_data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_email_templates';

		$template = array(
			'name'    => $template_data['name'],
			'subject' => isset( $template_data['subject'] ) ? $template_data['subject'] : '',
			'html'    => isset( $template_data['html'] ) ? $template_data['html'] : '',
			'text'    => isset( $template_data['text'] ) ? $template_data['text'] : '',
			'created_at' => current_time( 'mysql' ),
		);

		$wpdb->insert( $table, $template );

		if ( $wpdb->last_error ) {
			return new WP_Error( 'db_error', $wpdb->last_error );
		}

		return array(
			'template_id' => $wpdb->insert_id,
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
		global $wpdb;

		$table = $wpdb->prefix . 'ns_email_templates';

		$update_data = array();
		$allowed_fields = array( 'name', 'subject', 'html', 'text' );

		foreach ( $allowed_fields as $field ) {
			if ( isset( $template_data[ $field ] ) ) {
				$update_data[ $field ] = $template_data[ $field ];
			}
		}

		if ( empty( $update_data ) ) {
			return true;
		}

		$update_data['updated_at'] = current_time( 'mysql' );

		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $template_id ),
			null,
			array( '%d' )
		);

		return false !== $result ? true : new WP_Error( 'update_failed', __( 'Failed to update template', 'nonprofitsuite' ) );
	}

	/**
	 * Delete email template
	 *
	 * @param string $template_id Template identifier
	 * @return bool|WP_Error True on success
	 */
	public function delete_template( $template_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_email_templates';

		$result = $wpdb->delete(
			$table,
			array( 'id' => $template_id ),
			array( '%d' )
		);

		return false !== $result ? true : new WP_Error( 'delete_failed', __( 'Failed to delete template', 'nonprofitsuite' ) );
	}

	/**
	 * List email templates
	 *
	 * @param array $args List arguments
	 * @return array|WP_Error Array of templates
	 */
	public function list_templates( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args( $args, array(
			'limit'  => 100,
			'offset' => 0,
		) );

		$table = $wpdb->prefix . 'ns_email_templates';
		$limit = (int) $args['limit'];
		$offset = (int) $args['offset'];

		$templates = $wpdb->get_results(
			"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT {$offset}, {$limit}",
			ARRAY_A
		);

		return $templates ? $templates : array();
	}

	/**
	 * Get email statistics (not supported by wp_mail)
	 *
	 * @param array $args Statistics arguments
	 * @return array|WP_Error Statistics array
	 */
	public function get_statistics( $args = array() ) {
		// wp_mail doesn't provide statistics
		return new WP_Error( 'not_supported', __( 'Email statistics not supported by WordPress mail', 'nonprofitsuite' ) );
	}

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error True if connected
	 */
	public function test_connection() {
		// Test if wp_mail function exists
		if ( ! function_exists( 'wp_mail' ) ) {
			return new WP_Error( 'function_missing', __( 'wp_mail function not available', 'nonprofitsuite' ) );
		}

		return true;
	}

	/**
	 * Get provider name
	 *
	 * @return string Provider name
	 */
	public function get_provider_name() {
		return __( 'WordPress Mail', 'nonprofitsuite' );
	}
}
