<?php
class Ometria_Api_Model_Api extends Mage_Api_Model_Resource_Abstract {

    /**
     * Return current Ometria API version
     */
    public function version(){
        $version = current(Mage::getConfig()->getModuleConfig('Ometria_Api')->version);
        return array("branch"=>"new", "version"=>$version);
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

        $attribute_ids_per_store = $this->_getProductAttributeIdAndNameMapping();
        $product_per_store_attributes = $this->_loadProductPerStoreAttributes($ids, $attribute_ids_per_store);

        $website_store_ids = $this->_getWebsitesIdStoreIdsMapping(null);

        $parent_product_ids = $this->_getParentProductsMapping($ids);

        $m = new Mage_Catalog_Model_Product_Api();

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
                    if (isset($parent_product_ids[$id])) {
                        $info['parent_product_ids'] = array($parent_product_ids[$id]);
                    }
                }

                // Get Image URL
                // @todo this can be removed and using $listing[0] in future
                $product->load($id);
                if ($product && $product->getId()==$info['product_id']){
                    $imageUrl = $productMediaConfig->getMediaUrl($product->getSmallImage());
                    if (!$imageUrl) $imageUrl = $product->getImageUrl();
                    $info['image_url'] = $imageUrl;

                    $imageUrl = $productMediaConfig->getMediaUrl($product->getThumbnail());
                    $info['image_thumb_url'] = $imageUrl;
                }

                $website_ids = $info['websites'];
                $store_ids = $this->_getStoreIdsForWebsiteIds($website_ids, $website_store_ids);
                $info['stores'] = $store_ids;

                $listings = isset($product_per_store_attributes[$id]) ? $product_per_store_attributes[$id] : array();
                $listings = $this->_resolveStoreListings($listings, $store_ids, $productMediaConfig);
                $info['store_listings'] = $listings;

            } catch(Exception $e){
                $info = false;
            }
            $ret[$id] = $info;
        }
        return $ret;
    }

    // Get list of attribute_id => attribute_code pairs for product attributes we are interested in
    private function _getProductAttributeIdAndNameMapping(){

        $attribute_codes = array(
            'name',
            'status',
            'visibility',
            'price',
            'special_price',
            'url_path',
            'image',
            'small_image',
            'thumbnail'
            );

        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $core_resource = Mage::getSingleton('core/resource');

        $select = $db->select()
            ->from(
                array('ea' => $core_resource->getTableName('eav_attribute')),
                array('attribute_id','attribute_code')
                )
            ->joinLeft(
                array('et' => $core_resource->getTableName('eav_entity_type')),
                'ea.entity_type_id = et.entity_type_id',
                array()
                )
            ->where('ea.attribute_code IN (?)', $attribute_codes)
            ->where('et.entity_type_code = ?', 'catalog_product');

        $rows = $db->fetchAll($select);
        $ret = array();

        foreach($rows as $row){
            $ret[$row['attribute_id']] = $row['attribute_code'];
        }

        return $ret;
    }

    // Map overridden eav_attributes onto one listing per store
    // using defaults where no override exists for that store
    private function _resolveStoreListings($listings, $store_ids, $productMediaConfig){
        if (!isset($listings[0])) return array();

        $ret = array();
        $default = $listings[0];

        $store_url_cache = array();
        $store_currency_cache = array();

        foreach($store_ids as $store_id){
            $listing = $default;
            if (isset($listings[$store_id])){
                $listing = array_merge($listing, $listings[$store_id]);
            }

            if (!isset($store_url_cache[$store_id])) {
                $store = Mage::app()->getStore($store_id);
                $store_url_cache[$store_id]= $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);
                $store_currency_cache[$store_id] = array($store->getBaseCurrencyCode(), $store->getCurrentCurrencyCode());
            }

            if (isset($listing['url_path'])){
                $store_base_url = rtrim($store_url_cache[$store_id], '/').'/';
                $listing['url'] = $store_base_url . $listing['url_path'];
                unset($listing['url_path']);
            }

            $image_keys = array('image','thumbnail','small_image');
            foreach($image_keys as $key){
                if (isset($listing[$key])){
                    $listing[$key.'_url'] = $productMediaConfig->getMediaUrl($listing[$key]);
                    unset($listing[$key]);
                }
            }

            $store_currency_info = $store_currency_cache[$store_id];
            $listing['store_currency'] = $store_currency_info[1];

            if (isset($listing['price'])){
                $store_price = Mage::helper('directory')->currencyConvert($listing['price'], $store_currency_info[0], $store_currency_info[1]);
                $listing['store_price'] = $store_price;
            }
            if (isset($listing['special_price'])){
                $store_price = Mage::helper('directory')->currencyConvert($listing['special_price'], $store_currency_info[0], $store_currency_info[1]);
                $listing['store_special_price'] = $store_price;
            }

            $listing['store_id'] = $store_id;
            $ret[] = $listing;
        }

        return $ret;
    }

    // For a given list of products load all the per store eav attribute values
    private function _loadProductPerStoreAttributes($ids, $attribute_types){
        if (!$attribute_types) return array();
        $attribute_ids = array_keys($attribute_types);

        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $core_resource = Mage::getSingleton('core/resource');

        $ret = array();
        $this-> _loadProductPerStoreAttributesValues($ret, $ids, $attribute_types, $db, $core_resource->getTableName('catalog_product_entity_varchar'));
        $this-> _loadProductPerStoreAttributesValues($ret, $ids, $attribute_types, $db, $core_resource->getTableName('catalog_product_entity_decimal'));
        $this-> _loadProductPerStoreAttributesValues($ret, $ids, $attribute_types, $db, $core_resource->getTableName('catalog_product_entity_int'));

        return $ret;
    }
    private function _loadProductPerStoreAttributesValues(&$ret, $ids, $attribute_types, $db, $table_name){
        if (!$attribute_types) return array();
        $attribute_ids = array_keys($attribute_types);

        $select = $db->select()
            ->from(
                array(
                    's' => $table_name
                    ),
                array('entity_id', 'store_id','attribute_id', 'value')
                )
            ->where('entity_id IN (?)', $ids)
            ->where('attribute_id IN (?)', $attribute_ids);

        $rows =  $db->fetchAll($select);

        foreach($rows as $row){
            $product_id = $row['entity_id'];
            $attribute_id = $row['attribute_id'];
            $key = $attribute_types[$attribute_id];
            $value = $row['value'];
            $store_id = $row['store_id'];
            $ret[$product_id][$store_id][$key] = $value;
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

        $lineitem_product_ids = array();

        $m = new Mage_Sales_Model_Order_Api();
        $ret = array();
        foreach($ids as $id){
            try{
                $info = $m->info($id);
                foreach($info['items'] as $item){
                    $lineitem_product_ids[] = $item['product_id'];
                }

                /*if ($is_sku_mode && isset($info['items'])) {
                    $_items = $info['items'];
                    $items = array();
                    foreach($_items as $item){
                        $item['_product_id'] = $item['product_id'];
                        $item['product_id'] = $item['sku'];
                        $items[] = $item;
                    }
                    $info['items'] = $items;
                }*/

            } catch(Exception $e){
                $info = false;
            }
            $ret[$id] = $info;
        }

        $lineitem_product_ids = array_values(array_unique($lineitem_product_ids));
        $parent_product_ids = $this->_getParentProductsMapping($lineitem_product_ids);

        foreach($ids as $id){
            for($i=0;$i<count($ret[$id]['items']);$i++){
                $item = $ret[$id]['items'][$i];
                $product_id = $item['product_id'];
                $parent_product_id = isset($parent_product_ids[$product_id]) ? $parent_product_ids[$product_id] : null;
                $ret[$id]['items'][$i]['parent_product_id'] = $parent_product_id;
            }
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

    private function _getWebsitesIdStoreIdsMapping($website_ids){

        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $core_resource = Mage::getSingleton('core/resource');

        $select = $db->select()
            ->from(
                array(
                    's' => $core_resource->getTableName('core_store')
                    ),
                array(
                    'store_id',
                    'website_id'
                    )
                );

        if (is_array($website_ids) && $website_ids){
            $select->where('website_id IN (?)', $website_ids);
        }

        $rows = $db->fetchAll($select);
        $ret = array();

        foreach($rows as $row){
            $website_id = $row['website_id'];
            $store_id = $row['store_id'];

            if (!isset($ret[$website_id])) $ret[$website_id] = array();
            $ret[$website_id][] = $store_id;
        }

        return $ret;
    }

    private function _getStoreIdsForWebsiteIds($website_ids, $website_store_ids){
        $ret = array();
        foreach($website_ids as $website_id){
            if (isset($website_store_ids[$website_id])) {
                $ret = array_merge($ret, $website_store_ids[$website_id]);
            }
        }
        return $ret;
    }

    private function _getParentProductsMapping($product_ids){
        if (!$product_ids) return array();

        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $core_resource = Mage::getSingleton('core/resource');

        $select = $db->select()
            ->from(
                array('r' => $core_resource->getTableName('catalog_product_relation')),
                array('parent_id','child_id')
                )
            ->join(
                array('p'=>$core_resource->getTableName('catalog_product_entity')),
                'p.entity_id=r.parent_id'
                )
            ->where('r.child_id IN (?)', $product_ids)
            ->where('p.type_id=?', 'configurable');
        $rows = $db->fetchAll($select);

        $ret = array();
        foreach($rows as $row) {
            $ret[$row['child_id']] = $row['parent_id'];
        }

        return $ret;
    }
}