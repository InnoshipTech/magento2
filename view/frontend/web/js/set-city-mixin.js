define([
    'jquery',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-rate-registry',
    'Magento_Checkout/js/action/get-totals',
    'mage/utils/wrapper',
    'mage/validation'
], function ($, mainQuote, rateReg, getTotalsAction, wrapper, validation) {

    let citiDataList;
    let timeDiff = 0;
    let countryTmp;
    let cityTmp;
    let regioTmp;
    let shippingCitySelect = '#shippingcityselect';
    let billingCitySelect = '.billingcityselect';
    let shippingSelectHtml = '<select id="shippingcityselect"><option value="">Selectati</option></select>';
    let billingSelectHtml = '<select class="billingcityselect"><option value="">Selectati</option></select>';

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
                    if($(added_node).attr('class') === "checkout-billing-address"){
                        setTimeout(function(){
                            if(!$(added_node).find('.billingCitySelect:visible').length){
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
            var address = mainQuote.shippingAddress();
            address['city'] = $(this).val();
            address['regionId'] = $('.form-shipping-address [name="region_id"]').val();
            address['region'] = $('.form-shipping-address [name="region_id"]').find(':selected').data('title');
            address['countryId'] = $('.form-shipping-address [name="country_id"]').val();
            rateReg.set(address.getKey(), null);
            rateReg.set(address.getCacheKey(), null);
            mainQuote.shippingAddress(address);
        });

        $(document).on('change','.checkout-billing-address [name="country_id"]',function(){
            checkCity(this,$(this).closest('.checkout-billing-address').find('[name="city"]'),2);
        });

        $(document).on('change','.checkout-billing-address form fieldset [name="region_id"]',function(){
            changeCity(this, $(this).closest('.checkout-billing-address').find('[name="city"]'), $(this).closest('.checkout-billing-address').find('[name="country_id"]'), $(this).closest('.checkout-billing-address').find(billingCitySelect), 2);
        });

        $(document).on('change','.billingcityselect',function(){
            showPostcode(this, $(this).closest('.checkout-billing-address').find('[name="postcode"]'), citiDataList, $(this).closest('.checkout-billing-address').find('[name="city"]'));
        });
    });

    function showPostcode(city,postcode,citiDataList, inputCityObj){
        var cityVal = $(city).val();
        let codPostalAdd;
        $.each(citiDataList, function(i,item){
            if(item.localitate === cityVal){
                if(parseInt(item.codPostal) !== 0){
                    codPostalAdd = item.codPostal;
                    if($('.form-shipping-address [name="country_id"]').val() === "RO"){
                        if(codPostalAdd.length === 5){
                            codPostalAdd = "0" + codPostalAdd;
                        }
                    }
                    $(postcode).val(codPostalAdd);
                } else {
                    $(postcode).val("");
                }
                $(postcode).trigger('change').trigger('keyup');
            }
        });
        $(inputCityObj).val(cityVal).trigger('change').trigger('keyup');
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
                let tmpBS = $(country).closest('.checkout-billing-address').find(billingCitySelect);
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
                citiDataList = data['json_data'];
                let htmlAppend = '<option value="">Selectati</option>';
                let localitateTmp = '';
                $.each(citiDataList, function (i, item) {
                    localitateTmp = item.localitate.toString();
                    if(cityObjValTmp === localitateTmp){
                        htmlAppend+='<option value="' + localitateTmp + '" selected>' + item.localitate + '</option>';
                    } else {
                        htmlAppend+='<option value="' + localitateTmp + '">' + item.localitate + '</option>';
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
            let $billingSelect = $(country).closest('.checkout-billing-address').find(billingCitySelect);
            if ($billingSelect.length && $billingSelect.is(":visible")) {
                $billingSelect.hide();
                $(country).closest('.checkout-billing-address').find('[name="city"]').show();
            }
        }
    }

    return function (setShippingInformationAction) {
        return wrapper.wrap(setShippingInformationAction, function (originalAction) {
            return originalAction();
        });
    }
});
