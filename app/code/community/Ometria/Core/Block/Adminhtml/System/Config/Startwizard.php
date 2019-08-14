<?php
class Ometria_Core_Block_Adminhtml_System_Config_Startwizard extends Mage_Adminhtml_Block_System_Config_Form_Field {

    public function canOpenWizard() {
        return Mage::helper('ometria/config')->getAPIKey();
    }

    protected function _prepareLayout() {
        parent::_prepareLayout();
        if (!$this->getTemplate()) {
            $this->setTemplate('ometria/system/config/start_wizard.phtml');
        }
        return $this;
    }

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element) {
        $originalData = $element->getOriginalData();

        $this->addData(array(
            'button_label'	=> Mage::helper('ometria')->__($originalData['button_label']),
            'button_url'	=> Mage::helper('adminhtml')->getUrl($originalData['button_url']),
            'html_id'		=> $element->getHtmlId(),
        ));

        return $this->_toHtml();
    }
}