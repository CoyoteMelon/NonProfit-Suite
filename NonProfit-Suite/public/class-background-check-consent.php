<?php
/**
 * Background Check Consent Public Interface
 *
 * Handles public-facing consent workflow for background checks.
 * FCRA-compliant disclosure and authorization process.
 *
 * @package NonprofitSuite
 * @subpackage Public
 * @since 1.18.0
 */

class NS_Background_Check_Consent {

	/**
	 * Background check manager
	 *
	 * @var NS_Background_Check_Manager
	 */
	private $manager;

	/**
	 * Constructor
	 */
	public function __construct() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/helpers/class-background-check-manager.php';
		$this->manager = new \NonprofitSuite\Helpers\NS_Background_Check_Manager();

		// Register shortcode
		add_shortcode( 'ns_background_check_consent', array( $this, 'render_consent_form' ) );

		// AJAX handler for consent submission
		add_action( 'wp_ajax_nopriv_ns_submit_background_check_consent', array( $this, 'ajax_submit_consent' ) );
		add_action( 'wp_ajax_ns_submit_background_check_consent', array( $this, 'ajax_submit_consent' ) );

		// Enqueue assets
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue assets
	 */
	public function enqueue_assets() {
		if ( ! is_singular() || ! has_shortcode( get_post()->post_content, 'ns_background_check_consent' ) ) {
			return;
		}

		wp_enqueue_style(
			'ns-background-check-consent',
			plugin_dir_url( __FILE__ ) . 'css/background-check-consent.css',
			array(),
			'1.18.0'
		);

		wp_enqueue_script(
			'ns-background-check-consent',
			plugin_dir_url( __FILE__ ) . 'js/background-check-consent.js',
			array( 'jquery' ),
			'1.18.0',
			true
		);

		wp_localize_script(
			'ns-background-check-consent',
			'nsBackgroundCheckConsent',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ns_background_check_consent' ),
			)
		);
	}

	/**
	 * Render consent form shortcode
	 *
	 * Usage: [ns_background_check_consent request_id="123"]
	 *
	 * @param array $atts Shortcode attributes
	 * @return string Rendered form HTML
	 */
	public function render_consent_form( $atts ) {
		$atts = shortcode_atts(
			array(
				'request_id' => 0,
			),
			$atts
		);

		$request_id = intval( $atts['request_id'] );

		if ( ! $request_id ) {
			return '<div class="ns-error">Invalid background check request.</div>';
		}

		// Get request details
		global $wpdb;
		$request = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ns_background_check_requests WHERE id = %d",
				$request_id
			)
		);

		if ( ! $request ) {
			return '<div class="ns-error">Background check request not found.</div>';
		}

		if ( $request->consent_given ) {
			return $this->render_consent_confirmation( $request );
		}

		// Get FCRA disclosure text
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/integrations/adapters/class-checkr-adapter.php';
		$adapter = new \NonprofitSuite\Integrations\Adapters\NS_Checkr_Adapter( array(
			'api_key' => '',
			'organization_id' => 0,
		) );

		$disclosure = $adapter->get_fcra_disclosure( $request->check_type );

		ob_start();
		?>
		<div class="ns-background-check-consent-form">
			<div class="consent-header">
				<h2><?php esc_html_e( 'Background Check Consent', 'nonprofitsuite' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Please review the following documents carefully before providing your consent.', 'nonprofitsuite' ); ?>
				</p>
			</div>

			<form id="background-check-consent-form" data-request-id="<?php echo esc_attr( $request_id ); ?>">
				<!-- Candidate Information -->
				<div class="consent-section">
					<h3><?php esc_html_e( 'Your Information', 'nonprofitsuite' ); ?></h3>
					<table class="candidate-info">
						<tr>
							<th><?php esc_html_e( 'Name:', 'nonprofitsuite' ); ?></th>
							<td><?php echo esc_html( $request->candidate_first_name . ' ' . $request->candidate_last_name ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Email:', 'nonprofitsuite' ); ?></th>
							<td><?php echo esc_html( $request->candidate_email ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Check Type:', 'nonprofitsuite' ); ?></th>
							<td><?php echo esc_html( ucfirst( $request->check_type ) ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Package:', 'nonprofitsuite' ); ?></th>
							<td><?php echo esc_html( ucfirst( $request->package_name ) ); ?></td>
						</tr>
					</table>
				</div>

				<!-- FCRA Disclosure -->
				<div class="consent-section disclosure-section">
					<h3><?php esc_html_e( '1. Disclosure Regarding Background Investigation', 'nonprofitsuite' ); ?></h3>
					<div class="disclosure-text">
						<?php echo nl2br( esc_html( $disclosure['disclosure_text'] ) ); ?>
					</div>
				</div>

				<!-- Summary of Rights -->
				<div class="consent-section">
					<h3><?php esc_html_e( '2. Summary of Your Rights Under the Fair Credit Reporting Act', 'nonprofitsuite' ); ?></h3>
					<div class="disclosure-text">
						<?php echo nl2br( esc_html( $disclosure['summary_rights'] ) ); ?>
					</div>
				</div>

				<!-- Authorization -->
				<div class="consent-section authorization-section">
					<h3><?php esc_html_e( '3. Authorization for Background Check', 'nonprofitsuite' ); ?></h3>
					<div class="disclosure-text">
						<?php echo nl2br( esc_html( $disclosure['authorization_text'] ) ); ?>
					</div>
				</div>

				<!-- Consent Checkboxes -->
				<div class="consent-section consent-checkboxes">
					<label class="consent-checkbox">
						<input type="checkbox" name="disclosure_read" id="disclosure_read" required>
						<span><?php esc_html_e( 'I acknowledge that I have read and understand the Disclosure Regarding Background Investigation.', 'nonprofitsuite' ); ?></span>
					</label>

					<label class="consent-checkbox">
						<input type="checkbox" name="rights_read" id="rights_read" required>
						<span><?php esc_html_e( 'I acknowledge that I have read and understand my rights under the Fair Credit Reporting Act.', 'nonprofitsuite' ); ?></span>
					</label>

					<label class="consent-checkbox">
						<input type="checkbox" name="authorization_given" id="authorization_given" required>
						<span><?php esc_html_e( 'I hereby authorize the organization to obtain consumer reports and/or investigative consumer reports about me for employment or volunteer purposes.', 'nonprofitsuite' ); ?></span>
					</label>
				</div>

				<!-- Electronic Signature -->
				<div class="consent-section signature-section">
					<h3><?php esc_html_e( 'Electronic Signature', 'nonprofitsuite' ); ?></h3>
					<p class="description">
						<?php esc_html_e( 'By typing your full name below, you are providing your electronic signature, which has the same legal effect as a handwritten signature.', 'nonprofitsuite' ); ?>
					</p>

					<label for="signature">
						<?php esc_html_e( 'Full Name (Electronic Signature)', 'nonprofitsuite' ); ?>
					</label>
					<input type="text" name="signature" id="signature" class="regular-text" required placeholder="Type your full name">

					<label for="signature_date">
						<?php esc_html_e( 'Date', 'nonprofitsuite' ); ?>
					</label>
					<input type="text" name="signature_date" id="signature_date" class="regular-text" value="<?php echo esc_attr( date( 'F j, Y' ) ); ?>" readonly>
				</div>

				<!-- Submit Button -->
				<div class="consent-section submit-section">
					<button type="submit" class="button button-primary button-large">
						<?php esc_html_e( 'Submit Consent', 'nonprofitsuite' ); ?>
					</button>

					<p class="privacy-note">
						<?php esc_html_e( 'Your information will be handled in accordance with applicable privacy laws and regulations.', 'nonprofitsuite' ); ?>
					</p>
				</div>

				<div id="consent-status" style="display:none; margin-top: 15px;"></div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render consent confirmation
	 *
	 * @param object $request Request object
	 * @return string Confirmation HTML
	 */
	private function render_consent_confirmation( $request ) {
		ob_start();
		?>
		<div class="ns-background-check-consent-confirmation">
			<div class="confirmation-icon">✓</div>
			<h2><?php esc_html_e( 'Consent Submitted Successfully', 'nonprofitsuite' ); ?></h2>
			<p>
				<?php esc_html_e( 'Thank you for providing your consent. Your background check has been initiated and you will be notified when the results are available.', 'nonprofitsuite' ); ?>
			</p>

			<div class="confirmation-details">
				<p>
					<strong><?php esc_html_e( 'Consent Given:', 'nonprofitsuite' ); ?></strong>
					<?php echo esc_html( date( 'F j, Y g:i A', strtotime( $request->consent_given_at ) ) ); ?>
				</p>
				<p>
					<strong><?php esc_html_e( 'Status:', 'nonprofitsuite' ); ?></strong>
					<?php echo esc_html( ucwords( str_replace( '_', ' ', $request->request_status ) ) ); ?>
				</p>
			</div>

			<?php if ( $request->candidate_portal_url ) : ?>
				<p>
					<a href="<?php echo esc_url( $request->candidate_portal_url ); ?>" class="button button-primary" target="_blank">
						<?php esc_html_e( 'View Your Background Check Portal', 'nonprofitsuite' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * AJAX: Submit consent
	 */
	public function ajax_submit_consent() {
		check_ajax_referer( 'ns_background_check_consent', 'nonce' );

		$request_id = intval( $_POST['request_id'] ?? 0 );
		$signature  = sanitize_text_field( $_POST['signature'] ?? '' );

		if ( ! $request_id || ! $signature ) {
			wp_send_json_error( array( 'message' => 'Invalid request or signature' ) );
		}

		// Get client IP address
		$ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

		// Record consent
		$result = $this->manager->record_consent( $request_id, $ip_address );

		if ( $result['success'] ) {
			wp_send_json_success( array(
				'message' => 'Consent recorded successfully',
			) );
		} else {
			wp_send_json_error( array(
				'message' => $result['error'] ?? 'Failed to record consent',
			) );
		}
	}
}

// Initialize
new NS_Background_Check_Consent();
