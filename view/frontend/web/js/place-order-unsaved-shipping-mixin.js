/**
 * #B2-9 (Amasty One Step Checkout): with Locker chosen the separate billing form is open, and Amasty's auto-save
 * (shipping-mixin.js validateAndSaveIfChanged) returns early while isBillingAddressFormVisible() is true. A phone
 * typed after Confirma therefore reaches only the browser quote: Amasty's place-order shipping validator copies
 * the form into quote.shippingAddress with saving switched off (payment-validators/shipping-validator.js), and
 * payment-information is refused by the server (QuoteValidator: telephone required).
 *
 * Save the shipping information first when Amasty's registry reports unsaved shipping, then place the order.
 * Active only with Amasty One Step Checkout: Amasty not installed (its registry module never loads), Amasty
 * switched off (Amasty sets window.amasty_checkout_disabled), a virtual quote or nothing unsaved — the original
 * action, unchanged. Moved here from the InnoShip theme: the open billing form is InnoShip's locker flow.
 * ponytail: covers methods that place the order through Magento_Checkout/js/action/place-order; a redirect method
 * that skips it (e.g. PayPal Express) would need the same guard on set-payment-information.
 */
define([
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/set-shipping-information'
], function (wrapper, quote, setShippingInformationAction) {
    'use strict';

    var registryId = 'Amasty_CheckoutCore/js/model/shipping-registry';

    /**
     * @return {Boolean}
     */
    function hasUnsavedShipping() {
        // Amasty's registry is used only when Amasty already loaded it: no hard dependency on Amasty files.
        if (window.amasty_checkout_disabled || !require.defined(registryId)
            || quote.isVirtual() || !quote.shippingMethod()
        ) {
            return false;
        }

        return require(registryId).isHaveUnsavedShipping();
    }

    return function (placeOrderAction) {
        return wrapper.wrap(placeOrderAction, function (original, paymentData, messageContainer) {
            if (!hasUnsavedShipping()) {
                return original(paymentData, messageContainer);
            }

            // A failed shipping save rejects the chain: no order request, the save's own error is shown.
            return setShippingInformationAction().then(function () {
                return original(paymentData, messageContainer);
            });
        });
    };
});
