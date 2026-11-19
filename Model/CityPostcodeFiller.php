<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Model;

use Magento\Framework\App\ResourceConnection;

/**
 * The city imports (Cron\Citylist, Console\Regioimport) store only the first street's postcode per
 * locality, which is 0 for every city with street-level postcodes (Iași, Cluj-Napoca, the București
 * sectors). Checkout then empties the postcode field for those cities.
 *
 * Fill each such row with the lowest postcode of a pickup point (innoship_pudo) in the same country,
 * county and locality. Rows with no pickup-point postcode stay 0: there is none in the database.
 */
class CityPostcodeFiller
{
    private const DIACRITICS = [
        'ă' => 'a',
        'â' => 'a',
        'î' => 'i',
        'ș' => 's',
        'ş' => 's',
        'ț' => 't',
        'ţ' => 't',
    ];

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
    ) {
    }

    /**
     * Fill the city rows whose postcode is empty or 0.
     *
     * @return int Number of city rows filled.
     */
    public function execute(): int
    {
        $connection = $this->resourceConnection->getConnection();
        $citiesTable = $this->resourceConnection->getTableName('innoship_citys');
        $pudoTable = $this->resourceConnection->getTableName('innoship_pudo');

        if (!$connection->isTableExists($citiesTable) || !$connection->isTableExists($pudoTable)) {
            return 0;
        }

        $cities = $connection->fetchAll(
            $connection->select()
                ->from(
                    ['city' => $citiesTable],
                    [
                        'id',
                        'country',
                        'localitate',
                    ],
                )
                ->join(
                    ['region' => $this->resourceConnection->getTableName('directory_country_region')],
                    'region.region_id = city.regioId',
                    ['default_name'],
                )
                ->where('city.codPostal IN (?)', [
                    '',
                    '0',
                ]),
        );

        if (!$cities) {
            return 0;
        }

        $pudoRows = $connection->fetchAll(
            $connection->select()
                ->from(
                    $pudoTable,
                    [
                        'countryCode',
                        'countyName',
                        'localityName',
                        'postalCode' => 'MIN(postalCode)',
                    ],
                )
                ->where('postalCode NOT IN (?)', [
                    '',
                    '0',
                ])
                ->group([
                    'countryCode',
                    'countyName',
                    'localityName',
                ]),
        );

        $postcodes = [];
        foreach ($pudoRows as $row) {
            $key = $this->key(
                (string) $row['countryCode'],
                (string) $row['countyName'],
                (string) $row['localityName'],
            );
            // MySQL groups case- and accent-insensitively, PHP does not: keep the lowest per folded key.
            if (!isset($postcodes[$key]) || strcmp((string) $row['postalCode'], $postcodes[$key]) < 0) {
                $postcodes[$key] = (string) $row['postalCode'];
            }
        }

        $filled = 0;
        foreach ($cities as $city) {
            $key = $this->key(
                (string) $city['country'],
                (string) $city['default_name'],
                (string) $city['localitate'],
            );
            if (!isset($postcodes[$key])) {
                continue;
            }

            $filled += $connection->update(
                $citiesTable,
                [
                    'codPostal' => $postcodes[$key],
                ],
                [
                    'id = ?' => (int) $city['id'],
                ],
            );
        }

        return $filled;
    }

    /**
     * Build the country|county|locality lookup key.
     *
     * @param string $country
     * @param string $county
     * @param string $locality
     * @return string
     */
    private function key(string $country, string $county, string $locality): string
    {
        return mb_strtolower($country) . '|' . $this->normalise($county) . '|' . $this->normalise($locality);
    }

    /**
     * Lowercase, trim and strip Romanian diacritics.
     *
     * @param string $value
     * @return string
     */
    private function normalise(string $value): string
    {
        return strtr(mb_strtolower(trim($value), 'UTF-8'), self::DIACRITICS);
    }
}
