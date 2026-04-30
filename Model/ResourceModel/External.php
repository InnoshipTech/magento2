<?php

namespace InnoShip\InnoShip\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class External extends AbstractDb
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('innoship_external', 'id');
    }
}
