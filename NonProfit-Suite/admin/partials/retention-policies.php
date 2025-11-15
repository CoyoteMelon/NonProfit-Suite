<?php
/**
 * Document Retention Policies View
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Check permissions
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( __( 'You do not have permission to access this page.', 'nonprofitsuite' ) );
}

// Handle form submission
if ( isset( $_POST['update_retention_policies'] ) && check_admin_referer( 'nonprofitsuite_retention_policies' ) ) {
	$policies = NonprofitSuite_Retention_Policy::get_all();

	foreach ( $policies as $policy ) {
		$retention_years = isset( $_POST['retention_years_' . $policy->id] ) ? absint( $_POST['retention_years_' . $policy->id] ) : 0;
		$auto_archive_days = isset( $_POST['auto_archive_days_' . $policy->id] ) ? absint( $_POST['auto_archive_days_' . $policy->id] ) : 365;
		$is_active = isset( $_POST['is_active_' . $policy->id] ) ? 1 : 0;

		NonprofitSuite_Retention_Policy::update( $policy->id, array(
			'retention_years' => $retention_years,
			'auto_archive_after_days' => $auto_archive_days,
			'is_active' => $is_active,
		) );
	}

	echo '<div class="notice notice-success"><p>' . __( 'Retention policies updated successfully.', 'nonprofitsuite' ) . '</p></div>';
}

// Handle bulk apply policies
if ( isset( $_POST['bulk_apply_policies'] ) && check_admin_referer( 'nonprofitsuite_bulk_apply_policies' ) ) {
	$results = NonprofitSuite_Document_Retention::bulk_apply_policies();
	echo '<div class="notice notice-success"><p>' . sprintf(
		__( 'Applied retention policies to %d documents.', 'nonprofitsuite' ),
		$results['updated_count']
	) . '</p></div>';
}

$policies = NonprofitSuite_Retention_Policy::get_all();
$stats = NonprofitSuite_Document_Retention::get_statistics();
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Document Retention Policies', 'nonprofitsuite' ); ?></h1>

	<div class="ns-retention-info" style="background: #fff; padding: 15px; margin: 20px 0; border-left: 4px solid #0073aa;">
		<h3><?php esc_html_e( 'About Document Retention', 'nonprofitsuite' ); ?></h3>
		<p>
			<?php esc_html_e( 'Document retention policies help you comply with federal, state, and local laws regarding how long different types of documents must be kept. Configure these settings based on your organization\'s legal requirements.', 'nonprofitsuite' ); ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'Important:', 'nonprofitsuite' ); ?></strong>
			<?php esc_html_e( 'Consult with your legal counsel or CPA to determine the appropriate retention periods for your organization. Requirements may vary by state and document type.', 'nonprofitsuite' ); ?>
		</p>
	</div>

	<!-- Statistics Dashboard -->
	<div class="ns-card" style="margin-bottom: 20px;">
		<div class="ns-card-header">
			<h2 class="ns-card-title"><?php esc_html_e( 'Retention Statistics', 'nonprofitsuite' ); ?></h2>
		</div>
		<div class="ns-card-body" style="padding: 20px;">
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
				<div class="ns-stat-box" style="background: #f0f0f1; padding: 15px; border-radius: 4px;">
					<div style="font-size: 32px; font-weight: bold; color: #0073aa;"><?php echo esc_html( $stats['total_documents'] ); ?></div>
					<div style="color: #646970;"><?php esc_html_e( 'Total Documents', 'nonprofitsuite' ); ?></div>
				</div>
				<div class="ns-stat-box" style="background: #f0f0f1; padding: 15px; border-radius: 4px;">
					<div style="font-size: 32px; font-weight: bold; color: #00a32a;"><?php echo esc_html( $stats['active_documents'] ); ?></div>
					<div style="color: #646970;"><?php esc_html_e( 'Active Documents', 'nonprofitsuite' ); ?></div>
				</div>
				<div class="ns-stat-box" style="background: #f0f0f1; padding: 15px; border-radius: 4px;">
					<div style="font-size: 32px; font-weight: bold; color: #996800;"><?php echo esc_html( $stats['archived_documents'] ); ?></div>
					<div style="color: #646970;"><?php esc_html_e( 'Archived Documents', 'nonprofitsuite' ); ?></div>
				</div>
				<div class="ns-stat-box" style="background: #f0f0f1; padding: 15px; border-radius: 4px;">
					<div style="font-size: 32px; font-weight: bold; color: #d63638;"><?php echo esc_html( $stats['expired_documents'] ); ?></div>
					<div style="color: #646970;"><?php esc_html_e( 'Expired Documents', 'nonprofitsuite' ); ?></div>
				</div>
			</div>
		</div>
	</div>

	<!-- Retention Policies Configuration -->
	<div class="ns-card">
		<div class="ns-card-header">
			<h2 class="ns-card-title"><?php esc_html_e( 'Configure Retention Policies', 'nonprofitsuite' ); ?></h2>
		</div>
		<div class="ns-card-body">
			<form method="post" action="">
				<?php wp_nonce_field( 'nonprofitsuite_retention_policies' ); ?>

				<table class="ns-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Policy Name', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Document Types', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Retention Period', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Auto-Archive After', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Active', 'nonprofitsuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $policies as $policy ) :
							$categories = NonprofitSuite_Retention_Policy::get_categories( $policy );
						?>
							<tr>
								<td>
									<strong><?php echo esc_html( $policy->policy_name ); ?></strong>
									<div style="color: #646970; font-size: 12px;">
										<?php echo esc_html( $policy->description ); ?>
									</div>
								</td>
								<td>
									<?php echo esc_html( implode( ', ', array_map( 'ucfirst', $categories ) ) ); ?>
								</td>
								<td>
									<input type="number"
										   name="retention_years_<?php echo esc_attr( $policy->id ); ?>"
										   value="<?php echo esc_attr( $policy->retention_years ); ?>"
										   min="0"
										   style="width: 80px;">
									<span style="color: #646970;">
										<?php echo $policy->retention_years == 0 ? __( 'Forever', 'nonprofitsuite' ) : __( 'years', 'nonprofitsuite' ); ?>
									</span>
								</td>
								<td>
									<input type="number"
										   name="auto_archive_days_<?php echo esc_attr( $policy->id ); ?>"
										   value="<?php echo esc_attr( $policy->auto_archive_after_days ); ?>"
										   min="0"
										   style="width: 80px;">
									<span style="color: #646970;"><?php esc_html_e( 'days', 'nonprofitsuite' ); ?></span>
								</td>
								<td>
									<input type="checkbox"
										   name="is_active_<?php echo esc_attr( $policy->id ); ?>"
										   value="1"
										   <?php checked( $policy->is_active, 1 ); ?>>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<div style="margin-top: 20px;">
					<button type="submit" name="update_retention_policies" class="button button-primary">
						<?php esc_html_e( 'Save Retention Policies', 'nonprofitsuite' ); ?>
					</button>
				</div>
			</form>
		</div>
	</div>

	<!-- Bulk Actions -->
	<div class="ns-card" style="margin-top: 20px;">
		<div class="ns-card-header">
			<h2 class="ns-card-title"><?php esc_html_e( 'Bulk Actions', 'nonprofitsuite' ); ?></h2>
		</div>
		<div class="ns-card-body">
			<form method="post" action="">
				<?php wp_nonce_field( 'nonprofitsuite_bulk_apply_policies' ); ?>

				<p>
					<?php esc_html_e( 'Apply retention policies to all existing documents that don\'t have a policy assigned.', 'nonprofitsuite' ); ?>
				</p>

				<button type="submit" name="bulk_apply_policies" class="button button-secondary">
					<?php esc_html_e( 'Apply Policies to Existing Documents', 'nonprofitsuite' ); ?>
				</button>
			</form>
		</div>
	</div>

	<!-- Compliance Guidelines -->
	<div class="ns-card" style="margin-top: 20px;">
		<div class="ns-card-header">
			<h2 class="ns-card-title"><?php esc_html_e( 'General Retention Guidelines', 'nonprofitsuite' ); ?></h2>
		</div>
		<div class="ns-card-body" style="padding: 20px;">
			<p><em><?php esc_html_e( 'These are general guidelines only. Consult with legal counsel for your specific requirements.', 'nonprofitsuite' ); ?></em></p>

			<h4><?php esc_html_e( 'Federal Requirements (IRS)', 'nonprofitsuite' ); ?></h4>
			<ul>
				<li><?php esc_html_e( 'Tax returns and supporting documents: 7 years minimum', 'nonprofitsuite' ); ?></li>
				<li><?php esc_html_e( 'Employment tax records: 4 years minimum', 'nonprofitsuite' ); ?></li>
				<li><?php esc_html_e( 'Corporate records (articles, bylaws, board minutes): Permanent', 'nonprofitsuite' ); ?></li>
				<li><?php esc_html_e( 'Financial statements: Permanent', 'nonprofitsuite' ); ?></li>
			</ul>

			<h4><?php esc_html_e( 'Common State Requirements', 'nonprofitsuite' ); ?></h4>
			<ul>
				<li><?php esc_html_e( 'State registration and compliance filings: Permanent', 'nonprofitsuite' ); ?></li>
				<li><?php esc_html_e( 'Grant agreements and reports: Per grant terms or 7 years', 'nonprofitsuite' ); ?></li>
				<li><?php esc_html_e( 'Personnel files: 7 years after termination', 'nonprofitsuite' ); ?></li>
			</ul>
		</div>
	</div>
</div>
