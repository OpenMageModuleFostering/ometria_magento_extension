<?php

class Ometria_AbandonedCarts_Helper_Config extends Mage_Core_Helper_Abstract {

    public function getCartUrl($store_id=null) {
        return Mage::getStoreConfig('ometria_abandonedcarts/abandonedcarts/cartpath', $store_id);
    }

    public function isDeeplinkEnabled() {
        return Mage::getStoreConfigFlag('ometria_abandonedcarts/abandonedcarts/enabled');
    }

    public function shouldCheckDeeplinkgToken() {
        return Mage::getStoreConfigFlag('ometria_abandonedcarts/abandonedcarts/check_token');
    }
}