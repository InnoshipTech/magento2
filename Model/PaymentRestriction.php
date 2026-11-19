<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Model;

use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The payment methods allowed with the quote's InnoShip shipping method (Locker or Curier).
 * One decision for the list filter (MethodList plugin) and the availability check
 * (payment_method_is_active), so both apply the same rule.
 */
class PaymentRestriction
{
    private const LOCKER_METHOD = 'innoshipcargusgo_innoshipcargusgo_1';
    private const COURIER_METHOD = 'innoship_1';

    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
    ) {
    }

    /**
     * @param CartInterface|null $quote
     *
     * @return string[]|null allowed method codes; null = no restriction
     */
    public function getAllowedMethods(?CartInterface $quote): ?array
    {
        if (!$quote instanceof Quote) {
            return null;
        }

        $shippingMethod = (string) $quote->getShippingAddress()->getShippingMethod();
        if ($shippingMethod === self::LOCKER_METHOD) {
            $restriction = $this->config->getPaymentRestriction($this->getQuoteStoreId($quote));
        } elseif ($shippingMethod === self::COURIER_METHOD) {
            $restriction = $this->config->getPaymentRestrictionCourier($this->getQuoteStoreId($quote));
        } else {
            return null;
        }

        // explode() of an empty restriction gives [''], which used to remove every payment method.
        $methods = array_values(array_filter(array_map('trim', explode(',', (string) $restriction))));

        return $methods === [] ? null : $methods;
    }

    /**
     * @param CartInterface|null $quote
     * @param string $methodCode
     *
     * @return bool
     */
    public function isAllowed(?CartInterface $quote, string $methodCode): bool
    {
        $allowed = $this->getAllowedMethods($quote);

        return $allowed === null || in_array($methodCode, $allowed, true);
    }

    /**
     * The quote's store: in admin order create the current store is the admin store.
     */
    private function getQuoteStoreId(CartInterface $quote): int
    {
        return (int) ($quote->getStoreId() ?: $this->storeManager->getStore()->getId());
    }
}
