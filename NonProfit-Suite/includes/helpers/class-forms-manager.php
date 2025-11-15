<?php
/**
 * Forms Manager
 *
 * Central coordinator for form/survey operations across different providers.
 * Manages form creation, submission processing, and adapter coordination.
 *
 * @package NonprofitSuite
 * @subpackage Helpers
 */

class NS_Forms_Manager {
	private static $instance = null;

	/**
	 * Get singleton instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Register webhook endpoints
		add_action( 'rest_api_init', array( $this, 'register_webhook_endpoints' ) );

		// Register form shortcode
		add_shortcode( 'ns_form', array( $this, 'render_form_shortcode' ) );
	}

	/**
	 * Get form adapter for a specific provider.
	 *
	 * @param string $provider Provider name (google_forms, typeform, jotform).
	 * @param int    $organization_id Organization ID.
	 * @return NS_Form_Adapter|WP_Error Adapter instance or error.
	 */
	public function get_adapter( $provider, $organization_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_forms_settings';
		$settings = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE organization_id = %d AND provider = %s AND is_active = 1",
				$organization_id,
				$provider
			),
			ARRAY_A
		);

		if ( ! $settings ) {
			return new WP_Error( 'no_settings', 'No active settings found for this provider.' );
		}

		require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/interface-form-adapter.php';

		switch ( $provider ) {
			case 'google_forms':
				require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/class-google-forms-adapter.php';
				return new NS_Google_Forms_Adapter(
					$settings['oauth_token'],
					$settings['oauth_refresh_token']
				);

			case 'typeform':
				require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/class-typeform-adapter.php';
				return new NS_Typeform_Adapter(
					$settings['api_key'],
					$settings['webhook_secret']
				);

			case 'jotform':
				require_once NS_PLUGIN_DIR . 'includes/integrations/adapters/class-jotform-adapter.php';
				return new NS_JotForm_Adapter( $settings['api_key'] );

			default:
				return new WP_Error( 'unsupported_provider', 'Unsupported form provider.' );
		}
	}

	/**
	 * Create a new form.
	 *
	 * @param array $form_data Form configuration.
	 * @param array $fields Array of field configurations.
	 * @return int|WP_Error Form ID or error.
	 */
	public function create_form( $form_data, $fields ) {
		global $wpdb;

		$provider = $form_data['provider'] ?? 'builtin';

		// For external providers, create the form via adapter
		if ( $provider !== 'builtin' ) {
			$adapter = $this->get_adapter( $provider, $form_data['organization_id'] );

			if ( is_wp_error( $adapter ) ) {
				return $adapter;
			}

			$result = $adapter->create_form( $form_data, $fields );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$form_data['provider_form_id'] = $result['provider_form_id'];
			$form_data['form_url']          = $result['form_url'];
			$form_data['embed_code']        = $adapter->get_embed_code( $result['provider_form_id'] );
		}

		// Save form to database
		$table = $wpdb->prefix . 'ns_forms';
		$wpdb->insert(
			$table,
			array(
				'organization_id'      => $form_data['organization_id'],
				'form_name'            => $form_data['form_name'],
				'form_type'            => $form_data['form_type'] ?? 'custom',
				'description'          => $form_data['description'] ?? '',
				'provider'             => $provider,
				'provider_form_id'     => $form_data['provider_form_id'] ?? null,
				'form_url'             => $form_data['form_url'] ?? null,
				'embed_code'           => $form_data['embed_code'] ?? null,
				'status'               => $form_data['status'] ?? 'draft',
				'confirmation_message' => $form_data['confirmation_message'] ?? 'Thank you for your submission!',
				'notification_emails'  => $form_data['notification_emails'] ?? '',
				'redirect_url'         => $form_data['redirect_url'] ?? null,
				'allow_anonymous'      => $form_data['allow_anonymous'] ?? 1,
				'require_login'        => $form_data['require_login'] ?? 0,
				'enable_captcha'       => $form_data['enable_captcha'] ?? 0,
				'settings'             => ! empty( $form_data['settings'] ) ? wp_json_encode( $form_data['settings'] ) : null,
				'created_by'           => get_current_user_id(),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%d' )
		);

		$form_id = $wpdb->insert_id;

		// Save fields for builtin forms
		if ( $provider === 'builtin' && ! empty( $fields ) ) {
			$this->save_form_fields( $form_id, $fields );
		}

		do_action( 'ns_form_created', $form_id, $form_data );

		return $form_id;
	}

	/**
	 * Save form fields to database.
	 *
	 * @param int   $form_id Form ID.
	 * @param array $fields Array of field configurations.
	 */
	private function save_form_fields( $form_id, $fields ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ns_form_fields';

		foreach ( $fields as $field ) {
			$wpdb->insert(
				$table,
				array(
					'form_id'           => $form_id,
					'field_name'        => $field['field_name'],
					'field_label'       => $field['field_label'],
					'field_type'        => $field['field_type'],
					'field_options'     => ! empty( $field['field_options'] ) ? wp_json_encode( $field['field_options'] ) : null,
					'placeholder'       => $field['placeholder'] ?? null,
					'default_value'     => $field['default_value'] ?? null,
					'is_required'       => $field['is_required'] ?? 0,
					'validation_rules'  => ! empty( $field['validation_rules'] ) ? wp_json_encode( $field['validation_rules'] ) : null,
					'conditional_logic' => ! empty( $field['conditional_logic'] ) ? wp_json_encode( $field['conditional_logic'] ) : null,
					'field_order'       => $field['field_order'] ?? 0,
					'page_number'       => $field['page_number'] ?? 1,
					'help_text'         => $field['help_text'] ?? null,
					'css_class'         => $field['css_class'] ?? null,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * Process form submission.
	 *
	 * @param int   $form_id Form ID.
	 * @param array $submission_data Submitted form data.
	 * @return int|WP_Error Submission ID or error.
	 */
	public function process_submission( $form_id, $submission_data ) {
		global $wpdb;

		// Get form details
		$forms_table = $wpdb->prefix . 'ns_forms';
		$form        = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$forms_table} WHERE id = %d", $form_id ),
			ARRAY_A
		);

		if ( ! $form ) {
			return new WP_Error( 'form_not_found', 'Form not found.' );
		}

		// Check if form is active
		if ( $form['status'] !== 'active' ) {
			return new WP_Error( 'form_closed', 'This form is not accepting submissions.' );
		}

		// Check submission limit
		if ( $form['submission_limit'] && $form['submission_count'] >= $form['submission_limit'] ) {
			return new WP_Error( 'limit_reached', 'This form has reached its submission limit.' );
		}

		// Create submission record
		$submissions_table = $wpdb->prefix . 'ns_form_submissions';
		$wpdb->insert(
			$submissions_table,
			array(
				'form_id'         => $form_id,
				'organization_id' => $form['organization_id'],
				'contact_id'      => $submission_data['contact_id'] ?? null,
				'user_id'         => get_current_user_id() ?: null,
				'submission_status' => 'completed',
				'ip_address'      => $this->get_client_ip(),
				'user_agent'      => $_SERVER['HTTP_USER_AGENT'] ?? '',
				'referrer'        => $_SERVER['HTTP_REFERER'] ?? '',
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		$submission_id = $wpdb->insert_id;

		// Save submission data
		$data_table = $wpdb->prefix . 'ns_form_submission_data';
		foreach ( $submission_data['fields'] as $field_name => $field_value ) {
			$wpdb->insert(
				$data_table,
				array(
					'submission_id' => $submission_id,
					'field_name'    => $field_name,
					'field_value'   => is_array( $field_value ) ? wp_json_encode( $field_value ) : $field_value,
				),
				array( '%d', '%s', '%s' )
			);
		}

		// Update submission count
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$forms_table} SET submission_count = submission_count + 1 WHERE id = %d",
				$form_id
			)
		);

		// Send notification emails
		if ( ! empty( $form['notification_emails'] ) ) {
			$this->send_notification_emails( $form, $submission_id, $submission_data );
		}

		do_action( 'ns_form_submitted', $submission_id, $form_id, $submission_data );

		return $submission_id;
	}

	/**
	 * Send notification emails for form submission.
	 */
	private function send_notification_emails( $form, $submission_id, $submission_data ) {
		$emails  = array_map( 'trim', explode( ',', $form['notification_emails'] ) );
		$subject = sprintf( 'New submission for %s', $form['form_name'] );
		$message = sprintf( "A new submission has been received for the form '%s'.\n\nSubmission ID: %d\n\n", $form['form_name'], $submission_id );

		foreach ( $submission_data['fields'] as $field_name => $field_value ) {
			$message .= sprintf( "%s: %s\n", $field_name, $field_value );
		}

		foreach ( $emails as $email ) {
			if ( is_email( $email ) ) {
				wp_mail( $email, $subject, $message );
			}
		}
	}

	/**
	 * Get client IP address.
	 */
	private function get_client_ip() {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' );
		
		foreach ( $ip_keys as $key ) {
			if ( isset( $_SERVER[ $key ] ) && filter_var( $_SERVER[ $key ], FILTER_VALIDATE_IP ) ) {
				return $_SERVER[ $key ];
			}
		}
		
		return '0.0.0.0';
	}

	/**
	 * Sync submissions from external provider.
	 *
	 * @param int $form_id Form ID.
	 * @return int Number of new submissions synced.
	 */
	public function sync_external_submissions( $form_id ) {
		global $wpdb;

		$forms_table = $wpdb->prefix . 'ns_forms';
		$form        = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$forms_table} WHERE id = %d", $form_id ),
			ARRAY_A
		);

		if ( ! $form || $form['provider'] === 'builtin' ) {
			return 0;
		}

		$adapter = $this->get_adapter( $form['provider'], $form['organization_id'] );

		if ( is_wp_error( $adapter ) ) {
			return 0;
		}

		$submissions = $adapter->get_submissions( $form['provider_form_id'] );

		if ( is_wp_error( $submissions ) ) {
			return 0;
		}

		$synced_count = 0;

		foreach ( $submissions as $submission ) {
			// Check if submission already exists
			$submissions_table = $wpdb->prefix . 'ns_form_submissions';
			$exists            = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$submissions_table} WHERE form_id = %d AND provider_submission_id = %s",
					$form_id,
					$submission['provider_submission_id']
				)
			);

			if ( ! $exists ) {
				$submission_data = array(
					'contact_id' => null,
					'fields'     => array(),
				);

				foreach ( $submission['fields'] as $field ) {
					$submission_data['fields'][ $field['field_name'] ] = $field['field_value'];
				}

				// Process submission
				$this->process_submission( $form_id, $submission_data );
				$synced_count++;
			}
		}

		return $synced_count;
	}

	/**
	 * Register REST API webhook endpoints.
	 */
	public function register_webhook_endpoints() {
		register_rest_route(
			'nonprofitsuite/v1',
			'/forms/webhook/(?P<provider>[\w-]+)/(?P<form_id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true', // Webhooks don't use WordPress auth
			)
		);
	}

	/**
	 * Handle incoming webhook from form provider.
	 */
	public function handle_webhook( $request ) {
		$provider = $request->get_param( 'provider' );
		$form_id  = $request->get_param( 'form_id' );

		global $wpdb;
		$forms_table = $wpdb->prefix . 'ns_forms';
		$form        = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$forms_table} WHERE id = %d", $form_id ),
			ARRAY_A
		);

		if ( ! $form ) {
			return new WP_Error( 'form_not_found', 'Form not found.', array( 'status' => 404 ) );
		}

		$adapter = $this->get_adapter( $provider, $form['organization_id'] );

		if ( is_wp_error( $adapter ) ) {
			return $adapter;
		}

		// Validate webhook signature
		$signature = $request->get_header( 'X-Typeform-Signature' ) ?: $request->get_header( 'X-JotForm-Signature' );
		$payload   = $request->get_body();

		if ( ! $adapter->validate_webhook_signature( $payload, $signature ) ) {
			return new WP_Error( 'invalid_signature', 'Invalid webhook signature.', array( 'status' => 401 ) );
		}

		// Process webhook
		$webhook_data = $request->get_json_params();
		$submission   = $adapter->process_webhook( $webhook_data );

		if ( is_wp_error( $submission ) ) {
			return $submission;
		}

		// Save submission
		$submission_data = array(
			'contact_id' => null,
			'fields'     => array(),
		);

		foreach ( $submission['fields'] as $field ) {
			$submission_data['fields'][ $field['field_name'] ] = $field['field_value'];
		}

		$submission_id = $this->process_submission( $form_id, $submission_data );

		return array(
			'success'       => true,
			'submission_id' => $submission_id,
		);
	}

	/**
	 * Render form shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Form HTML.
	 */
	public function render_form_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts
		);

		global $wpdb;
		$forms_table = $wpdb->prefix . 'ns_forms';
		$form        = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$forms_table} WHERE id = %d", $atts['id'] ),
			ARRAY_A
		);

		if ( ! $form ) {
			return '<p>Form not found.</p>';
		}

		// For external forms, use embed code
		if ( $form['provider'] !== 'builtin' && ! empty( $form['embed_code'] ) ) {
			return $form['embed_code'];
		}

		// For builtin forms, render custom form (simplified for now)
		ob_start();
		?>
		<div class="ns-form" data-form-id="<?php echo esc_attr( $form['id'] ); ?>">
			<h3><?php echo esc_html( $form['form_name'] ); ?></h3>
			<?php if ( ! empty( $form['description'] ) ) : ?>
				<p><?php echo esc_html( $form['description'] ); ?></p>
			<?php endif; ?>
			<form class="ns-form-builtin" method="post" action="">
				<!-- Form fields would be rendered here -->
				<p>Form rendering requires additional frontend implementation.</p>
				<button type="submit">Submit</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}
}

// Initialize the forms manager
NS_Forms_Manager::get_instance();
