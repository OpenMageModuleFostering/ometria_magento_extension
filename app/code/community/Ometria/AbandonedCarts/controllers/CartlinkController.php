<?php

require_once Mage::getModuleDir('controllers','Mage_Checkout').DS.'CartController.php';

class Ometria_AbandonedCarts_CartlinkController extends Mage_Checkout_CartController
{
    public function indexAction(){

        $message_incorrect_link = 'Cart link is incorrect or expired';

        $session = Mage::getSingleton('customer/session');
        $helper = Mage::helper('ometria_abandonedcarts/config');

        if (!$helper->isDeeplinkEnabled()){
                $this->_redirect('');
                return;
        }

        $token = $this->getRequest()->getParam('token');
        $id = $this->getRequest()->getParam('id');

        $is_ok = false;

        if ($id && $token){
            $quote = Mage::getModel('sales/quote')->load($id);

            if (!$quote || !$quote->getId()){
                $session->addNotice($message_incorrect_link);
                $this->_redirect('');
                return;
            }

            if ($helper->shouldCheckDeeplinkgToken()){
                $computed_token = substr(md5($quote->created_at.$quote->getId()), 0, 12);

                if ($token!=$computed_token) {
                    $session->addNotice($message_incorrect_link);
                    $this->_redirect('');
                    return;
                }
            }

            $quote->setIsActive(true);
            $quote->save();

            $this->_getSession()->setQuoteId($quote->getId());

            $cart_path = $helper->getCartUrl();
            if (substr($cart_path,0,7)=='http://' || substr($cart_path,0,8)=='https://'){
                $this->_redirectUrl($cart_path);
            } else {
                $this->_redirect($cart_path);
            }
        } else {
            $this->_redirect('');
        }
    }
}