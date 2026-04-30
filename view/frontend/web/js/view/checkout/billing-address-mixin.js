define([
    'jquery',
    'ko',
    'underscore',
    'uiRegistry',
    'Magento_Checkout/js/model/quote',
], function (
    $,
    ko,
    _,
    registry,
    quote,
) {
    'use strict';

    var mixin = {
        initObservable: function () {
            var result = this._super();

            quote.billingAddress.subscribe(function (newAddress) {
                if(quote.shippingMethod()) {
                    if (quote.shippingMethod().method_code) {
                        if (quote.shippingMethod().method_code === 'innoshipcargusgo_1' && window.localStorage['innoshipFirstAddress'] === "false") {
                            // When locker is selected, billing must be different from shipping
                            // Hide address summary, show billing form
                            this.isAddressDetailsVisible(false);
                            this.isAddressSameAsShipping(false);
                        }
                    }
                }
            }, this);

            quote.shippingMethod.subscribe(function (newMethod) {
                if (!newMethod || !newMethod.method_code) {
                    return;
                }

                if (newMethod.method_code === 'innoshipcargusgo_1') {
                    // Hide address summary, show billing form
                    this.isAddressDetailsVisible(false);
                    this.isAddressSameAsShipping(false);
                } else if (window.localStorage['innoshipFirstAddress'] === "false") {
                    this.isAddressSameAsShipping(true);
                    this.isAddressDetailsVisible(false);
                }
            }, this);

            return result;
        },
        updateAddress: function () {
            window.localStorage['innoshipFirstAddress'] = true;
            this._super();
        }
    }

    return function (target) {
        return target.extend(mixin);
    };
});
