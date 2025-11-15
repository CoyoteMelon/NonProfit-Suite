<?php
/**
 * Formation Assistant Module (PRO)
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/modules
 */

defined( 'ABSPATH' ) or exit;

class NonprofitSuite_Formation {

	private static function check_pro() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'Formation Assistant module requires Pro license.', 'nonprofitsuite' ) );
		}
		return true;
	}

	public static function get_formation_progress() {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_formation_progress';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Use caching for formation progress
		$cache_key = NonprofitSuite_Cache::item_key( 'formation_progress', 'current' );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table ) {
			$progress = $wpdb->get_row(
				"SELECT id, current_phase, steps_completed, incorporation_state, incorporation_date, ein,
				        irs_determination_date, state_charity_registration, state_registration_date,
				        bylaws_adopted_date, board_established_date, bank_account_opened_date,
				        insurance_obtained_date, website_launched_date, created_at
				 FROM {$table}
				 ORDER BY id DESC
				 LIMIT 1"
			);

			if ( ! $progress ) {
				// Create initial record
				$wpdb->insert(
					$table,
					array(
						'current_phase' => 'planning',
						'steps_completed' => json_encode( array() ),
					),
					array( '%s', '%s' )
				);
				$progress = $wpdb->get_row(
					"SELECT id, current_phase, steps_completed, incorporation_state, incorporation_date, ein,
					        irs_determination_date, state_charity_registration, state_registration_date,
					        bylaws_adopted_date, board_established_date, bank_account_opened_date,
					        insurance_obtained_date, website_launched_date, created_at
					 FROM {$table}
					 ORDER BY id DESC
					 LIMIT 1"
				);
			}

			return $progress;
		}, 300 );
	}

	public static function update_progress( $phase, $step_id, $completed ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'manage_options', 'manage formation' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_formation_progress';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$progress = self::get_formation_progress();

		$steps_completed = ! empty( $progress->steps_completed ) ? json_decode( $progress->steps_completed, true ) : array();

		if ( ! is_array( $steps_completed ) ) {
			$steps_completed = array();
		}

		$step_key = $phase . '_' . $step_id;

		if ( $completed ) {
			$steps_completed[ $step_key ] = current_time( 'mysql' );
		} else {
			unset( $steps_completed[ $step_key ] );
		}

		// Update current phase based on completion
		$current_phase = self::determine_current_phase( $steps_completed );

		$result = $wpdb->update(
			$table,
			array(
				'steps_completed' => json_encode( $steps_completed ),
				'current_phase' => $current_phase,
			),
			array( 'id' => $progress->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			NonprofitSuite_Cache::delete( NonprofitSuite_Cache::item_key( 'formation_progress', 'current' ) );
		}

		return $result !== false;
	}

	public static function get_current_phase() {
		$progress = self::get_formation_progress();
		return $progress->current_phase;
	}

	public static function get_next_steps( $limit = 5 ) {
		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$progress = self::get_formation_progress();
		$steps_completed = ! empty( $progress->steps_completed ) ? json_decode( $progress->steps_completed, true ) : array();

		if ( ! is_array( $steps_completed ) ) {
			$steps_completed = array();
		}

		$all_steps = self::get_all_steps();
		$next_steps = array();

		foreach ( $all_steps as $phase => $steps ) {
			foreach ( $steps as $step_id => $step_title ) {
				$step_key = $phase . '_' . $step_id;
				if ( ! isset( $steps_completed[ $step_key ] ) ) {
					$next_steps[] = array(
						'phase' => $phase,
						'step_id' => $step_id,
						'title' => $step_title,
					);

					if ( count( $next_steps ) >= $limit ) {
						return $next_steps;
					}
				}
			}
		}

		return $next_steps;
	}

	public static function get_completion_percentage() {
		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return 0;

		$progress = self::get_formation_progress();
		$steps_completed = ! empty( $progress->steps_completed ) ? json_decode( $progress->steps_completed, true ) : array();

		if ( ! is_array( $steps_completed ) ) {
			$steps_completed = array();
		}

		$all_steps = self::get_all_steps();
		$total_steps = 0;

		foreach ( $all_steps as $steps ) {
			$total_steps += count( $steps );
		}

		$completed_count = count( $steps_completed );

		return $total_steps > 0 ? round( ( $completed_count / $total_steps ) * 100, 1 ) : 0;
	}

	public static function set_date( $field, $date ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_formation_progress';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$progress = self::get_formation_progress();

		$allowed_fields = array(
			'incorporation_state',
			'incorporation_date',
			'ein',
			'irs_determination_date',
			'state_charity_registration',
			'state_registration_date',
			'bylaws_adopted_date',
			'board_established_date',
			'bank_account_opened_date',
			'insurance_obtained_date',
			'website_launched_date',
		);

		if ( ! in_array( $field, $allowed_fields ) ) {
			return new WP_Error( 'invalid_field', __( 'Invalid field name.', 'nonprofitsuite' ) );
		}

		$result = $wpdb->update(
			$table,
			array( $field => sanitize_text_field( $date ) ),
			array( 'id' => $progress->id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			NonprofitSuite_Cache::delete( NonprofitSuite_Cache::item_key( 'formation_progress', 'current' ) );
		}

		return $result !== false;
	}

	private static function determine_current_phase( $steps_completed ) {
		$all_steps = self::get_all_steps();

		// Check each phase - if all steps in a phase are complete, move to next phase
		$phases = array_keys( $all_steps );

		foreach ( $phases as $phase ) {
			$phase_steps = $all_steps[ $phase ];
			$all_complete = true;

			foreach ( $phase_steps as $step_id => $step_title ) {
				$step_key = $phase . '_' . $step_id;
				if ( ! isset( $steps_completed[ $step_key ] ) ) {
					$all_complete = false;
					break;
				}
			}

			if ( ! $all_complete ) {
				return $phase;
			}
		}

		// All phases complete
		return 'completed';
	}

	public static function get_all_steps() {
		return array(
			'planning' => array(
				'mission' => __( 'Define mission statement', 'nonprofitsuite' ),
				'board_members' => __( 'Identify founding board members (minimum 3)', 'nonprofitsuite' ),
				'research' => __( 'Research similar organizations', 'nonprofitsuite' ),
				'programs' => __( 'Determine programs/services', 'nonprofitsuite' ),
				'assess' => __( 'Assess need for nonprofit status', 'nonprofitsuite' ),
				'name' => __( 'Choose organization name', 'nonprofitsuite' ),
				'check_name' => __( 'Check name availability', 'nonprofitsuite' ),
			),
			'incorporation' => array(
				'state' => __( 'Choose state of incorporation', 'nonprofitsuite' ),
				'articles' => __( 'Draft Articles of Incorporation', 'nonprofitsuite' ),
				'file_articles' => __( 'File Articles with Secretary of State', 'nonprofitsuite' ),
				'certificate' => __( 'Receive Certificate of Incorporation', 'nonprofitsuite' ),
				'ein' => __( 'Apply for EIN with IRS', 'nonprofitsuite' ),
			),
			'governance' => array(
				'bylaws' => __( 'Draft organizational bylaws', 'nonprofitsuite' ),
				'meeting' => __( 'Hold first board meeting', 'nonprofitsuite' ),
				'adopt_bylaws' => __( 'Adopt bylaws', 'nonprofitsuite' ),
				'officers' => __( 'Elect officers (President, Secretary, Treasurer)', 'nonprofitsuite' ),
				'conflict' => __( 'Adopt conflict of interest policy', 'nonprofitsuite' ),
				'schedule' => __( 'Create board meeting schedule', 'nonprofitsuite' ),
			),
			'irs' => array(
				'determine' => __( 'Determine eligibility (Form 1023 vs 1023-EZ)', 'nonprofitsuite' ),
				'prepare' => __( 'Prepare required documents', 'nonprofitsuite' ),
				'complete' => __( 'Complete Form 1023/1023-EZ', 'nonprofitsuite' ),
				'submit' => __( 'Submit to IRS (Pay.gov)', 'nonprofitsuite' ),
				'respond' => __( 'Respond to any IRS questions', 'nonprofitsuite' ),
				'letter' => __( 'Receive determination letter', 'nonprofitsuite' ),
			),
			'state' => array(
				'register' => __( 'Register for state charity solicitation', 'nonprofitsuite' ),
				'reports' => __( 'File initial state reports', 'nonprofitsuite' ),
				'exemption' => __( 'Register for state sales tax exemption', 'nonprofitsuite' ),
				'annual' => __( 'File state nonprofit report', 'nonprofitsuite' ),
			),
			'operations' => array(
				'bank' => __( 'Open bank account', 'nonprofitsuite' ),
				'bookkeeping' => __( 'Set up bookkeeping system', 'nonprofitsuite' ),
				'insurance' => __( 'Purchase insurance (D&O, general liability)', 'nonprofitsuite' ),
				'receipt' => __( 'Create donation receipt template', 'nonprofitsuite' ),
				'procedures' => __( 'Establish accounting procedures', 'nonprofitsuite' ),
				'fiscal' => __( 'Set fiscal year', 'nonprofitsuite' ),
			),
			'launch' => array(
				'website' => __( 'Create website', 'nonprofitsuite' ),
				'logo' => __( 'Design logo and branding', 'nonprofitsuite' ),
				'cards' => __( 'Print business cards', 'nonprofitsuite' ),
				'donate' => __( 'Launch donation page', 'nonprofitsuite' ),
				'announce' => __( 'Announce programs', 'nonprofitsuite' ),
				'event' => __( 'Hold public launch event', 'nonprofitsuite' ),
			),
		);
	}

	public static function get_phase_names() {
		return array(
			'planning' => __( 'Planning', 'nonprofitsuite' ),
			'incorporation' => __( 'Incorporation', 'nonprofitsuite' ),
			'governance' => __( 'Governance', 'nonprofitsuite' ),
			'irs' => __( 'IRS Application', 'nonprofitsuite' ),
			'state' => __( 'State Registration', 'nonprofitsuite' ),
			'operations' => __( 'Operations', 'nonprofitsuite' ),
			'launch' => __( 'Public Launch', 'nonprofitsuite' ),
		);
	}
}
