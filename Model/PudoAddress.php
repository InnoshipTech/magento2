<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Model;

use Magento\Directory\Model\Region;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory as RegionCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Quote\Model\Quote\Address as QuoteAddress;

/**
 * Writes a selected locker onto a quote shipping address.
 *
 * Before this class existed the locker address was only typed into the checkout form by
 * JavaScript, and nothing but innoship_pudo_id ever reached the quote. Magento re-imports the
 * customer address book over any quote shipping address that still carries a customer_address_id
 * (Magento\Quote\Model\ShippingAddressManagement::assign(), reached on every quote save through
 * Magento\Quote\Model\QuoteRepository\SaveHandler::save()), so for any customer with at least one
 * saved address the locker was silently replaced by that saved address. Guests were unaffected
 * because their quote address has no customer_address_id.
 *
 * Applying the locker server-side and detaching the address from the address book removes that
 * dependency for every entry point: the "confirm locker" controller, the shipping-information
 * web API call, and anything else that saves the quote afterwards.
 */
class PudoAddress
{
    /**
     * Quote/order address columns added by this module (etc/db_schema.xml).
     */
    public const PUDO_ID_FIELD = 'innoship_pudo_id';
    public const COURIER_ID_FIELD = 'innoship_courier_id';

    /**
     * Full shipping method code ("<carrier>_<method>") of the locker method.
     */
    public const PUDO_SHIPPING_METHOD = 'innoshipcargusgo_innoshipcargusgo_1';

    /**
     * Romanian diacritics, folded so an InnoShip county name matches a directory region name.
     */
    private const DIACRITIC_MAP = [
        'Ă' => 'a', 'ă' => 'a',
        'Â' => 'a', 'â' => 'a',
        'Î' => 'i', 'î' => 'i',
        'Ș' => 's', 'ș' => 's',
        'Ş' => 's', 'ş' => 's',
        'Ț' => 't', 'ț' => 't',
        'Ţ' => 't', 'ţ' => 't',
    ];

    /**
     * @var array<int, array<string, mixed>|null>
     */
    private array $pudoRows = [];

    /**
     * Resolved regions, keyed "<countryId>|<folded county name>". applyToAddress() runs on every
     * shipping-information save of a locker cart, so the directory lookup is done once per request.
     *
     * @var array<string, Region|null>
     */
    private array $regions = [];

    /**
     * @param ResourceConnection $resourceConnection
     * @param RegionCollectionFactory $regionCollectionFactory
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly RegionCollectionFactory $regionCollectionFactory,
    ) {
    }

    /**
     * Load the locker row for a PUDO id.
     *
     * @param int $pudoId
     * @return array<string, mixed>|null
     */
    public function getPudoRow(int $pudoId): ?array
    {
        if ($pudoId <= 0) {
            return null;
        }

        if (array_key_exists($pudoId, $this->pudoRows)) {
            return $this->pudoRows[$pudoId];
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName('innoship_pudo'))
            ->where('pudo_id = :pudoId')
            ->limit(1);

        $row = $connection->fetchRow($select, ['pudoId' => $pudoId]);
        $this->pudoRows[$pudoId] = $row ?: null;

        return $this->pudoRows[$pudoId];
    }

    /**
     * Copy a locker's address onto a shipping address and detach it from the address book.
     *
     * @param QuoteAddress $address
     * @param int $pudoId
     * @return bool True when the locker was found and applied.
     */
    public function applyToAddress(QuoteAddress $address, int $pudoId): bool
    {
        $pudo = $this->getPudoRow($pudoId);
        if ($pudo === null) {
            return false;
        }

        $address->setData(self::PUDO_ID_FIELD, $pudoId);

        // The locker must never be stored in the customer's address book, and Magento must never
        // re-import a saved address over it.
        $address->setCustomerAddressId(null);
        $address->setSaveInAddressBook(0);
        $address->setSameAsBilling(0);

        // QuoteManagement::_prepareCustomerQuote() exports a shipping address that is not
        // same-as-billing into the address book when it has no customer_id, whatever
        // save_in_address_book says. Tie the locker address to the quote's customer.
        $quote = $address->getQuote();
        if ($quote && $quote->getCustomerId()) {
            $address->setCustomerId((int) $quote->getCustomerId());
        }

        if (!empty($pudo['addressText'])) {
            $address->setStreet([(string) $pudo['addressText']]);
        }

        if (!empty($pudo['localityName'])) {
            $address->setCity((string) $pudo['localityName']);
        }

        if (!empty($pudo['postalCode'])) {
            $address->setPostcode((string) $pudo['postalCode']);
        }

        if (!empty($pudo['countryCode'])) {
            $address->setCountryId((string) $pudo['countryCode']);
        }

        $region = $this->resolveRegion(
            (string) ($pudo['countyName'] ?? ''),
            (string) $address->getCountryId(),
        );

        if ($region !== null) {
            $address->setRegionId((int) $region->getId());
            $address->setRegion((string) ($region->getName() ?: $region->getDefaultName()));
        }

        $address->setCollectShippingRates(true);

        return true;
    }

    /**
     * The quote_address columns this module owns, with their current values on the address.
     *
     * These are exactly the columns applyToAddress() writes, plus innoship_pudo_id. A caller that
     * must not overwrite the rest of a stored row (Setpudo) persists only these.
     *
     * @param QuoteAddress $address
     * @return array<string, mixed>
     */
    public function getOwnedColumns(QuoteAddress $address): array
    {
        $columns = [
            self::PUDO_ID_FIELD => $address->getData(self::PUDO_ID_FIELD),
        ];

        if ((int) $address->getData(self::PUDO_ID_FIELD) <= 0) {
            return $columns;
        }

        return $columns + [
            'customer_address_id' => $address->getCustomerAddressId(),
            'customer_id' => $address->getCustomerId(),
            'save_in_address_book' => (int) $address->getSaveInAddressBook(),
            'same_as_billing' => (int) $address->getSameAsBilling(),
            'street' => $address->getStreetFull(),
            'city' => $address->getCity(),
            'postcode' => $address->getPostcode(),
            'country_id' => $address->getCountryId(),
            // Raw values: getRegion()/getRegionId() may load or rewrite the region on read.
            'region_id' => $address->getData('region_id'),
            'region' => $address->getData('region'),
            'collect_shipping_rates' => (int) $address->getCollectShippingRates(),
        ];
    }

    /**
     * Resolve the Magento region for an InnoShip county name.
     *
     * InnoShip stores the county as a plain name ("Iasi", "Satu Mare") while a Magento address is
     * keyed by region_id. Both sides are folded to lowercase ASCII before comparison so that
     * diacritics, spaces and dashes cannot break the match.
     *
     * @param string $countyName
     * @param string $countryId
     * @return Region|null
     */
    private function resolveRegion(string $countyName, string $countryId): ?Region
    {
        if (trim($countyName) === '' || $countryId === '') {
            return null;
        }

        $needle = $this->foldName($countyName);
        if ($needle === '') {
            return null;
        }

        $cacheKey = $countryId . '|' . $needle;
        if (array_key_exists($cacheKey, $this->regions)) {
            return $this->regions[$cacheKey];
        }

        $this->regions[$cacheKey] = null;

        $collection = $this->regionCollectionFactory->create()->addCountryFilter($countryId);

        /** @var Region $region */
        foreach ($collection as $region) {
            if (!$region->getId()) {
                continue;
            }

            if ($this->foldName((string) $region->getDefaultName()) === $needle
                || $this->foldName((string) $region->getName()) === $needle
            ) {
                $this->regions[$cacheKey] = $region;

                return $region;
            }
        }

        return null;
    }

    /**
     * Lowercase a name and strip Romanian diacritics and every non-alphanumeric character.
     *
     * @param string $name
     * @return string
     */
    private function foldName(string $name): string
    {
        $name = mb_strtolower(strtr($name, self::DIACRITIC_MAP), 'UTF-8');

        return (string) preg_replace('/[^a-z0-9]/', '', $name);
    }
}
