<?php
/**
 * Policy Management (PRO) View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$policies = NonprofitSuite_Policy::get_policies();
$due_for_review = NonprofitSuite_Policy::get_policies( array( 'due_for_review' => true ) );
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Policy Management', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<?php if ( ! empty( $due_for_review ) && ! is_wp_error( $due_for_review ) ) : ?>
		<div class="ns-alert ns-alert-warning">
			<strong><?php esc_html_e( 'Review Needed:', 'nonprofitsuite' ); ?></strong>
			<?php printf( _n( '%d policy is due for review', '%d policies are due for review', count( $due_for_review ), 'nonprofitsuite' ), count( $due_for_review ) ); ?>
		</div>
	<?php endif; ?>

	<div class="ns-card">
		<div class="ns-card-header">
			<h2 class="ns-card-title"><?php esc_html_e( 'Organizational Policies', 'nonprofitsuite' ); ?></h2>
			<button class="ns-button ns-button-primary" onclick="alert('Add policy feature coming soon');">
				<?php esc_html_e( 'Add Policy', 'nonprofitsuite' ); ?>
			</button>
		</div>

		<?php if ( ! empty( $policies ) && ! is_wp_error( $policies ) ) : ?>
			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Policy', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Category', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Version', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Effective Date', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Next Review', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $policies as $policy ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $policy->title ); ?></strong>
								<?php if ( $policy->policy_number ) : ?>
									<br><span class="ns-text-sm ns-text-muted"><?php echo esc_html( $policy->policy_number ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( ucfirst( $policy->category ) ); ?></td>
							<td><?php echo esc_html( $policy->version ); ?></td>
							<td><?php echo NonprofitSuite_Utilities::get_status_badge( $policy->status ); ?></td>
							<td>
								<?php if ( $policy->effective_date ) : ?>
									<?php echo esc_html( date( 'M j, Y', strtotime( $policy->effective_date ) ) ); ?>
								<?php else : ?>
									<span class="ns-text-muted">-</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $policy->next_review_date ) : ?>
									<?php
									$days_until = round( ( strtotime( $policy->next_review_date ) - time() ) / 86400 );
									$class = $days_until < 30 ? 'ns-text-warning' : '';
									if ( $days_until < 0 ) {
										$class = 'ns-text-danger';
									}
									?>
									<span class="<?php echo $class; ?>">
										<?php echo esc_html( date( 'M j, Y', strtotime( $policy->next_review_date ) ) ); ?>
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
			<p><?php esc_html_e( 'No policies found. Create your first organizational policy to get started!', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>
</div>
