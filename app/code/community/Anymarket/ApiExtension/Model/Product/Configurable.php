<?php

class Anymarket_ApiExtension_Model_Product_Configurable extends Mage_Catalog_Model_Api_Resource
{
    
    public function setConfigurableAttributes($sku, $attributes)
    {
        if (empty($sku) || empty($attributes)) {
            $this->_fault('data_invalid', "At least one of the required parameters was not set.");
        }
        // load product
        $product = $this->loadProduct($sku, Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE);

        // load attribute ids
        if (!is_array($attributes)) {
            $attributes = array($attributes);
        }
        $attributeModel = Mage::getModel('eav/config');
        $attrs = array();
        try {
            foreach ($attributes as $attribute) {
                $attr = $attributeModel->getAttribute('catalog_product', $attribute);
                if ( !$attr->getAttributeId() && $product->getTypeInstance()->canUseAttribute($attr) ) {
                    Mage::throwException('invalid attribute');
                }
                $attrs[] = $attr->getAttributeId();
            }
        } catch (Exception $e) {
            $this->_fault('attribute_invalid');
        }

        // set product configurable-on attributes
        try {
            $product->getTypeInstance()->setUsedProductAttributeIds($attrs);
            $product->setConfigurableAttributesData($product->getTypeInstance()->getConfigurableAttributesAsArray());
            $product->setCanSaveConfigurableAttributes(true);
            $product->setCanSaveCustomOptions(true);
            $product->save();
        } catch (Exception $e) {
            $this->_fault('unknown');
        }
        
        return 'success';
        
    }

    public function associateSimpleChildren($parentSku, $childrenSkus)
    {
        
        if (empty($parentSku) || empty($childrenSkus)) {
            $this->_fault('data_invalid', "At least one of the required parameters was not set.");
        }
        
        // load parent product
        $product = $this->loadProduct($parentSku, Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE);
        
        // load children products
        if (!is_array($childrenSkus)) {
            $childrenSkus = array($childrenSkus);
        }
        $children = array();
        foreach ($childrenSkus as $childSku) {
            $child = $this->loadProduct($childSku, Mage_Catalog_Model_Product_Type::TYPE_SIMPLE);
            $children[$child->getId()] = $child;
        }

        // save
        try {
            $product->setConfigurableProductsData($children);
            $product->save();
        } catch (Exception $e) {
            $this->_fault('unknown');
        }
        
        return 'success';
        
    }

    public function getConfigurableBySimple($sku)
    {
        $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);
        if($product == null) {
            return "";
        }

        $parentIds = Mage::getResourceSingleton('catalog/product_type_configurable')
              ->getParentIdsByChild($product->getId());

        return Mage::getModel('catalog/product')->load($parentIds[0])->getSku();
    }

    public function getAllSimpleByConfigurable($sku)
    {
        $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);
        if($product == null) {
            return array();
        }

        $childProducts = Mage::getModel('catalog/product_type_configurable')
                            ->getChildrenIds($product->getId());
        $jsonRet = array();
        foreach ($childProducts as $key => $value) {
            array_push($jsonRet, $value);
        }

        return $jsonRet;
    }

    
    protected function loadProduct($sku, $type)
    {
        $product = Mage::getModel('catalog/product');
        try {
            $id = $product->getIdBySku($sku);
            if (empty($id)) {
                Mage::throwException('bad sku');
            }
            $product->load($id);
            if ($product->getTypeId() != $type) {
                Mage::throwException('not a configurable product');
            }
        } catch (Exception $e) {
            $this->_fault('product_not_exist', 'Invalid product sku: '.$sku);
        }
        return $product;
    }

}