<?php

define('POINTCHECKOUT_PAY_PAYMENT_METHOD', 'pointcheckout_pay');
define('POINTCHECKOUT_PAY_FLASH_MSG_ERROR', 'error');
define('POINTCHECKOUT_PAY_FLASH_MSG_SUCCESS', 'success');
define('POINTCHECKOUT_PAY_FLASH_MSG_INFO', 'info');
define('POINTCHECKOUT_PAY_FLASH_MSG_WARNING', 'warning');

class PointCheckout_PointCheckoutPay_Config extends PointCheckout_PointCheckoutPay_Super
{

    private static $instance;
    private $language;
    private $Api_Secret;
    private $Api_Key;
    private $command;
    private $Mode;
    private $gatewayCurrency;
    private $successOrderStatusId;
    private $orderPlacement;
    private $status;
    private $logFileDir;
    private $allowSpecific;
    private $specific_countries;
    


    public function __construct()
    {
        parent::__construct();

        $this->logFileDir         = WC_LOG_DIR. 'pointcheckout.log';
        
        $this->init_settings();
        $this->language                              = $this->_getShoppingCartConfig('language');
        $this->enabled                               = $this->_getShoppingCartConfig('enabled');
        $this->Api_Key                               = $this->_getShoppingCartConfig('Api_Key');
        $this->command                               = $this->_getShoppingCartConfig('command');
        $this->Api_Secret                            = $this->_getShoppingCartConfig('Api_Secret');
        $this->Mode                                  = $this->_getShoppingCartConfig('mode');
        $this->gatewayCurrency                       = 'base';
        $this->successOrderStatusId = '';
        $this->orderPlacement                        = $this->_getShoppingCartConfig('order_placement');
        $this->status                                = $this->enabled;
        $this->allowSpecific                         = $this->_getShoppingCartConfig('allow_specific');
        $this->specific_countries                    = $this->_getShoppingCartConfig('specific_countries');
        
    }

    /**
     * @return PointCheckout_PointCheckoutPay_Config
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new PointCheckout_PointCheckoutPay_Config();
        }
        return self::$instance;
    }

    private function _getShoppingCartConfig($key)
    {
        return $this->get_option($key);
    }

    public function getLanguage()
    {
        return 'en';
    }


    
    public function getEnabled()
    {
        return $this->enabled;
    }

    public function getMode()
    {
        return $this->Mode;
    }


    public function getGatewayCurrency()
    {
        return $this->gatewayCurrency;
    }



    public function getSuccessOrderStatusId()
    {
        return $this->successOrderStatusId;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function isActive()
    {
        if ($this->active) {
            return true;
        }
        return false;
    }
    
    public function isSpecificCountries(){
        return $this->allowSpecific == 1?true:false;
    }
    
    public function getSpecificCountries(){
        return $this->specific_countries;
    }

    public function getOrderPlacement()
    {
        return $this->orderPlacement;
    }

    public function orderPlacementIsAll()
    {
        if (empty($this->orderPlacement) || $this->orderPlacement == 'all') {
            return true;
        }
        return false;
    }

    public function orderPlacementIsOnSuccess()
    {
        if ($this->orderPlacement == 'success') {
            return true;
        }
        return false;
    }

    public function isEnabled(){
        return $this->enabled == 1?true:false;
    }

    public function getApiKey(){
        return $this->Api_Key;
    }
    
    public function getApiSecret(){
        return $this->Api_Secret;
    }
    
    public function isLiveMode(){
        return $this->Mode == 1?true:false;
    }
    
    public function isStagingMode(){
        return $this->Mode == 2?true:false;
    }



    public function getLogFileDir()
    {
        return $this->logFileDir;
    }

}

?>