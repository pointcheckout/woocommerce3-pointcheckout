<?php



class PointCheckout_PointCheckoutPay_Payment extends PointCheckout_Parent
{

    private static $instance;
    private $pcOrder;
    private $pcConfig;
    private $pcUtils;

    public function __construct()
    {
        parent::__construct();
        $this->pcOrder = new PointCheckout_PointCheckoutPay_Order();
        $this->pcConfig = PointCheckout_PointCheckoutPay_Config::getInstance();
        $this->pcUtils = new PointCheckout_PointCheckoutPay_Utils();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new PointCheckout_PointCheckoutPay_Payment();
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
        $params['paymentMethods'] = ["CARD"];

        $customer = array();

        $billingAddress = array();
        $billingAddress['name'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $billingAddress['address1'] = $order->get_billing_address_1();
        $billingAddress['address2'] = $order->get_billing_address_2();
        $billingAddress['city'] = $order->get_billing_city();
        $billingAddress['country'] = $order->get_billing_country();

        $shippingAddress = array();
        $shippingAddress['name'] = $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();
        $shippingAddress['address1'] = $order->get_shipping_address_1();
        $shippingAddress['address2'] = $order->get_shipping_address_2();
        $shippingAddress['city'] = $order->get_shipping_city();
        $shippingAddress['country'] = $order->get_shipping_country();

        $customer['billingAddress'] = $billingAddress;
        $customer['shippingAddress'] = $shippingAddress;
        $customer['firstname'] = $order->get_billing_first_name();
        $customer['lastname'] = $order->get_billing_last_name();
        $customer['email'] = $order->get_billing_email();
        $customer['phone'] = $order->get_billing_phone();

        $params['customer'] = $customer;

        return $params;
    }

    /**
     * build payment form 
     */
    public function getPaymentRequestForm()
    {

        if (!$this->pcConfig->isEnabled()) {
            return null;
        }
        $paymentRequestParams = $this->getPaymentRequestParams();
        $response = $this->postCheckout($paymentRequestParams);
        if (($response->success == 'true')) {
            $actionUrl = $response->result->redirectUrl;
            WC()->session->set('checkoutId', $response->result->id);
        } else {
            $this->pcUtils->log('Failed while sending first request to pointchckout resone: ' . $response->error);
            wc_add_notice(sprintf(__('Failed to process payment please try again later', 'error')));
            $actionUrl = get_site_url() . '/index.php/checkout';
        }
        $this->pcOrder->clearSessionCurrentOrder();
        $form = '<form style="display:none" name="frm_pointcheckout_payment" id="frm_pointcheckout_payment" method="GET" action="' . $actionUrl . '">';
        $form .= '<input type="submit">';
        $formArray = array(
            'form' => $form,
            'response' => $response

        );
        return $formArray;
    }

    public function postCheckout($paymentRequestParams)
    {
        return $this->pcUtils->apiCall("/", $paymentRequestParams);
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


            if ($response->success != true) {
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


            if ($response->success == true && $result->status != 'PAID') {

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
}
