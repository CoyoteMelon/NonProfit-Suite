<?php
/**
 * Settings View
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'NonprofitSuite Settings', 'nonprofitsuite' ); ?></h1>

	<div class="ns-card">
		<h2><?php esc_html_e( 'Organization Information', 'nonprofitsuite' ); ?></h2>

		<form method="post" action="options.php">
			<?php
			settings_fields( 'nonprofitsuite_settings' );
			do_settings_sections( 'nonprofitsuite_settings' );
			?>

			<div class="ns-form-group">
				<label class="ns-form-label"><?php esc_html_e( 'Organization Name', 'nonprofitsuite' ); ?></label>
				<input type="text" name="nonprofitsuite_organization_name" class="ns-form-input" value="<?php echo esc_attr( get_option( 'nonprofitsuite_organization_name' ) ); ?>">
			</div>

			<div class="ns-form-group">
				<label class="ns-form-label"><?php esc_html_e( 'Organization Type', 'nonprofitsuite' ); ?></label>
				<input type="text" name="nonprofitsuite_organization_type" class="ns-form-input" value="<?php echo esc_attr( get_option( 'nonprofitsuite_organization_type' ) ); ?>">
			</div>

			<button type="submit" class="ns-button ns-button-primary"><?php esc_html_e( 'Save Settings', 'nonprofitsuite' ); ?></button>
		</form>
	</div>

	<div class="ns-card">
		<h2><?php esc_html_e( 'About NonprofitSuite', 'nonprofitsuite' ); ?></h2>
		<p><?php esc_html_e( 'Version', 'nonprofitsuite' ); ?>: <?php echo esc_html( NONPROFITSUITE_VERSION ); ?></p>
		<p><?php printf( esc_html__( 'For support, visit %s', 'nonprofitsuite' ), '<a href="https://silverhost.net/nonprofitsuite" target="_blank">silverhost.net/nonprofitsuite</a>' ); ?></p>
	</div>
</div>
