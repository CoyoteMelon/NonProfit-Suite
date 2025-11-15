<?php
/**
 * Events (PRO) View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$events = NonprofitSuite_Events::get_events( array( 'upcoming' => true ) );
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Event Management', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<div class="ns-card">
		<div class="ns-card-header">
			<h2 class="ns-card-title"><?php esc_html_e( 'Upcoming Events', 'nonprofitsuite' ); ?></h2>
			<button class="ns-button ns-button-primary" onclick="alert('Add event feature coming soon');">
				<?php esc_html_e( 'Create Event', 'nonprofitsuite' ); ?>
			</button>
		</div>

		<?php if ( ! empty( $events ) && ! is_wp_error( $events ) ) : ?>
			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Event', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Date', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Location', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Capacity', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Registered', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Revenue', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $events as $event ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $event->event_name ); ?></strong></td>
							<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $event->event_type ) ) ); ?></td>
							<td><?php echo esc_html( date( 'M j, Y g:i A', strtotime( $event->event_date ) ) ); ?></td>
							<td>
								<?php if ( $event->location ) : ?>
									<?php echo esc_html( $event->location ); ?>
								<?php elseif ( $event->virtual_url ) : ?>
									<span class="ns-badge"><?php esc_html_e( 'Virtual', 'nonprofitsuite' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php
								if ( $event->capacity ) {
									echo esc_html( $event->capacity );
								} else {
									esc_html_e( 'Unlimited', 'nonprofitsuite' );
								}
								?>
							</td>
							<td><?php echo absint( $event->registered_count ); ?></td>
							<td>$<?php echo number_format( $event->total_revenue, 2 ); ?></td>
							<td><?php echo NonprofitSuite_Utilities::get_status_badge( $event->status ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No upcoming events found. Create your first event to get started!', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>
</div>
