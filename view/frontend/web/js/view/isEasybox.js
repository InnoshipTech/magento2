define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/additional-validators',
        'InnoShip_InnoShip/js/model/isEasybox'
    ],
    function (Component, additionalValidators, easyboxValidation) {
        'use strict';
        additionalValidators.registerValidator(easyboxValidation);
        return Component.extend({});
    }
);
