<?php

class Ometria_Core_Model_Observer_Cart {


    public function basketUpdated(Varien_Event_Observer $observer){
        // Return if admin area or API call
        if (Mage::app()->getStore()->isAdmin()) return;
        if (Mage::getSingleton('api/server')->getAdapter() != null) return;

        $this->updateBasketCookie();
    }

    public function updateBasketCookie() {

        $ometria_product_helper = Mage::helper('ometria/product');
        $ometria_cookiechannel_helper = Mage::helper('ometria/cookiechannel');
        $cart = Mage::getModel('checkout/cart')->getQuote();

        $cart_token = substr(md5($cart->created_at.$cart->getId()),0,12);

        $command = array(
                'basket',
                $cart->getId(),
                $cart->getGrandTotal(),
                Mage::app()->getStore()->getCurrentCurrencyCode(),
                $cart_token
                );

        $count = 0;
        foreach($cart->getAllVisibleItems() as $item){

            $product =  Mage::getModel('catalog/product')->load($item->getProductId());
            $buffer = array(
                'i'=>$ometria_product_helper->getIdentifierForProduct($product),
                //'s'=>$product->getSku(),
                'v'=>$item->getSku(),
                'q'=>(int) $item->getQty(),
                't'=>(float) $item->getRowTotalInclTax()
                );
            $command_part = http_build_query($buffer);
            $command[] = $command_part;

            $count++;
            if ($count>30) break; // Prevent overly long cookies
        }

        $ometria_cookiechannel_helper->addCommand($command, true);

        // Identify if needed
        if ($cart->getCustomerEmail()) {
            $identify_type = 'checkout_billing';
            $data = array('e'=>$cart->getCustomerEmail());
            $command = array('identify', $identify_type, http_build_query($data));
            $ometria_cookiechannel_helper->addCommand($command, true);
        }

        return $this;
    }

    public function orderPlaced(Varien_Event_Observer $observer){

        $ometria_session_helper = Mage::helper('ometria/session');
        $ometria_cookiechannel_helper = Mage::helper('ometria/cookiechannel');

        try{
            $ometria_ping_helper = Mage::helper('ometria/ping');
            $order = $observer->getEvent()->getOrder();
            $session_id = $ometria_session_helper->getSessionId();
            if ($session_id) {
                $ometria_ping_helper->sendPing('transaction', $order->getIncrementId(), array('session'=>$session_id), $order->store_id);
            }
            $ometria_cookiechannel_helper->addCommand(array('trans', $order->getIncrementId()));

            // If via front end, also identify via cookie channel (but do not replace if customer login has done it)
            $is_frontend = true;
            if (Mage::app()->getStore()->isAdmin()) $is_frontend=false;
            if (Mage::getSingleton('api/server')->getAdapter() != null) $is_frontend=false;
            if ($is_frontend){
                $ometria_cookiechannel_helper = Mage::helper('ometria/cookiechannel');

                if ($order->getCustomerIsGuest()){
                    $identify_type = 'guest_checkout';
                    $data = array('e'=>$order->getCustomerEmail());
                } else {
                    $identify_type = 'checkout';
                    $customer = $order->getCustomer();
                    $data = array('e'=>$customer->getEmail(),'i'=>$customer->getId());
                }

                $command = array('identify', $identify_type, http_build_query($data));
                $ometria_cookiechannel_helper->addCommand($command, true);
            }
        } catch(Exception $e){
            //pass
        }
    }
}