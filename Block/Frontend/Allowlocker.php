<?php

namespace InnoShip\InnoShip\Block\Frontend;
use InnoShip\InnoShip\Model\Config;
use Magento\Framework\View\Element\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ResourceConnection;

class Allowlocker extends Template
{
    private $_resourceConnection;
    private $config;
    private $countryFactory;
    private \Magento\Directory\Model\AllowedCountries $allowedCountryModel;

    public function __construct(Context $context, Config $config, ResourceConnection $resourceConnection, \Magento\Directory\Model\CountryFactory $countryFactory, \Magento\Directory\Model\AllowedCountries $allowedCountryModel, array $data = [])
    {
        $this->config = $config;
        $this->_resourceConnection = $resourceConnection;
        $this->countryFactory = $countryFactory;
        $this->allowedCountryModel = $allowedCountryModel;
        parent::__construct($context, $data);
    }

    public function getAllowCountry()
    {
        return $this->config->getSpecificCountriesGo();
    }

    public function getAllowCountryLocker()
    {
        $allowCountrys = $this->getAllowCountry();
        $allowCountrySettings = array();
        $countryObj = $this->countryFactory->create();
        if($allowCountrys){
            $allowCountrySettings = explode(",",$allowCountrys);
        }

        if(empty($allowCountrys)){
            $allowCountrySettings = $this->allowedCountryModel->getAllowedCountries();
        }
        $connection = $this->_resourceConnection->getConnection();
        $table = $this->_resourceConnection->getTableName('innoship_pudo');
        $query = "select distinct countryCode from ".$table." where supportedPaymentType in ('Card, Cash','Cash','Card')";
        $countryListLocker = $connection->query($query);
        $listSendData = array();

        foreach($countryListLocker as $countryFetch){
            if(in_array($countryFetch['countryCode'],$allowCountrySettings)){
                $listSendData[$countryFetch['countryCode']] = $countryObj->loadByCode($countryFetch['countryCode'])->getName();
            }
        }

        return $listSendData;
    }
}
