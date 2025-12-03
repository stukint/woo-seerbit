<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Gateway_Seerbit_Subscriptions
 */
class WC_Gateway_Seerbit_Subscriptions extends WC_Gateway_Seerbit {

    /**
	 * Constructor
	 */
	public function __construct() {
        
		parent::__construct();

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {

			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );

		}
    }

	/**
	 * Process a trial subscription order with 0 total.
	 *
	 * @param int $order_id WC Order ID.
	 *
	 * @return array|void
	 */
	public function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );

		// Check for trial subscription order with 0 total.
		if ( $this->order_contains_subscription( $order ) && $order->get_total() == 0 ) {

			$order->payment_complete();

			$order->add_order_note( __( 'This subscription has a free trial, reason for the 0 amount', 'woo-seerbit' ) );

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);

		}else{

			return parent::process_payment( $order_id );

		}

	}

	/**
	 * Process a subscription renewal.
	 *
	 * @param float    $amount_to_charge Subscription payment amount.
	 * @param WC_Order $renewal_order Renewal Order.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {

		$response = $this->process_subscription_payment( $renewal_order, $amount_to_charge );

		if ( is_wp_error( $response ) ) {

			$renewal_order->update_status( 'failed', sprintf( __( 'Seerbit Transaction Failed (%s)', 'woo-seerbit' ), $response->get_error_message() ) );

		}

	}

	/**
	 * Process a subscription renewal payment.
	 *
	 * @param WC_Order $order  Subscription renewal order.
	 * @param float    $amount Subscription payment amount.
	 *
	 * @return bool|WP_Error
	 */
	public function process_subscription_payment( $order, $amount ) {

		$order_id = $order->get_id();

		$seerbit_token = $order->get_meta( '_seerbit_token' );

		if ( ! empty( $seerbit_token ) ) {

			$order_amount = $amount;
			$tranref = 'Seerbit_'. $order_id . '_r_' . time();
			$first_name	   = $order->get_billing_first_name();
			$last_name	   = $order->get_billing_last_name();
			$customer_name = $first_name . ' ' . $last_name;
			$payment_descr = 'Payment for Order Num #' . $order_id;
			$currency      = $order->get_currency();
			$country 	   = 'NG';

			$order->update_meta_data( '_seerbit_tranref', $tranref );
			$order->save();

			$seerbit_enc_key = $this->get_seerbit_encrypted_key($this->public_key, $this->secret_key);

			$seerbit_url = 'https://seerbitapi.com/api/v2/payments/charge-token';

			$headers = array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $seerbit_enc_key,
			);

			if ( strpos( $seerbit_token, '###' ) !== false ) {
				$payment_token  = explode( '###', $seerbit_token );
				$auth_code      = $payment_token[0];
				$customer_email = $payment_token[1];
			} else {
				$auth_code      = $seerbit_token;
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

				if(strtolower($seerbit_response->data->message) == 'successful' || strtolower($seerbit_response->data->message) == 'approved'){

					$seerbit_tranref = $seerbit_response->data->payments->paymentReference;

					$order->payment_complete( $seerbit_tranref );

					$message = sprintf( __( 'Payment via Seerbit successful (Transaction Reference: %s)', 'woo-seerbit' ), $seerbit_tranref );

					$order->add_order_note( $message );

					if ( parent::is_autocomplete_order_enabled( $order ) ) {
						$order->update_status( 'completed' );
					}

					return true;

				}else{

					$gateway_response = __( 'Seerbit payment failed.', 'woo-seerbit' );

					if ( isset( $seerbit_response->data->message ) && ! empty( $seerbit_response->data->message ) ) {
						$gateway_response = sprintf( __( 'Seerbit payment failed. Reason: %s', 'woo-seerbit' ), $seerbit_response->data->message );
					}

					return new WP_Error( 'seerbit_error', $gateway_response );

				}

			}

			return new WP_Error( 'seerbit_error', __( 'This subscription can&#39;t be renewed automatically. The customer will have to login to their account to renew their subscription', 'woo-seerbit' ) );
		}

		return new WP_Error( 'seerbit_error', __( 'This subscription can&#39;t be renewed automatically. The customer will have to login to their account to renew their subscription', 'woo-seerbit' ) );

	}

}