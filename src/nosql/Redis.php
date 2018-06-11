<?php

namespace framework\nosql;

use framework\core\Config;
use framework\core\Exception;

/**
 *  Redis 缓存驱动
 *
 * 要求安装phpredis扩展：https://github.com/phpredis/phpredis/
 */
class Redis {

    private $conf;
    private $group = '_cache_';
    private $prefix = 'guipin_';
    private $tag = '_cache_';     /* 缓存标签 */
    private $ver = 0;
    private $link = [];
    private $hash;

    /**
     * 是否连接server
     */
    private $isConnected = false;

    /**
     * 重连次数
     * @var int
     */
    private $reConnected = 0;

    /**
     * 最大重连次数,默认为3次
     * @var int
     */
    private $maxReConnected = 3;

    public function __construct() {

        if (!extension_loaded('redis')) {
            throw new Exception('当前环境不支持: redis');
        }

        $this->conf = Config::get('redis_cache');

        if (empty($this->conf)) {
            throw new Exception('请配置 redis !');
        }

        $this->connect();
    }

    public static function getInstance() {
        static $obj;
        if (!$obj) {
            $obj = new self();
        }
        return $obj;
    }

    private function connect() {

        $this->hash = new Flexihash();

        foreach ($this->conf as $k => $conf) {
            $con = new \Redis;

            if ($conf['persistent'] == true) {
                $rs = $con->pconnect($conf['host'], $conf['port'], $conf['timeout'], 'persistent_id_' . $conf['select']);
            } else {
                $rs = $con->connect($conf['host'], $conf['port'], $conf['timeout']);
            }

            if ($rs == true) {
                $this->isConnected = true;
                $this->link[$k] = $con;

                $this->hash->addTarget($k);
            } else {
                $this->isConnected = false;
                /* 跳过 */
                continue;
            }

            if ('' != $conf['password']) {
                $con->auth($conf['password']);
            }

            if (0 != $conf['select']) {
                $con->select($conf['select']);
            }

            if (!empty($conf['prefix'])) {
                $this->prefix = $conf['prefix'];
            }
        }
    }

    /**
     * 获取实际的缓存标识
     * @access protected
     * @param  string $name 缓存名
     * @return string
     */
    protected function getCacheKey($name) {
        return $this->prefix . $name;
    }

    private function _getConForKey($key = '') {
        $i = $this->hash->lookup($key);
        return $this->link[$i];
    }

    /**
     * 检查是否能 ping 成功
     * @param type $key
     * @return boolean
     */
    public function ping($key = '') {
        return $this->_getConForKey($key)->ping() == '+PONG';
    }

    /**
     * 检查驱动是否可用
     * @return boolean      是否可用
     */
    public function is_available() {
        if (!$this->isConnected && $this->reConnected < $this->maxReConnected) {

            $this->connect();

            if (!$this->isConnected) {
                $this->reConnected++;
            }
            //如果重连成功,重连次数置为0
            else {
                $this->reConnected = 0;
            }
        }
        return $this->isConnected;
    }

    /**
     * 设置value,用于序列化存储
     * @param mixed $value
     * @return mixed
     */
    public function setValue($value) {
        if (!is_numeric($value)) {
            try {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            } catch (Exception $exc) {
                return false;
            }
        }
        return $value;
    }

    /**
     * 获取value,解析可能序列化的值
     * @param mixed $value
     * @return mixed
     */
    public function getValue($value, $default = false) {
        if (is_null($value) || $value === false) {
            return false;
        }
        if (!is_numeric($value)) {
            try {
                $value = json_decode($value, true);
            } catch (Exception $exc) {
                return $default;
            }
        }
        return $value;
    }

    /**
     * 设置缓存分组
     * @param type $group
     * @return $this
     */
    public function group($group = '_cache_') {
        $this->group = $group;

        $key = $this->getCacheKey('cache_ver_' . $this->group);

        try {
            /* 获取版本号 */
            $this->ver = $this->_getConForKey($key)->get($key);
            if ($this->ver) {
                return $this;
            }
            /* 设置新版本号 */
            $this->ver = $this->_getConForKey($key)->incrby($key, 1);
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
            /* 出错 */
            $this->ver = 0;
        }

        return $this;
    }

    /**
     * 按分组清空缓存
     * @param string $group
     * @return type
     * @return boolean
     */
    public function clear() {
        if ($this->group) {

            $key = $this->getCacheKey('cache_ver_' . $this->group);
            try {
                /* 获取新版本号 */
                $this->ver = $this->_getConForKey($key)->incrby($key, 1);

                /* 最大版本号修正 */
                if ($this->ver == PHP_INT_MAX) {
                    $this->ver = 1;
                    $this->_getConForKey($key)->set($key, 1);
                }

                return $this->ver;
            } catch (\Exception $ex) {
                //连接状态置为false
                $this->isConnected = false;
                $this->is_available();
            }
        }

        return true;
    }

    /**
     * 获取有分组的缓存
     * @access public
     * @param string $cache_id 缓存变量名
     * @param mixed  $default 默认值
     * @return mixed
     */
    public function get($cache_id, $default = false) {

        $key = $this->getCacheKey("{$this->group}_{$cache_id}");

        try {

            $value = $this->_getConForKey($key)->get($key);

            if (is_null($value) || false === $value) {
                return $default;
            }

            $data = $this->getValue($value, $default);

            if ($data && $data['ver'] == $this->ver) {
                return $data['data'];
            }
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

    /**
     * 设置有分组的缓存
     * @param type $cache_id    缓存 key
     * @param type $var         缓存值
     * @param type $ttl      有效期(秒)
     * @return boolean
     */
    public function set($cache_id, $var, $ttl = 0) {
        $key = $this->getCacheKey("{$this->group}_{$cache_id}");

        /* 缓存数据 */
        $data = $this->setValue(['ver' => $this->ver, 'data' => $var]);

        try {
            if ($ttl == 0) {
                // 缓存 3.5 天
                return $this->_getConForKey($key)->setex($key, 302400, $data);
            } else {
                // 有时间限制
                return $this->_getConForKey($key)->setex($key, $ttl, $data);
            }
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

    /**
     * 删除有分组的缓存
     * @param type $cache_id
     * @return type
     */
    public function delete($cache_id) {
        $key = $this->getCacheKey("{$this->group}_{$cache_id}");

        try {
            return $this->_getConForKey($key)->delete($key);
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

    /**
     * 对指定键名设置锁标记（此锁并不对键值做修改限制,仅为键名的锁标记）;
     * 此方法可用于防止惊群现象发生,在get方法获取键值无效时,先判断键名是否有锁标记,
     * 如果已加锁,则不获取新值;
     * 如果未加锁,则先设置锁，若设置失败说明锁已存在，若设置成功则获取新值,设置新的缓存
     * @param string $cache_id   键名
     * @param int $ttl     加锁时间
     * @return boolean      是否成功
     */
    public function lock($cache_id, $ttl = 5) {
        $key = "lock_{$cache_id}";

        $rs = $this->simple_get($key);
        if ($rs == true) {
            return false;
        }

        return $this->simple_set($key, true, $ttl);
    }

    /**
     * 判断键名是否有锁标记;<br>
     * 此方法可用于防止惊群现象发生,在get方法获取键值无效时,判断键名是否有锁标记
     * @param string $cache_id   键名
     * @return boolean      是否加锁
     */
    public function is_lock($cache_id) {
        $key = "lock_{$cache_id}";

        return $this->simple_get($key);
    }

    /**
     * 对指定键名移除锁标记
     * @param string $cache_id      键名
     * @return boolean              是否成功
     */
    public function unlock($cache_id) {
        $key = "lock_{$cache_id}";

        return $this->simple_delete($key);
    }

    /**
     * 简单设置缓存
     * @param type $cache_id    缓存 key
     * @param type $var         缓存值
     * @param type $ttl      有效期(秒)
     * @return
     */
    public function simple_set($cache_id, $var, $ttl = 0) {
        $key = $this->getCacheKey($cache_id);
        $var = $this->setValue($var);

        try {
            if ($ttl == 0) {
                return $this->_getConForKey($key)->set($key, $var);
            } else {
                // 有时间限制
                return $this->_getConForKey($key)->setex($key, $ttl, $var);
            }
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

    /**
     * 简单获取缓存
     * @param type $cache_id    缓存名称
     * @param type $default     默认返回　false
     * @return boolean
     */
    public function simple_get($cache_id, $default = false) {
        $key = $this->getCacheKey($cache_id);
        try {
            $value = $this->_getConForKey($key)->get($key);

            if (is_null($value) || false === $value) {
                return $default;
            }

            return $this->getValue($value, $default);
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

    /**
     * 简单删除缓存
     * @param type $cache_id
     * @return type
     */
    public function simple_delete($cache_id) {
        $key = $this->getCacheKey($cache_id);

        try {
            return $this->_getConForKey($key)->delete($key);
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

    /**
     * 操作次数限制函数: 限制 user_id 在 period 秒内能操作 action 最多 max_count 次.
     * 如果超过限制, 返回 false.
     * @param type $uid
     * @param type $action
     * @param type $max_count
     * @param type $period
     * @return boolean
     */
    public function act_limit($uid, $action, $max_count, $period) {
        $timestamp = time();
        $ttl = intval($timestamp / $period) * $period + $period;
        $ttl = $ttl - $timestamp;
        $cache_id = "act_limit_" . md5("{$uid}|{$action}");

        $count = $this->simple_get($cache_id);
        if ($count) {
            if ($count > $max_count) {
                return false;
            }
        } else {
            $count = 1;
        }
        $count += 1;

        return $this->simple_set($cache_id, $count, $ttl);
    }

    /**
     * 自增缓存（针对数值缓存）
     * @access public
     * @param  string    $key 缓存变量名
     * @param  int       $step 步长
     * @return false|int
     */
    public function simple_inc($key, $step = 1) {
        return $this->_getConForKey($key)->incrby($key, $step);
    }

    /**
     * 自减缓存（针对数值缓存）
     * @access public
     * @param  string    $key 缓存变量名
     * @param  int       $step 步长
     * @return false|int
     */
    public function simple_dec($key, $step = 1) {
        return $this->_getConForKey($key)->decrby($key, $step);
    }

    /**
     * 返回哈希表 key 中给定域 field 的值。
     * @param type $cache_id    缓存名称
     * @param type $id          ID
     * @param type $default     默认返回　false
     * @return boolean/给定域的值
     */
    public function hget($cache_id, $id, $default = false) {
        $key = $this->getCacheKey($cache_id);
        try {
            $value = $this->_getConForKey($key)->hget($key, $id);

            if (is_null($value) || false === $value) {
                return $default;
            }

            return $this->getValue($value, $default);
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

    /**
     * 将哈希表 key 中的域 field 的值设为 value 。
     * @param type $cache_id    缓存 key
     * @param type $id          ID
     * @param type $var         缓存值
     * @return
     */
    public function hset($cache_id, $id, $var) {
        $key = $this->getCacheKey($cache_id);
        $var = $this->setValue($var);

        try {
            return $this->_getConForKey($key)->hset($key, $id, $var);
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

    /**
     * 删除哈希表 key 中的一个指定域，不存在的域将被忽略。
     * @param type $cache_id
     * @param type $id          ID
     * @return boolean
     */
    public function hdel($cache_id, $id) {
        $key = $this->getCacheKey($cache_id);

        try {
            return $this->_getConForKey($key)->hdel($key, $id);
        } catch (\Exception $ex) {
            //连接状态置为false
            $this->isConnected = false;
            $this->is_available();
        }
        return false;
    }

    /**
     * 将一个或多个值 value 插入到列表 key 的表尾(最右边)。
     * @param type $name
     * @param type $data
     * @return type
     */
    public function rPush($name = 'queue_task', $data = []) {
        $data = $this->setValue($data);
        return $this->_getConForKey($name)->rPush($name, $data);
    }

    /**
     * 将一个值 value 插入到列表头部
     * @param type $name
     * @param type $data
     * @return type
     */
    public function lPush($name = 'queue_task', $data = []) {
        $data = $this->setValue($data);
        return $this->_getConForKey($name)->lPush($name, $data);
    }

    /**
     * 移除并返回列表 key 的尾元素。
     * @param type $name
     * @return boolean
     */
    public function rPop($name = 'queue_task') {
        $value = $this->_getConForKey($name)->rPop($name);
        if (is_null($value) || false === $value) {
            return false;
        }
        return $this->getValue($value, false);
    }

    /**
     * 返回列表 key 的长度
     * @param string $name
     * @return boolean/int
     */
    public function lLen($name = 'queue_task') {
        $rs = $this->_getConForKey($name)->lLen($name);
        if ($rs) {
            return $rs;
        }
        return 0;
    }

    /**
     * 获取状态
     * @return type
     */
    public function get_stats() {
        $data = [];
        foreach ($this->link as $key => $value) {
            $data[$key] = $this->link[$key]->info();
        }
        return $data;
    }

    /**
     * 最好能保证它能最后析构!
     * 关闭连接
     */
    public function __destruct() {
        if (!empty($this->link)) {
            foreach ($this->link as $key => $value) {
                unset($this->link[$key]);
            }
        }
        unset($this->link);
        unset($this->isConnected);
    }

}
