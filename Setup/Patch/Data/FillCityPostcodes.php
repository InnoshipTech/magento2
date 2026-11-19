<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Setup\Patch\Data;

use InnoShip\InnoShip\Model\CityPostcodeFiller;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Fill the city postcodes earlier imports left at 0. Later imports fill them themselves
 * (Cron\Citylist, Console\Regioimport). Safe to re-run: only rows still at 0 or empty are touched.
 */
class FillCityPostcodes implements DataPatchInterface
{
    /**
     * @var CityPostcodeFiller
     */
    private $cityPostcodeFiller;

    /**
     * @param CityPostcodeFiller $cityPostcodeFiller
     */
    public function __construct(
        CityPostcodeFiller $cityPostcodeFiller
    ) {
        $this->cityPostcodeFiller = $cityPostcodeFiller;
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $this->cityPostcodeFiller->execute();

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}
