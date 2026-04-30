<?php
namespace InnoShip\InnoShip\Cron;

use Magento\Framework\App\ResourceConnection;
use InnoShip\InnoShip\Model\Api\GetPudo;

class Pudoinnoship
{
    private $_getPudo;
    private $_resourceConnection;

    /**
     * Constructor
     *
     * @param GetPudo $getPudo
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        GetPudo $getPudo,
        ResourceConnection $resourceConnection
    ) {
        $this->_getPudo = $getPudo;
        $this->_resourceConnection = $resourceConnection;
    }

    /**
     * Execute the cron
     *
     * @return void
     */
    public function execute()
    {
        $pudoList = $this->_getPudo->getPudoList(array());
        $pudoAdded = array();
        $count = count($pudoList);
        if ($count) {
            foreach ($pudoList as $pudoChunk) {
                if (is_array($pudoChunk)) {
                    $pudoAdded[] = $this->insertChunk($pudoChunk);
                }
            }
            $this->cleanDB($pudoAdded);
        }
    }

    /**
     * Validate API response data before database insertion
     *
     * @param array $pudoChunk
     * @return bool
     */
    private function validatePudoData(array $pudoChunk): bool
    {
        // SECURITY: Validate required fields exist
        if (!isset($pudoChunk['id']) || !is_numeric($pudoChunk['id']) || $pudoChunk['id'] <= 0) {
            return false;
        }

        // Validate name
        if (!isset($pudoChunk['name']) || !is_string($pudoChunk['name']) || strlen($pudoChunk['name']) > 255) {
            return false;
        }

        // Validate IDs
        if (isset($pudoChunk['fixedLocationTypeId']) && !is_numeric($pudoChunk['fixedLocationTypeId'])) {
            return false;
        }
        if (isset($pudoChunk['serviceId']) && !is_numeric($pudoChunk['serviceId'])) {
            return false;
        }
        if (isset($pudoChunk['courierId']) && !is_numeric($pudoChunk['courierId'])) {
            return false;
        }
        if (isset($pudoChunk['localityId']) && !is_numeric($pudoChunk['localityId'])) {
            return false;
        }

        // Validate strings
        if (isset($pudoChunk['localityName']) && strlen($pudoChunk['localityName']) > 255) {
            return false;
        }
        if (isset($pudoChunk['countyName']) && strlen($pudoChunk['countyName']) > 255) {
            return false;
        }
        if (isset($pudoChunk['addressText']) && strlen($pudoChunk['addressText']) > 500) {
            return false;
        }

        // Validate country code (ISO 3166-1 alpha-2)
        if (isset($pudoChunk['countryCode']) && !preg_match('/^[A-Z]{2}$/', $pudoChunk['countryCode'])) {
            return false;
        }

        // Validate postal code
        if (isset($pudoChunk['postalCode']) && strlen($pudoChunk['postalCode']) > 20) {
            return false;
        }

        // Validate coordinates
        if (isset($pudoChunk['lat'])) {
            $lat = (float)$pudoChunk['lat'];
            if ($lat < -90 || $lat > 90) {
                return false;
            }
        }
        if (isset($pudoChunk['long'])) {
            $lng = (float)$pudoChunk['long'];
            if ($lng < -180 || $lng > 180) {
                return false;
            }
        }

        // Validate email format if provided
        if (isset($pudoChunk['email']) && !empty($pudoChunk['email'])) {
            if (!filter_var($pudoChunk['email'], FILTER_VALIDATE_EMAIL)) {
                return false;
            }
            if (strlen($pudoChunk['email']) > 255) {
                return false;
            }
        }

        // Validate phone
        if (isset($pudoChunk['phone']) && strlen($pudoChunk['phone']) > 50) {
            return false;
        }

        // Validate isActive is boolean
        if (isset($pudoChunk['isActive']) && !is_bool($pudoChunk['isActive']) && !is_numeric($pudoChunk['isActive'])) {
            return false;
        }

        return true;
    }

    /**
     * Method insertChunk
     *
     * @param array $pudoChunk
     *
     * @return int
     */
    private function insertChunk(array $pudoChunk): int
    {
        // SECURITY: Validate API response data before insertion
        if (!$this->validatePudoData($pudoChunk)) {
            return 0;
        }

        $connection = $this->_resourceConnection->getConnection();
        $table = $this->_resourceConnection->getTableName('innoship_pudo');

        if(isset($pudoChunk['id'])){
            if(isset($pudoChunk['schedule'][0]['openingHour'])){$open_hours_mo_start = $pudoChunk['schedule'][0]['openingHour'];}else{$open_hours_mo_start = "";}
            if(isset($pudoChunk['schedule'][0]['closingHour'])){$open_hours_mo_end = $pudoChunk['schedule'][0]['openingHour'];}else{$open_hours_mo_end = "";}
            if(isset($pudoChunk['schedule'][1]['openingHour'])){$open_hours_tu_start = $pudoChunk['schedule'][1]['openingHour'];}else{$open_hours_tu_start = "";}
            if(isset($pudoChunk['schedule'][1]['closingHour'])){$open_hours_tu_end = $pudoChunk['schedule'][1]['openingHour'];}else{$open_hours_tu_end = "";}
            if(isset($pudoChunk['schedule'][2]['openingHour'])){$open_hours_we_start = $pudoChunk['schedule'][2]['openingHour'];}else{$open_hours_we_start = "";}
            if(isset($pudoChunk['schedule'][2]['closingHour'])){$open_hours_we_end = $pudoChunk['schedule'][2]['openingHour'];}else{$open_hours_we_end = "";}
            if(isset($pudoChunk['schedule'][3]['openingHour'])){$open_hours_th_start = $pudoChunk['schedule'][3]['openingHour'];}else{$open_hours_th_start = "";}
            if(isset($pudoChunk['schedule'][3]['closingHour'])){$open_hours_th_end = $pudoChunk['schedule'][3]['openingHour'];}else{$open_hours_th_end = "";}
            if(isset($pudoChunk['schedule'][4]['openingHour'])){$open_hours_fr_start = $pudoChunk['schedule'][4]['openingHour'];}else{$open_hours_fr_start = "";}
            if(isset($pudoChunk['schedule'][4]['closingHour'])){$open_hours_fr_end = $pudoChunk['schedule'][4]['openingHour'];}else{$open_hours_fr_end = "";}
            if(isset($pudoChunk['schedule'][5]['openingHour'])){$open_hours_sa_start = $pudoChunk['schedule'][5]['openingHour'];}else{$open_hours_sa_start = "";}
            if(isset($pudoChunk['schedule'][5]['closingHour'])){$open_hours_sa_end = $pudoChunk['schedule'][5]['openingHour'];}else{$open_hours_sa_end = "";}
            if(isset($pudoChunk['schedule'][6]['openingHour'])){$open_hours_su_start = $pudoChunk['schedule'][6]['openingHour'];}else{$open_hours_su_start = "";}
            if(isset($pudoChunk['schedule'][6]['closingHour'])){$open_hours_su_end = $pudoChunk['schedule'][6]['openingHour'];}else{$open_hours_su_end = "";}

            // SECURITY: Use parameterized query to prevent SQL injection
            $data = [
                'pudo_id' => (int)$pudoChunk['id'],
                'name' => $pudoChunk['name'] ?? '',
                'fixedLocationTypeId' => (int)($pudoChunk['fixedLocationTypeId'] ?? 0),
                'serviceId' => (int)($pudoChunk['serviceId'] ?? 0),
                'courierId' => (int)($pudoChunk['courierId'] ?? 0),
                'localityId' => (int)($pudoChunk['localityId'] ?? 0),
                'localityName' => $pudoChunk['localityName'] ?? '',
                'countyName' => $pudoChunk['countyName'] ?? '',
                'countryCode' => $pudoChunk['countryCode'] ?? '',
                'addressText' => str_replace(",","",$pudoChunk['addressText']) ?? '',
                'postalCode' => $pudoChunk['postalCode'] ?? '',
                'longitude' => (float)($pudoChunk['long'] ?? 0),
                'latitude' => (float)($pudoChunk['lat'] ?? 0),
                'isActive' => (int)($pudoChunk['isActive'] ?? 0),
                'email' => $pudoChunk['email'] ?? '',
                'phone' => $pudoChunk['phone'] ?? '',
                'supportedPaymentType' => $pudoChunk['supportedPaymentType'] ?? '',
                'open_hours_mo_start' => $open_hours_mo_start,
                'open_hours_mo_end' => $open_hours_mo_end,
                'open_hours_tu_start' => $open_hours_tu_start,
                'open_hours_tu_end' => $open_hours_tu_end,
                'open_hours_we_start' => $open_hours_we_start,
                'open_hours_we_end' => $open_hours_we_end,
                'open_hours_th_start' => $open_hours_th_start,
                'open_hours_th_end' => $open_hours_th_end,
                'open_hours_fr_start' => $open_hours_fr_start,
                'open_hours_fr_end' => $open_hours_fr_end,
                'open_hours_sa_start' => $open_hours_sa_start,
                'open_hours_sa_end' => $open_hours_sa_end,
                'open_hours_su_start' => $open_hours_su_start,
                'open_hours_su_end' => $open_hours_su_end
            ];

            $connection->insertOnDuplicate($table, $data);
            return (int)$pudoChunk['id'];
        }
        return 0;
    }

    private function cleanString($string)
    {
        return str_replace(array("'",","),array("`"," -"),$string);
    }

    private function cleanDB($pudoAdded){
        $connection = $this->_resourceConnection->getConnection();
        $table = $this->_resourceConnection->getTableName('innoship_pudo');

        // SECURITY: Use parameterized query to prevent SQL injection
        $select = $connection->select()->from($table, ['pudo_id']);
        $pudoList = $connection->fetchAll($select);

        foreach($pudoList as $pudo) {
            if(!in_array((int)$pudo['pudo_id'], $pudoAdded, true)){
                // Use parameterized delete
                $connection->delete($table, ['pudo_id = ?' => (int)$pudo['pudo_id']]);
            }
        }
    }
}
