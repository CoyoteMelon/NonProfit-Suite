<?php
/**
 * Video Conferencing Meetings Dashboard
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/admin/views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="wrap">
	<h1>Video Conferencing Meetings</h1>

	<p>
		<button type="button" class="button button-primary" id="ns-create-meeting">Create Meeting</button>
	</p>

	<?php if ( ! empty( $meetings ) ) : ?>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th>Topic</th>
					<th>Provider</th>
					<th>Start Time</th>
					<th>Duration</th>
					<th>Status</th>
					<th>Join URL</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $meetings as $meeting ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $meeting['topic'] ); ?></strong></td>
						<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $meeting['provider'] ) ) ); ?></td>
						<td><?php echo esc_html( $meeting['start_time'] ); ?></td>
						<td><?php echo esc_html( $meeting['duration'] ); ?> min</td>
						<td>
							<span class="ns-status-badge <?php echo esc_attr( $meeting['status'] ); ?>">
								<?php echo esc_html( ucfirst( $meeting['status'] ) ); ?>
							</span>
						</td>
						<td>
							<?php if ( $meeting['join_url'] ) : ?>
								<a href="<?php echo esc_url( $meeting['join_url'] ); ?>" target="_blank" class="button button-small">Join</a>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $meeting['status'] === 'scheduled' ) : ?>
								<button type="button" class="button button-small ns-cancel-meeting" data-meeting-id="<?php echo esc_attr( $meeting['id'] ); ?>">Cancel</button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p>No meetings found. Create your first video meeting!</p>
	<?php endif; ?>
</div>

<style>
.ns-status-badge {
	display: inline-block;
	padding: 3px 8px;
	border-radius: 3px;
	font-size: 12px;
	font-weight: 500;
}

.ns-status-badge.scheduled {
	background: #fff3cd;
	color: #856404;
}

.ns-status-badge.started {
	background: #d4edda;
	color: #155724;
}

.ns-status-badge.ended {
	background: #f0f0f1;
	color: #646970;
}

.ns-status-badge.cancelled {
	background: #f8d7da;
	color: #721c24;
}
</style>

<script>
jQuery(document).ready(function($) {
	$('.ns-cancel-meeting').on('click', function() {
		var button = $(this);
		var meetingId = button.data('meeting-id');

		if (!confirm('Are you sure you want to cancel this meeting?')) {
			return;
		}

		button.prop('disabled', true).text('Cancelling...');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ns_delete_video_meeting',
				meeting_id: meetingId,
				nonce: '<?php echo wp_create_nonce( 'ns_video_admin' ); ?>'
			},
			success: function(response) {
				if (response.success) {
					alert(response.data.message);
					location.reload();
				} else {
					alert('Cancel failed: ' + response.data.message);
					button.prop('disabled', false).text('Cancel');
				}
			},
			error: function() {
				alert('Cancel failed due to a network error.');
				button.prop('disabled', false).text('Cancel');
			}
		});
	});
});
</script>
