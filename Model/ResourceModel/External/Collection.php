<?php

namespace InnoShip\InnoShip\Model\ResourceModel\External;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use InnoShip\InnoShip\Model\External as ExternalModel;
use InnoShip\InnoShip\Model\ResourceModel\External as ExternalResourceModel;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'id';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ExternalModel::class, ExternalResourceModel::class);
    }
}
