<?php

namespace InnoShip\InnoShip\Controller\Pudo;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use InnoShip\InnoShip\Model\SessionQuoteId;

class Getpudo extends Action
{
    private $_resourceConnection;
    private $resultJsonFactory;
    public $quoteRepository;
    private $sessionQuoteId;

    public function __construct(
        Context $context,
        ResourceConnection $resourceConnection,
        JsonFactory $resultJsonFactory,
        CartRepositoryInterface $quoteRepository,
        SessionQuoteId $sessionQuoteId
    )
    {
        $this->_resourceConnection = $resourceConnection;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->quoteRepository = $quoteRepository;
        $this->sessionQuoteId = $sessionQuoteId;
        parent::__construct($context);
    }

    public function execute()
    {
        $quoteId = $this->getRequest()->getParam('quote');
        $resultJson = $this->resultJsonFactory->create();

        // INPUT VALIDATION
        // 1. Validate quote ID exists
        if (empty($quoteId)) {
            return $resultJson->setData([
                'error' => __('Quote ID is required.')
            ]);
        }

        // 2. Validate quote ID length
        if (strlen($quoteId) > 255) {
            return $resultJson->setData([
                'error' => __('Invalid quote ID format.')
            ]);
        }

        // Only the visitor's own quote (checkout session).
        $quoteIdNr = $this->sessionQuoteId->resolve($quoteId);
        if ($quoteIdNr === null) {
            return $resultJson->setData([
                'error' => __('Unable to load pickup points. Please try again.')
            ]);
        }

        $allPudo = [];

        try {
            $quote = $this->quoteRepository->get($quoteIdNr);
            $pudoId = $quote->getShippingAddress()->getInnoshipPudoId();
            $connection = $this->_resourceConnection->getConnection();

            $table = $this->_resourceConnection->getTableName('innoship_pudo');

            $result = $connection->select()->from($table)->where('pudo_id = :pudoSelectedParameter');
            $bind = ['pudoSelectedParameter' => $pudoId];
            $allRows = $connection->fetchAll($result, $bind);

            foreach($allRows as $pudo){
                $allPudo[] = $pudo;
            }
        } catch (NoSuchEntityException $exception) {
            // SECURITY: Log error but don't expose details to user
            return $resultJson->setData([
                'error' => __('Unable to load pickup points. Please try again.')
            ]);
        }

        return $resultJson->setData(['json_data' => $allPudo]);
    }
}
