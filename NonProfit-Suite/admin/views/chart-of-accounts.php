<?php
/**
 * Chart of Accounts Page
 *
 * @package NonprofitSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Chart of Accounts', 'nonprofitsuite' ); ?></h1>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Account Number', 'nonprofitsuite' ); ?></th>
				<th><?php esc_html_e( 'Account Name', 'nonprofitsuite' ); ?></th>
				<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
				<th><?php esc_html_e( 'Balance', 'nonprofitsuite' ); ?></th>
				<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $accounts ) ) : ?>
				<tr>
					<td colspan="5"><?php esc_html_e( 'No accounts found. Accounts will be created automatically from payment transactions.', 'nonprofitsuite' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $accounts as $account ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $account['account_number'] ); ?></strong></td>
						<td><?php echo esc_html( $account['account_name'] ); ?></td>
						<td><?php echo esc_html( ucfirst( $account['account_type'] ) ); ?></td>
						<td>$<?php echo esc_html( number_format( $account['current_balance'], 2 ) ); ?></td>
						<td>
							<?php if ( $account['is_active'] ) : ?>
								<span class="ns-status-badge ns-status-active"><?php esc_html_e( 'Active', 'nonprofitsuite' ); ?></span>
							<?php else : ?>
								<span class="ns-status-badge ns-status-inactive"><?php esc_html_e( 'Inactive', 'nonprofitsuite' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>

<style>
.ns-status-badge {
	padding: 3px 8px;
	border-radius: 3px;
	font-size: 12px;
	font-weight: 600;
}

.ns-status-active {
	background: #d4edda;
	color: #155724;
}

.ns-status-inactive {
	background: #f8d7da;
	color: #721c24;
}
</style>
