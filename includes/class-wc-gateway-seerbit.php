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

        $icon = '<img src="' . WC_HTTPS::force_https_url( plugins_url( 'assets/images/seerbit-logo.png', WC_SEERBIT_MAIN_FILE ) ) . '" alt="Seerbit Payment Options" />';

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
        error_log(print_r("This Fires", true));
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

		//$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		$suffix = '';

		//$version = WC_SEERBIT_VERSION;
		$version = '10.0.0';

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
			$tranref        = 'Seebit_'. $order_id . '_' . time();
			$site_name     = get_option( 'blogname' );
			$payment_descr = 'Payment for ' . $site_name . ' #' . $order_id;
			$the_order_id  = $order->get_id();
			$the_order_key = $order->get_order_key();
			$currency      = $order->get_currency();

			if ( $the_order_id == $order_id && $the_order_key == $order_key ) {

				$seerbit_params['email'] = $email;
				$seerbit_params['currency'] = $currency;
				$seerbit_params['tranref'] = $tranref;
				$seerbit_params['amount'] = $amount;
				$seerbit_params['description'] = $payment_descr;
				$seerbit_params['full_name'] = $customer_name;

			}

			if($this->split_payment){
				$seerbit_params['splitCode'] = $this->split_code;
			}

			if($this->tokenize_cards){
				$seerbit_params['tokenize'] = true;
			}

			$order->update_meta_data( '_seerbit_tranref', $tranref );
			$order->save();

		}

		$payment_channels = array('card');

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
}