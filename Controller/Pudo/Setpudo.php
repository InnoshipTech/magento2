<?php

namespace InnoShip\InnoShip\Controller\Pudo;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;

class Setpudo extends Action implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private $_resourceConnection;
    private $resultJsonFactory;
    public $quoteRepository;
    private $maskedQuoteIdToQuoteId;
    private $formKeyValidator;

    public function __construct(
        Context $context,
        ResourceConnection $resourceConnection,
        JsonFactory $resultJsonFactory,
        CartRepositoryInterface $quoteRepository,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        FormKeyValidator $formKeyValidator
    )
    {
        $this->_resourceConnection = $resourceConnection;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->quoteRepository = $quoteRepository;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
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
            'error' => __('Invalid form key. Please refresh the page and try again.')
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

        $quoteId = trim($this->getRequest()->getParam('quote'));
        $pudoId = trim($this->getRequest()->getParam('pudoid'));

        // INPUT VALIDATION
        // 1. Validate quote ID
        if (empty($quoteId)) {
            return $resultJson->setData([
                'error' => __('Quote ID is required.')
            ]);
        }

        // 2. Validate PUDO ID is numeric. A value of 0 (or "0") is accepted as
        //    an explicit "clear locker" sentinel — used by checkout.js when the
        //    customer switches FROM the locker shipping method to a non-locker
        //    method, so the previously stored locker id is wiped from the quote.
        if (!is_numeric($pudoId) || (int)$pudoId < 0) {
            return $resultJson->setData([
                'error' => __('Invalid PUDO ID.')
            ]);
        }
        $pudoId = (int)$pudoId;

        // 4. Validate PUDO ID length (prevent buffer overflow)
        if (strlen((string)$pudoId) > 20) {
            return $resultJson->setData([
                'error' => __('Invalid PUDO ID format.')
            ]);
        }

        try {
            $quoteIdNr = $this->maskedQuoteIdToQuoteId->execute($quoteId);
        } catch (\Exception $exception) {
            $quoteIdNr = $quoteId;
        }

        try {
            $quote = $this->quoteRepository->get($quoteIdNr);
            // Use null (not 0) when clearing — the column is a nullable INT and
            // every consumer (template, observer, plugin) treats null/0 as
            // "no locker" via `(int) > 0`. Storing null is the canonical empty
            // state and avoids stale "0" values lingering on the order.
            $valueToStore = $pudoId > 0 ? $pudoId : null;
            $quote->getShippingAddress()->setInnoshipPudoId($valueToStore);
            // Use the repository (not the deprecated $quote->save()) so the
            // SaveHandler runs and the address change is reliably persisted.
            $this->quoteRepository->save($quote);
            return $resultJson->setData(['pudoId' => $valueToStore]);
        } catch (NoSuchEntityException $exception) {
            // SECURITY: Log error but don't expose details to user
            return $resultJson->setData([
                'error' => __('Unable to save pickup point selection. Please try again.')
            ]);
        }
    }
}
