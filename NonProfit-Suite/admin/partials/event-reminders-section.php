<?php
/**
 * Event Reminders Section
 *
 * UI component for managing event reminders.
 * Include this file in event creation/edit forms.
 *
 * @package    NonprofitSuite
 * @subpackage Admin
 * @since      1.5.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Expected variables:
// $event_id - Event ID (null for new events)
// $event_attendees - Array of attendee user IDs or emails

$existing_reminders = array();
if ( $event_id ) {
	$existing_reminders = NonprofitSuite_Calendar_Reminders::get_event_reminders( $event_id );
}

?>

<div class="ns-event-reminders-section">
	<h3><?php echo esc_html__( 'Event Reminders', 'nonprofitsuite' ); ?></h3>
	<p class="description">
		<?php echo esc_html__( 'Set up automatic reminders for event attendees. Reminders will be sent via email at the specified times before the event.', 'nonprofitsuite' ); ?>
	</p>

	<div class="ns-reminders-list">
		<?php if ( ! empty( $existing_reminders ) ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Send To', 'nonprofitsuite' ); ?></th>
						<th><?php echo esc_html__( 'When', 'nonprofitsuite' ); ?></th>
						<th><?php echo esc_html__( 'Type', 'nonprofitsuite' ); ?></th>
						<th><?php echo esc_html__( 'Status', 'nonprofitsuite' ); ?></th>
						<th><?php echo esc_html__( 'Actions', 'nonprofitsuite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $existing_reminders as $reminder ) : ?>
						<?php
						$recipient_display = '';
						if ( $reminder['recipient_user_id'] ) {
							$user = get_userdata( $reminder['recipient_user_id'] );
							$recipient_display = $user ? $user->display_name : $reminder['recipient_email'];
						} else {
							$recipient_display = $reminder['recipient_email'];
						}

						$time_display = self::format_reminder_offset( $reminder['reminder_offset'] );

						$status_class = $reminder['reminder_status'] === 'sent' ? 'success' : 'pending';
						?>
						<tr>
							<td><?php echo esc_html( $recipient_display ); ?></td>
							<td><?php echo esc_html( $time_display ); ?></td>
							<td><?php echo esc_html( ucfirst( $reminder['reminder_type'] ) ); ?></td>
							<td>
								<span class="ns-status-badge <?php echo esc_attr( $status_class ); ?>">
									<?php echo esc_html( ucfirst( $reminder['reminder_status'] ) ); ?>
								</span>
							</td>
							<td>
								<?php if ( $reminder['reminder_status'] === 'pending' ) : ?>
									<button type="button" class="button button-small ns-delete-reminder" data-reminder-id="<?php echo esc_attr( $reminder['id'] ); ?>">
										<?php echo esc_html__( 'Delete', 'nonprofitsuite' ); ?>
									</button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p class="description"><?php echo esc_html__( 'No reminders configured yet.', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>

	<hr>

	<h4><?php echo esc_html__( 'Add Reminders', 'nonprofitsuite' ); ?></h4>

	<div class="ns-add-reminders-section">
		<table class="form-table">
			<tr>
				<th scope="row">
					<label><?php echo esc_html__( 'Quick Setup', 'nonprofitsuite' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" id="ns_create_default_reminders" name="create_default_reminders" value="1" checked>
						<?php echo esc_html__( 'Create default reminders for all attendees', 'nonprofitsuite' ); ?>
					</label>
					<p class="description">
						<?php echo esc_html__( 'Automatically creates reminders 1 week, 1 day, and 1 hour before the event for all attendees.', 'nonprofitsuite' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label><?php echo esc_html__( 'Default Reminder Times', 'nonprofitsuite' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" name="default_reminder_offsets[]" value="10080" checked>
						<?php echo esc_html__( '1 week before', 'nonprofitsuite' ); ?>
					</label><br>
					<label>
						<input type="checkbox" name="default_reminder_offsets[]" value="1440" checked>
						<?php echo esc_html__( '1 day before', 'nonprofitsuite' ); ?>
					</label><br>
					<label>
						<input type="checkbox" name="default_reminder_offsets[]" value="60" checked>
						<?php echo esc_html__( '1 hour before', 'nonprofitsuite' ); ?>
					</label><br>
					<label>
						<input type="checkbox" name="default_reminder_offsets[]" value="15">
						<?php echo esc_html__( '15 minutes before', 'nonprofitsuite' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<hr>

		<h4><?php echo esc_html__( 'Custom Reminders', 'nonprofitsuite' ); ?></h4>
		<p class="description">
			<?php echo esc_html__( 'Add custom reminders for specific recipients or timing.', 'nonprofitsuite' ); ?>
		</p>

		<div id="ns-custom-reminders-container">
			<!-- Custom reminders will be added here dynamically -->
		</div>

		<button type="button" class="button" id="ns-add-custom-reminder">
			<span class="dashicons dashicons-plus"></span>
			<?php echo esc_html__( 'Add Custom Reminder', 'nonprofitsuite' ); ?>
		</button>
	</div>
</div>

<style>
.ns-event-reminders-section {
	background: #fff;
	padding: 20px;
	margin: 20px 0;
	border: 1px solid #ccd0d4;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.ns-status-badge {
	display: inline-block;
	padding: 3px 8px;
	border-radius: 3px;
	font-size: 11px;
	font-weight: 600;
}

.ns-status-badge.success {
	background: #d4edda;
	color: #155724;
}

.ns-status-badge.pending {
	background: #fff3cd;
	color: #856404;
}

.ns-custom-reminder-row {
	margin: 15px 0;
	padding: 15px;
	background: #f9f9f9;
	border: 1px solid #ddd;
	border-radius: 4px;
}

.ns-custom-reminder-row .form-row {
	display: flex;
	gap: 15px;
	margin-bottom: 10px;
	align-items: center;
}

.ns-custom-reminder-row .form-field {
	flex: 1;
}

.ns-custom-reminder-row label {
	display: block;
	margin-bottom: 5px;
	font-weight: 600;
}

.ns-custom-reminder-row input[type="number"],
.ns-custom-reminder-row input[type="email"],
.ns-custom-reminder-row select {
	width: 100%;
}
</style>

<script type="text/template" id="ns-custom-reminder-template">
	<div class="ns-custom-reminder-row">
		<div class="form-row">
			<div class="form-field">
				<label><?php echo esc_html__( 'Recipient Email', 'nonprofitsuite' ); ?></label>
				<input type="email" name="custom_reminders[{{index}}][recipient_email]" class="regular-text" required>
			</div>
			<div class="form-field">
				<label><?php echo esc_html__( 'Time Before Event', 'nonprofitsuite' ); ?></label>
				<div style="display: flex; gap: 5px;">
					<input type="number" name="custom_reminders[{{index}}][offset_value]" min="1" value="1" style="width: 80px;" required>
					<select name="custom_reminders[{{index}}][offset_unit]" style="width: 120px;">
						<option value="60"><?php echo esc_html__( 'Hours', 'nonprofitsuite' ); ?></option>
						<option value="1440"><?php echo esc_html__( 'Days', 'nonprofitsuite' ); ?></option>
						<option value="10080"><?php echo esc_html__( 'Weeks', 'nonprofitsuite' ); ?></option>
					</select>
				</div>
			</div>
			<div class="form-field">
				<label><?php echo esc_html__( 'Type', 'nonprofitsuite' ); ?></label>
				<select name="custom_reminders[{{index}}][reminder_type]">
					<option value="email"><?php echo esc_html__( 'Email', 'nonprofitsuite' ); ?></option>
					<option value="sms"><?php echo esc_html__( 'SMS', 'nonprofitsuite' ); ?></option>
				</select>
			</div>
			<div class="form-field" style="flex: 0;">
				<label>&nbsp;</label>
				<button type="button" class="button ns-remove-custom-reminder">
					<span class="dashicons dashicons-trash"></span>
				</button>
			</div>
		</div>
	</div>
</script>

<script>
jQuery(document).ready(function($) {
	let customReminderIndex = 0;

	// Add custom reminder
	$('#ns-add-custom-reminder').on('click', function() {
		const template = $('#ns-custom-reminder-template').html();
		const html = template.replace(/{{index}}/g, customReminderIndex);
		$('#ns-custom-reminders-container').append(html);
		customReminderIndex++;
	});

	// Remove custom reminder
	$(document).on('click', '.ns-remove-custom-reminder', function() {
		$(this).closest('.ns-custom-reminder-row').remove();
	});

	// Delete existing reminder
	$(document).on('click', '.ns-delete-reminder', function() {
		if (confirm('<?php echo esc_js( __( 'Are you sure you want to delete this reminder?', 'nonprofitsuite' ) ); ?>')) {
			const reminderId = $(this).data('reminder-id');
			const $row = $(this).closest('tr');

			$.post(ajaxurl, {
				action: 'ns_delete_reminder',
				reminder_id: reminderId,
				nonce: '<?php echo wp_create_nonce( 'ns_delete_reminder' ); ?>'
			}, function(response) {
				if (response.success) {
					$row.fadeOut(function() {
						$(this).remove();
					});
				} else {
					alert(response.data.message || '<?php echo esc_js( __( 'Failed to delete reminder', 'nonprofitsuite' ) ); ?>');
				}
			});
		}
	});
});
</script>

<?php

/**
 * Helper function to format reminder offset.
 *
 * @param int $offset_minutes Offset in minutes.
 * @return string Formatted string.
 */
function format_reminder_offset( $offset_minutes ) {
	if ( $offset_minutes < 60 ) {
		return sprintf( _n( '%d minute before', '%d minutes before', $offset_minutes, 'nonprofitsuite' ), $offset_minutes );
	}

	$hours = floor( $offset_minutes / 60 );
	if ( $hours < 24 ) {
		return sprintf( _n( '%d hour before', '%d hours before', $hours, 'nonprofitsuite' ), $hours );
	}

	$days = floor( $hours / 24 );
	if ( $days < 7 ) {
		return sprintf( _n( '%d day before', '%d days before', $days, 'nonprofitsuite' ), $days );
	}

	$weeks = floor( $days / 7 );
	return sprintf( _n( '%d week before', '%d weeks before', $weeks, 'nonprofitsuite' ), $weeks );
}
