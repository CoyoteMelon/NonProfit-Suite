<?php
/**
 * Fundraising/Campaigns (PRO) View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$campaigns = NonprofitSuite_Fundraising::get_campaigns( 'active' );
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Fundraising Campaigns', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<div class="ns-card">
		<div class="ns-card-header">
			<h2 class="ns-card-title"><?php esc_html_e( 'Active Campaigns', 'nonprofitsuite' ); ?></h2>
			<button class="ns-button ns-button-primary" onclick="alert('Add campaign feature coming soon');">
				<?php esc_html_e( 'New Campaign', 'nonprofitsuite' ); ?>
			</button>
		</div>

		<?php if ( ! empty( $campaigns ) && ! is_wp_error( $campaigns ) ) : ?>
			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Campaign', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Goal', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Raised', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Progress', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Dates', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $campaigns as $campaign ) :
						$progress = NonprofitSuite_Fundraising::get_campaign_progress( $campaign->id );
					?>
						<tr>
							<td><strong><?php echo esc_html( $campaign->campaign_name ); ?></strong></td>
							<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $campaign->campaign_type ) ) ); ?></td>
							<td>$<?php echo number_format( $campaign->goal_amount, 2 ); ?></td>
							<td>$<?php echo number_format( $campaign->amount_raised, 2 ); ?></td>
							<td>
								<div class="ns-progress-bar">
									<div class="ns-progress-fill" style="width: <?php echo min( 100, $progress['percentage'] ); ?>%"></div>
								</div>
								<span class="ns-text-sm"><?php echo $progress['percentage']; ?>%</span>
							</td>
							<td>
								<?php if ( $campaign->start_date ) : ?>
									<?php echo esc_html( date( 'M j, Y', strtotime( $campaign->start_date ) ) ); ?>
									<?php if ( $campaign->end_date ) : ?>
										- <?php echo esc_html( date( 'M j, Y', strtotime( $campaign->end_date ) ) ); ?>
									<?php endif; ?>
								<?php else : ?>
									<?php esc_html_e( 'Ongoing', 'nonprofitsuite' ); ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No campaigns found. Create your first fundraising campaign to get started!', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>
</div>
