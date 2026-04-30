var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/view/billing-address': {
                'InnoShip_InnoShip/js/view/checkout/billing-address-mixin': true
            },
            'Magento_Checkout/js/view/summary/shipping': {
                'InnoShip_InnoShip/js/view/summary/shipping-mixin': true
            },
            'Magento_Checkout/js/action/set-shipping-information': {
                'InnoShip_InnoShip/js/set-city-mixin': true
            }
        }
    },
    map: {
        '*': {
            'Magento_Checkout/template/shipping-address/shipping-method-item.html': 'InnoShip_InnoShip/template/shipping-address/shipping-method-item.html',
            'leaflet': 'InnoShip_InnoShip/js/leaflet',
        }
    },
    shim: {
        'leaflet': {
            exports: 'L'
        },
        'markercluster': {
            deps: ['L']
        }
    }
};
