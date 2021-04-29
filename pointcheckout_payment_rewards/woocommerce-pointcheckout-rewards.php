<?php

/* Plugin Name: PointCheckout Rewards
 * Description: PointCheckout payment allows your customer to pay for there cart using reward programs
 * Version:     2.0.4
 * Author:      PointCheckout
 * Author URI:  https://docs.pointcheckout.com/guides/woocommerce
 */
$active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
if (in_array('woocommerce/woocommerce.php', $active_plugins)) {

    add_filter('woocommerce_payment_gateways', 'add_pointcheckout_rewards_gateway');

    function add_pointcheckout_rewards_gateway($gateways)
    {
        $gateways[] = 'WC_Gateway_PointCheckout_Rewards';

        return $gateways;
    }

    add_action('plugins_loaded', 'init_pointcheckout_rewards_gateway');

    function init_pointcheckout_rewards_gateway()
    {
        require 'includes/class-woocommerce-pointcheckout-rewards.php';
        add_filter('woocommerce_get_sections_checkout', function ($sections) {
            return $sections;
        }, 500);
    }

    add_action('plugins_loaded', 'pointcheckout_rewards_load_plugin_textdomain');

    function pointcheckout_rewards_load_plugin_textdomain()
    {
        load_plugin_textdomain('woocommerce-other-payment-gateway', FALSE, basename(dirname(__FILE__)) . '/languages/');
    }

    function woocommerce_pointcheckout_rewards_actions()
    {
        if (isset($_GET['wc-api']) && !empty($_GET['wc-api'])) {
            WC()->payment_gateways();
            if ($_GET['wc-api'] == 'wc_gateway_pointcheckout_rewards_process_response') {
                do_action('woocommerce_wc_gateway_pointcheckout_rewards_process_response');
            }
        }
    }

    add_action('init', 'woocommerce_pointcheckout_rewards_actions', 500);
}
