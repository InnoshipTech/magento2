<?php

namespace InnoShip\InnoShip\Block\Adminhtml\Awb;

use Magento\Backend\Block\Widget\Form\Container;
use Magento\Framework\Registry;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * Class Create
 * @package InnoShip\InnoShip\Block\Adminhtml
 */
class Create extends Container
{
    /** @var Registry */
    protected $registry;
    private $messageManager;
    private $resourceConnection;
    private $orderRepository;
    private $shipmentRepositoryInterface;

    /**
     * Create constructor.
     *
     * @param Context  $context
     * @param Registry $registry
     * @param array    $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ManagerInterface $messageManager,
        ShipmentRepositoryInterface $shipmentRepositoryInterface,
        ResourceConnection $resourceConnection,
        OrderRepositoryInterface $orderRepository,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->messageManager = $messageManager;
        $this->shipmentRepositoryInterface = $shipmentRepositoryInterface;
        $this->resourceConnection = $resourceConnection;
        $this->orderRepository = $orderRepository;

        parent::__construct($context, $data);
    }

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_objectId   = 'innoship_awb';
        $this->_blockGroup = 'InnoShip_InnoShip';
        $this->_controller = 'adminhtml_awb';

        parent::_construct();

        $orderViewUrl = $this->_urlBuilder->getUrl(
            'adminhtml/order_shipment/view',
            [
                'shipment_id' => $this->registry->registry('current_shipment')->getId(),
            ]
        );

        $shipment   = $this->shipmentRepositoryInterface->get($this->registry->registry('current_shipment')->getId());
        $order      = $this->orderRepository->get($shipment->getOrderId());
        $error      = false;

        $checkPudoId = $order->getShippingAddress()->getInnoshipPudoId();
        if($checkPudoId > 0){
            $checkCourierID = $this->checkPudoValidation($checkPudoId);
            if($checkCourierID === 0){
                $this->messageManager->addErrorMessage(__('Nu se poate face AWB pentru aceasta comanda deoarece nu exista un curier valid pentru Locker!'));
                $error = true;
            }
        }

        if($error === false){
            $this->buttonList->update('save', 'label', __('Generate the AWB'));
        } else {
            $this->buttonList->remove('save');
            $this->buttonList->remove('reset');
        }

        $this->buttonList->update('back', 'onclick', "setLocation('" . $orderViewUrl . "');");

    }

    /**
     * Check PUDO validation and get courier ID
     *
     * @param int|string $pudoId
     * @return int
     */
    public function checkPudoValidation($pudoId): int
    {
        $pudoCourierId = 0;

        // SECURITY: Validate PUDO ID to prevent SQL injection
        if (!is_numeric($pudoId) || $pudoId <= 0) {
            return 0;
        }

        $pudoId = (int)$pudoId;

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('innoship_pudo');

        // SECURITY: Use parameterized query to prevent SQL injection
        $select = $connection->select()
            ->from($table, ['courierId'])
            ->where('pudo_id = ?', $pudoId)
            ->limit(1);

        $result = $connection->fetchOne($select);

        if ($result) {
            $pudoCourierId = (int)$result;
        }

        return $pudoCourierId;
    }
}
