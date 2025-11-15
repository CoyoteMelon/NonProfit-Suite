<?php
/**
 * Marketing Manager
 *
 * Central coordinator for all marketing operations.
 * Manages campaigns, segments, and platform integrations.
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonprofitSuite_Marketing_Manager {

	private static $adapters = array();

	public static function init() {
		// Register adapters
		self::register_adapter( 'mailchimp', 'NonprofitSuite_Mailchimp_Adapter' );

		// Schedule campaign sends
		add_action( 'nonprofitsuite_send_scheduled_campaigns', array( __CLASS__, 'send_scheduled_campaigns' ) );

		if ( ! wp_next_scheduled( 'nonprofitsuite_send_scheduled_campaigns' ) ) {
			wp_schedule_event( time(), 'hourly', 'nonprofitsuite_send_scheduled_campaigns' );
		}
	}

	public static function register_adapter( $key, $class_name ) {
		self::$adapters[ $key ] = $class_name;
	}

	public static function get_adapter( $platform, $org_id ) {
		if ( ! isset( self::$adapters[ $platform ] ) ) {
			return null;
		}

		$credentials = self::get_platform_credentials( $platform, $org_id );
		if ( ! $credentials ) {
			return null;
		}

		$class_name = self::$adapters[ $platform ];
		return new $class_name( $credentials );
	}

	public static function get_platform_credentials( $platform, $org_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_marketing_settings';

		$setting = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE organization_id = %d AND platform = %s AND is_active = 1",
				$org_id,
				$platform
			),
			ARRAY_A
		);

		if ( ! $setting ) {
			return null;
		}

		return array(
			'api_key'        => $setting['api_key'],
			'api_secret'     => $setting['api_secret'],
			'server_prefix'  => $setting['server_prefix'],
			'from_email'     => $setting['from_email'],
			'from_name'      => $setting['from_name'],
			'reply_to_email' => $setting['reply_to_email'],
		);
	}

	public static function create_segment( $org_id, $segment_data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_marketing_segments';

		$wpdb->insert(
			$table,
			array(
				'organization_id' => $org_id,
				'segment_name'    => $segment_data['segment_name'],
				'segment_type'    => $segment_data['segment_type'],
				'description'     => isset( $segment_data['description'] ) ? $segment_data['description'] : '',
				'criteria'        => isset( $segment_data['criteria'] ) ? wp_json_encode( $segment_data['criteria'] ) : null,
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		$segment_id = $wpdb->insert_id;

		// Sync to external platform if configured
		if ( isset( $segment_data['platform'] ) && ! empty( $segment_data['platform'] ) ) {
			self::sync_segment_to_platform( $segment_id, $segment_data['platform'], $org_id );
		}

		return $segment_id;
	}

	public static function sync_segment_to_platform( $segment_id, $platform, $org_id ) {
		global $wpdb;

		$adapter = self::get_adapter( $platform, $org_id );
		if ( ! $adapter ) {
			return new WP_Error( 'adapter_not_found', 'Platform adapter not found' );
		}

		$segment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ns_marketing_segments WHERE id = %d",
				$segment_id
			),
			ARRAY_A
		);

		$result = $adapter->create_audience( $segment );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Update segment with platform list ID
		$wpdb->update(
			$wpdb->prefix . 'ns_marketing_segments',
			array(
				'platform'         => $platform,
				'platform_list_id' => $result['platform_list_id'],
				'last_sync_at'     => current_time( 'mysql', 1 ),
			),
			array( 'id' => $segment_id )
		);

		return true;
	}

	public static function add_contacts_to_segment( $segment_id, $contact_ids ) {
		global $wpdb;

		$segment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ns_marketing_segments WHERE id = %d",
				$segment_id
			),
			ARRAY_A
		);

		foreach ( $contact_ids as $contact_id ) {
			$contact = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}ns_people WHERE id = %d",
					$contact_id
				),
				ARRAY_A
			);

			if ( $contact ) {
				$wpdb->insert(
					$wpdb->prefix . 'ns_marketing_segment_members',
					array(
						'segment_id' => $segment_id,
						'contact_id' => $contact_id,
						'email'      => $contact['email'],
						'status'     => 'subscribed',
					),
					array( '%d', '%d', '%s', '%s' )
				);
			}
		}

		// Sync to platform if configured
		if ( ! empty( $segment['platform'] ) && ! empty( $segment['platform_list_id'] ) ) {
			$adapter = self::get_adapter( $segment['platform'], $segment['organization_id'] );
			if ( $adapter ) {
				$contacts = array();
				foreach ( $contact_ids as $contact_id ) {
					$contact = $wpdb->get_row(
						$wpdb->prepare(
							"SELECT * FROM {$wpdb->prefix}ns_people WHERE id = %d",
							$contact_id
						),
						ARRAY_A
					);
					if ( $contact ) {
						$contacts[] = $contact;
					}
				}

				$adapter->add_contacts_to_audience( $segment['platform_list_id'], $contacts );
			}
		}

		// Update contact count
		self::update_segment_count( $segment_id );

		return true;
	}

	public static function create_campaign( $org_id, $campaign_data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_marketing_campaigns';

		$wpdb->insert(
			$table,
			array(
				'organization_id' => $org_id,
				'campaign_name'   => $campaign_data['campaign_name'],
				'campaign_type'   => $campaign_data['campaign_type'],
				'platform'        => isset( $campaign_data['platform'] ) ? $campaign_data['platform'] : 'builtin',
				'subject'         => isset( $campaign_data['subject'] ) ? $campaign_data['subject'] : '',
				'preview_text'    => isset( $campaign_data['preview_text'] ) ? $campaign_data['preview_text'] : '',
				'content'         => isset( $campaign_data['content'] ) ? $campaign_data['content'] : '',
				'segment_id'      => isset( $campaign_data['segment_id'] ) ? $campaign_data['segment_id'] : null,
				'status'          => 'draft',
				'created_by'      => get_current_user_id(),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d' )
		);

		return $wpdb->insert_id;
	}

	public static function send_campaign( $campaign_id ) {
		global $wpdb;

		$campaign = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ns_marketing_campaigns WHERE id = %d",
				$campaign_id
			),
			ARRAY_A
		);

		if ( ! $campaign ) {
			return new WP_Error( 'campaign_not_found', 'Campaign not found' );
		}

		// Get segment members
		$members = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ns_marketing_segment_members WHERE segment_id = %d AND status = 'subscribed'",
				$campaign['segment_id']
			),
			ARRAY_A
		);

		$wpdb->update(
			$wpdb->prefix . 'ns_marketing_campaigns',
			array(
				'status'           => 'sending',
				'total_recipients' => count( $members ),
			),
			array( 'id' => $campaign_id )
		);

		if ( $campaign['platform'] === 'builtin' ) {
			// Send via WordPress email
			foreach ( $members as $member ) {
				$sent = wp_mail(
					$member['email'],
					$campaign['subject'],
					$campaign['content'],
					array( 'Content-Type: text/html; charset=UTF-8' )
				);

				if ( $sent ) {
					self::log_campaign_event( $campaign_id, $member['contact_id'], $member['email'], 'sent' );
					$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}ns_marketing_campaigns SET total_sent = total_sent + 1 WHERE id = %d", $campaign_id ) );
				}
			}
		} else {
			// Send via platform adapter
			$adapter = self::get_adapter( $campaign['platform'], $campaign['organization_id'] );
			if ( ! $adapter ) {
				return new WP_Error( 'adapter_not_found', 'Platform adapter not found' );
			}

			// Create campaign on platform if not already created
			if ( empty( $campaign['platform_campaign_id'] ) ) {
				$segment = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}ns_marketing_segments WHERE id = %d",
						$campaign['segment_id']
					),
					ARRAY_A
				);

				$result = $adapter->create_campaign(
					array(
						'campaign_name'     => $campaign['campaign_name'],
						'subject'           => $campaign['subject'],
						'preview_text'      => $campaign['preview_text'],
						'content'           => $campaign['content'],
						'platform_list_id'  => $segment['platform_list_id'],
					)
				);

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				$wpdb->update(
					$wpdb->prefix . 'ns_marketing_campaigns',
					array( 'platform_campaign_id' => $result['platform_campaign_id'] ),
					array( 'id' => $campaign_id )
				);

				$campaign['platform_campaign_id'] = $result['platform_campaign_id'];
			}

			// Send the campaign
			$send_result = $adapter->send_campaign( $campaign['platform_campaign_id'] );

			if ( is_wp_error( $send_result ) ) {
				$wpdb->update(
					$wpdb->prefix . 'ns_marketing_campaigns',
					array( 'status' => 'draft' ),
					array( 'id' => $campaign_id )
				);
				return $send_result;
			}
		}

		$wpdb->update(
			$wpdb->prefix . 'ns_marketing_campaigns',
			array(
				'status'  => 'sent',
				'sent_at' => current_time( 'mysql', 1 ),
			),
			array( 'id' => $campaign_id )
		);

		return true;
	}

	public static function send_scheduled_campaigns() {
		global $wpdb;

		$campaigns = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ns_marketing_campaigns WHERE status = %s AND scheduled_at <= %s",
				'scheduled',
				current_time( 'mysql', 1 )
			),
			ARRAY_A
		);

		foreach ( $campaigns as $campaign ) {
			self::send_campaign( $campaign['id'] );
		}
	}

	public static function log_campaign_event( $campaign_id, $contact_id, $email, $event_type, $event_data = array() ) {
		global $wpdb;

		$campaign = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT organization_id FROM {$wpdb->prefix}ns_marketing_campaigns WHERE id = %d",
				$campaign_id
			),
			ARRAY_A
		);

		$wpdb->insert(
			$wpdb->prefix . 'ns_marketing_analytics',
			array(
				'organization_id' => $campaign['organization_id'],
				'campaign_id'     => $campaign_id,
				'contact_id'      => $contact_id,
				'email'           => $email,
				'event_type'      => $event_type,
				'event_data'      => ! empty( $event_data ) ? wp_json_encode( $event_data ) : null,
				'user_agent'      => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : null,
				'ip_address'      => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : null,
			)
		);

		// Update campaign stats
		$column_map = array(
			'sent'         => 'total_sent',
			'delivered'    => 'total_delivered',
			'opened'       => 'total_opened',
			'clicked'      => 'total_clicked',
			'bounced'      => 'total_bounced',
			'unsubscribed' => 'total_unsubscribed',
		);

		if ( isset( $column_map[ $event_type ] ) ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}ns_marketing_campaigns SET {$column_map[$event_type]} = {$column_map[$event_type]} + 1 WHERE id = %d",
					$campaign_id
				)
			);
		}
	}

	private static function update_segment_count( $segment_id ) {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ns_marketing_segment_members WHERE segment_id = %d AND status = 'subscribed'",
				$segment_id
			)
		);

		$wpdb->update(
			$wpdb->prefix . 'ns_marketing_segments',
			array( 'contact_count' => $count ),
			array( 'id' => $segment_id )
		);
	}
}
