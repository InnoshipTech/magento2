<?php

namespace InnoShip\InnoShip\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;

class AddCustomerLockerAttributes implements DataPatchInterface
{
    private $moduleDataSetup;
    private $customerSetupFactory;
    private $eavSetupFactory;
    private $attributeSetFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CustomerSetupFactory $customerSetupFactory,
        EavSetupFactory $eavSetupFactory,
        AttributeSetFactory $attributeSetFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->customerSetupFactory = $customerSetupFactory;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->attributeSetFactory = $attributeSetFactory;
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $customerEntity = $customerSetup->getEavConfig()->getEntityType('customer');
        $attributeSetId = $customerEntity->getDefaultAttributeSetId();
        $attributeGroupId = $this->attributeSetFactory->create()->getDefaultGroupId($attributeSetId);


        // Favorite Locker (int)
        $customerSetup->addAttribute(Customer::ENTITY, 'favorite_locker', [
            'type'         => 'int',
            'label'        => 'Favorite Locker',
            'input'        => 'text',
            'required'     => false,
            'visible'      => true,
            'user_defined' => true,
            'system'       => 0,
            'position'     => 999
        ]);

        $attr1 = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, 'favorite_locker');
        $attr1->addData([
            'attribute_set_id' => $attributeSetId,
            'attribute_group_id' => $attributeGroupId,
            'used_in_forms' => [
                'adminhtml_customer',
                'customer_account_create',
                'customer_account_edit'
            ]
        ]);
        $attr1->save();

        // Favorite Locker Name (varchar)
        $customerSetup->addAttribute(Customer::ENTITY, 'favorite_locker_name', [
            'type'         => 'varchar',
            'label'        => 'Favorite Locker Name',
            'input'        => 'text',
            'required'     => false,
            'visible'      => true,
            'user_defined' => true,
            'system'       => 0,
            'position'     => 1000,
        ]);

        $attr2 = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, 'favorite_locker_name');
        $attr2->addData([
            'attribute_set_id' => $attributeSetId,
            'attribute_group_id' => $attributeGroupId,
            'used_in_forms' => [
                'adminhtml_customer',
                'customer_account_create',
                'customer_account_edit'
            ]
        ]);
        $attr2->save();

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    public static function getDependencies() {
        return [];
    }

    public function getAliases() {
        return [];
    }
}
