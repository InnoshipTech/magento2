<?php

namespace InnoShip\InnoShip\Model;

use InnoShip\InnoShip\Model\Api\Rest\Service;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Directory\Helper\Data;
use Magento\Directory\Model\CountryFactory;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Async\CallbackDeferred;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Xml\Security;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrierOnline;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Shipping\Model\Rate\Result\ProxyDeferredFactory;
use Magento\Shipping\Model\Simplexml\ElementFactory;
use Magento\Shipping\Model\Tracking\Result\ErrorFactory as TrackingErrorFactory;
use Magento\Shipping\Model\Tracking\Result\StatusFactory;
use Magento\Shipping\Model\Tracking\ResultFactory as TrackingResultFactory;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Framework\DataObjectFactory;
use Magento\Catalog\Model\ProductRepository;
use InnoShip\InnoShip\Model\Api\Order;
use PHPStan\Reflection\InaccessibleMethod;
use Psr\Log\LoggerInterface;
use InnoShip\InnoShip\Api\ServiceInterface;

/**
 * Class Carrier
 * @package InnoShip\InnoShip\Model
 */
class Carrier extends AbstractCarrierOnline implements CarrierInterface
{
    /** @var string */
    protected $_code = 'innoship';

    /** @var bool */
    protected $_isFixed = true;

    /** @var Result */
    protected $_result;

    /** @var @var RateRequest|null */
    protected $_request = null;

    /** @var ProxyDeferredFactory */
    protected $deferredProxyFactory;

    /** @var TrackFactory */
    protected $trackFactory;

    /** @var Json */
    protected $jsonSerializer;

    /** @var DataObjectFactory */
    protected $dataObjectFactory;

    /** @var Config\Source\Method */
    protected $method;

    protected $productRepository;

    protected $orderModel;

    protected $service;

    /** @var Json */
    protected $serializer;


    /**
     * Carrier constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param ErrorFactory $rateErrorFactory
     * @param LoggerInterface $logger
     * @param Security $xmlSecurity
     * @param ElementFactory $xmlElFactory
     * @param ResultFactory $rateFactory
     * @param MethodFactory $rateMethodFactory
     * @param TrackingResultFactory $trackResultFactory
     * @param TrackingErrorFactory $trackErrorFactory
     * @param StatusFactory $trackStatusFactory
     * @param ProductRepository $productRepository
     * @param Order $orderModel
     * @param RegionFactory $regionFactory
     * @param CountryFactory $countryFactory
     * @param CurrencyFactory $currencyFactory
     * @param Data $directoryData
     * @param StockRegistryInterface $stockRegistry
     * @param TrackFactory $trackFactory
     * @param Json $jsonSerializer
     * @param DataObjectFactory $dataObjectFactory
     * @param Config\Source\Method $method
     * @param array $data
     * @param ProxyDeferredFactory|null $proxyDeferredFactory
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        Security $xmlSecurity,
        ElementFactory $xmlElFactory,
        ResultFactory $rateFactory,
        MethodFactory $rateMethodFactory,
        TrackingResultFactory $trackResultFactory,
        TrackingErrorFactory $trackErrorFactory,
        StatusFactory $trackStatusFactory,
        ProductRepository $productRepository,
        Order $orderModel,
        Service $service,
        RegionFactory $regionFactory,
        CountryFactory $countryFactory,
        CurrencyFactory $currencyFactory,
        Data $directoryData,
        StockRegistryInterface $stockRegistry,
        TrackFactory $trackFactory,
        Json $jsonSerializer,
        DataObjectFactory $dataObjectFactory,
        Config\Source\Method $method,
        array $data = [],
        ?ProxyDeferredFactory $proxyDeferredFactory = null
    ) {
        parent::__construct(
            $scopeConfig,
            $rateErrorFactory,
            $logger,
            $xmlSecurity,
            $xmlElFactory,
            $rateFactory,
            $rateMethodFactory,
            $trackResultFactory,
            $trackErrorFactory,
            $trackStatusFactory,
            $regionFactory,
            $countryFactory,
            $currencyFactory,
            $directoryData,
            $stockRegistry,
            $data
        );

        $this->deferredProxyFactory = $proxyDeferredFactory ?? ObjectManager::getInstance()->get(ProxyDeferredFactory::class);
        $this->trackFactory = $trackFactory;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->jsonSerializer = $jsonSerializer;
        $this->serializer = $jsonSerializer;
        $this->method = $method;
        $this->productRepository = $productRepository;
        $this->orderModel = $orderModel;
        $this->service = $service;
    }

    /**
     * @inheritDoc
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->canCollectRates()) {
            return $this->getErrorMessage();
        }

        $this->_result = $result = $this->getQuotes($request);

        return $this->deferredProxyFactory->create(
            [
                'deferred' => new CallbackDeferred(
                    function () use ($request, $result) {
                        $this->_result = $result;
                        $this->_updateFreeMethodQuote($request);

                        return $this->getResult();
                    }
                ),
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function processAdditionalValidation(\Magento\Framework\DataObject $request)
    {
        return $this;
    }

    /**
     * Get result of request
     *
     * @return Result
     */
    public function getResult()
    {
        return $this->_result;
    }

    /**
     * @param $tracks
     *
     * @return Result
     * @throws NotFoundException
     */
    public function getTracking($tracks)
    {
        if (!is_array($tracks)) {
            $tracks = [$tracks];
        }

        $this->getInnoShipTracking($tracks);

        return $this->_result;
    }

    /**
     * @inheritDoc
     */
    public function getAllowedMethods()
    {
        $allowed = explode(',', (string) $this->getConfigData('allowed_methods'));

        $methods = [];

        foreach ($allowed as $k) {
            foreach ($this->method->toOptionArray() as $option) {
                if ($option['value'] == $k) {
                    $methods[$k] = $option['label'];
                }
            }
        }

        return $methods;
    }

    /**
     * @inheritDoc
     */
    public function isTrackingAvailable()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isShippingLabelsAvailable()
    {
        return false;
    }

    /**
     * @param $tracks
     *
     * @return mixed
     * @throws NotFoundException
     */
    protected function getInnoShipTracking($tracks)
    {
        $result = $this->_trackFactory->create();

        foreach ($tracks as $tracking) {
            $trackStatus = $this->_trackStatusFactory->create();

            $trackStatus->setCarrier($this->_code);
            $trackStatus->setCarrierTitle($this->getConfigData('title'));
            $trackStatus->setTracking($tracking);
            $trackStatus->setPopup(1);
            $trackStatus->setUrl($this->getInnoShipTrackingUrl($tracking));

            $result->append($trackStatus);
        }

        $this->_result = $result;

        return $result;
    }

    /**
     * @param string $trackingNumber
     *
     * @return string
     * @throws NotFoundException
     */
    protected function getInnoShipTrackingUrl(string $trackingNumber): string
    {
        /** @var \Magento\Sales\Model\Order\Shipment\Track $track */
        $track = $this->trackFactory->create();
        $track->load($trackingNumber, 'track_number');

        if (!$track->getId()) {
            throw new NotFoundException('Invalid tracking number!');
        }

        /** @var \Magento\Framework\DataObject $innoShipData */
        $innoShipData = $this->dataObjectFactory->create(
            [
                'data' => $this->jsonSerializer->unserialize($track->getInnoshipData()),
            ]
        );

        return $innoShipData->getDataByKey('trackPageUrl');
    }

    /**
     * @inheritDoc
     */
    protected function _doShipmentRequest(\Magento\Framework\DataObject $request)
    {
        return new \Magento\Framework\DataObject();
    }

    /**
     * @return Result
     */
    protected function getQuotes($request)
    {
        /** @var Result $result */
        $result = $this->_rateFactory->create();

        $storeId = $request->getStoreId();

        $allowedMethods = explode(",", (string) $this->getConfigData('allowed_methods', $storeId));

        foreach ($allowedMethods as $method) {
            $price = $this->getMethodPrice(0, $method);

            // Check for custom price set on address
            $items = $request->getAllItems();
            if (!empty($items)) {
                $firstItem = reset($items);
                $quote = $firstItem->getQuote();
                if ($quote) {
                    $shippingAddress = $quote->getShippingAddress();
                    $customPrice = $shippingAddress->getInnoshipShippingPrice();
                    if ($customPrice !== null && $customPrice !== '') {
                        $price = (float) $customPrice;
                        // Skip other calculations if custom price is set
                        goto create_rate;
                    }
                }
            }

            $destinationCountry = $request->getData('dest_country_id');

            // Try new country fees structure first
            $countryFeesData = $this->getCountrySpecificFee($destinationCountry, $storeId);
            if ($countryFeesData !== null) {
                $price = $countryFeesData['fee'];

                // Check if free shipping threshold is met for this country
                if (
                    isset($countryFeesData['free_shipping_threshold']) &&
                    !empty($countryFeesData['free_shipping_threshold']) &&
                    $countryFeesData['free_shipping_threshold'] > 0
                ) {

                    $items = $request->getAllItems();
                    $itemDiscountAmount = 0;
                    foreach ($items as $item) {
                        $itemDiscountAmount += $item->getDiscountAmount();
                    }
                    $subtotalFinal = $request->getBaseSubtotalInclTax() - $itemDiscountAmount;

                    if ($subtotalFinal >= $countryFeesData['free_shipping_threshold']) {
                        $price = 0.0;
                    }
                }
            } else {
                // Fallback to old format for backward compatibility
                $destinationHandlingFeeExt = explode(",", (string) $this->getConfigData('handling_fee_external', $storeId));
                foreach ($destinationHandlingFeeExt as $locatieExterna) {
                    $locatieExternaInfo = explode(":", (string) $locatieExterna);
                    if (strtolower($destinationCountry) === strtolower($locatieExternaInfo[0])) {
                        $price = $locatieExternaInfo[1];
                    }
                }
            }

            /************************* Automatic price *********************************/
            $automaticPrice = (int) $this->getConfigData('automatic_price', $storeId);
            if ($automaticPrice === 1) {
                $response = false;
                $skuList = [];
                $parcelsData = [];
                $quote = null;
                $requestData = null;
                $rates = null;
                $weightObj = 0;
                $volumetrie = 0;

                $items = $request->getAllItems();

                $attrLungime = $this->getConfigData('lungime', $storeId);
                $attrLatime = $this->getConfigData('latime', $storeId);
                $attrInaltime = $this->getConfigData('inaltime', $storeId);

                $externalLocationIdAll = explode(",", $this->getConfigData('external_id_send', $storeId));

                $multiplicator = $this->getConfigData('multiplicator', $storeId);
                if (strlen((string) $multiplicator) > 0 && $multiplicator !== null) {
                    $multiplicator = (float) $multiplicator;
                } else {
                    $multiplicator = 1;
                }

                foreach ($items as $item) {
                    $skuList[$item->getSku()] = $item->getQty();
                    $quote = $item->getQuote();
                }

                if ($quote) {
                    $lungimeTmp = 0;
                    $latimeTmp = 0;
                    $inaltimeTmp = 0;

                    $quoteShippingData = $quote->getShippingAddress();
                    $quoteBillingData = $quote->getBillingAddress();
                    $shippingCountry = $quoteShippingData->getData('country_id');
                    $shippingRegio = $quoteShippingData->getData('region');
                    if ($shippingRegio === null) {
                        $shippingRegio = '';
                    } elseif ($shippingRegio instanceof \Magento\Customer\Model\Data\Region) {
                        $shippingRegio = $shippingRegio->getRegion() ?: '';
                    } elseif (is_object($shippingRegio) && method_exists($shippingRegio, 'getRegion')) {
                        $shippingRegio = $shippingRegio->getRegion() ?: '';
                    }
                    $shippingCity = $quoteShippingData->getData('city');
                    if ($shippingCity === null) {
                        $shippingCity = '';
                    }

                    if ((string) $shippingCountry === (string) $quoteBillingData->getData('country_id')) {
                        $serviceID = 1;
                    } else {
                        $serviceID = 5;
                    }

                    if (is_array($shippingRegio)) {
                        $shippingRegio = $shippingRegio["region"];
                    }

                    #FIX when region is not available in QUOTE
                    if ($shippingRegio === '') {
                        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

                        $shippingRegioId = $quoteShippingData->getData('region_id');
                        if (is_array($shippingRegioId)) {
                            $shippingRegioIdUse = $shippingRegioId[0];
                        } else {
                            $shippingRegioIdUse = $shippingRegioId;
                        }
                        $shippingRegioObj = $objectManager->create('Magento\Directory\Model\Region')->load($shippingRegioIdUse);
                        $shippingRegio = $shippingRegioObj['default_name'];
                        if ($shippingRegio === null) {
                            $shippingRegio = '';
                        }
                    }
                    #END FIX

                    if (strlen($shippingRegio) > 1 && strlen($shippingCity) > 1) {
                        foreach ($skuList as $sku => $qty) {
                            $product = $this->productRepository->get($sku);

                            if ($attrLungime && $attrLatime && $attrInaltime) {
                                $lungimeTmp = (float) $product->getData((string) $attrLungime) * $qty;
                                $latimeTmp = (float) $product->getData((string) $attrLatime) * $qty;
                                $inaltimeTmp = (float) $product->getData((string) $attrInaltime) * $qty;
                            }

                            $weightTmp = (float) ($product->getWeight() ?: 0) * $qty;

                            if ($lungimeTmp > 0 && $latimeTmp > 0 && $inaltimeTmp > 0) {
                                $volumetrie += $lungimeTmp * $latimeTmp * $inaltimeTmp;
                            }

                            if ($weightTmp > 0) {
                                $weightObj += $weightTmp;
                            }
                        }

                        $volumetrieCh = (float) $volumetrie / 6000;

                        $weightSend = $weightObj;
                        if ($volumetrieCh > $weightObj) {
                            $weightSend = $volumetrieCh;
                        }

                        // Ensure there's at least one external location ID
                        $externalLocationId = isset($externalLocationIdAll[0]) ? $externalLocationIdAll[0] : '';

                        $requestData = [
                            "ServiceId" => $serviceID,
                            "ShipmentDate" => date("Y-m-d\TH:i:s"),
                            "AddressTo" =>
                                [
                                    "Country" => $shippingCountry,
                                    "CountyName" => $shippingRegio,
                                    "LocalityName" => $shippingCity
                                ],
                            "Payment" => 1,
                            "Content" =>
                                [
                                    "EnvelopeCount" => 0,
                                    "ParcelsCount" => 1,
                                    "PalettesCount" => 0,
                                    "TotalWeight" => $weightSend,
                                    "Contents" => "products",
                                    "parcels" => $parcelsData
                                ],
                            "Extra" =>
                                [
                                    "BankRepaymentAmount" => 0
                                ],
                            "ExternalClientLocation" => $externalLocationId,
                        ];

                        $response = $this->service->makeRequest('/api/Price', $requestData, ServiceInterface::POST);
                        if ((int) $response['status_code'] === 200) {
                            if (isset($response['rates'])) {
                                $rates = $response['rates'];

                                if (count($rates) > 0) {
                                    $priceFromRate = $rates[0]['rateTotalAmount'];
                                    $price = round($priceFromRate * $multiplicator, 2);
                                }
                            }
                        }
                    }
                }
            }
            /***************************************************************************/

            if ($this->getConfigData('free_shipping_enable', $storeId)) {
                $items = $request->getAllItems();
                $itemDiscountAmount = 0;
                foreach ($items as $item) {
                    $itemDiscountAmount += $item->getDiscountAmount();
                }
                $subtotalFinal = $request->getBaseSubtotalInclTax() - $itemDiscountAmount;
                if ($subtotalFinal >= $this->getConfigData('free_shipping_subtotal', $storeId)) {
                    $price = 0.0;
                }
            }

            if ((int) $request->getData('free_shipping') === 1) {
                $price = 0.0;
            }

            //            $price = (int)$request->getData('free_shipping');

            create_rate:
            /** @var Method $method */
            $rate = $this->_rateMethodFactory->create();

            $rate->setCarrier($this->_code);
            $rate->setCarrierTitle($this->getConfigData('title', $storeId));
            $rate->setMethodTitle($this->getConfigData('name', $storeId));
            $rate->setMethod($method);

            $rate->setPrice($price);
            $rate->setCost($price);

            $result->append($rate);
        }


        return $result;
    }

    /**
     * Get country-specific fee from the new configuration structure
     *
     * @param string $countryCode
     * @param int $storeId
     * @return array|null Returns array with 'fee' and 'free_shipping_threshold' keys or null
     */
    protected function getCountrySpecificFee($countryCode, $storeId)
    {
        $countryFeesData = $this->getConfigData('country_fees', $storeId);

        if (empty($countryFeesData)) {
            return null;
        }

        try {
            $countryFees = $this->serializer->unserialize($countryFeesData);

            if (!is_array($countryFees)) {
                return null;
            }

            foreach ($countryFees as $row) {
                if (isset($row['country_id']) && isset($row['fee'])) {
                    if (strtoupper($row['country_id']) === strtoupper($countryCode)) {
                        return [
                            'fee' => (float) $row['fee'],
                            'free_shipping_threshold' => isset($row['free_shipping_threshold']) && !empty($row['free_shipping_threshold'])
                                ? (float) $row['free_shipping_threshold']
                                : 0
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }
}
