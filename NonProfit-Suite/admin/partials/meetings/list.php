<?php
/**
 * Meetings List View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Handle bulk actions
$bulk_results = null;
if ( isset( $_POST['bulk_action'] ) && isset( $_POST['bulk_select'] ) && check_admin_referer( 'ns_bulk_meetings', 'ns_bulk_nonce' ) ) {
	$action = sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) );
	$ids = array_map( 'absint', $_POST['bulk_select'] );

	if ( $action === 'delete' ) {
		$bulk_results = NonprofitSuite_Bulk_Operations::bulk_delete(
			'NonprofitSuite_Meetings',
			$ids,
			'ns_manage_meetings'
		);
	} elseif ( $action === 'archive' ) {
		$bulk_results = NonprofitSuite_Bulk_Operations::bulk_update(
			'NonprofitSuite_Meetings',
			$ids,
			array( 'status' => 'archived' ),
			'ns_manage_meetings'
		);
	}
}

$meetings = NonprofitSuite_Meetings::get_all( array( 'orderby' => 'meeting_date', 'order' => 'DESC', 'limit' => 50 ) );
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Meetings', 'nonprofitsuite' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-meetings&action=add' ) ); ?>" class="ns-button ns-button-primary">
		<?php esc_html_e( 'Add New Meeting', 'nonprofitsuite' ); ?>
	</a>

	<?php
	// Display bulk operation results
	if ( $bulk_results ) {
		NonprofitSuite_Bulk_Operations::display_bulk_results( $bulk_results );
	}
	?>

	<div class="ns-card ns-mt-20">
		<form method="post" action="">
			<?php wp_nonce_field( 'ns_bulk_meetings', 'ns_bulk_nonce' ); ?>

			<?php
			// Render bulk actions dropdown
			NonprofitSuite_Bulk_Operations::render_bulk_actions_dropdown(
				'bulk_action',
				array(
					'delete' => __( 'Delete', 'nonprofitsuite' ),
					'archive' => __( 'Archive', 'nonprofitsuite' ),
				)
			);
			?>
		<?php if ( ! empty( $meetings ) ) : ?>
			<table class="ns-table">
				<thead>
					<tr>
						<?php NonprofitSuite_Bulk_Operations::render_select_all_checkbox( 'bulk_select' ); ?>
						<th><?php esc_html_e( 'Meeting', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Type', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Date', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $meetings as $meeting ) : ?>
						<tr>
							<?php NonprofitSuite_Bulk_Operations::render_row_checkbox( 'bulk_select', $meeting->id ); ?>
							<td><strong><?php echo esc_html( $meeting->title ); ?></strong></td>
							<td><?php echo esc_html( ucfirst( $meeting->meeting_type ) ); ?></td>
							<td><?php echo esc_html( NonprofitSuite_Utilities::format_datetime( $meeting->meeting_date ) ); ?></td>
							<td><?php echo NonprofitSuite_Utilities::get_status_badge( $meeting->status ); ?></td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-meetings&action=edit&id=' . $meeting->id ) ); ?>"><?php esc_html_e( 'Edit', 'nonprofitsuite' ); ?></a> |
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-meetings&action=agenda&id=' . $meeting->id ) ); ?>"><?php esc_html_e( 'Agenda', 'nonprofitsuite' ); ?></a> |
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-meetings&action=minutes&id=' . $meeting->id ) ); ?>"><?php esc_html_e( 'Minutes', 'nonprofitsuite' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No meetings found. Create your first meeting!', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
		</form>
	</div>
</div>

<?php NonprofitSuite_Bulk_Operations::render_bulk_actions_script(); ?>
