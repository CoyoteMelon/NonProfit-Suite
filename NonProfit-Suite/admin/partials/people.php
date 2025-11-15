<?php
/**
 * People View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$people = NonprofitSuite_Person::get_all( array( 'limit' => 100 ) );
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'People', 'nonprofitsuite' ); ?></h1>

	<div class="ns-card">
		<?php if ( ! empty( $people ) ) : ?>
			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Email', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Phone', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $people as $person ) : ?>
						<tr>
							<td><strong><?php echo esc_html( NonprofitSuite_Person::get_full_name( $person ) ); ?></strong></td>
							<td><?php echo esc_html( $person->email ); ?></td>
							<td><?php echo esc_html( $person->phone ); ?></td>
							<td><?php echo NonprofitSuite_Utilities::get_status_badge( $person->status ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No people found.', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>
</div>
