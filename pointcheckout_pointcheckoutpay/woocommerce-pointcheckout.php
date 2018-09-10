<?php

/* Plugin Name: PointCheckout
 * Description: PointCheckout allows your customer to pay for there cart using reward points from different programs as payments -- and that would means more and more customers visiting your store <a href="https://www.pointcheckout.com/home/merchant" target="_blank"><span>more details</span></a>
 * Version:     1.0.0
 * Author:      PointCheckout
 * Author URI:  https://www.pointcheckout.com/
 */
$active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
if (in_array('woocommerce/woocommerce.php', $active_plugins)) {

    
    if (!defined('POINTCHECKOUT_PAY')) {
        define('POINTCHECKOUT_PAY', true);
    }

    if (defined('POINTCHECKOUT_PAY_VERSION'))
        return;

    define('POINTCHECKOUT_PAY_VERSION', '1.2.0');


    if (!defined('POINTCHECKOUT_PAY_DIR')) {
        define('POINTCHECKOUT_PAY_DIR', plugin_dir_path(__FILE__));
    }

    if (!defined('POINTCHECKOUT_PAY_URL')) {
        define('POINTCHECKOUT_PAY_URL', plugin_dir_url(__FILE__));
    }

    add_filter('woocommerce_payment_gateways', 'add_pointcheckout_pointcheckoutpay_gateway');

    function add_pointcheckout_pointcheckoutpay_gateway($gateways)
    {
        $gateways[] = 'WC_Gateway_PointCheckout';
        
        return $gateways;
    }

    add_action('plugins_loaded', 'init_pointcheckout_pointcheckoutpay_payment_gateway');

    function init_pointcheckout_pointcheckoutpay_payment_gateway()
    {
        require 'classes/class-woocommerce-pointcheckout.php';
        add_filter( 'woocommerce_get_sections_checkout', function($sections){return $sections;}, 500 );
    }

    add_action('plugins_loaded', 'pointcheckout_pointcheckoutpay_load_plugin_textdomain');

    function pointcheckout_pointcheckoutpay_load_plugin_textdomain()
    {
        load_plugin_textdomain('woocommerce-other-payment-gateway', FALSE, basename(dirname(__FILE__)) . '/languages/');
    }

    function woocommerce_pointcheckout_actions()
    {
        if (isset($_GET['wc-api']) && !empty($_GET['wc-api'])) {
            WC()->payment_gateways();
            if ($_GET['wc-api'] == 'wc_gateway_pointcheckout_process_response'){
                do_action('woocommerce_wc_gateway_pointcheckout_process_response');
            }
       
        }
    }
    
    add_action('init', 'woocommerce_pointcheckout_actions', 500);
}
