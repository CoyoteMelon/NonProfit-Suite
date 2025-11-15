<?php
/**
 * Entity Attachment Manager
 *
 * Handles attaching documents to entities (tasks, projects, meetings, people, donors, etc.)
 * and document-to-document relationships (citations, references, related documents).
 *
 * @package    NonprofitSuite
 * @subpackage Integrations/Storage
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Entity Attachment Manager Class
 */
class NonprofitSuite_Entity_Attachment_Manager {

	/**
	 * Singleton instance
	 *
	 * @var NonprofitSuite_Entity_Attachment_Manager
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return NonprofitSuite_Entity_Attachment_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Future: register hooks if needed
	}

	/**
	 * Attach a document to an entity
	 *
	 * @param int    $file_id File ID.
	 * @param string $entity_type Entity type (task, project, meeting, person, donor, etc.).
	 * @param int    $entity_id Entity ID.
	 * @param string $note Optional note about the attachment.
	 * @return int|WP_Error Attachment ID on success, WP_Error on failure.
	 */
	public function attach_to_entity( $file_id, $entity_type, $entity_id, $note = '' ) {
		global $wpdb;

		// Validate inputs
		if ( empty( $file_id ) || empty( $entity_type ) || empty( $entity_id ) ) {
			return new WP_Error( 'missing_required', 'File ID, entity type, and entity ID are required.' );
		}

		// Check if file exists
		$table_files = $wpdb->prefix . 'ns_storage_files';
		$file_exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_files} WHERE id = %d",
			$file_id
		) );

		if ( ! $file_exists ) {
			return new WP_Error( 'file_not_found', 'File not found.' );
		}

		// Check if attachment already exists
		$table_attachments = $wpdb->prefix . 'ns_storage_entity_attachments';
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table_attachments}
			 WHERE file_id = %d AND entity_type = %s AND entity_id = %d",
			$file_id,
			$entity_type,
			$entity_id
		) );

		if ( $exists ) {
			return new WP_Error( 'already_attached', 'Document is already attached to this entity.' );
		}

		// Create attachment
		$result = $wpdb->insert(
			$table_attachments,
			array(
				'file_id'         => $file_id,
				'entity_type'     => sanitize_text_field( $entity_type ),
				'entity_id'       => $entity_id,
				'attachment_note' => sanitize_textarea_field( $note ),
				'attached_by'     => get_current_user_id(),
				'attached_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%d', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', 'Failed to create attachment.' );
		}

		do_action( 'ns_storage_document_attached', $wpdb->insert_id, $file_id, $entity_type, $entity_id );

		return $wpdb->insert_id;
	}

	/**
	 * Detach a document from an entity
	 *
	 * @param int    $file_id File ID.
	 * @param string $entity_type Entity type.
	 * @param int    $entity_id Entity ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function detach_from_entity( $file_id, $entity_type, $entity_id ) {
		global $wpdb;

		$table_attachments = $wpdb->prefix . 'ns_storage_entity_attachments';

		$result = $wpdb->delete(
			$table_attachments,
			array(
				'file_id'     => $file_id,
				'entity_type' => $entity_type,
				'entity_id'   => $entity_id,
			),
			array( '%d', '%s', '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', 'Failed to detach document.' );
		}

		if ( 0 === $result ) {
			return new WP_Error( 'not_attached', 'Document is not attached to this entity.' );
		}

		do_action( 'ns_storage_document_detached', $file_id, $entity_type, $entity_id );

		return true;
	}

	/**
	 * Get all documents attached to an entity
	 *
	 * @param string $entity_type Entity type.
	 * @param int    $entity_id Entity ID.
	 * @param int    $user_id User ID for permission checking (optional).
	 * @return array Array of file objects with attachment info.
	 */
	public function get_entity_documents( $entity_type, $entity_id, $user_id = null ) {
		global $wpdb;

		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$table_attachments = $wpdb->prefix . 'ns_storage_entity_attachments';
		$table_files = $wpdb->prefix . 'ns_storage_files';

		$documents = $wpdb->get_results( $wpdb->prepare(
			"SELECT f.*, a.attachment_note, a.attached_by, a.attached_at
			 FROM {$table_attachments} a
			 INNER JOIN {$table_files} f ON a.file_id = f.id
			 WHERE a.entity_type = %s AND a.entity_id = %d
			 AND f.deleted_at IS NULL
			 ORDER BY a.attached_at DESC",
			$entity_type,
			$entity_id
		) );

		// Filter by permissions if permission manager is available
		if ( class_exists( 'NonprofitSuite_Document_Permissions' ) ) {
			$permission_manager = NonprofitSuite_Document_Permissions::get_instance();
			$documents = array_filter( $documents, function( $doc ) use ( $permission_manager, $user_id ) {
				return $permission_manager->can_access_file( $doc->id, $user_id, 'read' );
			} );
		}

		return $documents;
	}

	/**
	 * Get all entities a document is attached to
	 *
	 * @param int $file_id File ID.
	 * @return array Array of attachments.
	 */
	public function get_document_entities( $file_id ) {
		global $wpdb;

		$table_attachments = $wpdb->prefix . 'ns_storage_entity_attachments';

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table_attachments}
			 WHERE file_id = %d
			 ORDER BY entity_type, attached_at DESC",
			$file_id
		) );
	}

	/**
	 * Link two documents together (citation, reference, etc.)
	 *
	 * @param int    $source_file_id Source document ID.
	 * @param int    $target_file_id Target document ID.
	 * @param string $relationship_type Relationship type (citation, reference, related, etc.).
	 * @param string $note Optional note about the relationship.
	 * @return int|WP_Error Relationship ID on success, WP_Error on failure.
	 */
	public function link_documents( $source_file_id, $target_file_id, $relationship_type, $note = '' ) {
		global $wpdb;

		// Validate
		if ( empty( $source_file_id ) || empty( $target_file_id ) || empty( $relationship_type ) ) {
			return new WP_Error( 'missing_required', 'Source file ID, target file ID, and relationship type are required.' );
		}

		if ( $source_file_id === $target_file_id ) {
			return new WP_Error( 'invalid_relationship', 'Cannot link a document to itself.' );
		}

		// Check if both files exist
		$table_files = $wpdb->prefix . 'ns_storage_files';
		$source_exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_files} WHERE id = %d",
			$source_file_id
		) );
		$target_exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_files} WHERE id = %d",
			$target_file_id
		) );

		if ( ! $source_exists || ! $target_exists ) {
			return new WP_Error( 'file_not_found', 'One or both files not found.' );
		}

		// Check if relationship already exists
		$table_relationships = $wpdb->prefix . 'ns_storage_document_relationships';
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table_relationships}
			 WHERE source_file_id = %d AND target_file_id = %d AND relationship_type = %s",
			$source_file_id,
			$target_file_id,
			$relationship_type
		) );

		if ( $exists ) {
			return new WP_Error( 'already_linked', 'Documents are already linked with this relationship type.' );
		}

		// Create relationship
		$result = $wpdb->insert(
			$table_relationships,
			array(
				'source_file_id'    => $source_file_id,
				'target_file_id'    => $target_file_id,
				'relationship_type' => sanitize_text_field( $relationship_type ),
				'relationship_note' => sanitize_textarea_field( $note ),
				'created_by'        => get_current_user_id(),
				'created_at'        => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', 'Failed to link documents.' );
		}

		do_action( 'ns_storage_documents_linked', $wpdb->insert_id, $source_file_id, $target_file_id, $relationship_type );

		return $wpdb->insert_id;
	}

	/**
	 * Unlink two documents
	 *
	 * @param int    $source_file_id Source document ID.
	 * @param int    $target_file_id Target document ID.
	 * @param string $relationship_type Relationship type.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function unlink_documents( $source_file_id, $target_file_id, $relationship_type ) {
		global $wpdb;

		$table_relationships = $wpdb->prefix . 'ns_storage_document_relationships';

		$result = $wpdb->delete(
			$table_relationships,
			array(
				'source_file_id'    => $source_file_id,
				'target_file_id'    => $target_file_id,
				'relationship_type' => $relationship_type,
			),
			array( '%d', '%d', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', 'Failed to unlink documents.' );
		}

		if ( 0 === $result ) {
			return new WP_Error( 'not_linked', 'Documents are not linked with this relationship type.' );
		}

		do_action( 'ns_storage_documents_unlinked', $source_file_id, $target_file_id, $relationship_type );

		return true;
	}

	/**
	 * Get all documents related to a document
	 *
	 * @param int    $file_id File ID.
	 * @param string $relationship_type Filter by relationship type (optional).
	 * @param string $direction 'outgoing' (this doc references others), 'incoming' (others reference this), 'both'.
	 * @return array Array of related documents with relationship info.
	 */
	public function get_related_documents( $file_id, $relationship_type = '', $direction = 'both' ) {
		global $wpdb;

		$table_relationships = $wpdb->prefix . 'ns_storage_document_relationships';
		$table_files = $wpdb->prefix . 'ns_storage_files';

		$results = array();

		// Outgoing relationships (this document references others)
		if ( in_array( $direction, array( 'outgoing', 'both' ), true ) ) {
			$where = '';
			if ( ! empty( $relationship_type ) ) {
				$where = $wpdb->prepare( ' AND r.relationship_type = %s', $relationship_type );
			}

			$outgoing = $wpdb->get_results( $wpdb->prepare(
				"SELECT f.*, r.relationship_type, r.relationship_note, r.created_at as linked_at,
				        'outgoing' as direction
				 FROM {$table_relationships} r
				 INNER JOIN {$table_files} f ON r.target_file_id = f.id
				 WHERE r.source_file_id = %d {$where}
				 AND f.deleted_at IS NULL
				 ORDER BY r.created_at DESC",
				$file_id
			) );

			$results = array_merge( $results, $outgoing );
		}

		// Incoming relationships (other documents reference this one)
		if ( in_array( $direction, array( 'incoming', 'both' ), true ) ) {
			$where = '';
			if ( ! empty( $relationship_type ) ) {
				$where = $wpdb->prepare( ' AND r.relationship_type = %s', $relationship_type );
			}

			$incoming = $wpdb->get_results( $wpdb->prepare(
				"SELECT f.*, r.relationship_type, r.relationship_note, r.created_at as linked_at,
				        'incoming' as direction
				 FROM {$table_relationships} r
				 INNER JOIN {$table_files} f ON r.source_file_id = f.id
				 WHERE r.target_file_id = %d {$where}
				 AND f.deleted_at IS NULL
				 ORDER BY r.created_at DESC",
				$file_id
			) );

			$results = array_merge( $results, $incoming );
		}

		return $results;
	}

	/**
	 * Get all documents authored by a user
	 *
	 * @param int $user_id User ID.
	 * @param int $viewer_id Viewer user ID (for permission checking).
	 * @return array Array of documents.
	 */
	public function get_user_authored_documents( $user_id, $viewer_id = null ) {
		global $wpdb;

		if ( null === $viewer_id ) {
			$viewer_id = get_current_user_id();
		}

		$table_files = $wpdb->prefix . 'ns_storage_files';

		// Get documents created by user
		$documents = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table_files}
			 WHERE created_by = %d
			 AND deleted_at IS NULL
			 ORDER BY created_at DESC",
			$user_id
		) );

		// Filter by permissions
		if ( class_exists( 'NonprofitSuite_Document_Permissions' ) ) {
			$permission_manager = NonprofitSuite_Document_Permissions::get_instance();
			$documents = array_filter( $documents, function( $doc ) use ( $permission_manager, $viewer_id ) {
				return $permission_manager->can_access_file( $doc->id, $viewer_id, 'read' );
			} );
		}

		return array_values( $documents );
	}

	/**
	 * Get attachment counts by entity type
	 *
	 * @param int $file_id File ID.
	 * @return array Entity type => count.
	 */
	public function get_attachment_counts( $file_id ) {
		global $wpdb;

		$table_attachments = $wpdb->prefix . 'ns_storage_entity_attachments';

		$counts = $wpdb->get_results( $wpdb->prepare(
			"SELECT entity_type, COUNT(*) as count
			 FROM {$table_attachments}
			 WHERE file_id = %d
			 GROUP BY entity_type",
			$file_id
		), OBJECT_K );

		$result = array();
		foreach ( $counts as $entity_type => $data ) {
			$result[ $entity_type ] = $data->count;
		}

		return $result;
	}

	/**
	 * Get relationship counts by type
	 *
	 * @param int $file_id File ID.
	 * @return array Relationship type => count.
	 */
	public function get_relationship_counts( $file_id ) {
		global $wpdb;

		$table_relationships = $wpdb->prefix . 'ns_storage_document_relationships';

		$counts = $wpdb->get_results( $wpdb->prepare(
			"SELECT relationship_type, COUNT(*) as count
			 FROM {$table_relationships}
			 WHERE source_file_id = %d OR target_file_id = %d
			 GROUP BY relationship_type",
			$file_id,
			$file_id
		), OBJECT_K );

		$result = array();
		foreach ( $counts as $relationship_type => $data ) {
			$result[ $relationship_type ] = $data->count;
		}

		return $result;
	}

	/**
	 * Bulk attach documents to an entity
	 *
	 * @param array  $file_ids Array of file IDs.
	 * @param string $entity_type Entity type.
	 * @param int    $entity_id Entity ID.
	 * @param string $note Optional note.
	 * @return array Results array with success/error for each file.
	 */
	public function bulk_attach_to_entity( $file_ids, $entity_type, $entity_id, $note = '' ) {
		$results = array();

		foreach ( $file_ids as $file_id ) {
			$result = $this->attach_to_entity( $file_id, $entity_type, $entity_id, $note );
			$results[ $file_id ] = $result;
		}

		return $results;
	}

	/**
	 * Get all valid relationship types
	 *
	 * @return array Relationship types with descriptions.
	 */
	public function get_relationship_types() {
		return array(
			'citation'      => __( 'Citation', 'nonprofitsuite' ),
			'reference'     => __( 'Reference', 'nonprofitsuite' ),
			'related'       => __( 'Related Document', 'nonprofitsuite' ),
			'supersedes'    => __( 'Supersedes', 'nonprofitsuite' ),
			'superseded_by' => __( 'Superseded By', 'nonprofitsuite' ),
			'amendment'     => __( 'Amendment', 'nonprofitsuite' ),
			'parent'        => __( 'Parent Document', 'nonprofitsuite' ),
			'child'         => __( 'Child Document', 'nonprofitsuite' ),
		);
	}

	/**
	 * Get all valid entity types
	 *
	 * @return array Entity types with descriptions.
	 */
	public function get_entity_types() {
		return apply_filters( 'ns_storage_entity_types', array(
			'task'     => __( 'Task', 'nonprofitsuite' ),
			'project'  => __( 'Project', 'nonprofitsuite' ),
			'meeting'  => __( 'Meeting', 'nonprofitsuite' ),
			'agenda'   => __( 'Agenda', 'nonprofitsuite' ),
			'person'   => __( 'Person', 'nonprofitsuite' ),
			'donor'    => __( 'Donor', 'nonprofitsuite' ),
			'grant'    => __( 'Grant', 'nonprofitsuite' ),
			'event'    => __( 'Event', 'nonprofitsuite' ),
			'campaign' => __( 'Campaign', 'nonprofitsuite' ),
		) );
	}
}
