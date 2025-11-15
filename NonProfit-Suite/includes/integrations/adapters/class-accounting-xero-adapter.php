<?php
/**
 * Xero API Adapter
 *
 * Adapter for real-time Xero integration.
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
 * NonprofitSuite_Accounting_Xero_Adapter Class
 *
 * Implements accounting integration using Xero API.
 */
class NonprofitSuite_Accounting_Xero_Adapter implements NonprofitSuite_Accounting_Adapter_Interface {

	/**
	 * Xero API base URL
	 */
	const API_BASE_URL = 'https://api.xero.com/api.xro/2.0/';
	const AUTH_URL = 'https://login.xero.com/identity/connect/authorize';
	const TOKEN_URL = 'https://identity.xero.com/connect/token';

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
	 * Tenant ID
	 *
	 * @var string
	 */
	private $tenant_id;

	/**
	 * Constructor
	 */
	public function __construct() {
		$manager = NonprofitSuite_Integration_Manager::get_instance();
		$this->settings = $manager->get_provider_settings( 'accounting', 'xero' );
		$this->access_token = $this->settings['access_token'] ?? '';
		$this->tenant_id = $this->settings['tenant_id'] ?? '';
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
			'redirect_uri'  => $redirect_uri,
			'response_type' => 'code',
			'scope'         => 'offline_access accounting.transactions accounting.settings',
			'state'         => wp_create_nonce( 'xero_auth' ),
		);

		return self::AUTH_URL . '?' . http_build_query( $params );
	}

	/**
	 * Handle OAuth callback
	 *
	 * @param string $code Authorization code
	 * @return bool|WP_Error
	 */
	public function handle_oauth_callback( $code ) {
		$response = wp_remote_post( self::TOKEN_URL, array(
			'headers' => array(
				'Content-Type'  => 'application/x-www-form-urlencoded',
				'Authorization' => 'Basic ' . base64_encode(
					( $this->settings['client_id'] ?? '' ) . ':' . ( $this->settings['client_secret'] ?? '' )
				),
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

			// Get tenant ID
			$tenant_result = $this->get_tenant_id( $body['access_token'] );
			if ( ! is_wp_error( $tenant_result ) ) {
				$this->settings['tenant_id'] = $tenant_result;
				$this->tenant_id = $tenant_result;
			}

			// Update settings
			$manager = NonprofitSuite_Integration_Manager::get_instance();
			$manager->update_provider_settings( 'accounting', 'xero', $this->settings );

			$this->access_token = $body['access_token'];

			return true;
		}

		return new WP_Error( 'oauth_failed', __( 'Failed to obtain access token', 'nonprofitsuite' ) );
	}

	/**
	 * Get tenant ID
	 *
	 * @param string $access_token Access token
	 * @return string|WP_Error
	 */
	private function get_tenant_id( $access_token ) {
		$response = wp_remote_get( 'https://api.xero.com/connections', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body[0]['tenantId'] ) ) {
			return $body[0]['tenantId'];
		}

		return new WP_Error( 'no_tenant', __( 'No Xero organization found', 'nonprofitsuite' ) );
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
				'Content-Type'  => 'application/x-www-form-urlencoded',
				'Authorization' => 'Basic ' . base64_encode(
					( $this->settings['client_id'] ?? '' ) . ':' . ( $this->settings['client_secret'] ?? '' )
				),
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
			$manager->update_provider_settings( 'accounting', 'xero', $this->settings );

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
			'contact'     => '',
			'reference'   => '',
		) );

		// Create bank transaction
		$payload = array(
			'BankTransactions' => array(
				array(
					'Type'        => $transaction['type'] === 'income' ? 'RECEIVE' : 'SPEND',
					'Contact'     => array(
						'Name' => $transaction['contact'] ?: 'General',
					),
					'LineItems'   => array(
						array(
							'Description' => $transaction['description'],
							'Quantity'    => 1.0,
							'UnitAmount'  => $transaction['amount'],
							'AccountCode' => $transaction['category'],
						),
					),
					'BankAccount' => array(
						'Code' => $transaction['account'],
					),
					'Date'        => $transaction['date'],
					'Reference'   => $transaction['reference'],
				),
			),
		);

		$response = $this->make_request( 'BankTransactions', $payload, 'PUT' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'transaction_id' => $response['BankTransactions'][0]['BankTransactionID'],
			'status'         => 'synced',
		);
	}

	/**
	 * Get accounts
	 *
	 * @return array|WP_Error
	 */
	public function get_accounts() {
		$response = $this->make_request( 'Accounts', array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$accounts = array();
		if ( ! empty( $response['Accounts'] ) ) {
			foreach ( $response['Accounts'] as $account ) {
				$accounts[] = array(
					'id'   => $account['Code'],
					'name' => $account['Name'],
					'type' => $account['Type'],
				);
			}
		}

		return $accounts;
	}

	/**
	 * Get categories (accounts)
	 *
	 * @return array|WP_Error
	 */
	public function get_categories() {
		$response = $this->make_request( 'Accounts?where=Type=="EXPENSE"', array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$categories = array();
		if ( ! empty( $response['Accounts'] ) ) {
			foreach ( $response['Accounts'] as $account ) {
				$categories[] = array(
					'id'   => $account['Code'],
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

		$where = sprintf(
			'Date >= DateTime(%s) && Date <= DateTime(%s)',
			$args['start_date'],
			$args['end_date']
		);

		if ( $args['type'] === 'income' ) {
			$where .= ' && Type=="RECEIVE"';
		} elseif ( $args['type'] === 'expense' ) {
			$where .= ' && Type=="SPEND"';
		}

		$endpoint = 'BankTransactions?where=' . urlencode( $where );
		$response = $this->make_request( $endpoint, array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$transactions = array();
		if ( ! empty( $response['BankTransactions'] ) ) {
			foreach ( $response['BankTransactions'] as $txn ) {
				$transactions[] = array(
					'id'     => $txn['BankTransactionID'],
					'type'   => $txn['Type'] === 'RECEIVE' ? 'income' : 'expense',
					'amount' => $txn['Total'],
					'date'   => substr( $txn['Date'], 6, 10 ), // Parse date from /Date(timestamp)/
				);
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
		// Xero API doesn't provide bulk export in CSV format
		// Would need to fetch all data via API and format it
		return new WP_Error( 'not_supported', __( 'Bulk export not supported by Xero API', 'nonprofitsuite' ) );
	}

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error
	 */
	public function test_connection() {
		if ( empty( $this->access_token ) || empty( $this->tenant_id ) ) {
			return new WP_Error( 'not_configured', __( 'Xero not connected. Please authenticate.', 'nonprofitsuite' ) );
		}

		// Test with organisation request
		$response = $this->make_request( 'Organisation', array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return isset( $response['Organisations'] );
	}

	/**
	 * Get provider name
	 *
	 * @return string
	 */
	public function get_provider_name() {
		return 'Xero';
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
		$url = self::API_BASE_URL . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization'  => 'Bearer ' . $this->access_token,
				'xero-tenant-id' => $this->tenant_id,
				'Accept'         => 'application/json',
				'Content-Type'   => 'application/json',
			),
			'timeout' => 30,
		);

		if ( in_array( $method, array( 'POST', 'PUT' ) ) && ! empty( $data ) ) {
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
			$error_message = $body['Message'] ?? __( 'Unknown error', 'nonprofitsuite' );
			return new WP_Error( 'api_error', sprintf( __( 'Xero API error: %s', 'nonprofitsuite' ), $error_message ) );
		}

		return $body;
	}
}
