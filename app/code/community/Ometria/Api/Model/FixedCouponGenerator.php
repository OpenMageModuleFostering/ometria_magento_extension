<?php

class Ometria_Api_Model_FixedCouponGenerator extends Mage_SalesRule_Model_Coupon_Massgenerator{

    protected $code = null;

    public function setNextCode($code){
        $this->code = $code;
    }

    public function generateCode(){
        return $this->code;
    }
}