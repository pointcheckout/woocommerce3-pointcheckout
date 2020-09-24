<?php

class PointCheckout_Rewards_Utils extends PointCheckout_Rewards_Parent
{

    private $logger;
    private $pcConfig;

    public function __construct()
    {
        parent::__construct();
        $this->pcOrder = new PointCheckout_Rewards_Order();
        $this->pcConfig = PointCheckout_Rewards_Config::getInstance();
    }

    public function getLogger()
    {
        include_once('class-wc-logger.php');

        if (self::$logger === null) {
            self::$logger = new WC_Logger();
        }
        return self::$logger;
    }

    public function apiCall($url, $body)
    {
        try {

            $headers = array(
                'Content-Type: application/json',
                'X-PointCheckout-Api-Key:' . $this->pcConfig->getApiKey(),
                'X-PointCheckout-Api-Secret:' . $this->pcConfig->getApiSecret()
            );

            $_BASE_URL = $this->getApiBaseUrl() . $url;

            $ch = curl_init($_BASE_URL);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            if (!is_null($body)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }

            $response = curl_exec($ch);
        } catch (Exception $e) {
            $this->log('Failed to connect  to pointchckout resone: ' . $e->getMessage());
            throw $e;
        }

        return json_decode($response);
    }

    public function log($messages)
    {
        $this->getLogger()->add('pointcheckout_rewards', $messages);
    }

    public function getApiBaseUrl()
    {
        if ($this->pcConfig->isLiveMode()) {
            return 'https://api.pointcheckout.com/mer/v1.2/checkouts/';
        } elseif ($this->pcConfig->isStagingMode()) {
            return 'https://api.test.pointcheckout.com/mer/v1.2/checkouts/';
        } else {
            return 'https://api.test.pointcheckout.com/mer/v1.2/checkouts/';
        }
    }

    public function getAdminUrl()
    {
        if ($this->pcConfig->isLiveMode()) {
            return 'https://admin.pointcheckout.com';
        } elseif ($this->pcConfig->isStagingMode()) {
            return 'https://admin.staging.pointcheckout.com';
        } else {
            return 'https://admin.test.pointcheckout.com';
        }
    }
}
