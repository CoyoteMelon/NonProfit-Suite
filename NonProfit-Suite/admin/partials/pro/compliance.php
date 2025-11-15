<?php
/**
 * Compliance & Regulatory (PRO) View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$upcoming = NonprofitSuite_Compliance::get_upcoming( 30 );
$overdue = NonprofitSuite_Compliance::get_overdue();
$all_items = NonprofitSuite_Compliance::get_items( array( 'limit' => 50 ) );
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Compliance & Regulatory', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<?php if ( ! empty( $overdue ) && ! is_wp_error( $overdue ) ) : ?>
		<div class="ns-alert ns-alert-danger">
			<strong><?php esc_html_e( 'Overdue Items:', 'nonprofitsuite' ); ?></strong>
			<?php printf( _n( '%d compliance item is overdue', '%d compliance items are overdue', count( $overdue ), 'nonprofitsuite' ), count( $overdue ) ); ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $upcoming ) && ! is_wp_error( $upcoming ) ) : ?>
		<div class="ns-alert ns-alert-warning">
			<strong><?php esc_html_e( 'Due Soon:', 'nonprofitsuite' ); ?></strong>
			<?php printf( _n( '%d compliance item due within 30 days', '%d compliance items due within 30 days', count( $upcoming ), 'nonprofitsuite' ), count( $upcoming ) ); ?>
		</div>
	<?php endif; ?>

	<div class="ns-card">
		<div class="ns-card-header">
			<h2 class="ns-card-title"><?php esc_html_e( 'Compliance Items', 'nonprofitsuite' ); ?></h2>
			<button class="ns-button ns-button-primary" onclick="alert('Add compliance item feature coming soon');">
				<?php esc_html_e( 'Add Item', 'nonprofitsuite' ); ?>
			</button>
		</div>

		<?php if ( ! empty( $all_items ) && ! is_wp_error( $all_items ) ) : ?>
			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Item', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Due Date', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Responsible', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Recurrence', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $all_items as $item ) :
						$responsible = null;
						if ( $item->responsible_person_id ) {
							$responsible = NonprofitSuite_Person::get( $item->responsible_person_id );
						}
					?>
						<tr>
							<td><strong><?php echo esc_html( $item->item_name ); ?></strong></td>
							<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $item->item_type ) ) ); ?></td>
							<td>
								<?php
								$due_date = strtotime( $item->due_date );
								$today = strtotime( 'today' );
								$class = '';
								if ( $item->status === 'pending' ) {
									if ( $due_date < $today ) {
										$class = 'ns-text-danger';
									} elseif ( $due_date < strtotime( '+7 days' ) ) {
										$class = 'ns-text-warning';
									}
								}
								?>
								<span class="<?php echo $class; ?>">
									<?php echo esc_html( date( 'M j, Y', $due_date ) ); ?>
								</span>
							</td>
							<td>
								<?php if ( $responsible ) : ?>
									<?php echo esc_html( $responsible->first_name . ' ' . $responsible->last_name ); ?>
								<?php else : ?>
									<span class="ns-text-muted"><?php esc_html_e( 'Unassigned', 'nonprofitsuite' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $item->recurrence ) ) ); ?></td>
							<td><?php echo NonprofitSuite_Utilities::get_status_badge( $item->status ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No compliance items found. Add your first compliance requirement to get started!', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>
</div>
