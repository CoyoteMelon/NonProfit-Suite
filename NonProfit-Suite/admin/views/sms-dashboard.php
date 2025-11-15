<?php
/**
 * SMS Dashboard View
 *
 * Displays SMS statistics and recent messages.
 *
 * @package NonprofitSuite
 * @subpackage Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// Get organization ID (simplified - in production, get from user's context)
$organization_id = 1;

// Get statistics
$messages_table = $wpdb->prefix . 'ns_sms_messages';
$campaigns_table = $wpdb->prefix . 'ns_sms_campaigns';
$settings_table = $wpdb->prefix . 'ns_sms_settings';

// Total messages sent this month
$current_month = date( 'Y-m-01' );
$total_sent = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$messages_table} WHERE organization_id = %d AND created_at >= %s",
		$organization_id,
		$current_month
	)
);

// Total cost this month
$total_cost = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT SUM(cost) FROM {$messages_table} WHERE organization_id = %d AND created_at >= %s",
		$organization_id,
		$current_month
	)
);

// Active campaigns
$active_campaigns = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$campaigns_table} WHERE organization_id = %d AND status IN ('draft', 'scheduled', 'sending')",
		$organization_id
	)
);

// Recent messages
$recent_messages = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$messages_table} WHERE organization_id = %d ORDER BY created_at DESC LIMIT 20",
		$organization_id
	),
	ARRAY_A
);

// Provider settings
$providers = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$settings_table} WHERE organization_id = %d",
		$organization_id
	),
	ARRAY_A
);

?>

<div class="wrap">
	<h1><?php esc_html_e( 'SMS & Messaging Dashboard', 'nonprofitsuite' ); ?></h1>

	<?php if ( empty( $providers ) ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php esc_html_e( 'No SMS providers are configured. Please configure at least one provider in', 'nonprofitsuite' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ns-sms-settings' ) ); ?>">
					<?php esc_html_e( 'SMS Settings', 'nonprofitsuite' ); ?>
				</a>.
			</p>
		</div>
	<?php endif; ?>

	<!-- Statistics Cards -->
	<div class="ns-stats-grid">
		<div class="ns-stat-card">
			<div class="stat-icon">
				<span class="dashicons dashicons-email"></span>
			</div>
			<div class="stat-content">
				<h3><?php echo esc_html( number_format( $total_sent ) ); ?></h3>
				<p><?php esc_html_e( 'Messages Sent This Month', 'nonprofitsuite' ); ?></p>
			</div>
		</div>

		<div class="ns-stat-card">
			<div class="stat-icon">
				<span class="dashicons dashicons-money"></span>
			</div>
			<div class="stat-content">
				<h3>$<?php echo esc_html( number_format( $total_cost, 2 ) ); ?></h3>
				<p><?php esc_html_e( 'Total Cost This Month', 'nonprofitsuite' ); ?></p>
			</div>
		</div>

		<div class="ns-stat-card">
			<div class="stat-icon">
				<span class="dashicons dashicons-megaphone"></span>
			</div>
			<div class="stat-content">
				<h3><?php echo esc_html( $active_campaigns ); ?></h3>
				<p><?php esc_html_e( 'Active Campaigns', 'nonprofitsuite' ); ?></p>
			</div>
		</div>

		<div class="ns-stat-card">
			<div class="stat-icon">
				<span class="dashicons dashicons-admin-plugins"></span>
			</div>
			<div class="stat-content">
				<h3><?php echo esc_html( count( $providers ) ); ?></h3>
				<p><?php esc_html_e( 'Configured Providers', 'nonprofitsuite' ); ?></p>
			</div>
		</div>
	</div>

	<!-- Provider Status -->
	<div class="ns-section">
		<h2><?php esc_html_e( 'Provider Status', 'nonprofitsuite' ); ?></h2>

		<?php if ( ! empty( $providers ) ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Provider', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Phone Number', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'This Month', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Monthly Limit', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Cost', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $providers as $provider ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( ucfirst( $provider['provider'] ) ); ?></strong>
							</td>
							<td><?php echo esc_html( $provider['phone_number'] ); ?></td>
							<td>
								<?php if ( $provider['is_active'] ) : ?>
									<span class="status-badge active"><?php esc_html_e( 'Active', 'nonprofitsuite' ); ?></span>
								<?php else : ?>
									<span class="status-badge inactive"><?php esc_html_e( 'Inactive', 'nonprofitsuite' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( number_format( $provider['current_month_count'] ) ); ?></td>
							<td><?php echo esc_html( number_format( $provider['monthly_limit'] ) ); ?></td>
							<td>$<?php echo esc_html( number_format( $provider['current_month_cost'], 2 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No providers configured.', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>

	<!-- Recent Messages -->
	<div class="ns-section">
		<h2><?php esc_html_e( 'Recent Messages', 'nonprofitsuite' ); ?></h2>

		<?php if ( ! empty( $recent_messages ) ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'To', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Message', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Provider', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Segments', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Cost', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent_messages as $message ) : ?>
						<tr>
							<td><?php echo esc_html( date( 'M j, Y g:i a', strtotime( $message['created_at'] ) ) ); ?></td>
							<td><?php echo esc_html( $message['recipient_phone'] ); ?></td>
							<td>
								<div class="message-preview">
									<?php echo esc_html( wp_trim_words( $message['message_body'], 10 ) ); ?>
								</div>
							</td>
							<td><?php echo esc_html( ucfirst( $message['provider'] ) ); ?></td>
							<td>
								<span class="status-badge <?php echo esc_attr( $message['status'] ); ?>">
									<?php echo esc_html( ucfirst( $message['status'] ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $message['segments'] ); ?></td>
							<td>$<?php echo esc_html( number_format( $message['cost'], 4 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No messages sent yet.', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>
</div>

<style>
.ns-stats-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 20px;
	margin: 20px 0;
}

.ns-stat-card {
	background: #fff;
	border: 1px solid #ccd0d4;
	padding: 20px;
	display: flex;
	align-items: center;
	gap: 15px;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.stat-icon {
	font-size: 48px;
	color: #2271b1;
}

.stat-icon .dashicons {
	width: 48px;
	height: 48px;
	font-size: 48px;
}

.stat-content h3 {
	margin: 0 0 5px 0;
	font-size: 28px;
	font-weight: 600;
}

.stat-content p {
	margin: 0;
	color: #666;
	font-size: 13px;
}

.ns-section {
	background: #fff;
	border: 1px solid #ccd0d4;
	padding: 20px;
	margin: 20px 0;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.ns-section h2 {
	margin-top: 0;
	padding-bottom: 10px;
	border-bottom: 1px solid #ddd;
}

.status-badge {
	display: inline-block;
	padding: 3px 8px;
	border-radius: 3px;
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
}

.status-badge.active,
.status-badge.delivered,
.status-badge.sent {
	background: #d4edda;
	color: #155724;
}

.status-badge.inactive,
.status-badge.failed {
	background: #f8d7da;
	color: #721c24;
}

.status-badge.queued {
	background: #fff3cd;
	color: #856404;
}

.message-preview {
	max-width: 300px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
</style>
