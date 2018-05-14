<?php

namespace framework\safety;

use framework\core\Config;

/**
 * sso 登陆使用加密与解密
 */
class Des {

    var $key;
    var $iv; //偏移量

    private function GetKey($key = '') {
        $key = substr($key . Config::get('auth_key'), 0, 8);
        return $key;
    }

    function __construct($key, $iv = 0) {
        //key长度8
        $this->key = $this->GetKey($key);
        if ($iv == 0) {
            $this->iv = $this->key;
        } else {
            $this->iv = $iv;
        }
    }

    public function encrypt($str) {
        //加密，返回大写十六进制字符串
        $size = mcrypt_get_block_size(MCRYPT_DES, MCRYPT_MODE_CBC);
        $str = $this->pkcs5Pad($str, $size);
        return strtoupper(bin2hex(mcrypt_encrypt(MCRYPT_DES, $this->key, $str, MCRYPT_MODE_CBC, $this->iv)));
    }

    public function decrypt($str) {
        //解密
        $strBin = $this->hex2bin(strtolower($str));
        $str = mcrypt_decrypt(MCRYPT_DES, $this->key, $strBin, MCRYPT_MODE_CBC, $this->iv);
        $str = $this->pkcs5Unpad($str);
        return $str;
    }

    private function hex2bin($hexData) {
        $binData = "";
        for ($i = 0; $i < strlen($hexData); $i += 2) {
            $binData .= chr(hexdec(substr($hexData, $i, 2)));
        }
        return $binData;
    }

    private function pkcs5Pad($text, $blocksize) {
        $pad = $blocksize - (strlen($text) % $blocksize);
        return $text . str_repeat(chr($pad), $pad);
    }

    private function pkcs5Unpad($text) {
        $pad = ord($text {strlen($text) - 1});
        if ($pad > strlen($text)) {
            return false;
        }
        if (strspn($text, chr($pad), strlen($text) - $pad) != $pad) {
            return false;
        }
        return substr($text, 0, - 1 * $pad);
    }

}
