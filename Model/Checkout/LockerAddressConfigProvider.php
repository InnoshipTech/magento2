<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Model\Checkout;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * checkoutConfig.innoshipLockerAddressIds: the logged-in customer's saved addresses that are a
 * locker address (saved into the address book by module versions before 1.4.0). Leaving Locker,
 * checkout/courier-address.js must not fall back to one of them as the courier address.
 *
 * A saved address is a locker address when a pickup point of the same country and postcode has the
 * same street (InnoShip writes the locker's addressText into street line 1, commas as spaces).
 */
class LockerAddressConfigProvider implements ConfigProviderInterface
{
    /** @var CustomerSession */
    private $customerSession;

    /** @var ResourceConnection */
    private $resourceConnection;

    /** @var LoggerInterface */
    private $logger;

    /**
     * @param CustomerSession $customerSession
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     */
    public function __construct(
        CustomerSession $customerSession,
        ResourceConnection $resourceConnection,
        LoggerInterface $logger
    ) {
        $this->customerSession = $customerSession;
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return [
            'innoshipLockerAddressIds' => $this->getLockerAddressIds(),
        ];
    }

    /**
     * @return int[]
     */
    private function getLockerAddressIds(): array
    {
        if (!$this->customerSession->isLoggedIn()) {
            return [];
        }

        try {
            $candidates = [];
            foreach ($this->customerSession->getCustomer()->getAddresses() as $address) {
                $street = $this->normalize(implode(' ', (array) $address->getStreet()));
                $country = (string) $address->getCountryId();
                $postcode = trim((string) $address->getPostcode());

                if ($street === '' || $country === '' || $postcode === '') {
                    continue;
                }
                $candidates[(int) $address->getId()] = [$country, $postcode, $street];
            }

            if (empty($candidates)) {
                return [];
            }

            $connection = $this->resourceConnection->getConnection();
            // Index INNOSHIP_PUDO_COUNTRY_POSTCODE: a seek per postcode, never a scan of the locker list.
            $select = $connection->select()
                ->from($this->resourceConnection->getTableName('innoship_pudo'), ['countryCode', 'postalCode', 'addressText'])
                ->where('countryCode IN (?)', array_values(array_unique(array_column($candidates, 0))))
                ->where('postalCode IN (?)', array_values(array_unique(array_column($candidates, 1))));

            $lockerStreets = [];
            foreach ($connection->fetchAll($select) as $row) {
                $lockerStreets[$row['countryCode'] . '|' . trim((string) $row['postalCode'])][$this->normalize((string) $row['addressText'])] = true;
            }

            $ids = [];
            foreach ($candidates as $addressId => [$country, $postcode, $street]) {
                if (isset($lockerStreets[$country . '|' . $postcode][$street])) {
                    $ids[] = $addressId;
                }
            }

            return $ids;
        } catch (\Throwable $e) {
            // Checkout must render without the flag; the fallback then works as before.
            $this->logger->error('InnoShip locker address lookup failed: ' . $e->getMessage(), ['exception' => $e]);

            return [];
        }
    }

    /**
     * @param string $street
     *
     * @return string
     */
    private function normalize(string $street): string
    {
        return trim((string) preg_replace('/[\s,]+/u', ' ', mb_strtolower($street, 'UTF-8')));
    }
}
