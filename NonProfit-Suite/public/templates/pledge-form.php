<?php
/**
 * Pledge Form Template
 *
 * @package NonprofitSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="ns-donation-form-container">
	<div class="ns-donation-form ns-pledge-form">
		<?php if ( ! empty( $atts['title'] ) ) : ?>
			<h2 class="ns-form-title"><?php echo esc_html( $atts['title'] ); ?></h2>
		<?php endif; ?>

		<?php if ( ! empty( $atts['description'] ) ) : ?>
			<p class="ns-form-description"><?php echo esc_html( $atts['description'] ); ?></p>
		<?php endif; ?>

		<form id="ns-pledge-form" method="post">
			<input type="hidden" name="fund_id" value="<?php echo esc_attr( $atts['fund_id'] ); ?>">
			<input type="hidden" name="campaign_id" value="<?php echo esc_attr( $atts['campaign_id'] ); ?>">

			<div class="ns-form-section">
				<label for="pledge_amount" class="ns-form-label"><?php esc_html_e( 'Total Pledge Amount', 'nonprofitsuite' ); ?></label>
				<div class="ns-input-prefix">
					<span class="ns-prefix">$</span>
					<input type="number" id="pledge_amount" name="amount" min="<?php echo esc_attr( $atts['min_amount'] ); ?>" step="0.01" class="ns-input" required>
				</div>
				<p class="ns-help-text"><?php printf( esc_html__( 'Minimum: $%s', 'nonprofitsuite' ), esc_html( $atts['min_amount'] ) ); ?></p>
			</div>

			<div class="ns-form-section">
				<label for="installments" class="ns-form-label"><?php esc_html_e( 'Number of Installments', 'nonprofitsuite' ); ?></label>
				<select id="installments" name="installments" class="ns-input" required>
					<option value="1">1 (One-time)</option>
					<option value="3">3 (Quarterly)</option>
					<option value="12" selected>12 (Monthly)</option>
					<option value="24">24 (Biweekly)</option>
				</select>
			</div>

			<div class="ns-form-section">
				<div class="ns-form-row">
					<div class="ns-form-field">
						<label for="donor_name" class="ns-form-label"><?php esc_html_e( 'Full Name', 'nonprofitsuite' ); ?></label>
						<input type="text" id="donor_name" name="donor_name" class="ns-input" required>
					</div>

					<div class="ns-form-field">
						<label for="donor_email" class="ns-form-label"><?php esc_html_e( 'Email Address', 'nonprofitsuite' ); ?></label>
						<input type="email" id="donor_email" name="donor_email" class="ns-input" required>
					</div>
				</div>
			</div>

			<div class="ns-form-section">
				<button type="submit" class="ns-submit-btn" id="ns-submit-pledge">
					<?php esc_html_e( 'Submit Pledge', 'nonprofitsuite' ); ?>
				</button>
			</div>

			<div id="ns-form-messages" class="ns-form-messages" style="display: none;"></div>
		</form>
	</div>
</div>
