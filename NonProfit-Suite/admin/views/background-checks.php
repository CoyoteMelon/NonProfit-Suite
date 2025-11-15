<?php
/**
 * Background Checks View
 *
 * @package NonprofitSuite
 * @subpackage Admin/Views
 * @since 1.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$requests_table = $wpdb->prefix . 'ns_background_check_requests';

// Get recent requests
$recent_requests = $wpdb->get_results(
	"SELECT r.*, c.first_name, c.last_name, c.email
	FROM $requests_table r
	LEFT JOIN {$wpdb->prefix}ns_contacts c ON r.contact_id = c.id
	ORDER BY r.created_at DESC
	LIMIT 100"
);

// Get pending requests
$pending_requests = $wpdb->get_results(
	"SELECT r.*, c.first_name, c.last_name, c.email
	FROM $requests_table r
	LEFT JOIN {$wpdb->prefix}ns_contacts c ON r.contact_id = c.id
	WHERE r.request_status IN ('pending', 'sent')
	ORDER BY r.created_at DESC"
);

// Get completed requests needing review
$review_needed = $wpdb->get_results(
	"SELECT r.*, c.first_name, c.last_name, c.email
	FROM $requests_table r
	LEFT JOIN {$wpdb->prefix}ns_contacts c ON r.contact_id = c.id
	WHERE r.request_status = 'completed'
	AND r.reviewed_by IS NULL
	ORDER BY r.completed_at DESC
	LIMIT 50"
);

?>

<div class="wrap ns-background-checks">
	<h1><?php esc_html_e( 'Background Checks', 'nonprofitsuite' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Manage FCRA-compliant background checks for volunteers, staff, and board members.', 'nonprofitsuite' ); ?>
	</p>

	<!-- Tabs -->
	<h2 class="nav-tab-wrapper">
		<a href="#all-checks" class="nav-tab nav-tab-active"><?php esc_html_e( 'All Checks', 'nonprofitsuite' ); ?></a>
		<a href="#pending" class="nav-tab"><?php esc_html_e( 'Pending Consent', 'nonprofitsuite' ); ?></a>
		<a href="#review-needed" class="nav-tab"><?php esc_html_e( 'Review Needed', 'nonprofitsuite' ); ?></a>
		<a href="#compliance" class="nav-tab"><?php esc_html_e( 'Compliance Report', 'nonprofitsuite' ); ?></a>
	</h2>

	<!-- All Checks Tab -->
	<div id="all-checks" class="tab-content active">
		<div class="postbox">
			<div class="postbox-header">
				<h2><?php esc_html_e( 'All Background Checks', 'nonprofitsuite' ); ?></h2>
			</div>
			<div class="inside">
				<?php if ( empty( $recent_requests ) ) : ?>
					<p><?php esc_html_e( 'No background checks yet.', 'nonprofitsuite' ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Candidate', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Package', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Result', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Progress', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Requested', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $recent_requests as $request ) : ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $request->first_name . ' ' . $request->last_name ); ?></strong><br>
										<small><?php echo esc_html( $request->email ); ?></small>
									</td>
									<td><?php echo esc_html( ucfirst( $request->check_type ) ); ?></td>
									<td><?php echo esc_html( ucfirst( $request->package_name ) ); ?></td>
									<td>
										<span class="status-badge status-<?php echo esc_attr( $request->request_status ); ?>">
											<?php echo esc_html( ucwords( str_replace( '_', ' ', $request->request_status ) ) ); ?>
										</span>
									</td>
									<td>
										<?php if ( $request->overall_status ) : ?>
											<span class="result-badge result-<?php echo esc_attr( $request->overall_status ); ?>">
												<?php echo esc_html( ucfirst( $request->overall_status ) ); ?>
											</span>
										<?php else : ?>
											—
										<?php endif; ?>
									</td>
									<td>
										<div class="progress-bar">
											<div class="progress-fill" style="width: <?php echo esc_attr( $request->completion_percentage ); ?>%"></div>
										</div>
										<small><?php echo esc_html( $request->completion_percentage ); ?>%</small>
									</td>
									<td><?php echo esc_html( human_time_diff( strtotime( $request->created_at ), time() ) . ' ago' ); ?></td>
									<td>
										<button type="button" class="button button-small refresh-status" data-request-id="<?php echo esc_attr( $request->id ); ?>">
											<?php esc_html_e( 'Refresh', 'nonprofitsuite' ); ?>
										</button>
										<?php if ( 'pending' === $request->request_status ) : ?>
											<button type="button" class="button button-small send-invitation" data-request-id="<?php echo esc_attr( $request->id ); ?>">
												<?php esc_html_e( 'Send Invitation', 'nonprofitsuite' ); ?>
											</button>
										<?php endif; ?>
										<?php if ( 'completed' === $request->request_status && ! $request->reviewed_by ) : ?>
											<button type="button" class="button button-small button-primary review-check" data-request-id="<?php echo esc_attr( $request->id ); ?>">
												<?php esc_html_e( 'Review', 'nonprofitsuite' ); ?>
											</button>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- Pending Consent Tab -->
	<div id="pending" class="tab-content" style="display:none;">
		<div class="postbox">
			<div class="postbox-header">
				<h2><?php esc_html_e( 'Pending Consent', 'nonprofitsuite' ); ?></h2>
			</div>
			<div class="inside">
				<p class="description">
					<?php esc_html_e( 'These checks are awaiting candidate consent. Send consent invitations to proceed.', 'nonprofitsuite' ); ?>
				</p>

				<?php if ( empty( $pending_requests ) ) : ?>
					<p><?php esc_html_e( 'No pending consent requests.', 'nonprofitsuite' ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Candidate', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Package', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Requested', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $pending_requests as $request ) : ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $request->first_name . ' ' . $request->last_name ); ?></strong><br>
										<small><?php echo esc_html( $request->email ); ?></small>
									</td>
									<td><?php echo esc_html( ucfirst( $request->check_type ) ); ?></td>
									<td><?php echo esc_html( ucfirst( $request->package_name ) ); ?></td>
									<td>
										<span class="status-badge status-<?php echo esc_attr( $request->request_status ); ?>">
											<?php echo esc_html( ucwords( str_replace( '_', ' ', $request->request_status ) ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( human_time_diff( strtotime( $request->created_at ), time() ) . ' ago' ); ?></td>
									<td>
										<?php if ( 'pending' === $request->request_status ) : ?>
											<button type="button" class="button button-primary send-invitation" data-request-id="<?php echo esc_attr( $request->id ); ?>">
												<?php esc_html_e( 'Send Consent Invitation', 'nonprofitsuite' ); ?>
											</button>
										<?php else : ?>
											<?php esc_html_e( 'Invitation sent', 'nonprofitsuite' ); ?>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- Review Needed Tab -->
	<div id="review-needed" class="tab-content" style="display:none;">
		<div class="postbox">
			<div class="postbox-header">
				<h2><?php esc_html_e( 'Review Needed', 'nonprofitsuite' ); ?></h2>
			</div>
			<div class="inside">
				<p class="description">
					<?php esc_html_e( 'These checks are complete and need review. Approve or initiate adverse action as appropriate.', 'nonprofitsuite' ); ?>
				</p>

				<?php if ( empty( $review_needed ) ) : ?>
					<p><?php esc_html_e( 'No checks pending review.', 'nonprofitsuite' ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Candidate', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Result', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Completed', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $review_needed as $request ) : ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $request->first_name . ' ' . $request->last_name ); ?></strong><br>
										<small><?php echo esc_html( $request->email ); ?></small>
									</td>
									<td><?php echo esc_html( ucfirst( $request->check_type ) ); ?></td>
									<td>
										<span class="result-badge result-<?php echo esc_attr( $request->overall_status ); ?>">
											<?php echo esc_html( ucfirst( $request->overall_status ?: 'Pending' ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( human_time_diff( strtotime( $request->completed_at ), time() ) . ' ago' ); ?></td>
									<td>
										<button type="button" class="button button-primary approve-candidate" data-request-id="<?php echo esc_attr( $request->id ); ?>">
											<?php esc_html_e( 'Approve', 'nonprofitsuite' ); ?>
										</button>
										<button type="button" class="button adverse-action" data-request-id="<?php echo esc_attr( $request->id ); ?>">
											<?php esc_html_e( 'Adverse Action', 'nonprofitsuite' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- Compliance Report Tab -->
	<div id="compliance" class="tab-content" style="display:none;">
		<div class="postbox">
			<div class="postbox-header">
				<h2><?php esc_html_e( 'Compliance Report', 'nonprofitsuite' ); ?></h2>
			</div>
			<div class="inside">
				<p class="description">
					<?php esc_html_e( 'Generate compliance reports for audits and regulatory requirements.', 'nonprofitsuite' ); ?>
				</p>

				<?php
				// Get stats for current month
				$start = date( 'Y-m-01' );
				$end   = date( 'Y-m-t' );

				$total_checks = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM $requests_table WHERE created_at BETWEEN %s AND %s",
						$start,
						$end
					)
				);

				$completed_checks = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM $requests_table WHERE request_status = 'completed' AND created_at BETWEEN %s AND %s",
						$start,
						$end
					)
				);

				$adverse_actions = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM $requests_table WHERE adjudication IN ('pre_adverse', 'adverse') AND created_at BETWEEN %s AND %s",
						$start,
						$end
					)
				);

				$total_cost = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT SUM(cost) FROM $requests_table WHERE created_at BETWEEN %s AND %s",
						$start,
						$end
					)
				);
				?>

				<div class="compliance-stats">
					<div class="stat-box">
						<h3><?php echo esc_html( $total_checks ); ?></h3>
						<p><?php esc_html_e( 'Total Checks This Month', 'nonprofitsuite' ); ?></p>
					</div>

					<div class="stat-box">
						<h3><?php echo esc_html( $completed_checks ); ?></h3>
						<p><?php esc_html_e( 'Completed Checks', 'nonprofitsuite' ); ?></p>
					</div>

					<div class="stat-box">
						<h3><?php echo esc_html( $adverse_actions ); ?></h3>
						<p><?php esc_html_e( 'Adverse Actions', 'nonprofitsuite' ); ?></p>
					</div>

					<div class="stat-box">
						<h3>$<?php echo number_format( $total_cost, 2 ); ?></h3>
						<p><?php esc_html_e( 'Total Cost', 'nonprofitsuite' ); ?></p>
					</div>
				</div>

				<h3><?php esc_html_e( 'FCRA Compliance Checklist', 'nonprofitsuite' ); ?></h3>
				<ul style="list-style: disc; margin-left: 20px;">
					<li>✓ <?php esc_html_e( 'Disclosure provided to all candidates', 'nonprofitsuite' ); ?></li>
					<li>✓ <?php esc_html_e( 'Written authorization obtained before ordering', 'nonprofitsuite' ); ?></li>
					<li>✓ <?php esc_html_e( 'Consent tracked with IP addresses', 'nonprofitsuite' ); ?></li>
					<li>✓ <?php esc_html_e( 'Pre-adverse action notices sent when required', 'nonprofitsuite' ); ?></li>
					<li>✓ <?php esc_html_e( '7-day dispute period provided', 'nonprofitsuite' ); ?></li>
					<li>✓ <?php esc_html_e( 'Final adverse action notices documented', 'nonprofitsuite' ); ?></li>
				</ul>
			</div>
		</div>
	</div>
</div>

<style>
.tab-content {
	margin-top: 20px;
}

.status-badge {
	display: inline-block;
	padding: 4px 8px;
	border-radius: 3px;
	font-size: 0.9em;
	font-weight: bold;
}

.status-pending {
	background: #f0f0f1;
	color: #646970;
}

.status-sent {
	background: #e5f6fd;
	color: #0073aa;
}

.status-in_progress {
	background: #fff4ce;
	color: #826200;
}

.status-completed {
	background: #d4f4dd;
	color: #0e6245;
}

.status-cancelled {
	background: #ffe4e1;
	color: #a00;
}

.result-badge {
	display: inline-block;
	padding: 4px 8px;
	border-radius: 3px;
	font-size: 0.9em;
	font-weight: bold;
}

.result-clear {
	background: #d4f4dd;
	color: #0e6245;
}

.result-consider {
	background: #fff4ce;
	color: #826200;
}

.result-suspended {
	background: #ffe4e1;
	color: #a00;
}

.progress-bar {
	width: 100px;
	height: 10px;
	background: #f0f0f1;
	border-radius: 5px;
	overflow: hidden;
	display: inline-block;
	margin-right: 5px;
}

.progress-fill {
	height: 100%;
	background: #2271b1;
	transition: width 0.3s;
}

.compliance-stats {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 15px;
	margin-bottom: 20px;
}

.stat-box {
	background: #f9f9f9;
	padding: 20px;
	text-align: center;
	border-radius: 3px;
	border: 1px solid #ddd;
}

.stat-box h3 {
	font-size: 2em;
	margin: 0 0 10px 0;
	color: #2271b1;
}

.stat-box p {
	margin: 0;
	color: #646970;
}
</style>

<script>
jQuery(document).ready(function($) {
	// Tab switching
	$('.nav-tab').on('click', function(e) {
		e.preventDefault();
		const target = $(this).attr('href');

		$('.nav-tab').removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');

		$('.tab-content').hide();
		$(target).show();
	});

	// Send Invitation
	$('.send-invitation').on('click', function() {
		const requestId = $(this).data('request-id');

		if (!confirm('Send consent invitation to candidate?')) {
			return;
		}

		const button = $(this);
		button.prop('disabled', true).text('Sending...');

		$.ajax({
			url: nsBackgroundCheck.ajaxUrl,
			method: 'POST',
			data: {
				action: 'ns_background_check_send_invitation',
				nonce: nsBackgroundCheck.nonce,
				request_id: requestId
			},
			success: function(response) {
				if (response.success) {
					alert('Consent invitation sent successfully!');
					location.reload();
				} else {
					alert('Error: ' + response.data.message);
					button.prop('disabled', false).text('Send Invitation');
				}
			}
		});
	});

	// Refresh Status
	$('.refresh-status').on('click', function() {
		const requestId = $(this).data('request-id');
		const button = $(this);

		button.prop('disabled', true).text('Refreshing...');

		$.ajax({
			url: nsBackgroundCheck.ajaxUrl,
			method: 'POST',
			data: {
				action: 'ns_background_check_get_status',
				nonce: nsBackgroundCheck.nonce,
				request_id: requestId
			},
			success: function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert('Error: ' + response.data.message);
				}
				button.prop('disabled', false).text('Refresh');
			}
		});
	});

	// Approve Candidate
	$('.approve-candidate').on('click', function() {
		const requestId = $(this).data('request-id');
		const notes = prompt('Review notes (optional):');

		if (notes === null) return; // Cancelled

		const button = $(this);
		button.prop('disabled', true).text('Approving...');

		$.ajax({
			url: nsBackgroundCheck.ajaxUrl,
			method: 'POST',
			data: {
				action: 'ns_background_check_approve',
				nonce: nsBackgroundCheck.nonce,
				request_id: requestId,
				notes: notes
			},
			success: function(response) {
				if (response.success) {
					alert('Candidate approved successfully!');
					location.reload();
				} else {
					alert('Error: ' + response.data.message);
					button.prop('disabled', false).text('Approve');
				}
			}
		});
	});

	// Adverse Action
	$('.adverse-action').on('click', function() {
		const requestId = $(this).data('request-id');

		const reason = prompt('Reason for adverse action:');
		if (!reason) return;

		const preAdverse = confirm('Is this a PRE-adverse action notice? (Click OK for pre-adverse, Cancel for final adverse)');

		const button = $(this);
		button.prop('disabled', true).text('Processing...');

		$.ajax({
			url: nsBackgroundCheck.ajaxUrl,
			method: 'POST',
			data: {
				action: 'ns_background_check_adverse_action',
				nonce: nsBackgroundCheck.nonce,
				request_id: requestId,
				reason: reason,
				pre_adverse: preAdverse ? 1 : 0
			},
			success: function(response) {
				if (response.success) {
					alert('Adverse action initiated. Candidate will be notified.');
					location.reload();
				} else {
					alert('Error: ' + response.data.message);
					button.prop('disabled', false).text('Adverse Action');
				}
			}
		});
	});
});
</script>
