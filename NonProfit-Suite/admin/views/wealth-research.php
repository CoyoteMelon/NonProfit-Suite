<?php
/**
 * Wealth Research View
 *
 * @package NonprofitSuite
 * @subpackage Admin/Views
 * @since 1.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$reports_table = $wpdb->prefix . 'ns_wealth_research_reports';

// Get recent reports
$recent_reports = $wpdb->get_results(
	"SELECT r.*, c.first_name, c.last_name, c.email
	FROM $reports_table r
	LEFT JOIN {$wpdb->prefix}ns_contacts c ON r.contact_id = c.id
	ORDER BY r.researched_at DESC
	LIMIT 50"
);

// Get contacts without research
$unresearched_contacts = $wpdb->get_results(
	"SELECT c.* FROM {$wpdb->prefix}ns_contacts c
	LEFT JOIN $reports_table r ON c.id = r.contact_id AND r.expires_at > NOW()
	WHERE r.id IS NULL
	ORDER BY c.created_at DESC
	LIMIT 100"
);

?>

<div class="wrap ns-wealth-research">
	<h1><?php esc_html_e( 'Wealth Research', 'nonprofitsuite' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Screen donors, identify major gift prospects, and track philanthropic capacity.', 'nonprofitsuite' ); ?>
	</p>

	<!-- Tabs -->
	<h2 class="nav-tab-wrapper">
		<a href="#research-history" class="nav-tab nav-tab-active"><?php esc_html_e( 'Research History', 'nonprofitsuite' ); ?></a>
		<a href="#batch-screening" class="nav-tab"><?php esc_html_e( 'Batch Screening', 'nonprofitsuite' ); ?></a>
		<a href="#prospects" class="nav-tab"><?php esc_html_e( 'Major Gift Prospects', 'nonprofitsuite' ); ?></a>
	</h2>

	<!-- Research History Tab -->
	<div id="research-history" class="tab-content active">
		<div class="postbox">
			<div class="postbox-header">
				<h2><?php esc_html_e( 'Recent Research', 'nonprofitsuite' ); ?></h2>
			</div>
			<div class="inside">
				<?php if ( empty( $recent_reports ) ) : ?>
					<p><?php esc_html_e( 'No research reports yet.', 'nonprofitsuite' ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Contact', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Provider', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Capacity', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Income Range', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Net Worth Range', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Researched', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Cost', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $recent_reports as $report ) : ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $report->first_name . ' ' . $report->last_name ); ?></strong><br>
										<small><?php echo esc_html( $report->email ); ?></small>
									</td>
									<td><?php echo esc_html( ucfirst( $report->provider ) ); ?></td>
									<td>
										<span class="capacity-badge capacity-<?php echo esc_attr( strtolower( str_replace( '+', 'plus', $report->giving_capacity_rating ) ) ); ?>">
											<?php echo esc_html( $report->giving_capacity_rating ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $report->estimated_income_range ?: '—' ); ?></td>
									<td><?php echo esc_html( $report->estimated_net_worth_range ?: '—' ); ?></td>
									<td><?php echo esc_html( human_time_diff( strtotime( $report->researched_at ), time() ) . ' ago' ); ?></td>
									<td>$<?php echo number_format( $report->cost, 2 ); ?></td>
									<td>
										<button type="button" class="button button-small view-report" data-report-id="<?php echo esc_attr( $report->id ); ?>">
											<?php esc_html_e( 'View', 'nonprofitsuite' ); ?>
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

	<!-- Batch Screening Tab -->
	<div id="batch-screening" class="tab-content" style="display:none;">
		<div class="postbox">
			<div class="postbox-header">
				<h2><?php esc_html_e( 'Batch Screen Contacts', 'nonprofitsuite' ); ?></h2>
			</div>
			<div class="inside">
				<p class="description">
					<?php esc_html_e( 'Select contacts to screen for wealth capacity. Contacts with existing recent research will be skipped.', 'nonprofitsuite' ); ?>
				</p>

				<?php if ( empty( $unresearched_contacts ) ) : ?>
					<p><?php esc_html_e( 'All contacts have been researched recently.', 'nonprofitsuite' ); ?></p>
				<?php else : ?>
					<form id="batch-screening-form">
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th class="check-column">
										<input type="checkbox" id="select-all-contacts">
									</th>
									<th><?php esc_html_e( 'Name', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Email', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Location', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Created', 'nonprofitsuite' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $unresearched_contacts as $contact ) : ?>
									<tr>
										<th class="check-column">
											<input type="checkbox" name="contact_ids[]" value="<?php echo esc_attr( $contact->id ); ?>">
										</th>
										<td><?php echo esc_html( $contact->first_name . ' ' . $contact->last_name ); ?></td>
										<td><?php echo esc_html( $contact->email ); ?></td>
										<td><?php echo esc_html( $contact->city . ', ' . $contact->state ); ?></td>
										<td><?php echo esc_html( human_time_diff( strtotime( $contact->created_at ), time() ) . ' ago' ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>

						<p class="submit">
							<button type="submit" class="button button-primary">
								<?php esc_html_e( 'Screen Selected Contacts', 'nonprofitsuite' ); ?>
							</button>
							<span id="batch-screening-status" style="margin-left: 10px;"></span>
						</p>
					</form>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- Major Gift Prospects Tab -->
	<div id="prospects" class="tab-content" style="display:none;">
		<div class="postbox">
			<div class="postbox-header">
				<h2><?php esc_html_e( 'Major Gift Prospects', 'nonprofitsuite' ); ?></h2>
			</div>
			<div class="inside">
				<p class="description">
					<?php esc_html_e( 'Contacts with high giving capacity ratings (A+, A, B).', 'nonprofitsuite' ); ?>
				</p>

				<?php
				$prospects = $wpdb->get_results(
					"SELECT r.*, c.first_name, c.last_name, c.email
					FROM $reports_table r
					LEFT JOIN {$wpdb->prefix}ns_contacts c ON r.contact_id = c.id
					WHERE r.giving_capacity_rating IN ('A+', 'A', 'B')
					AND r.expires_at > NOW()
					ORDER BY FIELD(r.giving_capacity_rating, 'A+', 'A', 'B'), r.researched_at DESC
					LIMIT 100"
				);
				?>

				<?php if ( empty( $prospects ) ) : ?>
					<p><?php esc_html_e( 'No major gift prospects identified yet. Screen more contacts to find high-capacity donors.', 'nonprofitsuite' ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Contact', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Capacity', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Estimated Giving Capacity', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Net Worth Range', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Researched', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $prospects as $prospect ) : ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $prospect->first_name . ' ' . $prospect->last_name ); ?></strong><br>
										<small><?php echo esc_html( $prospect->email ); ?></small>
									</td>
									<td>
										<span class="capacity-badge capacity-<?php echo esc_attr( strtolower( str_replace( '+', 'plus', $prospect->giving_capacity_rating ) ) ); ?>">
											<?php echo esc_html( $prospect->giving_capacity_rating ); ?>
										</span>
									</td>
									<td>
										<?php
										$capacity_ranges = array(
											'A+' => '$100K+',
											'A'  => '$50K-$100K',
											'B'  => '$10K-$50K',
										);
										echo esc_html( $capacity_ranges[ $prospect->giving_capacity_rating ] ?? '—' );
										?>
									</td>
									<td><?php echo esc_html( $prospect->estimated_net_worth_range ?: '—' ); ?></td>
									<td><?php echo esc_html( human_time_diff( strtotime( $prospect->researched_at ), time() ) . ' ago' ); ?></td>
									<td>
										<a href="<?php echo admin_url( 'admin.php?page=ns-contacts&action=view&id=' . $prospect->contact_id ); ?>" class="button button-small">
											<?php esc_html_e( 'View Contact', 'nonprofitsuite' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<style>
.tab-content {
	margin-top: 20px;
}

.capacity-badge {
	display: inline-block;
	padding: 4px 8px;
	border-radius: 3px;
	font-weight: bold;
	font-size: 0.9em;
}

.capacity-aplus {
	background: #d4f4dd;
	color: #0e6245;
}

.capacity-a {
	background: #e5f6fd;
	color: #0073aa;
}

.capacity-b {
	background: #fff4ce;
	color: #826200;
}

.capacity-c {
	background: #ffe4e1;
	color: #a00;
}

.capacity-d {
	background: #f0f0f1;
	color: #646970;
}

#batch-screening-status.processing {
	color: #2271b1;
}

#batch-screening-status.success {
	color: #46b450;
	font-weight: bold;
}

#batch-screening-status.error {
	color: #dc3232;
	font-weight: bold;
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

	// Select all contacts
	$('#select-all-contacts').on('change', function() {
		$('input[name="contact_ids[]"]').prop('checked', this.checked);
	});

	// Batch screening
	$('#batch-screening-form').on('submit', function(e) {
		e.preventDefault();

		const contactIds = [];
		$('input[name="contact_ids[]"]:checked').each(function() {
			contactIds.push($(this).val());
		});

		if (contactIds.length === 0) {
			alert('Please select at least one contact to screen.');
			return;
		}

		if (!confirm(`Screen ${contactIds.length} contacts? This will use your API quota.`)) {
			return;
		}

		const submitBtn = $(this).find('button[type="submit"]');
		const status = $('#batch-screening-status');

		submitBtn.prop('disabled', true).text('Screening...');
		status.removeClass('success error').addClass('processing')
			.text(`Screening ${contactIds.length} contacts...`);

		$.ajax({
			url: nsWealthResearch.ajaxUrl,
			method: 'POST',
			data: {
				action: 'ns_wealth_research_batch_screen',
				nonce: nsWealthResearch.nonce,
				contact_ids: contactIds
			},
			success: function(response) {
				if (response.success) {
					status.removeClass('processing').addClass('success')
						.text(response.data.summary);
					setTimeout(() => location.reload(), 2000);
				} else {
					status.removeClass('processing').addClass('error')
						.text('Error: ' + (response.data.message || 'Batch screening failed'));
				}
			},
			error: function() {
				status.removeClass('processing').addClass('error')
					.text('Error: Failed to complete batch screening');
			},
			complete: function() {
				submitBtn.prop('disabled', false).text('Screen Selected Contacts');
			}
		});
	});

	// View report (placeholder)
	$('.view-report').on('click', function() {
		const reportId = $(this).data('report-id');
		alert('View report #' + reportId + ' - Full report viewer coming soon!');
	});
});
</script>
