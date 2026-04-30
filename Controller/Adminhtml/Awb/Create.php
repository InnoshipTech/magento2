<?php

namespace InnoShip\InnoShip\Controller\Adminhtml\Awb;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Convert\Order as OrderConverter;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\Sales\Model\Order\ShipmentRepository;
/**
 * Class Create
 * @package InnoShip\InnoShip\Controller\Adminhtml\Awb
 */
class Create extends Action
{
    /** @var string */
    const ADMIN_RESOURCE = 'InnoShip_InnoShip::awb';

    /** @var OrderRepositoryInterface */
    protected $orderRepository;

    /** @var ShipmentRepositoryInterface */
    protected $shipmentRepositoryInterface;

    /** @var Registry */
    protected $coreRegistry;

    /** @var PageFactory */
    protected $resultPageFactory;
    /**
     * @var OrderConverter
     */
    private $orderConverter;
    /**
     * @var StockByWebsiteIdResolverInterface
     */
    private $stockByWebsiteIdResolver;
    /**
     * @var GetSourcesAssignedToStockOrderedByPriorityInterface
     */
    private $getSourcesAssignedToStockOrderedByPriority;

    /**
     * @var DefaultSourceProviderInterface
     */
    private $defaultSourceProvider;

    /** @var ShipmentRepository */
    protected $shipmentRepository;

    /**
     * Add constructor.
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param ShipmentRepositoryInterface $shipmentRepositoryInterface
     * @param Registry $coreRegistry
     * @param OrderConverter $orderConverter
     * @param StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver
     * @param GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStockOrderedByPriority
     * @param DefaultSourceProviderInterface $defaultSourceProvider
     * @param ShipmentRepository $shipmentRepository
     */
    public function __construct(
        Action\Context $context,
        PageFactory $resultPageFactory,
        OrderRepositoryInterface $orderRepository,
        ShipmentRepositoryInterface $shipmentRepositoryInterface,
        Registry $coreRegistry,
        OrderConverter $orderConverter,
        StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver,
        GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStockOrderedByPriority,
        DefaultSourceProviderInterface $defaultSourceProvider,
        ShipmentRepository $shipmentRepository
    ) {
        $this->orderRepository                              = $orderRepository;
        $this->coreRegistry                                 = $coreRegistry;
        $this->resultPageFactory                            = $resultPageFactory;
        $this->shipmentRepositoryInterface                  = $shipmentRepositoryInterface;
        $this->orderConverter                               = $orderConverter;
        $this->stockByWebsiteIdResolver                     = $stockByWebsiteIdResolver;
        $this->getSourcesAssignedToStockOrderedByPriority   = $getSourcesAssignedToStockOrderedByPriority;
        $this->defaultSourceProvider                        = $defaultSourceProvider;
        $this->shipmentRepository                           = $shipmentRepository;

        parent::__construct($context);
    }

    /**
     * @return Page|ResponseInterface|ResultInterface
     */
    public function execute()
    {
        $shipment = false;
        $shipmentId = $this->getRequest()->getParam('shipment_id');
        if($shipmentId){
            $shipment   = $this->shipmentRepositoryInterface->get($shipmentId);
            $order      = $this->orderRepository->get($shipment->getOrderId());

            $this->coreRegistry->register('current_shipment', $shipment);
            $this->coreRegistry->register('current_order', $order);

            /** @var Page $resultPage */
            $resultPage = $this->resultPageFactory->create();

            $resultPage->addBreadcrumb(__('Generate AWB'), __('Generate AWB'));
            $resultPage->setActiveMenu(self::ADMIN_RESOURCE)
                ->addBreadcrumb(__('InnoShip'), __('InnoShip AWB'))
                ->addBreadcrumb(__('AWB'), __('AWB'));

            $resultPage->getConfig()->getTitle()->prepend(__('Generate InnoShip AWB'));

            return $resultPage;
        } else {
            $orderId        = $this->getRequest()->getParam('order_id');
            $order          = $this->orderRepository->get($orderId);
            $shipmentOrder  = $order->getShipmentsCollection();

            foreach($shipmentOrder as $shipmentItem){
                $shipment = $shipmentItem;
            }
            if ($order->canShip()) {
                if(!$shipment){
                    $shipment = $this->orderConverter->toShipment($order);
                    foreach ($order->getAllItems() AS $orderItem) {
                        if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                            continue;
                        }
                        $qtyShipped = $orderItem->getQtyToShip();
                        $shipmentItem = $this->orderConverter->itemToShipmentItem($orderItem)->setQty($qtyShipped);
                        $shipment->addItem($shipmentItem);
                    }
                    $shipment->register();
                    $shipment->getOrder()->setIsInProcess(true);

                    $websiteId = $order->getStore()->getWebsiteId();
                    $stockId = $this->stockByWebsiteIdResolver->execute((int)$websiteId)->getStockId();
                    $sources = $this->getSourcesAssignedToStockOrderedByPriority->execute((int)$stockId);
                    if (!empty($sources) && count($sources) === 1) {
                        $sourceCode = $sources[0]->getSourceCode();
                    } else {
                        $sourceCode = $this->defaultSourceProvider->getCode();
                    }
                    $shipment->getExtensionAttributes()->setSourceCode($sourceCode);
                    try {
                        $shipment->save();
                        $shipment->getOrder()->save();
                        $this->shipmentRepository->save($shipment);
                    } catch (\Exception $e) {
                        throw new \Magento\Framework\Exception\LocalizedException(
                            __($e->getMessage())
                        );
                    }
                }
            }

            if($shipment){
                $this->coreRegistry->register('current_shipment', $shipment);
                unset($shipment);
            }
            $this->coreRegistry->register('current_order', $order);

            /** @var Page $resultPage */
            $resultPage = $this->resultPageFactory->create();

            $resultPage->addBreadcrumb(__('Generate AWB'), __('Generate AWB'));
            $resultPage->setActiveMenu(self::ADMIN_RESOURCE)
                ->addBreadcrumb(__('InnoShip'), __('InnoShip AWB'))
                ->addBreadcrumb(__('AWB'), __('AWB'));

            $resultPage->getConfig()->getTitle()->prepend(__('Generate InnoShip AWB'));

            return $resultPage;
        }
    }
}
