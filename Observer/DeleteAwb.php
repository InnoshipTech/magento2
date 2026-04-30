<?php

namespace InnoShip\InnoShip\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\ShipmentInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order\Shipment\Comment;
use Magento\Sales\Model\Order\Shipment\Track;
use Magento\Sales\Model\Order\ShipmentRepository;
use InnoShip\InnoShip\Model\Api\Order;
use InnoShip\InnoShip\Model\Carrier;
use Magento\Sales\Model\Order\Shipment\CommentFactory;
use Magento\Framework\DataObjectFactory;

/**
 * Class DeleteAwb
 * @package InnoShip\InnoShip\Observer
 */
class DeleteAwb implements ObserverInterface
{
    /** @var Carrier */
    protected $innoshipCarrier;

    /** @var CommentFactory\ */
    protected $commentFactory;

    /** @var ShipmentRepository */
    protected $shipmentRepository;

    /** @var Order */
    protected $orderModel;

    /** @var ShipmentRepositoryInterface */
    protected $shipmentRepositoryInterface;

    /** @var Json */
    protected $jsonSerializer;

    /** @var DataObjectFactory */
    protected $dataObjectFactory;

    /**
     * DeleteAwb constructor.
     *
     * @param Carrier                     $innoshipCarrier
     * @param ShipmentRepository          $shipmentRepository
     * @param CommentFactory              $commentFactory
     * @param Order                       $orderModel
     * @param ShipmentRepositoryInterface $shipmentRepositoryInterface
     * @param Json                        $jsonSerializer
     * @param DataObjectFactory           $dataObjectFactory
     */
    public function __construct(
        Carrier $innoshipCarrier,
        ShipmentRepository $shipmentRepository,
        CommentFactory $commentFactory,
        Order $orderModel,
        ShipmentRepositoryInterface $shipmentRepositoryInterface,
        Json $jsonSerializer,
        DataObjectFactory $dataObjectFactory
    ) {
        $this->innoshipCarrier             = $innoshipCarrier;
        $this->shipmentRepository          = $shipmentRepository;
        $this->commentFactory              = $commentFactory;
        $this->orderModel                  = $orderModel;
        $this->shipmentRepositoryInterface = $shipmentRepositoryInterface;
        $this->jsonSerializer              = $jsonSerializer;
        $this->dataObjectFactory           = $dataObjectFactory;
    }

    /**
     * @param Observer $observer
     *
     * @return void|DeleteAwb
     * @throws \Exception
     */
    public function execute(Observer $observer)
    {
        /** @var Track $track */
        $track = $observer->getEvent()->getTrack();

        if ($track->getCarrierCode() !== $this->innoshipCarrier->getCarrierCode()) {
            return;
        }

        /** @var \Magento\Framework\DataObject $innoShipData */
        $innoShipData = $this->dataObjectFactory->create(
            [
                'data' => $this->jsonSerializer->unserialize($track->getInnoshipData()),
            ]
        );

        $response = $this->orderModel->delete($innoShipData->getDataByKey('courier'), $track->getTrackNumber());
        $shipment = $this->getShipment($track->getParentId());

        if (false === $response->getSuccess()) {
            $this->saveComment($shipment, 'Error on deleting AWB ' . $track->getTrackNumber() . '! Error: ' . $response->getStatusMessage());

            throw new \Exception($response->getStatusMessage());
        }

        $this->saveComment($shipment, 'The AWB ' . $track->getTrackNumber() . ' has ben deleted successfully!');

        return $this;
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
     * @param string $shipmentId
     *
     * @return ShipmentInterface
     */
    protected function getShipment(string $shipmentId): ShipmentInterface
    {
        return $this->shipmentRepositoryInterface->get($shipmentId);
    }
}
