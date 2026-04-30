<?php

namespace InnoShip\InnoShip\Setup\Patch\Schema;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class CreateExternalTable implements SchemaPatchInterface
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

        $tableName = $this->moduleDataSetup->getTable('innoship_external');
        $connection = $this->moduleDataSetup->getConnection();

        if (!$connection->isTableExists($tableName)) {
            $table = $connection->newTable($tableName)
                ->addColumn(
                    'id',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'identity' => true,
                        'unsigned' => true,
                        'nullable' => false,
                        'primary' => true
                    ],
                    'ID'
                )
                ->addColumn(
                    'external',
                    Table::TYPE_TEXT,
                    255,
                    ['nullable' => false],
                    'External Location ID'
                )
                ->addColumn(
                    'countryName',
                    Table::TYPE_TEXT,
                    255,
                    ['nullable' => false],
                    'Country Name'
                )
                ->addColumn(
                    'countryCode',
                    Table::TYPE_TEXT,
                    10,
                    ['nullable' => false],
                    'Country Code'
                )
                ->addIndex(
                    $connection->getIndexName(
                        'innoship_external',
                        ['external'],
                        \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE
                    ),
                    ['external'],
                    ['type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE]
                )
                ->addIndex(
                    $connection->getIndexName('innoship_external', ['countryCode']),
                    ['countryCode']
                )
                ->setComment('InnoShip External Locations');

            $connection->createTable($table);
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
