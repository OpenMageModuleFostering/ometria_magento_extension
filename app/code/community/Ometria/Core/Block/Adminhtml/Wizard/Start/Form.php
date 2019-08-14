<?php
class Ometria_Core_Block_Adminhtml_Wizard_Start_Form extends Mage_Adminhtml_Block_Widget_Form {

    protected function _prepareForm() {
        $id = $this->getRequest()->getParam('id');

        $form = new Varien_Data_Form(array(
            'id' => 'edit_form',
            'action' => $this->getUrl('*/*/process', array('id' => $id)),
            'method' => 'post',
        ));

        $fieldset = $form->addFieldset('base_fieldset', array(
            'legend'    => Mage::helper('ometria')->__('Ometria Customer Details'),
            'class'     => 'fieldset-wide',
        ));

        $fieldset->addField('api_key', 'text', array(
            'label'    => 'Web Services User Password',
            'title'    => 'Web Services User Password',
            'name'     => 'api_key',
            'after_element_html'  => '<p class="note"><span>This value will be provided to you by Ometria</span></p>',
            'required'  => true
        ));

        $form->setValues(array());
        $form->setUseContainer(true);
        $this->setForm($form);
        return parent::_prepareForm();
    }
}
