<?php

namespace framework\session;

use framework\nosql\Redis;

/**
 * session Redis 驱动类
 */
class RedisDriver extends \SessionHandler {

    private $ttl = 7200;       /* 2小时 */

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
        return Redis::getInstance()->tag('session')->get($session_id);
    }

    public function write($session_id, $session_data) {
        if (empty($session_id)) {
            return false;
        }
        Redis::getInstance()->tag('session')->set($session_id, $session_data, $this->ttl);
        return true;
    }

    public function destroy($session_id) {
        if (empty($session_id)) {
            return false;
        }
        Redis::getInstance()->tag('session')->delete($session_id);
        return true;
    }

    public function gc($maxlifetime) {
        return true;
    }

}
