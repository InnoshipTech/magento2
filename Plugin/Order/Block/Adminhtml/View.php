<?php

namespace InnoShip\InnoShip\Plugin\Order\Block\Adminhtml;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Block\Adminhtml\Order\View as OrderView;
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
     * @param OrderView $view
     */

    public function beforeSetLayout(OrderView $view)
    {
        $order       = $view->getOrder();

        // Generate AWB
        $view->addButton(
            'innoship_create_awb',
            [
                'label'   => __('Generate Shipment & InnoShip AWB'),
                'class'   => 'button-innoship-generate-awb',
                'onclick' => 'setLocation(\'' . $this->url->getUrl('innoship/awb/create', ['order_id' => $order->getId()]) . '\')',
                'resource' => 'InnoShip_InnoShip::manager'
            ]
        );
    }

}
