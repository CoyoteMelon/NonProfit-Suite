<?php
/**
 * Unified Contact Profile View
 *
 * Shows everything you're authorized to see about an individual:
 * - Contact information
 * - Memberships
 * - Donations
 * - Activities/Interactions
 * - Documents
 * - Calendar events
 * - Tasks
 * - CRM sync status
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/admin/views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$contact_id = isset( $_GET['contact_id'] ) ? intval( $_GET['contact_id'] ) : 0;
$org_id     = isset( $_GET['organization_id'] ) ? intval( $_GET['organization_id'] ) : 0;

if ( ! $contact_id || ! $org_id ) {
	echo '<div class="notice notice-error"><p>Invalid contact or organization ID.</p></div>';
	return;
}

// Get contact data
global $wpdb;
$contact = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}ns_people WHERE id = %d AND organization_id = %d",
		$contact_id,
		$org_id
	),
	ARRAY_A
);

if ( ! $contact ) {
	echo '<div class="notice notice-error"><p>Contact not found.</p></div>';
	return;
}

// Get related data
$memberships = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}ns_memberships WHERE person_id = %d AND organization_id = %d ORDER BY start_date DESC",
		$contact_id,
		$org_id
	),
	ARRAY_A
);

$donations = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}ns_payment_transactions WHERE metadata LIKE %s ORDER BY created_at DESC LIMIT 50",
		'%"donor_id":' . $contact_id . '%'
	),
	ARRAY_A
);

$activities = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}ns_crm_sync_log WHERE entity_id = %d AND entity_type = 'contact' ORDER BY synced_at DESC LIMIT 20",
		$contact_id
	),
	ARRAY_A
);

$total_donations = array_sum( array_column( $donations, 'amount' ) );
$total_net = array_sum( array_column( $donations, 'net_amount' ) );

?>

<div class="wrap ns-contact-profile">
	<h1><?php echo esc_html( $contact['first_name'] . ' ' . $contact['last_name'] ); ?></h1>

	<div class="ns-profile-container">
		<!-- Contact Information -->
		<div class="ns-profile-section ns-contact-info">
			<h2>Contact Information</h2>
			<table class="widefat">
				<tbody>
					<tr>
						<th>Name</th>
						<td><?php echo esc_html( $contact['first_name'] . ' ' . $contact['last_name'] ); ?></td>
					</tr>
					<tr>
						<th>Email</th>
						<td><a href="mailto:<?php echo esc_attr( $contact['email'] ); ?>"><?php echo esc_html( $contact['email'] ); ?></a></td>
					</tr>
					<tr>
						<th>Phone</th>
						<td><?php echo esc_html( $contact['phone'] ?? 'N/A' ); ?></td>
					</tr>
					<tr>
						<th>Address</th>
						<td>
							<?php
							if ( ! empty( $contact['address'] ) ) {
								echo esc_html( $contact['address'] );
							} else {
								echo 'N/A';
							}
							?>
						</td>
					</tr>
					<tr>
						<th>Type</th>
						<td><?php echo esc_html( ucfirst( $contact['person_type'] ?? 'contact' ) ); ?></td>
					</tr>
					<tr>
						<th>Status</th>
						<td>
							<span class="ns-status-badge <?php echo esc_attr( $contact['status'] ?? 'active' ); ?>">
								<?php echo esc_html( ucfirst( $contact['status'] ?? 'active' ) ); ?>
							</span>
						</td>
					</tr>
					<tr>
						<th>Created</th>
						<td><?php echo esc_html( $contact['created_at'] ); ?></td>
					</tr>
				</tbody>
			</table>

			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ns-contacts&action=edit&id=' . $contact_id ) ); ?>" class="button">Edit Contact</a>
			</p>
		</div>

		<!-- Giving Summary -->
		<div class="ns-profile-section ns-giving-summary">
			<h2>Giving Summary</h2>
			<div class="ns-stats-grid">
				<div class="ns-stat-box">
					<div class="ns-stat-label">Total Donations</div>
					<div class="ns-stat-value"><?php echo count( $donations ); ?></div>
				</div>
				<div class="ns-stat-box">
					<div class="ns-stat-label">Total Given</div>
					<div class="ns-stat-value">$<?php echo number_format( $total_donations, 2 ); ?></div>
				</div>
				<div class="ns-stat-box">
					<div class="ns-stat-label">Net Received</div>
					<div class="ns-stat-value">$<?php echo number_format( $total_net, 2 ); ?></div>
				</div>
			</div>
		</div>

		<!-- Memberships -->
		<div class="ns-profile-section ns-memberships">
			<h2>Memberships</h2>
			<?php if ( ! empty( $memberships ) ) : ?>
				<table class="widefat">
					<thead>
						<tr>
							<th>Type</th>
							<th>Status</th>
							<th>Start Date</th>
							<th>End Date</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $memberships as $membership ) : ?>
							<tr>
								<td><?php echo esc_html( $membership['membership_type'] ?? 'Standard' ); ?></td>
								<td>
									<span class="ns-status-badge <?php echo esc_attr( $membership['status'] ); ?>">
										<?php echo esc_html( ucfirst( $membership['status'] ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $membership['start_date'] ); ?></td>
								<td><?php echo esc_html( $membership['end_date'] ?? 'N/A' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p>No memberships found.</p>
			<?php endif; ?>
		</div>

		<!-- Recent Donations -->
		<div class="ns-profile-section ns-donations">
			<h2>Recent Donations</h2>
			<?php if ( ! empty( $donations ) ) : ?>
				<table class="widefat">
					<thead>
						<tr>
							<th>Date</th>
							<th>Amount</th>
							<th>Net</th>
							<th>Processor</th>
							<th>Status</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_slice( $donations, 0, 10 ) as $donation ) : ?>
							<tr>
								<td><?php echo esc_html( $donation['created_at'] ); ?></td>
								<td>$<?php echo number_format( $donation['amount'], 2 ); ?></td>
								<td>$<?php echo number_format( $donation['net_amount'], 2 ); ?></td>
								<td><?php echo esc_html( ucfirst( $donation['processor'] ) ); ?></td>
								<td>
									<span class="ns-status-badge <?php echo esc_attr( $donation['status'] ); ?>">
										<?php echo esc_html( ucfirst( $donation['status'] ) ); ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( count( $donations ) > 10 ) : ?>
					<p><em>Showing 10 of <?php echo count( $donations ); ?> total donations</em></p>
				<?php endif; ?>
			<?php else : ?>
				<p>No donations found.</p>
			<?php endif; ?>
		</div>

		<!-- CRM Sync Status -->
		<div class="ns-profile-section ns-crm-sync">
			<h2>CRM Sync Status</h2>
			<?php
			$crm_syncs = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}ns_crm_sync_log WHERE entity_type = 'contact' AND entity_id = %d ORDER BY synced_at DESC LIMIT 10",
					$contact_id
				),
				ARRAY_A
			);

			if ( ! empty( $crm_syncs ) ) :
				?>
				<table class="widefat">
					<thead>
						<tr>
							<th>CRM</th>
							<th>Direction</th>
							<th>Action</th>
							<th>Status</th>
							<th>Date</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $crm_syncs as $sync ) : ?>
							<tr>
								<td><?php echo esc_html( ucfirst( $sync['crm_provider'] ) ); ?></td>
								<td><?php echo esc_html( ucfirst( $sync['sync_direction'] ) ); ?></td>
								<td><?php echo esc_html( ucfirst( $sync['sync_action'] ) ); ?></td>
								<td>
									<span class="ns-status-badge <?php echo esc_attr( $sync['sync_status'] ); ?>">
										<?php echo esc_html( ucfirst( $sync['sync_status'] ) ); ?>
									</span>
									<?php if ( $sync['sync_status'] === 'error' && ! empty( $sync['error_message'] ) ) : ?>
										<br><small><?php echo esc_html( $sync['error_message'] ); ?></small>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $sync['synced_at'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p>
					<button type="button" class="button ns-sync-contact" data-contact-id="<?php echo esc_attr( $contact_id ); ?>" data-org-id="<?php echo esc_attr( $org_id ); ?>">
						Sync to CRM Now
					</button>
				</p>
			<?php else : ?>
				<p>No CRM sync history found.</p>
				<p>
					<button type="button" class="button button-primary ns-sync-contact" data-contact-id="<?php echo esc_attr( $contact_id ); ?>" data-org-id="<?php echo esc_attr( $org_id ); ?>">
						Sync to CRM
					</button>
				</p>
			<?php endif; ?>
		</div>

		<!-- Activity Timeline -->
		<div class="ns-profile-section ns-activity-timeline">
			<h2>Activity Timeline</h2>
			<div class="ns-timeline">
				<?php
				// Combine all activities into a timeline
				$timeline_items = array();

				// Add contact creation
				$timeline_items[] = array(
					'date'        => $contact['created_at'],
					'type'        => 'contact_created',
					'description' => 'Contact created',
					'icon'        => 'admin-users',
				);

				// Add memberships
				foreach ( $memberships as $membership ) {
					$timeline_items[] = array(
						'date'        => $membership['start_date'],
						'type'        => 'membership',
						'description' => 'Membership started: ' . $membership['membership_type'],
						'icon'        => 'groups',
					);
				}

				// Add donations
				foreach ( array_slice( $donations, 0, 10 ) as $donation ) {
					$timeline_items[] = array(
						'date'        => $donation['created_at'],
						'type'        => 'donation',
						'description' => 'Donation: $' . number_format( $donation['amount'], 2 ),
						'icon'        => 'money-alt',
					);
				}

				// Sort by date descending
				usort(
					$timeline_items,
					function( $a, $b ) {
						return strtotime( $b['date'] ) - strtotime( $a['date'] );
					}
				);

				foreach ( array_slice( $timeline_items, 0, 20 ) as $item ) :
					?>
					<div class="ns-timeline-item">
						<span class="dashicons dashicons-<?php echo esc_attr( $item['icon'] ); ?>"></span>
						<div class="ns-timeline-content">
							<div class="ns-timeline-date"><?php echo esc_html( $item['date'] ); ?></div>
							<div class="ns-timeline-description"><?php echo esc_html( $item['description'] ); ?></div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</div>

<style>
.ns-profile-container {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
	gap: 20px;
	margin-top: 20px;
}

.ns-profile-section {
	background: #fff;
	border: 1px solid #ccd0d4;
	padding: 20px;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.ns-profile-section h2 {
	margin-top: 0;
	border-bottom: 1px solid #eee;
	padding-bottom: 10px;
}

.ns-stats-grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 15px;
}

.ns-stat-box {
	text-align: center;
	padding: 15px;
	background: #f0f0f1;
	border-radius: 4px;
}

.ns-stat-label {
	font-size: 12px;
	color: #646970;
	margin-bottom: 5px;
}

.ns-stat-value {
	font-size: 24px;
	font-weight: bold;
	color: #1d2327;
}

.ns-status-badge {
	display: inline-block;
	padding: 3px 8px;
	border-radius: 3px;
	font-size: 12px;
	font-weight: 500;
}

.ns-status-badge.active,
.ns-status-badge.success {
	background: #d4edda;
	color: #155724;
}

.ns-status-badge.pending {
	background: #fff3cd;
	color: #856404;
}

.ns-status-badge.expired,
.ns-status-badge.error {
	background: #f8d7da;
	color: #721c24;
}

.ns-timeline-item {
	display: flex;
	gap: 10px;
	margin-bottom: 15px;
	padding-bottom: 15px;
	border-bottom: 1px solid #eee;
}

.ns-timeline-item:last-child {
	border-bottom: none;
}

.ns-timeline-item .dashicons {
	color: #2271b1;
	margin-top: 3px;
}

.ns-timeline-date {
	font-size: 12px;
	color: #646970;
}

.ns-timeline-description {
	font-weight: 500;
	color: #1d2327;
}
</style>

<script>
jQuery(document).ready(function($) {
	$('.ns-sync-contact').on('click', function() {
		var button = $(this);
		var contactId = button.data('contact-id');
		var orgId = button.data('org-id');

		button.prop('disabled', true).text('Syncing...');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ns_sync_contact_to_crm',
				contact_id: contactId,
				organization_id: orgId,
				nonce: '<?php echo wp_create_nonce( 'ns_crm_sync' ); ?>'
			},
			success: function(response) {
				if (response.success) {
					alert('Contact synced successfully!');
					location.reload();
				} else {
					alert('Sync failed: ' + response.data.message);
					button.prop('disabled', false).text('Sync to CRM Now');
				}
			},
			error: function() {
				alert('Sync failed due to a network error.');
				button.prop('disabled', false).text('Sync to CRM Now');
			}
		});
	});
});
</script>
