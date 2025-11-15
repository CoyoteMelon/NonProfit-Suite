<?php
/**
 * Committees (PRO) View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$committees = NonprofitSuite_Committees::get_committees( 'active' );
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Committee Management', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<div class="ns-card">
		<div class="ns-card-header">
			<h2 class="ns-card-title"><?php esc_html_e( 'Active Committees', 'nonprofitsuite' ); ?></h2>
			<button class="ns-button ns-button-primary" onclick="alert('Add committee feature coming soon');">
				<?php esc_html_e( 'Create Committee', 'nonprofitsuite' ); ?>
			</button>
		</div>

		<?php if ( ! empty( $committees ) && ! is_wp_error( $committees ) ) : ?>
			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Committee', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Chair', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Meeting Frequency', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Members', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $committees as $committee ) :
						$members = NonprofitSuite_Committees::get_committee_members( $committee->id );
						$chair = null;
						if ( $committee->chair_person_id ) {
							$chair = NonprofitSuite_Person::get( $committee->chair_person_id );
						}
					?>
						<tr>
							<td><strong><?php echo esc_html( $committee->committee_name ); ?></strong></td>
							<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $committee->committee_type ) ) ); ?></td>
							<td>
								<?php if ( $chair ) : ?>
									<?php echo esc_html( $chair->first_name . ' ' . $chair->last_name ); ?>
								<?php else : ?>
									<span class="ns-text-muted"><?php esc_html_e( 'No chair assigned', 'nonprofitsuite' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( ucfirst( $committee->meeting_frequency ) ); ?></td>
							<td><?php echo count( $members ); ?></td>
							<td><?php echo NonprofitSuite_Utilities::get_status_badge( $committee->status ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No committees found. Create your first committee to get started!', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>
</div>
