<?php
/**
 * Document Retention Helper
 *
 * Handles document retention policy enforcement, archival, and expiration.
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Document retention helper class.
 */
class NonprofitSuite_Document_Retention {

	/**
	 * Apply retention policy to a document.
	 *
	 * @param int    $document_id The document ID.
	 * @param string $policy_key  The policy key to apply (optional, auto-detected if not provided).
	 * @return bool True on success, false on failure.
	 */
	public static function apply_policy( $document_id, $policy_key = null ) {
		$document = NonprofitSuite_Document::get( $document_id );
		if ( ! $document ) {
			return false;
		}

		// Auto-detect policy if not provided
		if ( ! $policy_key ) {
			$policy = NonprofitSuite_Retention_Policy::get_policy_for_category( $document->category );
			if ( ! $policy ) {
				return false;
			}
			$policy_key = $policy->policy_key;
		} else {
			$policy = NonprofitSuite_Retention_Policy::get_by_key( $policy_key );
			if ( ! $policy ) {
				return false;
			}
		}

		// Calculate expiration date
		$expiration_date = null;
		if ( $policy->retention_years > 0 ) {
			$created_date = new DateTime( $document->created_at );
			$expiration_date = $created_date->add( new DateInterval( "P{$policy->retention_years}Y" ) );
			$expiration_date = $expiration_date->format( 'Y-m-d H:i:s' );
		}

		// Update document with retention policy
		return NonprofitSuite_Document::update( $document_id, array(
			'retention_policy' => $policy_key,
			'expiration_date' => $expiration_date,
		) );
	}

	/**
	 * Archive a document.
	 *
	 * @param int $document_id The document ID.
	 * @return bool True on success, false on failure.
	 */
	public static function archive_document( $document_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_documents';

		return $wpdb->update(
			$table,
			array(
				'is_archived' => 1,
				'archived_at' => current_time( 'mysql' ),
			),
			array( 'id' => $document_id ),
			array( '%d', '%s' ),
			array( '%d' )
		) !== false;
	}

	/**
	 * Unarchive a document.
	 *
	 * @param int $document_id The document ID.
	 * @return bool True on success, false on failure.
	 */
	public static function unarchive_document( $document_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_documents';

		return $wpdb->update(
			$table,
			array(
				'is_archived' => 0,
				'archived_at' => null,
			),
			array( 'id' => $document_id ),
			array( '%d', '%s' ),
			array( '%d' )
		) !== false;
	}

	/**
	 * Mark a document as expired.
	 *
	 * @param int $document_id The document ID.
	 * @return bool True on success, false on failure.
	 */
	public static function expire_document( $document_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_documents';

		return $wpdb->update(
			$table,
			array( 'is_expired' => 1 ),
			array( 'id' => $document_id ),
			array( '%d' ),
			array( '%d' )
		) !== false;
	}

	/**
	 * Process automatic archival of documents.
	 *
	 * Archives documents that have exceeded their auto-archive threshold.
	 *
	 * @return array Results array with counts.
	 */
	public static function process_auto_archival() {
		global $wpdb;
		$docs_table = $wpdb->prefix . 'ns_documents';
		$policies_table = $wpdb->prefix . 'ns_retention_policies';

		$archived_count = 0;
		$errors = array();

		// Get all active policies
		$policies = NonprofitSuite_Retention_Policy::get_all( array( 'is_active' => 1 ) );

		foreach ( $policies as $policy ) {
			$days = $policy->auto_archive_after_days;
			if ( $days <= 0 ) {
				continue;
			}

			// Calculate cutoff date
			$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

			// Find documents eligible for archival
			$query = $wpdb->prepare(
				"SELECT id FROM {$docs_table}
				 WHERE retention_policy = %s
				   AND is_archived = 0
				   AND created_at <= %s",
				$policy->policy_key,
				$cutoff_date
			);

			$documents = $wpdb->get_col( $query );

			foreach ( $documents as $doc_id ) {
				if ( self::archive_document( $doc_id ) ) {
					$archived_count++;
				} else {
					$errors[] = "Failed to archive document ID: {$doc_id}";
				}
			}
		}

		return array(
			'archived_count' => $archived_count,
			'errors' => $errors,
		);
	}

	/**
	 * Process document expiration.
	 *
	 * Marks documents as expired when they pass their expiration date.
	 *
	 * @return array Results array with counts.
	 */
	public static function process_expiration() {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_documents';

		$expired_count = 0;

		// Find documents that have passed their expiration date
		$query = $wpdb->prepare(
			"SELECT id FROM {$table}
			 WHERE is_expired = 0
			   AND expiration_date IS NOT NULL
			   AND expiration_date <= %s",
			current_time( 'mysql' )
		);

		$documents = $wpdb->get_col( $query );

		foreach ( $documents as $doc_id ) {
			if ( self::expire_document( $doc_id ) ) {
				$expired_count++;
			}
		}

		return array(
			'expired_count' => $expired_count,
		);
	}

	/**
	 * Get documents eligible for archival.
	 *
	 * @param string $policy_key Optional policy key to filter by.
	 * @return array Array of document objects.
	 */
	public static function get_eligible_for_archival( $policy_key = null ) {
		global $wpdb;
		$docs_table = $wpdb->prefix . 'ns_documents';
		$policies_table = $wpdb->prefix . 'ns_retention_policies';

		$where = "WHERE d.is_archived = 0 AND p.auto_archive_after_days > 0";

		if ( $policy_key ) {
			$where .= $wpdb->prepare( " AND d.retention_policy = %s", $policy_key );
		}

		$query = "SELECT d.*, p.auto_archive_after_days,
		                 DATEDIFF(CURDATE(), DATE(d.created_at)) as days_since_creation
		          FROM {$docs_table} d
		          INNER JOIN {$policies_table} p ON d.retention_policy = p.policy_key
		          {$where}
		          HAVING days_since_creation >= p.auto_archive_after_days
		          ORDER BY d.created_at ASC";

		return $wpdb->get_results( $query );
	}

	/**
	 * Get documents eligible for expiration.
	 *
	 * @param string $policy_key Optional policy key to filter by.
	 * @return array Array of document objects.
	 */
	public static function get_eligible_for_expiration( $policy_key = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_documents';

		$where = $wpdb->prepare(
			"WHERE is_expired = 0
			   AND expiration_date IS NOT NULL
			   AND expiration_date <= %s",
			current_time( 'mysql' )
		);

		if ( $policy_key ) {
			$where .= $wpdb->prepare( " AND retention_policy = %s", $policy_key );
		}

		$query = "SELECT * FROM {$table} {$where} ORDER BY expiration_date ASC";

		return $wpdb->get_results( $query );
	}

	/**
	 * Get retention statistics.
	 *
	 * @return array Statistics array.
	 */
	public static function get_statistics() {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_documents';

		$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$archived = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_archived = 1" );
		$expired = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_expired = 1" );
		$active = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_archived = 0 AND is_expired = 0" );

		$eligible_for_archival = count( self::get_eligible_for_archival() );
		$eligible_for_expiration = count( self::get_eligible_for_expiration() );

		return array(
			'total_documents' => (int) $total,
			'active_documents' => (int) $active,
			'archived_documents' => (int) $archived,
			'expired_documents' => (int) $expired,
			'eligible_for_archival' => $eligible_for_archival,
			'eligible_for_expiration' => $eligible_for_expiration,
		);
	}

	/**
	 * Bulk apply retention policies to existing documents.
	 *
	 * @return array Results array.
	 */
	public static function bulk_apply_policies() {
		global $wpdb;
		$table = $wpdb->prefix . 'ns_documents';

		$updated_count = 0;
		$errors = array();

		// Get all documents without a retention policy
		$documents = $wpdb->get_results(
			"SELECT id, category FROM {$table} WHERE retention_policy IS NULL OR retention_policy = 'standard'"
		);

		foreach ( $documents as $document ) {
			if ( self::apply_policy( $document->id ) ) {
				$updated_count++;
			} else {
				$errors[] = "Failed to apply policy to document ID: {$document->id}";
			}
		}

		return array(
			'updated_count' => $updated_count,
			'errors' => $errors,
		);
	}

	/**
	 * Get archived documents.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of document objects.
	 */
	public static function get_archived_documents( $args = array() ) {
		$defaults = array(
			'category' => '',
			'retention_policy' => '',
			'orderby' => 'archived_at',
			'order' => 'DESC',
			'limit' => 50,
		);

		$args = wp_parse_args( $args, $defaults );
		$args['is_archived'] = 1;

		global $wpdb;
		$table = $wpdb->prefix . 'ns_documents';

		$where = "WHERE is_archived = 1";
		if ( $args['category'] ) {
			$where .= $wpdb->prepare( " AND category = %s", $args['category'] );
		}
		if ( $args['retention_policy'] ) {
			$where .= $wpdb->prepare( " AND retention_policy = %s", $args['retention_policy'] );
		}

		$orderby = sanitize_sql_orderby( "{$args['orderby']} {$args['order']}" );
		$limit = absint( $args['limit'] );

		$query = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} LIMIT {$limit}";

		return $wpdb->get_results( $query );
	}
}
