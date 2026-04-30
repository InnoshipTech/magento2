<?php

namespace InnoShip\InnoShip\Controller\Pudo;

use InnoShip\InnoShip\Model\Config;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use InnoShip\InnoShip\Model\Api\Rest\Service;
use InnoShip\InnoShip\Api\ServiceInterface;

class GetPudoFromLocation extends Action
{
    private JsonFactory $resultJsonFactory;
    protected Config $config;
    private Service $service;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Config $config,
        Service $service
    )
    {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->config = $config;
        $this->service = $service;
        parent::__construct($context);
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        $lat = $this->getRequest()->getParam('lat');
        $long = $this->getRequest()->getParam('long');

        $dataReceive = [];
        $dataToSend = [];

        if($this->isValidGPS($lat, $long)) {
            $dataReceive = $this->service->makeRequest('/api/Location/FixedLocations?ShowInactive=false&Latitude='.$lat.'&Longitude='.$long.'&Radius=1', [], ServiceInterface::GET);
            if(is_array($dataReceive)){
                if(!empty($dataReceive)){
                    if(isset($dataReceive[0])){
                        $dataToSend['countryCode'] = $dataReceive[0]['countryCode'];
                        $dataToSend['countyName'] = $dataReceive[0]['countyName'];
                        $dataToSend['localityName'] = $dataReceive[0]['localityName'];
                    }
                }
            }
        }

        return $resultJson->setData(['json_data' => $dataToSend]);
    }

    private function isValidGPS($latitude, $longitude): bool {
        return is_numeric($latitude) && is_numeric($longitude) &&
            $latitude >= -90 && $latitude <= 90 &&
            $longitude >= -180 && $longitude <= 180;
    }
}
