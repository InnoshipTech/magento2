/**
 * Locker: the billing address is never the locker. Core copies the shipping address into billing through this
 * action (checkout-data-resolver on the payment step, useShippingAddress()); with Locker selected that copy is
 * the locker, and the order billed it. Refuse it here, before quote.billingAddress is written: clearing it later
 * from a billingAddress subscriber was not enough, because the subscribers after it (the payment renderer's
 * isPlaceOrderActionAllowed) still received the locker value and enabled Place Order over a null billing.
 * The current billing address (null, or one the customer typed or picked) stays as it is.
 */
define([
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote',
    'InnoShip_InnoShip/js/checkout/locker-billing'
], function (wrapper, quote, lockerBilling) {
    'use strict';

    return function (selectBillingAddress) {
        return wrapper.wrap(selectBillingAddress, function (originalAction, billingAddress) {
            var method = quote.shippingMethod();

            if (method && method.method_code === 'innoshipcargusgo_1'
                && lockerBilling.isLockerBillingCopy(billingAddress, quote.shippingAddress())
            ) {
                return;
            }

            return originalAction(billingAddress);
        });
    };
});
