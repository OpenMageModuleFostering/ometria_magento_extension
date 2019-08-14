<?php
class Ometria_Core_Block_Adminhtml_Wizard_Start extends Mage_Adminhtml_Block_Widget_Form_Container {

    public function __construct() {
        parent::__construct();
        $this->_blockGroup = 'ometria';
        $this->_controller = 'adminhtml_wizard';
        $this->_mode = 'start';

        $this->_updateButton('save', 'label', Mage::helper('ometria')->__('Next'));
        $this->_updateButton('save', 'onclick', 'if (editForm.submit()) { document.getElementById(\'loading-mask\').show(); }');
        $this->_updateButton('back', 'label', Mage::helper('ometria')->__('Cancel'));
        $this->_updateButton('back', 'onclick', 'window.close()');
        $this->_removeButton('reset');
    }

    public function getHeaderText() {
        return Mage::helper('ometria')->__('Ometria Setup Wizard');
    }
}
