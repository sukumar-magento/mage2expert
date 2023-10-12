<?php
namespace Novatti\Magento\Observer;

use Magento\Framework\Event\ObserverInterface;

class AfterPlaceOrderObserver implements ObserverInterface
{
    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $_invoiceService;

    /**
     * @var \Magento\Framework\DB\TransactionFactory
     */
    protected $_transactionFactory;

    /**
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Framework\DB\TransactionFactory $transactionFactory
     */
    public function __construct(
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\TransactionFactory $transactionFactory
    ) {
        $this->_invoiceService = $invoiceService;
        $this->_transactionFactory = $transactionFactory;
    }

    /**
     * Create invoice on payment success
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void|mixed
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();

        try {
            if (!$order->canInvoice()) {
                return null;
            }
            if (!$order->getState() == 'new') {
                return null;
            }

            $invoice = $this->_invoiceService->prepareInvoice($order);
            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
            $invoice->register();

            $transaction = $this->_transactionFactory->create()
              ->addObject($invoice)
              ->addObject($invoice->getOrder());

            $transaction->save();

        } catch (\Exception $e) {
            $order->addStatusHistoryComment('Exception message: '.$e->getMessage(), false);
            $order->save();
            return null;
        }
    }
}
