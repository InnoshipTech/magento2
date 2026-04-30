<?php

namespace InnoShip\InnoShip\Api;

use Magento\Framework\DataObject;

/**
 * Interface ApiLabelServiceInterface
 * @package InnoShip\InnoShip\Api
 */
interface ApiLabelServiceInterface
{
    /**
     * @param int         $courierId
     * @param string      $awb
     * @param string|null $filePath
     *
     * @return DataObject
     */
    public function get(int $courierId, string $awb, ?string $filePath = null): DataObject;
}
