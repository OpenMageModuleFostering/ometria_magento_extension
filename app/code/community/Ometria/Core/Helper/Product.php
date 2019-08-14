<?php

class Ometria_Core_Helper_Product extends Mage_Core_Helper_Abstract {

    public function isSkuMode(){
        return Mage::getStoreConfig('ometria/advanced/productmode')=='sku';
    }

    public function getIdentifierForProduct($product) {
        if (!$product) return null;


        if ($this->isSkuMode()) {
            return $product->getSku();
        } else {
            return $product->getId();
        }
    }

    public function getIdentifiersForProducts($products) {

        $is_sku_mode = $this->isSkuMode();

        $ret = array();
        foreach($products as $product){
            if ($is_sku_mode) {
                $ret[] = $product->getSku();
            } else {
                $ret[] = $product->getId();
            }
        }

        return $ret;

    }

    public function convertProductIdsIfNeeded($ids){

        if (!$this->isSkuMode()) {
            return $ids;
        }

        if (!$ids) return $ids;

        $was_array = is_array($ids);
        if (!is_array($ids)) $ids = array($ids);

        $products_collection = Mage::getModel('catalog/product')
                ->getCollection()
                ->addAttributeToFilter('entity_id', array('in' => $ids));

        $skus = array();
        foreach($products_collection as $product) {
            $skus[] =  $product->getSku();
            $product->clearInstance();
        }

        if (!$was_array) {
            return count($skus)>0 ? $skus[0] : null;
        } else {
            return $skus;
        }
    }

    public function getProductByIdentifier($id){
        $product_model = Mage::getModel('catalog/product');

        if ($this->isSkuMode()){
            return $product_model->load($product_model->getIdBySku($id));
        } else {
            return $product_model->load($id);
        }
    }
}