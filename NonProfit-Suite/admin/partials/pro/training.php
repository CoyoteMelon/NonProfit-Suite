<?php
/**
 * Training Module (PRO) View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$courses = NonprofitSuite_Training::get_courses( array( 'status' => 'active', 'limit' => 100 ) );
$upcoming_renewals = NonprofitSuite_Training::get_upcoming_renewals( 60 );

// Get all people for completion matrix (limit to 20 for performance)
global $wpdb;
$people = $wpdb->get_results( "SELECT id, first_name, last_name FROM {$wpdb->prefix}ns_people ORDER BY last_name ASC LIMIT 20" );
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Training Module', 'nonprofitsuite' ); ?> <span class="ns-pro-badge">PRO</span></h1>

	<?php if ( ! empty( $upcoming_renewals ) && ! is_wp_error( $upcoming_renewals ) ) : ?>
		<div class="ns-alert ns-alert-warning">
			<strong><?php esc_html_e( 'Renewals Due Soon:', 'nonprofitsuite' ); ?></strong>
			<?php printf( _n( '%d training certification expires within 60 days', '%d training certifications expire within 60 days', count( $upcoming_renewals ), 'nonprofitsuite' ), count( $upcoming_renewals ) ); ?>
		</div>
	<?php endif; ?>

	<!-- Training Courses Library -->
	<div class="ns-card" style="margin-bottom: 20px;">
		<div class="ns-card-header">
			<h2 class="ns-card-title"><?php esc_html_e( 'Training Courses', 'nonprofitsuite' ); ?></h2>
			<button class="ns-button ns-button-primary" onclick="alert('Add course feature coming soon');">
				<?php esc_html_e( 'Add Course', 'nonprofitsuite' ); ?>
			</button>
		</div>

		<?php if ( ! empty( $courses ) && ! is_wp_error( $courses ) ) : ?>
			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Course Name', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Category', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Required For', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Renewal', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Duration', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $courses as $course ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $course->course_name ); ?></strong>
								<?php if ( $course->description ) : ?>
									<br><span class="ns-text-sm ns-text-muted"><?php echo esc_html( wp_trim_words( $course->description, 15 ) ); ?></span>
								<?php endif; ?>
								<?php if ( $course->external_link ) : ?>
									<br><a href="<?php echo esc_url( $course->external_link ); ?>" target="_blank" class="ns-text-sm"><?php esc_html_e( 'View Course', 'nonprofitsuite' ); ?> ↗</a>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $course->category ) ) ); ?></td>
							<td>
								<?php
								$roles = ! empty( $course->required_for_roles ) ? json_decode( $course->required_for_roles, true ) : array();
								if ( is_array( $roles ) && ! empty( $roles ) ) {
									echo esc_html( implode( ', ', array_map( 'ucfirst', $roles ) ) );
								} else {
									echo '<span class="ns-text-muted">-</span>';
								}
								?>
							</td>
							<td>
								<?php if ( $course->renewal_frequency ) : ?>
									<?php
									$frequencies = array(
										'never' => __( 'Never', 'nonprofitsuite' ),
										'annually' => __( 'Annual', 'nonprofitsuite' ),
										'biannually' => __( 'Biannual', 'nonprofitsuite' ),
										'3_years' => __( 'Every 3 years', 'nonprofitsuite' ),
									);
									echo esc_html( isset( $frequencies[ $course->renewal_frequency ] ) ? $frequencies[ $course->renewal_frequency ] : ucfirst( $course->renewal_frequency ) );
									?>
								<?php else : ?>
									<span class="ns-text-muted">-</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $course->duration_hours ) : ?>
									<?php echo absint( $course->duration_hours ); ?> <?php esc_html_e( 'hours', 'nonprofitsuite' ); ?>
								<?php else : ?>
									<span class="ns-text-muted">-</span>
								<?php endif; ?>
							</td>
							<td><?php echo NonprofitSuite_Utilities::get_status_badge( $course->status ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No training courses found. Add your first course to start tracking training!', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>

	<!-- Training Completion Matrix -->
	<?php if ( ! empty( $people ) && ! empty( $courses ) ) : ?>
		<div class="ns-card" style="margin-bottom: 20px;">
			<h2 class="ns-card-title"><?php esc_html_e( 'Training Completion Matrix', 'nonprofitsuite' ); ?></h2>
			<p class="ns-text-sm ns-text-muted" style="margin-bottom: 15px;">
				<?php esc_html_e( 'Track who has completed required training. Green = completed, Red = missing or expired.', 'nonprofitsuite' ); ?>
			</p>

			<div style="overflow-x: auto;">
				<table class="ns-table" style="min-width: 800px;">
					<thead>
						<tr>
							<th style="position: sticky; left: 0; background: #f9fafb; z-index: 10;"><?php esc_html_e( 'Person', 'nonprofitsuite' ); ?></th>
							<?php foreach ( array_slice( $courses, 0, 10 ) as $course ) : ?>
								<th style="min-width: 120px; text-align: center; font-size: 12px;">
									<?php echo esc_html( wp_trim_words( $course->course_name, 3 ) ); ?>
								</th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $people as $person ) :
							$completions = NonprofitSuite_Training::get_completions( $person->id );
							$completion_map = array();
							if ( ! is_wp_error( $completions ) && is_array( $completions ) ) {
								foreach ( $completions as $completion ) {
									$completion_map[ $completion->course_id ] = $completion;
								}
							}
							?>
							<tr>
								<td style="position: sticky; left: 0; background: #fff; z-index: 10; font-weight: 500;">
									<?php echo esc_html( $person->first_name . ' ' . $person->last_name ); ?>
								</td>
								<?php foreach ( array_slice( $courses, 0, 10 ) as $course ) :
									$completion = isset( $completion_map[ $course->id ] ) ? $completion_map[ $course->id ] : null;
									$is_completed = $completion && $completion->status === 'completed';
									$is_expired = false;

									if ( $is_completed && $completion->next_due_date && strtotime( $completion->next_due_date ) < time() ) {
										$is_expired = true;
									}

									$bg_color = $is_completed && ! $is_expired ? '#dcfce7' : '#fee2e2';
									$text = $is_completed && ! $is_expired ? '✓' : '✗';
									$text_color = $is_completed && ! $is_expired ? '#166534' : '#991b1b';
									?>
									<td style="text-align: center; background: <?php echo esc_attr( $bg_color ); ?>; color: <?php echo esc_attr( $text_color ); ?>; font-weight: 600; font-size: 16px;">
										<?php echo esc_html( $text ); ?>
										<?php if ( $is_completed && $completion->completion_date ) : ?>
											<div class="ns-text-sm" style="font-weight: normal; opacity: 0.8;">
												<?php echo esc_html( date( 'm/d/y', strtotime( $completion->completion_date ) ) ); ?>
											</div>
											<?php if ( $is_expired ) : ?>
												<div class="ns-text-sm" style="font-weight: 600; color: #991b1b;">
													<?php esc_html_e( 'EXPIRED', 'nonprofitsuite' ); ?>
												</div>
											<?php endif; ?>
										<?php endif; ?>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ( count( $people ) >= 20 ) : ?>
				<p class="ns-text-sm ns-text-muted" style="margin-top: 10px;">
					<?php esc_html_e( 'Showing first 20 people. Use filters to view specific individuals.', 'nonprofitsuite' ); ?>
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<!-- Upcoming Renewals -->
	<?php if ( ! empty( $upcoming_renewals ) && ! is_wp_error( $upcoming_renewals ) ) : ?>
		<div class="ns-card">
			<h2 class="ns-card-title"><?php esc_html_e( 'Upcoming Training Renewals', 'nonprofitsuite' ); ?></h2>

			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Person', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Course', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Completed', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Due Date', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Days Until Due', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $upcoming_renewals as $renewal ) :
						$days_until = round( ( strtotime( $renewal->next_due_date ) - time() ) / 86400 );
						$is_overdue = $days_until < 0;
						$class = $is_overdue ? 'ns-text-danger' : ( $days_until < 30 ? 'ns-text-warning' : '' );
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $renewal->first_name . ' ' . $renewal->last_name ); ?></strong>
								<?php if ( $renewal->email ) : ?>
									<br><span class="ns-text-sm"><?php echo esc_html( $renewal->email ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $renewal->course_name ); ?></td>
							<td><?php echo esc_html( date( 'M j, Y', strtotime( $renewal->completion_date ) ) ); ?></td>
							<td>
								<span class="<?php echo $class; ?>" style="font-weight: 600;">
									<?php echo esc_html( date( 'M j, Y', strtotime( $renewal->next_due_date ) ) ); ?>
									<?php if ( $is_overdue ) : ?>
										<br><strong class="ns-text-sm"><?php esc_html_e( 'OVERDUE', 'nonprofitsuite' ); ?></strong>
									<?php endif; ?>
								</span>
							</td>
							<td>
								<span class="<?php echo $class; ?>" style="font-weight: 600;">
									<?php
									if ( $is_overdue ) {
										printf( __( '%d days overdue', 'nonprofitsuite' ), abs( $days_until ) );
									} else {
										printf( _n( '%d day', '%d days', $days_until, 'nonprofitsuite' ), $days_until );
									}
									?>
								</span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

	<!-- Missing Training Report -->
	<div class="ns-card" style="margin-top: 20px;">
		<h2 class="ns-card-title"><?php esc_html_e( 'Compliance Report', 'nonprofitsuite' ); ?></h2>
		<p class="ns-text-muted"><?php esc_html_e( 'Check training compliance for all staff and board members.', 'nonprofitsuite' ); ?></p>

		<?php if ( ! empty( $people ) ) : ?>
			<table class="ns-table" style="margin-top: 15px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Person', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Compliance Status', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Completed', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Missing', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Expired', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_slice( $people, 0, 10 ) as $person ) :
						$compliance = NonprofitSuite_Training::check_compliance( $person->id );
						if ( is_wp_error( $compliance ) ) continue;

						$is_compliant = $compliance['compliant'];
						$completed = count( $compliance['completed'] );
						$missing = count( $compliance['missing'] );
						$expired = count( $compliance['expired'] );
						?>
						<tr>
							<td><strong><?php echo esc_html( $person->first_name . ' ' . $person->last_name ); ?></strong></td>
							<td>
								<?php if ( $is_compliant ) : ?>
									<span style="background: #dcfce7; color: #166534; padding: 4px 12px; border-radius: 12px; font-weight: 600; font-size: 12px;">
										✓ <?php esc_html_e( 'COMPLIANT', 'nonprofitsuite' ); ?>
									</span>
								<?php else : ?>
									<span style="background: #fee2e2; color: #991b1b; padding: 4px 12px; border-radius: 12px; font-weight: 600; font-size: 12px;">
										✗ <?php esc_html_e( 'NON-COMPLIANT', 'nonprofitsuite' ); ?>
									</span>
								<?php endif; ?>
							</td>
							<td><span style="color: #10b981; font-weight: 600;"><?php echo absint( $completed ); ?></span></td>
							<td><span style="color: #ef4444; font-weight: 600;"><?php echo absint( $missing ); ?></span></td>
							<td><span style="color: #f59e0b; font-weight: 600;"><?php echo absint( $expired ); ?></span></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No people found to check compliance.', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>
</div>
