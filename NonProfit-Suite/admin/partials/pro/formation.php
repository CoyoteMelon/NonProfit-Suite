<?php
/**
 * Formation Assistant (PRO) View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$progress = NonprofitSuite_Formation::get_formation_progress();
$current_phase = NonprofitSuite_Formation::get_current_phase();
$next_steps = NonprofitSuite_Formation::get_next_steps( 5 );
$completion_percentage = NonprofitSuite_Formation::get_completion_percentage();
$all_steps = NonprofitSuite_Formation::get_all_steps();
$phase_names = NonprofitSuite_Formation::get_phase_names();

$steps_completed = ! empty( $progress->steps_completed ) ? json_decode( $progress->steps_completed, true ) : array();
if ( ! is_array( $steps_completed ) ) {
	$steps_completed = array();
}
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Formation Assistant', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<p class="ns-text-muted">
		<?php esc_html_e( 'Track your nonprofit formation journey through all 7 phases, from initial planning to public launch.', 'nonprofitsuite' ); ?>
	</p>

	<!-- Progress Overview Card -->
	<div class="ns-card" style="margin-bottom: 20px;">
		<h2 class="ns-card-title"><?php esc_html_e( 'Formation Progress', 'nonprofitsuite' ); ?></h2>

		<div style="margin: 20px 0;">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
				<span><strong><?php echo absint( $completion_percentage ); ?>%</strong> <?php esc_html_e( 'Complete', 'nonprofitsuite' ); ?></span>
				<span class="ns-text-muted"><?php esc_html_e( 'Current Phase:', 'nonprofitsuite' ); ?> <strong><?php echo esc_html( isset( $phase_names[ $current_phase ] ) ? $phase_names[ $current_phase ] : ucfirst( $current_phase ) ); ?></strong></span>
			</div>
			<div style="background: #e0e0e0; height: 20px; border-radius: 10px; overflow: hidden;">
				<div style="background: #10b981; height: 100%; width: <?php echo absint( $completion_percentage ); ?>%; transition: width 0.3s;"></div>
			</div>
		</div>

		<?php if ( ! empty( $next_steps ) ) : ?>
			<div style="margin-top: 20px;">
				<h3 style="margin-bottom: 10px; font-size: 16px;"><?php esc_html_e( 'Next Steps', 'nonprofitsuite' ); ?></h3>
				<ul style="list-style: none; padding: 0; margin: 0;">
					<?php foreach ( array_slice( $next_steps, 0, 3 ) as $step ) : ?>
						<li style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
							<span class="ns-text-sm ns-text-muted"><?php echo esc_html( isset( $phase_names[ $step['phase'] ] ) ? $phase_names[ $step['phase'] ] : ucfirst( $step['phase'] ) ); ?>:</span>
							<?php echo esc_html( $step['title'] ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</div>

	<!-- Phase Tabs -->
	<div class="ns-card">
		<div style="border-bottom: 2px solid #e0e0e0; margin-bottom: 20px;">
			<div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: -2px;">
				<?php foreach ( $phase_names as $phase_key => $phase_name ) :
					$phase_steps = isset( $all_steps[ $phase_key ] ) ? $all_steps[ $phase_key ] : array();
					$completed_in_phase = 0;
					foreach ( $phase_steps as $step_id => $step_title ) {
						$step_key = $phase_key . '_' . $step_id;
						if ( isset( $steps_completed[ $step_key ] ) ) {
							$completed_in_phase++;
						}
					}
					$total_in_phase = count( $phase_steps );
					$is_current = ( $phase_key === $current_phase );
					?>
					<button
						class="ns-tab-button<?php echo $is_current ? ' ns-tab-active' : ''; ?>"
						data-phase="<?php echo esc_attr( $phase_key ); ?>"
						style="padding: 10px 15px; border: none; background: <?php echo $is_current ? '#2563eb' : 'transparent'; ?>; color: <?php echo $is_current ? '#fff' : '#666'; ?>; cursor: pointer; border-bottom: 2px solid <?php echo $is_current ? '#2563eb' : 'transparent'; ?>; font-weight: 500;">
						<?php echo esc_html( $phase_name ); ?>
						<span style="font-size: 12px; opacity: 0.8;">(<?php echo absint( $completed_in_phase ); ?>/<?php echo absint( $total_in_phase ); ?>)</span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Phase Content -->
		<?php foreach ( $all_steps as $phase_key => $phase_steps ) : ?>
			<div class="ns-phase-content" data-phase="<?php echo esc_attr( $phase_key ); ?>" style="display: <?php echo $phase_key === $current_phase ? 'block' : 'none'; ?>;">
				<h2 class="ns-card-title"><?php echo esc_html( isset( $phase_names[ $phase_key ] ) ? $phase_names[ $phase_key ] : ucfirst( $phase_key ) ); ?></h2>

				<div style="margin-top: 20px;">
					<?php foreach ( $phase_steps as $step_id => $step_title ) :
						$step_key = $phase_key . '_' . $step_id;
						$is_completed = isset( $steps_completed[ $step_key ] );
						?>
						<div style="display: flex; align-items: flex-start; padding: 12px; border: 1px solid #e0e0e0; border-radius: 4px; margin-bottom: 10px; background: <?php echo $is_completed ? '#f0fdf4' : '#fff'; ?>;">
							<input
								type="checkbox"
								class="ns-formation-checkbox"
								data-phase="<?php echo esc_attr( $phase_key ); ?>"
								data-step="<?php echo esc_attr( $step_id ); ?>"
								<?php checked( $is_completed ); ?>
								style="margin-right: 12px; margin-top: 2px; width: 18px; height: 18px; cursor: pointer;"
							/>
							<div style="flex: 1;">
								<label style="cursor: pointer; <?php echo $is_completed ? 'text-decoration: line-through; opacity: 0.7;' : ''; ?>">
									<?php echo esc_html( $step_title ); ?>
								</label>
								<?php if ( $is_completed ) : ?>
									<div class="ns-text-sm ns-text-muted" style="margin-top: 4px;">
										<?php
										$completed_date = $steps_completed[ $step_key ];
										printf(
											__( 'Completed: %s', 'nonprofitsuite' ),
											esc_html( date( 'M j, Y', strtotime( $completed_date ) ) )
										);
										?>
									</div>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<!-- Key Milestone Dates -->
	<?php if ( ! is_wp_error( $progress ) ) : ?>
		<div class="ns-card" style="margin-top: 20px;">
			<h2 class="ns-card-title"><?php esc_html_e( 'Key Milestone Dates', 'nonprofitsuite' ); ?></h2>

			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
				<div>
					<label style="display: block; font-weight: 500; margin-bottom: 5px;">
						<?php esc_html_e( 'Incorporation State', 'nonprofitsuite' ); ?>
					</label>
					<input type="text" class="ns-input" value="<?php echo esc_attr( $progress->incorporation_state ?? '' ); ?>" readonly style="width: 100%; padding: 8px;">
				</div>

				<div>
					<label style="display: block; font-weight: 500; margin-bottom: 5px;">
						<?php esc_html_e( 'Incorporation Date', 'nonprofitsuite' ); ?>
					</label>
					<input type="text" class="ns-input" value="<?php echo esc_attr( $progress->incorporation_date ? date( 'M j, Y', strtotime( $progress->incorporation_date ) ) : '' ); ?>" readonly style="width: 100%; padding: 8px;">
				</div>

				<div>
					<label style="display: block; font-weight: 500; margin-bottom: 5px;">
						<?php esc_html_e( 'EIN', 'nonprofitsuite' ); ?>
					</label>
					<input type="text" class="ns-input" value="<?php echo esc_attr( $progress->ein ?? '' ); ?>" readonly style="width: 100%; padding: 8px;">
				</div>

				<div>
					<label style="display: block; font-weight: 500; margin-bottom: 5px;">
						<?php esc_html_e( 'IRS Determination Date', 'nonprofitsuite' ); ?>
					</label>
					<input type="text" class="ns-input" value="<?php echo esc_attr( $progress->irs_determination_date ? date( 'M j, Y', strtotime( $progress->irs_determination_date ) ) : '' ); ?>" readonly style="width: 100%; padding: 8px;">
				</div>

				<div>
					<label style="display: block; font-weight: 500; margin-bottom: 5px;">
						<?php esc_html_e( 'State Registration Date', 'nonprofitsuite' ); ?>
					</label>
					<input type="text" class="ns-input" value="<?php echo esc_attr( $progress->state_registration_date ? date( 'M j, Y', strtotime( $progress->state_registration_date ) ) : '' ); ?>" readonly style="width: 100%; padding: 8px;">
				</div>

				<div>
					<label style="display: block; font-weight: 500; margin-bottom: 5px;">
						<?php esc_html_e( 'Bylaws Adopted Date', 'nonprofitsuite' ); ?>
					</label>
					<input type="text" class="ns-input" value="<?php echo esc_attr( $progress->bylaws_adopted_date ? date( 'M j, Y', strtotime( $progress->bylaws_adopted_date ) ) : '' ); ?>" readonly style="width: 100%; padding: 8px;">
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
	// Tab switching
	$('.ns-tab-button').on('click', function() {
		var phase = $(this).data('phase');

		$('.ns-tab-button').removeClass('ns-tab-active').css({
			'background': 'transparent',
			'color': '#666',
			'border-bottom-color': 'transparent'
		});

		$(this).addClass('ns-tab-active').css({
			'background': '#2563eb',
			'color': '#fff',
			'border-bottom-color': '#2563eb'
		});

		$('.ns-phase-content').hide();
		$('.ns-phase-content[data-phase="' + phase + '"]').show();
	});

	// Checkbox handling (read-only for now - would need AJAX handler)
	$('.ns-formation-checkbox').on('change', function() {
		alert('<?php esc_html_e( 'Step completion tracking feature coming soon!', 'nonprofitsuite' ); ?>');
		// Would call AJAX to update progress via NonprofitSuite_Formation::update_progress()
	});
});
</script>
