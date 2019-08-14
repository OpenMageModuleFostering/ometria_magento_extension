<?php
class Ometria_Api_Model_Api extends Mage_Api_Model_Resource_Abstract {

    /**
     * Return current Ometria API version
     */
    public function version(){
        return "3.0";
    }

    public function get_stock_levels($ids){
        return $this->proxyApi('Mage_CatalogInventory_Model_Stock_Item_Api', 'items', array($ids));
    }

    public function list_stores(){
        return $this->proxyApi('Mage_Core_Model_Store_Api', 'items');
    }

    public function list_categories(){
        return $this->proxyApi('Mage_Catalog_Model_Category_Api', 'tree');
    }

    public function list_attributes(){
        $attr_sets = $this->proxyApi('Mage_Catalog_Model_Product_Attribute_Set_Api', 'items');

        foreach($attr_sets as &$attr_set){
            $attr_set['attributes'] = $this->proxyApi('Mage_Catalog_Model_Product_Attribute_Api', 'items', array($attr_set['set_id']));
        }

        return $attr_sets;
    }

    public function list_attribute_options($attributeId){
        return $this->proxyApi('Mage_Catalog_Model_Product_Attribute_Api', 'options', array($attributeId));
    }

    protected function proxyApi($model_class, $method, $args=array()){
        $m = new $model_class();
        return call_user_func_array(array($m, $method), $args);
    }

    public function list_subscribers($page=1, $pageSize=100){
        $collection = Mage::getModel('newsletter/subscriber')->getCollection();

        // setPage does not exist on this collection.
        // Access lower lever setters.
        $collection->setCurPage($page);
        $collection->setPageSize($pageSize);

        $collection->load();

        $ret = array();

        foreach($collection as $item){
            $ret[] = array(
                'customer_id'=>$item->customer_id,
                'email'=>$item->subscriber_email,
                'store_id'=>$item->store_id,
                'status'=>$item->subscriber_status
                );
        }

        return $ret;
    }

    public function list_customers($filters, $page = null, $pageSize = null) {
        list ($from, $to, $page, $pageSize) = $this->_handleParameters($filters, $page, $pageSize);

        $collection = Mage::getModel('customer/customer')->getCollection();

        if ($from) $collection->addAttributeToFilter('updated_at', array('gteq' => $from->format('Y-m-d H:i:s')));
        if ($to) $collection->addAttributeToFilter('updated_at', array('lteq' => $to->format('Y-m-d H:i:s')));

        if ($page && $pageSize) {
            $collection->setPage($page, $pageSize);
        }
        $collection->load();

        return $collection->getLoadedIds();
    }

    public function get_customers($ids) {
        /*$submodel = null;
        try{
            $submodel = Mage::getModel('newsletter/subscriber');
        } catch (Exception $e) {
            // pass
            $submodel = null;
        }*/

        $subscriptions_by_customer_id = $this->_load_customer_subscriptions($ids);

        $m = new Mage_Customer_Model_Customer_Api();
        $ret = array();
        foreach($ids as $id){
            try{
                $info = $m->info($id);
            } catch(Exception $e){
                $info = false;
            }
            $ret[$id] = $info;
            if (!$info) continue;

            $email = $info['email'];
            /*try{
                $subscriber = $submodel->loadByEmail($email);
                $is_subscribed = ($subscriber && $subscriber->subscriber_email==$email);
                $ret[$id]['subscription'] = $is_subscribed  ? $subscriber->toArray() : false;
            } catch(Exception $e){
                // pass
            }*/

            $subscription = isset($subscriptions_by_customer_id[$id]) ? $subscriptions_by_customer_id[$id] : null;
            $is_subscribed = ($subscription && $subscription['subscriber_email']==$email);
            $ret[$id]['subscription'] = $is_subscribed  ? $subscription : false;
        }
        return $ret;
    }

    private function _load_customer_subscriptions($customer_ids){
        if (!$customer_ids) return array();

        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $core_resource = Mage::getSingleton('core/resource');

        $select = $db->select()
            ->from(
                array(
                    's' => $core_resource->getTableName('newsletter_subscriber')
                    ),
                array('*')
                )
            ->where('customer_id IN (?)', $customer_ids);

        $rows =  $db->fetchAll($select);

        $ret = array();

        foreach($rows as $row){
            $ret[$row['customer_id']] = $row;
        }

        return $ret;
    }

    public function customer_collection_size($filters = null) {
        list ($from, $to) = $this->_handleParameters($filters, null, null);

        $collection = Mage::getModel('customer/customer')->getCollection();

        if ($from && $to) {
            $collection->addAttributeToFilter('updated_at', array('gteq' => $from->format('Y-m-d H:i:s')));
            $collection->addAttributeToFilter('updated_at', array('lteq' => $to->format('Y-m-d H:i:s')));
        }

        return $collection->getSize();
    }

    /**
     * API method for listing products IDs updated between a provided date range.
     *
     * If from date or to date is absent, or empty, then we return ALL product ids.
     * If $page or $pageSize is absent, then we return an all results. Note, this is not advised for sites with a large
     * product collection since you'll likely run out of memory.
     *
     * @param array $filters accepts an array of filters to apply to the colleciton. Currently supports just updatedFrom
     *              and updatedTo, which are date strings in ISO8601 format.
     * @param int $page an integer denoting the current page. Note, Magento indexes collection pages at 1.
     * @param int $pageSize an integer denoting the page size.
     * @return array
     */
    public function list_products($filters, $page = null, $pageSize = null) {
        list ($from, $to, $page, $pageSize) = $this->_handleParameters($filters, $page, $pageSize);

        $collection = Mage::getModel('catalog/product')->getCollection();

        if ($from && $to) {
            $collection->addAttributeToFilter('updated_at', array('gteq' => $from->format('Y-m-d H:i:s')));
            $collection->addAttributeToFilter('updated_at', array('lteq' => $to->format('Y-m-d H:i:s')));
        }

        if ($page && $pageSize) {
            $collection->setPage($page, $pageSize);
        }

        $collection->load();

        $ometria_product_helper = Mage::helper('ometria/product');
        return $ometria_product_helper->getIdentifiersForProducts($collection);
    }

    public function get_products($ids) {

        $ometria_product_helper = Mage::helper('ometria/product');
        $is_sku_mode = $ometria_product_helper->isSkuMode();

        $configurable_product_model = Mage::getModel('catalog/product_type_configurable');
        $product = Mage::getModel('catalog/product');
        $productMediaConfig = Mage::getModel('catalog/product_media_config');


        $m = new Mage_Catalog_Model_Product_Api();

        $rewrites = $this->_load_product_rewrites($ids);

        $ret = array();
        foreach($ids as $id){
            try{
                if ($is_sku_mode){
                    $info = $m->info($id, null, null, 'sku');
                    $info['_product_id'] = $info['product_id'];
                    $info['product_id'] = $info['sku'];
                } else {
                    $info = $m->info($id);
                }

                // Additional code to return parent information if available
                if ($info['type'] == "simple"){
                    if($parentIds = $configurable_product_model->getParentIdsByChild($info['product_id'])) {
                        $info['parent_product_ids'] = $parentIds;
                    }
                }

                // Get Image URL
                $product->load($id);
                if ($product && $product->getId()==$info['product_id']){
                    $imageUrl = $productMediaConfig->getMediaUrl($product->getSmallImage());
                    if (!$imageUrl) $imageUrl = $product->getImageUrl();
                    $info['image_url'] = $imageUrl;

                    $imageUrl = $productMediaConfig->getMediaUrl($product->getThumbnail());
                    $info['image_thumb_url'] = $imageUrl;
                }

                // URLs
                $info['urls'] = isset($rewrites[$id]) ? array_values($rewrites[$id]) : array();

                // Stock
                try{
                    $stock_item = Mage::getModel('cataloginventory/stock_item')->loadByProduct($id);
                    $stock = array();
                    $stock['qty'] = $stock_item->getQty();
                    $stock['is_in_stock'] = $stock_item->getIsInStock();
                    $info['stock'] = $stock;
                } catch(Exception $e){
                    // pass
                }

            } catch(Exception $e){
                $info = false;
            }
            $ret[$id] = $info;
        }
        return $ret;
    }

    private function _load_product_rewrites($ids){
        if (!$ids) return array();
        try{
            $db = Mage::getSingleton('core/resource')->getConnection('core_read');
            $core_resource = Mage::getSingleton('core/resource');

            $select = $db->select()
                ->from(
                    array(
                        's' => $core_resource->getTableName('core_url_rewrite')
                        ),
                    array('store_id', 'product_id','request_path')
                    )
                ->where('product_id IN (?)', $ids)
                ->order(new Zend_Db_Expr('category_id IS NOT NULL DESC'));

            $rows =  $db->fetchAll($select);

            $ret = array();

            $store_url_cache = array();

            foreach($rows as $row){
                $product_id = $row['product_id'];
                $store_id = $row['store_id'];

                if (!isset($store_url_cache[$store_id])) {
                    $store_url_cache[$store_id]=Mage::app()
                        ->getStore($store_id)
                        ->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);
                }

                $store_url = rtrim($store_url_cache[$store_id], '/').'/';

                $ret[$product_id][$store_id] = array(
                    'store'=>$store_id,
                    'url' => $store_url.$row['request_path']
                    );
            }

            return $ret;
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * API method for listing order increment IDs updated between a provided date range.
     *
     * If from date or to date is absent, or empty, then we return ALL ids.
     * If $page or $pageSize is absent, then we return an all results. Note, this is not advised for sites with a large
     * product collection since you'll likely run out of memory.
     *
     * @param array $filters accepts an array of filters to apply to the colleciton. Currently supports just updatedFrom
     *              and updatedTo, which are date strings in ISO8601 format.
     * @param int $page an integer denoting the current page. Note, Magento indexes collection pages at 1.
     * @param int $pageSize an integer denoting the page size.
     * @return array
     */
    public function list_transactions($filters = null, $page = null, $pageSize = null) {
        list ($from, $to, $page, $pageSize) = $this->_handleParameters($filters, $page, $pageSize);

        $collection = Mage::getModel('sales/order')->getCollection();

        if ($from && $to) {
            $collection->addAttributeToFilter('updated_at', array('gteq' => $from->format('Y-m-d H:i:s')));
            $collection->addAttributeToFilter('updated_at', array('lteq' => $to->format('Y-m-d H:i:s')));
        }

        if ($page && $pageSize) {
            $collection->setPage($page, $pageSize);
        }

        $collection->load();

        $ids = array();
        $items = $collection->getItems();

        foreach ($items as $item) {
            $ids []= $item ->getIncrementId();
        }

        return $ids;
    }

    public function get_transactions($ids) {

        $ometria_product_helper = Mage::helper('ometria/product');
        $is_sku_mode = $ometria_product_helper->isSkuMode();

        $m = new Mage_Sales_Model_Order_Api();
        $ret = array();
        foreach($ids as $id){
            try{
                $info = $m->info($id);

                if ($is_sku_mode && isset($info['items'])) {
                    $_items = $info['items'];
                    $items = array();
                    foreach($_items as $item){
                        $item['_product_id'] = $item['product_id'];
                        $item['product_id'] = $item['sku'];
                        $items[] = $item;
                    }
                    $info['items'] = $items;
                }

            } catch(Exception $e){
                $info = false;
            }
            $ret[$id] = $info;
        }
        return $ret;
    }

    /**
     * API method for retrieving the total number of products updated in a date range.
     *
     * If from date or to date is absent, or empty, then we count ALL products.
     *
     * @param array $filters accepts an array of filters to apply to the colleciton. Currently supports just updatedFrom
     *              and updatedTo, which are date strings in ISO8601 format.
     * @return int
     */
    public function product_collection_size($filters = null) {
        list ($from, $to) = $this->_handleParameters($filters, null, null);

        $collection = Mage::getModel('catalog/product')->getCollection();

        if ($from && $to) {
            $collection->addAttributeToFilter('updated_at', array('gteq' => $from->format('Y-m-d H:i:s')));
            $collection->addAttributeToFilter('updated_at', array('lteq' => $to->format('Y-m-d H:i:s')));
        }

        return $collection->getSize();
    }

    /**
     * API method for retrieving the total number of transaction updated in a date range.
     *
     * If from date or to date is absent, or empty, then we count ALL products.
     *
     * @param array $filters accepts an array of filters to apply to the colleciton. Currently supports just updatedFrom
     *              and updatedTo, which are date strings in ISO8601 format.
     * @return int
     */
    public function transaction_collection_size($filters = null) {
        list ($from, $to) = $this->_handleParameters($filters, null, null);

        $collection = Mage::getModel('sales/order')->getCollection();

        if ($from && $to) {
            $collection->addAttributeToFilter('updated_at', array('gteq' => $from->format('Y-m-d H:i:s')));
            $collection->addAttributeToFilter('updated_at', array('lteq' => $to->format('Y-m-d H:i:s')));
        }

        return $collection->getSize();
    }

    /**
     * Helper method to validate parameters
     *
     * @param array $filters accepts an array of filters to apply to the colleciton. Currently supports just updatedFrom
     *              and updatedTo, which are date strings in ISO8601 format.
     * @param int $page an integer denoting the current page. Note, Magento indexes collection pages at 1.
     * @param int $pageSize an integer denoting the page size.
     * @return array
     */
    protected function _handleParameters($filters, $page, $pageSize) {
        //Mage::log(sprintf("%s", print_r($filters, true)), Zend_Log::DEBUG, 'martin_dev.log', true);
        $updatedFrom = false;
        $updatedTo = false;
        if (isset($filters['updatedFrom']) && $filters['updatedFrom']) {
            if (!$updatedFrom = date_create_from_format(DATE_ISO8601, $filters['updatedFrom'])) {
                $this->_fault('data_invalid', sprintf('Invalid from date passed. "%s" is not ISO8601 format.', $filters['updatedFrom']));
            }
        }
        if (isset($filters['updatedTo']) && $filters['updatedTo']){
            if (!$updatedTo = date_create_from_format(DATE_ISO8601, $filters['updatedTo'])) {
                $this->_fault('data_invalid', sprintf('Invalid to date passed. "%s" is not ISO8601 format.', $filters['updatedTo']));
            }
        }
        if ($updatedFrom && $updatedTo){
            if ($updatedTo < $updatedFrom) {
                $this->_fault('data_invalid', sprintf('To date cannot be less than from date.'));
            }
        }

        // Validate page parameters
        if ($page && !is_int($page)) {
            $this->_fault('data_invalid', sprintf('Invalid page parameter passed, expected int and got "%s"', $page));
        }

        if ($pageSize && !is_int($pageSize)) {
            $this->_fault('data_invalid', sprintf('Invalid pageSize parameter passed, expected int and got "%s"', $pageSize));
        }

        if (!$page) $page = 1;
        if (!$pageSize) $pageSize = 50;

        return array($updatedFrom, $updatedTo, $page, $pageSize);
    }
}