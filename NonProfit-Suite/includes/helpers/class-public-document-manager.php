<?php
/**
 * Public Document Manager
 *
 * Handles public document sharing, access control, and analytics.
 *
 * @package NonprofitSuite
 * @subpackage Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_Public_Document_Manager {

	/**
	 * Singleton instance.
	 *
	 * @var NS_Public_Document_Manager
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return NS_Public_Document_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {
		// Register public endpoints
		add_action( 'init', array( $this, 'register_public_endpoints' ) );
		add_action( 'template_redirect', array( $this, 'handle_public_document_access' ) );
	}

	/**
	 * Register public document endpoints.
	 */
	public function register_public_endpoints() {
		add_rewrite_rule(
			'^documents/share/([^/]+)/?$',
			'index.php?ns_document_share=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			'^documents/portal/?$',
			'index.php?ns_document_portal=1',
			'top'
		);

		add_rewrite_rule(
			'^documents/category/([^/]+)/?$',
			'index.php?ns_document_category=$matches[1]',
			'top'
		);
	}

	/**
	 * Handle public document access.
	 */
	public function handle_public_document_access() {
		global $wp_query;

		// Handle document share access
		if ( isset( $wp_query->query_vars['ns_document_share'] ) ) {
			$share_token = sanitize_text_field( $wp_query->query_vars['ns_document_share'] );
			$this->serve_shared_document( $share_token );
			exit;
		}
	}

	/**
	 * Create a public share for a document.
	 *
	 * @param array $args Share arguments.
	 * @return int|WP_Error Share ID on success, WP_Error on failure.
	 */
	public function create_share( $args ) {
		global $wpdb;

		$defaults = array(
			'document_id'           => 0,
			'organization_id'       => 0,
			'share_name'            => '',
			'share_type'            => 'public',
			'password'              => null,
			'expires_at'            => null,
			'max_downloads'         => null,
			'permissions'           => array( 'view' => true, 'download' => true, 'print' => true ),
			'require_email'         => false,
			'require_tos_acceptance' => false,
			'watermark_text'        => null,
			'created_by'            => get_current_user_id(),
		);

		$data = wp_parse_args( $args, $defaults );

		// Validate
		if ( empty( $data['document_id'] ) || empty( $data['organization_id'] ) ) {
			return new WP_Error( 'invalid_data', __( 'Document ID and Organization ID are required', 'nonprofitsuite' ) );
		}

		// Generate unique share token
		$share_token = $this->generate_share_token();

		// Hash password if provided
		$password_hash = null;
		if ( ! empty( $data['password'] ) ) {
			$password_hash = wp_hash_password( $data['password'] );
		}

		$table = $wpdb->prefix . 'ns_public_document_shares';

		$inserted = $wpdb->insert(
			$table,
			array(
				'document_id'            => $data['document_id'],
				'organization_id'        => $data['organization_id'],
				'share_token'            => $share_token,
				'share_name'             => $data['share_name'],
				'share_type'             => $data['share_type'],
				'password_hash'          => $password_hash,
				'expires_at'             => $data['expires_at'],
				'max_downloads'          => $data['max_downloads'],
				'permissions'            => wp_json_encode( $data['permissions'] ),
				'require_email'          => $data['require_email'],
				'require_tos_acceptance' => $data['require_tos_acceptance'],
				'watermark_text'         => $data['watermark_text'],
				'created_by'             => $data['created_by'],
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%d' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'db_error', __( 'Failed to create share', 'nonprofitsuite' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get share by token.
	 *
	 * @param string $share_token Share token.
	 * @return array|null Share data or null if not found.
	 */
	public function get_share_by_token( $share_token ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_public_document_shares';
		$share = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE share_token = %s AND is_active = 1", $share_token ),
			ARRAY_A
		);

		if ( $share ) {
			$share['permissions'] = json_decode( $share['permissions'], true );
		}

		return $share;
	}

	/**
	 * Get all shares for a document.
	 *
	 * @param int $document_id Document ID.
	 * @return array Shares.
	 */
	public function get_document_shares( $document_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_public_document_shares';
		$shares = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE document_id = %d ORDER BY created_at DESC",
				$document_id
			),
			ARRAY_A
		);

		foreach ( $shares as &$share ) {
			$share['permissions'] = json_decode( $share['permissions'], true );
		}

		return $shares;
	}

	/**
	 * Validate access to a shared document.
	 *
	 * @param string $share_token Share token.
	 * @param array  $credentials Credentials (password, email, etc.).
	 * @return array|WP_Error Share data on success, WP_Error on failure.
	 */
	public function validate_share_access( $share_token, $credentials = array() ) {
		$share = $this->get_share_by_token( $share_token );

		if ( ! $share ) {
			return new WP_Error( 'invalid_share', __( 'Share link not found or inactive', 'nonprofitsuite' ) );
		}

		// Check expiry
		if ( ! empty( $share['expires_at'] ) && strtotime( $share['expires_at'] ) < time() ) {
			return new WP_Error( 'expired', __( 'This share link has expired', 'nonprofitsuite' ) );
		}

		// Check download limit
		if ( ! empty( $share['max_downloads'] ) && $share['current_downloads'] >= $share['max_downloads'] ) {
			return new WP_Error( 'limit_reached', __( 'Download limit reached for this share', 'nonprofitsuite' ) );
		}

		// Check password
		if ( ! empty( $share['password_hash'] ) ) {
			if ( empty( $credentials['password'] ) ) {
				return new WP_Error( 'password_required', __( 'Password required', 'nonprofitsuite' ) );
			}

			if ( ! wp_check_password( $credentials['password'], $share['password_hash'] ) ) {
				return new WP_Error( 'invalid_password', __( 'Invalid password', 'nonprofitsuite' ) );
			}
		}

		// Check email requirement
		if ( $share['require_email'] && empty( $credentials['email'] ) ) {
			return new WP_Error( 'email_required', __( 'Email address required', 'nonprofitsuite' ) );
		}

		// Check TOS acceptance
		if ( $share['require_tos_acceptance'] && empty( $credentials['tos_accepted'] ) ) {
			return new WP_Error( 'tos_required', __( 'You must accept the terms of service', 'nonprofitsuite' ) );
		}

		return $share;
	}

	/**
	 * Log document access.
	 *
	 * @param array $args Access log arguments.
	 * @return int|false Log ID on success, false on failure.
	 */
	public function log_access( $args ) {
		global $wpdb;

		$defaults = array(
			'document_id'    => 0,
			'share_id'       => null,
			'organization_id' => 0,
			'access_type'    => 'view',
			'user_id'        => get_current_user_id(),
			'visitor_email'  => null,
			'accepted_tos'   => false,
		);

		$data = wp_parse_args( $args, $defaults );

		// Get visitor info with proper sanitization
		$ip_address = $this->get_client_ip();
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';
		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( $_SERVER['HTTP_REFERER'] ) : '';

		$table = $wpdb->prefix . 'ns_document_access_logs';

		$inserted = $wpdb->insert(
			$table,
			array(
				'document_id'    => $data['document_id'],
				'share_id'       => $data['share_id'],
				'organization_id' => $data['organization_id'],
				'access_type'    => $data['access_type'],
				'user_id'        => $data['user_id'] ?: null,
				'visitor_email'  => $data['visitor_email'],
				'ip_address'     => $ip_address,
				'user_agent'     => $user_agent,
				'referer_url'    => $referer,
				'accepted_tos'   => $data['accepted_tos'],
			),
			array( '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( $inserted ) {
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Increment share download count.
	 *
	 * @param int $share_id Share ID.
	 * @return bool True on success, false on failure.
	 */
	public function increment_download_count( $share_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_public_document_shares';

		return $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET current_downloads = current_downloads + 1 WHERE id = %d",
				$share_id
			)
		) !== false;
	}

	/**
	 * Serve a shared document.
	 *
	 * @param string $share_token Share token.
	 */
	private function serve_shared_document( $share_token ) {
		// Validate access
		$credentials = array();

		if ( isset( $_POST['password'] ) ) {
			$credentials['password'] = $_POST['password'];
		}

		if ( isset( $_POST['email'] ) ) {
			$credentials['email'] = sanitize_email( $_POST['email'] );
		}

		if ( isset( $_POST['tos_accepted'] ) ) {
			$credentials['tos_accepted'] = (bool) $_POST['tos_accepted'];
		}

		$share = $this->validate_share_access( $share_token, $credentials );

		if ( is_wp_error( $share ) ) {
			// Show access form with error
			$this->render_access_form( $share_token, $share->get_error_message() );
			return;
		}

		// If no credentials submitted yet and required, show form
		if ( ( ! empty( $share['password_hash'] ) && empty( $credentials['password'] ) ) ||
		     ( $share['require_email'] && empty( $credentials['email'] ) ) ||
		     ( $share['require_tos_acceptance'] && empty( $credentials['tos_accepted'] ) ) ) {
			$this->render_access_form( $share_token );
			return;
		}

		// Log access
		$this->log_access( array(
			'document_id'    => $share['document_id'],
			'share_id'       => $share['id'],
			'organization_id' => $share['organization_id'],
			'access_type'    => 'download',
			'visitor_email'  => $credentials['email'] ?? null,
			'accepted_tos'   => $credentials['tos_accepted'] ?? false,
		) );

		// Increment download count
		$this->increment_download_count( $share['id'] );

		// Serve document (this would integrate with the document storage manager)
		// For now, redirect to document URL or show download page
		$this->render_document_viewer( $share );
	}

	/**
	 * Render access form for protected documents.
	 *
	 * @param string $share_token Share token.
	 * @param string $error       Error message if any.
	 */
	private function render_access_form( $share_token, $error = '' ) {
		$share = $this->get_share_by_token( $share_token );

		// This would load a template file
		include NS_PLUGIN_DIR . 'public/views/document-access-form.php';
	}

	/**
	 * Render document viewer.
	 *
	 * @param array $share Share data.
	 */
	private function render_document_viewer( $share ) {
		// This would load a template file
		include NS_PLUGIN_DIR . 'public/views/document-viewer.php';
	}

	/**
	 * Generate unique share token.
	 *
	 * @return string Share token.
	 */
	private function generate_share_token() {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Get client IP address.
	 *
	 * @return string IP address.
	 */
	private function get_client_ip() {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( isset( $_SERVER[ $key ] ) ) {
				$ip = $_SERVER[ $key ];
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Get share URL.
	 *
	 * @param string $share_token Share token.
	 * @return string Share URL.
	 */
	public function get_share_url( $share_token ) {
		return home_url( '/documents/share/' . $share_token );
	}

	/**
	 * Get document access statistics.
	 *
	 * @param int    $document_id Document ID.
	 * @param string $period      Period (today, week, month, all).
	 * @return array Statistics.
	 */
	public function get_document_statistics( $document_id, $period = 'all' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_document_access_logs';

		// Validate period parameter against whitelist for security
		$allowed_periods = array( 'today', 'week', 'month', 'all' );
		if ( ! in_array( $period, $allowed_periods, true ) ) {
			$period = 'all';
		}

		// Build date filter based on validated period
		$date_filter = '';
		switch ( $period ) {
			case 'today':
				$date_filter = "AND DATE(accessed_at) = CURDATE()";
				break;
			case 'week':
				$date_filter = "AND accessed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
				break;
			case 'month':
				$date_filter = "AND accessed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
				break;
		}

		$stats = array(
			'total_views'     => 0,
			'total_downloads' => 0,
			'unique_visitors' => 0,
			'popular_countries' => array(),
		);

		// Total views
		$stats['total_views'] = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE document_id = %d AND access_type = 'view' {$date_filter}",
				$document_id
			)
		);

		// Total downloads
		$stats['total_downloads'] = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE document_id = %d AND access_type = 'download' {$date_filter}",
				$document_id
			)
		);

		// Unique visitors (by IP)
		$stats['unique_visitors'] = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT ip_address) FROM {$table} WHERE document_id = %d {$date_filter}",
				$document_id
			)
		);

		return $stats;
	}

	/**
	 * Create or update document category.
	 *
	 * @param array $args Category arguments.
	 * @return int|WP_Error Category ID on success, WP_Error on failure.
	 */
	public function save_category( $args ) {
		global $wpdb;

		$defaults = array(
			'id'                   => 0,
			'organization_id'      => 0,
			'category_name'        => '',
			'category_slug'        => '',
			'category_description' => '',
			'parent_category_id'   => null,
			'display_order'        => 0,
			'is_public'            => true,
			'icon'                 => null,
			'color'                => null,
		);

		$data = wp_parse_args( $args, $defaults );

		// Auto-generate slug if not provided
		if ( empty( $data['category_slug'] ) && ! empty( $data['category_name'] ) ) {
			$data['category_slug'] = sanitize_title( $data['category_name'] );
		}

		$table = $wpdb->prefix . 'ns_document_categories';

		if ( ! empty( $data['id'] ) ) {
			// Update existing category
			$updated = $wpdb->update(
				$table,
				array(
					'category_name'        => $data['category_name'],
					'category_slug'        => $data['category_slug'],
					'category_description' => $data['category_description'],
					'parent_category_id'   => $data['parent_category_id'],
					'display_order'        => $data['display_order'],
					'is_public'            => $data['is_public'],
					'icon'                 => $data['icon'],
					'color'                => $data['color'],
				),
				array( 'id' => $data['id'] ),
				array( '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' ),
				array( '%d' )
			);

			if ( false === $updated ) {
				return new WP_Error( 'db_error', __( 'Failed to update category', 'nonprofitsuite' ) );
			}

			return $data['id'];
		} else {
			// Create new category
			$inserted = $wpdb->insert(
				$table,
				array(
					'organization_id'      => $data['organization_id'],
					'category_name'        => $data['category_name'],
					'category_slug'        => $data['category_slug'],
					'category_description' => $data['category_description'],
					'parent_category_id'   => $data['parent_category_id'],
					'display_order'        => $data['display_order'],
					'is_public'            => $data['is_public'],
					'icon'                 => $data['icon'],
					'color'                => $data['color'],
				),
				array( '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
			);

			if ( false === $inserted ) {
				return new WP_Error( 'db_error', __( 'Failed to create category', 'nonprofitsuite' ) );
			}

			return $wpdb->insert_id;
		}
	}

	/**
	 * Get all categories for an organization.
	 *
	 * @param int  $organization_id Organization ID.
	 * @param bool $public_only     Get only public categories.
	 * @return array Categories.
	 */
	public function get_categories( $organization_id, $public_only = false ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_document_categories';

		$where = $wpdb->prepare( "organization_id = %d", $organization_id );

		if ( $public_only ) {
			$where .= " AND is_public = 1";
		}

		$categories = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE {$where} ORDER BY display_order ASC, category_name ASC",
			ARRAY_A
		);

		return $categories;
	}
}
