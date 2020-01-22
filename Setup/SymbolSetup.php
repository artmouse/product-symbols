<?php

namespace MageSuite\ProductSymbols\Setup;

class SymbolSetup extends \Magento\Eav\Setup\EavSetup
{
    public function getDefaultEntities()
    {
        $symbolsEntity = \MageSuite\ProductSymbols\Model\Symbol::ENTITY;

        $entities = [
            $symbolsEntity => [
                'entity_model' => \MageSuite\ProductSymbols\Model\ResourceModel\Symbol::class,
                'attribute_model' => \Magento\Catalog\Model\ResourceModel\Eav\Attribute::class,
                'table' => $symbolsEntity . '_entity',
                'entity_attribute_collection' => \Magento\Eav\Model\ResourceModel\Entity\Attribute\Collection::class,
                'attributes' => [
                    'symbol_name' => [
                        'type' => 'varchar',
                        'label' => 'Symbol Name',
                        'input' => 'text',
                        'frontend_class' => 'validate-length maximum-length-255',
                        'sort_order' => 1,
                        'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                    ],
                    'symbol_icon' => [
                        'type' => 'varchar',
                        'label' => 'Symbol Icon',
                        'input' => 'text',
                        'required' => false,
                        'sort_order' => 2,
                        'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                    ],
                    'symbol_icon_url' => [
                        'type' => 'varchar',
                        'label' => 'Symbol Icon Url',
                        'input' => 'text',
                        'required' => false,
                        'sort_order' => 3,
                        'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                    ],
                    'symbol_short_description' => [
                        'type' => 'text',
                        'label' => 'Short Description',
                        'input' => 'textarea',
                        'required' => false,
                        'sort_order' => 4,
                        'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                    ],
                    'symbol_groups' => [
                        'type' => 'varchar',
                        'label' => 'Symbol Groups',
                        'input' => 'multiselect',
                        'required' => false,
                        'sort_order' => 5,
                        'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                        'backend' => \Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend::class,
                        'source_model' => \MageSuite\ProductSymbols\Model\Source\GroupList::class
                    ]
                ]
            ]
        ];
        return $entities;
    }
}
