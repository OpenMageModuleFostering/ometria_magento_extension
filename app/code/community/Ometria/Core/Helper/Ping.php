<?php

class Ometria_Core_Helper_Ping extends Mage_Core_Helper_Abstract {

    const API_HOST = 'trk.ometria.com';
    const API_SOCKET_SCHEMA = 'ssl://';
    const API_PATH = '/ping.php';
    const API_SOCKET_TIMEOUT = 2;

    private static $_PING_CACHE=array();

    public function sendPing($type, $ids, $extra=array(), $store_id=null){
        $ometriaConfigHelper = Mage::helper('ometria/config');

        if (!$ometriaConfigHelper->isConfigured()) {
            return false;
        }

        if (!$ometriaConfigHelper->isPingEnabled()) {
            return true;
        }

        if($ometriaConfigHelper->isDebugMode()) {
            if(is_array($ids)) {
                $ometriaConfigHelper->log("Sending ping. Type: ".$type." " . implode(',', $ids));
            } else {
                $ometriaConfigHelper->log("Sending ping. Type: ".$type." " . $ids);
            }
        }

        $extra['account']   =  $ometriaConfigHelper->getAPIKey($store_id);
        $extra['type']      =  $type;
        $extra['id']        =  $ids;

        return $this->_ping($extra);
    }


    /**
     * Helper function to ping ometria.  Manually doing an fsockopen
     * so that we don't have to wait for a response. Unless debugging
     * when we do wait and log the content body.
     *
     * @param array $parameters
     *
     * @return bool
     */

    protected function _ping($parameters = array()) {

        // Check cache
        $ping_signature = json_encode(array($parameters['type'], $parameters['id']));
        if (isset(self::$_PING_CACHE[$ping_signature])) return;
        self::$_PING_CACHE[$ping_signature] = true;
        //

        $ometriaConfigHelper = Mage::helper('ometria/config');

        $content = http_build_query($parameters);
        $path = self::API_PATH;


        try {

            $fp = fsockopen(self::API_SOCKET_SCHEMA . self::API_HOST, 443, $errorNum, $errorStr, self::API_SOCKET_TIMEOUT);

            if($fp !== false) {

                $out  = "POST $path HTTP/1.1\r\n";
                $out .= "Host: " . self::API_HOST. "\r\n";
                $out .= "Content-type: application/x-www-form-urlencoded\r\n";
                $out .= "Content-Length: " . strlen($content) . "\r\n";
                $out .= "Connection: Close\r\n\r\n";
                $out .= $content;

                fwrite($fp, $out);

                // If debug mode, wait for response and log
                if($ometriaConfigHelper->isDebugMode()) {

                    $responseHeader = "";
                    do {
                        $responseHeader .= fgets($fp, 1024);
                    } while(strpos($responseHeader, "\r\n\r\n") === false);

                    $response = "";
                    while (!feof($fp)) {
                        $response .= fgets($fp, 1024);
                    }

                    $ometriaConfigHelper->log($response);
                }

                fclose($fp);
            } else {
                $ometriaConfigHelper->log("Ping failed: Error $errorNum - $errorStr", Zend_Log::ERR);
                return false;
            }
        } catch (Exception $e) {
            $ometriaConfigHelper->log($e->getMessage());
            return false;
        }

        return true;
    }
}