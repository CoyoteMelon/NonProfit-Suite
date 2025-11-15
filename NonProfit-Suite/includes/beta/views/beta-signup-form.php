<?php
/**
 * Beta Signup Form View
 *
 * Renders the public-facing beta testing program signup form.
 * Used by shortcode: [nonprofitsuite_beta_signup]
 *
 * @package    NonprofitSuite
 * @subpackage Beta/Views
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get application manager instance
$app_manager = NonprofitSuite_Beta_Application_Manager::get_instance();
$stats = $app_manager->get_statistics();

// Check if program is active
$settings = get_option( 'ns_beta_program_settings', array() );
$program_active = $settings['program_active'] ?? true;

// Calculate available slots
$slots_501c3_available = ( $settings['max_501c3_slots'] ?? 500 ) - ( $stats['501c3_approved'] ?? 0 );
$program_full = $slots_501c3_available <= 0;
?>

<div class="ns-beta-signup-wrapper" style="max-width: 800px; margin: 0 auto; padding: 20px;">

	<?php if ( ! $program_active ) : ?>
		<div class="ns-alert ns-alert-info" style="background: #e7f3ff; border-left: 4px solid #2271b1; padding: 15px; margin-bottom: 20px;">
			<h3 style="margin-top: 0;">Beta Program Currently Closed</h3>
			<p>Thank you for your interest! The beta testing program is currently closed. Please check back later.</p>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<div class="ns-beta-header" style="text-align: center; margin-bottom: 40px;">
		<h1 style="color: #2271b1; margin-bottom: 10px;">Join the NonprofitSuite Beta Program</h1>
		<p style="font-size: 18px; color: #666;">Get a free lifetime license and help shape the future of nonprofit management software</p>
	</div>

	<!-- Slots Available -->
	<div class="ns-slots-status" style="background: #f0f6fc; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
		<h3 style="margin-top: 0; color: #2271b1;">Available Slots</h3>
		<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
			<div>
				<strong>Registered 501(c)(3) Nonprofits:</strong><br>
				<span style="font-size: 24px; color: <?php echo $slots_501c3_available > 0 ? '#10b981' : '#ef4444'; ?>">
					<?php echo max( 0, $slots_501c3_available ); ?> / <?php echo $settings['max_501c3_slots'] ?? 500; ?>
				</span> slots remaining
			</div>
			<div>
				<strong>Pre-Nonprofits:</strong><br>
				<span style="font-size: 24px; color: #10b981;">
					First 10 per state/territory
				</span>
			</div>
		</div>
	</div>

	<!-- Benefits -->
	<div class="ns-beta-benefits" style="margin-bottom: 40px;">
		<h3 style="color: #2271b1;">Beta Tester Benefits</h3>
		<ul style="list-style: none; padding-left: 0;">
			<li style="padding: 10px 0; border-bottom: 1px solid #eee;">✅ <strong>Free Lifetime License</strong> - No recurring costs, ever!</li>
			<li style="padding: 10px 0; border-bottom: 1px solid #eee;">✅ <strong>All Features Unlocked</strong> - Full access to everything</li>
			<li style="padding: 10px 0; border-bottom: 1px solid #eee;">✅ <strong>Direct Feedback Channel</strong> - Your voice shapes development</li>
			<li style="padding: 10px 0; border-bottom: 1px solid #eee;">✅ <strong>Early Access</strong> - Be first to try new features</li>
			<li style="padding: 10px 0; border-bottom: 1px solid #eee;">✅ <strong>Beta Tester Badge</strong> - Recognition in our community</li>
		</ul>
	</div>

	<!-- Application Form -->
	<form id="ns-beta-signup-form" method="post" enctype="multipart/form-data" style="background: white; padding: 30px; border: 1px solid #ddd; border-radius: 8px;">

		<?php wp_nonce_field( 'ns_beta_signup', 'ns_beta_nonce' ); ?>

		<h3 style="margin-top: 0; color: #2271b1;">Application Form</h3>

		<!-- Organization Type -->
		<div class="form-group" style="margin-bottom: 25px;">
			<label style="display: block; font-weight: bold; margin-bottom: 8px;">
				Organization Type <span style="color: red;">*</span>
			</label>
			<label style="display: block; margin-bottom: 10px;">
				<input type="radio" name="is_501c3" value="1" required onchange="toggleFormFields()">
				Registered 501(c)(3) Nonprofit
			</label>
			<label style="display: block;">
				<input type="radio" name="is_501c3" value="0" required onchange="toggleFormFields()">
				Pre-Nonprofit (Forming Stage)
			</label>
		</div>

		<!-- Organization Information -->
		<div class="form-group" style="margin-bottom: 20px;">
			<label for="organization_name" style="display: block; font-weight: bold; margin-bottom: 8px;">
				Organization Name <span style="color: red;">*</span>
			</label>
			<input type="text" id="organization_name" name="organization_name" required
				   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
		</div>

		<!-- EIN (for 501c3 only) -->
		<div class="form-group" id="ein_group" style="margin-bottom: 20px; display: none;">
			<label for="ein" style="display: block; font-weight: bold; margin-bottom: 8px;">
				EIN (Employer Identification Number) <span style="color: red;">*</span>
			</label>
			<input type="text" id="ein" name="ein" placeholder="XX-XXXXXXX"
				   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
			<small style="color: #666;">Format: XX-XXXXXXX</small>
		</div>

		<!-- Determination Letter (for 501c3 only) -->
		<div class="form-group" id="letter_group" style="margin-bottom: 20px; display: none;">
			<label style="display: block; font-weight: bold; margin-bottom: 8px;">
				IRS 501(c)(3) Determination Letter
			</label>
			<label style="display: block; margin-bottom: 10px;">
				<input type="checkbox" name="has_determination_letter" value="1">
				I have my IRS 501(c)(3) determination letter
			</label>
			<input type="file" name="determination_letter" accept=".pdf,.jpg,.jpeg,.png"
				   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
			<small style="color: #666;">Optional: Upload a copy (PDF, JPG, or PNG)</small>
		</div>

		<!-- Pre-Nonprofit Message -->
		<div class="form-group" id="prenp_message" style="margin-bottom: 20px; display: none; background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107;">
			<h4 style="margin-top: 0; color: #856404;">📚 For Pre-Nonprofits</h4>
			<p style="margin-bottom: 0;">We strongly encourage you to complete our <strong>"Forming a Nonprofit"</strong> module. While not required for your beta license, this comprehensive guide will help you successfully establish your nonprofit organization over the next 6-12 months.</p>
		</div>

		<!-- Contact Information -->
		<h4 style="color: #2271b1; margin-top: 30px;">Contact Information</h4>

		<div class="form-group" style="margin-bottom: 20px;">
			<label for="contact_name" style="display: block; font-weight: bold; margin-bottom: 8px;">
				Contact Person Name <span style="color: red;">*</span>
			</label>
			<input type="text" id="contact_name" name="contact_name" required
				   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
		</div>

		<div class="form-group" style="margin-bottom: 20px;">
			<label for="contact_email" style="display: block; font-weight: bold; margin-bottom: 8px;">
				Email Address <span style="color: red;">*</span>
			</label>
			<input type="email" id="contact_email" name="contact_email" required
				   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
		</div>

		<div class="form-group" style="margin-bottom: 20px;">
			<label for="contact_phone" style="display: block; font-weight: bold; margin-bottom: 8px;">
				Phone Number
			</label>
			<input type="tel" id="contact_phone" name="contact_phone"
				   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
		</div>

		<!-- Location -->
		<h4 style="color: #2271b1; margin-top: 30px;">Location</h4>

		<div class="form-group" style="margin-bottom: 20px;">
			<label for="state" style="display: block; font-weight: bold; margin-bottom: 8px;">
				State/Territory <span style="color: red;">*</span>
			</label>
			<select id="state" name="state" required
					style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
				<option value="">Select State/Territory</option>
				<optgroup label="States">
					<option value="AL">Alabama</option>
					<option value="AK">Alaska</option>
					<option value="AZ">Arizona</option>
					<option value="AR">Arkansas</option>
					<option value="CA">California</option>
					<option value="CO">Colorado</option>
					<option value="CT">Connecticut</option>
					<option value="DE">Delaware</option>
					<option value="FL">Florida</option>
					<option value="GA">Georgia</option>
					<option value="HI">Hawaii</option>
					<option value="ID">Idaho</option>
					<option value="IL">Illinois</option>
					<option value="IN">Indiana</option>
					<option value="IA">Iowa</option>
					<option value="KS">Kansas</option>
					<option value="KY">Kentucky</option>
					<option value="LA">Louisiana</option>
					<option value="ME">Maine</option>
					<option value="MD">Maryland</option>
					<option value="MA">Massachusetts</option>
					<option value="MI">Michigan</option>
					<option value="MN">Minnesota</option>
					<option value="MS">Mississippi</option>
					<option value="MO">Missouri</option>
					<option value="MT">Montana</option>
					<option value="NE">Nebraska</option>
					<option value="NV">Nevada</option>
					<option value="NH">New Hampshire</option>
					<option value="NJ">New Jersey</option>
					<option value="NM">New Mexico</option>
					<option value="NY">New York</option>
					<option value="NC">North Carolina</option>
					<option value="ND">North Dakota</option>
					<option value="OH">Ohio</option>
					<option value="OK">Oklahoma</option>
					<option value="OR">Oregon</option>
					<option value="PA">Pennsylvania</option>
					<option value="RI">Rhode Island</option>
					<option value="SC">South Carolina</option>
					<option value="SD">South Dakota</option>
					<option value="TN">Tennessee</option>
					<option value="TX">Texas</option>
					<option value="UT">Utah</option>
					<option value="VT">Vermont</option>
					<option value="VA">Virginia</option>
					<option value="WA">Washington</option>
					<option value="WV">West Virginia</option>
					<option value="WI">Wisconsin</option>
					<option value="WY">Wyoming</option>
				</optgroup>
				<optgroup label="Territories">
					<option value="AS">American Samoa</option>
					<option value="DC">District of Columbia</option>
					<option value="GU">Guam</option>
					<option value="MP">Northern Mariana Islands</option>
					<option value="PR">Puerto Rico</option>
					<option value="VI">U.S. Virgin Islands</option>
				</optgroup>
			</select>
		</div>

		<div class="form-group" style="margin-bottom: 20px;">
			<label for="city" style="display: block; font-weight: bold; margin-bottom: 8px;">
				City
			</label>
			<input type="text" id="city" name="city"
				   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
		</div>

		<!-- Agreement -->
		<div class="form-group" style="margin-bottom: 25px; background: #f9fafb; padding: 15px; border-radius: 4px;">
			<label style="display: block;">
				<input type="checkbox" name="agree_feedback" value="1" required>
				I agree to provide feedback through periodic surveys and help improve NonprofitSuite
			</label>
		</div>

		<!-- Submit Button -->
		<div class="form-group" style="text-align: center;">
			<button type="submit" name="submit_beta_application"
					style="background: #2271b1; color: white; padding: 15px 40px; border: none; border-radius: 4px; font-size: 16px; font-weight: bold; cursor: pointer;">
				Submit Application
			</button>
		</div>

		<!-- Form Messages -->
		<div id="form-messages" style="margin-top: 20px;"></div>
	</form>

	<!-- What Happens Next -->
	<div class="ns-next-steps" style="margin-top: 40px; background: #f9fafb; padding: 20px; border-radius: 8px;">
		<h3 style="color: #2271b1;">What Happens Next?</h3>
		<ol>
			<li style="margin-bottom: 10px;"><strong>Review:</strong> We review applications in the order received</li>
			<li style="margin-bottom: 10px;"><strong>Approval:</strong> If slots are available, you'll be auto-approved!</li>
			<li style="margin-bottom: 10px;"><strong>License Key:</strong> You'll receive your free lifetime license key via email</li>
			<li style="margin-bottom: 10px;"><strong>Install:</strong> Download and install NonprofitSuite with your license</li>
			<li style="margin-bottom: 10px;"><strong>Feedback:</strong> Share your experience through periodic surveys</li>
		</ol>
	</div>
</div>

<script>
function toggleFormFields() {
	const is501c3 = document.querySelector('input[name="is_501c3"]:checked');
	if (!is501c3) return;

	const is501c3Value = is501c3.value === '1';

	// Show/hide 501c3 fields
	document.getElementById('ein_group').style.display = is501c3Value ? 'block' : 'none';
	document.getElementById('letter_group').style.display = is501c3Value ? 'block' : 'none';
	document.getElementById('ein').required = is501c3Value;

	// Show/hide pre-nonprofit message
	document.getElementById('prenp_message').style.display = is501c3Value ? 'none' : 'block';
}

// Handle form submission
document.getElementById('ns-beta-signup-form').addEventListener('submit', function(e) {
	const submitBtn = this.querySelector('button[type="submit"]');
	submitBtn.disabled = true;
	submitBtn.textContent = 'Submitting...';
});
</script>

<?php
// Handle form submission
if ( isset( $_POST['submit_beta_application'] ) && wp_verify_nonce( $_POST['ns_beta_nonce'], 'ns_beta_signup' ) ) {

	$application_data = array(
		'organization_name'         => sanitize_text_field( $_POST['organization_name'] ?? '' ),
		'ein'                       => sanitize_text_field( $_POST['ein'] ?? '' ),
		'contact_name'              => sanitize_text_field( $_POST['contact_name'] ?? '' ),
		'contact_email'             => sanitize_email( $_POST['contact_email'] ?? '' ),
		'contact_phone'             => sanitize_text_field( $_POST['contact_phone'] ?? '' ),
		'is_501c3'                  => isset( $_POST['is_501c3'] ) && $_POST['is_501c3'] == '1',
		'has_determination_letter'  => isset( $_POST['has_determination_letter'] ),
		'state'                     => sanitize_text_field( $_POST['state'] ?? '' ),
		'city'                      => sanitize_text_field( $_POST['city'] ?? '' ),
	);

	// Handle file upload if present
	if ( ! empty( $_FILES['determination_letter']['name'] ) ) {
		// Define allowed MIME types for security
		$allowed_mimes = array(
			'pdf'  => 'application/pdf',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
		);

		// Upload with MIME type restrictions
		$upload = wp_handle_upload(
			$_FILES['determination_letter'],
			array(
				'test_form' => false,
				'mimes'     => $allowed_mimes,
			)
		);

		if ( ! isset( $upload['error'] ) ) {
			// Double-check file type after upload for additional security
			$filetype = wp_check_filetype( $upload['file'] );

			if ( ! in_array( $filetype['type'], $allowed_mimes ) ) {
				// Delete the file and show error
				@unlink( $upload['file'] );
				echo '<div class="ns-alert ns-alert-error" style="background: #fee; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0;">';
				echo '<strong>Error:</strong> Only PDF and image files (JPG, PNG) are allowed for determination letters.';
				echo '</div>';
			} else {
				// File is valid, save path
				$application_data['determination_letter_file'] = $upload['file'];
			}
		} else {
			// Show upload error
			echo '<div class="ns-alert ns-alert-error" style="background: #fee; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0;">';
			echo '<strong>Upload Error:</strong> ' . esc_html( $upload['error'] );
			echo '</div>';
		}
	}

	$result = $app_manager->submit_application( $application_data );

	if ( is_wp_error( $result ) ) {
		echo '<div class="ns-alert ns-alert-error" style="background: #fee; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0;">';
		echo '<strong>Error:</strong> ' . esc_html( $result->get_error_message() );
		echo '</div>';
	} else {
		echo '<div class="ns-alert ns-alert-success" style="background: #f0fdf4; border-left: 4px solid #10b981; padding: 20px; margin: 20px 0; text-align: center;">';
		echo '<h3 style="margin-top: 0; color: #10b981;">✓ Application Submitted!</h3>';
		echo '<p>' . esc_html( $result['message'] ) . '</p>';
		if ( ! empty( $result['license_key'] ) ) {
			echo '<div style="background: white; padding: 15px; margin: 15px 0; border: 2px solid #10b981; border-radius: 4px;">';
			echo '<strong>Your License Key:</strong><br>';
			echo '<span style="font-size: 20px; font-family: monospace;">' . esc_html( $result['license_key'] ) . '</span>';
			echo '</div>';
			echo '<p><small>Check your email for installation instructions!</small></p>';
		}
		echo '</div>';

		// Hide form after successful submission
		echo '<script>document.getElementById("ns-beta-signup-form").style.display = "none";</script>';
	}
}
?>
