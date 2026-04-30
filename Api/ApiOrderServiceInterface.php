<?php

namespace InnoShip\InnoShip\Api;

use Magento\Framework\DataObject;

/**
 * Interface ApiOrderServiceInterface
 * @package InnoShip\InnoShip\Api
 */
interface ApiOrderServiceInterface
{
    /**
     * @param array $data
     *
     * @return DataObject
     * @throws \Exception
     */
    public function create(array $data): DataObject;

    /**
     * @param array $data
     *
     * @return DataObject
     * @throws \Exception
     */
    public function price(array $data): DataObject;

    /**
     * @param int    $courierId
     * @param string $awb
     *
     * @return DataObject
     * @throws \Exception
     */
    public function delete(int $courierId, string $awb): DataObject;
}
