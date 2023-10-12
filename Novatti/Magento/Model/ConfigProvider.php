<?php
namespace Novatti\Magento\Model;

use Magento\Payment\Helper\Data as PaymentHelper;
use Novatti\Magento\Model\PaymentMethod;
use Magento\Checkout\Model\ConfigProviderInterface;

class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string[]
     */
    protected $methodCode = PaymentMethod::METHOD_CODE;

    /**
     * @var \Novatti\Magento\Model\Config
     */
    protected $config;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Magento\Framework\Url
     */
    protected $urlBuilder;

    /**
     * Novatti Model
     *
     * @var \Novatti\Magento\Model\Novatti
     */
    protected $novatti;

    /**
     * @param \Magento\Framework\View\Asset\Repository $assetRepo
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\Url $urlBuilder
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Payment\Helper\Data $paymentHelper
     * @param \Novatti\Magento\Model\Config $config
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Novatti\Magento\Model\Novatti $novatti
     */
    public function __construct(
        \Magento\Framework\View\Asset\Repository $assetRepo,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\Url $urlBuilder,
        \Psr\Log\LoggerInterface $logger,
        PaymentHelper $paymentHelper,
        \Novatti\Magento\Model\Config $config,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Novatti\Magento\Model\Novatti $novatti
    ) {
        $this->assetRepo = $assetRepo;
        $this->request = $request;
        $this->urlBuilder = $urlBuilder;
        $this->logger = $logger;
        $this->methodCode = PaymentMethod::METHOD_CODE;
        $this->method = $paymentHelper->getMethodInstance(PaymentMethod::METHOD_CODE);
        $this->config = $config;
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->novatti = $novatti;
    }

    /**
     * Retrieve Information from payment configuration
     *
     * @return array|void
     */
    public function getConfig()
    {
        if (!$this->config->isActive()) {
            return [];
        }

        $accessToken = $this->novatti->getToken($this->config->getClientId(), $this->config->getClientSecret());

        $config = [
            'payment' => [
                'novatti' => [
                    'merchant_id' => $this->config->getMerchantId(),
                    'token' => ($accessToken) ? $accessToken : '',
                    'paymentLogoSrc' => $this->novatti->getLogoSrc(),
                    'paymentDescription' => $this->novatti->getDescription(),
                ],
            ],
        ];

        return $config;
    }
}
