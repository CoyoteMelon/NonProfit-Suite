<?php
/**
 * Email Campaigns Admin Interface
 *
 * @package NonprofitSuite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Get campaigns
$campaigns = isset( $campaigns ) ? $campaigns : NonprofitSuite_Email_Campaigns::get_campaigns();
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Email Campaigns', 'nonprofitsuite' ); ?></h1>

	<div class="ns-card">
		<div class="ns-card-header">
			<h2 class="ns-card-title"><?php esc_html_e( 'Your Email Campaigns', 'nonprofitsuite' ); ?></h2>
			<button class="ns-button ns-button-primary"><?php esc_html_e( 'Create Campaign', 'nonprofitsuite' ); ?></button>
		</div>

		<?php if ( is_array( $campaigns ) && ! empty( $campaigns ) ) : ?>
			<div class="ns-table-container">
				<table class="ns-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Campaign Name', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Subject', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Recipients', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Sent', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Opens', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Clicks', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $campaigns as $campaign ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $campaign->campaign_name ); ?></strong></td>
								<td><?php echo esc_html( $campaign->subject_line ); ?></td>
								<td><?php echo esc_html( number_format( $campaign->total_recipients ) ); ?></td>
								<td><?php echo esc_html( number_format( $campaign->opened_count ?? 0 ) ); ?></td>
								<td>
									<?php
									$open_rate = $campaign->total_recipients > 0 ? ( $campaign->opened_count / $campaign->total_recipients * 100 ) : 0;
									echo esc_html( number_format( $open_rate, 1 ) . '%' );
									?>
								</td>
								<td>
									<?php
									$click_rate = $campaign->total_recipients > 0 ? ( $campaign->clicked_count / $campaign->total_recipients * 100 ) : 0;
									echo esc_html( number_format( $click_rate, 1 ) . '%' );
									?>
								</td>
								<td><?php echo NonprofitSuite_Utilities::get_status_badge( $campaign->status ); ?></td>
								<td>
									<button class="ns-button ns-button-sm"><?php esc_html_e( 'View', 'nonprofitsuite' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<div style="text-align: center; padding: 60px;">
				<p><?php esc_html_e( 'No email campaigns yet. Create your first campaign to engage with your community!', 'nonprofitsuite' ); ?></p>
				<button class="ns-button ns-button-primary"><?php esc_html_e( 'Create First Campaign', 'nonprofitsuite' ); ?></button>
			</div>
		<?php endif; ?>
	</div>

	<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
		<div class="ns-card">
			<h3><?php esc_html_e( 'Campaign Templates', 'nonprofitsuite' ); ?></h3>
			<p><?php esc_html_e( 'Use pre-built templates for common nonprofit communications.', 'nonprofitsuite' ); ?></p>
			<button class="ns-button ns-button-outline" style="margin-top: 10px;"><?php esc_html_e( 'Browse Templates', 'nonprofitsuite' ); ?></button>
		</div>

		<div class="ns-card">
			<h3><?php esc_html_e( 'Email Lists', 'nonprofitsuite' ); ?></h3>
			<p><?php esc_html_e( 'Manage your email lists and subscriber segments.', 'nonprofitsuite' ); ?></p>
			<button class="ns-button ns-button-outline" style="margin-top: 10px;"><?php esc_html_e( 'Manage Lists', 'nonprofitsuite' ); ?></button>
		</div>

		<div class="ns-card">
			<h3><?php esc_html_e( 'Analytics', 'nonprofitsuite' ); ?></h3>
			<p><?php esc_html_e( 'View detailed analytics for your email campaigns.', 'nonprofitsuite' ); ?></p>
			<button class="ns-button ns-button-outline" style="margin-top: 10px;"><?php esc_html_e( 'View Analytics', 'nonprofitsuite' ); ?></button>
		</div>
	</div>
</div>
