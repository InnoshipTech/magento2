/**
 * Restores the courier address and UI when the customer leaves the Locker method.
 *
 * Customer with saved addresses: fillShippingAddressInformation() (checkout.js) writes the locker
 * address into the new-address form, which for such a customer sits in the closed pop-up. Core
 * shipping-rates-validator.js (bindHandler → validateFields) turns every value change made while the
 * pop-up is closed into selectShippingAddress(), so the locker became the quote shipping address, and
 * leaving Locker (showShippingAddressInformation() blanks the fields) selected a blank address.
 * restoreListAddress(): outside Locker, an address that is not one of the customer's addresses is
 * replaced by the last address the customer had selected.
 *
 * Guest: the locker address replaced what the guest typed, and leaving Locker cleared it.
 * rememberGuestAddress() keeps the typed address and the "same as shipping" tick; restoreCourierUi()
 * puts them back.
 *
 * Every checkout: the address cards, the "new address" button, the step title and the billing
 * "same as shipping" block were shown again only by the body-delegated .fake-click handler. Knockout's
 * click binding on that node selects the method first, the method rows re-render and detach the
 * clicked node, so the delegated handler never matches on the click that changes the method.
 * restoreCourierUi() does the same restore from the method change itself.
 */
define([
    'jquery',
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/model/customer',
    'Magento_Customer/js/model/address-list',
    'Magento_Checkout/js/action/select-shipping-address',
    'Magento_Checkout/js/checkout-data',
    'uiRegistry',
    'InnoShip_InnoShip/js/checkout/helpers'
], function ($, quote, customer, addressList, selectShippingAddress, checkoutData, registry, helpers) {
    'use strict';

    var lastListAddress = null,
        guestAddressBeforeLocker = null,
        lockerVisited = false,
        billingSameBeforeLocker = false,
        billingFormName = 'checkout.steps.shipping-step.shippingAddress.billing-address-form';

    function isLocker(method) {
        return !!method && method['carrier_code'] === 'innoshipcargusgo';
    }

    /**
     * checkout.js hides the address fields itself (.hide() → inline display:none). jQuery ':hidden'
     * is also true for every field while the whole shipping step is hidden, so on the payment step a
     * shipping-rates refresh would rewrite the fields and fire getcity during place order.
     */
    function hiddenByInnoShip($field) {
        return $field.length > 0 && $field[0].style.display === 'none';
    }

    /**
     * Guests and customers without saved addresses type into the inline shipping form; customers
     * with saved addresses pick a card and the form sits in the closed New Address pop-up.
     */
    function usesInlineForm() {
        return !customer.isLoggedIn() || addressList().length === 0;
    }

    /**
     * Remember the address a guest typed (and the "same as shipping" tick removed for the locker)
     * while the form still shows it, so leaving the locker can put it back.
     */
    function rememberGuestAddress(method) {
        var $country = $('div[name="shippingAddress.country_id"]'),
            billing;

        if (!usesInlineForm() || !isLocker(method)) {
            return;
        }

        // The tick is taken once per locker visit, on entering it: checkout.js unticks it a moment
        // later, and a rates refresh re-selects the locker method while the customer is still on it.
        if (!lockerVisited) {
            billing = registry.get(billingFormName);
            billingSameBeforeLocker = !!billing && billing.isAddressSameAsShipping();
            lockerVisited = true;
        }

        // The typed address only while the form still shows it: on a second visit the stored locker
        // may already be in it, and the address from the first visit is the one to keep.
        if (!$country.length || hiddenByInnoShip($country)) {
            return;
        }

        guestAddressBeforeLocker = {
            street: $('div[name="shippingAddress.street.0"]').find('input').val(),
            city: $('div[name="shippingAddress.city"]').find('input').val(),
            regionId: $('div[name="shippingAddress.region_id"]').find('select').val(),
            postcode: $('div[name="shippingAddress.postcode"]').find('input').val()
        };
    }

    function isBlankAddress(address) {
        return !address.customerAddressId
            && !$.trim((address.street || []).join(''))
            && !address.city;
    }

    /**
     * A locker address saved into the address book by an older module version (server-side match,
     * Model/Checkout/LockerAddressConfigProvider): never a courier address to fall back to.
     */
    function isLegacyLockerAddress(address) {
        var ids = (window.checkoutConfig && window.checkoutConfig.innoshipLockerAddressIds) || [];

        return !!address.customerAddressId && ids.indexOf(parseInt(address.customerAddressId, 10)) !== -1;
    }

    function fallbackAddress() {
        var addresses = addressList(),
            selectedKey = checkoutData.getSelectedShippingAddress();

        if (lastListAddress && addresses.indexOf(lastListAddress) !== -1 && !isBlankAddress(lastListAddress)) {
            return lastListAddress;
        }

        // After a reload with Locker preselected nothing was remembered yet; the key core stored on
        // selection ("new-customer-address" for a "Ship here" address) still names the choice.
        addresses = addresses.filter(function (address) {
            return !isBlankAddress(address) && !isLegacyLockerAddress(address);
        });

        return addresses.filter(function (address) {
            return selectedKey && address.getKey() === selectedKey;
        })[0] || addresses.filter(function (address) {
            return address.isDefaultShipping();
        })[0] || addresses[0] || null;
    }

    function restoreListAddress() {
        var address = quote.shippingAddress(),
            restored;

        if (!customer.isLoggedIn() || !address) {
            return;
        }

        // A blank address in the list is not a choice of the customer: the checkout can add the
        // (empty) New Address form address to the list, which used to stop the restore below.
        if (addressList().indexOf(address) !== -1 && !isBlankAddress(address)) {
            lastListAddress = address;

            return;
        }

        if (isLocker(quote.shippingMethod())) {
            return;
        }

        restored = fallbackAddress();

        if (!restored) {
            return;
        }

        lastListAddress = restored;
        selectShippingAddress(restored);
        checkoutData.setSelectedShippingAddress(restored.getKey());
    }

    /**
     * Mirrors the non-locker branch of the .fake-click handler in checkout.js. The form fields are
     * rewritten only when checkout.js hid them (locker address written into the visible inline
     * form); a customer's pop-up form is only shown again, or the cleared values would be selected
     * as the shipping address.
     */
    function restoreCourierUi(method) {
        var $step = $('#checkout-step-shipping'),
            $country = $('div[name="shippingAddress.country_id"]'),
            typed,
            billing;

        if (!method || isLocker(method)) {
            return;
        }

        if (hiddenByInnoShip($country) && !usesInlineForm()) {
            // Customer with saved addresses: the hidden form is the closed New Address pop-up.
            // Rewriting it would make shipping-rates-validator select a blank address; the saved
            // address comes back through restoreListAddress(). Only make the fields usable again.
            $country.show();
            $('div[name="shippingAddress.city"]').show();
            $('div[name="shippingAddress.street.0"]').closest('.street').show();
            $('div[name="shippingAddress.postcode"]').show();
            $('div[name="shippingAddress.region_id"]').show();
            $('div[name="shippingAddress.entity_type"]').show();
        } else if (hiddenByInnoShip($country)) {
            typed = guestAddressBeforeLocker;

            $country.show().trigger('change');
            // City before region: set-city-mixin changeCity() reads the city input when the region
            // changes and preselects it in the rebuilt city list, without touching the postcode.
            $('div[name="shippingAddress.city"]').show().find('input').val(typed ? typed.city : '').trigger('change');
            $('div[name="shippingAddress.street.0"]').closest('.street').show();
            $('div[name="shippingAddress.street.0"]').find('input').val(typed ? typed.street : '').trigger('change');
            $('div[name="shippingAddress.postcode"]').show().find('input').val(typed ? typed.postcode : '').trigger('change');
            $('div[name="shippingAddress.region_id"]').show().find('select').val(typed ? typed.regionId : '').trigger('change');
            $('div[name="shippingAddress.entity_type"]').show();
        }

        // Outside the block above: the box is unticked whenever a locker is displayed, also when the
        // fields were not hidden again. Through the component, not a DOM click on the hidden box.
        // Once per locker visit: a later rates refresh must not re-tick a box the guest unticked since.
        if (usesInlineForm() && lockerVisited) {
            billing = registry.get(billingFormName);

            if (billingSameBeforeLocker && billing && !billing.isAddressSameAsShipping()) {
                billing.isAddressSameAsShipping(true);
                billing.useShippingAddress();
            }

            lockerVisited = false;
        }

        $('.billing-address-same-as-shipping-block').show();
        $('#label_carrier_innoshipcargusgo_1_innoshipcargusgopudom').hide();

        if (!customer.isLoggedIn()) {
            return;
        }

        $step.find('.field.addresses').show();
        helpers.newAddressControls(true);
        $('.shipping-address-item').children('.action-select-shipping-item').show();
        $('.innoship-shipping-address-info-extra').html('').hide();
        $('#shipping').find('.step-title').show();
    }

    quote.shippingAddress.subscribe(restoreListAddress);
    quote.shippingMethod.subscribe(restoreListAddress);
    quote.shippingMethod.subscribe(rememberGuestAddress);
    quote.shippingMethod.subscribe(restoreCourierUi);
});
