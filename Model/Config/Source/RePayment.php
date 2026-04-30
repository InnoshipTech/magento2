<?php

namespace InnoShip\InnoShip\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Class RePayment
 * @package InnoShip\InnoShip\Model\Config\Source
 */
class RePayment implements OptionSourceInterface
{
    /** @var string */
    const BANK = 'Bank';

    /** @var string */
    const CASH = 'Cash';

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::BANK,
                'label' => __('Bank'),
            ],
            [
                'value' => self::CASH,
                'label' => __('Cash'),
            ],
        ];
    }
}
