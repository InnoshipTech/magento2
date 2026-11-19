<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Observer;

use InnoShip\InnoShip\Model\PaymentRestriction;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\Data\CartInterface;

/**
 * payment_method_is_active: refuses a payment method the quote's InnoShip shipping method does not
 * allow (Quote\Payment::importData() checks isAvailable()).
 */
class RestrictPaymentMethod implements ObserverInterface
{
    public function __construct(
        private readonly PaymentRestriction $paymentRestriction,
    ) {
    }

    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $result = $observer->getEvent()->getData('result');
        $method = $observer->getEvent()->getData('method_instance');
        $quote = $observer->getEvent()->getData('quote');

        if (!$result || !$result->getData('is_available')
            || !$method instanceof MethodInterface
            || !$quote instanceof CartInterface
        ) {
            return;
        }

        if (!$this->paymentRestriction->isAllowed($quote, (string) $method->getCode())) {
            $result->setData('is_available', false);
        }
    }
}
