<?php

namespace InnoShip\InnoShip\Block\Adminhtml\Form\Renderer\Fieldset;

use Magento\Backend\Block\Widget\Form\Renderer\Fieldset\Element;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use InnoShip\InnoShip\Model\Config;

/**
 * Class Contents
 * @package InnoShip\InnoShip\Block\Adminhtml\Form\Renderer\Fieldset
 */
class Contents extends Element
{
    protected $_template = 'form/renderer/fieldset/contents.phtml';
    protected $orderInterface;
    protected $productRepository;
    protected $config;
    protected $shipmentRepository;


    public function __construct(\Magento\Backend\Block\Template\Context $context,
                                \Magento\Sales\Model\OrderRepository $orderInterface,
                                \Magento\Catalog\Model\Product $productRepository,
                                ShipmentRepositoryInterface $shipmentRepository,
                                Config $config)
    {
        parent::__construct($context);
        $this->productRepository = $productRepository;
        $this->orderInterface = $orderInterface;
        $this->shipmentRepository = $shipmentRepository;
        $this->config = $config;
    }

    public function getPackagesPred()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        if($orderId){
            $order = $this->orderInterface->get((int)$orderId);
        } else {
            $shipmentId = $this->getRequest()->getParam('shipment_id');
            $shipment   = $this->shipmentRepository->get($shipmentId);
            $order      = $this->orderInterface->get($shipment->getOrderId());
        }
        $items = $order->getAllItems();

        $lungimeObj = 0;
        $latimeObj = 0;
        $inaltimeObj = 0;
        $weightObj = 0;

        $attrLungime = $this->config->getLungime();
        $attrLatime = $this->config->getLatime();
        $attrInaltime = $this->config->getInaltime();

        if($attrLungime && $attrInaltime && $attrLatime){
            $skuList = array();
            foreach($items as $item){
                $skuList[$item->getSku()] = $item->getQtyOrdered();
            }

            foreach($skuList as $sku => $qty){
                $product = $this->productRepository->loadByAttribute('sku',$sku);

                $lungimeObj+= (float)$product->getData((string)$attrLungime) * $qty;
                $latimeObj+= (float)$product->getData((string)$attrLatime) * $qty;
                $inaltimeObj+= (float)$product->getData((string)$attrInaltime) * $qty;
                $weightObj+= (float)$product->getData('weight') * $qty;
            }

            if($lungimeObj <= 0){$lungimeObj = 1;}
            if($latimeObj <= 0){$latimeObj = 1;}
            if($inaltimeObj <= 0){$inaltimeObj = 1;}

            return [
                0 => [
                    'width' => $lungimeObj,
                    'height' => $inaltimeObj,
                    'length' => $latimeObj,
                    'weight' => round($weightObj,1),
                    'type' => '1',
                    'reference' => $order->getIncrementId()
                ]
            ];
        } else {
            $skuList = array();
            foreach($items as $item){
                $skuList[$item->getSku()] = $item->getQtyOrdered();
            }

            foreach($skuList as $sku => $qty){
                $product = $this->productRepository->loadByAttribute('sku',$sku);
                $weightObj+= (float)$product->getData('weight') * $qty;
            }

            return [
                0 => [
                    'width' => '10',
                    'height' => '10',
                    'length' => '10',
                    'weight' => round($weightObj,1),
                    'type' => '1',
                    'reference' => $order->getIncrementId()
                ]
            ];
        }

        return [];
    }
}
