<?php
/**
 * QuickBooks Online API Adapter
 *
 * Adapter for real-time QuickBooks Online integration.
 *
 * @package    NonprofitSuite
 * @subpackage Integrations
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NonprofitSuite_Accounting_QuickBooks_Adapter Class
 *
 * Implements accounting integration using QuickBooks Online API.
 */
class NonprofitSuite_Accounting_QuickBooks_Adapter implements NonprofitSuite_Accounting_Adapter_Interface {

	/**
	 * QuickBooks API base URL
	 */
	const API_BASE_URL = 'https://quickbooks.api.intuit.com/v3/company/';
	const AUTH_URL = 'https://appcenter.intuit.com/connect/oauth2';
	const TOKEN_URL = 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer';

	/**
	 * Provider settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Access token
	 *
	 * @var string
	 */
	private $access_token;

	/**
	 * Realm ID (Company ID)
	 *
	 * @var string
	 */
	private $realm_id;

	/**
	 * Sandbox mode
	 *
	 * @var bool
	 */
	private $sandbox;

	/**
	 * Constructor
	 */
	public function __construct() {
		$manager = NonprofitSuite_Integration_Manager::get_instance();
		$this->settings = $manager->get_provider_settings( 'accounting', 'quickbooks' );
		$this->sandbox = ! empty( $this->settings['sandbox_mode'] );
		$this->access_token = $this->settings['access_token'] ?? '';
		$this->realm_id = $this->settings['realm_id'] ?? '';
	}

	/**
	 * Get OAuth authorization URL
	 *
	 * @param string $redirect_uri Redirect URI after authorization
	 * @return string
	 */
	public function get_auth_url( $redirect_uri = '' ) {
		$params = array(
			'client_id'     => $this->settings['client_id'] ?? '',
			'scope'         => 'com.intuit.quickbooks.accounting',
			'redirect_uri'  => $redirect_uri,
			'response_type' => 'code',
			'state'         => wp_create_nonce( 'quickbooks_auth' ),
		);

		return self::AUTH_URL . '?' . http_build_query( $params );
	}

	/**
	 * Handle OAuth callback
	 *
	 * @param string $code Authorization code
	 * @param string $realm_id Realm ID
	 * @return bool|WP_Error
	 */
	public function handle_oauth_callback( $code, $realm_id ) {
		$response = wp_remote_post( self::TOKEN_URL, array(
			'headers' => array(
				'Accept'        => 'application/json',
				'Authorization' => 'Basic ' . base64_encode(
					( $this->settings['client_id'] ?? '' ) . ':' . ( $this->settings['client_secret'] ?? '' )
				),
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'body'    => array(
				'grant_type'   => 'authorization_code',
				'code'         => $code,
				'redirect_uri' => $this->settings['redirect_uri'] ?? '',
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['access_token'] ) ) {
			// Save tokens
			$this->settings['access_token'] = $body['access_token'];
			$this->settings['refresh_token'] = $body['refresh_token'];
			$this->settings['token_expiry'] = time() + $body['expires_in'];
			$this->settings['realm_id'] = $realm_id;

			// Update settings
			$manager = NonprofitSuite_Integration_Manager::get_instance();
			$manager->update_provider_settings( 'accounting', 'quickbooks', $this->settings );

			$this->access_token = $body['access_token'];
			$this->realm_id = $realm_id;

			return true;
		}

		return new WP_Error( 'oauth_failed', __( 'Failed to obtain access token', 'nonprofitsuite' ) );
	}

	/**
	 * Refresh access token
	 *
	 * @return bool|WP_Error
	 */
	private function refresh_access_token() {
		if ( empty( $this->settings['refresh_token'] ) ) {
			return new WP_Error( 'no_refresh_token', __( 'No refresh token available', 'nonprofitsuite' ) );
		}

		$response = wp_remote_post( self::TOKEN_URL, array(
			'headers' => array(
				'Accept'        => 'application/json',
				'Authorization' => 'Basic ' . base64_encode(
					( $this->settings['client_id'] ?? '' ) . ':' . ( $this->settings['client_secret'] ?? '' )
				),
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'body'    => array(
				'grant_type'    => 'refresh_token',
				'refresh_token' => $this->settings['refresh_token'],
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['access_token'] ) ) {
			$this->settings['access_token'] = $body['access_token'];
			$this->settings['refresh_token'] = $body['refresh_token'];
			$this->settings['token_expiry'] = time() + $body['expires_in'];

			// Update settings
			$manager = NonprofitSuite_Integration_Manager::get_instance();
			$manager->update_provider_settings( 'accounting', 'quickbooks', $this->settings );

			$this->access_token = $body['access_token'];

			return true;
		}

		return new WP_Error( 'refresh_failed', __( 'Failed to refresh access token', 'nonprofitsuite' ) );
	}

	/**
	 * Sync transaction
	 *
	 * @param array $transaction Transaction data
	 * @return array|WP_Error
	 */
	public function sync_transaction( $transaction ) {
		// Check token expiry
		if ( ! empty( $this->settings['token_expiry'] ) && time() > $this->settings['token_expiry'] - 300 ) {
			$this->refresh_access_token();
		}

		$transaction = wp_parse_args( $transaction, array(
			'type'        => 'income', // income or expense
			'amount'      => 0,
			'date'        => date( 'Y-m-d' ),
			'description' => '',
			'category'    => '',
			'account'     => '',
			'memo'        => '',
		) );

		// Create appropriate QuickBooks transaction type
		if ( $transaction['type'] === 'income' ) {
			return $this->create_sales_receipt( $transaction );
		} else {
			return $this->create_expense( $transaction );
		}
	}

	/**
	 * Create sales receipt (income)
	 *
	 * @param array $transaction Transaction data
	 * @return array|WP_Error
	 */
	private function create_sales_receipt( $transaction ) {
		$payload = array(
			'TxnDate'         => $transaction['date'],
			'PrivateNote'     => $transaction['memo'],
			'Line'            => array(
				array(
					'Amount'                => $transaction['amount'],
					'DetailType'            => 'SalesItemLineDetail',
					'SalesItemLineDetail'   => array(
						'ItemRef' => array(
							'value' => 1, // Default item, should be configurable
						),
					),
					'Description'           => $transaction['description'],
				),
			),
			'DepositToAccountRef' => array(
				'value' => $transaction['account'] ?: 1, // Default account
			),
		);

		$response = $this->make_request( 'salesreceipt', $payload, 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'transaction_id' => $response['SalesReceipt']['Id'],
			'sync_id'        => $response['SalesReceipt']['SyncToken'],
			'status'         => 'synced',
		);
	}

	/**
	 * Create expense
	 *
	 * @param array $transaction Transaction data
	 * @return array|WP_Error
	 */
	private function create_expense( $transaction ) {
		$payload = array(
			'TxnDate'     => $transaction['date'],
			'PrivateNote' => $transaction['memo'],
			'Line'        => array(
				array(
					'Amount'      => $transaction['amount'],
					'DetailType'  => 'AccountBasedExpenseLineDetail',
					'AccountBasedExpenseLineDetail' => array(
						'AccountRef' => array(
							'value' => $transaction['category'] ?: 1, // Expense account
						),
					),
					'Description' => $transaction['description'],
				),
			),
			'AccountRef'  => array(
				'value' => $transaction['account'] ?: 1, // Payment account
			),
		);

		$response = $this->make_request( 'purchase', $payload, 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'transaction_id' => $response['Purchase']['Id'],
			'sync_id'        => $response['Purchase']['SyncToken'],
			'status'         => 'synced',
		);
	}

	/**
	 * Get accounts
	 *
	 * @return array|WP_Error
	 */
	public function get_accounts() {
		$response = $this->make_request( 'query?query=SELECT * FROM Account', array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$accounts = array();
		if ( ! empty( $response['QueryResponse']['Account'] ) ) {
			foreach ( $response['QueryResponse']['Account'] as $account ) {
				$accounts[] = array(
					'id'   => $account['Id'],
					'name' => $account['Name'],
					'type' => $account['AccountType'],
				);
			}
		}

		return $accounts;
	}

	/**
	 * Get categories (accounts used as categories)
	 *
	 * @return array|WP_Error
	 */
	public function get_categories() {
		$response = $this->make_request( 'query?query=SELECT * FROM Account WHERE AccountType = \'Expense\'', array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$categories = array();
		if ( ! empty( $response['QueryResponse']['Account'] ) ) {
			foreach ( $response['QueryResponse']['Account'] as $account ) {
				$categories[] = array(
					'id'   => $account['Id'],
					'name' => $account['Name'],
				);
			}
		}

		return $categories;
	}

	/**
	 * Get transactions
	 *
	 * @param array $args Query arguments
	 * @return array|WP_Error
	 */
	public function get_transactions( $args = array() ) {
		$args = wp_parse_args( $args, array(
			'start_date' => date( 'Y-m-01' ),
			'end_date'   => date( 'Y-m-d' ),
			'type'       => 'all', // all, income, expense
		) );

		// Get both sales receipts and purchases
		$transactions = array();

		if ( in_array( $args['type'], array( 'all', 'income' ) ) ) {
			$query = sprintf(
				"SELECT * FROM SalesReceipt WHERE TxnDate >= '%s' AND TxnDate <= '%s'",
				$args['start_date'],
				$args['end_date']
			);
			$response = $this->make_request( 'query?query=' . urlencode( $query ), array(), 'GET' );

			if ( ! is_wp_error( $response ) && ! empty( $response['QueryResponse']['SalesReceipt'] ) ) {
				foreach ( $response['QueryResponse']['SalesReceipt'] as $sr ) {
					$transactions[] = array(
						'id'     => $sr['Id'],
						'type'   => 'income',
						'amount' => $sr['TotalAmt'],
						'date'   => $sr['TxnDate'],
					);
				}
			}
		}

		if ( in_array( $args['type'], array( 'all', 'expense' ) ) ) {
			$query = sprintf(
				"SELECT * FROM Purchase WHERE TxnDate >= '%s' AND TxnDate <= '%s'",
				$args['start_date'],
				$args['end_date']
			);
			$response = $this->make_request( 'query?query=' . urlencode( $query ), array(), 'GET' );

			if ( ! is_wp_error( $response ) && ! empty( $response['QueryResponse']['Purchase'] ) ) {
				foreach ( $response['QueryResponse']['Purchase'] as $purchase ) {
					$transactions[] = array(
						'id'     => $purchase['Id'],
						'type'   => 'expense',
						'amount' => $purchase['TotalAmt'],
						'date'   => $purchase['TxnDate'],
					);
				}
			}
		}

		return $transactions;
	}

	/**
	 * Export data
	 *
	 * @param array $args Export arguments
	 * @return string|WP_Error File path or error
	 */
	public function export_data( $args = array() ) {
		// QuickBooks API doesn't provide bulk export
		// This would need to fetch all data via API and format it
		return new WP_Error( 'not_supported', __( 'Bulk export not supported by QuickBooks Online API', 'nonprofitsuite' ) );
	}

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error
	 */
	public function test_connection() {
		if ( empty( $this->access_token ) || empty( $this->realm_id ) ) {
			return new WP_Error( 'not_configured', __( 'QuickBooks Online not connected. Please authenticate.', 'nonprofitsuite' ) );
		}

		// Test with company info request
		$response = $this->make_request( 'companyinfo/' . $this->realm_id, array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return isset( $response['CompanyInfo'] );
	}

	/**
	 * Get provider name
	 *
	 * @return string
	 */
	public function get_provider_name() {
		return 'QuickBooks Online';
	}

	/**
	 * Make API request
	 *
	 * @param string $endpoint Endpoint
	 * @param array  $data     Request data
	 * @param string $method   HTTP method
	 * @return array|WP_Error
	 */
	private function make_request( $endpoint, $data = array(), $method = 'GET' ) {
		$url = self::API_BASE_URL . $this->realm_id . '/' . $endpoint;

		if ( $this->sandbox ) {
			$url = str_replace( 'quickbooks.api.intuit.com', 'sandbox-quickbooks.api.intuit.com', $url );
		}

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->access_token,
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		);

		if ( $method !== 'GET' && ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code === 401 ) {
			// Token expired, try to refresh
			$refresh_result = $this->refresh_access_token();
			if ( ! is_wp_error( $refresh_result ) ) {
				// Retry request with new token
				return $this->make_request( $endpoint, $data, $method );
			}
			return $refresh_result;
		}

		if ( $code < 200 || $code >= 300 ) {
			$error_message = $body['Fault']['Error'][0]['Message'] ?? __( 'Unknown error', 'nonprofitsuite' );
			return new WP_Error( 'api_error', sprintf( __( 'QuickBooks API error: %s', 'nonprofitsuite' ), $error_message ) );
		}

		return $body;
	}
}
