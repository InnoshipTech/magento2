<?php

namespace InnoShip\InnoShip\Model;

use Magento\Framework\Model\AbstractModel;
use InnoShip\InnoShip\Model\ResourceModel\External as ExternalResourceModel;

class External extends AbstractModel
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ExternalResourceModel::class);
    }

    /**
     * Get ID
     *
     * @return int|null
     */
    public function getId()
    {
        return $this->getData('id');
    }

    /**
     * Get External Location ID
     *
     * @return string|null
     */
    public function getExternal()
    {
        return $this->getData('external');
    }

    /**
     * Get Country Name
     *
     * @return string|null
     */
    public function getCountryName()
    {
        return $this->getData('countryName');
    }

    /**
     * Get Country Code
     *
     * @return string|null
     */
    public function getCountryCode()
    {
        return $this->getData('countryCode');
    }

    /**
     * Set ID
     *
     * @param int $id
     * @return $this
     */
    public function setId($id)
    {
        return $this->setData('id', $id);
    }

    /**
     * Set External Location ID
     *
     * @param string $external
     * @return $this
     */
    public function setExternal($external)
    {
        return $this->setData('external', $external);
    }

    /**
     * Set Country Name
     *
     * @param string $countryName
     * @return $this
     */
    public function setCountryName($countryName)
    {
        return $this->setData('countryName', $countryName);
    }

    /**
     * Set Country Code
     *
     * @param string $countryCode
     * @return $this
     */
    public function setCountryCode($countryCode)
    {
        return $this->setData('countryCode', $countryCode);
    }
}
