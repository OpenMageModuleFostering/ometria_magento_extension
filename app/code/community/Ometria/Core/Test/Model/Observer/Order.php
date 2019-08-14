<?php

class Ometria_Core_Test_Model_Observer_Order extends EcomDev_PHPUnit_Test_Case {

    /**
     * @test
     */
    public function testSalesOrderSaveAfter() {

        $order = $this->getModelMock('sales/order', array('getId'));
        $order->expects($this->any())
            ->method('getId')
            ->will($this->returnValue(1));

        $mock = $this->getModelMock('ometria/api', array('notifyOrderUpdates'));
        $mock->expects($this->once())
            ->method('notifyOrderUpdates');

        $this->replaceByMock('model', 'ometria/api', $mock);

        Mage::dispatchEvent("sales_order_save_after", array('order' => $order));
    }
}