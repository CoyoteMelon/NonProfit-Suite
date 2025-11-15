<?php
/**
 * CPA Dashboard (PRO) View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$access_records = NonprofitSuite_CPA_Dashboard::get_access_records( array( 'status' => 'active' ) );
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'CPA Portal', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<p class="ns-text-muted">
		<?php esc_html_e( 'Manage secure CPA access, share financial documents, and collaborate with your accounting firm.', 'nonprofitsuite' ); ?>
	</p>

	<!-- Summary Stats -->
	<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Active CPA Access', 'nonprofitsuite' ); ?></h4>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: #2563eb;">
				<?php echo ! empty( $access_records ) ? count( $access_records ) : 0; ?>
			</p>
		</div>

		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Files Shared', 'nonprofitsuite' ); ?></h4>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: #10b981;">
				0
			</p>
		</div>
	</div>

	<!-- Quick Actions -->
	<div class="ns-card" style="margin-bottom: 20px;">
		<h2 class="ns-card-title"><?php esc_html_e( 'Quick Actions', 'nonprofitsuite' ); ?></h2>
		<div style="display: flex; gap: 10px; flex-wrap: wrap;">
			<button class="ns-button ns-button-primary" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
				+ <?php esc_html_e( 'Grant CPA Access', 'nonprofitsuite' ); ?>
			</button>
			<button class="ns-button" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
				<?php esc_html_e( 'Share Files', 'nonprofitsuite' ); ?>
			</button>
			<button class="ns-button" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
				<?php esc_html_e( 'Access Log', 'nonprofitsuite' ); ?>
			</button>
		</div>
	</div>

	<!-- Active CPA Access -->
	<div class="ns-card">
		<h2 class="ns-card-title"><?php esc_html_e( 'Active CPA Access', 'nonprofitsuite' ); ?></h2>

		<?php if ( ! empty( $access_records ) && ! is_wp_error( $access_records ) ) : ?>
			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Firm', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Contact', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Email', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Access Level', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Granted Date', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Expiration', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $access_records as $access ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $access->firm_name ); ?></strong></td>
							<td><?php echo esc_html( $access->contact_name ); ?></td>
							<td><?php echo esc_html( $access->contact_email ); ?></td>
							<td><?php echo esc_html( ucfirst( $access->access_level ) ); ?></td>
							<td><?php echo esc_html( date( 'M j, Y', strtotime( $access->granted_date ) ) ); ?></td>
							<td>
								<?php if ( $access->expiration_date ) : ?>
									<?php
									$is_expiring = strtotime( $access->expiration_date ) < strtotime( '+30 days' );
									?>
									<span class="<?php echo $is_expiring ? 'ns-text-danger' : ''; ?>">
										<?php echo esc_html( date( 'M j, Y', strtotime( $access->expiration_date ) ) ); ?>
									</span>
								<?php else : ?>
									<span class="ns-text-muted"><?php esc_html_e( 'No Expiration', 'nonprofitsuite' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<button class="ns-button ns-button-sm" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
									<?php esc_html_e( 'Manage', 'nonprofitsuite' ); ?>
								</button>
								<button class="ns-button ns-button-sm" onclick="if(confirm('<?php esc_attr_e( 'Revoke access?', 'nonprofitsuite' ); ?>')) alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
									<?php esc_html_e( 'Revoke', 'nonprofitsuite' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No active CPA access. Grant access to your accounting firm to enable secure document sharing.', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>
</div>
