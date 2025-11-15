/**
 * NonprofitSuite Admin JavaScript
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		// Auto-save minutes every 60 seconds
		if ($('#minutes-editor').length) {
			setInterval(autoSaveMinutes, 60000);
		}

		// Make agenda items sortable
		if ($('.ns-agenda-items').length) {
			$('.ns-agenda-items').sortable({
				handle: '.ns-drag-handle',
				placeholder: 'ns-sortable-placeholder',
				update: function(event, ui) {
					saveAgendaOrder();
				}
			});
		}

		// Task status quick update
		$('.ns-task-status-select').on('change', function() {
			var taskId = $(this).data('task-id');
			var status = $(this).val();
			updateTaskStatus(taskId, status);
		});
	});

	/**
	 * Auto-save minutes
	 */
	function autoSaveMinutes() {
		var meetingId = $('#meeting_id').val();
		var minutesId = $('#minutes_id').val();
		var content = $('#minutes-editor').val();

		$.ajax({
			url: nonprofitsuiteAjax.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_auto_save_minutes',
				nonce: nonprofitsuiteAjax.auto_save_minutes_nonce,
				meeting_id: meetingId,
				id: minutesId,
				content: content
			},
			success: function(response) {
				if (response.success) {
					$('#last-saved').text('Last saved: ' + response.data.timestamp);
				}
			}
		});
	}

	/**
	 * Save agenda item order
	 */
	function saveAgendaOrder() {
		var order = [];
		$('.ns-agenda-items .ns-agenda-item').each(function(index) {
			order.push($(this).data('item-id'));
		});

		$.ajax({
			url: nonprofitsuiteAjax.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_reorder_agenda_items',
				nonce: nonprofitsuiteAjax.reorder_agenda_items_nonce,
				order: JSON.stringify(order)
			}
		});
	}

	/**
	 * Update task status
	 */
	function updateTaskStatus(taskId, status) {
		$.ajax({
			url: nonprofitsuiteAjax.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_update_task_status',
				nonce: nonprofitsuiteAjax.update_task_status_nonce,
				task_id: taskId,
				status: status
			},
			success: function(response) {
				if (response.success) {
					location.reload();
				}
			}
		});
	}

	/**
	 * Add task comment
	 */
	window.nsAddTaskComment = function(taskId) {
		var comment = $('#task-comment-' + taskId).val();

		if (!comment) {
			alert('Please enter a comment.');
			return;
		}

		$.ajax({
			url: nonprofitsuiteAjax.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_add_task_comment',
				nonce: nonprofitsuiteAjax.add_task_comment_nonce,
				task_id: taskId,
				comment: comment
			},
			success: function(response) {
				if (response.success) {
					location.reload();
				}
			}
		});
	};

	/**
	 * Export agenda to PDF
	 */
	window.nsExportAgendaPDF = function(meetingId) {
		$.ajax({
			url: nonprofitsuiteAjax.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_export_agenda_pdf',
				nonce: nonprofitsuiteAjax.export_agenda_pdf_nonce,
				meeting_id: meetingId
			},
			success: function(response) {
				if (response.success) {
					window.open(response.data.url, '_blank');
				} else {
					alert(response.data.message || 'Failed to export agenda.');
				}
			},
			error: function() {
				alert('An error occurred while exporting the agenda.');
			}
		});
	};

	/**
	 * Export minutes to PDF
	 */
	window.nsExportMinutesPDF = function(meetingId) {
		$.ajax({
			url: nonprofitsuiteAjax.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_export_minutes_pdf',
				nonce: nonprofitsuiteAjax.export_minutes_pdf_nonce,
				meeting_id: meetingId
			},
			success: function(response) {
				if (response.success) {
					window.open(response.data.url, '_blank');
				} else {
					alert(response.data.message || 'Failed to export minutes.');
				}
			},
			error: function() {
				alert('An error occurred while exporting the minutes.');
			}
		});
	};

	/**
	 * Approve minutes
	 */
	window.nsApproveMinutes = function(minutesId) {
		if (!confirm('Are you sure you want to approve these minutes? This action cannot be undone.')) {
			return;
		}

		$.ajax({
			url: nonprofitsuiteAjax.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_approve_minutes',
				nonce: nonprofitsuiteAjax.approve_minutes_nonce,
				minutes_id: minutesId
			},
			success: function(response) {
				if (response.success) {
					alert(response.data.message || 'Minutes approved successfully.');
					location.reload();
				} else {
					alert(response.data.message || 'Failed to approve minutes.');
				}
			},
			error: function() {
				alert('An error occurred while approving the minutes.');
			}
		});
	};

	/**
	 * Show action item modal
	 */
	var currentMeetingId = 0;
	window.nsShowActionItemModal = function(meetingId) {
		currentMeetingId = meetingId;
		$('#ns-action-item-modal').css('display', 'flex');
		$('#action-item-title').val('');
		$('#action-item-description').val('');
		$('#action-item-assigned-to').val('');
		$('#action-item-due-date').val('');
		$('#action-item-priority').val('medium');
	};

	/**
	 * Hide action item modal
	 */
	window.nsHideActionItemModal = function() {
		$('#ns-action-item-modal').hide();
	};

	/**
	 * Handle action item form submission
	 */
	$(document).on('submit', '#ns-action-item-form', function(e) {
		e.preventDefault();

		var title = $('#action-item-title').val();
		var description = $('#action-item-description').val();
		var assignedTo = $('#action-item-assigned-to').val();
		var dueDate = $('#action-item-due-date').val();
		var priority = $('#action-item-priority').val();

		$.ajax({
			url: nonprofitsuiteAjax.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_create_task_from_action_item',
				nonce: nonprofitsuiteAjax.create_task_from_action_item_nonce,
				meeting_id: currentMeetingId,
				title: title,
				description: description,
				assigned_to: assignedTo,
				due_date: dueDate,
				priority: priority
			},
			success: function(response) {
				if (response.success) {
					alert(response.data.message || 'Action item created as task successfully.');
					nsHideActionItemModal();
				} else {
					alert(response.data.message || 'Failed to create action item.');
				}
			},
			error: function() {
				alert('An error occurred while creating the action item.');
			}
		});
	});

	// Close modal on background click
	$(document).on('click', '#ns-action-item-modal', function(e) {
		if (e.target.id === 'ns-action-item-modal') {
			nsHideActionItemModal();
		}
	});

})(jQuery);
