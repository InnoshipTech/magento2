<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Plugin\Frontend\Magento\Checkout\Model;

use InnoShip\InnoShip\Model\CourierPriceStore;
use InnoShip\InnoShip\Model\PudoAddress;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Psr\Log\LoggerInterface;

/**
 * Keeps the selected locker on the quote across every shipping-information save.
 *
 * The previous implementation compared getShippingCarrierCode() against the FULL method code
 * ("innoshipcargusgo_innoshipcargusgo_1" / "innoship_1") while that getter returns the carrier
 * alone ("innoshipcargusgo" / "innoship" — see InnoShip\InnoShip\Model\Carriergo::$_code and
 * InnoShip\InnoShip\Model\Carrier::$_code), so neither branch of the before plugin ever matched
 * and both $_pudoID and $_courierID stayed null. Its afterSave half then reloaded the quote and
 * saved it again, one extra write per request that could only ever clear the locker id.
 *
 * Everything now happens in a before plugin, on the DTO Magento is about to merge into the quote
 * shipping address. Magento\Quote\Model\Quote::setShippingAddress() merges with addData(), so a
 * key present on the DTO wins and a key absent from it leaves the stored value untouched.
 */
class ShippingInformationManagement
{
    /**
     * Carrier code and method code of the locker (PUDO) shipping method.
     */
    private const PUDO_CARRIER_CODE = 'innoshipcargusgo';
    private const PUDO_METHOD_CODE = 'innoshipcargusgo_1';

    /**
     * Carrier code and method code of the courier-choice shipping method.
     */
    private const COURIER_CARRIER_CODE = 'innoship';
    private const COURIER_METHOD_CODE = '1';

    /**
     * Quote address column holding the courier price Controller\Courier\Setcourierid stored.
     */
    private const COURIER_PRICE_FIELD = 'innoship_shipping_price';

    /**
     * @param CartRepositoryInterface $quoteRepository
     * @param PudoAddress $pudoAddress
     * @param LoggerInterface $logger
     * @param CourierPriceStore $courierPriceStore
     */
    public function __construct(
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly PudoAddress $pudoAddress,
        private readonly LoggerInterface $logger,
        private readonly CourierPriceStore $courierPriceStore,
    ) {
    }

    /**
     * Re-apply the stored locker, or clear it when the customer switched to another method.
     *
     * @param \Magento\Checkout\Model\ShippingInformationManagement $subject
     * @param int $cartId
     * @param ShippingInformationInterface $addressInformation
     * @return array
     */
    public function beforeSaveAddressInformation(
        \Magento\Checkout\Model\ShippingInformationManagement $subject,
        $cartId,
        ShippingInformationInterface $addressInformation
    ) {
        $address = $addressInformation->getShippingAddress();

        if (!$address instanceof QuoteAddress) {
            return [$cartId, $addressInformation];
        }

        $carrierCode = (string) $addressInformation->getShippingCarrierCode();
        $methodCode = (string) $addressInformation->getShippingMethodCode();

        $storedAddress = $this->getStoredShippingAddress($cartId);

        if ($carrierCode === self::PUDO_CARRIER_CODE && $methodCode === self::PUDO_METHOD_CODE) {
            $pudoId = $storedAddress !== null
                ? (int) $storedAddress->getData(PudoAddress::PUDO_ID_FIELD)
                : 0;

            if ($pudoId > 0) {
                $address->setData(PudoAddress::PUDO_ID_FIELD, $pudoId);
                // Writes the locker street/city/postcode/region and, critically, drops
                // customer_address_id, so Magento cannot re-import the customer's saved address
                // over the locker when this DTO reaches ShippingAddressManagement::assign().
                $this->pudoAddress->applyToAddress($address, $pudoId);
            }
        } else {
            // Switched away from the locker — wipe the stored locker id so it is not copied onto
            // the order at place-order time.
            $address->setData(PudoAddress::PUDO_ID_FIELD, null);
        }

        if ($carrierCode === self::COURIER_CARRIER_CODE && $methodCode === self::COURIER_METHOD_CODE) {
            $courierId = $storedAddress !== null
                ? (int) $storedAddress->getData(PudoAddress::COURIER_ID_FIELD)
                : 0;
            $storedPrice = $storedAddress !== null
                ? $storedAddress->getData(self::COURIER_PRICE_FIELD)
                : null;

            // Model\Carrier charges the stored courier price before any other rule: keep it only while
            // the address is the one Listcouriers priced it for. A new locality is never re-priced by
            // the browser, so the old city's price was charged with no courier shown.
            if ($storedPrice !== null && $storedPrice !== ''
                && !$this->courierPriceStore->isSelectionFor(
                    (int) $cartId,
                    $courierId,
                    $this->courierPriceStore->locationKey(
                        (string) $address->getCountryId(),
                        $address->getRegionId(),
                        (string) $address->getCity()
                    )
                )
            ) {
                $address->setData(PudoAddress::COURIER_ID_FIELD, null);
                $address->setData(self::COURIER_PRICE_FIELD, null);
            } elseif ($courierId > 0) {
                $address->setData(PudoAddress::COURIER_ID_FIELD, $courierId);
            }
        } else {
            $address->setData(PudoAddress::COURIER_ID_FIELD, null);
            // otherwise Model\Carrier charges it again when the customer comes back to Curier
            $address->setData(self::COURIER_PRICE_FIELD, null);
        }

        return [$cartId, $addressInformation];
    }

    /**
     * Read the quote's current shipping address, or null when the cart cannot be loaded.
     *
     * @param int $cartId
     * @return QuoteAddress|null
     */
    private function getStoredShippingAddress($cartId): ?QuoteAddress
    {
        try {
            $cart = $this->quoteRepository->getActive($cartId);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);

            return null;
        }

        if (!$cart instanceof Quote) {
            return null;
        }

        if ($cart->isVirtual()) {
            return null;
        }

        $address = $cart->getShippingAddress();

        return $address instanceof QuoteAddress ? $address : null;
    }
}
