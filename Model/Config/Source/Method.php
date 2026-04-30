<?php

namespace InnoShip\InnoShip\Model\Config\Source;

use Magento\Shipping\Model\Carrier\Source\GenericInterface;

/**
 * Class Method
 * @package InnoShip\InnoShip\Model\Config\Source
 */
class Method implements GenericInterface
{
    /**
     * Carrier code
     *
     * @var string
     */
    protected $code = 'method';

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 1,
                'label' => __('Innoship'),
            ]
        ];
    }
}
