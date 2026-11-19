<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Plugin\Magento\Quote\Model;

use InnoShip\InnoShip\Model\PudoAddress;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Psr\Log\LoggerInterface;

/**
 * Protects a selected locker at the single point where Magento overwrites a quote shipping
 * address from the customer address book.
 *
 * Magento\Quote\Model\ShippingAddressManagement::assign() does, for every caller:
 *
 *     if ($customerAddressId) {
 *         $addressData = $this->addressRepository->getById($customerAddressId);
 *         $address = $quote->getShippingAddress()->importCustomerAddressData($addressData);
 *     }
 *
 * and every quote save reaches it through QuoteRepository\SaveHandler::save() ->
 * processShippingAssignment() -> ShippingProcessor::save(). Any caller that hands over an address
 * still carrying customer_address_id therefore replaces the locker with the customer's saved
 * address. That is why the bug only appeared for customers with at least one saved address: a
 * guest quote address has no customer_address_id, so there was nothing to re-import.
 *
 * A caller that deliberately clears the locker — the customer switching to a courier, handled in
 * the ShippingInformationManagement plugin — sends innoship_pudo_id as an explicit empty value and
 * is left alone. In every other case the locker stored on the quote wins, because re-applying it
 * is idempotent and the callers that lose it do so by accident.
 */
class ShippingAddressManagement
{
    /**
     * @param CartRepositoryInterface $quoteRepository
     * @param PudoAddress $pudoAddress
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly PudoAddress $pudoAddress,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Re-apply the quote's stored locker to an address that was built without knowledge of it.
     *
     * @param \Magento\Quote\Model\ShippingAddressManagement $subject
     * @param int $cartId
     * @param AddressInterface $address
     * @return array
     */
    public function beforeAssign(
        \Magento\Quote\Model\ShippingAddressManagement $subject,
        $cartId,
        AddressInterface $address
    ) {
        if (!$address instanceof QuoteAddress) {
            return [$cartId, $address];
        }

        $pudoId = (int) $address->getData(PudoAddress::PUDO_ID_FIELD);

        if ($pudoId <= 0 && $address->hasData(PudoAddress::PUDO_ID_FIELD)) {
            // The caller is explicitly clearing the locker. Leave the address alone.
            return [$cartId, $address];
        }

        $storedAddress = $this->getStoredShippingAddress($cartId);

        // A fresh request DTO carrying a locker id is trusted as-is. Only the ShippingInformation
        // plugin puts one there, and only for the locker method. The stored shipping method cannot
        // be used to confirm it: when the customer switches from a courier TO a locker, the method
        // is still the courier at this point — ShippingProcessor::save() applies the new one only
        // after assign() has run.
        if ($pudoId > 0 && $storedAddress !== $address) {
            $this->pudoAddress->applyToAddress($address, $pudoId);

            return [$cartId, $address];
        }

        // Otherwise the id can only come from the quote itself, and a quote that has already moved
        // to a courier may still carry a stale one. Re-applying a locker to a courier order would
        // be worse than the bug this plugin fixes, so a non-locker method wins. An empty method is
        // allowed: the locker is chosen before the method is committed.
        if ($storedAddress === null) {
            return [$cartId, $address];
        }

        $shippingMethod = (string) $storedAddress->getShippingMethod();

        if ($shippingMethod !== '' && $shippingMethod !== PudoAddress::PUDO_SHIPPING_METHOD) {
            return [$cartId, $address];
        }

        $pudoId = (int) $storedAddress->getData(PudoAddress::PUDO_ID_FIELD);

        if ($pudoId > 0) {
            $this->pudoAddress->applyToAddress($address, $pudoId);
        }

        return [$cartId, $address];
    }

    /**
     * The quote's own shipping address, or null when the cart cannot be loaded or is virtual.
     *
     * @param int $cartId
     * @return QuoteAddress|null
     */
    private function getStoredShippingAddress($cartId): ?QuoteAddress
    {
        $cart = $this->getCart($cartId);

        if ($cart === null || $cart->isVirtual()) {
            return null;
        }

        $address = $cart->getShippingAddress();

        return $address instanceof QuoteAddress ? $address : null;
    }

    /**
     * Load the active cart, or null when it cannot be loaded.
     *
     * @param int $cartId
     * @return Quote|null
     */
    private function getCart($cartId): ?Quote
    {
        try {
            $cart = $this->quoteRepository->getActive($cartId);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);

            return null;
        }

        return $cart instanceof Quote ? $cart : null;
    }
}
