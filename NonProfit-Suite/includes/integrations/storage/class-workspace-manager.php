<?php
/**
 * Workspace Manager
 *
 * Manages isolated workspaces for groups, boards, and committees.
 *
 * @package NonprofitSuite
 * @subpackage Integrations\Storage
 * @since 1.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class NonprofitSuite_Workspace_Manager
 *
 * Provides isolated document workspaces for organizational groups with
 * role-based access control and shared spaces.
 *
 * Features:
 * - Group/board/committee workspaces
 * - Published documents (org-wide visibility)
 * - Joint committee shared spaces
 * - Role-based permissions
 * - Workspace inheritance
 *
 * @since 1.0.0
 */
class NonprofitSuite_Workspace_Manager {

    /**
     * Singleton instance
     *
     * @var NonprofitSuite_Workspace_Manager
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return NonprofitSuite_Workspace_Manager
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Hook into file access checks
        add_filter('ns_storage_can_access_file', array($this, 'check_workspace_access'), 10, 3);
    }

    /**
     * Create a new workspace
     *
     * @param array $args Workspace arguments.
     * @return int|WP_Error Workspace ID or error.
     */
    public function create_workspace($args) {
        global $wpdb;

        $defaults = array(
            'name' => '',
            'slug' => '',
            'type' => 'group', // group, board, committee, joint-committee, organization
            'parent_id' => null,
            'is_published' => false,
            'description' => '',
            'created_by' => get_current_user_id(),
        );

        $args = wp_parse_args($args, $defaults);

        // Validate
        if (empty($args['name'])) {
            return new WP_Error('missing_name', __('Workspace name is required.', 'nonprofitsuite'));
        }

        // Generate slug if not provided
        if (empty($args['slug'])) {
            $args['slug'] = sanitize_title($args['name']);
        }

        // Check for duplicate slug
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ns_storage_workspaces WHERE slug = %s",
            $args['slug']
        ));

        if ($existing) {
            return new WP_Error('duplicate_slug', __('Workspace with this slug already exists.', 'nonprofitsuite'));
        }

        // Insert workspace
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'ns_storage_workspaces',
            array(
                'name' => $args['name'],
                'slug' => $args['slug'],
                'type' => $args['type'],
                'parent_id' => $args['parent_id'],
                'is_published' => $args['is_published'] ? 1 : 0,
                'description' => $args['description'],
                'created_by' => $args['created_by'],
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s')
        );

        if (!$inserted) {
            return new WP_Error('insert_failed', __('Failed to create workspace.', 'nonprofitsuite'));
        }

        $workspace_id = $wpdb->insert_id;

        // Grant admin access to creator
        $this->grant_access($workspace_id, $args['created_by'], 'admin');

        do_action('ns_workspace_created', $workspace_id, $args);

        return $workspace_id;
    }

    /**
     * Get workspace by ID or slug
     *
     * @param int|string $workspace ID or slug.
     * @return object|false Workspace object or false.
     */
    public function get_workspace($workspace) {
        global $wpdb;

        if (is_numeric($workspace)) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ns_storage_workspaces WHERE id = %d",
                $workspace
            ));
        } else {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ns_storage_workspaces WHERE slug = %s",
                $workspace
            ));
        }
    }

    /**
     * Grant access to a workspace
     *
     * @param int    $workspace_id Workspace ID.
     * @param int    $user_id      User ID.
     * @param string $role         Role (admin, editor, viewer).
     * @return bool|WP_Error True on success, error on failure.
     */
    public function grant_access($workspace_id, $user_id, $role = 'viewer') {
        global $wpdb;

        $valid_roles = array('admin', 'editor', 'viewer');
        if (!in_array($role, $valid_roles)) {
            return new WP_Error('invalid_role', __('Invalid role specified.', 'nonprofitsuite'));
        }

        // Check if access already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ns_storage_workspace_access
            WHERE workspace_id = %d AND user_id = %d",
            $workspace_id,
            $user_id
        ));

        if ($existing) {
            // Update existing
            $updated = $wpdb->update(
                $wpdb->prefix . 'ns_storage_workspace_access',
                array('role' => $role, 'updated_at' => current_time('mysql')),
                array('id' => $existing),
                array('%s', '%s'),
                array('%d')
            );

            return $updated !== false;
        } else {
            // Insert new
            $inserted = $wpdb->insert(
                $wpdb->prefix . 'ns_storage_workspace_access',
                array(
                    'workspace_id' => $workspace_id,
                    'user_id' => $user_id,
                    'role' => $role,
                    'granted_by' => get_current_user_id(),
                    'granted_at' => current_time('mysql'),
                ),
                array('%d', '%d', '%s', '%d', '%s')
            );

            return $inserted !== false;
        }
    }

    /**
     * Revoke access from a workspace
     *
     * @param int $workspace_id Workspace ID.
     * @param int $user_id      User ID.
     * @return bool True on success, false on failure.
     */
    public function revoke_access($workspace_id, $user_id) {
        global $wpdb;

        $deleted = $wpdb->delete(
            $wpdb->prefix . 'ns_storage_workspace_access',
            array(
                'workspace_id' => $workspace_id,
                'user_id' => $user_id,
            ),
            array('%d', '%d')
        );

        return $deleted !== false;
    }

    /**
     * Check if user has access to workspace
     *
     * @param int    $workspace_id Workspace ID.
     * @param int    $user_id      User ID (defaults to current user).
     * @param string $min_role     Minimum role required.
     * @return bool True if has access.
     */
    public function has_access($workspace_id, $user_id = null, $min_role = 'viewer') {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        // Admins always have access
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        global $wpdb;

        $workspace = $this->get_workspace($workspace_id);
        if (!$workspace) {
            return false;
        }

        // Published workspaces are viewable by all
        if ($workspace->is_published && $min_role === 'viewer') {
            return true;
        }

        // Check direct access
        $access = $wpdb->get_row($wpdb->prepare(
            "SELECT role FROM {$wpdb->prefix}ns_storage_workspace_access
            WHERE workspace_id = %d AND user_id = %d",
            $workspace_id,
            $user_id
        ));

        if (!$access) {
            return false;
        }

        // Check role hierarchy
        $role_hierarchy = array('viewer' => 1, 'editor' => 2, 'admin' => 3);
        $user_level = $role_hierarchy[$access->role] ?? 0;
        $required_level = $role_hierarchy[$min_role] ?? 0;

        return $user_level >= $required_level;
    }

    /**
     * Get user's workspaces
     *
     * @param int   $user_id User ID (defaults to current user).
     * @param array $args    Query arguments.
     * @return array Workspaces.
     */
    public function get_user_workspaces($user_id = null, $args = array()) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        global $wpdb;

        $defaults = array(
            'include_published' => true,
            'type' => null,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array();
        $params = array($user_id);

        if ($args['type']) {
            $where[] = 'w.type = %s';
            $params[] = $args['type'];
        }

        $where_clause = !empty($where) ? 'AND ' . implode(' AND ', $where) : '';

        // Get workspaces with direct access
        $query = "SELECT DISTINCT w.*,
                  a.role as user_role
                  FROM {$wpdb->prefix}ns_storage_workspaces w
                  INNER JOIN {$wpdb->prefix}ns_storage_workspace_access a ON w.id = a.workspace_id
                  WHERE a.user_id = %d
                  {$where_clause}";

        // Include published workspaces if requested
        if ($args['include_published']) {
            $query .= " UNION
                       SELECT DISTINCT w.*,
                       'viewer' as user_role
                       FROM {$wpdb->prefix}ns_storage_workspaces w
                       WHERE w.is_published = 1
                       {$where_clause}";
        }

        $query .= " ORDER BY name ASC";

        return $wpdb->get_results($wpdb->prepare($query, $params));
    }

    /**
     * Create joint committee workspace
     *
     * @param string $name        Workspace name.
     * @param array  $committee_ids Array of committee workspace IDs.
     * @param array  $args        Additional arguments.
     * @return int|WP_Error Workspace ID or error.
     */
    public function create_joint_workspace($name, $committee_ids, $args = array()) {
        if (count($committee_ids) < 2) {
            return new WP_Error('insufficient_committees', __('At least 2 committees required for joint workspace.', 'nonprofitsuite'));
        }

        $args = wp_parse_args($args, array(
            'name' => $name,
            'type' => 'joint-committee',
            'description' => sprintf(__('Joint workspace for %d committees', 'nonprofitsuite'), count($committee_ids)),
        ));

        $workspace_id = $this->create_workspace($args);

        if (is_wp_error($workspace_id)) {
            return $workspace_id;
        }

        // Link committees to joint workspace
        global $wpdb;

        foreach ($committee_ids as $committee_id) {
            $wpdb->insert(
                $wpdb->prefix . 'ns_storage_workspace_links',
                array(
                    'workspace_id' => $workspace_id,
                    'linked_workspace_id' => $committee_id,
                    'relationship' => 'joint-member',
                    'created_at' => current_time('mysql'),
                ),
                array('%d', '%d', '%s', '%s')
            );

            // Grant access to all committee members
            $committee_members = $this->get_workspace_members($committee_id);
            foreach ($committee_members as $member) {
                $this->grant_access($workspace_id, $member->user_id, $member->role);
            }
        }

        return $workspace_id;
    }

    /**
     * Get workspace members
     *
     * @param int $workspace_id Workspace ID.
     * @return array Members.
     */
    public function get_workspace_members($workspace_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, u.display_name, u.user_email
            FROM {$wpdb->prefix}ns_storage_workspace_access a
            INNER JOIN {$wpdb->users} u ON a.user_id = u.ID
            WHERE a.workspace_id = %d
            ORDER BY u.display_name ASC",
            $workspace_id
        ));
    }

    /**
     * Check workspace access for file operation (filter callback)
     *
     * @param bool   $can_access Current access status.
     * @param string $file_id    File UUID.
     * @param int    $user_id    User ID.
     * @return bool True if has access.
     */
    public function check_workspace_access($can_access, $file_id, $user_id) {
        // If already denied, respect that
        if (!$can_access) {
            return false;
        }

        global $wpdb;

        // Get file's workspace
        $workspace_id = $wpdb->get_var($wpdb->prepare(
            "SELECT workspace_id FROM {$wpdb->prefix}ns_storage_files WHERE file_uuid = %s",
            $file_id
        ));

        if (!$workspace_id) {
            // No workspace means org-wide file, allow if already permitted
            return $can_access;
        }

        // Check workspace access
        return $this->has_access($workspace_id, $user_id, 'viewer');
    }

    /**
     * Get workspace statistics
     *
     * @param int $workspace_id Workspace ID.
     * @return array Statistics.
     */
    public function get_workspace_stats($workspace_id) {
        global $wpdb;

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total_files,
                SUM(file_size) as total_size,
                SUM(CASE WHEN is_public = 1 THEN 1 ELSE 0 END) as public_files,
                SUM(CASE WHEN is_public = 0 THEN 1 ELSE 0 END) as private_files
            FROM {$wpdb->prefix}ns_storage_files
            WHERE workspace_id = %d AND deleted_at IS NULL",
            $workspace_id
        ));

        $member_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ns_storage_workspace_access WHERE workspace_id = %d",
            $workspace_id
        ));

        return array(
            'total_files' => (int) $stats->total_files,
            'total_size' => (int) $stats->total_size,
            'public_files' => (int) $stats->public_files,
            'private_files' => (int) $stats->private_files,
            'member_count' => (int) $member_count,
        );
    }

    /**
     * Delete workspace
     *
     * @param int  $workspace_id Workspace ID.
     * @param bool $delete_files Whether to delete files.
     * @return bool|WP_Error True on success, error on failure.
     */
    public function delete_workspace($workspace_id, $delete_files = false) {
        global $wpdb;

        $workspace = $this->get_workspace($workspace_id);
        if (!$workspace) {
            return new WP_Error('workspace_not_found', __('Workspace not found.', 'nonprofitsuite'));
        }

        if ($delete_files) {
            // Delete all files in workspace
            $file_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT file_uuid FROM {$wpdb->prefix}ns_storage_files WHERE workspace_id = %d",
                $workspace_id
            ));

            $orchestrator = NonprofitSuite_Storage_Orchestrator::get_instance();
            foreach ($file_ids as $file_id) {
                $orchestrator->delete_file($file_id, false); // Hard delete
            }
        } else {
            // Move files to org-wide (null workspace)
            $wpdb->update(
                $wpdb->prefix . 'ns_storage_files',
                array('workspace_id' => null),
                array('workspace_id' => $workspace_id),
                array('%d'),
                array('%d')
            );
        }

        // Delete workspace access
        $wpdb->delete(
            $wpdb->prefix . 'ns_storage_workspace_access',
            array('workspace_id' => $workspace_id),
            array('%d')
        );

        // Delete workspace links
        $wpdb->delete(
            $wpdb->prefix . 'ns_storage_workspace_links',
            array('workspace_id' => $workspace_id),
            array('%d')
        );

        $wpdb->delete(
            $wpdb->prefix . 'ns_storage_workspace_links',
            array('linked_workspace_id' => $workspace_id),
            array('%d')
        );

        // Delete workspace
        $deleted = $wpdb->delete(
            $wpdb->prefix . 'ns_storage_workspaces',
            array('id' => $workspace_id),
            array('%d')
        );

        return $deleted !== false;
    }
}
