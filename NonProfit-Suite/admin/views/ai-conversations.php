<?php
/**
 * AI Conversations View
 *
 * Displays list of AI conversations and chat interface.
 *
 * @package NonprofitSuite
 * @subpackage Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// Get organization ID (simplified - in production, get from user's context)
$organization_id = 1;

// Get conversations
$conversations_table = $wpdb->prefix . 'ns_ai_conversations';
$conversations       = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$conversations_table} WHERE organization_id = %d ORDER BY updated_at DESC",
		$organization_id
	),
	ARRAY_A
);

// Get active AI providers
$settings_table = $wpdb->prefix . 'ns_ai_settings';
$active_providers = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT provider, model_name FROM {$settings_table} WHERE organization_id = %d AND is_active = 1",
		$organization_id
	),
	ARRAY_A
);

?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'AI Conversations', 'nonprofitsuite' ); ?></h1>
	<button type="button" class="page-title-action" id="new-conversation-btn">
		<?php esc_html_e( 'New Conversation', 'nonprofitsuite' ); ?>
	</button>
	<hr class="wp-header-end">

	<?php if ( empty( $active_providers ) ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php esc_html_e( 'No AI providers are configured. Please configure at least one provider in', 'nonprofitsuite' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ns-ai-settings' ) ); ?>">
					<?php esc_html_e( 'AI Settings', 'nonprofitsuite' ); ?>
				</a>.
			</p>
		</div>
	<?php endif; ?>

	<div class="ns-ai-conversations-container">
		<!-- Conversations List -->
		<div class="ns-conversations-list">
			<h2><?php esc_html_e( 'Recent Conversations', 'nonprofitsuite' ); ?></h2>

			<?php if ( empty( $conversations ) ) : ?>
				<p class="no-items"><?php esc_html_e( 'No conversations yet. Start a new conversation!', 'nonprofitsuite' ); ?></p>
			<?php else : ?>
				<div class="conversations-list">
					<?php foreach ( $conversations as $conversation ) : ?>
						<div class="conversation-item" data-id="<?php echo esc_attr( $conversation['id'] ); ?>">
							<div class="conversation-header">
								<h3><?php echo esc_html( $conversation['conversation_title'] ?: __( 'Untitled Conversation', 'nonprofitsuite' ) ); ?></h3>
								<button class="delete-conversation" data-id="<?php echo esc_attr( $conversation['id'] ); ?>">
									<span class="dashicons dashicons-trash"></span>
								</button>
							</div>
							<div class="conversation-meta">
								<span class="provider-badge <?php echo esc_attr( $conversation['provider'] ); ?>">
									<?php echo esc_html( ucfirst( $conversation['provider'] ) ); ?>
								</span>
								<span class="model-name"><?php echo esc_html( $conversation['model'] ); ?></span>
							</div>
							<div class="conversation-stats">
								<span><strong><?php echo esc_html( $conversation['total_messages'] ); ?></strong> messages</span>
								<span><strong><?php echo esc_html( number_format( $conversation['total_tokens'] ) ); ?></strong> tokens</span>
								<span><strong>$<?php echo esc_html( number_format( $conversation['total_cost'], 4 ) ); ?></strong> cost</span>
							</div>
							<div class="conversation-date">
								<?php echo esc_html( human_time_diff( strtotime( $conversation['updated_at'] ), current_time( 'timestamp' ) ) ); ?> ago
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<!-- Chat Interface -->
		<div class="ns-chat-interface" id="chat-interface" style="display: none;">
			<div class="chat-header">
				<h2 id="chat-title"><?php esc_html_e( 'Conversation', 'nonprofitsuite' ); ?></h2>
				<button class="close-chat" id="close-chat-btn">
					<span class="dashicons dashicons-no"></span>
				</button>
			</div>

			<div class="chat-messages" id="chat-messages">
				<!-- Messages will be loaded here -->
			</div>

			<div class="chat-input">
				<textarea id="message-input" placeholder="<?php esc_attr_e( 'Type your message...', 'nonprofitsuite' ); ?>" rows="3"></textarea>
				<button class="button button-primary" id="send-message-btn">
					<?php esc_html_e( 'Send', 'nonprofitsuite' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>

<!-- New Conversation Modal -->
<div id="new-conversation-modal" class="ns-modal" style="display: none;">
	<div class="ns-modal-content">
		<span class="ns-modal-close">&times;</span>
		<h2><?php esc_html_e( 'New Conversation', 'nonprofitsuite' ); ?></h2>

		<form id="new-conversation-form">
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="conversation-title"><?php esc_html_e( 'Title', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<input type="text" id="conversation-title" class="regular-text" required>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="conversation-provider"><?php esc_html_e( 'AI Provider', 'nonprofitsuite' ); ?></label>
					</th>
					<td>
						<select id="conversation-provider" required>
							<option value=""><?php esc_html_e( 'Select Provider', 'nonprofitsuite' ); ?></option>
							<?php foreach ( $active_providers as $provider ) : ?>
								<option value="<?php echo esc_attr( $provider['provider'] ); ?>" data-model="<?php echo esc_attr( $provider['model_name'] ); ?>">
									<?php echo esc_html( ucfirst( $provider['provider'] ) ); ?> (<?php echo esc_html( $provider['model_name'] ); ?>)
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Create Conversation', 'nonprofitsuite' ); ?>
				</button>
			</p>
		</form>
	</div>
</div>

<style>
.ns-ai-conversations-container {
	display: grid;
	grid-template-columns: 350px 1fr;
	gap: 20px;
	margin-top: 20px;
}

.ns-conversations-list {
	background: #fff;
	padding: 20px;
	border: 1px solid #ccd0d4;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.conversations-list {
	margin-top: 15px;
}

.conversation-item {
	padding: 15px;
	border: 1px solid #ddd;
	border-radius: 4px;
	margin-bottom: 10px;
	cursor: pointer;
	transition: all 0.2s;
}

.conversation-item:hover {
	background: #f9f9f9;
	border-color: #0073aa;
}

.conversation-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 8px;
}

.conversation-header h3 {
	margin: 0;
	font-size: 14px;
	font-weight: 600;
}

.delete-conversation {
	background: none;
	border: none;
	cursor: pointer;
	color: #b32d2e;
	padding: 0;
}

.delete-conversation:hover {
	color: #dc3232;
}

.conversation-meta {
	margin-bottom: 8px;
}

.provider-badge {
	display: inline-block;
	padding: 2px 8px;
	border-radius: 3px;
	font-size: 11px;
	font-weight: 600;
	margin-right: 8px;
	text-transform: uppercase;
}

.provider-badge.openai {
	background: #10a37f;
	color: #fff;
}

.provider-badge.anthropic {
	background: #e85d04;
	color: #fff;
}

.provider-badge.google {
	background: #4285f4;
	color: #fff;
}

.model-name {
	font-size: 11px;
	color: #666;
}

.conversation-stats {
	display: flex;
	gap: 15px;
	font-size: 12px;
	color: #666;
	margin-bottom: 5px;
}

.conversation-date {
	font-size: 11px;
	color: #999;
}

.ns-chat-interface {
	background: #fff;
	border: 1px solid #ccd0d4;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
	display: flex;
	flex-direction: column;
	height: 600px;
}

.chat-header {
	padding: 15px 20px;
	border-bottom: 1px solid #ddd;
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.chat-header h2 {
	margin: 0;
	font-size: 16px;
}

.close-chat {
	background: none;
	border: none;
	cursor: pointer;
	padding: 0;
}

.chat-messages {
	flex: 1;
	overflow-y: auto;
	padding: 20px;
}

.message {
	margin-bottom: 15px;
	padding: 10px 15px;
	border-radius: 8px;
	max-width: 80%;
}

.message.user {
	background: #0073aa;
	color: #fff;
	margin-left: auto;
	text-align: right;
}

.message.assistant {
	background: #f1f1f1;
	color: #333;
}

.message-role {
	font-size: 11px;
	font-weight: 600;
	margin-bottom: 5px;
	text-transform: uppercase;
}

.message-content {
	font-size: 14px;
	line-height: 1.5;
}

.chat-input {
	padding: 15px 20px;
	border-top: 1px solid #ddd;
	display: flex;
	gap: 10px;
}

.chat-input textarea {
	flex: 1;
	resize: none;
}

.ns-modal {
	display: none;
	position: fixed;
	z-index: 100000;
	left: 0;
	top: 0;
	width: 100%;
	height: 100%;
	overflow: auto;
	background-color: rgba(0,0,0,0.4);
}

.ns-modal-content {
	background-color: #fefefe;
	margin: 5% auto;
	padding: 20px;
	border: 1px solid #888;
	width: 80%;
	max-width: 600px;
	box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.ns-modal-close {
	color: #aaa;
	float: right;
	font-size: 28px;
	font-weight: bold;
	cursor: pointer;
}

.ns-modal-close:hover,
.ns-modal-close:focus {
	color: #000;
}
</style>

<script>
jQuery(document).ready(function($) {
	let currentConversationId = null;

	// New conversation modal
	$('#new-conversation-btn').on('click', function() {
		$('#new-conversation-modal').show();
	});

	$('.ns-modal-close').on('click', function() {
		$('#new-conversation-modal').hide();
	});

	// Create conversation
	$('#new-conversation-form').on('submit', function(e) {
		e.preventDefault();

		const title = $('#conversation-title').val();
		const provider = $('#conversation-provider').val();
		const model = $('#conversation-provider option:selected').data('model');

		$.ajax({
			url: nsAI.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_ai_create_conversation',
				nonce: nsAI.nonce,
				organization_id: <?php echo absint( $organization_id ); ?>,
				provider: provider,
				model: model,
				title: title
			},
			success: function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.data.message);
				}
			}
		});
	});

	// Open conversation
	$('.conversation-item').on('click', function() {
		const conversationId = $(this).data('id');
		openConversation(conversationId);
	});

	// Close chat
	$('#close-chat-btn').on('click', function() {
		$('#chat-interface').hide();
		currentConversationId = null;
	});

	// Send message
	$('#send-message-btn').on('click', function() {
		sendMessage();
	});

	$('#message-input').on('keydown', function(e) {
		if (e.key === 'Enter' && !e.shiftKey) {
			e.preventDefault();
			sendMessage();
		}
	});

	// Delete conversation
	$('.delete-conversation').on('click', function(e) {
		e.stopPropagation();

		if (!confirm('Are you sure you want to delete this conversation?')) {
			return;
		}

		const conversationId = $(this).data('id');

		$.ajax({
			url: nsAI.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_ai_delete_conversation',
				nonce: nsAI.nonce,
				conversation_id: conversationId
			},
			success: function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.data.message);
				}
			}
		});
	});

	function openConversation(conversationId) {
		currentConversationId = conversationId;
		$('#chat-interface').show();
		loadMessages(conversationId);
	}

	function loadMessages(conversationId) {
		// Load messages from database via AJAX
		// For now, clear messages
		$('#chat-messages').html('');
	}

	function sendMessage() {
		if (!currentConversationId) {
			return;
		}

		const message = $('#message-input').val().trim();
		if (!message) {
			return;
		}

		// Add user message to UI
		appendMessage('user', message);
		$('#message-input').val('');

		// Send to AI
		$.ajax({
			url: nsAI.ajax_url,
			type: 'POST',
			data: {
				action: 'ns_ai_send_message',
				nonce: nsAI.nonce,
				conversation_id: currentConversationId,
				message: message
			},
			success: function(response) {
				if (response.success) {
					appendMessage('assistant', response.data.response);
				} else {
					alert(response.data.message);
				}
			}
		});
	}

	function appendMessage(role, content) {
		const messageHtml = `
			<div class="message ${role}">
				<div class="message-role">${role}</div>
				<div class="message-content">${content}</div>
			</div>
		`;
		$('#chat-messages').append(messageHtml);
		$('#chat-messages').scrollTop($('#chat-messages')[0].scrollHeight);
	}
});
</script>
