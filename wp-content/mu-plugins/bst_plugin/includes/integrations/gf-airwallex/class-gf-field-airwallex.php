<?php
/**
 * Gravity Forms — Airwallex Drop-in container + hidden PaymentIntent id.
 *
 * @package BST_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GF_Field_Airwallex
 */
class GF_Field_Airwallex extends GF_Field {

	/**
	 * @var string
	 */
	public $type = 'airwallex';

	/**
	 * @return string
	 */
	public function get_form_editor_field_title() {
		return esc_html__( 'Airwallex', 'bst' );
	}

	/**
	 * @return string
	 */
	public function get_form_editor_field_group() {
		return 'pricing_fields';
	}

	/**
	 * @return array
	 */
	public function get_form_editor_field_settings() {
		return array(
			'conditional_logic_field_setting',
			'prepopulate_field_setting',
			'error_message_setting',
			'label_setting',
			'label_placement_setting',
			'admin_label_setting',
			'rules_setting',
			'visibility_setting',
			'description_setting',
			'css_class_setting',
		);
	}

	/**
	 * @param array      $form The form object.
	 * @param string|array $value Field value.
	 * @param null|array $entry Entry.
	 * @return string
	 */
	public function get_field_input( $form, $value = '', $entry = null ) {
		if ( $this->is_form_editor() ) {
			return $this->get_form_editor_preview_markup();
		}

		$form_id   = (int) $form['id'];
		$field_id  = (int) $this->id;
		$input_id  = 'input_' . $field_id;
		$mount_id  = 'bst_airwallex_dropin_' . $form_id . '_' . $field_id;
		$total_key = '';

		foreach ( $form['fields'] as $f ) {
			if ( isset( $f->type ) && 'total' === $f->type ) {
				$total_key = 'input_' . (int) $f->id;
				break;
			}
		}

		/**
		 * Extra options passed to Airwallex `createElement('dropIn', …)`.
		 * See https://www.airwallex.com/docs/js/payments/dropin — `requiredBillingContactFields`, `shopper_*`, etc.
		 *
		 * Default: card-only Drop-in + require cardholder name and billing postal/address (AVS / risk).
		 * Override or clear `requiredBillingContactFields` via filter if your checkout collects billing elsewhere.
		 */
		$drop_in_defaults = array(
			'methods'                        => array( 'card' ),
			'requiredBillingContactFields'   => array( 'name', 'postalAddress' ),
		);

		$config = array(
			'formId'      => $form_id,
			'fieldId'     => $field_id,
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'bst_gf_airwallex' ),
			'currency'    => strtoupper( (string) rgar( $form, 'currency', 'USD' ) ),
			'totalInput'  => $total_key,
			'returnUrl'   => esc_url_raw( $this->get_return_url_for_form( $form_id ) ),
			'dropIn'      => apply_filters( 'bst_gf_airwallex_dropin_options', $drop_in_defaults, $form, $this ),
		);

		$add_on = class_exists( 'GFAirwallexAddOn' ) ? GFAirwallexAddOn::get_instance() : null;
		if ( $add_on && method_exists( $add_on, 'get_airwallex_js_env' ) ) {
			$config['env'] = $add_on->get_airwallex_js_env();
		} else {
			$config['env'] = 'demo';
		}

		$config_json = wp_json_encode( $config );

		return sprintf(
			'<div class="ginput_container ginput_container_airwallex bst-airwallex-wrap" data-bst-airwallex-config="%s"><p class="bst-airwallex-loading">%s</p><div id="%s" class="bst-airwallex-dropin"></div><input type="hidden" name="%s" id="input_%d_%d" class="bst-airwallex-intent-id" value="%s" autocomplete="off" /></div>',
			esc_attr( $config_json ),
			esc_html__( 'Loading payment form…', 'bst' ),
			esc_attr( $mount_id ),
			esc_attr( $input_id ),
			$form_id,
			$field_id,
			esc_attr( (string) $value )
		);
	}

	/**
	 * Visual placeholder in the form editor (not live Airwallex — card data is never entered in wp-admin).
	 *
	 * @return string
	 */
	private function get_form_editor_preview_markup() {
		$style = 'max-width:520px;padding:16px;border:1px solid #c3c4c7;border-radius:6px;background:#f6f7f7;box-sizing:border-box;';
		$label = 'display:block;font-size:12px;font-weight:600;color:#1d2327;margin:0 0 6px 0;';
		$input = 'width:100%;padding:10px 12px;border:1px solid #8c8f94;border-radius:4px;background:#fff;font-size:14px;box-sizing:border-box;';
		$row      = 'display:flex;gap:12px;margin-bottom:14px;';
		$row_last = 'display:flex;gap:12px;margin-bottom:0;';
		$half     = 'flex:1;min-width:0;';
		$hint  = 'margin:12px 0 0 0;font-size:12px;color:#646970;font-style:italic;';

		ob_start();
		?>
		<div class="ginput_container ginput_container_airwallex bst-airwallex-editor-preview" style="<?php echo esc_attr( $style ); ?>">
			<p style="margin:0 0 14px 0;font-size:13px;color:#50575e;">
				<?php esc_html_e( 'Preview — Airwallex Drop-in (card fields) will load here on the live site.', 'bst' ); ?>
			</p>
			<div style="<?php echo esc_attr( $row ); ?>">
				<div style="flex:1;min-width:0;">
					<label style="<?php echo esc_attr( $label ); ?>"><?php esc_html_e( 'Card number', 'bst' ); ?></label>
					<input type="text" readonly disabled style="<?php echo esc_attr( $input ); ?>" value="4242 4242 4242 4242" aria-hidden="true" />
				</div>
			</div>
			<div style="<?php echo esc_attr( $row ); ?>">
				<div style="flex:1;min-width:0;">
					<label style="<?php echo esc_attr( $label ); ?>"><?php esc_html_e( 'Name on card', 'bst' ); ?></label>
					<input type="text" readonly disabled style="<?php echo esc_attr( $input ); ?>" value="Jane Cardholder" aria-hidden="true" />
				</div>
			</div>
			<div style="<?php echo esc_attr( $row ); ?>">
				<div style="flex:1;min-width:0;">
					<label style="<?php echo esc_attr( $label ); ?>"><?php esc_html_e( 'Billing postal / ZIP', 'bst' ); ?></label>
					<input type="text" readonly disabled style="<?php echo esc_attr( $input ); ?>" value="94102" aria-hidden="true" />
				</div>
			</div>
			<div style="<?php echo esc_attr( $row_last ); ?>">
				<div style="<?php echo esc_attr( $half ); ?>">
					<label style="<?php echo esc_attr( $label ); ?>"><?php esc_html_e( 'Expiration', 'bst' ); ?></label>
					<input type="text" readonly disabled style="<?php echo esc_attr( $input ); ?>" value="12 / 34" aria-hidden="true" />
				</div>
				<div style="<?php echo esc_attr( $half ); ?>">
					<label style="<?php echo esc_attr( $label ); ?>"><?php esc_html_e( 'CVC', 'bst' ); ?></label>
					<input type="text" readonly disabled style="<?php echo esc_attr( $input ); ?>" value="123" aria-hidden="true" />
				</div>
			</div>
			<p style="<?php echo esc_attr( $hint ); ?>">
				<?php esc_html_e( 'Use test cards in sandbox on the front end; nothing is charged from the form builder.', 'bst' ); ?>
			</p>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param int $form_id Form ID.
	 * @return string
	 */
	private function get_return_url_for_form( $form_id ) {
		$url = isset( $_SERVER['REQUEST_URI'] ) ? home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : home_url( '/' );
		return remove_query_arg( array( 'awx_status', 'awx_id' ), $url );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string|array $value Field value.
	 * @param array        $form  Form.
	 */
	public function validate( $value, $form ) {
		if ( ! $this->isRequired ) {
			return;
		}
		// Parent expects form ID, not $value (see GF_Field::is_value_submission_empty).
		if ( $this->is_value_submission_empty( (int) rgar( $form, 'id' ) ) ) {
			$this->failed_validation  = true;
			$this->validation_message = empty( $this->errorMessage )
				? esc_html__( 'Please complete payment before submitting the form.', 'bst' )
				: $this->errorMessage;
		}
	}
}
