<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Plugin\Magento\Checkout\Model;

use InnoShip\InnoShip\Model\PudoAddress;
use Magento\Checkout\Api\Data\TotalsInformationInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;

/**
 * Keeps the selected locker on the quote shipping address through a totals estimate.
 *
 * Magento\Checkout\Model\TotalsInformationManagement::calculate() merges the posted address into the
 * quote shipping address (Quote::setShippingAddress() -> addData()) and, when a carrier and method are
 * posted, saves that row. The checkout posts the browser's shipping address there: for a customer with
 * saved addresses that is the saved card, never the locker (the locker address lives on the server,
 * Model/PudoAddress). Core estimateTotals() sends countryId, region, regionId and postcode, and
 * Amasty_Conditions recollect-totals.js adds city, so a totals refresh after shipping-information
 * left the locker street with the customer's own city, county and postcode (order 000000347).
 *
 * Re-apply the stored locker to the posted address when the posted method is the locker method, or
 * when no method is posted and the quote is on Locker. A posted non-locker method (the customer is
 * leaving Locker) is left alone.
 */
class TotalsInformationManagement
{
    /**
     * @param CartRepositoryInterface $cartRepository
     * @param PudoAddress $pudoAddress
     */
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly PudoAddress $pudoAddress,
    ) {
    }

    /**
     * @param \Magento\Checkout\Model\TotalsInformationManagement $subject
     * @param int $cartId
     * @param TotalsInformationInterface $addressInformation
     * @return null
     */
    public function beforeCalculate(
        \Magento\Checkout\Model\TotalsInformationManagement $subject,
        $cartId,
        TotalsInformationInterface $addressInformation
    ) {
        $address = $addressInformation->getAddress();

        if (!$address instanceof QuoteAddress) {
            return null;
        }

        $carrierCode = (string) $addressInformation->getShippingCarrierCode();
        $methodCode = (string) $addressInformation->getShippingMethodCode();
        $postedMethod = $carrierCode !== '' && $methodCode !== '' ? $carrierCode . '_' . $methodCode : '';

        if ($postedMethod !== '' && $postedMethod !== PudoAddress::PUDO_SHIPPING_METHOD) {
            return null;
        }

        try {
            /** @var Quote $quote */
            $quote = $this->cartRepository->get((int) $cartId);
        } catch (NoSuchEntityException $e) {
            // calculate() loads the same cart and reports the missing quote itself
            return null;
        }

        if ($quote->isVirtual()) {
            return null;
        }

        $stored = $quote->getShippingAddress();

        if (!$stored) {
            return null;
        }

        if ($postedMethod === '' && $stored->getShippingMethod() !== PudoAddress::PUDO_SHIPPING_METHOD) {
            return null;
        }

        $pudoId = (int) $stored->getData(PudoAddress::PUDO_ID_FIELD);

        if ($pudoId > 0) {
            $this->pudoAddress->applyToAddress($address, $pudoId);
        }

        return null;
    }
}
