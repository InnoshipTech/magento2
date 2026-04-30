<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class FixSatuMareRegionName implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        // Update the region name from "Satu-Mare" to "Satu Mare" in directory_country_region_name table
        $this->moduleDataSetup->getConnection()->update(
            $this->moduleDataSetup->getTable('directory_country_region_name'),
            ['name' => 'Satu Mare'],
            ['name = ?' => 'Satu-Mare']
        );

        // Update the default_name from "Satu-Mare" to "Satu Mare" in directory_country_region table
        $this->moduleDataSetup->getConnection()->update(
            $this->moduleDataSetup->getTable('directory_country_region'),
            ['default_name' => 'Satu Mare'],
            ['default_name = ?' => 'Satu-Mare']
        );

        $this->moduleDataSetup->getConnection()->endSetup();

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