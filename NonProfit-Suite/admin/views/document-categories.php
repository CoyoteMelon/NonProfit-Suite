<?php
/**
 * Document Categories Admin View
 *
 * Manage public document categories.
 *
 * @package NonprofitSuite
 * @subpackage Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get organization ID
$organization_id = 1;

// Get manager
require_once NS_PLUGIN_DIR . 'includes/helpers/class-public-document-manager.php';
$manager = NS_Public_Document_Manager::get_instance();

$categories = $manager->get_categories( $organization_id );

?>

<div class="wrap ns-document-categories">
	<h1><?php esc_html_e( 'Document Categories', 'nonprofitsuite' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Organize public documents into categories for easier navigation.', 'nonprofitsuite' ); ?>
	</p>

	<button class="button button-primary" id="btn-add-category">
		<?php esc_html_e( 'Add New Category', 'nonprofitsuite' ); ?>
	</button>

	<table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Category Name', 'nonprofitsuite' ); ?></th>
				<th><?php esc_html_e( 'Slug', 'nonprofitsuite' ); ?></th>
				<th><?php esc_html_e( 'Public', 'nonprofitsuite' ); ?></th>
				<th><?php esc_html_e( 'Order', 'nonprofitsuite' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
			</tr>
		</thead>
		<tbody id="categories-list">
			<?php if ( empty( $categories ) ) : ?>
				<tr>
					<td colspan="5"><?php esc_html_e( 'No categories found. Create your first category!', 'nonprofitsuite' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $categories as $category ) : ?>
					<tr data-category-id="<?php echo esc_attr( $category['id'] ); ?>">
						<td>
							<strong><?php echo esc_html( $category['category_name'] ); ?></strong>
							<?php if ( $category['parent_category_id'] ) : ?>
								<span class="subcategory-indicator"><?php esc_html_e( '(Subcategory)', 'nonprofitsuite' ); ?></span>
							<?php endif; ?>
						</td>
						<td><code><?php echo esc_html( $category['category_slug'] ); ?></code></td>
						<td>
							<?php if ( $category['is_public'] ) : ?>
								<span class="dashicons dashicons-yes" style="color: #00a32a;"></span>
							<?php else : ?>
								<span class="dashicons dashicons-no" style="color: #d63638;"></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $category['display_order'] ); ?></td>
						<td>
							<button class="button button-small btn-edit-category" data-category-id="<?php echo esc_attr( $category['id'] ); ?>">
								<?php esc_html_e( 'Edit', 'nonprofitsuite' ); ?>
							</button>
							<button class="button button-small button-link-delete btn-delete-category" data-category-id="<?php echo esc_attr( $category['id'] ); ?>">
								<?php esc_html_e( 'Delete', 'nonprofitsuite' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>

<!-- Category Modal -->
<div id="category-modal" class="ns-modal" style="display: none;">
	<div class="ns-modal-content">
		<span class="ns-modal-close">&times;</span>
		<h2 id="modal-title"><?php esc_html_e( 'Add Category', 'nonprofitsuite' ); ?></h2>

		<form id="category-form">
			<input type="hidden" id="category-id" name="category_id">

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="category-name"><?php esc_html_e( 'Category Name', 'nonprofitsuite' ); ?> *</label>
					</th>
					<td>
						<input type="text" id="category-name" name="category_name" class="regular-text" required>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="category-slug"><?php esc_html_e( 'Slug', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<input type="text" id="category-slug" name="category_slug" class="regular-text">
						<p class="description"><?php esc_html_e( 'Leave blank to auto-generate from name', 'nonprofitsuite' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="category-description"><?php esc_html_e( 'Description', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<textarea id="category-description" name="category_description" rows="3" class="large-text"></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="display-order"><?php esc_html_e( 'Display Order', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<input type="number" id="display-order" name="display_order" min="0" value="0">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="is-public"><?php esc_html_e( 'Public', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="is-public" name="is_public" checked>
							<?php esc_html_e( 'Show in public document portal', 'nonprofitsuite' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="category-color"><?php esc_html_e( 'Color', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<input type="color" id="category-color" name="color" value="#2271b1">
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Category', 'nonprofitsuite' ); ?>
				</button>
				<button type="button" class="button cancel-modal">
					<?php esc_html_e( 'Cancel', 'nonprofitsuite' ); ?>
				</button>
			</p>
		</form>
	</div>
</div>

<style>
.subcategory-indicator {
	font-size: 11px;
	color: #646970;
}

.ns-modal {
	display: none;
	position: fixed;
	z-index: 100000;
	left: 0;
	top: 0;
	width: 100%;
	height: 100%;
	background-color: rgba(0,0,0,0.5);
}

.ns-modal-content {
	background-color: #fff;
	margin: 5% auto;
	padding: 30px;
	border: 1px solid #ccd0d4;
	width: 80%;
	max-width: 600px;
	max-height: 80vh;
	overflow-y: auto;
}

.ns-modal-close {
	color: #646970;
	float: right;
	font-size: 28px;
	font-weight: 700;
	cursor: pointer;
}

.ns-modal-close:hover {
	color: #000;
}
</style>

<script>
jQuery(document).ready(function($) {
	// Add category button
	$('#btn-add-category').on('click', function() {
		$('#modal-title').text('<?php esc_html_e( 'Add Category', 'nonprofitsuite' ); ?>');
		$('#category-form')[0].reset();
		$('#category-id').val('');
		$('#category-modal').show();
	});

	// Edit category button
	$(document).on('click', '.btn-edit-category', function() {
		const categoryId = $(this).data('category-id');
		// Load category data and populate form
		$('#modal-title').text('<?php esc_html_e( 'Edit Category', 'nonprofitsuite' ); ?>');
		$('#category-id').val(categoryId);
		$('#category-modal').show();
	});

	// Delete category button
	$(document).on('click', '.btn-delete-category', function() {
		if (!confirm('<?php esc_html_e( 'Are you sure you want to delete this category?', 'nonprofitsuite' ); ?>')) {
			return;
		}

		const categoryId = $(this).data('category-id');

		$.ajax({
			url: nsPublicDocs.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_delete_document_category',
				nonce: nsPublicDocs.nonce,
				category_id: categoryId
			},
			success: function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.data.message);
				}
			}
		});
	});

	// Close modal
	$('.ns-modal-close, .cancel-modal').on('click', function() {
		$('#category-modal').hide();
	});

	// Save category form
	$('#category-form').on('submit', function(e) {
		e.preventDefault();

		const formData = $(this).serialize();

		$.ajax({
			url: nsPublicDocs.ajax_url,
			type: 'POST',
			data: formData + '&action=ns_save_document_category&nonce=' + nsPublicDocs.nonce + '&organization_id=1',
			success: function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.data.message);
				}
			}
		});
	});
});
</script>
