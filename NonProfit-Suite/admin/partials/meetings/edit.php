<?php
/**
 * Meeting Edit View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$meeting_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
$meeting = $meeting_id ? NonprofitSuite_Meetings::get( $meeting_id ) : null;

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['ns_meeting_nonce'] ) && wp_verify_nonce( $_POST['ns_meeting_nonce'], 'ns_save_meeting' ) ) {
	$data = NonprofitSuite_Utilities::sanitize_meeting_data( $_POST );

	if ( $meeting_id ) {
		NonprofitSuite_Meetings::update( $meeting_id, $data );
	} else {
		$meeting_id = NonprofitSuite_Meetings::create( $data );
	}

	wp_redirect( admin_url( 'admin.php?page=nonprofitsuite-meetings' ) );
	exit;
}

$types = NonprofitSuite_Meetings::get_meeting_types();
$statuses = NonprofitSuite_Meetings::get_statuses();
?>

<div class="wrap ns-container">
	<h1><?php echo $meeting ? esc_html__( 'Edit Meeting', 'nonprofitsuite' ) : esc_html__( 'Add Meeting', 'nonprofitsuite' ); ?></h1>

	<div class="ns-card">
		<form method="post">
			<?php wp_nonce_field( 'ns_save_meeting', 'ns_meeting_nonce' ); ?>

			<div class="ns-form-group">
				<label class="ns-form-label"><?php esc_html_e( 'Meeting Title', 'nonprofitsuite' ); ?></label>
				<input type="text" name="title" class="ns-form-input" value="<?php echo $meeting ? esc_attr( $meeting->title ) : ''; ?>" required>
			</div>

			<div class="ns-form-group">
				<label class="ns-form-label"><?php esc_html_e( 'Meeting Type', 'nonprofitsuite' ); ?></label>
				<select name="meeting_type" class="ns-form-select">
					<?php foreach ( $types as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $meeting ? $meeting->meeting_type : 'board', $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="ns-form-group">
				<label class="ns-form-label"><?php esc_html_e( 'Date & Time', 'nonprofitsuite' ); ?></label>
				<input type="datetime-local" name="meeting_date" class="ns-form-input" value="<?php echo $meeting ? esc_attr( date( 'Y-m-d\TH:i', strtotime( $meeting->meeting_date ) ) ) : ''; ?>" required>
			</div>

			<div class="ns-form-group">
				<label class="ns-form-label"><?php esc_html_e( 'Location', 'nonprofitsuite' ); ?></label>
				<input type="text" name="location" class="ns-form-input" value="<?php echo $meeting ? esc_attr( $meeting->location ) : ''; ?>">
			</div>

			<div class="ns-form-group">
				<label class="ns-form-label"><?php esc_html_e( 'Virtual Meeting URL', 'nonprofitsuite' ); ?></label>
				<input type="url" name="virtual_url" class="ns-form-input" value="<?php echo $meeting ? esc_attr( $meeting->virtual_url ) : ''; ?>">
			</div>

			<div class="ns-form-group">
				<label class="ns-form-label"><?php esc_html_e( 'Description', 'nonprofitsuite' ); ?></label>
				<textarea name="description" class="ns-form-textarea" rows="4"><?php echo $meeting ? esc_textarea( $meeting->description ) : ''; ?></textarea>
			</div>

			<button type="submit" class="ns-button ns-button-primary"><?php esc_html_e( 'Save Meeting', 'nonprofitsuite' ); ?></button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-meetings' ) ); ?>" class="ns-button ns-button-outline"><?php esc_html_e( 'Cancel', 'nonprofitsuite' ); ?></a>
		</form>
	</div>
</div>
