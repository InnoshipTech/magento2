define([
    'jquery',
    'jquery-ui-modules/widget'
], function ($) {
    'use strict';

    $.widget('innoship.customerAddressCity', {
        options: {
            ajaxUrl: ''
        },

        /** @var {Array|null} */
        _cityData: null,

        /** @var {jQuery|null} */
        _countryEl: null,

        /** @var {jQuery|null} */
        _regionEl: null,

        /** @var {jQuery|null} */
        _cityEl: null,

        /** @var {jQuery|null} */
        _zipEl: null,

        /** @var {jQuery|null} */
        _selectEl: null,

        /** @var {string} */
        _initialCity: '',

        /**
         * Widget initialization
         *
         * @private
         */
        _create: function () {
            this._countryEl = $('#country');
            this._regionEl = $('#region_id');
            this._cityEl = $('#city');
            this._zipEl = $('#zip');
            this._initialCity = this._cityEl.val() || '';

            this._selectEl = $('<select/>', {
                id: 'innoship-city-select',
                'class': 'select'
            }).hide().insertAfter(this._cityEl);

            this._bindEvents();
            this._onCountryChange();
        },

        /**
         * Bind change events to country, region and city select
         *
         * @private
         */
        _bindEvents: function () {
            this._countryEl.on('change', this._onCountryChange.bind(this));
            this._regionEl.on('change', this._onRegionChange.bind(this));
            this._selectEl.on('change', this._onCitySelect.bind(this));
        },

        /**
         * Handle country change - show/hide city dropdown
         *
         * @private
         */
        _onCountryChange: function () {
            if (this._countryEl.val() === 'RO') {
                if (parseInt(this._regionEl.val(), 10) > 0) {
                    this._onRegionChange();
                }
            } else {
                this._hideCitySelect();
            }
        },

        /**
         * Handle region change - fetch cities via AJAX
         *
         * @private
         */
        _onRegionChange: function () {
            var self = this,
                regionId = this._regionEl.val();

            if (this._countryEl.val() !== 'RO') {
                return;
            }

            if (!regionId || parseInt(regionId, 10) <= 0) {
                this._hideCitySelect();

                return;
            }

            this._selectEl.html('<option value="...">...</option>');
            this._showSelectHideInput();

            $.ajax({
                url: this.options.ajaxUrl,
                type: 'POST',
                data: {
                    c: regionId
                },
                cache: false
            }).done(function (data) {
                var cities = data && data.json_data ? data.json_data : [];

                if (cities.length) {
                    self._cityData = cities;
                    self._showCitySelect(cities);
                } else {
                    self._hideCitySelect();
                }
            }).fail(function () {
                self._hideCitySelect();
            });
        },

        /**
         * Build and show city dropdown from data
         *
         * @param {Array} cities
         * @private
         */
        _showCitySelect: function (cities) {
            var hasPreselected = false,
                currentCity = this._initialCity || this._cityEl.val() || '';

            this._selectEl.empty();
            this._selectEl.append($('<option/>').val('').text('Selectati'));

            $.each(cities, function (i, item) {
                var cityName = item.localitate.toString(),
                    $option = $('<option/>').val(cityName).text(cityName);

                if (currentCity === cityName) {
                    $option.attr('selected', 'selected');
                    hasPreselected = true;
                }

                this._selectEl.append($option);
            }.bind(this));

            this._showSelectHideInput();

            if (hasPreselected) {
                this._fillPostcode(currentCity);
            }
        },

        /**
         * Hide city select and show text input
         *
         * @private
         */
        _hideCitySelect: function () {
            this._selectEl.hide();
            this._cityEl.show();
            this._cityData = null;
        },

        /**
         * Show select and hide text input
         *
         * @private
         */
        _showSelectHideInput: function () {
            this._cityEl.hide();
            this._selectEl.show();
        },

        /**
         * Handle city selection from dropdown
         *
         * @private
         */
        _onCitySelect: function () {
            var selectedCity = this._selectEl.val();

            this._cityEl.val(selectedCity).trigger('change').trigger('keyup');

            if (selectedCity) {
                this._fillPostcode(selectedCity);
            }
        },

        /**
         * Clean up widget on destruction
         *
         * @private
         */
        _destroy: function () {
            this._selectEl.remove();
            this._cityEl.show();
            this._countryEl.off('change');
            this._regionEl.off('change');
            this._super();
        },

        /**
         * Find and fill postcode for selected city
         *
         * @param {string} cityName
         * @private
         */
        _fillPostcode: function (cityName) {
            var self = this;

            if (!this._cityData) {
                return;
            }

            $.each(this._cityData, function (i, item) {
                if (item.localitate === cityName) {
                    if (parseInt(item.codPostal, 10) !== 0) {
                        var postalCode = item.codPostal.toString();

                        if (postalCode.length === 5) {
                            postalCode = '0' + postalCode;
                        }

                        self._zipEl.val(postalCode);
                    } else {
                        self._zipEl.val('');
                    }

                    self._zipEl.trigger('change').trigger('keyup');

                    return false;
                }
            });
        }
    });

    return $.innoship.customerAddressCity;
});
