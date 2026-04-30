<?php

namespace InnoShip\InnoShip\Controller\Adminhtml\Awb;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use InnoShip\InnoShip\Logger\Logger;
use InnoShip\InnoShip\Model\Api\Label as AwbLabel;
use InnoShip\InnoShip\Model\Carrier;
use Magento\Framework\DataObjectFactory;
use InnoShip\InnoShip\Model\Api\Rest\Service;
use Magento\Store\Model\StoreManagerInterface;
use InnoShip\InnoShip\Model\Config;

/**
 * Class Label
 * @package InnoShip\InnoShip\Controller\Adminhtml\Awb
 */
class Label extends Action
{
    /** @var string */
    const ADMIN_RESOURCE = 'InnoShip_InnoShip::awb';

    /** @var OrderRepositoryInterface */
    protected $orderRepository;

    /** @var ShipmentRepositoryInterface */
    protected $shipmentRepository;

    /** @var Registry */
    protected $coreRegistry;

    /** @var PageFactory */
    protected $resultPageFactory;

    /** @var AwbLabel */
    protected $label;

    /** @var FileFactory */
    protected $fileFactory;

    /** @var Filesystem */
    protected $filesystem;

    /** @var Carrier */
    protected $innoshipCarrier;

    /** @var Json */
    protected $jsonSerializer;

    /** @var DataObjectFactory */
    protected $dataObjectFactory;

    /** @var Logger */
    protected $logger;

    protected $storeManager;
    protected $fileManager;
    protected $dirManager;
    private $service;
    private $config;

    /**
     * Add constructor.
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param ShipmentRepositoryInterface $shipmentRepository
     * @param Registry $coreRegistry
     * @param AwbLabel $label
     * @param Filesystem $filesystem
     * @param FileFactory $fileFactory
     * @param Carrier $innoshipCarrier
     * @param Json $jsonSerializer
     * @param DataObjectFactory $dataObjectFactory
     * @param Logger $logger
     * @param StoreManagerInterface $storeManager
     * @param File $fileManagerInterface
     * @param Filesystem\DirectoryList $dirManagerInterface
     * @param Service $service
     * @param Config $config
     */
    public function __construct(
        Action\Context $context,
        PageFactory $resultPageFactory,
        OrderRepositoryInterface $orderRepository,
        ShipmentRepositoryInterface $shipmentRepository,
        Registry $coreRegistry,
        AwbLabel $label,
        Filesystem $filesystem,
        FileFactory $fileFactory,
        Carrier $innoshipCarrier,
        Json $jsonSerializer,
        DataObjectFactory $dataObjectFactory,
        Logger $logger,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Filesystem\Io\File $fileManagerInterface,
        \Magento\Framework\Filesystem\DirectoryList $dirManagerInterface,
        Service $service,
        Config $config
    ) {
        $this->orderRepository    = $orderRepository;
        $this->coreRegistry       = $coreRegistry;
        $this->resultPageFactory  = $resultPageFactory;
        $this->shipmentRepository = $shipmentRepository;
        $this->label              = $label;
        $this->fileFactory        = $fileFactory;
        $this->filesystem         = $filesystem;
        $this->innoshipCarrier    = $innoshipCarrier;
        $this->jsonSerializer     = $jsonSerializer;
        $this->dataObjectFactory  = $dataObjectFactory;
        $this->logger             = $logger;
        $this->storeManager       = $storeManager;
        $this->fileManager        = $fileManagerInterface;
        $this->dirManager         = $dirManagerInterface;
        $this->service            = $service;
        $this->config             = $config;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface|void
     */
    public function execute()
    {
        $checkPath = $this->dirManager->getPath('media').'/innoship';
        if ( ! file_exists($checkPath)) {
            $this->fileManager->mkdir($checkPath);
        }

        $shipmentId = $this->getRequest()->getParam('shipment_id');
        $shipment   = $this->shipmentRepository->get($shipmentId);
        $hasTracks  = false;
        $html = '';

        foreach ($shipment->getTracks() as $track) {
            try {
                /** @var \Magento\Framework\DataObject $innoShipData */
                $innoShipData = $this->dataObjectFactory->create(
                    [
                        'data' => $this->jsonSerializer->unserialize($track->getInnoshipData()),
                    ]
                );

                $courierId = $innoShipData->getDataByKey('courier');
                $awb = $track->getTrackNumber();

                $pdfContent = $this->service->makeRequest("/api/Label/by-courier/$courierId/awb/$awb?type=pdf&format=".(string)$this->config->getLabelformat()."&UseFile=false", [], 'get');

                // Set response headers for PDF download
                $this->getResponse()->setHeader('Content-Type', 'application/pdf');
                $this->getResponse()->setHeader('Content-Disposition', 'attachment; filename="' . $track->getTrackNumber() . '.pdf"');
                $this->getResponse()->setBody(base64_decode($pdfContent['contents']));
                return;
            } catch (\Throwable $exception) {
                // SECURITY: Log detailed error but show generic message to admin
                $this->logger->error('Failed to generate AWB label', [
                    'exception' => $exception->getMessage(),
                    'track_number' => $track->getTrackNumber()
                ]);
                $html.= __('Error generating label. Please check logs for details.');
                continue;
            }

            $hasTracks = true;
        }

        $html .= $hasTracks ? __('Downloading...') : __('No AWBs found!');
        $html .= ' <button type="button" onclick="window.close()">' . __('Close window') . '</button>';

        $this->getResponse()->setBody($html);
    }
}
