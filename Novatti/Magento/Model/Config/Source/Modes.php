<?php

namespace Novatti\Magento\Model\Config\Source;

class Modes implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * Return Modes
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'sandbox', 'label' => __('Sandbox')],
            ['value' => 'production', 'label' => __('Production')]
        ];
    }
}
