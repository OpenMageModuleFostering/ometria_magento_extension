<?php
class Ometria_Api_Model_Api2 extends Mage_Api_Model_Resource_Abstract {


    public function stock_list($filters, $page = null, $limit = null){
        $db = $this->_getReadDb();
        $core_resource = Mage::getSingleton('core/resource');

        $website_store_lookup = $this->_getWebsitesIdStoreIdsMapping(null);

        $select = $db->select()
            ->from(
                array(
                    'ss' => $core_resource->getTableName('cataloginventory_stock_status')
                    ),
                array('*')
                )
            ->joinLeft(
                array(
                    'r' => $core_resource->getTableName('catalog_product_relation')
                    ),
                'r.child_id = ss.product_id',
                array(
                    'parent_id'=>'parent_id'
                    )
                )
            ->join(
                array(
                    'p' => $core_resource->getTableName('catalog_product_entity')
                    ),
                'p.entity_id = ss.product_id',
                array(
                    'sku'=>'sku',
                    'product_type_id'=>'type_id'
                    )
                );

        if (array_key_exists('stock_status', $filters)) {
            $select->where('ss.stock_status=?', $filters['stock_status']);
        }

        // Expand to include child products if 'fetch_children' is given
        if (isset($filters['id']) && $filters['id'] && isset($filters['fetch_children']) && $filters['fetch_children']){
            $product_ids = $filters['id'];
            if (!is_array($product_ids)) $product_ids = array($product_ids);

            $product_child_ids = $this->_getChildProductIdsFor($product_ids);
            $product_ids = array_merge($product_ids, $product_child_ids);
            $filters['id'] = $product_ids;
        }

        if (isset($filters['id']) && $filters['id']){
            $product_ids = $filters['id'];
            if (!is_array($product_ids)) $product_ids = array($product_ids);
            $select->where('product_id IN (?)', $product_ids);
        }

        if ($limit && $page) {
            $offset = ($page-1)*$limit;
            $select->limit($limit, $offset);
        }

        $rows =  $db->fetchAll($select);
        $ret = array();
        foreach($rows as $row){
            $website_id = $row['website_id'];
            $row['store_ids'] = isset($website_store_lookup[$website_id]) ? $website_store_lookup[$website_id] : array();
            $ret[] = $row;
        }

        return $ret;
    }

    public function customers_list($filters, $page = null, $limit = null, $attributes=array('*')) {

        $collection = Mage::getModel('customer/customer')
            ->getCollection()
            ->addAttributeToSelect('lastname')
            ->addAttributeToSelect('firstname')
            ->addAttributeToSelect('suffix')
            ->addAttributeToSelect('middlename')
            ->addAttributeToSelect('prefix');

        foreach ($attributes as $attr) {
            $collection->addAttributeToSelect($attr);
        }

        $this->_applyFiltersToCollection($collection, $filters, $page, $limit);

        if (isset($filters['websites']) && $filters['websites']){
           $collection->addAttributeToFilter('website_id', array('in'=>$filters['websites']));
        }

        $collection->load();

        $customer_ids = array();
        foreach($collection as $item){
            $customer_ids[] = $item->getId();
        }

        $subscriptions_by_customer_id = $this->_load_customer_subscriptions($customer_ids);

        $ret = array();

        foreach($collection as $item){
            $row = $item->getData();
            $row['customer_id'] = $item->getId();

            $id = $item->getId();
            $subscription = isset($subscriptions_by_customer_id[$id]) ? $subscriptions_by_customer_id[$id] : null;
            $is_subscribed = ($subscription && $subscription['subscriber_email']==$item->getEmail());
            $row['subscription'] = $is_subscribed  ? $subscription : false;

            $ret[] = $row;
        }

        return $ret;
    }

    private function _load_customer_subscriptions($customer_ids){
        if (!$customer_ids) return array();

        $db = $this->_getReadDb();
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

    public function products_list($filters, $page = null, $limit = null, $attributes=array('*')) {

        $ometria_product_helper = Mage::helper('ometria/product');
        $is_sku_mode = $ometria_product_helper->isSkuMode();
        $productMediaConfig = Mage::getModel('catalog/product_media_config');

        $collection = Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('sku')
            ->addAttributeToSelect('price')
            ->addAttributeToSelect('image');

        foreach ($attributes as $attr) {
            $collection->addAttributeToSelect($attr);
        }

        $this->_applyFiltersToCollection($collection, $filters, $page, $limit, $is_sku_mode ? 'sku' : 'entity_id');

        if (isset($filters['websites']) && $filters['websites']){
           $collection->addWebsiteFilter($filters['websites']);
        }

        $collection->load();

        // Cache the mapping for website_ids => store_ids to avoid too many DB calls
        $website_store_lookup = array();

        // Collect loaded product ids
        $product_ids = array();
        foreach($collection as $item){
            $product_ids[] = $item->getId();
        }

        // Load rewrites
        $rewrites = $this->_load_product_rewrites($product_ids);

        $ret = array();
        foreach($collection as $item){
            $row = $item->getData();

            if ($is_sku_mode) {
                $row['product_id'] = $item->getSku();
            } else {
                $row['product_id'] = $item->getId();
            }

            $website_ids = $item->getWebsiteIds();
            $row['websites'] = $website_ids;

            $stores_cache_key = implode(":", $row['websites']);
            if (!isset($website_store_lookup[$stores_cache_key])) {
                $website_store_lookup[$stores_cache_key] = $this->_getStoreIdsForWebsites($website_ids);
            }
            $store_ids = $website_store_lookup[$stores_cache_key];
            $row['stores'] = $store_ids;

            // Get image url (small_image first)
            $imageUrl = $productMediaConfig->getMediaUrl($item->getSmallImage());
            if (!$imageUrl) $imageUrl = $item->getImageUrl();
            $row['image_url'] = $imageUrl;

            // Get thumbnail
            $imageUrl = $productMediaConfig->getMediaUrl($item->getThumbnail());
            $row['image_thumb_url'] = $imageUrl;

            // URLs
            $product_id = $item->getId();
            $row['urls'] = isset($rewrites[$product_id]) ? array_values($rewrites[$product_id]) : array();

            // Stock
            try{
                $stock_item = Mage::getModel('cataloginventory/stock_item')->loadByProduct($id);
                $stock = array();
                $stock['qty'] = $stock_item->getQty();
                $stock['is_in_stock'] = $stock_item->getIsInStock();
                $row['stock'] = $stock;
            } catch(Exception $e){
                // pass
            }

            $ret[] = $row;
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

    public function subscribers_list($filters=array(), $page=1, $pageSize=100){
        $collection = Mage::getModel('newsletter/subscriber')->getCollection();

        // setPage does not exist on this collection.
        // Access lower lever setters.
        $collection->setCurPage($page);
        $collection->setPageSize($pageSize);

        if (isset($filters['websites']) && $filters['websites']){
           $collection->addStoreFilter($this->_getStoreIdsForWebsites($filters['websites']));
        }

        if (isset($filters['updatedFrom']) && $filters['updatedFrom']){
            $collection->addFieldToFilter('change_status_at', array('gteq' => $filters['updatedFrom']));
        }

        if (isset($filters['updatedTo']) && $filters['updatedTo']){
            $collection->addFieldToFilter('change_status_at', array('lteq' => $filters['updatedTo']));
        }

        $collection->load();

        $ret = array();

        foreach($collection as $item){
            $ret[] = array(
                'customer_id'=>$item->customer_id,
                'email'=>$item->subscriber_email,
                'store_id'=>$item->store_id,
                'status'=>$item->subscriber_status,
                'change_status_at'=>$item->change_status_at
                );
        }

        return $ret;
    }

    public function subscribers_subscribe($email, $send_confirmation=false){

        if (!$email) return;

        $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($email);

        if (!$subscriber || !$subscriber->getId()){
            Mage::getModel('newsletter/subscriber')->setImportMode(!$send_confirmation)->subscribe($email);
            $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($email);
        }

        if ($subscriber && $subscriber->getId()){
            $subscriber->setStatus(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
            $subscriber->save();
            return true;
        }

        return false;
    }

    public function subscribers_unsubscribe($email, $reason='UNSUBSCRIBED'){

        if (!$email) return;

        $to_status = null;
        if ($reason=='UNSUBSCRIBED') $to_status = Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED;
        if ($reason=='NOT_ACTIVE') $to_status = Mage_Newsletter_Model_Subscriber::STATUS_NOT_ACTIVE;
        if ($reason=='UNCONFIRMED') $to_status = Mage_Newsletter_Model_Subscriber::STATUS_UNCONFIRMED;

        $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($email);
        if ($subscriber && $subscriber->getId() && $to_status){
            $subscriber->setStatus($to_status);
            $subscriber->save();
            return true;
        } else {
            return false;
        }
    }


    //@todo add websites filter
    public function salesrules_list($filters=array(), $page=1, $limit=100){
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $core_resource = Mage::getSingleton('core/resource');

        $offset = ($page-1)*$limit;

        $select = $db->select()
            ->from(
                array(
                    's' => $core_resource->getTableName('salesrule/rule')
                    ),
                array(
                    '*'
                    )
                )
            ->limit($limit, $offset);


        return $db->fetchAll($select);
    }

    public function salesrules_list_coupons($rule_id, $filters=array(), $page=1, $limit=100){
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $core_resource = Mage::getSingleton('core/resource');

        $offset = ($page-1)*$limit;

        $select = $db->select()
            ->from(
                array(
                    's' => $core_resource->getTableName('salesrule/coupon')
                    ),
                array(
                    '*'
                    )
                )
            ->where('rule_id=?', $rule_id)
            ->limit($limit, $offset);


        return $db->fetchAll($select);
    }


    public function salesrules_create_specific_coupons($rule_id, $requested_codes=array()){
        // Get the rule in question
        $rule = Mage::getModel('salesrule/rule')->load($rule_id);
        if (!$rule->getId()) return false;
        if (!$requested_codes) return false;

        $generator = new Ometria_Api_Model_FixedCouponGenerator();

        // Set the generator, and coupon type so it's able to generate
        $rule->setCouponCodeGenerator($generator);
        $rule->setCouponType( Mage_SalesRule_Model_Rule::COUPON_TYPE_AUTO );

        // Get as many coupons as you required
        $codes = array();
        foreach($requested_codes as $requested_code){
            try{
                $generator->setNextCode($requested_code);
                $coupon = $rule->acquireCoupon();
                $coupon->setType(Mage_SalesRule_Helper_Coupon::COUPON_TYPE_SPECIFIC_AUTOGENERATED)->save();
                $code = $coupon->getCode();
                $codes[] = $code;
            } catch(Exception $e) {
                //pass
            }
        }
        return $codes;
    }

    public function salesrules_create_coupons($rule_id, $count=1, $parameters=array()){
        // Get the rule in question
        $rule = Mage::getModel('salesrule/rule')->load($rule_id);
        if (!$rule->getId()) return false;

        $generator = Mage::getModel('salesrule/coupon_massgenerator');

        $_parameters = array(
            'format'=>'alphanumeric',
            'dash_every_x_characters'=>4,
            'prefix'=>'',
            'suffix'=>'',
            'length'=>12
        );
        $parameters = array_merge($_parameters, $parameters);

        if( !empty($parameters['format']) ){
          switch( strtolower($parameters['format']) ){
            case 'alphanumeric':
            case 'alphanum':
              $generator->setFormat( Mage_SalesRule_Helper_Coupon::COUPON_FORMAT_ALPHANUMERIC );
              break;
            case 'alphabetical':
            case 'alpha':
              $generator->setFormat( Mage_SalesRule_Helper_Coupon::COUPON_FORMAT_ALPHABETICAL );
              break;
            case 'numeric':
            case 'num':
              $generator->setFormat( Mage_SalesRule_Helper_Coupon::COUPON_FORMAT_NUMERIC );
              break;
          }
        }

        $generator->setDash((int) $parameters['dash_every_x_characters']);
        $generator->setLength((int) $parameters['length']);
        $generator->setPrefix($parameters['prefix']);
        $generator->setSuffix($parameters['suffix']);

        // Set the generator, and coupon type so it's able to generate
        $rule->setCouponCodeGenerator($generator);
        $rule->setCouponType( Mage_SalesRule_Model_Rule::COUPON_TYPE_AUTO );

        // Get as many coupons as you required
        $codes = array();
        for( $i = 0; $i < $count; $i++ ){
            try{
                $coupon = $rule->acquireCoupon();
                $coupon->setType(Mage_SalesRule_Helper_Coupon::COUPON_TYPE_SPECIFIC_AUTOGENERATED)->save();
                $code = $coupon->getCode();
                $codes[] = $code;
            } catch(Exception $e) {
                //pass
            }
        }
        return $codes;
    }



    public function salesrules_insert_coupons($rule_id, $coupon_codes, $parameters=array()){
        // Get the rule in question
        $rule = Mage::getModel('salesrule/rule')->load($rule_id);
        if (!$rule->getId()) return false;

        $coupon = Mage::getModel('salesrule/coupon');

        $expiration_date = null;
        if (isset($parameters['expiry_days'])) {
            $expiry_days = intval($parameters['expiry_days']);
            $expire_ts = strtotime('+'.$expiry_days.' days');
            $expiration_date = date('Y-m-d H:i:s', $expire_ts);
        }

        $usage_per_customer = isset($parameters['usage_per_customer']) ? $parameters['usage_per_customer'] : null;
        $usage_limit = isset($parameters['usage_limit']) ? $parameters['usage_limit'] : 1;

        $created_coupon_codes = array();

        foreach($coupon_codes as $code){
            try{
                $coupon->setId(null)
                    ->setRuleId($rule->getRuleId())
                    ->setCode($code)
                    ->setUsageLimit($usage_limit)
                    ->setUsagePerCustomer($usage_per_customer)
                    ->setExpirationDate($expiration_date)
                    //->setIsPrimary(1)
                    ->setCreatedAt(time())
                    ->setType(Mage_SalesRule_Helper_Coupon::COUPON_TYPE_SPECIFIC_AUTOGENERATED)
                    ->save();
                $created_coupon_codes[] = $code;
            } catch(Exception $e){
                // pass
            }
        }

        return $created_coupon_codes;
    }


    public function salesrules_remove_expired_coupons($rule_id){
        // Get the rule in question
        $rule = Mage::getModel('salesrule/rule')->load($rule_id);
        if (!$rule->getId()) return false;

        $coupons = Mage::getModel('salesrule/coupon')
            ->getCollection()
            ->addFieldToSelect('*')
            ->addFieldToFilter('rule_id', array('eq' => $rule_id))
            ->addFieldToFilter('expiration_date', array('lt' => date('Y-m-d H:i:s')));

        $removed_codes = array();
        foreach($coupons as $coupon){
            $removed_codes[] = $coupon->getCode();
            $coupon->delete();
        }

        return $removed_codes;
    }

    public function stores_list($filters=array()){
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $core_resource = Mage::getSingleton('core/resource');

        $select = $db->select()
            ->from(
                array(
                    's' => $core_resource->getTableName('core_store')
                    ),
                array(
                    '*'
                    )
                )
            ->join(
                array(
                    'w' => $core_resource->getTableName('core_website')
                    ),
                'w.website_id = s.website_id',
                array(
                    'website_name'=>'name'
                    )
                )
            ->where('store_id != 0');

        if (isset($filters['websites']) && $filters['websites']){
            $select->where('s.website_id IN (?)', $filters['websites']);
        }

        return $db->fetchAll($select);
    }

    public function product_attributes_list(){

        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $core_resource = Mage::getSingleton('core/resource');

        $product_entity_type_id = $this->_getEavEntityTypeId('catalog_product');

        $select = $db->select()
            ->from(
                array(
                    'a' => $core_resource->getTableName('eav_attribute')
                    ),
                array(
                    'attribute_code'
                    )
                )
            ->join(
                array(
                    'o' => $core_resource->getTableName('eav_attribute_option')
                    ),
                'o.attribute_id = a.attribute_id',
                array(
                    'option_id'
                    )
                )
            ->join(
                array(
                    'v' => $core_resource->getTableName('eav_attribute_option_value')
                    ),
                'o.option_id = v.option_id',
                array(
                    'store_id','value'
                    )
                )
            ->where('a.entity_type_id=?', $product_entity_type_id);

        $rows = $db->fetchAll($select);

        $ret = array();
        foreach($rows as $row){
            $type = $row['attribute_code'];
            $id = $row['option_id'];
            $label = $row['value'];
            $store_id = $row['store_id'];

            if (!isset($ret[$type])) $ret[$type] = array();
            if (!isset($ret[$type][$id])) $ret[$type][$id] = array(
                'attribute_id'=>$id,
                'label'=>$label,
                'storeviews'=>array()
                );
            $ret[$type][$id]['storeviews'][$store_id] = $label;
        }

        return $ret;
    }

    public function products_flat_list($filters=array(), $page=1, $limit=100){
        $db = $this->_getReadDb();
        $core_resource = Mage::getSingleton('core/resource');

        $ometria_product_helper = Mage::helper('ometria/product');
        $is_sku_mode = $ometria_product_helper->isSkuMode();

        // If filtered by websites, get default store id for first website id
        if (isset($filters['websites']) && is_array($filters['websites']) && $filters['websites']){
            $website_ids = $filters['websites'];
            $website_ids = array_values($website_ids);
            $website_id = $website_ids[0];

            $store = Mage::app()
                ->getWebsite($website_id)
                ->getDefaultGroup()
                ->getDefaultStoreId();
        } else{
            $store = 1;
        }

        // Override in case we need to
        if (isset($filters['store']) && $filters['store']){
            $store = $filters['store'];
        }

        $store = intval($store);
        $offset = ($page-1) * $limit;

        $select = $db->select()->from(
                array('p' => $core_resource->getTableName('catalog_product_flat_'.$store))
                )
                ->join(
                    array('a'=>'eav_attribute_set'),
                    'p.attribute_set_id=a.attribute_set_id',
                    array('attribute_set_name')
                )
                ->limit($limit, $offset)
                ->order('entity_id');

        // Filters
        if (isset($filters['visibility'])) $select->where('visibility=?', $filters['visibility']);
        if (isset($filters['updatedFrom'])) $select->where('updated_at>=?', $filters['updatedFrom']);
        if (isset($filters['updatedTo'])) $select->where('updated_at<=?', $filters['updatedTo']);
        if (isset($filters['createdFrom'])) $select->where('created_at>=?', $filters['createdFrom']);
        if (isset($filters['createdTo'])) $select->where('created_at<=?', $filters['createdTo']);

        if (isset($filters['id']) && !is_array($filters['id'])) {
            if ($is_sku_mode) {
                $select->where('sku=?', $filters['id']);
            } else {
                $select->where('entity_id=?', $filters['id']);
            }
        }
        if (isset($filters['id']) && $filters['id']) {
            $ids = $filters['id'];
            if (!is_array($ids)) $ids = array($ids);
            if ($is_sku_mode) {
                $select->where('sku IN (?)', $ids);
            } else {
                $select->where('entity_id IN (?)', $ids);
            }
        }

        $rows = $db->fetchAll($select);

        // Collect product_ids
        $product_ids = array();
        foreach($rows as $row){
            $product_ids[] = $row['entity_id'];
        }

        // Load website ids
        $product_id_website_ids = $this->_load_product_website_mapping($product_ids);


        $website_store_lookup = array();

        // Add id field to each row based on product ID mode
        foreach($rows as &$row){
            if ($is_sku_mode) {
                $row['product_id'] = $row['sku'];
            } else {
                $row['product_id'] = $row['entity_id'];
            }

            $entity_id = $row['entity_id'];
            $website_ids = isset($product_id_website_ids[$entity_id]) ?
                                $product_id_website_ids[$entity_id] :
                                array();

            $row['websites'] = $website_ids;
            $stores_cache_key = implode(":", $website_ids);
            if (!isset($website_store_lookup[$stores_cache_key])) {
                $website_store_lookup[$stores_cache_key] = $this->_getStoreIdsForWebsites($website_ids);
            }
            $store_ids = $website_store_lookup[$stores_cache_key];
            $row['stores'] = $store_ids;
        }
        unset($row);

        return $rows;
    }

    private function _load_product_website_mapping($product_ids){
        if (!$product_ids) return array();

        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $core_resource = Mage::getSingleton('core/resource');

        $select = $db->select()
            ->from(
                array(
                    'pw' => $core_resource->getTableName('catalog_product_website')
                    ),
                array(
                    '*'
                    )
                )
            ->where('product_id IN (?)', $product_ids);

        $rows = $db->fetchAll($select);

        $ret = array();

        foreach($rows as $row){
            $product_id = $row['product_id'];
            $website_id = $row['website_id'];

            if (!isset($ret[$product_id])) $ret[$product_id] = array();
            $ret[$product_id][] = $website_id;
        }

        return $ret;
    }

    public function orders_statuses_list(){
        $db = $this->_getReadDb();
        $core_resource = Mage::getSingleton('core/resource');

        $select = $db->select()
            ->from(
                array(
                    'i' => $core_resource->getTableName('sales_order_status_state')
                    ),
                array('*')
                );

        $rows =  $db->fetchAll($select);

        foreach($rows as &$row){
            $row['label'] = $row['status']; //@todo
        }
        unset($row);

        return $rows;
    }

    public function orders_count($filters=array(), $page=1, $limit=100){
        $collection = Mage::getModel('sales/order')->getCollection();
        $this->_applyFiltersToCollection($collection, $filters, $page, $limit);
        if (isset($filters['websites']) && $filters['websites']){
           $collection->addAttributeToFilter('store_id', array('in'=>$this->_getStoreIdsForWebsites($filters['websites'])));
        }
        return $collection->getSize();
    }

    public function orders_list($filters=array(), $page=1, $limit=100){

        $ret = array();

        // Load orders
        $order_records = $this->_order_list_get_orders($filters, $page, $limit);

        // Collect order_ids and address ids
        $order_address_ids = array();
        $order_entity_ids = array();
        foreach($order_records as $order){
            $order_entity_ids[] = $order['entity_id'];

            $order_address_ids[] = $order['shipping_address_id'];
            $order_address_ids[] = $order['billing_address_id'];
        }

        // Get lineitems for those order IDs
        $order_lineitems_by_order_id = $this->_order_list_get_lineitems($order_entity_ids);

        // Load addresses
        $order_addresses_by_id = $this->_order_list_get_addresses($order_address_ids);

        // Combine lineitems into orders
        $result = array();
        foreach($order_records as $order){
            $order_id = $order['entity_id'];
            $order['items'] = isset($order_lineitems_by_order_id[$order_id]) ? $order_lineitems_by_order_id[$order_id] : array();

            $shipping_address_id = $order['shipping_address_id'];
            $order['shipping_address'] =
                isset($order_addresses_by_id[$shipping_address_id]) ? $order_addresses_by_id[$shipping_address_id] : array();

            $billing_address_id = $order['billing_address_id'];
            $order['billing_address'] =
                isset($order_addresses_by_id[$billing_address_id]) ? $order_addresses_by_id[$billing_address_id] : array();


            $order['order_id'] = $order['increment_id'];

            $result[] = $order;
        }

        return $result;
    }

    private function _order_list_get_orders($filters, $page, $limit){
        $db = $this->_getReadDb();

        $orders_collection = Mage::getModel('sales/order')->getCollection();
        //$this->_applyFiltersToCollection($orders_collection, $filters, $page, $limit);

        $select = $orders_collection->getSelect();

        // Filters
        if (isset($filters['createdFrom'])) $select->where('created_at>=?', $filters['createdFrom']);
        if (isset($filters['createdTo'])) $select->where('created_at<=?', $filters['createdTo']);
        if (isset($filters['updatedFrom'])) $select->where('updated_at>=?', $filters['updatedFrom']);
        if (isset($filters['updatedTo'])) $select->where('updated_at<=?', $filters['updatedTo']);

        if (isset($filters['id']) && !is_array($filters['id'])) {
            $select->where('increment_id=?', $filters['id']);
        }
        if (isset($filters['id']) && is_array($filters['id']) && $filters['id']) {
            $select->where('increment_id IN (?)', $filters['id']);
        }

        if (isset($filters['websites']) && is_array($filters['websites']) && $filters['websites']) {
            $store_ids = $this->_getStoreIdsForWebsites($filters['websites']);
            $select->where('store_id IN (?)', $store_ids);
        }

        $select->limit($limit, ($page-1)*$limit)->order('entity_id');

        return $db->fetchAll($select);
    }

    private function _order_list_get_addresses($order_address_ids){
        if (!$order_address_ids) return array();

        $db = $this->_getReadDb();
        $core_resource = Mage::getSingleton('core/resource');

        $select = $db->select()
            ->from(
                array(
                    'i' => $core_resource->getTableName('sales_flat_order_address')
                    ),
                array('*')
                )
            ->where('entity_id IN (?)', $order_address_ids);

        $rows =  $db->fetchAll($select);

        $ret = array();
        foreach($rows as $row){
            $id = $row['entity_id'];
            $ret[$id] = $row;
        }

        return $ret;
    }

    private function _order_list_get_lineitems($order_ids){
        if (!$order_ids) return array();

        $ometria_product_helper = Mage::helper('ometria/product');
        $is_sku_mode = $ometria_product_helper->isSkuMode();

        $db = $this->_getReadDb();
        $core_resource = Mage::getSingleton('core/resource');

        $select = $db->select()
            ->from(
                array(
                    'i' => $core_resource->getTableName('sales/order_item')
                    ),
                array(
                    '*',
                    'variant_sku'=>'sku'
                    )
                )
            ->join(
                array(
                    'p' => $core_resource->getTableName('catalog/product')
                    ),
                'i.product_id = p.entity_id',
                array(
                    'sku'
                    )
                )
            //->where('i.parent_item_id IS NULL')
            ->where('i.order_id IN (?)', $order_ids);

        $_rows = $db->fetchAll($select);
        $rows = array();
        $groups = array();

        foreach($_rows as $row){
            $parent_item_id = $row['parent_item_id'];

            if ($parent_item_id===null) {
                $rows[] = $row;
            } else {
                if (!isset($groups[$parent_item_id])) $groups[$parent_item_id] = array();
                $groups[$parent_item_id][] = $row;
            }
        }


        $result = array();
        foreach($rows as $row){
            $item_id = $row['item_id'];
            $order_id = $row['order_id'];
            if (!isset($result[$order_id])) $result[$order_id] = array();
            $row['product_options'] = unserialize($row['product_options']);
            $children = isset($groups[$item_id]) ? $groups[$item_id] : array();

            $row['variant_id'] = count($children)>0 ? $children[0]['product_id'] : null;
            $row['children'] = $children;

            if ($is_sku_mode) {
                $row['_product_id'] = $row['product_id'];
                $row['product_id'] = $row['sku'];

                if ($row['variant_id']){
                    $row['variant_id'] = $row['variant_sku'];
                    $row['_variant_id'] = $row['variant_id'];
                }
            }

            $result[$order_id][] = $row;
        }

        return $result;
    }


    public function carts_list($filters=array(), $page=1, $limit=100){

        $ret = array();

        // Load orders
        $order_records = $this->_carts_list_get_carts($filters, $page, $limit);

        // Collect order_ids
        $order_entity_ids = array();
        foreach($order_records as $order){
            $order_entity_ids[] = $order['entity_id'];
        }

        // Get lineitems for those order IDs
        $order_lineitems_by_order_id = $this->_carts_list_get_lineitems($order_entity_ids);

        // Combine lineitems into orders
        $result = array();
        foreach($order_records as $order){
            $token = md5($order['created_at'].$order['remote_ip']);

            $order['token'] = $token;
            $order['deeplink'] = Mage::getUrl('omcart/cartlink',
                array('_query'=>array(
                    'token'=>$token,
                    'id'=>$order['entity_id']),
                    '_store' => $order['store_id'],
                    '_store_to_url'=>true
                ));

            $order['cart_id'] = $order['entity_id'];

            $order_id = $order['entity_id'];
            $order['items'] = isset($order_lineitems_by_order_id[$order_id]) ? $order_lineitems_by_order_id[$order_id] : array();
            $result[] = $order;
        }

        return $result;
    }


    private function _carts_list_get_carts($filters, $page, $limit){
        $db = $this->_getReadDb();
        $core_resource = Mage::getSingleton('core/resource');

        $offset = ($page-1) * $limit;

        $select = $db->select()
            ->from(
                array(
                    'q' => $core_resource->getTableName('sales/quote')
                    ),
                array(
                    '*'
                    )
                )
            ->joinLeft(
                array(
                    'o' => $core_resource->getTableName('sales/order')
                    ),
                'q.entity_id = o.quote_id',
                array(
                    'order_increment_id'=>'increment_id',
                    'ordered_at'=>'created_at',
                    )
                )
            ->limit($limit, $offset)
            ->order('q.entity_id');

        if (isset($filters['updatedFrom'])) $select->where('q.updated_at>=?', $filters['updatedFrom']);
        if (isset($filters['updatedTo'])) $select->where('q.updated_at<=?', $filters['updatedTo']);
        if (isset($filters['createdFrom'])) $select->where('q.created_at>=?', $filters['createdFrom']);
        if (isset($filters['createdTo'])) $select->where('q.created_at<=?', $filters['createdTo']);

        if (isset($filters['websites']) && is_array($filters['websites']) && $filters['websites']) {
            $store_ids = $this->_getStoreIdsForWebsites($filters['websites']);
            $select->where('q.store_id IN (?)', $store_ids);
        }

        return $db->fetchAll($select);
    }

    private function _carts_list_get_lineitems($order_ids){
        if (!$order_ids) return array();

        $db = $this->_getReadDb();
        $core_resource = Mage::getSingleton('core/resource');

        $ometria_product_helper = Mage::helper('ometria/product');
        $is_sku_mode = $ometria_product_helper->isSkuMode();

        $select = $db->select()
            ->from(
                array(
                    'i' => $core_resource->getTableName('sales/quote_item')
                    ),
                array(
                    '*',
                    'variant_sku'=>'sku'
                    )
                )
            ->join(
                array(
                    'p' => $core_resource->getTableName('catalog/product')
                    ),
                'i.product_id = p.entity_id',
                array(
                    'sku'
                    )
                )
            ->where('i.parent_item_id IS NULL')
            ->where('i.quote_id IN (?)', $order_ids);

        $_rows = $db->fetchAll($select);
        $rows = array();
        $groups = array();

        foreach($_rows as $row){
            $parent_item_id = $row['parent_item_id'];

            if ($parent_item_id===null) {
                $rows[] = $row;
            } else {
                if (!isset($groups[$parent_item_id])) $groups[$parent_item_id] = array();
                $groups[$parent_item_id][] = $row;
            }
        }


        $result = array();
        foreach($rows as $row){
            $item_id = $row['item_id'];
            $order_id = $row['quote_id'];
            if (!isset($result[$order_id])) $result[$order_id] = array();
            $row['product_options'] = unserialize($row['product_options']);
            $children = isset($groups[$item_id]) ? $groups[$item_id] : array();

            $row['variant_id'] = count($children)>0 ? $children[0]['product_id'] : null;
            $row['children'] = $children;

            if ($is_sku_mode) {
                $row['_product_id'] = $row['product_id'];
                $row['product_id'] = $row['sku'];

                if ($row['variant_id']){
                    $row['variant_id'] = $row['variant_sku'];
                    $row['_variant_id'] = $row['variant_id'];
                }
            }

            $result[$order_id][] = $row;
        }

        return $result;
    }

    public function get_magento_info(){
        $unique_id = Mage::getStoreConfig('ometria/advanced/unique_id');
        if (!$unique_id){
            $unique_id = md5(uniqid().time());
            $config_model = new Mage_Core_Model_Config();
            $config_model->saveConfig('ometria/advanced/unique_id', $unique_id, 'default', 0);

            Mage::app()->getCacheInstance()->cleanType('config');
        }
        $info = array();
        $info['id'] = $unique_id;
        $info['timezone'] = Mage::getStoreConfig('general/locale/timezone');
        $info['php_timezone'] = date_default_timezone_get();
        $info['php_version'] = phpversion();
        $info['magento_version'] = Mage::getVersion();

        $modules = array('Ometria_Api','Ometria_Core','Ometria_AbandonedCarts');
        $info['ometria_extension_versions'] = array();
        foreach($modules as $module){
            $info['ometria_extension_versions'][$module]
                    = current(Mage::getConfig()->getModuleConfig($module)->version);
        }

        return $info;
    }

    public function get_settings(){
        $ret = array();
        $ret['unique_id'] = Mage::getStoreConfig('ometria/advanced/unique_id');
        $ret['productmode'] = Mage::getStoreConfig('ometria/advanced/productmode');
        $ret['ping'] = Mage::getStoreConfig('ometria/advanced/ping');
        return $ret;
    }

    public function set_settings($settings){
        $keys = array('unique_id','productmode','ping');
        $config_model = new Mage_Core_Model_Config();
        foreach($keys as $key){
            if (isset($settings[$key]) && !empty($settings[$key])) {
                $config_model->saveConfig('ometria/advanced/'.$key, $settings[$key], 'default', 0);
            }
        }
        Mage::app()->getCacheInstance()->cleanType('config');
        return true;
    }

    // Apply common filters to Collection
    private function _applyFiltersToCollection($collection, $filters, $page, $limit, $id_field='entity_id'){

        if (isset($filters['updatedFrom']) && $filters['updatedFrom']){
            $collection->addAttributeToFilter('updated_at', array('gteq' => $filters['updatedFrom']));
        }

        if (isset($filters['updatedTo']) && $filters['updatedTo']){
            $collection->addAttributeToFilter('updated_at', array('lteq' => $filters['updatedTo']));
        }

        if (isset($filters['createdFrom']) && $filters['createdFrom']){
            $collection->addAttributeToFilter('created_at', array('gteq' => $filters['createdFrom']));
        }

        if (isset($filters['createdTo']) && $filters['createdTo']){
            $collection->addAttributeToFilter('created_at', array('lteq' => $filters['createdTo']));
        }

        if (isset($filters['id']) && $filters['id']){
            if (is_array($filters['id'])) {
                $collection->addAttributeToFilter($id_field, array('in'=>$filters['id']));
            } else {
                $collection->addAttributeToFilter($id_field, array('eq'=>$filters['id']));
            }
        }

        $collection->setPage($page, $limit);
    }

    private function _getReadDb(){
        return Mage::getSingleton('core/resource')->getConnection('core_read');
    }

    private function _getEavEntityTypeId($type){

        $db = $this->_getReadDb();
        $core_resource = Mage::getSingleton('core/resource');

        $select = $db->select()
            ->from($core_resource->getTableName('eav_entity_type'), array('entity_type_id'))
            ->where('entity_type_code = ? ', $type);

        return $db->fetchOne($select);
    }

    private function _getStoreIdsForWebsites($website_ids){

        if (!$website_ids) return array();

        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $core_resource = Mage::getSingleton('core/resource');

        $select = $db->select()
            ->from(
                array(
                    's' => $core_resource->getTableName('core_store')
                    ),
                array(
                    'store_id'
                    )
                );

        if (is_array($website_ids) && $website_ids){
            $select->where('website_id IN (?)', $website_ids);
        }

        $rows = $db->fetchAll($select);
        $store_ids = array();

        foreach($rows as $row){
            $store_ids[] = $row['store_id'];
        }

        return $store_ids;
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

    private function _getChildProductIdsFor($product_ids){

        if (!$product_ids) return array();

        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $core_resource = Mage::getSingleton('core/resource');

        $select = $db->select()
            ->from(
                array(
                    'r' => $core_resource->getTableName('catalog_product_relation')
                    ),
                array(
                    'child_id',
                    )
                )
            ->where('parent_id IN (?)', $product_ids);

        $rows = $db->fetchAll($select);
        $child_ids = array();
        foreach($rows as $row) $child_ids[] = $row['child_id'];
        return $child_ids;
    }
}