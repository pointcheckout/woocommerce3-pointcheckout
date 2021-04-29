<?php
define("PC_EXT_VERSION", "WooCommerce-Rewards-2.0.5");


class PointCheckout_Rewards_Payment extends PointCheckout_Rewards_Parent
{

    private static $instance;
    private $pcOrder;
    private $pcConfig;
    private $pcUtils;

    public function __construct()
    {
        parent::__construct();
        $this->pcOrder = new PointCheckout_Rewards_Order();
        $this->pcConfig = PointCheckout_Rewards_Config::getInstance();
        $this->pcUtils = new PointCheckout_Rewards_Utils();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new PointCheckout_Rewards_Payment();
        }
        return self::$instance;
    }

    public function getPaymentRequestParams()
    {
        $orderId = $this->pcOrder->getSessionOrderId();
        $order = new WC_order($orderId);
        $this->pcOrder->loadOrder($orderId);
        $order->update_status($this->pcConfig->getNewOrderStatus());

        $params = array(
            'transactionId' => $orderId,
        );
        $params["extVersion"] = PC_EXT_VERSION;
        try{
            $params["ecommerce"]= 'WordPress ' . $this->get_wp_version() . ', WooCommerce ' . $this->wpbo_get_woo_version_number();
        } catch (\Throwable $e) {
            // NOTHING TO DO 
        }

        $cartItems = $order->get_items();
        $items = array();
        $i = 0;
        foreach ($cartItems as $item_id => $item_data) {
            $product = $item_data->get_product();
            $item = (object) array(
                'name' => $product->get_name(),
                'sku' => $product->get_sku(),
                'quantity' => $item_data->get_quantity(),
                'type' => $product->get_type(),
                'total' => $item_data->get_total()
            );
            //in case of bundles the bundle group item total is set to zero here to prevent conflict in totals
            if ($product->get_type() == 'bundle') {
                $item->total = 0;
            }
            $items[$i++] = $item;
        }
        $params['items'] = array_values($items);
        $params['amount'] = $this->pcOrder->getTotal();
        $params['tax'] = $this->pcOrder->getTaxAmount();
        $params['shipping'] = $this->pcOrder->getShippingAmount();
        $params['subtotal'] = $this->pcOrder->getSubtotal();
        $params['discount'] = $this->pcOrder->getDiscountAmount();
        $params['currency'] = $this->pcOrder->getCurrencyCode();
        $params['paymentMethods'] = ["POINTCHECKOUT"];
        $params['resultUrl'] = get_site_url() . "?wc-api=wc_gateway_pointcheckout_rewards_process_response";

        // CUSTOMER
        $customer = array();
        if(!empty($order->get_customer_id()) && $order->get_customer_id() != 0) {
            $customer['id'] = $order->get_customer_id();
        }
        $customer['firstName'] = $order->get_billing_first_name();
        $customer['lastName'] = $order->get_billing_last_name();
        $customer['email'] = $order->get_billing_email();
        $customer['phone'] = $order->get_billing_phone();

        // BILLING ADDRESS
        $billingAddress = array();
        $billingAddress['name'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $billingAddress['address1'] = $order->get_billing_address_1();
        $billingAddress['address2'] = $order->get_billing_address_2();
        $billingAddress['city'] = $order->get_billing_city();
        $billingAddress['state'] = $order->get_billing_state();
        $billingAddress['country'] = $order->get_billing_country();
        $customer['billingAddress'] = $billingAddress;

        // SHIPPING ADDRESS
        $shippingAddress = array();
        $shippingAddress['name'] = $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();
        $shippingAddress['address1'] = $order->get_shipping_address_1();
        $shippingAddress['address2'] = $order->get_shipping_address_2();
        $shippingAddress['city'] = $order->get_shipping_city();
        $shippingAddress['country'] = $order->get_shipping_country();
        $shippingAddress['state'] = $order->get_shipping_state();
        $customer['shippingAddress'] = $shippingAddress;

        $params['customer'] = $customer;

        return $params;
    }

    /**
     * submit checkout details to pointcheckout
     */
    public function postOrderToPoitCheckout()
    {
        if (!$this->pcConfig->isEnabled()) {
            return null;
        }
        $paymentRequestParams = $this->getPaymentRequestParams();
        $response = $this->postCheckout($paymentRequestParams);

        $this->pcOrder->clearSessionCurrentOrder();
        return $response;
    }

    public function postCheckout($paymentRequestParams)
    {
        return $this->pcUtils->apiCall("", $paymentRequestParams);
    }


    public function getCheckout()
    {
        WC()->session->set('pointCheckoutCurrentOrderId', $_REQUEST['reference']);
        return $this->pcUtils->apiCall($_REQUEST['checkout'], null);
    }


    public function checkPaymentStatus()
    {
        $response = $this->getCheckout();
        $order = new WC_Order($_REQUEST['reference']);

        if (!empty($order)) {


            if (!$response->success) {
                $order->update_status('canceled');
                $errorMsg = isset($response->error) ? $response->error : 'connecting to pointcheckout failed';
                $note = __("[ERROR] order canceled  :" . $errorMsg);
                // Add the note
                $order->add_order_note($note);
                // Save the data
                $order->save();
                $this->pcUtils->log('ERROR ' . $errorMsg);
                return array(
                    'success' => false,
                    'transactionId' => isset($response->referenceId) ? $response->referenceId : ''
                );
            }

            $result = $response->result;


            if ($response->success && $result->status != 'PAID') {

                $order->update_status('canceled');
                $note = __($this->getOrderHistoryMessage($result->id, 0, $result->status, $result->currency));
                // Add the note
                $order->add_order_note($note);
                // Save the data
                $order->save();
                return array(
                    'success' => false,
                    'transactionId' => $result->referenceId
                );
            }
            $note = $this->getOrderHistoryMessage($result->id, $result->cash, $result->status, $result->currency);
            // Add the note
            $order->add_order_note($note);

            // Save the data
            $order->save();
            return array(
                'success' => true,
                'transactionId' => $result->referenceId
            );
        }
    }


    public function getOrderHistoryMessage($checkout, $codAmount, $orderStatus, $currency)
    {
        switch ($orderStatus) {
            case 'PAID':
                $color = 'style="color:green;"';
                break;
            case 'PENDING':
                $color = 'style="color:blue;"';
                break;
            default:
                $color = 'style="color:red;"';
        }
        $message = 'PointCheckout Status: <b ' . $color . '>' . $orderStatus . '</b><br/>PointCheckout Transaction ID: <a href="' . $this->pcUtils->getAdminUrl() . '/merchant/transactions/' . $checkout . '/read " target="_blank"><b>' . $checkout . '</b></a>' . "\n";
        if ($codAmount > 0) {
            $message .= '<b style="color:red;">[NOTICE] </b><i>COD Amount: <b>' . $codAmount . ' ' . $this->session->data['currency'] . '</b></i>' . "\n";
        }
        return $message;
    }

    function wpbo_get_woo_version_number() {
        // If get_plugins() isn't available, require it
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        
        // Create the plugins folder and file variables
        $plugin_folder = get_plugins( '/' . 'woocommerce' );
        $plugin_file = 'woocommerce.php';
        
        // If the plugin version number is set, return it 
        if ( isset( $plugin_folder[$plugin_file]['Version'] ) ) {
            return $plugin_folder[$plugin_file]['Version'];

        } else {
        // Otherwise return null
            return NULL;
        }
    }

    function get_wp_version() {
        return get_bloginfo('version');
    }
}
