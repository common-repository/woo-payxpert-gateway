<?php
/**
 * Client for the PayXpert Payment Gateway
 *
 * PHP dependencies:
 * PHP >= 5.2.0
 * PHP cURL library
 * PHP JSON extension
 *
 * @version 0202-4 2015-01-21
 * @author Francois Bard <fba@payxpert.com>
 * @author Régis Vidal <regis@payxpert.com>
 * @copyright Digital Media World 2012-2014
 * @package GatewayClient
 *
 */




/**
 * Client class for the PayXpert Gateway
 *
 * This class allows a client to manage several transactions with the PayXpert Gateway.
 *
 * @author Francois Bard <fba@payxpert.com>
 */
class PXP_GatewayClient
{
    private $url;
    private $originatorID;
    private $password;

    private $proxy;

    private $extraCurlOptions = array();

    /**
     * Create a new Gateway with optional global user and password
     *
     * @param string $url;
     * @param string|null $originatorID
     * @param string|null $password
     */
    public function __construct($url, $originatorID = null, $password = null)
    {
        $this->url          = $url;
        $this->originatorID = $originatorID;
        $this->password     = $password;
    }

    /**
     * Sets the proxy information
     *
     * Optional
     *
     * @param string|null $host
     * @param string|null $port
     * @param string|null $username
     * @param string|null $password
     */
    public function setProxy($host = null, $port = null, $username = null, $password = null)
    {
        $this->proxy           = new stdClass();
        $this->proxy->host     = $host;
        $this->proxy->port     = $port;
        $this->proxy->username = $username;
        $this->proxy->password = $password;
    }

    /**
     * Add extra curl options (internal use only)
     */
    public function setExtraCurlOption($name, $value) {
        $this->extraCurlOptions[$name] = $value;
    }

    /**
     * Creates a new request
     *
     * @param string       $type
     * @param string|null  $originatorID  must be set if wasn't set in constructor
     * @param string|null  $password      must be set if wasn't set in constructor
     */
    public function newTransaction($type, $originatorID = null, $password = null)
    {
        $originatorID = isset($originatorID) ? $originatorID : $this->originatorID;
        $password     = isset($password)     ? $password     : $this->password;

        return new GatewayTransaction($type, $originatorID, $password, $this->proxy, $this->url, $this->extraCurlOptions);
    }
}




/**
 * Custom class for the Gateway client Exceptions
 *
 * This class doesn't have any specific method or property.
 * We only use this class for its name, to separate between PHP Exceptions and our own exception.
 *
 * @author Francois Bard <fba@payxpert.com>
 */
class GatewayException extends Exception{}




/**
 * Transaction class for the PayXpert Gateway
 *
 * This class allows a client to easily build any request for the PayXpert Gateway.
 *
 * @author Francois Bard <fba@payxpert.com>
 */
class GatewayTransaction {

    private $request;
    private $response;
    private $requestObject;

    private $URL;

    private $method;
    private $proxy;

    private $extraCurlOptions;

    private $originatorID;
    private $password;

    /**
     * Constructor
     *
     * @param string $type
     * @param string $originatorID
     * @param string $password
     * @param object $proxy
     * @param string $baseURL
     * @param string $extraCurlOptions
     */
    public function __construct($type, $originatorID, $password, $proxy, $baseURL, $extraCurlOptions)
    {
        $URLMap = array(

        //Auth
        	'CCAuthorize'          => array("URL" => "transaction/authorize/creditcard"              , "method" => "POST"),

        //Sales
            'CCSale'               => array("URL" => "transaction/sale/creditcard"                   , "method" => "POST"),
            'ToditoSale'           => array("URL" => "transaction/sale/todito"                       , "method" => "POST"),

        //3DS
            '3DSCheck'             => array("URL" => "transaction/3dscheck/creditcard"               , "method" => "POST"),
            '3DSParse'             => array("URL" => "transaction/:transactionID/3dsparse"           , "method" => "POST"),

        //REFERRAL
            'Refund'               => array("URL" => "transaction/:transactionID/refund"             , "method" => "POST"),
            'Credit'               => array("URL" => "transaction/:transactionID/credit"             , "method" => "POST"),
            'Capture'              => array("URL" => "transaction/:transactionID/capture"            , "method" => "POST"),
            'Cancel'               => array("URL" => "transaction/:transactionID/cancel"             , "method" => "POST"),
            'Rebill'               => array("URL" => "transaction/:transactionID/rebill"             , "method" => "POST"),

        //BLACKLIST MANIPULATION
        	'BlacklistTransaction' => array("URL" => "transaction/:transactionID/blacklist"          , "method" => "POST"),
            'BlacklistValue'       => array("URL" => "blacklist"                                     , "method" => "POST"),

        //SUBSCRIPTION MANIPULATION
            'CancelSubscription'   => array("URL" => "subscription/:subscriptionID/cancel"           , "method" => "POST"),
            'InstantConversion'    => array("URL" => "subscription/:subscriptionID/instantconversion", "method" => "POST"),

        //STATUS
            'StatusTransaction'    => array("URL" => "transaction/:transactionID"                    , "method" => "GET"),
            'StatusSubscription'   => array("URL" => "subscription/:subscriptionID"                  , "method" => "GET"),

        //EXPORT
    		'ExportTransaction'    => array("URL" => "transactions/:transactionOperation"            , "method" => "GET"),
            'ExportSubscription'   => array("URL" => "subscriptions"                                 , "method" => "GET"),
            'ExportSubscriptionOffer' => array("URL" => "subscription/offer/:offerID"                , "method" => "GET"),
        
        );


        $this->originatorID     = $originatorID;
        $this->password         = $password;

        $this->URL              = $baseURL . $URLMap[$type]['URL'];
        $this->method           = $URLMap[$type]['method'];

        $this->proxy            = $proxy;

        $this->extraCurlOptions = $extraCurlOptions;

        $this->requestObject    = new stdClass();
    }


    ///////////////////////////////////
    //                               //
    //     SEND & HANDLE ERRORS      //
    //                               //
    ///////////////////////////////////

    /**
     * send the built request
     *
     * @return object response
     */
    public function send() {
        try {
            $this->removeNotSetOptionalParameterInURL();

            $this->encode(); //in get or post

            $this->curlSend();

            return $this->decode(); //the received json

        } catch (GatewayException $e) {
            $message = $e->getMessage();

        } catch (Exception $e) {
            $message = "Caught unexpected PHP Exception with message: {$e->getMessage()}";
        }

        return $this->setError($message, '998');
    }

    ///////////////////////////////////
    //                               //
    //   SALE TRANSACTION BUILDERS   //
    //                               //
    ///////////////////////////////////

    /**
     * Set some transaction information
     *
     * mandatory for all Sales transaction
     *
     * @param string      $amount     amount in minor unit.
     * @param string      $currency   currency in ISO 4217.
     * @param string      $orderID    unique reference to current transaction request.
     * @param string|null $customerIP IPv4 in Dot-decimal notation. Default value is remote IP or '127.0.0.1'.
     */
    public function setTransactionInformation($amount, $currency, $orderID, $customerIP = null) {
        $this->requestObject->amount     = $amount;
        $this->requestObject->currency   = $currency;
        $this->requestObject->orderID    = $orderID;
        if (empty($customerIP)) {
            if (empty($_SERVER['REMOTE_ADDR'])) {
                $customerIP = '127.0.0.1';
            } else {
                $customerIP = $_SERVER['REMOTE_ADDR'];
            }
        }
        $this->requestObject->customerIP = $customerIP;
    }

    /**
     * Set the card information
     *
     * mandatory, all sales except todito and ACH
     *
     * @param string $cardNumber
     * @param string $cardSecurityCode
     * @param string $cardHolderName
     * @param string $cardExpireMonth
     * @param string $cardExpireYear
     */
    public function setCardInformation($cardNumber, $cardSecurityCode, $cardHolderName, $cardExpireMonth, $cardExpireYear) {
        $this->requestObject->cardNumber       = $cardNumber;
        $this->requestObject->cardSecurityCode = $cardSecurityCode;
        $this->requestObject->cardHolderName   = $cardHolderName;
        $this->requestObject->cardExpireMonth  = $cardExpireMonth;
        $this->requestObject->cardExpireYear   = $cardExpireYear;
    }

    /**
     * Set the Todito card information
     *
     * mandatory, ToditoSale only
     *
     * @param string $cardNumber
     * @param string $cardNIP
     */
    public function setToditoCardInformation($cardNumber, $cardNIP) {
        $this->requestObject->cardNumber = $cardNumber;
        $this->requestObject->cardNIP    = $cardNIP;
    }

    /**
     * Set the account information
     *
     * mandatory, ACH only
     *
     * @param string $accountRoutingNumber
     * @param string $accountNumber
     * @param string $accountHolderName
     * @param string $accountBankAccountType
     * @param string $accountACHCheckType
     */
    public function setAccountInformation(
    $accountRoutingNumber,
    $accountNumber,
    $accountHolderName,
    $accountBankAccountType,
    $accountACHCheckType
    ) {
        $this->requestObject->accountRoutingNumber   = $accountRoutingNumber;
        $this->requestObject->accountNumber          = $accountNumber;
        $this->requestObject->accountHolderName      = $accountHolderName;
        $this->requestObject->accountBankAccountType = $accountBankAccountType;
        $this->requestObject->accountACHCheckType    = $accountACHCheckType;
    }

    /**
     * Set the shopper information
     *
     * mandatory, all sales
     *
     * @param string|null $shopperName          Use null or 'NA' when unavailable.
     * @param string|null $shopperAddress       Use null or 'NA' when unavailable.
     * @param string|null $shopperZipcode       Use null or 'NA' when unavailable.
     * @param string|null $shopperCity          Use null or 'NA' when unavailable.
     * @param string|null $shopperState         For US, Canada, Australia, etc... Use null or 'NA' when unavailable (it seems no states are called 'NA').
     * @param string|null $shopperCountryCode   2 letters country code as described in ISO 3166-1. Use null or 'ZZ' when not unavailable ('NA' stands for Namibia).
     * @param string|null $shopperPhone         Use null or 'NA' when unavailable.
     * @param string|null $shopperEmail         Use null or 'NA' when unavailable.
     *
     */
    public function setShopperInformation(
    $shopperName        = null,
    $shopperAddress     = null,
    $shopperZipcode     = null,
    $shopperCity        = null,
    $shopperState       = null,
    $shopperCountryCode = null,
    $shopperPhone       = null,
    $shopperEmail       = null
    ) {
        $this->requestObject->shopperName        = empty($shopperName)        ? 'NA' : $shopperName;
        $this->requestObject->shopperAddress     = empty($shopperAddress)     ? 'NA' : $shopperAddress;
        $this->requestObject->shopperZipcode     = empty($shopperZipcode)     ? 'NA' : $shopperZipcode;
        $this->requestObject->shopperCity        = empty($shopperCity)        ? 'NA' : $shopperCity;
        $this->requestObject->shopperState       = empty($shopperState)       ? 'NA' : $shopperState;
        $this->requestObject->shopperCountryCode = empty($shopperCountryCode) ? 'ZZ' : $shopperCountryCode;
        $this->requestObject->shopperPhone       = empty($shopperPhone)       ? 'NA' : $shopperPhone;
        $this->requestObject->shopperEmail       = empty($shopperEmail)       ? 'NA' : $shopperEmail;
    }

    /**
     * Set the address verification policies
     *
     * optional, all sales
     *
     * @param string|null $AVSPolicy
     * @param string|null $FSPolicy
     */
    public function setAVSPolicy($AVSPolicy = null, $FSPolicy = null) {
        !isset($AVSPolicy) ? null : $this->requestObject->AVSPolicy = $AVSPolicy;
        !isset($FSPolicy)  ? null : $this->requestObject->FSPolicy  = $FSPolicy;
    }

    /**
     * Set the information about the order
     *
     * optional, initiating transactions only
     *
     * @param string $orderAmount
     * @param string $productID
     * @param string $comment
     * @param string $orderDescription
     */
    public function setOrder(
    $orderAmount      = null,
    $productID        = null,
    $comment          = null,
    $orderDescription = null
    ) {
        !isset($orderAmount)      ? null : $this->requestObject->orderAmount      = $orderAmount;
        !isset($productID)        ? null : $this->requestObject->productID        = $productID;
        !isset($comment)          ? null : $this->requestObject->comment          = $comment;
        !isset($orderDescription) ? null : $this->requestObject->orderDescription = $orderDescription;
    }

    /**
     * Set the shipping address
     *
     * optional, initiating transaction only
     *
     * @param string $shipToName
     * @param string $shipToAddress
     * @param string $shipToZipcode
     * @param string $shipToCity
     * @param string $shipToState
     * @param string $shipToCountryCode
     * @param string $shipToPhone
     *
     */
    public function setShippingAddress(
    $shipToName        = null,
    $shipToAddress     = null,
    $shipToZipcode     = null,
    $shipToCity        = null,
    $shipToState       = null,
    $shipToCountryCode = null,
    $shipToPhone       = null
    ) {
        !isset($shipToName)        ? null : $this->requestObject->shipToName        = $shipToName;
        !isset($shipToAddress)     ? null : $this->requestObject->shipToAddress     = $shipToAddress;
        !isset($shipToZipcode)     ? null : $this->requestObject->shipToZipcode     = $shipToZipcode;
        !isset($shipToCity)        ? null : $this->requestObject->shipToCity        = $shipToCity;
        !isset($shipToState)       ? null : $this->requestObject->shipToState       = $shipToState;
        !isset($shipToCountryCode) ? null : $this->requestObject->shipToCountryCode = $shipToCountryCode;
        !isset($shipToPhone)       ? null : $this->requestObject->shipToPhone       = $shipToPhone;
    }

    /**
     * Set the 3Dsecure information
     *
     * optional, CCSale or CCAuthorize only
     *
     * @param string $ECI
     * @param string $XID
     * @param string $CAVV
     * @param string $CAVVAlgorithm
     */
    public function set3DSecure(
    $ECI  = null,
    $XID  = null,
    $CAVV = null,
    $CAVVAlgorithm = null
    ) {
        !isset($ECI)  ? null : $this->requestObject->ECI  = $ECI;
        !isset($XID)  ? null : $this->requestObject->XID  = $XID;
        !isset($CAVV) ? null : $this->requestObject->CAVV = $CAVV;
        !isset($CAVVAlgorithm) ? null : $this->requestObject->CAVVAlgorithm = $CAVVAlgorithm;
    }

    /**
     * Set the automatic subscription info
     *
     * optional, CCSale only.
     * MUST NOT be called if setAutomaticPartPayment or setManualSubscription have already been called.
     *
     * @param string $offerID
     */
    public function setAutomaticSubscription($offerID) {
        $this->requestObject->offerID = $offerID;
    }

    /**
     * Set the automatic part payment info
     *
     * optional, CCSale only.
     * MUST BE called after setTransactionInformation
     * MUST NOT be called if setAutomaticSubscription or setManualSubscription have already been called.
     *
     * @param string $times    (payment in 2 times)
     * @param string $interval (duration, ie "P1M" for one month)
     */
    public function setAutomaticPartPayment($times, $interval)
    {
        $totalAmount = $this->requestObject->amount;

        list($initialAmount, $rebillAmount) = $this->calculatePartPayment($totalAmount, $times);

        //set new initial amount
        $this->requestObject->amount = $initialAmount;

        //set new subscription
        $this->setManualSubscription('normal', $interval, $rebillAmount, $times-1, $interval);
    }

    /**
     * Set the manual subscription info
     *
     * optional, CCSale only.
     * MUST NOT be called if setAutomaticPartPayment or setAutomaticSubscription have already been called.
     *
     * @param string $subscriptionType (values can be 'normal' (default) 'lifetime', 'onetime', 'infinite')
     * @param string $rebillPeriod
     * @param string $rebillAmount
     * @param string $rebillMaxIteration
     * @param string|null $trialPeriod
     */
    public function setManualSubscription(
    $subscriptionType,
    $rebillPeriod,
    $rebillAmount,
    $rebillMaxIteration,
    $trialPeriod = null
    ) {
        $this->requestObject->subscriptionType    = $subscriptionType;
        $this->requestObject->rebillPeriod        = empty($rebillPeriod)       ? "P0D" : $rebillPeriod;
        $this->requestObject->rebillAmount        = empty($rebillAmount)       ? '0'   : $rebillAmount;
        $this->requestObject->rebillMaxIteration  = empty($rebillMaxIteration) ? '0'   : $rebillMaxIteration;
        $this->requestObject->trialPeriod         = empty($trialPeriod)        ? "P0D" : $trialPeriod;
    }

    /**
     * Set the affiliate information
     *
     * optional, any sale
     *
     * @param string|null $affiliateID
     * @param string|null $campaignName
     */
    public function setAffiliate(
    $affiliateID  = null,
    $campaignName = null
    ) {
        !isset($affiliateID)  ? null : $this->requestObject->affiliateID  = $affiliateID;
        !isset($campaignName) ? null : $this->requestObject->campaignName = $campaignName;
    }

    /**
     * Set the Threatmetrix Session value
     *
     * optional, any sale
     *
     * @param string $threatmetrixSession
     */
    public function setThreatmetrixSession($threatmetrixSession) {
        $this->requestObject->threatmetrixSession = $threatmetrixSession;
    }

    ///////////////////////////////////
    //                               //
    //   OTHER TRANSACTION BUILDERS  //
    //                               //
    ///////////////////////////////////

    /**
     * Set the transactionID
     *
     * mandatory for StatusTransaction, BlacklistTransaction and 3DSParse
     *
     * @param string $transactionID
     */
    public function setTransactionID($transactionID) {
        $this->setInURL("transactionID", $transactionID);
    }

    /**
     * Set the subscriptionID
     *
     * mandatory for StatusSubscription, CancelSubscription and InstantConversion
     *
     * @param string $subscriptionID
     */
    public function setSubscriptionID($subscriptionID) {
        $this->setInURL("subscriptionID", $subscriptionID);
    }

    /**
     * Set the offerID
     *
     * mandatory for SubscriptionOffer
     *
     * @param string $offerID
     */
    public function setOfferID($offerID) {
        $this->setInURL("offerID", $offerID);
    }

    /**
     * Set the information for referral transactions
     *
     * mandatory for Refund, Credit, Cancel, Capture, and Rebill.
     *
     * @param string $transactionID
     * @param string $amount
     */
    public function setReferralInformation($transactionID, $amount) {
        $this->setTransactionID($transactionID);
        $this->requestObject->amount = $amount;
    }

    /**
     * Set the card information for 3DSCheck
     *
     * mandatory, 3DSCheck only
     *
     * @param string      $cardNumber
     * @param string      $cardExpireMonth
     * @param string      $cardExpireYear
     * @param string|null $cardHolderEmail
     * @param string|null $cardSecurityCode
     */
    public function set3DSecureCardInformation($cardNumber, $cardExpireMonth, $cardExpireYear, $cardHolderEmail = null, $cardSecurityCode = null) {
        $this->requestObject->cardNumber      = $cardNumber;
        $this->requestObject->cardExpireMonth = $cardExpireMonth;
        $this->requestObject->cardExpireYear  = $cardExpireYear;
        isset($cardHolderEmail) ? $this->requestObject->cardHolderEmail = $cardHolderEmail : null;
        isset($cardSecurityCode) ? $this->requestObject->cardSecurityCode = $cardSecurityCode : null;
    }

    /**
     * Set the PaRes for 3DSParse
     *
     * mandatory, 3DSParse only
     *
     * @param string $PaRes
     */
    public function setPaRes($PaRes) {
        $this->requestObject->PaRes = $PaRes;
    }

    /**
     * Set the cancel reason code for cancelSubscription
     *
     * mandatory, CancelSubscription only
     *
     * @param string $cancelReason
     */
    public function setCancelReason($cancelReason) {
        $this->requestObject->cancelReason = $cancelReason;
    }

    /**
     * Specify wich fields must be blacklisted
     *
     * mandatory, BlacklistTransaction only
     *
     * @param bool|null $cardNumberBlackList
     * @param bool|null $shopperEmailBlackList
     * @param bool|null $customerIPBlackList
     */
    public function setBlacklistTransactionFlags($cardNumberBlackList = '0', $shopperEmailBlackList = '0', $customerIPBlackList = '0') {
        $cardNumberBlackList    ? $this->requestObject->cardNumberBlackList   = '1' : null;
        $shopperEmailBlackList  ? $this->requestObject->shopperEmailBlackList = '1' : null;
        $customerIPBlackList    ? $this->requestObject->customerIPBlackList   = '1' : null;
    }

    /**
     * Set the type and value to be blacklisted
     *
     * mandatory, BlacklistValue only
     *
     * @param string $valueType
     * @param string $value
     */
    public function setBlacklistValue($valueType, $value) {
        $this->requestObject->valueType = $valueType;
        $this->requestObject->value     = $value;
    }

    /**
     * Set the time window for export transactions
     *
     * mandatory, ExportTransaction and ExportSubscription only
     *
     * @param string $startDate
     * @param string $endDate
     */
    public function setExportInterval($startDate, $endDate) {
        $this->requestObject->startDate = $startDate;
        $this->requestObject->endDate   = $endDate;
    }

    /**
     * Set the export Allsubscriptions flag
     *
     * optional, ExportSubscription only
     *
     * @param string $allSubscriptions
     */
    public function setExportAllSubscriptions($allSubscriptions) {
        $this->requestObject->allSubscriptions = $allSubscriptions;
    }

    /**
     * Set the type of transaction for export transactions
     *
     * optional, ExportTransaction only
     *
     * @param string $transactionType
     */
    public function setExportTransactionType($transactionType) {
        $this->requestObject->transactionType = $transactionType;
    }

    /**
     * Set the operation of transaction for export transactions
     *
     * optional, ExportTransaction only
     *
     * @param string $transactionOperation
     */
    public function setExportTransactionOperation($transactionOperation) {
        $this->setInURL("transactionOperation", $transactionOperation);
    }
    
    /**
     * Set the error code for export transactions
     *
     * optional, ExportTransaction only
     *
     * @param string $transactionErrorCode
     */
    public function setExportErrorCode($transactionErrorCode) {
        $this->requestObject->transactionErrorCode = $transactionErrorCode;
    }

    ///////////////////////////////////
    //                               //
    //        PRIVATE METHODS        //
    //                               //
    ///////////////////////////////////

    /**
     * Set a value in the URL path (not as a GET or POST parameter)
     *
     * @param string $name
     * @param string $value
     */
    private function setInURL($name, $value) {
        if (substr_count($this->URL, "/:$name") == 0) {
            throw new GatewayException("Cannot set $name in URL for this transaction type");
        }

        $this->URL = str_replace("/:$name", "/$value", $this->URL);
    }

    /**
     * Remove an optional parameter from the URL path if it wasn't set by setInURL()
     */
    private function removeNotSetOptionalParameterInURL() {
        $this->URL = preg_replace("#/:[\w]+#", "", $this->URL);
    }

    /**
     * Encode the request object to a json string
     *
     * @throws GatewayException
     */
    private function encode() {
        if ('GET' == $this->method) {

            $this->URL = $this->URL . '?' . http_build_query((array)$this->requestObject);

        } elseif ('POST' == $this->method) {

            $this->request = json_encode($this->requestObject);

            if ($this->request === null) {
                $code = json_last_error();
                throw new GatewayException("Json encode failed with code $code)");
            }
        } else {
            throw new GatewayException("Unknown HTTP method {$this->method}");
        }
    }

    /**
     * Send request and receive answer using curl
     *
     * @throws GatewayException
     */
    private function curlSend()
    {
        //init curl object
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC ) ;
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->originatorID}:{$this->password}");

        //setup curl options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $this->URL);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);

        if ( "POST" == $this->method) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->request);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
        }

        //proxy info
        if (isset($this->proxy->host) && isset($this->proxy->port)) {
            curl_setopt($ch, CURLOPT_PROXY,     $this->proxy->host);
            curl_setopt($ch, CURLOPT_PROXYPORT, $this->proxy->port);

            if (isset($this->proxy->username) && isset($this->proxy->password)) {
                curl_setopt($ch, CURLOPT_PROXYAUTH,    CURLAUTH_BASIC);
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, "{$this->proxy->username}:{$this->proxy->password}");
            }
        }

        //Extra Curl Options
        foreach ($this->extraCurlOptions as $name => $value)
        {
            curl_setopt($ch, $name, $value);
        }

        $this->response = curl_exec($ch);

        $err = curl_errno($ch);
        curl_close($ch);

        if ($err != 0) {
            throw new GatewayException("Communication failed (CURL operation raised error '$err')");
        }
    }

    /**
     * Decode the response json string to an object
     *
     * @throws GatewayException
     * @return object responseObject
     */
    private function decode()
    {
        if (empty($this->response)) {
            throw new GatewayException("Response is empty (CURL returned an empty response)");
        }

        $responseObject = json_decode($this->response);

        if ( $responseObject === null ) {
            $code = json_last_error();
            throw new GatewayException("Json decode failed with code $code)");
        }

        return $responseObject;
    }

    /**
     * Calculate how to split an amount into several parts
     * (public for testing purposes only)
     *
     * @param  string $amount
     * @param  string $times
     * @throws GatewayException
     * @return array amounts
     */
    public function calculatePartPayment($amount, $times) {

        if (is_int($times) && $times > 1 && is_int($amount) && $amount >= $times) {
            $rebillAmount  = (int) floor($amount / $times);
            $initialAmount = $rebillAmount + ($amount % $times);
        } else {
            throw new GatewayException("Invalid value for amount or times");
        }

        return array($initialAmount, $rebillAmount);
    }

    /**
     * Set the error information and returns the response
     *
     * @param  string $message
     * @param  string $code
     * @return object responseObject
     */
    private function setError($message, $code)
    {
        $responseObject = new stdClass();
        $responseObject->errorMessage = $message;
        $responseObject->errorCode    = $code;

        return $responseObject;
    }
}
