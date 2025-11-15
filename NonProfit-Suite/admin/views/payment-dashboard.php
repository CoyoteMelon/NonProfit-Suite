<?php
/**
 * Payment Dashboard
 *
 * @package NonprofitSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Payment Dashboard', 'nonprofitsuite' ); ?></h1>

	<div class="ns-payment-dashboard">
		<!-- Summary Cards -->
		<div class="ns-dashboard-cards">
			<div class="ns-card">
				<h3><?php esc_html_e( 'Total Revenue', 'nonprofitsuite' ); ?></h3>
				<div class="ns-card-value">$<?php echo number_format( $transaction_summary['completed_amount'], 2 ); ?></div>
				<div class="ns-card-meta"><?php echo number_format( $transaction_summary['total_transactions'] ); ?> transactions</div>
			</div>

			<div class="ns-card">
				<h3><?php esc_html_e( 'Net After Fees', 'nonprofitsuite' ); ?></h3>
				<div class="ns-card-value">$<?php echo number_format( $transaction_summary['total_net'], 2 ); ?></div>
				<div class="ns-card-meta">$<?php echo number_format( $transaction_summary['total_fees'], 2 ); ?> in fees</div>
			</div>

			<div class="ns-card">
				<h3><?php esc_html_e( 'Monthly Recurring Revenue', 'nonprofitsuite' ); ?></h3>
				<div class="ns-card-value">$<?php echo number_format( $recurring_summary['monthly_recurring_revenue'], 2 ); ?></div>
				<div class="ns-card-meta"><?php echo $recurring_summary['active_subscriptions']; ?> active subscriptions</div>
			</div>

			<div class="ns-card">
				<h3><?php esc_html_e( 'Pledges', 'nonprofitsuite' ); ?></h3>
				<div class="ns-card-value">$<?php echo number_format( $pledge_summary['total_remaining'], 2 ); ?></div>
				<div class="ns-card-meta"><?php echo round( $pledge_summary['fulfillment_rate'], 1 ); ?>% fulfillment rate</div>
			</div>
		</div>

		<!-- Transactions Requiring Attention -->
		<?php if ( ! empty( $attention_transactions ) ) : ?>
			<div class="ns-attention-section">
				<h2><?php esc_html_e( 'Transactions Requiring Attention', 'nonprofitsuite' ); ?></h2>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Description', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $attention_transactions as $txn ) : ?>
							<tr>
								<td><?php echo esc_html( date( 'M j, Y', strtotime( $txn['created_at'] ) ) ); ?></td>
								<td>$<?php echo esc_html( number_format( $txn['amount'], 2 ) ); ?></td>
								<td>
									<span class="ns-status-badge ns-status-<?php echo esc_attr( $txn['status'] ); ?>">
										<?php echo esc_html( ucfirst( $txn['status'] ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $txn['description'] ); ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-transactions&txn_id=' . $txn['id'] ) ); ?>" class="button button-small">
										<?php esc_html_e( 'View', 'nonprofitsuite' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<!-- Quick Links -->
		<div class="ns-quick-links">
			<h2><?php esc_html_e( 'Quick Links', 'nonprofitsuite' ); ?></h2>
			<div class="ns-links-grid">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-processors' ) ); ?>" class="ns-quick-link">
					<span class="dashicons dashicons-admin-plugins"></span>
					<?php esc_html_e( 'Configure Processors', 'nonprofitsuite' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-bank-accounts' ) ); ?>" class="ns-quick-link">
					<span class="dashicons dashicons-building"></span>
					<?php esc_html_e( 'Manage Bank Accounts', 'nonprofitsuite' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-sweep-schedules' ) ); ?>" class="ns-quick-link">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Setup Sweep Schedules', 'nonprofitsuite' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-transactions' ) ); ?>" class="ns-quick-link">
					<span class="dashicons dashicons-list-view"></span>
					<?php esc_html_e( 'View All Transactions', 'nonprofitsuite' ); ?>
				</a>
			</div>
		</div>
	</div>
</div>

<style>
.ns-payment-dashboard {
	margin-top: 20px;
}

.ns-dashboard-cards {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 20px;
	margin-bottom: 30px;
}

.ns-card {
	background: #fff;
	padding: 20px;
	border: 1px solid #ccd0d4;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
	border-radius: 4px;
}

.ns-card h3 {
	margin: 0 0 10px 0;
	font-size: 14px;
	font-weight: 600;
	color: #646970;
	text-transform: uppercase;
}

.ns-card-value {
	font-size: 32px;
	font-weight: 700;
	color: #1d2327;
	margin-bottom: 5px;
}

.ns-card-meta {
	font-size: 13px;
	color: #646970;
}

.ns-attention-section,
.ns-quick-links {
	background: #fff;
	padding: 20px;
	margin-bottom: 20px;
	border: 1px solid #ccd0d4;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.ns-links-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 15px;
	margin-top: 15px;
}

.ns-quick-link {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 15px;
	background: #f6f7f7;
	border: 1px solid #dcdcde;
	border-radius: 4px;
	text-decoration: none;
	color: #2271b1;
	font-weight: 500;
	transition: all 0.2s;
}

.ns-quick-link:hover {
	background: #2271b1;
	color: #fff;
	border-color: #2271b1;
}

.ns-quick-link .dashicons {
	font-size: 24px;
	width: 24px;
	height: 24px;
}

.ns-status-badge {
	padding: 3px 8px;
	border-radius: 3px;
	font-size: 12px;
	font-weight: 600;
}

.ns-status-disputed {
	background: #fff3cd;
	color: #856404;
}

.ns-status-failed {
	background: #f8d7da;
	color: #721c24;
}

.ns-status-pending_review {
	background: #d1ecf1;
	color: #0c5460;
}
</style>
