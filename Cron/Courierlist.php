<?php
namespace InnoShip\InnoShip\Cron;

use Magento\Framework\App\ResourceConnection;
use InnoShip\InnoShip\Model\Config;

class Courierlist
{
    private $_resourceConnection;
    protected $config;

    /**
     * Constructor
     *
     * @param ResourceConnection $resourceConnection
     * @param Config $config
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        Config $config
    ) {
        $this->_resourceConnection = $resourceConnection;
        $this->config = $config;
    }

    /**
     * Execute the cron
     *
     * @return void
     */
    public function execute()
    {
        $url = "https://api.innoship.com/api/Location/ClientLocations";
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $headers = array(
            "X-Api-Key: ".$this->config->getApiKey(),
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $allCouriers = curl_exec($curl);
        $allCouriersObj = json_decode($allCouriers);
        curl_close($curl);

        $singleCourier = array();
        if(is_array($allCouriersObj)){
            foreach($allCouriersObj as $curierList){
                $courierCountryCode = $curierList->countryCode;
                $courierLists = $curierList->courierServices;
                foreach($courierLists as $itemS){
                    if((int)$itemS->serviceId === 1 && (int)$itemS->courierId !== -3){
                        // Use a unique key combining courier ID and country code
                        $uniqueKey = $itemS->courierId . '_' . $courierCountryCode;
                        $singleCourier[$uniqueKey]['id'] = $itemS->courierId;
                        $singleCourier[$uniqueKey]['name'] = $itemS->courierDisplayName;
                        $singleCourier[$uniqueKey]['country'] = $courierCountryCode;
                    }
                }
            }
        }

        $count = count($singleCourier);
        $courierListAdded = array();
        if ($count) {
            foreach ($singleCourier as $courierChunk) {
                if (is_array($courierChunk)) {
                    $courierListAdded[] = $this->insertChunk($courierChunk);
                }
            }
            $this->cleanDB($courierListAdded);
        }
    }

    /**
     * Validate courier data before database insertion
     *
     * @param array $courierChunk
     * @return bool
     */
    private function validateCourierData(array $courierChunk): bool
    {
        // SECURITY: Validate required fields exist
        if (!isset($courierChunk['id']) || !is_numeric($courierChunk['id']) || $courierChunk['id'] <= 0) {
            return false;
        }

        // Validate courier name
        if (!isset($courierChunk['name']) || !is_string($courierChunk['name']) || strlen($courierChunk['name']) > 255) {
            return false;
        }

        // Validate country code (ISO 3166-1 alpha-2) if provided
        if (isset($courierChunk['country']) && !empty($courierChunk['country'])) {
            if (!preg_match('/^[A-Z]{2}$/', $courierChunk['country'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Method insertChunk
     *
     * @param array $courierChunk
     *
     * @return int
     */
    private function insertChunk(array $courierChunk): int
    {
        // SECURITY: Validate API response data before insertion
        if (!$this->validateCourierData($courierChunk)) {
            return 0;
        }

        $connection = $this->_resourceConnection->getConnection();
        $table = $this->_resourceConnection->getTableName('innoship_courierlist');

        if(isset($courierChunk['id'])){
            // SECURITY: Use parameterized query to prevent SQL injection
            $data = [
                'courierId' => (int)$courierChunk['id'],
                'courierName' => $courierChunk['name'] ?? '',
                'country' => $courierChunk['country'] ?? null
            ];

            $connection->insertOnDuplicate($table, $data);
            return (int)$courierChunk['id'];
        }
        return 0;
    }

    private function cleanString($string)
    {
        return str_replace(array("'",","),array("`"," -"),$string);
    }

    private function cleanDB($courierListAdded){
        $connection = $this->_resourceConnection->getConnection();
        $table = $this->_resourceConnection->getTableName('innoship_courierlist');

        // SECURITY: Use parameterized query to prevent SQL injection
        $select = $connection->select()->from($table, ['courierId']);
        $courierList = $connection->fetchAll($select);

        foreach($courierList as $courierItem) {
            if(!in_array((int)$courierItem['courierId'], $courierListAdded, true)){
                // Use parameterized delete
                $connection->delete($table, ['courierId = ?' => (int)$courierItem['courierId']]);
            }
        }
    }
}
