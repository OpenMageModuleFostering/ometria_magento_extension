<?php

class Ometria_Core_Helper_Cookiechannel extends Mage_Core_Helper_Abstract {

    const COOKIE_NAME = 'ommage';
    const SEPERATOR_BETWEEN_COMMANDS = ';';
    const SEPERATOR_IN_COMMANDS = ':';

    public function addCommand($command, $replace_if_exists=false){
        if (!$command || !is_array($command)) return;

        // Return if admin area or API call
        if (Mage::app()->getStore()->isAdmin()) return;
        //if (Mage::getSingleton('api/server')->getAdapter() != null) return;
        if (isset($_SERVER['REQUEST_URI']) && stripos($_SERVER['REQUEST_URI'], "/api")) return;

        $ometria_config_helper = Mage::helper('ometria/config');
        if (!$ometria_config_helper->isConfigured()) return;
        if (!$ometria_config_helper->isUnivarEnabled()) return;

        $str = implode(self::SEPERATOR_IN_COMMANDS, $command);

        $this->appendCookieCommand($command[0], $str, $replace_if_exists);
    }

    private function appendCookieCommand($command_name, $str, $replace_if_exists=false){
        $existing_cookie = isset($_COOKIE[self::COOKIE_NAME]) ? $_COOKIE[self::COOKIE_NAME] : '';
        $commands = explode(self::SEPERATOR_BETWEEN_COMMANDS, $existing_cookie);

        if ($replace_if_exists && $commands) {
            $commands_filtered = array();
            foreach($commands as $command){
                if (strpos($command, $command_name.self::SEPERATOR_IN_COMMANDS)!==0) {
                    $commands_filtered[] = $command;
                }
            }
            $commands = $commands_filtered;
        }

        $commands[] = $str;
        if (count($commands)>6) $commands = array_slice($commands, 0, 6);

        $commands = array_unique($commands);
        $commands = array_filter($commands);
        $commands = array_values($commands);
        $new_cookie = implode(self::SEPERATOR_BETWEEN_COMMANDS, $commands);
        if (strlen($new_cookie)>1000) $new_cookie = '';

        $_COOKIE[self::COOKIE_NAME] = $new_cookie;
        if (!headers_sent()) setcookie(self::COOKIE_NAME, $new_cookie, 0, '/');
    }
}