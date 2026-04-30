<?php

namespace InnoShip\InnoShip\Model;

/**
 * Class Table
 * @package InnoShip\InnoShip\Model
 */
class Table
{
    /** @var array */
    const COURIER_TABLES = ['Cargus', 'DPD', 'FanCourier', 'GLS', 'Nemo', 'Sameday', 'ExpressOne', 'Econt', 'TeamCourier', 'DHL'];

    /**
     * @param int $courierId
     *
     * @return string
     */
    public function getCourier(int $courierId): string
    {
        try {
            return self::COURIER_TABLES[$courierId - 1];
        } catch (\Exception $exception) {
            return 'Invalid courier ID: '.$courierId;
        }
    }
}
