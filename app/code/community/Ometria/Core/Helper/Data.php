<?php

class Ometria_Core_Helper_Data extends Mage_Core_Helper_Abstract {

    /**
     * Get extension version
     * @return string
     */
    public function getExtensionVersion() {
        return Mage::getConfig()->getModuleConfig('Ometria_Core')->version;
    }
}