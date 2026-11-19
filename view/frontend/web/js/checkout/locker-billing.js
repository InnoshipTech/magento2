/**
 * Locker billing predicates. Kept free of checkout actions on purpose: the select-billing-address mixin requires
 * this module, and helpers.js requires select-billing-address, so helpers.js here was a RequireJS cycle (the
 * checkout never left its spinner).
 */
define([
    'Magento_Customer/js/model/customer'
], function (customer) {
    'use strict';

    function isLegacyLockerAddress(address) {
        var ids = (window.checkoutConfig && window.checkoutConfig.innoshipLockerAddressIds) || [];

        return !!address.customerAddressId && ids.indexOf(parseInt(address.customerAddressId, 10)) !== -1;
    }

    function street(address) {
        return Array.isArray(address.street) ? String(address.street[0] || '').trim() : '';
    }

    var api = {
        isLegacyLockerAddress: isLegacyLockerAddress,

        /**
         * Locker: a billing address picked from the customer's address book (never an old locker row) is
         * shown as the summary card with Edit; anything else opens the billing form (order 000000346: the
         * form was open over a billing address already on the quote, and Place Order used it unseen).
         *
         * @param {Object|null} address
         * @returns {Boolean}
         */
        isSavedBillingCard: function (address) {
            return !!(address && address.customerAddressId && customer.isLoggedIn()
                && !isLegacyLockerAddress(address));
        },

        /**
         * Locker: billing is a copy of the locker shipping address (core copies shipping into billing for a
         * guest on the payment step). It must never be billed: a saved customer card is not a copy, and a
         * billing address the customer typed has its own street/postcode/city.
         *
         * @param {Object|null} billing
         * @param {Object|null} shipping
         * @returns {Boolean}
         */
        isLockerBillingCopy: function (billing, shipping) {
            if (!billing || !shipping || api.isSavedBillingCard(billing) || street(billing) === '') {
                return false;
            }

            return street(billing) === street(shipping)
                && String(billing.postcode || '') === String(shipping.postcode || '')
                && String(billing.city || '') === String(shipping.city || '');
        }
    };

    return api;
});
