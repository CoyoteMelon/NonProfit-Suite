<?php
/**
 * Mobile API Admin Interface
 *
 * @package NonprofitSuite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Get API tokens
$tokens = isset( $tokens ) ? $tokens : NonprofitSuite_Mobile_API::get_user_tokens( get_current_user_id() );
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Mobile API', 'nonprofitsuite' ); ?></h1>

	<div class="ns-card">
		<h2><?php esc_html_e( 'API Tokens', 'nonprofitsuite' ); ?></h2>
		<p><?php esc_html_e( 'Manage API tokens for mobile app access and third-party integrations.', 'nonprofitsuite' ); ?></p>

		<?php if ( is_array( $tokens ) && ! empty( $tokens ) ) : ?>
			<div class="ns-table-container">
				<table class="ns-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Token Name', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Last Used', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Expires', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $tokens as $token ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $token->token_name ?: __( 'Unnamed Token', 'nonprofitsuite' ) ); ?></strong></td>
								<td><?php echo $token->last_used ? esc_html( NonprofitSuite_Utilities::format_datetime( $token->last_used ) ) : '-'; ?></td>
								<td><?php echo $token->expires_at ? esc_html( NonprofitSuite_Utilities::format_datetime( $token->expires_at ) ) : __( 'Never', 'nonprofitsuite' ); ?></td>
								<td><?php echo $token->is_active ? '<span class="ns-badge ns-badge-success">' . esc_html__( 'Active', 'nonprofitsuite' ) . '</span>' : '<span class="ns-badge ns-badge-secondary">' . esc_html__( 'Inactive', 'nonprofitsuite' ) . '</span>'; ?></td>
								<td>
									<button class="ns-button ns-button-sm ns-button-outline"><?php esc_html_e( 'Revoke', 'nonprofitsuite' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<div style="text-align: center; padding: 40px;">
				<p><?php esc_html_e( 'No API tokens generated yet.', 'nonprofitsuite' ); ?></p>
			</div>
		<?php endif; ?>

		<button class="ns-button ns-button-primary" style="margin-top: 20px;"><?php esc_html_e( 'Generate New Token', 'nonprofitsuite' ); ?></button>
	</div>

	<div class="ns-card" style="margin-top: 20px;">
		<h3><?php esc_html_e( 'API Documentation', 'nonprofitsuite' ); ?></h3>
		<p><?php esc_html_e( 'Use the NonprofitSuite API to integrate with mobile apps and third-party services.', 'nonprofitsuite' ); ?></p>
		<div style="background: #f9fafb; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px;">
			<strong><?php esc_html_e( 'API Endpoint:', 'nonprofitsuite' ); ?></strong><br>
			<?php echo esc_html( rest_url( 'nonprofitsuite/v1' ) ); ?>
		</div>
		<p style="margin-top: 15px;">
			<a href="https://silverhost.net/nonprofitsuite/api-docs" target="_blank" class="ns-button ns-button-outline">
				<?php esc_html_e( 'View Full API Documentation', 'nonprofitsuite' ); ?>
			</a>
		</p>
	</div>
</div>
