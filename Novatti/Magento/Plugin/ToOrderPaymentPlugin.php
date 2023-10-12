<?php
namespace Novatti\Magento\Plugin;

use Magento\Quote\Model\Quote\Payment;
use Magento\Quote\Model\Quote\Payment\ToOrderPayment;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class ToOrderPaymentPlugin
{
    /**
     * @param ToOrderPayment $subject
     * @param OrderPaymentInterface $result
     * @param Payment $object
     * @param array $data
     * @return OrderPaymentInterface
     */
    public function afterConvert(
        ToOrderPayment $subject,
        OrderPaymentInterface $result,
        Payment $object,
        $data = []
    ): OrderPaymentInterface {
        if ($result->getMethod() !== 'novatti') {
            return $result;
        }

        $navAttriTransId = $object->getAdditionalInformation('novatti_trans_id');

        if (!empty($navAttriTransId)) {
            $result->setLastTransId($navAttriTransId);
            $result->setCcTransId($navAttriTransId);
        }
        
        return $result;
    }
}
