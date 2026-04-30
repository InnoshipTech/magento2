<?php

namespace InnoShip\InnoShip\Controller\City;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use InnoShip\InnoShip\Model\Config;

class Getcity extends Action
{
    private $_resourceConnection;
    private $resultJsonFactory;
    public $quoteRepository;
    private $maskedQuoteIdToQuoteId;
    protected $config;

    public function __construct(
        Context $context,
        ResourceConnection $resourceConnection,
        JsonFactory $resultJsonFactory,
        CartRepositoryInterface $quoteRepository,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        Config $config
    )
    {
        $this->_resourceConnection = $resourceConnection;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->quoteRepository = $quoteRepository;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->config = $config;
        parent::__construct($context);
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        $judet = $this->getRequest()->getParam('c');

        // INPUT VALIDATION
        // 1. Validate parameter exists
        if ($judet === null || $judet === '') {
            return $resultJson->setData(['json_data' => []]);
        }

        // 2. Validate is numeric
        if (!is_numeric($judet)) {
            return $resultJson->setData(['json_data' => []]);
        }

        // 3. Cast to integer and validate range
        $judet = (int)$judet;
        if ($judet <= 0 || $judet > 99999) {
            return $resultJson->setData(['json_data' => []]);
        }

        $responseJsonData = [];

        $connection = $this->_resourceConnection->getConnection();
        $table = $this->_resourceConnection->getTableName('innoship_citys');
        $result = $connection->select()->from($table,array('localitate','codPostal'))->where('regioId = :judet')->where('localitate <> ?','Bucuresti')->order('localitate ASC');
        $bind = ['judet' => $judet];
        $allRows = $connection->fetchAll($result, $bind);

        foreach($allRows as $localitati){
            $responseJsonData[] = $localitati;
        }

        return $resultJson->setData(['json_data' => $responseJsonData]);
    }
}
