<?php
namespace Novatti\Magento\Controller\Process;

class Transaction extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Checkout\Model\Cart
     */
    protected $cart;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var \Magento\Framework\Webapi\Rest\Request
     */
    protected $request;
    
    /**
     * @var \Novatti\Magento\Model\Novatti
     */
    protected $novatti;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Cart $cart
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Magento\Framework\Webapi\Rest\Request $request
     * @param \Novatti\Magento\Model\Novatti $novatti
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\Webapi\Rest\Request $request,
        \Novatti\Magento\Model\Novatti $novatti
    ) {
        $this->cart = $cart;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request = $request;
        $this->novatti = $novatti;
        return parent::__construct($context);
    }

    /**
     * Perform Payment on Novatti
     *
     * @return void|mixed
     */
    public function execute()
    {
        $quote = $this->cart->getQuote();
        if ($this->novatti->isActive($quote->getStoreId())) {
            try {
                $result = $this->resultJsonFactory->create();
                $params = $this->getPostParams();

                $response = $this->novatti->performPayment($quote, $params, $this->request);

                return $result->setData($response);
            } catch (\Exception $e) {
                return $result->setData([]);
            }
        }

        return $result->setData([]);
    }

    /**
     * Get Post parameters
     *
     * @return mixed
     */
    public function getPostParams()
    {
        return $this->request->getBodyParams();
    }
}
