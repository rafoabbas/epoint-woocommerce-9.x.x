<?php

/*
Plugin Name: Epoint WooCommerce Payment Gateway
Plugin URI: https://epoint.az/
Description: Epoint Payment Gateway for Woocommerce
Version: 1.0.0
Author: Rauf Abbaszade <rafo.abbas@gmail.com>
Author URI: https://abbaszade.dev/
*/

use Automattic\WooCommerce\Utilities\FeaturesUtil;

define('EPOINT_PATH', untrailingslashit(plugin_dir_path(__FILE__)));


class WC_Epoint {

    /**
     * Constructor
     */
    public function __construct()
    {
        define( 'WC_EPOINT_VERSION', '2.0.0' );
        define( 'WC_EPOINT_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
        define( 'WC_EPOINT_PLUGIN_DIR', plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) . '/' );
        define( 'WC_EPOINT_MAIN_FILE', __FILE__ );

        // Actions
        add_action( 'plugins_loaded', array( $this, 'init' ), 0 );
        add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );


        add_action('woocommerce_blocks_loaded', array($this, 'epoint_woocommerce_blocks_support'));
        add_action('before_woocommerce_init', array($this, 'epoint_cart_checkout_blocks_compatibility'));
    }

    /**
     * Init localisations and files
     */
    public function init(): void
    {

        if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
            return;
        }

        // Includes
        include_once( 'includes/class-epoint-gateway-woocommerce.php' );
        include_once( 'reversal.php' );
    }

    /**
     * Register the gateway for use
     */
    public function register_gateway( $methods ) {

        $methods[] = 'WC_Gateway_Epoint';
        return $methods;

    }

    public function epoint_cart_checkout_blocks_compatibility()
    {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
        }
    }

    public function epoint_woocommerce_blocks_support()
    {
        if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            return;
        }
        include_once EPOINT_PATH . '/includes/class-epoint-block-gateway.php';

        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                $payment_method_registry->register(new Epoint_WooCommerce_Blocks);
            }
        );
    }
}

new WC_Epoint();
