<?php

class Ometria_Core_Model_Observer_Newsletter {
    public function handleSubscriberUpdate(Varien_Event_Observer $observer){
        $ometria_config_helper = Mage::helper('ometria/config');
        if (!$ometria_config_helper->isConfigured()) return;

        $ometria_ping_helper = Mage::helper('ometria/ping');

        $subscriber = $observer->getEvent()->getSubscriber();

        $data = $subscriber->getData();

        $original_data = $subscriber->getOrigData();
        if (!$original_data) {
            $status_change = true;
        } elseif (isset($original_data['subscriber_status'])) {
            $status_change = $data['subscriber_status'] != $original_data['subscriber_status'];
        }

        // Only if status has changed
        if ($status_change) {
            $event = null;
            if ($data['subscriber_status']==1) $event = 'newsletter_subscribed';
            if ($data['subscriber_status']==3) $event = 'newsletter_unsubscribed';
            if ($event) $ometria_ping_helper->sendPing($event, $subscriber->getEmail(), array('store_id'=>$subscriber->store_id), $subscriber->store_id);

            // Update timestamp column
            $subscriber->change_status_at = date("Y-m-d H:i:s", time());
        }

        // If via front end, also identify via cookie channel (but do not replace if customer login has done it)
        $is_frontend = true;
        if (Mage::app()->getStore()->isAdmin()) $is_frontend=false;
        if (Mage::getSingleton('api/server')->getAdapter() != null) $is_frontend=false;
        if ($is_frontend){
            $ometria_cookiechannel_helper = Mage::helper('ometria/cookiechannel');
            $data = array('e'=>$subscriber->getEmail());
            $command = array('identify', 'newsletter', http_build_query($data));
            $ometria_cookiechannel_helper->addCommand($command, false);
        }
    }

    public function handleSubscriberDeletion(Varien_Event_Observer $observer){
        $ometria_ping_helper = Mage::helper('ometria/ping');

        $subscriber = $observer->getEvent()->getSubscriber();
        $ometria_ping_helper->sendPing('newsletter_unsubscribed', $subscriber->getEmail(), array('store_id'=>$subscriber->store_id), $subscriber->store_id);
    }
}