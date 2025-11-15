<?php
/**
 * Communications & Outreach Module (PRO)
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/modules
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class NonprofitSuite_Communications {

	private static function check_pro() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'Communications module requires Pro license.', 'nonprofitsuite' ) );
		}
		return true;
	}

	public static function create_campaign( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage communications' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_email_campaigns';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$wpdb->insert(
			$table,
			array(
				'campaign_name' => sanitize_text_field( $data['campaign_name'] ),
				'subject' => sanitize_text_field( $data['subject'] ),
				'message' => wp_kses_post( $data['message'] ),
				'recipient_list' => sanitize_text_field( $data['recipient_list'] ),
				'send_date' => isset( $data['send_date'] ) ? sanitize_text_field( $data['send_date'] ) : null,
				'status' => 'draft',
				'sent_count' => 0,
				'open_rate' => 0,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f' )
		);

		NonprofitSuite_Cache::invalidate_module( 'email_campaigns' );
		return $wpdb->insert_id;
	}

	public static function schedule_campaign( $campaign_id, $send_date ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_email_campaigns';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		return $wpdb->update(
			$table,
			array(
				'send_date' => sanitize_text_field( $send_date ),
				'status' => 'scheduled',
			),
			array( 'id' => $campaign_id ),
			array( '%s', '%s' ),
			array( '%d' )
		) !== false;
	}

	public static function send_campaign( $campaign_id ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage communications' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_email_campaigns';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$campaign = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, campaign_name, subject, message, recipient_list, send_date, status, sent_count, open_rate
			 FROM {$table}
			 WHERE id = %d",
			$campaign_id
		) );

		if ( ! $campaign ) {
			return new WP_Error( 'campaign_not_found', __( 'Campaign not found.', 'nonprofitsuite' ) );
		}

		// Get recipients based on list type
		$recipients = self::get_recipients( $campaign->recipient_list );

		if ( empty( $recipients ) ) {
			return new WP_Error( 'no_recipients', __( 'No recipients found for this campaign.', 'nonprofitsuite' ) );
		}

		$sent_count = 0;

		// Send emails
		foreach ( $recipients as $recipient ) {
			$sent = wp_mail(
				$recipient->email,
				$campaign->subject,
				$campaign->message,
				array( 'Content-Type: text/html; charset=UTF-8' )
			);

			if ( $sent ) {
				$sent_count++;
			}
		}

		// Update campaign status
		$wpdb->update(
			$table,
			array(
				'status' => 'sent',
				'sent_count' => $sent_count,
			),
			array( 'id' => $campaign_id ),
			array( '%s', '%d' ),
			array( '%d' )
		);

		return $sent_count;
	}

	private static function get_recipients( $recipient_list ) {
		global $wpdb;
		$people_table = $wpdb->prefix . 'ns_people';

		switch ( $recipient_list ) {
			case 'all':
				return $wpdb->get_results(
					"SELECT id, first_name, last_name, email
					FROM {$people_table}
					WHERE email IS NOT NULL AND email != ''"
				);

			case 'donors':
				$donors_table = $wpdb->prefix . 'ns_donors';
				return $wpdb->get_results(
					"SELECT p.id, p.first_name, p.last_name, p.email
					FROM {$people_table} p
					JOIN {$donors_table} d ON p.id = d.person_id
					WHERE p.email IS NOT NULL AND p.email != ''
					AND d.donor_status = 'active'"
				);

			case 'members':
				$members_table = $wpdb->prefix . 'ns_members';
				return $wpdb->get_results(
					"SELECT p.id, p.first_name, p.last_name, p.email
					FROM {$people_table} p
					JOIN {$members_table} m ON p.id = m.person_id
					WHERE p.email IS NOT NULL AND p.email != ''
					AND m.status = 'active'"
				);

			case 'volunteers':
				$volunteers_table = $wpdb->prefix . 'ns_volunteers';
				return $wpdb->get_results(
					"SELECT p.id, p.first_name, p.last_name, p.email
					FROM {$people_table} p
					JOIN {$volunteers_table} v ON p.id = v.person_id
					WHERE p.email IS NOT NULL AND p.email != ''
					AND v.status = 'active'"
				);

			default:
				return array();
		}
	}

	public static function get_campaigns( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_email_campaigns';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Parse pagination arguments
		$defaults = array( 'status' => null );
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = "WHERE 1=1";
		if ( $args['status'] ) {
			$where .= $wpdb->prepare( " AND status = %s", $args['status'] );
		}

		// Use caching for campaign lists
		$cache_key = NonprofitSuite_Cache::list_key( 'email_campaigns', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $where, $args ) {
			$sql = "SELECT id, campaign_name, subject, message, recipient_list, send_date,
			               status, sent_count, open_rate, created_at
			        FROM {$table} {$where}
			        ORDER BY created_at DESC
			        " . NonprofitSuite_Utilities::build_limit_clause( $args );

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	public static function get_campaign( $campaign_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_email_campaigns';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Use caching for individual campaigns
		$cache_key = NonprofitSuite_Cache::item_key( 'email_campaign', $campaign_id );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $campaign_id ) {
			return $wpdb->get_row( $wpdb->prepare(
				"SELECT id, campaign_name, subject, message, recipient_list, send_date,
				        status, sent_count, open_rate, created_at
				 FROM {$table}
				 WHERE id = %d",
				$campaign_id
			) );
		}, 300 );
	}

	public static function update_open_rate( $campaign_id, $open_rate ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage communications' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_email_campaigns';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		return $wpdb->update(
			$table,
			array( 'open_rate' => floatval( $open_rate ) ),
			array( 'id' => $campaign_id ),
			array( '%f' ),
			array( '%d' )
		) !== false;
	}

	public static function get_recipient_lists() {
		return array(
			'all' => __( 'All Contacts', 'nonprofitsuite' ),
			'donors' => __( 'Active Donors', 'nonprofitsuite' ),
			'members' => __( 'Active Members', 'nonprofitsuite' ),
			'volunteers' => __( 'Active Volunteers', 'nonprofitsuite' ),
		);
	}

	public static function get_campaign_statuses() {
		return array(
			'draft' => __( 'Draft', 'nonprofitsuite' ),
			'scheduled' => __( 'Scheduled', 'nonprofitsuite' ),
			'sending' => __( 'Sending', 'nonprofitsuite' ),
			'sent' => __( 'Sent', 'nonprofitsuite' ),
			'cancelled' => __( 'Cancelled', 'nonprofitsuite' ),
		);
	}
}
