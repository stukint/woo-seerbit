<?php
/**
 * Plugin Name: Seerbit WooCommerce Payment Gateway
 * Plugin URI: https://www.seerbit.com
 * Description: WooCommerce payment gateway for Seerbit
 * Version: 1.0.0
 * Author: Netsave Technologies
 * Author URI: https://www.netsavetech.com.ng
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires Plugins: woocommerce
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 9.1
 */


 use Automattic\WooCommerce\Admin\Notes\Note;
 use Automattic\WooCommerce\Admin\Notes\Notes;
 
 if ( ! defined( 'ABSPATH' ) ) {
     exit;
 }

 define( 'WC_SEERBIT_MAIN_FILE', __FILE__ );
 define( 'WC_SEERBIT_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );

 define( 'WC_SEERBIT_VERSION', '1.0.0' );

 /**
 * Initialize Seerbit WooCommerce payment gateway.
 */
function nts_wc_seerbit_init() {

    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		add_action( 'admin_notices', 'nts_wc_seerbit_wc_missing_notice' );
		return;
	}

    add_action( 'admin_init', 'nts_wc_seerbit_testmode_notice' );

    require_once __DIR__ . '/includes/class-wc-gateway-seerbit.php';

    require_once __DIR__ . '/includes/class-wc-gateway-seerbit-subscriptions.php';

    add_filter( 'woocommerce_payment_gateways', 'nts_wc_add_seerbit_gateway', 99 );

    add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'nts_woo_seerbit_plugin_action_links' );

}
add_action( 'plugins_loaded', 'nts_wc_seerbit_init', 99 );

/**
 * Add Settings link to the plugin entry in the plugins menu.
 *
 * @param array $links Plugin action links.
 *
 * @return array
 **/
function nts_woo_seerbit_plugin_action_links( $links ) {

    $settings_link = array(
		'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=seerbit' ) . '" title="' . __( 'View Seerbit WooCommerce Settings', 'woo-seerbit' ) . '">' . __( 'Settings', 'woo-seerbit' ) . '</a>',
	);

	return array_merge( $settings_link, $links );

}


/**
 * Add Seerbit Gateway to WooCommerce.
 *
 * @param array $methods WooCommerce payment gateways methods.
 *
 * @return array
 */
function nts_wc_add_seerbit_gateway( $methods ) {

    if ( class_exists( 'WC_Subscriptions_Order' ) && class_exists( 'WC_Payment_Gateway_CC' ) ) {
		$methods[] = 'WC_Gateway_Seerbit_Subscriptions';
	} else {
		$methods[] = 'WC_Gateway_Seerbit';
	}

    return $methods;

}


/**
 * Display a notice if WooCommerce is not installed
 */
function nts_wc_seerbit_wc_missing_notice() {
    echo '<div class="error"><p><strong>' . sprintf( __( 'Seerbit requires WooCommerce to be installed and active. Click %s to install WooCommerce.', 'woo-seerbit' ), '<a href="' . admin_url( 'plugin-install.php?tab=plugin-information&plugin=woocommerce&TB_iframe=true&width=772&height=539' ) . '" class="thickbox open-plugin-details-modal">here</a>' ) . '</strong></p></div>';
}

/**
 * Display the test mode notice.
 **/
function nts_wc_seerbit_testmode_notice() {

    if ( ! class_exists( Notes::class ) ) {
		return;
	}

	if ( ! class_exists( WC_Data_Store::class ) ) {
		return;
	}

	if ( ! method_exists( Notes::class, 'get_note_by_name' ) ) {
		return;
	}

	$test_mode_note = Notes::get_note_by_name( 'seerbit-test-mode' );

    if ( false !== $test_mode_note ) {
		return;
	}

    $seerbit_settings = get_option( 'woocommerce_seerbit_settings' );
    $test_mode         = $seerbit_settings['testmode'] ?? '';

    if ( 'yes' !== $test_mode ) {
		Notes::delete_notes_with_name( 'seerbit-test-mode' );

		return;
	}

    $note = new Note();
	$note->set_title( __( 'Seerbit test mode enabled', 'woo-seerbit' ) );
	$note->set_content( __( 'Seerbit test mode is currently enabled. Remember to disable it when you want to start accepting live payment on your site.', 'woo-seerbit' ) );
	$note->set_type( Note::E_WC_ADMIN_NOTE_INFORMATIONAL );
	$note->set_layout( 'plain' );
	$note->set_is_snoozable( false );
	$note->set_name( 'seerbit-test-mode' );
	$note->set_source( 'woo-seerbit' );
	$note->add_action( 'disable-seerbit-test-mode', __( 'Disable Seerbit test mode', 'woo-seerbit' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=seerbit' ) );
	$note->save();

}

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Registers WooCommerce Blocks integration.
 */
function nts_wc_gateway_seerbit_woocommerce_block_support() {
    if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        require_once __DIR__ . '/includes/class-wc-gateway-seerbit-blocks-support.php';
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            static function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ){
                $payment_method_registry->register( new WC_Gateway_Seerbit_Blocks_Support() );
            }
        );
    }
}
add_action( 'woocommerce_blocks_loaded', 'nts_wc_gateway_seerbit_woocommerce_block_support' );