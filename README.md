# InnoShip Magento 2 Module

## Package

- Module: `InnoShip_InnoShip`
- Composer package: `innoship/module-innoship`

## Install In Magento 2 Via Composer (VCS)

In the client Magento project:

```bash
composer config repositories.innoship vcs https://github.com/InnoshipTech/magento2.git
composer require innoship/module-innoship:^1.3
bin/magento module:enable InnoShip_InnoShip
bin/magento setup:upgrade
bin/magento cache:flush
```

## Features

- InnoShip shipping carrier integration for Magento 2 checkout and order flows
- AWB creation and label download from Magento Admin
- PUDO / locker selection support in checkout
- Courier, city and PUDO synchronization via cron jobs
- Shipping-related data persistence on quote/order address entities

## Requirements

- Magento Open Source / Adobe Commerce 2.4.x
- PHP 8.1+

## Configuration

After installation, configure the module from Magento Admin under the InnoShip shipping settings section and add your API credentials.

## Changelog

### 1.3.8

- Added and stabilized latest schema/data patches used by the module
- Improved checkout and shipping integration behavior
- Updated compatibility metadata for Magento 2.4 + PHP 8.1+
