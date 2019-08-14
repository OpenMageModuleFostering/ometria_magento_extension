<?php
/**
 * Class Ometria_Core_Model_Observer_Product
 */
class Ometria_Core_Model_Observer_Product {

    /**
     * Catalog Product Delete After
     *
     * @param Varien_Event_Observer $observer
     * @return Ometria_Core_Model_Observer_Product
     */
    public function catalogProductDeleteAfter(Varien_Event_Observer $observer) {
        Varien_Profiler::start("Ometria::" . __METHOD__);

        $product = $observer->getEvent()->getProduct();
        $this->updateProducts($product->getId());

        Varien_Profiler::stop("Ometria::" . __METHOD__);

        return $this;
    }

    /**
     * Catalog Product Save After
     *
     * @param Varien_Event_Observer $observer
     * @return Ometria_Core_Model_Observer_Product
     */
    public function catalogProductSaveAfter(Varien_Event_Observer $observer) {
        Varien_Profiler::start("Ometria::" . __METHOD__);

        $product = $observer->getEvent()->getProduct();
        $this->updateProducts($product->getId());

        Varien_Profiler::stop("Ometria::" . __METHOD__);

        return $this;
    }

    /**
     * Product Mass Action - Update Attributes
     *
     * @param Varien_Event_Observer $observer
     * @return Ometria_Core_Model_Observer_Product
     */
    public function catalogProductUpdateAttributes(Varien_Event_Observer $observer) {
        Varien_Profiler::start("Ometria::" . __METHOD__);

        $productIds = Mage::helper('adminhtml/catalog_product_edit_action_attribute')->getProductIds();
        $this->updateProducts($productIds);

        Varien_Profiler::stop("Ometria::" . __METHOD__);

        return $this;
    }

    /**
     * Product Mass Action - Update Status
     *
     * @param Varien_Event_Observer $observer
     * @return Ometria_Core_Model_Observer_Product
     */
    public function catalogProductUpdateStatus(Varien_Event_Observer $observer) {
        Varien_Profiler::start("Ometria::" . __METHOD__);

        $productIds = Mage::app()->getFrontController()->getRequest()->getParam('product');
        $this->updateProducts($productIds);

        Varien_Profiler::stop("Ometria::" . __METHOD__);

        return $this;
    }


    /**
     * Pass product ids to Ometria API model
     *
     * @param $ids
     * @return bool
     *
     */
    protected function updateProducts($ids) {
        $ometria_ping_helper = Mage::helper('ometria/ping');
        $ometria_product_helper = Mage::helper('ometria/product');

        $ids = $ometria_product_helper->convertProductIdsIfNeeded($ids);

        $ometria_ping_helper->sendPing('product', $ids);
    }
}