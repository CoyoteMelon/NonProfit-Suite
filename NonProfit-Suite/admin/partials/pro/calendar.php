<?php
/**
 * Calendar Integration Admin Interface
 *
 * @package NonprofitSuite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Get calendar items
$start_date = isset( $_GET['start'] ) ? sanitize_text_field( $_GET['start'] ) : date( 'Y-m-01' );
$end_date = isset( $_GET['end'] ) ? sanitize_text_field( $_GET['end'] ) : date( 'Y-m-t' );
$items = isset( $items ) ? $items : NonprofitSuite_Calendar::get_items( $start_date, $end_date );
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Calendar Integration', 'nonprofitsuite' ); ?></h1>

	<div class="ns-card">
		<h2><?php esc_html_e( 'Organizational Calendar', 'nonprofitsuite' ); ?></h2>
		<p><?php esc_html_e( 'View all meetings, events, tasks, and deadlines in one unified calendar.', 'nonprofitsuite' ); ?></p>

		<div style="margin: 20px 0;">
			<button class="ns-button"><?php esc_html_e( '← Previous Month', 'nonprofitsuite' ); ?></button>
			<strong style="margin: 0 20px;"><?php echo esc_html( date( 'F Y', strtotime( $start_date ) ) ); ?></strong>
			<button class="ns-button"><?php esc_html_e( 'Next Month →', 'nonprofitsuite' ); ?></button>
		</div>

		<?php if ( is_array( $items ) && ! empty( $items ) ) : ?>
			<div class="ns-table-container">
				<table class="ns-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Title', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Date & Time', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Location', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Source', 'nonprofitsuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $items as $item ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $item->title ); ?></strong></td>
								<td>
									<span style="display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; background: <?php echo esc_attr( $item->color ?? '#ccc' ); ?>; color: white;">
										<?php echo esc_html( ucfirst( $item->item_type ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( NonprofitSuite_Utilities::format_datetime( $item->start_date ) ); ?></td>
								<td><?php echo esc_html( $item->location ?: '-' ); ?></td>
								<td><?php echo esc_html( ucfirst( $item->source_module ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<div style="text-align: center; padding: 60px;">
				<p><?php esc_html_e( 'No calendar items for this month.', 'nonprofitsuite' ); ?></p>
			</div>
		<?php endif; ?>
	</div>

	<div class="ns-card" style="margin-top: 20px;">
		<h3><?php esc_html_e( 'Sync with External Calendars', 'nonprofitsuite' ); ?></h3>
		<p><?php esc_html_e( 'Export your NonprofitSuite calendar to Google Calendar, Outlook, or Apple Calendar.', 'nonprofitsuite' ); ?></p>
		<div style="display: flex; gap: 10px; margin-top: 15px;">
			<button class="ns-button ns-button-outline">🔗 <?php esc_html_e( 'Google Calendar', 'nonprofitsuite' ); ?></button>
			<button class="ns-button ns-button-outline">🔗 <?php esc_html_e( 'Outlook', 'nonprofitsuite' ); ?></button>
			<button class="ns-button ns-button-outline">🔗 <?php esc_html_e( 'iCal/ICS', 'nonprofitsuite' ); ?></button>
		</div>
	</div>
</div>
