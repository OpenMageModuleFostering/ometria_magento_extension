<?php

class Ometria_Core_Model_Observer_Cart {

    public function basketUpdated(Varien_Event_Observer $observer){
        $this->updateBasketCookie();
    }

    public function updateBasketCookie() {

        $ometria_product_helper = Mage::helper('ometria/product');
        $ometria_cookiechannel_helper = Mage::helper('ometria/cookiechannel');
        $cart = Mage::getModel('checkout/cart')->getQuote();

        $cart_token = md5($cart->created_at.$cart->remote_ip);

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
                //'v'=>$item->getSku(),
                'q'=>$item->getQty(),
                );
            $command_part = http_build_query($buffer);
            $command[] = $command_part;

            $count++;
            if ($count>30) break; // Prevent overly long cookies
        }

        $ometria_cookiechannel_helper->addCommand($command, true);

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
                $ometria_ping_helper->sendPing('transaction', $order->getIncrementId(), array('session'=>$session_id));
            }

            $ometria_cookiechannel_helper->addCommand(array('trans', $order->getIncrementId()));
        } catch(Exception $e){
            //pass
        }
    }
}