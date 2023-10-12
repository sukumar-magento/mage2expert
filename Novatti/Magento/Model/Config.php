<?php

namespace Novatti\Magento\Model;

use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Framework\App\Config\Storage\WriterInterface;

class Config
{
    const KEY_ALLOW_SPECIFIC = 'allowspecific';

    const KEY_SPECIFIC_COUNTRY = 'specificcountry';

    const KEY_ACTIVE = 'active';

    const MERCHANT_ID = 'merchant_id';

    const CLIENT_ID = 'client_id';

    const CLIENT_SECRET = 'client_secret';

    /**
     * @var string
     */
    protected $methodCode = 'novatti';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * @var int
     */
    protected $storeId = null;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param WriterInterface $configWriter
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        WriterInterface $configWriter
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
    }

    /**
     * Retrieve Merchant Id from payment configuration
     *
     * @return string
     */
    public function getMerchantId()
    {
        return $this->getConfigData(self::MERCHANT_ID);
    }

    /**
     * Retrieve Client Id from payment configuration
     *
     * @return string
     */
    public function getClientId()
    {
        return $this->getConfigData(self::CLIENT_ID);
    }

    /**
     * Retrieve Client Secret from payment configuration
     *
     * @return string
     */
    public function getClientSecret()
    {
        return $this->getConfigData(self::CLIENT_SECRET);
    }

    /**
     * Set Store Id
     *
     * @param int $storeId
     * @return $this
     */
    public function setStoreId($storeId)
    {
        $this->storeId = $storeId;
        return $this;
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param string $field
     * @param null|string $storeId
     *
     * @return mixed
     */
    public function getConfigData($field, $storeId = null)
    {
        if ($storeId == null) {
            $storeId = $this->storeId;
        }

        $code = $this->methodCode;

        $path = 'payment/' . $code . '/' . $field;
        return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Set information from payment configuration
     *
     * @param string $field
     * @param string $value
     *
     * @return mixed
     */
    public function setConfigData($field, $value)
    {
        $code = $this->methodCode;

        $path = 'payment/' . $code . '/' . $field;

        return $this->configWriter->save($path, $value);
    }

    /**
     * Retrieve Method Active from payment configuration
     *
     * @return bool
     */
    public function isActive()
    {
        return (bool) (int) $this->getConfigData(self::KEY_ACTIVE, $this->storeId);
    }

    /**
     * To check billing country is allowed for the payment method
     *
     * @param string $country
     * @return bool
     */
    public function canUseForCountry($country)
    {
        /*
        for specific country, the flag will set up as 1
        */
        if ($this->getConfigData(self::KEY_ALLOW_SPECIFIC) == 1) {
            $availableCountries = explode(',', $this->getConfigData(self::KEY_SPECIFIC_COUNTRY));
            if (!in_array($country, $availableCountries)) {
                return false;
            }
        }

        return true;
    }
}
