<?php
/**
 * Public Document Portal
 *
 * Frontend document library with search and categories.
 *
 * @package NonprofitSuite
 * @subpackage Public/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$organization_id = $atts['organization_id'] ?? 1;

// Get categories
require_once NS_PLUGIN_DIR . 'includes/helpers/class-public-document-manager.php';
$manager = NS_Public_Document_Manager::get_instance();
$categories = $manager->get_categories( $organization_id, true );

// Get documents
global $wpdb;
$documents_table = $wpdb->prefix . 'ns_documents';
$documents = $wpdb->get_results(
	$wpdb->prepare( "SELECT * FROM {$documents_table} WHERE organization_id = %d AND is_public = 1 ORDER BY created_at DESC", $organization_id ),
	ARRAY_A
);

?>

<div class="ns-document-portal">
	<?php if ( $atts['show_search'] === 'yes' ) : ?>
		<div class="portal-search">
			<input type="text" id="document-search" class="search-input" placeholder="<?php esc_attr_e( 'Search documents...', 'nonprofitsuite' ); ?>">
		</div>
	<?php endif; ?>

	<?php if ( $atts['show_categories'] === 'yes' && ! empty( $categories ) ) : ?>
		<div class="portal-categories">
			<h3><?php esc_html_e( 'Categories', 'nonprofitsuite' ); ?></h3>
			<ul class="category-list">
				<li><a href="#" class="category-filter active" data-category=""><?php esc_html_e( 'All Documents', 'nonprofitsuite' ); ?></a></li>
				<?php foreach ( $categories as $category ) : ?>
					<li>
						<a href="#" class="category-filter" data-category="<?php echo esc_attr( $category['category_slug'] ); ?>">
							<?php echo esc_html( $category['category_name'] ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<div class="portal-documents">
		<?php if ( empty( $documents ) ) : ?>
			<p><?php esc_html_e( 'No public documents available.', 'nonprofitsuite' ); ?></p>
		<?php else : ?>
			<div class="document-grid">
				<?php foreach ( $documents as $doc ) : ?>
					<div class="document-card" data-document-name="<?php echo esc_attr( strtolower( $doc['document_name'] ) ); ?>">
						<div class="card-header">
							<span class="file-icon dashicons dashicons-media-document"></span>
							<span class="file-type"><?php echo esc_html( strtoupper( $doc['file_type'] ?? 'pdf' ) ); ?></span>
						</div>
						<div class="card-body">
							<h4><?php echo esc_html( $doc['document_name'] ); ?></h4>
							<?php if ( ! empty( $doc['document_description'] ) ) : ?>
								<p class="document-desc"><?php echo esc_html( wp_trim_words( $doc['document_description'], 20 ) ); ?></p>
							<?php endif; ?>
							<p class="document-meta">
								<?php echo esc_html( size_format( $doc['file_size'] ?? 0 ) ); ?> •
								<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $doc['created_at'] ) ) ); ?>
							</p>
						</div>
						<div class="card-footer">
							<a href="<?php echo esc_url( $doc['file_url'] ?? '#' ); ?>" class="btn-view" target="_blank">
								<?php esc_html_e( 'View', 'nonprofitsuite' ); ?>
							</a>
							<a href="<?php echo esc_url( $doc['file_url'] ?? '#' ); ?>" class="btn-download" download>
								<?php esc_html_e( 'Download', 'nonprofitsuite' ); ?>
							</a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</div>

<style>
.ns-document-portal {
	max-width: 1200px;
	margin: 40px auto;
	padding: 0 20px;
}

.portal-search {
	margin-bottom: 30px;
}

.search-input {
	width: 100%;
	max-width: 500px;
	padding: 12px 20px;
	font-size: 16px;
	border: 2px solid #ddd;
	border-radius: 4px;
}

.portal-categories {
	margin-bottom: 30px;
	padding: 20px;
	background: #f9f9f9;
	border-radius: 4px;
}

.category-list {
	list-style: none;
	padding: 0;
	margin: 10px 0 0 0;
	display: flex;
	flex-wrap: wrap;
	gap: 10px;
}

.category-filter {
	display: inline-block;
	padding: 8px 16px;
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 20px;
	text-decoration: none;
	color: #333;
	transition: all 0.2s;
}

.category-filter:hover,
.category-filter.active {
	background: #2271b1;
	color: #fff;
	border-color: #2271b1;
}

.document-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
	gap: 20px;
}

.document-card {
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 8px;
	overflow: hidden;
	transition: box-shadow 0.2s;
}

.document-card:hover {
	box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.card-header {
	background: #f0f0f1;
	padding: 15px;
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.file-icon {
	font-size: 32px;
	color: #2271b1;
}

.file-type {
	font-size: 11px;
	font-weight: 700;
	color: #646970;
	background: #fff;
	padding: 4px 8px;
	border-radius: 3px;
}

.card-body {
	padding: 15px;
}

.card-body h4 {
	margin: 0 0 10px 0;
	font-size: 16px;
	line-height: 1.4;
}

.document-desc {
	font-size: 14px;
	color: #646970;
	margin: 10px 0;
}

.document-meta {
	font-size: 12px;
	color: #999;
	margin: 10px 0 0 0;
}

.card-footer {
	padding: 15px;
	border-top: 1px solid #f0f0f1;
	display: flex;
	gap: 10px;
}

.btn-view,
.btn-download {
	flex: 1;
	padding: 8px 16px;
	text-align: center;
	text-decoration: none;
	border-radius: 4px;
	font-size: 14px;
	transition: all 0.2s;
}

.btn-view {
	background: #f0f0f1;
	color: #333;
}

.btn-view:hover {
	background: #e0e0e1;
}

.btn-download {
	background: #2271b1;
	color: #fff;
}

.btn-download:hover {
	background: #135e96;
}
</style>

<script>
jQuery(document).ready(function($) {
	// Search functionality
	$('#document-search').on('keyup', function() {
		const query = $(this).val().toLowerCase();
		$('.document-card').each(function() {
			const name = $(this).data('document-name');
			if (name.includes(query)) {
				$(this).show();
			} else {
				$(this).hide();
			}
		});
	});

	// Category filtering
	$('.category-filter').on('click', function(e) {
		e.preventDefault();
		$('.category-filter').removeClass('active');
		$(this).addClass('active');

		const category = $(this).data('category');
		// Filter logic would go here
	});
});
</script>
