<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Gateway_Seerbit extends WC_Payment_Gateway_CC {

    /**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;

    /**
	 * Should orders be marked as complete after payment?
	 * 
	 * @var bool
	 */
	public $autocomplete_order;

    /**
	 * Seerbit payment page type.
	 *
	 * @var string
	 */
	public $payment_page;

    /**
	 * Seerbit test public key.
	 *
	 * @var string
	 */
	public $test_public_key;

    /**
	 * Seerbit test secret key.
	 *
	 * @var string
	 */
	public $test_secret_key;

    /**
	 * Seerbit live public key.
	 *
	 * @var string
	 */
	public $live_public_key;

    /**
	 * Seerbit live secret key.
	 *
	 * @var string
	 */
	public $live_secret_key;

    /**
	 * Message to output when order is complete.
	 *
	 * @var string
	 */
	public $order_complete_message;

	/**
	 * Message to output when order failed.
	 *
	 * @var string
	 */
	public $order_failed_message;

    /**
	 * Should we tokenize customer cards?
	 *
	 * @var bool
	 */
	public $tokenize_cards;
    
    /**
	 * Should we save customer cards?
	 *
	 * @var bool
	 */
	public $saved_cards;

    /**
	 * Should Seerbit split payment be enabled.
	 *
	 * @var bool
	 */
	public $split_payment;

    /**
	 * Should the cancel & remove order button be removed on the pay for order page.
	 *
	 * @var bool
	 */
	public $remove_cancel_order_button;

    /**
	 * Seerbit sub account split code.
	 *
	 * @var string
	 */
	public $split_code;

    /**
	 * API public key
	 *
	 * @var string
	 */
	public $public_key;

    /**
	 * API secret key
	 *
	 * @var string
	 */
	public $secret_key;

    /**
	 * Gateway disabled message
	 *
	 * @var string
	 */
	public $msg;

    /**
	 * Payment channels.
	 *
	 * @var array
	 */
	public $payment_methods = array();

    /**
	 * Constructor
	 */
	public function __construct() {

        $this->id                 = 'seerbit';
		$this->method_title       = __( 'Seerbit', 'woo-seerbit' );
		$this->method_description = sprintf( __( 'Seerbit provides merchants with the tools and services needed to accept online payments from local and international customers using Mastercard, Visa, Verve Cards and Bank Accounts. <a href="%1$s" target="_blank">Sign up</a> for a Seerbit account, and <a href="%2$s" target="_blank">get your API keys</a>.', 'woo-seerbit' ), 'https://seerbit.com', 'https://www.dashboard.seerbit.com/#/settings/api_keys' );
		$this->has_fields         = true;

		$this->payment_page = $this->get_option( 'payment_page' );

        $this->supports = array(
			'products',
			'refunds',
			'tokenization',
			'subscriptions',
			'multiple_subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
		);

        // Load the form fields
		$this->init_form_fields();

		// Load the settings
		$this->init_settings();

        // Get setting values
		$this->title              = $this->get_option( 'title' );
		$this->description        = $this->get_option( 'description' );
		$this->enabled            = $this->get_option( 'enabled' );
		$this->testmode           = $this->get_option( 'testmode' ) === 'yes' ? true : false;
		$this->autocomplete_order = $this->get_option( 'autocomplete_order' ) === 'yes' ? true : false;

        $this->test_public_key = $this->get_option( 'test_public_key' );
		$this->test_secret_key = $this->get_option( 'test_secret_key' );

		$this->live_public_key = $this->get_option( 'live_public_key' );
		$this->live_secret_key = $this->get_option( 'live_secret_key' );

        $this->order_complete_message = $this->get_option('order_complete_message');
		$this->order_failed_message = $this->get_option('order_failed_message');

        $this->tokenize_cards = $this->get_option( 'tokenize_cards' ) === 'yes' ? true : false;
		$this->saved_cards = $this->get_option( 'saved_cards' ) === 'yes' ? true : false;

        $this->split_payment              = $this->get_option( 'split_payment' ) === 'yes' ? true : false;
        $this->split_code = $this->get_option( 'split_code' );
        $this->remove_cancel_order_button = $this->get_option( 'remove_cancel_order_button' ) === 'yes' ? true : false;

        $this->public_key = $this->testmode ? $this->test_public_key : $this->live_public_key;
		$this->secret_key = $this->testmode ? $this->test_secret_key : $this->live_secret_key;

        // Hooks
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
        add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'process_admin_options',
			)
		);

        add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page' ) );

        // Payment listener/API hook.
		add_action( 'woocommerce_api_wc_gateway_seerbit', array( $this, 'verify_seerbit_transaction' ) );

		// Webhook listener/API hook.
		add_action( 'woocommerce_api_nts_wc_seerbit_webhook', array( $this, 'process_webhooks' ) );

		// Check if the gateway can be used.
		if ( ! $this->is_valid_for_use() ) {
			$this->enabled = false;
		}

    }

    /**
	 * Check if this gateway is enabled and available in the user's country.
	 */
	public function is_valid_for_use() {

        if ( ! in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_seerbit_supported_currencies', array( 'NGN', 'GHS', 'KES', 'TZS', 'USD', 'XOF') ) ) ) {

			$this->msg = sprintf( __( 'Seerbit does not support your store currency. Kindly set it to either NGN (&#8358), GHS (&#x20b5;), USD (&#36;), KES (KSh) or XOF (CFA) <a href="%s">here</a>', 'woo-seerbit' ), admin_url( 'admin.php?page=wc-settings&tab=general' ) );

			return false;

		}

		return true;

    }

    /**
	 * Display seerbit payment icon.
	 */
	public function get_icon() {

        $icon = '<img src="' . WC_HTTPS::force_https_url( plugins_url( 'assets/images/seerbit-logo.png', WC_SEERBIT_MAIN_FILE ) ) . '" width="200px" alt="Seerbit Payment Options" />';

        return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );

    }

    /**
	 * Check if Seerbit merchant details is filled.
	 */
	public function admin_notices() {

        if ( $this->enabled == 'no' ) {
			return;
		}

		// Check required fields.
		if ( ! ( $this->public_key && $this->secret_key ) ) {
			echo '<div class="error"><p>' . sprintf( __( 'Please enter your Seerbit merchant details <a href="%s">here</a> to be able to use the Seerbit WooCommerce plugin.', 'woo-seerbit' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=seerbit' ) ) . '</p></div>';
			return;
		}

    }

    /**
	 * Check if Seerbit gateway is enabled.
	 *
	 * @return bool
	 */
	public function is_available() {

		if ( 'yes' == $this->enabled ) {

			if ( ! ( $this->public_key && $this->secret_key ) ) {

				return false;

			}

			return true;

		}

		return false;

	}

    /**
	 * Admin Panel Options.
	 */
	public function admin_options() {
        ?>
        <h2>
            <?php 
                _e( 'Seerbit', 'woo-seerbit' );
                if ( function_exists( 'wc_back_link' ) ) {
                    wc_back_link( __( 'Return to payments', 'woo-seerbit' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
                }
            ?>
        </h2>

        <h4>
            <strong><?php printf( __( 'Please set your webhook URL <a href="%1$s" target="_blank" rel="noopener noreferrer">here</a> to the URL below<span style="color: red"><pre><code>%2$s</code></pre></span>', 'woo-seerbit' ), 'https://www.dashboard.seerbit.com/#/settings/webhooks', WC()->api_request_url( 'Nts_WC_Seerbit_Webhook' ) ); ?></strong>
        </h4>
        <?php 
        
        if ( $this->is_valid_for_use() ) {

			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';

		} else {
            ?>
            <div class="inline error"><p><strong><?php _e( 'Seerbit Payment Gateway Disabled', 'woo-seerbit' ); ?></strong>: <?php echo $this->msg; ?></p></div>

            <?php
        }
    }

    /**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {

        $form_fields = array(
            'enabled' => array(
                'title'       => __( 'Enable/Disable', 'woo-seerbit' ),
				'label'       => __( 'Enable Seerbit', 'woo-seerbit' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable Seerbit as a payment option on the checkout page.', 'woo-seerbit' ),
				'default'     => 'no',
				'desc_tip'    => true
            ),
            'title' => array(
                'title'       => __( 'Title', 'woo-seerbit' ),
				'type'        => 'text',
				'description' => __( 'This controls the payment method title which the user sees during checkout.', 'woo-seerbit' ),
				'default'     => __( 'Debit/Credit Cards', 'woo-seerbit' ),
				'desc_tip'    => true
            ),
            'description' => array(
                'title'       => __( 'Description', 'woo-seerbit' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the payment method description which the user sees during checkout.', 'woo-seerbit' ),
				'default'     => __( 'Make payment using your debit and credit cards', 'woo-seerbit' ),
				'desc_tip'    => true,
            ),
            'testmode' => array(
                'title'       => __( 'Test mode', 'woo-seerbit' ),
				'label'       => __( 'Enable Test Mode', 'woo-seerbit' ),
				'type'        => 'checkbox',
				'description' => __( 'Test mode enables you to test payments before going live. <br />Once the LIVE MODE is enabled on your Seerbit account uncheck this.', 'woo-seerbit' ),
				'default'     => 'yes',
				'desc_tip'    => true,
            ),
            'payment_page' => array(
                'title'       => __( 'Payment Option', 'woo-seerbit' ),
				'type'        => 'select',
				'description' => __( 'Popup shows the payment popup on the page while Redirect will redirect the customer to Seerbit to make payment.', 'woo-seerbit' ),
				'default'     => '',
				'desc_tip'    => false,
				'options'     => array(
					''          => __( 'Select One', 'woo-seerbit' ),
					'inline'    => __( 'Popup', 'woo-seerbit' ),
					'redirect'  => __( 'Redirect', 'woo-seerbit' ),
				)
            ),
            'test_public_key' => array(
                'title'       => __( 'Test Public Key', 'woo-seerbit' ),
				'type'        => 'text',
				'description' => __( 'Enter your Test Public Key here.', 'woo-seerbit' ),
				'default'     => ''
            ),
            'test_secret_key' => array(
                'title'       => __( 'Test Secret Key', 'woo-seerbit' ),
				'type'        => 'password',
				'description' => __( 'Enter your Test Secret Key here', 'woo-seerbit' ),
				'default'     => ''
            ),
            'live_public_key' => array(
                'title'       => __( 'Live Public Key', 'woo-seerbit' ),
				'type'        => 'text',
				'description' => __( 'Enter your Live Public Key here.', 'woo-seerbit' ),
				'default'     => ''
            ),
            'live_secret_key' => array(
                'title'       => __( 'Live Secret Key', 'woo-seerbit' ),
				'type'        => 'password',
				'description' => __( 'Enter your Live Secret Key here', 'woo-seerbit' ),
				'default'     => ''
            ),
            'autocomplete_order' => array(
                'title'       => __( 'Autocomplete Order After Payment', 'woo-seerbit' ),
				'label'       => __( 'Autocomplete Order', 'woo-seerbit' ),
				'type'        => 'checkbox',
				'class'       => 'wc-seerbit-autocomplete-order',
				'description' => __( 'If enabled, the order will be marked as complete after successful payment', 'woo-seerbit' ),
				'default'     => 'no',
				'desc_tip'    => true
            ),
            'remove_cancel_order_button' => array(
                'title'       => __( 'Remove Cancel Order & Restore Cart Button', 'woo-seerbit' ),
				'label'       => __( 'Remove the cancel order & restore cart button on the pay for order page', 'woo-seerbit' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
            ),
            'order_complete_message' => array(
                'title'       => __( 'Order Complete Message', 'woo-seerbit' ),
				'type'        => 'text',
				'description' => __( 'Enter message to output when order is completed .', 'woo-seerbit' ),
				'default'     => ''
            ),
            'order_failed_message' => array(
                'title'       => __( 'Order Failed Message', 'woo-seerbit' ),
				'type'        => 'text',
				'description' => __( 'Enter message to output when order fails .', 'woo-seerbit' ),
				'default'     => ''
            ),
            'split_payment' =>array(
                'title'       => __( 'Split Payment', 'woo-seerbit' ),
				'label'       => __( 'Enable Split Payment', 'woo-seerbit' ),
				'type'        => 'checkbox',
				'description' => '',
				'class'       => 'woocommerce_seerbit_split_payment',
				'default'     => 'no',
				'desc_tip'    => true,
            ),
            'split_code' => array(
                'title'       => __( 'Subaccount Split Code', 'woo-seerbit' ),
				'type'        => 'text',
				'description' => __( 'Enter the subaccount split code here.', 'woo-seerbit' ),
				'class'       => 'woocommerce_seerbit_split_code',
				'default'     => ''
            ),
            'tokenize_cards' => array(
                'title'       => __( 'Tokenize Cards', 'woo-seerbit' ),
				'label'       => __( 'Enable Card Tokenization', 'woo-seerbit' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, users cards will be tokenized.', 'woo-seerbit' ),
                'class'       => 'woocommerce_seerbit_tokenize_cards',
				'default'     => 'no',
				'desc_tip'    => true,
            ),
            'saved_cards' => array(
                'title'       => __( 'Saved Cards', 'woo-seerbit' ),
				'label'       => __( 'Enable Payment via Saved Cards', 'woo-seerbit' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, users will be able to pay with a saved card during checkout. Card details are saved on Seerbit servers, not on your store.<br>Note that you need to have a valid SSL certificate installed.', 'woo-seerbit' ),
				'class'       => 'woocommerce_seerbit_saved_cards',
                'default'     => 'no',
				'desc_tip'    => true,
            )
        );

        $this->form_fields = $form_fields;

    }

    /**
	 * Payment form on checkout page
	 */
	public function payment_fields() {

		if ( $this->description ) {
			echo wpautop( wptexturize( $this->description ) );
		}

		if ( ! is_ssl() ) {
			return;
		}

		if ( $this->supports( 'tokenization' ) && is_checkout() && $this->saved_cards && is_user_logged_in() ) {
			$this->tokenization_script();
			$this->saved_payment_methods();
			$this->save_payment_method_checkbox();
		}

	}
    
    /**
	 * Outputs scripts used for seerbit payment.
	 */
	public function payment_scripts() {

		if ( isset( $_GET['pay_for_order'] ) || ! is_checkout_pay_page() ) {
			return;
		}

		if ( $this->enabled === 'no' ) {
			return;
		}

		$order_key = urldecode( $_GET['key'] );
		$order_id  = absint( get_query_var( 'order-pay' ) );

		$order = wc_get_order( $order_id );

		if ( $this->id !== $order->get_payment_method() ) {
			return;
		}

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		//$suffix = '';

		$version = WC_SEERBIT_VERSION;
		//$version = '10.0.4';

		wp_enqueue_script( 'jquery' );

		wp_enqueue_script( 'seerbit', 'https://checkout.seerbitapi.com/api/v2/seerbit.js', array( 'jquery' ), WC_SEERBIT_VERSION, false );

		wp_enqueue_script( 'wc_seerbit', plugins_url( 'assets/js/seerbit' . $suffix . '.js', WC_SEERBIT_MAIN_FILE ), array( 'jquery', 'seerbit' ), $version, false );

		$seerbit_params = array(
			'public_key' => $this->public_key
		);

		if ( is_checkout_pay_page() && get_query_var( 'order-pay' ) ) {

			$email         = $order->get_billing_email();
			$first_name	   = $order->get_billing_first_name();
			$last_name	   = $order->get_billing_last_name();
			$customer_name = $first_name . ' ' . $last_name;
			$amount        = $order->get_total();
			$tranref        = 'Seerbit_'. $order_id . '_' . time();
			$payment_descr = 'Payment for Order Num #' . $order_id;
			$the_order_id  = $order->get_id();
			$the_order_key = $order->get_order_key();
			$currency      = $order->get_currency();
			$country	   = 'NG';

			if ( $the_order_id == $order_id && $the_order_key == $order_key ) {

				$seerbit_params['email'] = $email;
				$seerbit_params['currency'] = $currency;
				$seerbit_params['country'] = $country;
				$seerbit_params['tranref'] = $tranref;
				$seerbit_params['amount'] = $amount;
				$seerbit_params['description'] = $payment_descr;
				$seerbit_params['full_name'] = $customer_name;

			}

			if($this->split_payment && $this->split_code){
				$seerbit_params['split_code'] = $this->split_code;
			}

			if($this->tokenize_cards){
				$seerbit_params['tokenize'] = true;
			}

			$order->update_meta_data( '_seerbit_tranref', $tranref );
			$order->save();

		}

		//Create function to store retrieve payment methods from settings when needed
		$payment_methods = array('card');

		$seerbit_params['payment_methods'] = $payment_methods;

		wp_localize_script( 'wc_seerbit', 'wc_seerbit_params', $seerbit_params );

	}

    /**
	 * Load admin scripts.
	 */
	public function admin_scripts() {
        
        if ( 'woocommerce_page_wc-settings' !== get_current_screen()->id ) {
			return;
		}

        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

        $version = WC_SEERBIT_VERSION;
        

        $seerbit_admin_params = array(
			'plugin_url' => WC_SEERBIT_URL,
		);

        wp_enqueue_script( 'wc_seerbit_admin', plugins_url( 'assets/js/seerbit-admin' . $suffix . '.js', WC_SEERBIT_MAIN_FILE ), array(), $version, true );
        
        wp_localize_script( 'wc_seerbit_admin', 'wc_seerbit_admin_params', $seerbit_admin_params );
    }

	/**
	 * Process the payment.
	 *
	 * @param int $order_id
	 *
	 * @return array|void
	 */
	public function process_payment( $order_id ) {
		$payment_token = 'wc-' . trim( $this->id ) . '-payment-token';

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_POST[ $payment_token ] ) && 'new' !== wc_clean( $_POST[ $payment_token ] ) ) {

			// phpcs:ignore WordPress.Security.NonceVerification
			$token_id = wc_clean( $_POST[ $payment_token ] );
			$token    = \WC_Payment_Tokens::get( $token_id );

			if ( $token->get_user_id() !== get_current_user_id() ) {

				wc_add_notice( 'Invalid token ID', 'error' );

				return;
			}

			$token_payment_status = $this->process_token_payment( $token->get_token(), $order_id );
			
			if ( ! $token_payment_status ) {
				return;
			}

			$order = wc_get_order( $order_id );

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);

		}

		$order = wc_get_order( $order_id );

		$new_payment_method = 'wc-' . trim( $this->id ) . '-new-payment-method';

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_POST[ $new_payment_method ] ) && ( true === (bool) $_POST[ $new_payment_method ] && $this->saved_cards ) && is_user_logged_in() ) {

			$order->update_meta_data( '_wc_seerbit_save_card', true );

			$order->save();
		}

		if ( 'redirect' === $this->payment_page ) {
			return $this->process_redirect_payment_option( $order_id );
		}

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	/**
	 * Process a redirect payment option payment.
	 *
	 * @since 5.7
	 * @param int $order_id
	 * @return array|void
	 */
	public function process_redirect_payment_option( $order_id ) {
		
		$order        = wc_get_order( $order_id );
		$email         = $order->get_billing_email();
		$first_name	   = $order->get_billing_first_name();
		$last_name	   = $order->get_billing_last_name();
		$customer_name = $first_name . ' ' . $last_name;
		$amount        = $order->get_total();
		$tranref        = 'Seerbit_'. $order_id . '_' . time();
		$payment_descr = 'Payment for Order Num #' . $order_id;
		$currency      = $order->get_currency();
		$country 	   = 'NG';
		$callback_url = WC()->api_request_url( 'WC_Gateway_Seerbit' );

		$seerbit_params = array(
			'publicKey' => $this->public_key,
			'amount' => $amount,
			'email' => $email,
			'currency' => $currency,
			'country' => $country,
			'paymentReference' => $tranref,
			'description' => $payment_descr,
			'fullName' => $customer_name,
			'callbackUrl' => $callback_url
		);

		if($this->split_payment && $this->split_code){
			$seerbit_params['splitCode'] = $this->split_code;
		}

		if($this->tokenize_cards){
			$seerbit_params['tokenize'] = true;
		}

		//Create function to store retrieve payment methods from settings when needed
		$payment_methods = array('card');

		$seerbit_params['customization'] = array(
			'confetti' => false,
			'payment_method' => $payment_methods
		);

		$order->update_meta_data( '_seerbit_tranref', $tranref );
		$order->save();

		$seerbit_enc_key = $this->get_seerbit_encrypted_key($this->public_key, $this->secret_key);

		if(!$seerbit_enc_key){
			wc_add_notice( __( 'Unable to process payment, please contact support', 'woo-seerbit' ), 'error' );
			return;
		}

		$seerbit_url = 'https://seerbitapi.com/api/v2/payments';

		$headers = array(
			'Authorization' => 'Bearer ' . $seerbit_enc_key,
			'Content-Type'  => 'application/json'
		);

		$args = array(
			'headers' => $headers,
			'timeout' => 60,
			'body'    => json_encode( $seerbit_params ),
		);

		$request = wp_remote_post( $seerbit_url, $args );

		if ( ! is_wp_error( $request ) && 200 === wp_remote_retrieve_response_code( $request ) ) {

			$seerbit_response = json_decode( wp_remote_retrieve_body( $request ) );

			return array(
				'result'   => 'success',
				'redirect' => $seerbit_response->data->payments->redirectLink
			);

		}else{
			wc_add_notice( __( 'Unable to process payment, please contact support', 'woo-seerbit' ), 'error' );

			return;
		}

	}

	/**
	 * Retrieve encrypted key from seerbit.
	 *
	 * @since 5.7
	 * @param string $public_key $secret_key
	 * @return string|void
	 */
	public function get_seerbit_encrypted_key($public_key, $secret_key){
		$seerbit_enc_key = get_transient( 'wc_seerbit_enc_key' );

		if($seerbit_enc_key && $seerbit_enc_key !== false){

			return $seerbit_enc_key;
		}
		
		$api_url = 'https://seerbitapi.com/api/v2/encrypt/keys';

		$headers = array(
			'Content-Type'  => 'application/json'
		);

		$data = array(
			'key' => $secret_key . '.' . $public_key
		);

		$args = array(
			'headers' => $headers,
			'timeout' => 120,
			'body' => json_encode($data)
		);

		$request = wp_remote_post($api_url, $args);

		$seerbit_response = null;

		if ( ! is_wp_error( $request ) && 200 === wp_remote_retrieve_response_code( $request ) ) {

			$seerbit_response = json_decode( wp_remote_retrieve_body( $request ) );

		}

		if($seerbit_response && $seerbit_response->data->code == '00'){
			$seerbit_enc_key = $seerbit_response->data->EncryptedSecKey->encryptedKey;

			$expiration = time() + 86400;

			set_transient( 'wc_seerbit_enc_key', $seerbit_enc_key, $expiration );

			return $seerbit_enc_key;
		}else{
			return false;
		}

		

	}

	/**
	 * Process a token payment.
	 *
	 * @param $token
	 * @param $order_id
	 *
	 * @return bool
	 */
	public function process_token_payment( $token, $order_id ) {
		if ( $token && $order_id ) {
			$order = wc_get_order( $order_id );

			$order_amount = $order->get_total();
			$tranref = 'Seerbit_'. $order_id . '_r_' . time();
			$first_name	   = $order->get_billing_first_name();
			$last_name	   = $order->get_billing_last_name();
			$customer_name = $first_name . ' ' . $last_name;
			$payment_descr = 'Payment for Order Num #' . $order_id;
			$currency      = $order->get_currency();
			$country 	   = $order->get_billing_country();

			$order->update_meta_data( '_seerbit_tranref', $tranref );
			$order->save();

			$seerbit_enc_key = $this->get_seerbit_encrypted_key($this->public_key, $this->secret_key);

			$seerbit_url = 'https://seerbitapi.com/api/v2/payments/charge-token';

			$headers = array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $seerbit_enc_key,
			);

			if ( strpos( $token, '###' ) !== false ) {
				$payment_token  = explode( '###', $token );
				$auth_code      = $payment_token[0];
				$customer_email = $payment_token[1];
			} else {
				$auth_code      = $token;
				$customer_email = $order->get_billing_email();
			}

			$seerbit_params = array(
				'publicKey' => $this->public_key,
				'amount' => $order_amount,
				'email' => $customer_email,
				'currency' => $currency,
				'country' => $country,
				'paymentReference' => $tranref,
				'description' => $payment_descr,
				'fullName' => $customer_name,
				'authorizationCode' => $auth_code
			);

			$args = array(
				'headers' => $headers,
				'timeout' => 120,
				'body' => json_encode($seerbit_params)
			);

			$request = wp_remote_post($seerbit_url, $args);

			$response_code = wp_remote_retrieve_response_code( $request );

			if ( ! is_wp_error( $request ) && $response_code ===200 ) {
				$seerbit_response = json_decode( wp_remote_retrieve_body( $request ) );

				if(strtolower($seerbit_response->data->message) == 'successful'){

					$seerbit_transaction = $this->get_seerbit_transaction($seerbit_response->data->payments->paymentReference);
					
					if($seerbit_transaction !== false){
						
						$order = wc_get_order( $order_id );

						if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ) ) ) {

							wp_redirect( $this->get_return_url( $order ) );

							exit;

						}

						$order_total      = $order->get_total();
						$order_currency   = $order->get_currency();
						$currency_symbol  = get_woocommerce_currency_symbol( $order_currency );
						$amount_paid 	  = $seerbit_transaction->data->payments->amount;
						$seerbit_ref 	  = $seerbit_transaction->data->payments->paymentReference;
						$payment_currency = strtoupper( $seerbit_transaction->data->payments->currency);
						$gateway_symbol   = get_woocommerce_currency_symbol( $payment_currency );

						//Check if amount paid is equal to order amount.
						if ( $amount_paid < absint($order_total)){

							$order->update_status( 'on-hold', '' );

							$order->add_meta_data( '_transaction_id', $seerbit_ref, true );

							$notice      = sprintf( __( 'Thank you for your payment.%1$sYour payment transaction was successful, but the amount paid is not the same as the total order amount.%2$sYour order is currently on hold.%3$sKindly contact us for more information regarding your order and payment status.', 'woo-seerbit' ), '<br />', '<br />', '<br />' );
							$notice_type = 'notice';

							// Add Customer Order Note
							$order->add_order_note( $notice, 1 );

							// Add Admin Order Note
							$admin_order_note = sprintf( __( '<strong>Look into this order</strong>%1$sThis order is currently on hold.%2$sReason: Amount paid is less than the total order amount.%3$sAmount Paid was <strong>%4$s (%5$s)</strong> while the total order amount is <strong>%6$s (%7$s)</strong>%8$s<strong>Seerbit Transaction Reference:</strong> %9$s', 'woo-seerbit' ), '<br />', '<br />', '<br />', $currency_symbol, $amount_paid, $currency_symbol, $order_total, '<br />', $seerbit_ref );
							$order->add_order_note( $admin_order_note );

							function_exists( 'wc_reduce_stock_levels' ) ? wc_reduce_stock_levels( $order_id ) : $order->reduce_order_stock();

							wc_add_notice( $notice, $notice_type );

						}else{

							if ( $payment_currency !== $order_currency ) {

								$order->update_status( 'on-hold', '' );

								$order->update_meta_data( '_transaction_id', $seerbit_ref );

								$notice      = sprintf( __( 'Thank you for your payment.%1$sYour payment was successful, but the payment currency is different from the order currency.%2$sYour order is currently on-hold.%3$sKindly contact us for more information regarding your order and payment status.', 'woo-seerbit' ), '<br />', '<br />', '<br />' );
								$notice_type = 'notice';

								// Add Customer Order Note
								$order->add_order_note( $notice, 1 );

								// Add Admin Order Note
								$admin_order_note = sprintf( __( '<strong>Look into this order</strong>%1$sThis order is currently on hold.%2$sReason: Order currency is different from the payment currency.%3$sOrder Currency is <strong>%4$s (%5$s)</strong> while the payment currency is <strong>%6$s (%7$s)</strong>%8$s<strong>Seerbit Transaction Reference:</strong> %9$s', 'woo-seerbit' ), '<br />', '<br />', '<br />', $order_currency, $currency_symbol, $payment_currency, $gateway_symbol, '<br />', $seerbit_ref );
								$order->add_order_note( $admin_order_note );

								function_exists( 'wc_reduce_stock_levels' ) ? wc_reduce_stock_levels( $order_id ) : $order->reduce_order_stock();

								wc_add_notice( $notice, $notice_type );

							}else{

								$order->payment_complete( $seerbit_ref );
								$order->add_order_note( sprintf( __( 'Payment via Seerbit successful (Transaction Reference: %s)', 'woo-seerbit' ), $seerbit_ref ) );

								if ( $this->is_autocomplete_order_enabled( $order ) ) {
									$order->update_status( 'completed' );
								}

							}

						}

						$order->save();

						$this->save_subscription_renewal_payment_token($order_id, $token);

						WC()->cart->empty_cart();

						return true;

					}else{

						wc_add_notice( __( 'Payment Failed.', 'woo-seerbit' ), 'error' );

						return false;

					}
					

				}else{

					$order_notice  = __( 'Payment was declined by Seerbit.', 'woo-seerbit' );
					$failed_notice = __( 'Payment failed using the saved card. Kindly use another payment option.', 'woo-seerbit' );

					if ( ! empty( $seerbit_response->data->message ) ) {

						$order_notice  = sprintf( __( 'Payment was declined by Seerbit. Reason: %s.', 'woo-seerbit' ), $seerbit_response->data->message );
						$failed_notice = sprintf( __( 'Payment failed using the saved card. Reason: %s. Kindly use another payment option.', 'woo-seerbit' ), $seerbit_response->data->message );

					}

					$order->update_status( 'failed', $order_notice );

					wc_add_notice( $failed_notice, 'error' );

					do_action( 'wc_gateway_seerbit_process_payment_error', $failed_notice, $order );

					return false;

				}
			}else{
				
				wc_add_notice( __( 'Payment Failed.', 'woo-seerbit' ), 'error' );

				return false;
			}
		}else{

			wc_add_notice( __( 'Payment Failed.', 'woo-seerbit' ), 'error' );

			return false;

		}
	}

	/**
	 * Show new card can only be added when placing an order notice.
	 */
	public function add_payment_method() {
		wc_add_notice( __( 'You can only add a new card when placing an order.', 'woo-seerbit' ), 'error' );

		return;
	}

	/**
	 * Displays the payment page.
	 *
	 * @param $order_id
	 */
	public function receipt_page( $order_id ) {

		$order = wc_get_order( $order_id );

		echo '<div id="wc-seerbit-form">';

		echo '<p>' . __( 'Thank you for your order, please click the button below to pay with Seerbit.', 'woo-seerbit' ) . '</p>';

		echo '<div id="seerbit_form"><form id="order_review" method="post" action="' . WC()->api_request_url( 'WC_Gateway_Seerbit' ) . '"></form><button class="button" id="seerbit-payment-button">' . __( 'Pay Now', 'woo-seerbit' ) . '</button>';

		if ( ! $this->remove_cancel_order_button ) {
			echo '  <a class="button cancel" id="seerbit-cancel-payment-button" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancel order &amp; restore cart', 'woo-seerbit' ) . '</a></div>';
		}

		echo '</div>';

	}

	/**
	 * Display message on thank you page.
	 */
	public function thankyou_page( $order_id ){

		$order = wc_get_order( $order_id );

		if($order->get_status() == 'failed'){
			if ($this->order_failed_message){
				$ptext = wpautop(wptexturize($this->order_failed_message));
				$ptext = str_replace('<p>', '<p style="font-size: 1rem; font-weight: 500; color: red;">', $ptext);
				echo wp_kses_post($ptext);
				return;
			}
			return;
		}

		if($order->get_status() == 'completed' || $order->get_status() == 'processing'){
			if ($this->order_complete_message){
				echo wp_kses_post(wpautop(wptexturize($this->order_complete_message)));
				return;
			}
			return;
		}

	}

	/**
	 * Verify Seerbit payment.
	 */
	public function verify_seerbit_transaction() {

		if ( isset( $_REQUEST['seerbit_tranref'] ) ) {
			$seerbit_tranref = sanitize_text_field($_REQUEST['seerbit_tranref']);
		}elseif(isset( $_REQUEST['reference'])){
			$seerbit_tranref = sanitize_text_field($_REQUEST['reference']);
		}else{
			$seerbit_tranref = false;
		}

		@ob_clean();

		if($seerbit_tranref){
			$seerbit_response = $this->get_seerbit_transaction($seerbit_tranref);

			if($seerbit_response !== false){
				
				if((strtolower($seerbit_response->data->message) == 'successful' || strtolower($seerbit_response->data->message) == 'approved') && $seerbit_response->data->code == '00'){
					$order_details = explode('_', $seerbit_response->data->payments->paymentReference);
					$order_id = (int) $order_details[1];
					$order = wc_get_order( $order_id );

					if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ) ) ) {

						wp_redirect( $this->get_return_url( $order ) );

						exit;

					}

					$order_total      = $order->get_total();
					$order_currency   = $order->get_currency();
					$currency_symbol  = get_woocommerce_currency_symbol( $order_currency );
					$amount_paid 	  = $seerbit_response->data->payments->amount;
					$seerbit_ref 	  = $seerbit_response->data->payments->paymentReference;
					$payment_currency = strtoupper( $seerbit_response->data->payments->currency);
					$gateway_symbol   = get_woocommerce_currency_symbol( $payment_currency );

					//Check if amount paid is equal to order amount.
					if ( $amount_paid < absint($order_total)){

						$order->update_status( 'on-hold', '' );

						$order->add_meta_data( '_transaction_id', $seerbit_ref, true );

						$notice      = sprintf( __( 'Thank you for your payment.%1$sYour payment transaction was successful, but the amount paid is not the same as the total order amount.%2$sYour order is currently on hold.%3$sKindly contact us for more information regarding your order and payment status.', 'woo-seerbit' ), '<br />', '<br />', '<br />' );
						$notice_type = 'notice';

						// Add Customer Order Note
						$order->add_order_note( $notice, 1 );

						// Add Admin Order Note
						$admin_order_note = sprintf( __( '<strong>Look into this order</strong>%1$sThis order is currently on hold.%2$sReason: Amount paid is less than the total order amount.%3$sAmount Paid was <strong>%4$s (%5$s)</strong> while the total order amount is <strong>%6$s (%7$s)</strong>%8$s<strong>Seerbit Transaction Reference:</strong> %9$s', 'woo-seerbit' ), '<br />', '<br />', '<br />', $currency_symbol, $amount_paid, $currency_symbol, $order_total, '<br />', $seerbit_ref );
						$order->add_order_note( $admin_order_note );

						function_exists( 'wc_reduce_stock_levels' ) ? wc_reduce_stock_levels( $order_id ) : $order->reduce_order_stock();

						wc_add_notice( $notice, $notice_type );

					}else{

						if ( $payment_currency !== $order_currency ) {

							$order->update_status( 'on-hold', '' );

							$order->update_meta_data( '_transaction_id', $seerbit_ref );

							$notice      = sprintf( __( 'Thank you for your payment.%1$sYour payment was successful, but the payment currency is different from the order currency.%2$sYour order is currently on-hold.%3$sKindly contact us for more information regarding your order and payment status.', 'woo-seerbit' ), '<br />', '<br />', '<br />' );
							$notice_type = 'notice';

							// Add Customer Order Note
							$order->add_order_note( $notice, 1 );

							// Add Admin Order Note
							$admin_order_note = sprintf( __( '<strong>Look into this order</strong>%1$sThis order is currently on hold.%2$sReason: Order currency is different from the payment currency.%3$sOrder Currency is <strong>%4$s (%5$s)</strong> while the payment currency is <strong>%6$s (%7$s)</strong>%8$s<strong>Seerbit Transaction Reference:</strong> %9$s', 'woo-seerbit' ), '<br />', '<br />', '<br />', $order_currency, $currency_symbol, $payment_currency, $gateway_symbol, '<br />', $seerbit_ref );
							$order->add_order_note( $admin_order_note );

							function_exists( 'wc_reduce_stock_levels' ) ? wc_reduce_stock_levels( $order_id ) : $order->reduce_order_stock();

							wc_add_notice( $notice, $notice_type );

						}else{

							$order->payment_complete( $seerbit_ref );
							$order->add_order_note( sprintf( __( 'Payment via Seerbit successful (Transaction Reference: %s)', 'woo-seerbit' ), $seerbit_ref ) );

							if ( $this->is_autocomplete_order_enabled( $order ) ) {
								$order->update_status( 'completed' );
							}

						}

					}

					$order->save();

					$this->save_card_details( $seerbit_response, $order->get_user_id(), $order_id );

					WC()->cart->empty_cart();

				}else{

					$order_details = explode( '_', $seerbit_tranref );

					$order_id = (int) $order_details[1];

					$order = wc_get_order( $order_id );

					$order->update_status( 'failed', __( 'Seerbit payment was declined.', 'woo-seerbit' ) );

				}

			}

			wp_redirect( $this->get_return_url( $order ) );

			exit;
			
		}
		
		wp_redirect( wc_get_page_permalink( 'cart' ) );

		exit;

	}

	/**
	 * Process Webhook.
	 */
	public function process_webhooks() {
		
		if ( ! array_key_exists( 'HTTP_X_SEERBIT_SIGNATURE', $_SERVER ) || ( strtoupper( $_SERVER['REQUEST_METHOD'] ) !== 'POST' ) ) {
			exit;
		}

		$json = file_get_contents( 'php://input' );

		//$seerbit_enc_key = $this->get_seerbit_encrypted_key($this->public_key, $this->secret_key);

		// validate event do all at once to avoid timing attack.
		// if ( $_SERVER['HTTP_X_SEERBIT_SIGNATURE'] !== hash_hmac( 'sha512', $json, $this->secret_key ) ) {
		// 	exit;
		// }

		$event = json_decode( $json );

		$notification = $event->notificationItems[0]->notificationRequestItem;

		if ($notification->data->code != '00' && (strtolower($notification->data->gatewayMessage) !== 'successful' || strtolower($notification->data->gatewayMessage) !== 'approved' )){
			return;
		}

		sleep( 10 );

		$seerbit_transaction = $this->get_seerbit_transaction($notification->data->reference);

		if ( false === $seerbit_transaction ) {
			return;
		}

		$order_details = explode('_', $seerbit_transaction->data->payments->paymentReference);

		$order_id = (int) $order_details[0];

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$seerbit_tranref = $order->get_meta( '_seerbit_tranref' );

		if ($seerbit_transaction->data->payments->paymentReference != $seerbit_tranref){
			exit;
		}

		error_log(print_r($seerbit_tranref, true));


		http_response_code( 200 );

		if ( in_array( strtolower( $order->get_status() ), array( 'processing', 'completed', 'on-hold' ), true ) ) {
			exit;
		}

		$order_total      = $order->get_total();
		$order_currency   = $order->get_currency();
		$currency_symbol  = get_woocommerce_currency_symbol( $order_currency );
		$amount_paid 	  = $seerbit_transaction->data->payments->amount;
		$seerbit_ref 	  = $seerbit_transaction->data->payments->paymentReference;
		$payment_currency = strtoupper( $seerbit_transaction->data->payments->currency);
		$gateway_symbol   = get_woocommerce_currency_symbol( $payment_currency );

		//Check if amount paid is equal to order amount.
		if ( $amount_paid < absint($order_total)){
			$order->update_status( 'on-hold', '' );

			$order->add_meta_data( '_transaction_id', $seerbit_ref, true );

			$notice      = sprintf( __( 'Thank you for your payment.%1$sYour payment transaction was successful, but the amount paid is not the same as the total order amount.%2$sYour order is currently on hold.%3$sKindly contact us for more information regarding your order and payment status.', 'woo-seerbit' ), '<br />', '<br />', '<br />' );
			$notice_type = 'notice';

			// Add Customer Order Note
			$order->add_order_note( $notice, 1 );

			// Add Admin Order Note
			$admin_order_note = sprintf( __( '<strong>Look into this order</strong>%1$sThis order is currently on hold.%2$sReason: Amount paid is less than the total order amount.%3$sAmount Paid was <strong>%4$s (%5$s)</strong> while the total order amount is <strong>%6$s (%7$s)</strong>%8$s<strong>Seerbit Transaction Reference:</strong> %9$s', 'woo-seerbit' ), '<br />', '<br />', '<br />', $currency_symbol, $amount_paid, $currency_symbol, $order_total, '<br />', $seerbit_ref );
			$order->add_order_note( $admin_order_note );

			function_exists( 'wc_reduce_stock_levels' ) ? wc_reduce_stock_levels( $order_id ) : $order->reduce_order_stock();

			wc_add_notice( $notice, $notice_type );
		}else{
			if ( $payment_currency !== $order_currency ) {

				$order->update_status( 'on-hold', '' );

				$order->update_meta_data( '_transaction_id', $seerbit_ref );

				$notice      = sprintf( __( 'Thank you for your payment.%1$sYour payment was successful, but the payment currency is different from the order currency.%2$sYour order is currently on-hold.%3$sKindly contact us for more information regarding your order and payment status.', 'woo-seerbit' ), '<br />', '<br />', '<br />' );
				$notice_type = 'notice';

				// Add Customer Order Note
				$order->add_order_note( $notice, 1 );

				// Add Admin Order Note
				$admin_order_note = sprintf( __( '<strong>Look into this order</strong>%1$sThis order is currently on hold.%2$sReason: Order currency is different from the payment currency.%3$sOrder Currency is <strong>%4$s (%5$s)</strong> while the payment currency is <strong>%6$s (%7$s)</strong>%8$s<strong>Seerbit Transaction Reference:</strong> %9$s', 'woo-seerbit' ), '<br />', '<br />', '<br />', $order_currency, $currency_symbol, $payment_currency, $gateway_symbol, '<br />', $seerbit_ref );
				$order->add_order_note( $admin_order_note );

				function_exists( 'wc_reduce_stock_levels' ) ? wc_reduce_stock_levels( $order_id ) : $order->reduce_order_stock();

				wc_add_notice( $notice, $notice_type );

			}else{
				$order->payment_complete( $seerbit_ref );
				$order->add_order_note( sprintf( __( 'Payment via Seerbit successful (Transaction Reference: %s)', 'woo-seerbit' ), $seerbit_ref ) );

				if ( $this->is_autocomplete_order_enabled( $order ) ) {
					$order->update_status( 'completed' );
				}

			}
		}
		$order->save();

		$this->save_card_details( $seerbit_transaction, $order->get_user_id(), $order_id );

		WC()->cart->empty_cart();

		exit;
	}

	/**
	 * Save Customer Card Details.
	 *
	 * @param $seerbit_response
	 * @param $user_id
	 * @param $order_id
	 */
	public function save_card_details( $seerbit_response, $user_id, $order_id ) {
		
		$this->save_subscription_payment_token( $order_id, $seerbit_response );

		$order = wc_get_order( $order_id );

		$save_card = $order->get_meta( '_wc_seerbit_save_card' );

		if ( $user_id && $this->saved_cards && $save_card && $seerbit_response->data->payments->authorizationCode && 'card' == strtolower($seerbit_response->data->payments->paymentType) ) {

			$gateway_id = $order->get_payment_method();

			$last4          = $seerbit_response->data->payments->lastFourDigits;
			$exp_year       = $seerbit_response->data->payments->expiryYear;
			$brand          = $seerbit_response->data->payments->cardType;
			$exp_month      = $seerbit_response->data->payments->expiryMonth;
			$auth_code      = $seerbit_response->data->payments->authorizationCode;
			$customer_email = $seerbit_response->data->customers->customerEmail;

			$payment_token = "$auth_code###$customer_email";

			$token = new WC_Payment_Token_CC();
			$token->set_token( $payment_token );
			$token->set_gateway_id( $gateway_id );
			$token->set_card_type( strtolower( $brand ) );
			$token->set_last4( $last4 );
			$token->set_expiry_month( $exp_month );
			$token->set_expiry_year( $exp_year );
			$token->set_user_id( $user_id );
			error_log(print_r($token, true));

			$token->save();

			$order->delete_meta_data( '_wc_seerbit_save_card' );
			$order->save();

		}
		
	}

	/**
	 * Save payment token to the order for automatic renewal for further subscription payment.
	 *
	 * @param $order_id
	 * @param $seerbit_response
	 */
	public function save_subscription_payment_token( $order_id, $seerbit_response ) {

		if ( ! function_exists( 'wcs_order_contains_subscription' ) ) {
			return;
		}

		if ( $this->order_contains_subscription( $order_id ) && $seerbit_response->data->payments->authorizationCode && 'card' == strtolower($seerbit_response->data->payments->paymentType) ){

			$auth_code = $seerbit_response->data->payments->authorizationCode;
			$customer_email = $seerbit_response->data->customers->customerEmail;

			$payment_token = "$auth_code###$customer_email";

			// Also store it on the subscriptions being purchased or paid for in the order
			if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {

				$subscriptions = wcs_get_subscriptions_for_order( $order_id );

			} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {

				$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );

			} else {

				$subscriptions = array();

			}

			if ( empty( $subscriptions ) ) {
				return;
			}

			foreach ( $subscriptions as $subscription ) {
				$subscription->update_meta_data( '_seerbit_token', $payment_token );
				$subscription->save();
			}

		}

	}

	/**
	 * Save payment token to the order after a new renewal order has been made.
	 *
	 * @param $order_id
	 * @param $token
	 */
	public function save_subscription_renewal_payment_token( $order_id, $token ) {

		if ( ! function_exists( 'wcs_order_contains_subscription' ) ) {
			return;
		}

		if ( $this->order_contains_subscription( $order_id ) && $token ){
			// Also store it on the subscriptions being purchased or paid for in the order
			if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {

				$subscriptions = wcs_get_subscriptions_for_order( $order_id );

			} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {

				$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );

			} else {

				$subscriptions = array();

			}

			if ( empty( $subscriptions ) ) {
				return;
			}

			foreach ( $subscriptions as $subscription ) {
				$subscription->update_meta_data( '_seerbit_token', $token );
				$subscription->save();
			}
		}

	}
	
	/**
	 * Checks if WC version is less than passed in version.
	 *
	 * @param string $version Version to check against.
	 *
	 * @return bool
	 */
	public function is_wc_lt( $version ) {
		//return version_compare( WC_VERSION, $version, '<' );
	}

	/**
	 * Checks if autocomplete order is enabled for the payment method.
	 *
	 * @since 5.7
	 * @param WC_Order $order Order object.
	 * @return bool
	 */
	protected function is_autocomplete_order_enabled( $order ) {

		$autocomplete_order = false;

		$payment_method = $order->get_payment_method();

		$seerbit_settings = get_option('woocommerce_' . $payment_method . '_settings');

		if ( isset( $seerbit_settings['autocomplete_order'] ) && 'yes' === $seerbit_settings['autocomplete_order'] ) {
			$autocomplete_order = true;
		}

		return $autocomplete_order;

	}

	/**
	 * Retrieve a transaction from Seerbit.
	 *
	 * @since 5.7.5
	 * @param $seerbit_tranref
	 * @return false|mixed
	 */
	private function get_seerbit_transaction( $seerbit_tranref ) {

		$seerbit_url = 'https://seerbitapi.com/api/v3/payments/query/' . $seerbit_tranref;

		$seerbit_enc_key = $this->get_seerbit_encrypted_key($this->public_key, $this->secret_key);

		if(!$seerbit_enc_key){
			return false;
		}

		$headers = array(
			'Authorization' => 'Bearer ' . $seerbit_enc_key,
		);

		$args = array(
			'headers' => $headers,
			'timeout' => 120,
		);

		$request = wp_remote_get( $seerbit_url, $args );

		if ( ! is_wp_error( $request ) && 200 === wp_remote_retrieve_response_code( $request ) ) {
			return json_decode( wp_remote_retrieve_body( $request ) );
		}

		return false;

	}

	/**
	 * Get Seerbit payment icon URL.
	 */
	public function get_logo_url() {
		
		$url = WC_HTTPS::force_https_url( plugins_url( 'assets/images/seerbit-logo.png', WC_SEERBIT_MAIN_FILE ) );

		return apply_filters( 'wc_seerbit_gateway_icon_url', $url, $this->id );

	}

	/**
	 * Check if an order contains a subscription.
	 *
	 * @param int $order_id WC Order ID.
	 *
	 * @return bool
	 */
	public function order_contains_subscription( $order_id ) {
		return function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) );
	}
}