<?php

namespace framework\safety;

use framework\core\Request;
use framework\nosql\Cache;

/**
 *
 * 验证码类
 */
class Captcha {

    /**
     * 生成图像验证码
     * @static
     * @access public
     * @param string $width  宽度
     * @param string $height  高度
     * @return string
     */
    public static function buildImageVerify($width = 80, $height = 30, $verifyName = 'verify') {
        $secret = GoogleAuthenticator::genSecret();

        $cache_id = Request::getInstance()->ip(0, true) . Request::getInstance()->get_user_agent() . $verifyName;
        $cache_id = md5($cache_id);
        Cache::getInstance()->group('_captcha_')->set($cache_id, $secret, 600);

        $randval = GoogleAuthenticator::getCode($secret);

        $length = mb_strlen($randval);
        $width = ($length * 10 + 10) > $width ? $length * 10 + 10 : $width;

        $im = imagecreatetruecolor($width, $height) or die("Cannot Initialize new GD image stream");

        /* 背景 */
        $white = imagecolorallocate($im, 255, 255, 255);
        imagefilledrectangle($im, 0, 0, $width, $height, $white);
        /* 边框色 */
        $borderColor = imagecolorallocate($im, 100, 100, 100);
        imagerectangle($im, 0, 0, $width - 1, $height - 1, $borderColor);
        /* 干扰 */
        for ($i = 0; $i < 3; $i++) {
            $stringColor = imagecolorallocate($im, rand(1, 150), rand(1, 120), rand(1, 160));
            imagearc($im, rand(-5, $width), rand(-5, $height), rand(30, 300), rand(20, 200), 55, 44, $stringColor);
        }
        for ($i = 0; $i < 20; $i++) {
            $stringColor = imagecolorallocate($im, rand(1, 150), rand(1, 120), rand(1, 160));
            imagesetpixel($im, mt_rand(0, $width), mt_rand(0, $height), $stringColor);
        }

        /* 写入文字 */
        for ($i = 0; $i < $length; $i++) {
            $stringColor = imagecolorallocate($im, mt_rand(1, 150), mt_rand(1, 120), mt_rand(1, 160));
            imagestring($im, 10, ($i * 11 + 6), mt_rand(1, ($height - 15)), $randval{$i}, $stringColor);
        }

        ob_clean();
        header("Content-type: image/png");
        imagepng($im);
        imagedestroy($im);
        exit();
    }

    /**
     * 生成数字计算题验证码
     * @param int $width
     * @param int $height
     * @param string $verifyName
     *
     * @return void
     */
    public static function calocVerify($width = 80, $height = 30, $verifyName = 'verifyCode') {
        $la = rand(1, 9);
        $ba = rand(1, 9);
        $randnum = rand(1, 3);
        if ($randnum == 3) {
            if ($la < $ba) {
                $tmp = $la;
                $la = $ba;
                $ba = $tmp;
            }
        }
        $randarr = array(
            1 => $la + $ba,
            2 => $la * $ba,
            3 => $la - $ba
        );
        $randstr = $randarr[$randnum];
        $randResult = array(
            1 => $la . '+' . $ba . '=?',
            2 => $la . '*' . $ba . '=?',
            3 => $la . '-' . $ba . '=?'
        );
        $randval = $randResult[$randnum];


        $cache_id = Request::getInstance()->ip(0, true) . Request::getInstance()->get_user_agent() . $verifyName;
        $cache_id = md5($cache_id);
        Cache::getInstance()->group('_captcha_')->set($cache_id, intval($randstr), 600);


        $length = count($randval);
        $width = ($length * 10 + 10) > $width ? $length * 10 + 10 : $width;
        $im = imagecreatetruecolor($width, $height) or die("Cannot Initialize new GD image stream");

        /* 背景 */
        $white = imagecolorallocate($im, 255, 255, 255);
        imagefilledrectangle($im, 0, 0, $width, $height, $white);
        /* 边框色 */
        $borderColor = imagecolorallocate($im, 100, 100, 100);
        imagerectangle($im, 0, 0, $width - 1, $height - 1, $borderColor);
        /* 干扰 */
        for ($i = 0; $i < 3; $i++) {
            $stringColor = imagecolorallocate($im, rand(1, 150), rand(1, 120), rand(1, 160));
            imagearc($im, rand(-5, $width), rand(-5, $height), rand(30, 300), rand(20, 200), 55, 44, $stringColor);
        }
        for ($i = 0; $i < 20; $i++) {
            $stringColor = imagecolorallocate($im, rand(1, 150), rand(1, 120), rand(1, 160));
            imagesetpixel($im, mt_rand(0, $width), mt_rand(0, $height), $stringColor);
        }

        /* 写入文字 */
        $length = mb_strlen($randval);
        for ($i = 0; $i < $length; $i++) {
            $stringColor = imagecolorallocate($im, mt_rand(1, 150), mt_rand(1, 120), mt_rand(1, 160));
            imagestring($im, 10, ($i * 11 + 6), mt_rand(1, ($height - 15)), $randval{$i}, $stringColor);
        }

        ob_clean();
        header("Content-type: image/png");
        imagepng($im);
        imagedestroy($im);
        exit();
    }

    /**
     * 校验验证码
     *
     * @param string $input 用户输入
     * @param string $verifyName 生成验证码时的字段
     * @return bool 正确返回true，错误返回false
     */
    public static function checkCode($input, $verifyName = 'verifyCode') {
        $cache_id = Request::getInstance()->ip(0, true) . Request::getInstance()->get_user_agent() . $verifyName;
        $cache_id = md5($cache_id);

        $code = Cache::getInstance()->group('_captcha_')->get($cache_id);
        Cache::getInstance()->group('_captcha_')->delete($cache_id);

        if ($code === false || $code != $input) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 检查纯数字验证码
     * @param type $code
     * @param type $verifyName
     * @return boolean
     */
    public static function check($code, $verifyName = 'verify') {
        $cache_id = Request::getInstance()->ip(0, true) . Request::getInstance()->get_user_agent() . $verifyName;
        $cache_id = md5($cache_id);

        $secret = Cache::getInstance()->group('_captcha_')->get($cache_id);
        Cache::getInstance()->group('_captcha_')->delete($cache_id);

        if ($secret == null) {
            return false;
        }
        return GoogleAuthenticator::checkCode($secret, $code);
    }

}
