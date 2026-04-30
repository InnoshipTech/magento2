<?php

namespace InnoShip\InnoShip\Controller\Adminhtml\Awb;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Message\Manager;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\ShipmentInterface;
use Magento\Sales\Api\Data\ShipmentTrackExtensionFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order\Shipment\Comment;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Sales\Model\Order\Shipment\Track;
use Magento\Sales\Model\Order\ShipmentRepository;
use InnoShip\InnoShip\Model\Carrier;
use InnoShip\InnoShip\Model\Api\Order;
use InnoShip\InnoShip\Model\Config;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Model\Order\Shipment\CommentFactory;
use InnoShip\InnoShip\Model\Table;
use Magento\Shipping\Model\ShipmentNotifier;
use InnoShip\InnoShip\Helper\ExternalSync;

class Save extends Action
{
    /** @var Manager */
    protected $manager;

    /** @var Order */
    protected $orderModel;

    /** @var Config */
    protected $config;

    /** @var Session */
    protected $session;

    /** @var ShipmentRepository */
    protected $shipmentRepository;

    /** @var TrackFactory */
    protected $trackFactory;

    /** @var OrderRepositoryInterface */
    protected $orderRepositoryInterface;

    /** @var ShipmentRepositoryInterface */
    protected $shipmentRepositoryInterface;

    /** @var DateTime */
    protected $dateTime;

    /** @var CommentFactory\ */
    protected $commentFactory;

    /** @var Table */
    protected $table;

    /** @var Carrier */
    protected $innoshipCarrier;

    /** @var Json  */
    protected $jsonSerializer;

    /**
     * The ShipmentNotifier class is used to send a notification email to the customer.
     *
     * @var ShipmentNotifier
     */
    protected $shipmentNotifier;

    /** @var ExternalSync */
    protected $externalSync;

    private $_resourceConnection;

    /**
     * Save constructor.
     *
     * @param Context $context
     * @param Manager $manager
     * @param Order $orderModel
     * @param Config $config
     * @param Session $session
     * @param ShipmentRepository $shipmentRepository
     * @param OrderRepositoryInterface $orderRepositoryInterface
     * @param ShipmentRepositoryInterface $shipmentRepositoryInterface
     * @param DateTime $dateTime
     * @param TrackFactory $trackFactory
     * @param CommentFactory $commentFactory
     * @param Table $table
     * @param Carrier $innoshipCarrier
     * @param Json $jsonSerializer
     * @param ShipmentNotifier $shipmentNotifier
     * @param ResourceConnection $resourceConnection
     * @param ExternalSync $externalSync
     */
    public function __construct(
        Action\Context $context,
        Manager $manager,
        Order $orderModel,
        Config $config,
        Session $session,
        ShipmentRepository $shipmentRepository,
        OrderRepositoryInterface $orderRepositoryInterface,
        ShipmentRepositoryInterface $shipmentRepositoryInterface,
        DateTime $dateTime,
        TrackFactory $trackFactory,
        CommentFactory $commentFactory,
        Table $table,
        Carrier $innoshipCarrier,
        Json $jsonSerializer,
        ShipmentNotifier $shipmentNotifier,
        ResourceConnection $resourceConnection,
        ExternalSync $externalSync

    ) {
        $this->manager                     = $manager;
        $this->orderModel                  = $orderModel;
        $this->config                      = $config;
        $this->session                     = $session;
        $this->shipmentRepository          = $shipmentRepository;
        $this->orderRepositoryInterface    = $orderRepositoryInterface;
        $this->shipmentRepositoryInterface = $shipmentRepositoryInterface;
        $this->dateTime                    = $dateTime;
        $this->trackFactory                = $trackFactory;
        $this->commentFactory              = $commentFactory;
        $this->table                       = $table;
        $this->innoshipCarrier             = $innoshipCarrier;
        $this->jsonSerializer              = $jsonSerializer;
        $this->shipmentNotifier            = $shipmentNotifier;
        $this->_resourceConnection         = $resourceConnection;
        $this->externalSync                = $externalSync;

        parent::__construct($context);
    }

    /**
     * @return Redirect|ResponseInterface|ResultInterface
     * @throws \Exception
     */
    public function execute()
    {
        // INPUT VALIDATION - Security enhancement
        $orderId    = $this->getRequest()->getParam('order_id');
        $shipmentId = $this->getRequest()->getParam('shipment_id');
        $data       = $this->getRequest()->getPostValue();

        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        // 1. Validate required data
        if (!$data) {
            $this->manager->addErrorMessage(__('Invalid request! Empty data!'));
            return $resultRedirect->setPath('sales/order/index');
        }

        // 2. Validate order_id
        if (!$orderId || !is_numeric($orderId) || (int)$orderId <= 0) {
            $this->manager->addErrorMessage(__('Invalid order ID.'));
            return $resultRedirect->setPath('sales/order/index');
        }
        $orderId = (int)$orderId;

        // 3. Validate shipment_id
        if (!$shipmentId || !is_numeric($shipmentId) || (int)$shipmentId <= 0) {
            $this->manager->addErrorMessage(__('Invalid shipment ID.'));
            return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);
        }
        $shipmentId = (int)$shipmentId;

        // 4. Validate package counts
        $envelopeCount = isset($data['envelope_count']) ? max(0, (int)$data['envelope_count']) : 0;
        $parcelCount = isset($data['parcel_count']) ? max(0, (int)$data['parcel_count']) : 0;
        $palletCount = isset($data['pallet_count']) ? max(0, (int)$data['pallet_count']) : 0;

        // 5. Validate total weight
        if (isset($data['total_weight'])) {
            $totalWeight = (float)$data['total_weight'];
            if ($totalWeight < 0 || $totalWeight > 100000) {
                $this->manager->addErrorMessage(__('Invalid total weight. Must be between 0 and 100000 kg.'));
                return $resultRedirect->setPath('innoship/awb/create', ['shipment_id' => $shipmentId]);
            }
            $data['total_weight'] = $totalWeight;
        }

        // 6. Validate declared value if present
        if (isset($data['declared_value'])) {
            $declaredValue = (float)$data['declared_value'];
            if ($declaredValue < 0 || $declaredValue > 9999999.99) {
                $this->manager->addErrorMessage(__('Invalid declared value. Must be between 0 and 9,999,999.99.'));
                return $resultRedirect->setPath('innoship/awb/create', ['shipment_id' => $shipmentId]);
            }
            $data['declared_value'] = $declaredValue;
        }

        // 7. Validate external_id if present
        if (isset($data['external_id'])) {
            $externalId = trim($data['external_id']);
            // Allow only alphanumeric, dash, and underscore
            if (!preg_match('/^[A-Za-z0-9_-]+$/', $externalId) || strlen($externalId) > 100) {
                $this->manager->addErrorMessage(__('Invalid external location ID format.'));
                return $resultRedirect->setPath('innoship/awb/create', ['shipment_id' => $shipmentId]);
            }
            $data['external_id'] = $externalId;
        }

        // 8. Validate packages array if present
        if (isset($data['packages']) && is_array($data['packages'])) {
            $validatedPackages = [];
            foreach ($data['packages'] as $key => $package) {
                if (!is_array($package)) {
                    continue;
                }

                $validatedPackages[$key] = [
                    'width' => isset($package['width']) ? max(1, min(500, (float)$package['width'])) : 10,
                    'height' => isset($package['height']) ? max(1, min(500, (float)$package['height'])) : 10,
                    'length' => isset($package['length']) ? max(1, min(500, (float)$package['length'])) : 10,
                    'weight' => isset($package['weight']) ? max(0.1, min(10000, (float)$package['weight'])) : 1,
                    'type' => isset($package['type']) ? max(1, min(5, (int)$package['type'])) : 2,
                    'reference' => isset($package['reference']) ? substr(strip_tags($package['reference']), 0, 255) : '-'
                ];
            }
            $data['packages'] = $validatedPackages;
        }

        // Update validated counts back to data array
        $data['envelope_count'] = $envelopeCount;
        $data['parcel_count'] = $parcelCount;
        $data['pallet_count'] = $palletCount;

        if (($data['envelope_count'] + $data['parcel_count'] + $data['pallet_count']) < 1) {
            $this->manager->addError('You must specify at least one Envelope / Parcel / Pallet!');

            $this->session->setFormData($data);

            return $resultRedirect->setPath('innoship/awb/create', ['shipment_id' => $shipmentId]);
        }

        try {
            $shipment    = $this->getShipment($shipmentId);
            $order       = $this->getOrder($orderId);
            $requestData = $this->prepareRequest($order, $shipment, $data);

            $response = $this->orderModel->create($requestData);

            if (false === $response->getSuccess()) {
                $this->saveComment($shipment, $response->getStatusMessage());
                $this->manager->addError($response->getStatusMessage());

                /********** reinitialize data ****************************/
                $items = $order->getAllVisibleItems();
                $weight = 0;
                foreach($items as $item) {
                    $weight = $weight + $item->getData('weight') * $item->getData('qty_ordered');
                }
                if($weight < 1){
                    $weight = 1;
                }
                $data['parcel_count'] = 1;
                $data['total_weight'] = round($weight,1);
                /********************************************************/
                $this->session->setFormData($data);

                return $resultRedirect->setPath('innoship/awb/create', ['shipment_id' => $shipmentId]);
            }

            // Add tracking info
            $this->addTrack($shipment, $response);

            // Add comments
            $this->addComments($shipment, $response);

            $this->manager->addSuccess(__('The AWB has been created successfully!'));

            $this->session->setFormData(false);

            return $resultRedirect->setPath('adminhtml/order_shipment/view', ['shipment_id' => $shipmentId]);
        } catch (\Throwable $e) {
            $this->manager->addError("E".$e->getMessage());
            $data['parcel_count'] = 1;
            $this->session->setFormData($data);

            return $resultRedirect->setPath('innoship/awb/create', ['shipment_id' => $shipmentId]);
        }
    }

    /**
     * @param ShipmentInterface $shipment
     * @param DataObject        $response
     *
     * @throws CouldNotSaveException
     */
    protected function addTrack(ShipmentInterface $shipment, DataObject $response)
    {
        $description    = __('Courier: %1', $this->table->getCourier($response->getDataByKey('courier')));
        $trackingNumber = $response->getDataByKey('courierShipmentId');

        /** @var Track $track */
        $track = $this->trackFactory->create();

        $track->setCarrierCode($this->innoshipCarrier->getCarrierCode());
        $track->setTitle($description);
        $track->setDescription($description);
        $track->setTrackNumber($trackingNumber);
        $track->setInnoshipData($this->jsonSerializer->serialize($response->getData()));

        $shipment->addTrack($track);

        $this->shipmentRepository->save($shipment);

        $this->shipmentNotifier->notify($shipment);
    }

    /**
     * @param string $orderId
     *
     * @return OrderInterface
     */
    protected function getOrder(string $orderId): OrderInterface
    {
        return $this->orderRepositoryInterface->get($orderId);
    }

    /**
     * @param string $shipmentId
     *
     * @return ShipmentInterface
     */
    protected function getShipment(string $shipmentId): ShipmentInterface
    {
        return $this->shipmentRepositoryInterface->get($shipmentId);
    }

    /**
     * @param ShipmentInterface $shipment
     * @param DataObject        $response
     *
     * @throws CouldNotSaveException
     */
    protected function addComments(ShipmentInterface $shipment, DataObject $response)
    {
        $this->saveComment(
            $shipment,
            __('InnoShip ID: ') . $response->getDataByKey('clientOrderId')
        );

        $this->saveComment(
            $shipment,
            __('AWB: ') . $response->getDataByKey('courierShipmentId')
        );

        $this->saveComment(
            $shipment,
            __('Courier: ') . $this->table->getCourier($response->getDataByKey('courier'))
        );

        $this->saveComment(
            $shipment,
            __('Shipment Price: ') . json_encode($response->getDataByKey('price'))
        );

        $this->saveComment(
            $shipment,
            __('Estimated delivery date: ') . $this->dateTime->gmtDate(null, strtotime($response->getDataByKey('calculatedDeliveryDate')))
        );

        $this->saveComment(
            $shipment,
            __('Track Page URL: ') . $response->getDataByKey('trackPageUrl')
        );
    }

    /**
     * @param ShipmentInterface $shipment
     * @param string            $text
     *
     * @throws CouldNotSaveException
     */
    protected function saveComment(ShipmentInterface $shipment, string $text)
    {
        /** @var Comment $comment */
        $comment = $this->commentFactory->create();

        $comment->setComment('[InnoShip] ' . $text);
        $comment->setIsCustomerNotified(false);

        $shipment->addComment($comment);

        $this->shipmentRepository->save($shipment);
    }

    /**
     * @param OrderInterface    $order
     * @param ShipmentInterface $shipment
     * @param array             $data
     *
     * @return array
     *
     * @TODO use model
     */
    protected function prepareRequest(OrderInterface $order, ShipmentInterface $shipment, array $data): array
    {
        $sequenceNo = 1;
        $parcels    = [];
        $packages   = array_key_exists('packages', $data) ? $data['packages'] : [];

        foreach ($packages as $key => $package) {
            $parcels[] = [
                "sequenceNo" => $sequenceNo,
                "size"       => [
                    "width"  => $package['width'],
                    "height" => $package['height'],
                    "length" => $package['length'],
                ],
                "weight"     => $package['weight'],
                "type"       => $package['type'],
                "reference1" => $package['reference'],
            ];

            $sequenceNo++;
        }

        if(empty($parcels)){
            $iParcels = 1;
            $parcelCount = (int)$data['parcel_count'];
            while($iParcels <= $parcelCount){
                $parcels[] = array(
                    "sequenceNo" => $iParcels,
                    "size"       => [
                        "width"  => 10,
                        "height" => 10,
                        "length" => 10,
                    ],
                    "weight"     => 1,
                    "type"       => 2,
                    "reference1" => "-",
                );
                ++$iParcels;
            }
        }
        $shippingAddress = $order->getShippingAddress();
        $billingAddress = $order->getBillingAddress();

        $payment = $order->getPayment();
        $method = $payment->getMethodInstance()->getCode();

        $methodsException = explode(',', (string)$this->config->getExceptionMethods());
        if(in_array($method, $methodsException)){
            $bankRepaymentAmount = null;
            $cashOnDeliveryAmount = null;
        } else {
            $bankRepaymentAmount = round($order->getGrandTotal(),2);
            $cashOnDeliveryAmount = null;
        }
        $externaleID = $data['externalid'];

        // Determine Service ID based on externalid
        $serviceID = 1; // Default value

        // Get external location data from database
        $externalLocation = $this->externalSync->getExternalLocationByExternalId($externaleID);

        if ($externalLocation) {
            // Get the country code from external location
            $externalCountryCode = $externalLocation->getCountryCode();
            $shippingCountryCode = $shippingAddress->getCountryId();

            // If shipping country code matches external location country code, use serviceID = 1
            // Otherwise use serviceID = 5
            if ((string)$shippingCountryCode === (string)$externalCountryCode) {
                $serviceID = 1;
            } else {
                $serviceID = 5;
            }
        } else {
            // Fallback to old logic if external location not found in database
            if ((string)$shippingAddress->getCountryId() === (string)$billingAddress->getCountryId()) {
                $serviceID = 1;
            } else {
                $serviceID = 5;
            }
        }
        // END Determine Service ID

        $apiName = $shippingAddress->getFirstName() . ' ' . $shippingAddress->getLastName();
        $shippingData = $shippingAddress->getData();
        if(isset($shippingData['company'])){
            if(strlen($shippingData['company']) > 3){
                $apiName = $shippingData['company'];
            }
        }

        $saveShipmentReturn =  [
            'serviceId'              => $serviceID, // The only available method
            'shipmentDate'           => $this->dateTime->gmtDate('c', strtotime($data['shipment_date'])),
            'addressTo'              => [
                'name'          => $apiName,
                'contactPerson' => $shippingAddress->getFirstName() . ' ' . $shippingAddress->getLastName(),
                'country'       => $shippingAddress->getCountryId(),
                'countyName'    => $shippingAddress->getRegion(),
                'localityName'  => $shippingAddress->getCity(),
                'addressText'   => implode(', ', $shippingAddress->getStreet()),
                'postalCode'    => $shippingAddress->getPostcode(),
                'phone'         => preg_replace('/[^0-9+]/', '', $shippingAddress->getTelephone()),
                'email'         => $shippingAddress->getEmail(),
            ],
            'payment'                => $data['payment'],
            'extra'                  => [
                'bankRepaymentAmount'  => $bankRepaymentAmount,
                'cashOnDeliveryAmount' => $cashOnDeliveryAmount,
                'openPackage'          => (bool) $data['open_package'],
                'saturdayDelivery'     => (bool) $data['saturday_delivery'],
                'insuranceAmount'      => $this->config->getInsuranceIncluded() ? $data['insurance_amount'] : null,
                'reference1'           => $data['order_reference'],
            ],
            'parameters'             => [
                'async' => false,
            ],
            'externalClientLocation' => $externaleID,
            'externalOrderId'        => $order->getIncrementId(),
            'sourceChannel'          => 'eCommerce',
            'content'                => [
                'envelopeCount' => $data['envelope_count'],
                'parcelsCount'  => $data['parcel_count'],
                'palettesCount' => $data['pallet_count'],
                'totalWeight'   => $data['total_weight'],
                'contents'      => $data['content'],
                'Parcels'       => $parcels,
            ],
        ];
        $shippingAddressPudo = $shippingAddress->getInnoshipPudoId();
        $shippingAddressCourier = $shippingAddress->getInnoshipCourierId();
        if((int)$shippingAddressPudo > 0){
            $serviceID = 4;
            $courierId = 0;
            $connection = $this->_resourceConnection->getConnection();
            $table = $this->_resourceConnection->getTableName('innoship_pudo');

            // SECURITY FIX: Use parameterized query instead of string concatenation
            $select = $connection->select()
                ->from($table, ['serviceId', 'courierId'])
                ->where('pudo_id = ?', (int)$shippingAddressPudo)
                ->limit(1);

            $pudoData = $connection->fetchAll($select);

            foreach($pudoData as $infoAddr){
                $serviceID = $infoAddr['serviceId'];
                $courierId = $infoAddr['courierId'];
            }
            $saveShipmentReturn['serviceId'] = $serviceID;
            $saveShipmentReturn['addressTo']['fixedLocationId'] = (int)$shippingAddressPudo;
            $saveShipmentReturn['CourierId'] = (int)$courierId;
        } else if((int)$shippingAddressCourier > 0){
            $saveShipmentReturn['CourierId'] = (int)$shippingAddressCourier;
        }

//        print_r($saveShipmentReturn);
//        echo json_encode($saveShipmentReturn);
//        die();
        return $saveShipmentReturn;
    }

}
