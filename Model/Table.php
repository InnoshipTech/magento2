<?php

namespace InnoShip\InnoShip\Model;

use Magento\Framework\App\ResourceConnection;

/**
 * Class Table
 * @package InnoShip\InnoShip\Model
 */
class Table
{
    /** @var array */
    const COURIER_TABLES = ['Cargus', 'DPD', 'FanCourier', 'GLS', 'Nemo', 'Sameday', 'ExpressOne', 'Econt', 'TeamCourier', 'DHL'];

    /** @var ResourceConnection */
    private $resourceConnection;

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Courier name for an AWB: innoship_courierlist (synced from the API), then the static list, then the id.
     * A courier outside the static list (e.g. Dragon Star) used to raise "Undefined array key" after the AWB
     * was already created at Innoship, so Magento saved no tracking number.
     *
     * @param int $courierId
     *
     * @return string
     */
    public function getCourier(int $courierId): string
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $name = $connection->fetchOne(
                $connection->select()
                    ->from($this->resourceConnection->getTableName('innoship_courierlist'), ['courierName'])
                    ->where('courierId = ?', $courierId)
                    ->limit(1)
            );
            if ($name) {
                return (string) $name;
            }
        } catch (\Exception $exception) {
            // fall back to the static list
        }

        return self::COURIER_TABLES[$courierId - 1] ?? ('Courier ID ' . $courierId);
    }
}
