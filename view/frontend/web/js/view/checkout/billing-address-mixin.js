define([
    'jquery',
    'ko',
    'underscore',
    'uiRegistry',
    'Magento_Checkout/js/model/quote',
    'InnoShip_InnoShip/js/checkout/helpers',
], function (
    $,
    ko,
    _,
    registry,
    quote,
    helpers,
) {
    'use strict';

    // Core checkout renders one billing-address component per payment method, and each one's
    // useShippingAddress() forces a billing-address POST: ten parallel saves raced in core
    // (BillingAddressManagement locks on the billing id they each replace) and recreated the
    // shipping address without the picked courier (#15). One save per method + address.
    var lastBillingSyncKey = null;

    // Counts the changes that leave Locker ('beforeChange' fires before every change subscriber): leaving
    // Locker must tick "same as shipping" again even after the customer updated a billing address there
    // (innoshipFirstAddress is then "true"). A counter, not "the method being left": one Curier click sets
    // the method twice, nested (Locker → Curier, then Curier → Curier), and the inner beforeChange
    // overwrote the left method before any billing component saw the change (#B2-4). Each billing
    // component (one per payment method) compares it with the count it last handled.
    var lockerLeaveCount = 0;

    // Set while a component clears the locker copy from billing: the null it writes notifies every
    // billing component (one per payment method) and must not re-enter the clear.
    var droppingLockerBilling = false;

    quote.shippingMethod.subscribe(function (oldMethod) {
        if (oldMethod && oldMethod.method_code === 'innoshipcargusgo_1') {
            lockerLeaveCount++;
        }
    }, null, 'beforeChange');

    function billingSyncKey(method, shippingAddress) {
        // getCacheKey() is the address type for a new address: key on the fields billing copies
        return method.carrier_code + '_' + method.method_code + '|'
            + JSON.stringify(_.pick(shippingAddress, [
                'firstname', 'lastname', 'company', 'street', 'city', 'postcode',
                'region', 'regionId', 'countryId', 'telephone', 'customerAddressId'
            ]));
    }

    var mixin = {
        initObservable: function () {
            var result = this._super(),
                coreCanUseShippingAddress = this.canUseShippingAddress;

            this.innoshipHandledLeaveCount = lockerLeaveCount;

            // Locker: billing can never be the locker address, so "My billing and shipping address are the same"
            // is not offered at all (order 000000231 ticked it on the payment step and billed the locker). Per
            // instance, wrapping whichever prototype computed won (core or Amasty's billing-address-mixin).
            this.canUseShippingAddress = ko.pureComputed(function () {
                var method = quote.shippingMethod();

                if (method && method.method_code === 'innoshipcargusgo_1') {
                    return false;
                }

                return coreCanUseShippingAddress.call(this);
            }, this);

            quote.billingAddress.subscribe(function (newAddress) {
                if(quote.shippingMethod()) {
                    if (quote.shippingMethod().method_code) {
                        if (quote.shippingMethod().method_code === 'innoshipcargusgo_1') {
                            if (this.innoshipDropLockerBilling(newAddress)) {
                                return;
                            }
                            // When locker is selected, billing must be different from shipping:
                            // a saved billing card stays shown, anything else opens the billing form
                            if (window.localStorage['innoshipFirstAddress'] === "false") {
                                this.isAddressDetailsVisible(helpers.isSavedBillingCard(newAddress));
                            }
                            // Always untick: core ticks the hidden box when billing is the shipping card's
                            // address (same cache key), and checkout.js then clicks it, which runs core
                            // useShippingAddress() and sets the billing address to null (form, no card).
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
                    // A saved billing card stays shown with Edit; anything else opens the billing form
                    this.isAddressDetailsVisible(helpers.isSavedBillingCard(quote.billingAddress()));
                    this.isAddressSameAsShipping(false);
                    this.innoshipDropLockerBilling(quote.billingAddress());
                    this.innoshipRetickPending = false;
                    // billing leaves shipping here: the next billing = shipping must be saved again, even for
                    // the same method + address as before Locker (the key skipped useShippingAddress, #B2-4)
                    lastBillingSyncKey = null;
                } else if (window.localStorage['innoshipFirstAddress'] === "false") {
                    // Tick "same as shipping" and apply it: billing = shipping and the address summary
                    // shown. isAddressDetailsVisible(false) here opened an empty billing form under a
                    // ticked box, which kept Amasty's Place Order disabled.
                    this.isAddressSameAsShipping(true);
                    if (quote.shippingAddress()) {
                        var syncKey = billingSyncKey(newMethod, quote.shippingAddress());

                        if (syncKey !== lastBillingSyncKey) {
                            this.innoshipUseShippingAddress(syncKey);
                        } else {
                            // another billing component already saved billing = shipping for this change
                            this.isAddressDetailsVisible(true);
                        }
                    }
                } else if (this.innoshipHandledLeaveCount !== lockerLeaveCount) {
                    this.innoshipHandledLeaveCount = lockerLeaveCount;
                    // Left Locker after a billing address was updated there: tick "same as shipping"
                    // again, but only once the customer's saved card is the shipping address again
                    // (checkout/courier-address.js restores it after this change; ticking now would
                    // copy the locker address into billing). Guests and customers without saved
                    // addresses get the tick back from courier-address.js restoreCourierUi().
                    this.innoshipRetickPending = true;
                    this.innoshipApplyRetick();
                }
            }, this);

            quote.shippingAddress.subscribe(function () {
                this.innoshipApplyRetick();
            }, this);

            return result;
        },

        // Locker + billing = the locker copy (a guest on plain checkout, innoshipFirstAddress "true"): open the
        // form and clear billing, so Place Order stays disabled until the customer updates a real billing
        // address. Keeping the copy showed it as the billing card and the order billed the locker.
        innoshipDropLockerBilling: function (address) {
            if (droppingLockerBilling || !helpers.isLockerBillingCopy(address, quote.shippingAddress())) {
                return false;
            }

            droppingLockerBilling = true;

            try {
                this.isAddressSameAsShipping(false);
                this.isAddressDetailsVisible(false);
                quote.billingAddress(null);
            } finally {
                droppingLockerBilling = false;
            }

            return true;
        },

        innoshipApplyRetick: function () {
            var method = quote.shippingMethod(),
                shippingAddress = quote.shippingAddress(),
                syncKey;

            if (!this.innoshipRetickPending || !method || method.method_code === 'innoshipcargusgo_1'
                || !shippingAddress || !shippingAddress.customerAddressId
            ) {
                return;
            }

            this.innoshipRetickPending = false;
            this.isAddressSameAsShipping(true);
            syncKey = billingSyncKey(method, shippingAddress);

            if (syncKey !== lastBillingSyncKey) {
                this.innoshipUseShippingAddress(syncKey);
            } else {
                this.isAddressDetailsVisible(true);
            }
        },

        // Copies shipping into billing. The quote shipping address carries no telephone until shipping-information
        // is saved (core shipping-rates-validator copies only the rate fields), and 2.4.9 refuses a billing-address
        // POST without one (400 "Phone Number is a required value"): copy on the page, skip that POST, leave the
        // key unset so the next change saves again. Billing still goes to the server with payment-information.
        innoshipUseShippingAddress: function (syncKey) {
            if (quote.shippingAddress().telephone) {
                lastBillingSyncKey = syncKey;
                this.useShippingAddress();

                return;
            }

            this.innoshipSkipBillingSave = true;

            try {
                this.useShippingAddress();
            } finally {
                this.innoshipSkipBillingSave = false;
            }
        },
        updateAddresses: function (force) {
            if (this.innoshipSkipBillingSave) {
                return;
            }

            return this._super(force);
        },
        updateAddress: function () {
            var savedAddressPath = this.selectedAddress() && !this.isAddressFormVisible();

            window.localStorage['innoshipFirstAddress'] = true;
            this._super();

            // Amasty's quote-mixin compares quote.billingAddress by value: re-selecting the saved address the
            // quote already holds (the Locker branch above hid the details but kept the address) does not
            // notify, so core never runs isAddressDetailsVisible(true) and Amasty keeps the billing form open.
            // The same holds for the form path: a guest back on Locker gets the form prefilled with the billing
            // address the quote already holds, and Update with those values changed nothing visible (form open,
            // Place Order disabled). Only after a valid form: an invalid one keeps the form and its errors.
            if ((savedAddressPath || !this.source.get('params.invalid'))
                && quote.billingAddress() && !this.isAddressDetailsVisible()
            ) {
                this.isAddressDetailsVisible(true);
            }
        }
    }

    return function (target) {
        return target.extend(mixin);
    };
});
