<?php
/**
 * Membership (PRO) View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$members = NonprofitSuite_Membership::get_members( array( 'status' => 'active' ) );
$expiring_soon = NonprofitSuite_Membership::get_expiring_soon( 30 );
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Membership Management', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<?php if ( ! empty( $expiring_soon ) && ! is_wp_error( $expiring_soon ) ) : ?>
		<div class="ns-alert ns-alert-warning">
			<strong><?php esc_html_e( 'Expiring Soon:', 'nonprofitsuite' ); ?></strong>
			<?php printf( _n( '%d membership expires within 30 days', '%d memberships expire within 30 days', count( $expiring_soon ), 'nonprofitsuite' ), count( $expiring_soon ) ); ?>
		</div>
	<?php endif; ?>

	<div class="ns-card">
		<div class="ns-card-header">
			<h2 class="ns-card-title"><?php esc_html_e( 'Active Members', 'nonprofitsuite' ); ?></h2>
			<button class="ns-button ns-button-primary" onclick="alert('Add member feature coming soon');">
				<?php esc_html_e( 'Add Member', 'nonprofitsuite' ); ?>
			</button>
		</div>

		<?php if ( ! empty( $members ) && ! is_wp_error( $members ) ) : ?>
			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Member ID', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Level', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Join Date', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Expires', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Dues', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $members as $member ) : ?>
						<tr>
							<td><strong>Member #<?php echo esc_html( $member->id ); ?></strong></td>
							<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $member->membership_type ) ) ); ?></td>
							<td><?php echo esc_html( ucfirst( $member->membership_level ) ); ?></td>
							<td><?php echo esc_html( date( 'M j, Y', strtotime( $member->join_date ) ) ); ?></td>
							<td>
								<?php
								if ( $member->expiration_date ) {
									echo esc_html( date( 'M j, Y', strtotime( $member->expiration_date ) ) );
								} else {
									esc_html_e( 'Lifetime', 'nonprofitsuite' );
								}
								?>
							</td>
							<td>$<?php echo number_format( $member->dues_amount, 2 ); ?></td>
							<td><?php echo NonprofitSuite_Utilities::get_status_badge( $member->status ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No members found. Add your first member to get started!', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>
</div>
