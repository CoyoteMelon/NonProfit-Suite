<?php
/**
 * Document Shortcodes
 *
 * Public-facing shortcodes for embedding documents.
 *
 * @package NonprofitSuite
 * @subpackage Public
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_Document_Shortcodes {

	/**
	 * Manager instance.
	 *
	 * @var NS_Public_Document_Manager
	 */
	private $manager;

	/**
	 * Constructor.
	 */
	public function __construct() {
		require_once NS_PLUGIN_DIR . 'includes/helpers/class-public-document-manager.php';
		$this->manager = NS_Public_Document_Manager::get_instance();

		$this->register_shortcodes();
	}

	/**
	 * Register shortcodes.
	 */
	private function register_shortcodes() {
		add_shortcode( 'ns_document_portal', array( $this, 'document_portal_shortcode' ) );
		add_shortcode( 'ns_document_list', array( $this, 'document_list_shortcode' ) );
		add_shortcode( 'ns_document_category', array( $this, 'document_category_shortcode' ) );
		add_shortcode( 'ns_document_embed', array( $this, 'document_embed_shortcode' ) );
	}

	/**
	 * Document portal shortcode.
	 *
	 * [ns_document_portal]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function document_portal_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'organization_id' => 1,
			'show_search'     => 'yes',
			'show_categories' => 'yes',
			'per_page'        => 20,
		), $atts );

		ob_start();
		include NS_PLUGIN_DIR . 'public/views/document-portal.php';
		return ob_get_clean();
	}

	/**
	 * Document list shortcode.
	 *
	 * [ns_document_list category="annual-reports" limit="10"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function document_list_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'category'     => '',
			'limit'        => 10,
			'show_download' => 'yes',
			'layout'       => 'list',
		), $atts );

		global $wpdb;

		// Get documents
		$table = $wpdb->prefix . 'ns_documents';
		$query = $wpdb->prepare( "SELECT * FROM {$table} WHERE is_public = 1 ORDER BY created_at DESC LIMIT %d", $atts['limit'] );
		$documents = $wpdb->get_results( $query, ARRAY_A );

		ob_start();
		?>
		<div class="ns-document-list layout-<?php echo esc_attr( $atts['layout'] ); ?>">
			<?php if ( empty( $documents ) ) : ?>
				<p><?php esc_html_e( 'No documents found.', 'nonprofitsuite' ); ?></p>
			<?php else : ?>
				<?php foreach ( $documents as $doc ) : ?>
					<div class="ns-document-item">
						<div class="document-icon">
							<span class="dashicons dashicons-media-document"></span>
						</div>
						<div class="document-info">
							<h3><?php echo esc_html( $doc['document_name'] ); ?></h3>
							<p class="document-meta">
								<?php echo esc_html( strtoupper( $doc['file_type'] ?? 'pdf' ) ); ?> •
								<?php echo esc_html( size_format( $doc['file_size'] ?? 0 ) ); ?> •
								<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $doc['created_at'] ) ) ); ?>
							</p>
							<?php if ( ! empty( $doc['document_description'] ) ) : ?>
								<p class="document-description"><?php echo esc_html( $doc['document_description'] ); ?></p>
							<?php endif; ?>
						</div>
						<?php if ( $atts['show_download'] === 'yes' ) : ?>
							<div class="document-actions">
								<a href="#" class="button" data-document-id="<?php echo esc_attr( $doc['id'] ); ?>">
									<?php esc_html_e( 'Download', 'nonprofitsuite' ); ?>
								</a>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Document category shortcode.
	 *
	 * [ns_document_category slug="annual-reports"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function document_category_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'slug'  => '',
			'limit' => 20,
		), $atts );

		if ( empty( $atts['slug'] ) ) {
			return '<p>' . esc_html__( 'Category slug is required', 'nonprofitsuite' ) . '</p>';
		}

		// Get category
		global $wpdb;
		$cat_table = $wpdb->prefix . 'ns_document_categories';
		$category = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$cat_table} WHERE category_slug = %s AND is_public = 1", $atts['slug'] ),
			ARRAY_A
		);

		if ( ! $category ) {
			return '<p>' . esc_html__( 'Category not found', 'nonprofitsuite' ) . '</p>';
		}

		ob_start();
		?>
		<div class="ns-document-category">
			<h2><?php echo esc_html( $category['category_name'] ); ?></h2>
			<?php if ( ! empty( $category['category_description'] ) ) : ?>
				<p class="category-description"><?php echo esc_html( $category['category_description'] ); ?></p>
			<?php endif; ?>

			<?php
			// Use document list shortcode to display category documents
			echo do_shortcode( '[ns_document_list category="' . esc_attr( $atts['slug'] ) . '" limit="' . esc_attr( $atts['limit'] ) . '"]' );
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Document embed shortcode.
	 *
	 * [ns_document_embed id="123" width="800" height="600"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function document_embed_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'id'     => 0,
			'width'  => '100%',
			'height' => '600px',
		), $atts );

		if ( empty( $atts['id'] ) ) {
			return '<p>' . esc_html__( 'Document ID is required', 'nonprofitsuite' ) . '</p>';
		}

		// Get document
		global $wpdb;
		$table = $wpdb->prefix . 'ns_documents';
		$document = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $atts['id'] ),
			ARRAY_A
		);

		if ( ! $document ) {
			return '<p>' . esc_html__( 'Document not found', 'nonprofitsuite' ) . '</p>';
		}

		ob_start();
		?>
		<div class="ns-document-embed" style="width: <?php echo esc_attr( $atts['width'] ); ?>; height: <?php echo esc_attr( $atts['height'] ); ?>;">
			<iframe
				src="<?php echo esc_url( $document['file_url'] ?? '' ); ?>"
				width="100%"
				height="100%"
				frameborder="0"
				title="<?php echo esc_attr( $document['document_name'] ); ?>">
			</iframe>
		</div>
		<?php
		return ob_get_clean();
	}
}

// Initialize
new NS_Document_Shortcodes();
