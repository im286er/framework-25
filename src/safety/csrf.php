<?php

namespace framework\safety;

use framework\core\Session;
use framework\core\Cookie;
use framework\core\Request;

/**
 * CSRF 基础预防
 */
class csrf {

    /**
     * 设置全局TOKEN，防止CSRF攻击
     *  使用方法：csrf::setToken()
     *  @return
     */
    public static function setToken() {
        if (Session::getInstance()->get('_hash_') === null) {
            $token = substr(md5(time() . Request::getInstance()->get_user_agent()), 5, 8);

            Cookie::getInstance()->set('_hash_', $token, ['httponly' => true]);

            Session::getInstance()->set('_hash_', $token);
        }
    }

    /**
     * 检测token值
     *  使用方法：csrf::checkToken($ispost = true)
     *  @return bool
     */
    public static function checkToken($ispost = true) {

        if ($ispost && (Request::getInstance()->isPost() == false)) {
            return false;
        }

        if (Session::getInstance()->get('_hash_') === null) {
            return false;
        }

        if (Session::getInstance()->get('_hash_') === Cookie::getInstance()->get('_hash_')) {
            return true;
        }

        return false;
    }

}
