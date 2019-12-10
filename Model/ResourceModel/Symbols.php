<?php

namespace MageSuite\ProductSymbols\Model\ResourceModel;

class Symbols extends \Magento\Catalog\Model\ResourceModel\AbstractResource
{
    protected $storeId;

    public function getEntityType()
    {
        if (empty($this->_type)) {
            $this->setType(\MageSuite\ProductSymbols\Model\Symbols::ENTITY);
        }
        return parent::getEntityType();
    }

    public function setDefaultStoreId($storeId){

        $this->storeId = $storeId;

        return $this;
    }

    /**
     * Returns default Store ID
     *
     * @return int
     */
    public function getDefaultStoreId()
    {
        if($this->storeId == null){
            return \Magento\Store\Model\Store::DEFAULT_STORE_ID;
        }
        return $this->storeId;
    }

    public function updateAttribute($object, $attribute, $value, $storeId){
        if($attribute->getBackendType() != 'static'){
            $this->_updateAttributeForStore($object, $attribute, $value, $storeId);
        }
    }

    /**
     * Update attribute value for specific store
     *
     * @param \Magento\Catalog\Model\AbstractModel $object
     * @param object $attribute
     * @param mixed $value
     * @param int $storeId
     * @return $this
     */
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

    public function getAttributeRawValue($entityId, $attribute, $store) {
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

}