define(
    [
        'jquery',
        'mage/validation',
        'Magento_Checkout/js/model/quote'
    ],
    function ($,validator,quote) {
        'use strict';

        return {

            /**
             * Validate checkout agreements
             *
             * @returns {Boolean}
             */
            validate: function () {
                if(quote.shippingMethod()){
                    if(quote.shippingMethod().method_code){
                        let method = quote.shippingMethod().method_code;
                        if(method === "innoshipcargusgo_1"){
                            if(parseInt(window.localStorage['innoshipPud']) > 1){
                                return true;
                            } else {
                                alert("Va rugam selectati o locatie pentru Locker");
                                return false;
                            }
                        }
                    }
                }

                return true;
            }
        };
    }
);
