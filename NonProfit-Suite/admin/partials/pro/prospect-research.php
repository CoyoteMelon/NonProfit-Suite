<?php
/**
 * Prospect Research (PRO) View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$pipeline_summary = NonprofitSuite_Prospect_Research::get_pipeline_summary();
$all_prospects = NonprofitSuite_Prospect_Research::get_prospects( array( 'limit' => 50 ) );
$needing_contact = NonprofitSuite_Prospect_Research::get_prospects_needing_contact( 30 );
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Prospect Research', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<p class="ns-text-muted">
		<?php esc_html_e( 'Research potential major donors, track wealth indicators, and manage your prospect pipeline.', 'nonprofitsuite' ); ?>
	</p>

	<?php if ( ! empty( $needing_contact ) && ! is_wp_error( $needing_contact ) ) : ?>
		<div class="ns-alert ns-alert-warning">
			<strong><?php esc_html_e( 'Attention Needed:', 'nonprofitsuite' ); ?></strong>
			<?php printf( _n( '%d prospect needs contact (30+ days since last interaction)', '%d prospects need contact (30+ days since last interaction)', count( $needing_contact ), 'nonprofitsuite' ), count( $needing_contact ) ); ?>
		</div>
	<?php endif; ?>

	<!-- Pipeline Summary -->
	<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px; margin-bottom: 20px;">
		<?php
		$stages = array(
			'identification' => __( 'Identification', 'nonprofitsuite' ),
			'qualification' => __( 'Qualification', 'nonprofitsuite' ),
			'cultivation' => __( 'Cultivation', 'nonprofitsuite' ),
			'solicitation' => __( 'Solicitation', 'nonprofitsuite' ),
			'stewardship' => __( 'Stewardship', 'nonprofitsuite' ),
		);

		foreach ( $stages as $stage_key => $stage_label ) :
			$stage_data = isset( $pipeline_summary[ $stage_key ] ) ? $pipeline_summary[ $stage_key ] : array( 'count' => 0, 'capacity' => 0 );
			?>
			<div class="ns-card" style="text-align: center; padding: 15px;">
				<h4 style="margin: 0 0 8px 0; font-size: 12px; color: #666;"><?php echo esc_html( $stage_label ); ?></h4>
				<p style="margin: 0; font-size: 28px; font-weight: 600; color: #2563eb;"><?php echo absint( $stage_data['count'] ); ?></p>
				<p class="ns-text-sm ns-text-muted" style="margin: 5px 0 0 0;">
					$<?php echo number_format( $stage_data['capacity'] / 1000, 0 ); ?>K <?php esc_html_e( 'Est.', 'nonprofitsuite' ); ?>
				</p>
			</div>
		<?php endforeach; ?>
	</div>

	<!-- Filters -->
	<div class="ns-card" style="margin-bottom: 20px;">
		<h2 class="ns-card-title"><?php esc_html_e( 'Filter Prospects', 'nonprofitsuite' ); ?></h2>
		<div style="display: flex; gap: 10px; flex-wrap: wrap;">
			<select id="filter-stage" style="padding: 8px;">
				<option value=""><?php esc_html_e( 'All Stages', 'nonprofitsuite' ); ?></option>
				<option value="identification"><?php esc_html_e( 'Identification', 'nonprofitsuite' ); ?></option>
				<option value="qualification"><?php esc_html_e( 'Qualification', 'nonprofitsuite' ); ?></option>
				<option value="cultivation"><?php esc_html_e( 'Cultivation', 'nonprofitsuite' ); ?></option>
				<option value="solicitation"><?php esc_html_e( 'Solicitation', 'nonprofitsuite' ); ?></option>
				<option value="stewardship"><?php esc_html_e( 'Stewardship', 'nonprofitsuite' ); ?></option>
			</select>

			<select id="filter-rating" style="padding: 8px;">
				<option value=""><?php esc_html_e( 'All Ratings', 'nonprofitsuite' ); ?></option>
				<option value="A+"><?php esc_html_e( 'A+ (>$1M)', 'nonprofitsuite' ); ?></option>
				<option value="A"><?php esc_html_e( 'A ($500K-$1M)', 'nonprofitsuite' ); ?></option>
				<option value="B"><?php esc_html_e( 'B ($100K-$500K)', 'nonprofitsuite' ); ?></option>
				<option value="C"><?php esc_html_e( 'C ($50K-$100K)', 'nonprofitsuite' ); ?></option>
				<option value="D"><?php esc_html_e( 'D ($25K-$50K)', 'nonprofitsuite' ); ?></option>
			</select>

			<button class="ns-button ns-button-primary" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
				<?php esc_html_e( 'Apply Filters', 'nonprofitsuite' ); ?>
			</button>

			<button class="ns-button" onclick="alert('<?php esc_attr_e( 'Feature coming soon', 'nonprofitsuite' ); ?>');">
				+ <?php esc_html_e( 'Add Prospect', 'nonprofitsuite' ); ?>
			</button>
		</div>
	</div>

	<!-- Prospect List -->
	<div class="ns-card">
		<h2 class="ns-card-title"><?php esc_html_e( 'Prospects', 'nonprofitsuite' ); ?></h2>

		<?php if ( ! empty( $all_prospects ) && ! is_wp_error( $all_prospects ) ) : ?>
			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Rating', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Capacity', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Ask Amount', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Stage', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Last Contact', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $all_prospects as $prospect ) :
						$last_contact = NonprofitSuite_Prospect_Research::get_last_contact_date( $prospect->id );
						$days_since_contact = $last_contact ? round( ( time() - strtotime( $last_contact ) ) / 86400 ) : null;
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $prospect->first_name . ' ' . $prospect->last_name ); ?></strong>
								<?php if ( $prospect->email ) : ?>
									<br><span class="ns-text-sm ns-text-muted"><?php echo esc_html( $prospect->email ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $prospect->rating ) : ?>
									<?php
									$rating_colors = array(
										'A+' => '#10b981',
										'A' => '#059669',
										'B' => '#2563eb',
										'C' => '#6b7280',
										'D' => '#9ca3af',
									);
									$color = isset( $rating_colors[ $prospect->rating ] ) ? $rating_colors[ $prospect->rating ] : '#6b7280';
									?>
									<span style="background: <?php echo esc_attr( $color ); ?>; color: #fff; padding: 4px 10px; border-radius: 4px; font-weight: 600; font-size: 12px;">
										<?php echo esc_html( $prospect->rating ); ?>
									</span>
								<?php else : ?>
									<span class="ns-text-muted">-</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $prospect->estimated_capacity ) : ?>
									$<?php echo number_format( $prospect->estimated_capacity, 0 ); ?>
								<?php else : ?>
									<span class="ns-text-muted">-</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $prospect->ask_amount ) : ?>
									$<?php echo number_format( $prospect->ask_amount, 0 ); ?>
								<?php else : ?>
									<span class="ns-text-muted">-</span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( ucfirst( $prospect->stage ) ); ?></td>
							<td>
								<?php if ( $last_contact ) : ?>
									<?php echo esc_html( date( 'M j, Y', strtotime( $last_contact ) ) ); ?>
									<br><span class="ns-text-sm <?php echo $days_since_contact > 30 ? 'ns-text-danger' : 'ns-text-muted'; ?>">
										(<?php echo absint( $days_since_contact ); ?> <?php esc_html_e( 'days ago', 'nonprofitsuite' ); ?>)
									</span>
								<?php else : ?>
									<span class="ns-text-muted"><?php esc_html_e( 'No contact', 'nonprofitsuite' ); ?></span>
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
		<?php else : ?>
			<p><?php esc_html_e( 'No prospects found. Add your first prospect to start building your major gift pipeline!', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>
</div>
