define([
    'jquery',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-rate-registry',
    'Magento_Checkout/js/action/get-totals',
    'mage/utils/wrapper',
    'mage/validation'
], function ($, mainQuote, rateReg, getTotalsAction, wrapper, validation) {

    let citiDataList;
    // billing keeps its own city list - one shared list made the last getcity response win, so a
    // billing city was looked up in the shipping county's list (or the reverse) and the postcode came back empty
    let citiDataListBilling;
    let timeDiff = 0;
    let countryTmp;
    let cityTmp;
    let regioTmp;
    let shippingCitySelect = '#shippingcityselect';
    let billingCitySelect = '.billingcityselect';
    let shippingSelectHtml = '<select id="shippingcityselect"><option value="">Selectati</option></select>';
    let billingSelectHtml = '<select class="billingcityselect"><option value="">Selectati</option></select>';
    // Luma renders the billing form in .checkout-billing-address; other checkouts (Magelearn, some
    // themes) only in .billing-address-form.
    let billingContainer = '.checkout-billing-address, .billing-address-form';

    function escapeHtml(text) {
        return String(text === null || text === undefined ? '' : text).replace(/[&<>"']/g, function (m) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[m];
        });
    }

    function billingFields(selector) {
        return billingContainer.split(',').map(function (container) {
            return $.trim(container) + ' ' + selector;
        }).join(', ');
    }

    window.localStorage['timeNown']  = Math.floor(Date.now() / 1000);

    $(document).ready(function () {

        const observer = new MutationObserver(function(mutations_list) {
            mutations_list.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(added_node) {
                    timeDiff = Math.floor(Date.now() / 1000) - parseInt(window.localStorage['timeNown']);
                    if(added_node.id === 'co-shipping-form') {
                        setTimeout(function() {
                            countryTmp = $('.form-shipping-address [name="country_id"]');
                            cityTmp = $('.form-shipping-address [name="city"]');
                            regioTmp = $('.form-shipping-address [name="region_id"]');

                            checkCity(countryTmp, cityTmp, 1);
                            if (parseInt(regioTmp.val()) > 0 && countryTmp.val() === "RO") {
                                regioTmp.trigger('change');
                            }
                        },1000);
                    }
                    if(added_node.nodeType === 1 && $(added_node).is(billingContainer)){
                        setTimeout(function(){
                            if(!$(added_node).find(billingCitySelect + ':visible').length){
                                countryTmp = $(added_node).find('[name="country_id"]');
                                cityTmp = $(added_node).find('[name="city"]');
                                regioTmp = $(added_node).find('[name="region_id"]');

                                checkCity(countryTmp,cityTmp,2);
                                if(parseInt(regioTmp.val()) > 0 && countryTmp.val() === "RO"){
                                    regioTmp.trigger('change');
                                }
                            }
                        },1000);
                    }
                });
            });
        });

        observer.observe(document.querySelector(".checkout-index-index"), { subtree: true, childList: true});

        $(document).on('change','.form-shipping-address [name="country_id"]',function(){
            checkCity(this,$('.form-shipping-address [name="city"]'),1);
        });

        $(document).on('change','.form-shipping-address [name="region_id"]',function(){
            changeCity(this, $('.form-shipping-address [name="city"]'), $('.form-shipping-address [name="country_id"]'),shippingCitySelect,1);
        });

        $(document).on('change','#shippingcityselect',function(){
            showPostcode(this, $('.form-shipping-address [name="postcode"]'), citiDataList, $('.form-shipping-address [name="city"]'));
            // Write to the form fields only (showPostcode above); the form change updates the quote
            // address through core shipping-rates-validator. Writing city/region/country onto the
            // quote.shippingAddress() object itself changed a selected saved address card while the
            // customer edited the New Address pop-up.
            var address = mainQuote.shippingAddress();
            if (address) {
                rateReg.set(address.getKey(), null);
                rateReg.set(address.getCacheKey(), null);
            }
        });

        $(document).on('change', billingFields('[name="country_id"]'), function(){
            checkCity(this,$(this).closest(billingContainer).find('[name="city"]'),2);
        });

        $(document).on('change', billingFields('form fieldset [name="region_id"]'), function(){
            changeCity(this, $(this).closest(billingContainer).find('[name="city"]'), $(this).closest(billingContainer).find('[name="country_id"]'), $(this).closest(billingContainer).find(billingCitySelect), 2);
        });

        $(document).on('change','.billingcityselect',function(){
            showPostcode(this, $(this).closest(billingContainer).find('[name="postcode"]'), citiDataListBilling, $(this).closest(billingContainer).find('[name="city"]'));
        });
    });

    function showPostcode(city,postcode,citiDataList, inputCityObj){
        var cityVal = $(city).val();
        let codPostalAdd;
        // change/keyup on a shipping field arms core shipping-rates-validator (validateFields after
        // 2 s); fired from the payment step, estimate-shipping-methods then ran during
        // payment-information and its session write could drop the new order. Shipping fields are
        // only touched on the shipping step, and every field only notifies when its value changed.
        if ($(postcode).closest('.form-shipping-address').length && !$('#checkout-step-shipping').is(':visible')) {
            return;
        }
        $.each(citiDataList, function(i,item){
            if(item.localitate === cityVal){
                if(parseInt(item.codPostal) !== 0){
                    codPostalAdd = item.codPostal;
                    if($('.form-shipping-address [name="country_id"]').val() === "RO"){
                        if(codPostalAdd.length === 5){
                            codPostalAdd = "0" + codPostalAdd;
                        }
                    }
                } else {
                    codPostalAdd = "";
                }
                setValueAndNotify(postcode, codPostalAdd);
            }
        });
        setValueAndNotify(inputCityObj, cityVal);
    }

    function setValueAndNotify(field, value){
        if ($(field).val() === value) {
            return;
        }
        $(field).val(value).trigger('change').trigger('keyup');
    }

    function checkCity(country,cityObj,locationSelect){
        if ($(country).val() !== 'RO') {
            hideCitySelect(country,locationSelect);
        } else {
            if(parseInt(locationSelect) === 1){
                if($(shippingCitySelect).length){
                    $(shippingCitySelect).show();
                    $(cityObj).hide();
                } else {
                    $(cityObj).hide();
                    $(shippingSelectHtml).insertAfter(cityObj);
                }
            }
            if(parseInt(locationSelect) === 2){
                let tmpBS = $(country).closest(billingContainer).find(billingCitySelect);
                if($(tmpBS).length){
                    $(tmpBS).show();
                    $(cityObj).hide();
                } else {
                    $(cityObj).hide();
                    $(billingSelectHtml).insertAfter(cityObj);
                }
            }
        }
    }

    function changeCity(regionObj, cityObj, countryObj, selectObj, locationSelect) {
        // the shipping city list is only needed on the shipping step - a reload fired from the payment
        // step (InnoShip refilling the locker address) ran getcity during place order, whose session write could drop
        // the new order from the checkout session
        if (parseInt(locationSelect) === 1 && !$('#checkout-step-shipping').is(':visible')) {
            return;
        }
        if ($(countryObj).val() === 'RO') {
            let cityObjValTmp = $(cityObj).val();
            $(selectObj).html('<option value="...">...</option>');
            $.ajax({
                url: window.BASE_URL + "innoshipf/city/getcity",
                type: "POST",
                data: {
                    refresh: "1",
                    c: $(regionObj).val()
                },
                cache: false
            }).done(function (data) {
                let cityList = data['json_data'];
                if (parseInt(locationSelect) === 2) {
                    citiDataListBilling = cityList;
                } else {
                    citiDataList = cityList;
                }
                let htmlAppend = '<option value="">Selectati</option>';
                let localitateTmp = '';
                $.each(cityList, function (i, item) {
                    localitateTmp = item.localitate.toString();
                    if(cityObjValTmp === localitateTmp){
                        htmlAppend+='<option value="' + escapeHtml(localitateTmp) + '" selected>' + escapeHtml(item.localitate) + '</option>';
                    } else {
                        htmlAppend+='<option value="' + escapeHtml(localitateTmp) + '">' + escapeHtml(item.localitate) + '</option>';
                    }
                });
                $(selectObj).html(htmlAppend);
            });
        }
    }

    function hideCitySelect(country, locationSelect){
        if(parseInt(locationSelect) === 1) {
            if ($(shippingCitySelect).length && $(shippingCitySelect).is(":visible")) {
                $(shippingCitySelect).hide();
                $(country).closest('.form-shipping-address').find('[name="city"]').show();
            }
        }

        if(parseInt(locationSelect) === 2) {
            let $billingSelect = $(country).closest(billingContainer).find(billingCitySelect);
            if ($billingSelect.length && $billingSelect.is(":visible")) {
                $billingSelect.hide();
                $(country).closest(billingContainer).find('[name="city"]').show();
            }
        }
    }

    return function (setShippingInformationAction) {
        return wrapper.wrap(setShippingInformationAction, function (originalAction) {
            return originalAction();
        });
    }
});
