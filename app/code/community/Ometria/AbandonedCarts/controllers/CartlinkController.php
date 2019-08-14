<?php

require_once Mage::getModuleDir('controllers','Mage_Checkout').DS.'CartController.php';

class Ometria_AbandonedCarts_CartlinkController extends Mage_Checkout_CartController
{
    public function indexAction(){

        $message_incorrect_link = 'Cart link is incorrect or expired';

        $session = Mage::getSingleton('customer/session');
        $helper = Mage::helper('ometria_abandonedcarts/config');

        if (!$helper->isDeeplinkEnabled()){
            $this->doRedirect('');
            return;
        }

        $token = $this->getRequest()->getParam('token');
        $id = $this->getRequest()->getParam('id');

        $is_ok = false;

        if ($id && $token){
            $quote = Mage::getModel('sales/quote')->load($id);

            if (!$quote || !$quote->getId()){
                $session->addNotice($message_incorrect_link);
                $this->doRedirect('');
                return;
            }

            if ($helper->shouldCheckDeeplinkgToken()){
                $computed_token = substr(md5($quote->created_at.$quote->getId()), 0, 12);

                if ($token!=$computed_token) {
                    $session->addNotice($message_incorrect_link);
                    $this->doRedirect('');
                    return;
                }
            }

            $quote->setIsActive(true);
            $quote->save();

            $this->_getSession()->setQuoteId($quote->getId());

            $cart_path = $helper->getCartUrl();
            if (substr($cart_path,0,7)=='http://' || substr($cart_path,0,8)=='https://'){
                $this->doRedirect($cart_path);
            } else {
                $this->doRedirect($cart_path);
            }
        } else {
            $this->doRedirect('');
        }
    }

    // Do redirect without stripping out utm_ params
    private function doRedirect($url){
        if (substr($url,0,7)=='http://' || substr($url,0,8)=='https://'){
            // pass
        } else {
            $url = Mage::getUrl($url);
        }
        if ($_GET){
            $qs = http_build_query($_GET);
            $separator = (parse_url($url, PHP_URL_QUERY) == NULL) ? '?' : '&';
            $url .= $separator . $qs;
        }
        $this->_redirectUrl($url);
    }
}