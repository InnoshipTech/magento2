<?php

namespace InnoShip\InnoShip\Plugin\Order\Email\Container\Template;

use Magento\Framework\App\ResourceConnection;

class EmailCode
{
    private $_resourceConnection;

    public function __construct(
        ResourceConnection $resourceConnection
    ) {
        $this->_resourceConnection = $resourceConnection;

    }
    public function beforeSetTemplateVars(\Magento\Sales\Model\Order\Email\Container\Template $subject, array $vars)
    {
        /** @var Order $order */
        $order = $vars['order'];
        $method = $order->getShippingMethod();
        if($method === 'innoshipcargusgo_innoshipcargusgo_1'){
            $address = $order->getShippingAddress();
            $pudoId = $address->getInnoshipPudoId();

            $table = $this->_resourceConnection->getTableName('innoship_pudo');
            $connection = $this->_resourceConnection->getConnection();

            $courierNames = array();
            $tableNameCourierList = $this->_resourceConnection->getTableName('innoship_courierlist');
            $courierNameListQuery = $connection->query("select * from ".$tableNameCourierList);
            foreach($courierNameListQuery as $itemCourierNameItem){
                $courierNames[$itemCourierNameItem['courierId']] = $itemCourierNameItem['courierName'];
            }

            $result = $connection->select()->from($table,array("name","courierId","addressText"))->where('pudo_id = :pudoSelectedParameter');
            $bind = ['pudoSelectedParameter' => $pudoId];
            $allRows = $connection->fetchAll($result, $bind);

            foreach($allRows as $courierValue){
                $pudoSelected['name'] = $courierValue['name'];
                $pudoSelected['addressText'] = $courierValue['addressText'];
                $pudoSelected['courierName'] = $courierNames[$courierValue['courierId']];
            }

            $vars['formattedShippingAddress'] = '';
            if(isset($pudoSelected['name'])){
                $vars['formattedShippingAddress'].= $pudoSelected['name'].", ";
            }
            if(isset($pudoSelected['courierName'])){
                $vars['formattedShippingAddress'].= $pudoSelected['courierName'].", ";
            }
            if(isset($pudoSelected['addressText'])){
                $vars['formattedShippingAddress'].= $pudoSelected['addressText']."";
            }
        }

        return [$vars];
    }
}
