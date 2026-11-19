<?php
namespace InnoShip\InnoShip\Cron;

use InnoShip\InnoShip\Model\CityPostcodeFiller;
use InnoShip\InnoShip\Model\Config;
use Magento\Framework\App\ResourceConnection;

class Citylist
{
    private $_resourceConnection;
    protected $config;
    private \Magento\Directory\Model\AllowedCountries $allowedCountryModel;
    private CityPostcodeFiller $cityPostcodeFiller;

    /**
     * Constructor
     *
     * @param ResourceConnection $resourceConnection
     * @param Config $config
     * @param CityPostcodeFiller $cityPostcodeFiller
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        Config $config,
        \Magento\Directory\Model\AllowedCountries $allowedCountryModel,
        CityPostcodeFiller $cityPostcodeFiller
    ) {
        $this->_resourceConnection = $resourceConnection;
        $this->config = $config;
        $this->allowedCountryModel = $allowedCountryModel;
        $this->cityPostcodeFiller = $cityPostcodeFiller;
    }

    /**
     * Execute the cron
     *
     * @return void
     */
    public function execute()
    {
        $allowCountries = $this->config->getSpecificCountriesGo();
        if($allowCountries === null){
            $allowCountries = $this->allowedCountryModel->getAllowedCountries();
        } else {
            $allowCountries = explode(",",$this->config->getSpecificCountriesGo());
        }
        if(is_array($allowCountries)){
            $apiKey = $this->config->getApiKey();
            $connection = $this->_resourceConnection->getConnection();
            $tableDirectoryCountryRegion = $this->_resourceConnection->getTableName('directory_country_region');
            $tableCities = $this->_resourceConnection->getTableName('innoship_citys');

            foreach($allowCountries as $countryCode) {
                // SECURITY: Validate country code format
                if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
                    continue;
                }

                $getAllCountryDataInnoshipData = $this->getAllCountryDetails($countryCode, $apiKey);
                if(isset($getAllCountryDataInnoshipData['status'])){
                    if((int)$getAllCountryDataInnoshipData['status'] === 404){
                        echo "Country ".$countryCode." not in INNOSHIP\n";
                    }
                } elseif (!is_array($getAllCountryDataInnoshipData)
                    || !isset($getAllCountryDataInnoshipData['counties'])
                    || !is_array($getAllCountryDataInnoshipData['counties'])
                ) {
                    // Failed or malformed answer: keep the stored cities of this country.
                    echo "Country ".$countryCode.": no valid answer from INNOSHIP, stored cities kept\n";
                } else {
                    echo "Working on Country ".$countryCode."\n";
                    $getAllCountryDataInnoship = $getAllCountryDataInnoshipData['counties'];

                    // SECURITY: Use parameterized query to prevent SQL injection
                    $select = $connection->select()
                        ->from($tableDirectoryCountryRegion)
                        ->where('country_id = ?', $countryCode);
                    $result = $connection->fetchAll($select);
                    $regionNames = array_map(
                        fn (array $region): string => $this->normalizeCountyName($region['default_name']),
                        $result
                    );
                    foreach ($result as $item) {
                        $judetGet = $this->eliminateDiacritice($item['default_name']);
                        $regionId = (int)$item['region_id'];
                        $regioCode = $item['code'];
                        $getInnoShipJudetInfo = $this->matchCounty($getAllCountryDataInnoship, $judetGet, $regionNames);

                        if($getInnoShipJudetInfo !== null){
                            echo "Working on ".$countryCode." => ".$regioCode."\n";
                            $localitatiInnoship = $getInnoShipJudetInfo['localities'] ?? null;
                            if (!is_array($localitatiInnoship)) {
                                echo "No localities for ".$countryCode." => ".$regioCode.", stored cities kept\n";
                                continue;
                            }

                            // SECURITY: Batch insert with validation
                            $dataToInsert = [];
                            foreach($localitatiInnoship as $localitateData){
                                // Validate locality data before insertion
                                if(is_array($localitateData) && $this->validateLocalityData($localitateData, $countryCode, (int) $regionId, (string) $regioCode)){
                                    $dataToInsert[] = [
                                        'country' => $countryCode,
                                        'regioId' => $regionId,
                                        'regioCode' => $regioCode,
                                        'localitate' => $localitateData['localityName'] ?? '',
                                        'codPostal' => $this->lowestPostalCode($localitateData)
                                    ];
                                }
                            }

                            if (empty($dataToInsert)) {
                                echo "No valid locality for ".$countryCode." => ".$regioCode.", stored cities kept\n";
                                continue;
                            }

                            // Replace the region's cities atomically: a failed insert keeps the old rows.
                            $connection->beginTransaction();
                            try {
                                // SECURITY: Use parameterized delete to prevent SQL injection
                                $connection->delete($tableCities, [
                                    'country = ?' => $countryCode,
                                    'regioId = ?' => $regionId
                                ]);
                                $connection->insertMultiple($tableCities, $dataToInsert);
                                $connection->commit();
                            } catch (\Throwable $e) {
                                $connection->rollBack();
                                echo "Import failed for ".$countryCode." => ".$regioCode.", stored cities kept: ".$e->getMessage()."\n";
                            }
                        }
                    }
                }
            }

            // Cities with street-level postcodes were stored with postcode 0 above.
            $this->cityPostcodeFiller->execute();
        }
    }

    /**
     * Validate locality data before database insertion
     *
     * @param array $localitateData
     * @param string $countryCode
     * @param int $regionId
     * @param string $regioCode
     * @return bool
     */
    private function validateLocalityData(array $localitateData, string $countryCode, int $regionId, string $regioCode): bool
    {
        // SECURITY: Validate locality name
        if (!isset($localitateData['localityName']) || !is_string($localitateData['localityName'])) {
            return false;
        }
        if (strlen($localitateData['localityName']) > 255) {
            return false;
        }

        // Validate postal code exists and is reasonable length
        $postalCode = $this->lowestPostalCode($localitateData);
        if ($postalCode === '') {
            return false;
        }
        if (strlen($postalCode) > 20) {
            return false;
        }

        // Validate country code (ISO 3166-1 alpha-2)
        if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
            return false;
        }

        // Validate region ID
        if ($regionId <= 0) {
            return false;
        }

        // Validate region code: directory_country_region.code is varchar(32). A cap of 10 skipped every
        // region with a longer code (RO BistritaNasaud, CarasSeverin, most PL/FI/LV regions) on every import.
        if (strlen($regioCode) > 32) {
            return false;
        }

        return true;
    }

    /**
     * The locality's lowest street postcode (string compare, as Model/CityPostcodeFiller does): the
     * first street depended on the API's street order, so a city's postcode changed between imports
     * (Cluj-Napoca 400001 -> 400496).
     *
     * @param array $localitateData
     * @return string '' when no street carries a postcode
     */
    private function lowestPostalCode(array $localitateData): string
    {
        $codes = [];
        foreach ((array) ($localitateData['streets'] ?? []) as $street) {
            $code = is_array($street) ? ($street['postalCode'] ?? null) : null;
            if ((!is_string($code) && !is_numeric($code)) || trim((string) $code) === '') {
                continue;
            }
            $codes[] = trim((string) $code);
        }
        if ($codes === []) {
            return '';
        }

        // Only codes with the locality's most common 2-char prefix: one stray street code in the API
        // data (Bistrita 042020 among 420xxx) would otherwise win as the lowest.
        $prefixCounts = array_count_values(array_map(static fn (string $code): string => substr($code, 0, 2), $codes));
        arsort($prefixCounts);
        $prefix = (string) array_key_first($prefixCounts);

        $lowest = '';
        foreach ($codes as $code) {
            if (substr($code, 0, 2) === $prefix && ($lowest === '' || strcmp($code, $lowest) < 0)) {
                $lowest = $code;
            }
        }

        return $lowest;
    }

    private function getAllCountryDetails($countryCode,$apiKey)
    {
        $url = $this->config->getApiBaseUrl()."/api/Location/Postalcodes/Country/".strtolower($countryCode);
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, Config::CURL_CONNECT_TIMEOUT);
        curl_setopt($curl, CURLOPT_TIMEOUT, Config::CURL_TIMEOUT);
        $headers = array(
            "X-Api-Key: ".$apiKey,
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $resp = curl_exec($curl);
        if (!is_string($resp)) {
            return null;
        }
        return json_decode($resp,true);
    }

    /**
     * Find the INNOSHIP county of a Magento region
     *
     * @param array    $dataReceive INNOSHIP counties of one country
     * @param string   $judet       Magento region default_name
     * @param string[] $regionNames normalized names of every Magento region of the country
     *
     * @return array|null
     */
    public function matchCounty($dataReceive,$judet, array $regionNames = [])
    {
        $judetNormalizat = $this->normalizeCountyName($judet);

        foreach($dataReceive as $judetInnoship){
            if($this->normalizeCountyName($judetInnoship['countyNameLocalized'] ?? '') === $judetNormalizat){
                return $judetInnoship;
            }
        }

        // LV: INNOSHIP names a municipality without the suffix ('Aizkraukles' = Magento 'Aizkraukles novads').
        // A county that already matches a Magento region exactly is never reused: the city 'Daugavpils'
        // must not fill the region 'Daugavpils novads' as well.
        foreach($dataReceive as $judetInnoship){
            $countyName = $this->normalizeCountyName($judetInnoship['countyNameLocalized'] ?? '');
            if ($countyName !== ''
                && $countyName . ' novads' === $judetNormalizat
                && !in_array($countyName, $regionNames, true)
            ) {
                return $judetInnoship;
            }
        }

        return null;
    }

    /**
     * Normalize a county name for comparison: strip diacritics, lowercase, collapse separators
     *
     * @param string $countyName
     * @return string
     */
    private function normalizeCountyName($countyName): string
    {
        $normalized = $this->eliminateDiacritice((string)$countyName);
        $normalized = mb_strtolower($normalized, 'UTF-8');
        $normalized = str_replace(array("-", "_"), " ", $normalized);
        $normalized = preg_replace('/\s+/u', ' ', $normalized);

        return trim($normalized);
    }

    private function eliminateDiacritice($string)
    {
        return str_replace(
            array(
                "ș","Ș","ş","Ş",
                "ț","Ț","ţ","Ţ",
                "ă","Ă","â","Â","î","Î",
            ),
            array(
                "s","S","s","S",
                "t","T","t","T",
                "a","A","a","A","i","I",
            ),
            $string
        );
    }

    private function cleanString($string)
    {
        return str_replace(array("'"),array("`"),$string);
    }
}
