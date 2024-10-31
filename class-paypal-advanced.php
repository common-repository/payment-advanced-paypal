<?php

class POCOPPP_WC_Gateway_PayPal_Advanced extends WC_Payment_Gateway {

	const PAYFLOWPRO_TEST = 'https://pilot-payflowpro.paypal.com/'; 
	const PAYFLOWPRO_LIVE = 'https://payflowpro.paypal.com';

	public function __construct() {

		$this->id = 'poco_paypal_advanced';
		$this->icon = POCOPPA_PLUGIN_URL . '/assets/images/combo.jpg';
		$this->has_fields = false;
		$this->method_title = __( 'PayPal Advanced', 'woocommerce-gateway-paypal-advanced' );
		$this->method_description = __( 'Receive payments using PayPal Advanced', 'woocommerce-gateway-paypal-advanced' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title = __( 'Pay with Visa, Mastercard', 'woocommerce-gateway-paypal-advanced' );
		// $this->description = __( '', 'woocommerce-gateway-paypal-advanced' );
		$this->partner_name = $this->get_option('partner_name');
		$this->vendor_name = $this->get_option('vendor_name');
		$this->user = $this->get_option('user');
		$this->password = $this->get_option('password');
		$this->transaction_type = $this->get_option('transaction_type');
		$this->payment_gateway = $this->get_option('payment_gateway');

		$this->log = new WC_Logger();

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_api_ppa_response', array( $this, 'ppa_response' ) );

	}

	public function receipt_page($order_id) {
		//create order object
		$order = new WC_Order($order_id);
		$auth_vars = $this->payment_auth( $order_id );

		if (isset($auth_vars['ppa_errors']) && is_array($auth_vars['ppa_errors']) && $auth_vars['ppa_errors']) {
			foreach ($auth_vars['ppa_errors'] as $error) {
				echo $error;
			}
		} else {
		?>

		<iframe src="<?php echo $auth_vars['ppa_url']; ?>" name="ppa_iframe" id="ppa_iframe" frameborder=0 style="width:500px;height:610px;border:none"></iframe>

		<?php
		}
	}

	public function ppa_response() {

		$this->log->add( $this->id, 'Response from PayPal: ' . print_r( wc_clean($_REQUEST), true ) );

		$order_id = 0;

		if ( isset( $_REQUEST[ 'ORDERID '] ) ) {
			$order_id = (int)$_REQUEST[ 'ORDERID' ];
		} else {

			global $wpdb;
			$secure_token = wc_clean($_REQUEST[ 'SECURETOKEN' ]);
			$post_meta = $wpdb->get_row( "select post_id from $wpdb->postmeta where meta_key = '_ppa_secure_token' and meta_value = '$secure_token'", ARRAY_A );

			if ( is_array( $post_meta ) && isset( $post_meta[ 'post_id' ] ) && $post_meta[ 'post_id' ] ) {
				$order_id = $post_meta[ 'post_id' ];
			}

		}

		$order = wc_get_order( $order_id );

		if ( ! $order_id || ! $order ) {
			$this->log->add( $this->id, 'Error: order not found.' . print_r( $order_id, true ) );
			die( __( 'Error: order not found.', 'woocommerce-gateway-paypal-advanced' ) );
		}

		if ($_REQUEST[ 'RESULT' ] == 0 && $_REQUEST[ 'RESPMSG' ] == 'Approved') {

			update_post_meta( $order_id, '_ppa_pnref', wc_clean($_REQUEST[ 'PNREF' ]) );
			update_post_meta( $order_id, '_ppa_expdate', wc_clean($_REQUEST[ 'EXPDATE' ]) );
			update_post_meta( $order_id, '_ppa_acct', wc_clean($_REQUEST[ 'ACCT' ]) );

			$order->payment_complete();
			WC()->cart->empty_cart();

			$order->add_order_note( sprintf( __( 'Payment was successfully processed by PayPal Advanced. Last 4 digits of the card: %s', 'woocommerce-gateway-paypal-advanced' ), wc_clean($_REQUEST[ 'ACCT' ]) ) );

		} else {
			$order->update_status( 'failed' );

			$order->add_order_note( sprintf( __( 'Error Message: %s', 'woocommerce-gateway-paypal-advanced' ), wc_clean($_REQUEST[ 'RESPMSG' ]) ) );
		}

		$redirect = $this->get_return_url( $order );

		echo '<script>'
			. "parent.location.href = '" . $redirect . "'"
			. '</script>';

		exit();
	}

	public function init_form_fields(){

		$this->form_fields = array(
			'partner_name' => array(
				'title' => __( 'Partner Name', 'woocommerce-gateway-paypal-advanced' ),
				'type' => 'text',
				'label' => __( 'Partner Name', 'woocommerce-gateway-paypal-advanced' ),
				'default' => ''
			),
			'vendor_name' => array(
				'title' => __( 'Vendor Name', 'woocommerce-gateway-paypal-advanced' ),
				'type' => 'text',
				'label' => __( 'Vendor Name', 'woocommerce-gateway-paypal-advanced' ),
				'default' => ''
			),
			'user' => array(
				'title' => __( 'User', 'woocommerce-gateway-paypal-advanced' ),
				'type' => 'text',
				'label' => __( 'User', 'woocommerce-gateway-paypal-advanced' ),
				'default' => ''
			),
			'password' => array(
				'title' => __( 'Password', 'woocommerce-gateway-paypal-advanced' ),
				'type' => 'text',
				'label' => __( 'Password', 'woocommerce-gateway-paypal-advanced' ),
				'default' => ''
			),
			'transaction_type' => array(
				'title' => __( 'Transaction type', 'woocommerce-gateway-paypal-advanced' ),
				'type' => 'select',
				'label' => __( 'Transaction type', 'woocommerce-gateway-paypal-advanced' ),
				'options' => array(
					'AUTHORIZATION' => 'AUTHORIZATION',
					'SALE' => 'SALE'
				)
			),
			'payment_gateway' => array(
				'title' => __( 'Payment Gateway', 'woocommerce-gateway-paypal-advanced' ),
				'type' => 'select',
				'label' => __( 'Payment Gateway', 'woocommerce-gateway-paypal-advanced' ),
				'options' => array(
					'0' => __( 'Live', 'woocommerce-gateway-paypal-advanced' ),
					'1' => __( 'Sandbox', 'woocommerce-gateway-paypal-advanced' )
				)
			),
			'accpeted_cards' => array(
				'title' => __( 'Accepted Cards', 'woocommerce-gateway-paypal-advanced' ),
				'type' => 'cardsettings'
			)
		);
	}

	public function generate_cardsettings_html( $key, $data ){

		$ppa_visa = get_option('pocoppa_visa');
		$ppa_mc = get_option('pocoppa_mc');
		$ppa_amex = get_option('pocoppa_amex');
		$ppa_discover = get_option('pocoppa_discover');

		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>

					<input type="checkbox" value="1" id="ppa_visa" name="ppa_visa" <?php if ( $ppa_visa == 1 ) echo 'checked'; ?>>
					<img src="<?php echo POCOPPA_PLUGIN_URL; ?>/assets/images/visa.gif" style="vertical-align: middle;">
					&nbsp;&nbsp;
					<input type="checkbox" value="1" id="ppa_mc" name="ppa_mc" <?php if ( $ppa_mc == 1 ) echo 'checked'; ?>>
					<img src="<?php echo POCOPPA_PLUGIN_URL; ?>/assets/images/mc.gif" style="vertical-align: middle;">
					&nbsp;&nbsp;
					<input type="checkbox" value="1" id="ppa_amex" name="ppa_amex"<?php if ( $ppa_amex == 1 ) echo 'checked'; ?>>
					<img src="<?php echo POCOPPA_PLUGIN_URL; ?>/assets/images/amex.gif" style="vertical-align: middle;">
					&nbsp;&nbsp;
					<input type="checkbox" value="1" id="ppa_discover" name="ppa_discover"<?php if ( $ppa_discover == 1 ) echo 'checked'; ?>>
					<img src="<?php echo POCOPPA_PLUGIN_URL; ?>/assets/images/discover.gif" style="vertical-align: middle;">
					&nbsp;&nbsp;<p>(<?php echo __( 'For payment logo display only', 'woocommerce-gateway-paypal-advanced' ); ?>)</p>


					<?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	public function process_admin_options() {

		update_option( 'partner_name', sanitize_user($_POST['woocommerce_poco_paypal_advanced_partner_name'] ));
		update_option( 'vendor_name', sanitize_user($_POST['woocommerce_poco_paypal_advanced_vendor_name'] ));
		update_option( 'user', sanitize_user($_POST['woocommerce_poco_paypal_advanced_user'] ));
		update_option( 'password', wc_clean($_POST['woocommerce_poco_paypal_advanced_password'] ));
		update_option( 'transaction_type', wc_clean($_POST['woocommerce_poco_paypal_advanced_transaction_type'] ));
		update_option( 'payment_gateway', (int)$_POST['woocommerce_poco_paypal_advanced_payment_gateway'] );

		if ( isset( $_POST[ 'ppa_visa' ] ) ) {
			update_option( 'pocoppa_visa', 1 );
		} else {
			update_option( 'pocoppa_visa', 0 );
		}

		if ( isset( $_POST[ 'ppa_mc' ] ) ) {
			update_option( 'pocoppa_mc', 1 );
		} else {
			update_option( 'pocoppa_mc', 0 );
		}

		if ( isset( $_POST[ 'ppa_amex' ] ) ) {
			update_option( 'pocoppa_amex', 1 );
		} else {
			update_option( 'pocoppa_amex', 0 );
		}

		if ( isset( $_POST[ 'ppa_discover' ] ) ) {
			update_option( 'pocoppa_discover', 1 );
		} else {
			update_option( 'pocoppa_discover', 0 );
		}

		$this->create_combo( get_option( 'pocoppa_visa' ), get_option( 'pocoppa_mc' ), get_option( 'pocoppa_amex' ), get_option( 'pocoppa_discover' ) );

		parent::process_admin_options();
	}

	public function process_payment( $order_id ) {
		global $woocommerce;
		$order = new WC_Order( $order_id );

		// Return thankyou redirect
		return array(
			'result' => 'success',
			'redirect' => $order->get_checkout_payment_url( true )
		);
	}

	public function get_returnUrl() {
		return str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'ppa_response', home_url( '/' ) ) );
	}

	public function payment_auth( $order_id ) {
		$request_data = $this->prepare_data_to_send( $order_id );
		$arr_response = $this->run_payflow_call( $request_data );
		$ret = array();

		if ( $arr_response['RESULT'] != '0' ) {
			$ret = array(
				'ppa_errors' => array( $arr_response["RESPMSG"] ),
			);
		} else {
			// save secure token
			update_post_meta( $order_id, '_ppa_secure_token', $arr_response['SECURETOKEN'] );
			update_post_meta( $order_id, '_ppa_secure_token_id', $arr_response['SECURETOKENID'] );

			$ret = array(
				'ppa_errors' => array(),
				'ppa_url' => 'https://payflowlink.paypal.com?SECURETOKEN=' . $arr_response['SECURETOKEN'] . '&SECURETOKENID=' . $arr_response['SECURETOKENID'] . '&MODE=' . $this->get_mode_str()
			);
		}

		return $ret;
	}

	public function prepare_data_to_send( $order_id ) {

		update_post_meta( $order_id, '_ppa_partner', $this->partner_name );
		update_post_meta( $order_id, '_ppa_vendor', $this->vendor_name );
		update_post_meta( $order_id, '_ppa_user', $this->user );
		update_post_meta( $order_id, '_ppa_password', $this->password );

		$order = new WC_Order( $order_id );

		$address2 = $order->get_billing_address_2();
		$shipping_address2 = $order->get_shipping_address_2();
		$request_data = array(
			"PARTNER" => $this->partner_name,
			"VENDOR" => $this->vendor_name,
			"USER" => $this->user,
			"PWD" => $this->password, 
			"TRXTYPE" => ! empty( $this->transaction_type ) ? substr( $this->transaction_type, 0, 1 ) : "A",

			"AMT" => number_format( $order->get_total(), 2, '.', '' ),
			"TAXAMT" => number_format( $order->get_total_tax(), 2, '.', '' ),
			"FREIGHTAMT" => number_format( $order->get_shipping_total(), 2, '.', '' ),

			"CURRENCY" => $order->get_currency(),
			"CREATESECURETOKEN" => "Y",
			"SECURETOKENID" => $this->get_vendor_tx_code(), //Should be unique, never used before
			"RETURNURL" => $this->get_returnUrl(),
			"CANCELURL" => $this->get_returnUrl(),
			"ERRORURL"  => $this->get_returnUrl(),
			"SILENTPOSTURL" => $this->get_returnUrl(),
			"TEMPLATE" => 'MINLAYOUT',
			"URLMETHOD" => 'POST',
			"BILLTOFIRSTNAME" => $order->get_billing_first_name(),
			"BUTTONSOURCE" => 'PrestoChangeo_SP',
			"BILLTOLASTNAME" => $order->get_billing_last_name(),
			"BILLTOSTREET" => $order->get_billing_address_1() . ( ! empty( $address2 ) ? ' ' . $address2 : '' ),
			"BILLTOCITY" => $order->get_billing_city(),
			"BILLTOSTATE" => $order->get_billing_state(),
			"BILLTOZIP" => $order->get_billing_postcode(),
			"BILLTOCOUNTRY" => $order->get_billing_country(),
			"SHIPTOFIRSTNAME" => $order->get_shipping_first_name(),
			"SHIPTOLASTNAME" => $order->get_shipping_last_name(),
			"SHIPTOSTREET" => $order->get_shipping_address_1() . ( ! empty( $shipping_address2 ) ? ' ' . $shipping_address2 : '' ),
			"SHIPTOCITY" => $order->get_shipping_city(),
			"SHIPTOSTATE" => $order->get_shipping_state(),
			"SHIPTOZIP" => $order->get_shipping_postcode(),
			"SHIPTOCOUNTRY" => $order->get_shipping_country(),
			"ORDERID" => $order->get_id()
		);

		return $request_data;
	}

	public function get_vendor_tx_code()
	{
		$str_time_stamp = date( 'ymdHis' );
		$int_rand_num = rand( 0, 32000 ) * rand( 0, 32000 );
		
		return substr( $this->user, 0 ,8 ) . "-" . $str_time_stamp . "-" . $int_rand_num;
	}

	// run_payflow_call: Runs a Payflow API call.  $params is an associative array of
	// Payflow API parameters.  Returns FALSE on failure, or an associative array of response
	// parameters on success.
	protected function run_payflow_call( $params ) {
		// Which endpoint will we be using?
		if($this->payment_gateway == 1) {
			$endpoint = self::PAYFLOWPRO_TEST;
		} else {
			$endpoint = self::PAYFLOWPRO_LIVE;
		}

		$args = array(
		    'body'        => $params,
		    'timeout'     => '5',
		    'redirection' => '5',
		    'httpversion' => '1.0',
		    'blocking'    => true,
		    'headers'     => array(),
		    'cookies'     => array(),
		);

		$result = wp_remote_post( $endpoint, $args );

		parse_str( wp_remote_retrieve_body( $result ), $result );
		if ( is_wp_error( $result ) ) {
			echo sprintf( __( 'An error occurred while trying to connect to PayPal: %s', 'woocommerce-gateway-paypal-advanced' ), $result->get_error_message() );
			return false;
		} else {
			return $result;
		}
	}

	public function get_mode_str() {
		return $this->payment_gateway == 0 ? 'LIVE' : 'TEST';
	}

	private function create_combo( $ppa_visa, $ppa_mc, $ppa_amex, $ppa_discover ) {
		if ( ! $ppa_visa && ! $ppa_mc && ! $ppa_amex && ! $ppa_discover ) {
			return;
		}

		$img_buf = array();
		if ( $ppa_visa ) {
			array_push( $img_buf, imagecreatefromgif( dirname( __FILE__ ) . '/assets/images/visa.gif' ) );
		}
		if ( $ppa_mc ) {
			array_push( $img_buf, imagecreatefromgif( dirname( __FILE__ ) . '/assets/images/mc.gif' ) );
		}
		if ( $ppa_amex ) {
			array_push( $img_buf, imagecreatefromgif( dirname( __FILE__ ) . '/assets/images/amex.gif' ) );
		}
		if ( $ppa_discover ) {
			array_push( $img_buf, imagecreatefromgif( dirname( __FILE__ ) . '/assets/images/discover.gif' ) );
		}

		$i_out = imagecreatetruecolor ( '86', ceil( sizeof( $img_buf ) / 2 ) * 26 );
		$bg_color = imagecolorallocate( $i_out, 255, 255, 255 );
		imagefill( $i_out, 0, 0, $bg_color );
		foreach ( $img_buf as $i => $img ) {
			imagecopy( $i_out, $img, ( $i % 2 == 0 ? 0 : 49 ) - 1, floor( $i / 2 ) * 26 - 1, 0, 0, imagesx( $img ), imagesy( $img ) );
			imagedestroy( $img );
		}
		imagejpeg( $i_out, dirname( __FILE__ ) . '/assets/images/combo.jpg', 100 );
	}

}