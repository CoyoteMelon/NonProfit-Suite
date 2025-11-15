<?php
/**
 * Support System Admin Interface
 *
 * @package NonprofitSuite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Get dashboard data
$dashboard_data = isset( $dashboard_data ) ? $dashboard_data : NonprofitSuite_Support::get_dashboard_data();
$tickets = isset( $tickets ) ? $tickets : NonprofitSuite_Support::get_tickets( array( 'user_id' => get_current_user_id() ) );
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Support System', 'nonprofitsuite' ); ?></h1>

	<div class="ns-card">
		<div class="ns-card-header">
			<h2 class="ns-card-title"><?php esc_html_e( 'My Support Tickets', 'nonprofitsuite' ); ?></h2>
			<a href="https://silverhost.net/nonprofitsuite/support" target="_blank" class="ns-button ns-button-primary">
				<?php esc_html_e( 'Create New Ticket', 'nonprofitsuite' ); ?>
			</a>
		</div>

		<?php if ( is_array( $tickets ) && ! empty( $tickets ) ) : ?>
			<div class="ns-table-container">
				<table class="ns-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Ticket #', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Subject', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Category', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Priority', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Created', 'nonprofitsuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $tickets as $ticket ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $ticket->ticket_number ); ?></strong></td>
								<td><?php echo esc_html( $ticket->subject ); ?></td>
								<td><?php echo esc_html( $ticket->category ?? '-' ); ?></td>
								<td><?php echo NonprofitSuite_Utilities::get_priority_badge( $ticket->priority ?? 'normal' ); ?></td>
								<td><?php echo NonprofitSuite_Utilities::get_status_badge( $ticket->status ); ?></td>
								<td><?php echo esc_html( NonprofitSuite_Utilities::format_datetime( $ticket->created_at ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<div style="text-align: center; padding: 60px 20px;">
				<p><?php esc_html_e( 'No support tickets yet. Create one to get help from our team!', 'nonprofitsuite' ); ?></p>
				<a href="https://silverhost.net/nonprofitsuite/support" target="_blank" class="ns-button ns-button-primary">
					<?php esc_html_e( 'Create First Ticket', 'nonprofitsuite' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>

	<div class="ns-card" style="margin-top: 20px;">
		<h3><?php esc_html_e( 'Support Resources', 'nonprofitsuite' ); ?></h3>
		<ul style="list-style: none; padding: 0;">
			<li style="padding: 10px 0; border-bottom: 1px solid #eee;">
				<a href="https://silverhost.net/nonprofitsuite/docs" target="_blank">📚 <?php esc_html_e( 'Documentation', 'nonprofitsuite' ); ?></a>
			</li>
			<li style="padding: 10px 0; border-bottom: 1px solid #eee;">
				<a href="https://silverhost.net/nonprofitsuite/kb" target="_blank">💡 <?php esc_html_e( 'Knowledge Base', 'nonprofitsuite' ); ?></a>
			</li>
			<li style="padding: 10px 0; border-bottom: 1px solid #eee;">
				<a href="https://silverhost.net/nonprofitsuite/community" target="_blank">👥 <?php esc_html_e( 'Community Forum', 'nonprofitsuite' ); ?></a>
			</li>
			<li style="padding: 10px 0;">
				<a href="https://silverhost.net/nonprofitsuite/contact" target="_blank">✉️ <?php esc_html_e( 'Contact Us', 'nonprofitsuite' ); ?></a>
			</li>
		</ul>
	</div>
</div>
