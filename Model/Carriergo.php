<?php

namespace InnoShip\InnoShip\Model;

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

/**
 * Class Carrier
 * @package InnoShip\InnoShip\Model
 */
class Carriergo extends AbstractCarrierOnline implements CarrierInterface
{
    /** @var string */
    protected $_code = 'innoshipcargusgo';

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

    /** @var Json */
    protected $serializer;

    /**
     * Carrier constructor.
     *
     * @param ScopeConfigInterface      $scopeConfig
     * @param ErrorFactory              $rateErrorFactory
     * @param \Psr\Log\LoggerInterface  $logger
     * @param Security                  $xmlSecurity
     * @param ElementFactory            $xmlElFactory
     * @param ResultFactory             $rateFactory
     * @param MethodFactory             $rateMethodFactory
     * @param TrackingResultFactory     $trackResultFactory
     * @param TrackingErrorFactory      $trackErrorFactory
     * @param StatusFactory             $trackStatusFactory
     * @param RegionFactory             $regionFactory
     * @param CountryFactory            $countryFactory
     * @param CurrencyFactory           $currencyFactory
     * @param Data                      $directoryData
     * @param StockRegistryInterface    $stockRegistry
     * @param TrackFactory              $trackFactory
     * @param Json                      $jsonSerializer
     * @param DataObjectFactory         $dataObjectFactory
     * @param Config\Source\Method      $method
     * @param array                     $data
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
        \Magento\Catalog\Model\ProductRepository $productRepository,
        RegionFactory $regionFactory,
        CountryFactory $countryFactory,
        CurrencyFactory $currencyFactory,
        Data $directoryData,
        StockRegistryInterface $stockRegistry,
        TrackFactory $trackFactory,
        Json $jsonSerializer,
        DataObjectFactory $dataObjectFactory,
        Config\Source\Methodgo $method,
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
        $this->trackFactory         = $trackFactory;
        $this->dataObjectFactory    = $dataObjectFactory;
        $this->jsonSerializer       = $jsonSerializer;
        $this->serializer           = $jsonSerializer;
        $this->method               = $method;
        $this->productRepository    = $productRepository;

    }

    /**
     * @inheritDoc
     */
    public function collectRates(RateRequest $request)
    {
        if ( ! $this->canCollectRates()) {
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
        if ( ! is_array($tracks)) {
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
        $allowed = explode(',', (string)$this->getConfigData('allowed_methods'));

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

        if ( ! $track->getId()) {
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
        $lungimeMaxima = 44.5;
        $latimeMaxima = 39;
        $inaltimeMaxima = 47;
        $volumMaxim = 81568.5;

        /** @var Result $result */
        $result = $this->_rateFactory->create();
        $method = "innoshipcargusgo_1";
        $storeId = $request->getStoreId();
        $price = $this->getMethodPrice(0, $method);

        // Check for country-specific PUDO fees
        $destinationCountry = $request->getData('dest_country_id');
        $countryFeesData = $this->getCountrySpecificPudoFee($destinationCountry, $storeId);
        if ($countryFeesData !== null) {
            $price = $countryFeesData['fee'];

            // Check if free shipping threshold is met for this country
            if (isset($countryFeesData['free_shipping_threshold']) &&
                !empty($countryFeesData['free_shipping_threshold']) &&
                $countryFeesData['free_shipping_threshold'] > 0) {

                $items = $request->getAllItems();
                $itemDiscountAmount = 0;
                foreach($items as $item){
                    $itemDiscountAmount+= $item->getDiscountAmount();
                }
                $subtotalFinal = $request->getBaseSubtotalInclTax() - $itemDiscountAmount;

                if ($subtotalFinal >= $countryFeesData['free_shipping_threshold']) {
                    $price = 0.0;
                }
            }
        }

        /************************* Check if available *********************************/
        $checkIfAvailable = (int)$this->getConfigData('check_if_available', $storeId);
        if($checkIfAvailable === 1){
            $items = $request->getAllItems();

            $lungimeObj = 0;
            $latimeObj = 0;
            $inaltimeObj = 0;
            $volumeObj = 0;

            $attrLungime = $this->getConfigData('lungime', $storeId);
            $attrLatime = $this->getConfigData('latime', $storeId);
            $attrInaltime = $this->getConfigData('inaltime', $storeId);

            $skuList = array();
            foreach($items as $item){
                $skuList[$item->getSku()] = $item->getQty();
            }

            foreach($skuList as $sku => $qty){
                $product = $this->productRepository->get($sku);

                $lungimeTmp     = (float)$product->getData((string)$attrLungime);
                $latimeTmp      = (float)$product->getData((string)$attrLatime);
                $inaltimeTmp    = (float)$product->getData((string)$attrInaltime);

                if($lungimeTmp > $lungimeObj){
                    $lungimeObj = $lungimeTmp;
                }

                if($latimeTmp > $latimeObj){
                    $latimeObj = $latimeTmp;
                }

                if($inaltimeTmp > $inaltimeObj){
                    $inaltimeObj = $inaltimeTmp;
                }

                if($lungimeTmp > 0 && $latimeTmp > 0 && $inaltimeTmp > 0){
                    $volumeObj+= $lungimeObj * $latimeObj * $inaltimeObj * $qty;
                }
            }

            if ($lungimeObj > $lungimeMaxima || $latimeObj > $latimeMaxima || $inaltimeObj > $inaltimeMaxima) {
                return $result;
            }

            if($volumeObj > $volumMaxim) {
                return $result;
            }
        }
        /***************************************************************************/

        if ($this->getConfigData('free_shipping_enable', $storeId)) {
            $items = $request->getAllItems();
            $itemDiscountAmount = 0;
            foreach($items as $item){
                $itemDiscountAmount+= $item->getDiscountAmount();
            }
            $subtotalFinal = $request->getBaseSubtotalInclTax() - $itemDiscountAmount;
            if ($subtotalFinal >= $this->getConfigData('free_shipping_subtotal', $storeId)) {
                $price = 0.0;
            }
        }

        if((int)$request->getData('free_shipping') === 1){
            $price = 0.0;
        }

        /** @var Method $method */
        $rate = $this->_rateMethodFactory->create();

        $rate->setCarrier($this->_code);
        $rate->setCarrierTitle($this->getConfigData('title', $storeId));
        $rate->setMethodTitle($this->getConfigData('name', $storeId));
        $rate->setMethod($method);

        $rate->setPrice($price);
        $rate->setCost($price);

        $result->append($rate);

        return $result;
    }

    /**
     * Get country-specific PUDO fee from the new configuration structure
     *
     * @param string $countryCode
     * @param int $storeId
     * @return array|null Returns array with 'fee' and 'free_shipping_threshold' keys or null
     */
    protected function getCountrySpecificPudoFee($countryCode, $storeId)
    {
        $countryFeesData = $this->getConfigData('country_pudo_fees', $storeId);

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
                            'fee' => (float)$row['fee'],
                            'free_shipping_threshold' => isset($row['free_shipping_threshold']) && !empty($row['free_shipping_threshold'])
                                ? (float)$row['free_shipping_threshold']
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
