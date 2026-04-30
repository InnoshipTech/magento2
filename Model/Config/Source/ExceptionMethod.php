<?php

namespace InnoShip\InnoShip\Model\Config\Source;

use Magento\Shipping\Model\Carrier\Source\GenericInterface;

/**
 * Class Method
 * @package InnoShip\InnoShip\Model\Config\Source
 */
class ExceptionMethod implements GenericInterface
{
    /**
     * Carrier code
     *
     * @var string
     */
    protected $code = 'exceptionmethod';
    /**
     * @var \Magento\Payment\Model\Config\Source\Allmethods
     */
    private $allPaymentMethod;

    public function __construct(
        \Magento\Payment\Model\Config\Source\Allmethods $allPaymentMethod
    )
    {
        $this->allPaymentMethod = $allPaymentMethod;
    }

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return $this->allPaymentMethod->toOptionArray();
    }
}
