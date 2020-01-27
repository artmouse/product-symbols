<?php

namespace MageSuite\ProductSymbols\Model\ResourceModel;

class Symbol extends \Magento\Eav\Model\Entity\AbstractEntity
{
    protected $symbolAttributes = [
        'entity_id',
        'store_id',
        'symbol_name',
        'symbol_icon',
        'symbol_icon_url',
        'symbol_short_description',
        'symbol_groups',
    ];

    protected $storeId;
    /**
     * @var \MageSuite\ProductSymbols\Model\ResourceModel\Group\CollectionFactory
     */
    protected $groupCollectionFactory;
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\Action
     */
    protected $productResourceAction;
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $productCollectionFactory;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    public function __construct(
        \Magento\Eav\Model\Entity\Context $context,
        \MageSuite\ProductSymbols\Model\ResourceModel\Group\CollectionFactory $groupCollectionFactory,
        \Magento\Catalog\Model\ResourceModel\Product\Action $productResourceAction,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        $data = [],
        \Magento\Eav\Model\Entity\Attribute\UniqueValidationInterface $uniqueValidator = null
    ) {
        parent::__construct($context, $data, $uniqueValidator);
        $this->groupCollectionFactory = $groupCollectionFactory;
        $this->productResourceAction = $productResourceAction;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->storeManager = $storeManager;
    }

    public function getEntityType()
    {
        \Magento\Catalog\Model\ResourceModel\AbstractResource::class;
        if (empty($this->_type)) {
            $this->setType(\MageSuite\ProductSymbols\Model\Symbol::ENTITY);
        }
        return parent::getEntityType();
    }

    public function setDefaultStoreId($storeId)
    {
        $this->storeId = $storeId;

        return $this;
    }

    public function getDefaultStoreId()
    {
        if ($this->storeId == null) {
            return \Magento\Store\Model\Store::DEFAULT_STORE_ID;
        }
        return $this->storeId;
    }

    public function updateAttribute($object, $attribute, $value, $storeId)
    {
        if ($attribute->getBackendType() != 'static') {
            $this->_updateAttributeForStore($object, $attribute, $value, $storeId);
        }
    }

    protected function _updateAttributeForStore($object, $attribute, $value, $storeId)
    {
        $connection = $this->getConnection();
        $table = $attribute->getBackend()->getTable();
        $entityIdField = $this->getLinkField();
        $select = $connection->select()
            ->from($table, 'value_id')
            ->where("$entityIdField = :entity_field_id")
            ->where('store_id = :store_id')
            ->where('attribute_id = :attribute_id');
        $bind = [
            'entity_field_id' => $object->getId(),
            'store_id' => $storeId,
            'attribute_id' => $attribute->getId(),
        ];
        $valueId = $connection->fetchOne($select, $bind);

        if ($valueId) {
            $bind = ['value' => $this->_prepareValueForSave($value, $attribute)];
            $where = ['value_id = ?' => (int) $valueId];

            $connection->update($table, $bind, $where);
        } else {
            $bind = [
                $entityIdField => (int) $object->getId(),
                'attribute_id' => (int) $attribute->getId(),
                'value' => $this->_prepareValueForSave($value, $attribute),
                'store_id' => (int) $storeId,
            ];

            $connection->insert($table, $bind);
        }

        return $this;
    }

    public function removeAttribute($symbol, $attributes)
    {
        foreach ($attributes as $attribute) {
            $attr = $this->getAttribute($attribute);
            $this->removeAttributeForStore($symbol, $attr, $symbol->getStoreId());
        }
    }

    protected function removeAttributeForStore($object, $attribute, $storeId)
    {
        $connection = $this->getConnection();
        $table = $attribute->getBackend()->getTable();
        $entityIdField = $this->getLinkField();
        $select = $connection->select()
            ->from($table, 'value_id')
            ->where("$entityIdField = :entity_field_id")
            ->where('store_id = :store_id')
            ->where('attribute_id = :attribute_id');
        $bind = [
            'entity_field_id' => $object->getId(),
            'store_id' => $storeId,
            'attribute_id' => $attribute->getId(),
        ];
        $valueId = $connection->fetchOne($select, $bind);

        $where = ['value_id = ?' => (int) $valueId];
        $connection->delete($table, $where);

        return $this;
    }

    public function getAttributeRawValue($entityId, $attribute, $store)
    {
        $attribute = $this->getAttribute($attribute);
        $connection = $this->getConnection();
        $table = $attribute->getBackend()->getTable();
        $entityIdField = $this->getLinkField();
        $select = $connection->select()
            ->from($table, 'value')
            ->where($entityIdField.' = ?', $entityId)
            ->where('store_id = ?', $store)
            ->where('attribute_id = ?', $attribute->getId());
        $result = $connection->fetchOne($select);

        return $result;
    }

    public function save(\Magento\Framework\Model\AbstractModel $symbol)
    {
        $currentTime = date('Y-m-d H:i:s');
        $symbol->setUpdatedAt($currentTime);
        $isNew = empty($symbol['entity_id']) ? true : false;

        if ($isNew) {
            $symbol->setCreatedAt($currentTime);
            return parent::save($symbol);
        }

        $attributesToRemove = $this->symbolAttributes;
        foreach ($symbol->getData() as $key => $value) {
            $attr = $this->getAttribute($key);
            $attributeIndex = array_search($key, $attributesToRemove);
            if (false !== $attributeIndex) {
                unset($attributesToRemove[$attributeIndex]);
            }

            if (!$attr) {
                continue;
            }

            $this->updateAttribute($symbol, $attr, $value, $symbol->getStoreId());
        }
        $this->removeAttribute($symbol, $attributesToRemove);

        return $this;
    }

    protected function _afterDelete(\Magento\Framework\DataObject $object)
    {
        $groupCollection = $this->groupCollectionFactory->create();
        $groupCollection->addFieldToFilter('entity_id', ['in' => explode(',', $object->getSymbolGroups())]);

        foreach ($groupCollection as $group) {
            $productsCollection = $this->productCollectionFactory->create();
            $productsCollection->addAttributeToFilter($group->getGroupCode(), ['finset' => $object->getEntityId()]);

            foreach ($productsCollection as $product) {
                $groupAttribute = $product->getData($group->getGroupCode());

                $groupAttribute = explode(',', $groupAttribute);

                $groupAttribute = array_diff($groupAttribute, [$object->getEntityId()]);

                $this->productResourceAction->updateAttributes(
                    [$product->getId()],
                    [$group->getGroupCode() => implode(',', $groupAttribute)],
                    \Magento\Store\Model\Store::DEFAULT_STORE_ID
                );
            }
        }

        return parent::_afterDelete($object); // TODO: Change the autogenerated stub
    }

    protected function _getLoadAttributesSelect($object, $table)
    {
        if ($this->storeManager->hasSingleStore()) {
            $storeId = (int) $this->storeManager->getStore(true)->getId();
        } else {
            $storeId = (int) $object->getStoreId();
        }

        $setId = $object->getAttributeSetId();
        $storeIds = [$this->getDefaultStoreId()];
        if ($storeId != $this->getDefaultStoreId()) {
            $storeIds[] = $storeId;
        }

        $select = $this->getConnection()
            ->select()
            ->from(['attr_table' => $table], [])
            ->where("attr_table.{$this->getLinkField()} = ?", $object->getData($this->getLinkField()))
            ->where('attr_table.store_id IN (?)', $storeIds);

        if ($setId) {
            $select->join(
                ['set_table' => $this->getTable('eav_entity_attribute')],
                $this->getConnection()->quoteInto(
                    'attr_table.attribute_id = set_table.attribute_id' . ' AND set_table.attribute_set_id = ?',
                    $setId
                ),
                []
            );
        }
        return $select;
    }

    protected function _prepareLoadSelect(array $selects)
    {
        $select = parent::_prepareLoadSelect($selects);
        $select->order('store_id');
        return $select;
    }
}
