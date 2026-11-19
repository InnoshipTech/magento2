<?php
namespace InnoShip\InnoShip\Cron;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;
use InnoShip\InnoShip\Model\Api\GetPudo;
use InnoShip\InnoShip\Model\Api\JsonArrayReader;

class Pudoinnoship
{
    private const BATCH_SIZE = 1000;

    private $_getPudo;
    private $_resourceConnection;
    private $filesystem;
    private $jsonArrayReader;

    /**
     * Constructor
     *
     * @param GetPudo $getPudo
     * @param ResourceConnection $resourceConnection
     * @param Filesystem $filesystem
     * @param JsonArrayReader $jsonArrayReader
     */
    public function __construct(
        GetPudo $getPudo,
        ResourceConnection $resourceConnection,
        Filesystem $filesystem,
        JsonArrayReader $jsonArrayReader
    ) {
        $this->_getPudo = $getPudo;
        $this->_resourceConnection = $resourceConnection;
        $this->filesystem = $filesystem;
        $this->jsonArrayReader = $jsonArrayReader;
    }

    /**
     * Execute the cron
     *
     * The list holds every locker InnoShip knows (~445k): it is downloaded to var/tmp and read one locker
     * at a time. Decoding the whole answer at once peaked at ~3.5 GB.
     *
     * @return void
     * @throws \UnexpectedValueException|\JsonException when the downloaded list is not a complete JSON array
     */
    public function execute()
    {
        $tmpDir = $this->filesystem->getDirectoryWrite(DirectoryList::TMP);
        $tmpDir->create();
        $file = $tmpDir->getAbsolutePath('innoship_pudo_' . uniqid('', true) . '.json');

        try {
            $result = $this->_getPudo->downloadPudoList($file);
            // stop before cleanDB(), which would otherwise delete every stored locker
            if (empty($result['success'])) {
                return;
            }

            $connection = $this->_resourceConnection->getConnection();
            $table = $this->_resourceConnection->getTableName('innoship_pudo');
            $pudoAdded = array();
            $rows = array();
            // a truncated or non-array answer throws here, before cleanDB()
            foreach ($this->jsonArrayReader->read($file) as $pudoChunk) {
                $row = $this->buildRow($pudoChunk);
                if ($row !== null) {
                    $rows[] = $row;
                    $pudoAdded[$row['pudo_id']] = true;
                }
                if (count($rows) >= self::BATCH_SIZE) {
                    $connection->insertOnDuplicate($table, $rows);
                    $rows = array();
                }
            }
            if (!empty($rows)) {
                $connection->insertOnDuplicate($table, $rows);
            }

            // Remove lockers missing from the list only when the list actually delivered lockers.
            if (!empty($pudoAdded)) {
                $this->cleanDB($pudoAdded);
            }
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
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
     * Build the innoship_pudo row for one locker of the API list
     *
     * @param array $pudoChunk
     *
     * @return array|null Null when the locker is invalid or has no id.
     */
    private function buildRow(array $pudoChunk): ?array
    {
        // SECURITY: Validate API response data before insertion
        if (!$this->validatePudoData($pudoChunk)) {
            return null;
        }

        if(isset($pudoChunk['id'])){
            if(isset($pudoChunk['schedule'][0]['openingHour'])){$open_hours_mo_start = $pudoChunk['schedule'][0]['openingHour'];}else{$open_hours_mo_start = "";}
            if(isset($pudoChunk['schedule'][0]['closingHour'])){$open_hours_mo_end = $pudoChunk['schedule'][0]['closingHour'];}else{$open_hours_mo_end = "";}
            if(isset($pudoChunk['schedule'][1]['openingHour'])){$open_hours_tu_start = $pudoChunk['schedule'][1]['openingHour'];}else{$open_hours_tu_start = "";}
            if(isset($pudoChunk['schedule'][1]['closingHour'])){$open_hours_tu_end = $pudoChunk['schedule'][1]['closingHour'];}else{$open_hours_tu_end = "";}
            if(isset($pudoChunk['schedule'][2]['openingHour'])){$open_hours_we_start = $pudoChunk['schedule'][2]['openingHour'];}else{$open_hours_we_start = "";}
            if(isset($pudoChunk['schedule'][2]['closingHour'])){$open_hours_we_end = $pudoChunk['schedule'][2]['closingHour'];}else{$open_hours_we_end = "";}
            if(isset($pudoChunk['schedule'][3]['openingHour'])){$open_hours_th_start = $pudoChunk['schedule'][3]['openingHour'];}else{$open_hours_th_start = "";}
            if(isset($pudoChunk['schedule'][3]['closingHour'])){$open_hours_th_end = $pudoChunk['schedule'][3]['closingHour'];}else{$open_hours_th_end = "";}
            if(isset($pudoChunk['schedule'][4]['openingHour'])){$open_hours_fr_start = $pudoChunk['schedule'][4]['openingHour'];}else{$open_hours_fr_start = "";}
            if(isset($pudoChunk['schedule'][4]['closingHour'])){$open_hours_fr_end = $pudoChunk['schedule'][4]['closingHour'];}else{$open_hours_fr_end = "";}
            if(isset($pudoChunk['schedule'][5]['openingHour'])){$open_hours_sa_start = $pudoChunk['schedule'][5]['openingHour'];}else{$open_hours_sa_start = "";}
            if(isset($pudoChunk['schedule'][5]['closingHour'])){$open_hours_sa_end = $pudoChunk['schedule'][5]['closingHour'];}else{$open_hours_sa_end = "";}
            if(isset($pudoChunk['schedule'][6]['openingHour'])){$open_hours_su_start = $pudoChunk['schedule'][6]['openingHour'];}else{$open_hours_su_start = "";}
            if(isset($pudoChunk['schedule'][6]['closingHour'])){$open_hours_su_end = $pudoChunk['schedule'][6]['closingHour'];}else{$open_hours_su_end = "";}

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
                'addressText' => str_replace(",", "", (string) ($pudoChunk['addressText'] ?? '')),
                'postalCode' => $pudoChunk['postalCode'] ?? '',
                'longitude' => (float)($pudoChunk['long'] ?? 0),
                'latitude' => (float)($pudoChunk['lat'] ?? 0),
                'isActive' => (int)($pudoChunk['isActive'] ?? 0),
                'email' => $pudoChunk['email'] ?? '',
                'phone' => $pudoChunk['phone'] ?? '',
                'supportedPaymentType' => $pudoChunk['supportedPaymentType'] ?? '',
                'brandId' => $pudoChunk['brandId'] ?? '',
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

            return $data;
        }
        return null;
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
        $pudoList = $connection->fetchCol($select);

        // $pudoAdded is keyed by pudo_id: a hash lookup, since in_array() over the full list for every
        // stored row is quadratic and never finished on a real list (~445k lockers).
        $toDelete = array();
        foreach($pudoList as $pudoId) {
            if(!isset($pudoAdded[(int)$pudoId])){
                $toDelete[] = (int)$pudoId;
            }
        }
        foreach (array_chunk($toDelete, self::BATCH_SIZE) as $ids) {
            // Use parameterized delete
            $connection->delete($table, ['pudo_id IN (?)' => $ids]);
        }
    }
}
