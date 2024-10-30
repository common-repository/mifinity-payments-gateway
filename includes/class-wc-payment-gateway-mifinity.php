<?php

/**
 * MiFinity Payments Gateway.
 *
 * Provides a MiFinity Payments Payment Gateway.
 *
 * @class       MiFinity_Payment_Gateway
 * @extends     WC_Payment_Gateway
 * @version     1.0.9
 * @package     WooCommerce/Classes/Payment
 */

class MiFinity_Payment_Gateway extends WC_Payment_Gateway_CC {

    const ENDPOINT_URL_TEST = 'https://demo.mifinity.com/pegasus-ci/api/gateway/guest-payment';
    const ENDPOINT_URL_LIVE = 'https://secure.mifinity.com/pegasus-ci/api/gateway/guest-payment';

    /**
	 * Constructor for the gateway.
	 */
    public function __construct() {
        // Setup general properties.
        $this->setup_properties();
        
        // Load the settings.
		$this->init_form_fields();
        $this->init_settings();
        
        // Get settings.
        $this->title = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );
        $this->instructions = $this->get_option( 'instructions', $this->description );
        $this->enabled = $this->get_option( 'enabled' );
        $this->testmode = $this->get_option( 'testmode' ) === 'yes' ? true : false;
        $this->api_key = $this->get_option( 'api_key' );
        $this->account_number = $this->get_option( 'account_number' );
        $this->logging = $this->get_option( 'logging' ) === 'yes' ? true : false;
        $this->debugging = $this->get_option( 'debugging' ) === 'yes' ? true : false;
        $this->statement_descriptor = $this->get_option( 'statement_descriptor', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );

        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options' ));
        
        add_action( 'woocommerce_thank_you_' . $this->id, array($this, 'thank_you_page' ));

        // load external JS files
        add_action( 'wp_enqueue_scripts', array( $this, 'addScripts' ) );
    }

    /**
	 * Get additional JS files
	 */
    public function addScripts(){
        ?>
        <script type='text/javascript' id='threeDsSt' src='https://webservices.securetrading.net/js/v2/st.js'></script>
        <?php
        wp_enqueue_script( 'script', plugins_url('../assets/js/payment-handler.js', __FILE__ ));

        $cart = WC()->cart;
        $amount = 0;
        if ($cart) {
            $amount = $cart->get_cart_contents_total();
        }
        $currency = $this->get_payment_currency();

        $liveStatus = 0;
        if (!$this->testmode) {
            $liveStatus = 1;
        }

        $script_params = array(
            'endpoint_url' => $this->testmode ? self::ENDPOINT_URL_TEST : self::ENDPOINT_URL_LIVE,
            'key'          => $this->api_key,
            'currency'     => $currency,
            'amount'       => $amount,
            'live_status'  => $liveStatus,
            'descriptor'   => $this->statement_descriptor
        );

        wp_localize_script( 'script', 'scriptParams', $script_params );
    }

    /**
	 * Setup general properties for the gateway.
	 */
	protected function setup_properties() {
        $this->id = 'mifinity_payment';
        $this->has_fields = true;
        $this->supports = array( 'refunds' );
        $this->method_title = __( 'MiFinity Payments', 'mifinity-pay-woo');
        $this->method_description = __( 'Take Credit card payments easily and directly on your store.', 'mifinity-pay-woo');
    }

    /**
	 * get_icon function.
	 *
	 * @access public
	 * @return string
	 */
	public function get_icon() {
		$icon = '<img style="margin-left: 0.3em" src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/visa.svg' ) . '" alt="Visa" width="32" />' .
                '<img style="margin-left: 0.3em" src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/mastercard.svg' ) . '" alt="Mastercard" width="32" />';
        return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}

    /**
	 * Check if SSL is enabled and notify the user
	 */
	public function admin_notices() {
		if ( $this->enabled == 'no' ) {
            return;
        }

		// Check required fields
        if ( !$this->api_key ) {
            echo  '<div class="error"><p>' . sprintf( __( 'MiFinity error: Please enter your API Key <a href="%s">here</a>', 'mifinity-pay-woo' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=mifinity_payment' ) ) . '</p></div>';
            return;
        } elseif ( !$this->account_number ) {
            echo  '<div class="error"><p>' . sprintf( __( 'MiFinity error: Please enter your Account Number <a href="%s">here</a>', 'mifinity-pay-woo' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=mifinity_payment' ) ) . '</p></div>';
            return;
        }

        // Show message if enabled and FORCE SSL is disabled and WordpressHTTPS plugin is not detected
        if ( !wc_checkout_is_https() ) {
            echo  '<div class="notice notice-warning"><p>' . sprintf( __( 'MiFinity is enabled, but an SSL certificate is not detected. Your checkout may not be secure! Please ensure your server has a valid <a href="%1$s" target="_blank">SSL certificate</a>', 'mifinity-pay-woo' ), 'https://en.wikipedia.org/wiki/Transport_Layer_Security' ) . '</p></div>';
        }
    }
    
    /**
	 * Check if this gateway is enabled
	 */
	public function is_available() {
		if ( $this->enabled == "yes" ) {
            // Required fields check
            if ( !$this->api_key || !$this->account_number ) {
                return false;
            }
            return true;
        }

        return parent::is_available();
	}

    /**
	 * Initialize Gateway Settings Form Fields.
	 */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __( 'Enable/Disable', 'mifinity-pay-woo'),
                'type' => 'checkbox',
                'label' => __( 'Enable MiFinity', 'mifinity-pay-woo'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __( 'Title', 'mifinity-pay-woo'),
                'type' => 'text',
                'default' => __( 'Debit/Credit Card', 'mifinity-pay-woo'),
                'desc_tip' => true,
                'description' => __( 'Add a new title for the MiFinity Gateway that customers will see when they are in the checkout page.', 'mifinity-pay-woo')
            ),
            'description' => array(
                'title' => __( 'Description', 'mifinity-pay-woo'),
                'type' => 'text',
                'default' => __( 'Pay with your credit card via MiFinity.', 'mifinity-pay-woo'),
                'desc_tip' => true,
                'description' => __( 'The user will see this description during checkout.', 'mifinity-pay-woo')
            ),
			'logging' => array(
				'title'       => __( 'Logging', 'mifinity-pay-woo' ),
				'label'       => __( 'Log debug messages', 'mifinity-pay-woo' ),
				'type'        => 'checkbox',
				'description' => sprintf( __( 'Save debug messages to the WooCommerce System Status log file <code>%s</code>.', 'mifinity-pay-woo' ), WC_Log_Handler_File::get_log_file_path( 'woocommerce-gateway-mifinity_payment' ) ),
				'default'     => 'no',
            ),
            'api_key' => array(
                'title'       => __('API Key', 'mifinity-pay-woo'),
                'type'        => 'textarea'
            ),
            'account_number' => array(
                'title'       => __('Account Number', 'mifinity-pay-woo'),
                'type'        => 'text',
            ),
			'statement_descriptor' => array(
				'title'       => __( 'Statement Descriptor', 'mifinity-pay-woo' ),
				'type'        => 'text',
				'description' => __( 'Extra information about a charge. This will appear in your order description. Defaults to site name.', 'mifinity-pay-woo' ),
				'default'     => '',
				'desc_tip'    => true,
			),
            'testmode' => array(
                'title'		=> __( 'Test Mode', 'mifinity-pay-woo' ),
                'label'		=> __( 'Enable Test Mode', 'mifinity-pay-woo' ),
                'type'		=> 'checkbox',
                'description' => __( 'Place the payment gateway in test mode.', 'mifinity-pay-woo' ),
                'default'	=> 'yes',
            )
        );
    }

    /**
	 * Set custom payment fields
	 */
    public function payment_fields() {

        if ( $this->testmode ) {
			$this->description .= ' ' . sprintf( __( '<br /><br /><strong>TEST MODE ENABLED</strong><br /> In test mode, you can use the card number 5200000000001096 with any CVC and a valid expiration date.', 'mifinity-pay-woo' ), 'https://apidocs.mifinity.com/', $this->method_title );
            $this->description  = trim( $this->description );
        }

        echo wpautop( wp_kses_post( $this->description ) );
        
        $this->form();

        echo wp_kses_post( '<div id="powered-by-container">
                                <div id="powered-by">
                                    <div id="powered-by-label">
                                        <span>Powered by</span>
                                    </div>
                                    <div id="powered-by-logos">
                                        <img style="max-height:1.5em !important; float:unset; padding-right:.6em" src="' . WC_HTTPS::force_https_url( plugins_url('../assets/images/mifinity.png', __FILE__ ) ) . '" alt="MiFinity" />
                                        <img style="max-height:1.5em !important; float:unset; padding-right:.6em" src="' . WC_HTTPS::force_https_url( plugins_url('../assets/images/pci.png', __FILE__ ) ) . '" alt=pci" />
                                        <img style="max-height:1.5em !important; float:unset" src="' . WC_HTTPS::force_https_url( plugins_url('../assets/images/digicert.svg', __FILE__ ) ) . '" alt="digicert" />
                                    </div>
                                </div>
                            </div>' );

        ?>
        <script type="text/javascript">
        (function($){
            $('#mifinity_payment-card-number').first().css('font-size', '1em');
            $('#mifinity_payment-card-expiry').first().css('font-size', '1em');
            $('#mifinity_payment-card-cvc').first().css('font-size', '1em');
            $('#powered-by-container').first().css('display', 'flex');
            $('#powered-by-container').first().css('justify-content', 'flex-end');
            $('#powered-by').first().css('display', 'flex');
            $('#powered-by').first().css('flex-direction', 'column');
            $('#powered-by').first().css('justify-content', 'flex-end');
            $('#powered-by').first().css('width', 'max-content');
            $('#powered-by-logos').first().css('display', 'flex');
            $('#powered-by-logos').first().css('align-items', 'center');
            $('#powered-by-label').first().css('color', '#C2C2C2');
            $('#powered-by-label').first().css('font-size', '.6em');
        })(jQuery);
        </script>
        <?php
     
    }

    /**
	 * Process the payment
	 */
    public function process_payment( $order_id, $retry = true ) {

        $order = wc_get_order( $order_id );

        $this->log( "Info: Begin processing payment for order {$order_id} for the amount of {$order->get_total()}" );

        try {
            $payment_args = array();

            // Check for CC details filled or not
			if( empty( $_POST['mifinity_payment-card-number'] ) || empty( $_POST['mifinity_payment-card-expiry'] ) || empty( $_POST['mifinity_payment-card-cvc'] ) ) {
				throw new Exception( __( 'Credit card details cannot be left incomplete.', 'mifinity-pay-woo' ) );
            }

            // validate card number
            $card_number = str_replace(' ', '', wc_clean( $_POST['mifinity_payment-card-number'] ));
            if (!preg_match('/^(?:4[0-9]{12}(?:[0-9]{3})?|4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|6[3-5][0-9]{14}|3[47][0-9]{13}|3(?:0[0-5]|[68][0-9])[0-9]{11}|6(?:011|5[0-9]{2})[0-9]{12}(?:2131|1800|35\d{3})\d{11})$/', $card_number)) {
                throw new Exception( __( 'Card number is incorrect', 'mifinity-pay-woo' ) );
            }

            // validate expiry date
            $expiry_date_stripped = str_replace(' ', '', wc_clean( $_POST['mifinity_payment-card-expiry'] ));
            if (!preg_match('/^(0[1-9]|1[0-2])\/?([0-9]{4}|[0-9]{2})$/', $expiry_date_stripped)) {
                throw new Exception( __( 'Expiry date is incorrect', 'mifinity-pay-woo' ) );
            }

            // validate CVC
            if (!preg_match('/^[0-9]{3,4}$/', wc_clean( $_POST['mifinity_payment-card-cvc'] ))) {
                throw new Exception( __( 'CVC is incorrect', 'mifinity-pay-woo' ) );
            }
            
            $expiry = explode( ' / ', wc_clean( $_POST['mifinity_payment-card-expiry'] ) );

            $description = sprintf( __( '%s - Order %s', 'mifinity-pay-woo' ), $this->statement_descriptor, $order->get_order_number() );

            $threeDS2 = json_decode( wc_clean( wp_unslash( $_POST['three-ds-resp'] )));

            $payment_args = array(
                'money'   => array(
                    'amount' => $order->get_total(),
                    'currency' => $this->get_payment_currency( $order_id )
                ),
                'description' => substr( $description, 0, 255 ),
                'destinationAccountNumber' => $this->account_number,
                'customerId' => $order->get_user_id(),
                'cardNumber' => str_replace( ' ', '', wc_clean( $_POST['mifinity_payment-card-number'] ) ),
                'billingName' => substr( trim( $order->get_billing_first_name() ) . ' ' . trim( $order->get_billing_last_name() ), 0, 75),
                'expiryDate' => $expiry[0] . '/' . $expiry[1],
                'transactionType' => 'SINGLE_PAYMENT',
                'traceId' => uniqid('', true),
                'cvc' => wc_clean( $_POST['mifinity_payment-card-cvc'] ),
                'billingAddress' => array(
                    'firstName' => substr( $order->get_billing_first_name(), 0, 50 ),
                    'lastName' => substr( $order->get_billing_last_name(), 0, 50 ),
                    'addressLine1' => substr( $order->get_billing_address_1(), 0, 60 ),
                    'addressLine2' => substr( $order->get_billing_address_2(), 0, 60 ),
                    'countryCode' => $order->get_billing_country(),
                    'postalCode' => $order->get_billing_postcode(),
                    'city' => substr( $order->get_billing_city(), 0, 30 ),
                    'emailAddress' => $order->get_billing_email(),
                    'phone' => $order->get_billing_phone(),
                    'company' => substr( $order->get_billing_company(), 0, 50 )
                ),
                'shippingInformation' => array(
                    'firstName' => empty(substr( $order->get_shipping_first_name(), 0, 50 )) ? substr( $order->get_billing_first_name(), 0, 50 ) : substr( $order->get_shipping_first_name(), 0, 50 ),
                    'lastName' => empty(substr( $order->get_shipping_last_name(), 0, 50 )) ? substr( $order->get_billing_last_name(), 0, 50 ) : substr( $order->get_shipping_last_name(), 0, 50 ),
                    'addressLine1' => empty($order->get_shipping_address_1()) ? substr( $order->get_billing_address_1(), 0, 60 ) : substr( $order->get_shipping_address_1(), 0, 60 ),
                    'addressLine2' => empty($order->get_shipping_address_2()) ? substr( $order->get_billing_address_2(), 0, 60 ) : substr( $order->get_shipping_address_2(), 0, 60 ),
                    'countryCode' => empty($order->get_shipping_country()) ? $order->get_billing_country() : $order->get_shipping_country(),
                    'postalCode' => empty($order->get_shipping_postcode()) ? $order->get_billing_postcode() : $order->get_shipping_postcode(),
                    'city' => empty($order->get_shipping_city()) ? substr( $order->get_billing_city(), 0, 30 ) : substr( $order->get_shipping_city(), 0, 30 ),
                    'company' => substr( $order->get_shipping_company(), 0, 50 )
                ),
                'threeDS2' => $threeDS2
            );
            
            $response = $this->payment_request( $payment_args );

            if ( is_wp_error( $response ) ) {
				throw new Exception( $response->get_error_message() );
            }

			$order->update_meta_data( '_mifinity_payment_charge_id', $response['response_code'] );
            
            $order->set_transaction_id( $response['response_code'] );

            if( $response['response_code']) {

                // Store captured value
                $order->update_meta_data( '_mifinity_payment_charge_captured', 'yes' );
                $order->update_meta_data( 'MiFinity Transaction reference', $response['transaction_reference'] );

                // Payment complete
                $order->payment_complete( $response['response_code'] );

                // Add order note
                $complete_message = sprintf( __( 'MiFinity charge complete (Charge ID: %s)', 'mifinity-pay-woo' ), $response['response_code'] );
                $order->add_order_note( $complete_message );
                $this->log( "Success: $complete_message" );
            }

            $order->save();

			// Remove cart
			WC()->cart->empty_cart();

			do_action( 'wc_gateway_mifinity_payment_process_payment', $response, $order );

			// Return thank you page redirect
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order )
			);
            
        } catch ( Exception $e ) {
            wc_add_notice( sprintf( __( '%s', 'mifinity-pay-woo' ), $e->getMessage() ), 'error' );
            $this->log( sprintf( __( '%s', 'mifinity-pay-woo' ), $e->getMessage() ) );

            if(is_wp_error( $response ) && $response = $response->get_error_data() ) {
                $order->add_order_note( sprintf( __( 'MiFinity failure reason: %s', 'mifinity-pay-woo' ), $response['response_reason_code'] . ' - ' . $response['response_reason_text'] ) );
            }

            do_action( 'wc_gateway_mifinity_payment_process_payment_error', $e, $order );

            $order->update_status( 'failed' );

			return array(
				'result'   => 'fail',
				'redirect' => ''
			);
        }
    }

    /**
	 * Create payment request
	 */
    function payment_request( $args ) {

        $endpoint_url = $this->testmode ? self::ENDPOINT_URL_TEST : self::ENDPOINT_URL_LIVE;

        $body = wp_json_encode( $args );

        $options = [
            'body'        => $body,
            'headers'     => [
                'Content-Type' => 'application/json',
                'api-version'  => 1,
                'key'          => $this->api_key
            ],
            'timeout'     => 60,
            'redirection' => 5,
            'blocking'    => true,
            'httpversion' => '1.0',
            'data_format' => 'body',
        ];

        $response = wp_remote_post( $endpoint_url, $options );

        if ( is_wp_error( $response ) ) {
            return $response;
        } elseif (wp_remote_retrieve_response_code( $response ) != 200 && wp_remote_retrieve_response_code( $response ) != 201) {
            $error = wp_remote_retrieve_body( $response );
            $error_message = json_decode($error, true)['errors'][0]['message'];
            return new WP_Error( 'card_declined', $error_message );
        } else {
            $result = wp_remote_retrieve_body( $response );
            $result_payload = json_decode($result, true)['payload'][0];
            $mifinity_response = array(
                'transaction_reference' => $result_payload['transactionReference'],
                'response_code'         => $result_payload['responseCode']
            );
            return $mifinity_response;
        }

    }

    /**
	 * Process the refund
	 */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {

        $order = wc_get_order( $order_id );

        if ( ! $order || ! $order->get_transaction_id() || $amount <= 0 ) {
			return false;
        }
        
        $refund_type = $amount == $order->get_total() ? 'FULL' : 'PARTIAL';

        $args = array(
            'money' => array(
                'amount' => $amount,
                'currency' => $this->get_payment_currency( $order_id )
            ),
            'refundType' => $refund_type,
            'traceId' => uniqid('', true),
            'responseCode' => $order->get_transaction_id()
        );

        $this->log( "Info: Beginning refund for order $order_id for the amount of {$amount}" );

        $response = $this->refund_request( $args );

        if ( is_wp_error( $response ) ) {
            $this->log( $response->get_error_message() );
            return $response;
        } elseif ( $response['response_code'] ) {
            $refund_message = sprintf( __( 'Refunded %s - Reason: %s', 'mifinity-pay-woo' ), $amount, $reason );
            $order->add_order_note( $refund_message );
            $order->save();
            $this->log( "Success: " . html_entity_decode( strip_tags( $refund_message ) ) );
        }

        return true;
    }

    /**
	 * Create refund request
	 */
    function refund_request($args) {

        $endpoint_url = $this->testmode ? self::ENDPOINT_URL_TEST : self::ENDPOINT_URL_LIVE;
        $refund_url = $endpoint_url . '/revert';

        $body = wp_json_encode( $args );

        $options = [
            'body'        => $body,
            'headers'     => [
                'Content-Type' => 'application/json',
                'api-version'  => 1,
                'key'          => $this->api_key
            ],
            'timeout'     => 60,
            'redirection' => 5,
            'blocking'    => true,
            'httpversion' => '1.0',
            'data_format' => 'body',
        ];

        $response = wp_remote_post( $refund_url, $options );

        if ( is_wp_error( $response ) ) {
            return $response;
        } elseif (wp_remote_retrieve_response_code( $response ) != 200 && wp_remote_retrieve_response_code( $response ) != 201) {
            $error = wp_remote_retrieve_body( $response );
            $error_message = json_decode($error, true)['errors'][0]['message'];
            return new WP_Error( 'refund_declined', $error_message );
        } else {
            $refund_response = array(
                'response_code'  => 'OK'
            );
            return $refund_response;
        }

    }

    /**
	 * Get payment currency, either from current order or WC settings
	 */
	function get_payment_currency( $order_id = false ) {
        $currency = get_woocommerce_currency();
        $order_id = ! $order_id ? $this->get_checkout_pay_page_order_id() : $order_id;

        // Gets currency for the current order, that is about to be paid for
        if ( $order_id ) {
            $order    = wc_get_order( $order_id );
            $currency = $order->get_currency();
        }
        return $currency;
    }

    /**
	 * Returns the order_id if on the checkout pay page
	 */
	public function get_checkout_pay_page_order_id() {
		global $wp;
		return isset( $wp->query_vars['order-pay'] ) ? absint( $wp->query_vars['order-pay'] ) : 0;
	}

    /**
	 * Output for the order received page.
	 */
    public function thank_you_page(){
        if( $this->instructions ){
            echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) );
        }
    }

    /**
	 * Get log instance
	 *
	 * @param string $context
	 * @param string $message
	 */
	public function log( $message ) {
		if ( $this->logging ) {
			WC_MiFinity_Logger::log( $message );
		}
	}

}

?>