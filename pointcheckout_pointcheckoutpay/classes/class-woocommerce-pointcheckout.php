<?php
require_once POINTCHECKOUT_PAY_DIR . 'lib/pointcheckout/init.php';

class WC_Gateway_PointCheckout extends PointCheckout_PointCheckoutPay_Super
{
    public $pfConfig;
    public $pfPayment;

    public function __construct()
    {
        global $woocommerce;
        $this->has_fields = false;
        $this->icon       = apply_filters('woocommerce_POINTCHECKOUT_icon', POINTCHECKOUT_PAY_URL . 'assets/images/pointcheckout.png');
        if(is_admin()) {
            $this->has_fields = true;
            $this->init_form_fields();
        }
        
        // Define user set variables
        $this->title               = 'PointCheckout';
        $this->description  = __('Pay for your items with using PointCheckout payment method', 'pointcheckout_pointcheckoutpay');
        $this->pfConfig            = PointCheckout_PointCheckoutPay_Config::getInstance();
        $this->pfPayment           = PointCheckout_PointCheckoutPay_Payment::getInstance();
        
        
        
        // Actions
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        
        // Save options
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_wc_gateway_pointcheckout_process_response', array(&$this, 'process_response'));
    }

    function process_admin_options() {
        $result = parent::process_admin_options();
        $post_data = $this->get_post_data();
        $settings = $this->settings;
        
        $pointcheckoutSettings = array();
        
        $pointcheckoutSettings['enabled']  = isset($settings['enabled']) ? $settings['enabled'] : "no";
        
        update_option( 'woocommerce_pointcheckout_settings', apply_filters( 'woocommerce_settings_api_sanitized_fields_pointcheckout', $pointcheckoutSettings ) );
        return $result;
    }
    
    function payment_scripts()
    {
        global $woocommerce;
        if (!is_checkout()) {
            return;
        }
        wp_enqueue_script('pointcheckoutpayjs-checkout', POINTCHECKOUT_PAY_URL . 'assets/js/checkout.js', array(), WC_VERSION, true);
    }

    /**
     * Admin Panel Options
     * - Options for bits like 'api keys' and availability on a country-by-country basis
     *
     * @since 1.0.0
     */
    public function admin_options()
    {
        ?>
        <h3><?php _e('PointCheckout', 'pointcheckout_pointcheckoutpay'); ?></h3>
        <p><?php _e('Please fill in the below section to start accepting payments on your site! You can find all the required information in your <a href="https://www.pointcheckout.com/" target="_blank">PointCheckout website</a>.', 'pointcheckout_pointcheckoutpay'); ?></p>


            <table class="form-table">
            <?php
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            ?>
                <script>
                    jQuery(document).ready(function () {
                        jQuery('[name=save]').click(function () {
                            if (!jQuery('#woocommerce_pointcheckout_pay_Api_Key').val()) {
                                alert('Please enter your Api Key!');
                                return false;
                            }
                            if (!jQuery('#woocommerce_pointcheckout_pay_Api_Secret').val()) {
                                alert('Please enter your Api Secret!');
                                return false;
                            }
                        })
                    });
                </script>
            </table><!--/.form-table-->
  <?php      
    }   

    /**
     * Initialise Gateway Settings Form Fields
     *
     * @access public
     * @return void
     */
    function init_form_fields()
    {
        $staging_enabled=false;
        $this->form_fields = array(
            'enabled'             => array(
                'title'   => __('Enable/Disable', 'pointcheckout_pointcheckoutpay'),
                'type'    => 'checkbox',
                'label'   => __('Enable the PointCheckout gateway', 'pointcheckout_pointcheckoutpay'),
                'default' => 'yes'
            ),
            'description'         => array(
                'title'       => __('Description', 'pointcheckout_pointcheckoutpay'),
                'type'        => 'text',
                'description' => __('This is the description the user sees during checkout.', 'pointcheckout_pointcheckoutpay'),
                'default'     => __('Pay for your items with your collected reward points', 'pointcheckout_pointcheckoutpay')
            ),
            'mode'          => array(
                'title'       => __( 'Mode', 'pointcheckout_pointcheckoutpay'),
                'type'        => 'select',
                'options'     => $staging_enabled?array(
                    '1' => __('live', 'pointcheckout_pointcheckoutpay'),
                    '0' => __('testing', 'pointcheckout_pointcheckoutpay'),
                    '2' => __('Staging', 'pointcheckout_pointcheckoutpay'),
                ):array(
                    '1' => __('live', 'pointcheckout_pointcheckoutpay'),
                    '0' => __('testing', 'pointcheckout_pointcheckoutpay'),
                ),
                'default'     => '0',
                'desc_tip'    => true,
                'description' => sprintf(__('Logs additional information. <br>Log file path: %s', 'pointcheckout_pointcheckoutpay'), 'Your admin panel -> WooCommerce -> System Status -> Logs'),
                'placeholder' => '',
                'class'       => 'wc-enhanced-select',
            ),
            'language'            => array(
                'title'       => __('Language', 'pointcheckout_pointcheckoutpay'),
                'type'        => 'select',
                'options'     => array(
                    'en'    => __('English (en)', 'pointcheckout_pointcheckoutpay'),
                ),
                'description' => __('The language of the payment page.', 'pointcheckout_pointcheckoutpay'),
                'default'     => 'en',
                'desc_tip'    => true,
                'placeholder' => '',
                'class'       => 'wc-enhanced-select',
            ),
            'Api_Key'         => array(
                'title'       => __('Api Key', 'pointcheckout_pointcheckoutpay'),
                'type'        => 'text',
                'description' => __('Your Api Key, you can find in your PointCheckout account  settings.', 'pointcheckout_pointcheckoutpay'),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => ''
            ),
            'Api_Secret'         => array(
                'title'       => __('Api Secret', 'pointcheckout_pointcheckoutpay'),
                'type'        => 'text',
                'description' => __('Your Api Secret, you can find in your PointCheckout account  settings.', 'pointcheckout_pointcheckoutpay'),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => ''
            ),
            'command'             => array(
                'title'       => __('Command', 'pointcheckout_pointcheckoutpay'),
                'type'        => 'select',
                'options'     => array(
                                       'AUTHORIZATION' => __('AUTHORIZATION', 'pointcheckout_pointcheckoutpay'),
                                       'PURCHASE'      => __('PURCHASE', 'pointcheckout_pointcheckoutpay')
                ),
                'description' => __('Order operation to be used in the payment page.', 'pointcheckout_pointcheckoutpay'),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => '',
                'class'       => 'wc-enhanced-select',
            ),
            
            'order_placement' => array(
                'title'       => __('Order Placement', 'pointcheckout_pointcheckoutpay'),
                'type'        => 'select',
                'options'     => array(
                                       'all' => __('All', 'pointcheckout_pointcheckoutpay'),
                                       'success' => __('Success', 'pointcheckout_pointcheckoutpay'),
                ),
                'default'     => 'all',
                'placeholder' => '',
                'class'       => 'wc-enhanced-select',
            ),
        );
    }

    /**
     * Process the payment and return the result
     *
     * @access public
     * @param int $order_id
     * @return array
     */
    function process_payment($order_id)
    {
        global $woocommerce;
        $order   = new WC_Order($order_id);
        if (!isset($_GET['response_code'])) {
            $payment_method  = $_POST['payment_method'];
            $paymentMethod = POINTCHECKOUT_PAY_PAYMENT_METHOD;
            $postData   = array();
            $gatewayUrl = '#';
            update_post_meta($order->id, '_payment_method_title', 'PointCheckout');
            update_post_meta($order->id, '_payment_method', POINTCHECKOUT_PAY_PAYMENT_METHOD);
         }
            $form   = $this->pfPayment->getPaymentRequestForm();
            $result = array('result' => 'success', 'form' => $form);
            if (isset($_POST['woocommerce_pay']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'woocommerce-pay')) {
                wp_send_json($result);
                exit;
            }else {
                return $result;
            }
        }
     
    

    public function process_response()
    {
        $this->_handleResponse();
    }


    private function _handleResponse()
    {
        global $woocommerce;
            //send the secound call to pointcheckout to confirm payment 
            $success = $this->pfPayment->handlePointCheckoutResponse();
            $order = wc_get_order(WC()->session->get('order_awaiting_payment'));
            if ($success['success']) {
                $order->payment_complete();
                $order->add_order_note('PointCheckout payment confirmed');
                WC()->session->set('refresh_totals', true);
                $redirectUrl = $this->get_return_url($order);
            }
            else {
                $redirectUrl = esc_url($woocommerce->cart->get_checkout_url());
                $order->cancel_order();
                $order->add_order_note('PointCheckout payment Failed');
            }
            echo '<script>window.top.location.href = "' . $redirectUrl . '"</script>';
            exit;
    }

    /**
     * Generate the credit card payment form
     *
     * @access public
     * @param none
     * @return string
     */
    function payment_fields()
    {
        // Access the global object
        if ($this->description) {
            echo "<p>" . $this->description . "</p>";
        }
        
    }
}
