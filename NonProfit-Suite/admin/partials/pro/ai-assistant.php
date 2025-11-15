<?php
/**
 * AI Assistant Admin Interface
 *
 * @package NonprofitSuite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Check if API is configured
$is_configured = isset( $is_configured ) ? $is_configured : NonprofitSuite_AI_Assistant::is_api_configured();
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'AI Assistant', 'nonprofitsuite' ); ?></h1>

	<?php if ( ! $is_configured ) : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'AI Assistant requires API configuration. Contact support to enable this feature.', 'nonprofitsuite' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="ns-card">
		<h2><?php esc_html_e( 'AI-Powered Nonprofit Assistant', 'nonprofitsuite' ); ?></h2>
		<p><?php esc_html_e( 'Get intelligent help with drafting documents, analyzing data, and managing your nonprofit operations.', 'nonprofitsuite' ); ?></p>

		<div style="border: 2px solid #e0e0e0; border-radius: 8px; padding: 20px; margin: 20px 0; background: #f9fafb;">
			<div id="ai-chat-messages" style="height: 400px; overflow-y: auto; margin-bottom: 20px; padding: 15px; background: white; border-radius: 4px;">
				<div style="padding: 20px; text-align: center; color: #666;">
					<p><strong><?php esc_html_e( 'Welcome to AI Assistant!', 'nonprofitsuite' ); ?></strong></p>
					<p><?php esc_html_e( 'Ask me anything about:', 'nonprofitsuite' ); ?></p>
					<ul style="list-style: none; padding: 0; margin: 20px 0;">
						<li>📝 <?php esc_html_e( 'Drafting meeting minutes and agendas', 'nonprofitsuite' ); ?></li>
						<li>📊 <?php esc_html_e( 'Analyzing financial reports', 'nonprofitsuite' ); ?></li>
						<li>✉️ <?php esc_html_e( 'Writing donor communications', 'nonprofitsuite' ); ?></li>
						<li>📋 <?php esc_html_e( 'Creating grant proposals', 'nonprofitsuite' ); ?></li>
						<li>💡 <?php esc_html_e( 'Nonprofit best practices', 'nonprofitsuite' ); ?></li>
					</ul>
				</div>
			</div>

			<div style="display: flex; gap: 10px;">
				<textarea
					id="ai-prompt-input"
					placeholder="<?php esc_attr_e( 'Type your question or request here...', 'nonprofitsuite' ); ?>"
					style="flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 4px; resize: vertical; min-height: 60px;"
					<?php echo ! $is_configured ? 'disabled' : ''; ?>
				></textarea>
				<button
					class="ns-button ns-button-primary"
					style="height: fit-content;"
					<?php echo ! $is_configured ? 'disabled' : ''; ?>
				>
					<?php esc_html_e( 'Send', 'nonprofitsuite' ); ?>
				</button>
			</div>

			<p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">
				<?php esc_html_e( 'Powered by Claude AI | Your conversations are private and secure', 'nonprofitsuite' ); ?>
			</p>
		</div>
	</div>

	<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
		<div class="ns-card">
			<h3><?php esc_html_e( 'Quick Actions', 'nonprofitsuite' ); ?></h3>
			<div style="display: flex; flex-direction: column; gap: 10px;">
				<button class="ns-button ns-button-outline" <?php echo ! $is_configured ? 'disabled' : ''; ?>>
					📝 <?php esc_html_e( 'Draft Meeting Minutes', 'nonprofitsuite' ); ?>
				</button>
				<button class="ns-button ns-button-outline" <?php echo ! $is_configured ? 'disabled' : ''; ?>>
					✉️ <?php esc_html_e( 'Write Donor Thank You', 'nonprofitsuite' ); ?>
				</button>
				<button class="ns-button ns-button-outline" <?php echo ! $is_configured ? 'disabled' : ''; ?>>
					📊 <?php esc_html_e( 'Analyze Financials', 'nonprofitsuite' ); ?>
				</button>
			</div>
		</div>

		<div class="ns-card">
			<h3><?php esc_html_e( 'Recent Conversations', 'nonprofitsuite' ); ?></h3>
			<p style="color: #666; font-size: 14px;"><?php esc_html_e( 'Your conversation history will appear here.', 'nonprofitsuite' ); ?></p>
			<button class="ns-button ns-button-outline" style="margin-top: 10px;" <?php echo ! $is_configured ? 'disabled' : ''; ?>>
				<?php esc_html_e( 'View History', 'nonprofitsuite' ); ?>
			</button>
		</div>

		<div class="ns-card">
			<h3><?php esc_html_e( 'Settings', 'nonprofitsuite' ); ?></h3>
			<p style="color: #666; font-size: 14px;"><?php esc_html_e( 'Configure AI Assistant preferences and API settings.', 'nonprofitsuite' ); ?></p>
			<button class="ns-button ns-button-outline" style="margin-top: 10px;">
				<?php esc_html_e( 'Configure', 'nonprofitsuite' ); ?>
			</button>
		</div>
	</div>
</div>
