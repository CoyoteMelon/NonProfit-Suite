<?php
/**
 * Beta Survey Form View
 *
 * Renders the beta testing survey form.
 * Used by shortcode: [nonprofitsuite_beta_survey application_id="X"]
 *
 * @package    NonprofitSuite
 * @subpackage Beta/Views
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$application_id = isset( $atts['application_id'] ) ? (int) $atts['application_id'] : 0;

// Verify application exists
$app_manager = NonprofitSuite_Beta_Application_Manager::get_instance();
$application = $app_manager->get_application( $application_id );

if ( ! $application ) {
	echo '<p>Invalid survey link.</p>';
	return;
}

$survey_number = ( (int) $application['survey_count'] ) + 1;
?>

<div class="ns-beta-survey-wrapper" style="max-width: 900px; margin: 0 auto; padding: 20px;">

	<div class="ns-survey-header" style="text-align: center; margin-bottom: 40px;">
		<h1 style="color: #2271b1;">Beta Survey #<?php echo absint( $survey_number ); ?></h1>
		<p style="font-size: 18px; color: #666;">
			Thank you for being part of our beta program, <?php echo esc_html( $application['contact_name'] ); ?>!
		</p>
		<p style="color: #999;">This survey takes about 5-10 minutes</p>
	</div>

	<form id="ns-beta-survey-form" method="post" style="background: white; padding: 30px; border: 1px solid #ddd; border-radius: 8px;">

		<?php wp_nonce_field( 'ns_beta_survey_' . $application_id, 'ns_survey_nonce' ); ?>
		<input type="hidden" name="application_id" value="<?php echo absint( $application_id ); ?>">

		<!-- Overall Experience -->
		<section style="margin-bottom: 40px;">
			<h2 style="color: #2271b1; border-bottom: 2px solid #2271b1; padding-bottom: 10px;">Overall Experience</h2>
			<p style="margin-bottom: 20px; color: #666;">Please rate your overall experience (1 = Poor, 5 = Excellent)</p>

			<?php
			$overall_questions = array(
				'overall_satisfaction' => 'Overall Satisfaction',
				'ease_of_use'          => 'Ease of Use',
				'feature_completeness' => 'Feature Completeness',
				'performance'          => 'Performance & Speed',
				'would_recommend'      => 'Would you recommend to others?',
			);

			foreach ( $overall_questions as $key => $label ) :
			?>
				<div style="margin-bottom: 20px; padding: 15px; background: #f9fafb; border-radius: 4px;">
					<label style="display: block; font-weight: bold; margin-bottom: 10px;"><?php echo esc_html( $label ); ?></label>
					<div class="rating-stars" style="font-size: 30px;">
						<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
							<label style="cursor: pointer; display: inline-block; margin-right: 5px;">
								<input type="radio" name="<?php echo esc_attr( $key ); ?>" value="<?php echo absint( $i ); ?>" style="display: none;" onchange="updateStars(this)">
								<span class="star" data-rating="<?php echo absint( $i ); ?>">☆</span>
							</label>
						<?php endfor; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</section>

		<!-- Feature Ratings -->
		<section style="margin-bottom: 40px;">
			<h2 style="color: #2271b1; border-bottom: 2px solid #2271b1; padding-bottom: 10px;">Feature Ratings</h2>
			<p style="margin-bottom: 20px; color: #666;">Rate the features you've used (leave blank if you haven't used a feature)</p>

			<?php
			$features = array(
				'meetings_rating'        => 'Meetings & Board Management',
				'documents_rating'       => 'Document Management',
				'treasury_rating'        => 'Treasury & Accounting',
				'donors_rating'          => 'Donor Management',
				'volunteers_rating'      => 'Volunteer Management',
				'compliance_rating'      => 'Compliance & Reporting',
				'calendar_rating'        => 'Calendar & Scheduling',
				'email_rating'           => 'Email & Communications',
				'payments_rating'        => 'Payment Processing',
				'membership_rating'      => 'Membership Management',
				'board_rating'           => 'Board Portal',
				'communications_rating'  => 'Internal Communications',
				'events_rating'          => 'Event Management',
				'grants_rating'          => 'Grant Management',
				'inventory_rating'       => 'Inventory Tracking',
				'programs_rating'        => 'Program Management',
			);

			$column = 0;
			echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
			foreach ( $features as $key => $label ) :
				if ( $column % 8 == 0 && $column > 0 ) {
					echo '</div><div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
				}
			?>
				<div style="padding: 12px; background: #f9fafb; border-radius: 4px;">
					<label style="display: block; font-weight: bold; margin-bottom: 8px; font-size: 14px;"><?php echo esc_html( $label ); ?></label>
					<div class="rating-stars-small" style="font-size: 20px;">
						<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
							<label style="cursor: pointer; display: inline-block;">
								<input type="radio" name="<?php echo esc_attr( $key ); ?>" value="<?php echo absint( $i ); ?>" style="display: none;" onchange="updateStars(this)">
								<span class="star" data-rating="<?php echo absint( $i ); ?>">☆</span>
							</label>
						<?php endfor; ?>
					</div>
				</div>
			<?php
				$column++;
			endforeach;
			echo '</div>';
			?>
		</section>

		<!-- Integration Usage -->
		<section style="margin-bottom: 40px;">
			<h2 style="color: #2271b1; border-bottom: 2px solid #2271b1; padding-bottom: 10px;">Integration Usage</h2>

			<div style="margin-bottom: 20px;">
				<label style="display: block; font-weight: bold; margin-bottom: 10px;">Which integrations have you used?</label>
				<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
					<?php
					$integrations = array(
						'Google Calendar', 'Outlook Calendar', 'Gmail', 'Outlook Email', 'SendGrid',
						'Stripe', 'PayPal', 'Square', 'QuickBooks', 'Xero',
						'Salesforce', 'HubSpot', 'Mailchimp', 'Zoom', 'Google Meet'
					);
					foreach ( $integrations as $integration ) :
					?>
						<label style="display: block;">
							<input type="checkbox" name="integrations_used[]" value="<?php echo esc_attr( $integration ); ?>">
							<?php echo esc_html( $integration ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<div style="margin-bottom: 20px;">
				<label for="integration_issues" style="display: block; font-weight: bold; margin-bottom: 10px;">
					Any integration issues or problems?
				</label>
				<textarea id="integration_issues" name="integration_issues" rows="3"
						  style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"
						  placeholder="Describe any problems you encountered with integrations..."></textarea>
			</div>
		</section>

		<!-- Open Feedback -->
		<section style="margin-bottom: 40px;">
			<h2 style="color: #2271b1; border-bottom: 2px solid #2271b1; padding-bottom: 10px;">Your Feedback</h2>

			<div style="margin-bottom: 20px;">
				<label for="what_works_well" style="display: block; font-weight: bold; margin-bottom: 10px;">
					What's working well? ✅
				</label>
				<textarea id="what_works_well" name="what_works_well" rows="4"
						  style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"
						  placeholder="Tell us what you love about NonprofitSuite..."></textarea>
			</div>

			<div style="margin-bottom: 20px;">
				<label for="what_is_broken" style="display: block; font-weight: bold; margin-bottom: 10px;">
					What's broken or not working? 🐛
				</label>
				<textarea id="what_is_broken" name="what_is_broken" rows="4"
						  style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"
						  placeholder="Report any bugs, errors, or things that don't work..."></textarea>
			</div>

			<div style="margin-bottom: 20px;">
				<label for="what_is_missing" style="display: block; font-weight: bold; margin-bottom: 10px;">
					What's missing? 🤔
				</label>
				<textarea id="what_is_missing" name="what_is_missing" rows="4"
						  style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"
						  placeholder="Features you expected but didn't find..."></textarea>
			</div>

			<div style="margin-bottom: 20px;">
				<label for="feature_requests" style="display: block; font-weight: bold; margin-bottom: 10px;">
					Feature requests 💡
				</label>
				<textarea id="feature_requests" name="feature_requests" rows="4"
						  style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"
						  placeholder="New features or improvements you'd like to see..."></textarea>
			</div>

			<div style="margin-bottom: 20px;">
				<label for="pain_points" style="display: block; font-weight: bold; margin-bottom: 10px;">
					Biggest pain points 😫
				</label>
				<textarea id="pain_points" name="pain_points" rows="4"
						  style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"
						  placeholder="What frustrates you most or slows you down..."></textarea>
			</div>
		</section>

		<!-- Technical Info -->
		<section style="margin-bottom: 40px;">
			<h2 style="color: #2271b1; border-bottom: 2px solid #2271b1; padding-bottom: 10px;">Organization Details</h2>

			<div style="margin-bottom: 20px;">
				<label for="active_users" style="display: block; font-weight: bold; margin-bottom: 10px;">
					How many active users in your organization?
				</label>
				<select id="active_users" name="active_users" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
					<option value="1">Just me</option>
					<option value="2">2-5 users</option>
					<option value="6">6-10 users</option>
					<option value="11">11-20 users</option>
					<option value="21">21-50 users</option>
					<option value="51">50+ users</option>
				</select>
			</div>

			<div style="margin-bottom: 20px;">
				<label for="org_size" style="display: block; font-weight: bold; margin-bottom: 10px;">
					Organization size (annual budget)
				</label>
				<select id="org_size" name="org_size" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
					<option value="under_50k">Under $50,000</option>
					<option value="50k_250k">$50,000 - $250,000</option>
					<option value="250k_1m">$250,000 - $1 million</option>
					<option value="1m_5m">$1 million - $5 million</option>
					<option value="over_5m">Over $5 million</option>
				</select>
			</div>
		</section>

		<!-- Submit -->
		<div style="text-align: center; margin-top: 40px;">
			<button type="submit" name="submit_beta_survey"
					style="background: #2271b1; color: white; padding: 15px 60px; border: none; border-radius: 4px; font-size: 18px; font-weight: bold; cursor: pointer;">
				Submit Survey
			</button>
		</div>
	</form>

</div>

<script>
// Star rating functionality
function updateStars(input) {
	const container = input.closest('.rating-stars, .rating-stars-small');
	const stars = container.querySelectorAll('.star');
	const rating = parseInt(input.value);

	stars.forEach((star, index) => {
		if (index < rating) {
			star.textContent = '★';
			star.style.color = '#fbbf24';
		} else {
			star.textContent = '☆';
			star.style.color = '#d1d5db';
		}
	});
}

// Hover effect for stars
document.querySelectorAll('.star').forEach(star => {
	star.addEventListener('mouseenter', function() {
		const container = this.closest('.rating-stars, .rating-stars-small');
		const rating = parseInt(this.dataset.rating);
		const stars = container.querySelectorAll('.star');

		stars.forEach((s, index) => {
			if (index < rating) {
				s.textContent = '★';
				s.style.color = '#fbbf24';
			} else {
				s.textContent = '☆';
				s.style.color = '#d1d5db';
			}
		});
	});

	star.parentElement.addEventListener('mouseleave', function() {
		const container = this.closest('.rating-stars, .rating-stars-small');
		const selectedInput = container.querySelector('input:checked');

		if (selectedInput) {
			updateStars(selectedInput);
		} else {
			const stars = container.querySelectorAll('.star');
			stars.forEach(s => {
				s.textContent = '☆';
				s.style.color = '#d1d5db';
			});
		}
	});
});

// Form submission
document.getElementById('ns-beta-survey-form').addEventListener('submit', function(e) {
	const submitBtn = this.querySelector('button[type="submit"]');
	submitBtn.disabled = true;
	submitBtn.textContent = 'Submitting...';
});
</script>

<?php
// Handle form submission
if ( isset( $_POST['submit_beta_survey'] ) && wp_verify_nonce( $_POST['ns_survey_nonce'], 'ns_beta_survey_' . $application_id ) ) {

	// Prepare survey data
	$survey_data = array(
		// Overall ratings
		'overall_satisfaction' => isset( $_POST['overall_satisfaction'] ) ? (int) $_POST['overall_satisfaction'] : null,
		'ease_of_use'          => isset( $_POST['ease_of_use'] ) ? (int) $_POST['ease_of_use'] : null,
		'feature_completeness' => isset( $_POST['feature_completeness'] ) ? (int) $_POST['feature_completeness'] : null,
		'performance'          => isset( $_POST['performance'] ) ? (int) $_POST['performance'] : null,
		'would_recommend'      => isset( $_POST['would_recommend'] ) ? (int) $_POST['would_recommend'] : null,

		// Feature ratings
		'meetings_rating'        => isset( $_POST['meetings_rating'] ) ? (int) $_POST['meetings_rating'] : null,
		'documents_rating'       => isset( $_POST['documents_rating'] ) ? (int) $_POST['documents_rating'] : null,
		'treasury_rating'        => isset( $_POST['treasury_rating'] ) ? (int) $_POST['treasury_rating'] : null,
		'donors_rating'          => isset( $_POST['donors_rating'] ) ? (int) $_POST['donors_rating'] : null,
		'volunteers_rating'      => isset( $_POST['volunteers_rating'] ) ? (int) $_POST['volunteers_rating'] : null,
		'compliance_rating'      => isset( $_POST['compliance_rating'] ) ? (int) $_POST['compliance_rating'] : null,
		'calendar_rating'        => isset( $_POST['calendar_rating'] ) ? (int) $_POST['calendar_rating'] : null,
		'email_rating'           => isset( $_POST['email_rating'] ) ? (int) $_POST['email_rating'] : null,
		'payments_rating'        => isset( $_POST['payments_rating'] ) ? (int) $_POST['payments_rating'] : null,
		'membership_rating'      => isset( $_POST['membership_rating'] ) ? (int) $_POST['membership_rating'] : null,
		'board_rating'           => isset( $_POST['board_rating'] ) ? (int) $_POST['board_rating'] : null,
		'communications_rating'  => isset( $_POST['communications_rating'] ) ? (int) $_POST['communications_rating'] : null,
		'events_rating'          => isset( $_POST['events_rating'] ) ? (int) $_POST['events_rating'] : null,
		'grants_rating'          => isset( $_POST['grants_rating'] ) ? (int) $_POST['grants_rating'] : null,
		'inventory_rating'       => isset( $_POST['inventory_rating'] ) ? (int) $_POST['inventory_rating'] : null,
		'programs_rating'        => isset( $_POST['programs_rating'] ) ? (int) $_POST['programs_rating'] : null,

		// Integration usage
		'integrations_used'   => isset( $_POST['integrations_used'] ) && is_array( $_POST['integrations_used'] )
			? array_map( 'sanitize_text_field', $_POST['integrations_used'] )
			: array(),
		'integration_issues'  => sanitize_textarea_field( $_POST['integration_issues'] ?? '' ),

		// Open feedback
		'what_works_well' => sanitize_textarea_field( $_POST['what_works_well'] ?? '' ),
		'what_is_broken'  => sanitize_textarea_field( $_POST['what_is_broken'] ?? '' ),
		'what_is_missing' => sanitize_textarea_field( $_POST['what_is_missing'] ?? '' ),
		'feature_requests' => sanitize_textarea_field( $_POST['feature_requests'] ?? '' ),
		'pain_points'     => sanitize_textarea_field( $_POST['pain_points'] ?? '' ),

		// Technical info
		'active_users' => isset( $_POST['active_users'] ) ? (int) $_POST['active_users'] : null,
		'org_size'     => sanitize_text_field( $_POST['org_size'] ?? '' ),
	);

	$survey_manager = NonprofitSuite_Beta_Survey_Manager::get_instance();
	$result = $survey_manager->submit_survey( $application_id, $survey_data );

	if ( is_wp_error( $result ) ) {
		echo '<div style="background: #fee; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0;">';
		echo '<strong>Error:</strong> ' . esc_html( $result->get_error_message() );
		echo '</div>';
	} else {
		echo '<div style="background: #f0fdf4; border-left: 4px solid #10b981; padding: 30px; margin: 20px 0; text-align: center;">';
		echo '<h2 style="margin-top: 0; color: #10b981;">✓ Thank You!</h2>';
		echo '<p style="font-size: 18px;">Your feedback has been submitted successfully.</p>';
		echo '<p>We truly appreciate you taking the time to help us improve NonprofitSuite!</p>';
		echo '</div>';

		// Hide form after successful submission
		echo '<script>document.getElementById("ns-beta-survey-form").style.display = "none";</script>';
	}
}
?>
