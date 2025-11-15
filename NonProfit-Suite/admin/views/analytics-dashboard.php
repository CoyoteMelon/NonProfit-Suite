<?php
/**
 * Analytics Dashboard View
 *
 * Displays analytics metrics and charts.
 *
 * @package NonprofitSuite
 * @subpackage Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$organization_id = 1;

// Get key metrics
$metrics_table = $wpdb->prefix . 'ns_analytics_metrics';
$period_date   = date( 'Y-m-d' );

$metrics = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$metrics_table} WHERE organization_id = %d AND time_period = 'daily' AND period_date = %s",
		$organization_id,
		$period_date
	),
	ARRAY_A
);

?>

<div class="wrap">
	<h1><?php esc_html_e( 'Analytics Dashboard', 'nonprofitsuite' ); ?></h1>

	<div class="ns-stats-grid">
		<?php foreach ( $metrics as $metric ) : ?>
			<div class="ns-stat-card">
				<div class="stat-content">
					<h3><?php echo esc_html( number_format( $metric['metric_value'], 2 ) ); ?></h3>
					<p><?php echo esc_html( ucwords( str_replace( '_', ' ', $metric['metric_name'] ) ) ); ?></p>
					<?php if ( $metric['change_percent'] !== null ) : ?>
						<span class="change <?php echo $metric['change_percent'] >= 0 ? 'positive' : 'negative'; ?>">
							<?php echo esc_html( number_format( abs( $metric['change_percent'] ), 1 ) . '%' ); ?>
						</span>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="ns-charts">
		<canvas id="metrics-chart"></canvas>
	</div>
</div>

<style>
.ns-stats-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 20px;
	margin: 20px 0;
}

.ns-stat-card {
	background: #fff;
	border: 1px solid #ccd0d4;
	padding: 20px;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.stat-content h3 {
	margin: 0 0 5px 0;
	font-size: 32px;
	font-weight: 600;
}

.stat-content p {
	margin: 0;
	color: #666;
	font-size: 14px;
}

.change {
	font-size: 12px;
	font-weight: 600;
}

.change.positive {
	color: #46b450;
}

.change.negative {
	color: #dc3232;
}

.ns-charts {
	background: #fff;
	padding: 20px;
	margin: 20px 0;
	border: 1px solid #ccd0d4;
}
</style>

<script>
jQuery(document).ready(function($) {
	const ctx = document.getElementById('metrics-chart');
	if (ctx) {
		new Chart(ctx, {
			type: 'line',
			data: {
				labels: <?php echo wp_json_encode( array_column( $metrics, 'period_date' ) ); ?>,
				datasets: [{
					label: 'Metrics',
					data: <?php echo wp_json_encode( array_column( $metrics, 'metric_value' ) ); ?>,
					borderColor: 'rgb(75, 192, 192)',
					tension: 0.1
				}]
			}
		});
	}
});
</script>
