<?php

namespace InnoShip\InnoShip\Plugin\Model;

use InnoShip\InnoShip\Model\Config;
use Magento\Payment\Model\MethodList;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Store\Model\StoreManagerInterface;

class PaymentMethodAvailable
{
    /** @var Config */
    protected $config;
    /**
     * @var StoreManagerInterface
     */
    private $_storeManagerInterface;

    public function __construct(
        Config $config,
        StoreManagerInterface $storeManagerInterface
    ) {
        $this->config = $config;
        $this->_storeManagerInterface = $storeManagerInterface;
    }

    public function afterGetAvailableMethods(MethodList $subject, $availableMethods, ?CartInterface $quote = null)
    {

        $shippingMethod = $this->getShippingMethodFromQuote($quote);
        if($shippingMethod === "innoshipcargusgo_innoshipcargusgo_1"){
            $currentStore = $this->_storeManagerInterface->getStore();
            $currentStoreId = $currentStore->getId();
            $methods = explode(',',(string)$this->config->getPaymentRestriction($currentStoreId));
            if(!empty($methods)){
                foreach ($availableMethods as $key => $method) {
                    if(in_array($method->getCode(), $methods, true) === false){
                        unset($availableMethods[$key]);
                    }
                }
            }
        }

        return $availableMethods;
    }

    private function getShippingMethodFromQuote($quote)
    {
        if($quote) {
            return $quote->getShippingAddress()->getShippingMethod();
        }
        return '';
    }
}
