<?php
/**
 * Airwallex REST API client for Payment Intents.
 *
 * @package BST_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BST_Airwallex_API {

	/**
	 * @var array Resolved settings from normalize_plugin_settings().
	 */
	private $settings;

	/**
	 * @param array $settings Raw GF plugin settings or already normalized.
	 */
	public function __construct( array $settings ) {
		$this->settings = isset( $settings['api_base_url'] ) ? $settings : self::normalize_plugin_settings( $settings );
	}

	/**
	 * @param array $plugin_settings Raw add-on settings from Gravity Forms.
	 * @return array{
	 *   environment: string,
	 *   client_id: string,
	 *   api_key: string,
	 *   webhook_secret: string,
	 *   api_base_url: string,
	 *   files_base_url: string
	 * }
	 */
	public static function normalize_plugin_settings( array $plugin_settings ) {
		$env = isset( $plugin_settings['environment'] ) ? (string) $plugin_settings['environment'] : '';
		if ( '' === $env ) {
			$env = ! empty( $plugin_settings['sandbox'] ) ? 'sandbox' : 'production';
		}

		$prefix = ( 'sandbox' === $env ) ? 'sandbox_' : 'live_';
		$client_id = isset( $plugin_settings[ $prefix . 'client_id' ] ) ? trim( (string) $plugin_settings[ $prefix . 'client_id' ] ) : '';
		$api_key   = isset( $plugin_settings[ $prefix . 'api_key' ] ) ? trim( (string) $plugin_settings[ $prefix . 'api_key' ] ) : '';
		$webhook   = isset( $plugin_settings[ $prefix . 'webhook_secret' ] ) ? trim( (string) $plugin_settings[ $prefix . 'webhook_secret' ] ) : '';

		if ( '' === $client_id && '' === $api_key && ! empty( $plugin_settings['client_id'] ) ) {
			$client_id = trim( (string) $plugin_settings['client_id'] );
			$api_key   = isset( $plugin_settings['api_key'] ) ? trim( (string) $plugin_settings['api_key'] ) : '';
			$env       = ! empty( $plugin_settings['sandbox'] ) ? 'sandbox' : 'production';
			$prefix    = ( 'sandbox' === $env ) ? 'sandbox_' : 'live_';
			$webhook   = isset( $plugin_settings[ $prefix . 'webhook_secret' ] ) ? trim( (string) $plugin_settings[ $prefix . 'webhook_secret' ] ) : '';
		}

		$is_sandbox = ( 'sandbox' === $env );

		return array(
			'environment'    => $env,
			'client_id'      => $client_id,
			'api_key'        => $api_key,
			'webhook_secret' => $webhook,
			'api_base_url'   => $is_sandbox ? 'https://api-demo.airwallex.com' : 'https://api.airwallex.com',
			'files_base_url' => $is_sandbox ? 'https://files-demo.airwallex.com' : 'https://files.airwallex.com',
		);
	}

	public function is_configured() {
		return ! empty( $this->settings['client_id'] ) && ! empty( $this->settings['api_key'] );
	}

	/**
	 * @return array
	 */
	public function get_config() {
		return $this->settings;
	}

	/**
	 * Convert major units (e.g. 12.34) to minor units per Airwallex rules.
	 *
	 * @param float  $amount   Major units.
	 * @param string $currency ISO 4217.
	 * @return int
	 */
	public static function amount_to_minor_units( $amount, $currency ) {
		$currency = strtoupper( (string) $currency );
		$decimals = self::currency_decimals( $currency );
		$factor   = pow( 10, $decimals );
		return (int) round( floatval( $amount ) * $factor );
	}

	/**
	 * @param string $currency ISO 4217.
	 * @return int 0 for zero-decimal currencies, 2 for most.
	 */
	public static function currency_decimals( $currency ) {
		static $zero = array(
			'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG',
			'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
		);
		return in_array( strtoupper( $currency ), $zero, true ) ? 0 : 2;
	}

	/**
	 * Obtain or refresh bearer token (cached ~25 min).
	 *
	 * @return string|WP_Error
	 */
	public function get_access_token() {
		$cache_key = 'bst_awx_at_' . md5( $this->settings['client_id'] . '|' . $this->settings['api_key'] . '|' . $this->settings['api_base_url'] );
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) && $cached !== '' ) {
			return $cached;
		}

		$url = $this->settings['api_base_url'] . '/api/v1/authentication/login';
		$res = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
					'x-client-id'  => $this->settings['client_id'],
					'x-api-key'    => $this->settings['api_key'],
				),
				'body'    => wp_json_encode( new stdClass() ),
			)
		);

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$code = wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( $code < 200 || $code >= 300 || empty( $body['token'] ) ) {
			$msg = isset( $body['message'] ) ? $body['message'] : __( 'Airwallex login failed.', 'bst' );
			return new WP_Error( 'bst_airwallex_login', $msg, array( 'status' => $code, 'body' => $body ) );
		}

		$ttl = 25 * MINUTE_IN_SECONDS;
		if ( ! empty( $body['expires_at'] ) ) {
			$exp = strtotime( $body['expires_at'] );
			if ( $exp ) {
				$ttl = max( 60, $exp - time() - 120 );
			}
		}
		set_transient( $cache_key, $body['token'], $ttl );

		return $body['token'];
	}

	/**
	 * @param string $method GET|POST.
	 * @param string $path   Absolute path starting with /api/.
	 * @param array  $body   Optional JSON body for POST.
	 * @return array|WP_Error Decoded JSON or error.
	 */
	private function api_request( $method, $path, array $body = null ) {
		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$url = $this->settings['api_base_url'] . $path;
		$args = array(
			'timeout' => 30,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $token,
			),
		);

		if ( 'POST' === $method && null !== $body ) {
			$args['body'] = wp_json_encode( $body );
			$res          = wp_remote_post( $url, $args );
		} elseif ( 'GET' === $method ) {
			$res = wp_remote_get( $url, $args );
		} else {
			return new WP_Error( 'bst_airwallex_bad_method', 'Unsupported HTTP method' );
		}

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$code = wp_remote_retrieve_response_code( $res );
		$raw  = wp_remote_retrieve_body( $res );
		$data = json_decode( $raw, true );
		if ( $code < 200 || $code >= 300 ) {
			$msg = is_array( $data ) && ! empty( $data['message'] ) ? $data['message'] : __( 'Airwallex API error', 'bst' );
			return new WP_Error( 'bst_airwallex_api', $msg, array( 'status' => $code, 'body' => $data ) );
		}

		return is_array( $data ) ? $data : array();
	}

	/**
	 * @param int    $amount_minor Amount in minor units.
	 * @param string $currency     ISO 4217.
	 * @param string $merchant_order_id Unique per checkout attempt.
	 * @param string $request_id    UUID.
	 * @param string $return_url    Optional redirect URL for some payment methods.
	 * @return array|WP_Error
	 */
	public function create_payment_intent( $amount_minor, $currency, $merchant_order_id, $request_id, $return_url = '' ) {
		$payload = array(
			'amount'            => (int) $amount_minor,
			'currency'          => strtoupper( (string) $currency ),
			'merchant_order_id' => (string) $merchant_order_id,
			'request_id'        => (string) $request_id,
		);
		if ( $return_url !== '' ) {
			$payload['return_url'] = $return_url;
		}

		return $this->api_request( 'POST', '/api/v1/pa/payment_intents/create', $payload );
	}

	/**
	 * @param string $intent_id PaymentIntent id (e.g. int_xxx).
	 * @return array|WP_Error
	 */
	public function get_payment_intent( $intent_id ) {
		$intent_id = rawurlencode( (string) $intent_id );
		return $this->api_request( 'GET', '/api/v1/pa/payment_intents/' . $intent_id );
	}
}
