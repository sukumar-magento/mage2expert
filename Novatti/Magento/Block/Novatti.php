<?php

namespace Novatti\Magento\Block;

class Novatti extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

     /**
     * @var \Novatti\Magento\Model\Novatti
     */
    protected $novatti;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Novatti\Magento\Model\Novatti $novatti
     */
	public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Novatti\Magento\Model\Novatti $novatti        
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->novatti = $novatti;
		parent::__construct($context);
	}

    /**
     * Retrieve Mode
     *
     * @return string
     */
	public function isSandbox()
	{
		$mode = $this->novatti->isSandbox();

        if ($mode == 'sandbox') {
            return true;
        }

        return false;
	}

    /**
     * Retrieve Mode
     *
     * @return string
     */
	public function isActive()
	{
		return $this->novatti->isActive();
	}

    /**
     * Get Riskified Domain
     *
     * @return string
     */
	public function getRiskifiedDomain()
	{
		return $this->novatti->getRiskifiedDomain();
	}

    /**
     * Get Quote ID
     *
     * @return string
     */
    public function getQuoteId(): int
    {
        return (int)$this->checkoutSession->getQuote()->getId();
    }
}
