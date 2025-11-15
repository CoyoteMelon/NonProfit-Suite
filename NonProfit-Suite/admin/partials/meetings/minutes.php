<?php
/**
 * Minutes Recorder View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$meeting_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

// Handle form submission with CSRF protection
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['ns_minutes_nonce'] ) && wp_verify_nonce( $_POST['ns_minutes_nonce'], 'ns_save_minutes' ) ) {
	if ( ! current_user_can( 'ns_edit_minutes' ) ) {
		wp_die( esc_html__( 'You do not have permission to edit minutes.', 'nonprofitsuite' ) );
	}

	$content = isset( $_POST['minutes-editor'] ) ? wp_kses_post( $_POST['minutes-editor'] ) : '';
	$minutes_id = isset( $_POST['minutes_id'] ) ? intval( $_POST['minutes_id'] ) : 0;

	$data = array(
		'meeting_id' => $meeting_id,
		'content' => $content,
	);

	if ( $minutes_id ) {
		NonprofitSuite_Minutes::update( $minutes_id, $data );
	} else {
		NonprofitSuite_Minutes::create( $data );
	}

	wp_redirect( admin_url( 'admin.php?page=nonprofitsuite-meetings&action=minutes&id=' . $meeting_id . '&saved=1' ) );
	exit;
}

$meeting = NonprofitSuite_Meetings::get( $meeting_id );
$minutes = NonprofitSuite_Minutes::get_by_meeting( $meeting_id );
?>

<div class="wrap ns-container">
	<h1><?php printf( esc_html__( 'Minutes: %s', 'nonprofitsuite' ), esc_html( $meeting->title ) ); ?></h1>

	<div class="ns-card">
		<div class="ns-card-header">
			<h2 class="ns-card-title"><?php esc_html_e( 'Meeting Minutes', 'nonprofitsuite' ); ?></h2>
			<div>
				<?php if ( $minutes && $minutes->status === 'approved' ) : ?>
					<span class="ns-badge ns-badge-success"><?php esc_html_e( 'Approved', 'nonprofitsuite' ); ?></span>
				<?php elseif ( $minutes && $minutes->status === 'draft' ) : ?>
					<span class="ns-badge ns-badge-warning"><?php esc_html_e( 'Draft', 'nonprofitsuite' ); ?></span>
				<?php endif; ?>
				<span id="last-saved"></span>
			</div>
		</div>

		<form method="post">
			<?php wp_nonce_field( 'ns_save_minutes', 'ns_minutes_nonce' ); ?>
			<input type="hidden" id="meeting_id" name="meeting_id" value="<?php echo esc_attr( $meeting_id ); ?>">
			<input type="hidden" id="minutes_id" name="minutes_id" value="<?php echo $minutes ? esc_attr( $minutes->id ) : ''; ?>">

			<?php
			$content = $minutes ? $minutes->content : '';
			wp_editor( $content, 'minutes-editor', array(
				'textarea_rows' => 20,
				'media_buttons' => false,
			) );
			?>

			<div class="ns-mt-20">
				<button type="submit" class="ns-button ns-button-primary"><?php esc_html_e( 'Save Minutes', 'nonprofitsuite' ); ?></button>
				<?php if ( $minutes ) : ?>
					<button type="button" class="ns-button ns-button-outline" onclick="nsExportMinutesPDF(<?php echo esc_js( $meeting_id ); ?>);"><?php esc_html_e( 'Export PDF', 'nonprofitsuite' ); ?></button>
					<?php if ( $minutes->status !== 'approved' && current_user_can( 'ns_approve_minutes' ) ) : ?>
						<button type="button" class="ns-button ns-button-success" onclick="nsApproveMinutes(<?php echo esc_js( $minutes->id ); ?>);"><?php esc_html_e( 'Approve Minutes', 'nonprofitsuite' ); ?></button>
					<?php endif; ?>
				<?php endif; ?>
				<button type="button" class="ns-button ns-button-outline" onclick="nsShowActionItemModal(<?php echo esc_js( $meeting_id ); ?>);"><?php esc_html_e( 'Create Action Item', 'nonprofitsuite' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-meetings' ) ); ?>" class="ns-button ns-button-outline"><?php esc_html_e( 'Back to Meetings', 'nonprofitsuite' ); ?></a>
			</div>
		</form>
	</div>

	<!-- Action Item Modal -->
	<div id="ns-action-item-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
		<div style="background: white; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%;">
			<h2><?php esc_html_e( 'Create Action Item as Task', 'nonprofitsuite' ); ?></h2>
			<form id="ns-action-item-form">
				<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'nonprofitsuite_nonce' ) ); ?>">
				<input type="hidden" name="meeting_id" value="<?php echo esc_attr( $meeting_id ); ?>">
				<div style="margin-bottom: 15px;">
					<label for="action-item-title" style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e( 'Task Title', 'nonprofitsuite' ); ?> *</label>
					<input type="text" id="action-item-title" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
				</div>
				<div style="margin-bottom: 15px;">
					<label for="action-item-description" style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e( 'Description', 'nonprofitsuite' ); ?></label>
					<textarea id="action-item-description" rows="4" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
				</div>
				<div style="margin-bottom: 15px;">
					<label for="action-item-assigned-to" style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e( 'Assign To', 'nonprofitsuite' ); ?></label>
					<select id="action-item-assigned-to" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
						<option value=""><?php esc_html_e( 'Unassigned', 'nonprofitsuite' ); ?></option>
						<?php
						$users = get_users( array( 'orderby' => 'display_name' ) );
						foreach ( $users as $user ) {
							echo '<option value="' . esc_attr( $user->ID ) . '">' . esc_html( $user->display_name ) . '</option>';
						}
						?>
					</select>
				</div>
				<div style="margin-bottom: 15px;">
					<label for="action-item-due-date" style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e( 'Due Date', 'nonprofitsuite' ); ?></label>
					<input type="date" id="action-item-due-date" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
				</div>
				<div style="margin-bottom: 15px;">
					<label for="action-item-priority" style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e( 'Priority', 'nonprofitsuite' ); ?></label>
					<select id="action-item-priority" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
						<option value="low"><?php esc_html_e( 'Low', 'nonprofitsuite' ); ?></option>
						<option value="medium" selected><?php esc_html_e( 'Medium', 'nonprofitsuite' ); ?></option>
						<option value="high"><?php esc_html_e( 'High', 'nonprofitsuite' ); ?></option>
					</select>
				</div>
				<div style="display: flex; gap: 10px; justify-content: flex-end;">
					<button type="button" onclick="nsHideActionItemModal()" class="ns-button ns-button-outline"><?php esc_html_e( 'Cancel', 'nonprofitsuite' ); ?></button>
					<button type="submit" class="ns-button ns-button-primary"><?php esc_html_e( 'Create Task', 'nonprofitsuite' ); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>
