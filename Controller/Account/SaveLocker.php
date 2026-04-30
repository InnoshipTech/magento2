<?php

namespace InnoShip\InnoShip\Controller\Account;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class SaveLocker extends Action implements HttpPostActionInterface, CsrfAwareActionInterface
{
    protected $customerSession;
    protected $jsonFactory;
    protected $customerRepository;
    protected $formKeyValidator;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        JsonFactory $jsonFactory,
        FormKeyValidator $formKeyValidator
    ) {
        $this->customerSession = $customerSession;
        $this->jsonFactory = $jsonFactory;
        $this->customerRepository = $customerRepository;
        $this->formKeyValidator = $formKeyValidator;

        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        $result = $this->jsonFactory->create();
        $result->setData([
            'success' => false,
            'message' => __('Invalid form key. Please refresh the page and try again.')
        ]);

        return new InvalidRequestException(
            $result,
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
        $result = $this->jsonFactory->create();

        // Check if customer is logged in
        if (!$this->customerSession->isLoggedIn()) {
            return $result->setData(['success' => false, 'message' => 'Customer not logged in']);
        }

        // Get the logged-in customer ID from session (secure)
        $loggedInCustomerId = (int)$this->customerSession->getCustomerId();

        // Get customer ID from request
        $requestCustomerId = (int)$this->getRequest()->getParam('favorite_locker_customerId');
        $storeId = (int)$this->getRequest()->getParam('favorite_locker_storeId');

        // SECURITY FIX: Validate that the customer can only modify their own data
        if ($requestCustomerId !== $loggedInCustomerId) {
            return $result->setData(['success' => false, 'message' => 'Unauthorized access']);
        }

        if (!$loggedInCustomerId || !$storeId) {
            return $result->setData(['success' => false, 'message' => 'Customer ID and Store ID are required']);
        }

        // INPUT VALIDATION
        $lockerId = $this->getRequest()->getParam('favorite_locker');
        $lockerName = $this->getRequest()->getParam('favorite_locker_name');

        // 1. Validate locker ID
        if (empty($lockerId)) {
            return $result->setData(['success' => false, 'message' => 'Locker ID is required']);
        }

        if (!is_numeric($lockerId) || $lockerId <= 0) {
            return $result->setData(['success' => false, 'message' => 'Invalid locker ID']);
        }
        $lockerId = (int)$lockerId;

        // 2. Validate locker name
        if (empty($lockerName)) {
            return $result->setData(['success' => false, 'message' => 'Locker name is required']);
        }

        // 3. Validate locker name length
        if (strlen($lockerName) > 255) {
            return $result->setData(['success' => false, 'message' => 'Locker name is too long']);
        }

        // 4. Sanitize locker name (remove any HTML/JavaScript to prevent XSS)
        $lockerName = strip_tags($lockerName);
        $lockerName = htmlspecialchars($lockerName, ENT_QUOTES, 'UTF-8');

        try {
            $customer = $this->customerRepository->getById($loggedInCustomerId);
            $customer->setStoreId($storeId);

            $customer->setCustomAttribute('favorite_locker', $lockerId);
            $customer->setCustomAttribute('favorite_locker_name', $lockerName);

            $this->customerRepository->save($customer);

            return $result->setData(['success' => true]);
        } catch (NoSuchEntityException $e) {
            return $result->setData(['success' => false, 'message' => 'Customer not found']);
        } catch (\Exception $e) {
            return $result->setData(['success' => false, 'message' => 'Unable to save preference']);
        }
    }
}
