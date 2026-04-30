<?php

namespace InnoShip\InnoShip\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\ValidatorException;

/**
 * Class GatewayUrl
 * @package InnoShip\InnoShip\Model\Config\Backend
 */
class GatewayUrl extends Value
{
    /**
     * List of valid InnoShip Gateway URLs
     *
     * @var array
     */
    const GATEWAY_URLS = [
        'https://api.innoship.io',
    ];

    /**
     * @inheritdoc
     * @throws ValidatorException
     */
    public function beforeSave()
    {
        $gatewayUrl = 'https://' . parse_url((string) $this->getValue(), \PHP_URL_HOST);

        if ( ! empty($gatewayUrl) && ! in_array($gatewayUrl, self::GATEWAY_URLS)) {
            throw new ValidatorException(__('InnoShip API endpoint URL must be one of: %1', [implode(', ', self::GATEWAY_URLS)]));
        }

        return parent::beforeSave();
    }
}
