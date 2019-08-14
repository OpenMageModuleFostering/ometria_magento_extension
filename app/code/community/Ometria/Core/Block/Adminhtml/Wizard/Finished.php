<?php
class Ometria_Core_Block_Adminhtml_Wizard_Finished extends Mage_Adminhtml_Block_Template {

    public function getResults() {
        if (!$result = $this->getData('result')) {
            $result = Mage::getSingleton('adminhtml/session')->getData('ometria_wizard_result');
        }
        return $result;
    }

    /**
     * Determine if the result was successful or not. We class it as unsuccessful if one of the entries has an 'error'
     * status.
     *
     * @return bool
     */
    public function getWasSuccessful() {
        $results = $this->getResults();
        return array_reduce($results, array($this, '_checkForError'));
    }

    protected function _checkForError($result, $entry) {
        if ($result === false) {
            return false;
        }

        if ($entry['status'] == 'error') {
            return false;
        }

        return true;
    }
}
