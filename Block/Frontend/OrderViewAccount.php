<?php
namespace InnoShip\InnoShip\Block\Frontend;

use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\Template;
use Magento\Framework\Registry;


class OrderViewAccount extends Template {

    private $resourceConnection;
    private $_coreRegistry;

    public function __construct(Context $context, Registry $registry, array $data, ResourceConnection $resourceConnection)
    {
        parent::__construct($context, $data);
        $this->resourceConnection = $resourceConnection;
        $this->_coreRegistry = $registry;
    }

    public function getOrder(){
        return $this->_coreRegistry->registry('current_order');
    }

    public function getPudoAddress($pudoId): string
    {
        $connection = $this->resourceConnection->getConnection();
        $pudoAddress = "a";

        // SECURITY FIX: Use parameterized query to prevent SQL injection
        $table = $this->resourceConnection->getTableName('innoship_pudo');

        // Validate pudoId is numeric
        if (!is_numeric($pudoId)) {
            return $pudoAddress;
        }

        $select = $connection->select()
            ->from($table, ['addressText', 'localityName', 'countyName', 'countryCode'])
            ->where('pudo_id = ?', (int)$pudoId)
            ->limit(1);

        $pudo = $connection->fetchRow($select);

        if ($pudo) {
            $pudoAddress = $pudo['addressText'] . ', ' . $pudo['localityName'] . ', ' .
                          $pudo['countyName'] . ', ' . $pudo['countryCode'];
        }

        return $pudoAddress;
    }

    public function getPudoCourierName($pudoId): string
    {
        $connection = $this->resourceConnection->getConnection();
        $pudoCourierId = 0;

        // SECURITY FIX: Use parameterized query to prevent SQL injection
        $table = $this->resourceConnection->getTableName('innoship_pudo');

        // Validate pudoId is numeric
        if (!is_numeric($pudoId)) {
            return '';
        }

        $select = $connection->select()
            ->from($table, ['courierId'])
            ->where('pudo_id = ?', (int)$pudoId)
            ->limit(1);

        $pudoCourierId = $connection->fetchOne($select);

        if ($pudoCourierId) {
            return $this->getCourierName((int)$pudoCourierId);
        }

        return '';
    }

    public function getCourierName($courierId)
    {
        $connection = $this->resourceConnection->getConnection();
        $courierItemName = false;

        // SECURITY FIX: Use parameterized query to prevent SQL injection
        $table = $this->resourceConnection->getTableName('innoship_courierlist');

        // Validate courierId is numeric
        if (!is_numeric($courierId)) {
            return $courierItemName;
        }

        $select = $connection->select()
            ->from($table, ['courierName'])
            ->where('courierId = ?', (int)$courierId)
            ->limit(1);

        $courierItemName = $connection->fetchOne($select);

        return $courierItemName;
    }
}
