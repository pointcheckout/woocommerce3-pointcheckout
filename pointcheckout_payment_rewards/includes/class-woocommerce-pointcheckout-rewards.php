<?php
require_once dirname( __FILE__ ) . '/lib/index.php';


class WC_Gateway_PointCheckout_Rewards extends PointCheckout_Rewards_Parent
{
    public $paymentService;
    public $config;
    private $pcUtils;

    public function __construct()
    {
        $this->pcUtils = new PointCheckout_Card_Utils();
        $this->has_fields = false;
        if (is_admin()) {
            $this->has_fields = true;
            $this->init_form_fields();
            $this->init_settings();
        }

        // Define user set variables
        $this->method_title = __('PointCheckout Rewards Pay', 'woocommerce');
        $this->title = 'PointCheckout';
        $this->description = 'Pay using your loyalty points with PointCheckout';
        $this->paymentService = PointCheckout_Rewards_Payment::getInstance();
        $this->config = PointCheckout_Rewards_Config::getInstance();
        $this->description = $this->config->getDescription();
        $this->icon       = apply_filters('woocommerce_POINTCHECKOUT_icon', 'https://www.pointcheckout.com/en/image/logo.png');

        if ( !$this->config->isLiveMode() ) {
          $this->description .= ' ' . sprintf( __( 'TEST MODE ENABLED.' .
              'See the <a href="%s" target="_blank">PointCheckout WooCommerce Guide</a> for more details.', 'woocommerce' ),
              'https://docs.pointcheckout.com/guides/woocommerce' );
          $this->description  = trim( $this->description );
        }

        // Actions
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

        // Save options
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_wc_gateway_pointcheckout_rewards_process_response', array($this, 'process_response'));
    }

    function process_admin_options()
    {
        $result = parent::process_admin_options();
        $settings = $this->settings;
        $settings['enabled']  = isset($settings['enabled']) ? $settings['enabled'] : 'no';

        update_option('woocommerce_pointcheckout_rewards_settings', apply_filters('woocommerce_settings_api_sanitized_fields_pointcheckout_rewards', $settings));
        return $result;
    }


    public function is_available()
    {
        if (!$this->config->isEnabled()) {
            return false;
        }

        $valid = true;
        if ($this->config->isSpecificUserRoles()) {
            $valid = false;
            if(WC()->customer != null) {
                $user_id = WC()->customer->get_id();
                $user = new WP_User($user_id);
                if (!empty($user->roles) && is_array($user->roles)) {
                    foreach ($user->roles as $user_role) {
                        foreach ($this->config->getSpecificUserRoles() as $role) {
                            if ($role == $user_role) {
                                $valid = true;
                            }
                        }
                    }
                }
            }
        }

        if ($valid && $this->config->isSpecificCountries()) {
            $valid = false;
            if(WC()->customer != null) {
                $billingCountry = WC()->customer->get_billing_country();

                if (!$billingCountry == null) {
                    foreach ($this->config->getSpecificCountries() as $country) {
                        if ($country == $billingCountry) {
                            $valid = true;
                        }
                    }
                }
            }
        }
        if ($valid) {
            return parent::is_available();
        } else {
            return false;
        }
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
        <h3><?php _e('PointCheckout Rewards Payment', 'pointcheckout_rewards'); ?></h3>
        <p><?php _e('Please fill in the below section to start accepting payments on your site via PointCheckout! Learn more at <a href="https://docs.pointcheckout.com/" target="_blank">PointCheckout</a>.', 'pointcheckout_rewards'); ?></p>


        <table class="form-table">
            <?php
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            ?>
            <script>
                jQuery(document).ready(function() {
                    jQuery('[name=save]').click(function() {
                        if (!jQuery('#woocommerce_pointcheckout_rewards_api_key').val()) {
                            alert('API key not configured');
                            return false;
                        }
                        if (!jQuery('#woocommerce_pointcheckout_rewards_api_secret').val()) {
                            alert('API secret not configured');
                            return false;
                        }
                        if (jQuery('#woocommerce_pointcheckout_rewards_allow_specific').val() == 1) {
                            if (!jQuery('#woocommerce_pointcheckout_rewards_specific_countries').val()) {
                                alert('You enabled PointCheckout for specific countries but you did not select any');
                                return false;
                            }
                        }
                        if (jQuery('#woocommerce_pointcheckout_rewards_allow_user_specific').val() == 1) {
                            if (!jQuery('#woocommerce_pointcheckout_rewards_specific_user_roles').val()) {
                                alert('You enabled PointCheckout for speficic user roles but you did not select any');
                                return false;
                            }
                        }

                    })
                });
            </script>
        </table>
        <!--/.form-table-->
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
        $staging_enabled = false;
        $this->form_fields = array(
            'enabled'     => array(
                'title'   => __('Enable/Disable', 'pointcheckout_rewards'),
                'type'    => 'checkbox',
                'label'   => __('Enable rewards payments via PointCheckout', 'pointcheckout_rewards'),
                'default' => 'no'
            ),
            'description'         => array(
                'title'       => __('Description', 'pointcheckout_rewards'),
                'type'        => 'text',
                'description' => __('This is the description the user sees during checkout.', 'pointcheckout_rewards'),
                'default'     => __('Pay for your items with your collected reward points', 'pointcheckout_rewards')
            ),
            'mode'          => array(
                'title'       => __('Mode', 'pointcheckout_rewards'),
                'type'        => 'select',
                'options'     => $staging_enabled ? array(
                    '1' => __('Live', 'pointcheckout_rewards'),
                    '0' => __('Testing', 'pointcheckout_rewards'),
                    '2' => __('Staging', 'pointcheckout_rewards'),
                ) : array(
                    '1' => __('Live', 'pointcheckout_rewards'),
                    '0' => __('Testing', 'pointcheckout_rewards'),
                ),
                'default'     => '0',
                'desc_tip'    => true,
                'description' => sprintf(__('Logs additional information. <br>Log file path: %s', 'pointcheckout_rewards'), 'Your admin panel -> WooCommerce -> System Status -> Logs'),
                'placeholder' => '',
                'class'       => 'wc-enhanced-select',
            ),
            'api_key'         => array(
                'title'       => __('API Key', 'pointcheckout_rewards'),
                'type'        => 'text',
                'description' => __('Your Api Key, you can find in your PointCheckout account  settings.', 'pointcheckout_rewards'),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => ''
            ),
            'api_secret'         => array(
                'title'       => __('API Secret', 'pointcheckout_rewards'),
                'type'        => 'text',
                'description' => __('Your Api Secret, you can find in your PointCheckout account  settings.', 'pointcheckout_rewards'),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => ''
            ),
            'allow_specific' => array(
                'title'       => __('Applicable Countries', 'pointcheckout_rewards'),
                'type'        => 'select',
                'options'     => array(
                    '0' => __('All Countries', 'pointcheckout_rewards'),
                    '1' => __('Specific countries only', 'pointcheckout_rewards')
                )
            ),
            'specific_countries' => array(
                'title'   => __('Specific Countries', 'pointcheckout_rewards'),
                'desc'    => '',
                'css'     => 'min-width: 350px;min-height:300px;',
                'default' => 'wc_get_base_location()',
                'type'    => 'multiselect',
                'options' => $this->getCountries()
            ),
            'allow_user_specific' => array(
                'title'       => __('Applicable User Roles', 'pointcheckout_rewards'),
                'type'        => 'select',
                'options'     => array(
                    '0' => __('All User Roles', 'pointcheckout_rewards'),
                    '1' => __('Specific Roles only', 'pointcheckout_rewards')
                )
            ),
            'specific_user_roles' => array(
                'title'   => __('Specific User Roles', 'pointcheckout_rewards'),
                'desc'    => '',
                'css'     => 'min-width: 350px;min-height:300px;',
                'default' => 'wc_get_base_role()',
                'type'    => 'multiselect',
                'options' => $this->getRoles()
            )
        );
    }


    function getCountries()
    {
        $countries_obj   = new WC_Countries();
        return $countries_obj->__get('countries');
    }

    function getRoles()
    {
        global $wp_roles;
        $all_roles = $wp_roles->roles;
        $editable_roles = apply_filters('editable_roles', $all_roles);
        $user_roles = array();

        foreach ($editable_roles as $k => $v) {
            $user_roles[$k]  = $k;
        }
        return $user_roles;
    }

    function process_payment($order_id)
    {
        $order   = new WC_Order($order_id);
        if (!isset($_GET['response_code'])) {
            update_post_meta($order->get_id(), '_payment_method_title', 'PointCheckout');
            update_post_meta($order->get_id(), '_payment_method', 'pointcheckout_rewards');
        }
        $response   = $this->paymentService->postOrderToPoitCheckout();

        if ($response->success == 'true') {
            WC()->session->set('checkoutId', $response->result->id);
        } else {
            $this->pcUtils->log('Failed to initiate rewards payment using PointCheckout, message : ' . $response->error);
        }

        $note = $this->paymentService->getOrderHistoryMessage($form['response']->result->id, 0, $form['response']->result->status, '');
        $order->add_order_note($note);

        if ($response->success == 'true') {
            return array(
                'result' => 'success',
                'redirect' => $response->result->redirectUrl
            );
        } else {
            wc_add_notice(__('Failed to process payment please try again later'), 'error');
        }
    }



    public function process_response()
    {

        global $woocommerce;
        //send the secound call to pointcheckout to confirm payment
        $success = $this->paymentService->checkPaymentStatus();

        $order = wc_get_order($_REQUEST['reference']);
        if ($success['success']) {
            $order->payment_complete();
            WC()->session->set('refresh_totals', true);
            $redirectUrl = $this->get_return_url($order);
        } else {
            $redirectUrl = esc_url($woocommerce->cart->get_checkout_url());
            $order->cancel_order();
        }
        echo '<script>window.top.location.href = "' . $redirectUrl . '"</script>';
        exit;
    }
}
