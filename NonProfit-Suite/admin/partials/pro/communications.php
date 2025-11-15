<?php
/**
 * Communications & Outreach (PRO) View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$campaigns = NonprofitSuite_Communications::get_campaigns( array( 'limit' => 50 ) );
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Communications & Outreach', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<div class="ns-card">
		<div class="ns-card-header">
			<h2 class="ns-card-title"><?php esc_html_e( 'Email Campaigns', 'nonprofitsuite' ); ?></h2>
			<button class="ns-button ns-button-primary" onclick="alert('Create campaign feature coming soon');">
				<?php esc_html_e( 'Create Campaign', 'nonprofitsuite' ); ?>
			</button>
		</div>

		<?php if ( ! empty( $campaigns ) && ! is_wp_error( $campaigns ) ) : ?>
			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Campaign', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Subject', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Recipients', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Send Date', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Sent', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Open Rate', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $campaigns as $campaign ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $campaign->campaign_name ); ?></strong></td>
							<td><?php echo esc_html( $campaign->subject ); ?></td>
							<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $campaign->recipient_list ) ) ); ?></td>
							<td>
								<?php
								if ( $campaign->send_date ) {
									echo esc_html( date( 'M j, Y g:i A', strtotime( $campaign->send_date ) ) );
								} else {
									esc_html_e( 'Not scheduled', 'nonprofitsuite' );
								}
								?>
							</td>
							<td><?php echo esc_html( $campaign->sent_count ); ?></td>
							<td>
								<?php if ( $campaign->status === 'sent' && $campaign->sent_count > 0 ) : ?>
									<?php echo number_format( $campaign->open_rate, 1 ); ?>%
								<?php else : ?>
									<span class="ns-text-muted">-</span>
								<?php endif; ?>
							</td>
							<td><?php echo NonprofitSuite_Utilities::get_status_badge( $campaign->status ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No email campaigns found. Create your first campaign to reach out to your community!', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="ns-card" style="margin-top: 20px;">
		<div class="ns-card-header">
			<h2 class="ns-card-title"><?php esc_html_e( 'Quick Stats', 'nonprofitsuite' ); ?></h2>
		</div>
		<div class="ns-stats-grid">
			<div class="ns-stat-box">
				<div class="ns-stat-label"><?php esc_html_e( 'Total Campaigns', 'nonprofitsuite' ); ?></div>
				<div class="ns-stat-value"><?php echo count( $campaigns ); ?></div>
			</div>
			<div class="ns-stat-box">
				<div class="ns-stat-label"><?php esc_html_e( 'Sent Campaigns', 'nonprofitsuite' ); ?></div>
				<div class="ns-stat-value">
					<?php
					$sent_count = 0;
					foreach ( $campaigns as $campaign ) {
						if ( $campaign->status === 'sent' ) {
							$sent_count++;
						}
					}
					echo $sent_count;
					?>
				</div>
			</div>
			<div class="ns-stat-box">
				<div class="ns-stat-label"><?php esc_html_e( 'Draft Campaigns', 'nonprofitsuite' ); ?></div>
				<div class="ns-stat-value">
					<?php
					$draft_count = 0;
					foreach ( $campaigns as $campaign ) {
						if ( $campaign->status === 'draft' ) {
							$draft_count++;
						}
					}
					echo $draft_count;
					?>
				</div>
			</div>
			<div class="ns-stat-box">
				<div class="ns-stat-label"><?php esc_html_e( 'Scheduled Campaigns', 'nonprofitsuite' ); ?></div>
				<div class="ns-stat-value">
					<?php
					$scheduled_count = 0;
					foreach ( $campaigns as $campaign ) {
						if ( $campaign->status === 'scheduled' ) {
							$scheduled_count++;
						}
					}
					echo $scheduled_count;
					?>
				</div>
			</div>
		</div>
	</div>
</div>
