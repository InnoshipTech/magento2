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
use InnoShip\InnoShip\Model\PudoAddress;
use InnoShip\InnoShip\Model\SessionQuoteId;

class Setpudo extends Action implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private $_resourceConnection;
    private $resultJsonFactory;
    public $quoteRepository;
    private $sessionQuoteId;
    private $formKeyValidator;
    private $pudoAddress;

    public function __construct(
        Context $context,
        ResourceConnection $resourceConnection,
        JsonFactory $resultJsonFactory,
        CartRepositoryInterface $quoteRepository,
        SessionQuoteId $sessionQuoteId,
        FormKeyValidator $formKeyValidator,
        PudoAddress $pudoAddress
    )
    {
        $this->_resourceConnection = $resourceConnection;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->quoteRepository = $quoteRepository;
        $this->sessionQuoteId = $sessionQuoteId;
        $this->formKeyValidator = $formKeyValidator;
        $this->pudoAddress = $pudoAddress;
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

        // Only the visitor's own quote (checkout session).
        $quoteIdNr = $this->sessionQuoteId->resolve($quoteId);
        if ($quoteIdNr === null) {
            return $resultJson->setData([
                'error' => __('Unable to save pickup point selection. Please try again.')
            ]);
        }

        try {
            /** @var \Magento\Quote\Model\Quote $quote */
            $quote = $this->quoteRepository->get($quoteIdNr);
            // Use null (not 0) when clearing — the column is a nullable INT and
            // every consumer (template, observer, plugin) treats null/0 as
            // "no locker" via `(int) > 0`. Storing null is the canonical empty
            // state and avoids stale "0" values lingering on the order.
            $valueToStore = $pudoId > 0 ? $pudoId : null;
            $shippingAddress = $quote->getShippingAddress();
            $shippingAddress->setInnoshipPudoId($valueToStore);

            $applied = false;
            if ($pudoId > 0) {
                // Persist the locker ADDRESS, not only its id. Magento re-imports the customer
                // address book over any quote shipping address that still carries a
                // customer_address_id — see
                // vendor/magento/module-quote/Model/ShippingAddressManagement.php::assign(),
                // reached on every quote save through QuoteRepository\SaveHandler::save(). For a
                // customer with at least one saved address that silently replaced the locker with
                // their own address, while a guest (customer_address_id NULL) was unaffected.
                // Writing the locker fields here and detaching the address from the address book
                // removes that dependency.
                $applied = $this->pudoAddress->applyToAddress($shippingAddress, $pudoId);
            }

            if ($shippingAddress->getId()) {
                // Write only the columns this module owns. Checkout fires setpudo on every
                // shipping-method change, next to shipping-information; a full address/quote save
                // here rewrites every column from the copy loaded above, and when it commits last
                // it blanks the address shipping-information just saved (place-order then fails
                // with "firstname/street/city/telephone is required"). A column-level UPDATE with
                // bound values is used on purpose instead of a repository save for that reason.
                $columns = $applied
                    ? $this->pudoAddress->getOwnedColumns($shippingAddress)
                    : [PudoAddress::PUDO_ID_FIELD => $valueToStore];
                $connection = $this->_resourceConnection->getConnection();
                $connection->update(
                    $this->_resourceConnection->getTableName('quote_address'),
                    $columns,
                    ['address_id = ?' => (int) $shippingAddress->getId()]
                );

                return $resultJson->setData(['pudoId' => $valueToStore]);
            }

            // No stored address row yet: the saves below create it.
            // SaveHandler only reaches the shipping address through the shipping assignment, which
            // LoadHandler populates for a non-virtual quote with items. Saving the address here as
            // well guarantees the write on every path; core does the same in
            // Magento\Quote\Model\ShippingAddressManagement::assign().
            $shippingAddress->save();

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
