<?php
/**
 * Transactions Admin Page
 *
 * @package NonprofitSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Payment Transactions', 'nonprofitsuite' ); ?></h1>

	<div class="ns-transactions-filters">
		<form method="get" action="">
			<input type="hidden" name="page" value="nonprofitsuite-transactions">

			<select name="status">
				<option value=""><?php esc_html_e( 'All Statuses', 'nonprofitsuite' ); ?></option>
				<option value="completed" <?php selected( isset( $_GET['status'] ) && $_GET['status'] === 'completed' ); ?>><?php esc_html_e( 'Completed', 'nonprofitsuite' ); ?></option>
				<option value="pending" <?php selected( isset( $_GET['status'] ) && $_GET['status'] === 'pending' ); ?>><?php esc_html_e( 'Pending', 'nonprofitsuite' ); ?></option>
				<option value="failed" <?php selected( isset( $_GET['status'] ) && $_GET['status'] === 'failed' ); ?>><?php esc_html_e( 'Failed', 'nonprofitsuite' ); ?></option>
				<option value="disputed" <?php selected( isset( $_GET['status'] ) && $_GET['status'] === 'disputed' ); ?>><?php esc_html_e( 'Disputed', 'nonprofitsuite' ); ?></option>
			</select>

			<select name="payment_type">
				<option value=""><?php esc_html_e( 'All Types', 'nonprofitsuite' ); ?></option>
				<option value="donation" <?php selected( isset( $_GET['payment_type'] ) && $_GET['payment_type'] === 'donation' ); ?>><?php esc_html_e( 'Donation', 'nonprofitsuite' ); ?></option>
				<option value="membership" <?php selected( isset( $_GET['payment_type'] ) && $_GET['payment_type'] === 'membership' ); ?>><?php esc_html_e( 'Membership', 'nonprofitsuite' ); ?></option>
				<option value="event" <?php selected( isset( $_GET['payment_type'] ) && $_GET['payment_type'] === 'event' ); ?>><?php esc_html_e( 'Event', 'nonprofitsuite' ); ?></option>
			</select>

			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'nonprofitsuite' ); ?></button>
		</form>
	</div>

	<div class="ns-transactions-list">
		<?php if ( empty( $transactions ) ) : ?>
			<p><?php esc_html_e( 'No transactions found.', 'nonprofitsuite' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Description', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Fee', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Net', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $transactions as $txn ) : ?>
						<tr>
							<td><?php echo esc_html( date( 'M j, Y g:i A', strtotime( $txn['created_at'] ) ) ); ?></td>
							<td>$<?php echo esc_html( number_format( $txn['amount'], 2 ) ); ?></td>
							<td><?php echo esc_html( ucfirst( $txn['payment_type'] ) ); ?></td>
							<td>
								<span class="ns-status-badge ns-status-<?php echo esc_attr( $txn['status'] ); ?>">
									<?php echo esc_html( ucfirst( $txn['status'] ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $txn['description'] ); ?></td>
							<td>$<?php echo esc_html( number_format( $txn['fee_amount'], 2 ) ); ?></td>
							<td><strong>$<?php echo esc_html( number_format( $txn['net_amount'], 2 ) ); ?></strong></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>

<style>
.ns-transactions-filters {
	background: #fff;
	padding: 15px;
	margin: 20px 0;
	border: 1px solid #ccd0d4;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.ns-transactions-filters form {
	display: flex;
	gap: 10px;
	align-items: center;
}

.ns-transactions-list {
	background: #fff;
	padding: 20px;
	border: 1px solid #ccd0d4;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.ns-status-badge {
	padding: 3px 8px;
	border-radius: 3px;
	font-size: 12px;
	font-weight: 600;
}

.ns-status-completed {
	background: #d4edda;
	color: #155724;
}

.ns-status-pending {
	background: #fff3cd;
	color: #856404;
}

.ns-status-failed {
	background: #f8d7da;
	color: #721c24;
}

.ns-status-disputed {
	background: #f8d7da;
	color: #721c24;
}
</style>
