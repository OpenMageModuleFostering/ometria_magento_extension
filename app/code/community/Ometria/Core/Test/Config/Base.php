<?php
class Ometria_Core_Test_Config_Base extends EcomDev_PHPUnit_Test_Case_Config {
    public function testBlockAlias() {
        $this->assertBlockAlias('ometria/test', 'Ometria_Core_Block_Test');
    }

    public function testModelAlias() {
        $this->assertModelAlias('ometria/test', 'Ometria_Core_Model_Test');
    }

    public function testHelperAlias() {
        $this->assertHelperAlias('ometria/test', 'Ometria_Core_Helper_Test');
    }

    public function testCodePool() {
        $this->assertModuleCodePool('community');
    }

    public function testDepends() {
        $this->assertModuleDepends('Mage_Catalog');
        $this->assertModuleDepends('Mage_Customer');
        $this->assertModuleDepends('Mage_Sales');

    }

    public function testLayoutFile() {
        $this->assertLayoutFileDefined('frontend', 'ometria/core.xml');
        $this->assertLayoutFileExistsInTheme('frontend', 'ometria/core.xml', 'default', 'base');
    }
}