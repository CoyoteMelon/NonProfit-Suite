<?php
/**
 * Public Documents Admin View
 *
 * Manage public document shares and analytics.
 *
 * @package NonprofitSuite
 * @subpackage Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get organization ID
$organization_id = 1;

// Get all documents (this would integrate with Phase 2 Document Storage)
global $wpdb;
$documents_table = $wpdb->prefix . 'ns_documents';
$documents = $wpdb->get_results(
	$wpdb->prepare( "SELECT * FROM {$documents_table} WHERE organization_id = %d ORDER BY created_at DESC LIMIT 50", $organization_id ),
	ARRAY_A
);

?>

<div class="wrap ns-public-documents">
	<h1><?php esc_html_e( 'Public Documents', 'nonprofitsuite' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Create public share links for documents with access controls, analytics, and security features.', 'nonprofitsuite' ); ?>
	</p>

	<div class="public-docs-tabs">
		<button class="tab-button active" data-tab="document-list">
			<?php esc_html_e( 'Documents', 'nonprofitsuite' ); ?>
		</button>
		<button class="tab-button" data-tab="shares-list">
			<?php esc_html_e( 'Active Shares', 'nonprofitsuite' ); ?>
		</button>
		<button class="tab-button" data-tab="analytics">
			<?php esc_html_e( 'Analytics', 'nonprofitsuite' ); ?>
		</button>
	</div>

	<!-- Documents List Tab -->
	<div class="tab-content" id="document-list" style="display: block;">
		<h2><?php esc_html_e( 'Your Documents', 'nonprofitsuite' ); ?></h2>

		<?php if ( empty( $documents ) ) : ?>
			<p><?php esc_html_e( 'No documents found. Upload documents in Document Storage first.', 'nonprofitsuite' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Document Name', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Size', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Shares', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Views', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $documents as $doc ) : ?>
						<tr data-document-id="<?php echo esc_attr( $doc['id'] ); ?>">
							<td><strong><?php echo esc_html( $doc['document_name'] ); ?></strong></td>
							<td><?php echo esc_html( strtoupper( $doc['file_type'] ?? 'pdf' ) ); ?></td>
							<td><?php echo esc_html( size_format( $doc['file_size'] ?? 0 ) ); ?></td>
							<td class="share-count">0</td>
							<td class="view-count">0</td>
							<td>
								<button class="button button-small btn-create-share" data-document-id="<?php echo esc_attr( $doc['id'] ); ?>">
									<?php esc_html_e( 'Create Share', 'nonprofitsuite' ); ?>
								</button>
								<button class="button button-small btn-view-stats" data-document-id="<?php echo esc_attr( $doc['id'] ); ?>">
									<?php esc_html_e( 'View Stats', 'nonprofitsuite' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<!-- Active Shares Tab -->
	<div class="tab-content" id="shares-list" style="display: none;">
		<h2><?php esc_html_e( 'Active Share Links', 'nonprofitsuite' ); ?></h2>
		<div id="shares-table-container">
			<p><?php esc_html_e( 'Loading shares...', 'nonprofitsuite' ); ?></p>
		</div>
	</div>

	<!-- Analytics Tab -->
	<div class="tab-content" id="analytics" style="display: none;">
		<h2><?php esc_html_e( 'Document Analytics', 'nonprofitsuite' ); ?></h2>

		<div class="analytics-filters">
			<label>
				<?php esc_html_e( 'Period:', 'nonprofitsuite' ); ?>
				<select id="analytics-period">
					<option value="today"><?php esc_html_e( 'Today', 'nonprofitsuite' ); ?></option>
					<option value="week"><?php esc_html_e( 'Last 7 Days', 'nonprofitsuite' ); ?></option>
					<option value="month"><?php esc_html_e( 'Last 30 Days', 'nonprofitsuite' ); ?></option>
					<option value="all" selected><?php esc_html_e( 'All Time', 'nonprofitsuite' ); ?></option>
				</select>
			</label>
		</div>

		<div class="analytics-cards">
			<div class="analytics-card">
				<h3><?php esc_html_e( 'Total Views', 'nonprofitsuite' ); ?></h3>
				<p class="stat-number" id="total-views">0</p>
			</div>
			<div class="analytics-card">
				<h3><?php esc_html_e( 'Total Downloads', 'nonprofitsuite' ); ?></h3>
				<p class="stat-number" id="total-downloads">0</p>
			</div>
			<div class="analytics-card">
				<h3><?php esc_html_e( 'Unique Visitors', 'nonprofitsuite' ); ?></h3>
				<p class="stat-number" id="unique-visitors">0</p>
			</div>
			<div class="analytics-card">
				<h3><?php esc_html_e( 'Active Shares', 'nonprofitsuite' ); ?></h3>
				<p class="stat-number" id="active-shares">0</p>
			</div>
		</div>
	</div>
</div>

<!-- Create Share Modal -->
<div id="create-share-modal" class="ns-modal" style="display: none;">
	<div class="ns-modal-content">
		<span class="ns-modal-close">&times;</span>
		<h2><?php esc_html_e( 'Create Public Share Link', 'nonprofitsuite' ); ?></h2>

		<form id="create-share-form">
			<input type="hidden" id="share-document-id" name="document_id">

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="share-name"><?php esc_html_e( 'Share Name', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<input type="text" id="share-name" name="share_name" class="regular-text"
							placeholder="<?php esc_attr_e( 'e.g., Annual Report 2024 - Public', 'nonprofitsuite' ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="share-type"><?php esc_html_e( 'Share Type', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<select id="share-type" name="share_type">
							<option value="public"><?php esc_html_e( 'Public (anyone with link)', 'nonprofitsuite' ); ?></option>
							<option value="password"><?php esc_html_e( 'Password Protected', 'nonprofitsuite' ); ?></option>
							<option value="expiring"><?php esc_html_e( 'Expiring Link', 'nonprofitsuite' ); ?></option>
							<option value="limited"><?php esc_html_e( 'Limited Downloads', 'nonprofitsuite' ); ?></option>
						</select>
					</td>
				</tr>
				<tr class="password-row" style="display: none;">
					<th scope="row">
						<label for="share-password"><?php esc_html_e( 'Password', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<input type="text" id="share-password" name="password" class="regular-text">
					</td>
				</tr>
				<tr class="expiry-row" style="display: none;">
					<th scope="row">
						<label for="expires-at"><?php esc_html_e( 'Expires At', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<input type="datetime-local" id="expires-at" name="expires_at">
					</td>
				</tr>
				<tr class="downloads-row" style="display: none;">
					<th scope="row">
						<label for="max-downloads"><?php esc_html_e( 'Max Downloads', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<input type="number" id="max-downloads" name="max_downloads" min="1" value="100">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Permissions', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="permissions[view]" checked>
							<?php esc_html_e( 'View', 'nonprofitsuite' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="permissions[download]" checked>
							<?php esc_html_e( 'Download', 'nonprofitsuite' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="permissions[print]" checked>
							<?php esc_html_e( 'Print', 'nonprofitsuite' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Additional Options', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="require_email">
							<?php esc_html_e( 'Require email address', 'nonprofitsuite' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="require_tos">
							<?php esc_html_e( 'Require terms of service acceptance', 'nonprofitsuite' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="watermark-text"><?php esc_html_e( 'Watermark Text', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<input type="text" id="watermark-text" name="watermark_text" class="regular-text"
							placeholder="<?php esc_attr_e( 'Optional watermark text', 'nonprofitsuite' ); ?>">
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Create Share Link', 'nonprofitsuite' ); ?>
				</button>
				<button type="button" class="button cancel-modal">
					<?php esc_html_e( 'Cancel', 'nonprofitsuite' ); ?>
				</button>
			</p>

			<div class="share-created-message" style="display: none;">
				<h3><?php esc_html_e( 'Share Link Created!', 'nonprofitsuite' ); ?></h3>
				<p>
					<strong><?php esc_html_e( 'Share URL:', 'nonprofitsuite' ); ?></strong><br>
					<input type="text" id="created-share-url" class="regular-text" readonly>
					<button type="button" class="button btn-copy-url">
						<?php esc_html_e( 'Copy', 'nonprofitsuite' ); ?>
					</button>
				</p>
			</div>
		</form>
	</div>
</div>

<style>
.public-docs-tabs {
	margin: 20px 0;
	border-bottom: 1px solid #ccd0d4;
}

.tab-button {
	background: none;
	border: none;
	padding: 10px 20px;
	cursor: pointer;
	font-size: 14px;
	border-bottom: 2px solid transparent;
	margin-bottom: -1px;
}

.tab-button.active {
	border-bottom-color: #2271b1;
	font-weight: 700;
}

.tab-content {
	background: #fff;
	border: 1px solid #ccd0d4;
	padding: 30px;
	margin-top: 20px;
}

.analytics-cards {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 20px;
	margin-top: 20px;
}

.analytics-card {
	background: #f0f0f1;
	padding: 20px;
	border-radius: 4px;
	text-align: center;
}

.analytics-card h3 {
	margin: 0 0 10px 0;
	font-size: 14px;
	color: #646970;
}

.stat-number {
	font-size: 32px;
	font-weight: 700;
	margin: 0;
	color: #2271b1;
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
	max-width: 700px;
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
	// Tab switching
	$('.tab-button').on('click', function() {
		const tab = $(this).data('tab');
		$('.tab-button').removeClass('active');
		$(this).addClass('active');
		$('.tab-content').hide();
		$('#' + tab).show();
	});

	// Create share button
	$('.btn-create-share').on('click', function() {
		const documentId = $(this).data('document-id');
		$('#share-document-id').val(documentId);
		$('#create-share-modal').show();
	});

	// Close modal
	$('.ns-modal-close, .cancel-modal').on('click', function() {
		$('#create-share-modal').hide();
		$('#create-share-form')[0].reset();
		$('.share-created-message').hide();
	});

	// Share type change
	$('#share-type').on('change', function() {
		const type = $(this).val();
		$('.password-row, .expiry-row, .downloads-row').hide();

		if (type === 'password') {
			$('.password-row').show();
		} else if (type === 'expiring') {
			$('.expiry-row').show();
		} else if (type === 'limited') {
			$('.downloads-row').show();
		}
	});

	// Create share form submit
	$('#create-share-form').on('submit', function(e) {
		e.preventDefault();

		const formData = new FormData(this);
		formData.append('action', 'ns_create_document_share');
		formData.append('nonce', nsPublicDocs.nonce);
		formData.append('organization_id', 1);

		$.ajax({
			url: nsPublicDocs.ajax_url,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function(response) {
				if (response.success) {
					$('#created-share-url').val(response.data.share_url);
					$('.share-created-message').show();
					alert(response.data.message);
				} else {
					alert(response.data.message);
				}
			}
		});
	});

	// Copy URL button
	$('.btn-copy-url').on('click', function() {
		const url = $('#created-share-url').val();
		navigator.clipboard.writeText(url).then(function() {
			alert('<?php esc_html_e( 'URL copied to clipboard!', 'nonprofitsuite' ); ?>');
		});
	});
});
</script>
