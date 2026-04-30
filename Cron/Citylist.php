<?php
namespace InnoShip\InnoShip\Cron;

use InnoShip\InnoShip\Model\Config;
use Magento\Framework\App\ResourceConnection;

class Citylist
{
    private $_resourceConnection;
    protected $config;
    private \Magento\Directory\Model\AllowedCountries $allowedCountryModel;

    /**
     * Constructor
     *
     * @param ResourceConnection $resourceConnection
     * @param Config $config
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        Config $config,
        \Magento\Directory\Model\AllowedCountries $allowedCountryModel
    ) {
        $this->_resourceConnection = $resourceConnection;
        $this->config = $config;
        $this->allowedCountryModel = $allowedCountryModel;
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
                } else {
                    echo "Working on Country ".$countryCode."\n";
                    $getAllCountryDataInnoship = $getAllCountryDataInnoshipData['counties'];

                    // SECURITY: Use parameterized query to prevent SQL injection
                    $select = $connection->select()
                        ->from($tableDirectoryCountryRegion)
                        ->where('country_id = ?', $countryCode);
                    $result = $connection->fetchAll($select);
                    foreach ($result as $item) {
                        $judetGet = $this->eliminateDiacritice($item['default_name']);
                        $regionId = (int)$item['region_id'];
                        $regioCode = $item['code'];
                        $getInnoShipJudetInfo = $this->matchCounty($getAllCountryDataInnoship, $judetGet);

                        if($getInnoShipJudetInfo !== null){
                            // SECURITY: Use parameterized delete to prevent SQL injection
                            $connection->delete($tableCities, [
                                'country = ?' => $countryCode,
                                'regioId = ?' => $regionId
                            ]);

                            echo "Working on ".$countryCode." => ".$regioCode."\n";
                            $localitatiInnoship = $getInnoShipJudetInfo['localities'];

                            // SECURITY: Batch insert with validation
                            $dataToInsert = [];
                            foreach($localitatiInnoship as $localitateData){
                                // Validate locality data before insertion
                                if($this->validateLocalityData($localitateData, $countryCode, $regionId, $regioCode)){
                                    $dataToInsert[] = [
                                        'country' => $countryCode,
                                        'regioId' => $regionId,
                                        'regioCode' => $regioCode,
                                        'localitate' => $localitateData['localityName'] ?? '',
                                        'codPostal' => $localitateData['streets'][0]['postalCode'] ?? ''
                                    ];
                                }
                            }

                            // Insert in batches to avoid memory issues
                            if (!empty($dataToInsert)) {
                                $connection->insertMultiple($tableCities, $dataToInsert);
                            }
                        }
                    }
                }
            }
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
        if (!isset($localitateData['streets'][0]['postalCode'])) {
            return false;
        }
        $postalCode = $localitateData['streets'][0]['postalCode'];
        if (!is_string($postalCode) && !is_numeric($postalCode)) {
            return false;
        }
        if (strlen((string)$postalCode) > 20) {
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

        // Validate region code
        if (strlen($regioCode) > 10) {
            return false;
        }

        return true;
    }

    private function getAllCountryDetails($countryCode,$apiKey)
    {
        $url = "https://api.innoship.com/api/Location/Postalcodes/Country/".strtolower($countryCode);
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $headers = array(
            "X-Api-Key: ".$apiKey,
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $resp = curl_exec($curl);
        curl_close($curl);
        return json_decode($resp,true);
    }

    public function matchCounty($dataReceive,$judet)
    {
        foreach($dataReceive as $judetInnoship){
            if(strtolower($judetInnoship['countyNameLocalized']) === strtolower($judet)){
                return $judetInnoship;
            }
        }

        return null;
    }

    private function eliminateDiacritice($string)
    {
        return str_replace(array("ș","ş","Ș","ț","ţ","Ț","Â","â","Ă","ă","Î","î","ă","ţ"),array("s","s","S","t","t","T","A","a","A","a","I","i","a","t"), $string);
    }

    private function cleanString($string)
    {
        return str_replace(array("'"),array("`"),$string);
    }
}
