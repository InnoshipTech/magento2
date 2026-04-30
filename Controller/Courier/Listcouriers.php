<?php

namespace InnoShip\InnoShip\Controller\Courier;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use InnoShip\InnoShip\Model\Config;
use InnoShip\InnoShip\Api\ServiceInterface;
use InnoShip\InnoShip\Model\Api\Rest\Service;
use InnoShip\InnoShip\Model\ResourceModel\External\CollectionFactory as ExternalCollectionFactory;
use Psr\Log\LoggerInterface;


class Listcouriers extends Action
{
    private $_resourceConnection;
    private $resultJsonFactory;
    public $quoteRepository;
    private $maskedQuoteIdToQuoteId;
    protected $config;
    protected $service;
    protected $externalCollectionFactory;
    protected $logger;

    public function __construct(
        Context $context,
        ResourceConnection $resourceConnection,
        JsonFactory $resultJsonFactory,
        CartRepositoryInterface $quoteRepository,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        Config $config,
        Service $service,
        ExternalCollectionFactory $externalCollectionFactory,
        LoggerInterface $logger
    )
    {
        $this->_resourceConnection = $resourceConnection;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->quoteRepository = $quoteRepository;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->config = $config;
        $this->service = $service;
        $this->externalCollectionFactory = $externalCollectionFactory;
        $this->logger = $logger;
        parent::__construct($context);
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        // SECURITY FIX: Add security headers to JSON response
        $resultJson->setHeader('X-Content-Type-Options', 'nosniff');
        $resultJson->setHeader('X-Frame-Options', 'DENY');
        $resultJson->setHeader('X-XSS-Protection', '1; mode=block');

        // INPUT VALIDATION
        $quoteId = $this->getRequest()->getParam('quote');
        $countryCode = $this->getRequest()->getParam('country');
        $regionParam = $this->getRequest()->getParam('region');
        $cityParam = $this->getRequest()->getParam('city');

        // 1. Validate quote ID
        if (empty($quoteId)) {
            return $resultJson->setData([
                'error' => __('Quote ID is required.'),
                'json_data' => []
            ]);
        }

        // 2. Validate country code (ISO 3166-1 alpha-2)
        if ($countryCode) {
            $countryCode = strtoupper(trim($countryCode));
            if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
                return $resultJson->setData([
                    'error' => __('Invalid country code format.'),
                    'json_data' => []
                ]);
            }
        }

        // 3. Validate and sanitize region parameter
        if ($regionParam) {
            $regionParam = trim($regionParam);
            if (strlen($regionParam) > 100) {
                $regionParam = substr($regionParam, 0, 100);
            }
            $regionParam = htmlspecialchars($regionParam, ENT_QUOTES, 'UTF-8');
        }

        // 4. Validate and sanitize city parameter
        if ($cityParam) {
            $cityParam = trim($cityParam);
            if (strlen($cityParam) > 100) {
                $cityParam = substr($cityParam, 0, 100);
            }
            $cityParam = htmlspecialchars($cityParam, ENT_QUOTES, 'UTF-8');
        }

        try {
            $quoteIdNr = $this->maskedQuoteIdToQuoteId->execute($quoteId);
        } catch (\Exception $exception) {
            $quoteIdNr = $quoteId;
        }

        $connection = $this->_resourceConnection->getConnection();
        $allCourier = [];

        // Load quote to get shipping address and cart data
        try {
            $quote = $this->quoteRepository->get($quoteIdNr);
            $shippingAddress = $quote->getShippingAddress();
            $items = $quote->getAllVisibleItems();

            // Get shipping address details with fallback to request parameters
            $shippingCountry = $countryCode ?: $shippingAddress->getCountryId();
            $shippingRegion = $regionParam;
            $shippingCity = $cityParam;

            // Calculate total weight
            $totalWeight = 0;
            foreach ($items as $item) {
                $product = $item->getProduct();
                $weight = (float)($product->getWeight() ?: 0);
                $qty = $item->getQty();
                $totalWeight += $weight * $qty;
            }

            // Default to 1kg if no weight
            if ($totalWeight <= 0) {
                $totalWeight = 1;
            }

            // Get first external location for the shipping country
            $externalLocationId = $this->getExternalLocationByCountry($shippingCountry);

            // Fetch available couriers with prices from API
            $availableCouriers = $this->fetchAvailableCouriers(
                $shippingCountry,
                $shippingRegion,
                $shippingCity,
                $totalWeight,
                $externalLocationId
            );

            // Get courier names from database and merge with API prices
            if (!empty($availableCouriers)) {
                $table = $this->_resourceConnection->getTableName('innoship_courierlist');

                foreach ($availableCouriers as $courierId => $courierData) {
                    // SECURITY FIX: Validate courier ID is numeric
                    if (!is_numeric($courierId) || $courierId <= 0) {
                        continue;
                    }

                    // SECURITY FIX: Use parameterized query
                    $select = $connection->select()
                        ->from($table, ['courierName'])
                        ->where('courierId = ?', (int)$courierId)
                        ->limit(1);

                    $result = $connection->fetchOne($select);

                    if ($result) {
                        // SECURITY FIX: Validate and sanitize price from API
                        $price = isset($courierData['price']) && is_numeric($courierData['price'])
                            ? (float)$courierData['price']
                            : null;

                        $allCourier[$courierId] = [
                            'name' => htmlspecialchars($result, ENT_QUOTES, 'UTF-8'),
                            'price' => $price
                        ];
                    }
                }
            }

        } catch (\Exception $e) {
            // SECURITY FIX: Don't expose detailed error messages to users
            $this->logger->error('Error fetching courier prices', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Fallback: return couriers without prices
            $table = $this->_resourceConnection->getTableName('innoship_courierlist');

            // SECURITY FIX: Use parameterized query
            $select = $connection->select()->from($table);

            if ($countryCode) {
                $select->where('country = ? OR country IS NULL', $countryCode);
            }

            $courierList = $connection->fetchAll($select);

            foreach ($courierList as $courierSingle) {
                // SECURITY FIX: Validate courier ID
                if (!isset($courierSingle['courierId']) || !is_numeric($courierSingle['courierId'])) {
                    continue;
                }

                $allCourier[(int)$courierSingle['courierId']] = [
                    'name' => htmlspecialchars($courierSingle['courierName'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'price' => null
                ];
            }
        }

        return $resultJson->setData(['json_data' => $allCourier]);
    }

    /**
     * Get external location ID for a country
     *
     * @param string $countryCode
     * @return string
     */
    private function getExternalLocationByCountry($countryCode)
    {
        $collection = $this->externalCollectionFactory->create();
        $collection->addFieldToFilter('countryCode', $countryCode);
        $external = $collection->getFirstItem();

        if ($external->getId()) {
            return $external->getExternal();
        }

        // Fallback: get first available external location
        $collection = $this->externalCollectionFactory->create();
        $collection->setPageSize(1);
        $external = $collection->getFirstItem();

        return $external->getId() ? $external->getExternal() : 'Default';
    }

    /**
     * Fetch available couriers with prices from InnoShip API
     *
     * @param string $country
     * @param string $region
     * @param string $city
     * @param float $weight
     * @param string $externalLocationId
     * @return array Array of available couriers [courierId => ['price' => float]]
     */
    private function fetchAvailableCouriers($country, $region, $city, $weight, $externalLocationId)
    {
        $availableCouriers = [];

        try {
            // Get multiplicator from config
            $multiplicator = $this->config->getMultiplicator();
            if ($multiplicator === null || $multiplicator <= 0) {
                $multiplicator = 1;
            }

            // Prepare request data
            $tomorrow = new \DateTime('tomorrow');
            $requestData = [
                "ServiceId" => 0,
                "ShipmentDate" => $tomorrow->format('Y-m-d\TH:i:sP'),
                "AddressTo" => [
                    "Country" => $country,
                    "CountyName" => $region,
                    "LocalityName" => $city
                ],
                "Payment" => 1,
                "Content" => [
                    "EnvelopeCount" => 0,
                    "ParcelsCount" => 1,
                    "PalettesCount" => 0,
                    "TotalWeight" => $weight,
                    "Contents" => "products",
                    "Package" => "box",
                    "OversizedPackage" => false,
                    "Parcels" => []
                ],
                "Extra" => [
                    "BankRepaymentAmount" => "",
                    "OpenPackage" => false,
                    "SaturdayDelivery" => false,
                    "InsuranceAmount" => "",
                    "ReturnPackage" => false
                ],
                "ExternalClientLocation" => $externalLocationId
            ];

            // Make API request
            $response = $this->service->makeRequest('/api/Price', $requestData, ServiceInterface::POST);

            // Process all available couriers from API response
            if ((int)$response['status_code'] === 200 && isset($response['rates']) && is_array($response['rates']) && count($response['rates']) > 0) {
                foreach ($response['rates'] as $rate) {
                    // SECURITY FIX: Validate API response data structure
                    if (!is_array($rate)) {
                        continue;
                    }

                    // SECURITY FIX: Validate carrier ID
                    if (!isset($rate['carrierId']) || !is_numeric($rate['carrierId']) || $rate['carrierId'] <= 0) {
                        $this->logger->warning('Invalid carrierId in API response', ['rate' => $rate]);
                        continue;
                    }

                    // SECURITY FIX: Validate price
                    if (!isset($rate['rateTotalAmount']) || !is_numeric($rate['rateTotalAmount']) || $rate['rateTotalAmount'] < 0) {
                        $this->logger->warning('Invalid rateTotalAmount in API response', ['rate' => $rate]);
                        continue;
                    }

                    $courierId = (int)$rate['carrierId'];
                    $priceFromRate = (float)$rate['rateTotalAmount'];

                    // SECURITY FIX: Validate multiplicator is reasonable
                    if ($multiplicator < 0 || $multiplicator > 100) {
                        $this->logger->error('Invalid multiplicator value', ['multiplicator' => $multiplicator]);
                        $multiplicator = 1;
                    }

                    $finalPrice = round($priceFromRate * $multiplicator, 2);

                    // SECURITY FIX: Validate final price is reasonable
                    if ($finalPrice < 0 || $finalPrice > 999999.99) {
                        $this->logger->error('Calculated price out of range', [
                            'price' => $finalPrice,
                            'carrier_id' => $courierId
                        ]);
                        continue;
                    }

                    $availableCouriers[$courierId] = [
                        'price' => $finalPrice
                    ];
                }
            }

        } catch (\Exception $e) {
            // SECURITY FIX: Don't expose API details in logs
            $this->logger->error('Error fetching available couriers from API', [
                'exception' => $e->getMessage()
            ]);
        }

        return $availableCouriers;
    }
}
