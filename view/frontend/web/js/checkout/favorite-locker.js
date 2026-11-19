/**
 * Keeps the customer's favourite locker when a rates reload passes through a non-locker method.
 *
 * The favourite-locker request stores the locker and writes its address into the shipping form; the
 * address change reloads the rates, the selected method passes through a non-locker method, and the
 * shippingMethod subscriber in checkout.js runs clearStoredPudo() (innoshipPud = '0', address and
 * description removed, setpudo POSTed with pudoid=0 so the quote loses innoship_pudo_id).
 *
 * Whenever the locker method becomes selected while no locker is stored, put the favourite back in
 * the localStorage keys checkout.js reads, before its row re-render handler runs, and store it on
 * the quote again with the same request setPudoValue() sends.
 */
define([
    'jquery',
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/model/customer',
    'mage/cookies'
], function ($, quote, customer) {
    'use strict';

    function getFavoriteLocker() {
        var attributes = customer.isLoggedIn() && customer.customerData && customer.customerData.custom_attributes;

        if (!attributes || !attributes.favorite_locker || !attributes.favorite_locker_name) {
            return null;
        }

        if (!(parseInt(attributes.favorite_locker.value, 10) > 0) || !attributes.favorite_locker_name.value) {
            return null;
        }

        return {
            id: String(attributes.favorite_locker.value),
            name: String(attributes.favorite_locker_name.value)
        };
    }

    /**
     * The locker picked earlier in this checkout visit (checkout.js clearStoredPudo() keeps it when
     * the customer leaves Locker); it wins over the favourite: Locker → Curier → Locker gives back
     * the customer's own pick.
     */
    function getLastPickedLocker() {
        var id = window.localStorage.getItem('innoshipLastPud');

        if (!(parseInt(id, 10) > 0)) {
            return null;
        }

        return {
            id: String(id),
            name: window.localStorage.getItem('innoshipLastPudAddress') || '',
            description: window.localStorage.getItem('innoshipLastPudDescription') || ''
        };
    }

    quote.shippingMethod.subscribe(function (method) {
        var stored,
            favorite;

        if (!method || method['carrier_code'] !== 'innoshipcargusgo') {
            return;
        }

        stored = window.localStorage.getItem('innoshipPud');

        if (parseInt(stored, 10) > 0) {
            return;
        }

        favorite = getLastPickedLocker() || getFavoriteLocker();

        if (!favorite) {
            return;
        }

        window.localStorage.setItem('innoshipPud', favorite.id);
        window.localStorage.setItem('innoshipPudAddress', favorite.name);
        if (favorite.description) {
            window.localStorage.setItem('innoshipPudDescription', favorite.description);
        }

        $.ajax({
            url: window.BASE_URL + 'innoshipf/pudo/setpudo',
            type: 'POST',
            data: {
                refresh: '1',
                quote: quote.getQuoteId(),
                pudoid: favorite.id,
                'form_key': $.mage.cookies.get('form_key')
            },
            cache: false
        });
    });
});
