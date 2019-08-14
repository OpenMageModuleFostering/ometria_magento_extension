<?php

class Ometria_Core_Test_Model_Observer_Product extends EcomDev_PHPUnit_Test_Case {

    /**
     * @test
     */
    public function testCatalogProductDeleteAfter() {

        $product = $this->getModelMock('catalog/product', array('getId'));
        $product->expects($this->any())
                ->method('getId')
                ->will($this->returnValue(1));

        $mock = $this->getModelMock('ometria/api', array('notifyProductUpdates'));
        $mock->expects($this->once())
                ->method('notifyProductUpdates');

        $this->replaceByMock('model', 'ometria/api', $mock);

        Mage::dispatchEvent("catalog_product_delete_after", array('data_object' => $product, 'product' => $product));
    }

    /**
     * @test
     */
    public function testCatalogProductSaveAfter() {

        $product = $this->getModelMock('catalog/product', array('getId'));
        $product->expects($this->any())
            ->method('getId')
            ->will($this->returnValue(1));

        $mock = $this->getModelMock('ometria/api', array('notifyProductUpdates'));
        $mock->expects($this->once())
            ->method('notifyProductUpdates');

        $this->replaceByMock('model', 'ometria/api', $mock);

        Mage::dispatchEvent("catalog_product_save_after", array('data_object' => $product, 'product' => $product));
    }
}