<?php

class Ometria_Core_Model_Observer_Order {

    /**
     * Sales Order After Save
     *
     * @param Varien_Event_Observer $observer
     * @return Ometria_Core_Model_Observer_Order
     */
    public function salesOrderSaveAfter(Varien_Event_Observer $observer) {

        $ometria_ping_helper = Mage::helper('ometria/ping');
        $order = $observer->getEvent()->getOrder();
        $ometria_ping_helper->sendPing('transaction', $order->getIncrementId(), array(), $order->getStoreId());

        return $this;
    }
}