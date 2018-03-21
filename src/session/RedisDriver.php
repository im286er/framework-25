<?php

namespace framework\session;

use framework\nosql\Redis;

/**
 * session Redis 驱动类
 */
class RedisDriver extends \SessionHandler {

    private $_expiration = 7200;       /* 2小时 */

    public function open($savePath, $sessionName) {
        return true;
    }

    public function close() {
        return true;
    }

    public function read($session_id) {
        if (empty($session_id)) {
            return '';
        }
        return Redis::getInstance(['prefix' => 'session_'])->simple_get($session_id);
    }

    public function write($session_id, $session_data) {
        if (empty($session_id)) {
            return false;
        }
        Redis::getInstance(['prefix' => 'session_'])->simple_set($session_id, $session_data, $this->_expiration);
        return true;
    }

    public function destroy($session_id) {
        if (empty($session_id)) {
            return false;
        }
        Redis::getInstance(['prefix' => 'session_'])->simple_delete($session_id);
        return true;
    }

    public function gc($maxlifetime) {
        return true;
    }

}
