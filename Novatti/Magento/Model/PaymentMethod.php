<?php
namespace Novatti\Magento\Model;

use Magento\Framework\DataObject;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Directory\Helper\Data as DirectoryHelper;

class PaymentMethod extends \Magento\Payment\Model\Method\AbstractMethod
{
    const METHOD_CODE = 'novatti';

    /**
     * @var string
     */
    protected $_code = self::METHOD_CODE;

    /**
     * @var string
     */
    protected $_infoBlockType = \Novatti\Magento\Block\Info\Novatti::class;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_isOffline = false;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canOrder = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_isInitializeNeeded = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canFetchTransactionInfo = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canReviewPayment = true;

    /**
     * Novatti Model
     *
     * @var \Novatti\Magento\Model\Novatti
     */
    protected $novatti;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param \Novatti\Magento\Model\Novatti $novatti
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param array $data
     * @param DirectoryHelper $directory
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Novatti\Magento\Model\Novatti $novatti,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
        $this->novatti = $novatti;
        $this->messageManager = $messageManager;
    }

    /**
     * Assign data to info model instance
     *
     * @param array|\Magento\Framework\DataObject $data
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @deprecated 100.2.0
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_object($additionalData)) {
            $additionalData = new DataObject($additionalData ?: []);
        }

        $info = $this->getInfoInstance();

        $info->addData(
            [
                'cc_type' => $additionalData->getCcType(),
                'cc_owner' => $additionalData->getCcOwner(),
                'cc_last_4' => $additionalData->getCcLast4(),
                'cc_exp_month' => $additionalData->getCcExpMonth(),
                'cc_exp_year' => $additionalData->getCcExpYear(),
                'cc_trans_id' => $additionalData->getCcTransId(),
                'last_trans_id' => $additionalData->getLastTransId()
            ]
        );

        $info->setAdditionalInformation('novatti_trans_id', $additionalData->getLastTransId());

        return $this;
    }

    /**
     * Capture specified amount with authorization
     *
     * @param InfoInterface $payment
     * @param string $amount
     * @return $this
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canCapture()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The capture action is not available.'));
        }

        $payment->setTransactionId($payment->getLastTransId());
        $payment->save();

        return $this;
    }

    /**
     * Refunds specified amount
     *
     * @param InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws LocalizedException
     */
    public function refund(InfoInterface $payment, $amount)
    {
        if (!$this->canRefund()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The refund action is not available.'));
        }

        try {
            $order = $payment->getOrder();
            $isFullRefund = false;

            $totalPaid = $order->getTotalPaid();
            $totalOnlineRefund = $order->getTotalOnlineRefunded();

            $remainingAmount = $totalPaid - $totalOnlineRefund;
            if (round($remainingAmount, 6) == 0) {
                $isFullRefund = true;
            }
            $result = $this->novatti->refundPayment($payment, $amount);

            if ($result['Result']['ResponseCode'] == 1000) {
                $payment->setLastTransId($result['Result']['PaymentID'])
                    ->setTransactionId($result['Result']['PaymentID']);
                if (!$isFullRefund) {
                    $payment->setIsTransactionClosed(false)
                    ->setShouldCloseParentTransaction(false);
                }
                        
            } else {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Novatti Refund Error')
                );
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Novatti Error: %1.', $e->getMessage()));
            throw new \Magento\Framework\Exception\LocalizedException(__('Novatti Error: %1.', $e->getMessage()));
        }

        return $this;
    }
}
