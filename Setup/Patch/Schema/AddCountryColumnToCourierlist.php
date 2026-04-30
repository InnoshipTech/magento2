<?php

namespace InnoShip\InnoShip\Setup\Patch\Schema;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class AddCountryColumnToCourierlist implements SchemaPatchInterface
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
     * {@inheritdoc}
     */
    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $tableName = $this->moduleDataSetup->getTable('innoship_courierlist');
        $connection = $this->moduleDataSetup->getConnection();

        // Check if the column doesn't already exist
        if (!$connection->tableColumnExists($tableName, 'country')) {
            // First, drop the old unique constraint on courierId only
            $oldIndexName = $connection->getIndexName(
                'innoship_courierlist',
                ['courierId'],
                \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE
            );

            if ($connection->getIndexList($tableName)[$oldIndexName] ?? false) {
                $connection->dropIndex($tableName, $oldIndexName);
            }

            // Add the country column
            $connection->addColumn(
                $tableName,
                'country',
                [
                    'type' => Table::TYPE_TEXT,
                    'length' => 10,
                    'nullable' => true,
                    'comment' => 'Country Code',
                    'after' => 'courierName'
                ]
            );

            // Add a new unique constraint on courierId + country combination
            $connection->addIndex(
                $tableName,
                $connection->getIndexName(
                    'innoship_courierlist',
                    ['courierId', 'country'],
                    \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE
                ),
                ['courierId', 'country'],
                \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE
            );

            // Add regular index for country for better query performance
            $connection->addIndex(
                $tableName,
                $connection->getIndexName('innoship_courierlist', ['country']),
                ['country']
            );
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}
