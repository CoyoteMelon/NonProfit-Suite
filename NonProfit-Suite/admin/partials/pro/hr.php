<?php
/**
 * Human Resources (PRO) View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$employees = NonprofitSuite_HR::get_employees( 'active' );
$time_off_requests = NonprofitSuite_HR::get_time_off_requests( array( 'status' => 'pending' ) );
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Human Resources', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<?php if ( ! empty( $time_off_requests ) && ! is_wp_error( $time_off_requests ) ) : ?>
		<div class="ns-alert ns-alert-info">
			<strong><?php esc_html_e( 'Pending Requests:', 'nonprofitsuite' ); ?></strong>
			<?php printf( _n( '%d time off request awaiting approval', '%d time off requests awaiting approval', count( $time_off_requests ), 'nonprofitsuite' ), count( $time_off_requests ) ); ?>
		</div>
	<?php endif; ?>

	<div class="ns-card">
		<div class="ns-card-header">
			<h2 class="ns-card-title"><?php esc_html_e( 'Active Employees', 'nonprofitsuite' ); ?></h2>
			<button class="ns-button ns-button-primary" onclick="alert('Add employee feature coming soon');">
				<?php esc_html_e( 'Add Employee', 'nonprofitsuite' ); ?>
			</button>
		</div>

		<?php if ( ! empty( $employees ) && ! is_wp_error( $employees ) ) : ?>
			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Position', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Hire Date', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Contact', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $employees as $employee ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $employee->first_name . ' ' . $employee->last_name ); ?></strong></td>
							<td><?php echo esc_html( $employee->position ?: __( 'Not specified', 'nonprofitsuite' ) ); ?></td>
							<td><?php echo esc_html( ucfirst( str_replace( '_', '-', $employee->employee_type ) ) ); ?></td>
							<td><?php echo esc_html( date( 'M j, Y', strtotime( $employee->hire_date ) ) ); ?></td>
							<td>
								<?php if ( $employee->email ) : ?>
									<a href="mailto:<?php echo esc_attr( $employee->email ); ?>"><?php echo esc_html( $employee->email ); ?></a>
								<?php endif; ?>
								<?php if ( $employee->phone ) : ?>
									<br><?php echo esc_html( $employee->phone ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo NonprofitSuite_Utilities::get_status_badge( $employee->status ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No employees found. Add your first employee to get started!', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $time_off_requests ) && ! is_wp_error( $time_off_requests ) ) : ?>
		<div class="ns-card" style="margin-top: 20px;">
			<div class="ns-card-header">
				<h2 class="ns-card-title"><?php esc_html_e( 'Pending Time Off Requests', 'nonprofitsuite' ); ?></h2>
			</div>

			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Employee', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Start Date', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'End Date', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Days', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $time_off_requests as $request ) : ?>
						<tr>
							<td><strong>Employee #<?php echo esc_html( $request->employee_id ); ?></strong></td>
							<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $request->time_off_type ) ) ); ?></td>
							<td><?php echo esc_html( date( 'M j, Y', strtotime( $request->start_date ) ) ); ?></td>
							<td><?php echo esc_html( date( 'M j, Y', strtotime( $request->end_date ) ) ); ?></td>
							<td><?php echo esc_html( $request->days ); ?></td>
							<td>
								<button class="ns-button ns-button-sm" onclick="alert('Approve feature coming soon');">
									<?php esc_html_e( 'Approve', 'nonprofitsuite' ); ?>
								</button>
								<button class="ns-button ns-button-sm ns-button-danger" onclick="alert('Deny feature coming soon');">
									<?php esc_html_e( 'Deny', 'nonprofitsuite' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
