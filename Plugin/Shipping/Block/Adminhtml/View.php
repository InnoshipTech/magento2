<?php

namespace InnoShip\InnoShip\Plugin\Shipping\Block\Adminhtml;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Shipping\Block\Adminhtml\View as ShippingView;
use Magento\Framework\UrlInterface;
use InnoShip\InnoShip\Model\Carrier;
use InnoShip\InnoShip\Model\Config;

/**
 * Class View
 * @package InnoShip\InnoShip\Plugin\Shipping\Block\Adminhtml
 */
class View
{
    /** @var UrlInterface */
    protected $url;

    /** @var Carrier */
    protected $innoshipCarrier;

    /** @var OrderRepositoryInterface */
    protected $orderRepositoryInterface;

    /** @var Config */
    protected $config;

    /**
     * View constructor.
     *
     * @param UrlInterface             $url
     * @param Carrier                  $innoshipCarrier
     * @param OrderRepositoryInterface $orderRepositoryInterface
     * @param Config                   $config
     */
    public function __construct(
        UrlInterface $url,
        Carrier $innoshipCarrier,
        OrderRepositoryInterface $orderRepositoryInterface,
        Config $config
    ) {
        $this->url                      = $url;
        $this->innoshipCarrier          = $innoshipCarrier;
        $this->orderRepositoryInterface = $orderRepositoryInterface;
        $this->config                   = $config;
    }

    /**
     * @param ShippingView $view
     */
    public function beforeSetLayout(ShippingView $view)
    {
        $order       = $this->getOrder($view->getShipment()->getOrderId());
        $hasTracks   = false;
        $isInnoShipOrder = false;
        $allowedMethods     = ['innoship_1','innoshipcargusgo_innoshipcargusgo_1'];

        if (in_array($order->getShippingMethod(),$allowedMethods, true)) {
            $isInnoShipOrder = true;
        }

        if (false === $isInnoShipOrder) {
            return;
        }

        $shipmentId = $view->getShipment()->getEntityId();

        foreach ($view->getShipment()->getTracks() as $track) {
            if ($track->getCarrierCode() !== $this->innoshipCarrier->getCarrierCode()) {
                continue;
            }

            $hasTracks = true;
        }

        // Generate AWB
        $view->addButton(
            'innoship_create_awb',
            [
                'label'   => __('Generate InnoShip AWB'),
                'class'   => 'button-innoship-generate-awb',
                'onclick' => 'setLocation(\'' . $this->url->getUrl('innoship/awb/create', ['shipment_id' => $shipmentId]) . '\')',
            ]
        );

        // Print AWB button
        if (true === $hasTracks) {
            $view->addButton(
                'innoship_print_awb',
                [
                    'label'   => __('Print InnoShip AWBs'),
                    'class'   => 'button-innoship-print-awb',
                    'onclick' => 'window.open( \'' . $this->url->getUrl(
                            'innoship/awb/label',
                            ['shipment_id' => $shipmentId]
                        ) . '\', \'Download AWBs\', \'width=600,height=300\')',
                    'target'  => '_blank',
                ]
            );
        }
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
}
