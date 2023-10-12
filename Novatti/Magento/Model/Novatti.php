<?php
namespace Novatti\Magento\Model;

use \Magento\Store\Model\ScopeInterface;

class Novatti
{
    const SANDBOX_URL = 'https://test-api.novattipayments.com';

    const PRODUCTION_URL = 'https://api.novattipayments.com';
    
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;
    
    /**
     * @var \Magento\Framework\HTTP\Client\Curl
     */
    protected $curl;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $json;

    /**
     * @var \Magento\Framework\View\Asset\Repository
     */
    protected $moduleAssetDir;

    /**
     * @var \Magento\Catalog\Model\CategoryFactory
     */
    protected $categoryFactory;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepositoryInterface;

    /**
     * @var \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress
     */
    protected $remoteAddress;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\HTTP\Client\Curl $curl
     * @param \Magento\Framework\Serialize\Serializer\Json $json
     * @param \Magento\Framework\View\Asset\Repository $moduleAssetDir
     * @param \Magento\Catalog\Model\CategoryFactory $categoryFactory
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepositoryInterface
     * @param \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Framework\Serialize\Serializer\Json $json,
        \Magento\Framework\View\Asset\Repository $moduleAssetDir,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepositoryInterface,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->curl = $curl;
        $this->json = $json;
        $this->moduleAssetDir = $moduleAssetDir;
        $this->categoryFactory = $categoryFactory;
        $this->customerRepositoryInterface = $customerRepositoryInterface;
        $this->remoteAddress = $remoteAddress;
        $this->storeManager = $storeManager;
        $this->_logger = $logger;
    }

    /**
     * Get Token from Novatti API
     *
     * @param string $clientId
     * @param string $clientSecret
     * @return string
     */
    public function getToken($clientId, $clientSecret)
    {
        $this->curl->addHeader("Content-Type", "application/json");
        $this->curl->setCredentials($clientId, $clientSecret);
        $this->curl->post($this->getGatewayUrl().'/token?grant_type=client_credentials', []);
        $result = $this->curl->getBody();
        $response = $this->json->unserialize($result);
        return $response['access_token'];
    }

    /**
     * Refund payment from Novatti API
     *
     * @param \Magento\Framework\DataObject|\Magento\Payment\Model\InfoInterface|Payment $payment
     * @param float $amount
     * @return mixed
     */
    public function refundPayment($payment, $amount)
    {
        $token = $this->getToken($this->getClientId($payment->getOrder()->getStoreId()), $this->getClientSecret($payment->getOrder()->getStoreId()));
        $currency = $payment->getOrder()->getOrderCurrencyCode();
        $payload = [
            'Header' => [
                'TransactionType' => 'RefundPayment',
                'MerchantID' => $this->getMerchantId($payment->getOrder()->getStoreId()),
                'UserID' => $this->getUserId($payment->getOrder()->getStoreId()),
                'Version' => '2'
            ],
            'Transaction' => [
                'MerchantTxnID' => $payment->getParentTransactionId(),
                'Currency' => $currency,
                'Amount' => round($amount, 0)*1000
            ]
        ];

        $this->curl->addHeader("Content-Type", "application/json");
        $this->curl->addHeader("Authorization", "Bearer ".$token);
        $this->curl->post($this->getGatewayUrl(), $this->json->serialize($payload));
        $response = $this->curl->getBody();
        $result = $this->json->unserialize($response);
        $this->_logger->log('DEBUG','refund', $result);
        return $result;
    }

    /**
     * Perform payment from Novatti API
     *
     * @param \Magento\Framework\DataObject $quote
     * @param array $params
     * @return mixed
     */

    public function performPayment($quote, $params, $request)
    {
        $device = 'web';
        $language_name = 'en';
        $billing = $quote->getBillingAddress();
        $shipping = $quote->getShippingAddress();
        $quoteItems = $quote->getAllVisibleItems();
        $ip = $this->remoteAddress->getRemoteAddress();
        $userAgent = $request->getHeader('useragent');
        $server = $request->getServer();
        $baseUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
        $createdAt = date("dmYhis");
        $total_discounts = 0;
        $total_shipping = $shippingAmount = $quote->getShippingAddress()->getShippingAmount();
        $items = [];

        foreach ($quoteItems as $item) {
            $categoryIds = $item->getProduct()->getCategoryIds();
            $itemCategories = [];
            foreach ($categoryIds as $categoryId) {
                $itemCategories[] =  $category = $this->categoryFactory->create()->load($categoryId)->getName();
            }
            $items[] = array (
                'ItemAmount' => round($item->getPrice(), 0)*1000,
                'ItemQuantity' => round($item->getQty(), 0),
                'ItemName' => $item->getName(),
                'ItemCode' => $item->getSku(),
                'ItemCategory' => $itemCategories
            );
            $total_discounts += $item->getDiscountAmount();
        }
        
        if ($customerId = $quote->getCustomerId()) {
            $customer = $this->customerRepositoryInterface->getById($customerId);
            $createdAt = date("dmYhis", strtotime($customer->getCreatedAt()));
        }
        
       $billingStreet = $billing->getStreet();
       $shippingStreet = $shipping->getStreet();
        

        $payload = [
            'Header' => [
                'TransactionType' => 'OneStepPayment',
                'MerchantID' => $this->getMerchantId($quote->getStoreId()),
                'UserID' => $this->getUserId($quote->getStoreId()),
                'Version' => '2'
            ],
            'Transaction' => [
                'MerchantTxnID' => $params['txn_id'],
                'TransactionTimestamp' => date("dmYhis"),
                'Currency' => $quote->getQuoteCurrencyCode(),
                'Method' => 'CC',
                'Amount' => round($quote->getGrandTotal(), 0)*1000,
                'DiscountSaving' => round($total_discounts, 0)*1000,
                'ShippingAmount' => round($total_shipping, 0)*1000,
                'ClientType' => $device,
                'Language' => $language_name,
                'ChannelType' => '07',
                'RequestorType' => 'physical',
                'StoredCredentialType' => '01'
            ],
            'SecureTokenHolder' => [
                'SecureToken' => $params['secure_token']
            ],
            'BillingAddress' => [
                'CustomerFirstName' => $billing->getFirstname(),
                'CustomerName' => $billing->getLastname(),
                'Street1' => $billingStreet[0],
                'Street2' => isset($billingStreet[1]) ? $billingStreet[1] : '',
                'Country' => strtoupper($billing->getCountryId()),
                'City' => $billing->getCity(),
                'Zip' => $billing->getPostcode(),
                'State' => $billing->getRegion()
            ],
            'Customer' => [
                'EmailAddress' => $quote->getCustomerId() ? $quote->getCustomerEmail() : $params['guest_email'],
                'CustomerID' => $quote->getCustomerId() ? $quote->getCustomerId() : rand(1111111111, 9999999999),
                'ExistingCustomer' => $quote->getCustomerId() ? 'registered' : 'guest_user',
                'IPAddress' => $ip,
                'TelNo' => $billing->getTelephone(),
                'SessionID' => $quote->getId(),
                'Browser' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''
            ],
            'ExtendedCustomerData' => [
                'AccountCreated' => $createdAt,
            ],
            'ShippingAddress' => [
                'CustomerName' => $shipping->getFirstname() . ' ' . $shipping->getLastname(),
                'Street1' => $shippingStreet[0],
                'Street2' => isset($shippingStreet[1]) ? $shippingStreet[1] : '',
                'City' => $shipping->getCity(),
                'Country' => strtoupper($shipping->getCountryId()),
                'ShippingPhone' => $shipping->getTelephone(),
                'State' => $billing->getRegion(),
                'Zip' => $shipping->getPostcode(),
                'ShippingMethod' => $quote->getShippingAddress()->getShippingMethod(),
                'ShippingProvider' => 'false'
            ],
            'Item' => $items,
            'SoftDescriptor' => [
                'DescriptorType' => $baseUrl
            ]
        ];
        
        if ($params['token']) {
            $this->_logger->log('DEBUG', 'novatti_request', $payload);
            $this->curl->addHeader("Content-Type", "application/json");
            $this->curl->addHeader("Authorization", "Bearer ".$params['token']);
            $this->curl->post($this->getGatewayUrl(), $this->json->serialize($payload));
            $headers = $this->curl->getHeaders();
            $response = $this->curl->getBody();
            if (!$response) {
                $response = $this->json->serialize(['data' => ['Result' => ['ResponseCode' => '0000', 'ResponseMessage' => 'Payment failed. Please contact merchant.']]]);
            }
        } else {
            $response = $this->json->serialize(['data' => ['Result' => ['ResponseCode' => '0000', 'ResponseMessage' => 'Token expired']]]);
        }
        $result = $this->json->unserialize($response);
        $this->_logger->log('DEBUG', 'novatti_response', $result);
        
        return $response;
    }

    /**
     * Get Payment Logo
     *
     * @return string
     */
    public function getLogoSrc($storeId = null)
    {
        if ($logo = $this->scopeConfig->getValue("payment/novatti/logo", ScopeInterface::SCOPE_STORE, $storeId)) {
            return $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA).'novatti/'.$logo;
        }
        return $this->moduleAssetDir->getUrl("Novatti_Magento::images/novatti_logo.png");
    }

    /**
     * Get Payment Description
     *
     * @return string
     */
    public function getDescription($storeId = null)
    {
        return  $this->scopeConfig->getValue("payment/novatti/description", ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Retrieve Gateway url
     *
     * @return string
     */
    public function getGatewayUrl()
    {
        if ($this->isSandbox()) {
            return self::SANDBOX_URL;
        } else {
            return self::PRODUCTION_URL;
        }
    }

    /**
     * Retrieve Merchant Id from payment configuration
     *
     * @param int|null $storeId
     * @return string
     */
    private function getClientId($storeId = null)
    {
        return $this->scopeConfig->getValue("payment/novatti/client_id", ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Retrieve Client Id from payment configuration
     *
     * @param int|null $storeId
     * @return string
     */
    private function getClientSecret($storeId = null)
    {
        return $this->scopeConfig->getValue("payment/novatti/client_secret", ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Retrieve Merchant Id from payment configuration
     *
     * @param int|null $storeId
     * @return string
     */
    private function getMerchantId($storeId = null)
    {
        return $this->scopeConfig->getValue("payment/novatti/merchant_id", ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Retrieve User Id from payment configuration
     *
     * @param int|null $storeId
     * @return string
     */
    private function getUserId($storeId = null)
    {
        return $this->scopeConfig->getValue("payment/novatti/user_id", ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Retrieve Method Active from payment configuration
     *
     * @param int|null $storeId
     * @return string
     */
    public function isActive($storeId = null)
    {
        return $this->scopeConfig->getValue("payment/novatti/active", ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Retrieve Mode from payment configuration
     *
     * @param int|null $storeId
     * @return string
     */
    public function isSandbox($storeId = null)
    {
        return $this->scopeConfig->getValue("payment/novatti/mode", ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Get Riskified Domain from payment configuration
     *
     * @param int|null $storeId
     * @return string
     */
    public function getRiskifiedDomain($storeId = null)
    {
        return $this->scopeConfig->getValue("payment/novatti/riskified_domain", ScopeInterface::SCOPE_STORE, $storeId);
    }
}
