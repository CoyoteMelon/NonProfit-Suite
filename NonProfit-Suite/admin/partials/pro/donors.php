<?php
/**
 * Donors (PRO) View - Full Featured Interface
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Get data
$donors = NonprofitSuite_Donors::get_donors( array( 'limit' => 100 ) );
$dashboard_data = NonprofitSuite_Donors::get_dashboard_data();

// Get filter parameters
$filter_level = isset( $_GET['filter_level'] ) ? sanitize_text_field( $_GET['filter_level'] ) : '';
$filter_type = isset( $_GET['filter_type'] ) ? sanitize_text_field( $_GET['filter_type'] ) : '';
$filter_status = isset( $_GET['filter_status'] ) ? sanitize_text_field( $_GET['filter_status'] ) : '';
$search = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';

// Active tab
$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'dashboard';
$donor_id = isset( $_GET['donor_id'] ) ? intval( $_GET['donor_id'] ) : 0;

// If viewing a specific donor
$donor_detail = null;
if ( $donor_id ) {
	$donor_detail = NonprofitSuite_Donors::get_donor( $donor_id );
	$donor_donations = NonprofitSuite_Donors::get_donor_donations( $donor_id );
	$donor_pledges = NonprofitSuite_Donors::get_donor_pledges( $donor_id );
}
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Donor Management', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<?php if ( ! $donor_id ) : ?>
		<!-- Quick Actions -->
		<div class="ns-actions-bar">
			<button class="ns-button ns-button-primary" id="ns-add-donor">
				<span class="dashicons dashicons-plus-alt"></span>
				<?php esc_html_e( 'Add Donor', 'nonprofitsuite' ); ?>
			</button>
			<button class="ns-button ns-button-secondary" id="ns-record-donation">
				<span class="dashicons dashicons-money-alt"></span>
				<?php esc_html_e( 'Record Donation', 'nonprofitsuite' ); ?>
			</button>
			<button class="ns-button ns-button-secondary" id="ns-create-pledge">
				<span class="dashicons dashicons-calendar-alt"></span>
				<?php esc_html_e( 'Create Pledge', 'nonprofitsuite' ); ?>
			</button>
			<button class="ns-button ns-button-secondary" id="ns-export-donors">
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Export', 'nonprofitsuite' ); ?>
			</button>
		</div>

		<!-- Tab Navigation -->
		<nav class="ns-tabs">
			<a href="?page=nonprofitsuite-donors&tab=dashboard" class="ns-tab <?php echo $active_tab === 'dashboard' ? 'active' : ''; ?>">
				<?php esc_html_e( 'Dashboard', 'nonprofitsuite' ); ?>
			</a>
			<a href="?page=nonprofitsuite-donors&tab=donors" class="ns-tab <?php echo $active_tab === 'donors' ? 'active' : ''; ?>">
				<?php esc_html_e( 'All Donors', 'nonprofitsuite' ); ?>
			</a>
			<a href="?page=nonprofitsuite-donors&tab=donations" class="ns-tab <?php echo $active_tab === 'donations' ? 'active' : ''; ?>">
				<?php esc_html_e( 'Donations', 'nonprofitsuite' ); ?>
			</a>
			<a href="?page=nonprofitsuite-donors&tab=pledges" class="ns-tab <?php echo $active_tab === 'pledges' ? 'active' : ''; ?>">
				<?php esc_html_e( 'Pledges', 'nonprofitsuite' ); ?>
			</a>
			<a href="?page=nonprofitsuite-donors&tab=reports" class="ns-tab <?php echo $active_tab === 'reports' ? 'active' : ''; ?>">
				<?php esc_html_e( 'Reports', 'nonprofitsuite' ); ?>
			</a>
		</nav>

		<!-- Tab: Dashboard -->
		<?php if ( $active_tab === 'dashboard' ) : ?>
			<div class="ns-tab-content">
				<!-- Stats Cards -->
				<div class="ns-dashboard-grid">
					<div class="ns-stat-card">
						<div class="ns-stat-icon"><span class="dashicons dashicons-groups"></span></div>
						<div class="ns-stat-label"><?php esc_html_e( 'Total Donors', 'nonprofitsuite' ); ?></div>
						<div class="ns-stat-value"><?php echo number_format( $dashboard_data['total_donors'] ?? 0 ); ?></div>
					</div>
					<div class="ns-stat-card">
						<div class="ns-stat-icon"><span class="dashicons dashicons-money-alt"></span></div>
						<div class="ns-stat-label"><?php esc_html_e( 'Total Donations (YTD)', 'nonprofitsuite' ); ?></div>
						<div class="ns-stat-value success">$<?php echo number_format( $dashboard_data['total_donations'] ?? 0, 2 ); ?></div>
					</div>
					<div class="ns-stat-card">
						<div class="ns-stat-icon"><span class="dashicons dashicons-chart-area"></span></div>
						<div class="ns-stat-label"><?php esc_html_e( 'Average Gift', 'nonprofitsuite' ); ?></div>
						<div class="ns-stat-value">$<?php echo number_format( $dashboard_data['avg_donation'] ?? 0, 2 ); ?></div>
					</div>
					<div class="ns-stat-card">
						<div class="ns-stat-icon"><span class="dashicons dashicons-update"></span></div>
						<div class="ns-stat-label"><?php esc_html_e( 'Recurring Donors', 'nonprofitsuite' ); ?></div>
						<div class="ns-stat-value"><?php echo number_format( $dashboard_data['recurring_donors'] ?? 0 ); ?></div>
					</div>
				</div>

				<!-- Recent Donations -->
				<div class="ns-card">
					<div class="ns-card-header">
						<h2 class="ns-card-title"><?php esc_html_e( 'Recent Donations', 'nonprofitsuite' ); ?></h2>
						<a href="?page=nonprofitsuite-donors&tab=donations" class="ns-button-small"><?php esc_html_e( 'View All', 'nonprofitsuite' ); ?></a>
					</div>

					<?php
					$recent_donations = NonprofitSuite_Donors::get_recent_donations( 10 );
					if ( ! empty( $recent_donations ) && ! is_wp_error( $recent_donations ) ) : ?>
						<table class="ns-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Date', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Donor', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Amount', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Method', 'nonprofitsuite' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $recent_donations as $donation ) : ?>
									<tr>
										<td><?php echo esc_html( NonprofitSuite_Utilities::format_date( $donation->donation_date ) ); ?></td>
										<td>
											<a href="?page=nonprofitsuite-donors&donor_id=<?php echo esc_attr( $donation->donor_id ); ?>">
												<?php echo esc_html( $donation->donor_name ?? 'Donor #' . $donation->donor_id ); ?>
											</a>
										</td>
										<td><strong>$<?php echo number_format( $donation->amount, 2 ); ?></strong></td>
										<td><?php echo esc_html( ucfirst( $donation->donation_type ?? 'cash' ) ); ?></td>
										<td><?php echo esc_html( ucfirst( $donation->payment_method ?? 'N/A' ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="ns-empty-state"><?php esc_html_e( 'No donations recorded yet.', 'nonprofitsuite' ); ?></p>
					<?php endif; ?>
				</div>

				<!-- Top Donors -->
				<div class="ns-card">
					<div class="ns-card-header">
						<h2 class="ns-card-title"><?php esc_html_e( 'Top Donors (This Year)', 'nonprofitsuite' ); ?></h2>
					</div>

					<?php
					$top_donors = NonprofitSuite_Donors::get_top_donors( 10 );
					if ( ! empty( $top_donors ) && ! is_wp_error( $top_donors ) ) : ?>
						<table class="ns-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Donor', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Level', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Total Given (YTD)', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Lifetime Total', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $top_donors as $donor ) : ?>
									<tr>
										<td>
											<strong>
												<a href="?page=nonprofitsuite-donors&donor_id=<?php echo esc_attr( $donor->id ); ?>">
													<?php echo esc_html( $donor->donor_name ?? 'Donor #' . $donor->id ); ?>
												</a>
											</strong>
										</td>
										<td>
											<span class="ns-badge ns-badge-level-<?php echo esc_attr( $donor->donor_level ); ?>">
												<?php echo esc_html( ucfirst( $donor->donor_level ) ); ?>
											</span>
										</td>
										<td><strong>$<?php echo number_format( $donor->ytd_total ?? 0, 2 ); ?></strong></td>
										<td>$<?php echo number_format( $donor->total_donated, 2 ); ?></td>
										<td>
											<button class="ns-button-icon ns-view-donor" data-id="<?php echo esc_attr( $donor->id ); ?>">
												<span class="dashicons dashicons-visibility"></span>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="ns-empty-state"><?php esc_html_e( 'No donor data available yet.', 'nonprofitsuite' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<!-- Tab: All Donors -->
		<?php if ( $active_tab === 'donors' ) : ?>
			<div class="ns-tab-content">
				<div class="ns-card">
					<div class="ns-card-header">
						<h2 class="ns-card-title"><?php esc_html_e( 'All Donors', 'nonprofitsuite' ); ?></h2>
					</div>

					<!-- Filters -->
					<form method="get" class="ns-filters">
						<input type="hidden" name="page" value="nonprofitsuite-donors">
						<input type="hidden" name="tab" value="donors">

						<div class="ns-filters-row">
							<div class="ns-filter-group">
								<label><?php esc_html_e( 'Search', 'nonprofitsuite' ); ?></label>
								<input type="text" name="search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Name, email...', 'nonprofitsuite' ); ?>">
							</div>

							<div class="ns-filter-group">
								<label><?php esc_html_e( 'Donor Level', 'nonprofitsuite' ); ?></label>
								<select name="filter_level">
									<option value=""><?php esc_html_e( 'All Levels', 'nonprofitsuite' ); ?></option>
									<option value="bronze" <?php selected( $filter_level, 'bronze' ); ?>><?php esc_html_e( 'Bronze', 'nonprofitsuite' ); ?></option>
									<option value="silver" <?php selected( $filter_level, 'silver' ); ?>><?php esc_html_e( 'Silver', 'nonprofitsuite' ); ?></option>
									<option value="gold" <?php selected( $filter_level, 'gold' ); ?>><?php esc_html_e( 'Gold', 'nonprofitsuite' ); ?></option>
									<option value="platinum" <?php selected( $filter_level, 'platinum' ); ?>><?php esc_html_e( 'Platinum', 'nonprofitsuite' ); ?></option>
								</select>
							</div>

							<div class="ns-filter-group">
								<label><?php esc_html_e( 'Donor Type', 'nonprofitsuite' ); ?></label>
								<select name="filter_type">
									<option value=""><?php esc_html_e( 'All Types', 'nonprofitsuite' ); ?></option>
									<option value="individual" <?php selected( $filter_type, 'individual' ); ?>><?php esc_html_e( 'Individual', 'nonprofitsuite' ); ?></option>
									<option value="organization" <?php selected( $filter_type, 'organization' ); ?>><?php esc_html_e( 'Organization', 'nonprofitsuite' ); ?></option>
									<option value="foundation" <?php selected( $filter_type, 'foundation' ); ?>><?php esc_html_e( 'Foundation', 'nonprofitsuite' ); ?></option>
								</select>
							</div>

							<div class="ns-filter-group">
								<label><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></label>
								<select name="filter_status">
									<option value=""><?php esc_html_e( 'All Status', 'nonprofitsuite' ); ?></option>
									<option value="active" <?php selected( $filter_status, 'active' ); ?>><?php esc_html_e( 'Active', 'nonprofitsuite' ); ?></option>
									<option value="lapsed" <?php selected( $filter_status, 'lapsed' ); ?>><?php esc_html_e( 'Lapsed', 'nonprofitsuite' ); ?></option>
									<option value="inactive" <?php selected( $filter_status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'nonprofitsuite' ); ?></option>
								</select>
							</div>

							<div class="ns-filter-group">
								<button type="submit" class="ns-button ns-button-secondary"><?php esc_html_e( 'Apply', 'nonprofitsuite' ); ?></button>
								<a href="?page=nonprofitsuite-donors&tab=donors" class="ns-button ns-button-text"><?php esc_html_e( 'Clear', 'nonprofitsuite' ); ?></a>
							</div>
						</div>
					</form>

					<!-- Donors Table -->
					<?php if ( ! empty( $donors ) && ! is_wp_error( $donors ) ) : ?>
						<table class="ns-table ns-table-hover">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Donor', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Level', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Contact', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Total Donated', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Last Gift', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $donors as $donor ) : ?>
									<tr>
										<td>
											<strong>
												<a href="?page=nonprofitsuite-donors&donor_id=<?php echo esc_attr( $donor->id ); ?>">
													<?php echo esc_html( $donor->donor_name ?? 'Donor #' . $donor->id ); ?>
												</a>
											</strong>
										</td>
										<td><?php echo esc_html( ucfirst( $donor->donor_type ) ); ?></td>
										<td>
											<span class="ns-badge ns-badge-level-<?php echo esc_attr( $donor->donor_level ); ?>">
												<?php echo esc_html( ucfirst( $donor->donor_level ) ); ?>
											</span>
										</td>
										<td>
											<?php if ( ! empty( $donor->email ) ) : ?>
												<a href="mailto:<?php echo esc_attr( $donor->email ); ?>"><?php echo esc_html( $donor->email ); ?></a>
											<?php endif; ?>
										</td>
										<td><strong>$<?php echo number_format( $donor->total_donated, 2 ); ?></strong></td>
										<td><?php echo $donor->last_donation_date ? esc_html( NonprofitSuite_Utilities::format_date( $donor->last_donation_date ) ) : '—'; ?></td>
										<td><?php echo NonprofitSuite_Utilities::get_status_badge( $donor->donor_status ); ?></td>
										<td>
											<a href="?page=nonprofitsuite-donors&donor_id=<?php echo esc_attr( $donor->id ); ?>" class="ns-button-icon" title="<?php esc_attr_e( 'View Details', 'nonprofitsuite' ); ?>">
												<span class="dashicons dashicons-visibility"></span>
											</a>
											<button class="ns-button-icon ns-edit-donor" data-id="<?php echo esc_attr( $donor->id ); ?>" title="<?php esc_attr_e( 'Edit', 'nonprofitsuite' ); ?>">
												<span class="dashicons dashicons-edit"></span>
											</button>
											<button class="ns-button-icon ns-quick-donate" data-id="<?php echo esc_attr( $donor->id ); ?>" title="<?php esc_attr_e( 'Record Donation', 'nonprofitsuite' ); ?>">
												<span class="dashicons dashicons-money-alt"></span>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="ns-empty-state"><?php esc_html_e( 'No donors found. Add your first donor to get started!', 'nonprofitsuite' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<!-- Tab: Donations -->
		<?php if ( $active_tab === 'donations' ) : ?>
			<div class="ns-tab-content">
				<div class="ns-card">
					<div class="ns-card-header">
						<h2 class="ns-card-title"><?php esc_html_e( 'All Donations', 'nonprofitsuite' ); ?></h2>
						<button class="ns-button ns-button-primary" id="ns-record-donation-inline">
							<?php esc_html_e( 'Record Donation', 'nonprofitsuite' ); ?>
						</button>
					</div>

					<?php
					$all_donations = NonprofitSuite_Donors::get_recent_donations( 100 );
					if ( ! empty( $all_donations ) && ! is_wp_error( $all_donations ) ) : ?>
						<table class="ns-table ns-table-hover">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Date', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Donor', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Amount', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Method', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Campaign', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Tax Receipt', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $all_donations as $donation ) : ?>
									<tr>
										<td><?php echo esc_html( NonprofitSuite_Utilities::format_date( $donation->donation_date ) ); ?></td>
										<td>
											<a href="?page=nonprofitsuite-donors&donor_id=<?php echo esc_attr( $donation->donor_id ); ?>">
												<?php echo esc_html( $donation->donor_name ?? 'Donor #' . $donation->donor_id ); ?>
											</a>
										</td>
										<td><strong>$<?php echo number_format( $donation->amount, 2 ); ?></strong></td>
										<td><?php echo esc_html( ucfirst( $donation->donation_type ?? 'cash' ) ); ?></td>
										<td><?php echo esc_html( ucfirst( $donation->payment_method ?? 'N/A' ) ); ?></td>
										<td><?php echo esc_html( $donation->campaign ?? '—' ); ?></td>
										<td>
											<?php if ( $donation->tax_receipt_issued ) : ?>
												<span class="ns-badge ns-badge-success">✓ <?php esc_html_e( 'Issued', 'nonprofitsuite' ); ?></span>
											<?php else : ?>
												<button class="ns-button-small ns-issue-receipt" data-id="<?php echo esc_attr( $donation->id ); ?>">
													<?php esc_html_e( 'Issue Receipt', 'nonprofitsuite' ); ?>
												</button>
											<?php endif; ?>
										</td>
										<td>
											<button class="ns-button-icon ns-edit-donation" data-id="<?php echo esc_attr( $donation->id ); ?>">
												<span class="dashicons dashicons-edit"></span>
											</button>
											<button class="ns-button-icon ns-delete-donation" data-id="<?php echo esc_attr( $donation->id ); ?>">
												<span class="dashicons dashicons-trash"></span>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="ns-empty-state"><?php esc_html_e( 'No donations recorded yet.', 'nonprofitsuite' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<!-- Tab: Pledges -->
		<?php if ( $active_tab === 'pledges' ) : ?>
			<div class="ns-tab-content">
				<div class="ns-card">
					<div class="ns-card-header">
						<h2 class="ns-card-title"><?php esc_html_e( 'Pledges & Commitments', 'nonprofitsuite' ); ?></h2>
						<button class="ns-button ns-button-primary" id="ns-create-pledge-inline">
							<?php esc_html_e( 'Create Pledge', 'nonprofitsuite' ); ?>
						</button>
					</div>

					<p class="ns-help-text"><?php esc_html_e( 'Track donor pledges and multi-year commitments', 'nonprofitsuite' ); ?></p>

					<div class="ns-empty-state">
						<p><?php esc_html_e( 'No pledges tracked yet.', 'nonprofitsuite' ); ?></p>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<!-- Tab: Reports -->
		<?php if ( $active_tab === 'reports' ) : ?>
			<div class="ns-tab-content">
				<div class="ns-card">
					<div class="ns-card-header">
						<h2 class="ns-card-title"><?php esc_html_e( 'Donor Reports', 'nonprofitsuite' ); ?></h2>
					</div>

					<div class="ns-report-cards">
						<div class="ns-report-card">
							<h3><?php esc_html_e( 'Giving History Report', 'nonprofitsuite' ); ?></h3>
							<p><?php esc_html_e( 'Complete giving history by donor with totals', 'nonprofitsuite' ); ?></p>
							<button class="ns-button ns-button-primary"><?php esc_html_e( 'Generate', 'nonprofitsuite' ); ?></button>
						</div>

						<div class="ns-report-card">
							<h3><?php esc_html_e( 'Tax Receipt Summary', 'nonprofitsuite' ); ?></h3>
							<p><?php esc_html_e( 'Annual tax receipts for all donors', 'nonprofitsuite' ); ?></p>
							<button class="ns-button ns-button-primary"><?php esc_html_e( 'Generate', 'nonprofitsuite' ); ?></button>
						</div>

						<div class="ns-report-card">
							<h3><?php esc_html_e( 'Donor Retention Report', 'nonprofitsuite' ); ?></h3>
							<p><?php esc_html_e( 'Track donor retention and lapse rates', 'nonprofitsuite' ); ?></p>
							<button class="ns-button ns-button-primary"><?php esc_html_e( 'Generate', 'nonprofitsuite' ); ?></button>
						</div>

						<div class="ns-report-card">
							<h3><?php esc_html_e( 'Campaign Performance', 'nonprofitsuite' ); ?></h3>
							<p><?php esc_html_e( 'Compare fundraising campaigns and appeals', 'nonprofitsuite' ); ?></p>
							<button class="ns-button ns-button-primary"><?php esc_html_e( 'Generate', 'nonprofitsuite' ); ?></button>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>

	<?php else : ?>
		<!-- Donor Detail View -->
		<?php if ( $donor_detail ) : ?>
			<div class="ns-detail-view">
				<div class="ns-detail-header">
					<a href="?page=nonprofitsuite-donors&tab=donors" class="ns-button ns-button-text">
						<span class="dashicons dashicons-arrow-left-alt2"></span> <?php esc_html_e( 'Back to Donors', 'nonprofitsuite' ); ?>
					</a>
					<h2><?php echo esc_html( $donor_detail->donor_name ?? 'Donor #' . $donor_detail->id ); ?></h2>
					<div class="ns-detail-actions">
						<button class="ns-button ns-button-primary ns-quick-donate" data-id="<?php echo esc_attr( $donor_detail->id ); ?>">
							<?php esc_html_e( 'Record Donation', 'nonprofitsuite' ); ?>
						</button>
						<button class="ns-button ns-button-secondary ns-edit-donor" data-id="<?php echo esc_attr( $donor_detail->id ); ?>">
							<?php esc_html_e( 'Edit Donor', 'nonprofitsuite' ); ?>
						</button>
					</div>
				</div>

				<div class="ns-detail-grid">
					<!-- Donor Info Card -->
					<div class="ns-card">
						<h3><?php esc_html_e( 'Donor Information', 'nonprofitsuite' ); ?></h3>
						<table class="ns-detail-table">
							<tr>
								<th><?php esc_html_e( 'Type:', 'nonprofitsuite' ); ?></th>
								<td><?php echo esc_html( ucfirst( $donor_detail->donor_type ) ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Level:', 'nonprofitsuite' ); ?></th>
								<td>
									<span class="ns-badge ns-badge-level-<?php echo esc_attr( $donor_detail->donor_level ); ?>">
										<?php echo esc_html( ucfirst( $donor_detail->donor_level ) ); ?>
									</span>
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Email:', 'nonprofitsuite' ); ?></th>
								<td><?php echo $donor_detail->email ? '<a href="mailto:' . esc_attr( $donor_detail->email ) . '">' . esc_html( $donor_detail->email ) . '</a>' : '—'; ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Phone:', 'nonprofitsuite' ); ?></th>
								<td><?php echo esc_html( $donor_detail->phone ?? '—' ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Address:', 'nonprofitsuite' ); ?></th>
								<td><?php echo esc_html( $donor_detail->address ?? '—' ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Status:', 'nonprofitsuite' ); ?></th>
								<td><?php echo NonprofitSuite_Utilities::get_status_badge( $donor_detail->donor_status ); ?></td>
							</tr>
						</table>
					</div>

					<!-- Giving Summary -->
					<div class="ns-card">
						<h3><?php esc_html_e( 'Giving Summary', 'nonprofitsuite' ); ?></h3>
						<div class="ns-summary-stats">
							<div class="ns-summary-item">
								<div class="ns-summary-label"><?php esc_html_e( 'Lifetime Total', 'nonprofitsuite' ); ?></div>
								<div class="ns-summary-value">$<?php echo number_format( $donor_detail->total_donated, 2 ); ?></div>
							</div>
							<div class="ns-summary-item">
								<div class="ns-summary-label"><?php esc_html_e( 'This Year', 'nonprofitsuite' ); ?></div>
								<div class="ns-summary-value">$<?php echo number_format( $donor_detail->ytd_total ?? 0, 2 ); ?></div>
							</div>
							<div class="ns-summary-item">
								<div class="ns-summary-label"><?php esc_html_e( 'Number of Gifts', 'nonprofitsuite' ); ?></div>
								<div class="ns-summary-value"><?php echo number_format( $donor_detail->donation_count ?? 0 ); ?></div>
							</div>
							<div class="ns-summary-item">
								<div class="ns-summary-label"><?php esc_html_e( 'Last Gift', 'nonprofitsuite' ); ?></div>
								<div class="ns-summary-value">
									<?php echo $donor_detail->last_donation_date ? esc_html( NonprofitSuite_Utilities::format_date( $donor_detail->last_donation_date ) ) : '—'; ?>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Giving History -->
				<div class="ns-card">
					<h3><?php esc_html_e( 'Giving History', 'nonprofitsuite' ); ?></h3>
					<?php if ( ! empty( $donor_donations ) && ! is_wp_error( $donor_donations ) ) : ?>
						<table class="ns-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Date', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Amount', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Method', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Campaign', 'nonprofitsuite' ); ?></th>
									<th><?php esc_html_e( 'Tax Receipt', 'nonprofitsuite' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $donor_donations as $donation ) : ?>
									<tr>
										<td><?php echo esc_html( NonprofitSuite_Utilities::format_date( $donation->donation_date ) ); ?></td>
										<td><strong>$<?php echo number_format( $donation->amount, 2 ); ?></strong></td>
										<td><?php echo esc_html( ucfirst( $donation->donation_type ?? 'cash' ) ); ?></td>
										<td><?php echo esc_html( ucfirst( $donation->payment_method ?? 'N/A' ) ); ?></td>
										<td><?php echo esc_html( $donation->campaign ?? '—' ); ?></td>
										<td>
											<?php if ( $donation->tax_receipt_issued ) : ?>
												<span class="ns-badge ns-badge-success">✓ <?php esc_html_e( 'Issued', 'nonprofitsuite' ); ?></span>
											<?php else : ?>
												<button class="ns-button-small ns-issue-receipt" data-id="<?php echo esc_attr( $donation->id ); ?>">
													<?php esc_html_e( 'Issue Receipt', 'nonprofitsuite' ); ?>
												</button>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="ns-empty-state"><?php esc_html_e( 'No donations recorded for this donor yet.', 'nonprofitsuite' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		<?php else : ?>
			<div class="ns-card">
				<p class="ns-empty-state"><?php esc_html_e( 'Donor not found.', 'nonprofitsuite' ); ?></p>
				<a href="?page=nonprofitsuite-donors&tab=donors" class="ns-button ns-button-primary"><?php esc_html_e( 'Back to Donors', 'nonprofitsuite' ); ?></a>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>

<!-- Modal: Add/Edit Donor -->
<div id="ns-donor-modal" class="ns-modal" style="display:none;">
	<div class="ns-modal-content">
		<div class="ns-modal-header">
			<h2 id="ns-donor-modal-title"><?php esc_html_e( 'Add Donor', 'nonprofitsuite' ); ?></h2>
			<button class="ns-modal-close">&times;</button>
		</div>
		<form id="ns-donor-form">
			<input type="hidden" name="donor_id" id="donor-id">
			<input type="hidden" name="action" value="ns_save_donor">
			<input type="hidden" name="nonce" value="<?php echo wp_create_nonce( 'ns_donor_save' ); ?>">

			<div class="ns-form-row">
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Donor Name', 'nonprofitsuite' ); ?> <span class="required">*</span></label>
					<input type="text" name="donor_name" id="donor-name" required>
				</div>
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Donor Type', 'nonprofitsuite' ); ?> <span class="required">*</span></label>
					<select name="donor_type" id="donor-type" required>
						<option value="individual"><?php esc_html_e( 'Individual', 'nonprofitsuite' ); ?></option>
						<option value="organization"><?php esc_html_e( 'Organization', 'nonprofitsuite' ); ?></option>
						<option value="foundation"><?php esc_html_e( 'Foundation', 'nonprofitsuite' ); ?></option>
					</select>
				</div>
			</div>

			<div class="ns-form-row">
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Email', 'nonprofitsuite' ); ?></label>
					<input type="email" name="email" id="donor-email">
				</div>
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Phone', 'nonprofitsuite' ); ?></label>
					<input type="tel" name="phone" id="donor-phone">
				</div>
			</div>

			<div class="ns-form-group">
				<label><?php esc_html_e( 'Address', 'nonprofitsuite' ); ?></label>
				<textarea name="address" id="donor-address" rows="3"></textarea>
			</div>

			<div class="ns-form-row">
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Donor Level', 'nonprofitsuite' ); ?></label>
					<select name="donor_level" id="donor-level">
						<option value="bronze"><?php esc_html_e( 'Bronze', 'nonprofitsuite' ); ?></option>
						<option value="silver"><?php esc_html_e( 'Silver', 'nonprofitsuite' ); ?></option>
						<option value="gold"><?php esc_html_e( 'Gold', 'nonprofitsuite' ); ?></option>
						<option value="platinum"><?php esc_html_e( 'Platinum', 'nonprofitsuite' ); ?></option>
					</select>
				</div>
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></label>
					<select name="donor_status" id="donor-status">
						<option value="active"><?php esc_html_e( 'Active', 'nonprofitsuite' ); ?></option>
						<option value="lapsed"><?php esc_html_e( 'Lapsed', 'nonprofitsuite' ); ?></option>
						<option value="inactive"><?php esc_html_e( 'Inactive', 'nonprofitsuite' ); ?></option>
					</select>
				</div>
			</div>

			<div class="ns-modal-footer">
				<button type="button" class="ns-button ns-button-text ns-modal-close"><?php esc_html_e( 'Cancel', 'nonprofitsuite' ); ?></button>
				<button type="submit" class="ns-button ns-button-primary"><?php esc_html_e( 'Save Donor', 'nonprofitsuite' ); ?></button>
			</div>
		</form>
	</div>
</div>

<!-- Modal: Record Donation -->
<div id="ns-donation-modal" class="ns-modal" style="display:none;">
	<div class="ns-modal-content">
		<div class="ns-modal-header">
			<h2><?php esc_html_e( 'Record Donation', 'nonprofitsuite' ); ?></h2>
			<button class="ns-modal-close">&times;</button>
		</div>
		<form id="ns-donation-form">
			<input type="hidden" name="action" value="ns_save_donation">
			<input type="hidden" name="nonce" value="<?php echo wp_create_nonce( 'ns_donation_save' ); ?>">

			<div class="ns-form-row">
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Donor', 'nonprofitsuite' ); ?> <span class="required">*</span></label>
					<select name="donor_id" id="donation-donor" required>
						<option value=""><?php esc_html_e( 'Select Donor', 'nonprofitsuite' ); ?></option>
						<?php if ( ! empty( $donors ) ) : foreach ( $donors as $donor ) : ?>
							<option value="<?php echo esc_attr( $donor->id ); ?>">
								<?php echo esc_html( $donor->donor_name ?? 'Donor #' . $donor->id ); ?>
							</option>
						<?php endforeach; endif; ?>
					</select>
				</div>
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Donation Date', 'nonprofitsuite' ); ?> <span class="required">*</span></label>
					<input type="date" name="donation_date" id="donation-date" required>
				</div>
			</div>

			<div class="ns-form-row">
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Amount', 'nonprofitsuite' ); ?> <span class="required">*</span></label>
					<input type="number" step="0.01" name="amount" id="donation-amount" placeholder="0.00" required>
				</div>
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Payment Method', 'nonprofitsuite' ); ?></label>
					<select name="payment_method" id="donation-method">
						<option value="cash"><?php esc_html_e( 'Cash', 'nonprofitsuite' ); ?></option>
						<option value="check"><?php esc_html_e( 'Check', 'nonprofitsuite' ); ?></option>
						<option value="credit_card"><?php esc_html_e( 'Credit Card', 'nonprofitsuite' ); ?></option>
						<option value="ach"><?php esc_html_e( 'ACH/Bank Transfer', 'nonprofitsuite' ); ?></option>
						<option value="online"><?php esc_html_e( 'Online Payment', 'nonprofitsuite' ); ?></option>
						<option value="other"><?php esc_html_e( 'Other', 'nonprofitsuite' ); ?></option>
					</select>
				</div>
			</div>

			<div class="ns-form-row">
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Donation Type', 'nonprofitsuite' ); ?></label>
					<select name="donation_type" id="donation-type">
						<option value="cash"><?php esc_html_e( 'Cash', 'nonprofitsuite' ); ?></option>
						<option value="in-kind"><?php esc_html_e( 'In-Kind', 'nonprofitsuite' ); ?></option>
						<option value="stock"><?php esc_html_e( 'Stock/Securities', 'nonprofitsuite' ); ?></option>
						<option value="property"><?php esc_html_e( 'Property', 'nonprofitsuite' ); ?></option>
					</select>
				</div>
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Campaign', 'nonprofitsuite' ); ?></label>
					<input type="text" name="campaign" id="donation-campaign">
				</div>
			</div>

			<div class="ns-form-group">
				<label><?php esc_html_e( 'Notes', 'nonprofitsuite' ); ?></label>
				<textarea name="notes" id="donation-notes" rows="3"></textarea>
			</div>

			<div class="ns-modal-footer">
				<button type="button" class="ns-button ns-button-text ns-modal-close"><?php esc_html_e( 'Cancel', 'nonprofitsuite' ); ?></button>
				<button type="submit" class="ns-button ns-button-primary"><?php esc_html_e( 'Record Donation', 'nonprofitsuite' ); ?></button>
			</div>
		</form>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// Open donor modal
	$('#ns-add-donor, .ns-edit-donor').on('click', function() {
		$('#ns-donor-form')[0].reset();
		$('#ns-donor-modal').fadeIn();
	});

	// Open donation modal
	$('#ns-record-donation, #ns-record-donation-inline, .ns-quick-donate').on('click', function() {
		var donorId = $(this).data('id');
		$('#ns-donation-form')[0].reset();
		$('#donation-date').val(new Date().toISOString().split('T')[0]);
		if (donorId) {
			$('#donation-donor').val(donorId);
		}
		$('#ns-donation-modal').fadeIn();
	});

	// Close modals
	$('.ns-modal-close').on('click', function() {
		$(this).closest('.ns-modal').fadeOut();
	});

	$('.ns-modal').on('click', function(e) {
		if ($(e.target).hasClass('ns-modal')) {
			$(this).fadeOut();
		}
	});

	// Submit donor form
	$('#ns-donor-form').on('submit', function(e) {
		e.preventDefault();
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: $(this).serialize(),
			success: function(response) {
				if (response.success) {
					alert('<?php esc_html_e( 'Donor saved successfully!', 'nonprofitsuite' ); ?>');
					location.reload();
				} else {
					alert(response.data.message || '<?php esc_html_e( 'Error saving donor', 'nonprofitsuite' ); ?>');
				}
			}
		});
	});

	// Submit donation form
	$('#ns-donation-form').on('submit', function(e) {
		e.preventDefault();
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: $(this).serialize(),
			success: function(response) {
				if (response.success) {
					alert('<?php esc_html_e( 'Donation recorded successfully!', 'nonprofitsuite' ); ?>');
					location.reload();
				} else {
					alert(response.data.message || '<?php esc_html_e( 'Error recording donation', 'nonprofitsuite' ); ?>');
				}
			}
		});
	});

	// Export
	$('#ns-export-donors').on('click', function() {
		var format = prompt('<?php esc_html_e( 'Export format (csv/pdf/excel):', 'nonprofitsuite' ); ?>', 'csv');
		if (format) {
			window.location.href = '?page=nonprofitsuite-donors&action=export&format=' + format;
		}
	});

	// View donor details
	$('.ns-view-donor').on('click', function() {
		var donorId = $(this).data('id');
		window.location.href = '?page=nonprofitsuite-donors&donor_id=' + donorId;
	});
});
</script>

<style>
/* Reuse treasury.php styles + these additions */
.ns-badge-level-bronze { background: #cd7f32; color: #fff; }
.ns-badge-level-silver { background: #c0c0c0; color: #000; }
.ns-badge-level-gold { background: #ffd700; color: #000; }
.ns-badge-level-platinum { background: #e5e4e2; color: #000; border: 1px solid #ccc; }

.ns-detail-view { max-width: 1200px; }
.ns-detail-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.ns-detail-header h2 { margin: 0; font-size: 24px; }
.ns-detail-actions { display: flex; gap: 10px; }
.ns-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
.ns-detail-table { width: 100%; }
.ns-detail-table th { text-align: left; padding: 8px 0; color: #646970; font-weight: 600; width: 30%; }
.ns-detail-table td { padding: 8px 0; }

.ns-summary-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; }
.ns-summary-item { text-align: center; padding: 15px; background: #f6f7f7; border-radius: 4px; }
.ns-summary-label { font-size: 12px; color: #646970; margin-bottom: 8px; }
.ns-summary-value { font-size: 22px; font-weight: 700; color: #1d2327; }

@media (max-width: 768px) {
	.ns-detail-header { flex-direction: column; align-items: flex-start; gap: 10px; }
	.ns-detail-grid { grid-template-columns: 1fr; }
	.ns-summary-stats { grid-template-columns: 1fr 1fr; }
}
</style>
