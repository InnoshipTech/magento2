<?php

namespace InnoShip\InnoShip\Controller\Adminhtml\Awb;

use Magento\Backend\App\Action;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Filesystem;
use Psr\Log\LoggerInterface;

/**
 * Class Download
 * @package InnoShip\InnoShip\Controller\Adminhtml\Awb
 */
class Download extends Action
{
    /** @var string */
    const ADMIN_RESOURCE = 'InnoShip_InnoShip::awb';

    /** @var FileFactory */
    protected $fileFactory;

    /** @var Filesystem */
    protected $filesystem;

    /** @var LoggerInterface */
    protected $logger;

    /**
     * Add constructor.
     *
     * @param Action\Context $context
     * @param Filesystem     $filesystem
     * @param FileFactory    $fileFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Action\Context $context,
        Filesystem $filesystem,
        FileFactory $fileFactory,
        LoggerInterface $logger
    ) {
        $this->fileFactory = $fileFactory;
        $this->filesystem  = $filesystem;
        $this->logger      = $logger;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface
     * @throws \Exception
     */
    public function execute()
    {
        $fileName = $this->getRequest()->getParam('file');
        $filePath = $this->getRequest()->getParam('path');

        // SECURITY: Validate inputs
        if (empty($fileName) || empty($filePath)) {
            $this->messageManager->addErrorMessage(__('Invalid file parameters.'));
            return $this->_redirect('adminhtml/dashboard');
        }

        // SECURITY: Remove any directory traversal attempts from filename
        $fileName = basename($fileName);

        // SECURITY: Validate file extension (only allow PDF files for AWB labels)
        $allowedExtensions = ['pdf'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedExtensions)) {
            $this->messageManager->addErrorMessage(__('Invalid file type. Only PDF files are allowed.'));
            return $this->_redirect('adminhtml/dashboard');
        }

        // SECURITY: Get the base media directory
        $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $mediaPath = $mediaDirectory->getAbsolutePath();

        // SECURITY: Normalize the requested path (remove ../, ./, etc)
        $filePath = trim($filePath, '/');
        $filePath = preg_replace('#/+#', '/', $filePath); // Remove double slashes

        // SECURITY: Build the full file path
        $fullFilePath = $mediaPath . $filePath . $fileName;

        // SECURITY: Resolve the real path and validate it's within media directory
        $realFilePath = realpath($fullFilePath);
        $realMediaPath = realpath($mediaPath);

        if ($realFilePath === false) {
            $this->messageManager->addErrorMessage(__('File not found.'));
            return $this->_redirect('adminhtml/dashboard');
        }

        // SECURITY: Ensure the resolved path is within the media directory
        if (strpos($realFilePath, $realMediaPath) !== 0) {
            $this->messageManager->addErrorMessage(__('Access denied: Invalid file path.'));
            $this->logger->critical('Path traversal attempt detected', [
                'requested_file' => $fileName,
                'requested_path' => $filePath,
                'resolved_path' => $realFilePath
            ]);
            return $this->_redirect('adminhtml/dashboard');
        }

        // SECURITY: Verify file exists and is readable
        if (!file_exists($realFilePath) || !is_readable($realFilePath)) {
            $this->messageManager->addErrorMessage(__('File not found or not accessible.'));
            return $this->_redirect('adminhtml/dashboard');
        }

        return $this->fileFactory->create(
            $fileName,
            [
                'type'  => "filename",
                'value' => $realFilePath,
                'rm'    => true,
            ],
            DirectoryList::MEDIA
        );
    }
}
