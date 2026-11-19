<?php

namespace InnoShip\InnoShip\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use InnoShip\InnoShip\Model\Config;
use InnoShip\InnoShip\Model\ExternalFactory;
use InnoShip\InnoShip\Model\ResourceModel\External as ExternalResource;
use InnoShip\InnoShip\Model\ResourceModel\External\CollectionFactory as ExternalCollectionFactory;
use Psr\Log\LoggerInterface;

class ExternalSync extends AbstractHelper
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var ExternalFactory
     */
    protected $externalFactory;

    /**
     * @var ExternalResource
     */
    protected $externalResource;

    /**
     * @var ExternalCollectionFactory
     */
    protected $externalCollectionFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param Context $context
     * @param Config $config
     * @param ExternalFactory $externalFactory
     * @param ExternalResource $externalResource
     * @param ExternalCollectionFactory $externalCollectionFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        Config $config,
        ExternalFactory $externalFactory,
        ExternalResource $externalResource,
        ExternalCollectionFactory $externalCollectionFactory,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->config = $config;
        $this->externalFactory = $externalFactory;
        $this->externalResource = $externalResource;
        $this->externalCollectionFactory = $externalCollectionFactory;
        $this->logger = $logger;
    }

    /**
     * Validate external location data from API
     *
     * @param array $externalIdObj
     * @return bool
     */
    private function validateExternalLocationData(array $externalIdObj): bool
    {
        // SECURITY: Validate external location ID exists and has reasonable length
        if (!isset($externalIdObj['externalLocationId'])) {
            return false;
        }

        $externalId = $externalIdObj['externalLocationId'];
        if (!is_string($externalId) && !is_numeric($externalId)) {
            return false;
        }

        if (strlen((string)$externalId) < 1 || strlen((string)$externalId) > 255) {
            return false;
        }

        // Validate country name length if provided
        if (isset($externalIdObj['countryName'])) {
            if (!is_string($externalIdObj['countryName'])) {
                return false;
            }
            if (strlen($externalIdObj['countryName']) > 255) {
                return false;
            }
        }

        // Validate country code format (ISO 3166-1 alpha-2) if provided
        if (isset($externalIdObj['countryCode']) && !empty($externalIdObj['countryCode'])) {
            if (!preg_match('/^[A-Z]{2}$/', $externalIdObj['countryCode'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Synchronize external locations from InnoShip API
     *
     * @return array
     */
    public function synchronizeExternalLocations()
    {
        try {
            $apiKey = $this->config->getApiKey();
            $externalIdList = $this->getExternalIdFromInnoship($apiKey);

            // Validate the whole answer BEFORE touching the table: an error body or an empty list
            // used to truncate every stored location and insert nothing.
            $validLocations = array_values(array_filter(
                $externalIdList,
                fn ($externalIdObj) => is_array($externalIdObj) && $this->validateExternalLocationData($externalIdObj)
            ));
            if (empty($validLocations)) {
                $this->logger->error('InnoShip external locations: no valid location in the API answer; stored locations kept.');
                return [
                    'success' => false,
                    'error' => __('The InnoShip API returned no valid external location; nothing was changed.')->render()
                ];
            }

            $syncedCount = 0;
            $errorCount = 0;

            $connection = $this->externalResource->getConnection();
            $connection->beginTransaction();
            try {
                // Replace only current data; DELETE (not TRUNCATE) so a failure rolls back.
                $this->truncateExternalTable();

                foreach ($validLocations as $externalIdObj) {
                    try {
                        $this->saveExternalLocation(
                            $externalIdObj['externalLocationId'],
                            $externalIdObj['countryName'] ?? '',
                            $externalIdObj['countryCode'] ?? ''
                        );
                        $syncedCount++;
                    } catch (\Exception $e) {
                        $this->logger->error('Error saving external location: ' . $e->getMessage());
                        $errorCount++;
                    }
                }

                if ($syncedCount === 0) {
                    throw new \RuntimeException('no external location could be saved');
                }
                $connection->commit();
            } catch (\Throwable $e) {
                $connection->rollBack();
                throw $e;
            }

            return [
                'success' => true,
                'synced' => $syncedCount,
                'errors' => $errorCount,
                'total' => count($externalIdList)
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Error synchronizing external locations: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Truncate external locations table
     *
     * @return void
     * @throws \Exception
     */
    protected function truncateExternalTable()
    {
        $connection = $this->externalResource->getConnection();
        $tableName = $this->externalResource->getMainTable();
        // DELETE instead of TRUNCATE: TRUNCATE commits implicitly and cannot be rolled back.
        $connection->delete($tableName);
    }

    /**
     * Fetch external location IDs from InnoShip API
     *
     * @param string $apiKey
     * @return array
     */
    protected function getExternalIdFromInnoship($apiKey)
    {
        $url = $this->config->getApiBaseUrl() . "/api/Location/ClientLocations";
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, Config::CURL_CONNECT_TIMEOUT);
        curl_setopt($curl, CURLOPT_TIMEOUT, Config::CURL_TIMEOUT);
        $headers = [
            "X-Api-Key: " . $apiKey,
        ];
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $resp = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if (!is_string($resp) || $httpCode !== 200) {
            $this->logger->error('InnoShip external locations: API answered HTTP ' . $httpCode);
            return [];
        }

        $list = json_decode($resp, true);

        // A list of locations only; an error object ({"message": ...}) is not one.
        return is_array($list) && array_is_list($list) ? $list : [];
    }

    /**
     * Save external location to database
     *
     * @param string $externalId
     * @param string $countryName
     * @param string $countryCode
     * @return void
     * @throws \Exception
     */
    protected function saveExternalLocation($externalId, $countryName, $countryCode)
    {
        // Load existing record by external ID
        $collection = $this->externalCollectionFactory->create();
        $collection->addFieldToFilter('external', $externalId);
        $existingExternal = $collection->getFirstItem();

        if ($existingExternal->getId()) {
            // Update existing record
            $existingExternal->setCountryName($countryName);
            $existingExternal->setCountryCode($countryCode);
            $this->externalResource->save($existingExternal);
        } else {
            // Create new record
            $external = $this->externalFactory->create();
            $external->setExternal($externalId);
            $external->setCountryName($countryName);
            $external->setCountryCode($countryCode);
            $this->externalResource->save($external);
        }
    }

    /**
     * Get external location data by external ID
     *
     * @param string $externalId
     * @return \InnoShip\InnoShip\Model\External|null
     */
    public function getExternalLocationByExternalId($externalId)
    {
        $collection = $this->externalCollectionFactory->create();
        $collection->addFieldToFilter('external', $externalId);
        $external = $collection->getFirstItem();

        return $external->getId() ? $external : null;
    }
}
