/**
 * Small checkout helpers used by checkout.js (kept out of checkout.js, which is over the file-size
 * ceiling).
 */
define([
    'Magento_Customer/js/model/customer',
    'Magento_Customer/js/model/address-list',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/select-billing-address',
    'Magento_Checkout/js/checkout-data',
    'jquery',
    'InnoShip_InnoShip/js/checkout/locker-billing'
], function (customer, addressList, quote, selectBillingAddress, checkoutData, $, lockerBilling) {
    'use strict';

    var isLegacyLockerAddress = lockerBilling.isLegacyLockerAddress;

    return {
        // Locker billing predicates live in locker-billing.js (the select-billing-address mixin needs them
        // without this module's checkout-action dependencies)
        isSavedBillingCard: lockerBilling.isSavedBillingCard,
        isLockerBillingCopy: lockerBilling.isLockerBillingCopy,

        /**
         * The method row is the added node itself on a re-render, but on the first (uncached) render
         * Knockout inserts the whole table/tbody with the row inside it: matching the id alone missed
         * it, and the Select button / courier list stayed missing until a refresh.
         *
         * @param {Node} addedNode
         * @param {String} rowId
         * @returns {Boolean}
         */
        addedNodeHasRow: function (addedNode, rowId) {
            if (!addedNode || addedNode.nodeType !== 1) {
                return false;
            }

            return addedNode.id === rowId
                || (typeof addedNode.querySelector === 'function' && addedNode.querySelector('#' + rowId) !== null);
        },

        /**
         * Locker unticks "same as shipping" (the locker is never the billing address). For a logged-in
         * customer with no saved card chosen as billing, the empty billing form was validated and the
         * step silently did not advance: select the default billing address, else the first saved one.
         * Old locker rows of the address book are never chosen.
         */
        selectSavedBillingAddress: function () {
            var current = quote.billingAddress(),
                saved,
                address;

            if (!customer.isLoggedIn() || (current && current.customerAddressId)) {
                return;
            }

            // Core billing-address.js (line 95) compares the new billing with quote.shippingAddress().getCacheKey():
            // on plain checkout the favourite locker is applied at load before the shipping address exists (#B2-8).
            if (!quote.shippingAddress()) {
                return;
            }

            // A new billing address the customer typed for this order is kept; it is never the locker,
            // which only ever goes into the shipping address.
            if (current && Array.isArray(current.street) && String(current.street[0] || '').trim() !== ''
                && quote.shippingAddress() && current.getCacheKey && quote.shippingAddress().getCacheKey
                && current.getCacheKey() !== quote.shippingAddress().getCacheKey()
            ) {
                return;
            }

            saved = addressList().filter(function (item) {
                return item.getType() === 'customer-address' && !isLegacyLockerAddress(item);
            });

            if (!saved.length) {
                return;
            }

            address = saved.filter(function (item) {
                return item.isDefaultBilling();
            })[0] || saved[0];

            selectBillingAddress(address);
            checkoutData.setSelectedBillingAddress(address.getKey());
        },

        /**
         * Hide or show the shipping step's "New Address" control with the address cards. Core wraps the
         * button in .new-address-popup; Amasty Checkout renders it as button.action-show-popup outside
         * that wrapper, so hiding the wrapper alone left Amasty's button on screen with a locker chosen.
         * Show only restores an Amasty button this hid: its own KO binding (visible: !isNewAddressAdded())
         * keeps deciding otherwise.
         *
         * @param {Boolean} visible
         */
        newAddressControls: function (visible) {
            var $step = $('#checkout-step-shipping'),
                $button = $step.find('button.action-show-popup').not('.new-address-popup *');

            if (visible) {
                $step.find('.new-address-popup').show();
                $button.filter('[data-innoship-hidden]').removeAttr('data-innoship-hidden').css('display', '');

                return;
            }
            $step.find('.new-address-popup').hide();
            // not :visible — that is false while the step itself is still hidden; only what KO hid is skipped
            $button.filter(function () {
                return this.style.display !== 'none';
            }).attr('data-innoship-hidden', '1').hide();
        }
    };
});
