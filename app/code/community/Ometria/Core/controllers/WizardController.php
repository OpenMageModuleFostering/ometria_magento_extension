<?php
class Ometria_Core_WizardController extends Mage_Adminhtml_Controller_Action {

    const OMETRIA_API_ROLE_NAME = 'Ometria';
    const OMETRIA_API_USERNAME = 'ometria';
    const OMETRIA_API_USER_FIRSTNAME = 'Ometria';
    const OMETRIA_API_USER_LASTNAME = 'Ometria';
    const OMETRIA_API_USER_EMAIL = 'ometria@ometria.com';
    const OMETRIA_API_TEST_URL = 'https://console.ometria.com/setup/test-importer/magento';

    public function indexAction() {
        $this->loadLayout();
        $this->renderLayout();
    }

    public function processAction() {
        // We'll collect some parameters to pass to the success / fail page and place them in the $result array()
        $result = array();

        // Clear the session, incase there is something there already.
        Mage::getSingleton('adminhtml/session')->unsetData('ometria_wizard_result');

        $siteId = Mage::helper('ometria/config')->getAPIKey();
        $apiKey = $this->getRequest()->getParam('api_key');

        // Create the role.
        $role = $this->_createRole($result);

        // If the role was created successfully, create the user.
        if ($role) {
            $user = $this->_createUser($role, $apiKey, $result);
        }

        if ($role && $user) {
            $this->_testConnection($siteId, $result);
        }

        Mage::getSingleton('adminhtml/session')->setData('ometria_wizard_result', $result);
        $this->_redirect('*/*/finished');
    }

    public function finishedAction() {
        $this->loadLayout();
        $this->renderLayout();
    }

    protected function _createRole(&$result) {
        // Only make the new role if we don't have one called 'Ometria'
        $role = Mage::getModel('api/roles')->load(self::OMETRIA_API_ROLE_NAME, 'role_name');
        if (!$role->getId()) {
            try {
                $resource = array('all');
                $role = $role
                    ->setName(self::OMETRIA_API_ROLE_NAME)
                    ->setPid(0)
                    ->setRoleType('G')
                    ->save();

                Mage::getModel("api/rules")
                    ->setRoleId($role->getId())
                    ->setResources($resource)
                    ->saveRel();

                $result []= array('status' => 'success', 'message' => 'Successfully created WebServices Role.');
            } catch (Exception $e) {
                $role = false;
                $result []= array('status' => 'error', 'message' => 'Failed to create WebServices Role. Reason: ' . $e->getMessage());
            }
        } else {
            $result []= array('status' => 'warning', 'message' => 'Unable to create WebServices Role, one already exists. Proceeding.');
        }
        return $role;
    }

    protected function _createUser($role, $apiKey, &$result) {
        // Make a user and attach it to the $role if one doesn't exist.
        $user = Mage::getModel('api/user')->load(self::OMETRIA_API_USERNAME, 'username');
        if (!$user->getId()) {
            $user->setData(array(
                'username'  => self::OMETRIA_API_USERNAME,
                'firstname' => self::OMETRIA_API_USER_FIRSTNAME,
                'lastname'  => self::OMETRIA_API_USER_LASTNAME,
                'email'     => self::OMETRIA_API_USER_EMAIL,
                'api_key'   => $apiKey,
                'api_key_confirmation'   => $apiKey,
                'is_active' => 1,
                'roles'     => array($role->getId())
            ));

            try {
                $user->save();
                $user->setRoleIds(array($role->getId()))
                    ->setRoleUserId($user->getUserId())
                    ->saveRelations();

                $result []= array('status' => 'success', 'message' => 'Successfully created WebServices User.');
            } catch (Exception $e) {
                $user = false;
                $result []= array('status' => 'error', 'message' => 'Failed to create WebServices User. Reason: ' . $e->getMessage());
            }

        } else {
            $result []= array('status' => 'warning', 'message' => 'Unable to create WebServices User, one already exists. Proceeding.');
        }

        return $user;
    }

    protected function _testConnection($siteId, &$result) {
        $fields = array(
            'acc' => urlencode($siteId)
        );

        $fieldsString = http_build_query($fields);

        // Here's how I think the implementation will look - Will have to wait until it's been implemented however before
        // we can be sure.
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, self::OMETRIA_API_TEST_URL);
        curl_setopt($ch, CURLOPT_POST, 2);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fieldsString);

        $rawResponse = curl_exec($ch);

        $response = Mage::helper('core')->jsonDecode($rawResponse);

        if ($response['status'] == 'OK') {
            $result []= array('status' => 'success', 'message' => 'Successfully tested connection with Ometria.');
        } else {
            $result []= array('status' => 'error', 'message' => 'Failed when testing connection with Ometria. Reason: ' . $response['error']);
        }
    }
}
