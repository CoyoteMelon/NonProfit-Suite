<?php
/**
 * Journal Entries Page
 *
 * @package NonprofitSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Journal Entries', 'nonprofitsuite' ); ?></h1>

	<div class="ns-filters" style="margin: 20px 0;">
		<form method="get" action="">
			<input type="hidden" name="page" value="nonprofitsuite-journal-entries">
			<label>
				<?php esc_html_e( 'From:', 'nonprofitsuite' ); ?>
				<input type="date" name="date_from" value="<?php echo esc_attr( $_GET['date_from'] ?? '' ); ?>">
			</label>
			<label>
				<?php esc_html_e( 'To:', 'nonprofitsuite' ); ?>
				<input type="date" name="date_to" value="<?php echo esc_attr( $_GET['date_to'] ?? '' ); ?>">
			</label>
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'nonprofitsuite' ); ?></button>
		</form>
	</div>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date', 'nonprofitsuite' ); ?></th>
				<th><?php esc_html_e( 'Entry #', 'nonprofitsuite' ); ?></th>
				<th><?php esc_html_e( 'Account', 'nonprofitsuite' ); ?></th>
				<th><?php esc_html_e( 'Debit', 'nonprofitsuite' ); ?></th>
				<th><?php esc_html_e( 'Credit', 'nonprofitsuite' ); ?></th>
				<th><?php esc_html_e( 'Description', 'nonprofitsuite' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $entries ) ) : ?>
				<tr>
					<td colspan="6"><?php esc_html_e( 'No journal entries found. Entries are created automatically from payment transactions when accounting is enabled.', 'nonprofitsuite' ); ?></td>
				</tr>
			<?php else : ?>
				<?php
				$current_batch = '';
				foreach ( $entries as $entry ) :
					$show_batch_border = $current_batch !== $entry['batch_id'];
					$current_batch = $entry['batch_id'];
					?>
					<tr <?php echo $show_batch_border ? 'style="border-top: 2px solid #0073aa;"' : ''; ?>>
						<td><?php echo esc_html( date( 'M j, Y', strtotime( $entry['entry_date'] ) ) ); ?></td>
						<td><small><?php echo esc_html( $entry['entry_number'] ); ?></small></td>
						<td>
							<strong><?php echo esc_html( $entry['account_number'] ); ?></strong>
							<?php echo esc_html( $entry['account_name'] ); ?>
						</td>
						<td>
							<?php if ( $entry['debit_amount'] > 0 ) : ?>
								$<?php echo esc_html( number_format( $entry['debit_amount'], 2 ) ); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $entry['credit_amount'] > 0 ) : ?>
								$<?php echo esc_html( number_format( $entry['credit_amount'], 2 ) ); ?>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $entry['description'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
