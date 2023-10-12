<?php
namespace Novatti\Magento\Block\Info;

class Novatti extends \Magento\Payment\Block\Info
{
    /**
     * @var string
     */
    protected $_template = 'Novatti_Magento::info/novatti.phtml';

    /**
     * Set Info template
     *
     * @return string
     */
    public function toPdf()
    {
        $this->setTemplate('Novatti_Magento::info/pdf/novatti.phtml');
        return $this->toHtml();
    }
}
