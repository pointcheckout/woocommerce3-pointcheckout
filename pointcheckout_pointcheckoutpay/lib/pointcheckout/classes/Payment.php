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
        $this->log        = wc_get_logger();
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
        $order = new WC_order($orderId);
        $this->pfOrder->loadOrder($orderId);
        $order->update_status($this->pfConfig->getNewOrderStatus());

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

    /**
     * build payment form 
     */
    public function getPaymentRequestForm()
    {
        
        if(!$this->pfConfig->isEnabled()){            
            return null;
        }
        $paymentRequestParams = $this->getPaymentRequestParams();
        $response = $this->PointCheckoutApiCall($paymentRequestParams);
        if (($response->success == 'true' && $response->result->checkoutKey != null )) {
            $actionUrl = $this->getGatewayUrl().$response->result->checkoutKey;
            $returnUrl = get_site_url().'?wc-api=wc_gateway_pointcheckout_process_response';
            WC()->session->set('checkoutId',$response->result->checkoutId);
        }else{
            $this->paymentLog('Failed while sending first request to pointchckout resone: '.$response->error);
            wc_add_notice( sprintf( __( 'Failed to process payment please try again later', 'error' )));
            $actionUrl = get_site_url().'/index.php/checkout';
        }
        $this->pfOrder->clearSessionCurrentOrder();
        $form = '<form style="display:none" name="frm_pointcheckout_payment" id="frm_pointcheckout_payment" method="GET" action="' . $actionUrl . '">';
        $form .= '<input type="submit">';
        $formArray = array(
            'form' => $form,
            'response' => $response
            
        );
        return $formArray;
        
       
    }
    /**
     * first call that to get checkout key from pointcheckout
     */
    public function PointCheckoutApiCall($paymentRequestParams){
        $info = json_encode($paymentRequestParams);
        try {
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
            // excecute first call 
            $response = curl_exec($ch);
            
        }catch(Exception $e){
            $this->paymentLog('Failed to connect pointchckout resone: '.$e->getMessage());
            throw $e;
        }
       return json_decode($response);
    }
    
    
    public function PointCheckoutSecoundCall(){
        try {
            
            // create a new cURL resource
            $headers = array(
                'Content-Type: application/json',
                'Api-Key:'.$this->pfConfig->getApiKey(),
                'Api-Secret:'.$this->pfConfig->getApiSecret()
            );
            WC()->session->set('pointCheckoutCurrentOrderId',$_REQUEST['reference']);
            $_BASE_URL=$this->getGatewayApiUrl().$_REQUEST['checkout'];
            $ch = curl_init($_BASE_URL);
            
            // set URL and other appropriate options
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
            //execute secound call 
            $response = curl_exec($ch);
            
        }catch(Exception $e){
            $this->paymentLog('Failed to connect  to pointchckout resone: '.$e->getMessage());
            throw $e;
        }
        return $response;
    }
    
    public function getGatewayApiUrl(){
        $liveMode = $this->pfConfig->isLiveMode();
        if ($liveMode) {
           return $gatewayUrl = 'https://pay.pointcheckout.com/api/v1.0/checkout/';
        }elseif($this->pfConfig->isStagingMode()){
            return $gatewayUrl = 'https://pay.staging.pointcheckout.com/api/v1.0/checkout/';
        }else {
           return  $gatewayUrl = 'https://pay.test.pointcheckout.com/api/v1.0/checkout/';
        }
    }

    
    public function getGatewayUrl(){
        $liveMode = $this->pfConfig->isLiveMode();
        if ($liveMode) {
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
            $order = new WC_Order($_REQUEST['reference']);
            
            if (!empty($order)) {
                
            
            $response_info = json_decode($response);
            if (!$response ||  $response_info->success != true){    
                    $order->update_status('canceled');
                    $errorMsg = isset($response_info->error)?$response_info->error:'connecting to pointcheckout failed';
                    $note = __("[ERROR] order canceled  :".$errorMsg);
                    // Add the note
                    $order->add_order_note( $note );
                    // Save the data
                    $order->save();
                    $this->paymentLog('ERROR '.$errorMsg);
                    return array(
                        'success' => false,
                        'referenceId' => isset($response_info->referenceId)?$response_info->referenceId:''
                    );
                }
            if ($response_info->success == true && $response_info->result->status != 'PAID' ){
                $order->update_status('canceled');
                $note = __($this->getPointCheckoutOrderHistoryMessage($response_info->result->checkoutId,0,$response_info->result->status ,$response_info->result->currency));
                // Add the note
                $order->add_order_note( $note );
                // Save the data
                $order->save();
                return array(
                    'success' => false,
                    'referenceId' => $response_info->referenceId
                );
            }
            $note = __($this->getPointCheckoutOrderHistoryMessage($response_info->result->checkoutId,$response_info->result->cod,$response_info->result->status ,$response_info->result->currency));
            // Add the note
            $order->add_order_note( $note );
            
            // Save the data
            $order->save();
            return array(
                'success' => true,
                'referenceId' => $response_info->referenceId
            );
            }
    }
    
    public function paymentLog($messages, $forceDebug = false)
    {
        if ( ! class_exists( 'WC_Logger' ) ) {
            include_once( 'class-wc-logger.php' );
        }
        if ( empty( $this->log ) ) {
            $this->log = new WC_Logger();
        }
        $this->log->add( 'pointcheckout_pay', $messages );
    }
    
    
    public function getPointCheckoutOrderHistoryMessage($checkout,$codAmount,$orderStatus,$currency) {
        switch($orderStatus){
            case 'PAID':
                $color='style="color:green;"';
                break;
            case 'PENDING':
                $color='style="color:blue;"';
                break;
            default:
                $color='style="color:red;"';
        }
        $message = 'PointCheckout Status: <b '.$color.'>'.$orderStatus.'</b><br/>PointCheckout Transaction ID: <a href="'.$this->getAdminUrl().'/merchant/transactions/'.$checkout.'/read " target="_blank"><b>'.$checkout.'</b></a>'."\n" ;
        if($codAmount>0){
           $message.= '<b style="color:red;">[NOTICE] </b><i>COD Amount: <b>'.$codAmount.' '.$this->session->data['currency'].'</b></i>'."\n";
        }
        return $message;
    }
    
    
    private function getAdminUrl(){
        if ($this->pfConfig->isStagingMode()){
            $_ADMIN_URL='https://admin.staging.pointcheckout.com';
        }elseif($this->pfConfig->isLiveMode()){
            $_ADMIN_URL='https://admin.pointcheckout.com';
        }else{
            $_ADMIN_URL='https://admin.test.pointcheckout.com';
        }
        return $_ADMIN_URL;
        
    }
    

}

?>