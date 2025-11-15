<?php
/**
 * Email System Setup Helper
 *
 * Provides easy setup for standard organizational email addresses.
 *
 * @package    NonprofitSuite
 * @subpackage Helpers
 * @since      1.6.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonprofitSuite_Email_Setup Class
 *
 * Helper for setting up organizational email addresses.
 */
class NonprofitSuite_Email_Setup {

	/**
	 * Initialize standard organizational email addresses.
	 *
	 * @param string $domain Organization domain (e.g., 'mynonprofit.org').
	 * @param array  $options Optional configuration.
	 * @return array Results of email address creation.
	 */
	public static function initialize_standard_addresses( $domain, $options = array() ) {
		$results = array();

		// Standard public-facing emails
		$public_emails = array(
			array(
				'email_address'        => "info@{$domain}",
				'address_type'         => 'functional',
				'functional_category'  => 'general',
				'archive_category'     => 'general_inquiries',
				'forward_to_users'     => isset( $options['info_forwards_to'] ) ? $options['info_forwards_to'] : array(),
				'trigger_automations'  => false,
			),
			array(
				'email_address'        => "contact@{$domain}",
				'address_type'         => 'functional',
				'functional_category'  => 'general',
				'archive_category'     => 'general_inquiries',
				'forward_to_users'     => isset( $options['contact_forwards_to'] ) ? $options['contact_forwards_to'] : array(),
				'trigger_automations'  => false,
			),
			array(
				'email_address'        => "support@{$domain}",
				'address_type'         => 'functional',
				'functional_category'  => 'support',
				'archive_category'     => 'member_support',
				'forward_to_users'     => isset( $options['support_forwards_to'] ) ? $options['support_forwards_to'] : array(),
				'trigger_automations'  => false,
			),
		);

		foreach ( $public_emails as $email_config ) {
			$result = NonprofitSuite_Email_Routing::register_email_address( $email_config );
			$results[] = array(
				'email'   => $email_config['email_address'],
				'success' => ! is_wp_error( $result ),
				'message' => is_wp_error( $result ) ? $result->get_error_message() : 'Created successfully',
			);
		}

		// Role-based board emails
		$board_roles = array(
			'president' => 'President',
			'treasurer' => 'Treasurer',
			'secretary' => 'Secretary',
		);

		foreach ( $board_roles as $role_slug => $role_name ) {
			$role_id = self::get_role_id_by_slug( $role_slug );

			$result = NonprofitSuite_Email_Routing::register_email_address( array(
				'email_address'        => "{$role_slug}@{$domain}",
				'address_type'         => 'role',
				'role_id'              => $role_id,
				'archive_category'     => "board_{$role_slug}",
				'trigger_automations'  => false,
			) );

			$results[] = array(
				'email'   => "{$role_slug}@{$domain}",
				'success' => ! is_wp_error( $result ),
				'message' => is_wp_error( $result ) ? $result->get_error_message() : 'Created successfully',
			);
		}

		// Group emails
		$result = NonprofitSuite_Email_Routing::register_email_address( array(
			'email_address'        => "board@{$domain}",
			'address_type'         => 'group',
			'group_type'           => 'board',
			'archive_category'     => 'board_communications',
			'trigger_automations'  => false,
		) );

		$results[] = array(
			'email'   => "board@{$domain}",
			'success' => ! is_wp_error( $result ),
			'message' => is_wp_error( $result ) ? $result->get_error_message() : 'Created successfully',
		);

		// Functional filing emails
		$functional_emails = array(
			array(
				'email_address'        => "irs@{$domain}",
				'address_type'         => 'functional',
				'functional_category'  => 'federal_filing',
				'archive_category'     => 'irs_correspondence',
				'forward_to_users'     => isset( $options['treasurer_user_id'] ) ? array( $options['treasurer_user_id'] ) : array(),
				'trigger_automations'  => true,
				'automation_rules'     => array(
					array(
						'conditions' => array(),
						'actions'    => array(
							array( 'type' => 'file_document', 'category' => 'federal_filing' ),
							array( 'type' => 'notify_user', 'user_role' => 'treasurer' ),
						),
					),
				),
			),
			array(
				'email_address'        => "casos@{$domain}",
				'address_type'         => 'functional',
				'functional_category'  => 'state_filing',
				'archive_category'     => 'state_correspondence',
				'forward_to_users'     => isset( $options['secretary_user_id'] ) ? array( $options['secretary_user_id'] ) : array(),
				'trigger_automations'  => true,
				'automation_rules'     => array(
					array(
						'conditions' => array(),
						'actions'    => array(
							array( 'type' => 'file_document', 'category' => 'state_filing' ),
							array( 'type' => 'notify_user', 'user_role' => 'secretary' ),
						),
					),
				),
			),
			array(
				'email_address'        => "legal@{$domain}",
				'address_type'         => 'functional',
				'functional_category'  => 'legal',
				'archive_category'     => 'legal_correspondence',
				'forward_to_users'     => isset( $options['legal_forwards_to'] ) ? $options['legal_forwards_to'] : array(),
				'trigger_automations'  => true,
				'automation_rules'     => array(
					array(
						'conditions' => array(),
						'actions'    => array(
							array( 'type' => 'file_document', 'category' => 'legal' ),
						),
					),
				),
			),
		);

		foreach ( $functional_emails as $email_config ) {
			$result = NonprofitSuite_Email_Routing::register_email_address( $email_config );
			$results[] = array(
				'email'   => $email_config['email_address'],
				'success' => ! is_wp_error( $result ),
				'message' => is_wp_error( $result ) ? $result->get_error_message() : 'Created successfully',
			);
		}

		// AI Assistant email
		$result = NonprofitSuite_Email_Routing::register_email_address( array(
			'email_address'        => "ai@{$domain}",
			'address_type'         => 'ai',
			'functional_category'  => 'ai_assistant',
			'archive_category'     => 'ai_requests',
			'trigger_automations'  => false,
		) );

		$results[] = array(
			'email'   => "ai@{$domain}",
			'success' => ! is_wp_error( $result ),
			'message' => is_wp_error( $result ) ? $result->get_error_message() : 'Created successfully',
		);

		// Payment processor emails
		$processor_emails = array(
			array(
				'email_address'        => "paypal@{$domain}",
				'address_type'         => 'functional',
				'functional_category'  => 'payment_processor',
				'archive_category'     => 'paypal_correspondence',
				'forward_to_users'     => isset( $options['treasurer_user_id'] ) ? array( $options['treasurer_user_id'] ) : array(),
				'trigger_automations'  => true,
				'automation_rules'     => array(
					array(
						'conditions' => array(
							array( 'type' => 'subject_contains', 'value' => 'dispute' ),
						),
						'actions'    => array(
							array( 'type' => 'notify_user', 'user_role' => 'treasurer' ),
							array( 'type' => 'create_task', 'title' => 'PayPal Dispute Received', 'priority' => 'high' ),
							array( 'type' => 'file_document', 'category' => 'payment_disputes' ),
						),
					),
					array(
						'conditions' => array(
							array( 'type' => 'subject_contains', 'value' => 'chargeback' ),
						),
						'actions'    => array(
							array( 'type' => 'notify_user', 'user_role' => 'treasurer' ),
							array( 'type' => 'create_task', 'title' => 'PayPal Chargeback Received', 'priority' => 'urgent' ),
							array( 'type' => 'file_document', 'category' => 'payment_disputes' ),
						),
					),
				),
			),
			array(
				'email_address'        => "stripe@{$domain}",
				'address_type'         => 'functional',
				'functional_category'  => 'payment_processor',
				'archive_category'     => 'stripe_correspondence',
				'forward_to_users'     => isset( $options['treasurer_user_id'] ) ? array( $options['treasurer_user_id'] ) : array(),
				'trigger_automations'  => true,
				'automation_rules'     => array(
					array(
						'conditions' => array(
							array( 'type' => 'subject_contains', 'value' => 'dispute' ),
						),
						'actions'    => array(
							array( 'type' => 'notify_user', 'user_role' => 'treasurer' ),
							array( 'type' => 'create_task', 'title' => 'Stripe Dispute Received', 'priority' => 'high' ),
							array( 'type' => 'file_document', 'category' => 'payment_disputes' ),
						),
					),
					array(
						'conditions' => array(
							array( 'type' => 'subject_contains', 'value' => 'chargeback' ),
						),
						'actions'    => array(
							array( 'type' => 'notify_user', 'user_role' => 'treasurer' ),
							array( 'type' => 'create_task', 'title' => 'Stripe Chargeback Received', 'priority' => 'urgent' ),
							array( 'type' => 'file_document', 'category' => 'payment_disputes' ),
						),
					),
				),
			),
			array(
				'email_address'        => "venmo@{$domain}",
				'address_type'         => 'functional',
				'functional_category'  => 'payment_processor',
				'archive_category'     => 'venmo_correspondence',
				'forward_to_users'     => isset( $options['treasurer_user_id'] ) ? array( $options['treasurer_user_id'] ) : array(),
				'trigger_automations'  => true,
				'automation_rules'     => array(
					array(
						'conditions' => array(
							array( 'type' => 'subject_contains', 'value' => 'dispute' ),
						),
						'actions'    => array(
							array( 'type' => 'notify_user', 'user_role' => 'treasurer' ),
							array( 'type' => 'create_task', 'title' => 'Venmo Dispute Received', 'priority' => 'high' ),
							array( 'type' => 'file_document', 'category' => 'payment_disputes' ),
						),
					),
				),
			),
			array(
				'email_address'        => "square@{$domain}",
				'address_type'         => 'functional',
				'functional_category'  => 'payment_processor',
				'archive_category'     => 'square_correspondence',
				'forward_to_users'     => isset( $options['treasurer_user_id'] ) ? array( $options['treasurer_user_id'] ) : array(),
				'trigger_automations'  => true,
				'automation_rules'     => array(
					array(
						'conditions' => array(
							array( 'type' => 'subject_contains', 'value' => 'dispute' ),
						),
						'actions'    => array(
							array( 'type' => 'notify_user', 'user_role' => 'treasurer' ),
							array( 'type' => 'create_task', 'title' => 'Square Dispute Received', 'priority' => 'high' ),
							array( 'type' => 'file_document', 'category' => 'payment_disputes' ),
						),
					),
				),
			),
			array(
				'email_address'        => "zelle@{$domain}",
				'address_type'         => 'functional',
				'functional_category'  => 'payment_processor',
				'archive_category'     => 'zelle_correspondence',
				'forward_to_users'     => isset( $options['treasurer_user_id'] ) ? array( $options['treasurer_user_id'] ) : array(),
				'trigger_automations'  => true,
				'automation_rules'     => array(
					array(
						'conditions' => array(
							array( 'type' => 'subject_contains', 'value' => 'dispute' ),
						),
						'actions'    => array(
							array( 'type' => 'notify_user', 'user_role' => 'treasurer' ),
							array( 'type' => 'create_task', 'title' => 'Zelle Dispute Received', 'priority' => 'high' ),
							array( 'type' => 'file_document', 'category' => 'payment_disputes' ),
						),
					),
				),
			),
		);

		foreach ( $processor_emails as $email_config ) {
			$result = NonprofitSuite_Email_Routing::register_email_address( $email_config );
			$results[] = array(
				'email'   => $email_config['email_address'],
				'success' => ! is_wp_error( $result ),
				'message' => is_wp_error( $result ) ? $result->get_error_message() : 'Created successfully',
			);
		}

		return $results;
	}

	/**
	 * Get role ID by slug.
	 *
	 * @param string $role_slug Role slug (e.g., 'president').
	 * @return int|null Role ID or null if not found.
	 */
	private static function get_role_id_by_slug( $role_slug ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_board_positions';

		// Try to find the position in board positions table
		$role_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE position_slug = %s LIMIT 1",
				$role_slug
			)
		);

		return $role_id ? (int) $role_id : null;
	}

	/**
	 * Create a custom email address.
	 *
	 * @param string $local_part Local part of email (before @).
	 * @param string $domain     Domain.
	 * @param array  $config     Email configuration.
	 * @return int|WP_Error Email address ID or error.
	 */
	public static function create_custom_address( $local_part, $domain, $config ) {
		$email_address = "{$local_part}@{$domain}";

		$address_data = array_merge( $config, array(
			'email_address' => $email_address,
		) );

		return NonprofitSuite_Email_Routing::register_email_address( $address_data );
	}

	/**
	 * Get setup status.
	 *
	 * @param string $domain Organization domain.
	 * @return array Status of standard email addresses.
	 */
	public static function get_setup_status( $domain ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_email_addresses';

		$standard_addresses = array(
			'info', 'contact', 'support',
			'president', 'treasurer', 'secretary',
			'board',
			'irs', 'casos', 'legal',
			'ai',
			'paypal', 'stripe', 'venmo', 'square', 'zelle',
		);

		$status = array();

		foreach ( $standard_addresses as $local_part ) {
			$email = "{$local_part}@{$domain}";

			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE email_address = %s",
					$email
				)
			);

			$status[ $local_part ] = array(
				'email'  => $email,
				'exists' => (bool) $exists,
			);
		}

		return $status;
	}
}
