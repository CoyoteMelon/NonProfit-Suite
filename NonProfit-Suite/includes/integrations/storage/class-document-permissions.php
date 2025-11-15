<?php
/**
 * Document Permissions Manager
 *
 * Implements Unix-style owner/group/world permissions with RWX flags.
 * Supports group hierarchy where parent groups inherit child permissions.
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
 * Document Permissions Manager Class
 */
class NonprofitSuite_Document_Permissions {

	/**
	 * Singleton instance
	 *
	 * @var NonprofitSuite_Document_Permissions
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return NonprofitSuite_Document_Permissions
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
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks
	 */
	private function register_hooks() {
		add_filter( 'ns_storage_can_access_file', array( $this, 'check_file_access' ), 10, 3 );
	}

	/**
	 * Set file permissions (owner)
	 *
	 * @param int  $file_id File ID.
	 * @param int  $owner_id Owner user ID.
	 * @param bool $can_read Read permission.
	 * @param bool $can_write Write permission.
	 * @param bool $can_execute Execute/download permission.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function set_owner_permissions( $file_id, $owner_id, $can_read = true, $can_write = true, $can_execute = true ) {
		global $wpdb;

		$table_permissions = $wpdb->prefix . 'ns_storage_permissions';

		// Check if owner permission exists
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table_permissions}
			 WHERE file_id = %d AND permission_type = 'owner'",
			$file_id
		) );

		if ( $existing ) {
			// Update
			$result = $wpdb->update(
				$table_permissions,
				array(
					'owner_id'    => $owner_id,
					'can_read'    => $can_read ? 1 : 0,
					'can_write'   => $can_write ? 1 : 0,
					'can_execute' => $can_execute ? 1 : 0,
					'updated_at'  => current_time( 'mysql' ),
				),
				array( 'id' => $existing ),
				array( '%d', '%d', '%d', '%d', '%s' ),
				array( '%d' )
			);
		} else {
			// Insert
			$result = $wpdb->insert(
				$table_permissions,
				array(
					'file_id'         => $file_id,
					'permission_type' => 'owner',
					'owner_id'        => $owner_id,
					'can_read'        => $can_read ? 1 : 0,
					'can_write'       => $can_write ? 1 : 0,
					'can_execute'     => $can_execute ? 1 : 0,
					'created_by'      => get_current_user_id(),
					'created_at'      => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%s' )
			);
		}

		if ( false === $result ) {
			return new WP_Error( 'db_error', 'Failed to set owner permissions.' );
		}

		do_action( 'ns_storage_permissions_updated', $file_id, 'owner', $owner_id );

		return true;
	}

	/**
	 * Add group permissions
	 *
	 * @param int  $file_id File ID.
	 * @param int  $group_id Workspace/committee ID.
	 * @param bool $can_read Read permission.
	 * @param bool $can_write Write permission.
	 * @param bool $can_execute Execute/download permission.
	 * @param bool $inherit_to_children Whether parent groups inherit.
	 * @return int|WP_Error Permission ID on success, WP_Error on failure.
	 */
	public function add_group_permissions( $file_id, $group_id, $can_read = true, $can_write = false, $can_execute = true, $inherit_to_children = false ) {
		global $wpdb;

		$table_permissions = $wpdb->prefix . 'ns_storage_permissions';

		// Check if this group already has permissions
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table_permissions}
			 WHERE file_id = %d AND permission_type = 'group' AND group_id = %d",
			$file_id,
			$group_id
		) );

		if ( $existing ) {
			// Update
			$result = $wpdb->update(
				$table_permissions,
				array(
					'can_read'            => $can_read ? 1 : 0,
					'can_write'           => $can_write ? 1 : 0,
					'can_execute'         => $can_execute ? 1 : 0,
					'inherit_to_children' => $inherit_to_children ? 1 : 0,
					'updated_at'          => current_time( 'mysql' ),
				),
				array( 'id' => $existing ),
				array( '%d', '%d', '%d', '%d', '%s' ),
				array( '%d' )
			);

			if ( false === $result ) {
				return new WP_Error( 'db_error', 'Failed to update group permissions.' );
			}

			do_action( 'ns_storage_permissions_updated', $file_id, 'group', $group_id );

			return $existing;
		}

		// Insert new
		$result = $wpdb->insert(
			$table_permissions,
			array(
				'file_id'             => $file_id,
				'permission_type'     => 'group',
				'group_id'            => $group_id,
				'can_read'            => $can_read ? 1 : 0,
				'can_write'           => $can_write ? 1 : 0,
				'can_execute'         => $can_execute ? 1 : 0,
				'inherit_to_children' => $inherit_to_children ? 1 : 0,
				'created_by'          => get_current_user_id(),
				'created_at'          => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', 'Failed to add group permissions.' );
		}

		do_action( 'ns_storage_permissions_updated', $file_id, 'group', $group_id );

		return $wpdb->insert_id;
	}

	/**
	 * Remove group permissions
	 *
	 * @param int $file_id File ID.
	 * @param int $group_id Group ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function remove_group_permissions( $file_id, $group_id ) {
		global $wpdb;

		$table_permissions = $wpdb->prefix . 'ns_storage_permissions';

		$result = $wpdb->delete(
			$table_permissions,
			array(
				'file_id'         => $file_id,
				'permission_type' => 'group',
				'group_id'        => $group_id,
			),
			array( '%d', '%s', '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', 'Failed to remove group permissions.' );
		}

		do_action( 'ns_storage_permissions_removed', $file_id, 'group', $group_id );

		return true;
	}

	/**
	 * Set world permissions
	 *
	 * @param int  $file_id File ID.
	 * @param bool $can_read Read permission.
	 * @param bool $can_write Write permission.
	 * @param bool $can_execute Execute/download permission.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function set_world_permissions( $file_id, $can_read = false, $can_write = false, $can_execute = false ) {
		global $wpdb;

		$table_permissions = $wpdb->prefix . 'ns_storage_permissions';

		// Check if world permission exists
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table_permissions}
			 WHERE file_id = %d AND permission_type = 'world'",
			$file_id
		) );

		if ( $existing ) {
			// Update
			$result = $wpdb->update(
				$table_permissions,
				array(
					'can_read'    => $can_read ? 1 : 0,
					'can_write'   => $can_write ? 1 : 0,
					'can_execute' => $can_execute ? 1 : 0,
					'updated_at'  => current_time( 'mysql' ),
				),
				array( 'id' => $existing ),
				array( '%d', '%d', '%d', '%s' ),
				array( '%d' )
			);
		} else {
			// Insert
			$result = $wpdb->insert(
				$table_permissions,
				array(
					'file_id'         => $file_id,
					'permission_type' => 'world',
					'can_read'        => $can_read ? 1 : 0,
					'can_write'       => $can_write ? 1 : 0,
					'can_execute'     => $can_execute ? 1 : 0,
					'created_by'      => get_current_user_id(),
					'created_at'      => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%d', '%d', '%d', '%d', '%s' )
			);
		}

		if ( false === $result ) {
			return new WP_Error( 'db_error', 'Failed to set world permissions.' );
		}

		do_action( 'ns_storage_permissions_updated', $file_id, 'world', null );

		return true;
	}

	/**
	 * Check if user can access file
	 *
	 * @param int    $file_id File ID.
	 * @param int    $user_id User ID.
	 * @param string $permission_type Permission to check (read, write, execute).
	 * @return bool True if user has access.
	 */
	public function can_access_file( $file_id, $user_id, $permission_type = 'read' ) {
		global $wpdb;

		// Admins can access everything
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		// Map permission type to column
		$permission_column = 'can_' . $permission_type;
		if ( ! in_array( $permission_column, array( 'can_read', 'can_write', 'can_execute' ), true ) ) {
			return false;
		}

		$table_permissions = $wpdb->prefix . 'ns_storage_permissions';

		// Check owner permissions
		$owner_permission = $wpdb->get_var( $wpdb->prepare(
			"SELECT {$permission_column} FROM {$table_permissions}
			 WHERE file_id = %d AND permission_type = 'owner' AND owner_id = %d",
			$file_id,
			$user_id
		) );

		if ( $owner_permission ) {
			return true;
		}

		// Check group permissions
		$user_groups = $this->get_user_groups( $user_id );
		if ( ! empty( $user_groups ) ) {
			$group_ids_placeholder = implode( ',', array_fill( 0, count( $user_groups ), '%d' ) );

			$query = "SELECT {$permission_column}, group_id, inherit_to_children FROM {$table_permissions}
			          WHERE file_id = %d AND permission_type = 'group' AND group_id IN ({$group_ids_placeholder})";

			$params = array_merge( array( $file_id ), $user_groups );

			$group_permissions = $wpdb->get_results( $wpdb->prepare( $query, $params ) );

			foreach ( $group_permissions as $perm ) {
				if ( $perm->{$permission_column} ) {
					return true;
				}

				// Check parent groups if inheritance is enabled
				if ( $perm->inherit_to_children ) {
					$parent_groups = $this->get_parent_groups( $perm->group_id );
					foreach ( $parent_groups as $parent_id ) {
						if ( in_array( $parent_id, $user_groups, true ) ) {
							return true;
						}
					}
				}
			}
		}

		// Check world permissions
		$world_permission = $wpdb->get_var( $wpdb->prepare(
			"SELECT {$permission_column} FROM {$table_permissions}
			 WHERE file_id = %d AND permission_type = 'world'",
			$file_id
		) );

		return (bool) $world_permission;
	}

	/**
	 * Get all groups (workspaces) a user belongs to
	 *
	 * @param int $user_id User ID.
	 * @return array Array of group IDs.
	 */
	private function get_user_groups( $user_id ) {
		global $wpdb;

		$table_workspace_access = $wpdb->prefix . 'ns_storage_workspace_access';

		$groups = $wpdb->get_col( $wpdb->prepare(
			"SELECT workspace_id FROM {$table_workspace_access} WHERE user_id = %d",
			$user_id
		) );

		return array_map( 'intval', $groups );
	}

	/**
	 * Get parent groups for a given group (workspace hierarchy)
	 *
	 * @param int $group_id Group ID.
	 * @return array Array of parent group IDs.
	 */
	private function get_parent_groups( $group_id ) {
		global $wpdb;

		$table_workspaces = $wpdb->prefix . 'ns_storage_workspaces';
		$parents = array();
		$current_id = $group_id;

		// Walk up the hierarchy
		while ( $current_id ) {
			$parent_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT parent_id FROM {$table_workspaces} WHERE id = %d",
				$current_id
			) );

			if ( $parent_id ) {
				$parents[] = (int) $parent_id;
				$current_id = $parent_id;
			} else {
				break;
			}
		}

		return $parents;
	}

	/**
	 * Get all permissions for a file
	 *
	 * @param int $file_id File ID.
	 * @return array Permissions grouped by type.
	 */
	public function get_file_permissions( $file_id ) {
		global $wpdb;

		$table_permissions = $wpdb->prefix . 'ns_storage_permissions';

		$permissions = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table_permissions} WHERE file_id = %d ORDER BY permission_type",
			$file_id
		) );

		$grouped = array(
			'owner'  => null,
			'groups' => array(),
			'world'  => null,
		);

		foreach ( $permissions as $perm ) {
			if ( 'owner' === $perm->permission_type ) {
				$grouped['owner'] = $perm;
			} elseif ( 'group' === $perm->permission_type ) {
				$grouped['groups'][] = $perm;
			} elseif ( 'world' === $perm->permission_type ) {
				$grouped['world'] = $perm;
			}
		}

		return $grouped;
	}

	/**
	 * Set default permissions when file is created
	 *
	 * @param int $file_id File ID.
	 * @param int $creator_id Creator user ID.
	 * @param int $workspace_id Workspace ID (optional).
	 * @return bool True on success.
	 */
	public function set_default_permissions( $file_id, $creator_id, $workspace_id = null ) {
		// Owner: RWX
		$this->set_owner_permissions( $file_id, $creator_id, true, true, true );

		// If workspace is set, give that workspace group permissions
		if ( $workspace_id ) {
			$this->add_group_permissions( $file_id, $workspace_id, true, false, true, false );
		}

		// World: no access by default
		$this->set_world_permissions( $file_id, false, false, false );

		return true;
	}

	/**
	 * Copy permissions from one file to another
	 *
	 * @param int $source_file_id Source file ID.
	 * @param int $target_file_id Target file ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function copy_permissions( $source_file_id, $target_file_id ) {
		$permissions = $this->get_file_permissions( $source_file_id );

		// Copy owner
		if ( $permissions['owner'] ) {
			$this->set_owner_permissions(
				$target_file_id,
				$permissions['owner']->owner_id,
				$permissions['owner']->can_read,
				$permissions['owner']->can_write,
				$permissions['owner']->can_execute
			);
		}

		// Copy groups
		foreach ( $permissions['groups'] as $group ) {
			$this->add_group_permissions(
				$target_file_id,
				$group->group_id,
				$group->can_read,
				$group->can_write,
				$group->can_execute,
				$group->inherit_to_children
			);
		}

		// Copy world
		if ( $permissions['world'] ) {
			$this->set_world_permissions(
				$target_file_id,
				$permissions['world']->can_read,
				$permissions['world']->can_write,
				$permissions['world']->can_execute
			);
		}

		return true;
	}

	/**
	 * Filter callback for file access check
	 *
	 * @param bool $can_access Current access status.
	 * @param int  $file_id File ID.
	 * @param int  $user_id User ID.
	 * @return bool
	 */
	public function check_file_access( $can_access, $file_id, $user_id ) {
		// If already denied, respect that
		if ( ! $can_access ) {
			return false;
		}

		// Check our permissions
		return $this->can_access_file( $file_id, $user_id, 'read' );
	}

	/**
	 * Get permission summary as string (e.g., "rwx r-x r--")
	 *
	 * @param int $file_id File ID.
	 * @return string Permission string.
	 */
	public function get_permission_string( $file_id ) {
		$permissions = $this->get_file_permissions( $file_id );

		$owner_str = '---';
		if ( $permissions['owner'] ) {
			$owner_str = ( $permissions['owner']->can_read ? 'r' : '-' ) .
			             ( $permissions['owner']->can_write ? 'w' : '-' ) .
			             ( $permissions['owner']->can_execute ? 'x' : '-' );
		}

		// For groups, show the most permissive
		$group_str = '---';
		$max_read = $max_write = $max_execute = false;
		foreach ( $permissions['groups'] as $group ) {
			if ( $group->can_read ) {
				$max_read = true;
			}
			if ( $group->can_write ) {
				$max_write = true;
			}
			if ( $group->can_execute ) {
				$max_execute = true;
			}
		}
		if ( ! empty( $permissions['groups'] ) ) {
			$group_str = ( $max_read ? 'r' : '-' ) .
			             ( $max_write ? 'w' : '-' ) .
			             ( $max_execute ? 'x' : '-' );
		}

		$world_str = '---';
		if ( $permissions['world'] ) {
			$world_str = ( $permissions['world']->can_read ? 'r' : '-' ) .
			             ( $permissions['world']->can_write ? 'w' : '-' ) .
			             ( $permissions['world']->can_execute ? 'x' : '-' );
		}

		return $owner_str . ' ' . $group_str . ' ' . $world_str;
	}

	/**
	 * Bulk set permissions for multiple groups
	 *
	 * @param int   $file_id File ID.
	 * @param array $group_permissions Array of ['group_id' => int, 'read' => bool, 'write' => bool, 'execute' => bool].
	 * @return array Results for each group.
	 */
	public function bulk_set_group_permissions( $file_id, $group_permissions ) {
		$results = array();

		foreach ( $group_permissions as $perm ) {
			$group_id = $perm['group_id'];
			$result = $this->add_group_permissions(
				$file_id,
				$group_id,
				$perm['read'] ?? false,
				$perm['write'] ?? false,
				$perm['execute'] ?? false,
				$perm['inherit'] ?? false
			);

			$results[ $group_id ] = $result;
		}

		return $results;
	}
}
