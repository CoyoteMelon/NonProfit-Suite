<?php
/**
 * Training Module (PRO)
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/modules
 */

defined( 'ABSPATH' ) or exit;

class NonprofitSuite_Training {

	private static function check_pro() {
		if ( ! NonprofitSuite_License::is_pro_active() ) {
			return new WP_Error( 'pro_required', __( 'Training module requires Pro license.', 'nonprofitsuite' ) );
		}
		return true;
	}

	// COURSE MANAGEMENT

	public static function create_course( $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage training' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_training_courses';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$wpdb->insert(
			$table,
			array(
				'title' => sanitize_text_field( $data['title'] ),
				'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : null,
				'category' => isset( $data['category'] ) ? sanitize_text_field( $data['category'] ) : null,
				'duration_minutes' => isset( $data['duration_minutes'] ) ? absint( $data['duration_minutes'] ) : null,
				'required_for' => isset( $data['required_for'] ) ? sanitize_text_field( $data['required_for'] ) : null,
				'frequency' => isset( $data['frequency'] ) ? sanitize_text_field( $data['frequency'] ) : null,
				'content_type' => isset( $data['content_type'] ) ? sanitize_text_field( $data['content_type'] ) : 'internal',
				'external_url' => isset( $data['external_url'] ) ? esc_url_raw( $data['external_url'] ) : null,
				'passing_score' => isset( $data['passing_score'] ) ? absint( $data['passing_score'] ) : null,
				'certificate_enabled' => isset( $data['certificate_enabled'] ) ? 1 : 0,
				'status' => 'active',
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
		);

		NonprofitSuite_Cache::invalidate_module( 'training_courses' );
		return $wpdb->insert_id;
	}

	public static function get_course( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_training_courses';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Use caching for individual courses
		$cache_key = NonprofitSuite_Cache::item_key( 'training_course', $id );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $id ) {
			return $wpdb->get_row( $wpdb->prepare(
				"SELECT id, title, description, category, duration_minutes, required_for,
				        frequency, content_type, external_url, passing_score, certificate_enabled,
				        status, created_at
				 FROM {$table}
				 WHERE id = %d",
				$id
			) );
		}, 300 );
	}

	public static function get_courses( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_training_courses';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Parse pagination arguments
		$defaults = array(
			'category' => null,
			'required_for' => null,
			'status' => 'active',
		);
		$args = NonprofitSuite_Utilities::parse_pagination_args( wp_parse_args( $args, $defaults ) );

		$where = "WHERE 1=1";
		if ( $args['category'] ) {
			$where .= $wpdb->prepare( " AND category = %s", $args['category'] );
		}
		if ( $args['required_for'] ) {
			$where .= $wpdb->prepare( " AND required_for LIKE %s", '%' . $wpdb->esc_like( $args['required_for'] ) . '%' );
		}
		if ( $args['status'] ) {
			$where .= $wpdb->prepare( " AND status = %s", $args['status'] );
		}

		// Use caching for course lists
		$cache_key = NonprofitSuite_Cache::list_key( 'training_courses', $args );
		return NonprofitSuite_Cache::remember( $cache_key, function() use ( $wpdb, $table, $where, $args ) {
			$sql = "SELECT id, title, description, category, duration_minutes, required_for,
			               frequency, content_type, external_url, passing_score, certificate_enabled,
			               status, created_at
			        FROM {$table} {$where}
			        ORDER BY title ASC
			        " . NonprofitSuite_Utilities::build_limit_clause( $args );

			return $wpdb->get_results( $sql );
		}, 300 );
	}

	public static function update_course( $id, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage training' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_training_courses';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$update_data = array();
		$update_format = array();

		$allowed_fields = array(
			'title' => '%s',
			'description' => '%s',
			'category' => '%s',
			'duration_minutes' => '%d',
			'required_for' => '%s',
			'frequency' => '%s',
			'content_type' => '%s',
			'external_url' => '%s',
			'passing_score' => '%d',
			'certificate_enabled' => '%d',
		);

		foreach ( $allowed_fields as $field => $format ) {
			if ( isset( $data[ $field ] ) ) {
				if ( $format === '%s' ) {
					$update_data[ $field ] = sanitize_text_field( $data[ $field ] );
				} elseif ( $format === '%d' ) {
					$update_data[ $field ] = absint( $data[ $field ] );
				}
				$update_format[] = $format;
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		return $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $id ),
			$update_format,
			array( '%d' )
		) !== false;
	}

	public static function deactivate_course( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_training_courses';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		return $wpdb->update(
			$table,
			array( 'status' => 'inactive' ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		) !== false;
	}

	// COMPLETION TRACKING

	public static function record_completion( $course_id, $person_id, $data ) {
		// Check permissions FIRST
		$permission_check = NonprofitSuite_Security::check_capability( 'edit_posts', 'manage training' );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ns_training_completions';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		// Get course details for frequency
		$course = self::get_course( $course_id );
		if ( ! $course ) {
			return new WP_Error( 'course_not_found', __( 'Course not found.', 'nonprofitsuite' ) );
		}

		$completion_date = isset( $data['completion_date'] ) ? sanitize_text_field( $data['completion_date'] ) : current_time( 'mysql' );
		$next_due_date = self::calculate_next_due_date( $completion_date, $course->frequency );

		$wpdb->insert(
			$table,
			array(
				'course_id' => absint( $course_id ),
				'person_id' => absint( $person_id ),
				'completion_date' => $completion_date,
				'score' => isset( $data['score'] ) ? absint( $data['score'] ) : null,
				'passed' => isset( $data['passed'] ) ? 1 : 1,
				'certificate_issued' => isset( $data['certificate_issued'] ) ? 1 : 0,
				'next_due_date' => $next_due_date,
				'notes' => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : null,
			),
			array( '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s' )
		);

		return $wpdb->insert_id;
	}

	public static function get_completions( $person_id ) {
		global $wpdb;
		$completions_table = $wpdb->prefix . 'ns_training_completions';
		$courses_table = $wpdb->prefix . 'ns_training_courses';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT tc.*, c.title, c.category, c.frequency
			FROM {$completions_table} tc
			JOIN {$courses_table} c ON tc.course_id = c.id
			WHERE tc.person_id = %d
			ORDER BY tc.completion_date DESC",
			$person_id
		) );
	}

	public static function get_course_completions( $course_id ) {
		global $wpdb;
		$completions_table = $wpdb->prefix . 'ns_training_completions';
		$people_table = $wpdb->prefix . 'ns_people';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT tc.*, p.first_name, p.last_name, p.email
			FROM {$completions_table} tc
			JOIN {$people_table} p ON tc.person_id = p.id
			WHERE tc.course_id = %d
			ORDER BY tc.completion_date DESC",
			$course_id
		) );
	}

	public static function check_compliance( $person_id, $role = null ) {
		global $wpdb;
		$courses_table = $wpdb->prefix . 'ns_training_courses';
		$completions_table = $wpdb->prefix . 'ns_training_completions';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$where = "WHERE c.status = 'active'";
		if ( $role ) {
			$where .= $wpdb->prepare( " AND c.required_for LIKE %s", '%' . $wpdb->esc_like( $role ) . '%' );
		}

		// Get all required courses
		$required_courses = $wpdb->get_results(
			"SELECT c.id, c.title, c.frequency
			FROM {$courses_table} c
			{$where}"
		);

		$missing = array();

		foreach ( $required_courses as $course ) {
			// Check if person has a current completion
			$completion = $wpdb->get_row( $wpdb->prepare(
				"SELECT id, course_id, person_id, completion_date, score, passed,
				        next_due_date, certificate_issued, created_at
				 FROM {$completions_table}
				 WHERE course_id = %d
				 AND person_id = %d
				 AND (next_due_date IS NULL OR next_due_date >= CURDATE())
				 ORDER BY completion_date DESC
				 LIMIT 1",
				$course->id,
				$person_id
			) );

			if ( ! $completion ) {
				$missing[] = $course;
			}
		}

		return $missing;
	}

	public static function get_upcoming_renewals( $days_ahead = 30 ) {
		global $wpdb;
		$completions_table = $wpdb->prefix . 'ns_training_completions';
		$courses_table = $wpdb->prefix . 'ns_training_courses';
		$people_table = $wpdb->prefix . 'ns_people';

		$check = self::check_pro();
		if ( is_wp_error( $check ) ) return $check;

		$date = date( 'Y-m-d', strtotime( "+{$days_ahead} days" ) );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT tc.*, c.title as course_title, p.first_name, p.last_name, p.email
			FROM {$completions_table} tc
			JOIN {$courses_table} c ON tc.course_id = c.id
			JOIN {$people_table} p ON tc.person_id = p.id
			WHERE tc.next_due_date IS NOT NULL
			AND tc.next_due_date >= CURDATE()
			AND tc.next_due_date <= %s
			ORDER BY tc.next_due_date ASC",
			$date
		) );
	}

	public static function calculate_next_due_date( $completion_date, $frequency ) {
		if ( ! $frequency || $frequency === 'once' ) {
			return null;
		}

		$date = new DateTime( $completion_date );

		switch ( $frequency ) {
			case 'annually':
				$date->modify( '+1 year' );
				break;
			case '2_years':
				$date->modify( '+2 years' );
				break;
			case '3_years':
				$date->modify( '+3 years' );
				break;
			default:
				return null;
		}

		return $date->format( 'Y-m-d' );
	}

	public static function get_categories() {
		return array(
			'orientation' => __( 'Orientation', 'nonprofitsuite' ),
			'safety' => __( 'Safety', 'nonprofitsuite' ),
			'compliance' => __( 'Compliance', 'nonprofitsuite' ),
			'skills' => __( 'Skills', 'nonprofitsuite' ),
			'leadership' => __( 'Leadership', 'nonprofitsuite' ),
			'technology' => __( 'Technology', 'nonprofitsuite' ),
			'fundraising' => __( 'Fundraising', 'nonprofitsuite' ),
			'program' => __( 'Program-Specific', 'nonprofitsuite' ),
		);
	}

	public static function get_frequencies() {
		return array(
			'once' => __( 'Once', 'nonprofitsuite' ),
			'annually' => __( 'Annually', 'nonprofitsuite' ),
			'2_years' => __( 'Every 2 Years', 'nonprofitsuite' ),
			'3_years' => __( 'Every 3 Years', 'nonprofitsuite' ),
		);
	}
}
