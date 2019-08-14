<?php

class Ometria_Core_Helper_Config extends Mage_Core_Helper_Abstract {

    public function isEnabled() {
        return Mage::getStoreConfigFlag('ometria/general/enabled');
    }

    public function isDebugMode() {
        return Mage::getStoreConfigFlag('ometria/advanced/debug');
    }

    // Is data layer configured?
    public function isUnivarEnabled() {
        return Mage::getStoreConfigFlag('ometria/advanced/univar');
    }

    public function isPingEnabled() {
        return Mage::getStoreConfigFlag('ometria/advanced/ping');
    }

    public function isScriptDeferred() {
        return Mage::getStoreConfigFlag('ometria/advanced/scriptload');
    }

    public function getAPIKey($store_id=null) {
        if ($store_id) {
            return Mage::getStoreConfig('ometria/general/apikey', $store_id);
        } else {
            return Mage::getStoreConfig('ometria/general/apikey');
        }
    }

    public function isConfigured() {
        return $this->isEnabled() && $this->getAPIKey() != "";
    }

    public function log($message, $level = Zend_Log::DEBUG) {
        Mage::log($message, $level, "ometria.log");
    }
}