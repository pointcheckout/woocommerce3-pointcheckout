<?php

class PointCheckout_PointCheckoutPay_Order extends PointCheckout_PointCheckoutPay_Super
{

    private $order = array();
    private $orderId;
    private $pfConfig;

    public function __construct()
    {
        parent::__construct();
        $this->pfConfig = PointCheckout_PointCheckoutPay_Config::getInstance();
    }

    public function loadOrder($orderId)
    {
        $this->orderId = $orderId;
        $this->order   = $this->getOrderById($orderId);
    }

    public function setOrderId($orderId)
    {
        $this->orderId = $orderId;
    }

    public function setOrder($order)
    {
        $this->order = $order;
    }

    public function getSessionOrderId()
    {
        return WC()->session->get('order_awaiting_payment');
    }
    
    public function clearSessionCurrentOrder()
    {
        return WC()->session->__unset('order_awaiting_payment');
    }
    

    public function getOrderId()
    {
        return $this->order->id;
    }

    public function getOrderById($orderId)
    {
        $order = wc_get_order($orderId);
        return $order;
    }

    public function getLoadedOrder()
    {
        return $this->order;
    }

    public function getEmail()
    {
        return $this->order->billing_email;
    }

    public function getCustomerName()
    {
        $fullName  = '';
        $firstName = $this->order->billing_first_name;
        $lastName  = $this->order->billing_last_name;

        $fullName = trim($firstName . ' ' . $lastName);
        return $fullName;
    }

    public function getCurrencyCode()
    {
        return $this->order->get_order_currency();
    }

    public function getCurrencyValue()
    {
        return 1;
    }

    public function getTotal()
    {
        return $this->order->get_total();
    }

    public function getTaxAmount(){
        return $this->order->get_total_tax();
    }
    
    public function getShippingAmount(){
        return $this->order->get_shipping_total();
    }
    
    public function getSubtotal(){
        return $this->order->get_subtotal();
    }
    
    public function getDiscountAmount(){
        return $this->order->get_discount_total();
    }
    public function getPaymentMethod()
    {
        return $this->order->payment_method;
    }

    public function getStatusId()
    {
        return $this->order->get_status();
    }

}

?>