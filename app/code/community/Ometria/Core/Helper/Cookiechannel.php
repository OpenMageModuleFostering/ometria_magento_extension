<?php

class Ometria_Core_Helper_Cookiechannel extends Mage_Core_Helper_Abstract {

    const COOKIE_NAME = 'ommage';
    const SEPERATOR_BETWEEN_COMMANDS = ';';
    const SEPERATOR_IN_COMMANDS = ':';

    public function addCommand($command, $replace_if_exists=false){
        if (!$command || !is_array($command)) return;

        $ometria_config_helper = Mage::helper('ometria/config');
        if (!$ometria_config_helper->isConfigured()) return;
        if (!$ometria_config_helper->isUnivarEnabled()) return;

        $str = implode(self::SEPERATOR_IN_COMMANDS, $command);

        $this->appendCookieCommand($command[0], $str, $replace_if_exists);
    }

    private function appendCookieCommand($command_name, $str, $replace_if_exists=false){
        $existing_cookie = isset($_COOKIE[self::COOKIE_NAME]) ? $_COOKIE[self::COOKIE_NAME] : '';
        $new_cookie = '';

        if ($replace_if_exists && $existing_cookie) {
            $_commands = explode(self::SEPERATOR_BETWEEN_COMMANDS, $existing_cookie);
            $commands = array();
            foreach($_commands as $command){
                if (strpos($command, $command_name.self::SEPERATOR_IN_COMMANDS)!==0) {
                    $commands[] = $command;
                }
            }
            $commands = array_filter($commands);
            $existing_cookie = implode(self::SEPERATOR_BETWEEN_COMMANDS, $commands);
        }

        if ($existing_cookie){

            $new_cookie = $existing_cookie.self::SEPERATOR_BETWEEN_COMMANDS.$str;
        } else {
            $new_cookie = $str;
        }

        $_COOKIE[self::COOKIE_NAME] = $new_cookie;
        setcookie(self::COOKIE_NAME, $new_cookie, 0, '/');
    }
}