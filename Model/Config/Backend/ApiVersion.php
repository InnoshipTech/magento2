<?php

namespace InnoShip\InnoShip\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\ValidatorException;

/**
 * Class ApiVersion
 * @package InnoShip\InnoShip\Model\Config\Backend
 */
class ApiVersion extends Value
{
    /**
     * List of valid InnoShip API versions
     *
     * @var array
     */
    const API_VERSIONS = [
        '1.0'
    ];

    /**
     * @inheritdoc
     * @throws ValidatorException
     */
    public function beforeSave()
    {
        $apiVersion = $this->getValue();

        if ( ! empty($apiVersion) && ! in_array($apiVersion, self::API_VERSIONS)) {
            throw new ValidatorException(__('InnoShip API version must be one of: %1', implode(', ', self::API_VERSIONS)));
        }

        return parent::beforeSave();
    }
}
