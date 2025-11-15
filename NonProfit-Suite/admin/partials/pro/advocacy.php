<?php
/**
 * Advocacy Module (PRO) View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$dashboard = NonprofitSuite_Advocacy::get_advocacy_dashboard();
$active_issues = NonprofitSuite_Advocacy::get_active_issues();
$all_issues = NonprofitSuite_Advocacy::get_issues( array( 'limit' => 50 ) );
$active_campaigns = NonprofitSuite_Advocacy::get_active_campaigns();
$all_campaigns = NonprofitSuite_Advocacy::get_campaigns( array( 'limit' => 30 ) );
$upcoming_decisions = NonprofitSuite_Advocacy::get_issues_by_decision_date( 60 );

$tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'issues';
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Advocacy', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<p class="ns-text-muted">
		<?php esc_html_e( 'Manage advocacy campaigns, track legislative issues, and mobilize supporters for action.', 'nonprofitsuite' ); ?>
	</p>

	<?php if ( ! empty( $upcoming_decisions ) && ! is_wp_error( $upcoming_decisions ) ) : ?>
		<div class="ns-alert ns-alert-warning">
			<strong><?php esc_html_e( 'Upcoming Decisions:', 'nonprofitsuite' ); ?></strong>
			<?php printf( _n( '%d issue has a decision within 60 days', '%d issues have decisions within 60 days', count( $upcoming_decisions ), 'nonprofitsuite' ), count( $upcoming_decisions ) ); ?>
		</div>
	<?php endif; ?>

	<!-- Dashboard Stats -->
	<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Active Issues', 'nonprofitsuite' ); ?></h4>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: #2563eb;">
				<?php echo absint( $dashboard['active_issues'] ); ?>
			</p>
		</div>

		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Active Campaigns', 'nonprofitsuite' ); ?></h4>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: #10b981;">
				<?php echo absint( $dashboard['active_campaigns'] ); ?>
			</p>
		</div>

		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Total Actions', 'nonprofitsuite' ); ?></h4>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: #f59e0b;">
				<?php echo absint( $dashboard['total_actions'] ); ?>
			</p>
		</div>

		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Victories', 'nonprofitsuite' ); ?></h4>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: #10b981;">
				<?php echo absint( $dashboard['victories'] ); ?>
			</p>
		</div>
	</div>

	<!-- Tab Navigation -->
	<div style="border-bottom: 2px solid #e0e0e0; margin-bottom: 20px;">
		<div style="display: flex; gap: 10px; margin-bottom: -2px;">
			<a href="?page=nonprofitsuite-advocacy&tab=issues"
			   class="ns-tab-button<?php echo $tab === 'issues' ? ' ns-tab-active' : ''; ?>"
			   style="padding: 10px 20px; text-decoration: none; border-bottom: 2px solid <?php echo $tab === 'issues' ? '#2563eb' : 'transparent'; ?>; color: <?php echo $tab === 'issues' ? '#2563eb' : '#666'; ?>; font-weight: 500;">
				<?php esc_html_e( 'Issues', 'nonprofitsuite' ); ?>
			</a>
			<a href="?page=nonprofitsuite-advocacy&tab=campaigns"
			   class="ns-tab-button<?php echo $tab === 'campaigns' ? ' ns-tab-active' : ''; ?>"
			   style="padding: 10px 20px; text-decoration: none; border-bottom: 2px solid <?php echo $tab === 'campaigns' ? '#2563eb' : 'transparent'; ?>; color: <?php echo $tab === 'campaigns' ? '#2563eb' : '#666'; ?>; font-weight: 500;">
				<?php esc_html_e( 'Campaigns', 'nonprofitsuite' ); ?>
			</a>
		</div>
	</div>

	<?php if ( $tab === 'issues' ) : ?>
		<!-- Issues Tab -->

		<?php if ( ! empty( $active_issues ) && ! is_wp_error( $active_issues ) ) : ?>
			<div class="ns-card" style="margin-bottom: 20px; background: #fef3c7;">
				<h2 class="ns-card-title" style="color: #92400e;">🚨 <?php esc_html_e( 'High Priority Issues', 'nonprofitsuite' ); ?></h2>

				<table class="ns-table">
					<tbody>
						<?php foreach ( array_slice( $active_issues, 0, 3 ) as $issue ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $issue->title ); ?></strong>
									<?php if ( $issue->bill_number ) : ?>
										<br><span class="ns-text-sm"><?php echo esc_html( $issue->bill_number ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php
									$position_colors = array(
										'support' => '#10b981',
										'oppose' => '#ef4444',
										'neutral' => '#6b7280',
									);
									$color = isset( $position_colors[ $issue->position ] ) ? $position_colors[ $issue->position ] : '#6b7280';
									?>
									<span style="color: <?php echo esc_attr( $color ); ?>; font-weight: 600;">
										<?php echo esc_html( ucfirst( $issue->position ?? 'Monitor' ) ); ?>
									</span>
								</td>
								<td><?php echo $issue->current_stage ? esc_html( $issue->current_stage ) : '-'; ?></td>
								<td>
									<?php if ( $issue->target_decision_date ) : ?>
										<?php echo esc_html( date( 'M j, Y', strtotime( $issue->target_decision_date ) ) ); ?>
									<?php else : ?>
										<span class="ns-text-muted">-</span>
									<?php endif; ?>
								</td>
								<td>
									<button class="ns-button ns-button-sm" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
										<?php esc_html_e( 'View Campaign', 'nonprofitsuite' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<div class="ns-card">
			<div class="ns-card-header">
				<h2 class="ns-card-title"><?php esc_html_e( 'All Issues', 'nonprofitsuite' ); ?></h2>
				<button class="ns-button ns-button-primary" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
					+ <?php esc_html_e( 'Add Issue', 'nonprofitsuite' ); ?>
				</button>
			</div>

			<?php if ( ! empty( $all_issues ) && ! is_wp_error( $all_issues ) ) : ?>
				<table class="ns-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Issue', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Position', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Priority', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Decision Date', 'nonprofitsuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $all_issues as $issue ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $issue->title ); ?></strong>
									<?php if ( $issue->bill_number ) : ?>
										<br><span class="ns-text-sm ns-text-muted"><?php echo esc_html( $issue->bill_number ); ?> - <?php echo esc_html( $issue->legislative_body ?? '' ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $issue->issue_type ) ) ); ?></td>
								<td>
									<?php
									$position_colors = array(
										'support' => '#10b981',
										'oppose' => '#ef4444',
										'neutral' => '#6b7280',
									);
									$color = isset( $position_colors[ $issue->position ] ) ? $position_colors[ $issue->position ] : '#6b7280';
									?>
									<span style="color: <?php echo esc_attr( $color ); ?>; font-weight: 600;">
										<?php echo esc_html( ucfirst( $issue->position ?? 'Monitor' ) ); ?>
									</span>
								</td>
								<td><?php echo NonprofitSuite_Utilities::get_status_badge( $issue->status ); ?></td>
								<td>
									<?php
									$priority_colors = array(
										'critical' => '#ef4444',
										'high' => '#f59e0b',
										'medium' => '#6b7280',
										'low' => '#9ca3af',
									);
									$color = isset( $priority_colors[ $issue->priority ] ) ? $priority_colors[ $issue->priority ] : '#6b7280';
									?>
									<span style="color: <?php echo esc_attr( $color ); ?>; font-weight: 600;">
										<?php echo esc_html( ucfirst( $issue->priority ) ); ?>
									</span>
								</td>
								<td>
									<?php if ( $issue->target_decision_date ) : ?>
										<?php
										$days_until = round( ( strtotime( $issue->target_decision_date ) - time() ) / 86400 );
										$is_soon = $days_until <= 30 && $days_until >= 0;
										?>
										<span class="<?php echo $is_soon ? 'ns-text-warning' : ''; ?>">
											<?php echo esc_html( date( 'M j, Y', strtotime( $issue->target_decision_date ) ) ); ?>
										</span>
									<?php else : ?>
										<span class="ns-text-muted">-</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No advocacy issues found. Add your first issue to start tracking!', 'nonprofitsuite' ); ?></p>
			<?php endif; ?>
		</div>

	<?php else : ?>
		<!-- Campaigns Tab -->

		<?php if ( ! empty( $active_campaigns ) && ! is_wp_error( $active_campaigns ) ) : ?>
			<div class="ns-card" style="margin-bottom: 20px;">
				<h2 class="ns-card-title"><?php esc_html_e( 'Active Campaigns', 'nonprofitsuite' ); ?></h2>

				<?php foreach ( $active_campaigns as $campaign ) :
					$progress = NonprofitSuite_Advocacy::calculate_progress( $campaign->id );
					$action_summary = NonprofitSuite_Advocacy::get_action_summary( $campaign->id );
					?>
					<div style="border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin-bottom: 15px;">
						<h3 style="margin: 0 0 10px 0;"><?php echo esc_html( $campaign->campaign_name ); ?></h3>
						<p class="ns-text-muted" style="margin: 0 0 15px 0;"><?php echo esc_html( $campaign->campaign_type ); ?> - <?php echo esc_html( $campaign->issue_title ); ?></p>

						<!-- Progress Bar -->
						<div style="margin-bottom: 15px;">
							<div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
								<span><?php esc_html_e( 'Progress:', 'nonprofitsuite' ); ?> <?php echo absint( $campaign->action_count ); ?> / <?php echo absint( $campaign->goal_count ); ?> <?php esc_html_e( 'actions', 'nonprofitsuite' ); ?></span>
								<strong><?php echo absint( $progress ); ?>%</strong>
							</div>
							<div style="background: #e0e0e0; height: 12px; border-radius: 6px; overflow: hidden;">
								<div style="background: <?php echo $progress >= 100 ? '#10b981' : '#2563eb'; ?>; height: 100%; width: <?php echo absint( $progress ); ?>%; transition: width 0.3s;"></div>
							</div>
						</div>

						<!-- Action Breakdown -->
						<?php if ( ! empty( $action_summary ) ) : ?>
							<div style="display: flex; gap: 15px; margin-bottom: 15px; flex-wrap: wrap;">
								<?php foreach ( $action_summary as $type => $count ) : ?>
									<span class="ns-text-sm">
										<?php
										$icons = array(
											'email_sent' => '📧',
											'call_made' => '📞',
											'letter_sent' => '✉️',
											'social_post' => '📱',
										);
										$icon = isset( $icons[ $type ] ) ? $icons[ $type ] : '•';
										?>
										<?php echo esc_html( $icon ); ?> <?php echo absint( $count ); ?> <?php echo esc_html( ucfirst( str_replace( '_', ' ', $type ) ) ); ?>
									</span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<div style="display: flex; gap: 10px;">
							<button class="ns-button" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
								<?php esc_html_e( 'View Details', 'nonprofitsuite' ); ?>
							</button>
							<button class="ns-button" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
								<?php esc_html_e( 'Send to Supporters', 'nonprofitsuite' ); ?>
							</button>
							<button class="ns-button" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
								<?php esc_html_e( 'Download Report', 'nonprofitsuite' ); ?>
							</button>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="ns-card">
			<div class="ns-card-header">
				<h2 class="ns-card-title"><?php esc_html_e( 'All Campaigns', 'nonprofitsuite' ); ?></h2>
				<button class="ns-button ns-button-primary" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
					+ <?php esc_html_e( 'Launch Campaign', 'nonprofitsuite' ); ?>
				</button>
			</div>

			<?php if ( ! empty( $all_campaigns ) && ! is_wp_error( $all_campaigns ) ) : ?>
				<table class="ns-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Campaign', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Issue', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Progress', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Start Date', 'nonprofitsuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $all_campaigns as $campaign ) :
							$progress = NonprofitSuite_Advocacy::calculate_progress( $campaign->id );
							?>
							<tr>
								<td><strong><?php echo esc_html( $campaign->campaign_name ); ?></strong></td>
								<td><?php echo esc_html( $campaign->issue_title ); ?></td>
								<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $campaign->campaign_type ) ) ); ?></td>
								<td><?php echo NonprofitSuite_Utilities::get_status_badge( $campaign->status ); ?></td>
								<td>
									<div style="display: flex; align-items: center; gap: 10px;">
										<div style="flex: 1; background: #e0e0e0; height: 8px; border-radius: 4px; overflow: hidden; min-width: 60px;">
											<div style="background: <?php echo $progress >= 100 ? '#10b981' : '#2563eb'; ?>; height: 100%; width: <?php echo absint( $progress ); ?>%;"></div>
										</div>
										<span style="font-weight: 600; min-width: 40px;"><?php echo absint( $progress ); ?>%</span>
									</div>
								</td>
								<td><?php echo esc_html( date( 'M j, Y', strtotime( $campaign->start_date ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No campaigns found. Launch your first campaign to mobilize supporters!', 'nonprofitsuite' ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
