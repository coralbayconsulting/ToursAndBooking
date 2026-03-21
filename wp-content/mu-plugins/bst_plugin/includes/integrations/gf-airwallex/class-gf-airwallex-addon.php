<?php
/**
 * Gravity Forms — Airwallex payment add-on.
 *
 * @package BST_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GFAirwallexAddOn
 */
class GFAirwallexAddOn extends GFPaymentAddOn {

	/** @var GFAirwallexAddOn|null */
	private static $instance = null;

	/** @var bool */
	private static $field_type_registered = false;

	protected $_version                  = '0.2.0';
	protected $_min_gravityforms_version = '2.8';
	protected $_slug                     = 'bst-gf-airwallex';
	protected $_path                     = 'bst_plugin/includes/integrations/gf-airwallex/class-gf-airwallex-addon.php';
	protected $_full_path                = __FILE__;
	protected $_title                    = 'Gravity Forms Airwallex Add-On (BST)';
	protected $_short_title              = 'Airwallex';
	protected $_supports_callbacks       = false;
	protected $_requires_credit_card     = false;

	/**
	 * @return GFAirwallexAddOn
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Airwallex.js env: demo | prod
	 *
	 * @return string
	 */
	public function get_airwallex_js_env() {
		$s = $this->get_plugin_settings();
		$s = is_array( $s ) ? $s : array();
		$env = isset( $s['environment'] ) ? (string) $s['environment'] : '';
		if ( '' === $env ) {
			$env = ! empty( $s['sandbox'] ) ? 'sandbox' : 'production';
		}
		return ( 'sandbox' === $env ) ? 'demo' : 'prod';
	}

	/**
	 * Register field type, AJAX, scripts.
	 */
	public function init() {
		parent::init();

		if ( ! self::$field_type_registered && class_exists( 'GF_Fields' ) && class_exists( 'GF_Field_Airwallex' ) ) {
			GF_Fields::register( new GF_Field_Airwallex() );
			self::$field_type_registered = true;
		}

		add_action( 'wp_ajax_bst_gf_airwallex_create_intent', array( $this, 'ajax_create_payment_intent' ) );
		add_action( 'wp_ajax_nopriv_bst_gf_airwallex_create_intent', array( $this, 'ajax_create_payment_intent' ) );
		add_action( 'gform_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ), 10, 2 );
	}

	/**
	 * Global settings.
	 */
	public function plugin_settings_fields() {
		return array(
			array(
				'title'       => esc_html__( 'Environment', 'bst' ),
				'description' => esc_html__( 'Choose which credential set the site uses. Store both test and live keys here; switch mode without re-entering keys.', 'bst' ),
				'fields'      => array(
					array(
						'name'          => 'environment',
						'label'         => esc_html__( 'Active mode', 'bst' ),
						'type'          => 'select',
						'default_value' => 'sandbox',
						'choices'       => array(
							array(
								'label' => esc_html__( 'Sandbox (test) - api-demo.airwallex.com', 'bst' ),
								'value' => 'sandbox',
							),
							array(
								'label' => esc_html__( 'Live (production) - api.airwallex.com', 'bst' ),
								'value' => 'production',
							),
						),
					),
				),
			),
			array(
				'title'       => esc_html__( 'Sandbox (test) credentials', 'bst' ),
				'description' => esc_html__( 'From Airwallex web app → Settings → Developer → API keys (sandbox account).', 'bst' ),
				'fields'      => array(
					array(
						'name'    => 'sandbox_client_id',
						'label'   => esc_html__( 'Sandbox Client ID', 'bst' ),
						'type'    => 'text',
						'class'   => 'large',
						'tooltip' => esc_html__( 'Sent as x-client-id for authentication.', 'bst' ),
					),
					array(
						'name'    => 'sandbox_api_key',
						'label'   => esc_html__( 'Sandbox API key', 'bst' ),
						'type'    => 'text',
						'class'   => 'large',
						'tooltip' => esc_html__( 'Sent as x-api-key. Prefer env / wp-config in production sites.', 'bst' ),
					),
					array(
						'name'    => 'sandbox_webhook_secret',
						'label'   => esc_html__( 'Sandbox webhook signing secret (optional)', 'bst' ),
						'type'    => 'text',
						'class'   => 'large',
						'tooltip' => esc_html__( 'Used to verify webhook payloads when callbacks are enabled.', 'bst' ),
					),
				),
			),
			array(
				'title'       => esc_html__( 'Live (production) credentials', 'bst' ),
				'description' => esc_html__( 'Production keys — use only on the live site.', 'bst' ),
				'fields'      => array(
					array(
						'name'  => 'live_client_id',
						'label' => esc_html__( 'Live Client ID', 'bst' ),
						'type'  => 'text',
						'class' => 'large',
					),
					array(
						'name'  => 'live_api_key',
						'label' => esc_html__( 'Live API key', 'bst' ),
						'type'  => 'text',
						'class' => 'large',
					),
					array(
						'name'  => 'live_webhook_secret',
						'label' => esc_html__( 'Live webhook signing secret (optional)', 'bst' ),
						'type'  => 'text',
						'class' => 'large',
					),
				),
			),
			array(
				'title'       => esc_html__( 'Legacy (deprecated)', 'bst' ),
				'description' => esc_html__( 'Older installs used a single Client ID / API key and a sandbox checkbox.', 'bst' ),
				'fields'      => array(
					array(
						'name'  => 'client_id',
						'label' => esc_html__( 'Client ID (legacy)', 'bst' ),
						'type'  => 'text',
						'class' => 'large',
					),
					array(
						'name'  => 'api_key',
						'label' => esc_html__( 'API key (legacy)', 'bst' ),
						'type'  => 'text',
						'class' => 'large',
					),
					array(
						'name'    => 'sandbox',
						'label'   => esc_html__( 'Legacy sandbox flag', 'bst' ),
						'type'    => 'checkbox',
						'choices' => array(
							array(
								'label' => esc_html__( 'Use sandbox endpoints for legacy keys', 'bst' ),
								'name'  => 'sandbox',
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Enqueue Airwallex Drop-in (ES module).
	 *
	 * @param array $form     Form.
	 * @param bool  $is_ajax Whether AJAX is enabled for this form.
	 */
	public function enqueue_frontend_scripts( $form, $is_ajax ) {
		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return;
		}
		$has = false;
		foreach ( $form['fields'] as $field ) {
			if ( isset( $field->type ) && 'airwallex' === $field->type ) {
				$has = true;
				break;
			}
		}
		if ( ! $has ) {
			return;
		}

		$base = defined( 'BST_PLUGIN_URL' ) ? BST_PLUGIN_URL : '';
		if ( '' === $base && defined( 'BST_PLUGIN_DIR' ) ) {
			$base = plugin_dir_url( BST_PLUGIN_DIR . 'bst-plugin.php' );
		}
		$src = trailingslashit( $base ) . 'includes/integrations/gf-airwallex/js/gf-airwallex.js';

		wp_enqueue_script(
			'bst-gf-airwallex',
			$src,
			array( 'jquery' ),
			defined( 'BST_PLUGIN_VERSION' ) ? BST_PLUGIN_VERSION : '0.2.0',
			true
		);
		wp_script_add_data( 'bst-gf-airwallex', 'type', 'module' );
	}

	/**
	 * AJAX: create PaymentIntent (server-trusted amount from posted GF fields).
	 */
	public function ajax_create_payment_intent() {
		check_ajax_referer( 'bst_gf_airwallex', 'nonce' );

		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$form    = $form_id ? GFAPI::get_form( $form_id ) : false;
		if ( ! $form || is_wp_error( $form ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid form.', 'bst' ) ), 400 );
		}

		$posted_raw = isset( $_POST['posted'] ) ? wp_unslash( $_POST['posted'] ) : '';
		$posted     = json_decode( $posted_raw, true );
		if ( ! is_array( $posted ) ) {
			$posted = array();
		}

		// Merge into $_POST for GF pricing helpers.
		foreach ( $posted as $key => $val ) {
			$_POST[ $key ] = $val; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		$_REQUEST = array_merge( $_REQUEST, $posted ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$total = self::get_payment_total_from_request( $form );
		if ( null === $total || $total <= 0 ) {
			wp_send_json_error(
				array( 'message' => __( 'Enter a valid order total before paying (add products or a total).', 'bst' ) ),
				400
			);
		}

		$currency = strtoupper( (string) rgar( $form, 'currency', 'USD' ) );
		$minor    = BST_Airwallex_API::amount_to_minor_units( $total, $currency );

		$settings = $this->get_plugin_settings();
		$api      = new BST_Airwallex_API( is_array( $settings ) ? $settings : array() );
		if ( ! $api->is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'Airwallex is not configured.', 'bst' ) ), 400 );
		}

		$order_ref = 'gf' . $form_id . '-' . wp_generate_uuid4();
		$req_id    = wp_generate_uuid4();
		$return    = isset( $_POST['return_url'] ) ? esc_url_raw( wp_unslash( $_POST['return_url'] ) ) : home_url( '/' );

		$intent = $api->create_payment_intent( $minor, $currency, $order_ref, $req_id, $return );
		if ( is_wp_error( $intent ) ) {
			wp_send_json_error(
				array( 'message' => $intent->get_error_message() ),
				400
			);
		}

		if ( empty( $intent['id'] ) || empty( $intent['client_secret'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Unexpected Airwallex response.', 'bst' ) ), 500 );
		}

		wp_send_json_success(
			array(
				'intent_id'     => $intent['id'],
				'client_secret' => $intent['client_secret'],
				'currency'      => $currency,
				'amount_minor'  => $minor,
			)
		);
	}

	/**
	 * Resolve payment total from current request (after AJAX merge).
	 *
	 * @param array $form Form.
	 * @return float|null
	 */
	public static function get_payment_total_from_request( $form ) {
		foreach ( $form['fields'] as $field ) {
			if ( isset( $field->type ) && 'total' === $field->type ) {
				$key = 'input_' . (int) $field->id;
				if ( isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$raw = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
					if ( class_exists( 'GFCommon' ) && method_exists( 'GFCommon', 'to_number' ) ) {
						return floatval( GFCommon::to_number( $raw, rgar( $form, 'currency' ) ) );
					}
					return floatval( $raw );
				}
			}
		}

		if ( class_exists( 'GFCommon' ) && is_callable( array( 'GFCommon', 'get_product_submission' ) ) ) {
			$submission = call_user_func( array( 'GFCommon', 'get_product_submission' ), $form );
			if ( is_array( $submission ) && isset( $submission['total'] ) ) {
				return floatval( $submission['total'] );
			}
		}

		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function authorize( $feed, $submission_data, $form, $entry ) {
		$settings = $this->get_plugin_settings();
		$api      = new BST_Airwallex_API( is_array( $settings ) ? $settings : array() );

		if ( ! $api->is_configured() ) {
			return array(
				'is_authorized' => false,
				'error_message' => esc_html__( 'Airwallex is not configured. Go to Forms → Settings → Airwallex and add API credentials.', 'bst' ),
			);
		}

		$intent_id = '';
		foreach ( $form['fields'] as $field ) {
			if ( isset( $field->type ) && 'airwallex' === $field->type ) {
				$intent_id = sanitize_text_field( rgpost( 'input_' . (int) $field->id ) );
				break;
			}
		}

		if ( '' === $intent_id ) {
			return array(
				'is_authorized' => false,
				'error_message' => esc_html__( 'Payment was not completed.', 'bst' ),
			);
		}

		$intent = $api->get_payment_intent( $intent_id );
		if ( is_wp_error( $intent ) ) {
			return array(
				'is_authorized' => false,
				'error_message' => esc_html( $intent->get_error_message() ),
			);
		}

		$status = isset( $intent['status'] ) ? strtoupper( (string) $intent['status'] ) : '';
		if ( 'SUCCEEDED' !== $status ) {
			return array(
				'is_authorized' => false,
				'error_message' => sprintf(
					/* translators: %s: Airwallex payment status */
					esc_html__( 'Payment not successful (status: %s).', 'bst' ),
					esc_html( $status )
				),
			);
		}

		$currency = strtoupper( (string) rgar( $submission_data, 'currency', rgar( $form, 'currency', 'USD' ) ) );
		$expected = isset( $submission_data['payment_amount'] ) ? floatval( $submission_data['payment_amount'] ) : 0;
		$exp_min  = BST_Airwallex_API::amount_to_minor_units( $expected, $currency );
		$got_min  = isset( $intent['amount'] ) ? (int) $intent['amount'] : -1;

		if ( $exp_min !== $got_min ) {
			return array(
				'is_authorized' => false,
				'error_message' => esc_html__( 'Payment amount does not match the form total.', 'bst' ),
			);
		}

		return array(
			'is_authorized'  => true,
			'transaction_id' => sanitize_text_field( $intent_id ),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function subscribe( $feed, $submission_data, $form, $entry ) {
		return array(
			'is_success'    => false,
			'error_message' => esc_html__( 'Subscriptions are not supported. Use one-time payments only.', 'bst' ),
		);
	}
}
