<?php

namespace InnoShip\InnoShip\Model\Config\Source;

use Magento\Shipping\Model\Carrier\Source\GenericInterface;

/**
 * Class Format
 * @package InnoShip\InnoShip\Model\Config\Source
 */
class Format implements GenericInterface
{
    /**
     * Carrier code
     *
     * @var string
     */
    protected $code = 'format';

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'A4',
                'label' => __('A4'),
            ],
            [
                'value' => 'A6',
                'label' => __('A6'),
            ]
        ];
    }
}
