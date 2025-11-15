<?php
/**
 * Email Campaign Builder Module
 *
 * @package NonprofitSuite
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NonprofitSuite_Email_Campaigns {

	/**
	 * Create campaign
	 *
	 * @param array $data Campaign data
	 * @return int|WP_Error Campaign ID or error
	 */
	public static function create_campaign( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage email campaigns' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'ns_email_campaigns',
			array(
				'campaign_name' => sanitize_text_field( $data['campaign_name'] ),
				'subject_line' => sanitize_text_field( $data['subject_line'] ),
				'from_name' => isset( $data['from_name'] ) ? sanitize_text_field( $data['from_name'] ) : get_bloginfo( 'name' ),
				'from_email' => isset( $data['from_email'] ) ? sanitize_email( $data['from_email'] ) : get_option( 'admin_email' ),
				'reply_to' => isset( $data['reply_to'] ) ? sanitize_email( $data['reply_to'] ) : null,
				'email_content' => wp_kses_post( $data['email_content'] ),
				'template_id' => isset( $data['template_id'] ) ? absint( $data['template_id'] ) : null,
				'status' => 'draft',
				'segment' => isset( $data['segment'] ) ? sanitize_text_field( $data['segment'] ) : null,
				'created_by' => get_current_user_id(),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d' )
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Failed to create campaign', 'nonprofitsuite' ) );
		}

		NonprofitSuite_Cache::invalidate_module( 'email_campaigns' );
		return $wpdb->insert_id;
	}

	/**
	 * Get campaigns
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error Array of campaigns or error
	 */
	public static function get_campaigns( $args = array() ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$defaults = array(
			'status' => null,
		);

		// Parse pagination arguments
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = array( '1=1' );
		$values = array();

		if ( $args['status'] ) {
			$where[] = 'status = %s';
			$values[] = sanitize_text_field( $args['status'] );
		}

		$where_clause = implode( ' AND ', $where );

		// Use caching for campaign lists
		$cache_key = NonprofitSuite_Cache::list_key( 'email_campaigns', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $where_clause, $values, $args ) {
			$sql = "SELECT id, campaign_name, subject_line, from_name, from_email, reply_to,
			               email_content, template_id, status, segment, scheduled_date, sent_date,
			               total_recipients, opened_count, clicked_count, unsubscribed_count,
			               bounced_count, created_by, created_at
			        FROM {$wpdb->prefix}ns_email_campaigns
			        WHERE $where_clause
			        ORDER BY created_at DESC
			        " . NonprofitSuite_Utilities::build_limit_clause( $args );

			if ( ! empty( $values ) ) {
				$sql = $wpdb->prepare( $sql, $values );
			}

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	/**
	 * Schedule campaign
	 *
	 * @param int    $campaign_id Campaign ID
	 * @param string $scheduled_date Schedule date (Y-m-d H:i:s)
	 * @return bool|WP_Error True on success or error
	 */
	public static function schedule_campaign( $campaign_id, $scheduled_date ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		$result = $wpdb->update(
			$wpdb->prefix . 'ns_email_campaigns',
			array(
				'status' => 'scheduled',
				'scheduled_date' => sanitize_text_field( $scheduled_date ),
			),
			array( 'id' => absint( $campaign_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			NonprofitSuite_Cache::invalidate_module( 'email_campaigns' );
			NonprofitSuite_Cache::delete( NonprofitSuite_Cache::item_key( 'email_campaign', $campaign_id ) );
		}

		return $result !== false;
	}

	/**
	 * Send campaign
	 *
	 * @param int   $campaign_id Campaign ID
	 * @param array $recipients Array of email addresses
	 * @return int|WP_Error Number of emails sent or error
	 */
	public static function send_campaign( $campaign_id, $recipients ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage email campaigns' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'PRO license required', 'nonprofitsuite' ) );
		}

		global $wpdb;

		// Get campaign
		$campaign = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, subject_line, email_content, from_name, from_email, reply_to
			 FROM {$wpdb->prefix}ns_email_campaigns
			 WHERE id = %d",
			absint( $campaign_id )
		) );

		if ( ! $campaign ) {
			return new WP_Error( 'not_found', __( 'Campaign not found', 'nonprofitsuite' ) );
		}

		$sent_count = 0;

		foreach ( $recipients as $recipient ) {
			// Send email via wp_mail
			$sent = wp_mail(
				$recipient['email'],
				$campaign->subject_line,
				$campaign->email_content,
				array(
					'Content-Type: text/html; charset=UTF-8',
					'From: ' . $campaign->from_name . ' <' . $campaign->from_email . '>',
					'Reply-To: ' . ( $campaign->reply_to ?? $campaign->from_email ),
				)
			);

			// Record send
			if ( $sent ) {
				$wpdb->insert(
					$wpdb->prefix . 'ns_campaign_sends',
					array(
						'campaign_id' => $campaign_id,
						'recipient_email' => sanitize_email( $recipient['email'] ),
						'recipient_name' => isset( $recipient['name'] ) ? sanitize_text_field( $recipient['name'] ) : null,
						'sent_date' => current_time( 'mysql' ),
					),
					array( '%d', '%s', '%s', '%s' )
				);
				$sent_count++;
			}
		}

		// Update campaign
		$wpdb->update(
			$wpdb->prefix . 'ns_email_campaigns',
			array(
				'status' => 'sent',
				'sent_date' => current_time( 'mysql' ),
				'total_recipients' => count( $recipients ),
			),
			array( 'id' => $campaign_id ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);

		NonprofitSuite_Cache::invalidate_module( 'email_campaigns' );
		NonprofitSuite_Cache::delete( NonprofitSuite_Cache::item_key( 'email_campaign', $campaign_id ) );
		return $sent_count;
	}

	/**
	 * Track email open
	 *
	 * @param int    $campaign_id Campaign ID
	 * @param string $recipient_email Recipient email
	 * @return bool True on success
	 */
	public static function track_open( $campaign_id, $recipient_email ) {
		global $wpdb;

		// Update send record
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}ns_campaign_sends
			SET opened_date = %s
			WHERE campaign_id = %d AND recipient_email = %s AND opened_date IS NULL",
			current_time( 'mysql' ),
			absint( $campaign_id ),
			sanitize_email( $recipient_email )
		) );

		// Update campaign stats
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}ns_email_campaigns
			SET opened_count = (SELECT COUNT(*) FROM {$wpdb->prefix}ns_campaign_sends WHERE campaign_id = %d AND opened_date IS NOT NULL)
			WHERE id = %d",
			absint( $campaign_id ),
			absint( $campaign_id )
		) );

		return true;
	}

	/**
	 * Get campaign stats
	 *
	 * @param int $campaign_id Campaign ID
	 * @return array Campaign statistics
	 */
	public static function get_stats( $campaign_id ) {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return array();
		}

		global $wpdb;

		// Use caching for campaign stats
		$cache_key = NonprofitSuite_Cache::item_key( 'email_campaign_stats', $campaign_id );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $campaign_id ) {
			$campaign = $wpdb->get_row( $wpdb->prepare(
				"SELECT id, total_recipients, opened_count, clicked_count, unsubscribed_count, bounced_count
				 FROM {$wpdb->prefix}ns_email_campaigns
				 WHERE id = %d",
				absint( $campaign_id )
			) );

			if ( ! $campaign ) {
				return array();
			}

			$open_rate = $campaign->total_recipients > 0 ? ( $campaign->opened_count / $campaign->total_recipients ) * 100 : 0;
			$click_rate = $campaign->total_recipients > 0 ? ( $campaign->clicked_count / $campaign->total_recipients ) * 100 : 0;

			return array(
				'total_sent' => $campaign->total_recipients,
				'opened' => $campaign->opened_count,
				'clicked' => $campaign->clicked_count,
				'unsubscribed' => $campaign->unsubscribed_count,
				'bounced' => $campaign->bounced_count,
				'open_rate' => round( $open_rate, 2 ),
				'click_rate' => round( $click_rate, 2 ),
			);
		}, 300 );
	}
}
