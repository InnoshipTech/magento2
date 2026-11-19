<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Plugin\Magento\Quote\Model;

use InnoShip\InnoShip\Model\PudoAddress;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;

/**
 * Keeps a locker shipping address out of the customer's address book.
 *
 * Magento\Quote\Model\QuoteManagement::_prepareCustomerQuote(), run from submitQuote() for every
 * customer order, exports the shipping address into the address book (even as the default
 * shipping address) when:
 *
 *     !$shipping->getSameAsBilling()
 *         && (!$shipping->getCustomerId() || $shipping->getSaveInAddressBook())
 *
 * A locker order is never same-as-billing, and the shipping-information request object
 * PudoAddress::applyToAddress() works on has no quote, so it cannot set customer_id there.
 * submit() is the one point every customer order passes (storefront, web API, admin create), so
 * the address is tied to the customer and flagged "do not save" here.
 *
 * beforePlaceOrder() refuses a locker order without a locker. placeOrder() is the service every
 * storefront path reaches (GuestCartManagement::placeOrder(),
 * PaymentInformationManagement::savePaymentInformationAndPlaceOrder(), the Web API), and it runs
 * before submit(), so no stock is reserved for a refused attempt. Admin order creation calls
 * submit() directly and is deliberately not gated: the admin cannot pick a locker there.
 */
class QuoteManagement
{
    /** @var CartRepositoryInterface */
    private $cartRepository;

    /**
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(CartRepositoryInterface $cartRepository)
    {
        $this->cartRepository = $cartRepository;
    }

    /**
     * @param \Magento\Quote\Model\QuoteManagement $subject
     * @param int $cartId
     * @param PaymentInterface|null $paymentMethod
     * @return null
     * @throws LocalizedException
     */
    public function beforePlaceOrder(
        \Magento\Quote\Model\QuoteManagement $subject,
        $cartId,
        ?PaymentInterface $paymentMethod = null
    ) {
        /** @var Quote $quote */
        $quote = $this->cartRepository->getActive((int) $cartId);

        if ($quote->isVirtual()) {
            return null;
        }

        $shipping = $quote->getShippingAddress();

        if ($shipping
            && $shipping->getShippingMethod() === PudoAddress::PUDO_SHIPPING_METHOD
            && (int) $shipping->getInnoshipPudoId() <= 0
        ) {
            throw new LocalizedException(__('Please select a locker location before placing the order.'));
        }

        // A locker is a delivery point, never the customer's billing address: the checkout hides
        // "My billing and shipping address are the same" for Locker, this refuses the copy from any path.
        if ($shipping
            && $shipping->getShippingMethod() === PudoAddress::PUDO_SHIPPING_METHOD
            && $this->isSameAddress($shipping, $quote->getBillingAddress())
        ) {
            throw new LocalizedException(
                __('Please enter your billing address. The locker address cannot be used as billing address.')
            );
        }

        return null;
    }

    /**
     * @param Address $shipping
     * @param Address|null $billing
     * @return bool
     */
    private function isSameAddress(Address $shipping, ?Address $billing): bool
    {
        if (!$billing) {
            return false;
        }

        foreach (['street', 'city', 'postcode', 'country_id'] as $field) {
            if ($this->normalize($shipping->getData($field)) !== $this->normalize($billing->getData($field))) {
                return false;
            }
        }

        return $this->normalize($shipping->getData('street')) !== '';
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function normalize($value): string
    {
        if (is_array($value)) {
            $value = implode(' ', $value);
        }

        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $value)));
    }

    /**
     * @param \Magento\Quote\Model\QuoteManagement $subject
     * @param Quote $quote
     * @param array $orderData
     * @return array
     */
    public function beforeSubmit(
        \Magento\Quote\Model\QuoteManagement $subject,
        Quote $quote,
        $orderData = []
    ) {
        if ($quote->isVirtual() || $quote->getCustomerIsGuest() || !$quote->getCustomerId()) {
            return [$quote, $orderData];
        }

        $shipping = $quote->getShippingAddress();

        if (!$shipping
            || $shipping->getShippingMethod() !== PudoAddress::PUDO_SHIPPING_METHOD
            || $shipping->getCustomerAddressId()
        ) {
            return [$quote, $orderData];
        }

        $shipping->setCustomerId((int) $quote->getCustomerId());
        $shipping->setSaveInAddressBook(0);

        return [$quote, $orderData];
    }
}
