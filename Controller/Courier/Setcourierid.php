<?php

namespace InnoShip\InnoShip\Controller\Courier;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Psr\Log\LoggerInterface;

class Setcourierid extends Action implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private $_resourceConnection;
    private $resultJsonFactory;
    public $quoteRepository;
    private $maskedQuoteIdToQuoteId;
    private $logger;
    private $formKeyValidator;

    public function __construct(
        Context $context,
        ResourceConnection $resourceConnection,
        JsonFactory $resultJsonFactory,
        CartRepositoryInterface $quoteRepository,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        LoggerInterface $logger,
        FormKeyValidator $formKeyValidator
    ) {
        $this->_resourceConnection = $resourceConnection;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->quoteRepository = $quoteRepository;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->logger = $logger;
        $this->formKeyValidator = $formKeyValidator;
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        $resultJson = $this->resultJsonFactory->create();
        $resultJson->setData([
            'error' => __('Invalid form key. Please refresh the page and try again.'),
            'success' => false
        ]);

        return new InvalidRequestException(
            $resultJson,
            [__('Invalid form key. Please refresh the page and try again.')]
        );
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        // Validate form key for POST requests
        if ($request->isPost()) {
            return $this->formKeyValidator->validate($request);
        }
        return true;
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        $quoteId = $this->getRequest()->getParam('quote');
        $courierId = $this->getRequest()->getParam('cid');
        $price = $this->getRequest()->getParam('price');

        // INPUT VALIDATION
        // 1. Validate quote ID
        if (empty($quoteId)) {
            return $resultJson->setData([
                'error' => __('Quote ID is required.'),
                'success' => false
            ]);
        }

        // 2. Validate courier ID
        if (empty($courierId) || !is_numeric($courierId) || $courierId <= 0) {
            return $resultJson->setData([
                'error' => __('Invalid courier ID.'),
                'success' => false
            ]);
        }
        $courierId = (int)$courierId;

        // 3. Validate price if provided
        if ($price !== null && $price !== '') {
            if (!is_numeric($price) || $price < 0) {
                return $resultJson->setData([
                    'error' => __('Invalid price value.'),
                    'success' => false
                ]);
            }
            // Validate price range (max 99999.99)
            if ($price > 99999.99) {
                return $resultJson->setData([
                    'error' => __('Price exceeds maximum allowed value.'),
                    'success' => false
                ]);
            }
        }

        try {
            $quoteIdNr = $this->maskedQuoteIdToQuoteId->execute($quoteId);
        } catch (\Exception $exception) {
            $quoteIdNr = $quoteId;
        }

        try {
            $quote = $this->quoteRepository->get($quoteIdNr);
            $shippingAddress = $quote->getShippingAddress();

            // Set the courier ID
            $shippingAddress->setInnoshipCourierId($courierId);

            // Get courier name from local storage (passed from frontend)
            $courierName = '';

            // Update shipping amount if price is provided
            if ($price !== null && $price !== '') {
                $priceFloat = (float) $price;

                $shippingAddress->setShippingMethod('innoship_1');
                $shippingAddress->setCollectShippingRates(true);
                $shippingAddress->collectShippingRates();

                $rate = $shippingAddress->getShippingRateByCode('innoship_1');
                if ($rate) {
                    $rate->setPrice($priceFloat);
                    $rate->setCost($priceFloat);
                }

                $shippingAddress->setShippingAmount($priceFloat);
                $shippingAddress->setBaseShippingAmount($priceFloat);
                $shippingAddress->setInnoshipShippingPrice($priceFloat);

                // Get shipping method title from config
                $connection = $this->_resourceConnection->getConnection();
                $courierTable = $this->_resourceConnection->getTableName('innoship_courierlist');

                // Use query builder with parameterized query for security
                $select = $connection->select()
                    ->from($courierTable, ['courierName'])
                    ->where('courierId = ?', (int)$courierId)
                    ->limit(1);

                $courierName = $connection->fetchOne($select);

                if ($courierName) {
                    $shippingAddress->setShippingDescription($courierName);
                }

                // Collect totals to recalculate grand total
//                $quote->collectTotals();
            }

            $this->quoteRepository->save($quote);

            return $resultJson->setData([
                'shippinginfo' => $quote->getId() . " - " . $quote->getStoreId(),
                'courierId' => $courierId,
                'courierName' => $courierName,
                'price' => $price,
                'success' => true
            ]);
        } catch (\Exception $exception) {
            // SECURITY: Log detailed error but return generic message to user
            $this->logger->error('Failed to set courier ID', [
                'exception' => $exception->getMessage(),
                'quote_id' => $quoteId ?? 'unknown',
                'courier_id' => $courierId ?? 'unknown'
            ]);

            return $resultJson->setData([
                'error' => __('Unable to update shipping method. Please try again.'),
                'success' => false
            ]);
        }
    }
}
