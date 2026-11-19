require([
    'jquery',
    'Magento_Customer/js/customer-data',
    'leaflet',
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/model/cart/totals-processor/default',
    'Magento_Checkout/js/model/cart/cache',
    'Magento_Checkout/js/action/set-shipping-information',
    'uiRegistry',
    'Magento_Catalog/js/price-utils',
    'InnoShip_InnoShip/js/checkout/helpers',
    'InnoShip_InnoShip/js/checkout/courier-address',
    'InnoShip_InnoShip/js/checkout/favorite-locker'
], function ($, customerData, L, quote, customer, defaultTotal, cartCache, setShippingInformationAction, registry, priceUtils, helpers) {

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

    let map;
    let county;
    let pudoSelected;
    let dataJsonExtra;
    let dataJsonCityExtra;
    let dataJsonCourierListExtra;
    let allDataPudo;
    let locations = [];
    let mapLink;
    let courierList;
    let timeDiff = 0;
    let timeDiffRadio = 0;
    let lastCourierListRequest = 0;
    let courierListRetryTimer = null;
    let lastCourierListLocation = '';
    let allDataList;
    let htmlDataListCouriers;
    let dataJsonCountryAlege = '<option value="">Tara</option>';
    let debounceTimer;
    let ignoreMoveEvent = false;
    let dataJsonCounty = '<select id="innopudocounty"><option value="">' + $.mage.__('State') + '</option></select>';
    let dataJsonCity = '<select id="innopudocity"><option value="">' + $.mage.__('City') + '</option></select><div class="searchpudo"><input type="search" id="searchpudovalue" placeholder="' + $.mage.__('Search location ...') + '"><div class="searchpudoresults"></div></div>';
    let dataJsonCourierList = '<div id="innopudocourierlist"></div>';
    let countryList = null; // Will be parsed when needed to avoid race condition

    let iconDisplayMap = [];
    let iconDpd = { iconUrl: require.toUrl('InnoShip_InnoShip/images/pin-dpd.svg'), iconSize: [30, 30] };
    let iconCargus = { iconUrl: require.toUrl('InnoShip_InnoShip/images/pin-cargus.svg'), iconSize: [30, 30] };
    let iconSameday = { iconUrl: require.toUrl('InnoShip_InnoShip/images/pin-emag.svg'), iconSize: [50, 50] };
    let iconFancourier = { iconUrl: require.toUrl('InnoShip_InnoShip/images/pin-fancourier.png'), iconSize: [30, 42] };
    let iconPostapanduri = {
        iconUrl: require.toUrl('InnoShip_InnoShip/images/pin-postapanduri.svg'),
        iconSize: [50, 50]
    };
    let iconPostaromana = { iconUrl: require.toUrl('InnoShip_InnoShip/images/pin-postaromana.svg'), iconSize: [50, 50] };
    let iconGeneral = { iconUrl: require.toUrl('InnoShip_InnoShip/images/pin-general.png'), iconSize: [30, 49] };

    let favoriteSvg = '<svg fill="#000000" height="11px" width="11px" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 455 455" xml:space="preserve"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M326.632,10.346c-38.733,0-74.991,17.537-99.132,46.92c-24.141-29.384-60.398-46.92-99.132-46.92 C57.586,10.346,0,67.931,0,138.714c0,55.426,33.05,119.535,98.23,190.546c50.161,54.647,104.728,96.959,120.257,108.626l9.01,6.769 l9.01-6.768c15.529-11.667,70.098-53.978,120.26-108.625C421.949,258.251,455,194.141,455,138.714 C455,67.931,397.414,10.346,326.632,10.346z M334.666,308.974c-41.259,44.948-85.648,81.283-107.169,98.029 c-21.52-16.746-65.907-53.082-107.166-98.03C61.236,244.592,30,185.717,30,138.714c0-54.24,44.128-98.368,98.368-98.368 c35.694,0,68.652,19.454,86.013,50.771l13.119,23.666l13.119-23.666c17.36-31.316,50.318-50.771,86.013-50.771 c54.24,0,98.368,44.127,98.368,98.368C425,185.719,393.763,244.594,334.666,308.974z"></path> </g></svg>';
    let favoriteSvgOk = '<svg fill="#000000" height="11px" width="11px" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 455 455" xml:space="preserve"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M326.632,10.346c-38.733,0-74.991,17.537-99.132,46.92c-24.141-29.383-60.399-46.92-99.132-46.92 C57.586,10.346,0,67.931,0,138.714c0,55.426,33.049,119.535,98.23,190.546c50.162,54.649,104.729,96.96,120.257,108.626l9.01,6.769 l9.009-6.768c15.53-11.667,70.099-53.979,120.26-108.625C421.95,258.251,455,194.141,455,138.714 C455,67.931,397.414,10.346,326.632,10.346z"></path> </g></svg>';
    let locationSvg = '<svg fill="#000000" version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 395.71 395.71" xml:space="preserve"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g> <path d="M197.849,0C122.131,0,60.531,61.609,60.531,137.329c0,72.887,124.591,243.177,129.896,250.388l4.951,6.738 c0.579,0.792,1.501,1.255,2.471,1.255c0.985,0,1.901-0.463,2.486-1.255l4.948-6.738c5.308-7.211,129.896-177.501,129.896-250.388 C335.179,61.609,273.569,0,197.849,0z M197.849,88.138c27.13,0,49.191,22.062,49.191,49.191c0,27.115-22.062,49.191-49.191,49.191 c-27.114,0-49.191-22.076-49.191-49.191C148.658,110.2,170.734,88.138,197.849,88.138z"></path> </g> </g></svg>';
    let locationSvgLive = '<svg fill="#008000" version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 395.71 395.71" xml:space="preserve"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g> <path d="M197.849,0C122.131,0,60.531,61.609,60.531,137.329c0,72.887,124.591,243.177,129.896,250.388l4.951,6.738 c0.579,0.792,1.501,1.255,2.471,1.255c0.985,0,1.901-0.463,2.486-1.255l4.948-6.738c5.308-7.211,129.896-177.501,129.896-250.388 C335.179,61.609,273.569,0,197.849,0z M197.849,88.138c27.13,0,49.191,22.062,49.191,49.191c0,27.115-22.062,49.191-49.191,49.191 c-27.114,0-49.191-22.076-49.191-49.191C148.658,110.2,170.734,88.138,197.849,88.138z"></path> </g> </g></svg>';

    window.localStorage['Innoshipcourier'] = 0;
    window.localStorage.removeItem('innoshipPud');
    window.localStorage.removeItem('innoshipPudAddress');
    window.localStorage.removeItem('innoshipCourierSelected');
    window.localStorage.removeItem('innoshipCourierSelectedName');
    window.localStorage.removeItem('innoshipPudDescription');
    // The last locker picked in this checkout visit only (see clearStoredPudo()).
    window.localStorage.removeItem('innoshipLastPud');
    window.localStorage.removeItem('innoshipLastPudAddress');
    window.localStorage.removeItem('innoshipLastPudDescription');
    window.timeNow = Math.floor(Date.now() / 1000);
    window.timeNowRadio = window.timeNow;
    window.localStorage['innoshipPudDescription'] = '';
    // N17: after a reload the quote still holds the locker (Model/Checkout/SelectedLockerConfigProvider);
    // put it back in the keys cleared above so initDisplayOnMap() shows it again. It wins over the favourite.
    let innoshipQuoteLocker = window.checkoutConfig && window.checkoutConfig.innoshipSelectedLocker;
    let hasQuoteLocker = !!(innoshipQuoteLocker && parseInt(innoshipQuoteLocker.id, 10) > 0 && innoshipQuoteLocker.address);
    if (hasQuoteLocker) {
        window.localStorage['innoshipPud'] = String(innoshipQuoteLocker.id);
        window.localStorage['innoshipPudAddress'] = innoshipQuoteLocker.address;
    }
    if (typeof window.localStorage['innoshipFirstAddress'] === 'undefined') {
        window.localStorage['innoshipFirstAddress'] = false;
    }

    window.innoshipGpsCurrentLat = null;
    window.innoshipGpsCurrentLong = null;
    window.innoshipLockerFavorite = null;
    window.innoshipLockerFavoriteValue = null;
    window.innoshipLockerFavoriteFirstTime = 0;

    window.stepGetInfo = 0;

    let allDataPudoFavorite = null;

    if (customer.isLoggedIn()) {
        if (customer.customerData) {
            if (customer.customerData.custom_attributes) {
                if (customer.customerData.custom_attributes.favorite_locker_name) {
                    window.innoshipLockerFavoriteValue = customer.customerData.custom_attributes.favorite_locker_name.value;
                    if (!hasQuoteLocker) {
                        window.localStorage['innoshipPudAddress'] = window.innoshipLockerFavoriteValue;
                    }
                }
                // favorite_locker 0 means "no favourite" - loading it threw in setMarkerPudo on every checkout
                if (customer.customerData.custom_attributes.favorite_locker && parseInt(customer.customerData.custom_attributes.favorite_locker.value, 10) > 0) {
                    $.ajax({
                        url: window.BASE_URL + "innoshipf/pudo/getmap",
                        type: "POST",
                        data: {
                            refresh: "1",
                            quote: quote.getQuoteId(),
                            storeId: window.checkout.storeId,
                            ps: customer.customerData.custom_attributes.favorite_locker.value
                        },
                        cache: false
                    }).done(function (data) {
                        if (data['pudoselected']) {
                            allDataPudoFavorite = data['pudoselected'];
                        }

                        window.innoshipLockerFavorite = customer.customerData.custom_attributes.favorite_locker.value;
                        // the locker already on the quote stays (N17); the favourite remains one click away
                        if (hasQuoteLocker) {
                            return;
                        }
                        allDataPudo = allDataPudoFavorite;
                        window.localStorage['innoshipPud'] = window.innoshipLockerFavorite;

                        setMarkerPudo(window.innoshipLockerFavorite, window.innoshipLockerFavoriteValue, false);

                    });
                }
            }
        }
    }

    $(document).ready(function () {
        // Subscribe to shipping method changes via KO. Runs for ALL checkouts
        // (standard + Amasty). Two responsibilities:
        //   1. Always: when switching away from the locker method, clear the
        //      previously stored locker id from the quote on the server. Without
        //      this, the quote keeps the stale innoship_pudo_id and the order is
        //      placed as if the locker were still selected.
        //   2. Amasty only: also reset the shipping address UI, since Amasty has
        //      no "Next" button and the payment section is always visible.
        var isAmastyCheckout = $('#checkout').hasClass('am-checkout');
        // The method already saved on the quote (core DefaultConfigProvider::getSelectedShippingMethod()); the first
        // time the checkout restores it on load there is nothing to save.
        var savedOnLoad = window.checkoutConfig && window.checkoutConfig.selectedShippingMethod;
        var savedOnLoadCode = savedOnLoad ? savedOnLoad.carrier_code + '_' + savedOnLoad.method_code : '';
        var methodAtSubscribe = quote.shippingMethod();
        // Already restored before this subscriber exists: no restore event will come, and the customer's own
        // later pick of that method would have been taken for it and not saved.
        if (methodAtSubscribe && methodAtSubscribe.carrier_code + '_' + methodAtSubscribe.method_code === savedOnLoadCode) {
            savedOnLoadCode = '';
        }
        quote.shippingMethod.subscribe(function (newMethod) {
            if (!newMethod || !newMethod.method_code) {
                return;
            }

            var fullCode = newMethod.carrier_code + '_' + newMethod.method_code;
            var restoredOnLoad = savedOnLoadCode !== '' && fullCode === savedOnLoadCode;

            savedOnLoadCode = '';

            if (fullCode === 'innoshipcargusgo_innoshipcargusgo_1') {
                // Switching TO locker: billing-address-mixin.js immediately fires
                // isAddressSameAsShipping(false), which causes Amasty's
                // isBillingAddressFormVisible() to return true and block
                // validateAndSaveIfChanged. Call setShippingInformationAction directly
                // so the locker method is saved and payment methods are refreshed
                // with the locker-restricted set.
                // Amasty's mixin fires before_shipping_save → shippingRegistry.register(),
                // so Amasty's own subsequent validateAndSaveIfChanged finds nothing
                // unsaved and skips — no duplicate save.
                // Not for the reload restore of a locker already saved: Amasty's validateShipping() then ran before the
                // guest e-mail form existed (jquery.validate "reading 'form'" TypeError, #B2-3).
                if (isAmastyCheckout && !restoredOnLoad) {
                    saveShippingFromForm();
                }

                // A stored locker (e.g. the favourite put back by checkout/favorite-locker.js) is shown by
                // initDisplayOnMap(), which otherwise runs only when the method rows re-render — Amasty
                // does not re-render them on a method change, so the locker line stayed empty.
                showStoredLockerWhenReady();
                return;
            }

            // Switched away from locker — clear the stored pudo id everywhere.
            clearStoredPudo();

            // Courier list: the .fake-click handler never matches on the click that selects the
            // method (Knockout re-renders the rows and detaches the clicked node), and the row
            // observer fires only when the rows are re-rendered. Load it from the method change too
            // (showCourierList() drops a repeat within 1 s). Only on the shipping step.
            if (fullCode === 'innoship_1' && parseInt($('#showcourierlist').val()) === 1
                && ($('#checkout-step-shipping').length === 0 || $('#checkout-step-shipping').is(':visible'))
            ) {
                $('#label_carrier_1_innoshippudom').show();
                $('#label_carrier_1_innoshippudom td').html('Loading...');
                showCourierList();
            }

            if (!isAmastyCheckout) {
                return;
            }

            // Amasty-specific UI reset (no "Next" button to gate this on). A customer with saved
            // addresses keeps the form in the closed New Address pop-up (always :hidden): blanking it
            // made the blank form the shipping address; checkout/courier-address.js restores the card.
            if ($('div[name="shippingAddress.country_id"]').is(':hidden') && !hasSavedAddressCards()) {
                showShippingAddressInformation();
            }
            $('#label_carrier_innoshipcargusgo_1_innoshipcargusgopudom').hide();
            hideCourierList();

            if (customer.isLoggedIn()) {
                $('#checkout-step-shipping').find('.field.addresses').show();
                helpers.newAddressControls(true);
                $('#shipping').find('.step-title').show();
                if ($('.innoship-shipping-address-info-extra').length) {
                    $('.innoship-shipping-address-info-extra').html('').hide();
                }
            }

            // Amasty's validateAndSaveIfChanged may be blocked because the billing
            // form was left open when locker was previously selected (either just the
            // radio, or a confirmed pudo). Call setShippingInformationAction directly
            // to guarantee the new shipping method is saved and payment methods are
            // refreshed for the new (unrestricted) set.
            // Amasty's mixin fires before_shipping_save → shippingRegistry.register(),
            // so Amasty's own subsequent validateAndSaveIfChanged finds nothing
            // unsaved and skips — no duplicate save.
            saveShippingFromForm();
        });

        // a picked courier (setcourierid → get-totals / estimateTotals) changes the charge: show it on the row
        quote.totals.subscribe(showCurierRowPrice);

        // A new locality needs its own courier prices, and nothing else re-requests the list on a city
        // change: the county change sends the old city and the city change sends nothing, so the Curier
        // row stayed empty. Only once a list was shown, for Curier with the list on, on the shipping step.
        quote.shippingAddress.subscribe(function (address) {
            let method = quote.shippingMethod();

            if (!address || !address.city || lastCourierListLocation === ''
                || !method || method.carrier_code + '_' + method.method_code !== 'innoship_1'
                || parseInt($('#showcourierlist').val()) !== 1
                || !($('#checkout-step-shipping').length === 0 || $('#checkout-step-shipping').is(':visible'))
                || courierListLocation(address.countryId, address.regionId, address.city) === lastCourierListLocation
            ) {
                return;
            }
            $('#label_carrier_1_innoshippudom').show();
            $('#label_carrier_1_innoshippudom td').html('Loading...');
            showCourierList();
        });

        /**
         * Amasty: save shipping information the way Amasty's validateAndSaveIfChanged does — first
         * validateShippingInformation(), which copies the inline form (a phone typed after the last
         * estimate included) into quote.shippingAddress, then the save. Calling the save alone sent the
         * stale quote address (empty telephone) and Amasty then saw nothing unsaved.
         */
        var saveShippingDeferred = false;

        function saveShippingFromForm() {
            var shipping;

            // Amasty's validateShipping() (shipping-mixin.js:255) reads registry.get('checkout.errors').messageContainer
            // first: a method change before that component is registered (slow cold load) threw a TypeError. Wait for it,
            // one deferred save at most.
            if (!registry.get('checkout.errors')) {
                if (!saveShippingDeferred) {
                    saveShippingDeferred = true;
                    registry.get('checkout.errors', function () {
                        saveShippingDeferred = false;
                        saveShippingFromForm();
                    });
                }

                return;
            }
            shipping = registry.get('checkout.steps.shipping-step.shippingAddress');

            if (shipping && typeof shipping.validateShippingInformation === 'function'
                && !shipping.validateShippingInformation(true)
            ) {
                return;
            }
            setShippingInformationAction();
        }

        // the row may be inside the added node on the first render (helpers.addedNodeHasRow)
        const addedNodeHasRow = helpers.addedNodeHasRow;

        const observer = new MutationObserver(function (mutations_list) {
            mutations_list.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (added_node) {
                    let hasCourierRow = addedNodeHasRow(added_node, 'tr_method_1_innoship');
                    if (hasCourierRow) {
                        restoreCourierListAfterRender();
                    }
                    timeDiff = Math.floor(Date.now() / 100) - window.timeNow;
                    if (hasCourierRow && timeDiff >= 1) {
                        $('.innshippingmethod').each(function () {
                            if (($(this).prop('checked') || $(this).is(':checked')) && $(this).val() === 'innoship_1' && parseInt($('#showcourierlist').val()) === 1) {
                                $('#label_carrier_1_innoshippudom').show();
                                $('#label_carrier_1_innoshippudom td').html('Loading...');
                                showCourierList();
                            }
                        });
                    }
                    if (addedNodeHasRow(added_node, 'tr_method_innoshipcargusgo_1_innoshipcargusgo') && timeDiff >= 1) {
                        setTimeout(function () {
                            window.timeNow = Math.floor(Date.now() / 1000);
                            initDisplayOnMap();
                            if (parseInt(window.innoshipLockerFavorite) > 1 && window.innoshipLockerFavoriteFirstTime === 0) {
                                $('#label_method_innoshipcargusgo_1_innoshipcargusgo').trigger("click");
                                window.innoshipLockerFavoriteFirstTime = 1;
                            }
                        }, 300);
                    }
                });
            });
        });
        observer.observe(document.querySelector(".checkout-index-index"), { subtree: true, childList: true });

        $('body').on('click', '.innoshippudoclose', function () {
            $('.innoship-mapsection').fadeOut();
            $('.innoship-mapsection-layer').fadeOut();
            removeCloseMap(map);
        });

        $('body').on('click', '#innoshipshowmapb', function () {
            $('.innoship-mapsection').fadeIn(400, function () {
                if (map) {
                    map.invalidateSize();
                }
            });
            $('.innoship-mapsection-layer').fadeIn();
            initMapInno();
            let address = getCurrentAddress();

            let county = address.region;
            let countrySelected = $('div[name="shippingAddress.country_id"]').find("select").val();
            if (county) {
                let cleanedCounty = cleanString(county);
                let cleanedCity = address.city ? cleanString(address.city) : null;

                // Set country and wait for county list to load
                setCountryAndWaitForCounties(address.countryId).then(function() {
                    // County list loaded, now set county and wait for city list
                    return setCountyAndWaitForCities(cleanedCounty);
                }).then(function() {
                    // City list loaded, now set city if available
                    if (cleanedCity) {
                        // Check if the city exists in the dropdown options (case-insensitive)
                        let cityExists = false;
                        let matchedCityValue = null;
                        $('#innopudocity option').each(function() {
                            let optionValue = $(this).val();
                            if (optionValue.toLowerCase() === cleanedCity.toLowerCase()) {
                                cityExists = true;
                                matchedCityValue = optionValue; // Use the exact value from dropdown
                                return false; // break the loop
                            }
                        });

                        if (cityExists && matchedCityValue) {
                            $('#innopudocity').val(matchedCityValue).trigger('change');
                        }
                    }
                }).catch(function(error) {
                    console.error('Error loading address data:', error);
                });
            } else if (countrySelected) {
                $('#innopudocountry').val(countrySelected).trigger("change");
            }
        });

        $('body').on('click', '#innoshipcargusalegeconf', function () {
            $('.innoship-mapsection').fadeOut();
            $('.innoship-mapsection-layer').fadeOut();
            $('#label_carrier_innoshipcargusgo_1_innoshipcargusgopudom').show();
            $('#label_method_innoshipcargusgo_1_innoshipcargusgo').trigger("click");
            removeCloseMap(map);
            // The locker is written onto the quote shipping address by the setpudo controller.
            // Filling the form below makes the checkout save shipping information, so that request
            // must not start before setpudo has answered.
            setPudoValue().always(function () {
                displayPudoLocation(window.localStorage['innoshipPud'], window.localStorage['innoshipPudAddress'], false, false);
            });
            if (!$('#checkout').hasClass('am-checkout')) {
                cartCache.set('totals', null);
                // loadFromServer() posts _.pick(address, ...): with no address the totals are priced for an empty one
                defaultTotal.estimateTotals(quote.shippingAddress());
            }
        });

        $('body').on("click", ".fake-click", function (e) {

            if (e.target.id === 'label_method_innoshipcargusgo_1_innoshipcargusgo') {
                hideCourierList();
                return false;
            }
            if (e.target.id == 'innopudocounty') {
                $('#innopudocounty').focus();
                return false;
            } else {

                // Try to find the radio input - first as child, then as sibling
                let radioVal = $(this).find('.innshippingmethod');
                if (radioVal.length === 0) {
                    // If not found as child, look for sibling radio in the same parent
                    radioVal = $(this).siblings('.innshippingmethod');
                }
                if (radioVal.length === 0) {
                    // If still not found, try parent's radio input
                    radioVal = $(this).closest('td, div').find('.innshippingmethod');
                }

                if (radioVal.length > 0 && radioVal.val() === 'innoship_1' && parseInt($('#showcourierlist').val()) === 1) {
                    $('#label_carrier_1_innoshippudom').show();
                    $('#label_carrier_1_innoshippudom td').html('Loading...');

                } else {
                    hideCourierList();
                }
                timeDiffRadio = Math.floor(Date.now() / 100) - window.timeNowRadio;
                let clickOnCourierList = false;
                if (radioVal.length > 0) {
                    if (radioVal.hasClass('innoship-courier-ids')) {
                        clickOnCourierList = true;
                    }
                }

                if ($(this).hasClass('pudo-success')) {
                    clickOnCourierList = true;
                }

                if (radioVal.length > 0 && radioVal.val() !== "innoshipcargusgo_innoshipcargusgo_1" && timeDiffRadio > 1 && clickOnCourierList === false) {
                    window.timeNowRadio = Math.floor(Date.now() / 100);
                    if (!customer.isLoggedIn()) {
                        if ($('div[name="shippingAddress.country_id"]').is(":hidden")) {
                            showShippingAddressInformation();
                        }
                    } else {
                        // a customer without saved addresses types into the visible inline form -
                        // clear it only when a locker hid it, as for guests; never the pop-up form
                        if ($('div[name="shippingAddress.country_id"]').is(":hidden") && !hasSavedAddressCards()) {
                            showShippingAddressInformation();
                        }
                        $('#checkout-step-shipping').find('.field.addresses').show();
                        helpers.newAddressControls(true);
                        $.each($(".shipping-address-item"), function () {
                            $(this).children('.action-select-shipping-item').show();
                        });
                        if ($('.innoship-shipping-address-info-extra').length) {
                            $('.innoship-shipping-address-info-extra').html("").hide();
                        }
                        $('#shipping').find('.step-title').show();

                        $('#checkout-step-shipping').find('.field.addresses').show();
                        helpers.newAddressControls(true);
                    }

                    $('#label_carrier_innoshipcargusgo_1_innoshipcargusgopudom').hide();
                    setMarkerPudo(null, null);
                    setTimeout(function () {
                        initDisplayOnMap();
                        $.ajax({
                            url: window.BASE_URL + "innoshipf/pudo/getmap",
                            type: "POST",
                            data: {
                                refresh: "1",
                                quote: quote.getQuoteId(),
                                storeId: window.checkout.storeId,
                                courier: window.localStorage['Innoshipcourier']
                            },
                            cache: false
                        }).done(function (data) {
                            if (data['json_data']) {
                                allDataPudo = data['json_data'];
                            }
                            if (data['county']) {
                                county = data['county'];
                            }
                            if (data['pudoselected']) {
                                pudoSelected = data['pudoselected'];
                            }
                            if (data['courierList']) {
                                courierList = data['courierList'];
                            }

                            dataJsonExtra = '<option value="">' + $.mage.__('State') + '</option>';
                            $.each(county, function (i, item) {
                                // SECURITY: Escape HTML to prevent XSS
                                dataJsonExtra += '<option value="' + escapeHtml(i) + '">' + escapeHtml(i) + '</option>';
                            });
                            $('#innopudocounty').html(dataJsonExtra);
                            $('#innopudocity').html('');
                            dataJsonCourierListExtra = '';
                            $.each(courierList, function (i, item) {
                                // SECURITY: Escape HTML to prevent XSS
                                dataJsonCourierListExtra += '<div class="courier-list-row"><input type="checkbox" name="courierl" value="' + escapeHtml(i) + '" checked><span>' + escapeHtml(item) + '</span></div>';
                            });
                            $('#innopudocourierlist').html(dataJsonCourierListExtra);
                        });
                    }, 300);



                    if (radioVal.length > 0 && radioVal.val() === 'innoship_1' && parseInt($('#showcourierlist').val()) === 1) {

                        showCourierList();
                    }
                }
            }
        });

        $("body").on("click", ".innoship-courier-iditem", function () {
            let radioSelectedCourierId = $(this).find('.innoship-courier-ids');
            let courierSelected = $(radioSelectedCourierId).val();
            let nameCourierSelected = $(radioSelectedCourierId).data('name');
            let priceSelected = $(radioSelectedCourierId).data('price');

            window.localStorage['innoshipCourierSelected'] = courierSelected;
            window.localStorage['innoshipCourierSelectedName'] = nameCourierSelected;

            // Update shipping price
            updateShippingPriceWithCourier(courierSelected, priceSelected);
        });

        $("body").on("click", "#shipping-method-buttons-container button", function () {
            if (quote.shippingMethod().method_code === "innoshipcargusgo_1") {
                if (parseInt(window.localStorage['innoshipPud']) > 1) {
                    setTimeout(function () {
                        // Uncheck "same as shipping" checkbox
                        if ($('#billing-address-same-as-shipping').length) {
                            if ($('#billing-address-same-as-shipping').is(':checked')) {
                                $('#billing-address-same-as-shipping').trigger('click').trigger('change');
                            }
                        } else if ($('#billing-address-same-as-shipping-shared').length) {
                            if ($('#billing-address-same-as-shipping-shared').is(':checked')) {
                                $('#billing-address-same-as-shipping-shared').trigger("click").trigger("change");
                            }
                        } else if ($('input[name="billing-address-same-as-shipping"]').length) {
                            if ($('input[name="billing-address-same-as-shipping"]').is(':checked')) {
                                $('input[name="billing-address-same-as-shipping"]').trigger('click').trigger('change');
                            }
                        }
                        // the billing form vs saved card is decided by billing-address-mixin (KO visible binding);
                        // a jQuery show() here opened the form under the card
                        $('.billing-address-same-as-shipping-block').hide();
                    }, 1000);
                    return true;
                } else {
                    $('#label_carrier_innoshipcargusgo_1_innoshipcargusgopudom').html('<td class="col col-pudo-message" colspan="4"><b style="color: red;">Va rugam selectati o locatie</b></td>');
                    $('#label_carrier_innoshipcargusgo_1_innoshipcargusgopudom').show();
                    return false;
                }
            } else {
                return true;
            }
        });

        $("body").on("click", ".courier-list-row input", function () {
            let listCurieri_tmp = [];
            let defaultLat;
            let defaultLong;
            window.localStorage['Innoshipcourier'] = '';
            $("input[name='courierl']:checked").each(function () {
                window.localStorage['Innoshipcourier'] += $(this).val() + ",";
                listCurieri_tmp.push($(this).val());
            });
            window.localStorage['Innoshipcourier'] = window.localStorage['Innoshipcourier'].slice(0, -1);

            locations = [];
            var thisCity = $('#innopudocity').val();
            $.each(allDataPudo, function (i, item) {
                if (item['localityName'] === thisCity && listCurieri_tmp.includes(item['courierId'])) {
                    locations.push(['' + item['addressText'] + '', item['latitude'], item['longitude'], item['pudo_id'], item['localityName'], item['countyName'], item['postalCode'], item['courierId']]);
                } else if (item['localityName'] === thisCity) {
                    defaultLat = item['latitude'];
                    defaultLong = item['longitude']
                }
            });

            removeCloseMap(map);

            setTimeout(function () {
                initIcons();

                // If we have locations from a previous search, we might want to stay centered there?
                // But loadLockersByRadius uses the *current* map center.
                // If the map is already initialized, loadLockersByRadius will use its center.
                // If not, we need to initialize it first.

                if (!map) {
                    let lat = 45.943161; // Default fallback
                    let lng = 24.96676;
                    let zoom = 10;

                    if (locations.hasOwnProperty(0) && typeof locations[0] !== "undefined") {
                        lat = locations[0][1];
                        lng = locations[0][2];
                        zoom = 12;
                    } else if (typeof defaultLat !== 'undefined' && typeof defaultLong !== 'undefined') {
                        lat = defaultLat;
                        lng = defaultLong;
                    }
                    initializeMap(lat, lng, zoom);
                }

                // Trigger server-side search with new filters
                loadLockersByRadius();
            }, 300);
        });

        $("body").on("keyup", "#searchpudovalue", function () {
            $('.searchpudoresults').show();
            let valueSearch = $.trim($(this).val());
            let cityToSearchIn = $('#innopudocity').val();
            let heiStack;
            let tmpLocationString = '';
            if (valueSearch.length > 0) {
                let listFindSuccess = '';
                $.each(allDataPudo, function (i, item) {
                    //if (item['localityName'] === cityToSearchIn) {
                    heiStack = item['name'].toLowerCase();
                    // plain substring match - search() took the term as a regex and '> 0' skipped a match at the start of the name
                    if (heiStack.indexOf(valueSearch.toLowerCase()) !== -1) {
                        // street, city, county, postcode - the order fillShippingAddressInformation() reads
                        tmpLocationString = "" + String(item['addressText']).replace(/,/g, ' ') + ", " + item['localityName'] + ", " + item['countyName'] + ", " + item['postalCode'];
                        // SECURITY: Escape HTML to prevent XSS
                        listFindSuccess += '<div class="pudosearchitemfound" data-pfid="' + escapeHtml(item['pudo_id']) + '" data-pfidl="' + escapeHtml(tmpLocationString) + '">' + escapeHtml(item['name']) + '</div>';
                    }
                    //}
                });

                if (listFindSuccess.length > 0) {
                    // SECURITY: Data already escaped in loop below, safe to insert
                    $(".searchpudoresults").html(listFindSuccess);
                }
            } else {
                $(".searchpudoresults").html('');
            }
        });

        $('body').on("click", ".pudosearchitemfound", function () {
            let pudoIDU = $(this).data('pfid');
            if (allDataPudo[pudoIDU]) {
                map.setView([allDataPudo[pudoIDU]['latitude'], allDataPudo[pudoIDU]['longitude']], 17);
            }
            setMarkerPudo($(this).data('pfid'), $(this).data('pfidl'), true);
            $('#searchpudovalue').val('').trigger("click");
            $('.searchpudoresults').hide();
        });

        $('body').on("click", ".checkout-billing-address .action-update", function () {
            window.localStorage['innoshipFirstAddress'] = true;
        });

        $('body').on("click", '.billing-address-same-as-shipping-block', function () {
            if (window.localStorage['innoshipFirstAddress'] === "false") {
                window.localStorage['innoshipFirstAddress'] = true;
                // $(this).find("input").attr('checked', 'checked');
                // $(this).find("input").trigger("click");
                // $(this).find("input").trigger("change");
            }
        });

        // Change country - get county
        $('body').on("change", "#innopudocountry", function () {
            $.ajax({
                url: window.BASE_URL + "innoshipf/pudo/getmap",
                type: "POST",
                data: {
                    refresh: "1",
                    quote: quote.getQuoteId(),
                    storeId: window.checkout.storeId,
                    country: $(this).val()
                },
                cache: false
            }).done(function (data) {
                // Check if response contains error
                if (data && data.error) {
                    console.error('InnoShip Getmap error:', data.error);
                    // Still show empty dropdown to prevent timeout
                    setCountyList({county: []});
                } else if (data && data.county) {
                    setCountyList(data);
                    $('#innopudocity').hide();
                    $('#innopudocourierlist').hide();
                    $('#searchpudo').hide();
                } else {
                    console.error('InnoShip Getmap: Invalid response format', data);
                    setCountyList({county: []});
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error('InnoShip Getmap AJAX failed:', textStatus, errorThrown);
                // Show empty dropdown to prevent timeout
                setCountyList({county: []});
            });
        });

        // Change county - get city's
        $('body').on("change", "#innopudocounty", function () {
            $.ajax({
                url: window.BASE_URL + "innoshipf/pudo/getmap",
                type: "POST",
                data: {
                    refresh: "1",
                    quote: quote.getQuoteId(),
                    storeId: window.checkout.storeId,
                    county: $(this).val()
                },
                cache: false
            }).done(function (data) {
                // Check if response contains error
                if (data && data.error) {
                    console.error('InnoShip Getmap error:', data.error);
                    setLocalityList({locality: []});
                } else if (data && data.locality) {
                    setLocalityList(data);
                } else {
                    console.error('InnoShip Getmap: Invalid response format', data);
                    setLocalityList({locality: []});
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error('InnoShip Getmap AJAX failed:', textStatus, errorThrown);
                setLocalityList({locality: []});
            });
        });

        $('body').on("change", "#innopudocity", function () {
            $.ajax({
                url: window.BASE_URL + "innoshipf/pudo/getmap",
                type: "POST",
                data: {
                    refresh: "1",
                    quote: quote.getQuoteId(),
                    storeId: window.checkout.storeId,
                    locality: $(this).val()
                },
                cache: false
            }).done(function (data) {
                setLockerList(data['json_data']);
                showCourierListPudo(data['courierList']);
                window.stepGetInfo = 3;
            });
        });

        setTimeout(function () {
            if (!$('#innoshipshowmapb').length) {
                initDisplayOnMap();
            }
        }, 1500);

        $("body").on("click", "#locatiamea", function () {
            getGPSCoordinates(2);
        });

        $("body").on("click", "#innoshipsalveazafavorit", function () {
            if (customer.isLoggedIn()) {
                $.ajax({
                    url: window.BASE_URL + "innoshipf/account/savelocker",
                    type: "POST",
                    data: {
                        favorite_locker_customerId: customer.customerData.id,
                        favorite_locker_storeId: customer.customerData.store_id,
                        refresh: "1",
                        favorite_locker: window.localStorage['innoshipPud'],
                        favorite_locker_name: window.localStorage['innoshipPudAddress'],
                        form_key: $.mage.cookies.get('form_key')
                    },
                    cache: false
                }).done(function (data) {
                    if (data['success'] === true) {
                        $('.svg-favorit').html(favoriteSvgOk);

                        window.innoshipLockerFavorite = window.localStorage['innoshipPud'];
                        window.innoshipLockerFavoriteValue = window.localStorage['innoshipPudAddress'];

                        setTimeout(function () {
                            $('#innoshipcargusalegeconf').trigger("click");
                        }, 200);
                    }
                });
            } else {

            }
        });

        $("body").on("click", ".alege-locker-favorit-click", function () {
            window.localStorage['innoshipPud'] = window.innoshipLockerFavorite;
            window.localStorage['innoshipPudAddress'] = window.innoshipLockerFavoriteValue;

            setTimeout(function () {
                $('#innoshipcargusalegeconf').trigger("click");
            }, 200);
        });
    });

    function getCurrentAddress() {
        let city = quote.shippingAddress().city;
        let addressId = quote.shippingAddress().customerAddressId;
        let address = [];
        if (typeof city !== 'undefined' && typeof addressId != 'undefined') {
            address = quote.shippingAddress();

        } else {
            let cityAddress = $('div[name="shippingAddress.city"]').find('select').val();
            if (!cityAddress) {
                cityAddress = $('div[name="shippingAddress.city"]').find('input').val();
            }
            address = {
                city: cityAddress,
                region: $('div[name="shippingAddress.region_id"]').find('select  option:selected').text(),
                countryId: $('div[name="shippingAddress.country_id"]').find('select').val(),
                street: $('div[name="shippingAddress.street.0"]').find('textarea').val()
            };
        }
        return address;
    }

    /**
     * Show the stored locker once both preconditions hold: the Locker radio is checked (Amasty checks
     * it after the method change is published) and a locker id + address are stored (the favourite
     * may be put back a moment later). Idempotent: does nothing when the locker line is already
     * filled; stops after ~5 s.
     * ponytail: short poll — neither precondition raises an event this file can subscribe to.
     */
    function showStoredLockerWhenReady() {
        let attempts = 0;
        let timer = setInterval(function () {
            attempts++;
            let lockerChecked = $('#tr_method_innoshipcargusgo_1_innoshipcargusgo input[type="radio"]').is(':checked');
            let stored = parseInt(window.localStorage['innoshipPud']) > 0 && !!window.localStorage['innoshipPudAddress']
                && window.localStorage['innoshipPudAddress'] !== 'null' && window.localStorage['innoshipPudAddress'] !== 'undefined';
            let $line = $('#label_carrier_innoshipcargusgo_1_innoshipcargusgopudom');
            let shown = $line.is(':visible') && $.trim($line.find('td').text()) !== '';
            let method = quote.shippingMethod();
            let stillLocker = method && method.carrier_code === 'innoshipcargusgo';
            // The customer opened the map to choose: a marker click stores the locker before Confirm, and
            // initDisplayOnMap() would rebuild the open panel and apply that unconfirmed pick (IS47b).
            let choosingOnMap = $('.innoship-mapsection').is(':visible');

            if (!stillLocker || shown || choosingOnMap || attempts > 20) {
                clearInterval(timer);
                return;
            }
            if (lockerChecked && stored) {
                clearInterval(timer);
                initDisplayOnMap();
            }
        }, 250);
    }

    function initDisplayOnMap() {
        // The customer has the map open: rebuilding replaced it with a hidden copy (Amasty re-renders the method row
        // ~2.5 s after Locker is chosen, then the row observer and the stored-locker poll both land here - an "Alege"
        // click in between opened a panel that vanished, IS37), and the stored locker may be an unconfirmed marker
        // pick (IS47b). A re-rendered row has a new, empty label, so this never skips a needed first render.
        if ($('#label_carrier_innoshipcargusgo_1_innoshipcargusgo .innoship-mapsection').is(':visible')) {
            return;
        }

        // Load countryList lazily to avoid race condition
        // window.allowCountryLocker is already a JavaScript object, not a JSON string
        if (countryList === null && window.allowCountryLocker) {
            countryList = window.allowCountryLocker;
        }

        let dataCountryHtml = dataJsonCountryAlege;
        $.each(countryList || {}, function (i, item) {
            dataCountryHtml += '<option value="' + i + '">' + item + '</option>';
        });
        $('#label_carrier_innoshipcargusgo_1_innoshipcargusgo').html('' +
            '<div class="innoshipcargusgodiv" xmlns="http://www.w3.org/1999/html">' +
            '   <div id="innoshipshowmapb">' + $.mage.__('Select') + '</div>' +
            '</div>' +
            '<div class="innoship-mapsection-layer">' +
            '   <div class="innoship-mapsection">' +
            '       <div class="innopudo-section-left">' +
            '           <div class="innopudoheader"><div class="alege-locker-favorit-click">' + favoriteSvgOk + ' ' + escapeHtml(window.innoshipLockerFavoriteValue || '') + '</div></div>' +
            '           <div class="innopudoheader"><div id="locatiamea">' + locationSvg + '</div>' + $.mage.__('Select shipping address') + '</div>' +
            '           <div class="searchmap">' +
            '               <select id="innopudocountry">' + dataCountryHtml + '</select>'
            + dataJsonCounty
            + dataJsonCity
            + dataJsonCourierList +
            '               <div class="innomapliv"><b>' + $.mage.__('Shipping point selected') + '</b> ...</div>' +
            '               <div id="innoshipcargusalegeconf">' + $.mage.__('Confirm') + '</div>' +
            '               <div id="innoshipsalveazafavorit"><span class="svg-favorit">' + favoriteSvg + '</span> ' + $.mage.__('Save favorite locker and chose') + '</div>' +
            '               <div id="innoship-map-loading" style="display:none; text-align:center; padding:10px;">' +
            '                   <style>' +
            '                       .innoship-loader { width: 50px; aspect-ratio: 1; border-radius: 50%; border: 8px solid #514b82; animation: l20-1 0.8s infinite linear alternate, l20-2 1.6s infinite linear; margin: 0 auto; }' +
            '                       @keyframes l20-1{ 0% {clip-path: polygon(50% 50%,0 0, 50% 0%, 50% 0%, 50% 0%, 50% 0%, 50% 0% )} 12.5% {clip-path: polygon(50% 50%,0 0, 50% 0%, 100% 0%, 100% 0%, 100% 0%, 100% 0% )} 25% {clip-path: polygon(50% 50%,0 0, 50% 0%, 100% 0%, 100% 100%, 100% 100%, 100% 100% )} 50% {clip-path: polygon(50% 50%,0 0, 50% 0%, 100% 0%, 100% 100%, 50% 100%, 0% 100% )} 62.5% {clip-path: polygon(50% 50%,100% 0, 100% 0%, 100% 0%, 100% 100%, 50% 100%, 0% 100% )} 75% {clip-path: polygon(50% 50%,100% 100%, 100% 100%, 100% 100%, 100% 100%, 50% 100%, 0% 100% )} 100% {clip-path: polygon(50% 50%,50% 100%, 50% 100%, 50% 100%, 50% 100%, 50% 100%, 0% 100% )} }' +
            '                       @keyframes l20-2{ 0% {transform:scaleY(1) rotate(0deg)} 49.99%{transform:scaleY(1) rotate(135deg)} 50% {transform:scaleY(-1) rotate(0deg)} 100% {transform:scaleY(-1) rotate(-135deg)} }' +
            '                   </style>' +
            '                   <div class="innoship-loader"></div>' +
            '               </div>' +
            '           </div>' +
            '       </div>' +
            '       <div id="mapview"></div>' +
            '       <div class="innoshippudoclose">x</div>' +
            '   </div>' +
            '</div>');
        if (parseInt(window.innoshipLockerFavorite) > 0) {
            $('.alege-locker-favorit-click').show();
        }
        if ($('#tr_method_innoshipcargusgo_1_innoshipcargusgo input[type="radio"]').is(":checked") && window.localStorage['innoshipPud']) {
            // no stored locker (clearStoredPudo leaves innoshipPud '0' and no address) - displaying it threw
            // on pudoLocation.split / allDataPudo[pudoId] and wrote "Punct de livrare ales: undefined"
            if (parseInt(window.localStorage['innoshipPud']) > 0 && window.localStorage['innoshipPudAddress'] && window.localStorage['innoshipPudAddress'] !== 'null' && window.localStorage['innoshipPudAddress'] !== 'undefined') {
                $.ajax({
                    url: window.BASE_URL + "innoshipf/pudo/getmap",
                    type: "POST",
                    data: {
                        refresh: "1",
                        quote: quote.getQuoteId(),
                        storeId: window.checkout.storeId,
                        ps: window.localStorage['innoshipPud']
                    },
                    cache: false
                }).done(function (data) {
                    allDataPudo = data['pudoselected'];
                    // the customer may have left the Locker method before this answer arrived
                    if (!$('#tr_method_innoshipcargusgo_1_innoshipcargusgo input[type="radio"]').is(":checked")) {
                        return;
                    }
                    displayPudoLocation(window.localStorage['innoshipPud'], window.localStorage['innoshipPudAddress'], false, true);
                });
            }
        }
    }

    function initMapInno() {
        initIcons();
        initializeMap(45.943161, 24.96676, 7);
    }

    function removeCloseMap(map) {
        if (map) {
            setTimeout(function () {
                map.off();
            }, 150);
        }
    }

    function displayPudoLocation(pudoId, pudoLocation, clickFromMap = false, initDisplayPudo = false) {
        if (pudoId) {
            window.localStorage['innoshipPud'] = pudoId;
            window.localStorage['innoshipPudAddress'] = pudoLocation;
            if (allDataPudo) {
                if (allDataPudo[pudoId]) {
                    window.localStorage['innoshipPudDescription'] = allDataPudo[pudoId]['infoShow'];
                }
            }

            if (clickFromMap === false) {
                // SECURITY NOTE: infoShow comes from trusted backend and contains intentional HTML formatting
                // Only escape the user-provided location data
                var pudoDescription = window.localStorage['innoshipPudDescription']; // Contains safe HTML from backend
                var escapedPudoLocation = escapeHtml(pudoLocation);
                $('#label_carrier_innoshipcargusgo_1_innoshipcargusgopudom').html('<td class="col col-pudo-message" colspan="4"><b>' + $.mage.__('Shipping point selected') + '</b> ' + pudoDescription + " <br/><b>" + $.mage.__('Address:') + "</b> " + escapedPudoLocation + '</td>');

                // IMPORTANT: When locker is selected, billing and shipping must be different
                // Uncheck "billing-address-same-as-shipping" checkbox
                if ($('#billing-address-same-as-shipping').length) {
                    // Only click if it's currently checked (to uncheck it)
                    if ($('#billing-address-same-as-shipping').is(':checked')) {
                        $('#billing-address-same-as-shipping').trigger('click').trigger('change');
                    }
                } else if ($('#billing-address-same-as-shipping-shared').length) {
                    if ($('#billing-address-same-as-shipping-shared').is(':checked')) {
                        $('#billing-address-same-as-shipping-shared').trigger("click").trigger("change");
                    }

                    if (initDisplayPudo === true && parseInt(window.localStorage['innoshipPud']) <= 0) {
                        $('#billing-address-same-as-shipping-shared').trigger("click").trigger("change");
                    }
                } else if ($('input[name="billing-address-same-as-shipping"]').length) {
                    // Only click if it's currently checked (to uncheck it)
                    if ($('input[name="billing-address-same-as-shipping"]').is(':checked')) {
                        $('input[name="billing-address-same-as-shipping"]').trigger('click').trigger('change');
                    }
                }

                // One path for guest and logged-in. The old code ran the fill only for a logged-in
                // customer whose customerData 'customer' section reported no addresses — a section
                // that never carries an addresses key at all (Magento\Customer\CustomerData\Customer
                // returns fullname, firstname, websiteId), so that branch never decided what it was
                // written to decide, and a customer with saved addresses kept their own address on
                // the shipping form.
                fillShippingAddressInformation(pudoId);

                // debug Address information selected by easybox
                // $('div[name="shippingAddress.country_id"]').show();
                // $('div[name="shippingAddress.city"]').show();
                // $('div[name="shippingAddress.street.0"]').closest('.street').show();
                // $('div[name="shippingAddress.postcode"]').show();
                // $('div[name="shippingAddress.region_id"]').show();

                if (customer.isLoggedIn()) {
                    $('#checkout-step-shipping').find('.field.addresses').hide();
                    helpers.newAddressControls(false);
                    $('#shipping').find('.step-title').hide();
                    $('#shipping').find('.checkout-billing-address').find('.step-title').show();
                }

                $('#label_carrier_innoshipcargusgo_1_innoshipcargusgopudom').show();

                if (parseInt(window.localStorage['innoshipPud']) > 0) {
                    // form vs saved card: billing-address-mixin (KO visible binding), not a jQuery show()
                    $('.billing-address-same-as-shipping-block').hide();
                    helpers.selectSavedBillingAddress();
                }
            }
        }
    }

    /**
     * Store the selected locker on the quote. Returns the request so callers can wait for the
     * locker to be stored before triggering a shipping-information save.
     */
    function setPudoValue() {
        return $.ajax({
            url: window.BASE_URL + "innoshipf/pudo/setpudo",
            type: "POST",
            data: {
                refresh: "1",
                quote: quote.getQuoteId(),
                pudoid: window.localStorage['innoshipPud'],
                form_key: $.mage.cookies.get('form_key')
            },
            cache: false
        });
    }

    /**
     * Clear any previously stored locker selection from localStorage AND from
     * the server-side quote. Called when the customer switches from the locker
     * shipping method to a non-locker method, so the order is not placed with
     * a stale innoship_pudo_id. The controller accepts pudoid=0 as the
     * explicit "clear" sentinel.
     */
    function clearStoredPudo() {
        // Nothing to clear if no locker was ever selected
        var current = window.localStorage['innoshipPud'];
        // !(> 0), not <= 0: a "null" string (setMarkerPudo(null) on plain checkout) parses to NaN, passed this check
        // and overwrote the remembered locker with "null" (#B2-7)
        if (typeof current === 'undefined' || current === null || current === '' || !(parseInt(current, 10) > 0)) {
            // Still reset the local UI state in case description lingered
            window.localStorage['innoshipPud'] = '0';
            window.localStorage.removeItem('innoshipPudAddress');
            window.localStorage.removeItem('innoshipPudDescription');
            return;
        }

        // Remember the pick: going back to Locker in this visit restores it (checkout/favorite-locker.js)
        // before the customer's favourite.
        window.localStorage['innoshipLastPud'] = current;
        window.localStorage['innoshipLastPudAddress'] = window.localStorage['innoshipPudAddress'] || '';
        window.localStorage['innoshipLastPudDescription'] = window.localStorage['innoshipPudDescription'] || '';

        window.localStorage['innoshipPud'] = '0';
        window.localStorage.removeItem('innoshipPudAddress');
        window.localStorage.removeItem('innoshipPudDescription');

        // setPudoValue() reads from localStorage, so it will now POST pudoid=0
        setPudoValue();
    }

    function cleanString(stringReplace) {
        return stringReplace.replace("Ă", "A").replace("ă", "a").replace("Â", "A").replace("â", "a").replace("Î", "I").replace("î", "i").replace("Ș", "S").replace("Ş", "S").replace("ș", "s").replace("ş", "s").replace("Ț", "T").replace("ț", "t").replace("ţ", "t").replace("ă", "a");
    }

    function updateShippingPriceWithCourier(courierId, courierPrice) {
        $.ajax({
            url: window.BASE_URL + "innoshipf/courier/setcourierid",
            type: "POST",
            data: {
                refresh: "1",
                quote: quote.getQuoteId(),
                storeId: window.checkout.storeId,
                cid: courierId,
                price: courierPrice,
                form_key: $.mage.cookies.get('form_key')
            },
            cache: false
        }).done(function (data) {
            // Show selected courier message with price
            // SECURITY: Escape HTML to prevent XSS
            let escapedCourierName = escapeHtml(window.localStorage['innoshipCourierSelectedName']);
            let courierMessage = '<div class="courier-selected-message"><b>' + $.mage.__('Selected courier:') + '</b> ' + escapedCourierName;

            // The server charges the price it quoted (Model/CourierPriceStore), so show that one
            let shownPrice = data && data.price !== null && data.price !== undefined ? data.price : courierPrice;

            // Add price if available
            if (shownPrice !== null && shownPrice !== undefined && shownPrice !== '') {
                courierMessage += ' - ' + escapeHtml(shownPrice) + ' ' + quote.totals().quote_currency_code;
            }
            courierMessage += '</div>';

            $('#label_carrier_1_innoshippudom td').html(courierMessage);

            // Recalculate shipping totals
            if (!$('#checkout').hasClass('am-checkout')) {
                cartCache.set('totals', null);
                // loadFromServer() posts _.pick(address, ...): with no address the totals are priced for an empty one
                defaultTotal.estimateTotals(quote.shippingAddress());
            } else {
                // Amasty: the quote on the server already holds the courier price; read the totals
                // back (no re-estimate) so the sidebar shows what the order will charge.
                require(['Magento_Checkout/js/action/get-totals'], function (getTotalsAction) {
                    getTotalsAction([]);
                });
            }
        });
    }

    /**
     * Curier row price: showCourierList() puts a "..." placeholder there and nothing rendered a price back.
     * Show the picked courier's price (the one Setcourierid charges) when the list on screen is priced for
     * this address, the Curier rate otherwise. Not the quote totals: the core checkout keeps their
     * shipping_amount at 0 until the shipping-information save.
     */
    function showCurierRowPrice() {
        let method = quote.shippingMethod();
        let address = quote.shippingAddress();
        let picked = allDataList ? allDataList[window.localStorage['innoshipCourierSelected']] : null;
        let price = window.checkoutConfig.isDisplayShippingPriceExclTax ? method && method.price_excl_tax
            : method && method.price_incl_tax;

        if (!method || method.carrier_code + '_' + method.method_code !== 'innoship_1') {
            return;
        }
        if (picked && typeof picked === 'object' && picked.price !== null && picked.price !== undefined
            && address && courierListLocation(address.countryId, address.regionId, address.city) === lastCourierListLocation
        ) {
            price = picked.price;
        }
        if (price === null || price === undefined || price === '') {
            return;
        }
        $('#tr_method_1_innoship').find('.col-price .price')
            .text(priceUtils.formatPrice(price, quote.getPriceFormat()));
    }

    /**
     * The address a courier list is priced for: country + county + city, as Model\CourierPriceStore::locationKey().
     * The county matters on its own: Ineu, Arad and Ineu, Bihor have different prices.
     */
    function courierListLocation(countryCode, regionId, city) {
        return (countryCode || '') + '|' + (parseInt(regionId, 10) || 0) + '|' + $.trim(city || '').toLowerCase();
    }

    function showCourierList() {
        // Three triggers (method change, row re-render, .fake-click) can fire for one selection.
        if (Date.now() - lastCourierListRequest < 1000) {
            // ...but an address change inside that second (the quote leaving a locker's county right
            // after the list was asked for) must still get its own list: retry once after the window.
            if (!courierListRetryTimer) {
                courierListRetryTimer = setTimeout(function () {
                    let address = quote.shippingAddress();
                    let method = quote.shippingMethod();

                    courierListRetryTimer = null;
                    if (address && method && method.carrier_code + '_' + method.method_code === 'innoship_1'
                        && courierListLocation(address.countryId, address.regionId, address.city)
                        !== lastCourierListLocation
                    ) {
                        showCourierList();
                    }
                }, 1000 - (Date.now() - lastCourierListRequest));
            }

            return;
        }
        lastCourierListRequest = Date.now();

        let pricePlaceholderTimer = setTimeout(function () {
            // Show loading indicator on shipping method price
            let $priceElement = $('#tr_method_1_innoship').find('.col-price .price');
            if ($priceElement.length === 0) {
                // Try alternative selectors
                $priceElement = $('input[value="innoship_1"]').closest('tr').find('.col-price .price');
            }
            if ($priceElement.length === 0) {
                // Try another alternative
                $priceElement = $('#label_method_1_innoship').closest('tr').find('.col-price .price');
            }
            if ($priceElement.length > 0) {
                $priceElement.html('...');

            } else {

            }
        }, 500);


        // Get shipping address country code
        let shippingAddress = quote.shippingAddress();
        let countryCode = shippingAddress ? shippingAddress.countryId : null;
        let region = shippingAddress ? shippingAddress.region : null;
        let regionId = shippingAddress ? shippingAddress.regionId : null;
        let city = shippingAddress ? shippingAddress.city : null;

        // Fallback: region id from the form, read with the region name below
        if (!regionId) {
            regionId = $('div[name="shippingAddress.region_id"]').find('select').val() || null;
        }

        // Fallback: get country from form if not available in quote
        if (!countryCode) {
            let countrySelect = $('div[name="shippingAddress.country_id"]').find('select');
            if (countrySelect.length > 0) {
                countryCode = countrySelect.val();
            }
        }

        // Fallback: get region from form if not available in quote
        if (!region) {
            let regionSelect = $('div[name="shippingAddress.region_id"]').find('select');
            if (regionSelect.length > 0) {
                // Get selected option text (region name) not value (region ID)
                region = regionSelect.find('option:selected').text();
            }
            // If still no region, try input field
            if (!region || region === '') {
                let regionInput = $('div[name="shippingAddress.region"]').find('input');
                if (regionInput.length > 0) {
                    region = regionInput.val();
                }
            }
        }

        // Fallback: get city from form if not available in quote
        if (!city) {
            let cityInput = $('div[name="shippingAddress.city"]').find('input');
            if (cityInput.length > 0) {
                city = cityInput.val();
            }
            // Some checkouts might use select for city
            if (!city || city === '') {
                let citySelect = $('div[name="shippingAddress.city"]').find('select');
                if (citySelect.length > 0) {
                    city = citySelect.val();
                }
            }
        }

        let listLocation = courierListLocation(countryCode, regionId, city);
        // a courier picked for another locality is dropped server-side (ShippingInformationManagement
        // plugin, Model\Carrier): do not show it checked in the new list
        if (lastCourierListLocation !== '' && listLocation !== lastCourierListLocation) {
            window.localStorage.removeItem('innoshipCourierSelected');
            window.localStorage.removeItem('innoshipCourierSelectedName');
        }
        lastCourierListLocation = listLocation;

        $.ajax({
            url: window.BASE_URL + "innoshipf/courier/listcouriers",
            type: "POST",
            data: {
                refresh: "1",
                quote: quote.getQuoteId(),
                storeId: window.checkout.storeId,
                country: countryCode,
                region: region,
                // Setcourierid keeps this price only for this county (Model\CourierPriceStore::locationKey)
                region_id: regionId,
                city: city
            },
            cache: false
        }).done(function (data) {
            allDataList = data['json_data'];
            let courierCount = paintCourierList();

            // If only one courier, auto-select it and update shipping price
            if (courierCount === 1) {
                let firstCourierId = Object.keys(allDataList)[0];
                let firstCourier = allDataList[firstCourierId];
                let courierName = typeof firstCourier === 'object' ? firstCourier.name : firstCourier;
                let courierPrice = typeof firstCourier === 'object' ? firstCourier.price : null;

                // Store selection
                window.localStorage['innoshipCourierSelected'] = firstCourierId;
                window.localStorage['innoshipCourierSelectedName'] = courierName;

                // Update shipping price via AJAX
                updateShippingPriceWithCourier(firstCourierId, courierPrice);
            }
        }).always(function () {
            // replace the "..." placeholder (or stop it from being set after a fast answer)
            clearTimeout(pricePlaceholderTimer);
            showCurierRowPrice();
        });
    }

    /**
     * Draw the last courier list (allDataList) into the Curier row. Returns the number of couriers.
     */
    function paintCourierList() {
            let checkedSelected;
            let courierCount = Object.keys(allDataList || {}).length;
            htmlDataListCouriers = '<div class="courierList-innoship"><ul>';

            $.each(allDataList, function (i, item) {
                checkedSelected = '';

                // Auto-select if only one courier available
                if (courierCount === 1) {
                    checkedSelected = 'checked';
                } else if (i === window.localStorage['innoshipCourierSelected']) {
                    checkedSelected = 'checked';
                }

                // Handle both old format (string) and new format (object with name/price)
                let courierName = '';
                let courierPrice = '';
                let courierPriceValue = null;

                if (typeof item === 'object' && item !== null) {
                    courierName = item.name || '';
                    courierPriceValue = item.price;
                    if (item.price !== null && item.price !== undefined) {
                        courierPrice = ' - ' + item.price + ' ' + quote.totals().quote_currency_code;
                    }
                } else {
                    // Legacy format: item is just the name string
                    courierName = item;
                }

                // SECURITY: Escape HTML to prevent XSS
                htmlDataListCouriers += '<li class="innoship-courier-iditem"><input type="radio" name="cl-innoship" class="innoship-courier-ids" id="clInnoship-' + escapeHtml(i) + '" value="' + escapeHtml(i) + '" data-name="' + escapeHtml(courierName) + '" data-price="' + escapeHtml(courierPriceValue || '') + '" ' + checkedSelected + '/><label>' + escapeHtml(courierName) + escapeHtml(courierPrice) + '</label></li>';
            });
            htmlDataListCouriers += '</ul></div>';
            $('#label_carrier_1_innoshippudom').show();
            // SECURITY: Data already escaped in loop above, safe to insert
            $('#label_carrier_1_innoshippudom td').html(htmlDataListCouriers);

            return courierCount;
    }

    /**
     * The shipping-method rows were re-rendered (every rates estimate replaces them, and the new Curier
     * row comes back empty and hidden). Put the courier list back: the cached one when it was priced for
     * this locality, a new request otherwise. Curier selected, list on, shipping step only.
     */
    function restoreCourierListAfterRender() {
        let method = quote.shippingMethod();
        let address = quote.shippingAddress();

        if (!method || method.carrier_code + '_' + method.method_code !== 'innoship_1'
            || parseInt($('#showcourierlist').val()) !== 1
            || !($('#checkout-step-shipping').length === 0 || $('#checkout-step-shipping').is(':visible'))
            || !address || !address.city || $('#label_carrier_1_innoshippudom').length === 0
            || $('#label_carrier_1_innoshippudom .courierList-innoship').length > 0
        ) {
            return;
        }
        if (allDataList && Object.keys(allDataList).length > 0
            && courierListLocation(address.countryId, address.regionId, address.city) === lastCourierListLocation
        ) {
            paintCourierList();
            return;
        }
        $('#label_carrier_1_innoshippudom').show();
        $('#label_carrier_1_innoshippudom td').html('Loading...');
        showCourierList();
    }

    function hideCourierList() {
        $('#label_carrier_1_innoshippudom').hide();
        $('#label_carrier_1_innoshippudom td').html('');
    }

    function loadLockersByRadius() {

        $('#innoship-map-loading').show();

        if (!map) {

            return;
        }

        // Get center coordinates of current map view
        let center = map.getCenter();
        let lat = center.lat;
        let lng = center.lng;



        let requestData = {
            refresh: "1",
            quote: quote.getQuoteId(),
            storeId: window.checkout.storeId,
            lat: lat,
            lng: lng,
            radius: 5,
            courier: window.localStorage['Innoshipcourier']
        };



        // Make AJAX request to load lockers within 5km radius
        $.ajax({
            url: window.BASE_URL + "innoshipf/pudo/getmap",
            type: "POST",
            data: requestData,
            cache: false
        }).done(function (data) {
            $('#innoship-map-loading').hide();


            if (data && data.json_data) {
                let lockerCount = Object.keys(data.json_data).length;


                allDataPudo = data.json_data;

                // Re-initialize map to ensure layers are fresh (as requested to fix blocking issues)
                // We capture the current state, destroy, and recreate.
                // No ignoreMoveEvent here: the new map's setView fires its moveend synchronously, before
                // initializeMap() attaches the handler, so a flag set here was never consumed and swallowed
                // the customer's next pan (lockers past the old 5 km stayed unloaded until a second pan).
                initializeMap(lat, lng, map.getZoom());

                // Add new markers based on radius search
                let locations = [];
                $.each(allDataPudo, function (pudoId, pudoData) {
                    locations.push([
                        pudoData.name,
                        pudoData.latitude,
                        pudoData.longitude,
                        pudoData.pudo_id,
                        pudoData.addressText,
                        pudoData.localityName,
                        pudoData.countyName,
                        pudoData.courierId,
                        pudoData.postalCode
                    ]);
                });



                // Add markers to map
                let iconDisplayMapShow = iconDisplayMap[0];
                for (let i = 0; i < locations.length; i++) {
                    if (typeof iconDisplayMap[locations[i][7]] === 'undefined') {
                        iconDisplayMapShow = iconDisplayMap[0];
                    } else {
                        iconDisplayMapShow = iconDisplayMap[locations[i][7]];
                    }

                    let marker = new L.marker([locations[i][1], locations[i][2]], {
                        key: locations[i][3],
                        // street, city, county, postcode - the order fillShippingAddressInformation() reads
                        pudoLocation: String(locations[i][4]).replace(/,/g, ' ') + ', ' + locations[i][5] + ', ' + locations[i][6] + ', ' + locations[i][8],
                        icon: iconDisplayMapShow,
                        // #M1: a tap focuses the icon, and the focus pan's moveend reloaded the map, dropping the popup
                        autoPanOnFocus: false
                    })
                        // Leaflet renders popup content as HTML: escape the API's locker name/address.
                        .bindPopup(escapeHtml(locations[i][0] + ', ' + locations[i][4] + ', ' + locations[i][5] + ', ' + locations[i][6]))
                        .addTo(map)
                        .on("click", function (e) {
                            setMarkerPudo(e['sourceTarget']['options']['key'], e['sourceTarget']['options']['pudoLocation'], true);
                        });
                }


            } else {

            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
            $('#innoship-map-loading').hide();
            console.error('AJAX request failed:', {
                status: textStatus,
                error: errorThrown,
                response: jqXHR.responseText
            });
        });
    }

    function buildOpenHoursHtml(pudoData) {
        if (!pudoData) { return ''; }
        var days = [
            { key: 'mo', label: $.mage.__('Monday')    },
            { key: 'tu', label: $.mage.__('Tuesday')   },
            { key: 'we', label: $.mage.__('Wednesday') },
            { key: 'th', label: $.mage.__('Thursday')  },
            { key: 'fr', label: $.mage.__('Friday')    },
            { key: 'sa', label: $.mage.__('Saturday')  },
            { key: 'su', label: $.mage.__('Sunday')    }
        ];
        var rows = '';
        days.forEach(function (day) {
            var start = pudoData['open_hours_' + day.key + '_start'];
            var end   = pudoData['open_hours_' + day.key + '_end'];
            if (start && end && start !== '' && end !== '') {
                rows += '<tr><td style="padding:5px">' + escapeHtml(day.label) + '</td><td style="padding:5px">' + escapeHtml(start) + ' - ' + escapeHtml(end) + '</td></tr>';
            }
        });
        if (!rows) { return ''; }
        return '<br/><b style="padding-top: 20px; display: inline-block; width: 100%;">' + escapeHtml($.mage.__('Schedule')) + '</b><table class="innoship-open-hours"><tbody>' + rows + '</tbody></table>';
    }

    function setMarkerPudo(pudoId, pudoLocation, clickFromMap = false) {
        if (pudoId == null) {
            window.localStorage['innoshipPud'] = pudoId;
            window.localStorage['innoshipPudAddress'] = pudoLocation;
            setPudoValue();
        } else {
            window.localStorage['innoshipPud'] = pudoId;
            window.localStorage['innoshipPudAddress'] = pudoLocation;
            // the locker may be missing from allDataPudo - read it only when present
            if (allDataPudo && allDataPudo[pudoId]) {
                window.localStorage['innoshipPudDescription'] = allDataPudo[pudoId]['infoShow'];
            }

            if (parseInt(pudoId) > 0) {
                // SECURITY NOTE: infoShow comes from trusted backend and contains intentional HTML formatting
                // Only escape the user-provided location data
                var pudoDescription = window.localStorage['innoshipPudDescription']; // Contains safe HTML from backend
                var escapedLocation = escapeHtml(pudoLocation);
                var hoursHtml = (allDataPudo && allDataPudo[pudoId]) ? buildOpenHoursHtml(allDataPudo[pudoId]) : '';
                $('.innomapliv').html("<b>" + $.mage.__('Shipping point selected') + "</b> " + pudoDescription + "<br/><b>" + $.mage.__('Address:') + "</b> " + escapedLocation + hoursHtml);
                $('#innoshipcargusalegeconf').css('display', 'inline-block');
                if (customer.isLoggedIn()) {
                    $('#innoshipsalveazafavorit').css('display', 'inline-block');
                }
                displayPudoLocation(pudoId, pudoLocation, clickFromMap, false);
                $(".innopudo-section-left").animate({ scrollTop: $('.innopudo-section-left').prop("scrollHeight") }, 1000);
            } else {
                $('.innomapliv').html("");
                $('#innoshipcargusalegeconf').css('display', 'none');
                $('#innoshipsalveazafavorit').css('display', 'none');
            }

            setPudoValue();

            if (clickFromMap === false) {
                $('.innoship-mapsection').fadeOut();
                $('.innoship-mapsection-layer').fadeOut();
                removeCloseMap(map);
            }

        }
    }

    function cleanSearch() {
        $('#searchpudovalue').val('');
        $('.searchpudoresults').hide();
    }

    function showSearch() {
        $('.searchpudo').show();
    }

    function setCountyList(data) {
        let countyList = data['county'];
        let htmlData = '<option value="">' + $.mage.__('State') + '</option>';
        $.each(countyList, function (i, item) {
            // SECURITY: Escape HTML to prevent XSS
            htmlData += '<option value="' + escapeHtml(item) + '">' + escapeHtml(item) + '</option>';
        });

        $('#innopudocounty').html(htmlData).show();
        cleanSearch();

        window.stepGetInfo = 2;
    }

    function setLocalityList(data) {
        let countyList = data['locality'];
        let htmlData = '<option value="">' + $.mage.__('City') + '</option>';
        $.each(countyList, function (i, item) {
            // SECURITY: Escape HTML to prevent XSS
            htmlData += '<option value="' + escapeHtml(item) + '">' + escapeHtml(item) + '</option>';
        });

        $('#innopudocity').html(htmlData).show();
        cleanSearch();

        window.stepGetInfo = 3;
    }

    function setLockerList(data) {
        allDataPudo = data;
        let locations = [];
        $.each(data, function (i, item) {
            locations.push(['' + item['addressText'] + '', item['latitude'], item['longitude'], item['pudo_id'], item['localityName'], item['countyName'], item['postalCode'], item['courierId']]);
        });

        removeCloseMap(map);

        setTimeout(function () {
            initIcons();
            let lat = 45.943161;
            let lng = 24.96676;
            let zoom = 7;

            if (typeof locations[0] !== "undefined") {
                lat = locations[0][1];
                lng = locations[0][2];
                zoom = 12;
            }

            initializeMap(lat, lng, zoom);

            let iconDisplayMapShow = '';
            for (let i = 0; i < locations.length; i++) {
                if (typeof iconDisplayMap[locations[i][7]] === 'undefined') {
                    iconDisplayMapShow = iconDisplayMap[0];
                } else {
                    iconDisplayMapShow = iconDisplayMap[locations[i][7]];
                }
                marker = new L.marker([locations[i][1], locations[i][2]], {
                    key: locations[i][3],
                    pudoLocation: locations[i][0] + ', ' + locations[i][4] + ', ' + locations[i][5] + ', ' + locations[i][6],
                    icon: iconDisplayMapShow,
                    // #M1: a tap focuses the icon, and the focus pan's moveend reloaded the map, dropping the popup
                    autoPanOnFocus: false
                })
                    // Leaflet renders popup content as HTML: escape the API's locker name/address.
                    .bindPopup(escapeHtml(locations[i][0] + ', ' + locations[i][4] + ', ' + locations[i][5] + ', ' + locations[i][6]))
                    .addTo(map)
                    .on("click", function (e) {
                        setMarkerPudo(e['sourceTarget']['options']['key'], e['sourceTarget']['options']['pudoLocation'], true);
                    });
            }
            setTimeout(function () { window.stepGetInfo = 4; }, 1000);
        }, 300);

        showSearch();
        // a late locker reload for the same city kept wiping what the customer had typed - re-filter it instead
        if ($.trim($('#searchpudovalue').val()) !== '') {
            $('#searchpudovalue').trigger('keyup');
        } else {
            cleanSearch();
        }
    }

    function showCourierListPudo(courierList) {
        let dataJsonCourierListExtraIns = '';
        $.each(courierList, function (i, item) {
            // SECURITY: Escape HTML to prevent XSS
            dataJsonCourierListExtraIns += '<div class="courier-list-row"><input type="checkbox" name="courierl" value="' + escapeHtml(i) + '" checked><span>' + escapeHtml(item) + '</span></div>';
        });

        $('#innopudocourierlist').html(dataJsonCourierListExtraIns).show();
    }

    /**
     * True when the customer picks from saved address cards (the form sits in the New Address pop-up).
     */
    function hasSavedAddressCards() {
        return customer.isLoggedIn() && $('#checkout-step-shipping .shipping-address-item').length > 0;
    }

    function showShippingAddressInformation() {
        $('div[name="shippingAddress.country_id"]').show().trigger('change');
        $('div[name="shippingAddress.region_id"]').show();
        $('div[name="shippingAddress.region_id"]').find('select').val('').trigger('change');
        $('div[name="shippingAddress.city"]').show();
        $('div[name="shippingAddress.city"]').find('input').val('').trigger('change');
        $('div[name="shippingAddress.street.0"]').closest('.street').show();
        $('div[name="shippingAddress.street.0"]').find('input').val('').trigger('change');
        $('div[name="shippingAddress.postcode"]').show();
        $('div[name="shippingAddress.postcode"]').find('input').val('').trigger('change');
        $('div[name="shippingAddress.entity_type"]').show();
        $(".billing-address-same-as-shipping-block").show();
    }

    /**
     * Lowercase a name and strip Romanian diacritics and every non-alphanumeric character.
     */
    function foldName(value) {
        let map = {
            'Ă': 'a', 'ă': 'a', 'Â': 'a', 'â': 'a', 'Î': 'i', 'î': 'i',
            'Ș': 's', 'ș': 's', 'Ş': 's', 'ş': 's',
            'Ț': 't', 'ț': 't', 'Ţ': 't', 'ţ': 't'
        };

        return String(value === null || value === undefined ? '' : value)
            .replace(/[ĂăÂâÎîȘșŞşȚțŢţ]/g, function (character) {
                return map[character];
            })
            .toLowerCase()
            .replace(/[^a-z0-9]/g, '');
    }

    /**
     * Resolve a Magento region from an InnoShip county name, using the directory section data and
     * falling back to the rendered region select. Returns null when neither knows the county; the
     * server resolves the region again in that case.
     */
    function findRegion(countryId, countyName) {
        let directory = customerData.get('directory-data')();
        let needle = foldName(countyName);
        let found = null;

        if (!needle) {
            return null;
        }

        if (countryId && directory && directory[countryId] && directory[countryId].regions) {
            $.each(directory[countryId].regions, function (regionId, region) {
                if (foldName(region.name) === needle || foldName(region.code) === needle) {
                    found = { id: regionId, name: region.name, code: region.code };

                    return false;
                }
            });
        }

        if (found) {
            return found;
        }

        // The directory section may not be loaded yet. Fall back to the rendered region select,
        // which is what the old code matched against, so a missing section cannot cost us the region.
        $('div[name="shippingAddress.region_id"]').find('select option').each(function () {
            let option = $(this);
            let optionValue = option.val();

            if (!optionValue) {
                return true;
            }

            if (foldName(option.data('title')) === needle || foldName(option.text()) === needle) {
                found = { id: optionValue, name: option.data('title') || option.text(), code: '' };

                return false;
            }
        });

        return found;
    }

    /**
     * Fill the shipping address form with a locker, reading the fields from the locker record
     * rather than splitting a comma-joined display string. Three different producers build that
     * string in three different field orders, and any locker whose addressText itself contains a
     * comma ("Sos. Pacurari, Nr. 121") shifted every field by one.
     *
     * The form stays the client-side source of truth: the checkout rebuilds the quote address from
     * it, so writing anywhere else would be overwritten on the next form change. The stored address
     * is guaranteed server-side by InnoShip\InnoShip\Model\PudoAddress, which also clears the
     * customer_address_id that made Magento re-import the customer's saved address over the locker.
     */
    function fillShippingAddressInformation(pudoId) {
        let pudo = allDataPudo ? allDataPudo[pudoId] : null;

        if (!pudo || !pudo['addressText']) {
            return;
        }

        let countryId = pudo['countryCode'] || 'RO';

        setTimeout(function () {
            let countrySelect = $('div[name="shippingAddress.country_id"]').find('select');

            if (countrySelect.length) {
                countrySelect.val(countryId).trigger('change');
            }
            $('div[name="shippingAddress.country_id"]').hide();

            // Resolved only after the country has been applied: the region select is re-rendered
            // per country, and the DOM fallback inside findRegion reads those options.
            let region = findRegion(countryId, pudo['countyName']);

            if (region) {
                let regionSelect = $('div[name="shippingAddress.region_id"]').find('select');

                if (regionSelect.length) {
                    regionSelect.val(region.id).trigger('change');
                }
                // Hidden only when it holds a resolved value. Hiding an unmatched county would
                // leave a hidden required field empty and block checkout with no visible error.
                $('div[name="shippingAddress.region_id"]').hide();
            }

            $('div[name="shippingAddress.city"]').find('input').val(pudo['localityName']).trigger('change');
            $('div[name="shippingAddress.city"]').hide();
            // Some themes render the street as a textarea instead of an input.
            $('div[name="shippingAddress.street.0"]').find('input, textarea').val(pudo['addressText']).trigger('change');
            $('div[name="shippingAddress.street.0"]').closest('.street').hide();
            // Address type (person/company) means nothing for a locker; shown again on leaving Locker.
            $('div[name="shippingAddress.entity_type"]').hide();
            $('div[name="shippingAddress.postcode"]').find('input').val(pudo['postalCode']).trigger('change');
            $('div[name="shippingAddress.postcode"]').hide();

            fillTelephoneIfEmpty();
        }, 500);
    }

    /**
     * A locker has no phone number of its own, so the customer's own number must be kept. Only fill
     * the field when it is empty; the previous code wrote a literal '0000000000' for every
     * logged-in customer, which left the courier unable to call them.
     */
    function fillTelephoneIfEmpty() {
        let input = $('div[name="shippingAddress.telephone"]').find('input');

        if (!input.length || $.trim(input.val()) !== '') {
            return;
        }

        let current = quote.shippingAddress() || {};
        let billing = quote.billingAddress() || {};
        let phone = current.telephone || billing.telephone || '';

        if (!phone && customer.isLoggedIn() && customer.customerData && customer.customerData.addresses) {
            $.each(customer.customerData.addresses, function (addressId, address) {
                if (address && address.telephone) {
                    phone = address.telephone;

                    return false;
                }
            });
        }

        if (phone) {
            input.val(phone).trigger('change');
        }
    }

    function getGPSCoordinates(priority) {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function (position) {

                let lat = position.coords.latitude;
                let lon = position.coords.longitude;

                showInnoShipLoader("#locatiamea");

                window.innoshipGpsCurrentLat = lat;
                window.innoshipGpsCurrentLong = lon;




                window.stepGetInfo = 1;

                $.ajax({
                    url: window.BASE_URL + "innoshipf/pudo/getpudofromlocation",
                    type: "POST",
                    data: {
                        refresh: "1",
                        lat: lat,
                        long: lon
                    },
                    cache: false
                }).done(function (data) {
                    if (data['json_data']['countryCode']) {
                        let stepGetInfo = window.stepGetInfo
                        $('#innopudocountry').val(data['json_data']['countryCode']).trigger("change");

                        waitForValue(2, (stepGetInfo) => {
                            $('#innopudocounty').val(cleanString(data['json_data']['countyName'])).trigger('change');
                        });

                        waitForValue(3, (stepGetInfo) => {
                            $('#innopudocity').val(cleanString(data['json_data']['localityName'])).trigger('change');
                        });

                        waitForValue(4, (stepGetInfo) => {
                            map.setView([lat, lon], 16);
                            innoshipLoaderHide();
                            showInnoShipLocationGreen();
                            window.stepGetInfo = 0;
                        });
                    }
                });
                // Optional AJAX send
            }, function (error) {
                switch (error.code) {
                    case error.PERMISSION_DENIED:
                        console.warn("User denied the request for Geolocation.");
                        break;
                    case error.POSITION_UNAVAILABLE:
                        console.warn("Location information is unavailable.");
                        break;
                    case error.TIMEOUT:
                        console.warn("The request to get user location timed out.");
                        break;
                    case error.UNKNOWN_ERROR:
                        console.warn("An unknown error occurred.");
                        break;
                }
            }, {
                enableHighAccuracy: true, // use GPS if available
                timeout: 10000,           // max wait time (10 sec)
                maximumAge: 0             // don’t use cached position
            });
        }
    }
    function waitForValue(targetValue, callback, interval = 50) {
        const check = setInterval(() => {
            if (window.stepGetInfo === targetValue) {

                clearInterval(check);
                callback(window.stepGetInfo);
            }
        }, interval);
    }

    function innoshipLoaderHide() {
        $('.loader-innoship').hide();
    }

    function showInnoShipLocationGreen() {
        $('#locatiamea').html(locationSvgLive);
    }

    function showInnoShipLoader(classToReplaceContent) {
        $(classToReplaceContent).html('<div class="loader-innoship"></div>');
        $('.loader-innoship').show();
    }
    function attachMapEvents() {
        if (map) {
            map.off('moveend');
            // #M1: a popup fitting itself into view fires autopanstart, then panBy, which always ends in one
            // moveend; skip that reload, or initializeMap() removes the map together with the open popup.
            map.off('autopanstart');
            map.on('autopanstart', function () {
                ignoreMoveEvent = true;
            });
            map.on('moveend', function () {
                if (ignoreMoveEvent) {
                    ignoreMoveEvent = false;
                    return;
                }
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () {

                    loadLockersByRadius();
                }, 500);
            });

        }
    }

    function initIcons() {
        if (iconDisplayMap.length === 0 || typeof iconDisplayMap[0] === 'undefined') {
            iconDisplayMap[1] = L.icon(iconCargus);
            iconDisplayMap[2] = L.icon(iconDpd);
            iconDisplayMap[3] = L.icon(iconFancourier);
            iconDisplayMap[6] = L.icon(iconSameday);
            iconDisplayMap[11] = L.icon(iconPostaromana);
            iconDisplayMap[12] = L.icon(iconPostapanduri);
            iconDisplayMap[0] = L.icon(iconGeneral);
        }
    }

    /**
     * Set country and wait for county list to load
     * @param {string} countryId - Country ID to select
     * @returns {Promise} Promise that resolves when county list is loaded
     */
    function setCountryAndWaitForCounties(countryId) {
        return new Promise(function(resolve, reject) {
            // Reset county/city dropdowns so the poll below truly waits for the
            // fresh AJAX response. Without this reset, on a 2nd open the dropdowns
            // still hold options from the previous open, the poll resolves
            // immediately, and the late AJAX callbacks wipe the user-selected
            // county/city after the chain has already finished.
            $('#innopudocounty').html('<option value="">' + $.mage.__('State') + '</option>');
            $('#innopudocity').html('<option value="">' + $.mage.__('City') + '</option>').hide();

            // Set the country value and trigger change
            $('#innopudocountry').val(countryId).trigger("change");

            // The change event handler will make an AJAX call
            // We need to wait for it to complete by checking when setCountyList is called
            // Since we can't easily hook into that, we'll poll for the county dropdown to be populated
            let checkInterval = setInterval(function() {
                let countyOptions = $('#innopudocounty option');
                // Check if county dropdown has more than just the default option
                if (countyOptions.length > 1) {
                    clearInterval(checkInterval);
                    resolve();
                }
            }, 50);

            // Add timeout to prevent infinite waiting
            setTimeout(function() {
                clearInterval(checkInterval);
                reject(new Error('Timeout waiting for county list to load'));
            }, 10000);
        });
    }

    /**
     * Set county and wait for city list to load
     * @param {string} countyName - County name to select
     * @returns {Promise} Promise that resolves when city list is loaded
     */
    function setCountyAndWaitForCities(countyName) {
        return new Promise(function(resolve, reject) {
            // Reset city dropdown so the poll below truly waits for the fresh
            // AJAX response (see comment in setCountryAndWaitForCounties).
            $('#innopudocity').html('<option value="">' + $.mage.__('City') + '</option>').hide();

            // Set the county value and trigger change
            $('#innopudocounty').val(countyName).trigger('change');

            // The change event handler will make an AJAX call
            // We need to wait for it to complete by checking when setLocalityList is called
            let checkInterval = setInterval(function() {
                let cityDropdown = $('#innopudocity');
                let cityOptions = cityDropdown.find('option');
                // Check if city dropdown has more than just the default option AND is visible
                if (cityOptions.length > 1 && cityDropdown.is(':visible')) {
                    clearInterval(checkInterval);
                    // Add small delay to ensure DOM is fully updated
                    setTimeout(function() {
                        resolve();
                    }, 100);
                }
            }, 50);

            // Add timeout to prevent infinite waiting
            setTimeout(function() {
                clearInterval(checkInterval);
                reject(new Error('Timeout waiting for city list to load'));
            }, 10000);
        });
    }

    function initializeMap(lat, lng, zoom) {
        if (map) {
            map.remove();
            map = null;
        }

        // Extra safety check for container
        var container = L.DomUtil.get('mapview');
        if (container != null) {
            if (container._leaflet_id) {
                container._leaflet_id = null;
            }
        }

        try {
            map = L.map('mapview').setView([lat, lng], zoom);
        } catch (e) {
            console.error("Error initializing map:", e);
            // Last resort fallback if map is still stuck
            if (e.message.includes('Map container is already initialized')) {
                // This shouldn't happen with the cleanup above, but just in case
                return null;
            }
            throw e;
        }

        mapLink = '<a href="https://openstreetmap.org">OpenStreetMap</a>';
        L.tileLayer(
            'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; ' + mapLink + ' Contributors',
            maxZoom: 18,
            // OSM tile servers refuse requests without a referrer (the page's no-referrer policy).
            referrerPolicy: 'strict-origin-when-cross-origin',
        }).addTo(map);

        setTimeout(function () {
            map.invalidateSize();
        }, 100);

        attachMapEvents();

        return map;
    }
});
