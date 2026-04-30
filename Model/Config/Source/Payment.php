<?php

namespace InnoShip\InnoShip\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Class Payment
 * @package InnoShip\InnoShip\Model\Config\Source
 */
class Payment implements OptionSourceInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'Sender',
                'label' => __('Sender'),
            ],
            [
                'value' => 'Recipient',
                'label' => __('Recipient'),
            ],
        ];
    }
}
