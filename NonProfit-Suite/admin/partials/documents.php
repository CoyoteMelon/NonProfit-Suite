<?php
/**
 * Documents View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Determine view type (active or archived)
$view = isset( $_GET['view'] ) ? sanitize_text_field( $_GET['view'] ) : 'active';

if ( $view === 'archived' ) {
	$documents = NonprofitSuite_Documents::get_archived( array( 'limit' => 50 ) );
} else {
	global $wpdb;
	$table = $wpdb->prefix . 'ns_documents';
	$documents = $wpdb->get_results(
		"SELECT * FROM {$table} WHERE is_archived = 0 AND is_expired = 0 ORDER BY created_at DESC LIMIT 50"
	);
}

$categories = NonprofitSuite_Documents::get_categories();
$stats = NonprofitSuite_Documents::get_retention_stats();
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Documents', 'nonprofitsuite' ); ?></h1>

	<!-- View Tabs -->
	<div class="ns-tabs" style="margin-bottom: 20px;">
		<a href="?page=nonprofitsuite-documents&view=active"
		   class="ns-tab <?php echo $view === 'active' ? 'active' : ''; ?>"
		   style="padding: 10px 20px; text-decoration: none; border-bottom: <?php echo $view === 'active' ? '2px solid #0073aa' : 'none'; ?>">
			<?php esc_html_e( 'Active Documents', 'nonprofitsuite' ); ?>
			<span class="count">(<?php echo esc_html( $stats['active_documents'] ); ?>)</span>
		</a>
		<a href="?page=nonprofitsuite-documents&view=archived"
		   class="ns-tab <?php echo $view === 'archived' ? 'active' : ''; ?>"
		   style="padding: 10px 20px; text-decoration: none; border-bottom: <?php echo $view === 'archived' ? '2px solid #0073aa' : 'none'; ?>">
			<?php esc_html_e( 'Archived Documents', 'nonprofitsuite' ); ?>
			<span class="count">(<?php echo esc_html( $stats['archived_documents'] ); ?>)</span>
		</a>
	</div>

	<div class="ns-card">
		<div class="ns-card-header">
			<h2 class="ns-card-title">
				<?php echo $view === 'archived' ? esc_html__( 'Archived Documents', 'nonprofitsuite' ) : esc_html__( 'Active Documents', 'nonprofitsuite' ); ?>
			</h2>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<div style="margin-left: auto;">
					<a href="?page=nonprofitsuite-retention-policies" class="button button-secondary">
						<?php esc_html_e( 'Manage Retention Policies', 'nonprofitsuite' ); ?>
					</a>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $documents ) ) : ?>
			<table class="ns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Document', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Category', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Uploaded', 'nonprofitsuite' ); ?></th>
						<?php if ( $view === 'archived' ) : ?>
							<th><?php esc_html_e( 'Archived', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Retention Policy', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Expires', 'nonprofitsuite' ); ?></th>
						<?php endif; ?>
						<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $documents as $doc ) :
						$retention_policy = NonprofitSuite_Retention_Policy::get_by_key( $doc->retention_policy );
					?>
						<tr>
							<td><strong><?php echo esc_html( $doc->title ); ?></strong></td>
							<td><?php echo esc_html( ucfirst( $doc->category ) ); ?></td>
							<td><?php echo esc_html( NonprofitSuite_Utilities::format_date( $doc->created_at ) ); ?></td>
							<?php if ( $view === 'archived' ) : ?>
								<td><?php echo esc_html( NonprofitSuite_Utilities::format_date( $doc->archived_at ) ); ?></td>
								<td>
									<?php
									echo $retention_policy ? esc_html( $retention_policy->policy_name ) : esc_html__( 'N/A', 'nonprofitsuite' );
									?>
								</td>
								<td>
									<?php
									if ( $doc->expiration_date ) {
										echo esc_html( NonprofitSuite_Utilities::format_date( $doc->expiration_date ) );
									} else {
										echo '<span style="color: #00a32a;">' . esc_html__( 'Never', 'nonprofitsuite' ) . '</span>';
									}
									?>
								</td>
							<?php endif; ?>
							<td>
								<a href="<?php echo esc_url( wp_get_attachment_url( $doc->attachment_id ) ); ?>" target="_blank"><?php esc_html_e( 'View', 'nonprofitsuite' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No documents found.', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>
</div>
