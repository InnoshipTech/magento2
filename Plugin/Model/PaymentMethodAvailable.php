<?php

namespace InnoShip\InnoShip\Plugin\Model;

use InnoShip\InnoShip\Model\PaymentRestriction;
use Magento\Payment\Model\MethodList;
use Magento\Quote\Api\Data\CartInterface;

class PaymentMethodAvailable
{
    /** @var PaymentRestriction */
    private $paymentRestriction;

    public function __construct(
        PaymentRestriction $paymentRestriction
    ) {
        $this->paymentRestriction = $paymentRestriction;
    }

    public function afterGetAvailableMethods(MethodList $subject, $availableMethods, ?CartInterface $quote = null)
    {
        $methods = $this->paymentRestriction->getAllowedMethods($quote);
        if ($methods !== null) {
            foreach ($availableMethods as $key => $method) {
                if(in_array($method->getCode(), $methods, true) === false){
                    unset($availableMethods[$key]);
                }
            }
        }

        return $availableMethods;
    }
}
