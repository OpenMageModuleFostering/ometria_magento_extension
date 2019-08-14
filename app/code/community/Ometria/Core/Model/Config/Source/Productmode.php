<?php

class Ometria_Core_Model_Config_Source_Productmode
{
  public function toOptionArray()
  {
    return array(
      array('value' => 'id', 'label' => 'Product ID'),
      array('value' => 'sku', 'label' => 'Product SKU')
    );
  }
}