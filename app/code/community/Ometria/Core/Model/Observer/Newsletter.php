<?php

class Ometria_Core_Model_Observer_Newsletter {
    public function handleSubscriberUpdate(Varien_Event_Observer $observer){
        $ometria_ping_helper = Mage::helper('ometria/ping');

        $subscriber = $observer->getEvent()->getSubscriber();

        $data = $subscriber->getData();
        $status_change = $subscriber->getIsStatusChanged();

        // Only if status has changed
        if ($status_change) {
            $event = null;
            if ($data['subscriber_status']==1) $event = 'newsletter_subscribed';
            if ($data['subscriber_status']==3) $event = 'newsletter_unsubscribed';
            if ($event) $ometria_ping_helper->sendPing($event, $subscriber->getEmail());

            // Update timestamp column
            $subscriber->change_status_at = time();
        }
    }

    public function handleSubscriberDeletion(Varien_Event_Observer $observer){
        $ometria_ping_helper = Mage::helper('ometria/ping');

        $subscriber = $observer->getEvent()->getSubscriber();
        $ometria_ping_helper->sendPing('newsletter_unsubscribed', $subscriber->getEmail());
    }
}