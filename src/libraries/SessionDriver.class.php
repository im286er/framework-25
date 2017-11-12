<?php

/**
 * SessionHandlerInterface
 * session 处理类
 * PHP 5.4 compatibility interface
 */
class SessionDriver implements SessionHandlerInterface {

    private $_expiration = 28800;       /* 8小时 */

    public static function getInstance() {
        static $obj;
        if (!$obj) {
            $obj = new self();
        }
        return $obj;
    }

    /**
     * session 已连接
     * @return bool
     */
    private function is_active() {
        return session_status() == PHP_SESSION_ACTIVE;
    }

    public function open($savePath, $sessionName) {
        return true;
    }

    public function close() {
        return true;
    }

    public function read($session_id) {
        if (empty($session_id)) {
            return false;
        }
        return Cache::getInstance()->simple_get($session_id);
    }

    public function write($session_id, $session_data) {
        if (empty($session_id)) {
            return false;
        }
        return Cache::getInstance()->simple_set($session_id, $session_data, $this->_expiration);
    }

    public function destroy($session_id) {
        if (empty($session_id)) {
            return false;
        }
        return Cache::getInstance()->simple_delete($session_id);
    }

    public function gc($maxlifetime) {
        return true;
    }

}
