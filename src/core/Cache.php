<?php

namespace framework\core;

use framework\core\Exception;
use framework\core\Config;

/**
 * 基本缓存管理类
 */
class Cache {

    protected $handler;

    public function __get($name) {
        return $this->get($name);
    }

    public function __set($name, $value) {
        return $this->set($name, $value);
    }

    public function __unset($name) {
        $this->delete($name);
    }

    public function __construct($type = '') {

        if (empty($type)) {
            $type = Config::getInstance()->get('DATA_CACHE_TYPE');
        }

        $class = strpos($type, '\\') ? $type : 'framework\\nosql\\' . $type;
        if (class_exists($class)) {
            $this->handler = $class::getInstance();
        } else {
            throw new Exception($type . '缓存类不存在');
        }
    }

    public function group($group = '_cache_') {
        return $this->handler->group($group);
    }

    public function clear() {
        return $this->handler->clear($group);
    }

    public function get($cache_id) {
        return $this->handler->get($cache_id);
    }

    public function set($cache_id, $var, $expire) {
        return $this->handler->set($cache_id, $var, $expire);
    }

    public function delete($cache_id) {
        return $this->handler->delete($cache_id);
    }

    public function lock($cache_id, $ttl = 5) {
        return $this->handler->lock($cache_id, $ttl);
    }

    public function unlock($cache_id) {
        return $this->handler->unlock($cache_id);
    }

    public function simple_set($cache_id, $var, $expire = 0) {
        return $this->handler->simple_set($cache_id, $var, $expire);
    }

    public function simple_get($cache_id) {
        return $this->handler->simple_get($cache_id);
    }

    public function simple_delete($cache_id) {
        return $this->handler->simple_delete($cache_id);
    }

    public function act_limit($uid, $action, $max_count, $period) {
        return $this->handler->act_limit($uid, $action, $max_count, $period);
    }

    static function getInstance($type = '') {
        $key = md5($type);
        static $obj = [];
        if (!isset($obj[$key])) {
            $obj[$key] = new self($type);
        }
        return $obj[$key];
    }

    public function __call($method, $args) {
        //调用缓存类型自己的方法
        if (method_exists($this->handler, $method)) {
            return call_user_func_array(array($this->handler, $method), $args);
        } else {
            throw new Exception(__CLASS__ . ':' . $method . '方法不存在');
        }
    }

}
