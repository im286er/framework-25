<?php

namespace framework\safety;

use framework\core\Config;

/**
 *  高级加密标准（英语：Advanced Encryption Standard，缩写：AES），在密码学中又称Rijndael加密法，是美国联邦政府采用的一种区块加密标准。
 */
class aes {

    private $z;
    private $iv;
    private $mod = 'CBC';

    function __construct() {
        $key = Config::getInstance()->get('auth_key');

        $key = str_repeat($key, 3);

        $this->z = substr($key, 0, 16);
        $this->iv = substr($key, 16, 16);
    }

    public static function getInstance() {
        static $obj;
        if (!$obj) {
            $obj = new self();
        }
        return $obj;
    }

    /**
     * 对称加密字符串
     * @param   string      $data       原始字符串          Y
     * @return string       加密过的字符串
     */
    public function encrypt($data) {
        $aes = new \PhpAes\Aes($this->z, $this->mod, $this->iv);

        $str = $aes->encrypt($data);
        return str_replace(array('+', '/', '='), array('-', '_', ''), base64_encode($str));
    }

    /**
     * 对称解密字符串
     * @param   string      $data       加密过的字符串          Y
     * @return  string
     */
    public function decrypt($data) {
        $data = str_replace(array('-', '_'), array('+', '/'), $data);
        $mod4 = strlen($data) % 4;
        if ($mod4) {
            $data .= substr('====', $mod4);
        }
        $data = base64_decode($data);

        $aes = new \PhpAes\Aes($this->z, $this->mod, $this->iv);
        return $aes->decrypt($data);
    }

}
