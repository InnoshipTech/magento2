# InnoShip Magento 2 Module

## Package

- Module: `InnoShip_InnoShip`
- Composer package: `innoship/module-innoship`

## Install In Magento 2 Via Composer (VCS)

In the client Magento project:

```bash
composer config repositories.innoship vcs https://github.com/InnoshipTech/magento2.git
composer require innoship/module-innoship:^1.4
bin/magento module:enable InnoShip_InnoShip
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy   # production mode only
bin/magento cache:flush
```

When updating from an earlier version run the same commands: constructors changed in 1.4.0, so `setup:di:compile` is required.

## Optional: Amasty Checkout Override

If the project uses Amasty Checkout, install the dedicated template override package too:

```bash
composer config repositories.innoship_amasty vcs https://github.com/InnoshipTech/magento2-amasty-checkout-override.git
composer require innoship/theme-amasty-checkout-override:^1.0
bin/magento setup:upgrade
bin/magento cache:flush
```

This package provides the overrides for:

- `Amasty_CheckoutCore/web/template/onepage/shipping/methods.html` (required by InnoShip)
- `Amasty_CheckoutCore/web/template/onepage/billing-address/details.html` (prints the billing company, which Amasty leaves out)

The package registers the theme `frontend/InnoShip/InnoShip` (parent `Magento/luma`). A store with its own theme copies the two files into `app/design/frontend/<Vendor>/<theme>/Amasty_CheckoutCore/web/template/onepage/` instead of installing the package.

Both packages are versioned with git tags (`1.4.0`, `1.0.0`, ...); `composer show -a innoship/module-innoship` lists the available versions.

## Features

- InnoShip shipping carrier integration for Magento 2 checkout and order flows (Curier and Locker methods)
- Courier list in checkout: the customer picks a courier and pays the price quoted by the InnoShip API
- PUDO / locker selection in checkout with a map, favourite locker in My Account
- AWB creation and label download from Magento Admin, tracking number saved on the shipment
- Country specific fees, free shipping thresholds and optional automatic price from the InnoShip API
- Payment methods restricted per shipping method (Curier / Locker)
- Courier, city and PUDO synchronization via cron jobs and console commands
- Works with the standard Magento checkout and with Amasty One Step Checkout
- GraphQL `storeConfig { innoship_handling_fee innoship_locker_handling_fee }`

## Requirements

- Magento Open Source / Adobe Commerce 2.4.x (tested on 2.4.9)
- PHP 8.1 – 8.5

## Configuration

After installation, configure the module from Magento Admin in **Stores → Configuration → Sales → Delivery Methods → InnoShip** (Curier) and **InnoShip Locker**, and add your API key.

### Shipping price (Curier)

The price is decided in this order:

1. Base price: the country specific fee, else the handling fee.
2. "Automatic Price" = Yes: the price returned by the InnoShip API replaces the base price.
3. A courier picked by the customer in the courier list: its quoted price replaces the price above.
4. Free shipping, applied last and to every price above:
   - the country's free shipping threshold (Country Specific Fees);
   - "Enable Free Shipping" with "Minimum Order Amount for Free Shipping";
   - a cart price rule with Free Shipping.

The order subtotal compared with a threshold is the subtotal including tax, minus item discounts.

## Changelog

The full list of changes is in [`change.log`](change.log).

### 1.4.0

- Compatibility with Magento 2.4.9 and PHP 8.5
- Security hardening
- Checkout fixes for Curier and Locker (standard checkout and Amasty One Step Checkout)
- Free shipping also applies to a courier picked in checkout
- AWB tracking number saved for every courier returned by the InnoShip API
- Locker and city import reliability (large locker lists, stable region ids)
- Default values for every typed configuration setting
