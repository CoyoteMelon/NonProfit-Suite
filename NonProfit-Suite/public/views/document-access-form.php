<?php
/**
 * Document Access Form
 *
 * Form for accessing protected documents.
 *
 * @package NonprofitSuite
 * @subpackage Public/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e( 'Document Access', 'nonprofitsuite' ); ?> - <?php bloginfo( 'name' ); ?></title>
	<?php wp_head(); ?>
	<style>
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			background: #f0f0f1;
			margin: 0;
			padding: 0;
		}

		.access-container {
			max-width: 500px;
			margin: 100px auto;
			background: #fff;
			padding: 40px;
			border-radius: 8px;
			box-shadow: 0 2px 8px rgba(0,0,0,0.1);
		}

		.access-header {
			text-align: center;
			margin-bottom: 30px;
		}

		.access-header h1 {
			margin: 0 0 10px 0;
			font-size: 24px;
		}

		.access-header p {
			margin: 0;
			color: #646970;
		}

		.access-form {
			margin-top: 30px;
		}

		.form-group {
			margin-bottom: 20px;
		}

		.form-group label {
			display: block;
			margin-bottom: 5px;
			font-weight: 600;
		}

		.form-group input[type="text"],
		.form-group input[type="email"],
		.form-group input[type="password"] {
			width: 100%;
			padding: 10px;
			border: 1px solid #ddd;
			border-radius: 4px;
			font-size: 14px;
		}

		.form-group input[type="checkbox"] {
			margin-right: 5px;
		}

		.error-message {
			background: #fcf0f1;
			color: #d63638;
			padding: 15px;
			border-left: 4px solid #d63638;
			margin-bottom: 20px;
			border-radius: 4px;
		}

		.submit-button {
			width: 100%;
			padding: 12px;
			background: #2271b1;
			color: #fff;
			border: none;
			border-radius: 4px;
			font-size: 16px;
			font-weight: 600;
			cursor: pointer;
			transition: background 0.2s;
		}

		.submit-button:hover {
			background: #135e96;
		}

		.tos-text {
			font-size: 12px;
			color: #646970;
			margin-top: 5px;
		}

		.secure-badge {
			text-align: center;
			margin-top: 30px;
			padding-top: 20px;
			border-top: 1px solid #f0f0f1;
			font-size: 12px;
			color: #999;
		}

		.secure-badge::before {
			content: "\1F512";
			margin-right: 5px;
		}
	</style>
</head>
<body>
	<div class="access-container">
		<div class="access-header">
			<h1><?php esc_html_e( 'Document Access Required', 'nonprofitsuite' ); ?></h1>
			<p><?php esc_html_e( 'This document is protected. Please provide the required information to access it.', 'nonprofitsuite' ); ?></p>
		</div>

		<?php if ( ! empty( $error ) ) : ?>
			<div class="error-message">
				<?php echo esc_html( $error ); ?>
			</div>
		<?php endif; ?>

		<form method="POST" class="access-form">
			<?php if ( ! empty( $share['password_hash'] ) ) : ?>
				<div class="form-group">
					<label for="password"><?php esc_html_e( 'Password', 'nonprofitsuite' ); ?> *</label>
					<input type="password" id="password" name="password" required>
				</div>
			<?php endif; ?>

			<?php if ( $share['require_email'] ) : ?>
				<div class="form-group">
					<label for="email"><?php esc_html_e( 'Email Address', 'nonprofitsuite' ); ?> *</label>
					<input type="email" id="email" name="email" required>
					<p class="tos-text"><?php esc_html_e( 'Your email will be used for access logging purposes only.', 'nonprofitsuite' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $share['require_tos_acceptance'] ) : ?>
				<div class="form-group">
					<label>
						<input type="checkbox" name="tos_accepted" value="1" required>
						<?php esc_html_e( 'I accept the terms of service', 'nonprofitsuite' ); ?>
					</label>
					<p class="tos-text">
						<?php esc_html_e( 'By accessing this document, you agree to use it only for lawful purposes and not redistribute it without permission.', 'nonprofitsuite' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<button type="submit" class="submit-button">
				<?php esc_html_e( 'Access Document', 'nonprofitsuite' ); ?>
			</button>
		</form>

		<div class="secure-badge">
			<?php esc_html_e( 'Secure document access powered by NonprofitSuite', 'nonprofitsuite' ); ?>
		</div>
	</div>

	<?php wp_footer(); ?>
</body>
</html>
