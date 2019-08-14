<?php

class Ometria_Core_Block_Head extends Mage_Core_Block_Template {


    const PAGE_TYPE_BASKET       = 'basket';
    const PAGE_TYPE_CHECKOUT     = 'checkout';
    const PAGE_TYPE_CMS          = 'content';
    const PAGE_TYPE_CATEGORY     = 'listing';
    const PAGE_TYPE_CONFIRMATION = 'confirmation';
    const PAGE_TYPE_HOMEPAGE     = 'homepage';
    const PAGE_TYPE_PRODUCT      = 'product';
    const PAGE_TYPE_SEARCH       = 'search';

    const OM_QUERY               = 'query';
    const OM_SITE                = 'store';
    const OM_PAGE_TYPE           = 'type';
    const OM_PAGE_DATA           = 'data';

    const PRODUCT_IN_STOCK       = 'in_stock';

    public function getDataLayer() {
        $category = 'null';
        $page = array();
        $page[self::OM_SITE] = $this->_getStore();
        $page['store_url'] = Mage::getBaseUrl();

        $page['route'] = $this->_getRouteName();
        $page['controller'] = $this->_getControllerName();
        $page['action'] = $this->_getActionName();

        if ($this->_isHomepage()) {
            $page[self::OM_PAGE_TYPE] = self::PAGE_TYPE_HOMEPAGE;

        } elseif ($this->_isCMSPage()) {
            $page[self::OM_PAGE_TYPE] = self::PAGE_TYPE_CMS;

        } elseif ($this->_isCategory()) {
            $page[self::OM_PAGE_TYPE] = self::PAGE_TYPE_CATEGORY;

        } elseif ($this->_isSearch()) {
            $page[self::OM_PAGE_TYPE] = self::PAGE_TYPE_SEARCH;

            if($query = $this->_getSearchQuery()) $page[self::OM_QUERY] = $query;

        } elseif ($this->_isProduct()) {
            $page[self::OM_PAGE_TYPE] = self::PAGE_TYPE_PRODUCT;
            $page[self::OM_PAGE_DATA] = $this->_getProductPageData();
        } elseif ($this->_isBasket()) {
            $page[self::OM_PAGE_TYPE] = self::PAGE_TYPE_BASKET;

        } elseif ($this->_isCheckout()) {
            $page[self::OM_PAGE_TYPE] = self::PAGE_TYPE_CHECKOUT;
            if ($step = $this->_getCheckoutStep()) $page[self::OM_PAGE_DATA] = array('step'=>$step);

        } elseif ($this->_isOrderConfirmation()) {
            $page[self::OM_PAGE_TYPE] = self::PAGE_TYPE_CONFIRMATION;
            $page[self::OM_PAGE_DATA] = $this->_getOrderData();
        }

        if ($category = Mage::registry("current_category")) {
            $page['category'] = array(
                'id'=>$category->getId(),
                'path'=>$category->url_path
                );
        }

        return $page;
    }

    protected function _getCheckoutStep() {
        if(!$this->_isCheckout())
            return false;

        if($step = Mage::app()->getRequest()->getParam('step'))
            return $step;

        return false;
    }

    protected function _getOrderData() {
        if (!$this->_isOrderConfirmation())
            return false;

        if ($orderId = $this->_getCheckoutSession()->getLastOrderId()) {
            /** @var Mage_Sales_Model_Order $order */
            $order = Mage::getModel('sales/order')->load($orderId);

            return array(
                'id'              => $order->getIncrementId()
            );
        }

        return false;
    }

    protected function _getCheckoutSession() {
        if ($this->_isBasket())
            return Mage::getSingleton('checkout/cart');

        return Mage::getSingleton('checkout/session');
    }

    protected function _getProductInStock($product)
    {
        /*$product = Mage::registry("current_product");

        if (!$product && $id = $this->getProductId()) {
            $product = Mage::getModel("catalog/product")->load($id);
        }*/

        $stock = false;
        if ($product) {
            $api = Mage::getModel('cataloginventory/stock_item_api');
            $stock_objects = $api->items(array($product->getId()));
            $stock = array_shift($stock_objects);
        }

        if($stock && array_key_exists('is_in_stock', $stock))
        {
            return (boolean) $stock['is_in_stock'];
        }

        return null;
    }

    protected function _getProductPageData(){

        $product = Mage::registry("current_product");

        if (!$product && $id = $this->getProductId()) {
            $product = Mage::getModel("catalog/product")->load($id);
        }

        if ($product) {
            return $this->_getProductInfo($product);
        }

        return false;
    }

    /**
     * Get limited product info from product
     * Used in listing, baskets, transactions
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    protected function _getProductInfo($product) {
        $ometria_product_helper = Mage::helper('ometria/product');

        if($product instanceof Mage_Catalog_Model_Product) {
            return array(
                'id'                        => $ometria_product_helper->getIdentifierForProduct($product),
                'sku'                       => $product->getSku(),
                'name'                      => $product->getName(),
                'url'                       => $product->getProductUrl(),
                self::PRODUCT_IN_STOCK      => $this->_getProductInStock($product)
            );
        }

        return false;
    }

    /**
     * Get Controller name
     * @return string
     */
    protected function _getControllerName() {
        return $this->getRequest()->getRequestedControllerName();
    }

    /**
     * Get Action name
     * @return string
     */
    protected function _getActionName() {
        return $this->getRequest()->getRequestedActionName();
    }

    /**
     * Get Route name
     * @return string
     */
    protected function _getRouteName() {
        return $this->getRequest()->getRequestedRouteName();
    }

    /**
     * Check if home page
     * @return bool
     */
    protected function _isHomepage() {
        return $this->getUrl('') == $this->getUrl('*/*/*', array('_current'=>true, '_use_rewrite'=>true));
    }

    /**
     * Check if cms page
     * @return bool
     */
    protected function _isCMSPage() {
        return $this->_getRouteName() == 'cms';
    }

    /**
     * Check if category page
     * @return bool
     */
    protected function _isCategory() {
        return $this->_getRouteName()       == 'catalog'
            && $this->_getControllerName()  == 'category';
    }

    /**
     * Check if search page
     * @return bool
     */
    protected function _isSearch() {
        return $this->_getRouteName() == 'catalogsearch';
    }

    /**
     * Check if product page
     * @return bool
     */
    protected function _isProduct() {
        return $this->_getRouteName()      == 'catalog'
            && $this->_getControllerName() == 'product';
    }

    /**
     * Check if basket
     * @return bool
     */
    protected function _isBasket() {
        return $this->_getRouteName()           == 'checkout'
                && $this->_getControllerName()  == 'cart'
                && $this->_getActionName()      == 'index';
    }

    /**
     * Check if checkout
     * @return bool
     */
    protected function _isCheckout() {
        return strpos($this->_getRouteName(), 'checkout') !== false
                && $this->_getActionName()  != 'success';
    }

    /**
     * Check if success page
     * @return bool
     */
    protected function _isOrderConfirmation() {
        return strpos($this->_getRouteName(), 'checkout') !== false
                && $this->_getActionName() == 'success';
    }

    /**
     * Get Store id
     * @return string|int
     */
    protected function _getStore() {
        return Mage::app()->getStore()->getStoreId();
    }

    /**
     * Get search query text
     * @return string
     */
    protected function _getSearchQuery() {
        if(!$this->_isSearch())
            return false;

        return Mage::helper('catalogsearch')->getQueryText();
    }
}
