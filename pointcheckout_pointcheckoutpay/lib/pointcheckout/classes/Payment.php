<?php
class PointCheckout_PointCheckoutPay_Payment extends PointCheckout_PointCheckoutPay_Super
{

    private static $instance;
    private $pfConfig;
    private $pfOrder;
    private $log;

    public function __construct()
    {
        parent::__construct();
        $this->pfConfig   = PointCheckout_PointCheckoutPay_Config::getInstance();
        $this->pfOrder    = new PointCheckout_PointCheckoutPay_Order();
    }

    /**
     * @return PointCheckout_PointCheckoutPay_Payment
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new PointCheckout_PointCheckoutPay_Payment();
        }
        return self::$instance;
    }
    
    public function getPaymentRequestParams()
    {
        $orderId = $this->pfOrder->getSessionOrderId();
        $order = wc_get_order($orderId);
        $this->pfOrder->loadOrder($orderId);

        $gatewayParams = array(
            'referenceId' => $orderId,
        );
        
        $cartItems= $order->get_items();
        $items = array();
        $i = 0;
        foreach ($cartItems as $item_id => $item_data){
            $product =$item_data->get_product();
            $item = (object) array(
                'name'=> $product->get_name(),
                'sku' => $product->get_sku(),
                'quantity' => $item_data->get_quantity(),
                'type'=>$product->get_type(),
                'total' => $item_data->get_total());
            //in case of bundles the bundle group item total is set to zero here to prevent conflict in totals
            if($product->get_type()=='bundle'){
                $item->total =0;
            }
            $items[$i++] = $item;
        }
        $gatewayParams['items']=array_values($items);
        $gatewayParams['grandtotal']= $this->pfOrder->getTotal();
        $gatewayParams['tax'] =$this->pfOrder->getTaxAmount();
        $gatewayParams['shipping'] =$this->pfOrder->getShippingAmount();
        $gatewayParams['subtotal'] =$this->pfOrder->getSubtotal();
        $gatewayParams['discount'] =$this->pfOrder->getDiscountAmount();
        $gatewayParams['currency'] =$this->pfOrder->getCurrencyCode();
        
        $customer = array();
        
        $billingAddress = array();
        $billingAddress['name'] = $order->get_billing_first_name().' '.$order->get_billing_last_name();
        $billingAddress['address1'] = $order->get_billing_address_1();
        $billingAddress['address2'] = $order->get_billing_address_2();
        $billingAddress['city'] = $order->get_billing_city();
        $billingAddress['country'] = $order->get_billing_country();
        
        $shippingAddress = array();
        $shippingAddress['name'] = $order->get_shipping_first_name().' '.$order->get_shipping_last_name();
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
        
        $gatewayParams['customer'] = $customer;
        
        return $gatewayParams;
    }

    public function getPaymentRequestForm()
    {
         
        $paymentRequestParams = $this->getPaymentRequestParams();
        $response = $this->PointCheckoutApiCall($paymentRequestParams);
        if (($response->success == 'true' && $response->result->checkoutKey != null )) {
            $actionUrl = $this->getGatewayUrl().$response->result->checkoutKey;
            $returnUrl = get_site_url().'?wc-api=wc_gateway_pointcheckout_process_response';
            WC()->session->set('checkoutId',$response->result->checkoutId);
        }else{
            $actionUrl = get_site_url().'/index.php/checkout';
        }
        $form = '<form style="display:none" name="frm_pointcheckout_payment" id="frm_pointcheckout_payment" method="GET" action="' . $actionUrl . '">';
        $form .= '<input type="submit">';
        return $form;
        
       
    }
    
    public function PointCheckoutApiCall($paymentRequestParams){
        $info = json_encode($paymentRequestParams);
        try {
            file_put_contents("/Applications/XAMPP/xamppfiles/htdocs/magento/var/log/yaser.log", date("Y-m-d h:i:sa") .'request is '.$info.' -----\\r\\n',FILE_APPEND);
            // create a new cURL resource
            $headers = array(
                'Content-Type: application/json',
                'Api-Key:'.$this->pfConfig->getApiKey(),
                'Api-Secret:'.$this->pfConfig->getApiSecret()
            );
            
            $_BASE_URL=$this->getGatewayApiUrl();
            $ch = curl_init($_BASE_URL);
            
            // set URL and other appropriate options
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS ,json_encode($paymentRequestParams));
            // grab URL and pass it to the browser
            $response = curl_exec($ch);
            file_put_contents("/Applications/XAMPP/xamppfiles/htdocs/magento/var/log/yaser.log", date("Y-m-d h:i:sa") .'response  is '.$response.'from '.$_BASE_URL.' -----\\r\\n',FILE_APPEND);
            
        }catch(Exception $e){
            $this->log('Failed while sending first request to pointchckout resone: '.$e->getMessage());
            throw $e;
        }
       return json_decode($response);
    }
    
    
    public function PointCheckoutSecoundCall(){
        try {
            file_put_contents("/Applications/XAMPP/xamppfiles/htdocs/magento/var/log/yaser.log", date("Y-m-d h:i:sa") .'checkout Id is '.$checkoutId.' -----\\r\\n',FILE_APPEND);
            
            // create a new cURL resource
            $headers = array(
                'Content-Type: application/json',
                'Api-Key:'.$this->pfConfig->getApiKey(),
                'Api-Secret:'.$this->pfConfig->getApiSecret()
            );
            
            $_BASE_URL=$this->getGatewayApiUrl().WC()->session->get('checkoutId');
            $ch = curl_init($_BASE_URL);
            
            // set URL and other appropriate options
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
            // grab URL and pass it to the browser
            $response = curl_exec($ch);
            file_put_contents("/Applications/XAMPP/xamppfiles/htdocs/magento/var/log/yaser.log", date("Y-m-d h:i:sa") .'response  is '.$response.'from '.$_BASE_URL.' -----\\r\\n',FILE_APPEND);
            
        }catch(Exception $e){
            $this->log('Failed while sending secound request to pointchckout resone: '.$e->getMessage());
            throw $e;
        }
        return $response;
    }
    
    public function getGatewayApiUrl(){
        $testMode = $this->pfConfig->isTestMode();
        if ($testMode) {
           return $gatewayUrl = 'https://pay.pointcheckout.com/api/v1.0/checkout/';
        }elseif($this->pfConfig->isStagingMode()){
            return $gatewayUrl = 'https://pay.staging.pointcheckout.com/api/v1.0/checkout/';
        }else {
           return  $gatewayUrl = 'https://pay.test.pointcheckout.com/api/v1.0/checkout/';
        }
    }

    
    public function getGatewayUrl(){
        $testMode = $this->pfConfig->isTestMode();
        if ($testMode) {
            return $gatewayUrl = 'https://pay.pointcheckout.com/checkout/';
        }elseif($this->pfConfig->isStagingMode()){
            return $gatewayUrl = 'https://pay.staging.pointcheckout.com/checkout/';
        }else {
            return  $gatewayUrl = 'https://pay.test.pointcheckout.com/checkout/';
        }
    }
    /**
     * @retrun array
     */
    public function handlePointCheckoutResponse()
    {
            $response = $this->PointCheckoutSecoundCall();
            $response_info = json_decode($response);
            if (!$response &&  $response_info->success != true){    
                return array(
                    'success' => false,
                    'referenceId' => ''
                );
            }elseif ($response_info->result->status != 'PAID' ){
                return array(
                    'success' => false,
                    'referenceId' => $response_info->referenceId
                );
            }
            return array(
                'success' => true,
                'referenceId' => $response_info->referenceId
            );
            
    }
    
    public function log($messages, $forceDebug = false)
    {
        if ( ! class_exists( 'WC_Logger' ) ) {
            include_once( 'class-wc-logger.php' );
        }
        if ( empty( $this->log ) ) {
            $this->log = new WC_Logger();
        }
        $this->log->add( 'pointcheckout_pay', $messages );
    }
    

}

?>