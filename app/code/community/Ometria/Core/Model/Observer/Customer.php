<?php

class Ometria_Core_Model_Observer_Customer {

    var $did_register = false;

    public function customerSaveAfter(Varien_Event_Observer $observer) {

        $ometria_config_helper = Mage::helper('ometria/config');
        if (!$ometria_config_helper->isConfigured()) return;

        $ometria_ping_helper = Mage::helper('ometria/ping');
        $customer = $observer->getEvent()->getCustomer();
        $ometria_ping_helper->sendPing('customer', $customer->getId(), array(), $customer->getStoreId());

        return $this;
    }

    public function loggedOut(Varien_Event_Observer $observer){
        $ometria_config_helper = Mage::helper('ometria/config');
        if (!$ometria_config_helper->isConfigured()) return;

        $this->identify('logout');
    }

    public function loggedIn(Varien_Event_Observer $observer){
        $ometria_config_helper = Mage::helper('ometria/config');
        if (!$ometria_config_helper->isConfigured()) return;

        $this->identify('login');
    }

    public function registered(Varien_Event_Observer $observer){
        $ometria_config_helper = Mage::helper('ometria/config');
        if (!$ometria_config_helper->isConfigured()) return;

        $this->did_register = true;
        $this->identify('register');
    }

    protected function identify($event){
        $ometria_cookiechannel_helper = Mage::helper('ometria/cookiechannel');

        if ($this->did_register && $event=='login') {
            $event = 'register';
        }

        $customer = Mage::getSingleton('customer/session')->getCustomer();
        if ($customer) {
            $data = array('e'=>$customer->getEmail(),'i'=>$customer->getId());
            $command = array('identify', $event, http_build_query($data));
            $ometria_cookiechannel_helper->addCommand($command, true);
        }
    }
}