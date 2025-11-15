<?php
/**
 * Accounting Export Page
 *
 * @package NonprofitSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Export Accounting Data', 'nonprofitsuite' ); ?></h1>
	<p><?php esc_html_e( 'Export your accounting data to external systems. Perfect for sharing with your CPA or importing into accounting software.', 'nonprofitsuite' ); ?></p>

	<div class="ns-export-container">
		<div class="ns-export-formats">
			<h2><?php esc_html_e( 'Choose Export Format', 'nonprofitsuite' ); ?></h2>

			<?php foreach ( $adapters as $key => $adapter_class ) : ?>
				<?php
				$adapter = new $adapter_class();
				?>
				<div class="ns-format-card">
					<h3><?php echo esc_html( $adapter->get_format_name() ); ?></h3>
					<p class="ns-format-description">
						<?php
						switch ( $key ) {
							case 'quickbooks':
								esc_html_e( 'QuickBooks Desktop IIF format - Import directly into QuickBooks', 'nonprofitsuite' );
								break;
							case 'xero':
								esc_html_e( 'Xero CSV format - Import into Xero online accounting', 'nonprofitsuite' );
								break;
							case 'wave':
								esc_html_e( 'Wave CSV format - Import into Wave accounting (free)', 'nonprofitsuite' );
								break;
						}
						?>
					</p>

					<form class="ns-export-form" data-adapter="<?php echo esc_attr( $key ); ?>">
						<h4><?php esc_html_e( 'What to Export', 'nonprofitsuite' ); ?></h4>

						<label>
							<input type="radio" name="export_type_<?php echo esc_attr( $key ); ?>" value="accounts" required>
							<?php esc_html_e( 'Chart of Accounts', 'nonprofitsuite' ); ?>
						</label><br>

						<label>
							<input type="radio" name="export_type_<?php echo esc_attr( $key ); ?>" value="transactions" required>
							<?php esc_html_e( 'Payment Transactions', 'nonprofitsuite' ); ?>
						</label><br>

						<label>
							<input type="radio" name="export_type_<?php echo esc_attr( $key ); ?>" value="entries" required>
							<?php esc_html_e( 'Journal Entries', 'nonprofitsuite' ); ?>
						</label>

						<div class="ns-date-range" style="margin-top: 15px;">
							<label>
								<?php esc_html_e( 'From Date:', 'nonprofitsuite' ); ?>
								<input type="date" name="date_from" class="regular-text">
							</label>
							<label>
								<?php esc_html_e( 'To Date:', 'nonprofitsuite' ); ?>
								<input type="date" name="date_to" class="regular-text">
							</label>
						</div>

						<button type="submit" class="button button-primary" style="margin-top: 15px;">
							<?php esc_html_e( 'Download Export', 'nonprofitsuite' ); ?>
						</button>
					</form>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="ns-export-help">
			<h2><?php esc_html_e( 'Export Guide', 'nonprofitsuite' ); ?></h2>

			<div class="ns-help-section">
				<h3><?php esc_html_e( 'QuickBooks Desktop', 'nonprofitsuite' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Export as QuickBooks IIF format', 'nonprofitsuite' ); ?></li>
					<li><?php esc_html_e( 'Open QuickBooks Desktop', 'nonprofitsuite' ); ?></li>
					<li><?php esc_html_e( 'Go to File > Utilities > Import > IIF Files', 'nonprofitsuite' ); ?></li>
					<li><?php esc_html_e( 'Select your downloaded file', 'nonprofitsuite' ); ?></li>
				</ol>
			</div>

			<div class="ns-help-section">
				<h3><?php esc_html_e( 'Xero', 'nonprofitsuite' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Export as Xero CSV format', 'nonprofitsuite' ); ?></li>
					<li><?php esc_html_e( 'Log into Xero', 'nonprofitsuite' ); ?></li>
					<li><?php esc_html_e( 'Go to Accounting > Advanced > Import', 'nonprofitsuite' ); ?></li>
					<li><?php esc_html_e( 'Select your downloaded CSV file', 'nonprofitsuite' ); ?></li>
				</ol>
			</div>

			<div class="ns-help-section">
				<h3><?php esc_html_e( 'Wave', 'nonprofitsuite' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Export as Wave CSV format', 'nonprofitsuite' ); ?></li>
					<li><?php esc_html_e( 'Log into Wave', 'nonprofitsuite' ); ?></li>
					<li><?php esc_html_e( 'Go to Accounting > Transactions', 'nonprofitsuite' ); ?></li>
					<li><?php esc_html_e( 'Click "Upload" and select your CSV file', 'nonprofitsuite' ); ?></li>
				</ol>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	$('.ns-export-form').on('submit', function(e) {
		e.preventDefault();

		const $form = $(this);
		const adapter = $form.data('adapter');
		const exportType = $form.find('input[name^="export_type"]:checked').val();

		if (!exportType) {
			alert('Please select what to export');
			return;
		}

		const data = {
			action: 'ns_export_accounting',
			nonce: '<?php echo wp_create_nonce( 'ns_accounting' ); ?>',
			adapter: adapter,
			export_type: exportType,
			date_from: $form.find('[name="date_from"]').val(),
			date_to: $form.find('[name="date_to"]').val()
		};

		const $button = $form.find('button[type="submit"]');
		$button.prop('disabled', true).text('Generating...');

		$.post(ajaxurl, data, function(response) {
			if (response.success) {
				// Convert base64 to blob and download
				const content = atob(response.data.content);
				const blob = new Blob([content], { type: response.data.mime_type });
				const url = window.URL.createObjectURL(blob);
				const a = document.createElement('a');
				a.href = url;
				a.download = response.data.filename;
				document.body.appendChild(a);
				a.click();
				window.URL.revokeObjectURL(url);
				document.body.removeChild(a);

				alert('Export downloaded successfully!');
			} else {
				alert('Error: ' + response.data.message);
			}

			$button.prop('disabled', false).text('Download Export');
		});
	});
});
</script>

<style>
.ns-export-container {
	display: grid;
	grid-template-columns: 2fr 1fr;
	gap: 30px;
	margin-top: 20px;
}

.ns-format-card {
	background: #fff;
	padding: 20px;
	margin-bottom: 20px;
	border: 1px solid #ccd0d4;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.ns-format-card h3 {
	margin-top: 0;
	color: #0073aa;
}

.ns-format-description {
	color: #666;
	margin-bottom: 15px;
}

.ns-export-help {
	background: #fff;
	padding: 20px;
	border: 1px solid #ccd0d4;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.ns-help-section {
	margin-bottom: 25px;
}

.ns-help-section h3 {
	margin-top: 0;
	border-bottom: 1px solid #ddd;
	padding-bottom: 10px;
}

@media (max-width: 900px) {
	.ns-export-container {
		grid-template-columns: 1fr;
	}
}
</style>
