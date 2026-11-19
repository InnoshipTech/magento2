<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Model\Checkout;

use InnoShip\InnoShip\Model\PudoAddress;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Psr\Log\LoggerInterface;

/**
 * checkoutConfig.innoshipSelectedLocker: the locker already stored on the quote ({id, address}), or null.
 *
 * checkout.js clears its localStorage locker keys on every page load, so after a reload the Locker
 * method was still selected and quote_address.innoship_pudo_id still set, but the "Shipping point
 * selected" line stayed empty (N17). checkout.js seeds those keys from this value; the address uses
 * the same format the locker map builds (addressText, localityName, countyName, postalCode).
 */
class SelectedLockerConfigProvider implements ConfigProviderInterface
{
    /** @var CheckoutSession */
    private $checkoutSession;

    /** @var PudoAddress */
    private $pudoAddress;

    /** @var LoggerInterface */
    private $logger;

    /**
     * @param CheckoutSession $checkoutSession
     * @param PudoAddress $pudoAddress
     * @param LoggerInterface $logger
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        PudoAddress $pudoAddress,
        LoggerInterface $logger
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->pudoAddress = $pudoAddress;
        $this->logger = $logger;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return [
            'innoshipSelectedLocker' => $this->getSelectedLocker(),
        ];
    }

    /**
     * @return array|null
     */
    private function getSelectedLocker(): ?array
    {
        try {
            if (!$this->checkoutSession->getQuoteId()) {
                return null;
            }

            $address = $this->checkoutSession->getQuote()->getShippingAddress();
            $method = (string) $address->getShippingMethod();
            // Plain checkout saves the method only at Next: a picked locker sits on a quote with no method yet (#B2-6).
            if ($method !== '' && $method !== PudoAddress::PUDO_SHIPPING_METHOD) {
                return null;
            }

            $pudoId = (int) $address->getData(PudoAddress::PUDO_ID_FIELD);
            $pudo = $this->pudoAddress->getPudoRow($pudoId);
            if ($pudo === null) {
                return null;
            }

            return [
                'id' => $pudoId,
                'address' => implode(', ', [
                    (string) ($pudo['addressText'] ?? ''),
                    (string) ($pudo['localityName'] ?? ''),
                    (string) ($pudo['countyName'] ?? ''),
                    (string) ($pudo['postalCode'] ?? ''),
                ]),
            ];
        } catch (\Throwable $e) {
            // Checkout must render without it; the locker line then stays empty as before.
            $this->logger->error('InnoShip selected locker lookup failed: ' . $e->getMessage(), ['exception' => $e]);

            return null;
        }
    }
}
