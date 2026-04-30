<?php
namespace InnoShip\InnoShip\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Config\ConfigOptionsListConstants;

class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * @var \Magento\Framework\App\DeploymentConfig
     */
    protected $deploymentConfig;
    public function __construct(
        \Magento\Framework\App\DeploymentConfig  $deploymentConfig
    ) {

        $this->deploymentConfig = $deploymentConfig;
    }
    public function getTablePrefix()
    {
        return $this->deploymentConfig->get(
            ConfigOptionsListConstants::CONFIG_PATH_DB_PREFIX
        );
    }

    public function upgrade( SchemaSetupInterface $setup, ModuleContextInterface $context ) {
        $installer = $setup;

        $installer->startSetup();

        if(version_compare($context->getVersion(), '1.1.3', '<=')) {
            $tableName = $installer->getTable( 'innoship_pudo' );
            $isExist =  $setup->getConnection()->tableColumnExists(
                $setup->getTable($tableName),
                "pudo_id"
            );
            if ((null !== $isExist) && (!$isExist))
                $installer->getConnection()->query("ALTER TABLE ".$tableName." ADD UNIQUE ".$this->getTablePrefix()."INNOSHIP_PUDO_PUDO_ID (`pudo_id`);");
            $tableNameC = $installer->getTable( 'innoship_courierlist' );
            $isExistC =  $setup->getConnection()->tableColumnExists(
                $setup->getTable($tableNameC),
                "courierId"
            );
            if ((null !== $isExistC) && (!$isExistC))
                $installer->getConnection()->query("ALTER TABLE ".$tableNameC." ADD UNIQUE ".$this->getTablePrefix()."INNOSHIP_PUDO_COURIER_ID (`courierId`);");
        }

        $installer->endSetup();
    }
}
