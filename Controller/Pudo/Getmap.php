<?php

namespace InnoShip\InnoShip\Controller\Pudo;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use InnoShip\InnoShip\Model\Config;
use Psr\Log\LoggerInterface;

class Getmap extends Action
{
    private $_resourceConnection;
    private $resultJsonFactory;
    public $quoteRepository;
    private $maskedQuoteIdToQuoteId;
    protected $config;
    protected $logger;

    public function __construct(
        Context $context,
        ResourceConnection $resourceConnection,
        JsonFactory $resultJsonFactory,
        CartRepositoryInterface $quoteRepository,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->_resourceConnection = $resourceConnection;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->quoteRepository = $quoteRepository;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->config = $config;
        $this->logger = $logger;
        parent::__construct($context);
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        $pudoId = 0;

        $quoteId = $this->getRequest()->getParam('quote');
        $currentStoreId = $this->getRequest()->getParam('storeId');
        $selectedCourier = $this->getRequest()->getParam('courier');
        $countryParameter = $this->getRequest()->getParam('country');
        $countyParameter = $this->getRequest()->getParam('county');
        $localityParameter = $this->getRequest()->getParam('locality');
        $pudoSelectedParameter = $this->getRequest()->getParam('ps');
        $latParameter = $this->getRequest()->getParam('lat');
        $lngParameter = $this->getRequest()->getParam('lng');
        $radiusParameter = $this->getRequest()->getParam('radius', 5); // Default 5km

        // INPUT VALIDATION
        // 1. Validate country code (ISO 3166-1 alpha-2)
        if ($countryParameter) {
            // Normalize to uppercase for case-insensitive validation
            $countryParameter = strtoupper(trim($countryParameter));

            if (!preg_match('/^[A-Z]{2}$/', $countryParameter)) {
                return $resultJson->setData(['error' => __('Invalid country code.')]);
            }
        }

        // 2. Validate county name
        if ($countyParameter) {
            if (strlen($countyParameter) > 100) {
                return $resultJson->setData(['error' => __('County name too long.')]);
            }
            // Sanitize county name
            $countyParameter = htmlspecialchars($countyParameter, ENT_QUOTES, 'UTF-8');
        }

        // 3. Validate locality name
        if ($localityParameter) {
            if (strlen($localityParameter) > 100) {
                return $resultJson->setData(['error' => __('Locality name too long.')]);
            }
            // Sanitize locality name
            $localityParameter = htmlspecialchars($localityParameter, ENT_QUOTES, 'UTF-8');
        }

        // 4. Validate PUDO selected parameter
        if ($pudoSelectedParameter) {
            if (!is_numeric($pudoSelectedParameter) || $pudoSelectedParameter <= 0) {
                return $resultJson->setData(['error' => __('Invalid PUDO ID.')]);
            }
            $pudoSelectedParameter = (int)$pudoSelectedParameter;
        }

        // 5. Validate latitude and longitude
        if ($latParameter !== null || $lngParameter !== null) {
            // Both must be provided together
            if ($latParameter === null || $lngParameter === null) {
                return $resultJson->setData(['error' => __('Both latitude and longitude are required.')]);
            }

            // Validate they are numeric
            if (!is_numeric($latParameter) || !is_numeric($lngParameter)) {
                return $resultJson->setData(['error' => __('Invalid coordinates.')]);
            }

            // Validate coordinate ranges
            $latParameter = (float)$latParameter;
            $lngParameter = (float)$lngParameter;

            if ($latParameter < -90 || $latParameter > 90) {
                return $resultJson->setData(['error' => __('Invalid latitude. Must be between -90 and 90.')]);
            }

            if ($lngParameter < -180 || $lngParameter > 180) {
                return $resultJson->setData(['error' => __('Invalid longitude. Must be between -180 and 180.')]);
            }
        }

        // 6. Validate radius
        if ($radiusParameter) {
            if (!is_numeric($radiusParameter) || $radiusParameter <= 0) {
                $radiusParameter = 5; // Default
            } else {
                $radiusParameter = (float)$radiusParameter;
                // Limit radius to reasonable value (e.g., 100km)
                if ($radiusParameter > 100) {
                    $radiusParameter = 100;
                }
            }
        }

        // 7. Validate selected courier (comma-separated IDs)
        if ($selectedCourier) {
            // Sanitize courier list
            $courierIds = explode(',', $selectedCourier);
            $validCourierIds = [];

            foreach ($courierIds as $courierId) {
                $courierId = trim($courierId);
                if (is_numeric($courierId) && $courierId > 0) {
                    $validCourierIds[] = (int)$courierId;
                }
            }

            if (empty($validCourierIds)) {
                return $resultJson->setData(['error' => __('Invalid courier selection.')]);
            }

            $selectedCourier = implode(',', $validCourierIds);
        }

        if ($quoteId) {
            try {
                $quoteIdNr = $this->maskedQuoteIdToQuoteId->execute($quoteId);
            } catch (\Exception $exception) {
                $quoteIdNr = $quoteId;
            }

            try {
                $quote = $this->quoteRepository->get($quoteIdNr);
                $pudoId = $quote->getShippingAddress()->getInnoshipPudoId();
            } catch (NoSuchEntityException $exception) {
                // SECURITY: Log error but don't expose details to user
                return $resultJson->setData([
                    'error' => __('Unable to load map. Please try again.')
                ]);
            }
        }

        $connection = $this->_resourceConnection->getConnection();
        $allPudo = [];
        $county = [];
        $locality = [];
        $pudoSelected = [];
        $courierList = [];
        $courierNames = [];
        $courierQuery = '';

        $supportedPaymentType = " and supportedPaymentType in ('Card, Cash','Cash','Card')";
        $table = $this->_resourceConnection->getTableName('innoship_pudo');

        if ($countryParameter && !$countyParameter && !$localityParameter) {
            $result = $connection->select()->distinct(true)->from($table, array('countyName'))->where('countryCode = :countryParameter')->where('isActive = 1')->where('latitude != 0.000000')->where("supportedPaymentType in ('Card, Cash','Cash','Card')")->order('countyName ASC');
            $bind = ['countryParameter' => $countryParameter];
            $allRows = $connection->fetchAll($result, $bind);

            foreach ($allRows as $countValue) {
                $county[] = $countValue['countyName'];
            }
        }

        if ($countyParameter && !$localityParameter) {
            $result = $connection->select()->distinct(true)->from($table, array('localityName'))->where('countyName = :countyParameter')->where('isActive = 1')->where('latitude != 0.000000')->where("localityName <> 'Bucuresti'")->where("supportedPaymentType in ('Card, Cash','Cash','Card')")->order('localityName ASC');
            $bind = ['countyParameter' => $countyParameter];
            $allRows = $connection->fetchAll($result, $bind);

            foreach ($allRows as $localityValue) {
                $locality[] = $localityValue['localityName'];
            }
        }

        if ($localityParameter) {
            $stringExtraFixDpd = "";
            if ($localityParameter === "Sector 1" || $localityParameter === "Sector 2" || $localityParameter === "Sector 3" || $localityParameter === "Sector 4" || $localityParameter === "Sector 5" || $localityParameter === "Sector 6") {
                $stringExtraFixDpd = " or (courierid = 2 and localityName = 'sector 1')";
            }

            $tableNameCourierList = $this->_resourceConnection->getTableName('innoship_courierlist');
            $courierNameListQuery = $connection->query("select * from " . $tableNameCourierList . " order by courierName");
            foreach ($courierNameListQuery as $itemCourierNameItem) {
                $courierNames[$itemCourierNameItem['courierId']] = $itemCourierNameItem['courierName'];
            }

            $result = $connection->select()->from($table)
                //->where('localityName = :localityParameter')->where('isActive = 1')->where('latitude != 0.000000')->where("localityName <> 'Bucuresti'")->where("supportedPaymentType in ('Card, Cash','Cash','Card')")->order('localityName ASC');
                ->where('isActive = 1')
                ->where('latitude != 0.000000')
                ->where("supportedPaymentType IN ('Card, Cash', 'Cash', 'Card')")
                ->where(
                    "(localityName = :localityParameter and localityName <> 'Bucuresti')" . $stringExtraFixDpd
                )
                ->order('localityName ASC');
            $bind = ['localityParameter' => $localityParameter];
            $allRows = $connection->fetchAll($result, $bind);

            foreach ($allRows as $pudoValue) {
                if (isset($pudoValue['courierId']) && isset($courierNames[$pudoValue['courierId']])) {
                    $pudoValue['infoShow'] = $pudoValue['name'] . "<br/><b>Curier:</b> " . $courierNames[$pudoValue['courierId']];
                    $pudoValue['addressText'] = str_replace(","," ", $pudoValue['addressText']);
                    $allPudo[$pudoValue['pudo_id']] = $pudoValue;
                }
            }

            $result = $connection->select()->distinct(true)->from($table, array("courierId"))
                //->where('localityName = :localityParameter')->where('isActive = 1')->where('latitude != 0.000000')->where("localityName <> 'Bucuresti'")->where("supportedPaymentType in ('Card, Cash','Cash','Card')")->order('localityName ASC');
                ->where('isActive = 1')
                ->where('latitude != 0.000000')
                ->where("supportedPaymentType IN ('Card, Cash', 'Cash', 'Card')")
                ->where(
                    "(localityName = :localityParameter AND localityName <> 'Bucuresti')" . $stringExtraFixDpd
                )
                ->order('localityName ASC');
            $bind = ['localityParameter' => $localityParameter];
            $allRowsCourier = $connection->fetchAll($result, $bind);

            foreach ($allRowsCourier as $courierValue) {
                if (isset($courierValue['courierId']) && isset($courierNames[$courierValue['courierId']])) {
                    $courierList[$courierValue['courierId']] = $courierNames[$courierValue['courierId']];
                }
            }
        }

        // Radius-based search when map is dragged (lat/lng parameters provided)
        if ($latParameter && $lngParameter) {
            $this->logger->info('InnoShip Getmap: Radius search requested', [
                'lat' => $latParameter,
                'lng' => $lngParameter,
                'radius' => $radiusParameter
            ]);

            $tableNameCourierList = $this->_resourceConnection->getTableName('innoship_courierlist');
            $courierNameListQuery = $connection->query("select * from " . $tableNameCourierList . " order by courierName");
            foreach ($courierNameListQuery as $itemCourierNameItem) {
                $courierNames[$itemCourierNameItem['courierId']] = $itemCourierNameItem['courierName'];
            }

            // Calculate distance using Haversine formula in SQL
            // Radius of Earth in km is 6371
            $lat = (float) $latParameter;
            $lng = (float) $lngParameter;
            $radius = (float) $radiusParameter;

            $result = $connection->select()
                ->from($table)
                ->where('isActive = 1')
                ->where('latitude != 0.000000')
                ->where("supportedPaymentType IN ('Card, Cash', 'Cash', 'Card')")
                ->where(
                    "(6371 * acos(cos(radians($lat)) * cos(radians(latitude)) * cos(radians(longitude) - radians($lng)) + sin(radians($lat)) * sin(radians(latitude)))) <= ?",
                    $radius
                );

            if ($selectedCourier) {
                $result->where('courierId IN (?)', explode(',', $selectedCourier));
            }

            $result->order(['localityName ASC', 'name ASC']);

            $this->logger->info('InnoShip Getmap: SQL Query', ['query' => $result->__toString()]);

            $allRows = $connection->fetchAll($result);

            $this->logger->info('InnoShip Getmap: Found lockers in radius', ['count' => count($allRows)]);

            foreach ($allRows as $pudoValue) {
                if (isset($pudoValue['courierId']) && isset($courierNames[$pudoValue['courierId']])) {
                    $pudoValue['infoShow'] = $pudoValue['name'] . "<br/><b>Curier:</b> " . $courierNames[$pudoValue['courierId']];
                    $allPudo[$pudoValue['pudo_id']] = $pudoValue;
                }
            }

            // Get list of available couriers in the radius
            $resultCouriers = $connection->select()
                ->distinct(true)
                ->from($table, array("courierId"))
                ->where('isActive = 1')
                ->where('latitude != 0.000000')
                ->where("supportedPaymentType IN ('Card, Cash', 'Cash', 'Card')")
                ->where(
                    "(6371 * acos(cos(radians($lat)) * cos(radians(latitude)) * cos(radians(longitude) - radians($lng)) + sin(radians($lat)) * sin(radians(latitude)))) <= ?",
                    $radius
                );

            $allRowsCourier = $connection->fetchAll($resultCouriers);

            foreach ($allRowsCourier as $courierValue) {
                if (isset($courierValue['courierId']) && isset($courierNames[$courierValue['courierId']])) {
                    $courierList[$courierValue['courierId']] = $courierNames[$courierValue['courierId']];
                }
            }
        }

        if ($pudoSelectedParameter) {
            $tableNameCourierList = $this->_resourceConnection->getTableName('innoship_courierlist');
            $courierNameListQuery = $connection->query("select * from " . $tableNameCourierList);
            foreach ($courierNameListQuery as $itemCourierNameItem) {
                $courierNames[$itemCourierNameItem['courierId']] = $itemCourierNameItem['courierName'];
            }

            $result = $connection->select()->from($table, array("name", "courierId"))->where('pudo_id = :pudoSelectedParameter');
            $bind = ['pudoSelectedParameter' => $pudoSelectedParameter];
            $allRows = $connection->fetchAll($result, $bind);

            foreach ($allRows as $courierValue) {
                $pudoSelected[$pudoSelectedParameter]['infoShow'] = $courierValue['name'] . "<br/><b>Curier:</b> " . $courierNames[$courierValue['courierId']];
            }
        }

        return $resultJson->setData(['json_data' => $allPudo, 'county' => $county, 'locality' => $locality, 'pudoselected' => $pudoSelected, 'courierList' => $courierList]);
    }
}
