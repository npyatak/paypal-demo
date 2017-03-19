<?php
namespace app\components;

use Yii;
use yii\helpers\Url;
use yii\helpers\Html;
use yii\base\InvalidConfigException;
use InvalidArgumentException;

/**
 * PayPal payment system component for Yii2 framework.
 */
class PayPal extends \yii\base\Component
{
    /**
     * Mods.
     */
    const MODE_SANDBOX  = 'sandbox';
    const MODE_LIVE     = 'live';

    /**
     * Actions.
     */
    const ACTION_SALE = 'sale';

    /**
     * Urls.
     */
    const DEFAULT_URL_SANDBOX           = 'api-3t.sandbox.paypal.com/nvp';
    const DEFAULT_URL_LIVE              = 'api-3t.paypal.com/nvp';
    const DEFAULT_CHECKOUT_URL_SANDBOX  = 'www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=';
    const DEFAULT_CHECKOUT_URL_LIVE     = 'www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=';
    
    /**
     * Methods.
     */
    const METHOD_SET_EXPRESS_CHECKOUT           = 'SetExpressCheckout';
    const METHOD_SET_EXPRESS_CHECKOUT_PAYMENT   = 'SetExpressCheckoutPayment';
    const METHOD_GET_EXPRESS_CHECKOUT_DETAILS   = 'GetExpressCheckoutDetails';
    const METHOD_DO_EXPRESS_CHECKOUT_PAYMENT    = 'DoExpressCheckoutPayment';
    
    /**
     * Others.
     */
    const DEFAULT_CURRENCY_CODE = 'USD';
    const DEFAULT_TIMEOUT       = 0;

    /**
     * Array of component params from main config.
     * 
     * @var array
     */
    public $config = [];

    /**
     * User ID.
     * 
     * @var string
     */
    public $user;

    /**
     * User password.
     * 
     * @var string
     */
    public $password;

    /**
     * User signature.
     * 
     * @var string
     */
    public $signature;

    /**
     * Payment system url for request with methods.
     * 
     * @var string
     */
    public $url;

    /**
     * Payment system url for express-checkout payment.
     * 
     * @var string
     */
    public $checkoutUrl;

    /**
     * Your url for redirect client on site success payment page.
     * 
     * @var string
     */
    public $returnUrl;

    /**
     * Your url for redirect client on site failure payment page.
     * 
     * @var string
     */
    public $cancelUrl;
    
    /**
     * PayPal payment system API version.
     * 
     * @var float
     */
    public $version;
    
    /**
     * Curl request to payment system timeout (sec).
     *     def: 0
     *     
     * @var integer
     */
    public $timeout;

    /**
     * Curl response storage.
     * 
     * @var array
     */
    private $_response;

    /**
     * Payent token by payment system.
     * 
     * @var string
     */
    private $_token;

    /**
     * Current payment mode.
     *     - sandbox
     *     - live
     *     
     * @var string
     */
    private $_mode;

    /**
     * Initialization.
     * 
     * @return void
     */
    public function init()
    {
        parent::init();

        $this->setMode();
        $this->setConfig();
        $this->setTimeout();
        $this->setUrls();
        $this->setRedirectUrls();
    }

    /**
     * Checked params from main config and defines their respective attributes of this class.
     * 
     * @throws yii\base\InvalidConfigException If required params are not specified, throws an error
     * 
     * @return void
     */
    private function setConfig()
    {
        if ($config = $this->config) {
            // requierd params
            if (!isset($config['user']) || empty($config['user']))
                throw new InvalidConfigException('"User" is not specified!');
                $this->user = (string) $config['user'];
            if (!isset($config['password']) || empty($config['password']))
                throw new InvalidConfigException('User "password" is not specified!');
                $this->password = (string) $config['password'];
            if (!isset($config['signature']) || empty($config['signature']))
                throw new InvalidConfigException('User "signature" is not specified!');
                $this->signature = (string) $config['signature'];
            if (!isset($config['returnUrl']) || empty($config['returnUrl']))
                throw new InvalidConfigException('"ReturnUrl" for redirect user is not specified!');
                $this->returnUrl = (string) $config['returnUrl'];
            if (!isset($config['cancelUrl']) || empty($config['cancelUrl']))
                throw new InvalidConfigException('"CancelUrl" for redirect user is not specified!');
                $this->cancelUrl = (string) $config['cancelUrl'];
            if (!isset($config['version']) || empty($config['version']))
                throw new InvalidConfigException('PayPal API "version" is not specified!');
                $this->version = (float) $config['version'];
            // non-required params
            if (isset($config['mode']) && !empty($config['mode'])) {
                if (in_array($config['mode'], self::getModes())) {
                    $this->_mode = (string) $config['mode'];
                } else {
                    throw new InvalidConfigException('Incorrect "mode" selected!');
                }
            }
            if (isset($config['url']) && !empty($config['url']))
                $this->url = (string) $config['url'];
            if (isset($config['checkoutUrl']) && !empty($config['checkoutUrl']))
                $this->checkoutUrl = (string) $config['checkoutUrl'];
            if (isset($config['timeout']) && !empty($config['timeout']))
                $this->timeout = (integer) $config['timeout'];
        } else {
            throw new InvalidConfigException('System config is empty!');
        }
    }

    /**
     * Set current payment mode. 
     * If mode is not specified, set default mode - live.
     *
     * @return void
     */
    private function setMode()
    {
        if (!$this->_mode) $this->_mode = self::MODE_LIVE;
    }

    /**
     * Set timeout for curl request to payment system.
     * If timeout not specified, set default timeout - 0 sec.
     *
     * @return void
     */
    private function setTimeout()
    {
        if (!$this->timeout) $this->timeout = self::DEFAULT_TIMEOUT;
    }

    /**
     * Set payment system urls by payment mode.
     * If urls not specified, set default sandbox or live urls.
     *
     * @return void
     */
    private function setUrls()
    {
        switch ($this->_mode) {
            case self::MODE_SANDBOX:
                $this->url = $this->url ? $this->url : self::DEFAULT_URL_SANDBOX;
                $this->checkoutUrl = $this->checkoutUrl ? $this->checkoutUrl : self::DEFAULT_CHECKOUT_URL_SANDBOX;
                break;
            case self::MODE_LIVE:
                $this->url = $this->url ? $this->url : self::DEFAULT_URL_LIVE;
                $this->checkoutUrl = $this->checkoutUrl ? $this->checkoutUrl : self::DEFAULT_CHECKOUT_URL_LIVE;
                break;
            default:
                throw new InvalidConfigException('Payment "mode" not supported!');
                break;
        }
        foreach (array('url', 'checkoutUrl') as $attr) {
            $this->$attr = 'https://' . $this->$attr;       
        }
    }

    /**
     * Set redirect - return and cancel - urls.
     * Urls processed by yii\helpers\Url::to().
     *
     * @return void
     */
    private function setRedirectUrls()
    {
        $this->returnUrl = Url::to([$this->returnUrl], true);
        $this->cancelUrl = Url::to([$this->cancelUrl], true);
    }

    /**
     * Create curl request to PayPal payment system.
     * 
     * @param string $postfields URL-encoded query string with PayPal params.
     * @return void
     */
    private function createCurlRequest($postfields)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        $server_output = curl_exec($ch);
        curl_close($ch);
        parse_str($server_output, $response);

        $this->setCurlResponse($response);
    }

    /**
     * Set curl response to response storage $_response.
     * 
     * @param array Array of parmas by PayPal response.
     * 
     * @return void
     */
    public function setCurlResponse($response)
    {
        $this->_response = $response;
    }

    /**
     * Get curl response params from storage $_response.
     *  
     * @return array Array of parmas by PayPal response.
     */
    public function getCurlResponse()
    {   
        return $this->_response;
    }
    
    /**
     * Set token from PayPal response in storage $_token.
     * If token is not in response, token is set null.
     * 
     * @return void
     */
    private function setToken()
    {
        $response = $this->getCurlResponse();
        if (isset($response['TOKEN']) && !empty($response['TOKEN'])) {
            $this->_token = $response['TOKEN'];
        } else {
            $this->_token = null;
        }
    }

    /**
     * Get current token.
     * 
     * @return string|null Token.
     */
    public function getToken()
    {
        $this->setToken();
        return $this->_token;
    }

    /**
     * Generate URL-encoded query string.
     * 
     * @param  array  $postfields Array of payment system params for curl request.
     * 
     * @throws \Exception if argument $postfields is empty
     * @return string URL-encoded query string
     */
    private static function buildQuery(array $postfields = [])
    {
        if ($postfields) {
            return http_build_query($postfields);
        } else {
            throw new \Exception("Postfields is empty!");
        }
    }

    /**
     * Get all payment system modes.
     *  
     * @return array
     */
    private static function getModes()
    {
        return [
            self::MODE_SANDBOX,
            self::MODE_LIVE,
        ];
    }

    /**
     * Get all payment system actions.
     * 
     * @return array
     */
    private static function getActions()
    {
        return [
            self::ACTION_SALE,
        ];   
    }

    /**
     * Set Up the Payment Information and curl request to PayPal system.
     * 
     * @param float  $amount       Value of payment amount
     * @param string $action       Payment system action
     *                             def. 'sale'
     * @param [type] $currencyCode [description]
     *
     * @return leko\components\paypal\PayPal
     */
    public function setUpPayment($amount = null, $action = null, $currencyCode = null, $description = null)
    {
        if (!$amount || !is_numeric($amount))
            throw new InvalidArgumentException('Current argument "amount" must be non-null and numeric!');
        if (is_null($action))
            $action = self::ACTION_SALE;
        if (!in_array(strtolower($action), self::getActions()))
            throw new InvalidArgumentException('Current argument "action" not supported!');
        if (is_null($currencyCode))
            $currencyCode = self::DEFAULT_CURRENCY_CODE;
        if (!in_array(strtoupper($currencyCode), self::getSupportedCurencies()))
            throw new InvalidArgumentException('Current argument "currencyCode" not supported!');

        $postfields = self::buildQuery([
            'USER' => $this->user,
            'PWD' => $this->password,
            'SIGNATURE' => $this->signature,
            'VERSION' => $this->version,
            'PAYMENTREQUEST_0_PAYMENTACTION' => $action,
            'PAYMENTREQUEST_0_AMT' => $amount,
            'PAYMENTREQUEST_0_CURRENCYCODE' => $currencyCode,
            'RETURNURL' => $this->returnUrl,
            'CANCELURL' => $this->cancelUrl,
            'METHOD' => self::METHOD_SET_EXPRESS_CHECKOUT,
            'L_PAYMENTREQUEST_0_NAME0' => $description ? $description : Yii::t('app', 'Пополнение счета'),
            'L_PAYMENTREQUEST_0_AMT0' => $amount,
            'L_PAYMENTREQUEST_0_QTY0' => 1,
            'L_PAYMENTREQUEST_0_ITEMCATEGORY0' => 'Physical',
            'ALLOWNOTE' => 1,
        ]);

        $this->createCurlRequest($postfields);
        return $this;
    }

    /**
     * Переход на PayPal для проведения платежа.
     * 
     * @return mixed
     */
    public function approvalPayment()
    {
        if ($token = $this->getToken()) {
            if ($url = $this->createCheckoutUrl($token))
                return Yii::$app->response->redirect($url);
        }
        return false;
    }

    /**
     * Создать адрес для редиректа пользователя в PayPal для оплаты.
     * 
     * @param  string $token Токен ключ с PayPal
     * 
     * @return string Url 
     */
    public function createCheckoutUrl($token = null)
    {
        if (is_string($token))
            return $this->checkoutUrl . $token;
        throw new InvalidArgumentException("Token is null or not string!");
    }

    /**
     * [getApprovedPaymentDetails description]
     * 
     * @param  [type] $token [description]
     * 
     * @return leko\components\paypal\PayPal
     */
    public function getApprovedPaymentDetails($token = null)
    {
        if ($token) {
            $postfields = self::buildQuery([
                'USER' => $this->user,
                'PWD' => $this->password,
                'SIGNATURE' => $this->signature,
                'METHOD' => self::METHOD_GET_EXPRESS_CHECKOUT_DETAILS,
                'VERSION' => $this->version,
                'TOKEN' => $token,
            ]);

            $this->createCurlRequest($postfields);
            return $this;
        }
    }

    /**
     * [completeTransaction description]
     * 
     * @param  [type] $token   [description]
     * @param  [type] $payerID [description]
     * @param  [type] $amount  [description]
     * 
     * @return leko\components\paypal\PayPal
     */
    public function completeTransaction($token = null, $payerID = null, $amount = null, $currencyCode = null)
    {
        if (is_null($amount))
            throw new InvalidArgumentException('Current argument "amount" must be not-null!');
        if (is_null($currencyCode))
            $currencyCode = self::DEFAULT_CURRENCY_CODE;
        if ($token && $payerID) {
            $postfields = self::buildQuery([
                'USER' => $this->user,
                'PWD' => $this->password,
                'SIGNATURE' => $this->signature,
                'METHOD' => self::METHOD_DO_EXPRESS_CHECKOUT_PAYMENT,
                'VERSION' => $this->version,
                'TOKEN' => $token,
                'PAYERID' => $payerID,
                'PAYMENTREQUEST_0_PAYMENTACTION' => self::ACTION_SALE,
                'PAYMENTREQUEST_0_AMT' => $amount,
                'PAYMENTREQUEST_0_CURRENCYCODE' => $currencyCode,
            ]);

            $this->createCurlRequest($postfields);
            return $this;
        }
    }

    /**
     * Get payment system supported currencies codes.
     * 
     * @return array
     */
    public static function getSupportedCurencies()
    {
        return [
            'AUD', 'BRL', 'CAD', 'CZK', 'DKK', 'EUR', 'HKD', 'HUF', 'ILS', 'JPY', 'MYR', 'MXN',
            'NOK', 'NZD', 'PHP', 'PLN', 'GBP', 'RUB', 'SGD', 'SEK', 'CHF', 'TWD', 'THB', 'TRY', 
            'USD',
        ];
    }
}