<?php
/**
 * Legal Counsel Dashboard (PRO) View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$access_records = NonprofitSuite_Legal_Counsel::get_access_records( array( 'status' => 'active' ) );
$all_matters = array();

if ( ! empty( $access_records ) && ! is_wp_error( $access_records ) ) {
	foreach ( $access_records as $access ) {
		$matters = NonprofitSuite_Legal_Counsel::get_matters( array( 'access_id' => $access->id, 'status' => 'active' ) );
		if ( ! is_wp_error( $matters ) ) {
			$all_matters = array_merge( $all_matters, $matters );
		}
	}
}
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Legal Counsel Portal', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<p class="ns-text-muted">
		<?php esc_html_e( 'Manage attorney access, track legal matters, monitor costs, and maintain privileged communications.', 'nonprofitsuite' ); ?>
	</p>

	<!-- Summary Stats -->
	<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Active Counsel', 'nonprofitsuite' ); ?></h4>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: #2563eb;">
				<?php echo ! empty( $access_records ) ? count( $access_records ) : 0; ?>
			</p>
		</div>

		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'Active Matters', 'nonprofitsuite' ); ?></h4>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: #10b981;">
				<?php echo count( $all_matters ); ?>
			</p>
		</div>

		<div class="ns-card" style="text-align: center; padding: 20px;">
			<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666;"><?php esc_html_e( 'High Priority', 'nonprofitsuite' ); ?></h4>
			<p style="margin: 0; font-size: 36px; font-weight: 600; color: #ef4444;">
				<?php
				$high_priority = array_filter( $all_matters, function( $m ) {
					return $m->priority === 'high';
				} );
				echo count( $high_priority );
				?>
			</p>
		</div>
	</div>

	<!-- Quick Actions -->
	<div class="ns-card" style="margin-bottom: 20px;">
		<h2 class="ns-card-title"><?php esc_html_e( 'Quick Actions', 'nonprofitsuite' ); ?></h2>
		<div style="display: flex; gap: 10px; flex-wrap: wrap;">
			<button class="ns-button ns-button-primary" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
				+ <?php esc_html_e( 'Grant Attorney Access', 'nonprofitsuite' ); ?>
			</button>
			<button class="ns-button" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
				+ <?php esc_html_e( 'New Legal Matter', 'nonprofitsuite' ); ?>
			</button>
			<button class="ns-button" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
				<?php esc_html_e( 'View All Matters', 'nonprofitsuite' ); ?>
			</button>
		</div>
	</div>

	<!-- Active Legal Counsel -->
	<div class="ns-card" style="margin-bottom: 20px;">
		<h2 class="ns-card-title"><?php esc_html_e( 'Active Legal Counsel', 'nonprofitsuite' ); ?></h2>

		<?php if ( ! empty( $access_records ) && ! is_wp_error( $access_records ) ) : ?>
			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Firm', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Attorney', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Specialization', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Contact', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Granted Date', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $access_records as $access ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $access->firm_name ); ?></strong></td>
							<td>
								<?php echo esc_html( $access->attorney_name ); ?>
								<?php if ( $access->bar_number ) : ?>
									<br><span class="ns-text-sm ns-text-muted"><?php esc_html_e( 'Bar #:', 'nonprofitsuite' ); ?> <?php echo esc_html( $access->bar_number ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo $access->specialization ? esc_html( $access->specialization ) : '<span class="ns-text-muted">-</span>'; ?></td>
							<td>
								<?php echo esc_html( $access->attorney_email ); ?>
								<?php if ( $access->attorney_phone ) : ?>
									<br><span class="ns-text-sm"><?php echo esc_html( $access->attorney_phone ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( date( 'M j, Y', strtotime( $access->granted_date ) ) ); ?></td>
							<td>
								<button class="ns-button ns-button-sm" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
									<?php esc_html_e( 'Manage', 'nonprofitsuite' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No active legal counsel. Grant access to your attorney to enable secure collaboration.', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>

	<!-- Active Legal Matters -->
	<?php if ( ! empty( $all_matters ) ) : ?>
		<div class="ns-card">
			<h2 class="ns-card-title"><?php esc_html_e( 'Active Legal Matters', 'nonprofitsuite' ); ?></h2>

			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Matter', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Priority', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Opened', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Estimated Cost', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $all_matters as $matter ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $matter->matter_name ); ?></strong>
								<?php if ( $matter->matter_number ) : ?>
									<br><span class="ns-text-sm ns-text-muted"><?php echo esc_html( $matter->matter_number ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $matter->matter_type ) ) ); ?></td>
							<td>
								<?php
								$priority_colors = array( 'high' => '#ef4444', 'medium' => '#f59e0b', 'low' => '#6b7280' );
								$color = isset( $priority_colors[ $matter->priority ] ) ? $priority_colors[ $matter->priority ] : '#6b7280';
								?>
								<span style="color: <?php echo esc_attr( $color ); ?>; font-weight: 600;">
									<?php echo esc_html( ucfirst( $matter->priority ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( date( 'M j, Y', strtotime( $matter->opened_date ) ) ); ?></td>
							<td>
								<?php if ( $matter->estimated_cost ) : ?>
									$<?php echo number_format( $matter->estimated_cost, 0 ); ?>
								<?php else : ?>
									<span class="ns-text-muted">-</span>
								<?php endif; ?>
							</td>
							<td>
								<button class="ns-button ns-button-sm" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
									<?php esc_html_e( 'View Details', 'nonprofitsuite' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
