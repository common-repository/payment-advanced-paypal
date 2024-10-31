<?php
/*
Plugin Name: Payment Advanced - PayPal
Plugin URI: https://www.presto-changeo.com/woocommerce-plugins-free-modules/152-prestashop-paypal-advanced-module.html
Description: Receive payments using PayPal Advanced
Author: Presto-Changeo
Version: 1.0.0
Author URI: https://www.presto-changeo.com/
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function pocoppa_activate() {

	update_option( 'pocoppa_transaction_type', 'SALE' );
	update_option( 'pocoppa_payment_gateway', 1 );
	update_option( 'pocoppa_visa', 1 );
	update_option( 'pocoppa_mc', 1 );

}
register_activation_hook( __FILE__, 'pocoppa_activate' );

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	add_action( 'plugins_loaded', 'pocoppa_init_paypal_addvanced' );

	function pocoppa_init_paypal_addvanced() {

		load_plugin_textdomain( 'woocommerce-gateway-paypal-advanced', false, basename( dirname( __FILE__ ) ) . '/languages' );

		define( 'POCOPPA_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );

		require_once(plugin_dir_path(__FILE__) . 'class-paypal-advanced.php');

	}

	function pocoppa_add_paypal_advanced_class( $methods ) {
		$methods[] = 'POCOPPP_WC_Gateway_PayPal_Advanced'; 
		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'pocoppa_add_paypal_advanced_class' );

}

if ( is_admin() ) {
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function( $links ) {

		$plugin_links = array(
		'<a href="admin.php?page=wc-settings&tab=checkout&section=poco_paypal_advanced">' . esc_html__( 'Settings', 'woocommerce-gateway-paypal-advanced' ) . '</a>'
		);
		return array_merge( $plugin_links, $links );

	} );
}




