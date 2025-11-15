<?php
/**
 * Treasury (PRO) View - Full Featured Interface
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Get dashboard data
$dashboard_data = NonprofitSuite_Treasury::get_dashboard_data();
$accounts = NonprofitSuite_Treasury::get_accounts();
$recent_transactions = NonprofitSuite_Treasury::get_transactions( array( 'limit' => 20 ) );

// Get filter parameters
$filter_account = isset( $_GET['filter_account'] ) ? sanitize_text_field( $_GET['filter_account'] ) : '';
$filter_type = isset( $_GET['filter_type'] ) ? sanitize_text_field( $_GET['filter_type'] ) : '';
$filter_start_date = isset( $_GET['filter_start_date'] ) ? sanitize_text_field( $_GET['filter_start_date'] ) : '';
$filter_end_date = isset( $_GET['filter_end_date'] ) ? sanitize_text_field( $_GET['filter_end_date'] ) : '';
$search = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';

// Build filter args
$filter_args = array( 'limit' => 100 );
if ( $filter_account ) $filter_args['account_id'] = $filter_account;
if ( $filter_type ) $filter_args['transaction_type'] = $filter_type;
if ( $filter_start_date ) $filter_args['start_date'] = $filter_start_date;
if ( $filter_end_date ) $filter_args['end_date'] = $filter_end_date;
if ( $search ) $filter_args['search'] = $search;

$filtered_transactions = NonprofitSuite_Treasury::get_transactions( $filter_args );

// Active tab
$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'dashboard';
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Treasury', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<!-- Quick Actions -->
	<div class="ns-actions-bar">
		<button class="ns-button ns-button-primary" id="ns-add-transaction">
			<span class="dashicons dashicons-plus-alt"></span>
			<?php esc_html_e( 'Record Transaction', 'nonprofitsuite' ); ?>
		</button>
		<button class="ns-button ns-button-secondary" id="ns-add-account">
			<span class="dashicons dashicons-admin-settings"></span>
			<?php esc_html_e( 'Add Account', 'nonprofitsuite' ); ?>
		</button>
		<button class="ns-button ns-button-secondary" id="ns-reconcile">
			<span class="dashicons dashicons-yes-alt"></span>
			<?php esc_html_e( 'Reconcile', 'nonprofitsuite' ); ?>
		</button>
		<button class="ns-button ns-button-secondary" id="ns-export-treasury">
			<span class="dashicons dashicons-download"></span>
			<?php esc_html_e( 'Export', 'nonprofitsuite' ); ?>
		</button>
	</div>

	<!-- Tab Navigation -->
	<nav class="ns-tabs">
		<a href="?page=nonprofitsuite-treasury&tab=dashboard" class="ns-tab <?php echo $active_tab === 'dashboard' ? 'active' : ''; ?>">
			<?php esc_html_e( 'Dashboard', 'nonprofitsuite' ); ?>
		</a>
		<a href="?page=nonprofitsuite-treasury&tab=transactions" class="ns-tab <?php echo $active_tab === 'transactions' ? 'active' : ''; ?>">
			<?php esc_html_e( 'Transactions', 'nonprofitsuite' ); ?>
		</a>
		<a href="?page=nonprofitsuite-treasury&tab=accounts" class="ns-tab <?php echo $active_tab === 'accounts' ? 'active' : ''; ?>">
			<?php esc_html_e( 'Chart of Accounts', 'nonprofitsuite' ); ?>
		</a>
		<a href="?page=nonprofitsuite-treasury&tab=reports" class="ns-tab <?php echo $active_tab === 'reports' ? 'active' : ''; ?>">
			<?php esc_html_e( 'Financial Reports', 'nonprofitsuite' ); ?>
		</a>
		<a href="?page=nonprofitsuite-treasury&tab=reconciliation" class="ns-tab <?php echo $active_tab === 'reconciliation' ? 'active' : ''; ?>">
			<?php esc_html_e( 'Reconciliation', 'nonprofitsuite' ); ?>
		</a>
		<a href="?page=nonprofitsuite-treasury&tab=budgets" class="ns-tab <?php echo $active_tab === 'budgets' ? 'active' : ''; ?>">
			<?php esc_html_e( 'Budgets', 'nonprofitsuite' ); ?>
		</a>
	</nav>

	<!-- Tab: Dashboard -->
	<?php if ( $active_tab === 'dashboard' ) : ?>
		<div class="ns-tab-content">
			<!-- Financial Summary Cards -->
			<div class="ns-dashboard-grid">
				<div class="ns-stat-card">
					<div class="ns-stat-icon"><span class="dashicons dashicons-chart-line"></span></div>
					<div class="ns-stat-label"><?php esc_html_e( 'Total Assets', 'nonprofitsuite' ); ?></div>
					<div class="ns-stat-value">$<?php echo number_format( $dashboard_data['total_assets'] ?? 0, 2 ); ?></div>
				</div>
				<div class="ns-stat-card">
					<div class="ns-stat-icon"><span class="dashicons dashicons-arrow-down-alt"></span></div>
					<div class="ns-stat-label"><?php esc_html_e( 'Revenue (YTD)', 'nonprofitsuite' ); ?></div>
					<div class="ns-stat-value success">$<?php echo number_format( $dashboard_data['total_revenue'] ?? 0, 2 ); ?></div>
				</div>
				<div class="ns-stat-card">
					<div class="ns-stat-icon"><span class="dashicons dashicons-arrow-up-alt"></span></div>
					<div class="ns-stat-label"><?php esc_html_e( 'Expenses (YTD)', 'nonprofitsuite' ); ?></div>
					<div class="ns-stat-value warning">$<?php echo number_format( $dashboard_data['total_expenses'] ?? 0, 2 ); ?></div>
				</div>
				<div class="ns-stat-card">
					<div class="ns-stat-icon"><span class="dashicons dashicons-businessman"></span></div>
					<div class="ns-stat-label"><?php esc_html_e( 'Net Income (YTD)', 'nonprofitsuite' ); ?></div>
					<div class="ns-stat-value <?php echo ( $dashboard_data['net_income'] ?? 0 ) >= 0 ? 'success' : 'danger'; ?>">
						$<?php echo number_format( $dashboard_data['net_income'] ?? 0, 2 ); ?>
					</div>
				</div>
			</div>

			<!-- Recent Activity -->
			<div class="ns-card">
				<div class="ns-card-header">
					<h2 class="ns-card-title"><?php esc_html_e( 'Recent Transactions', 'nonprofitsuite' ); ?></h2>
					<a href="?page=nonprofitsuite-treasury&tab=transactions" class="ns-button-small"><?php esc_html_e( 'View All', 'nonprofitsuite' ); ?></a>
				</div>

				<?php if ( ! empty( $recent_transactions ) && ! is_wp_error( $recent_transactions ) ) : ?>
					<table class="ns-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Date', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Description', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Account', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
								<th class="ns-text-right"><?php esc_html_e( 'Amount', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( array_slice( $recent_transactions, 0, 10 ) as $trans ) : ?>
								<tr>
									<td><?php echo esc_html( NonprofitSuite_Utilities::format_date( $trans->transaction_date ) ); ?></td>
									<td><?php echo esc_html( $trans->description ); ?></td>
									<td><?php echo esc_html( $trans->account_name ?? '' ); ?></td>
									<td>
										<span class="ns-badge ns-badge-<?php echo esc_attr( $trans->transaction_type ); ?>">
											<?php echo esc_html( ucfirst( $trans->transaction_type ) ); ?>
										</span>
									</td>
									<td class="ns-text-right">
										<strong class="ns-amount-<?php echo $trans->transaction_type === 'income' ? 'positive' : 'negative'; ?>">
											$<?php echo number_format( $trans->amount, 2 ); ?>
										</strong>
									</td>
									<td>
										<button class="ns-button-icon ns-edit-transaction" data-id="<?php echo esc_attr( $trans->id ); ?>" title="<?php esc_attr_e( 'Edit', 'nonprofitsuite' ); ?>">
											<span class="dashicons dashicons-edit"></span>
										</button>
										<button class="ns-button-icon ns-delete-transaction" data-id="<?php echo esc_attr( $trans->id ); ?>" title="<?php esc_attr_e( 'Delete', 'nonprofitsuite' ); ?>">
											<span class="dashicons dashicons-trash"></span>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="ns-empty-state"><?php esc_html_e( 'No transactions recorded yet. Click "Record Transaction" to get started!', 'nonprofitsuite' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<!-- Tab: Transactions -->
	<?php if ( $active_tab === 'transactions' ) : ?>
		<div class="ns-tab-content">
			<div class="ns-card">
				<div class="ns-card-header">
					<h2 class="ns-card-title"><?php esc_html_e( 'All Transactions', 'nonprofitsuite' ); ?></h2>
				</div>

				<!-- Filters -->
				<form method="get" class="ns-filters">
					<input type="hidden" name="page" value="nonprofitsuite-treasury">
					<input type="hidden" name="tab" value="transactions">

					<div class="ns-filters-row">
						<div class="ns-filter-group">
							<label><?php esc_html_e( 'Search', 'nonprofitsuite' ); ?></label>
							<input type="text" name="search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Description, payee...', 'nonprofitsuite' ); ?>">
						</div>

						<div class="ns-filter-group">
							<label><?php esc_html_e( 'Account', 'nonprofitsuite' ); ?></label>
							<select name="filter_account">
								<option value=""><?php esc_html_e( 'All Accounts', 'nonprofitsuite' ); ?></option>
								<?php if ( ! empty( $accounts ) ) : foreach ( $accounts as $account ) : ?>
									<option value="<?php echo esc_attr( $account->id ); ?>" <?php selected( $filter_account, $account->id ); ?>>
										<?php echo esc_html( $account->account_number . ' - ' . $account->account_name ); ?>
									</option>
								<?php endforeach; endif; ?>
							</select>
						</div>

						<div class="ns-filter-group">
							<label><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></label>
							<select name="filter_type">
								<option value=""><?php esc_html_e( 'All Types', 'nonprofitsuite' ); ?></option>
								<option value="income" <?php selected( $filter_type, 'income' ); ?>><?php esc_html_e( 'Income', 'nonprofitsuite' ); ?></option>
								<option value="expense" <?php selected( $filter_type, 'expense' ); ?>><?php esc_html_e( 'Expense', 'nonprofitsuite' ); ?></option>
								<option value="transfer" <?php selected( $filter_type, 'transfer' ); ?>><?php esc_html_e( 'Transfer', 'nonprofitsuite' ); ?></option>
							</select>
						</div>

						<div class="ns-filter-group">
							<label><?php esc_html_e( 'Start Date', 'nonprofitsuite' ); ?></label>
							<input type="date" name="filter_start_date" value="<?php echo esc_attr( $filter_start_date ); ?>">
						</div>

						<div class="ns-filter-group">
							<label><?php esc_html_e( 'End Date', 'nonprofitsuite' ); ?></label>
							<input type="date" name="filter_end_date" value="<?php echo esc_attr( $filter_end_date ); ?>">
						</div>

						<div class="ns-filter-group">
							<button type="submit" class="ns-button ns-button-secondary"><?php esc_html_e( 'Apply Filters', 'nonprofitsuite' ); ?></button>
							<a href="?page=nonprofitsuite-treasury&tab=transactions" class="ns-button ns-button-text"><?php esc_html_e( 'Clear', 'nonprofitsuite' ); ?></a>
						</div>
					</div>
				</form>

				<!-- Transactions Table -->
				<?php if ( ! empty( $filtered_transactions ) && ! is_wp_error( $filtered_transactions ) ) : ?>
					<table class="ns-table ns-table-hover">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Date', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Description', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Account', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Category', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
								<th class="ns-text-right"><?php esc_html_e( 'Amount', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $filtered_transactions as $trans ) : ?>
								<tr>
									<td><?php echo esc_html( NonprofitSuite_Utilities::format_date( $trans->transaction_date ) ); ?></td>
									<td>
										<strong><?php echo esc_html( $trans->description ); ?></strong>
										<?php if ( ! empty( $trans->payee ) ) : ?>
											<br><small><?php esc_html_e( 'Payee:', 'nonprofitsuite' ); ?> <?php echo esc_html( $trans->payee ); ?></small>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $trans->account_name ?? '' ); ?></td>
									<td><?php echo esc_html( $trans->category ?? '' ); ?></td>
									<td>
										<span class="ns-badge ns-badge-<?php echo esc_attr( $trans->transaction_type ); ?>">
											<?php echo esc_html( ucfirst( $trans->transaction_type ) ); ?>
										</span>
									</td>
									<td class="ns-text-right">
										<strong class="ns-amount-<?php echo $trans->transaction_type === 'income' ? 'positive' : 'negative'; ?>">
											$<?php echo number_format( $trans->amount, 2 ); ?>
										</strong>
									</td>
									<td>
										<button class="ns-button-icon ns-edit-transaction" data-id="<?php echo esc_attr( $trans->id ); ?>">
											<span class="dashicons dashicons-edit"></span>
										</button>
										<button class="ns-button-icon ns-view-transaction" data-id="<?php echo esc_attr( $trans->id ); ?>">
											<span class="dashicons dashicons-visibility"></span>
										</button>
										<button class="ns-button-icon ns-delete-transaction" data-id="<?php echo esc_attr( $trans->id ); ?>">
											<span class="dashicons dashicons-trash"></span>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="ns-empty-state"><?php esc_html_e( 'No transactions found matching your filters.', 'nonprofitsuite' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<!-- Tab: Chart of Accounts -->
	<?php if ( $active_tab === 'accounts' ) : ?>
		<div class="ns-tab-content">
			<div class="ns-card">
				<div class="ns-card-header">
					<h2 class="ns-card-title"><?php esc_html_e( 'Chart of Accounts', 'nonprofitsuite' ); ?></h2>
					<button class="ns-button ns-button-primary" id="ns-add-account">
						<?php esc_html_e( 'Add Account', 'nonprofitsuite' ); ?>
					</button>
				</div>

				<?php if ( ! empty( $accounts ) && ! is_wp_error( $accounts ) ) : ?>
					<table class="ns-table ns-table-striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Account #', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Account Name', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Category', 'nonprofitsuite' ); ?></th>
								<th class="ns-text-right"><?php esc_html_e( 'Balance', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $accounts as $account ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $account->account_number ); ?></strong></td>
									<td><?php echo esc_html( $account->account_name ); ?></td>
									<td><?php echo esc_html( ucfirst( $account->account_type ) ); ?></td>
									<td><?php echo esc_html( $account->category ?? '' ); ?></td>
									<td class="ns-text-right">
										<strong>$<?php echo number_format( $account->balance, 2 ); ?></strong>
									</td>
									<td>
										<?php if ( $account->is_active ) : ?>
											<span class="ns-badge ns-badge-success"><?php esc_html_e( 'Active', 'nonprofitsuite' ); ?></span>
										<?php else : ?>
											<span class="ns-badge ns-badge-inactive"><?php esc_html_e( 'Inactive', 'nonprofitsuite' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<button class="ns-button-icon ns-edit-account" data-id="<?php echo esc_attr( $account->id ); ?>">
											<span class="dashicons dashicons-edit"></span>
										</button>
										<button class="ns-button-icon ns-view-account-ledger" data-id="<?php echo esc_attr( $account->id ); ?>">
											<span class="dashicons dashicons-book"></span>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<div class="ns-empty-state">
						<p><?php esc_html_e( 'No accounts found. Initialize your chart of accounts to get started.', 'nonprofitsuite' ); ?></p>
						<button class="ns-button ns-button-primary" id="ns-init-accounts">
							<?php esc_html_e( 'Initialize Chart of Accounts', 'nonprofitsuite' ); ?>
						</button>
					</div>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<!-- Tab: Financial Reports -->
	<?php if ( $active_tab === 'reports' ) : ?>
		<div class="ns-tab-content">
			<div class="ns-card">
				<div class="ns-card-header">
					<h2 class="ns-card-title"><?php esc_html_e( 'Financial Reports', 'nonprofitsuite' ); ?></h2>
				</div>

				<div class="ns-report-cards">
					<div class="ns-report-card">
						<h3><?php esc_html_e( 'Income Statement (P&L)', 'nonprofitsuite' ); ?></h3>
						<p><?php esc_html_e( 'Statement of revenues and expenses for a given period', 'nonprofitsuite' ); ?></p>
						<button class="ns-button ns-button-primary ns-generate-report" data-report="income-statement">
							<?php esc_html_e( 'Generate Report', 'nonprofitsuite' ); ?>
						</button>
					</div>

					<div class="ns-report-card">
						<h3><?php esc_html_e( 'Balance Sheet', 'nonprofitsuite' ); ?></h3>
						<p><?php esc_html_e( 'Statement of assets, liabilities, and net assets', 'nonprofitsuite' ); ?></p>
						<button class="ns-button ns-button-primary ns-generate-report" data-report="balance-sheet">
							<?php esc_html_e( 'Generate Report', 'nonprofitsuite' ); ?>
						</button>
					</div>

					<div class="ns-report-card">
						<h3><?php esc_html_e( 'Cash Flow Statement', 'nonprofitsuite' ); ?></h3>
						<p><?php esc_html_e( 'Statement of cash receipts and disbursements', 'nonprofitsuite' ); ?></p>
						<button class="ns-button ns-button-primary ns-generate-report" data-report="cash-flow">
							<?php esc_html_e( 'Generate Report', 'nonprofitsuite' ); ?>
						</button>
					</div>

					<div class="ns-report-card">
						<h3><?php esc_html_e( 'Budget vs Actual', 'nonprofitsuite' ); ?></h3>
						<p><?php esc_html_e( 'Compare budgeted amounts to actual performance', 'nonprofitsuite' ); ?></p>
						<button class="ns-button ns-button-primary ns-generate-report" data-report="budget-actual">
							<?php esc_html_e( 'Generate Report', 'nonprofitsuite' ); ?>
						</button>
					</div>

					<div class="ns-report-card">
						<h3><?php esc_html_e( 'General Ledger', 'nonprofitsuite' ); ?></h3>
						<p><?php esc_html_e( 'Complete transaction history by account', 'nonprofitsuite' ); ?></p>
						<button class="ns-button ns-button-primary ns-generate-report" data-report="general-ledger">
							<?php esc_html_e( 'Generate Report', 'nonprofitsuite' ); ?>
						</button>
					</div>

					<div class="ns-report-card">
						<h3><?php esc_html_e( 'Functional Expenses (990)', 'nonprofitsuite' ); ?></h3>
						<p><?php esc_html_e( 'Expense breakdown for Form 990 Part IX', 'nonprofitsuite' ); ?></p>
						<button class="ns-button ns-button-primary ns-generate-report" data-report="functional-expenses">
							<?php esc_html_e( 'Generate Report', 'nonprofitsuite' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<!-- Tab: Reconciliation -->
	<?php if ( $active_tab === 'reconciliation' ) : ?>
		<div class="ns-tab-content">
			<div class="ns-card">
				<div class="ns-card-header">
					<h2 class="ns-card-title"><?php esc_html_e( 'Bank Reconciliation', 'nonprofitsuite' ); ?></h2>
				</div>

				<div class="ns-reconcile-wizard">
					<div class="ns-step">
						<h3><?php esc_html_e( 'Step 1: Select Account', 'nonprofitsuite' ); ?></h3>
						<select id="ns-reconcile-account" class="ns-input-large">
							<option value=""><?php esc_html_e( 'Select Account to Reconcile', 'nonprofitsuite' ); ?></option>
							<?php if ( ! empty( $accounts ) ) : foreach ( $accounts as $account ) : ?>
								<option value="<?php echo esc_attr( $account->id ); ?>">
									<?php echo esc_html( $account->account_number . ' - ' . $account->account_name ); ?>
								</option>
							<?php endforeach; endif; ?>
						</select>
					</div>

					<div class="ns-step">
						<h3><?php esc_html_e( 'Step 2: Enter Bank Statement Info', 'nonprofitsuite' ); ?></h3>
						<div class="ns-form-row">
							<div class="ns-form-group">
								<label><?php esc_html_e( 'Statement Date', 'nonprofitsuite' ); ?></label>
								<input type="date" id="ns-statement-date" class="ns-input">
							</div>
							<div class="ns-form-group">
								<label><?php esc_html_e( 'Statement Ending Balance', 'nonprofitsuite' ); ?></label>
								<input type="number" step="0.01" id="ns-statement-balance" class="ns-input" placeholder="0.00">
							</div>
						</div>
					</div>

					<div class="ns-step">
						<h3><?php esc_html_e( 'Step 3: Mark Cleared Transactions', 'nonprofitsuite' ); ?></h3>
						<p class="ns-help-text"><?php esc_html_e( 'Check off transactions that appear on your bank statement', 'nonprofitsuite' ); ?></p>
						<div id="ns-reconcile-transactions-list">
							<p class="ns-empty-state"><?php esc_html_e( 'Select an account to view unreconciled transactions', 'nonprofitsuite' ); ?></p>
						</div>
					</div>

					<div class="ns-step">
						<button class="ns-button ns-button-primary ns-button-large" id="ns-complete-reconciliation">
							<?php esc_html_e( 'Complete Reconciliation', 'nonprofitsuite' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<!-- Tab: Budgets -->
	<?php if ( $active_tab === 'budgets' ) : ?>
		<div class="ns-tab-content">
			<div class="ns-card">
				<div class="ns-card-header">
					<h2 class="ns-card-title"><?php esc_html_e( 'Budget Management', 'nonprofitsuite' ); ?></h2>
					<button class="ns-button ns-button-primary" id="ns-add-budget">
						<?php esc_html_e( 'Create Budget', 'nonprofitsuite' ); ?>
					</button>
				</div>

				<p class="ns-help-text"><?php esc_html_e( 'Create and manage budgets to track planned vs actual spending', 'nonprofitsuite' ); ?></p>

				<div class="ns-empty-state">
					<p><?php esc_html_e( 'No budgets created yet.', 'nonprofitsuite' ); ?></p>
					<button class="ns-button ns-button-primary" id="ns-add-budget-alt">
						<?php esc_html_e( 'Create Your First Budget', 'nonprofitsuite' ); ?>
					</button>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>

<!-- Modal: Add/Edit Transaction -->
<div id="ns-transaction-modal" class="ns-modal" style="display:none;">
	<div class="ns-modal-content">
		<div class="ns-modal-header">
			<h2 id="ns-transaction-modal-title"><?php esc_html_e( 'Record Transaction', 'nonprofitsuite' ); ?></h2>
			<button class="ns-modal-close">&times;</button>
		</div>
		<form id="ns-transaction-form">
			<input type="hidden" name="transaction_id" id="transaction-id">
			<input type="hidden" name="action" value="ns_save_transaction">
			<input type="hidden" name="nonce" value="<?php echo wp_create_nonce( 'ns_treasury_transaction' ); ?>">

			<div class="ns-form-row">
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Transaction Date', 'nonprofitsuite' ); ?> <span class="required">*</span></label>
					<input type="date" name="transaction_date" id="transaction-date" required>
				</div>
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Transaction Type', 'nonprofitsuite' ); ?> <span class="required">*</span></label>
					<select name="transaction_type" id="transaction-type" required>
						<option value=""><?php esc_html_e( 'Select Type', 'nonprofitsuite' ); ?></option>
						<option value="income"><?php esc_html_e( 'Income', 'nonprofitsuite' ); ?></option>
						<option value="expense"><?php esc_html_e( 'Expense', 'nonprofitsuite' ); ?></option>
						<option value="transfer"><?php esc_html_e( 'Transfer', 'nonprofitsuite' ); ?></option>
					</select>
				</div>
			</div>

			<div class="ns-form-row">
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Account', 'nonprofitsuite' ); ?> <span class="required">*</span></label>
					<select name="account_id" id="transaction-account" required>
						<option value=""><?php esc_html_e( 'Select Account', 'nonprofitsuite' ); ?></option>
						<?php if ( ! empty( $accounts ) ) : foreach ( $accounts as $account ) : ?>
							<option value="<?php echo esc_attr( $account->id ); ?>">
								<?php echo esc_html( $account->account_number . ' - ' . $account->account_name ); ?>
							</option>
						<?php endforeach; endif; ?>
					</select>
				</div>
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Amount', 'nonprofitsuite' ); ?> <span class="required">*</span></label>
					<input type="number" step="0.01" name="amount" id="transaction-amount" placeholder="0.00" required>
				</div>
			</div>

			<div class="ns-form-group">
				<label><?php esc_html_e( 'Description', 'nonprofitsuite' ); ?> <span class="required">*</span></label>
				<input type="text" name="description" id="transaction-description" required>
			</div>

			<div class="ns-form-row">
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Payee/Payer', 'nonprofitsuite' ); ?></label>
					<input type="text" name="payee" id="transaction-payee">
				</div>
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Category', 'nonprofitsuite' ); ?></label>
					<input type="text" name="category" id="transaction-category">
				</div>
			</div>

			<div class="ns-form-group">
				<label><?php esc_html_e( 'Notes', 'nonprofitsuite' ); ?></label>
				<textarea name="notes" id="transaction-notes" rows="3"></textarea>
			</div>

			<div class="ns-modal-footer">
				<button type="button" class="ns-button ns-button-text ns-modal-close"><?php esc_html_e( 'Cancel', 'nonprofitsuite' ); ?></button>
				<button type="submit" class="ns-button ns-button-primary"><?php esc_html_e( 'Save Transaction', 'nonprofitsuite' ); ?></button>
			</div>
		</form>
	</div>
</div>

<!-- Modal: Add/Edit Account -->
<div id="ns-account-modal" class="ns-modal" style="display:none;">
	<div class="ns-modal-content">
		<div class="ns-modal-header">
			<h2 id="ns-account-modal-title"><?php esc_html_e( 'Add Account', 'nonprofitsuite' ); ?></h2>
			<button class="ns-modal-close">&times;</button>
		</div>
		<form id="ns-account-form">
			<input type="hidden" name="account_id" id="account-id">
			<input type="hidden" name="action" value="ns_save_account">
			<input type="hidden" name="nonce" value="<?php echo wp_create_nonce( 'ns_treasury_account' ); ?>">

			<div class="ns-form-row">
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Account Number', 'nonprofitsuite' ); ?> <span class="required">*</span></label>
					<input type="text" name="account_number" id="account-number" required>
				</div>
				<div class="ns-form-group">
					<label><?php esc_html_e( 'Account Type', 'nonprofitsuite' ); ?> <span class="required">*</span></label>
					<select name="account_type" id="account-type" required>
						<option value=""><?php esc_html_e( 'Select Type', 'nonprofitsuite' ); ?></option>
						<option value="asset"><?php esc_html_e( 'Asset', 'nonprofitsuite' ); ?></option>
						<option value="liability"><?php esc_html_e( 'Liability', 'nonprofitsuite' ); ?></option>
						<option value="equity"><?php esc_html_e( 'Net Assets/Equity', 'nonprofitsuite' ); ?></option>
						<option value="revenue"><?php esc_html_e( 'Revenue', 'nonprofitsuite' ); ?></option>
						<option value="expense"><?php esc_html_e( 'Expense', 'nonprofitsuite' ); ?></option>
					</select>
				</div>
			</div>

			<div class="ns-form-group">
				<label><?php esc_html_e( 'Account Name', 'nonprofitsuite' ); ?> <span class="required">*</span></label>
				<input type="text" name="account_name" id="account-name" required>
			</div>

			<div class="ns-form-group">
				<label><?php esc_html_e( 'Category', 'nonprofitsuite' ); ?></label>
				<input type="text" name="category" id="account-category">
			</div>

			<div class="ns-form-group">
				<label><?php esc_html_e( 'Description', 'nonprofitsuite' ); ?></label>
				<textarea name="description" id="account-description" rows="3"></textarea>
			</div>

			<div class="ns-modal-footer">
				<button type="button" class="ns-button ns-button-text ns-modal-close"><?php esc_html_e( 'Cancel', 'nonprofitsuite' ); ?></button>
				<button type="submit" class="ns-button ns-button-primary"><?php esc_html_e( 'Save Account', 'nonprofitsuite' ); ?></button>
			</div>
		</form>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// Open transaction modal
	$('#ns-add-transaction, .ns-edit-transaction').on('click', function() {
		var transactionId = $(this).data('id');
		if (transactionId) {
			// TODO: Load transaction data via AJAX
			$('#ns-transaction-modal-title').text('<?php esc_html_e( 'Edit Transaction', 'nonprofitsuite' ); ?>');
		} else {
			$('#ns-transaction-form')[0].reset();
			$('#transaction-date').val(new Date().toISOString().split('T')[0]);
			$('#ns-transaction-modal-title').text('<?php esc_html_e( 'Record Transaction', 'nonprofitsuite' ); ?>');
		}
		$('#ns-transaction-modal').fadeIn();
	});

	// Open account modal
	$('#ns-add-account').on('click', function() {
		$('#ns-account-form')[0].reset();
		$('#ns-account-modal-title').text('<?php esc_html_e( 'Add Account', 'nonprofitsuite' ); ?>');
		$('#ns-account-modal').fadeIn();
	});

	// Close modals
	$('.ns-modal-close').on('click', function() {
		$(this).closest('.ns-modal').fadeOut();
	});

	// Close modal on background click
	$('.ns-modal').on('click', function(e) {
		if ($(e.target).hasClass('ns-modal')) {
			$(this).fadeOut();
		}
	});

	// Submit transaction form via AJAX
	$('#ns-transaction-form').on('submit', function(e) {
		e.preventDefault();
		var formData = $(this).serialize();

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: formData,
			success: function(response) {
				if (response.success) {
					alert('<?php esc_html_e( 'Transaction saved successfully!', 'nonprofitsuite' ); ?>');
					location.reload();
				} else {
					alert(response.data.message || '<?php esc_html_e( 'Error saving transaction', 'nonprofitsuite' ); ?>');
				}
			},
			error: function() {
				alert('<?php esc_html_e( 'Error saving transaction', 'nonprofitsuite' ); ?>');
			}
		});
	});

	// Submit account form via AJAX
	$('#ns-account-form').on('submit', function(e) {
		e.preventDefault();
		var formData = $(this).serialize();

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: formData,
			success: function(response) {
				if (response.success) {
					alert('<?php esc_html_e( 'Account saved successfully!', 'nonprofitsuite' ); ?>');
					location.reload();
				} else {
					alert(response.data.message || '<?php esc_html_e( 'Error saving account', 'nonprofitsuite' ); ?>');
				}
			},
			error: function() {
				alert('<?php esc_html_e( 'Error saving account', 'nonprofitsuite' ); ?>');
			}
		});
	});

	// Delete transaction
	$('.ns-delete-transaction').on('click', function() {
		if (!confirm('<?php esc_html_e( 'Are you sure you want to delete this transaction?', 'nonprofitsuite' ); ?>')) {
			return;
		}

		var transactionId = $(this).data('id');
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ns_delete_transaction',
				transaction_id: transactionId,
				nonce: '<?php echo wp_create_nonce( 'ns_treasury_transaction' ); ?>'
			},
			success: function(response) {
				if (response.success) {
					alert('<?php esc_html_e( 'Transaction deleted', 'nonprofitsuite' ); ?>');
					location.reload();
				}
			}
		});
	});

	// Export functionality
	$('#ns-export-treasury').on('click', function() {
		var format = prompt('<?php esc_html_e( 'Export format (csv/pdf/excel):', 'nonprofitsuite' ); ?>', 'csv');
		if (format) {
			window.location.href = '?page=nonprofitsuite-treasury&action=export&format=' + format;
		}
	});

	// Initialize chart of accounts
	$('#ns-init-accounts').on('click', function() {
		if (!confirm('<?php esc_html_e( 'Initialize default chart of accounts?', 'nonprofitsuite' ); ?>')) {
			return;
		}

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ns_init_accounts',
				nonce: '<?php echo wp_create_nonce( 'ns_treasury_init' ); ?>'
			},
			success: function(response) {
				if (response.success) {
					alert('<?php esc_html_e( 'Chart of accounts initialized!', 'nonprofitsuite' ); ?>');
					location.reload();
				}
			}
		});
	});
});
</script>

<style>
.ns-container { max-width: 1400px; margin: 20px; }
.ns-actions-bar { margin: 20px 0; display: flex; gap: 10px; flex-wrap: wrap; }
.ns-button { padding: 10px 20px; border-radius: 4px; border: none; cursor: pointer; font-size: 14px; transition: all 0.2s; }
.ns-button-primary { background: #2271b1; color: #fff; }
.ns-button-primary:hover { background: #135e96; }
.ns-button-secondary { background: #f0f0f1; color: #2c3338; }
.ns-button-secondary:hover { background: #dcdcde; }
.ns-button .dashicons { font-size: 16px; vertical-align: middle; margin-right: 5px; }

.ns-tabs { display: flex; border-bottom: 2px solid #dcdcde; margin: 20px 0; gap: 5px; }
.ns-tab { padding: 12px 20px; text-decoration: none; color: #2c3338; border-bottom: 3px solid transparent; transition: all 0.2s; }
.ns-tab:hover { color: #2271b1; }
.ns-tab.active { color: #2271b1; border-bottom-color: #2271b1; font-weight: 600; }

.ns-dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0; }
.ns-stat-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.ns-stat-icon { font-size: 32px; color: #2271b1; margin-bottom: 10px; }
.ns-stat-icon .dashicons { font-size: 32px; width: 32px; height: 32px; }
.ns-stat-label { font-size: 13px; color: #646970; margin-bottom: 8px; }
.ns-stat-value { font-size: 28px; font-weight: 700; color: #1d2327; }
.ns-stat-value.success { color: #00a32a; }
.ns-stat-value.warning { color: #dba617; }
.ns-stat-value.danger { color: #d63638; }

.ns-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin: 20px 0; }
.ns-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.ns-card-title { margin: 0; font-size: 18px; }

.ns-table { width: 100%; border-collapse: collapse; }
.ns-table th { text-align: left; padding: 12px; background: #f6f7f7; font-weight: 600; border-bottom: 2px solid #dcdcde; }
.ns-table td { padding: 12px; border-bottom: 1px solid #f0f0f1; }
.ns-table-hover tbody tr:hover { background: #f6f7f7; }
.ns-table-striped tbody tr:nth-child(even) { background: #f9f9f9; }
.ns-text-right { text-align: right; }

.ns-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
.ns-badge-income { background: #d7f8e0; color: #00593d; }
.ns-badge-expense { background: #ffe0e0; color: #8b0000; }
.ns-badge-transfer { background: #e0e8ff; color: #003d7a; }
.ns-badge-success { background: #d7f8e0; color: #00593d; }
.ns-badge-inactive { background: #f0f0f1; color: #646970; }

.ns-amount-positive { color: #00a32a; }
.ns-amount-negative { color: #d63638; }

.ns-button-icon { background: none; border: none; cursor: pointer; color: #2271b1; padding: 5px; }
.ns-button-icon:hover { color: #135e96; }
.ns-button-icon .dashicons { font-size: 18px; }

.ns-filters { background: #f6f7f7; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
.ns-filters-row { display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; }
.ns-filter-group { display: flex; flex-direction: column; }
.ns-filter-group label { font-size: 12px; font-weight: 600; margin-bottom: 5px; }
.ns-filter-group input, .ns-filter-group select { padding: 6px 10px; border: 1px solid #dcdcde; border-radius: 4px; }

.ns-empty-state { text-align: center; padding: 40px; color: #646970; }

.ns-report-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
.ns-report-card { border: 1px solid #dcdcde; padding: 20px; border-radius: 8px; }
.ns-report-card h3 { margin-top: 0; font-size: 16px; }
.ns-report-card p { color: #646970; font-size: 14px; }

.ns-modal { display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
.ns-modal-content { background-color: #fff; margin: 5% auto; padding: 0; border-radius: 8px; width: 90%; max-width: 600px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
.ns-modal-header { padding: 20px; border-bottom: 1px solid #dcdcde; display: flex; justify-content: space-between; align-items: center; }
.ns-modal-header h2 { margin: 0; font-size: 20px; }
.ns-modal-close { font-size: 28px; font-weight: bold; color: #646970; background: none; border: none; cursor: pointer; }
.ns-modal-close:hover { color: #000; }
.ns-modal form { padding: 20px; }
.ns-modal-footer { padding: 20px; border-top: 1px solid #dcdcde; display: flex; justify-content: flex-end; gap: 10px; }

.ns-form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px; }
.ns-form-group { display: flex; flex-direction: column; margin-bottom: 15px; }
.ns-form-group label { font-weight: 600; margin-bottom: 5px; font-size: 13px; }
.ns-form-group .required { color: #d63638; }
.ns-form-group input, .ns-form-group select, .ns-form-group textarea { padding: 8px 12px; border: 1px solid #dcdcde; border-radius: 4px; font-size: 14px; }
.ns-form-group input:focus, .ns-form-group select:focus, .ns-form-group textarea:focus { border-color: #2271b1; outline: none; box-shadow: 0 0 0 1px #2271b1; }

@media (max-width: 768px) {
	.ns-dashboard-grid { grid-template-columns: 1fr; }
	.ns-table { font-size: 12px; }
	.ns-table th, .ns-table td { padding: 8px; }
	.ns-filters-row { flex-direction: column; }
	.ns-filter-group { width: 100%; }
	.ns-actions-bar { flex-direction: column; }
	.ns-button { width: 100%; justify-content: center; display: flex; }
}
</style>
