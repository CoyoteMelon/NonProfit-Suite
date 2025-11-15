<?php
/**
 * CRM Sync Log View
 *
 * Shows sync history and status for CRM operations.
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/admin/views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="wrap">
	<h1>CRM Sync Log</h1>
	<p>View the history of CRM synchronization operations.</p>

	<?php if ( ! empty( $logs ) ) : ?>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th>Date</th>
					<th>CRM Provider</th>
					<th>Direction</th>
					<th>Entity Type</th>
					<th>Action</th>
					<th>Status</th>
					<th>Details</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logs as $log ) : ?>
					<tr>
						<td><?php echo esc_html( $log['synced_at'] ); ?></td>
						<td><?php echo esc_html( ucfirst( $log['crm_provider'] ) ); ?></td>
						<td><?php echo esc_html( ucfirst( $log['sync_direction'] ) ); ?></td>
						<td><?php echo esc_html( ucfirst( $log['entity_type'] ) ); ?></td>
						<td><?php echo esc_html( ucfirst( $log['sync_action'] ) ); ?></td>
						<td>
							<span class="ns-status-badge <?php echo esc_attr( $log['sync_status'] ); ?>">
								<?php echo esc_html( ucfirst( $log['sync_status'] ) ); ?>
							</span>
						</td>
						<td>
							<?php if ( $log['sync_status'] === 'error' && ! empty( $log['error_message'] ) ) : ?>
								<details>
									<summary>Error Details</summary>
									<pre><?php echo esc_html( $log['error_message'] ); ?></pre>
								</details>
							<?php elseif ( $log['sync_status'] === 'success' ) : ?>
								NS ID: <?php echo esc_html( $log['entity_id'] ); ?><br>
								CRM ID: <?php echo esc_html( $log['crm_entity_id'] ); ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p>No sync log entries found.</p>
	<?php endif; ?>

	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ns-crm-settings&organization_id=' . $org_id ) ); ?>" class="button">
			Back to CRM Settings
		</a>
	</p>
</div>

<style>
.ns-status-badge {
	display: inline-block;
	padding: 3px 8px;
	border-radius: 3px;
	font-size: 12px;
	font-weight: 500;
}

.ns-status-badge.success {
	background: #d4edda;
	color: #155724;
}

.ns-status-badge.error {
	background: #f8d7da;
	color: #721c24;
}

.ns-status-badge.conflict {
	background: #fff3cd;
	color: #856404;
}

details {
	cursor: pointer;
}

details pre {
	background: #f0f0f1;
	padding: 10px;
	border-radius: 3px;
	margin-top: 5px;
	font-size: 12px;
}
</style>
