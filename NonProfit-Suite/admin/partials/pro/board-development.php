<?php
/**
 * Board Development (PRO) View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$active_members = NonprofitSuite_Board_Development::get_active_board_members();
$expiring_terms = NonprofitSuite_Board_Development::get_terms_expiring( 3 );
$prospects = NonprofitSuite_Board_Development::get_prospects();
$composition = NonprofitSuite_Board_Development::get_board_composition();
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Board Development', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<?php if ( ! empty( $expiring_terms ) && ! is_wp_error( $expiring_terms ) ) : ?>
		<div class="ns-alert ns-alert-warning">
			<strong><?php esc_html_e( 'Terms Expiring Soon:', 'nonprofitsuite' ); ?></strong>
			<?php printf( _n( '%d board term expires within 3 months', '%d board terms expire within 3 months', count( $expiring_terms ), 'nonprofitsuite' ), count( $expiring_terms ) ); ?>
		</div>
	<?php endif; ?>

	<!-- Board Composition Summary -->
	<div class="ns-card" style="margin-bottom: 20px;">
		<h2 class="ns-card-title"><?php esc_html_e( 'Current Board Composition', 'nonprofitsuite' ); ?></h2>
		<div class="ns-stats-grid">
			<div class="ns-stat-box">
				<div class="ns-stat-label"><?php esc_html_e( 'Total Members', 'nonprofitsuite' ); ?></div>
				<div class="ns-stat-value"><?php echo is_wp_error( $composition ) ? 0 : absint( $composition['total_members'] ); ?></div>
			</div>
		</div>
	</div>

	<!-- Current Board Members -->
	<div class="ns-card" style="margin-bottom: 20px;">
		<div class="ns-card-header">
			<h2 class="ns-card-title"><?php esc_html_e( 'Current Board Members', 'nonprofitsuite' ); ?></h2>
			<button class="ns-button ns-button-primary" onclick="alert('Add board term feature coming soon');">
				<?php esc_html_e( 'Add Board Member', 'nonprofitsuite' ); ?>
			</button>
		</div>

		<?php if ( ! empty( $active_members ) && ! is_wp_error( $active_members ) ) : ?>
			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Position', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Term', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Term #', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Expires', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $active_members as $member ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $member->first_name . ' ' . $member->last_name ); ?></strong>
								<?php if ( $member->email ) : ?>
									<br><span class="ns-text-sm"><?php echo esc_html( $member->email ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $member->position ) ) ); ?></td>
							<td><?php echo esc_html( date( 'M Y', strtotime( $member->term_start ) ) ); ?> - <?php echo esc_html( date( 'M Y', strtotime( $member->term_end ) ) ); ?></td>
							<td><?php echo absint( $member->term_number ); ?></td>
							<td>
								<?php
								$days_until = round( ( strtotime( $member->term_end ) - time() ) / 86400 );
								$class = $days_until < 90 ? 'ns-text-warning' : '';
								?>
								<span class="<?php echo $class; ?>">
									<?php echo esc_html( date( 'M j, Y', strtotime( $member->term_end ) ) ); ?>
								</span>
							</td>
							<td><?php echo NonprofitSuite_Utilities::get_status_badge( $member->status ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No active board members found. Add your first board member to get started!', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>

	<!-- Board Prospects Pipeline -->
	<div class="ns-card">
		<div class="ns-card-header">
			<h2 class="ns-card-title"><?php esc_html_e( 'Board Prospects Pipeline', 'nonprofitsuite' ); ?></h2>
			<button class="ns-button ns-button-primary" onclick="alert('Add prospect feature coming soon');">
				<?php esc_html_e( 'Add Prospect', 'nonprofitsuite' ); ?>
			</button>
		</div>

		<?php if ( ! empty( $prospects ) && ! is_wp_error( $prospects ) ) : ?>
			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Stage', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Readiness Score', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Giving Capacity', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Target Join Date', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $prospects as $prospect ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $prospect->first_name . ' ' . $prospect->last_name ); ?></strong>
								<?php if ( $prospect->email ) : ?>
									<br><span class="ns-text-sm"><?php echo esc_html( $prospect->email ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $prospect->prospect_status ) ) ); ?></td>
							<td>
								<?php if ( $prospect->readiness_score ) : ?>
									<strong><?php echo absint( $prospect->readiness_score ); ?></strong>/100
								<?php else : ?>
									<span class="ns-text-muted">Not scored</span>
								<?php endif; ?>
							</td>
							<td><?php echo $prospect->giving_capacity ? esc_html( ucfirst( $prospect->giving_capacity ) ) : '-'; ?></td>
							<td>
								<?php if ( $prospect->target_join_date ) : ?>
									<?php echo esc_html( date( 'M j, Y', strtotime( $prospect->target_join_date ) ) ); ?>
								<?php else : ?>
									<span class="ns-text-muted">-</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No board prospects found. Add prospects to build your board pipeline!', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>
</div>
