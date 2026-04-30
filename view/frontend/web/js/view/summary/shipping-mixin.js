/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'jquery',
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/model/customer'
], function ($,wrapper, quote, customer) {
    'use strict';

    /**
     * SECURITY: HTML escape function to prevent XSS attacks
     * Escapes HTML special characters to prevent script injection
     */
    function escapeHtml(text) {
        if (text === null || text === undefined) {
            return '';
        }
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    var mixin = {
        getShippingMethodTitle: function () {
            var shippingMethod = '',
                shippingMethodTitle = '';

            if (!this.isCalculated()) {
                return '';
            }
            shippingMethod = quote.shippingMethod();

            if (typeof shippingMethod['method_title'] !== 'undefined') {
                shippingMethodTitle = ' - ' + shippingMethod['method_title'];
            }

            var pudoDescriptionLong = window.localStorage['innoshipPudDescription'];
            var pudoDescription = pudoDescriptionLong;
            if(pudoDescription){
                pudoDescription = pudoDescription.replace("<br/>",", ");
                pudoDescription = pudoDescription.replace("<b>","");
                pudoDescription = pudoDescription.replace("</b>","");

                if(shippingMethod['method_code'] === 'innoshipcargusgo_1'){
                    shippingMethodTitle+= " (" + pudoDescription+ ", " + window.localStorage['innoshipPudAddress'] + ")";
                    if(customer.isLoggedIn()){
                        // SECURITY: Escape HTML to prevent XSS
                        var escapedPudoDescriptionLong = escapeHtml(pudoDescriptionLong);
                        var escapedPudoAddress = escapeHtml(window.localStorage['innoshipPudAddress']);
                        $('.ship-to .shipping-information-content').html(escapedPudoDescriptionLong + "<br/><strong>Adresa:</strong> " + escapedPudoAddress);
                    }
                }
            }

            return shippingMethodTitle ?
                shippingMethod['carrier_title'] + shippingMethodTitle :
                shippingMethod['carrier_title'];
        },
    };

    /**
     * Override default getShippingMethodTitle
     */
    return function (OriginShipping) {
        return OriginShipping.extend(mixin);
    };
});
