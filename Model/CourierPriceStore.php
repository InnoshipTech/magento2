<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Model;

use Magento\Checkout\Model\Session as CheckoutSession;

/**
 * Courier prices quoted to this visitor, kept server-side.
 *
 * Controller\Courier\Listcouriers prices every courier through the InnoShip API; the customer then
 * picks one and Controller\Courier\Setcourierid stores that price on the quote, where
 * Model\Carrier::collectRates() uses it as the courier rate. Setcourierid reads the price from here.
 */
class CourierPriceStore
{
    private const SESSION_KEY = 'innoship_courier_prices';
    private const SELECTION_KEY = 'innoship_courier_selection';

    /**
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
    ) {
    }

    /**
     * Remember the prices of the last courier list shown for a quote.
     *
     * @param int $quoteId
     * @param array<int, array{price: float|null}> $couriers Keyed by courier id.
     * @return void
     */
    public function save(int $quoteId, array $couriers, string $location = ''): void
    {
        $prices = [];
        foreach ($couriers as $courierId => $courier) {
            if (isset($courier['price']) && is_numeric($courier['price'])) {
                $prices[(int) $courierId] = (float) $courier['price'];
            }
        }

        $this->checkoutSession->setData(self::SESSION_KEY, [
            'quote_id' => $quoteId,
            'prices' => $prices,
            'location' => $location,
        ]);
    }

    /**
     * The address a courier list is priced for: country, county (region id) and locality, the
     * locality case- and diacritics-insensitive (ş/ș, ţ/ț both occur). The county is part of the key:
     * one locality name exists in several counties (Ineu, Arad and Ineu, Bihor are priced differently).
     *
     * @param string|null $countryId
     * @param int|string|null $regionId
     * @param string|null $city
     * @return string
     */
    public function locationKey(?string $countryId, int|string|null $regionId, ?string $city): string
    {
        $city = strtr(
            mb_strtolower(trim((string) $city), 'UTF-8'),
            ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't']
        );

        return strtoupper(trim((string) $countryId)) . '|' . (int) $regionId . '|'
            . preg_replace('/\s+/u', ' ', $city);
    }

    /**
     * Record the courier Setcourierid charged, with the address its price was quoted for.
     *
     * @param int $quoteId
     * @param int $courierId
     * @return void
     */
    public function rememberSelection(int $quoteId, int $courierId): void
    {
        $stored = $this->checkoutSession->getData(self::SESSION_KEY);
        $location = is_array($stored) && (int) ($stored['quote_id'] ?? 0) === $quoteId
            ? (string) ($stored['location'] ?? '')
            : '';

        $this->checkoutSession->setData(self::SELECTION_KEY, [
            'quote_id' => $quoteId,
            'courier_id' => $courierId,
            'location' => $location,
        ]);
    }

    /**
     * Whether the courier price stored on the quote was quoted for this courier and this address.
     * False when nothing was recorded (another session, a list priced without a location).
     *
     * @param int $quoteId
     * @param int $courierId
     * @param string $location A locationKey() of the address being saved.
     * @return bool
     */
    public function isSelectionFor(int $quoteId, int $courierId, string $location): bool
    {
        $selection = $this->checkoutSession->getData(self::SELECTION_KEY);

        return is_array($selection)
            && (int) ($selection['quote_id'] ?? 0) === $quoteId
            && (int) ($selection['courier_id'] ?? 0) === $courierId
            && ($selection['location'] ?? '') !== ''
            && $selection['location'] === $location;
    }

    /**
     * The price quoted for a courier on this quote, or null when none was quoted.
     *
     * @param int $quoteId
     * @param int $courierId
     * @return float|null
     */
    public function get(int $quoteId, int $courierId): ?float
    {
        $stored = $this->checkoutSession->getData(self::SESSION_KEY);

        if (!is_array($stored) || (int) ($stored['quote_id'] ?? 0) !== $quoteId) {
            return null;
        }

        $price = $stored['prices'][$courierId] ?? null;

        return $price === null ? null : (float) $price;
    }
}
