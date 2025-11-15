<?php
/**
 * In-Kind Donations & Asset Management (PRO) View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$ytd_value = NonprofitSuite_InKind::calculate_annual_in_kind_value( date( 'Y' ) );
$recent_donations = NonprofitSuite_InKind::get_donations( array( 'limit' => 20 ) );
$donations_by_category = NonprofitSuite_InKind::get_donations_by_category( date( 'Y' ) );
$active_assets = NonprofitSuite_InKind::get_assets( array( 'status' => 'active', 'limit' => 50 ) );
$asset_summary = NonprofitSuite_InKind::get_asset_summary();

$total_asset_value = 0;
foreach ( $asset_summary as $category ) {
	$total_asset_value += $category->total_value;
}

$tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'donations';
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'In-Kind & Asset Management', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<p class="ns-text-muted">
		<?php esc_html_e( 'Track non-cash donations, manage physical assets, calculate fair market value, and maintain comprehensive inventory.', 'nonprofitsuite' ); ?>
	</p>

	<!-- Tab Navigation -->
	<div style="border-bottom: 2px solid #e0e0e0; margin-bottom: 20px;">
		<div style="display: flex; gap: 10px; margin-bottom: -2px;">
			<a href="?page=nonprofitsuite-in-kind&tab=donations"
			   class="ns-tab-button<?php echo $tab === 'donations' ? ' ns-tab-active' : ''; ?>"
			   style="padding: 10px 20px; text-decoration: none; border-bottom: 2px solid <?php echo $tab === 'donations' ? '#2563eb' : 'transparent'; ?>; color: <?php echo $tab === 'donations' ? '#2563eb' : '#666'; ?>; font-weight: 500;">
				<?php esc_html_e( 'In-Kind Donations', 'nonprofitsuite' ); ?>
			</a>
			<a href="?page=nonprofitsuite-in-kind&tab=assets"
			   class="ns-tab-button<?php echo $tab === 'assets' ? ' ns-tab-active' : ''; ?>"
			   style="padding: 10px 20px; text-decoration: none; border-bottom: 2px solid <?php echo $tab === 'assets' ? '#2563eb' : 'transparent'; ?>; color: <?php echo $tab === 'assets' ? '#2563eb' : '#666'; ?>; font-weight: 500;">
				<?php esc_html_e( 'Asset Inventory', 'nonprofitsuite' ); ?>
			</a>
		</div>
	</div>

	<?php if ( $tab === 'donations' ) : ?>
		<!-- In-Kind Donations Tab -->

		<!-- Summary Stats -->
		<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
			<div class="ns-card" style="text-align: center; padding: 20px;">
				<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'YTD In-Kind Value', 'nonprofitsuite' ); ?></h4>
				<p style="margin: 0; font-size: 36px; font-weight: 600; color: #10b981;">
					$<?php echo number_format( $ytd_value, 0 ); ?>
				</p>
			</div>

			<div class="ns-card" style="text-align: center; padding: 20px;">
				<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Items Received', 'nonprofitsuite' ); ?></h4>
				<p style="margin: 0; font-size: 36px; font-weight: 600; color: #2563eb;">
					<?php echo count( $recent_donations ); ?>
				</p>
			</div>

			<div class="ns-card" style="text-align: center; padding: 20px;">
				<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Pending Appraisal', 'nonprofitsuite' ); ?></h4>
				<p style="margin: 0; font-size: 36px; font-weight: 600; color: #f59e0b;">0</p>
				<p class="ns-text-sm ns-text-muted" style="margin: 10px 0 0 0;"><?php esc_html_e( '>$5,000 value', 'nonprofitsuite' ); ?></p>
			</div>
		</div>

		<!-- Recent Donations -->
		<div class="ns-card" style="margin-bottom: 20px;">
			<div class="ns-card-header">
				<h2 class="ns-card-title"><?php esc_html_e( 'Recent In-Kind Donations', 'nonprofitsuite' ); ?></h2>
				<button class="ns-button ns-button-primary" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
					+ <?php esc_html_e( 'Record Donation', 'nonprofitsuite' ); ?>
				</button>
			</div>

			<?php if ( ! empty( $recent_donations ) && ! is_wp_error( $recent_donations ) ) : ?>
				<table class="ns-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Category', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Description', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Value', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Condition', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Receipt', 'nonprofitsuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recent_donations as $donation ) : ?>
							<tr>
								<td><?php echo esc_html( date( 'M j, Y', strtotime( $donation->donation_date ) ) ); ?></td>
								<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $donation->category ) ) ); ?></td>
								<td>
									<?php echo esc_html( wp_trim_words( $donation->description, 10 ) ); ?>
									<?php if ( $donation->quantity > 1 ) : ?>
										<br><span class="ns-text-sm ns-text-muted"><?php echo esc_html( $donation->quantity . ' ' . $donation->unit ); ?></span>
									<?php endif; ?>
								</td>
								<td><strong>$<?php echo number_format( $donation->fair_market_value, 2 ); ?></strong></td>
								<td><?php echo $donation->condition_rating ? esc_html( ucfirst( $donation->condition_rating ) ) : '-'; ?></td>
								<td>
									<?php if ( $donation->tax_receipt_issued ) : ?>
										<span style="color: #10b981;">✓ <?php esc_html_e( 'Issued', 'nonprofitsuite' ); ?></span>
									<?php else : ?>
										<button class="ns-button ns-button-sm" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
											<?php esc_html_e( 'Generate', 'nonprofitsuite' ); ?>
										</button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No in-kind donations recorded yet.', 'nonprofitsuite' ); ?></p>
			<?php endif; ?>
		</div>

		<!-- By Category -->
		<?php if ( ! empty( $donations_by_category ) ) : ?>
			<div class="ns-card">
				<h2 class="ns-card-title"><?php esc_html_e( 'YTD by Category', 'nonprofitsuite' ); ?></h2>

				<table class="ns-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Category', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Items', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Total Value', 'nonprofitsuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $donations_by_category as $category ) : ?>
							<tr>
								<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $category->category ) ) ); ?></td>
								<td><?php echo absint( $category->count ); ?></td>
								<td><strong>$<?php echo number_format( $category->total_value, 2 ); ?></strong></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

	<?php else : ?>
		<!-- Asset Inventory Tab -->

		<!-- Asset Summary -->
		<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
			<div class="ns-card" style="text-align: center; padding: 20px;">
				<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Total Asset Value', 'nonprofitsuite' ); ?></h4>
				<p style="margin: 0; font-size: 36px; font-weight: 600; color: #10b981;">
					$<?php echo number_format( $total_asset_value, 0 ); ?>
				</p>
			</div>

			<div class="ns-card" style="text-align: center; padding: 20px;">
				<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Active Assets', 'nonprofitsuite' ); ?></h4>
				<p style="margin: 0; font-size: 36px; font-weight: 600; color: #2563eb;">
					<?php echo count( $active_assets ); ?>
				</p>
			</div>

			<div class="ns-card" style="text-align: center; padding: 20px;">
				<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Annual Depreciation', 'nonprofitsuite' ); ?></h4>
				<p style="margin: 0; font-size: 36px; font-weight: 600; color: #6b7280;">
					$0
				</p>
			</div>
		</div>

		<!-- Asset Inventory -->
		<div class="ns-card">
			<div class="ns-card-header">
				<h2 class="ns-card-title"><?php esc_html_e( 'Asset Inventory', 'nonprofitsuite' ); ?></h2>
				<button class="ns-button ns-button-primary" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
					+ <?php esc_html_e( 'Add Asset', 'nonprofitsuite' ); ?>
				</button>
			</div>

			<?php if ( ! empty( $active_assets ) && ! is_wp_error( $active_assets ) ) : ?>
				<table class="ns-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Asset Name', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Location', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Current Value', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Condition', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Assigned To', 'nonprofitsuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $active_assets as $asset ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $asset->asset_name ); ?></strong>
									<?php if ( $asset->serial_number ) : ?>
										<br><span class="ns-text-sm ns-text-muted">S/N: <?php echo esc_html( $asset->serial_number ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $asset->asset_type ) ) ); ?></td>
								<td><?php echo $asset->location ? esc_html( $asset->location ) : '<span class="ns-text-muted">-</span>'; ?></td>
								<td>
									<?php if ( $asset->current_value ) : ?>
										<strong>$<?php echo number_format( $asset->current_value, 2 ); ?></strong>
									<?php else : ?>
										<span class="ns-text-muted">-</span>
									<?php endif; ?>
								</td>
								<td><?php echo $asset->condition_rating ? esc_html( ucfirst( $asset->condition_rating ) ) : '-'; ?></td>
								<td><?php echo $asset->assigned_to ? esc_html( $asset->assigned_to ) : '<span class="ns-text-muted">-</span>'; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No assets in inventory yet.', 'nonprofitsuite' ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
